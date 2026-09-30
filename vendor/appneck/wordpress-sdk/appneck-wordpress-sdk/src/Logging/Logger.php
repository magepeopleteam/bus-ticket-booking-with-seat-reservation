<?php

namespace Appneck\Sdk\Logging;

/**
 * Minimal logging seam. Deliberately not PSR-3: pulling a Composer
 * dependency into a package that must work as a bundled directory with
 * no vendor/ present would defeat the point, and the SDK needs exactly
 * one level.
 */
interface Logger {

	/**
	 * @param string               $message Human-readable, no secrets.
	 * @param array<string, mixed> $context Structured detail.
	 */
	public function error( $message, array $context = array() );
}
