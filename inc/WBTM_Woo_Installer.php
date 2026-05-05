<?php
/**
 * WBTM WooCommerce Installer
 * Handles WooCommerce dependency check, beautiful popup display,
 * and AJAX-based installation & activation.
 * The popup shows on EVERY admin page when WooCommerce is not active.
 *
 * @package BusTicketBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_Woo_Installer' ) ) {

	class WBTM_Woo_Installer {

		/**
		 * Constructor – hooks into WordPress.
		 */
		public function __construct() {
			// On admin_init, check if our plugin was just activated (for redirect)
			add_action( 'admin_init', array( $this, 'handle_activation_redirect' ) );
			// Enqueue popup assets on all admin pages (only outputs if WooCommerce is missing)
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			// Render the popup markup in admin footer
			add_action( 'admin_footer', array( $this, 'render_popup' ) );
			// AJAX handlers for install, activate & dismiss
			add_action( 'wp_ajax_wbtm_install_woocommerce', array( $this, 'ajax_install_woocommerce' ) );
			add_action( 'wp_ajax_wbtm_activate_woocommerce', array( $this, 'ajax_activate_woocommerce' ) );
		}

		/**
		 * Check if WooCommerce plugin file exists (installed but maybe not active).
		 *
		 * @return bool
		 */
		private function is_woo_installed() {
			$plugin_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
			return file_exists( $plugin_file );
		}

		/**
		 * Check if WooCommerce is truly active (listed as active AND files exist).
		 *
		 * @return bool
		 */
		// Fixed by Shahnur — 2026-05-04 03:15 PM (Asia/Dhaka)
		// is_plugin_active can return true even if WC folder was deleted manually.
		// Verify the file actually exists to avoid ghost-active state.
		private function is_woo_active() {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			return is_plugin_active( 'woocommerce/woocommerce.php' ) && $this->is_woo_installed();
		}

		/**
		 * Runs on admin_init. If the transient from activation exists
		 * and WooCommerce IS active, redirect to bus lists page.
		 */
		public function handle_activation_redirect() {
			if ( ! get_transient( 'wbtm_plugin_activated' ) ) {
				return;
			}

			// Don't redirect on multi-site bulk activations
			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
				delete_transient( 'wbtm_plugin_activated' );
				return;
			}

			// WooCommerce is active → redirect immediately
			if ( $this->is_woo_active() ) {
				delete_transient( 'wbtm_plugin_activated' );
				wp_safe_redirect( admin_url( 'edit.php?post_type=wbtm_bus' ) );
				exit;
			}

			// WooCommerce is NOT active → clear transient, popup will show via should_show_popup()
			delete_transient( 'wbtm_plugin_activated' );
		}

		/**
		 * Should we show the popup on this page load?
		 * Show when WooCommerce is not active OR when its files are missing.
		 *
		 * @return bool
		 */
		private function should_show_popup() {
			// Show the popup if WooCommerce is not active or files are missing (e.g. deleted manually)
			return ! $this->is_woo_active() || ! $this->is_woo_installed();
		}

		/**
		 * Enqueue CSS & JS for the popup only when needed.
		 */
		public function enqueue_assets() {
			if ( ! $this->should_show_popup() ) {
				return;
			}

			wp_enqueue_style(
				'wbtm-woo-installer',
				WBTM_PLUGIN_URL . '/assets/admin/wbtm_woo_installer.css',
				array(),
				filemtime( WBTM_PLUGIN_DIR . '/assets/admin/wbtm_woo_installer.css' )
			);

			wp_enqueue_script(
				'wbtm-woo-installer',
				WBTM_PLUGIN_URL . '/assets/admin/wbtm_woo_installer.js',
				array( 'jquery' ),
				filemtime( WBTM_PLUGIN_DIR . '/assets/admin/wbtm_woo_installer.js' ),
				true
			);

			wp_localize_script( 'wbtm-woo-installer', 'wbtm_woo_installer', array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'install_nonce'    => wp_create_nonce( 'wbtm_install_woo' ),
				'activate_nonce'   => wp_create_nonce( 'wbtm_activate_woo' ),
				'redirect_url'     => admin_url( 'edit.php?post_type=wbtm_bus' ),
				'woo_installed'    => $this->is_woo_installed() ? 'yes' : 'no',
				'i18n'             => array(
					'installing'     => __( 'Installing WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
					'activating'     => __( 'Activating WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
					'success'        => __( 'WooCommerce activated successfully!', 'bus-ticket-booking-with-seat-reservation' ),
					'redirecting'    => __( 'Redirecting...', 'bus-ticket-booking-with-seat-reservation' ),
					'error'          => __( 'Something went wrong. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
					'install_error'  => __( 'Installation failed. Please install WooCommerce manually.', 'bus-ticket-booking-with-seat-reservation' ),
					'activate_error' => __( 'Activation failed. Please activate WooCommerce manually.', 'bus-ticket-booking-with-seat-reservation' ),
				),
			) );
		}

		/**
		 * Render the popup HTML in admin footer.
		 */
		public function render_popup() {
			if ( ! $this->should_show_popup() ) {
				return;
			}

			$is_installed = $this->is_woo_installed();
			$btn_text     = $is_installed
				? __( 'Activate WooCommerce', 'bus-ticket-booking-with-seat-reservation' )
				: __( 'Install & Activate WooCommerce', 'bus-ticket-booking-with-seat-reservation' );
			?>
			<!-- WBTM WooCommerce Installer Popup Overlay -->
			<div id="wbtm-woo-overlay" class="wbtm-woo-overlay">
				<div class="wbtm-woo-popup">

					<!-- Header strip -->
					<div class="wbtm-woo-header">
						<div class="wbtm-woo-header-icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</div>
						<span class="wbtm-woo-header-text"><?php esc_html_e( 'Bus Ticket Booking', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
					</div>

					<!-- Icon -->
					<div class="wbtm-woo-icon-wrapper">
						<div class="wbtm-woo-icon">
							<svg width="40" height="40" viewBox="0 0 24 24" fill="none">
								<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
								<path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</div>
					</div>

					<!-- Content -->
					<div class="wbtm-woo-content">
						<h2 class="wbtm-woo-title"><?php esc_html_e( 'WooCommerce Required', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
						<p class="wbtm-woo-desc">
							<?php esc_html_e( 'Bus Ticket Booking with Seat Reservation requires WooCommerce to manage bus ticket sales, seat reservations, and payments. Please install and activate WooCommerce to continue using this plugin.', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</p>
					</div>

					<!-- Feature highlights -->
					<div class="wbtm-woo-features">
						<div class="wbtm-woo-feature">
							<span class="wbtm-woo-feature-icon">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
							<span><?php esc_html_e( 'Ticket selling & payments', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</div>
						<div class="wbtm-woo-feature">
							<span class="wbtm-woo-feature-icon">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
							<span><?php esc_html_e( 'Order management', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</div>
						<div class="wbtm-woo-feature">
							<span class="wbtm-woo-feature-icon">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.3 4.3L6 11.6 2.7 8.3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
							<span><?php esc_html_e( 'Seat reservation system', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</div>
					</div>

					<!-- Progress area (hidden by default) -->
					<div id="wbtm-woo-progress" class="wbtm-woo-progress" style="display:none;">
						<div class="wbtm-woo-progress-bar">
							<div id="wbtm-woo-progress-fill" class="wbtm-woo-progress-fill"></div>
						</div>
						<p id="wbtm-woo-status-text" class="wbtm-woo-status-text"></p>
					</div>

					<!-- Action buttons -->
					<div class="wbtm-woo-actions">
						<button type="button" id="wbtm-woo-install-btn" class="wbtm-woo-btn wbtm-woo-btn-primary">
							<span class="wbtm-woo-btn-icon">
								<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
									<path d="M10 3v10m0 0l-4-4m4 4l4-4M3 17h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
							<span class="wbtm-woo-btn-text"><?php echo esc_html( $btn_text ); ?></span>
						</button>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>" class="wbtm-woo-btn wbtm-woo-btn-secondary">
							<?php esc_html_e( 'Install Manually', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</a>
					</div>

					<!-- Footer note -->
					<p class="wbtm-woo-footer-note">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align: -2px; flex-shrink: 0;">
							<path d="M7 1a6 6 0 100 12A6 6 0 007 1zm0 8.5a.75.75 0 110-1.5.75.75 0 010 1.5zM7.75 6.25a.75.75 0 01-1.5 0V4a.75.75 0 011.5 0v2.25z" fill="currentColor"/>
						</svg>
						<?php esc_html_e( 'WooCommerce is free, open-source, and trusted by millions of stores worldwide.', 'bus-ticket-booking-with-seat-reservation' ); ?>
					</p>
				</div>
			</div>
			<?php
		}

		/**
		 * AJAX: Install WooCommerce from WordPress.org repository.
		 */
		public function ajax_install_woocommerce() {
			check_ajax_referer( 'wbtm_install_woo', 'nonce' );

			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to install plugins.', 'bus-ticket-booking-with-seat-reservation' ) ) );
			}

			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/misc.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$api = plugins_api( 'plugin_information', array(
				'slug'   => 'woocommerce',
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			) );

			if ( is_wp_error( $api ) ) {
				wp_send_json_error( array( 'message' => $api->get_error_message() ) );
			}

			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			if ( $result === false ) {
				wp_send_json_error( array( 'message' => __( 'Installation failed.', 'bus-ticket-booking-with-seat-reservation' ) ) );
			}

			wp_send_json_success( array( 'message' => __( 'WooCommerce installed successfully.', 'bus-ticket-booking-with-seat-reservation' ) ) );
		}

		/**
		 * AJAX: Activate WooCommerce plugin.
		 */
		public function ajax_activate_woocommerce() {
			check_ajax_referer( 'wbtm_activate_woo', 'nonce' );

			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to activate plugins.', 'bus-ticket-booking-with-seat-reservation' ) ) );
			}

			$result = activate_plugin( 'woocommerce/woocommerce.php' );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'WooCommerce activated successfully!', 'bus-ticket-booking-with-seat-reservation' ) ) );
		}
	}

	new WBTM_Woo_Installer();
}
