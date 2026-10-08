<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\Consent;
use Appneck\Sdk\ContactGate;
use Appneck\Sdk\MarketingConsent;

/**
 * The site-owner-facing opt-in (journal §70 D2): an admin notice asking
 * the question, and a settings section for changing either answer later.
 *
 * ## One question, two consents
 *
 * "Allow & Continue" is telemetry consent (`accepted`) AND the update-email
 * opt-in, recorded together in one /sdk/v1/consent call. "Skip" refuses
 * both and sends nothing at all. The settings section then splits them into
 * two independent switches — "Share usage data" and "Receive update emails"
 * — each of which records its own consent event, so the bundled opt-in can
 * be withdrawn one half at a time.
 *
 * The "What's shared?" list is the contract with the site owner, and
 * SHARED_FIELDS maps every line of it to the payload keys that line
 * covers. A test compares that map against what registration, telemetry
 * and consent actually send, so the list cannot drift from the code.
 *
 * ## Free plugins only
 *
 * A premium build (ContactGate::is_premium()) renders neither the notice
 * nor the settings section: it shows no prompt (journal §70 D5).
 *
 * ## Why an admin notice, and not a settings page of our own
 *
 * An embedded library must not add a top-level menu item to somebody
 * else's plugin — the site owner would see an "Appneck" menu they never
 * installed, and two plugins bundling this SDK would add two. An admin
 * notice is the WordPress-idiomatic way to ask the owner a question.
 *
 * The notice is NOT dismissible, and shows until the question is answered.
 * A dismiss button is a third answer meaning neither yes nor no. Allow and
 * Skip are the only exits; either one hides the notice for good.
 *
 * ## Changing the decision later
 *
 * render_settings_section() is a fragment the host plugin echoes inside
 * its OWN settings page, which is where a site owner looks for a setting:
 *
 *     $sdk->consent_notice()->render_settings_section();
 *
 * ## Everything posts, and everything is per-product
 *
 * Every control is a form submit to admin-post.php, never a link: a GET
 * that changes a stored decision is triggerable by a prefetching browser
 * or an <img> on another site. The action name carries the product key
 * hash because several plugins on one site may each bundle this SDK, and a
 * shared action would mean one plugin's click answered for every other.
 */
final class ConsentNotice {

	const ACTION_PREFIX = 'appneck_sdk_consent_';

	/** The prompt's buttons: `accepted` (Allow & Continue) or `rejected` (Skip). */
	const FIELD = 'appneck_sdk_consent_decision';

	/** The settings switches: usage_on, usage_off, emails_on, emails_off. */
	const SETTING_FIELD = 'appneck_sdk_consent_setting';

	const TITLE = 'Never miss an important update';

	/**
	 * Every line of the "What's shared?" list, and the request payload keys
	 * it discloses (journal §70 D2). `%s` is the plugin's name. Keys are
	 * dotted for nested fields (`environment.plugins` is the heartbeat's
	 * plugin inventory). Pinned against the real payloads by
	 * DisclosureTest — add a line here before sending anything new.
	 *
	 * @var array<string, array<int, string>>
	 */
	const SHARED_FIELDS = array(
		'Your name and email (for update emails only)'       => array( 'marketing_name', 'marketing_email' ),
		'Site URL'                                           => array( 'site_domain' ),
		'WordPress, PHP, WooCommerce and plugin versions'    => array( 'plugin_version', 'php_version', 'wordpress_version', 'woocommerce_version', 'sdk_version', 'versions' ),
		'Installed plugins and active theme'                 => array( 'environment.plugins', 'environment.theme' ),
		'Locale, timezone and country'                       => array( 'locale', 'timezone', 'country' ),
		'Server software and whether the site is a multisite' => array( 'server_type', 'is_multisite' ),
		'Which features of %s you use, and errors it hits'   => array( 'custom_event', 'error_report' ),
	);

	const SHARED_FOOTNOTE = 'Nothing is sent if you skip. You can change this anytime in Settings.';

	/** @var Consent */
	private $consent;

	/** @var MarketingConsent|null */
	private $marketing_consent;

	/** @var ContactGate|null */
	private $gate;

	/** @var string|null */
	private $product_name = null;

	/** @var string|null */
	private $privacy_policy_url = null;

	/** @var string|null */
	private $icon_url = null;

	/** @var callable|null */
	private $redirect_handler = null;

	/** @var bool Styles are printed once per page, however many surfaces render. */
	private static $styles_printed = false;

