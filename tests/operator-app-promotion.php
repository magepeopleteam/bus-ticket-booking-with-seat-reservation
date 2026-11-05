<?php
/**
 * Real WordPress promotion contracts. Run with the operator test flock and wp eval-file.
 * Also run with --skip-plugins=addon-bus--ticket-booking-with-seat-pro for Free-only coverage.
 *
 * @package WBTM
 */

// phpcs:disable WordPress.Files.FileName -- Established CLI fixture naming.
// phpcs:disable WordPress.WP.GlobalVariablesOverride -- In-memory WordPress query fixture is restored in finally.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$promo_checks          = 0;
$promo_original        = array( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['post'] ?? null, $GLOBALS['shortcode_tags'], $_SERVER );
$promo_customer        = WBTM_App_Settings::defaults();
$promo_customer        = array_merge(
	$promo_customer,
	array(
		'app_promo_enabled'   => 'yes',
		'app_name'            => 'Fixture Customer',
		'app_android_apk_url' => 'https://example.invalid/customer.apk',
		'app_logo'            => '',
		'app_primary_color'   => '#4355cc',
	)
);
$promo_operator        = class_exists( 'WBTM_Operator_Settings' ) ? array_merge(
	WBTM_Operator_Settings::defaults(),
	array(
		'enabled'         => 'yes',
		'promo_enabled'   => 'yes',
		'name'            => 'Fixture Operator',
		'logo'            => '',
		'primary_color'   => '#5445d8',
		'android_apk_url' => 'https://example.invalid/operator.apk',
		'qr_enabled'      => 'no',
		'manual_checkin'  => 'no',
	)
) : array();
$promo_filters         = array();
$promo_mail            = static function () {
	return true;
};
$promo_assert          = static function ( $condition, $label ) use ( &$promo_checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'Promotion check failed: ' . esc_html( $label ) );
	}
	++$promo_checks;
};
$promo_customer_filter = static function () use ( &$promo_customer ) {
	return $promo_customer;
};
$promo_operator_filter = static function () use ( &$promo_operator ) {
	return $promo_operator;
};
add_filter( 'pre_option_' . WBTM_App_Settings::OPTION, $promo_customer_filter );
add_filter( 'pre_option_wbtm_operator_settings', $promo_operator_filter );
add_filter( 'pre_wp_mail', $promo_mail );

