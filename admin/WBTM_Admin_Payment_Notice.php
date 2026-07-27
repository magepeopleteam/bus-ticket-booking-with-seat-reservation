<?php
/**
 * Payment-related admin notices for the Bus plugin.
 *
 * Ported from the sibling rental plugin's RBFW_Admin_Payment_Notice (the
 * "no gateway enabled for the active mode" warning) plus its root functions.php
 * rbfw_standalone_mode_notice()/dismiss-handler ("running in Standalone mode"
 * notice) — folded into one file here since the bus plugin has no root-level
 * functions.php the way the rental plugin does.
 *
 * Both notices share one modern card renderer (render_card()); the payment
 * warning leads with a Pro upsell in the one state where Pro is the actual fix.
 *
 * Availability logic lives in WBTM_Payment_Status_Checker; this class only
 * wires it into `admin_notices` and renders the markup, so the check itself
 * stays reusable and unit-testable outside of WordPress hooks.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WBTM_Admin_Payment_Notice' ) ) {

	class WBTM_Admin_Payment_Notice {

		/** Transient key: the payment warning is snoozed (not permanently hidden). */
		const PAYMENT_SNOOZE_KEY = 'wbtm_payment_notice_snoozed';

		/** @var WBTM_Payment_Status_Checker */
		private $checker;

		public function __construct( $checker = null ) {
			$this->checker = ( $checker instanceof WBTM_Payment_Status_Checker ) ? $checker : new WBTM_Payment_Status_Checker();
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'admin_notices', array( $this, 'render_standalone_notice' ) );
			add_action( 'admin_init', array( $this, 'handle_standalone_notice_dismiss' ) );
			add_action( 'admin_init', array( $this, 'handle_payment_notice_dismiss' ) );
		}

		/** Warning when the currently active booking mode has no usable gateway. */
		public function render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// The modern bus editor renders its own inline payment banner (below the
			// stepper) with a modal to fix the problem in place — showing this global
			// notice there too would be a duplicate warning.
			if ( $this->is_modern_bus_editor() ) {
				return;
			}

			// Mode-aware: warn when the system that actually owns bookings right now has
			// no usable gateway — even if the other (inactive) system does.
			if ( $this->checker->has_gateway_for_active_mode() ) {
				return;
			}

			// Snoozed by the admin. Kept as a time-boxed transient (not a permanent
			// option) on purpose: this warns that customers literally cannot pay, so it
			// must resurface if the site still has no gateway after the snooze window.
			if ( 'yes' === get_transient( self::PAYMENT_SNOOZE_KEY ) ) {
				return;
			}

			$mode      = $this->checker->active_mode();
			$is_pro    = class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_pro_active();
			$wc_active = class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_wc_active();

			// Standalone mode with no Pro: Offline is a FREE gateway, so the real fix is
			// to switch it on — not to buy anything. We lead with that and keep a light
			// PRO upsell (Stripe/PayPal) as a secondary link. In WooCommerce mode the fix
			// is a free WC gateway. Either way the notice never claims payments require Pro.
			$offline_first = ( 'standalone' === $mode && ! $is_pro );

			$msg = ( 'woocommerce' === $mode )
				? esc_html__( 'Bookings run through WooCommerce, but no WooCommerce payment gateway is enabled. Customers will not be able to complete bookings until you enable at least one.', 'bus-ticket-booking-with-seat-reservation' )
				: esc_html__( 'Bookings run through Custom Payment (Standalone), but no payment method is enabled yet. Customers will not be able to complete bookings until you enable at least one.', 'bus-ticket-booking-with-seat-reservation' );

			$this->render_card( array(
				'variant'     => 'warning',
				'icon'        => 'dashicons-money-alt',
				'badge'       => '',
				'title'       => WBTM_Functions::get_name(),
				'message'     => $msg,
				'lead'        => $offline_first ? __( 'Turn on the free Offline gateway (bank transfer, cash, pay on pickup) to start taking bookings right away — no WooCommerce required.', 'bus-ticket-booking-with-seat-reservation' ) : '',
				'benefits'    => array(),
				'primary'     => $this->primary_action( $mode, $is_pro ),
				'secondary'   => $this->secondary_action( $mode, $is_pro, $wc_active ),
				'dismiss_url' => $this->payment_dismiss_url(),
			) );
		}

		/**
		 * The prominent call-to-action button, driven by the active booking mode:
		 *  - WooCommerce mode → configure a WC gateway (free; WC is active in this mode).
		 *  - Standalone + Pro → configure the Pro payment methods.
		 *  - Standalone, no Pro → enable the FREE Offline gateway (the real fix).
		 */
		private function primary_action( $mode, $is_pro ) {
			if ( 'woocommerce' === $mode ) {
				return sprintf(
					'<a class="wbtm-pn-cta" href="%s"><span class="dashicons dashicons-admin-settings"></span>%s</a>',
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
					esc_html__( 'Configure WooCommerce Payments', 'bus-ticket-booking-with-seat-reservation' )
				);
			}

			if ( $is_pro ) {
				return sprintf(
					'<a class="wbtm-pn-cta" href="%s"><span class="dashicons dashicons-admin-settings"></span>%s</a>',
					esc_url( admin_url( 'edit.php?post_type=wbtm_bus&page=wbtm_settings_page#wbtm_payment_settings' ) ),
					esc_html__( 'Configure Pro Payment Methods', 'bus-ticket-booking-with-seat-reservation' )
				);
			}

			// Standalone, free: enabling the free Offline gateway is all it takes.
			return sprintf(
				'<a class="wbtm-pn-cta" href="%s"><span class="dashicons dashicons-money-alt"></span>%s</a>',
				esc_url( admin_url( 'edit.php?post_type=wbtm_bus&page=wbtm_settings_page#wbtm_payment_settings' ) ),
				esc_html__( 'Enable Offline Payment', 'bus-ticket-booking-with-seat-reservation' )
			);
		}

		/** Muted secondary link — a light PRO upsell shown only for the free Offline case. */
		private function secondary_action( $mode, $is_pro, $wc_active ) {
			if ( 'standalone' === $mode && ! $is_pro ) {
				return sprintf(
					'<a class="wbtm-pn-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( 'https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/' ),
					esc_html__( 'Need card payments? Upgrade to PRO for Stripe & PayPal', 'bus-ticket-booking-with-seat-reservation' )
				);
			}
			return '';
		}

		/** True on the bus add/edit screen while the modern editor UI is active. */
		private function is_modern_bus_editor() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}
			$screen = get_current_screen();
			$cpt    = class_exists( 'WBTM_Functions' ) ? WBTM_Functions::get_cpt() : 'wbtm_bus';
			if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== $cpt ) {
				return false;
			}
			return class_exists( 'WBTM_Settings_Modern' ) && WBTM_Settings_Modern::current_ui() === 'modern';
		}

		/**
		 * Render one modern notice card. Shared by both notices so they stay visually
		 * consistent and there is a single place to maintain the markup/close button.
		 *
		 * @param array $args {
		 *   @type string   variant     'upsell' | 'warning' | 'info' — drives the colorway.
		 *   @type string   icon        dashicon class for the leading badge.
		 *   @type string   badge       Optional pill text (e.g. "PRO"); '' hides it.
		 *   @type string   title       Card title (escaped here).
		 *   @type string   message     Body text — PRE-ESCAPED HTML (callers escape).
		 *   @type string   lead        Optional bold lead line above benefits (escaped here).
		 *   @type string[] benefits    Optional benefit chips (escaped here).
		 *   @type string   primary     Optional primary CTA — PRE-BUILT escaped <a>.
		 *   @type string   secondary   Optional secondary link — PRE-BUILT escaped <a>.
		 *   @type string   dismiss_url Optional; when set renders the × close link.
		 * }
		 */
		private function render_card( array $args ) {
			$a = array_merge(
				array(
					'variant'     => 'warning',
					'icon'        => 'dashicons-money-alt',
					'badge'       => '',
					'title'       => '',
					'message'     => '',
					'lead'        => '',
					'benefits'    => array(),
					'primary'     => '',
					'secondary'   => '',
					'dismiss_url' => '',
				),
				$args
			);

			$this->print_styles();

			$classes = 'notice wbtm-pro-notice wbtm-pn--' . sanitize_html_class( $a['variant'] );
			if ( 'upsell' === $a['variant'] ) {
				$classes .= ' is-upsell';
			}
			if ( ! empty( $a['dismiss_url'] ) ) {
				$classes .= ' has-dismiss';
			}
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<div class="wbtm-pn-card">
					<span class="wbtm-pn-accent" aria-hidden="true"></span>
					<?php if ( ! empty( $a['dismiss_url'] ) ) : ?>
						<a class="wbtm-pn-dismiss" href="<?php echo esc_url( $a['dismiss_url'] ); ?>" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'bus-ticket-booking-with-seat-reservation' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</a>
					<?php endif; ?>
					<div class="wbtm-pn-inner">
						<span class="wbtm-pn-icon" aria-hidden="true">
							<span class="dashicons <?php echo esc_attr( $a['icon'] ); ?>"></span>
						</span>
						<div class="wbtm-pn-content">
							<div class="wbtm-pn-head">
								<span class="wbtm-pn-title"><?php echo esc_html( $a['title'] ); ?></span>
								<?php if ( '' !== $a['badge'] ) : ?>
									<span class="wbtm-pn-badge"><span class="dashicons dashicons-star-filled"></span><?php echo esc_html( $a['badge'] ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $a['message'] ) : ?>
								<p class="wbtm-pn-msg"><?php echo $a['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass pre-escaped HTML. ?></p>
							<?php endif; ?>
							<?php if ( '' !== $a['lead'] ) : ?>
								<p class="wbtm-pn-lead"><?php echo esc_html( $a['lead'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $a['benefits'] ) ) : ?>
								<ul class="wbtm-pn-benefits">
									<?php foreach ( $a['benefits'] as $benefit ) : ?>
										<li><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html( $benefit ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $a['primary'] || '' !== $a['secondary'] ) : ?>
							<div class="wbtm-pn-actions">
								<?php echo $a['primary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
								<?php echo $a['secondary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php
		}

		/** Nonce'd URL that snoozes the payment warning for a while. */
		private function payment_dismiss_url() {
			return wp_nonce_url( add_query_arg( 'wbtm_dismiss_payment_notice', '1' ), 'wbtm_dismiss_payment_notice' );
		}

		/**
		 * Print the notice styles once per request.
		 *
		 * Kept inline (rather than an enqueued stylesheet) because this notice can
		 * appear on any admin screen and the plugin's admin assets are conditionally
		 * gated to plugin screens only — a tiny scoped block avoids loading the whole
		 * admin bundle site-wide just to style one card.
		 */
		private function print_styles() {
			static $printed = false;
			if ( $printed ) {
				return;
			}
			$printed = true;
			?>
			<style id="wbtm-pro-notice-css">
				.notice.wbtm-pro-notice { border: 0; background: transparent; box-shadow: none; padding: 0; margin: 16px 20px 20px 2px; }
				.wbtm-pro-notice .wbtm-pn-card { position: relative; display: flex; align-items: stretch; background: #fff; border: 1px solid #f0d9dc; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 22px rgba(179,33,47,.08); }
				.wbtm-pro-notice .wbtm-pn-accent { flex: 0 0 6px; background: linear-gradient(180deg,#e63946,#b3212f); }
				.wbtm-pro-notice .wbtm-pn-inner { flex: 1 1 auto; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 18px 22px; }
				.wbtm-pro-notice.has-dismiss .wbtm-pn-inner { padding-right: 46px; }
				.wbtm-pro-notice .wbtm-pn-icon { flex: 0 0 auto; width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg,#e63946,#b3212f); box-shadow: 0 6px 16px rgba(230,57,70,.35); }
				.wbtm-pro-notice .wbtm-pn-icon .dashicons { font-size: 28px; width: 28px; height: 28px; }
				.wbtm-pro-notice .wbtm-pn-content { flex: 1 1 320px; min-width: 240px; }
				.wbtm-pro-notice .wbtm-pn-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
				.wbtm-pro-notice .wbtm-pn-title { font-size: 16px; font-weight: 700; color: #0f172a; line-height: 1.2; }
				.wbtm-pro-notice .wbtm-pn-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 800; letter-spacing: .4px; color: #fff; background: linear-gradient(135deg,#f59e0b,#d97706); padding: 2px 9px 2px 6px; border-radius: 999px; text-transform: uppercase; }
				.wbtm-pro-notice .wbtm-pn-badge .dashicons { font-size: 12px; width: 12px; height: 12px; }
				.wbtm-pro-notice .wbtm-pn-msg { margin: 0 0 8px; color: #475569; font-size: 13.5px; line-height: 1.5; }
				.wbtm-pro-notice .wbtm-pn-msg strong { color: #0f172a; }
				.wbtm-pro-notice .wbtm-pn-lead { margin: 0 0 8px; color: #0f172a; font-size: 13px; font-weight: 600; }
				.wbtm-pro-notice .wbtm-pn-benefits { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
				.wbtm-pro-notice .wbtm-pn-benefits li { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: #b3212f; background: #fdeef0; border: 1px solid #f7d4d9; padding: 5px 10px; border-radius: 8px; margin: 0; }
				.wbtm-pro-notice .wbtm-pn-benefits .dashicons { font-size: 15px; width: 15px; height: 15px; color: #e63946; }
				.wbtm-pro-notice .wbtm-pn-actions { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-start; gap: 8px; margin-left: auto; }
				.wbtm-pro-notice .wbtm-pn-cta { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; white-space: nowrap; background: linear-gradient(135deg,#e63946,#b3212f); color: #fff !important; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; box-shadow: 0 6px 16px rgba(230,57,70,.32); transition: transform .12s ease, box-shadow .12s ease, filter .12s ease; }
				.wbtm-pro-notice .wbtm-pn-cta:hover { filter: brightness(1.06); transform: translateY(-1px); box-shadow: 0 10px 22px rgba(230,57,70,.4); }
				.wbtm-pro-notice .wbtm-pn-cta:focus { outline: none; box-shadow: 0 0 0 3px rgba(230,57,70,.35); }
				.wbtm-pro-notice .wbtm-pn-cta .dashicons { font-size: 18px; width: 18px; height: 18px; }
				.wbtm-pro-notice .wbtm-pn-secondary { font-size: 12.5px; color: #64748b; text-decoration: none; }
				.wbtm-pro-notice .wbtm-pn-secondary:hover { color: #b3212f; text-decoration: underline; }
				/* Close (×) — our own, so it works on cards that aren't WP `is-dismissible`. */
				.wbtm-pro-notice .wbtm-pn-dismiss { position: absolute; top: 10px; right: 10px; z-index: 2; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; color: #94a3b8; text-decoration: none; transition: background .12s ease, color .12s ease; }
				.wbtm-pro-notice .wbtm-pn-dismiss:hover { background: #f1f5f9; color: #334155; }
				.wbtm-pro-notice .wbtm-pn-dismiss:focus { outline: none; box-shadow: 0 0 0 3px rgba(148,163,184,.4); }
				.wbtm-pro-notice .wbtm-pn-dismiss .dashicons { font-size: 18px; width: 18px; height: 18px; }
				/* Info colorway — the Standalone-mode heads-up (not a warning, not an upsell). */
				.wbtm-pro-notice.wbtm-pn--info .wbtm-pn-card { border-color: #d5e3f7; box-shadow: 0 6px 22px rgba(37,99,235,.08); }
				.wbtm-pro-notice.wbtm-pn--info .wbtm-pn-accent { background: linear-gradient(180deg,#3b82f6,#2563eb); }
				.wbtm-pro-notice.wbtm-pn--info .wbtm-pn-icon { background: linear-gradient(135deg,#3b82f6,#2563eb); box-shadow: 0 6px 16px rgba(37,99,235,.3); }
				.wbtm-pro-notice.wbtm-pn--info .wbtm-pn-secondary:hover { color: #2563eb; }
				@media (max-width: 782px) {
					.notice.wbtm-pro-notice { margin: 12px 0 16px; }
					.wbtm-pro-notice .wbtm-pn-inner { padding: 16px; gap: 14px; }
					.wbtm-pro-notice.has-dismiss .wbtm-pn-inner { padding-right: 16px; padding-top: 40px; }
					.wbtm-pro-notice .wbtm-pn-actions { margin-left: 0; width: 100%; }
					.wbtm-pro-notice .wbtm-pn-cta { width: 100%; justify-content: center; }
				}
				@media (prefers-reduced-motion: reduce) {
					.wbtm-pro-notice .wbtm-pn-cta, .wbtm-pro-notice .wbtm-pn-dismiss { transition: none; }
				}
			</style>
			<?php
		}

		/**
		 * Helpful, dismissible admin notice shown when WooCommerce is inactive,
		 * explaining that the plugin is running in Standalone booking mode.
		 */
		public function render_standalone_notice() {
			if ( class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_wc_active() ) {
				return;
			}
			if ( get_option( 'wbtm_standalone_dismissed' ) === 'yes' ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$dismiss_url = wp_nonce_url( add_query_arg( 'wbtm_dismiss_standalone', '1' ), 'wbtm_dismiss_standalone' );

			$message = '<strong>' . esc_html__( 'Running in Standalone mode.', 'bus-ticket-booking-with-seat-reservation' ) . '</strong> '
				. esc_html__( "WooCommerce isn't active, so bookings are handled internally — activate it any time to use its cart, checkout and order flow.", 'bus-ticket-booking-with-seat-reservation' );

			$this->render_card( array(
				'variant'     => 'info',
				'icon'        => 'dashicons-info-outline',
				'title'       => WBTM_Functions::get_name(),
				'message'     => $message,
				'secondary'   => sprintf(
					'<a class="wbtm-pn-secondary" href="%s">%s</a>',
					esc_url( $dismiss_url ),
					esc_html__( 'Continue without WooCommerce', 'bus-ticket-booking-with-seat-reservation' )
				),
				'dismiss_url' => $dismiss_url,
			) );
		}

		public function handle_standalone_notice_dismiss() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['wbtm_dismiss_standalone'] ) && sanitize_text_field( wp_unslash( $_GET['wbtm_dismiss_standalone'] ) ) === '1' ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbtm_dismiss_standalone' ) && current_user_can( 'manage_options' ) ) {
					update_option( 'wbtm_standalone_dismissed', 'yes' );
				}
			}
		}

		public function handle_payment_notice_dismiss() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['wbtm_dismiss_payment_notice'] ) && sanitize_text_field( wp_unslash( $_GET['wbtm_dismiss_payment_notice'] ) ) === '1' ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbtm_dismiss_payment_notice' ) && current_user_can( 'manage_options' ) ) {
					// Time-boxed snooze — see the note in render() for why this is not a
					// permanent dismissal.
					set_transient( self::PAYMENT_SNOOZE_KEY, 'yes', WEEK_IN_SECONDS );
				}
			}
		}
	}

	new WBTM_Admin_Payment_Notice();
}
