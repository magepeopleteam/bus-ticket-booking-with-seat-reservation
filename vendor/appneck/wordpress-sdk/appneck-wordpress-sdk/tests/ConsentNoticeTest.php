<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\ConsentNotice;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Consent;
use Appneck\Sdk\ContactGate;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\MarketingConsent;
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
		$this->assertStringContainsString( 'notice appneck-sdk-optin', $html );
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

		$this->assertStringContainsString( 'method="post"', $html );
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
	// The prompt's copy and design (journal §70 D2)
	// -----------------------------------------------------------------

	private function notice_with_marketing( array $options = array() ): ConsentNotice {
		$marketing = new MarketingConsent( $this->client() );
		$this->consent->set_marketing_consent( $marketing );

		$notice = new ConsentNotice( $this->consent, array( 'product_name' => 'Acme Bookings' ) + $options, $marketing );
		$notice->set_redirect_handler(
			function ( $url ) {
				$this->redirects[] = $url;
			}
		);

		return $notice;
	}

	private function client(): Client {
		return new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$this->transport
		);
	}

	private function as_admin( $email = 'ada@example.test', $name = 'Ada Lovelace' ): void {
		$GLOBALS['appneck_test_admin']['user'] = (object) array(
			'ID'           => 7,
			'user_email'   => $email,
			'display_name' => $name,
		);
	}

	private function render_notice( ConsentNotice $notice ): string {
		ob_start();
		$notice->render();

		return (string) ob_get_clean();
	}

	private function render_notice_settings( ConsentNotice $notice ): string {
		ob_start();
		$notice->render_settings_section();

		return (string) ob_get_clean();
	}

	private function click_on( ConsentNotice $notice, $field, $value ) {
		$_POST = array(
			'action'   => $notice->action(),
			$field     => $value,
			'_wpnonce' => 'nonce-for-' . $notice->action(),
		);

		return $notice->handle();
	}

	private function last_body(): array {
		return (array) json_decode( (string) $this->transport->last_request()['body'], true );
	}

	public function test_the_prompt_carries_the_agreed_title_body_and_buttons(): void {
		$html = $this->render_notice( $this->notice_with_marketing() );

		$this->assertStringContainsString( 'Never miss an important update', $html );
		$this->assertStringContainsString( 'This helps us make Acme Bookings more compatible with your site', $html );
		$this->assertStringContainsString( 'Allow &amp; Continue', $html );
		$this->assertStringContainsString( '>Skip<', $html );
		$this->assertStringNotContainsString( 'No personal data', $html, 'the old, untrue promise is gone' );
		$this->assertStringNotContainsString( 'type="checkbox"', $html, 'the separate marketing checkbox is gone' );
	}

	public function test_whats_shared_is_hidden_for_now(): void {
		// Commented out in render() at the product owner's request
		// (2026-10-05). When it is restored, bring back the check that every
		// shared_items() line and the footnote are rendered.
		$html = $this->render_notice( $this->notice_with_marketing() );

		$this->assertStringNotContainsString( '<details', $html );
		$this->assertStringNotContainsString( 'What&#039;s shared?', $html );
	}

	public function test_the_product_icon_is_used_when_configured(): void {
		$html = $this->render_notice( $this->notice_with_marketing( array( 'icon_url' => 'https://cdn.example.test/icon.png' ) ) );

		$this->assertStringContainsString( '<img src="https://cdn.example.test/icon.png"', $html );
	}

	public function test_a_neutral_icon_is_used_without_one(): void {
		$html = $this->render_notice( $this->notice_with_marketing() );

		$this->assertStringContainsString( 'appneck-sdk-optin__icon', $html );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_a_premium_build_never_renders_the_prompt_or_the_settings(): void {
		$notice = new ConsentNotice( $this->consent, array( 'product_name' => 'Acme Pro' ), null, new ContactGate( true, $this->consent ) );

		$this->assertSame( '', $this->render_notice( $notice ) );
		$this->assertSame( '', $this->render_notice_settings( $notice ) );
	}

	// -----------------------------------------------------------------
	// Allow & Continue, and Skip
	// -----------------------------------------------------------------

	public function test_allow_and_continue_opts_in_with_the_clicking_admins_own_email_and_name(): void {
		$this->as_admin();
		$GLOBALS['appneck_test_options']['admin_email'] = 'shared-inbox@example.test';
		$notice = $this->notice_with_marketing();

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$body = $this->last_body();
		$this->assertSame( 'accepted', $body['status'] );
		$this->assertTrue( $body['marketing_opt_in'] );
		$this->assertSame( 'ada@example.test', $body['marketing_email'], 'the admin who clicked, not admin_email' );
		$this->assertSame( 'Ada Lovelace', $body['marketing_name'] );
		$this->assertStringContainsString( 'Never miss an important update', $body['marketing_wording'] );
		$this->assertStringContainsString( 'This helps us make Acme Bookings', $body['marketing_wording'] );
		$this->assertSame( 1, $this->transport->count(), 'both decisions in one request' );
	}

	public function test_skip_declines_both_and_keeps_no_name_or_email(): void {
		$this->as_admin();
		$notice = $this->notice_with_marketing();

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'rejected' );

		$this->assertTrue( $this->consent->is_rejected() );
		$this->assertFalse( $this->consent_marketing_state( $notice ) );
	}

	public function test_an_admin_without_an_email_is_not_opted_in_to_anything(): void {
		$this->as_admin( '' );
		$notice = $this->notice_with_marketing();

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$this->assertTrue( $this->consent->is_accepted() );
		$this->assertArrayNotHasKey( 'marketing_opt_in', $this->last_body() );
	}

	public function test_a_reconfirmation_keeps_the_earlier_email_opt_in(): void {
		$this->as_admin();
		$this->consent->set_privacy_policy_version( '1.0' );
		$notice = $this->notice_with_marketing();

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );
		$this->assertTrue( $this->consent_marketing_state( $notice ) );

		$this->consent->set_privacy_policy_version( '2.0' );
		$html = $this->render_notice( $notice );

		$this->assertStringContainsString( 'privacy policy has been updated', $html );
		$this->assertStringContainsString( 'Keep sharing', $html );
		$this->assertStringNotContainsString( "What&#039;s shared?", $html );

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$this->assertTrue( $this->consent_marketing_state( $notice ), 'an unrelated re-confirmation does not touch the email opt-in' );
		$this->assertArrayNotHasKey( 'marketing_opt_in', $this->last_body() );
	}

	// -----------------------------------------------------------------
	// The settings section: two independent switches
	// -----------------------------------------------------------------

	public function test_the_settings_section_shows_both_switches_in_their_current_state(): void {
		$this->as_admin();
		$notice = $this->notice_with_marketing();

		$html = $this->render_notice_settings( $notice );
		$this->assertStringContainsString( 'Share usage data', $html );
		$this->assertStringContainsString( 'Receive update emails', $html );
		$this->assertStringContainsString( 'value="usage_on"', $html );
		$this->assertStringContainsString( 'have not decided', $html );

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$html = $this->render_notice_settings( $notice );
		$this->assertStringContainsString( 'value="usage_off"', $html );
		$this->assertStringContainsString( 'value="emails_off"', $html );
		$this->assertSame( 2, substr_count( $html, 'aria-checked="true"' ) );
	}

	public function test_the_usage_switch_records_only_a_telemetry_decision(): void {
		$this->as_admin();
		$notice = $this->notice_with_marketing();
		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$this->transport->queue( $this->ok() );
		$this->assertSame( 'usage_off', $this->click_on( $notice, ConsentNotice::SETTING_FIELD, 'usage_off' ) );

		$this->assertTrue( $this->consent->is_rejected() );
		$this->assertTrue( $this->consent_marketing_state( $notice ), 'the email opt-in is a separate decision' );
		$this->assertSame( 'rejected', $this->last_body()['status'] );
		$this->assertArrayNotHasKey( 'marketing_opt_in', $this->last_body() );
	}

	public function test_the_email_switch_records_only_a_marketing_decision(): void {
		$this->as_admin();
		$notice = $this->notice_with_marketing();
		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'accepted' );

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::SETTING_FIELD, 'emails_off' );

		$body = $this->last_body();
		$this->assertFalse( $body['marketing_opt_in'] );
		$this->assertArrayNotHasKey( 'status', $body, 'no telemetry decision is re-sent' );
		$this->assertTrue( $this->consent->is_accepted() );

		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::SETTING_FIELD, 'emails_on' );

		$body = $this->last_body();
		$this->assertTrue( $body['marketing_opt_in'] );
		$this->assertSame( 'ada@example.test', $body['marketing_email'] );
		$this->assertStringContainsString( 'Receive update emails', $body['marketing_wording'] );
	}

	public function test_emails_cannot_be_turned_on_while_usage_data_is_off(): void {
		$this->as_admin();
		$notice = $this->notice_with_marketing();
		$this->transport->queue( $this->ok() );
		$this->click_on( $notice, ConsentNotice::FIELD, 'rejected' );

		$html = $this->render_notice_settings( $notice );
		$this->assertStringContainsString( 'Turn on usage data sharing first', $html );
		$this->assertMatchesRegularExpression( '/value="emails_on"[^>]*disabled/', $html );

		$before = $this->transport->count();
		$this->assertNull( $this->click_on( $notice, ConsentNotice::SETTING_FIELD, 'emails_on' ) );
		$this->assertNotNull( $notice->denied );
		$this->assertFalse( $this->consent_marketing_state( $notice ) );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_an_unknown_setting_changes_nothing(): void {
		$notice = $this->notice_with_marketing();

		$this->assertNull( $this->click_on( $notice, ConsentNotice::SETTING_FIELD, 'everything_on' ) );
		$this->assertTrue( $this->consent->is_pending() );
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

	/**
	 * Reads the MarketingConsent wired onto $notice via reflection — there
	 * is no public getter on ConsentNotice by design.
	 */
	private function consent_marketing_state( ConsentNotice $notice ): bool {
		$property = new \ReflectionProperty( ConsentNotice::class, 'marketing_consent' );
		$property->setAccessible( true );

		/** @var MarketingConsent $marketing */
		$marketing = $property->getValue( $notice );

		return $marketing->is_opted_in();
	}
}
