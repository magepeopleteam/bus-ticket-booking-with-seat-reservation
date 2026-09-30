<?php
/**
 * The four wp_options functions WpOptionsCredentialStore uses, backed by
 * a global array. Deliberately mirrors WordPress's real return-value
 * quirks — notably update_option() returning FALSE when the value is
 * unchanged, which is exactly the behaviour the store has to tolerate.
 */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return isset( $GLOBALS['appneck_test_options'][ $name ] )
			? $GLOBALS['appneck_test_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$existing = isset( $GLOBALS['appneck_test_options'][ $name ] )
			? $GLOBALS['appneck_test_options'][ $name ]
			: null;

		if ( $existing === $value ) {
			return false; // WordPress does this.
		}

		$GLOBALS['appneck_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['appneck_test_options'][ $name ] );

		return true;
	}
}
