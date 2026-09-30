<?php

namespace Appneck\Sdk\Storage;

/**
 * The production store: one wp_options row per product, autoloaded.
 *
 * ## autoload IS 'yes' here, unlike WpOptionsCredentialStore's
 *
 * That store is autoload 'no' on purpose — credentials are read only
 * when the SDK actually talks to the API, which is not on every page
 * load, and adding to the autoloaded blob is a cost every request on the
 * host's site pays.
 *
 * This row is the opposite case. License::is_valid() is the method
 * plugin authors call to gate a premium feature, on EVERY page load, and
 * its whole design is that the common path answers from this row with no
 * network call. With autoload 'no' that becomes an extra database query
 * on every request — strictly worse than carrying a few hundred bytes in
 * the blob WordPress already fetches in one query regardless. Consent's
 * option makes the same trade for the same reason (it is read by every
 * track() call), so this is the established rule in this package, not a
 * new judgement: autoload follows read frequency.
 *
 * The stored value is small and bounded by construction — a key, a
 * handful of scalars from the last result, and four integers of backoff
 * state. Nothing here grows with the site's age or traffic.
 *
 * The option name is namespaced by a hash of a stable per-plugin identity
 * (Config::storage_identity(), not the product's API key — journal §35:
 * the key can rotate, and hashing it directly used to make an
 * already-registered site's own storage unreachable to itself the moment
 * a plugin update shipped the new key), the same convention
 * WpOptionsCredentialStore and Consent already use, so two plugins from
 * one vendor on one site keep separate licenses. Hashed rather than
 * embedded raw because option names are not secret and surface in
 * exports and debug tooling.
 */
final class WpOptionsLicenseStore implements LicenseStore {

	const OPTION_PREFIX = 'appneck_sdk_license_';

	/** @var string */
	private $option_name;

	/** @param string $storage_identity See Config::storage_identity(). */
	public function __construct( $storage_identity ) {
		$this->option_name = self::OPTION_PREFIX . substr( hash( 'sha256', (string) $storage_identity ), 0, 32 );
	}

	public function option_name() {
		return $this->option_name;
	}

	/**
	 * Deliberately NOT cached on the instance, for the reason
	 * WpOptionsCredentialStore's own read() records at length: WordPress
	 * already caches options in memory for the duration of a request, so
	 * a second layer buys nothing, and a store that had already read "no
	 * license" would keep saying so for the rest of the request even
	 * after an activation on another instance saved one.
	 *
	 * @return array<string, mixed>
	 */
	public function read() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$stored = get_option( $this->option_name, array() );

		// A corrupted or hand-edited option must read as "no license"
		// rather than propagating a non-array into is_valid()'s path.
		return is_array( $stored ) ? $stored : array();
	}

	/** @param array<string, mixed> $state */
	public function write( array $state ) {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}

		// autoload true — see the class doc.
		$result = update_option( $this->option_name, $state, true );

		// update_option returns false when the value is unchanged, which
		// is not a failure: re-writing an identical result is a no-op,
		// not something the caller should treat as "storage broke".
		return true === $result || $state === $this->read();
	}

	public function forget() {
		if ( ! function_exists( 'delete_option' ) ) {
			return false;
		}

		delete_option( $this->option_name );

		return true;
	}
}
