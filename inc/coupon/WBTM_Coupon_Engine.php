<?php
	/**
	 * Coupon Engine — validation + discount calculation.
	 *
	 * Pure decision logic: given a coupon post and the current cart, it decides
	 * whether the coupon applies and how large the discount is. It never writes
	 * state (usage counters are written by WBTM_Coupon_Cart on order creation),
	 * so it is safe to call on every `woocommerce_cart_calculate_fees` pass — the
	 * discount can never drift from the live cart or outlive its own rules.
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_Coupon_Engine' ) ) {
		class WBTM_Coupon_Engine {

			/** Standardised failure result. */
			private static function fail( $message ) {
				return array(
					'valid'          => false,
					'message'        => $message,
					'discount'       => 0.0,
					'eligible_total' => 0.0,
					'eligible_seats' => 0,
				);
			}

			/** Standardised success result. */
			private static function ok( $discount, $eligible_total, $eligible_seats ) {
				return array(
					'valid'          => true,
					'message'        => '',
					'discount'       => (float) $discount,
					'eligible_total' => (float) $eligible_total,
					'eligible_seats' => (int) $eligible_seats,
				);
			}

			/**
			 * The stable identity used for per-user usage limits: the account ID
			 * for logged-in users, else the lower-cased billing/session email.
			 */
			public static function current_identity( $email = '' ) {
				if ( is_user_logged_in() ) {
					return 'u:' . get_current_user_id();
				}
				$email = strtolower( trim( (string) $email ) );
				return $email ? 'e:' . $email : '';
			}

			/**
			 * Evaluate a coupon against a WooCommerce cart.
			 *
			 * @param int      $post_id Coupon post ID.
			 * @param WC_Cart  $cart    The cart being calculated.
			 * @param string   $email   Optional billing email (guest per-user limits).
			 * @return array{valid:bool,message:string,discount:float,eligible_total:float,eligible_seats:int}
			 */
			public static function evaluate( $post_id, $cart, $email = '' ) {
				$post_id = (int) $post_id;
				if ( ! $post_id || get_post_type( $post_id ) !== WBTM_Coupon_Module::CPT || get_post_status( $post_id ) !== 'publish' ) {
					return self::fail( esc_html__( 'This coupon code is not valid.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				if ( WBTM_Coupon_Module::is_on( $post_id, 'disabled' ) ) {
					return self::fail( esc_html__( 'This coupon is no longer available.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				/* ---- Schedule: active date window ---- */
				$now   = current_time( 'timestamp' );
				$start = WBTM_Coupon_Module::get( $post_id, 'date_start', '' );
				$end   = WBTM_Coupon_Module::get( $post_id, 'date_end', '' );
				if ( $start && $now < strtotime( $start . ' 00:00:00' ) ) {
					return self::fail( esc_html__( 'This coupon is not active yet.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				if ( $end && $now > strtotime( $end . ' 23:59:59' ) ) {
					return self::fail( esc_html__( 'This coupon has expired.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				/* ---- Schedule: day of week ---- */
				$days = WBTM_Coupon_Module::get_array( $post_id, 'days_of_week' );
				if ( ! empty( $days ) ) {
					$today = (string) (int) date_i18n( 'w', $now ); // 0 (Sun) .. 6 (Sat)
					if ( ! in_array( $today, array_map( 'strval', $days ), true ) ) {
						return self::fail( esc_html__( 'This coupon cannot be used today.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}

				/* ---- Eligibility: login / roles / users ---- */
				$login_required     = WBTM_Coupon_Module::is_on( $post_id, 'login_required' );
				$first_booking_only = WBTM_Coupon_Module::is_on( $post_id, 'first_booking_only' );
				$allowed_roles      = WBTM_Coupon_Module::get_array( $post_id, 'allowed_roles' );
				$allowed_emails     = WBTM_Coupon_Module::get_array( $post_id, 'allowed_emails' );

				if ( ( $login_required || $first_booking_only || ! empty( $allowed_roles ) ) && ! is_user_logged_in() ) {
					return self::fail( esc_html__( 'Please log in to use this coupon.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				if ( ! empty( $allowed_roles ) && is_user_logged_in() ) {
					$user  = wp_get_current_user();
					$roles = (array) $user->roles;
					if ( ! array_intersect( $roles, $allowed_roles ) ) {
						return self::fail( esc_html__( 'This coupon is not available for your account.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}
				if ( ! empty( $allowed_emails ) ) {
					$check_email = is_user_logged_in() ? wp_get_current_user()->user_email : $email;
					$check_email = strtolower( trim( (string) $check_email ) );
					$allowed_lc  = array_map( 'strtolower', array_map( 'trim', $allowed_emails ) );
					if ( ! $check_email || ! in_array( $check_email, $allowed_lc, true ) ) {
						return self::fail( esc_html__( 'This coupon is reserved for specific customers.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}
				if ( $first_booking_only && self::user_has_previous_bus_order() ) {
					return self::fail( esc_html__( 'This coupon is only valid on your first booking.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				/* ---- Usage limits ---- */
				$limit_total = WBTM_Coupon_Module::get_int( $post_id, 'usage_limit_total', 0 );
				if ( $limit_total > 0 && WBTM_Coupon_Module::get_int( $post_id, 'used_count', 0 ) >= $limit_total ) {
					return self::fail( esc_html__( 'This coupon has reached its usage limit.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				$limit_user = WBTM_Coupon_Module::get_int( $post_id, 'usage_limit_per_user', 0 );
				if ( $limit_user > 0 ) {
					$identity = self::current_identity( $email );
					$user_log = WBTM_Coupon_Module::get_array( $post_id, 'user_log' );
					if ( $identity && isset( $user_log[ $identity ] ) && (int) $user_log[ $identity ] >= $limit_user ) {
						return self::fail( esc_html__( 'You have already used this coupon the maximum number of times.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}
				$limit_day = WBTM_Coupon_Module::get_int( $post_id, 'usage_limit_per_day', 0 );
				if ( $limit_day > 0 ) {
					$day_log = WBTM_Coupon_Module::get_array( $post_id, 'day_log' );
					$today   = date_i18n( 'Y-m-d', $now );
					if ( isset( $day_log[ $today ] ) && (int) $day_log[ $today ] >= $limit_day ) {
						return self::fail( esc_html__( 'This coupon has reached today\'s usage limit.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}

				/* ---- Eligible cart items (targeting + travel window) ---- */
				$travel_start = WBTM_Coupon_Module::get( $post_id, 'travel_start', '' );
				$travel_end   = WBTM_Coupon_Module::get( $post_id, 'travel_end', '' );
				$travel_start_ts = $travel_start ? strtotime( $travel_start . ' 00:00:00' ) : 0;
				$travel_end_ts   = $travel_end ? strtotime( $travel_end . ' 23:59:59' ) : 0;

				$eligible_total = 0.0;
				$eligible_seats = 0;
				$has_bus_item   = false;

				foreach ( $cart->get_cart() as $cart_item ) {
					$parsed = WBTM_Coupon_Module::read_cart_item( $cart_item );
					if ( $parsed === null ) {
						continue;
					}
					$has_bus_item = true;
					if ( ! self::item_is_targeted( $post_id, $parsed['bus_id'] ) ) {
						continue;
					}
					// Travel-date window (only counts items whose journey falls inside it).
					if ( $travel_start_ts && ( ! $parsed['journey_ts'] || $parsed['journey_ts'] < $travel_start_ts ) ) {
						continue;
					}
					if ( $travel_end_ts && ( ! $parsed['journey_ts'] || $parsed['journey_ts'] > $travel_end_ts ) ) {
						continue;
					}
					$eligible_total += $parsed['line_total'];
					$eligible_seats += $parsed['seat_qty'];
				}

				if ( ! $has_bus_item ) {
					return self::fail( esc_html__( 'Add a bus ticket to your cart before applying this coupon.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				if ( $eligible_total <= 0 || $eligible_seats <= 0 ) {
					return self::fail( esc_html__( 'This coupon does not apply to the tickets in your cart.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				/* ---- Amount restrictions ---- */
				$min_spend = WBTM_Coupon_Module::get_float( $post_id, 'min_spend', 0 );
				if ( $min_spend > 0 && $eligible_total < $min_spend ) {
					return self::fail( sprintf(
						/* translators: %s: formatted minimum spend amount */
						esc_html__( 'A minimum eligible fare of %s is required for this coupon.', 'bus-ticket-booking-with-seat-reservation' ),
						wp_strip_all_tags( wc_price( $min_spend ) )
					) );
				}
				$min_seats = WBTM_Coupon_Module::get_int( $post_id, 'min_seats', 0 );
				if ( $min_seats > 0 && $eligible_seats < $min_seats ) {
					return self::fail( sprintf(
						/* translators: %d: minimum number of seats */
						esc_html__( 'Book at least %d seat(s) to use this coupon.', 'bus-ticket-booking-with-seat-reservation' ),
						$min_seats
					) );
				}
				$max_seats = WBTM_Coupon_Module::get_int( $post_id, 'max_seats', 0 );
				if ( $max_seats > 0 && $eligible_seats > $max_seats ) {
					return self::fail( sprintf(
						/* translators: %d: maximum number of seats */
						esc_html__( 'This coupon is valid for up to %d seat(s).', 'bus-ticket-booking-with-seat-reservation' ),
						$max_seats
					) );
				}

				/* ---- Discount calculation ---- */
				$discount = self::calc_discount( $post_id, $eligible_total, $eligible_seats );
				if ( $discount <= 0 ) {
					return self::fail( esc_html__( 'This coupon does not produce a discount on your cart.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				return self::ok( $discount, $eligible_total, $eligible_seats );
			}

			/** Compute the raw discount amount, capped to the eligible fare. */
			public static function calc_discount( $post_id, $eligible_total, $eligible_seats ) {
				$type   = WBTM_Coupon_Module::get( $post_id, 'discount_type', 'percent' );
				$amount = WBTM_Coupon_Module::get_float( $post_id, 'amount', 0 );
				$discount = 0.0;

				switch ( $type ) {
					case 'percent':
						$pct      = min( 100.0, $amount );
						$discount = $eligible_total * ( $pct / 100 );
						$cap      = WBTM_Coupon_Module::get_float( $post_id, 'max_discount', 0 );
						if ( $cap > 0 && $discount > $cap ) {
							$discount = $cap;
						}
						break;
					case 'fixed_seat':
						$discount = $amount * max( 1, (int) $eligible_seats );
						break;
					case 'fixed_booking':
					default:
						$discount = $amount;
						break;
				}

				// Never discount more than the eligible fare itself.
				$discount = min( $discount, $eligible_total );
				$discount = max( 0.0, $discount );
				return round( $discount, wc_get_price_decimals() );
			}

			/** Does the coupon's targeting include this bus? */
			public static function item_is_targeted( $post_id, $bus_id ) {
				if ( WBTM_Coupon_Module::get( $post_id, 'apply_to', 'all' ) !== 'specific' ) {
					return true;
				}
				$bus_ids = array_map( 'intval', WBTM_Coupon_Module::get_array( $post_id, 'bus_ids' ) );
				if ( $bus_ids && in_array( (int) $bus_id, $bus_ids, true ) ) {
					return true;
				}
				$cats = array_map( 'intval', WBTM_Coupon_Module::get_array( $post_id, 'bus_cats' ) );
				if ( $cats ) {
					$terms = wp_get_post_terms( (int) $bus_id, 'wbtm_bus_cat', array( 'fields' => 'ids' ) );
					if ( ! is_wp_error( $terms ) && array_intersect( $cats, array_map( 'intval', $terms ) ) ) {
						return true;
					}
				}
				return false;
			}

			/**
			 * True if the current logged-in user already has a paid/processing
			 * order that contains a bus ticket. Used for "first booking only".
			 */
			public static function user_has_previous_bus_order() {
				if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
					return false;
				}
				$orders = wc_get_orders( array(
					'customer_id' => get_current_user_id(),
					'status'      => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
					'limit'       => 20,
					'return'      => 'ids',
				) );
				foreach ( (array) $orders as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						continue;
					}
					foreach ( $order->get_items() as $item ) {
						if ( $item->get_meta( '_wbtm_bus_id' ) && get_post_type( $item->get_meta( '_wbtm_bus_id' ) ) === WBTM_Functions::get_cpt() ) {
							return true;
						}
					}
				}
				return false;
			}
		}
	}
