<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

require_once __DIR__ . '/WBTM_Abstract_Payment_Gateway.php';

if ( ! class_exists( 'WBTM_Offline_Gateway' ) ) {
	/**
	 * "Pay offline" gateway — no external API call. The booking is recorded and
	 * left on-hold for an admin to confirm payment manually (mirrors WooCommerce's
	 * own BACS/Cheque/COD gateways). get_payment_url() has nothing to redirect to,
	 * so it just hands back the caller's own return_url; verify_payment() is
	 * always true since there is no remote state to check.
	 */
	class WBTM_Offline_Gateway extends WBTM_Abstract_Payment_Gateway {

		protected function init_gateway() {
			$this->id    = 'offline';
			$this->title = $this->get_setting( 'label' ) ?: __( 'Offline Payment', 'bus-ticket-booking-with-seat-reservation' );
		}

		public function get_payment_url( array $booking_data ) {
			return $booking_data['return_url'];
		}

		public function verify_payment( $booking_id ) {
			return true;
		}
	}
}
