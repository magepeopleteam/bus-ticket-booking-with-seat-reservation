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
			}
			public function add_cpt(): void {
				$name = WBTM_Functions::get_name();
				$slug = WBTM_Functions::get_slug();
				$icon = WBTM_Functions::get_icon();
				$labels = array(
					'name' => _x($name, 'bus-ticket-booking-with-seat-reservation'),
					'singular_name' => _x($name, 'bus-ticket-booking-with-seat-reservation'),
					'menu_name' => __($name, 'bus-ticket-booking-with-seat-reservation'),
					'name_admin_bar' => __($name, 'bus-ticket-booking-with-seat-reservation'),
					'archives' => __($name . ' List', 'bus-ticket-booking-with-seat-reservation'),
					'attributes' => __($name . ' List', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item_colon' => __($name . ' Item:', 'bus-ticket-booking-with-seat-reservation'),
					'all_items' => __('All', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'add_new_item' => __('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'add_new' => __('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'new_item' => __('New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'edit_item' => __('Edit', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'update_item' => __('Update', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'view_item' => __('View', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'view_items' => __('View', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'search_items' => __('Search', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'not_found' => __('Not found', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'not_found_in_trash' => __('Not found in Trash', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'featured_image' => __('Feature Image', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'set_featured_image' => __('Set', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . '.' . __('featured image', 'bus-ticket-booking-with-seat-reservation'),
					'remove_featured_image' => __('Remove', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . '.' . __('featured image', 'bus-ticket-booking-with-seat-reservation'),
					'use_featured_image' => __('Use as', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . '.' . __('featured image', 'bus-ticket-booking-with-seat-reservation'),
					'insert_into_item' => __('Insert into', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'uploaded_to_this_item' => __('Uploaded to this', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name,
					'items_list' => $name . ' ' . __(' list', 'bus-ticket-booking-with-seat-reservation'),
					'items_list_navigation' => $name . ' ' . __(' list navigation', 'bus-ticket-booking-with-seat-reservation'),
					'filter_items_list' => __('Filter', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . __('list', 'bus-ticket-booking-with-seat-reservation'),
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

				$argsl = apply_filters( 'filter_wbtm_bus_booking', array(
					'public'          => true,
					'label'           => __( 'Bus Attendee', 'bus-ticket-booking-with-seat-reservation' ),
					'menu_icon'       => 'dashicons-id',
					'supports'        => array( 'title' ),
					// 'show_in_menu' => 'edit.php?post_type=mep_events',
					'exclude_from_search'   => true,
					'show_in_menu'    => false,
					'capability_type' => 'post',
					'capabilities'    => array(
						'create_posts' => 'do_not_allow',
					),
					'map_meta_cap'    => true,
					'show_in_rest'    => true,
					'rest_base'       => 'wbtm_bus_bookings'
				) );
				register_post_type( 'wbtm_bus_booking', $argsl );
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
				$column['taxonomy-wbtm_bus_cat'] = WBTM_Translations::text_coach_type();
				$column['wbtm_added_by'] = esc_html__('Added by', 'bus-ticket-booking-with-seat-reservation');
				$column['date'] = $date;
				return $column;
			}
			public function custom_column_data($column, $post_id) {
				$seat_plan = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
				$seat_plan_text = $seat_plan == 'wbtm_seat_plan' ? esc_html__('Seal Plan', 'bus-ticket-booking-with-seat-reservation') : esc_html__('Without Seal Plan', 'bus-ticket-booking-with-seat-reservation');
				switch ($column) {
					case 'wbtm_bus_no':
						echo "<span class=''>" . MP_Global_Function::get_post_info($post_id, 'wbtm_bus_no') . "</span>";
						break;
					case 'wbtm_bus_type':
						echo "<span class=''>" . $seat_plan_text . "</span>";
						break;
					case 'wbtm_added_by':
						$user_id = get_post_field('post_author', $post_id);
						echo "<span class=''>" . get_the_author_meta('display_name', $user_id) . ' [' . WBTM_Functions::wbtm_get_user_role($user_id) . "]</span>";
						break;
				}
			}
		}
		new WBTM_CPT();
	}