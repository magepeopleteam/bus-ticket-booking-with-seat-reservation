<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Http\Response;
use Appneck\Sdk\Logging\Logger;
use Appneck\Sdk\Logging\NullLogger;
use Appneck\Sdk\Storage\LicenseStore;

/**
 * The site's license: activating it, releasing it, and answering
 * "is this site licensed?" on every page load without punishing anybody
 * for a network that is having a bad day.
 *
 * ## is_valid() is the whole design
 *
 * Everything else here exists to make one method safe to call from a
 * plugin's own hot path. A plugin author will write
 *
 *     if ( $sdk->license()->is_valid() ) { … premium feature … }
 *
 * at the top of a template, in an admin screen, and inside a loop, and
 * they will be right to. So is_valid() must (a) never throw, (b) cost
 * one autoloaded option read in the overwhelming majority of calls, and
 * (c) never make a network request the site is waiting on unless the
 * cache has genuinely expired AND the server is not already known to be
 * unreachable.
 *
 * Its order is exact, and each step exists to prevent a specific
 * failure:
 *
 *   1. No key stored  →  false, zero HTTP. An unlicensed site must not
 *                        generate licensing traffic. This is most sites
 *                        that ever install a freemium plugin.
 *   2. Cache fresh    →  the cached flag, zero HTTP. The hot path.
 *   3. In backoff     →  the fail-mode answer, zero HTTP. See below.
 *   4. Otherwise      →  one validate call, then answer from what it
 *                        learned.
 *
 * ## Fail-open, and what it is NOT
 *
 * The default (configurable — 'license_fail_mode' => 'closed') is that
 * an UNREACHABLE server does not cost a paying customer their features.
 * That is a narrower promise than it first sounds, and the narrowness is
 * the point:
 *
 *   - Fail-open returns the LAST DEFINITIVE ANSWER, never a blanket
 *     true. If the last thing the server said was "invalid", an outage
 *     must not upgrade that to valid. "Your outage doesn't punish a
 *     valid customer" is the promise; "unreachable equals licensed" is
 *     not, and would make the whole system defeatable by unplugging a
 *     network cable.
 *   - A key that has NEVER once validated successfully returns false.
 *     An unverified key is not a paying customer, and there is no last
 *     known good answer to fall back to.
 *
 * A definitive answer is always honoured, including a negative one. Only
 * an ok() response — a 2xx whose JSON parsed — is definitive. A 5xx, a
 * 429, a 401 from a rotated product secret, and an HTML error page from
 * a WAF are all treated as "no answer", not as "invalid": none of them
 * is the licensing server saying anything about this license, and
 * reading them as a rejection would let a deploy blip revoke every
 * customer in the field at once.
 *
 * ## The backoff is the most dangerous thing in this file
 *
 * If the server is unreachable and the SDK retried on the next page
 * load, then every page of a site whose license server is down would
 * block for the transport timeout. That alone would be enough to make
 * the host's site unusable. Worse, every affected site retrying on every
 * page load turns an outage into a self-inflicted flood aimed at the
 * recovering server.
 *
 * So a transport failure records a widening backoff — 5m, 15m, 1h, 6h,
 * 24h, and never more than 24h even after jitter — during which
 * is_valid() answers from the fail mode with zero network calls. The
 * jitter is ±20% and is rolled ONCE, at failure time, and stored as an
 * absolute next_attempt_at: re-rolling it on every read would mean the
 * effective wait was random per page load rather than per failure, which
 * is not a spread, it is noise. Thousands of sites that all lost contact
 * in the same minute must not all come back in the same second.
 *
 * Any successful call resets the counter to zero.
 *
 * ## What this class deliberately does not do
 *
 * It never touches CredentialStore and never requires an installation.
 * Journal §23.1: analytics consent is fail-closed, so a customer who
 * declined telemetry has no installation row at all, and their license
 * must work anyway. Licensing and telemetry share a product secret and
 * nothing else.
 */
final class License {

	/** An unreachable server returns the last definitive answer. Default. */
	const FAIL_OPEN = 'open';

