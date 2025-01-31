<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Shortcode')) {
		class WBTM_Shortcode {
			public function __construct() {
				add_shortcode('wbtm-bus-list', array($this, 'wbtm_bus_list'));
				add_shortcode('wbtm-bus-search-form', array($this, 'wbtm_bus_search'));
				add_shortcode('wbtm-bus-search', array($this, 'wbtm_bus_search'));
			}
			public function wbtm_bus_list($attribute, $content = null){
				$defaults = $this->default_attribute();
				$params = shortcode_atts($defaults, $attribute);
				$cat = $params['cat'];
				$show = $params['show'];
				$start = $params['start'];
				$end = $params['end'];
				$column = $params['column'];
				$style = $params['style'];
				$bus_ids = WBTM_Query::get_bus_id($start, $end, $cat);
				ob_start();
				if (sizeof($bus_ids) > 0) {
					$count = 0;
					?>
					<div class="mpStyle placeholderLoader mp_pagination_main_area">
						<div class="mpContainer flexWrap">
							<?php foreach ($bus_ids as $bus_id) { ?>
								<?php
								$thumbnail = MP_Global_Function::get_image_url($bus_id);
								$url = get_the_permalink($bus_id);
								$category = MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_category');
								$route = MP_Global_Function::get_post_info($bus_id, 'wbtm_route_direction', []);
								$d_class = $show > $count ? '' : 'dNone';
								$grid_class = 'grid_' . $column;
								$count++;
								?>
								<div class="placeholder_area mp_pagination_item _dShadow_9 <?php echo esc_attr($grid_class . ' ' . $d_class); ?>">
									<?php if ($category) { ?>
										<div class="ribbon"><?php echo esc_html($category); ?></div>
									<?php } ?>
									<div class="bg_image_area" data-href="<?php echo esc_attr($url); ?>" data-placeholder>
										<div data-bg-image="<?php echo esc_attr($thumbnail); ?>"></div>
									</div>
									<div class="divider"></div>
									<a href="<?php echo esc_attr($url); ?>">
										<h5 class="_textCenter_textTheme"><?php echo esc_html(get_the_title($bus_id)); ?></h5>
									</a>
									<div class="divider"></div>
									<h6 class="_allCenter"><?php echo esc_html(current($route)); ?><small><span class="fas fa-long-arrow-alt-right _mLR_xs"></span></small><?php echo esc_html(end($route)); ?></h6>
									<div class="divider"></div>
									<h6 class="_allCenter">
										<strong><?php echo WBTM_Translations::text_passenger_capacity(); ?> :</strong>
										<?php echo MP_Global_Function::get_post_info($bus_id, 'wbtm_get_total_seat', 0); ?>
									</h6>
									<div class="divider"></div>
									<div class="mp_wp_editor">
										<?php //echo get_the_content('', '', $bus_id); ?>
									</div>
								</div>
							<?php } ?>
						</div>
						<?php do_action('add_mp_pagination_section', $params, sizeof($bus_ids)); ?>
					</div>
					<?php
				}
				return ob_get_clean();
			}
			public function wbtm_bus_search($attr, $content = null) {
				$defaults = array("cat" => "0", "style" => '', "search-page" => '', 'left_filter' => 'off', 'left_filter_type' => 'on' ,'left_filter_operator' => 'on', 'left_filter_boarding' => 'on');
				$params = shortcode_atts($defaults, $attr);
				$cat = $params['cat'];
				$form_style = $params['style'];
				$search_path = $params['search-page'];
				$style = $params['style'];
				$left_filter            = $params['left_filter'];
				$left_filter_type       = $params['left_filter_type'];
				$left_filter_operator   = $params['left_filter_operator'];
				$left_filter_boarding   = $params['left_filter_boarding'];
				ob_start();
				do_action('woocommerce_before_single_product');
				?>
				<div class="mpStyle wbtm_container">
					<?php require WBTM_Functions::template_path('layout/search_form.php'); ?>
				</div>
				<?php
				do_action('wbtm_after_search_result_section', $params);
				return ob_get_clean();
			}
			public function default_attribute(): array {
				return array(
					"style" => 'grid',
					"show" => 9,
					"pagination" => "yes",
					'sort' => 'ASC',
					'sort_by' => '',
					"pagination-style" => "load_more",
					"column" => 3,
					"cat" => "",
					"start" => "",
					"end" => "",
				);
			}
		}
		new WBTM_Shortcode();
	}