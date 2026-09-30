<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\Consent;

/**
 * The site-owner-facing consent prompt: an admin notice asking the
 * question, and a reusable settings section for changing the answer later.
 *
 * ## Why an admin notice, and not a settings page of our own
 *
 * This SDK has no admin surface at all, so there was a real choice here.
 * A dedicated page was rejected: an embedded library must not add a
 * top-level menu item to somebody else's plugin — the site owner would
 * see an "Appneck" menu they never installed, and two plugins bundling
 * this SDK would either fight over the menu or add two of them. An admin
 * notice is the WordPress-idiomatic way for a plugin to ask its owner a
 * question, it appears wherever they already are, and it costs the host
 * plugin nothing.
 *
 * The notice is NOT dismissible, and shows until the question is answered.
 * A dismiss button on a consent prompt is a third answer that means
 * neither yes nor no, and the state it leaves behind (`pending`) is one
 * where the SDK keeps buffering — so "dismiss" would read as a way to
 * make the question go away while collection quietly continued. Accept
 * and Reject are the only exits; either one hides the notice forever.
 *
 * ## Changing the decision later
 *
 * render_settings_section() is a fragment the host plugin echoes inside
 * its OWN settings page, which is where a site owner looks for a setting.
 * That keeps the "change your mind at any time" requirement satisfied
 * without this SDK inventing a page to put it on:
 *
 *     $sdk->consent_notice()->render_settings_section();
 *
 * ## Both surfaces post, and both are per-product
 *
 * The buttons are form submits to admin-post.php, not links: a GET link
 * that changes a stored decision is triggerable by a prefetching browser
 * or an <img> tag on another site, and nonces are not meant to be the
 * only thing standing between the two. The action name carries the
 * product key hash because several plugins on one site may each bundle
 * this SDK, and a shared `admin_post_appneck_sdk_consent` action would
 * mean one plugin's Accept click also answered for every other.
 */
final class ConsentNotice {

	const ACTION_PREFIX = 'appneck_sdk_consent_';

	const FIELD = 'appneck_sdk_consent_decision';

	/** @var Consent */
	private $consent;

	/** @var string|null */
	private $product_name = null;

	/** @var string|null */
	private $privacy_policy_url = null;

	/** @var callable|null */
	private $redirect_handler = null;

	/**
	 * The last refusal reason, recorded only when WordPress's wp_die() is
	 * unavailable (this package's own test environment) so the refusal is
	 * still observable rather than silent.
	 *
	 * @var string|null
	 */
	public $denied = null;

