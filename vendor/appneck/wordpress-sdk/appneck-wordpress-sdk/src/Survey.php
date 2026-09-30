<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;

/**
 * The uninstall survey: fetching a product's questions, checking an
 * answer locally, and submitting it once.
 *
 * ## Nothing here is queued, deliberately
 *
 * Telemetry buffers because it is high-volume and best-effort. A survey is
 * the opposite: one submission per uninstall, with a person waiting for
 * the page to move on. So this makes ONE attempt and returns whatever
 * happened. There is no retry and no local queue, because there is
 * nowhere natural to retry from — the plugin is being deactivated, the
 * moment has passed, and the server's own design is one response per
 * installation (journal §9.3a), so a resurrected submission days later
 * would be a duplicate at best.
 *
 * ## The cache is a fallback, never a gate, at the moment that matters
 *
 * The modal-open call (Admin\DeactivationSurvey::handle_ajax(), 'questions'
 * op) always passes $force = true: it needs THIS product's CURRENT
 * survey, not whatever was true up to 12 hours ago, and a survey edited
 * minutes before someone clicks Deactivate must be visible immediately.
 * A real bug shipped here once — the cache was checked first
 * regardless of $force's default, so an edit made after the last
 * natural fetch stayed invisible for up to CACHE_TTL, and nothing ever
 * passed $force = true to notice. What CACHE_TTL still legitimately
 * governs: how long the fallback copy is trusted when a live attempt is
 * skipped (breaker open) or fails (network/timeout) — see the failure
 * branch of questions() below — and how often the 'submit' op's own
 * (un-forced) re-fetch has to touch the network rather than reuse the
 * copy the 'questions' op just refreshed seconds earlier. A product
 * with no survey configured is the common case and must not mean an
 * API call on every visit to the plugins screen that never opens the
 * modal at all.
 *
 * ## Local validation, but the server is still the authority
 *
 * validate() mirrors POST /sdk/v1/surveys' rules so a mistake surfaces
 * as an inline message next to the field instead of as a rejected
 * submission nobody sees. It is a UX affordance, not a security boundary:
 * the server re-checks everything, and a disagreement between the two is
 * resolved in the server's favour by simply not blocking deactivation.
 */
final class Survey {

	/** Matches SurveyController::TEXT_AREA_MAX_LENGTH. */
	const TEXT_AREA_MAX_LENGTH = 2000;

	/** 12 hours. A survey is edited a handful of times ever. */
	const CACHE_TTL = 43200;

	/** @var Client */
	private $client;

	/** @var Logger */
	private $logger;

	/** @var string */
	private $key;

	/** @var RealtimeConfig|null */
	private $realtime_config;

