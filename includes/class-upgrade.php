<?php
class WBTM_UPGRADE{


	public function __construct() {
		$this->run_upgrade();
	}

    public function run_upgrade(){
        add_action('admin_init',array($this,'upgrade_old_booking_to_cpt'));
        add_action('admin_init',array($this,'move_bus_settings_to_new_panel'));
    }
    public function move_bus_settings_to_new_panel(){
        if ( get_option( 'wbtm_upgrade_settings_to_new' ) != 'completed' ) {
            $old_general_settings = !empty(get_option('general_setting_sec'))? get_option('general_setting_sec') : array();
            $old_label_settings = !empty(get_option('label_setting_sec'))? get_option('label_setting_sec') : array();
            //print_r(array_merge($old_general_settings,$old_label_settings ));
            $old_settings = array_merge($old_general_settings,$old_label_settings );
            update_option('wbtm_bus_settings',$old_settings);
            update_option( 'wbtm_upgrade_settings_to_new', 'completed' );
        }
    }
    public function upgrade_old_booking_to_cpt(){
  
        global $wpdb;
        $table_name = $wpdb->base_prefix.'wbtm_bus_booking_list';
        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
        if ( $wpdb->get_var( $query ) == $table_name ) {
            if ( get_option( 'wbtm_upgrade_to_new' ) != 'completed' ) {
            $result = $wpdb->get_results( "SELECT * FROM $table_name" );
                foreach ( $result as $_row ){

                    $name = '#'.$_row->order_id.get_the_title($_row->bus_id);

                    $new_post = array(
                        'post_title'    =>   $name,
                        'post_content'  =>   '',
                        'post_category' =>   array(),  // Usable for custom taxonomies too
                        'tags_input'    =>   array(),
                        'post_status'   =>   'publish', // Choose: publish, preview, future, draft, etc.
                        'post_type'     =>   'wbtm_bus_booking'  //'post',page' or use a custom post type if you want to
                        );
                  
                        //SAVE THE POST
                        $pid             = wp_insert_post($new_post);
                        update_post_meta( $pid, 'wbtm_order_id', $_row->order_id);
                        update_post_meta( $pid, 'wbtm_bus_id', $_row->bus_id);
                        update_post_meta( $pid, 'wbtm_user_id', $_row->user_id);
                        update_post_meta( $pid, 'wbtm_boarding_point', $_row->boarding_point);
                        update_post_meta( $pid, 'wbtm_next_stops', $_row->next_stops);
                        update_post_meta( $pid, 'wbtm_droping_point', $_row->droping_point);
                        update_post_meta( $pid, 'wbtm_bus_start', $_row->bus_start);
                        update_post_meta( $pid, 'wbtm_user_start', $_row->user_start);
                        update_post_meta( $pid, 'wbtm_seat', $_row->seat);
                        update_post_meta( $pid, 'wbtm_bus_fare', $_row->bus_fare);
                        update_post_meta( $pid, 'wbtm_journey_date', $_row->journey_date);
                        update_post_meta( $pid, 'wbtm_booking_date', $_row->booking_date);
                        update_post_meta( $pid, 'wbtm_status', $_row->status);
                        update_post_meta( $pid, 'wbtm_ticket_status', $_row->ticket_status);
                        update_post_meta( $pid, 'wbtm_user_name', $_row->user_name);
                        update_post_meta( $pid, 'wbtm_user_email', $_row->user_email);
                        update_post_meta( $pid, 'wbtm_user_phone', $_row->user_phone);
                        update_post_meta( $pid, 'wbtm_user_gender', $_row->user_gender);
                        update_post_meta( $pid, 'wbtm_user_address', $_row->user_address);
                        update_post_meta( $pid, 'wbtm_user_extra_bag', $_row->user_extra_bag);
                
                }
                update_option( 'wbtm_upgrade_to_new', 'completed' );
            }
          
        }
    }
}

new WBTM_UPGRADE();




add_action('admin_init', 'wbtm_create_old_bus_product', 10);
function wbtm_create_old_bus_product()
{

    if (get_option('wbtm_create_old_bus_product_01') != 'completed') {
        $args = array(
            'post_type' => 'wbtm_bus',
            'posts_per_page' => -1
        );
        $qr = new WP_Query($args);
        foreach ($qr->posts as $result) {
            $post_id = $result->ID;
            wbtm_create_hidden_event_product($post_id, get_the_title($post_id));
        }
        update_option('wbtm_create_old_bus_product_01', 'completed');
    }
}