<?php
/**
 * Owned, isolated WooCommerce checkout handoff for native customer selections.
 * Uses WooCommerce's real cart/checkout hooks for taxes, coupons and deposits.
 */
if ( ! defined( 'ABSPATH' ) ) { die; }

if ( ! class_exists( 'WBTM_App_Checkout' ) ) {
	class WBTM_App_Checkout {
		const COOKIE = 'wbtm_app_checkout_context';
		const TTL = 1800;
		private static $context = null;
		private static $context_token = '';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
			add_action( 'init', array( $this, 'activate_context' ), -1000 );
			add_action( 'template_redirect', array( $this, 'bridge' ), 0 );
		}
		public function routes() {
			register_rest_route( 'wbtm-app/v1', '/checkout', array( 'methods' => 'POST', 'callback' => array( $this, 'create' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ) );
		}
		private static function error( $message, $status = 400 ) { return WBTM_App_Inventory::error( $message, $status, 'wbtm_app_checkout' ); }
		private static function key( $id ) { return 'wbtm_app_checkout_' . $id; }
		public static function context_valid( $intent ) {
			return is_array( $intent ) && isset( $intent['wc_customer_id'] ) && preg_match( '/^t_[a-f0-9]{30}$/D', $intent['wc_customer_id'] ) && ! empty( $intent['expires_at'] ) && $intent['expires_at'] > time() && WBTM_App_Auth::enabled() && WBTM_App_Auth::session_active( $intent['session'] ?? '', $intent['user_id'] ?? 0 );
		}
		private static function active() {
			return self::$context && self::context_valid( get_transient( self::key( self::$context['checkout_id'] ) ) );
		}
		public static function capabilities() {
			return array( 'hosted_checkout' => WBTM_Functions::use_wc(), 'hosted_paired_round_trip' => WBTM_Functions::use_wc(), 'hosted_tax_checkout' => WBTM_Functions::use_wc(), 'hosted_coupons' => WBTM_Functions::use_wc() && wc_coupons_enabled(), 'hosted_deposits' => WBTM_Functions::use_wc() && class_exists( 'WBTM_Deposit' ) && WBTM_Deposit::is_enabled() );
		}

		public function create( WP_REST_Request $request ) {
			if ( ! WBTM_Functions::use_wc() ) { return self::error( 'Hosted checkout is available in WooCommerce payment mode.', 409 ); }
			$rate = WBTM_App_Auth::rate_limit( 'hosted_checkout', 30, (string) get_current_user_id() ); if ( is_wp_error( $rate ) ) { return $rate; }
			$input = $request->get_json_params();
			if ( ! is_array( $input ) || strlen( $request->get_body() ) > 131072 || true !== ( $input['terms_accepted'] ?? false ) ) { return self::error( 'Send booking details and accept the booking terms.' ); }
			$key = WBTM_App_Inventory::text( $input['idempotency_key'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $key ) ) { return self::error( 'A unique checkout request key is required.' ); }
			$journeys = $input['journeys'] ?? array();
			if ( ! is_array( $journeys ) || ! count( $journeys ) || count( $journeys ) > 2 ) { return self::error( 'Choose one journey or a departure and return.' ); }
			$prepared = array();
			foreach ( $journeys as $journey ) {
				if ( ! is_array( $journey ) ) { return self::error( 'Invalid journey.' ); }
				// Native client cannot set the physical fare leg or any price.
				unset( $journey['price_leg'] );
				$q = WBTM_App_Inventory::quote( $journey ); if ( is_wp_error( $q ) ) { return $q; }
				$passengers = WBTM_App_Inventory::passengers( $q, $journey['passengers'] ?? array() ); if ( is_wp_error( $passengers ) ) { return $passengers; }
				$q['passengers'] = $passengers; $prepared[] = $q;
			}
			if ( 2 === count( $prepared ) ) {
				$a = $prepared[0]['trip']; $b = $prepared[1]['trip'];
				if ( $a['bp'] !== $b['dp'] || $a['dp'] !== $b['bp'] || strtotime( $b['bp_time'] ) < strtotime( $a['dp_time'] ) ) { return self::error( 'The return must reverse the outbound route and depart after arrival.' ); }
			}
			$billing = array();
			foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'city', 'state', 'country', 'postcode' ) as $field ) {
				$value = WBTM_App_Inventory::text( $input['billing'][ $field ] ?? '' ); if ( strlen( $value ) > 512 ) { return self::error( 'A billing field is too long.' ); } $billing[ $field ] = $value;
			}
			$selector = WBTM_App_Auth::current_session();
			if ( ! WBTM_App_Auth::session_active( $selector, get_current_user_id() ) ) { return self::error( 'Please sign in again.', 401 ); }
			$fingerprint = hash( 'sha256', wp_json_encode( $input ) );
			$request_key = 'wbtm_app_checkout_request_' . hash( 'sha256', get_current_user_id() . '|' . $key );
			return WBTM_App_Auth::locked( $request_key, function() use ( $prepared, $billing, $selector, $fingerprint, $request_key ) {
				$prior = get_transient( $request_key );
				if ( $prior && ! hash_equals( $prior['fingerprint'], $fingerprint ) ) { return self::error( 'This checkout key was used for different details.', 409 ); }
				$intent = $prior ? get_transient( self::key( $prior['id'] ) ) : false;
				if ( $intent && ! self::context_valid( $intent ) ) { return self::error( 'This checkout session expired. Start a new booking draft.', 409 ); }
				if ( ! $intent ) {
					$id = bin2hex( random_bytes( 16 ) );
					$intent = array( 'checkout_id' => $id, 'user_id' => get_current_user_id(), 'session' => $selector, 'hold_token' => bin2hex( random_bytes( 16 ) ), 'wc_customer_id' => 't_' . bin2hex( random_bytes( 15 ) ), 'journeys' => $prepared, 'billing' => $billing, 'expires_at' => time() + self::TTL );
					set_transient( self::key( $id ), $intent, self::TTL );
					set_transient( $request_key, array( 'id' => $id, 'fingerprint' => $fingerprint ), self::TTL );
				}
				$token = bin2hex( random_bytes( 32 ) );
				set_transient( 'wbtm_app_checkout_bridge_' . hash( 'sha256', $token ), $intent['checkout_id'], 120 );
				return new WP_REST_Response( array( 'checkout_id' => $intent['checkout_id'], 'url' => add_query_arg( 'wbtm_app_begin', $token, home_url( '/' ) ), 'expires_at' => time() + 120 ), 201 );
			} );
		}

		/** Determine checkout context before WooCommerce initializes its cart at init. */
		public function activate_context() {
			$token = isset( $_COOKIE[ self::COOKIE ] ) ? WBTM_App_Inventory::text( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
			if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || ! function_exists( 'WC' ) ) { return; }
			$id = get_transient( 'wbtm_app_checkout_context_' . hash( 'sha256', $token ) );
			$intent = $id ? get_transient( self::key( $id ) ) : false;
			if ( ! $this->is_checkout_request() ) { return; }
			if ( ! self::context_valid( $intent ) ) { wp_die( 'App checkout expired. Close this checkout and return to the app.', '', array( 'response' => 403 ) ); }
			self::$context = $intent; self::$context_token = $token;
			require_once __DIR__ . '/WBTM_App_WC_Session.php';
			// Separate cookie/session and guest identity preserve an existing signed-in
			// website cart. No WP login cookie is created or removed by this handoff.
			add_filter( 'determine_current_user', '__return_zero', PHP_INT_MAX ); wp_set_current_user( 0 );
			add_filter( 'woocommerce_cookie', array( $this, 'session_cookie' ) );
			add_filter( 'woocommerce_session_handler', array( $this, 'session_handler' ), PHP_INT_MAX );
			// Store API Cart-Token selects a different session handler upstream. A
			// retained token from the browser's normal cart must never replace this
			// checkout's isolated cookie session, even when its JWT is valid.
			$cart_token = isset( $_SERVER['HTTP_CART_TOKEN'] ) && is_string( $_SERVER['HTTP_CART_TOKEN'] ) ? wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) : '';
			if ( $cart_token ) {
				if ( ! self::cart_token_matches( $cart_token ) ) {
					add_filter( 'rest_pre_dispatch', static function( $response, $server, $request ) {
						return 0 === strpos( $request->get_route(), '/wc/store/' ) ? self::error( 'This cart token belongs to another checkout.', 403 ) : $response;
					}, 1, 3 );
				}
			}
			if ( isset( $_GET['session'] ) && ! self::cart_token_matches( is_string( $_GET['session'] ) ? wp_unslash( $_GET['session'] ) : '' ) ) {
				wp_die( 'This cart link belongs to another checkout.', '', array( 'response' => 403 ) );
			}
			add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );
			add_filter( 'woocommerce_checkout_registration_required', '__return_false' );
			add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );
			add_filter( 'woocommerce_checkout_update_customer_data', '__return_false' );
			add_filter( 'woocommerce_checkout_customer_id', array( $this, 'customer_id' ) );
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'checkout_value' ), 10, 2 );
			add_filter( 'woocommerce_create_order', array( $this, 'claim_order' ), 5 );
			add_action( 'woocommerce_checkout_create_order', array( $this, 'mark_order' ), 5 );
			add_action( 'woocommerce_checkout_order_created', array( $this, 'order_created' ), 5 );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_processed' ), 99 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'mark_order' ), 5 );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'store_order_processed' ), 99 );
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_context' ), 5, 2 );
			add_filter( 'woocommerce_order_received_verify_known_shoppers', array( $this, 'verify_known_shopper' ) );
			add_filter( 'user_has_cap', array( $this, 'pay_owned_order' ), 20, 3 );
			$_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] = $intent['hold_token'];
		}
		private function is_checkout_request() {
			if ( isset( $_GET['wbtm_app_ready'] ) || isset( $_GET['wc-ajax'] ) ) { return true; }
			if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) && 0 === strpos( ltrim( wp_unslash( $_GET['rest_route'] ), '/' ), 'wc/store/' ) ) { return true; }
			$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
			if ( ! is_string( $path ) ) { return false; }
			if ( false !== strpos( $path, '/wc/store/' ) ) { return true; }
			foreach ( array( 'woocommerce_checkout_page_id', 'woocommerce_cart_page_id' ) as $option ) {
				$id = (int) get_option( $option );
				if ( isset( $_GET['page_id'] ) && (int) $_GET['page_id'] === $id ) { return true; }
				$url_path = $id ? wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) : '';
				if ( $url_path && '/' !== $url_path && 0 === strpos( trailingslashit( $path ), trailingslashit( $url_path ) ) ) { return true; }
			}
			return false;
		}
		public function session_cookie( $cookie ) { return $cookie . '_wbtm_app_' . substr( self::$context['checkout_id'], 0, 12 ); }
		public function session_handler() { return 'WBTM_App_WC_Session'; }
		public static function wc_customer_id() { return self::$context['wc_customer_id'] ?? ''; }
		private static function cart_token_matches( $token ) {
			if ( ! is_string( $token ) || ! $token || strlen( $token ) > 4096 ) { return false; }
			$cookie = ( new WC_Session_Handler() )->get_session_cookie();
			$payload = \Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils::get_cart_token_payload( $token );
			return $cookie && hash_equals( self::wc_customer_id(), (string) $cookie[0] ) && hash_equals( self::wc_customer_id(), (string) ( $payload['user_id'] ?? '' ) );
		}
		private static function owns_context_order( $id ) {
			if ( ! self::active() || ! $id ) { return false; }
			$intent = get_transient( self::key( self::$context['checkout_id'] ) );
			if ( (int) ( $intent['order_id'] ?? 0 ) !== (int) $id ) { return false; }
			$order = wc_get_order( $id );
			return $order && (int) $order->get_customer_id() === (int) self::$context['user_id'];
		}
		public function verify_known_shopper( $verify ) {
			$id = absint( get_query_var( 'order-received', 0 ) );
			return self::owns_context_order( $id ) ? false : $verify;
		}
		public function pay_owned_order( $caps, $required, $args ) {
			if ( 'pay_for_order' === ( $args[0] ?? '' ) && self::owns_context_order( absint( $args[2] ?? 0 ) ) ) { $caps['pay_for_order'] = true; }
			return $caps;
		}
		public function customer_id( $id ) { return self::active() ? (int) self::$context['user_id'] : 0; }
		public function checkout_value( $value, $key ) {
			return self::active() && 0 === strpos( $key, 'billing_' ) ? ( self::$context['billing'][ substr( $key, 8 ) ] ?? $value ) : $value;
		}
		public function validate_context( $data, $errors ) { if ( ! self::active() ) { $errors->add( 'wbtm_app_checkout_expired', 'App checkout expired. Return to the app and sign in again.' ); } }

		public function claim_order( $existing ) {
			if ( ! self::active() ) { throw new Exception( 'App checkout expired. Return to the app.' ); }
			$key = 'wbtm_app_checkout_order_' . self::$context['checkout_id']; $claim = get_option( $key );
			if ( is_array( $claim ) && ! empty( $claim['id'] ) ) { return (int) $claim['id']; }
			if ( ! add_option( $key, array( 'started_at' => time() ), '', false ) ) { throw new Exception( 'This checkout is already being processed. Check My Trips before retrying.' ); }
			return $existing;
		}
		public function mark_order( $order ) {
			if ( ! self::active() ) { throw new Exception( 'App checkout expired. Return to the app.' ); }
			if ( 'woocommerce_store_api_checkout_update_order_from_request' === current_filter() ) {
				$key = 'wbtm_app_checkout_order_' . self::$context['checkout_id']; $claim = get_option( $key );
				if ( is_array( $claim ) && ! empty( $claim['id'] ) && (int) $claim['id'] !== $order->get_id() ) { throw new Exception( 'This checkout already has an order. Check My Trips before retrying.' ); }
				if ( ! $claim && ! add_option( $key, array( 'id' => $order->get_id() ), '', false ) ) { throw new Exception( 'This checkout is already being processed.' ); }
			}
			$order->set_customer_id( self::$context['user_id'] );
			$order->update_meta_data( '_wbtm_app', 1 );
			$order->update_meta_data( '_wbtm_app_checkout_id', self::$context['checkout_id'] );
		}
		public function order_created( $order ) {
			if ( ! self::active() || ! $order->get_meta( '_wbtm_app_checkout_id' ) ) { return; }
			update_option( 'wbtm_app_checkout_order_' . self::$context['checkout_id'], array( 'id' => $order->get_id() ), false );
			$intent = get_transient( self::key( self::$context['checkout_id'] ) ); $intent['order_id'] = $order->get_id(); set_transient( self::key( self::$context['checkout_id'] ), $intent, max( 1, $intent['expires_at'] - time() ) );
		}
		public function order_processed( $id ) { if ( self::active() ) { WBTM_App_Lifecycle::schedule( $id ); } }
		public function store_order_processed( $order ) { $this->order_created( $order ); $this->order_processed( $order->get_id() ); }

		public function bridge() {
			if ( self::active() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) && self::owns_context_order( absint( get_query_var( 'order-received', 0 ) ) ) ) {
				// Confirmation can use this exact owned capability for this response;
				// later website visits regain their original login/cart cookie context.
				setcookie( self::COOKIE, '', array( 'expires' => time() - HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			}
			if ( ! empty( $_GET['wbtm_app_begin'] ) ) {
				$token = WBTM_App_Inventory::text( wp_unslash( $_GET['wbtm_app_begin'] ) );
				$result = self::exchange( $token );
				if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) ); }
				setcookie( self::COOKIE, $result['context_token'], array( 'expires' => $result['expires_at'], 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
				nocache_headers(); header( 'Referrer-Policy: no-referrer' ); wp_safe_redirect( add_query_arg( 'wbtm_app_ready', 1, home_url( '/' ) ) ); exit;
			}
			if ( isset( $_GET['wbtm_app_ready'] ) ) {
				if ( ! self::active() || ! WC()->cart ) { wp_die( 'App checkout expired. Return to the app.', '', array( 'response' => 403 ) ); }
				$result = self::populate_cart( self::$context );
				if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 409 ) ); }
				nocache_headers(); header( 'Referrer-Policy: no-referrer' ); wp_safe_redirect( wc_get_checkout_url() ); exit;
			}
		}

		/** Non-exiting single-use exchange, serialized with logout/password revocation. */
		public static function exchange( $token ) {
			if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) { return self::error( 'Invalid app checkout.', 403 ); }
			return WBTM_App_Auth::locked( 'checkout_bridge_' . hash( 'sha256', $token ), function() use ( $token ) {
				$key = 'wbtm_app_checkout_bridge_' . hash( 'sha256', $token ); $id = get_transient( $key ); $intent = $id ? get_transient( self::key( $id ) ) : false;
				if ( ! is_array( $intent ) || empty( $intent['session'] ) ) { return self::error( 'App checkout expired. Return to the app.', 403 ); }
				return WBTM_App_Auth::locked( 'session_' . $intent['session'], function() use ( $intent, $key ) {
					if ( ! self::context_valid( $intent ) || ! get_transient( $key ) ) { return self::error( 'App checkout expired. Return to the app.', 403 ); }
					delete_transient( $key ); $context_token = bin2hex( random_bytes( 32 ) );
					set_transient( 'wbtm_app_checkout_context_' . hash( 'sha256', $context_token ), $intent['checkout_id'], max( 1, $intent['expires_at'] - time() ) );
					return array( 'context_token' => $context_token, 'expires_at' => $intent['expires_at'] );
				} );
			} );
		}

		/** Non-exiting seam, also used by self-cleaning cart/checkout integration tests. */
		public static function populate_cart( array $intent ) {
			if ( ! self::context_valid( $intent ) || ! WBTM_Functions::use_wc() || ! WC()->cart ) { return self::error( 'Checkout context is unavailable.', 403 ); }
			return self::populate_cart_contents( $intent );
		}

		/** Native REST uses its own explicit principal and an in-memory cart, never browser context. */
		public static function populate_native_cart( array $intent ) {
			if ( ! WBTM_App_Native_Checkout::context_valid( $intent ) || ! WBTM_Functions::use_wc() || ! WC()->cart || ! WC()->session instanceof WBTM_App_Memory_Session ) { return self::error( 'Checkout context is unavailable.', 403 ); }
			return self::populate_cart_contents( $intent );
		}

		private static function populate_cart_contents( array $intent ) {
			if ( ! WC()->cart->is_empty() ) {
				foreach ( WC()->cart->get_cart() as $item ) { if ( ( $item['_wbtm_app_checkout'] ?? '' ) !== $intent['checkout_id'] ) { return self::error( 'This checkout has an existing cart. Return to the app to open a separate checkout.', 409 ); } }
				return true;
			}
			$locks = array(); $added = array(); $quotes = array(); $previous_post = $_POST;
			try {
				$bus_ids = array_map( static function( $q ) { return $q['trip']['bus_id']; }, $intent['journeys'] ); $bus_ids = array_unique( $bus_ids ); sort( $bus_ids, SORT_NUMERIC );
				foreach ( $bus_ids as $id ) { if ( ! WBTM_Cart_Helper::acquire_trip_lock( $id, '', '', '', 5 ) ) { return self::error( 'Inventory is busy. Please retry.', 423 ); } $locks[] = $id; }
				foreach ( $intent['journeys'] as $journey ) {
					$q = WBTM_App_Inventory::quote( $journey['input'] ); if ( is_wp_error( $q ) ) { return $q; }
					$t = $q['trip'];
					$valid = WBTM_Cart_Helper::validate_bus_availability( $t['bus_id'], $t['bp'], $t['dp'], $t['bp_time'], $q['booking_mode'], $q['tickets'], array(), 'full_bus' === $q['booking_mode'] ? $t['total_seat'] : 0 );
					if ( is_wp_error( $valid ) ) { return self::error( $valid->get_error_message(), 409 ); }
					$passengers = WBTM_App_Inventory::passengers( $q, $journey['input']['passengers'] ?? array() );
					if ( is_wp_error( $passengers ) ) { return $passengers; }
					$q['passengers'] = $passengers; $quotes[] = $q;
				}
				foreach ( $quotes as $index => $q ) {
					$post = self::cart_post( $q, $index ? 'return' : 'departure' );
					$_POST = wp_slash( $post ); $_POST['wbtm_form_nonce'] = wp_create_nonce( 'wbtm_form_nonce' );
					$id = $q['trip']['bus_id']; $product_id = (int) get_post_meta( $id, 'link_wc_product', true );
					if ( ! $product_id && class_exists( 'WBTM_Hidden_Product' ) ) { $product_id = WBTM_Hidden_Product::ensure_hidden_wc_product( $id ); }
					$cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( '_wbtm_app_checkout' => $intent['checkout_id'] ) );
					if ( ! $cart_key ) { foreach ( $added as $key ) { WC()->cart->remove_cart_item( $key ); } return self::error( 'This bus could not be added to checkout.', 409 ); }
					$added[] = $cart_key;
					$item = WC()->cart->get_cart_item( $cart_key );
					if ( empty( $item['wbtm_bus_id'] ) || (int) ( $item['wbtm_seats_qty'] ?? 0 ) !== $q['passenger_count'] ) {
						foreach ( $added as $key ) { WC()->cart->remove_cart_item( $key ); }
						return self::error( 'The website booking form could not validate these seats. Please check the bus configuration.', 409 );
					}
				}
				foreach ( $intent['billing'] as $key => $value ) { $setter = 'set_billing_' . $key; if ( is_callable( array( WC()->customer, $setter ) ) ) { WC()->customer->$setter( $value ); } }
				WC()->cart->calculate_totals(); WC()->cart->set_session();
				return true;
			} catch ( Throwable $error ) {
				foreach ( $added as $key ) { WC()->cart->remove_cart_item( $key ); }
				return self::error( 'Checkout could not be prepared. Return to the app and try again.', 500 );
			} finally { $_POST = $previous_post; foreach ( array_reverse( $locks ) as $id ) { WBTM_Cart_Helper::release_trip_lock( $id, '', '', '' ); } }
		}

		private static function cart_post( array $q, $role ) {
			$t = $q['trip'];
			$post = array( 'wbtm_post_id' => $t['bus_id'], 'wbtm_bp_place' => $t['bp'], 'wbtm_dp_place' => $t['dp'], 'wbtm_bp_time' => $t['bp_time'], 'wbtm_dp_time' => $t['dp_time'], 'wbtm_start_point' => $t['start_point'], 'wbtm_start_time' => $t['start_time'], 'wbtm_price_leg' => $t['price_leg'], 'wbtm_journey_type' => $role, 'wbtm_booking_mode' => $q['booking_mode'], 'wbtm_pickup_point' => $q['pickup'], 'wbtm_drop_off_point' => $q['dropoff'], 'extra_service_name' => array_column( $q['extras'], 'name' ), 'extra_service_qty' => array_column( $q['extras'], 'qty' ) );
			foreach ( $q['tickets'] as $ticket ) {
				if ( isset( $ticket['cabin_index'] ) ) {
					$post['wbtm_cabin_mode_enabled'] = 'yes'; $upper = ! empty( $ticket['is_upper'] );
					$seat_key = 'wbtm_selected_seat_cabin_' . ( $upper ? 'dd_' : '' ) . $ticket['cabin_index']; $type_key = 'wbtm_selected_seat_type_cabin_' . ( $upper ? 'dd_' : '' ) . $ticket['cabin_index'];
					$post[ $seat_key ][] = $ticket['seat_name']; $post[ $type_key ][] = $ticket['ticket_type'];
				} elseif ( isset( $ticket['seat_identifier'] ) ) {
					$seat_key = ! empty( $ticket['dd'] ) ? 'wbtm_selected_seat_dd' : 'wbtm_selected_seat'; $type_key = ! empty( $ticket['dd'] ) ? 'wbtm_selected_seat_dd_type' : 'wbtm_selected_seat_type';
					$post[ $seat_key ][] = $ticket['seat_name']; $post[ $type_key ][] = $ticket['ticket_type'];
				} else { $post['wbtm_passenger_type'][] = $ticket['ticket_type']; $post['wbtm_seat_qty'][] = $ticket['ticket_qty']; }
			}
			foreach ( $post as $key => $value ) { if ( is_array( $value ) && 0 === strpos( $key, 'wbtm_selected_seat' ) ) { $post[ $key ] = implode( ',', $value ); } }
			foreach ( $q['passenger_fields'] as $field ) { $post[ $field['id'] ] = array_map( static function( $row ) use ( $field ) { return $row[ $field['id'] ]['value'] ?? ''; }, $q['passengers'] ); }
			return $post;
		}
	}
	new WBTM_App_Checkout();
}
