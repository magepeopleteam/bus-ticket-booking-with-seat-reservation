<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MP_Global_Style' ) ) {
		class MP_Global_Style {
			public function __construct() {
				add_action( 'wp_head', array( $this, 'add_global_style' ), 100 );
				add_action( 'admin_head', array( $this, 'add_global_style' ), 100 );
			}
			
			public function add_global_style() {
				$default_color   = MP_Global_Function::get_style_settings( 'default_text_color', '#303030' );
				$theme_color     = MP_Global_Function::get_style_settings( 'theme_color', '#2F9DF4' );
				$alternate_color = MP_Global_Function::get_style_settings( 'theme_alternate_color', '#fff' );
				$warning_color   = MP_Global_Function::get_style_settings( 'warning_color', '#E67C30' );
				$default_fs      = MP_Global_Function::get_style_settings( 'default_font_size', '14' ) . 'px';
				$fs_h1           = MP_Global_Function::get_style_settings( 'font_size_h1', '35' ) . 'px';
				$fs_h2           = MP_Global_Function::get_style_settings( 'font_size_h2', '30' ) . 'px';
				$fs_h3           = MP_Global_Function::get_style_settings( 'font_size_h3', '25' ) . 'px';
				$fs_h4           = MP_Global_Function::get_style_settings( 'font_size_h4', '22' ) . 'px';
				$fs_h5           = MP_Global_Function::get_style_settings( 'font_size_h5', '18' ) . 'px';
				$fs_h6           = MP_Global_Function::get_style_settings( 'font_size_h6', '16' ) . 'px';
				$fs_label        = MP_Global_Function::get_style_settings( 'font_size_label', '16' ) . 'px';
				$button_fs       = MP_Global_Function::get_style_settings( 'button_font_size', '16' ) . 'px';
				$button_color    = MP_Global_Function::get_style_settings( 'button_color', $alternate_color );
				$button_bg       = MP_Global_Function::get_style_settings( 'button_bg', '#ea8125' );
				$section_bg      = MP_Global_Function::get_style_settings( 'section_bg', '#FAFCFE' );
				?>
				<style>
					:root {
						--dContainer_Width: 1320px;
						--sidebarLeft: 280px;
						--sidebarRight: 300px;
						--mainSection: calc(100% - 300px);
						--dMPL: 40px;
						--dMP: 20px;
						--dMP_negetive: -20px;
						--dMP_xs: 10px;
						--dMP_xs_negative: -10px;
						--dBRL: 10px;
						--dBR: 5px;
						--dShadow: 0 0 2px #665F5F7A;
					}
					/*****Font size********/
					:root {
						--fs: <?php echo esc_attr($default_fs); ?>;
						--fw: normal;
						--fs_small: 13px;
						--fs_label: <?php echo esc_attr($fs_label); ?>;
						--fs_h6: <?php echo esc_attr($fs_h6); ?>;
						--fs_h5: <?php echo esc_attr($fs_h5); ?>;
						--fs_h4: <?php echo esc_attr($fs_h4); ?>;
						--fs_h3: <?php echo esc_attr($fs_h3); ?>;
						--fs_h2: <?php echo esc_attr($fs_h2); ?>;
						--fs_h1: <?php echo esc_attr($fs_h1); ?>;
						--fw-thin: 300; /*font weight medium*/
						--fw-normal: 500; /*font weight medium*/
						--fw-medium: 600; /*font weight medium*/
						--fw-bold: bold; /*font weight bold*/
					}
					/*****Button********/
					:root {
						--button_bg: <?php echo esc_attr($button_bg); ?>;
						--color_button: <?php echo esc_attr($button_color); ?>;
						--button_fs: <?php echo esc_attr($button_fs); ?>;
						--button_height: 40px;
						--button_height_xs: 30px;
						--button_width: 120px;
						--button_shadows: 0 8px 12px rgb(51 65 80 / 6%), 0 14px 44px rgb(51 65 80 / 11%);
					}
					/*******Color***********/
					:root {
						--d_color: <?php echo esc_attr($default_color); ?>;
						--color_border: #DDD;
						--color_active: #0E6BB7;
						--color_section: <?php echo esc_attr($section_bg); ?>;
						--color_theme: <?php echo esc_attr($theme_color); ?>;
						--color_theme_ee: <?php echo esc_attr($theme_color).'ee'; ?>;
						--color_theme_cc: <?php echo esc_attr($theme_color).'cc'; ?>;
						--color_theme_aa: <?php echo esc_attr($theme_color).'aa'; ?>;
						--color_theme_88: <?php echo esc_attr($theme_color).'88'; ?>;
						--color_theme_77: <?php echo esc_attr($theme_color).'77'; ?>;
						--color_theme_alter: <?php echo esc_attr($alternate_color); ?>;
						--color_warning: <?php echo esc_attr($warning_color); ?>;
						--color_black: #000;
						--color_success: #006607;
						--color_danger: #C00;
						--color_required: #C00;
						--color_white: #FFFFFF;
						--color_light: #F2F2F2;
						--color_light_1: #BBB;
						--color_light_2: #EAECEE;
						--color_info: #666;
						--color_yellow: #FEBB02;
						--color_blue: #815DF2;
						--color_navy_blue: #007CBA;
						--color_1: #0C5460;
						--color_2: #0CB32612;
						--color_3: #FAFCFE;
						--color_4: #6148BA;
						--color_5: #BCB;
					}
					@media only screen and (max-width: 1100px) {
						:root {
							--fs: 14px;
							--fs_small: 12px;
							--fs_label: 15px;
							--fs_h4: 20px;
							--fs_h3: 22px;
							--fs_h2: 25px;
							--fs_h1: 30px;
							--dMPL: 32px;
							--dMP: 16px;
							--dMP_negetive: -16px;
							--dMP_xs: 8px;
							--dMP_xs_negative: -8px;
						}
					}
					@media only screen and (max-width: 700px) {
						:root {
							--fs: 12px;
							--fs_small: 10px;
							--fs_label: 13px;
							--fs_h6: 15px;
							--fs_h5: 16px;
							--fs_h4: 18px;
							--fs_h3: 20px;
							--fs_h2: 22px;
							--fs_h1: 24px;
							--dMP: 10px;
							--dMP_xs: 5px;
							--dMP_xs_negative: -5px;
							--button_fs: 14px;
						}
					}
				</style>
				<?php
			}
		}
		new MP_Global_Style();
	}
	add_action( 'wp_head', 'wbtm_add_custom_css_codes' );
	function wbtm_add_custom_css_codes() {
		ob_start();
		?>
		<style>
			ul.mage_list_inline li.mage_active {
				background-color: <?php echo wbtm_get_style('wbtm_search_next_date_active_bg_color','#777'); ?>;
			}
			ul.mage_list_inline li.mage_active a {
				color: <?php echo wbtm_get_style('wbtm_search_next_date_active_text_color','#fff'); ?>;
			}
			ul.mage_list_inline li {
				background-color: <?php echo wbtm_get_style('wbtm_search_next_date_bg_color','#f2f2f2'); ?>;
			}
			ul.mage_list_inline li a {
				color: <?php echo wbtm_get_style('wbtm_search_next_date_text_color','#0a4b78'); ?>;
			}
			[class*='bgLight'] {
				background-color: <?php echo wbtm_get_style('wbtm_search_route_list_title_bg_color','#777'); ?>;
			}
			.bgLight_mar_t_textCenter_radius_pad_xs_justifyAround.mage_title h4 {
				color: <?php echo wbtm_get_style('wbtm_search_route_list_title_text_color','#000'); ?>;
			}
			.mage_bus_list_title, .mage_bus_list_title {
				background-color: <?php echo wbtm_get_style('wbtm_search_list_table_bg_color','#0a4b78'); ?>;
				color: <?php echo wbtm_get_style('wbtm_search_list_table_text_color','#fff'); ?>;
			}
			button.mage_button_xs.mage_bus_details_toggle {
				background: <?php echo wbtm_get_style('wbtm_view_seat_btn_bg_color','#0a4b78'); ?>;
				color: <?php echo wbtm_get_style('wbtm_view_seat_btn_text_color','#fff'); ?>;
				border-color: <?php echo wbtm_get_style('wbtm_view_seat_btn_bg_color','#0a4b78'); ?>;
			}
			form.mage_form button[class*='mage_button'] {
				background: <?php echo wbtm_get_style('wbtm_book_now_btn_bg_color','#0a4b78'); ?>;
				color: <?php echo wbtm_get_style('wbtm_book_now_btn_text_color','#fff'); ?>;
				border: 1px solid<?php echo wbtm_get_style('wbtm_book_now_btn_bg_color','#0a4b78'); ?>;
			}
			form.mage_form button[class*='mage_button_search'] {
				color: <?php echo wbtm_get_style('wbtm_search_btn_text_color','#fff'); ?>;
				background-color: <?php echo wbtm_get_style('wbtm_search_btn_bg_color','#0a4b78'); ?>;
				border: 1px solid<?php echo wbtm_get_style('wbtm_search_btn_bg_color','#0a4b78'); ?>;
			}
			.wbtm-details-page-list-total-avl-seat, .flexEqual.mage_bus_selected_list, .mage_customer_info_area .mage_title {
				background: <?php echo wbtm_get_style('wbtm_search_list_bus_details_title_bg_color','#ddd'); ?>;
				color: <?php echo wbtm_get_style('wbtm_search_list_bus_details_title_text_color','#000'); ?> !important;
			}
			form.mage_form .mage_form_list .mage_input_select_list,
			form.mage_form .mage_form_list #wbtm_show_msg {
				color: <?php echo wbtm_get_style('wbtm_form_route_item_color','#333'); ?>;;
			}
		</style>
		<?php
		echo ob_get_clean();
	}
	function wbtm_get_style($name, $default) {
		global $wbtmmain;
		return $wbtmmain->bus_get_option($name, '', $default);
	}