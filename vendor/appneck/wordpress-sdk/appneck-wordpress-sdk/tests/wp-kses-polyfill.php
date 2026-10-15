<?php
/**
 * wp_kses() for the formatted announcement body (journal §95). Records
 * every call — allowlist and protocols included — so a test can assert
 * exactly what the SDK asked WordPress to permit, and filters for real
 * enough to assert against: unlisted tags go, every attribute but an
 * allowlisted one goes, and an href keeps only a listed protocol.
 *
 * It is not WordPress's kses and does not try to be; the real one is what
 * runs on a site. Loaded only by the tests that need it, so a test in its
 * own process can prove the SDK prints no HTML when wp_kses() is missing.
 */

if ( ! isset( $GLOBALS['appneck_test_kses_calls'] ) ) {
	$GLOBALS['appneck_test_kses_calls'] = array();
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $content, $allowed_html, $allowed_protocols = array() ) {
		$GLOBALS['appneck_test_kses_calls'][] = array(
			'allowed'   => $allowed_html,
			'protocols' => $allowed_protocols,
		);

		return preg_replace_callback(
			'#<(/?)([a-zA-Z0-9]+)([^>]*)>#',
			function ( $match ) use ( $allowed_html, $allowed_protocols ) {
				$tag = strtolower( $match[2] );

				if ( ! isset( $allowed_html[ $tag ] ) ) {
					return '';
				}

				if ( '/' === $match[1] ) {
					return '</' . $tag . '>';
				}

				$attributes = '';

				if ( isset( $allowed_html[ $tag ]['href'] ) && preg_match( '#\shref\s*=\s*"([^"]*)"#i', $match[3], $href ) ) {
					$scheme = strtolower( (string) strtok( $href[1], ':' ) );

					if ( in_array( $scheme, $allowed_protocols, true ) ) {
						$attributes = ' href="' . $href[1] . '"';
					}
				}

				return '<' . $tag . $attributes . '>';
			},
			(string) $content
		);
	}
}
