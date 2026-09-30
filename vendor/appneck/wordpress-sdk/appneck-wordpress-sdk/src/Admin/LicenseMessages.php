<?php

namespace Appneck\Sdk\Admin;

/**
 * The one place the server's reason vocabulary (journal §23.5) is
 * translated into a sentence a site owner can read.
 *
 * Extracted out of Admin\LicenseForm (Phase 5) rather than duplicated
 * into Admin\LicensePage (Phase 8): both classes need the identical
 * mapping, and two copies is exactly how one gets a new reason added and
 * the other doesn't. LicenseForm's own behaviour is unchanged — this is
 * the same array it always had, moved so a second caller can reach it,
 * verified byte-for-byte against LicenseFormTest's own assertions.
 *
 * The list is deliberately open-ended, matching LicenseForm's original
 * reasoning: the server's reason vocabulary can grow without a release of
 * this SDK, so an unrecognised reason renders readably (`ucfirst` +
 * underscores replaced) rather than being dropped or printed as a raw
 * machine token like `not_activated_on_domain`.
 */
final class LicenseMessages {

	/**
	 * Read from `App\Actions\Licensing\ValidateLicenseFromSdk` and
	 * `ActivateLicenseFromSdk` (journal §23.5) — the exact strings the
	 * server can return in a rejection's `reason` field, plus the two
	 * statuses (`refunded`, `revoked`) that reach the client only via
	 * that same "the reason IS the status string" rule.
	 *
	 * @return array<string, string>
	 */
	public static function map() {
		return array(
			'license_not_found'        => 'This license key was not recognised.',
			'expired'                  => 'This license has expired.',
			'not_activated_on_domain'  => 'This license is not activated on this domain. If the site was moved or cloned, activate it here to use the license on this domain.',
			'activation_limit_reached' => 'This license is already in use on the maximum number of sites.',
			'suspended'                => 'This license is suspended.',
			'cancelled'                => 'This license has been cancelled.',
			'refunded'                 => 'This license was refunded.',
			'revoked'                  => 'This license has been revoked.',
		);
	}

	/**
	 * @param string|null $reason
	 * @param string      $fallback Used only when $reason is empty.
	 * @return string
	 */
	public static function for_reason( $reason, $fallback = 'Not active on this site.' ) {
		$reason = (string) $reason;
		$known  = self::map();

		if ( isset( $known[ $reason ] ) ) {
			return $known[ $reason ];
		}

		if ( '' !== $reason ) {
			return ucfirst( str_replace( '_', ' ', $reason ) ) . '.';
		}

		return $fallback;
	}
}
