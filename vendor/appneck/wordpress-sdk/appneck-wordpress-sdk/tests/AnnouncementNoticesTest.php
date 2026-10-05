<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\AnnouncementNotices;
use Appneck\Sdk\Announcements;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\RealtimeConfig;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * What the site owner sees, and what a Dismiss click does.
 */
class AnnouncementNoticesTest extends TestCase {

	const API_KEY        = 'pk_announcement_notices_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-abcabcabcabc';
	const BASE_URL       = 'https://api.example.test';

	const KEY = 'noticekey123';

	const SECURITY_ID = 'aaaaaaaa-1111-7111-8111-111111111111';
	const FEATURE_ID  = 'bbbbbbbb-2222-7222-8222-222222222222';
	const DISCOUNT_ID = 'cccccccc-3333-7333-8333-333333333333';
	const UPDATE_ID   = 'dddddddd-4444-7444-8444-444444444444';

	/** @var QueueingTransport */
	private $transport;

	/** @var Announcements */
	private $announcements;

	/** @var AnnouncementNotices */
	private $notices;

	/** @var array<int, string> */
	private $redirects = array();

	/** @var RealtimeConfig */
	private $realtime_config;

	/** @var Client */
	private $fast_client;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/wp-menu-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options']    = array();
		$GLOBALS['appneck_test_transients'] = array();
		appneck_test_reset_admin();

