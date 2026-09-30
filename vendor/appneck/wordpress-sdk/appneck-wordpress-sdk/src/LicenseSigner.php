<?php

namespace Appneck\Sdk;

/**
 * The licensing request signing contract, journal §23.1.
 *
 * A SECOND, deliberately separate scheme from Signer, which implements
 * journal §9.2a's telemetry contract. They are not interchangeable and
 * neither may be "unified" into the other:
 *
 *     Signer        METHOD \n /path \n installation-id \n timestamp \n body
 *                   signed with the PER-INSTALLATION secret.
 *     LicenseSigner body . timestamp
 *                   signed with the PRODUCT secret.
 *
 * The licensing base string is shorter because the server's own base
 * string is shorter, and that is the whole reason this class exists.
 * App\Http\Middleware\VerifyLicensingSdkSignature computes exactly:
 *
 *     hash_hmac( 'sha256', $request->getContent() . $timestamp, $secret )
 *
 * No method, no path, no installation id, and no newlines anywhere —
 * licensing has no installation to bind against (journal §23.1: a
 * customer who declines analytics consent never gets one, and their
 * license must still work), and every route it guards shares one fixed
 * prefix with no sibling a captured signature could be repointed at.
 *
 * Field by field, with the mistake each one invites:
 *
 *   body       The EXACT bytes transmitted. Not the array, not a
 *              re-encode of a decoded copy — json_encode is not
 *              canonical, so a decode/encode round trip can legally
 *              change key order and escaping, and the signature with it.
 *   timestamp  Unix seconds as a STRING, identical to the value sent in
 *              X-Timestamp. Reading time() twice — once to sign, once to
 *              send — is a real intermittent bug: it works until the two
 *              calls land either side of a second boundary.
 *
 * Concatenated with NO separator, exactly as the server does it. A
 * separator that the server does not also add makes every request 401
 * with no clue as to why, so there is deliberately nothing here to get
 * creative with.
 *
 * Pure: no WordPress, no I/O, no state. LicenseSignerTest pins the
 * output against a hardcoded fixture vector so a future refactor cannot
 * silently change the contract — this code ships inside customers' sites
 * and cannot be recalled, so the regression lock matters more than the
 * eleven lines it protects.
 */
final class LicenseSigner {

	const ALGORITHM = 'sha256';

	/**
	 * The base string, public for the same reason Signer's is: it is
	 * worth being able to assert on directly and worth being able to log
	 * when debugging a 401, and it contains no secret.
	 *
	 * @param string $body      Raw request body bytes, exactly as sent.
	 * @param string $timestamp Unix seconds, as sent in X-Timestamp.
	 * @return string
	 */
	public static function base_string( $body, $timestamp ) {
		return (string) $body . (string) $timestamp;
	}

	/**
	 * The hex signature for X-Signature.
	 *
	 * hash_hmac returns lowercase hex, which is what the server compares
	 * against with hash_equals — so no case normalisation is needed or
	 * wanted here.
	 *
	 * @param string $body
	 * @param string $timestamp
	 * @param string $secret The product secret (Config::product_secret).
	 * @return string
	 */
	public static function sign( $body, $timestamp, $secret ) {
		return hash_hmac(
			self::ALGORITHM,
			self::base_string( $body, $timestamp ),
			(string) $secret
		);
	}
}
