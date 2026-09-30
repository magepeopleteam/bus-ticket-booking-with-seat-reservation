<?php

namespace Appneck\Sdk\Http;

/**
 * The seam between the SDK and however HTTP actually happens.
 *
 * Exists so the signing/error-handling logic can be exercised without
 * WordPress loaded, and so the same code path can be pointed at a real
 * server in an integration test. WpHttpTransport is the only
 * implementation shipped for production use.
 *
 * Implementations MUST NOT throw. They return a Response, including for
 * network failure — see Response::transport_error().
 */
interface Transport {

	/**
	 * @param string                $method  Uppercase HTTP verb.
	 * @param string                $url     Absolute URL.
	 * @param array<string, string> $headers
	 * @param string|null           $body    Raw body bytes, exactly as signed.
	 * @return Response
	 */
	public function request( $method, $url, array $headers, $body = null );
}
