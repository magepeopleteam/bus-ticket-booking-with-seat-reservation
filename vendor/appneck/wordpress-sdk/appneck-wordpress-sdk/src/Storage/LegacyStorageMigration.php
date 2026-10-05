<?php

namespace Appneck\Sdk\Storage;

use Appneck\Sdk\Queue\TableEventQueue;

/**
 * Moves an already-registered site's local storage from a legacy
 * namespace to the current one, exactly once.
 *
 * ## Why this exists
 *
 * Everything this package stores locally is namespaced by
 * `substr( sha256( <identity> ), 0, 32 )`. That identity used to be the
 * product's **API key** — which journal §35 records as a real bug, since
 * a key can rotate (`product_api_keys` supports active/deprecated/
 * revoked) and a plugin release shipping a rotated key made every
 * already-registered site look, to itself, like it had never registered.
 * It would then attempt a fresh enrolment, collide with its own
 * still-active installation row, take a 409, and give up permanently.
 *
 * The fix was to key storage by something that survives rotation. But
 * **changing the identity is itself exactly that event** — the first
 * boot after the update, every already-registered site in the field
 * looks in a new, empty namespace and reproduces the whole failure. That
 * is not theoretical: it happened on the development site this was
 * written on, to both plugins, within minutes of the new code landing.
 * The fix is only complete with this migration beside it.
 *
 * ## What it does
 *
 * If the current namespace has no credentials but a legacy one does,
 * every `appneck_sdk_*_<legacy>` option is moved to `<current>`, the
 * queued telemetry rows are re-pointed, and a marker is written so this
 * never runs twice. Credentials are the signal, because they are the one
 * thing that cannot be reconstructed: the installation secret is issued
 * once and never re-disclosed (journal §9.2a), so a site that loses
 * track of them has no recovery that does not involve a human.
 *
 * Discovery is a `LIKE` sweep over the option names rather than a
 * hardcoded list of suffixes. A list would be correct on the day it was
 * written and quietly incomplete the first time a feature adds a new
 * option — and "quietly incomplete" here means silent data loss on a
 * customer's site.
 */
final class LegacyStorageMigration {

	const MARKER_PREFIX = 'appneck_sdk_storage_migrated_';

	const OPTION_PREFIX = 'appneck_sdk_';

	/**
	 * Retry/give-up bookkeeping, cleared on the target after a migration.
	 *
	 * A migrated namespace has credentials by definition, so any attempt
	 * counter that accumulated while the SDK believed itself unregistered
	 * is not just stale, it is actively harmful: `Lifecycle::give_up()`
	 * parks `attempts` at MAX_ATTEMPTS and nothing lowers it again, so a
	 * site that gave up during the gap would stay given-up forever.
	 * Clearing these is what `Lifecycle::clear_pending()` already does on
	 * a successful registration — the same state, reached another way.
	 *
	 * `installation_id` goes too: it is the id of an in-flight enrolment
	 * that must never be reused now that the real, confirmed id is back.
	 */
	const TRANSIENT_LIFECYCLE_SUFFIXES = array(
		'pending',
		'attempts',
		'last_attempt',
		'force',
		'installation_id',
	);

	/** @var string */
	private $key;

	/** @var callable|null */
	private $lister;

	/**
	 * @param string        $identity Current storage identity (NOT hashed).
	 * @param callable|null $lister   Returns every existing option name
	 *                                ending in a given 32-char key. Defaults
	 *                                to a $wpdb query; injectable so tests
	 *                                drive this exact code rather than a
	 *                                parallel branch written for them.
	 */
	public function __construct( $identity, $lister = null ) {
		$this->key    = self::hash( $identity );
		$this->lister = $lister;
	}

	public static function hash( $identity ) {
		return substr( hash( 'sha256', (string) $identity ), 0, 32 );
	}

	/**
	 * @param array<int, string> $legacy_identities Tried in order; the first
	 *                                              holding credentials wins.
	 * @return bool Whether anything was moved.
	 */
	public function run( array $legacy_identities ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}

		if ( '' !== (string) get_option( $this->marker_name(), '' ) ) {
			return false;
		}

		// Already living in the current namespace: nothing to move, and
		// the marker stops this from being asked again on every boot.
		if ( $this->has_credentials( $this->key ) ) {
			$this->mark_done();

			return false;
		}

		$source = null;

		foreach ( $legacy_identities as $identity ) {
			$candidate = self::hash( $identity );

			if ( $candidate !== $this->key && $this->has_credentials( $candidate ) ) {
				$source = $candidate;
				break;
			}
		}

		// A genuinely fresh install. Marked done so the sweep below is
		// not repeated for the life of the plugin.
		if ( null === $source ) {
			$this->mark_done();

			return false;
		}

		$this->move_options( $source );
		$this->clear_stale_lifecycle_state();
		$this->move_queued_events( $source );
		$this->mark_done();

		return true;
	}

	private function move_options( $source ) {
		foreach ( $this->option_names_for( $source ) as $old_name ) {
			// Only the trailing key is replaced. str_replace() would also
			// rewrite the key if it somehow appeared earlier in the name.
			$new_name = substr( $old_name, 0, -strlen( $source ) ) . $this->key;

			$value = get_option( $old_name, null );

			if ( null !== $value ) {
				update_option( $new_name, $value, self::autoloads( $new_name ) );
			}

			if ( function_exists( 'delete_option' ) ) {
				delete_option( $old_name );
			}
		}
	}

	private function clear_stale_lifecycle_state() {
		if ( ! function_exists( 'delete_option' ) ) {
			return;
		}

		foreach ( self::TRANSIENT_LIFECYCLE_SUFFIXES as $suffix ) {
			delete_option( self::OPTION_PREFIX . $suffix . '_' . $this->key );
		}
	}

	/**
	 * The queued-but-unsent telemetry backlog. Losing it would not break
	 * the site, but it is legitimate data the server has never seen, and
	 * re-pointing it costs one UPDATE.
	 */
	private function move_queued_events( $source ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return;
		}

		$table = TableEventQueue::table_name();

		if ( '' === $table ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET product_key = %s WHERE product_key = %s",
				$this->key,
				$source
			)
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function option_names_for( $key ) {
		if ( null !== $this->lister ) {
			return (array) call_user_func( $this->lister, $key );
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return array();
		}

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%' . $wpdb->esc_like( '_' . $key )
			)
		);

		return is_array( $names ) ? $names : array();
	}

	private function has_credentials( $key ) {
		$stored = get_option( WpOptionsCredentialStore::OPTION_PREFIX . $key, array() );

		return is_array( $stored )
			&& ! empty( $stored['installation_id'] )
			&& ! empty( $stored['installation_secret'] );
	}

	/**
	 * Autoload follows read frequency, the rule this package already
	 * states in WpOptionsLicenseStore's class doc: consent is read by
	 * every track() call and the licence by every is_valid(), so both
	 * ride the blob WordPress fetches anyway. Everything else is read
	 * only when the SDK is actually doing something.
	 */
	private static function autoloads( $option_name ) {
		return 0 === strpos( $option_name, self::OPTION_PREFIX . 'consent_' )
			|| 0 === strpos( $option_name, self::OPTION_PREFIX . 'license_' );
	}

	private function marker_name() {
		return self::MARKER_PREFIX . $this->key;
	}

	private function mark_done() {
		update_option( $this->marker_name(), '1', false );
	}
}
