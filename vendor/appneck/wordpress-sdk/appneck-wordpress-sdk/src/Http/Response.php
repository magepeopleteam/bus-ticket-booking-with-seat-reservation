<?php

namespace Appneck\Sdk\Http;

/**
 * Every outcome of an SDK call, success or failure, arrives here.
 *
 * The SDK has no public method that throws and no public method that
 * returns a bare value — everything returns one of these. That is the
 * central safety guarantee of this library: it runs inside someone
 * else's production WordPress site, where an uncaught exception or a
 * PHP error is a white screen on a site whose owner has never heard of
 * Appneck. A telemetry heartbeat failing is not worth anyone's site.
 *
 * So there are three distinct failure shapes, all non-throwing:
 *
 *   - transport failure  — DNS, timeout, TLS, WP_Error. status() is 0,
 *                          transport_error() explains.
 *   - HTTP failure       — a real response with a non-2xx status. 401,
 *                          403, 422, 429, 5xx all land here with the
 *                          server's own message available.
 *   - malformed response — 2xx whose body is not the JSON we expect.
 *                          Treated as a failure rather than silently
 *                          handing the caller an empty array.
 *
 * ok() is true only for a 2xx whose body parsed. Callers that just want
 * "did it work" need nothing else.
 */
final class Response {

	/** @var int 0 when no HTTP response was received at all. */
	private $status;

	/** @var array<string, string> Lower-cased header names. */
	private $headers;

	/** @var string */
	private $raw_body;

	/** @var array<mixed>|null Decoded JSON, null if absent/unparseable. */
	private $data;

	/** @var string|null */
	private $transport_error;

	/** @var string|null */
	private $decode_error;

	/** @var RateLimit */
	private $rate_limit;

	/**
	 * @param array<string, string> $headers
	 */
	private function __construct( $status, array $headers, $raw_body, $transport_error = null ) {
		$this->status          = (int) $status;
		$this->headers         = $headers;
		$this->raw_body        = (string) $raw_body;
		$this->transport_error = $transport_error;
		$this->rate_limit      = RateLimit::from_headers( $headers );

		$this->decode();
	}

	/**
	 * @param array<string, string> $headers
	 */
	public static function from_http( $status, array $headers, $raw_body ) {
		return new self( $status, $headers, $raw_body );
	}

	/**
	 * A request that never produced an HTTP response.
	 */
	public static function from_transport_error( $message ) {
		return new self( 0, array(), '', (string) $message );
	}

	/**
	 * A Throwable caught anywhere in the SDK. Deliberately reported as a
	 * transport error rather than rethrown — see the class doc. The
	 * message is included because the caller may want to log it; the
	 * stack trace is not, since it can contain argument values and this
	 * object may be surfaced in a plugin's own admin UI.
	 */
	public static function from_throwable( $throwable ) {
		return self::from_transport_error(
			get_class( $throwable ) . ': ' . $throwable->getMessage()
		);
	}

	/** @return bool 2xx with a parseable body. */
	public function ok() {
		return $this->status >= 200 && $this->status < 300 && null === $this->decode_error;
	}

	/** @return int 0 means no response was received. */
	public function status() {
		return $this->status;
	}

	/** @return array<mixed> Empty array rather than null, so callers can index safely. */
	public function data() {
		return is_array( $this->data ) ? $this->data : array();
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$data = $this->data();

		return array_key_exists( $key, $data ) ? $data[ $key ] : $default;
	}

	public function raw_body() {
		return $this->raw_body;
	}

	/** @return array<string, string> */
	public function headers() {
		return $this->headers;
	}

	public function rate_limit() {
		return $this->rate_limit;
	}

	/** @return string|null Non-null when no HTTP response was received. */
	public function transport_error() {
		return $this->transport_error;
	}

	/** @return bool */
	public function is_transport_error() {
		return null !== $this->transport_error;
	}

	/** @return bool The credentials were rejected (journal §9.2a). */
	public function is_unauthorized() {
		return 401 === $this->status;
	}

	/** @return bool Authenticated, but not allowed — suspended org, inactive installation, consent withheld. */
	public function is_forbidden() {
		return 403 === $this->status;
	}

	/** @return bool The server does not know this installation; re-enrolment is required. */
	public function is_unknown_installation() {
		return 404 === $this->status;
	}

	/** @return bool */
	public function is_rate_limited() {
		return 429 === $this->status;
	}

	/** @return bool */
	public function is_server_error() {
		return $this->status >= 500;
	}

	/**
	 * Whether retrying this exact request could plausibly succeed.
	 * Wired now, consumed by S4.3's backoff. A 4xx other than 429 is the
	 * caller's fault and will fail identically forever, so retrying it
	 * is just load.
	 *
	 * @return bool
	 */
	public function is_retryable() {
		return $this->is_transport_error() || $this->is_rate_limited() || $this->is_server_error();
	}

	/**
	 * The best available human-readable explanation of a failure, or null
	 * when ok(). Prefers the server's own `message` field, which every
	 * SDK-zone error response carries.
	 *
	 * @return string|null
	 */
	public function error_message() {
		if ( $this->ok() ) {
			return null;
		}

		if ( null !== $this->transport_error ) {
			return $this->transport_error;
		}

		$data = $this->data();

		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			return $data['message'];
		}

		if ( null !== $this->decode_error ) {
			return $this->decode_error;
		}

		return 'HTTP ' . $this->status;
	}

	/**
	 * Per-field validation errors from a 422, if the server sent any.
	 *
	 * @return array<mixed>
	 */
	public function validation_errors() {
		$data = $this->data();

		return isset( $data['errors'] ) && is_array( $data['errors'] ) ? $data['errors'] : array();
	}

	private function decode() {
		if ( null !== $this->transport_error ) {
			return;
		}

		// A body-less success (204, or a HEAD) is not malformed.
		if ( '' === trim( $this->raw_body ) ) {
			$this->data = array();

			return;
		}

		$decoded = json_decode( $this->raw_body, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			// Common in the wild: a caching plugin, a WAF, or a hosting
			// error page replacing the response body with HTML. Reported
			// rather than silently treated as an empty result, because
			// "the server said nothing" and "something ate the response"
			// need different responses from the caller.
			$this->decode_error = 'Malformed response body (' . json_last_error_msg() . ')';

			return;
		}

		$this->data = is_array( $decoded ) ? $decoded : array( 'value' => $decoded );
	}
}
