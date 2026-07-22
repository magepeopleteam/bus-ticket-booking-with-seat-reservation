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
 * Availability logic lives in WBTM_Payment_Status_Checker; this class only
 * wires it into `admin_notices` and renders the markup, so the check itself
 * stays reusable and unit-testable outside of WordPress hooks.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WBTM_Admin_Payment_Notice' ) ) {

	class WBTM_Admin_Payment_Notice {

		/** @var WBTM_Payment_Status_Checker */
		private $checker;

		public function __construct( $checker = null ) {
			$this->checker = ( $checker instanceof WBTM_Payment_Status_Checker ) ? $checker : new WBTM_Payment_Status_Checker();
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'admin_notices', array( $this, 'render_standalone_notice' ) );
			add_action( 'admin_init', array( $this, 'handle_standalone_notice_dismiss' ) );
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

			$mode = $this->checker->active_mode();
			$msg  = ( 'woocommerce' === $mode )
				? esc_html__( 'Bookings run through WooCommerce, but no WooCommerce payment gateway is enabled. Customers will not be able to complete bookings until you enable at least one.', 'bus-ticket-booking-with-seat-reservation' )
				: esc_html__( 'Bookings run through Custom Payment (Standalone), but no custom payment method is enabled. Customers will not be able to complete bookings until you enable at least one.', 'bus-ticket-booking-with-seat-reservation' );
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php echo esc_html( WBTM_Functions::get_name() ); ?>:</strong>
					<?php echo $msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
				</p>
				<p><?php echo wp_kses_post( $this->action_links() ); ?></p>
			</div>
			<?php
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

		/** Build the contextual "fix it" links shown under the notice. */
		private function action_links() {
			$links = array();

			if ( class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_wc_active() ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ),
					esc_html__( 'Configure WooCommerce Payments', 'bus-ticket-booking-with-seat-reservation' )
				);
			}

			$is_pro = class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_pro_active();

			if ( $is_pro ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?post_type=wbtm_bus&page=wbtm_settings_page#wbtm_payment_settings' ) ),
					esc_html__( 'Configure Pro Payment Methods', 'bus-ticket-booking-with-seat-reservation' )
				);
			} else {
				$links[] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( 'https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/' ),
					esc_html__( 'Upgrade to Pro', 'bus-ticket-booking-with-seat-reservation' )
				);
			}

			return implode( ' &nbsp;|&nbsp; ', $links );
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
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php echo esc_html( sprintf(
						/* translators: %s: plugin label, e.g. "Bus" */
						__( '%s is running in Standalone mode.', 'bus-ticket-booking-with-seat-reservation' ),
						WBTM_Functions::get_name()
					) ); ?></strong>
					<?php esc_html_e( 'WooCommerce is not active, so bookings are handled internally. Activate WooCommerce any time to use its cart, checkout and order flow.', 'bus-ticket-booking-with-seat-reservation' ); ?>
					<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Continue without WooCommerce', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
				</p>
			</div>
			<?php
		}

		public function handle_standalone_notice_dismiss() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['wbtm_dismiss_standalone'] ) && sanitize_text_field( wp_unslash( $_GET['wbtm_dismiss_standalone'] ) ) === '1' ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wbtm_dismiss_standalone' ) && current_user_can( 'manage_options' ) ) {
					update_option( 'wbtm_standalone_dismissed', 'yes' );
				}
			}
		}
	}

	new WBTM_Admin_Payment_Notice();
}
