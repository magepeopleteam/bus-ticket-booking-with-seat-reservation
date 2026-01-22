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
			public function __construct() {}
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

            public static function query_total_booked($post_id, $start, $end, $date, $ticket_name = '', $seat_name = '') {
				$total_booked = 0;
				if ($post_id && $start && $end && $date) {
					$date = gmdate('Y-m-d', strtotime($date));
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					$routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
					if (sizeof($routes) > 0) {
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
						$sp = array_search($start, $routes);
						$ep = array_search($end, $routes);
						$args = array(
							'post_type' => 'wbtm_bus_booking',
							'posts_per_page' => -1,
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
						$total_booked = $q->found_posts;
                        wp_reset_postdata();

						// If checking for a specific seat, also check if it's reserved in the plan
						if (!empty($seat_name)) {
							$reserved_seats = self::query_reserved_seats($post_id, $date);
							if (in_array($seat_name, $reserved_seats)) {
								$total_booked = max(1, $total_booked);
							}
						}
					}
				}
				return $total_booked;
			}
			public static function query_seat_booked($post_id, $start, $end, $date) {
				$seat_booked=[];
				if ($post_id && $start && $end && $date) {
					$date = gmdate('Y-m-d', strtotime($date));
					$seat_booked_status = WBTM_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					$routes = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
					if (sizeof($routes) > 0) {
						$sp = array_search($start, $routes);
						$ep = array_search($end, $routes);
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
								$seat_booked[]=WBTM_Global_Function::get_post_info($guest_id,'wbtm_seat');
							}
						}
						// Add reserved seats from seat plan
						$reserved_seats = self::query_reserved_seats($post_id, $date);
						$seat_booked = array_merge($seat_booked, $reserved_seats);
					}
				}
				return array_unique($seat_booked);
			}
			public static function query_reserved_seats($post_id, $date = '') {
				$reserved_seats = [];
				$reopened_seats = [];
				if (!empty($date)) {
					$date_key = gmdate('Y-m-d', strtotime($date));
					$all_reopened = WBTM_Global_Function::get_post_info($post_id, '_wbtm_reopened_seats', []);
					$reopened_seats = isset($all_reopened[$date_key]) ? $all_reopened[$date_key] : [];
				}
				
				// Check main seat plan
				$seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
				foreach ($seat_infos as $row) {
					foreach ($row as $key => $val) {
						if (is_string($val) && strpos($key, '_rotation') === false && stripos($val, 'reserved') === 0) {
							$name = $val;
							if (stripos($val, 'reserved:') === 0) {
								$name = substr($val, 9);
							}
							if (empty($name)) {
								$name = $val; // If it was just "reserved:" show full string
							}
							if (!in_array($name, $reopened_seats)) {
								$reserved_seats[] = $name;
							}
						}
					}
				}
				
				// Check upper deck
				$seat_infos_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
				foreach ($seat_infos_dd as $row) {
					foreach ($row as $key => $val) {
						if (is_string($val) && strpos($key, '_rotation') === false && stripos($val, 'reserved') === 0) {
							$name = $val;
							if (stripos($val, 'reserved:') === 0) {
								$name = substr($val, 9);
							}
							if (empty($name)) {
								$name = $val;
							}
							if (!in_array($name, $reopened_seats)) {
								$reserved_seats[] = $name;
							}
						}
					}
				}
				
				// Check cabins
				$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
				if (!empty($cabin_config)) {
					foreach ($cabin_config as $index => $cabin) {
						$cabin_seats = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $index, []);
						foreach ($cabin_seats as $row) {
							foreach ($row as $key => $val) {
								if (is_string($val) && strpos($key, '_rotation') === false && stripos($val, 'reserved') === 0) {
									$name = $val;
									if (stripos($val, 'reserved:') === 0) {
										$name = substr($val, 9);
									}
									if (empty($name)) {
										$name = $val;
									}
									$cabin_seat_id = 'cabin_' . $index . '_' . $name;
									if (!in_array($cabin_seat_id, $reopened_seats) && !in_array($name, $reopened_seats)) {
										$reserved_seats[] = $cabin_seat_id;
										$reserved_seats[] = $name;
									}
								}
							}
						}
					}
				}
				
				return array_unique($reserved_seats);
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