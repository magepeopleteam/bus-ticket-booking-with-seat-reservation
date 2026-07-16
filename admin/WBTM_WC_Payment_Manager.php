<?php
	/**
	 * WooCommerce Payment Methods Manager for the Bus plugin.
	 *
	 * Renders every WooCommerce payment gateway's OWN native settings form inline,
	 * inside the bus plugin's Payments → WooCommerce tab. Each gateway's fields
	 * are produced by WooCommerce itself (generate_settings_html / get_form_fields)
	 * and saved through the gateway's own process_admin_options(). Nothing is
	 * re-implemented — this is WooCommerce's real configuration, embedded inline.
	 *
	 * Ported from the sibling rental plugin's RBFW_WC_Payment_Manager.
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_WC_Payment_Manager' ) ) :

		class WBTM_WC_Payment_Manager {

			private static $instance = null;

			public static function instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
				add_action( 'wp_ajax_wbtm_wc_save_gateway', array( $this, 'ajax_save_gateway' ) );
				add_action( 'wp_ajax_wbtm_wc_toggle_gateway', array( $this, 'ajax_toggle_gateway' ) );
			}

			// ---------------------------------------------------------------
			// Assets
			// ---------------------------------------------------------------

			public function enqueue_assets( $hook ) {
				$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				$on_settings = $screen && strpos( $screen->id, 'wbtm_settings_page' ) !== false;
				$cpt         = class_exists( 'WBTM_Functions' ) ? WBTM_Functions::get_cpt() : 'wbtm_bus';
				$on_bus_edit = $screen
					&& in_array( $hook, array( 'post.php', 'post-new.php' ), true )
					&& $screen->base === 'post'
					&& $screen->post_type === $cpt;
				if ( ! $on_settings && ! $on_bus_edit ) {
					return;
				}

				// WooCommerce admin styling + the scripts its native fields rely on.
				if ( function_exists( 'WC' ) && class_exists( 'WooCommerce' ) ) {
					wp_enqueue_style( 'woocommerce_admin_styles' );
					wp_enqueue_script( 'wc-enhanced-select' );
					wp_enqueue_script( 'wc-jquery-tiptip' );
				}

				$js_path = WBTM_PLUGIN_DIR . '/assets/admin/js/wbtm-wc-payment-manager.js';
				$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WBTM_VERSION;

				wp_enqueue_script(
					'wbtm-wc-payment-manager',
					WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-wc-payment-manager.js',
					array( 'jquery' ),
					$js_ver,
					true
				);
				wp_localize_script(
					'wbtm-wc-payment-manager',
					'wbtmWcPaymentManager',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'wbtm_wc_payment_manager' ),
						'i18n'    => array(
							'saving'    => __( 'Saving…', 'bus-ticket-booking-with-seat-reservation' ),
							'saved'     => __( 'Saved!', 'bus-ticket-booking-with-seat-reservation' ),
							'error'     => __( 'An error occurred. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
							'enabled'   => __( 'Enabled', 'bus-ticket-booking-with-seat-reservation' ),
							'disabled'  => __( 'Disabled', 'bus-ticket-booking-with-seat-reservation' ),
							'configure' => __( 'Configure', 'bus-ticket-booking-with-seat-reservation' ),
							'close'     => __( 'Close', 'bus-ticket-booking-with-seat-reservation' ),
						),
					)
				);
			}

			// ---------------------------------------------------------------
			// Gateway collection (includes suppressed ones, e.g. PayPal Standard)
			// ---------------------------------------------------------------

			private function get_all_gateways() {
				$wc_defaults     = array( 'WC_Gateway_BACS', 'WC_Gateway_Cheque', 'WC_Gateway_COD', 'WC_Gateway_Paypal' );
				$gateway_classes = apply_filters( 'woocommerce_payment_gateways', $wc_defaults );

				$loaded   = WC()->payment_gateways()->payment_gateways();
				$gateways = array();
				foreach ( $loaded as $g ) {
					if ( $g instanceof WC_Payment_Gateway ) {
						$gateways[ $g->id ] = $g;
					}
				}
				foreach ( $gateway_classes as $class ) {
					if ( ! is_string( $class ) || ! class_exists( $class ) ) {
						continue;
					}
					$already = false;
					foreach ( $gateways as $g ) {
						if ( $g instanceof $class ) {
							$already = true;
							break;
						}
					}
					if ( ! $already ) {
						$instance = new $class();
						if ( $instance instanceof WC_Payment_Gateway && ! isset( $gateways[ $instance->id ] ) ) {
							$gateways[ $instance->id ] = $instance;
						}
					}
				}

				// Respect WooCommerce's saved gateway order.
				$order = (array) get_option( 'woocommerce_gateway_order', array() );
				if ( ! empty( $order ) ) {
					uasort(
						$gateways,
						static function ( $a, $b ) use ( $order ) {
							$pa = isset( $order[ $a->id ] ) ? (int) $order[ $a->id ] : 999;
							$pb = isset( $order[ $b->id ] ) ? (int) $order[ $b->id ] : 999;
							return $pa <=> $pb;
						}
					);
				}

				return $gateways;
			}

			private function get_gateway( $gateway_id ) {
				$gateways = $this->get_all_gateways();
				return isset( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;
			}

			private function verify_request() {
				check_ajax_referer( 'wbtm_wc_payment_manager', 'nonce' );
				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'bus-ticket-booking-with-seat-reservation' ), 403 );
				}
				if ( ! class_exists( 'WooCommerce' ) ) {
					wp_send_json_error( __( 'WooCommerce is not active.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
			}

			// ---------------------------------------------------------------
			// AJAX: save one gateway's native form (process_admin_options)
			// ---------------------------------------------------------------

			public function ajax_save_gateway() {
				$this->verify_request();

				$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_key( wp_unslash( $_POST['gateway_id'] ) ) : '';
				$gateway    = $this->get_gateway( $gateway_id );
				if ( ! $gateway ) {
					wp_send_json_error( __( 'Gateway not found.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				// process_admin_options() reads $_POST keyed as woocommerce_{id}_{field};
				// our JS submits the native form fields under exactly those names.
				$gateway->process_admin_options();

				$errors = $gateway->get_errors();
				if ( ! empty( $errors ) ) {
					wp_send_json_error( implode( ' ', array_map( 'wp_strip_all_tags', $errors ) ) );
				}

				do_action( 'woocommerce_update_options_payment_gateways_' . $gateway->id );
				if ( WC()->payment_gateways() ) {
					WC()->payment_gateways()->init();
				}

				$refreshed = $this->get_gateway( $gateway_id );
				wp_send_json_success(
					array(
						'message' => __( 'Settings saved successfully!', 'bus-ticket-booking-with-seat-reservation' ),
						'enabled' => ( $refreshed && 'yes' === $refreshed->enabled ) ? 'yes' : 'no',
					)
				);
			}

			// ---------------------------------------------------------------
			// AJAX: quick enable/disable from the card header
			// ---------------------------------------------------------------

			public function ajax_toggle_gateway() {
				$this->verify_request();

				$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_key( wp_unslash( $_POST['gateway_id'] ) ) : '';
				$enabled    = ( isset( $_POST['enabled'] ) && 'yes' === $_POST['enabled'] ) ? 'yes' : 'no';
				if ( empty( $gateway_id ) ) {
					wp_send_json_error( __( 'Invalid gateway.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$option_key = 'woocommerce_' . $gateway_id . '_settings';
				$opts       = get_option( $option_key, array() );
				if ( ! is_array( $opts ) ) {
					$opts = array();
				}
				$opts['enabled'] = $enabled;
				if ( 'yes' === $enabled ) {
					$opts['_should_load'] = 'yes';
				}
				update_option( $option_key, $opts );

				if ( WC()->payment_gateways() ) {
					WC()->payment_gateways()->init();
				}

				wp_send_json_success( array( 'enabled' => $enabled ) );
			}

			// ---------------------------------------------------------------
			// Render — called from the WooCommerce tab
			// ---------------------------------------------------------------

			public function render() {
				if ( ! class_exists( 'WooCommerce' ) ) {
					return;
				}

				$gateways = $this->get_all_gateways();
				if ( empty( $gateways ) ) {
					echo '<p>' . esc_html__( 'No payment gateways are registered.', 'bus-ticket-booking-with-seat-reservation' ) . '</p>';
					return;
				}
				?>
				<div class="wbtm-wc-payment-manager">
					<div class="wbtm-wc-pm-bar">
						<h3 class="wbtm-wc-pm-heading"><?php esc_html_e( 'WooCommerce Payment Methods', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" class="button button-small wbtm-wc-pm-wc-link" target="_blank">
							<?php esc_html_e( 'Open in WooCommerce', 'bus-ticket-booking-with-seat-reservation' ); ?>
							<span class="dashicons dashicons-external" style="font-size:14px;line-height:1.4;vertical-align:middle;"></span>
						</a>
					</div>

					<?php
					foreach ( $gateways as $gateway ) :
						$is_enabled = ( 'yes' === $gateway->enabled );
						$title      = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
						$desc       = $gateway->get_method_description() ? $gateway->get_method_description() : $gateway->get_description();
						?>
						<div class="wbtm-gw-card <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>">
							<div class="wbtm-gw-head">
								<div class="wbtm-gw-head-main">
									<label class="wbtm-gw-toggle" title="<?php esc_attr_e( 'Enable / disable', 'bus-ticket-booking-with-seat-reservation' ); ?>">
										<input type="checkbox" class="wbtm-gw-toggle-input" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $is_enabled ); ?>>
										<span class="wbtm-gw-toggle-slider"></span>
									</label>
									<span class="wbtm-gw-title"><?php echo esc_html( $title ); ?></span>
									<span class="wbtm-gw-badge"><?php echo $is_enabled ? esc_html__( 'Enabled', 'bus-ticket-booking-with-seat-reservation' ) : esc_html__( 'Disabled', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</div>
								<button type="button" class="button wbtm-gw-configure-btn"><?php esc_html_e( 'Configure', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							</div>

							<?php if ( $desc ) : ?>
								<div class="wbtm-gw-desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
							<?php endif; ?>

							<div class="wbtm-gw-body" style="display:none;">
								<?php // Not a <form> on purpose: this sits inside the Settings API <form>, and nested forms are invalid HTML (the inner one is dropped by the browser). We serialize this container's inputs and save over AJAX instead. ?>
								<div class="wbtm-gw-form" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>">
									<table class="form-table wbtm-gw-form-table">
										<?php
										// WooCommerce's OWN field rendering for this gateway.
										echo $gateway->generate_settings_html( $gateway->get_form_fields(), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</table>
									<div class="wbtm-gw-form-footer">
										<button type="button" class="button button-primary wbtm-gw-save-btn"><?php esc_html_e( 'Save changes', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
										<span class="wbtm-gw-status"></span>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<?php $this->render_styles(); ?>
				</div>
				<?php
			}

			private function render_styles() {
				?>
				<style>
					.wbtm-wc-payment-manager { --wbtm-pay-accent:#F12971; display:block; width:100%; box-sizing:border-box; margin-top:8px; }
					.wbtm-wc-pm-bar { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
					.wbtm-wc-pm-heading { margin:0; font-size:15px; }
					.wbtm-wc-pm-wc-link { font-size:12px; font-weight:normal; }

					.wbtm-gw-card { border:1px solid #e7e8ec; border-radius:12px; background:#fff; margin-bottom:14px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.04); transition:box-shadow 0.18s ease; }
					.wbtm-gw-card:hover { box-shadow:0 4px 14px rgba(16,24,40,0.08); }
					.wbtm-gw-card.is-enabled { border-left:3px solid var(--wbtm-pay-accent); }
					.wbtm-gw-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; }
					.wbtm-gw-head-main { display:flex; align-items:center; gap:12px; }
					.wbtm-gw-title { font-size:14px; font-weight:600; color:#1d2327; }
					.wbtm-gw-badge { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.3px; padding:2px 8px; border-radius:9px; background:#f0f0f1; color:#646970; }
					.wbtm-gw-card.is-enabled .wbtm-gw-badge { background:#e6f4ea; color:#0a7c2f; }
					.wbtm-gw-desc { padding:0 16px 12px; color:#50575e; font-size:13px; }
					.wbtm-gw-desc p { margin:0 0 6px; }

					.wbtm-gw-body { padding:6px 16px 16px; border-top:1px solid #f0f0f1; background:#fbfbfc; }
					.wbtm-gw-form-table { width:100%; background:transparent; }
					.wbtm-gw-form-table th { width:200px; padding:14px 10px 14px 0; background:transparent; font-weight:600; vertical-align:top; }
					.wbtm-gw-form-table td { padding:12px 0; background:transparent; }
					.wbtm-gw-form-table input[type=text], .wbtm-gw-form-table input[type=password],
					.wbtm-gw-form-table input[type=email], .wbtm-gw-form-table input[type=number],
					.wbtm-gw-form-table textarea, .wbtm-gw-form-table select { min-width:320px; max-width:100%; }
					.wbtm-gw-form-footer { display:flex; align-items:center; gap:12px; margin-top:8px; padding-top:12px; border-top:1px solid #f0f0f1; }
					.wbtm-gw-status { font-size:13px; }
					.wbtm-gw-status.is-success { color:#0a7c2f; }
					.wbtm-gw-status.is-error { color:#d63638; }

					/* Toggle switch */
					.wbtm-gw-toggle { position:relative; display:inline-block; width:42px; height:24px; cursor:pointer; flex:0 0 auto; }
					.wbtm-gw-toggle-input {
						position:absolute; inset:0; margin:0; padding:0;
						width:100%; height:100%; min-width:0 !important; min-height:0 !important;
						opacity:0 !important; cursor:pointer; z-index:1;
						-webkit-appearance:none !important; -moz-appearance:none !important; appearance:none !important;
						background:none !important; border:none !important; box-shadow:none !important;
					}
					.wbtm-gw-toggle-input::before,
					.wbtm-gw-toggle-input::after { content:none !important; display:none !important; }
					.wbtm-gw-toggle-slider { position:absolute; inset:0; background:#b5b5ba; border-radius:24px; transition:background .2s; }
					.wbtm-gw-toggle-slider::before { content:''; position:absolute; height:18px; width:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
					.wbtm-gw-toggle-input:checked + .wbtm-gw-toggle-slider { background:var(--wbtm-pay-accent); }
					.wbtm-gw-toggle-input:checked + .wbtm-gw-toggle-slider::before { transform:translateX(18px); }
					.wbtm-gw-toggle-input:disabled + .wbtm-gw-toggle-slider { opacity:.5; cursor:not-allowed; }
				</style>
				<?php
			}
		}

		// Always instantiate so the admin_enqueue_scripts + AJAX hooks register.
		// (Required during plugin include, before WooCommerce has loaded — gating on
		// class_exists('WooCommerce') here would silently skip hook registration.
		// Each method guards WC availability internally.)
		WBTM_WC_Payment_Manager::instance();

	endif;
