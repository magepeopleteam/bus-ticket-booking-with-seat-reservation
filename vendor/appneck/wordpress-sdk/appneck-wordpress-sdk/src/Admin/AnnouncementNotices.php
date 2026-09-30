<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\Announcements;
use Appneck\Sdk\Client;
use Appneck\Sdk\RealtimeConfig;

/**
 * Announcements as the site owner sees them: admin notices on the
 * plugin's OWN screen.
 *
 * ## Not a global admin notice
 *
 * Nothing here hooks `admin_notices` unconditionally. A discount from one
 * plugin's vendor has no business appearing on the Media Library, the post
 * editor, or another plugin's settings page, and a site running five
 * Appneck-instrumented plugins would stack five vendors' messages on every
 * screen. So the host plugin says where:
 *
 *     // inside the plugin's own settings page callback
 *     $sdk->announcement_notices()->render();
 *
 *     // or, hooked once, printed only on that screen
 *     $sdk->announcement_notices()->render_on_screen( 'settings_page_acme' );
 *
 * ## All of them, urgent first — not one at a time
 *
 * Every undismissed announcement is rendered, ordered by type urgency and
 * then by the server's own recency (see Announcements::visible()). One at a
 * time with pagination was rejected: it needs state, nonces and a
 * "next" control for a list that is realistically zero to two items, and
 * it can queue a Security Notice behind a discount — the one outcome worth
 * actively avoiding. Stacking on a page the owner deliberately opened is
 * what WordPress itself does with notices.
 *
 * MAX_VISIBLE caps how many print at once so an organization publishing
 * ten cannot bury the plugin's actual settings under a wall of boxes; the
 * rest surface as earlier ones are dismissed.
 *
 * ## Dismissal is local for DISPLAY, and best-effort recorded for ANALYTICS
 *
 * What a site shows is still decided ENTIRELY by this site's own options
 * (see dismiss()/is_dismissed() below) — dismissing hides a notice
 * instantly, with zero network dependency, and it stays hidden even if
 * Appneck is completely unreachable (13-realtime-config-delivery.md §6.3).
 * handle_dismiss_ajax() ALSO fires a best-effort POST to
 * /sdk/v1/announcements/{id}/dismiss afterwards, purely so the org panel
 * can eventually see how many installations dismissed something instead
 * of acting on it — its failure changes nothing an admin can perceive.
 *
 * ## render_globally(): the widened default (Layer 2)
 *
 * render_on_screen() above — one screen, opt-in, refreshed only by the
 * cron tick or the once-an-hour fallback — is the ORIGINAL behaviour and
 * is left untouched for anything already calling it directly.
 *
 * render_globally() is the new default Sdk::bootstrap() wires instead: it
 * prints on every wp-admin page for a capable user, and it does so from
 * CACHE ONLY — no maybe_refresh(), no network call of any kind inside
 * admin_notices, because rendering must never be the thing a page load
 * waits on (13-realtime-config-delivery.md, Part 3 rule 2). Freshness
 * instead comes from a small script, printed once on admin_footer, that
 * calls a same-site admin-ajax endpoint after the page has already
 * painted — see handle_refresh_ajax() — and updates the notices in place.
 * The browser never talks to Appneck directly and the signing secret
 * never reaches JavaScript either way: the AJAX handler is the one making
 * the signed call, exactly like DeactivationSurvey's existing survey
 * fetch.
 *
 * The same script also polls handle_poll_ajax() every 60 seconds for
 * cheap urgent-or-not information (Layer 3) and fires
 * handle_dismiss_ajax() when a notice is dismissed.
 */
final class AnnouncementNotices {

	const ACTION_PREFIX = 'appneck_sdk_dismiss_announcement_';

	/** AJAX action prefixes for the global path — see render_globally(). */
	const ACTION_REFRESH_PREFIX = 'appneck_sdk_config_refresh_';
	const ACTION_POLL_PREFIX    = 'appneck_sdk_config_poll_';
	const ACTION_DISMISS_AJAX_PREFIX = 'appneck_sdk_dismiss_ajax_';

	/**
	 * Idle timeout for the urgent poll, and the single-flight window for
	 * both the refresh and poll endpoints — see the inline script.
	 *
	 * LOCK_TTL_SECONDS must stay LESS than POLL_INTERVAL_SECONDS, not
	 * greater — found by live E2E testing against a real WordPress site,
	 * not by the polyfilled suite. An earlier version set it to 65 (5
	 * seconds OVER the poll interval, on the reasoning that a slightly
	 * late request shouldn't slip through). That is backwards: the
	 * page-load refresh and the 60-second poll's own refresh are two
	 * INDEPENDENT triggers that share the same lock name, and a lock
	 * outliving the poll interval means the lock a page-load refresh
	 * took can still be held when the very next poll tick tries to pull
	 * a genuinely urgent notice — silently delaying it by however long
	 * the lock outlives the interval. Keeping the TTL strictly under the
	 * interval guarantees any lock has expired before the next
	 * independent trigger needs it, while still capping call frequency
	 * from a single source (e.g. rapid page loads) to roughly once per
	 * this many seconds, which is the guarantee rule 4 actually asks for.
	 */
	const POLL_INTERVAL_SECONDS = 60;
	const IDLE_TIMEOUT_SECONDS  = 1800; // 30 minutes
	const LOCK_TTL_SECONDS      = 50; // < POLL_INTERVAL_SECONDS, with margin for request latency/jitter

