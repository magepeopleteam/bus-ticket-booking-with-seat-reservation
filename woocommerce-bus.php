<?php
	/**
	 * Plugin Name: Bus Ticket Booking with Seat Reservation
	 * Plugin URI: http://mage-people.com
	 * Description: A Complete Bus Ticketing System for WordPress & WooCommerce
	 * Version: 5.2.6
	 * Author: MagePeople Team
	 * Author URI: http://www.mage-people.com/
	 * Text Domain: bus-ticket-booking-with-seat-reservation
	 * Domain Path: /languages/
	 */
// If this file is called directly, abort.
	if (!defined('WPINC')) {
		die;
	}
	if (!class_exists('Wbtm_Woocommerce_bus')) {
		class Wbtm_Woocommerce_bus {
			public function __construct() {
				include_once(ABSPATH . 'wp-admin/includes/plugin.php');
				$this->define();
				$this->load_plugin();
			}
			public function define() {
				if (!defined('WBTM_PLUGIN_DIR')) {
					define('WBTM_PLUGIN_DIR', dirname(__FILE__));
				}
				if (!defined('WBTM_PLUGIN_URL')) {
					define('WBTM_PLUGIN_URL', plugins_url() . '/' . plugin_basename(dirname(__FILE__)));
				}
				if (!defined('WBTM_PLUGIN_DATA')) {
					define('WBTM_PLUGIN_DATA', get_plugin_data( __FILE__ ));
				}
				require_once WBTM_PLUGIN_DIR . '/mp_global/MP_Global_File_Load.php';
			}
			
			private function load_plugin() {
				if (MP_Global_Function::check_woocommerce() == 1) {
					$this->appsero_init_tracker();
					$this->setBusPermission();
					add_filter('plugin_action_links', array($this, 'wbtm_plugin_action_link'), 10, 2);
					add_filter('plugin_row_meta', array($this, 'wbtm_plugin_row_meta'), 10, 2);
					self::on_activation_page_create();
					require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Dependencies.php';
					add_action('activated_plugin', array($this, 'activation_redirect'), 90);
				}
				else {
					require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Quick_Setup.php';
					add_action('activated_plugin', array($this, 'activation_redirect_setup'), 90, 1);
				}
			}
			public function activation_redirect($plugin) {
				$wbtm_quick_setup_done = get_option('wbtm_quick_setup_done');
				if ($plugin == plugin_basename(__FILE__) && $wbtm_quick_setup_done != 'yes') {
					exit(wp_redirect(admin_url('edit.php?post_type=wbtm_bus&page=wbtm_quick_setup')));
				}
			}
			public function activation_redirect_setup($plugin) {
				$wbtm_quick_setup_done = get_option('wbtm_quick_setup_done');
				if ($plugin == plugin_basename(__FILE__) && $wbtm_quick_setup_done != 'yes') {
					exit(wp_redirect(admin_url('admin.php?post_type=wbtm_bus&page=wbtm_quick_setup')));
				}
			}
			public function appsero_init_tracker() {
				if (!class_exists('Appsero\Client')) {
					require_once __DIR__ . '/lib/appsero/src/Client.php';
				}
				$client = new Appsero\Client('183b453a-7a2a-47f6-aa7e-10bf246d1d44', 'Bus Ticket Booking with Seat Reservation', __FILE__);
				$client->insights()->init();
			}
			function wbtm_plugin_row_meta($links_array, $plugin_file_name) {
				if (strpos($plugin_file_name, basename(__FILE__))) {
					if (!is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php')) {
						$wbtm_links = array(
							'docs' => '<a href="' . esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/") . '" target="_blank">' . __('Docs', 'bus-booking-manager') . '</a>',
							'support' => '<a href="' . esc_url("https://mage-people.com/my-account") . '" target="_blank">' . __('Support', 'bus-booking-manager') . '</a>',
							'get_pro' => '<a href="' . esc_url("https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/") . '" target="_blank" class="wbtm_plugin_pro_meta_link">' . __('Upgrade to PRO Version', 'bus-booking-manager') . '</a>'
						);
					}
					else {
						$wbtm_links = array(
							'docs' => '<a href="' . esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/") . '" target="_blank">' . __('Docs', 'bus-booking-manager') . '</a>',
							'support' => '<a href="' . esc_url("https://mage-people.com/my-account") . '" target="_blank">' . __('Support', 'bus-booking-manager') . '</a>',
						);
					}
					$links_array = array_merge($links_array, $wbtm_links);
				}
				return $links_array;
			}
			function wbtm_plugin_action_link($links_array, $plugin_file_name) {
				if (strpos($plugin_file_name, basename(__FILE__))) {
					array_unshift($links_array, '<a href="' . esc_url(admin_url()) . 'edit.php?post_type=wbtm_bus&page=wbtm-bus-manager-settings">' . __('Settings', 'bus-booking-manager') . '</a>');
				}
				return $links_array;
			}
			// Give bus all permission to admin
			public function setBusPermission() {
				if (is_admin()) {
					$role = get_role('administrator');
					(!$role->has_cap('publish_wbtm_buses')) ? $role->add_cap('publish_wbtm_buses') : null;
					(!$role->has_cap('edit_wbtm_buses')) ? $role->add_cap('edit_wbtm_buses') : null;
					(!$role->has_cap('edit_others_wbtm_buses')) ? $role->add_cap('edit_others_wbtm_buses') : null;
					(!$role->has_cap('read_private_wbtm_buses')) ? $role->add_cap('read_private_wbtm_buses') : null;
					(!$role->has_cap('edit_wbtm_bus')) ? $role->add_cap('edit_wbtm_bus') : null;
					(!$role->has_cap('delete_wbtm_bus')) ? $role->add_cap('delete_wbtm_bus') : null;
					(!$role->has_cap('read_wbtm_bus')) ? $role->add_cap('read_wbtm_bus') : null;
					(!$role->has_cap('wbtm_permission_page')) ? $role->add_cap('wbtm_permission_page') : null;
					(!$role->has_cap('extra_service_wbtm_bus')) ? $role->add_cap('extra_service_wbtm_bus') : null;
				}
			}
			public static function on_activation_page_create() {
				if (!MP_Global_Function::get_page_by_slug('bus-search-list')) {
					$bus_search_page = array(
						'post_type' => 'page',
						'post_name' => 'bus-search-list',
						'post_title' => 'Bus Search result',
						'post_content' => '[wbtm-bus-search]',
						'post_status' => 'publish',
					);
					wp_insert_post($bus_search_page);
					flush_rewrite_rules();
				}
				if (!MP_Global_Function::get_page_by_slug('bus-global-search')) {
					$bus_global_search_page = array(
						'post_type' => 'page',
						'post_name' => 'bus-global-search',
						'post_title' => 'Global search form',
						'post_content' => '[wbtm-bus-search-form]',
						'post_status' => 'publish',
					);
					wp_insert_post($bus_global_search_page);
					flush_rewrite_rules();
				}
			}
		}
	new Wbtm_Woocommerce_bus();
	}