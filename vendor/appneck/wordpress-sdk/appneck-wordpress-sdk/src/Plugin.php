<?php

namespace Appneck\Sdk;

use Appneck\Sdk\Admin\AnnouncementNotices;
use Appneck\Sdk\Admin\ConsentNotice;
use Appneck\Sdk\Admin\DeactivationSurvey;
use Appneck\Sdk\Admin\LicenseForm;
use Appneck\Sdk\Admin\LicensePage;

/**
 * What Sdk::bootstrap() hands back: the one object a plugin author keeps
 * a reference to.
 *
 * A facade rather than making them wire Client + Lifecycle + Telemetry
 * themselves — those three exist as separate classes because they have
 * genuinely separate jobs and are separately testable, which is a reason
 * for the SDK's internals to be split, not a reason to make every plugin
 * author learn the split.
 *
 * The forwarding methods here are the entire public surface most plugins
 * will ever touch:
 *
 *     $sdk = \Appneck\Sdk\Sdk::bootstrap( 'pk_…', 'sk_…', 'https://…', __FILE__ );
 *     $sdk->track( 'booking_created', array( 'source' => 'checkout' ) );
 */
final class Plugin {

	/** @var Client */
	private $client;

	/** @var Lifecycle */
	private $lifecycle;

	/** @var Telemetry */
	private $telemetry;

	/** @var Consent|null */
	private $consent;

	/** @var ConsentNotice|null */
	private $consent_notice;

	/** @var Survey|null */
	private $survey;

	/** @var DeactivationSurvey|null */
	private $deactivation_survey;

	/** @var Announcements|null */
	private $announcements;

	/** @var AnnouncementNotices|null */
	private $announcement_notices;

	/** @var License|null */
	private $license;

	/** @var LicenseForm|null */
	private $license_form;

	/** @var string|null Phase 8: license_page()'s product_name fallback. */
	private $product_name;

	/** @var LicensePage|null Built lazily, on the first license_page() call. */
	private $license_page;

	public function __construct(
		Client $client,
		Lifecycle $lifecycle,
		Telemetry $telemetry,
		?Consent $consent = null,
		?ConsentNotice $consent_notice = null,
		?Survey $survey = null,
		?DeactivationSurvey $deactivation_survey = null,
		?Announcements $announcements = null,
		?AnnouncementNotices $announcement_notices = null,
		?License $license = null,
		?LicenseForm $license_form = null,
		$product_name = null
	) {
		$this->client               = $client;
		$this->lifecycle            = $lifecycle;
		$this->telemetry            = $telemetry;
		$this->consent              = $consent;
		$this->consent_notice       = $consent_notice;
		$this->survey               = $survey;
		$this->deactivation_survey  = $deactivation_survey;
		$this->announcements        = $announcements;
		$this->announcement_notices = $announcement_notices;
		$this->license              = $license;
		$this->license_form         = $license_form;
		$this->product_name         = null !== $product_name ? (string) $product_name : null;
	}

	/**
	 * Record an event. Local only — never an HTTP call, so this is safe
	 * to call from anywhere in a page load.
	 *
	 * @param string               $event_name
	 * @param array<string, mixed> $data
	 */
	public function track( $event_name, array $data = array() ) {
		return $this->telemetry->track( $event_name, $data );
	}

	/**
	 * @param string               $message
	 * @param array<string, mixed> $context
	 */
	public function track_error( $message, array $context = array() ) {
		return $this->telemetry->track_error( $message, $context );
	}

	/**
	 * Force a send now instead of waiting for the scheduled flush. Rarely
	 * needed; this one DOES make an HTTP call, so never call it from a
	 * page load a visitor is waiting on.
	 */
	public function flush() {
		return $this->telemetry->flush();
	}

	/** True once this site has registered and can sign requests. */
	public function is_registered() {
		return $this->client->credentials()->has_credentials();
	}

	public function client() {
		return $this->client;
	}

	public function lifecycle() {
		return $this->lifecycle;
	}

	public function telemetry() {
		return $this->telemetry;
	}

