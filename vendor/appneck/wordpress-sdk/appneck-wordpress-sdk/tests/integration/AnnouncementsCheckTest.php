<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Admin\AnnouncementNotices;
use Appneck\Sdk\Announcements;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Telemetry;
use Appneck\Sdk\Tests\RecordingLogger;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;
use Appneck\Sdk\Tests\Integration\Support\OrgPanelClient;

/**
 * Converted from tests/integration/announcements-check.php (S4.7 audit).
 * Announcements against the REAL backend: a real signed fetch of what a
 * real organization authored, rendered as real admin notices, dismissed
 * through the real admin-post handler — unchanged from the original
 * script's assertions.
 *
 * The original script documented a manual precondition: author 5
 * specific announcements (published security with no window, published
 * discount inside its window, published-but-expired, draft, and
 * published-but-not-yet-started) in the Org Panel before running.
 * setUp()/tearDown() here author and remove all 5 through the real
 * `POST`/`DELETE .../announcements` API instead.
 */
final class AnnouncementsCheckTest extends IntegrationTestCase {

	const TITLE_EXPIRED      = 'This one already expired';
	const TITLE_DRAFT        = 'Still a draft';
	const TITLE_NOT_STARTED  = 'Scheduled for next month';
	const TITLE_SECURITY     = 'Security notice: update recommended';
	const TITLE_DISCOUNT     = 'Limited time discount';

	/** @var array<int, string> Announcement ids created in setUp(), for cleanup. */
	private $created_announcement_ids = array();

	/** @var OrgPanelClient|null */
	private $org_panel = null;

	protected function setUp(): void {
		parent::setUp();
		$this->require_fixture_authoring();
		$this->create_announcements();
	}

	protected function tearDown(): void {
		$this->delete_announcements();
		parent::tearDown();
	}

