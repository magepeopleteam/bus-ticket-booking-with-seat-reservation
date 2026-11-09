<?php
/**
 * Public mobile-app branding, configured through the existing global settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

require_once __DIR__ . '/WBTM_App_Home.php';
require_once __DIR__ . '/WBTM_App_Promotion.php';

if ( ! class_exists( 'WBTM_App_Settings' ) ) {
	class WBTM_App_Settings {
		const OPTION = 'wbtm_app_settings';

		public function __construct() {
			add_filter( 'wbtm_settings_sec_reg', array( $this, 'register_section' ), 13 );
			add_filter( 'wbtm_settings_sec_fields', array( $this, 'register_fields' ), 13 );
			add_filter( 'sanitize_option_' . self::OPTION, array( __CLASS__, 'sanitize' ) );
		}

		public static function defaults() {
			return array(
				'app_enabled'             => 'no',
				'app_registration_enabled' => 'yes',
				'app_push_enabled'        => 'no',
				'app_expo_project_id'     => '',
				'app_payment_timeout_minutes' => '15',
				'app_name'                => '',
				'app_logo'                => '',
				'app_primary_color'       => '#2563eb',
				'app_background_color'    => '#ffffff',
				'app_support_email'       => '',
				'app_support_phone'       => '',
				'app_support_url'         => '',
				'app_terms_url'           => '',
				'app_privacy_url'         => '',
				'app_account_deletion_url' => '',
				'app_ios_store_url'       => '',
				'app_android_store_url'   => '',
				'app_promo_enabled'       => 'no',
				'app_android_apk_url'     => '',
				'app_promo_title'         => '',
				'app_promo_description'   => '',
				'app_promo_button_label'  => '',
				'app_min_ios_version'     => '0.0.0',
				'app_min_android_version' => '0.0.0',
				'app_maintenance'         => 'no',
				'app_maintenance_message' => '',
			);
		}

		/** Sanitize both settings-form saves and programmatic option writes. */
		public static function sanitize( $input ) {
			$input  = is_array( $input ) ? $input : array();
			$output = self::defaults();
			foreach ( $output as $key => $default ) {
				if ( 'app_payment_timeout_minutes' === $key && isset( $input[ $key ] ) && is_int( $input[ $key ] ) ) {
					$input[ $key ] = (string) $input[ $key ];
				}
				if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
					continue;
				}
				$value = $input[ $key ];
				if ( in_array( $key, array( 'app_enabled', 'app_maintenance', 'app_registration_enabled', 'app_push_enabled', 'app_promo_enabled' ), true ) ) {
					$output[ $key ] = 'yes' === $value ? 'yes' : 'no';
				} elseif ( false !== strpos( $key, '_color' ) ) {
					$output[ $key ] = sanitize_hex_color( $value ) ?: $default;
				} elseif ( 'app_android_apk_url' === $key ) {
					$output[ $key ] = WBTM_App_Promotion::apk_url( $value );
				} elseif ( in_array( $key, array( 'app_promo_title', 'app_promo_description', 'app_promo_button_label' ), true ) ) {
					$limits         = array(
						'app_promo_title'        => 180,
						'app_promo_description'  => 600,
						'app_promo_button_label' => 60,
					);
					$text           = 'app_promo_description' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
					$output[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limits[ $key ] ) : substr( $text, 0, $limits[ $key ] );
				} elseif ( 'app_logo' === $key || str_ends_with( $key, '_url' ) ) {
					$url   = esc_url_raw( $value, array( 'https', 'http' ) );
					$parts = wp_parse_url( $url );
					$output[ $key ] = is_array( $parts ) && ! empty( $parts['host'] ) && empty( $parts['user'] ) && empty( $parts['pass'] ) ? $url : '';
				} elseif ( 'app_payment_timeout_minutes' === $key ) {
					$output[ $key ] = ctype_digit( $value ) ? (string) max( 5, min( 120, (int) $value ) ) : $default;
				} elseif ( 'app_expo_project_id' === $key ) {
					$output[ $key ] = preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $value ) ? strtolower( $value ) : '';
				} elseif ( 'app_support_email' === $key ) {
					$output[ $key ] = sanitize_email( $value );
				} elseif ( false !== strpos( $key, '_version' ) ) {
					$output[ $key ] = preg_match( '/^\d{1,5}\.\d{1,5}\.\d{1,5}$/D', $value ) ? $value : $default;
				} elseif ( 'app_maintenance_message' === $key ) {
					$output[ $key ] = sanitize_textarea_field( $value );
				} else {
					$output[ $key ] = sanitize_text_field( $value );
				}
			}
			return $output;
		}

		public static function get() {
			return self::sanitize( get_option( self::OPTION, array() ) );
		}

		public function register_section( $sections ) {
			$sections[] = array(
				'id'    => self::OPTION,
				'title' => esc_html__( 'Mobile App', 'addon-bus--ticket-booking-with-seat-pro' ),
			);
			return $sections;
		}

		public function register_fields( $fields ) {
			$yes_no = array(
				'no'  => esc_html__( 'No', 'addon-bus--ticket-booking-with-seat-pro' ),
				'yes' => esc_html__( 'Yes', 'addon-bus--ticket-booking-with-seat-pro' ),
			);
			$definitions = array(
				'app_enabled' => array(
					'label'   => esc_html__( 'Enable Mobile App API', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'    => esc_html__( 'Enables customer app endpoints and public branding below. The app reads supported operations from this configuration. Existing website APIs are unchanged.', 'addon-bus--ticket-booking-with-seat-pro' ) . ' ' . esc_url( rest_url( 'wbtm-app/v1/config' ) ),
					'type'    => 'select',
					'options' => $yes_no,
				),
				'app_registration_enabled' => array(
					'label'   => esc_html__( 'Allow App Account Registration', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'    => esc_html__( 'Customers can create a customer account in the app. Existing customers can still sign in when this is disabled.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'    => 'select',
					'options' => $yes_no,
				),
				'app_payment_timeout_minutes' => array(
					'label' => esc_html__( 'App Pending Payment Timeout', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Minutes before an unpaid app booking expires and releases its inventory. Applies to app orders only. Allowed range: 5–120 minutes; default 15. Offline bookings awaiting manual payment follow their existing payment workflow.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'number', 'min' => 5, 'max' => 120, 'step' => 1,
				),
				'app_push_enabled' => array(
					'label'   => esc_html__( 'Enable App Push Delivery', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'    => esc_html__( 'Sends generic booking-status notifications through Expo. Requires the project ID below, configured Android/iOS push credentials, device consent, and working WordPress cron. Optional Expo access token belongs in server configuration (WBTM_EXPO_ACCESS_TOKEN), never here.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'    => 'select',
					'options' => $yes_no,
				),
				'app_expo_project_id' => array(
					'label' => esc_html__( 'Expo Project ID', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Public UUID from the customer app Expo project. Must match the app build. Push credentials remain in the customer Expo project.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_name' => array(
					'label' => esc_html__( 'App Display Name', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Name inside the app; defaults to the site title. The installed app name, icon, splash screen and bundle IDs must be set in each customer build.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_home_editor' => array(
					'label' => esc_html__( 'App Home Content', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Open Bus > Mobile App Home to manage hero slides, home content, featured routes and benefits. Saved content is loaded by the app without rebuilding it.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'html',
				),
				'app_logo' => array(
					'label' => esc_html__( 'App Logo', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Public image URL for the app header. Defaults to the WordPress custom logo. This does not replace the installed app icon.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'file',
				),
				'app_primary_color' => array(
					'label' => esc_html__( 'App Primary Color', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'color',
				),
				'app_background_color' => array(
					'label' => esc_html__( 'App Background Color', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'color',
				),
				'app_support_email' => array(
					'label' => esc_html__( 'Public Support Email', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'email',
				),
				'app_support_phone' => array(
					'label' => esc_html__( 'Public Support Phone', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_support_url' => array(
					'label' => esc_html__( 'Support URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_terms_url' => array(
					'label' => esc_html__( 'App Terms URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_privacy_url' => array(
					'label' => esc_html__( 'App Privacy URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Defaults to the WordPress privacy policy page. All settings in this section are public; never enter credentials or private keys.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_account_deletion_url' => array(
					'label' => esc_html__( 'Account Deletion Information URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Public instructions for requesting account deletion. An in-app deletion flow must also be implemented before releasing an app with account creation.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_ios_store_url' => array(
					'label' => esc_html__( 'iOS App Store URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_android_store_url' => array(
					'label' => esc_html__( 'Google Play URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_promo_enabled' => array(
					'label'   => esc_html__( 'Show App Download on Bus Search Pages', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'    => esc_html__( 'Adds a download card above the main bus search shortcode. Uses the app name, logo and primary color above. Requires the HTTPS Android APK URL below. You can also place [wbtm_app_download] in page content. Disabled by default.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'    => 'select',
					'options' => $yes_no,
				),
				'app_android_apk_url' => array(
					'label' => esc_html__( 'Android APK Download URL', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Public HTTPS link to the Android installation file. This is a direct APK download, separate from Google Play. Removing the URL hides the card.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'url',
				),
				'app_promo_title' => array(
					'label' => esc_html__( 'App Download Heading', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Optional heading, up to 180 characters. Defaults to the app display name.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_promo_description' => array(
					'label' => esc_html__( 'App Download Description', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Optional plain text, up to 600 characters. Explain the app or provide installation instructions. Changes appear on the website after saving.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'textarea',
				),
				'app_promo_button_label' => array(
					'label' => esc_html__( 'App Download Button Label', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Optional label, up to 60 characters. Defaults to Download for Android.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_min_ios_version' => array(
					'label' => esc_html__( 'Minimum iOS App Version', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Three numbers, for example 1.2.0. Use 0.0.0 to impose no minimum. This is the app version, not the iOS version.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_min_android_version' => array(
					'label' => esc_html__( 'Minimum Android App Version', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'  => esc_html__( 'Three numbers, for example 1.2.0. Use 0.0.0 to impose no minimum. This is the app version, not the Android version.', 'addon-bus--ticket-booking-with-seat-pro' ),
				),
				'app_maintenance' => array(
					'label'   => esc_html__( 'App Maintenance Notice', 'addon-bus--ticket-booking-with-seat-pro' ),
					'desc'    => esc_html__( 'Asks the app to show the message below. This does not suspend website booking or disable existing public search APIs.', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'    => 'select',
					'options' => $yes_no,
				),
				'app_maintenance_message' => array(
					'label' => esc_html__( 'App Maintenance Message', 'addon-bus--ticket-booking-with-seat-pro' ),
					'type'  => 'textarea',
				),
			);
			$defaults = self::defaults();
			foreach ( $definitions as $name => $definition ) {
				$fields[ self::OPTION ][] = array_merge(
					array( 'name' => $name, 'type' => 'text', 'default' => $defaults[ $name ] ?? '' ),
					$definition
				);
			}
			return $fields;
		}
	}
	new WBTM_App_Settings();
}
