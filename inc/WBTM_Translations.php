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
			private static function get_translation($key, $default) {
				$translations = get_option('wbtm_translations', array());
				return isset($translations[$key]) ? $translations[$key] : $default;
			}

			public static function text_journey_date() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Journey Date', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_return_date() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Return Date (Optional)', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_date() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Date', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_order_date() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Order Date', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_buy_ticket() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('BUY TICKET', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_from() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('From', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_to() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('To', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_search() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Search', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_please_select() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Please Select', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_bp() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Boarding', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_dp() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Dropping', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_start_point() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Starting Points', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_pickup() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Pickup', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_pickup_point() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_drop_off() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Drop-Off', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_drop_off_point() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_pin() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('PIN', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_bus_type() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Bus Type', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_bus_operator() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Bus Operator', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_attendee_id() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Attendee ID', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_passenger_type() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Passenger Type', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_order_status() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Order Status', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_payment_method() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Payment Method', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_return_trip() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Return Trip', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_upper_deck() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Upper Deck', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_already_in_cart() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_no_bus() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('No Bus Found !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_no_ticket() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('No Ticket found !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			public static function text_no_security_issue() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Security Issue Found !', 'bus-ticket-booking-with-seat-reservation')
				);
			}
			
			public static function text_no_seat_plan() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('No Seat Plan Available !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_no_seat() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('No Seat Available !!', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_bus_close_msg() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('This bus Now Closed.', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_available_seat() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Available Seat', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_already_sold() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Already Sold', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_select_wrong_route() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('You select Wrong Route !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_select_route() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Please select Starting Point !', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_schedule() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Schedule', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_coach_type() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Coach Type', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_ticket_type() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Seat Type', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_seat() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Seat', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_seat_name() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Seat', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_name() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Name', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_image() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Image', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_qty() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Quantity', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_total_qty() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Total Quantity', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_price() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Price', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_fare() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Fare', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_total() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Total', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_ticket_sub_total() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Ticket Sub total', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_order_total() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Order Total', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_passenger_capacity() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Passenger Capacity', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_passenger_info() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Passenger Information', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_ex_service() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Extra Services', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_ex_service_sub_total() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Extra Service Sub total', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_adult() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Adult', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_child() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Child', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_infant() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Infant', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_action() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Action', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_available() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Seats Available', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_date_available_status() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Available', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_date_unavailable_status() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Unavailable', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function duration_text() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Duration :', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_view_seat() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('View Seats', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_close_seat() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Close Seat', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_book_now() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('Book Now', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
			
			public static function text_seat_reserved() { 
				return self::get_translation(__FUNCTION__,
					esc_html__('This seat is already reserved.', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Seat related translations
			public static function text_seat_details() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Seat Details', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_seat_selection() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Seat Selection', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Passenger related translations
			public static function text_passenger_details() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Passenger Details', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// filter translations
			public static function text_filter() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Filter', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_reset() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Reset', 'bus-ticket-booking-with-seat-reservation')
				); 
			}
						
			public static function text_mobile_number() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Mobile Number', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_email() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Email', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_gender() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Gender', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Booking related translations  
			public static function text_booking_date() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Booking Date', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_booking_id() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Booking ID', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_booking_status() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Booking Status', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Bus details translations
			public static function text_departure_time() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Departure Time', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_arrival_time() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Arrival Time', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_route_details() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Route Details', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Payment related translations
			public static function text_payment_status() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Payment Status', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_transaction_id() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Transaction ID', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Message translations
			public static function text_booking_success() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Booking Successful!', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_booking_failed() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Booking Failed!', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_seat_unavailable() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Selected seat(s) no longer available!', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Button translations
			public static function text_proceed() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Proceed', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_cancel() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Cancel', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_print_ticket() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Print Ticket', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Additional Info translations
			public static function text_terms_conditions() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Terms & Conditions', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			public static function text_note() { 
				return self::get_translation(__FUNCTION__, 
					esc_html__('Note', 'bus-ticket-booking-with-seat-reservation')
				); 
			}

			// Additional Error Messages
			public static function text_error_required_field() {
				return self::get_translation(__FUNCTION__,
					esc_html__('This field is required', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_error_invalid_email() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Please enter a valid email address', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_error_invalid_phone() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Please enter a valid phone number', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Ticket Status Messages
			public static function text_ticket_confirmed() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Ticket Confirmed', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_ticket_pending() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Ticket Pending', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_ticket_cancelled() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Ticket Cancelled', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Booking Form Labels
			public static function text_passenger_name() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Passenger Name', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_age() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Age', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_nid() {
				return self::get_translation(__FUNCTION__,
					esc_html__('NID/Passport', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Additional Bus Information
			public static function text_boarding_point() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Boarding Point', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_dropping_point() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Dropping Point', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_journey_time() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Journey Time', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_distance() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Distance', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Seat Information
			public static function text_seat_number() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Seat Number', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_seat_status() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Seat Status', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Price Related
			public static function text_tax() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Tax', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_discount() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Discount', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_grand_total() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Grand Total', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			// Additional Status Messages
			public static function text_payment_pending() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Payment Pending', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_payment_complete() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Payment Complete', 'bus-ticket-booking-with-seat-reservation')
				);
			}

			public static function text_payment_failed() {
				return self::get_translation(__FUNCTION__,
					esc_html__('Payment Failed', 'bus-ticket-booking-with-seat-reservation')
				);
			}
		}
		//new WBTM_Translations();
	}