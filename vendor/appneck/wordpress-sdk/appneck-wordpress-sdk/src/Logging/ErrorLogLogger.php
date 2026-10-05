<?php

namespace Appneck\Sdk\Logging;

/**
 * Writes to PHP's error log. Offered for plugin authors who want SDK
 * failures visible without wiring their own logger.
 */
final class ErrorLogLogger implements Logger {

	/** @var string */
	private $prefix;

	public function __construct( $prefix = 'Appneck SDK' ) {
		$this->prefix = (string) $prefix;
	}

	public function error( $message, array $context = array() ) {
		$line = '[' . $this->prefix . '] ' . (string) $message;

		if ( ! empty( $context ) ) {
			// JSON_PARTIAL_OUTPUT_ON_ERROR so a context value that cannot
			// be encoded degrades to a partial line instead of making the
			// logging call itself the thing that fails.
			$encoded = json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
			if ( is_string( $encoded ) ) {
				$line .= ' ' . $encoded;
			}
		}

		error_log( $line );
	}
}
