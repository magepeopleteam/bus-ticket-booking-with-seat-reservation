<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (! defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (! class_exists('WBTM_Woocommerce')) {
	class WBTM_Woocommerce
	{
		public function __construct()
		{
			add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 90, 3);
			add_action('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'));
			add_filter('woocommerce_cart_item_thumbnail', array($this, 'cart_item_thumbnail'), 90, 3);
			add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 20, 2);
			/**********************************************/
			add_action('woocommerce_after_checkout_validation', array($this, 'after_checkout_validation'));
			add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 10, 4);
			add_action('woocommerce_store_api_checkout_order_processed', array($this, 'api_checkout_order_processed'), 90);
			add_action('woocommerce_checkout_order_processed', array($this, 'checkout_order_processed'), 90);
			//add_action('woocommerce_before_thankyou', array($this, 'checkout_order_processed'),90);

			/**********************************************/
			add_filter('woocommerce_thankyou', array($this, 'update_order_status'), 10, 1);

			add_filter('woocommerce_order_status_changed', array($this, 'order_status_changed'), 10, 4);

			add_action('woocommerce_before_calculate_totals', array($this, 'prevent_duplicate_bookings'), 5);
			
			// Add redirect logic after adding to cart
			add_filter('woocommerce_add_to_cart_redirect', array($this, 'maybe_redirect_to_checkout'), 10, 1);
			add_action('wp_footer', array($this, 'add_checkout_redirect_script'));
			add_action('woocommerce_add_to_cart', array($this, 'maybe_set_redirect_flag'), 10, 6);
			add_filter('woocommerce_cart_item_permalink', array($this, 'cmv_fix_bus_cart_item_link'), 10, 3);
			add_filter('woocommerce_cart_item_price', array($this, 'cmv_fix_cart_dropdown_price'), 10, 3);
			add_filter('woocommerce_cart_item_subtotal', array($this, 'cmv_fix_cart_item_subtotal'), 10, 3);
			// Prevent add to cart notices when redirect is enabled
			add_filter('wc_add_to_cart_message_html', array($this, 'maybe_remove_add_to_cart_message'), 10, 3);
		}

		//Price of product in mini cart
		public function cmv_fix_cart_dropdown_price($price, $cart_item, $cart_item_key) {
			// Check if this is a bus booking item
			$post_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
			// Use the stored total price from cart item data
			$line_total = isset($cart_item['wbtm_tp']) ? floatval($cart_item['wbtm_tp']) : 0;
			// Fallback to line_total if wbtm_tp is not available
			if ($line_total <= 0 && isset($cart_item['line_total'])) {
				$line_total = floatval($cart_item['line_total']);
			}
			// Ensure we have a valid price
			$line_total = max(0, $line_total);
			return wc_price($line_total);
			}
			// For non-bus items, return the original price
			return $price;
		}

		// Fix cart item subtotal to prevent 0.01 from showing
		public function cmv_fix_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
			// Check if this is a bus booking item
			$post_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
			// Use the stored total price from cart item data
			$line_total = isset($cart_item['wbtm_tp']) ? floatval($cart_item['wbtm_tp']) : 0;
			// Fallback to line_total if wbtm_tp is not available
			if ($line_total <= 0 && isset($cart_item['line_total'])) {
				$line_total = floatval($cart_item['line_total']);
			}
			// Ensure we have a valid price
			$line_total = max(0, $line_total);
			return wc_price($line_total);
			}
			// For non-bus items, return the original subtotal
			return $subtotal;
		}


			//Link product in the mini cart
			public function cmv_fix_bus_cart_item_link($permalink, $cart_item, $cart_item_key) {
				// Controlla se è un bus (e se ha l'ID del post bus)
				if (isset($cart_item['wbtm_bus_id']) && get_post_type($cart_item['wbtm_bus_id']) === 'wbtm_bus') {
					$permalink = get_permalink($cart_item['wbtm_bus_id']);
				}
				return $permalink;
			}
		public function prevent_duplicate_bookings($cart_object)
		{
			foreach ($cart_object->cart_contents as $key => $cart_item) {
				$post_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;

				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$start_route = array_key_exists('wbtm_bp_place', $cart_item) ? $cart_item['wbtm_bp_place'] : '';
					$end_route   = array_key_exists('wbtm_dp_place', $cart_item) ? $cart_item['wbtm_dp_place'] : '';
					$date        = array_key_exists('wbtm_bp_time', $cart_item) ? $cart_item['wbtm_bp_time'] : '';
					$seat_type   = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');

					if ($seat_type == 'wbtm_seat_plan') {
						// Handle cabin-specific seat validation
						$cabin_seats = array_key_exists('wbtm_cabin_seats', $cart_item) ? $cart_item['wbtm_cabin_seats'] : [];
						$legacy_seats = array_key_exists('wbtm_seats', $cart_item) ? $cart_item['wbtm_seats'] : [];

						if (!empty($cabin_seats)) {
							// Enhanced by Shahnur Alam - 2025-10-08
							// Fix cabin seat availability validation - use cabin-specific identifiers
							foreach ($cabin_seats as $seat_info) {
								$seat_name = array_key_exists('seat_name', $seat_info) ? $seat_info['seat_name'] : '';
								$cabin_index = array_key_exists('cabin_index', $seat_info) ? $seat_info['cabin_index'] : '';
								
								// Create cabin-specific seat identifier to prevent cross-cabin conflicts
								$cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $seat_name;

								if ($seat_name && WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $cabin_seat_identifier) > 0) {
									WC()->cart->remove_cart_item($key);
									wc_add_notice(sprintf(__("Seat %s in %s has already been booked by another user. Please choose another seat.", 'woocommerce'), $seat_name, $seat_info['cabin_name']), 'error');
								}
							}
						} elseif (!empty($legacy_seats)) {
							// Legacy seat validation
							foreach ($legacy_seats as $seat_info) {
								$seat_name = array_key_exists('seat_name', $seat_info) ? $seat_info['seat_name'] : '';

								if (WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $seat_name) > 0) {
									WC()->cart->remove_cart_item($key);
									wc_add_notice(sprintf(__("Seat %s has already been booked by another user. Please choose another seat.", 'woocommerce'), $seat_name), 'error');
								}
							}
						}
					}
				}
			}
		}
		public function add_cart_item_data($cart_item_data, $product_id)
		{

			$linked_id = WBTM_Global_Function::get_post_info($product_id, 'link_wbtm_bus', $product_id);
			$post_id   = is_string(get_post_status($linked_id)) ? $linked_id : $product_id;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt() && (isset($_POST['wbtm_form_nonce']) && wp_verify_nonce($_POST['wbtm_form_nonce'], 'wbtm_form_nonce'))) {
				$bp               = WBTM_Global_Function::get_submit_info('wbtm_bp_place');
				$bp_time          = WBTM_Global_Function::get_submit_info('wbtm_bp_time');
				$dp               = WBTM_Global_Function::get_submit_info('wbtm_dp_place');
				
				$ticket_infos     = self::get_cart_ticket_info($post_id);
				$cabin_config     = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
				$cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');

				// Check if cabin seats are actually selected
				$has_cabin_seats_selected = false;
				if ($cabin_mode_enabled === 'yes' && !empty($cabin_config)) {
					// Check if any cabin has seats selected
					foreach ($cabin_config as $cabin_index => $cabin) {
						if (($cabin['enabled'] ?? 'yes') === 'yes' && ($cabin['rows'] ?? 0) > 0 && ($cabin['cols'] ?? 0) > 0) {
							$selected_seats = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_cabin_' . $cabin_index, '');
							if (!empty($selected_seats)) {
								$has_cabin_seats_selected = true;
								break;
							}
						}
					}
				}

				// Handle both legacy and cabin-based seat selection
				$seat_price       = 0;
				if ($has_cabin_seats_selected) {
					$seat_price = self::get_cart_cabin_seat_price($post_id, $ticket_infos, $cabin_config);
				} else {
					$seat_price = self::get_cart_seat_price($ticket_infos);
				}
				
				// Ensure seat_price is a valid number
				$seat_price = floatval($seat_price);
				if ($seat_price < 0) {
					$seat_price = 0;
				}

				$ex_service_infos = self::get_cart_extra_service_info($post_id);
				$ex_service_price = self::get_cart_ex_service_price($ex_service_infos);
				
				// Ensure ex_service_price is a valid number
				$ex_service_price = floatval($ex_service_price);
				if ($ex_service_price < 0) {
					$ex_service_price = 0;
				}
				
				$total_price = $seat_price + $ex_service_price;
				
				// Ensure total_price is valid
				$total_price = max(0, floatval($total_price));
				$cart_item_data['wbtm_bus_id']         = $post_id;
				$cart_item_data['wbtm_start_point']    = WBTM_Global_Function::get_submit_info('wbtm_start_point');
				$cart_item_data['wbtm_start_time']     = WBTM_Global_Function::get_submit_info('wbtm_start_time');
				$cart_item_data['wbtm_bp_place']       = $bp;
				$cart_item_data['wbtm_bp_time']        = $bp_time;
				$cart_item_data['wbtm_dp_place']       = $dp;
				$cart_item_data['wbtm_dp_time']        = WBTM_Global_Function::get_submit_info('wbtm_dp_time');
				$cart_item_data['wbtm_pickup_point']   = WBTM_Global_Function::get_submit_info('wbtm_pickup_point');
				$cart_item_data['wbtm_drop_off_point'] = WBTM_Global_Function::get_submit_info('wbtm_drop_off_point');

				// Handle cabin-specific seat data
				if ($has_cabin_seats_selected) {
					$cabin_seats = self::get_cart_cabin_seat_info($post_id, $cabin_config);
					$cart_item_data['wbtm_cabin_seats']    = $cabin_seats;
					$cart_item_data['wbtm_cabin_config']   = $cabin_config;
					$cart_item_data['wbtm_seats_qty']      = self::get_cart_cabin_ticket_qty($cabin_seats);
				} else {
					// Legacy seat handling
					$cart_item_data['wbtm_seats']          = $ticket_infos;
					$cart_item_data['wbtm_seats_qty']      = self::get_cart_ticket_qty($ticket_infos);
				}

				$cart_item_data['wbtm_base_price']     = $seat_price;
				$cart_item_data['wbtm_extra_services'] = $ex_service_infos;
				$cart_item_data['wbtm_base_ex_price']  = $ex_service_price;
				$cart_item_data['wbtm_passenger_info'] = apply_filters('add_wbtm_user_info_data', array(), $post_id, $ticket_infos);
				$cart_item_data['wbtm_tp']             = $total_price;
				$cart_item_data['line_total']          = $total_price;
				$cart_item_data['line_subtotal']       = $total_price;
				
				$cart_item_data                        = apply_filters('wbtm_add_cart_item', $cart_item_data, $post_id);
			}
			//echo '<pre>'; print_r(WBTM_Global_Function::get_post_info($post_id, 'wbtm_selected_seat')); echo '</pre>';
			//echo '<pre>'; print_r($cart_item_data); echo '</pre>'; die();
			return $cart_item_data;
		}

		public static function get_cart_cabin_seat_price($post_id, $ticket_infos, $cabin_config) {
			$total_price = 0;

			foreach ($cabin_config as $cabin_index => $cabin) {
				if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;

				$selected_seats = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_cabin_' . $cabin_index, '');
				if ($selected_seats) {
					$seat_names = explode(',', $selected_seats);
					$seat_types = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_type_cabin_' . $cabin_index, '');
					$seat_types = $seat_types ? explode(',', $seat_types) : [];

					foreach ($seat_names as $seat_index => $seat_name) {
						$seat_type = isset($seat_types[$seat_index]) ? $seat_types[$seat_index] : 0;

						// Find the corresponding ticket info
						foreach ($ticket_infos as $ticket_info) {
							if ($ticket_info['type'] == $seat_type) {
								$base_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_info['price']);
								$price_multiplier = $cabin['price_multiplier'] ?? 1.0;
								$total_price += $base_price * $price_multiplier;
								break;
							}
						}
					}
				}
			}

			return $total_price;
		}

		public static function get_cart_cabin_seat_info($post_id, $cabin_config) {
			$cabin_seats = [];

			// Get ticket information for price calculation
			$bp = WBTM_Global_Function::get_submit_info('wbtm_bp_place');
			$dp = WBTM_Global_Function::get_submit_info('wbtm_dp_place');
			$ticket_infos = WBTM_Functions::get_ticket_info($post_id, $bp, $dp);

			foreach ($cabin_config as $cabin_index => $cabin) {
				if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;

				$selected_seats = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_cabin_' . $cabin_index, '');
				$selected_seat_types = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_type_cabin_' . $cabin_index, '');

				if ($selected_seats) {
					$seat_names = explode(',', $selected_seats);
					$seat_types = $selected_seat_types ? explode(',', $selected_seat_types) : [];

					foreach ($seat_names as $seat_index => $seat_name) {
						$seat_type = isset($seat_types[$seat_index]) ? $seat_types[$seat_index] : 0;

						// Find the ticket price for this seat type
						$ticket_price = 0;
						foreach ($ticket_infos as $ticket_info) {
							if ($ticket_info['type'] == $seat_type) {
								$base_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_info['price']);
								$price_multiplier = $cabin['price_multiplier'] ?? 1.0;
								$ticket_price = $base_price * $price_multiplier;
								break;
							}
						}

						$cabin_seats[] = [
							'cabin_index' => $cabin_index,
							'cabin_name' => $cabin['name'] ?? 'Cabin ' . ($cabin_index + 1),
							'seat_name' => $seat_name,
							'seat_type' => $seat_type,
							'ticket_price' => $ticket_price,
							'price_multiplier' => $cabin['price_multiplier'] ?? 1.0
						];
					}
				}
			}

			return $cabin_seats;
		}

		public static function get_cart_cabin_ticket_qty($cabin_seats) {
			return count($cabin_seats);
		}
	public function before_calculate_totals($cart_object)
	{
		foreach ($cart_object->cart_contents as $key => $value) {
			$post_id = array_key_exists('wbtm_bus_id', $value) ? $value['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
				// Get stored total price
				$total_price = isset($value['wbtm_tp']) ? floatval($value['wbtm_tp']) : 0;
				
				// Check if we have cabin seats
				$has_cabin_seats = isset($value['wbtm_cabin_seats']) && is_array($value['wbtm_cabin_seats']) && !empty($value['wbtm_cabin_seats']);
				
				// Recalculate price if it's missing, zero, or we need to recalculate from seat data
				if (empty($total_price) || $total_price <= 0) {
					$seat_price = 0;
					
					if ($has_cabin_seats) {
						// Calculate price from cabin seats
						foreach ($value['wbtm_cabin_seats'] as $cabin_seat) {
							$ticket_price = isset($cabin_seat['ticket_price']) ? floatval($cabin_seat['ticket_price']) : 0;
							$seat_price += $ticket_price;
						}
					} else {
						// Try to use stored base price first
						$base_price = isset($value['wbtm_base_price']) ? floatval($value['wbtm_base_price']) : 0;
						
						if ($base_price > 0) {
							$seat_price = $base_price;
						} else {
							// Calculate from seat ticket info
							$seat_price = self::get_cart_seat_price($value['wbtm_seats'] ?? []);
						}
					}
					
					// Get extra service price
					$ex_base_price = isset($value['wbtm_base_ex_price']) ? floatval($value['wbtm_base_ex_price']) : 0;
					
					if ($ex_base_price > 0) {
						$ex_service_price = $ex_base_price;
					} else {
						$ex_service_price = self::get_cart_ex_service_price($value['wbtm_extra_services'] ?? []);
					}
					
					// Calculate total price
					$total_price = floatval($seat_price) + floatval($ex_service_price);
					
					// Ensure price is not negative
					$total_price = max(0, $total_price);
					
					// Update the cart item data with the calculated price
					$cart_object->cart_contents[$key]['wbtm_tp'] = $total_price;
					$cart_object->cart_contents[$key]['line_total'] = $total_price;
					$cart_object->cart_contents[$key]['line_subtotal'] = $total_price;
				}
				
				// Always set the price on the product object (WooCommerce uses this for display)
				$value['data']->set_price($total_price);
				$value['data']->set_regular_price($total_price);
				$value['data']->set_sale_price($total_price);
				$value['data']->set_sold_individually('yes');
			}
		}
	}
		public function update_order_status($order_id)
		{
			$force_processing_completed =  WBTM_Global_Function::get_settings('wbtm_general_settings', 'make_processing_completed', 'off');
			if ($force_processing_completed == 'on') {
				if (!$order_id) {
					return;
				}
				$order = new WC_Order($order_id);
				if ('processing' == $order->status) {
					$order->update_status('completed');
				}
				return;
			}
		}
		public function cart_item_thumbnail($thumbnail, $cart_item)
		{
			$post_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
				$thumbnail = '<div class="bg_image_area" data-href="' . get_the_permalink($post_id) . '"><div data-bg-image="' . WBTM_Global_Function::get_image_url($post_id) . '"></div></div>';
			}
			return $thumbnail;
		}
		public function get_item_data($item_data, $cart_item)
		{
			$post_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
				ob_start();
				$this->show_cart_item($cart_item, $post_id);
				do_action('wbtm_show_cart_item', $cart_item, $post_id);
				$item_data[] = array('key' => esc_html__('Booking Details ', 'bus-ticket-booking-with-seat-reservation'), 'value' => ob_get_clean());
			}
			return $item_data;
		}
		/*********************/
		public function after_checkout_validation()
		{
			$cart_items = WC()->cart->get_cart();
			if (sizeof($cart_items) > 0) {
				foreach ($cart_items as $cart_item) {
					$post_id     = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : 0;
					$start_route = array_key_exists('wbtm_bp_place', $cart_item) ? $cart_item['wbtm_bp_place'] : '';
					$end_route   = array_key_exists('wbtm_dp_place', $cart_item) ? $cart_item['wbtm_dp_place'] : '';
					$date        = array_key_exists('wbtm_bp_time', $cart_item) ? $cart_item['wbtm_bp_time'] : '';
					$seats_qty   = array_key_exists('wbtm_seats_qty', $cart_item) ? $cart_item['wbtm_seats_qty'] : '';
					if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
						$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
						if ($seat_type == 'wbtm_seat_plan') {
							$cart_seat_infos = array_key_exists('wbtm_seats', $cart_item) ? $cart_item['wbtm_seats'] : '';
							$cabin_seat_infos = array_key_exists('wbtm_cabin_seats', $cart_item) ? $cart_item['wbtm_cabin_seats'] : '';

							// Check regular seats
							if (is_array($cart_seat_infos) && sizeof($cart_seat_infos) > 0) {
								foreach ($cart_seat_infos as $seat_info) {
									$seat_name = array_key_exists('seat_name', $seat_info) ? $seat_info['seat_name'] : '';
									if ($seat_name && WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $seat_name) > 0) {
										WC()->cart->empty_cart();
										wc_add_notice(__("Sorry, Your Selected seat Already Booked by another user", 'woocommerce'), 'error');
									}
								}
							}

							// Check cabin seats
							if (is_array($cabin_seat_infos) && sizeof($cabin_seat_infos) > 0) {
								// Enhanced by Shahnur Alam - 2025-10-08
								// Fix cabin seat availability validation - use cabin-specific identifiers
								foreach ($cabin_seat_infos as $seat_info) {
									$seat_name = array_key_exists('seat_name', $seat_info) ? $seat_info['seat_name'] : '';
									$cabin_index = array_key_exists('cabin_index', $seat_info) ? $seat_info['cabin_index'] : '';
									
									// Create cabin-specific seat identifier to prevent cross-cabin conflicts
									$cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $seat_name;
									
									if ($seat_name && WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $cabin_seat_identifier) > 0) {
										WC()->cart->empty_cart();
										wc_add_notice(__("Sorry, Your Selected seat Already Booked by another user", 'woocommerce'), 'error');
									}
								}
							}
							do_action('something');
						} else {
							$total_seat     = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
							$sold_seat      = WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date);
							$available_seat = max(0, $total_seat - $sold_seat);
							if ($available_seat < $seats_qty) {
								WC()->cart->empty_cart();
								wc_add_notice(__("Sorry, Your Selected ticket Already Booked by another user", 'woocommerce'), 'error');
							}
						}
					}
				}
			}
		}
		public function checkout_create_order_line_item($item, $cart_item_key, $values)
		{
			
			$post_id = array_key_exists('wbtm_bus_id', $values) ? $values['wbtm_bus_id'] : 0;
			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
				// echo '<pre>';print_r($item);echo '</pre>';die();
				$passenger_infos = array_key_exists('wbtm_passenger_info', $values) ? $values['wbtm_passenger_info'] : [];
				
				

				//==============//
				$bp_place = array_key_exists('wbtm_bp_place', $values) ? $values['wbtm_bp_place'] : '';
				$bp_time  = array_key_exists('wbtm_bp_time', $values) ? $values['wbtm_bp_time'] : '';
				$item->add_meta_data(WBTM_Translations::text_bp(), $bp_place . '(' . WBTM_Global_Function::date_format($bp_time, 'full') . ')');
				//==============//
				$dp_place = array_key_exists('wbtm_dp_place', $values) ? $values['wbtm_dp_place'] : '';
				$dp_time  = array_key_exists('wbtm_dp_time', $values) ? $values['wbtm_dp_time'] : '';
				$item->add_meta_data(WBTM_Translations::text_dp(), $dp_place . '(' . WBTM_Global_Function::date_format($dp_time, 'full') . ')');
				//==============//
				$start_point = array_key_exists('wbtm_start_point', $values) ? $values['wbtm_start_point'] : '';
				$start_time  = array_key_exists('wbtm_start_time', $values) ? $values['wbtm_start_time'] : '';
				if ($bp_place != $start_point) {
					$item->add_meta_data(WBTM_Translations::text_start_point(), $start_point . '(' . WBTM_Global_Function::date_format($start_time, 'full') . ')');
				}
				//==============//
				$pickup_point = array_key_exists('wbtm_pickup_point', $values) ? $values['wbtm_pickup_point'] : '';
				if ($pickup_point) {
					$item->add_meta_data(WBTM_Translations::text_pickup_point(), $pickup_point);
				}
				$drop_off_point = array_key_exists('wbtm_drop_off_point', $values) ? $values['wbtm_drop_off_point'] : '';
				if ($drop_off_point) {
					$item->add_meta_data(WBTM_Translations::text_drop_off_point(), $drop_off_point);
				}

				//==============//
				// Enhanced by Shahnur Alam - 2025-10-08
				// Handle both legacy seats and cabin seats for order creation
				$ticket_infos = [];
				$cabin_seats = array_key_exists('wbtm_cabin_seats', $values) ? $values['wbtm_cabin_seats'] : [];
				$legacy_seats = array_key_exists('wbtm_seats', $values) ? $values['wbtm_seats'] : [];
				
				// Convert cabin seats to ticket info format if cabin seats exist
				if (!empty($cabin_seats)) {
					foreach ($cabin_seats as $cabin_seat) {
						$ticket_infos[] = [
							'ticket_name' => $cabin_seat['cabin_name'] ?? 'Cabin Seat',
							'seat_name' => $cabin_seat['seat_name'] ?? '',
							'ticket_type' => $cabin_seat['seat_type'] ?? 0,
							'ticket_price' => $cabin_seat['ticket_price'] ?? 0,
							'ticket_qty' => 1, // Each cabin seat is quantity 1
							'cabin_name' => $cabin_seat['cabin_name'] ?? '',
							'cabin_index' => $cabin_seat['cabin_index'] ?? 0,
							'price_multiplier' => $cabin_seat['price_multiplier'] ?? 1.0
						];
					}
				} else {
					// Use legacy seats if no cabin seats
					$ticket_infos = $legacy_seats;
				}
				
				$ticket_qty   = array_key_exists('wbtm_seats_qty', $values) ? $values['wbtm_seats_qty'] : 0;
				$base_price   = array_key_exists('wbtm_base_price', $values) ? $values['wbtm_base_price'] : 0;
				if (sizeof($ticket_infos) > 0) {
					foreach ($ticket_infos as $ticket_info) {
						$item->add_meta_data(WBTM_Translations::text_ticket_type(), $ticket_info['ticket_name']);
						if (array_key_exists('seat_name', $ticket_info)) {
							$seat_name = $ticket_info['seat_name'];
							if (array_key_exists('dd', $ticket_info) && $ticket_info['dd']) {
								$seat_name = $seat_name . '(' . WBTM_Translations::text_upper_deck() . ')';
							}
							$item->add_meta_data(WBTM_Translations::text_seat_name(), $seat_name);
						}
						$item->add_meta_data(WBTM_Translations::text_qty(), $ticket_info['ticket_qty']);
						$item->add_meta_data(WBTM_Translations::text_price(), ' ( ' . $ticket_info["ticket_price"] . ' x ' . $ticket_info['ticket_qty'] . ' ) = ' . wc_price($ticket_info['ticket_price'] * $ticket_info['ticket_qty']));
					}
					$item->add_meta_data(WBTM_Translations::text_total_qty(), $ticket_qty);
					$item->add_meta_data(WBTM_Translations::text_ticket_sub_total(), wc_price($base_price));
				}
				//==============//
				$extra_service = array_key_exists('wbtm_extra_services', $values) ? $values['wbtm_extra_services'] : [];
				$ex_base_price = array_key_exists('wbtm_base_ex_price', $values) ? $values['wbtm_base_ex_price'] : 0;
				if (sizeof($extra_service) > 0) {
					$item->add_meta_data(WBTM_Translations::text_ex_service(), '');
					foreach ($extra_service as $service) {
						$item->add_meta_data(WBTM_Translations::text_name(), $service['name']);
						$item->add_meta_data(WBTM_Translations::text_total_qty(), $service['qty']);
						$item->add_meta_data(WBTM_Translations::text_price(), ' ( ' . wc_price($service['price']) . ' x ' . $service['qty'] . ' ) = ' . wc_price($service['price'] * $service['qty']));
					}
					$item->add_meta_data(WBTM_Translations::text_ex_service_sub_total(), $ex_base_price);
				}
				//==============//
				$total_price = array_key_exists('wbtm_tp', $values) ? $values['wbtm_tp'] : [];
				$item->add_meta_data(WBTM_Translations::text_order_total(), wc_price($total_price));
				//==============//
				$item->add_meta_data('_bus_id', $post_id);
				$item->add_meta_data('_wbtm_bus_id', $post_id);
				$item->add_meta_data('_wbtm_ticket_info', $ticket_infos);
				$item->add_meta_data('_wbtm_bp', $bp_place);
				$item->add_meta_data('_wbtm_bp_time', $bp_time);
				$item->add_meta_data('_wbtm_dp', $dp_place);
				$item->add_meta_data('_wbtm_dp_time', $dp_time);
				$item->add_meta_data('_wbtm_start_point', $start_point);
				$item->add_meta_data('_wbtm_start_time', $start_time);
				$item->add_meta_data('_extra_services', $extra_service);
				$item->add_meta_data('_wbtm_pickup_point', $pickup_point);
				$item->add_meta_data('_wbtm_drop_off_point', $drop_off_point);
				$item->add_meta_data('_wbtm_base_price', $base_price);
				$item->add_meta_data('_wbtm_qty', $ticket_qty);
				$item->add_meta_data('_wbtm_passenger_info', $passenger_infos);
				$item->add_meta_data('_wbtm_tp', $total_price);
				do_action('wbtm_checkout_create_order_line_item', $item, $values);
			}
		}
		public function checkout_order_processed($order_id)
		{
			if ($order_id) {
				$order        = wc_get_order($order_id);
				$order_status = $order->get_status();
				if ($order_status != 'failed') {
					$check_attendee = WBTM_Query::query_check_order($order_id)->post_count;
					if ($check_attendee == 0) {
						foreach ($order->get_items() as $item_id => $item) {
							self::add_billing_data($item_id, $order_id);
						}
						do_action('wbtm_send_mail', $order_id);
					}
				}
			}
		}
		public function api_checkout_order_processed($order)
		{
			$this->checkout_order_processed($order->get_id());
		}
		/*********************/
		public static function add_billing_data($item_id, $order_id)
		{

			$post_id = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_bus_id');

			if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
				$order = wc_get_order($order_id);
				//$order_meta = get_post_meta($order_id);
				//echo '<pre>';print_r($order_meta);echo '</pre>';
				//echo '<pre>';print_r($order);echo '</pre>';die();
				$order_status    = $order->get_status();
				$payment_method  = $order->get_payment_method();
				$user_id         = $order->get_user_id();
				$billing_name    = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				$billing_email   = $order->get_billing_email();
				$billing_phone   = $order->get_billing_phone();
				$billing_address = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
				$now_full        = current_time('Y-m-d H:i');
				/********************************/
				$bp      = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_bp');
				$bp      = $bp ? WBTM_Global_Function::data_sanitize($bp) : '';
				$bp_time = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_bp_time');
				$bp_time = $bp_time ? WBTM_Global_Function::data_sanitize($bp_time) : '';
				/*******************/
				$dp      = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_dp');
				$dp      = $dp ? WBTM_Global_Function::data_sanitize($dp) : '';
				$dp_time = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_dp_time');
				$dp_time = $dp_time ? WBTM_Global_Function::data_sanitize($dp_time) : '';
				/*******************/
				$start_point = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_start_point');
				$start_point = $start_point ? WBTM_Global_Function::data_sanitize($start_point) : '';
				$start_time  = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_start_time');
				$start_time  = $start_time ? WBTM_Global_Function::data_sanitize($start_time) : '';
				/*******************/
				$pickup_point = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_pickup_point');
				$pickup_point = $pickup_point ? WBTM_Global_Function::data_sanitize($pickup_point) : '';
				/*******************/
				$drop_off_point = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_drop_off_point');
				$drop_off_point = $drop_off_point ? WBTM_Global_Function::data_sanitize($drop_off_point) : '';
				/*******************/
				$order_total = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_tp');
				$order_total = $order_total ? WBTM_Global_Function::data_sanitize($order_total) : '';
				/*******************/
				$service_info = WBTM_Global_Function::get_order_item_meta($item_id, '_extra_services');
				$service_info = $service_info ? WBTM_Global_Function::data_sanitize($service_info) : [];
				/*******************/
				$attendee_info = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_passenger_info');
				$attendee_info = $attendee_info ? WBTM_Global_Function::data_sanitize($attendee_info) : [];
				
				
				/*******************/
				$ticket_infos = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_ticket_info');
				$ticket_infos = $ticket_infos ? WBTM_Global_Function::data_sanitize($ticket_infos) : [];
				/*************************/
				if (sizeof($ticket_infos) > 0) {
					$count = 0;
					foreach ($ticket_infos as $ticket_info) {
						$qty = $ticket_info['ticket_qty'];

						for ($key = 0; $key < $qty; $key++) {
							$data['wbtm_order_id']        = $order_id;
							$data['wbtm_bus_id']          = $post_id;
							$data['wbtm_user_id']         = $user_id;
							$data['wbtm_item_id']         = $item_id;
							$data['wbtm_tp']              = $order_total;
							$data['wbtm_boarding_point']  = $bp;
							$data['wbtm_boarding_time']   = $bp_time;
							$data['wbtm_dropping_point']  = $dp;
							$data['wbtm_dropping_time']   = $dp_time;
							$data['wbtm_bus_start_point'] = $start_point;
							$data['wbtm_start_time']      = $start_time;
							$data['wbtm_booking_date']    = $now_full;
							$data['wbtm_pickup_point']    = $pickup_point;
							$data['wbtm_drop_off_point']  = $drop_off_point;
							$data['wbtm_ticket']          = $ticket_info['ticket_name'];
							$data['wbtm_seat']            = array_key_exists('seat_name', $ticket_info) ? $ticket_info['seat_name'] : $ticket_info['ticket_name'];
						
						// Enhanced by Shahnur Alam - 2025-10-08 
						// Save cabin information for cabin/coach bookings to display in passenger list
						// Fix cabin seat storage: use cabin-specific seat identifier for proper availability tracking
						if (array_key_exists('cabin_name', $ticket_info) && array_key_exists('cabin_index', $ticket_info)) {
							$data['wbtm_cabin_info'] = [
								'cabin_name' => $ticket_info['cabin_name'],
								'cabin_index' => $ticket_info['cabin_index'],
								'price_multiplier' => $ticket_info['price_multiplier'] ?? 1.0
							];
							// Store cabin-specific seat identifier to prevent cross-cabin conflicts
							$data['wbtm_seat'] = 'cabin_' . $ticket_info['cabin_index'] . '_' . $ticket_info['seat_name'];
						} else {
							// Legacy seat storage for non-cabin bookings
							$data['wbtm_seat'] = $ticket_info['seat_name'];
						}
							$data['wbtm_bus_fare']        = $ticket_info['ticket_price'];
							$data['wbtm_ticket_status']   = 1;
							$data['wbtm_order_status']    = $order_status;
							$data['wbtm_attendee_info']   = array_key_exists($count, $attendee_info) ? $attendee_info[$count] : [];
							$data['wbtm_billing_type']    = $payment_method;
							$data['wbtm_extra_services']  = $service_info;
							$data['wbtm_user_name']       = $billing_name;
							$data['wbtm_user_email']      = $billing_email;
							$data['wbtm_user_phone']      = $billing_phone;
							$data['wbtm_user_address']    = $billing_address;
							$booking_data                 = apply_filters('add_wbtm_booking_data', $data, $post_id, $count);
							self::add_cpt_data('wbtm_bus_booking', $billing_name, $booking_data);
							$count++;
						}
					}
					if (class_exists('Wbtm_Woocommerce_bus_Pro')) {
						
						
						// Get seat type configuration
						$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
						
						
						$total_seat_count = 0;
						
						if ($seat_type === 'wbtm_seat_plan') {
							
							// Get raw seat info from database
							$raw_seat_info = get_post_meta($post_id, 'wbtm_bus_seats_info', true);
							
							
							$seat_infos = $seat_infos ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
							
							
							if (is_array($seat_infos)) {
								foreach ($seat_infos as $row_index => $seats) {
									if (is_array($seats)) {
										foreach ($seats as $seat_index => $seat) {
											if (!empty($seat)) {
												$total_seat_count++;
											}
										}
									}
								}
							}
						} else if ($seat_type === 'wbtm_without_seat_plan') {
							$total_seat_count = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
						}
						
						
						
						$minimum_seat_treshold = WBTM_Global_Function::get_settings('wbtm_email_settings', 'minimum_seat_treshold');
						$minimum_seat_treshold_email_content = WBTM_Global_Function::get_settings('wbtm_email_settings', 'seat_treshold_email_content');
						
						$seat_booked = WBTM_Query::query_seat_booked($post_id, $start_point, $dp, $start_time);
						$seat_left = $total_seat_count - count($seat_booked);
						$seat_left = $seat_left - $count;
						
						
						
						if ($minimum_seat_treshold != -1 && $minimum_seat_treshold >= $seat_left) {
							$minimum_seat_treshold_email_content = str_replace(
								array('{bus_name}', '{journey_date}'),
								array($bus_name, $start_time),
								$minimum_seat_treshold_email_content
							);
							
							$notification_receiver_email = WBTM_Global_Function::get_settings('wbtm_email_settings', 'pdf_admin_notification_email');
							$formatted_date = str_replace([' ', ':'], '', $start_time);
							$bus_unique_string = $formatted_date . $bus_name_short;
							$email_sent = get_option($bus_unique_string);
							
							
							
							if ($email_sent !== 'yes') {
								
								
								$email_result = wp_mail($notification_receiver_email, 'Bus Minimum Seat Treshold', $minimum_seat_treshold_email_content);
								
								
								
								if ($email_result) {
									$update_result = update_option($bus_unique_string, 'yes');
									
								}
							}
						} 
					}
				}
				/*******************/
				if (sizeof($service_info) > 0) {
					$ex_data['wbtm_bus_id']         = $post_id;
					$ex_data['wbtm_item_id']        = $item_id;
					$ex_data['wbtm_boarding_time']  = $bp_time;
					$ex_data['wbtm_start_time']     = $start_time;
					$ex_data['wbtm_order_id']       = $order_id;
					$ex_data['wbtm_order_status']   = $order_status;
					$ex_data['wbtm_user_id']        = $user_id;
					$ex_data['wbtm_extra_services'] = $service_info;
					self::add_cpt_data('wbtm_service_booking', $billing_name, $ex_data);
				}
			}
		}
		/*********************/
		public function maybe_redirect_to_checkout($url)
		{
			// Check if the redirect setting is enabled
			$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'checkout_redirect_after_booking', 'off');
			
			if ($redirect_enabled === 'on') {
				// Check if the last added item is a bus booking
				$cart_items = WC()->cart->get_cart();
				if (!empty($cart_items)) {
					$last_item = end($cart_items);
					$post_id = array_key_exists('wbtm_bus_id', $last_item) ? $last_item['wbtm_bus_id'] : 0;
					
					if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
						// Redirect to checkout page
						return wc_get_checkout_url();
					}
				}
			}
			
			return $url;
		}
		/*********************/
		public function maybe_set_redirect_flag($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
		{
			// Check if the redirect setting is enabled
			$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'checkout_redirect_after_booking', 'off');
			
			if ($redirect_enabled === 'on') {
				// Check if this is a bus booking
				$linked_id = WBTM_Global_Function::get_post_info($product_id, 'link_wbtm_bus', $product_id);
				$post_id = is_string(get_post_status($linked_id)) ? $linked_id : $product_id;
				
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					// Set a flag to indicate we should redirect
					WC()->session->set('wbtm_redirect_to_checkout', true);
				}
			}
		}
		/*********************/
		public function add_checkout_redirect_script()
		{
			// Only add script if redirect is enabled and we're on a bus booking page
			$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'checkout_redirect_after_booking', 'off');
			
			if ($redirect_enabled === 'on' && (is_singular(WBTM_Functions::get_cpt()) || (WC()->session && WC()->session->get('wbtm_redirect_to_checkout')))) {
				// Clear the flag if it exists
				if (WC()->session && WC()->session->get('wbtm_redirect_to_checkout')) {
					WC()->session->set('wbtm_redirect_to_checkout', false);
				}
				
				// Add script to redirect to checkout after bus booking
				?>
				<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Handle bus booking form submission
					$(document).on('submit', 'form[name="wbtm_bus_booking_form"]', function(e) {
						var form = $(this);
						var button = form.find('button[type="submit"]');
						
						// Check if this is an add-to-cart submission
						if (button.attr('name') === 'add-to-cart' && button.attr('value')) {
							// Use a flag to track if we should redirect
							window.wbtm_should_redirect = true;
						}
					});
					
					// Listen for WooCommerce add to cart events
					$(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
						if (window.wbtm_should_redirect) {
							// Redirect to checkout immediately (no notice to remove)
							window.location.href = '<?php echo wc_get_checkout_url(); ?>';
						}
					});
				});
				</script>
				<?php
			}
		}
		/*********************/
		public function maybe_remove_add_to_cart_message($message, $products, $show_qty)
		{
			// Check if redirect is enabled
			$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'checkout_redirect_after_booking', 'off');
			
			if ($redirect_enabled === 'on') {
				// Check if any of the added products are bus bookings
				foreach ($products as $product_id => $qty) {
					$linked_id = WBTM_Global_Function::get_post_info($product_id, 'link_wbtm_bus', $product_id);
					$post_id = is_string(get_post_status($linked_id)) ? $linked_id : $product_id;
					
					if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
						// This is a bus booking, don't show the message
						return '';
					}
				}
			}
			
			return $message;
		}
		/*********************/
		public function order_status_changed($order_id)
		{
			$order        = wc_get_order($order_id);
			$order_status = $order->get_status();
			foreach ($order->get_items() as $item_id => $item_values) {
				$post_id = WBTM_Global_Function::get_order_item_meta($item_id, '_wbtm_bus_id');
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					if ($order->has_status('processing') || $order->has_status('pending') || $order->has_status('on-hold') || $order->has_status('completed') || $order->has_status('cancelled') || $order->has_status('refunded') || $order->has_status('failed') || $order->has_status('requested')) {
						$this->wc_order_status_change($order_status, $post_id, $order_id);
						//echo '<pre>';print_r($order_status);echo '</pre>';die();
						do_action('wbtm_order_status_change', $order_status, $post_id, $order_id);
					}
				}
			}
		}
		public function wc_order_status_change($order_status, $post_id, $order_id)
		{
			$args = array(
				'post_type'      => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						array(
							'key'     => 'wbtm_bus_id',
							'value'   => $post_id,
							'compare' => '='
						),
						array(
							'key'     => 'wbtm_order_id',
							'value'   => $order_id,
							'compare' => '='
						)
					)
				)
			);
			$loop = new WP_Query($args);
			foreach ($loop->posts as $user) {
				$user_id = $user->ID;
				update_post_meta($user_id, 'wbtm_order_status', $order_status);
			}
			$ex_args = array(
				'post_type'      => 'wbtm_service_booking',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						array(
							'key'     => 'wbtm_bus_id',
							'value'   => $post_id,
							'compare' => '='
						),
						array(
							'key'     => 'wbtm_order_id',
							'value'   => $order_id,
							'compare' => '='
						)
					)
				)
			);
			$ex_loop = new WP_Query($ex_args);
			foreach ($ex_loop->posts as $user) {
				$user_id = $user->ID;
				update_post_meta($user_id, 'wbtm_order_status', $order_status);
			}
		}
		/*********************/
	public static function get_cart_seat_price($ticket_infos = [])
	{
		$total_price = 0;
		if (sizeof($ticket_infos) > 0) {
			foreach ($ticket_infos as $index => $ticket_info) {
				$ticket_price = isset($ticket_info['ticket_price']) ? floatval($ticket_info['ticket_price']) : 0;
				$ticket_qty = isset($ticket_info['ticket_qty']) ? intval($ticket_info['ticket_qty']) : 0;
				// Handle false values from get_seat_price function
				if ($ticket_price === false || $ticket_price < 0) {
					$ticket_price = 0;
				}
				$item_total = $ticket_price * $ticket_qty;
				$total_price = $total_price + $item_total;
			}
		}
		$final_price = max(0, floatval($total_price));
		return $final_price;
	}
		public static function get_cart_ticket_qty($ticket_infos = [])
		{
			$total_qty = 0;
			if (sizeof($ticket_infos) > 0) {
				foreach ($ticket_infos as $ticket_info) {
					$total_qty = $total_qty + $ticket_info['ticket_qty'];
				}
			}
			return max(0, $total_qty);
		}
		public static function get_cart_ex_service_price($ex_service_infos = [])
		{
			$total_price = 0;
			if (sizeof($ex_service_infos) > 0) {
				foreach ($ex_service_infos as $ticket_info) {
					$total_price = $total_price + $ticket_info['price'] * $ticket_info['qty'];
				}
			}
			return max(0, $total_price);
		}
		public static function get_cart_ticket_info($post_id)
		{
			$ticket_info = [];
			$seat_type   = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
			$seat_infos  = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
			$seat_row    = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
			$seat_column = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
			/************************/
			$start_place = WBTM_Global_Function::get_submit_info('wbtm_bp_place');
			$end_place   = WBTM_Global_Function::get_submit_info('wbtm_dp_place');
			$start_date  = WBTM_Global_Function::get_submit_info('wbtm_bp_time');
			if ($seat_type == 'wbtm_seat_plan' && sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0) {
				$count                = 0;
				$selected_seat        = WBTM_Global_Function::get_submit_info('wbtm_selected_seat');
				$selected_seat        = $selected_seat ? explode(',', $selected_seat) : [];
				$selected_ticket_type = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_type');
				$selected_ticket_type = $selected_ticket_type ? explode(',', $selected_ticket_type) : [0];
				if (sizeof($selected_seat) > 0 && sizeof($selected_ticket_type) > 0) {
					foreach ($selected_seat as $key => $seat_name) {
						$type = isset($selected_ticket_type[$key]) ? $selected_ticket_type[$key] : 0;
						if ($seat_name) {
							$seat_price = WBTM_Functions::get_seat_price($post_id, $start_place, $end_place, $type);
							// Handle false return value from get_seat_price
							if ($seat_price === false || $seat_price < 0) {
								$seat_price = 0;
							}
							$ticket_info[$count]['ticket_name']  = WBTM_Functions::get_ticket_name($type);
							$ticket_info[$count]['ticket_type']  = $type;
							$ticket_info[$count]['seat_name']    = $seat_name;
							$ticket_info[$count]['ticket_price'] = floatval($seat_price);
							$ticket_info[$count]['ticket_qty']   = 1;
							$ticket_info[$count]['date']         = $start_date ?? '';
							$ticket_info[$count]['dd']           = '';
							$count++;
						}
					}
				}
				$selected_seat_dd        = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_dd');
				$selected_seat_dd        = $selected_seat_dd ? explode(',', $selected_seat_dd) : [];
				$selected_ticket_type_dd = WBTM_Global_Function::get_submit_info('wbtm_selected_seat_dd_type');
				$selected_ticket_type_dd = $selected_ticket_type_dd ? explode(',', $selected_ticket_type_dd) : [0];
				if (sizeof($selected_seat_dd) > 0 && sizeof($selected_ticket_type_dd) > 0) {
					foreach ($selected_seat_dd as $key => $seat_name) {
						$type = isset($selected_ticket_type_dd[$key]) ? $selected_ticket_type_dd[$key] : 0;
						if ($seat_name) {
							$seat_price = WBTM_Functions::get_seat_price($post_id, $start_place, $end_place, $type, true);
							// Handle false return value from get_seat_price
							if ($seat_price === false || $seat_price < 0) {
								$seat_price = 0;
							}
							$ticket_info[$count]['ticket_name']  = WBTM_Functions::get_ticket_name($type);
							$ticket_info[$count]['ticket_type']  = $type;
							$ticket_info[$count]['seat_name']    = $seat_name;
							$ticket_info[$count]['ticket_price'] = floatval($seat_price);
							$ticket_info[$count]['ticket_qty']   = 1;
							$ticket_info[$count]['date']         = $start_date ?? '';
							$ticket_info[$count]['dd']           = 1;
							$count++;
						}
					}
				}
			} else {
				// Without seat plan mode
				$qty            = WBTM_Global_Function::get_submit_info('wbtm_seat_qty', array());
				$passenger_type = WBTM_Global_Function::get_submit_info('wbtm_passenger_type', []);
				$count          = count($passenger_type);
				if ($count > 0 && is_array($qty)) {
					for ($i = 0; $i < count($passenger_type); $i++) {
						if (isset($qty[$i]) && $qty[$i] > 0) {
							$type                              = $passenger_type[$i] ?? '';
							$ticket_name                       = WBTM_Functions::get_ticket_name($type);
							$seat_price                        = WBTM_Functions::get_seat_price($post_id, $start_place, $end_place, $type);
							// Handle false return value from get_seat_price
							if ($seat_price === false || $seat_price < 0) {
								$seat_price = 0;
							}
							$ticket_info[$i]['ticket_name']  = $ticket_name;
							$ticket_info[$i]['seat_name']    = $ticket_name;
							$ticket_info[$i]['ticket_type']  = $type;
							$ticket_info[$i]['ticket_price'] = floatval($seat_price);
							$ticket_info[$i]['ticket_qty']   = intval($qty[$i]);
							$ticket_info[$i]['date']         = $start_date ?? '';
						}
					}
				}
			}
			return apply_filters('wbtm_cart_ticket_info_data_prepare', $ticket_info, $post_id);
		}
		public static function get_cart_extra_service_info($post_id): array
		{
			$start_date    = WBTM_Global_Function::get_submit_info('wbtm_bp_time');
			$service_name  = WBTM_Global_Function::get_submit_info('extra_service_name', array());
			$service_qty   = WBTM_Global_Function::get_submit_info('extra_service_qty', array());
			$extra_service = array();
			if (sizeof($service_name) > 0) {
				for ($i = 0; $i < count($service_name); $i++) {
					if ($service_qty[$i] > 0) {
						$name                         = $service_name[$i] ?? '';
						$extra_service[$i]['name']  = $name;
						$extra_service[$i]['price'] = WBTM_Functions::get_ex_service_price($post_id, $name);
						$extra_service[$i]['qty']   = $service_qty[$i];
						$extra_service[$i]['date']  = $start_date ?? '';
					}
				}
			}
			return $extra_service;
		}
		/*********************/
		public function show_cart_item($cart_item, $post_id)
		{
?>
			<div class="wbtm_style">
				<?php do_action('mptbm_before_cart_item_display', $cart_item, $post_id); ?>
				<?php $this->show_cart_route_details($cart_item); ?>
				<?php $this->show_cart_ticket_information($cart_item); ?>
				<?php $this->show_cart_ex_service($cart_item); ?>
				<?php do_action('wbtm_after_cart_item_display', $cart_item, $post_id); ?>
			</div>
		<?php
		}
		public function show_cart_route_details($cart_item)
		{
			$bp             = array_key_exists('wbtm_bp_place', $cart_item) ? $cart_item['wbtm_bp_place'] : '';
			$bp_time        = array_key_exists('wbtm_bp_time', $cart_item) ? $cart_item['wbtm_bp_time'] : '';
			$dp             = array_key_exists('wbtm_dp_place', $cart_item) ? $cart_item['wbtm_dp_place'] : '';
			$dp_time        = array_key_exists('wbtm_dp_time', $cart_item) ? $cart_item['wbtm_dp_time'] : '';
			$start_point    = array_key_exists('wbtm_start_point', $cart_item) ? $cart_item['wbtm_start_point'] : '';
			$start_time     = array_key_exists('wbtm_start_time', $cart_item) ? $cart_item['wbtm_start_time'] : '';
			$pickup_point   = array_key_exists('wbtm_pickup_point', $cart_item) ? $cart_item['wbtm_pickup_point'] : '';
			$drop_off_point = array_key_exists('wbtm_drop_off_point', $cart_item) ? $cart_item['wbtm_drop_off_point'] : '';
		?>
			<div class="dLayout_xs">
				<ul class="cart_list">
					<li>
						<span class="fas fa-map-marker-alt"></span>
						<h6 class="_mR_xs"><?php echo WBTM_Translations::text_bp(); ?> :</h6>
						<span><?php echo esc_html($bp) . ' ' . esc_html($bp_time ? ' (' . WBTM_Global_Function::date_format($bp_time, 'full') . ' )' : ''); ?></span>
					</li>
					<li>
						<span class="fas fa-map-marker-alt"></span>
						<h6 class="_mR_xs"><?php echo WBTM_Translations::text_dp(); ?> :</h6>
						<span><?php echo esc_html($dp) . ' ' . esc_html($dp_time ? ' (' . WBTM_Global_Function::date_format($dp_time, 'full') . ' )' : ''); ?></span>
					</li>
					<?php if ($start_point != $bp) { ?>
						<li>
							<span class="fas fa-map-marker-alt"></span>
							<h6 class="_mR_xs"><?php echo WBTM_Translations::text_start_point(); ?> :</h6>
							<span><?php echo esc_html($start_point) . ' ' . esc_html($start_time ? ' (' . WBTM_Global_Function::date_format($start_time, 'full') . ' )' : ''); ?></span>
						</li>
					<?php } ?>
					<?php if ($pickup_point) { ?>
						<li>
							<span class="fas fa-map-marker-alt"></span>
							<h6 class="_mR_xs"><?php echo WBTM_Translations::text_pickup_point(); ?> :</h6>
							<span><?php echo esc_html($pickup_point); ?></span>
						</li>
					<?php } ?>
					<?php if ($drop_off_point) { ?>
						<li>
							<span class="fas fa-map-marker-alt"></span>
							<h6 class="_mR_xs"><?php echo WBTM_Translations::text_drop_off_point(); ?> :</h6>
							<span><?php echo esc_html($drop_off_point); ?></span>
						</li>
					<?php } ?>
				</ul>
			</div>
			<?php
		}
		public function show_cart_ticket_information($cart_item)
		{
			$wbtm_seats   = array_key_exists('wbtm_seats', $cart_item) ? $cart_item['wbtm_seats'] : [];
			$cabin_seats  = array_key_exists('wbtm_cabin_seats', $cart_item) ? $cart_item['wbtm_cabin_seats'] : [];
			$base_price   = array_key_exists('wbtm_base_price', $cart_item) ? $cart_item['wbtm_base_price'] : '';
			$ticket_count = 0;
			$tic_key      = 0;

			// Handle both regular seats and cabin seats
			$seats_to_display = [];
			if (is_array($wbtm_seats) && !empty($wbtm_seats)) {
				$seats_to_display = $wbtm_seats;
			} elseif (is_array($cabin_seats) && !empty($cabin_seats)) {
				$seats_to_display = $cabin_seats;
			}

			$calculated_total = 0;
			if (sizeof($seats_to_display) > 0) { ?>
				<h5 class="_mB_xs"><?php esc_html_e('Ticket Information', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
				<div class="dLayout_xs">
					<ul class="cart_list">
						<?php foreach ($seats_to_display as $key => $wbtm_seat) {
							// Handle different seat data structures
							$is_cabin_seat = isset($wbtm_seat['cabin_name']);
							$ticket_name = $wbtm_seat['ticket_name'] ?? $wbtm_seat['cabin_name'] ?? '';
							$seat_name = $wbtm_seat['seat_name'] ?? '';
							$seat_type = $wbtm_seat['seat_type'] ?? 0;
							$ticket_price = $wbtm_seat['ticket_price'] ?? $wbtm_seat['price'] ?? 0;
							$qty = 1; // Default quantity

							// Calculate total for all seats
							$calculated_total += $ticket_price * $qty;

							if ($ticket_count > 0) { ?>
								<li>
									<div class="_divider"></div>
								</li>
							<?php } ?>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_ticket_type(); ?> :</h6>
								<span><?php echo esc_html($ticket_name ?: WBTM_Functions::get_ticket_name($seat_type)); ?></span>
							</li>
							<?php if ($seat_name) { ?>
								<li>
									<h6 class="_mR_xs"><?php echo WBTM_Translations::text_seat_name(); ?> :</h6>
									<span><?php echo esc_html($seat_name); ?></span>
								</li>
							<?php } ?>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_qty(); ?> :</h6>
								<span><?php echo esc_html($qty); ?></span>
							</li>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_price(); ?> :</h6>
								<span><?php echo ' ( ' . wc_price($ticket_price) . ' x ' . $qty . ' ) = ' . wc_price(($ticket_price * $qty)); ?></span>
							</li>
							<?php
							if ($qty > 1) {
								for ($i = 0; $i < $qty; $i++) {
							?>
									<div class="_divider"></div><?php
																do_action('add_wbtm_after_cart_ticket_info', $cart_item, $tic_key);
																$tic_key++;
															}
														} else {
															// Fixed by Shahnur Alam: Use $tic_key instead of $key to maintain proper passenger indexing
															do_action('add_wbtm_after_cart_ticket_info', $cart_item, $tic_key);
															$tic_key++; // Fixed by Shahnur Alam: Increment $tic_key for consistency
														}
														$ticket_count++;
													} ?>
					</ul>
					<div class="_divider"></div>
					<div class="justifyBetween">
						<h5><?php echo WBTM_Translations::text_ticket_sub_total(); ?> :</h5>
						<h5><?php echo wc_price($calculated_total); ?></h5>
					</div>
				</div>
			<?php }
		}
		public function show_cart_ex_service($cart_item)
		{
			$ex_base_price = array_key_exists('wbtm_base_ex_price', $cart_item) ? $cart_item['wbtm_base_ex_price'] : '';
			$extra_service = array_key_exists('wbtm_extra_services', $cart_item) ? $cart_item['wbtm_extra_services'] : [];
			$ex_count      = 0;
			if (sizeof($extra_service) > 0) { ?>
				<h5 class="_mB_xs"><?php echo WBTM_Translations::text_ex_service(); ?></h5>
				<div class="dLayout_xs">
					<ul class="cart_list">
						<?php foreach ($extra_service as $service) { ?>
							<?php if ($ex_count > 0) { ?>
								<li>
									<div class="_divider"></div>
								</li>
							<?php } ?>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_name(); ?> :</h6>
								<span><?php echo esc_html($service['name']); ?></span>
							</li>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_qty(); ?> :</h6>
								<span><?php echo esc_html($service['qty']); ?></span>
							</li>
							<li>
								<h6 class="_mR_xs"><?php echo WBTM_Translations::text_price(); ?> :</h6>
								<span><?php echo ' ( ' . wc_price($service['price']) . ' x ' . $service['qty'] . ' ) = ' . wc_price(($service['price'] * $service['qty'])); ?></span>
							</li>
							<?php $ex_count++; ?>
						<?php } ?>
					</ul>
					<div class="_divider"></div>
					<div class="justifyBetween">
						<h5><?php echo WBTM_Translations::text_ex_service_sub_total(); ?></h5>
						<h5><?php echo wc_price($ex_base_price); ?></h5>
					</div>
				</div>
<?php }
		}
		/*********************/
		public static function add_cpt_data($cpt_name, $title, $meta_data = array(), $status = 'publish', $cat = array())
		{
			// Generate a privacy-safe post title and slug for bus bookings
			if ($cpt_name === 'wbtm_bus_booking') {
				// Use order ID and timestamp for privacy-safe identification
				$order_id = isset($meta_data['wbtm_order_id']) ? $meta_data['wbtm_order_id'] : '';
				$timestamp = current_time('Y-m-d-H-i-s');
				$safe_title = 'Bus Booking #' . $order_id . '-' . $timestamp;
				
				$new_post = array(
					'post_title'    => $safe_title,
					'post_name'     => 'bus-booking-' . $order_id . '-' . $timestamp, // Explicitly set slug
					'post_content'  => '',
					'post_category' => $cat,
					'tags_input'    => array(),
					'post_status'   => $status,
					'post_type'     => $cpt_name
				);
			} else {
				// For other post types, use original behavior
				$new_post = array(
					'post_title'    => $title,
					'post_content'  => '',
					'post_category' => $cat,
					'tags_input'    => array(),
					'post_status'   => $status,
					'post_type'     => $cpt_name
				);
			}
			
			$post_id  = wp_insert_post($new_post);
			if (sizeof($meta_data) > 0) {
				foreach ($meta_data as $key => $value) {
					update_post_meta($post_id, $key, $value);
				}
			}
		}
		
		/**
		 * Clean up existing booking posts that have customer names in URLs
		 * This should be run once to fix existing privacy issues
		 */
		public static function cleanup_existing_booking_urls() {
			// Get all existing bus booking posts
			$bookings = get_posts(array(
				'post_type' => 'wbtm_bus_booking',
				'numberposts' => -1,
				'post_status' => 'publish'
			));
			
			foreach ($bookings as $booking) {
				// Check if the post name contains customer name patterns
				$current_slug = $booking->post_name;
				
				// If the slug looks like a customer name (contains hyphens and letters)
				if (preg_match('/^[a-z]+-[a-z]+/', $current_slug)) {
					// Get order ID from meta
					$order_id = get_post_meta($booking->ID, 'wbtm_order_id', true);
					$timestamp = get_the_date('Y-m-d-H-i-s', $booking->ID);
					
					// Create new privacy-safe slug
					$new_slug = 'bus-booking-' . $order_id . '-' . $timestamp;
					
					// Update the post
					wp_update_post(array(
						'ID' => $booking->ID,
						'post_name' => $new_slug,
						'post_title' => 'Bus Booking #' . $order_id . '-' . $timestamp
					));
				}
			}
		}
	}
	new WBTM_Woocommerce();
}
