<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Config;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\LicenseSigner;
use Appneck\Sdk\Signer;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use PHPUnit\Framework\TestCase;

/**
 * The licensing signing contract (journal §23.1), pinned.
 *
 * The fixture vector below is the point of this file. This SDK ships
 * inside customers' WordPress sites and cannot be recalled or hot-fixed;
 * a site will run whatever copy it installed for years. If a later
 * refactor changes the base string by one byte, every licensing request
 * from every site running that version 401s forever, with no clue in any
 * log as to why. A hardcoded expected signature is the only thing that
 * turns that into a failing test instead of a support catastrophe.
 *
 * The expected value was computed against the SERVER's own expression,
 * verbatim from App\Http\Middleware\VerifyLicensingSdkSignature:
 *
 *     hash_hmac( 'sha256', $request->getContent() . $timestamp, $secret )
 */
class LicenseSignerTest extends TestCase {

	const FIXTURE_BODY = '{"license_key":"lic_0000000000000000000000000000000000000001","domain":"example.test"}';

	const FIXTURE_TIMESTAMP = '1767225600';

	const FIXTURE_SECRET = 'sk_fixture_secret';

	const FIXTURE_SIGNATURE = '091336b98510c72a99a62d0f443f988a6a5e37c2b696cb3caf27bc0e11cfb48d';

	public function test_the_signature_matches_the_fixture_vector(): void {
		$this->assertSame(
			self::FIXTURE_SIGNATURE,
			LicenseSigner::sign( self::FIXTURE_BODY, self::FIXTURE_TIMESTAMP, self::FIXTURE_SECRET )
		);
	}

	public function test_the_base_string_is_body_then_timestamp_with_no_separator(): void {
		$this->assertSame(
			self::FIXTURE_BODY . self::FIXTURE_TIMESTAMP,
			LicenseSigner::base_string( self::FIXTURE_BODY, self::FIXTURE_TIMESTAMP )
		);
	}

	/**
	 * The mistake this exists to catch is a well-meaning "unification" of
	 * the two signers. They are different contracts against different
	 * server middleware and neither may be substituted for the other.
	 */
	public function test_it_is_not_the_same_scheme_as_the_telemetry_signer(): void {
		$telemetry = Signer::sign(
			'POST',
			'/sdk/v1/licenses/validate',
			'019fb200-0000-7000-8000-aaaaaaaaaaaa',
			self::FIXTURE_TIMESTAMP,
			self::FIXTURE_BODY,
			self::FIXTURE_SECRET
		);

		$this->assertNotSame( $telemetry, LicenseSigner::sign( self::FIXTURE_BODY, self::FIXTURE_TIMESTAMP, self::FIXTURE_SECRET ) );
	}

	public function test_a_different_secret_produces_a_different_signature(): void {
		$this->assertNotSame(
			self::FIXTURE_SIGNATURE,
			LicenseSigner::sign( self::FIXTURE_BODY, self::FIXTURE_TIMESTAMP, 'sk_a_different_secret' )
		);
	}

	public function test_a_one_second_timestamp_drift_produces_a_different_signature(): void {
		$this->assertNotSame(
			self::FIXTURE_SIGNATURE,
			LicenseSigner::sign( self::FIXTURE_BODY, (string) ( (int) self::FIXTURE_TIMESTAMP + 1 ), self::FIXTURE_SECRET )
		);
	}

	// -----------------------------------------------------------------
	// What actually goes over the wire
	// -----------------------------------------------------------------

	/**
	 * The signer being right is not enough — the client has to sign the
	 * exact bytes it transmits, and send the exact timestamp it signed.
	 */
	public function test_the_client_signs_the_body_it_transmits_with_the_timestamp_it_sends(): void {
		require_once __DIR__ . '/QueueingTransport.php';

		$transport = new QueueingTransport();
		$transport->queue( \Appneck\Sdk\Http\Response::from_http( 200, array(), '{"valid":true}' ) );

		$license = new License(
			new LicenseClient(
				new Config( 'pk_signing_test', self::FIXTURE_SECRET, 'https://api.example.test' ),
				$transport
			),
			new ArrayLicenseStore()
		);
		$license->set_domain( 'example.test' );

		$license->validate( 'lic_0000000000000000000000000000000000000001' );

		$request = $transport->last_request();

		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://api.example.test/sdk/v1/licenses/validate', $request['url'] );

		$this->assertSame(
			LicenseSigner::sign( $request['body'], $request['headers']['X-Timestamp'], self::FIXTURE_SECRET ),
			$request['headers']['X-Signature']
		);
	}

	/**
	 * Journal §23.1 attaches no installation and reads no such header.
	 * Sending one would invite a later reader to assume it is part of the
	 * base string, which it is not.
	 */
	public function test_no_installation_id_header_is_sent(): void {
		require_once __DIR__ . '/QueueingTransport.php';

		$transport = new QueueingTransport();
		$transport->queue( \Appneck\Sdk\Http\Response::from_http( 200, array(), '{"valid":true}' ) );

		$license = new License(
			new LicenseClient(
				new Config( 'pk_signing_test', self::FIXTURE_SECRET, 'https://api.example.test' ),
				$transport
			),
			new ArrayLicenseStore()
		);
		$license->set_domain( 'example.test' );
		$license->validate( 'lic_0000000000000000000000000000000000000001' );

		$headers = $transport->last_request()['headers'];

		$this->assertArrayNotHasKey( 'X-Installation-Id', $headers );
		$this->assertSame( 'pk_signing_test', $headers['X-Api-Key'] );
		$this->assertArrayHasKey( 'X-Timestamp', $headers );
		$this->assertArrayHasKey( 'X-Signature', $headers );
	}
}
