<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Admin\ConsentNotice;
use Appneck\Sdk\Consent;
use Appneck\Sdk\ContactGate;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Http\Transport;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Plugin;
use Appneck\Sdk\Queue\ArrayEventQueue;
use Appneck\Sdk\Sdk;
use Appneck\Sdk\Telemetry;
use PHPUnit\Framework\TestCase;

/**
 * Journal §70 — the free/pro consent model, end to end through the real
 * Sdk::bootstrap() wiring.
 *
 * The central guarantee (D1): on a FREE plugin, nothing leaves the site
 * until the owner clicks Allow & Continue. These tests drive every hook the
 * SDK registers — activation, a day of cron, admin page loads, the AJAX
 * endpoints, deactivation and uninstall — and fail on ANY outgoing request,
 * whether it comes through the injected transport or through the 3-second
 * client's own WpHttpTransport (caught by wp-http-polyfill.php).
 */
class ContactGateTest extends TestCase {

	const API_KEY        = 'pk_gate_test';
	const PRODUCT_SECRET = 'sk_gate_product_secret';
	const BASE_URL       = 'https://api.example.test';

	/** @var RoutingTransport */
	private $transport;

	/** @var ArrayEventQueue */
	private $queue;

	/** @var string */
	private $plugin_file;

	/** @var array<int, string> */
	private $redirects = array();

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/wp-cron-polyfill.php';
		require_once __DIR__ . '/wp-filter-polyfill.php';
		require_once __DIR__ . '/wp-hook-polyfill.php';
		require_once __DIR__ . '/wp-admin-polyfill.php';
		require_once __DIR__ . '/wp-file-data-polyfill.php';
		require_once __DIR__ . '/wp-http-polyfill.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options']    = array();
		$GLOBALS['appneck_test_cron']       = array();
		$GLOBALS['appneck_test_hooks']      = array();
		$GLOBALS['appneck_test_did_action'] = array();
		$GLOBALS['appneck_test_http']       = array();
		appneck_test_clear_filters();
		appneck_test_reset_admin();
		$GLOBALS['appneck_test_admin']['user'] = (object) array(
			'ID'           => 3,
			'user_email'   => 'owner@example.test',
			'display_name' => 'Site Owner',
		);

		$this->transport = new RoutingTransport();
		$this->queue     = new ArrayEventQueue();
		$this->redirects = array();

		$this->plugin_file = tempnam( sys_get_temp_dir(), 'appneck-gate' ) . '.php';
		file_put_contents( $this->plugin_file, "<?php\n/*\n * Plugin Name: Acme Free\n * Version: 1.2.0\n */\n" );

		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
		@unlink( $this->plugin_file );
	}

	private function boot( $premium = false, ?Transport $transport = null ): Plugin {
		$plugin = Sdk::bootstrap(
			self::API_KEY,
			self::PRODUCT_SECRET,
			self::BASE_URL,
			$this->plugin_file,
			null,
			$transport ?? $this->transport,
			new RecordingLogger(),
			$this->queue,
			array( 'is_premium' => $premium )
		);

		$plugin->consent_notice()->set_redirect_handler(
			function ( $url ) {
				$this->redirects[] = $url;
			}
		);

		return $plugin;
	}

	/** Every request that left the site, by either route. */
	private function outgoing(): int {
		return $this->transport->count() + count( $GLOBALS['appneck_test_http'] );
	}

	/**
	 * Everything a site does in a day with the plugin active: activation,
	 * admin page loads (init, admin_init, notices, footers), 96 telemetry
	 * cron ticks plus every one-off cron event the SDK scheduled, the AJAX
	 * refresh/poll/dismiss endpoints, and the plugin's own track() calls.
	 */
	private function live_a_day( Plugin $plugin ): void {
		$plugin->lifecycle()->on_activate();

		for ( $tick = 0; $tick < 96; $tick++ ) {
			appneck_test_do_action( 'init' );
			appneck_test_do_action( 'admin_init' );

			ob_start();
			appneck_test_do_action( 'admin_notices' );
			appneck_test_do_action( 'admin_footer' );
			ob_end_clean();

			$plugin->track( 'booking_created', array( 'tick' => $tick ) );
			$plugin->track_error( 'Something went wrong', array( 'tick' => $tick ) );

			appneck_test_do_action( Telemetry::CRON_HOOK );
			appneck_test_do_action( Lifecycle::CRON_HOOK );
			appneck_test_do_action( Consent::CRON_HOOK );
		}

		$plugin->announcement_notices()->handle_refresh_ajax();
		$plugin->announcement_notices()->handle_poll_ajax();
		$_POST = array( 'id' => '019fb200-0000-7000-8000-aaaaaaaaaaaa' );
		$plugin->announcement_notices()->handle_dismiss_ajax();
		$_POST = array();

		$plugin->announcements()->maybe_refresh();
		$plugin->flush();
	}

	private function click( Plugin $plugin, $field, $value ) {
		$notice = $plugin->consent_notice();
		$_POST  = array(
			'action'   => $notice->action(),
			$field     => $value,
			'_wpnonce' => 'nonce-for-' . $notice->action(),
		);

		$result = $notice->handle();
		$_POST  = array();

		return $result;
	}

	private function uninstall(): void {
		Sdk::uninstall( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL, null, $this->transport, new RecordingLogger(), $this->plugin_file );
	}

	// -----------------------------------------------------------------
	// Free, pending: nothing at all
	// -----------------------------------------------------------------

	public function test_a_free_plugin_with_no_answer_sends_nothing_for_a_whole_day_and_through_uninstall(): void {
		$plugin = $this->boot();

		$this->assertFalse( $plugin->may_contact_appneck() );

		$this->live_a_day( $plugin );
		$plugin->lifecycle()->on_deactivate();
		$this->uninstall();

		$this->assertSame( 0, $this->outgoing(), 'a free plugin must not contact Appneck before consent' );
		$this->assertSame( 0, $this->queue->count(), 'nothing is collected locally either' );
		$this->assertFalse( $plugin->is_registered() );
	}

	public function test_pending_collects_nothing_locally(): void {
		$plugin = $this->boot();

		$this->assertFalse( $plugin->track( 'feature_used' ) );
		$this->assertFalse( $plugin->track_error( 'boom' ) );
		$this->assertFalse( $plugin->telemetry()->heartbeat() );
		$this->assertSame( 0, $this->queue->count() );
	}

	public function test_registration_waiting_for_consent_does_not_use_up_its_retry_budget(): void {
		$plugin = $this->boot();
		$plugin->lifecycle()->on_activate();

		for ( $i = 0; $i < 50; $i++ ) {
			$plugin->lifecycle()->ensure_registered();
		}

		$this->assertSame( 0, $plugin->lifecycle()->attempts() );
		$this->assertTrue( $plugin->lifecycle()->is_pending() );
	}

	public function test_the_prompt_is_shown_while_pending(): void {
		$plugin = $this->boot();

		ob_start();
		appneck_test_do_action( 'admin_notices' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Never miss an important update', $html );
		$this->assertStringContainsString( 'Acme Free', $html );
	}

	// -----------------------------------------------------------------
	// Free, Skip: still nothing
	// -----------------------------------------------------------------

	public function test_skip_sends_nothing_now_or_later(): void {
		$plugin = $this->boot();
		$plugin->lifecycle()->on_activate();

		$this->assertSame( 'rejected', $this->click( $plugin, ConsentNotice::FIELD, 'rejected' ) );

		$this->live_a_day( $plugin );
		$plugin->lifecycle()->on_deactivate();

		$this->assertTrue( $plugin->consent()->is_rejected() );
		$this->assertFalse( $plugin->marketing_consent()->is_opted_in() );
		$this->assertFalse( $plugin->consent()->is_sync_pending(), 'settled locally — there is nothing the server needs to hear' );

		$this->uninstall();

		$this->assertSame( 0, $this->outgoing() );
	}

	// -----------------------------------------------------------------
	// Free, Allow & Continue
	// -----------------------------------------------------------------

	private function allow( Plugin $plugin ): void {
		$plugin->lifecycle()->on_activate();
		$this->click( $plugin, ConsentNotice::FIELD, 'accepted' );

		// The redirect lands on an admin page: registration, then the
		// consent sync, both on admin_init.
		appneck_test_do_action( 'admin_init' );
	}

	public function test_allow_registers_without_site_admins_and_records_both_consents(): void {
		$plugin = $this->boot();
		$this->allow( $plugin );

		$registration = $this->transport->body_of( '/sdk/v1/installations' );
		$this->assertNotNull( $registration, 'registration happens on the next admin page load' );
		$this->assertArrayNotHasKey( 'site_admins', $registration, 'a free build no longer sends the admin list' );
		$this->assertSame( 'free', $registration['edition'] );

		$consent = $this->transport->body_of( '/sdk/v1/consent' );
		$this->assertSame( 'accepted', $consent['status'] );
		$this->assertTrue( $consent['marketing_opt_in'] );
		$this->assertSame( 'owner@example.test', $consent['marketing_email'] );
		$this->assertSame( 'Site Owner', $consent['marketing_name'] );
		$this->assertSame( 1, $this->transport->count_to( '/sdk/v1/consent' ), 'both decisions in one call' );

		$this->assertTrue( $plugin->may_contact_appneck() );
	}

	/**
	 * D2: the "What's shared?" list must match exactly what the code sends.
	 * Every data key sent by registration, consent and telemetry must be
	 * disclosed, and every disclosed key must actually be sent.
	 *
	 * In its own process: it stands in for the WordPress functions the
	 * environment is read from (so every field has a value and is sent),
	 * and those must not leak into tests that rely on their absence.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_disclosed_fields_are_exactly_the_fields_sent(): void {
		require_once __DIR__ . '/wp-environment-polyfill.php';
		$plugin = $this->boot();
		$this->allow( $plugin );

		$plugin->track( 'booking_created', array( 'seats' => 2 ) );
		$plugin->track_error( 'Payment gateway timed out' );
		$plugin->telemetry()->heartbeat();
		$plugin->flush();

		$sent = array();

		foreach ( $this->transport->requests() as $request ) {
			$body = json_decode( (string) $request['body'], true );

			if ( ! is_array( $body ) ) {
				continue;
			}

			foreach ( $body as $key => $value ) {
				if ( 'events' !== $key ) {
					$sent[ $key ] = true;
					continue;
				}

				foreach ( $value as $event ) {
					$sent[ $event['type'] ] = true;

					foreach ( (array) $event['payload'] as $payload_key => $payload_value ) {
						if ( 'environment' === $payload_key ) {
							foreach ( array_keys( (array) $payload_value ) as $environment_key ) {
								$sent[ 'environment.' . $environment_key ] = true;
							}
						} elseif ( 'heartbeat' === $event['type'] ) {
							$sent[ $payload_key ] = true;
						}
					}
				}
			}
		}

		// Protocol, not data about the site or a person: how the request is
		// framed, which consent is being given, and the plugin's own event
		// shape (whose contents the "features you use" line discloses).
		// `environment.hash` is a checksum of the (disclosed) inventory, so
		// an unchanged inventory is not stored twice.
		$protocol = array( 'status', 'privacy_policy_version', 'marketing_opt_in', 'marketing_wording', 'edition', 'reclaim_token', 'heartbeat', 'environment.hash' );

		$disclosed = array();
		foreach ( ConsentNotice::SHARED_FIELDS as $keys ) {
			foreach ( $keys as $key ) {
				$disclosed[ $key ] = true;
			}
		}

		$undisclosed = array_diff( array_keys( $sent ), array_keys( $disclosed ), $protocol );
		$this->assertSame( array(), array_values( $undisclosed ), 'sent but not listed under "What\'s shared?"' );

		$unsent = array_diff( array_keys( $disclosed ), array_keys( $sent ) );
		$this->assertSame( array(), array_values( $unsent ), 'listed under "What\'s shared?" but never sent' );
	}

	public function test_turning_usage_data_off_stops_everything_but_the_survey(): void {
		$plugin = $this->boot();
		$this->allow( $plugin );

		$this->click( $plugin, ConsentNotice::SETTING_FIELD, 'usage_off' );

		$withdrawal = $this->transport->last_body_of( '/sdk/v1/consent' );
		$this->assertSame( 'rejected', $withdrawal['status'], 'a withdrawal of a recorded acceptance reaches the server' );
		$this->assertArrayNotHasKey( 'marketing_opt_in', $withdrawal, 'the email opt-in is a separate decision' );

		$before = $this->outgoing();
		$this->live_a_day( $plugin );
		$plugin->lifecycle()->on_deactivate();
		$this->assertSame( $before, $this->outgoing(), 'nothing after the withdrawal' );

		// ...except the survey, which is a person's own click.
		$plugin->survey()->questions( true );
		$this->assertSame( $before + 1, $this->outgoing() );
	}

	public function test_each_settings_switch_records_its_own_consent_event(): void {
		$plugin = $this->boot();
		$this->allow( $plugin );

		$this->click( $plugin, ConsentNotice::SETTING_FIELD, 'emails_off' );
		$emails = $this->transport->last_body_of( '/sdk/v1/consent' );
		$this->assertFalse( $emails['marketing_opt_in'] );
		$this->assertArrayNotHasKey( 'status', $emails );

		$this->click( $plugin, ConsentNotice::SETTING_FIELD, 'usage_off' );
		$usage = $this->transport->last_body_of( '/sdk/v1/consent' );
		$this->assertSame( 'rejected', $usage['status'] );
		$this->assertArrayNotHasKey( 'marketing_opt_in', $usage );

		$this->assertSame( 3, $this->transport->count_to( '/sdk/v1/consent' ) );
	}

	public function test_turning_emails_off_after_usage_off_still_reaches_the_server(): void {
		$plugin = $this->boot();
		$this->allow( $plugin );
		$this->click( $plugin, ConsentNotice::SETTING_FIELD, 'usage_off' );

		$this->click( $plugin, ConsentNotice::SETTING_FIELD, 'emails_off' );

		$this->assertFalse( $this->transport->last_body_of( '/sdk/v1/consent' )['marketing_opt_in'] );
		$this->assertFalse( $plugin->marketing_consent()->is_sync_pending() );
	}

	// -----------------------------------------------------------------
	// The uninstall survey without consent
	// -----------------------------------------------------------------

	private function survey_ajax( Plugin $plugin, $op, array $extra = array() ) {
		$_POST = array( 'op' => $op ) + $extra;
		$data  = $plugin->deactivation_survey()->handle_ajax();
		$_POST = array();

		return $data;
	}

	private function survey_questions_response(): Response {
		return Response::from_http( 200, array(), json_encode( array( 'questions' => array(
			array( 'id' => '11111111-1111-7111-8111-111111111111', 'position' => 1, 'type' => 'radio', 'text' => 'Why?', 'options' => array( 'choices' => array( 'Too complicated' ) ) ),
		) ) ) );
	}

	public function test_the_survey_works_with_no_consent_and_no_installation(): void {
		$plugin = $this->boot();
		$plugin->deactivation_survey()->set_plugin_basename( 'acme/acme.php' );

		// The survey goes over the 3-second client, whose transport is
		// WordPress's — so the polyfill is what sees it.
		$GLOBALS['appneck_test_http'] = array();
		$data = $this->survey_ajax( $plugin, 'questions' );

		$this->assertCount( 1, $GLOBALS['appneck_test_http'] );
		$request = $GLOBALS['appneck_test_http'][0];
		$this->assertStringEndsWith( '/sdk/v1/product/survey-questions', $request['url'] );
		$this->assertSame( '00000000-0000-0000-0000-000000000000', $request['args']['headers']['X-Installation-Id'] );
		$this->assertArrayNotHasKey( 'body', array_filter( $request['args'], function ( $value ) {
			return null !== $value && '' !== $value;
		} ), 'the questions request carries no domain and no environment' );
		$this->assertArrayHasKey( 'questions', $data );
		$this->assertFalse( $plugin->is_registered(), 'no installation is created' );
	}

	public function test_an_anonymous_survey_omits_name_and_email(): void {
		$survey = new \Appneck\Sdk\Survey(
			\Appneck\Sdk\Sdk::client( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL, new \Appneck\Sdk\Storage\ArrayCredentialStore(), $this->transport )
		);
		$deactivation = new \Appneck\Sdk\Admin\DeactivationSurvey( $survey, 'k', null );
		$deactivation->set_plugin_basename( 'acme/acme.php' );

		$this->transport->respond( '/sdk/v1/product/survey-questions', $this->survey_questions_response() );
		$this->transport->respond( '/sdk/v1/product/surveys', Response::from_http( 201, array(), '{}' ) );

		$_POST = array( 'op' => 'submit', 'answers' => json_encode( array( '11111111-1111-7111-8111-111111111111' => 'Too complicated' ) ), 'anonymous' => '1' );
		$deactivation->handle_ajax();

		$body = $this->transport->body_of( '/sdk/v1/product/surveys' );
		$this->assertArrayNotHasKey( 'respondent', $body );

		$_POST = array( 'op' => 'submit', 'answers' => json_encode( array( '11111111-1111-7111-8111-111111111111' => 'Too complicated' ) ), 'anonymous' => '0' );
		$deactivation->handle_ajax();

		$this->assertSame( 'owner@example.test', $this->transport->last_body_of( '/sdk/v1/product/surveys' )['respondent']['email'] );
	}

	public function test_a_failed_survey_never_blocks_deactivation(): void {
		$survey = new \Appneck\Sdk\Survey(
			\Appneck\Sdk\Sdk::client( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL, new \Appneck\Sdk\Storage\ArrayCredentialStore(), $this->transport )
		);
		$deactivation = new \Appneck\Sdk\Admin\DeactivationSurvey( $survey, 'k', null );
		$deactivation->set_plugin_basename( 'acme/acme.php' );

		$this->transport->respond( '/sdk/v1/product/survey-questions', $this->survey_questions_response() );
		$this->transport->respond( '/sdk/v1/product/surveys', Response::from_http( 500, array(), '{}' ) );

		$_POST = array( 'op' => 'submit', 'answers' => json_encode( array( '11111111-1111-7111-8111-111111111111' => 'Too complicated' ) ) );
		$data  = $deactivation->handle_ajax();

		$this->assertFalse( $data['submitted'] );
		$this->assertNull( $deactivation->denied, 'a failed submission still lets the modal deactivate' );
	}

	// -----------------------------------------------------------------
	// Premium
	// -----------------------------------------------------------------

	public function test_a_premium_plugin_never_shows_the_prompt_and_registers_straight_away(): void {
		$plugin = $this->boot( true );

		$this->assertTrue( $plugin->is_premium() );
		$this->assertTrue( $plugin->may_contact_appneck() );

		ob_start();
		appneck_test_do_action( 'admin_notices' );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'Never miss an important update', $html );

		$plugin->lifecycle()->on_activate();
		appneck_test_do_action( 'admin_init' );

		$registration = $this->transport->body_of( '/sdk/v1/installations' );
		$this->assertSame( 'premium', $registration['edition'] );
		$this->assertArrayHasKey( 'site_admins', $registration, 'premium still sends the admin list' );

		$this->assertTrue( $plugin->track( 'feature_used' ) );
	}

	public function test_a_premium_uninstall_still_reports_removal(): void {
		$plugin = $this->boot( true );
		$plugin->lifecycle()->on_activate();
		appneck_test_do_action( 'admin_init' );

		$this->uninstall();

		$this->assertSame( 1, $this->transport->count_to( '/sdk/v1/installations/status' ) );
	}

	public function test_is_premium_defaults_to_free(): void {
		$plugin = Sdk::bootstrap( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL, $this->plugin_file, null, $this->transport, new RecordingLogger(), $this->queue );

		$this->assertFalse( $plugin->is_premium() );
		$this->assertFalse( $plugin->may_contact_appneck() );
	}

	// -----------------------------------------------------------------
	// Upgrading from an SDK that buffered while pending (D6)
	// -----------------------------------------------------------------

	public function test_upgrading_while_pending_purges_what_the_old_sdk_buffered(): void {
		// What 0.3.0 left behind: events queued while consent was pending,
		// and the versions it last ran.
		$this->queue->push( 'custom_event', array( 'event' => 'old', 'data' => array() ) );
		$this->queue->push( 'heartbeat', array( 'sdk_version' => '0.3.0' ) );
		$key = substr( hash( 'sha256', Sdk::storage_identity_for( $this->plugin_file ) ), 0, 32 );
		$GLOBALS['appneck_test_options'][ 'appneck_sdk_seen_versions_' . $key ] = '1.2.0|0.3.0';

		$this->boot();

		$this->assertSame( 0, $this->queue->count() );
		$this->assertSame( 0, $this->outgoing() );
	}

	// -----------------------------------------------------------------
	// The gate itself
	// -----------------------------------------------------------------

	public function test_a_closed_gate_refuses_every_ordinary_request_without_touching_the_transport(): void {
		$client = Sdk::client( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL, new \Appneck\Sdk\Storage\ArrayCredentialStore( 'id', 'secret' ), $this->transport );
		$client->set_gate( new ContactGate( false, null ) );

		$this->assertFalse( $client->post( '/sdk/v1/telemetry', array( 'events' => array() ) )->ok() );
		$this->assertFalse( $client->get( '/sdk/v1/announcements' )->ok() );
		$this->assertFalse( $client->post( '/sdk/v1/installations', array(), \Appneck\Sdk\Client::MODE_BOOTSTRAP, 'id' )->ok() );
		$this->assertSame( 0, $this->transport->count() );

		$client->post_exempt( '/sdk/v1/product/surveys', array(), \Appneck\Sdk\Client::MODE_BOOTSTRAP, '00000000-0000-0000-0000-000000000000' );
		$this->assertSame( 1, $this->transport->count(), 'only the explicit exempt path gets through' );
	}

	/**
	 * Structural: the gate is only a guarantee if every request goes through
	 * Client. Anything that reaches the network another way — a direct
	 * wp_remote_* call, a second transport call site, curl — fails here.
	 * LicenseClient is the one documented exception (journal §70 D1: a
	 * licence call only follows someone entering a licence key).
	 */
	public function test_nothing_in_src_reaches_the_network_except_through_client(): void {
		$allowed = array(
			'Http/WpHttpTransport.php' => 'the transport itself',
			'Client.php'               => 'gated',
			'LicenseClient.php'        => 'licensing, journal §70 D1',
		);

		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src' ) );

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$relative = ltrim( str_replace( dirname( __DIR__ ) . '/src', '', $file->getPathname() ), '/' );
			$source   = (string) file_get_contents( $file->getPathname() );

			if ( isset( $allowed[ $relative ] ) ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression( '/\bwp_remote_(get|post|request|head)\s*\(|\bcurl_exec\s*\(|\bfsockopen\s*\(|file_get_contents\s*\(\s*[\'"]https?:/', $source, $relative . ' contacts the network directly' );
			$this->assertDoesNotMatchRegularExpression( '/transport\s*->\s*request\s*\(/', $source, $relative . ' calls a transport directly' );
		}
	}
}

