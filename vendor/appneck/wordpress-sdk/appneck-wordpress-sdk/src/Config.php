<?php

namespace Appneck\Sdk;

/**
 * What a plugin author configures: which product this is, and where the
 * API lives.
 *
 * The product secret here is the BOOTSTRAP secret — the one shipped
 * inside the distributed plugin, used only to sign
 * POST /sdk/v1/installations. Journal §9.2a is explicit that this value
 * is public in practice: every customer running the plugin has a
 * readable copy of it, so it authenticates "this is the product", never
 * "this is a particular site". Everything after enrolment is signed with
 * the per-installation secret from the CredentialStore instead.
 *
 * Which is why this class holds no expectation of secrecy and the SDK
 * never logs, transmits, or displays the value beyond signing with it.
 */
final class Config {

	/** @var string */
	private $api_key;

	/** @var string */
	private $product_secret;

	/** @var string */
	private $base_url;

	/** @var string */
	private $storage_identity;

	/**
	 * @param string      $api_key          The product's public API key (pk_...).
	 * @param string      $product_secret   The bootstrap signing secret (sk_...).
	 * @param string      $base_url         API root, e.g. https://appneck.com.
	 * @param string|null $storage_identity What local wp_options/queue storage is
	 *                                      namespaced by. Deliberately NOT the
	 *                                      api_key: a product's key can rotate
	 *                                      (`product_api_keys` supports
	 *                                      active/deprecated/revoked — journal
	 *                                      §11.4), and every class in this
	 *                                      package that stores anything locally
	 *                                      used to hash the *current* api_key to
	 *                                      build its option name. A plugin
	 *                                      release that ships a rotated key made
	 *                                      every already-registered site compute
	 *                                      a brand-new, empty namespace, conclude
	 *                                      it had never registered, and attempt a
	 *                                      fresh enrolment that collided with its
	 *                                      own still-active installation row —
	 *                                      permanently, since Lifecycle gives up
	 *                                      after MAX_ATTEMPTS with no self-heal
	 *                                      (journal §35). Callers pass something
	 *                                      that outlives key rotation — the
	 *                                      plugin's own file path — instead.
	 *                                      Falls back to $api_key when omitted,
	 *                                      so existing direct callers (tests,
	 *                                      the documented Sdk::client() example)
	 *                                      keep working exactly as before.
	 */
	public function __construct( $api_key, $product_secret, $base_url, $storage_identity = null ) {
		$this->api_key        = (string) $api_key;
		$this->product_secret = (string) $product_secret;
		// Trailing slash stripped once, here, so path joining is
		// unambiguous everywhere else and a configured
		// "https://host/" can never produce "https://host//sdk/v1/...".
		$this->base_url = rtrim( (string) $base_url, '/' );

		$this->storage_identity = ( null !== $storage_identity && '' !== (string) $storage_identity )
			? (string) $storage_identity
			: $this->api_key;
	}

	public function api_key() {
		return $this->api_key;
	}

	/** @return string See the constructor's $storage_identity doc. */
	public function storage_identity() {
		return $this->storage_identity;
	}

	public function product_secret() {
		return $this->product_secret;
	}

	public function base_url() {
		return $this->base_url;
	}

	/**
	 * Absolute URL for a signed path. The path passed here is the same
	 * string that goes into the signature base string, so the two cannot
	 * drift — see Client::request.
	 */
	public function url_for( $path ) {
		return $this->base_url . '/' . ltrim( (string) $path, '/' );
	}

	/**
	 * Whether this config could possibly work. Checked before any
	 * request so a plugin misconfigured at build time gets a clear
	 * failure instead of a 401 it cannot diagnose.
	 *
	 * @return string|null Reason it is unusable, or null if fine.
	 */
	public function validation_error() {
		if ( '' === $this->api_key ) {
			return 'No API key configured.';
		}

		if ( '' === $this->product_secret ) {
			return 'No product secret configured.';
		}

		if ( '' === $this->base_url || ! preg_match( '#^https?://#i', $this->base_url ) ) {
			return 'The API base URL must be an absolute http(s) URL.';
		}

		return null;
	}
}
