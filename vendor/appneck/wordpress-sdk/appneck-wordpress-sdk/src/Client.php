<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Http\Transport;
use Appneck\Sdk\Http\WpHttpTransport;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;
use Appneck\Sdk\Storage\CredentialStore;

/**
 * The signed HTTP client every other part of the SDK is built on.
 *
 * Deliberately low-level: it knows how to sign and send a request to the
 * SDK zone and how to fail safely, and nothing about what any particular
 * endpoint means. Registration, telemetry, consent, surveys and
 * announcements (S4.2 onward) are built as thin callers of post()/get().
 *
 * ## The two signing modes (journal §9.2a)
 *
 * MODE_BOOTSTRAP  — signs with the product secret from Config. The only
 *                   legitimate use is POST /sdk/v1/installations, which
 *                   happens before any installation secret exists. The
 *                   installation id is still part of the base string:
 *                   the CLIENT generates it and enrols under it.
 * MODE_INSTALLATION — signs with the per-installation secret from the
 *                   CredentialStore. Everything else. Refuses to send at
 *                   all when no credentials are stored, rather than
 *                   signing with the product secret as a fallback, which
 *                   would be silently reintroducing the exact
 *                   impersonation hole §9.2a closed.
 *
 * ## Failure guarantees
 *
 * No public method throws. Every path — including a Throwable from
 * anywhere inside the SDK or the transport — returns a Response. See
 * Response's class doc for why that matters this much.
 */
final class Client {

	const MODE_BOOTSTRAP    = 'bootstrap';
	const MODE_INSTALLATION = 'installation';

	/** @var Config */
	private $config;

	/** @var CredentialStore */
	private $credentials;

	/** @var Transport */
	private $transport;

	/** @var Logger */
	private $logger;

	/** @var Response|null The most recent response, for rate-limit inspection. */
	private $last_response = null;

	public function __construct(
		Config $config,
		CredentialStore $credentials,
		?Transport $transport = null,
		?Logger $logger = null
	) {
		$this->config      = $config;
		$this->credentials = $credentials;
		$this->transport   = null !== $transport ? $transport : new WpHttpTransport();
		$this->logger      = null !== $logger ? $logger : new NullLogger();
	}

	public function config() {
		return $this->config;
	}

	public function credentials() {
		return $this->credentials;
	}

	/**
	 * The last response received, for callers that want rate-limit state
	 * without threading it through their own return values. Null before
	 * the first request.
	 *
	 * @return Response|null
	 */
	public function last_response() {
		return $this->last_response;
	}

	/**
	 * @param string             $path    e.g. '/sdk/v1/telemetry'
	 * @param array<mixed>       $payload JSON-encoded as the request body.
	 * @param string             $mode    MODE_BOOTSTRAP|MODE_INSTALLATION
	 * @param string|null        $installation_id Overrides the stored id.
	 *                                    Required in bootstrap mode, where
	 *                                    nothing is stored yet.
	 */
	public function post( $path, array $payload = array(), $mode = self::MODE_INSTALLATION, $installation_id = null ) {
		return $this->request( 'POST', $path, $payload, $mode, $installation_id );
	}

	/**
	 * @param string                $path
	 * @param array<mixed>          $query   Appended to the URL. NOT signed
	 *                                       — the base string carries the
	 *                                       path only (journal §9.2a), so
	 *                                       the server must not be given
	 *                                       security-relevant input here.
	 * @param array<string, string> $headers Extra request headers, e.g.
	 *                                       If-None-Match. Same reasoning
	 *                                       as $query: not part of the
	 *                                       signed base string, so nothing
	 *                                       security-relevant belongs here
	 *                                       either — the server-side
	 *                                       equivalent (VerifySdkSignature)
	 *                                       never reads a header for
	 *                                       anything but the signature
	 *                                       itself.
	 */
	public function get( $path, array $query = array(), array $headers = array() ) {
		return $this->request( 'GET', $path, null, self::MODE_INSTALLATION, null, $query, $headers );
	}

	/**
	 * @param array<mixed>|null    $payload
	 * @param array<mixed>         $query
	 * @param array<string,string> $extra_headers
	 */
	private function request( $method, $path, $payload, $mode, $installation_id = null, array $query = array(), array $extra_headers = array() ) {
		// One try/catch around everything. Not defensive clutter: this
		// is the boundary between "an SDK problem" and "a fatal error on
		// a stranger's website", and it has to hold even for bugs in
		// this SDK that nobody has thought of yet.
		try {
			$response = $this->send( $method, $path, $payload, $mode, $installation_id, $query, $extra_headers );
		} catch ( \Throwable $e ) {
			// PHP 7+. Catches Error as well as Exception, which is the
			// point — a TypeError from a malformed payload must not
			// escape into the host's page load either.
			$response = Response::from_throwable( $e );
			$this->log_failure( $method, $path, $response );
		} catch ( \Exception $e ) {
			// PHP 5.x fallback. WordPress still supports PHP 7.2+, but a
			// plugin bundling this SDK may be loaded on older hosts.
			$response = Response::from_throwable( $e );
			$this->log_failure( $method, $path, $response );
		}

		$this->last_response = $response;

		return $response;
	}

