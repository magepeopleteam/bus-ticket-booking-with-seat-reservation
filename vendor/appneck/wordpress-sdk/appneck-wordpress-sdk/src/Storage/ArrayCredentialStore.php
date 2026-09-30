<?php

namespace Appneck\Sdk\Storage;

/**
 * In-memory store, for tests and for callers that manage persistence
 * themselves. Shipped rather than kept in tests/ so the package's own
 * test suite needs no WordPress, and so a plugin author can exercise the
 * SDK in WP-CLI or a unit test without touching the options table.
 */
final class ArrayCredentialStore implements CredentialStore {

	/** @var string|null */
	private $installation_id;

	/** @var string|null */
	private $installation_secret;

	public function __construct( $installation_id = null, $installation_secret = null ) {
		$this->installation_id     = $installation_id;
		$this->installation_secret = $installation_secret;
	}

	public function get_installation_id() {
		return $this->installation_id;
	}

	public function get_installation_secret() {
		return $this->installation_secret;
	}

	public function save( $installation_id, $installation_secret ) {
		$this->installation_id     = (string) $installation_id;
		$this->installation_secret = (string) $installation_secret;

		return true;
	}

	public function forget() {
		$this->installation_id     = null;
		$this->installation_secret = null;

		return true;
	}

	public function has_credentials() {
		return ! empty( $this->installation_id ) && ! empty( $this->installation_secret );
	}
}
