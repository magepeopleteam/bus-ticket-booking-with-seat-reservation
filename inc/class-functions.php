<?php
	function wbtm_myaccount_query_vars($vars) {
		$vars[] = 'bus-panel';
		return $vars;
	}
	//add_filter('query_vars', 'wbtm_myaccount_query_vars', 0);
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
	//add_filter('woocommerce_account_menu_items', 'wbtm_bus_panel_menu_items');
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
	//add_filter('the_title', 'wbtm_bus_panel_endpoint_title');
