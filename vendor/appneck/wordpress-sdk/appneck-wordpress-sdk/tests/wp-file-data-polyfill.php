<?php
/**
 * get_file_data(), reduced to what Environment reads: a "Header: value"
 * line in the first 8 KB of the file, as WordPress itself does.
 */

if ( ! function_exists( 'get_file_data' ) ) {
	function get_file_data( $file, $default_headers, $context = '' ) {
		$contents = (string) file_get_contents( $file, false, null, 0, 8192 );
		$result   = array();

		foreach ( $default_headers as $field => $header ) {
			$result[ $field ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':(.*)$/mi', $contents, $match )
				? trim( $match[1] )
				: '';
		}

		return $result;
	}
}
