<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Dependencies')) {
		class WBTM_Dependencies {
			public function __construct() {
				add_action('init', array($this, 'language_load'));
				$this->load_file();
				add_action('add_mp_global_enqueue', array($this, 'global_enqueue'), 90);
				add_action('add_mp_admin_enqueue', array($this, 'admin_enqueue'), 90);
				add_action('add_mp_frontend_enqueue', array($this, 'frontend_enqueue'), 90);
				add_filter('single_template', array($this, 'load_single_template'), 10);
				add_filter('template_include', array($this, 'load_template'));
			}
			public function language_load(): void {
				$plugin_dir = basename(dirname(__DIR__)) . "/languages/";
				load_plugin_textdomain('bus-ticket-booking-with-seat-reservation', false, $plugin_dir);
			}
			private function load_file(): void {
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Functions.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Translations.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Query.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Layout.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Admin.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Shortcodes.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Woocommerce.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/class-functions.php';
				//==================//
			}
			public function global_enqueue() {
				wp_enqueue_style('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.css', array(), time());
				wp_enqueue_script('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.js', array('jquery'), time(), true);
				do_action('add_wbtm_common_script');
			}
			public function admin_enqueue() {
				// custom
				wp_enqueue_script('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.js', array('jquery'), time(), true);
				wp_enqueue_style('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.css', array(), time());
				do_action('add_wbtm_admin_script');
			}
			public function frontend_enqueue() {
				wp_enqueue_style('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.css', array(), time());
				wp_enqueue_script('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.js', array('jquery'), time(), true);
				do_action('add_wbtm_frontend_script');
			}
			public function load_single_template($template) {
				global $post;
				if ($post->post_type == "wbtm_bus") {
					$template = WBTM_Functions::template_path('single_page/single-bus.php');
				}
				return $template;
			}
			public function load_template($template): string {
				if (get_query_var('bussearchlist')) {
					$template = WBTM_Functions::template_path('single_page/bus-search-list.php');
				}
				return $template;
			}
		}
		new WBTM_Dependencies();
	}
	