<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	class WbtmAddToCart {
		public function __construct() {
			add_filter('woocommerce_add_to_cart_validation', array($this, 'wbtm_check_seat_available_or_not'), 10, 5);
			add_filter('woocommerce_add_cart_item_data', array($this, 'wbtm_add_custom_fields_text_to_cart_item'), 20, 2);
			add_filter('woocommerce_get_item_data', array($this, 'wbtm_display_custom_fields_text_cart'), 20, 2);
			add_action('woocommerce_before_calculate_totals', array($this, 'wbtm_add_custom_price'));
			add_action('woocommerce_after_order_notes', array($this, 'wbtm_custom_checkout_field'));
			add_action('woocommerce_checkout_update_order_meta', array($this, 'wbtm_custom_checkout_field_update_order_meta'));
			add_action('woocommerce_after_checkout_validation', array($this, 'rei_after_checkout_validation'));
			add_action('woocommerce_checkout_create_order_line_item', array($this, 'wbtm_add_custom_fields_text_to_order_items'), 10, 4);
		}
		public function wbtm_check_seat_available_or_not($passed, $product_id, $quantity, $variation_id = '', $variations = '') {
			global $wbtmmain;
			// $seat_name = $wbtmmain->wbtm_array_strip($_POST['seat_name']);
			// $journey_date = sanitize_text_field($_POST['journey_date']);
			// $bus_id = sanitize_text_field($_POST['bus_id']);
			// $start_stops = sanitize_text_field($_POST['start_stops']);
			// $end_stops = sanitize_text_field($_POST['end_stops']);
			// $check_before_order = $wbtmmain->wbtm_get_seat_cehck_before_order($seat_name, $journey_date, $bus_id, $start_stops, $end_stops);
			// if ($check_before_order > 0) {
			//     $passed = false;
			//     wc_add_notice(__('Sorry, Your Selected Seat Already Booked by another user', 'bus-ticket-booking-with-seat-reservation'), 'error');
			// }
			return $passed;
		}
		public function wbtm_add_custom_fields_text_to_cart_item($cart_item_data, $product_id, $variation_id = null) {
			global $wbtmmain;
			// echo '<pre>';print_r($_POST);die;
			$bus_id = isset($_POST['bus_id']) ? sanitize_text_field($_POST['bus_id']) : $product_id;
			if (get_post_type($bus_id) === 'wbtm_bus') {
				$custom_reg_yes_user = array();
				$custom_reg_additional = array();
				$basic_info_user = array();
				$seat_name = array();
				$return_discount = 0;
				$per_seat_price = 0;
				$total_fare = 0;
				$original_fare = 0;
				$return_fare = 0;
				$city_zone = isset($_POST['city_zone']) ? $_POST['city_zone'] : '';
				$start_stops = '';
				$end_stops = '';
				if ($city_zone == '') { // Except Susbscription City zone
					$return_discount = mage_cart_has_opposite_route($_POST['start_stops'], $_POST['end_stops'], $_POST['journey_date']);
					$start_stops = sanitize_text_field($_POST['start_stops']);
					$end_stops = sanitize_text_field($_POST['end_stops']);
				}
				$product_id = get_post_meta($product_id, 'link_wbtm_bus', true) ? get_post_meta($product_id, 'link_wbtm_bus', true) : $product_id;
				$bus_start_time = sanitize_text_field($_POST['bus_start_time']);
				$total_seat = sanitize_text_field($_POST['total_seat']);
				$journey_date = sanitize_text_field($_POST['journey_date']);
				$seat_names = isset($_POST['seat_name']) ? $_POST['seat_name'] : null;
				$seat_qty = isset($_POST['seat_qty']) ? $_POST['seat_qty'] : null;
				$passenger_type = isset($_POST['passenger_type']) ? $_POST['passenger_type'] : '';
				$dd = isset($_POST['bus_dd']) ? $_POST['bus_dd'] : '';
				$custom_reg_user = sanitize_text_field($_POST['custom_reg_user']);
				$wbtm_order_seat_plan = sanitize_text_field($_POST['wbtm_order_seat_plan']);
				$bus_type = $_POST['wbtm_bus_type'];
				$mtsa_billing_type = isset($_POST['mtsa_billing_type']) ? $_POST['mtsa_billing_type'] : '';
				// Pickup Point
				$wbtm_pickpoint = isset($_POST['wbtm_pickpoint']) ? $_POST['wbtm_pickpoint'] : '';
				$wbtm_bus_no = isset($_POST['wbtm_bus_no']) ? $_POST['wbtm_bus_no'] : '';
				$wbtm_bus_name = isset($_POST['wbtm_bus_name']) ? $_POST['wbtm_bus_name'] : '';
				$bus_start_stops = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
				if ($start_stops && $end_stops) {
					$determine_route = mage_determine_route($bus_id, false, $start_stops, $end_stops);
					if ($determine_route != 'wbtm_bus_bp_stops') {
						$bus_start_stops = get_post_meta($bus_id, 'wbtm_bus_bp_stops_return', true);
					}
				}
				$bus_start_stops = maybe_unserialize($bus_start_stops);
				$extra_per_bag_price = get_post_meta($bus_id, 'wbtm_extra_bag_price', true);
				$extra_per_bag_price = $extra_per_bag_price ? $extra_per_bag_price : 0;
				$wbtm_anydate_return = isset($_POST['wbtm_anydate_return']) ? $_POST['wbtm_anydate_return'] : '';
				$wbtm_anydate_return_price = $_POST['wbtm_anydate_return_price'];
				// Get Bus Start Time
				if ($bus_start_stops) {
					foreach ($bus_start_stops as $stop) {
						if ($stop['wbtm_bus_bp_stops_name'] == $start_stops) {
							$bus_start_time = $stop['wbtm_bus_bp_start_time'];
							break;
						}
					}
				}
				$passenger_type_num = array('Adult' => 0, 'Child' => 1, 'Infant' => 2,);
				// Init Values
				$original_fare = 0;
				$return_fare = 0;
				$is_return = false;
				$total_fare = 0;
				$extra_services = array();
				$total_extra_price = 0;
				$total_fare = $total_fare + (float)$wbtm_anydate_return_price;
				if ($wbtm_order_seat_plan === 'yes') {
					// With Seat Plan
					if ($return_discount == 1 && count($passenger_type) == 1) {
						$return_discount = 2;
					}
					if (!empty($seat_names)) {
						$j = 0;
						foreach ($seat_names as $seat) {
							$bag_price = 0;
							$seat_name[$j]['wbtm_seat_name'] = $seat;
							if ($return_discount == 2) {
								$is_return = true;
							}
							else {
								$is_return = false;
							}
							$d = ($dd[$j] == 'yes' ? true : false);
							// Price
							$per_seat_price = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $d, $passenger_type[$j]);
							$per_seat_price_original = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $d, $passenger_type[$j]);
							$per_seat_price_return = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $d, $passenger_type[$j], true);
							// Custom reg user yes
							if ($_POST['custom_reg_user'] == 'yes') {
								$custom_reg_yes_user[$j]['wbtm_user_name'] = (isset($_POST['wbtm_user_name'][$j]) ? $_POST['wbtm_user_name'][$j] : '');
								$custom_reg_yes_user[$j]['wbtm_user_email'] = (isset($_POST['wbtm_user_email'][$j]) ? $_POST['wbtm_user_email'][$j] : '');
								$custom_reg_yes_user[$j]['wbtm_user_phone'] = (isset($_POST['wbtm_user_phone'][$j]) ? $_POST['wbtm_user_phone'][$j] : '');
								$custom_reg_yes_user[$j]['wbtm_user_gender'] = (isset($_POST['wbtm_user_gender'][$j]) ? $_POST['wbtm_user_gender'][$j] : '');
								$custom_reg_yes_user[$j]['wbtm_extra_bag_qty'] = $bag_qty = (isset($_POST['extra_bag_quantity'][$j]) ? $_POST['extra_bag_quantity'][$j] : 0);
								$bag_price = ($bag_qty * $extra_per_bag_price);
								$custom_reg_yes_user[$j]['wbtm_extra_bag_price'] = $bag_price;
								// Additional reg builder field
								$reg_form_arr = unserialize(get_post_meta($bus_id, 'attendee_reg_form', true));
								if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
									foreach ($reg_form_arr as $builder) {
										$custom_reg_additional[$j][] = array('name' => $builder['field_label'], 'value' => (isset($_POST[$builder['field_id']][$j]) ? $_POST[$builder['field_id']][$j] : ''),);
									}
								}
							}
							// Price
							if ($per_seat_price) {
								$total_fare = (float)$per_seat_price + $total_fare + $bag_price;
								$original_fare = (float)$per_seat_price_original + $original_fare + $bag_price;
								$return_fare = (float)$per_seat_price_return + $return_fare + $bag_price;
							}
							// Basic Info
							$basic_info_user[$j]['wbtm_seat_fare'] = $per_seat_price;
							$basic_info_user[$j]['wbtm_passenger_type'] = array_search($passenger_type[$j], $passenger_type_num);
							$j++;
						}
					}
				}
				elseif ($wbtm_order_seat_plan === 'no') {
					// Without Seat Plan
					if ($return_discount == 1 && array_sum($seat_qty) == 1) {
						$return_discount = 2;
					}
					if ($seat_qty) {
						$total_seats = array_sum($seat_qty);
						$j = 0;
						foreach ($seat_qty as $key => $qty) {
							if ($qty > 0) {
								for ($i = 0; $i < (int)$qty; $i++) {
									$bag_price = 0;
									// Seat
									if ($passenger_type) {
										$seat_name[$j]['wbtm_seat_name'] = $passenger_type[$key] . '(1)';
									}
									else {
										$seat_name[$j]['wbtm_seat_name'] = 'Private';
									}
									if ($bus_type === 'sub') { // Subscription Bus
										// Price
										$per_seat_price = mtsa_bus_price_get($bus_id, $start_stops, $end_stops, $mtsa_billing_type, $passenger_type[$key], $city_zone);
										$per_seat_price_original = $per_seat_price;
										$per_seat_price_return = $per_seat_price_original;
										// Price END
										$is_return = false;
									}
									elseif ($bus_type === 'private') { // Private Bus
										$per_seat_price = mtpa_bus_price_get($bus_id, $start_stops, $end_stops);
										$per_seat_price_original = $per_seat_price;
										$per_seat_price_return = $per_seat_price_original;
										$is_return = false;
									}
									else { // General Bus But Not seat plan
										if ($return_discount == 2) {
											$is_return = true;
										}
										else {
											$is_return = false;
										}
										$per_seat_price = mage_bus_seat_price($bus_id, $start_stops, $end_stops, false, $passenger_type_num[$passenger_type[$key]]);
										$per_seat_price_original = mage_bus_seat_price($bus_id, $start_stops, $end_stops, false, $passenger_type_num[$passenger_type[$key]]);
										$per_seat_price_return = mage_bus_seat_price($bus_id, $start_stops, $end_stops, false, $passenger_type_num[$passenger_type[$key]], true);
									}
									// Custom reg user yes
									if ($_POST['custom_reg_user'] == 'yes') {
										$custom_reg_yes_user[$j]['wbtm_user_name'] = (isset($_POST['wbtm_user_name'][$j]) ? $_POST['wbtm_user_name'][$j] : '');
										$custom_reg_yes_user[$j]['wbtm_user_email'] = (isset($_POST['wbtm_user_email'][$j]) ? $_POST['wbtm_user_email'][$j] : '');
										$custom_reg_yes_user[$j]['wbtm_user_phone'] = (isset($_POST['wbtm_user_phone'][$j]) ? $_POST['wbtm_user_phone'][$j] : '');
										$custom_reg_yes_user[$j]['wbtm_user_gender'] = (isset($_POST['wbtm_user_gender'][$j]) ? $_POST['wbtm_user_gender'][$j] : '');
										$custom_reg_yes_user[$j]['wbtm_extra_bag_qty'] = $bag_qty = (isset($_POST['extra_bag_quantity'][$j]) ? $_POST['extra_bag_quantity'][$j] : 0);
										$bag_price = ($bag_qty * $extra_per_bag_price);
										$custom_reg_yes_user[$j]['wbtm_extra_bag_price'] = $bag_price;
										// Additional reg builder field
										$reg_form_arr = unserialize(get_post_meta($bus_id, 'attendee_reg_form', true));
										if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
											foreach ($reg_form_arr as $builder) {
												$custom_reg_additional[$j][] = array('name' => $builder['field_label'], 'value' => (isset($_POST[$builder['field_id']][$j]) ? $_POST[$builder['field_id']][$j] : ''),);
											}
										}
									}
									// Price
									if ($per_seat_price) {
										$total_fare = (float)$per_seat_price + $total_fare + $bag_price;
										$original_fare = (float)$per_seat_price_original + $original_fare + $bag_price;
										$return_fare = (float)$per_seat_price_return + $return_fare + $bag_price;
									}
									// Price END
									// Basic Info
									$basic_info_user[$j]['wbtm_seat_fare'] = $per_seat_price;
									$basic_info_user[$j]['wbtm_passenger_type'] = is_array($passenger_type) ? $passenger_type[$key] : 'Private';
									$j++;
								}
							}
						}
					}
				}
				// Extra Service
				$extra_service_qty = isset($_POST['extra_service_qty']) ? $_POST['extra_service_qty'] : array();
				$extra_service_name = isset($_POST['extra_service_name']) ? $_POST['extra_service_name'] : array();
				$extra_service_price = isset($_POST['extra_service_price']) ? $_POST['extra_service_price'] : array();
				$total_extra_service_qty = array_sum($extra_service_qty);
				if ($total_extra_service_qty > 0) {
					$extra_service_i = 0;
					foreach ($extra_service_qty as $extra_item) {
						if ($extra_item > 0) {
							$extra_services[] = array('name' => isset($extra_service_name[$extra_service_i]) ? $extra_service_name[$extra_service_i] : '', 'qty' => $extra_item, 'price' => isset($extra_service_price[$extra_service_i]) ? $extra_service_price[$extra_service_i] : 0,);
							$total_extra_price += $extra_services[$extra_service_i]['qty'] * $extra_services[$extra_service_i]['price'];
						}
						$extra_service_i++;
					}
					// Custom reg user yes
					$j = 0;
					if ($_POST['custom_reg_user'] == 'yes' && empty($custom_reg_yes_user)) {
						$custom_reg_yes_user[$j]['wbtm_user_name'] = (isset($_POST['wbtm_user_name'][$j]) ? $_POST['wbtm_user_name'][$j] : '');
						$custom_reg_yes_user[$j]['wbtm_user_email'] = (isset($_POST['wbtm_user_email'][$j]) ? $_POST['wbtm_user_email'][$j] : '');
						$custom_reg_yes_user[$j]['wbtm_user_phone'] = (isset($_POST['wbtm_user_phone'][$j]) ? $_POST['wbtm_user_phone'][$j] : '');
						$custom_reg_yes_user[$j]['wbtm_user_gender'] = (isset($_POST['wbtm_user_gender'][$j]) ? $_POST['wbtm_user_gender'][$j] : '');
						$custom_reg_yes_user[$j]['wbtm_extra_bag_qty'] = $bag_qty = (isset($_POST['extra_bag_quantity'][$j]) ? $_POST['extra_bag_quantity'][$j] : 0);
						$bag_price = ($bag_qty * $extra_per_bag_price);
						$custom_reg_yes_user[$j]['wbtm_extra_bag_price'] = $bag_price;
						// Additional reg builder field
						$reg_form_arr = unserialize(get_post_meta($bus_id, 'attendee_reg_form', true));
						if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
							foreach ($reg_form_arr as $builder) {
								$custom_reg_additional[$j][] = array('name' => $builder['field_label'], 'value' => (isset($_POST[$builder['field_id']][$j]) ? $_POST[$builder['field_id']][$j] : ''),);
							}
						}
					}
					// Basic Info
					if (empty($basic_info_user)) {
						$basic_info_user[$j]['wbtm_seat_fare'] = $total_extra_price;
					}
					if ($total_fare == 0) {
						$total_fare += $bag_price;
					}
				}
				$total_fare = $total_fare + $total_extra_price;
				// Extra Service END
				// Add to Cart
				$cart_item_data['wbtm_seats'] = $seat_name;
				$cart_item_data['wbtm_start_stops'] = $start_stops;
				$cart_item_data['wbtm_end_stops'] = $end_stops;
				$cart_item_data['wbtm_journey_date'] = $journey_date;
				$cart_item_data['wbtm_journey_time'] = $bus_start_time;
				$cart_item_data['wbtm_bus_time'] = $bus_start_time;
				$cart_item_data['wbtm_total_seats'] = $total_seat;
				$cart_item_data['wbtm_seat_original_fare'] = $original_fare;
				$cart_item_data['wbtm_seat_return_fare'] = $return_fare;
				$cart_item_data['is_return'] = $is_return;
				$cart_item_data['wbtm_billing_type'] = $mtsa_billing_type;
				$cart_item_data['wbtm_city_zone'] = $city_zone;
				$cart_item_data['wbtm_pickpoint'] = $wbtm_pickpoint;
				$cart_item_data['wbtm_bus_no'] = $wbtm_bus_no;
				$cart_item_data['wbtm_bus_name'] = $wbtm_bus_name;
				$cart_item_data['extra_services'] = $extra_services;
				$cart_item_data['wbtm_anydate_return'] = $wbtm_anydate_return;
				$cart_item_data['wbtm_anydate_return_price'] = $wbtm_anydate_return_price;
				$cart_item_data['wbtm_passenger_info'] = $custom_reg_yes_user;
				$cart_item_data['wbtm_passenger_info_additional'] = $custom_reg_additional;
				$cart_item_data['wbtm_single_passenger_info'] = $custom_reg_yes_user;
				$cart_item_data['wbtm_basic_passenger_info'] = $basic_info_user;
				$cart_item_data['wbtm_tp'] = $total_fare;
				$cart_item_data['wbtm_bus_id'] = $bus_id;
				$cart_item_data['line_total'] = $total_fare;
				$cart_item_data['line_subtotal'] = $total_fare;
				$cart_item_data['bus_id'] = $product_id;
			}
			//echo '<pre>'; print_r($cart_item_data); die();
			return $cart_item_data;
		}
		public function wbtm_add_custom_price($cart_object) {
			foreach ($cart_object->cart_contents as $key => $value) {
				$eid = $value['bus_id'];
				if (get_post_type($eid) == 'wbtm_bus') {
					$cp = $value['wbtm_tp'];
					$value['data']->set_price($cp);
					$new_price = $value['data']->get_price();
					$value['data']->set_price($cp);
					$value['data']->set_regular_price($cp);
					$value['data']->set_sale_price($cp);
					$value['data']->set_sold_individually('yes');
				}
			}
		}
		public function wbtm_display_custom_fields_text_cart($item_data, $cart_item) {
			if (!is_admin()) {
				global $wbtmmain;
				if (get_post_type($cart_item['bus_id']) === 'wbtm_bus') {
					$wbtm_seats = $cart_item['wbtm_seats'];
					$extra_bag_quantity = isset($cart_item['extra_bag_quantity']) ? $cart_item['extra_bag_quantity'] : 0;
					$passenger_info = $cart_item['wbtm_passenger_info'];
					$passenger_info_additional = $cart_item['wbtm_passenger_info_additional'];
					$basic_passenger_info = $cart_item['wbtm_basic_passenger_info'];
					$date_format = get_option('date_format');
					$time_format = get_option('time_format');
					$datetimeformat = $date_format . '  ' . $time_format;
					$wbtm_anydate_return = $cart_item['wbtm_anydate_return'];
					$wbtm_anydate_return_price = $cart_item['wbtm_anydate_return_price'];
					// echo '<pre>';  print_r($cart_item);  echo '<pre>'; exit;
					// echo '<pre>'; print_r($passenger_info); die;
					// $billing_type_items = mtsa_get_billing_type_items();
					// if ($billing_type_items) {
					//     $billing_cycles = $billing_type_items;
					// } else {
					//     $billing_cycles = array();
					// }
					// if ($wbtm_seats) {
					$extra_bag_price = get_post_meta($cart_item['bus_id'], 'wbtm_extra_bag_price', true);
					// echo '<pre>'; print_r($passenger_info); die;
					if (is_array($passenger_info) && sizeof($passenger_info) > 0) { // With Form builder
						$i = 0;
						foreach ($passenger_info as $_passenger) {
							//echo '<pre>';print_r($_passenger);echo '</pre>';
							?>
							<ul class=event-custom-price>
								<?php
									if (isset($_passenger['wbtm_user_name']) && $_passenger['wbtm_user_name'] != '') {
										?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_name_text', __('Name', 'bus-ticket-booking-with-seat-reservation')); ?> :</strong>
											<?php echo $_passenger['wbtm_user_name']; ?>
										</li>
										<?php
									}
									if (isset($_passenger['wbtm_user_email']) && $_passenger['wbtm_user_email'] != '') {
										?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_email_text', __('Email:', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											<?php echo $_passenger['wbtm_user_email']; ?>
										</li>
										<?php
									}
									if (isset($_passenger['wbtm_user_phone']) && $_passenger['wbtm_user_phone'] != '') {
										?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_phone_text', __('Phone:', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											<?php echo $_passenger['wbtm_user_phone']; ?>
										</li>
										<?php
									}
									if (isset($_passenger['wbtm_user_gender']) && $_passenger['wbtm_user_gender'] != '') {
										?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_gender_text', __('Gender:', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											<?php echo $_passenger['wbtm_user_gender']; ?>
										</li>
										<?php
									}
									if (isset($_passenger['wbtm_user_address'])) {
										?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_address_text', __('Address:', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											<?php echo $_passenger['wbtm_user_address']; ?>
										</li>
										<?php
									}
									// $reg_form_arr = unserialize(get_post_meta($cart_item['bus_id'], 'attendee_reg_form', true));
									// echo '<pre>'; print_r($passenger_info_additional); die;
									if (is_array($passenger_info_additional) && sizeof($passenger_info_additional) > 0) {
										foreach ($passenger_info_additional[$i] as $builder) {
											?>
											<li>
												<strong><?php echo $builder['name'] . ':</strong> ' . $builder['value']; ?>
											</li>
											<?php
										}
									}
								?>

								<?php if (isset($wbtm_seats[$i]['wbtm_seat_name'])) : ?>
									<li>
										<strong><?php mage_bus_label('wbtm_seat_no_text', __('Seat No', 'bus-ticket-booking-with-seat-reservation')); ?>
											:</strong>
										<?php echo $wbtm_seats[$i]['wbtm_seat_name']; ?>
									</li>
								<?php endif; ?>
								<?php if (isset($basic_passenger_info[$i]['wbtm_passenger_type'])) { ?>
									<?php if ($basic_passenger_info[$i]['wbtm_passenger_type'] != '') { ?>
										<li>
											<strong><?php _e('Passenger Type', 'bus-ticket-booking-with-seat-reservation'); ?>
												:</strong>
											<?php echo wbtm_get_seat_type_label(strtolower($basic_passenger_info[$i]['wbtm_passenger_type']), $basic_passenger_info[$i]['wbtm_passenger_type']); ?>
										</li>
									<?php } ?>
								<?php } ?>
								<?php
									if ($cart_item['wbtm_billing_type'] != '') {
										$valid_till = mtsa_calculate_valid_date(mage_wp_date($cart_item['wbtm_journey_date'], 'Y-m-d'), $cart_item['wbtm_billing_type']);
										?>
										<li>
											<strong><?php _e(__('Start Date', 'bus-ticket-booking-with-seat-reservation')); ?>
												:</strong>
											<?php echo mage_wp_date($cart_item['wbtm_journey_date']); ?>
										</li>
										<li>
											<strong><?php _e('Valid Till', 'bus-ticket-booking-with-seat-reservation'); ?>
												:</strong>
											<?php echo mage_wp_date($valid_till); ?>
										</li>
										<li>
											<strong><?php _e('Billing Type', 'bus-ticket-booking-with-seat-reservation'); ?>
												:</strong>
											<?php echo ucwords($cart_item['wbtm_billing_type']); ?>
										</li>
										<?php
										if ($cart_item['wbtm_city_zone'] != '') {
											$term = get_term($cart_item['wbtm_city_zone'], 'mtsa_city_zone');
											?>
											<li>
												<strong><?php _e('Zone', 'bus-ticket-booking-with-seat-reservation'); ?>
													:</strong>
												<?php echo $term->name; ?>
											</li>
										<?php } else { ?>
											<li>
												<strong><?php mage_bus_label('wbtm_boarding_points_text', __('Boarding Point', 'bus-ticket-booking-with-seat-reservation')); ?>
													:</strong>
												<?php echo $cart_item['wbtm_start_stops']; ?>
											</li>
											<li>
												<strong><?php mage_bus_label('wbtm_dropping_points_text', __('Dropping Point', 'bus-ticket-booking-with-seat-reservation')); ?>
													:</strong>
												<?php echo $cart_item['wbtm_end_stops']; ?>
											</li>
										<?php }
									}
									else { ?>
										<li>
											<strong><?php mage_bus_label('wbtm_cart_journey_date_text', __('Journey Date', 'bus-ticket-booking-with-seat-reservation')); ?>
												:</strong>
											<?php //echo get_wbtm_datetime($cart_item['wbtm_journey_date'], 'date-text'); ?>
											<?php echo mage_wp_date($cart_item['wbtm_journey_date']); ?>
										</li>
										<li>
											<strong><?php mage_bus_label('wbtm_start_time_text', __('Start Time', 'bus-ticket-booking-with-seat-reservation')); ?>
												:</strong>
											<?php echo mage_wp_time($cart_item['wbtm_journey_time']); ?>
										</li>
										<li>
											<strong><?php mage_bus_label('wbtm_boarding_points_text', __('Boarding Point', 'bus-ticket-booking-with-seat-reservation')); ?>
												:</strong>
											<?php echo $cart_item['wbtm_start_stops']; ?>
										</li>
										<li>
											<strong><?php mage_bus_label('wbtm_dropping_points_text', __('Dropping Point', 'bus-ticket-booking-with-seat-reservation')); ?>
												:</strong>
											<?php echo $cart_item['wbtm_end_stops']; ?>
										</li>
									<?php }
								?>
								<?php if ($cart_item['wbtm_pickpoint']) : ?>
									<li>
										<strong><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
										<?php echo $cart_item['wbtm_pickpoint']; ?>
									</li>
								<?php endif; ?>
								<?php if (isset($basic_passenger_info[$i]['wbtm_seat_fare'])) : ?>
									<li>
										<strong><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?>
											:</strong>
										<?php echo wc_price($basic_passenger_info[$i]['wbtm_seat_fare']); ?>
									</li>
								<?php endif; ?>
								<?php
									if (isset($_passenger['wbtm_extra_bag_qty'])) {
										if ($_passenger['wbtm_extra_bag_qty'] > 0) {
											?>
											<li>
												<strong><?php mage_bus_label('wbtm_extra_bag_text', __('Extra Bag', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong>
												<?php echo $_passenger['wbtm_extra_bag_qty']; ?>
											</li>
											<li>
												<strong><?php mage_bus_label('wbtm_extra_bag_price_text', __('Extra Bag Price', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong>
												<?php echo wc_price($_passenger['wbtm_extra_bag_price']); ?>
											</li>
											<li>
												<strong><?php mage_bus_label('wbtm_total_text', __('Total', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong>
												<?php echo wc_price($basic_passenger_info[$i]['wbtm_seat_fare']) . ' + ' . wc_price($_passenger['wbtm_extra_bag_price']) . ' = ' . wc_price($basic_passenger_info[$i]['wbtm_seat_fare'] + $_passenger['wbtm_extra_bag_price']); ?>
											</li>
											<?php
										}
									}
								?>
							</ul>
							<?php
							if (($cart_item['is_return'] == 1) && ($cart_item['wbtm_seat_original_fare'] > $cart_item['wbtm_seat_return_fare'])) {
								$percent = ($cart_item['wbtm_seat_return_fare'] * 100) / $cart_item['wbtm_seat_original_fare'];
								$percent = 100 - $percent;
								echo '<p style="color:#af7a2d;font-size: 14px;line-height: 1em;"><strong>' . __('Congratulation!', 'bus-ticket-booking-with-seat-reservation') . '</strong> <span> ' . __('For a round trip, you got', 'bus-ticket-booking-with-seat-reservation') . ' <span style="font-weight:600">' . number_format($percent, 2) . '%</span> ' . __('discount on this trip', 'bus-ticket-booking-with-seat-reservation') . '</span></p>';
							}
							$i++;
						}
					}
					else {
						?>
						<ul class='event-custom-price'>
							<?php
								if ($wbtm_seats) : ?>
									<li>
										<?php echo $wbtmmain->bus_get_option('wbtm_seat_list_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_seat_list_text', 'label_setting_sec') . ': ' : __('Seat List:', 'bus-ticket-booking-with-seat-reservation');
											$seat_lists = array_column($wbtm_seats, 'wbtm_seat_name');
											echo implode(', ', $seat_lists);
										?>
									</li>
								<?php
								endif;
								if ($cart_item['wbtm_billing_type'] != '') :
									$valid_till = mtsa_calculate_valid_date(get_wbtm_datetime($cart_item['wbtm_journey_date'], 'date-text'), $cart_item['wbtm_billing_type']);
									?>
									<li><?php _e('Start Date: ', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo $cart_item['wbtm_journey_date']; ?></li>
									<li><?php _e('Valid Till: ', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo $valid_till; ?></li>
									<li><?php _e('Billing Type: ', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo $cart_item['wbtm_billing_type']; ?></li>
									<?php if ($cart_item['wbtm_city_zone'] != '') :
									$term = get_term($cart_item['wbtm_city_zone'], 'mtsa_city_zone'); ?>
									<li><?php _e('Zone: ', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo $term->name; ?></li>
								<?php else : ?>
									<li>hh<?php mage_bus_label('wbtm_boarding_points_text', __('Boarding Point', 'bus-ticket-booking-with-seat-reservation'));
										?><?php echo $cart_item['wbtm_start_stops']; ?></li>
									<li>hh<?php mage_bus_label('wbtm_dropping_points_text', __('Dropping Point', 'bus-ticket-booking-with-seat-reservation'));
										?><?php echo $cart_item['wbtm_end_stops']; ?></li>
								<?php endif; ?>
								<?php else : ?>
									<li><?php echo $wbtmmain->bus_get_option('wbtm_select_journey_date_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_select_journey_date_text', 'label_setting_sec') . ': ' : __('Journey Date:', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo mage_wp_date($cart_item['wbtm_journey_date']); ?></li>
									<li><?php echo $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') . ': ' : __('Journey Time:', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo mage_wp_time($cart_item['wbtm_journey_time']); ?></li>
									<li><?php echo $wbtmmain->bus_get_option('wbtm_boarding_points_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_boarding_points_text', 'label_setting_sec') . ': ' : __('Boarding Point:', 'bus-ticket-booking-with-seat-reservation');
										?><?php echo $cart_item['wbtm_start_stops']; ?></li>
									<li><?php echo $wbtmmain->bus_get_option('wbtm_dropping_points_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_dropping_points_text', 'label_setting_sec') . ': ' : __('Dropping Point:', 'bus-ticket-booking-with-seat-reservation'); ?><?php echo $cart_item['wbtm_end_stops']; ?>
									</li>
								<?php endif; ?>

							<?php if ($cart_item['wbtm_bus_no']) : ?>
								<li><?php _e('Bus No', 'bus-ticket-booking-with-seat-reservation'); ?>: <?php echo $cart_item['wbtm_bus_name']; ?> - <?php echo $cart_item['wbtm_bus_no']; ?></li>
							<?php endif; ?>

							<?php if ($cart_item['wbtm_pickpoint']) : ?>
								<li><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?>: <?php echo $cart_item['wbtm_pickpoint']; ?></li>
							<?php endif; ?>




							<?php if ($extra_bag_quantity > 0) { ?>
								<li><?php _e('Extra Bag: ', 'bus-ticket-booking-with-seat-reservation');
										echo '(' . $cart_item['extra_bag_quantity'] . ' x ' . $extra_bag_price . ') = ' . wc_price($cart_item['extra_bag_quantity'] * $extra_bag_price); ?>
								</li>
							<?php } ?>
						</ul>
						<?php
						if (($cart_item['is_return'] == 1) && ($cart_item['wbtm_seat_original_fare'] > $cart_item['wbtm_seat_return_fare'])) {
							$percent = ($cart_item['wbtm_seat_return_fare'] * 100) / $cart_item['wbtm_seat_original_fare'];
							$percent = 100 - $percent;
							echo '<p style="color:#af7a2d;font-size: 14px;line-height: 1em;"><strong>' . __('Congratulation!', 'bus-ticket-booking-with-seat-reservation') . '</strong> <span> ' . __('For a round trip, you got', 'bus-ticket-booking-with-seat-reservation') . ' <span style="font-weight:600">' . number_format($percent, 2) . '%</span> ' . __('discount on this trip', 'bus-ticket-booking-with-seat-reservation') . '</span></p>';
						}
					}
					// }
					?>
					<?php
					if ($cart_item['extra_services']) : ?>
						<p style="margin:0"><strong><?php _e('Extra Services', 'bus-ticket-booking-with-seat-reservation') ?></strong></p>
						<ul style="margin:0">
							<?php foreach ($cart_item['extra_services'] as $service) : ?>
								<li><?php echo __($service['name'], 'bus-ticket-booking-with-seat-reservation') . ' - ' . wc_price($service["price"]) . ' x ' . $service['qty'] . ' = ' . wc_price($service["price"] * $service['qty']);; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php
					endif;
					if ($wbtm_anydate_return == 'on') { ?>
						<p style="margin:0"><strong><?php _e('Any Date Return:', 'bus-ticket-booking-with-seat-reservation') ?></strong> <?php echo wc_price($wbtm_anydate_return_price); ?></p>
						<?php
					}
				}
				return $item_data;
			}
		}
		public function rei_after_checkout_validation($posted) {
			global $woocommerce, $wbtmmain;
			$items = $woocommerce->cart->get_cart();
			foreach ($items as $item => $values) {
				if (get_post_type($values['bus_id']) == 'wbtm_bus') {
					$wbtm_seats = $values['wbtm_seats'];
					$wbtm_passenger_info = $values['wbtm_passenger_info'];
					$wbtm_passenger_info_additional = $values['wbtm_passenger_info_additional'];
					$wbtm_basic_passenger_info = $values['wbtm_basic_passenger_info'];
					$wbtm_start_stops = $values['wbtm_start_stops'];
					$wbtm_end_stops = $values['wbtm_end_stops'];
					$wbtm_journey_date = $values['wbtm_journey_date'];
					$wbtm_journey_time = $values['wbtm_journey_time'];
					$wbtm_bus_start_time = $values['wbtm_bus_time'];
					$wbtm_bus_id = $values['wbtm_bus_id'];
					$se = $wbtm_seats[0]['wbtm_seat_name'];
					$is_booked = mage_partial_seat_booked_count(false, $se, $wbtm_bus_id, $wbtm_start_stops, $wbtm_end_stops, $wbtm_journey_date);
					$bus_type = get_post_meta($wbtm_bus_id, 'wbtm_seat_type_conf', true);
					if ($bus_type == 'wbtm_seat_plan') {
						if ($is_booked['has_booked']) {
							WC()->cart->empty_cart();
							wc_add_notice(__("Sorry, Your Selected Seat Already Booked by another user", 'woocommerce'), 'error');
						}
					}
					if ($bus_type == 'wbtm_without_seat_plan') {
						$total_seat = get_post_meta($wbtm_bus_id, 'wbtm_total_seat', true);
						if ($total_seat <= $is_booked) {
							WC()->cart->empty_cart();
							wc_add_notice(__("Sorry, Your Selected Seat Already Booked by another user", 'woocommerce'), 'error');
						}
					}
				}
			}

		}
		public function wbtm_add_custom_fields_text_to_order_items($item, $cart_item_key, $values, $order) {
			$eid = $values['bus_id'];
			if (get_post_type($eid) == 'wbtm_bus') {
				$wbtm_seats = $values['wbtm_seats'];
				$wbtm_passenger_info = $values['wbtm_passenger_info'];
				$wbtm_passenger_info_additional = $values['wbtm_passenger_info_additional'];
				$wbtm_single_passenger_info = $values['wbtm_single_passenger_info'];
				$wbtm_basic_passenger_info = $values['wbtm_basic_passenger_info'];
				$wbtm_billing_type = $values['wbtm_billing_type'];
				$wbtm_city_zone = $values['wbtm_city_zone'];
				$wbtm_pickpoint = $values['wbtm_pickpoint'];
				$wbtm_bus_no = get_the_title($eid) . (($values['wbtm_bus_no']) ? (' - ' . $values['wbtm_bus_no']) : '');
				$extra_services = $values['extra_services'];
				$wbtm_start_stops = $values['wbtm_start_stops'];
				$wbtm_end_stops = $values['wbtm_end_stops'];
				$wbtm_journey_date = $values['wbtm_journey_date'];
				$wbtm_journey_time = $values['wbtm_journey_time'];
				$wbtm_bus_start_time = $values['wbtm_bus_time'];
				$wbtm_bus_id = $values['wbtm_bus_id'];
				$wbtm_is_return = $values['is_return'];
				$extra_bag_quantity = isset($values['extra_bag_quantity']) ? $values['extra_bag_quantity'] : null;
				$wbtm_tp = $values['wbtm_tp'];
				$wbtm_anydate_return = $values['wbtm_anydate_return'];
				$wbtm_anydate_return_price = $values['wbtm_anydate_return_price'];
				$seat = "";
				foreach ($wbtm_seats as $field) {
					// $item->add_meta_data( __( esc_attr($field['wbtm_seat_name'])));
					$seat .= $field['wbtm_seat_name'] . ",";
				}
				// Extra Services
				if ($extra_services) {
					$extra_service_html = '';
					$extra_service_i = 0;
					foreach ($extra_services as $extra_item) {
						if ($extra_item > 0) {
							$name = isset($extra_item['name']) ? $extra_item['name'] : '';
							$qty = isset($extra_item['qty']) ? $extra_item['qty'] : 0;
							$price = isset($extra_item['price']) ? $extra_item['price'] : 0;
							$extra_service_html .= '(' . ($extra_service_i + 1) . '). ' . $name . ' - ' . $qty . ' x ' . $price . '= ' . ($qty * $price) . '   ';
						}
						$extra_service_i++;
					}
				}
				else {
					$extra_service_html = '';
				}
				// .$seat =0;
				$item->add_meta_data('Seats', $seat);
				$item->add_meta_data('Start', $wbtm_start_stops);
				$item->add_meta_data('End', $wbtm_end_stops);
				$item->add_meta_data('Date', $wbtm_journey_date);
				$item->add_meta_data('Time', $wbtm_journey_time);
				$item->add_meta_data('Extra Services', $extra_service_html);
				$item->add_meta_data('Anydate Return', $wbtm_anydate_return);
				$item->add_meta_data('Anydate Return Price', $wbtm_anydate_return_price);
				$item->add_meta_data('_wbtm_tp', $wbtm_tp);
				$item->add_meta_data('_bus_id', $wbtm_bus_id);
				$item->add_meta_data('_btime', $wbtm_bus_start_time);
				$item->add_meta_data('_wbtm_passenger_info', $wbtm_passenger_info);
				$item->add_meta_data('_wbtm_passenger_info_additional', $wbtm_passenger_info_additional);
				$item->add_meta_data('_wbtm_single_passenger_info', $wbtm_single_passenger_info);
				$item->add_meta_data('_wbtm_basic_passenger_info', $wbtm_basic_passenger_info);
				$item->add_meta_data('_wbtm_billing_type', $wbtm_billing_type);
				$item->add_meta_data('_wbtm_city_zone', $wbtm_city_zone);
				$item->add_meta_data('_wbtm_pickpoint', $wbtm_pickpoint);
				$item->add_meta_data('_wbtm_bus_no', $wbtm_bus_no);
				$item->add_meta_data('_extra_services', $extra_services);
				$item->add_meta_data('_wbtm_is_return', $wbtm_is_return);
				$item->add_meta_data('_wbtm_bus_id', $eid);
				$item->add_meta_data('_wbtm_anydate_return', $wbtm_anydate_return);
				$item->add_meta_data('_wbtm_anydate_return_price', $wbtm_anydate_return_price);
			}
		}
		/**
		 * Add custom field to the checkout page
		 */
		function wbtm_custom_checkout_field($checkout) {
			$get_settings = get_option('wbtm_bus_settings');
			$get_val = isset($get_settings['custom_fields']) ? $get_settings['custom_fields'] : '';
			$output = $get_val ? $get_val : null;
			if ($output) {
				$get_custom_fields_arr = explode(',', $output);
				if ($get_custom_fields_arr) {
					echo '<div id="custom_checkout_field"><h2>' . __('Additional field') . '</h2>';
					foreach ($get_custom_fields_arr as $item) {
						$item = trim($item);
						$name = ucfirst(str_replace('_', ' ', $item));
						woocommerce_form_field('wbtm_custom_field_' . $item, array('type' => 'text', 'class' => array('my-field-class form-row-wide'), 'label' => __($name), 'placeholder' => __($name),), $checkout->get_value('wbtm_custom_field_' . $item));
					}
					echo '</div>';
				}
			}
		}
		function wbtm_custom_checkout_field_update_order_meta($order_id) {
			$order = wc_get_order($order_id);
			foreach ($order->get_items() as $item_id => $item_obj) {
				$has_extra_service = wc_get_order_item_meta($item_id, '_extra_services');
				if ($has_extra_service) {
					update_post_meta($order_id, '_extra_services', $has_extra_service);
				}
			}
			$get_settings = get_option('wbtm_bus_settings');
			$get_val = isset($get_settings['custom_fields']) ? $get_settings['custom_fields'] : '';
			$output = $get_val ? $get_val : null;
			if ($output) {
				$get_custom_fields_arr = explode(',', $output);
				if ($get_custom_fields_arr) {
					foreach ($get_custom_fields_arr as $item) {
						$item = trim($item);
						if (!empty($_POST['wbtm_custom_field_' . $item])) {
							update_post_meta($order_id, 'wbtm_custom_field_' . $item, sanitize_text_field($_POST['wbtm_custom_field_' . $item]));
						}
					}
				}
			}
		}
	}
	new WbtmAddToCart();