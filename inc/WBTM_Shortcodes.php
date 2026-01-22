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
				add_shortcode('wbtm-bus-details', array($this, 'wbtm_bus_details'));
			}
			public function wbtm_bus_list($attribute, $content = null){
				$defaults = $this->default_attribute();
				$params = shortcode_atts($defaults, $attribute);
				$cat = $params['cat'];
				$show = (int)$params['show'];
				$start = $params['start'];
				$end = $params['end'];
				$column = $params['column'];
				$style = $params['style'];
				$pagination = "";
				
				// For pagination, we need to get total count and limit results
				if ($pagination === 'yes' && $show > 0) {
					$total_buses = WBTM_Query::get_bus_count($start, $end, $cat);
					$bus_ids = WBTM_Query::get_bus_id($start, $end, $cat, -1); // Get all for now, we'll handle display in template
				} else {
					$bus_ids = WBTM_Query::get_bus_id($start, $end, $cat);
					$total_buses = count($bus_ids);
				}
				ob_start();
				if (sizeof($bus_ids) > 0) {
					$count = 0;
					?>
					<div class="wbtm_style wbtm_placeholderLoader wbtm_pagination_main_area">
						<div class="mpContainer flexWrap">
							<?php foreach ($bus_ids as $bus_id) { ?>
								<?php
								$thumbnail = WBTM_Global_Function::get_image_url($bus_id);
								$url = get_the_permalink($bus_id);
								$category = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category');
								$route = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_direction', []);
								$d_class = $show > $count ? '' : 'dNone';
								$grid_class = 'grid_' . $column;
								$count++;
								?>
								<div class="placeholder_area wbtm_pagination_item _dShadow_9 <?php echo esc_attr($grid_class . ' ' . $d_class); ?>">
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
										<strong><?php echo esc_html( WBTM_Translations::text_passenger_capacity() ); ?> :</strong>
										<?php echo esc_html( WBTM_Global_Function::get_post_info($bus_id, 'wbtm_get_total_seat', 0) ); ?>
									</h6>
									<div class="divider"></div>
									<div class="mp_wp_editor">
										<?php //echo get_the_content('', '', $bus_id); ?>
									</div>
								</div>
							<?php } ?>
						</div>
						<?php 
							if ($pagination === 'yes') {
								do_action('wbtm_pagination_section', $params, $total_buses);
							}
						?>
					</div>
					<?php
				}
				return ob_get_clean();
			}
			public function wbtm_bus_search($attr, $content = null) {
				$defaults = array("cat" => "0", "style" => '', "search-page" => '', 'left_filter' => 'off','left_filter_input' => 'off', 'left_filter_type' => 'on' ,'left_filter_operator' => 'on', 'left_filter_boarding' => 'on');
				$params = shortcode_atts($defaults, $attr);

				$cat = $params['cat'];
				$form_style = $params['style'];
				$search_path = $params['search-page'];
				$style = $params['style'];
//				$left_filter            = $params['left_filter_input'];
				$left_filter            = $params['left_filter'];
				$left_filter_type       = $params['left_filter_type'];
				$left_filter_operator   = $params['left_filter_operator'];
				$left_filter_boarding   = $params['left_filter_boarding'];
				ob_start();
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook
				do_action('woocommerce_before_single_product');
				?>
				<div class="wbtm_style wbtm_container">
					<?php require WBTM_Functions::template_path('layout/search_form.php'); ?>
				</div>
				<?php
				do_action('wbtm_after_search_result_section', $params);
				return ob_get_clean();
			}
			public function wbtm_bus_details($attribute, $content = null) {
				$params = shortcode_atts(array(
					'id' => '',
					'name' => '',
					'date' => '',
					'start_route' => '',
					'end_route' => '',
					'style' => 'flix',
					'btn_show' => 'show'
				), $attribute);

				$bus_id = $params['id'];
				if (empty($bus_id) && !empty($params['name'])) {
					$bus = get_page_by_title($params['name'], OBJECT, 'wbtm_bus');
					if ($bus) {
						$bus_id = $bus->ID;
					}
				}

				if (empty($bus_id)) {
					// Apply to current post if it's a bus
					if (get_post_type() == 'wbtm_bus') {
						$bus_id = get_the_id();
					} else {
						return "";
					}
				}

				$date = $params['date'] ?: current_time('Y-m-d');
				$start_route = $params['start_route'];
				$end_route = $params['end_route'];

				if (empty($start_route) || empty($end_route)) {
					$route = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_direction', []);
					if (!empty($route)) {
						$start_route = $start_route ?: reset($route);
						$end_route = $end_route ?: end($route);
					}
				}

				$search_info = [
					'bus_start_route' => $start_route,
					'bus_end_route' => $end_route,
					'j_date' => $date,
					'r_date' => '',
				];

				$left_filter_show = [
					'left_filter_input' => 'off',
					'left_filter_type' => 'on',
					'left_filter_operator' => 'on',
					'left_filter_boarding' => 'on',
				];

				ob_start();
				?>
				<div id="wbtm_area" class="wbtm_style wbtm_container wbtm_bus_details_shortcode">
                    <input type="hidden" name="bus_start_route" value="<?php echo esc_attr($start_route); ?>" />
                    <input type="hidden" name="bus_end_route" value="<?php echo esc_attr($end_route); ?>" />
                    <input type="hidden" name="j_date" value="<?php echo esc_attr($date); ?>" />
                    <input type="hidden" name="r_date" value="" />
                    <input type="hidden" name="wbtm_list_style" value="<?php echo esc_attr($params['style']); ?>" />
                    <input type="hidden" name="wbtm_list_btn_show" value="<?php echo esc_attr($params['btn_show']); ?>" />
                    <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($bus_id); ?>" />
                    <input type="hidden" name="wbtm_left_filter_show" value="off" />
                    <input type="hidden" name="wbtm_left_filter_type" value="on" />
                    <input type="hidden" name="wbtm_left_filter_operator" value="on" />
                    <input type="hidden" name="wbtm_left_filter_boarding" value="on" />

                    <button type="submit" class="get_wbtm_bus_list" style="display: none;"></button>

                    <div class="wbtm_search_result">
                        <?php 
                        WBTM_Layout::wbtm_bus_list($bus_id, $start_route, $end_route, $date, '', $params['style'], $params['btn_show'], $search_info, $left_filter_show, 'wbtm_bus_start_end_' . $bus_id); 
                        ?>
                    </div>
				</div>
                <style>
                    .wbtm_bus_details_shortcode .wbtm_bus_left_filter_holder { display: none !important; }
                    .wbtm_bus_details_shortcode .wbtm_bus_list_area { width: 100% !important; }
                    .wbtm_bus_details_shortcode .wbtm-date-suggetion .wbtm_next_date.mActive {
                        border: 2px solid var(--wbtm_color_theme) !important;
                        background-color: #fff !important;
                    }
                    /* Specific overrides for the flix-style results in shortcode */
                    .wbtm_bus_details_shortcode .wbtm-bus-flix-style_bus {
                        padding: 20px !important;
                        gap: 15px;
                    }
                    .wbtm_bus_details_shortcode .wbtm-bus-route {
                        width: 35% !important;
                        padding: 0 15px !important;
                        border-right: none !important;
                        align-items: flex-start !important;
                        text-align: left !important;
                    }
                    .wbtm_bus_details_shortcode .wbtm-bus-route h6 {
                        margin: 0 0 10px 0 !important;
                        display: flex !important;
                        flex-direction: row !important;
                        flex-wrap: nowrap !important;
                        align-items: center !important;
                        gap: 8px !important;
                        text-align: left !important;
                        white-space: nowrap !important;
                    }
                    .wbtm_bus_details_shortcode .wbtm-bus-route h6 .route,
                    .wbtm_bus_details_shortcode .wbtm-bus-route h6 .time {
                        display: inline !important;
                    }
                    .wbtm_bus_details_shortcode .wbtm-bus-route h6 i {
                        width: 16px;
                        text-align: center;
                        padding-right: 0 !important;
                        flex-shrink: 0 !important;
                    }
                    .wbtm_bus_details_shortcode .wbtm-bus-route h6 .time {
                        margin-left: 4px;
                        font-size: 0.9em;
                        color: #666;
                    }
                    .wbtm_bus_details_shortcode .wbtm_bus_feature_badge {
                        white-space: nowrap;
                    }
                </style>
				<?php
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
