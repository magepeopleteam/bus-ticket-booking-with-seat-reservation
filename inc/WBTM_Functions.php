<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Functions' ) ) {
		class WBTM_Functions {
			public static function get_name() {
				return MP_Global_Function::get_settings('label_setting_sec','bus_menu_label',esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'));
			}
			public static function get_slug() {
				return MP_Global_Function::get_settings('label_setting_sec','bus_menu_slug','bus');
			}
		}
	}