	/**
	 * The site owner's telemetry decision. Read it to gate your own
	 * optional features, or set the policy version:
	 *
	 *     if ( $sdk->consent()->is_accepted() ) { … }
	 *
	 * @return Consent|null Null only when built without the consent flow.
	 */
	public function consent() {
		return $this->consent;
	}

	/**
	 * The prompt. The notice renders itself; the one call a plugin makes by
	 * hand is the change-decision control on its own settings page:
	 *
	 *     $sdk->consent_notice()->render_settings_section();
	 *
	 * @return ConsentNotice|null
	 */
	public function consent_notice() {
		return $this->consent_notice;
	}

	/**
	 * The uninstall survey's client — questions, local validation and the
	 * one-shot submission. Rarely needed directly; the modal drives it.
	 *
	 * @return Survey|null
	 */
	public function survey() {
		return $this->survey;
	}

	/**
	 * The deactivation modal. Nothing to call for the normal case — it
	 * wires its own hooks — but this is where a plugin sets the name shown
	 * in the prompt if the SDK could not read it from the file header:
	 *
	 *     $sdk->deactivation_survey()->set_product_name( 'Acme Bookings' );
	 *
	 * @return DeactivationSurvey|null
	 */
	public function deactivation_survey() {
		return $this->deactivation_survey;
	}

	/**
	 * The announcement cache. Read it to render the messages yourself
	 * instead of using the built-in notices:
	 *
	 *     foreach ( $sdk->announcements()->visible() as $announcement ) { … }
	 *
	 * @return Announcements|null
	 */
	public function announcements() {
		return $this->announcements;
	}

	/**
	 * The notices, which print NOWHERE until you say where — an
	 * announcement from your product has no business on another plugin's
	 * screen:
	 *
	 *     $sdk->announcement_notices()->render_on_screen( 'settings_page_acme' );
	 *
	 * @return AnnouncementNotices|null
	 */
	public function announcement_notices() {
		return $this->announcement_notices;
	}

	/**
	 * The site's license. The one method most plugins will call:
	 *
	 *     if ( $sdk->license()->is_valid() ) { … premium feature … }
	 *
	 * is_valid() is safe on every page load — it answers from an
	 * autoloaded option and makes no network call unless its 24-hour
	 * cache has expired. See License's class doc for the full contract,
	 * including what happens when the license server is unreachable.
	 *
	 * @return License|null Null only when built without licensing.
	 */
	public function license() {
		return $this->license;
	}

	/**
	 * The license panel, which prints NOWHERE until the host plugin says
	 * where — a key input appearing unbidden on somebody else's settings
	 * screen is indistinguishable from a phishing field:
	 *
	 *     $sdk->license_form()->render();
	 *
	 * @return LicenseForm|null
	 */
	public function license_form() {
		return $this->license_form;
	}

	/**
	 * Phase 8: the complete, one-call license admin page — registers its
	 * own menu item, renders every state, and handles the Activate/
	 * Deactivate submission. Nothing to combine with your own settings
	 * page for; if you want that instead, use license_form() above.
	 *
	 *     $sdk->license_page( array(
	 *         'parent'       => 'options-general.php',
	 *         'purchase_url' => 'https://example.com/pricing',
	 *         'renew_url'    => 'https://example.com/account',
	 *         'support_url'  => 'https://example.com/support',
	 *         'notice'       => true,
	 *     ) );
	 *
	 * Safe to call more than once with different args — the underlying
	 * page is built once and re-registered; this is not a documented
	 * use case, just not something that fatals if it happens.
	 *
	 * @param array<string, mixed> $args See Admin\LicensePage::register().
	 * @return LicensePage|null Null only when built without licensing.
	 */
	public function license_page( array $args = array() ) {
		if ( null === $this->license ) {
			return null;
		}

		if ( null === $this->license_page ) {
			$options = array();

			if ( isset( $args['product_name'] ) ) {
				$options['product_name'] = $args['product_name'];
			} elseif ( null !== $this->product_name ) {
				$options['product_name'] = $this->product_name;
			}

			$this->license_page = new LicensePage( $this->license, $options );
		}

		return $this->license_page->register( $args );
	}
}
