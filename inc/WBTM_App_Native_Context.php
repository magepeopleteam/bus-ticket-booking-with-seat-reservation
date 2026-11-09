<?php
/** Request-only WooCommerce session. It never reads, writes or emits browser cookies. */
if ( ! defined( 'ABSPATH' ) ) { die; }

class WBTM_App_Memory_Session extends WC_Session {
	public function __construct( $identity ) { $this->_customer_id = $identity; }
	public function has_session() { return true; }
	public function set_customer_session_cookie( $set ) {}
	public function save_data( $old_session_key = 0 ) {}
	public function destroy_session() { $this->_data = array(); }
}

/** Checkout updates addresses for tax calculations but must not persist a profile or create a user. */
class WBTM_App_Memory_Customer extends WC_Customer {
	public function save() { return $this->get_id(); }
}

/** Exposes WooCommerce's own protected validation, rather than copying country/field rules. */
class WBTM_App_Native_WC_Checkout extends WC_Checkout {
	public function prepare_customer( array $data ) { $this->update_session( $data ); }
	public function validate_native( array &$data ) {
		$errors = new WP_Error(); $this->validate_checkout( $data, $errors );
		foreach ( wc_get_notices( 'error' ) as $notice ) { $errors->add( 'wbtm_app_checkout_validation', wp_strip_all_tags( $notice['notice'] ) ); }
		return $errors;
	}
}
class WBTM_App_Native_Context {
	/**
	 * Detach browser-cart bound callbacks as well as globals. Restored even on
	 * exceptions, so nested REST requests and an existing website cart are isolated.
	 */
	public static function run( array $principal, array $intent, $callback ) {
		global $wp_filter;
		$wc = WC(); $saved = array( $wc->session, $wc->customer, $wc->cart, $_POST, $_COOKIE, get_current_user_id(), $wc->shipping()->packages );
		$detached = array(); $isolated_cart = null;
		$disable_session = static function() { return false; }; $disable_persistence = static function() { return false; };
		$checkout_value = static function( $value, $key ) { if ( in_array( $key, array( 'billing_country', 'billing_state' ), true ) ) { $getter = 'get_' . $key; return WC()->customer->$getter(); } return $value; };
		try {
			foreach ( $wp_filter as $name => $hook ) {
				foreach ( $hook->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $entry ) {
						$fn = $entry['function'];
						if ( is_array( $fn ) && is_object( $fn[0] ) && ( $fn[0] instanceof WC_Cart || $fn[0] instanceof WC_Cart_Session ) ) { $detached[] = array( $name, $fn, $priority, $entry['accepted_args'] ); remove_filter( $name, $fn, $priority ); }
					}
				}
			}
			wp_set_current_user( $principal['user_id'] );
			$wc->session = new WBTM_App_Memory_Session( 't_' . substr( $intent['checkout_id'], 0, 30 ) );
			$wc->customer = new WBTM_App_Memory_Customer( $principal['user_id'], false );
			add_filter( 'woocommerce_cart_session_initialize', $disable_session, PHP_INT_MAX );
			add_filter( 'woocommerce_persistent_cart_enabled', $disable_persistence, PHP_INT_MAX );
			add_filter( 'woocommerce_checkout_get_value', $checkout_value, PHP_INT_MAX, 2 );
			$isolated_cart = new WC_Cart(); $wc->cart = $isolated_cart;
			$wc->shipping()->packages = array();
			$wc->session->set( 'chosen_payment_method', $intent['payment_method'] ?? '' );
			if ( class_exists( 'WBTM_Deposit' ) ) { $wc->session->set( WBTM_Deposit::SESSION_KEY, $intent['deposit_choice'] ?? 'full' ); }
			$_COOKIE[ WBTM_Seat_Hold::COOKIE_NAME ] = $intent['hold_token'];
			$_POST = array();
			return call_user_func( $callback );
		} finally {
			list( $wc->session, $wc->customer, $wc->cart, $_POST, $_COOKIE, $user_id, $packages ) = $saved;
			$wc->shipping()->packages = $packages;
			wp_set_current_user( $user_id );
			// Remove only this request's cart callbacks. Preserve unrelated hooks
			// installed by payment/checkout extensions during this operation.
			foreach ( $wp_filter as $name => $hook ) { foreach ( $hook->callbacks as $priority => $callbacks ) { foreach ( $callbacks as $entry ) { $fn = $entry['function']; if ( is_array( $fn ) && $fn[0] === $isolated_cart ) { remove_filter( $name, $fn, $priority ); } } } }
			remove_filter( 'woocommerce_cart_session_initialize', $disable_session, PHP_INT_MAX ); remove_filter( 'woocommerce_persistent_cart_enabled', $disable_persistence, PHP_INT_MAX );
			remove_filter( 'woocommerce_checkout_get_value', $checkout_value, PHP_INT_MAX );
			foreach ( $detached as $entry ) { add_filter( $entry[0], $entry[1], $entry[2], $entry[3] ); }
		}
	}
}
