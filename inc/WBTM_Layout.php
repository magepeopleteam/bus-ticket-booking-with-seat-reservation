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
				add_action('wbtm_search_result', [$this, 'search_result'], 10, 4);
				/*********************/
				add_action('wp_ajax_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				add_action('wp_ajax_nopriv_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				/**************************/
				add_action('wp_ajax_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				add_action('wp_ajax_nopriv_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				/**************************/
			}
			public function search_result($start_route, $end_route, $date, $post_id = '') {
				require WBTM_Functions::template_path('layout/search_result.php');
			}
			public function get_wbtm_dropping_point() {
				$post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
				$start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
				self::route_list($post_id, $start_route);
				die();
			}
			public function get_wbtm_bus_details() {
				$post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
				$start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
				$end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
				$date = $_POST['date'] ?? '';
				$seat_type = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
				if ($post_id > 0 && $start_route && $end_route && $date) {
					$all_info = WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route);
					if ($all_info['available_seat'] > 0) {
						$seat_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
						$seat_row = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
						$seat_column = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
						?>
						<div class="_infoLayout_xs wbtm_registration_area">
							<form action="" method="post" class="">
								<input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
								<input type="hidden" name='wbtm_start_point' value='<?php echo esc_attr($all_info['start_point']); ?>'/>
								<input type="hidden" name='wbtm_start_time' value='<?php echo esc_attr($all_info['start_time']); ?>'/>
								<input type="hidden" name='wbtm_bp_place' value='<?php echo esc_attr($all_info['bp']); ?>'/>
								<input type="hidden" name='wbtm_bp_time' value='<?php echo esc_attr($all_info['bp_time']); ?>'/>
								<input type="hidden" name='wbtm_dp_place' value='<?php echo esc_attr($all_info['dp']); ?>'/>
								<input type="hidden" name='wbtm_dp_time' value='<?php echo esc_attr($all_info['dp_time']); ?>'/>
								<?php do_action('wbtm_registration_form_inside', $post_id); ?>
								<?php
									if ($seat_type == 'wbtm_seat_plan' && sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0) {
										require WBTM_Functions::template_path('layout/registration_seat_plan.php');
									}
									else {
										require WBTM_Functions::template_path('layout/registration_without_seat_plan.php');
									}
								?>
							</form>
							<?php do_action('wbtm_attendee_form_hidden', $post_id); ?>
						</div>
						<?php
					}
					else {
						WBTM_Layout::msg(WBTM_Translations::text_no_seat());
					}
				}
				die();
			}
			public static function route_list($post_id = 0, $start_route = '') {
				$all_routes = WBTM_Functions::get_bus_route($post_id, $start_route);
				if (sizeof($all_routes) > 0) {
					?>
					<ul class="mp_input_select_list">
						<?php foreach ($all_routes as $route) { ?>
							<li data-value="<?php echo esc_attr($route); ?>">
								<span class="fas fa-map-marker"></span><?php echo esc_html($route); ?>
							</li>
						<?php } ?>
					</ul>
					<?php
				}
			}
			public static function next_date_suggestion($all_dates, $return = false) {
				if (sizeof($all_dates) > 0) {
					$count = 1;
					$start_route = isset($_POST['bus_start_route']) ? MP_Global_Function::data_sanitize($_POST['bus_start_route']) : '';
					$end_route = isset($_POST['bus_end_route']) ? MP_Global_Function::data_sanitize($_POST['bus_end_route']) : '';
					$j_date = $_POST['j_date'] ?? '';
					$r_date = $_POST['r_date'] ?? '';
					$active_date = $return ? $r_date : $j_date;
					if ($start_route && $end_route && $j_date) {
						?>
						<div class="buttonGroup _equalChild_fullWidth">
							<?php
								foreach ($all_dates as $date) {
									if ($count <= 6 && (strtotime($date) >= strtotime($active_date) || sizeof($all_dates) < 6)) {
										$btn_class = strtotime($date) == strtotime($active_date) ? '_themeButton_textWhite' : '_mpBtn_bgLight_textTheme';
										?>
										<button type="button" class="wbtm_next_date <?php echo esc_attr($btn_class); ?>" data-date="<?php echo esc_attr($date); ?>">
											<?php echo MP_Global_Function::date_format($date); ?>
										</button>
										<?php
										$count++;
									}
									?>
								<?php } ?>
						</div>
						<?php
					}
				}
			}
			public static function route_title($return = false) {
				$start_route = isset($_POST['bus_start_route']) ? MP_Global_Function::data_sanitize($_POST['bus_start_route']) : '';
				$end_route = isset($_POST['bus_end_route']) ? MP_Global_Function::data_sanitize($_POST['bus_end_route']) : '';
				$start = $return ? $end_route : $start_route;
				$end = $return ? $start_route : $end_route;
				$j_date = $_POST['j_date'] ?? '';
				$r_date = $_POST['r_date'] ?? '';
				$date = $return ? $r_date : $j_date;
				if ($date) {
					?>
					<div class="buttonGroup _mT_xs_equalChild_fullWidth">
						<button type="button" class="_mpBtn_h4">
							<?php echo esc_html($start); ?>
							<span class="fas fa-long-arrow-alt-right _mLR_xs"></span>
							<?php echo esc_html($end); ?>
						</button>
						<button type="button" class="_mpBtn_h4">
							<?php echo MP_Global_Function::date_format($date); ?>
						</button>
					</div>
					<?php
				}
			}
			public static function msg($msg, $class = '_bgWarning') {
				?>
				<div class="_dLayout_mZero <?php echo esc_attr($class); ?>">
					<h4 class="_textCenter_textWhite"><?php echo esc_html($msg); ?></h4>
				</div>
				<?php
			}
		}
		new WBTM_Layout();
	}