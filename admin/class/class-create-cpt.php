<?php 
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
class WBTM_Cpt{
	
	public function __construct(){
		add_action( 'init', array($this, 'register_cpt' ));
	}


	public function register_cpt(){
		global $wbtmmain;
		$name = $wbtmmain->get_name();
		$slug = $wbtmmain->get_slug();
		$labels = array(
			'name'                  => _x( $name, 'bus-ticket-booking-with-seat-reservation' ),
			'singular_name'         => _x( $name, 'bus-ticket-booking-with-seat-reservation' ),
			'menu_name'             => __( $name, 'bus-ticket-booking-with-seat-reservation' ),
			'name_admin_bar'        => __( $name, 'bus-ticket-booking-with-seat-reservation' ),
			'archives'              => __( $name.' List', 'bus-ticket-booking-with-seat-reservation' ),
			'attributes'            => __( $name.' List', 'bus-ticket-booking-with-seat-reservation' ),
			'parent_item_colon'     => __( $name.' Item:', 'bus-ticket-booking-with-seat-reservation' ),
			'all_items'             => __( 'All', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'add_new_item'          => __( 'Add New', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'add_new'               => __( 'Add New', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'new_item'              => __( 'New', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'edit_item'             => __( 'Edit', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'update_item'           => __( 'Update', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'view_item'             => __( 'View', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'view_items'            => __( 'View', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'search_items'          => __( 'Search', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'not_found'             => __( 'Not found', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'not_found_in_trash'    => __( 'Not found in Trash', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'featured_image'        => __( 'Feature Image', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'set_featured_image'    => __( 'Set', 'bus-ticket-booking-with-seat-reservation' ).' '.$name.'.'.__( 'featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'remove_featured_image' => __( 'Remove', 'bus-ticket-booking-with-seat-reservation' ).' '.$name.'.'.__( 'featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'use_featured_image'    => __( 'Use as', 'bus-ticket-booking-with-seat-reservation' ).' '.$name.'.'.__( 'featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'insert_into_item'      => __( 'Insert into', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'uploaded_to_this_item' => __( 'Uploaded to this', 'bus-ticket-booking-with-seat-reservation' ).' '.$name,
			'items_list'            => $name.' '. __( ' list', 'bus-ticket-booking-with-seat-reservation' ),
			'items_list_navigation' => $name.' '.__( ' list navigation', 'bus-ticket-booking-with-seat-reservation' ),
			'filter_items_list'     => __( 'Filter', 'bus-ticket-booking-with-seat-reservation' ).' '.$name.' '.__( 'list', 'bus-ticket-booking-with-seat-reservation' ),
		);
	
	    $args = array(
	        'public'                => true,
	        'labels'                => $labels,
	        'menu_icon'             => 'dashicons-slides',
	        'supports'              => array('title','editor','thumbnail'),
			'rewrite'               => array('slug' => $slug),
			'show_in_rest'          => true,
			'rest_base'             => 'wbtm_bus',
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

	   	register_post_type( 'wbtm_bus', $args );

	}

}
new WBTM_Cpt();