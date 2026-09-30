<?php
/**
 * Minimal wp_cron: enough for Lifecycle's scheduling to run for real.
 * Mirrors WordPress in the ways that matter here — one entry per
 * hook+timestamp, and wp_clear_scheduled_hook removing all of them.
 */

if ( ! isset( $GLOBALS['appneck_test_cron'] ) ) {
	$GLOBALS['appneck_test_cron'] = array();
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		$GLOBALS['appneck_test_cron'][ $hook ][] = (int) $timestamp;

		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		unset( $GLOBALS['appneck_test_cron'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		if ( empty( $GLOBALS['appneck_test_cron'][ $hook ] ) ) {
			return false;
		}

		return min( $GLOBALS['appneck_test_cron'][ $hook ] );
	}
}

function appneck_test_is_scheduled( $hook ) {
	return ! empty( $GLOBALS['appneck_test_cron'][ $hook ] );
}

function appneck_test_next_scheduled( $hook ) {
	return appneck_test_is_scheduled( $hook ) ? min( $GLOBALS['appneck_test_cron'][ $hook ] ) : 0;
}
