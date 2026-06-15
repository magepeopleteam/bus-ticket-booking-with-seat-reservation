<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Query')) {
		class WBTM_Query {
			public function __construct() {
				// Drop the per-request booked map whenever booking data changes mid-request,
				// so a later availability check can never read a stale count.
				add_action( 'wbtm_order_status_change', array( __CLASS__, 'flush_booked_map' ) );
				add_action( 'save_post_wbtm_bus_booking', array( __CLASS__, 'flush_booked_map' ) );
				add_action( 'deleted_post_wbtm_bus_booking', array( __CLASS__, 'flush_booked_map' ) );
			}
			public static function flush_booked_map() {
				self::$booked_map_cache = array();
			}
			/**
			 * Request-scoped cache of booked seats/counts, grouped by date, for a given
			 * bus + boarding/dropping pair. Lets the calendar's sold-out scan resolve
			 * every date from a single query instead of two DB queries per date.
			 * Shape: [ "<post_id>|<start>|<end>" => [ 'totals' => [date=>int], 'seats' => [date=>array] ] ]
			 *
			 * Deliberately per-request (static) rather than a transient: booking counts
			 * drive duplicate-sale prevention, so we never want to serve a stale count
			 * across requests.
			 */
			private static $booked_map_cache = [];
			private static function booked_map_key( $post_id, $start, $end ) {
				return $post_id . '|' . strtolower( (string) $start ) . '|' . strtolower( (string) $end );
			}
			/**
			 * Build the per-date booked map for a bus/route in ONE query and memoise it
			 * for the rest of the request. query_total_booked()/query_seat_booked() then
			 * read straight from it for any date, with no further DB hits.
			 *
			 * Mirrors exactly the meta_query, status set and counting rules of those two
			 * methods (minus the date filter) so results are identical.
			 */
			public static function prime_booked_map( $post_id, $start, $end ) {
				$key = self::booked_map_key( $post_id, $start, $end );
				if ( isset( self::$booked_map_cache[ $key ] ) ) {
					return;
				}
				$map = array( 'totals' => array(), 'seats' => array() );
				self::$booked_map_cache[ $key ] = $map; // mark primed up-front (covers the no-result / invalid-route case)
				if ( ! ( $post_id && $start && $end ) ) {
					return;
				}
				$seat_booked_status = WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'set_book_status', array( 'processing', 'completed' ) );
				// Keep pending in sync with the per-date queries: pending orders reserve seats.
				$seat_booked_status = array_filter( array_unique( array_merge( (array) $seat_booked_status, array( 'pending' ) ) ) );
				$routes = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_direction', array() );
				if ( sizeof( $routes ) === 0 ) {
					return;
				}
				$norm   = self::wbtm_normalize_route_for_booking_query( $routes, $start, $end );
				$routes = $norm['routes'];
				$sp     = $norm['sp'];
				$ep     = $norm['ep'];
				if ( $sp === false || $ep === false ) {
					return;
				}
				$args = array(
					'post_type'      => 'wbtm_bus_booking',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array(
						array(
							'relation' => 'AND',
							array( 'key' => 'wbtm_boarding_point', 'value' => array_slice( $routes, 0, $ep ), 'compare' => 'IN' ),
							array( 'key' => 'wbtm_dropping_point', 'value' => array_slice( $routes, $sp + 1 ), 'compare' => 'IN' ),
							array( 'key' => 'wbtm_bus_id', 'value' => $post_id, 'compare' => '=' ),
							array( 'key' => 'wbtm_order_status', 'value' => $seat_booked_status, 'compare' => 'IN' ),
						),
					),
				);
				$booking_ids = get_posts( $args );
				if ( sizeof( $booking_ids ) === 0 ) {
					return;
				}
				// Prime the meta cache once so the per-booking reads below don't each hit the DB (avoids N+1).
				update_meta_cache( 'post', $booking_ids );
				foreach ( $booking_ids as $booking_id ) {
					$start_time = WBTM_Global_Function::get_post_info( $booking_id, 'wbtm_start_time' );
					if ( ! $start_time ) {
						continue;
					}
					$date = gmdate( 'Y-m-d', strtotime( $start_time ) );
					// Total booked — full-bus bookings cover all their seats (matches query_total_booked()).
					$booking_mode        = WBTM_Global_Function::get_post_info( $booking_id, 'wbtm_booking_mode' );
					$full_bus_seat_count = (int) WBTM_Global_Function::get_post_info( $booking_id, 'wbtm_full_bus_seat_count' );
					$units               = ( $booking_mode === 'full_bus' && $full_bus_seat_count > 0 ) ? $full_bus_seat_count : 1;
					$map['totals'][ $date ] = ( isset( $map['totals'][ $date ] ) ? $map['totals'][ $date ] : 0 ) + $units;
					// Booked seat names (matches query_seat_booked()).
					$seat_name = WBTM_Global_Function::get_post_info( $booking_id, 'wbtm_seat' );
					if ( class_exists( 'WBTM_Seat_Configuration' ) ) {
						$seat_name = WBTM_Seat_Configuration::normalize_saved_seat_value( $seat_name );
					}
					if ( $seat_name && ! ( class_exists( 'WBTM_Seat_Configuration' ) && WBTM_Seat_Configuration::is_non_seat_item( $seat_name ) ) ) {
						if ( ! isset( $map['seats'][ $date ] ) ) {
							$map['seats'][ $date ] = array();
						}
						$map['seats'][ $date ][] = $seat_name;
					}
				}
				self::$booked_map_cache[ $key ] = $map;
			}
			/** Read a primed total for a date, or null when nothing is primed for this route. */
			private static function booked_map_total( $post_id, $start, $end, $date ) {
				$key = self::booked_map_key( $post_id, $start, $end );
				if ( ! isset( self::$booked_map_cache[ $key ] ) ) {
					return null;
				}
				$date = gmdate( 'Y-m-d', strtotime( $date ) );
				return isset( self::$booked_map_cache[ $key ]['totals'][ $date ] ) ? (int) self::$booked_map_cache[ $key ]['totals'][ $date ] : 0;
			}
			/** Read primed seat names for a date, or null when nothing is primed for this route. */
			private static function booked_map_seats( $post_id, $start, $end, $date ) {
				$key = self::booked_map_key( $post_id, $start, $end );
				if ( ! isset( self::$booked_map_cache[ $key ] ) ) {
					return null;
				}
				$date = gmdate( 'Y-m-d', strtotime( $date ) );
				return isset( self::$booked_map_cache[ $key ]['seats'][ $date ] ) ? self::$booked_map_cache[ $key ]['seats'][ $date ] : array();
			}
            public static function get_bus_id($start = '', $end = '', $cat = '', $posts_per_page = -1) {
                $bus_ids = [];
                $start_route_query = !empty($start) ? array(
                    'key' => 'wbtm_bus_bp_stops',
                    'value' => $start,
                    'compare' => 'LIKE',
                ) : '';
                $end_route_query = !empty($end) ? array(
                    'key' => 'wbtm_bus_next_stops',
                    'value' => $end,
                    'compare' => 'LIKE',
                ) : '';
                $cat_query = [];
                if (!empty($cat)) {
                    $taxonomies = get_object_taxonomies('wbtm_bus');
                    $cat_value = $cat;
                    if (!empty($taxonomies)) {
                        foreach ($taxonomies as $tax) {
                            $term = get_term_by('id', $cat, $tax);
                            if ($term && !is_wp_error($term)) {
                                $cat_value = trim($term->name);
                                break;
                            }
                        }
                    }
                    $cat_query[] = array(
                        'key'     => 'wbtm_bus_category',
                        'value'   => $cat_value,
                        'compare' => '='
                    );
                }
                $args = array(
                    'post_type' => array('wbtm_bus'),
                    'posts_per_page' => $posts_per_page,
                    'order' => 'ASC',
                    'orderby' => 'meta_value',
                    'post_status' => 'publish',
                    'meta_query' => array(
                        'relation' => 'AND',
                        $start_route_query,
                        $end_route_query,
                        $cat_query
                    )
                );
                $bus_query = new WP_Query($args);
                while ($bus_query->have_posts()) {
                    $bus_query->the_post();
                    $bus_ids[] = get_the_id();
                }
                wp_reset_postdata();
                return $bus_ids;
            }

            public static function get_bus_count($start = '', $end = '', $cat = '') {
                $start_route_query = !empty($start) ? array(
                    'key' => 'wbtm_bus_bp_stops',
                    'value' => $start,
                    'compare' => 'LIKE',
                ) : '';
                $end_route_query = !empty($end) ? array(
                    'key' => 'wbtm_bus_next_stops',
                    'value' => $end,
                    'compare' => 'LIKE',
                ) : '';
                $cat_query = [];
                if (!empty($cat)) {
                    $taxonomies = get_object_taxonomies('wbtm_bus');
                    $cat_value = $cat;
                    if (!empty($taxonomies)) {
                        foreach ($taxonomies as $tax) {
                            $term = get_term_by('id', $cat, $tax);
                            if ($term && !is_wp_error($term)) {
                                $cat_value = trim($term->name);
                                break;
                            }
                        }
                    }
                    $cat_query[] = array(
                        'key'     => 'wbtm_bus_category',
                        'value'   => $cat_value,
                        'compare' => '='
                    );
                }
                $args = array(
                    'post_type' => array('wbtm_bus'),
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'order' => 'ASC',
                    'orderby' => 'meta_value',
                    'post_status' => 'publish',
                    'meta_query' => array(
                        'relation' => 'AND',
                        $start_route_query,
                        $end_route_query,
                        $cat_query
                    )
                );
                $bus_query = new WP_Query($args);
                $total_count = $bus_query->found_posts;
                wp_reset_postdata();
                return $total_count;
            }

			/**
			 * Same-bus return: boarding index may be after dropping index on stored wbtm_route_direction.
			 * Reverse a copy so overlap queries match forward logic.
			 *
			 * @param array<int, string> $routes
			 * @return array{routes: array<int, string>, sp: int|false, ep: int|false}
			 */
			private static function wbtm_normalize_route_for_booking_query( $routes, $start, $end ) {
				if ( ! is_array( $routes ) || count( $routes ) === 0 ) {
					return [ 'routes' => $routes, 'sp' => false, 'ep' => false ];
				}
				$sp = false;
				$ep = false;
				foreach ( $routes as $idx => $place ) {
					if ( strtolower( (string) $place ) === strtolower( (string) $start ) ) {
						$sp = (int) $idx;
					}
					if ( strtolower( (string) $place ) === strtolower( (string) $end ) ) {
						$ep = (int) $idx;
					}
				}
				if ( $sp === false || $ep === false ) {
					return [ 'routes' => $routes, 'sp' => $sp, 'ep' => $ep ];
				}
				if ( $sp > $ep ) {
					$routes = array_reverse( $routes );
					$sp     = false;
					$ep     = false;
					foreach ( $routes as $idx => $place ) {
						if ( strtolower( (string) $place ) === strtolower( (string) $start ) ) {
							$sp = (int) $idx;
						}
						if ( strtolower( (string) $place ) === strtolower( (string) $end ) ) {
							$ep = (int) $idx;
						}
					}
				}
				return [ 'routes' => $routes, 'sp' => $sp, 'ep' => $ep ];
			}

            public static function query_total_booked($post_id, $start, $end, $date, $ticket_name = '', $seat_name = '') {
				$total_booked = 0;
				if ($post_id && $start && $end && $date) {
					$date = gmdate('Y-m-d', strtotime($date));
					// Serve from the request-scoped batch map when one was primed for this route
					// (calendar sold-out scan). Only the unfiltered totals are batched.
					if ( empty( $ticket_name ) && empty( $seat_name ) ) {
						$cached = self::booked_map_total( $post_id, $start, $end, $date );
						if ( $cached !== null ) {
							return $cached;
						}
					}
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					// Pending orders have already reserved seats during checkout, so they must be
					// counted as booked to prevent duplicate sales while payment is finalised.
					$seat_booked_status = array_filter( array_unique( array_merge( (array) $seat_booked_status, array( 'pending' ) ) ) );
					$routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
					if (sizeof($routes) > 0) {
						$norm = self::wbtm_normalize_route_for_booking_query( $routes, $start, $end );
						$routes = $norm['routes'];
						$sp = $norm['sp'];
						$ep = $norm['ep'];
						if ( $sp === false || $ep === false ) {
							return 0;
						}
						$seat_query = !empty($seat_name) ? array(
							'key' => 'wbtm_seat',
							'value' => $seat_name,
							'compare' => '='
						) : '';
						$ticket_query = !empty($ticket_name) ? array(
							'key' => 'wbtm_ticket',
							'value' => $ticket_name,
							'compare' => '='
						) : '';
						$args = array(
							'post_type' => 'wbtm_bus_booking',
							'posts_per_page' => -1,
							'fields' => 'ids',
							'meta_query' => array(
								array(
									'relation' => 'AND',
									array(
										'key' => 'wbtm_boarding_point',
										'value' => array_slice($routes, 0, $ep),
										'compare' => 'IN'
									),
									array(
										'key' => 'wbtm_dropping_point',
										'value' => array_slice($routes, $sp + 1),
										'compare' => 'IN'
									),
									array(
										'key' => 'wbtm_start_time',
										'value' => $date,
										'compare' => 'LIKE'
									),
									array(
										'key' => 'wbtm_bus_id',
										'value' => $post_id,
										'compare' => '='
									),
									array(
										'key' => 'wbtm_order_status',
										'value' => $seat_booked_status,
										'compare' => 'IN'
									),
									$seat_query,
									$ticket_query
								)
							),
						);
						$q = new WP_Query($args);
						$total_booked = 0;
						if (sizeof($q->posts) > 0) {
							foreach ($q->posts as $booking_id) {
								// Fixed by Shahnur — count one full bus ticket as all covered seats 2026-05-07 01:25 PM
								$booking_mode = WBTM_Global_Function::get_post_info($booking_id, 'wbtm_booking_mode');
								$full_bus_seat_count = (int) WBTM_Global_Function::get_post_info($booking_id, 'wbtm_full_bus_seat_count');
								$total_booked += ($booking_mode === 'full_bus' && $full_bus_seat_count > 0) ? $full_bus_seat_count : 1;
							}
						}
                        wp_reset_postdata();
					}
				}
				return $total_booked;
			}
			public static function query_seat_booked($post_id, $start, $end, $date) {
				$seat_booked=[];
				if ($post_id && $start && $end && $date) {
					$date = gmdate('Y-m-d', strtotime($date));
					// Serve from the request-scoped batch map when one was primed for this route.
					$cached = self::booked_map_seats( $post_id, $start, $end, $date );
					if ( $cached !== null ) {
						return $cached;
					}
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					// Pending orders have already reserved seats during checkout, so they must be
					// reflected in the seat map to keep the frontend and backend views consistent.
					$seat_booked_status = array_filter( array_unique( array_merge( (array) $seat_booked_status, array( 'pending' ) ) ) );
					$routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
					if (sizeof($routes) > 0) {
						$norm = self::wbtm_normalize_route_for_booking_query( $routes, $start, $end );
						$routes = $norm['routes'];
						$sp = $norm['sp'];
						$ep = $norm['ep'];
						if ( $sp === false || $ep === false ) {
							return $seat_booked;
						}
						$args = array(
							'post_type' => 'wbtm_bus_booking',
							'posts_per_page' => -1,
							'fields'     => 'ids',
							'meta_query' => array(
								array(
									'relation' => 'AND',
									array(
										'key' => 'wbtm_boarding_point',
										'value' => array_slice($routes, 0, $ep),
										'compare' => 'IN'
									),
									array(
										'key' => 'wbtm_dropping_point',
										'value' => array_slice($routes, $sp + 1),
										'compare' => 'IN'
									),
									array(
										'key' => 'wbtm_start_time',
										'value' => $date,
										'compare' => 'LIKE'
									),
									array(
										'key' => 'wbtm_bus_id',
										'value' => $post_id,
										'compare' => '='
									),
									array(
										'key' => 'wbtm_order_status',
										'value' => $seat_booked_status,
										'compare' => 'IN'
									)
								)
							),
						);
						$guest_ids= get_posts($args);
						if(sizeof($guest_ids)>0){
							foreach ($guest_ids as $guest_id){
								$seat_name = WBTM_Global_Function::get_post_info($guest_id,'wbtm_seat');
								if (class_exists('WBTM_Seat_Configuration')) {
									$seat_name = WBTM_Seat_Configuration::normalize_saved_seat_value($seat_name);
								}
								if ($seat_name && !(class_exists('WBTM_Seat_Configuration') && WBTM_Seat_Configuration::is_non_seat_item($seat_name))) {
									$seat_booked[] = $seat_name;
								}
							}
						}
					}
				}
				return $seat_booked;
			}
			public static function query_ex_service_sold($post_id, $date, $ex_name) {
				$total_booked = 0;
				if ($post_id && $date && $ex_name) {
					$date = gmdate('Y-m-d', strtotime($date));
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					$args = array(
						'post_type' => 'wbtm_service_booking',
						'posts_per_page' => -1,
						'meta_query' => array(
							array(
								'relation' => 'AND',
								array(
									'key' => 'wbtm_bus_id',
									'compare' => '=',
									'value' => $post_id,
								),
								array(
									'key' => 'wbtm_start_time',
									'compare' => 'LIKE',
									'value' => $date,
								),
								array(
									'key' => 'wbtm_order_status',
									'value' => $seat_booked_status,
									'compare' => 'IN'
								),
							),
						)
					);
					$query = new WP_Query($args);
					//return $query->post_count;
					if ($query->found_posts > 0) {
						while ($query->have_posts()) {
							$query->the_post();
							$id = get_the_id();
							$ex_infos = WBTM_Global_Function::get_post_info($id, 'wbtm_extra_services', []);
							if (sizeof($ex_infos) > 0) {
								foreach ($ex_infos as $ex_info) {
									if (is_array($ex_info) && array_key_exists('name',$ex_info) && $ex_info['name'] == $ex_name) {
										$total_booked += max($ex_info['qty'], 0);
									}
								}
							}
						}
					}
                    wp_reset_postdata();
				}
				return $total_booked;
			}
            public static function query_check_order($order_id)            {
                $args = array(
                    'post_type' => 'wbtm_bus_booking',
                    'posts_per_page' => -1,
                    'paged' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'wbtm_order_id',
                            'value' => $order_id,
                            'compare' => '='
                        )
                    )
                );
                return new WP_Query($args);
            }
		}
		new WBTM_Query();
	}
