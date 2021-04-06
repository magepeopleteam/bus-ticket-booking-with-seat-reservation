<?php
/**
* Plugin Name: Bus Ticket Booking with Seat Reservation
* Plugin URI: http://mage-people.com
* Description: A Complete Bus Ticketig System for WordPress & WooCommerce
* Version: 3.2
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
	// WBTM_Plugin_Activator::activate();
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

			// ( !$role->has_cap('publish_wbtm_buses') ) ? $role->add_cap('publish_wbtm_buses') : null;
			// ( !$role->has_cap('edit_wbtm_buses') ) ? $role->add_cap('edit_wbtm_buses') : null;
			// ( !$role->has_cap('edit_others_wbtm_buses') ) ? $role->add_cap('edit_others_wbtm_buses') : null;
			// ( !$role->has_cap('read_private_wbtm_buses') ) ? $role->add_cap('read_private_wbtm_buses') : null;
			// ( !$role->has_cap('edit_wbtm_bus') ) ? $role->add_cap('edit_wbtm_bus') : null;
			// ( !$role->has_cap('delete_wbtm_bus') ) ? $role->add_cap('delete_wbtm_bus') : null;
			// ( !$role->has_cap('read_wbtm_bus') ) ? $role->add_cap('read_wbtm_bus') : null;
			// ( !$role->has_cap('wbtm_permission_page') ) ? $role->add_cap('wbtm_permission_page') : null;
			// ( !$role->has_cap('extra_service_wbtm_bus') ) ? $role->add_cap('extra_service_wbtm_bus') : null;
		}
	}
}
new Wbtm_Base();



}else{









	function mm_get_plugins($plugins)
	{
		$args = array(
				'path' => ABSPATH.'wp-content/plugins/',
				'preserve_zip' => false
		);
	
		foreach($plugins as $plugin)
		{
				mm_plugin_download($plugin['path'], $args['path'].$plugin['name'].'.zip');
				mm_plugin_unpack($args, $args['path'].$plugin['name'].'.zip');
				mm_plugin_activate($plugin['install']);
		}
	}
	function mm_plugin_download($url, $path) 
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($ch);
		curl_close($ch);
		if(file_put_contents($path, $data))
				return true;
		else
				return false;
	}
	function mm_plugin_unpack($args, $target)
	{


		if($zip = zip_open($target))
		{
				while($entry = zip_read($zip))
				{
						$is_file = substr(zip_entry_name($entry), -1) == '/' ? false : true;
						$file_path = $args['path'].zip_entry_name($entry);
						if($is_file)
						{
								if(zip_entry_open($zip,$entry,"r")) 
								{
										$fstream = zip_entry_read($entry, zip_entry_filesize($entry));
										file_put_contents($file_path, $fstream );
										chmod($file_path, 0777);
										//echo "save: ".$file_path."<br />";
								}
								zip_entry_close($entry);
						}
						else
						{
								if(zip_entry_name($entry))
								{
										mkdir($file_path);
										chmod($file_path, 0777);
										//echo "create: ".$file_path."<br />";
								}
						}
				}
				zip_close($zip);


		}



		if($args['preserve_zip'] === false)
		{
				unlink($target);
		}
	}
	function mm_plugin_activate($installer)
	{
		$current = get_option('active_plugins');
		$plugin = plugin_basename(trim($installer));
	
		if(!in_array($plugin, $current))
		{
				$current[] = $plugin;
				sort($current);
				do_action('activate_plugin', trim($plugin));
				update_option('active_plugins', $current);
				do_action('activate_'.trim($plugin));
				do_action('activated_plugin', trim($plugin));
				return true;
		}
		else
				return false;
	}
	

    function wbtm_wc_not_active() {
      $class = 'notice notice-error';
      $wc_install_url = get_admin_url().'plugin-install.php?s=Woocommerce&tab=search&type=term';
      $message = __( 'You Must Install Woocommerce Plugin before activating Bus Ticket Booking with Seat Reservation, Becuase It is fully dependent on that Plugin. <a class="btn button" href='.$wc_install_url.'>Click Here to Install</a> ', 'bus-ticket-booking-with-seat-reservation' );
      printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ),  $message  ); 

    }
    add_action( 'admin_notices', 'wbtm_wc_not_active' );

}