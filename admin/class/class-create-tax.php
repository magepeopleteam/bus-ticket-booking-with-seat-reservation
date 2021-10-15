<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

class WBTM_Tax{
	public function __construct(){
		add_action("init",array($this,"WBTM_tax_init"),5);
	}
	public function WBTM_tax_init(){
		global $wbtmmain;
		$name = $wbtmmain->get_name();
		$slug = $wbtmmain->get_slug();
		$labels = array(
			'name'                       => _x( $name.' Type','bus-ticket-booking-with-seat-reservation' ),
			'singular_name'              => _x( $name.' Type','bus-ticket-booking-with-seat-reservation' ),
			'menu_name'                  => __( $name.' Type', 'bus-ticket-booking-with-seat-reservation' ),
			'all_items'                  => __( 'All Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'parent_item'                => __( 'Parent Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'parent_item_colon'          => __( 'Parent Bus Type:', 'bus-ticket-booking-with-seat-reservation' ),
			'new_item_name'              => __( 'New Bus Type Name', 'bus-ticket-booking-with-seat-reservation' ),
			'add_new_item'               => __( 'Add New Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'edit_item'                  => __( 'Edit Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'update_item'                => __( 'Update Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'view_item'                  => __( 'View Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'separate_items_with_commas' => __( 'Separate Category with commas', 'bus-ticket-booking-with-seat-reservation' ),
			'add_or_remove_items'        => __( 'Add or remove Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'bus-ticket-booking-with-seat-reservation' ),
			'popular_items'              => __( 'Popular Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'search_items'               => __( 'Search Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'not_found'                  => __( 'Not Found', 'bus-ticket-booking-with-seat-reservation' ),
			'no_terms'                   => __( 'No Bus Type', 'bus-ticket-booking-with-seat-reservation' ),
			'items_list'                 => __( $name.' Type list', 'bus-ticket-booking-with-seat-reservation' ),
			'items_list_navigation'      => __( $name.' Type list navigation', 'bus-ticket-booking-with-seat-reservation' ),
		);
	
		$args = array(
			'hierarchical'          => true,
			"public" 				=> true,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'bus-category' ),
			'show_in_rest'          => true,
			'rest_base'             => 'bus_cat',				
		);
	register_taxonomy('wbtm_bus_cat', 'wbtm_bus', $args);

		
	
    $seat_type_labels = array(
        'singular_name'              => _x( 'Seat Type','bus-ticket-booking-with-seat-reservation' ),
        'name'                       => _x( 'Seat Type','bus-ticket-booking-with-seat-reservation' ),
    );

    $seat_type_args = array(
        'hierarchical'          => true,
        "public" 				=> true,
        'labels'                => $seat_type_labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'update_count_callback' => '_update_post_term_count',
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'seat-type' ),
    );
	// register_taxonomy('wbtm_seat_type', 'wbtm_bus', $seat_type_args);
	
	
	
	
	
	
		$bus_stops_labels = array(
			'singular_name'              => _x( $name.' Stops','bus-ticket-booking-with-seat-reservation' ),
			'name'                       => _x( $name.' Stops','bus-ticket-booking-with-seat-reservation' ),
		);
	
		$bus_stops_args = array(
			'hierarchical'          => true,
			"public" 				=> true,
			'labels'                => $bus_stops_labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'bus-stops' ),
			'show_in_rest'          => true,
			'rest_base'             => 'bus_stops',				
		);
	    register_taxonomy('wbtm_bus_stops', 'wbtm_bus', $bus_stops_args);

        $labels = array(
            'name'                       => _x( $name.' Pickup Point','bus-ticket-booking-with-seat-reservation' ),
            'singular_name'              => _x( $name.' Pickup Point','bus-ticket-booking-with-seat-reservation' ),
            'menu_name'                  => __( $name.' Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'all_items'                  => __( 'All Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'parent_item'                => __( 'Parent Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'parent_item_colon'          => __( 'Parent Bus Pickup Point:', 'bus-ticket-booking-with-seat-reservation' ),
            'new_item_name'              => __( 'New Bus Pickup Point Name', 'bus-ticket-booking-with-seat-reservation' ),
            'add_new_item'               => __( 'Add New Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'edit_item'                  => __( 'Edit Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'update_item'                => __( 'Update Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'view_item'                  => __( 'View Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'separate_items_with_commas' => __( 'Separate Category with commas', 'bus-ticket-booking-with-seat-reservation' ),
            'add_or_remove_items'        => __( 'Add or remove Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'bus-ticket-booking-with-seat-reservation' ),
            'popular_items'              => __( 'Popular Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'search_items'               => __( 'Search Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'not_found'                  => __( 'Not Found', 'bus-ticket-booking-with-seat-reservation' ),
            'no_terms'                   => __( 'No Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation' ),
            'items_list'                 => __( $name.' Pickup Point list', 'bus-ticket-booking-with-seat-reservation' ),
            'items_list_navigation'      => __( $name.' Pickup Point list navigation', 'bus-ticket-booking-with-seat-reservation' ),
        );
    
        $args = array(
            'hierarchical'          => true,
            "public" 				=> true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'bus-pickuppoint' ),
            'show_in_rest'          => false,
            'rest_base'             => 'bus_pickpoint',		
            'meta_box_cb'           => false,		
        );
        register_taxonomy('wbtm_bus_pickpoint', 'wbtm_bus', $args);
	
	
	
	
		$bus_route_labels = array(
			'singular_name'              => _x( 'Seat Type','bus-ticket-booking-with-seat-reservation' ),
			'name'                       => _x( 'Seat Type','bus-ticket-booking-with-seat-reservation' ),
		);
	
		$bus_route_args = array(
			'hierarchical'          => true,
			"public" 				=> true,
			'labels'                => $bus_route_labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'seat-type' ),
		);
	// register_taxonomy('wbtm_seat_type', 'wbtm_bus', $bus_route_args);
	}
}
new WBTM_Tax();