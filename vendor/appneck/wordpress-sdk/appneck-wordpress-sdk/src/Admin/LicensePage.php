<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\License;

/**
 * The complete, one-call license admin experience (Phase 8, journal §22-26).
 *
 * ## Why this is a NEW class, not LicenseForm extended or replaced
 *
 * LicenseForm is `final`, so "extend" was never on the table structurally
 * — and the audit that preceded this class concluded it should not be,
 * even if it could be. LicenseForm answers a narrower, still-real
 * question: "let me put a license panel inside a settings page I already
 * built." Its render() prints one panel shape, its handle() answers on a
 * FIXED per-product admin-post action, and every developer already using
 * it gets that exact behaviour forever — LicenseFormTest's 15 cases still
 * pass, unedited, byte-for-byte, after this class was added.
 *
 * This class answers a different, wider question: "register a whole page
 * for me, with every state Elementor's or WP Rocket's license screen
 * has, and I don't want to build any of it." That needs a menu
 * registration LicenseForm has no opinion about, a distinct admin-post
 * action (a menu-registered page can be reached under a developer-chosen
 * slug, so its action name cannot reuse LicenseForm's), and several
 * states LicenseForm's minimal panel does not attempt: expired vs.
 * suspended vs. cancelled read differently on purpose, activation-limit
 * guidance, and the two distinct unreachable states §2 of the build brief
 * asks for.
 *
 * The one piece of real, shared LOGIC between the two — the server's
 * reason-to-sentence map — is NOT duplicated: both classes call
 * Admin\LicenseMessages::for_reason(). Everything else these two classes
 * do differs enough in shape (a whole page vs. a panel fragment) that
 * sharing more would mean threading page-only concerns like the menu URL
 * back into LicenseForm, which has no business knowing about them.
 *
 * ## One call, two things happen
 *
 * `Plugin::license_page( $args )` builds one of these and calls
 * register() on it — the admin_menu hook, the admin-post handler, and
 * (opt-in via $args['notice']) the unlicensed nag. A developer never
 * constructs this class directly.
 */
final class LicensePage {

	const FIELD_OPERATION = 'appneck_sdk_license_page_operation';

	const FIELD_KEY = 'appneck_sdk_license_page_key';

	const OP_ACTIVATE = 'activate';

	const OP_DEACTIVATE = 'deactivate';

	/** Seconds the post-redirect flash notice survives. One page load. */
	const FLASH_TTL = 60;

	/** @var License */
	private $license;

	/** @var string|null */
	private $product_name;

	/** @var array<string, mixed> */
	private $args = array();

	/** @var string|null Set once register() has run. */
	private $hook_suffix = null;

	/** @var LicenseNotice|null */
	private $notice;

	/** @var callable|null For this package's own tests. */
	private $redirect_handler = null;

	/**
	 * The last refusal reason, recorded only when wp_die() is unavailable
	 * (this package's own test environment), matching LicenseForm's own
	 * convention for the identical reason.
	 *
	 * @var string|null
	 */
	public $denied = null;

	/** @param array<string, string> $options product_name. */
	public function __construct( License $license, array $options = array() ) {
		$this->license = $license;

		$this->product_name = isset( $options['product_name'] ) ? (string) $options['product_name'] : null;
	}

	/** For this package's own tests; a real exit() ends the test run. */
	public function set_redirect_handler( ?callable $handler ) {
		$this->redirect_handler = $handler;

		return $this;
	}

	// -----------------------------------------------------------------
	// Registration
	// -----------------------------------------------------------------

	/**
	 * @param array<string, mixed> $args parent, page_title, menu_title,
	 *                                   capability, menu_slug, icon,
	 *                                   position, purchase_url, renew_url,
	 *                                   support_url, product_name, notice.
	 */
	public function register( array $args = array() ) {
		$this->args = array_merge( $this->defaults(), $args );

		if ( null !== $this->args['product_name'] ) {
			$this->product_name = (string) $this->args['product_name'];
		}

		// Lets License::require_valid() link somewhere real, without
		// License ever constructing a URL itself.
		$this->license->set_page_url( $this->page_url() );

		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_post_' . $this->action(), array( $this, 'handle' ) );
		}

