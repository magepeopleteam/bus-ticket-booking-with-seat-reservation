<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;

/**
 * The messages a product's team wants its installations to see: fetching
 * them, caching them, and remembering which ones this site has dismissed.
 *
 * ## No consent gate, on purpose
 *
 * Checked against the server before writing this class: only
 * /sdk/v1/telemetry reads installations.consent_status (journal §5.4's
 * fail-closed gate). Announcements are authenticated but NOT
 * consent-gated, and that is the correct asymmetry — consent governs data
 * collected FROM a site, while this is notification content sent TO it.
 * A site owner who refuses telemetry has not asked to stop being told
 * about a security release.
 *
 * So this class does not consult Consent either. Inventing a client-side
 * gate the server does not have would mean the SDK silently withholding
 * content the server was willing to serve, which is both a mismatch
 * nobody could debug from the dashboard and a decision the SDK has no
 * standing to make.
 *
 * ## It rides the heartbeat tick — no second cron schedule
 *
 * refresh() is registered on Telemetry::CRON_HOOK, the schedule S4.3
 * already created. WordPress runs every callback on a hook, so this costs
 * nothing but one more listener, and the 15-minute heartbeat interval is
 * already the right order of magnitude for "have the announcements
 * changed?". A second wp_cron entry would mean a second thing to
 * schedule, unschedule, and reason about on every activation path, for no
 * behavioural difference.
 *
 * There is one hole in relying on cron alone: a site with
 * DISABLE_WP_CRON and no system cron never ticks. Covered the same way
 * Lifecycle covers registration — a stale-cache refresh when the site
 * owner is already on the plugin's own settings screen, rate-limited to
 * one attempt an hour. Never on any other admin page: a signed API call
 * on every wp-admin page load is exactly what the cache exists to
 * prevent.
 */
final class Announcements {

	/** Refresh the cache when it is older than this, on the plugin's own screen. */
	const STALE_AFTER = 43200;

	/** Never more than one fallback attempt per hour, successful or not. */
	const MIN_ATTEMPT_INTERVAL = 3600;

	/**
	 * How many dismissals to remember. Bounded, because the option would
	 * otherwise grow forever on a long-lived site.
	 *
	 * Kept as a cap rather than pruning ids that are no longer served: an
	 * announcement can be unpublished and published again, and a site
	 * owner seeing a dismissed notice come back because the organization
	 * edited it is precisely the annoyance dismissal exists to prevent.
	 * 200 dismissals is far beyond what any product will publish.
	 */
	const MAX_DISMISSALS = 200;

	/**
	 * Urgency order for display. Recency decides within a type; this
	 * decides between them, so a Security Notice is never queued behind a
	 * discount (see AnnouncementNotices for why they stack at all).
	 */
	const TYPE_PRIORITY = array(
		'security' => 0,
		'update'   => 1,
		'feature'  => 2,
		'discount' => 3,
	);

	/** @var Client */
	private $client;

	/** @var Logger */
	private $logger;

	/** @var string */
	private $key;

	/** @var RealtimeConfig|null */
	private $realtime_config;

