<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Sdk;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * Converted from tests/integration/live-check.php (S4.7 audit). What it
 * proves, unchanged from the original script:
 *
 *   1. A request signed with WRONG credentials gets a clean 401 that is
 *      RETURNED, not thrown — the failure mode a plugin will actually
 *      hit if its keys are wrong, on a live site.
 *   2. A request signed correctly by THIS client is ACCEPTED by the real
 *      server — i.e. the base string this SDK builds and the one
 *      VerifySdkSignature builds agree byte for byte.
 *   3. A request to an unreachable host fails safely, not by throwing.
 *
 * One change from the original: step 2 used to SKIP when no installation
 * credentials were passed on the command line. Here the test registers
 * its own throwaway installation first, so step 2 always runs rather
 * than depending on a human having a registered install handy.
 */
final class LiveCheckTest extends IntegrationTestCase {

	public function test_signing_against_the_real_backend() {
		$credentials = $this->credentials();

		echo "\nSDK version loaded: " . Sdk::loaded_version() . "\n\n";

		// -------------------------------------------------------------
		echo "1. Wrong credentials must produce a clean, non-throwing 401\n";

		$bad = new Client(
			new Config( $credentials->api_key(), 'sk_completely_the_wrong_secret_0000000000', $credentials->base_url() ),
			new ArrayCredentialStore( 'not-a-real-installation-id', 'sk_completely_the_wrong_secret_0000000000' )
		);

		$response = $bad->post( '/sdk/v1/telemetry', array( 'events' => array( array( 'type' => 'heartbeat', 'payload' => array( 'x' => 1 ) ) ) ) );

		$this->assertCheck( 'returned rather than threw', true );
		$this->assertCheck( 'status is 401', 401 === $response->status(), 'got ' . $response->status() );
		$this->assertCheck( 'ok() is false', ! $response->ok() );
		$this->assertCheck( 'is_unauthorized()', $response->is_unauthorized() );
		$this->assertCheck( 'not retryable', ! $response->is_retryable() );
		$this->assertCheck( 'server message surfaced', is_string( $response->error_message() ) && '' !== $response->error_message(), (string) $response->error_message() );
		echo '        server said: ' . $response->error_message() . "\n\n";

		// -------------------------------------------------------------
		echo "2. Correct credentials must be ACCEPTED by the real server\n";

		$site_domain = $this->random_domain( 's4-live-check' );
		$client      = $this->make_client( $credentials->api_key(), $credentials->product_secret() );
		$lifecycle   = new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
		$lifecycle->on_activate();
		$registration = $lifecycle->ensure_registered();

		$this->assertCheck( 'the throwaway installation registered (201)', 201 === $registration->status(), 'got ' . $registration->status() . ' — ' . $registration->error_message() );

		$store                = new WpOptionsCredentialStore( $credentials->api_key() );
		$installation_id      = $store->get_installation_id();
		$installation_secret  = $store->get_installation_secret();

		$good = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), $credentials->base_url() ),
			new ArrayCredentialStore( $installation_id, $installation_secret )
		);

		$response = $good->get( '/sdk/v1/announcements' );

		$this->assertCheck( 'status is 200', 200 === $response->status(), 'got ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'ok()', $response->ok() );
		$this->assertCheck( 'body decoded', is_array( $response->data() ) );
		$this->assertCheck( 'announcements key present', array_key_exists( 'announcements', $response->data() ) );

		$announcements = $response->get( 'announcements', array() );
		echo '        returned ' . count( $announcements ) . " announcement(s)\n";

		// Telemetry fails closed on consent (journal §5.4) — a fresh
		// throwaway installation starts `pending`, so it has to be
		// accepted before the signed POST below can be a meaningful
		// signing proof rather than a 403 for an unrelated reason.
		$consent = $good->post( '/sdk/v1/consent', array( 'status' => 'accepted', 'privacy_policy_version' => '1.0' ) );
		$this->assertCheck( 'consent accepted so telemetry is not refused for an unrelated reason', 200 === $consent->status(), 'got ' . $consent->status() );

		// A signed POST as well as a GET — the two body shapes sign
		// differently (empty string vs. encoded JSON), so one working does
		// not imply the other does.
		$telemetry = $good->post(
			'/sdk/v1/telemetry',
			array( 'events' => array( array( 'type' => 'heartbeat', 'payload' => array( 'source' => 'sdk-live-check' ) ) ) )
		);

		$this->assertCheck( 'signed POST accepted (202)', 202 === $telemetry->status(), 'got ' . $telemetry->status() . ' — ' . $telemetry->error_message() );
		$this->assertCheck( 'rate limit headers read', null !== $telemetry->rate_limit()->limit(), 'limit header missing' );
		echo '        rate limit: ' . $telemetry->rate_limit()->remaining() . ' of ' . $telemetry->rate_limit()->limit() . " remaining\n\n";

		// -------------------------------------------------------------
		echo "3. A request to an unreachable host must fail safely, not throw\n";

		$offline = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), 'http://127.0.0.1:9' ),
			new ArrayCredentialStore( $installation_id, $installation_secret )
		);

		$response = $offline->get( '/sdk/v1/announcements' );

		$this->assertCheck( 'returned rather than threw', true );
		$this->assertCheck( 'is_transport_error()', $response->is_transport_error() );
		$this->assertCheck( 'status is 0', 0 === $response->status() );
		$this->assertCheck( 'is retryable', $response->is_retryable() );
		echo '        transport said: ' . $response->error_message() . "\n\n";

		// -------------------------------------------------------------
		// Cleanup: this installation exists only to prove signing works.
		$lifecycle->on_uninstall();
	}
}