	/** An unreachable server returns false. */
	const FAIL_CLOSED = 'closed';

	/** Seconds. Journal §22's client-side cache window. */
	const DEFAULT_CACHE_TTL = 86400;

	/**
	 * Seconds to wait after the 1st, 2nd, … consecutive failure. The last
	 * entry repeats forever.
	 */
	const BACKOFF = array( 300, 900, 3600, 21600, 86400 );

	/** Seconds. A hard ceiling the jitter may not push through. */
	const MAX_BACKOFF = 86400;

	/** Percent, applied either side of the backoff base. */
	const JITTER_PERCENT = 20;

	const PATH_ACTIVATE   = '/sdk/v1/licenses/activate';
	const PATH_VALIDATE   = '/sdk/v1/licenses/validate';
	const PATH_DEACTIVATE = '/sdk/v1/licenses/deactivate';

	/** @var LicenseClient */
	private $client;

	/** @var LicenseStore */
	private $store;

	/** @var Logger */
	private $logger;

	/** @var string FAIL_OPEN|FAIL_CLOSED */
	private $fail_mode = self::FAIL_OPEN;

	/** @var int */
	private $cache_ttl = self::DEFAULT_CACHE_TTL;

	/** @var string|null Set explicitly, bypassing home_url(). */
	private $domain = null;

	/** @var string|null Set by Admin\LicensePage::register(). */
	private $page_url = null;

	/** @var string Per-product suffix, shared with the admin form. */
	private $key;

	/**
	 * @param array<string, mixed> $options license_fail_mode,
	 *                                      license_cache_ttl,
	 *                                      license_domain.
	 */
	public function __construct(
		LicenseClient $client,
		LicenseStore $store,
		?Logger $logger = null,
		array $options = array()
	) {
		$this->client = $client;
		$this->store  = $store;
		$this->logger = null !== $logger ? $logger : new NullLogger();
		$this->key    = substr( hash( 'sha256', $client->config()->storage_identity() ), 0, 32 );

		// Anything that is not exactly 'closed' is open. A typo in a
		// plugin's bootstrap must not silently lock a site's own
		// customers out of what they paid for.
		if ( isset( $options['license_fail_mode'] ) && self::FAIL_CLOSED === $options['license_fail_mode'] ) {
			$this->fail_mode = self::FAIL_CLOSED;
		}

		if ( isset( $options['license_cache_ttl'] ) && is_numeric( $options['license_cache_ttl'] ) ) {
			$ttl = (int) $options['license_cache_ttl'];

			if ( $ttl > 0 ) {
				$this->cache_ttl = $ttl;
			}
		}

		if ( isset( $options['license_domain'] ) ) {
			$this->set_domain( $options['license_domain'] );
		}
	}

	/** The per-product option/action suffix, shared with Admin\LicenseForm. */
	public function key() {
		return $this->key;
	}

	/** @return string FAIL_OPEN|FAIL_CLOSED */
	public function fail_mode() {
		return $this->fail_mode;
	}

	/** @return int Seconds. */
	public function cache_ttl() {
		return $this->cache_ttl;
	}

	// -----------------------------------------------------------------
	// The hot path
	// -----------------------------------------------------------------

	/**
	 * Is this site licensed right now?
	 *
	 * Safe to call on every page load and from anywhere. Never throws,
	 * and makes at most one HTTP request — see the class doc for the
	 * exact order and why each step is there.
	 *
	 * @return bool
	 */
	public function is_valid() {
		try {
			return $this->determine_validity();
		} catch ( \Throwable $e ) {
			// Unreachable in practice: LicenseClient already converts
			// every Throwable into a Response, and the stores guard
			// every WordPress function they call. This is the last
			// barrier between an SDK bug and a white screen on somebody
			// else's site.
			//
			// false rather than the fail-mode answer, deliberately: an
			// exception escaping those layers means this SDK is broken,
			// not that the network is, and granting a license on the
			// strength of a bug is the one outcome worse than a customer
			// having to click Activate again.
			$this->log_internal_failure( $e );

			return false;
		} catch ( \Exception $e ) {
			$this->log_internal_failure( $e );

			return false;
		}
	}

