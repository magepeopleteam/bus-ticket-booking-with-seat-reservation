<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;

/**
 * The site owner's telemetry decision: where it is stored, what it means
 * for track(), and how it reaches the server.
 *
 * The server is the enforcement authority (journal §5.4: /sdk/v1/telemetry
 * fails closed with a 403 on `pending` or `rejected`). Nothing here is a
 * security control — the point of this class is that the SDK behaves
 * decently on the site owner's own machine, and that their answer
 * actually gets recorded.
 *
 * ## Three states, and only one of them is a decision the owner made
 *
 * `pending`  — never asked, or asked and not yet answered. The prompt is
 *              on screen. Nothing has been decided, so track() keeps
 *              buffering locally: transmission is imminently possible and
 *              the server refuses the batch anyway (which S4.3 already
 *              proves the queue survives). This is the state where "keep
 *              the backlog" is right, because a grant a minute later
 *              should ship what happened in that minute.
 * `accepted` — normal operation.
 * `rejected` — track() becomes a NO-OP and the existing local queue is
 *              PURGED. See below; this is the one genuinely debatable
 *              decision in this class.
 *
 * ## Why an explicit reject stops local collection, not just sending
 *
 * The alternative — keep writing rows to the site's own database that can
 * never be sent — was rejected for three reasons:
 *
 *  1. It is still behaving like a tracker on a system that said no. The
 *     site owner cannot see our outbound requests, but they can see rows
 *     accumulating in their database, and "we only collect it, we don't
 *     send it" is exactly the defence nobody accepts. Journal §5.4's
 *     fail-closed / privacy-by-default principle is about not collecting
 *     without permission, not merely about not transmitting.
 *  2. Data minimisation: the only justification the local buffer ever had
 *     is imminent transmission. After a reject there is none, so the rows
 *     are storage on someone else's server with no purpose.
 *  3. If the owner later changes their mind, a retained backlog would
 *     ship events collected during precisely the window they had said no
 *     — a worse outcome than having dropped them. Which is why reject
 *     purges rather than parks: the queue must not become a way to
 *     collect through a refusal and send it later.
 *
 * `pending` is deliberately NOT treated the same way. "Never asked" and
 * "said no" are different facts, and collapsing them would either throw
 * away legitimate startup telemetry (if pending behaved like rejected) or
 * ignore a refusal (if rejected behaved like pending).
 *
 * ## Privacy policy version
 *
 * The server requires one on every POST /sdk/v1/consent and stores it on
 * the permanent consent_events row, so the SDK must send a real value.
 * It comes from the `appneck_sdk_privacy_policy_version` filter (or
 * set_privacy_policy_version() outside WordPress), because only the
 * plugin author knows their own policy.
 *
 * A version change re-shows the prompt for a previously ACCEPTED
 * decision, and deliberately does NOT block telemetry while it is
 * unanswered — see needs_decision() for the reasoning, which is the
 * honest limit of what the server currently supports.
 */
final class Consent {

	const STATUS_PENDING  = 'pending';
	const STATUS_ACCEPTED = 'accepted';
	const STATUS_REJECTED = 'rejected';

	const DEFAULT_PRIVACY_POLICY_VERSION = '1.0';

	const CRON_HOOK = 'appneck_sdk_consent_sync';

	/** Same widening shape as Lifecycle's registration backoff. */
	const SYNC_BACKOFF = array( 60, 300, 900, 3600, 21600, 86400 );

	const MAX_SYNC_ATTEMPTS = 12;

	/** @var Client */
	private $client;

	/** @var Telemetry|null */
	private $telemetry;

	/** @var Logger */
	private $logger;

	/** @var string Per-product suffix for this SDK's option names. */
	private $key;

	/** @var string|null Set explicitly, bypassing the filter. */
	private $privacy_policy_version = null;

	public function __construct( Client $client, ?Telemetry $telemetry = null, ?Logger $logger = null ) {
		$this->client    = $client;
		$this->telemetry = $telemetry;
		$this->logger    = null !== $logger ? $logger : new NullLogger();
		$this->key       = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
	}

	/**
	 * Wired separately from the constructor because Telemetry consults
	 * Consent and Consent acts on Telemetry — a mutual reference that has
	 * to be resolved somewhere, and a setter on one side keeps both
	 * constructible (and testable) alone.
	 */
	public function set_telemetry( ?Telemetry $telemetry ) {
		$this->telemetry = $telemetry;
	}

