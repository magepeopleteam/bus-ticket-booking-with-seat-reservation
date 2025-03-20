<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists('WBTM_Global_Style') ) {
		class WBTM_Global_Style {
			public function __construct() {
				add_action( 'wp_head', array( $this, 'add_global_style' ), 100 );
				add_action( 'admin_head', array( $this, 'add_global_style' ), 100 );
			}
			public function add_global_style() {
				$default_color   = WBTM_Global_Function::get_style_settings( 'default_text_color', '#303030' );
				$theme_color     = WBTM_Global_Function::get_style_settings( 'theme_color', '#F12971' );
				$alternate_color = WBTM_Global_Function::get_style_settings( 'theme_alternate_color', '#fff' );
				$warning_color   = WBTM_Global_Function::get_style_settings( 'warning_color', '#E67C30' );
				$default_fs      = WBTM_Global_Function::get_style_settings( 'default_font_size', '14' ) . 'px';
				$fs_h1           = WBTM_Global_Function::get_style_settings( 'font_size_h1', '35' ) . 'px';
				$fs_h2           = WBTM_Global_Function::get_style_settings( 'font_size_h2', '30' ) . 'px';
				$fs_h3           = WBTM_Global_Function::get_style_settings( 'font_size_h3', '25' ) . 'px';
				$fs_h4           = WBTM_Global_Function::get_style_settings( 'font_size_h4', '22' ) . 'px';
				$fs_h5           = WBTM_Global_Function::get_style_settings( 'font_size_h5', '18' ) . 'px';
				$fs_h6           = WBTM_Global_Function::get_style_settings( 'font_size_h6', '16' ) . 'px';
				$fs_label        = WBTM_Global_Function::get_style_settings( 'font_size_label', '16' ) . 'px';
				$button_fs       = WBTM_Global_Function::get_style_settings( 'button_font_size', '16' ) . 'px';
				$button_color    = WBTM_Global_Function::get_style_settings( 'button_color', $alternate_color );
				$button_bg       = WBTM_Global_Function::get_style_settings( 'button_bg', '#ea8125' );
				$section_bg      = WBTM_Global_Function::get_style_settings( 'section_bg', '#FAFCFE' );
				?>
				<style>
					:root {
						--wbtm_dcontainer_width: 1320px;
						--wbtm_sidebarleft: 280px;
						--wbtm_sidebarright: 300px;
						--wbtm_mainsection: calc(100% - 300px);
						--wbtm_dmpl: 40px;
						--wbtm_dmp: 20px;
						--wbtm_dmp_negetive: -20px;
						--wbtm_dmp_xs: 10px;
						--wbtm_dmp_xs_negative: -10px;
						--wbtm_dbrl: 10px;
						--wbtm_dbr: 5px;
						--wbtm_dshadow: 0 0 2px #665F5F7A;
					}
					/*****Font size********/
					:root {
						--wbtm_fs: <?php echo esc_attr($default_fs); ?>;
						--wbtm_fw: normal;
						--wbtm_fs_small: 10px;
						--wbtm_fs_label: <?php echo esc_attr($fs_label); ?>;
						--wbtm_fs_h6: <?php echo esc_attr($fs_h6); ?>;
						--wbtm_fs_h5: <?php echo esc_attr($fs_h5); ?>;
						--wbtm_fs_h4: <?php echo esc_attr($fs_h4); ?>;
						--wbtm_fs_h3: <?php echo esc_attr($fs_h3); ?>;
						--wbtm_fs_h2: <?php echo esc_attr($fs_h2); ?>;
						--wbtm_fs_h1: <?php echo esc_attr($fs_h1); ?>;
						--wbtm_fw-thin: 300; /*font weight medium*/
						--wbtm_fw-normal: 500; /*font weight medium*/
						--wbtm_fw-medium: 600; /*font weight medium*/
						--wbtm_fw-bold: bold; /*font weight bold*/
					}
					/*****Button********/
					:root {
						--wbtm_button_bg: <?php echo esc_attr($button_bg); ?>;
						--wbtm_color_button: <?php echo esc_attr($button_color); ?>;
						--wbtm_button_fs: <?php echo esc_attr($button_fs); ?>;
						--wbtm_button_height: 40px;
						--wbtm_button_height_xs: 30px;
						--wbtm_button_width: 120px;
						--wbtm_button_shadows: 0 8px 12px rgb(51 65 80 / 6%), 0 14px 44px rgb(51 65 80 / 11%);
					}
					/*******Color***********/
					:root {
						--wbtm_d_color: <?php echo esc_attr($default_color); ?>;
						--wbtm_color_border: #DDD;
						--wbtm_color_active: #0E6BB7;
						--wbtm_color_section: <?php echo esc_attr($section_bg); ?>;
						--wbtm_color_theme: <?php echo esc_attr($theme_color); ?>;
						--wbtm_color_theme_ee: <?php echo esc_attr($theme_color).'ee'; ?>;
						--wbtm_color_theme_cc: <?php echo esc_attr($theme_color).'cc'; ?>;
						--wbtm_color_theme_aa: <?php echo esc_attr($theme_color).'aa'; ?>;
						--wbtm_color_theme_88: <?php echo esc_attr($theme_color).'88'; ?>;
						--wbtm_color_theme_77: <?php echo esc_attr($theme_color).'77'; ?>;
						--wbtm_color_theme_alter: <?php echo esc_attr($alternate_color); ?>;
						--wbtm_color_warning: <?php echo esc_attr($warning_color); ?>;
						--wbtm_color_black: #000;
						--wbtm_color_success: #00A656;
						--wbtm_color_danger: #C00;
						--wbtm_color_required: #C00;
						--wbtm_color_white: #FFFFFF;
						--wbtm_color_light: #F2F2F2;
						--wbtm_color_light_1: #BBB;
						--wbtm_color_light_2: #EAECEE;
						--wbtm_color_light_3: #878787;
						--wbtm_color_light_4: #f9f9f9;
						--wbtm_color_info: #666;
						--wbtm_color_yellow: #FEBB02;
						--wbtm_color_blue: #815DF2;
						--wbtm_color_navy_blue: #007CBA;
						--wbtm_color_1: #0C5460;
						--wbtm_color_2: #caf0ffcc;
						--wbtm_color_3: #FAFCFE;
						--wbtm_color_4: #6148BA;
						--wbtm_color_5: #BCB;
					}
					@media only screen and (max-width: 1100px) {
						:root {
							--wbtm_fs: 14px;
							--wbtm_fs_small: 12px;
							--wbtm_fs_label: 15px;
							--wbtm_fs_h4: 20px;
							--wbtm_fs_h3: 22px;
							--wbtm_fs_h2: 25px;
							--wbtm_fs_h1: 30px;
							--wbtm_dmpl: 32px;
							--wbtm_dmp: 16px;
							--wbtm_dmp_negetive: -16px;
							--wbtm_dmp_xs: 8px;
							--wbtm_dmp_xs_negative: -8px;
						}
					}
					@media only screen and (max-width: 700px) {
						:root {
							--wbtm_fs: 12px;
							--wbtm_fs_small: 10px;
							--wbtm_fs_label: 13px;
							--wbtm_fs_h6: 15px;
							--wbtm_fs_h5: 16px;
							--wbtm_fs_h4: 18px;
							--wbtm_fs_h3: 20px;
							--wbtm_fs_h2: 22px;
							--wbtm_fs_h1: 24px;
							--wbtm_dmp: 10px;
							--wbtm_dmp_xs: 5px;
							--wbtm_dmp_xs_negative: -5px;
							--wbtm_button_fs: 14px;
						}
					}
				</style>
				<?php
			}
		}
		new WBTM_Global_Style();
	}