	/** @return bool */
	private function determine_validity() {
		$state = $this->state();

		// 1. Nothing stored. No network call, ever — the majority of
		//    sites that install a freemium plugin never enter a key, and
		//    they must not generate licensing traffic.
		if ( '' === $state['license_key'] ) {
			return false;
		}

		// 2. The hot path.
		if ( $this->cache_is_fresh( $state ) ) {
			return $this->last_known_valid( $state );
		}

		// 3. The cache is stale but the server is already known to be
		//    unreachable. Answer immediately; see the class doc.
		if ( $this->in_backoff( $state ) ) {
			return $this->fail_mode_answer( $state );
		}

		// 4. One attempt. validate() has already written whatever it
		//    learned, so re-reading is how the outcome is applied — a
		//    fresh cache means it got a definitive answer (positive or
		//    negative) and that answer stands.
		$this->validate( $state['license_key'] );

		$state = $this->state();

		if ( $this->cache_is_fresh( $state ) ) {
			return $this->last_known_valid( $state );
		}

		return $this->fail_mode_answer( $state );
	}

	/**
	 * Everything an admin screen needs to render the license, computed
	 * WITHOUT a network call — this is a render path, and a settings page
	 * that blocks on a licensing round trip is the thing the cache exists
	 * to prevent.
	 *
	 * `valid` is the answer is_valid() would give right now from cache
	 * and fail mode alone, so the UI can never disagree with the gate.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status() {
		try {
			return $this->build_status();
		} catch ( \Throwable $e ) {
			$this->log_internal_failure( $e );

			return $this->blank_status();
		} catch ( \Exception $e ) {
			$this->log_internal_failure( $e );

			return $this->blank_status();
		}
	}

	/** @return array<string, mixed> */
	private function build_status() {
		$state = $this->state();

		if ( '' === $state['license_key'] ) {
			return $this->blank_status();
		}

		$fresh           = $this->cache_is_fresh( $state );
		$domain_mismatch = null !== $state['validated_domain']
			&& $state['validated_domain'] !== $this->domain();

		return array(
			'has_license'      => true,
			// Masked to the last 4 characters. The raw key is stored (it
			// has to be — re-validation needs it) but there is no reason
			// to paint it back onto a screen somebody may be sharing.
			'license_key'      => $this->mask( $state['license_key'] ),
			'status'           => $state['status'],
			'reason'           => $state['reason'],
			'customer_name'    => $state['customer_name'],
			'expires_at'       => $state['expires_at'],
			'activation_limit' => $state['activation_limit'],
			'activations_used' => $state['activations_used'],
			'valid'            => $fresh ? $this->last_known_valid( $state ) : $this->fail_mode_answer( $state ),
			'domain_mismatch'  => $domain_mismatch,
			'last_checked_at'  => $state['validated_at'] > 0 ? $state['validated_at'] : null,
			// "The cache has expired and we could not reach the server."
			// Both halves are required: a merely-expired cache on a
			// healthy site is about to refresh itself and is not worth
			// warning anybody about.
			'stale'            => ! $fresh
				&& $state['failure_domain'] === $this->domain()
				&& $state['failure_count'] > 0,
			'failure_count'    => $state['failure_count'],
			'next_attempt_at'  => $state['next_attempt_at'] > 0 ? $state['next_attempt_at'] : null,
			'fail_mode'        => $this->fail_mode,
		);
	}

	/** @return array<string, mixed> */
	private function blank_status() {
		return array(
			'has_license'      => false,
			'license_key'      => '',
			'status'           => null,
			'reason'           => null,
			'customer_name'    => null,
			'expires_at'       => null,
			'activation_limit' => null,
			'activations_used' => null,
			'valid'            => false,
			'domain_mismatch'  => false,
			'last_checked_at'  => null,
			'stale'            => false,
			'failure_count'    => 0,
			'next_attempt_at'  => null,
			'fail_mode'        => $this->fail_mode,
		);
	}

