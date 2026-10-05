<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Announcements;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * The announcement cache: what a refresh does (and does not) overwrite,
 * how dismissals survive it, and the ordering the display depends on.
 */
class AnnouncementsTest extends TestCase {

	const API_KEY        = 'pk_announcements_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-ffffffffffff';
	const BASE_URL       = 'https://api.example.test';

	const SECURITY_ID = 'aaaaaaaa-1111-7111-8111-111111111111';
	const FEATURE_ID  = 'bbbbbbbb-2222-7222-8222-222222222222';
	const DISCOUNT_ID = 'cccccccc-3333-7333-8333-333333333333';
	const UPDATE_ID   = 'dddddddd-4444-7444-8444-444444444444';

	/** @var QueueingTransport */
	private $transport;

	/** @var RecordingLogger */
	private $logger;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-hook-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options'] = array();

		$this->transport = new QueueingTransport();
		$this->logger    = new RecordingLogger();
	}

	private function announcements( $registered = true ): Announcements {
		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			$registered
				? new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET )
				: new ArrayCredentialStore(),
			$this->transport
		);

		return new Announcements( $client, $this->logger );
	}

	/**
	 * The server's own shape and order: newest first by starts_at, already
	 * filtered to what is currently visible.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function payload(): array {
		return array(
			array(
				'id'         => self::DISCOUNT_ID,
				'type'       => 'discount',
				'title'      => '20% off renewals',
				'body'       => "Use code SAVE20.\nEnds Friday.",
				'starts_at'  => '2026-08-05T00:00:00+00:00',
				'expires_at' => '2026-09-01T00:00:00+00:00',
			),
			array(
				'id'         => self::SECURITY_ID,
				'type'       => 'security',
				'title'      => 'Security release 2.4.1',
				'body'       => 'Please update as soon as possible.',
				'starts_at'  => '2026-08-04T00:00:00+00:00',
				'expires_at' => null,
			),
			array(
				'id'         => self::FEATURE_ID,
				'type'       => 'feature',
				'title'      => 'Bulk export is here',
				'body'       => '',
				'starts_at'  => '2026-08-01T00:00:00+00:00',
				'expires_at' => null,
			),
		);
	}

	/** @param array<int, array<string, mixed>>|null $announcements */
	private function queue( ?array $announcements = null ): void {
		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				json_encode( array( 'announcements' => null !== $announcements ? $announcements : $this->payload() ) )
			)
		);
	}

	// -----------------------------------------------------------------
	// Fetching
	// -----------------------------------------------------------------

	public function test_a_refresh_caches_the_servers_list(): void {
		$announcements = $this->announcements();
		$this->queue();

		$fetched = $announcements->refresh();

		$this->assertCount( 3, $fetched );
		$this->assertCount( 3, $announcements->all() );

		$request = $this->transport->last_request();
		$this->assertSame( 'GET', $request['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/announcements', $request['url'] );
	}

	public function test_reading_the_cache_makes_no_request(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$before = $this->transport->count();

		$announcements->visible();
		$announcements->all();

		$this->assertSame( $before, $this->transport->count() );
	}

	/**
	 * It rides Telemetry's existing schedule. Asserted against the hook
	 * constant rather than a string so a rename cannot silently detach it.
	 */
	public function test_it_rides_the_existing_heartbeat_tick(): void {
		require_once __DIR__ . '/wp-hook-polyfill.php';

		$announcements = $this->announcements();
		$announcements->register_hooks();

		$this->assertArrayHasKey(
			Telemetry::CRON_HOOK,
			$GLOBALS['appneck_test_hooks'],
			'the refresh must hang off the heartbeat hook'
		);
	}

	public function test_it_adds_no_schedule_of_its_own(): void {
		require_once __DIR__ . '/wp-cron-polyfill.php';

		$GLOBALS['appneck_test_cron'] = array();

		$announcements = $this->announcements();
		$announcements->register_hooks();
		$this->queue();
		$announcements->refresh();

		$this->assertSame( array(), $GLOBALS['appneck_test_cron'], 'no new wp_cron event may be created' );
	}

	/**
	 * The important failure case: a poll that fails must not blank the
	 * list, or a security notice would vanish because a request timed out.
	 */
	public function test_a_failed_refresh_keeps_the_cached_copy(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->transport->queue( Response::from_http( 500, array(), '{"message":"boom"}' ) );

		$this->assertNull( $announcements->refresh() );
		$this->assertCount( 3, $announcements->all(), 'the cache survived' );
		$this->assertTrue( $this->logger->contains( 'the cached copy is kept' ) );
	}

	public function test_an_unreachable_api_keeps_the_cached_copy_too(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		// Nothing queued: transport error.
		$this->assertNull( $announcements->refresh() );
		$this->assertCount( 3, $announcements->all() );
	}

	/**
	 * A 403 means this installation is no longer active. Still not a reason
	 * to blank the list — the plugin may be reactivated a minute later.
	 */
	public function test_a_403_keeps_the_cached_copy(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->transport->queue( Response::from_http( 403, array(), '{"message":"This installation is not active."}' ) );

		$this->assertNull( $announcements->refresh() );
		$this->assertCount( 3, $announcements->all() );
	}

	public function test_an_unregistered_site_makes_no_request(): void {
		$announcements = $this->announcements( false );

		$this->assertNull( $announcements->refresh() );
		$this->assertSame( 0, $this->transport->count() );
	}

	/**
	 * An empty list IS applied — unlike a failure. The organization
	 * unpublishing everything, or every announcement expiring, is a real
	 * answer and the notices must stop showing.
	 */
	public function test_an_empty_list_replaces_the_cache(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->queue( array() );

		$this->assertSame( array(), $announcements->refresh() );
		$this->assertSame( array(), $announcements->all() );
		$this->assertSame( array(), $announcements->visible() );
	}

	public function test_an_unrenderable_announcement_is_dropped(): void {
		$announcements = $this->announcements();
		$this->queue(
			array(
				array( 'id' => self::SECURITY_ID, 'type' => 'security', 'title' => '   ' ),
				array( 'type' => 'feature', 'title' => 'No id' ),
				array( 'id' => self::FEATURE_ID, 'title' => 'Only a title' ),
				'not an array',
			)
		);

		$visible = $announcements->refresh();

		$this->assertCount( 1, $visible );
		$this->assertSame( self::FEATURE_ID, $visible[0]['id'] );
		// A missing type must not become the urgent one.
		$this->assertSame( 'update', $visible[0]['type'] );
	}

	/**
	 * The client must not re-apply the validity window: the server already
	 * evaluated it at request time (journal 9.3b), and a site clock a few
	 * minutes off would otherwise hide something the server chose to send.
	 */
	public function test_the_client_does_not_re_filter_by_the_validity_window(): void {
		$announcements = $this->announcements();

		// A window the client would reject if it were checking: both dates
		// in the past. The server sent it anyway, so it shows.
		$this->queue(
			array(
				array(
					'id'         => self::UPDATE_ID,
					'type'       => 'update',
					'title'      => 'Served by the server, whatever the local clock thinks',
					'body'       => '',
					'starts_at'  => '2020-01-01T00:00:00+00:00',
					'expires_at' => '2020-02-01T00:00:00+00:00',
				),
			)
		);

		$this->assertCount( 1, $announcements->refresh() );
		$this->assertCount( 1, $announcements->visible() );
	}

	// -----------------------------------------------------------------
	// Ordering
	// -----------------------------------------------------------------

	public function test_urgency_wins_over_recency(): void {
		$announcements = $this->announcements();
		$this->queue();

		$announcements->refresh();

		$ids = array();

		foreach ( $announcements->visible() as $announcement ) {
			$ids[] = $announcement['id'];
		}

		// The discount is the newest and the server sent it first. The
		// security notice still comes out on top — the one place ordering
		// genuinely matters.
		$this->assertSame( array( self::SECURITY_ID, self::FEATURE_ID, self::DISCOUNT_ID ), $ids );
	}

	public function test_recency_decides_within_a_type(): void {
		$announcements = $this->announcements();

		// Two features, in the server's newest-first order.
		$this->queue(
			array(
				array( 'id' => self::FEATURE_ID, 'type' => 'feature', 'title' => 'Newer feature', 'body' => '' ),
				array( 'id' => self::UPDATE_ID, 'type' => 'feature', 'title' => 'Older feature', 'body' => '' ),
			)
		);

		$announcements->refresh();
		$visible = $announcements->visible();

		$this->assertSame( self::FEATURE_ID, $visible[0]['id'] );
		$this->assertSame( self::UPDATE_ID, $visible[1]['id'] );
	}

	public function test_an_unknown_type_sorts_last_rather_than_first(): void {
		$announcements = $this->announcements();
		$this->queue(
			array(
				array( 'id' => self::UPDATE_ID, 'type' => 'something_new', 'title' => 'From a newer server', 'body' => '' ),
				array( 'id' => self::DISCOUNT_ID, 'type' => 'discount', 'title' => 'A discount', 'body' => '' ),
			)
		);

		$announcements->refresh();
		$visible = $announcements->visible();

		$this->assertSame( self::DISCOUNT_ID, $visible[0]['id'] );
		$this->assertSame( self::UPDATE_ID, $visible[1]['id'] );
	}

	// -----------------------------------------------------------------
	// Dismissals
	// -----------------------------------------------------------------

	public function test_a_dismissed_announcement_stops_being_visible(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->assertTrue( $announcements->dismiss( self::SECURITY_ID ) );
		$this->assertTrue( $announcements->is_dismissed( self::SECURITY_ID ) );

		$ids = array();

		foreach ( $announcements->visible() as $announcement ) {
			$ids[] = $announcement['id'];
		}

		$this->assertNotContains( self::SECURITY_ID, $ids );
		$this->assertCount( 2, $ids, 'the others are untouched' );
	}

	/**
	 * The requirement this feature turns on: the cached list is replaced
	 * wholesale on every tick, and the announcement is still inside its
	 * validity window, so a dismissal stored in the list itself would be
	 * forgotten immediately.
	 */
	public function test_a_dismissal_survives_a_cache_refresh(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();
		$announcements->dismiss( self::SECURITY_ID );

		// The server still serves it — nothing expired.
		$this->queue();
		$announcements->refresh();

		$this->assertTrue( $announcements->is_dismissed( self::SECURITY_ID ) );

		foreach ( $announcements->visible() as $announcement ) {
			$this->assertNotSame( self::SECURITY_ID, $announcement['id'] );
		}

		$this->assertCount( 3, $announcements->all(), 'it is still cached, just not shown' );
	}

	public function test_dismissing_twice_is_harmless(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->assertTrue( $announcements->dismiss( self::SECURITY_ID ) );
		$this->assertTrue( $announcements->dismiss( self::SECURITY_ID ) );
		$this->assertCount( 1, $announcements->dismissed() );
	}

	/**
	 * A spoofed or stale id must not consume a dismissal slot — the option
	 * is capped, so junk would eventually evict real dismissals.
	 */
	public function test_an_unknown_id_cannot_be_dismissed(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$this->assertFalse( $announcements->dismiss( 'not-a-real-announcement' ) );
		$this->assertFalse( $announcements->dismiss( '' ) );
		$this->assertSame( array(), $announcements->dismissed() );
	}

	public function test_dismissals_are_capped_and_drop_the_oldest(): void {
		$announcements = $this->announcements();

		$payload = array();

		for ( $i = 0; $i < Announcements::MAX_DISMISSALS + 5; $i++ ) {
			$payload[] = array(
				'id'    => sprintf( '%08d-0000-7000-8000-000000000000', $i ),
				'type'  => 'feature',
				'title' => 'Announcement ' . $i,
				'body'  => '',
			);
		}

		$this->queue( $payload );
		$announcements->refresh();

		foreach ( $payload as $announcement ) {
			$announcements->dismiss( $announcement['id'] );
		}

		$this->assertCount( Announcements::MAX_DISMISSALS, $announcements->dismissed() );
		// The newest survived; the very first was evicted.
		$this->assertTrue( $announcements->is_dismissed( $payload[ count( $payload ) - 1 ]['id'] ) );
		$this->assertFalse( $announcements->is_dismissed( $payload[0]['id'] ) );
	}

	public function test_forget_clears_the_cache_and_the_dismissals(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();
		$announcements->dismiss( self::SECURITY_ID );

		$announcements->forget();

		$this->assertSame( array(), $announcements->all() );
		$this->assertSame( array(), $announcements->dismissed() );
		$this->assertSame( 0, $announcements->fetched_at() );
	}

	// -----------------------------------------------------------------
	// The WP-Cron fallback
	// -----------------------------------------------------------------

	public function test_a_fresh_cache_is_not_re_fetched_on_screen(): void {
		$announcements = $this->announcements();
		$this->queue();
		$announcements->refresh();

		$before = $this->transport->count();

		$this->assertNull( $announcements->maybe_refresh() );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_an_empty_cache_is_fetched_on_screen(): void {
		$announcements = $this->announcements();
		$this->queue();

		$this->assertCount( 3, $announcements->maybe_refresh() );
	}

	/**
	 * Rate-limited on the attempt, not the success: an unreachable API must
	 * not mean a doomed request every time the owner reloads their settings.
	 */
	public function test_the_fallback_is_rate_limited_even_when_it_fails(): void {
		$announcements = $this->announcements();

		// Nothing queued: the attempt fails.
		$announcements->maybe_refresh();

		$before = $this->transport->count();

		$announcements->maybe_refresh();
		$announcements->maybe_refresh();

		$this->assertSame( $before, $this->transport->count(), 'one attempt an hour, whatever happened' );
	}
}
