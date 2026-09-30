<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\License;

/**
 * The opt-in "you haven't licensed this yet" nag (Phase 8, §4 of the
 * build brief).
 *
 * ## Opt-in, and narrowly scoped, on purpose
 *
 * A license nag on every admin page is, in the brief's own words, "the
 * single most complained-about behaviour in commercial WordPress
 * plugins" — so this prints NOWHERE by default. `LicensePage::register()`
 * only ever constructs one when the host plugin passes
 * `'notice' => true`, and even then it registers on exactly two places:
 * the license page's own screen (so the nag and the place that resolves
 * it are the same click away) and the Plugins screen (`plugins.php`,
 * where a site owner deciding what to activate/configure already is).
 * Nowhere else — never site-wide.
 *
 * ## Suppressed whenever nagging would be wrong, not just when it is
 * technically true
 *
 * A valid license suppresses it, obviously. Less obviously: a STALE cache
 * whose last definitive answer was `valid` also suppresses it — a
 * customer who paid and is running fine through a network blip must
 * never see "you haven't licensed this" because of an outage. Only a
 * genuinely unlicensed, expired, suspended, or otherwise non-granting
 * state shows it.
 *
 * ## Dismissal persists, and re-shows after a reasonable interval
 *
 * Stored as a WordPress option, not a transient/object-cache entry —
 * License's own class doc explains why a persistent object cache is the
 * wrong place for anything a fail-closed product cannot afford to lose:
 * the same reasoning applies here, just for a dismissal rather than a
 * license (an evicted dismissal simply re-nags a bit early, which is
 * annoying, not a lockout — still not worth building on a layer that can
 * silently forget). Re-shown after DISMISS_FOR seconds, not "on the very
 * next page load" — the brief's own explicit instruction.
 */
final class LicenseNotice {

	const DISMISS_ACTION_PREFIX = 'appneck_sdk_license_notice_dismiss_';

	/** Seconds a dismissal lasts before the notice can show again. */
	const DISMISS_FOR = 604800; // 7 days.

	/** @var License */
	private $license;

	/** @var string|null */
	private $product_name;

	/** @var array<int, string> */
	private $screen_ids = array();

	/** @var callable|null */
	private $redirect_handler = null;

	/**
	 * The last refusal reason, recorded only when wp_die() is unavailable
	 * (this package's own test environment), matching every other
	 * admin-post handler's convention in this package.
	 *
	 * @var string|null
	 */
	public $denied = null;

	/** @param array<string, string> $options product_name. */
	public function __construct( License $license, array $options = array() ) {
		$this->license      = $license;
		$this->product_name = isset( $options['product_name'] ) ? (string) $options['product_name'] : null;
	}

	/** For this package's own tests. */
	public function set_redirect_handler( ?callable $handler ) {
		$this->redirect_handler = $handler;

		return $this;
	}

	/**
	 * @param array<int, string|null> $screen_ids get_current_screen()->id
	 *                                            values this notice may
	 *                                            appear on. Null entries
	 *                                            (a page not yet
	 *                                            registered) are dropped.
	 */
	public function register_on_screens( array $screen_ids ) {
		$this->screen_ids = array_values(
			array_unique(
				array_filter(
					$screen_ids,
					function ( $id ) {
						return is_string( $id ) && '' !== $id;
					}
				)
			)
		);

		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render_if_on_screen' ) );
			add_action( 'admin_post_' . $this->dismiss_action(), array( $this, 'handle_dismiss' ) );
		}

		return $this;
	}

	private function dismiss_action() {
		return self::DISMISS_ACTION_PREFIX . $this->license->key();
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	/** The `admin_notices` callback. */
	public function render_if_on_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! in_array( $screen->id, $this->screen_ids, true ) ) {
			return;
		}

		$this->render();
	}

	/** Prints nothing when there is nothing to nag about. */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		if ( ! $this->should_show() ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible appneck-sdk-license-notice">';
		echo '<p>' . esc_html(
			'Activate your license for ' . $this->product_name() . ' to enable automatic updates and priority support.'
		) . '</p>';

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '" style="margin-bottom:8px">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->dismiss_action() ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->dismiss_action() );
		}

		echo '<button type="submit" class="button-link notice-dismiss-inline">' . esc_html( 'Dismiss' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Suppressed on a valid license, AND on a stale-but-last-known-valid
	 * one (§4's own explicit instruction — a network blip must not nag a
	 * paying customer), AND while a dismissal is still in its window.
	 */
	private function should_show() {
		$status = $this->license->get_status();

		if ( ! empty( $status['valid'] ) ) {
			return false;
		}

		if ( $this->is_dismissed() ) {
			return false;
		}

		return true;
	}

	private function is_dismissed() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$until = (int) get_option( $this->dismiss_option(), 0 );

		return $until > time();
	}

	private function dismiss_option() {
		return 'appneck_sdk_license_notice_dismissed_until_' . $this->license->key();
	}

	// -----------------------------------------------------------------
	// Handling the dismiss click
	// -----------------------------------------------------------------

	public function handle_dismiss() {
		if ( ! $this->current_user_can_decide() ) {
			$this->deny( 'You are not allowed to change this setting.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->dismiss_action() ) ) {
			$this->deny( 'That link has expired. Please try again.' );

			return null;
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( $this->dismiss_option(), time() + self::DISMISS_FOR );
		}

		$this->redirect_back();

		return true;
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

		$this->denied = (string) $message;
	}

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

	private function post_url() {
		return function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	}

	private function product_name() {
		return null !== $this->product_name && '' !== $this->product_name ? $this->product_name : 'this plugin';
	}
}
