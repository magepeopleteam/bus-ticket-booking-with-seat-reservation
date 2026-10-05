<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Signer;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * The client's two jobs: sign exactly right, and never throw.
 *
 * The second matters more than it looks. This library runs inside other
 * people's production WordPress sites, where an uncaught Throwable is a
 * white screen on a site whose owner has never heard of Appneck. Every
 * failure mode below is therefore asserted to RETURN rather than throw.
 */
class ClientTest extends TestCase {

	const API_KEY        = 'pk_test_key';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-aaaaaaaaaaaa';
	const BASE_URL       = 'https://api.example.test';

	private function client( ?Response $response = null, ?ArrayCredentialStore $store = null, ?FakeTransport &$transport = null ) {
		$transport = new FakeTransport( $response );

		return new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			null !== $store ? $store : new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$transport
		);
	}

	// -----------------------------------------------------------------
	// Signing
	// -----------------------------------------------------------------

	public function test_it_signs_with_the_installation_secret_and_sends_what_it_signed(): void {
		$client = $this->client( null, null, $transport );

		$client->post( '/sdk/v1/telemetry', array( 'events' => array( array( 'type' => 'heartbeat' ) ) ) );

		$sent = $transport->last_request;
		$this->assertSame( 'POST', $sent['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/telemetry', $sent['url'] );

		// The signature must verify against the EXACT bytes transmitted.
		// Re-encoding the payload here instead of using $sent['body']
		// would make this test pass while the real server 401s.
		$expected = Signer::sign(
			'POST',
			'/sdk/v1/telemetry',
			self::INSTALL_ID,
			$sent['headers']['X-Timestamp'],
			$sent['body'],
			self::INSTALL_SECRET
		);

		$this->assertSame( $expected, $sent['headers']['X-Signature'] );
		$this->assertSame( self::API_KEY, $sent['headers']['X-Api-Key'] );
		$this->assertSame( self::INSTALL_ID, $sent['headers']['X-Installation-Id'] );
	}

	public function test_bootstrap_mode_signs_with_the_product_secret_and_an_explicit_id(): void {
		$client = $this->client( null, new ArrayCredentialStore(), $transport );

		$new_id = '019fb200-0000-7000-8000-cccccccccccc';
		$client->post( '/sdk/v1/installations', array( 'site_domain' => 'example.com' ), Client::MODE_BOOTSTRAP, $new_id );

		$sent     = $transport->last_request;
		$expected = Signer::sign(
			'POST',
			'/sdk/v1/installations',
			$new_id,
			$sent['headers']['X-Timestamp'],
			$sent['body'],
			self::PRODUCT_SECRET
		);

		$this->assertSame( $expected, $sent['headers']['X-Signature'] );
		$this->assertSame( $new_id, $sent['headers']['X-Installation-Id'] );
	}

	/**
	 * The security property journal §9.2a exists for: with no stored
	 * installation secret, an installation-mode call must FAIL rather
	 * than quietly fall back to the product secret — which every
	 * customer running the plugin also holds, and which would let any
	 * installation sign as any other.
	 */
	public function test_it_refuses_to_fall_back_to_the_product_secret(): void {
		$client = $this->client( null, new ArrayCredentialStore(), $transport );

		$response = $client->post( '/sdk/v1/telemetry', array( 'events' => array() ) );

		$this->assertFalse( $response->ok() );
		$this->assertNull( $transport->last_request, 'Nothing should have been sent' );
		$this->assertStringContainsString( 'no stored signing secret', $response->error_message() );
	}

	public function test_a_get_signs_an_empty_body_and_does_not_sign_the_query_string(): void {
		$client = $this->client( null, null, $transport );

		$client->get( '/sdk/v1/announcements', array( 'since' => '2026-01-01' ) );

		$sent = $transport->last_request;
		$this->assertNull( $sent['body'] );
		$this->assertStringContainsString( 'since=2026-01-01', $sent['url'] );

		$expected = Signer::sign(
			'GET',
			'/sdk/v1/announcements',
			self::INSTALL_ID,
			$sent['headers']['X-Timestamp'],
			'',
			self::INSTALL_SECRET
		);

		$this->assertSame( $expected, $sent['headers']['X-Signature'] );
	}

	// -----------------------------------------------------------------
	// Failing safely
	// -----------------------------------------------------------------

	public function test_a_transport_failure_returns_instead_of_throwing(): void {
		$client = $this->client( Response::from_transport_error( 'cURL error 28: timed out' ), null, $transport );

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertTrue( $response->is_transport_error() );
		$this->assertSame( 0, $response->status() );
		$this->assertTrue( $response->is_retryable() );
	}

	/**
	 * The guarantee that matters most: even a bug INSIDE the SDK or the
	 * transport — a TypeError, a division by zero, anything — must not
	 * escape into the host site's page load.
	 */
	public function test_a_throwable_from_the_transport_never_escapes(): void {
		$client = $this->client( null, null, $transport );
		$transport->throw_on_request(
			function () {
				throw new \RuntimeException( 'the transport exploded' );
			}
		);

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertTrue( $response->is_transport_error() );
		$this->assertStringContainsString( 'the transport exploded', $response->error_message() );
	}

	public function test_a_php_error_from_the_transport_never_escapes(): void {
		$client = $this->client( null, null, $transport );
		$transport->throw_on_request(
			function () {
				// An Error, not an Exception — the case a bare
				// `catch (Exception $e)` would miss entirely.
				throw new \TypeError( 'bad argument somewhere in the SDK' );
			}
		);

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertStringContainsString( 'bad argument', $response->error_message() );
	}

	public function test_an_unencodable_payload_fails_cleanly(): void {
		$client = $this->client( null, null, $transport );

		// Malformed UTF-8 cannot be JSON-encoded.
		$response = $client->post( '/sdk/v1/telemetry', array( 'bad' => "\xB1\x31" ) );

		$this->assertFalse( $response->ok() );
		$this->assertStringContainsString( 'could not be encoded', $response->error_message() );
		$this->assertNull( $transport->last_request );
	}

	public function test_a_misconfigured_base_url_fails_before_sending(): void {
		$transport = new FakeTransport();
		$client    = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, 'not-a-url' ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$transport
		);

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertStringContainsString( 'absolute http(s) URL', $response->error_message() );
		$this->assertNull( $transport->last_request );
	}

	// -----------------------------------------------------------------
	// Reading the server's answers
	// -----------------------------------------------------------------

	public function test_it_surfaces_the_servers_own_error_message(): void {
		$client = $this->client(
			Response::from_http( 403, array(), '{"message":"Consent required before telemetry can be accepted for this installation."}' ),
			null,
			$transport
		);

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertTrue( $response->is_forbidden() );
		$this->assertSame( 'Consent required before telemetry can be accepted for this installation.', $response->error_message() );
		$this->assertFalse( $response->is_retryable() );
	}

	public function test_it_exposes_validation_errors_from_a_422(): void {
		$body   = '{"message":"One or more answers are not valid.","errors":{"0":["Unknown question id for this product."]}}';
		$client = $this->client( Response::from_http( 422, array(), $body ), null, $transport );

		$response = $client->post( '/sdk/v1/surveys', array() );

		$this->assertSame( array( '0' => array( 'Unknown question id for this product.' ) ), $response->validation_errors() );
	}

	public function test_it_reads_rate_limit_headers(): void {
		$client = $this->client(
			Response::from_http(
				429,
				array(
					'x-ratelimit-limit'     => '100',
					'x-ratelimit-remaining' => '0',
					'retry-after'           => '260',
				),
				'{"message":"Rate limit exceeded for this installation."}'
			),
			null,
			$transport
		);

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertTrue( $response->is_rate_limited() );
		$this->assertTrue( $response->is_retryable() );
		$this->assertSame( 100, $response->rate_limit()->limit() );
		$this->assertSame( 0, $response->rate_limit()->remaining() );
		$this->assertSame( 260, $response->rate_limit()->retry_after() );
		$this->assertSame( $response, $client->last_response() );
	}

	/**
	 * A caching plugin or hosting error page replacing the body with
	 * HTML must not read as a successful empty result.
	 */
	public function test_a_malformed_body_on_a_200_is_a_failure_not_an_empty_success(): void {
		$client = $this->client( Response::from_http( 200, array(), '<html>Service unavailable</html>' ), null, $transport );

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertFalse( $response->ok() );
		$this->assertStringContainsString( 'Malformed response body', $response->error_message() );
	}

	public function test_a_401_is_reported_as_unauthorized_and_is_not_retryable(): void {
		$client = $this->client( Response::from_http( 401, array(), '{"message":"Invalid signature."}' ), null, $transport );

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertTrue( $response->is_unauthorized() );
		$this->assertFalse( $response->is_retryable() );
	}

	public function test_a_5xx_is_retryable(): void {
		$client = $this->client( Response::from_http( 503, array(), '' ), null, $transport );

		$this->assertTrue( $client->post( '/sdk/v1/telemetry', array() )->is_retryable() );
	}

	public function test_an_empty_body_on_a_2xx_is_still_a_success(): void {
		$client = $this->client( Response::from_http( 204, array(), '' ), null, $transport );

		$response = $client->post( '/sdk/v1/telemetry', array() );

		$this->assertTrue( $response->ok() );
		$this->assertSame( array(), $response->data() );
	}
}
