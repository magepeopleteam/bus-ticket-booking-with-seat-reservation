<?php
/** Free-only native integration. Run wp --require=tests/app-free-bootstrap.php eval-file <this file>. No mail or charges. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
$checks = 0; $posts = array(); $orders = array(); $users = array(); $guests = array(); $intents = array(); $request_keys = array(); $pdf_tokens = array(); $tax_ids = array();
$assert = static function( $ok, $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
$data = static function( $result ) { if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_code() . ': ' . $result->get_error_message() ); } return $result instanceof WP_REST_Response ? $result->get_data() : $result; };
$req = static function( $route, $body = array(), $token = '', $method = 'POST' ) { $r = new WP_REST_Request( $method, '/wbtm-app/v1' . $route ); $r->set_header( 'Content-Type', 'application/json' ); if ( $token ) { $r->set_header( 'Authorization', 'Bearer ' . $token ); } $r->set_body( wp_json_encode( $body ) ); foreach ( $body as $k => $v ) { $r->set_param( $k, $v ); } return $r; };
$free_plugins = array_values( array_filter( get_option( 'active_plugins', array() ), static function( $slug ) { return 'addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php' !== $slug; } ) );
$coupons_enabled = 'yes'; $general_settings = get_option( 'wbtm_general_settings', array() );
$guest_enabled = 'yes'; $tax_enabled = 'no'; $deposit_enabled = false; $mail_calls = 0;
$filters = array(
	'pre_option_wbtm_general_settings' => static function() use ( &$general_settings ) { return $general_settings; },
	'pre_option_active_plugins' => static function() use ( $free_plugins ) { return $free_plugins; },
	'pre_option_woocommerce_enable_coupons' => static function() use ( &$coupons_enabled ) { return $coupons_enabled; },
	'pre_option_wbtm_app_settings' => static function() { return array( 'app_enabled' => 'yes', 'app_registration_enabled' => 'yes' ); },
	'pre_option_wbtm_payment_settings' => static function() { return array( 'wbtm_booking_mode' => 'woocommerce' ); },
	'pre_option_woocommerce_enable_guest_checkout' => static function() use ( &$guest_enabled ) { return $guest_enabled; },
	'pre_option_woocommerce_enable_signup_and_login_from_checkout' => static function() { return 'yes'; },
	'pre_option_woocommerce_calc_taxes' => static function() use ( &$tax_enabled ) { return $tax_enabled; },
	'pre_option_woocommerce_prices_include_tax' => static function() { return 'no'; },
	'pre_option_woocommerce_tax_based_on' => static function() { return 'billing'; },
	'pre_option_wbtm_email_settings' => static function() { return array( 'admin_notifications_enabled' => 'no', 'pdf_send_status' => 'no', 'pdf_email_status' => array() ); },
	'wbtm_app_mail_configured' => static function() { return false; },
	'pre_wp_mail' => static function() use ( &$mail_calls ) { ++$mail_calls; return false; },
);
foreach ( $filters as $name => $callback ) { add_filter( $name, $callback ); }
$wc = WC(); $before = array( $wc->session, $wc->customer, $wc->cart, $_POST, $_COOKIE, get_current_user_id() );
$original_address = $_SERVER['REMOTE_ADDR'] ?? null; $_SERVER['REMOTE_ADDR'] = 'native-fixture-' . bin2hex( random_bytes( 8 ) );
$cod = WC()->payment_gateways()->payment_gateways()['cod']; $cod_before = array( $cod->enabled, $cod->enable_for_virtual ); $cod->enabled = 'yes'; $cod->enable_for_virtual = true;
try {
	$assert( ! class_exists( 'WBTM_Deposit' ) && ! class_exists( 'WBTM_Pro_Pdf' ), 'test process has no PRO payment or PDF modules' );
	$api = new WBTM_App_Native_Checkout(); $receipts = new WBTM_App_Receipt(); $guest_api = new WBTM_App_Guest();
	$a = $data( $guest_api->session( $req( '/guest/session' ) ) ); $b = $data( $guest_api->session( $req( '/guest/session' ) ) ); $guests = array( $a, $b );
	$pa = $data( WBTM_App_Guest::principal( $req( '/native-bookings', array(), $a['access_token'] ) ) );
	$pb = $data( WBTM_App_Guest::principal( $req( '/native-bookings', array(), $b['access_token'] ) ) );
	$assert( 0 === $pa['user_id'] && 'guest' === $pa['kind'] && $pa['owner'] !== $pb['owner'], 'guests are genuine user-zero WC customers with different cryptographic owners' );
	$stored = wp_json_encode( get_option( WBTM_App_Guest::PREFIX . $pa['session'] ) ); $assert( false === strpos( $stored, explode( '.', $a['access_token'] )[1] ) && false === strpos( $stored, explode( '.', $a['refresh_token'] )[1] ), 'guest secrets are stored only as keyed hashes' );
	$assert( is_wp_error( WBTM_App_Auth::require_user( $req( '/me', array(), $a['access_token'] ) ) ), 'guest bearer cannot use legacy customer-only APIs' );
	$rotated = $data( $guest_api->refresh( $req( '/guest/refresh', array( 'refresh_token' => $a['refresh_token'] ) ) ) ); $guests[0] = $rotated;
	$assert( $rotated['guest_id'] === $a['guest_id'] && $rotated['access_token'] !== $a['access_token'], 'refresh rotates both credentials without changing the owner' );
	$assert( is_wp_error( WBTM_App_Guest::principal( $req( '/native-bookings', array(), $a['access_token'] ) ) ) && is_wp_error( $guest_api->refresh( $req( '/guest/refresh', array( 'refresh_token' => $a['refresh_token'] ) ) ) ), 'old access and refresh credentials cannot be replayed' ); $a = $rotated;
	$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+9 days' )->format( 'Y-m-d' );
	$bus = wp_insert_post( array( 'post_type' => 'wbtm_bus', 'post_status' => 'publish', 'post_title' => 'Native checkout isolated fixture' ) ); $posts[] = $bus;
	$generated_product = (int) get_post_meta( $bus, 'link_wc_product', true ); if ( $generated_product ) { $posts[] = $generated_product; }
	$meta = array( 'wbtm_route_info' => array( array( 'place' => 'Native A', 'type' => 'bp', 'time' => '09:00' ), array( 'place' => 'Native B', 'type' => 'dp', 'time' => '11:00' ) ), 'wbtm_route_direction' => array( 'Native A', 'Native B' ), 'wbtm_bus_bp_stops' => array( 'Native A' ), 'wbtm_bus_next_stops' => array( 'Native B' ), 'show_operational_on_day' => 'yes', 'wbtm_particular_dates' => array( $date ), 'wbtm_registration' => 'yes', 'wbtm_seat_type_conf' => 'wbtm_seat_plan', 'wbtm_get_total_seat' => 6, 'wbtm_bus_seats_info' => array( array( 'seat1' => 'A1', 'seat2' => 'A2', 'seat3' => 'A3', 'seat4' => 'A4', 'seat5' => 'A5', 'seat6' => 'A6' ) ), 'wbtm_seat_rows' => 1, 'wbtm_seat_cols' => 6, 'wbtm_bus_prices' => array( array( 'wbtm_bus_bp_price_stop' => 'Native A', 'wbtm_bus_dp_price_stop' => 'Native B', 'wbtm_bus_price' => 40 ) ), 'wbtm_bus_deposit_override' => 'no', 'show_extra_service' => 'yes', 'wbtm_extra_services' => array( array( 'option_name' => 'Water', 'option_price' => 3, 'option_qty' => 100, 'option_qty_type' => 'inputbox', 'option_charge_type' => 'per_passenger' ) ) );
	foreach ( $meta as $key => $value ) { update_post_meta( $bus, $key, $value ); }
	$product = new WC_Product_Simple(); $product->set_name( 'Native checkout test product' ); $product->set_regular_price( 1 ); $product->set_virtual( true ); $product->set_catalog_visibility( 'hidden' ); $product->save(); $posts[] = $product->get_id(); update_post_meta( $bus, 'link_wc_product', $product->get_id() ); update_post_meta( $product->get_id(), 'link_wbtm_bus', $bus );
	$trip = array( 'bus_id' => $bus, 'bp' => 'Native A', 'dp' => 'Native B', 'date' => $date ); $detail = $data( WBTM_App_Inventory::detail( $trip ) ); $type = $detail['ticket_types'][0]['id'];
	$journey = array_merge( $trip, array( 'selections' => array( array( 'seat_id' => 'l|A1', 'ticket_type' => $type ), array( 'seat_id' => 'l|A2', 'ticket_type' => $type ) ), 'extras' => array( array( 'name' => 'Water', 'quantity' => 1 ) ), 'total' => 0, 'price' => -999 ) );
	$selection = $data( $api->selection_summary( $req( '/native-checkout/selection-summary', array( 'journeys' => array( $journey ) ) ) ) );
	$assert( 80.0 === $selection['totals']['tickets'] && 6.0 === $selection['totals']['extras'] && 86.0 === $selection['totals']['total'], 'public selection summary prices seats and per-passenger extras on server; injected price ignored' );
	$line = $selection['journeys'][0]['extras'][0]; $assert( $line['quantity'] * $line['unit_price'] === $line['total'] && 2 === $line['quantity'], 'per-passenger extra billed quantity times unit fare reconciles the displayed line total' );
	$assert( false === strpos( wp_json_encode( $selection ), 'passengers' ), 'public summary does not return passenger PII' );
	$schema = $data( $api->schema( $req( '/native-checkout/schema', array(), $a['access_token'], 'GET' ) ) );
	$bundle = $data( $guest_api->session( $req( '/guest/session', array( 'checkout' => array( 'bus_ids' => (string) $bus, 'country' => 'US', 'state' => 'NY' ) ) ) ) ); $guests[] = $bundle;
	$assert( isset( $bundle['checkout_schema']['billing_fields'] ) && $bundle['checkout_schema']['policy'] === $schema['policy'], 'first guest response includes the real Woo checkout schema without a second WordPress request' );
	$assert( 0 === get_current_user_id() && $before[0] === WC()->session && $before[2] === WC()->cart, 'bundled guest schema retains genuine guest identity and restores website cart globals' );
	$assert( $schema['policy']['guest_checkout_enabled'] && $schema['policy']['registration_enabled'] && count( $schema['countries'] ) > 0 && count( $schema['billing_fields'] ) > 0, 'dynamic Woo schema supplies countries, billing fields and true guest/registration policy' );
	$billing = array( 'first_name' => 'Native', 'last_name' => 'Fixture', 'email' => wp_generate_password( 12, false ) . '@example.test', 'phone' => '2125550199', 'country' => 'US', 'state' => 'NY', 'city' => 'Brooklyn', 'address_1' => '100 Test Street', 'postcode' => '11201' );
	$body = array( 'journeys' => array( $journey ), 'billing' => $billing, 'payment_method' => 'cod', 'terms_accepted' => false );
	$preview = $data( $api->preview( $req( '/native-checkout/preview', $body, $a['access_token'] ) ) ); $intents[] = $preview['checkout_id'];
	$assert( 86.0 === $preview['summary']['totals']['due_now'] && 3 === count( $preview['summary']['items'] ), 'native Woo preview shows two seats and extras with correct authoritative total' );
	$assert( $before[0] === $wc->session && $before[1] === $wc->customer && $before[2] === $wc->cart && $before[3] === $_POST && $before[4] === $_COOKIE, 'preview restores Woo globals, POST and cookies' );
	$foreign = array( 'checkout_id' => $preview['checkout_id'], 'revision' => $preview['revision'], 'idempotency_key' => 'native-isolation-key-0001', 'terms_accepted' => false );
	$assert( is_wp_error( $api->submit( $req( '/native-checkout/submit', $foreign, $b['access_token'] ) ) ), 'guest B cannot submit guest A checkout' );
	$request_keys[] = 'wbtm_app_native_request_' . hash( 'sha256', $pa['owner'] . '|native-isolation-key-0001' );
	$meta_policy_before = apply_filters( 'woocommerce_orders_table_datastore_should_save_after_meta_change', true ); $meta_policy_during = null;
	$observe_meta_policy = static function() use ( &$meta_policy_during ) { $meta_policy_during = apply_filters( 'woocommerce_orders_table_datastore_should_save_after_meta_change', true ); };
	add_action( 'woocommerce_checkout_order_created', $observe_meta_policy, 20 );
	try { $placed = $data( $api->submit( $req( '/native-checkout/submit', $foreign, $a['access_token'] ) ) ); $orders[] = $placed['id']; }
	finally { remove_action( 'woocommerce_checkout_order_created', $observe_meta_policy, 20 ); }
	$assert( false === $meta_policy_during && $meta_policy_before === apply_filters( 'woocommerce_orders_table_datastore_should_save_after_meta_change', true ), 'native metadata batching prevents recursive HPOS saves and restores the website metadata policy afterward' );
	$assert( false === $placed['is_paid'], 'COD processing reserves booking without falsely reporting money received' );
	$assert( 86.0 === $placed['total'] && 'cod' === $placed['payment_method'] && 'processing' === $placed['status'] && isset( $placed['journeys'][0]['seats'][1] ), 'COD order completes inside native API with detailed receipt and no website handoff' );
	$order = wc_get_order( $placed['id'] ); $assert( 0 === $order->get_customer_id() && $order->get_meta( '_wbtm_app_guest_owner' ) === $pa['owner'], 'real Woo guest order has zero customer ID and exact guest proof owner' );
	$rows = get_posts( array( 'post_type' => 'wbtm_bus_booking', 'posts_per_page' => 20, 'meta_key' => 'wbtm_order_id', 'meta_value' => $order->get_id() ) ); $assert( 2 === count( $rows ), 'normal Woo/WBTM checkout hooks create one booking per selected seat' );
	$duplicate = $data( $api->submit( $req( '/native-checkout/submit', $foreign, $a['access_token'] ) ) ); $assert( $duplicate['id'] === $placed['id'], 'same idempotency key replays exact order without duplicate bookings' );
	$foreign['idempotency_key'] = 'native-isolation-key-0002'; $duplicate = $data( $api->submit( $req( '/native-checkout/submit', $foreign, $a['access_token'] ) ) ); $assert( $duplicate['id'] === $placed['id'], 'different request key cannot create a second order from the same checkout' );
	$assert( is_wp_error( $receipts->booking( $req( '/native-bookings/' . $placed['id'], array( 'id' => $placed['id'] ), $b['access_token'], 'GET' ) ) ), 'foreign guest cannot read receipt' );
	$assert( is_wp_error( $receipts->ticket( $req( '/native-bookings/' . $placed['id'] . '/ticket', array( 'id' => $placed['id'] ), $b['access_token'] ) ) ) && is_wp_error( $receipts->email( $req( '/native-bookings/' . $placed['id'] . '/email', array( 'id' => $placed['id'] ), $b['access_token'] ) ) ), 'foreign guest cannot download PDF or trigger booking email' );
	$list = $data( $receipts->bookings( $req( '/native-bookings', array(), $a['access_token'], 'GET' ) ) ); $other = $data( $receipts->bookings( $req( '/native-bookings', array(), $b['access_token'], 'GET' ) ) ); $assert( 1 === count( $list['bookings'] ) && 0 === count( $other['bookings'] ), 'guest history contains only exact proof-owned bookings' );
	$config = $data( ( new WBTM_App_Config() )->get_config() );
	$assert( ! $config['backend_features']['pro'] && $config['operations']['native_checkout'] && ! $config['operations']['hosted_deposits'] && ! $config['operations']['pdf'], 'Free-only capabilities expose native Woo booking and hide unavailable PRO modules' );
	$general_settings['bus_return_show'] = 'disable'; $hidden_return = $data( ( new WBTM_App_Config() )->get_config() );
	$assert( ! $hidden_return['booking']['return_search_enabled'], 'global return search switch disables the app return controls' );
	$general_settings['bus_return_show'] = 'enable'; $shown_return = $data( ( new WBTM_App_Config() )->get_config() );
	$assert( $shown_return['booking']['return_search_enabled'], 'global return search switch re-enables the app without a rebuild' );
	$assert( empty( $schema['deposit_choices'] ) && ! $placed['ticket']['available'], 'Free-only checkout has no deposit picker or misleading PDF action' );
	$assert( $schema['coupons_enabled'], 'Woo enabled coupons appear in the native schema' );
	$coupons_enabled = 'no';
	$disabled_schema = $data( $api->schema( $req( '/native-checkout/schema', array(), $a['access_token'], 'GET' ) ) );
	$assert( ! $disabled_schema['coupons_enabled'] && ! WBTM_App_Checkout::capabilities()['hosted_coupons'], 'disabling Woo coupons changes schema and capability without rebuilding the app' );
	$coupon_body = $body; $coupon_body['journeys'][0]['selections'] = array( array( 'seat_id' => 'l|A3', 'ticket_type' => $type ) ); $coupon_body['coupon_codes'] = array( 'disabled-fixture-coupon' );
	$denied = $api->preview( $req( '/native-checkout/preview', $coupon_body, $a['access_token'] ) );
	$assert( is_wp_error( $denied ) && str_contains( $denied->get_error_message(), 'Coupons are disabled' ), 'server rejects forged coupon requests while store coupons are disabled' );
	update_post_meta( $bus, 'bus_return_discount', 25 );
	$free_detail = $data( WBTM_App_Inventory::detail( $trip ) );
	$assert( ! $free_detail['return_discount']['enabled'], 'saved premium discount data does not activate a missing PRO module' );
	update_post_meta( $bus, 'wbtm_ticket_types', array( array( 'id' => 'flex', 'label' => 'Administrator Flex' ), array( 'id' => 'youth', 'label' => 'Administrator Youth' ) ) );
	$prices = $meta['wbtm_bus_prices']; $prices[0]['wbtm_ticket_prices'] = array( 'flex' => 47, 'youth' => 19 );
	// Use the same canonical custom-price key produced by the bus editor.
	update_post_meta( $bus, 'wbtm_bus_prices', $prices );
	$dynamic = $data( WBTM_App_Inventory::detail( $trip ) );
	$assert( array( 'flex', 'youth' ) === array_column( $dynamic['ticket_types'], 'id' ) && 'Administrator Flex' === $dynamic['ticket_types'][0]['name'], 'app ticket names and IDs come from administrator bus types' );
	update_post_meta( $bus, 'wbtm_seat_type_conf', 'without_seat_plan' );
	$quantity = $data( WBTM_App_Inventory::detail( $trip ) );
	$assert( $quantity['booking_modes']['quantity'] && ! $quantity['booking_modes']['seats'] && array() === $quantity['layouts'], 'changing bus seat mode immediately changes native quantity controls' );
	$assert( WP_Block_Type_Registry::get_instance()->is_registered( 'wbtm/app-download' ), 'editable download block is registered in Free-only WordPress' );
	WP_CLI::success( $checks . ' Free-only native booking/settings checks passed; fixtures removed.' );
} finally {
	foreach ( $intents as $id ) { $claim = get_option( 'wbtm_app_native_order_' . $id ); if ( ! empty( $claim['order_id'] ) ) { $orders[] = $claim['order_id']; } delete_option( 'wbtm_app_native_order_' . $id ); delete_transient( WBTM_App_Native_Checkout::PREFIX . $id ); }
	foreach ( array_unique( $orders ) as $id ) { foreach ( get_posts( array( 'post_type' => array( 'wbtm_bus_booking', 'wbtm_service_booking' ), 'posts_per_page' => 100, 'meta_key' => 'wbtm_order_id', 'meta_value' => $id ) ) as $row ) { wp_delete_post( $row->ID, true ); } wp_clear_scheduled_hook( WBTM_App_Lifecycle::CRON, array( (int) $id ) ); $order = wc_get_order( $id ); if ( $order ) { $order->delete( true ); } }
	foreach ( $request_keys as $key ) { delete_option( $key ); } foreach ( $pdf_tokens as $token ) { delete_transient( 'wbtm_app_native_pdf_' . hash( 'sha256', $token ) ); }
	foreach ( $tax_ids as $id ) { WC_Tax::_delete_tax_rate( $id ); }
	foreach ( $guests as $guest ) { preg_match( '/^g_([a-f0-9]{24})\./', $guest['access_token'], $parts ); if ( ! empty( $parts[1] ) ) { delete_option( WBTM_App_Guest::PREFIX . $parts[1] ); wp_clear_scheduled_hook( 'wbtm_app_expire_guest', array( $parts[1] ) ); } }
	require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $id ) { WBTM_App_Auth::revoke_all( $id ); wp_delete_user( $id ); } foreach ( array_reverse( $posts ) as $id ) { wp_delete_post( $id, true ); }
	list( $wc->session, $wc->customer, $wc->cart, $_POST, $_COOKIE, $old_user ) = $before; wp_set_current_user( $old_user ); list( $cod->enabled, $cod->enable_for_virtual ) = $cod_before;
	$_SERVER['REMOTE_ADDR'] = $original_address;
	foreach ( $filters as $name => $callback ) { remove_filter( $name, $callback ); } WBTM_Query::flush_booked_map();
}
