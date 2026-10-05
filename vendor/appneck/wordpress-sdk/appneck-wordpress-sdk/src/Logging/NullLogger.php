<?php

namespace Appneck\Sdk\Logging;

/**
 * The default. An SDK embedded in someone else's site does not get to
 * decide that their error log should fill up with our diagnostics, so
 * logging is opt-in — the host plugin passes a real logger if it wants
 * one. Failures are still reported through the Response object either
 * way, so choosing this loses nothing the caller cannot see.
 */
final class NullLogger implements Logger {

	public function error( $message, array $context = array() ) {
		// Intentionally empty.
	}
}