	/**
	 * @param array<mixed>|null    $payload
	 * @param array<mixed>         $query
	 * @param array<string,string> $extra_headers
	 */
	private function send( $method, $path, $payload, $mode, $installation_id, array $query, array $extra_headers = array() ) {
		$config_error = $this->config->validation_error();

		if ( null !== $config_error ) {
			return $this->fail( $method, $path, $config_error );
		}

		$secret = $this->secret_for_mode( $mode );

		if ( null === $secret ) {
			return $this->fail(
				$method,
				$path,
				self::MODE_BOOTSTRAP === $mode
					? 'No product secret configured.'
					: 'This installation has no stored signing secret. Register it first.'
			);
		}

		$installation_id = $this->installation_id_for( $mode, $installation_id );

		if ( null === $installation_id || '' === $installation_id ) {
			return $this->fail( $method, $path, 'No installation id available to sign with.' );
		}

		// The body is encoded exactly ONCE, and the resulting string is
		// both signed and transmitted. Re-encoding between those two
		// steps is the single most likely way to produce a signature
		// that cannot be verified: json_encode is not canonical, so a
		// decode/encode round trip can legally change key order, escape
		// sequences and float formatting (journal §9.2a).
		$body = null;

		if ( null !== $payload ) {
			$body = json_encode( $payload );

			if ( false === $body ) {
				return $this->fail( $method, $path, 'The request payload could not be encoded as JSON.' );
			}
		}

		// Captured once and reused for both the signature and the header.
		// Reading time() twice is a genuine intermittent bug: it works
		// until the two calls land either side of a second boundary, and
		// then fails a fraction of a percent of requests forever.
		$timestamp = (string) time();

		$signature = Signer::sign(
			$method,
			$path,
			$installation_id,
			$timestamp,
			null === $body ? '' : $body,
			$secret
		);

		$headers = array(
			'X-Api-Key'         => $this->config->api_key(),
			'X-Installation-Id' => $installation_id,
			'X-Timestamp'       => $timestamp,
			'X-Signature'       => $signature,
			'Accept'            => 'application/json',
		);

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
		}

		// Merged in last: an extra header may add to the request (e.g.
		// If-None-Match) but must never be able to override one of the
		// signed identity headers above.
		foreach ( $extra_headers as $name => $value ) {
			if ( ! array_key_exists( $name, $headers ) ) {
				$headers[ $name ] = $value;
			}
		}

		$url = $this->config->url_for( $path );

		if ( ! empty( $query ) ) {
			// Appended AFTER signing on purpose — the base string is the
			// path only. Sending a query string the signature does not
			// cover is safe precisely because the server does not read
			// security-relevant input from it.
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query );
		}

		$response = $this->transport->request( $method, $url, $headers, $body );

		if ( ! $response->ok() ) {
			$this->log_failure( $method, $path, $response );
		}

		return $response;
	}

	/**
	 * @return string|null
	 */
	private function secret_for_mode( $mode ) {
		if ( self::MODE_BOOTSTRAP === $mode ) {
			$secret = $this->config->product_secret();

			return '' === $secret ? null : $secret;
		}

		$secret = $this->credentials->get_installation_secret();

		// Deliberately NOT falling back to the product secret. That
		// fallback would make every installation able to sign as any
		// other installation of the same product — the exact hole
		// journal §9.2a's per-installation secrets exist to close — and
		// it would do so invisibly, since requests would keep working.
		return empty( $secret ) ? null : $secret;
	}

	/**
	 * @return string|null
	 */
	private function installation_id_for( $mode, $explicit ) {
		if ( null !== $explicit && '' !== $explicit ) {
			return (string) $explicit;
		}

		return $this->credentials->get_installation_id();
	}

	private function fail( $method, $path, $message ) {
		$response = Response::from_transport_error( $message );
		$this->log_failure( $method, $path, $response );

		return $response;
	}

	private function log_failure( $method, $path, Response $response ) {
		$this->logger->error(
			'Request failed: ' . strtoupper( $method ) . ' ' . $path,
			array(
				'status' => $response->status(),
				'error'  => $response->error_message(),
			)
		);
	}
}
