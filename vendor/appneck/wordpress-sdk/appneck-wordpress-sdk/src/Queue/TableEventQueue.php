<?php

namespace Appneck\Sdk\Queue;

/**
 * The production queue: a small custom table, created with dbDelta.
 *
 * ## Why not wp_options
 *
 * The obvious implementation — one option holding an array of events —
 * is wrong here for three separate reasons, and the first is the one
 * that bites hardest in the real world:
 *
 *  1. **Option bloat.** A growing serialized array in wp_options is a
 *     well-known WordPress performance problem. Even set to
 *     autoload = no, every push means reading, unserializing, appending,
 *     re-serializing and writing back the WHOLE list — O(n) work and an
 *     O(n) row rewrite on every single track() call, on a site whose
 *     page load we are a guest in.
 *  2. **Concurrency.** Two requests calling track() at the same moment
 *     both read the array, both append, and the second write silently
 *     discards the first event. An INSERT has no such race.
 *  3. **Partial removal.** After a flush the server accepts some events
 *     and rejects others; clearing exactly those means rewriting the
 *     whole option again, and any track() that landed in between is
 *     lost. Deleting by primary key does not have that problem.
 *
 * A table gives O(1) appends, ordered reads with a LIMIT, and deletes by
 * id — which is precisely the access pattern, and none of it is
 * expressible efficiently in an option.
 *
 * ## One table, all products
 *
 * Two plugins from the same vendor may each embed the SDK. They share
 * this one table and are separated by a `product_key` column rather than
 * getting a table each — WordPress sites already carry enough tables,
 * and the query is indexed on (product_key, id) so the separation costs
 * nothing. That column is named `product_key` for history, but is a hash
 * of a stable per-plugin identity (Config::storage_identity()), never of
 * the product's API key directly — the key can rotate (journal §11.4),
 * and hashing it directly used to mean a plugin update shipping a
 * rotated key made a site's own already-queued events unreachable to
 * itself (journal §35).
 *
 * ## Degrading rather than fataling
 *
 * Some managed hosts revoke CREATE privileges. If the table cannot be
 * created, every method here returns a safe empty/false result and the
 * SDK simply never sends telemetry. That is the correct outcome: losing
 * analytics is a bad day for us, and a fatal error is a bad day for the
 * site owner.
 */
final class TableEventQueue implements EventQueue {

	/** Bumped when the schema changes, so dbDelta runs again. */
	const SCHEMA_VERSION = 1;

	const TABLE_SUFFIX = 'appneck_sdk_events';

	/**
	 * The local cap. Deliberately larger than the backend's
	 * telemetry_batch_max_events (100) because the two solve different
	 * problems: that is how much fits in ONE request, this is how much
	 * may accumulate BETWEEN requests.
	 *
	 * 1000 is ten full batches. At the default 15-minute flush that is
	 * over two hours of complete backlog even if every tick sends a full
	 * batch, and far longer in practice since a normal site produces
	 * nowhere near 100 events per quarter hour. Past that we are no
	 * longer buffering a blip, we are hoarding data from a site that
	 * cannot reach us, in a database we do not own.
	 */
	const MAX_EVENTS = 1000;

	/** @var string */
	private $product_key;

	/** @var bool|null */
	private $available = null;

	/** @param string $storage_identity See Config::storage_identity(). */
	public function __construct( $storage_identity ) {
		$this->product_key = substr( hash( 'sha256', (string) $storage_identity ), 0, 32 );
	}

