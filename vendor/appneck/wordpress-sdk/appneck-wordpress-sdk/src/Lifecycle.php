<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Queue\TableEventQueue;

/**
 * Registration on activation, status updates through the plugin's life,
 * and the retry machinery that makes both safe.
 *
 * ## Activation never waits on the API
 *
 * The single most important rule here: a WordPress plugin must activate
 * successfully whether or not Appneck is reachable at that instant. So
 * `register_activation_hook` performs NO network I/O at all. It marks
 * the site as needing registration and schedules an immediate cron
 * event; the first attempt happens on the next page load, milliseconds
 * to seconds later, in a request nobody is watching.
 *
 * The alternative — call the API during activation with a short
 * timeout — was rejected. If the API is merely slow or a firewall
 * blackholes the connection, every activation stalls for the full
 * timeout, and the person doing it experiences that as "this plugin is
 * broken". Deferring also means the first attempt and every retry run
 * the same code path, so the path that matters is the one exercised
 * every time rather than a rarely-hit fallback.
 *
 * ## Retry: escalating single events, capped
 *
 * A failed attempt schedules the next one on a widening backoff (1m, 5m,
 * 15m, 1h, 6h, 24h, then daily) and stops after MAX_ATTEMPTS. Single
 * events rather than a recurring schedule so a site that registers on
 * the first try never carries a permanent cron entry, and so the delay
 * can widen — a recurring event has one fixed interval.
 *
 * WP-Cron only fires when the site gets traffic, which is exactly right:
 * a site nobody visits is a site with nothing to report. For sites with
 * `DISABLE_WP_CRON` and no system cron, `admin_init` is a belt-and-braces
 * fallback, rate-limited to one attempt per hour.
 *
 * ## Registration is idempotent, so re-activation needs no special case
 *
 * The server's POST /sdk/v1/installations is create-or-reactivate
 * (journal 9.3): a call for an installation that already exists and is
 * deactivated reactivates the same record and fires
 * InstallationReactivated. So deactivate → reactivate simply runs the
 * normal registration flow again with the stored id, and no client-side
 * reactivation logic exists at all.
 *
 * ## Multisite: lazily, per site
 *
 * See ensure_registered() and the README for the reasoning.
 */
final class Lifecycle {

	const CRON_HOOK = 'appneck_sdk_register';

	const MAX_ATTEMPTS = 12;

	/** Backoff in seconds; the last value repeats until MAX_ATTEMPTS. */
	const BACKOFF = array( 60, 300, 900, 3600, 21600, 86400 );

	/**
	 * Statuses where retrying can never succeed, so the SDK stops at once
	 * instead of spending its twelve attempts learning that the hard way.
	 *
	 *  403 — the product is archived and is not accepting installations.
	 *  409 — an installation already exists for this site and product.
	 *        Reached when a site lost its stored credentials and enrolled
	 *        under a new id; the conflict is on (site, product), so every
	 *        retry hits the same wall. Self-service recovery from this
	 *        state does not exist yet by explicit decision (journal §9.5),
	 *        which makes retrying pure noise on someone else's server.
	 *
	 * Both are reset by a deactivate/reactivate cycle, since on_activate()
	 * clears the attempt counter — so if an operator resolves the conflict
	 * server-side, toggling the plugin retries.
	 */
	const PERMANENT_FAILURE_STATUSES = array( 403, 409 );

	/** @var Client */
	private $client;

	/** @var Environment */
	private $environment;

	/** @var string|null */
	private $plugin_file;

	/** @var string Per-product suffix for this SDK's option names. */
	private $key;

	/** @var Telemetry|null */
	private $telemetry;

	/** @var RealtimeConfig|null */
	private $realtime_config;

	public function __construct( Client $client, $plugin_file = null, ?Environment $environment = null, ?Telemetry $telemetry = null, ?RealtimeConfig $realtime_config = null ) {
		$this->client          = $client;
		$this->plugin_file     = $plugin_file;
		$this->realtime_config = $realtime_config;
		$this->environment = null !== $environment ? $environment : new Environment( $plugin_file );
		$this->telemetry   = $telemetry;
		$this->key         = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
	}

	/**
	 * Wire every hook. Call this unconditionally on every request, at
	 * load time — the hooks themselves decide when to act.
	 */
	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		if ( null !== $this->plugin_file && function_exists( 'register_activation_hook' ) ) {
			register_activation_hook( $this->plugin_file, array( $this, 'on_activate' ) );
			register_deactivation_hook( $this->plugin_file, array( $this, 'on_deactivate' ) );
		}

