<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * Fallback for WooCommerce's own wc_price() when WooCommerce is inactive.
 * Several Pro-plugin admin screens and templates (Passenger List, Reports,
 * the [view-ticket] shortcode) call wc_price() directly rather than through
 * WBTM_Global_Function::format_price()'s WC-optional wrapper; defining this
 * only when missing keeps them from fataling instead of patching every call
 * site individually. Matches format_price()'s own WC-inactive fallback.
 *
 * Deferred to plugins_loaded (rather than declared at this file's own parse
 * time) because active plugins' main files are require()'d in the order
 * they appear in the 'active_plugins' option, not alphabetically — if this
 * plugin's turn came before WooCommerce's, function_exists('wc_price') would
 * still be false here even with WooCommerce active, we'd declare our stub
 * first, and WooCommerce's own unguarded wc_price() declaration would then
 * fatal with "Cannot redeclare". Every active plugin's main file has already
 * been require()'d by the time plugins_loaded fires, so checking there is
 * load-order-independent.
 *
 * We ALSO skip the stub whenever WooCommerce is present on disk (installed but
 * not necessarily active). During WooCommerce *activation*, WordPress runs
 * plugin_sandbox_scrape() which include()s woocommerce.php mid-request — long
 * after this plugins_loaded callback already ran with WC still inactive. Had we
 * declared the stub then, WC's own unguarded wc_price() would fatal on that
 * include. Once WooCommerce is installed the site is on its way to using it, so
 * deferring to WC's real wc_price() (present as soon as it loads) is correct.
 */
add_action( 'plugins_loaded', function () {
	if ( function_exists( 'wc_price' ) ) {
		return;
	}
	if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		return; // WooCommerce installed — its real wc_price() will load; don't collide with it.
	}
	function wc_price( $price, $args = array() ) {
		return number_format( (float) $price, 2 );
	}
} );

/**
 * Booking-record helpers that have no real WooCommerce dependency: seat/trip
 * advisory locks, availability validation, cart-style $_POST parsing for
 * tickets/cabin seats/extra services, and generic CPT insertion. Always loaded
 * (unlike WBTM_Woocommerce.php, which is only required when WooCommerce is
 * active) so both the WooCommerce cart flow and the WC-independent
 * Standalone/Custom Payment flow can use the same logic.
 *
 * WBTM_Woocommerce keeps its original public method names as thin delegating
 * wrappers around these, so every existing WC-flow caller keeps working
 * unchanged.
 */
