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
				add_action('admin_init', array($this, 'wbtm_upgrade'));
				$this->load_file();
				$this->appsero_init_tracker();
				add_action('wbtm_add_global_enqueue', array($this, 'global_enqueue'), 90);
				add_action('wbtm_add_admin_enqueue', array($this, 'admin_enqueue'), 90);
				add_action('wbtm_add_frontend_enqueue', array($this, 'frontend_enqueue'), 90);
				add_filter('single_template', array($this, 'load_single_template'), 10);
				add_filter('template_include', array($this, 'load_template'));
				add_filter('register_post_type_args', array($this, 'modify_bus_slug'), 5, 2);
				// Privacy protection for booking pages
				add_action('wp_head', array($this, 'add_privacy_meta_tags'));
				add_filter('robots_txt', array($this, 'add_robots_txt_rules'));
				// Add admin cleanup tool
				//add_action('admin_init', array($this, 'handle_privacy_cleanup'));
				//add_action('admin_notices', array($this, 'show_privacy_notice'));
			}
			public function modify_bus_slug($args, $post_type) {
				if ('wbtm_bus' === $post_type) {
					$slug = WBTM_Global_Function::get_settings('wbtm_general_settings', 'slug', 'bus');
					$args['rewrite']['slug'] = $slug;
				}
				return $args;
			}
			public function language_load(): void {
				$plugin_dir = basename(dirname(__DIR__)) . "/languages/";
				load_plugin_textdomain('bus-ticket-booking-with-seat-reservation', false, $plugin_dir);
			}
			public function wbtm_upgrade() {
				if (get_option('wbtm_conflict_update') != 'completed') {
					$style_settings = get_option('mp_style_settings');
					update_option('wbtm_style_settings', $style_settings);
					$slider_settings = get_option('mp_slider_settings');
					update_option('wbtm_slider_settings', $slider_settings);
					$license_settings = get_option('mp_basic_license_settings');
					update_option('wbtm_license_settings', $license_settings);
					update_option('wbtm_conflict_update', 'completed');
				}
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
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Single_Bus_Details.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Woocommerce.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/class-functions.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_My_Account_Dashboard.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Installer.php';
				//==================//
			}
			public function global_enqueue() {
				wp_enqueue_style('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.css', array(), WBTM_VERSION);
				wp_enqueue_style('mage-icon', WBTM_PLUGIN_URL . '/assets/mage-icon/css/mage-icon.css', array(), WBTM_VERSION);
				wp_enqueue_style('wbtm_bus_left_filter', WBTM_PLUGIN_URL . '/assets/global/wbtm_bus_left_filter.css', array(), WBTM_VERSION);
				wp_enqueue_script('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.js', array('jquery'), WBTM_VERSION, true);
				wp_enqueue_script('wbtm_bus_left_filter', WBTM_PLUGIN_URL . '/assets/global/wbtm_bus_left_filter.js', array('jquery'), WBTM_VERSION, true);
				do_action('wbtm_add_common_script');
			}
			public function admin_enqueue() {
				// custom
				wp_enqueue_script('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.js', array('jquery'), WBTM_VERSION, true);
				wp_enqueue_script('wtbm_bus_taxonomy', WBTM_PLUGIN_URL . '/assets/admin/wtbm_bus_taxonomy.js', array('jquery'), WBTM_VERSION, true);
				wp_enqueue_style('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.css', array(), WBTM_VERSION);
				wp_enqueue_style('wtbm_bus_taxonomy', WBTM_PLUGIN_URL . '/assets/admin/wtbm_bus_taxonomy.css', array(), WBTM_VERSION);
				$non_seat_icon_map = [];
				if (class_exists('WBTM_Seat_Configuration')) {
					foreach (WBTM_Seat_Configuration::get_toolbar_items() as $kw => $d) {
						$non_seat_icon_map[$kw] = $d['icon'];
					}
					$non_seat_icon_map['wc'] = 'fa-restroom';
				}
				$ticket_types_payload = [];
				$pro_seat_features_enabled = class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
				if (function_exists('get_current_screen')) {
					$screen = get_current_screen();
					if ($screen && $screen->post_type === 'wbtm_bus' && isset($_GET['post'])) {
						$bus_pid = absint($_GET['post']);
						if ($bus_pid > 0 && class_exists('WBTM_Functions')) {
							foreach (WBTM_Functions::get_ticket_types_for_seat_price_modal($bus_pid) as $tt) {
								$ticket_types_payload[] = [
									'id' => (string) $tt['id'],
									'label' => $tt['label'],
								];
							}
						}
					}
				}
				wp_localize_script( 'wbtm_admin', 'wbtm_admin_var', array(
					'url'               => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'wbtm_admin_nonce' ),
					'seat_row_col_error' => esc_html__( 'Number of rows & columns must be greater than 0', 'bus-ticket-booking-with-seat-reservation' ),
					'non_seat_items'    => $non_seat_icon_map,
					'pro_seat_features_enabled' => $pro_seat_features_enabled,
					'ticket_types'      => $ticket_types_payload,
					'seat_price_need_name' => esc_html__( 'Enter a seat label first (e.g. A1).', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_price_no_types' => esc_html__( 'Add a route fare for at least one passenger type under Routing & Pricing, or save a per-seat price first.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				do_action('wbtm_add_admin_script');
			}
			public function appsero_init_tracker() {
				//if (!class_exists('Appsero\Client')) {
					//require_once WBTM_PLUGIN_DIR . '/lib/appsero/src/Client.php';
					// require_once __DIR__ . '/lib/appsero/src/Client.php';
				//}
				//$client = new Appsero\Client('183b453a-7a2a-47f6-aa7e-10bf246d1d44', 'Bus Ticket Booking with Seat Reservation', __FILE__);
				//$client->insights()->init();
			}
			public function frontend_enqueue() {
				wp_enqueue_style('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.css', array(), WBTM_VERSION);
				wp_enqueue_style('wtbm_search', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_search.css', array(), WBTM_VERSION);
				wp_enqueue_style('wtbm_single_bus_details', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_single_bus_details.css', array(), WBTM_VERSION);
				wp_enqueue_script('wtbm_single_bus_details', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_single_bus_details.js', array('jquery'), WBTM_VERSION, true);
				wp_enqueue_script('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.js', array('jquery'), WBTM_VERSION, true);
				wp_localize_script('jquery', 'wbtm_wc_vars', array(
					'checkout_url' => wc_get_checkout_url()
				));
				wp_localize_script( 'wbtm_global', 'wbtm_strings', array(
					'searching'             => esc_html__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ),
					'loading'               => esc_html__( 'Loading...', 'bus-ticket-booking-with-seat-reservation' ),
					'place_departure_first' => esc_html__( 'Please place departure bus first.', 'bus-ticket-booking-with-seat-reservation' ),
					'fill_required_fields'  => esc_html__( 'Please fill all required fields', 'bus-ticket-booking-with-seat-reservation' ),
					'failed_add_ticket'     => esc_html__( 'Failed to add ticket', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				do_action('wbtm_add_frontend_script');
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
			/**
			 * Add privacy meta tags to prevent search engine indexing of booking pages
			 */
			public function add_privacy_meta_tags() {
				global $post;
				// Check if this is a bus booking page
				if (is_singular('wbtm_bus_booking')) {
					echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
					echo '<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
					echo '<meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
					echo '<meta name="duckduckbot" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
				}
			}
			/**
			 * Add robots.txt rules to prevent crawling of booking pages
			 */
			public function add_robots_txt_rules($output) {
				$output .= "\n# Prevent indexing of bus booking pages\n";
				$output .= "Disallow: /wbtm_bus_booking/\n";
				$output .= "Disallow: /*/wbtm_bus_booking/\n";
				$output .= "Disallow: /bus-booking-*\n";
				return $output;
			}
			/**
			 * Handle privacy cleanup from admin
			 */
//			public function handle_privacy_cleanup() {
//
//                $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
//
//				if (isset($_GET['wbtm_cleanup_urls']) && $nonce && wp_verify_nonce( $nonce, 'wbtm_cleanup_urls')) {
//					if (current_user_can('manage_options')) {
//						WBTM_Woocommerce::cleanup_existing_booking_urls();
//						wp_safe_redirect(admin_url('edit.php?post_type=wbtm_bus_booking&wbtm_cleanup_complete=1'));
//						exit;
//					}
//				}
//			}
			/**
			 * Show privacy notice in admin
			 */
//			public function show_privacy_notice() {
//				$screen = get_current_screen();
//				if ($screen && $screen->id === 'edit-wbtm_bus_booking') {
//					if (isset($_GET['wbtm_cleanup_complete'])) {
//						echo '<div class="notice notice-success is-dismissible"><p><strong>Privacy Cleanup Complete:</strong> All existing booking URLs have been updated to remove customer names.</p></div>';
//					} else {
//						// Check if there are any booking posts with customer names in URLs
//						$bookings = get_posts(array(
//							'post_type' => 'wbtm_bus_booking',
//							'numberposts' => 5,
//							'post_status' => 'publish'
//						));
//						$has_customer_names = false;
//						foreach ($bookings as $booking) {
//							if (preg_match('/^[a-z]+-[a-z]+/', $booking->post_name)) {
//								$has_customer_names = true;
//								break;
//							}
//						}
//						if ($has_customer_names) {
//							$cleanup_url = wp_nonce_url(admin_url('edit.php?post_type=wbtm_bus_booking&wbtm_cleanup_urls=1'), 'wbtm_cleanup_urls');
//							echo '<div class="notice notice-warning is-dismissible"><p><strong>Privacy Issue Detected:</strong> Some booking pages contain customer names in URLs. <a href="' . esc_url($cleanup_url) . '" class="button button-primary">Clean Up URLs Now</a></p></div>';
//						}
//					}
//				}
//			}
		}
		new WBTM_Dependencies();
	}
