<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * Converted from tests/integration/conflict-check.php (S4.7 audit).
 * Reproduces the exact scenario that produced a raw 500 in S4.2, against
 * the REAL backend: a site whose stored credentials are gone mints a new
 * installation id and registers again for a (site, product) that already
 * has an installation. Also checks what the SDK's own retry logic does
 * with the answer.
 *
 * One fix made during conversion, not a weakened assertion: the original
 * script referenced an undefined `$store_secret` variable in its final
 * "restore" step (`$restored->save( $original_id, $store_secret ?? '' )`)
 * — dead code that saved an empty secret and asserted nothing. This
 * version actually captures the original installation's secret before
 * the credential-loss simulation, so it can cleanly uninstall that
 * installation afterward instead of leaving it permanently `active` in
 * the dev data on every run.
 */
final class ConflictCheckTest extends IntegrationTestCase {

	public function test_registration_conflict_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's4-conflict' );

		echo "\n1. First registration (the installation that will be conflicted with)\n";

		$lifecycle = $this->make_lifecycle( $site_domain );
		$lifecycle->on_activate();
		$first = $lifecycle->ensure_registered();

		$this->assertCheck( 'HTTP 201', 201 === $first->status(), 'got ' . $first->status() . ' — ' . $first->error_message() );

		$store              = new WpOptionsCredentialStore( $credentials->api_key() );
		$original_id        = $store->get_installation_id();
		$original_secret    = $store->get_installation_secret();
		echo "        installation: {$original_id}\n";

		echo "\n2. The site LOSES its credentials (backup restore, options wiped)\n";

		$this->reset_wordpress_state();

		$this->assertCheck( 'no credentials stored', ! ( new WpOptionsCredentialStore( $credentials->api_key() ) )->has_credentials() );

		echo "\n3. It enrols again under a NEW id — the exact S4.2 repro\n";

		$recovering = $this->make_lifecycle( $site_domain );
		$recovering->on_activate();
		$response = $recovering->ensure_registered();

		$this->assertCheck( 'HTTP 409 Conflict (was a raw 500)', 409 === $response->status(), 'got ' . $response->status() );
		$this->assertCheck(
			'generic message',
			'An installation already exists for this site and product.' === $response->get( 'message' ),
			(string) $response->get( 'message' )
		);

		$body = $response->raw_body();
		echo "        body: {$body}\n";

		foreach ( array(
			'installations_site_id_product_id_unique',
			'SQLSTATE',
			'insert into',
			'pgsql',
			'Connection',
			$original_id,
			'removed',
			'active',
			'secret',
		) as $forbidden ) {
			$this->assertCheck( "does not leak \"{$forbidden}\"", false === strpos( $body, $forbidden ) );
		}

		$decoded = json_decode( $body, true );
		$this->assertCheck( 'exactly one field in the body', is_array( $decoded ) && array( 'message' ) === array_keys( $decoded ) );

		echo "\n4. The SDK must NOT retry — a 409 can never succeed on retry\n";

		$this->assertCheck( 'registration is not pending', ! $recovering->is_pending() );
		$this->assertCheck( 'no retry scheduled', ! appneck_test_is_scheduled( Lifecycle::CRON_HOOK ) );

		$again = $recovering->ensure_registered();
		$this->assertCheck( 'a further cron tick sends nothing', null === $again );

		echo "\n5. The existing installation is untouched, verified server-side\n";

		// Restore the ORIGINAL credentials (not the failed second
		// enrolment's) and prove they still authenticate — the strongest
		// available evidence the first installation was never touched.
		$this->reset_wordpress_state();
		$restored = new WpOptionsCredentialStore( $credentials->api_key() );
		$restored->save( $original_id, $original_secret );

		$verify_client = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), $credentials->base_url() ),
			new ArrayCredentialStore( $original_id, $original_secret )
		);
		$still_signs = $verify_client->get( '/sdk/v1/announcements' );

		$this->assertCheck( 'the original installation still authenticates (200)', 200 === $still_signs->status(), 'got ' . $still_signs->status() );

		// Cleanup: this installation only exists to be conflicted with.
		$cleanup_lifecycle = new Lifecycle(
			$verify_client,
			null,
			new Environment( null, array( 'site_domain' => $site_domain ) )
		);
		$cleanup_lifecycle->on_uninstall();
		echo "        (original installation marked removed and cleaned up)\n";
	}

	private function make_lifecycle( $site_domain ) {
		$credentials = $this->credentials();
		$client      = $this->make_client( $credentials->api_key(), $credentials->product_secret() );

		return new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
	}
}