if ( ! class_exists( 'WBTM_Cart_Helper' ) ) {
	class WBTM_Cart_Helper {

		public static function get_trip_lock_key( $post_id, $bp, $dp, $date ) {
			global $wpdb;
			$date = $date ? gmdate( 'Y-m-d', strtotime( $date ) ) : '';
			return substr( $wpdb->prefix . 'wbtm_lock_' . md5( $post_id . '|' . $bp . '|' . $dp . '|' . $date ), 0, 64 );
		}

		/**
		 * Acquire a MySQL advisory lock for a trip.
		 *
		 * @param int    $post_id Bus post ID.
		 * @param string $bp      Boarding point.
		 * @param string $dp      Dropping point.
		 * @param string $date    Journey date.
		 */
		public static function acquire_trip_lock( $post_id, $bp, $dp, $date, $timeout = 30 ) {
			global $wpdb;
			$key    = self::get_trip_lock_key( $post_id, $bp, $dp, $date );
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, %d)", $key, $timeout ) );
			// If the DB does not support advisory locks (NULL), fall back to no lock rather than failing the order.
			if ( null === $result ) {
				return true;
			}
			return ( 1 === (int) $result );
		}

		/**
		 * Release a MySQL advisory lock for a trip.
		 *
		 * @param int    $post_id Bus post ID.
		 * @param string $bp      Boarding point.
		 * @param string $dp      Dropping point.
		 * @param string $date    Journey date.
		 */
		public static function release_trip_lock( $post_id, $bp, $dp, $date ) {
			global $wpdb;
			$key = self::get_trip_lock_key( $post_id, $bp, $dp, $date );
			$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $key ) );
		}

		/**
		 * Verify that the requested seats/tickets are still available.
		 *
		 * Supports full-bus, seat-plan (legacy/cabin) and without-seat-plan modes.
		 *
		 * @param int    $post_id           Bus post ID.
		 * @param string $bp                Boarding point.
		 * @param string $dp                Dropping point.
		 * @param string $date              Journey date.
		 * @param string $booking_mode      'full_bus' or 'seat'.
		 * @param array  $ticket_infos      Legacy ticket/seat list.
		 * @param array  $cabin_seats       Cabin-specific seat list.
		 * @param int    $full_bus_seat_count Number of seats covered by a full-bus booking.
		 * @return true|WP_Error
		 */
		public static function validate_bus_availability( $post_id, $bp, $dp, $date, $booking_mode = 'seat', $ticket_infos = [], $cabin_seats = [], $full_bus_seat_count = 0 ) {
			if ( get_post_type( $post_id ) != WBTM_Functions::get_cpt() ) {
				return true;
			}
			// Enforce the ticket-sale cut-off (and past-departure): a departure whose
			// sale deadline has passed can't be booked, even via a crafted request that
			// bypasses the hidden search UI. Covers the WooCommerce, Standalone and
			// backend (counter) flows, since they all validate through this method.
			if ( $date && ! WBTM_Functions::is_ticket_sale_open( $date ) ) {
				return new WP_Error(
					'wbtm_sale_closed',
					esc_html__( 'Ticket sales for this trip are closed.', 'bus-ticket-booking-with-seat-reservation' )
				);
			}
			$booking_mode = $booking_mode === 'full_bus' ? 'full_bus' : 'seat';
			// Reject segments with no fare configured so they can't be booked at 0,
			// even via a crafted request that bypasses the hidden UI. Full-bus pricing
			// is validated separately, so this guard applies to seat bookings only.
			if ( $booking_mode === 'seat'
				&& ! WBTM_Functions::route_price_configured( $post_id, $bp, $dp, 'outbound' )
				&& ! WBTM_Functions::route_price_configured( $post_id, $bp, $dp, 'return' ) ) {
				return new WP_Error(
					'wbtm_route_unpriced',
					esc_html__( 'This route is not currently available for booking.', 'bus-ticket-booking-with-seat-reservation' )
				);
			}
			if ( $booking_mode === 'full_bus' ) {
				$total_seat = class_exists( 'WBTM_Seat_Configuration' ) ? WBTM_Seat_Configuration::count_actual_seats( $post_id ) : (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
				$sold_seat  = WBTM_Query::query_total_booked( $post_id, $bp, $dp, $date );
				if ( $sold_seat > 0 || (int) $full_bus_seat_count > $total_seat ) {
					return new WP_Error(
						'wbtm_full_bus_unavailable',
						esc_html__( 'Sorry, this full bus is no longer available.', 'bus-ticket-booking-with-seat-reservation' )
					);
				}
				return true;
			}
			$seat_type = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
			if ( $seat_type == 'wbtm_seat_plan' ) {
				$seat_infos = ! empty( $cabin_seats ) ? $cabin_seats : $ticket_infos;
				if ( is_array( $seat_infos ) && sizeof( $seat_infos ) > 0 ) {
					foreach ( $seat_infos as $seat_info ) {
						$seat_name = array_key_exists( 'seat_name', $seat_info ) ? $seat_info['seat_name'] : '';
						if ( ! $seat_name ) {
							continue;
						}
						$cabin_index = array_key_exists( 'cabin_index', $seat_info ) ? $seat_info['cabin_index'] : '';
						// Prefer the pre-built deck-scoped identifier (lower vs upper
						// deck of a double-decker cabin); fall back to the legacy
						// lower-deck form for entries that predate it.
						if ( ! empty( $seat_info['seat_identifier'] ) ) {
							$check_seat = $seat_info['seat_identifier'];
						} else {
							$check_seat = ( $cabin_index !== '' ) ? 'cabin_' . $cabin_index . '_' . $seat_name : $seat_name;
						}
						if ( WBTM_Query::query_total_booked( $post_id, $bp, $dp, $date, '', $check_seat ) > 0 ) {
							return new WP_Error(
								'wbtm_seat_unavailable',
								esc_html__( 'Sorry, your selected seat is already booked by another user.', 'bus-ticket-booking-with-seat-reservation' )
							);
						}
					}
				}
				return true;
			}
			// Without seat plan: validate total ticket quantity against remaining capacity.
			$total_seat = class_exists( 'WBTM_Seat_Configuration' ) ? WBTM_Seat_Configuration::count_actual_seats( $post_id ) : (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
			$sold_seat  = WBTM_Query::query_total_booked( $post_id, $bp, $dp, $date );
			$total_qty  = 0;
			if ( is_array( $ticket_infos ) && sizeof( $ticket_infos ) > 0 ) {
				foreach ( $ticket_infos as $ticket_info ) {
					$total_qty += isset( $ticket_info['ticket_qty'] ) ? max( 1, intval( $ticket_info['ticket_qty'] ) ) : 1;
				}
			}
			$available_seat = max( 0, $total_seat - $sold_seat );
			if ( $available_seat < $total_qty ) {
				return new WP_Error(
					'wbtm_tickets_unavailable',
					esc_html__( 'Sorry, your selected ticket quantity is no longer available.', 'bus-ticket-booking-with-seat-reservation' )
				);
			}
			return true;
		}

		public static function get_cart_cabin_seat_info( $post_id, $cabin_config ) {
			$cabin_seats = [];
			if ( isset( $_POST['wbtm_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_form_nonce'] ) ), 'wbtm_form_nonce' ) ) {
				// Get ticket information for price calculation
				$bp = isset( $_POST['wbtm_bp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ) ) : '';
				$dp = isset( $_POST['wbtm_dp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ) ) : '';
				$journey_date = isset( $_POST['wbtm_bp_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ) ) : '';
				$price_leg_cart = WBTM_Functions::get_requested_price_leg();
				foreach ( $cabin_config as $cabin_index => $cabin ) {
					if ( ( $cabin['enabled'] ?? 'yes' ) !== 'yes' )
						continue;
					$price_multiplier = $cabin['price_multiplier'] ?? 1.0;
					// A cabin can be a double-decker coach: gather selections from
					// both its lower deck and (when present) its upper deck. Each
					// resulting entry carries a deck-scoped seat_identifier so the
					// two decks never collide downstream (availability/cart/order).
					$deck_sources = [
						[ 'is_upper' => false, 'seat_key' => 'wbtm_selected_seat_cabin_' . $cabin_index, 'type_key' => 'wbtm_selected_seat_type_cabin_' . $cabin_index ],
						[ 'is_upper' => true,  'seat_key' => 'wbtm_selected_seat_cabin_dd_' . $cabin_index, 'type_key' => 'wbtm_selected_seat_type_cabin_dd_' . $cabin_index ],
					];
					$upper_multiplier = isset( $cabin['upper_price_multiplier'] ) ? (float) $cabin['upper_price_multiplier'] : 1.0;
					foreach ( $deck_sources as $deck_source ) {
						$selected_seats = isset( $_POST[ $deck_source['seat_key'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $deck_source['seat_key'] ] ) ) : '';
						if ( ! $selected_seats ) {
							continue;
						}
						$is_upper = $deck_source['is_upper'];
						// Each deck is priced by its own multiplier (upper can differ from lower).
						$deck_multiplier = $is_upper ? $upper_multiplier : $price_multiplier;
						$selected_seat_types = isset( $_POST[ $deck_source['type_key'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $deck_source['type_key'] ] ) ) : '';
						$seat_names = explode( ',', $selected_seats );
						$seat_types = $selected_seat_types ? explode( ',', $selected_seat_types ) : [];
						foreach ( $seat_names as $seat_index => $seat_name ) {
							$seat_type = isset( $seat_types[ $seat_index ] ) ? $seat_types[ $seat_index ] : 0;
							$base_price = WBTM_Functions::get_seat_price( $post_id, $bp, $dp, $seat_type, false, $price_leg_cart, $seat_name, $cabin_index, $journey_date );
							if ( $base_price === false || $base_price < 0 ) {
								$base_price = 0;
							}
							$ticket_price = floatval( $base_price ) * floatval( $deck_multiplier );
							$cabin_seats[] = [
								'cabin_index' => $cabin_index,
								'cabin_name' => $cabin['name'] ?? 'Cabin ' . ( $cabin_index + 1 ),
								'deck' => $is_upper ? 'upper' : 'lower',
								'is_upper' => $is_upper,
								'seat_name' => $seat_name,
								'seat_identifier' => WBTM_Functions::cabin_seat_identifier( $cabin_index, $seat_name, $is_upper ),
								'seat_type' => $seat_type,
								'ticket_name' => WBTM_Functions::get_ticket_name( $seat_type, $post_id ),
								'ticket_price' => $ticket_price,
								'price_multiplier' => $deck_multiplier
							];
						}
					}
				}
			}
			return $cabin_seats;
		}

		public static function get_cart_ticket_info( $post_id ) {
			$ticket_info = [];
			if ( isset( $_POST['wbtm_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_form_nonce'] ) ), 'wbtm_form_nonce' ) ) {
				$price_leg = WBTM_Functions::get_requested_price_leg();
				$seat_type = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
				$seat_infos = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_seats_info', [] );
				$seat_row = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_rows', 0 );
				$seat_column = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_cols', 0 );
				$ticket_types = WBTM_Functions::get_ticket_types( $post_id );
				$default_ticket_type = array_key_exists( 0, $ticket_types ) ? $ticket_types[0]['id'] : 'adult';
				/************************/
				$start_place = isset( $_POST['wbtm_bp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_place'] ) ) : '';
				$end_place = isset( $_POST['wbtm_dp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_dp_place'] ) ) : '';
				$start_date = isset( $_POST['wbtm_bp_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ) ) : '';
				if ( $seat_type == 'wbtm_seat_plan' && sizeof( $seat_infos ) > 0 && $seat_row > 0 && $seat_column > 0 ) {
					$count = 0;
					$selected_seat = isset( $_POST['wbtm_selected_seat'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat'] ) ) : '';
					$selected_seat = $selected_seat ? explode( ',', $selected_seat ) : [];
					$selected_ticket_type = isset( $_POST['wbtm_selected_seat_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat_type'] ) ) : '';
					$selected_ticket_type = $selected_ticket_type ? explode( ',', $selected_ticket_type ) : [ $default_ticket_type ];
					if ( sizeof( $selected_seat ) > 0 && sizeof( $selected_ticket_type ) > 0 ) {
						foreach ( $selected_seat as $key => $seat_name ) {
							$type = isset( $selected_ticket_type[ $key ] ) ? $selected_ticket_type[ $key ] : $default_ticket_type;
							if ( $seat_name ) {
								$seat_price = WBTM_Functions::get_seat_price( $post_id, $start_place, $end_place, $type, false, $price_leg, $seat_name, null, $start_date );
								// Handle false return value from get_seat_price
								if ( $seat_price === false || $seat_price < 0 ) {
									$seat_price = 0;
								}
								$ticket_info[ $count ]['ticket_name'] = WBTM_Functions::get_ticket_name( $type, $post_id );
								$ticket_info[ $count ]['ticket_type'] = $type;
								$ticket_info[ $count ]['seat_name'] = $seat_name;
								$ticket_info[ $count ]['ticket_price'] = floatval( $seat_price );
								$ticket_info[ $count ]['ticket_qty'] = 1;
								$ticket_info[ $count ]['date'] = $start_date ?? '';
								$ticket_info[ $count ]['dd'] = '';
								$count++;
							}
						}
					}
					$selected_seat_dd = isset( $_POST['wbtm_selected_seat_dd'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat_dd'] ) ) : '';
					$selected_seat_dd = $selected_seat_dd ? explode( ',', $selected_seat_dd ) : [];
					$selected_ticket_type_dd = isset( $_POST['wbtm_selected_seat_dd_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_selected_seat_dd_type'] ) ) : '';
					$selected_ticket_type_dd = $selected_ticket_type_dd ? explode( ',', $selected_ticket_type_dd ) : [ $default_ticket_type ];
					if ( sizeof( $selected_seat_dd ) > 0 && sizeof( $selected_ticket_type_dd ) > 0 ) {
						foreach ( $selected_seat_dd as $key => $seat_name ) {
							$type = isset( $selected_ticket_type_dd[ $key ] ) ? $selected_ticket_type_dd[ $key ] : $default_ticket_type;
							if ( $seat_name ) {
								$seat_price = WBTM_Functions::get_seat_price( $post_id, $start_place, $end_place, $type, true, $price_leg, $seat_name, null, $start_date );
								// Handle false return value from get_seat_price
								if ( $seat_price === false || $seat_price < 0 ) {
									$seat_price = 0;
								}
								$ticket_info[ $count ]['ticket_name'] = WBTM_Functions::get_ticket_name( $type, $post_id );
								$ticket_info[ $count ]['ticket_type'] = $type;
								$ticket_info[ $count ]['seat_name'] = $seat_name;
								$ticket_info[ $count ]['ticket_price'] = floatval( $seat_price );
								$ticket_info[ $count ]['ticket_qty'] = 1;
								$ticket_info[ $count ]['date'] = $start_date ?? '';
								$ticket_info[ $count ]['dd'] = 1;
								$count++;
							}
						}
					}
				} else {
					// Without seat plan mode
					$qty = isset( $_POST['wbtm_seat_qty'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wbtm_seat_qty'] ) ) : [];
					$passenger_type = isset( $_POST['wbtm_passenger_type'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wbtm_passenger_type'] ) ) : [];
					// NOTE: the browser also posts wbtm_seat_price[], but it is deliberately
					// never read — every price below is resolved server-side from the
					// route's own fare table so a tampered form cannot set the amount.
					$total_types = count( $passenger_type );
					// Use a monotonic $count instead of the loop index $i: when a
					// passenger type has qty 0 it is skipped, so indexing by $i left
					// gaps in $ticket_info (e.g. key 0 missing) that later triggered
					// "Undefined array key ticket_name" when the order was created.
					$count = 0;
					if ( $total_types > 0 && is_array( $qty ) ) {
						for ( $i = 0; $i < $total_types; $i++ ) {
							if ( isset( $qty[ $i ] ) && $qty[ $i ] > 0 ) {
								$type = $passenger_type[ $i ] ?? '';
								$ticket_name = WBTM_Functions::get_ticket_name( $type, $post_id );
								$seat_price = WBTM_Functions::get_seat_price( $post_id, $start_place, $end_place, $type, false, $price_leg, '', null, $start_date );
								// Reject ticket if the route price cannot be determined — never trust user-submitted price.
								if ( $seat_price === false || $seat_price < 0 ) {
									continue;
								}
								$ticket_info[ $count ]['ticket_name'] = $ticket_name;
								$ticket_info[ $count ]['seat_name'] = $ticket_name;
								$ticket_info[ $count ]['ticket_type'] = $type;
								$ticket_info[ $count ]['ticket_price'] = floatval( $seat_price );
								$ticket_info[ $count ]['ticket_qty'] = intval( $qty[ $i ] );
								$ticket_info[ $count ]['date'] = $start_date ?? '';
								$count++;
							}
						}
					}
				}
			}
			$ticket_info = apply_filters( 'wbtm_cart_ticket_info_data_prepare', $ticket_info, $post_id );
			// Defensively guarantee every ticket entry has the keys the order
			// creation code reads unconditionally (ticket_name, ticket_qty,
			// ticket_price). Filter callbacks or sparse legacy data could otherwise
			// leave them unset and trigger PHP warnings at checkout.
			if ( is_array( $ticket_info ) ) {
				$default_name = WBTM_Functions::get_ticket_name( '', $post_id );
				foreach ( $ticket_info as $k => $row ) {
					if ( ! is_array( $row ) ) {
						unset( $ticket_info[ $k ] );
						continue;
					}
					$ticket_info[ $k ]['ticket_name']  = $row['ticket_name']  ?? $default_name;
					$ticket_info[ $k ]['ticket_qty']   = $row['ticket_qty']   ?? 1;
					$ticket_info[ $k ]['ticket_price'] = $row['ticket_price'] ?? 0;
				}
				$ticket_info = array_values( $ticket_info );
			}
			return $ticket_info;
		}

		public static function get_cart_extra_service_info( $post_id ): array {
			$extra_service = array();
			if ( isset( $_POST['wbtm_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_form_nonce'] ) ), 'wbtm_form_nonce' ) ) {
				$start_date = isset( $_POST['wbtm_bp_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bp_time'] ) ) : '';
				$service_name = isset( $_POST['extra_service_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['extra_service_name'] ) ) : [];
				$service_qty = isset( $_POST['extra_service_qty'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['extra_service_qty'] ) ) : [];
				if ( sizeof( $service_name ) > 0 ) {
					for ( $i = 0; $i < count( $service_name ); $i++ ) {
						if ( $service_qty[ $i ] > 0 ) {
							$name = $service_name[ $i ] ?? '';
							$extra_service[ $i ]['name'] = $name;
							$extra_service[ $i ]['price'] = WBTM_Functions::get_ex_service_price( $post_id, $name );
							$extra_service[ $i ]['qty'] = $service_qty[ $i ];
							// Charging mode travels with the selection so every
							// downstream consumer (cart, checkout recalc, standalone
							// payment, order/e-voucher display, exports, reports) can
							// price it consistently without re-querying the bus meta.
							$extra_service[ $i ]['charge_type'] = WBTM_Functions::get_ex_service_charge_type( $post_id, $name );
							$extra_service[ $i ]['date'] = $start_date ?? '';
						}
					}
				}
			}
			return $extra_service;
		}

		public static function add_cpt_data( $cpt_name, $title, $meta_data = array(), $status = 'publish', $cat = array() ) {
			// Generate a privacy-safe post title and slug for bus bookings
			if ( $cpt_name === 'wbtm_bus_booking' ) {
				// Use order ID and timestamp for privacy-safe identification
				$order_id = isset( $meta_data['wbtm_order_id'] ) ? $meta_data['wbtm_order_id'] : '';
				$timestamp = current_time( 'Y-m-d-H-i-s' );
				$safe_title = 'Bus Booking #' . $order_id . '-' . $timestamp;
				$new_post = array(
					'post_title' => $safe_title,
					'post_name' => 'bus-booking-' . $order_id . '-' . $timestamp, // Explicitly set slug
					'post_content' => '',
					'post_category' => $cat,
					'tags_input' => array(),
					'post_status' => $status,
					'post_type' => $cpt_name
				);
			} else {
				// For other post types, use original behavior
				$new_post = array(
					'post_title' => $title,
					'post_content' => '',
					'post_category' => $cat,
					'tags_input' => array(),
					'post_status' => $status,
					'post_type' => $cpt_name
				);
			}
			$post_id = wp_insert_post( $new_post );
			if ( sizeof( $meta_data ) > 0 ) {
				foreach ( $meta_data as $key => $value ) {
					update_post_meta( $post_id, $key, $value );
				}
			}
			return $post_id;
		}
	}
}
