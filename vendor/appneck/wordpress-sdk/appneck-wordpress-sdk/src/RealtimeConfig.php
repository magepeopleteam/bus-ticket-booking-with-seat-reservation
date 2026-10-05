<?php

namespace Appneck\Sdk;

/**
 * The client-side half of docs/architecture/13-realtime-config-delivery.md:
 * tracking whether this site's cached announcements are known-stale, and
 * a circuit breaker shared by every network call this feature makes
 * (the config poll, the page-load announcements refresh, and the live
 * survey-questions fetch).
 *
 * ## Why one shared circuit, not three
 *
 * All three calls hit the same backend for the same reason (Appneck is
 * unreachable, or rate-limiting), so three independent breakers would
 * all trip at once anyway — at the cost of three times the failed
 * attempts before any of them noticed. One shared breaker means the
 * FIRST feature to notice an outage protects the other two immediately.
 *
 * ## config_version is recorded, never fetched for
 *
 * note_version() is called from wherever the SDK already receives a
 * response carrying config_version (Telemetry, Lifecycle's register/
 * status calls, and the poll endpoint itself) — never the other way
 * around. This class makes no network call to learn a version; it only
 * reacts to one arriving for free on a request that was happening
 * anyway. Whether anything ELSE happens as a result (a page-load
 * refresh, an urgent poll) is entirely up to the caller.
 *
 * ## Wire protocol is frozen once deployed
 *
 * A response predating config_version simply never calls note_version(),
 * and a response from a server that no longer sends it (a field this
 * SDK version has stopped expecting) is exactly the same "no signal"
 * case — both fall back to whatever staleness state was already stored,
 * never to an error.
 */
final class RealtimeConfig {

	/** Consecutive failures before the breaker opens. */
	const FAILURE_THRESHOLD = 3;

	/** How long the breaker stays open after tripping on repeated failures. */
	const OPEN_SECONDS = 900; // 15 minutes

	/** @var string */
	private $key;

	public function __construct( $key ) {
		$this->key = (string) $key;
	}

	// -----------------------------------------------------------------
	// config_version / staleness
	// -----------------------------------------------------------------

	/**
	 * Record whatever config_version a response carried.
	 *
	 * @param mixed $version Whatever the response contained — validated
	 *                        here, never assumed to be a clean int. A
	 *                        response predating this field, or one from a
	 *                        server that stopped sending it, hands this
	 *                        null/absent — see the class doc on forward
	 *                        compatibility.
	 */
	public function note_version( $version ) {
		if ( ! is_int( $version ) && ! ( is_string( $version ) && ctype_digit( $version ) ) ) {
			return;
		}

		$version = (int) $version;
		$known   = $this->stored_version();

		// A first sighting has nothing to compare against, and whatever
		// is cached was fetched under the very state this version already
		// describes — same reasoning as the SDK's other version markers.
		if ( null !== $known && $known !== $version ) {
			$this->mark_stale();
		}

		$this->update_option( 'version', $version );
	}

	/** @return int|null */
	public function stored_version() {
		$value = $this->get_option( 'version' );

		return null === $value ? null : (int) $value;
	}

	public function is_stale() {
		return (bool) $this->get_option( 'stale', false );
	}

	public function mark_stale() {
		$this->update_option( 'stale', true );
	}

	public function clear_stale() {
		$this->update_option( 'stale', false );
	}

	// -----------------------------------------------------------------
	// Circuit breaker
	// -----------------------------------------------------------------

	/**
	 * Whether the breaker is currently open — i.e. every caller of this
	 * class must skip the network entirely and serve cache.
	 */
	public function is_open() {
		$until = (int) $this->get_option( 'circuit_open_until', 0 );

		return $until > time();
	}

	/**
	 * A successful call (2xx, or a 304 — "unchanged" is still a working
	 * connection) closes the breaker and resets the failure count.
	 */
	public function record_success() {
		$this->update_option( 'circuit_failures', 0 );
		$this->update_option( 'circuit_open_until', 0 );
	}

	/**
	 * A failed call. After FAILURE_THRESHOLD consecutive failures the
	 * breaker opens for OPEN_SECONDS.
	 *
	 * @param int|null $retry_after When the failure was a 429, the
	 *                              server's own Retry-After. Honoured
	 *                              directly and unconditionally — a rate
	 *                              limit is not "try three times and
	 *                              then back off", it is "the server just
	 *                              told you exactly how long to wait, so
	 *                              wait that long" — never retried into.
	 */
	public function record_failure( $retry_after = null ) {
		if ( null !== $retry_after ) {
			$this->update_option( 'circuit_open_until', time() + max( 1, (int) $retry_after ) );

			return;
		}

		$failures = (int) $this->get_option( 'circuit_failures', 0 ) + 1;
		$this->update_option( 'circuit_failures', $failures );

		if ( $failures >= self::FAILURE_THRESHOLD ) {
			$this->update_option( 'circuit_open_until', time() + self::OPEN_SECONDS );
		}
	}

