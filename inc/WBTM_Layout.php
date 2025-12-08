<?php
	/*
	* @Author 		engr.sumonazma@gmail.com
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Layout')) {
		class WBTM_Layout {
			public function __construct() {
				add_action('wbtm_search_result', [$this, 'search_result'], 10, 9);
				/*********************/
				add_action('wp_ajax_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				add_action('wp_ajax_nopriv_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				/**************************/
				add_action('wp_ajax_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
				add_action('wp_ajax_nopriv_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
				/**************************/
				add_action('wp_ajax_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
				add_action('wp_ajax_nopriv_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
				/**************************/
				add_action('wp_ajax_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
				add_action('wp_ajax_nopriv_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
				/**************************/
				add_action('wp_ajax_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				add_action('wp_ajax_nopriv_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				/**************************/
			}
			public function search_result($start_route, $end_route, $date, $post_id = '', $style = '', $btn_show = '', $search_info = [], $journey_type = '', $left_filter_show = '') {
				if ($style == 'flix') {
					require WBTM_Functions::template_path('layout/search_result_flix.php');
				} else {
					require WBTM_Functions::template_path('layout/search_result.php');
				}
			}
			public function get_wbtm_dropping_point() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					self::route_list($post_id, $start_route);
					die();
				}
			}
			public function get_wbtm_journey_date() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					self::journey_date_picker($post_id, $start_route, $end_route);
					die();
				}
			}
			public function get_wbtm_return_date() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
					self::return_date_picker($post_id, $end_route, $start_route, $j_date);
					die();
				}
			}
			public function get_wbtm_bus_list() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
					$r_date = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
					$style = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : '';
					$btn_show = isset($_POST['btn_show']) ? sanitize_text_field(wp_unslash($_POST['btn_show'])) : '';
					$left_filter_show = isset($_POST['left_filter_show']) ? sanitize_text_field(wp_unslash($_POST['left_filter_show'])) : '';
					$search_info['bus_start_route'] = $start_route;
					$search_info['bus_end_route'] = $end_route;
					$search_info['j_date'] = $j_date;
					$search_info['r_date'] = $r_date;
					self::wbtm_bus_list($post_id, $start_route, $end_route, $j_date, $r_date, $style, $btn_show, $search_info, $left_filter_show);
					$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'cart_empty_after_search', 'off');
					if ($redirect_enabled === 'on' && WC()->cart->get_cart_contents_count() > 0) {
						WC()->cart->empty_cart();
					}
					die();
				}
			}
			public function get_wbtm_bus_details() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					/*$post_id = WBTM_Global_Function::data_sanitize($_POST['post_id']);
					$start_route = WBTM_Global_Function::data_sanitize($_POST['start_route']);
					$end_route = WBTM_Global_Function::data_sanitize($_POST['end_route']);
					$date = $_POST['date'] ?? '';*/
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
					$backend_order = isset($_POST['backend_order']) ? sanitize_text_field(wp_unslash($_POST['backend_order'])) : '';
					$link_wc_product = WBTM_Global_Function::get_post_info($post_id, 'link_wc_product');
					$display_drop_off_point = WBTM_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
					$drop_off_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
					$drop_off_required = WBTM_Global_Function::get_post_info($post_id, 'wbtm_dropping_point_required', 'no');
					$display_pickup_point = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
					$pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
					$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
					if ($post_id > 0 && $start_route && $end_route && $date) {
						$all_info = WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route);
						$seat_price = $seat_price ?? WBTM_Functions::get_seat_price($post_id, $start_route, $end_route);
						$ticket_infos = $ticket_infos ?? WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route);
