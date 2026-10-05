<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\MarketingConsent;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * MarketingConsent in isolation — local storage only, no network call of
 * its own (Consent::sync() is what actually reaches the server; see
 * ConsentTest for that half).
 */
class MarketingConsentTest extends TestCase {

	const API_KEY        = 'pk_marketing_consent_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-dddddddddddd';
	const BASE_URL       = 'https://api.example.test';

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_options'] = array();
	}

	private function marketing_consent(): MarketingConsent {
		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			new QueueingTransport()
		);

		return new MarketingConsent( $client );
	}

	public function test_starts_undecided(): void {
		$mc = $this->marketing_consent();

		$this->assertFalse( $mc->has_decided() );
		$this->assertFalse( $mc->is_opted_in() );
		$this->assertNull( $mc->wording() );
		$this->assertNull( $mc->email() );
		$this->assertFalse( $mc->is_sync_pending() );
	}

	public function test_opting_in_stores_the_wording_and_email_and_is_pending(): void {
		$mc = $this->marketing_consent();

		$mc->decide( true, 'Also email me updates at admin@example.test.', 'admin@example.test' );

		$this->assertTrue( $mc->has_decided() );
		$this->assertTrue( $mc->is_opted_in() );
		$this->assertSame( 'Also email me updates at admin@example.test.', $mc->wording() );
		$this->assertSame( 'admin@example.test', $mc->email() );
		$this->assertTrue( $mc->is_sync_pending() );
	}

	public function test_declining_stores_the_wording_but_never_an_email(): void {
		$mc = $this->marketing_consent();

		// Even if a caller mistakenly passes an email while declining,
		// data minimisation wins: it is never stored.
		$mc->decide( false, 'Also email me updates...', 'admin@example.test' );

		$this->assertTrue( $mc->has_decided() );
		$this->assertFalse( $mc->is_opted_in() );
		$this->assertNull( $mc->email() );
	}

	public function test_mark_synced_clears_the_pending_flag(): void {
		$mc = $this->marketing_consent();

		$mc->decide( true, 'wording', 'admin@example.test' );
		$this->assertTrue( $mc->is_sync_pending() );

		$mc->mark_synced();

		$this->assertFalse( $mc->is_sync_pending() );
		// The decision itself is untouched by marking it synced.
		$this->assertTrue( $mc->is_opted_in() );
		$this->assertSame( 'admin@example.test', $mc->email() );
	}

	public function test_mark_synced_before_any_decision_does_nothing(): void {
		$mc = $this->marketing_consent();

		$mc->mark_synced(); // must not throw or fabricate a decision

		$this->assertFalse( $mc->has_decided() );
	}

	public function test_forget_clears_the_local_record(): void {
		$mc = $this->marketing_consent();

		$mc->decide( true, 'wording', 'admin@example.test' );
		$mc->forget();

		$this->assertFalse( $mc->has_decided() );
		$this->assertNull( $mc->email() );
	}

	public function test_the_option_is_namespaced_per_product(): void {
		$other = new MarketingConsent(
			new Client(
				new Config( 'pk_another_product', self::PRODUCT_SECRET, self::BASE_URL ),
				new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
				new QueueingTransport()
			)
		);

		$mc = $this->marketing_consent();
		$mc->decide( true, 'wording', 'admin@example.test' );

		// A different product's instance never sees this one's decision —
		// same per-product option-key scheme Consent itself uses.
		$this->assertFalse( $other->has_decided() );
	}
}
