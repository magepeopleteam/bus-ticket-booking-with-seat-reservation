<?php
/**
 * The wp-admin functions ConsentNotice uses to render and to handle a
 * click. Deliberately real enough to be worth asserting against:
 * esc_html/esc_attr actually escape, so a test can prove the notice's
 * output is escaped rather than trusting that the call was written.
 *
 * check_admin_referer records the action it verified and returns false on
 * a bad nonce instead of dying, so the refusal is observable.
 */

if ( ! isset( $GLOBALS['appneck_test_admin'] ) ) {
	$GLOBALS['appneck_test_admin'] = array(
		'can'       => true,
		'nonce_ok'  => true,
		'referer'   => 'https://example.test/wp-admin/options-general.php',
		'redirects' => array(),
		'checked'   => array(),
	);
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return (bool) $GLOBALS['appneck_test_admin']['can'];
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action ) {
		echo '<input type="hidden" name="_wpnonce" value="nonce-for-' . esc_attr( $action ) . '" />';
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) {
		return 'nonce-for-' . $action;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action ) {
		$GLOBALS['appneck_test_admin']['checked'][] = (string) $action;

		return (bool) $GLOBALS['appneck_test_admin']['nonce_ok'];
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action, $query_arg = false, $stop = true ) {
		$GLOBALS['appneck_test_admin']['checked'][] = (string) $action;

		return (bool) $GLOBALS['appneck_test_admin']['nonce_ok'];
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() {
		return $GLOBALS['appneck_test_admin']['referer'];
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

function appneck_test_reset_admin() {
	$GLOBALS['appneck_test_admin'] = array(
		'can'       => true,
		'nonce_ok'  => true,
		'referer'   => 'https://example.test/wp-admin/options-general.php',
		'redirects' => array(),
		'checked'   => array(),
	);
}
