<?php
/**
 * Just enough of WordPress's hook system for the loader scenarios:
 * add_action / did_action / do_action, with priority ordering and
 * WordPress's own de-duplication (registering the same callback on the
 * same hook at the same priority twice is a no-op).
 *
 * That de-duplication is load-bearing for the loader: N bundled copies of
 * the SDK all call add_action('plugins_loaded', 'appneck_sdk_load_latest', 0),
 * and the result must be exactly one call, not N.
 */

$GLOBALS['appneck_test_hooks']     = array();
$GLOBALS['appneck_test_did_action'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// WordPress keys callbacks by a unique id, so an identical
		// function name at the same priority replaces rather than
		// appends. A plain string callback is its own unique id.
		$id = is_string( $callback ) ? $callback : spl_object_hash( (object) array( $callback ) );

		$GLOBALS['appneck_test_hooks'][ $hook ][ $priority ][ $id ] = $callback;

		return true;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return isset( $GLOBALS['appneck_test_did_action'][ $hook ] )
			? $GLOBALS['appneck_test_did_action'][ $hook ]
			: 0;
	}
}

function appneck_test_do_action( $hook ) {
	if ( ! isset( $GLOBALS['appneck_test_did_action'][ $hook ] ) ) {
		$GLOBALS['appneck_test_did_action'][ $hook ] = 0;
	}

	++$GLOBALS['appneck_test_did_action'][ $hook ];

	if ( empty( $GLOBALS['appneck_test_hooks'][ $hook ] ) ) {
		return;
	}

	$by_priority = $GLOBALS['appneck_test_hooks'][ $hook ];
	ksort( $by_priority );

	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			call_user_func( $callback );
		}
	}
}