	/**
	 * The last refusal reason, recorded only when WordPress's wp_die() is
	 * unavailable (this package's own test environment) so the refusal is
	 * still observable rather than silent.
	 *
	 * @var string|null
	 */
	public $denied = null;

	/**
	 * @param array<string, string> $options           product_name, privacy_policy_url, icon_url.
	 * @param MarketingConsent|null $marketing_consent The update-email opt-in. Without it, Allow
	 *                                                  records telemetry consent only and the
	 *                                                  settings section shows one switch.
	 * @param ContactGate|null      $gate              Premium builds render nothing.
	 */
	public function __construct( Consent $consent, array $options = array(), ?MarketingConsent $marketing_consent = null, ?ContactGate $gate = null ) {
		$this->consent           = $consent;
		$this->marketing_consent = $marketing_consent;
		$this->gate              = $gate;

		if ( isset( $options['product_name'] ) ) {
			$this->product_name = (string) $options['product_name'];
		}

		if ( isset( $options['privacy_policy_url'] ) ) {
			$this->privacy_policy_url = (string) $options['privacy_policy_url'];
		}

		if ( isset( $options['icon_url'] ) ) {
			$this->icon_url = (string) $options['icon_url'];
		}
	}

	/** @param string $name Shown in the prompt, e.g. "Acme Bookings". */
	public function set_product_name( $name ) {
		$this->product_name = (string) $name;

		return $this;
	}

	/** @param string $url Linked from the prompt when set. */
	public function set_privacy_policy_url( $url ) {
		$this->privacy_policy_url = (string) $url;

		return $this;
	}

	/** @param string $url The product icon shown in the prompt's circle. */
	public function set_icon_url( $url ) {
		$this->icon_url = (string) $url;

		return $this;
	}

