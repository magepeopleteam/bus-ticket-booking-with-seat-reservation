<?php
/**
 * Plugin Name: Bus Ticket Booking with Seat Reservation
 * Plugin URI: http://mage-people.com
 * Description: A Complete Bus Ticketig System for WordPress & WooCommerce
 * Version: 5.2.3
 * Author: MagePeople Team
 * Author URI: http://www.mage-people.com/
 * Text Domain: bus-ticket-booking-with-seat-reservation
 * Domain Path: /languages/
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


class Wbtm_Woocommerce_bus
{

    public function __construct()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $this->define_constants();
        $this->load_plugin();
        register_deactivation_hook(__FILE__,array($this,'wbtm_deactivate_wbtm_plugin') );
        register_activation_hook(__FILE__, array($this, 'wbtm_activate_wbtm_plugin'));
    }





    private function load_plugin()
    {

        if (self::check_woocommerce() === 'yes') {

            $this->appsero_init_tracker_bus_ticket_booking_with_seat_reservation();
            add_filter('plugin_action_links', array($this, 'wbtm_plugin_action_link'), 10, 2);
            add_filter('plugin_row_meta', array($this, 'wbtm_plugin_row_meta'), 10, 2);

            require WBTM_PLUGIN_DIR . 'includes/class-plugin.php';
            require WBTM_PLUGIN_DIR . 'includes/BusTicketBookingWithSeatReservationClass.php';
            $this->run_wbtm_plugin();
            require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Quick_Setup.php';
            add_action('activated_plugin', array($this, 'activation_redirect'), 90, 1);
        } else {
            require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Quick_Setup.php';
            add_action('activated_plugin', array($this, 'activation_redirect_setup'), 90, 1);
        }
    }




    public function activation_redirect($plugin)
    {
        if ($plugin == plugin_basename(__FILE__)) {
            exit(wp_redirect(admin_url('edit.php?post_type=wbtm_bus&page=wbtm_quick_setup')));
        }
    }
    public function activation_redirect_setup( $plugin ) {
        if ( $plugin == plugin_basename( __FILE__ ) ) {
            exit( wp_redirect( admin_url( 'admin.php?post_type=wbtm_bus&page=wbtm_quick_setup' ) ) );
        }
    }

    public function define_constants()
    {
        // define( 'WBTM_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
        define('WBTM_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WBTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('WBTM_PLUGIN_FILE', plugin_basename(__FILE__));
        define('WBTM_TEXTDOMAIN', 'mage-plugin');
    }
    function wbtm_activate_wbtm_plugin()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-activator.php';
        WBTM_Plugin_Activator::activate();
    }
    function wbtm_deactivate_wbtm_plugin()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-deactivator.php';
        // wbtm_Plugin_Deactivator::deactivate();
    }
    function appsero_init_tracker_bus_ticket_booking_with_seat_reservation()
    {
        if (!class_exists('Appsero\Client')) {
            require_once __DIR__ . '/lib/appsero/src/Client.php';
        }
        $client = new Appsero\Client('183b453a-7a2a-47f6-aa7e-10bf246d1d44', 'Bus Ticket Booking with Seat Reservation', __FILE__);
        // Active insights
        $client->insights()->init();

    }
    function wbtm_get_plugin_data($data)
    {
        $get_wbtm_plugin_data = get_plugin_data(__FILE__);
        $wbtm_data = $get_wbtm_plugin_data[$data];
        return $wbtm_data;
    }
    function wbtm_plugin_row_meta($links_array, $plugin_file_name)
    {

        if (strpos($plugin_file_name, basename(__FILE__))) {

            if (!is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php')) {
                $wbtm_links = array(
                    'docs' => '<a href="' . esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/") . '" target="_blank">' . __('Docs', 'bus-booking-manager') . '</a>',
                    'support' => '<a href="' . esc_url("https://mage-people.com/my-account") . '" target="_blank">' . __('Support', 'bus-booking-manager') . '</a>',
                    'get_pro' => '<a href="' . esc_url("https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/") . '" target="_blank" class="wbtm_plugin_pro_meta_link">' . __('Upgrade to PRO Version', 'bus-booking-manager') . '</a>'
                );
            } else {
                $wbtm_links = array(
                    'docs' => '<a href="' . esc_url("https://docs.mage-people.com/bus-ticket-booking-with-seat-reservation/") . '" target="_blank">' . __('Docs', 'bus-booking-manager') . '</a>',
                    'support' => '<a href="' . esc_url("https://mage-people.com/my-account") . '" target="_blank">' . __('Support', 'bus-booking-manager') . '</a>',
                );
            }

            $links_array = array_merge($links_array, $wbtm_links);
        }

        return $links_array;
    }

    function wbtm_plugin_action_link($links_array, $plugin_file_name)
    {

        if (strpos($plugin_file_name, basename(__FILE__))) {

            array_unshift($links_array, '<a href="' . esc_url(admin_url()) . 'edit.php?post_type=wbtm_bus&page=wbtm-bus-manager-settings">' . __('Settings', 'bus-booking-manager') . '</a>');
        }

        return $links_array;
    }

    function run_wbtm_plugin()
    {
        $plugin = new Wbtm_Plugin();
        $plugin->run();
        $this->setBusPermission();

    }

    // Give bus all permission to admin
    function setBusPermission()
    {
        if (is_admin()) {
            $role = get_role('administrator');
            (!$role->has_cap('publish_wbtm_buses')) ? $role->add_cap('publish_wbtm_buses') : null;
            (!$role->has_cap('edit_wbtm_buses')) ? $role->add_cap('edit_wbtm_buses') : null;
            (!$role->has_cap('edit_others_wbtm_buses')) ? $role->add_cap('edit_others_wbtm_buses') : null;
            (!$role->has_cap('read_private_wbtm_buses')) ? $role->add_cap('read_private_wbtm_buses') : null;
            (!$role->has_cap('edit_wbtm_bus')) ? $role->add_cap('edit_wbtm_bus') : null;
            (!$role->has_cap('delete_wbtm_bus')) ? $role->add_cap('delete_wbtm_bus') : null;
            (!$role->has_cap('read_wbtm_bus')) ? $role->add_cap('read_wbtm_bus') : null;
            (!$role->has_cap('wbtm_permission_page')) ? $role->add_cap('wbtm_permission_page') : null;
            (!$role->has_cap('extra_service_wbtm_bus')) ? $role->add_cap('extra_service_wbtm_bus') : null;
        }
    }

    public static function check_woocommerce() {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        $plugin_dir = ABSPATH . 'wp-content/plugins/woocommerce';
        if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return 'yes';
        } elseif ( is_dir( $plugin_dir ) ) {
            return 'no';
        } else {
            return 0;
        }
    }
}

$Woocommerce_bus = new Wbtm_Woocommerce_bus();


if(!function_exists('wbtm_get_plugin_data')) {
    function wbtm_get_plugin_data($data) {
        $get_wbtm_plugin_data = get_plugin_data( __FILE__ );
        $wbtm_data = $get_wbtm_plugin_data[$data];
        return $wbtm_data;
    }
}