		add_action( self::CRON_HOOK, array( $this, 'ensure_registered' ) );

		// Fallback for sites where WP-Cron cannot run. Cheap: it exits on
		// an option read when there is nothing to do.
		add_action( 'admin_init', array( $this, 'maybe_retry_on_admin_init' ) );
	}

	// -----------------------------------------------------------------
	// Activation
	// -----------------------------------------------------------------

	/**
	 * NO network I/O. See the class doc.
	 *
	 * @param bool $network_wide True when network-activated on multisite.
	 */
	public function on_activate( $network_wide = false ) {
		// Deliberately not looping the network's sites even when
		// $network_wide is true. A 500-site network would mean 500
		// synchronous API calls inside one activation request — a
		// guaranteed timeout, and precisely the kind of thing that gets
		// an SDK blamed for taking a network down. Each site registers
		// itself lazily instead; see ensure_registered().
		$this->mark_pending();

		// Forces the next attempt to call the server even though
		// credentials may already exist. Reactivation depends on it: the
		// bootstrap endpoint is create-or-reactivate (journal 9.3), and a
		// site that was deactivated is still `deactivated` server-side
		// until something says otherwise. Without this the plugin would
		// be active locally and deactivated remotely — and telemetry,
		// which is `active`-tier, would be refused with nothing to
		// explain why.
		$this->update_option( 'force', 1 );
		$this->schedule_attempt( 0 );

		// The events table has to exist before the first track() call,
		// and activation is the one moment WordPress guarantees us for
		// schema work. Failure is non-fatal — TableEventQueue degrades to
		// storing nothing rather than taking the site down.
		TableEventQueue::install();

		if ( null !== $this->telemetry ) {
			$this->telemetry->schedule();
		}
	}

	/**
	 * Registers this site if it is not registered already. The cron
	 * callback, the admin_init fallback, and any manual call all land
	 * here.
	 *
	 * The guard is `has_credentials()`, NOT the pending flag, and that is
	 * what makes multisite work: options are per-site, so on a
	 * network-activated plugin each site independently observes "I have
	 * no credentials" the first time this runs in its context, and
	 * registers itself. New sites added to the network later are handled
	 * by the same rule, with no site-creation hook needed.
	 *
	 * @return Response|null Null when nothing needed doing.
	 */
	public function ensure_registered() {
		$already_registered = $this->client->credentials()->has_credentials();
		$force              = (bool) $this->get_option( 'force', 0 );

		if ( $already_registered && ! $force ) {
			$this->clear_pending();

			return null;
		}

		$attempts = $this->attempts();

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			// Stop rather than retry forever. A site that has failed a
			// dozen times over several days is misconfigured or
			// firewalled, and continuing to try is just load on someone
			// else's server. Reactivating the plugin resets this.
			return null;
		}

		$this->record_attempt( $attempts + 1 );

		$installation_id = $this->installation_id();
		$payload         = $this->environment->collect();

		// journal 9.2b: present only when on_uninstall() stored one on
		// THIS site's last removal. Harmless to send when there is
		// nothing to reclaim — the server only ever looks at it if a
		// different id already occupies this (site, product) pair.
		$reclaim_token = $this->get_option( 'reclaim_token', '' );

		if ( is_string( $reclaim_token ) && '' !== $reclaim_token ) {
			$payload['reclaim_token'] = $reclaim_token;
		}

		$response = $this->client->post(
			'/sdk/v1/installations',
			$payload,
			Client::MODE_BOOTSTRAP,
			$installation_id
		);

		$this->note_config_version( $response );

		if ( ! $response->ok() ) {
			if ( in_array( $response->status(), self::PERMANENT_FAILURE_STATUSES, true ) ) {
				$this->give_up();

				return $response;
			}

			$this->schedule_attempt( $attempts + 1 );

			return $response;
		}

		$secret = $response->get( 'installation_secret' );

		if ( is_string( $secret ) && '' !== $secret ) {
			// journal 9.2b: a successful reclaim returns the EXISTING
			// row's real id, which the server chose — not necessarily
			// the id this request was sent under (a fresh enrolment
			// mints its own; a reclaim keeps the original one and this
			// client's freshly-generated id is discarded). Always defer
			// to whatever id the response actually carries, falling back
			// to the one sent only if the field is somehow absent.
			$confirmed_id = $response->get( 'id' );
			$confirmed_id = is_string( $confirmed_id ) && '' !== $confirmed_id ? $confirmed_id : $installation_id;

			// A fresh enrolment or a reclaim: the one and only time the
			// server discloses this installation's secret (journal §9.2a).
			$this->client->credentials()->save( $confirmed_id, $secret );
			$this->delete_option( 'reclaim_token' );
		} elseif ( ! $already_registered ) {
			// No secret, and none stored. The server knows this id but
			// will never re-disclose its secret, so nothing here can ever
			// sign a request — the only recovery is to enrol afresh under
			// a NEW id, which happens automatically since
			// installation_id() mints one when none is stored.
			$this->forget_installation_id();
			$this->schedule_attempt( $attempts + 1 );

			return $response;
		}
		// The remaining case — no secret returned but credentials already
		// stored — is a REACTIVATION, and is entirely expected: the server
		// reactivated the existing record and correctly declined to
		// re-issue its secret. The stored pair is still valid; keeping it
		// is the whole point.

		$this->clear_pending();
		$this->forget_installation_id();
		$this->delete_option( 'reclaim_token' );

		// A previous 403 may have stopped telemetry because the
		// installation was inactive. It is active again now, so lift that.
		if ( null !== $this->telemetry ) {
			$this->telemetry->resume();
			$this->telemetry->schedule();
		}

		return $response;
	}

	/**
	 * Belt-and-braces for sites where WP-Cron never runs. Rate-limited so
	 * a broken API cannot mean an API call on every admin page load.
	 */
	public function maybe_retry_on_admin_init() {
		if ( ! $this->is_pending() ) {
			return;
		}

		$last = (int) $this->get_option( 'last_attempt', 0 );

		if ( $last > 0 && ( time() - $last ) < HOUR_IN_SECONDS_FALLBACK ) {
			return;
		}

		$this->ensure_registered();
	}

	// -----------------------------------------------------------------
	// Deactivation and uninstall
	// -----------------------------------------------------------------

	/**
	 * POST /sdk/v1/installations/status {status: deactivated}, signed
	 * with this installation's own secret (scoped tier).
	 *
	 * @return Response|null
	 */
	public function on_deactivate() {
		// Any scheduled registration attempt is moot now.
		$this->unschedule();
		$this->clear_pending();

		if ( null !== $this->telemetry ) {
			// Stop the flush timer, but KEEP the queued events: a
			// deactivate/reactivate cycle is common and the backlog is
			// still legitimate telemetry for this installation.
			$this->telemetry->unschedule();
		}

		return $this->report_status( 'deactivated' );
	}

	/**
	 * POST /sdk/v1/installations/status {status: removed}, then discard
	 * the local credentials.
	 *
	 * Credentials are cleared AFTER the call, not before: the request has
	 * to be signed with them. And they are cleared even if the call
	 * failed — the plugin's data is being deleted either way, and leaving
	 * a secret in wp_options for an installation that no longer exists
	 * locally is just litter. The server has DetectLostInstallations
	 * (journal 8.4) for installations that go silent without saying so.
	 *
	 * @return Response|null
	 */
	public function on_uninstall() {
		$this->unschedule();

		$response = $this->report_status( 'removed' );

		// journal 9.2b: that call, signed with the secret about to be
		// discarded below, is exactly the proof of possession a reclaim
		// needs. Its response carries a one-time token FOR THIS REASON —
		// stored here, deliberately NOT among the options cleared just
		// below, so it survives to the next activation's registration
		// attempt on this same site. Consumed (or expired) server-side
		// either way; kept locally only long enough to be offered once.
		if ( null !== $response && $response->ok() ) {
			$token = $response->get( 'reclaim_token' );

			if ( is_string( $token ) && '' !== $token ) {
				$this->update_option( 'reclaim_token', $token );
			}
		}

		if ( null !== $this->telemetry ) {
			// Uninstall means the plugin's data goes. Unsent events are
			// part of that data, and holding them in the site's database
			// after the plugin is gone would be litter we left behind.
			$this->telemetry->unschedule();
			$this->telemetry->queue()->purge();
		}

		$this->client->credentials()->forget();
		$this->delete_option( 'pending' );
		$this->delete_option( 'attempts' );
		$this->delete_option( 'last_attempt' );
		$this->delete_option( 'installation_id' );

		return $response;
	}

	/**
	 * @param string $status active|deactivated|removed
	 * @return Response|null Null when there is nothing to report.
	 */
	/**
	 * config_version rides both installations/register and
	 * installations/status (13-realtime-config-delivery.md §4) — every
	 * response this class already receives. A response predating the
	 * field, or a transport failure with no body at all, simply hands
	 * note_version() nothing usable, which it already treats as
	 * "no signal" rather than an error.
	 */
	private function note_config_version( Response $response ) {
		if ( null !== $this->realtime_config ) {
			$this->realtime_config->note_version( $response->get( 'config_version' ) );
		}
	}

	private function report_status( $status ) {
		// Never registered, or registration never completed: there is no
		// installation on the server to update and nothing to sign with.
		// Not an error — silence is the correct behaviour.
		if ( ! $this->client->credentials()->has_credentials() ) {
			return null;
		}

		$response = $this->client->post( '/sdk/v1/installations/status', array( 'status' => $status ) );
		$this->note_config_version( $response );

		return $response;
	}

	// -----------------------------------------------------------------
	// Scheduling
	// -----------------------------------------------------------------

	private function schedule_attempt( $attempt_number ) {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}

		$index = min( $attempt_number, count( self::BACKOFF ) - 1 );
		$delay = 0 === $attempt_number ? 0 : self::BACKOFF[ $index ];

		// A single event per hook+args is deduplicated by WordPress, so
		// double-scheduling is harmless — but clearing first keeps the
		// next attempt at the delay we just calculated rather than
		// whatever was already queued.
		$this->unschedule();

		wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
	}

	private function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	// -----------------------------------------------------------------
	// The in-flight installation id
	// -----------------------------------------------------------------

	/**
	 * The id this site is enrolling under. Generated once and REMEMBERED
	 * across retries, deliberately: minting a fresh id on every attempt
	 * would leave a trail of half-registered installations on the server
	 * whenever the response was lost after the server had already
	 * committed the row.
	 *
	 * Cleared once registration succeeds — from then on the id lives in
	 * the credential store.
	 *
	 * @return string
	 */
	private function installation_id() {
		$stored = $this->client->credentials()->get_installation_id();

		if ( is_string( $stored ) && '' !== $stored ) {
			return $stored;
		}

		$pending = $this->get_option( 'installation_id', '' );

		if ( is_string( $pending ) && '' !== $pending ) {
			return $pending;
		}

		$generated = Environment::generate_installation_id();
		$this->update_option( 'installation_id', $generated );

		return $generated;
	}

	private function forget_installation_id() {
		$this->delete_option( 'installation_id' );
	}

	// -----------------------------------------------------------------
	// State
	// -----------------------------------------------------------------

	private function mark_pending() {
		$this->update_option( 'pending', 1 );
		$this->update_option( 'attempts', 0 );
	}

	public function is_pending() {
		return (bool) $this->get_option( 'pending', 0 );
	}

	/**
	 * Also unschedules: leaving a queued retry behind after the work is
	 * done means a cron entry that wakes up, finds nothing to do and goes
	 * back to sleep — harmless in effect, but it is litter in the site
	 * owner's cron table that we put there and never cleaned up.
	 */
	private function clear_pending() {
		$this->unschedule();
		$this->delete_option( 'force' );
		$this->delete_option( 'pending' );
		$this->delete_option( 'attempts' );
		$this->delete_option( 'last_attempt' );
	}

	private function give_up() {
		$this->unschedule();
		$this->delete_option( 'force' );
		$this->delete_option( 'pending' );
		$this->update_option( 'attempts', self::MAX_ATTEMPTS );
		// journal 9.2b: reached on a 403 or 409 — if this attempt carried
		// a reclaim token, the server has already rejected it (wrong,
		// expired, or nothing to reclaim). Nothing left to do with it.
		$this->delete_option( 'reclaim_token' );
	}

	public function attempts() {
		return (int) $this->get_option( 'attempts', 0 );
	}

	private function record_attempt( $number ) {
		$this->update_option( 'attempts', $number );
		$this->update_option( 'last_attempt', time() );
	}

	private function option_name( $suffix ) {
		return 'appneck_sdk_' . $suffix . '_' . $this->key;
	}

	private function get_option( $suffix, $default = null ) {
		return function_exists( 'get_option' ) ? get_option( $this->option_name( $suffix ), $default ) : $default;
	}

	private function update_option( $suffix, $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $this->option_name( $suffix ), $value, false );
		}
	}

	private function delete_option( $suffix ) {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $this->option_name( $suffix ) );
		}
	}
}

if ( ! defined( 'Appneck\\Sdk\\HOUR_IN_SECONDS_FALLBACK' ) ) {
	/**
	 * WordPress defines HOUR_IN_SECONDS, but this SDK is also exercised
	 * outside WordPress, where it does not exist.
	 */
	define( 'Appneck\\Sdk\\HOUR_IN_SECONDS_FALLBACK', 3600 );
}