	/**
	 * Set once, by Admin\LicensePage::register(), so require_valid()
	 * below can link to wherever the host plugin actually put the
	 * license screen — this class has no way to know that on its own,
	 * and does not guess at a URL.
	 *
	 * @param string $url
	 */
	public function set_page_url( $url ) {
		$this->page_url = (string) $url;

		return $this;
	}

	/** @return string|null Null until a LicensePage has registered. */
	public function page_url() {
		return $this->page_url;
	}

	/**
	 * The gate a pro feature's own admin screen calls first:
	 *
	 *     if ( ! $sdk->license()->require_valid() ) { return; }
	 *
	 * Prints a styled notice and returns false when the license is not
	 * valid; prints nothing and returns true otherwise. Never exits or
	 * dies — the one thing this method must not decide is whether the
	 * calling page still renders something after it, because a host
	 * plugin's page may have its own reasons to keep going (a read-only
	 * preview of the pro feature, for instance).
	 *
	 * This is the one method on License that prints anything — everywhere
	 * else in this class is deliberately presentation-free. It earns the
	 * exception because the public API asked for it directly on
	 * `$sdk->license()`, not on a rendering helper the caller would have
	 * to know to reach for instead; the same function_exists guards every
	 * other WordPress call in this file already uses keep it safe to call
	 * from this package's own non-WordPress tests.
	 *
	 * @param string|null $message Overrides the default sentence.
	 * @return bool
	 */
	public function require_valid( $message = null ) {
		if ( $this->is_valid() ) {
			return true;
		}

		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'esc_url' ) ) {
			return false;
		}

		$status = $this->get_status();

		$text = null !== $message
			? (string) $message
			: 'This feature requires an active license. '
				. \Appneck\Sdk\Admin\LicenseMessages::for_reason(
					$status['reason'],
					empty( $status['has_license'] ) ? 'Enter a license key to unlock this feature.' : 'This license is not currently active.'
				);

		echo '<div class="notice notice-warning"><p>' . esc_html( $text );

		if ( null !== $this->page_url && '' !== $this->page_url ) {
			echo ' <a href="' . esc_url( $this->page_url ) . '">' . esc_html( 'Manage your license' ) . '</a>';
		}

		echo '</p></div>';

		return false;
	}

	// -----------------------------------------------------------------
	// The three calls
	// -----------------------------------------------------------------

	/**
	 * POST /sdk/v1/licenses/activate — a human clicked Activate.
	 *
	 * No caching and no backoff: this is not a page load, it is a person
	 * waiting for an answer they asked for, and making them wait out a
	 * backoff window because a cron tick failed earlier would be
	 * incomprehensible.
	 *
	 * The key is stored ONLY on success. A rejected key that was saved
	 * anyway would leave the site re-validating a key the server has
	 * already refused, forever.
	 *
	 * @param string $license_key
	 * @return Response
	 */
	public function activate( $license_key ) {
		$license_key = (string) $license_key;

		$response = $this->client->post(
			self::PATH_ACTIVATE,
			array(
				'license_key' => $license_key,
				'domain'      => $this->domain(),
			)
		);

		$data = $response->data();

		if ( $response->ok() && ! empty( $data['valid'] ) ) {
			// From blank, not from the existing state: activating a NEW
			// key must not inherit the previous key's cached result,
			// expiry or activation counts.
			$this->record_result( $this->blank_state(), $response, $license_key );
		}

		return $response;
	}

	/**
	 * POST /sdk/v1/licenses/deactivate — a human clicked Deactivate.
	 *
	 * Local state is cleared ONLY on a definitive success. On a transport
	 * failure it is deliberately kept: the server still counts this
	 * domain as holding a slot, and forgetting the key locally would
	 * strand that slot with the customer holding nothing to release it
	 * with. Returning the failure lets the UI say "try again", which is
	 * the only correct instruction.
	 *
	 * @param string $license_key
	 * @return Response
	 */
	public function deactivate( $license_key ) {
		$response = $this->client->post(
			self::PATH_DEACTIVATE,
			array(
				'license_key' => (string) $license_key,
				'domain'      => $this->domain(),
			)
		);

		if ( $response->ok() ) {
			$this->forget();
		}

		return $response;
	}

	/**
	 * POST /sdk/v1/licenses/validate — the call is_valid() makes when its
	 * cache has expired, and the one a plugin can make by hand to force a
	 * re-check.
	 *
	 * The short-timeout transport, because the usual caller is a page
	 * load: a slow license server must not become somebody's slow site.
	 *
	 * The result is written to the cache only when $license_key is the
	 * key this site actually holds. Validating some OTHER key — a form
	 * preview, a support tool — is a bare query and must not overwrite
	 * the stored license's state or its backoff.
	 *
	 * @param string $license_key
	 * @return Response
	 */
	public function validate( $license_key ) {
		$license_key = (string) $license_key;

		$response = $this->client->post_fast(
			self::PATH_VALIDATE,
			array(
				'license_key' => $license_key,
				'domain'      => $this->domain(),
			)
		);

		$state = $this->state();

		if ( '' === $license_key || $license_key !== $state['license_key'] ) {
			return $response;
		}

		if ( $response->ok() ) {
			$this->record_result( $state, $response, $license_key );
		} else {
			$this->record_failure( $state );
		}

		return $response;
	}

	/**
	 * deactivate(), using the key this site already holds.
	 *
	 * The admin panel's Deactivate button calls this rather than passing
	 * a key back through the form: the panel renders only a masked key,
	 * so there is nothing legitimate for a POST to carry, and this class
	 * deliberately exposes no getter for the raw one. The key is storage,
	 * not public API.
	 *
	 * @return Response|null Null when no license is stored.
	 */
	public function deactivate_stored() {
		$state = $this->state();

		if ( '' === $state['license_key'] ) {
			return null;
		}

		return $this->deactivate( $state['license_key'] );
	}

	/**
	 * Best-effort release at uninstall, called by Sdk::uninstall().
	 *
	 * Short timeout and no retry — an uninstall must never block or fail
	 * on a licensing call — and the local row goes regardless of the
	 * outcome, which is the one place this class does NOT follow
	 * deactivate()'s keep-state-on-failure rule. Deliberately: the plugin
	 * and every option it owns are being removed, so there is no local
	 * state left for a retry to belong to. The slot is recoverable from
	 * the customer's own account page; a stranded option row on a site
	 * that no longer has the plugin is not recoverable by anyone.
	 *
	 * @return Response|null Null when no license was stored.
	 */
	public function on_uninstall() {
		$state = $this->state();

		$response = null;

		if ( '' !== $state['license_key'] ) {
			$response = $this->client->post_fast(
				self::PATH_DEACTIVATE,
				array(
					'license_key' => $state['license_key'],
					'domain'      => $this->domain(),
				)
			);
		}

		$this->forget();

		return $response;
	}

	/** Discards the stored key and cached result. */
	public function forget() {
		return $this->store->forget();
	}

	// -----------------------------------------------------------------
	// Reading the stored state
	// -----------------------------------------------------------------

	/** @return bool Whether a key is stored at all. */
	public function has_license() {
		$state = $this->state();

		return '' !== $state['license_key'];
	}

	/**
	 * Unix timestamp of the last successful validation, or 0.
	 *
	 * @return int
	 */
	public function last_checked_at() {
		$state = $this->state();

		return $state['validated_at'];
	}

	/**
	 * Consecutive failed attempts. Public because "why isn't it
	 * retrying?" is the question this SDK is hardest to answer, and a
	 * plugin author debugging it needs to be able to see the counter
	 * rather than guess at it.
	 *
	 * @return int
	 */
	public function failure_count() {
		$state = $this->state();

		return $state['failure_count'];
	}

	/**
	 * Unix timestamp before which is_valid() will make no network call at
	 * all, or 0 when there is no backoff in force. The other half of the
	 * answer to "why isn't it retrying?".
	 *
	 * @return int
	 */
	public function next_attempt_at() {
		$state = $this->state();

		return $state['next_attempt_at'];
	}

	// -----------------------------------------------------------------
	// The domain
	// -----------------------------------------------------------------

	/**
	 * The normalized domain this site activates under.
	 *
	 * @return string
	 */
	public function domain() {
		if ( null !== $this->domain ) {
			return $this->domain;
		}

		$home = function_exists( 'home_url' ) ? home_url() : '';

		return self::normalize_domain( $home );
	}

	/**
	 * Overrides home_url(). For this package's own tests, for WP-CLI
	 * harnesses, and for a site whose licensed identity is not its
	 * home_url (a staging clone that must not consume the production
	 * slot).
	 *
	 * @param string|null $domain
	 */
	public function set_domain( $domain ) {
		$this->domain = null === $domain ? null : self::normalize_domain( (string) $domain );

		return $this;
	}

	/**
	 * Byte-for-byte the server's own rule —
	 * App\Models\LicenseActivation::normalizeDomain(), journal §22.8.
	 *
	 * This MUST match, not approximate. The server enforces "one domain
	 * cannot hold two active activations" with a Postgres partial unique
	 * index on the normalized string. If this SDK normalizes differently
	 * by so much as a trailing slash, one site sends two spellings of
	 * itself over its lifetime, the index sees two different domains, and
	 * one customer silently burns two activation slots on one install.
	 *
	 * The rule, in the server's own order:
	 *
	 *   1. trim
	 *   2. strip a leading scheme (any RFC-3986 scheme, case-insensitive)
	 *   3. keep everything before the first "/" — which is what removes
	 *      the path AND the trailing slash in one step
	 *   4. lowercase, and trim again
	 *
	 * `www.` is deliberately NOT stripped, and neither is a port. Journal
	 * §22.8 is explicit about www: it is a different host, and merging it
	 * would collapse a real multi-site setup into one slot. The prompt
	 * for this SDK described the normalization as stripping www; the
	 * server does not, and the server is what the index is built on, so
	 * this follows the server.
	 *
	 * mb_strtolower where available, strtolower otherwise: mbstring is
	 * not guaranteed on every WordPress host, and the two differ only for
	 * non-ASCII hosts, which home_url() returns punycode-encoded.
	 *
	 * @param string $domain A URL, a host, or anything in between.
	 * @return string
	 */
	public static function normalize_domain( $domain ) {
		$domain = trim( (string) $domain );

		$stripped = preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', $domain );

		if ( null !== $stripped ) {
			$domain = $stripped;
		}

		$parts  = explode( '/', $domain, 2 );
		$domain = $parts[0];

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( trim( $domain ) );
		}

		return strtolower( trim( $domain ) );
	}

	// -----------------------------------------------------------------
	// Cache, fail mode and backoff
	// -----------------------------------------------------------------

	/**
	 * @param array<string, mixed> $state
	 * @return bool
	 */
	private function cache_is_fresh( array $state ) {
		// A definitive answer belongs to the domain that was validated.
		// A database/search-replace migration can leave the product-scoped
		// option intact while home_url() changes; that answer is not a cache
		// hit on the new site.
		if ( $state['validated_domain'] !== $this->domain() ) {
			return false;
		}

		if ( $state['validated_at'] <= 0 ) {
			return false;
		}

		$age = time() - $state['validated_at'];

		// A negative age means the stored timestamp is in the future —
		// a clock that was wrong when the result was written, or a host
		// that moved backwards. Read as stale rather than as fresh: the
		// wrong direction here would pin a cached answer in place until
		// the clock caught up, which could be indefinitely.
		return $age >= 0 && $age < $this->cache_ttl;
	}

	/**
	 * @param array<string, mixed> $state
	 * @return bool
	 */
	private function in_backoff( array $state ) {
		return $state['failure_domain'] === $this->domain()
			&& $state['next_attempt_at'] > 0
			&& time() < $state['next_attempt_at'];
	}

	/**
	 * The last DEFINITIVE answer. Null (never validated) reads as false.
	 *
	 * @param array<string, mixed> $state
	 * @return bool
	 */
	private function last_known_valid( array $state ) {
		return $state['validated_domain'] === $this->domain() && true === $state['valid'];
	}

	/**
	 * @param array<string, mixed> $state
	 * @return bool
	 */
	private function fail_mode_answer( array $state ) {
		if ( self::FAIL_CLOSED === $this->fail_mode ) {
			return false;
		}

		return $this->last_known_valid( $state );
	}

	/**
	 * @param array<string, mixed> $state
	 * @param string               $license_key
	 */
	private function record_result( array $state, Response $response, $license_key ) {
		$data = $response->data();

		$state['license_key']      = $license_key;
		$state['valid']            = ! empty( $data['valid'] );
		$state['validated_domain'] = $this->domain();

		// Absent fields keep their previous value rather than being
		// blanked. /validate's success shape carries status and
		// expires_at but NOT the activation counts, and its rejection
		// shape carries only a reason — so a validate that followed an
		// activate would otherwise erase the "3 of 5 activations used"
		// the customer is looking at, on a call that said nothing about
		// it either way.
		$state['status']           = $this->scalar_or( $data, 'status', $state['status'] );
		$state['expires_at']       = $this->scalar_or( $data, 'expires_at', $state['expires_at'] );
		$state['customer_name']    = $this->scalar_or( $data, 'customer_name', $state['customer_name'] );
		$state['activation_limit'] = $this->int_or( $data, 'activation_limit', $state['activation_limit'] );
		$state['activations_used'] = $this->int_or( $data, 'activations_used', $state['activations_used'] );

		// The rejection reason, and only from a rejection: keeping a
		// stale "expired" alongside a valid:true result would be read by
		// any UI as a contradiction.
		$state['reason'] = $state['valid'] ? null : $this->scalar_or( $data, 'reason', null );

		// /activate answers valid:true only for a license that is active
		// and unexpired (App\Actions\Licensing\ActivateLicenseFromSdk),
		// but its success shape carries no status field. Inferring it
		// here is what stops a freshly activated license rendering with
		// a blank status until its first validate.
		if ( $state['valid'] && null === $state['status'] ) {
			$state['status'] = 'active';
		}

		$state['validated_at'] = time();

		// Any definitive answer clears the backoff — the server is
		// plainly reachable.
		$state['failure_count']   = 0;
		$state['failed_at']       = 0;
		$state['next_attempt_at'] = 0;
		$state['failure_domain']  = null;

		$this->store->write( $state );
	}

	/**
	 * A failed attempt: count it, and set the absolute time before which
	 * no further attempt will be made.
	 *
	 * validated_at is deliberately untouched. The cached answer is not
	 * refreshed by a failure and must keep aging, so that a server that
	 * comes back after two days does not find a cache that a string of
	 * failures had quietly kept alive.
	 *
	 * @param array<string, mixed> $state
	 */
	private function record_failure( array $state ) {
		$count = $state['failure_count'] + 1;

		$state['failure_count']   = $count;
		$state['failed_at']       = time();
		$state['next_attempt_at'] = time() + $this->backoff_delay( $count );
		$state['failure_domain']  = $this->domain();

		$this->store->write( $state );
	}

	/**
	 * Seconds to wait after $failure_count consecutive failures, with
	 * ±20% jitter and a hard 24-hour ceiling.
	 *
	 * The ceiling is applied AFTER the jitter, which is why the top of
	 * the sequence is effectively one-sided: 86400 + 20% would be 28.8
	 * hours, and "never exceed 24 hours" is the requirement.
	 *
	 * mt_rand rather than random_int: random_int can throw when the
	 * platform has no usable entropy source, and nothing in a backoff
	 * calculation is worth an exception on a stranger's site. The jitter
	 * is a load-spreading measure, not a security one.
	 *
	 * @param int $failure_count 1-based.
	 * @return int Seconds, at least 1.
	 */
	private function backoff_delay( $failure_count ) {
		$count = (int) $failure_count;

		if ( $count < 1 ) {
			$count = 1;
		}

		$index = min( $count, count( self::BACKOFF ) ) - 1;
		$base  = self::BACKOFF[ $index ];

		$spread = (int) round( $base * self::JITTER_PERCENT / 100 );
		$delay  = $base + mt_rand( -$spread, $spread );

		if ( $delay > self::MAX_BACKOFF ) {
			$delay = self::MAX_BACKOFF;
		}

		return $delay < 1 ? 1 : $delay;
	}

	// -----------------------------------------------------------------
	// State
	// -----------------------------------------------------------------

	/**
	 * The stored state, normalized to the full shape with every field
	 * present and correctly typed.
	 *
	 * Every read goes through here so nothing downstream has to guard
	 * against a missing key, a hand-edited option, or a value written by
	 * an older version of this SDK — which, given this code ships inside
	 * sites that will run it for years, is not a hypothetical.
	 *
	 * @return array<string, mixed>
	 */
	private function state() {
		$stored = $this->store->read();

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$state = $this->blank_state();

		$state['license_key'] = isset( $stored['license_key'] ) && is_scalar( $stored['license_key'] )
			? (string) $stored['license_key']
			: '';

		// Three-valued on purpose: null means "never had a definitive
		// answer", which fail-open must distinguish from "the last
		// answer was no".
		if ( isset( $stored['valid'] ) ) {
			$state['valid'] = (bool) $stored['valid'];
		}

		$state['status']           = $this->scalar_or( $stored, 'status', null );
		$state['reason']           = $this->scalar_or( $stored, 'reason', null );
		$state['validated_domain'] = $this->scalar_or( $stored, 'validated_domain', null );
		$state['failure_domain']   = $this->scalar_or( $stored, 'failure_domain', null );
		$state['customer_name']    = $this->scalar_or( $stored, 'customer_name', null );
		$state['expires_at']       = $this->scalar_or( $stored, 'expires_at', null );
		$state['activation_limit'] = $this->int_or( $stored, 'activation_limit', null );
		$state['activations_used'] = $this->int_or( $stored, 'activations_used', null );

		$state['validated_at']    = $this->int_or( $stored, 'validated_at', 0 );
		$state['failed_at']       = $this->int_or( $stored, 'failed_at', 0 );
		$state['failure_count']   = $this->int_or( $stored, 'failure_count', 0 );
		$state['next_attempt_at'] = $this->int_or( $stored, 'next_attempt_at', 0 );

		return $state;
	}

	/** @return array<string, mixed> */
	private function blank_state() {
		return array(
			'license_key'      => '',
			'valid'            => null,
			'status'           => null,
			'reason'           => null,
			'validated_domain' => null,
			'failure_domain'   => null,
			'customer_name'    => null,
			'expires_at'       => null,
			'activation_limit' => null,
			'activations_used' => null,
			'validated_at'     => 0,
			'failed_at'        => 0,
			'failure_count'    => 0,
			'next_attempt_at'  => 0,
		);
	}

	/**
	 * @param array<string, mixed> $source
	 * @param string               $field
	 * @param string|null          $fallback Used when the field is absent.
	 * @return string|null
	 */
	private function scalar_or( array $source, $field, $fallback ) {
		if ( ! array_key_exists( $field, $source ) || null === $source[ $field ] ) {
			return $fallback;
		}

		return is_scalar( $source[ $field ] ) ? (string) $source[ $field ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $source
	 * @param string               $field
	 * @param int|null             $fallback Used when the field is absent.
	 * @return int|null
	 */
	private function int_or( array $source, $field, $fallback ) {
		if ( ! array_key_exists( $field, $source ) || ! is_numeric( $source[ $field ] ) ) {
			return $fallback;
		}

		return (int) $source[ $field ];
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function mask( $key ) {
		if ( '' === $key ) {
			return '';
		}

		return '****' . substr( $key, -4 );
	}

	/** @param \Throwable|\Exception $e */
	private function log_internal_failure( $e ) {
		$this->logger->error(
			'The licensing SDK failed internally; the site is treated as unlicensed.',
			array( 'error' => get_class( $e ) . ': ' . $e->getMessage() )
		);
	}
}