	/**
	 * @param RealtimeConfig|null $realtime_config The shared circuit
	 *        breaker (13-realtime-config-delivery.md). Optional so
	 *        existing callers keep working; without one, refresh() has
	 *        no way to know a circuit is open and simply always attempts
	 *        the network, exactly as before this was introduced.
	 */
	public function __construct( Client $client, ?Logger $logger = null, ?RealtimeConfig $realtime_config = null ) {
		$this->client          = $client;
		$this->logger          = null !== $logger ? $logger : new NullLogger();
		$this->key             = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
		$this->realtime_config = $realtime_config;
	}

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// The existing heartbeat tick. Not a schedule of our own — see the
		// class doc.
		add_action( Telemetry::CRON_HOOK, array( $this, 'refresh' ) );
	}

	// -----------------------------------------------------------------
	// Reading
	// -----------------------------------------------------------------

	/**
	 * What should be shown right now: everything cached that this site has
	 * not dismissed, most urgent type first and newest first within a type.
	 *
	 * Reads the cache only — never makes a request, because the caller is
	 * a page render.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function visible() {
		$dismissed = $this->dismissed();

		$announcements = array();

		foreach ( $this->cached() as $announcement ) {
			if ( isset( $dismissed[ $announcement['id'] ] ) ) {
				continue;
			}

			$announcements[] = $announcement;
		}

		// The server already ordered these newest-first and already
		// excluded anything unpublished, unstarted or expired (journal
		// §9.3b evaluates the window at request time). This re-sorts by
		// urgency WITHOUT re-filtering: the client must not second-guess
		// which announcements are live, or a clock skew between the site
		// and the server would hide something the server chose to serve.
		usort(
			$announcements,
			function ( array $a, array $b ) {
				$rank = $this->priority_of( $a['type'] ) - $this->priority_of( $b['type'] );

				if ( 0 !== $rank ) {
					return $rank;
				}

				// Preserve the server's own ordering within a type.
				return $a['_order'] - $b['_order'];
			}
		);

		return $announcements;
	}

	/** @return array<int, array<string, mixed>> Everything cached, dismissed or not. */
	public function all() {
		return $this->cached();
	}

	/** @param string $type */
	private function priority_of( $type ) {
		return isset( self::TYPE_PRIORITY[ $type ] )
			? self::TYPE_PRIORITY[ $type ]
			// An unknown type from a newer server sorts last rather than
			// first: it is not worth pushing above a security notice on a
			// guess.
			: count( self::TYPE_PRIORITY );
	}

	// -----------------------------------------------------------------
	// Fetching
	// -----------------------------------------------------------------

	/**
	 * GET /sdk/v1/announcements and replace the cache. The cron callback.
	 *
	 * @return array<int, array<string, mixed>>|null Null when no request
	 *                                               was made or it failed.
	 */
	public function refresh() {
		if ( ! $this->client->credentials()->has_credentials() ) {
			return null;
		}

		// The shared circuit breaker: after repeated failures anywhere in
		// the realtime-config feature, skip the network here too rather
		// than adding this call's own failure to a server that is already
		// known to be unreachable or rate-limiting.
		if ( null !== $this->realtime_config && $this->realtime_config->is_open() ) {
			return null;
		}

		$this->record_attempt();

		$response = $this->client->get( '/sdk/v1/announcements' );

		if ( ! $response->ok() ) {
			// Cache deliberately left alone. A 403 (the installation is
			// not active) or a 500 is not evidence that the product has
			// stopped announcing anything, and blanking the list on a
			// failed poll would make a security notice vanish because a
			// request timed out.
			$this->logger->error(
				'Could not refresh announcements; the cached copy is kept.',
				array(
					'status' => $response->status(),
					'error'  => $response->error_message(),
				)
			);

			if ( null !== $this->realtime_config ) {
				$this->realtime_config->record_failure(
					$response->is_rate_limited() ? $response->rate_limit()->retry_after() : null
				);
			}

			return null;
		}

		if ( null !== $this->realtime_config ) {
			$this->realtime_config->record_success();
			$this->realtime_config->clear_stale();
		}

		$announcements = $this->normalize( $response->get( 'announcements', array() ) );

		$this->cache( $announcements );

		return $announcements;
	}

	/**
	 * The fallback for sites where WP-Cron never runs, called by the
	 * notice renderer — and only from the plugin's own settings screen.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	public function maybe_refresh() {
		$fetched_at = (int) $this->stored( 'fetched_at', 0 );

		if ( 0 !== $fetched_at && ( time() - $fetched_at ) < self::STALE_AFTER ) {
			return null;
		}

		$last_attempt = (int) $this->stored( 'attempted_at', 0 );

		if ( 0 !== $last_attempt && ( time() - $last_attempt ) < self::MIN_ATTEMPT_INTERVAL ) {
			// Rate-limited on the ATTEMPT, not the success, so an
			// unreachable API cannot mean a doomed request every time the
			// owner reloads their own settings page.
			return null;
		}

		return $this->refresh();
	}

	/**
	 * Keeps exactly the display fields, drops anything unrenderable, and
	 * records the server's ordering so it can be preserved through the
	 * client's urgency sort.
	 *
	 * `_order` is the only field this SDK adds. Nothing else is derived:
	 * journal §12.2 makes this payload display-only, and the shape here is
	 * where that is enforced client-side — there is no field an SDK could
	 * act on even if a future response tried to supply one.
	 *
	 * @param mixed $announcements
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize( $announcements ) {
		if ( ! is_array( $announcements ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $announcements as $announcement ) {
			if ( ! is_array( $announcement ) ) {
				continue;
			}

			if ( empty( $announcement['id'] ) || ! isset( $announcement['title'] ) ) {
				continue;
			}

			$title = trim( (string) $announcement['title'] );

			if ( '' === $title ) {
				continue;
			}

			$normalized[] = array(
				'id'         => (string) $announcement['id'],
				'type'       => isset( $announcement['type'] ) ? (string) $announcement['type'] : 'update',
				'title'      => $title,
				'body'       => isset( $announcement['body'] ) ? (string) $announcement['body'] : '',
				'starts_at'  => isset( $announcement['starts_at'] ) ? (string) $announcement['starts_at'] : null,
				'expires_at' => isset( $announcement['expires_at'] ) ? (string) $announcement['expires_at'] : null,
				'_order'     => count( $normalized ),
			);
		}

		return $normalized;
	}

	// -----------------------------------------------------------------
	// Dismissals
	// -----------------------------------------------------------------

	/**
	 * Records a dismissal for this site. Kept in its OWN option, not in
	 * the cached list: the list is replaced wholesale on every refresh,
	 * and a dismissal that lived inside it would be forgotten the moment
	 * the cache updated — which is exactly the bug this feature exists to
	 * not have.
	 *
	 * @param string $id
	 * @return bool False when the id is not one of the cached announcements.
	 */
	public function dismiss( $id ) {
		$id = (string) $id;

		if ( '' === $id ) {
			return false;
		}

		// Only a real announcement can be dismissed, so a spoofed or stale
		// id cannot fill the option with junk that counts against
		// MAX_DISMISSALS and evicts genuine dismissals.
		$known = false;

		foreach ( $this->cached() as $announcement ) {
			if ( $announcement['id'] === $id ) {
				$known = true;
				break;
			}
		}

		if ( ! $known ) {
			return false;
		}

		$dismissed = $this->dismissed();

		if ( isset( $dismissed[ $id ] ) ) {
			return true;
		}

		$dismissed[ $id ] = time();

		// Oldest out first when capped.
		if ( count( $dismissed ) > self::MAX_DISMISSALS ) {
			asort( $dismissed );
			$dismissed = array_slice( $dismissed, count( $dismissed ) - self::MAX_DISMISSALS, null, true );
		}

		$this->write_dismissals( $dismissed );

		return true;
	}

	/** @param string $id */
	public function is_dismissed( $id ) {
		$dismissed = $this->dismissed();

		return isset( $dismissed[ (string) $id ] );
	}

	/** @return array<string, int> id => timestamp. */
	public function dismissed() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$stored = get_option( $this->option_name( 'announcements_dismissed' ), array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$clean = array();

		foreach ( $stored as $id => $timestamp ) {
			if ( is_string( $id ) && is_numeric( $timestamp ) ) {
				$clean[ $id ] = (int) $timestamp;
			}
		}

		return $clean;
	}

	/** @param array<string, int> $dismissed */
	private function write_dismissals( array $dismissed ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $this->option_name( 'announcements_dismissed' ), $dismissed, false );
		}
	}

	// -----------------------------------------------------------------
	// Cache
	// -----------------------------------------------------------------

	/** @return array<int, array<string, mixed>> */
	private function cached() {
		$stored = $this->stored( 'announcements', array() );

		return is_array( $stored ) ? $stored : array();
	}

	public function fetched_at() {
		return (int) $this->stored( 'fetched_at', 0 );
	}

	/** @param array<int, array<string, mixed>> $announcements */
	private function cache( array $announcements ) {
		$this->write(
			array(
				'fetched_at'    => time(),
				'attempted_at'  => time(),
				'announcements' => $announcements,
			)
		);
	}

	private function record_attempt() {
		$stored                 = $this->read();
		$stored['attempted_at'] = time();
		$this->write( $stored );
	}

	/** Called at uninstall — the cache and the dismissals are plugin data. */
	public function forget() {
		if ( ! function_exists( 'delete_option' ) ) {
			return;
		}

		delete_option( $this->option_name( 'announcements' ) );
		delete_option( $this->option_name( 'announcements_dismissed' ) );
	}

	/**
	 * @param string $field
	 * @param mixed  $default
	 * @return mixed
	 */
	private function stored( $field, $default = null ) {
		$stored = $this->read();

		return isset( $stored[ $field ] ) ? $stored[ $field ] : $default;
	}

	/** @return array<string, mixed> */
	private function read() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$stored = get_option( $this->option_name( 'announcements' ), array() );

		return is_array( $stored ) ? $stored : array();
	}

	/** @param array<string, mixed> $value */
	private function write( array $value ) {
		if ( function_exists( 'update_option' ) ) {
			// autoload 'no': read on the plugin's own settings screen and
			// on the cron tick, never on an ordinary page load.
			update_option( $this->option_name( 'announcements' ), $value, false );
		}
	}

	private function option_name( $suffix ) {
		return 'appneck_sdk_' . $suffix . '_' . $this->key;
	}
}
