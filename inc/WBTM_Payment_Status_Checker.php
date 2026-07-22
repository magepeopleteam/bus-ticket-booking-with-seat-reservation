<?php
/**
 * Determines whether the booking system currently has at least one usable
 * payment method, across WooCommerce gateways and the optional Pro plugin's
 * custom gateways.
 *
 * Deliberately free of any WordPress hook registration so it can be
 * instantiated and unit tested in isolation. WBTM_Admin_Payment_Notice is the
 * only caller that wires it into `admin_notices`.
 *
 * Ported from the sibling rental plugin's RBFW_Payment_Status_Checker.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WBTM_Payment_Status_Checker' ) ) {

	class WBTM_Payment_Status_Checker {

		/**
		 * Enabled WooCommerce payment gateways.
		 *
		 * Empty when WooCommerce is not active, when the resolved booking mode isn't
		 * WooCommerce (it no longer owns checkout, so its gateways are not a usable
		 * path), or when active with no gateway enabled.
		 *
		 * @return WC_Payment_Gateway[]
		 */
		public function get_enabled_woocommerce_gateways() {
			if ( ! $this->has_woocommerce() || ! $this->wc_payment_enabled() ) {
				return array();
			}

			if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
				return array();
			}

			return WC()->payment_gateways()->get_available_payment_gateways();
		}

		/**
		 * Enabled Pro custom payment methods.
		 *
		 * The free plugin never references Pro classes directly: when Pro is active
		 * it hooks the `wbtm_pro_enabled_payment_methods` filter and returns its own
		 * enabled gateways/offline method. Without Pro (or with an older Pro that
		 * doesn't hook the filter yet) this simply stays empty.
		 *
		 * @return array id => label of currently enabled Pro payment methods.
		 */
		public function get_enabled_pro_payment_methods() {
			if ( ! $this->has_pro() ) {
				return array();
			}

			return (array) apply_filters( 'wbtm_pro_enabled_payment_methods', array() );
		}

		/** Total number of payment methods available to customers right now. */
		public function count_available_payment_methods() {
			return count( $this->get_enabled_woocommerce_gateways() ) + count( $this->get_enabled_pro_payment_methods() );
		}

		/** Whether the booking system has at least one usable payment method. */
		public function has_available_payment_method() {
			return $this->count_available_payment_methods() > 0;
		}

		/**
		 * Whether the *currently active* booking mode has a usable payment method.
		 *
		 * A site can have (say) an enabled WooCommerce gateway while running in
		 * Standalone mode with no custom gateway configured — customers still can't
		 * pay. This checks only the system that actually owns bookings right now, so
		 * the admin notice reflects the real state instead of a gateway sitting in the
		 * mode that isn't in use.
		 */
		public function has_gateway_for_active_mode() {
			if ( 'woocommerce' === $this->active_mode() ) {
				return count( $this->get_enabled_woocommerce_gateways() ) > 0;
			}
			return count( $this->get_enabled_pro_payment_methods() ) > 0;
		}

		/** The active booking mode, exposed for mode-aware messaging. */
		public function active_mode() {
			return class_exists( 'WBTM_Functions' ) ? WBTM_Functions::booking_mode() : 'woocommerce';
		}

		private function has_woocommerce() {
			return class_exists( 'WBTM_Functions' ) ? WBTM_Functions::is_wc_active() : class_exists( 'WooCommerce' );
		}

		private function wc_payment_enabled() {
			return class_exists( 'WBTM_Functions' ) ? WBTM_Functions::wc_payment_enabled() : true;
		}

		private function has_pro() {
			return class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_pro_active();
		}
	}
}
