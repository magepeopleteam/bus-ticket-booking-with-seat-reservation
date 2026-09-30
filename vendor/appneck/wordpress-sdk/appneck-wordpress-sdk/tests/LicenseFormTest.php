<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\LicenseForm;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use PHPUnit\Framework\TestCase;

/**
 * The panel a site owner actually clicks: what it prints in each state,
 * and what a click does.
 *
 * Worth testing as thoroughly as the caching logic for the same reason —
 * an admin-post handler that ships with a missing capability check is a
 * permanent hole on every site that installed that version, and the
 * escaping is the only thing between a server-supplied customer name and
 * the site owner's browser.
 */
class LicenseFormTest extends TestCase {

	const API_KEY        = 'pk_license_form_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const BASE_URL       = 'https://api.example.test';

	const KEY = 'lic_0000000000000000000000000000000000000001';

	/** @var QueueingTransport */
	private $transport;

	/** @var ArrayLicenseStore */
	private $store;

	/** @var License */
	private $license;

	/** @var LicenseForm */
	private $form;

	/** @var array<int, string> */
	private $redirects = array();

	protected function setUp(): void {
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';

		$GLOBALS['appneck_test_admin'] = array(
			'can'       => true,
			'nonce_ok'  => true,
			'referer'   => 'https://example.test/wp-admin/options-general.php',
			'redirects' => array(),
			'checked'   => array(),
		);

		$_POST = array();

		$this->transport = new QueueingTransport();
		$this->store     = new ArrayLicenseStore();
		$this->redirects = array();

		$this->license = ( new License(
			new LicenseClient(
				new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
				$this->transport
			),
			$this->store
		) )->set_domain( 'example.test' );

		$this->form = new LicenseForm( $this->license, array( 'product_name' => 'Acme Bookings' ) );

		$redirects = &$this->redirects;
		$this->form->set_redirect_handler(
			function ( $url ) use ( &$redirects ) {
				$redirects[] = $url;
			}
		);
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	private function render(): string {
		ob_start();
		$this->form->render();

		return (string) ob_get_clean();
	}

	/** @param array<string, mixed> $state */
	private function seed( array $state = array() ): void {
		$this->store->write(
			array_merge(
				array(
					'license_key'      => self::KEY,
					'valid'            => true,
					'status'           => 'active',
					'validated_domain' => 'example.test',
					'validated_at'     => time(),
				),
				$state
			)
		);
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	public function test_with_no_license_it_renders_a_key_input_and_an_activate_button(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'name="' . LicenseForm::FIELD_KEY . '"', $html );
		$this->assertStringContainsString( 'Activate', $html );
		$this->assertStringNotContainsString( 'Deactivate', $html );
	}

	public function test_with_a_license_it_renders_the_masked_key_and_a_deactivate_button(): void {
		$this->seed(
			array(
				'expires_at'       => '2027-01-01T00:00:00.000000Z',
				'activation_limit' => 5,
				'activations_used' => 2,
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( '****0001', $html );
		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertStringContainsString( 'Deactivate', $html );
		$this->assertStringContainsString( '2027-01-01', $html );
		$this->assertStringContainsString( '2 of 5', $html );
		$this->assertStringNotContainsString( 'name="' . LicenseForm::FIELD_KEY . '"', $html );
	}

	public function test_a_stale_license_renders_the_unreachable_notice(): void {
		$this->seed( array( 'validated_at' => time() - 90000 ) );
		$this->transport->queue( Response::from_transport_error( 'Operation timed out' ) );
		$this->license->is_valid();

		$html = $this->render();

		$this->assertStringContainsString( 'could not reach the license server', $html );
		// The last known status is still shown, not replaced by an error.
		$this->assertStringContainsString( 'Active on this site', $html );
	}

	public function test_a_rejection_reason_is_rendered_as_a_sentence(): void {
		$this->seed(
			array(
				'valid'  => false,
				'reason' => 'not_activated_on_domain',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'not activated on this domain', $html );
		$this->assertStringContainsString( 'activate it here', $html );
	}

	/**
	 * A reason this SDK has never heard of — journal §23.5's vocabulary is
	 * open-ended, and a status added to the platform later must render
	 * readably on a copy of the SDK that shipped years earlier.
	 */
	public function test_an_unknown_reason_is_rendered_readably_rather_than_dropped(): void {
		$this->seed(
			array(
				'valid'  => false,
				'reason' => 'chargeback_pending',
			)
		);

		$this->assertStringContainsString( 'Chargeback pending.', $this->render() );
	}

	public function test_server_supplied_text_is_escaped(): void {
		$this->seed( array( 'customer_name' => '<script>alert(1)</script>' ) );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_it_prints_nothing_for_a_user_without_manage_options(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->assertSame( '', $this->render() );
	}

	// -----------------------------------------------------------------
	// Handling a click
	// -----------------------------------------------------------------

	public function test_activate_stores_the_key_and_redirects_back(): void {
		$this->transport->queue( Response::from_http( 200, array(), '{"valid":true,"activation_limit":3,"activations_used":1}' ) );

		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_ACTIVATE,
			LicenseForm::FIELD_KEY       => self::KEY,
		);

		$this->assertSame( LicenseForm::OP_ACTIVATE, $this->form->handle() );
		$this->assertTrue( $this->license->has_license() );
		$this->assertSame( array( 'https://example.test/wp-admin/options-general.php' ), $this->redirects );
	}

	public function test_a_rejected_key_is_not_stored_and_the_response_is_available_to_the_host(): void {
		$this->transport->queue( Response::from_http( 200, array(), '{"valid":false,"reason":"license_not_found"}' ) );

		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_ACTIVATE,
			LicenseForm::FIELD_KEY       => self::KEY,
		);

		$this->form->handle();

		$this->assertFalse( $this->license->has_license() );
		$this->assertSame( 'license_not_found', $this->form->last_response->get( 'reason' ) );
	}

	public function test_deactivate_uses_the_stored_key_not_one_from_the_request(): void {
		$this->seed();
		$this->transport->queue( Response::from_http( 200, array(), '{"success":true}' ) );

		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_DEACTIVATE,
			// A crafted form trying to aim this site's button elsewhere.
			LicenseForm::FIELD_KEY       => 'lic_9999999999999999999999999999999999999999',
		);

		$this->assertSame( LicenseForm::OP_DEACTIVATE, $this->form->handle() );

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( self::KEY, $body['license_key'] );
		$this->assertFalse( $this->license->has_license() );
	}

	public function test_a_user_without_manage_options_is_refused(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;

		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_ACTIVATE,
			LicenseForm::FIELD_KEY       => self::KEY,
		);

		$this->assertNull( $this->form->handle() );
		$this->assertSame( 'You are not allowed to change this setting.', $this->form->denied );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_a_bad_nonce_is_refused(): void {
		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;

		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_ACTIVATE,
			LicenseForm::FIELD_KEY       => self::KEY,
		);

		$this->assertNull( $this->form->handle() );
		$this->assertSame( 'That link has expired. Please try again.', $this->form->denied );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_an_unknown_operation_is_refused(): void {
		$_POST = array( LicenseForm::FIELD_OPERATION => 'delete_everything' );

		$this->assertNull( $this->form->handle() );
		$this->assertSame( 'That is not a valid action.', $this->form->denied );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_an_empty_key_is_refused_before_any_request(): void {
		$_POST = array(
			LicenseForm::FIELD_OPERATION => LicenseForm::OP_ACTIVATE,
			LicenseForm::FIELD_KEY       => '   ',
		);

		$this->assertNull( $this->form->handle() );
		$this->assertSame( 'Please enter a license key.', $this->form->denied );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_the_admin_post_action_is_namespaced_per_product(): void {
		$other = new LicenseForm(
			new License(
				new LicenseClient( new Config( 'pk_a_different_product', self::PRODUCT_SECRET, self::BASE_URL ), $this->transport ),
				new ArrayLicenseStore()
			)
		);

		$this->assertNotSame( $this->form->action(), $other->action() );
		$this->assertStringStartsWith( LicenseForm::ACTION_PREFIX, $this->form->action() );
	}
}
