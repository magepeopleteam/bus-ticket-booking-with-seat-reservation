<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\License;
// LicenseMessages (the reason-to-message map, Phase 8) is in this same
// Admin namespace, so no `use` is needed to reach it below.

/**
 * The license panel a host plugin echoes on its OWN settings page.
 *
 * ## Opt-in, and it prints nowhere until asked
 *
 * Unlike ConsentNotice, this class registers no `admin_notices` hook at
 * all. register_hooks() wires exactly one thing — the admin-post handler
 * that receives a click — and render() prints only where the host plugin
 * calls it:
 *
 *     $sdk->license_form()->render();
 *
 * A licensing panel appearing unbidden on somebody else's screen would
 * be worse than an announcement doing it: it asks for a secret, and a
 * key input that materialises on a plugin page the site owner did not
 * associate with this product is indistinguishable from a phishing
 * field. The host plugin knows where its own settings live; this SDK
 * does not, and does not guess.
 *
 * ## One admin-post action per product
 *
 * The action name carries the product key hash, the same shape
 * ConsentNotice uses and for the same reason: several plugins on one
 * site may each bundle this SDK, and a shared
 * `admin_post_appneck_sdk_license` action would mean one plugin's
 * Deactivate click released another plugin's license.
 *
 * Both operations POST — never a GET link. A link that deactivates a
 * license is triggerable by a prefetching browser or an <img> tag on
 * another site, and a nonce is not meant to be the only thing standing
 * between the two.
 *
 * ## No enqueued assets, no build step
 *
 * The panel is plain HTML using WordPress's own admin classes, matching
 * DeactivationSurvey's rule. The single line of JavaScript is an inline
 * ES5 `confirm()` on the Deactivate button — no arrow functions, no
 * const, nothing that needs transpiling, and the button still works with
 * JavaScript disabled because the confirmation is a courtesy, not the
 * control.
 */
final class LicenseForm {

	const ACTION_PREFIX = 'appneck_sdk_license_';

	const FIELD_OPERATION = 'appneck_sdk_license_operation';

	const FIELD_KEY = 'appneck_sdk_license_key';

	const OP_ACTIVATE = 'activate';

	const OP_DEACTIVATE = 'deactivate';

	/** @var License */
	private $license;

	/** @var string|null */
	private $product_name = null;

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
	 * The last operation handle() actually performed, for the host plugin
	 * and for this package's tests. Null when nothing was performed.
	 *
	 * @var string|null
	 */
	public $last_operation = null;

	/**
	 * The Response from that operation, so a host can surface the
	 * server's own message. Null when nothing was attempted.
	 *
	 * @var \Appneck\Sdk\Http\Response|null
	 */
	public $last_response = null;

	/** @param array<string, string> $options product_name. */
	public function __construct( License $license, array $options = array() ) {
		$this->license = $license;

		if ( isset( $options['product_name'] ) ) {
			$this->product_name = (string) $options['product_name'];
		}
	}

	/** @param string $name Shown in the panel, e.g. "Acme Bookings". */
	public function set_product_name( $name ) {
		$this->product_name = (string) $name;

		return $this;
	}

