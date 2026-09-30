<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use PHPUnit\Framework\TestCase;

/**
 * The caching, fail-open and backoff behaviour of is_valid(), and the
 * two human-triggered calls either side of it.
 *
 * This is the most thoroughly tested class in the package on purpose.
 * Everything here runs inside customers' WordPress sites and cannot be
 * recalled: a caching bug locks paying customers out of what they bought,
 * and a backoff bug turns one outage into a flood aimed at the server
 * that is trying to recover from it. Neither is fixable after the fact
 * on a site that installed version N and will run it for three years.
 */
class LicenseTest extends TestCase {

	const API_KEY        = 'pk_license_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const BASE_URL       = 'https://api.example.test';
	const DOMAIN         = 'example.test';

	const KEY       = 'lic_0000000000000000000000000000000000000001';
	const OTHER_KEY = 'lic_0000000000000000000000000000000000000002';

	/** @var QueueingTransport */
	private $transport;

	/** @var ArrayLicenseStore */
	private $store;

	/** @var RecordingLogger */
	private $logger;

	protected function setUp(): void {
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$this->transport = new QueueingTransport();
		$this->store     = new ArrayLicenseStore();
		$this->logger    = new RecordingLogger();
	}

	/** @param array<string, mixed> $options */
	private function license( array $options = array() ): License {
		$license = new License(
			new LicenseClient(
				new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
				$this->transport,
				$this->logger
			),
			$this->store,
			$this->logger,
			$options
		);

		return $license->set_domain( self::DOMAIN );
	}

	/**
	 * Seeds the store directly, so a test can start from a state that
	 * would otherwise take hours of wall-clock to reach.
	 *
	 * @param array<string, mixed> $state
	 */
	private function seed( array $state ): void {
		$this->store->write(
			array_merge(
				array(
					'license_key'      => self::KEY,
					'valid'            => true,
					'status'           => 'active',
					'validated_domain' => self::DOMAIN,
					'validated_at'     => time(),
					'failure_count'    => 0,
					'failed_at'        => 0,
					'next_attempt_at'  => 0,
				),
				$state
			)
		);
	}

	/** @return array<string, mixed> */
	private function stored(): array {
		return $this->store->read();
	}

	/**
	 * Moves every stored timestamp $seconds into the past. Rewinding the
	 * data rather than mocking time() keeps the production code free of a
	 * clock seam it would otherwise carry forever for the sake of tests.
	 */
	private function rewind_clock( int $seconds ): void {
		$state = $this->store->read();

		foreach ( array( 'validated_at', 'failed_at', 'next_attempt_at' ) as $field ) {
			if ( ! empty( $state[ $field ] ) ) {
				$state[ $field ] = (int) $state[ $field ] - $seconds;
			}
		}

		$this->store->write( $state );
	}

