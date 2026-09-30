<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;
use Appneck\Sdk\Queue\EventQueue;

/**
 * The developer-facing telemetry API, and the batching that ships it.
 *
 *     $sdk->track( 'booking_created', array( 'source' => 'checkout' ) );
 *     $sdk->track_error( 'Payment gateway timeout', array( 'gateway' => 'stripe' ) );
 *
 * ## track() never makes an HTTP call
 *
 * It writes one row locally and returns. A plugin author calling this
 * during a page load must not be adding network latency to a site we are
 * a guest on — an API that is slow to call is an API that gets called
 * from a background job by every careful developer and from a page load
 * by everyone else. Sending happens on a scheduled flush.
 *
 * ## Heartbeats ride the same queue
 *
 * A heartbeat is just an event of type `heartbeat` (journal §9.3's own
 * type list). It is pushed onto the same queue and sent in the same
 * batch as everything else, rather than getting a private code path —
 * so the retry, partial-success and back-off behaviour that matters most
 * for real events is exercised constantly by the most common event
 * there is, instead of being the rarely-tested branch.
 *
 * ## Consent
 *
 * When a Consent is wired in, an explicit refusal makes track(),
 * track_error() and heartbeat() no-ops and drops anything already queued
 * — see is_refused() and Consent's class doc. `pending` changes nothing
 * here: the server's fail-closed 403 remains the enforcement, and the
 * back-off path below handles it while keeping the backlog.
 *
 * ## Interval
 *
 * 15 minutes by default, filterable via `appneck_sdk_flush_interval`.
 * Journal §9.1 sizes the SDK rate limits around "one heartbeat every
 * five minutes per installation", so 15 sits comfortably inside what the
 * server expects; §8.4 notes WP-Cron only fires on page load, so any
 * interval is a ceiling rather than a promise, and it sets the
 * lost-installation threshold at 24 hours — 15 minutes leaves roughly a
 * 96x margin, so a quiet site can miss a great many ticks before it
 * looks lost.
 */
final class Telemetry {

	/** Matches the server's telemetry_batch_max_events. */
	const BATCH_SIZE = 100;

	const CRON_HOOK = 'appneck_sdk_flush';

	const DEFAULT_INTERVAL = 900;

	/** Back-off after a consent refusal before trying again. */
	const CONSENT_RETRY_SECONDS = 3600;

	/** The server's three accepted event types (journal §9.3). */
	const TYPE_HEARTBEAT    = 'heartbeat';
	const TYPE_CUSTOM_EVENT = 'custom_event';
	const TYPE_ERROR_REPORT = 'error_report';

	/** @var Client */
	private $client;

	/** @var EventQueue */
	private $queue;

	/** @var Logger */
	private $logger;

	/** @var string */
	private $key;

	/** @var Consent|null */
	private $consent = null;

	/** @var Environment */
	private $environment;

	/** @var RealtimeConfig|null */
	private $realtime_config = null;

	public function __construct( Client $client, EventQueue $queue, ?Logger $logger = null, ?Consent $consent = null, ?Environment $environment = null ) {
		$this->client  = $client;
		$this->queue   = $queue;
		$this->logger  = null !== $logger ? $logger : new NullLogger();
		$this->consent = $consent;
		// Defaulted rather than required: the inventory it collects needs
		// no plugin_file, so every existing caller keeps working unchanged.
		$this->environment = null !== $environment ? $environment : new Environment();
		$this->key     = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
	}

	/** Also settable after construction — see Consent::set_telemetry. */
	public function set_consent( ?Consent $consent ) {
		$this->consent = $consent;
	}

	/**
	 * Wired the same way as Consent (settable after construction, so both
	 * stay independently constructible): every successful flush hands its
	 * response's config_version to RealtimeConfig, which decides whether
	 * anything actually changed (13-realtime-config-delivery.md §4).
	 * Supersedes the older per-feature `survey_questions_updated_at`
	 * marker this method used to carry (§6.2) — one signal, read the same
	 * way from every response that carries it.
	 */
	public function set_realtime_config( ?RealtimeConfig $realtime_config ) {
		$this->realtime_config = $realtime_config;
	}

