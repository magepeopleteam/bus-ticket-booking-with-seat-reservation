<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Admin\DeactivationSurvey;
use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Environment;
use Appneck\Sdk\Lifecycle;
use Appneck\Sdk\Storage\WpOptionsCredentialStore;
use Appneck\Sdk\Survey;
use Appneck\Sdk\Tests\RecordingLogger;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;
use Appneck\Sdk\Tests\Integration\Support\OrgPanelClient;

/**
 * Converted from tests/integration/survey-check.php (S4.7 audit). The
 * deactivation survey against the REAL backend: fetch the questions a
 * real product has configured, answer them through the same AJAX handler
 * the browser modal drives, and confirm the response lands where the
 * organization will read it — unchanged from the original script's
 * assertions.
 *
 * The original script documented a manual precondition: "author 5
 * questions (one of each type) for this product in the Org Panel
 * first." setUp()/tearDown() here do that through the real
 * `POST /app/v1/organizations/{org}/products/{product}/survey-questions`
 * API instead — no standing manual step, no Org Panel click-through
 * required before this test can run.
 *
 * The second product (APPNECK_SDK_TEST_SECOND_API_KEY) still needs to be
 * a product with nothing configured on it — see "Integration tests" in
 * README.md. That's a one-time environment choice (which product these
 * env vars point at), not a per-run manual task.
 */
final class SurveyCheckTest extends IntegrationTestCase {

	/** @var array<int, string> Question ids created in setUp(), for cleanup. */
	private $created_question_ids = array();

	/** @var OrgPanelClient|null */
	private $org_panel = null;

	protected function setUp(): void {
		parent::setUp();
		$this->require_fixture_authoring();
		$this->create_questions();
	}

	protected function tearDown(): void {
		$this->delete_questions();
		parent::tearDown();
	}

