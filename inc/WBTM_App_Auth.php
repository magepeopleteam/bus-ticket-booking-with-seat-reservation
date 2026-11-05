<?php
/**
 * Revocable, device-scoped customer sessions for the native app.
 *
 * Opaque credentials are returned once. Only keyed hashes are stored, and refresh
 * rotation is serialized with a database advisory lock. No WordPress login cookie
 * is issued and no customer bearer credential authenticates wp/v2 or admin routes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Auth' ) ) {
	class WBTM_App_Auth {
		const SESSION_PREFIX = 'wbtm_app_session_';
		const SESSION_META   = '_wbtm_app_session';
		const ACCESS_TTL     = 900;
		const REFRESH_TTL    = 2592000;
		private static $current_selector = '';
		private $request_context = array();

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_filter( 'rest_pre_dispatch', array( $this, 'guard_api' ), 5, 3 );
			add_filter( 'rest_post_dispatch', array( $this, 'private_response' ), 10, 3 );
			add_filter( 'rest_request_before_callbacks', array( $this, 'begin_request' ), 5, 3 );
			add_filter( 'rest_request_after_callbacks', array( $this, 'end_request' ), 100, 3 );
			add_action( 'wbtm_app_expire_session', array( __CLASS__, 'expire_session' ) );
			add_action( 'delete_user', array( __CLASS__, 'revoke_all' ) );
			add_action( 'after_password_reset', array( $this, 'password_reset' ) );
		}

		public function register_routes() {
			foreach ( array( 'register', 'login', 'refresh', 'forgot', 'logout' ) as $action ) {
				register_rest_route( WBTM_App_Config::REST_NAMESPACE, '/auth/' . $action, array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action . '_customer' ),
					'permission_callback' => 'logout' === $action ? array( __CLASS__, 'require_user' ) : '__return_true',
				) );
			}
		}

		public static function enabled() {
			$settings = WBTM_App_Settings::get();
			return 'yes' === $settings['app_enabled'];
		}

		public function guard_api( $result, $server, $request ) {
			if ( 0 !== strpos( $request->get_route(), '/' . WBTM_App_Config::REST_NAMESPACE . '/' ) ) {
				return $result;
			}
			// The independently enabled operator build connector owns its HTTPS/hash gate.
			if ( class_exists( 'WBTM_App_Builder_Connection' ) && WBTM_App_Builder_Connection::is_operator_builder_request( $request ) ) {
				return $result;
			}
			if ( ! self::enabled() ) {
				return self::error( 'wbtm_app_disabled', __( 'The mobile app API is disabled.', 'addon-bus--ticket-booking-with-seat-pro' ), 404 );
			}
			$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$is_local  = in_array( $site_host, array( 'localhost', '127.0.0.1', '::1' ), true ) || in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
			if ( ! is_ssl() && ! $is_local ) {
				return self::error( 'wbtm_app_https_required', __( 'HTTPS is required for the mobile app API.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			return $result;
		}

		public function private_response( $response, $server, $request ) {
			if ( 0 === strpos( $request->get_route(), '/' . WBTM_App_Config::REST_NAMESPACE . '/' ) ) {
				$response = rest_ensure_response( $response );
				$response->header( 'Cache-Control', 'no-store, private' );
			}
			return $response;
		}

		/** Scope the temporary current-user identity to this callback, even in REST batches. */
		public function begin_request( $response, $handler, $request ) {
			if ( 0 === strpos( $request->get_route(), '/' . WBTM_App_Config::REST_NAMESPACE . '/' ) ) {
				$this->request_context[ spl_object_id( $request ) ] = array( get_current_user_id(), self::$current_selector );
				wp_set_current_user( 0 );
				self::$current_selector = '';
			}
			return $response;
		}

		public function end_request( $response, $handler, $request ) {
			$key = spl_object_id( $request );
			if ( isset( $this->request_context[ $key ] ) ) {
				list( $user_id, $selector ) = $this->request_context[ $key ];
				wp_set_current_user( $user_id );
				self::$current_selector = $selector;
				unset( $this->request_context[ $key ] );
			}
			return $response;
		}

		public static function error( $code, $message, $status = 400 ) {
			return new WP_Error( $code, $message, array( 'status' => $status ) );
		}

		public static function string_param( $request, $key, $maximum = 300 ) {
			$value = $request->get_param( $key );
			return is_string( $value ) && strlen( $value ) <= $maximum ? $value : '';
		}

		public static function user_data( $user ) {
			return array( 'id' => (int) $user->ID, 'name' => $user->display_name, 'email' => $user->user_email );
		}

		/** Deliberately ignore ambient cookie/application-password authentication. */
		public static function require_user( $request ) {
			// WordPress re-runs permissions to build Allow headers after callbacks.
			// That later probe must not re-authenticate the surrounding request.
			$probe = doing_filter( 'rest_post_dispatch' );
			if ( ! $probe ) {
				self::$current_selector = '';
				wp_set_current_user( 0 );
			}
			if ( ! self::enabled() ) {
				return self::error( 'wbtm_app_disabled', __( 'The mobile app API is disabled.', 'addon-bus--ticket-booking-with-seat-pro' ), 404 );
			}
			$authorization = (string) $request->get_header( 'authorization' );
			if ( ! preg_match( '/^Bearer ([a-f0-9]{24})\.([a-f0-9]{64})$/D', $authorization, $matches ) ) {
				return self::unauthorized();
			}
			$session = get_option( self::SESSION_PREFIX . $matches[1], array() );
			$user    = ! empty( $session['user_id'] ) ? get_user_by( 'id', $session['user_id'] ) : false;
			if ( ! $user || ! self::customer_allowed( $user ) || ! self::valid_session( $session, $user ) || empty( $session['access_expires'] ) || $session['access_expires'] <= time() || ! hash_equals( $session['access_hash'], self::hash_secret( $matches[2] ) ) ) {
				return self::unauthorized();
			}
			if ( ! $probe ) {
				self::$current_selector = $matches[1];
				wp_set_current_user( $user->ID );
			}
			return true;
		}

		private static function valid_session( $session, $user ) {
			foreach ( array( 'password_hash', 'access_hash', 'refresh_hash' ) as $key ) {
				if ( ! is_array( $session ) || ! isset( $session[ $key ] ) || ! is_string( $session[ $key ] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $session[ $key ] ) ) {
					return false;
				}
			}
			return hash_equals( $session['password_hash'], self::hash_secret( $user->user_pass ) ) && ! get_user_meta( $user->ID, '_wbtm_app_deletion_request', true );
		}

		private static function unauthorized() {
			return self::error( 'wbtm_app_unauthorized', __( 'Please sign in again.', 'addon-bus--ticket-booking-with-seat-pro' ), 401 );
		}

		private static function hash_secret( $secret ) {
			return hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) );
		}

		public static function current_session() {
			return self::$current_selector;
		}

		/** Background devices follow the refresh-session lifetime, not the 15-minute access token. */
		public static function session_active( $selector, $user_id ) {
			if ( ! is_string( $selector ) || ! preg_match( '/^[a-f0-9]{24}$/D', $selector ) ) {
				return false;
			}
			$session = get_option( self::SESSION_PREFIX . $selector, array() );
			$user    = get_user_by( 'id', $user_id );
			return is_array( $session ) && $user && (int) ( $session['user_id'] ?? 0 ) === (int) $user_id && self::customer_allowed( $user ) && self::valid_session( $session, $user ) && ! empty( $session['refresh_expires'] ) && $session['refresh_expires'] > time();
		}

		/** Fail closed rather than rotate/revoke credentials concurrently. */
		public static function locked( $name, $callback ) {
			global $wpdb;
			$key = 'wbtm_app_' . substr( hash( 'sha256', $wpdb->prefix . $name ), 0, 48 );
			if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2)', $key ) ) ) {
				return self::error( 'wbtm_app_busy', __( 'The service is busy. Please try again.', 'addon-bus--ticket-booking-with-seat-pro' ), 503 );
			}
			try {
				return call_user_func( $callback );
			} finally {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
			}
		}

		/** Actor-scoped writes and IP-scoped public limits; forwarded IPs are never trusted. */
		public static function rate_limit( $action, $maximum = 10, $identity = '' ) {
			$address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli';
			$actor   = '' !== $identity ? 'owner:' . $identity : 'ip:' . $address;
			$key     = 'wbtm_app_rate_' . self::hash_secret( $action . '|' . $actor );
			return self::locked( $key, function () use ( $key, $maximum ) {
				$state = get_transient( $key );
				$state = is_array( $state ) && ! empty( $state['until'] ) && $state['until'] > time() ? $state : array( 'count' => 0, 'until' => time() + 15 * MINUTE_IN_SECONDS );
				if ( $state['count'] >= $maximum ) {
					return self::error( 'wbtm_app_rate_limited', __( 'Too many attempts. Please try again later.', 'addon-bus--ticket-booking-with-seat-pro' ), 429 );
				}
				++$state['count'];
				set_transient( $key, $state, max( 1, $state['until'] - time() ) );
				return true;
			} );
		}

		public function login_customer( $request ) {
			$rate = self::rate_limit( 'login', 20 );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$email    = sanitize_email( self::string_param( $request, 'email' ) );
			$password = self::string_param( $request, 'password', 4096 );
			$user     = wp_authenticate( $email, $password );
			if ( is_wp_error( $user ) || ! self::customer_allowed( $user ) ) {
				return self::error( 'wbtm_app_login_failed', __( 'The email or password is incorrect, or this account cannot use customer sign-in.', 'addon-bus--ticket-booking-with-seat-pro' ), 401 );
			}
			return self::issue_session( $user );
		}

		/** App credentials never grant administrator, editor or staff privileges. */
		public static function customer_allowed( $user ) {
			return $user instanceof WP_User && ! array_intersect( $user->roles, array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' ) ) && ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'wbtm_staff_access' ) && ! get_user_meta( $user->ID, '_wbtm_app_deletion_request', true );
		}

		public function register_customer( $request ) {
			$rate = self::rate_limit( 'register', 5 );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$settings = WBTM_App_Settings::get();
			if ( 'yes' !== $settings['app_registration_enabled'] || ( class_exists( 'WBTM_App_Native_Checkout' ) && ! WBTM_App_Native_Checkout::policy()['registration_enabled'] ) ) {
				return self::error( 'wbtm_app_registration_disabled', __( 'Account registration is disabled.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			$email    = sanitize_email( self::string_param( $request, 'email' ) );
			$name     = sanitize_text_field( self::string_param( $request, 'name', 200 ) );
			$password = self::string_param( $request, 'password', 4096 );
			if ( ! is_email( $email ) || '' === $name || strlen( $password ) < 10 ) {
				return self::error( 'wbtm_app_invalid_account', __( 'Enter a valid email, name and a password with at least 10 characters.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			}
			$result = self::locked( 'register_' . strtolower( $email ), function () use ( $email, $name, $password ) {
				if ( email_exists( $email ) ) {
					return self::error( 'wbtm_app_registration_failed', __( 'Unable to create this account. Try signing in or resetting your password.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
				}
				return wp_insert_user( array(
					'user_login' => 'bus_' . bin2hex( random_bytes( 12 ) ), 'user_email' => $email,
					'user_pass' => $password, 'display_name' => $name, 'nickname' => $name,
					'role' => get_role( 'customer' ) ? 'customer' : 'subscriber',
				) );
			} );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return self::issue_session( get_user_by( 'id', $result ) );
		}

		private static function issue_session( $user, $selector = '' ) {
			$selector = $selector ?: bin2hex( random_bytes( 12 ) );
			$access   = bin2hex( random_bytes( 32 ) );
			$refresh  = bin2hex( random_bytes( 32 ) );
			$expires  = time() + self::REFRESH_TTL;
			$session  = array(
				'user_id' => (int) $user->ID, 'password_hash' => self::hash_secret( $user->user_pass ),
				'access_hash' => self::hash_secret( $access ), 'refresh_hash' => self::hash_secret( $refresh ),
				'access_expires' => time() + self::ACCESS_TTL, 'refresh_expires' => $expires,
			);
			update_option( self::SESSION_PREFIX . $selector, $session, false );
			add_user_meta( $user->ID, self::SESSION_META, $selector, false );
			$sessions = array_values( array_unique( get_user_meta( $user->ID, self::SESSION_META ) ) );
			while ( count( $sessions ) > 10 ) {
				self::revoke_session( array_shift( $sessions ) );
			}
			wp_clear_scheduled_hook( 'wbtm_app_expire_session', array( $selector ) );
			wp_schedule_single_event( $expires + MINUTE_IN_SECONDS, 'wbtm_app_expire_session', array( $selector ) );
			return new WP_REST_Response( array(
				'access_token' => $selector . '.' . $access, 'refresh_token' => $selector . '.' . $refresh,
				'expires_in' => self::ACCESS_TTL, 'user' => self::user_data( $user ),
			) );
		}

		public function refresh_customer( $request ) {
			$rate = self::rate_limit( 'refresh', 120 );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$token = self::string_param( $request, 'refresh_token', 90 );
			if ( ! preg_match( '/^([a-f0-9]{24})\.([a-f0-9]{64})$/D', $token, $matches ) ) {
				return self::unauthorized();
			}
			return self::locked( 'session_' . $matches[1], function () use ( $matches ) {
				$session = get_option( self::SESSION_PREFIX . $matches[1], array() );
				$user    = ! empty( $session['user_id'] ) ? get_user_by( 'id', $session['user_id'] ) : false;
				if ( ! $user || ! self::customer_allowed( $user ) || ! self::valid_session( $session, $user ) || empty( $session['refresh_expires'] ) || $session['refresh_expires'] <= time() || ! hash_equals( $session['refresh_hash'], self::hash_secret( $matches[2] ) ) ) {
					return self::unauthorized();
				}
				delete_user_meta( $user->ID, self::SESSION_META, $matches[1] );
				return self::issue_session( $user, $matches[1] );
			} );
		}

		public function forgot_customer( $request ) {
			$rate = self::rate_limit( 'forgot', 5 );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$email = sanitize_email( self::string_param( $request, 'email' ) );
			if ( is_email( $email ) ) {
				// WordPress generates the one-time reset link and sends it server-side.
				// https://developer.wordpress.org/reference/functions/retrieve_password/
				retrieve_password( $email );
			}
			return array( 'message' => __( 'If this account exists, a password reset email will be sent.', 'addon-bus--ticket-booking-with-seat-pro' ) );
		}

		public function logout_customer() {
			if ( self::$current_selector ) {
				$result = self::revoke_session( self::$current_selector );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			wp_set_current_user( 0 );
			return array( 'logged_out' => true );
		}

		public static function revoke_session( $selector ) {
			if ( ! is_string( $selector ) || ! preg_match( '/^[a-f0-9]{24}$/D', $selector ) ) {
				return;
			}
			return self::locked( 'session_' . $selector, function () use ( $selector ) {
				$session = get_option( self::SESSION_PREFIX . $selector, array() );
				delete_option( self::SESSION_PREFIX . $selector );
				wp_clear_scheduled_hook( 'wbtm_app_expire_session', array( $selector ) );
				if ( ! empty( $session['user_id'] ) ) {
					delete_user_meta( $session['user_id'], self::SESSION_META, $selector );
					if ( class_exists( 'WBTM_App_Account' ) ) {
						WBTM_App_Account::remove_session_devices( $session['user_id'], $selector );
					}
				}
				return true;
			} );
		}

		public static function revoke_all( $user_id ) {
			foreach ( get_user_meta( $user_id, self::SESSION_META ) as $selector ) {
				self::revoke_session( $selector );
			}
			delete_user_meta( $user_id, self::SESSION_META );
		}

		public static function expire_session( $selector ) {
			$session = get_option( self::SESSION_PREFIX . $selector, array() );
			if ( empty( $session['refresh_expires'] ) || $session['refresh_expires'] <= time() ) {
				self::revoke_session( $selector );
			}
		}

		public function password_reset( $user ) {
			self::revoke_all( $user->ID );
		}
	}
	new WBTM_App_Auth();
}
