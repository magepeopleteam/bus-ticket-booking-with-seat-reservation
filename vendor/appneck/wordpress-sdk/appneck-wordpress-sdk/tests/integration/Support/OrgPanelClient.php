<?php

namespace Appneck\Sdk\Tests\Integration\Support;

/**
 * A thin curl client for the Dashboard zone (`/app/v1/*`, Sanctum bearer
 * auth), used ONLY to create and remove the fixtures a couple of these
 * integration tests need through the real authoring APIs — survey
 * questions and announcements — rather than documenting "a human must
 * configure this in the Org Panel first" as a standing precondition.
 *
 * Deliberately separate from the SDK's own `Client`: that class signs
 * `/sdk/v1/*` requests with a product API key/secret per journal §9.2a,
 * which is a different auth model entirely from a logged-in org member's
 * bearer token. Reusing `Client` here would mean bending the thing under
 * test to fit a concern it was never meant to have.
 */
final class OrgPanelClient {

	/** @var string */
	private $base_url;

	/** @var string|null */
	private $token = null;

	public function __construct( $base_url ) {
		$this->base_url = rtrim( $base_url, '/' );
	}

	/**
	 * Logs in exactly like a real dashboard user and caches the resulting
	 * Sanctum token for the rest of this instance's calls.
	 *
	 * @return bool
	 */
	public function login( $email, $password ) {
		$response = $this->request( 'POST', '/app/v1/auth/login', array(
			'email'    => $email,
			'password' => $password,
		), null );

		if ( 200 !== $response['status'] || ! isset( $response['body']['token'] ) ) {
			return false;
		}

		$this->token = $response['body']['token'];

		return true;
	}

	/** @return array{status:int, body:mixed} */
	public function post( $path, array $payload ) {
		return $this->request( 'POST', $path, $payload, $this->token );
	}

	/**
	 * Added for licensing: the org panel exposes lifecycle transitions as
	 * PATCH with an `action` verb (journal §22.13), never a DELETE, so
	 * cancelling a fixture license needs this rather than delete().
	 *
	 * @return array{status:int, body:mixed}
	 */
	public function patch( $path, array $payload ) {
		return $this->request( 'PATCH', $path, $payload, $this->token );
	}

	/** @return array{status:int, body:mixed} */
	public function delete( $path ) {
		return $this->request( 'DELETE', $path, null, $this->token );
	}

	/** @return array{status:int, body:mixed} */
	public function get( $path ) {
		return $this->request( 'GET', $path, null, $this->token );
	}

	/**
	 * @param string      $method
	 * @param string      $path
	 * @param array|null  $payload
	 * @param string|null $token
	 * @return array{status:int, body:mixed}
	 */
	private function request( $method, $path, $payload, $token ) {
		$ch = curl_init( $this->base_url . $path );

		$headers = array( 'Accept: application/json' );

		if ( null !== $token ) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => 10,
				CURLOPT_HTTPHEADER     => $headers,
			)
		);

		if ( null !== $payload ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );
			$headers[] = 'Content-Type: application/json';
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		}

		$raw    = curl_exec( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$decoded = null;

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
		}

		return array(
			'status' => $status,
			'body'   => null !== $decoded ? $decoded : $raw,
		);
	}
}