	public function test_deactivation_survey_against_the_real_backend() {
		$credentials = $this->credentials();
		$site_domain = $this->random_domain( 's45-survey' );

		$logger = new RecordingLogger();
		$client = $this->make_client( $credentials->api_key(), $credentials->product_secret() );
		$survey = new Survey( $client, $logger );
		$key    = substr( hash( 'sha256', $credentials->api_key() ), 0, 32 );
		$modal  = new DeactivationSurvey( $survey, $key, null, array( 'product_name' => 'Survey Check Plugin' ) );
		$modal->set_plugin_basename( 'survey-check/survey-check.php' );

		// -------------------------------------------------------------
		echo "\n1. Register, then grant consent (the survey rides the same zone)\n";

		$lifecycle    = new Lifecycle( $client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
		$lifecycle->on_activate();
		$registration = $lifecycle->ensure_registered();

		$this->assertCheck( 'registered (201)', 201 === $registration->status(), 'got ' . $registration->status() . ' — ' . $registration->error_message() );

		$installation_id = ( new WpOptionsCredentialStore( $credentials->api_key() ) )->get_installation_id();
		echo "        installation: {$installation_id}\n";

		// -------------------------------------------------------------
		echo "\n2. The plugins-screen modal renders without touching the API\n";

		ob_start();
		$modal->render();
		$html = (string) ob_get_clean();

		$this->assertCheck( 'the modal shell printed', false !== strpos( $html, 'role="dialog"' ) );
		// json_encode escapes forward slashes, so the basename appears in
		// the config as survey-check\/survey-check.php — compared in that
		// form rather than raw, since that is what the browser actually
		// receives.
		$this->assertCheck(
			'it targets this plugin only',
			false !== strpos( $html, trim( json_encode( 'survey-check/survey-check.php' ), '"' ) ),
			'basename missing from the printed config'
		);
		$this->assertCheck( 'it intercepts WordPress own deactivate link', false !== strpos( $html, 'action=deactivate' ) );
		$this->assertCheck(
			'submit, skip and cancel are all offered',
			false !== strpos( $html, 'data-appneck-submit' )
				&& false !== strpos( $html, 'data-appneck-skip' )
				&& false !== strpos( $html, 'data-appneck-cancel' )
		);
		$this->assertCheck( 'the questions are NOT baked into the page', false === strpos( $html, 'Why are you deactivating' ) );

		// -------------------------------------------------------------
		echo "\n3. Opening the modal fetches this product's real questions\n";

		$result = $this->modal_request( $modal, 'questions' );

		$this->assertCheck( 'the handler answered', is_array( $result ) && isset( $result['questions'] ), var_export( $result, true ) );

		$questions = isset( $result['questions'] ) ? $result['questions'] : array();

		$this->assertCheck( 'five questions came back', 5 === count( $questions ), (string) count( $questions ) );

		$types = array();
		foreach ( $questions as $question ) {
			$types[] = $question['type'];
			echo '        ' . $question['position'] . '. [' . $question['type'] . '] ' . $question['text'] . "\n";
		}

		$this->assertCheck( 'every configured type is present', array( 'radio', 'checkbox', 'rating', 'dropdown', 'text_area' ) === $types, implode( ',', $types ) );
		$this->assertCheck( 'they arrived in configured order', 1 === $questions[0]['position'] && 5 === $questions[4]['position'] );
		$this->assertCheck( 'choices came with them', ! empty( $questions[0]['options']['choices'] ) );
		$this->assertCheck( 'the rating carries its max', 5 === (int) $questions[2]['options']['max'] );
		$this->assertCheck( 'every question is displayable', '' !== trim( $questions[0]['text'] ) );

		$radio    = $questions[0];
		$checkbox = $questions[1];
		$rating   = $questions[2];
		$dropdown = $questions[3];
		$text     = $questions[4];

		// -------------------------------------------------------------
		echo "\n4. Local validation refuses a bad answer before anything is sent\n";

		$result = $this->modal_request(
			$modal,
			'submit',
			array(
				$rating['id'] => '99',
				$radio['id']  => 'A choice nobody configured',
			)
		);

		$this->assertCheck( 'the modal is told to stay open', is_array( $result ) && isset( $result['errors'] ), var_export( $result, true ) );
		$this->assertCheck( 'the rating is flagged', isset( $result['errors'][ $rating['id'] ] ) );
		$this->assertCheck( 'the invented choice is flagged', isset( $result['errors'][ $radio['id'] ] ) );

		// -------------------------------------------------------------
		echo "\n5. Skip — no submission, and nothing stands in the way\n";

		$result = $this->modal_request( $modal, 'submit', array() );

		$this->assertCheck( 'no submission was made', is_array( $result ) && false === $result['submitted'], var_export( $result, true ) );
		$this->assertCheck( 'and no error was raised for the modal to stall on', ! isset( $result['errors'] ) );

		// -------------------------------------------------------------
		echo "\n6. The submission itself fails — deactivation must still proceed\n";

		$offline_client = new Client(
			new Config( $credentials->api_key(), $credentials->product_secret(), 'http://127.0.0.1:9' ),
			new WpOptionsCredentialStore( $credentials->api_key() )
		);
		$offline_survey = new Survey( $offline_client, $logger );
		$offline_modal  = new DeactivationSurvey( $offline_survey, $key, null, array( 'product_name' => 'Survey Check Plugin' ) );
		$offline_modal->set_plugin_basename( 'survey-check/survey-check.php' );

		// The questions are already cached from step 3, so the modal
		// still has something to show even with the API unreachable —
		// which is exactly why they are cached.
		$offline_questions = $this->modal_request( $offline_modal, 'questions' );
		$this->assertCheck( 'the cached questions still render offline', 5 === count( $offline_questions['questions'] ), (string) count( $offline_questions['questions'] ) );

		$result = $this->modal_request( $offline_modal, 'submit', array( $radio['id'] => $radio['options']['choices'][0] ) );

		$this->assertCheck( 'the handler returned normally, not an error', is_array( $result ) && ! isset( $result['errors'] ), var_export( $result, true ) );
		$this->assertCheck( 'it reports the submission did not land', false === $result['submitted'] );
		$this->assertCheck( 'as a transport failure', 0 === (int) $result['status'], (string) $result['status'] );
		$this->assertCheck( 'the failure was logged, not shown', $logger->contains( 'could not be submitted' ) );
		echo "        (the modal navigates to the deactivate link regardless — nothing here can stop it)\n";

		// -------------------------------------------------------------
		echo "\n7. A real answer, submitted through the modal\n";

		$answers = array(
			$radio['id']    => $radio['options']['choices'][1],
			$checkbox['id'] => array( $checkbox['options']['choices'][0], $checkbox['options']['choices'][2] ),
			$rating['id']   => '4',
			$dropdown['id'] => $dropdown['options']['choices'][2],
			$text['id']     => 'Great plugin, but we built the feature in-house. Sorry!',
		);

		$result = $this->modal_request( $modal, 'submit', $answers );

		$this->assertCheck( 'the submission landed', is_array( $result ) && true === $result['submitted'], var_export( $result, true ) );
		$this->assertCheck( 'HTTP 201 Created', 201 === (int) $result['status'], (string) $result['status'] );

		// journal 9.3a's idempotency: a second submission is a 200 with
		// the response already on file, never a duplicate row.
		$repeat = $this->modal_request( $modal, 'submit', $answers );

		$this->assertCheck( 'a repeat submission is accepted without duplicating', is_array( $repeat ) && true === $repeat['submitted'] );
		$this->assertCheck( 'and answered 200, not 201', 200 === (int) $repeat['status'], (string) $repeat['status'] );

		// -------------------------------------------------------------
		echo "\n8. Cross-product isolation, and a product with no survey at all\n";

		if ( $credentials->has_second_product() ) {
			$other_client    = $this->make_client( $credentials->second_api_key(), $credentials->second_product_secret() );
			$other_lifecycle = new Lifecycle( $other_client, null, new Environment( null, array( 'site_domain' => $site_domain ) ) );
			$other_lifecycle->on_activate();
			$other_registration = $other_lifecycle->ensure_registered();

			$this->assertCheck( 'a second product registered on the same site (201)', 201 === $other_registration->status(), 'got ' . $other_registration->status() );

			$other_survey = new Survey( $other_client, $logger );
			$other_modal  = new DeactivationSurvey( $other_survey, substr( hash( 'sha256', $credentials->second_api_key() ), 0, 32 ) );
			$other_modal->set_plugin_basename( 'other-plugin/other-plugin.php' );

			$other_result = $this->modal_request( $other_modal, 'questions' );

			$this->assertCheck( "it sees no questions — not the first product's", array() === $other_result['questions'], json_encode( $other_result ) );
			$this->assertCheck( 'which is a normal answer, not an error', is_array( $other_result ) && ! isset( $other_result['errors'] ) );
			echo "        (the modal skips itself entirely and the plugin deactivates untouched)\n";

			$other_lifecycle->on_uninstall();
			$other_survey->forget();
		} else {
			$this->markTestIncomplete( 'No second product configured (APPNECK_SDK_TEST_SECOND_API_KEY) — cross-product isolation not exercised.' );
		}

		// -------------------------------------------------------------
		echo "\n9. Deactivate for real — the action the survey must never block\n";

		$deactivation = $lifecycle->on_deactivate();

		$this->assertCheck( 'the installation reported itself deactivated (200)', null !== $deactivation && 200 === $deactivation->status(), $deactivation ? (string) $deactivation->status() : 'null' );
		$this->assertCheck( 'server-side status is deactivated', 'deactivated' === $deactivation->get( 'status' ), (string) $deactivation->get( 'status' ) );

		// A survey submitted after the plugin already reported itself
		// deactivated is precisely what journal 9.3a's `scoped` tier
		// exists for — the read side must behave the same way, or the
		// modal would work on some plugins and not others depending on
		// hook order.
		$after = $this->modal_request( $modal, 'questions' );

		$this->assertCheck( 'the questions are still fetchable while deactivated', 5 === count( $after['questions'] ), (string) count( $after['questions'] ) );

		// -------------------------------------------------------------
		echo "\n10. Cleanup\n";

		$lifecycle->on_uninstall();
		$survey->forget();

		echo "        (uninstalled, cached questions cleared)\n";
	}

	/** Exactly what the modal's JavaScript posts to admin-ajax.php. */
	private function modal_request( DeactivationSurvey $modal, $op, array $answers = array() ) {
		$_POST = array(
			'action'  => $modal->action(),
			'nonce'   => 'nonce-for-' . $modal->action(),
			'op'      => $op,
			'answers' => json_encode( $answers ),
		);

		return $modal->handle_ajax();
	}

	/**
	 * Authors exactly the 5-question fixture the assertions above depend
	 * on (one of each type, in this order, with >=3 choices for every
	 * choice type so index [0]/[1]/[2] references below are always valid).
	 */
	private function create_questions() {
		$credentials     = $this->credentials();
		$this->org_panel = $this->org_panel_client();
		$base            = '/app/v1/organizations/' . $credentials->organization_id() . '/products/' . $credentials->product_id() . '/survey-questions';

		$fixtures = array(
			array(
				'question_type' => 'radio',
				'question_text' => 'Why are you deactivating?',
				'options'       => array( 'Found a better plugin', 'Too expensive', 'Missing features' ),
			),
			array(
				'question_type' => 'checkbox',
				'question_text' => 'What mattered most to you?',
				'options'       => array( 'Speed', 'Price', 'Support' ),
			),
			array(
				'question_type' => 'rating',
				'question_text' => 'How would you rate this plugin?',
				'rating_max'    => 5,
			),
			array(
				'question_type' => 'dropdown',
				'question_text' => 'How did you find this plugin?',
				'options'       => array( 'WordPress.org', 'Google', 'A friend' ),
			),
			array(
				'question_type' => 'text_area',
				'question_text' => 'Anything else you would like us to know?',
			),
		);

		foreach ( $fixtures as $payload ) {
			$response = $this->org_panel->post( $base, $payload );

			if ( 201 !== $response['status'] ) {
				$this->fail( 'Could not author survey question fixture (' . $payload['question_type'] . '): HTTP ' . $response['status'] . ' — ' . json_encode( $response['body'] ) );
			}

			$this->created_question_ids[] = $response['body']['id'];
		}
	}

	private function delete_questions() {
		if ( null === $this->org_panel || empty( $this->created_question_ids ) ) {
			return;
		}

		$credentials = $this->credentials();
		$base        = '/app/v1/organizations/' . $credentials->organization_id() . '/products/' . $credentials->product_id() . '/survey-questions/';

		foreach ( $this->created_question_ids as $id ) {
			$this->org_panel->delete( $base . $id );
		}

		$this->created_question_ids = array();
	}
}
