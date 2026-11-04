<?php
/** Self-cleaning WP-CLI contract/security checks. No bookings, mail or payments. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die;
}

$keys = array( WBTM_App_Builder_Connection::OPTION, 'wbtm_app_builder_release', WBTM_App_Settings::OPTION );
$before = array();
foreach ( $keys as $key ) {
	$before[ $key ] = get_option( $key, null );
}
$https_before = $_SERVER['HTTPS'] ?? null;
$checked = 0;
$check = static function ( $value, $message ) use ( &$checked ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
	++$checked;
};
$call = static function ( $route, $token = '', $data = array() ) {
	$request = new WP_REST_Request( 'POST', '/wbtm-app/v1/builder/' . $route );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'X-WBTM-Builder-Token', $token );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
};
try {
	$_SERVER['HTTPS'] = 'on';
	$token = bin2hex( random_bytes( 32 ) );
	update_option( WBTM_App_Builder_Connection::OPTION, array( 'hash' => hash( 'sha256', $token ), 'created' => time() ), false );
	$settings = WBTM_App_Settings::get();
	$settings['app_enabled'] = 'yes';
	update_option( WBTM_App_Settings::OPTION, $settings, false );
	$check( 401 === $call( 'manifest' )->get_status(), 'Missing connection code must fail.' );
	$check( 401 === $call( 'manifest', bin2hex( random_bytes( 32 ) ) )->get_status(), 'Wrong connection code must fail.' );
	$manifest = $call( 'manifest', $token );
	$check( 200 === $manifest->get_status(), 'Valid connection must return a manifest.' );
	$data = $manifest->get_data();
	$check( 1 === $data['schema_version'] && 1 === $data['config']['schema_version'], 'Manifest must preserve the public API contract.' );
	$check( home_url( '/' ) === $data['config']['site']['url'], 'Manifest must identify the actual WordPress website.' );
	$check( ! isset( $data['hash'], $data['customers'], $data['orders'], $data['settings'] ), 'Manifest must not contain secrets or customer records.' );
	$check( 'private, no-store' === $manifest->get_headers()['Cache-Control'], 'Connection responses must not be cached.' );
	$_SERVER['HTTPS'] = 'off';
	$check( in_array( $call( 'manifest', $token )->get_status(), array( 401, 403 ), true ), 'Cleartext connections must fail through the connection permission or production HTTPS guard.' );
	$_SERVER['HTTPS'] = 'on';
	$settings['app_enabled'] = 'no';
	update_option( WBTM_App_Settings::OPTION, $settings, false );
	$check( 404 === $call( 'manifest', $token )->get_status(), 'Disabled app API must stop builds through the existing API guard.' );
	$settings['app_enabled'] = 'yes';
	update_option( WBTM_App_Settings::OPTION, $settings, false );
	delete_option( 'wbtm_app_builder_release' );
	$release = array( 'url' => 'https://apps.bithi.online/download/test/Customer%20Bus.apk', 'version' => 1, 'sha256' => hash( 'sha256', 'qa-owned-artifact' ), 'size' => 4096, 'project_id' => '11111111-1111-4111-8111-111111111111' );
	foreach ( array( array( 'url' => 'http://unsafe.example/app.apk' ), array( 'url' => 'https://user:password@unsafe.example/app.apk' ), array( 'sha256' => 'invalid' ), array( 'version' => 0 ), array( 'size' => 104857601 ), array( 'project_id' => '../escape' ) ) as $invalid ) {
		$check( 400 === $call( 'release', $token, array_merge( $release, $invalid ) )->get_status(), 'Invalid release input must fail.' );
	}
	$check( 200 === $call( 'release', $token, $release )->get_status(), 'Authorized release must publish its URL.' );
	$after = WBTM_App_Settings::get();
	$check( $release['url'] === $after['app_android_apk_url'], 'Published APK URL must match.' );
	unset( $after['app_android_apk_url'], $settings['app_android_apk_url'] );
	$check( $after === $settings, 'Publishing must preserve every other app setting.' );
	$check( 200 === $call( 'release', $token, $release )->get_status(), 'Repeated identical publish must be idempotent.' );
	$check( 409 === $call( 'release', $token, array_merge( $release, array( 'sha256' => hash( 'sha256', 'different' ) ) ) )->get_status(), 'A version cannot silently replace its signed artifact.' );
	$check( 409 === $call( 'release', $token, array_merge( $release, array( 'project_id' => '22222222-2222-4222-8222-222222222222' ) ) )->get_status(), 'Another signing identity cannot replace the app.' );
	$check( 200 === $call( 'release', $token, array_merge( $release, array( 'version' => 2 ) ) )->get_status(), 'Newer release from same app may publish.' );
	$check( 409 === $call( 'release', $token, $release )->get_status(), 'A stale version cannot roll back the live download.' );
	delete_option( WBTM_App_Builder_Connection::OPTION );
	$check( 401 === $call( 'manifest', $token )->get_status(), 'Revoked connection code must fail immediately.' );
} finally {
	foreach ( $before as $key => $value ) {
		if ( null === $value ) {
			delete_option( $key );
		} else {
			update_option( $key, $value, false );
		}
	}
	if ( null === $https_before ) {
		unset( $_SERVER['HTTPS'] );
	} else {
		$_SERVER['HTTPS'] = $https_before;
	}
}
WP_CLI::success( $checked . ' app builder connection checks passed; original options restored.' );
