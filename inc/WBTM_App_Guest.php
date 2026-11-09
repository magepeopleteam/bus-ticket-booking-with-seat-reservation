<?php
/** Device-scoped guest principals. A WooCommerce guest is never a WordPress user. */
if ( ! defined( 'ABSPATH' ) ) { die; }

if ( ! class_exists( 'WBTM_App_Guest' ) ) {
	class WBTM_App_Guest {
		const PREFIX = 'wbtm_app_guest_';
		const ACCESS_TTL = 900;
		const REFRESH_TTL = 31536000;

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'routes' ) );
			add_action( 'wbtm_app_expire_guest', array( __CLASS__, 'expire' ) );
		}
		public function routes() {
			foreach ( array( 'session', 'refresh', 'logout' ) as $action ) {
				register_rest_route( WBTM_App_Config::REST_NAMESPACE, '/guest/' . $action, array( 'methods' => 'POST', 'callback' => array( $this, $action ), 'permission_callback' => '__return_true' ) );
			}
		}
		public static function digest( $value ) { return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ); }
		private static function unauthorized() { return WBTM_App_Auth::error( 'wbtm_app_unauthorized', __( 'Your booking session expired. Please reconnect.', 'addon-bus--ticket-booking-with-seat-pro' ), 401 ); }
		private static function parse( $token ) {
			return is_string( $token ) && preg_match( '/^g_([a-f0-9]{24})\.([a-f0-9]{64})$/D', $token, $parts ) ? $parts : false;
		}
		/** A lock wait can outlive another PHP request's revoke/rotation. Drop only this cached option. */
		public static function fresh_option( $key ) {
			wp_cache_delete( $key, 'options' );
			$missing = wp_cache_get( 'notoptions', 'options' ); if ( is_array( $missing ) && isset( $missing[ $key ] ) ) { unset( $missing[ $key ] ); wp_cache_set( 'notoptions', $missing, 'options' ); }
			return get_option( $key, array() );
		}
		public static function fresh_principal( WP_REST_Request $request ) {
			$authorization = (string) $request->get_header( 'authorization' );
			if ( preg_match( '/^Bearer (g_)?([a-f0-9]{24})\.[a-f0-9]{64}$/D', $authorization, $parts ) ) {
				$record = self::fresh_option( ( ! empty( $parts[1] ) ? self::PREFIX : WBTM_App_Auth::SESSION_PREFIX ) . $parts[2] );
				if ( empty( $parts[1] ) && ! empty( $record['user_id'] ) ) { clean_user_cache( (int) $record['user_id'] ); wp_cache_delete( (int) $record['user_id'], 'user_meta' ); }
			}
			return self::principal( $request );
		}
		/** Explicit authentication on every owned operation; request parameters cannot supply an owner. */
		public static function principal( WP_REST_Request $request ) {
			if ( ! WBTM_App_Auth::enabled() ) { return self::unauthorized(); }
			$authorization = (string) $request->get_header( 'authorization' );
			if ( 0 !== strpos( $authorization, 'Bearer g_' ) ) {
				$allowed = WBTM_App_Auth::require_user( $request );
				if ( is_wp_error( $allowed ) ) { return $allowed; }
				// Allow-header permission probes do not alter current_user.
				preg_match( '/^Bearer ([a-f0-9]{24})\./', $authorization, $matches );
				$session = get_option( WBTM_App_Auth::SESSION_PREFIX . ( $matches[1] ?? '' ), array() );
				$id = (int) ( $session['user_id'] ?? 0 );
				return array( 'kind' => 'customer', 'user_id' => $id, 'session' => $matches[1], 'owner' => self::digest( 'customer|' . $id ) );
			}
			$parts = self::parse( substr( $authorization, 7 ) );
			$session = $parts ? get_option( self::PREFIX . $parts[1], array() ) : array();
			if ( ! $parts || ! self::valid( $session ) || (int) ( $session['access_expires'] ?? 0 ) <= time() || ! hash_equals( $session['access_hash'], self::digest( $parts[2] ) ) ) { return self::unauthorized(); }
			if ( ! doing_filter( 'rest_post_dispatch' ) ) { wp_set_current_user( 0 ); }
			return array( 'kind' => 'guest', 'user_id' => 0, 'session' => $parts[1], 'owner' => self::digest( 'guest|' . $session['guest_id'] ) );
		}
		public static function require_principal( $request ) { $principal = self::principal( $request ); return is_wp_error( $principal ) ? $principal : true; }
		private static function valid( $session ) {
			return is_array( $session ) && preg_match( '/^[a-f0-9]{32}$/D', $session['guest_id'] ?? '' ) && preg_match( '/^[a-f0-9]{64}$/D', $session['access_hash'] ?? '' ) && preg_match( '/^[a-f0-9]{64}$/D', $session['refresh_hash'] ?? '' ) && (int) ( $session['refresh_expires'] ?? 0 ) > time();
		}
		public static function active( array $principal ) {
			if ( 'customer' === ( $principal['kind'] ?? '' ) ) { return WBTM_App_Auth::session_active( $principal['session'] ?? '', $principal['user_id'] ?? 0 ); }
			if ( ! preg_match( '/^[a-f0-9]{24}$/D', $principal['session'] ?? '' ) ) { return false; }
			$session = get_option( self::PREFIX . $principal['session'], array() );
			return self::valid( $session ) && hash_equals( self::digest( 'guest|' . $session['guest_id'] ), $principal['owner'] ?? '' );
		}
		private static function issue( $selector = '', $guest_id = '' ) {
			$selector = $selector ?: bin2hex( random_bytes( 12 ) ); $guest_id = $guest_id ?: bin2hex( random_bytes( 16 ) );
			$access = bin2hex( random_bytes( 32 ) ); $refresh = bin2hex( random_bytes( 32 ) ); $expires = time() + self::REFRESH_TTL;
			$record = array( 'guest_id' => $guest_id, 'access_hash' => self::digest( $access ), 'refresh_hash' => self::digest( $refresh ), 'access_expires' => time() + self::ACCESS_TTL, 'refresh_expires' => $expires );
			update_option( self::PREFIX . $selector, $record, false );
			wp_clear_scheduled_hook( 'wbtm_app_expire_guest', array( $selector ) ); wp_schedule_single_event( $expires, 'wbtm_app_expire_guest', array( $selector ) );
			return new WP_REST_Response( array( 'guest_id' => $guest_id, 'access_token' => 'g_' . $selector . '.' . $access, 'refresh_token' => 'g_' . $selector . '.' . $refresh, 'expires_at' => $record['access_expires'], 'refresh_expires_at' => $expires ), 201 );
		}
		public function session( $request ) {
			if ( strlen( (string) $request->get_body() ) > 1024 ) { return WBTM_App_Auth::error( 'wbtm_app_invalid_request', 'Request is too large.' ); }
			$rate = WBTM_App_Auth::rate_limit( 'guest_session', 20 ); if ( is_wp_error( $rate ) ) { return $rate; }
			$response = self::issue();
			// An optional, backwards-compatible bundle avoids a second WordPress boot
			// before the guest can enter the real WooCommerce checkout fields.
			$body = $request->get_json_params();
			if ( is_array( $body['checkout'] ?? null ) && class_exists( 'WBTM_App_Native_Checkout' ) ) {
				$data = $response->get_data(); $schema_request = new WP_REST_Request( 'GET' );
				$schema_request->set_header( 'authorization', 'Bearer ' . $data['access_token'] );
				foreach ( array( 'bus_ids', 'country', 'state' ) as $key ) { if ( is_string( $body['checkout'][ $key ] ?? null ) ) { $schema_request->set_param( $key, $body['checkout'][ $key ] ); } }
				$p = self::principal( $schema_request );
				$schema = is_wp_error( $p ) ? $p : WBTM_App_Native_Checkout::schema_for_principal( $p, $schema_request );
				if ( ! is_wp_error( $schema ) ) { $data['checkout_schema'] = rest_ensure_response( $schema )->get_data(); $response->set_data( $data ); }
			}
			return $response;
		}
		public function refresh( $request ) {
			if ( strlen( (string) $request->get_body() ) > 1024 ) { return self::unauthorized(); }
			$rate = WBTM_App_Auth::rate_limit( 'guest_refresh', 120 ); if ( is_wp_error( $rate ) ) { return $rate; }
			$parts = self::parse( WBTM_App_Auth::string_param( $request, 'refresh_token', 100 ) ); if ( ! $parts ) { return self::unauthorized(); }
			return WBTM_App_Auth::locked( 'guest_' . $parts[1], static function() use ( $parts ) {
				$session = self::fresh_option( self::PREFIX . $parts[1] );
				if ( ! self::valid( $session ) || ! hash_equals( $session['refresh_hash'], self::digest( $parts[2] ) ) ) { return self::unauthorized(); }
				return self::issue( $parts[1], $session['guest_id'] );
			} );
		}
		public function logout( $request ) {
			$rate = WBTM_App_Auth::rate_limit( 'guest_logout', 60 ); if ( is_wp_error( $rate ) ) { return $rate; }
			if ( strlen( (string) $request->get_body() ) > 1024 ) { return self::unauthorized(); }
			$refresh = self::parse( WBTM_App_Auth::string_param( $request, 'refresh_token', 100 ) );
			if ( $refresh ) {
				// This proof is accepted ONLY for revocation, never booking authentication.
				return WBTM_App_Auth::locked( 'guest_' . $refresh[1], static function() use ( $refresh ) {
					$session = self::fresh_option( self::PREFIX . $refresh[1] );
					if ( ! self::valid( $session ) || ! hash_equals( $session['refresh_hash'], self::digest( $refresh[2] ) ) ) { return self::unauthorized(); }
					delete_option( self::PREFIX . $refresh[1] ); wp_clear_scheduled_hook( 'wbtm_app_expire_guest', array( $refresh[1] ) ); return new WP_REST_Response( array( 'revoked' => true ) );
				} );
			}
			$principal = self::principal( $request ); if ( is_wp_error( $principal ) ) { return $principal; }
			if ( 'guest' !== $principal['kind'] ) { return WBTM_App_Auth::error( 'wbtm_app_invalid_request', 'Use account logout for a customer session.' ); }
			return WBTM_App_Auth::locked( 'guest_' . $principal['session'], static function() use ( $principal, $request ) { $current = self::fresh_principal( $request ); if ( is_wp_error( $current ) ) { return $current; } delete_option( self::PREFIX . $principal['session'] ); wp_clear_scheduled_hook( 'wbtm_app_expire_guest', array( $principal['session'] ) ); return new WP_REST_Response( array( 'revoked' => true ) ); } );
		}
		public static function expire( $selector ) {
			if ( ! preg_match( '/^[a-f0-9]{24}$/D', (string) $selector ) ) { return; }
			return WBTM_App_Auth::locked( 'guest_' . $selector, static function() use ( $selector ) { $record = self::fresh_option( self::PREFIX . $selector ); if ( (int) ( $record['refresh_expires'] ?? 0 ) <= time() ) { delete_option( self::PREFIX . $selector ); } } );
		}
	}
	new WBTM_App_Guest();
}
