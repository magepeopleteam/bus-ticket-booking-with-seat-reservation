<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Functions')) {
		class WBTM_Functions {
			public static function template_path($file_name): string {
				$template_path = get_stylesheet_directory() . '/templates/';
				$default_dir = WBTM_PLUGIN_DIR . '/templates/';
				$dir = is_dir($template_path) ? $template_path : $default_dir;
				$file_path = $dir . $file_name;
				return locate_template(array('templates/' . $file_name)) ? $file_path : $default_dir . $file_name;
			}
			//==========================//
			public static function get_bus_route($post_id = 0, $start_route = '') {
				$all_routes = [];
				if ($post_id > 0) {
					$count_next = 0;
					$full_route_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
					if (sizeof($full_route_infos) > 0) {
						foreach ($full_route_infos as $info) {
							if ($start_route) {
								if ($count_next > 0 && ($info['type'] == 'dp' || $info['type'] == 'both')) {
									$all_routes[] = $info['place'];
								}
								if (($info['type'] == 'bp' || $info['type'] == 'both') && strtolower($info['place']) == strtolower($start_route)) {
									$count_next = 1;
								}
							}
							else {
								if ($info['type'] == 'bp' || $info['type'] == 'both') {
									$all_routes[] = $info['place'];
								}
							}
						}
					}
				}
				else {
					$all_routes = MP_Global_Function::get_all_term_data('wbtm_bus_stops');
					if ($start_route) {
						$all_routes = array_diff($all_routes, [$start_route]);
					}
				}
				return $all_routes;
			}
			public static function get_ticket_info($post_id, $start_route, $end_route) {
				$ticket_infos = [];
				if ($post_id && $start_route && $end_route) {
					$price_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []);
					if (sizeof($price_infos) > 0) {
						foreach ($price_infos as $price_info) {
							if (strtolower($price_info['wbtm_bus_bp_price_stop']) == strtolower($start_route) && strtolower($price_info['wbtm_bus_dp_price_stop']) == strtolower($end_route)) {
								$adult_price = $price_info['wbtm_bus_price'];
								$child_price = $price_info['wbtm_bus_child_price'];
								$infant_price = $price_info['wbtm_bus_infant_price'];
								if ($adult_price && (float)$adult_price >= 0) {
									$ticket_infos[] = [
										'name' => WBTM_Translations::text_adult(),
										'price' => MP_Global_Function::get_wc_raw_price($post_id, $adult_price),
										'type' => 0
									];
								}
								if ($child_price && (float)$child_price >= 0) {
									$ticket_infos[] = [
										'name' => WBTM_Translations::text_child(),
										'price' => MP_Global_Function::get_wc_raw_price($post_id, $child_price),
										'type' => 1
									];
								}
								if ($infant_price && (float)$infant_price >= 0) {
									$ticket_infos[] = [
										'name' => WBTM_Translations::text_infant(),
										'price' => MP_Global_Function::get_wc_raw_price($post_id, $infant_price),
										'type' => 2
									];
								}
							}
						}
					}
				}
				return $ticket_infos;
			}
			public static function get_ticket_name($type = 0) {
				$ticket[0] = WBTM_Translations::text_adult();
				$ticket[1] = WBTM_Translations::text_child();
				$ticket[2] = WBTM_Translations::text_infant();
				return $ticket[$type];
			}
			public static function get_route_all_date_info($post_id, $all_dates = []) {
				$all_dates = sizeof($all_dates) > 0 ? $all_dates : self::get_post_date($post_id);
				$all_infos = [];
				$route_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
				if (sizeof($all_dates) > 0) {
					foreach ($all_dates as $date) {
						if ($date) {
							$prev_date = $date;
							$prev_full_date = $date;
							$count = 0;
							foreach ($route_infos as $info) {
								$current_date = date('Y-m-d H:i', strtotime($prev_date . ' ' . $info['time']));
								if ($count > 0) {
									if (strtotime($prev_full_date) > strtotime($current_date)) {
										$current_date = date('Y-m-d H:i', strtotime($current_date . ' +1 day'));
									}
								}
								$info['time'] = $current_date;
								$all_infos[$date][] = $info;
								$prev_full_date = $current_date;
								$prev_date = date('Y-m-d', strtotime($current_date));
								$count++;
							}
						}
					}
				}
				return $all_infos;
			}
			public static function get_bus_all_info($post_id, $date, $start_route, $end_route) {
				if ($post_id > 0 && $date && $start_route && $end_route) {
					$all_dates = WBTM_Functions::get_post_date($post_id);
					$route_infos = WBTM_Functions::get_route_all_date_info($post_id, $all_dates);
					if (sizeof($route_infos) > 0) {
						$now_full = current_time('Y-m-d H:i');
						foreach ($route_infos as $route_info) {
							$bp_date = '';
							if (sizeof($route_info) > 0) {
								foreach ($route_info as $info) {
									if (strtolower($start_route) == strtolower($info['place']) && ($info['type'] == 'bp' || $info['type'] == 'both') && strtotime($date) == strtotime(date('Y-m-d', strtotime($info['time'])))) {
										$bp_date = $info['time'];
									}
									if ($bp_date && strtolower($end_route) == strtolower($info['place']) && ($info['type'] == 'dp' || $info['type'] == 'both')) {
										$slice_time = self::slice_buffer_time($bp_date);
										if (strtotime($now_full) < strtotime($slice_time)) {
											$total_seat = MP_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
											$sold_seat = WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date);
											$available_seat = $total_seat - $sold_seat;
											return [
												'start_point' => $route_info[0]['place'],
												'start_time' => $route_info[0]['time'],
												'bp' => $start_route,
												'bp_time' => $bp_date,
												'dp' => $end_route,
												'dp_time' => $info['time'],
												'price' => WBTM_Functions::get_seat_price($post_id, $start_route, $end_route),
												'total_seat' => $total_seat,
												'sold_seat' => $sold_seat,
												'available_seat' => max(0, $available_seat)
											];
										}
									}
								}
							}
						}
					}
				}
				return [];
			}
			//==========================//
			public static function get_all_dates($post_id = 0) {
				$all_dates = [];
				if ($post_id > 0) {
					$date_infos = self::get_post_date($post_id);
					$route_infos = WBTM_Functions::get_route_all_date_info($post_id, $date_infos);
					if (sizeof($route_infos) > 0) {
						foreach ($route_infos as $route_info) {
							if (sizeof($route_info) > 0) {
								foreach ($route_info as $info) {
									if ($info['type'] == 'bp' || $info['type'] == 'both') {
										$all_dates[] = date('Y-m-d', strtotime($info['time']));
									}
								}
							}
						}
					}
				}
				else {
					$sale_end_date = MP_Global_Function::get_settings('wbtm_general_settings', 'ticket_sale_close_date');
					$sale_end_date = $sale_end_date ? date('Y-m-d', strtotime($sale_end_date)) : '';
					$active_days = MP_Global_Function::get_settings('wbtm_general_settings', 'ticket_sale_max_date', 30);
					$start_date = current_time('Y-m-d');
					$end_date = date('Y-m-d', strtotime($start_date . ' +' . $active_days . ' day'));
					if ($sale_end_date && strtotime($sale_end_date) < strtotime($end_date)) {
						$end_date = $sale_end_date;
					}
					if (strtotime($start_date) < strtotime($end_date)) {
						$dates = MP_Global_Function::date_separate_period($start_date, $end_date);
						foreach ($dates as $date) {
							$date = $date->format('Y-m-d');
							$all_dates[] = $date;
						}
					}
				}
				$all_dates = array_unique($all_dates);
				usort($all_dates, "MP_Global_Function::sort_date");
				return $all_dates;
			}
			public static function get_post_date($post_id) {
				$all_dates = [];
				if ($post_id > 0) {
					$show_on_dates = MP_Global_Function::get_post_info($post_id, 'show_operational_on_day', 'no');
					$now = current_time('Y-m-d');
					$year = current_time('Y');
					if ($show_on_dates == 'yes') {
						$on_dates = MP_Global_Function::get_post_info($post_id, 'wbtm_particular_dates', array());
						if (sizeof($on_dates)) {
							foreach ($on_dates as $on_date) {
								$date_item = date('Y-m-d', strtotime($year . '-' . $on_date));
								if (strtotime($date_item) < strtotime($now)) {
									$date_item = date('Y-m-d', strtotime($year + 1 . '-' . $on_date));
								}
								if (strtotime($date_item) >= strtotime($now)) {
									$all_dates[] = $date_item;
								}
							}
						}
					}
					else {
						$sale_end_date = MP_Global_Function::get_post_info($post_id, 'wbtm_repeated_end_date') ?: MP_Global_Function::get_settings('wbtm_general_settings', 'ticket_sale_close_date');
						$sale_end_date = $sale_end_date ? date('Y-m-d', strtotime($sale_end_date)) : '';
						$active_days = MP_Global_Function::get_post_info($post_id, 'wbtm_active_days') ?: MP_Global_Function::get_settings('wbtm_general_settings', 'ticket_sale_max_date', 30);
						$start_date = MP_Global_Function::get_post_info($post_id, 'wbtm_repeated_start_date', $now);
						if (strtotime($now) >= strtotime($start_date)) {
							$start_date = $now;
						}
						$end_date = date('Y-m-d', strtotime($start_date . ' +' . $active_days . ' day'));
						if ($sale_end_date && strtotime($sale_end_date) < strtotime($end_date)) {
							$end_date = $sale_end_date;
						}
						if (strtotime($start_date) < strtotime($end_date)) {
							$off_dates = [];
							$all_off_dates = MP_Global_Function::get_post_info($post_id, 'wbtm_offday_schedule', array());
							if (sizeof($all_off_dates) > 0) {
								foreach ($all_off_dates as $off_date) {
									if ($off_date['from_date'] && $off_date['to_date']) {
										$from_date = date('Y-m-d', strtotime($year . '-' . $off_date['from_date']));
										$to_date = date('Y-m-d', strtotime($year . '-' . $off_date['to_date']));
										if (strtotime($to_date) < strtotime($now)) {
											$from_date = date('Y-m-d', strtotime($year + 1 . '-' . $off_date['from_date']));
											$to_date = date('Y-m-d', strtotime($year + 1 . '-' . $off_date['to_date']));
										}
										$off_date_lists = MP_Global_Function::date_separate_period($from_date, $to_date);
										foreach ($off_date_lists as $off_date_list) {
											$off_dates[] = $off_date_list->format('Y-m-d');
										}
									}
								}
							}
							$particular_off_dates = MP_Global_Function::get_post_info($post_id, 'wbtm_off_dates', array());
							if (sizeof($particular_off_dates) > 0) {
								foreach ($particular_off_dates as $particular_off_date) {
									$particular_off_date = date('Y-m-d', strtotime($year . '-' . $particular_off_date));
									if (strtotime($particular_off_date) < strtotime($now)) {
										$particular_off_date = date('Y-m-d', strtotime($year + 1 . '-' . $particular_off_date));
									}
									$off_dates[] = $particular_off_date;
								}
							}
							$off_dates = array_unique($off_dates);
							$off_days = MP_Global_Function::get_post_info($post_id, 'wbtm_off_days');
							$off_day_array = $off_days ? explode(',', $off_days) : [];
							$show_off_day = MP_Global_Function::get_post_info($post_id, 'show_off_day');
							$repeat = MP_Global_Function::get_post_info($post_id, 'wbtm_repeated_after', 1);
							$dates = MP_Global_Function::date_separate_period($start_date, $end_date, $repeat);
							foreach ($dates as $date) {
								$date = $date->format('Y-m-d');
								if (strtotime($date) >= strtotime($now)) {
									$day = strtolower(date('l', strtotime($date)));
									if ($show_off_day == 'yes') {
										if (!in_array($date, $off_dates) && !in_array($day, $off_day_array)) {
											$all_dates[] = $date;
										}
									}
									else {
										$all_dates[] = $date;
									}
								}
							}
						}
					}
				}
				return $all_dates;
			}
			public static function slice_buffer_time($date) {
				$buffer_time = MP_Global_Function::get_settings('wbtm_general_settings', 'bus_buffer_time', 0) * 60;
				if ($buffer_time > 0) {
					$date = date('Y-m-d H:i', strtotime($date) - $buffer_time);
				}
				return $date;
			}
			//==========================//
			public static function get_seat_price($post_id, $start_route, $end_route, $seat_type = 0, $dd = false) {
				if ($post_id && $start_route && $end_route) {
					$ticket_infos = self::get_ticket_info($post_id, $start_route, $end_route);
					if (sizeof($ticket_infos) > 0) {
						foreach ($ticket_infos as $ticket_info) {
							if ($ticket_info['type'] == $seat_type) {
								$price = $ticket_info['price'];
								$seat_plan_type = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
								if ($seat_plan_type == 'wbtm_seat_plan' && $dd) {
									$seat_dd_increase = (int)MP_Global_Function::get_post_info($post_id, 'wbtm_seat_dd_price_parcent', 0);
									$price = $price + ($price * $seat_dd_increase / 100);
								}
								return $price;
							}
						}
					}
				}
				return false;
			}
			public static function get_ex_service_price($post_id, $service_name) {
				$show_extra_service = MP_Global_Function::get_post_info($post_id, 'show_extra_service', 'no');
				if ($show_extra_service == 'yes') {
					$ex_services = MP_Global_Function::get_post_info($post_id, 'wbtm_extra_services', []);
					if (sizeof($ex_services) > 0) {
						foreach ($ex_services as $ex_service) {
							if ($ex_service['option_name'] == $service_name) {
								$price = max(0, $ex_service['option_price']);
								$price = MP_Global_Function::wc_price($post_id, $price);
								return MP_Global_Function::price_convert_raw($price);
							}
						}
					}
				}
				return false;
			}
			//==========================//
			public static function check_bus_in_cart($bus_id) {
				if (!is_admin()) {
					$cart_items = WC()->cart->get_cart();
					if (sizeof($cart_items) > 0) {
						foreach ($cart_items as $cart_item) {
							if (array_key_exists('wbtm_bus_id', $cart_item) && $cart_item['wbtm_bus_id'] == $bus_id) {
								return true;
							}
						}
					}
				}
				return false;
			}
			public static function check_seat_in_cart($bus_id, $bp, $dp, $bp_date, $seat_name) {
				if (!is_admin()) {
					$cart_items = WC()->cart->get_cart();
					if (sizeof($cart_items) > 0) {
						foreach ($cart_items as $cart_item) {
							$cart_bus_id = array_key_exists('wbtm_bus_id', $cart_item) ? $cart_item['wbtm_bus_id'] : '';
							$cart_bp = array_key_exists('wbtm_bp_place', $cart_item) ? $cart_item['wbtm_bp_place'] : '';
							$cart_dp = array_key_exists('wbtm_dp_place', $cart_item) ? $cart_item['wbtm_dp_place'] : '';
							$cart_date = array_key_exists('wbtm_bp_time', $cart_item) ? $cart_item['wbtm_bp_time'] : '';
							$cart_date = $cart_date ? date('Y-m-d', strtotime($cart_date)) : '';
							$bp_date = $bp_date ? date('Y-m-d', strtotime($bp_date)) : '';
							if ($cart_bus_id == $bus_id && $cart_bp == $bp && $cart_dp == $dp && strtotime($cart_date) == strtotime($bp_date)) {
								$cart_seat_infos = array_key_exists('wbtm_seats', $cart_item) ? $cart_item['wbtm_seats'] : '';
								if (sizeof($cart_seat_infos) > 0) {
									foreach ($cart_seat_infos as $seat_info) {
										if (array_key_exists('seat_name', $seat_info) && $seat_info['seat_name'] == $seat_name) {
											return true;
										}
									}
								}
							}
						}
					}
				}
				return false;
			}
			//==========================//
			public static function get_order_status_text($key) {
				$status = array(
					'1' => 'processing',
					'2' => 'completed',
					'3' => 'pending',
					'4' => 'on-hold',
					'5' => 'canceled',
				);
				return array_key_exists($key, $status) ? $status[$key] : $key;
			}
			public static function week_day_num_to_text($key) {
				$day = array(
					'0' => 'sunday',
					'1' => 'monday',
					'2' => 'tuesday',
					'3' => 'wednesday',
					'4' => 'thursday',
					'5' => 'friday',
					'6' => 'saturday',
				);
				return array_key_exists($key, $day) ? $day[$key] : $key;
			}
			//==========================//
			public static function wbtm_get_user_role($user_ID) {
				global $wp_roles;
				$user_data = get_userdata($user_ID);
				$user_role_slug = $user_data->roles;
				$user_role_nr = 0;
				$user_role_list = '';
				foreach ($user_role_slug as $user_role) {
					$user_role_nr++;
					if ($user_role_nr > 1) {
						$user_role_list .= ", ";
					}
					$user_role_list .= translate_user_role($wp_roles->roles[$user_role]['name']);
				}
				return $user_role_list;
			}
			//==========================//
			public static function get_cpt(): string {
				return 'wbtm_bus';
			}
			public static function get_name() {
				return MP_Global_Function::get_settings('wbtm_general_settings', 'label', esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'));
			}
			public static function get_slug() {
				return MP_Global_Function::get_settings('wbtm_general_settings', 'bus', 'bus');
			}
			public static function get_icon() {
				return MP_Global_Function::get_settings('wbtm_general_settings', 'icon', 'dashicons-car');
			}
		}
	}