<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

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
			// Polled by the popup while install/activate is running to read the live percentage.
			add_action( 'wp_ajax_wbtm_install_progress', array( $this, 'ajax_get_install_progress' ) );
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
		 * Raise PHP time/memory headroom for the duration of the install/activation
		 * request so large downloads & extraction never hit a host's default
		 * max_execution_time / memory_limit. Uses WP core's own helper for the
		 * memory bump so it still respects WP_MAX_MEMORY_LIMIT / hosting caps.
		 */
		private function prepare_environment_for_long_task() {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on some hosts, safe to suppress.
			}
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			if ( function_exists( 'ignore_user_abort' ) ) {
				ignore_user_abort( true );
			}
		}

		/**
		 * Remove a half-extracted WooCommerce folder left behind by a previous
		 * install attempt that timed out / failed mid-way. Without this, every
		 * retry fails immediately with "Destination folder already exists."
		 */
		private function cleanup_stale_partial_install() {
			$woo_dir = WP_PLUGIN_DIR . '/woocommerce';

			if ( ! is_dir( $woo_dir ) || file_exists( $woo_dir . '/woocommerce.php' ) ) {
				return; // Nothing there, or a complete install — leave it alone.
			}

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				include_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $woo_dir, true );
			}
		}

		/**
		 * Build the transient key used to share live progress between the
		 * install/activate AJAX request and the popup's polling requests.
		 *
		 * @param string $token Per-attempt token generated client-side, so a stale
		 *                      transient from a previous attempt is never reused.
		 * @return string
		 */
		private function progress_transient_key( $token ) {
			return 'wbtm_woo_progress_' . sanitize_key( $token );
		}

		/**
		 * Record the current install/activate progress so it can be polled.
		 *
		 * @param string $token   Per-attempt token, see progress_transient_key().
		 * @param int    $percent 0-100.
		 * @param string $text    Human-readable status text for the current step.
		 * @param string $status  'progress' (default), 'success', or 'error'. The
		 *                        popup uses this to recover even if the original
		 *                        AJAX response never reaches the browser (e.g. the
		 *                        browser gave up waiting while the server kept going).
		 */
		private function set_progress( $token, $percent, $text, $status = 'progress' ) {
			if ( ! $token ) {
				return;
			}
			set_transient(
				$this->progress_transient_key( $token ),
				array(
					'percent' => max( 0, min( 100, (int) round( $percent ) ) ),
					'text'    => (string) $text,
					'status'  => $status,
				),
				120
			);
		}

		/**
		 * Record a failure (so polling can pick it up even past a client-side
		 * timeout) and send the AJAX error response.
		 *
		 * @param string $token   Per-attempt progress token.
		 * @param string $message Error message.
		 */
		private function fail_install( $token, $message ) {
			$existing = $token ? get_transient( $this->progress_transient_key( $token ) ) : false;
			$percent  = ( is_array( $existing ) && isset( $existing['percent'] ) ) ? $existing['percent'] : 0;
			$this->set_progress( $token, $percent, $message, 'error' );
			wp_send_json_error( array( 'message' => $message ) );
		}

		/**
		 * WP core resets the execution time limit to a flat 300 seconds right
		 * before moving/copying files into place (see Plugin_Upgrader's
		 * install_package(), called via the 'upgrader_pre_install' hook this
		 * targets). WooCommerce ships several thousand files, and when its
		 * directory move falls back to a file-by-file copy — which can happen
		 * on Windows/local setups for reasons outside our control (locked
		 * handles, AV scanning, etc.) — that copy can outrun even 300 seconds.
		 * Re-extend it back to unlimited at the one hook WP fires right before
		 * that copy starts.
		 *
		 * @param mixed $response Pass-through filter value.
		 * @return mixed
		 */
		private function reset_time_limit_before_copy( $response ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on some hosts, safe to suppress.
			}
			return $response;
		}

		/**
		 * Download the WooCommerce package ourselves (instead of letting
		 * Plugin_Upgrader do it internally) so we can report real byte-level
		 * download progress back to the popup. Falls back to returning false
		 * (caller then lets Plugin_Upgrader handle the download as before) when
		 * cURL isn't available, or when the site has locked down outbound
		 * requests via WP_HTTP_BLOCK_EXTERNAL.
		 *
		 * @param string $url         Package URL from plugins_api().
		 * @param string $token       Per-attempt progress token.
		 * @param int    $range_start Progress percent representing 0% downloaded.
		 * @param int    $range_end   Progress percent representing 100% downloaded.
		 * @return string|false Local temp file path, or false to signal fallback.
		 */
		private function download_package_with_progress( $url, $token, $range_start, $range_end ) {
			if ( ! function_exists( 'curl_init' ) ) {
				return false;
			}

			// Respect a host's outbound-request lockdown rather than bypassing it.
			if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) {
				return false;
			}

			$tmp_file = wp_tempnam( $url );
			if ( ! $tmp_file ) {
				return false;
			}

			$fp = fopen( $tmp_file, 'wb' );
			if ( ! $fp ) {
				@unlink( $tmp_file );
				return false;
			}

			$ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
			$ch        = curl_init( $url );

			curl_setopt_array(
				$ch,
				array(
					CURLOPT_FILE             => $fp,
					CURLOPT_FOLLOWLOCATION   => true,
					CURLOPT_MAXREDIRS        => 5,
					CURLOPT_CONNECTTIMEOUT   => 30,
					CURLOPT_TIMEOUT          => 0, // No cap — the request already has unlimited execution time.
					CURLOPT_SSL_VERIFYPEER   => true,
					CURLOPT_CAINFO           => $ca_bundle,
					CURLOPT_USERAGENT        => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
					CURLOPT_NOPROGRESS       => false,
					CURLOPT_PROGRESSFUNCTION => function ( $resource, $download_size, $downloaded, $upload_size, $uploaded ) use ( $token, $range_start, $range_end ) {
						if ( $download_size > 0 ) {
							$ratio   = $downloaded / $download_size;
							$percent = $range_start + ( $ratio * ( $range_end - $range_start ) );
							$this->set_progress(
								$token,
								$percent,
								sprintf(
									/* translators: 1: downloaded MB, 2: total MB */
									__( 'Downloading WooCommerce... %1$s MB / %2$s MB', 'bus-ticket-booking-with-seat-reservation' ),
									number_format_i18n( $downloaded / MB_IN_BYTES, 1 ),
									number_format_i18n( $download_size / MB_IN_BYTES, 1 )
								)
							);
						}
					},
				)
			);

			$ok        = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );
			fclose( $fp );

			if ( ! $ok || $http_code >= 400 ) {
				@unlink( $tmp_file );
				return false;
			}

			return $tmp_file;
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
					'timeout_wait'   => __( 'Still working on the server — large installs can take a few minutes. This page will update automatically once it finishes.', 'bus-ticket-booking-with-seat-reservation' ),
					'timeout_error'  => __( 'This is taking unusually long. The install may still finish in the background — check back in a few minutes, or install WooCommerce manually.', 'bus-ticket-booking-with-seat-reservation' ),
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
		 * AJAX: Report current install/activate progress percentage + status text.
		 * Polled by the popup every ~1s while installWooCommerce()/activateWooCommerce()
		 * are running, so the user always sees how far along the process is.
		 */
		public function ajax_get_install_progress() {
			check_ajax_referer( 'wbtm_install_woo', 'nonce' );

			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_send_json_error();
			}

			$token = isset( $_POST['progress_token'] ) ? sanitize_key( wp_unslash( $_POST['progress_token'] ) ) : '';
			$data  = $token ? get_transient( $this->progress_transient_key( $token ) ) : false;

			if ( ! is_array( $data ) ) {
				$data = array(
					'percent' => 0,
					'text'    => '',
					'status'  => 'progress',
				);
			}

			wp_send_json_success( $data );
		}

		/**
		 * AJAX: Install WooCommerce from WordPress.org repository.
		 */
		public function ajax_install_woocommerce() {
			check_ajax_referer( 'wbtm_install_woo', 'nonce' );

			$token = isset( $_POST['progress_token'] ) ? sanitize_key( wp_unslash( $_POST['progress_token'] ) ) : '';

			if ( ! current_user_can( 'install_plugins' ) ) {
				$this->fail_install( $token, __( 'You do not have permission to install plugins.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/misc.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$this->prepare_environment_for_long_task();
			$this->set_progress( $token, 2, __( 'Preparing installation...', 'bus-ticket-booking-with-seat-reservation' ) );

			// Fail fast with a clear message instead of a confusing timeout
			// when the host needs FTP credentials for filesystem writes.
			if ( 'direct' !== get_filesystem_method( array(), WP_PLUGIN_DIR ) ) {
				$this->fail_install( $token, __( 'This server requires manual FTP credentials for plugin installation. Please install WooCommerce manually.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			$this->cleanup_stale_partial_install();
			$this->set_progress( $token, 5, __( 'Fetching plugin information...', 'bus-ticket-booking-with-seat-reservation' ) );

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
				$this->fail_install( $token, $api->get_error_message() );
			}

			// Download it ourselves first so we can report real byte-level percentage.
			// Returns false (and we fall back to Plugin_Upgrader's own download) when
			// cURL is unavailable or outbound requests are locked down on this host.
			$local_package = $this->download_package_with_progress( $api->download_link, $token, 10, 70 );

			if ( ! $local_package ) {
				// We didn't download it ourselves — Plugin_Upgrader will, internally,
				// with no granular progress available, so just mark the phase.
				$this->set_progress( $token, 10, __( 'Downloading WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			$this->set_progress( $token, 75, __( 'Unpacking & installing WooCommerce (this can take several minutes for many files)...', 'bus-ticket-booking-with-seat-reservation' ) );

			// WP core resets the execution time limit to a flat 300s right before
			// moving files into the plugins folder (see reset_time_limit_before_copy()
			// docblock) — re-extend it for the duration of that move/copy so a host
			// with thousands of small files (like WooCommerce) can't fatally time out.
			add_filter( 'upgrader_pre_install', array( $this, 'reset_time_limit_before_copy' ) );

			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$package  = $local_package ? $local_package : $api->download_link;
			$result   = $upgrader->install( $package );

			remove_filter( 'upgrader_pre_install', array( $this, 'reset_time_limit_before_copy' ) );

			if ( $local_package ) {
				@unlink( $local_package );
			}

			if ( is_wp_error( $result ) ) {
				$this->fail_install( $token, $result->get_error_message() );
			}

			if ( $result === false ) {
				$this->fail_install( $token, __( 'Installation failed.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			$this->set_progress( $token, 95, __( 'WooCommerce installed successfully.', 'bus-ticket-booking-with-seat-reservation' ), 'success' );

			wp_send_json_success( array( 'message' => __( 'WooCommerce installed successfully.', 'bus-ticket-booking-with-seat-reservation' ) ) );
		}

		/**
		 * AJAX: Activate WooCommerce plugin.
		 */
		public function ajax_activate_woocommerce() {
			check_ajax_referer( 'wbtm_activate_woo', 'nonce' );

			$token = isset( $_POST['progress_token'] ) ? sanitize_key( wp_unslash( $_POST['progress_token'] ) ) : '';

			if ( ! current_user_can( 'activate_plugins' ) ) {
				$this->fail_install( $token, __( 'You do not have permission to activate plugins.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			// WooCommerce runs its own DB table/setup routines on activation,
			// which can also be heavy on first run — give it the same headroom.
			$this->prepare_environment_for_long_task();
			$this->set_progress( $token, 96, __( 'Activating WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ) );

			$result = activate_plugin( 'woocommerce/woocommerce.php' );

			if ( is_wp_error( $result ) ) {
				$this->fail_install( $token, $result->get_error_message() );
			}

			$this->set_progress( $token, 100, __( 'WooCommerce activated successfully!', 'bus-ticket-booking-with-seat-reservation' ), 'success' );

			wp_send_json_success( array( 'message' => __( 'WooCommerce activated successfully!', 'bus-ticket-booking-with-seat-reservation' ) ) );
		}
	}

	new WBTM_Woo_Installer();
}
