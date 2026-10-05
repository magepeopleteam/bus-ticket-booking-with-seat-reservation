<?php

namespace Appneck\Sdk;

/**
 * The site owner's marketing-email opt-in — deliberately a SECOND,
 * independent decision from Consent (telemetry/privacy). A site owner may
 * accept telemetry and decline marketing, or the reverse: nothing here
 * reads Consent's own option, and Consent never reads this one directly
 * either. The only coupling is deliberate and one-directional — Consent
 * asks THIS class whether it has something pending to send, and rides it
 * along on the same `/sdk/v1/consent` HTTP call (see Consent::set_marketing_consent()/sync()) —
 * so answering both questions costs the site owner one interruption and
 * the server one request, not two of each.
 *
 * Storage mirrors Consent's own per-product key scheme, for the same
 * reason: several plugins on one site may each bundle this SDK, and a
 * shared option would mean one plugin's answer overwriting another's.
 *
 * `wording` is stored locally (and sent to the server — see
 * ConsentController on the API side) because doc 16 §5 requires the
 * EXACT text shown to be part of the consent record, not just a boolean.
 * `email` is only ever populated when opting in — declining never needs
 * an email captured at all, which is the same data-minimisation reasoning
 * Consent::apply_to_telemetry() already documents for a telemetry reject.
 */
final class MarketingConsent {

	const STATUS_OPTED_IN = 'opted_in';
	const STATUS_DECLINED = 'declined';

	/** @var Client */
	private $client;

	/** @var string Per-product suffix, same derivation as Consent::$key. */
	private $key;

	public function __construct( Client $client ) {
		$this->client = $client;
		$this->key    = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
	}

	public function has_decided() {
		$stored = $this->read();

		return isset( $stored['status'] );
	}

	public function is_opted_in() {
		$stored = $this->read();

		return isset( $stored['status'] ) && self::STATUS_OPTED_IN === $stored['status'];
	}

	/** @return string|null The exact wording shown when the decision was made. */
	public function wording() {
		$stored = $this->read();

		return isset( $stored['wording'] ) ? (string) $stored['wording'] : null;
	}

	/** @return string|null Only ever set when is_opted_in() is true. */
	public function email() {
		$stored = $this->read();

		return isset( $stored['email'] ) && '' !== $stored['email'] ? (string) $stored['email'] : null;
	}

	/** False once the server has this decision. */
	public function is_sync_pending() {
		$stored = $this->read();

		return $this->has_decided() && empty( $stored['synced'] );
	}

	/**
	 * Records the decision locally ONLY — no network call. Consent::sync()
	 * is what actually reaches the server, folding this in alongside
	 * whatever telemetry decision triggered that same request. Calling
	 * this alone leaves the decision durably stored (survives an
	 * unreachable API, same as Consent::decide()) but pending until the
	 * next sync().
	 *
	 * @param bool        $opted_in
	 * @param string      $wording  The exact text shown alongside the checkbox.
	 * @param string|null $email    Only meaningful when opting in; always stored as null when declining.
	 */
	public function decide( $opted_in, $wording, $email = null ) {
		$this->write(
			array(
				'status'  => $opted_in ? self::STATUS_OPTED_IN : self::STATUS_DECLINED,
				'wording' => (string) $wording,
				'email'   => $opted_in && null !== $email ? (string) $email : null,
				'synced'  => false,
			)
		);
	}

	/** Called by Consent::sync() once the server has accepted this decision. */
	public function mark_synced() {
		$stored = $this->read();

		if ( empty( $stored ) ) {
			return;
		}

		$stored['synced'] = true;
		$this->write( $stored );
	}

	/**
	 * Discards the local record on uninstall, same reasoning as
	 * Consent::forget(): this is the plugin's own data, and the server
	 * keeps the permanent history regardless.
	 */
	public function forget() {
		$this->delete_option();
	}

	/** @return array<string, mixed> */
	private function read() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$stored = get_option( $this->option_name(), array() );

		return is_array( $stored ) ? $stored : array();
	}

	/** @param array<string, mixed> $value */
	private function write( array $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $this->option_name(), $value, true );
		}
	}

	private function delete_option() {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $this->option_name() );
		}
	}

	private function option_name() {
		return 'appneck_sdk_marketing_consent_' . $this->key;
	}
}
