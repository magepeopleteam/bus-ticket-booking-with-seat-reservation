<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Admin\ConsentNotice;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Consent;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Telemetry;
use Appneck\Sdk\Tests\RecordingLogger;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * Converted from tests/integration/consent-check.php (S4.7 audit).
 * Consent against the REAL backend: prompt -> Accept -> queued events
 * send; Reject -> nothing collected and telemetry stays refused
 * server-side; change of mind -> the queue unblocks again — unchanged
 * from the original script's assertions.
 *
 * Every decision goes through the actual admin-post handler
 * (ConsentNotice::handle) with a populated $_POST, not through
 * Consent::accept() directly — the click is the thing being tested, and
 * the capability check, the nonce check and the redirect are part of it.
 */
final class ConsentCheckTest extends IntegrationTestCase {

	public function test_consent_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's4-consent' );

		$logger    = new RecordingLogger();
		$queue     = new ArrayEventQueue();
		$client    = $this->make_client( $credentials->api_key(), $credentials->product_secret() );
		$telemetry = new Telemetry( $client, $queue, $logger );
		$consent   = new Consent( $client, $telemetry, $logger );
		$telemetry->set_consent( $consent );
		$consent->set_privacy_policy_version( '2026-08-06' );

		$redirects = array();
		$notice    = new ConsentNotice( $consent, array( 'product_name' => 'Consent Check Plugin' ) );
		$notice->set_redirect_handler(
			function ( $url ) use ( &$redirects ) {
				$redirects[] = $url;
			}
		);

		// -------------------------------------------------------------
		echo "\n1. Register, and confirm the prompt is showing\n";

		$lifecycle    = new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ), $telemetry );
		$lifecycle->on_activate();
		$registration = $lifecycle->ensure_registered();

		$this->assertCheck( 'registered (201)', 201 === $registration->status(), 'got ' . $registration->status() . ' — ' . $registration->error_message() );

		$installation_id = ( new WpOptionsCredentialStore( $credentials->api_key() ) )->get_installation_id();
		echo "        installation: {$installation_id}\n";

		$this->assertCheck( 'consent starts pending', $consent->is_pending() );
		$this->assertCheck( 'the prompt needs an answer', $consent->needs_decision() );

		ob_start();
		$notice->render();
		$html = (string) ob_get_clean();

		$this->assertCheck( 'the notice renders', false !== strpos( $html, 'notice notice-info' ) );
		$this->assertCheck( 'it names the plugin', false !== strpos( $html, 'Consent Check Plugin' ) );
		$this->assertCheck( 'it offers both answers', false !== strpos( $html, 'value="accepted"' ) && false !== strpos( $html, 'value="rejected"' ) );
		$this->assertCheck( 'it is not dismissible', false === strpos( $html, 'is-dismissible' ) );

		// -------------------------------------------------------------
		echo "\n2. Events queue while unanswered, and the server refuses them\n";

		$telemetry->track( 'queued_before_consent_1' );
		$telemetry->track( 'queued_before_consent_2' );

		$this->assertCheck( 'two events queued locally', 2 === $queue->count(), (string) $queue->count() );

		$refused = $telemetry->flush();

		$this->assertCheck( 'telemetry refused (403)', null !== $refused && 403 === $refused->status(), $refused ? (string) $refused->status() : 'null' );
		$this->assertCheck( 'both events survived the refusal', 2 === $queue->count(), (string) $queue->count() );

		// -------------------------------------------------------------
		echo "\n3. Accept clicked -> POST /consent, and the backlog goes out\n";

		$applied = $this->click( $notice, 'accepted' );

		$this->assertCheck( 'the click was applied', 'accepted' === $applied, var_export( $applied, true ) );
		$this->assertCheck( 'redirected back to where they were', 1 === count( $redirects ), (string) count( $redirects ) );
		$this->assertCheck( 'the consent call succeeded', 200 === $client->last_response()->status(), (string) $client->last_response()->status() );
		$this->assertCheck( 'stored locally as accepted', $consent->is_accepted() );
		$this->assertCheck( 'the server has it — nothing pending', ! $consent->is_sync_pending() );
		$this->assertCheck( 'the prompt is answered', ! $consent->needs_decision() );
		$this->assertCheck( 'the consent back-off was lifted', 0 === $telemetry->suppressed_until(), (string) $telemetry->suppressed_until() );
		$this->assertCheck( 'the backlog is still queued', 2 === $queue->count(), (string) $queue->count() );

		$flush = $telemetry->flush();

		$this->assertCheck( 'the previously-refused batch is accepted (202)', null !== $flush && 202 === $flush->status(), $flush ? (string) $flush->status() : 'null' );
		$this->assertCheck( 'both previously-queued events accepted', 2 === (int) $flush->get( 'accepted_count' ), (string) $flush->get( 'accepted_count' ) );
		$this->assertCheck( 'queue cleared', 0 === $queue->count() );

		ob_start();
		$notice->render();
		$this->assertCheck( 'the notice no longer renders', '' === (string) ob_get_clean() );

		// -------------------------------------------------------------
		echo "\n4. Reject clicked -> nothing is collected at all\n";

		$telemetry->track( 'collected_while_accepted' );
		$this->assertCheck( 'an event was collected while accepted', 1 === $queue->count() );

		$applied = $this->click( $notice, 'rejected' );

		$this->assertCheck( 'the click was applied', 'rejected' === $applied, var_export( $applied, true ) );
		$this->assertCheck( 'the consent call succeeded', 200 === $client->last_response()->status(), (string) $client->last_response()->status() );
		$this->assertCheck( 'stored locally as rejected', $consent->is_rejected() );

		$this->assertCheck( 'track() is a no-op', false === $telemetry->track( 'after_refusal' ) );
		$this->assertCheck( 'track_error() is a no-op', false === $telemetry->track_error( 'after_refusal' ) );
		$this->assertCheck( 'heartbeat() is a no-op', false === $telemetry->heartbeat() );
		$this->assertCheck( 'the local queue is empty — collection stopped, not parked', 0 === $queue->count(), (string) $queue->count() );
		$this->assertCheck( 'flush() makes no request', null === $telemetry->flush() );

		// The client-side no-op is a courtesy; the server is the
		// enforcement. Prove the server still refuses by going around
		// Telemetry entirely.
		$direct = $client->post(
			'/sdk/v1/telemetry',
			array(
				'events' => array(
					array(
						'type'        => 'custom_event',
						'payload'     => array( 'event' => 'should_never_be_stored' ),
						'occurred_at' => gmdate( 'c' ),
					),
				),
			)
		);

		$this->assertCheck( 'the server refuses telemetry under a rejection (403)', 403 === $direct->status(), 'got ' . $direct->status() );
		$this->assertCheck( 'and says why', false !== stripos( (string) $direct->error_message(), 'consent' ), (string) $direct->error_message() );

		// -------------------------------------------------------------
		echo "\n5. Change of mind: rejected -> accepted unblocks the queue\n";

		ob_start();
		$notice->render_settings_section();
		$settings = (string) ob_get_clean();

		$this->assertCheck( 'the settings control shows the current decision', false !== strpos( $settings, 'not sharing usage data' ) );
		$this->assertCheck( 'and offers the opposite action only', false !== strpos( $settings, 'value="accepted"' ) && false === strpos( $settings, 'value="rejected"' ) );

		$applied = $this->click( $notice, 'accepted' );

		$this->assertCheck( 'the click was applied', 'accepted' === $applied, var_export( $applied, true ) );
		$this->assertCheck( 'the consent call succeeded', 200 === $client->last_response()->status(), (string) $client->last_response()->status() );
		$this->assertCheck( 'collection resumes', true === $telemetry->track( 'after_changing_my_mind' ) );

		$flush = $telemetry->flush();

		$this->assertCheck( 'and it sends again (202)', null !== $flush && 202 === $flush->status(), $flush ? (string) $flush->status() : 'null' );
		$this->assertCheck( 'accepted by the server', 1 === (int) $flush->get( 'accepted_count' ), (string) $flush->get( 'accepted_count' ) );

		// -------------------------------------------------------------
		echo "\n6. The consent call itself failing must not break anything\n";

		$offline_client    = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), 'http://127.0.0.1:9' ),
			new WpOptionsCredentialStore( $credentials->api_key() )
		);
		$offline_telemetry = new Telemetry( $offline_client, $queue, $logger );
		$offline_consent   = new Consent( $offline_client, $offline_telemetry, $logger );
		$offline_telemetry->set_consent( $offline_consent );
		$offline_consent->set_privacy_policy_version( '2026-08-06' );

		$offline_redirects = array();
		$offline_notice    = new ConsentNotice( $offline_consent );
		$offline_notice->set_redirect_handler(
			function ( $url ) use ( &$offline_redirects ) {
				$offline_redirects[] = $url;
			}
		);

		$applied = $this->click( $offline_notice, 'rejected' );

		$this->assertCheck( 'the click still applied', 'rejected' === $applied, var_export( $applied, true ) );
		$this->assertCheck( 'the consent call failed as a transport error', $offline_client->last_response()->is_transport_error() );
		$this->assertCheck( 'the decision is stored locally anyway', $offline_consent->is_rejected() );
		$this->assertCheck( 'and enforced locally anyway', false === $offline_telemetry->track( 'after_offline_refusal' ) );
		$this->assertCheck( 'flagged as not yet sent', $offline_consent->is_sync_pending() );
		$this->assertCheck( 'a retry is scheduled', appneck_test_is_scheduled( Consent::CRON_HOOK ) );
		$this->assertCheck( 'the site owner was still redirected, not shown an error', 1 === count( $offline_redirects ) );
		$this->assertCheck( 'the failure was logged', $logger->contains( 'stored locally and will retry' ) );

		// The API comes back: the scheduled retry is what runs next.
		$recovered = new Consent( $client, $telemetry, $logger );
		$response  = $recovered->sync();

		$this->assertCheck( 'the retry delivered the decision (200)', null !== $response && 200 === $response->status(), $response ? (string) $response->status() : 'null' );
		$this->assertCheck( 'nothing outstanding now', ! $recovered->is_sync_pending() );
		$this->assertCheck( 'the retry is unscheduled', ! appneck_test_is_scheduled( Consent::CRON_HOOK ) );
		$this->assertCheck( 'a further sync makes no request', null === $recovered->sync() );

		// The site is left REJECTED by that recovery, which is what the
		// last decision actually was — assert the server agrees rather
		// than assuming.
		$blocked = $client->post(
			'/sdk/v1/telemetry',
			array(
				'events' => array(
					array(
						'type'        => 'heartbeat',
						'payload'     => array( 'sdk_version' => '0.1.0' ),
						'occurred_at' => gmdate( 'c' ),
					),
				),
			)
		);

		$this->assertCheck( 'the server is back to refusing (403)', 403 === $blocked->status(), 'got ' . $blocked->status() );

		// -------------------------------------------------------------
		echo "\n7. Cleanup — mark this installation removed and forget locally\n";

		$lifecycle->on_uninstall();
		$consent->forget();

		$this->assertCheck( 'the local decision is gone', $consent->is_pending() );
		echo "        (uninstalled)\n";
	}

	/** Simulates a site owner clicking a button in the admin notice. */
	private function click( ConsentNotice $notice, $status ) {
		$_POST = array(
			'action'             => $notice->action(),
			ConsentNotice::FIELD => $status,
			'_wpnonce'           => 'nonce-for-' . $notice->action(),
		);

		return $notice->handle();
	}
}