	const FIELD = 'appneck_sdk_announcement_id';

	/** How many notices print at once. */
	const MAX_VISIBLE = 3;

	/**
	 * type => WordPress's own notice class. Core's four levels already
	 * carry the right connotations, so there is no custom palette here —
	 * `security` reads as an error because that is the one a site owner
	 * must not skim past, and `discount` reads as good news.
	 */
	const NOTICE_CLASSES = array(
		'security' => 'notice-error',
		'update'   => 'notice-warning',
		'feature'  => 'notice-info',
		'discount' => 'notice-success',
	);

	/** @var Announcements */
	private $announcements;

	/** @var string */
	private $key;

	/** @var string|null */
	private $screen_id = null;

	/** @var callable|null */
	private $redirect_handler = null;

	/** @var RealtimeConfig|null */
	private $realtime_config;

	/** @var Client|null Short-timeout client for the AJAX-triggered calls. */
	private $fast_client;

	/**
	 * The last refusal, recorded only when WordPress's wp_die() is
	 * unavailable (this package's own tests) so it stays observable.
	 *
	 * @var string|null
	 */
	public $denied = null;

	/**
	 * @param RealtimeConfig|null $realtime_config Required for
	 *        render_globally()'s AJAX handlers; null is fine for anything
	 *        using only the original render_on_screen() path.
	 * @param Client|null         $fast_client A short-timeout Client
	 *        (13-realtime-config-delivery.md Part 3 rule 3) for the poll
	 *        and the best-effort dismissal report — never the 10-second
	 *        default client every other SDK feature shares.
	 */
	public function __construct( Announcements $announcements, $key, ?RealtimeConfig $realtime_config = null, ?Client $fast_client = null ) {
		$this->announcements   = $announcements;
		$this->key             = (string) $key;
		$this->realtime_config = $realtime_config;
		$this->fast_client     = $fast_client;
	}

	public function action() {
		return self::ACTION_PREFIX . $this->key;
	}

	public function refresh_action() {
		return self::ACTION_REFRESH_PREFIX . $this->key;
	}

	public function poll_action() {
		return self::ACTION_POLL_PREFIX . $this->key;
	}

	public function dismiss_ajax_action() {
		return self::ACTION_DISMISS_AJAX_PREFIX . $this->key;
	}