	/**
	 * @param array<string, string> $options product_name, privacy_policy_url.
	 */
	public function __construct( Consent $consent, array $options = array() ) {
		$this->consent = $consent;

		if ( isset( $options['product_name'] ) ) {
			$this->product_name = (string) $options['product_name'];
		}

		if ( isset( $options['privacy_policy_url'] ) ) {
			$this->privacy_policy_url = (string) $options['privacy_policy_url'];
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

	/** The per-product admin-post action this instance answers on. */
	public function action() {
		return self::ACTION_PREFIX . $this->consent->key();
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	/** The `admin_notices` callback. Prints nothing when already answered. */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		if ( ! $this->consent->needs_decision() ) {
			return;
		}

		$reconfirming = ! $this->consent->is_pending();

		echo '<div class="notice notice-info">';
		echo '<p><strong>' . esc_html( $this->product_name() ) . '</strong></p>';
		echo '<p>' . esc_html( $this->prompt_text( $reconfirming ) ) . '</p>';

		if ( null !== $this->privacy_policy_url && '' !== $this->privacy_policy_url ) {
			echo '<p><a href="' . esc_url( $this->privacy_policy_url ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( 'Read the privacy policy' ) . '</a></p>';
		}

		$this->render_form(
			array(
				Consent::STATUS_ACCEPTED => 'Allow usage data',
				Consent::STATUS_REJECTED => 'No thanks',
			)
		);

		echo '</div>';
	}

	/**
	 * The change-decision control, for the host plugin's own settings
	 * page. Prints the current decision and the one button that changes
	 * it — a single opposing action rather than two, because the state is
	 * already stated in the sentence above it.
	 */
	public function render_settings_section() {
		if ( ! $this->can_render() ) {
			return;
		}

		echo '<div class="appneck-sdk-consent">';
		echo '<h3>' . esc_html( 'Usage data' ) . '</h3>';
		echo '<p>' . esc_html( $this->status_text() ) . '</p>';

		if ( $this->consent->is_sync_pending() ) {
			// Honest about the one state a site owner could otherwise
			// misread as "my click did nothing".
			echo '<p><em>' . esc_html(
				'Your choice is saved on this site and will be sent to '
				. $this->product_name() . ' automatically.'
			) . '</em></p>';
		}

		$this->render_form(
			$this->consent->is_accepted()
				? array( Consent::STATUS_REJECTED => 'Stop sharing usage data' )
				: array( Consent::STATUS_ACCEPTED => 'Start sharing usage data' )
		);

		echo '</div>';
	}

	/**
	 * @param array<string, string> $buttons status => label. The first is
	 *                                       rendered as the primary.
	 */
	private function render_form( array $buttons ) {
		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->action() ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->action() );
		}

		$primary = true;

		foreach ( $buttons as $status => $label ) {
			echo '<button type="submit" name="' . esc_attr( self::FIELD ) . '"'
				. ' value="' . esc_attr( $status ) . '"'
				. ' class="button ' . ( $primary ? 'button-primary' : 'button-secondary' ) . '"'
				. ' style="margin-right:6px">' . esc_html( $label ) . '</button>';

			$primary = false;
		}

		echo '</form>';
	}

	// -----------------------------------------------------------------
	// Handling the click
	// -----------------------------------------------------------------

	/**
	 * The `admin_post_<action>` callback.
	 *
	 * @return string|null The status applied, or null when refused.
	 */
	public function handle() {
		if ( ! $this->current_user_can_decide() ) {
			$this->deny( 'You are not allowed to change this setting.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->action() ) ) {
			// WordPress's own implementation dies before returning on a bad
			// nonce, which is the intended behaviour for a state change.
			// Honouring a false return as well costs one comparison and
			// means the refusal is real rather than assumed.
			$this->deny( 'That link has expired. Please try again.' );

			return null;
		}

		$status = isset( $_POST[ self::FIELD ] ) ? $this->sanitize( $_POST[ self::FIELD ] ) : '';

		if ( ! in_array( $status, array( Consent::STATUS_ACCEPTED, Consent::STATUS_REJECTED ), true ) ) {
			$this->deny( 'That is not a valid choice.' );

			return null;
		}

		// Returns a Response (or null) and never throws, so a consent call
		// that fails while the API is down still lands the site owner back
		// on their own page with the decision saved locally. The retry is
		// Consent's problem, not theirs.
		$this->consent->decide( $status );

		$this->redirect_back();

		return $status;
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

	/** @param string $message */
	private function deny( $message ) {
		if ( function_exists( 'wp_die' ) ) {
			wp_die( esc_html( $message ), '', array( 'response' => 403 ) );

			return;
		}

		// No WordPress (this package's own tests). Recorded rather than
		// exiting, so the refusal is assertable.
		$this->denied = (string) $message;
	}

	// -----------------------------------------------------------------
	// Environment
	// -----------------------------------------------------------------

	/**
	 * Consent is the site owner's decision, so only a user who
	 * administers the site may make it — `manage_options`, the same
	 * capability WordPress gates its own privacy tools behind. An editor
	 * publishing a post has no business answering for the site.
	 */
	private function current_user_can_decide() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		return (bool) current_user_can( 'manage_options' );
	}

	/**
	 * The escaping functions are WordPress's; nothing may be printed
	 * without them. In production these always exist, because the only
	 * callers are admin hooks — this guard is for the package's non-WordPress
	 * test environment and for a host plugin calling the settings section
	 * from somewhere unexpected.
	 */
	private function can_render() {
		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'esc_attr' ) || ! function_exists( 'esc_url' ) ) {
			return false;
		}

		return $this->current_user_can_decide();
	}

	private function post_url() {
		return function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	}

	private function product_name() {
		return null !== $this->product_name && '' !== $this->product_name
			? $this->product_name
			: 'This plugin';
	}

	/** @param bool $reconfirming */
	private function prompt_text( $reconfirming ) {
		if ( $reconfirming ) {
			return 'Our privacy policy has been updated since you agreed to share usage data. '
				. 'Please confirm whether you are still happy to share it.';
		}

		return 'Help improve this plugin by sharing anonymous usage data — which features are used, '
			. 'and errors when they happen. No personal data and no content from your site is collected, '
			. 'and you can change this at any time.';
	}

	private function status_text() {
		if ( $this->consent->is_accepted() ) {
			$decided = $this->consent->decided_at();

			return 'You are sharing anonymous usage data'
				. ( null !== $decided ? ' (since ' . substr( $decided, 0, 10 ) . ')' : '' ) . '.';
		}

		if ( $this->consent->is_rejected() ) {
			return 'You are not sharing usage data. Nothing is collected on this site.';
		}

		return 'You have not decided whether to share anonymous usage data yet.';
	}

	/** @param mixed $value */
	private function sanitize( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z_]/', '', strtolower( $value ) );
	}
}
