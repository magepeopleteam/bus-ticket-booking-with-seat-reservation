<?php
	/**
	 * Plugin Name: Bus Ticket Booking with Seat Reservation
	 * Plugin URI: http://mage-people.com
	 * Description: A Complete Bus Ticketing System for WordPress & WooCommerce
	 * Version: 5.4.7
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
				require_once WBTM_PLUGIN_DIR . '/mp_global/WBTM_Global_File_Load.php';
			}
			private function load_plugin() {
				if (WBTM_Global_Function::check_woocommerce() == 1) {
					$this->setBusPermission();
					add_filter('plugin_action_links', array($this, 'wbtm_plugin_action_link'), 10, 2);
					add_filter('plugin_row_meta', array($this, 'wbtm_plugin_row_meta'), 10, 2);
					self::on_activation_page_create();
					require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Dependencies.php';
					add_action('activated_plugin', array($this, 'activation_redirect'), 90);
					add_action( 'admin_init', [ $this, 'flush_rules_wbtm_post_list_page' ] );
				}else{
					require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Quick_Setup.php';
					add_action('activated_plugin', array($this, 'activation_redirect_setup'), 90, 1);
					add_action( 'admin_init', [ $this, 'flush_rules_wbtm_post_list_page' ] );
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
			function flush_rules_wbtm_post_list_page() {				
				if ( isset( $_GET['post_type'] ) && sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) == 'wbtm_bus' ) {
					flush_rewrite_rules(); 
				}
			}			
			function wbtm_plugin_row_meta($links_array, $plugin_file_name) {
				if (strpos($plugin_file_name, basename(__FILE__))) {
					if (!is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php')) {
						$wbtm_links = array(
							'docs' => '<a href="' . esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/") . '" target="_blank">' . __('Docs', 'bus-booking-manager') . '</a>',
							'support' => '<a href="' . esc_url("https://mage-people.com/my-account") . '" target="_blank">' . __('Support', 'bus-booking-manager') . '</a>',
							'get_pro' => '<a href="' . esc_url("https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/") . '" target="_blank" class="wbtm_plugin_pro_meta_link">' . __('Upgrade to PRO Version', 'bus-booking-manager') . '</a>'
						);
					} else {
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
					array_unshift($links_array, '<a href="' . esc_url(admin_url()) . 'edit.php?post_type=wbtm_bus&page=wbtm_settings_page">' . __('Settings', 'bus-booking-manager') . '</a>');
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
				if (!WBTM_Global_Function::get_page_by_slug('bus-global-search')) {
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
				if (!WBTM_Global_Function::get_page_by_slug('bus-global-search-flix')) {
					$bus_global_search_page = array(
						'post_type' => 'page',
						'post_name' => 'bus-global-search-flix',
						'post_title' => 'Global search form flix style',
						'post_content' => '[wbtm-bus-search-form style="flix"]',
						'post_status' => 'publish',
					);
					wp_insert_post($bus_global_search_page);
					flush_rewrite_rules();
				}
				if (!WBTM_Global_Function::get_page_by_slug('search-result')) {
					$search_result= array(
						'post_type' => 'page',
						'post_name' => 'search-result',
						'post_title' => 'Search Result',
						'post_content' => '[wbtm-bus-search-form]',
						'post_status' => 'publish',
					);
					wp_insert_post($search_result);
					flush_rewrite_rules();
				}
			}
		}
		new Wbtm_Woocommerce_bus();
	}
	add_action( 'rest_api_init', 'wbtm_bookings_cunstom_fields_to_rest_init' );
	if ( ! function_exists( 'wbtm_bookings_cunstom_fields_to_rest_init' ) ) {
		function wbtm_bookings_cunstom_fields_to_rest_init() {
			register_rest_field( 'wbtm_bus_booking', 'passenger_informations', array(
				'get_callback' => 'wbtm_get_passenger_custom_meta_for_api',
				'schema'       => null,
			) );
			register_rest_field( 'wbtm_bus', 'bus_informations', array(
				'get_callback' => 'wbtm_get_passenger_custom_meta_for_api',
				'schema'       => null,
			) );
		}
	}
	if ( ! function_exists( 'wbtm_get_passenger_custom_meta_for_api' ) ) {
		function wbtm_get_passenger_custom_meta_for_api( $object ) {
			$post_id   = $object['id'];
			$post_meta = get_post_meta( $post_id );
			return $post_meta;
		}
	}