	/**
	 * Replaces the redirect-and-exit at the end of handle(). Exists for
	 * this package's own tests (a real exit() ends the test run) and for
	 * a host that wants to render its own confirmation instead.
	 */
	public function set_redirect_handler( ?callable $handler ) {
		$this->redirect_handler = $handler;

		return $this;
	}

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// The handler ONLY. No admin_notices — see the class doc.
		add_action( 'admin_post_' . $this->action(), array( $this, 'handle' ) );
	}

	/** The per-product admin-post action this instance answers on. */
	public function action() {
		return self::ACTION_PREFIX . $this->license->key();
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	/**
	 * The panel. Call it from the host plugin's own settings page.
	 *
	 * Reads cached state only — get_status() makes no network call — so
	 * this is safe to render on a page somebody is waiting for.
	 */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		$status = $this->license->get_status();

		echo '<div class="appneck-sdk-license">';
		echo '<h3>' . esc_html( $this->product_name() . ' license' ) . '</h3>';

		if ( empty( $status['has_license'] ) ) {
			$this->render_activate_form();
		} else {
			$this->render_active_panel( $status );
		}

		echo '</div>';
	}

	private function render_activate_form() {
		echo '<p>' . esc_html( 'Enter your license key to activate this site.' ) . '</p>';

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '">';
		$this->render_hidden_fields( self::OP_ACTIVATE );

		echo '<p><input type="text" name="' . esc_attr( self::FIELD_KEY ) . '"'
			. ' class="regular-text" autocomplete="off" spellcheck="false"'
			. ' placeholder="' . esc_attr( 'lic_…' ) . '" /></p>';

		echo '<p><button type="submit" class="button button-primary">'
			. esc_html( 'Activate' ) . '</button></p>';
		echo '</form>';
	}

	/** @param array<string, mixed> $status */
	private function render_active_panel( array $status ) {
		echo '<table class="form-table"><tbody>';
		$this->render_row( 'License key', $status['license_key'] );
		$this->render_row( 'Status', $this->status_text( $status ) );

		if ( null !== $status['customer_name'] ) {
			$this->render_row( 'Licensed to', $status['customer_name'] );
		}

		if ( null !== $status['expires_at'] ) {
			$this->render_row( 'Expires', substr( (string) $status['expires_at'], 0, 10 ) );
		}

		if ( null !== $status['activation_limit'] ) {
			$this->render_row(
				'Sites used',
				( null === $status['activations_used'] ? '?' : (string) $status['activations_used'] )
					. ' of ' . (string) $status['activation_limit']
			);
		}

		if ( null !== $status['last_checked_at'] ) {
			$this->render_row( 'Last checked', $this->format_time( (int) $status['last_checked_at'] ) );
		}

		echo '</tbody></table>';

		if ( ! empty( $status['stale'] ) ) {
			// The one state a site owner would otherwise misread as "my
			// license broke". Says what is actually true: we could not
			// ask, so this is the last answer we got.
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				'We could not reach the license server, so this is the last known status. '
				. $this->retry_text( $status )
			) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '">';
		$this->render_hidden_fields( self::OP_DEACTIVATE );

		echo '<p><button type="submit" class="button button-secondary"'
			. ' onclick="return confirm(\''
			. esc_attr( 'Deactivate this license on this site?' )
			. '\');">' . esc_html( 'Deactivate' ) . '</button></p>';
		echo '</form>';
	}

	/** @param string $operation */
	private function render_hidden_fields( $operation ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->action() ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::FIELD_OPERATION ) . '"'
			. ' value="' . esc_attr( $operation ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->action() );
		}
	}

	/**
	 * @param string $label
	 * @param string $value
	 */
	private function render_row( $label, $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th>'
			. '<td>' . esc_html( (string) $value ) . '</td></tr>';
	}

	// -----------------------------------------------------------------
	// Handling the click
	// -----------------------------------------------------------------

	/**
	 * The `admin_post_<action>` callback.
	 *
	 * @return string|null The operation performed, or null when refused.
	 */
	public function handle() {
		$this->last_operation = null;
		$this->last_response  = null;

		if ( ! $this->current_user_can_decide() ) {
			$this->deny( 'You are not allowed to change this setting.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->action() ) ) {
			// WordPress's own implementation dies before returning on a
			// bad nonce, which is the intended behaviour for a state
			// change. Honouring a false return as well costs one
			// comparison and means the refusal is real rather than
			// assumed.
			$this->deny( 'That link has expired. Please try again.' );

			return null;
		}

		$operation = isset( $_POST[ self::FIELD_OPERATION ] )
			? $this->sanitize_key( $_POST[ self::FIELD_OPERATION ] )
			: '';

		if ( self::OP_ACTIVATE === $operation ) {
			return $this->handle_activate();
		}

		if ( self::OP_DEACTIVATE === $operation ) {
			return $this->handle_deactivate();
		}

		$this->deny( 'That is not a valid action.' );

		return null;
	}

	/** @return string|null */
	private function handle_activate() {
		$key = isset( $_POST[ self::FIELD_KEY ] ) ? $this->sanitize_text( $_POST[ self::FIELD_KEY ] ) : '';

		if ( '' === $key ) {
			$this->deny( 'Please enter a license key.' );

			return null;
		}

		// Never throws and always returns a Response, so an unreachable
		// server lands the site owner back on their own page with an
		// unchanged license rather than on an error screen.
		$this->last_response  = $this->license->activate( $key );
		$this->last_operation = self::OP_ACTIVATE;

		$this->redirect_back();

		return self::OP_ACTIVATE;
	}

	/** @return string|null */
	private function handle_deactivate() {
		$status = $this->license->get_status();

		if ( empty( $status['has_license'] ) ) {
			$this->deny( 'There is no license to deactivate.' );

			return null;
		}

		// deactivate_stored(), not deactivate( $_POST[...] ): the panel
		// renders only a masked key, so there is nothing for the form to
		// carry back, and accepting a key from the request would let a
		// crafted POST aim this site's Deactivate button at a license it
		// never held. License keeps the raw key inside its own store and
		// exposes no getter for it, which is what makes that impossible
		// here rather than merely unlikely.
		$this->last_response  = $this->license->deactivate_stored();
		$this->last_operation = self::OP_DEACTIVATE;

		$this->redirect_back();

		return self::OP_DEACTIVATE;
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
	 * A license is the site owner's property and their commercial
	 * relationship — `manage_options`, the same capability ConsentNotice
	 * uses. An editor publishing a post has no business releasing the
	 * site's activation slot.
	 */
	private function current_user_can_decide() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		return (bool) current_user_can( 'manage_options' );
	}

	/**
	 * The escaping functions are WordPress's; nothing may be printed
	 * without them. In production these always exist — the only caller is
	 * an admin screen — so this guard is for the package's non-WordPress
	 * test environment and for a host calling render() from somewhere
	 * unexpected.
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

	/**
	 * @param array<string, mixed> $status
	 * @return string
	 */
	private function status_text( array $status ) {
		if ( ! empty( $status['valid'] ) ) {
			return 'Active on this site.';
		}

		$reason = isset( $status['reason'] ) ? (string) $status['reason'] : '';

		// Extracted to Admin\LicenseMessages (Phase 8) so
		// Admin\LicensePage does not carry a second, driftable copy of
		// the same map. Same strings, same fallback — this method's
		// output is unchanged.
		return LicenseMessages::for_reason( $reason );
	}

	/**
	 * @param array<string, mixed> $status
	 * @return string
	 */
	private function retry_text( array $status ) {
		if ( empty( $status['next_attempt_at'] ) ) {
			return 'It will be checked again shortly.';
		}

		$seconds = (int) $status['next_attempt_at'] - time();

		if ( $seconds <= 0 ) {
			return 'It will be checked again shortly.';
		}

		$minutes = (int) ceil( $seconds / 60 );

		if ( $minutes < 60 ) {
			return 'The next check is in about ' . $minutes . ' minute' . ( 1 === $minutes ? '' : 's' ) . '.';
		}

		$hours = (int) ceil( $minutes / 60 );

		return 'The next check is in about ' . $hours . ' hour' . ( 1 === $hours ? '' : 's' ) . '.';
	}

	/** @param int $timestamp */
	private function format_time( $timestamp ) {
		if ( function_exists( 'date_i18n' ) && function_exists( 'get_option' ) ) {
			$format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );

			return (string) date_i18n( $format, $timestamp );
		}

		return gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC';
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	private function sanitize_key( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return preg_replace( '/[^a-z_]/', '', strtolower( $value ) );
	}

	/**
	 * A license key is `lic_` + 40 alphanumerics (journal §22.3), so
	 * anything outside that alphabet is not a key and is stripped rather
	 * than sent. sanitize_text_field where WordPress offers it, because
	 * it also strips the invisible characters a copy-paste from an email
	 * client carries.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function sanitize_text( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = sanitize_text_field( $value );
		}

		return trim( preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) );
	}
}
