<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

if ( ! interface_exists( 'WBTM_Payment_Gateway_Interface' ) ) {
	interface WBTM_Payment_Gateway_Interface {

		/** Unique gateway id, e.g. 'paypal', 'stripe', 'offline'. */
		public function get_id();

		/** Human-readable label shown at checkout. */
		public function get_title();

		/** Whether the admin has enabled + configured this gateway. */
		public function is_enabled();

		/**
		 * Start payment for a standalone booking.
		 *
		 * @param array $booking_data { booking_id, amount, currency, description, return_url, cancel_url, customer_email }
		 * @return string|WP_Error Hosted checkout URL to redirect the customer to, or WP_Error on failure.
		 */
		public function get_payment_url( array $booking_data );

		/**
		 * Confirm payment for a booking after the customer returns from the gateway.
		 *
		 * @param int $booking_id The wbtm_bus_booking post id.
		 * @return bool True if the payment is confirmed captured/paid.
		 */
		public function verify_payment( $booking_id );
	}
}
