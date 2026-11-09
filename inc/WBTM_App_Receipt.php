<?php
/** Owned native receipts, history, PDF capabilities and transactional mail actions. */
if ( ! defined( 'ABSPATH' ) ) { die; }

if ( ! class_exists( 'WBTM_App_Receipt' ) ) {
	class WBTM_App_Receipt {
		const EMAIL_ACTION = 'wbtm_app_native_ticket_email';
		public function __construct() {
			add_action( self::EMAIL_ACTION, array( __CLASS__, 'send_queued_ticket_mail' ) );
			add_filter( 'woocommerce_defer_transactional_emails', array( __CLASS__, 'defer_checkout_notifications' ) );
			add_action( 'rest_api_init', array( $this, 'routes' ) );
			add_action( 'template_redirect', array( $this, 'download' ), 0 );
		}
		/** Woo installs notification hooks at init, before REST dispatch starts. */
		public static function defer_checkout_notifications( $defer ) {
			if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return $defer; }
			$route = is_string( $_GET['rest_route'] ?? null ) ? trim( wp_unslash( $_GET['rest_route'] ), '/' ) : '';
			$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
			return 'wbtm-app/v1/native-checkout/submit' === $route || preg_match( '#/wp-json/wbtm-app/v1/native-checkout/submit/?$#D', $path ) ? true : $defer;
		}
		public function routes() {
			foreach ( array( array( '/native-bookings', 'GET', 'bookings' ), array( '/native-bookings/(?P<id>\d+)', 'GET', 'booking' ), array( '/native-bookings/(?P<id>\d+)/ticket', 'POST', 'ticket' ), array( '/native-bookings/(?P<id>\d+)/email', 'POST', 'email' ) ) as $route ) {
				register_rest_route( WBTM_App_Config::REST_NAMESPACE, $route[0], array( 'methods' => $route[1], 'callback' => array( $this, $route[2] ), 'permission_callback' => array( 'WBTM_App_Guest', 'require_principal' ) ) );
			}
		}
		public static function owned_order( $id, array $p ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $id ) ) : false;
			if ( ! $order || 'shop_order' !== $order->get_type() ) { return WBTM_App_Native_Checkout::error( 'Booking not found.', 404 ); }
			$owned = 'customer' === $p['kind'] ? ( $p['user_id'] > 0 && (int) $order->get_customer_id() === (int) $p['user_id'] ) : ( 0 === (int) $order->get_customer_id() && hash_equals( (string) $order->get_meta( '_wbtm_app_guest_owner' ), $p['owner'] ) );
			$bus = false; foreach ( $order->get_items() as $item ) { if ( $item->get_meta( '_wbtm_bus_id' ) ) { $bus = true; break; } }
			if ( ! $owned || ( ! $bus && ! self::native_review_order( $order, $p ) ) ) { return WBTM_App_Native_Checkout::error( 'Booking not found.', 404 ); }
			return $order;
		}
		/** A crash after the first order save can precede line-item persistence. */
		private static function native_review_order( $order, array $p ) {
			if ( ! $order->get_meta( '_wbtm_app_native_processing_review' ) || ! $order->get_meta( '_wbtm_app' ) || 'wbtm-native-app' !== $order->get_created_via() || ! hash_equals( (string) $order->get_meta( '_wbtm_app_native_owner' ), $p['owner'] ) ) { return false; }
			$id = (string) $order->get_meta( '_wbtm_app_native_checkout_id' ); $journeys = $order->get_meta( '_wbtm_app_native_journeys' );
			if ( ! preg_match( '/^[a-f0-9]{32}$/D', $id ) || ! is_array( $journeys ) || count( $journeys ) < 1 || count( $journeys ) > 2 ) { return false; }
			foreach ( $journeys as $journey ) {
				if ( ! is_array( $journey ) || (int) ( $journey['bus_id'] ?? 0 ) < 1 || ! is_string( $journey['bp'] ?? null ) || '' === $journey['bp'] || ! is_string( $journey['dp'] ?? null ) || '' === $journey['dp'] || $journey['bp'] === $journey['dp'] || ! is_string( $journey['date'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $journey['date'] ) || (int) ( $journey['passenger_count'] ?? 0 ) < 1 || (int) $journey['passenger_count'] > 20 ) { return false; }
			}
			$claim = WBTM_App_Guest::fresh_option( 'wbtm_app_native_order_' . $id );
			return is_array( $claim ) && (int) ( $claim['order_id'] ?? 0 ) === $order->get_id() && hash_equals( (string) ( $claim['owner'] ?? '' ), $p['owner'] );
		}
		public static function journeys_from_quotes( array $quotes ) {
			$result = array(); foreach ( $quotes as $q ) {
				$t = $q['trip']; $seats = array(); foreach ( $q['tickets'] as $ticket ) { $seats[] = $ticket['seat_name']; }
				$result[] = array( 'bus_id' => (int) $t['bus_id'], 'name' => get_the_title( $t['bus_id'] ), 'bp' => $t['bp'], 'dp' => $t['dp'], 'date' => $t['date'], 'departure_time' => $t['bp_time'], 'arrival_time' => $t['dp_time'], 'seats' => $seats, 'passenger_count' => (int) $q['passenger_count'], 'pickup' => $q['pickup'], 'dropoff' => $q['dropoff'] );
			} return $result;
		}
		private static function journeys_from_order( $order ) {
			$result = array(); foreach ( $order->get_items() as $item ) {
				$id = (int) $item->get_meta( '_wbtm_bus_id' ); if ( ! $id ) { continue; }
				$tickets = (array) $item->get_meta( '_wbtm_ticket_info' ); $seats = array(); $count = 0; foreach ( $tickets as $ticket ) { if ( ! is_array( $ticket ) ) { continue; } $seats[] = WBTM_App_Inventory::text( $ticket['seat_name'] ?? $ticket['ticket_name'] ?? '' ); $count += (int) ( $ticket['ticket_qty'] ?? 1 ); }
				$time = (string) $item->get_meta( '_wbtm_bp_time' );
				$result[] = array( 'bus_id' => $id, 'name' => $item->get_name(), 'bp' => (string) $item->get_meta( '_wbtm_bp' ), 'dp' => (string) $item->get_meta( '_wbtm_dp' ), 'date' => substr( $time, 0, 10 ), 'departure_time' => $time, 'arrival_time' => (string) $item->get_meta( '_wbtm_dp_time' ), 'seats' => $seats, 'passenger_count' => $count, 'pickup' => (string) $item->get_meta( '_wbtm_pickup_point' ), 'dropoff' => (string) $item->get_meta( '_wbtm_drop_off_point' ) );
			} return $result;
		}
		public static function pdf_status( $order ) {
			if ( ! class_exists( 'WBTM_Customer_Portal' ) || ! class_exists( 'WBTM_Pro_Pdf' ) || ! WBTM_Pro_Pdf::is_mpdf_available() ) { return array( 'available' => false, 'reason' => __( 'PDF tickets are unavailable on this site.', 'addon-bus--ticket-booking-with-seat-pro' ) ); }
			if ( $order->get_meta( WBTM_App_Lifecycle::EXPIRED ) || $order->get_meta( '_wbtm_app_native_processing_review' ) || ! WBTM_Customer_Portal::is_ticket_ready( $order->get_status() ) ) { return array( 'available' => false, 'reason' => __( 'Your ticket PDF will be available when the operator confirms this booking.', 'addon-bus--ticket-booking-with-seat-pro' ) ); }
			return array( 'available' => true, 'reason' => '' );
		}
		public static function mail_configured() {
			$override = apply_filters( 'wbtm_app_mail_configured', null ); if ( is_bool( $override ) ) { return $override; }
			$fluent = get_option( 'fluentmail-settings', array() ); if ( ! empty( $fluent['connections'] ) ) { return true; }
			$smtp = get_option( 'wp_mail_smtp', array() ); if ( ! empty( $smtp['mail']['mailer'] ) && 'mail' !== $smtp['mail']['mailer'] ) { return true; }
			if ( isset( $GLOBALS['phpmailer'] ) && 'smtp' === $GLOBALS['phpmailer']->Mailer && ! empty( $GLOBALS['phpmailer']->Host ) ) { return true; }
			$path = preg_split( '/\s+/', (string) ini_get( 'sendmail_path' ) ); return ! empty( $path[0] ) && is_executable( $path[0] );
		}
		/** Queue the existing plugin PDF email only after the order is fully saved. */
		public static function queue_ticket_mail( $order ) {
			if ( ! apply_filters( 'wbtm_app_queue_ticket_mail', true, $order ) ) { return false; }
			if ( ! self::mail_configured() || ! self::pdf_status( $order )['available'] || 'yes' !== WBTM_Global_Function::get_settings( 'wbtm_email_settings', 'pdf_send_status', 'yes' ) || ! in_array( $order->get_status(), (array) WBTM_Global_Function::get_settings( 'wbtm_email_settings', 'pdf_email_status', array( 'processing', 'completed' ) ), true ) ) { return false; }
			$args = array( $order->get_id() );
			try { $queued = function_exists( 'as_enqueue_async_action' ) ? as_enqueue_async_action( self::EMAIL_ACTION, $args, 'wbtm-app', true ) : wp_schedule_single_event( time(), self::EMAIL_ACTION, $args, true ); }
			catch ( Throwable $error ) { return false; }
			if ( $queued && ! is_wp_error( $queued ) ) { $order->update_meta_data( '_wbtm_app_mail_status', 'queued' ); $order->save_meta_data(); return true; }
			return false;
		}
		public static function send_queued_ticket_mail( $id ) {
			$result = WBTM_App_Auth::locked( 'native_email_' . absint( $id ), static function() use ( $id ) {
				$order = wc_get_order( absint( $id ) );
				if ( ! $order || 'wbtm-native-app' !== $order->get_created_via() || ! $order->get_meta( '_wbtm_app_native_processed' ) || $order->get_meta( '_wbtm_app_native_processing_review' ) ) { return; }
				$cleanup = self::observe_mail( $order );
				try { do_action( 'wbtm_send_mail', $order->get_id() ); }
				finally { $cleanup(); }
			} );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Ticket email is busy; check the existing delivery before resending.' ); }
		}
		public static function email_status( $order ) {
			if ( $order->get_meta( '_wbtm_app_native_processing_review' ) ) { return array( 'status' => 'unknown', 'message' => __( 'Your order needs an operator status check before an email can be sent. Your receipt is saved in My Tickets.', 'addon-bus--ticket-booking-with-seat-pro' ) ); }
			$status = (string) $order->get_meta( '_wbtm_app_mail_status' );
			if ( ! self::mail_configured() && ! in_array( $status, array( 'sent', 'queued' ), true ) ) { return array( 'status' => 'not_configured', 'message' => __( 'Email delivery is not configured by the operator. Your booking is saved in My Tickets.', 'addon-bus--ticket-booking-with-seat-pro' ) ); }
			$messages = array( 'sent' => __( 'The email was accepted by the mail service. Check your inbox and spam folder.', 'addon-bus--ticket-booking-with-seat-pro' ), 'queued' => __( 'Your booking email is queued for sending.', 'addon-bus--ticket-booking-with-seat-pro' ), 'failed' => __( 'The email could not be sent. Your booking remains available in My Tickets.', 'addon-bus--ticket-booking-with-seat-pro' ), 'unknown' => __( 'Your booking is saved. Email delivery has not been confirmed.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			if ( ! isset( $messages[ $status ] ) ) { $status = 'unknown'; } return array( 'status' => $status, 'message' => $messages[ $status ] );
		}
		/** Observe only customer mail for this exact order, not an unrelated admin notification. */
		public static function observe_mail( $order ) {
			$recipient = strtolower( $order->get_billing_email() ); $id = $order->get_id();
			$ticket_sent = static function( $order_id, $sent, $to ) use ( $recipient, $id ) { if ( $id === (int) $order_id && $recipient && 0 === strcasecmp( $recipient, trim( (string) $to ) ) ) { $current = wc_get_order( $id ); if ( $current ) { $current->update_meta_data( '_wbtm_app_mail_status', $sent ? 'sent' : 'failed' ); $current->save_meta_data(); } } };
			$wc_sent = static function( $sent, $email_id, $email ) use ( $id ) { if ( isset( $email->object ) && $email->object instanceof WC_Order && $id === $email->object->get_id() && $email->is_customer_email() ) { $current = wc_get_order( $id ); $current->update_meta_data( '_wbtm_app_mail_status', $sent ? 'sent' : 'failed' ); $current->save_meta_data(); } };
			add_action( 'wbtm_ticket_mail_result', $ticket_sent, 10, 3 ); add_action( 'woocommerce_email_sent', $wc_sent, 10, 3 );
			return static function() use ( $ticket_sent, $wc_sent ) { remove_action( 'wbtm_ticket_mail_result', $ticket_sent, 10 ); remove_action( 'woocommerce_email_sent', $wc_sent, 10 ); };
		}
		public static function data( $order, $include_admissions = true ) {
			$summary = $order->get_meta( '_wbtm_app_native_summary' ); $balance = (float) $order->get_meta( '_wbtm_remaining_balance' );
			// Current Woo totals remain authoritative after an administrator edits an order.
			$use_snapshot = is_array( $summary ) && abs( (float) ( $summary['totals']['due_now'] ?? -1 ) - (float) $order->get_total() ) < 0.00001 && abs( (float) ( $summary['totals']['subtotal'] ?? -1 ) - (float) $order->get_subtotal() ) < 0.00001 && abs( (float) ( $summary['totals']['discount'] ?? -1 ) - (float) $order->get_discount_total() ) < 0.00001 && abs( (float) ( $summary['totals']['tax'] ?? -1 ) - (float) $order->get_total_tax() ) < 0.00001;
			$snapshot_items = $use_snapshot ? $summary['items'] : null;
			{
				$items = array(); foreach ( $order->get_items() as $item ) { $qty = max( 1, $item->get_quantity() ); $items[] = array( 'label' => $item->get_name(), 'quantity' => $qty, 'unit_price' => (float) $item->get_subtotal() / $qty, 'total' => (float) $item->get_total() ); }
				$fees = array(); foreach ( $order->get_fees() as $fee ) { $fees[] = array( 'label' => $fee->get_name(), 'total' => (float) $fee->get_total() ); }
				$taxes = array(); foreach ( $order->get_taxes() as $tax ) { $taxes[] = array( 'label' => $tax->get_label(), 'total' => (float) $tax->get_tax_total() ); }
				$coupons = array(); foreach ( $order->get_coupons() as $coupon ) { $coupons[] = array( 'code' => $coupon->get_code(), 'discount' => (float) $coupon->get_discount() ); }
				$summary = array( 'currency' => $order->get_currency(), 'items' => $items, 'totals' => array( 'subtotal' => (float) $order->get_subtotal(), 'discount' => (float) $order->get_discount_total(), 'tax' => (float) $order->get_total_tax(), 'fees' => (float) array_sum( array_column( $fees, 'total' ) ), 'total' => (float) $order->get_total() + $balance, 'due_now' => (float) $order->get_total(), 'balance_due' => $balance ), 'fees' => $fees, 'taxes' => $taxes, 'coupons' => $coupons );
			}
			if ( null !== $snapshot_items ) { $summary['items'] = $snapshot_items; }
			$journeys = $order->get_meta( '_wbtm_app_native_journeys' ); if ( ! is_array( $journeys ) ) { $journeys = self::journeys_from_order( $order ); }
			$gateways = WC()->payment_gateways()->payment_gateways(); $gateway = $gateways[ $order->get_payment_method() ] ?? null;
			$instructions = $gateway && isset( $gateway->instructions ) ? WBTM_App_Inventory::description( $gateway->instructions ) : '';
			if ( $gateway && 'WC_Gateway_BACS' === get_class( $gateway ) ) {
				// The built-in renderer owns localized bank labels, account fields
				// and Woo filters. Render only for this already-owned order.
				$level = ob_get_level(); ob_start();
				try { $gateway->thankyou_page( $order->get_id() ); $instructions = WBTM_App_Inventory::description( (string) ob_get_contents() ); }
				catch ( Throwable $error ) { /* Retain configured instructions if an extension fails. */ }
				finally { while ( ob_get_level() > $level ) { ob_end_clean(); } }
			}
			if ( $order->get_meta( '_wbtm_app_native_processing_review' ) ) { $instructions = __( 'Your order was saved and needs an operator status check. Do not place this booking again.', 'addon-bus--ticket-booking-with-seat-pro' ); }
			$paid = $order->get_date_paid() && ! in_array( $order->get_status(), array( 'cancelled', 'failed', 'refunded', 'wbtm-review' ), true );
			return array( 'id' => $order->get_id(), 'reference' => (string) $order->get_order_number(), 'status' => $order->get_status(), 'status_label' => wc_get_order_status_name( $order->get_status() ), 'created_at' => $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : '', 'currency' => $order->get_currency(), 'total' => (float) $summary['totals']['total'], 'due_now' => (float) $order->get_total(), 'balance_due' => $balance, 'payment_method' => $order->get_payment_method(), 'payment_title' => WBTM_App_Inventory::text( $order->get_payment_method_title() ), 'is_paid' => (bool) $paid, 'journeys' => $journeys, 'billing' => array( 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name(), 'email' => $order->get_billing_email(), 'phone' => $order->get_billing_phone() ), 'summary' => $summary, 'admissions' => $include_admissions && is_callable( array( 'WBTM_Admission_QR', 'for_order' ) ) ? WBTM_Admission_QR::for_order( $order->get_id() ) : array(), 'ticket' => self::pdf_status( $order ), 'email' => self::email_status( $order ), 'instructions' => $instructions );
		}
		public function bookings( $request ) {
			if ( ! function_exists( 'wc_get_orders' ) ) { return WBTM_App_Native_Checkout::error( 'WooCommerce booking history is unavailable.', 409 ); }
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; }
			$rate = WBTM_App_Auth::rate_limit( 'native_receipts', 180, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			$page = max( 1, min( 10000, absint( $request['page'] ?? 1 ) ) ); $per_page = max( 1, min( 20, absint( $request['per_page'] ?? 20 ) ) );
			$args = array( 'type' => 'shop_order', 'limit' => $per_page + 1, 'offset' => ( $page - 1 ) * $per_page, 'orderby' => 'ID', 'order' => 'DESC' );
			if ( 'customer' === $p['kind'] ) { $args['customer_id'] = $p['user_id']; } else { $args['customer_id'] = 0; $args['meta_query'] = array( array( 'key' => '_wbtm_app_guest_owner', 'value' => $p['owner'] ) ); }
			$orders = wc_get_orders( $args ); $has_more = count( $orders ) > $per_page; $result = array();
			foreach ( array_slice( $orders, 0, $per_page ) as $order ) { if ( ! is_wp_error( self::owned_order( $order->get_id(), $p ) ) ) { $result[] = self::data( $order, false ); } }
			return new WP_REST_Response( array( 'bookings' => $result, 'page' => $page, 'per_page' => $per_page, 'has_more' => $has_more ) );
		}
		public function booking( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; } $order = self::owned_order( $request['id'], $p );
			$rate = WBTM_App_Auth::rate_limit( 'native_receipts', 180, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			return is_wp_error( $order ) ? $order : new WP_REST_Response( self::data( $order ) );
		}
		public function ticket( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; } $order = self::owned_order( $request['id'], $p ); if ( is_wp_error( $order ) ) { return $order; }
			$rate = WBTM_App_Auth::rate_limit( 'native_pdf', 20, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			$status = self::pdf_status( $order ); if ( ! $status['available'] ) { return WBTM_App_Native_Checkout::error( $status['reason'], 409 ); }
			$token = bin2hex( random_bytes( 32 ) ); $expires = time() + 120;
			set_transient( 'wbtm_app_native_pdf_' . hash( 'sha256', $token ), array( 'order_id' => $order->get_id(), 'principal' => $p ), 120 );
			return new WP_REST_Response( array( 'url' => add_query_arg( 'wbtm_app_native_pdf', $token, home_url( '/' ) ), 'expires_at' => $expires, 'filename' => self::ticket_filename( $order ) ) );
		}
		/** Match the plugin's normal order-PDF filename setting and token meanings. */
		public static function ticket_filename( $order ) {
			$template = WBTM_Global_Function::get_settings( 'wbtm_pdf_settings', 'pdf_ticket_filename', 'Ticket' ) ?: 'Ticket';
			return sanitize_file_name( str_replace( array( '{order_id}', '{attendee_id}', '{bus_name}', '{date}' ), array( $order->get_id(), '', '', '' ), $template ) ) . '.pdf';
		}
		/** Non-exiting capability exchange is also exercised by integration tests. */
		public static function redeem_pdf( $token ) {
			if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || ! WBTM_App_Auth::enabled() ) { return WBTM_App_Native_Checkout::error( 'Ticket link expired.', 403 ); }
			return WBTM_App_Auth::locked( 'native_pdf_' . hash( 'sha256', $token ), static function() use ( $token ) {
				$key = 'wbtm_app_native_pdf_' . hash( 'sha256', $token ); $capability = get_transient( $key );
				if ( ! is_array( $capability ) || ! WBTM_App_Guest::active( $capability['principal'] ) ) { return WBTM_App_Native_Checkout::error( 'Ticket link expired.', 403 ); }
				$order = self::owned_order( $capability['order_id'], $capability['principal'] ); if ( is_wp_error( $order ) || ! self::pdf_status( $order )['available'] ) { return WBTM_App_Native_Checkout::error( 'This ticket is unavailable.', 403 ); }
				delete_transient( $key ); return $order;
			} );
		}
		public function download() {
			if ( ! isset( $_GET['wbtm_app_native_pdf'] ) ) { return; }
			$token = is_string( $_GET['wbtm_app_native_pdf'] ) ? wp_unslash( $_GET['wbtm_app_native_pdf'] ) : ''; $order = self::redeem_pdf( $token );
			if ( is_wp_error( $order ) ) { wp_die( esc_html( $order->get_error_message() ), '', array( 'response' => 403 ) ); }
			nocache_headers(); header( 'Referrer-Policy: no-referrer' ); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: application/pdf' );
			$args = array( 'wbtm_order_id' => $order->get_id() ); $filename = self::ticket_filename( $order );
			if ( has_action( 'wbtm_generate_pdf' ) ) { do_action( 'wbtm_generate_pdf', $args, $filename ); }
			else { ( new WBTM_Pro_Pdf() )->generate_pdf( $args, $filename ); }
			exit;
		}
		public function email( $request ) {
			$p = WBTM_App_Guest::principal( $request ); if ( is_wp_error( $p ) ) { return $p; } $order = self::owned_order( $request['id'], $p ); if ( is_wp_error( $order ) ) { return $order; }
			$rate = WBTM_App_Auth::rate_limit( 'native_email_' . $order->get_id(), 3, $p['owner'] ); if ( is_wp_error( $rate ) ) { return $rate; }
			if ( $order->get_meta( '_wbtm_app_native_processing_review' ) ) { return new WP_REST_Response( self::email_status( $order ) ); }
			if ( ! self::mail_configured() ) { return new WP_REST_Response( self::email_status( $order ) ); }
			return WBTM_App_Auth::locked( 'native_email_' . $order->get_id(), static function() use ( $order ) {
				$id = $order->get_id(); $status = 'failed';
				$cleanup = self::observe_mail( $order ); $order->delete_meta_data( '_wbtm_app_mail_status' ); $order->save_meta_data();
				$force = static function( $value, $order_id ) use ( $id ) { return $id === (int) $order_id ? true : $value; };
				try {
					// The recipient is always the saved billing email; arbitrary recipients are never accepted.
					$ticket_email = class_exists( 'WBTM_Pro_Mail' ) && self::pdf_status( $order )['available'] && 'yes' === WBTM_Global_Function::get_settings( 'wbtm_email_settings', 'pdf_send_status', 'yes' ) && in_array( $order->get_status(), (array) WBTM_Global_Function::get_settings( 'wbtm_email_settings', 'pdf_email_status', array( 'processing', 'completed' ) ), true );
					if ( $ticket_email ) { add_filter( 'wbtm_ticket_mail_force_resend', $force, 10, 2 ); do_action( 'wbtm_send_mail', $id ); $status = 'unknown'; }
					else { $mailer = WC()->mailer(); $emails = $mailer->get_emails(); $email = $emails['WC_Email_Customer_Invoice'] ?? null; if ( $email ) { $email->trigger( $id, $order ); $status = $email->is_enabled() ? 'unknown' : 'failed'; } }
				} catch ( Throwable $error ) { $status = 'failed'; } finally { $cleanup(); remove_filter( 'wbtm_ticket_mail_force_resend', $force, 10 ); }
				$current = wc_get_order( $id );
				$current->read_meta_data( true ); if ( ! $current->get_meta( '_wbtm_app_mail_status' ) ) { $current->update_meta_data( '_wbtm_app_mail_status', $status ); $current->save_meta_data(); } return new WP_REST_Response( self::email_status( $current ) );
			} );
		}
	}
	new WBTM_App_Receipt();
}
