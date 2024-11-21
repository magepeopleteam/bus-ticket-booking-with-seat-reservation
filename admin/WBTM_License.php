<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_License')) {
		class WBTM_License {
			public function __construct() {
				add_action('mp_license_page_plugin_list', [$this, 'bus_licence'], 10);
			}
			public function bus_licence() {
				?>
				<tr>
					<th colspan="4" class="_textLeft"><?php echo esc_html('bus-ticket-booking-with-seat-reservation'); ?></th>
					<th><?php esc_html_e('Free','bus-ticket-booking-with-seat-reservation'); ?></th>
					<th></th>
					<th colspan="2"><?php esc_html_e('Unlimited','bus-ticket-booking-with-seat-reservation'); ?></th>
					<th colspan="3"><?php esc_html_e('No Need','bus-ticket-booking-with-seat-reservation'); ?></th>
					<th class="textSuccess"><?php esc_html_e('Active','bus-ticket-booking-with-seat-reservation'); ?></th>
					<td colspan="2"></td>
				</tr>
				<?php
				do_action('wbtm_addon_list');
			}
		}
		new WBTM_License();
	}