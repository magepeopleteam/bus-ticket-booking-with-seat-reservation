<?php
	/**
	 * Coupon cart/checkout integration — virtual WooCommerce coupons.
	 *
	 * Rather than ship a bespoke checkout field (which the block/Store-API
	 * checkout would never render), each `wbtm_coupon` is exposed to WooCommerce
	 * as a *virtual* coupon via `woocommerce_get_shop_coupon_data`. That makes the
	 * codes work through WooCommerce's own coupon field on BOTH the classic and
	 * the block checkout, while our engine enforces every per-bus rule through the
	 * `woocommerce_coupon_is_valid` filter (throwing the exact rejection message)
	 * and computes the live discount amount. Usage counters are ours alone —
	 * virtual coupons always carry id 0, so WooCommerce never touches them.
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_Coupon_Cart' ) ) {
		class WBTM_Coupon_Cart {

			/** Per-request memo: signature => [ code => engine result ]. */
			private $memo = array();

			public function __construct() {
				// Expose our coupons to WooCommerce's coupon layer (classic + block).
				add_filter( 'woocommerce_get_shop_coupon_data', array( $this, 'virtual_coupon' ), 10, 2 );
				// Enforce every per-bus rule; throw our own rejection message.
				add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 3 );
				// Show the code uppercase in the totals ("Coupon: SUMMER20").
				add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'coupon_label' ), 10, 2 );

				// Record usage into our own counters when the order is placed.
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'record_usage' ), 20, 1 );
				add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record_usage' ), 20, 1 );

				// Write per-passenger discount shares after attendees are created, so the
				// passenger list, CSV and (emailed) PDF tickets all show the coupon.
				// Priority 5 keeps it ahead of the PDF/email builders on the same hook.
				add_action( 'wbtm_send_mail', array( $this, 'sync_discount_shares' ), 5, 1 );
			}

			/* ============================================================
			 *  Virtual coupon definition
			 * ============================================================ */

			/**
			 * @param mixed  $false Passed-through false (another source may own the code).
			 * @param string $code  The coupon code WooCommerce is resolving.
			 * @return array|mixed Coupon data array for our codes, else the original value.
			 */
			public function virtual_coupon( $false, $code ) {
				$post_id = WBTM_Coupon_Module::find_by_code( $code );
				if ( ! $post_id ) {
					return $false;
				}
				$result    = $this->evaluate( $code );
				$stackable = WBTM_Coupon_Module::is_on( $post_id, 'stackable' );

				// Always advertise the coupon as fixed_cart with our engine-computed
				// amount; validity (and the real rejection reason) is decided in
				// validate_coupon(). Amount is 0 when invalid — it is never used then.
				return array(
					'id'                   => $post_id, // Informational only; WC forces id 0 on virtual coupons.
					'discount_type'        => 'fixed_cart',
					'amount'               => ! empty( $result['valid'] ) ? $result['discount'] : 0,
					'individual_use'       => $stackable ? false : true,
					'usage_limit'          => 0,
					'usage_limit_per_user' => 0,
					'free_shipping'        => false,
					'exclude_sale_items'   => false,
				);
			}

			/* ============================================================
			 *  Rule enforcement
			 * ============================================================ */

			/**
			 * @param bool      $valid   Current validity.
			 * @param WC_Coupon $coupon  Coupon being validated.
			 * @return bool True when valid.
			 * @throws Exception With the customer-facing rejection message when invalid.
			 */
			public function validate_coupon( $valid, $coupon, $discounts = null ) {
				$code    = $coupon instanceof WC_Coupon ? $coupon->get_code() : (string) $coupon;
				$post_id = WBTM_Coupon_Module::find_by_code( $code );
				if ( ! $post_id ) {
					return $valid; // Not one of ours — leave it alone.
				}
				$result = $this->evaluate( $code );
				if ( empty( $result['valid'] ) ) {
					throw new Exception( esc_html( $result['message'] ) );
				}
				return true;
			}

			public function coupon_label( $label, $coupon ) {
				$code = $coupon instanceof WC_Coupon ? $coupon->get_code() : '';
				if ( $code && WBTM_Coupon_Module::find_by_code( $code ) ) {
					return sprintf(
						/* translators: %s: coupon code */
						esc_html__( 'Coupon: %s', 'bus-ticket-booking-with-seat-reservation' ),
						strtoupper( $code )
					);
				}
				return $label;
			}

			/* ============================================================
			 *  Engine bridge (memoised per request/cart signature)
			 * ============================================================ */

			private function evaluate( $code ) {
				if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
					return array( 'valid' => false, 'message' => esc_html__( 'Your cart is not available.', 'bus-ticket-booking-with-seat-reservation' ), 'discount' => 0.0 );
				}
				$post_id = WBTM_Coupon_Module::find_by_code( $code );
				if ( ! $post_id ) {
					return array( 'valid' => false, 'message' => esc_html__( 'This coupon code is not valid.', 'bus-ticket-booking-with-seat-reservation' ), 'discount' => 0.0 );
				}
				$sig = $this->cart_signature();
				if ( isset( $this->memo[ $sig ][ $code ] ) ) {
					return $this->memo[ $sig ][ $code ];
				}
				$result = WBTM_Coupon_Engine::evaluate( $post_id, WC()->cart, $this->billing_email() );
				$this->memo[ $sig ][ $code ] = $result;
				return $result;
			}

			/** A lightweight fingerprint of the cart's bus lines, so the memo self-invalidates. */
			private function cart_signature() {
				$parts = array();
				foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
					$parsed = WBTM_Coupon_Module::read_cart_item( $cart_item );
					if ( $parsed !== null ) {
						$parts[] = $parsed['bus_id'] . ':' . $parsed['line_total'] . ':' . $parsed['seat_qty'] . ':' . $parsed['journey_ts'];
					}
				}
				return md5( implode( '|', $parts ) . '|' . $this->billing_email() . '|' . get_current_user_id() );
			}

			private function billing_email() {
				if ( function_exists( 'WC' ) && WC()->customer ) {
					return (string) WC()->customer->get_billing_email();
				}
				return '';
			}

			/** Populate per-passenger discount shares for the just-placed order. */
			public function sync_discount_shares( $order_id ) {
				WBTM_Coupon_Module::sync_order_discount_shares( $order_id );
			}

			/* ============================================================
			 *  Usage tracking (our own counters + stats)
			 * ============================================================ */

			/**
			 * Increment our per-coupon counters once when an order is placed.
			 * Accepts either an order ID (classic hook) or an order object (Store API).
			 */
			public function record_usage( $order_or_id ) {
				$order = is_a( $order_or_id, 'WC_Order' ) ? $order_or_id : wc_get_order( $order_or_id );
				if ( ! $order ) {
					return;
				}
				// Guard against the classic + Store-API hooks both firing.
				if ( $order->get_meta( WBTM_Coupon_Module::ORDER_RECORDED ) === 'yes' ) {
					return;
				}

				$coupon_items = $order->get_items( 'coupon' );
				if ( empty( $coupon_items ) ) {
					return;
				}

				$user_id  = (int) $order->get_customer_id();
				$identity = $user_id ? 'u:' . $user_id : 'e:' . strtolower( trim( (string) $order->get_billing_email() ) );
				$today    = date_i18n( 'Y-m-d', current_time( 'timestamp' ) );
				$recorded = array();

				foreach ( $coupon_items as $item ) {
					$code    = $item instanceof WC_Order_Item_Coupon ? $item->get_code() : '';
					$post_id = $code ? WBTM_Coupon_Module::find_by_code( $code ) : 0;
					if ( ! $post_id ) {
						continue; // A real WooCommerce coupon, not ours.
					}
					$m        = WBTM_Coupon_Module::META;
					$discount = (float) $item->get_discount();

					$used = (int) get_post_meta( $post_id, $m . 'used_count', true ) + 1;
					update_post_meta( $post_id, $m . 'used_count', $used );

					$user_log = get_post_meta( $post_id, $m . 'user_log', true );
					$user_log = is_array( $user_log ) ? $user_log : array();
					if ( $identity !== 'e:' ) {
						$user_log[ $identity ] = ( isset( $user_log[ $identity ] ) ? (int) $user_log[ $identity ] : 0 ) + 1;
						update_post_meta( $post_id, $m . 'user_log', $user_log );
					}

					$day_log = get_post_meta( $post_id, $m . 'day_log', true );
					$day_log = is_array( $day_log ) ? $day_log : array();
					$day_log[ $today ] = ( isset( $day_log[ $today ] ) ? (int) $day_log[ $today ] : 0 ) + 1;
					if ( count( $day_log ) > 400 ) {
						ksort( $day_log );
						$day_log = array_slice( $day_log, -400, null, true );
					}
					update_post_meta( $post_id, $m . 'day_log', $day_log );

					$discount_total = (float) get_post_meta( $post_id, $m . 'discount_total', true ) + $discount;
					update_post_meta( $post_id, $m . 'discount_total', round( $discount_total, wc_get_price_decimals() ) );

					$recorded[] = strtoupper( $code );
				}

				if ( ! empty( $recorded ) ) {
					$order->update_meta_data( WBTM_Coupon_Module::ORDER_RECORDED, 'yes' );
					$order->add_order_note( sprintf(
						/* translators: %s: comma-separated coupon codes */
						esc_html__( 'Bus coupon(s) recorded: %s.', 'bus-ticket-booking-with-seat-reservation' ),
						implode( ', ', array_unique( $recorded ) )
					) );
					$order->save();
				}
			}
		}
	}
