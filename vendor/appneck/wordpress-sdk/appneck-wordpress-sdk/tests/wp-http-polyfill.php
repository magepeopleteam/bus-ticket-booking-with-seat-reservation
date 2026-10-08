<?php
/**
 * wp_remote_request(), recording instead of sending — so a test can prove
 * that NOTHING left the site, including requests made by a Client whose
 * transport the test did not inject (Sdk::bootstrap() builds the 3-second
 * client with its own WpHttpTransport). Journal §70 D1.
 *
 * Returns a plain 200 with an empty JSON body; a test that wants real
 * responses injects its own Transport instead.
 */

if ( ! isset( $GLOBALS['appneck_test_http'] ) ) {
	$GLOBALS['appneck_test_http'] = array();
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( $url, $args = array() ) {
		$GLOBALS['appneck_test_http'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'headers'  => array(),
			'body'     => '{}',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
		);
	}
}