	public function queue() {
		return $this->queue;
	}

	/**
	 * Whether the site owner has explicitly refused. The one consent state
	 * this class acts on locally.
	 *
	 * `pending` deliberately does NOT stop anything here: it means the
	 * question is unanswered, the server is the authority (journal §5.4's
	 * fail-closed 403), and the existing back-off path already handles
	 * that refusal correctly while keeping the backlog — which is exactly
	 * what should be sent the moment consent is granted. Blocking on
	 * pending would also make a client that has never stored a decision
	 * silently stop reporting for an installation the server considers
	 * accepted.
	 *
	 * `rejected` is a decision, and it is honoured without asking the
	 * server: nothing is collected, and anything collected while the
	 * question was open is dropped. See Consent's class doc.
	 */
	private function is_refused() {
		if ( null === $this->consent || ! $this->consent->is_rejected() ) {
			return false;
		}

		// Lazily enforced as well as applied at the moment of the click:
		// the decision may have been made in another request entirely, and
		// a queue left non-empty by an older SDK version, a restored
		// backup or a crash mid-decision must not survive a refusal.
		if ( $this->queue->count() > 0 ) {
			$this->queue->purge();
		}

		return true;
	}

	// -----------------------------------------------------------------
	// Public developer API
	// -----------------------------------------------------------------

	/**
	 * Record something that happened. Local only — no HTTP.
	 *
	 * @param string               $event_name 'booking_created', 'feature_used'…
	 * @param array<string, mixed> $data       Anything JSON-serialisable.
	 * @return bool False only if the event could not be stored locally.
	 */
	public function track( $event_name, array $data = array() ) {
		$event_name = (string) $event_name;

		if ( '' === $event_name ) {
			return false;
		}

		if ( $this->is_refused() ) {
			return false;
		}

		// The server's `type` vocabulary is fixed at three values, so a
		// developer's own event name lives INSIDE the payload rather than
		// becoming a new type. `event` and `data` are separated so the
		// dashboard can group by name without having to guess which
		// payload key is the name.
		return $this->queue->push(
			self::TYPE_CUSTOM_EVENT,
			array(
				'event' => $event_name,
				'data'  => $data,
			)
		);
	}

	/**
	 * Record an error the plugin handled. Same queue, different type, so
	 * the dashboard can separate errors from ordinary activity.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $context
	 */
	public function track_error( $message, array $context = array() ) {
		$message = (string) $message;

		if ( '' === $message ) {
			return false;
		}

		if ( $this->is_refused() ) {
			return false;
		}

		return $this->queue->push(
			self::TYPE_ERROR_REPORT,
			array(
				'message' => $message,
				'context' => $context,
			)
		);
	}

	/**
	 * Queues a heartbeat. Sent by the next flush, like anything else.
	 *
	 * The payload is deliberately NOT empty. The server validates every
	 * event with `payload => required|array`, and Laravel's `required`
	 * rejects an empty array — so a heartbeat sent as `{}` is rejected,
	 * every time, for the most frequent event type there is. Found by the
	 * live integration check against the real backend; a mocked server
	 * had happily accepted it.
	 *
	 * `sdk_version` is also genuinely worth having: it is how anyone can
	 * later tell which SDK versions are actually deployed in the field,
	 * which matters the first time a fix has to be rolled out.
	 *
	 * @param array<string, mixed> $extra Merged in by the caller.
	 */
	public function heartbeat( array $extra = array() ) {
		// Suppressed under a refusal too. Journal §5.4 already accepted the
		// consequence: a rejected installation stops sending heartbeats and
		// will eventually surface in the lost-installation detector, and the
		// stored consent_status is what distinguishes it from an abandoned
		// site. "I am alive" is still a report about a system that said no.
		if ( $this->is_refused() ) {
			return false;
		}

		$payload = array_merge( array( 'sdk_version' => Sdk::VERSION ), $extra );

		// Rides INSIDE the heartbeat's payload rather than as a sibling of
		// `events` in the request body, because there is no such thing as
		// a heartbeat request here — a heartbeat is one queued event among
		// others (see this class's own doc), and the server reads
		// payload.environment off events of type `heartbeat`.
		$environment = $this->environment_payload();

		if ( null !== $environment ) {
			$payload['environment'] = $environment;
		}

		return $this->queue->push( self::TYPE_HEARTBEAT, $payload );
	}

