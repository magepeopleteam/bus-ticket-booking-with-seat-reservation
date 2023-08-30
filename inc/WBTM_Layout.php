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
				/*********************/
				add_action('wp_ajax_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				add_action('wp_ajax_nopriv_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				/**************************/
			}
			public function get_wbtm_dropping_point() {
				$post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
				$start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
				self::route_list($start_route, $post_id);
				die();
			}
			public static function route_list($start_route = '', $post_id = 0) {
				$all_routes = WBTM_Functions::get_bus_route($start_route, $post_id);
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
			public static function next_date_suggestion( $all_dates,$return=false,$post_id=0) {
				if (sizeof($all_dates) > 0) {
					$count = 1;
					$target_page = MP_Global_Function::get_settings('wbtm_bus_settings', 'search_target_page');
					$target_page = $target_page ? get_post_field('post_name', $target_page) : 'bus-search-list';
					$start_route = isset($_GET['bus_start_route']) ? MP_Global_Function::data_sanitize($_GET['bus_start_route']) : '';
					$end_route = isset($_GET['bus_end_route']) ? MP_Global_Function::data_sanitize($_GET['bus_end_route']) : '';
					$j_date = $_GET['j_date'] ?? '';
					$r_date = $_GET['r_date'] ?? '';
					$active_date=$return?$r_date:$j_date;
					$form_url=$post_id>0?'':get_site_url().'/'.$target_page;
					if ($start_route && $end_route && $j_date) {
					?>
					<div class="buttonGroup _mT_xs_equalChild_fullWidth">
						<?php
							foreach ($all_dates as $date) {
								if ($count <= 6 && strtotime($date) >= strtotime($active_date)) {
									$btn_class = strtotime($date) == strtotime($active_date) ? '_themeButton_textWhite' : '_mpBtn_bgLight_textTheme';
									$url_j_date=$return?$j_date:$date;
									$url_r_date=$return?$date:$r_date;
									$url=$form_url.'?bus_start_route='.$start_route.'&bus_end_route='.$end_route.'&j_date='.$url_j_date.'&r_date='.$url_r_date;
									?>
									<button type="button" class="<?php echo esc_attr($btn_class); ?>" data-href="<?php echo esc_attr($url);?>">
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
			public static function route_title($return=false){
				$start_route = isset($_GET['bus_start_route']) ? MP_Global_Function::data_sanitize($_GET['bus_start_route']) : '';
				$end_route = isset($_GET['bus_end_route']) ? MP_Global_Function::data_sanitize($_GET['bus_end_route']) : '';
				$start=$return?$end_route:$start_route;
				$end=$return?$start_route:$end_route;
				$j_date = $_GET['j_date'] ?? '';
				$r_date = $_GET['r_date'] ?? '';
				$date=$return?$r_date:$j_date;
				if($date){
				?>
				<div class="buttonGroup _mT_xs_equalChild_fullWidth">
					<h4 class="_bgBlack_textWhite_allCenter_padding">
						<?php echo esc_html($start); ?>
							<span class="fas fa-long-arrow-alt-right _mLR_xs"></span>
						<?php echo esc_html($end); ?>
					</h4>
					<h4 class="_bgBlack_textWhite_allCenter_padding">
						<?php echo MP_Global_Function::date_format($date); ?>
					</h4>
				</div>
				<?php
				}
			}
		}
		new WBTM_Layout();
	}