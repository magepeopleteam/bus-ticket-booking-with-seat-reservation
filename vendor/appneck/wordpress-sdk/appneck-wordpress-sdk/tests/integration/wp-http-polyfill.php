<?php
/**
 * The WordPress HTTP functions WpHttpTransport calls, implemented with
 * curl, so the REAL transport class runs against a REAL server.
 *
 * The point of polyfilling rather than stubbing: the integration test
 * then exercises the actual production code path — header normalisation,
 * WP_Error handling, response shaping — instead of a test double that
 * could diverge from it. What it does not prove is WordPress's own
 * transport behaviour (proxies, WP_HTTP_BLOCK_EXTERNAL, the
 * pre_http_request filter); those are WordPress's contract, and the
 * transport's job is only to call into them correctly.
 *
 * Only used by tests/integration/. Never shipped in the SDK's own path.
 */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		private $message;

		public function __construct( $message ) {
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( $url, $args = array() ) {
		$handle = curl_init();

		$headers = array();

		foreach ( isset( $args['headers'] ) ? $args['headers'] : array() as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}

		curl_setopt_array(
			$handle,
			array(
				CURLOPT_URL            => $url,
				CURLOPT_CUSTOMREQUEST  => isset( $args['method'] ) ? $args['method'] : 'GET',
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_TIMEOUT        => isset( $args['timeout'] ) ? $args['timeout'] : 10,
				CURLOPT_FOLLOWLOCATION => false,
			)
		);

		if ( isset( $args['body'] ) ) {
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $args['body'] );
		}

		$raw = curl_exec( $handle );

		if ( false === $raw ) {
			$error = curl_error( $handle );
			curl_close( $handle );

			return new WP_Error( 'http_request_failed: ' . $error );
		}

		$status      = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		$header_size = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
		curl_close( $handle );

		$raw_headers = substr( $raw, 0, $header_size );
		$body        = substr( $raw, $header_size );

		$parsed = array();

		foreach ( preg_split( '/\r?\n/', trim( $raw_headers ) ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $line, 2 );
			$parsed[ strtolower( trim( $name ) ) ] = trim( $value );
		}

		return array(
			'response' => array( 'code' => $status ),
			'headers'  => $parsed,
			'body'     => $body,
		);
	}
}
