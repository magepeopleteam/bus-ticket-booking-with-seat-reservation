<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Http\Transport;
use Appneck\Sdk\Http\WpHttpTransport;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;

/**
 * The signed HTTP caller for /sdk/v1/licenses/*, and nothing else.
 *
 * ## Why this is not Client
 *
 * Client implements journal §9.2a: a five-field canonical base string
 * that includes an installation id, and — outside MODE_BOOTSTRAP — a
 * per-installation secret it refuses to send without. Licensing has
 * neither. Journal §23.1 is explicit that a licensing call must succeed
 * for a site with ZERO installation rows, because analytics consent is
 * fail-closed and a paying customer who declined telemetry must never
 * lose their license over it. Bending Client to allow that would mean
 * teaching it a third mode that skips the credential requirement — which
 * is exactly the fallback its MODE_INSTALLATION branch documents as the
 * impersonation hole §9.2a exists to close.
 *
 * So this class is additive. It touches no CredentialStore at all, and
 * Client, Signer and their modes are unmodified.
 *
 * ## Headers
 *
 * X-Api-Key, X-Timestamp, X-Signature. Deliberately NO X-Installation-Id:
 * VerifyLicensingSdkSignature neither reads nor requires one, and sending
 * a header the server ignores would invite a later reader to assume it is
 * part of the signature. It is not.
 *
 * ## Two timeouts, one class
 *
 * validate() runs on a page load a visitor is waiting on; activate() and
 * deactivate() run behind a button a human just clicked. A slow license
 * server must not make somebody's whole site feel slow, so post_fast()
 * uses a 5-second transport and post() the SDK's usual 10.
 *
 * When a Transport is injected explicitly (this package's own tests, and
 * hosts with unusual network needs) that ONE instance serves both — the
 * caller has taken ownership of the timeout and the SDK does not
 * second-guess it.
 *
 * ## Failure guarantees
 *
 * No public method throws. Every path — including a Throwable from
 * anywhere inside the SDK or the transport — returns a Response, the
 * same guarantee Client makes and for the same reason: this runs inside
 * a stranger's production WordPress site.
 */
final class LicenseClient {

	/** Seconds. Human-triggered calls: activate, deactivate. */
	const DEFAULT_TIMEOUT = 10;

	/** Seconds. Page-load calls: validate, and the uninstall best-effort. */
	const FAST_TIMEOUT = 5;

	/** @var Config */
	private $config;

	/** @var Transport */
	private $transport;

	/** @var Transport */
	private $fast_transport;

	/** @var Logger */
	private $logger;

	/** @var Response|null */
	private $last_response = null;

	public function __construct( Config $config, ?Transport $transport = null, ?Logger $logger = null ) {
		$this->config = $config;
		$this->logger = null !== $logger ? $logger : new NullLogger();

		if ( null !== $transport ) {
			$this->transport      = $transport;
			$this->fast_transport = $transport;
		} else {
			$this->transport      = new WpHttpTransport( self::DEFAULT_TIMEOUT );
			$this->fast_transport = new WpHttpTransport( self::FAST_TIMEOUT );
		}
	}

	public function config() {
		return $this->config;
	}

	/** @return Response|null Null before the first request. */
	public function last_response() {
		return $this->last_response;
	}

	/**
	 * @param string       $path    e.g. '/sdk/v1/licenses/activate'
	 * @param array<mixed> $payload JSON-encoded as the request body.
	 * @return Response
	 */
	public function post( $path, array $payload = array() ) {
		return $this->request( $path, $payload, $this->transport );
	}

	/**
	 * The same call on the short-timeout transport. See the class doc.
	 *
	 * @param string       $path
	 * @param array<mixed> $payload
	 * @return Response
	 */
	public function post_fast( $path, array $payload = array() ) {
		return $this->request( $path, $payload, $this->fast_transport );
	}

	/**
	 * @param array<mixed> $payload
	 * @return Response
	 */
	private function request( $path, array $payload, Transport $transport ) {
		// One try/catch around everything. Not defensive clutter: this is
		// the boundary between "an SDK problem" and "a fatal error on a
		// stranger's website", and it has to hold even for bugs in this
		// SDK that nobody has thought of yet.
		try {
			$response = $this->send( $path, $payload, $transport );
		} catch ( \Throwable $e ) {
			// PHP 7+. Catches Error as well as Exception, which is the
			// point — a TypeError from a malformed payload must not
			// escape into the host's page load either.
			$response = Response::from_throwable( $e );
			$this->log_failure( $path, $response );
		} catch ( \Exception $e ) {
			// PHP 5.x fallback, mirroring Client's own belt-and-braces.
			$response = Response::from_throwable( $e );
			$this->log_failure( $path, $response );
		}

		$this->last_response = $response;

		return $response;
	}

	/**
	 * @param array<mixed> $payload
	 * @return Response
	 */
	private function send( $path, array $payload, Transport $transport ) {
		$config_error = $this->config->validation_error();

		if ( null !== $config_error ) {
			return $this->fail( $path, $config_error );
		}

		$secret = $this->config->product_secret();

		if ( '' === $secret ) {
			return $this->fail( $path, 'No product secret configured.' );
		}

		// Encoded exactly ONCE, and the resulting string is both signed
		// and transmitted. Re-encoding between those two steps is the
		// single most likely way to produce a signature the server cannot
		// verify — json_encode is not canonical.
		$body = json_encode( $payload );

		if ( false === $body ) {
			return $this->fail( $path, 'The request payload could not be encoded as JSON.' );
		}

		// Captured once and reused for both the signature and the header.
		$timestamp = (string) time();

		$signature = LicenseSigner::sign( $body, $timestamp, $secret );

		$headers = array(
			'X-Api-Key'    => $this->config->api_key(),
			'X-Timestamp'  => $timestamp,
			'X-Signature'  => $signature,
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);

		$response = $transport->request( 'POST', $this->config->url_for( $path ), $headers, $body );

		if ( ! $response->ok() ) {
			$this->log_failure( $path, $response );
		}

		return $response;
	}

	/**
	 * @param string $path
	 * @param string $message
	 * @return Response
	 */
	private function fail( $path, $message ) {
		$response = Response::from_transport_error( $message );
		$this->log_failure( $path, $response );

		return $response;
	}

	/** @param string $path */
	private function log_failure( $path, Response $response ) {
		$this->logger->error(
			'Licensing request failed: POST ' . $path,
			array(
				'status' => $response->status(),
				'error'  => $response->error_message(),
			)
		);
	}
}
