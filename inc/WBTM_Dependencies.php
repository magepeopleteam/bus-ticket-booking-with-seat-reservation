<?php
	/*
* @Author 		MagePeople Team
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
				add_action('wbtm_add_global_enqueue', array($this, 'global_enqueue'), 90);
				add_action('wbtm_add_admin_enqueue', array($this, 'admin_enqueue'), 90);
				add_action('wbtm_add_frontend_enqueue', array($this, 'frontend_enqueue'), 90);
				add_filter('single_template', array($this, 'load_single_template'), 10);
				add_filter('template_include', array($this, 'load_template'));
				add_filter('register_post_type_args', array($this, 'modify_bus_slug'), 5, 2);
				// Privacy protection for booking pages
				add_action('wp_head', array($this, 'add_privacy_meta_tags'));
				add_filter('robots_txt', array($this, 'add_robots_txt_rules'));
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
				// Booking-record helpers (seat locks, availability checks, cart-style POST
				// parsing, post insertion) with no real WooCommerce dependency — shared by
				// the WooCommerce cart flow (via WBTM_Woocommerce's delegating wrappers below)
				// and the WC-independent Standalone/Custom Payment flow (Pro plugin) alike.
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Cart_Helper.php';
				// Temporary seat holds (transient-based) + hold countdown — consulted by
				// WBTM_Query availability reads and enforced at both booking entry points.
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Seat_Hold.php';
				// The "Book Now" AJAX entry point — always loaded (see its own docblock);
				// dispatches to either the WooCommerce cart or the Standalone flow.
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Booking_Controller.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Layout.php';
				// Payment mode / gateway availability checks (WooCommerce-optional).
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Payment_Status_Checker.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Payment_Provider_Interface.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Admin.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Shortcodes.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Single_Bus_Details.php';
				// Cart/checkout/order integration — entirely WooCommerce-specific.
				if ( WBTM_Functions::is_wc_active() ) {
					require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Woocommerce.php';
				}
				//==================//
				// Coupon engine (per-bus discounts, restrictions, usage limits).
				require_once WBTM_PLUGIN_DIR . '/inc/coupon/WBTM_Coupon_Module.php';
				new WBTM_Coupon_Module();
				//==================//
				// My Account "bus panel" endpoint — WooCommerce-account-only.
				if ( WBTM_Functions::is_wc_active() ) {
					require_once WBTM_PLUGIN_DIR . '/inc/class-functions.php';
				}
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_My_Account_Dashboard.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Installer.php';
				//==================//
				// Public read-only REST API (wbtm/v1) — registers its routes on rest_api_init.
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_REST_API.php';
				//==================//
			}
			public function global_enqueue() {
				wp_enqueue_style('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.css', array(), WBTM_VERSION);
				wp_enqueue_style('mage-icon', WBTM_PLUGIN_URL . '/assets/mage-icon/css/mage-icon.css', array(), WBTM_VERSION);
				wp_enqueue_style('wbtm_bus_left_filter', WBTM_PLUGIN_URL . '/assets/global/wbtm_bus_left_filter.css', array(), WBTM_VERSION);
				$wbtm_global_js = WBTM_PLUGIN_DIR . '/assets/global/wbtm_global.js';
				wp_enqueue_script('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.js', array('jquery'), file_exists($wbtm_global_js) ? filemtime($wbtm_global_js) : WBTM_VERSION, true);
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
				// Per-seat PRICE OVERRIDE stays Pro-gated; the drag-and-drop
				// TOOLBAR (door/toilet/driver/etc.) is a free-plugin feature —
				// see WBTM_Seat_Configuration::has_seat_toolbar_features().
				$pro_seat_features_enabled = class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
				$seat_toolbar_enabled = class_exists('WBTM_Seat_Configuration') && WBTM_Seat_Configuration::has_seat_toolbar_features();
				// Seat template column-patterns + numbering schemes, shared with
				// wbtm_admin.js applySeatTemplate() — PHP stays the single source
				// of truth (also used to render the <select> options).
				$seat_templates_payload = [];
				// Labels are kept separate from seat_templates (patterns) so the
				// existing applySeatTemplate() JS — which uses seat_templates[key]
				// as the pattern array directly — is untouched; only the new
				// cabin "Configure Cabins" JS template (which has to build its
				// <option> list client-side, unlike the server-rendered deck
				// picker) needs the labels.
				$seat_template_labels_payload = [];
				if (class_exists('WBTM_Seat_Configuration')) {
					foreach (WBTM_Seat_Configuration::get_seat_templates() as $tkey => $tpl) {
						$seat_templates_payload[$tkey] = $tpl['pattern'];
						$seat_template_labels_payload[$tkey] = $tpl['label'];
					}
					// key => label map (was array_keys()-only; unused anywhere in JS
					// beforehand, so widening the shape here is safe) — same reason
					// as above, needed to build the cabin numbering <option> list.
					$seat_numbering_payload = WBTM_Seat_Configuration::get_seat_numbering_schemes();
				} else {
					$seat_numbering_payload = [];
				}
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
					'seat_toolbar_enabled' => $seat_toolbar_enabled,
					'nonseat_badge_title' => esc_attr__( 'Double click to Remove', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_templates'    => $seat_templates_payload,
					'seat_template_labels' => $seat_template_labels_payload,
					'seat_numbering_schemes' => $seat_numbering_payload,
					'seat_template_pick_error' => esc_html__( 'Please choose a seat template first.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_types'      => $ticket_types_payload,
					'seat_price_need_name' => esc_html__( 'Enter a seat label first (e.g. A1).', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_price_no_types' => esc_html__( 'Add a route fare for at least one passenger type under Routing & Pricing, or save a per-seat price first.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				do_action('wbtm_add_admin_script');
			}
			public function frontend_enqueue() {
				$wbtm_css = WBTM_PLUGIN_DIR . '/assets/frontend/wbtm.css';
				wp_enqueue_style('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.css', array(), file_exists($wbtm_css) ? filemtime($wbtm_css) : WBTM_VERSION);
				wp_enqueue_style('wtbm_search', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_search.css', array(), WBTM_VERSION);
				wp_enqueue_style('wtbm_single_bus_details', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_single_bus_details.css', array(), WBTM_VERSION);
				wp_enqueue_script('wtbm_single_bus_details', WBTM_PLUGIN_URL . '/assets/frontend/wtbm_single_bus_details.js', array('jquery'), WBTM_VERSION, true);
				wp_enqueue_script('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.js', array('jquery'), WBTM_VERSION, true);
				wp_localize_script('jquery', 'wbtm_wc_vars', array(
					'checkout_url'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
					'booking_mode'   => class_exists( 'WBTM_Functions' ) ? WBTM_Functions::booking_mode() : 'woocommerce',
					// Standalone/Custom Payment mode only — lets the booking submit handler
					// short-circuit to the inline login/register panel (Pro) without a
					// round trip when we already know the visitor isn't logged in.
					'login_required' => class_exists( 'WBTM_Functions' ) ? WBTM_Functions::login_required() : false,
					'is_logged_in'   => is_user_logged_in(),
				));
				wp_localize_script( 'wbtm_global', 'wbtm_strings', array(
					'searching'             => esc_html__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ),
					'loading'               => esc_html__( 'Loading...', 'bus-ticket-booking-with-seat-reservation' ),
					'place_departure_first' => esc_html__( 'Please place departure bus first.', 'bus-ticket-booking-with-seat-reservation' ),
					'fill_required_fields'  => esc_html__( 'Please fill all required fields', 'bus-ticket-booking-with-seat-reservation' ),
					'failed_add_ticket'     => esc_html__( 'Failed to add ticket', 'bus-ticket-booking-with-seat-reservation' ),
					// Generic transport-error message shown to the customer when an AJAX
					// request fails (network drop, 500, expired nonce). Previously every
					// error: callback only did console.log, so the user saw a blank result.
					'error_bus_list'        => esc_html__( 'Could not load buses. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_dropping_point'  => esc_html__( 'Could not load destinations. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_journey_date'    => esc_html__( 'Could not load journey dates. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_return_date'     => esc_html__( 'Could not load return dates. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_seat_plan'       => esc_html__( 'Could not load the seat plan. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					// Seat-hold countdown (WBTM_Seat_Hold): badge text, expiry notice and
					// conflict notice shown when another customer holds a selected seat.
					'seats_held_for'        => esc_html__( 'Seats held for', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_hold_expired'     => esc_html__( 'Your seat hold has expired. The seats are available to other customers again.', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_hold_conflict'    => esc_html__( 'is no longer available — it is held or booked by another customer.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_return_buses'    => esc_html__( 'Could not load return buses. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'error_generic'         => esc_html__( 'Something went wrong. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
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
		}
		new WBTM_Dependencies();
	}
