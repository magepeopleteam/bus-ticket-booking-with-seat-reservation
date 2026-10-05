<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Storage\LegacyStorageMigration;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * The migration that makes journal §35's fix safe to ship.
 *
 * Changing what local storage is keyed by is, for every site already in
 * the field, indistinguishable from the key rotation the fix exists to
 * survive — the site boots, looks in an empty namespace, decides it has
 * never registered, and collides with its own installation row. That is
 * not hypothetical: it happened to two live plugins on the development
 * site within minutes of the first cut of the fix landing, which is why
 * these tests start from a POPULATED legacy namespace rather than an
 * empty one. A migration test that starts empty cannot fail no matter
 * how broken the migration is (the same trap §13 §4's postmortem
 * records for cache-bypass tests).
 */
class LegacyStorageMigrationTest extends TestCase {

	const OLD_API_KEY = 'pk_deprecated_key';
	const PLUGIN_FILE = 'ecab-taxi-booking-manager/MPTBM_Plugin.php';

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		$GLOBALS['appneck_test_options'] = array();
	}

	/** Mirrors the $wpdb LIKE sweep against the polyfill's option array. */
	private function lister(): callable {
		return static function ( $key ) {
			$found = array();

			foreach ( array_keys( $GLOBALS['appneck_test_options'] ) as $name ) {
				if ( 0 === strpos( $name, 'appneck_sdk_' ) && substr( $name, -strlen( $key ) ) === $key ) {
					$found[] = $name;
				}
			}

			return $found;
		};
	}

	private function migration( $identity = self::PLUGIN_FILE ): LegacyStorageMigration {
		return new LegacyStorageMigration( $identity, $this->lister() );
	}

	private function legacyKey(): string {
		return LegacyStorageMigration::hash( self::OLD_API_KEY );
	}

	private function currentKey(): string {
		return LegacyStorageMigration::hash( self::PLUGIN_FILE );
	}

	/** A site that registered under the old scheme and has real data. */
	private function seedRegisteredLegacySite(): void {
		$key = $this->legacyKey();

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $key ] = array(
			'installation_id'     => 'inst-real',
			'installation_secret' => 'sk_real_secret',
		);
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_consent_' . $key ]           = array( 'status' => 'accepted' );
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_license_' . $key ]           = array( 'license_key' => 'lic_abc' );
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_announcements_' . $key ]     = array( 'announcements' => array() );
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_config_version_' . $key ]    = 13;
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_telemetry_env_hash_' . $key ] = 'envhash';
	}

	public function test_it_moves_every_option_to_the_current_namespace(): void {
		$this->seedRegisteredLegacySite();

		$this->assertTrue( $this->migration()->run( array( self::OLD_API_KEY ) ) );

		$new = $this->currentKey();

		$this->assertSame(
			array(
				'installation_id'     => 'inst-real',
				'installation_secret' => 'sk_real_secret',
			),
			$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $new ]
		);
		$this->assertSame( array( 'status' => 'accepted' ), $GLOBALS['appneck_test_options'][ 'appneck_sdk_consent_' . $new ] );
		$this->assertSame( array( 'license_key' => 'lic_abc' ), $GLOBALS['appneck_test_options'][ 'appneck_sdk_license_' . $new ] );
		$this->assertSame( 13, $GLOBALS['appneck_test_options'][ 'appneck_sdk_config_version_' . $new ] );
		$this->assertSame( 'envhash', $GLOBALS['appneck_test_options'][ 'appneck_sdk_telemetry_env_hash_' . $new ] );
	}

	public function test_it_leaves_nothing_behind_in_the_legacy_namespace(): void {
		$this->seedRegisteredLegacySite();

		$this->migration()->run( array( self::OLD_API_KEY ) );

		foreach ( array_keys( $GLOBALS['appneck_test_options'] ) as $name ) {
			$this->assertStringNotContainsString(
				$this->legacyKey(),
				$name,
				'A migrated site must not keep a second, divergent copy of its own state.'
			);
		}
	}

	/**
	 * The exact regression this migration was written for: the site had
	 * already failed 12 times and parked itself at MAX_ATTEMPTS before
	 * anyone noticed. Nothing lowers that counter again, so a migration
	 * that restored the credentials but left the counter would restore a
	 * site that still refuses to ever talk to the server.
	 */
	public function test_it_clears_a_give_up_state_left_by_the_failed_gap(): void {
		$this->seedRegisteredLegacySite();

		$new = $this->currentKey();

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_attempts_' . $new ]        = 12;
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_last_attempt_' . $new ]    = 1789803510;
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_installation_id_' . $new ] = 'inst-doomed';

		$this->migration()->run( array( self::OLD_API_KEY ) );

		$this->assertArrayNotHasKey( 'appneck_sdk_attempts_' . $new, $GLOBALS['appneck_test_options'] );
		$this->assertArrayNotHasKey( 'appneck_sdk_last_attempt_' . $new, $GLOBALS['appneck_test_options'] );
		$this->assertArrayNotHasKey( 'appneck_sdk_installation_id_' . $new, $GLOBALS['appneck_test_options'] );

		// ...while the thing that actually matters survived.
		$this->assertArrayHasKey( 'appneck_sdk_credentials_' . $new, $GLOBALS['appneck_test_options'] );
	}

	public function test_the_migrated_credentials_are_readable_through_the_real_store(): void {
		$this->seedRegisteredLegacySite();

		$this->migration()->run( array( self::OLD_API_KEY ) );

		$store = new WpOptionsCredentialStore( self::PLUGIN_FILE );

		$this->assertTrue( $store->has_credentials() );
		$this->assertSame( 'inst-real', $store->get_installation_id() );
		$this->assertSame( 'sk_real_secret', $store->get_installation_secret() );
	}

	public function test_it_runs_only_once(): void {
		$this->seedRegisteredLegacySite();

		$this->assertTrue( $this->migration()->run( array( self::OLD_API_KEY ) ) );

		// A later boot re-registers under the current namespace; the
		// migration must not reach back and undo it.
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->legacyKey() ] = array(
			'installation_id'     => 'inst-stale',
			'installation_secret' => 'sk_stale',
		);

		$this->assertFalse( $this->migration()->run( array( self::OLD_API_KEY ) ) );
		$this->assertSame(
			'inst-real',
			$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->currentKey() ]['installation_id']
		);
	}

	public function test_it_does_nothing_when_the_current_namespace_already_has_credentials(): void {
		$this->seedRegisteredLegacySite();

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->currentKey() ] = array(
			'installation_id'     => 'inst-native',
			'installation_secret' => 'sk_native',
		);

		$this->assertFalse( $this->migration()->run( array( self::OLD_API_KEY ) ) );
		$this->assertSame(
			'inst-native',
			$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->currentKey() ]['installation_id']
		);
	}

	public function test_a_fresh_install_migrates_nothing(): void {
		$this->assertFalse( $this->migration()->run( array( self::OLD_API_KEY ) ) );

		foreach ( array_keys( $GLOBALS['appneck_test_options'] ) as $name ) {
			$this->assertStringStartsWith( LegacyStorageMigration::MARKER_PREFIX, $name );
		}
	}

	/**
	 * Credentials are the signal, not merely "some option exists": a
	 * half-populated legacy namespace (a consent decision, no enrolment)
	 * is not a registered site and must not win over a later candidate.
	 */
	public function test_a_legacy_namespace_without_credentials_is_not_a_source(): void {
		$halfKey = LegacyStorageMigration::hash( 'pk_never_registered' );

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_consent_' . $halfKey ] = array( 'status' => 'accepted' );

		$this->seedRegisteredLegacySite();

		$this->assertTrue( $this->migration()->run( array( 'pk_never_registered', self::OLD_API_KEY ) ) );
		$this->assertSame(
			'inst-real',
			$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->currentKey() ]['installation_id']
		);
	}

	/**
	 * The development site that found this bug was left holding a
	 * namespace keyed by the absolute plugin path — a scheme that existed
	 * only between two commits and never shipped. The migration repairs
	 * it anyway, because its own predecessor created it.
	 */
	public function test_it_recovers_from_the_absolute_path_namespace_too(): void {
		$absolute = '/var/www/site/wp-content/plugins/' . self::PLUGIN_FILE;
		$pathKey  = LegacyStorageMigration::hash( $absolute );

		$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $pathKey ] = array(
			'installation_id'     => 'inst-from-path',
			'installation_secret' => 'sk_from_path',
		);

		$this->assertTrue( $this->migration()->run( array( 'pk_unused', $absolute ) ) );
		$this->assertSame(
			'inst-from-path',
			$GLOBALS['appneck_test_options'][ 'appneck_sdk_credentials_' . $this->currentKey() ]['installation_id']
		);
	}
}
