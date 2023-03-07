<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

		class FilterClass extends CommonClass{
			public function __construct() {

			}

            public function WBTM_register_custom_single_template($template) {
                global $post;
                if ($post->post_type == "wbtm_bus"){
                    $template_name = 'single-bus.php';
                    $template_path = 'bus-ticket-booking-with-seat-reservation/';
                    $default_path = WBTM_PLUGIN_DIR. 'public/templates/';

                    $bus_type = get_post_meta($post->ID, 'wbtm_seat_type_conf', true);
                    if($bus_type === 'wbtm_seat_subscription') {
                        if(is_plugin_active('addon-bus-ticket-subscription/plugin.php')) {
                            $template_path = WP_PLUGIN_DIR. '/addon-bus-ticket-subscription/inc/';
                            $default_path = WP_PLUGIN_DIR. '/addon-bus-ticket-subscription/inc/';
                        } else {
                            $template_name = 'template-not-found.php';
                        }
                    }

                    if($bus_type === 'wbtm_seat_private') {
                        if(is_plugin_active('addon-bus-ticket-private/plugin.php')) {
                            $template_path = WP_PLUGIN_DIR. '/addon-bus-ticket-private/inc/';
                            $default_path = WP_PLUGIN_DIR. '/addon-bus-ticket-private/inc/';
                        } else {
                            $template_name = 'template-not-found.php';
                        }
                    }

                    $template = locate_template( array($template_path . $template_name) );

                    if ( ! $template ) :
                        $template = $default_path . $template_name;
                    endif;
                    return $template;
                }
                return $template;
            }


	}