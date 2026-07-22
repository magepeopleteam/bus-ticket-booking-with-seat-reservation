<?php
/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('WBTM_REST_API')) {
	/**
	 * Public read-only REST API (namespace wbtm/v1).
	 *
	 * Exposes the same data the frontend search/seat-plan already shows publicly:
	 * stops in use, bus search, single bus details and per-date seat availability.
	 * All routes are GET + permission_callback __return_true; a per-IP transient
	 * rate limit (pattern copied from Pro WBTM_AI_Chatbot, no Pro dependency)
	 * throttles abuse. No booking/cart endpoints by design.
	 */
	class WBTM_REST_API {
		public function __construct() {
			add_action('rest_api_init', array($this, 'register_routes'));
		}
		public function register_routes() {
			register_rest_route('wbtm/v1', '/routes', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_routes'),
				'permission_callback' => '__return_true',
			));
			register_rest_route('wbtm/v1', '/buses/search', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'search_buses'),
				'permission_callback' => '__return_true',
			));
			register_rest_route('wbtm/v1', '/buses/(?P<id>\d+)', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_bus'),
				'permission_callback' => '__return_true',
			));
			register_rest_route('wbtm/v1', '/buses/(?P<id>\d+)/seats', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_bus_seats'),
				'permission_callback' => '__return_true',
			));
		}
		/**
		 * Per-IP transient rate limit. Returns true, or a WP_Error (429) when the
		 * caller exceeded the per-minute cap. Same pattern as Pro WBTM_AI_Chatbot::check_rest_permission().
		 *
		 * @return true|WP_Error
		 */
		private function check_rate_limit() {
			$id   = is_user_logged_in()
				? 'u' . get_current_user_id()
				: 'ip' . md5(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown');
			$key  = 'wbtm_rest_rl_' . $id;
			$limit = (int) apply_filters('wbtm_rest_api_rate_limit', 30);
			$count = (int) get_transient($key);
			if ($count >= $limit) {
				return new WP_Error(
					'wbtm_rate_limited',
					__('Too many requests. Please try again in a minute.', 'bus-ticket-booking-with-seat-reservation'),
					array('status' => 429)
				);
			}
			set_transient($key, $count + 1, MINUTE_IN_SECONDS);
			return true;
		}
		/**
		 * Validate a Y-m-d date string.
		 *
		 * @return string|WP_Error Normalized date or 400 error.
		 */
		private function validate_date($date, $param = 'date') {
			$date = trim((string) $date);
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
				return new WP_Error(
					'wbtm_invalid_date',
					/* translators: %s: parameter name */
					sprintf(__('Invalid or missing "%s" parameter. Expected format: YYYY-MM-DD.', 'bus-ticket-booking-with-seat-reservation'), $param),
					array('status' => 400)
				);
			}
			return gmdate('Y-m-d', strtotime($date));
		}
		/**
		 * Load a published wbtm_bus post or return a 400 error.
		 *
		 * @return WP_Post|WP_Error
		 */
		private function validate_bus($bus_id) {
			$bus_id = absint($bus_id);
			$post   = $bus_id > 0 ? get_post($bus_id) : null;
			if (!$post || $post->post_type !== 'wbtm_bus' || $post->post_status !== 'publish') {
				return new WP_Error(
					'wbtm_invalid_bus',
					__('Bus not found.', 'bus-ticket-booking-with-seat-reservation'),
					array('status' => 400)
				);
			}
			return $post;
		}
		/**
		 * Boarding / dropping points actually used by at least one published bus,
		 * restricted to registered wbtm_bus_stops taxonomy terms.
		 */
		private function get_stops_in_use() {
			$boarding = WBTM_Functions::get_bus_route();
			$dropping = array();
			$bus_ids  = WBTM_Global_Function::get_all_post_id(WBTM_Functions::get_cpt());
			if (sizeof($bus_ids) > 0) {
				foreach ($bus_ids as $bus_id) {
					$dropping = array_merge($dropping, (array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_next_stops', []));
				}
			}
			$dropping = array_unique($dropping);
			$terms    = get_terms(array('taxonomy' => 'wbtm_bus_stops', 'hide_empty' => false));
			$names    = (!is_wp_error($terms) && sizeof($terms) > 0) ? wp_list_pluck($terms, 'name') : array();
			if (sizeof($names) > 0) {
				// Keep only stops registered in the taxonomy (see admin/WBTM_Taxonomy.php).
				$boarding = array_values(array_intersect($boarding, $names));
				$dropping = array_values(array_intersect($dropping, $names));
			}
			return array(
				'boarding_points' => array_values(array_unique($boarding)),
				'dropping_points' => array_values(array_unique($dropping)),
			);
		}
		/**
		 * Feature names attached to a bus (wbtm_bus_feature taxonomy, ids in meta).
		 */
		private function get_bus_features($bus_id) {
			$features    = array();
			$feature_ids = get_post_meta($bus_id, 'wbbm_bus_features_term_id', true);
			if (is_array($feature_ids) && sizeof($feature_ids) > 0) {
				foreach ($feature_ids as $term_id) {
					$term = get_term(absint($term_id), 'wbtm_bus_feature');
					if ($term && !is_wp_error($term)) {
						$features[] = $term->name;
					}
				}
			}
			return $features;
		}
		/**
		 * Full-route boarding point (first) and dropping point (last) for a bus.
		 */
		private function get_bus_endpoints($bus_id) {
			$bp_stops = (array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_bp_stops', []);
			$dp_stops = (array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_next_stops', []);
			return array(
				'bp' => sizeof($bp_stops) > 0 ? reset($bp_stops) : '',
				'dp' => sizeof($dp_stops) > 0 ? end($dp_stops) : '',
			);
		}
		/**
		 * Assemble one search-result entry (mirrors templates/layout/search_result.php data).
		 */
		private function build_search_item($bus_id, $bp, $dp, $date, $price_leg = 'outbound') {
			$all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $bp, $dp, $price_leg);
			if (sizeof($all_info) === 0) {
				return null;
			}
			$ticket_infos = WBTM_Functions::get_ticket_info($bus_id, $bp, $dp, $price_leg);
			$prices       = array();
			foreach ($ticket_infos as $ticket_info) {
				$prices[] = array(
					'type'  => $ticket_info['type'],
					'name'  => $ticket_info['name'],
					'price' => $ticket_info['price'],
				);
			}
			return array(
				'id'             => $bus_id,
				'name'           => get_the_title($bus_id),
				'type'           => WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category'),
				'boarding_point' => $all_info['bp'],
				'departure_time' => $all_info['bp_time'],
				'dropping_point' => $all_info['dp'],
				'arrival_time'   => $all_info['dp_time'],
				'prices'         => $prices,
				'total_seats'    => $all_info['total_seat'],
				'seats_left'     => $all_info['available_seat'],
				'features'       => $this->get_bus_features($bus_id),
			);
		}
		//==================================================//
		public function get_routes(WP_REST_Request $request) {
			$rate = $this->check_rate_limit();
			if (is_wp_error($rate)) {
				return $rate;
			}
			return rest_ensure_response($this->get_stops_in_use());
		}
		public function search_buses(WP_REST_Request $request) {
			$rate = $this->check_rate_limit();
			if (is_wp_error($rate)) {
				return $rate;
			}
			$bp = sanitize_text_field(wp_unslash((string) $request->get_param('bp')));
			$dp = sanitize_text_field(wp_unslash((string) $request->get_param('dp')));
			if ($bp === '' || $dp === '') {
				return new WP_Error(
					'wbtm_missing_param',
					__('Both "bp" (boarding point) and "dp" (dropping point) parameters are required.', 'bus-ticket-booking-with-seat-reservation'),
					array('status' => 400)
				);
			}
			$date = $this->validate_date($request->get_param('date'));
			if (is_wp_error($date)) {
				return $date;
			}
			$stops = $this->get_stops_in_use();
			if (!in_array($bp, array_merge($stops['boarding_points'], $stops['dropping_points']), true)
				|| !in_array($dp, array_merge($stops['boarding_points'], $stops['dropping_points']), true)) {
				return new WP_Error(
					'wbtm_unknown_stop',
					__('Unknown boarding or dropping point. See /wbtm/v1/routes for valid stops.', 'bus-ticket-booking-with-seat-reservation'),
					array('status' => 400)
				);
			}
			$return_date = $request->get_param('return_date');
			if ($return_date) {
				$return_date = $this->validate_date($return_date, 'return_date');
				if (is_wp_error($return_date)) {
					return $return_date;
				}
			}
			$buses = array();
			// get_bus_id() matches the route only; intersect with per-bus operational dates.
			foreach (WBTM_Query::get_bus_id($bp, $dp) as $bus_id) {
				if (!in_array($date, WBTM_Functions::get_post_date($bus_id), true)) {
					continue;
				}
				$item = $this->build_search_item($bus_id, $bp, $dp, $date);
				if ($item) {
					$buses[] = $item;
				}
			}
			$response = array(
				'boarding_point' => $bp,
				'dropping_point' => $dp,
				'date'           => $date,
				'buses'          => $buses,
			);
			if ($return_date) {
				$return_buses = array();
				foreach (WBTM_Query::get_bus_id($dp, $bp) as $bus_id) {
					if (!in_array($return_date, WBTM_Functions::get_post_date($bus_id), true)) {
						continue;
					}
					$item = $this->build_search_item($bus_id, $dp, $bp, $return_date, 'return');
					if ($item) {
						$return_buses[] = $item;
					}
				}
				$response['return_date'] = $return_date;
				$response['return_buses'] = $return_buses;
			}
			return rest_ensure_response($response);
		}
		public function get_bus(WP_REST_Request $request) {
			$rate = $this->check_rate_limit();
			if (is_wp_error($rate)) {
				return $rate;
			}
			$bus_id = absint($request->get_param('id'));
			$post   = $this->validate_bus($bus_id);
			if (is_wp_error($post)) {
				return $post;
			}
			$route_info  = (array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
			$route_stops = array();
			foreach ($route_info as $info) {
				if (!isset($info['place'], $info['time'])) {
					continue;
				}
				$route_stops[] = array(
					'place' => $info['place'],
					'type'  => isset($info['type']) ? $info['type'] : '',
					'time'  => $info['time'],
				);
			}
			$endpoints    = $this->get_bus_endpoints($bus_id);
			$ticket_infos = ($endpoints['bp'] && $endpoints['dp'])
				? WBTM_Functions::get_ticket_info($bus_id, $endpoints['bp'], $endpoints['dp'])
				: array();
			$tickets = array();
			foreach ($ticket_infos as $ticket_info) {
				$tickets[] = array(
					'type'  => $ticket_info['type'],
					'name'  => $ticket_info['name'],
					'price' => $ticket_info['price'],
				);
			}
			$gallery = array();
			foreach ((array) get_post_meta($bus_id, 'wbtm_gallery_images', true) as $image_id) {
				$url = wp_get_attachment_url(absint($image_id));
				if ($url) {
					$gallery[] = $url;
				}
			}
			return rest_ensure_response(array(
				'id'          => $bus_id,
				'name'        => get_the_title($bus_id),
				'type'        => WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category'),
				'route_stops' => $route_stops,
				'tickets'     => $tickets,
				'features'    => $this->get_bus_features($bus_id),
				'gallery'     => $gallery,
			));
		}
		public function get_bus_seats(WP_REST_Request $request) {
			$rate = $this->check_rate_limit();
			if (is_wp_error($rate)) {
				return $rate;
			}
			$bus_id = absint($request->get_param('id'));
			$post   = $this->validate_bus($bus_id);
			if (is_wp_error($post)) {
				return $post;
			}
			$date = $this->validate_date($request->get_param('date'));
			if (is_wp_error($date)) {
				return $date;
			}
			$endpoints = $this->get_bus_endpoints($bus_id);
			$bp        = sanitize_text_field(wp_unslash((string) $request->get_param('bp')));
			$dp        = sanitize_text_field(wp_unslash((string) $request->get_param('dp')));
			$bp        = $bp !== '' ? $bp : $endpoints['bp'];
			$dp        = $dp !== '' ? $dp : $endpoints['dp'];
			if ($bp === '' || $dp === '') {
				return new WP_Error(
					'wbtm_no_route',
					__('This bus has no route configured.', 'bus-ticket-booking-with-seat-reservation'),
					array('status' => 400)
				);
			}
			// Booked seats include held seats via the wbtm_query_seat_booked filter.
			$booked    = array_map('strval', WBTM_Query::query_seat_booked($bus_id, $bp, $dp, $date));
			$seat_type = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_seat_type_conf');
			$response  = array(
				'id'             => $bus_id,
				'date'           => $date,
				'boarding_point' => $bp,
				'dropping_point' => $dp,
				'seat_type'      => $seat_type,
			);
			if ($seat_type === 'wbtm_seat_plan') {
				$decks = array();
				$lower = $this->build_deck(
					(array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_seats_info', []),
					$booked,
					$bus_id,
					$date
				);
				if ($lower) {
					$lower['deck'] = 'lower';
					$decks[]       = $lower;
				}
				if (WBTM_Global_Function::get_post_info($bus_id, 'show_upper_desk')) {
					$upper = $this->build_deck(
						(array) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_seats_info_dd', []),
						$booked,
						$bus_id,
						$date
					);
					if ($upper) {
						$upper['deck'] = 'upper';
						$decks[]       = $upper;
					}
				}
				$response['decks'] = $decks;
			} else {
				$total_seat = (int) WBTM_Global_Function::get_post_info($bus_id, 'wbtm_get_total_seat', 0);
				$sold_seat  = WBTM_Query::query_total_booked($bus_id, $bp, $dp, $date);
				$response['total_seats'] = $total_seat;
				$response['booked']      = $sold_seat;
				$response['available']   = max(0, $total_seat - $sold_seat);
			}
			return rest_ensure_response($response);
		}
		/**
		 * Build one deck's seat grid with per-seat status (available/booked/held).
		 * Empty cells are null; non-seat items (door, driver, ...) get type "non_seat".
		 */
		private function build_deck($seat_infos, $booked, $bus_id, $date) {
			if (sizeof($seat_infos) === 0) {
				return null;
			}
			$grid = array();
			foreach ($seat_infos as $row) {
				$grid_row = array();
				foreach ((array) $row as $seat_key => $seat) {
					if (strpos((string) $seat_key, '_rotation') !== false) {
						continue;
					}
					$seat = class_exists('WBTM_Seat_Configuration')
						? WBTM_Seat_Configuration::normalize_saved_seat_value($seat)
						: trim((string) $seat);
					if ($seat === '') {
						$grid_row[] = null;
						continue;
					}
					$is_non_seat = class_exists('WBTM_Seat_Configuration')
						? WBTM_Seat_Configuration::is_non_seat_item($seat)
						: in_array(strtolower($seat), array('door', 'toilet', 'wc', 'driver', 'window', 'food_stall', 'luggage', 'stairs', 'aisle', 'emergency_exit'), true);
					if ($is_non_seat) {
						$grid_row[] = array('name' => $seat, 'type' => 'non_seat');
						continue;
					}
					$status = 'available';
					if (in_array($seat, $booked, true)) {
						$status = 'booked';
						if (class_exists('WBTM_Seat_Hold') && WBTM_Seat_Hold::get_hold_owner($bus_id, $date, $seat)) {
							$status = 'held';
						}
					}
					$grid_row[] = array('name' => $seat, 'type' => 'seat', 'status' => $status);
				}
				$grid[] = $grid_row;
			}
			return array(
				'rows'  => sizeof($grid),
				'cols'  => sizeof($grid) > 0 ? sizeof($grid[0]) : 0,
				'seats' => $grid,
			);
		}
	}
	new WBTM_REST_API();
}
