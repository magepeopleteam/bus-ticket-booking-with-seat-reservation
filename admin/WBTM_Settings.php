<?php
	/*
	* @Author 		engr.sumonazma@gmail.com
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Settings')) {
		class WBTM_Settings {
			public function __construct() {
				add_action('add_meta_boxes', [$this, 'settings_meta']);
				add_action('save_post', array($this, 'save_settings'), 99, 1);
				add_action('wbtm_settings_tab', array($this, 'settings_tab'), 99);
			}
			//************************//
			public function settings_meta() {
				$label = WBTM_Functions::get_name();
				$cpt = WBTM_Functions::get_cpt();
				add_meta_box('wbtm_meta_box_panel', $label . esc_html__(' Information Settings : ', 'bus-ticket-booking-with-seat-reservation') . get_the_title(get_the_id()), array($this, 'settings'), $cpt, 'normal', 'high');
			}
			//******************************//
			public function settings() {
				$post_id = get_the_id();
				wp_nonce_field('wbtm_type_nonce', 'wbtm_type_nonce');
				$this->settings_tab($post_id);
			}
			public function settings_tab($post_id) {
				?>
                <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <div class="wbtm_style">
                    <div class="wbtm_tabs leftTabs">
                        <ul class="tabLists">
                            <li data-tabs-target="#wbtm_general_info">
                                <span class="fas fa-tools"></span><?php esc_html_e('General Info', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
                            <li data-tabs-target="#wbtm_settings_seat">
                                <span class="fas fa-chair"></span><?php esc_html_e('Seat Configure', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
                            <li data-tabs-target="#wbtm_settings_pricing_routing">
                                <span class="fas fa-file-invoice-dollar"></span><?php esc_html_e('Pricing & Route', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
                            <li data-tabs-target="#wbtm_settings_ex_service">
                                <span class="fas fa-list"></span><?php echo esc_html(WBTM_Translations::text_ex_service()); ?>
                            </li>
                            <li data-tabs-target="#wbtm_settings_pickup_point">
                                <span class="fas fa-route"></span><?php esc_html_e('Pickup/Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
							<?php do_action('wbtm_add_add_setting_menu', $post_id); ?>
                            <li data-tabs-target="#wbtm_settings_date">
                                <span class="fas fa-calendar-alt"></span><?php esc_html_e('Date Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
                            <li data-tabs-target="#wbtm_settings_tax">
                                <span class="fas fa-hand-holding-usd"></span><?php esc_html_e('Tax Configure', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
							<?php if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) { ?>
                                <li data-tabs-target="#mp_pp_deposits_type">
                                    <span class=""></span>&nbsp;&nbsp;<?php esc_html_e('Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </li>
							<?php } ?>
                        </ul>
                        <div class="tabsContent tab-content">
							<?php do_action('wbtm_add_settings_tab_content', $post_id); ?>
							<?php if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) { ?>
                                <div class="tabsItem" data-tabs="#mp_pp_deposits_type">
                                    <h5><?php esc_html_e('Partial Payment Settings : ', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
                                    <div class="divider"></div>
									<?php //do_action('wcpp_partial_product_settings', get_post_custom($post_id)); ?>
                                </div>
							<?php } ?>
                        </div>
                    </div>
                </div>
				<?php
			}
			public static function description_array($key) {
				$des = array(
					'wbtm_bus_no' => esc_html__('Please add your unique bus id', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_bus_category' => esc_html__('Please add your bus category', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_reservation' => esc_html__('Turn on or off, bus seat registration', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_reservation_tips' => esc_html__('By default Registration is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
					'show_boarding_time' => esc_html__('By default Boarding Time is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
					'show_dropping_time' => esc_html__('By default Dropping Time is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_seat_type_conf' => esc_html__('Please select your bus seat type . Default Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_get_total_seat' => esc_html__('Please Type your bus total seat.', 'bus-ticket-booking-with-seat-reservation'),
					'show_operational_on_day' => esc_html__('Select Particular Date or Repeated Date.', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_routing_info' => esc_html__('Here you can set bus route for stopage and dropping', 'bus-ticket-booking-with-seat-reservation'),
					'wbtm_pricing_info' => esc_html__('Please configure bus route price. Before price setting must be complete route configuration .', 'bus-ticket-booking-with-seat-reservation'),
					'show_extra_service' => esc_html__('Turn On or Off Extra service.', 'bus-ticket-booking-with-seat-reservation'),
					'show_pickup_point' => esc_html__('Turn On or Off pickup point.', 'bus-ticket-booking-with-seat-reservation'),
					'show_drop_off_point' => esc_html__('Turn On or Off drop-off point.', 'bus-ticket-booking-with-seat-reservation'),
					'tax_class' => esc_html__('To add any new tax class , Please go to WooCommerce ->Settings->Tax Area', 'bus-ticket-booking-with-seat-reservation'),
					//================//
					'mp_slider_images' => esc_html__('Please upload images for gallery', 'bus-ticket-booking-with-seat-reservation'),
					//''          => esc_html__( '', 'bus-ticket-booking-with-seat-reservation' ),
				);
				$des = apply_filters('wbtm_filter_description_array', $des);
				return $des[$key];
			}
			public static function info_text($key) {
				$data = self::description_array($key);
				if ($data) {
					?>
					<?php echo esc_html($data); ?>
					<?php
				}
			}
			public function save_settings($post_id) {
				if (!isset($_POST['wbtm_type_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wbtm_type_nonce'])), 'wbtm_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
					return;
				}
				//General settings
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$bus_logo = isset($_POST['wbtm_bus_logo']) ? sanitize_text_field(wp_unslash($_POST['wbtm_bus_logo'])) : '';
					$bus_no = isset($_POST['wbtm_bus_no']) ? sanitize_text_field(wp_unslash($_POST['wbtm_bus_no'])) : '';
					$bus_category = isset($_POST['wbtm_bus_category']) ? sanitize_text_field(wp_unslash($_POST['wbtm_bus_category'])) : '';
					$wbtm_registration = isset($_POST['wbtm_registration']) && sanitize_text_field(wp_unslash($_POST['wbtm_registration'])) ? 'yes' : 'no';
					
					update_post_meta($post_id, 'wbtm_bus_logo', $bus_logo);
					update_post_meta($post_id, 'wbtm_bus_no', $bus_no);
					update_post_meta($post_id, 'wbtm_bus_category', $bus_category);
					update_post_meta($post_id, 'wbtm_registration', $wbtm_registration);
					// Check if the term exists, if not create it
					$term = term_exists($bus_category, 'wbtm_bus_cat');
					if (!$term) {
						$term = wp_insert_term($bus_category, 'wbtm_bus_cat');
					}
					// Set the taxonomy term for this bus
					if (!is_wp_error($term)) {
						$term_id = is_array($term) ? $term['term_id'] : $term;
						wp_set_object_terms($post_id, intval($term_id), 'wbtm_bus_cat', false);
					}
				}
				//date settings
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					//************************************//
					$date_type = isset($_POST['show_operational_on_day']) ? sanitize_text_field(wp_unslash($_POST['show_operational_on_day'])) : 'no';
					update_post_meta($post_id, 'show_operational_on_day', $date_type);
					//**********************//
					$particular_dates = isset($_POST['wbtm_particular_dates']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_particular_dates'])) : [];
					$particular = array();
					if (!empty($particular_dates)) {
						foreach ($particular_dates as $particular_date) {
							if (!empty($particular_date)) {
								if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $particular_date)) {
									$particular[] = $particular_date;
								} else {
									$particular[] = gmdate('Y-m-d', strtotime(gmdate('Y') . '-' . $particular_date));
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_particular_dates', array_unique($particular));
					//*************************//
					$repeated_start_date = isset($_POST['wbtm_repeated_start_date']) ? sanitize_text_field(wp_unslash($_POST['wbtm_repeated_start_date'])) : '';
					$repeated_start_date = $repeated_start_date ? gmdate('Y-m-d', strtotime($repeated_start_date)) : '';
					update_post_meta($post_id, 'wbtm_repeated_start_date', $repeated_start_date);
					$repeated_end_date = isset($_POST['wbtm_repeated_end_date']) ? sanitize_text_field(wp_unslash($_POST['wbtm_repeated_end_date'])) : '';
					$repeated_end_date = $repeated_end_date ? gmdate('Y-m-d', strtotime($repeated_end_date)) : '';
					update_post_meta($post_id, 'wbtm_repeated_end_date', $repeated_end_date);
					$repeated_after = isset($_POST['wbtm_repeated_after']) ? sanitize_text_field(wp_unslash($_POST['wbtm_repeated_after'])) : 1;
					$active_days = isset($_POST['wbtm_active_days']) ? sanitize_text_field(wp_unslash($_POST['wbtm_active_days'])) : '';
					update_post_meta($post_id, 'wbtm_repeated_after', $repeated_after);
					update_post_meta($post_id, 'wbtm_active_days', $active_days);
					//**********************//
					$off_days = isset($_POST['wbtm_off_days']) ? sanitize_text_field(wp_unslash($_POST['wbtm_off_days'])) : '';
					update_post_meta($post_id, 'wbtm_off_days', $off_days);
					//**********************//
					$off_dates = isset($_POST['wbtm_off_dates']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_off_dates'])) : [];
					$_off_dates = array();
					if (sizeof($off_dates) > 0) {
						foreach ($off_dates as $off_date) {
							if ($off_date) {
								$_off_dates[] = $off_date;
							}
						}
					}
					update_post_meta($post_id, 'wbtm_off_dates', $_off_dates);
					//**********************//
					$off_schedules = [];
					$from_dates = isset($_POST['wbtm_from_date']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_from_date'])) : [];
					$to_dates = isset($_POST['wbtm_to_date']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_to_date'])) : [];
					if (sizeof($from_dates) > 0) {
						foreach ($from_dates as $key => $from_date) {
							if ($from_date && $to_dates[$key]) {
								$off_schedules[] = [
									'from_date' => $from_date,
									'to_date' => $to_dates[$key],
								];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_offday_schedule', $off_schedules);
					//***********************************//
					$from_dates = isset($_POST['wbtm_from_off_date']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_from_off_date'])) : [];
					$to_dates = isset($_POST['wbtm_to_off_date']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_to_off_date'])) : [];
					$off_date_ranges = [];
					if (sizeof($from_dates) > 0) {
						foreach ($from_dates as $key => $from_date) {
							if ($from_date && $to_dates[$key]) {
								$off_date_ranges[] = [
									'from_date' => $from_date,
									'to_date' => $to_dates[$key],
								];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_offday_range', $off_date_ranges);
				}
				//pricing and  routing
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$route_infos = [];
					$bp = [];
					$dp = [];
					$stops = isset($_POST['wbtm_route_place']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_route_place'])) : [];
					$times = isset($_POST['wbtm_route_time']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_route_time'])) : [];
					$types = isset($_POST['wbtm_route_type']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_route_type'])) : [];
					$next_days = isset($_POST['wbtm_route_next_day']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_route_next_day'])) : [];
					if (sizeof($stops) > 0) {
						foreach ($stops as $key => $stop) {
							if ($stop && $times[$key] && $types[$key]) {
								$next_day_value = isset($next_days[$key]) ? $next_days[$key] : '0';
								$route_infos[] = [
									'place' => $stop,
									'time' => $times[$key],
									'type' => $types[$key],
									'next_day' => $next_day_value == '1',
								];
							}
						}
					}
					$count = sizeof($route_infos);
					if ($count > 0) {
						$route_infos[0]['type'] = 'bp';
						//$route_infos[0]['interval'] = 0;
						$route_infos[$count - 1]['type'] = 'dp';
						//$route_infos[$count - 1]['interval'] = 0;
						foreach ($route_infos as $route_info) {
							if ($route_info['type'] == 'bp') {
								$bp[] = $route_info['place'];
							} elseif ($route_info['type'] == 'dp') {
								$dp[] = $route_info['place'];
							} else {
								$bp[] = $route_info['place'];
								$dp[] = $route_info['place'];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_route_info', $route_infos);
					update_post_meta($post_id, 'wbtm_bus_bp_stops', $bp);
					update_post_meta($post_id, 'wbtm_bus_next_stops', $dp);
					if (sizeof($route_infos) > 0) {
						$route_direction = [];
						foreach ($route_infos as $route) {
							$route_direction[] = $route['place'];
						}
						$route_direction = array_unique($route_direction);
						update_post_meta($post_id, 'wbtm_route_direction', $route_direction);
					}
					/********************************************/
					$price_infos = [];
					$stops_bps = isset($_POST['wbtm_price_bp']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_price_bp'])) : [];
					$stops_dps = isset($_POST['wbtm_price_dp']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_price_dp'])) : [];
					$adult_price = isset($_POST['wbtm_adult_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_adult_price'])) : [];
					$child_price = isset($_POST['wbtm_child_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_child_price'])) : [];
					$infant_price = isset($_POST['wbtm_infant_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_infant_price'])) : [];
					if (sizeof($stops_bps) > 0) {
						foreach ($stops_bps as $key => $stops_bp) {
							if ($stops_bp && $stops_dps[$key] && isset($adult_price[$key])) {
								$adult = $adult_price[$key] === '' ? '' : (float)$adult_price[$key];
								$child = !isset($child_price[$key]) || $child_price[$key] === '' ? '' : (float)$child_price[$key];
								$infant = !isset($infant_price[$key]) || $infant_price[$key] === '' ? '' : (float)$infant_price[$key];
								$price_infos[] = [
									'wbtm_bus_bp_price_stop' => $stops_bp,
									'wbtm_bus_dp_price_stop' => $stops_dps[$key],
									'wbtm_bus_price' => $adult,
									'wbtm_bus_child_price' => $child,
									'wbtm_bus_infant_price' => $infant,
								];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_prices', $price_infos);
				}
				//Seat configuration
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$pickup_infos = [];
					$count = 0;
					$hidden_ids = isset($_POST['wbtm_pickup_unique_id']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_pickup_unique_id'])) : [];
					$wbtm_pickup_bp = isset($_POST['wbtm_bp_pickup']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_bp_pickup'])) : [];
					$wbtm_pickup = isset($_POST['wbtm_pickup_name']) ? wp_unslash($_POST['wbtm_pickup_name']) : [];
					$wbtm_pickup_time = isset($_POST['wbtm_pickup_time']) ? wp_unslash($_POST['wbtm_pickup_time']) : [];
					if (sizeof($hidden_ids) > 0) {
						foreach ($hidden_ids as $hidden_id) {
							$pickups = array_key_exists($hidden_id, $wbtm_pickup) ? $wbtm_pickup[$hidden_id] : [];
							$pickup_times = array_key_exists($hidden_id, $wbtm_pickup_time) ? $wbtm_pickup_time[$hidden_id] : '';
							if (array_key_exists($hidden_id, $wbtm_pickup_bp) && $wbtm_pickup_bp[$hidden_id] && is_array($pickups) && sizeof($pickups) > 0 && is_array($pickup_times) &&  sizeof($pickup_times) > 0) {
								foreach ($pickups as $key => $pickup) {
									if ($pickup && $pickup_times[$key]) {
										$pickup_infos[$count]['bp_point'] = $wbtm_pickup_bp[$hidden_id];
										$pickup_infos[$count]['pickup_info'][] = [
											'pickup_point' => $pickup,
											'time' => $pickup_times[$key],
										];
									}
								}
							}
							$count++;
						}
					}
					update_post_meta($post_id, 'wbtm_pickup_point', $pickup_infos);
					$display_pickup = isset($_POST['show_pickup_point']) && sanitize_text_field(wp_unslash($_POST['show_pickup_point'])) ? 'yes' : 'no';
					$wbtm_pickup_point_required = isset($_POST['wbtm_pickup_point_required']) && sanitize_text_field(wp_unslash($_POST['wbtm_pickup_point_required'])) ? 'yes' : 'no';
					update_post_meta($post_id, 'show_pickup_point', $display_pickup);
					update_post_meta($post_id, 'wbtm_pickup_point_required', $wbtm_pickup_point_required);
					//************************//
					$drop_off_infos = [];
					$d_count = 0;
					$d_hidden_ids = isset($_POST['wbtm_drop_off_unique_id']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_drop_off_unique_id'])) : [];
					$wbtm_dp_pickup = isset($_POST['wbtm_dp_pickup']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_dp_pickup'])) : [];
					$wbtm_drop_off_name = isset($_POST['wbtm_drop_off_name']) ? wp_unslash($_POST['wbtm_drop_off_name']) : [];
					$wbtm_drop_off_time = isset($_POST['wbtm_drop_off_time']) ?  wp_unslash($_POST['wbtm_drop_off_time']) : [];
					if (sizeof($d_hidden_ids) > 0 ) {
						foreach ($d_hidden_ids as $d_hidden_id) {
							$drop_offs = array_key_exists($d_hidden_id, $wbtm_drop_off_name) ? $wbtm_drop_off_name[$d_hidden_id] : [];
							$drop_off_times = array_key_exists($d_hidden_id, $wbtm_drop_off_time) ? $wbtm_drop_off_time[$d_hidden_id] : '';
							if (array_key_exists($d_hidden_id, $wbtm_dp_pickup) && $wbtm_dp_pickup[$d_hidden_id] && sizeof($drop_offs) > 0 && sizeof($drop_off_times) > 0) {
								foreach ($drop_offs as $key => $drop_off) {
									if ($drop_off && $drop_off_times[$key]) {
										$drop_off_infos[$d_count]['dp_point'] = $wbtm_dp_pickup[$d_hidden_id];
										$drop_off_infos[$d_count]['drop_off_info'][] = [
											'drop_off_point' => $drop_off,
											'time' => $drop_off_times[$key],
										];
									}
								}
							}
							$d_count++;
						}
					}
					update_post_meta($post_id, 'wbtm_drop_off_point', $drop_off_infos);
					$display_dro_off = isset($_POST['show_drop_off_point']) && sanitize_text_field(wp_unslash($_POST['show_drop_off_point'])) ? 'yes' : 'no';
					$wbtm_dropping_point_required = isset($_POST['wbtm_dropping_point_required']) && sanitize_text_field(wp_unslash($_POST['wbtm_dropping_point_required'])) ? 'yes' : 'no';
					update_post_meta($post_id, 'show_drop_off_point', $display_dro_off);
					update_post_meta($post_id, 'wbtm_dropping_point_required', $wbtm_dropping_point_required);
				}
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$cabin_mode_enabled = isset($_POST['wbtm_cabin_mode_enabled']) && sanitize_text_field(wp_unslash($_POST['wbtm_cabin_mode_enabled'])) ? 'yes' : 'no';
					update_post_meta($post_id, 'wbtm_cabin_mode_enabled', $cabin_mode_enabled);
					$cabin_count = isset($_POST['wbtm_cabin_count']) ? sanitize_text_field(wp_unslash($_POST['wbtm_cabin_count'])) : 1;
					$cabin_names = isset($_POST['wbtm_cabin_name']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_cabin_name'])) : [];
					$cabin_enabled = isset($_POST['wbtm_cabin_enabled']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_cabin_enabled'])) : [];
					$cabin_rows = isset($_POST['wbtm_cabin_rows']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_cabin_rows'])) : [];
					$cabin_cols = isset($_POST['wbtm_cabin_cols']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_cabin_cols'])) : [];
					$cabin_price_multipliers = isset($_POST['wbtm_cabin_price_multiplier']) ? array_map('sanitize_text_field', wp_unslash($_POST['wbtm_cabin_price_multiplier'])) : [];
					$cabin_config = [];
					for ($i = 0; $i < $cabin_count; $i++) {
						$cabin_config[] = [
							'name' => $cabin_names[$i] ?? 'Cabin ' . ($i + 1),
							'enabled' => isset($cabin_enabled[$i]) ? 'yes' : 'no',
							'rows' => intval($cabin_rows[$i] ?? 0),
							'cols' => intval($cabin_cols[$i] ?? 0),
							'price_multiplier' => floatval($cabin_price_multipliers[$i] ?? 1.0)
						];
					}
					update_post_meta($post_id, 'wbtm_cabin_config', $cabin_config);
					// Save cabin seat plans only when cabin mode is enabled
					if ($cabin_mode_enabled === 'yes') {
						$total_seat = 0;
						$has_enabled_cabin = false;
						$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
						foreach ($cabin_config as $cabin_index => $cabin) {
							if (($cabin['enabled'] ?? 'yes') !== 'yes')
								continue;
							$has_enabled_cabin = true;
							$rows = $cabin['rows'] ?? 0;
							$cols = $cabin['cols'] ?? 0;
							$cabin_seat_info = [];
							if ($rows > 0 && $cols > 0) {
								for ($j = 1; $j <= $cols; $j++) {
									/*$col_infos = isset($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j]) ? sanitize_text_field(wp_unslash($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j])) : null;
									$col_rotation_infos = isset($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j . '_rotation']) ? sanitize_text_field(wp_unslash($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j . '_rotation'])) : null;*/
									$col_infos = isset($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j]) ?$_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j] : null;
									$col_rotation_infos = isset($_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j . '_rotation']) ? $_POST['wbtm_cabin_' . $cabin_index . '_seat' . $j . '_rotation'] : null;
									if ($col_infos === null) {
										$col_infos = [];
									} elseif (is_array($col_infos)) {
										$col_infos = array_values($col_infos);
									} else {
										$col_infos = [$col_infos];
									}
									if ($col_rotation_infos === null) {
										$col_rotation_infos = [];
									} elseif (is_array($col_rotation_infos)) {
										$col_rotation_infos = array_values($col_rotation_infos);
									} else {
										$col_rotation_infos = [$col_rotation_infos];
									}
									for ($i = 0; $i < $rows; $i++) {
										if (isset($col_infos[$i])) {
											$seat_value = $col_infos[$i];
											$rotation_value = $col_rotation_infos[$i] ?? '0';
											$cabin_seat_info[$i]['cabin_' . $cabin_index . '_seat' . $j] = $seat_value;
											if ($enable_rotation == 'yes') {
												$cabin_seat_info[$i]['cabin_' . $cabin_index . '_seat' . $j . '_rotation'] = $rotation_value;
											}
											if ($seat_value && $seat_value != 'door' && $seat_value != 'wc') {
												$total_seat++;
											}
										}
									}
								}
							}
							update_post_meta($post_id, 'wbtm_cabin_seats_info_' . $cabin_index, $cabin_seat_info);
						}
						if ($has_enabled_cabin) {
							update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
						}
					}
					$seat_type = isset($_POST['wbtm_seat_type_conf']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_type_conf'])) : '';
					update_post_meta($post_id, 'wbtm_seat_type_conf', $seat_type);
					/***********************/
					$driver_seat_position = isset($_POST['driver_seat_position']) ? sanitize_text_field(wp_unslash($_POST['driver_seat_position'])) : '';
					$rows = isset($_POST['wbtm_seat_rows_hidden']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_rows_hidden'])) : 0;
					$columns = isset($_POST['wbtm_seat_cols_hidden']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_cols_hidden'])) : 0;
					$wbtm_enable_seat_rotation = isset($_POST['wbtm_enable_seat_rotation']) && sanitize_text_field(wp_unslash($_POST['wbtm_enable_seat_rotation'])) ? 'yes' : 'no';
					update_post_meta($post_id, 'driver_seat_position', $driver_seat_position);
					update_post_meta($post_id, 'wbtm_seat_rows', $rows);
					update_post_meta($post_id, 'wbtm_seat_cols', $columns);
					update_post_meta($post_id, 'wbtm_enable_seat_rotation', $wbtm_enable_seat_rotation);
					$lower_deck_info = [];
					$total_seat = 0;
					if ($rows > 0 && $columns > 0) {
						for ($j = 1; $j <= $columns; $j++) {
							/*$col_infos = isset($_POST['wbtm_seat' . $j]) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat' . $j])) : '';
							$col_rotation_infos = isset($_POST['wbtm_seat' . $j . '_rotation']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat' . $j . '_rotation'])) : '';*/

                            $col_infos = isset($_POST['wbtm_seat' . $j]) ? $_POST['wbtm_seat' . $j] : '';
                            $col_rotation_infos = isset($_POST['wbtm_seat' . $j . '_rotation']) ? $_POST['wbtm_seat' . $j . '_rotation'] : '';

							if ($col_infos === null) {
								$col_infos = [];
							} elseif (is_array($col_infos)) {
								$col_infos = array_values($col_infos);
							} else {
								$col_infos = [$col_infos];
							}
							if ($col_rotation_infos === null) {
								$col_rotation_infos = [];
							} elseif (is_array($col_rotation_infos)) {
								$col_rotation_infos = array_values($col_rotation_infos);
							} else {
								$col_rotation_infos = [$col_rotation_infos];
							}
							for ($i = 0; $i < $rows; $i++) {
								$seat_value = $col_infos[$i] ?? '';
								$rotation_value = $col_rotation_infos[$i] ?? '0';
								$lower_deck_info[$i]['seat' . $j] = $seat_value;
								if ($wbtm_enable_seat_rotation == 'yes') {
									$lower_deck_info[$i]['seat' . $j . '_rotation'] = $rotation_value;
								}
								if ($seat_value && $seat_value != 'door' && $seat_value != 'wc') {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info', $lower_deck_info);
					/***********************/
					$wbtm_show_upper_desk = isset($_POST['wbtm_show_upper_desk']) && sanitize_text_field(wp_unslash($_POST['wbtm_show_upper_desk'])) ? 'yes' : 'no';
					$rows_dd = isset($_POST['wbtm_seat_rows_dd_hidden']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_rows_dd_hidden'])) : 0;
					$cols_dd = isset($_POST['wbtm_seat_cols_dd_hidden']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_cols_dd_hidden'])) : 0;
					$wbtm_seat_dd_price_parcent = isset($_POST['wbtm_seat_dd_price_parcent']) ? sanitize_text_field(wp_unslash($_POST['wbtm_seat_dd_price_parcent'])) : '';
					update_post_meta($post_id, 'show_upper_desk', $wbtm_show_upper_desk);
					update_post_meta($post_id, 'wbtm_seat_rows_dd', $rows_dd);
					update_post_meta($post_id, 'wbtm_seat_cols_dd', $cols_dd);
					update_post_meta($post_id, 'wbtm_seat_dd_price_parcent', $wbtm_seat_dd_price_parcent);
					$upper_deck_info = [];
					if ($rows_dd > 0 && $cols_dd > 0) {
						for ($j = 1; $j <= $cols_dd; $j++) {
							/*$col_infos = isset($_POST['wbtm_dd_seat' . $j]) ? sanitize_text_field(wp_unslash($_POST['wbtm_dd_seat' . $j])) : null;
							$col_rotation_infos = isset($_POST['wbtm_dd_seat' . $j . '_rotation']) ? sanitize_text_field(wp_unslash($_POST['wbtm_dd_seat' . $j . '_rotation'])) : null;*/

							$col_infos = isset($_POST['wbtm_dd_seat' . $j]) ?$_POST['wbtm_dd_seat' . $j] : null;
							$col_rotation_infos = isset($_POST['wbtm_dd_seat' . $j . '_rotation']) ? $_POST['wbtm_dd_seat' . $j . '_rotation'] : null;
							if ($col_infos === null) {
								$col_infos = [];
							} elseif (is_array($col_infos)) {
								$col_infos = array_values($col_infos);
							} else {
								$col_infos = [$col_infos];
							}
							if ($col_rotation_infos === null) {
								$col_rotation_infos = [];
							} elseif (is_array($col_rotation_infos)) {
								$col_rotation_infos = array_values($col_rotation_infos);
							} else {
								$col_rotation_infos = [$col_rotation_infos];
							}
							for ($i = 0; $i < $rows_dd; $i++) {
								$seat_value = $col_infos[$i] ?? '';
								$rotation_value = $col_rotation_infos[$i] ?? '0';
								$upper_deck_info[$i]['dd_seat' . $j] = $seat_value;
								if ($wbtm_enable_seat_rotation == 'yes') {
									$upper_deck_info[$i]['dd_seat' . $j . '_rotation'] = $rotation_value;
								}
								if ($seat_value && $seat_value != 'door' && $seat_value != 'wc' && $wbtm_show_upper_desk == 'yes') {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $upper_deck_info);
					/***********************/
					$has_cabin_config = $cabin_mode_enabled === 'yes' && !empty($cabin_config) && count(array_filter($cabin_config, function ($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;
					if (!$has_cabin_config) {
						$current_seat = isset($_POST['wbtm_get_total_seat']) ? sanitize_text_field(wp_unslash($_POST['wbtm_get_total_seat'])) : 0;
						$total_seat = $seat_type == 'wbtm_seat_plan' ? $total_seat : $current_seat;
						update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
					}
				}
				//Extra service configuration
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$new_extra_service = array();
					$extra_names = isset($_POST['ex_option_name']) ? array_map('sanitize_text_field', wp_unslash($_POST['ex_option_name'])) : [];
					$extra_price = isset($_POST['ex_option_price']) ? array_map('sanitize_text_field', wp_unslash($_POST['ex_option_price'])) : [];
					$extra_qty = isset($_POST['ex_option_qty']) ? array_map('sanitize_text_field', wp_unslash($_POST['ex_option_qty'])) : [];
					$extra_qty_type = isset($_POST['ex_option_qty_type']) ? array_map('sanitize_text_field', wp_unslash($_POST['ex_option_qty_type'])) : [];
					$extra_count = count($extra_names);
					for ($i = 0; $i < $extra_count; $i++) {
						if ($extra_names[$i] && $extra_price[$i] && $extra_qty[$i] > 0) {
							//$new_extra_service[$i]['option_icon'] = $extra_icon[$i] ?? '';
							$new_extra_service[$i]['option_name'] = $extra_names[$i];
							$new_extra_service[$i]['option_price'] = $extra_price[$i];
							$new_extra_service[$i]['option_qty'] = $extra_qty[$i];
							$new_extra_service[$i]['option_qty_type'] = $extra_qty_type[$i] ?? 'inputbox';
						}
					}
					update_post_meta($post_id, 'wbtm_extra_services', $new_extra_service);
					$display_ex = isset($_POST['show_extra_service']) && sanitize_text_field(wp_unslash($_POST['show_extra_service'])) ? 'yes' : 'no';
					update_post_meta($post_id, 'show_extra_service', $display_ex);
				}
				//tax configuration
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$tax_status = isset($_POST['_tax_status']) ? sanitize_text_field(wp_unslash($_POST['_tax_status'])) : 'none';
					$tax_class = isset($_POST['_tax_class']) ? sanitize_text_field(wp_unslash($_POST['_tax_class'])) : '';
					update_post_meta($post_id, '_tax_status', $tax_status);
					update_post_meta($post_id, '_tax_class', $tax_class);
				}
				do_action('wbtm_settings_save', $post_id);
				//do_action('wcpp_partial_settings_saved', $post_id);
			}
		}
		new WBTM_Settings();
	}