		if ( ! empty( $this->args['notice'] ) ) {
			$this->notice = new LicenseNotice( $this->license, array( 'product_name' => $this->product_name ) );

			// 'plugins' is always included — see LicenseNotice's own doc
			// comment for why that screen specifically, alongside this
			// page's own (known once add_menu() has actually run).
			add_action(
				'admin_menu',
				function () {
					$this->notice->register_on_screens( array( $this->hook_suffix, 'plugins' ) );
				},
				20
			);
		}

		return $this;
	}

	/** @return array<string, mixed> */
	private function defaults() {
		return array(
			'parent'       => null,
			'page_title'   => 'License',
			'menu_title'   => 'License',
			'capability'   => 'manage_options',
			'menu_slug'    => 'appneck-sdk-license-' . $this->license->key(),
			'icon'         => 'dashicons-admin-network',
			'position'     => null,
			'purchase_url' => null,
			'renew_url'    => null,
			'support_url'  => null,
			'product_name' => null,
			'notice'       => false,
		);
	}

	/** The `admin_menu` callback. */
	public function add_menu() {
		if ( ! function_exists( 'add_menu_page' ) ) {
			return;
		}

		if ( null !== $this->args['parent'] && '' !== $this->args['parent'] && function_exists( 'add_submenu_page' ) ) {
			$this->hook_suffix = add_submenu_page(
				$this->args['parent'],
				$this->args['page_title'],
				$this->args['menu_title'],
				$this->args['capability'],
				$this->args['menu_slug'],
				array( $this, 'render' )
			);

			return;
		}

		$this->hook_suffix = add_menu_page(
			$this->args['page_title'],
			$this->args['menu_title'],
			$this->args['capability'],
			$this->args['menu_slug'],
			array( $this, 'render' ),
			(string) $this->args['icon'],
			$this->args['position']
		);
	}

	/** The per-instance admin-post action — see the class doc for why
	 *  this cannot reuse LicenseForm's. */
	public function action() {
		return 'appneck_sdk_license_page_' . $this->license->key();
	}

	/**
	 * Where this page actually lives, which is NOT always admin.php.
	 *
	 * WordPress serves a submenu page from whichever admin FILE its
	 * parent is: a page parented to 'edit.php?post_type=book' lives at
	 * edit.php?post_type=book&page=<slug>, one parented to
	 * 'options-general.php' at options-general.php?page=<slug>. Only a
	 * page whose parent is itself a slug registered by add_menu_page
	 * (or a top-level page of our own) is served by admin.php.
	 *
	 * Getting this wrong is not cosmetic: redirect_back() sends the
	 * browser here after every activate/deactivate, and admin.php would
	 * answer "You do not have sufficient permissions to access this
	 * page" for a page it never registered — so the operation would
	 * succeed and still look broken. Found against a real plugin whose
	 * menu is a custom-post-type menu.
	 *
	 * Built by string rather than via menu_page_url(): this is called
	 * from register(), long before admin_menu has populated the
	 * $_parent_pages lookup that function reads, and again from
	 * admin-post.php, which never builds the admin menu at all.
	 */
	private function page_url() {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}

		$parent = $this->args['parent'];

		if ( is_string( $parent ) && '' !== $parent && false !== strpos( $parent, '.php' ) ) {
			$separator = false === strpos( $parent, '?' ) ? '?' : '&';

			return admin_url( $parent . $separator . 'page=' . $this->args['menu_slug'] );
		}

		return admin_url( 'admin.php?page=' . $this->args['menu_slug'] );
	}

	// -----------------------------------------------------------------
	// Rendering — the page itself
	// -----------------------------------------------------------------

	/**
	 * The `add_menu_page`/`add_submenu_page` render callback.
	 *
	 * Reads cached state only (get_status() makes no network call) plus
	 * this request's own flash, if any — never a fresh network round
	 * trip, because a settings page must not block on one.
	 */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		$status = $this->license->get_status();
		$flash  = $this->read_flash();

		echo '<div class="wrap appneck-sdk-license-page">';
		echo '<h1>' . esc_html( $this->heading() ) . '</h1>';

		$this->render_style();

		if ( null !== $flash ) {
			$this->render_flash( $flash );
		}

		$this->render_state( $status );

		echo '</div>';
	}

	private function heading() {
		return $this->product_name() . ' License';
	}

	/** @param array<string, mixed> $status */
	private function render_state( array $status ) {
		if ( empty( $status['has_license'] ) ) {
			$this->render_no_license();

			return;
		}

		if ( ! empty( $status['domain_mismatch'] ) ) {
			$this->render_domain_changed();

			return;
		}

		if ( ! empty( $status['stale'] ) ) {
			// Unreachable. Split on whether there is a last known answer
			// worth reassuring the customer with at all.
			if ( true === $status['valid'] ) {
				$this->render_unreachable_last_known_valid( $status );
			} else {
				$this->render_unreachable_never_confirmed( $status );
			}

			return;
		}

		if ( ! empty( $status['valid'] ) ) {
			$this->render_active( $status );

			return;
		}

		$reason = isset( $status['reason'] ) ? (string) $status['reason'] : '';

		if ( 'expired' === $reason ) {
			$this->render_expired( $status );

			return;
		}

		if ( 'suspended' === $reason ) {
			$this->render_suspended( $status );

			return;
		}

		if ( in_array( $reason, array( 'cancelled', 'refunded', 'revoked' ), true ) ) {
			$this->render_terminal( $status, $reason );

			return;
		}

		// A stored key that is simply not valid for some other reason
		// (e.g. not_activated_on_domain, license_not_found after a key
		// rotation) — same "explain plainly, let them re-enter" shape as
		// no-license, but the input is pre-filled with nothing since the
		// raw key is never available to render back (§6).
		$this->render_no_license( $status );
	}

	// -- No license --------------------------------------------------

	private function render_domain_changed() {
		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--bad">'
			. '<span class="dashicons dashicons-warning" aria-hidden="true"></span> '
			. esc_html( 'This site\'s domain has changed since the license was last checked. Activate the license here to use it on this domain.' ) . '</p>';
		echo '<p>' . esc_html( 'Enter the existing license key to activate it for ' . $this->product_name() . '.' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '" class="appneck-sdk-license-form">';
		$this->render_hidden_fields( self::OP_ACTIVATE );
		echo '<input type="text" name="' . esc_attr( self::FIELD_KEY ) . '" class="regular-text code"'
			. ' autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"'
			. ' placeholder="' . esc_attr( 'lic_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ) . '" />'
			. ' <button type="submit" class="button button-primary">' . esc_html( 'Activate' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/** @param array<string, mixed>|null $status Set for a rejected-but-stored key. */
	private function render_no_license( $status = null ) {
		echo '<div class="appneck-sdk-license-card">';

		if ( null !== $status ) {
			echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--bad">'
				. '<span class="dashicons dashicons-warning" aria-hidden="true"></span> '
				. esc_html( LicenseMessages::for_reason( $status['reason'] ) ) . '</p>';
		}

		echo '<p>' . esc_html( 'Enter your license key to enable updates and priority support for ' . $this->product_name() . '.' ) . '</p>';

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '" class="appneck-sdk-license-form">';
		$this->render_hidden_fields( self::OP_ACTIVATE );
		echo '<input type="text" name="' . esc_attr( self::FIELD_KEY ) . '" class="regular-text code"'
			. ' autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"'
			. ' placeholder="' . esc_attr( 'lic_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ) . '" />'
			. ' <button type="submit" class="button button-primary">' . esc_html( 'Activate' ) . '</button>';
		echo '</form>';

		if ( null !== $this->args['purchase_url'] && '' !== $this->args['purchase_url'] ) {
			echo '<p class="appneck-sdk-license-meta"><a href="' . esc_url( $this->args['purchase_url'] ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( "Don't have a key? Buy one" ) . '</a></p>';
		}

		echo '</div>';
	}

	// -- Active --------------------------------------------------------

	/** @param array<string, mixed> $status */
	private function render_active( array $status ) {
		echo '<div class="appneck-sdk-license-card">';

		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--good">'
			. '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html( 'Active' ) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row( 'License key', esc_html( $status['license_key'] ) );

		if ( null !== $status['customer_name'] && '' !== $status['customer_name'] ) {
			$this->row( 'Licensed to', esc_html( (string) $status['customer_name'] ) );
		}

		$this->row( 'Expires', $this->expiry_html( $status['expires_at'] ) );
		$this->row( 'Sites used', esc_html( $this->sites_used_text( $status ) ) );
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '">';
		$this->render_hidden_fields( self::OP_DEACTIVATE );
		echo '<button type="submit" class="button button-secondary" onclick="return confirm('
			. esc_attr( $this->confirm_text( 'Deactivate this license on this site? ' . $this->product_name() . "'s pro features will stop working here until you activate again." ) )
			. ');">' . esc_html( 'Deactivate' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}

	// -- Expired ---------------------------------------------------------

	/** @param array<string, mixed> $status */
	private function render_expired( array $status ) {
		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--warn">'
			. '<span class="dashicons dashicons-clock" aria-hidden="true"></span> ' . esc_html( 'Expired' ) . '</p>';

		echo '<p>' . esc_html(
			$this->product_name() . ' keeps working exactly as before. What stops is updates and priority '
			. 'support, until this license is renewed.'
		) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row( 'License key', esc_html( $status['license_key'] ) );
		$this->row( 'Expired', $this->expiry_html( $status['expires_at'] ) );
		echo '</tbody></table>';

		if ( null !== $this->args['renew_url'] && '' !== $this->args['renew_url'] ) {
			echo '<p><a href="' . esc_url( $this->args['renew_url'] ) . '" target="_blank" rel="noopener noreferrer" class="button button-primary">'
				. esc_html( 'Renew your license' ) . '</a></p>';
		}

		echo '</div>';
	}

	// -- Suspended -------------------------------------------------------

	/** @param array<string, mixed> $status */
	private function render_suspended( array $status ) {
		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--bad">'
			. '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> ' . esc_html( 'Suspended' ) . '</p>';

		echo '<p>' . esc_html(
			'This license has been suspended by ' . $this->product_name()
			. "'s publisher. Retrying activation will not change this — please contact support."
		) . '</p>';

		$this->render_support_link();
		echo '</div>';
	}

	// -- Cancelled / refunded / revoked -----------------------------------

	/**
	 * @param array<string, mixed> $status
	 * @param string               $reason cancelled|refunded|revoked
	 */
	private function render_terminal( array $status, $reason ) {
		$copy = array(
			'cancelled' => 'This license was cancelled and can no longer be activated.',
			'refunded'  => 'This license was refunded and can no longer be activated.',
			'revoked'   => 'This license was revoked and can no longer be activated.',
		);

		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--bad">'
			. '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> '
			. esc_html( ucfirst( $reason ) ) . '</p>';

		echo '<p>' . esc_html( isset( $copy[ $reason ] ) ? $copy[ $reason ] : LicenseMessages::for_reason( $reason ) ) . '</p>';

		$this->render_support_link();
		echo '</div>';
	}

	private function render_support_link() {
		if ( null !== $this->args['support_url'] && '' !== $this->args['support_url'] ) {
			echo '<p><a href="' . esc_url( $this->args['support_url'] ) . '" target="_blank" rel="noopener noreferrer" class="button">'
				. esc_html( 'Contact support' ) . '</a></p>';
		}
	}

	// -- Unreachable -------------------------------------------------------

	/** @param array<string, mixed> $status */
	private function render_unreachable_last_known_valid( array $status ) {
		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--info">'
			. '<span class="dashicons dashicons-warning" aria-hidden="true"></span> '
			. esc_html( 'Active (last known status)' ) . '</p>';

		echo '<p>' . esc_html(
			'We could not reach the license server just now, so ' . $this->product_name()
			. ' is running on the last confirmed status. Nothing is broken and no action is needed — '
			. 'it checks again automatically.'
		) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row( 'License key', esc_html( $status['license_key'] ) );

		if ( null !== $status['last_checked_at'] ) {
			$this->row( 'Last confirmed', esc_html( $this->format_time( (int) $status['last_checked_at'] ) ) );
		}

		$this->row( 'Next check', esc_html( $this->retry_text( $status ) ) );
		echo '</tbody></table>';
		echo '</div>';
	}

	/** @param array<string, mixed> $status */
	private function render_unreachable_never_confirmed( array $status ) {
		echo '<div class="appneck-sdk-license-card">';
		echo '<p class="appneck-sdk-license-status appneck-sdk-license-status--warn">'
			. '<span class="dashicons dashicons-warning" aria-hidden="true"></span> '
			. esc_html( 'Could not verify' ) . '</p>';

		echo '<p>' . esc_html(
			'We have not been able to confirm this license key with the server yet. '
			. 'Please check that this site can make outgoing connections, and try again — '
			. 'we will also keep retrying automatically.'
		) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->row( 'License key', esc_html( $status['license_key'] ) );
		$this->row( 'Next check', esc_html( $this->retry_text( $status ) ) );
		echo '</tbody></table>';
		echo '</div>';
	}

	// -- Shared row/format helpers -----------------------------------------

	/** @param string $label */
	private function row( $label, $value_html ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value_html . '</td></tr>';
	}

	/** @param string|null $expires_at ISO 8601 or null. */
	private function expiry_html( $expires_at ) {
		if ( null === $expires_at || '' === $expires_at ) {
			return esc_html( 'Lifetime' );
		}

		$timestamp = strtotime( (string) $expires_at );

		if ( false === $timestamp ) {
			return esc_html( (string) $expires_at );
		}

		return esc_html( $this->format_date( $timestamp ) . ' (' . $this->relative_time( $timestamp ) . ')' );
	}

	/** @param array<string, mixed> $status */
	private function sites_used_text( array $status ) {
		$limit = $status['activation_limit'];
		$used  = null === $status['activations_used'] ? '?' : (string) $status['activations_used'];

		return $used . ' of ' . ( null === $limit ? 'Unlimited' : (string) $limit );
	}

	/** @param int $timestamp */
	private function relative_time( $timestamp ) {
		$diff = $timestamp - time();
		$past = $diff < 0;
		$diff = abs( $diff );

		if ( $diff < $this->day_in_seconds() ) {
			$unit  = 'day';
			$value = 1;
		} else {
			$months = (int) round( $diff / 2592000 ); // 30-day months, a label not an invoice.

			if ( $months < 1 ) {
				$value = (int) round( $diff / 86400 );
				$unit  = 'day';
			} elseif ( $months < 12 ) {
				$value = $months;
				$unit  = 'month';
			} else {
				$value = (int) round( $months / 12 );
				$unit  = 'year';
			}
		}

		$label = $value . ' ' . $unit . ( 1 === $value ? '' : 's' );

		return $past ? 'expired ' . $label . ' ago' : 'expires in ' . $label;
	}

	/** @param int $timestamp */
	private function format_date( $timestamp ) {
		if ( function_exists( 'date_i18n' ) && function_exists( 'get_option' ) ) {
			return (string) date_i18n( get_option( 'date_format', 'Y-m-d' ), $timestamp );
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/** @param int $timestamp */
	private function format_time( $timestamp ) {
		if ( function_exists( 'date_i18n' ) && function_exists( 'get_option' ) ) {
			$format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );

			return (string) date_i18n( $format, $timestamp );
		}

		return gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC';
	}

	/** @param array<string, mixed> $status */
	private function retry_text( array $status ) {
		if ( empty( $status['next_attempt_at'] ) ) {
			return 'shortly';
		}

		$seconds = (int) $status['next_attempt_at'] - time();

		if ( $seconds <= 0 ) {
			return 'shortly';
		}

		$minutes = (int) ceil( $seconds / 60 );

		if ( $minutes < 60 ) {
			return 'in about ' . $minutes . ' minute' . ( 1 === $minutes ? '' : 's' );
		}

		$hours = (int) ceil( $minutes / 60 );

		return 'in about ' . $hours . ' hour' . ( 1 === $hours ? '' : 's' );
	}

	// -----------------------------------------------------------------
	// Inline styling — one block, printed only on this page
	// -----------------------------------------------------------------

	private function render_style() {
		echo '<style>
.appneck-sdk-license-page .appneck-sdk-license-card{background:#fff;border:1px solid rgba(0,0,0,.25);border-radius:4px;padding:20px 24px;max-width:640px;margin-top:16px}
.appneck-sdk-license-page .appneck-sdk-license-status{font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px;margin:0 0 12px}
.appneck-sdk-license-page .appneck-sdk-license-status .dashicons{width:18px;height:18px;font-size:18px}
.appneck-sdk-license-page .appneck-sdk-license-status--good{color:#008a20}
.appneck-sdk-license-page .appneck-sdk-license-status--bad{color:#d63638}
.appneck-sdk-license-page .appneck-sdk-license-status--warn{color:#b45309}
.appneck-sdk-license-page .appneck-sdk-license-status--info{color:#50575e}
.appneck-sdk-license-page .appneck-sdk-license-form input[type=text]{margin-right:6px}
.appneck-sdk-license-page .appneck-sdk-license-meta{margin-top:12px;font-size:13px}
.appneck-sdk-license-page table.form-table{margin:0 0 16px}
.appneck-sdk-license-page table.form-table th{width:160px;padding-left:0}
.appneck-sdk-license-page table.form-table td{padding-left:0}
@media (max-width:782px){
.appneck-sdk-license-page .appneck-sdk-license-card{padding:16px;max-width:100%}
.appneck-sdk-license-page table.form-table th{width:auto;display:block;padding-bottom:2px}
.appneck-sdk-license-page table.form-table td{display:block;padding-top:0;padding-bottom:12px}
}
</style>';
	}

	// -----------------------------------------------------------------
	// Handling the click
	// -----------------------------------------------------------------

	/** The `admin_post_<action>` callback. */
	public function handle() {
		if ( ! $this->current_user_can_decide() ) {
			$this->deny( 'You are not allowed to change this setting.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->action() ) ) {
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
			$this->write_flash(
				array(
					'ok'      => false,
					'message' => 'Please enter a license key.',
				)
			);
			$this->redirect_back();

			return null;
		}

		$response = $this->license->activate( $key );
		$data     = $response->data();

		if ( $response->ok() && ! empty( $data['valid'] ) ) {
			$this->write_flash(
				array(
					'ok'      => true,
					'message' => 'License activated.',
				)
			);
		} elseif ( $response->ok() && isset( $data['reason'] ) ) {
			// A definitive rejection — the server answered, it said no.
			// activation_limit_reached gets its own actionable sentence;
			// the shared map is a statement of fact, not instructions.
			$reason  = (string) $data['reason'];
			$message = LicenseMessages::for_reason( $reason );

			if ( 'activation_limit_reached' === $reason ) {
				$message .= ' Deactivate it on a site you no longer use, or upgrade to a plan with more sites.';
			}

			$this->write_flash(
				array(
					'ok'      => false,
					'message' => $message,
				)
			);
		} else {
			// No definitive answer at all — a transport failure or a
			// non-JSON response. Nothing was stored (License::activate()
			// only writes on success), so get_status() will show
			// "no license" again after this redirect; the flash is the
			// ONLY place this specific moment is ever communicated.
			$this->write_flash(
				array(
					'ok'      => false,
					'message' => 'We could not verify this license key — the license server could not be reached. '
						. 'Please check your connection and try again.',
				)
			);
		}

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

		// deactivate_stored(), never a key from $_POST — see
		// LicenseForm::handle_deactivate()'s identical reasoning: the
		// page renders only a masked key, so there is nothing legitimate
		// for the form to carry back, and License exposes no getter for
		// the raw one.
		$response = $this->license->deactivate_stored();

		$this->write_flash(
			array(
				'ok'      => $response->ok(),
				'message' => $response->ok()
					? 'License deactivated on this site.'
					: 'We could not confirm the deactivation with the server — please try again.',
			)
		);

		$this->redirect_back();

		return self::OP_DEACTIVATE;
	}

	// -----------------------------------------------------------------
	// The post-redirect flash
	// -----------------------------------------------------------------

	/** @param array<string, mixed> $payload */
	private function write_flash( array $payload ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $this->flash_key(), $payload, self::FLASH_TTL );
		}
	}

	/** @return array<string, mixed>|null */
	private function read_flash() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}

		$flash = get_transient( $this->flash_key() );

		if ( ! is_array( $flash ) ) {
			return null;
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->flash_key() );
		}

		return $flash;
	}

	/**
	 * Per-user: two admins on the same site submitting at the same
	 * moment must not see each other's result.
	 */
	private function flash_key() {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return 'appneck_sdk_license_flash_' . $this->license->key() . '_' . $user;
	}

	/** @param array<string, mixed> $flash */
	private function render_flash( array $flash ) {
		$class = ! empty( $flash['ok'] ) ? 'notice-success' : 'notice-error';

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>'
			. esc_html( isset( $flash['message'] ) ? (string) $flash['message'] : '' ) . '</p></div>';
	}

	// -----------------------------------------------------------------
	// Form plumbing
	// -----------------------------------------------------------------

	/** @param string $operation */
	private function render_hidden_fields( $operation ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->action() ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::FIELD_OPERATION ) . '" value="' . esc_attr( $operation ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->action() );
		}
	}

	private function redirect_back() {
		$url = $this->page_url();

		if ( '' === $url && function_exists( 'wp_get_referer' ) ) {
			$referer = wp_get_referer();
			$url     = is_string( $referer ) && '' !== $referer ? $referer : '';
		}

		if ( '' === $url ) {
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

		return (bool) current_user_can( isset( $this->args['capability'] ) ? $this->args['capability'] : 'manage_options' );
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
		return null !== $this->product_name && '' !== $this->product_name ? $this->product_name : 'This plugin';
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

	/**
	 * The confirm() string for the Deactivate button — WordPress's own
	 * wp_json_encode() where available (matches DeactivationSurvey's
	 * convention), a plain json_encode() fallback for this package's
	 * non-WordPress tests.
	 *
	 * @param string $text
	 * @return string
	 */
	private function confirm_text( $text ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return (string) wp_json_encode( $text );
		}

		return (string) json_encode( $text );
	}

	/**
	 * WordPress defines DAY_IN_SECONDS as a bare global constant; this
	 * package's non-WordPress tests do not load wp-includes.
	 *
	 * @return int
	 */
	private function day_in_seconds() {
		return defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
	}
}