	private function queue_valid(): void {
		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				'{"valid":true,"status":"active","expires_at":"2027-01-01T00:00:00.000000Z"}'
			)
		);
	}

	private function queue_invalid( string $reason = 'not_activated_on_domain' ): void {
		$this->transport->queue(
			Response::from_http( 200, array(), '{"valid":false,"reason":"' . $reason . '"}' )
		);
	}

	private function queue_transport_failure(): void {
		$this->transport->queue( Response::from_transport_error( 'cURL error 28: Operation timed out' ) );
	}

	// -----------------------------------------------------------------
	// The hot path
	// -----------------------------------------------------------------

	public function test_no_key_stored_is_false_with_zero_http_calls(): void {
		$this->assertFalse( $this->license()->is_valid() );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_a_fresh_cache_returns_the_cached_value_with_zero_http_calls(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 60,
			)
		);

		$this->assertTrue( $this->license()->is_valid() );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_a_fresh_valid_cache_from_another_domain_is_revalidated(): void {
		$this->seed(
			array(
				'valid'            => true,
				'validated_domain' => 'old.example',
				'validated_at'     => time() - 60,
			)
		);
		$this->queue_invalid();

		$this->assertFalse( $this->license()->is_valid() );
		$this->assertSame( 1, $this->transport->count() );

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( self::DOMAIN, $body['domain'] );
		$this->assertSame( self::DOMAIN, $this->stored()['validated_domain'] );
	}

	public function test_legacy_cached_state_without_a_domain_revalidates_without_crashing(): void {
		$this->seed( array( 'valid' => true, 'validated_at' => time() - 60 ) );
		$legacy = $this->stored();
		unset( $legacy['validated_domain'] );
		$this->store->write( $legacy );
		$this->queue_valid();

		$this->assertTrue( $this->license()->is_valid() );
		$this->assertSame( 1, $this->transport->count() );
		$this->assertSame( self::DOMAIN, $this->stored()['validated_domain'] );
	}

	public function test_a_fresh_negative_cache_is_also_answered_without_a_call(): void {
		$this->seed(
			array(
				'valid'        => false,
				'validated_at' => time() - 60,
			)
		);

		$this->assertFalse( $this->license()->is_valid() );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_the_cache_ttl_is_configurable_and_defaults_to_24_hours(): void {
		$this->assertSame( 86400, $this->license()->cache_ttl() );
		$this->assertSame( 60, $this->license( array( 'license_cache_ttl' => 60 ) )->cache_ttl() );
	}

	public function test_an_expired_cache_revalidates_and_a_valid_answer_updates_it(): void {
		$this->seed(
			array(
				'valid'        => false,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_valid();

		$this->assertTrue( $this->license()->is_valid() );
		$this->assertSame( 1, $this->transport->count() );

		$stored = $this->stored();
		$this->assertTrue( $stored['valid'] );
		$this->assertSame( 'active', $stored['status'] );
		$this->assertSame( '2027-01-01T00:00:00.000000Z', $stored['expires_at'] );
		$this->assertGreaterThan( time() - 5, $stored['validated_at'] );
	}

	public function test_an_expired_cache_revalidates_and_an_invalid_answer_is_honoured(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_invalid( 'expired' );

		$this->assertFalse( $this->license()->is_valid() );

		$stored = $this->stored();
		$this->assertFalse( $stored['valid'] );
		$this->assertSame( 'expired', $stored['reason'] );
	}

	/**
	 * The fix journal §27.6/§28 records: the server now includes `status`
	 * and `expires_at` on a rejection (they were already loaded to decide
	 * the rejection in the first place), specifically so a stale value
	 * from an earlier SUCCESS does not go on being displayed once the
	 * license has expired. This asserts the CLIENT side of that fix:
	 * scalar_or() already reads any present field unconditionally, so no
	 * SDK code change was needed — this pins that it actually works, not
	 * just that it should in theory.
	 */
	public function test_an_expired_rejection_updates_the_stored_expiry_rather_than_preserving_the_stale_one(): void {
		$this->seed(
			array(
				'valid'        => true,
				'status'       => 'active',
				'expires_at'   => '2027-01-01T00:00:00.000000Z', // The stale value.
				'validated_at' => time() - 90000,
			)
		);

		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				'{"valid":false,"reason":"expired","status":"active","expires_at":"2026-06-01T00:00:00.000000Z"}'
			)
		);

		$this->assertFalse( $this->license()->is_valid() );

		$stored = $this->stored();
		$this->assertSame( 'expired', $stored['reason'] );
		// The NEW date from the rejection, not the one seeded above.
		$this->assertSame( '2026-06-01T00:00:00.000000Z', $stored['expires_at'] );
		$this->assertSame( 'active', $stored['status'] );
	}

	// -----------------------------------------------------------------
	// Fail-open
	// -----------------------------------------------------------------

	public function test_fail_open_returns_the_last_known_valid_answer(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_transport_failure();

		$this->assertTrue( $this->license()->is_valid() );
	}

	public function test_fail_open_never_reuses_a_valid_answer_from_another_domain(): void {
		$this->seed(
			array(
				'valid'            => true,
				'validated_domain' => 'old.example',
				'validated_at'     => time() - 60,
			)
		);
		$this->queue_transport_failure();

		$this->assertFalse( $this->license()->is_valid() );
		$this->assertSame( 1, $this->transport->count() );

		// The failed attempt opens a backoff for the NEW domain, while
		// remaining unable to reuse the old domain's valid answer.
		$this->assertFalse( $this->license()->is_valid() );
		$this->assertSame( 1, $this->transport->count() );
	}

	/**
	 * The distinction the whole fail-open design turns on: an outage must
	 * not UPGRADE a known-invalid license to valid. If it did, the system
	 * would be defeatable by blocking one hostname.
	 */
	public function test_fail_open_does_not_upgrade_a_last_known_invalid_answer(): void {
		$this->seed(
			array(
				'valid'        => false,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_transport_failure();

		$this->assertFalse( $this->license()->is_valid() );
	}

	public function test_fail_open_is_false_when_a_key_has_never_validated_successfully(): void {
		$this->seed(
			array(
				'valid'        => null,
				'validated_at' => 0,
			)
		);
		$this->queue_transport_failure();

		$this->assertFalse( $this->license()->is_valid() );
	}

	public function test_fail_closed_is_false_even_with_a_last_known_valid_answer(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_transport_failure();

		$this->assertFalse( $this->license( array( 'license_fail_mode' => 'closed' ) )->is_valid() );
	}

	public function test_the_fail_mode_defaults_to_open_and_an_unrecognised_value_stays_open(): void {
		$this->assertSame( License::FAIL_OPEN, $this->license()->fail_mode() );
		$this->assertSame( License::FAIL_OPEN, $this->license( array( 'license_fail_mode' => 'clsoed' ) )->fail_mode() );
		$this->assertSame( License::FAIL_CLOSED, $this->license( array( 'license_fail_mode' => 'closed' ) )->fail_mode() );
	}

	/**
	 * A 500 is not the server saying this license is invalid. Treating it
	 * as a definitive rejection would let one bad deploy revoke every
	 * customer in the field at once.
	 */
	public function test_a_server_error_is_not_a_definitive_answer(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->transport->queue( Response::from_http( 500, array(), '{"message":"Server Error"}' ) );

		$license = $this->license();

		$this->assertTrue( $license->is_valid() );
		$this->assertSame( 1, $license->failure_count() );
	}

	// -----------------------------------------------------------------
	// Backoff
	// -----------------------------------------------------------------

	public function test_after_a_failure_the_next_call_makes_zero_http_calls(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_transport_failure();

		$license = $this->license();
		$license->is_valid();
		$this->assertSame( 1, $this->transport->count() );

		// Same page load or the next one — the cache is still expired, so
		// only the backoff is stopping a second attempt.
		$license->is_valid();
		$license->is_valid();

		$this->assertSame( 1, $this->transport->count() );
	}

	public function test_the_backoff_escalates_through_the_sequence(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		$license  = $this->license();
		$observed = array();

		foreach ( License::BACKOFF as $expected_base ) {
			$this->queue_transport_failure();
			$license->is_valid();

			$observed[] = $license->next_attempt_at() - time();

			// Step past the window this failure just opened, without
			// refreshing validated_at — the cache stays expired.
			$this->rewind_clock( License::MAX_BACKOFF * 2 );
		}

		$this->assertCount( count( License::BACKOFF ), $observed );

		foreach ( License::BACKOFF as $index => $base ) {
			$spread = (int) round( $base * License::JITTER_PERCENT / 100 );
			$lower  = $base - $spread;
			$upper  = min( $base + $spread, License::MAX_BACKOFF );

			$this->assertGreaterThanOrEqual(
				$lower - 2,
				$observed[ $index ],
				'Failure ' . ( $index + 1 ) . ' waited less than the jittered minimum.'
			);
			$this->assertLessThanOrEqual(
				$upper + 2,
				$observed[ $index ],
				'Failure ' . ( $index + 1 ) . ' waited more than the jittered maximum.'
			);
		}
	}

	public function test_the_backoff_never_exceeds_24_hours_however_many_failures(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		$license = $this->license();

		// Well past the end of the sequence: the last entry must repeat,
		// clamped, rather than growing or wrapping.
		for ( $i = 0; $i < 12; $i++ ) {
			$this->queue_transport_failure();
			$license->is_valid();

			$this->assertLessThanOrEqual(
				License::MAX_BACKOFF,
				$license->next_attempt_at() - time(),
				'Attempt ' . ( $i + 1 ) . ' scheduled a retry more than 24 hours out.'
			);

			$this->rewind_clock( License::MAX_BACKOFF * 2 );
		}

		$this->assertSame( 12, $license->failure_count() );
	}

	/**
	 * Asserted as a RANGE, never an exact value — an exact assertion here
	 * would either be testing mt_rand's seeding or quietly passing
	 * because the jitter had been removed.
	 */
	public function test_jitter_is_applied_within_twenty_percent_of_the_base(): void {
		$base   = License::BACKOFF[0];
		$spread = (int) round( $base * License::JITTER_PERCENT / 100 );

		$observed = array();

		for ( $i = 0; $i < 40; $i++ ) {
			$this->store = new ArrayLicenseStore();
			$this->seed(
				array(
					'valid'        => true,
					'validated_at' => time() - 90000,
				)
			);
			$this->queue_transport_failure();

			$license = $this->license();
			$license->is_valid();

			$observed[] = $license->next_attempt_at() - time();
		}

		foreach ( $observed as $delay ) {
			$this->assertGreaterThanOrEqual( $base - $spread - 2, $delay );
			$this->assertLessThanOrEqual( $base + $spread + 2, $delay );
		}

		// And it is genuinely varying. Forty draws from a 121-value range
		// landing on one number would mean the jitter is not applied at
		// all, which is the failure this whole test exists to catch.
		$this->assertGreaterThan(
			1,
			count( array_unique( $observed ) ),
			'Every backoff delay was identical — the jitter is not being applied.'
		);
	}

	public function test_the_backoff_resets_on_a_successful_call(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		$license = $this->license();

		$this->queue_transport_failure();
		$license->is_valid();
		$this->assertSame( 1, $license->failure_count() );
		$this->assertGreaterThan( 0, $license->next_attempt_at() );

		$this->rewind_clock( License::MAX_BACKOFF * 2 );

		$this->queue_valid();
		$this->assertTrue( $license->is_valid() );

		$this->assertSame( 0, $license->failure_count() );
		$this->assertSame( 0, $license->next_attempt_at() );
	}

	/**
	 * A failure must not refresh validated_at. If it did, a string of
	 * outages would keep a cached answer alive indefinitely and the site
	 * would stop re-checking a license that had since been cancelled.
	 */
	public function test_a_failure_does_not_refresh_the_cache_timestamp(): void {
		$validated_at = time() - 90000;
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => $validated_at,
			)
		);
		$this->queue_transport_failure();

		$this->license()->is_valid();

		$this->assertSame( $validated_at, $this->stored()['validated_at'] );
	}

	// -----------------------------------------------------------------
	// activate()
	// -----------------------------------------------------------------

	public function test_activate_success_stores_the_key_and_the_result(): void {
		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				'{"valid":true,"customer_name":"Ada Lovelace","expires_at":"2027-01-01T00:00:00.000000Z",'
				. '"activation_limit":5,"activations_used":2}'
			)
		);

		$license  = $this->license();
		$response = $license->activate( self::KEY );

		$this->assertTrue( $response->ok() );

		$stored = $this->stored();
		$this->assertSame( self::KEY, $stored['license_key'] );
		$this->assertTrue( $stored['valid'] );
		$this->assertSame( 5, $stored['activation_limit'] );
		$this->assertSame( 2, $stored['activations_used'] );
		$this->assertSame( 'Ada Lovelace', $stored['customer_name'] );
		// /activate answers valid:true only for an active, unexpired
		// license, but carries no status field of its own.
		$this->assertSame( 'active', $stored['status'] );
		$this->assertSame( self::DOMAIN, $stored['validated_domain'] );

		// And the site is immediately licensed, from cache, with no
		// further call.
		$this->assertTrue( $license->is_valid() );
		$this->assertSame( 1, $this->transport->count() );
	}

	public function test_activate_rejection_does_not_store_the_key(): void {
		$this->transport->queue( Response::from_http( 200, array(), '{"valid":false,"reason":"license_not_found"}' ) );

		$license = $this->license();
		$license->activate( self::KEY );

		$this->assertSame( array(), $this->stored() );
		$this->assertFalse( $license->has_license() );
	}

	public function test_activate_transport_failure_does_not_store_the_key(): void {
		$this->queue_transport_failure();

		$license  = $this->license();
		$response = $license->activate( self::KEY );

		$this->assertTrue( $response->is_transport_error() );
		$this->assertSame( array(), $this->stored() );
	}

	/**
	 * Activating a second key must not inherit the first one's expiry or
	 * activation counts — those describe a different license.
	 */
	public function test_activating_a_new_key_replaces_the_previous_state_entirely(): void {
		$this->seed(
			array(
				'license_key'      => self::KEY,
				'expires_at'       => '2020-01-01T00:00:00.000000Z',
				'activation_limit' => 1,
				'activations_used' => 1,
				'reason'           => 'expired',
			)
		);

		$this->transport->queue(
			Response::from_http( 200, array(), '{"valid":true,"activation_limit":10,"activations_used":1}' )
		);

		$this->license()->activate( self::OTHER_KEY );

		$stored = $this->stored();
		$this->assertSame( self::OTHER_KEY, $stored['license_key'] );
		$this->assertNull( $stored['expires_at'] );
		$this->assertSame( 10, $stored['activation_limit'] );
		$this->assertNull( $stored['reason'] );
	}

	public function test_activate_sends_the_normalized_domain(): void {
		$this->transport->queue( Response::from_http( 200, array(), '{"valid":true}' ) );

		$license = $this->license();
		$license->set_domain( 'https://Example.TEST/wp/' );
		$license->activate( self::KEY );

		$body = json_decode( $this->transport->last_request()['body'], true );

		$this->assertSame( self::DOMAIN, $body['domain'] );
		$this->assertSame( self::KEY, $body['license_key'] );
	}

	// -----------------------------------------------------------------
	// deactivate()
	// -----------------------------------------------------------------

	public function test_deactivate_success_clears_all_state(): void {
		$this->seed( array() );
		$this->transport->queue( Response::from_http( 200, array(), '{"success":true}' ) );

		$license = $this->license();
		$license->deactivate( self::KEY );

		$this->assertSame( array(), $this->stored() );
		$this->assertFalse( $license->has_license() );
		$this->assertFalse( $license->is_valid() );
	}

	/**
	 * The failure mode that would strand a customer: the server still
	 * counts this domain as holding a slot, so forgetting the key here
	 * would leave them nothing to release it with.
	 */
	public function test_deactivate_transport_failure_preserves_local_state(): void {
		$this->seed( array() );
		$this->queue_transport_failure();

		$license  = $this->license();
		$response = $license->deactivate( self::KEY );

		$this->assertTrue( $response->is_transport_error() );
		$this->assertTrue( $license->has_license() );
		$this->assertSame( self::KEY, $this->stored()['license_key'] );
	}

	public function test_deactivate_stored_uses_the_key_this_site_holds(): void {
		$this->seed( array() );
		$this->transport->queue( Response::from_http( 200, array(), '{"success":true}' ) );

		$this->license()->deactivate_stored();

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( self::KEY, $body['license_key'] );
		$this->assertSame( array(), $this->stored() );
	}

	public function test_deactivate_stored_makes_no_call_when_nothing_is_stored(): void {
		$this->assertNull( $this->license()->deactivate_stored() );
		$this->assertSame( 0, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// validate() called directly
	// -----------------------------------------------------------------

	/**
	 * A support tool or a form preview checking some OTHER key must not
	 * overwrite this site's own license state — or its backoff.
	 */
	public function test_validating_a_different_key_does_not_touch_the_stored_state(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$before = $this->stored();

		$this->queue_invalid( 'license_not_found' );
		$this->license()->validate( self::OTHER_KEY );

		$this->assertSame( $before, $this->stored() );
	}

	public function test_a_direct_validate_of_the_stored_key_refreshes_the_cache(): void {
		$this->seed(
			array(
				'valid'        => false,
				'validated_at' => time() - 90000,
			)
		);
		$this->queue_valid();

		$license = $this->license();
		$license->validate( self::KEY );

		$this->assertTrue( $license->is_valid() );
		$this->assertSame( 1, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// get_status()
	// -----------------------------------------------------------------

	public function test_get_status_makes_no_network_call_even_with_an_expired_cache(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		$this->license()->get_status();

		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_get_status_masks_the_key_to_its_last_four_characters(): void {
		$this->seed( array() );

		$status = $this->license()->get_status();

		$this->assertSame( '****0001', $status['license_key'] );
		$this->assertStringNotContainsString( self::KEY, $status['license_key'] );
	}

	public function test_get_status_reports_stale_only_when_expired_and_failing(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 60,
			)
		);
		$this->assertFalse( $this->license()->get_status()['stale'] );

		// Expired but healthy: about to refresh itself, not worth a
		// warning on anyone's screen.
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);
		$this->assertFalse( $this->license()->get_status()['stale'] );

		$this->queue_transport_failure();
		$license = $this->license();
		$license->is_valid();

		$status = $license->get_status();
		$this->assertTrue( $status['stale'] );
		$this->assertTrue( $status['valid'] );
		$this->assertNotNull( $status['next_attempt_at'] );
	}

	public function test_get_status_is_blank_when_no_license_is_stored(): void {
		$status = $this->license()->get_status();

		$this->assertFalse( $status['has_license'] );
		$this->assertSame( '', $status['license_key'] );
		$this->assertFalse( $status['valid'] );
		$this->assertNull( $status['last_checked_at'] );
		$this->assertFalse( $status['stale'] );
	}

	/**
	 * /validate's success shape carries status and expires_at but NOT the
	 * activation counts, so a validate after an activate must not blank
	 * the "2 of 5 sites" the customer is looking at.
	 */
	public function test_a_validate_does_not_erase_fields_it_does_not_carry(): void {
		$this->seed(
			array(
				'activation_limit' => 5,
				'activations_used' => 2,
				'validated_at'     => time() - 90000,
			)
		);
		$this->queue_valid();

		$this->license()->is_valid();

		$status = $this->license()->get_status();
		$this->assertSame( 5, $status['activation_limit'] );
		$this->assertSame( 2, $status['activations_used'] );
	}

	// -----------------------------------------------------------------
	// Uninstall
	// -----------------------------------------------------------------

	public function test_uninstall_releases_the_slot_and_deletes_the_row(): void {
		$this->seed( array() );
		$this->transport->queue( Response::from_http( 200, array(), '{"success":true}' ) );

		$this->license()->on_uninstall();

		$this->assertSame( 1, $this->transport->count() );
		$this->assertStringEndsWith( '/sdk/v1/licenses/deactivate', $this->transport->last_request()['url'] );
		$this->assertSame( array(), $this->stored() );
	}

	/**
	 * The one place deactivate()'s keep-state-on-failure rule is
	 * deliberately inverted: the plugin is going away either way, so
	 * there is no local state left for a retry to live in.
	 */
	public function test_uninstall_deletes_the_row_even_when_the_call_fails(): void {
		$this->seed( array() );
		$this->queue_transport_failure();

		$this->license()->on_uninstall();

		$this->assertSame( array(), $this->stored() );
	}

	public function test_uninstall_makes_no_call_when_no_license_is_stored(): void {
		$this->assertNull( $this->license()->on_uninstall() );
		$this->assertSame( 0, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// Domain normalization
	// -----------------------------------------------------------------

	/**
	 * The exact fixture list this SDK and the SERVER were run over
	 * side by side, in the same PHP build, before this test was written —
	 * App\Models\LicenseActivation::normalizeDomain() produced byte-
	 * identical output for all seventeen. See the README's licensing
	 * section and journal §22.8.
	 *
	 * This MUST match rather than approximate: the server enforces "one
	 * domain cannot hold two active activations" with a Postgres partial
	 * unique index on the normalized string, so a client that normalized
	 * differently would let one install quietly consume two slots.
	 *
	 * Note `www.myshop.com` staying `www.myshop.com`. Journal §22.8 is
	 * explicit that www is NOT stripped — it is a genuinely different
	 * host, and merging it would collapse a real multi-site setup into
	 * one slot.
	 *
	 * A loop rather than a data-provider annotation deliberately: composer.json
	 * allows PHPUnit ^9.6 || ^10.5, PHPUnit 12 no longer reads metadata
	 * from doc comments, and attributes do not parse on the PHP versions
	 * the older constraint implies. Nothing else in this suite uses a
	 * provider either, so a loop is the portable shape here.
	 */
	public function test_domain_normalization_matches_the_servers_cases(): void {
		foreach ( self::domain_fixtures() as $label => $case ) {
			$this->assertSame(
				$case[1],
				License::normalize_domain( $case[0] ),
				'Domain fixture "' . $label . '" does not match the server.'
			);
		}
	}

	/** @return array<string, array<int, string>> */
	public static function domain_fixtures(): array {
		return array(
			'scheme and trailing slash' => array( 'https://myshop.com/', 'myshop.com' ),
			'http scheme'               => array( 'http://myshop.com', 'myshop.com' ),
			'scheme, no slash'          => array( 'https://myshop.com', 'myshop.com' ),
			'bare host'                 => array( 'myshop.com', 'myshop.com' ),
			'mixed case host'           => array( 'https://MyShop.com/', 'myshop.com' ),
			'upper case throughout'     => array( 'HTTPS://MYSHOP.COM/PATH', 'myshop.com' ),
			'www is NOT stripped'       => array( 'https://www.myshop.com/', 'www.myshop.com' ),
			'bare www host'             => array( 'www.myshop.com', 'www.myshop.com' ),
			'path is stripped'          => array( 'https://myshop.com/blog', 'myshop.com' ),
			'subdirectory install'      => array( 'https://myshop.com/wp/', 'myshop.com' ),
			'surrounding whitespace'    => array( '  https://myshop.com/  ', 'myshop.com' ),
			'port is kept'              => array( 'https://myshop.com:8080/', 'myshop.com:8080' ),
			'subdomain is kept'         => array( 'https://sub.myshop.com/', 'sub.myshop.com' ),
			'any scheme is stripped'    => array( 'ftp://myshop.com/', 'myshop.com' ),
			'punycode is untouched'     => array( 'https://xn--bcher-kva.example/', 'xn--bcher-kva.example' ),
			'protocol-relative'         => array( '//myshop.com/', '' ),
			'empty string'              => array( '', '' ),
		);
	}

	public function test_the_domain_defaults_to_home_url_when_not_set_explicitly(): void {
		require_once __DIR__ . '/wp-home-url-polyfill.php';

		$GLOBALS['appneck_test_home_url'] = 'https://Fixture.Example/wp/';

		$license = new License(
			new LicenseClient( new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ), $this->transport ),
			$this->store
		);

		$this->assertSame( 'fixture.example', $license->domain() );

		unset( $GLOBALS['appneck_test_home_url'] );
	}

	// -----------------------------------------------------------------
	// Robustness
	// -----------------------------------------------------------------

	/**
	 * This code will still be running on sites in five years, on options
	 * that have been through backups, migrations and search-replace
	 * tools. A corrupted row must read as "no license", never fatal.
	 */
	public function test_a_corrupted_stored_value_reads_as_no_license(): void {
		foreach ( self::corrupt_states() as $label => $case ) {
			$license = new License(
				new LicenseClient( new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ), $this->transport ),
				new ArrayLicenseStore( $case[0] )
			);

			$this->assertFalse( $license->is_valid(), 'Corrupt state "' . $label . '" was not read as unlicensed.' );
		}

		$this->assertSame( 0, $this->transport->count() );
	}

	/** @return array<string, array<int, mixed>> */
	public static function corrupt_states(): array {
		return array(
			'empty'                 => array( array() ),
			'key is an array'       => array( array( 'license_key' => array( 'nope' ) ) ),
			'key is null'           => array( array( 'license_key' => null ) ),
			'empty key with result' => array(
				array(
					'license_key'  => '',
					'valid'        => true,
					'validated_at' => 999,
				),
			),
		);
	}

	public function test_a_malformed_response_body_is_not_a_definitive_answer(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		// A caching plugin or a WAF replacing the body with HTML — common
		// enough in the wild to be worth pinning.
		$this->transport->queue( Response::from_http( 200, array(), '<html>Service Unavailable</html>' ) );

		$license = $this->license();

		$this->assertTrue( $license->is_valid() );
		$this->assertSame( 1, $license->failure_count() );
	}

	// -----------------------------------------------------------------
	// require_valid() (Phase 8) — the gate a pro feature's own page calls
	// -----------------------------------------------------------------

	public function test_require_valid_returns_true_and_prints_nothing_when_the_license_is_valid(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';

		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time(),
			)
		);

		ob_start();
		$result = $this->license()->require_valid();
		$output = (string) ob_get_clean();

		$this->assertTrue( $result );
		$this->assertSame( '', $output );
	}

	public function test_require_valid_returns_false_and_prints_a_notice_when_there_is_no_license(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';

		ob_start();
		$result = $this->license()->require_valid();
		$output = (string) ob_get_clean();

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'license key', $output );
	}

	public function test_require_valid_surfaces_the_actual_rejection_reason(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';

		$this->seed(
			array(
				'valid'        => false,
				'reason'       => 'suspended',
				'validated_at' => time(),
			)
		);

		ob_start();
		$this->license()->require_valid();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'suspended', strtolower( $output ) );
	}

	public function test_require_valid_accepts_a_custom_message(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';

		ob_start();
		$this->license()->require_valid( 'Upgrade to Pro to use this.' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Upgrade to Pro to use this.', $output );
	}

	public function test_require_valid_links_to_the_page_url_once_one_is_set(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';

		$license = $this->license()->set_page_url( 'https://example.test/wp-admin/admin.php?page=acme-license' );

		ob_start();
		$license->require_valid();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'https://example.test/wp-admin/admin.php?page=acme-license', $output );
	}
}
