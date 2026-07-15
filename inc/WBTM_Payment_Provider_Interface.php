<?php
/**
 * Payment provider contract — PLACEHOLDER for a future custom payment phase.
 *
 * This phase does NOT implement any payment processing. This interface only defines the
 * seam that future gateways (offline, Stripe, PayPal, …) will implement so a standalone
 * booking flow can hand a booking to a provider. Ported from the sibling rental plugin's
 * RBFW_Payment_Provider_Interface, adapted to the bus plugin's flat inc/ layout (no
 * inc/booking/ subfolder).
 *
 * Do not add gateway logic here in this phase.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! interface_exists( 'WBTM_Payment_Provider_Interface' ) ) {
	interface WBTM_Payment_Provider_Interface {

		/** Unique provider id, e.g. 'offline', 'stripe'. */
		public function get_id();

		/** Human-readable label shown at checkout. */
		public function get_label();

		/** Whether this provider is enabled in settings. */
		public function is_enabled();

		/**
		 * Process payment for a created booking.
		 *
		 * @param int   $booking_id The wbtm_bus_booking post id.
		 * @param array $context    Arbitrary context (amount, currency, return urls…).
		 * @return array Result: [ 'success' => bool, 'redirect' => string, 'message' => string ].
		 */
		public function process( $booking_id, $context = array() );
	}
}