	/**
	 * Registers only the dismissal handler. Rendering is opt-in by screen —
	 * see the class doc for why this does not hook `admin_notices` itself.
	 */
	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'admin_post_' . $this->action(), array( $this, 'handle_dismiss' ) );
		add_action( 'wp_ajax_' . $this->refresh_action(), array( $this, 'handle_refresh_ajax' ) );
		add_action( 'wp_ajax_' . $this->poll_action(), array( $this, 'handle_poll_ajax' ) );
		add_action( 'wp_ajax_' . $this->dismiss_ajax_action(), array( $this, 'handle_dismiss_ajax' ) );
	}

	/**
	 * The widened default (Layer 2/3) — see the class doc. Prints on
	 * EVERY wp-admin page for a capable user, from cache only, and
	 * enqueues the script that keeps it fresh without ever blocking the
	 * page that printed it.
	 */
	public function render_globally() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render_from_cache_only' ) );
			add_action( 'admin_footer', array( $this, 'print_refresh_script' ) );
		}

		return $this;
	}

	/**
	 * Print the notices on one specific admin screen.
	 *
	 * @param string $screen_id e.g. 'settings_page_acme', or
	 *                          'toplevel_page_acme'. get_current_screen()->id.
	 */
	public function render_on_screen( $screen_id ) {
		$this->screen_id = (string) $screen_id;

		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render_if_on_screen' ) );
		}

		return $this;
	}

	/** For tests and hosts that render their own confirmation. */
	public function set_redirect_handler( ?callable $handler ) {
		$this->redirect_handler = $handler;

		return $this;
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	/** The `admin_notices` callback installed by render_on_screen(). */
	public function render_if_on_screen() {
		if ( null === $this->screen_id || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || ! isset( $screen->id ) || $screen->id !== $this->screen_id ) {
			return;
		}

		$this->render();
	}

	/**
	 * Prints the notices. Safe to call with nothing to show — it prints
	 * absolutely nothing rather than an empty container, so a plugin can
	 * call it unconditionally without leaving a stray box on the page.
	 */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		// The WP-Cron fallback, and the only place it runs: the owner is
		// already on the plugin's own screen, so at most one API call an
		// hour here is acceptable in a way it would not be anywhere else.
		$this->announcements->maybe_refresh();

		$visible = $this->announcements->visible();

		if ( array() === $visible ) {
			return;
		}

		$printed = 0;

		foreach ( $visible as $announcement ) {
			if ( $printed >= self::MAX_VISIBLE ) {
				break;
			}

			$this->render_one( $announcement );
			++$printed;
		}

		$this->render_notice_style();
	}

	/** @param array<string, mixed> $announcement */
	private function render_one( array $announcement ) {
		$class = $this->notice_class( $announcement['type'] );

		echo '<div class="notice ' . esc_attr( $class ) . ' appneck-sdk-announcement">';
		echo $this->notice_icon( $announcement['type'] ); // phpcs:ignore -- static, non-user markup.
		echo '<div class="appneck-sdk-announcement__content">';
		echo '<span class="appneck-sdk-announcement__badge">' . esc_html( $this->notice_label( $announcement['type'] ) ) . '</span>';
		echo '<p class="appneck-sdk-announcement__title">' . esc_html( $announcement['title'] ) . '</p>';

		if ( '' !== $announcement['body'] ) {
			// esc_html FIRST, then nl2br on the escaped string, so line
			// breaks survive without any tag from the server surviving with
			// them. journal §12.2 makes this content display-only; letting
			// remote HTML into wp-admin would quietly undo that.
			echo '<p class="appneck-sdk-announcement__body">' . nl2br( esc_html( $announcement['body'] ) ) . '</p>';
		}

		echo '<form method="post" action="' . esc_url( $this->post_url() ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $this->action() ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $announcement['id'] ) . '" />';

		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $this->action() );
		}

		// A plain submit rather than core's dismissible X: the X is added
		// by core's own JS to `.notice.is-dismissible` and only hides the
		// box for that page view, which is the opposite of what a stored
		// dismissal means. Two dismiss controls where one is a lie is
		// worse than one that is honest.
		echo '<button type="submit" class="appneck-sdk-announcement__dismiss">' . esc_html( 'Dismiss' ) . '</button>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	// -----------------------------------------------------------------
	// The global path: cache-only render, AJAX refresh, poll, dismiss
	// -----------------------------------------------------------------

	/**
	 * The `admin_notices` callback render_globally() installs. Reads
	 * ONLY the local cache — no maybe_refresh(), no network call of any
	 * kind — because this runs on every wp-admin page load and rendering
	 * must never be the thing that page load waits on.
	 *
	 * Always prints the container, even with nothing inside it, so
	 * print_refresh_script()'s injection target exists on every page —
	 * an urgent notice that appears between two page loads needs
	 * somewhere to be injected INTO without a reload.
	 */
	public function render_from_cache_only() {
		if ( ! $this->can_render() ) {
			return;
		}

		$visible = $this->announcements->visible();

		// Seeded from RealtimeConfig's own last-known answer, not
		// hardcoded to "0" — otherwise the very first poll after this
		// page load would see data-has-urgent="0" and immediately
		// re-fetch an urgent notice that was already sitting in the
		// initial paint, having mistaken "never asked" for "not urgent".
		$has_urgent = null !== $this->realtime_config
			? (bool) $this->realtime_config->cached_poll()['has_urgent']
			: false;

		echo '<div id="appneck-sdk-announcements-' . esc_attr( $this->key ) . '" class="appneck-sdk-announcements" data-has-urgent="' . ( $has_urgent ? '1' : '0' ) . '">';
		echo $this->render_notices_html( $visible ); // phpcs:ignore -- already escaped per-field in render_notice_for_ajax().
		echo '</div>';
	}

	/**
	 * The shared markup builder behind BOTH render_from_cache_only()'s
	 * initial paint and handle_refresh_ajax()'s later response, so a
	 * notice looks and behaves identically whichever one produced it —
	 * the same reasoning DeactivationSurvey's JS-rendered questions and
	 * AnnouncementNotices' server-rendered ones already follow elsewhere
	 * in this package.
	 *
	 * Deliberately NOT render_one(): that method builds a full
	 * admin-post.php POST form for the original opt-in path, which stays
	 * exactly as it was for anything still calling render_on_screen().
	 * This one is a plain button the inline script dismisses via AJAX.
	 *
	 * @param array<int, array<string, mixed>> $visible
	 */
	private function render_notices_html( array $visible ) {
		ob_start();

		$printed = 0;

		foreach ( $visible as $announcement ) {
			if ( $printed >= self::MAX_VISIBLE ) {
				break;
			}

			$this->render_notice_for_ajax( $announcement );
			++$printed;
		}

		$html = ob_get_clean();

		return false === $html ? '' : $html;
	}

	/**
	 * Deliberately NOT the literal `notice` class core's own
	 * `wp-admin/js/common.js` looks for (`div.updated, div.error,
	 * div.notice`) — only `notice-error`/`-warning`/`-info`/`-success`,
	 * which this component's own CSS already keys off and which core's
	 * selector does not match (it looks for the exact `notice` token,
	 * not a `notice-*` prefix).
	 *
	 * A real bug shipped here once: with the literal `notice` class
	 * present, core's relocation script — which runs exactly ONCE, on
	 * page ready — would grab this element and move it to right after
	 * the screen's `<h1>`. The page-load background refresh
	 * (print_refresh_script()'s unconditional `refresh()` call) then
	 * replaces THIS element's parent container's innerHTML with a
	 * fresh copy from the server. If that AJAX response arrives after
	 * core's one-time relocation already ran, the ORIGINAL notice is
	 * already sitting after the `<h1>`, and the freshly-injected copy
	 * lands back in the container's original, pre-relocation position
	 * — two visible copies of the same notice, in two different
	 * places, appearing only once the refresh resolves (which is why
	 * it looked instant-then-delayed rather than a same-request bug).
	 * Removing the literal `notice` token means core never touches
	 * this element at all, so there is nothing left to race.
	 *
	 * @param array<string, mixed> $announcement
	 */
	private function render_notice_for_ajax( array $announcement ) {
		$class = $this->notice_class( $announcement['type'] );

		echo '<div class="' . esc_attr( $class ) . ' appneck-sdk-announcement" data-appneck-announcement-id="' . esc_attr( $announcement['id'] ) . '">';
		echo $this->notice_icon( $announcement['type'] ); // phpcs:ignore -- static, non-user markup.
		echo '<div class="appneck-sdk-announcement__content">';
		echo '<span class="appneck-sdk-announcement__badge">' . esc_html( $this->notice_label( $announcement['type'] ) ) . '</span>';
		echo '<p class="appneck-sdk-announcement__title">' . esc_html( $announcement['title'] ) . '</p>';

		if ( '' !== $announcement['body'] ) {
			echo '<p class="appneck-sdk-announcement__body">' . nl2br( esc_html( $announcement['body'] ) ) . '</p>';
		}

		echo '<button type="button" class="appneck-sdk-announcement__dismiss" data-appneck-dismiss="' . esc_attr( $announcement['id'] ) . '">' . esc_html( 'Dismiss' ) . '</button>';
		echo '</div>';
		echo '</div>';
	}

	/** @param string $type */
	private function notice_class( $type ) {
		return isset( self::NOTICE_CLASSES[ $type ] )
			? self::NOTICE_CLASSES[ $type ]
			// An unknown type from a newer server. Neutral rather than
			// guessed at — and never the urgent one.
			: 'notice-info';
	}

	/**
	 * The small colored badge word next to the title — plain sentence
	 * case, not the tracked-out ALL-CAPS eyebrow label wp-admin plugins
	 * tend to overuse. Names what the announcement actually is, the
	 * same job the icon and colour are already doing, so someone
	 * scanning several at once can sort by kind without reading either.
	 *
	 * @param string $type
	 */
	private function notice_label( $type ) {
		$labels = array(
			'security' => 'Security',
			'update'   => 'Update',
			'feature'  => 'New feature',
			'discount' => 'Offer',
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : 'Notice';
	}

	/**
	 * A small icon per type, matching what each one is actually telling
	 * the site owner — a shield for something to act on, a refresh arrow
	 * for a routine update, a spark for a new feature, a tag for a
	 * discount — rather than four identical boxes distinguished only by
	 * a thin left border. Static markup, no user data, so no escaping
	 * is needed; `aria-hidden` keeps it decorative (the type is already
	 * conveyed by the notice's own colour and, for a screen reader, by
	 * its content).
	 *
	 * @param string $type
	 */
	private function notice_icon( $type ) {
		$icons = array(
			'security' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v5.5c0 5-3.1 8-7 9.5-3.9-1.5-7-4.5-7-9.5V6l7-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 12.3l2.1 2.1L15.3 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'update'   => '<svg viewBox="0 0 24 24" fill="none"><path d="M4.5 12a7.5 7.5 0 0 1 12.6-5.5M19.5 12a7.5 7.5 0 0 1-12.6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M17 3.5v3.5h-3.5M7 20.5V17h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			'feature'  => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M17.5 15.5 20 18M18 6l-2.5 2.5M8.5 15.5 6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.6"/></svg>',
			'discount' => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 12.5V6a1 1 0 0 1 1-1h6.5a1 1 0 0 1 .7.3l7 7a1 1 0 0 1 0 1.4l-6.5 6.5a1 1 0 0 1-1.4 0l-7-7a1 1 0 0 1-.3-.7z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="8.2" cy="8.2" r="1.3" stroke="currentColor" stroke-width="1.4"/></svg>',
		);

		$icon = isset( $icons[ $type ] ) ? $icons[ $type ] : '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 10.5v6M12 7.5v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

		return '<div class="appneck-sdk-announcement__icon" aria-hidden="true">' . $icon . '</div>';
	}

	/**
	 * Printed once per page — inside render_one()/render() for the
	 * original opt-in path (only when there is something to show), and
	 * from print_refresh_script() for the default global path, since
	 * that runs on admin_footer exactly once per page load and, unlike
	 * the announcements container, is never overwritten by an AJAX
	 * refresh's innerHTML swap.
	 */
	private function render_notice_style() {
		echo '<style>
.appneck-sdk-announcement{display:flex!important;align-items:flex-start;gap:14px;margin:0 0 16px!important;padding:16px 18px!important;border-radius:10px;border-left-width:0!important;box-shadow:0 1px 3px rgba(16,20,26,.08)}
.appneck-sdk-announcement__icon{flex:0 0 auto;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:1px;color:#fff;box-shadow:0 2px 5px rgba(16,20,26,.18)}
.appneck-sdk-announcement__icon svg{width:18px;height:18px}
.appneck-sdk-announcement__content{flex:1 1 auto;min-width:0;padding-top:2px}
.appneck-sdk-announcement__badge{display:inline-block;margin:0 0 6px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;line-height:1.6}
.appneck-sdk-announcement__title{margin:0 0 3px!important;font-size:14.5px;font-weight:700;color:#1d2327}
.appneck-sdk-announcement__body{margin:0 0 8px!important;font-size:13px;color:#3c434a;line-height:1.55}
.appneck-sdk-announcement__dismiss{background:none!important;border:0!important;padding:0!important;margin:0!important;font-size:12.5px;font-weight:600;color:#5f6773;cursor:pointer;text-decoration:underline}
.appneck-sdk-announcement__dismiss:hover{color:#1d2327}

.appneck-sdk-announcement.notice-error{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%)}
.appneck-sdk-announcement.notice-error .appneck-sdk-announcement__icon,
.appneck-sdk-announcement.notice-error .appneck-sdk-announcement__badge{background:#dc2626}
.appneck-sdk-announcement.notice-error .appneck-sdk-announcement__title{color:#991b1b}

.appneck-sdk-announcement.notice-warning{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)}
.appneck-sdk-announcement.notice-warning .appneck-sdk-announcement__icon,
.appneck-sdk-announcement.notice-warning .appneck-sdk-announcement__badge{background:#d97706}
.appneck-sdk-announcement.notice-warning .appneck-sdk-announcement__title{color:#92400e}

.appneck-sdk-announcement.notice-info{background:linear-gradient(135deg,#dbeafe 0%,#bfdbfe 100%)}
.appneck-sdk-announcement.notice-info .appneck-sdk-announcement__icon,
.appneck-sdk-announcement.notice-info .appneck-sdk-announcement__badge{background:#2563eb}
.appneck-sdk-announcement.notice-info .appneck-sdk-announcement__title{color:#1e3a8a}

.appneck-sdk-announcement.notice-success{background:linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%)}
.appneck-sdk-announcement.notice-success .appneck-sdk-announcement__icon,
.appneck-sdk-announcement.notice-success .appneck-sdk-announcement__badge{background:#16a34a}
.appneck-sdk-announcement.notice-success .appneck-sdk-announcement__title{color:#14532d}
</style>';
	}

	/**
	 * The one script this class prints, on `admin_footer`, for a capable
	 * user, on every wp-admin page. ES5/XMLHttpRequest, no build step —
	 * same convention as DeactivationSurvey's script.
	 *
	 * Everything remote it triggers happens strictly AFTER this prints,
	 * inside the admin-ajax calls below — never during the page's own
	 * render, which is already long finished by the time admin_footer
	 * fires.
	 */
	public function print_refresh_script() {
		if ( ! $this->can_render() || ! function_exists( 'wp_create_nonce' ) ) {
			return;
		}

		$cfg = array(
			'ajaxUrl'         => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '/wp-admin/admin-ajax.php',
			'refreshAction'   => $this->refresh_action(),
			'refreshNonce'    => wp_create_nonce( $this->refresh_action() ),
			'pollAction'      => $this->poll_action(),
			'pollNonce'       => wp_create_nonce( $this->poll_action() ),
			'dismissAction'   => $this->dismiss_ajax_action(),
			'dismissNonce'    => wp_create_nonce( $this->dismiss_ajax_action() ),
			'containerId'     => 'appneck-sdk-announcements-' . $this->key,
			'pollIntervalMs'  => self::POLL_INTERVAL_SECONDS * 1000,
			'idleTimeoutMs'   => self::IDLE_TIMEOUT_SECONDS * 1000,
		);

		// Printed here, once per page (admin_footer), rather than inside
		// render_from_cache_only()'s container — that container's
		// innerHTML is replaced wholesale on every AJAX refresh/poll, so
		// a <style> tag placed inside it would vanish the first time an
		// urgent notice arrived and get silently re-added forever after.
		$this->render_notice_style();

		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $cfg ) : json_encode( $cfg );

		echo '<script>(function(){
var cfg = ' . $json . ';
var container = document.getElementById(cfg.containerId);
if (!container) { return; }

// Position the container ourselves, once, right where WordPress core
// own wp-admin/js/common.js would have moved a literal .notice element
// to (right after the screen own <h1>/<h2>) -- deliberately not
// relying on core to do this: that relocation runs exactly once, on
// page ready, and if it ran on OUR element the page-load refresh below
// (which replaces this container content once its own AJAX response
// arrives, on no fixed schedule) could lose the race and leave a
// second, un-relocated copy behind. This script tag prints in
// admin_footer, well after the whole .wrap -- including its heading --
// has already been parsed, so this move is synchronous and has nothing
// left to race.
(function () {
	var wrap = document.querySelector(".wrap");
	var heading = wrap ? wrap.querySelector("h1, h2") : null;
	if (heading && heading.parentNode && container.parentNode !== heading.parentNode) {
		heading.parentNode.insertBefore(container, heading.nextSibling);
	}
})();

var lastVersion = null;
var refreshedForVersion = null;
var lastInteractionAt = Date.now();
var pollTimer = null;

function onInteraction() { lastInteractionAt = Date.now(); }
document.addEventListener("click", onInteraction, false);
document.addEventListener("keydown", onInteraction, false);
document.addEventListener("scroll", onInteraction, false);
document.addEventListener("mousemove", onInteraction, false);

function post(action, nonce, extra, done) {
	var xhr = new XMLHttpRequest();
	xhr.open("POST", cfg.ajaxUrl, true);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
	xhr.timeout = 3000;
	xhr.onreadystatechange = function () {
		if (xhr.readyState !== 4) { return; }
		var parsed = null;
		try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
		done(xhr.status >= 200 && xhr.status < 300, parsed);
	};
	// Fails open and silent (rule 6): a transport error/timeout never
	// throws here and never surfaces anything to the admin — the
	// onreadystatechange above simply never reaches readyState 4, or
	// reaches it with ok=false, and every done() callback below already
	// treats that as "nothing changed" rather than an error to show.
	var body = "action=" + encodeURIComponent(action) + "&nonce=" + encodeURIComponent(nonce) + (extra || "");
	xhr.send(body);
}

function applyHtml(html, hasUrgent) {
	container.innerHTML = html;
	container.setAttribute("data-has-urgent", hasUrgent ? "1" : "0");
}

function refresh(refreshingForVersion) {
	post(cfg.refreshAction, cfg.refreshNonce, "", function (ok, data) {
		if (!ok || !data || !data.success || !data.data) { return; }
		applyHtml(data.data.html || "", data.data.has_urgent);
		// Only latch on confirmed success: a failed/timed-out refresh
		// must not mark this version as "handled", or the NEXT poll
		// tick (which would otherwise retry) would wrongly skip it too.
		if (undefined !== refreshingForVersion) { refreshedForVersion = refreshingForVersion; }
	});
}

function poll() {
	post(cfg.pollAction, cfg.pollNonce, "", function (ok, data) {
		if (!ok || !data || !data.success || !data.data) { return; }

		var version = data.data.config_version;
		var hasUrgent = !!data.data.has_urgent;

		// hasUrgent is checked on EVERY tick, including the first one
		// after page load — not gated on lastVersion having already
		// been seen once. An urgent notice published moments after this
		// script starts must be caught on the very next 60-second tick,
		// not the one after that: gating on "a version change we can
		// detect" would need two ticks (one to record the baseline, one
		// to notice the change), silently doubling the worst-case delay
		// for the one case Layer 3 exists to make fast.
		//
		// The idempotency check is refreshedForVersion, a version
		// number, NOT the container own data-has-urgent attribute. A
		// real bug shipped with the DOM-attribute version: once ANY
		// urgent notice had ever been shown once during a page visit,
		// data-has-urgent latched to "1" and nothing ever cleared it
		// back to "0" — dismissing or deleting that notice removes the
		// DOM node but never touches the attribute, and no further
		// page-load refresh happens without a navigation this tab may
		// never make. So a SECOND, later, unrelated urgent announcement
		// (a config_version bump has_urgent still reports true for) was
		// silently skipped forever, for as long as that tab stayed open —
		// found by a randomized-delay repeat of E2E scenario A publishing
		// a second urgent item into an already-urgent-once session, not
		// by any single best-case run. Comparing against the specific
		// version already refreshed for fixes this: a new version number
		// always re-triggers, the same version never does, and a failed
		// refresh version is never latched (see refresh() above), so
		// it retries on the very next tick rather than skipping forever.
		if (hasUrgent && version !== refreshedForVersion) {
			refresh(version);
		}

		// lastVersion still records every tick, kept for any future
		// caller that needs to know whether the version changed since
		// the last tick for a non-urgent reason, though nothing here
		// reads it back for that today; a non-urgent version bump
		// deliberately waits for the next page load (Layer 2) rather
		// than being acted on from the poll at all.
		lastVersion = version;
	});
}

document.addEventListener("click", function (event) {
	var el = event.target;
	while (el && el !== container && el.getAttribute) {
		var id = el.getAttribute("data-appneck-dismiss");
		if (id) {
			// Hidden INSTANTLY, before the network call even starts —
			// zero network dependency for what the admin sees.
			var notice = el;
			while (notice && notice !== container && (" " + notice.className + " ").indexOf(" appneck-sdk-announcement ") === -1) {
				notice = notice.parentNode;
			}
			if (notice && notice.parentNode) { notice.parentNode.removeChild(notice); }

			post(cfg.dismissAction, cfg.dismissNonce, "&" + encodeURIComponent("id") + "=" + encodeURIComponent(id), function () {});
			return;
		}
		el = el.parentNode;
	}
}, false);

function schedulePoll() {
	if (pollTimer) { return; }
	pollTimer = setInterval(function () {
		if (Date.now() - lastInteractionAt > cfg.idleTimeoutMs) {
			clearInterval(pollTimer);
			pollTimer = null;
			return;
		}
		poll();
	}, cfg.pollIntervalMs);
}

function stopPoll() {
	if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

document.addEventListener("visibilitychange", function () {
	if (document.hidden) {
		stopPoll();
	} else {
		lastInteractionAt = Date.now();
		poll();
		schedulePoll();
	}
}, false);

refresh();
schedulePoll();
})();</script>';
	}

	/**
	 * wp_ajax_<refresh_action> — the async refresh a page load never
	 * waits on. Single-flight: at most one of any number of concurrent
	 * callers (across tabs, across page loads) actually reaches the
	 * network; every other one falls straight through to returning
	 * whatever is already cached.
	 */
	public function handle_refresh_ajax() {
		if ( ! $this->current_user_can_dismiss() ) {
			return $this->ajax_fail( 'You are not allowed to do that.' );
		}

		if ( function_exists( 'check_ajax_referer' ) && ! check_ajax_referer( $this->refresh_action(), 'nonce', false ) ) {
			return $this->ajax_fail( 'That page has expired. Please reload and try again.' );
		}

		$needs_refresh = null !== $this->realtime_config && $this->realtime_config->is_stale();

		if ( ! $needs_refresh ) {
			$fetched_at = (int) $this->announcements->fetched_at();
			$needs_refresh = 0 === $fetched_at || ( time() - $fetched_at ) >= self::POLL_INTERVAL_SECONDS;
		}

		// The lock is deliberately NOT released after a successful refresh.
		// Its job is to enforce a MINIMUM INTERVAL between upstream calls
		// (rule 4: "enforces a minimum interval... using a transient
		// lock"), not merely to stop two calls landing in the same
		// instant — releasing early would let the very next caller,
		// milliseconds later, acquire it again and make a second upstream
		// call inside the same window. It expires on its own after
		// LOCK_TTL_SECONDS and is left to do so.
		if ( $needs_refresh && null !== $this->realtime_config
			&& $this->realtime_config->acquire_lock( 'refresh', self::LOCK_TTL_SECONDS ) ) {
			$this->announcements->refresh();
		}
		// Lock not acquired, or no refresh needed: falls straight through
		// to returning whatever is cached — which is either already
		// current, or is about to be made current by whichever caller DID
		// get the lock, within the same 60-second window.

		$visible = $this->announcements->visible();

		// The announcements payload never carries is_urgent per item —
		// journal §12.2 deliberately keeps that endpoint free of any
		// field an SDK could act on (13-realtime-config-delivery.md
		// §6.1/§5.1). Whether ANYTHING currently visible is urgent is
		// answered only by the poll endpoint's has_urgent, so this reads
		// whatever RealtimeConfig last learned from it rather than
		// inspecting $visible for a field that was never there.
		$has_urgent = null !== $this->realtime_config
			? (bool) $this->realtime_config->cached_poll()['has_urgent']
			: false;

		return $this->ajax_send(
			array(
				'html'       => $this->render_notices_html( $visible ),
				'has_urgent' => $has_urgent,
			)
		);
	}

	/**
	 * wp_ajax_<poll_action> — the 60-second poll. Single-flight the same
	 * way as the refresh above; a caller that loses the race reads
	 * RealtimeConfig's own cached poll entry rather than making a second
	 * upstream call this same window.
	 */
	public function handle_poll_ajax() {
		if ( ! $this->current_user_can_dismiss() ) {
			return $this->ajax_fail( 'You are not allowed to do that.' );
		}

		if ( function_exists( 'check_ajax_referer' ) && ! check_ajax_referer( $this->poll_action(), 'nonce', false ) ) {
			return $this->ajax_fail( 'That page has expired. Please reload and try again.' );
		}

		if ( null === $this->realtime_config ) {
			return $this->ajax_send( array( 'config_version' => null, 'has_urgent' => false ) );
		}

		// Same reasoning as handle_refresh_ajax(): the lock is held for its
		// full TTL, not released right after use, because its job is to
		// cap how OFTEN this site calls Appneck, not just to serialise
		// simultaneous callers.
		if ( null !== $this->fast_client
			&& $this->realtime_config->acquire_lock( 'poll', self::LOCK_TTL_SECONDS ) ) {
			$entry = $this->realtime_config->poll( $this->fast_client );
		} else {
			$entry = $this->realtime_config->cached_poll();
		}

		return $this->ajax_send( $entry );
	}

	/**
	 * wp_ajax_<dismiss_ajax_action> — instant local hide, best-effort
	 * remote record (13-realtime-config-delivery.md §6.3). The inline
	 * script already hid the element before this request was even sent;
	 * everything here is about persistence, not what the admin sees.
	 */
	public function handle_dismiss_ajax() {
		if ( ! $this->current_user_can_dismiss() ) {
			return $this->ajax_fail( 'You are not allowed to do that.' );
		}

		if ( function_exists( 'check_ajax_referer' ) && ! check_ajax_referer( $this->dismiss_ajax_action(), 'nonce', false ) ) {
			return $this->ajax_fail( 'That page has expired. Please reload and try again.' );
		}

		$id = isset( $_POST['id'] ) ? $this->sanitize_id( $_POST['id'] ) : '';

		if ( '' === $id || ! $this->announcements->dismiss( $id ) ) {
			// Same reasoning as the form-based handler: an unknown id is
			// most likely a stale page whose announcement has already
			// expired, not something worth erroring over — the admin's
			// click already got what it wanted, the notice is gone either
			// way.
			return $this->ajax_send( array( 'dismissed' => false ) );
		}

		// Best-effort, fire-and-forget from the CALLER's point of view —
		// but made here, server-side, over the signed fast client, never
		// from the browser (rule 1: the secret never reaches JavaScript).
		// Failure is deliberately not inspected: nothing about it changes
		// this response, which has already succeeded on the local
		// dismissal alone.
		if ( null !== $this->fast_client ) {
			$this->fast_client->post( '/sdk/v1/announcements/' . rawurlencode( $id ) . '/dismiss' );
		}

		return $this->ajax_send( array( 'dismissed' => true ) );
	}

	/** @param array<string, mixed> $data */
	private function ajax_send( array $data ) {
		if ( function_exists( 'wp_send_json_success' ) ) {
			wp_send_json_success( $data );
		}

		return $data;
	}

	/** @param string $message */
	private function ajax_fail( $message ) {
		$this->denied = (string) $message;

		if ( function_exists( 'wp_send_json_error' ) ) {
			wp_send_json_error( array( 'message' => $message ) );
		}

		return null;
	}

	// -----------------------------------------------------------------
	// Dismissal
	// -----------------------------------------------------------------

	/**
	 * The `admin_post_<action>` callback.
	 *
	 * @return string|null The dismissed id, or null when refused.
	 */
	public function handle_dismiss() {
		if ( ! $this->current_user_can_dismiss() ) {
			$this->deny( 'You are not allowed to do that.' );

			return null;
		}

		if ( function_exists( 'check_admin_referer' ) && false === check_admin_referer( $this->action() ) ) {
			$this->deny( 'That page has expired. Please reload and try again.' );

			return null;
		}

		$id = isset( $_POST[ self::FIELD ] ) ? $this->sanitize_id( $_POST[ self::FIELD ] ) : '';

		if ( '' === $id || ! $this->announcements->dismiss( $id ) ) {
			// An unknown id is not worth a wp_die() — the likeliest cause is
			// a stale page whose announcement has since expired, and the
			// owner's click did what they wanted either way: it is gone.
			$this->denied = 'unknown announcement';
			$this->redirect_back();

			return null;
		}

		$this->redirect_back();

		return $id;
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

	// -----------------------------------------------------------------
	// Environment
	// -----------------------------------------------------------------

	/**
	 * `manage_options` — the capability that gates a plugin's settings
	 * screen, which is the only place these print. Dismissal is a stored
	 * decision for the whole site, so it belongs to whoever administers it.
	 */
	private function current_user_can_dismiss() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		return (bool) current_user_can( 'manage_options' );
	}

	private function can_render() {
		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'esc_attr' ) || ! function_exists( 'esc_url' ) ) {
			return false;
		}

		return $this->current_user_can_dismiss();
	}

	private function post_url() {
		return function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '/wp-admin/admin-post.php';
	}

	/** @param mixed $value */
	private function sanitize_id( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		// The ids are uuids. Anything else cannot match a cached
		// announcement anyway, and dismiss() rejects unknown ids — this
		// just keeps junk out of the comparison entirely.
		return preg_replace( '/[^a-zA-Z0-9\-]/', '', $value );
	}
}
