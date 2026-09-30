<?php

namespace Appneck\Sdk\Http;

/**
 * Talks to the API through WordPress's own HTTP API, never curl or
 * Guzzle directly.
 *
 * That is not a stylistic preference. wp_remote_request is the only path
 * that respects the host site's actual network reality: WP_HTTP_BLOCK_EXTERNAL
 * and WP_ACCESSIBLE_HOSTS, proxy constants (WP_PROXY_HOST and friends),
 * the `http_request_args` and `pre_http_request` filters that security
 * plugins and hosts rely on, and the CA bundle WordPress ships. A plugin
 * that reaches for curl directly is the plugin that breaks on managed
 * hosts and inside corporate proxies, and that a site owner cannot block.
 *
 * Guzzle would additionally be a Composer dependency in a package that
 * must work as a bundled directory with no vendor/ — see composer.json.
 *
 * This class never throws: every failure becomes a Response.
 */
final class WpHttpTransport implements Transport {

	/** @var int */
	private $timeout;

	/**
	 * @param int $timeout Seconds. Short by default: this runs inside a
	 *                     page load on someone else's site, and a slow
	 *                     API must never become their slow site.
	 */
	public function __construct( $timeout = 10 ) {
		$this->timeout = (int) $timeout;
	}

	public function request( $method, $url, array $headers, $body = null ) {
		if ( ! function_exists( 'wp_remote_request' ) ) {
			return Response::from_transport_error(
				'WordPress HTTP API unavailable (wp_remote_request is not defined).'
			);
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => $this->timeout,
			// The API is machine-to-machine; a redirect would mean
			// something is wrong with the URL, and following one would
			// replay a signed body at an unsigned path.
			'redirection' => 0,
			// Never let a plugin's own cookies ride along on an API call.
			'cookies' => array(),
			'sslverify' => true,
		);

		// Only set a body for verbs that carry one — some WP transports
		// will happily attach an empty string to a GET, which changes the
		// request in ways the signature does not account for.
		if ( null !== $body && '' !== $body ) {
			$args['body'] = $body;
		}

		$result = wp_remote_request( $url, $args );

		if ( $this->is_wp_error( $result ) ) {
			return Response::from_transport_error( $this->wp_error_message( $result ) );
		}

		if ( ! is_array( $result ) ) {
			return Response::from_transport_error( 'Unexpected response from the WordPress HTTP API.' );
		}

		return Response::from_http(
			$this->status_from( $result ),
			$this->headers_from( $result ),
			$this->body_from( $result )
		);
	}

	private function is_wp_error( $result ) {
		return function_exists( 'is_wp_error' ) && is_wp_error( $result );
	}

	private function wp_error_message( $result ) {
		if ( is_object( $result ) && method_exists( $result, 'get_error_message' ) ) {
			$message = $result->get_error_message();

			if ( is_string( $message ) && '' !== $message ) {
				return $message;
			}
		}

		return 'The request failed before a response was received.';
	}

	/**
	 * Read through wp_remote_retrieve_* where available, but tolerate
	 * their absence: this class is exercised in the package's own tests
	 * with only the core HTTP functions polyfilled, and a helper being
	 * missing should not be the reason a real request reports failure.
	 */
	private function status_from( array $result ) {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $result );
		}

		return isset( $result['response']['code'] ) ? (int) $result['response']['code'] : 0;
	}

	private function body_from( array $result ) {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $result );
		}

		return isset( $result['body'] ) ? (string) $result['body'] : '';
	}

	/**
	 * @return array<string, string> Header names lower-cased.
	 */
	private function headers_from( array $result ) {
		$headers = isset( $result['headers'] ) ? $result['headers'] : array();

		// WordPress returns a Requests_Utility_CaseInsensitiveDictionary
		// (or the WpOrg\Requests equivalent), not a plain array.
		if ( is_object( $headers ) ) {
			if ( method_exists( $headers, 'getAll' ) ) {
				$headers = $headers->getAll();
			} elseif ( $headers instanceof \ArrayObject ) {
				$headers = $headers->getArrayCopy();
			} elseif ( $headers instanceof \Traversable ) {
				$headers = iterator_to_array( $headers );
			} else {
				$headers = (array) $headers;
			}
		}

		if ( ! is_array( $headers ) ) {
			return array();
		}

		$normalised = array();

		foreach ( $headers as $name => $value ) {
			// A repeated header comes back as an array; the SDK only
			// reads single-valued headers, so take the last one, which
			// is what a plain string cast would lose entirely.
			if ( is_array( $value ) ) {
				$value = end( $value );
			}

			if ( is_scalar( $value ) || null === $value ) {
				$normalised[ strtolower( (string) $name ) ] = (string) $value;
			}
		}

		return $normalised;
	}
}
