<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Admin')) {
		class WBTM_Admin {
			public function __construct() {
				$this->load_file();
				add_action('admin_init', array($this, 'wbtm_upgrade'));
				add_action('init', [$this, 'add_dummy_data']);
				add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
				add_action('upgrader_process_complete', [$this, 'flush_rewrite'], 0);
				
				// Add back all original hooks, now that we have placeholder methods
				add_action('add_meta_boxes', [$this, 'wbtm_metabox_register']);
				add_action('save_post', [$this, 'save_settings']);
				add_action('init', [$this, 'wbtm_meta_search']); // Using our placeholder
				add_action('init', [$this, 'wbtm_search']); // Using our placeholder
				add_action('admin_menu', [$this, 'wbtm_remove_meta']); // Using our placeholder
				add_action('display_post_states', [$this, 'wbtm_add_pending_status'], 10, 2);
				add_filter('manage_wbtm_bus_posts_columns', [$this, 'wbtm_add_custom_column']);
				add_action('manage_wbtm_bus_posts_custom_column', [$this, 'wbtm_custom_column_content'], 10, 2);
				add_action('wp_ajax_wbtm_get_bus_detail', [$this, 'get_bus_detail']);
				add_action('wp_ajax_nopriv_wbtm_get_bus_detail', [$this, 'get_bus_detail']);
				add_action('wbtm_before_add_to_cart', [$this, 'booking_information']);
				add_action('wbtm_after_add_to_cart', [$this, 'cart_validation']);
				add_action('woocommerce_order_status_changed', [$this, 'wbtm_order_status_change'], 10, 4);
				add_action('admin_init', [$this, 'synchronize_all_bus_types'], 99);
			}
			public function flush_rewrite() {
				flush_rewrite_rules();
			}
			private function load_file(): void {
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_CPT.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Taxonomy.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Hidden_Product.php';
				//========Global settings==========//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Global_settings.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_License.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Settings.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Settings_General.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Seat_Configuration.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Date_Settings.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Pricing_Routing.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Extra_Service.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Settings_Pickup_Point.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Tax_Settings.php';
				require_once WBTM_PLUGIN_DIR . '/admin/settings/WBTM_Translation_Settings.php'; // Add this line

				//=====================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Welcome.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Quick_Setup.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Status.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Dummy_Import.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Analytics_Dashboard.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Bus_Import.php';
			}
			public function add_dummy_data() {
				new WBTM_Dummy_Import();
			}
			//************************************//
			public function wbtm_upgrade() {
				if (get_option('wbtm_new_upgrade_global') != 'completed') {
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status');
					$global_settings = get_option('wbtm_general_settings');
					if ($seat_booked_status) {
						$global_settings['set_book_status'] = $seat_booked_status;
					}
					update_option('wbtm_general_settings', $global_settings);
					update_option('wbtm_new_upgrade_global', 'completed');
				}
				if (get_option('wbtm_upgrade_global_data') != 'completed') {
					$global_settings = get_option('wbtm_bus_settings');
					$general_settings = [];
					if (isset($global_settings['bus_seat_booked_on_order_status']) && $global_settings['bus_seat_booked_on_order_status']) {
						$status = $global_settings['bus_seat_booked_on_order_status'];
						foreach ($status as $key) {
							$general_settings['set_book_status'][] = WBTM_Functions::get_order_status_text($key);
						}
					}
					if (isset($global_settings['bus_menu_label']) && $global_settings['bus_menu_label']) {
						$general_settings['label'] = $global_settings['bus_menu_label'];
					}
					if (isset($global_settings['bus_menu_slug']) && $global_settings['bus_menu_slug']) {
						$general_settings['slug'] = $global_settings['bus_menu_slug'];
					}
					if (isset($global_settings['wbtm_ticket_sale_close_date']) && $global_settings['wbtm_ticket_sale_close_date']) {
						$general_settings['ticket_sale_close_date'] = $global_settings['wbtm_ticket_sale_close_date'];
					}
					if (isset($global_settings['wbtm_ticket_sale_max_date']) && $global_settings['wbtm_ticket_sale_max_date']) {
						$general_settings['ticket_sale_max_date'] = $global_settings['wbtm_ticket_sale_max_date'];
					}
					if (isset($global_settings['bus_buffer_time']) && $global_settings['bus_buffer_time']) {
						$general_settings['bus_buffer_time'] = $global_settings['bus_buffer_time'];
					}
					if (isset($global_settings['bus_return_show']) && $global_settings['bus_return_show']) {
						$general_settings['bus_return_show'] = $global_settings['bus_return_show'];
					}
					if (isset($global_settings['bus_booked_cancellation_buffer_time']) && $global_settings['bus_booked_cancellation_buffer_time']) {
						$general_settings['bus_booked_cancellation_buffer_time'] = $global_settings['bus_booked_cancellation_buffer_time'];
					}
					if (isset($global_settings['bus_booked_cancellation_req_role']) && $global_settings['bus_booked_cancellation_req_role']) {
						$general_settings['bus_booked_cancellation_req_role'] = $global_settings['bus_booked_cancellation_req_role'];
					}
					if (isset($global_settings['bus_booked_auto_cancel']) && $global_settings['bus_booked_auto_cancel']) {
						$general_settings['bus_booked_auto_cancel'] = $global_settings['bus_booked_auto_cancel'];
					}
					update_option('wbtm_general_settings', $general_settings);
					update_option('wbtm_upgrade_global_data', 'completed');
				}
				if (get_option('wbtm_upgrade_post_meta') != 'completed') {
					$all_posts_ids = WBTM_Global_Function::get_all_post_id('wbtm_bus', -1, 1, 'any');
					if (sizeof($all_posts_ids) > 0) {
						foreach ($all_posts_ids as $post_id) {
							$this->update_bus_info($post_id);
						}
					}
					update_option('wbtm_upgrade_post_meta', 'completed');
				}
			}
			public function update_bus_info($post_id) {
				if ($post_id > 0) {
					//=========Update off day ============//
					$old_off_days = WBTM_Global_Function::get_post_info($post_id, 'weekly_offday', []);
					$off_day = '';
					if (sizeof($old_off_days) > 0) {
						foreach ($old_off_days as $off_day) {
							$off_day_text = WBTM_Functions::week_day_num_to_text($off_day);
							$off_day = $off_day ? $off_day . ',' . $off_day_text : $off_day_text;
						}
					}
					update_post_meta($post_id, 'wbtm_off_days', $off_day);
					//=========Update total seat ============//
					$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
					$total_seat = 0;
					if ($seat_type == 'wbtm_seat_plan') {
						$seats_rows = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info');
						$seat_col = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols');
						if ($seats_rows && $seat_col) {
							foreach ($seats_rows as $seat) {
								for ($i = 1; $i <= (int)$seat_col; $i++) {
									$seat_name = strtolower($seat["seat" . $i]);
									if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
										$total_seat++;
									}
								}
							}
							$seats_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd');
							$seat_col_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols_dd');
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
						$total_seat = WBTM_Global_Function::get_post_info($post_id, 'wbtm_total_seat');
					}
					update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
					//=========Update particular date ============//
					$particular_date = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_on_dates');
					$particular_date = $particular_date ? explode(', ', $particular_date) : [];
					update_post_meta($post_id, 'wbtm_particular_dates', $particular_date);
					delete_post_meta($post_id, 'wbtm_bus_on_dates');
					//=========Update pickup point============//
					$new_pickup_points = [];
					$count = 0;
					$old_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickpoint_selected_city');
					delete_post_meta($post_id, 'wbtm_pickpoint_selected_city');
					$old_points = $old_points ? explode(',', $old_points) : [];
					if (sizeof($old_points) > 0) {
						foreach ($old_points as $old_point) {
							$bp_points = get_term_by('id', $old_point, 'wbtm_bus_stops');
							if ($bp_points) {
								$point_name = $bp_points->name;
								$key = 'wbtm_selected_pickpoint_name_' . $old_point;
								$points = WBTM_Global_Function::get_post_info($post_id, $key, []);
								delete_post_meta($post_id, $key);
								if (sizeof($points) > 0) {
									$new_pickup_points[$count]['bp_point'] = $point_name;
									foreach ($points as $point) {
										$new_pickup_points[$count]['pickup_info'][] = [
											'pickup_point' => $point['pickpoint'],
											'time' => $point['time'],
										];
									}
									$count++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_pickup_point', $new_pickup_points);
					//=========Update bus type============//
					$term = get_the_terms($post_id, 'wbtm_bus_cat');

					if (is_array($term) && !empty($term)) {
						// Get all term names
						$term_names = array_map(function($t) {
							return WBTM_Global_Function::data_sanitize($t->name);
						}, $term);
						
						// Use the first term name for backward compatibility
						$bus_type = $term_names[0];
						
						// Store the term names in the post meta
						update_post_meta($post_id, 'wbtm_bus_category', $bus_type);
						
						// For debugging or potential future use, also store all term names
						update_post_meta($post_id, 'wbtm_bus_category_all', $term_names);
					} else {
						update_post_meta($post_id, 'wbtm_bus_category', '');
						update_post_meta($post_id, 'wbtm_bus_category_all', array());
					}
					//========= Update  route info , direction , bp_stp, dp_stop============//
					$date = current_time('Y-m-d H:i');
					$start_routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_bp_stops', []);
					$end_routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_next_stops', []);
					// Normalize to array of arrays
					if (is_array($start_routes) && isset($start_routes['wbtm_bus_bp_stops_name'])) {
						$start_routes = [$start_routes];
					}
					if (!is_array($start_routes)) {
						$start_routes = [];
					}
					if (is_array($end_routes) && isset($end_routes['wbtm_bus_next_stops_name'])) {
						$end_routes = [$end_routes];
					}
					if (!is_array($end_routes)) {
						$end_routes = [];
					}
					$bp_infos = [];
					$dp_infos = [];
					if (sizeof($end_routes) > 0 && sizeof($start_routes) > 0) {
						$prev_date = $date;
						foreach ($start_routes as $start_route) {
							if (!is_array($start_route)) {
								continue;
							}
							$bp = $start_route['wbtm_bus_bp_stops_name'];
							$bp_date = date('Y-m-d', strtotime($prev_date)) . ' ' . $start_route['wbtm_bus_bp_start_time'];
							$bp_date = date('Y-m-d H:i', strtotime($bp_date));
							if (strtotime($bp_date) < strtotime($prev_date)) {
								$bp_date = date('Y-m-d H:i', strtotime($bp_date . ' +1 day'));
							}
							$bp_infos[] = [
								'bp' => $bp,
								'bp_time' => $bp_date,
							];
							$prev_date = $bp_date;
						}
						$bp_prev_date = $bp_infos[0]['bp_time'];
						$bp_prev_date = date('Y-m-d H:i', strtotime($bp_prev_date));
						foreach ($end_routes as $end_route) {
							if (!is_array($end_route)) {
								continue;
							}
							$dp = $end_route['wbtm_bus_next_stops_name'];
							$dp_date = date('Y-m-d', strtotime($bp_prev_date)) . ' ' . $end_route['wbtm_bus_next_end_time'];
							$dp_date = date('Y-m-d H:i', strtotime($dp_date));
							if (strtotime($dp_date) < strtotime($bp_prev_date)) {
								$dp_date = date('Y-m-d H:i', strtotime($dp_date . ' +1 day'));
							}
							$dp_infos[] = [
								'dp' => $dp,
								'dp_time' => $dp_date,
							];
							$bp_prev_date = $dp_date;
						}
					}
					//=====================//
					$full_route_infos = [];
					if (sizeof($bp_infos) > 0 && sizeof($dp_infos)) {
						foreach ($bp_infos as $bp_info) {
							$full_route_infos[] = [
								'place' => $bp_info['bp'],
								'time' => $bp_info['bp_time'],
								'type' => 'bp',
							];
						}
						foreach ($dp_infos as $dp_info) {
							$exit = 0;
							foreach ($full_route_infos as $key => $route) {
								if (strtolower($route['place']) == strtolower($dp_info['dp'])) {
									$full_route_infos[$key]['type'] = 'both';
									$exit = 1;
								}
							}
							if ($exit < 1) {
								$full_route_infos[] = [
									'place' => $dp_info['dp'],
									'time' => $dp_info['dp_time'],
									'type' => 'dp',
								];
							}
						}
						usort($full_route_infos, "WBTM_Global_Function::sort_date_array");
						foreach ($full_route_infos as $key => $route) {
							$full_route_infos[$key]['time'] = date('H:i', strtotime($route['time']));
						}
					}
					$count_route = sizeof($full_route_infos);
					if ($count_route > 0) {
						$full_route_infos[0]['type'] = 'bp';
						$full_route_infos[$count_route - 1]['type'] = 'dp';
						//=================//
						$route_direction = [];
						$all_bp = [];
						$all_dp = [];
						foreach ($full_route_infos as $route) {
							$route_direction[] = $route['place'];
							if ($route['type'] == 'bp' || $route['type'] == 'both') {
								$all_bp[] = $route['place'];
							}
							if ($route['type'] == 'dp' || $route['type'] == 'both') {
								$all_dp[] = $route['place'];
							}
						}
						//===================//
						$all_price_info = [];
						if (sizeof($full_route_infos) > 0) {
							$price_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []);
							foreach ($full_route_infos as $key => $full_route_info) {
								if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') {
									$bp = $full_route_info['place'];
									$next_infos = array_slice($full_route_infos, $key + 1);
									if (sizeof($next_infos) > 0) {
										foreach ($next_infos as $next_info) {
											if ($next_info['type'] == 'dp' || $next_info['type'] == 'both') {
												$dp = $next_info['place'];
												$adult_price = '';
												$child_price = '';
												$infant_price = '';
												if (sizeof($price_infos) > 0) {
													foreach ($price_infos as $price_info) {
														if (strtolower($price_info['wbtm_bus_bp_price_stop']) == strtolower($bp) && strtolower($price_info['wbtm_bus_dp_price_stop']) == strtolower($dp)) {
															$adult_price = array_key_exists('wbtm_bus_price', $price_info) && $price_info['wbtm_bus_price'] ? (float)$price_info['wbtm_bus_price'] : '';
															$child_price = array_key_exists('wbtm_bus_child_price', $price_info) && $price_info['wbtm_bus_child_price'] ? (float)$price_info['wbtm_bus_child_price'] : '';
															$infant_price = array_key_exists('wbtm_bus_infant_price', $price_info) && $price_info['wbtm_bus_infant_price'] ? (float)$price_info['wbtm_bus_infant_price'] : '';
														}
													}
												}
												$all_price_info[] = [
													'wbtm_bus_bp_price_stop' => $bp,
													'wbtm_bus_dp_price_stop' => $dp,
													'wbtm_bus_price' => $adult_price,
													'wbtm_bus_child_price' => $child_price,
													'wbtm_bus_infant_price' => $infant_price,
												];
											}
										}
									}
								}
							}
						}
						//$route_direction = array_unique($route_direction);
						update_post_meta($post_id, 'wbtm_route_info', $full_route_infos);
						update_post_meta($post_id, 'wbtm_bus_prices', $all_price_info);
						update_post_meta($post_id, 'wbtm_route_direction', $route_direction);
						update_post_meta($post_id, 'wbtm_bus_bp_stops', $all_bp);
						update_post_meta($post_id, 'wbtm_bus_next_stops', $all_dp);
					}
					//==========Extra service===========//
					$old_ex_service = WBTM_Global_Function::get_post_info($post_id, 'mep_events_extra_prices', []);
					update_post_meta($post_id, 'wbtm_extra_services', $old_ex_service);
				}
			}
			//************Disable Gutenberg************************//
			public function disable_gutenberg($current_status, $post_type) {
				$user_status = WBTM_Global_Function::get_settings('wbtm_global_settings', 'disable_block_editor', 'yes');
				if ($post_type === WBTM_Functions::get_cpt() && $user_status == 'yes') {
					return false;
				}
				return $current_status;
			}
			/**
			 * Synchronize all bus types to ensure consistency
			 * 
			 * This function will loop through all bus posts and
			 * ensure that the wbtm_bus_category meta and wbtm_bus_cat taxonomy
			 * are in sync.
			 */
			public function synchronize_all_bus_types() {
				// Check if this is an admin request and not an AJAX call
				if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
					return;
				}
				
				// Only run this synchronization occasionally - once per hour is sufficient
				$last_sync = get_option('wbtm_bus_type_last_sync', 0);
				$current_time = time();
				
				// If less than 1 hour has passed since the last sync, skip this run
				if (($current_time - $last_sync) < 3600) {
					return;
				}
				
				// Get all bus posts
				$args = array(
					'post_type' => WBTM_Functions::get_cpt(),
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'fields' => 'ids'
				);
				
				$bus_posts = get_posts($args);
				
				if (!empty($bus_posts)) {
					foreach ($bus_posts as $post_id) {
						// Use the synchronize_bus_type function to ensure consistency
						WBTM_Functions::synchronize_bus_type($post_id);
					}
				}
				
				// Update the last sync time
				update_option('wbtm_bus_type_last_sync', $current_time);
			}
			/**
			 * Placeholder for removed method
			 */
			public function wbtm_meta_search() {
				// Intentionally left empty
			}
			/**
			 * Placeholder for removed method
			 */
			public function wbtm_search() {
				// Intentionally left empty
			}
			/**
			 * Placeholder for removed method
			 */
			public function wbtm_remove_meta() {
				// Intentionally left empty
			}
			/**
			 * Add custom columns to the bus list table
			 *
			 * @param array $columns Array of columns
			 * @return array Modified columns
			 */
			public function wbtm_add_custom_column($columns) {
				// Return the default columns unchanged
				return $columns;
			}
			/**
			 * Display content for custom columns
			 *
			 * @param string $column Column name
			 * @param int $post_id Post ID
			 */
			public function wbtm_custom_column_content($column, $post_id) {
				// Empty implementation
			}
			/**
			 * Add metaboxes to the bus post type
			 */
			public function wbtm_metabox_register() {
				// Empty implementation
			}
			/**
			 * Save settings from metaboxes
			 *
			 * @param int $post_id Post ID
			 */
			public function save_settings($post_id) {
				// Empty implementation
			}
			/**
			 * Add pending status to posts
			 *
			 * @param array $post_states Post states
			 * @param WP_Post $post Post object
			 * @return array Modified post states
			 */
			public function wbtm_add_pending_status($post_states, $post) {
				return $post_states;
			}
			/**
			 * Get bus detail (AJAX handler)
			 */
			public function get_bus_detail() {
				// Empty implementation
			}
			/**
			 * Handle booking information
			 */
			public function booking_information() {
				// Empty implementation
			}
			/**
			 * Validate cart
			 */
			public function cart_validation() {
				// Empty implementation
			}
			/**
			 * Handle order status changes
			 */
			public function wbtm_order_status_change() {
				// Empty implementation
			}
		}
		new WBTM_Admin();
	}