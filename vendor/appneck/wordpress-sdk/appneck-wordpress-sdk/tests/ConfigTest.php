<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Config;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for journal §35: local storage (credentials, the
 * event queue, license cache, and the realtime-config/consent/survey/
 * announcements namespaces) must stay findable across a product API key
 * rotation — it must NOT be keyed by the api_key itself.
 *
 * `product_api_keys` supports rotating a product's key
 * (active/deprecated/revoked, journal §11.4). Before this fix, every
 * class that stores anything locally hashed `Config::api_key()` directly
 * to build its option/table name. A plugin release that shipped a
 * rotated key made an already-registered site compute a brand-new,
 * empty namespace, conclude it had never registered, and attempt a
 * fresh enrolment that collided with its own still-active installation
 * row — permanently, since Lifecycle gives up retrying after
 * MAX_ATTEMPTS with no self-heal.
 */
class ConfigTest extends TestCase {

	public function test_storage_identity_defaults_to_the_api_key_when_not_given(): void {
		$config = new Config( 'pk_abc', 'sk_secret', 'https://appneck.com' );

		$this->assertSame( 'pk_abc', $config->storage_identity() );
	}

	public function test_storage_identity_falls_back_on_an_empty_string_override(): void {
		$config = new Config( 'pk_abc', 'sk_secret', 'https://appneck.com', '' );

		$this->assertSame( 'pk_abc', $config->storage_identity() );
	}

	public function test_storage_identity_uses_the_given_override_instead_of_the_api_key(): void {
		$config = new Config(
			'pk_abc',
			'sk_secret',
			'https://appneck.com',
			'plugin-dir/plugin-main-file.php'
		);

		$this->assertSame( 'plugin-dir/plugin-main-file.php', $config->storage_identity() );
		$this->assertNotSame( $config->api_key(), $config->storage_identity() );
	}

	/**
	 * The actual regression: a site registered under an old key must
	 * still find its own credentials after the plugin starts presenting
	 * a rotated key, as long as its storage identity (the plugin file)
	 * did not change.
	 */
	public function test_rotating_the_api_key_does_not_orphan_stored_credentials(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();

		$plugin_file = 'ecab-taxi-booking-manager/ecab-taxi-booking-manager.php';

		$before = new Config( 'pk_old_deprecated_key', 'sk_secret', 'https://appneck.com', $plugin_file );
		( new WpOptionsCredentialStore( $before->storage_identity() ) )->save( 'inst-1', 'secret-1' );

		// The plugin ships an update with a rotated key. Same site, same
		// plugin file, different api_key.
		$after = new Config( 'pk_new_active_key', 'sk_secret', 'https://appneck.com', $plugin_file );
		$store_after = new WpOptionsCredentialStore( $after->storage_identity() );

		$this->assertTrue(
			$store_after->has_credentials(),
			'Credentials saved under the old key must still be visible after the api_key rotates, ' .
			'as long as the plugin file (storage identity) is unchanged.'
		);
		$this->assertSame( 'inst-1', $store_after->get_installation_id() );
	}
}
