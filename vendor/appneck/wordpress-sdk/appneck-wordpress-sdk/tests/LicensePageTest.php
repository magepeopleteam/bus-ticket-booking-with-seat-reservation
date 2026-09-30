<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\LicenseMessages;
use Appneck\Sdk\Admin\LicensePage;
use Appneck\Sdk\Config;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8's complete license admin page — every state it must render,
 * and every refusal its form handler must enforce.
 *
 * Seeds the store directly (LicenseTest's own convention) rather than
 * queuing HTTP responses: this is a rendering and form-handling suite,
 * not a re-test of License's own caching/backoff logic, which LicenseTest
 * already covers at length.
 */
class LicensePageTest extends TestCase {

	const API_KEY        = 'pk_license_page_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const BASE_URL       = 'https://api.example.test';
	const KEY            = 'lic_0000000000000000000000000000000000000001';

	/** @var ArrayLicenseStore */
	private $store;

	/** @var License */
	private $license;

	/** @var LicensePage */
	private $page;

	/** @var array<int, string> */
	private $redirects = array();

	protected function setUp(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-hook-polyfill.php';
		require_once __DIR__ . '/wp-menu-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_admin'] = array(
			'can'       => true,
			'nonce_ok'  => true,
			'referer'   => 'https://example.test/wp-admin/options-general.php',
			'redirects' => array(),
			'checked'   => array(),
		);
		$GLOBALS['appneck_test_options']     = array();
		$GLOBALS['appneck_test_transients']  = array();
		$GLOBALS['appneck_test_menu']        = array(
			'pages'           => array(),
			'current_screen'  => null,
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

		$this->page = new LicensePage( $this->license, array( 'product_name' => 'Acme Bookings' ) );

		$redirects = &$this->redirects;
		$this->page->set_redirect_handler(
			function ( $url ) use ( &$redirects ) {
				$redirects[] = $url;
			}
		);
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	private function render( array $args = array() ): string {
		$this->page->register( $args );

		ob_start();
		$this->page->render();

		return (string) ob_get_clean();
	}

	/** @param array<string, mixed> $overrides */
	private function seed( array $overrides = array() ): void {
		$this->store->write(
			array_merge(
				array(
					'license_key'      => self::KEY,
					'valid'            => true,
					'status'           => 'active',
					'reason'           => null,
					'customer_name'    => 'Jane Buyer',
					'expires_at'       => null,
					'activation_limit' => 5,
					'activations_used' => 2,
					'validated_domain' => 'example.test',
					'failure_domain'   => 'example.test',
					'validated_at'     => time(),
					'failure_count'    => 0,
					'failed_at'        => 0,
					'next_attempt_at'  => 0,
				),
				$overrides
			)
		);
	}

	// -----------------------------------------------------------------
	// No license
	// -----------------------------------------------------------------

	public function test_no_license_renders_the_activate_form(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Activate', $html );
		$this->assertStringContainsString( 'appneck_sdk_license_page_key', $html );
		$this->assertStringNotContainsString( 'Deactivate', $html );
	}

	public function test_no_license_shows_the_purchase_link_only_when_given(): void {
		$this->assertStringNotContainsString( 'Buy one', $this->render() );

		$html = $this->render( array( 'purchase_url' => 'https://example.com/buy' ) );
		$this->assertStringContainsString( 'https://example.com/buy', $html );
		$this->assertStringContainsString( 'Buy one', $html );
	}

	// -----------------------------------------------------------------
	// Active
	// -----------------------------------------------------------------

	public function test_active_masks_the_key_to_the_last_four_characters_only(): void {
		$this->seed();
		$html = $this->render();

		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertStringContainsString( substr( self::KEY, -4 ), $html );
	}

	public function test_active_shows_sites_used_and_a_deactivate_confirm(): void {
		$this->seed();
		$html = $this->render();

		$this->assertStringContainsString( '2 of 5', $html );
		$this->assertStringContainsString( 'Deactivate', $html );
		$this->assertStringContainsString( 'confirm(', $html );
		$this->assertStringContainsString( 'Jane Buyer', $html );
	}

	public function test_unlimited_activation_limit_renders_as_the_word_unlimited_not_null(): void {
		$this->seed( array( 'activation_limit' => null ) );
		$html = $this->render();

		$this->assertStringContainsString( '2 of Unlimited', $html );
		$this->assertStringNotContainsString( 'of ' . '<', $html );
	}

	public function test_lifetime_license_renders_the_word_lifetime_not_a_date_or_blank(): void {
		$this->seed( array( 'expires_at' => null ) );
		$html = $this->render();

		$this->assertStringContainsString( 'Lifetime', $html );
	}

	public function test_a_dated_expiry_shows_a_real_date_and_a_relative_phrase(): void {
		$this->seed( array( 'expires_at' => gmdate( 'Y-m-d\TH:i:s.000000\Z', strtotime( '+11 months' ) ) ) );
		$html = $this->render();

		$this->assertStringContainsString( 'expires in', $html );
	}

	// -----------------------------------------------------------------
	// Expired / suspended / terminal
	// -----------------------------------------------------------------

	public function test_expired_keeps_the_key_visible_and_offers_renewal(): void {
		$this->seed(
			array(
				'valid'      => false,
				'reason'     => 'expired',
				'expires_at' => gmdate( 'Y-m-d\TH:i:s.000000\Z', strtotime( '-3 days' ) ),
			)
		);

		$html = $this->render( array( 'renew_url' => 'https://example.com/renew' ) );

		$this->assertStringContainsString( substr( self::KEY, -4 ), $html );
		$this->assertStringContainsString( 'https://example.com/renew', $html );
		$this->assertStringContainsString( 'keeps working', $html );
		$this->assertStringNotContainsString( 'Activate', $html );
	}

	public function test_suspended_has_no_activate_button_and_offers_support(): void {
		$this->seed( array( 'valid' => false, 'reason' => 'suspended' ) );

		$html = $this->render( array( 'support_url' => 'https://example.com/support' ) );

		$this->assertStringContainsString( 'suspended', strtolower( $html ) );
		$this->assertStringContainsString( 'https://example.com/support', $html );
		$this->assertStringNotContainsString( '>Activate<', $html );
	}

	public function test_a_license_moved_to_this_domain_explains_that_it_must_be_activated_here(): void {
		$this->seed(
			array(
				'valid'            => true,
				'reason'           => null,
				'validated_domain' => 'old.example',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'domain has changed', strtolower( $html ) );
		$this->assertStringContainsString( 'activate the license here', strtolower( $html ) );
		$this->assertStringContainsString( '>Activate<', $html );
	}

	/** @dataProvider terminalReasons */
	public function test_terminal_reasons_render_distinct_wording( string $reason ): void {
		$this->seed( array( 'valid' => false, 'reason' => $reason ) );

		$html = $this->render();

		$this->assertStringContainsString( ucfirst( $reason ), $html );
	}

	/** @return array<string, array<int, string>> */
	public static function terminalReasons(): array {
		return array(
			'cancelled' => array( 'cancelled' ),
			'refunded'  => array( 'refunded' ),
			'revoked'   => array( 'revoked' ),
		);
	}

	// -----------------------------------------------------------------
	// Unreachable
	// -----------------------------------------------------------------

	public function test_unreachable_with_last_known_valid_is_reassuring_not_an_error(): void {
		$this->seed(
			array(
				'valid'           => true,
				'validated_at'    => time() - 90000, // stale
				'failure_count'   => 1,
				'next_attempt_at' => time() + 300,
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'last known status', $html );
		$this->assertStringContainsString( 'Nothing is broken', $html );
		$this->assertStringNotContainsString( 'Activate', $html );
	}

	public function test_never_validated_and_unreachable_renders_a_different_state(): void {
		$this->seed(
			array(
				'valid'           => null,
				'validated_at'    => 0,
				'failure_count'   => 1,
				'next_attempt_at' => time() + 300,
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'could not verify', strtolower( $html ) );
		$this->assertStringNotContainsString( 'last known status', $html );
	}

	// -----------------------------------------------------------------
	// Reason mapping
	// -----------------------------------------------------------------

	public function test_every_known_reason_maps_to_its_own_message(): void {
		foreach ( LicenseMessages::map() as $reason => $message ) {
			$this->assertSame( $message, LicenseMessages::for_reason( $reason ) );
		}
	}

	public function test_an_unknown_reason_falls_back_safely_instead_of_printing_the_raw_token(): void {
		$this->assertSame(
			'Some new server reason.',
			LicenseMessages::for_reason( 'some_new_server_reason' )
		);
	}

	public function test_an_empty_reason_uses_the_caller_supplied_fallback(): void {
		$this->assertSame( 'Custom fallback.', LicenseMessages::for_reason( null, 'Custom fallback.' ) );
	}

	// -----------------------------------------------------------------
	// Form handling: nonce, capability
	// -----------------------------------------------------------------

	public function test_a_bad_nonce_is_rejected(): void {
		$this->page->register();
		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;
		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'activate' );

		$this->page->handle();

		$this->assertSame( 'That link has expired. Please try again.', $this->page->denied );
		$this->assertSame( array(), $this->redirects );
	}

	public function test_insufficient_capability_is_rejected(): void {
		$this->page->register();
		$GLOBALS['appneck_test_admin']['can'] = false;
		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'activate' );

		$this->page->handle();

		$this->assertSame( 'You are not allowed to change this setting.', $this->page->denied );
		$this->assertSame( array(), $this->redirects );
	}

	public function test_activation_limit_reached_gives_actionable_guidance_not_just_the_bare_reason(): void {
		$this->page->register();

		$transport = new QueueingTransport();
		$transport->queue(
			\Appneck\Sdk\Http\Response::from_http( 200, array(), '{"valid":false,"reason":"activation_limit_reached"}' )
		);
		$license = ( new License(
			new LicenseClient( new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ), $transport ),
			$this->store
		) )->set_domain( 'example.test' );
		$page = new LicensePage( $license, array( 'product_name' => 'Acme Bookings' ) );
		$redirects = array();
		$page->set_redirect_handler(
			function ( $url ) use ( &$redirects ) {
				$redirects[] = $url;
			}
		);
		$page->register();

		$_POST = array(
			'appneck_sdk_license_page_key'       => self::KEY,
			'appneck_sdk_license_page_operation' => 'activate',
		);
		$page->handle();

		$this->assertNotEmpty( $redirects );

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'maximum number of sites', $html );
		$this->assertStringContainsString( 'upgrade', strtolower( $html ) );
	}

	// -----------------------------------------------------------------
	// Where the page lives — the post-submit redirect target
	// -----------------------------------------------------------------

	/**
	 * WordPress serves a submenu page from whichever admin FILE its
	 * parent is, and redirect_back() sends the browser there after every
	 * activate/deactivate. A page parented to a custom-post-type menu
	 * that redirected to admin.php would answer "You do not have
	 * sufficient permissions" — the save having actually succeeded,
	 * which is the worst shape a bug can take. Found against a real
	 * plugin whose whole admin lives under a CPT menu.
	 */
	public function test_a_page_under_a_custom_post_type_menu_redirects_to_that_post_type_not_admin_php(): void {
		$this->seed();
		$this->page->register( array( 'parent' => 'edit.php?post_type=mptbm_rent' ) );

		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'deactivate' );
		$this->page->handle();

		$this->assertNotEmpty( $this->redirects );
		$this->assertStringContainsString( 'edit.php?post_type=mptbm_rent&page=', $this->redirects[0] );
		$this->assertStringNotContainsString( 'admin.php', $this->redirects[0] );
	}

	public function test_a_page_under_a_core_admin_file_redirects_to_that_file(): void {
		$this->seed();
		$this->page->register( array( 'parent' => 'options-general.php' ) );

		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'deactivate' );
		$this->page->handle();

		$this->assertNotEmpty( $this->redirects );
		$this->assertStringContainsString( 'options-general.php?page=', $this->redirects[0] );
	}

	/**
	 * The two cases admin.php genuinely does serve: a top-level page of
	 * our own, and one parented to another plugin's add_menu_page slug
	 * (which is a bare slug, not a .php file).
	 */
	public function test_a_top_level_page_and_a_plugin_slug_parent_both_redirect_to_admin_php(): void {
		$this->seed();
		$this->page->register();

		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'deactivate' );
		$this->page->handle();

		$this->assertStringContainsString( 'admin.php?page=', $this->redirects[0] );

		$this->redirects = array();
		$this->page->register( array( 'parent' => 'some-other-plugin-menu' ) );

		$_POST = array( self::field_key() => self::KEY, self::field_op() => 'deactivate' );
		$this->page->handle();

		$this->assertStringContainsString( 'admin.php?page=', $this->redirects[0] );
	}

	// -----------------------------------------------------------------
	// Menu slug collision
	// -----------------------------------------------------------------

	public function test_two_instances_with_different_api_keys_get_different_menu_slugs(): void {
		$other = ( new License(
			new LicenseClient(
				new Config( 'pk_a_totally_different_key', self::PRODUCT_SECRET, self::BASE_URL ),
				new QueueingTransport()
			),
			new ArrayLicenseStore()
		) );
		$other_page = new LicensePage( $other );

		$this->page->register();
		$other_page->register();

		$this->assertNotSame( $this->page->action(), $other_page->action() );
	}

	private static function field_key(): string {
		return 'appneck_sdk_license_page_key';
	}

	private static function field_op(): string {
		return 'appneck_sdk_license_page_operation';
	}
}
