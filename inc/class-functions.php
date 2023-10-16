<?php
	function wbtm_myaccount_query_vars($vars) {
		$vars[] = 'bus-panel';
		return $vars;
	}
	add_filter('query_vars', 'wbtm_myaccount_query_vars', 0);
	/******************************/
	function wbtm_bus_panel_menu_items($items) {
		$new_items = array();
		$label = WBTM_Functions::get_name();
		$new_items['bus-panel'] = $label . ' ' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');
		// Add the new item after `orders`.
		$after = 'orders';
		$position = array_search($after, array_keys($items)) + 1;
		// Insert the new item.
		$array = array_slice($items, 0, $position, true);
		$array += $new_items;
		$array += array_slice($items, $position, count($items) - $position, true);
		return $array;
	}
	add_filter('woocommerce_account_menu_items', 'wbtm_bus_panel_menu_items');
	/******************************/
	function wbtm_bus_panel_endpoint_title($title) {
		global $wp_query;
		$is_endpoint = isset($wp_query->query_vars['bus-panel']);
		if ($is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
			$label = WBTM_Functions::get_name();
			// New page title.
			$title = $label . ' ' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');
			remove_filter('the_title', 'wbtm_bus_panel_endpoint_title');
		}
		return $title;
	}
	add_filter('the_title', 'wbtm_bus_panel_endpoint_title');
	/******************************/
	function mep_license_expire_date($date) {
		if (empty($date) || $date == 'lifetime') {
			echo esc_html($date);
		}
		else {
			if (strtotime(current_time('Y-m-d H:i')) < strtotime(date('Y-m-d H:i', strtotime($date)))) {
				echo MP_Global_Function::date_format($date, 'full');
			}
			else {
				esc_html_e('Expired', 'bus-ticket-booking-with-seat-reservation');
			}
		}
	}
	/******************************/
	add_filter('wbtm_submenu_setings_panels', 'wbtm_register_license_tab_name', 90);
	function wbtm_register_license_tab_name($default_sec) {
		$wbtm_qr_settings = array(
			'page_nav' => __('<i class="fas fa-qrcode"></i> License', 'bus-ticket-booking-with-seat-reservation-qr-code'),
			'priority' => 10,
			'page_settings' => array(
				'section_20' => array(
					'title' => __('License Settings', 'bus-ticket-booking-with-seat-reservation-qr-code'),
					'nav_title' => __('General', 'bus-ticket-booking-with-seat-reservation-qr-code'),
					'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation-qr-code'),
					'options' => array()
				),
			),
		);
		$wbtm_qr_settings = array(
			'wbtm_license_settings' => $wbtm_qr_settings,
		);
		return array_merge($default_sec, $wbtm_qr_settings);
	}
	/**************************************/
	add_action('mage_settings_panel_content_wbtm_license_settings', 'wbtm_licensing_page', 5);
	function wbtm_licensing_page($form) {
		?>
		<div class='ttbm-licensing-page'>
			<h3>Tour Booking Manager For Woocommerce Licensing</h3>
			<p>Thanks you for using our Tour Booking Manager For Woocommerce Licensing plugin. This plugin is free and no license is required. We have some Additional addon to enhace feature of this plugin functionality. If you have any addon you need to enter a valid license for that plugin below.</p>
			<div class="mep_licensae_info"></div>
			<table class='wp-list-table widefat striped posts mep-licensing-table'>
				<thead>
				<tr>
					<th>Plugin Name</th>
					<th width=10%>Order No</th>
					<th width=15%>Expire on</th>
					<th width=30%>License Key</th>
					<th width=10%>Status</th>
					<th width=10%>Action</th>
				</tr>
				</thead>
				<tbody>
				<?php do_action('wbtm_license_page_addon_list'); ?>
				</tbody>
			</table>
		</div>
		<?php
	}
