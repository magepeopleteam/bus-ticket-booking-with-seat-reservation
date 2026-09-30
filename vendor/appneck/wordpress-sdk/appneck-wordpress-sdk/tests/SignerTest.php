<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Sdk;
use Appneck\Sdk\Signer;
use PHPUnit\Framework\TestCase;

/**
 * The signing contract, journal §9.2a.
 *
 * The two KNOWN_GOOD_* constants below were not produced by this class.
 * They were computed by the BACKEND, in the backend container, using the
 * same expression App\Tracking\Http\Middleware\VerifySdkSignature uses to
 * verify a live request. A test that only checks this implementation
 * against itself would happily pin a base string the server rejects —
 * which is the entire failure mode worth guarding against here, since a
 * wrong base string produces a valid-looking signature and an
 * undiagnosable 401.
 */
class SignerTest extends TestCase {

	const SECRET          = 'sk_test_product_secret_value_for_signing_00';
	const INSTALLATION_ID = '019fb200-0000-7000-8000-aaaaaaaaaaaa';
	const TIMESTAMP       = '1754300000';

	/** Backend-computed: POST /sdk/v1/telemetry with the body below. */
	const KNOWN_GOOD_POST = '6ab7a9cfa54b2fca5b4606f994b2eb414130933fe622f0727e07417941b7df53';

	/** Backend-computed: GET /sdk/v1/announcements, empty body. */
	const KNOWN_GOOD_GET = '7791f13100f3195fa404e830e2db4bc784d0377162beb0d14db2b5109fcb1a52';

	const POST_BODY = '{"events":[{"type":"heartbeat","payload":{"php":"8.4"}}]}';

	public function test_it_matches_the_backend_signature_for_a_post(): void {
		$signature = Signer::sign(
			'POST',
			'/sdk/v1/telemetry',
			self::INSTALLATION_ID,
			self::TIMESTAMP,
			self::POST_BODY,
			self::SECRET
		);

		$this->assertSame( self::KNOWN_GOOD_POST, $signature );
	}

	/**
	 * A GET signs an empty body — the base string still has all five
	 * fields and therefore still ends with a trailing newline before the
	 * empty final field. Dropping that separator is the obvious
	 * simplification, and it produces a signature the server rejects.
	 */
	public function test_it_matches_the_backend_signature_for_a_get_with_no_body(): void {
		$signature = Signer::sign(
			'GET',
			'/sdk/v1/announcements',
			self::INSTALLATION_ID,
			self::TIMESTAMP,
			'',
			self::SECRET
		);

		$this->assertSame( self::KNOWN_GOOD_GET, $signature );
	}

	public function test_the_base_string_has_exactly_the_documented_shape(): void {
		$base = Signer::base_string( 'POST', '/sdk/v1/telemetry', self::INSTALLATION_ID, self::TIMESTAMP, self::POST_BODY );

		$this->assertSame(
			"POST\n/sdk/v1/telemetry\n" . self::INSTALLATION_ID . "\n" . self::TIMESTAMP . "\n" . self::POST_BODY,
			$base
		);

		// Five fields, four separators — and the separator is "\n", not
		// "\r\n", which some HTTP-adjacent code reaches for by habit.
		$this->assertCount( 5, explode( "\n", $base ) );
		$this->assertStringNotContainsString( "\r", $base );
	}

	public function test_the_method_is_upper_cased(): void {
		$this->assertSame(
			Signer::base_string( 'POST', '/x', 'i', '1', '' ),
			Signer::base_string( 'post', '/x', 'i', '1', '' )
		);
	}

	/**
	 * A caller passing 'sdk/v1/telemetry' must produce the same
	 * signature as one passing '/sdk/v1/telemetry' — the server always
	 * builds the leading slash itself.
	 */
	public function test_a_missing_leading_slash_is_normalised(): void {
		$this->assertSame(
			Signer::base_string( 'POST', '/sdk/v1/telemetry', 'i', '1', '' ),
			Signer::base_string( 'POST', 'sdk/v1/telemetry', 'i', '1', '' )
		);
	}

	public function test_signing_is_deterministic(): void {
		$args = array( 'POST', '/sdk/v1/consent', self::INSTALLATION_ID, self::TIMESTAMP, '{"a":1}', self::SECRET );

		$first = call_user_func_array( array( Signer::class, 'sign' ), $args );

		for ( $i = 0; $i < 25; $i++ ) {
			$this->assertSame( $first, call_user_func_array( array( Signer::class, 'sign' ), $args ) );
		}
	}

	/**
	 * Each field genuinely participates. Without this, a base string that
	 * silently dropped, say, the path would still pass every test above
	 * that only checks one vector.
	 */
	public function test_every_field_changes_the_signature(): void {
		$base = array( 'POST', '/sdk/v1/telemetry', self::INSTALLATION_ID, self::TIMESTAMP, self::POST_BODY, self::SECRET );

		$mutations = array(
			'method'          => array( 'GET', 1, 0 ),
			'path'            => array( '/sdk/v1/consent', 1, 1 ),
			'installation_id' => array( '019fb200-0000-7000-8000-bbbbbbbbbbbb', 1, 2 ),
			'timestamp'       => array( '1754300001', 1, 3 ),
			'body'            => array( '{"events":[]}', 1, 4 ),
			'secret'          => array( 'sk_a_different_secret_entirely_0000000000', 1, 5 ),
		);

		$original = call_user_func_array( array( Signer::class, 'sign' ), $base );

		foreach ( $mutations as $field => $mutation ) {
			$mutated                   = $base;
			$mutated[ $mutation[2] ]   = $mutation[0];
			$this->assertNotSame(
				$original,
				call_user_func_array( array( Signer::class, 'sign' ), $mutated ),
				"Changing {$field} did not change the signature"
			);
		}
	}

	/**
	 * The loader ranks copies of the SDK by the version string in
	 * appneck-sdk.php, which cannot read Sdk::VERSION. Bumping one and
	 * not the other would make this copy rank wrongly against its
	 * siblings on a site — silently, and only when two plugins are
	 * installed together.
	 */
	public function test_the_loader_version_and_the_class_constant_agree(): void {
		$loader = file_get_contents( dirname( __DIR__ ) . '/appneck-sdk.php' );

		$this->assertSame(
			1,
			preg_match( "/\\\$appneck_sdk_this_version\s*=\s*'([^']+)'/", $loader, $matches ),
			'Could not find the version literal in appneck-sdk.php'
		);

		$this->assertSame( Sdk::VERSION, $matches[1] );
	}
}
