<?php
/** Native customer booking adapter. Prices, inventory and records stay in WBTM. */
if ( ! defined( 'ABSPATH' ) ) { die; }
require_once __DIR__ . '/WBTM_App_Inventory.php';
require_once __DIR__ . '/WBTM_App_Lifecycle.php';

if ( ! class_exists( 'WBTM_App_Booking' ) ) {
	class WBTM_App_Booking {
		const NS = 'wbtm-app/v1';
		const QUOTE_TTL = 600;

		public static function capabilities() {
			return array(
				'search' => true, 'seats' => true, 'quote' => true, 'holds' => true,
				'create_booking' => WBTM_Functions::use_wc() || is_callable( array( 'WBTM_Standalone_Payment', 'insert_app_booking' ) ), 'my_bookings' => true, 'payment' => WBTM_Functions::use_wc() || is_callable( array( 'WBTM_Standalone_Payment', 'insert_app_booking' ) ), 'pdf' => class_exists( 'WBTM_Pro_Pdf' ) && class_exists( 'WBTM_Customer_Portal' ),
				'cancel_request' => true, 'change_request' => true, 'cancel' => false, 'reschedule' => false,
				'quantity' => true, 'full_bus' => WBTM_Functions::is_full_bus_feature_enabled(), 'cabins' => true, 'sleeper' => true,
				'paired_round_trip' => false, 'coupons' => false, 'deposits' => false, 'tax_checkout' => false, 'quantity_holds' => false,
			);
		}

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
			add_action( 'template_redirect', array( $this, 'browser_bridge' ), 1 );
		}

		public function routes() {
			foreach ( array(
				array( '/search', 'GET', 'search', false ), array( '/buses/(?P<id>\d+)', 'GET', 'bus', false ),
				array( '/quotes', 'POST', 'quote', true ), array( '/holds', 'POST', 'hold', true ), array( '/holds/(?P<id>[a-f0-9]{32})', 'DELETE', 'release', true ),
				array( '/bookings', 'POST', 'create', true ), array( '/bookings', 'GET', 'bookings', true ), array( '/bookings/(?P<id>\d+)', 'GET', 'booking', true ),
				array( '/bookings/(?P<id>\d+)/payment', 'POST', 'payment', true ), array( '/bookings/(?P<id>\d+)/ticket', 'POST', 'ticket', true ),
				array( '/bookings/(?P<id>\d+)/cancel-request', 'POST', 'request_change', true ), array( '/bookings/(?P<id>\d+)/change-request', 'POST', 'request_change', true ),
			) as $route ) {
				register_rest_route( self::NS, $route[0], array( 'methods' => $route[1], 'callback' => array( $this, $route[2] ), 'permission_callback' => $route[3] ? array( 'WBTM_App_Auth', 'require_user' ) : '__return_true' ) );
			}
		}

		private function error( $message, $status = 400, $code = 'wbtm_app_booking' ) { return WBTM_App_Inventory::error( $message, $status, $code ); }
		private function text( $value ) { return WBTM_App_Inventory::text( $value ); }
		private function token() { return bin2hex( random_bytes( 16 ) ); }
		private function response( $data, $status = 200 ) {
			$response = new WP_REST_Response( $data, $status );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}
		private function owned_quote( $id ) {
			$id = $this->text( $id );
			$quote = preg_match( '/^[a-f0-9]{32}$/D', $id ) ? get_transient( 'wbtm_app_quote_' . $id ) : false;
			return is_array( $quote ) && (int) $quote['user_id'] === get_current_user_id() ? $quote : $this->error( 'Quote expired. Please search again.', 410, 'wbtm_app_quote_expired' );
		}
		private function with_hold_token( $token, $callback ) {
			$had = array_key_exists( WBTM_Seat_Hold::COOKIE_NAME, $_COOKIE );
			$previous = $_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] ?? null;
			try {
				$_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] = $token;
				return $callback();
			} finally {
				if ( $had ) { $_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] = $previous; } else { unset( $_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] ); }
			}
		}

		public function search( WP_REST_Request $request ) {
			$limit = WBTM_App_Auth::rate_limit( 'booking_public', 300 );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$bp = $this->text( $request['bp'] ); $dp = $this->text( $request['dp'] ); $date = $this->text( $request['date'] );
			if ( ! $bp || ! $dp || $bp === $dp || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) { return $this->error( 'Select departure, destination and date.' ); }
			$page = max( 1, absint( $request['page'] ?? 1 ) ); $per_page = min( 100, max( 1, absint( $request['per_page'] ?? 50 ) ) );
			$result = array( 'boarding_point' => $bp, 'dropping_point' => $dp, 'date' => $date, 'buses' => array(), 'return_buses' => array(), 'page' => $page, 'per_page' => $per_page, 'has_more' => false );
			$legs = array( array( $bp, $dp, $date, 'buses', 'outbound' ) );
			if ( $request['return_date'] ) { $legs[] = array( $dp, $bp, $this->text( $request['return_date'] ), 'return_buses', 'return' ); }
			foreach ( $legs as $leg ) {
				$candidates = WBTM_Query::get_bus_id( $leg[0], $leg[1] );
				$result['has_more'] = $result['has_more'] || count( $candidates ) > $page * $per_page;
				foreach ( array_slice( $candidates, ( $page - 1 ) * $per_page, $per_page ) as $id ) {
					$trip = WBTM_App_Inventory::trip( array( 'bus_id' => $id, 'bp' => $leg[0], 'dp' => $leg[1], 'date' => $leg[2], 'price_leg' => $leg[4] ) );
					if ( is_wp_error( $trip ) ) { continue; }
					$prices = WBTM_Functions::get_ticket_info( $id, $leg[0], $leg[1], $trip['price_leg'] );
					foreach ( $prices as &$price ) { $price['price'] = (float) WBTM_Functions::get_seat_price( $id, $leg[0], $leg[1], $price['type'], false, $trip['price_leg'], '', null, $trip['bp_time'] ); } unset( $price );
					$result[ $leg[3] ][] = array(
						'id' => $id, 'name' => get_the_title( $id ), 'type' => WBTM_App_Inventory::text( get_post_meta( $id, 'wbtm_bus_category', true ) ),
						'boarding_point' => $leg[0], 'dropping_point' => $leg[1], 'bp' => $leg[0], 'dp' => $leg[1], 'date' => $leg[2], 'price_leg' => $trip['price_leg'],
						'departure_time' => $trip['bp_time'], 'arrival_time' => $trip['dp_time'], 'total_seats' => $trip['total_seat'], 'seats_left' => $trip['available_seat'],
						'prices' => $prices, 'features' => WBTM_App_Inventory::features( $id ), 'image_url' => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
						'duration_minutes' => WBTM_App_Inventory::duration_minutes( $trip ),
					);
				}
			}
			return $this->response( $result );
		}

		public function bus( WP_REST_Request $request ) {
			$limit = WBTM_App_Auth::rate_limit( 'booking_public', 300 );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$result = WBTM_App_Inventory::detail( $request->get_params() );
			return is_wp_error( $result ) ? $result : $this->response( $result );
		}

		public function quote( WP_REST_Request $request ) {
			$limit = WBTM_App_Auth::rate_limit( 'booking_quote', 120, (string) get_current_user_id() );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$input = $request->get_json_params();
			if ( ! is_array( $input ) ) { return $this->error( 'A JSON booking selection is required.' ); }
			$result = $this->make_quote( $input );
			if ( is_wp_error( $result ) ) { return $result; }
			$result['user_id'] = get_current_user_id(); $result['quote_id'] = $this->token(); $result['expires_at'] = time() + self::QUOTE_TTL;
			set_transient( 'wbtm_app_quote_' . $result['quote_id'], $result, self::QUOTE_TTL );
			$public = $result; unset( $public['input'], $public['user_id'] );
			return $this->response( $public, 201 );
		}

		private function make_quote( array $input ) {
			// Explicit fallback is safer than silently bypassing checkout-only addons.
			if ( ! empty( $input['coupon'] ) || ! empty( $input['coupon_code'] ) || ! empty( $input['deposit'] ) || ! empty( $input['return_quote_id'] ) ) { return $this->error( 'Use website checkout for coupons, deposits or a combined return booking.', 409, 'wbtm_app_website_checkout_required' ); }
			$quote = WBTM_App_Inventory::quote( $input );
			if ( is_wp_error( $quote ) ) { return $quote; }
			$deposit = class_exists( 'WBTM_Deposit' ) ? WBTM_Deposit::bus_config( $quote['trip']['bus_id'] ) : array();
			if ( ( ! empty( $deposit['enabled'] ) && empty( $deposit['offer_choice'] ) ) || ( WBTM_Functions::use_wc() && wc_tax_enabled() ) ) { return $this->error( 'This site requires website checkout to calculate its tax or required deposit.', 409, 'wbtm_app_website_checkout_required' ); }
			$quote['engine'] = WBTM_Functions::booking_mode();
			$quote['return_discount'] = 0;
			if ( 'return' === $quote['trip']['price_leg'] && 'full_bus' !== $quote['booking_mode'] && (float) get_post_meta( $quote['trip']['bus_id'], 'bus_return_discount', true ) > 0 ) {
				return $this->error( 'Use website checkout to pair this return discount with its outbound journey.', 409, 'wbtm_app_website_checkout_required' );
			}
			return $quote;
		}

		private function lock( array $quote ) {
			$t = $quote['trip'];
			return WBTM_Cart_Helper::acquire_trip_lock( $t['bus_id'], $t['bp'], $t['dp'], $t['bp_time'], 5 );
		}
		private function unlock( array $quote ) { $t = $quote['trip']; WBTM_Cart_Helper::release_trip_lock( $t['bus_id'], $t['bp'], $t['dp'], $t['bp_time'] ); }
		private function identifiers( array $quote ) {
			if ( 'full_bus' === $quote['booking_mode'] ) {
				$result = array();
				foreach ( WBTM_App_Inventory::layouts( $quote['trip'] ) as $layout ) { foreach ( $layout['rows'] as $row ) { foreach ( $row as $cell ) { if ( $cell && 'seat' === $cell['type'] ) { $result[] = $cell['canonical']; } } } }
				return array_values( array_unique( $result ) );
			}
			return array_values( array_filter( array_column( $quote['tickets'], 'seat_identifier' ) ) );
		}

		/** Re-read sold seats and foreign holds while the shared lock is held. */
		private function available( array $quote, $token = '' ) {
			$t = $quote['trip'];
			WBTM_Query::flush_booked_map();
			$valid = $this->with_hold_token( $token, static function() use ( $t, $quote ) {
				return WBTM_Cart_Helper::validate_bus_availability( $t['bus_id'], $t['bp'], $t['dp'], $t['bp_time'], $quote['booking_mode'], $quote['tickets'], array(), 'full_bus' === $quote['booking_mode'] ? $t['total_seat'] : 0 );
			} );
			if ( is_wp_error( $valid ) ) { return $this->error( $valid->get_error_message(), 409, 'wbtm_app_unavailable' ); }
			foreach ( $this->identifiers( $quote ) as $seat ) {
				if ( WBTM_Seat_Hold::is_held_by_other( $t['bus_id'], WBTM_Seat_Hold::normalize_date( $t['start_time'] ), $seat, $token ) ) { return $this->error( 'A selected seat is held by another customer.', 409, 'wbtm_app_seat_held' ); }
			}
			return true;
		}

		public function hold( WP_REST_Request $request ) {
			$quote = $this->owned_quote( $request['quote_id'] );
			if ( is_wp_error( $quote ) ) { return $quote; }
			if ( ! $this->lock( $quote ) ) { return $this->error( 'Inventory is busy. Please retry.', 423 ); }
			try {
				$valid = $this->available( $quote ); if ( is_wp_error( $valid ) ) { return $valid; }
				$token = $this->token(); $ttl = min( self::QUOTE_TTL, max( 30, WBTM_Seat_Hold::get_ttl_seconds() ) );
				$t = $quote['trip']; $ids = $this->identifiers( $quote );
				if ( $ids && WBTM_Seat_Hold::is_enabled() ) {
					$held = WBTM_Seat_Hold::hold_seats( $t['bus_id'], $t['start_time'], $ids, $token, $ttl );
					if ( $held['conflicts'] ) { WBTM_Seat_Hold::release_seats( $t['bus_id'], $t['start_time'], $held['held'], $token ); return $this->error( 'A selected seat was just reserved.', 409 ); }
				}
				set_transient( 'wbtm_app_hold_' . $token, array( 'quote_id' => $quote['quote_id'], 'user_id' => get_current_user_id(), 'expires_at' => time() + $ttl, 'quote' => $quote ), $ttl );
				return $this->response( array( 'hold_id' => $token, 'expires_at' => time() + $ttl, 'reserved' => ! empty( $ids ) && WBTM_Seat_Hold::is_enabled(), 'advisory' => empty( $ids ) || ! WBTM_Seat_Hold::is_enabled() ), 201 );
			} finally { $this->unlock( $quote ); }
		}

		public function release( WP_REST_Request $request ) {
			$token = $this->text( $request['id'] ); $hold = get_transient( 'wbtm_app_hold_' . $token );
			if ( ! is_array( $hold ) || (int) $hold['user_id'] !== get_current_user_id() ) { return $this->error( 'Hold not found.', 404 ); }
			$this->release_hold( $token, $hold['quote'] );
			return $this->response( array( 'released' => true ) );
		}
		private function release_hold( $token, array $quote ) {
			$t = $quote['trip']; WBTM_Seat_Hold::release_seats( $t['bus_id'], $t['start_time'], $this->identifiers( $quote ), $token ); delete_transient( 'wbtm_app_hold_' . $token );
		}

		private function passengers( array $quote, $input ) {
			return WBTM_App_Inventory::passengers( $quote, $input );
		}

		public function create( WP_REST_Request $request ) {
			$limit = WBTM_App_Auth::rate_limit( 'booking_create', 30, (string) get_current_user_id() );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$input = $request->get_json_params();
			if ( ! is_array( $input ) ) { return $this->error( 'JSON booking details are required.' ); }
			$key = $this->text( $input['idempotency_key'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $key ) ) { return $this->error( 'A unique idempotency key (16–100 characters) is required.' ); }
			$fingerprint = hash( 'sha256', wp_json_encode( $input ) );
			$option = 'wbtm_app_request_' . hash( 'sha256', get_current_user_id() . '|' . $key );
			$previous = get_option( $option );
			if ( is_array( $previous ) && isset( $previous['fingerprint'] ) && ! hash_equals( $previous['fingerprint'], $fingerprint ) ) { return $this->error( 'This idempotency key was used for different booking details.', 409 ); }
			if ( is_array( $previous ) && ! empty( $previous['id'] ) ) { return $this->booking_id( $previous['id'] ); }
			$quote = $this->owned_quote( $input['quote_id'] ?? '' );
			if ( is_wp_error( $quote ) ) { return $quote; }
			if ( true !== ( $input['terms_accepted'] ?? false ) ) { return $this->error( 'Accept the booking terms before continuing.' ); }
			$billing = is_array( $input['billing'] ?? null ) ? $input['billing'] : array();
			foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'city', 'state', 'country', 'postcode' ) as $field ) { $billing[ $field ] = $this->text( $billing[ $field ] ?? '' ); }
			if ( ! $billing['first_name'] || ! is_email( $billing['email'] ) || ! preg_match( '/^[0-9+()\s-]{6,30}$/D', $billing['phone'] ) ) { return $this->error( 'Enter a name, valid email and phone number.' ); }
			$passengers = $this->passengers( $quote, $input['passengers'] ?? array() );
			if ( is_wp_error( $passengers ) ) { return $passengers; }
			$hold_id = $this->text( $input['hold_id'] ?? '' ); $hold = $hold_id ? get_transient( 'wbtm_app_hold_' . $hold_id ) : false;
			if ( $hold_id && ( ! is_array( $hold ) || $hold['quote_id'] !== $quote['quote_id'] || (int) $hold['user_id'] !== get_current_user_id() ) ) { return $this->error( 'Your seat hold expired. Please select seats again.', 409 ); }
			if ( ! $this->lock( $quote ) ) { return $this->error( 'Inventory is busy. Please retry.', 423 ); }
			$claimed = false;
			try {
				$previous = get_option( $option );
				if ( is_array( $previous ) && isset( $previous['fingerprint'] ) && ! hash_equals( $previous['fingerprint'], $fingerprint ) ) { return $this->error( 'This idempotency key was used for different booking details.', 409 ); }
				if ( is_array( $previous ) && ! empty( $previous['id'] ) ) { return $this->booking_id( $previous['id'] ); }
				$owned = $this->owned_quote( $quote['quote_id'] );
				if ( is_wp_error( $owned ) ) { return $owned; }
				$fresh = $this->with_hold_token( $hold_id, function() use ( $quote ) { return $this->make_quote( $quote['input'] ); } );
				if ( is_wp_error( $fresh ) ) { return $fresh; }
				if ( $fresh['engine'] !== $quote['engine'] || abs( $fresh['total'] - $quote['total'] ) > 0.001 || wp_json_encode( $fresh['tickets'] ) !== wp_json_encode( $quote['tickets'] ) ) { return $this->error( 'The fare changed. Please request a fresh quote.', 409, 'wbtm_app_price_changed' ); }
				$valid = $this->available( $fresh, $hold_id ); if ( is_wp_error( $valid ) ) { return $valid; }
				if ( ! add_option( $option, array( 'started_at' => time(), 'quote_id' => $quote['quote_id'], 'fingerprint' => $fingerprint ), '', false ) ) { return $this->error( 'This booking request is already being processed.', 409 ); }
				$claimed = true;
				$id = 'woocommerce' === $fresh['engine'] ? $this->create_woo( $fresh, $billing, $passengers ) : $this->create_standalone( $fresh, $billing, $passengers );
				if ( is_wp_error( $id ) ) { delete_option( $option ); return $id; }
				update_option( $option, array( 'id' => $id, 'created_at' => time(), 'quote_id' => $quote['quote_id'], 'fingerprint' => $fingerprint ), false );
				WBTM_App_Lifecycle::schedule( $id );
				$this->release_hold( $hold_id, $quote ); delete_transient( 'wbtm_app_quote_' . $quote['quote_id'] );
				do_action( 'wbtm_app_booking_created', get_current_user_id(), $id );
				return $this->booking_id( $id, 201 );
			} catch ( Throwable $error ) {
				// Retain an incomplete idempotency record if a write could have happened:
				// retrying blindly can charge or reserve twice. Operators can reconcile it.
				return $this->error( 'The booking could not be completed. Check My Trips before retrying.', 500, 'wbtm_app_booking_failed' );
			} finally { $this->unlock( $quote ); }
		}

		private function create_woo( array $q, array $billing, array $passengers ) {
			$t = $q['trip']; $product_id = (int) get_post_meta( $t['bus_id'], 'link_wc_product', true );
			if ( ! $product_id && class_exists( 'WBTM_Hidden_Product' ) ) { $product_id = WBTM_Hidden_Product::ensure_hidden_wc_product( $t['bus_id'] ); }
			$product = wc_get_product( $product_id ); if ( ! $product ) { return $this->error( 'This bus is not configured for checkout.', 409 ); }
			$order = wc_create_order( array( 'customer_id' => get_current_user_id(), 'created_via' => 'wbtm-app' ) );
			if ( is_wp_error( $order ) ) { return $order; }
			$order->set_address( $billing, 'billing' ); $order->set_currency( $q['currency'] );
			$order->update_meta_data( '_wbtm_app', 1 ); $order->update_meta_data( '_wbtm_app_trip', $t );
			$item = new WC_Order_Item_Product(); $item->set_product( $product ); $item->set_quantity( 1 ); $item->set_subtotal( $q['total'] ); $item->set_total( $q['total'] );
			$values = array(
				'wbtm_bus_id' => $t['bus_id'], 'wbtm_bp_place' => $t['bp'], 'wbtm_dp_place' => $t['dp'], 'wbtm_bp_time' => $t['bp_time'], 'wbtm_dp_time' => $t['dp_time'],
				'wbtm_start_point' => $t['start_point'], 'wbtm_start_time' => $t['start_time'], 'wbtm_pickup_point' => $q['pickup'], 'wbtm_drop_off_point' => $q['dropoff'],
				'wbtm_seats' => $q['tickets'], 'wbtm_seats_qty' => $q['passenger_count'], 'wbtm_base_price' => $q['base_price'], 'wbtm_tp' => $q['total'],
				'wbtm_extra_services' => $q['extras'], 'wbtm_passenger_info' => $passengers, 'wbtm_price_leg' => $t['price_leg'], 'wbtm_journey_type' => 'return' === $t['price_leg'] ? 'return' : 'departure',
				'wbtm_booking_mode' => $q['booking_mode'], 'wbtm_full_bus_base_price' => $q['full_bus']['base_price'] ?? 0, 'wbtm_full_bus_discount' => $q['full_bus']['discount'] ?? 0, 'wbtm_full_bus_seat_count' => 'full_bus' === $q['booking_mode'] ? $t['total_seat'] : 0,
			);
			do_action( 'woocommerce_checkout_create_order_line_item', $item, 'wbtm_app', $values, $order );
			$order->add_item( $item ); $order->calculate_totals( false ); $order->save();
			WBTM_Woocommerce::add_billing_data( $item->get_id(), $order->get_id() );
			return $order->get_id();
		}

		private function create_standalone( array $q, array $billing, array $passengers ) {
			if ( ! is_callable( array( 'WBTM_Standalone_Payment', 'insert_app_booking' ) ) ) { return $this->error( 'This standalone engine requires a compatible app checkout adapter.', 409 ); }
			$t = $q['trip'];
			$post = array( 'wbtm_bp_place' => $t['bp'], 'wbtm_dp_place' => $t['dp'], 'wbtm_bp_time' => $t['bp_time'], 'wbtm_dp_time' => $t['dp_time'], 'wbtm_start_point' => $t['start_point'], 'wbtm_start_time' => $t['start_time'], 'wbtm_pickup_point' => $q['pickup'], 'wbtm_drop_off_point' => $q['dropoff'], 'wbtm_price_leg' => $t['price_leg'], 'wbtm_journey_type' => 'return' === $t['price_leg'] ? 'return' : 'departure', 'extra_service_name' => array_column( $q['extras'], 'name' ), 'extra_service_qty' => array_column( $q['extras'], 'qty' ) );
			foreach ( $q['passenger_fields'] as $field ) { $post[ $field['id'] ] = array_map( static function( $row ) use ( $field ) { return $row[ $field['id'] ]['value'] ?? ''; }, $passengers ); }
			$full = $q['full_bus'] ? array( 'base_price' => $q['full_bus']['base_price'], 'discount' => $q['full_bus']['discount'], 'seat_count' => $t['total_seat'] ) : array();
			$ids = WBTM_Standalone_Payment::insert_app_booking( $t['bus_id'], $q['tickets'], $post, $full );
			if ( ! $ids ) { return $this->error( 'Booking records could not be saved.', 500 ); }
			foreach ( $ids as $id ) {
				update_post_meta( $id, 'wbtm_user_name', trim( $billing['first_name'] . ' ' . $billing['last_name'] ) ); update_post_meta( $id, 'wbtm_user_email', $billing['email'] ); update_post_meta( $id, 'wbtm_user_phone', $billing['phone'] ); update_post_meta( $id, 'wbtm_user_address', $billing['address_1'] );
				update_post_meta( $id, '_wbtm_app', 1 ); update_post_meta( $id, 'wbtm_tp', $q['total'] );
			}
			update_post_meta( $ids[0], '_wbtm_app_trip', $t );
			return $ids[0];
		}

		private function owned_rows( $id ) {
			return get_posts( array( 'post_type' => 'wbtm_bus_booking', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => 'wbtm_order_id', 'value' => absint( $id ) ), array( 'key' => 'wbtm_user_id', 'value' => get_current_user_id() ) ) ) );
		}

		private function booking_id( $id, $http_status = 200 ) {
			$id = absint( $id ); $rows = $this->owned_rows( $id ); if ( ! $rows ) { return $this->error( 'Booking not found.', 404 ); }
			WBTM_App_Lifecycle::expire( $id );
			$head = $rows[0]->ID; $order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;
			if ( $order && (int) $order->get_customer_id() !== get_current_user_id() ) { return $this->error( 'Booking not found.', 404 ); }
			$status = $order ? $order->get_status() : get_post_meta( $head, 'wbtm_order_status', true );
			$expired = $order ? $order->get_meta( WBTM_App_Lifecycle::EXPIRED ) : get_post_meta( $head, WBTM_App_Lifecycle::EXPIRED, true );
			if ( $expired && in_array( $status, array( 'processing', 'completed', 'on-hold', 'partially-paid', 'wbtm-review' ), true ) ) { $status = 'payment-review'; }
			$tickets = array();
			foreach ( $rows as $row ) { $tickets[] = array( 'id' => $row->ID, 'name' => get_post_meta( $row->ID, 'wbtm_ticket', true ), 'seat' => get_post_meta( $row->ID, 'wbtm_seat', true ), 'price' => (float) get_post_meta( $row->ID, 'wbtm_bus_fare', true ), 'passenger' => get_post_meta( $row->ID, 'wbtm_attendee_info', true ) ); }
			$bus_id = (int) get_post_meta( $head, 'wbtm_bus_id', true );
			$result = array(
				'id' => $id, 'engine' => $order ? 'woocommerce' : 'standalone', 'status' => $status, 'total' => $order ? (float) $order->get_total() : (float) get_post_meta( $head, 'wbtm_tp', true ),
				'currency' => $order ? $order->get_currency() : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' ), 'tickets' => $tickets,
				'placed_at' => get_post_meta( $head, 'wbtm_booking_date', true ) ?: $rows[0]->post_date,
				'payment_required' => in_array( $status, array( 'pending', 'failed' ), true ), 'pdf_available' => class_exists( 'WBTM_Pro_Pdf' ) && class_exists( 'WBTM_Customer_Portal' ) && WBTM_Customer_Portal::is_ticket_ready( $status ),
				'cancel_request' => get_post_meta( $head, '_wbtm_app_cancel_request', true ) ?: null, 'change_request' => get_post_meta( $head, '_wbtm_app_change_request', true ) ?: null,
				'trip' => array( 'bus_id' => $bus_id, 'name' => get_the_title( $bus_id ), 'date' => substr( (string) get_post_meta( $head, 'wbtm_boarding_time', true ), 0, 10 ), 'bp' => get_post_meta( $head, 'wbtm_boarding_point', true ), 'dp' => get_post_meta( $head, 'wbtm_dropping_point', true ), 'bp_time' => get_post_meta( $head, 'wbtm_boarding_time', true ), 'dp_time' => get_post_meta( $head, 'wbtm_dropping_time', true ), 'pickup' => get_post_meta( $head, 'wbtm_pickup_point', true ), 'dropoff' => get_post_meta( $head, 'wbtm_drop_off_point', true ) ),
			);
			return $this->response( $result, $http_status );
		}

		public function booking( WP_REST_Request $request ) { return $this->booking_id( $request['id'] ); }
		public function bookings( WP_REST_Request $request ) {
			global $wpdb;
			$page = max( 1, absint( $request['page'] ?? 1 ) ); $per_page = min( 20, max( 1, absint( $request['per_page'] ?? 10 ) ) );
			// Group in SQL, then load only this page. Customers with years of trips
			// never load all passenger records merely to render the first screen.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT CAST(o.meta_value AS UNSIGNED) AS order_id FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} u ON u.post_id=p.ID AND u.meta_key='wbtm_user_id' INNER JOIN {$wpdb->postmeta} o ON o.post_id=p.ID AND o.meta_key='wbtm_order_id' WHERE p.post_type='wbtm_bus_booking' AND p.post_status='publish' AND u.meta_value=%s GROUP BY o.meta_value ORDER BY MAX(p.ID) DESC LIMIT %d OFFSET %d", (string) get_current_user_id(), $per_page + 1, ( $page - 1 ) * $per_page ) );
			$result = array(); foreach ( array_slice( $ids, 0, $per_page ) as $id ) { $booking = $this->booking_id( $id ); if ( ! is_wp_error( $booking ) ) { $result[] = $booking->get_data(); } }
			return $this->response( array( 'bookings' => $result, 'page' => $page, 'has_more' => count( $ids ) > $per_page ) );
		}

		public function payment( WP_REST_Request $request ) {
			$booking = $this->booking_id( $request['id'] ); if ( is_wp_error( $booking ) ) { return $booking; }
			$data = $booking->get_data(); if ( ! $data['payment_required'] ) { return $this->error( 'This booking does not require payment.', 409 ); }
			$token = $this->token();
			$selector = WBTM_App_Auth::current_session();
			if ( ! WBTM_App_Auth::session_active( $selector, get_current_user_id() ) ) { return $this->error( 'Sign in again before opening payment.', 401 ); }
			set_transient( 'wbtm_app_bridge_' . hash( 'sha256', $token ), array( 'id' => $data['id'], 'user_id' => get_current_user_id(), 'engine' => $data['engine'], 'session' => $selector ), 120 );
			return $this->response( array( 'url' => add_query_arg( 'wbtm_app_checkout', $token, home_url( '/' ) ), 'expires_at' => time() + 120 ) );
		}

		public static function bridge_active( array $data ) {
			return ! empty( $data['session'] ) && ! empty( $data['user_id'] ) && WBTM_App_Auth::session_active( $data['session'], $data['user_id'] );
		}

		public function ticket( WP_REST_Request $request ) {
			$booking = $this->booking_id( $request['id'] ); if ( is_wp_error( $booking ) ) { return $booking; }
			if ( ! $booking->get_data()['pdf_available'] ) { return $this->error( 'A ticket PDF is available after payment confirmation.', 409 ); }
			return $this->response( array( 'url' => WBTM_Customer_Portal::mint_download_url( absint( $request['id'] ), 120 ) ) );
		}

		public function request_change( WP_REST_Request $request ) {
			$booking = $this->booking_id( $request['id'] ); if ( is_wp_error( $booking ) ) { return $booking; }
			$data = $booking->get_data(); $message = $this->text( $request['message'] );
			if ( ! $message || strlen( $message ) > 2000 || in_array( $data['status'], array( 'cancelled', 'refunded', 'failed' ), true ) ) { return $this->error( 'Enter a request for an active booking (maximum 2000 bytes).' ); }
			$type = str_ends_with( $request->get_route(), '/cancel-request' ) ? 'cancel' : 'change';
			$limit = WBTM_App_Auth::rate_limit( 'booking_request_' . $type, 5, get_current_user_id() . '|' . $data['id'] );
			if ( is_wp_error( $limit ) ) { return $limit; }
			$rows = $this->owned_rows( $data['id'] );
			update_post_meta( $rows[0]->ID, '_wbtm_app_' . $type . '_request', array( 'status' => 'requested', 'message' => $message, 'requested_at' => gmdate( 'c' ) ) );
			if ( 'woocommerce' === $data['engine'] ) { wc_get_order( $data['id'] )->add_order_note( 'Customer app ' . $type . ' request: ' . $message ); }
			do_action( 'wbtm_app_booking_request', get_current_user_id(), $data['id'], $type );
			return $this->response( array( 'status' => 'requested', 'message' => 'Your request was saved for operator review. Your booking and payment have not changed.' ), 202 );
		}

		/** Single-use checkout capability; redirects only to this site's own checkout. */
		public function browser_bridge() {
			if ( empty( $_GET['wbtm_app_checkout'] ) ) { return; }
			$token = $this->text( wp_unslash( $_GET['wbtm_app_checkout'] ) );
			$url = $this->redeem_payment( $token );
			if ( is_wp_error( $url ) ) { wp_die( esc_html( $url->get_error_message() ), '', array( 'response' => 403 ) ); }
			nocache_headers(); header( 'Referrer-Policy: no-referrer' ); wp_safe_redirect( $url );
			exit;
		}

		/** Non-exiting exchange: bridge lock -> issuing-session lock -> inventory. */
		public function redeem_payment( $token, $issue_cookie = true ) {
			if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/D', $token ) || ! WBTM_App_Auth::enabled() ) { return $this->error( 'Invalid checkout link.', 403 ); }
			return WBTM_App_Auth::locked( 'payment_bridge_' . hash( 'sha256', $token ), function() use ( $token, $issue_cookie ) {
				$key = 'wbtm_app_bridge_' . hash( 'sha256', $token ); $data = get_transient( $key );
				if ( ! is_array( $data ) || empty( $data['session'] ) ) { return $this->error( 'Checkout link expired. Return to the app.', 403 ); }
				return WBTM_App_Auth::locked( 'session_' . $data['session'], function() use ( $data, $key, $issue_cookie ) {
					if ( ! self::bridge_active( $data ) || ! get_transient( $key ) ) { return $this->error( 'Checkout link expired. Return to the app.', 403 ); }
					$previous_user = get_current_user_id();
					try {
						wp_set_current_user( $data['user_id'] ); $booking = $this->booking_id( $data['id'] );
						if ( is_wp_error( $booking ) || ! $booking->get_data()['payment_required'] ) { return $this->error( 'This booking cannot be paid.', 403 ); }
						delete_transient( $key );
						if ( $issue_cookie ) { wp_set_auth_cookie( $data['user_id'], false, is_ssl() ); }
						if ( 'woocommerce' !== $data['engine'] && ! class_exists( 'WBTM_Standalone_Payment' ) ) { return $this->error( 'The payment module is unavailable.', 409 ); }
						return 'woocommerce' === $data['engine'] ? wc_get_order( $data['id'] )->get_checkout_payment_url() : add_query_arg( 'booking_id', $data['id'], WBTM_Standalone_Payment::get_checkout_page_url() );
					} finally { wp_set_current_user( $previous_user ); }
				} );
			} );
		}
	}
	new WBTM_App_Booking();
}
