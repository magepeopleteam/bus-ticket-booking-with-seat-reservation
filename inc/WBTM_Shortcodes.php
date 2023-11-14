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
			public function wbtm_bus_list($atts, $content = null) {
				$defaults = array(
					"cat" => "0",
					"show" => "20",
				);
				$params = shortcode_atts($defaults, $atts);
				$cat = $params['cat'];
				$show = $params['show'];
				ob_start();
				$paged = get_query_var("page") ? get_query_var("page") : 1;
				if ($cat > 0) {
					$args_search_qqq = array(
						'post_type' => array('wbtm_bus'),
						'paged' => $paged,
						'posts_per_page' => $show,
						'tax_query' => array(
							array(
								'taxonomy' => 'wbtm_bus_cat',
								'field' => 'term_id',
								'terms' => $cat
							)
						)
					);
				}
				else {
					$args_search_qqq = array(
						'post_type' => array('wbtm_bus'),
						'paged' => $paged,
						'posts_per_page' => $show
					);
				}
				$loop = new WP_Query($args_search_qqq);
				?>
				<div class="wbtm-bus-list-sec">
					<?php
						while ($loop->have_posts()) {
							$loop->the_post();
							WBTM_Functions::template_path('bus-list');
						}
						wp_reset_postdata();
					?>
				</div>
				<div class="row">
					<div class="col-md-12"><?php
							$pargs = array(
								"current" => $paged,
								"total" => $loop->max_num_pages
							);
							echo "<div class='pagination-sec'>" . paginate_links($pargs) . "</div>";
						?>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}
			public function wbtm_bus_search($attr, $content = null) {
				$defaults = array("cat" => "0", "style" => '', "search-page" => '');
				$params = shortcode_atts($defaults, $attr);
				$cat = $params['cat'];
				$form_style = $params['style'];
				$search_path = $params['search-page'];
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
		}
		new WBTM_Shortcode();
	}