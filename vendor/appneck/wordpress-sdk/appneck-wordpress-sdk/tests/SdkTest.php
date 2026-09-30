<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Sdk;
use PHPUnit\Framework\TestCase;

/**
 * Wiring-level coverage for journal §35, on top of ConfigTest's coverage
 * of the underlying mechanism: `Sdk::client()` must actually pass a
 * storage identity through to the credential store it builds, not just
 * offer the parameter.
 */
class SdkTest extends TestCase {

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();
	}

	public function test_client_without_a_storage_identity_still_works_as_documented(): void {
		$client = Sdk::client( 'pk_abc', 'sk_secret', 'https://appneck.com' );

		$this->assertSame( 'pk_abc', $client->config()->storage_identity() );
		$this->assertFalse( $client->credentials()->has_credentials() );
	}

	/**
	 * The actual regression, exercised through the public entry point
	 * rather than the internals: two clients built for the same plugin
	 * file but different (rotated) api_keys must share the same
	 * credential storage.
	 */
	public function test_client_credentials_survive_an_api_key_rotation_when_the_plugin_file_is_unchanged(): void {
		$plugin_file = 'ecab-taxi-booking-manager/ecab-taxi-booking-manager.php';

		$before = Sdk::client( 'pk_old_deprecated_key', 'sk_secret', 'https://appneck.com', null, null, null, $plugin_file );
		$before->credentials()->save( 'inst-1', 'secret-1' );

		$after = Sdk::client( 'pk_new_active_key', 'sk_secret', 'https://appneck.com', null, null, null, $plugin_file );

		$this->assertTrue( $after->credentials()->has_credentials() );
		$this->assertSame( 'inst-1', $after->credentials()->get_installation_id() );
	}
}