	public static function table_name() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return '';
		}

		// $wpdb->prefix, not base_prefix: on multisite each site gets its
		// own table, matching the per-site installation model (journal
		// §9.5) — a subsite's events belong to that subsite's installation.
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Creates or upgrades the table. Called from activation, and cheap
	 * enough to call again on load when the stored schema version is old.
	 *
	 * @return bool
	 */
	public static function install() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! function_exists( 'dbDelta' ) ) {
			if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			if ( ! function_exists( 'dbDelta' ) ) {
				return false;
			}
		}

		$table   = self::table_name();
		$collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';

		// dbDelta is famously fussy: two spaces after PRIMARY KEY, one
		// field per line, KEY not INDEX, and no backticks around the
		// table name. Formatted to its rules deliberately.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_key varchar(32) NOT NULL,
			type varchar(32) NOT NULL,
			payload longtext NOT NULL,
			occurred_at varchar(32) NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_key,id)
		) {$collate};";

		dbDelta( $sql );

		if ( function_exists( 'update_option' ) ) {
			update_option( 'appneck_sdk_events_schema', self::SCHEMA_VERSION, false );
		}

		return true;
	}

	/**
	 * Whether the table is actually usable. Cached per request — this is
	 * a SHOW TABLES query, and track() may be called many times.
	 */
	public function is_available() {
		if ( null !== $this->available ) {
			return $this->available;
		}

		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			$this->available = false;

			return false;
		}

		$table = self::table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		$this->available = ( $found === $table );

		return $this->available;
	}

	public function push( $type, array $payload, $occurred_at = null ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		global $wpdb;

		$encoded = wp_json_encode_compat( $payload );

		if ( false === $encoded ) {
			return false;
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'product_key' => $this->product_key,
				'type'        => (string) $type,
				'payload'     => $encoded,
				'occurred_at' => null !== $occurred_at ? (string) $occurred_at : gmdate( 'c' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$this->enforce_cap();

		return true;
	}

	/**
	 * Drops the OLDEST events when over the cap, not the newest.
	 *
	 * The alternative — refusing new events once full — keeps a snapshot
	 * of whatever was happening when the outage started and then goes
	 * blind, so the site reports stale history and nothing about now.
	 * Dropping oldest means a site recovering from a long outage sends
	 * its most recent activity, which is the part anyone looking at a
	 * dashboard actually wants. Telemetry is explicitly best-effort
	 * (journal §9.3's partial-success reasoning applies here too): losing
	 * the oldest events beats losing the current ones.
	 */
	private function enforce_cap() {
		global $wpdb;

		$table = self::table_name();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_key = %s", $this->product_key )
		);

		if ( $count <= self::MAX_EVENTS ) {
			return;
		}

		$excess = $count - self::MAX_EVENTS;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE product_key = %s ORDER BY id ASC LIMIT %d",
				$this->product_key,
				$excess
			)
		);
	}

	public function take( $limit ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		global $wpdb;

		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, payload, occurred_at FROM {$table} WHERE product_key = %s ORDER BY id ASC LIMIT %d",
				$this->product_key,
				(int) $limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$events = array();

		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );

			$events[] = array(
				'id'          => (int) $row['id'],
				'type'        => $row['type'],
				// A row whose JSON no longer decodes would otherwise be
				// sent as null and rejected forever; an empty payload is
				// at least valid and gets cleared on the next flush.
				'payload'     => is_array( $payload ) ? $payload : array(),
				'occurred_at' => $row['occurred_at'],
			);
		}

		return $events;
	}

	public function forget( array $ids ) {
		if ( empty( $ids ) || ! $this->is_available() ) {
			return 0;
		}

		global $wpdb;

		$ids = array_map( 'intval', $ids );
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$table = self::table_name();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE product_key = %s AND id IN ({$in})",
				array_merge( array( $this->product_key ), $ids )
			)
		);
	}

	public function count() {
		if ( ! $this->is_available() ) {
			return 0;
		}

		global $wpdb;

		$table = self::table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_key = %s", $this->product_key )
		);
	}

	public function purge() {
		if ( ! $this->is_available() ) {
			return;
		}

		global $wpdb;

		$table = self::table_name();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE product_key = %s", $this->product_key ) );
	}
}

if ( ! function_exists( 'Appneck\\Sdk\\Queue\\wp_json_encode_compat' ) ) {
	/**
	 * wp_json_encode where available (it handles invalid UTF-8 the way
	 * WordPress expects), plain json_encode otherwise. Namespaced so it
	 * cannot collide with anything global.
	 *
	 * @param array<string, mixed> $value
	 * @return string|false
	 */
	function wp_json_encode_compat( array $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value );

			return is_string( $encoded ) ? $encoded : false;
		}

		return json_encode( $value );
	}
}