	public function test_announcements_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's46-announcements' );

		$logger        = new RecordingLogger();
		$client        = $this->make_client( $credentials->api_key(), $credentials->product_secret() );
		$announcements = new Announcements( $client, $logger );
		$key           = substr( hash( 'sha256', $credentials->api_key() ), 0, 32 );
		$notices       = new AnnouncementNotices( $announcements, $key );

		$redirects = array();
		$notices->set_redirect_handler(
			function ( $url ) use ( &$redirects ) {
				$redirects[] = $url;
			}
		);

		// -------------------------------------------------------------
		echo "\n1. Register\n";

		$lifecycle    = new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
		$lifecycle->on_activate();
		$registration = $lifecycle->ensure_registered();

		$this->assertCheck( 'registered (201)', 201 === $registration->status(), 'got ' . $registration->status() . ' — ' . $registration->error_message() );

		$installation_id = ( new WpOptionsCredentialStore( $credentials->api_key() ) )->get_installation_id();
		echo "        installation: {$installation_id}\n";

		// -------------------------------------------------------------
		echo "\n2. No second cron schedule — it rides the heartbeat tick\n";

		// Cron/hooks only — NOT a full reset_wordpress_state(), which would
		// also wipe the credentials this test just registered above.
		$GLOBALS['appneck_test_cron']  = array();
		$GLOBALS['appneck_test_hooks'] = array();

		$announcements->register_hooks();

		$this->assertCheck( 'a listener was added to the heartbeat hook', isset( $GLOBALS['appneck_test_hooks'][ Telemetry::CRON_HOOK ] ) );
		$this->assertCheck( 'and no wp_cron event of its own was created', array() === $GLOBALS['appneck_test_cron'], json_encode( array_keys( $GLOBALS['appneck_test_cron'] ) ) );
		$this->assertCheck( 'nothing hooks admin_notices globally', ! isset( $GLOBALS['appneck_test_hooks']['admin_notices'] ) );

		// -------------------------------------------------------------
		echo "\n3. A real signed fetch of what the organization published\n";

		$fetched = $announcements->refresh();

		$this->assertCheck( 'the fetch succeeded', is_array( $fetched ), 'null — ' . implode( '; ', array_column( $logger->lines, 'message' ) ) );
		$this->assertCheck( 'exactly the two currently-visible announcements came back', 2 === count( $fetched ), (string) count( $fetched ) );

		foreach ( $fetched as $announcement ) {
			echo '        [' . $announcement['type'] . '] ' . $announcement['title'] . "\n";
		}

		$titles = array_column( $fetched, 'title' );

		$this->assertCheck( 'the expired one was excluded server-side', ! in_array( self::TITLE_EXPIRED, $titles, true ) );
		$this->assertCheck( 'the draft was excluded server-side', ! in_array( self::TITLE_DRAFT, $titles, true ) );
		$this->assertCheck( 'the not-yet-started one was excluded server-side', ! in_array( self::TITLE_NOT_STARTED, $titles, true ) );
		echo "        (and the client re-filters nothing — see AnnouncementsTest)\n";

		$security = null;
		$discount = null;

		foreach ( $fetched as $announcement ) {
			if ( 'security' === $announcement['type'] ) {
				$security = $announcement;
			}

			if ( 'discount' === $announcement['type'] ) {
				$discount = $announcement;
			}
		}

		$this->assertCheck( 'the security notice is present', null !== $security );
		$this->assertCheck( 'the discount is present', null !== $discount );

		// -------------------------------------------------------------
		echo "\n4. Rendering on the plugin's own screen\n";

		$html = $this->render( $notices );

		$this->assertCheck( 'both notices printed', false !== strpos( $html, $security['title'] ) && false !== strpos( $html, $discount['title'] ) );
		$this->assertCheck( 'the security notice uses the urgent class', false !== strpos( $html, 'notice notice-error' ) );
		$this->assertCheck( 'the discount does not', false !== strpos( $html, 'notice notice-success' ) );
		$this->assertCheck(
			'the security notice is printed first',
			strpos( $html, $security['title'] ) < strpos( $html, $discount['title'] ),
			'urgency must beat recency'
		);
		$this->assertCheck( 'the body survived with its line break', false !== strpos( $html, '<br' ) );
		$this->assertCheck( 'each carries its own dismiss form', 2 === substr_count( $html, '<form method="post"' ), (string) substr_count( $html, '<form method="post"' ) );
		$this->assertCheck( 'nonce-protected', false !== strpos( $html, 'name="_wpnonce"' ) );

		$before = count( $logger->lines );

		$this->render( $notices );

		$this->assertCheck( 'a second render made no API call', $before === count( $logger->lines ) );

		// -------------------------------------------------------------
		echo "\n5. Dismiss the security notice\n";

		$dismissed = $this->dismiss( $notices, $security['id'] );

		$this->assertCheck( 'the dismissal was applied', $security['id'] === $dismissed, var_export( $dismissed, true ) );
		$this->assertCheck( 'the site owner was redirected back', 1 === count( $redirects ) );

		$html = $this->render( $notices );

		$this->assertCheck( 'it is gone from the screen', false === strpos( $html, $security['title'] ) );
		$this->assertCheck( 'the discount is still there', false !== strpos( $html, $discount['title'] ) );

		// -------------------------------------------------------------
		echo "\n6. Refresh again — the dismissal must survive it\n";

		// The announcement is untouched server-side and still inside its
		// validity window, so the server serves it again. The cache is
		// replaced wholesale; the dismissal lives in its own option,
		// which is the whole point.
		$refetched = $announcements->refresh();

		$this->assertCheck( 'the server still serves it', 2 === count( $refetched ), (string) count( $refetched ) );
		$this->assertCheck( 'it is still recorded as dismissed', $announcements->is_dismissed( $security['id'] ) );

		$html = $this->render( $notices );

		$this->assertCheck( 'and it did NOT come back on screen', false === strpos( $html, $security['title'] ) );
		$this->assertCheck( 'while the discount still shows', false !== strpos( $html, $discount['title'] ) );

		// -------------------------------------------------------------
		echo "\n7. A dismissal cannot be forged, and a stale click does not break\n";

		$GLOBALS['appneck_test_admin']['nonce_ok'] = false;
		$this->assertCheck( 'a bad nonce is refused', null === $this->dismiss( $notices, $discount['id'] ) );
		$this->assertCheck( 'nothing was dismissed', ! $announcements->is_dismissed( $discount['id'] ) );
		$GLOBALS['appneck_test_admin']['nonce_ok'] = true;

		$GLOBALS['appneck_test_admin']['can'] = false;
		$this->assertCheck( 'a user without manage_options is refused', null === $this->dismiss( $notices, $discount['id'] ) );
		$this->assertCheck( 'still nothing dismissed', ! $announcements->is_dismissed( $discount['id'] ) );
		$GLOBALS['appneck_test_admin']['can'] = true;

		$redirects = array();
		$this->assertCheck( 'an unknown id redirects rather than dying', null === $this->dismiss( $notices, 'aaaaaaaa-0000-0000-0000-000000000000' ) );
		$this->assertCheck( 'and still sends them back to their page', 1 === count( $redirects ) );

		// -------------------------------------------------------------
		echo "\n8. Zero announcements renders nothing at all\n";

		if ( $credentials->has_second_product() ) {
			$other_client    = $this->make_client( $credentials->second_api_key(), $credentials->second_product_secret() );
			$other_lifecycle = new Lifecycle( $other_client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
			$other_lifecycle->on_activate();
			$other_registration = $other_lifecycle->ensure_registered();

			$this->assertCheck( 'a second product registered on the same site (201)', 201 === $other_registration->status(), 'got ' . $other_registration->status() );

			$other_announcements = new Announcements( $other_client, $logger );
			$other_notices       = new AnnouncementNotices( $other_announcements, substr( hash( 'sha256', $credentials->second_api_key() ), 0, 32 ) );

			$other_fetched = $other_announcements->refresh();

			$this->assertCheck( "it sees no announcements — not the first product's", array() === $other_fetched, json_encode( $other_fetched ) );
			$this->assertCheck( 'and prints absolutely nothing', '' === $this->render( $other_notices ), 'an empty container would be worse than nothing' );

			$other_lifecycle->on_uninstall();
			$other_announcements->forget();
		} else {
			$this->markTestIncomplete( 'No second product configured (APPNECK_SDK_TEST_SECOND_API_KEY) — the zero-announcements case not exercised.' );
		}

		// -------------------------------------------------------------
		echo "\n9. Deactivated: the endpoint is `active` tier, and the cache is kept\n";

		$deactivation = $lifecycle->on_deactivate();

		$this->assertCheck( 'reported deactivated (200)', null !== $deactivation && 200 === $deactivation->status(), $deactivation ? (string) $deactivation->status() : 'null' );

		$result = $announcements->refresh();

		$this->assertCheck( 'the refresh was refused', null === $result );
		$this->assertCheck( 'the server said 403', 403 === $client->last_response()->status(), (string) $client->last_response()->status() );
		$this->assertCheck( 'the cached copy was NOT blanked', 2 === count( $announcements->all() ), (string) count( $announcements->all() ) );
		$this->assertCheck( 'the failure was logged', $logger->contains( 'the cached copy is kept' ) );
		echo "        (a reactivation a minute later must not have lost the security notice)\n";

		// -------------------------------------------------------------
		echo "\n10. Cleanup\n";

		$lifecycle->on_uninstall();
		$announcements->forget();

		$this->assertCheck( 'the cache and dismissals are gone', array() === $announcements->all() && array() === $announcements->dismissed() );
		echo "        (uninstalled)\n";
	}

	private function render( AnnouncementNotices $notices ) {
		ob_start();
		$notices->render();

		return (string) ob_get_clean();
	}

	/** Exactly what a Dismiss click posts to admin-post.php. */
	private function dismiss( AnnouncementNotices $notices, $id ) {
		$_POST = array(
			'action'                   => $notices->action(),
			AnnouncementNotices::FIELD => $id,
			'_wpnonce'                 => 'nonce-for-' . $notices->action(),
		);

		return $notices->handle_dismiss();
	}

	/**
	 * Authors exactly the 5-announcement fixture the assertions above
	 * depend on: one published-no-window security, one published-in-window
	 * discount, one published-but-expired, one draft, one
	 * published-but-not-yet-started. Windows are computed relative to now
	 * rather than hardcoded dates, so this fixture never goes stale.
	 */
	private function create_announcements() {
		$credentials     = $this->credentials();
		$this->org_panel = $this->org_panel_client();
		$base            = '/app/v1/organizations/' . $credentials->organization_id() . '/products/' . $credentials->product_id() . '/announcements';

		$fixtures = array(
			array(
				'type'   => 'security',
				'title'  => self::TITLE_SECURITY,
				'body'   => "A security update is available.\nPlease upgrade as soon as possible.",
				'status' => 'published',
			),
			array(
				'type'       => 'discount',
				'title'      => self::TITLE_DISCOUNT,
				'body'       => 'Use code SAVE20 at checkout.',
				'status'     => 'published',
				'starts_at'  => gmdate( 'c', strtotime( '-7 days' ) ),
				'expires_at' => gmdate( 'c', strtotime( '+30 days' ) ),
			),
			array(
				'type'       => 'discount',
				'title'      => self::TITLE_EXPIRED,
				'body'       => 'This offer has ended.',
				'status'     => 'published',
				'starts_at'  => gmdate( 'c', strtotime( '-60 days' ) ),
				'expires_at' => gmdate( 'c', strtotime( '-30 days' ) ),
			),
			array(
				'type'   => 'update',
				'title'  => self::TITLE_DRAFT,
				'body'   => 'Not published yet.',
				'status' => 'draft',
			),
			array(
				'type'      => 'feature',
				'title'     => self::TITLE_NOT_STARTED,
				'body'      => 'Coming soon.',
				'status'    => 'published',
				'starts_at' => gmdate( 'c', strtotime( '+30 days' ) ),
			),
		);

		foreach ( $fixtures as $payload ) {
			$response = $this->org_panel->post( $base, $payload );

			if ( 201 !== $response['status'] ) {
				$this->fail( 'Could not author announcement fixture (' . $payload['title'] . '): HTTP ' . $response['status'] . ' — ' . json_encode( $response['body'] ) );
			}

			$this->created_announcement_ids[] = $response['body']['id'];
		}
	}

	private function delete_announcements() {
		if ( null === $this->org_panel || empty( $this->created_announcement_ids ) ) {
			return;
		}

		$credentials = $this->credentials();
		$base        = '/app/v1/organizations/' . $credentials->organization_id() . '/products/' . $credentials->product_id() . '/announcements/';

		foreach ( $this->created_announcement_ids as $id ) {
			$this->org_panel->delete( $base . $id );
		}

		$this->created_announcement_ids = array();
	}
}
