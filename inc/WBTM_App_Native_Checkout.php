<?php
/** Native checkout over WooCommerce's real cart, validation and order hooks. */
if ( ! defined( 'ABSPATH' ) ) { die; }
require_once __DIR__ . '/WBTM_App_Guest.php';
require_once __DIR__ . '/WBTM_App_Native_Fields.php';
// PRO is loaded before WooCommerce in some installations. Extend WC classes
// only after the dependency has registered its autoloader.
add_action( 'plugins_loaded', static function() { if ( class_exists( 'WooCommerce' ) ) { require_once __DIR__ . '/WBTM_App_Native_Context.php'; } }, 20 );

if ( ! class_exists( 'WBTM_App_Native_Checkout' ) ) {
	class WBTM_App_Native_Checkout {
		const TTL = 600;
		const PREFIX = 'wbtm_app_native_checkout_';
		public function __construct() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
		public function routes() {
			foreach ( array( array( 'selection-summary', 'POST', 'selection_summary', false ), array( 'schema', 'GET', 'schema', true ), array( 'preview', 'POST', 'preview', true ), array( 'submit', 'POST', 'submit', true ) ) as $route ) {
				register_rest_route( WBTM_App_Config::REST_NAMESPACE, '/native-checkout/' . $route[0], array( 'methods' => $route[1], 'callback' => array( $this, $route[2] ), 'permission_callback' => $route[3] ? array( 'WBTM_App_Guest', 'require_principal' ) : '__return_true' ) );
			}
		}
		public static function policy() {
			$settings = WBTM_App_Settings::get(); $wc = WBTM_Functions::use_wc();
			$guest = $wc && ! WC()->checkout()->is_registration_required();
			$registration = 'yes' === $settings['app_registration_enabled'] && ( ! $wc || WC()->checkout()->is_registration_enabled() || 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) );
			return array( 'guest_checkout_enabled' => $guest, 'registration_enabled' => $registration, 'login_required' => ! $guest, 'terms_required' => function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) && wc_terms_and_conditions_checkbox_enabled() );
		}
		public static function capabilities() { return array( 'native_checkout' => WBTM_Functions::use_wc(), 'native_bookings' => WBTM_Functions::use_wc(), 'selection_summary' => true, 'guest_checkout' => self::policy()['guest_checkout_enabled'] ); }
		public static function context_valid( $intent ) {
			return is_array( $intent ) && WBTM_App_Auth::enabled() && preg_match( '/^[a-f0-9]{32}$/D', $intent['checkout_id'] ?? '' ) && ! empty( $intent['expires_at'] ) && $intent['expires_at'] > time() && isset( $intent['principal'] ) && WBTM_App_Guest::active( $intent['principal'] );
		}
		public static function error( $message, $status = 400, $code = 'wbtm_app_native_checkout' ) { return WBTM_App_Auth::error( $code, wp_strip_all_tags( $message ), $status ); }
		private static function body( $request ) {
			$body = $request->get_json_params();
			return is_array( $body ) && strlen( $request->get_body() ) <= 131072 ? $body : self::error( 'Send a valid checkout request smaller than 128 KB.' );
		}
		private static function allowed( array $principal ) {
			if ( ! WBTM_Functions::use_wc() ) { return self::error( 'Native WooCommerce checkout is unavailable for this payment mode.', 409 ); }
			if ( 'guest' === $principal['kind'] && ! self::policy()['guest_checkout_enabled'] ) { return self::error( 'Please sign in or create an account to place this booking.', 403, 'wbtm_app_login_required' ); }
			return true;
		}
		private static function input_journey( array $input ) {
			// Persist only supported selection inputs, never client prices, totals or ownership.
			return array_intersect_key( $input, array_flip( array( 'bus_id', 'id', 'bp', 'dp', 'date', 'selections', 'extras', 'booking_mode', 'pickup', 'dropoff', 'passengers' ) ) );
		}
		public static function journeys( $input, $summary = false ) {
			if ( ! is_array( $input ) || ! count( $input ) || count( $input ) > 2 ) { return self::error( 'Choose one journey or an outbound and return journey.' ); }
			$prepared = array();
			foreach ( array_values( $input ) as $index => $journey ) {
				if ( ! is_array( $journey ) ) { return self::error( 'Invalid journey.' ); }
				$journey = self::input_journey( $journey );
				if ( $summary ) { unset( $journey['passengers'] ); }
				$q = WBTM_App_Inventory::quote( $journey, $summary ); if ( is_wp_error( $q ) ) { return $q; }
				if ( ! $summary ) { $passengers = WBTM_App_Inventory::passengers( $q, $journey['passengers'] ?? array() ); if ( is_wp_error( $passengers ) ) { return $passengers; } $q['passengers'] = $passengers; }
				$prepared[] = $q;
			}
			if ( 2 === count( $prepared ) ) {
				$a = $prepared[0]['trip']; $b = $prepared[1]['trip'];
				if ( $a['bp'] !== $b['dp'] || $a['dp'] !== $b['bp'] || new DateTimeImmutable( $b['bp_time'], wp_timezone() ) < new DateTimeImmutable( $a['dp_time'], wp_timezone() ) ) { return self::error( 'The return must reverse the outbound route and depart after arrival.' ); }
			}
			return $prepared;
		}
		public static function ticket_lines( array $q ) {
			$lines = array();
			foreach ( $q['tickets'] as $ticket ) {
				$seat = isset( $ticket['seat_identifier'] ) ? ( $ticket['cabin_name'] ?? ( ! empty( $ticket['dd'] ) ? __( 'Upper deck', 'addon-bus--ticket-booking-with-seat-pro' ) : __( 'Lower deck', 'addon-bus--ticket-booking-with-seat-pro' ) ) ) . ' ' . $ticket['seat_name'] . ' · ' : '';
				$lines[] = array( 'label' => $seat . $ticket['ticket_name'], 'quantity' => (int) $ticket['ticket_qty'], 'unit_price' => (float) $ticket['ticket_price'], 'total' => (float) $ticket['ticket_qty'] * (float) $ticket['ticket_price'] );
			}
			return $lines;
		}
		public static function extra_lines( array $q ) {
			$lines = array(); foreach ( $q['extras'] as $extra ) {
				$quantity = (int) $extra['qty'] * ( 'per_passenger' === $extra['charge_type'] ? max( 1, $q['passenger_count'] ) : 1 ); $total = (float) WBTM_Functions::ex_service_line_total( $extra, $q['passenger_count'] );
				$lines[] = array( 'label' => $extra['name'], 'quantity' => $quantity, 'unit_price' => (float) $extra['price'], 'total' => $total, 'charge_type' => $extra['charge_type'] );
			}
			return $lines;
		}
		public function selection_summary( $request ) {
			$rate = WBTM_App_Auth::rate_limit( 'native_selection_summary', 180 ); if ( is_wp_error( $rate ) ) { return $rate; }
			$body = self::body( $request ); if ( is_wp_error( $body ) ) { return $body; }
			$quotes = self::journeys( $body['journeys'] ?? array(), true ); if ( is_wp_error( $quotes ) ) { return $quotes; }
			$journeys = array(); $base = 0; $extras = 0;
			foreach ( $quotes as $q ) {
				$t = $q['trip']; $tickets = self::ticket_lines( $q ); $services = self::extra_lines( $q );
				$journeys[] = array( 'bus_id' => $t['bus_id'], 'name' => get_the_title( $t['bus_id'] ), 'bp' => $t['bp'], 'dp' => $t['dp'], 'date' => $t['date'], 'departure_time' => $t['bp_time'], 'arrival_time' => $t['dp_time'], 'passenger_count' => $q['passenger_count'], 'tickets' => $tickets, 'extras' => $services, 'subtotal' => (float) $q['total'] );
				$base += $q['base_price']; $extras += $q['total'] - $q['base_price'];
			}
			return new WP_REST_Response( array( 'currency' => $quotes[0]['currency'], 'journeys' => $journeys, 'totals' => array( 'tickets' => (float) $base, 'extras' => (float) $extras, 'subtotal' => (float) ( $base + $extras ), 'total' => (float) ( $base + $extras ) ), 'taxes_pending' => WBTM_Functions::use_wc() && wc_tax_enabled() ) );
		}
		private static function base_intent( array $principal ) {
			return array( 'checkout_id' => bin2hex( random_bytes( 16 ) ), 'hold_token' => bin2hex( random_bytes( 16 ) ), 'principal' => $principal, 'expires_at' => time() + self::TTL, 'billing' => array(), 'journeys' => array() );
		}
		private static function choices( $options ) { $result = array(); foreach ( (array) $options as $value => $label ) { if ( is_scalar( $label ) ) { $result[] = array( 'value' => (string) $value, 'label' => WBTM_App_Inventory::text( $label ) ); } } return $result; }
		private static function deposit_choices( $ids ) {
			if ( ! class_exists( 'WBTM_Deposit' ) ) { return array( 'deposit_choices' => array(), 'deposit_default' => 'full' ); }
			$enabled = false; $forced = false;
			foreach ( array_slice( explode( ',', $ids ), 0, 2 ) as $id ) { $id = absint( $id ); if ( 'wbtm_bus' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) { continue; } $config = WBTM_Deposit::bus_config( $id ); if ( ! empty( $config['enabled'] ) ) { $enabled = true; $forced = $forced || empty( $config['offer_choice'] ); } }
			$options = array(); if ( $enabled ) { if ( ! $forced ) { $options[] = array( 'value' => 'full', 'label' => __( 'Pay in full', 'addon-bus--ticket-booking-with-seat-pro' ) ); } $options[] = array( 'value' => 'deposit', 'label' => __( 'Pay deposit', 'addon-bus--ticket-booking-with-seat-pro' ) ); }
			return array( 'deposit_choices' => $options, 'deposit_default' => $forced ? 'deposit' : 'full' );
		}
		private static function fields( $checkout, $group ) {
			$result = array(); foreach ( $checkout->get_checkout_fields( $group ) as $key => $field ) {
				if ( true === ( $field['hidden'] ?? false ) ) { continue; }
				$type = $field['type'] ?? 'text'; $value = $checkout->get_value( $key );
				$result[] = array( 'id' => $key, 'label' => WBTM_App_Inventory::text( $field['label'] ?? $key ), 'type' => $type, 'required' => ! empty( $field['required'] ), 'options' => self::choices( $field['options'] ?? array() ), 'value' => is_scalar( $value ) ? (string) $value : '', 'placeholder' => WBTM_App_Inventory::text( $field['placeholder'] ?? '' ) );
			} return $result;
		}
		public static function methods() {
			$result = array(); foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
				$result[] = array( 'id' => $gateway->id, 'title' => WBTM_App_Inventory::text( $gateway->get_title() ), 'description' => WBTM_App_Inventory::description( $gateway->get_description() ), 'native_supported' => in_array( $gateway->id, array( 'cod', 'bacs', 'cheque' ), true ) && in_array( get_class( $gateway ), array( 'WC_Gateway_COD', 'WC_Gateway_BACS', 'WC_Gateway_Cheque' ), true ) );
			} return $result;
		}
		public function schema( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; }
			$rate = WBTM_App_Auth::rate_limit( 'native_schema', 120, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			return self::schema_for_principal( $p, $request );
		}
		/** Also supplies the first guest form in the session-creation response. */
		public static function schema_for_principal( array $p, WP_REST_Request $request ) {
			if ( ! WBTM_Functions::use_wc() ) { return self::error( 'Native WooCommerce checkout is unavailable.', 409 ); }
			$intent = self::base_intent( $p );
			return WBTM_App_Native_Context::run( $p, $intent, static function() use ( $request ) {
				$country = strtoupper( WBTM_App_Auth::string_param( $request, 'country', 2 ) ); $state = WBTM_App_Auth::string_param( $request, 'state', 100 );
				if ( $country && ! isset( WC()->countries->get_allowed_countries()[ $country ] ) ) { return self::error( 'Select a valid billing country.' ); }
				if ( ! $country ) { $country = WC()->customer->get_billing_country(); } if ( ! $country ) { $country = wc_get_customer_default_location()['country'] ?? ''; }
				if ( $country ) { WC()->customer->set_billing_country( $country ); } if ( $state ) { WC()->customer->set_billing_state( $state ); }
				$checkout = new WBTM_App_Native_WC_Checkout(); $country = WC()->customer->get_billing_country(); $settings = WBTM_App_Settings::get();
				$additional = WBTM_App_Native_Fields::schema(); if ( is_wp_error( $additional ) ) { return $additional; }
				return new WP_REST_Response( array_merge( array( 'billing_fields' => array_merge( self::fields( $checkout, 'billing' ), $additional['billing_fields'] ), 'order_fields' => array_merge( self::fields( $checkout, 'order' ), $additional['order_fields'] ), 'countries' => self::choices( WC()->countries->get_allowed_countries() ), 'states' => self::choices( WC()->countries->get_states( $country ) ), 'payment_methods' => self::methods(), 'coupons_enabled' => wc_coupons_enabled(), 'policy' => self::policy(), 'terms_url' => $settings['app_terms_url'] ?: wc_get_page_permalink( 'terms' ), 'privacy_url' => $settings['app_privacy_url'] ?: get_privacy_policy_url() ), self::deposit_choices( WBTM_App_Auth::string_param( $request, 'bus_ids', 40 ) ) ) );
			} );
		}
		private static function prepare_data( array $intent, $checkout ) {
			$data = array( 'payment_method' => $intent['payment_method'], 'ship_to_different_address' => false, 'shipping_method' => array(), 'terms' => ! empty( $intent['terms_accepted'] ) ? 1 : 0, 'terms-field' => self::policy()['terms_required'] ? 1 : 0, 'createaccount' => 0 );
			foreach ( array( 'billing', 'order' ) as $group ) {
				foreach ( $checkout->get_checkout_fields( $group ) as $key => $field ) {
					$raw = 'billing' === $group ? ( $intent['billing'][ substr( $key, 8 ) ] ?? $intent['fields'][ $key ] ?? '' ) : ( $intent['fields'][ $key ] ?? '' );
					if ( ! is_scalar( $raw ) || strlen( (string) $raw ) > 2000 ) { return self::error( 'Check checkout field: ' . ( $field['label'] ?? $key ) ); }
					$type = $field['type'] ?? 'text';
					if ( ! in_array( $type, array( 'text', 'tel', 'email', 'number', 'textarea', 'select', 'checkbox', 'country', 'state', 'date', 'hidden', 'radio' ), true ) ) { return self::error( 'This checkout field requires an additional native adapter: ' . ( $field['label'] ?? $key ), 409 ); }
					$value = 'textarea' === $type ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
					if ( 'checkbox' === $type ) { $value = in_array( $raw, array( 1, true, '1', 'yes', 'on' ), true ) ? 1 : ''; }
					if ( in_array( $type, array( 'select', 'radio' ), true ) && '' !== $value && ! array_key_exists( $value, $field['options'] ?? array() ) ) { return self::error( 'Check checkout field: ' . ( $field['label'] ?? $key ) ); }
					$data[ $key ] = apply_filters( 'woocommerce_process_checkout_field_' . $key, $value );
				}
			}
			return apply_filters( 'woocommerce_checkout_posted_data', $data );
		}
		public static function summary( array $intent ) {
			$cart = WC()->cart; $items = array(); $cart_items = array_values( $cart->get_cart() );
			foreach ( $intent['journeys'] as $index => $q ) {
				$lines = array_merge( self::ticket_lines( $q ), self::extra_lines( $q ) ); $actual = (float) ( $cart_items[ $index ]['line_subtotal'] ?? 0 ); $explained = array_sum( array_column( $lines, 'total' ) );
				if ( abs( $actual - $explained ) > pow( 10, -wc_get_price_decimals() ) / 2 ) { $adjustment = $actual - $explained; $lines[] = array( 'label' => __( 'Fare adjustment', 'addon-bus--ticket-booking-with-seat-pro' ), 'quantity' => 1, 'unit_price' => $adjustment, 'total' => $adjustment ); }
				$items = array_merge( $items, $lines );
			}
			$fees = array(); foreach ( $cart->get_fees() as $fee ) { $fees[] = array( 'label' => WBTM_App_Inventory::text( $fee->name ), 'total' => (float) $fee->total ); }
			$taxes = array(); foreach ( $cart->get_tax_totals() as $tax ) { $taxes[] = array( 'label' => WBTM_App_Inventory::text( $tax->label ), 'total' => (float) $tax->amount ); }
			$coupons = array(); foreach ( $cart->get_applied_coupons() as $code ) { $coupons[] = array( 'code' => $code, 'discount' => (float) $cart->get_coupon_discount_amount( $code ) ); }
			// Read the deposit plugin's actual calculated fee instead of pricing it independently.
			$balance = 0; $deposit_label = __( 'Remaining Balance (Due Later)', 'addon-bus--ticket-booking-with-seat-pro' );
			foreach ( $cart->get_fees() as $fee ) { if ( $fee->name === $deposit_label && $fee->amount < 0 ) { $balance += -(float) $fee->amount; } }
			$now = (float) $cart->get_total( 'edit' );
			return array( 'currency' => get_woocommerce_currency(), 'items' => $items, 'totals' => array( 'subtotal' => (float) $cart->get_subtotal(), 'discount' => (float) $cart->get_discount_total(), 'tax' => (float) $cart->get_total_tax(), 'fees' => (float) $cart->get_fee_total(), 'total' => $now + $balance, 'due_now' => $now, 'balance_due' => (float) $balance ), 'fees' => $fees, 'taxes' => $taxes, 'coupons' => $coupons );
		}
		private static function calculate( array &$intent, $validate = true ) {
			$quotes = self::journeys( array_map( static function( $q ) { return $q['input']; }, $intent['journeys'] ) ); if ( is_wp_error( $quotes ) ) { return $quotes; } $intent['journeys'] = $quotes;
			$checkout = new WBTM_App_Native_WC_Checkout();
			foreach ( $intent['billing'] as $key => $value ) { $setter = 'set_billing_' . $key; if ( is_callable( array( WC()->customer, $setter ) ) ) { WC()->customer->$setter( $value ); } }
			$data = self::prepare_data( $intent, $checkout ); if ( is_wp_error( $data ) ) { return $data; }
			$_POST = wp_slash( $data );
			$result = WBTM_App_Checkout::populate_native_cart( $intent ); if ( is_wp_error( $result ) ) { return $result; }
			$checkout->prepare_customer( $data );
			if ( $intent['coupon_codes'] && ! wc_coupons_enabled() ) { return self::error( 'Coupons are disabled by this store.' ); }
			foreach ( $intent['coupon_codes'] as $code ) { if ( ! WC()->cart->apply_coupon( $code ) ) { return self::error( 'This coupon is invalid or unavailable: ' . $code ); } }
			WC()->cart->calculate_totals();
			$additional = WBTM_App_Native_Fields::validate( $intent ); if ( is_wp_error( $additional ) ) { return $additional; } $data['wbtm_native_additional_fields'] = $additional;
			if ( WC()->cart->needs_shipping() ) { return self::error( 'A bus product must be virtual to use native checkout.', 409 ); }
			if ( $validate ) {
				$errors = $checkout->validate_native( $data );
				if ( $errors->has_errors() ) { return self::error( implode( ' ', $errors->get_error_messages() ) ); }
			}
			$methods = self::methods(); $intent['summary'] = self::summary( $intent ); $intent['checkout_data'] = $data; $intent['payment_methods'] = $methods;
			$intent['revision'] = hash( 'sha256', wp_json_encode( array( $intent['summary'], $data, $methods ) ) );
			return $checkout;
		}
		private static function public_preview( array $intent ) { return array( 'checkout_id' => $intent['checkout_id'], 'revision' => $intent['revision'], 'expires_at' => $intent['expires_at'], 'summary' => $intent['summary'], 'payment_methods' => $intent['payment_methods'] ); }
		public function preview( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; } $allowed = self::allowed( $p ); if ( is_wp_error( $allowed ) ) { return $allowed; }
			$rate = WBTM_App_Auth::rate_limit( 'native_preview', 60, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			$body = self::body( $request ); if ( is_wp_error( $body ) ) { return $body; }
			$quotes = self::journeys( $body['journeys'] ?? array() ); if ( is_wp_error( $quotes ) ) { return $quotes; }
			if ( ! is_array( $body['billing'] ?? null ) || ! is_array( $body['fields'] ?? array() ) || ! is_array( $body['coupon_codes'] ?? array() ) || count( $body['coupon_codes'] ?? array() ) > 10 ) { return self::error( 'Send billing details, checkout fields and coupon codes.' ); }
			$intent = self::base_intent( $p ); $intent['journeys'] = $quotes;
			$intent['billing'] = $body['billing']; $intent['fields'] = $body['fields'] ?? array(); $intent['coupon_codes'] = array();
			foreach ( $body['coupon_codes'] ?? array() as $coupon ) { if ( ! is_string( $coupon ) || strlen( $coupon ) > 100 ) { return self::error( 'Invalid coupon code.' ); } $intent['coupon_codes'][] = wc_format_coupon_code( $coupon ); }
			$intent['coupon_codes'] = array_values( array_unique( $intent['coupon_codes'] ) );
			$intent['payment_method'] = WBTM_App_Inventory::text( $body['payment_method'] ?? '' ); $intent['terms_accepted'] = true === ( $body['terms_accepted'] ?? false );
			$intent['deposit_choice'] = 'deposit' === ( $body['deposit_choice'] ?? '' ) ? 'deposit' : 'full';
			// Reject unknown/oversized address values before calling customer setters.
			foreach ( $intent['billing'] as $key => $value ) { if ( ! is_string( $key ) || ! is_scalar( $value ) || strlen( (string) $value ) > 2000 ) { return self::error( 'Invalid billing field.' ); } $intent['billing'][ $key ] = sanitize_text_field( (string) $value ); }
			return WBTM_App_Native_Context::run( $p, $intent, static function() use ( &$intent ) {
				$calculated = self::calculate( $intent ); if ( is_wp_error( $calculated ) ) { return $calculated; }
				set_transient( self::PREFIX . $intent['checkout_id'], $intent, self::TTL );
				return new WP_REST_Response( self::public_preview( $intent ), 201 );
			} );
		}
		public function submit( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; }
			$rate = WBTM_App_Auth::rate_limit( 'native_submit', 30, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			$body = self::body( $request ); if ( is_wp_error( $body ) ) { return $body; }
			$id = WBTM_App_Inventory::text( $body['checkout_id'] ?? '' ); $revision = WBTM_App_Inventory::text( $body['revision'] ?? '' ); $key = WBTM_App_Inventory::text( $body['idempotency_key'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{32}$/D', $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $revision ) || ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $key ) || ( self::policy()['terms_required'] && true !== ( $body['terms_accepted'] ?? false ) ) ) { return self::error( 'Review checkout and accept the terms before placing your order.' ); }
			$request_key = 'wbtm_app_native_request_' . hash( 'sha256', $p['owner'] . '|' . $key ); $fingerprint = hash( 'sha256', $id . '|' . $revision );
			return WBTM_App_Auth::locked( ( 'guest' === $p['kind'] ? 'guest_' : 'session_' ) . $p['session'], static function() use ( $request, $p, $id, $revision, $request_key, $fingerprint ) {
				$current = WBTM_App_Guest::fresh_principal( $request ); if ( is_wp_error( $current ) ) { return $current; }
				return WBTM_App_Auth::locked( $request_key, static function() use ( $p, $id, $revision, $request_key, $fingerprint ) {
				return WBTM_App_Auth::locked( 'native_checkout_' . $id, static function() use ( $p, $id, $revision, $request_key, $fingerprint ) {
					$prior = get_option( $request_key, array() );
					if ( $prior && ! hash_equals( $prior['fingerprint'] ?? '', $fingerprint ) ) { return self::error( 'This request key was used for different checkout details.', 409 ); }
					$claim_key = 'wbtm_app_native_order_' . $id; $claim = get_option( $claim_key, array() );
					if ( $claim && ! hash_equals( $claim['owner'] ?? '', $p['owner'] ) ) { return self::error( 'Checkout not found.', 404 ); }
					if ( ! empty( $claim['order_id'] ) ) { $order = WBTM_App_Receipt::owned_order( $claim['order_id'], $p ); return is_wp_error( $order ) ? $order : new WP_REST_Response( WBTM_App_Receipt::data( $order ), 200 ); }
					if ( $claim ) { return self::error( 'This order is being processed. Check My Tickets before retrying.', 409 ); }
					$allowed = self::allowed( $p ); if ( is_wp_error( $allowed ) ) { return $allowed; }
					$intent = get_transient( self::PREFIX . $id );
					if ( ! self::context_valid( $intent ) || ! hash_equals( $intent['principal']['owner'], $p['owner'] ) || ! hash_equals( $intent['principal']['session'], $p['session'] ) ) { return self::error( 'Checkout expired. Review your booking again.', 410 ); }
					$intent['principal'] = $p;
					$bus_ids = array_values( array_unique( array_column( array_column( $intent['journeys'], 'trip' ), 'bus_id' ) ) ); sort( $bus_ids, SORT_NUMERIC ); $locks = array();
					try {
						foreach ( $bus_ids as $bus_id ) { if ( ! WBTM_Cart_Helper::acquire_trip_lock( $bus_id, '', '', '', 5 ) ) { return self::error( 'Inventory is busy. Please retry.', 423 ); } $locks[] = $bus_id; }
						return WBTM_App_Native_Context::run( $p, $intent, static function() use ( &$intent, $p, $revision, $claim_key, $request_key, $fingerprint ) {
							$checkout = self::calculate( $intent ); if ( is_wp_error( $checkout ) ) { return $checkout; }
							if ( ! hash_equals( $revision, $intent['revision'] ) ) {
								set_transient( self::PREFIX . $intent['checkout_id'], $intent, max( 1, $intent['expires_at'] - time() ) );
								return new WP_Error( 'wbtm_app_checkout_changed', 'Your checkout has changed. Review the updated price before placing the order.', array( 'status' => 409, 'checkout' => self::public_preview( $intent ) ) );
							}
							$gateways = WC()->payment_gateways()->get_available_payment_gateways(); $method = $intent['payment_method'];
							$supported = array_column( array_filter( $intent['payment_methods'], static function( $m ) { return $m['native_supported']; } ), 'id' );
							if ( WC()->cart->needs_payment() && ( ! isset( $gateways[ $method ] ) || ! in_array( $method, $supported, true ) ) ) { return self::error( 'This payment method requires a native payment integration. Choose an available offline method.', 409, 'wbtm_app_payment_authorization_required' ); }
							$claim = array( 'owner' => $p['owner'], 'started_at' => time(), 'order_id' => 0 );
							if ( ! add_option( $claim_key, $claim, '', false ) ) { return self::error( 'This order is already being processed.', 409 ); }
							update_option( $request_key, array( 'fingerprint' => $fingerprint, 'checkout_id' => $intent['checkout_id'] ), false );
							$order_id = 0; $mail_cleanup = null;
							// PDF generation and network delivery must not hold the seat lock or order response.
							$defer_ticket = static function( $defer, $mail_order_id ) use ( $intent ) {
								$mail_order = wc_get_order( $mail_order_id );
								return $mail_order && $mail_order->get_meta( '_wbtm_app_native_checkout_id' ) === $intent['checkout_id'] ? true : $defer;
							};
							$defer_wc = static function() { return true; };
							// HPOS otherwise re-saves the complete order when a metadata
							// write crosses another clock second. Remote databases amplify
							// that into repeated sync/report invalidation per metadata field.
							// This explicit create/payment sequence already saves order props;
							// metadata writes retain their normal data-store/cache hooks.
							$batch_meta = static function() { return false; };
							add_filter( 'wbtm_ticket_mail_defer', $defer_ticket, 10, 2 );
							add_filter( 'woocommerce_defer_transactional_emails', $defer_wc, PHP_INT_MAX );
							add_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', $batch_meta, PHP_INT_MAX );
							$mark = static function( $order ) use ( $intent, $p ) {
								$order->set_customer_id( $p['user_id'] ); $order->set_created_via( 'wbtm-native-app' ); $order->update_meta_data( '_wbtm_app', 1 ); $order->update_meta_data( '_wbtm_app_native_owner', $p['owner'] );
								if ( 'guest' === $p['kind'] ) { $order->update_meta_data( '_wbtm_app_guest_owner', $p['owner'] ); }
								$order->update_meta_data( '_wbtm_app_native_checkout_id', $intent['checkout_id'] ); $order->update_meta_data( '_wbtm_app_native_summary', $intent['summary'] );
								$order->update_meta_data( '_wbtm_app_native_journeys', WBTM_App_Receipt::journeys_from_quotes( $intent['journeys'] ) );
								WBTM_App_Native_Fields::persist( $order, $intent['checkout_data']['wbtm_native_additional_fields'] ?? array() );
							};
							$remember = static function( $order ) use ( &$order_id, &$mail_cleanup, $claim_key, $request_key, $fingerprint, $intent, $p ) {
								$order_id = $order->get_id(); update_option( $claim_key, array( 'owner' => $p['owner'], 'started_at' => time(), 'order_id' => $order_id ), false );
								update_option( $request_key, array( 'fingerprint' => $fingerprint, 'checkout_id' => $intent['checkout_id'], 'order_id' => $order_id ), false );
								if ( ! $mail_cleanup ) { $mail_cleanup = WBTM_App_Receipt::observe_mail( $order ); }
							};
							$remember_new = static function( $id, $order ) use ( $remember, $intent ) { if ( $order instanceof WC_Order && $order->get_meta( '_wbtm_app_native_checkout_id' ) === $intent['checkout_id'] ) { $remember( $order ); } };
							$customer_id = static function() use ( $p ) { return $p['user_id']; };
							add_filter( 'woocommerce_checkout_customer_id', $customer_id, PHP_INT_MAX );
							add_action( 'woocommerce_checkout_create_order', $mark, 1 ); add_action( 'woocommerce_checkout_order_created', $remember, 1 ); add_action( 'woocommerce_new_order', $remember_new, 1, 2 );
							try {
								$created = $checkout->create_order( $intent['checkout_data'] );
								if ( is_wp_error( $created ) ) { throw new RuntimeException( 'Order creation requires reconciliation.' ); }
								$order_id = (int) $created; $order = wc_get_order( $order_id );
								if ( ! $order || $order->get_meta( '_wbtm_app_native_checkout_id' ) !== $intent['checkout_id'] || ! hash_equals( $order->get_meta( '_wbtm_app_native_owner' ), $p['owner'] ) ) { throw new RuntimeException( 'Unexpected checkout order.' ); }
								$remember( $order );
								// Ownership/idempotency are durable BEFORE notification and gateway hooks.
								do_action( 'woocommerce_checkout_order_processed', $order_id, $intent['checkout_data'], $order );
								$order = wc_get_order( $order_id );
								if ( $order->needs_payment() ) { $result = $gateways[ $method ]->process_payment( $order_id ); if ( 'success' !== ( $result['result'] ?? '' ) ) { throw new RuntimeException( 'The offline gateway could not confirm this order.' ); } }
								elseif ( (float) $order->get_total() <= 0 ) { $order->payment_complete(); }
								$order = wc_get_order( $order_id ); $order->update_meta_data( '_wbtm_app_native_processed', 1 ); $order->save_meta_data(); WBTM_App_Lifecycle::schedule( $order_id );
								WBTM_App_Receipt::queue_ticket_mail( $order );
								WBTM_Query::flush_booked_map(); return new WP_REST_Response( WBTM_App_Receipt::data( wc_get_order( $order_id ) ), 201 );
							} catch ( Throwable $error ) {
								if ( $order_id ) { $order = wc_get_order( $order_id ); if ( $order ) { $order->update_meta_data( '_wbtm_app_native_processing_review', 1 ); $order->save_meta_data(); return new WP_REST_Response( WBTM_App_Receipt::data( $order ), 201 ); } }
								// Retain a started claim on ambiguous failure: never risk duplicate reservations.
								return self::error( 'Your order needs a status check. Check My Tickets before retrying.', 503 );
							} finally { remove_filter( 'woocommerce_orders_table_datastore_should_save_after_meta_change', $batch_meta, PHP_INT_MAX ); remove_filter( 'wbtm_ticket_mail_defer', $defer_ticket, 10 ); remove_filter( 'woocommerce_defer_transactional_emails', $defer_wc, PHP_INT_MAX ); remove_action( 'woocommerce_checkout_create_order', $mark, 1 ); remove_action( 'woocommerce_checkout_order_created', $remember, 1 ); remove_action( 'woocommerce_new_order', $remember_new, 1 ); remove_filter( 'woocommerce_checkout_customer_id', $customer_id, PHP_INT_MAX ); if ( $mail_cleanup ) { $mail_cleanup(); } }
						} );
					} finally { foreach ( array_reverse( $locks ) as $bus_id ) { WBTM_Cart_Helper::release_trip_lock( $bus_id, '', '', '' ); } }
				} );
				} );
			} );
		}
	}
	new WBTM_App_Native_Checkout();
}
