<?php

namespace Appneck\Sdk;

/**
 * The one decision of whether this plugin may contact Appneck at all
 * (journal §70 D1).
 *
 * WordPress.org guideline 7: a free plugin may not contact an external
 * server without the site owner's explicit opt-in. So, for a FREE plugin,
 * nothing leaves the site unless the owner's telemetry consent is
 * `accepted`. `pending` (not answered yet) is a no, exactly like
 * `rejected`. A PREMIUM plugin is not distributed through WordPress.org,
 * shows no prompt, and may always contact Appneck.
 *
 * Every Client the SDK builds holds the same instance and refuses to call
 * its transport while may_contact() is false — see Client::send(). That is
 * the structural guarantee: a new code path cannot forget the check,
 * because the check lives where the request is sent. The two deliberate
 * exceptions, both started by a person's own click, go through
 * Client::post_exempt()/get_exempt(): the uninstall survey, and telling
 * the server that someone who had accepted has now withdrawn.
 *
 * Free is the default, so a pro build that forgets `is_premium` fails safe
 * — it asks for consent — rather than silently tracking free users.
 */
final class ContactGate {

	/** @var bool */
	private $premium;

	/** @var Consent|null */
	private $consent;

	/**
	 * @param bool         $premium Sdk::bootstrap()'s `is_premium` option.
	 * @param Consent|null $consent Null only for a gate built before Consent
	 *                              exists; such a free gate stays closed.
	 */
	public function __construct( $premium = false, ?Consent $consent = null ) {
		$this->premium = (bool) $premium;
		$this->consent = $consent;
	}

	public function set_consent( ?Consent $consent ) {
		$this->consent = $consent;
	}

	/** @return bool */
	public function is_premium() {
		return $this->premium;
	}

	/** `premium` or `free`, as registration reports it. */
	public function edition() {
		return $this->premium ? 'premium' : 'free';
	}

	/**
	 * Whether anything may be collected or sent to Appneck right now.
	 *
	 * @return bool
	 */
	public function may_contact() {
		if ( $this->premium ) {
			return true;
		}

		return null !== $this->consent && $this->consent->is_accepted();
	}
}