	/** The per-product option/action suffix, shared with the admin notice. */
	public function key() {
		return $this->key;
	}

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( self::CRON_HOOK, array( $this, 'sync' ) );

		// Fallback for sites where WP-Cron never runs, and for the very
		// common case where the decision was made before registration
		// finished. Cheap: exits on one option read when synced.
		add_action( 'admin_init', array( $this, 'maybe_sync_on_admin_init' ) );
	}

	// -----------------------------------------------------------------
	// Reading the decision
	// -----------------------------------------------------------------

	/** @return string pending|accepted|rejected */
	public function status() {
		$stored = $this->read();
		$status = isset( $stored['status'] ) ? (string) $stored['status'] : self::STATUS_PENDING;

		// A hand-edited or corrupted option must read as the safe value,
		// not as consent nobody gave.
		return in_array( $status, array( self::STATUS_ACCEPTED, self::STATUS_REJECTED ), true )
			? $status
			: self::STATUS_PENDING;
	}

	public function is_accepted() {
		return self::STATUS_ACCEPTED === $this->status();
	}

	public function is_rejected() {
		return self::STATUS_REJECTED === $this->status();
	}

	public function is_pending() {
		return self::STATUS_PENDING === $this->status();
	}

	/** ISO-8601 of when the decision was made, or null if undecided. */
	public function decided_at() {
		$stored = $this->read();

		return isset( $stored['decided_at'] ) ? (string) $stored['decided_at'] : null;
	}

	/** The policy version the stored decision was made under, or null. */
	public function decided_version() {
		$stored = $this->read();

		return isset( $stored['privacy_policy_version'] ) ? (string) $stored['privacy_policy_version'] : null;
	}

	/** False once the server has the decision. */
	public function is_sync_pending() {
		$stored = $this->read();

		if ( empty( $stored ) || ! isset( $stored['status'] ) ) {
			return false;
		}

		return empty( $stored['synced'] );
	}

	/**
	 * Whether the prompt should be shown.
	 *
	 * True while pending, obviously. Also true when an ACCEPTED decision
	 * was made under an older privacy policy version: consent given to
	 * one policy does not automatically cover a revised one, and asking
	 * again is the whole reason the server stores a version per consent
	 * event rather than a single flag.
	 *
	 * What it deliberately does NOT do is block telemetry in the meantime.
	 * The server has no notion of a current policy version to compare
	 * against — it only records the version each decision was made under —
	 * so a client-side block would put the installation in a state the
	 * server reads as `accepted` while it silently stops reporting, which
	 * then trips the lost-installation detector (journal §8.4) for a site
	 * that is perfectly healthy. Prior consent stands until the owner
	 * answers; the re-confirmation appends a fresh consent_events row
	 * under the new version, which is the durable record that matters.
	 *
	 * A REJECTED decision is never re-prompted on a version change.
	 * Re-asking someone who said no, because we edited our own policy,
	 * is nagging — they have the change-decision control for that.
	 */
	public function needs_decision() {
		if ( $this->is_pending() ) {
			return true;
		}

		if ( ! $this->is_accepted() ) {
			return false;
		}

		if ( ! $this->reprompt_on_policy_change() ) {
			return false;
		}

		$decided = $this->decided_version();

		return null !== $decided && $decided !== $this->privacy_policy_version();
	}

	/**
	 * Filterable off for the case this exists to serve: a version bumped
	 * for a typo fix should not ask every site in the field to click
	 * again, and only the plugin author can know the difference.
	 */
	private function reprompt_on_policy_change() {
		if ( ! function_exists( 'apply_filters' ) ) {
			return true;
		}

		return (bool) apply_filters(
			'appneck_sdk_reprompt_on_policy_change',
			true,
			$this->client->config()->api_key()
		);
	}

	// -----------------------------------------------------------------
	// Privacy policy version
	// -----------------------------------------------------------------

	/**
	 * Explicit setter for callers with no WordPress filter system (the
	 * package's own tests, WP-CLI harnesses). Wins over the filter.
	 *
	 * @param string|null $version
	 */
	public function set_privacy_policy_version( $version ) {
		$this->privacy_policy_version = null === $version ? null : (string) $version;
	}

	public function privacy_policy_version() {
		if ( null !== $this->privacy_policy_version && '' !== $this->privacy_policy_version ) {
			return $this->normalize_version( $this->privacy_policy_version );
		}

		$version = self::DEFAULT_PRIVACY_POLICY_VERSION;

		if ( function_exists( 'apply_filters' ) ) {
			$version = apply_filters(
				'appneck_sdk_privacy_policy_version',
				$version,
				$this->client->config()->api_key()
			);
		}

		return $this->normalize_version( $version );
	}

	/**
	 * The server's rule is required|string|max:255. Applied to the setter
	 * and the filter alike, in one place — a value that makes every consent
	 * call a 422 the site owner cannot diagnose is not worth passing
	 * through faithfully.
	 *
	 * @param mixed $version
	 * @return string
	 */
	private function normalize_version( $version ) {
		$version = is_scalar( $version ) ? (string) $version : '';

		if ( '' === $version ) {
			return self::DEFAULT_PRIVACY_POLICY_VERSION;
		}

		return substr( $version, 0, 255 );
	}

	// -----------------------------------------------------------------
	// Making a decision
	// -----------------------------------------------------------------

	/**
	 * @return Response|null The consent call's response, or null when it
	 *                       could not be attempted yet (not registered).
	 */
	public function accept() {
		return $this->decide( self::STATUS_ACCEPTED );
	}

	/** @return Response|null */
	public function reject() {
		return $this->decide( self::STATUS_REJECTED );
	}

	/**
	 * @param string $status accepted|rejected
	 * @return Response|null
	 */
	public function decide( $status ) {
		if ( ! in_array( $status, array( self::STATUS_ACCEPTED, self::STATUS_REJECTED ), true ) ) {
			return null;
		}

		// Stored FIRST, before any network call. The owner clicked a
		// button; their answer must survive an unreachable API, and the
		// prompt must not come back on the next page load as though the
		// click did nothing. Sending is a separate, retryable concern.
		$this->write(
			array(
				'status'                 => $status,
				'decided_at'             => gmdate( 'c' ),
				'privacy_policy_version' => $this->privacy_policy_version(),
				'synced'                 => false,
				'sync_attempts'          => 0,
				'last_sync_attempt'      => 0,
			)
		);

		$this->apply_to_telemetry( $status );

		return $this->sync();
	}

	/**
	 * The local consequences of a decision, applied immediately rather
	 * than waiting for the server round trip — the site owner's own
	 * machine should behave correctly even if the API never answers.
	 *
	 * @param string $status
	 */
	private function apply_to_telemetry( $status ) {
		if ( null === $this->telemetry ) {
			return;
		}

		if ( self::STATUS_REJECTED === $status ) {
			// Stop collecting, and drop what was collected while the
			// question was still open. See the class doc for why parking
			// the backlog was rejected.
			$this->telemetry->queue()->purge();
			$this->telemetry->unschedule();

			return;
		}

		// A pending-consent 403 will have suppressed flushing for an hour
		// (Telemetry::CONSENT_RETRY_SECONDS). Consent exists now, so lift
		// that immediately instead of making the owner wait out a back-off
		// that was measuring a condition they just resolved.
		$this->telemetry->resume();
		$this->telemetry->schedule();
	}

	/**
	 * Discards the local record. Called from uninstall: the decision is
	 * the plugin's own data, and the server keeps the permanent history
	 * (consent_events) regardless.
	 */
	public function forget() {
		$this->unschedule();
		$this->delete_option( 'consent' );
	}

	// -----------------------------------------------------------------
	// Getting it to the server
	// -----------------------------------------------------------------

	/**
	 * POST /sdk/v1/consent. Safe to call any number of times — it exits
	 * immediately when there is nothing outstanding.
	 *
	 * @return Response|null
	 */
	public function sync() {
		$stored = $this->read();

		if ( empty( $stored['status'] ) || ! empty( $stored['synced'] ) ) {
			return null;
		}

		if ( ! $this->client->credentials()->has_credentials() ) {
			// Registration is asynchronous (S4.2: activation performs no
			// network I/O), so a decision made in the first seconds after
			// activation regularly lands here. Nothing was attempted, so
			// no attempt is counted against MAX_SYNC_ATTEMPTS — otherwise
			// a site whose registration is retrying on the 24h backoff
			// would burn its whole consent budget waiting.
			$this->schedule_attempt( 0 );

			return null;
		}

		$attempts = (int) ( isset( $stored['sync_attempts'] ) ? $stored['sync_attempts'] : 0 );

		if ( $attempts >= self::MAX_SYNC_ATTEMPTS ) {
			return null;
		}

		$this->record_attempt( $attempts + 1 );

		$response = $this->client->post(
			'/sdk/v1/consent',
			array(
				'status'                 => (string) $stored['status'],
				'privacy_policy_version' => isset( $stored['privacy_policy_version'] )
					? (string) $stored['privacy_policy_version']
					: $this->privacy_policy_version(),
			)
		);

		if ( $response->ok() ) {
			$this->mark_synced();

			return $response;
		}

		// Everything else is retried, including a 403/409. The consent
		// endpoint is `scoped` tier (journal §9.2 step 4) precisely so a
		// deactivated installation can still withdraw, so there is no
		// permanent-failure shortcut worth special-casing here — and the
		// local decision is already in force either way.
		$this->logger->error(
			'Consent could not be recorded with the server; it is stored locally and will retry.',
			array(
				'status' => $response->status(),
				'error'  => $response->error_message(),
			)
		);

		$this->schedule_attempt( $attempts + 1 );

		return $response;
	}

	/**
	 * Belt-and-braces for sites where WP-Cron cannot run, rate-limited so
	 * an unreachable API never means a request on every admin page load.
	 */
	public function maybe_sync_on_admin_init() {
		if ( ! $this->is_sync_pending() ) {
			return;
		}

		$stored = $this->read();
		$last   = (int) ( isset( $stored['last_sync_attempt'] ) ? $stored['last_sync_attempt'] : 0 );

		if ( $last > 0 && ( time() - $last ) < HOUR_IN_SECONDS_FALLBACK ) {
			return;
		}

		$this->sync();
	}

	// -----------------------------------------------------------------
	// Scheduling
	// -----------------------------------------------------------------

	private function schedule_attempt( $attempt_number ) {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}

		$index = min( (int) $attempt_number, count( self::SYNC_BACKOFF ) - 1 );
		$delay = 0 === (int) $attempt_number ? self::SYNC_BACKOFF[0] : self::SYNC_BACKOFF[ $index ];

		$this->unschedule();

		wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
	}

	private function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	// -----------------------------------------------------------------
	// Storage
	// -----------------------------------------------------------------

	private function mark_synced() {
		$stored           = $this->read();
		$stored['synced'] = true;
		$this->write( $stored );
		$this->unschedule();
	}

	private function record_attempt( $number ) {
		$stored                      = $this->read();
		$stored['sync_attempts']     = (int) $number;
		$stored['last_sync_attempt'] = time();
		$this->write( $stored );
	}

	/**
	 * One option holding the whole decision, for the same reason
	 * WpOptionsCredentialStore keeps the id and secret together: a status
	 * without the date and policy version it was decided under is not a
	 * consent record, it is a fragment, and separate writes can be
	 * half-restored from a backup.
	 *
	 * autoload IS enabled here, unlike the credential store's. Credentials
	 * are read only when the SDK talks to the API; this value is read by
	 * every track() call, so leaving it out of the autoload blob would
	 * trade one shared read for an extra query on page loads we are a
	 * guest in.
	 *
	 * @param array<string, mixed> $value
	 */
	private function write( array $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $this->option_name( 'consent' ), $value, true );
		}
	}

	/** @return array<string, mixed> */
	private function read() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$stored = get_option( $this->option_name( 'consent' ), array() );

		return is_array( $stored ) ? $stored : array();
	}

	private function option_name( $suffix ) {
		return 'appneck_sdk_' . $suffix . '_' . $this->key;
	}

	private function delete_option( $suffix ) {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $this->option_name( $suffix ) );
		}
	}
}

if ( ! defined( 'Appneck\\Sdk\\HOUR_IN_SECONDS_FALLBACK' ) ) {
	/**
	 * Also defined by Lifecycle.php, and guarded in both — under Composer's
	 * PSR-4 autoloading either class can be the only one loaded, so neither
	 * may rely on the other's file having been included.
	 */
	define( 'Appneck\\Sdk\\HOUR_IN_SECONDS_FALLBACK', 3600 );
}
