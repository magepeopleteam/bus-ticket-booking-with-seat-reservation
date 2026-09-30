<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Client;
use Appneck\Sdk\Config;
use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Storage\ArrayCredentialStore;
use Appneck\Sdk\Survey;
use PHPUnit\Framework\TestCase;

/**
 * The survey client: fetching questions (and caching the answer, including
 * "there is no survey"), checking an answer against its own question, and
 * the single submission attempt.
 */
class SurveyTest extends TestCase {

	const API_KEY        = 'pk_survey_test';
	const PRODUCT_SECRET = 'sk_product_secret_value';
	const INSTALL_SECRET = 'sk_installation_secret_value';
	const INSTALL_ID     = '019fb200-0000-7000-8000-dddddddddddd';
	const BASE_URL       = 'https://api.example.test';

	const RADIO_ID    = '11111111-1111-7111-8111-111111111111';
	const CHECKBOX_ID = '22222222-2222-7222-8222-222222222222';
	const RATING_ID   = '33333333-3333-7333-8333-333333333333';
	const DROPDOWN_ID    = '44444444-4444-7444-8444-444444444444';
	const TEXT_ID        = '55555555-5555-7555-8555-555555555555';
	const CONDITIONAL_ID = '66666666-6666-7666-8666-666666666666';

	/** @var QueueingTransport */
	private $transport;

	/** @var RecordingLogger */
	private $logger;

	protected function setUp(): void {
		require_once __DIR__ . '/wp-option-polyfill.php';
		require_once __DIR__ . '/QueueingTransport.php';
		require_once __DIR__ . '/RecordingLogger.php';

		$GLOBALS['appneck_test_options'] = array();

		$this->transport = new QueueingTransport();
		$this->logger    = new RecordingLogger();
	}

	private function survey( $registered = true, ?\Appneck\Sdk\RealtimeConfig $realtime_config = null ): Survey {
		$client = new Client(
			new Config( self::API_KEY, self::PRODUCT_SECRET, self::BASE_URL ),
			$registered
				? new ArrayCredentialStore( self::INSTALL_ID, self::INSTALL_SECRET )
				: new ArrayCredentialStore(),
			$this->transport
		);

		return new Survey( $client, $this->logger, $realtime_config );
	}

	/** @return array<int, array<string, mixed>> */
	private function question_payload(): array {
		return array(
			array(
				'id'       => self::RADIO_ID,
				'position' => 1,
				'type'     => 'radio',
				'text'     => 'Why are you deactivating?',
				'options'  => array( 'choices' => array( 'Too complicated', 'Found a better plugin' ) ),
			),
			array(
				'id'       => self::CHECKBOX_ID,
				'position' => 2,
				'type'     => 'checkbox',
				'text'     => 'What mattered most?',
				'options'  => array( 'choices' => array( 'Speed', 'Support', 'Price' ) ),
			),
			array(
				'id'       => self::RATING_ID,
				'position' => 3,
				'type'     => 'rating',
				'text'     => 'How would you rate it?',
				'options'  => array( 'max' => 5 ),
			),
			array(
				'id'       => self::DROPDOWN_ID,
				'position' => 4,
				'type'     => 'dropdown',
				'text'     => 'How did you find us?',
				'options'  => array( 'choices' => array( 'Search', 'A friend' ) ),
			),
			array(
				'id'       => self::TEXT_ID,
				'position' => 5,
				'type'     => 'text_area',
				'text'     => 'Anything else?',
				'options'  => null,
			),
			array(
				'id'       => self::CONDITIONAL_ID,
				'position' => 6,
				'type'     => 'conditional',
				'text'     => 'Why are you really leaving?',
				'options'  => array( 'choices' => array(
					array( 'text' => 'Found a better plugin', 'requires_text' => false ),
					array( 'text' => 'Other', 'requires_text' => true ),
				) ),
			),
		);
	}

	/** @param array<int, array<string, mixed>>|null $questions */
	private function queue_questions( ?array $questions = null ): void {
		$this->transport->queue(
			Response::from_http(
				200,
				array(),
				json_encode( array( 'questions' => null !== $questions ? $questions : $this->question_payload() ) )
			)
		);
	}

	private function queue_created(): void {
		$this->transport->queue(
			Response::from_http( 201, array(), json_encode( array( 'id' => 'r1', 'answer_count' => 2 ) ) )
		);
	}

	// -----------------------------------------------------------------
	// Fetching
	// -----------------------------------------------------------------

