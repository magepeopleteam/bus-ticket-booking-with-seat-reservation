<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

if (!class_exists('WBTM_Dummy_Import')) {
    class WBTM_Dummy_Import
    {
        public function __construct()
        {
            //update_option('wbtm_bus_data_update_01', 'completed');
                        
            add_action('deactivate_plugin', array($this, 'update_option'), 98);
            add_action('activated_plugin', array($this, 'update_option'), 98);
            add_action('admin_init', array($this, 'dummy_import'), 98);

        }

        
        function update_option() 
        {
            update_option('wbtm_bus_seat_plan_data_input_done', 'no');           
        }

        public function test()
        {


        }

        public static function check_plugin($plugin_dir_name, $plugin_file): int
        {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin_dir = ABSPATH . 'wp-content/plugins/' . $plugin_dir_name;
            if (is_plugin_active($plugin_dir_name . '/' . $plugin_file)) {
                return 1;
            } elseif (is_dir($plugin_dir)) {
                return 2;
            } else {
                return 0;
            }
        }
	
		function craete_pages()
		{
				if (empty(wbtm_get_page_by_slug('events-list-style'))) {			
				$post_details = array(
					'post_title'    => 'Events – List Style',
					'post_content'  => '[event-list show="10" style="list" pagination="yes"]',
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_type' 	  => 'page'
				);		   
				wp_insert_post( $post_details );
				}

				if (empty(wbtm_get_page_by_slug('events-grid-style'))) {
				$post_details = array(
					'post_title'    => 'Events – Grid Style',
					'post_content'  => "[event-list show='10' style='grid']",
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_type' 	  => 'page'
				);
				wp_insert_post( $post_details );
				}

				if (empty(wbtm_get_page_by_slug('events-list-style-with-search-box'))) {

				$post_details = array(
					'post_title'    => 'Events – List Style with Search Box',
					'post_content'  => "[event-list column=4 search-filter='yes']",
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_type' 	  => 'page'
				);
				wp_insert_post( $post_details );	
				}	   

		}

        public function dummy_import()
        {
            
            $dummy_post_inserted = get_option('wbtm_bus_seat_plan_data_input_done','no');
            $count_existing_event = wp_count_posts('wbtm_bus')->publish;
            
            $plugin_active = self::check_plugin('bus-ticket-booking-with-seat-reservation', 'woocommerce-bus.php');
			
            if ($count_existing_event == 0 && $plugin_active == 1 && $dummy_post_inserted != 'yes') {

                $dummy_taxonomies = $this->dummy_taxonomy();

                if(array_key_exists('taxonomy', $dummy_taxonomies))
                {
                    foreach ($dummy_taxonomies['taxonomy'] as $taxonomy => $dummy_taxonomy) 
                    {
                        
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

                    //echo "<pre>";print_r($dummy_taxonomies);exit;

                }

                $dummy_cpt = $this->dummy_cpt();

                if(array_key_exists('custom_post', $dummy_cpt))
                {
                    foreach ($dummy_cpt['custom_post'] as $custom_post => $dummy_post) 
                    {
                        unset($args);
                        $args = array(
                            'post_type' => $custom_post,
                            'posts_per_page' => -1,
                        );

                        unset($post);
                        $post = new WP_Query($args);

                        if ($post->post_count == 0) {

                            foreach ($dummy_post as $dummy_data) {
                                $title = $dummy_data['name'];
                                $content = $dummy_data['content'];
                                $post_id = wp_insert_post([
                                    'post_title' => $title,
                                    'post_content' => $content,
                                    'post_status' => 'publish',
                                    'post_type' => $custom_post,
                                ]);

                                if (array_key_exists('taxonomy_terms', $dummy_data) && count($dummy_data['taxonomy_terms'])) 
                                {
                                    foreach ($dummy_data['taxonomy_terms'] as $taxonomy_term) 
                                    {
                                        wp_set_object_terms( $post_id, $taxonomy_term['terms'], $taxonomy_term['taxonomy_name'], true );
                                    }
                                }

                                if (array_key_exists('post_data', $dummy_data)) {
                                    foreach ($dummy_data['post_data'] as $meta_key => $data) {
                                        if ($meta_key == 'feature_image') {

                                            $url = $data;
                                            $desc = "The Demo Dummy Image of the bus booking";
                                            $image = media_sideload_image($url, $post_id, $desc, 'id');
                                            set_post_thumbnail($post_id, $image);

                                        } else {

                                            update_post_meta($post_id, $meta_key, $data);

                                        }

                                    }
                                }

                            }
                        }
                    }
                }
				//$this->craete_pages();
                update_option('wbtm_bus_seat_plan_data_input_done', 'yes');
            }
        }

        public function dummy_taxonomy(): array
        {
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

        public function dummy_cpt(): array
        {
            return [
                'custom_post' => [
                    'wbtm_bus' => [
                        0 => [
                            'name' => 'Flix Bus Service',
                            'content' => '

                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                            
                            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.
                            ',
                            'taxonomy_terms' => [
                                0 => array(
                                    'taxonomy_name' => 'wbtm_bus_cat',
                                    'terms' => array(
                                        0 => 'Non AC',
                                    )
                                ),
                                1 => array(
                                    'taxonomy_name' => 'wbtm_bus_stops',
                                    'terms' => array(
                                        0 => 'Berlin',
                                        1 => 'Frankfurt',
                                        2 => 'Hamburg',
                                        3 => 'Paris',
                                    )
                                ),
                                2 => array(
                                    'taxonomy_name' => 'wbtm_bus_pickpoint',
                                    'terms' => array(
                                        0=>'Berlin',
                                        1=>'Frankfurt',
                                    )
                                ),                                
                            ],
                            'post_data' => [
                                //configuration
                                'feature_image' => 'https://img.freepik.com/free-photo/young-man-taking-city-bus_23-2148958086.jpg',
                                
                                'wbtm_bus_no' => 'Flixbus-01',
                                'wbtm_total_seat' => '16',
                                'wbtm_seat_type_conf' => 'wbtm_seat_plan',
                                'zero_price_allow' => 'no',
                                'show_boarding_time' => 'yes',
                                'show_dropping_time' => 'yes',

                                'driver_seat_position' => 'driver_left',
                                'wbtm_seat_cols' => '4',
                                'wbtm_seat_rows' => '8',
                                'wbtm_bus_seats_info' => array(
                                    0 => array(
                                        'seat1' => 'A1',
                                        'seat2' => 'A2',
                                        'seat3' => 'A3',
                                        'seat4' => 'A4',
                                    ),
                                    1 => array(
                                        'seat1' => 'B1',
                                        'seat2' => 'B2',
                                        'seat3' => 'B3',
                                        'seat4' => 'B4',
                                    ),
                                    2 => array(
                                        'seat1' => 'C1',
                                        'seat2' => 'C2',
                                        'seat3' => 'C3',
                                        'seat4' => 'C4',
                                    ),
                                    3 => array(
                                        'seat1' => 'D1',
                                        'seat2' => 'D2',
                                        'seat3' => 'D3',
                                        'seat4' => 'D4',
                                    ),
                                    4 => array(
                                        'seat1' => 'E1',
                                        'seat2' => 'E2',
                                        'seat3' => 'E3',
                                        'seat4' => 'E4',
                                    ),
                                    5 => array(
                                        'seat1' => 'F1',
                                        'seat2' => 'F2',
                                        'seat3' => 'F3',
                                        'seat4' => 'F4',
                                    ),
                                    6 => array(
                                        'seat1' => 'G1',
                                        'seat2' => 'G2',
                                        'seat3' => 'G3',
                                        'seat4' => 'G4',
                                    ),
                                    7 => array(
                                        'seat1' => 'H1',
                                        'seat2' => 'H2',
                                        'seat3' => 'H3',
                                        'seat4' => 'H4',
                                    ),
                                ),
                                'show_upper_desk' => 'yes',
                                'wbtm_seat_cols_dd' => '4',
                                'wbtm_seat_rows_dd' => '8',
                                'wbtm_bus_seats_info_dd' => array(
                                    0 => array(
                                        'dd_seat1' => 'S1',
                                        'dd_seat2' => 'S2',
                                        'dd_seat3' => 'S3',
                                        'dd_seat4' => 'S4',
                                    ),
                                    1 => array(
                                        'dd_seat1' => 'T1',
                                        'dd_seat2' => 'T2',
                                        'dd_seat3' => 'T3',
                                        'dd_seat4' => 'T4',
                                    ),
                                    2 => array(
                                        'dd_seat1' => 'U1',
                                        'dd_seat2' => 'U2',
                                        'dd_seat3' => 'U3',
                                        'dd_seat4' => 'U4',
                                    ),
                                    3 => array(
                                        'dd_seat1' => 'V1',
                                        'dd_seat2' => 'V2',
                                        'dd_seat3' => 'V3',
                                        'dd_seat4' => 'V4',
                                    ),
                                    4 => array(
                                        'dd_seat1' => 'W1',
                                        'dd_seat2' => 'W2',
                                        'dd_seat3' => 'W3',
                                        'dd_seat4' => 'W4',
                                    ),
                                    5 => array(
                                        'dd_seat1' => 'X1',
                                        'dd_seat2' => 'X2',
                                        'dd_seat3' => 'X3',
                                        'dd_seat4' => 'X4',
                                    ),
                                    6 => array(
                                        'dd_seat1' => 'Y1',
                                        'dd_seat2' => 'Y2',
                                        'dd_seat3' => 'Y3',
                                        'dd_seat4' => 'Y4',
                                    ),
                                    7 => array(
                                        'dd_seat1' => 'Z1',
                                        'dd_seat2' => 'Z2',
                                        'dd_seat3' => 'Z3',
                                        'dd_seat4' => 'Z4',
                                    ),
                                ),
                                'wbtm_seat_dd_price_parcent' => '',

                                //Routing
                                'wbtm_bus_bp_stops' => array(
                                    0 => array(
                                        'wbtm_bus_bp_stops_name' => 'Paris',
                                        'wbtm_bus_bp_start_time' => '12:00',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_stops_name' => 'Frankfurt',
                                        'wbtm_bus_bp_start_time' => '12:20',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_stops_name' => 'Hamburg',
                                        'wbtm_bus_bp_start_time' => '12:30',
                                    ),
                                ),
                                'wbtm_bus_next_stops' => array(
                                    0 => array(
                                        'wbtm_bus_next_stops_name' => 'Frankfurt',
                                        'wbtm_bus_next_end_time' => '16:10',
                                    ),
                                    1 => array(
                                        'wbtm_bus_next_stops_name' => 'Hamburg',
                                        'wbtm_bus_next_end_time' => '19:10',
                                    ),
                                    2 => array(
                                        'wbtm_bus_next_stops_name' => 'Berlin',
                                        'wbtm_bus_next_end_time' => '22:30',
                                    ),
                                ),
                                'wbtm_route_summary' => array(),
                                'mtsa_subscription_route_type' => array(),
                                // Seat Price
                                'wbtm_bus_prices' => array(
                                    0 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '20',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    3 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '25',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    4 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    5 => array(
                                        'wbtm_bus_bp_price_stop' => 'Hamburg',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                ),
                                'show_extra_service' => 'yes',
                                'mep_events_extra_prices' => array(
                                    0 => array(
                                        'option_name' => 'Welcome Drink',
                                        'option_price' => '50',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                    1 => array(
                                        'option_name' => 'Cap',
                                        'option_price' => '70',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                ),
                                // Pickup Points
                                'show_pickup_point' => 'no',
                                // Onday & Offday
                                'show_operational_on_day' => 'no',
                                'show_off_day' => 'yes',
                                'weekly_offday' => '',
                                'wbtm_od_start' => '',
                                'wbtm_od_end' => '',
                                'wbtm_bus_on_date' => '',
                                'show_boarding_points' => '',
                                'show_off_day' => 'no',
                            ],
                        ],
                        1 => [
                            'name' => 'Mega Bus Express',
                            'content' => '

                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                            
                            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.
                            ',
                            'taxonomy_terms' => [
                                0 => array(
                                    'taxonomy_name' => 'wbtm_bus_cat',
                                    'terms' => array(
                                        0 => 'AC',
                                    )
                                ),
                                1 => array(
                                    'taxonomy_name' => 'wbtm_bus_stops',
                                    'terms' => array(
                                        0 => 'Berlin',
                                        1 => 'Frankfurt',
                                        2 => 'Hamburg',
                                        3 => 'Paris',
                                    )
                                ),
                                2 => array(
                                    'taxonomy_name' => 'wbtm_bus_pickpoint',
                                    'terms' => array(
                                        0=>'Berlin',
                                        1=>'Frankfurt',
                                    )
                                ),                                
                            ],
                            'post_data' => [
                                //configuration
                                'feature_image' => 'https://img.freepik.com/free-photo/central-hong-kong-jan-10-2016-traffic-scene-tram-hong-kong_1137-317.jpg',
                                
                                'wbtm_bus_no' => 'Megabus-01',
                                'wbtm_total_seat' => '16',
                                'wbtm_seat_type_conf' => 'wbtm_seat_plan',
                                'zero_price_allow' => 'no',
                                'show_boarding_time' => 'yes',
                                'show_dropping_time' => 'yes',

                                'driver_seat_position' => 'driver_left',
                                'wbtm_seat_cols' => '4',
                                'wbtm_seat_rows' => '8',
                                'wbtm_bus_seats_info' => array(
                                    0 => array(
                                        'seat1' => 'A1',
                                        'seat2' => 'A2',
                                        'seat3' => 'A3',
                                        'seat4' => 'A4',
                                    ),
                                    1 => array(
                                        'seat1' => 'B1',
                                        'seat2' => 'B2',
                                        'seat3' => 'B3',
                                        'seat4' => 'B4',
                                    ),
                                    2 => array(
                                        'seat1' => 'C1',
                                        'seat2' => 'C2',
                                        'seat3' => 'C3',
                                        'seat4' => 'C4',
                                    ),
                                    3 => array(
                                        'seat1' => 'D1',
                                        'seat2' => 'D2',
                                        'seat3' => 'D3',
                                        'seat4' => 'D4',
                                    ),
                                    4 => array(
                                        'seat1' => 'E1',
                                        'seat2' => 'E2',
                                        'seat3' => 'E3',
                                        'seat4' => 'E4',
                                    ),
                                    5 => array(
                                        'seat1' => 'F1',
                                        'seat2' => 'F2',
                                        'seat3' => 'F3',
                                        'seat4' => 'F4',
                                    ),
                                    6 => array(
                                        'seat1' => 'G1',
                                        'seat2' => 'G2',
                                        'seat3' => 'G3',
                                        'seat4' => 'G4',
                                    ),
                                    7 => array(
                                        'seat1' => 'H1',
                                        'seat2' => 'H2',
                                        'seat3' => 'H3',
                                        'seat4' => 'H4',
                                    ),
                                ),
                                'show_upper_desk' => 'yes',
                                'wbtm_seat_cols_dd' => '4',
                                'wbtm_seat_rows_dd' => '8',
                                'wbtm_bus_seats_info_dd' => array(
                                    0 => array(
                                        'dd_seat1' => 'S1',
                                        'dd_seat2' => 'S2',
                                        'dd_seat3' => 'S3',
                                        'dd_seat4' => 'S4',
                                    ),
                                    1 => array(
                                        'dd_seat1' => 'T1',
                                        'dd_seat2' => 'T2',
                                        'dd_seat3' => 'T3',
                                        'dd_seat4' => 'T4',
                                    ),
                                    2 => array(
                                        'dd_seat1' => 'U1',
                                        'dd_seat2' => 'U2',
                                        'dd_seat3' => 'U3',
                                        'dd_seat4' => 'U4',
                                    ),
                                    3 => array(
                                        'dd_seat1' => 'V1',
                                        'dd_seat2' => 'V2',
                                        'dd_seat3' => 'V3',
                                        'dd_seat4' => 'V4',
                                    ),
                                    4 => array(
                                        'dd_seat1' => 'W1',
                                        'dd_seat2' => 'W2',
                                        'dd_seat3' => 'W3',
                                        'dd_seat4' => 'W4',
                                    ),
                                    5 => array(
                                        'dd_seat1' => 'X1',
                                        'dd_seat2' => 'X2',
                                        'dd_seat3' => 'X3',
                                        'dd_seat4' => 'X4',
                                    ),
                                    6 => array(
                                        'dd_seat1' => 'Y1',
                                        'dd_seat2' => 'Y2',
                                        'dd_seat3' => 'Y3',
                                        'dd_seat4' => 'Y4',
                                    ),
                                    7 => array(
                                        'dd_seat1' => 'Z1',
                                        'dd_seat2' => 'Z2',
                                        'dd_seat3' => 'Z3',
                                        'dd_seat4' => 'Z4',
                                    ),
                                ),
                                'wbtm_seat_dd_price_parcent' => '',

                                //Routing
                                'wbtm_bus_bp_stops' => array(
                                    0 => array(
                                        'wbtm_bus_bp_stops_name' => 'Hamburg',
                                        'wbtm_bus_bp_start_time' => '12:30',
                                    ),
                                ),
                                'wbtm_bus_next_stops' => array(
                                    2 => array(
                                        'wbtm_bus_next_stops_name' => 'Berlin',
                                        'wbtm_bus_next_end_time' => '22:30',
                                    ),
                                ),
                                'wbtm_route_summary' => array(),
                                'mtsa_subscription_route_type' => array(),
                                // Seat Price
                                'wbtm_bus_prices' => array(
                                    0 => array(
                                        'wbtm_bus_bp_price_stop' => 'Hamburg',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                ),
                                'show_extra_service' => 'yes',
                                'mep_events_extra_prices' => array(
                                    0 => array(
                                        'option_name' => 'Welcome Drink',
                                        'option_price' => '50',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                    1 => array(
                                        'option_name' => 'Cap',
                                        'option_price' => '70',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                ),
                                // Pickup Points
                                'show_pickup_point' => 'no',
                                // Onday & Offday
                                'show_operational_on_day' => 'no',
                                'show_off_day' => 'yes',
                                'weekly_offday' => '',
                                'wbtm_od_start' => '',
                                'wbtm_od_end' => '',
                                'wbtm_bus_on_date' => '',
                                'show_boarding_points' => '',
                                'show_off_day' => 'no',
                            ],
                        ],
                        2 => [
                            'name' => 'BYD Express',
                            'content' => '

                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                            
                            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.
                            ',
                            'taxonomy_terms' => [
                                0 => array(
                                    'taxonomy_name' => 'wbtm_bus_cat',
                                    'terms' => array(
                                        0 => 'Non AC',
                                    )
                                ),
                                1 => array(
                                    'taxonomy_name' => 'wbtm_bus_stops',
                                    'terms' => array(
                                        0 => 'Berlin',
                                        1 => 'Frankfurt',
                                        2 => 'Hamburg',
                                        3 => 'Paris',
                                    )
                                ),
                                2 => array(
                                    'taxonomy_name' => 'wbtm_bus_pickpoint',
                                    'terms' => array(
                                        0=>'Berlin',
                                        1=>'Frankfurt',
                                    )
                                ),                                
                            ],
                            'post_data' => [
                                //configuration
                                'feature_image' => 'https://img.freepik.com/premium-photo/white-bus-modern-park-city_100800-5400.jpg',
                                
                                'wbtm_bus_no' => 'Bydbus-01',
                                'wbtm_total_seat' => '16',
                                'wbtm_seat_type_conf' => 'wbtm_seat_plan',
                                'zero_price_allow' => 'no',
                                'show_boarding_time' => 'yes',
                                'show_dropping_time' => 'yes',

                                'driver_seat_position' => 'driver_left',
                                'wbtm_seat_cols' => '4',
                                'wbtm_seat_rows' => '8',
                                'wbtm_bus_seats_info' => array(
                                    0 => array(
                                        'seat1' => 'A1',
                                        'seat2' => 'A2',
                                        'seat3' => 'A3',
                                        'seat4' => 'A4',
                                    ),
                                    1 => array(
                                        'seat1' => 'B1',
                                        'seat2' => 'B2',
                                        'seat3' => 'B3',
                                        'seat4' => 'B4',
                                    ),
                                    2 => array(
                                        'seat1' => 'C1',
                                        'seat2' => 'C2',
                                        'seat3' => 'C3',
                                        'seat4' => 'C4',
                                    ),
                                    3 => array(
                                        'seat1' => 'D1',
                                        'seat2' => 'D2',
                                        'seat3' => 'D3',
                                        'seat4' => 'D4',
                                    ),
                                    4 => array(
                                        'seat1' => 'E1',
                                        'seat2' => 'E2',
                                        'seat3' => 'E3',
                                        'seat4' => 'E4',
                                    ),
                                    5 => array(
                                        'seat1' => 'F1',
                                        'seat2' => 'F2',
                                        'seat3' => 'F3',
                                        'seat4' => 'F4',
                                    ),
                                    6 => array(
                                        'seat1' => 'G1',
                                        'seat2' => 'G2',
                                        'seat3' => 'G3',
                                        'seat4' => 'G4',
                                    ),
                                    7 => array(
                                        'seat1' => 'H1',
                                        'seat2' => 'H2',
                                        'seat3' => 'H3',
                                        'seat4' => 'H4',
                                    ),
                                ),
                                'show_upper_desk' => 'yes',
                                'wbtm_seat_cols_dd' => '4',
                                'wbtm_seat_rows_dd' => '8',
                                'wbtm_bus_seats_info_dd' => array(
                                    0 => array(
                                        'dd_seat1' => 'S1',
                                        'dd_seat2' => 'S2',
                                        'dd_seat3' => 'S3',
                                        'dd_seat4' => 'S4',
                                    ),
                                    1 => array(
                                        'dd_seat1' => 'T1',
                                        'dd_seat2' => 'T2',
                                        'dd_seat3' => 'T3',
                                        'dd_seat4' => 'T4',
                                    ),
                                    2 => array(
                                        'dd_seat1' => 'U1',
                                        'dd_seat2' => 'U2',
                                        'dd_seat3' => 'U3',
                                        'dd_seat4' => 'U4',
                                    ),
                                    3 => array(
                                        'dd_seat1' => 'V1',
                                        'dd_seat2' => 'V2',
                                        'dd_seat3' => 'V3',
                                        'dd_seat4' => 'V4',
                                    ),
                                    4 => array(
                                        'dd_seat1' => 'W1',
                                        'dd_seat2' => 'W2',
                                        'dd_seat3' => 'W3',
                                        'dd_seat4' => 'W4',
                                    ),
                                    5 => array(
                                        'dd_seat1' => 'X1',
                                        'dd_seat2' => 'X2',
                                        'dd_seat3' => 'X3',
                                        'dd_seat4' => 'X4',
                                    ),
                                    6 => array(
                                        'dd_seat1' => 'Y1',
                                        'dd_seat2' => 'Y2',
                                        'dd_seat3' => 'Y3',
                                        'dd_seat4' => 'Y4',
                                    ),
                                    7 => array(
                                        'dd_seat1' => 'Z1',
                                        'dd_seat2' => 'Z2',
                                        'dd_seat3' => 'Z3',
                                        'dd_seat4' => 'Z4',
                                    ),
                                ),
                                'wbtm_seat_dd_price_parcent' => '',

                                //Routing
                                'wbtm_bus_bp_stops' => array(
                                    0 => array(
                                        'wbtm_bus_bp_stops_name' => 'Paris',
                                        'wbtm_bus_bp_start_time' => '12:00',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_stops_name' => 'Frankfurt',
                                        'wbtm_bus_bp_start_time' => '12:20',
                                    ),
                                ),
                                'wbtm_bus_next_stops' => array(
                                    0 => array(
                                        'wbtm_bus_next_stops_name' => 'Hamburg',
                                        'wbtm_bus_next_end_time' => '19:10',
                                    ),
                                    1 => array(
                                        'wbtm_bus_next_stops_name' => 'Berlin',
                                        'wbtm_bus_next_end_time' => '22:30',
                                    ),
                                ),
                                'wbtm_route_summary' => array(),
                                'mtsa_subscription_route_type' => array(),
                                // Seat Price
                                'wbtm_bus_prices' => array(
                                    0 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '20',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    3 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '25',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    4 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    5 => array(
                                        'wbtm_bus_bp_price_stop' => 'Hamburg',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                ),
                                'show_extra_service' => 'yes',
                                'mep_events_extra_prices' => array(
                                    0 => array(
                                        'option_name' => 'Welcome Drink',
                                        'option_price' => '50',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                    1 => array(
                                        'option_name' => 'Cap',
                                        'option_price' => '70',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                ),
                                // Pickup Points
                                'show_pickup_point' => 'no',
                                // Onday & Offday
                                'show_operational_on_day' => 'no',
                                'show_off_day' => 'yes',
                                'weekly_offday' => '',
                                'wbtm_od_start' => '',
                                'wbtm_od_end' => '',
                                'wbtm_bus_on_date' => '',
                                'show_boarding_points' => '',
                                'show_off_day' => 'no',
                            ],
                        ],
                        3 => [
                            'name' => 'RED Coach',
                            'content' => '

                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                            
                            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.
                            ',
                            'taxonomy_terms' => [
                                0 => array(
                                    'taxonomy_name' => 'wbtm_bus_cat',
                                    'terms' => array(
                                        0 => 'AC',
                                    )
                                ),
                                1 => array(
                                    'taxonomy_name' => 'wbtm_bus_stops',
                                    'terms' => array(
                                        0 => 'Berlin',
                                        1 => 'Frankfurt',
                                        2 => 'Hamburg',
                                        3 => 'Paris',
                                    )
                                ),
                                2 => array(
                                    'taxonomy_name' => 'wbtm_bus_pickpoint',
                                    'terms' => array(
                                        0=>'Berlin',
                                        1=>'Frankfurt',
                                    )
                                ),                                
                            ],
                            'post_data' => [
                                //configuration
                                'feature_image' => 'https://img.freepik.com/premium-photo/white-bus-moving-fast-road-modern-city-with-light-effect_207634-3202.jpg',
                                
                                'wbtm_bus_no' => 'Redbus-01',
                                'wbtm_total_seat' => '16',
                                'wbtm_seat_type_conf' => 'wbtm_seat_plan',
                                'zero_price_allow' => 'no',
                                'show_boarding_time' => 'yes',
                                'show_dropping_time' => 'yes',

                                'driver_seat_position' => 'driver_left',
                                'wbtm_seat_cols' => '4',
                                'wbtm_seat_rows' => '8',
                                'wbtm_bus_seats_info' => array(
                                    0 => array(
                                        'seat1' => 'A1',
                                        'seat2' => 'A2',
                                        'seat3' => 'A3',
                                        'seat4' => 'A4',
                                    ),
                                    1 => array(
                                        'seat1' => 'B1',
                                        'seat2' => 'B2',
                                        'seat3' => 'B3',
                                        'seat4' => 'B4',
                                    ),
                                    2 => array(
                                        'seat1' => 'C1',
                                        'seat2' => 'C2',
                                        'seat3' => 'C3',
                                        'seat4' => 'C4',
                                    ),
                                    3 => array(
                                        'seat1' => 'D1',
                                        'seat2' => 'D2',
                                        'seat3' => 'D3',
                                        'seat4' => 'D4',
                                    ),
                                    4 => array(
                                        'seat1' => 'E1',
                                        'seat2' => 'E2',
                                        'seat3' => 'E3',
                                        'seat4' => 'E4',
                                    ),
                                    5 => array(
                                        'seat1' => 'F1',
                                        'seat2' => 'F2',
                                        'seat3' => 'F3',
                                        'seat4' => 'F4',
                                    ),
                                    6 => array(
                                        'seat1' => 'G1',
                                        'seat2' => 'G2',
                                        'seat3' => 'G3',
                                        'seat4' => 'G4',
                                    ),
                                    7 => array(
                                        'seat1' => 'H1',
                                        'seat2' => 'H2',
                                        'seat3' => 'H3',
                                        'seat4' => 'H4',
                                    ),
                                ),
                                'show_upper_desk' => 'yes',
                                'wbtm_seat_cols_dd' => '4',
                                'wbtm_seat_rows_dd' => '8',
                                'wbtm_bus_seats_info_dd' => array(
                                    0 => array(
                                        'dd_seat1' => 'S1',
                                        'dd_seat2' => 'S2',
                                        'dd_seat3' => 'S3',
                                        'dd_seat4' => 'S4',
                                    ),
                                    1 => array(
                                        'dd_seat1' => 'T1',
                                        'dd_seat2' => 'T2',
                                        'dd_seat3' => 'T3',
                                        'dd_seat4' => 'T4',
                                    ),
                                    2 => array(
                                        'dd_seat1' => 'U1',
                                        'dd_seat2' => 'U2',
                                        'dd_seat3' => 'U3',
                                        'dd_seat4' => 'U4',
                                    ),
                                    3 => array(
                                        'dd_seat1' => 'V1',
                                        'dd_seat2' => 'V2',
                                        'dd_seat3' => 'V3',
                                        'dd_seat4' => 'V4',
                                    ),
                                    4 => array(
                                        'dd_seat1' => 'W1',
                                        'dd_seat2' => 'W2',
                                        'dd_seat3' => 'W3',
                                        'dd_seat4' => 'W4',
                                    ),
                                    5 => array(
                                        'dd_seat1' => 'X1',
                                        'dd_seat2' => 'X2',
                                        'dd_seat3' => 'X3',
                                        'dd_seat4' => 'X4',
                                    ),
                                    6 => array(
                                        'dd_seat1' => 'Y1',
                                        'dd_seat2' => 'Y2',
                                        'dd_seat3' => 'Y3',
                                        'dd_seat4' => 'Y4',
                                    ),
                                    7 => array(
                                        'dd_seat1' => 'Z1',
                                        'dd_seat2' => 'Z2',
                                        'dd_seat3' => 'Z3',
                                        'dd_seat4' => 'Z4',
                                    ),
                                ),
                                'wbtm_seat_dd_price_parcent' => '',

                                //Routing
                                'wbtm_bus_bp_stops' => array(
                                    0 => array(
                                        'wbtm_bus_bp_stops_name' => 'Paris',
                                        'wbtm_bus_bp_start_time' => '12:00',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_stops_name' => 'Frankfurt',
                                        'wbtm_bus_bp_start_time' => '12:20',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_stops_name' => 'Hamburg',
                                        'wbtm_bus_bp_start_time' => '12:30',
                                    ),
                                ),
                                'wbtm_bus_next_stops' => array(
                                    0 => array(
                                        'wbtm_bus_next_stops_name' => 'Frankfurt',
                                        'wbtm_bus_next_end_time' => '16:10',
                                    ),
                                    1 => array(
                                        'wbtm_bus_next_stops_name' => 'Hamburg',
                                        'wbtm_bus_next_end_time' => '19:10',
                                    ),
                                    2 => array(
                                        'wbtm_bus_next_stops_name' => 'Berlin',
                                        'wbtm_bus_next_end_time' => '22:30',
                                    ),
                                ),
                                'wbtm_route_summary' => array(),
                                'mtsa_subscription_route_type' => array(),
                                // Seat Price
                                'wbtm_bus_prices' => array(
                                    0 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_price_stop' => 'Paris',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '20',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    3 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '25',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    4 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    5 => array(
                                        'wbtm_bus_bp_price_stop' => 'Hamburg',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                ),
                                'show_extra_service' => 'yes',
                                'mep_events_extra_prices' => array(
                                    0 => array(
                                        'option_name' => 'Welcome Drink',
                                        'option_price' => '50',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                    1 => array(
                                        'option_name' => 'Cap',
                                        'option_price' => '70',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                ),
                                // Pickup Points
                                'show_pickup_point' => 'no',
                                // Onday & Offday
                                'show_operational_on_day' => 'no',
                                'show_off_day' => 'yes',
                                'weekly_offday' => '',
                                'wbtm_od_start' => '',
                                'wbtm_od_end' => '',
                                'wbtm_bus_on_date' => '',
                                'show_boarding_points' => '',
                                'show_off_day' => 'no',
                            ],
                        ],
                        4 => [
                            'name' => 'Bonanza Bus',
                            'content' => '

                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                            
                            Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur.
                            ',
                            'taxonomy_terms' => [
                                0 => array(
                                    'taxonomy_name' => 'wbtm_bus_cat',
                                    'terms' => array(
                                        0 => 'Non AC',
                                    )
                                ),
                                1 => array(
                                    'taxonomy_name' => 'wbtm_bus_stops',
                                    'terms' => array(
                                        0 => 'Berlin',
                                        1 => 'Frankfurt',
                                        2 => 'Hamburg',
                                        3 => 'Paris',
                                    )
                                ),
                                2 => array(
                                    'taxonomy_name' => 'wbtm_bus_pickpoint',
                                    'terms' => array(
                                        0=>'Berlin',
                                        1=>'Frankfurt',
                                    )
                                ),                                
                            ],
                            'post_data' => [
                                //configuration
                                'feature_image' => 'https://img.freepik.com/premium-photo/white-bus-waits-patiently-passengers-its-engine-humming-softly-as-it-prepares-whisk-them-away-their-adventure-generative-ai_653286-796.jpg',
                                
                                'wbtm_bus_no' => 'Bonanzabus-01',
                                'wbtm_total_seat' => '16',
                                'wbtm_seat_type_conf' => 'wbtm_seat_plan',
                                'zero_price_allow' => 'no',
                                'show_boarding_time' => 'yes',
                                'show_dropping_time' => 'yes',

                                'driver_seat_position' => 'driver_left',
                                'wbtm_seat_cols' => '4',
                                'wbtm_seat_rows' => '8',
                                'wbtm_bus_seats_info' => array(
                                    0 => array(
                                        'seat1' => 'A1',
                                        'seat2' => 'A2',
                                        'seat3' => 'A3',
                                        'seat4' => 'A4',
                                    ),
                                    1 => array(
                                        'seat1' => 'B1',
                                        'seat2' => 'B2',
                                        'seat3' => 'B3',
                                        'seat4' => 'B4',
                                    ),
                                    2 => array(
                                        'seat1' => 'C1',
                                        'seat2' => 'C2',
                                        'seat3' => 'C3',
                                        'seat4' => 'C4',
                                    ),
                                    3 => array(
                                        'seat1' => 'D1',
                                        'seat2' => 'D2',
                                        'seat3' => 'D3',
                                        'seat4' => 'D4',
                                    ),
                                    4 => array(
                                        'seat1' => 'E1',
                                        'seat2' => 'E2',
                                        'seat3' => 'E3',
                                        'seat4' => 'E4',
                                    ),
                                    5 => array(
                                        'seat1' => 'F1',
                                        'seat2' => 'F2',
                                        'seat3' => 'F3',
                                        'seat4' => 'F4',
                                    ),
                                    6 => array(
                                        'seat1' => 'G1',
                                        'seat2' => 'G2',
                                        'seat3' => 'G3',
                                        'seat4' => 'G4',
                                    ),
                                    7 => array(
                                        'seat1' => 'H1',
                                        'seat2' => 'H2',
                                        'seat3' => 'H3',
                                        'seat4' => 'H4',
                                    ),
                                ),
                                'show_upper_desk' => 'yes',
                                'wbtm_seat_cols_dd' => '4',
                                'wbtm_seat_rows_dd' => '8',
                                'wbtm_bus_seats_info_dd' => array(
                                    0 => array(
                                        'dd_seat1' => 'S1',
                                        'dd_seat2' => 'S2',
                                        'dd_seat3' => 'S3',
                                        'dd_seat4' => 'S4',
                                    ),
                                    1 => array(
                                        'dd_seat1' => 'T1',
                                        'dd_seat2' => 'T2',
                                        'dd_seat3' => 'T3',
                                        'dd_seat4' => 'T4',
                                    ),
                                    2 => array(
                                        'dd_seat1' => 'U1',
                                        'dd_seat2' => 'U2',
                                        'dd_seat3' => 'U3',
                                        'dd_seat4' => 'U4',
                                    ),
                                    3 => array(
                                        'dd_seat1' => 'V1',
                                        'dd_seat2' => 'V2',
                                        'dd_seat3' => 'V3',
                                        'dd_seat4' => 'V4',
                                    ),
                                    4 => array(
                                        'dd_seat1' => 'W1',
                                        'dd_seat2' => 'W2',
                                        'dd_seat3' => 'W3',
                                        'dd_seat4' => 'W4',
                                    ),
                                    5 => array(
                                        'dd_seat1' => 'X1',
                                        'dd_seat2' => 'X2',
                                        'dd_seat3' => 'X3',
                                        'dd_seat4' => 'X4',
                                    ),
                                    6 => array(
                                        'dd_seat1' => 'Y1',
                                        'dd_seat2' => 'Y2',
                                        'dd_seat3' => 'Y3',
                                        'dd_seat4' => 'Y4',
                                    ),
                                    7 => array(
                                        'dd_seat1' => 'Z1',
                                        'dd_seat2' => 'Z2',
                                        'dd_seat3' => 'Z3',
                                        'dd_seat4' => 'Z4',
                                    ),
                                ),
                                'wbtm_seat_dd_price_parcent' => '',

                                //Routing
                                'wbtm_bus_bp_stops' => array(
                                    0 => array(
                                        'wbtm_bus_bp_stops_name' => 'Frankfurt',
                                        'wbtm_bus_bp_start_time' => '12:20',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_stops_name' => 'Hamburg',
                                        'wbtm_bus_bp_start_time' => '12:30',
                                    ),
                                ),
                                'wbtm_bus_next_stops' => array(
                                    1 => array(
                                        'wbtm_bus_next_stops_name' => 'Hamburg',
                                        'wbtm_bus_next_end_time' => '19:10',
                                    ),
                                    2 => array(
                                        'wbtm_bus_next_stops_name' => 'Berlin',
                                        'wbtm_bus_next_end_time' => '22:30',
                                    ),
                                ),
                                'wbtm_route_summary' => array(),
                                'mtsa_subscription_route_type' => array(),
                                // Seat Price
                                'wbtm_bus_prices' => array(
                                    0 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Hamburg',
                                        'wbtm_bus_price' => '25',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    1 => array(
                                        'wbtm_bus_bp_price_stop' => 'Frankfurt',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '15',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                    2 => array(
                                        'wbtm_bus_bp_price_stop' => 'Hamburg',
                                        'wbtm_bus_dp_price_stop' => 'Berlin',
                                        'wbtm_bus_price' => '10',
                                        'wbtm_bus_price_return' => '',
                                        'wbtm_bus_child_price' => '',
                                        'wbtm_bus_child_price_return' => '0',
                                        'wbtm_bus_infant_price' => '0',
                                        'wbtm_bus_infant_price_return' => '',
                                    ),
                                ),
                                'show_extra_service' => 'yes',
                                'mep_events_extra_prices' => array(
                                    0 => array(
                                        'option_name' => 'Welcome Drink',
                                        'option_price' => '50',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                    1 => array(
                                        'option_name' => 'Cap',
                                        'option_price' => '70',
                                        'option_qty' => '500',
                                        'option_qty_type' => 'inputbox',
                                    ),
                                ),
                                // Pickup Points
                                'show_pickup_point' => 'no',
                                // Onday & Offday
                                'show_operational_on_day' => 'no',
                                'show_off_day' => 'yes',
                                'weekly_offday' => '',
                                'wbtm_od_start' => '',
                                'wbtm_od_end' => '',
                                'wbtm_bus_on_date' => '',
                                'show_boarding_points' => '',
                                'show_off_day' => 'no',
                            ],
                        ],
                        
                    ],
                ],
            ];

        }
    }

    new WBTM_Dummy_Import();
}