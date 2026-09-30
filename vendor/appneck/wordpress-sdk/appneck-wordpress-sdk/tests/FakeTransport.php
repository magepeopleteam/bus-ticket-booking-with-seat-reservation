<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Http\Transport;

/**
 * Records what the client sent and returns a canned Response, so the
 * signing/error-handling behaviour can be asserted without a network.
 */
final class FakeTransport implements Transport {

	/** @var array<string, mixed>|null */
	public $last_request = null;

	/** @var Response */
	private $response;

	/** @var callable|null */
	private $thrower = null;

	public function __construct( ?Response $response = null ) {
		$this->response = null !== $response ? $response : Response::from_http( 200, array(), '{"ok":true}' );
	}

	/** Makes the transport itself blow up, to prove nothing escapes. */
	public function throw_on_request( callable $thrower ) {
		$this->thrower = $thrower;
	}

	public function request( $method, $url, array $headers, $body = null ) {
		$this->last_request = compact( 'method', 'url', 'headers', 'body' );

		if ( null !== $this->thrower ) {
			call_user_func( $this->thrower );
		}

		return $this->response;
	}
}