	// -----------------------------------------------------------------
	// Single-flight lock
	// -----------------------------------------------------------------

	/**
	 * Attempt to become the one caller allowed to hit the network right
	 * now. Returns true exactly once per $ttl_seconds window across
	 * however many concurrent callers ask — every other caller in that
	 * window gets false and must serve cache instead of waiting, which is
	 * what turns ten open tabs into one upstream call rather than ten
	 * blocked ones.
	 *
	 * Not perfectly atomic — WordPress's transient API has no
	 * check-and-set primitive, and two requests landing in the same
	 * instant could both read "unlocked" before either writes the lock.
	 * That is an acceptable, narrow race for a rate-limiting mechanism
	 * (worst case: two upstream calls instead of one, never zero and
	 * never a stampede), and it is the same reasoning this project has
	 * already accepted for cache stampede protection elsewhere — see
	 * ProductConfigCache on the API side for the equivalent, less
	 * narrow, blocking version of the same idea.
	 */
	public function acquire_lock( $name, $ttl_seconds ) {
		$lock_key = $this->option_name( 'lock_' . $name );

		if ( function_exists( 'get_transient' ) && false !== get_transient( $lock_key ) ) {
			return false;
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( $lock_key, time(), max( 1, (int) $ttl_seconds ) );
		}

		return true;
	}

	public function release_lock( $name ) {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->option_name( 'lock_' . $name ) );
		}
	}

	// -----------------------------------------------------------------
	// The 60-second urgent poll (Layer 3)
	// -----------------------------------------------------------------

	/**
	 * GET /sdk/v1/config — the cheapest call this SDK makes, and the one
	 * a background poll hits every 60 seconds while an admin tab is open.
	 *
	 * On an open circuit, this makes no request at all and returns
	 * whatever was last known — Rule 6's "fail open and silent" applies
	 * to the poll itself, not only to the features it feeds.
	 *
	 * @return array{config_version: int|null, has_urgent: bool}
	 */
	public function poll( Client $client ) {
		if ( $this->is_open() ) {
			return $this->cached_poll();
		}

		$etag = $this->get_option( 'poll_etag' );

		$headers = array();

		if ( is_string( $etag ) && '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		$response = $client->get( '/sdk/v1/config', array(), $headers );

		// 304: the server confirmed nothing changed. A real, successful
		// answer — closes the breaker — just not one with a body to read.
		if ( 304 === $response->status() ) {
			$this->record_success();

			return $this->cached_poll();
		}

		if ( ! $response->ok() ) {
			$this->record_failure(
				$response->is_rate_limited() ? $response->rate_limit()->retry_after() : null
			);

			return $this->cached_poll();
		}

		$this->record_success();

		$version    = $response->get( 'config_version' );
		$has_urgent = (bool) $response->get( 'has_urgent', false );

		$this->note_version( $version );

		$new_etag = $response->headers();
		$new_etag = isset( $new_etag['etag'] ) ? $new_etag['etag'] : null;

		if ( is_string( $new_etag ) && '' !== $new_etag ) {
			$this->update_option( 'poll_etag', $new_etag );
		}

		$result = array(
			'config_version' => is_int( $version ) || ( is_string( $version ) && ctype_digit( $version ) ) ? (int) $version : $this->stored_version(),
			'has_urgent'      => $has_urgent,
		);

		$this->update_option( 'poll_cache', $result );

		return $result;
	}

	/** @return array{config_version: int|null, has_urgent: bool} */
	public function cached_poll() {
		$cached = $this->get_option( 'poll_cache' );

		if ( is_array( $cached ) && array_key_exists( 'config_version', $cached ) && array_key_exists( 'has_urgent', $cached ) ) {
			return $cached;
		}

		return array(
			'config_version' => $this->stored_version(),
			'has_urgent'      => false,
		);
	}

	// -----------------------------------------------------------------
	// Storage
	// -----------------------------------------------------------------

	private function option_name( $suffix ) {
		return 'appneck_sdk_config_' . $suffix . '_' . $this->key;
	}

	/** @return mixed */
	private function get_option( $suffix, $default = null ) {
		if ( ! function_exists( 'get_option' ) ) {
			return $default;
		}

		return get_option( $this->option_name( $suffix ), $default );
	}

	/** @param mixed $value */
	private function update_option( $suffix, $value ) {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		// autoload 'no': read only on an admin page load and inside the
		// async refresh/poll endpoints, never on the front end.
		update_option( $this->option_name( $suffix ), $value, false );
	}

	/** Called at uninstall — every option this class ever wrote. */
	public function forget() {
		if ( ! function_exists( 'delete_option' ) ) {
			return;
		}

		foreach ( array( 'version', 'stale', 'circuit_failures', 'circuit_open_until', 'poll_etag', 'poll_cache' ) as $suffix ) {
			delete_option( $this->option_name( $suffix ) );
		}
	}
}
