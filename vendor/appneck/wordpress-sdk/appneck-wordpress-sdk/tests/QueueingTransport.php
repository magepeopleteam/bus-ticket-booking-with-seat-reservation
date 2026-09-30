<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Http\Transport;

/**
 * A transport that returns queued responses in order, and records every
 * request. Lets a test drive a multi-step lifecycle (register → fail →
 * retry → deactivate) through the real Client and Lifecycle code.
 */
final class QueueingTransport implements Transport {

	/** @var Response[] */
	private $queue = array();

	/** @var array<int, array<string, mixed>> */
	private $requests = array();

	public function queue( Response $response ) {
		$this->queue[] = $response;
	}

	public function request( $method, $url, array $headers, $body = null ) {
		$this->requests[] = compact( 'method', 'url', 'headers', 'body' );

		if ( empty( $this->queue ) ) {
			// An unqueued call is a test bug, not a network condition —
			// make it visible rather than returning a plausible success.
			return Response::from_transport_error( 'QueueingTransport: no response queued for ' . $method . ' ' . $url );
		}

		return array_shift( $this->queue );
	}

	public function count() {
		return count( $this->requests );
	}

	/** @return array<string, mixed>|null */
	public function last_request() {
		return empty( $this->requests ) ? null : $this->requests[ count( $this->requests ) - 1 ];
	}
}