	/**
	 * Replaces the redirect-and-exit at the end of handle(). Exists for
	 * this package's own tests (a real exit() ends the test run) and for a
	 * host that wants to render its own confirmation instead.
	 */
	public function set_redirect_handler( ?callable $handler ) {
		$this->redirect_handler = $handler;

		return $this;
	}

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . $this->action(), array( $this, 'handle' ) );
	}

	/** The admin-post.php action name, unique to this product. */
	public function action() {
		return self::ACTION_PREFIX . $this->consent->key();
	}

	// -----------------------------------------------------------------
	// Wording
	// -----------------------------------------------------------------

	/** The prompt's body text, with the plugin's name in it. */
	public function body_text() {
		return 'Opt in to get email notifications for security & feature updates, educational content, '
			. 'and occasional offers, and to share some basic WordPress environment info. This helps us make '
			. $this->product_name() . ' more compatible with your site and better at doing what you need it to.';
	}

	/**
	 * The "What's shared?" lines, in display order.
	 *
	 * @return array<int, string>
	 */
	public function shared_items() {
		$items = array();

		foreach ( array_keys( self::SHARED_FIELDS ) as $line ) {
			$items[] = sprintf( $line, $this->product_name() );
		}

		return $items;
	}

	/**
	 * Stored as the marketing consent's exact wording: the full title and
	 * body the owner saw when they clicked Allow & Continue.
	 */
	public function prompt_wording() {
		return self::TITLE . "\n\n" . $this->body_text();
	}

	/** The settings switch's wording, stored when the owner turns it on there. */
	private function settings_email_wording( $email ) {
		return sprintf(
			'Receive update emails: email notifications for security & feature updates, educational content, '
			. 'and occasional offers about %s, sent to %s. You can turn this off at any time.',
			$this->product_name(),
			$email
		);
	}

	private function reconfirm_text() {
		return 'Our privacy policy has been updated since you agreed to share usage data. '
			. 'Please confirm whether you are still happy to share it.';
	}

	// -----------------------------------------------------------------
	// The prompt
	// -----------------------------------------------------------------

	/** The `admin_notices` callback. Prints nothing when already answered. */
	public function render() {
		if ( ! $this->can_render() || $this->is_premium() ) {
			return;
		}

		if ( ! $this->consent->needs_decision() ) {
			return;
		}

		$reconfirming = ! $this->consent->is_pending();

		$this->print_styles();

		// `notice` so WordPress places it with the other admin notices; the
		// card inside carries all of the visual design.
		echo '<div class="notice appneck-sdk-optin">';
		echo '<div class="appneck-sdk-optin__card" role="region" aria-label="' . esc_attr( $this->product_name() ) . '">';
		echo '<div class="appneck-sdk-optin__band" aria-hidden="true"></div>';
		echo '<div class="appneck-sdk-optin__icon" aria-hidden="true">' . $this->icon_html() . '</div>';
		echo '<div class="appneck-sdk-optin__content">';

		if ( $reconfirming ) {
			echo '<h2 class="appneck-sdk-optin__title">' . esc_html( $this->product_name() ) . '</h2>';
			echo '<p class="appneck-sdk-optin__body">' . esc_html( $this->reconfirm_text() ) . '</p>';
		} else {
			echo '<h2 class="appneck-sdk-optin__title">' . esc_html( self::TITLE ) . '</h2>';
			echo '<p class="appneck-sdk-optin__body">' . esc_html( $this->body_text() ) . '</p>';
			// Hidden for now at the product owner's request (2026-10-05). Restore
			// by uncommenting; SHARED_FIELDS and shared_items() are still pinned
			// to the real payloads by ContactGateTest, so the list stays accurate.
			// $this->render_shared_details();
		}

		if ( null !== $this->privacy_policy_url && '' !== $this->privacy_policy_url ) {
			echo '<p class="appneck-sdk-optin__policy"><a href="' . esc_url( $this->privacy_policy_url ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( 'Read the privacy policy' ) . '</a></p>';
		}

		echo '</div>';

		echo '<form class="appneck-sdk-optin__actions" method="post" action="' . esc_url( $this->post_url() ) . '">';
		$this->render_form_fields();

		if ( $reconfirming ) {
			$this->render_button( self::FIELD, Consent::STATUS_ACCEPTED, 'Keep sharing', 'primary' );
			$this->render_button( self::FIELD, Consent::STATUS_REJECTED, 'Stop sharing', 'secondary' );
		} else {
			$this->render_button( self::FIELD, Consent::STATUS_ACCEPTED, 'Allow & Continue', 'primary', '&rarr;' );
			$this->render_button( self::FIELD, Consent::STATUS_REJECTED, 'Skip', 'secondary' );
		}

		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * <details>, not a script: the list must be readable with JavaScript
	 * off, and the browser's own disclosure widget is keyboard- and
	 * screen-reader-accessible for free.
	 */
	private function render_shared_details() {
		echo '<details class="appneck-sdk-optin__details">';
		echo '<summary>' . esc_html( "What's shared?" ) . '</summary>';
		echo '<ul>';

		foreach ( $this->shared_items() as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}

		echo '</ul>';
		echo '<p>' . esc_html( self::SHARED_FOOTNOTE ) . '</p>';
		echo '</details>';
	}

	/** The product icon, or a neutral plug glyph when none was configured. */
	private function icon_html() {
		if ( null !== $this->icon_url && '' !== $this->icon_url ) {
			return '<img src="' . esc_url( $this->icon_url ) . '" alt="" width="64" height="64" />';
		}

		return '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
			. '<path d="M9 2v5M15 2v5"/><path d="M6 7h12v4a6 6 0 0 1-12 0V7z"/><path d="M12 17v5"/></svg>';
	}

	// -----------------------------------------------------------------
	// The settings section
	// -----------------------------------------------------------------

	/**
	 * Two independent switches for the host plugin's own settings page
	 * (journal §70 D2). Each is a one-button form: pressing it flips that
	 * one decision and records it as its own consent event.
	 *
	 * "Receive update emails" can only be turned ON while usage data is on:
	 * an email opt-in needs a registered installation, and registering is
	 * itself the contact the owner has refused. Turning it OFF always works.
	 */
	public function render_settings_section() {
		if ( ! $this->can_render() || $this->is_premium() ) {
			return;
		}

		$this->print_styles();

		$sharing = $this->consent->is_accepted();

		echo '<div class="appneck-sdk-consent appneck-sdk-settings">';
		echo '<h3 class="appneck-sdk-settings__heading">' . esc_html( 'Data sharing' ) . '</h3>';

		$this->render_switch(
			'Share usage data',
			$this->usage_status_text(),
			$sharing,
			$sharing ? 'usage_off' : 'usage_on',
			false
		);

		if ( null !== $this->marketing_consent ) {
			$emails = $this->marketing_consent->is_opted_in();

			$this->render_switch(
				'Receive update emails',
				$emails
					? 'Security & feature updates, tips and occasional offers'
						. ( null !== $this->marketing_consent->email() ? ' are sent to ' . $this->marketing_consent->email() : '' ) . '.'
					: ( $sharing
						? 'Get security & feature updates, tips and occasional offers by email.'
						: 'Turn on usage data sharing first to receive update emails.' ),
				$emails,
				$emails ? 'emails_off' : 'emails_on',
				! $emails && ! $sharing
			);
		}

		if ( $this->consent->is_sync_pending() || ( null !== $this->marketing_consent && $this->marketing_consent->is_sync_pending() ) ) {
			echo '<p class="appneck-sdk-settings__pending"><em>' . esc_html(
				'Your choice is saved on this site and will be sent to '
				. $this->product_name() . ' automatically.'
			) . '</em></p>';
		}

		echo '</div>';
	}

	private function render_switch( $label, $description, $on, $value, $disabled ) {
		echo '<form class="appneck-sdk-settings__row" method="post" action="' . esc_url( $this->post_url() ) . '">';
		$this->render_form_fields();
		echo '<div class="appneck-sdk-settings__text">';
		echo '<span class="appneck-sdk-settings__label">' . esc_html( $label ) . '</span>';
		echo '<span class="appneck-sdk-settings__description">' . esc_html( $description ) . '</span>';
		echo '</div>';
		echo '<button type="submit" class="appneck-sdk-switch" role="switch"'
			. ' name="' . esc_attr( self::SETTING_FIELD ) . '" value="' . esc_attr( $value ) . '"'
			. ' aria-checked="' . ( $on ? 'true' : 'false' ) . '"'
			. ' aria-label="' . esc_attr( $label ) . '"'
			. ( $disabled ? ' disabled' : '' ) . '>'
			. '<span class="appneck-sdk-switch__thumb" aria-hidden="true"></span>'
			. '</button>';
		echo '</form>';
	}

	private function usage_status_text() {
		if ( $this->consent->is_accepted() ) {
			$decided = $this->consent->decided_at();

			return 'Basic WordPress environment info is shared'
				. ( null !== $decided ? ' (since ' . substr( $decided, 0, 10 ) . ')' : '' ) . '.';
		}

		if ( $this->consent->is_rejected() ) {
			return 'Nothing is shared or collected on this site.';
		}

		return 'You have not decided yet. Nothing is shared until you turn this on.';
	}

	// -----------------------------------------------------------------
	// Shared form plumbing
	// -----------------------------------------------------------------

	private function render_form_fields() {
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->action() ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->action() );
		}
	}

	private function render_button( $name, $value, $label, $variant, $trailing_html = '' ) {
		echo '<button type="submit" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"'
			. ' class="appneck-sdk-optin__btn appneck-sdk-optin__btn--' . esc_attr( $variant ) . '">'
			. esc_html( $label ) . ( '' !== $trailing_html ? ' <span aria-hidden="true">' . $trailing_html . '</span>' : '' )
			. '</button>';
	}

	// -----------------------------------------------------------------
	// Handling a click
	// -----------------------------------------------------------------

	/**
	 * The `admin_post_{action}` callback.
	 *
	 * @return string|null The decision or setting applied, or null when
	 *                     the request was refused. (Returned for tests;
	 *                     in WordPress the redirect ends the request.)
	 */
	public function handle() {
		if ( ! $this->current_user_can_decide() ) {
			$this->deny( 'You are not allowed to change this setting.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->action() ) ) {
			$this->deny( 'That link has expired. Please try again.' );

			return null;
		}

		if ( isset( $_POST[ self::SETTING_FIELD ] ) ) {
			return $this->handle_setting( $this->sanitize( $_POST[ self::SETTING_FIELD ] ) );
		}

		$status = isset( $_POST[ self::FIELD ] ) ? $this->sanitize( $_POST[ self::FIELD ] ) : '';

		if ( ! in_array( $status, array( Consent::STATUS_ACCEPTED, Consent::STATUS_REJECTED ), true ) ) {
			$this->deny( 'That is not a valid choice.' );

			return null;
		}

		// Only a FIRST-EVER answer carries the email opt-in. A
		// re-confirmation exists because the privacy policy changed; it
		// re-asks the usage-data question alone and must not touch an
		// existing email decision.
		if ( $this->consent->is_pending() && null !== $this->marketing_consent ) {
			$this->record_prompt_marketing( Consent::STATUS_ACCEPTED === $status );
		}

		// Marketing first: Consent::decide() syncs, and folds a pending
		// marketing decision into the same /sdk/v1/consent request.
		$this->consent->decide( $status );

		$this->redirect_back();

		return $status;
	}

	/**
	 * Allow & Continue = opted in, with the clicking admin's own email and
	 * name. Skip = an explicit decline. An admin with no email on their
	 * account is not opted in to anything — there is nowhere to send to.
	 */
	private function record_prompt_marketing( $allowed ) {
		$user = $this->current_user();

		if ( $allowed && null === $user ) {
			return;
		}

		$this->marketing_consent->decide(
			$allowed,
			$this->prompt_wording(),
			$allowed ? $user['email'] : null,
			$allowed ? $user['name'] : null
		);
	}

	/** @return string|null */
	private function handle_setting( $setting ) {
		switch ( $setting ) {
			case 'usage_on':
				$this->consent->decide( Consent::STATUS_ACCEPTED );
				break;

			case 'usage_off':
				$this->consent->decide( Consent::STATUS_REJECTED );
				break;

			case 'emails_on':
				$user = $this->current_user();

				if ( null === $this->marketing_consent || ! $this->consent->is_accepted() || null === $user ) {
					$this->deny( 'Turn on usage data sharing first to receive update emails.' );

					return null;
				}

				$this->marketing_consent->decide( true, $this->settings_email_wording( $user['email'] ), $user['email'], $user['name'] );
				$this->consent->sync();
				break;

			case 'emails_off':
				if ( null === $this->marketing_consent ) {
					$this->deny( 'That is not a valid choice.' );

					return null;
				}

				$this->marketing_consent->decide( false, 'Receive update emails: turned off in ' . $this->product_name() . ' settings.' );
				$this->consent->sync();
				break;

			default:
				$this->deny( 'That is not a valid choice.' );

				return null;
		}

		$this->redirect_back();

		return $setting;
	}

	/**
	 * The admin who clicked — their own account's email and display name,
	 * not the site's `admin_email` (journal §70 D2).
	 *
	 * @return array{email: string, name: string|null}|null
	 */
	private function current_user() {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return null;
		}

		$user = wp_get_current_user();

		if ( ! is_object( $user ) || empty( $user->user_email ) || ! is_string( $user->user_email ) ) {
			return null;
		}

		$name = isset( $user->display_name ) && is_string( $user->display_name ) && '' !== trim( $user->display_name )
			? trim( $user->display_name )
			: null;

		return array(
			'email' => $user->user_email,
			'name'  => $name,
		);
	}

	private function redirect_back() {
		$url = null;

		if ( function_exists( 'wp_get_referer' ) ) {
			$referer = wp_get_referer();
			$url     = is_string( $referer ) && '' !== $referer ? $referer : null;
		}

		if ( null === $url ) {
			$url = function_exists( 'admin_url' ) ? admin_url() : '/wp-admin/';
		}

		if ( null !== $this->redirect_handler ) {
			call_user_func( $this->redirect_handler, $url );

			return;
		}

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $url );
		}

		exit;
	}

	private function deny( $message ) {
		if ( function_exists( 'wp_die' ) ) {
			wp_die( esc_html( $message ), '', array( 'response' => 403 ) );

			return;
		}

		$this->denied = (string) $message;
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	/**
	 * `manage_options`: this is a site-wide decision about the site's own
	 * data, which is an administrator's call, not an editor's.
	 */
	private function current_user_can_decide() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		return (bool) current_user_can( 'manage_options' );
	}

	private function can_render() {
		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'esc_attr' ) || ! function_exists( 'esc_url' ) ) {
			return false;
		}

		return $this->current_user_can_decide();
	}

	private function is_premium() {
		return null !== $this->gate && $this->gate->is_premium();
	}

	private function post_url() {
		return function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	}

	private function product_name() {
		return null !== $this->product_name && '' !== $this->product_name
			? $this->product_name
			: 'this plugin';
	}

	private function sanitize( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z_]/', '', strtolower( $value ) );
	}

	/**
	 * Scoped under `.appneck-sdk-optin` / `.appneck-sdk-settings` so
	 * nothing leaks into wp-admin or the host plugin. Printed once per page
	 * even when several products bundle this SDK and the class is loaded
	 * once (the registry loads one copy).
	 */
	private function print_styles() {
		if ( self::$styles_printed ) {
			return;
		}

		self::$styles_printed = true;

		echo '<style>
.appneck-sdk-optin.notice{border:0;background:transparent;box-shadow:none;padding:0;margin:20px 0 16px}
.appneck-sdk-optin__card{position:relative;max-width:520px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.06);overflow:hidden;font-size:14px;color:#1d2327}
.appneck-sdk-optin__band{height:56px;background:#f0f0f1;border-bottom:1px solid #e6e6e8}
.appneck-sdk-optin__icon{position:absolute;top:18px;left:50%;transform:translateX(-50%);width:72px;height:72px;border-radius:50%;background:#fff;border:4px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;overflow:hidden;color:#3858e9;background-image:linear-gradient(135deg,#eef2ff,#e0e7ff)}
.appneck-sdk-optin__icon img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.appneck-sdk-optin__content{padding:44px 28px 18px;text-align:center}
.appneck-sdk-optin__title{margin:0 0 10px;padding:0;font-size:16px;font-weight:600;line-height:1.4;color:#1d2327}
.appneck-sdk-optin__body{margin:0;text-align:left;line-height:1.6;color:#3c434a}
.appneck-sdk-optin__details{margin:12px 0 0;text-align:left}
.appneck-sdk-optin__details summary{display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#3858e9;font-weight:500;list-style:none}
.appneck-sdk-optin__details summary::-webkit-details-marker{display:none}
.appneck-sdk-optin__details summary::after{content:"";width:6px;height:6px;border-right:1.5px solid currentColor;border-bottom:1.5px solid currentColor;transform:rotate(45deg);margin:-3px 0 0 4px;transition:transform .15s ease}
.appneck-sdk-optin__details[open] summary::after{transform:rotate(-135deg);margin-top:3px}
.appneck-sdk-optin__details summary:focus-visible{outline:2px solid #3858e9;outline-offset:2px;border-radius:2px}
.appneck-sdk-optin__details ul{margin:10px 0 0;padding:12px 14px 12px 30px;background:#f6f7f7;border-radius:8px;list-style:disc;color:#3c434a}
.appneck-sdk-optin__details li{margin:3px 0}
.appneck-sdk-optin__details p{margin:8px 0 0;font-size:12.5px;color:#646970}
.appneck-sdk-optin__policy{margin:10px 0 0;font-size:12.5px}
.appneck-sdk-optin__actions{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 20px;border-top:1px solid #f0f0f1;background:#fff;margin:0}
.appneck-sdk-optin__btn{appearance:none;cursor:pointer;font:inherit;font-size:13.5px;font-weight:500;line-height:1.4;border-radius:4px;padding:8px 16px;transition:background .15s ease,box-shadow .15s ease,border-color .15s ease}
.appneck-sdk-optin__btn--primary{background:#3858e9;border:1px solid #3858e9;color:#fff}
.appneck-sdk-optin__btn--primary:hover{background:#2145e6;border-color:#2145e6;box-shadow:0 4px 12px rgba(56,88,233,.3)}
.appneck-sdk-optin__btn--secondary{background:#fff;border:1px solid #3858e9;color:#3858e9}
.appneck-sdk-optin__btn--secondary:hover{background:#f0f3ff}
.appneck-sdk-optin__btn:focus-visible{outline:2px solid #3858e9;outline-offset:2px}
.appneck-sdk-settings{max-width:640px}
.appneck-sdk-settings__heading{margin:0 0 4px}
.appneck-sdk-settings__row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid #f0f0f1;margin:0}
.appneck-sdk-settings__text{display:flex;flex-direction:column;gap:2px}
.appneck-sdk-settings__label{font-weight:600;color:#1d2327}
.appneck-sdk-settings__description{color:#646970;font-size:13px}
.appneck-sdk-settings__pending{color:#646970}
.appneck-sdk-switch{position:relative;flex:0 0 auto;width:40px;height:22px;padding:0;border-radius:11px;border:0;background:#c3c4c7;cursor:pointer;transition:background .15s ease}
.appneck-sdk-switch[aria-checked="true"]{background:#3858e9}
.appneck-sdk-switch__thumb{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .15s ease}
.appneck-sdk-switch[aria-checked="true"] .appneck-sdk-switch__thumb{transform:translateX(18px)}
.appneck-sdk-switch:disabled{opacity:.5;cursor:not-allowed}
.appneck-sdk-switch:focus-visible{outline:2px solid #3858e9;outline-offset:2px}
@media (max-width:600px){.appneck-sdk-optin__content{padding:44px 18px 16px}.appneck-sdk-optin__actions{padding:12px 16px}}
@media (prefers-reduced-motion:reduce){.appneck-sdk-optin__btn,.appneck-sdk-switch,.appneck-sdk-switch__thumb,.appneck-sdk-optin__details summary::after{transition:none}}
</style>';
	}
}
