<?php

namespace Appneck\Sdk\Tests;

use Appneck\Sdk\Logging\Logger;

/** Captures log lines so a test can assert an event was reported. */
final class RecordingLogger implements Logger {

	/** @var array<int, array{message: string, context: array}> */
	public $lines = array();

	public function error( $message, array $context = array() ) {
		$this->lines[] = array(
			'message' => (string) $message,
			'context' => $context,
		);
	}

	public function contains( $needle ) {
		foreach ( $this->lines as $line ) {
			if ( false !== stripos( $line['message'], $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
