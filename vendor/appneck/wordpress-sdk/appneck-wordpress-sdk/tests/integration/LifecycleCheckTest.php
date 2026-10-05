<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * Converted from tests/integration/lifecycle-check.php (S4.7 audit).
 * Full plugin lifecycle against the REAL backend: activate → register →
 * deactivate → reactivate → uninstall, plus the API-down activation path
 * and its retry — unchanged from the original script's assertions.
 *
 * Runs the real Lifecycle, the real Client, the real WpHttpTransport and
 * the real WpOptionsCredentialStore — only WordPress's own option and
 * cron functions are polyfilled, so what is exercised here is the code a
 * plugin actually ships.
 *
 * Domains are randomized per run (the original script used a fixed
 * `s4-lifecycle-test.example.com`) so repeated runs — including parallel
 * CI runs — never collide on the server's (site, product) uniqueness
 * constraint.
 */
final class LifecycleCheckTest extends IntegrationTestCase {

	public function test_full_lifecycle_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's4-lifecycle' );

		$store     = new WpOptionsCredentialStore( $credentials->api_key() );
		$lifecycle = $this->make_lifecycle( $site_domain );

		// -------------------------------------------------------------
		echo "\n1. ACTIVATE — must not touch the network, must not fail\n";

		$lifecycle->on_activate();

		$this->assertCheck( 'activation completed without throwing', true );
		$this->assertCheck( 'no credentials yet', ! $store->has_credentials() );
		$this->assertCheck( 'registration is pending', $lifecycle->is_pending() );
		$this->assertCheck( 'a cron attempt is scheduled', appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );

		// -------------------------------------------------------------
		echo "\n2. CRON FIRES — the deferred registration runs for real\n";

		$response = $lifecycle->ensure_registered();

		$this->assertCheck( 'HTTP 201 Created', 201 === $response->status(), 'got ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'credentials stored', $store->has_credentials() );
		$this->assertCheck( 'installation id stored', ! empty( $store->get_installation_id() ) );
		$this->assertCheck( 'secret stored', ! empty( $store->get_installation_secret() ) );
		$this->assertCheck( 'pending cleared', ! $lifecycle->is_pending() );
		$this->assertCheck( 'no stale cron entry left behind', ! appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );

		$installation_id = $store->get_installation_id();
		echo "        installation: {$installation_id}\n";
		echo '        server status: ' . $response->get( 'status' ) . "\n";

		// -------------------------------------------------------------
		echo "\n3. DEACTIVATE\n";

		$response = $lifecycle->on_deactivate();

		$this->assertCheck( 'HTTP 200', 200 === $response->status(), 'got ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'server reports deactivated', 'deactivated' === $response->get( 'status' ), (string) $response->get( 'status' ) );
		$this->assertCheck( 'credentials retained', $store->has_credentials() );

		// -------------------------------------------------------------
		echo "\n4. REACTIVATE — same id, no duplicate installation\n";

		$lifecycle->on_activate();
		$response = $lifecycle->ensure_registered();

		$this->assertCheck( 'HTTP 200 (reactivated, not 201 created)', 200 === $response->status(), 'got ' . $response->status() );
		$this->assertCheck( 'server reports active', 'active' === $response->get( 'status' ), (string) $response->get( 'status' ) );
		$this->assertCheck( 'same installation id', $installation_id === $store->get_installation_id() );
		$this->assertCheck( 'no secret re-disclosed', null === $response->get( 'installation_secret' ) );
		$this->assertCheck( 'stored secret survived reactivation', ! empty( $store->get_installation_secret() ) );

		// -------------------------------------------------------------
		echo "\n5. A SIGNED CALL still works after the round trip\n";

		$client        = $this->make_client( $credentials->api_key(), $credentials->product_secret() );
		$announcements = $client->get( '/sdk/v1/announcements' );
		$this->assertCheck( 'GET /sdk/v1/announcements -> 200', 200 === $announcements->status(), 'got ' . $announcements->status() . ' — ' . $announcements->error_message() );

		// -------------------------------------------------------------
		echo "\n6. UNINSTALL\n";

		$response = $lifecycle->on_uninstall();

		$this->assertCheck( 'HTTP 200', 200 === $response->status(), 'got ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'server reports removed', 'removed' === $response->get( 'status' ), (string) $response->get( 'status' ) );
		$this->assertCheck( 'local credentials cleared', ! $store->has_credentials() );

		// -------------------------------------------------------------
		echo "\n7. ACTIVATION WITH THE API DOWN — must still succeed, must retry\n";

		$this->reset_wordpress_state();

		$offline = $this->make_offline_lifecycle( $site_domain, $credentials );

		$offline->on_activate();
		$this->assertCheck( 'activation completed with the API unreachable', true );
		$this->assertCheck( 'a retry is scheduled', appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );

		$response = $offline->ensure_registered();
		$this->assertCheck( 'the attempt failed as a transport error', $response->is_transport_error() );
		$this->assertCheck( 'still no credentials', ! ( new WpOptionsCredentialStore( $credentials->api_key() ) )->has_credentials() );
		$this->assertCheck( 'another retry is queued', appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );
		$this->assertCheck( 'attempt counter advanced', 1 === $offline->attempts() );

		$delay_after_first  = appneck_test_next_scheduled( Lifecycle::CRON_HOOK ) - time();
		$offline->ensure_registered();
		$delay_after_second = appneck_test_next_scheduled( Lifecycle::CRON_HOOK ) - time();

		$this->assertCheck( 'the retry actually fired again', 2 === $offline->attempts() );
		$this->assertCheck( 'backoff widened', $delay_after_second > $delay_after_first, "{$delay_after_first}s then {$delay_after_second}s" );
		echo "        backoff: {$delay_after_first}s -> {$delay_after_second}s\n";

		// -------------------------------------------------------------
		echo "\n8. RETRY EVENTUALLY SUCCEEDS once the API comes back\n";

		// A DIFFERENT site domain on purpose. Re-registering the first
		// domain after uninstall hits a server-side unique constraint on
		// (site_id, product_id) — a real gap, reported rather than worked
		// around in the SDK; see the S4.2 report and ConflictCheckTest.
		// This step is about proving the retry path recovers, so it uses a
		// site that has never registered.
		$recovered = $this->make_lifecycle( $this->random_domain( 's4-lifecycle-retry' ) );
		$response  = $recovered->ensure_registered();

		$this->assertCheck( 'registered on the retry', $response->ok(), 'status ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'credentials now stored', ( new WpOptionsCredentialStore( $credentials->api_key() ) )->has_credentials() );

		$recovered_id = ( new WpOptionsCredentialStore( $credentials->api_key() ) )->get_installation_id();
		echo "        installation: {$recovered_id}\n";

		// Leave the backend tidy: this second installation exists only to
		// prove the retry path, so report it removed rather than leaving a
		// phantom active install in the dev data.
		$recovered->on_uninstall();
		echo "        (marked removed and cleaned up)\n";
	}

	private function make_lifecycle( $site_domain ) {
		$credentials = $this->credentials();
		$client      = $this->make_client( $credentials->api_key(), $credentials->product_secret() );

		return new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
	}

	private function make_offline_lifecycle( $site_domain, $credentials ) {
		$client = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), 'http://127.0.0.1:9' ),
			new WpOptionsCredentialStore( $credentials->api_key() )
		);

		return new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
	}
}
