<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Taxonomy')) {
		class WBTM_Taxonomy {
			public function __construct() {
				add_action('init', [$this, 'taxonomy']);
			}
			public function taxonomy() {
				$name = WBTM_Functions::get_name();
				$labels = array(
					'name' => $name . ' ' . esc_html__(' Type', 'bus-ticket-booking-with-seat-reservation'),
					'singular_name' => _x($name . ' Type', 'bus-ticket-booking-with-seat-reservation'),
					'menu_name' => _x($name . ' Type', 'bus-ticket-booking-with-seat-reservation'),
					'all_items' => esc_html__('All', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item_colon' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type:', 'bus-ticket-booking-with-seat-reservation'),
					'new_item_name' => _x('New  ' . $name . '  Type Name', 'bus-ticket-booking-with-seat-reservation'),
					'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . _x('Type', 'bus-ticket-booking-with-seat-reservation'),
					'edit_item' => _x('Edit  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'update_item' => _x('Update  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'view_item' => _x('View  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'add_or_remove_items' => _x('Add or remove  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'popular_items' => _x('Popular  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'search_items' => _x('Search  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					'no_terms' => _x('No  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'items_list' => _x($name . ' Type list', 'bus-ticket-booking-with-seat-reservation'),
					'items_list_navigation' => _x($name . ' Type list navigation', 'bus-ticket-booking-with-seat-reservation'),
				);
				$args = [
					'hierarchical' => true,
					"public" => true,
					'labels' => $labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => ['slug' => 'bus-category'],
					'show_in_rest' => true,
					'rest_base' => 'bus_cat',
					'meta_box_cb' => false,
				];
				register_taxonomy('wbtm_bus_cat', 'wbtm_bus', $args);
				$bus_stops_labels = array(
					'singular_name' => _x($name . ' Stops', 'bus-ticket-booking-with-seat-reservation'),
					'name' => _x($name . ' Stops', 'bus-ticket-booking-with-seat-reservation'),
				);
				$bus_stops_args = [
					'hierarchical' => true,
					"public" => true,
					'labels' => $bus_stops_labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => ['slug' => 'bus-stops'],
					'show_in_rest' => true,
					'rest_base' => 'bus_stops',
					'meta_box_cb' => false,
				];
				register_taxonomy('wbtm_bus_stops', 'wbtm_bus', $bus_stops_args);
				$labels = array(
					'name' => $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'singular_name' => esc_html__($name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'menu_name' => esc_html__($name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'all_items' => esc_html__('Allx ' . $name . ' Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item_colon' => _x('Parent ' . $name . ' Pickup Point:', 'bus-ticket-booking-with-seat-reservation'),
					'new_item_name' => _x('New ' . $name . ' Pickup Point Name', 'bus-ticket-booking-with-seat-reservation'),
					'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'edit_item' => _x('Edit ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'update_item' => _x('Update ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'view_item' => _x('View ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'add_or_remove_items' => _x('Add or remove ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'popular_items' => _x('Popular' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'search_items' => _x('Search' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					'no_terms' => _x('No' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'items_list' => _x($name . ' Pickup Point list', 'bus-ticket-booking-with-seat-reservation'),
					'items_list_navigation' => _x($name . ' Pickup Point list navigation', 'bus-ticket-booking-with-seat-reservation'),
				);
				$args = array(
					'hierarchical' => true,
					"public" => true,
					'labels' => $labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => array('slug' => 'bus-pickuppoint'),
					'show_in_rest' => false,
					'rest_base' => 'bus_pickpoint',
					'meta_box_cb' => false,
				);
				register_taxonomy('wbtm_bus_pickpoint', 'wbtm_bus', $args);
                $labels_drop_off = array(
                    'name' => $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'singular_name' => esc_html__($name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'menu_name' => esc_html__($name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'all_items' => esc_html__('Allx ' . $name . ' Bus Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'parent_item_colon' => _x('Parent ' . $name . ' Drop-Off Point:', 'bus-ticket-booking-with-seat-reservation'),
                    'new_item_name' => _x('New ' . $name . ' Drop-Off Point Name', 'bus-ticket-booking-with-seat-reservation'),
                    'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'edit_item' => _x('Edit ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'update_item' => _x('Update ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'view_item' => _x('View ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
                    'add_or_remove_items' => _x('Add or remove ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
                    'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
                    'popular_items' => _x('Popular' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'search_items' => _x('Search' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
                    'no_terms' => _x('No' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'items_list' => _x($name . ' Drop-Off Point list', 'bus-ticket-booking-with-seat-reservation'),
                    'items_list_navigation' => _x($name . ' Drop-Off Point list navigation', 'bus-ticket-booking-with-seat-reservation'),
                );
                $args_drop_off = array(
                    'hierarchical' => true,
                    "public" => true,
                    'labels' => $labels_drop_off,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'update_count_callback' => '_update_post_term_count',
                    'query_var' => true,
                    'rewrite' => array('slug' => 'bus-drop_off'),
                    'show_in_rest' => false,
                    'rest_base' => 'bus_drop_off',
                    'meta_box_cb' => false,
                );
                register_taxonomy('wbtm_bus_drop_off', 'wbtm_bus', $args_drop_off);
			}
		}
		new WBTM_Taxonomy();
	}