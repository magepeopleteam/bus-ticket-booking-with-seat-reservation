<?php

namespace Appneck\Sdk\Storage;

/**
 * In-memory license state, for tests and for callers that deliberately
 * want none persisted.
 *
 * The twin of ArrayCredentialStore, and it exists for the same reason:
 * the caching, fail-open and backoff logic in \Appneck\Sdk\License is
 * the part of this SDK most worth testing exhaustively, and it must be
 * testable with no WordPress and no database.
 */
final class ArrayLicenseStore implements LicenseStore {

	/** @var array<string, mixed> */
	private $state;

	/** @param array<string, mixed> $state */
	public function __construct( array $state = array() ) {
		$this->state = $state;
	}

	/** @return array<string, mixed> */
	public function read() {
		return $this->state;
	}

	/** @param array<string, mixed> $state */
	public function write( array $state ) {
		$this->state = $state;

		return true;
	}

	public function forget() {
		$this->state = array();

		return true;
	}
}