	public function test_it_fetches_and_normalizes_the_questions(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$questions = $survey->questions();

		$this->assertCount( 6, $questions );
		$this->assertSame( self::RADIO_ID, $questions[0]['id'] );
		$this->assertSame( 'radio', $questions[0]['type'] );
		$this->assertSame( 'Why are you deactivating?', $questions[0]['text'] );
		$this->assertSame( array( 'Too complicated', 'Found a better plugin' ), $questions[0]['options']['choices'] );

		$request = $this->transport->last_request();
		$this->assertSame( 'GET', $request['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/survey-questions', $request['url'] );
	}

	public function test_the_questions_are_cached_so_a_click_never_waits_on_the_api(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$survey->questions();
		$before = $this->transport->count();

		$this->assertCount( 6, $survey->questions() );
		$this->assertSame( $before, $this->transport->count(), 'the second read came from the cache' );
	}

	/**
	 * The common case: most products never configure a survey. Caching the
	 * empty answer is the difference between one request ever and one on
	 * every visit to the plugins screen.
	 */
	public function test_no_survey_configured_is_cached_too(): void {
		$survey = $this->survey();
		$this->queue_questions( array() );

		$this->assertSame( array(), $survey->questions() );

		$before = $this->transport->count();
		$this->assertSame( array(), $survey->questions() );
		$this->assertSame( $before, $this->transport->count() );
	}

	/**
	 * A 500 is not evidence that a product has no survey — caching it would
	 * silently disable the survey for twelve hours over one bad response.
	 */
	public function test_a_failed_fetch_is_not_cached(): void {
		$survey = $this->survey();
		$this->transport->queue( Response::from_http( 500, array(), '{"message":"boom"}' ) );

		$this->assertSame( array(), $survey->questions() );
		$this->assertTrue( $this->logger->contains( 'Could not fetch the uninstall survey' ) );

		$this->queue_questions();
		$this->assertCount( 6, $survey->questions(), 'the next attempt asks again' );
	}

	public function test_an_unreachable_api_yields_no_survey_rather_than_an_error(): void {
		$survey = $this->survey();

		// Nothing queued in the transport.
		$this->assertSame( array(), $survey->questions() );
	}

	/**
	 * Registration is asynchronous, so the plugins screen can be reached
	 * before it finishes. That must not be remembered as "no survey".
	 */
	public function test_an_unregistered_site_asks_for_nothing_and_caches_nothing(): void {
		$survey = $this->survey( false );

		$this->assertSame( array(), $survey->questions() );
		$this->assertSame( 0, $this->transport->count() );

		$registered = $this->survey();
		$this->queue_questions();
		$this->assertCount( 6, $registered->questions() );
	}

	public function test_a_malformed_question_is_dropped_rather_than_rendered_blank(): void {
		$survey = $this->survey();
		$this->queue_questions(
			array(
				array( 'id' => self::RADIO_ID, 'type' => 'radio', 'text' => '', 'options' => array( 'choices' => array( 'a' ) ) ),
				array( 'id' => self::TEXT_ID, 'type' => 'text_area', 'text' => 'Anything else?' ),
				array( 'type' => 'text_area', 'text' => 'No id' ),
				'not an array',
			)
		);

		$questions = $survey->questions();

		$this->assertCount( 1, $questions );
		$this->assertSame( self::TEXT_ID, $questions[0]['id'] );
		$this->assertNull( $questions[0]['options'] );
	}

	// -----------------------------------------------------------------
	// The shared circuit breaker (13-realtime-config-delivery.md Part 7)
	// -----------------------------------------------------------------

	/**
	 * The exact scenario Part 7 exists for: the person standing at the
	 * Deactivate modal must not wait out a doomed request during an
	 * outage the breaker already knows about.
	 */
	public function test_an_open_circuit_skips_the_network_and_serves_the_stale_cache(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key' );
		$survey = $this->survey( true, $rc );

		$this->queue_questions();
		$survey->questions(); // warms the cache

		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();
		$this->assertTrue( $rc->is_open() );

		$before = $this->transport->count();
		$questions = $survey->questions( true ); // force, to prove the SKIP is the circuit, not the cache TTL

		$this->assertSame( $before, $this->transport->count(), 'an open circuit must not attempt a request' );
		$this->assertCount( 6, $questions );
	}

	/**
	 * And with nothing ever cached at all, an open circuit must yield an
	 * empty result — the modal's signal to let deactivation proceed
	 * rather than trapping the user.
	 */
	public function test_an_open_circuit_with_nothing_cached_yields_an_empty_result(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-2' );
		$rc->record_failure();
		$rc->record_failure();
		$rc->record_failure();

		$survey = $this->survey( true, $rc );

		$before = $this->transport->count();
		$this->assertSame( array(), $survey->questions() );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_a_successful_fetch_records_success_on_the_circuit(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-3' );
		$rc->record_failure();
		$rc->record_failure();

		$survey = $this->survey( true, $rc );
		$this->queue_questions();
		$survey->questions();

		$rc->record_failure();
		$this->assertFalse( $rc->is_open(), 'success must have reset the failure count, so one more failure is not the third' );
	}

	/**
	 * A failed fetch falls back to whatever was cached, HOWEVER STALE —
	 * not to an empty result. This is the behaviour change Part 7 makes:
	 * previously a failure discarded an expired-but-still-useful cache.
	 */
	public function test_a_failed_fetch_falls_back_to_the_stale_cache_rather_than_emptying_it(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-4' );
		$survey = $this->survey( true, $rc );

		$this->queue_questions();
		$survey->questions(); // caches 5 questions

		$this->transport->queue( Response::from_http( 500, array(), '{"message":"boom"}' ) );
		$questions = $survey->questions( true );

		$this->assertCount( 6, $questions, 'a failed fetch must fall back to the last cached answer, not an empty one' );
	}

	/**
	 * The same fallback, specifically for the 3-second fast-client
	 * timeout the modal actually uses (Sdk::bootstrap()'s $fastClient) —
	 * a timeout is a transport error, not an HTTP response, so it takes
	 * a different Response constructor than the 500 case above.
	 */
	public function test_a_timed_out_request_falls_back_to_the_stale_cache(): void {
		$rc     = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-timeout' );
		$survey = $this->survey( true, $rc );

		$this->queue_questions();
		$survey->questions(); // caches 5 questions

		$this->transport->queue( Response::from_transport_error( 'Operation timed out after 3000 milliseconds' ) );
		$questions = $survey->questions( true );

		$this->assertCount( 6, $questions, 'a timeout must fall back to the last cached answer, not an empty one' );
	}

	public function test_a_failed_fetch_records_a_failure_on_the_circuit(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-5' );
		$survey = $this->survey( true, $rc );

		$this->transport->queue( Response::from_http( 500, array(), '' ) );
		$survey->questions();

		$rc->record_failure();
		$rc->record_failure();
		$this->assertTrue( $rc->is_open(), 'this should be the third failure counting the one questions() itself recorded' );
	}

	public function test_a_429_backs_off_via_retry_after_rather_than_the_fixed_threshold(): void {
		$rc = new \Appneck\Sdk\RealtimeConfig( 'circuit-key-6' );
		$survey = $this->survey( true, $rc );

		$this->transport->queue(
			Response::from_http( 429, array( 'retry-after' => '60' ), '{"message":"Rate limit exceeded"}' )
		);
		$survey->questions();

		$this->assertTrue( $rc->is_open(), 'a single 429 must open the circuit immediately' );
	}

	public function test_no_realtime_config_wired_behaves_exactly_as_before(): void {
		$survey = $this->survey(); // no RealtimeConfig at all
		$this->queue_questions();

		$this->assertCount( 6, $survey->questions() );
	}

	public function test_forget_clears_the_cached_questions(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$survey->questions();

		$survey->forget();

		$this->queue_questions();
		$survey->questions();

		$this->assertSame( 2, $this->transport->count(), 'it asked again after forgetting' );
	}

	// -----------------------------------------------------------------
	// Local validation — mirrors the server's rules
	// -----------------------------------------------------------------

	public function test_valid_answers_pass(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertSame(
			array(),
			$survey->validate(
				array(
					self::RADIO_ID    => 'Too complicated',
					self::CHECKBOX_ID => array( 'Speed', 'Price' ),
					self::RATING_ID   => '4',
					self::DROPDOWN_ID => 'Search',
					self::TEXT_ID     => 'Nothing else.',
				)
			)
		);
	}

	public function test_a_choice_that_is_not_on_the_list_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$errors = $survey->validate( array( self::RADIO_ID => 'Something I made up' ) );

		$this->assertArrayHasKey( self::RADIO_ID, $errors );
	}

	public function test_a_checkbox_answer_outside_the_choices_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertArrayHasKey(
			self::CHECKBOX_ID,
			$survey->validate( array( self::CHECKBOX_ID => array( 'Speed', 'Invented' ) ) )
		);
	}

