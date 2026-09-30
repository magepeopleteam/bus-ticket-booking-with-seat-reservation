<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\ConsentNotice;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Consent;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * The prompt itself: when it appears, what it renders, and what a click
 * on it actually does.
 */
class ConsentNoticeTest extends TestCase {

	const API_KEY        = 'pk_notice_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-cccccccccccc';
	const BASE_URL       = 'https://api.example.test';

	/** @var QueueingTransport */
	private $transport;

	/** @var Consent */
	private $consent;

	/** @var Telemetry */
	private $telemetry;

	/** @var ArrayEventQueue */
	private $queue;

	/** @var ConsentNotice */
	private $notice;

	/** @var array<int, string> */
	private $redirects = array();

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-cron-polyfill.php';
		require_once __DIR__ . '/wp-filter-polyfill.php';
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options'] = array();
		$GLOBALS['appneck_test_cron']    = array();
		appneck_test_clear_filters();
		appneck_test_reset_admin();

		$this->transport = new QueueingTransport();
		$this->queue     = new ArrayEventQueue();
		$this->redirects = array();

		$client          = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$this->transport
		);
		$this->telemetry = new Telemetry( $client, $this->queue, new RecordingLogger() );
		$this->consent   = new Consent( $client, $this->telemetry, new RecordingLogger() );
		$this->telemetry->set_consent( $this->consent );

		$this->notice = new ConsentNotice( $this->consent, array( 'product_name' => 'Acme Bookings' ) );
		$this->notice->set_redirect_handler(
			function ( $url ) {
				$this->redirects[] = $url;
			}
		);

		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	private function ok(): Response {
		return Response::from_http( 200, array(), json_encode( array( 'consent_status' => 'accepted' ) ) );
	}

	private function render(): string {
		ob_start();
		$this->notice->render();

		return (string) ob_get_clean();
	}

	private function render_settings(): string {
		ob_start();
		$this->notice->render_settings_section();

		return (string) ob_get_clean();
	}

	/** @param string $status accepted|rejected */
	private function click( $status ) {
		$_POST = array(
			'action'            => $this->notice->action(),
			ConsentNotice::FIELD => $status,
			'_wpnonce'          => 'nonce-for-' . $this->notice->action(),
		);

		return $this->notice->handle();
	}

	// -----------------------------------------------------------------
	// When it appears
	// -----------------------------------------------------------------

	public function test_the_prompt_shows_while_the_question_is_unanswered(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Acme Bookings', $html );
		$this->assertStringContainsString( 'notice notice-info', $html );
		$this->assertStringContainsString( 'value="accepted"', $html );
		$this->assertStringContainsString( 'value="rejected"', $html );
	}

	public function test_the_prompt_is_not_dismissible(): void {
		// A dismiss button would be a third answer meaning neither yes nor
		// no, leaving the state where collection continues.
		$this->assertStringNotContainsString( 'is-dismissible', $this->render() );
	}

	public function test_the_prompt_posts_rather_than_linking(): void {
		$html = $this->render();

		$this->assertStringContainsString( '<form method="post"', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_the_action_is_namespaced_per_product(): void {
		$other = new ConsentNotice(
			new Consent(
				new Client(
					new Config( 'pk_another_product', self::PRODUCT_SECRET, self::BASE_URL ),
					new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
					$this->transport
				)
			)
		);

		$this->assertNotSame(
			$this->notice->action(),
			$other->action(),
			'a shared action would mean one plugin answering for every other'
		);
	}

	public function test_the_prompt_disappears_once_answered(): void {
		$this->transport->queue( $this->ok() );
		$this->click( 'accepted' );

		$this->assertSame( '', $this->render() );
	}

	public function test_the_prompt_disappears_after_a_rejection_too(): void {
		$this->transport->queue( $this->ok() );
		$this->click( 'rejected' );

		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_is_shown_to_a_user_who_cannot_decide(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->assertSame( '', $this->render() );
		$this->assertSame( '', $this->render_settings() );
	}

	public function test_the_privacy_policy_link_is_rendered_when_set(): void {
		$this->notice->set_privacy_policy_url( 'https://acme.test/privacy' );

		$this->assertStringContainsString( 'https://acme.test/privacy', $this->render() );
	}

	public function test_the_product_name_is_escaped(): void {
		$this->notice->set_product_name( 'Acme <script>alert(1)</script>' );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_a_policy_change_brings_the_prompt_back_with_different_wording(): void {
		$this->consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$this->click( 'accepted' );

		$this->assertSame( '', $this->render() );

		$this->consent->set_privacy_policy_version( '2.0' );
		$html = $this->render();

		$this->assertStringContainsString( 'privacy policy has been updated', $html );
	}

	// -----------------------------------------------------------------
	// Clicking it
	// -----------------------------------------------------------------

	public function test_accept_records_the_decision_and_redirects_back(): void {
		$this->transport->queue( $this->ok() );

		$this->assertSame( 'accepted', $this->click( 'accepted' ) );
		$this->assertTrue( $this->consent->is_accepted() );
		$this->assertSame( 1, $this->transport->count() );
		$this->assertSame( array( 'https://example.test/wp-admin/options-general.php' ), $this->redirects );
	}

	public function test_reject_stops_collection_immediately(): void {
		$this->telemetry->track( 'queued_while_pending' );

		$this->transport->queue( $this->ok() );

		$this->assertSame( 'rejected', $this->click( 'rejected' ) );
		$this->assertTrue( $this->consent->is_rejected() );
		$this->assertSame( 0, $this->queue->count() );
		$this->assertFalse( $this->telemetry->track( 'after_refusal' ) );
	}

	public function test_a_click_still_lands_when_the_consent_call_fails(): void {
		// Nothing queued in the transport — the API is unreachable.
		$this->assertSame( 'accepted', $this->click( 'accepted' ) );

		$this->assertTrue( $this->consent->is_accepted() );
		$this->assertTrue( $this->consent->is_sync_pending() );
		$this->assertSame(
			array( 'https://example.test/wp-admin/options-general.php' ),
			$this->redirects,
			'the site owner ends up back on their own page, not on an error'
		);
		$this->assertSame( '', $this->render(), 'and is not asked again' );
	}

	public function test_a_user_without_the_capability_cannot_decide(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->assertNull( $this->click( 'accepted' ) );
		$this->assertTrue( $this->consent->is_pending() );
		$this->assertSame( 0, $this->transport->count() );
		$this->assertNotNull( $this->notice->denied );
	}

	public function test_a_bad_nonce_is_refused(): void {
		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;

		$this->assertNull( $this->click( 'accepted' ) );
		$this->assertTrue( $this->consent->is_pending() );
		$this->assertSame( 0, $this->transport->count() );
	}

	public function test_the_nonce_checked_is_this_products_action(): void {
		$this->transport->queue( $this->ok() );
		$this->click( 'accepted' );

		$this->assertSame( array( $this->notice->action() ), $GLOBALS['appneck_test_admin']['checked'] );
	}

	public function test_an_unrecognised_choice_changes_nothing(): void {
		$this->assertNull( $this->click( 'sure_why_not' ) );
		$this->assertTrue( $this->consent->is_pending() );
		$this->assertSame( 0, $this->transport->count() );
		$this->assertNotNull( $this->notice->denied );
	}

	public function test_a_missing_choice_changes_nothing(): void {
		$_POST = array( 'action' => $this->notice->action() );

		$this->assertNull( $this->notice->handle() );
		$this->assertTrue( $this->consent->is_pending() );
	}

	// -----------------------------------------------------------------
	// Changing the decision later
	// -----------------------------------------------------------------

	public function test_the_settings_section_offers_the_opposite_of_the_current_decision(): void {
		$this->transport->queue( $this->ok() );
		$this->click( 'accepted' );

		$html = $this->render_settings();

		$this->assertStringContainsString( 'You are sharing anonymous usage data', $html );
		$this->assertStringContainsString( 'value="rejected"', $html );
		$this->assertStringNotContainsString( 'value="accepted"', $html );

		$this->transport->queue( $this->ok() );
		$this->click( 'rejected' );

		$html = $this->render_settings();

		$this->assertStringContainsString( 'not sharing usage data', $html );
		$this->assertStringContainsString( 'value="accepted"', $html );
		$this->assertStringNotContainsString( 'value="rejected"', $html );
	}

	public function test_the_settings_section_works_before_any_decision(): void {
		$html = $this->render_settings();

		$this->assertStringContainsString( 'have not decided', $html );
		$this->assertStringContainsString( 'value="accepted"', $html );
	}

	public function test_the_settings_section_says_so_when_the_server_has_not_been_told_yet(): void {
		// No response queued: the decision is stored but unsynced.
		$this->click( 'accepted' );

		$this->assertStringContainsString( 'will be sent to Acme Bookings', $this->render_settings() );
	}

	public function test_changing_from_rejected_to_accepted_unblocks_the_queue(): void {
		$this->transport->queue( $this->ok() );
		$this->click( 'rejected' );

		$this->assertFalse( $this->telemetry->track( 'blocked' ) );

		$this->transport->queue( $this->ok() );
		$this->click( 'accepted' );

		$this->assertTrue( $this->telemetry->track( 'allowed' ) );
		$this->assertSame( 1, $this->queue->count() );
	}
}
