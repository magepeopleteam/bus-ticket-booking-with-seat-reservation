<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * The "Book Now" AJAX entry point — always loaded (unlike WBTM_Woocommerce,
 * which is only required when WooCommerce is active), because it's the first
 * thing that must run for EITHER flow: it checks WBTM_Functions::booking_mode()
 * and, in Standalone/Custom Payment mode, dispatches straight to Pro's
 * WBTM_Standalone_Payment via the wbtm_standalone_add_booking action without
 * ever touching WooCommerce. It only falls through to the WC()->cart calls
 * below when booking_mode() is 'woocommerce' — which itself can only happen
 * when WooCommerce is active (see WBTM_Functions::mode_availability()) — so
 * relocating this here doesn't change what runs in the WooCommerce-cart flow.
 */
if ( ! class_exists( 'WBTM_Booking_Controller' ) ) {
	class WBTM_Booking_Controller {

		public function __construct() {
			add_action( 'wp_ajax_wbtm_ajax_add_to_cart', array( $this, 'wbtm_ajax_add_to_cart' ) );
			add_action( 'wp_ajax_nopriv_wbtm_ajax_add_to_cart', array( $this, 'wbtm_ajax_add_to_cart' ) );
		}

		public function wbtm_ajax_add_to_cart() {

			// Nonce check
			if (
				! isset( $_POST['wbtm_form_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['wbtm_form_nonce'] ) ),
					'wbtm_form_nonce'
				)
			) {
				wp_send_json_error( 'Nonce failed', 403 );
			}

			/*
			 * Custom/standalone booking mode bypasses the WooCommerce cart entirely —
			 * Pro's WBTM_Standalone_Payment hooks this action to create the
			 * wbtm_bus_booking record directly and respond with a checkout redirect.
			 * The wp_send_json_error below only fires if nothing handled it (Pro
			 * inactive/misconfigured), since wp_send_json_* exits on success.
			 */
			if ( class_exists( 'WBTM_Functions' ) && WBTM_Functions::booking_mode() === 'standalone' ) {
				do_action( 'wbtm_standalone_add_booking' );
				wp_send_json_error( __( 'Standalone booking is not available right now.', 'bus-ticket-booking-with-seat-reservation' ), 500 );
			}

			/**
			 * Block PHP Object Injection via serialized payloads
			 */
			$block_serialized = function ( $value ) {
				if ( is_string( $value ) && is_serialized( $value ) ) {
					wp_send_json_error( 'Invalid input detected', 400 );
					exit;
				}
			};

			/* -------------------------
			 * Block dangerous inputs
			 * ------------------------- */
			$fields = [
				'wbtm_bp_place',
				'wbtm_dp_place',
				'wbtm_bp_time',
				'wbtm_dp_time',
				'wbtm_selected_seat',
				'wbtm_price_leg',
				'wbtm_booking_mode',
				'price_val',
				'cabinSeats',
				'cabinSeatTypes',
				'j_date',
			];

			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					$block_serialized( $_POST[ $field ] );
				}
			}

			/* -------------------------
			 * Same-day return time validation
			 * ------------------------- */
			$price_leg = isset( $_POST['wbtm_price_leg'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_price_leg'] ) ) : 'outbound';
			$j_date    = isset( $_POST['j_date'] ) ? sanitize_text_field( wp_unslash( $_POST['j_date'] ) ) : '';
			$r_date    = isset( $_POST['r_date'] ) ? sanitize_text_field( wp_unslash( $_POST['r_date'] ) ) : '';
			$post_id_for_return_validation = isset( $_POST['wbtm_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_post_id'] ) ) : '';
			if ( $price_leg === 'return' && $post_id_for_return_validation && WBTM_Functions::is_same_bus_return_enabled( $post_id_for_return_validation ) && $j_date && $r_date && $j_date === $r_date && function_exists( 'WC' ) && WC()->cart ) {
				$return_bp_time = isset( $_POST['wbtm_bp_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ) ) : '';
				$return_bp_place = isset( $_POST['wbtm_bp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ) ) : '';
				$return_dp_place = isset( $_POST['wbtm_dp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ) ) : '';
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( ( $cart_item['wbtm_price_leg'] ?? 'outbound' ) !== 'outbound' ) {
						continue;
					}
					$outbound_dp_time  = $cart_item['wbtm_dp_time'] ?? '';
					$outbound_dp_place = $cart_item['wbtm_dp_place'] ?? '';
					$outbound_bp_place = $cart_item['wbtm_bp_place'] ?? '';
					// Match the return leg to its corresponding outbound leg (reverse route)
					if ( strtolower( $return_bp_place ) !== strtolower( $outbound_dp_place ) || strtolower( $return_dp_place ) !== strtolower( $outbound_bp_place ) ) {
						continue;
					}
					if ( $outbound_dp_time && $return_bp_time && strtotime( $return_bp_time ) < strtotime( $outbound_dp_time ) ) {
						wp_send_json_error( __( 'Return bus must depart after the outbound bus arrives. Please select a later return bus.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
					}
				}
			}

			$post_id = isset( $_POST['wbtm_post_id'] )
				? sanitize_text_field( wp_unslash( $_POST['wbtm_post_id'] ) )
				: '';

			$product_id = WBTM_Global_Function::get_post_info( $post_id, 'link_wc_product' );
			$booking_mode = isset( $_POST['wbtm_booking_mode'] ) ? sanitize_key( wp_unslash( $_POST['wbtm_booking_mode'] ) ) : '';
			$bp           = sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ?? '' ) );
			$dp           = sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ?? '' ) );
			$date         = sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ?? '' ) );

			$lock_acquired = false;
			try {
				$lock_acquired = WBTM_Cart_Helper::acquire_trip_lock( $post_id, $bp, $dp, $date, 30 );
				if ( ! $lock_acquired ) {
					wp_send_json_error( __( 'The system is busy. Please try again in a moment.', 'bus-ticket-booking-with-seat-reservation' ), 423 );
				}

				if ( $booking_mode === 'full_bus' ) {
					if ( ! WBTM_Functions::is_full_bus_feature_enabled() ) {
						wp_send_json_error( __( 'Full bus booking requires the Pro addon.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
					}
					$full_bus_leg   = WBTM_Functions::get_requested_price_leg();
					$full_bus_price = WBTM_Functions::get_full_bus_price( $post_id, $bp, $dp, $full_bus_leg );
					$total_seat     = (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
					$valid          = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'full_bus', [], [], $total_seat );
					if ( is_wp_error( $valid ) || $full_bus_price === '' || (float) $full_bus_price <= 0 || $total_seat <= 0 ) {
						wp_send_json_error( __( 'This full bus is not available anymore.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
					}
				}

				$cabin_mode_enabled = isset( $_POST['wbtm_cabin_mode_enabled'] )
					? sanitize_text_field( wp_unslash( $_POST['wbtm_cabin_mode_enabled'] ) )
					: '';

				$cabin_seats = false;
				$selected_cabin_seats = '';

				if ( $cabin_mode_enabled === 'yes' ) {

					$cabin_seats_raw = $_POST['cabinSeats'] ?? '';
					$block_serialized( $cabin_seats_raw );

					$cabin_seats = sanitize_text_field( wp_unslash( $cabin_seats_raw ) );
					$cabin_seats = self::wbtm_get_sanitized_cabin_seats( $cabin_seats );

					foreach ( $cabin_seats as $value ) {
						$_POST[ 'wbtm_selected_seat_cabin_' . $value['cabin'] ] = $value['seat'];
					}

					$cabin_seat_types_raw = $_POST['cabinSeatTypes'] ?? '';
					$block_serialized( $cabin_seat_types_raw );

					$cabin_seat_types = sanitize_text_field( wp_unslash( $cabin_seat_types_raw ) );
					$cabin_seat_types = self::wbtm_get_sanitized_cabin_seats( $cabin_seat_types );

					foreach ( $cabin_seat_types as $type_value ) {
						$_POST[ 'wbtm_selected_seat_type_cabin_' . $type_value['cabin'] ] = $type_value['seat'];
					}

					foreach ( $_POST as $key => $value ) {
						if ( strpos( $key, 'wbtm_selected_seat_cabin_' ) === 0 ) {
							$cabin_seats = true;
							$cabin = str_replace( 'wbtm_selected_seat_', '', $key );
							$cabin = sanitize_text_field( $cabin );
							$selected_cabin_seats .= $cabin . ' (' . sanitize_text_field( $value ) . ')' . PHP_EOL;
						}
					}
				}

				$selected_seats = $cabin_seats
					? $selected_cabin_seats
					: sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat'] ?? '' ) );

				/* -------------------------
				 * Seat availability check before the cart is touched
				 * ------------------------- */
				if ( $booking_mode !== 'full_bus' ) {
					$seat_type = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
					if ( $seat_type == 'wbtm_seat_plan' ) {
						$cabin_config      = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_cabin_config', [] );
						$has_cabin_seats   = false;
						if ( $cabin_mode_enabled === 'yes' && ! empty( $cabin_config ) ) {
							foreach ( $cabin_config as $cabin_index => $cabin ) {
								if ( ( $cabin['enabled'] ?? 'yes' ) === 'yes' && ( $cabin['rows'] ?? 0 ) > 0 && ( $cabin['cols'] ?? 0 ) > 0 ) {
									$key_name = 'wbtm_selected_seat_cabin_' . $cabin_index;
									if ( ! empty( $_POST[ $key_name ] ) ) {
										$has_cabin_seats = true;
										break;
									}
								}
							}
						}
						if ( $has_cabin_seats ) {
							$cabin_seat_infos = WBTM_Cart_Helper::get_cart_cabin_seat_info( $post_id, $cabin_config );
							$valid = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'seat', [], $cabin_seat_infos );
						} else {
							$ticket_infos = WBTM_Cart_Helper::get_cart_ticket_info( $post_id );
							$valid = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'seat', $ticket_infos );
						}
					} else {
						$ticket_infos = WBTM_Cart_Helper::get_cart_ticket_info( $post_id );
						$valid = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'seat', $ticket_infos );
					}
					if ( is_wp_error( $valid ) ) {
						wp_send_json_error( $valid->get_error_message(), 400 );
					}
				}

				/* -------------------------
				 * Selected data
				 * ------------------------- */
				$selected_Data = [
					'post_id'            => $post_id,
					'j_date'             => sanitize_text_field( wp_unslash( $_POST['j_date'] ?? '' ) ),
					'r_date'             => sanitize_text_field( wp_unslash( $_POST['r_date'] ?? '' ) ),
					'wbtm_selected_seat' => $selected_seats,
					'wbtm_bp_place'      => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ?? '' ) ),
					'wbtm_dp_place'      => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ?? '' ) ),
					'wbtm_bp_time'       => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ?? '' ) ),
					'wbtm_dp_time'       => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_time'] ?? '' ) ),
					'wbtm_booking_mode'  => sanitize_key( wp_unslash( $_POST['wbtm_booking_mode'] ?? '' ) ),
					'price_val'          => sanitize_text_field( wp_unslash( $_POST['price_val'] ?? 0 ) ),
				];

				if ( $product_id ) {
					$added = WC()->cart->add_to_cart( $product_id, 1 );
					if ( $added ) {
						$selected_bus = WBTM_Layout::selected_bus_display( $selected_Data );
						wp_send_json_success( [
							'message'      => 'Added successfully',
							'selected_bus' => $selected_bus,
						] );
					}
				}

				wp_send_json_error( 'Cart error', 400 );
			} finally {
				if ( $lock_acquired ) {
					WBTM_Cart_Helper::release_trip_lock( $post_id, $bp, $dp, $date );
				}
			}
		}

		/**
		 * Sanitize cabin seats JSON and return safe array
		 *
		 * @param string $json Cabin seats JSON string
		 * @return array
		 */
		public static function wbtm_get_sanitized_cabin_seats( $json ) {

			if ( empty($json) || ! is_string($json) ) {
				return [];
			}
			$raw_json = wp_unslash( $json );
			$decoded = json_decode( $raw_json, true );
			if ( ! is_array($decoded) ) {
				return [];
			}
			$cabin_seats = [];
			foreach ( $decoded as $item ) {
				if (
					! isset($item['cabin'], $item['seat']) ||
					! is_string($item['cabin']) ||
					! is_string($item['seat'])
				) {
					continue;
				}
				$cabin_seats[] = [
					'cabin' => sanitize_key( $item['cabin'] ),
					'seat'  => sanitize_text_field( $item['seat'] ),
				];
			}

			return $cabin_seats;
		}
	}
	new WBTM_Booking_Controller();
}
