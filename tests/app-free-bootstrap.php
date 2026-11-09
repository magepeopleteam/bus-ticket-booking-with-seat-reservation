<?php
/** Request-only plugin registry for Free-only QA. Use wp --require=<this file> eval-file app-free-native.php. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
WP_CLI::add_wp_hook( 'option_active_plugins', static function( $plugins ) {
	return array_values( array_filter( $plugins, static function( $slug ) { return 'addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php' !== $slug; } ) );
}, PHP_INT_MAX );
