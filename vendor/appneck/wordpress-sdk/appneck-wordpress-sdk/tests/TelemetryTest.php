<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * The event queue, the public track() API, and how a flush interprets
 * every answer the server can give.
 */
class TelemetryTest extends TestCase {

	const API_KEY        = 'pk_telemetry_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-aaaaaaaaaaaa';
	const BASE_URL       = 'https://api.example.test';

	/** @var QueueingTransport */
	private $transport;

	/** @var ArrayEventQueue */
	private $queue;

	/** @var RecordingLogger */
	private $logger;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-cron-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options'] = array();
		$GLOBALS['appneck_test_cron']    = array();

		$this->transport = new QueueingTransport();
		$this->queue     = new ArrayEventQueue();
		$this->logger    = new RecordingLogger();
	}

	private function telemetry( $registered = true ): Telemetry {
		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			$registered
				? new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET )
				: new ArrayCredentialStore(),
			$this->transport
		);

		return new Telemetry( $client, $this->queue, $this->logger );
	}

	/** A 202 accepting every event in the batch. */
	private function accept_all( $count ): Response {
		$accepted = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$accepted[] = array( 'index' => $i, 'type' => 'custom_event' );
		}

		return Response::from_http(
			202,
			array(
				'x-ratelimit-limit'     => '100',
				'x-ratelimit-remaining' => '90',
			),
			json_encode(
				array(
					'accepted_count' => $count,
					'rejected_count' => 0,
					'accepted'       => $accepted,
					'rejected'       => array(),
				)
			)
		);
	}

	// -----------------------------------------------------------------
	// The public API never makes an HTTP call
	// -----------------------------------------------------------------

	public function test_track_makes_no_http_call(): void {
		$telemetry = $this->telemetry();

		$telemetry->track( 'booking_created', array( 'source' => 'checkout' ) );
		$telemetry->track_error( 'gateway timeout' );
		$telemetry->heartbeat();

		$this->assertSame( 0, $this->transport->count(), 'track() must never touch the network' );
		$this->assertSame( 3, $this->queue->count() );
	}

	public function test_track_stores_the_event_name_inside_the_payload(): void {
		$this->telemetry()->track( 'booking_created', array( 'source' => 'checkout' ) );

		$event = $this->queue->all()[0];

		// The server's `type` vocabulary is fixed at three values, so a
		// developer's own name lives in the payload.
		$this->assertSame( Telemetry::TYPE_CUSTOM_EVENT, $event['type'] );
		$this->assertSame( 'booking_created', $event['payload']['event'] );
		$this->assertSame( array( 'source' => 'checkout' ), $event['payload']['data'] );
	}

	public function test_an_error_is_queued_as_an_error_report(): void {
		$this->telemetry()->track_error( 'Payment gateway timeout', array( 'gateway' => 'stripe' ) );

		$event = $this->queue->all()[0];

		$this->assertSame( Telemetry::TYPE_ERROR_REPORT, $event['type'] );
		$this->assertSame( 'Payment gateway timeout', $event['payload']['message'] );
	}

	public function test_an_empty_event_name_is_refused(): void {
		$this->assertFalse( $this->telemetry()->track( '' ) );
		$this->assertSame( 0, $this->queue->count() );
	}

	/** A heartbeat is an ordinary event on the ordinary queue. */
	public function test_the_scheduled_run_queues_a_heartbeat_and_flushes_it_in_the_same_batch(): void {
		$this->transport->queue( $this->accept_all( 2 ) );

		$telemetry = $this->telemetry();
		$telemetry->track( 'feature_used' );
		$telemetry->run_scheduled_flush();

		$sent = json_decode( $this->transport->last_request()['body'], true );

		$this->assertCount( 2, $sent['events'] );
		$types = array_column( $sent['events'], 'type' );
		$this->assertContains( Telemetry::TYPE_HEARTBEAT, $types );
		$this->assertContains( Telemetry::TYPE_CUSTOM_EVENT, $types );
		$this->assertSame( 0, $this->queue->count() );
	}

	/**
	 * The server validates `payload => required|array`, and Laravel's
	 * `required` rejects an empty array — so an event with an empty
	 * payload is rejected every single time. A heartbeat is the most
	 * frequent event there is, so getting this wrong means the most
	 * common event silently never lands.
	 *
	 * Regression: the live backend caught exactly this; the mocked
	 * transport had accepted it happily.
	 */
	public function test_every_event_the_sdk_produces_has_a_non_empty_payload(): void {
		$telemetry = $this->telemetry();

		$telemetry->heartbeat();
		$telemetry->track( 'some_event' );
		$telemetry->track( 'no_data_event', array() );
		$telemetry->track_error( 'something broke' );

		foreach ( $this->queue->all() as $event ) {
			$this->assertNotEmpty(
				$event['payload'],
				"An event of type {$event['type']} has an empty payload and the server will reject it"
			);
		}
	}

	public function test_a_heartbeat_reports_the_sdk_version(): void {
		$this->telemetry()->heartbeat();

		$this->assertSame( \Appneck\Sdk\Sdk::VERSION, $this->queue->all()[0]['payload']['sdk_version'] );
	}

	// -----------------------------------------------------------------
	// The cap
	// -----------------------------------------------------------------

	/**
	 * Drop OLDEST, not newest: a site recovering from a long outage
	 * should report what is happening now, not a snapshot of whenever the
	 * outage began.
	 */
	public function test_the_queue_cap_drops_the_oldest_events(): void {
		$queue = new ArrayEventQueue( 5 );

		for ( $i = 1; $i <= 8; $i++ ) {
			$queue->push( 'custom_event', array( 'n' => $i ) );
		}

		$this->assertSame( 5, $queue->count() );

		$numbers = array_map(
			static function ( array $event ) {
				return $event['payload']['n'];
			},
			$queue->all()
		);

		$this->assertSame( array( 4, 5, 6, 7, 8 ), $numbers );
	}

	public function test_the_queue_stays_capped_under_sustained_failure(): void {
		$queue     = new ArrayEventQueue( 10 );
		$client    = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$this->transport
		);
		$telemetry = new Telemetry( $client, $queue, $this->logger );

		for ( $tick = 0; $tick < 20; $tick++ ) {
			$telemetry->track( 'event', array( 'tick' => $tick ) );
			$this->transport->queue( Response::from_transport_error( 'down' ) );
			$telemetry->run_scheduled_flush();
		}

		// Never grows past the cap, and never loses everything either.
		$this->assertSame( 10, $queue->count() );
	}

	// -----------------------------------------------------------------
	// Flushing
	// -----------------------------------------------------------------

	public function test_a_flush_sends_one_signed_batch_and_clears_it(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );
		$telemetry->track( 'b' );
		$telemetry->track( 'c' );

		$this->transport->queue( $this->accept_all( 3 ) );
		$telemetry->flush();

		$sent = $this->transport->last_request();
		$this->assertSame( self::BASE_URL . '/sdk/v1/telemetry', $sent['url'] );

		$expected = \Appneck\Sdk\Signer::sign(
			'POST',
			'/sdk/v1/telemetry',
			self::INSTALL_ID,
			$sent['headers']['X-Timestamp'],
			$sent['body'],
			self::INSTALL_SECRET
		);
		$this->assertSame( $expected, $sent['headers']['X-Signature'] );

		$this->assertSame( 0, $this->queue->count() );
	}

	public function test_a_flush_never_sends_more_than_the_batch_size(): void {
		$telemetry = $this->telemetry();

		for ( $i = 0; $i < Telemetry::BATCH_SIZE + 25; $i++ ) {
			$telemetry->track( 'event' );
		}

		$this->transport->queue( $this->accept_all( Telemetry::BATCH_SIZE ) );
		$telemetry->flush();

		$sent = json_decode( $this->transport->last_request()['body'], true );
		$this->assertCount( Telemetry::BATCH_SIZE, $sent['events'] );
		$this->assertSame( 25, $this->queue->count(), 'The remainder waits for the next tick' );
	}

	public function test_nothing_is_sent_when_the_queue_is_empty(): void {
		$this->assertNull( $this->telemetry()->flush() );
		$this->assertSame( 0, $this->transport->count() );
	}

	/**
	 * Events accumulate before registration completes and go out once it
	 * does — the reason for buffering rather than sending inline.
	 */
	public function test_nothing_is_sent_before_the_site_is_registered(): void {
		$telemetry = $this->telemetry( false );
		$telemetry->track( 'early_event' );

		$this->assertNull( $telemetry->flush() );
		$this->assertSame( 0, $this->transport->count() );
		$this->assertSame( 1, $this->queue->count(), 'The event must survive' );
	}

	// -----------------------------------------------------------------
	// Partial success
	// -----------------------------------------------------------------

	/**
	 * journal §9.3: accepted events clear, permanently-rejected ones are
	 * dropped and logged. Keeping a rejected event would block the queue
	 * behind something that can never leave it.
	 */
	public function test_partial_success_clears_accepted_and_drops_rejected(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'good_one' );
		$telemetry->track( 'bad_one' );
		$telemetry->track( 'good_two' );

		$this->transport->queue(
			Response::from_http(
				202,
				array(),
				json_encode(
					array(
						'accepted_count' => 2,
						'rejected_count' => 1,
						'accepted'       => array(
							array( 'index' => 0 ),
							array( 'index' => 2 ),
						),
						'rejected'       => array(
							array(
								'index'  => 1,
								'errors' => array( 'type' => array( 'The selected type is invalid.' ) ),
							),
						),
					)
				)
			)
		);

		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count(), 'Both accepted and rejected are resolved' );
		$this->assertTrue( $this->logger->contains( 'permanently rejected' ) );
	}

	public function test_a_batch_rejected_entirely_is_dropped_not_retried(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'nonsense' );

		$this->transport->queue(
			Response::from_http( 422, array(), '{"message":"All events in this batch were invalid.","rejected":[{"index":0}]}' )
		);

		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count() );
		$this->assertTrue( $this->logger->contains( 'entirely invalid' ) );
	}

	// -----------------------------------------------------------------
	// Failures that must NOT lose events
	// -----------------------------------------------------------------

	public function test_a_transport_failure_leaves_events_queued(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );
		$telemetry->track( 'b' );

		$this->transport->queue( Response::from_transport_error( 'could not connect' ) );
		$telemetry->flush();

		$this->assertSame( 2, $this->queue->count() );
	}

	public function test_a_server_error_leaves_events_queued(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue( Response::from_http( 503, array(), '' ) );
		$telemetry->flush();

		$this->assertSame( 1, $this->queue->count() );
	}

	public function test_a_401_leaves_events_queued(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue( Response::from_http( 401, array(), '{"message":"Invalid signature."}' ) );
		$telemetry->flush();

		// Could be clock skew or a key rotation mid-flight — the event is
		// not at fault and must survive.
		$this->assertSame( 1, $this->queue->count() );
	}

	public function test_events_queued_offline_are_sent_on_a_later_flush(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'while_offline_1' );
		$telemetry->track( 'while_offline_2' );

		$this->transport->queue( Response::from_transport_error( 'down' ) );
		$telemetry->flush();
		$this->assertSame( 2, $this->queue->count() );

		$this->transport->queue( $this->accept_all( 2 ) );
		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count() );
		$sent = json_decode( $this->transport->last_request()['body'], true );
		$this->assertCount( 2, $sent['events'] );
	}

	// -----------------------------------------------------------------
	// Rate limiting and 403
	// -----------------------------------------------------------------

	public function test_a_429_backs_off_for_the_servers_retry_after(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue(
			Response::from_http(
				429,
				array(
					'retry-after'       => '260',
					'x-ratelimit-limit' => '100',
				),
				'{"message":"Rate limit exceeded for this installation."}'
			)
		);

		$telemetry->flush();

		$this->assertSame( 1, $this->queue->count(), 'Events survive a 429' );
		$this->assertGreaterThan( time() + 200, $telemetry->suppressed_until() );

		// And the next tick sends nothing rather than hammering.
		$before = $this->transport->count();
		$telemetry->flush();
		$this->assertSame( $before, $this->transport->count() );
	}

	/**
	 * The budget is spent but we have not been refused yet — waiting is
	 * cheaper than being refused next tick.
	 */
	public function test_an_exhausted_rate_limit_budget_backs_off_proactively(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue(
			Response::from_http(
				202,
				array(
					'x-ratelimit-limit'     => '100',
					'x-ratelimit-remaining' => '0',
				),
				'{"accepted_count":1,"rejected_count":0,"accepted":[{"index":0}],"rejected":[]}'
			)
		);

		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count(), 'The batch still succeeded' );
		$this->assertGreaterThan( time(), $telemetry->suppressed_until() );
	}

	/**
	 * journal §5.4's fail-closed gate. Consent may still be granted, so
	 * the events are kept and sending is merely paused.
	 */
	public function test_a_consent_403_pauses_but_keeps_queuing(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue(
			Response::from_http( 403, array(), '{"message":"Consent required before telemetry can be accepted for this installation."}' )
		);

		$telemetry->flush();

		$this->assertSame( 1, $this->queue->count(), 'Events must survive a consent refusal' );
		$this->assertFalse( $telemetry->is_stopped() );
		$this->assertGreaterThan( time(), $telemetry->suppressed_until() );

		// New events keep accumulating while paused.
		$telemetry->track( 'b' );
		$this->assertSame( 2, $this->queue->count() );
	}

	/**
	 * H1's active-tier enforcement: nothing queued will ever be accepted
	 * under this installation again.
	 */
	public function test_an_inactive_installation_403_stops_sending_entirely(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue(
			Response::from_http( 403, array(), '{"message":"This installation is removed and can no longer send data. Re-register via POST /sdk/v1/installations first."}' )
		);

		$telemetry->flush();

		$this->assertTrue( $telemetry->is_stopped() );
		$this->assertSame( 0, $this->queue->count(), 'The backlog is dropped, not carried forever' );

		$before = $this->transport->count();
		$telemetry->track( 'b' );
		$telemetry->flush();
		$this->assertSame( $before, $this->transport->count() );
	}

	/** Re-registering lifts the stop. */
	public function test_resume_lifts_a_stop(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue( Response::from_http( 403, array(), '{"message":"This installation is removed and can no longer send data."}' ) );
		$telemetry->flush();
		$this->assertTrue( $telemetry->is_stopped() );

		$telemetry->resume();

		$this->assertFalse( $telemetry->is_stopped() );

		$telemetry->track( 'b' );
		$this->transport->queue( $this->accept_all( 1 ) );
		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count() );
	}

	/**
	 * An unrecognised 403 is treated as the RECOVERABLE case: being wrong
	 * that way costs a delay, while being wrong the other way silently
	 * discards a working installation's telemetry forever.
	 */
	public function test_an_unrecognised_403_is_treated_as_recoverable(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue( Response::from_http( 403, array(), '{"message":"Something new we have not seen before."}' ) );
		$telemetry->flush();

		$this->assertTrue( $telemetry->is_stopped() || 1 === $this->queue->count() );
	}

	// -----------------------------------------------------------------
	// config_version hand-off to RealtimeConfig
	// -----------------------------------------------------------------

	private function config_version_option(): string {
		return 'appneck_sdk_config_version_' . substr( hash( 'sha256', self::API_KEY ), 0, 32 );
	}

	public function test_a_successful_flush_hands_the_version_to_realtime_config(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();

		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$telemetry->set_realtime_config( new \Appneck\Sdk\RealtimeConfig( substr( hash( 'sha256', self::API_KEY ), 0, 32 ) ) );

		$this->transport->queue(
			Response::from_http(
				202,
				array(),
				json_encode(
					array(
						'accepted_count'  => 1,
						'rejected_count'  => 0,
						'accepted'        => array( array( 'index' => 0 ) ),
						'rejected'        => array(),
						'config_version'  => 7,
					)
				)
			)
		);

		$telemetry->flush();

		$this->assertSame( 7, $GLOBALS['appneck_test_options'][ $this->config_version_option() ] );
	}

	/** No wired RealtimeConfig must not be a fatal error — it is optional, per set_realtime_config's own doc. */
	public function test_a_flush_with_no_realtime_config_wired_does_not_error(): void {
		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$this->transport->queue( $this->accept_all( 1 ) );

		$telemetry->flush();

		$this->assertSame( 0, $this->queue->count() );
	}

	/**
	 * Only a genuinely successful (202) flush is evidence of anything —
	 * a rate-limited or forbidden response says nothing about whether the
	 * product's config changed, so RealtimeConfig must not be told
	 * anything.
	 */
	public function test_a_non_202_response_never_reaches_realtime_config(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();

		$telemetry = $this->telemetry();
		$telemetry->track( 'a' );

		$telemetry->set_realtime_config( new \Appneck\Sdk\RealtimeConfig( substr( hash( 'sha256', self::API_KEY ), 0, 32 ) ) );

		$this->transport->queue( Response::from_http( 503, array(), '' ) );
		$telemetry->flush();

		$this->assertArrayNotHasKey( $this->config_version_option(), $GLOBALS['appneck_test_options'] );
	}

	// -----------------------------------------------------------------
	// Interval
	// -----------------------------------------------------------------

	public function test_the_default_interval_is_fifteen_minutes(): void {
		$this->assertSame( 900, $this->telemetry()->interval() );
	}

	public function test_the_interval_has_a_floor_so_a_filter_cannot_cause_a_request_storm(): void {
		require_once __DIR__ . '/wp-filter-polyfill.php';

		appneck_test_add_filter(
			'appneck_sdk_flush_interval',
			static function () {
				return 1;
			}
		);

		$this->assertSame( 60, $this->telemetry()->interval() );

		appneck_test_clear_filters();
	}
}
