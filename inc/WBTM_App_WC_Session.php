<?php
/** WooCommerce session restricted to the server-owned native checkout intent. */
if ( ! defined( 'ABSPATH' ) ) { die; }

if ( ! class_exists( 'WBTM_App_WC_Session' ) ) {
	class WBTM_App_WC_Session extends WC_Session_Handler {
		public function generate_customer_id() {
			return WBTM_App_Checkout::wc_customer_id();
		}

		public function init_session_cookie() {
			$cookie = $this->get_session_cookie();
			if ( $cookie && ! hash_equals( WBTM_App_Checkout::wc_customer_id(), (string) $cookie[0] ) ) {
				wp_die( 'This cart session belongs to another checkout.', '', array( 'response' => 403 ) );
			}
			parent::init_session_cookie();
		}

		public function init() {
			parent::init();
			if ( ! hash_equals( WBTM_App_Checkout::wc_customer_id(), (string) $this->get_customer_id() ) ) {
				wp_die( 'This cart session belongs to another checkout.', '', array( 'response' => 403 ) );
			}
		}
	}
}
