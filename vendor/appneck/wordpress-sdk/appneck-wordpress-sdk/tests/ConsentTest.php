<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Consent;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * The decision itself: what each state means for track(), what reaches
 * the server, and what happens when the consent call is the thing that
 * fails.
 */
class ConsentTest extends TestCase {

	const API_KEY        = 'pk_consent_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-bbbbbbbbbbbb';
	const BASE_URL       = 'https://api.example.test';

	/** @var QueueingTransport */
	private $transport;

	/** @var ArrayEventQueue */
	private $queue;

	/** @var RecordingLogger */
	private $logger;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-cron-polyfill.php';
		require_once __DIR__ . '/wp-filter-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options'] = array();
		$GLOBALS['appneck_test_cron']    = array();
		appneck_test_clear_filters();

		$this->transport = new QueueingTransport();
		$this->queue     = new ArrayEventQueue();
		$this->logger    = new RecordingLogger();
	}

	private function client( $registered = true ): Client {
		return new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			$registered
				? new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET )
				: new ArrayCredentialStore(),
			$this->transport
		);
	}

	/**
	 * The pair as Sdk::bootstrap wires them.
	 *
	 * @return array{0: Consent, 1: Telemetry}
	 */
	private function wired( $registered = true ): array {
		$client    = $this->client( $registered );
		$telemetry = new Telemetry( $client, $this->queue, $this->logger );
		$consent   = new Consent( $client, $telemetry, $this->logger );

		$telemetry->set_consent( $consent );

		return array( $consent, $telemetry );
	}

	private function ok(): Response {
		return Response::from_http( 200, array(), json_encode( array( 'consent_status' => 'accepted' ) ) );
	}

	// -----------------------------------------------------------------
	// The three states
	// -----------------------------------------------------------------

	public function test_starts_pending_and_needs_a_decision(): void {
		list( $consent ) = $this->wired();

		$this->assertTrue( $consent->is_pending() );
		$this->assertFalse( $consent->is_accepted() );
		$this->assertFalse( $consent->is_rejected() );
		$this->assertTrue( $consent->needs_decision() );
		$this->assertNull( $consent->decided_at() );
	}

	/**
	 * The S4.3 behaviour, pinned: nothing has been decided, so events
	 * accumulate and the server's 403 is what stops them going out.
	 */
	public function test_pending_still_queues_events_locally(): void {
		list( , $telemetry ) = $this->wired();

		$this->assertTrue( $telemetry->track( 'before_any_decision' ) );
		$this->assertSame( 1, $this->queue->count() );
	}

	public function test_pending_still_attempts_a_flush(): void {
		list( , $telemetry ) = $this->wired();

		$telemetry->track( 'before_any_decision' );
		$this->transport->queue( Response::from_http( 403, array(), json_encode( array( 'message' => 'Consent has not been granted for this installation.' ) ) ) );

		$response = $telemetry->flush();

		$this->assertNotNull( $response, 'pending must not gate client-side — the server is the authority' );
		$this->assertSame( 403, $response->status() );
		$this->assertSame( 1, $this->queue->count(), 'the event survives the refusal' );
	}

	public function test_accept_records_the_decision_and_calls_the_server(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );

		$response = $consent->accept();

		$this->assertSame( 200, $response->status() );
		$this->assertTrue( $consent->is_accepted() );
		$this->assertFalse( $consent->needs_decision() );
		$this->assertNotNull( $consent->decided_at() );
		$this->assertFalse( $consent->is_sync_pending() );

		$request = $this->transport->last_request();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/consent', $request['url'] );
		$this->assertSame(
			array(
				'status'                 => 'accepted',
				'privacy_policy_version' => '1.0',
			),
			json_decode( $request['body'], true )
		);
	}

	public function test_accept_lifts_the_consent_backoff_so_the_backlog_goes_out(): void {
		list( $consent, $telemetry ) = $this->wired();

		// A pending-consent 403 suppresses flushing for an hour.
		$telemetry->track( 'queued_while_pending' );
		$this->transport->queue( Response::from_http( 403, array(), json_encode( array( 'message' => 'Consent has not been granted.' ) ) ) );
		$telemetry->flush();

		$this->assertGreaterThan( time(), $telemetry->suppressed_until() );

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$this->assertSame( 0, $telemetry->suppressed_until(), 'the back-off measured a condition that is now resolved' );
		$this->assertSame( 1, $this->queue->count(), 'and the backlog is still there to send' );
	}

	// -----------------------------------------------------------------
	// Reject: no-op, not silent accumulation
	// -----------------------------------------------------------------

	public function test_reject_makes_track_a_no_op(): void {
		list( $consent, $telemetry ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->reject();

		$this->assertFalse( $telemetry->track( 'after_refusal' ) );
		$this->assertFalse( $telemetry->track_error( 'after_refusal' ) );
		$this->assertFalse( $telemetry->heartbeat() );
		$this->assertSame( 0, $this->queue->count(), 'nothing may be collected after an explicit no' );
	}

	public function test_reject_purges_events_collected_while_the_question_was_open(): void {
		list( $consent, $telemetry ) = $this->wired();

		$telemetry->track( 'queued_while_pending_1' );
		$telemetry->track( 'queued_while_pending_2' );
		$this->assertSame( 2, $this->queue->count() );

		$this->transport->queue( $this->ok() );
		$consent->reject();

		$this->assertSame(
			0,
			$this->queue->count(),
			'a later change of mind must not ship events collected during the refusal window'
		);
	}

	public function test_reject_stops_a_flush_reached_by_an_already_scheduled_cron(): void {
		list( $consent, $telemetry ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->reject();

		$before = $this->transport->count();

		$this->assertNull( $telemetry->flush() );
		$this->assertNull( $telemetry->run_scheduled_flush() );
		$this->assertSame( $before, $this->transport->count(), 'no request may be made under a refusal' );
	}

	/**
	 * The decision may have been made in a different request entirely — by
	 * the admin-post handler — so a Telemetry built fresh on the next page
	 * load must still honour it, and must clear a queue that a crash or a
	 * restored backup left behind.
	 */
	public function test_a_refusal_stored_in_another_request_is_still_honoured(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->reject();

		// Fresh objects, same stored option — a new page load.
		$client        = $this->client();
		$new_queue     = new ArrayEventQueue();
		$new_queue->push( 'custom_event', array( 'event' => 'left_behind' ) );
		$new_telemetry = new Telemetry( $client, $new_queue, $this->logger );
		$new_telemetry->set_consent( new Consent( $client, $new_telemetry, $this->logger ) );

		$this->assertFalse( $new_telemetry->track( 'nope' ) );
		$this->assertSame( 0, $new_queue->count() );
	}

	public function test_reject_reports_rejected_to_the_server(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->reject();

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( 'rejected', $body['status'] );
	}

	// -----------------------------------------------------------------
	// Changing the decision
	// -----------------------------------------------------------------

	public function test_rejected_to_accepted_unblocks_collection(): void {
		list( $consent, $telemetry ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->reject();
		$this->assertFalse( $telemetry->track( 'blocked' ) );

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$this->assertTrue( $consent->is_accepted() );
		$this->assertTrue( $telemetry->track( 'allowed_again' ) );
		$this->assertSame( 1, $this->queue->count() );

		$this->transport->queue(
			Response::from_http(
				202,
				array(),
				json_encode(
					array(
						'accepted_count' => 1,
						'rejected_count' => 0,
						'accepted'       => array( array( 'index' => 0 ) ),
						'rejected'       => array(),
					)
				)
			)
		);

		$flush = $telemetry->flush();

		$this->assertSame( 202, $flush->status() );
		$this->assertSame( 0, $this->queue->count() );
	}

	public function test_accepted_to_rejected_appends_a_second_server_call(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$this->transport->queue( $this->ok() );
		$consent->reject();

		$this->assertSame( 2, $this->transport->count(), 'each decision is its own consent_events row' );
		$this->assertTrue( $consent->is_rejected() );
	}

	// -----------------------------------------------------------------
	// The consent call itself failing
	// -----------------------------------------------------------------

	public function test_the_decision_survives_an_unreachable_api(): void {
		list( $consent, $telemetry ) = $this->wired();

		// Nothing queued in the transport: a transport error.
		$response = $consent->accept();

		$this->assertTrue( $response->is_transport_error() );
		$this->assertTrue( $consent->is_accepted(), 'the owner clicked; their answer is not the API\'s to lose' );
		$this->assertTrue( $consent->is_sync_pending() );
		$this->assertTrue( appneck_test_is_scheduled( Consent::CRON_HOOK ), 'a retry is scheduled' );
		$this->assertTrue( $this->logger->contains( 'stored locally and will retry' ) );

		// And the local consequence was applied anyway.
		$this->assertTrue( $telemetry->track( 'allowed_locally' ) );
	}

	public function test_a_rejection_is_enforced_locally_even_if_the_api_is_down(): void {
		list( $consent, $telemetry ) = $this->wired();

		$telemetry->track( 'queued_while_pending' );

		$response = $consent->reject();

		$this->assertTrue( $response->is_transport_error() );
		$this->assertTrue( $consent->is_rejected() );
		$this->assertSame( 0, $this->queue->count() );
		$this->assertFalse( $telemetry->track( 'after_refusal' ) );
	}

	public function test_the_retry_sends_the_decision_and_stops_retrying(): void {
		list( $consent ) = $this->wired();

		$consent->accept();
		$this->assertTrue( $consent->is_sync_pending() );

		$this->transport->queue( $this->ok() );
		$response = $consent->sync();

		$this->assertSame( 200, $response->status() );
		$this->assertFalse( $consent->is_sync_pending() );
		$this->assertFalse( appneck_test_is_scheduled( Consent::CRON_HOOK ) );

		// Nothing outstanding: a further sync must not re-post.
		$before = $this->transport->count();
		$this->assertNull( $consent->sync() );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_a_decision_made_before_registration_waits_without_burning_attempts(): void {
		list( $consent ) = $this->wired( false );

		$response = $consent->accept();

		$this->assertNull( $response, 'nothing can be signed yet, so nothing was attempted' );
		$this->assertSame( 0, $this->transport->count() );
		$this->assertTrue( $consent->is_accepted() );
		$this->assertTrue( $consent->is_sync_pending() );
		$this->assertTrue( appneck_test_is_scheduled( Consent::CRON_HOOK ) );

		// MAX_SYNC_ATTEMPTS must still be intact once registration lands:
		// registration can retry for a day on its own backoff.
		for ( $i = 0; $i < Consent::MAX_SYNC_ATTEMPTS + 5; $i++ ) {
			$consent->sync();
		}

		$registered = new Consent( $this->client(), null, $this->logger );
		$this->transport->queue( $this->ok() );

		$this->assertSame( 200, $registered->sync()->status() );
		$this->assertFalse( $registered->is_sync_pending() );
	}

	public function test_sync_gives_up_after_max_attempts(): void {
		list( $consent ) = $this->wired();

		$consent->accept(); // attempt 1, transport error.

		for ( $i = 0; $i < Consent::MAX_SYNC_ATTEMPTS + 3; $i++ ) {
			$consent->sync();
		}

		$this->assertSame(
			Consent::MAX_SYNC_ATTEMPTS,
			$this->transport->count(),
			'a firewalled site must not keep calling forever'
		);
	}

	public function test_admin_init_retry_is_rate_limited_to_once_an_hour(): void {
		list( $consent ) = $this->wired();

		$consent->accept(); // One failed attempt, just now.

		$before = $this->transport->count();
		$consent->maybe_sync_on_admin_init();

		$this->assertSame( $before, $this->transport->count(), 'no request per admin page load' );
	}

	public function test_admin_init_retry_does_nothing_when_synced(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$before = $this->transport->count();
		$consent->maybe_sync_on_admin_init();

		$this->assertSame( $before, $this->transport->count() );
	}

	// -----------------------------------------------------------------
	// Privacy policy version
	// -----------------------------------------------------------------

	public function test_the_policy_version_comes_from_the_filter(): void {
		list( $consent ) = $this->wired();

		appneck_test_add_filter(
			'appneck_sdk_privacy_policy_version',
			static function () {
				return '2026-08-01';
			}
		);

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( '2026-08-01', $body['privacy_policy_version'] );
		$this->assertSame( '2026-08-01', $consent->decided_version() );
	}

	public function test_an_unusable_filter_value_falls_back_rather_than_sending_junk(): void {
		list( $consent ) = $this->wired();

		appneck_test_add_filter(
			'appneck_sdk_privacy_policy_version',
			static function () {
				return '';
			}
		);

		$this->assertSame( Consent::DEFAULT_PRIVACY_POLICY_VERSION, $consent->privacy_policy_version() );
	}

	public function test_an_over_long_policy_version_is_truncated_to_the_servers_limit(): void {
		list( $consent ) = $this->wired();

		$consent->set_privacy_policy_version( str_repeat( 'v', 300 ) );

		$this->assertSame( 255, strlen( $consent->privacy_policy_version() ) );
	}

	public function test_a_new_policy_version_re_prompts_a_previous_acceptance(): void {
		list( $consent ) = $this->wired();

		$consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$consent->accept();

		$this->assertFalse( $consent->needs_decision() );

		$consent->set_privacy_policy_version( '2.0' );

		$this->assertTrue( $consent->needs_decision(), 'consent to one policy does not cover a revised one' );
	}

	/**
	 * The deliberate limit of the re-prompt: the server has no current
	 * policy version to compare against, so blocking here would make a
	 * healthy site look lost (journal §8.4) while the server still reads
	 * it as accepted.
	 */
	public function test_a_new_policy_version_does_not_block_telemetry_while_unanswered(): void {
		list( $consent, $telemetry ) = $this->wired();

		$consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$consent->accept();

		$consent->set_privacy_policy_version( '2.0' );

		$this->assertTrue( $consent->is_accepted() );
		$this->assertTrue( $telemetry->track( 'still_collecting' ) );
	}

	public function test_re_confirming_records_the_new_version_server_side(): void {
		list( $consent ) = $this->wired();

		$consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$consent->accept();

		$consent->set_privacy_policy_version( '2.0' );
		$this->transport->queue( $this->ok() );
		$consent->accept();

		$body = json_decode( $this->transport->last_request()['body'], true );
		$this->assertSame( '2.0', $body['privacy_policy_version'] );
		$this->assertFalse( $consent->needs_decision() );
	}

	public function test_a_rejection_is_never_re_prompted_by_a_policy_change(): void {
		list( $consent ) = $this->wired();

		$consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$consent->reject();

		$consent->set_privacy_policy_version( '2.0' );

		$this->assertFalse( $consent->needs_decision(), 'do not nag someone who said no' );
	}

	public function test_the_re_prompt_can_be_filtered_off_for_a_trivial_policy_edit(): void {
		list( $consent ) = $this->wired();

		$consent->set_privacy_policy_version( '1.0' );
		$this->transport->queue( $this->ok() );
		$consent->accept();

		appneck_test_add_filter(
			'appneck_sdk_reprompt_on_policy_change',
			static function () {
				return false;
			}
		);

		$consent->set_privacy_policy_version( '1.0.1' );

		$this->assertFalse( $consent->needs_decision() );
	}

	// -----------------------------------------------------------------
	// Storage
	// -----------------------------------------------------------------

	public function test_a_corrupted_option_reads_as_pending_not_as_consent(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$name = 'appneck_sdk_consent_' . $consent->key();
		update_option( $name, array( 'status' => 'yes_please' ), true );

		$this->assertTrue( $consent->is_pending() );
		$this->assertFalse( $consent->is_accepted() );
	}

	public function test_the_option_is_namespaced_per_product(): void {
		$other = new Consent(
			new Client(
				new Config( 'pk_a_different_product', self::PRODUCT_SECRET, self::BASE_URL ),
				new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET ),
				$this->transport
			)
		);

		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$this->assertNotSame( $consent->key(), $other->key() );
		$this->assertTrue( $other->is_pending(), 'one plugin answering must not answer for another' );
	}

	public function test_forget_clears_the_local_decision(): void {
		list( $consent ) = $this->wired();

		$this->transport->queue( $this->ok() );
		$consent->accept();

		$consent->forget();

		$this->assertTrue( $consent->is_pending() );
		$this->assertFalse( $consent->is_sync_pending() );
	}

	public function test_an_invalid_status_is_refused(): void {
		list( $consent ) = $this->wired();

		$this->assertNull( $consent->decide( 'maybe' ) );
		$this->assertTrue( $consent->is_pending() );
		$this->assertSame( 0, $this->transport->count() );
	}
}
