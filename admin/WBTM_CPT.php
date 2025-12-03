<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_CPT')) {
		class WBTM_CPT {
			public function __construct() {
				add_action('init', [$this, 'add_cpt']);
				//=======================//
				add_action('manage_wbtm_bus_posts_columns', [$this, 'set_custom_columns'], 5, 2);
				add_action('manage_wbtm_bus_posts_custom_column', [$this, 'custom_column_data'], 5, 2);
				//=======================//
				// Prevent public access to booking pages
				add_action('wp_head', [$this, 'add_noindex_meta'], 1);
				add_filter('robots_txt', [$this, 'add_robots_txt_rules'], 10, 2);
			}
			public function add_cpt(): void {
				$name = WBTM_Functions::get_name();
				$slug = WBTM_Functions::get_slug();
				$icon = WBTM_Functions::get_icon();

				$labels = array(
					'name' => $name,
					'singular_name' => $name,
					'menu_name' => $name,
					'name_admin_bar' => $name,
					/* translators: %s: event name */
					'archives' => sprintf(__('%s List', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'attributes' => sprintf(__('%s Attributes', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item_colon' => sprintf(__('%s Item :', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'all_items' => sprintf(__('All %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'add_new_item' => sprintf(__('All New %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'add_new' => sprintf(__('Add New %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'new_item' => sprintf(__('New %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'edit_item' => sprintf(__('Edit %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'update_item' => sprintf(__('Update %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'view_item' => sprintf(__('View %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'view_items' => sprintf(__('View %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'search_items' => sprintf(__('Search %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'not_found' => sprintf(__('Not found %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'not_found_in_trash' => sprintf(__('Not found in Trash %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'featured_image' => sprintf(__('Feature Image %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'insert_into_item' => sprintf(__('Insert into %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'uploaded_to_this_item' => sprintf(__('Uploaded to this %s ', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list' => sprintf(__('%s list', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list_navigation' => sprintf(__('%s list navigation', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'set_featured_image' => sprintf(__('Set %s featured image', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'remove_featured_image' => sprintf(__('Remove %s featured image', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'use_featured_image' => sprintf(__('Use as %s featured image', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'filter_items_list' => sprintf(__('Filter %s list', 'bus-ticket-booking-with-seat-reservation'), $name),
				);
				$args = array(
					'public' => true,
					'labels' => $labels,
					'menu_icon' => $icon,
					'supports' => array('title', 'editor', 'thumbnail'),
					'rewrite' => array('slug' => $slug),
					'show_in_rest' => true,
					'rest_base' => 'wbtm_bus',
					'capability_type' => 'wbtm_bus',
					'capabilities' => array(
						'publish_posts' => 'publish_wbtm_buses',
						'edit_posts' => 'edit_wbtm_buses',
						'edit_others_posts' => 'edit_others_wbtm_buses',
						'read_private_posts' => 'read_private_wbtm_buses',
						'edit_post' => 'edit_wbtm_bus',
						'delete_post' => 'delete_wbtm_bus',
						'read_post' => 'read_wbtm_bus',
						'wbtm_permission_page' => 'wbtm_permission_page',
						'extra_service_wbtm_bus' => 'extra_service_wbtm_bus',
					),
				);
				$args = apply_filters('wbtm_add_cap', $args);
				register_post_type('wbtm_bus', $args);
				$argsl = apply_filters('wbtm_filter_bus_booking', array(
					'public' => false, // Changed from true to false
					'publicly_queryable' => false, // Explicitly prevent public queries
					'label' => __('Bus Attendee', 'bus-ticket-booking-with-seat-reservation'),
					'menu_icon' => 'dashicons-id',
					'supports' => array('title'),
					// 'show_in_menu' => 'edit.php?post_type=mep_events',
					'exclude_from_search' => true,
					'show_in_menu' => false,
					'capability_type' => 'post',
					'capabilities' => array(
						'create_posts' => 'do_not_allow',
					),
					'map_meta_cap' => true,
					'show_in_rest' => false, // Disable REST API access
					'rest_base' => 'wbtm_bus_bookings'
				));
				register_post_type('wbtm_bus_booking', $argsl);
			}
			//************************************//
			public function set_custom_columns($column) {
				$name = WBTM_Functions::get_name();
				$date = $column['date'];
				unset($column['taxonomy-wbtm_bus_pickpoint']);
				unset($column['taxonomy-wbtm_bus_stops']);
				unset($column['taxonomy-wbtm_bus_cat']);
				unset($column['date']);
				$column['wbtm_bus_no'] = esc_html__('Coach no', 'bus-ticket-booking-with-seat-reservation');
				$column['wbtm_bus_type'] = $name . ' ' . esc_html__('Type', 'bus-ticket-booking-with-seat-reservation');
				$column['wbtm_coach_type'] = WBTM_Translations::text_coach_type();
				$column['wbtm_added_by'] = esc_html__('Added by', 'bus-ticket-booking-with-seat-reservation');
				$column['date'] = $date;
				return $column;
			}
			public function custom_column_data($column, $post_id) {
				$seat_plan = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
				$seat_plan_text = $seat_plan == 'wbtm_seat_plan' ? esc_html__('Seal Plan', 'bus-ticket-booking-with-seat-reservation') : esc_html__('Without Seal Plan', 'bus-ticket-booking-with-seat-reservation');
				switch ($column) {
					case 'wbtm_bus_no':
						echo wp_kses_post("<span class=''>" . WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_no') . "</span>");
						break;
					case 'wbtm_bus_type':
						echo wp_kses_post("<span class=''>" . $seat_plan_text . "</span>");
						break;
					case 'wbtm_coach_type':
						$category = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category');
						echo wp_kses_post("<span class=''>" . ($category ? esc_html($category) : '-') . "</span>");
						break;
					case 'wbtm_added_by':
						$user_id = get_post_field('post_author', $post_id);
						echo wp_kses_post("<span class=''>" . get_the_author_meta('display_name', $user_id) . ' [' . WBTM_Functions::wbtm_get_user_role($user_id) . "]</span>");
						break;
				}
			}
			/**
			 * Add noindex meta tag to booking pages (extra security)
			 */
			public function add_noindex_meta() {
				// Check multiple ways to detect booking page access
				$is_booking_page = false;
				// Method 1: Check if it's a singular booking post
				if (is_singular('wbtm_bus_booking')) {
					$is_booking_page = true;
				}
				// Method 2: Check URL patterns
//				$request_uri = $_SERVER['REQUEST_URI'] ?? '';
//				if (strpos($request_uri, '/wbtm_bus_booking/') !== false) {
//					$is_booking_page = true;
//				}
//				// Method 3: Check query parameters
//				if (isset($_GET['wbtm_bus_booking']) || (isset($_GET['post_type']) && $_GET['post_type'] === 'wbtm_bus_booking')) {
//					$is_booking_page = true;
//				}
				// Method 4: Check current post
				global $post;
				if ($post && $post->post_type === 'wbtm_bus_booking') {
					$is_booking_page = true;
				}
				if ($is_booking_page) {
					echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
				}
			}
			/**
			 * Add robots.txt rules to prevent crawling of booking pages
			 */
			public function add_robots_txt_rules($output, $public) {
				if ($public) {
					$output .= "\n# Block bus booking pages from search engines\n";
					$output .= "Disallow: /wbtm_bus_booking/\n";
					$output .= "Disallow: /*?wbtm_bus_booking=\n";
					$output .= "Disallow: /*?post_type=wbtm_bus_booking\n";
				}
				return $output;
			}
		}
		new WBTM_CPT();
	}