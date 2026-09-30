<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * The plugin lifecycle: activate → register, deactivate, reactivate,
 * uninstall — plus the retry machinery that lets activation succeed
 * while Appneck is unreachable.
 *
 * Runs against polyfilled wp_options and wp_cron functions so the real
 * Lifecycle code executes, rather than a double of it.
 */
class LifecycleTest extends TestCase {

	const API_KEY        = 'pk_lifecycle_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const BASE_URL       = 'https://api.example.test';

	/** @var QueueingTransport */
	private $transport;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-cron-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_options'] = array();
		$GLOBALS['appneck_test_cron']    = array();

		$this->transport = new QueueingTransport();
	}

	private function lifecycle(): Lifecycle {
		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new WpOptionsCredentialStore( self::API_KEY ),
			$this->transport
		);

		return new Lifecycle( $client, null, new Environment() );
	}

	/**
	 * Deliberately no fixed `id` field: a real server's response to a
	 * NORMAL (non-reclaim) registration always echoes back the same id
	 * the request was sent under, so the client falls back to the sent
	 * id when the field is absent here — see
	 * test_a_successful_registration_stores_the_credential_pair, which
	 * asserts exactly that agreement. journal §9.2b's reclaim case, where
	 * the response id deliberately DIFFERS from the sent id, has its own
	 * fixture below (registration_reclaim_success()).
	 */
	private function registration_success( $secret = 'sk_issued_secret' ): Response {
		return Response::from_http(
			201,
			array(),
			json_encode(
				array(
					'status'              => 'active',
					'installation_secret' => $secret,
				)
			)
		);
	}

	/**
	 * journal §9.2b: what the server actually returns on a successful
	 * reclaim — a DIFFERENT id (the existing row's real one) than
	 * whatever the client just generated and sent, plus a fresh secret.
	 */
	private function registration_reclaim_success( $reclaimed_id, $secret = 'sk_reclaimed_secret' ): Response {
		return Response::from_http(
			200,
			array(),
			json_encode(
				array(
					'id'                  => $reclaimed_id,
					'status'              => 'active',
					'installation_secret' => $secret,
				)
			)
		);
	}

	// -----------------------------------------------------------------
	// Activation must never touch the network
	// -----------------------------------------------------------------

	public function test_activation_performs_no_http_and_schedules_instead(): void {
		$lifecycle = $this->lifecycle();

		$lifecycle->on_activate();

		$this->assertSame( 0, $this->transport->count(), 'Activation must not call the API' );
		$this->assertTrue( $lifecycle->is_pending() );
		$this->assertTrue( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
	}

	/**
	 * The requirement in one test: even with the API completely down,
	 * activation completes without throwing and a retry is queued.
	 */
	public function test_activation_succeeds_when_the_api_is_unreachable(): void {
		$this->transport->queue( Response::from_transport_error( 'could not connect' ) );

		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		// The deferred first attempt now runs and fails.
		$response = $lifecycle->ensure_registered();

		$this->assertNotNull( $response );
		$this->assertTrue( $response->is_transport_error() );
		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );
		$this->assertTrue( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ), 'A retry must be queued' );
		$this->assertSame( 1, $lifecycle->attempts() );
	}

	public function test_a_failed_attempt_backs_off_and_eventually_gives_up(): void {
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		for ( $i = 0; $i < Lifecycle::MAX_ATTEMPTS; $i++ ) {
			$this->transport->queue( Response::from_transport_error( 'still down' ) );
			$lifecycle->ensure_registered();
		}

		$this->assertSame( Lifecycle::MAX_ATTEMPTS, $lifecycle->attempts() );

		// One more call must be a no-op rather than another request.
		$before = $this->transport->count();
		$lifecycle->ensure_registered();

		$this->assertSame( $before, $this->transport->count(), 'Must stop after MAX_ATTEMPTS' );
	}

	/**
	 * The delay widens rather than hammering once a minute forever.
	 */
	public function test_the_retry_delay_widens(): void {
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		$delays = array();

		for ( $i = 0; $i < 4; $i++ ) {
			$this->transport->queue( Response::from_transport_error( 'down' ) );
			$lifecycle->ensure_registered();
			$delays[] = appneck_test_next_scheduled( Lifecycle::CRON_HOOK ) - time();
		}

		$this->assertGreaterThan( $delays[0], $delays[1] );
		$this->assertGreaterThan( $delays[1], $delays[2] );
	}

	/**
	 * An archived product is a permanent refusal — retrying for a day
	 * changes nothing.
	 */
	public function test_a_403_stops_retrying_immediately(): void {
		$this->transport->queue(
			Response::from_http( 403, array(), '{"message":"This product is archived and is not accepting new installations."}' )
		);

		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$this->assertFalse( $lifecycle->is_pending() );
		$this->assertFalse( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
	}

	/**
	 * A 409 means an installation already exists for this site and
	 * product — the conflict is on (site, product), so every retry hits
	 * the same wall. Retrying would be twelve futile requests on someone
	 * else's server across several days.
	 */
	public function test_a_409_stops_retrying_immediately(): void {
		$this->transport->queue(
			Response::from_http( 409, array(), '{"message":"An installation already exists for this site and product."}' )
		);

		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$this->assertFalse( $lifecycle->is_pending() );
		$this->assertFalse( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ), 'A 409 must not queue a retry' );

		// And a further tick sends nothing rather than looping.
		$before = $this->transport->count();
		$lifecycle->ensure_registered();
		$this->assertSame( $before, $this->transport->count() );
	}

	/**
	 * Toggling the plugin is the recovery trigger once an operator has
	 * resolved the conflict server-side — activation resets the counter.
	 */
	public function test_reactivating_retries_after_a_permanent_failure(): void {
		$this->transport->queue( Response::from_http( 409, array(), '{"message":"conflict"}' ) );

		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$before = $this->transport->count();

		$lifecycle->on_activate();
		$this->transport->queue( $this->registration_success() );
		$lifecycle->ensure_registered();

		$this->assertSame( $before + 1, $this->transport->count() );
		$this->assertTrue( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );
	}

	// -----------------------------------------------------------------
	// Registration
	// -----------------------------------------------------------------

	public function test_a_successful_registration_stores_the_credential_pair(): void {
		$this->transport->queue( $this->registration_success() );

		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$store = new WpOptionsCredentialStore( self::API_KEY );
		$this->assertTrue( $store->has_credentials() );
		$this->assertSame( 'sk_issued_secret', $store->get_installation_secret() );

		$sent = $this->transport->last_request();
		$this->assertSame( 'POST', $sent['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/installations', $sent['url'] );

		// The id sent must be the one stored — the whole pair has to
		// agree or nothing can be signed afterwards.
		$this->assertSame( $sent['headers']['X-Installation-Id'], $store->get_installation_id() );

		$this->assertFalse( $lifecycle->is_pending() );
		$this->assertFalse( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
	}

	public function test_registration_is_signed_with_the_product_secret(): void {
		$this->transport->queue( $this->registration_success() );

		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$sent     = $this->transport->last_request();
		$expected = \Appneck\Sdk\Signer::sign(
			'POST',
			'/sdk/v1/installations',
			$sent['headers']['X-Installation-Id'],
			$sent['headers']['X-Timestamp'],
			$sent['body'],
			self::PRODUCT_SECRET
		);

		$this->assertSame( $expected, $sent['headers']['X-Signature'] );
	}

	/**
	 * Minting a fresh id per attempt would leave half-registered rows on
	 * the server whenever a response was lost after the row was written.
	 */
	public function test_retries_reuse_the_same_installation_id(): void {
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		$this->transport->queue( Response::from_transport_error( 'down' ) );
		$lifecycle->ensure_registered();
		$first = $this->transport->last_request()['headers']['X-Installation-Id'];

		$this->transport->queue( Response::from_transport_error( 'down' ) );
		$lifecycle->ensure_registered();
		$second = $this->transport->last_request()['headers']['X-Installation-Id'];

		$this->assertSame( $first, $second );
	}

	public function test_an_already_registered_site_does_not_register_again(): void {
		$this->transport->queue( $this->registration_success() );

		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$after_first = $this->transport->count();
		$lifecycle->ensure_registered();

		$this->assertSame( $after_first, $this->transport->count() );
	}

	/**
	 * A 200 with no secret is only a problem when nothing is stored —
	 * then there is genuinely no way to sign anything.
	 */
	public function test_a_secretless_response_with_no_stored_credentials_retries(): void {
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		$this->transport->queue( Response::from_http( 200, array(), '{"id":"x","status":"active"}' ) );
		$lifecycle->ensure_registered();

		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );
		$this->assertTrue( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
	}

	/**
	 * A 200 with no `installation_secret` means the server already knew
	 * this id and will not re-disclose the secret (journal §9.2a). We
	 * cannot sign with it, and cannot ask again — so the id is discarded
	 * and the next attempt enrols afresh under a new one.
	 */
	public function test_a_response_without_a_secret_re_enrols_under_a_new_id(): void {
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();

		$this->transport->queue( Response::from_http( 200, array(), '{"id":"x","status":"active"}' ) );
		$lifecycle->ensure_registered();
		$first = $this->transport->last_request()['headers']['X-Installation-Id'];

		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );

		$this->transport->queue( $this->registration_success() );
		$lifecycle->ensure_registered();
		$second = $this->transport->last_request()['headers']['X-Installation-Id'];

		$this->assertNotSame( $first, $second );
	}

	// -----------------------------------------------------------------
	// Deactivate / reactivate / uninstall
	// -----------------------------------------------------------------

	public function test_deactivation_reports_deactivated_signed_with_the_installation_secret(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$this->transport->queue( Response::from_http( 200, array(), '{"status":"deactivated"}' ) );
		$lifecycle->on_deactivate();

		$sent = $this->transport->last_request();
		$this->assertSame( self::BASE_URL . '/sdk/v1/installations/status', $sent['url'] );
		$this->assertSame( '{"status":"deactivated"}', $sent['body'] );

		$expected = \Appneck\Sdk\Signer::sign(
			'POST',
			'/sdk/v1/installations/status',
			$sent['headers']['X-Installation-Id'],
			$sent['headers']['X-Timestamp'],
			$sent['body'],
			'sk_issued_secret'
		);

		$this->assertSame( $expected, $sent['headers']['X-Signature'] );
	}

	/**
	 * Reactivation runs the same registration flow with the SAME stored
	 * id — the server's endpoint is create-or-reactivate (journal 9.3),
	 * so there is no client-side reactivation logic at all.
	 */
	public function test_reactivation_reuses_the_stored_credentials_and_creates_no_duplicate(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$store       = new WpOptionsCredentialStore( self::API_KEY );
		$original_id = $store->get_installation_id();
		$original_secret = $store->get_installation_secret();

		$this->transport->queue( Response::from_http( 200, array(), '{"status":"deactivated"}' ) );
		$lifecycle->on_deactivate();

		// Reactivating MUST talk to the server. The site is `deactivated`
		// server-side until something says otherwise, and the endpoint is
		// create-or-reactivate — so the same registration flow runs again
		// rather than any client-side reactivation logic.
		$lifecycle->on_activate();

		// The reactivation response carries NO installation_secret: the
		// server reactivated the existing record and correctly declines to
		// re-disclose it (journal §9.2a).
		$this->transport->queue( Response::from_http( 200, array(), '{"id":"x","status":"active"}' ) );
		$lifecycle->ensure_registered();

		$sent = $this->transport->last_request();
		$this->assertSame( self::BASE_URL . '/sdk/v1/installations', $sent['url'] );

		// Same id — so the server reactivates rather than creating a
		// duplicate — and the stored secret survives, which is what makes
		// every later signed request keep working.
		$this->assertSame( $original_id, $sent['headers']['X-Installation-Id'] );
		$this->assertSame( $original_id, $store->get_installation_id() );
		$this->assertSame( $original_secret, $store->get_installation_secret() );
	}

	/**
	 * The routine cron tick must NOT re-register on every run — only an
	 * explicit activation forces a call.
	 */
	public function test_a_routine_cron_tick_on_a_registered_site_is_a_no_op(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->on_activate();
		$lifecycle->ensure_registered();

		$after = $this->transport->count();
		$lifecycle->ensure_registered();
		$lifecycle->ensure_registered();

		$this->assertSame( $after, $this->transport->count() );
	}

	public function test_uninstall_reports_removed_and_clears_credentials(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$this->transport->queue( Response::from_http( 200, array(), '{"status":"removed"}' ) );
		$lifecycle->on_uninstall();

		$this->assertSame( '{"status":"removed"}', $this->transport->last_request()['body'] );
		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );
	}

	/**
	 * Credentials are cleared even when the status call failed: the
	 * plugin's data is being deleted either way, and the server has
	 * lost-installation detection for anything that goes silent.
	 */
	public function test_uninstall_clears_credentials_even_if_the_call_fails(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$this->transport->queue( Response::from_transport_error( 'down' ) );
		$lifecycle->on_uninstall();

		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );
	}

	// -----------------------------------------------------------------
	// journal §9.2b — installation reclaim
	// -----------------------------------------------------------------

	/**
	 * The exact S5.1 scenario end to end, client-side: uninstall (real
	 * removal call, real token captured from its response) then a later
	 * activation on the same site sends that stored token, and the
	 * client adopts whatever id the server's reclaim response actually
	 * returns — NOT the fresh id it locally generated and sent.
	 */
	public function test_a_stored_reclaim_token_is_sent_on_the_next_registration_and_the_returned_id_is_adopted(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$original_id = ( new WpOptionsCredentialStore( self::API_KEY ) )->get_installation_id();

		$this->transport->queue(
			Response::from_http( 200, array(), json_encode( array( 'status' => 'removed', 'reclaim_token' => 'rt_test_token_value' ) ) )
		);
		$lifecycle->on_uninstall();

		$this->assertFalse( ( new WpOptionsCredentialStore( self::API_KEY ) )->has_credentials() );

		// A later activation on the same site: a fresh Lifecycle, exactly
		// as a real reinstall would construct one, sharing only the
		// wp_options this test's polyfill backs.
		$reinstalled = $this->lifecycle();
		$this->transport->queue( $this->registration_reclaim_success( $original_id, 'sk_reclaimed_secret' ) );
		$reinstalled->on_activate();
		$reinstalled->ensure_registered();

		$sent = $this->transport->last_request();
		$this->assertSame( 'rt_test_token_value', json_decode( $sent['body'], true )['reclaim_token'] );

		// The freshly-generated id that was SENT must differ from the
		// original — this is a genuinely new local enrolment attempt.
		$this->assertNotSame( $original_id, $sent['headers']['X-Installation-Id'] );

		// But what's STORED afterward is the id the server actually
		// confirmed — the original row, reclaimed — not the one just sent.
		$store = new WpOptionsCredentialStore( self::API_KEY );
		$this->assertSame( $original_id, $store->get_installation_id() );
		$this->assertSame( 'sk_reclaimed_secret', $store->get_installation_secret() );
	}

	/** No prior removal on this site — nothing stored, nothing sent. */
	public function test_no_reclaim_token_is_sent_when_none_was_ever_stored(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$sent = $this->transport->last_request();
		$this->assertArrayNotHasKey( 'reclaim_token', json_decode( $sent['body'], true ) );
	}

	/**
	 * A normal (non-reclaim) registration response never includes an
	 * `id` field that differs from what was sent — but even so, the
	 * client's fallback to the sent id when the field is entirely absent
	 * must keep working, since real servers may not always echo it.
	 */
	public function test_registration_without_a_response_id_falls_back_to_the_sent_id(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$sent  = $this->transport->last_request();
		$store = new WpOptionsCredentialStore( self::API_KEY );
		$this->assertSame( $sent['headers']['X-Installation-Id'], $store->get_installation_id() );
	}

	/**
	 * A registration that fails outright (409: no valid reclaim, or none
	 * offered) must not silently drop the stored token — it may still be
	 * valid for a retry within the same grace window, and the SDK cannot
	 * tell from a bare failure whether the token itself was the problem.
	 * Only a PERMANENT failure (403/409, via give_up()) or a SUCCESSFUL
	 * registration consumes it.
	 */
	public function test_a_409_during_reclaim_clears_the_stored_token(): void {
		$this->transport->queue( $this->registration_success() );
		$lifecycle = $this->lifecycle();
		$lifecycle->ensure_registered();

		$this->transport->queue(
			Response::from_http( 200, array(), json_encode( array( 'status' => 'removed', 'reclaim_token' => 'rt_will_be_rejected' ) ) )
		);
		$lifecycle->on_uninstall();

		$reinstalled = $this->lifecycle();
		$this->transport->queue( Response::from_http( 409, array(), '{"message":"An installation already exists for this site and product."}' ) );
		$reinstalled->on_activate();
		$reinstalled->ensure_registered();

		// 409 is permanent — give_up() runs, and the now-rejected token
		// is cleared rather than offered again on a future independent
		// activation attempt. Reactivating (as the class doc says) resets
		// the attempt counter give_up() maxed out, so this is a genuinely
		// new attempt, not a blocked retry of the same one.
		$reinstalled->on_activate();
		$this->transport->queue( $this->registration_success() );
		$reinstalled->ensure_registered();
		$second_sent = $this->transport->last_request();
		$this->assertArrayNotHasKey( 'reclaim_token', json_decode( $second_sent['body'], true ) );
	}

	// -----------------------------------------------------------------
	// Nothing to report
	// -----------------------------------------------------------------

	public function test_deactivating_an_unregistered_site_does_nothing_quietly(): void {
		$lifecycle = $this->lifecycle();

		$this->assertNull( $lifecycle->on_deactivate() );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_uninstalling_an_unregistered_site_does_nothing_quietly(): void {
		$lifecycle = $this->lifecycle();

		$this->assertNull( $lifecycle->on_uninstall() );
		$this->assertSame( 0, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// Multisite
	// -----------------------------------------------------------------

	/**
	 * Network activation must not fan out into one API call per site —
	 * on a large network that is a guaranteed timeout. Each site
	 * registers itself lazily instead, which works because wp_options is
	 * per-site: switching sites changes what has_credentials() sees.
	 */
	public function test_network_activation_does_not_fan_out(): void {
		$lifecycle = $this->lifecycle();

		$lifecycle->on_activate( true );

		$this->assertSame( 0, $this->transport->count() );
		$this->assertTrue( appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
	}

	/**
	 * Simulates two sites of a network by swapping the options table, the
	 * way switch_to_blog does. Each site enrols separately, with its own
	 * id — which is what makes the per-site model work at all.
	 */
	public function test_each_site_in_a_network_registers_separately(): void {
		$site_one = array();
		$site_two = array();

		$GLOBALS['appneck_test_options'] = &$site_one;
		$this->transport->queue( $this->registration_success( 'sk_site_one' ) );
		$this->lifecycle()->ensure_registered();
		$id_one = $this->transport->last_request()['headers']['X-Installation-Id'];

		unset( $GLOBALS['appneck_test_options'] );
		$GLOBALS['appneck_test_options'] = &$site_two;
		$this->transport->queue( $this->registration_success( 'sk_site_two' ) );
		$this->lifecycle()->ensure_registered();
		$id_two = $this->transport->last_request()['headers']['X-Installation-Id'];

		$this->assertNotSame( $id_one, $id_two );
		$this->assertSame( 'sk_site_one', $site_one[ ( new WpOptionsCredentialStore( self::API_KEY ) )->option_name() ]['installation_secret'] );
		$this->assertSame( 'sk_site_two', $site_two[ ( new WpOptionsCredentialStore( self::API_KEY ) )->option_name() ]['installation_secret'] );
	}
}
