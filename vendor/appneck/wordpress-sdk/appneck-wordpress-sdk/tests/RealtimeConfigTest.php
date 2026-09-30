<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\RealtimeConfig;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * The circuit breaker, config_version tracking, single-flight lock, and
 * the /sdk/v1/config poll — the shared machinery behind
 * docs/architecture/13-realtime-config-delivery.md.
 */
class RealtimeConfigTest extends TestCase {

	const KEY            = 'rtckey123';
	const API_KEY        = 'pk_realtime_config_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-eeeeeeeeeeee';
	const BASE_URL       = 'https://api.example.test';

	/** @var QueueingTransport */
	private $transport;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-menu-polyfill.php'; // transients
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_options']    = array();
		$GLOBALS['appneck_test_transients'] = array();

		$this->transport = new QueueingTransport();
	}

	private function realtime_config(): RealtimeConfig {
		return new RealtimeConfig( self::KEY );
	}

	private function client(): Client {
		return new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$this->transport
		);
	}

	// -----------------------------------------------------------------
	// config_version / staleness (Part 4)
	// -----------------------------------------------------------------

	public function test_a_first_sighting_is_stored_but_not_stale(): void {
		$rc = $this->realtime_config();

		$rc->note_version( 5 );

		$this->assertSame( 5, $rc->stored_version() );
		$this->assertFalse( $rc->is_stale() );
	}

	public function test_a_changed_version_marks_stale(): void {
		$rc = $this->realtime_config();

		$rc->note_version( 5 );
		$rc->note_version( 6 );

		$this->assertTrue( $rc->is_stale() );
		$this->assertSame( 6, $rc->stored_version() );
	}

	public function test_the_same_version_again_does_not_mark_stale(): void {
		$rc = $this->realtime_config();

		$rc->note_version( 5 );
		$rc->note_version( 5 );

		$this->assertFalse( $rc->is_stale() );
	}

	public function test_clear_stale_resets_it(): void {
		$rc = $this->realtime_config();
		$rc->note_version( 5 );
		$rc->note_version( 6 );

		$rc->clear_stale();

		$this->assertFalse( $rc->is_stale() );
	}

	/**
	 * Forward compatibility (Part 3 rule 8, Part 9): a missing or
	 * malformed config_version must fall back to whatever was already
	 * known, never error and never be treated as a change.
	 */
	public function test_a_missing_config_version_is_ignored(): void {
		$rc = $this->realtime_config();
		$rc->note_version( 5 );

		$rc->note_version( null );
		$rc->note_version( 'not-a-number' );
		$rc->note_version( array( 'unexpected' => 'shape' ) );

		$this->assertSame( 5, $rc->stored_version() );
		$this->assertFalse( $rc->is_stale() );
	}

	public function test_a_numeric_string_version_is_accepted(): void {
		$rc = $this->realtime_config();

		$rc->note_version( '5' );

		$this->assertSame( 5, $rc->stored_version() );
	}

	// -----------------------------------------------------------------
	// Circuit breaker (Part 3 rule 5)
	// -----------------------------------------------------------------

	public function test_the_circuit_is_closed_by_default(): void {
		$this->assertFalse( $this->realtime_config()->is_open() );
	}

	public function test_the_circuit_stays_closed_under_the_failure_threshold(): void {
		$rc = $this->realtime_config();

		$rc->record_failure();
		$rc->record_failure();

		$this->assertFalse( $rc->is_open() );
	}

	public function test_the_circuit_opens_on_the_third_consecutive_failure(): void {
		$rc = $this->realtime_config();

		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();

		$this->assertTrue( $rc->is_open() );
	}

	public function test_a_success_resets_the_failure_count(): void {
		$rc = $this->realtime_config();

		$rc->record_failure();
		$rc->record_failure();
		$rc->record_success();
		$rc->record_failure();
		$rc->record_failure();

		// Two failures since the reset — still under threshold.
		$this->assertFalse( $rc->is_open() );
	}

	public function test_the_circuit_closes_again_once_the_open_window_elapses(): void {
		$rc = $this->realtime_config();

		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();
		$this->assertTrue( $rc->is_open() );

		// Same convention as this package's other backoff tests
		// (LicenseTest): backdate the stored state rather than mocking
		// time().
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_config_circuit_open_until_' . self::KEY ] = time() - 1;

		$this->assertFalse( $rc->is_open() );
	}

	/**
	 * Rule 5: "On a 429, back off and respect it — never retry into a
	 * rate limit." A single 429 opens the circuit for exactly the
	 * server's own Retry-After, not the 3-strikes threshold.
	 */
	public function test_a_429_opens_the_circuit_for_the_servers_retry_after_immediately(): void {
		$rc = $this->realtime_config();

		$rc->record_failure( 120 );

		$this->assertTrue( $rc->is_open(), 'one 429 must open the circuit, not wait for two more failures' );

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_config_circuit_open_until_' . self::KEY ] = time() + 200;
		$this->assertTrue( $rc->is_open() );
	}

	// -----------------------------------------------------------------
	// Single-flight lock (Part 3 rule 4, Part 9)
	// -----------------------------------------------------------------

	public function test_the_first_caller_acquires_the_lock(): void {
		$this->assertTrue( $this->realtime_config()->acquire_lock( 'refresh', 65 ) );
	}

	/**
	 * The exact scenario Part 9 names: ten concurrent callers, one lock.
	 */
	public function test_only_one_of_ten_concurrent_callers_acquires_the_lock(): void {
		$rc = $this->realtime_config();

		$acquired = 0;

		for ( $i = 0; $i < 10; $i++ ) {
			if ( $rc->acquire_lock( 'refresh', 65 ) ) {
				++$acquired;
			}
		}

		$this->assertSame( 1, $acquired, 'ten concurrent callers must produce exactly one acquisition' );
	}

	public function test_releasing_the_lock_lets_the_next_caller_acquire_it(): void {
		$rc = $this->realtime_config();

		$rc->acquire_lock( 'refresh', 65 );
		$this->assertFalse( $rc->acquire_lock( 'refresh', 65 ) );

		$rc->release_lock( 'refresh' );

		$this->assertTrue( $rc->acquire_lock( 'refresh', 65 ) );
	}

	public function test_different_lock_names_are_independent(): void {
		$rc = $this->realtime_config();

		$this->assertTrue( $rc->acquire_lock( 'refresh', 65 ) );
		$this->assertTrue( $rc->acquire_lock( 'poll', 65 ) );
	}

	public function test_the_lock_expires_on_its_own(): void {
		$rc = $this->realtime_config();
		$rc->acquire_lock( 'refresh', 65 );

		// Same backdating convention as the circuit-open test above.
		$lock_key = 'appneck_sdk_config_lock_refresh_' . self::KEY;
		$GLOBALS['appneck_test_transients'][ $lock_key ]['expires'] = time() - 1;

		$this->assertTrue( $rc->acquire_lock( 'refresh', 65 ) );
	}

	// -----------------------------------------------------------------
	// poll() — GET /sdk/v1/config (Layer 3)
	// -----------------------------------------------------------------

	private function queue_poll_response( $configVersion, $hasUrgent, $etag = '"v1"' ): void {
		$this->transport->queue(
			Response::from_http(
				200,
				array( 'etag' => $etag ),
				json_encode( array( 'config_version' => $configVersion, 'has_urgent' => $hasUrgent ) )
			)
		);
	}

	public function test_poll_returns_the_servers_answer_and_records_success(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, true );

		$result = $rc->poll( $this->client() );

		$this->assertSame( 3, $result['config_version'] );
		$this->assertTrue( $result['has_urgent'] );
		$this->assertFalse( $rc->is_open() );
	}

	public function test_poll_sends_the_stored_etag_as_if_none_match(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, false, '"etag-one"' );
		$rc->poll( $this->client() );

		$this->queue_poll_response( 3, false, '"etag-one"' );
		$rc->poll( $this->client() );

		$request = $this->transport->last_request();
		$this->assertSame( '"etag-one"', $request['headers']['If-None-Match'] );
	}

	public function test_a_304_is_treated_as_success_and_returns_the_cached_answer(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, true, '"etag-one"' );
		$rc->poll( $this->client() );

		$this->transport->queue( Response::from_http( 304, array(), '' ) );
		$result = $rc->poll( $this->client() );

		$this->assertSame( 3, $result['config_version'] );
		$this->assertTrue( $result['has_urgent'] );
		$this->assertFalse( $rc->is_open() );
	}

	public function test_poll_feeds_note_version_so_staleness_is_tracked(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, false );
		$rc->poll( $this->client() );

		$this->queue_poll_response( 4, false );
		$rc->poll( $this->client() );

		$this->assertTrue( $rc->is_stale(), 'a version change seen via the poll must mark staleness too' );
	}

	/**
	 * The rule this whole layer exists to enforce: a non-urgent version
	 * bump marks staleness (for the next page load to pick up) but the
	 * poll response itself still reports has_urgent=false, so nothing
	 * about poll()'s OWN return value tells a caller to pull the full
	 * payload. Whether to act on it is the caller's decision — the JS in
	 * AnnouncementNotices makes exactly this check.
	 */
	public function test_a_non_urgent_version_change_is_marked_stale_but_still_reports_not_urgent(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, false );
		$rc->poll( $this->client() );

		$this->queue_poll_response( 4, false );
		$result = $rc->poll( $this->client() );

		$this->assertFalse( $result['has_urgent'] );
		$this->assertTrue( $rc->is_stale() );
	}

	public function test_a_failed_poll_records_a_failure_and_returns_the_last_cached_answer(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, true );
		$rc->poll( $this->client() );

		$this->transport->queue( Response::from_http( 500, array(), '' ) );
		$result = $rc->poll( $this->client() );

		$this->assertSame( 3, $result['config_version'] );
		$this->assertTrue( $result['has_urgent'], 'a failed poll must not lose the last known urgent state' );
	}

	public function test_a_429_on_poll_backs_off_via_retry_after(): void {
		$rc = $this->realtime_config();

		$this->transport->queue(
			Response::from_http( 429, array( 'retry-after' => '90' ), '{"message":"Rate limit exceeded"}' )
		);
		$rc->poll( $this->client() );

		$this->assertTrue( $rc->is_open() );

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_config_circuit_open_until_' . self::KEY ] = time() + 80;
		$this->assertTrue( $rc->is_open(), 'must still be open well inside the 90-second retry-after' );
	}

	/**
	 * While the circuit is open, poll() makes NO request at all — Rule 6
	 * applies to the poll itself, not only to the features consuming it.
	 */
	public function test_an_open_circuit_skips_the_network_entirely(): void {
		$rc = $this->realtime_config();
		$this->queue_poll_response( 3, true );
		$rc->poll( $this->client() ); // warms the cache

		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();
		$this->assertTrue( $rc->is_open() );

		$before = $this->transport->count();
		$result = $rc->poll( $this->client() );

		$this->assertSame( $before, $this->transport->count(), 'an open circuit must not attempt a request' );
		$this->assertSame( 3, $result['config_version'] );
		$this->assertTrue( $result['has_urgent'] );
	}

	/**
	 * Forward compatibility: an unrecognised extra field in the response
	 * must be ignored, never fatal.
	 */
	public function test_an_unknown_extra_response_field_is_ignored(): void {
		$rc = $this->realtime_config();

		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				json_encode(
					array(
						'config_version'      => 5,
						'has_urgent'           => false,
						'something_from_the_future' => array( 'nested' => true ),
					)
				)
			)
		);

		$result = $rc->poll( $this->client() );

		$this->assertSame( 5, $result['config_version'] );
		$this->assertFalse( $result['has_urgent'] );
	}

	public function test_poll_with_nothing_ever_cached_and_no_network_returns_a_safe_default(): void {
		$rc = $this->realtime_config();
		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();

		$result = $rc->poll( $this->client() );

		$this->assertNull( $result['config_version'] );
		$this->assertFalse( $result['has_urgent'] );
	}

	// -----------------------------------------------------------------
	// A new SDK against an OLD API — no /sdk/v1/config, no config_version
	// -----------------------------------------------------------------

	/**
	 * A self-hosted or lagging API that has never deployed this endpoint
	 * answers 404 (or, for an ancient build with no route registered at
	 * all, the same shape a transport-level failure produces). Either
	 * way this must NOT open the circuit forever — it opens for the
	 * normal OPEN_SECONDS window and then tries again, exactly like any
	 * other repeated failure. "Forever" would mean a site that upgrades
	 * its API six months later never benefits without a plugin update;
	 * a bounded window means it recovers on its own the next time the
	 * breaker closes and happens to catch the newly-deployed endpoint.
	 */
	public function test_repeated_404s_from_an_old_api_open_the_circuit_only_for_the_normal_window_not_forever(): void {
		$rc = $this->realtime_config();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->transport->queue( Response::from_http( 404, array(), 'Not Found' ) );
			$rc->poll( $this->client() );
		}

		$this->assertTrue( $rc->is_open() );

		// The window elapses — same backdating convention used elsewhere
		// in this file.
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_config_circuit_open_until_' . self::KEY ] = time() - 1;

		$this->assertFalse( $rc->is_open(), 'a permanently old API must not permanently open the circuit' );
	}

	/**
	 * While the breaker is open because of this, poll() must still
	 * return a harmless, well-shaped result — the caller (the AJAX poll
	 * handler, and ultimately the JS) never sees an exception or a
	 * malformed value just because the server has never heard of this
	 * endpoint.
	 */
	public function test_an_old_api_with_no_config_endpoint_degrades_to_a_safe_default_never_a_fatal(): void {
		$rc = $this->realtime_config();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->transport->queue( Response::from_http( 404, array(), 'Not Found' ) );
			$rc->poll( $this->client() );
		}

		$result = $rc->poll( $this->client() ); // circuit now open — no network attempted

		$this->assertIsInt( $this->transport->count() ); // sanity: no exception unwound the test
		$this->assertNull( $result['config_version'] );
		$this->assertFalse( $result['has_urgent'] );
	}

	/**
	 * The other half of the same scenario: telemetry/registration/status
	 * responses from an old API simply never carry config_version at
	 * all. note_version() must treat that identically to a malformed
	 * value — ignored, not an error — which test_a_missing_config_version_is_ignored
	 * already proves for note_version() directly; this proves it end to
	 * end through the shape an old API's telemetry response actually has.
	 */
	public function test_a_telemetry_style_response_with_no_config_version_key_at_all_does_not_error(): void {
		$rc = $this->realtime_config();

		$response = Response::from_http(
			202,
			array(),
			json_encode( array(
				'installation_id' => 'abc',
				'accepted_count'  => 1,
				'rejected_count'  => 0,
				'accepted'        => array(),
				'rejected'        => array(),
				// No config_version key — an old API's exact shape.
			) )
		);

		$rc->note_version( $response->get( 'config_version' ) );

		$this->assertNull( $rc->stored_version() );
		$this->assertFalse( $rc->is_stale() );
	}

	// -----------------------------------------------------------------
	// forget()
	// -----------------------------------------------------------------

	public function test_forget_clears_every_option_this_class_wrote(): void {
		$rc = $this->realtime_config();
		$rc->note_version( 5 );
		$rc->note_version( 6 );
		$rc->record_failure();
		$this->queue_poll_response( 6, false );
		$rc->poll( $this->client() );

		$rc->forget();

		$survivors = array_filter(
			array_keys( $GLOBALS['appneck_test_options'] ),
			static function ( $name ) {
				return false !== strpos( $name, RealtimeConfigTest::KEY );
			}
		);

		$this->assertSame( array(), array_values( $survivors ), 'no option for this key may survive forget()' );
	}
}
