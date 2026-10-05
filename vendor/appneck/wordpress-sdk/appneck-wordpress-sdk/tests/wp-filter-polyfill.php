<?php
/**
 * Just enough of WordPress's filter system for the interval filter.
 */

if ( ! isset( $GLOBALS['appneck_test_filters'] ) ) {
	$GLOBALS['appneck_test_filters'] = array();
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		$args = array_slice( func_get_args(), 2 );

		if ( empty( $GLOBALS['appneck_test_filters'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['appneck_test_filters'][ $hook ] as $callback ) {
			$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['appneck_test_filters'][ $hook ][] = $callback;

		return true;
	}
}

function appneck_test_add_filter( $hook, $callback ) {
	$GLOBALS['appneck_test_filters'][ $hook ][] = $callback;
}

function appneck_test_clear_filters() {
	$GLOBALS['appneck_test_filters'] = array();
}
