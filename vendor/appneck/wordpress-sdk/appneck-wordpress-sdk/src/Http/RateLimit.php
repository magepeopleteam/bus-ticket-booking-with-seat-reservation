<?php

namespace Appneck\Sdk\Http;

/**
 * The X-RateLimit-* / Retry-After headers the SDK zone returns.
 *
 * Plumbing only at this stage: S4.3 builds retry/backoff on top of it.
 * Parsed and exposed now because the headers are already being returned
 * by every SDK endpoint, and a client that throws them away cannot start
 * respecting them later without a second pass over every call site.
 *
 * The server sends X-RateLimit-Limit and X-RateLimit-Remaining on
 * successful responses, and additionally Retry-After on a 429.
 */
final class RateLimit {

	/** @var int|null */
	private $limit;

	/** @var int|null */
	private $remaining;

	/** @var int|null Seconds until the window resets. */
	private $retry_after;

	private function __construct( $limit, $remaining, $retry_after ) {
		$this->limit       = $limit;
		$this->remaining   = $remaining;
		$this->retry_after = $retry_after;
	}

	/**
	 * @param array<string, string> $headers Lower-cased header map.
	 */
	public static function from_headers( array $headers ) {
		return new self(
			self::int_or_null( $headers, 'x-ratelimit-limit' ),
			self::int_or_null( $headers, 'x-ratelimit-remaining' ),
			self::int_or_null( $headers, 'retry-after' )
		);
	}

	public static function none() {
		return new self( null, null, null );
	}

	/** @return int|null */
	public function limit() {
		return $this->limit;
	}

	/** @return int|null */
	public function remaining() {
		return $this->remaining;
	}

	/** @return int|null */
	public function retry_after() {
		return $this->retry_after;
	}

	/** @return bool Whether the server reported any rate-limit state. */
	public function is_known() {
		return null !== $this->limit || null !== $this->retry_after;
	}

	/**
	 * @param array<string, string> $headers
	 * @return int|null
	 */
	private static function int_or_null( array $headers, $name ) {
		if ( ! isset( $headers[ $name ] ) || '' === $headers[ $name ] ) {
			return null;
		}

		// Header values are strings from an external source; anything
		// non-numeric reads as "not reported" rather than casting to 0,
		// which backoff logic would misread as "no budget left".
		if ( ! is_numeric( $headers[ $name ] ) ) {
			return null;
		}

		return (int) $headers[ $name ];
	}
}
