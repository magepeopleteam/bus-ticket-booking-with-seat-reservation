<?php

namespace Appneck\Sdk\Storage;

/**
 * Where this installation's identity lives between requests.
 *
 * Two values, and they are a pair: the installation id the client
 * generated at enrolment, and the per-installation signing secret the
 * server issued in response (journal §9.2a). Losing one is losing both —
 * the secret is disclosed exactly once, at registration, and can never
 * be re-fetched, so an installation that loses it must enrol afresh
 * under a new id. That is precisely why both belong in the SAME stored
 * record: a partial restore that kept the id and dropped the secret
 * would leave an installation permanently unable to authenticate and
 * unable to recover.
 *
 * An interface rather than wp_options calls inline so the signing and
 * transport layers can be tested without WordPress, and so a plugin that
 * stores its settings somewhere unusual (a network option on multisite,
 * an encrypted store) can supply its own.
 */
interface CredentialStore {

	/** @return string|null */
	public function get_installation_id();

	/** @return string|null */
	public function get_installation_secret();

	/**
	 * Persists both halves together. Implementations must write them
	 * atomically enough that a reader never sees one without the other.
	 *
	 * @param string $installation_id
	 * @param string $installation_secret
	 * @return bool True if persisted.
	 */
	public function save( $installation_id, $installation_secret );

	/**
	 * Discards the stored pair. Used when the server tells us the
	 * installation is unknown and we must enrol again.
	 *
	 * @return bool
	 */
	public function forget();

	/** @return bool Whether a complete, usable pair is stored. */
	public function has_credentials();
}
