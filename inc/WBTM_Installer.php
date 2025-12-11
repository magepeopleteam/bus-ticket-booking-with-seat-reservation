<?php
/*
 * @Author: MagePeople Team
 * Copyright: mage-people.com
 */

if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('WBTM_Installer')) {
    class WBTM_Installer
    {
        public static function activate()
        {
            // Add rewrite endpoints
            add_rewrite_endpoint('bus-panel', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('bus-booking-dashboard', EP_ROOT | EP_PAGES);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set default options
            self::set_default_options();
        }

        public static function deactivate()
        {
            // Flush rewrite rules on deactivation
            flush_rewrite_rules();
        }

        private static function set_default_options()
        {
            // Set default dashboard options if they don't exist
            if (!get_option('wbtm_dashboard_settings')) {
                $default_settings = array(
                    'enable_dashboard' => 'yes',
                    'enable_pdf_download' => 'yes',
                    'enable_attendee_edit' => 'yes',
                    'dashboard_per_page' => 10
                );
                update_option('wbtm_dashboard_settings', $default_settings);
            }
        }

        public static function create_pages()
        {
            // This method can be used to create any necessary pages
            // Currently not needed as we're using WooCommerce My Account endpoints
        }
    }
}

// Register activation/deactivation hooks
register_activation_hook(WBTM_PLUGIN_DIR . '/woocommerce-bus.php', array('WBTM_Installer', 'activate'));
register_deactivation_hook(WBTM_PLUGIN_DIR . '/woocommerce-bus.php', array('WBTM_Installer', 'deactivate'));
