<?php
	/**
	 * Coupon Engine — bootstrap & shared helpers.
	 *
	 * A self-contained, per-bus coupon system for the Bus Ticket Booking plugin.
	 * Coupons live in their own CPT (`wbtm_coupon`) and are surfaced to WooCommerce
	 * as *virtual* coupons, so the codes work through WooCommerce's native coupon
	 * field on BOTH the classic and the block/Store-API checkout. Every rule is
	 * re-validated server-side on each totals calculation, so a stale cart can
	 * never grant a discount.
	 *
	 * Files:
	 *   WBTM_Coupon_Module.php  — this loader + shared constants/helpers
	 *   WBTM_Coupon_CPT.php     — registers the coupon CPT + admin list columns
	 *   WBTM_Coupon_Meta.php    — the modern editor UI + save handler
	 *   WBTM_Coupon_Engine.php  — validation + discount calculation (pure logic)
	 *   WBTM_Coupon_Cart.php    — virtual-coupon bridge to WooCommerce + usage tracking
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_Coupon_Module' ) ) {
		class WBTM_Coupon_Module {

			/** Custom post type that stores each coupon. */
			const CPT = 'wbtm_coupon';

			/** Prefix for every coupon meta key (keeps them out of the generic meta box UI). */
			const META = '_wbtm_coupon_';

			/** WooCommerce session key holding the array of applied coupon codes. */
			const SESSION_KEY = 'wbtm_applied_coupons';

			/** Order meta guard so usage is only ever counted once per order. */
			const ORDER_RECORDED = '_wbtm_coupons_recorded';

			/** Order meta holding the applied coupon payload (code => discount). */
			const ORDER_META = '_wbtm_coupons';

			public function __construct() {
				$this->load();
			}

			private function load() {
				$dir = __DIR__;
				require_once $dir . '/WBTM_Coupon_CPT.php';
				require_once $dir . '/WBTM_Coupon_Engine.php';
				require_once $dir . '/WBTM_Coupon_Meta.php';
				require_once $dir . '/WBTM_Coupon_Cart.php';

				new WBTM_Coupon_CPT();
				new WBTM_Coupon_Meta();
				new WBTM_Coupon_Cart();
			}

			/* ============================================================
			 *  Shared helpers (used across every coupon class)
			 * ============================================================ */

			/** Read a single coupon meta value with a typed default. */
			public static function get( $post_id, $key, $default = '' ) {
				$value = get_post_meta( (int) $post_id, self::META . $key, true );
				if ( $value === '' || $value === false || $value === null ) {
					return $default;
				}
				return $value;
			}

			/** Read a coupon meta value guaranteed to be an array. */
			public static function get_array( $post_id, $key ) {
				$value = self::get( $post_id, $key, array() );
				return is_array( $value ) ? $value : array();
			}

			/** Read a coupon meta value as a clean float ( >= 0 ). */
			public static function get_float( $post_id, $key, $default = 0.0 ) {
				$value = self::get( $post_id, $key, $default );
				$value = is_numeric( $value ) ? (float) $value : (float) $default;
				return $value < 0 ? 0.0 : $value;
			}

			/** Read a coupon meta value as a non-negative int. */
			public static function get_int( $post_id, $key, $default = 0 ) {
				$value = self::get( $post_id, $key, $default );
				$value = is_numeric( $value ) ? (int) $value : (int) $default;
				return $value < 0 ? 0 : $value;
			}

			/** True when the given meta flag is switched on. */
			public static function is_on( $post_id, $key ) {
				return self::get( $post_id, $key, 'no' ) === 'yes';
			}

			/** Normalise a user-entered code: uppercase, trimmed, collapse inner whitespace. */
			public static function normalize_code( $code ) {
				$code = sanitize_text_field( (string) $code );
				$code = preg_replace( '/\s+/', '', $code );
				return strtoupper( $code );
			}

			/**
			 * Resolve a coupon code to its published post ID (0 if not found).
			 * Matching is case-insensitive because codes are stored normalised.
			 */
			public static function find_by_code( $code ) {
				$code = self::normalize_code( $code );
				if ( $code === '' ) {
					return 0;
				}
				$query = new WP_Query( array(
					'post_type'              => self::CPT,
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
					'meta_query'             => array(
						array(
							'key'     => self::META . 'code',
							'value'   => $code,
							'compare' => '=',
						),
					),
				) );
				return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
			}

			/** Per-request memo so the share sync runs its DB work once per order. */
			private static $synced_orders = array();

			/**
			 * Distribute an order's total coupon discount across its bus-booking
			 * attendees, writing the per-passenger `wbtm_discount_share` and
			 * `wbtm_discount_label` metas that the passenger list, CSV export and
			 * PDF tickets all read. Idempotent and memoised per request, so it is
			 * safe to call from any render/export path (it also back-fills orders
			 * that were placed before the coupon engine existed).
			 *
			 * @param int $order_id WooCommerce order ID.
			 */
			public static function sync_order_discount_shares( $order_id ) {
				$order_id = (int) $order_id;
				if ( ! $order_id || isset( self::$synced_orders[ $order_id ] ) ) {
					return;
				}
				self::$synced_orders[ $order_id ] = true;

				if ( ! function_exists( 'wc_get_order' ) ) {
					return;
				}
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return;
				}

				$attendees = get_posts( array(
					'post_type'      => 'wbtm_bus_booking',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array(
						array( 'key' => 'wbtm_order_id', 'value' => $order_id, 'compare' => '=' ),
					),
				) );
				if ( empty( $attendees ) ) {
					return;
				}

				$codes    = $order->get_coupon_codes();
				$discount = (float) $order->get_discount_total() + (float) $order->get_discount_tax();

				// No coupon / no discount → clear any stale shares and stop.
				if ( empty( $codes ) || $discount <= 0 ) {
					foreach ( $attendees as $aid ) {
						if ( get_post_meta( $aid, 'wbtm_discount_share', true ) !== '' ) {
							delete_post_meta( $aid, 'wbtm_discount_share' );
							delete_post_meta( $aid, 'wbtm_discount_label' );
						}
					}
					return;
				}

				$label = implode( ', ', array_map( 'strtoupper', $codes ) );
				$dec   = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

				// Distribute proportionally to each passenger's fare; the last
				// passenger absorbs the rounding remainder so the shares sum exactly.
				$fares = array();
				$sum   = 0.0;
				foreach ( $attendees as $aid ) {
					$f = (float) get_post_meta( $aid, 'wbtm_bus_fare', true );
					$f = $f < 0 ? 0.0 : $f;
					$fares[ $aid ] = $f;
					$sum          += $f;
				}
				$count    = count( $attendees );
				$assigned = 0.0;
				$i        = 0;
				foreach ( $attendees as $aid ) {
					$i++;
					if ( $i < $count ) {
						$share     = $sum > 0 ? round( $discount * ( $fares[ $aid ] / $sum ), $dec ) : round( $discount / $count, $dec );
						$assigned += $share;
					} else {
						$share = round( $discount - $assigned, $dec ); // Remainder to the last passenger.
						$share = $share < 0 ? 0.0 : $share;
					}
					update_post_meta( $aid, 'wbtm_discount_share', $share );
					update_post_meta( $aid, 'wbtm_discount_label', $label );
				}
			}

			/** The discount types the engine understands. */
			public static function discount_types() {
				return array(
					'percent'       => esc_html__( 'Percentage of eligible fare', 'bus-ticket-booking-with-seat-reservation' ),
					'fixed_booking' => esc_html__( 'Fixed amount per booking', 'bus-ticket-booking-with-seat-reservation' ),
					'fixed_seat'    => esc_html__( 'Fixed amount per seat', 'bus-ticket-booking-with-seat-reservation' ),
				);
			}

			/** Human day-of-week map (WP: 0 = Sunday ... 6 = Saturday). */
			public static function week_days() {
				return array(
					'1' => esc_html__( 'Mon', 'bus-ticket-booking-with-seat-reservation' ),
					'2' => esc_html__( 'Tue', 'bus-ticket-booking-with-seat-reservation' ),
					'3' => esc_html__( 'Wed', 'bus-ticket-booking-with-seat-reservation' ),
					'4' => esc_html__( 'Thu', 'bus-ticket-booking-with-seat-reservation' ),
					'5' => esc_html__( 'Fri', 'bus-ticket-booking-with-seat-reservation' ),
					'6' => esc_html__( 'Sat', 'bus-ticket-booking-with-seat-reservation' ),
					'0' => esc_html__( 'Sun', 'bus-ticket-booking-with-seat-reservation' ),
				);
			}

			/**
			 * Parse a stored "seats-and-fare" cart item into a normalised shape the
			 * engine can reason about: [ bus_id, line_total, seat_qty, journey_ts ].
			 * Returns null for non-bus items.
			 */
			public static function read_cart_item( $cart_item ) {
				$bus_id = isset( $cart_item['wbtm_bus_id'] ) ? (int) $cart_item['wbtm_bus_id'] : 0;
				if ( ! $bus_id || get_post_type( $bus_id ) !== WBTM_Functions::get_cpt() ) {
					return null;
				}
				$line_total = isset( $cart_item['wbtm_tp'] ) ? (float) $cart_item['wbtm_tp'] : 0.0;
				if ( $line_total <= 0 && isset( $cart_item['line_total'] ) ) {
					$line_total = (float) $cart_item['line_total'];
				}
				$seat_qty = isset( $cart_item['wbtm_seats_qty'] ) ? (int) $cart_item['wbtm_seats_qty'] : 0;
				if ( $seat_qty < 1 ) {
					$seat_qty = 1;
				}
				$journey_raw = isset( $cart_item['wbtm_bp_time'] ) ? $cart_item['wbtm_bp_time'] : '';
				$journey_ts  = $journey_raw ? strtotime( $journey_raw ) : 0;

				return array(
					'bus_id'        => $bus_id,
					'line_total'    => max( 0.0, $line_total ),
					'seat_qty'      => $seat_qty,
					'journey_ts'    => $journey_ts ? (int) $journey_ts : 0,
					'pickup_point'  => isset( $cart_item['wbtm_pickup_point'] ) ? (string) $cart_item['wbtm_pickup_point'] : '',
				);
			}
		}
	}
