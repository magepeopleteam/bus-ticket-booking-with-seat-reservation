<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * The wp_options store, exercised against polyfilled option functions
 * (see tests/wp-option-polyfill.php) so the real class runs rather than
 * a test double of it.
 */
class StorageTest extends TestCase {

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();
	}

	public function test_it_round_trips_a_credential_pair(): void {
		$store = new WpOptionsCredentialStore( 'pk_abc' );

		$this->assertFalse( $store->has_credentials() );

		$this->assertTrue( $store->save( 'inst-1', 'secret-1' ) );
		$this->assertTrue( $store->has_credentials() );
		$this->assertSame( 'inst-1', $store->get_installation_id() );
		$this->assertSame( 'secret-1', $store->get_installation_secret() );
	}

	/**
	 * Both halves live in ONE option so a partial restore cannot leave an
	 * id with no secret — an unauthenticatable, unrecoverable state,
	 * since the secret is issued once and never re-disclosed.
	 */
	public function test_both_halves_are_stored_in_a_single_option(): void {
		$store = new WpOptionsCredentialStore( 'pk_abc' );
		$store->save( 'inst-1', 'secret-1' );

		$this->assertCount( 1, $GLOBALS['appneck_test_options'] );
		$stored = reset( $GLOBALS['appneck_test_options'] );
		$this->assertSame(
			array(
				'installation_id'     => 'inst-1',
				'installation_secret' => 'secret-1',
			),
			$stored
		);
	}

	public function test_two_products_on_one_site_do_not_share_credentials(): void {
		$a = new WpOptionsCredentialStore( 'pk_product_a' );
		$b = new WpOptionsCredentialStore( 'pk_product_b' );

		$a->save( 'inst-a', 'secret-a' );

		$this->assertNotSame( $a->option_name(), $b->option_name() );
		$this->assertFalse( $b->has_credentials() );
	}

	/** The raw API key must not end up in an option name. */
	public function test_the_option_name_does_not_contain_the_api_key(): void {
		$store = new WpOptionsCredentialStore( 'pk_a_very_recognisable_key' );

		$this->assertStringNotContainsString( 'a_very_recognisable_key', $store->option_name() );
	}

	public function test_forget_clears_the_pair(): void {
		$store = new WpOptionsCredentialStore( 'pk_abc' );
		$store->save( 'inst-1', 'secret-1' );

		$this->assertTrue( $store->forget() );
		$this->assertFalse( $store->has_credentials() );
	}

	/**
	 * A hand-edited or corrupted option must read as "no credentials"
	 * rather than pushing a non-array into the signing path.
	 */
	public function test_a_corrupted_option_reads_as_no_credentials(): void {
		$store = new WpOptionsCredentialStore( 'pk_abc' );
		$GLOBALS['appneck_test_options'][ $store->option_name() ] = 'not-an-array';

		$this->assertFalse( $store->has_credentials() );
		$this->assertNull( $store->get_installation_id() );
	}

	/**
	 * Regression: two stores for the same product can exist in one
	 * request — the Lifecycle holds one, a plugin's own client another.
	 * An earlier per-instance cache meant a store that had already read
	 * "no credentials" kept saying so for the rest of the request even
	 * after the other instance registered, which reads as "registration
	 * silently did nothing" and invites a second attempt. Caught by the
	 * live lifecycle check, not by the unit suite, so it is pinned here.
	 */
	public function test_two_stores_in_one_request_see_each_others_writes(): void {
		$reader = new WpOptionsCredentialStore( 'pk_abc' );
		$writer = new WpOptionsCredentialStore( 'pk_abc' );

		// The reader looks first and finds nothing — this is what used to
		// poison its cache for the rest of the request.
		$this->assertFalse( $reader->has_credentials() );

		$writer->save( 'inst-1', 'secret-1' );

		$this->assertTrue( $reader->has_credentials() );
		$this->assertSame( 'inst-1', $reader->get_installation_id() );
		$this->assertSame( 'secret-1', $reader->get_installation_secret() );
	}

	public function test_a_store_sees_a_forget_from_another_instance(): void {
		$a = new WpOptionsCredentialStore( 'pk_abc' );
		$b = new WpOptionsCredentialStore( 'pk_abc' );

		$a->save( 'inst-1', 'secret-1' );
		$this->assertTrue( $b->has_credentials() );

		$a->forget();
		$this->assertFalse( $b->has_credentials() );
	}

	/** Re-saving identical credentials is a no-op, not a failure. */
	public function test_saving_the_same_values_twice_still_reports_success(): void {
		$store = new WpOptionsCredentialStore( 'pk_abc' );

		$this->assertTrue( $store->save( 'inst-1', 'secret-1' ) );
		$this->assertTrue( ( new WpOptionsCredentialStore( 'pk_abc' ) )->save( 'inst-1', 'secret-1' ) );
	}
}
