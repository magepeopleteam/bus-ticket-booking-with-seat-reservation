<?php
/** Self-cleaning WP-CLI contract/security checks. No bookings, mail or payments. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die;
}

$keys   = array( WBTM_App_Builder_Connection::OPTION, 'wbtm_app_builder_release', WBTM_App_Settings::OPTION, 'wbtm_operator_builder_release', 'wbtm_operator_settings' );
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
	$check( 400 === $call( 'manifest', $token, array( 'app_variant' => array( 'operator' ) ) )->get_status(), 'Only exact app variants are accepted.' );
	foreach ( array(
		array( 'POST', '/wbtm-app/v1/builder/manifest', true ),
		array( 'POST', '/wbtm-app/v1/builder/release', true ),
		array( 'GET', '/wbtm-app/v1/builder/manifest', false ),
		array( 'POST', '/wbtm-app/v1/builder/manifest/', false ),
		array( 'POST', '/wbtm-app/v1/builder/manifest/extra', false ),
		array( 'POST', '/wbtm-app/v1/config', false ),
		array( 'POST', '/wbtm-app/v1/auth/login', false ),
	) as $scope ) {
		$scope_request = new WP_REST_Request( $scope[0], $scope[1] );
		$scope_request->set_header( 'Content-Type', 'application/json' );
		$scope_request->set_body( wp_json_encode( array( 'app_variant' => 'operator' ) ) );
		$check( WBTM_App_Builder_Connection::is_operator_builder_request( $scope_request ) === $scope[2], 'Only the two exact operator builder POST routes can bypass customer enablement: ' . $scope[1] );
	}
	$scope_request->set_route( '/wbtm-app/v1/builder/manifest' );
	$scope_request->set_body( '{}' );
	$scope_request->set_query_params( array( 'app_variant' => 'operator' ) );
	$check( ! WBTM_App_Builder_Connection::is_operator_builder_request( $scope_request ), 'A query parameter cannot opt a customer build request into operator enablement.' );
	if ( class_exists( 'WBTM_Operator_Settings' ) && class_exists( 'WBTM_Operator' ) && class_exists( 'WBTM_Admission_Store' ) && WBTM_Admission_Store::ready() ) {
		$operator_settings            = WBTM_Operator_Settings::get();
		$operator_settings['enabled'] = 'no';
		update_option( WBTM_Operator_Settings::OPTION, $operator_settings, false );
		$check( 409 === $call( 'manifest', $token, array( 'app_variant' => 'operator' ) )->get_status(), 'Disabled Operator App cannot build.' );
		$operator_settings['enabled'] = 'yes';
		$operator_settings['name']    = 'Actual Operator Name';
		$operator_settings['logo']    = 'https://bus.business.com/operator-logo.png';
		update_option( WBTM_Operator_Settings::OPTION, $operator_settings, false );
		$settings['app_enabled'] = 'no';
		update_option( WBTM_App_Settings::OPTION, $settings, false );
		$customer_before         = get_option( WBTM_App_Settings::OPTION );
		$customer_release_before = get_option( 'wbtm_app_builder_release' );
		$operator_manifest       = $call( 'manifest', $token, array( 'app_variant' => 'operator' ) );
		$check( 200 === $operator_manifest->get_status(), 'Operator builds work independently when the customer API is disabled.' );
		$operator_data = $operator_manifest->get_data();
		$check( 'operator' === $operator_data['app_variant'] && true === $operator_data['capabilities']['operator'], 'Operator manifest declares an explicit enabled variant capability.' );
		$check( 'Actual Operator Name' === $operator_data['config']['branding']['name'] && home_url( '/' ) === $operator_data['config']['site']['url'], 'Operator build uses its actual website and branding.' );
		$check( ! isset( $operator_data['users'], $operator_data['passengers'], $operator_data['assignments'], $operator_data['config']['support'], $operator_data['config']['legal'] ), 'Only normalized build fields are exposed.' );
		$check( '[wbtm_operator_app_download]' === $operator_data['website']['download_shortcode'] && 'wbtm/app-download' === $operator_data['website']['download_block'] && array( 'appVariant' => 'operator' ) === $operator_data['website']['download_block_attributes'], 'Operator builds advertise their own shortcode and selected editable block.' );
		$check( 401 === $call( 'manifest', bin2hex( random_bytes( 32 ) ), array( 'app_variant' => 'operator' ) )->get_status(), 'Standalone operator connection still requires the correct hashed code.' );
		foreach ( array( '/wbtm-app/v1/config', '/wbtm-app/v1/me', '/wbtm-app/v1/bookings' ) as $private_route ) {
			$private_request = new WP_REST_Request( 'GET', $private_route );
			$private_request->set_header( 'X-WBTM-Builder-Token', $token );
			$private_request->set_param( 'app_variant', 'operator' );
			$check( 404 === rest_do_request( $private_request )->get_status(), 'Operator variant cannot bypass the disabled customer API: ' . $private_route );
		}
		delete_option( 'wbtm_operator_builder_release' );
		$operator_release = array_merge(
			$release,
			array(
				'app_variant' => 'operator',
				'project_id'  => '33333333-3333-4333-8333-333333333333',
				'url'         => 'https://apps.bithi.online/download/test/Actual%20Operator%20Name.apk',
			)
		);
		$check( 200 === $call( 'release', $token, $operator_release )->get_status(), 'Operator APK publication works with customer API disabled.' );
		$check( WBTM_Operator_Settings::get()['android_apk_url'] === $operator_release['url'], 'Operator URL updates only its settings.' );
		$check( get_option( WBTM_App_Settings::OPTION ) === $customer_before && get_option( 'wbtm_app_builder_release' ) === $customer_release_before, 'Operator publication preserves every customer setting and release identity.' );
		$check( get_option( 'wbtm_operator_builder_release' )['project_id'] === $operator_release['project_id'], 'Operator identity is independently guarded.' );
		$check( 200 === $call( 'release', $token, $operator_release )->get_status(), 'Identical operator publication is idempotent.' );
		$check( 409 === $call( 'release', $token, array_merge( $operator_release, array( 'project_id' => $release['project_id'] ) ) )->get_status(), 'Customer project identity cannot overwrite an operator release.' );
		$check( 409 === $call( 'release', $token, array_merge( $operator_release, array( 'sha256' => hash( 'sha256', 'operator-conflict' ) ) ) )->get_status(), 'Operator artifact checksum conflicts fail.' );
		$check( 200 === $call( 'release', $token, array_merge( $operator_release, array( 'version' => 2 ) ) )->get_status(), 'Operator version can advance independently.' );
		$check( 409 === $call( 'release', $token, $operator_release )->get_status(), 'Older operator release cannot replace the current download.' );
		$_SERVER['HTTPS'] = 'off';
		$check( in_array( $call( 'manifest', $token, array( 'app_variant' => 'operator' ) )->get_status(), array( 401, 403 ), true ), 'Standalone operator build connection always requires HTTPS.' );
		$_SERVER['HTTPS']        = 'on';
		$settings['app_enabled'] = 'yes';
		update_option( WBTM_App_Settings::OPTION, $settings, false );
		$private_request = new WP_REST_Request( 'GET', '/wbtm-app/v1/me' );
		$private_request->set_header( 'X-WBTM-Builder-Token', $token );
		$private_request->set_param( 'app_variant', 'operator' );
		$check( 401 === rest_do_request( $private_request )->get_status(), 'A builder code cannot authenticate a customer when the API is enabled.' );
		$operator_before         = get_option( WBTM_Operator_Settings::OPTION );
		$operator_release_before = get_option( 'wbtm_operator_builder_release' );
		$check( 200 === $call( 'release', $token, array_merge( $release, array( 'version' => 3 ) ) )->get_status(), 'Customer publication can advance after an operator release.' );
		$check( get_option( WBTM_Operator_Settings::OPTION ) === $operator_before && get_option( 'wbtm_operator_builder_release' ) === $operator_release_before, 'Customer publication preserves every operator setting and release identity.' );
	} else {
		$check( 409 === $call( 'manifest', $token, array( 'app_variant' => 'operator' ) )->get_status(), 'Missing compatible PRO/storage provider stops operator builds.' );
	}
	delete_option( WBTM_App_Builder_Connection::OPTION );
	$check( 401 === $call( 'manifest', $token )->get_status(), 'Revoked connection code must fail immediately.' );
} finally {
	// Restore raw option snapshots even when a newer sanitizer adds defaults.
	$restore_filters = array();
	foreach ( array( 'WBTM_App_Settings', 'WBTM_Operator_Settings' ) as $settings_class ) {
		if ( class_exists( $settings_class ) && remove_filter( 'sanitize_option_' . $settings_class::OPTION, array( $settings_class, 'sanitize' ) ) ) {
			$restore_filters[] = $settings_class;
		}
	}
	foreach ( $before as $key => $value ) {
		if ( null === $value ) {
			delete_option( $key );
		} else {
			update_option( $key, $value, false );
		}
	}
	foreach ( $restore_filters as $settings_class ) {
		add_filter( 'sanitize_option_' . $settings_class::OPTION, array( $settings_class, 'sanitize' ) );
	}
	foreach ( $before as $key => $value ) {
		$check( get_option( $key, null ) === $value, 'Original raw option snapshot must be restored: ' . $key );
	}
	if ( null === $https_before ) {
		unset( $_SERVER['HTTPS'] );
	} else {
		$_SERVER['HTTPS'] = $https_before;
	}
}
WP_CLI::success( $checked . ' app builder connection checks passed; original options restored.' );
