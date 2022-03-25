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
			'all_items'             => __( 'All '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'add_new_item'          => __( 'Add New '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'add_new'               => __( 'Add New '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'new_item'              => __( 'New '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'edit_item'             => __( 'Edit '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'update_item'           => __( 'Update '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'view_item'             => __( 'View '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'view_items'            => __( 'View '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'search_items'          => __( 'Search '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'not_found'             => __( $name.' Not found', 'bus-ticket-booking-with-seat-reservation' ),
			'not_found_in_trash'    => __( $name.' Not found in Trash', 'bus-ticket-booking-with-seat-reservation' ),
			'featured_image'        => __( $name.' Feature Image', 'bus-ticket-booking-with-seat-reservation' ),
			'set_featured_image'    => __( 'Set Bus featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'remove_featured_image' => __( 'Remove Bus featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'use_featured_image'    => __( 'Use as Bus featured image', 'bus-ticket-booking-with-seat-reservation' ),
			'insert_into_item'      => __( 'Insert into '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'uploaded_to_this_item' => __( 'Uploaded to this '.$name, 'bus-ticket-booking-with-seat-reservation' ),
			'items_list'            => __( $name.' list', 'bus-ticket-booking-with-seat-reservation' ),
			'items_list_navigation' => __( $name.' list navigation', 'bus-ticket-booking-with-seat-reservation' ),
			'filter_items_list'     => __( 'Filter Bus list', 'bus-ticket-booking-with-seat-reservation' ),
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