	/**
	 * The inventory, but only when it has changed since the last time one
	 * was queued — otherwise null, and the heartbeat carries nothing.
	 *
	 * Sending the full list on every heartbeat would be a few KB every 15
	 * minutes forever to tell the server something it already knows, so
	 * the SDK hashes the snapshot and stays silent while the hash holds.
	 * The server keeps the same hash on its side and re-checks it anyway
	 * (a restored backup can resend a stale hash), so this is a bandwidth
	 * optimisation, not the correctness mechanism.
	 *
	 * The hash is stored at QUEUE time, not send time, and that is safe
	 * here specifically because push() persists the event locally: once
	 * queued, the payload survives failed flushes, retries and back-off
	 * until the server acknowledges it. It would NOT be safe in an SDK
	 * that sent inline.
	 *
	 * Never throws. An inventory that cannot be read is not a reason for a
	 * site to stop reporting that it is alive.
	 *
	 * @return array<string, mixed>|null
	 */
	private function environment_payload() {
		try {
			$snapshot = $this->environment->plugin_inventory();

			if ( ! is_array( $snapshot ) ) {
				return null;
			}

			$encoded = json_encode( $snapshot, JSON_UNESCAPED_UNICODE );

			if ( ! is_string( $encoded ) ) {
				return null;
			}

			$hash = hash( 'sha256', $encoded );

			if ( $hash === (string) $this->get_option( 'env_hash', '' ) ) {
				return null;
			}

			// autoload=false via update_option()'s third argument (see
			// update_option() below) — this value is read on a cron tick,
			// not on every page load, and has no business in the options
			// autoload cache.
			$this->update_option( 'env_hash', $hash );

			return array_merge( $snapshot, array( 'hash' => $hash ) );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Could not collect the site environment; heartbeat sent without it.',
				array( 'error' => $e->getMessage() )
			);

			return null;
		}
	}

	// -----------------------------------------------------------------
	// Scheduling
	// -----------------------------------------------------------------

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_flush' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules
	 * @return array<string, array<string, mixed>>
	 */
	public function add_cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			return $schedules;
		}

		$schedules['appneck_sdk_interval'] = array(
			'interval' => $this->interval(),
			'display'  => 'Appneck SDK telemetry interval',
		);

		return $schedules;
	}

	public function schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + $this->interval(), 'appneck_sdk_interval', self::CRON_HOOK );
		}
	}

	public function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	public function interval() {
		$interval = self::DEFAULT_INTERVAL;

		if ( function_exists( 'apply_filters' ) ) {
			$interval = apply_filters( 'appneck_sdk_flush_interval', $interval, $this->client->config()->api_key() );
		}

		$interval = (int) $interval;

		// A filter returning something silly must not turn into a request
		// storm on the site owner's server. 60s floor matches the
		// tightest WP-Cron can realistically fire anyway.
		return max( 60, $interval );
	}

	/** The cron callback: one heartbeat, then ship whatever is queued. */
	public function run_scheduled_flush() {
		$this->heartbeat();

		return $this->flush();
	}

	// -----------------------------------------------------------------
	// Flushing
	// -----------------------------------------------------------------

	/**
	 * Sends up to one batch. Returns the Response, or null when there was
	 * nothing to do (empty queue, no credentials, or backing off).
	 *
	 * @return Response|null
	 */
	public function flush() {
		if ( $this->is_stopped() ) {
			return null;
		}

		// Nothing to send, and nothing that may be kept — is_refused()
		// purges. Checked here as well as in track() because a flush can be
		// reached by a cron event scheduled before the refusal.
		if ( $this->is_refused() ) {
			return null;
		}

		if ( time() < (int) $this->get_option( 'suppressed_until', 0 ) ) {
			return null;
		}

		if ( ! $this->client->credentials()->has_credentials() ) {
			// Not registered yet. Events keep accumulating locally and go
			// out once Lifecycle completes registration — which is the
			// whole point of buffering rather than sending inline.
			return null;
		}

		$events = $this->queue->take( self::BATCH_SIZE );

		if ( empty( $events ) ) {
			return null;
		}

		$response = $this->client->post( '/sdk/v1/telemetry', array( 'events' => $this->to_payload( $events ) ) );

		$this->handle_response( $response, $events );

		return $response;
	}

	/**
	 * @param array<int, array<string, mixed>> $events
	 * @return array<int, array<string, mixed>>
	 */
	private function to_payload( array $events ) {
		$payload = array();

		foreach ( $events as $event ) {
			$payload[] = array(
				'type'        => $event['type'],
				'payload'     => $event['payload'],
				'occurred_at' => $event['occurred_at'],
			);
		}

		return $payload;
	}

	/**
	 * @param array<int, array<string, mixed>> $events The batch as sent,
	 *                                                 index-aligned with
	 *                                                 the server's reply.
	 */
	private function handle_response( Response $response, array $events ) {
		$this->remember_rate_limit( $response );

		if ( $response->is_rate_limited() ) {
			// Respect the server's own Retry-After rather than guessing.
			$retry_after = $response->rate_limit()->retry_after();
			$this->suppress_for( null !== $retry_after ? $retry_after : 300 );

			return;
		}

		if ( $response->is_forbidden() ) {
			$this->handle_forbidden( $response );

			return;
		}

		if ( 202 === $response->status() ) {
			$this->clear_resolved( $response, $events );

			if ( null !== $this->realtime_config ) {
				$this->realtime_config->note_version( $response->get( 'config_version' ) );
			}

			return;
		}

		if ( 422 === $response->status() ) {
			// Every event in the batch was invalid. They can never become
			// valid, so retrying is a loop that never ends — drop them and
			// say so.
			$this->logger->error(
				'Telemetry batch rejected as entirely invalid; dropping ' . count( $events ) . ' event(s).',
				array( 'rejected' => $response->get( 'rejected' ) )
			);

			$this->queue->forget( $this->ids_of( $events ) );

			return;
		}

		// 401, 5xx, transport failure. Left queued deliberately: a 401 can
		// be clock skew or a key rotation mid-flight, and a 5xx is the
		// server's problem, not the event's. The next tick tries again.
		$this->logger->error(
			'Telemetry flush failed; ' . count( $events ) . ' event(s) remain queued.',
			array(
				'status' => $response->status(),
				'error'  => $response->error_message(),
			)
		);
	}

	/**
	 * 403 has two distinct causes with opposite correct responses.
	 *
	 * The server does not send a machine-readable code, so this reads the
	 * message — narrowly, and biased towards the safe interpretation: an
	 * unrecognised 403 is treated as the recoverable consent case (back
	 * off, keep the events) rather than the terminal one, because being
	 * wrong that way costs a delay, while being wrong the other way
	 * silently discards a working installation's telemetry forever.
	 * A machine-readable error code from the backend would remove the
	 * guesswork; noted as a follow-up rather than changed here.
	 */
	private function handle_forbidden( Response $response ) {
		$message = (string) $response->error_message();

		// journal §5.4's fail-closed gate: consent is pending or refused.
		// Keep queuing locally and keep the events — consent may be
		// granted, and then the backlog is exactly what should be sent.
		if ( false !== stripos( $message, 'consent' ) ) {
			$this->suppress_for( self::CONSENT_RETRY_SECONDS );
			$this->logger->error( 'Telemetry paused: consent has not been granted for this installation.' );

			return;
		}

		// H1's active-tier enforcement: the installation is deactivated or
		// removed. Nothing queued will ever be accepted under this
		// installation, so stop sending entirely and drop the backlog
		// rather than carrying it in the site's database forever. Cleared
		// by a successful re-registration (Lifecycle::on_activate).
		$this->stop();
		$this->queue->purge();
		$this->logger->error( 'Telemetry stopped: this installation is no longer active.', array( 'message' => $message ) );
	}

	/**
	 * Clears everything the server resolved — accepted AND rejected.
	 *
	 * Rejected events are permanent validation failures (a bad type, a
	 * payload that is not an object, an occurred_at outside the
	 * partition window). They cannot become valid by being sent again,
	 * so keeping them would block the queue behind events that can never
	 * leave it. Dropped and logged, per journal §9.3's partial-success
	 * contract.
	 *
	 * @param array<int, array<string, mixed>> $events
	 */
	private function clear_resolved( Response $response, array $events ) {
		$accepted = $response->get( 'accepted', array() );
		$rejected = $response->get( 'rejected', array() );

		$resolved = array();

		foreach ( array( $accepted, $rejected ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $entry ) {
				// The server echoes each event's index in the batch we
				// sent, which is how a reply maps back to local rows.
				if ( ! is_array( $entry ) || ! isset( $entry['index'] ) ) {
					continue;
				}

				$index = (int) $entry['index'];

				if ( isset( $events[ $index ]['id'] ) ) {
					$resolved[] = $events[ $index ]['id'];
				}
			}
		}

		if ( is_array( $rejected ) && ! empty( $rejected ) ) {
			$this->logger->error(
				'Telemetry: ' . count( $rejected ) . ' event(s) permanently rejected and dropped.',
				array( 'rejected' => $rejected )
			);
		}

		// Fallback: a 202 whose body we could not map (an unexpected shape,
		// a proxy rewriting it). The server accepted the batch, so keeping
		// it would double-send on the next tick.
		if ( empty( $resolved ) ) {
			$resolved = $this->ids_of( $events );
		}

		$this->queue->forget( $resolved );
	}

	/**
	 * @param array<int, array<string, mixed>> $events
	 * @return array<int, mixed>
	 */
	private function ids_of( array $events ) {
		$ids = array();

		foreach ( $events as $event ) {
			if ( isset( $event['id'] ) ) {
				$ids[] = $event['id'];
			}
		}

		return $ids;
	}

	// -----------------------------------------------------------------
	// Back-off state
	// -----------------------------------------------------------------

	private function remember_rate_limit( Response $response ) {
		$remaining = $response->rate_limit()->remaining();

		// The budget is spent but the server has not refused us yet.
		// Waiting now is cheaper than being refused next tick, and it is
		// the polite reading of a header that exists to be respected.
		if ( null !== $remaining && 0 === $remaining && ! $response->is_rate_limited() ) {
			$retry_after = $response->rate_limit()->retry_after();
			$this->suppress_for( null !== $retry_after ? $retry_after : 300 );
		}
	}

	private function suppress_for( $seconds ) {
		$this->update_option( 'suppressed_until', time() + max( 1, (int) $seconds ) );
	}

	public function suppressed_until() {
		return (int) $this->get_option( 'suppressed_until', 0 );
	}

	private function stop() {
		$this->update_option( 'stopped', 1 );
	}

	public function is_stopped() {
		return (bool) $this->get_option( 'stopped', 0 );
	}

	/** Called by Lifecycle when registration succeeds again. */
	public function resume() {
		$this->delete_option( 'stopped' );
		$this->delete_option( 'suppressed_until' );
		// The backlog was purged when this installation was stopped, which
		// may have included the environment snapshot. Forgetting the hash
		// makes the next heartbeat resend it rather than staying silent
		// about an inventory the server never received.
		$this->delete_option( 'env_hash' );
	}

	private function option_name( $suffix ) {
		return 'appneck_sdk_telemetry_' . $suffix . '_' . $this->key;
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
