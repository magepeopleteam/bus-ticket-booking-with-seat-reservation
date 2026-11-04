<?php
/** Run with WP-CLI. Restores all options; never sends email or creates bookings. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$keys = array( WBTM_App_Home::OPTION, WBTM_App_Settings::OPTION, WBTM_App_Builder_Connection::OPTION, 'wbtm_app_builder_release' );
$before = array();
foreach ( $keys as $key ) {
	$before[ $key ] = get_option( $key, null );
}
$user_before = get_current_user_id();
$post_before = $_POST;
$get_before = $_GET;
$request_before = $_REQUEST;
$method_before = $_SERVER['REQUEST_METHOD'] ?? null;
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$die_handler = static function () {
	return static function () { throw new RuntimeException( 'EXPECTED_WORKSPACE_DENIED' ); };
};
$redirect_handler = null;
try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$check( ! empty( $admins ), 'An existing administrator is required.' );
	wp_set_current_user( (int) $admins[0] );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET['app_tab'] = 'connection';
	$editor = new WBTM_App_Home();
	ob_start();
	$editor->render_page();
	$html = ob_get_clean();
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	$xpath = new DOMXPath( $document );
	$check( 7 === $xpath->query( '//*[@role="tab"]' )->length && 7 === $xpath->query( '//*[@role="tabpanel"]' )->length, 'Seven accessible workspace tabs must render.' );
	$check( 1 === $xpath->query( '//*[@data-initial-tab="connection"]' )->length, 'Connection deep links must select the connection tab.' );
	$check( 3 === $xpath->query( '//form' )->length, 'Home content, code management and identity reset must use separate forms.' );
	$check( 0 === $xpath->query( '//form//form' )->length, 'Forms must not be nested.' );
	$check( 0 === $xpath->query( '//form[@data-connection-form]//*[starts-with(@name,"wbtm_app_home[")]' )->length, 'Connection actions must not submit home content.' );
	$check( 0 === $xpath->query( '//form[@id="wbtm-app-home-form"]//*[@name="connection_action"]' )->length, 'Home saves must not submit connection actions.' );
	$check( 2 === $xpath->query( '//form[@data-connection-form]//input[@name="_wpnonce"]' )->length, 'Every connection form requires its nonce.' );
	foreach ( $before as $key => $value ) {
		$check( get_option( $key, null ) === $value, 'Read-only rendering must preserve ' . $key );
	}
	$_GET['app_tab'] = array( 'invalid' );
	ob_start();
	$editor->render_page();
	$invalid_html = ob_get_clean();
	$check( false !== strpos( $invalid_html, 'data-initial-tab=""' ), 'Malformed tab input must use a safe default.' );

	add_filter( 'wp_die_handler', $die_handler );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array( WBTM_App_Home::OPTION => array( 'heading' => 'Must not change' ) );
	$_REQUEST = array();
	$check( '' === WBTM_App_Builder_Connection::handle_actions(), 'Unrelated POSTs must not trigger connection nonce checks or mutations.' );
	$_POST['connection_action'] = 'create';
	$denied = false;
	try { WBTM_App_Builder_Connection::handle_actions(); } catch ( RuntimeException $error ) { $denied = 'EXPECTED_WORKSPACE_DENIED' === $error->getMessage(); }
	$check( $denied && get_option( WBTM_App_Builder_Connection::OPTION, null ) === $before[ WBTM_App_Builder_Connection::OPTION ], 'Missing connection nonce must reject mutations.' );
	$_REQUEST['_wpnonce'] = wp_create_nonce( WBTM_App_Builder_Connection::PAGE );
	$token = WBTM_App_Builder_Connection::handle_actions();
	$state = get_option( WBTM_App_Builder_Connection::OPTION );
	$check( 1 === preg_match( '/^[a-f0-9]{64}$/D', $token ) && hash( 'sha256', $token ) === $state['hash'], 'Creating a code must store only its hash.' );
	$check( $before[ WBTM_App_Home::OPTION ] === get_option( WBTM_App_Home::OPTION, null ), 'Code creation must preserve home content.' );
	$check( $before[ WBTM_App_Settings::OPTION ] === get_option( WBTM_App_Settings::OPTION, null ), 'Code creation must preserve branding and booking settings.' );
	$_POST['connection_action'] = 'revoke';
	WBTM_App_Builder_Connection::handle_actions();
	$check( false === get_option( WBTM_App_Builder_Connection::OPTION ), 'Revocation must remove the connection hash.' );
	$check( $before['wbtm_app_builder_release'] === get_option( 'wbtm_app_builder_release', null ), 'Revocation must retain the published identity.' );
	$redirect_handler = static function ( $location, $status ) use ( $check ) {
		$check( WBTM_App_Builder_Connection::workspace_url() === $location, 'Legacy URL must redirect to the unified connection tab.' );
		$check( 307 === $status, 'Legacy POST redirects must preserve the submitted method and nonce.' );
		throw new RuntimeException( 'EXPECTED_WORKSPACE_REDIRECT' );
	};
	add_filter( 'wp_redirect', $redirect_handler, 1, 2 );
	try { ( new WBTM_App_Builder_Connection() )->redirect_to_workspace(); } catch ( RuntimeException $error ) {
		$check( 'EXPECTED_WORKSPACE_REDIRECT' === $error->getMessage(), 'Legacy POST redirect must be intercepted.' );
	}
	wp_set_current_user( 0 );
	$denied = false;
	try { WBTM_App_Builder_Connection::handle_actions(); } catch ( RuntimeException $error ) { $denied = 'EXPECTED_WORKSPACE_DENIED' === $error->getMessage(); }
	$check( $denied, 'Visitors must never manage a connection.' );
} finally {
	remove_filter( 'wp_die_handler', $die_handler );
	if ( $redirect_handler ) {
		remove_filter( 'wp_redirect', $redirect_handler, 1 );
	}
	wp_set_current_user( $user_before );
	$_POST = $post_before;
	$_GET = $get_before;
	$_REQUEST = $request_before;
	if ( null === $method_before ) {
		unset( $_SERVER['REQUEST_METHOD'] );
	} else {
		$_SERVER['REQUEST_METHOD'] = $method_before;
	}
	foreach ( $before as $key => $value ) {
		if ( null === $value ) {
			delete_option( $key );
		} else {
			update_option( $key, $value, false );
		}
	}
}
WP_CLI::success( $checks . ' mobile app workspace checks passed; original options restored.' );
