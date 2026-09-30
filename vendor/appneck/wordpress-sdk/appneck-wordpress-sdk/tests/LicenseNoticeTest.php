<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\LicenseNotice;
use Appneck\Sdk\Config;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use PHPUnit\Framework\TestCase;

/**
 * The opt-in unlicensed nag: when it shows, when it must not, screen
 * targeting, and that a dismissal actually persists.
 */
class LicenseNoticeTest extends TestCase {

	const API_KEY        = 'pk_license_notice_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const BASE_URL       = 'https://api.example.test';
	const KEY            = 'lic_0000000000000000000000000000000000000001';

	/** @var ArrayLicenseStore */
	private $store;

	/** @var License */
	private $license;

	/** @var LicenseNotice */
	private $notice;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-hook-polyfill.php';
		require_once __DIR__ . '/wp-menu-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_admin']      = array(
			'can'      => true,
			'nonce_ok' => true,
			'referer'  => 'https://example.test/wp-admin/',
			'checked'  => array(),
		);
		$GLOBALS['appneck_test_options']    = array();
		$GLOBALS['appneck_test_menu']       = array(
			'pages'           => array(),
			'current_screen'  => 'plugins',
			'current_user_id' => 1,
		);
		$_POST = array();

		$this->store = new ArrayLicenseStore();

		$this->license = ( new License(
			new LicenseClient(
				new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
				new QueueingTransport()
			),
			$this->store
		) )->set_domain( 'example.test' );

		$this->notice = new LicenseNotice( $this->license, array( 'product_name' => 'Acme Bookings' ) );
		$this->notice->set_redirect_handler( function ( $url ) {} );
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	private function seed( array $overrides ): void {
		$this->store->write(
			array_merge(
				array(
					'license_key'      => self::KEY,
					'valid'            => false,
					'status'           => null,
					'reason'           => null,
					'customer_name'    => null,
					'expires_at'       => null,
					'activation_limit' => null,
					'activations_used' => null,
					'validated_domain' => 'example.test',
					'failure_domain'   => 'example.test',
					'validated_at'     => 0,
					'failure_count'    => 0,
					'failed_at'        => 0,
					'next_attempt_at'  => 0,
				),
				$overrides
			)
		);
	}

	private function render_on( string $screen_id ): string {
		$GLOBALS['appneck_test_menu']['current_screen'] = $screen_id;
		$this->notice->register_on_screens( array( 'toplevel_page_acme-license', 'plugins' ) );

		ob_start();
		$this->notice->render_if_on_screen();

		return (string) ob_get_clean();
	}

	public function test_it_shows_when_there_is_no_license(): void {
		$html = $this->render_on( 'plugins' );

		$this->assertStringContainsString( 'Activate your license', $html );
		$this->assertStringContainsString( 'Acme Bookings', $html );
	}

	public function test_it_does_not_show_when_the_license_is_valid(): void {
		$this->seed( array( 'valid' => true, 'validated_at' => time() ) );

		$this->assertSame( '', $this->render_on( 'plugins' ) );
	}

	public function test_it_does_not_show_when_unreachable_but_last_known_valid(): void {
		$this->seed(
			array(
				'valid'        => true,
				'validated_at' => time() - 90000,
			)
		);

		$this->assertSame( '', $this->render_on( 'plugins' ) );
	}

	public function test_it_does_not_show_on_an_unregistered_screen(): void {
		$this->assertSame( '', $this->render_on( 'edit.php' ) );
	}

	public function test_it_shows_on_the_plugins_screen_and_the_license_pages_own_screen(): void {
		$this->assertNotSame( '', $this->render_on( 'plugins' ) );
		$this->assertNotSame( '', $this->render_on( 'toplevel_page_acme-license' ) );
	}

	public function test_dismissing_persists_and_suppresses_the_notice(): void {
		$this->notice->register_on_screens( array( 'plugins' ) );
		$_POST = array( 'action' => 'appneck_sdk_license_notice_dismiss_' . $this->license->key() );

		$this->notice->handle_dismiss();

		$this->assertSame( '', $this->render_on( 'plugins' ) );
	}

	public function test_a_bad_nonce_on_dismiss_is_rejected(): void {
		$this->notice->register_on_screens( array( 'plugins' ) );
		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;

		$this->notice->handle_dismiss();

		$this->assertSame( 'That link has expired. Please try again.', $this->notice->denied );
	}

	public function test_insufficient_capability_on_dismiss_is_rejected(): void {
		$this->notice->register_on_screens( array( 'plugins' ) );
		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->notice->handle_dismiss();

		$this->assertSame( 'You are not allowed to change this setting.', $this->notice->denied );
	}
}
