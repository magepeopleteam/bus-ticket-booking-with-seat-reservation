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
					/* translators: %s: event name */
					'name' => sprintf(__('%s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'singular_name' => sprintf(__('%s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'menu_name' => sprintf(__('%s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'all_items' => sprintf(__('All %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item' => sprintf(__('Parent %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item_colon' => sprintf(__('Parent %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'new_item_name' => sprintf(__('New %s Type Name', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'add_new_item' => sprintf(__('Add New %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'edit_item' => sprintf(__('Edit %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'update_item' => sprintf(__('Update %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'view_item' => sprintf(__('View %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					'separate_items_with_commas' => __('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => __('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => __('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					/* translators: %s: event name */
					'add_or_remove_items' => sprintf(__('Add or remove %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'popular_items' => sprintf(__('Popular %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'search_items' => sprintf(__('Search %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'no_terms' => sprintf(__('No %s Type', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list' => sprintf(__('%s Type list', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list_navigation' => sprintf(__('%s Type list navigation', 'bus-ticket-booking-with-seat-reservation'), $name),
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
					/* translators: %s: event name */
					'name' => sprintf(__('%s Stops', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'singular_name' => sprintf(__('%s Stops', 'bus-ticket-booking-with-seat-reservation'), $name),
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
					/* translators: %s: event name */
					'name' => sprintf(__('%s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'singular_name' => sprintf(__('%s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'menu_name' => sprintf(__('%s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'all_items' => sprintf(__('All %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item' => sprintf(__('Parent %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item_colon' => sprintf(__('Parent %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'new_item_name' => sprintf(__('New %s Pickup Point Name', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'add_new_item' => sprintf(__('Add New %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'edit_item' => sprintf(__('Edit %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'update_item' => sprintf(__('Update %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'view_item' => sprintf(__('View %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					'separate_items_with_commas' => __('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => __('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => __('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					/* translators: %s: event name */
					'add_or_remove_items' => sprintf(__('Add or remove %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'popular_items' => sprintf(__('Popular %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'search_items' => sprintf(__('Search %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'no_terms' => sprintf(__('No %s Pickup Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list' => sprintf(__('%s Pickup Point list', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list_navigation' => sprintf(__('%s Pickup Point list navigation', 'bus-ticket-booking-with-seat-reservation'), $name),
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
					/* translators: %s: event name */
					'name' => sprintf(__('%s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'singular_name' => sprintf(__('%s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'menu_name' => sprintf(__('%s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'all_items' => sprintf(__('All %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item' => sprintf(__('Parent %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'parent_item_colon' => sprintf(__('Parent %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'new_item_name' => sprintf(__('New %s Drop-Off Point Name', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'add_new_item' => sprintf(__('Add New %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'edit_item' => sprintf(__('Edit %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'update_item' => sprintf(__('Update %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'view_item' => sprintf(__('View %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					'separate_items_with_commas' => __('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => __('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => __('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					/* translators: %s: event name */
					'add_or_remove_items' => sprintf(__('Add or remove %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'popular_items' => sprintf(__('Popular %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'search_items' => sprintf(__('Search %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'no_terms' => sprintf(__('No %s Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list' => sprintf(__('%s Drop-Off Point list', 'bus-ticket-booking-with-seat-reservation'), $name),
					/* translators: %s: event name */
					'items_list_navigation' => sprintf(__('%s Drop-Off Point list navigation', 'bus-ticket-booking-with-seat-reservation'), $name),
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