		$this->transport = new QueueingTransport();
		$this->redirects = array();

		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
			$this->transport
		);

		$this->realtime_config = new RealtimeConfig( self::KEY );
		$this->fast_client     = $client;

		$this->announcements = new Announcements( $client, new RecordingLogger(), $this->realtime_config );
		$this->notices       = new AnnouncementNotices( $this->announcements, self::KEY, $this->realtime_config, $this->fast_client );
		$this->notices->set_redirect_handler(
			function ( $url ) {
				$this->redirects[] = $url;
			}
		);

		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	/** @param array<int, array<string, mixed>>|null $announcements */
	private function seed( ?array $announcements = null ): void {
		$payload = null !== $announcements ? $announcements : array(
			array(
				'id'    => self::SECURITY_ID,
				'type'  => 'security',
				'title' => 'Security release 2.4.1',
				'body'  => "Please update.\nDetails in the changelog.",
			),
			array(
				'id'    => self::FEATURE_ID,
				'type'  => 'feature',
				'title' => 'Bulk export is here',
				'body'  => '',
			),
		);

		$this->transport->queue(
			Response::from_http( 200, array(), json_encode( array( 'announcements' => $payload ) ) )
		);

		$this->announcements->refresh();
	}

	private function render(): string {
		ob_start();
		$this->notices->render();

		return (string) ob_get_clean();
	}

	/** @param string $id */
	private function dismiss( $id ) {
		$_POST = array(
			'action'                    => $this->notices->action(),
			AnnouncementNotices::FIELD  => $id,
			'_wpnonce'                  => 'nonce-for-' . $this->notices->action(),
		);

		return $this->notices->handle_dismiss();
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	public function test_it_renders_each_announcement_as_a_wordpress_notice(): void {
		$this->seed();

		$html = $this->render();

		$this->assertStringContainsString( 'Security release 2.4.1', $html );
		$this->assertStringContainsString( 'Bulk export is here', $html );
		$this->assertStringContainsString( 'class="notice notice-error appneck-sdk-announcement"', $html );
		$this->assertStringContainsString( 'class="notice notice-info appneck-sdk-announcement"', $html );
	}

	public function test_the_type_decides_the_notice_class(): void {
		$this->seed(
			array(
				array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => 'S', 'body' => '' ),
				array( 'id' => self::UPDATE_ID, 'type' => 'update', 'title' => 'U', 'body' => '' ),
				array( 'id' => self::FEATURE_ID, 'type' => 'feature', 'title' => 'F', 'body' => '' ),
				array( 'id' => self::DISCOUNT_ID, 'type' => 'discount', 'title' => 'D', 'body' => '' ),
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'notice-info', $html );
		// Four announcements, but only MAX_VISIBLE print.
		$this->assertSame( AnnouncementNotices::MAX_VISIBLE, substr_count( $html, 'class="notice ' ) );
	}

	public function test_an_unknown_type_renders_neutrally_never_as_urgent(): void {
		$this->seed(
			array( array( 'id' => self::UPDATE_ID, 'type' => 'from_the_future', 'title' => 'X', 'body' => '' ) )
		);

		$html = $this->render();

		// Checked against the notice div's own class attribute (space
		// before the type class, matching how the attribute is actually
		// written) rather than anywhere in the string — the style
		// block's CSS necessarily mentions every type's class name
		// (concatenated with a dot, no space) so it can style whichever
		// one is actually present, and that must not be mistaken for
		// this one rendering as urgent.
		$this->assertStringContainsString( 'class="notice notice-info appneck-sdk-announcement"', $html );
		$this->assertStringNotContainsString( 'class="notice notice-error', $html );
	}

	public function test_the_most_urgent_announcement_prints_first(): void {
		$this->seed(
			array(
				array( 'id' => self::DISCOUNT_ID, 'type' => 'discount', 'title' => 'A discount', 'body' => '' ),
				array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => 'A security notice', 'body' => '' ),
			)
		);

		$html = $this->render();

		$this->assertLessThan(
			strpos( $html, 'A discount' ),
			strpos( $html, 'A security notice' ),
			'a security notice must never be queued behind a discount'
		);
	}

	/**
	 * The zero case: nothing at all, not an empty box. A plugin calls this
	 * unconditionally from its settings page.
	 */
	public function test_nothing_is_printed_when_there_is_nothing_to_show(): void {
		$this->seed( array() );

		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_is_printed_before_the_first_fetch(): void {
		// No refresh at all, and the transport has nothing queued — the
		// fallback attempt fails and must leave the page untouched.
		$this->assertSame( '', $this->render() );
	}

	public function test_nothing_is_printed_for_a_user_who_cannot_administer_the_site(): void {
		$this->seed();

		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->assertSame( '', $this->render() );
	}

	/**
	 * The body is display-only per journal 12.2. Remote HTML must not reach
	 * wp-admin, but real line breaks should survive.
	 */
	public function test_the_body_is_escaped_but_keeps_its_line_breaks(): void {
		$this->seed(
			array(
				array(
					'id'    => self::SECURITY_ID,
					'type'  => 'security',
					'title' => 'Careful',
					'body'  => "Line one\nLine two <script>alert(1)</script>",
				),
			)
		);

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '<br', $html );
	}

	public function test_the_title_is_escaped(): void {
		$this->seed(
			array( array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => '<img src=x onerror=1>', 'body' => '' ) )
		);

		$html = $this->render();

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;img', $html );
	}

	/**
	 * Core's own X is added by core JS to `.notice.is-dismissible` and only
	 * hides the box for that page view — the opposite of a stored
	 * dismissal. One honest control beats two where one lies.
	 */
	public function test_it_does_not_use_cores_view_only_dismiss_button(): void {
		$this->seed();

		$html = $this->render();

		$this->assertStringNotContainsString( 'is-dismissible', $html );
		$this->assertStringContainsString( 'Dismiss', $html );
	}

	public function test_the_dismiss_control_posts_with_a_nonce(): void {
		$this->seed();

		$html = $this->render();

		$this->assertStringContainsString( '<form method="post"', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertStringContainsString( 'value="' . self::SECURITY_ID . '"', $html );
	}

	public function test_the_action_is_namespaced_per_product(): void {
		$other = new AnnouncementNotices( $this->announcements, 'another-key' );

		$this->assertNotSame( $this->notices->action(), $other->action() );
	}

	/**
	 * Nothing may hook admin_notices globally: another plugin's screen is
	 * no place for this product's discount.
	 */
	public function test_registering_hooks_does_not_print_anywhere_by_itself(): void {
		require_once __DIR__ . '/wp-hook-polyfill.php';

		$GLOBALS['appneck_test_hooks'] = array();

		$this->notices->register_hooks();

		$this->assertArrayNotHasKey( 'admin_notices', $GLOBALS['appneck_test_hooks'] );
		$this->assertArrayHasKey( 'admin_post_' . $this->notices->action(), $GLOBALS['appneck_test_hooks'] );
	}

	public function test_render_on_screen_is_what_opts_in(): void {
		require_once __DIR__ . '/wp-hook-polyfill.php';

		$GLOBALS['appneck_test_hooks'] = array();

		$this->notices->render_on_screen( 'settings_page_acme' );

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['appneck_test_hooks'] );
	}

	// -----------------------------------------------------------------
	// Dismissal
	// -----------------------------------------------------------------

	public function test_dismissing_hides_it_and_redirects_back(): void {
		$this->seed();

		$this->assertSame( self::SECURITY_ID, $this->dismiss( self::SECURITY_ID ) );
		$this->assertTrue( $this->announcements->is_dismissed( self::SECURITY_ID ) );
		$this->assertSame( array( 'https://example.test/wp-admin/options-general.php' ), $this->redirects );

		$html = $this->render();

		$this->assertStringNotContainsString( 'Security release 2.4.1', $html );
		$this->assertStringContainsString( 'Bulk export is here', $html );
	}

	/**
	 * The behaviour this whole feature turns on.
	 */
	public function test_a_dismissed_announcement_does_not_come_back_after_a_refresh(): void {
		$this->seed();
		$this->dismiss( self::SECURITY_ID );

		// The server still serves it — nothing expired.
		$this->seed();

		$this->assertStringNotContainsString( 'Security release 2.4.1', $this->render() );
	}

	public function test_dismissing_the_last_one_leaves_an_empty_page_not_an_empty_box(): void {
		$this->seed();

		$this->dismiss( self::SECURITY_ID );
		$this->dismiss( self::FEATURE_ID );

		$this->assertSame( '', $this->render() );
	}

	public function test_a_user_without_the_capability_cannot_dismiss(): void {
		$this->seed();

		$GLOBALS['appneck_test_admin']['can'] = false;

		$this->assertNull( $this->dismiss( self::SECURITY_ID ) );
		$this->assertFalse( $this->announcements->is_dismissed( self::SECURITY_ID ) );
		$this->assertNotNull( $this->notices->denied );
	}

	public function test_a_bad_nonce_is_refused(): void {
		$this->seed();

		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;

		$this->assertNull( $this->dismiss( self::SECURITY_ID ) );
		$this->assertFalse( $this->announcements->is_dismissed( self::SECURITY_ID ) );
	}

	/**
	 * The likeliest cause is a stale page whose announcement has since
	 * expired. The owner's click did what they wanted either way, so this
	 * redirects rather than dying on them.
	 */
	public function test_an_unknown_id_redirects_instead_of_dying(): void {
		$this->seed();

		$this->assertNull( $this->dismiss( 'not-a-real-id' ) );
		$this->assertCount( 1, $this->redirects );
		$this->assertSame( array(), $this->announcements->dismissed() );
	}

	public function test_a_missing_id_changes_nothing(): void {
		$this->seed();

		$_POST = array( 'action' => $this->notices->action() );

		$this->assertNull( $this->notices->handle_dismiss() );
		$this->assertSame( array(), $this->announcements->dismissed() );
	}

	// -----------------------------------------------------------------
	// render_globally() — the widened default (Layer 2/3, Part 5/9)
	// -----------------------------------------------------------------

	private function render_from_cache_only(): string {
		ob_start();
		$this->notices->render_from_cache_only();

		return (string) ob_get_clean();
	}

	/**
	 * A regression pin for a bug live E2E testing against a real
	 * WordPress site caught and the polyfilled suite could not: an
	 * earlier version set LOCK_TTL_SECONDS to 65 (over the 60-second
	 * poll interval), so a lock a page-load refresh took could still be
	 * held when the very next poll tick tried to pull a genuinely urgent
	 * notice, silently delaying it. Nothing in a fast, sequential-call
	 * test suite exercises real elapsed time, so this only surfaces as
	 * an explicit invariant check.
	 */
	public function test_the_lock_ttl_is_shorter_than_the_poll_interval(): void {
		$this->assertLessThan(
			AnnouncementNotices::POLL_INTERVAL_SECONDS,
			AnnouncementNotices::LOCK_TTL_SECONDS,
			'a lock that outlives the poll interval can block the very next poll tick from pulling an urgent notice'
		);
	}

	public function test_render_globally_hooks_admin_notices_and_admin_footer_globally(): void {
		require_once __DIR__ . '/wp-hook-polyfill.php';
		$GLOBALS['appneck_test_hooks'] = array();

		$this->notices->render_globally();

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['appneck_test_hooks'] );
		$this->assertArrayHasKey( 'admin_footer', $GLOBALS['appneck_test_hooks'] );
	}

	/**
	 * Part 3 rule 2: rendering is NEVER blocked by a network call. The
	 * cache-only path must make literally zero requests, on any input.
	 */
	public function test_render_from_cache_only_never_touches_the_network(): void {
		$this->seed();

		$before = $this->transport->count();
		$this->render_from_cache_only();

		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_render_from_cache_only_prints_the_container_even_when_empty(): void {
		// Nothing seeded at all.
		$html = $this->render_from_cache_only();

		$this->assertStringContainsString( 'id="appneck-sdk-announcements-' . self::KEY . '"', $html );
	}

	public function test_render_from_cache_only_prints_visible_announcements(): void {
		$this->seed();

		$html = $this->render_from_cache_only();

		$this->assertStringContainsString( 'Security release 2.4.1', $html );
		$this->assertStringContainsString( 'data-appneck-dismiss="' . self::SECURITY_ID . '"', $html );
	}

	/**
	 * Rule 7: only a capable user triggers anything. A subscriber must
	 * see nothing and cause no request.
	 */
	public function test_a_non_capable_user_gets_nothing_from_the_global_render(): void {
		$this->seed();
		$GLOBALS['appneck_test_admin']['can'] = false;

		$before = $this->transport->count();
		$html   = $this->render_from_cache_only();

		$this->assertSame( '', $html );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_print_refresh_script_prints_nothing_for_a_non_capable_user(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;

		ob_start();
		$this->notices->print_refresh_script();
		$html = ob_get_clean();

		$this->assertSame( '', $html );
	}

	/**
	 * The script itself never makes a network call — printing it is a
	 * pure string echo. All three AJAX actions are named inside it,
	 * proving the browser is told to call THIS site, never Appneck
	 * directly (rule 1).
	 */
	public function test_print_refresh_script_makes_no_network_call_and_names_only_same_site_actions(): void {
		$before = $this->transport->count();

		ob_start();
		$this->notices->print_refresh_script();
		$html = ob_get_clean();

		$this->assertSame( $before, $this->transport->count() );
		$this->assertStringContainsString( $this->notices->refresh_action(), $html );
		$this->assertStringContainsString( $this->notices->poll_action(), $html );
		$this->assertStringContainsString( $this->notices->dismiss_ajax_action(), $html );
		$this->assertStringNotContainsString( self::BASE_URL, $html, 'the script must never carry an Appneck URL, only admin-ajax.php' );
	}

	public function test_print_refresh_script_pauses_on_visibilitychange_and_has_an_idle_timeout(): void {
		ob_start();
		$this->notices->print_refresh_script();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'visibilitychange', $html );
		$this->assertStringContainsString( 'idleTimeoutMs', $html );
		$this->assertStringContainsString( (string) ( AnnouncementNotices::IDLE_TIMEOUT_SECONDS * 1000 ), $html );

		// The rule this whole layer exists to enforce, present as an
		// actual guard in the shipped code, not merely asserted by intent:
		// a non-urgent version change must not trigger a pull, and the
		// guard is keyed on the version already refreshed for, not on
		// the container's own data-has-urgent attribute (that version
		// latched permanently once tripped and never reset — see
		// test_the_poll_urgent_guard_is_keyed_on_version_not_the_dom_attribute).
		$this->assertStringContainsString( 'hasUrgent && version !== refreshedForVersion', $html );
	}

	/**
	 * The eCab-shaped bug for THIS endpoint, found by a randomized-delay
	 * repeat of E2E scenario A: once any urgent notice had ever shown
	 * once, the OLD guard (container.getAttribute("data-has-urgent") !==
	 * "1") latched permanently — nothing ever reset the attribute back
	 * to "0" without a navigation, so a second, later, unrelated urgent
	 * announcement was silently skipped for the rest of that tab's life.
	 * Asserting against the shipped script string, the same way the
	 * test above does, since this bug lives entirely in inline JS a
	 * PHPUnit process cannot execute.
	 */
	public function test_the_poll_urgent_guard_is_keyed_on_version_not_the_dom_attribute(): void {
		ob_start();
		$this->notices->print_refresh_script();
		$html = ob_get_clean();

		$this->assertStringNotContainsString(
			'container.getAttribute("data-has-urgent") !== "1"',
			$html,
			'the old DOM-attribute latch must not come back — it never resets once tripped'
		);
		$this->assertStringContainsString( 'refreshedForVersion = refreshingForVersion', $html );
	}

	// -----------------------------------------------------------------
	// handle_refresh_ajax()
	// -----------------------------------------------------------------

	private function ajax_post( array $post ) {
		$_POST = $post;
	}

	public function test_refresh_ajax_requires_capability(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$this->assertNull( $this->notices->handle_refresh_ajax() );
		$this->assertNotNull( $this->notices->denied );
	}

	public function test_refresh_ajax_requires_a_valid_nonce(): void {
		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$this->assertNull( $this->notices->handle_refresh_ajax() );
	}

	/**
	 * A real bug: this markup used to carry the literal `notice` class
	 * WordPress core's own wp-admin/js/common.js relocates (once, on
	 * page ready) to right after the screen's <h1>. Because this HTML
	 * is injected via AJAX on every page-load refresh, a response
	 * arriving after core's one-time relocation already ran left the
	 * ORIGINAL notice sitting after the <h1> and the FRESHLY-injected
	 * one sitting in the container's original position -- two visible
	 * copies of the same notice. Asserting against the AJAX response's
	 * own markup, not just render()'s (the unaffected opt-in path,
	 * covered by the class="notice ..." tests above).
	 */
	public function test_the_ajax_refresh_markup_never_carries_the_literal_notice_class_core_relocates(): void {
		$this->seed();

		$result = $this->notices->handle_refresh_ajax();

		$this->assertStringNotContainsString( '"notice ', $result['html'], 'core relocates any literal "notice" class once, splitting this element from a later AJAX-refreshed copy' );
		$this->assertStringContainsString( 'notice-error appneck-sdk-announcement', $result['html'], 'the notice-error class itself must still be present for this component\'s own styling' );
	}

	public function test_refresh_ajax_fetches_when_stale_and_returns_html(): void {
		$this->realtime_config->note_version( 1 );
		$this->realtime_config->note_version( 2 ); // marks stale

		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				json_encode( array( 'announcements' => array(
					array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => 'Fresh notice', 'body' => '' ),
				) ) )
			)
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$result = $this->notices->handle_refresh_ajax();

		$this->assertStringContainsString( 'Fresh notice', $result['html'] );
		$this->assertFalse( $this->realtime_config->is_stale(), 'a successful refresh must clear staleness' );
	}

	/**
	 * The exact scenario Part 9 names for this endpoint too: ten
	 * concurrent calls, one upstream request.
	 */
	public function test_refresh_ajax_single_flight_ten_concurrent_calls_produce_one_upstream_request(): void {
		$this->realtime_config->note_version( 1 );
		$this->realtime_config->note_version( 2 ); // marks stale, so every caller WANTS to refresh

		$this->transport->queue(
			Response::from_http( 200, array(), json_encode( array( 'announcements' => array() ) ) )
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->notices->handle_refresh_ajax();
		}

		$this->assertSame( 1, $this->transport->count(), 'ten concurrent refresh calls must produce exactly one upstream request' );
	}

	public function test_refresh_ajax_skips_the_network_on_an_open_circuit(): void {
		$this->seed(); // warms the announcements cache
		$this->realtime_config->note_version( 1 );
		$this->realtime_config->note_version( 2 ); // stale, so it WOULD refresh

		$this->realtime_config->record_failure();
		$this->realtime_config->record_failure();
		$this->realtime_config->record_failure();

		$before = $this->transport->count();
		$this->ajax_post( array( 'nonce' => 'x' ) );
		$result = $this->notices->handle_refresh_ajax();

		$this->assertSame( $before, $this->transport->count(), 'an open circuit must not attempt a refresh' );
		$this->assertStringContainsString( 'Security release 2.4.1', $result['html'], 'the cached announcements must still be returned' );
	}

	public function test_refresh_ajax_does_not_fetch_when_nothing_is_stale_and_the_cache_is_fresh(): void {
		$this->seed();

		$before = $this->transport->count();
		$this->ajax_post( array( 'nonce' => 'x' ) );
		$this->notices->handle_refresh_ajax();

		$this->assertSame( $before, $this->transport->count() );
	}

	/**
	 * The Part 2/eCab-shaped check for THIS endpoint: a warm cache
	 * holding OLD content must not gate the network call once the
	 * version marker says the site is stale — unlike the survey bug,
	 * Announcements::refresh() has no cache-freshness check of its own
	 * to bypass, so this is confirming that absence, not fixing a gate.
	 */
	public function test_refresh_ajax_replaces_a_warm_cache_holding_old_content_when_stale(): void {
		$this->seed(); // warms the cache with "Security release 2.4.1" / "Bulk export is here"
		$this->realtime_config->note_version( 1 );
		$this->realtime_config->note_version( 2 ); // marks stale

		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				json_encode(
					array(
						'announcements' => array(
							array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => 'A brand new notice', 'body' => '' ),
						),
					)
				)
			)
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$result = $this->notices->handle_refresh_ajax();

		$this->assertStringContainsString( 'A brand new notice', $result['html'] );
		$this->assertStringNotContainsString( 'Bulk export is here', $result['html'], 'the old cached content must not survive a live refresh' );
	}

	// -----------------------------------------------------------------
	// handle_poll_ajax()
	// -----------------------------------------------------------------

	public function test_poll_ajax_requires_capability(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$this->assertNull( $this->notices->handle_poll_ajax() );
	}

	public function test_poll_ajax_returns_config_version_and_has_urgent(): void {
		$this->transport->queue(
			Response::from_http( 200, array( 'etag' => '"v1"' ), json_encode( array( 'config_version' => 9, 'has_urgent' => true ) ) )
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );

		$result = $this->notices->handle_poll_ajax();

		$this->assertSame( 9, $result['config_version'] );
		$this->assertTrue( $result['has_urgent'] );
	}

	/**
	 * The same single-flight guarantee, for the poll endpoint — Part 3
	 * rule 4 names both the refresh AND the poll explicitly.
	 */
	/**
	 * The Part 2/eCab-shaped check for the poll: a warm `poll_cache`
	 * holding an OLD has_urgent/config_version must not gate the next
	 * network attempt once the single-flight lock has cleared — the
	 * lock rate-limits how OFTEN this fires, it must never become a
	 * cache the poll cannot see past.
	 */
	public function test_poll_ajax_pulls_the_new_payload_when_has_urgent_flips(): void {
		$this->transport->queue(
			Response::from_http( 200, array( 'etag' => '"v1"' ), json_encode( array( 'config_version' => 1, 'has_urgent' => false ) ) )
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );
		$first = $this->notices->handle_poll_ajax();

		$this->assertFalse( $first['has_urgent'] );

		// Simulate the single-flight lock's TTL having elapsed, exactly
		// as a real 60-second tick would — this test cares about the
		// cache, not about re-proving the lock's own cooldown.
		$GLOBALS['appneck_test_transients'] = array();

		$this->transport->queue(
			Response::from_http( 200, array( 'etag' => '"v2"' ), json_encode( array( 'config_version' => 2, 'has_urgent' => true ) ) )
		);
		$second = $this->notices->handle_poll_ajax();

		$this->assertSame( 2, $this->transport->count(), 'the second tick must be a genuine second upstream request' );
		$this->assertSame( 2, $second['config_version'] );
		$this->assertTrue( $second['has_urgent'], 'the flip must reach the caller, not the stale cached poll' );
	}

	public function test_poll_ajax_single_flight_ten_concurrent_calls_produce_one_upstream_request(): void {
		$this->transport->queue(
			Response::from_http( 200, array( 'etag' => '"v1"' ), json_encode( array( 'config_version' => 1, 'has_urgent' => false ) ) )
		);
		$this->ajax_post( array( 'nonce' => 'x' ) );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->notices->handle_poll_ajax();
		}

		$this->assertSame( 1, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// handle_dismiss_ajax()
	// -----------------------------------------------------------------

	public function test_dismiss_ajax_requires_capability(): void {
		$GLOBALS['appneck_test_admin']['can'] = false;
		$this->ajax_post( array( 'nonce' => 'x', 'id' => self::SECURITY_ID ) );

		$this->assertNull( $this->notices->handle_dismiss_ajax() );
	}

	public function test_dismiss_ajax_hides_locally_immediately(): void {
		$this->seed();
		$this->ajax_post( array( 'nonce' => 'x', 'id' => self::SECURITY_ID ) );

		$result = $this->notices->handle_dismiss_ajax();

		$this->assertTrue( $result['dismissed'] );
		$this->assertTrue( $this->announcements->is_dismissed( self::SECURITY_ID ) );
	}

	/**
	 * §6.3: local dismissal succeeds and stays hidden even when the
	 * best-effort remote report fails — the SDK never has a client
	 * configured for /sdk/v1/announcements/{id}/dismiss in this test's
	 * transport queue, so that POST hits QueueingTransport's
	 * "nothing queued" transport error. The local dismissal must be
	 * unaffected.
	 */
	public function test_dismiss_ajax_succeeds_locally_even_when_the_remote_report_fails(): void {
		$this->seed();
		// Deliberately nothing queued in $this->transport for the
		// dismiss POST — it will fail as a transport error.
		$this->ajax_post( array( 'nonce' => 'x', 'id' => self::SECURITY_ID ) );

		$result = $this->notices->handle_dismiss_ajax();

		$this->assertTrue( $result['dismissed'] );
		$this->assertTrue( $this->announcements->is_dismissed( self::SECURITY_ID ) );
	}

	public function test_dismiss_ajax_with_no_fast_client_still_dismisses_locally(): void {
		$this->seed();

		$notices = new AnnouncementNotices( $this->announcements, self::KEY, $this->realtime_config, null );
		$_POST   = array( 'nonce' => 'x', 'id' => self::SECURITY_ID );

		$result = $notices->handle_dismiss_ajax();

		$this->assertTrue( $result['dismissed'] );
		$this->assertTrue( $this->announcements->is_dismissed( self::SECURITY_ID ) );
	}

	public function test_dismiss_ajax_reports_false_for_an_unknown_id_without_dying(): void {
		$this->seed();
		$this->ajax_post( array( 'nonce' => 'x', 'id' => 'not-a-real-id' ) );

		$result = $this->notices->handle_dismiss_ajax();

		$this->assertFalse( $result['dismissed'] );
	}
}
