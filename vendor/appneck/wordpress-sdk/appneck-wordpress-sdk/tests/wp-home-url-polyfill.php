<?php
/**
 * home_url(), the one WordPress function License reads when no domain
 * has been set explicitly.
 *
 * Backed by a global so a test can point the "site" at any URL it likes
 * and prove the normalization is applied on the way out — the default
 * path a real plugin takes, as opposed to the set_domain() override the
 * rest of the licensing tests use.
 */

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		$base = isset( $GLOBALS['appneck_test_home_url'] )
			? $GLOBALS['appneck_test_home_url']
			: 'https://example.test';

		return $base . ltrim( (string) $path, '/' );
	}
}
