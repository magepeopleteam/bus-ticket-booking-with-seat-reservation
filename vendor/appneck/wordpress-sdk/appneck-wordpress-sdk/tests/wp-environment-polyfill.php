<?php
/**
 * Every WordPress function Environment reads, returning a real-looking
 * value, so a test can see the complete set of fields the SDK sends
 * (ContactGateTest's disclosure check, journal §70 D2). Load only in a
 * separate process: defining WC(), get_plugins() and the rest globally
 * would change what every other test's Environment reports.
 */

$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.25.3';

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://shop.example.test' . $path;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'version' === $show ? '6.6.2' : 'Example Shop';
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_GB';
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() {
		return 'Europe/London';
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}

if ( ! function_exists( 'WC' ) ) {
	function WC() {
		return (object) array(
			'version'   => '9.3.0',
			'countries' => new class() {
				public function get_base_country() {
					return 'GB';
				}
			},
		);
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() {
		return array(
			'acme/acme.php'             => array( 'Name' => 'Acme Free', 'Version' => '1.2.0' ),
			'woocommerce/woocommerce.php' => array( 'Name' => 'WooCommerce', 'Version' => '9.3.0' ),
		);
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class() {
			public function get( $field ) {
				return array( 'Name' => 'Storefront', 'Version' => '4.6.0', 'Author' => 'WooCommerce' )[ $field ] ?? '';
			}

			public function parent() {
				return false;
			}
		};
	}
}

if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '9.3.0' );
}
