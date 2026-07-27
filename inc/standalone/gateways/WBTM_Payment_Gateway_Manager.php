<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

require_once __DIR__ . '/WBTM_Payment_Gateway_Interface.php';
require_once __DIR__ . '/WBTM_Abstract_Payment_Gateway.php';
require_once __DIR__ . '/WBTM_Offline_Gateway.php';
// NOTE: PayPal & Stripe gateways are intentionally NOT bundled in the free plugin —
// they remain a Pro feature. The free standalone engine ships Offline only; the Pro
// addon adds its own PayPal/Stripe gateways (and its own manager) when active.

if ( ! class_exists( 'WBTM_Payment_Gateway_Manager' ) ) {
	class WBTM_Payment_Gateway_Manager {

		private static $instance = null;

		/** @var WBTM_Payment_Gateway_Interface[] */
		private $gateways = array();

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->register_gateway( new WBTM_Offline_Gateway() );

			/**
			 * Extension point for third-party / Pro gateways (PayPal, Stripe, …).
			 * The Pro addon registers its gateways here when it is active.
			 */
			do_action( 'wbtm_register_payment_gateways', $this );
		}

		public function register_gateway( WBTM_Payment_Gateway_Interface $gateway ) {
			$this->gateways[ $gateway->get_id() ] = $gateway;
		}

		public function get_gateway( $id ) {
			return $this->gateways[ $id ] ?? null;
		}

		/** @return WBTM_Payment_Gateway_Interface[] */
		public function get_all_gateways() {
			return $this->gateways;
		}

		/** @return WBTM_Payment_Gateway_Interface[] */
		public function get_available_gateways() {
			return array_filter(
				$this->gateways,
				function ( $gateway ) {
					return $gateway->is_enabled();
				}
			);
		}
	}
}
