<?php
	/*
	* @Author 		MagePeople Team
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.

	/**
	 * Temporary seat holds ("seat is mine for N minutes while I check out").
	 *
	 * A hold is a per-seat transient: wbtm_hold_{bus_id}_{date}_{seat} = owner token,
	 * expiring after the seat_hold_minutes setting (Global Settings -> General;
	 * default 10, 0 disables the feature). The owner token is a random 32-char hex
	 * string kept in the wbtm_hold_token cookie so a guest's holds survive across
	 * requests without login.
	 *
	 * Seat identifiers match what gets stored in wbtm_seat booking meta: the plain
	 * seat name for lower/upper deck seats, cabin_{index}_{seat} for cabin seats
	 * (see templates/layout/seat_plan.php and Pro WBTM_Standalone_Payment.php).
	 *
	 * Holds are bus-wide per date (the key carries no route segment), so a held
	 * seat is blocked for every segment of that bus on that date. This over-blocks
	 * relative to the segment-overlap booking logic, but never double-sells; the
	 * $start/$end args on the wbtm_query_seat_booked filter keep the door open for
	 * segment-aware holds later.
	 *
	 * Known race: without a persistent object cache the hold write is check-then-set
	 * (get_transient -> set_transient), so two simultaneous holds on the same seat
	 * can both succeed. The booking-time validation under the MySQL GET_LOCK in
	 * WBTM_Booking_Controller / WBTM_Standalone_Payment is the real safety net —
	 * worst case one customer loses the seat at submit and is told why. When an
	 * external object cache exists, an atomic wp_cache_add() fast path is used
	 * instead (pattern from Pro WBTM_AI_Chatbot).
	 */
	if (!class_exists('WBTM_Seat_Hold')) {
		class WBTM_Seat_Hold {
			const COOKIE_NAME = 'wbtm_hold_token';
			const HOLD_PREFIX = 'wbtm_hold_';
			const INDEX_PREFIX = 'wbtm_hold_index_';
			const CACHE_GROUP = 'wbtm_holds';
			const MAX_SEATS_PER_REQUEST = 50;

			public function __construct() {
				add_action('wp_ajax_wbtm_hold_seats', array($this, 'ajax_hold_seats'));
				add_action('wp_ajax_nopriv_wbtm_hold_seats', array($this, 'ajax_hold_seats'));
				add_action('wp_ajax_wbtm_release_seats', array($this, 'ajax_release_seats'));
				add_action('wp_ajax_nopriv_wbtm_release_seats', array($this, 'ajax_release_seats'));
				add_filter('wbtm_query_seat_booked', array($this, 'filter_query_seat_booked'), 10, 5);
			}

			/* ================= settings / token ================= */

			public static function get_ttl_seconds() {
				$minutes = (int) WBTM_Global_Function::get_settings('wbtm_global_settings', 'seat_hold_minutes', 10);
				return max(0, $minutes) * MINUTE_IN_SECONDS;
			}

			public static function is_enabled() {
				return self::get_ttl_seconds() > 0;
			}

			/**
			 * Current visitor's hold token from the cookie, or '' when absent/invalid.
			 */
			public static function get_token() {
				$token = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME])) : '';
				return preg_match('/^[a-f0-9]{32}$/', $token) ? $token : '';
			}

			/**
			 * Return the visitor's token, generating and cooking a fresh one on first use.
			 */
			public static function ensure_token() {
				$token = self::get_token();
				if (!$token) {
					$token = bin2hex(random_bytes(16));
					if (!headers_sent()) {
						setcookie(self::COOKIE_NAME, $token, time() + 7 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
					}
					$_COOKIE[self::COOKIE_NAME] = $token;
				}
				return $token;
			}

			/* ================= key helpers ================= */

			public static function normalize_date($date) {
				return gmdate('Y-m-d', strtotime($date));
			}

			private static function hold_key($bus_id, $date, $seat) {
				return self::HOLD_PREFIX . $bus_id . '_' . $date . '_' . $seat;
			}

			private static function index_key($bus_id, $date) {
				return self::INDEX_PREFIX . $bus_id . '_' . $date;
			}

			/**
			 * Cabin-aware seat identifier used by both the booking records and the
			 * hold keys: cabin_{index}_{seat} for cabin seats (cabin_{index}_dd_{seat}
			 * for a double-decker cabin's upper deck), plain name otherwise.
			 */
			public static function seat_identifier($seat_name, $cabin_index = '', $is_upper = false) {
				return ($cabin_index !== '' && $cabin_index !== null)
					? 'cabin_' . $cabin_index . ($is_upper ? '_dd_' : '_') . $seat_name
					: $seat_name;
			}

			/* ================= read API ================= */

			/**
			 * Owner token of the hold on a seat, or false when the seat is not held.
			 */
			public static function get_hold_owner($bus_id, $date, $seat) {
				if (!self::is_enabled()) {
					return false;
				}
				$owner = get_transient(self::hold_key($bus_id, $date, $seat));
				return $owner ? $owner : false;
			}

			/**
			 * Is this seat held by someone OTHER than the given (or current) token?
			 */
			public static function is_held_by_other($bus_id, $date, $seat, $token = null) {
				$owner = self::get_hold_owner($bus_id, $date, $seat);
				if (!$owner) {
					return false;
				}
				$token = $token === null ? self::get_token() : $token;
				return $owner !== $token;
			}

			/**
			 * Seat identifiers from $seat_infos (each with seat_name and optional
			 * cabin_index) that are currently held by another visitor.
			 */
			public static function seats_held_by_others($bus_id, $date, $seat_infos) {
				$conflicts = array();
				if (!self::is_enabled() || !is_array($seat_infos)) {
					return $conflicts;
				}
				$date = self::normalize_date($date);
				$token = self::get_token();
				foreach ($seat_infos as $seat_info) {
					if (!is_array($seat_info)) {
						continue;
					}
					$seat_name = isset($seat_info['seat_name']) ? $seat_info['seat_name'] : '';
					if (!$seat_name) {
						continue;
					}
					$cabin_index = isset($seat_info['cabin_index']) ? $seat_info['cabin_index'] : '';
					$identifier = !empty($seat_info['seat_identifier'])
						? $seat_info['seat_identifier']
						: self::seat_identifier($seat_name, $cabin_index, !empty($seat_info['is_upper']));
					if (self::is_held_by_other($bus_id, $date, $identifier, $token)) {
						$conflicts[] = $identifier;
					}
				}
				return $conflicts;
			}

			/* ================= write API ================= */

			/**
			 * Write holds for the given seats (already in identifier form).
			 * Seats already held by ANOTHER token are skipped and reported as
			 * conflicts; the caller's own existing holds are simply refreshed.
			 *
			 * @return array {held: string[], conflicts: string[]}
			 */
			public static function hold_seats($bus_id, $date, $seats, $token) {
				$held = array();
				$conflicts = array();
				$ttl = self::get_ttl_seconds();
				if ($ttl <= 0 || !$token) {
					return array('held' => $held, 'conflicts' => $conflicts);
				}
				$date = self::normalize_date($date);
				$index = get_transient(self::index_key($bus_id, $date));
				$index = is_array($index) ? $index : array();
				$index_changed = false;
				foreach ($seats as $seat) {
					$key = self::hold_key($bus_id, $date, $seat);
					$owner = get_transient($key);
					if ($owner && $owner !== $token) {
						$conflicts[] = $seat;
						continue;
					}
					$acquired = false;
					if (wp_using_ext_object_cache()) {
						// Atomic fast path: only one concurrent request can win the add.
						if (wp_cache_add($key, $token, self::CACHE_GROUP, $ttl)) {
							$acquired = true;
						} else {
							$cache_owner = wp_cache_get($key, self::CACHE_GROUP);
							if ($cache_owner === $token) {
								wp_cache_set($key, $token, self::CACHE_GROUP, $ttl);
								$acquired = true;
							}
						}
						if (!$acquired) {
							$conflicts[] = $seat;
							continue;
						}
					}
					// Single read path for every consumer (availability filters,
					// enforcement) is the transient — mirror the atomic claim into it.
					set_transient($key, $token, $ttl);
					$held[] = $seat;
					if (!isset($index[$seat])) {
						$index[$seat] = $token;
						$index_changed = true;
					}
				}
				if ($index_changed) {
					set_transient(self::index_key($bus_id, $date), $index, $ttl);
				}
				return array('held' => $held, 'conflicts' => $conflicts);
			}

			/**
			 * Delete the caller's own holds for the given seats (identifier form).
			 * Holds owned by another token are left untouched.
			 */
			public static function release_seats($bus_id, $date, $seats, $token) {
				if (!$token) {
					return;
				}
				$date = self::normalize_date($date);
				$index_key = self::index_key($bus_id, $date);
				$index = get_transient($index_key);
				$index = is_array($index) ? $index : array();
				$index_changed = false;
				foreach ($seats as $seat) {
					$key = self::hold_key($bus_id, $date, $seat);
					$owner = get_transient($key);
					if ($owner && $owner === $token) {
						delete_transient($key);
						if (wp_using_ext_object_cache()) {
							wp_cache_delete($key, self::CACHE_GROUP);
						}
					}
					if (isset($index[$seat])) {
						unset($index[$seat]);
						$index_changed = true;
					}
				}
				if ($index_changed) {
					$ttl = self::get_ttl_seconds();
					if ($ttl > 0) {
						set_transient($index_key, $index, $ttl);
					}
				}
			}

			/**
			 * Release the caller's holds for seats that were just booked (the booking
			 * record itself blocks the seat from now on). $seat_infos items carry
			 * seat_name and optional cabin_index, like the cart/booking arrays.
			 */
			public static function release_booked_seats($bus_id, $date, $seat_infos) {
				if (!self::is_enabled() || !is_array($seat_infos)) {
					return;
				}
				$token = self::get_token();
				if (!$token) {
					return;
				}
				$seats = array();
				foreach ($seat_infos as $seat_info) {
					if (!is_array($seat_info)) {
						continue;
					}
					$seat_name = isset($seat_info['seat_name']) ? $seat_info['seat_name'] : '';
					if (!$seat_name) {
						continue;
					}
					$cabin_index = isset($seat_info['cabin_index']) ? $seat_info['cabin_index'] : '';
					$seats[] = !empty($seat_info['seat_identifier'])
						? $seat_info['seat_identifier']
						: self::seat_identifier($seat_name, $cabin_index, !empty($seat_info['is_upper']));
				}
				if (!empty($seats)) {
					self::release_seats($bus_id, $date, $seats, $token);
				}
			}

			/* ================= availability integration ================= */

			/**
			 * wbtm_query_seat_booked filter: seats held by another visitor render as
			 * booked on the seat plans (lower deck + cabins via query_seat_booked;
			 * the upper deck path goes through query_total_booked, which consults
			 * WBTM_Seat_Hold::is_held_by_other() directly).
			 */
			public function filter_query_seat_booked($booked, $post_id, $start, $end, $date) {
				if (!self::is_enabled()) {
					return $booked;
				}
				$booked = is_array($booked) ? $booked : array();
				$date = self::normalize_date($date);
				$index = get_transient(self::index_key($post_id, $date));
				if (!is_array($index) || empty($index)) {
					return $booked;
				}
				$token = self::get_token();
				foreach ($index as $seat => $index_owner) {
					// The index can outlive an individual hold (it expires with the
					// newest hold); trust only seats whose hold transient is still live.
					$owner = get_transient(self::hold_key($post_id, $date, $seat));
					if ($owner && $owner !== $token && !in_array($seat, $booked, true)) {
						$booked[] = $seat;
					}
				}
				return $booked;
			}

			/* ================= AJAX ================= */

			/**
			 * Shared request parsing for both endpoints.
			 *
			 * @return array {bus_id, date, start, end, seats}
			 */
			private static function parse_request() {
				$bus_id = isset($_POST['bus_id']) ? absint($_POST['bus_id']) : 0;
				$date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
				$start = isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '';
				$end = isset($_POST['end']) ? sanitize_text_field(wp_unslash($_POST['end'])) : '';
				$seats = isset($_POST['seats']) ? (array) wp_unslash($_POST['seats']) : array();
				$seats = array_slice(array_filter(array_map('sanitize_text_field', $seats)), 0, self::MAX_SEATS_PER_REQUEST);
				return array(
					'bus_id' => $bus_id,
					'date' => $date,
					'start' => $start,
					'end' => $end,
					'seats' => $seats,
				);
			}

			public function ajax_hold_seats() {
				check_ajax_referer('wtbm_ajax_nonce', 'nonce');
				$request = self::parse_request();
				if (!$request['bus_id'] || !$request['date'] || empty($request['seats'])) {
					wp_send_json_error(array('message' => __('Invalid hold request.', 'bus-ticket-booking-with-seat-reservation')), 400);
				}
				if (!self::is_enabled()) {
					wp_send_json_success(array('expires' => 0, 'ttl' => 0, 'held' => array(), 'conflicts' => array()));
				}
				$date = self::normalize_date($request['date']);
				$token = self::ensure_token();
				// Never hand out a hold on a seat that is already booked. Uses the
				// seat-name path of query_total_booked when the route context was
				// sent (holds themselves stay bus-wide per date).
				$seats = array();
				$booked_conflicts = array();
				foreach ($request['seats'] as $seat) {
					if ($request['start'] && $request['end']
						&& WBTM_Query::query_total_booked($request['bus_id'], $request['start'], $request['end'], $date, '', $seat) > 0) {
						$booked_conflicts[] = $seat;
						continue;
					}
					$seats[] = $seat;
				}
				$result = self::hold_seats($request['bus_id'], $date, $seats, $token);
				$conflicts = array_merge($booked_conflicts, $result['conflicts']);
				wp_send_json_success(array(
					'expires' => !empty($result['held']) ? time() + self::get_ttl_seconds() : 0,
					'ttl' => self::get_ttl_seconds(),
					'held' => $result['held'],
					'conflicts' => $conflicts,
				));
			}

			public function ajax_release_seats() {
				check_ajax_referer('wtbm_ajax_nonce', 'nonce');
				$request = self::parse_request();
				if (!$request['bus_id'] || !$request['date'] || empty($request['seats'])) {
					wp_send_json_error(array('message' => __('Invalid release request.', 'bus-ticket-booking-with-seat-reservation')), 400);
				}
				$token = self::get_token();
				if ($token) {
					self::release_seats($request['bus_id'], $request['date'], $request['seats'], $token);
				}
				wp_send_json_success(array('released' => $request['seats']));
			}
		}
		new WBTM_Seat_Hold();
	}