//                    $seat_column = $seat_column ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
//                    $seat_row = $seat_row ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
//                    $seat_infos = $seat_infos ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
//                    $cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
						$cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');
						$wbtm_bus_type = esc_html(WBTM_Functions::synchronize_bus_type($post_id));
						$display_wbtm_registration = WBTM_Global_Function::get_post_info($post_id, 'wbtm_registration', 'yes');
						if ($all_info['available_seat'] > 0) {
							$seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
							$seat_row = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
							$seat_column = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
							$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
							$has_cabin_config = !empty($cabin_config) && count(array_filter($cabin_config, function ($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;
							$bus_start_time = $all_info['start_time'];
							$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
							$r_date = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
							$bus_start_route = isset($_POST['bus_start_route']) ? sanitize_text_field(wp_unslash($_POST['bus_start_route'])) : '';
							$bus_end_route = isset($_POST['bus_end_route']) ? sanitize_text_field(wp_unslash($_POST['bus_end_route'])) : '';
							?>
                            <div class="wbtm_registration_area _bgWhite mT">
                                <form action="" method="post" class="">
                                    <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                                    <input type="hidden" name='wbtm_start_point' value='<?php echo esc_attr($all_info['start_point']); ?>'/>
                                    <input type="hidden" name='wbtm_start_time' value='<?php echo esc_attr($bus_start_time); ?>'/>
                                    <input type="hidden" name='wbtm_bp_place' value='<?php echo esc_attr($all_info['bp']); ?>'/>
                                    <input type="hidden" name='wbtm_bp_time' value='<?php echo esc_attr($all_info['bp_time']); ?>'/>
                                    <input type="hidden" name='wbtm_dp_place' value='<?php echo esc_attr($all_info['dp']); ?>'/>
                                    <input type="hidden" name='wbtm_dp_time' value='<?php echo esc_attr($all_info['dp_time']); ?>'/>
                                    <input type="hidden" name='bus_start_route' value='<?php echo esc_attr($bus_start_route); ?>'/>
                                    <input type="hidden" name='bus_end_route' value='<?php echo esc_attr($bus_end_route); ?>'/>
                                    <input type="hidden" name='j_date' value='<?php echo esc_attr($j_date); ?>'/>
                                    <input type="hidden" name='r_date' value='<?php echo esc_attr($r_date); ?>'/>
									<?php
										wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce');
										// Check for cabin configuration or legacy seat plan
										if ($seat_type == 'wbtm_seat_plan' && ($has_cabin_config || (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0))) {
											require WBTM_Functions::template_path('layout/registration_seat_plan.php');
										} else {
											require WBTM_Functions::template_path('layout/registration_without_seat_plan.php');
										}
									?>
                                </form>
								<?php do_action('wbtm_attendee_form_hidden', $post_id); ?>
                            </div>
							<?php
						} else {
							WBTM_Layout::msg(WBTM_Translations::text_no_seat());
						}
					}
					die();
				}
			}
			public static function wbtm_bus_list($post_id, $start_route, $end_route, $j_date, $r_date, $style = '', $btn_show = '', $search_info = [], $left_filter_show = '') {
				if ($start_route && $end_route && $j_date) { ?>
                    <div class="wbtm-bus-lists" id="wbtm_start_container">
                        <div class="wbtm-date-suggetion">
							<?php self::next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date); ?>
                        </div>
                        <div class="wbtm-date-route">
							<?php self::route_title($start_route, $end_route, $j_date, $r_date); ?>
                        </div>
                        <div class="wbtm-bus-lists" id="start_bus">
							<?php do_action('wbtm_search_result', $start_route, $end_route, $j_date, $post_id, $style, $btn_show, $search_info, 'start_journey', $left_filter_show); ?>
                        </div>
                    </div>
                    <div class="wbtm-date-return-route" id="wbtm_date_return_route_start">
						<?php esc_html_e('Return', 'bus-ticket-booking-with-seat-reservation'); ?><?php self::route_title($start_route, $end_route, $j_date, $r_date, true); ?>
                    </div>
				<?php }
				if ($post_id == 0 && $start_route && $end_route && $r_date) { ?>
                    <div class="wbtm-bus-lists" id="wbtm_return_container" style="display: none">
                        <div class="wbtm-date-suggetion">
							<?php self::next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date, true); ?>
                        </div>
                        <div class="wbtm-bus-notification" id="wbtm_selected_bus_notification" style="display: none">
							<?php esc_attr_e('Your ticket has been added to the cart.', 'bus-ticket-booking-with-seat-reservation'); ?>
                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="cart-link">
								<?php esc_attr_e('View Cart', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a>
							<?php esc_attr_e('Book your return ticket now!', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </div>
                        <div class="wbtm_seleced_start_bus" id="wbtm_seleced_start_bus"></div>
                        <h4 class="lists-title"><?php echo esc_html(WBTM_Translations::text_return_trip()); ?></h4>
                        <div class="wbtm-date-route" id="wbtm_date_return_route_return" style="display: none">
							<?php self::route_title($start_route, $end_route, $j_date, $r_date, true); ?>
                        </div>
                        <div class="wbtm-bus-lists" id="return_bus">
							<?php do_action('wbtm_search_result', $end_route, $start_route, $r_date, '', $style, $btn_show, $search_info, 'return_journey', $left_filter_show); ?>
                        </div>
                    </div>
				<?php }
			}
			public static function wbtm_get_time_diff($start_time, $end_time) {
				$start = new DateTime($start_time);
				$end = new DateTime($end_time);
				$interval = $start->diff($end);
				$result = [];
				if ($interval->d > 0) {
					$result[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
				}
				if ($interval->h > 0) {
					$result[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
				}
				if ($interval->i > 0) {
					$result[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
				}
				if (empty($result)) {
					return '0 minutes';
				}
				return implode(' ', $result);
			}
			public static function selected_bus_display($data) {
				$bus_id = $data['post_id'];
				$bus_title = get_the_title($bus_id);
				$bus_image = get_the_post_thumbnail_url($bus_id, 'medium');
				$bus_number = get_post_meta($bus_id, 'wbtm_bus_no', true);
				$start_date_format = date("d F, Y", strtotime($data['j_date']));
				$start_time = date("h:i A", strtotime($data['wbtm_bp_time']));
				$end_time = date("h:i A", strtotime($data['wbtm_dp_time']));
				$time_diff = self::wbtm_get_time_diff($data['wbtm_bp_time'], $data['wbtm_dp_time']);
				ob_start();
				?>
                <div class="wbtm_selected_bus_card">
                    <div class="wbtm_selected_bus_image">
                        <img src="<?php echo esc_url($bus_image); ?>" alt="<?php echo esc_html($bus_title); ?>" style="width: 160px; height: 140px">
                    </div>
                    <div class="wbtm_selected_bus_img_name">
						<?php echo esc_html($bus_title); ?> <span><?php echo esc_html($bus_number); ?></span>
                    </div>
                    <div class="wbtm_selected_bus_info">
                        <div class="wbtm_selected_bus_date"><?php echo esc_attr($start_date_format); ?></div>
                        <div class="wbtm_selected_bus_route">
                            <div class="wbtm_selected_bus_left">
                                <div class="wbtm_selected_bus_time"><?php echo esc_attr($start_time) ?></div>
                                <div class="wbtm_selected_bus_city"><?php echo esc_attr($data['wbtm_bp_place']); ?></div>
                            </div>
                            <div class="wbtm_selected_bus_center">
                                <div class="wbtm_selected_bus_icon">🚍</div>
                                <div class="wbtm_selected_bus_duration"><?php echo esc_attr($time_diff); ?></div>
                                <div class="wbtm_selected_bus_arrow">➜</div>
                            </div>
                            <div class="wbtm_selected_bus_right">
                                <div class="wbtm_selected_bus_time"><?php echo esc_attr($end_time) ?></div>
                                <div class="wbtm_selected_bus_city"><?php echo esc_attr($data['wbtm_dp_place']); ?></div>
                            </div>
                        </div>
                        <div class="wbtm_selected_bus_seats">
                            Seat: <?php echo esc_attr($data['wbtm_selected_seat']); ?>
                        </div>
                    </div>
                    <div class="wbtm_selected_bus_payment">
                        <div class="wbtm_selected_bus_label"><?php esc_html_e('Total Amount to be Paid', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm_selected_bus_price"><?php echo esc_attr($data['price_val']); ?></div>
                        <button class="wbtm_selected_bus_btn"><?php esc_html_e('Booked', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                    </div>
                </div>
				<?php
				return ob_get_clean();
			}
			public static function route_list($post_id = 0, $start_route = '') {
				$all_routes = WBTM_Functions::get_bus_route($post_id, $start_route);
				if (sizeof($all_routes) > 0) {
					?>
                    <ul class="wbtm_input_select_list">
						<?php foreach ($all_routes as $route) { ?>
                            <li data-value="<?php echo esc_attr($route); ?>">
								<?php echo esc_html($route); ?>
                            </li>
						<?php } ?>
                    </ul>
					<?php
				}
			}
			public static function next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date, $return = false) {
				$route = $return ? $end_route : $start_route;
				$all_dates = WBTM_Functions::get_all_dates($post_id, $route);
				$total_date = sizeof($all_dates);
				if ($total_date > 0) {
					$active_date = $return ? $r_date : $j_date;
					if ($start_route && $end_route && $j_date) {
						$key = array_search($active_date, $all_dates);
						$start_key = $key > 2 ? $key - 2 : 0;
						$start_key = $total_date - 3 <= $key ? max(0, $total_date - 5) : $start_key;
						$all_dates = array_slice($all_dates, $start_key, 5);
						?>
                        <div class="_xs_equalChild ">
							<?php foreach ($all_dates as $date) { ?>
								<?php $btn_class = strtotime($date) == strtotime($active_date) ? 'active' : ''; ?>
                                <button type="button" class="wbtm_next_date <?php echo esc_attr($btn_class); ?>" data-date="<?php echo esc_attr($date); ?>">
									<?php echo esc_html(WBTM_Global_Function::date_format($date)); ?>
                                </button>
							<?php } ?>
                        </div>
						<?php
					}
				} else {
					WBTM_Layout::msg(WBTM_Translations::text_bus_close_msg());
				}
			}
			public static function route_title($start_route, $end_route, $j_date, $r_date, $return = false) {
				$start = $return ? $end_route : $start_route;
				$end = $return ? $start_route : $end_route;
				$date = $return ? $r_date : $j_date;
				if ($date) {
					?>
                    <div>
						<?php echo esc_html($start); ?>
                        <span class="fas fa-long-arrow-alt-right _mLR_xs"></span>
						<?php echo esc_html($end); ?>
                    </div>
                    <div>
						<?php echo esc_attr(WBTM_Global_Function::date_format($date)); ?>
                    </div>
					<?php
				}
			}
			public static function journey_date_picker($post_id = '', $start_route = '', $end_route = '', $date = '') {
				$date_format = WBTM_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
                <label class="fdColumn">
					<?php echo esc_attr(WBTM_Translations::text_journey_date()); ?>
                    <div class="calendar">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="hidden" name="j_date" value="<?php echo esc_attr($hidden_date); ?>" required/>
                        <input id="wbtm_journey_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl " placeholder="<?php echo esc_attr($now); ?>" data-alert="<?php echo esc_html(WBTM_Translations::text_select_route()); ?>" readonly required/>
                    </div>
                </label>
				<?php
				if ($start_route) {
					$all_dates = WBTM_Functions::get_all_dates($post_id, $start_route, $end_route);
					do_action('wbtm_load_date_picker_js', '#wbtm_journey_date', $all_dates);
				}
			}
			public static function return_date_picker($post_id = '', $end_route = '', $start_route = '', $j_date = '', $date = '') {
				$date_format = WBTM_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
                <label class="fdColumn">
					<?php echo esc_html(WBTM_Translations::text_return_date()); ?>
                    <div class="calendar">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="hidden" name="r_date" value="<?php echo esc_attr($hidden_date); ?>"/>
                        <input id="wbtm_return_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
                    </div>
                </label>
				<?php
				if ($end_route && $j_date) {
					$all_dates = WBTM_Functions::get_all_dates($post_id, $end_route, $start_route);
					if (sizeof($all_dates) > 0) {
						$j_date = strtotime($j_date);
						$date_list = [];
						foreach ($all_dates as $date) {
							if (strtotime($date) >= $j_date) {
								$date_list[] = $date;
							}
						}
						do_action('wbtm_load_date_picker_js', '#wbtm_return_date', $date_list);
					}
				}
			}
			public static function msg($msg, $class = '') {
				?>
                <div class="_mZero_textCenter <?php echo esc_attr($class); ?>">
                    <label class="_textTheme"><?php echo esc_html($msg); ?></label>
                </div>
				<?php
			}
			public static function trigger_view_seat_details() {
				?>
                <script type="text/javascript">
                    var get_wbtm_bus_details = document.getElementById("get_wbtm_bus_details");
                    get_wbtm_bus_details.click();
                </script>
				<?php
			}
		}
		new WBTM_Layout();
	}