/**
 * Answers by path, and records every request. A path with nothing
 * configured gets a plausible success for its endpoint, so a full day of
 * hooks can run against it.
 */
final class RoutingTransport implements Transport {

	/** @var array<int, array<string, mixed>> */
	private $requests = array();

	/** @var array<string, Response> */
	private $routes = array();

	public function respond( $path, Response $response ) {
		$this->routes[ $path ] = $response;
	}

	public function request( $method, $url, array $headers, $body = null ) {
		$this->requests[] = compact( 'method', 'url', 'headers', 'body' );
		$path             = (string) parse_url( $url, PHP_URL_PATH );

		if ( isset( $this->routes[ $path ] ) ) {
			return $this->routes[ $path ];
		}

		if ( '/sdk/v1/installations' === $path ) {
			return Response::from_http( 201, array(), json_encode( array( 'id' => $headers['X-Installation-Id'], 'installation_secret' => 'sk_issued_installation_secret' ) ) );
		}

		if ( '/sdk/v1/telemetry' === $path ) {
			return Response::from_http( 202, array(), json_encode( array( 'accepted' => array(), 'rejected' => array() ) ) );
		}

		return Response::from_http( 200, array(), '{}' );
	}

	/** @return array<int, array<string, mixed>> */
	public function requests() {
		return $this->requests;
	}

	public function count() {
		return count( $this->requests );
	}

	public function count_to( $path ) {
		return count( $this->matching( $path ) );
	}

	/** The JSON body of the FIRST request to $path, or null. */
	public function body_of( $path ) {
		$matching = $this->matching( $path );

		return empty( $matching ) ? null : json_decode( (string) $matching[0]['body'], true );
	}

	/** The JSON body of the LAST request to $path, or null. */
	public function last_body_of( $path ) {
		$matching = $this->matching( $path );

		return empty( $matching ) ? null : json_decode( (string) end( $matching )['body'], true );
	}

	private function matching( $path ) {
		return array_values(
			array_filter(
				$this->requests,
				function ( $request ) use ( $path ) {
					return parse_url( $request['url'], PHP_URL_PATH ) === $path;
				}
			)
		);
	}
}
