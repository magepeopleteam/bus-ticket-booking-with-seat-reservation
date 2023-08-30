<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Settings_Global')) {
		class WBTM_Settings_Global {
			public function __construct() {
				$this->settings_page();
			}
			public function settings_page() {
				$current_date = current_time('Y-m-d');
				$name = WBTM_Functions::get_name();
				$roleArr = array();
				$rolesObj = new WP_Roles;
				if ($rolesObj->roles) {
					foreach ($rolesObj->roles as $key => $role) {
						$roleArr[$key] = $role['name'];
					}
				}
				if ($post = get_page_by_path('bus-search-list', OBJECT, 'page')) {
					$id = $post->ID;
				}
				else {
					$id = 0;
				}
				$gen_settings = array(
					'page_nav' => __('<i class="fas fa fa-cog"></i> General Settings', 'bus-ticket-booking-with-seat-reservation'),
					'priority' => 10,
					'page_settings' => array(
						'section_4' => array(
							'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation'),
							'options' => array(
								array(
									'id' => 'disable_block_editor',
									'title' => esc_html__('Disable Block/Gutenberg Editor', 'bus-ticket-booking-with-seat-reservation'),
									'details' => esc_html__('If you want to disable WordPress\'s new Block/Gutenberg editor, please select Yes.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'select',
									'default' => 'yes',
									'args' => array(
										'yes' => esc_html__('Yes', 'bus-ticket-booking-with-seat-reservation'),
										'no' => esc_html__('No', 'bus-ticket-booking-with-seat-reservation')
									)
								),
								array(
									'id' => 'date_format',
									'title' => esc_html__('Date Picker Format', 'bus-ticket-booking-with-seat-reservation'),
									'details' => esc_html__('If you want to change Date Picker Format, please select format. Default  is D d M , yy.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'select',
									'default' => 'D d M , yy',
									'args' => array(
										'yy-mm-dd' => $current_date,
										'yy/mm/dd' => date_i18n('Y/m/d', strtotime($current_date)),
										'yy-dd-mm' => date_i18n('Y-d-m', strtotime($current_date)),
										'yy/dd/mm' => date_i18n('Y/d/m', strtotime($current_date)),
										'dd-mm-yy' => date_i18n('d-m-Y', strtotime($current_date)),
										'dd/mm/yy' => date_i18n('d/m/Y', strtotime($current_date)),
										'mm-dd-yy' => date_i18n('m-d-Y', strtotime($current_date)),
										'mm/dd/yy' => date_i18n('m/d/Y', strtotime($current_date)),
										'd M , yy' => date_i18n('j M , Y', strtotime($current_date)),
										'D d M , yy' => date_i18n('D j M , Y', strtotime($current_date)),
										'M d , yy' => date_i18n('M  j, Y', strtotime($current_date)),
										'D M d , yy' => date_i18n('D M  j, Y', strtotime($current_date)),
									)
								),
								array(
									'id' => 'date_format_short',
									'title' => esc_html__('Short Date  Format', 'bus-ticket-booking-with-seat-reservation'),
									'details' => esc_html__('If you want to change Short Date  Format, please select format. Default  is M , Y.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'select',
									'default' => 'M , Y',
									'args' => array(
										'M , Y' => date_i18n('M , Y', strtotime($current_date)),
										'M , y' => date_i18n('M , y', strtotime($current_date)),
										'M - Y' => date_i18n('M - Y', strtotime($current_date)),
										'M - y' => date_i18n('M - y', strtotime($current_date)),
										'F , Y' => date_i18n('F , Y', strtotime($current_date)),
										'F , y' => date_i18n('F , y', strtotime($current_date)),
										'F - Y' => date_i18n('F - y', strtotime($current_date)),
										'F - y' => date_i18n('F - y', strtotime($current_date)),
										'm - Y' => date_i18n('m - Y', strtotime($current_date)),
										'm - y' => date_i18n('m - y', strtotime($current_date)),
										'm , Y' => date_i18n('m , Y', strtotime($current_date)),
										'm , y' => date_i18n('m , y', strtotime($current_date)),
										'F' => date_i18n('F', strtotime($current_date)),
										'm' => date_i18n('m', strtotime($current_date)),
										'M' => date_i18n('M', strtotime($current_date)),
									)
								),
								array(
									'id' => 'wbtm_ticket_sale_close_date',
									'title' => esc_html__('Ticket sale off after date', 'bus-ticket-booking-with-seat-reservation'),
									'details' => esc_html__('Please select Seat sale off after date . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '',
									'type' => 'datepicker',
									'date_format' => 'dd-mm-yy',
									'placeholder' => current_time('Y-m-d'),
								),
								array(
									'id' => 'wbtm_ticket_sale_max_date',
									'title' => esc_html__('Maximum advanced day Sale', 'bus-ticket-booking-with-seat-reservation'),
									'details' => esc_html__('Please select Maximum advanced day Ticket Sale . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '30',
									'type' => 'text',
									'placeholder' => 30,
								),
								array(
									'id' => 'bus_menu_label',
									'title' => $name . ' ' . __('Label', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('If you want to change the bus label in the dashboard menu you can change here', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Bus',
									'placeholder' => __('Bus', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'bus_menu_slug',
									'title' => $name . ' ' . __('Slug', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter the slug name you want. Remember after change this slug you need to flush permalink, Just go to Settings->Permalink hit the Save Settings button', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'bus',
									'placeholder' => __('bus', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'bus_search_list_order',
									'title' => $name . ' ' . __('Search list order', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Search list order by time.', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'asc',
									'value' => 'asc',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'asc' => __('Lower to higher', 'bus-ticket-booking-with-seat-reservation'),
										'desc' => __('Higher to lower', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'bus_buffer_time',
									'title' => __('Buffer Time', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter here car buffer time in minute. By default is 0', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 0,
									'placeholder' => __('', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'same_bus_return_setting',
									'title' => __('Same bus return setting', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enable if you want to see the same bus should return option in the bus edit page. By default Disable', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'disable',
									'value' => 'disable',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'disable' => __('Disable', 'bus-ticket-booking-with-seat-reservation'),
										'enable' => __('Enable', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'bus_return_show',
									'title' => __('Show return field', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Disable if you don\'t want to show return field in search. By default Enable', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'enable',
									'value' => 'enable',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'disable' => __('Disable', 'bus-ticket-booking-with-seat-reservation'),
										'enable' => __('Enable', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'bus_return_discount',
									'title' => __('Return Discount Enable', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enable if you want round trip price discont. By default is No', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'no',
									'value' => 'no',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'yes' => __('Yes', 'bus-ticket-booking-with-seat-reservation'),
										'no' => __('No', 'bus-ticket-booking-with-seat-reservation')
									),
								),
								array(
									'id' => 'any_day_return',
									'title' => __('On/Off any date return switch', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('By default: Off', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'off',
									'value' => 'off',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'off' => __('Off', 'bus-ticket-booking-with-seat-reservation'),
										'on' => __('On', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'route_disable_switch',
									'title' => __('Show route disable switch', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('By default: Off', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'off',
									'value' => 'off',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'off' => __('Off', 'bus-ticket-booking-with-seat-reservation'),
										'on' => __('On', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'bus_seat_booked_on_order_status',
									'title' => __('Seat booked on status', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Seat will be booked in which state of seat order. <br> eg. If you want to showing seat as booked when seat status is "On hold" then check "On hold".'),
									'type' => 'checkbox_multi',
									'default' => array('1', '2'),
									// 'value'		    => array('option_2'),
									'args' => array(
										'3' => __('Pending payment', 'bus-ticket-booking-with-seat-reservation'),
										'4' => __('On hold', 'bus-ticket-booking-with-seat-reservation'),
										'1' => __('Processing', 'bus-ticket-booking-with-seat-reservation'),
										'2' => __('Completed', 'bus-ticket-booking-with-seat-reservation'),
										// '5'    => __('Cancelled','bus-ticket-booking-with-seat-reservation'),
										// '6'    => __('Refund','bus-ticket-booking-with-seat-reservation'),
										// '7'    => __('Failed','bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'search_target_page',
									'title' => __('Search Result Page', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be the global Search result page. By default the search result page is bus-search-list Page. If you want to change this you can select your own page from this list. Or also you can set particulr page in the shortcode also. Example: [wbtm-bus-search-form searh-page="page-slug-here"]', 'bus-ticket-booking-with-seat-reservation'),
									//'multiple'=> true,
									'type' => 'select2',
									'default' => $id,
									'args' => 'PAGES_IDS_ARRAY',
								),
								array(
									'id' => 'bus_booked_cancellation_buffer_time',
									'title' => __('Cancel Req. Allowed before', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter here car buffer time in Hours. By default is 0', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 0,
									'placeholder' => __('', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'bus_booked_cancellation_req_role',
									'title' => __('Cancel Req. Allowed User Role?', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please select the user role who can able to send cancel request of event order from My Account Page.'),
									'type' => 'checkbox_multi',
									'default' => array(),
									'args' => $roleArr,
								),
								array(
									'id' => 'bus_booked_auto_cancel',
									'title' => __('Auto Cancel?', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please select Yes if you want to automatically cancel the order, when user submit a cancellation request', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'no',
									'value' => 'no',
									'multiple' => false,
									'type' => 'select',
									'args' => array(
										'no' => __('No', 'bus-ticket-booking-with-seat-reservation'),
										'yes' => __('Yes', 'bus-ticket-booking-with-seat-reservation'),
									),
								),
								array(
									'id' => 'alter_image',
									'title' => __('Alter Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Alter Image ', 'bus-ticket-booking-with-seat-reservation'),
									'placeholder' => 'https://i.imgur.com/807vGSc.png',
									'type' => 'media',
								),
							)
						),
					),
				);
				$seat_panel_settings = array(
					'page_nav' => __('<i class="fas fa-user-cog"></i> Seat Panel Settings', 'bus-ticket-booking-with-seat-reservation'),
					'priority' => 10,
					'page_settings' => array(
						'section_1' => array(
							'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation'),
							'options' => array(
								array(
									'id' => 'wbtm_seat_type_adult_label',
									'title' => __('Adult Seat Type Label ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter the lable of Adult Seat Type', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'Adult',
									'type' => 'text',
									'placeholder' => __('Adult', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'wbtm_seat_type_child_label',
									'title' => __('Child Seat Type Label ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter the lable of Child Seat Type', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'Child',
									'type' => 'text',
									'placeholder' => __('Child', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'wbtm_seat_type_infant_label',
									'title' => __('Infant Seat Type Label ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter the lable of Infant Seat Type', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'Infant',
									'type' => 'text',
									'placeholder' => __('Infant', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'wbtm_seat_type_special_label',
									'title' => __('Special Seat Type Label ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter the lable of Special Seat Type', 'bus-ticket-booking-with-seat-reservation'),
									'default' => 'Special',
									'type' => 'text',
									'placeholder' => __('Special', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'useer_deck_title',
									'title' => __('Upper Deck Title ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'text',
									'placeholder' => __('Upper Deck', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'lower_deck_title',
									'title' => __('Lower Deck Title ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'text',
									'placeholder' => __('Lower Deck', 'bus-ticket-booking-with-seat-reservation'),
								),
								array(
									'id' => 'diriver_image',
									'title' => __('Driver Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Driver Image ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'media',
								),
								array(
									'id' => 'seat_blank_image',
									'title' => __('Blank Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Blank Seat Image', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'media',
								),
								array(
									'id' => 'seat_active_image',
									'title' => __('Cart Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Cart Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'media',
								),
								array(
									'id' => 'seat_booked_image',
									'title' => __('Booked Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Booked Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'media',
								),
								array(
									'id' => 'seat_sold_image',
									'title' => __('Sold Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Sold Seat Image ', 'bus-ticket-booking-with-seat-reservation'),
									// 'placeholder'	=> 'https://i.imgur.com/GD3zKtz.png',
									'type' => 'media',
								),
							)
						),
					),
				);
				$translation_settings = array(
					'page_nav' => __('<i class="fas fa-language"></i> Translation Settings', 'bus-ticket-booking-with-seat-reservation'),
					'priority' => 10,
					'page_settings' => array(
						'section_4' => array(
							// 'title' 	=> 	__('This is Section Title 40','bus-ticket-booking-with-seat-reservation'),
							// 'nav_title' 	=> 	__('This is nav Title 40','bus-ticket-booking-with-seat-reservation'),
							'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation'),
							'options' => array(
								array(
									'id' => 'wbtm_buy_ticket_text',
									'title' => __('BUY TICKET', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as To Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'BUY TICKET'
								),
								array(
									'id' => 'wbtm_from_text',
									'title' => __('From', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as To Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'From:'
								),
								array(
									'id' => 'wbtm_to_text',
									'title' => __('To:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as To Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'To:'
								),
								array(
									'id' => 'wbtm_date_of_journey_text',
									'title' => __('Date of Journey:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Date of Journey Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Date of Journey:'
								),
								array(
									'id' => 'wbtm_return_date_text',
									'title' => __('Return Date:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Date of Journey Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Return Date:'
								),
								array(
									'id' => 'wbtm_one_way_text',
									'title' => __('One Way', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as One Way Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'One Way'
								),
								array(
									'id' => 'wbtm_return_text',
									'title' => __('Return', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Return Search form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Return'
								),
								array(
									'id' => 'wbtm_search_buses_text',
									'title' => __('SEARCH BUSES', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as SEARCH BUSES button form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'SEARCH BUSES'
								),
								array(
									'id' => 'wbtm_please_select_text',
									'title' => __('Please Select', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Please Select button form page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Please Select'
								),
								array(
									'id' => 'wbtm_no_bus_found_text',
									'title' => __('No Bus Found!', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as No Bus Found!.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'No Bus Found!'
								),
								array(
									'id' => 'wbtm_already_in_cart_text',
									'title' => __('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Already Added in cart !.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Already Added in cart !'
								),
								array(
									'id' => 'wbtm_route_text',
									'title' => __('Route', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Route Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Route'
								),
								array(
									'id' => 'wbtm_date_text',
									'title' => __('Date:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Date Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Date:'
								),
								array(
									'id' => 'wbtm_start_time_text',
									'title' => __('Start Time:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Start Time.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Start Time:'
								),
								array(
									'id' => 'wbtm_bus_name_text',
									'title' => __('Bus Name:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Bus Name Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Bus Name:'
								),
								array(
									'id' => 'wbtm_departing_text',
									'title' => __('DEPARTING', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as DEPARTING Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'DEPARTING'
								),
								array(
									'id' => 'wbtm_coach_no_text',
									'title' => __('COACH NO', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as COACH NO Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'COACH NO'
								),
								array(
									'id' => 'wbtm_starting_text',
									'title' => __('STARTING', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as STARTING Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'STARTING'
								),
								array(
									'id' => 'wbtm_end_text',
									'title' => __('END', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as END Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'END'
								),
								array(
									'id' => 'wbtm_fare_text',
									'title' => __('FARE', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as FARE Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'FARE'
								),
								array(
									'id' => 'wbtm_type_text',
									'title' => __('TYPE', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as TYPE Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'TYPE'
								),
								array(
									'id' => 'wbtm_passenger_capacity_text',
									'title' => __('Passenger Capacity :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Passenger Capacity :.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Passenger Capacity :'
								),
								array(
									'id' => 'wbtm_bus_not_availabe_text',
									'title' => __('Bus not availabe in this date :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Bus not availabe in this date.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Uhu! No Cheating, This bus available only in the particular date. :)'
								),
								array(
									'id' => 'wbtm_arrival_text',
									'title' => __('ARRIVAL', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as ARRIVAL Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'ARRIVAL'
								),
								array(
									'id' => 'wbtm_seats_available_text',
									'title' => __('SEATS AVAILABLE ', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as SEATS AVAILABLE Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'SEATS AVAILABLE'
								),
								array(
									'id' => 'wbtm_view_text',
									'title' => __('VIEW', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as VIEW Search Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'VIEW'
								),
								array(
									'id' => 'wbtm_view_seats_text',
									'title' => __('View Seats', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as View Seats button Result Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'View Seats'
								),
								array(
									'id' => 'wbtm_start_arrival_time_text',
									'title' => __('Start & Arrival Time', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Start & Arrival Time Details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Start & Arrival Time'
								),
								array(
									'id' => 'wbtm_seat_no_text',
									'title' => __('Seat No', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Seat No Details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Seat No'
								),
								array(
									'id' => 'wbtm_seat_text',
									'title' => __('Seat', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Seat Details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Seat'
								),
								array(
									'id' => 'wbtm_seat_available_text',
									'title' => __('Seat Available', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Seat Available.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Seat Available'
								),
								array(
									'id' => 'wbtm_passenger_info_seat_text',
									'title' => __('Passenger Info seat :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Passenger Info seat :', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Passenger Info seat :'
								),
								array(
									'id' => 'wbtm_qty_text',
									'title' => __('Qty :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Qty :', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Qty :'
								),
								array(
									'id' => 'wbtm_sub_total_text',
									'title' => __('Sub Total :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Sub Total :', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Sub Total :'
								),
								array(
									'id' => 'wbtm_extra_bag_text',
									'title' => __('Extra Bag :', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Extra Bag :', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Extra Bag :'
								),
								array(
									'id' => 'wbtm_extra_bag_price_text',
									'title' => __('Extra Bag Price:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Extra Bag Price :', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Extra Bag Price :'
								),
								array(
									'id' => 'wbtm_schedule_text',
									'title' => __('Schedule', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Schedule.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Schedule'
								),
								array(
									'id' => 'wbtm_image_text',
									'title' => __('Image', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Image.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Image'
								),
								array(
									'id' => 'wbtm_remove_text',
									'title' => __('Remove', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Remove Details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Remove'
								),
								array(
									'id' => 'wbtm_total_text',
									'title' => __('Total', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Total Details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Total'
								),
								array(
									'id' => 'wbtm_book_now_text',
									'title' => __('BOOK NOW', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as BOOK NOW button details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'BOOK NOW'
								),
								array(
									'id' => 'wbtm_bus_no_text',
									'title' => __('Bus No:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Bus No single bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Bus No:'
								),
								array(
									'id' => 'wbtm_total_seat_text',
									'title' => __('Total Seat:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Total Seat  bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Total Seat:'
								),
								array(
									'id' => 'wbtm_boarding_points_text',
									'title' => __('Boarding Points', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Boarding Points single bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Boarding Points'
								),
								array(
									'id' => 'wbtm_dropping_points_text',
									'title' => __('Dropping Points', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Dropping Points single bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Dropping Points'
								),
								array(
									'id' => 'wbtm_select_journey_date_text',
									'title' => __('Select Journey Date', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Select Journey Date single bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Select Journey Date'
								),
								array(
									'id' => 'wbtm_search_text',
									'title' => __('Search', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as search button single bus details Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Search'
								),
								array(
									'id' => 'wbtm_seat_list_text',
									'title' => __('Seat List:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as search button single bus seat list in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Seat List:'
								),
								// Cart Intem Strings
								array(
									'id' => 'wbtm_cart_name_text',
									'title' => __('Name:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Name in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Name:'
								),
								array(
									'id' => 'wbtm_cart_email_text',
									'title' => __('Email:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Email in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Email:'
								),
								array(
									'id' => 'wbtm_cart_phone_text',
									'title' => __('Phone:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Phone in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Phone:'
								),
								array(
									'id' => 'wbtm_cart_gender_text',
									'title' => __('Gender:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Gender in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Gender:'
								),
								array(
									'id' => 'wbtm_cart_address_text',
									'title' => __('Address:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Address in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Address:'
								),
								array(
									'id' => 'wbtm_cart_journey_date_text',
									'title' => __('Journey Date:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Journey Date in cart Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Journey Date:'
								),
								array(
									'id' => 'wbtm_return_trip_text_heading',
									'title' => __('Return Trip:', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as Return Trip in search Page.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Return Trip:'
								),
								array(
									'id' => 'wbtm_anydate_return_desc_text',
									'title' => __('Any Date Return Description', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Enter the text which you want to display as any date return description.', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Same ticket will be valid for return up to next 15 days'
								),
								array(
									'id' => 'wbtm_menu_translate_purchase_ticket',
									'title' => __('Translate Purchase Ticket', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Translate purchase ticket menu text', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'text',
									'default' => 'Purchase Ticket'
								),
							)
						),
					),
				);
				$color_settings = array(
					'page_nav' => __('<i class="fas fa-palette"></i> Color & Styles', 'bus-ticket-booking-with-seat-reservation'),
					'priority' => 10,
					'page_settings' => array(
						'section_4' => array(
							// 'title' 	=> 	__('This is Section Title 40','bus-ticket-booking-with-seat-reservation'),
							// 'nav_title' 	=> 	__('This is nav Title 40','bus-ticket-booking-with-seat-reservation'),
							'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation'),
							'options' => array(
								/**
								 * Search Button Background & Text Color
								 */
								array(
									'id' => 'wbtm_search_btn_bg_color',
									'title' => __('Search Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be applied in Search Form Search Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#0a4b78',
									'value' => '#0a4b78',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_btn_text_color',
									'title' => __('Search Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be applied in Search Form Search Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#fff',
									'value' => '#fff',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_form_route_item_color',
									'title' => __('Search Form Dropdown Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be applied in Search Form Dropdown Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#333',
									'value' => '#333',
									'type' => 'colorpicker',
								),
								/**
								 * Search Listing Page Next Date Tab Default & Active Background & Text Color
								 */
								array(
									'id' => 'wbtm_search_next_date_bg_color',
									'title' => __('Search Next date Tab Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in Search Listing Page Next Five Date Tab Default Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#f2f2f2',
									'value' => '#f2f2f2',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_next_date_text_color',
									'title' => __('Search Next date Tab Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in Search Listing Page Next Five Date Tab Default Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#0a4b78',
									'value' => '#0a4b78',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_next_date_active_bg_color',
									'title' => __('Search Next date Active Tab Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in Search Listing Page Next Five Date Tab Active Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#777777',
									'value' => '#777777',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_next_date_active_text_color',
									'title' => __('Search Next date Active Tab Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in Search Listing Page Next Five Date Tab Active Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#ffffff',
									'value' => '#ffffff',
									'type' => 'colorpicker',
								),
								/**
								 * Search Listing Page Route List Title Background & Text Color
								 */
								array(
									'id' => 'wbtm_search_route_list_title_bg_color',
									'title' => __('Search List Route List Title Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change the Route Text & Journey Date Section Background Color just above the search list table', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#777777',
									'value' => '#777777',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_route_list_title_text_color',
									'title' => __('Search List Route List  Title Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change the Route Text & Journey Date Section Text Color just above the search list table', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#000',
									'value' => '#000',
									'type' => 'colorpicker',
								),
								/**
								 * Search Listing Table  Background & Text Color
								 */
								array(
									'id' => 'wbtm_search_list_table_bg_color',
									'title' => __('Search List Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in the Search Table Top and Bottom background color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#0a4b78',
									'value' => '#0a4b78',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_list_table_text_color',
									'title' => __('Search List Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('This will be applied in the Search Table Top and Bottom Text color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#ffffff',
									'value' => '#ffffff',
									'type' => 'colorpicker',
								),
								/**
								 * View Seat Button Background & Text Color
								 */
								array(
									'id' => 'wbtm_view_seat_btn_bg_color',
									'title' => __('View Seat Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change View Seat Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#0a4b78',
									'value' => '#0a4b78',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_view_seat_btn_text_color',
									'title' => __('View Seat Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change View Seat Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#fff',
									'value' => '#fff',
									'type' => 'colorpicker',
								),
								/**
								 * Book Now Button Background & Text Color
								 */
								array(
									'id' => 'wbtm_book_now_btn_bg_color',
									'title' => __('Book Now Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change Book Now Button Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#0a4b78',
									'value' => '#0a4b78',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_book_now_btn_text_color',
									'title' => __('Book Now Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be change Book Now Button Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#fff',
									'value' => '#fff',
									'type' => 'colorpicker',
								),
								/**
								 * Search List Bus Details View Title Background & Text Color, It will be applied in Available seat, Passenger Into Title, Seleted Seat Table Title Section.
								 */
								array(
									'id' => 'wbtm_search_list_bus_details_title_bg_color',
									'title' => __('Search List Bus Details Title Background Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be applied in Available seat, Passenger Into Title, Seleted Seat Table Title Section. ', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#ddd',
									'value' => '#ddd',
									'type' => 'colorpicker',
								),
								array(
									'id' => 'wbtm_search_list_bus_details_title_text_color',
									'title' => __('Search List Bus Details Title Text Color', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('It will be applied in Available seat, Passenger Into Title, Seleted Seat Table Title Section. ', 'bus-ticket-booking-with-seat-reservation'),
									'default' => '#000',
									'value' => '#000',
									'type' => 'colorpicker',
								),
							)
						),
					),
				);
				$custom_css_settings = array(
					'page_nav' => __('<i class="fas fa-file-code"></i> Custom CSS', 'bus-ticket-booking-with-seat-reservation'),
					'priority' => 10,
					'page_settings' => array(
						'section_4' => array(
							'description' => __('This is section details', 'bus-ticket-booking-with-seat-reservation'),
							'options' => array(
								array(
									'id' => 'wbtm_customn_css_code',
									'title' => __('Custom CSS', 'bus-ticket-booking-with-seat-reservation'),
									'details' => __('Please enter your custom CSS Code Here', 'bus-ticket-booking-with-seat-reservation'),
									'value' => __('', 'bus-ticket-booking-with-seat-reservation'),
									'default' => __('', 'bus-ticket-booking-with-seat-reservation'),
									'type' => 'textarea',
									'placeholder' => __('Your Custom CSS Code Here', 'bus-ticket-booking-with-seat-reservation'),
								),
							)
						),
					),
				);
				$args = array(
					'add_in_menu' => true,
					'menu_type' => 'sub',
					'menu_name' => __('Settings', 'bus-ticket-booking-with-seat-reservation'),
					'menu_title' => __('Settings', 'bus-ticket-booking-with-seat-reservation'),
					'page_title' => __('Settings', 'bus-ticket-booking-with-seat-reservation'),
					'menu_page_title' => __('Settings', 'bus-ticket-booking-with-seat-reservation'),
					'capability' => "manage_options",
					'cpt_menu' => "edit.php?post_type=wbtm_bus",
					'menu_slug' => "wbtm-bus-manager-settings",
					'option_name' => "wbtm_bus_settings",
					'menu_icon' => "dashicons-iWBTM-filter",
					'item_name' => "Bus Manager Settings",
					'item_version' => "1.0.0",
					'panels' => apply_filters('wbtm_submenu_setings_panels', array(
						'gensettings' => $gen_settings,
						'seat_panel_settings' => $seat_panel_settings,
						'transsettings' => $translation_settings,
						'colorsettings' => $color_settings,
						'customcss' => $custom_css_settings,
						// 'license_settings' => $license_key_settings
					)),
				);
				$AddThemePage = new AddThemePage($args);
			}
		}
		new WBTM_Settings_Global();
	}