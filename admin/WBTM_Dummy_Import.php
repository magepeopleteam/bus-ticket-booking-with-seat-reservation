<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Dummy_Import')) {
		class WBTM_Dummy_Import {
			public function __construct() {
				add_action('admin_init', array($this, 'dummy_import'), 98);
			}
			public function dummy_import() {
				$dummy_post_inserted = get_option('wbtm_bus_seat_plan_data_input_done', 'no');
				$count_existing_event = wp_count_posts('wbtm_bus')->publish;
				$plugin_active = MP_Global_Function::check_plugin('bus-ticket-booking-with-seat-reservation', 'woocommerce-bus.php');
				if ($count_existing_event == 0 && $plugin_active == 1 && $dummy_post_inserted != 'yes') {
					$dummy_taxonomies = $this->dummy_taxonomy();
					if (array_key_exists('taxonomy', $dummy_taxonomies)) {
						foreach ($dummy_taxonomies['taxonomy'] as $taxonomy => $dummy_taxonomy) {
							if (taxonomy_exists($taxonomy)) {
								$check_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
								if (is_string($check_terms) || sizeof($check_terms) == 0) {
									foreach ($dummy_taxonomy as $taxonomy_data) {
										unset($term);
										$term = wp_insert_term($taxonomy_data['name'], $taxonomy);
										if (array_key_exists('tax_data', $taxonomy_data)) {
											foreach ($taxonomy_data['tax_data'] as $meta_key => $data) {
												update_term_meta($term['term_id'], $meta_key, $data);
											}
										}
									}
								}
							}
						}
					}
					$dummy_cpt = $this->dummy_cpt();
					if (array_key_exists('custom_post', $dummy_cpt)) {
						foreach ($dummy_cpt['custom_post'] as $custom_post => $dummy_post) {
							unset($args);
							$args = array(
								'post_type' => $custom_post,
								'posts_per_page' => -1,
							);
							unset($post);
							$post = new WP_Query($args);
							if ($post->post_count == 0) {
								foreach ($dummy_post as $dummy_data) {
									if (isset($dummy_data['name'])) {
										$args['post_title'] = $dummy_data['name'];
									}
									if (isset($dummy_data['content'])) {
										$args['post_content'] = $dummy_data['content'];
									}
									$args['post_status'] = 'publish';
									$args['post_type'] = $custom_post;
									$post_id = wp_insert_post($args);
									if (array_key_exists('taxonomy_terms', $dummy_data) && count($dummy_data['taxonomy_terms'])) {
										foreach ($dummy_data['taxonomy_terms'] as $taxonomy_term) {
											wp_set_object_terms($post_id, $taxonomy_term['terms'], $taxonomy_term['taxonomy_name'], true);
										}
									}
									if (array_key_exists('post_data', $dummy_data)) {
										foreach ($dummy_data['post_data'] as $meta_key => $data) {
											if ($meta_key == 'feature_image') {
												$url = $data;
												$desc = "The Demo Dummy Image of the bus booking";
												$image = media_sideload_image($url, $post_id, $desc, 'id');
												set_post_thumbnail($post_id, $image);
											}
											else {
												update_post_meta($post_id, $meta_key, $data);
											}
										}
									}
								}
							}
						}
					}
					flush_rewrite_rules();
					update_option('wbtm_bus_seat_plan_data_input_done', 'yes');
				}
			}
			public function dummy_taxonomy(): array {
				return [
					'taxonomy' => [
						'wbtm_bus_cat' => [
							0 => ['name' => 'AC'],
							1 => ['name' => 'Non AC'],
						],
						'wbtm_bus_stops' => [
							0 => [
								'name' => 'Berlin',
								'tax_data' => array(
									'wbtm_bus_routes_name_list' => array(
										0 => array(
											'wbtm_bus_routes_name' => 'Frankfurt'
										),
										1 => array(
											'wbtm_bus_routes_name' => 'Hamburg'
										),
										2 => array(
											'wbtm_bus_routes_name' => 'Paris'
										),
									)
								),
							],
							1 => [
								'name' => 'Frankfurt',
								'tax_data' => array(
									'wbtm_bus_routes_name_list' => array(
										0 => array(
											'wbtm_bus_routes_name' => 'Berlin'
										),
										1 => array(
											'wbtm_bus_routes_name' => 'Hamburg'
										),
										2 => array(
											'wbtm_bus_routes_name' => 'Paris'
										),
									)
								),
							],
							2 => [
								'name' => 'Hamburg',
								'tax_data' => array(
									'wbtm_bus_routes_name_list' => array(
										0 => array(
											'wbtm_bus_routes_name' => 'Berlin'
										),
										1 => array(
											'wbtm_bus_routes_name' => 'Frankfurt'
										),
										2 => array(
											'wbtm_bus_routes_name' => 'Paris'
										),
									)
								),
							],
							3 => [
								'name' => 'Paris',
								'tax_data' => array(
									'wbtm_bus_routes_name_list' => array(
										0 => array(
											'wbtm_bus_routes_name' => 'Berlin'
										),
										1 => array(
											'wbtm_bus_routes_name' => 'Frankfurt'
										),
										2 => array(
											'wbtm_bus_routes_name' => 'Hamburg'
										),
									)
								),
							],
						],
						'wbtm_bus_pickpoint' => [
							0 => ['name' => 'Berlin'],
							1 => ['name' => 'Frankfurt'],
							2 => ['name' => 'Hamburg'],
							3 => ['name' => 'Paris'],
						],
					],
				];
			}
			public function dummy_cpt(): array {
				return [
					'custom_post' => [
						'wbtm_bus' => [
							0 => [
								'name' => 'Flix Bus Service',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'Flixbus-01',
									'wbtm_bus_category' => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '64',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'yes',
									'wbtm_seat_rows_dd' => '8',
									'wbtm_seat_cols_dd' => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd' => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '08:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '09:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '11:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '22:30'],
									],
									// Seat Price
									'wbtm_bus_prices' =>$this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' =>$this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +5 day')),
									'wbtm_repeated_end_date' => date('Y-m-d', strtotime(' +100 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
									'wbtm_off_days' => 'saturday,sunday',
									'wbtm_off_dates' => [
										date('m-d', strtotime(' +15 day')),
										date('m-d', strtotime(' +25 day')),
										date('m-d', strtotime(' +45 day')),
										date('m-d', strtotime(' +55 day')),
										date('m-d', strtotime(' +75 day')),
										date('m-d', strtotime(' +90 day')),
									],
									'wbtm_offday_schedule' => [
										0=>['from_date'=>'01-25','to_date'=>'01-28'],
										1=>['from_date'=>'02-20','to_date'=>'02-25'],
										2=>['from_date'=>'04-10','to_date'=>'04-12'],
										3=>['from_date'=>'08-10','to_date'=>'08-12'],
										4=>['from_date'=>'11-11','to_date'=>'12-12'],
									]
								],
							],
							1 => [
								'name' => 'Mega Bus Express',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'Megabus-01',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									//price & Routing
									'wbtm_route_direction' => ['Berlin', 'Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_bus_bp_stops' => ['Berlin', 'Hamburg', 'Frankfurt'],
									'wbtm_bus_next_stops' => ['Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_route_info' => [
										0 => ['place' => 'Berlin', 'type' => 'bp', 'time' => '08:00'],
										1 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '09:30'],
										2 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00'],
										3 => ['place' => 'Paris', 'type' => 'dp', 'time' => '22:30'],
									],
									// Seat Price
									'wbtm_bus_prices' =>$this->seat_price_return(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +2 day')),
									'wbtm_repeated_end_date' => date('Y-m-d', strtotime(' +150 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
									'wbtm_off_days' => 'saturday,sunday',
									'wbtm_off_dates' => [
										date('m-d', strtotime(' +10 day')),
										date('m-d', strtotime(' +20 day')),
										date('m-d', strtotime(' +30 day')),
										date('m-d', strtotime(' +40 day')),
										date('m-d', strtotime(' +45 day')),
										date('m-d', strtotime(' +110 day')),
									]
								],
							],
							2 => [
								'name' => 'BYD Express',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'Bydbus-01',
									'wbtm_bus_category' => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '64',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'yes',
									'wbtm_seat_rows_dd' => '8',
									'wbtm_seat_cols_dd' => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd' => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '11:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '12:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '01:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '03:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
									'wbtm_repeated_end_date' => date('Y-m-d', strtotime(' +100 day')),
									'wbtm_repeated_after' => '3',
									'wbtm_active_days' => '90',
									'wbtm_off_days' => '',
									'wbtm_off_dates' => [
										date('m-d', strtotime(' +2 day')),
										date('m-d', strtotime(' +7 day')),
										date('m-d', strtotime(' +30 day')),
										date('m-d', strtotime(' +45 day')),
									]
								],
							],
							3 => [
								'name' => 'RED Coach',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'Redbus-01',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '64',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'yes',
									'wbtm_seat_rows_dd' => '8',
									'wbtm_seat_cols_dd' => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd' => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '11:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '12:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '01:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '03:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'yes',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
								],
							],
							4 => [
								'name' => 'Bonanza Bus',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'Bonanzabus-01',
									'wbtm_bus_category' => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									//price & Routing
									'wbtm_route_direction' => ['Berlin', 'Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_bus_bp_stops' => ['Berlin', 'Hamburg', 'Frankfurt'],
									'wbtm_bus_next_stops' => ['Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_route_info' => [
										0 => ['place' => 'Berlin', 'type' => 'bp', 'time' => '08:00'],
										1 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '09:30'],
										2 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00'],
										3 => ['place' => 'Paris', 'type' => 'dp', 'time' => '22:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price_return(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
								],
							],
							5 => [
								'name' => 'Berlin Linien Bus',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'BerlinLinien-Bus-01',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									'wbtm_seat_rows_dd' => '8',
									'wbtm_seat_cols_dd' => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd' => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction' => ['Berlin', 'Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_bus_bp_stops' => ['Berlin', 'Hamburg', 'Frankfurt'],
									'wbtm_bus_next_stops' => ['Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_route_info' => [
										0 => ['place' => 'Berlin', 'type' => 'bp', 'time' => '08:00'],
										1 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '09:30'],
										2 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00'],
										3 => ['place' => 'Paris', 'type' => 'dp', 'time' => '22:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price_return(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +2 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
									'wbtm_off_days' => 'saturday,sunday',
									'wbtm_off_dates' => [
										date('m-d', strtotime(' +10 day')),
										date('m-d', strtotime(' +20 day')),
										date('m-d', strtotime(' +30 day')),
										date('m-d', strtotime(' +40 day')),
										date('m-d', strtotime(' +45 day')),
										date('m-d', strtotime(' +110 day')),
									],
								],
							],
							6 => [
								'name' => 'Royal Bus',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'royal_706',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '09:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '12:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '01:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
								],
							],
							7 => [
								'name' => 'Bold Bus',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'bold_706',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									'wbtm_seat_rows_dd' => '8',
									'wbtm_seat_cols_dd' => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd' => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '09:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '12:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '01:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'yes',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
								],
							],
							8 => [
								'name' => 'Eco Move',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'eco_706',
									'wbtm_bus_category' => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									//price & Routing
									'wbtm_route_direction' => ['Paris', 'Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_bus_bp_stops' => ['Paris', 'Frankfurt', 'Hamburg'],
									'wbtm_bus_next_stops' => ['Frankfurt', 'Hamburg', 'Berlin'],
									'wbtm_route_info' => [
										0 => ['place' => 'Paris', 'type' => 'bp', 'time' => '09:00'],
										1 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30'],
										2 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '12:00'],
										3 => ['place' => 'Berlin', 'type' => 'dp', 'time' => '01:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' =>$this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +1 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
								],
							],
							9 => [
								'name' => 'Badger Bus Service',
								'post_data' => [
									//general
									'wbtm_bus_no' => 'badger-01',
									'wbtm_bus_category' => 'AC',
									//lower seat
									'wbtm_seat_type_conf' => 'wbtm_seat_plan',
									'driver_seat_position' => 'driver_left',
									'wbtm_seat_rows' => '8',
									'wbtm_seat_cols' => '5',
									'wbtm_get_total_seat' => '32',
									'wbtm_bus_seats_info' => $this->seat_info(),
									//upper desk
									'show_upper_desk' => 'no',
									//price & Routing
									'wbtm_route_direction' => ['Berlin', 'Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_bus_bp_stops' => ['Berlin', 'Hamburg', 'Frankfurt'],
									'wbtm_bus_next_stops' => ['Hamburg', 'Frankfurt', 'Paris'],
									'wbtm_route_info' => [
										0 => ['place' => 'Berlin', 'type' => 'bp', 'time' => '08:00'],
										1 => ['place' => 'Hamburg', 'type' => 'both', 'time' => '09:30'],
										2 => ['place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00'],
										3 => ['place' => 'Paris', 'type' => 'dp', 'time' => '22:30'],
									],
									// Seat Price
									'wbtm_bus_prices' => $this->seat_price_return(),
									//Extra service
									'show_extra_service' => 'yes',
									'wbtm_extra_services' => $this->ex_service(),
									// Pickup Points
									'show_pickup_point' => 'no',
									'wbtm_pickup_point' => [],
									// date settings
									'show_operational_on_day' => 'no',
									'wbtm_particular_dates' => ['01-01','02-02','03-03','04-04','05-05','06-06','07-07','08-08','09-09','10-10','11-11','12-12'],
									'wbtm_repeated_start_date' =>date('Y-m-d', strtotime(' +5 day')),
									'wbtm_repeated_after' => '1',
									'wbtm_active_days' => '90',
									'wbtm_off_days' => 'saturday,sunday',
									'wbtm_off_dates' => [
										date('m-d', strtotime(' +15 day')),
										date('m-d', strtotime(' +25 day')),
										date('m-d', strtotime(' +45 day')),
										date('m-d', strtotime(' +55 day')),
										date('m-d', strtotime(' +75 day')),
										date('m-d', strtotime(' +90 day')),
									],
								],
							],
						],
					],
				];
			}
			public function seat($args = []): array {
				$seat = [];
				if (sizeof($args) > 0) {
					$count = 1;
					foreach ($args as $arg) {
						$seat['seat' . $count] = $arg;
						$count++;
					}
				}
				return $seat;
			}
			public function dd_seat($args = []): array {
				$seat = [];
				if (sizeof($args) > 0) {
					$count = 1;
					foreach ($args as $arg) {
						$seat['dd_seat' . $count] = $arg;
						$count++;
					}
				}
				return $seat;
			}
			public function price($args = []): array {
				$price_info = [];
				if (sizeof($args) > 0) {
					$price_info['wbtm_bus_bp_price_stop'] = $args[0];
					$price_info['wbtm_bus_dp_price_stop'] = $args[1];
					$price_info['wbtm_bus_price'] = $args[2];
					$price_info['wbtm_bus_child_price'] = $args[3];
					$price_info['wbtm_bus_infant_price'] = $args[4];
				}
				return $price_info;
			}
			public function seat_info(): array {
				return array(
					0 => $this->seat(['A1', 'A2', '', 'A3', 'A4']),
					1 => $this->seat(['B1', 'B2', '', 'B3', 'B4']),
					2 => $this->seat(['C1', 'C2', '', 'C3', 'C4']),
					3 => $this->seat(['D1', 'D2', '', 'D3', 'D4']),
					4 => $this->seat(['E1', 'E2', '', 'E3', 'E4']),
					5 => $this->seat(['F1', 'F2', '', 'F3', 'F4']),
					6 => $this->seat(['G1', 'G2', '', 'G3', 'G4']),
					7 => $this->seat(['H1', 'H2', '', 'H3', 'H4']),
				);
			}
			public function seat_price(): array {
				return array(
					0 => $this->price(['Paris', 'Frankfurt', 10, '', '']),
					1 => $this->price(['Paris', 'Hamburg', 15, '', '']),
					2 => $this->price(['Paris', 'Berlin', 20, '', '']),
					3 => $this->price(['Frankfurt', 'Hamburg', 7, '', '']),
					4 => $this->price(['Frankfurt', 'Berlin', 12, '', '']),
					5 => $this->price(['Hamburg', 'Berlin', 8, '', ''])
				);
			}
			public function seat_price_return(): array {
				return array(
					0 => $this->price(['Berlin', 'Hamburg', 10, '', '']),
					1 => $this->price(['Berlin', 'Frankfurt', 15, '', '']),
					2 => $this->price(['Berlin', 'Paris', 20, '', '']),
					3 => $this->price(['Hamburg', 'Frankfurt', 7, '', '']),
					4 => $this->price(['Hamburg', 'Paris', 12, '', '']),
					5 => $this->price(['Frankfurt', 'Paris', 8, '', ''])
				);
			}
			public function seat_info_dd(): array {
				return array(
					0 => $this->dd_seat(['S1', 'S2', '', 'S3', 'S4']),
					1 => $this->dd_seat(['T1', 'T2', '', 'T3', 'T4']),
					2 => $this->dd_seat(['U1', 'U2', '', 'U3', 'U4']),
					3 => $this->dd_seat(['V1', 'V2', '', 'V3', 'V4']),
					4 => $this->dd_seat(['W1', 'W2', '', 'W3', 'W4']),
					5 => $this->dd_seat(['X1', 'X2', '', 'X3', 'X4']),
					6 => $this->dd_seat(['Y1', 'Y2', '', 'Y3', 'Y4']),
					7 => $this->dd_seat(['Z1', 'Z2', '', 'Z3', 'Z4']),
				);
			}
			public function ex_service(): array {
				return [
					0 => ['option_name' => 'Welcome Drink', 'option_price' => '50', 'option_qty' => '500', 'option_qty_type' => 'inputbox',],
					1 => ['option_name' => 'Cap', 'option_price' => '70', 'option_qty' => '500', 'option_qty_type' => 'inputbox',],
				];
			}
		}
	}