	/**
	 * @param RealtimeConfig|null $realtime_config The shared circuit
	 *        breaker (13-realtime-config-delivery.md). Optional and
	 *        defaulted so existing callers keep working unchanged; a
	 *        survey constructed without one simply never skips the
	 *        network on an open circuit it has no way to know about.
	 */
	public function __construct( Client $client, ?Logger $logger = null, ?RealtimeConfig $realtime_config = null ) {
		$this->client          = $client;
		$this->logger          = null !== $logger ? $logger : new NullLogger();
		$this->key             = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );
		$this->realtime_config = $realtime_config;
	}

	// -----------------------------------------------------------------
	// Questions
	// -----------------------------------------------------------------

	/**
	 * This product's questions, oldest cache first.
	 *
	 * @param bool $force Ignore the cache and re-fetch.
	 * @return array<int, array<string, mixed>> Empty when there is no
	 *                                          survey, when the site is
	 *                                          not registered, or when
	 *                                          the fetch failed — all
	 *                                          three mean the same thing
	 *                                          to a caller: no survey to
	 *                                          show, carry on.
	 */
	public function questions( $force = false ) {
		if ( ! $force ) {
			$cached = $this->cached_questions();

			if ( null !== $cached ) {
				return $cached;
			}
		}

		if ( ! $this->client->credentials()->has_credentials() ) {
			// Nothing to sign with. Not cached as an empty survey: the
			// site may register moments later, and caching "no survey" for
			// twelve hours because registration had not finished yet would
			// silently skip the survey on a site that has one.
			return array();
		}

		// The circuit breaker (13-realtime-config-delivery.md, shared
		// with the announcements refresh and the config poll): after
		// repeated failures elsewhere, skip the network entirely rather
		// than making the one person standing at the Deactivate modal
		// wait out a doomed 3-second timeout. Falls back to whatever is
		// cached, however stale — see stale_cached_questions().
		if ( null !== $this->realtime_config && $this->realtime_config->is_open() ) {
			return $this->stale_cached_questions();
		}

		$response = $this->client->get( '/sdk/v1/survey-questions' );

		if ( ! $response->ok() ) {
			$this->logger->error(
				'Could not fetch the uninstall survey; deactivation will proceed without it.',
				array(
					'status' => $response->status(),
					'error'  => $response->error_message(),
				)
			);

			if ( null !== $this->realtime_config ) {
				$this->realtime_config->record_failure(
					$response->is_rate_limited() ? $response->rate_limit()->retry_after() : null
				);
			}

			// Falls back to the LAST cached answer, however stale, rather
			// than an empty result — a timeout or an outage is not
			// evidence that this product has no survey, and the site
			// owner standing at the modal right now is better served by
			// yesterday's questions than by none at all. Genuinely
			// nothing cached still means an empty array, which is what
			// tells the modal to let deactivation proceed unblocked.
			return $this->stale_cached_questions();
		}

		if ( null !== $this->realtime_config ) {
			$this->realtime_config->record_success();
		}

		$questions = $this->normalize_questions( $response->get( 'questions', array() ) );

		// An empty list IS cached — "this product has no survey" is a real
		// answer and the most common one, and re-asking on every visit to
		// the plugins screen would be a request per page load for nothing.
		$this->cache_questions( $questions );

		return $questions;
	}

	/**
	 * Drops anything a malformed or hostile response could smuggle
	 * through, and keeps exactly the five fields the renderer uses. A
	 * question missing text or type cannot be displayed, so it is
	 * discarded rather than rendered half-formed.
	 *
	 * @param mixed $questions
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_questions( $questions ) {
		if ( ! is_array( $questions ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $questions as $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}

			if ( empty( $question['id'] ) || empty( $question['type'] ) || ! isset( $question['text'] ) ) {
				continue;
			}

			if ( '' === (string) $question['text'] ) {
				continue;
			}

			$normalized[] = array(
				'id'       => (string) $question['id'],
				'position' => isset( $question['position'] ) ? (int) $question['position'] : count( $normalized ) + 1,
				'type'     => (string) $question['type'],
				'text'     => (string) $question['text'],
				'options'  => isset( $question['options'] ) && is_array( $question['options'] )
					? $question['options']
					: null,
			);
		}

		return $normalized;
	}

	// -----------------------------------------------------------------
	// Validation
	// -----------------------------------------------------------------

	/**
	 * Mirrors POST /sdk/v1/surveys' per-type rules (journal §9.3a).
	 *
	 * @param array<string, mixed>                  $values    question id => value.
	 * @param array<int, array<string, mixed>>|null $questions Defaults to questions().
	 * @return array<string, string> question id => message. Empty when fine.
	 */
	public function validate( array $values, ?array $questions = null ) {
		$questions = null !== $questions ? $questions : $this->questions();
		$by_id     = array();

		foreach ( $questions as $question ) {
			$by_id[ $question['id'] ] = $question;
		}

		$errors = array();

		foreach ( $values as $id => $value ) {
			// Blank is not an answer and not an error either. Every
			// question is optional (the builder has no required flag), so
			// an untouched field must pass validation and then be dropped
			// on submission — not stop a submission the site owner filled
			// in perfectly well elsewhere.
			if ( $this->is_blank( $value ) ) {
				continue;
			}

			if ( ! isset( $by_id[ $id ] ) ) {
				// Not surfaced to the site owner — there is no field to
				// attach it to. Dropped on submission instead; see
				// answers_for().
				continue;
			}

			$question = $by_id[ $id ];
			$choices  = isset( $question['options']['choices'] ) && is_array( $question['options']['choices'] )
				? $question['options']['choices']
				: array();

			switch ( $question['type'] ) {
				case 'radio':
				case 'dropdown':
					if ( ! is_string( $value ) || ! in_array( $value, $choices, true ) ) {
						$errors[ $id ] = 'Please choose one of the listed options.';
					}
					break;

				case 'checkbox':
					if ( ! is_array( $value ) ) {
						$errors[ $id ] = 'Please choose from the listed options.';
						break;
					}

					foreach ( $value as $selected ) {
						if ( ! is_string( $selected ) || ! in_array( $selected, $choices, true ) ) {
							$errors[ $id ] = 'Please choose from the listed options.';
							break 2;
						}
					}

					if ( count( $value ) !== count( array_unique( $value ) ) ) {
						$errors[ $id ] = 'The same option is selected twice.';
					}
					break;

				case 'rating':
					$max = isset( $question['options']['max'] ) ? (int) $question['options']['max'] : 5;

					if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
						$errors[ $id ] = 'Please choose a rating.';
						break;
					}

					$rating = (int) $value;

					if ( $rating < 1 || $rating > $max ) {
						$errors[ $id ] = 'Please choose a rating between 1 and ' . $max . '.';
					}
					break;

				case 'text_area':
					if ( ! is_string( $value ) ) {
						$errors[ $id ] = 'Please enter text.';
						break;
					}

					// Matches the server's own cap. mb_strlen so a comment
					// in a non-Latin script is measured the way the server
					// measures it, not in bytes.
					if ( $this->length( $value ) > self::TEXT_AREA_MAX_LENGTH ) {
						$errors[ $id ] = 'Please keep this under ' . self::TEXT_AREA_MAX_LENGTH . ' characters.';
					}
					break;

				case 'conditional':
					// $value is {value: <chosen choice text>, text?: <optional
					// follow-up>} — see DeactivationSurvey::posted_answers(),
					// the only place that shape is built. $choices here is a
					// list of {text, requires_text} objects, not bare
					// strings, so the choice text has to be pulled out of
					// each one before comparing.
					$choice_texts = array();
					foreach ( $choices as $choice ) {
						if ( is_array( $choice ) && isset( $choice['text'] ) ) {
							$choice_texts[] = $choice['text'];
						}
					}

					$selected = is_array( $value ) && isset( $value['value'] ) ? $value['value'] : null;

					if ( ! is_string( $selected ) || ! in_array( $selected, $choice_texts, true ) ) {
						$errors[ $id ] = 'Please choose one of the listed options.';
						break;
					}

					// Optional even for a choice the admin marked
					// requires_text — that flag sets an expectation for the
					// modal to reveal a field, never a requirement the SDK
					// enforces.
					$follow_up = isset( $value['text'] ) ? $value['text'] : null;

					if ( null !== $follow_up && ( ! is_string( $follow_up ) || $this->length( $follow_up ) > self::TEXT_AREA_MAX_LENGTH ) ) {
						$errors[ $id ] = 'Please keep the extra detail under ' . self::TEXT_AREA_MAX_LENGTH . ' characters.';
					}
					break;

				default:
					// A type this SDK version does not know how to check.
					// Not an error the owner can fix, and the server will
					// reject it anyway — dropped on submission.
					break;
			}
		}

		return $errors;
	}

	// -----------------------------------------------------------------
	// Submission
	// -----------------------------------------------------------------

	/**
	 * One attempt. No retry, no queue.
	 *
	 * @param array<string, mixed>                  $values    question id => value.
	 * @param array<int, array<string, mixed>>|null $questions Defaults to questions().
	 * @return Response|null Null when there was nothing to submit (every
	 *                       answer blank, no questions, not registered) or
	 *                       when the answers did not pass validate().
	 */
	public function submit( array $values, ?array $questions = null ) {
		$questions = null !== $questions ? $questions : $this->questions();

		if ( array() === $questions ) {
			return null;
		}

		if ( ! $this->client->credentials()->has_credentials() ) {
			return null;
		}

		if ( array() !== $this->validate( $values, $questions ) ) {
			return null;
		}

		$answers = $this->answers_for( $values, $questions );

		// The server requires at least one answer, and an entirely blank
		// form is a skip in every meaningful sense — sending it would turn
		// "answered nothing" into a stored response that dilutes the
		// organization's tallies with an empty row.
		if ( array() === $answers ) {
			return null;
		}

		$response = $this->client->post(
			'/sdk/v1/surveys',
			array(
				'answers'      => $answers,
				'submitted_at' => gmdate( 'c' ),
			)
		);

		if ( ! $response->ok() ) {
			// Logged and dropped. Deactivation is already in flight by the
			// time a caller sees this, and there is no surface left to
			// report it on — see DeactivationSurvey's class doc.
			$this->logger->error(
				'The uninstall survey could not be submitted.',
				array(
					'status' => $response->status(),
					'error'  => $response->error_message(),
				)
			);
		}

		return $response;
	}

	/**
	 * The server's wire shape, with unanswered and unknown questions
	 * dropped.
	 *
	 * Blank is not an answer: every question is optional (the builder has
	 * no required flag), so a skipped field must be absent rather than
	 * submitted as an empty string — which would be counted as a real
	 * response by the dashboard's tallies.
	 *
	 * @param array<string, mixed>            $values
	 * @param array<int, array<string, mixed>> $questions
	 * @return array<int, array{question_id: string, value: mixed}>
	 */
	private function answers_for( array $values, array $questions ) {
		$types = array();

		foreach ( $questions as $question ) {
			$types[ $question['id'] ] = $question['type'];
		}

		$answers = array();

		foreach ( $values as $id => $value ) {
			if ( ! isset( $types[ $id ] ) ) {
				continue;
			}

			if ( 'checkbox' === $types[ $id ] ) {
				if ( ! is_array( $value ) || array() === $value ) {
					continue;
				}

				$answers[] = array( 'question_id' => (string) $id, 'value' => array_values( $value ) );

				continue;
			}

			if ( 'rating' === $types[ $id ] ) {
				if ( '' === $value || null === $value ) {
					continue;
				}

				$answers[] = array( 'question_id' => (string) $id, 'value' => (int) $value );

				continue;
			}

			if ( 'conditional' === $types[ $id ] ) {
				$selected = is_array( $value ) && isset( $value['value'] ) ? $value['value'] : null;

				if ( ! is_string( $selected ) || '' === trim( $selected ) ) {
					continue;
				}

				$answer = array( 'question_id' => (string) $id, 'value' => $selected );

				$follow_up = isset( $value['text'] ) ? $value['text'] : null;

				if ( is_string( $follow_up ) && '' !== trim( $follow_up ) ) {
					$answer['text'] = $follow_up;
				}

				$answers[] = $answer;

				continue;
			}

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$answers[] = array( 'question_id' => (string) $id, 'value' => $value );
		}

		return $answers;
	}

	// -----------------------------------------------------------------
	// Cache
	// -----------------------------------------------------------------

	/** @return array<int, array<string, mixed>>|null Null when absent or stale. */
	private function cached_questions() {
		$stored = $this->stored_cache();

		if ( null === $stored ) {
			return null;
		}

		if ( ( time() - (int) $stored['fetched_at'] ) > self::CACHE_TTL ) {
			return null;
		}

		return $stored['questions'];
	}

	/**
	 * The fallback for a failed or circuit-skipped fetch: whatever was
	 * last cached, with NO TTL check at all. CACHE_TTL governs whether a
	 * healthy fetch bothers re-asking; it has no bearing on whether an
	 * unhealthy one may still use what it has. An empty array here means
	 * genuinely nothing has ever been cached, which is the one case the
	 * modal is meant to read as "let deactivation proceed".
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function stale_cached_questions() {
		$stored = $this->stored_cache();

		return null === $stored ? array() : $stored['questions'];
	}

	/** @return array{fetched_at: int, questions: array<int, array<string, mixed>>}|null */
	private function stored_cache() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$stored = get_option( $this->option_name(), null );

		if ( ! is_array( $stored ) || ! isset( $stored['fetched_at'], $stored['questions'] ) || ! is_array( $stored['questions'] ) ) {
			return null;
		}

		return $stored;
	}

	/** @param array<int, array<string, mixed>> $questions */
	private function cache_questions( array $questions ) {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		// autoload 'no': read only on the plugins screen and in the
		// survey's own AJAX handler, never on an ordinary page load.
		update_option(
			$this->option_name(),
			array(
				'fetched_at' => time(),
				'questions'  => $questions,
			),
			false
		);
	}

	/**
	 * Called at uninstall — the cached questions are the plugin's data
	 * too. config_version-driven staleness is now handled centrally by
	 * RealtimeConfig, which owns its own forget(); this class no longer
	 * stores a marker of its own (13-realtime-config-delivery.md §6.2).
	 */
	public function forget() {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $this->option_name() );
		}
	}

	private function option_name() {
		return 'appneck_sdk_survey_questions_' . $this->key;
	}

	/**
	 * Whether a submitted value means "left alone". Whitespace counts:
	 * a comment box containing three spaces is an untouched box.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function is_blank( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return array() === $value;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		return false;
	}

	private function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
