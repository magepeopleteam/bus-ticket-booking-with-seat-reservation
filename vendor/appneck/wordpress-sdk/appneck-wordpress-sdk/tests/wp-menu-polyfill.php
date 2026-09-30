<?php
/**
 * Just enough of wp-admin's menu, screen, nonce and transient API for
 * LicensePage/LicenseNotice's own tests — real enough to assert against,
 * same convention as wp-admin-polyfill.php (esc_* functions actually
 * escape, check_admin_referer actually records what it checked).
 *
 * add_menu_page()/add_submenu_page() record every call and return a
 * synthetic hook_suffix built the same way WordPress does
 * ("{$parent}_page_{$menu_slug}" or "toplevel_page_{$menu_slug}"), so a
 * test can assert the RIGHT screen id without hardcoding WordPress's own
 * internal string-building rule twice.
 */

if ( ! isset( $GLOBALS['appneck_test_menu'] ) ) {
	$GLOBALS['appneck_test_menu'] = array(
		'pages'           => array(),
		'current_screen'  => null,
		'current_user_id' => 1,
	);
}

if ( ! isset( $GLOBALS['appneck_test_transients'] ) ) {
	$GLOBALS['appneck_test_transients'] = array();
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon = '', $position = null ) {
		$hook = 'toplevel_page_' . $menu_slug;

		$GLOBALS['appneck_test_menu']['pages'][ $menu_slug ] = array(
			'type'     => 'top',
			'hook'     => $hook,
			'title'    => $page_title,
			'callback' => $callback,
		);

		return $hook;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		$hook = preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $parent_slug ) ) . '_page_' . $menu_slug;

		$GLOBALS['appneck_test_menu']['pages'][ $menu_slug ] = array(
			'type'     => 'sub',
			'parent'   => $parent_slug,
			'hook'     => $hook,
			'title'    => $page_title,
			'callback' => $callback,
		);

		return $hook;
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		if ( null === $GLOBALS['appneck_test_menu']['current_screen'] ) {
			return null;
		}

		return (object) array( 'id' => $GLOBALS['appneck_test_menu']['current_screen'] );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) $GLOBALS['appneck_test_menu']['current_user_id'];
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	// Deliberately does NOT define wp_die: LicensePage/LicenseNotice's
	// deny() falls back to recording ->denied when wp_die is absent, and
	// this suite relies on that fallback to observe a refusal without
	// ending the test process — see wp-admin-polyfill.php's own comment
	// on the same pattern.
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) {
		return gmdate( $format, $timestamp );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['appneck_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => 0 === $ttl ? 0 : time() + $ttl,
		);

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		if ( ! isset( $GLOBALS['appneck_test_transients'][ $key ] ) ) {
			return false;
		}

		$entry = $GLOBALS['appneck_test_transients'][ $key ];

		if ( 0 !== $entry['expires'] && $entry['expires'] < time() ) {
			unset( $GLOBALS['appneck_test_transients'][ $key ] );

			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['appneck_test_transients'][ $key ] );

		return true;
	}
}
