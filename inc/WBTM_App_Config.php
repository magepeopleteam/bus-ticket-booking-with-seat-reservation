<?php
/**
 * Read-only bootstrap contract for the native customer app.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Config' ) ) {
	class WBTM_App_Config {
		const REST_NAMESPACE = 'wbtm-app/v1';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public function register_routes() {
			register_rest_route( self::REST_NAMESPACE, '/config', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			) );
		}

		/** All returned values are deliberately public; never serialize raw options. */
		public function get_config() {
			$settings = WBTM_App_Settings::get();
			if ( 'yes' !== $settings['app_enabled'] ) {
				return new WP_Error( 'wbtm_app_disabled', __( 'App configuration is disabled.', 'addon-bus--ticket-booking-with-seat-pro' ), array( 'status' => 404 ) );
			}
			$use_wc = WBTM_Functions::use_wc();
			$booking_operations = is_callable( array( 'WBTM_App_Booking', 'capabilities' ) ) ? WBTM_App_Booking::capabilities() : array();
			if ( is_callable( array( 'WBTM_App_Checkout', 'capabilities' ) ) ) {
				$booking_operations = array_merge( $booking_operations, WBTM_App_Checkout::capabilities() );
			}
			if ( is_callable( array( 'WBTM_App_Native_Checkout', 'capabilities' ) ) ) { $booking_operations = array_merge( $booking_operations, WBTM_App_Native_Checkout::capabilities() ); }
			$policy = class_exists( 'WBTM_App_Native_Checkout' ) ? WBTM_App_Native_Checkout::policy() : array( 'registration_enabled' => 'yes' === $settings['app_registration_enabled'], 'guest_checkout_enabled' => false, 'login_required' => true );
			$logo   = $settings['app_logo'] ?: wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
			$labels = array();
			foreach ( array( 'from', 'to', 'search', 'journey_date', 'return_date', 'pickup_point', 'drop_off_point', 'seat', 'seater', 'sleeper', 'passenger_info', 'ticket_type', 'ex_service', 'total' ) as $label ) {
				$callback = array( 'WBTM_Translations', 'text_' . $label );
				if ( is_callable( $callback ) ) {
					$labels[ $label ] = self::plain_text( call_user_func( $callback ) );
				}
			}
			$response = new WP_REST_Response( array(
				'schema_version' => 1,
				'api_namespace'  => self::REST_NAMESPACE,
				'site' => array(
					'url'      => home_url( '/' ),
					'rest_url' => rest_url(),
					'name'     => self::plain_text( get_bloginfo( 'name' ) ),
					'locale'   => get_locale(),
					'rtl'      => is_rtl(),
					'timezone' => wp_timezone_string(),
				),
				'branding' => array(
					'name'             => $settings['app_name'] ?: self::plain_text( get_bloginfo( 'name' ) ),
					'logo_url'         => $logo ? esc_url_raw( $logo, array( 'https', 'http' ) ) : '',
					'primary_color'    => $settings['app_primary_color'],
					'background_color' => $settings['app_background_color'],
				),
				'home' => WBTM_App_Home::public_config(),
				'support' => array(
					'email' => $settings['app_support_email'],
					'phone' => $settings['app_support_phone'],
					'url'   => $settings['app_support_url'],
				),
				'legal' => array(
					'terms_url'            => $settings['app_terms_url'],
					'privacy_url'          => $settings['app_privacy_url'] ?: get_privacy_policy_url(),
					'account_deletion_url' => $settings['app_account_deletion_url'],
				),
				'release' => array(
					'ios' => array( 'minimum_version' => $settings['app_min_ios_version'], 'store_url' => $settings['app_ios_store_url'] ),
					'android' => array( 'minimum_version' => $settings['app_min_android_version'], 'store_url' => $settings['app_android_store_url'] ),
					'maintenance' => 'yes' === $settings['app_maintenance'],
					'message'     => $settings['app_maintenance_message'],
				),
				'auth' => array(
					'registration_enabled' => $policy['registration_enabled'],
					'minimum_password_length' => 10,
					'login_required_at_booking' => $policy['login_required'],
					'guest_checkout_enabled' => $policy['guest_checkout_enabled'],
				),
				'push' => array(
					'enabled' => class_exists( 'WBTM_App_Push' ) && WBTM_App_Push::enabled(),
					'project_id' => $settings['app_expo_project_id'],
				),
				'booking' => array(
					'engine'                 => WBTM_Functions::booking_mode(),
					'return_search_enabled'  => 'disable' !== WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'bus_return_show', 'enable' ),
					'guest_checkout_enabled' => $policy['guest_checkout_enabled'],
					'website_available'      => WBTM_Functions::is_booking_available(),
					'website_login_required' => $use_wc ? 'yes' !== get_option( 'woocommerce_enable_guest_checkout', 'yes' ) : WBTM_Functions::login_required(),
					'seat_hold_seconds'      => class_exists( 'WBTM_Seat_Hold' ) && WBTM_Seat_Hold::is_enabled() ? WBTM_Seat_Hold::get_ttl_seconds() : 0,
					'payment_timeout_seconds' => (int) $settings['app_payment_timeout_minutes'] * MINUTE_IN_SECONDS,
					'currency'               => $this->currency(),
					'configured_gateways'    => $this->configured_gateways( $use_wc ),
					'gateways_require_checkout_context' => $use_wc,
				),
				'backend_features' => array(
					'pro'                => class_exists( 'WBTM_Function_PRO' ),
					'full_bus'           => WBTM_Functions::is_full_bus_feature_enabled(),
					'deposits'           => class_exists( 'WBTM_Deposit' ) && WBTM_Deposit::is_enabled(),
					'return_discount'    => class_exists( 'WBTM_return_discount' ),
					'coupons'            => $use_wc && wc_coupons_enabled(),
					'taxes'              => $use_wc && wc_tax_enabled(),
					'customer_portal'    => class_exists( 'WBTM_Customer_Portal' ),
					'pdf_library_loaded' => class_exists( 'Mpdf\\Mpdf' ),
				),
				// Backend features are not a promise that a native API adapter exists.
				'operations' => array_merge( array(
					'config' => true, 'legacy_search' => class_exists( 'WBTM_REST_API' ),
					'legacy_seats' => class_exists( 'WBTM_REST_API' ),
					'auth' => class_exists( 'WBTM_App_Auth' ), 'quote' => false, 'holds' => false, 'create_booking' => false,
					'my_bookings' => false, 'payment' => false, 'pdf' => false,
					'cancel' => false, 'reschedule' => false,
					'push' => class_exists( 'WBTM_App_Push' ) && WBTM_App_Push::enabled(),
					'device_registration' => class_exists( 'WBTM_App_Account' ),
					'account_deletion' => class_exists( 'WBTM_App_Account' ),
					'attendance_read' => is_callable( array( 'WBTM_Operator', 'enabled' ) ) && WBTM_Operator::enabled(),
					'qr_display' => is_callable( array( 'WBTM_Operator', 'enabled' ) ) && WBTM_Operator::enabled() && 'yes' === WBTM_Operator_Settings::get()['qr_enabled'],
				), $booking_operations ),
				'links' => array(
					'routes' => rest_url( 'wbtm/v1/routes' ),
					'search' => rest_url( 'wbtm/v1/buses/search' ),
				),
				'labels' => $labels,
			) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		private function currency() {
			$woo = function_exists( 'get_woocommerce_currency' );
			return array(
				'code'               => $woo ? get_woocommerce_currency() : 'USD',
				'symbol'             => $woo ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '',
				'decimals'           => $woo ? wc_get_price_decimals() : 2,
				'decimal_separator'  => $woo ? wc_get_price_decimal_separator() : '.',
				'thousand_separator' => $woo ? wc_get_price_thousand_separator() : ',',
				'position'           => $woo ? get_option( 'woocommerce_currency_pos', 'left' ) : 'left',
			);
		}

		private static function plain_text( $value ) {
			return html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
		}

		/** Configured methods only; eligibility must be resolved for the actual order. */
		private function configured_gateways( $use_wc ) {
			$methods = array();
			if ( $use_wc ) {
				foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
					if ( 'yes' === $gateway->enabled ) {
						$methods[] = array( 'id' => $gateway->id, 'title' => self::plain_text( $gateway->get_title() ) );
					}
				}
			} elseif ( class_exists( 'WBTM_Payment_Gateway_Manager' ) ) {
				foreach ( WBTM_Payment_Gateway_Manager::instance()->get_available_gateways() as $gateway ) {
					$methods[] = array( 'id' => $gateway->get_id(), 'title' => self::plain_text( $gateway->get_title() ) );
				}
			}
			return $methods;
		}
	}
	new WBTM_App_Config();
}
