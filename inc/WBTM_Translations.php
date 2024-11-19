<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Translations')) {
		class WBTM_Translations {
			//public function __construct() {}
			//*************************//
			public static function text_journey_date() { return esc_html__('Journey Date', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_return_date() { return esc_html__('Return Date (Optional)', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_date() { return esc_html__('Date', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_order_date() { return esc_html__('Order Date', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_buy_ticket() { return esc_html__('BUY TICKET', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_from() { return esc_html__('From', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_to() { return esc_html__('To', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_search() { return esc_html__('Search', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_please_select() { return esc_html__('Please Select', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_bp() { return esc_html__('Boarding', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_dp() { return esc_html__('Dropping', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_start_point() { return esc_html__('Starting Points', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_pickup() { return esc_html__('Pickup', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_pickup_point() { return esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_drop_off() { return esc_html__('Drop-Off', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_drop_off_point() { return esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_pin() { return esc_html__('PIN', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_bus_type() { return esc_html__('Bus Type', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_attendee_id() { return esc_html__('Attendee ID', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_order_status() { return esc_html__('Order Status', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_payment_method() { return esc_html__('Payment Method', 'bus-ticket-booking-with-seat-reservation'); }
			
			public static function text_return_trip() { return esc_html__('Return Trip', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_upper_deck() { return esc_html__('Upper Deck', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_already_in_cart() { return esc_html__('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_no_bus() { return esc_html__('No Bus Found !', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_no_ticket() { return esc_html__('No Ticket found !', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_no_seat_plan() { return esc_html__('No Seat Plan Available !', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_no_seat() { return esc_html__('No Seat Available !!', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_bus_close_msg() { return esc_html__('This bus Now Closed.', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_available_seat() { return esc_html__('Available Seat', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_already_sold() { return esc_html__('Already Sold', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_select_wrong_route() { return esc_html__('You select Wrong Route !', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_select_route() { return esc_html__('Please select Starting Point !', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_schedule() { return esc_html__('Schedule', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_coach_type() { return esc_html__('Coach Type', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_ticket_type() { return esc_html__('Seat Type', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_seat() { return esc_html__('Seat', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_seat_name() { return esc_html__('Seat', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_name() { return esc_html__('Name', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_image() { return esc_html__('Image', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_qty() { return esc_html__('Quantity', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_total_qty() { return esc_html__('Total Quantity', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_price() { return esc_html__('Price', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_fare() { return esc_html__('Fare', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_total() { return esc_html__('Total', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_ticket_sub_total() { return esc_html__('Ticket Sub total', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_order_total() { return esc_html__('Order Total', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_passenger_capacity() { return esc_html__('Passenger Capacity', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_passenger_info() { return esc_html__('Passenger Information', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_ex_service() { return esc_html__('Extra Services', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_ex_service_sub_total() { return esc_html__('Extra Service Sub total', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_adult() { return esc_html__('Adult', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_child() { return esc_html__('Child', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_infant() { return esc_html__('Infant', 'bus-ticket-booking-with-seat-reservation'); }
			//*************************//
			public static function text_action() { return esc_html__('Action', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_available() { return esc_html__('Seats Available', 'bus-ticket-booking-with-seat-reservation'); }
			public static function duration_text() { return esc_html__('Duration :', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_view_seat() { return esc_html__('View Seats', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_close_seat() { return esc_html__('Close Seat', 'bus-ticket-booking-with-seat-reservation'); }
			public static function text_book_now() { return esc_html__('Book Now', 'bus-ticket-booking-with-seat-reservation'); }
		}
		//new WBTM_Translations();
	}