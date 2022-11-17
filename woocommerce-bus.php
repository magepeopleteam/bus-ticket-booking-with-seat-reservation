<?php
/**
* Plugin Name: Bus Ticket Booking with Seat Reservation
* Plugin URI: http://mage-people.com
* Description: A Complete Bus Ticketig System for WordPress & WooCommerce
* Version: 4.0
* Author: MagePeople Team
* Author URI: http://www.mage-people.com/
* Text Domain: bus-ticket-booking-with-seat-reservation
* Domain Path: /languages/
*/ 

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php' )) {

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mage-plugin-activator.php
 */
function wbtm_activate_wbtm_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	WBTM_Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mage-plugin-deactivator.php
 */
function wbtm_deactivate_wbtm_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	// wbtm_Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'wbtm_activate_wbtm_plugin' );
register_deactivation_hook( __FILE__, 'wbtm_deactivate_wbtm_plugin' );

function appsero_init_tracker_bus_ticket_booking_with_seat_reservation() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/lib/appsero/src/Client.php';
    }

    $client = new Appsero\Client( '183b453a-7a2a-47f6-aa7e-10bf246d1d44', 'Bus Ticket Booking with Seat Reservation', __FILE__ );

    // Active insights
    $client->insights()->init();

}
appsero_init_tracker_bus_ticket_booking_with_seat_reservation();

class Wbtm_Base{
	
	public function __construct(){
		$this->define_constants();
		$this->load_main_class();
		$this->run_wbtm_plugin();
	}

	public function define_constants() {
		// define( 'WBTM_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
		define( 'WBTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'WBTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WBTM_PLUGIN_FILE', plugin_basename( __FILE__ ) );
		define( 'WBTM_TEXTDOMAIN', 'mage-plugin' );
	}

	public function load_main_class(){
		require WBTM_PLUGIN_DIR . 'includes/class-plugin.php';
	}

	public function run_wbtm_plugin() {
		$plugin = new Wbtm_Plugin();
		$plugin->run();
		$this->setBusPermission();
	}

	// Give bus all permission to admin
	public function setBusPermission()
	{
		if(is_admin()) {
			$role = get_role('administrator');
			( !$role->has_cap('publish_wbtm_buses') ) ? $role->add_cap('publish_wbtm_buses') : null;
			( !$role->has_cap('edit_wbtm_buses') ) ? $role->add_cap('edit_wbtm_buses') : null;
			( !$role->has_cap('edit_others_wbtm_buses') ) ? $role->add_cap('edit_others_wbtm_buses') : null;
			( !$role->has_cap('read_private_wbtm_buses') ) ? $role->add_cap('read_private_wbtm_buses') : null;
			( !$role->has_cap('edit_wbtm_bus') ) ? $role->add_cap('edit_wbtm_bus') : null;
			( !$role->has_cap('delete_wbtm_bus') ) ? $role->add_cap('delete_wbtm_bus') : null;
			( !$role->has_cap('read_wbtm_bus') ) ? $role->add_cap('read_wbtm_bus') : null;
			( !$role->has_cap('wbtm_permission_page') ) ? $role->add_cap('wbtm_permission_page') : null;
			( !$role->has_cap('extra_service_wbtm_bus') ) ? $role->add_cap('extra_service_wbtm_bus') : null;
		}
	}
}
new Wbtm_Base();

// Get Plugin Data
if(!function_exists('wbtm_get_plugin_data')) {
    function wbtm_get_plugin_data($data) {
        $get_wbtm_plugin_data = get_plugin_data( __FILE__ );
        $wbtm_data = $get_wbtm_plugin_data[$data];
        return $wbtm_data;
    }
}

// Added Settings link to plugin action links
add_filter( 'plugin_action_links', 'wbtm_plugin_action_link', 10, 2 );

function wbtm_plugin_action_link( $links_array, $plugin_file_name ){

	if( strpos( $plugin_file_name, basename(__FILE__) ) ) {

		array_unshift( $links_array, '<a href="'.esc_url(admin_url()).'edit.php?post_type=wbtm_bus&page=wbtm-bus-manager-settings">'.__('Settings','bus-booking-manager').'</a>');
	}
	
	return $links_array;
}

// Added links to plugin row meta
add_filter( 'plugin_row_meta', 'wbtm_plugin_row_meta', 10, 2 );
 
function wbtm_plugin_row_meta( $links_array, $plugin_file_name ) {
 
    if( strpos( $plugin_file_name, basename(__FILE__) ) ) {

		if(!is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php')){
			$wbtm_links = array(
                'docs' 	  => '<a href="'.esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/").'" target="_blank">'.__('Docs','bus-booking-manager').'</a>',
                'support' => '<a href="'.esc_url("https://mage-people.com/my-account").'" target="_blank">'.__('Support','bus-booking-manager').'</a>',
                'get_pro' => '<a href="'.esc_url("https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/").'" target="_blank" class="wbtm_plugin_pro_meta_link">'.__('Upgrade to PRO Version','bus-booking-manager').'</a>'
                );
		}
		else{
			$wbtm_links = array(
                'docs' 	  => '<a href="'.esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/").'" target="_blank">'.__('Docs','bus-booking-manager').'</a>',
                'support' => '<a href="'.esc_url("https://mage-people.com/my-account").'" target="_blank">'.__('Support','bus-booking-manager').'</a>',
                );
		}

        $links_array = array_merge( $links_array, $wbtm_links );
    }
     
    return $links_array;
}

}else{

    function wbtm_wc_not_active() {
      $class = 'notice notice-error';
      $wc_install_url = get_admin_url().'plugin-install.php?s=Woocommerce&tab=search&type=term';
      $message = __( 'You Must Install Woocommerce Plugin before activating Bus Ticket Booking with Seat Reservation, Becuase It is fully dependent on that Plugin. <a class="btn button" href='.$wc_install_url.'>Click Here to Install</a> ', 'bus-ticket-booking-with-seat-reservation' );
      printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ),  $message  ); 

    }
    add_action( 'admin_notices', 'wbtm_wc_not_active' );
	deactivate_plugins(array('/bus-ticket-booking-with-seat-reservation/woocommerce-bus.php', '/addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php'));

	add_action('admin_notices', 'wbtm_plugin_deactivated_notice', 70);
	function wbtm_plugin_deactivated_notice() {
		printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr('notice notice-error'),  'Plugin Deactivated!'); 
	}
}

/*************************
Check the required plugins
***************************/
require_once(dirname(__FILE__) . "/includes/class-wbtm-required-plugins.php");