	public function test_a_duplicated_checkbox_selection_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertArrayHasKey(
			self::CHECKBOX_ID,
			$survey->validate( array( self::CHECKBOX_ID => array( 'Speed', 'Speed' ) ) )
		);
	}

	public function test_a_rating_above_the_configured_max_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertArrayHasKey( self::RATING_ID, $survey->validate( array( self::RATING_ID => 9 ) ) );
		$this->assertArrayHasKey( self::RATING_ID, $survey->validate( array( self::RATING_ID => 0 ) ) );
		$this->assertArrayHasKey( self::RATING_ID, $survey->validate( array( self::RATING_ID => 'five' ) ) );
	}

	public function test_an_over_long_comment_is_rejected_at_the_servers_own_limit(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertSame( array(), $survey->validate( array( self::TEXT_ID => str_repeat( 'a', Survey::TEXT_AREA_MAX_LENGTH ) ) ) );
		$this->assertArrayHasKey(
			self::TEXT_ID,
			$survey->validate( array( self::TEXT_ID => str_repeat( 'a', Survey::TEXT_AREA_MAX_LENGTH + 1 ) ) )
		);
	}

	/**
	 * There is no field to attach the message to, so it cannot be an error
	 * the site owner is asked to fix — it is dropped on submission instead.
	 */
	public function test_an_answer_for_an_unknown_question_is_not_a_visible_error(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertSame( array(), $survey->validate( array( 'not-a-question' => 'value' ) ) );
	}

	public function test_a_valid_conditional_answer_with_follow_up_text_passes(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertSame(
			array(),
			$survey->validate( array( self::CONDITIONAL_ID => array( 'value' => 'Other', 'text' => 'It stopped working after an update.' ) ) )
		);
	}

	/**
	 * requires_text is a hint for the modal to reveal a field, never a
	 * requirement the SDK enforces — omitting the follow-up must still
	 * pass, even for a choice marked requires_text.
	 */
	public function test_a_conditional_answer_may_omit_the_follow_up_text(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertSame(
			array(),
			$survey->validate( array( self::CONDITIONAL_ID => array( 'value' => 'Other' ) ) )
		);
	}

	public function test_a_conditional_value_not_on_the_list_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertArrayHasKey(
			self::CONDITIONAL_ID,
			$survey->validate( array( self::CONDITIONAL_ID => array( 'value' => 'Invented' ) ) )
		);
	}

	public function test_an_over_long_conditional_follow_up_is_rejected(): void {
		$survey = $this->survey();
		$this->queue_questions();

		$this->assertArrayHasKey(
			self::CONDITIONAL_ID,
			$survey->validate( array(
				self::CONDITIONAL_ID => array( 'value' => 'Other', 'text' => str_repeat( 'a', Survey::TEXT_AREA_MAX_LENGTH + 1 ) ),
			) )
		);
	}

	// -----------------------------------------------------------------
	// Submission
	// -----------------------------------------------------------------

	public function test_it_submits_the_answers_in_the_servers_shape(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();
		$this->queue_created();

		$response = $survey->submit(
			array(
				self::RADIO_ID    => 'Found a better plugin',
				self::CHECKBOX_ID => array( 'Speed', 'Price' ),
				self::RATING_ID   => '4',
				self::TEXT_ID     => 'Good plugin, wrong fit.',
			),
			$questions
		);

		$this->assertSame( 201, $response->status() );

		$request = $this->transport->last_request();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( self::BASE_URL . '/sdk/v1/surveys', $request['url'] );

		$body = json_decode( $request['body'], true );
		$this->assertArrayHasKey( 'submitted_at', $body );

		$byId = array();
		foreach ( $body['answers'] as $answer ) {
			$byId[ $answer['question_id'] ] = $answer['value'];
		}

		$this->assertSame( 'Found a better plugin', $byId[ self::RADIO_ID ] );
		$this->assertSame( array( 'Speed', 'Price' ), $byId[ self::CHECKBOX_ID ] );
		// Cast so the dashboard does not tally "4" and 4 separately.
		$this->assertSame( 4, $byId[ self::RATING_ID ] );
	}

	public function test_a_conditional_answer_is_submitted_with_its_follow_up_text(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();
		$this->queue_created();

		$survey->submit(
			array( self::CONDITIONAL_ID => array( 'value' => 'Other', 'text' => 'It stopped working after an update.' ) ),
			$questions
		);

		$body = json_decode( $this->transport->last_request()['body'], true );
		$byId = array();
		foreach ( $body['answers'] as $answer ) {
			$byId[ $answer['question_id'] ] = $answer;
		}

		$this->assertSame( 'Other', $byId[ self::CONDITIONAL_ID ]['value'] );
		$this->assertSame( 'It stopped working after an update.', $byId[ self::CONDITIONAL_ID ]['text'] );
	}

	/**
	 * The follow-up is optional: a chosen value with no text still submits,
	 * and the wire payload simply carries no 'text' key for it rather than
	 * an empty string.
	 */
	public function test_a_conditional_answer_without_follow_up_text_omits_the_text_key(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();
		$this->queue_created();

		$survey->submit(
			array( self::CONDITIONAL_ID => array( 'value' => 'Found a better plugin' ) ),
			$questions
		);

		$body = json_decode( $this->transport->last_request()['body'], true );
		$byId = array();
		foreach ( $body['answers'] as $answer ) {
			$byId[ $answer['question_id'] ] = $answer;
		}

		$this->assertSame( 'Found a better plugin', $byId[ self::CONDITIONAL_ID ]['value'] );
		$this->assertArrayNotHasKey( 'text', $byId[ self::CONDITIONAL_ID ] );
	}

	public function test_unanswered_questions_are_omitted_rather_than_sent_blank(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();
		$this->queue_created();

		$survey->submit(
			array(
				self::RADIO_ID    => 'Too complicated',
				self::CHECKBOX_ID => array(),
				self::RATING_ID   => '',
				self::TEXT_ID     => '   ',
			),
			$questions
		);

		$body = json_decode( $this->transport->last_request()['body'], true );

		$this->assertCount( 1, $body['answers'] );
		$this->assertSame( self::RADIO_ID, $body['answers'][0]['question_id'] );
	}

	/**
	 * An entirely blank form is a skip. Sending it would store a response
	 * that dilutes the organization's tallies with an empty row — and the
	 * server requires at least one answer anyway.
	 */
	public function test_an_entirely_blank_form_is_not_submitted_at_all(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();

		$before = $this->transport->count();

		$this->assertNull( $survey->submit( array( self::TEXT_ID => '' ), $questions ) );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_invalid_answers_are_not_sent(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();

		$before = $this->transport->count();

		$this->assertNull( $survey->submit( array( self::RATING_ID => 99 ), $questions ) );
		$this->assertSame( $before, $this->transport->count() );
	}

	public function test_nothing_is_submitted_when_the_product_has_no_survey(): void {
		$survey = $this->survey();
		$this->queue_questions( array() );

		$this->assertNull( $survey->submit( array( self::TEXT_ID => 'anything' ) ) );
	}

	/**
	 * One attempt, no queue, no retry. There is nowhere to retry from: the
	 * plugin is being deactivated as this returns.
	 */
	public function test_a_failed_submission_is_attempted_once_and_logged(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();

		$before = $this->transport->count();

		// Nothing queued: the network is gone.
		$response = $survey->submit( array( self::RADIO_ID => 'Too complicated' ), $questions );

		$this->assertTrue( $response->is_transport_error() );
		$this->assertSame( $before + 1, $this->transport->count(), 'exactly one attempt' );
		$this->assertTrue( $this->logger->contains( 'could not be submitted' ) );
	}

	public function test_a_server_rejection_is_returned_rather_than_thrown(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();

		$this->transport->queue(
			Response::from_http( 422, array(), json_encode( array( 'message' => 'One or more answers are not valid.' ) ) )
		);

		$response = $survey->submit( array( self::RADIO_ID => 'Too complicated' ), $questions );

		$this->assertSame( 422, $response->status() );
		$this->assertFalse( $response->ok() );
	}

	/**
	 * The server answers a repeat submission 200 with the response already
	 * on file (journal 9.3a's idempotency). That is a success, not an error.
	 */
	public function test_an_already_recorded_response_reads_as_success(): void {
		$survey = $this->survey();
		$this->queue_questions();
		$questions = $survey->questions();

		$this->transport->queue(
			Response::from_http( 200, array(), json_encode( array( 'message' => 'A survey response has already been recorded for this installation.' ) ) )
		);

		$response = $survey->submit( array( self::RADIO_ID => 'Too complicated' ), $questions );

		$this->assertTrue( $response->ok() );
		$this->assertFalse( $this->logger->contains( 'could not be submitted' ) );
	}
}