try {
	$promotion = new WBTM_App_Promotion();
	$customer  = $promotion->shortcode();
	$promo_assert( str_contains( $customer, 'Fixture Customer' ) && str_contains( $customer, 'customer.apk' ), 'default customer remains dynamic' );
	$promo_assert( '' === $promotion->shortcode(), 'customer shortcode deduplicates' );
	$promo_assert( '' === ( new WBTM_App_Promotion() )->shortcode( array( 'app' => 'unknown' ) ), 'unknown shortcode app fails closed' );
	$promo_assert( '' === ( new WBTM_App_Promotion() )->render_block( array( 'appVariant' => array( 'operator' ) ) ), 'malformed block app fails closed' );
	$promo_assert( '' === ( new WBTM_App_Promotion() )->render_block( array( 'appVariant' => 'unknown' ) ), 'unknown block app fails closed' );
	$metadata = json_decode( file_get_contents( WBTM_PLUGIN_DIR . '/blocks/app-download/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local immutable block metadata.
	$promo_assert( array( 'customer', 'operator' ) === $metadata['attributes']['appVariant']['enum'] && 'customer' === $metadata['attributes']['appVariant']['default'], 'editor variant schema is explicit and backwards compatible' );
	$promo_assert( true === $metadata['supports']['multiple'], 'both app blocks may be inserted on one page' );
	$promo_assert( shortcode_exists( 'wbtm_operator_app_download' ), 'operator alias is registered in Free' );
	if ( ! class_exists( 'WBTM_Operator_Settings' ) ) {
		$promo_assert( '' === $promotion->operator_shortcode(), 'Free-only operator banner is hidden' );
		$promo_assert( '' === ( new WBTM_App_Promotion() )->shortcode( array( 'app' => 'operator' ) ), 'Free-only shared operator shortcode is hidden' );
		$promo_assert( '' === ( new WBTM_App_Promotion() )->render_block( array( 'appVariant' => 'operator' ) ), 'Free-only operator block is hidden' );
	} else {
		$operator = $promotion->operator_shortcode();
		$promo_assert( str_contains( $operator, 'Fixture Operator' ) && str_contains( $operator, 'operator.apk' ) && ! str_contains( $operator, 'customer.apk' ), 'operator identity and link are isolated' );
		$promo_assert( str_contains( $operator, 'Passenger manifests' ) && str_contains( $operator, 'Attendance status' ) && ! str_contains( $operator, 'Scan QR' ), 'default features are valid with QR and manual check-in disabled' );
		$promo_assert( str_contains( $operator, 'Approved operator account required' ) && str_contains( $operator, 'download="Fixture Operator.apk"' ), 'operator eligibility and dynamic filename are clear' );
		$promo_assert( '' === $promotion->operator_shortcode() && '' === $promotion->shortcode( array( 'app' => 'operator' ) ), 'operator aliases share per-variant deduplication' );
		$promotion = new WBTM_App_Promotion();
		$dual      = do_shortcode( '[wbtm_app_download][wbtm_operator_app_download][wbtm_app_download app="operator"]' );
		$promo_assert( 2 === substr_count( $dual, '<section ' ) && str_contains( $dual, 'data-app-variant="customer"' ) && str_contains( $dual, 'data-app-variant="operator"' ), 'real WordPress parser renders both variants once' );
		$promo_operator['name']               = 'Updated Operator';
		$promo_operator['promo_title']        = 'Your departure workspace';
		$promo_operator['promo_description']  = 'Live assigned services and passenger attendance.';
		$promo_operator['promo_button_label'] = 'Install operator app';
		$promo_operator['android_apk_url']    = 'https://example.invalid/updated-operator.apk';
		$updated                              = ( new WBTM_App_Promotion() )->operator_shortcode();
		$promo_assert( str_contains( $updated, 'Your departure workspace' ) && str_contains( $updated, 'Live assigned services' ) && str_contains( $updated, 'Install operator app' ) && str_contains( $updated, 'updated-operator.apk' ) && str_contains( $updated, 'Updated Operator.apk' ), 'all operator promotion updates appear dynamically' );
		$unsafe = ( new WBTM_App_Promotion() )->shortcode(
			array(
				'app'          => 'operator',
				'heading'      => '<script>bad()</script>Safe heading',
				'description'  => '<img src=x onerror=bad()>Safe details',
				'url'          => 'https://attacker.invalid/app.apk',
				'name'         => 'Injected Identity',
				'logo'         => 'https://attacker.invalid/logo.png',
				'accent_color' => '#ff0000',
			)
		);
		$promo_assert( ! str_contains( $unsafe, '<script' ) && ! str_contains( $unsafe, 'onerror=' ) && ! str_contains( $unsafe, 'attacker.invalid' ) && ! str_contains( $unsafe, 'Injected Identity' ), 'page attributes cannot inject markup or trusted identity' );
		$promo_assert( str_contains( $unsafe, '--wbtm-app-accent:#5445d8' ), 'operator color follows effective PRO branding' );
		$promo_operator['logo'] = 'https://example.invalid/operator-logo.svg';
		$logo_html              = ( new WBTM_App_Promotion() )->operator_shortcode();
		$promo_assert( str_contains( $logo_html, 'operator-logo.svg' ), 'operator logo follows its own current PRO settings' );
		$promo_operator['logo']              = '';
		$promo_operator['primary_color']     = '';
		$promo_customer['app_primary_color'] = '#176b5b';
		$inherited_html                      = ( new WBTM_App_Promotion() )->operator_shortcode();
		$promo_assert( str_contains( $inherited_html, '--wbtm-app-accent:#176b5b' ), 'effective PRO branding inheritance remains authoritative' );
		$promo_operator['primary_color'] = '#5445d8';
		$block_html                      = do_blocks( '<!-- wp:wbtm/app-download {"appVariant":"operator"} /--><!-- wp:wbtm/app-download /-->' );
		$promo_assert( 2 === substr_count( $block_html, '<section ' ) && str_contains( $block_html, 'data-app-variant="operator"' ) && str_contains( $block_html, 'data-app-variant="customer"' ), 'actual dynamic block renderer supports both variants on one page' );

		$promo_assert( str_contains( ( new WBTM_App_Promotion() )->operator_shortcode( array( 'app' => 'customer' ) ), 'data-app-variant="operator"' ), 'operator alias cannot switch to customer' );
		foreach ( array( '', 'http://example.invalid/a.apk', 'https://user:secret@example.invalid/a.apk', 'https://@example.invalid/a.apk', 'javascript:alert(1)', array() ) as $invalid ) {
			$promo_operator['android_apk_url'] = $invalid;
			$promo_assert( '' === ( new WBTM_App_Promotion() )->operator_shortcode(), 'missing or invalid operator URL hides card with no customer fallback' );
		}
		$promo_operator['android_apk_url'] = 'https://example.invalid/operator.apk';
		$promo_operator['promo_enabled']   = 'no';
		$promo_assert( '' === ( new WBTM_App_Promotion() )->operator_shortcode(), 'operator presentation requires separate explicit opt-in' );
		$promo_operator['promo_enabled'] = 'yes';
		$promo_operator['enabled']       = 'no';
		$promo_assert( '' === ( new WBTM_App_Promotion() )->operator_shortcode(), 'disabled operator feature hides operator card' );
		$promo_operator['enabled'] = 'yes';
		$sanitized                 = WBTM_Operator_Settings::sanitize(
			array(
				'name'               => str_repeat( 'n', 120 ),
				'support_phone'      => str_repeat( 'p', 150 ),
				'promo_title'        => '<b>' . str_repeat( 't', 170 ) . '</b>',
				'promo_description'  => "Line one\n" . str_repeat( 'd', 650 ),
				'promo_button_label' => str_repeat( 'b', 90 ),
				'promo_enabled'      => array( 'yes' ),
			)
		);
		$promo_assert( 80 === strlen( $sanitized['name'] ) && 100 === strlen( $sanitized['support_phone'] ), 'existing field bounds remain unchanged' );
		$promo_assert( 160 === strlen( $sanitized['promo_title'] ) && 600 === strlen( $sanitized['promo_description'] ) && 80 === strlen( $sanitized['promo_button_label'] ) && 'no' === $sanitized['promo_enabled'], 'new presentation fields are strictly bounded' );
		$promo_assert( WBTM_Operator_Settings::sanitize( $sanitized ) === $sanitized, 'new sanitizer remains idempotent' );

		// In-memory singular page/query exercises actual placement and scoped enqueueing.
		$fixture_post            = new WP_Post(
			(object) array(
				'ID'            => 0,
				'filter'        => 'raw',
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => '',
				'post_content'  => '[wbtm_operator_app_download][wbtm-bus-search-form]',
			)
		);
		$query                   = new WP_Query();
		$query->is_singular      = true;
		$query->is_page          = true;
		$query->in_the_loop      = true;
		$query->queried_object   = $fixture_post;
		$query->post             = $fixture_post;
		$GLOBALS['wp_query']     = $query;
		$GLOBALS['wp_the_query'] = $query;
		$GLOBALS['post']         = $fixture_post;
		$promo_assert( is_singular() && in_the_loop() && is_main_query() && has_shortcode( $fixture_post->post_content, 'wbtm-bus-search-form' ), 'synthetic query is singular main search' );
		$auto = ( new WBTM_App_Promotion() )->prepend_to_search( 'existing content' );
		$promo_assert( str_contains( $auto, 'data-app-variant="customer"' ) && str_ends_with( $auto, 'existing content' ), 'explicit operator placement preserves customer search auto placement' );
		$fixture_post->post_content = '[wbtm_app_download app="customer"][wbtm-bus-search-form]';
		$promo_assert( 'existing content' === ( new WBTM_App_Promotion() )->prepend_to_search( 'existing content' ), 'explicit customer shortcode still suppresses automatic duplicate' );
		$fixture_post->post_content = '<!-- wp:group --><div><!-- wp:wbtm/app-download {"appVariant":"operator"} /--></div><!-- /wp:group -->[wbtm-bus-search-form]';
		$promo_assert( str_contains( ( new WBTM_App_Promotion() )->prepend_to_search( 'existing' ), 'data-app-variant="customer"' ), 'nested operator block does not suppress automatic customer card' );
		$fixture_post->post_content = '<!-- wp:wbtm/app-download /-->[wbtm-bus-search-form]';
		$promo_assert( 'existing' === ( new WBTM_App_Promotion() )->prepend_to_search( 'existing' ), 'legacy default customer block preserves explicit placement' );
		$promo_customer['app_promo_enabled'] = 'no';
		$fixture_post->post_content          = '[wbtm_operator_app_download]';
		wp_dequeue_style( WBTM_App_Promotion::STYLE_HANDLE );
		( new WBTM_App_Promotion() )->enqueue_assets();
		$promo_assert( wp_style_is( WBTM_App_Promotion::STYLE_HANDLE, 'enqueued' ), 'operator-only page gets shared scoped assets even with customer promotion off' );
		wp_dequeue_style( WBTM_App_Promotion::STYLE_HANDLE );
		$fixture_post->post_content = 'Unrelated content';
		( new WBTM_App_Promotion() )->enqueue_assets();
		$promo_assert( ! wp_style_is( WBTM_App_Promotion::STYLE_HANDLE, 'enqueued' ), 'unrelated pages do not enqueue banner styles' );
		$fixture_post->post_content      = '[wbtm_operator_app_download]';
		$promo_operator['promo_enabled'] = 'no';
		( new WBTM_App_Promotion() )->enqueue_assets();
		$promo_assert( ! wp_style_is( WBTM_App_Promotion::STYLE_HANDLE, 'enqueued' ), 'disabled operator-only banner does not enqueue styles' );
		$promo_operator['promo_enabled']     = 'yes';
		$promo_customer['app_promo_enabled'] = 'yes';

		// Observe a real connector release through option filters without persisting site settings.
		$writes = array();
		foreach ( array( WBTM_Operator_Settings::OPTION, 'wbtm_operator_builder_release' ) as $key ) {
			$filter                = static function ( $value, $old ) use ( &$writes, $key ) {
				$writes[ $key ] = $value;
				return $old;
			};
			$promo_filters[ $key ] = $filter;
			add_filter( 'pre_update_option_' . $key, $filter, 10, 2 );
		}
		$empty_release = static function () {
			return array();
		};
		add_filter( 'pre_option_wbtm_operator_builder_release', $empty_release );
		$promo_filters['read_release'] = $empty_release;
		$request                       = new WP_REST_Request( 'POST', '/wbtm-app/v1/builder/release' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'app_variant' => 'operator',
					'url'         => 'https://example.invalid/release.apk',
					'version'     => 1,
					'size'        => 2048,
					'sha256'      => hash( 'sha256', 'synthetic app' ),
					'project_id'  => '11111111-1111-4111-8111-111111111111',
				)
			)
		);
		$response = ( new WBTM_App_Builder_Connection() )->release( $request );
		$promo_assert( $response instanceof WP_REST_Response && '[wbtm_operator_app_download]' === $response->get_data()['shortcode'], 'release metadata advertises operator shortcode' );
		$expected                    = WBTM_Operator_Settings::sanitize( $promo_operator );
		$expected['android_apk_url'] = 'https://example.invalid/release.apk';
		$promo_assert( $expected === $writes[ WBTM_Operator_Settings::OPTION ], 'publishing preserves every non-URL operator setting including promotion and access policy' );
		$promo_assert( 'https://example.invalid/customer.apk' === WBTM_App_Settings::get()['app_android_apk_url'], 'publishing never changes customer download identity' );

		$promo_preview = getenv( 'WBTM_OPERATOR_PROMOTION_PREVIEW' );
		if ( $promo_preview && is_dir( dirname( $promo_preview ) ) ) {
			$html  = ( new WBTM_App_Promotion() )->render_block(
				array(
					'appVariant' => 'operator',
					'heading'    => 'Your next departure, under control',
					'highlight'  => 'wherever you are',
					'iconStyle'  => 'bus',
				)
			);
			$html .= ( new WBTM_App_Promotion() )->render_block( array( 'iconStyle' => 'bus' ) );
			$css   = file_get_contents( WBTM_PLUGIN_DIR . '/assets/frontend/wbtm-app-promotion.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Actual shared production stylesheet, synthetic preview only.
			file_put_contents( $promo_preview, '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Operator download banner fixture</title><style>body{margin:0;padding:32px;background:#f5f6fb;font-family:system-ui}main{max-width:1240px;margin:auto}h1{font-size:24px;color:#14233d}a{cursor:pointer}' . $css . '</style><main><h1>Your apps, ready for the journey</h1>' . $html . '</main></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private explicitly requested synthetic preview, never a web endpoint.
			chmod( $promo_preview, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Keep preview private.
		}
	}
	WP_CLI::success( 'Passed ' . $promo_checks . ' operator promotion checks; no persistent fixtures.' );
} finally {
	remove_filter( 'pre_option_' . WBTM_App_Settings::OPTION, $promo_customer_filter );
	remove_filter( 'pre_option_wbtm_operator_settings', $promo_operator_filter );
	remove_filter( 'pre_wp_mail', $promo_mail );
	foreach ( $promo_filters as $key => $filter ) {
		remove_filter( 'read_release' === $key ? 'pre_option_wbtm_operator_builder_release' : 'pre_update_option_' . $key, $filter );
	}
	list( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['post'], $GLOBALS['shortcode_tags'], $_SERVER ) = $promo_original;
	wp_dequeue_style( WBTM_App_Promotion::STYLE_HANDLE );
}
