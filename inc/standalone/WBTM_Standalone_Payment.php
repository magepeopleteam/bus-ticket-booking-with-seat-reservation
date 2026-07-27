<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * Orchestrates the Standalone/Custom Payment booking flow: creates a
 * wbtm_bus_booking record straight from the "Book Now" submission (bypassing
 * the WooCommerce cart), takes the customer to a PayPal/Stripe/Offline
 * checkout, and reconciles the result when the gateway redirects back.
 *
 * Supports the regular seat-map and without-seat-plan ticket-quantity modes
 * (via WBTM_Cart_Helper::get_cart_ticket_info()) as well as full-bus bookings,
 * whose single "Full Bus" record mirrors the meta the WooCommerce flow writes in
 * WBTM_Woocommerce::add_cart_item_data() so downstream consumers behave identically.
 */
if ( ! class_exists( 'WBTM_Standalone_Payment' ) ) {
	class WBTM_Standalone_Payment {

		const RETURN_FLAG = 'wbtm_payment_return';
		const CANCEL_FLAG = 'wbtm_payment_cancel';
		const PAID_STATUSES = array( 'processing', 'completed', 'on-hold' );
		// Per-group secret issued when a booking group is created, stored on the
		// group head and mirrored to a cookie on the visitor's browser. It binds
		// the unauthenticated checkout-pay call to whoever actually created the
		// booking — see can_checkout_group() / issue_group_token().
		const TOKEN_META = 'wbtm_sa_access_token';

		public function __construct() {
			add_action( 'wbtm_standalone_add_booking', array( $this, 'create_booking' ) );
			add_action( 'wp_ajax_wbtm_standalone_checkout_pay', array( $this, 'ajax_checkout_pay' ) );
			add_action( 'wp_ajax_nopriv_wbtm_standalone_checkout_pay', array( $this, 'ajax_checkout_pay' ) );
			add_action( 'init', array( $this, 'handle_payment_return' ) );
			add_filter( 'wbtm_pro_enabled_payment_methods', array( $this, 'filter_enabled_payment_methods' ) );
		}

		public function filter_enabled_payment_methods( $methods ) {
			foreach ( WBTM_Payment_Gateway_Manager::instance()->get_available_gateways() as $gateway ) {
				$methods[ $gateway->get_id() ] = $gateway->get_title();
			}
			return $methods;
		}

		/**
		 * wbtm_standalone_add_booking handler — fired by the free plugin's
		 * wbtm_ajax_add_to_cart() when booking_mode() === 'standalone'. Must
		 * end the request itself (wp_send_json_* exits) since the free plugin
		 * has no further fallback beyond an error response.
		 */
		public function create_booking() {
			$post_id = isset( $_POST['wbtm_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_post_id'] ) ) : '';
			if ( ! $post_id || get_post_type( $post_id ) !== WBTM_Functions::get_cpt() ) {
				wp_send_json_error( __( 'Invalid bus selected.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
			}

			// Full-bus booking is Pro-gated. This class only loads with Pro active, but
			// keep the guard so the request fails cleanly if the feature is ever toggled off.
			$is_full_bus = ( ( $_POST['wbtm_booking_mode'] ?? '' ) === 'full_bus' );
			if ( $is_full_bus && ! WBTM_Functions::is_full_bus_feature_enabled() ) {
				wp_send_json_error( __( 'Full bus booking requires the Pro addon.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
			}

			// Authoritative re-check: the frontend already short-circuits to the inline
			// login/register panel when it knows the visitor is logged out, but that
			// client-side flag is only a UX shortcut, not the security boundary.
			if ( WBTM_Functions::login_required() && ! is_user_logged_in() ) {
				wp_send_json_error(
					array(
						'require_login' => true,
						'message'       => __( 'Please log in or create an account to continue booking.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					401
				);
			}

			$bp   = sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ?? '' ) );
			$dp   = sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ?? '' ) );
			$date = sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ?? '' ) );

			$lock_acquired = false;
			try {
				$lock_acquired = WBTM_Cart_Helper::acquire_trip_lock( $post_id, $bp, $dp, $date, 30 );
				if ( ! $lock_acquired ) {
					wp_send_json_error( __( 'The system is busy. Please try again in a moment.', 'bus-ticket-booking-with-seat-reservation' ), 423 );
				}

				// Full-bus context passed to insert_booking_records() so the created
				// record carries the same wbtm_booking_mode/full-bus meta the WooCommerce
				// flow writes (see WBTM_Woocommerce), which every downstream consumer —
				// availability queries, passenger list, checkout/confirmation UIs — reads.
				$full_bus_context = array();

				if ( $is_full_bus ) {
					// Mirror WBTM_Woocommerce::add_cart_item_data()'s full-bus path: one
					// "Full Bus" ticket row priced by get_full_bus_pricing(), validated
					// against whole-bus availability (no seat can already be sold).
					$price_leg        = WBTM_Functions::get_requested_price_leg();
					$full_bus_pricing = WBTM_Functions::get_full_bus_pricing( $post_id, $bp, $dp, $price_leg );
					$full_bus_price   = ! empty( $full_bus_pricing ) ? (float) $full_bus_pricing['final_price'] : 0;
					$total_seat       = (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );

					$valid = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'full_bus', array(), array(), $total_seat );
					if ( is_wp_error( $valid ) ) {
						wp_send_json_error( $valid->get_error_message(), 400 );
					}
					if ( $full_bus_price <= 0 || $total_seat <= 0 ) {
						wp_send_json_error( __( 'This full bus is not available anymore.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
					}

					$ticket_infos = array(
						array(
							'ticket_name'  => esc_html__( 'Full Bus', 'bus-ticket-booking-with-seat-reservation' ),
							'ticket_type'  => 'full_bus',
							'seat_name'    => esc_html__( 'Full Bus', 'bus-ticket-booking-with-seat-reservation' ),
							'ticket_price' => $full_bus_price,
							'ticket_qty'   => 1,
						),
					);
					$full_bus_context = array(
						'base_price' => ! empty( $full_bus_pricing ) ? (float) $full_bus_pricing['base_price'] : $full_bus_price,
						'discount'   => ! empty( $full_bus_pricing ) ? (float) $full_bus_pricing['discount'] : 0,
						'seat_count' => max( 1, $total_seat ),
					);
				} else {
					$cabin_mode_enabled = sanitize_text_field( wp_unslash( $_POST['wbtm_cabin_mode_enabled'] ?? '' ) ) === 'yes';
					$seat_type          = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );

					$cabin_infos = array();
					if ( $cabin_mode_enabled && $seat_type === 'wbtm_seat_plan' ) {
						$cabin_config = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_cabin_config', array() );
						$cabin_infos  = WBTM_Cart_Helper::get_cart_cabin_seat_info( $post_id, $cabin_config );
					}

					if ( ! empty( $cabin_infos ) ) {
						$valid        = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'seat', array(), $cabin_infos );
						$ticket_infos = array_map(
							function ( $cabin_seat ) {
								return array(
									'ticket_name'      => $cabin_seat['ticket_name'],
									'ticket_type'      => $cabin_seat['seat_type'],
									'seat_name'        => $cabin_seat['seat_name'],
									'ticket_price'     => $cabin_seat['ticket_price'],
									'ticket_qty'       => 1,
									'cabin_name'       => $cabin_seat['cabin_name'],
									'cabin_index'      => $cabin_seat['cabin_index'],
									'price_multiplier' => $cabin_seat['price_multiplier'],
								);
							},
							$cabin_infos
						);
					} else {
						$ticket_infos = WBTM_Cart_Helper::get_cart_ticket_info( $post_id );
						$valid        = WBTM_Cart_Helper::validate_bus_availability( $post_id, $bp, $dp, $date, 'seat', $ticket_infos );
					}

					if ( is_wp_error( $valid ) ) {
						wp_send_json_error( $valid->get_error_message(), 400 );
					}
					// Seat-hold enforcement (free-plugin Feature 2): seats held by another
					// visitor are rejected here under the trip lock; own holds pass through.
					if ( class_exists( 'WBTM_Seat_Hold' ) && WBTM_Seat_Hold::is_enabled() ) {
						$held_seats = WBTM_Seat_Hold::seats_held_by_others( $post_id, $date, $ticket_infos );
						if ( ! empty( $held_seats ) ) {
							wp_send_json_error(
								sprintf(
									/* translators: %s: comma-separated seat names. */
									__( 'Sorry, seat %s is currently held by another customer. Please choose a different seat.', 'bus-ticket-booking-with-seat-reservation' ),
									implode( ', ', $held_seats )
								),
								400
							);
						}
					}
					if ( empty( $ticket_infos ) ) {
						wp_send_json_error( __( 'Please select at least one seat.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
					}
				}

				/*
				 * Round-trip context. Standalone/Custom Payment mode has no WooCommerce
				 * cart to stage a two-leg trip in, so we mirror the WC flow manually:
				 *  - Outbound leg of a round trip  -> stage the booking, return the
				 *    selected-bus card + its group id, and let the frontend reveal the
				 *    Return tab (no checkout yet).
				 *  - Return leg                    -> link into the staged outbound group
				 *    so both legs check out as one order.
				 *  - Single trip                   -> check out immediately (original flow).
				 */
				$journey_type    = ( ( $_POST['wbtm_journey_type'] ?? '' ) === 'return' ) ? 'return' : 'departure';
				$is_round_trip   = ( ( $_POST['wbtm_round_trip'] ?? '' ) === '1' );
				$return_group_id = isset( $_POST['wbtm_return_group_id'] ) ? absint( wp_unslash( $_POST['wbtm_return_group_id'] ) ) : 0;

				// Validate the staged outbound group the return leg claims to belong to:
				// it must be a real pending booking, and (when logged in) owned by this user.
				if ( $journey_type === 'return' && $return_group_id > 0 ) {
					$outbound_ok = get_post( $return_group_id )
						&& get_post_type( $return_group_id ) === 'wbtm_bus_booking'
						&& (string) get_post_meta( $return_group_id, 'wbtm_order_status', true ) === 'pending';
					if ( $outbound_ok && is_user_logged_in() ) {
						$owner = (int) get_post_meta( $return_group_id, 'wbtm_user_id', true );
						if ( $owner && $owner !== get_current_user_id() ) {
							$outbound_ok = false;
						}
					}
					if ( ! $outbound_ok ) {
						// Staged outbound is gone/invalid — degrade to a standalone booking
						// for the return leg on its own rather than failing outright.
						$return_group_id = 0;
					}
				}

				// Re-staging the outbound leg (customer used "Change Departure"): discard the
				// previously-staged, still-unpaid outbound group so it stops holding seats.
				if ( $journey_type === 'departure' ) {
					$prev_group_id = isset( $_POST['wbtm_prev_outbound_group_id'] ) ? absint( wp_unslash( $_POST['wbtm_prev_outbound_group_id'] ) ) : 0;
					if ( $prev_group_id > 0 && get_post_type( $prev_group_id ) === 'wbtm_bus_booking'
						&& (string) get_post_meta( $prev_group_id, 'wbtm_order_status', true ) === 'pending' ) {
						$prev_owner = (int) get_post_meta( $prev_group_id, 'wbtm_user_id', true );
						$owns_prev  = ! is_user_logged_in() || ! $prev_owner || $prev_owner === get_current_user_id();
						if ( $owns_prev ) {
							foreach ( self::get_group_post_ids( $prev_group_id ) as $stale_id ) {
								wp_delete_post( $stale_id, true );
							}
						}
					}
				}

				$link_group_id = ( $journey_type === 'return' && $return_group_id > 0 ) ? $return_group_id : 0;

				// Re-picking a return bus must replace the previous return leg, not stack a
				// second one onto the group (which would double-book seats and the total).
				if ( $link_group_id > 0 ) {
					foreach ( self::get_group_post_ids( $link_group_id ) as $existing_id ) {
						if ( (string) get_post_meta( $existing_id, 'wbtm_journey_type', true ) === 'return' ) {
							wp_delete_post( $existing_id, true );
						}
					}
				}

				$booking_ids = $this->insert_booking_records( $post_id, $ticket_infos, $link_group_id, $full_bus_context );
				if ( empty( $booking_ids ) ) {
					wp_send_json_error( __( 'Unable to create the booking. Please try again.', 'bus-ticket-booking-with-seat-reservation' ), 500 );
				}

				// The booking records now block the seats, so the caller's temporary
				// holds on them can go (free-plugin Feature 2).
				if ( class_exists( 'WBTM_Seat_Hold' ) ) {
					WBTM_Seat_Hold::release_booked_seats( $post_id, $date, $ticket_infos );
				}

				do_action( 'wbtm_order_status_change', 'pending', $post_id, $booking_ids[0] );
				if ( class_exists( 'WBTM_Standalone_Mail' ) ) {
					WBTM_Standalone_Mail::maybe_send_status_emails( $booking_ids[0], 'pending' );
				}

				// --- Outbound leg of a round trip: stage it and reveal the Return tab. ---
				if ( $journey_type === 'departure' && $is_round_trip && class_exists( 'WBTM_Layout' ) ) {
					$group_id     = $booking_ids[0];
					$checkout_url = add_query_arg( 'booking_id', $group_id, self::get_checkout_page_url() );

					$selected_seat_label = sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat'] ?? '' ) );
					if ( $selected_seat_label === '' ) {
						$seat_names = array();
						foreach ( $ticket_infos as $ticket_info ) {
							if ( ! empty( $ticket_info['seat_name'] ) ) {
								$seat_names[] = $ticket_info['seat_name'];
							}
						}
						$selected_seat_label = implode( ', ', $seat_names );
					}

					$selected_bus = WBTM_Layout::selected_bus_display(
						array(
							'post_id'                 => $post_id,
							'j_date'                  => sanitize_text_field( wp_unslash( $_POST['j_date'] ?? '' ) ),
							'r_date'                  => sanitize_text_field( wp_unslash( $_POST['r_date'] ?? '' ) ),
							'wbtm_selected_seat'      => $selected_seat_label,
							'wbtm_bp_place'           => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ?? '' ) ),
							'wbtm_dp_place'           => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ?? '' ) ),
							'wbtm_bp_time'            => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ?? '' ) ),
							'wbtm_dp_time'            => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_time'] ?? '' ) ),
							'wbtm_booking_mode'       => 'standalone',
							'price_val'               => sanitize_text_field( wp_unslash( $_POST['price_val'] ?? 0 ) ),
							'standalone_checkout_url' => $checkout_url,
						)
					);

					wp_send_json_success(
						array(
							'message'           => __( 'Outbound bus selected. Now choose your return bus.', 'bus-ticket-booking-with-seat-reservation' ),
							'selected_bus'      => $selected_bus,
							'outbound_group_id' => $group_id,
						)
					);
				}

				// --- Return leg or single trip: proceed to checkout. A linked return leg
				//     keys the checkout on the outbound group so both legs are billed together. ---
				$checkout_booking_id = ( $journey_type === 'return' && $return_group_id > 0 ) ? $return_group_id : $booking_ids[0];
				$checkout_url   = add_query_arg( 'booking_id', $checkout_booking_id, self::get_checkout_page_url() );
				$checkout_html  = apply_filters( 'wbtm_get_standalone_checkout_html', '', $checkout_booking_id );
				$response_data  = array(
					'booking_id'     => $checkout_booking_id,
					'redirect_url'   => $checkout_url,
					'checkout_modal' => true,
				);
				if ( $checkout_html ) {
					$response_data['checkout_html'] = $checkout_html;
				}
				wp_send_json_success( $response_data );
			} finally {
				if ( $lock_acquired ) {
					WBTM_Cart_Helper::release_trip_lock( $post_id, $bp, $dp, $date );
				}
			}
		}

		/**
		 * @param int $group_order_id When > 0, every created record is linked into this
		 *                            existing order group instead of starting a new one —
		 *                            used for the return leg of a round trip so both legs
		 *                            share one wbtm_order_id and check out together.
		 * @param array $full_bus When non-empty, the created record(s) are tagged as a
		 *                        full-bus booking (wbtm_booking_mode = full_bus) with the
		 *                        base_price/discount/seat_count meta the WooCommerce flow
		 *                        also writes, so availability tracking counts the whole bus
		 *                        and the passenger/checkout UIs render the full-bus summary.
		 * @return int[] Created wbtm_bus_booking post ids. For a new group the first id
		 *                doubles as the group's wbtm_order_id (there is no real WC order to key on).
		 */
		private function insert_booking_records( $post_id, array $ticket_infos, $group_order_id = 0, array $full_bus = array() ) {
			$group_order_id = absint( $group_order_id );
			$extra_services = WBTM_Cart_Helper::get_cart_extra_service_info( $post_id );

			$total = 0.0;
			$passenger_count = 0;
			foreach ( $ticket_infos as $ticket_info ) {
				$qty = (int) ( $ticket_info['ticket_qty'] ?? 1 );
				$total += (float) ( $ticket_info['ticket_price'] ?? 0 ) * $qty;
				$passenger_count += max( 1, $qty );
			}
			$passenger_count = max( 1, $passenger_count );
			foreach ( $extra_services as $service ) {
				// per_booking: price × qty (charged once); per_passenger: × passengers.
				$total += WBTM_Functions::ex_service_line_total( $service, $passenger_count );
			}

			$price_leg     = ( ( $_POST['wbtm_price_leg'] ?? '' ) === 'return' ) ? 'return' : 'outbound';
			$journey_type  = ( ( $_POST['wbtm_journey_type'] ?? '' ) === 'return' ) ? 'return' : 'departure';
			$common        = array(
				'wbtm_bus_id'          => $post_id,
				'wbtm_user_id'         => get_current_user_id(),
				'wbtm_item_id'         => 0,
				'wbtm_tp'              => $total,
				'wbtm_boarding_point'  => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ?? '' ) ),
				'wbtm_boarding_time'   => sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ?? '' ) ),
				'wbtm_dropping_point'  => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ?? '' ) ),
				'wbtm_dropping_time'   => sanitize_text_field( wp_unslash( $_POST['wbtm_dp_time'] ?? '' ) ),
				'wbtm_bus_start_point' => sanitize_text_field( wp_unslash( $_POST['wbtm_start_point'] ?? '' ) ),
				'wbtm_start_time'      => sanitize_text_field( wp_unslash( $_POST['wbtm_start_time'] ?? '' ) ),
				'wbtm_booking_date'    => current_time( 'mysql' ),
				'wbtm_pickup_point'    => sanitize_text_field( wp_unslash( $_POST['wbtm_pickup_point'] ?? '' ) ),
				'wbtm_drop_off_point'  => sanitize_text_field( wp_unslash( $_POST['wbtm_drop_off_point'] ?? '' ) ),
				'wbtm_ticket_status'   => 1,
				'wbtm_order_status'    => 'pending',
				'wbtm_billing_type'    => '',
				'wbtm_extra_services'  => $extra_services,
				'wbtm_user_name'       => '',
				'wbtm_user_email'      => '',
				'wbtm_user_phone'      => '',
				'wbtm_user_address'    => '',
				'wbtm_price_leg'       => $price_leg,
				'wbtm_journey_type'    => $journey_type,
			);

			$booking_ids = array();
			foreach ( $ticket_infos as $ticket_info ) {
				$qty = max( 1, (int) ( $ticket_info['ticket_qty'] ?? 1 ) );
				for ( $i = 0; $i < $qty; $i++ ) {
					$data              = $common;
					$data['wbtm_ticket']    = $ticket_info['ticket_name'] ?? '';
					$data['wbtm_seat']      = $ticket_info['seat_name'] ?? ( $ticket_info['ticket_name'] ?? '' );
					$data['wbtm_bus_fare']  = (float) ( $ticket_info['ticket_price'] ?? 0 );

					if ( isset( $ticket_info['cabin_name'], $ticket_info['cabin_index'] ) ) {
						// Preserve the deck (lower/upper) so a double-decker cabin's
						// upper seat is stored/tracked distinctly from the lower deck.
						$cabin_is_upper = ! empty( $ticket_info['is_upper'] );
						$data['wbtm_cabin_info'] = array(
							'cabin_name'       => $ticket_info['cabin_name'],
							'cabin_index'      => $ticket_info['cabin_index'],
							'deck'             => $cabin_is_upper ? 'upper' : 'lower',
							'is_upper'         => $cabin_is_upper,
							'price_multiplier' => $ticket_info['price_multiplier'] ?? 1.0,
						);
						$data['wbtm_seat'] = ! empty( $ticket_info['seat_identifier'] )
							? $ticket_info['seat_identifier']
							: WBTM_Functions::cabin_seat_identifier( $ticket_info['cabin_index'], $data['wbtm_seat'], $cabin_is_upper );
					}

					if ( ! empty( $full_bus ) ) {
						$data['wbtm_booking_mode']        = 'full_bus';
						$data['wbtm_full_bus_base_price'] = $full_bus['base_price'];
						$data['wbtm_full_bus_discount']   = $full_bus['discount'];
						$data['wbtm_full_bus_seat_count'] = $full_bus['seat_count'];
					}

					if ( $group_order_id > 0 ) {
						// Link this leg into an existing order group (round-trip return leg).
						$data['wbtm_order_id'] = $group_order_id;
					} elseif ( ! empty( $booking_ids ) ) {
						$data['wbtm_order_id'] = $booking_ids[0];
					}

					$booking_id = WBTM_Cart_Helper::add_cpt_data( 'wbtm_bus_booking', '', $data );
					if ( ! $booking_id || is_wp_error( $booking_id ) ) {
						continue;
					}
					if ( $group_order_id <= 0 && empty( $booking_ids ) ) {
						// The group's own first post id doubles as its wbtm_order_id.
						update_post_meta( $booking_id, 'wbtm_order_id', $booking_id );
						// Bind subsequent checkout to this browser/session (see
						// can_checkout_group()). Only the group head carries the token.
						$this->issue_group_token( $booking_id );
					}
					$booking_ids[] = $booking_id;
				}
			}

			if ( ! empty( $booking_ids ) && ! empty( $extra_services ) ) {
				WBTM_Cart_Helper::add_cpt_data(
					'wbtm_service_booking',
					'',
					array(
						'wbtm_bus_id'         => $post_id,
						'wbtm_item_id'        => 0,
						'wbtm_boarding_time'  => $common['wbtm_boarding_time'],
						'wbtm_start_time'     => $common['wbtm_start_time'],
						'wbtm_order_id'       => $group_order_id > 0 ? $group_order_id : $booking_ids[0],
						'wbtm_order_status'   => 'pending',
						'wbtm_user_id'        => get_current_user_id(),
						'wbtm_extra_services' => $extra_services,
					)
				);
			}

			return $booking_ids;
		}

		/**
		 * AJAX: customer picked a gateway + entered billing info on the checkout
		 * page and clicked Pay. Persists billing info onto the booking group and
		 * asks the chosen gateway for a redirect URL.
		 */
		public function ajax_checkout_pay() {
			check_ajax_referer( 'wbtm_standalone_checkout', 'nonce' );

			$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
			$gateway_id = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';

			if ( ! $booking_id || get_post_type( $booking_id ) !== 'wbtm_bus_booking' ) {
				wp_send_json_error( __( 'Booking not found.', 'bus-ticket-booking-with-seat-reservation' ), 404 );
			}

			// SECURITY: this endpoint is unauthenticated (wp_ajax_nopriv) and its
			// nonce is a shared guest nonce localized on every public checkout page,
			// so the nonce alone proves nothing about WHICH booking the caller owns.
			// Without this gate any visitor could POST an arbitrary booking_id and
			// overwrite its billing name/email — and because the guest customer
			// portal authenticates on order-id + email, rewriting a paid booking's
			// email hands the attacker that victim's ticket. Bind the call to the
			// booking's creator and refuse to touch an already-paid booking.
			if ( ! self::can_checkout_group( $booking_id ) ) {
				wp_send_json_error( __( 'This booking cannot be paid from here.', 'bus-ticket-booking-with-seat-reservation' ), 403 );
			}

			$gateway = WBTM_Payment_Gateway_Manager::instance()->get_gateway( $gateway_id );
			if ( ! $gateway || ! $gateway->is_enabled() ) {
				wp_send_json_error( __( 'The selected payment method is not available.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
			}

			$billing_name  = isset( $_POST['billing_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_name'] ) ) : '';
			$billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
			$billing_phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';

			if ( ! $billing_name || ! $billing_email || ! is_email( $billing_email ) ) {
				wp_send_json_error( __( 'Please provide your name and a valid email address.', 'bus-ticket-booking-with-seat-reservation' ), 400 );
			}

			foreach ( self::get_group_post_ids( $booking_id ) as $id ) {
				update_post_meta( $id, 'wbtm_user_name', $billing_name );
				update_post_meta( $id, 'wbtm_user_email', $billing_email );
				update_post_meta( $id, 'wbtm_user_phone', $billing_phone );
				update_post_meta( $id, 'wbtm_billing_type', $gateway_id );
			}
			update_post_meta( $booking_id, 'wbtm_awaiting_gateway', 1 );

			$checkout_url = self::get_checkout_page_url();
			$return_url   = add_query_arg(
				array(
					'booking_id'     => $booking_id,
					'gateway'        => $gateway_id,
					self::RETURN_FLAG => 1,
				),
				$checkout_url
			);
			$cancel_url = add_query_arg(
				array(
					'booking_id'     => $booking_id,
					'gateway'        => $gateway_id,
					self::CANCEL_FLAG => 1,
				),
				$checkout_url
			);

			// Charge the whole group total (outbound + return for a round trip), not just
			// the head record's single-leg wbtm_tp, minus any coupon the customer
			// applied on the checkout card.
			$pay_summary = self::get_booking_summary( $booking_id );
			$pay_amount  = $pay_summary ? (float) $pay_summary['total'] : (float) get_post_meta( $booking_id, 'wbtm_tp', true );
			if ( class_exists( 'WBTM_Standalone_Coupon' ) ) {
				$pay_amount = WBTM_Standalone_Coupon::payable_total( $booking_id, $pay_amount );
			}
			$booking_data = array(
				'booking_id'     => $booking_id,
				'amount'         => $pay_amount,
				'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
				'description'    => sprintf( /* translators: %d: booking id */ __( 'Bus Booking #%d', 'bus-ticket-booking-with-seat-reservation' ), $booking_id ),
				'return_url'     => $return_url,
				'cancel_url'     => $cancel_url,
				'customer_email' => $billing_email,
			);

			$url = $gateway->get_payment_url( $booking_data );
			if ( is_wp_error( $url ) ) {
				wp_send_json_error( $url->get_error_message(), 400 );
			}

			// Offline completes immediately — skip the checkout-return hop and send
			// the customer straight to an inline modal confirmation.
			if ( 'offline' === $gateway_id ) {
				if ( ! $gateway->verify_payment( $booking_id ) ) {
					$this->mark_group_status( $booking_id, 'failed' );
					$confirmation_html = class_exists( 'WBTM_Standalone_Confirmation' )
						? WBTM_Standalone_Confirmation::get_confirmation_card_html( $booking_id, 'failed' )
						: '';
					wp_send_json_success(
						array(
							'confirmation_html'   => $confirmation_html,
							'confirmation_result' => 'failed',
							'confirmation_url'    => self::get_result_page_url( $booking_id, 'failed' ),
						)
					);
				}
				$this->mark_group_status( $booking_id, 'on-hold' );
				$confirmation_html = class_exists( 'WBTM_Standalone_Confirmation' )
					? WBTM_Standalone_Confirmation::get_confirmation_card_html( $booking_id, 'success' )
					: '';
				wp_send_json_success(
					array(
						'confirmation_html'   => $confirmation_html,
						'confirmation_result' => 'success',
						'confirmation_url'    => self::get_result_page_url( $booking_id, 'success' ),
					)
				);
			}

			wp_send_json_success( array( 'redirect_url' => $url ) );
		}


		/**
		 * Runs on every front-end request; only acts when the checkout page's
		 * return/cancel query args are present (set by us in ajax_checkout_pay()).
		 */
		public function handle_payment_return() {
			if ( is_admin() || ! isset( $_GET['booking_id'] ) ) {
				return;
			}
			if ( ! isset( $_GET[ self::RETURN_FLAG ] ) && ! isset( $_GET[ self::CANCEL_FLAG ] ) ) {
				return;
			}

			$booking_id = absint( $_GET['booking_id'] );
			if ( ! $booking_id || get_post_type( $booking_id ) !== 'wbtm_bus_booking' ) {
				return;
			}

			if ( isset( $_GET[ self::CANCEL_FLAG ] ) ) {
				$this->mark_group_status( $booking_id, 'cancelled' );
				$this->redirect_to_result( $booking_id, 'cancelled' );
			}

			$current_status = get_post_meta( $booking_id, 'wbtm_order_status', true );
			if ( in_array( $current_status, self::PAID_STATUSES, true ) ) {
				// Idempotent: gateway may hit the return URL more than once.
				$this->redirect_to_result( $booking_id, 'success' );
			}

			$gateway_id = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : get_post_meta( $booking_id, 'wbtm_billing_type', true );
			$gateway    = WBTM_Payment_Gateway_Manager::instance()->get_gateway( $gateway_id );

			if ( ! $gateway || ! $gateway->verify_payment( $booking_id ) ) {
				$this->mark_group_status( $booking_id, 'failed' );
				$this->redirect_to_result( $booking_id, 'failed' );
			}

			$paid_status = ( 'offline' === $gateway_id ) ? 'on-hold' : 'processing';
			$this->mark_group_status( $booking_id, $paid_status );
			$this->redirect_to_result( $booking_id, 'success' );
		}

		/* ------------------------------------------------------------------ *
		 *  Ownership binding for the unauthenticated checkout-pay endpoint
		 * ------------------------------------------------------------------ */

		/** Cookie name that carries the access token for one booking group. */
		private static function token_cookie_name( $group_id ) {
			return 'wbtm_sa_token_' . (int) $group_id;
		}

		/**
		 * Mint a per-group access token, store it on the group head and mirror it
		 * to an http-only cookie so the same browser can later prove it owns the
		 * booking at checkout. Called once, when a new group head is created.
		 */
		private function issue_group_token( $group_id ) {
			$token = wp_generate_password( 32, false );
			update_post_meta( $group_id, self::TOKEN_META, $token );

			if ( ! headers_sent() ) {
				$name = self::token_cookie_name( $group_id );
				setcookie(
					$name,
					$token,
					array(
						'expires'  => time() + DAY_IN_SECONDS,
						'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
				// Make it readable within this same request (e.g. an immediate
				// single-trip checkout) before the browser echoes it back.
				$_COOKIE[ $name ] = $token;
			}
		}

		/**
		 * Whether the current request is allowed to drive checkout for a booking
		 * group. Two independent conditions must both hold:
		 *
		 *  1. The group must NOT already be paid/confirmed — you can never mutate
		 *     the billing of a settled booking (this is what blocks the ticket-
		 *     takeover vector against other people's completed bookings).
		 *  2. The caller must be the booking's creator — a logged-in user matching
		 *     wbtm_user_id, or a guest presenting the token cookie issued when the
		 *     booking was created.
		 *
		 * Legacy bookings created before tokens existed have no token meta; for
		 * those we fall back to the status guard alone (condition 1), which still
		 * protects every paid booking.
		 */
		public static function can_checkout_group( $booking_id ) {
			$status = (string) get_post_meta( $booking_id, 'wbtm_order_status', true );
			if ( in_array( $status, self::PAID_STATUSES, true ) ) {
				return false;
			}

			$owner = (int) get_post_meta( $booking_id, 'wbtm_user_id', true );
			if ( $owner > 0 && is_user_logged_in() && $owner === get_current_user_id() ) {
				return true;
			}

			$token = (string) get_post_meta( $booking_id, self::TOKEN_META, true );
			if ( $token !== '' ) {
				$cookie_name = self::token_cookie_name( $booking_id );
				$presented   = isset( $_COOKIE[ $cookie_name ] )
					? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) )
					: '';
				return $presented !== '' && hash_equals( $token, $presented );
			}

			// No token on record (pre-upgrade booking): status guard above still
			// applies, so only an unpaid legacy group can reach here.
			return true;
		}

		private static function get_group_post_ids( $booking_id ) {
			$order_id = get_post_meta( $booking_id, 'wbtm_order_id', true ) ?: $booking_id;
			$query    = new WP_Query(
				array(
					'post_type'      => 'wbtm_bus_booking',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => 'wbtm_order_id',
					'meta_value'     => $order_id,
				)
			);
			return $query->posts ?: array( $booking_id );
		}

		/**
		 * The full itemized picture of a booking group — every seat/ticket row
		 * plus any purchased extra services — shared by the checkout page, the
		 * confirmation page, and the notification emails so all three always
		 * show the same breakdown instead of each re-deriving it independently.
		 *
		 * @return array|null Null if $booking_id isn't a real booking post.
		 */
		public static function get_booking_summary( $booking_id ) {
			if ( get_post_type( $booking_id ) !== 'wbtm_bus_booking' ) {
				return null;
			}

			$tickets        = array();
			$extra_services = array();
			$bus_id         = 0;
			// wbtm_tp is the whole-leg total duplicated onto every seat record of that
			// leg, so for a round-trip group we sum ONE value per journey leg (departure +
			// return) instead of per record — otherwise a multi-seat leg would multiply it.
			$leg_totals     = array();
			// Per-leg breakdown (outbound + return) so the checkout / confirmation UIs can
			// show each journey's own route, date, bus and seats instead of one flat list.
			$legs           = array();
			$leg_labels     = array(
				'departure' => __( 'Outbound', 'bus-ticket-booking-with-seat-reservation' ),
				'return'    => __( 'Return', 'bus-ticket-booking-with-seat-reservation' ),
			);

			foreach ( self::get_group_post_ids( $booking_id ) as $id ) {
				if ( ! $bus_id ) {
					$bus_id = (int) get_post_meta( $id, 'wbtm_bus_id', true );
				}
				$ticket = array(
					'name'  => get_post_meta( $id, 'wbtm_ticket', true ) ?: __( 'Ticket', 'bus-ticket-booking-with-seat-reservation' ),
					// Deck-aware label (e.g. "Coach A - Upper Deck - U3") for double-decker cabins.
					'seat'  => WBTM_Functions::format_cabin_seat_label( get_post_meta( $id, 'wbtm_seat', true ), get_post_meta( $id, 'wbtm_cabin_info', true ) ),
					'price' => (float) get_post_meta( $id, 'wbtm_bus_fare', true ),
				);
				$tickets[] = $ticket;

				$row_services = (array) get_post_meta( $id, 'wbtm_extra_services', true );
				if ( empty( $extra_services ) ) {
					// Duplicated identically onto every seat post at insert time — read once.
					$extra_services = $row_services;
				}

				$leg_key = get_post_meta( $id, 'wbtm_journey_type', true ) ?: 'departure';
				if ( ! isset( $leg_totals[ $leg_key ] ) ) {
					$leg_totals[ $leg_key ] = (float) get_post_meta( $id, 'wbtm_tp', true );
				}

				if ( ! isset( $legs[ $leg_key ] ) ) {
					$leg_bus_id  = (int) get_post_meta( $id, 'wbtm_bus_id', true );
					$leg_bp_time = get_post_meta( $id, 'wbtm_boarding_time', true );
					$legs[ $leg_key ] = array(
						'journey_type'   => $leg_key,
						'label'          => $leg_labels[ $leg_key ] ?? ucfirst( $leg_key ),
						'bus_id'         => $leg_bus_id,
						'bus_name'       => get_the_title( $leg_bus_id ),
						'boarding_point' => get_post_meta( $id, 'wbtm_boarding_point', true ),
						'dropping_point' => get_post_meta( $id, 'wbtm_dropping_point', true ),
						'boarding_time'  => $leg_bp_time,
						'journey_date'   => $leg_bp_time ? date_i18n( 'D, j M Y \a\t H:i', strtotime( $leg_bp_time ) ) : '',
						'tickets'        => array(),
						'extra_services' => array_values( array_filter( $row_services ) ),
						'subtotal'       => (float) get_post_meta( $id, 'wbtm_tp', true ),
					);
				}
				$legs[ $leg_key ]['tickets'][] = $ticket;
			}

			// Present outbound before return regardless of query order.
			uksort(
				$legs,
				static function ( $a, $b ) {
					$order = array( 'departure' => 0, 'return' => 1 );
					return ( $order[ $a ] ?? 9 ) <=> ( $order[ $b ] ?? 9 );
				}
			);

			$bp_time = get_post_meta( $booking_id, 'wbtm_boarding_time', true );

			return array(
				'booking_id'     => (int) $booking_id,
				'bus_id'         => $bus_id,
				'bus_name'       => get_the_title( $bus_id ),
				'boarding_point' => get_post_meta( $booking_id, 'wbtm_boarding_point', true ),
				'dropping_point' => get_post_meta( $booking_id, 'wbtm_dropping_point', true ),
				'journey_date'   => $bp_time ? date_i18n( 'D, j M Y \a\t H:i', strtotime( $bp_time ) ) : '',
				'tickets'        => $tickets,
				'extra_services' => array_values( array_filter( $extra_services ) ),
				'total'          => array_sum( $leg_totals ),
				'legs'           => array_values( $legs ),
				'is_round_trip'  => count( $legs ) > 1,
				'status'         => get_post_meta( $booking_id, 'wbtm_order_status', true ),
				'customer_name'  => get_post_meta( $booking_id, 'wbtm_user_name', true ),
				'customer_email' => get_post_meta( $booking_id, 'wbtm_user_email', true ),
				'customer_phone' => get_post_meta( $booking_id, 'wbtm_user_phone', true ),
			);
		}

		private function mark_group_status( $booking_id, $status ) {
			$bus_id = 0;
			foreach ( self::get_group_post_ids( $booking_id ) as $id ) {
				update_post_meta( $id, 'wbtm_order_status', $status );
				delete_post_meta( $id, 'wbtm_awaiting_gateway' );
				if ( ! $bus_id ) {
					$bus_id = (int) get_post_meta( $id, 'wbtm_bus_id', true );
				}
			}
			$order_id = get_post_meta( $booking_id, 'wbtm_order_id', true ) ?: $booking_id;
			do_action( 'wbtm_order_status_change', $status, $bus_id, $order_id );

			// Applied standalone coupon: count usage only once a payment actually
			// settles (processing/on-hold); on failure/cancellation drop it without
			// recording so the customer can reuse the code on a retry.
			if ( class_exists( 'WBTM_Standalone_Coupon' ) && class_exists( 'WBTM_Coupon_Module' ) ) {
				$coupon = WBTM_Standalone_Coupon::get_group_coupon( $booking_id );
				if ( $coupon && in_array( $status, array( 'processing', 'on-hold' ), true ) ) {
					if ( empty( $coupon['recorded'] ) ) {
						$coupon_post_id = WBTM_Coupon_Module::find_by_code( $coupon['code'] );
						if ( $coupon_post_id ) {
							$user_id  = (int) get_post_meta( $booking_id, 'wbtm_user_id', true );
							$identity = $user_id ? 'u:' . $user_id : 'e:' . strtolower( trim( (string) get_post_meta( $booking_id, 'wbtm_user_email', true ) ) );
							WBTM_Coupon_Module::increment_usage( $coupon_post_id, $identity, $coupon['discount'] );
							$coupon['recorded'] = 1;
							update_post_meta( $booking_id, WBTM_Standalone_Coupon::COUPON_META, $coupon );
						}
					}
				} elseif ( $coupon && in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
					delete_post_meta( $booking_id, WBTM_Standalone_Coupon::COUPON_META );
				}
			}

			if ( class_exists( 'WBTM_Standalone_Mail' ) ) {
				WBTM_Standalone_Mail::maybe_send_status_emails( $booking_id, $status );
			}
		}

		/**
		 * The "pay for your booking" page — where get_payment_url() sends the
		 * customer, and where the gateway redirects back to (handle_payment_return()
		 * intercepts that on init and redirects onward before this page ever renders,
		 * so which page return_url/cancel_url name doesn't otherwise matter).
		 */
		public static function get_checkout_page_url() {
			$checkout_page_id = (int) get_option( 'wbtm_checkout_page_id' );
			if ( $checkout_page_id && get_post_status( $checkout_page_id ) ) {
				return get_permalink( $checkout_page_id );
			}
			return home_url( '/' );
		}

		/**
		 * The "Booking Confirmation Page" set in Payments settings — where the
		 * customer actually lands after payment, success or not. Auto-created and
		 * pre-selected on Pro activation (see wbtm-pro.php); this fallback only
		 * matters if that page was later deleted or the setting was cleared.
		 */
		public static function get_confirmation_page_url() {
			$confirmation_page_id = (int) ( get_option( 'wbtm_payment_settings', array() )['wbtm_confirmation_page_id'] ?? 0 );
			if ( $confirmation_page_id && get_post_status( $confirmation_page_id ) ) {
				return get_permalink( $confirmation_page_id );
			}
			return home_url( '/' );
		}

		public static function get_result_page_url( $booking_id, $result ) {
			return add_query_arg(
				array(
					'booking_id'          => $booking_id,
					'wbtm_booking_result' => $result,
				),
				self::get_checkout_page_url()
			);
		}

		private function redirect_to_result( $booking_id, $result ) {
			wp_safe_redirect( self::get_result_page_url( $booking_id, $result ) );
			exit;
		}
	}
	new WBTM_Standalone_Payment();
}
