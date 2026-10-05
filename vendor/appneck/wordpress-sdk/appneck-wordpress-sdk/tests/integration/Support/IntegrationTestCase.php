<?php

namespace Appneck\Sdk\Tests\Integration\Support;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * Base class for every real-backend integration test converted from the
 * old tests/integration/*-check.php scripts (S4.7 audit).
 *
 * Two things every one of those scripts had to do by hand, done once
 * here instead: (1) skip cleanly instead of failing when there's no
 * backend to talk to, so `composer test` (the default, no `:integration`
 * suffix) never breaks for a developer without the Docker stack running;
 * (2) reset the WordPress-polyfill globals between tests so each test
 * method starts as its own "site" — the polyfills in tests/wp-*-polyfill.php
 * back options/cron/hooks with process globals, and without a reset a
 * later test would see state an earlier one left behind.
 */
abstract class IntegrationTestCase extends TestCase {

	/** @var EnvCredentials|null */
	private static $credentials = null;

	/** @var bool|null Cached across the whole run — one probe, not one per test. */
	private static $reachable = null;

	protected function setUp(): void {
		parent::setUp();

		$credentials = $this->credentials();

		if ( ! $credentials->configured() ) {
			$this->markTestSkipped(
				'No backend configured — set APPNECK_SDK_TEST_API_KEY and ' .
				'APPNECK_SDK_TEST_PRODUCT_SECRET to run the integration suite. ' .
				'See packages/wordpress-sdk/README.md, "Integration tests".'
			);
		}

		if ( ! $this->backend_reachable( $credentials->base_url() ) ) {
			$this->markTestSkipped(
				'APPNECK_SDK_TEST_BASE_URL (' . $credentials->base_url() . ') is not reachable. ' .
				'Is the dev stack up (`docker compose up`)?'
			);
		}

		$this->reset_wordpress_state();
	}

	protected function credentials() {
		if ( null === self::$credentials ) {
			self::$credentials = new EnvCredentials();
		}

		return self::$credentials;
	}

	/**
	 * A quick, cheap probe — Laravel's own `/up` health check — rather
	 * than attempting a real SDK call and treating any failure as
	 * "unreachable": that would conflate "no server" with "server up but
	 * refusing these credentials", which is a real assertion these tests
	 * want to make, not a skip condition.
	 */
	private function backend_reachable( $base_url ) {
		if ( null !== self::$reachable ) {
			return self::$reachable;
		}

		$ch = curl_init( rtrim( $base_url, '/' ) . '/up' );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 2,
				CURLOPT_TIMEOUT        => 3,
				CURLOPT_NOBODY         => true,
			)
		);
		curl_exec( $ch );
		$errno = curl_errno( $ch );
		curl_close( $ch );

		self::$reachable = 0 === $errno;

		return self::$reachable;
	}

	/**
	 * Every polyfill global these tests can touch, reset to empty. Not
	 * every test uses every one, but resetting all of them is cheap and
	 * removes any chance of one test's leftover state changing another's
	 * result depending on run order.
	 */
	protected function reset_wordpress_state() {
		$GLOBALS['appneck_test_options']    = array();
		$GLOBALS['appneck_test_cron']       = array();
		$GLOBALS['appneck_test_hooks']      = array();
		$GLOBALS['appneck_test_did_action'] = array();
		$GLOBALS['appneck_test_filters']    = array();
		$GLOBALS['appneck_test_admin']      = array(
			'can'       => true,
			'nonce_ok'  => true,
			'referer'   => 'https://example.test/wp-admin/options-general.php',
			'redirects' => array(),
			'checked'   => array(),
		);
	}

	/** A fresh, collision-free domain for this test run — never a fixed string two runs could race on. */
	protected function random_domain( $prefix ) {
		return $prefix . '-' . bin2hex( random_bytes( 4 ) ) . '.example.com';
	}

	protected function make_client( $api_key, $product_secret ) {
		return new Client(
			new Config( $api_key, $product_secret, $this->credentials()->base_url() ),
			new WpOptionsCredentialStore( $api_key )
		);
	}

	/**
	 * The direct replacement for every script's own check()/global-$failures
	 * function: same signature (label, condition, optional detail), but a
	 * real PHPUnit assertion instead of a printed line and a counter.
	 *
	 * One deliberate behaviour change from the original scripts, not a
	 * weakening: check() logged a PASS/FAIL line for every single
	 * assertion and kept going even after a failure, so one bad step
	 * didn't hide the rest. assertCheck() stops the test method at the
	 * first failure, which is normal PHPUnit behaviour — and arguably
	 * more correct here, since these scripts are sequential narratives
	 * where a later step assumes an earlier one actually succeeded
	 * (continuing to "register a second time" after registration itself
	 * failed was never a meaningful check).
	 */
	protected function assertCheck( $label, $condition, $detail = '' ) {
		$this->assertTrue( (bool) $condition, $label . ( '' !== $detail ? " — {$detail}" : '' ) );
	}

	protected function org_panel_client() {
		$credentials = $this->credentials();
		$client      = new OrgPanelClient( $credentials->base_url() );

		if ( ! $client->login( $credentials->org_email(), $credentials->org_password() ) ) {
			$this->markTestSkipped(
				'Could not log in to the Org Panel as ' . $credentials->org_email() . ' — ' .
				'set APPNECK_SDK_TEST_ORG_EMAIL/APPNECK_SDK_TEST_ORG_PASSWORD, or check the dev seed data.'
			);
		}

		return $client;
	}

	/** Skips the current test if fixture-authoring prerequisites (org id, product id) aren't set. */
	protected function require_fixture_authoring() {
		if ( ! $this->credentials()->can_author_fixtures() ) {
			$this->markTestSkipped(
				'APPNECK_SDK_TEST_ORGANIZATION_ID and APPNECK_SDK_TEST_PRODUCT_ID are required to author ' .
				'this test\'s fixtures through the real Org Panel API. See "Integration tests" in the README.'
			);
		}
	}
}
