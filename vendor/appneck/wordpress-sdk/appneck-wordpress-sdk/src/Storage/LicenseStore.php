<?php

namespace Appneck\Sdk\Storage;

/**
 * Where this site's license key and its last validation result live
 * between requests.
 *
 * ## One record, not several
 *
 * The same rule CredentialStore states for the id/secret pair, for the
 * same reason: a key without the result it was last validated against —
 * or a result without the key it describes — is a fragment, and separate
 * writes can be half-restored from a backup or half-deleted. The whole
 * state moves together or not at all, so this interface deliberately has
 * no per-field accessors. \Appneck\Sdk\License owns the shape of the
 * array; this interface owns only its durability.
 *
 * ## Deliberately NOT a transient
 *
 * The obvious WordPress idiom for "a cached answer with a TTL" is
 * set_transient(), and it is the wrong tool here. A transient backed by
 * a persistent object cache can be evicted at any moment under memory
 * pressure, and eviction is indistinguishable from expiry. On a
 * fail-open product that costs an unnecessary network call on a page
 * load; on a fail-closed one it locks a paying customer out of features
 * they have already paid for, at a moment nobody can correlate with
 * anything. An option row is durable — it is either there or it was
 * deleted on purpose — so the TTL is arithmetic this SDK does itself
 * against a stored timestamp, never something the storage layer is
 * trusted to enforce.
 *
 * An interface rather than get_option() calls inline so the caching,
 * fail-open and backoff logic can be tested without WordPress, and so a
 * plugin storing its settings somewhere unusual (a network option on
 * multisite, an encrypted store) can supply its own.
 */
interface LicenseStore {

	/**
	 * The stored state, or an empty array when there is none.
	 *
	 * Implementations must return an array even for a corrupted or
	 * hand-edited value: this is read on every page load through
	 * License::is_valid(), and a non-array escaping into that path is a
	 * fatal error on somebody else's website.
	 *
	 * @return array<string, mixed>
	 */
	public function read();

	/**
	 * Replaces the stored state wholesale.
	 *
	 * @param array<string, mixed> $state
	 * @return bool True if persisted.
	 */
	public function write( array $state );

	/**
	 * Discards the stored state entirely, key included. Used on a
	 * successful deactivation and at uninstall.
	 *
	 * @return bool
	 */
	public function forget();
}
