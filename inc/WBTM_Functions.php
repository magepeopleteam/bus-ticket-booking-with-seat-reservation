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
				$template_path = get_stylesheet_directory() . '/public/templates/';
				$default_dir = WBTM_PLUGIN_DIR . '/public/templates/';
				$dir = is_dir($template_path) ? $template_path : $default_dir;
				$file_path = $dir . $file_name;
				return locate_template(array('public/templates/' . $file_name)) ? $file_path : $default_dir . $file_name;
			}
			//==========================//
			public static function get_bus_route($start_route = '', $post_id = 0) {
				$all_routes = [];
				if ($post_id > 0) {
					$route_key = !$start_route ? 'wbtm_bus_bp_stops' : 'wbtm_bus_next_stops';
					$route_name = !$start_route ? 'wbtm_bus_bp_stops_name' : 'wbtm_bus_next_stops_name';
					$routes = MP_Global_Function::get_post_info($post_id, $route_key, []);
					if (sizeof($routes) > 0) {
						foreach ($routes as $route) {
							if ($route[$route_name]) {
								$all_routes[] = $route[$route_name];
							}
						}
					}
				}
				else {
					if (!$start_route) {
						$routes = MP_Global_Function::get_taxonomy('wbtm_bus_stops');
						if (sizeof($routes) > 0) {
							foreach ($routes as $route) {
								$get_term = get_term_by('name', $route->name, 'wbtm_bus_stops');
								$is_hide_on_boarding = get_term_meta($get_term->term_id, 'wbtm_is_hide_global_boarding', true);
								if ($is_hide_on_boarding !== 'yes') {
									$all_routes[] = $route->name;
								}
							}
						}
					}
					else {
						$category = get_term_by('name', $start_route, 'wbtm_bus_stops');
						$dropping_points = get_term_meta($category->term_id, 'wbtm_bus_routes_name_list', true);
						$dropping_points = $dropping_points ? MP_Global_Function::data_sanitize($dropping_points) : array();
						if (sizeof($dropping_points) > 0) {
							foreach ($dropping_points as $dropping_point) {
								$all_routes[] = $dropping_point['wbtm_bus_routes_name'];
							}
						}
						else {
							$routes = MP_Global_Function::get_taxonomy('wbtm_bus_stops');
							if (sizeof($routes) > 0) {
								foreach ($routes as $route) {
									$all_routes[] = $route->name;
								}
							}
						}
					}
				}
				return $all_routes;
			}
			public static function get_bus_type($post_id) {
				$term = get_the_terms($post_id, 'wbtm_bus_cat');
				return $term ? $term[0]->name : '';
			}
			//==========================//
			public static function get_date($post_id, $start_route = '') {
				$now = current_time('Y-m-d');
				$year = current_time('Y');
				$all_dates = [];
				if ($post_id > 0) {
					$show_on_dates = MP_Global_Function::get_post_info($post_id, 'show_operational_on_day', 'no');
					$on_dates_text = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_on_dates', array());
					if ($show_on_dates != 'no' && $on_dates_text) {
						$on_dates = explode(', ', $on_dates_text);
						foreach ($on_dates as $on_date) {
							$date_item = date('Y-m-d', strtotime($year . '-' . $on_date));
							if (strtotime($date_item) < strtotime($now)) {
								$date_item = date('Y-m-d', strtotime($year + 1 . '-' . $on_date));
							}
							$all_dates[] = $date_item;
						}
					}
					else {
						$sale_end_date = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_ticket_sale_close_date', '');
						$sale_end_date = $sale_end_date ? date('Y-m-d', strtotime($sale_end_date)) : '';
						$active_days = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_ticket_sale_max_date', 30);
						$start_date = $now;
						$end_date = date('Y-m-d', strtotime($start_date . ' +' . $active_days . ' day'));
						if ($sale_end_date && strtotime($sale_end_date) < strtotime($end_date)) {
							$end_date = $sale_end_date;
						}
						if (strtotime($start_date) < strtotime($end_date)) {
							$all_off_dates = MP_Global_Function::get_post_info($post_id, 'wbtm_offday_schedule', array());
							$off_dates = [];
							foreach ($all_off_dates as $off_date) {
								if ($off_date['from_date'] && $off_date['to_date']) {
									$from_date = date('Y-m-d', strtotime($year . '-' . $off_date['from_date']));
									$to_date = date('Y-m-d', strtotime($year . '-' . $off_date['to_date']));
									$off_date_lists = MP_Global_Function::date_separate_period($from_date, $to_date);
									foreach ($off_date_lists as $off_date_list) {
										$off_dates[] = $off_date_list->format('Y-m-d');
									}
								}
							}
							$off_dates = array_unique($off_dates);
							$off_days = MP_Global_Function::get_post_info($post_id, 'weekly_offday', array());
							$show_off_day = MP_Global_Function::get_post_info($post_id, 'show_off_day');
							$dates = MP_Global_Function::date_separate_period($start_date, $end_date);
							foreach ($dates as $date) {
								$date = $date->format('Y-m-d');
								$day = strtolower(date('w', strtotime($date)));
								if ($show_off_day = 'yes') {
									if (!in_array($date, $off_dates) && !in_array($day, $off_days)) {
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
				return $all_dates;
			}
			public static function get_all_dates($post_id = 0) {
				$all_dates = [];
				if ($post_id > 0) {
					$all_dates = self::get_date($post_id);
				}
				else {
					$all_post_ids = MP_Global_Function::get_all_post_id('wbtm_bus');
					if (sizeof($all_post_ids) > 0) {
						foreach ($all_post_ids as $all_post_id) {
							$dates = self::get_date($all_post_id);
							$all_dates = array_merge($all_dates, $dates);
						}
					}
				}
				$all_dates = array_unique($all_dates);
				usort($all_dates, "MP_Global_Function::sort_date");
				return $all_dates;
			}
			public static function check_buffer_time($post_id, $date, $start_route) {
				if ($post_id > 0 && $date && $start_route) {
				}
				return false;
			}
			//==========================//
			public static function get_total_seat($post_id) {
				$seat_type = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
				$total_seat = 0;
				if ($seat_type == 'wbtm_seat_plan') {
					$seats_rows = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info');
					$seat_col = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_cols');
					if ($seats_rows && $seat_col) {
						foreach ($seats_rows as $seat) {
							for ($i = 1; $i <= (int)$seat_col; $i++) {
								$seat_name = strtolower($seat["seat" . $i]);
								if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
									$total_seat++;
								}
							}
						}
						$seats_dd = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd');
						$seat_col_dd = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_cols_dd');
						if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
							foreach ($seats_dd as $seat) {
								for ($i = 1; $i <= $seat_col_dd; $i++) {
									$seat_name = $seat["dd_seat" . $i] ?? '';
									if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
										$total_seat++;
									}
								}
							}
						}
					}
				}
				else {
					$total_seat = MP_Global_Function::get_post_info($post_id, 'wbtm_total_seat');
				}
				return $total_seat;
			}
			//==========================//
			public static function get_seat_price($post_id, $start_route, $end_route, $dd = false, $seat_type = null, $return_price = false) {
				if ($post_id && $start_route && $end_route) {
					$start_route = strtolower($start_route);
					$end_route = strtolower($end_route);
					$flag = false;
					$price_arr = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_prices');
					if (!empty($price_arr) && is_array($price_arr)) {
						foreach ($price_arr as $value) {
							if (strtolower($value['wbtm_bus_bp_price_stop']) == $start_route && strtolower($value['wbtm_bus_dp_price_stop']) == $end_route) {
								$flag = true;
								break;
							}
						}
					}
					if (!$flag) {
						$price_arr = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_prices_return');
						if (!empty($price_arr) && is_array($price_arr)) {
							foreach ($price_arr as $value) {
								if (strtolower($value['wbtm_bus_bp_price_stop']) == $start_route && strtolower($value['wbtm_bus_dp_price_stop']) == $end_route) {
									$flag = true;
									break;
								}
							}
						}
						if (!$flag) {
							return false;
						}
					}
					$return_price_data = false;
					if ($flag) {
						$seat_dd_increase = (int)MP_Global_Function::get_post_info($post_id, 'wbtm_seat_dd_price_parcent');
						$dd_price_increase = ($dd && $seat_dd_increase) ? $seat_dd_increase : 0;
						foreach ($price_arr as $val) {
							$p_start = strtolower($val['wbtm_bus_bp_price_stop']);
							$p_end = strtolower($val['wbtm_bus_dp_price_stop']);
							if ($p_start === $start_route && $p_end === $end_route && !$return_price) { // Not return
								if (1 == $seat_type) {
									$price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);
								}
								elseif (2 == $seat_type) {
									$price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);
								}
								elseif (3 == $seat_type) {
									$price = $val['wbtm_bus_special_price'] + ($val['wbtm_bus_special_price'] * $dd_price_increase / 100);
								}
								else {
									$price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
								}
								$return_price_data = $price;
								break;
							}
							if ($p_start === $start_route && $p_end === $end_route && $return_price) { // Return
								if (1 == $seat_type) {
									$p = (($val['wbtm_bus_child_price_return']) ?: $val['wbtm_bus_child_price']);
									$price = $p + ($p * $dd_price_increase / 100);
								}
								elseif (2 == $seat_type) {
									$p = (($val['wbtm_bus_infant_price_return']) ?: $val['wbtm_bus_infant_price']);
									$price = $p + ($p * $dd_price_increase / 100);
								}
								elseif (3 == $seat_type) {
									$p = (($val['wbtm_bus_special_price']) ?: 0);
									$price = $p + ($p * $dd_price_increase / 100);
								}
								else {
									$p = (($val['wbtm_bus_price_return']) ?: $val['wbtm_bus_price']);
									$price = $p + ($p * $dd_price_increase / 100);
								}
								$return_price_data = $price;
								break;
							}
						}
						return $return_price_data;
					}
				}
				return false;
			}
			//==========================//
			public static function get_name() {
				return MP_Global_Function::get_settings('wbtm_bus_settings', 'bus_menu_label', esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'));
			}
			public static function get_slug() {
				return MP_Global_Function::get_settings('wbtm_bus_settings', 'bus_menu_slug', 'bus');
			}
			public static function get_icon() {
				return MP_Global_Function::get_settings('wbtm_bus_settings', 'bus_menu_icon', 'dashicons-car');
			}
		}
	}