<?php
	/*
   * @Author 		MagePeople Team
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Taxonomy')) {
		class WBTM_Taxonomy {
			/**
			 * Old bus-stop term names captured on 'edit_terms' (before the DB update),
			 * keyed by term id, so the rename can be detected and migrated on the
			 * following 'edited_wbtm_bus_stops'.
			 *
			 * @var array<int,string>
			 */
			private $stop_rename_old_names = array();
			public function __construct() {
				add_action('init', [$this, 'taxonomy']);
				// Keep bus route/pricing config (and existing bookings) in sync when a
				// Bus Stop is renamed. Stops are referenced by NAME throughout, so a
				// rename would otherwise orphan every route/fare that used the old name
				// (the stop appears "unconfigured") AND make existing bookings on that
				// stop invisible to the availability query — a silent double-booking.
				add_action('edit_terms', [$this, 'wbtm_capture_stop_old_name'], 10, 2);
				add_action('edited_wbtm_bus_stops', [$this, 'wbtm_migrate_stop_rename'], 10, 1);
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
					'menu_name' => __('Pick & Drop Point', 'bus-ticket-booking-with-seat-reservation'),
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
					// No separate "Drop-Off Point" admin submenu: its term
					// management UI is merged into the Pickup Point screen
					// instead (see WBTM_Taxonomy_Modern::MERGED_TAXONOMIES).
					// show_ui stays true so the taxonomy, its terms, and its
					// capabilities keep working exactly as before.
					'show_in_menu' => false,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => array('slug' => 'bus-drop_off'),
					'show_in_rest' => false,
					'rest_base' => 'bus_drop_off',
					'meta_box_cb' => false,
				);
				register_taxonomy('wbtm_bus_drop_off', 'wbtm_bus', $args_drop_off);


                $bus_feature_args = array(
                    'hierarchical'        => true, // IMPORTANT: makes it behave like a category
                    'public'              => false,
                    'label'               => esc_html__( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
                    'labels'              => array(
                        'name'              => esc_html__( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
                        'singular_name'     => esc_html__( 'Bus Feature', 'bus-ticket-booking-with-seat-reservation' ),
                        'search_items'      => esc_html__( 'Search Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
                        'all_items'         => esc_html__( 'All Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
                        'parent_item'       => esc_html__( 'Parent Bus Feature', 'bus-ticket-booking-with-seat-reservation' ),
                        'parent_item_colon' => esc_html__( 'Parent Bus Feature:', 'bus-ticket-booking-with-seat-reservation' ),
                        'edit_item'         => esc_html__( 'Edit Bus Feature', 'bus-ticket-booking-with-seat-reservation' ),
                        'update_item'       => esc_html__( 'Update Bus Feature', 'bus-ticket-booking-with-seat-reservation' ),
                        'add_new_item'      => esc_html__( 'Add New Bus Feature', 'bus-ticket-booking-with-seat-reservation' ),
                        'new_item_name'     => esc_html__( 'New Bus Feature Name', 'bus-ticket-booking-with-seat-reservation' ),
                        'menu_name'         => esc_html__( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
                    ),
                    'show_ui'             => true,
                    'show_admin_column'   => true, // shows column in bus list table
                    'show_in_menu'        => 'edit.php?post_type=wbtm_bus',
                    'publicly_queryable'  => true,
                    'exclude_from_search' => true,
                    'show_in_nav_menus'   => false,
                    'has_archive'         => false,
                    'rewrite'             => false,
                );
                register_taxonomy( 'wbtm_bus_feature', 'wbtm_bus', $bus_feature_args );

            }
			/**
			 * Remember a bus-stop term's current name just before it is updated, so
			 * wbtm_migrate_stop_rename() can tell whether the name actually changed.
			 *
			 * @param int    $term_id  Term being edited.
			 * @param string $taxonomy Its taxonomy.
			 */
			public function wbtm_capture_stop_old_name( $term_id, $taxonomy ) {
				if ( 'wbtm_bus_stops' !== $taxonomy ) {
					return;
				}
				$term = get_term( (int) $term_id, 'wbtm_bus_stops' );
				if ( $term && ! is_wp_error( $term ) ) {
					$this->stop_rename_old_names[ (int) $term_id ] = $term->name;
				}
			}
			/**
			 * When a bus stop is renamed, migrate every reference to the old name —
			 * across all buses' route/fare configuration and all existing bookings —
			 * to the new name, so nothing has to be reconfigured and availability
			 * keeps counting the renamed stop's bookings.
			 *
			 * @param int $term_id Renamed term id.
			 */
			public function wbtm_migrate_stop_rename( $term_id ) {
				$term_id = (int) $term_id;
				if ( ! isset( $this->stop_rename_old_names[ $term_id ] ) ) {
					return;
				}
				$old_name = $this->stop_rename_old_names[ $term_id ];
				unset( $this->stop_rename_old_names[ $term_id ] );
				$term = get_term( $term_id, 'wbtm_bus_stops' );
				if ( ! $term || is_wp_error( $term ) ) {
					return;
				}
				$new_name = $term->name;
				// Strict compare: a case-only rename (Berlin -> berlin) still needs
				// migrating because the editor's stop <select> matches case-sensitively.
				if ( $old_name === '' || $old_name === $new_name ) {
					return;
				}
				$bus_ids = get_posts( array(
					'post_type'      => WBTM_Functions::get_cpt(),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				) );
				foreach ( $bus_ids as $bus_id ) {
					$this->wbtm_rename_stop_in_bus_config( (int) $bus_id, $old_name, $new_name );
				}
				$this->wbtm_rename_stop_in_bookings( $old_name, $new_name );
			}
			/**
			 * Replace a stop name in one bus's route + fare configuration meta.
			 */
			private function wbtm_rename_stop_in_bus_config( $bus_id, $old_name, $new_name ) {
				$is_old = static function ( $value ) use ( $old_name ) {
					return is_string( $value ) && 0 === strcasecmp( trim( $value ), trim( $old_name ) );
				};
				// Route legs: rows keyed by 'place'.
				foreach ( array( 'wbtm_route_info', 'wbtm_return_route_info' ) as $key ) {
					$rows = get_post_meta( $bus_id, $key, true );
					if ( is_array( $rows ) && $rows ) {
						$changed = false;
						foreach ( $rows as $i => $row ) {
							if ( is_array( $row ) && isset( $row['place'] ) && $is_old( $row['place'] ) ) {
								$rows[ $i ]['place'] = $new_name;
								$changed            = true;
							}
						}
						if ( $changed ) {
							update_post_meta( $bus_id, $key, $rows );
						}
					}
				}
				// Fare rows: boarding/dropping stop names.
				$prices = get_post_meta( $bus_id, 'wbtm_bus_prices', true );
				if ( is_array( $prices ) && $prices ) {
					$changed = false;
					foreach ( $prices as $i => $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						if ( isset( $row['wbtm_bus_bp_price_stop'] ) && $is_old( $row['wbtm_bus_bp_price_stop'] ) ) {
							$prices[ $i ]['wbtm_bus_bp_price_stop'] = $new_name;
							$changed                                = true;
						}
						if ( isset( $row['wbtm_bus_dp_price_stop'] ) && $is_old( $row['wbtm_bus_dp_price_stop'] ) ) {
							$prices[ $i ]['wbtm_bus_dp_price_stop'] = $new_name;
							$changed                                = true;
						}
					}
					if ( $changed ) {
						update_post_meta( $bus_id, 'wbtm_bus_prices', $prices );
					}
				}
				// Flat stop-name arrays (boarding list, dropping list, route order).
				foreach ( array( 'wbtm_bus_bp_stops', 'wbtm_bus_next_stops', 'wbtm_route_direction' ) as $key ) {
					$list = get_post_meta( $bus_id, $key, true );
					if ( is_array( $list ) && $list ) {
						$changed = false;
						foreach ( $list as $i => $value ) {
							if ( $is_old( $value ) ) {
								$list[ $i ] = $new_name;
								$changed    = true;
							}
						}
						if ( $changed ) {
							update_post_meta( $bus_id, $key, $list );
						}
					}
				}
			}
			/**
			 * Repoint existing bookings from the old stop name to the new one.
			 *
			 * Availability (WBTM_Query::query_total_booked) matches bookings to a route
			 * by wbtm_boarding_point / wbtm_dropping_point, so leaving these on the old
			 * name after the route was migrated would drop those seats from the count
			 * and allow them to be sold twice.
			 */
			private function wbtm_rename_stop_in_bookings( $old_name, $new_name ) {
				foreach ( array( 'wbtm_boarding_point', 'wbtm_dropping_point' ) as $meta_key ) {
					$booking_ids = get_posts( array(
						'post_type'      => 'wbtm_bus_booking',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_query'     => array(
							array(
								'key'     => $meta_key,
								'value'   => $old_name,
								'compare' => '=',
							),
						),
					) );
					foreach ( $booking_ids as $booking_id ) {
						update_post_meta( (int) $booking_id, $meta_key, $new_name );
					}
				}
			}
		}
		new WBTM_Taxonomy();
	}