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
			public static function get_bus_id($start='', $end='',$cat='') {
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
				$cat_query = !empty($cat) ? array(
					'key' => 'wbtm_bus_category',
					'value' => $cat,
					'compare' => '=',
				) : '';
				$args = array(
					'post_type' => array('wbtm_bus'),
					'posts_per_page' => -1,
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
				wp_reset_query();
				return $bus_ids;
			}
			public static function query_total_booked($post_id, $start, $end, $date, $ticket_name = '', $seat_name = '') {
				$total_booked = 0;
				if ($post_id && $start && $end && $date) {
					$date = date('Y-m-d', strtotime($date));
					$seat_booked_status = MP_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					$routes = MP_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
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
                        wp_reset_query();
					}
				}
				return $total_booked;
			}
			public static function query_seat_booked($post_id, $start, $end, $date) {
				$seat_booked=[];
				if ($post_id && $start && $end && $date) {
					$date = date('Y-m-d', strtotime($date));
					$seat_booked_status = MP_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
					$routes = MP_Global_Function::get_post_info($post_id, 'wbtm_route_direction', []);
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
								$seat_booked[]=MP_Global_Function::get_post_info($guest_id,'wbtm_seat');
							}
						}
					}
				}
				return $seat_booked;
			}
			public static function query_ex_service_sold($post_id, $date, $ex_name) {
				$total_booked = 0;
				if ($post_id && $date && $ex_name) {
					$date = date('Y-m-d', strtotime($date));
					$seat_booked_status = MP_Global_Function::get_settings('wbtm_general_settings', 'set_book_status', array('processing', 'completed'));
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
							$ex_infos = MP_Global_Function::get_post_info($id, 'wbtm_extra_services', []);
							if (sizeof($ex_infos) > 0) {
								foreach ($ex_infos as $ex_info) {
									if (is_array($ex_info) && array_key_exists('name',$ex_info) && $ex_info['name'] == $ex_name) {
										$total_booked += max($ex_info['qty'], 0);
									}
								}
							}
						}
					}
                    wp_reset_query();
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