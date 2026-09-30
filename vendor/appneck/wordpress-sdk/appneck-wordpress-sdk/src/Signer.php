<?php

namespace Appneck\Sdk;

/**
 * The request signing contract, journal §9.2a.
 *
 * This class is the single most correctness-critical thing in the SDK: a
 * base string that differs from the server's by one byte produces a
 * completely different signature and every request 401s, with no clue as
 * to which of the five fields was wrong. It is deliberately pure — no
 * WordPress, no I/O, no state — so it is exactly reproducible and
 * testable, and so a signature can be computed identically in a unit
 * test, in WP-CLI, and inside a live request.
 *
 * The contract, verbatim:
 *
 *     X-Signature = HMAC_SHA256(base_string, secret)
 *
 *     base_string = METHOD \n /path \n installation-id \n timestamp \n raw-body
 *
 * Field by field, with the mistake each one invites:
 *
 *   METHOD           Uppercase verb: "POST", "GET". Lowercase fails.
 *   /path            Routed path, LEADING SLASH, NO query string, no
 *                    host, no scheme: "/sdk/v1/telemetry". Signing the
 *                    full URL is the obvious wrong guess.
 *   installation-id  The X-Installation-Id header value. Present even on
 *                    registration, where the installation does not exist
 *                    server-side yet — the client generates the id first
 *                    and signs with it from the very first request.
 *   timestamp        Unix seconds as a STRING, identical to the value
 *                    sent in X-Timestamp. Regenerating it between signing
 *                    and sending is a real, intermittent bug: it works
 *                    until the two land either side of a second boundary.
 *   raw-body         The EXACT bytes transmitted. Not the array, not a
 *                    re-encode of a decoded copy — json_encode is not
 *                    canonical, and PHP and the server will not agree on
 *                    key order or float formatting. Empty string for GET.
 *
 * Separator is a single "\n" (0x0A), never "\r\n". The four fields ahead
 * of the body are all constrained (verb, path, UUID, digits) and cannot
 * themselves contain a newline, so the concatenation is unambiguous even
 * though the body is free-form.
 */
final class Signer {

	public const ALGORITHM = 'sha256';

	/**
	 * Builds the canonical base string. Public because it is worth being
	 * able to assert on directly in a test, and worth being able to log
	 * when debugging a 401 — it contains no secret.
	 *
	 * @param string $method          HTTP verb, any case.
	 * @param string $path            Path with or without a leading slash.
	 * @param string $installation_id The X-Installation-Id value.
	 * @param string $timestamp       Unix seconds, as sent in X-Timestamp.
	 * @param string $body            Raw request body bytes, "" for GET.
	 */
	public static function base_string( $method, $path, $installation_id, $timestamp, $body ) {
		return implode(
			"\n",
			array(
				strtoupper( (string) $method ),
				'/' . ltrim( (string) $path, '/' ),
				(string) $installation_id,
				(string) $timestamp,
				(string) $body,
			)
		);
	}

	/**
	 * The hex signature for X-Signature.
	 *
	 * hash_hmac returns lowercase hex, which is what the server compares
	 * against with hash_equals — so no case normalisation is needed or
	 * wanted here.
	 */
	public static function sign( $method, $path, $installation_id, $timestamp, $body, $secret ) {
		return hash_hmac(
			self::ALGORITHM,
			self::base_string( $method, $path, $installation_id, $timestamp, $body ),
			(string) $secret
		);
	}
}
