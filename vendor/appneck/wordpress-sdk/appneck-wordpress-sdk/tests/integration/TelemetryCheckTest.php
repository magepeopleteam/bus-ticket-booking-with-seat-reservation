<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Telemetry;
use Appneck\Sdk\Tests\RecordingLogger;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * Converted from tests/integration/telemetry-check.php (S4.7 audit).
 * Telemetry against the REAL backend: enqueue -> batch -> send -> partial
 * success -> offline resilience — unchanged from the original script's
 * assertions.
 *
 * Uses the real Client, WpHttpTransport, Lifecycle and Telemetry.
 * ArrayEventQueue substitutes for the production TableEventQueue because
 * this harness has no real WordPress/$wpdb — see the Known limitations
 * section of README.md; the two implement the same EventQueue contract.
 */
final class TelemetryCheckTest extends IntegrationTestCase {

	public function test_telemetry_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's4-telemetry' );

		$logger = new RecordingLogger();
		$queue  = new ArrayEventQueue();
		$client = $this->make_client( $credentials->api_key(), $credentials->product_secret() );

		// -------------------------------------------------------------
		echo "\n1. Register, then grant consent (journal 5.4's fail-closed gate)\n";

		$lifecycle    = new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
		$lifecycle->on_activate();
		$registration = $lifecycle->ensure_registered();

		$this->assertCheck( 'registered (201)', 201 === $registration->status(), 'got ' . $registration->status() . ' — ' . $registration->error_message() );

		$store            = new WpOptionsCredentialStore( $credentials->api_key() );
		$installation_id  = $store->get_installation_id();
		echo "        installation: {$installation_id}\n";

		$telemetry = new Telemetry( $client, $queue, $logger );

		// Fail-closed: telemetry must be refused until consent exists.
		$telemetry->track( 'before_consent' );
		$refused = $telemetry->flush();

		$this->assertCheck( 'telemetry refused before consent (403)', null !== $refused && 403 === $refused->status(), $refused ? (string) $refused->status() : 'null' );
		$this->assertCheck( 'the event survived the refusal', 1 === $queue->count() );
		$this->assertCheck( 'not stopped — consent is recoverable', ! $telemetry->is_stopped() );

		$consent = $client->post(
			'/sdk/v1/consent',
			array(
				'status'                 => 'accepted',
				'privacy_policy_version' => '1.0',
			)
		);
		$this->assertCheck( 'consent accepted (200)', 200 === $consent->status(), 'got ' . $consent->status() );

		// The consent refusal set a back-off; lift it now that consent exists.
		$telemetry->resume();

		// -------------------------------------------------------------
		echo "\n2. track() must not make an HTTP call\n";

		$before = $queue->count();

		$telemetry->track( 'booking_created', array( 'source' => 'checkout', 'total' => 42 ) );
		$telemetry->track( 'feature_used', array( 'feature' => 'bulk_export' ) );
		$telemetry->track_error( 'Payment gateway timeout', array( 'gateway' => 'stripe' ) );
		$telemetry->heartbeat();

		$this->assertCheck( 'four events queued locally', ( $before + 4 ) === $queue->count(), (string) $queue->count() );
		echo "        (no request was made — proven structurally by the unit suite too)\n";

		// -------------------------------------------------------------
		echo "\n3. Flush — one signed batch to the real backend\n";

		$queued_before = $queue->count();
		$response      = $telemetry->flush();

		$this->assertCheck( 'HTTP 202 Accepted', 202 === $response->status(), 'got ' . $response->status() . ' — ' . $response->error_message() );
		$this->assertCheck( 'server accepted every event', $queued_before === (int) $response->get( 'accepted_count' ), (string) $response->get( 'accepted_count' ) );
		$this->assertCheck( 'local queue cleared', 0 === $queue->count() );
		$this->assertCheck( 'rate limit headers read', null !== $response->rate_limit()->limit() );
		echo '        accepted ' . $response->get( 'accepted_count' ) . ', rate limit ' .
			$response->rate_limit()->remaining() . '/' . $response->rate_limit()->limit() . " remaining\n";

		// -------------------------------------------------------------
		echo "\n4. Partial success — a mix of valid and deliberately invalid events\n";

		$telemetry->track( 'valid_one', array( 'ok' => true ) );

		// Pushed straight onto the queue: track() cannot produce an invalid
		// event, which is the point — this simulates a corrupted row or an
		// older SDK version's output.
		$queue->push( 'not_a_real_type', array( 'nope' => true ) );
		$queue->push( 'custom_event', array( 'event' => 'valid_two' ) );

		$response = $telemetry->flush();

		$this->assertCheck( 'HTTP 202', 202 === $response->status(), 'got ' . $response->status() );
		$this->assertCheck( 'two accepted', 2 === (int) $response->get( 'accepted_count' ), (string) $response->get( 'accepted_count' ) );
		$this->assertCheck( 'one rejected', 1 === (int) $response->get( 'rejected_count' ), (string) $response->get( 'rejected_count' ) );
		$this->assertCheck( 'queue fully cleared — rejected dropped, not retried forever', 0 === $queue->count() );
		$this->assertCheck( 'the rejection was logged', $logger->contains( 'permanently rejected' ) );

		// -------------------------------------------------------------
		echo "\n5. Offline resilience\n";

		$offline_client    = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), 'http://127.0.0.1:9' ),
			new WpOptionsCredentialStore( $credentials->api_key() )
		);
		$offline_telemetry = new Telemetry( $offline_client, $queue, $logger );

		$offline_telemetry->track( 'queued_while_offline_1' );
		$offline_telemetry->track( 'queued_while_offline_2' );
		$offline_telemetry->heartbeat();

		$response = $offline_telemetry->flush();

		$this->assertCheck( 'the flush failed as a transport error', null !== $response && $response->is_transport_error() );
		$this->assertCheck( 'all three events survived', 3 === $queue->count(), (string) $queue->count() );

		// The API comes back.
		$recovered = new Telemetry( $client, $queue, $logger );
		$response  = $recovered->flush();

		$this->assertCheck( 'the backlog sent once the API returned (202)', 202 === $response->status(), 'got ' . $response->status() );
		$this->assertCheck( 'all three accepted', 3 === (int) $response->get( 'accepted_count' ), (string) $response->get( 'accepted_count' ) );
		$this->assertCheck( 'queue empty', 0 === $queue->count() );

		// -------------------------------------------------------------
		echo "\n6. Cleanup — mark this installation removed\n";

		$lifecycle->on_uninstall();
		echo "        (uninstalled)\n";
	}
}
