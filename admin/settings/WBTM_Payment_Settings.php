<?php
	/**
	 * Payment settings tab for the Bus Global Settings page.
	 *
	 * Ported from the sibling rental plugin's RBFW_Payment_Settings, adapted to the
	 * wbtm_/WBTM_ naming convention and this plugin's own Settings API filter pattern
	 * (wbtm_settings_sec_reg / wbtm_settings_sec_fields).
	 *
	 * - Registers a new "Payments" tab via wbtm_settings_sec_reg.
	 * - Adds the sub-tabbed UI (WooCommerce / Custom Payment), WooCommerce fields,
	 *   and the PayPal / Stripe / Offline gateway cards via wbtm_settings_sec_fields.
	 * - Injects the gateway Configure modals + the WooCommerce install/activate
	 *   modal + the tab-switching script on admin_footer (raw HTML, so the SVG /
	 *   button / input markup is not stripped by the html field's wp_kses pass).
	 *
	 * Gateway credentials are stored in the wbtm_payment_settings option and are
	 * saved in real time over AJAX from their own modals, so they are protected
	 * from being wiped when the Settings API saves the rest of the form.
	 *
	 * All three Custom Payment gateways (PayPal, Stripe, Offline) are Pro-only — Configure
	 * is gated behind the Pro plugin (WBTM_Functions::is_pro_active()); the free version
	 * shows a PRO badge instead for each of them. (Unlike the sibling rental plugin, where
	 * Offline is fully functional in free — this plugin deliberately keeps all three gated.)
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'WBTM_Payment_Settings' ) ) :
		class WBTM_Payment_Settings {

			const OPTION = 'wbtm_payment_settings';
			const SCREEN = 'wbtm_bus_page_wbtm_settings_page';

			public function __construct() {
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'register_section' ), 15 );
				add_filter( 'wbtm_settings_sec_fields', array( $this, 'register_fields' ), 15 );

				add_action( 'admin_footer', array( $this, 'render_wc_warning_modal' ) );
				add_action( 'admin_footer', array( $this, 'render_gateway_modals' ) );
				add_action( 'admin_footer', array( $this, 'payment_tabs_script' ) );

				add_action( 'wp_ajax_wbtm_save_gateway_settings', array( $this, 'ajax_save_gateway_settings' ) );
				add_action( 'wp_ajax_wbtm_save_booking_mode', array( $this, 'ajax_save_booking_mode' ) );
				add_action( 'wp_ajax_wbtm_install_activate_wc', array( $this, 'ajax_install_activate_wc' ) );

				// Gateway keys are managed by their own AJAX modals and never travel with
				// the settings form, so preserve them when the Settings API saves the rest.
				add_filter( 'pre_update_option_' . self::OPTION, array( $this, 'preserve_gateway_keys' ), 10, 2 );
			}

			/** Is this the bus settings screen? */
			private function is_settings_screen() {
				$screen = get_current_screen();
				return $screen && ( $screen->id === self::SCREEN || strpos( $screen->id, 'wbtm_settings_page' ) !== false );
			}

			private function has_woo() {
				return class_exists( 'WBTM_Functions' ) ? WBTM_Functions::is_wc_active() : class_exists( 'WooCommerce' );
			}

			private function is_pro() {
				return class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_pro_active();
			}

			private function opt( $key, $default = '' ) {
				$o = get_option( self::OPTION, array() );
				return isset( $o[ $key ] ) ? $o[ $key ] : $default;
			}

			/**
			 * Add the "Payments" tab to the settings navigation.
			 *
			 * Plain text only — unlike the sibling rental plugin, this shell always
			 * runs the section title through esc_html() and renders its own icon
			 * from get_tab_configs() (see WBTM_Global_settings::get_tab_configs()),
			 * so embedding an <i> icon tag here would show up as literal text.
			 */
			public function register_section( $sections ) {
				$sections[] = array(
					'id'    => self::OPTION,
					'title' => esc_html__( 'Payments', 'bus-ticket-booking-with-seat-reservation' ),
				);

				return $sections;
			}

			/** Register the fields that make up the Payments tab. */
			public function register_fields( $settings_fields ) {
				$settings_fields[ self::OPTION ] = array(
					array(
						'name'     => 'wbtm_booking_mode_selector',
						'label'    => '',
						'callback' => array( $this, 'render_mode_selector' ),
					),
					array(
						'name'     => 'wbtm_payment_tabs_html',
						'label'    => '',
						'callback' => array( $this, 'render_sub_tabs' ),
					),
					array(
						'name'     => 'wbtm_wc_payment_gateways_manager',
						'label'    => '',
						'class'    => 'woocommerce-field wc-payment-methods-field',
						'callback' => array( $this, 'render_wc_payment_manager' ),
					),
					array(
						'name'    => 'wbtm_wc_add_to_cart_redirect',
						'label'   => __( 'After Adding to Cart, Redirect to', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'    => __( 'Select where to redirect after adding an item to the cart.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'    => 'select',
						'default' => 'checkout',
						'options' => array(
							'cart'     => __( 'Cart', 'bus-ticket-booking-with-seat-reservation' ),
							'checkout' => __( 'Checkout', 'bus-ticket-booking-with-seat-reservation' ),
						),
						'class'   => 'woocommerce-field wc-additional-field',
					),
					array(
						'name'    => 'wbtm_wc_require_login',
						'label'   => __( 'Require Account Login', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'    => __( 'Require login to complete a booking.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'    => 'checkbox',
						'default' => '',
						'class'   => 'woocommerce-field wc-additional-field',
					),
					array(
						'name'    => 'wbtm_wc_show_billing_info',
						'label'   => __( 'Show Billing Info', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'    => __( 'Show billing info on the WooCommerce checkout page.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'    => 'checkbox',
						'default' => '',
						'class'   => 'woocommerce-field wc-additional-field',
					),
					array(
						'name'    => 'wbtm_wc_confirm_status',
						'label'   => __( 'Confirm Booking Based on Payment Status', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'    => __( 'Select the order statuses that will confirm a booking.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'    => 'multicheck',
						'default' => array( 'processing' => 'processing', 'completed' => 'completed' ),
						'options' => array(
							'pending'    => __( 'Pending payment', 'bus-ticket-booking-with-seat-reservation' ),
							'processing' => __( 'Processing', 'bus-ticket-booking-with-seat-reservation' ),
							'on-hold'    => __( 'On hold', 'bus-ticket-booking-with-seat-reservation' ),
							'completed'  => __( 'Completed', 'bus-ticket-booking-with-seat-reservation' ),
						),
						'class'   => 'woocommerce-field wc-additional-field',
					),
					array(
						'name'     => 'wbtm_payment_gateways_ui',
						'label'    => '',
						'class'    => 'no-woocommerce-field payment-gateways-container',
						'callback' => array( $this, 'render_gateway_cards' ),
					),
				);

				return $settings_fields;
			}

			/**
			 * The "Booking Mode" selector — the single, explicit switch that decides whether
			 * WooCommerce or the standalone Custom Payment flow processes bookings.
			 *
			 * It saves in real time over its own AJAX handler (never through the main form),
			 * so its radios are named wbtm_booking_mode_radio, NOT the option key — the real
			 * value is written by WBTM_Functions::set_booking_mode(). When only one system is
			 * available the mode is auto-resolved, so this shows an explanatory note instead of
			 * a choice. Modelled on the sibling ecab-taxi-booking-manager / rental plugins'
			 * equivalent Booking Mode selectors.
			 */
			public function render_mode_selector() {
				if ( ! class_exists( 'WBTM_Functions' ) ) {
					return;
				}
				$availability = WBTM_Functions::mode_availability();

				if ( 'none' === $availability ) {
					?>
					<div class="wbtm-bm-auto-note wbtm-bm-auto-note--warn">
						<span class="dashicons dashicons-warning"></span>
						<p><?php esc_html_e( 'No booking flow is available yet: WooCommerce is not active and the Pro plugin is not active. Activate WooCommerce or the Pro plugin to start taking bookings.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				if ( 'woocommerce_only' === $availability ) {
					?>
					<div class="wbtm-bm-auto-note">
						<span class="dashicons dashicons-yes-alt"></span>
						<p><?php esc_html_e( 'Bookings are automatically processed through WooCommerce — it\'s the only booking flow available right now. Activate the Pro plugin to unlock the standalone Custom Payment flow (and a mode switch here).', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				if ( 'custom_only' === $availability ) {
					?>
					<div class="wbtm-bm-auto-note">
						<span class="dashicons dashicons-yes-alt"></span>
						<p><?php esc_html_e( 'Bookings are automatically processed through the Custom Payment flow — WooCommerce is not active. Activate WooCommerce to unlock the WooCommerce checkout flow (and a mode switch here).', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				// $availability === 'both': a real choice.
				$mode        = WBTM_Functions::booking_mode();
				$is_wc       = ( 'woocommerce' === $mode );
				$is_custom   = ( 'standalone' === $mode );
				$checker     = class_exists( 'WBTM_Payment_Status_Checker' ) ? new WBTM_Payment_Status_Checker() : null;
				$has_gateway = $checker ? $checker->has_gateway_for_active_mode() : true;
				?>
				<div class="wbtm-bm-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wbtm_save_booking_mode' ) ); ?>">
					<div class="wbtm-bm-head">
						<h3><?php esc_html_e( 'Booking Mode', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
						<p><?php esc_html_e( 'Choose exactly one flow to process bookings. This single switch decides everything below, so WooCommerce and Custom Payment never both try to handle the same booking. Your choice is saved instantly.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</div>

					<div class="wbtm-bm-cards">
						<label class="wbtm-bm-card<?php echo $is_wc ? ' is-selected' : ''; ?>" data-mode="woocommerce">
							<input type="radio" name="wbtm_booking_mode_radio" value="woocommerce" <?php checked( $is_wc ); ?>>
							<span class="wbtm-bm-card-icon dashicons dashicons-cart"></span>
							<span class="wbtm-bm-card-body">
								<span class="wbtm-bm-card-title-row">
									<strong><?php esc_html_e( 'WooCommerce Checkout', 'bus-ticket-booking-with-seat-reservation' ); ?></strong>
									<span class="wbtm-bm-card-badge"><?php esc_html_e( 'Active', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</span>
								<span class="wbtm-bm-card-desc"><?php esc_html_e( 'Bookings go through the WooCommerce cart, checkout, and orders.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							</span>
						</label>
						<label class="wbtm-bm-card<?php echo $is_custom ? ' is-selected' : ''; ?>" data-mode="standalone">
							<input type="radio" name="wbtm_booking_mode_radio" value="standalone" <?php checked( $is_custom ); ?>>
							<span class="wbtm-bm-card-icon dashicons dashicons-money-alt"></span>
							<span class="wbtm-bm-card-body">
								<span class="wbtm-bm-card-title-row">
									<strong><?php esc_html_e( 'Custom Payment (Standalone)', 'bus-ticket-booking-with-seat-reservation' ); ?></strong>
									<span class="wbtm-bm-card-badge"><?php esc_html_e( 'Active', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</span>
								<span class="wbtm-bm-card-desc"><?php esc_html_e( 'Bookings are taken directly via PayPal, Stripe, or Offline payment — no WooCommerce.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							</span>
						</label>
					</div>

					<p class="wbtm-bm-status" role="status" aria-live="polite"></p>

					<div class="wbtm-bm-gateway-warning-slot">
						<?php if ( ! $has_gateway ) : ?>
							<div class="wbtm-bm-gateway-warning">
								<span class="dashicons dashicons-warning"></span>
								<p>
									<?php if ( $is_wc ) : ?>
										<?php esc_html_e( 'WooCommerce mode is selected, but no WooCommerce payment gateway is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'bus-ticket-booking-with-seat-reservation' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Custom Payment mode is selected, but no gateway (PayPal, Stripe, or Offline) is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'bus-ticket-booking-with-seat-reservation' ); ?>
									<?php endif; ?>
								</p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php $this->booking_mode_styles(); ?>
				<script>
				jQuery(function($){
					var $wrap = $('.wbtm-bm-wrap');
					if (!$wrap.length) { return; }
					var nonce = $wrap.data('nonce');
					var i18n = {
						saving: <?php echo wp_json_encode( __( 'Saving…', 'bus-ticket-booking-with-seat-reservation' ) ); ?>,
						saved:  <?php echo wp_json_encode( __( 'Booking mode saved.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>,
						error:  <?php echo wp_json_encode( __( 'Could not save. Please try again.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>,
						wcWarn: <?php echo wp_json_encode( __( 'WooCommerce mode is selected, but no WooCommerce payment gateway is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>,
						customWarn: <?php echo wp_json_encode( __( 'Custom Payment mode is selected, but no gateway (PayPal, Stripe, or Offline) is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>
					};

					// window.wbtmToast is the plugin's shared global toast helper (see
					// assets/admin/js/wbtm-toast.js, loaded on every plugin admin screen
					// via WBTM_Global_File_Load::admin_enqueue()) — falls back to the old
					// inline status text if it's somehow unavailable, so this never breaks.
					function notify(message, type) {
						if (window.wbtmToast) {
							window.wbtmToast[type](message);
						} else {
							$wrap.find('.wbtm-bm-status').show().text(message)
								.css('color', type === 'success' ? '#0a7c2f' : '#d63638');
						}
					}

					$wrap.on('click', '.wbtm-bm-card', function(){
						var $card = $(this), mode = $card.data('mode');
						if ($card.hasClass('is-selected')) { return; }

						$wrap.find('.wbtm-bm-card').removeClass('is-selected');
						$card.addClass('is-selected').find('input[type=radio]').prop('checked', true);
						var $status = $wrap.find('.wbtm-bm-status').text(i18n.saving);

						$.post(ajaxurl, { action:'wbtm_save_booking_mode', nonce:nonce, mode:mode })
							.done(function(res){
								if (res && res.success) {
									$status.text('');
									notify(i18n.saved, 'success');

									// Refresh the "Active" badge on the sub-tab bar.
									$('.wbtm-pay-subtab-badge').hide();
									$('.wbtm-pay-subtab-badge[data-badge-for="'+mode+'"]').show();

									// Jump to the matching sub-tab so it can be configured right away.
									var targetHref = (mode === 'standalone') ? '#no-woocommerce-field' : '#woocommerce-field';
									$('.payment-sub-tabs .nav-tab[href="'+targetHref+'"]').trigger('click');

									// Refresh the "no gateway enabled" warning for the newly active mode.
									var $slot = $wrap.find('.wbtm-bm-gateway-warning-slot').empty();
									if (res.data && res.data.has_gateway === false) {
										var msg = (mode === 'woocommerce') ? i18n.wcWarn : i18n.customWarn;
										$slot.append('<div class="wbtm-bm-gateway-warning"><span class="dashicons dashicons-warning"></span><p>'+msg+'</p></div>');
										notify(msg, 'warning');
									}
								} else {
									$status.text('');
									notify((res && res.data) ? res.data : i18n.error, 'error');
								}
							})
							.fail(function(){ $status.text(''); notify(i18n.error, 'error'); });
					});
				});
				</script>
				<?php
			}

			/** Styles for the Booking Mode selector + its auto-detected notices. Printed once. */
			private function booking_mode_styles() {
				static $printed = false;
				if ( $printed ) {
					return;
				}
				$printed = true;
				?>
				<style>
				.wbtm-bm-wrap,.wbtm-bm-wrap *,.wbtm-bm-auto-note,.wbtm-bm-auto-note *{box-sizing:border-box;}
				.wbtm-bm-wrap{margin:2px 0 18px;max-width:100%;}
				.wbtm-bm-head h3{margin:0 0 2px;font-size:15px;font-weight:700;color:#1d2327;}
				.wbtm-bm-head p{margin:0 0 12px;font-size:12.5px;color:#6b7280;max-width:680px;line-height:1.55;}
				.wbtm-bm-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:100%;}
				.wbtm-bm-card{position:relative;display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid #e5e7eb;border-radius:12px;background:#fafafb;cursor:pointer;transition:border-color .15s,box-shadow .15s,background .15s;min-width:0;}
				.wbtm-bm-card:hover{border-color:#d4b3c3;box-shadow:0 4px 14px rgba(16,24,40,0.06);}
				.wbtm-bm-card.is-selected{border-color:#F12971;background:#fff;box-shadow:0 6px 18px rgba(241,41,113,0.12);}
				.wbtm-bm-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
				.wbtm-bm-card-icon{flex:0 0 auto;width:36px;height:36px;border-radius:9px;background:rgba(241,41,113,0.1);color:#F12971;display:flex !important;align-items:center !important;justify-content:center !important;font-size:18px;}
				.wbtm-bm-card-body{display:block !important;flex:1;min-width:0;white-space:normal !important;}
				.wbtm-bm-card-title-row{display:flex !important;align-items:center;justify-content:space-between;gap:8px;margin:0 0 4px;width:100%;}
				.wbtm-bm-card-body strong{display:inline-block !important;font-size:14px;line-height:1.3;color:#1d2327;}
				.wbtm-bm-card-desc{display:block !important;font-size:12px;color:#6b7280;line-height:1.5;overflow-wrap:break-word;}
				.wbtm-bm-card-badge{flex:0 0 auto;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:#dcfce7;color:#166534;padding:1px 8px;border-radius:20px;display:none !important;}
				.wbtm-bm-card.is-selected .wbtm-bm-card-badge{display:inline-block !important;}
				.wbtm-bm-status{min-height:16px;margin:8px 2px 0;font-size:12px;font-weight:600;}
				.wbtm-bm-gateway-warning{display:flex;align-items:flex-start;gap:8px;margin-top:10px;padding:9px 12px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;}
				.wbtm-bm-gateway-warning p{margin:0;}
				.wbtm-bm-auto-note{display:flex;align-items:flex-start;gap:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;border-radius:10px;padding:10px 14px;margin:4px 0 14px;font-size:12.5px;}
				.wbtm-bm-auto-note--warn{background:#fef2f2;border-color:#fecaca;color:#991b1b;}
				.wbtm-bm-auto-note p{margin:0;}
				.wbtm-pay-subtab-badge{margin-left:6px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:rgba(255,255,255,0.9);color:#166534;padding:1px 7px;border-radius:20px;vertical-align:middle;}
				@media (max-width:680px){.wbtm-bm-cards{grid-template-columns:1fr;}}
				</style>
				<?php
			}

			/** Sub-tab bar (WooCommerce / Custom Payment) + WC-inactive warning. */
			public function render_sub_tabs() {
				$wc_active    = $this->has_woo();
				$is_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
				$btn_text     = $is_installed
					? __( 'Activate WooCommerce Now', 'bus-ticket-booking-with-seat-reservation' )
					: __( 'Install &amp; Activate Now', 'bus-ticket-booking-with-seat-reservation' );

				// Default the active sub-tab to whichever flow currently owns bookings, so the
				// Custom Payment gateways aren't the first thing shown when WooCommerce is the mode.
				$mode           = class_exists( 'WBTM_Functions' ) ? WBTM_Functions::booking_mode() : 'woocommerce';
				$custom_is_mode = ( 'standalone' === $mode );
				?>
				<div class="payment-sub-tabs-wrapper">
					<h2 class="nav-tab-wrapper payment-sub-tabs">
						<a href="#woocommerce-field" class="nav-tab<?php echo $custom_is_mode ? '' : ' nav-tab-active'; ?>">
							<?php esc_html_e( 'WooCommerce', 'bus-ticket-booking-with-seat-reservation' ); ?>
							<span class="wbtm-pay-subtab-badge" data-badge-for="woocommerce"<?php echo $custom_is_mode ? ' style="display:none;"' : ''; ?>><?php esc_html_e( 'Active', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</a>
						<a href="#no-woocommerce-field" class="nav-tab<?php echo $custom_is_mode ? ' nav-tab-active' : ''; ?>">
							<?php esc_html_e( 'Custom Payment', 'bus-ticket-booking-with-seat-reservation' ); ?>
							<span class="wbtm-pay-subtab-badge" data-badge-for="standalone"<?php echo $custom_is_mode ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Active', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</a>
					</h2>
					<?php if ( ! $wc_active ) : ?>
						<div class="woocommerce-field">
							<div class="wbtm-woo-warning-notice" style="background:#fff3cd;color:#856404;padding:15px;border-left:4px solid #ffeeba;border-radius:6px;margin:15px 0 10px;">
								<div style="display:flex;flex-direction:column;align-items:flex-start;gap:15px;">
									<div style="width:100%;">
										<strong style="display:block;font-size:14px;margin-bottom:5px;"><i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i><?php esc_html_e( 'Notice: WooCommerce is Not Activated', 'bus-ticket-booking-with-seat-reservation' ); ?></strong>
										<span style="font-size:13px;display:block;"><?php esc_html_e( 'To process bookings through the WooCommerce cart/checkout flow, you must install and activate WooCommerce. Otherwise, use the Custom Payment tab.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									</div>
									<div>
										<button type="button" class="button button-primary wbtm-install-wc-trigger" style="white-space:nowrap;"><?php echo wp_kses_post( $btn_text ); ?></button>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<?php
			}

			/** PayPal / Stripe / Offline gateway cards + booking confirmation page. */
			public function render_gateway_cards() {
				$is_pro      = $this->is_pro();
				$pp_enabled  = $this->opt( 'wbtm_paypal_enable' ) === 'on';
				$st_enabled  = $this->opt( 'wbtm_stripe_enable' ) === 'on';
				$off_enabled = $this->opt( 'wbtm_offline_enable' ) === 'on';
				$conf_page   = absint( $this->opt( 'wbtm_confirmation_page_id', 0 ) );

				$enabled_txt  = __( 'Enabled', 'bus-ticket-booking-with-seat-reservation' );
				$disabled_txt = __( 'Disabled', 'bus-ticket-booking-with-seat-reservation' );
				$pro_badge    = '<span class="wbtm-gw-pro-badge" title="' . esc_attr__( 'Available in Pro version', 'bus-ticket-booking-with-seat-reservation' ) . '">PRO</span>';
				?>
				<div class="wbtm-gw-intro">
					<h3><?php esc_html_e( 'Custom Payment Gateways', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
					<p><?php esc_html_e( 'Accept payments directly without WooCommerce. Configure a gateway below, then enable it for the Standalone / Custom Payment checkout.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				</div>

				<!-- PayPal Card -->
				<div class="gateway-card paypal-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106z" fill="#fff"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'PayPal', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Cards & PayPal balance', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							</span>
						</div>
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $pp_enabled ? 'active' : ''; ?>"><?php echo esc_html( $pp_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="wbtm-paypal-configure-btn"><?php esc_html_e( 'Configure', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Stripe Card -->
				<div class="gateway-card stripe-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="26" height="26" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
									<path fill="#fff" d="M14.07 15.11c-1.85-.43-2.61-.79-2.61-1.63 0-.79.75-1.33 1.95-1.33 1.34 0 2.87.41 4.31 1.09V8.65c-1.39-.56-2.93-.84-4.52-.84-3.8 0-6.66 1.96-6.66 5.25 0 3.73 3.32 4.96 6.03 5.61 2.05.49 2.8.92 2.8 1.8 0 .86-.87 1.48-2.3 1.48-1.57 0-3.37-.53-5.06-1.54v4.75c1.67.75 3.59 1.13 5.51 1.13 4.13 0 7-2 7-5.34-.01-3.6-3.6-4.41-6.45-5.84z"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'Stripe', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Credit & debit cards', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							</span>
						</div>
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $st_enabled ? 'active' : ''; ?>"><?php echo esc_html( $st_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="wbtm-stripe-configure-btn"><?php esc_html_e( 'Configure', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Offline Payment Card -->
				<div class="gateway-card offline-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M3 19h18a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
									<path d="M2 10h20M6 14h4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'Offline Payment', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Bank transfer, cash, pay on pickup', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							</span>
						</div>
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $off_enabled ? 'active' : ''; ?>"><?php echo esc_html( $off_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="wbtm-offline-configure-btn"><?php esc_html_e( 'Configure', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Booking Confirmation Page -->
				<?php $req_login = $this->opt( 'wbtm_require_login', 'on' ) !== 'off'; ?>
				<div class="wbtm-conf-page">
					<div class="wbtm-conf-page-label">
						<label><?php esc_html_e( 'Require Account Login', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
						<span><?php esc_html_e( 'Require customers to log in or register before booking. When on, guests see an inline Login / Register panel; when off, guest checkout is allowed and customers can track a booking with their email and reference.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
					</div>
					<div class="wbtm-conf-page-field">
						<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[wbtm_require_login]" value="off">
						<label class="wbtm-gw-switch"><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[wbtm_require_login]" value="on" <?php checked( $req_login ); ?>><span class="wbtm-gw-slider"></span></label>
					</div>
				</div>

				<div class="wbtm-conf-page">
					<div class="wbtm-conf-page-label">
						<label><?php esc_html_e( 'Booking Confirmation Page', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
						<span><?php esc_html_e( 'In Standalone / Custom Payment mode, customers are shown a confirmation after booking. Optionally choose a dedicated page here.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
					</div>
					<div class="wbtm-conf-page-field">
						<?php
							wp_dropdown_pages( array(
								'name'              => self::OPTION . '[wbtm_confirmation_page_id]',
								'id'                => 'wbtm_confirmation_page_id',
								'selected'          => $conf_page,
								'show_option_none'  => __( '— Default —', 'bus-ticket-booking-with-seat-reservation' ),
								'option_none_value' => '0',
							) );
						?>
					</div>
				</div>
				<?php
			}

			/** WooCommerce native payment-methods manager (inside the Payment Methods accordion). */
			public function render_wc_payment_manager() {
				if ( class_exists( 'WooCommerce' ) && class_exists( 'WBTM_WC_Payment_Manager' ) ) {
					WBTM_WC_Payment_Manager::instance()->render();
				}
			}

			/** WooCommerce install / activate modal (footer). */
			public function render_wc_warning_modal() {
				if ( ! $this->is_settings_screen() || $this->has_woo() ) {
					return;
				}
				$is_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
				$modal_desc   = $is_installed
					? __( 'WooCommerce is already installed but not active. Click the button below to activate it now.', 'bus-ticket-booking-with-seat-reservation' )
					: __( 'WooCommerce is required to process payments through the cart/checkout flow. We will securely download, install, and activate it for you now.', 'bus-ticket-booking-with-seat-reservation' );
				$modal_btn    = $is_installed
					? __( 'Activate WooCommerce Now', 'bus-ticket-booking-with-seat-reservation' )
					: __( 'Install &amp; Activate Now', 'bus-ticket-booking-with-seat-reservation' );
				?>
				<div id="wbtm-wc-install-modal" style="display:none;position:fixed;z-index:999999;inset:0;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
					<div style="background:#fff;border-radius:12px;width:520px;max-width:92vw;box-shadow:0 10px 40px rgba(0,0,0,0.35);overflow:hidden;">
						<div style="padding:18px 24px;border-bottom:1px solid #e2e4e7;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;">
							<h3 style="margin:0;font-size:17px;color:#2c3338;display:flex;align-items:center;gap:8px;">
								<span class="dashicons dashicons-plugins-checked" style="font-size:20px;color:#2271b1;"></span>
								<?php esc_html_e( 'Set Up WooCommerce', 'bus-ticket-booking-with-seat-reservation' ); ?>
							</h3>
							<button type="button" id="wbtm-wc-install-modal-close" style="background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#666;padding:0;">&times;</button>
						</div>
						<div style="padding:24px;">
							<div id="wbtm-wc-modal-info">
								<p style="margin:0 0 18px;font-size:14px;color:#3c434a;line-height:1.6;"><?php echo esc_html( $modal_desc ); ?></p>
								<button type="button" id="wbtm-wc-modal-action-btn" class="button button-primary" style="white-space:nowrap;padding:6px 18px;"><?php echo wp_kses_post( $modal_btn ); ?></button>
							</div>
							<div id="wbtm-wc-modal-progress" style="display:none;">
								<div style="width:100%;height:8px;background:#f0f0f1;border-radius:100px;overflow:hidden;margin-bottom:10px;">
									<div id="wbtm-wc-modal-progress-fill" style="height:100%;width:0%;border-radius:100px;background:linear-gradient(90deg,#7b5ea7,#9b72cf);transition:width 0.5s cubic-bezier(0.16,1,0.3,1);"></div>
								</div>
								<p id="wbtm-wc-modal-status-text" style="font-size:13px;color:#50575e;margin:0;text-align:center;min-height:20px;"></p>
							</div>
						</div>
					</div>
				</div>
				<script>
				jQuery(function($){
					var wbtmWcIsInstalled = <?php echo $is_installed ? 'true' : 'false'; ?>;
					var wbtmWcNonce       = '<?php echo esc_js( wp_create_nonce( 'wbtm_install_wc' ) ); ?>';

					$(document).on('click', '.wbtm-install-wc-trigger', function(e){
						e.preventDefault();
						$('#wbtm-wc-install-modal').css('display','flex').hide().fadeIn(200);
					});
					$('#wbtm-wc-install-modal-close').on('click', function(){ $('#wbtm-wc-install-modal').fadeOut(200); });
					$(document).on('click', '#wbtm-wc-install-modal', function(e){
						if ($(e.target).is('#wbtm-wc-install-modal')) { $(this).fadeOut(200); }
					});

					$('#wbtm-wc-modal-action-btn').on('click', function(){
						var $info=$('#wbtm-wc-modal-info'), $progress=$('#wbtm-wc-modal-progress'),
						    $fill=$('#wbtm-wc-modal-progress-fill'), $status=$('#wbtm-wc-modal-status-text');
						$info.hide(); $fill.css('width','0%'); $progress.fadeIn(200);
						var texts = wbtmWcIsInstalled
							? [<?php echo implode( ',', array_map( 'wp_json_encode', array(
								__( 'Activating WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Configuring settings...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Finalizing setup...', 'bus-ticket-booking-with-seat-reservation' ),
							) ) ); ?>]
							: [<?php echo implode( ',', array_map( 'wp_json_encode', array(
								__( 'Downloading WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Installing WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Activating WooCommerce...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Configuring settings...', 'bus-ticket-booking-with-seat-reservation' ),
								__( 'Finalizing...', 'bus-ticket-booking-with-seat-reservation' ),
							) ) ); ?>];
						var duration=wbtmWcIsInstalled?3000:15000, startTime=Date.now(), isDone=false, frameId;
						$status.text(texts[0]);
						function animateBar(){
							if(isDone) return;
							var raw=Math.min((Date.now()-startTime)/duration,1), pct=raw*(2-raw)*95;
							$fill.css('width',pct+'%');
							var idx=Math.min(Math.floor((pct/95)*texts.length),texts.length-1);
							$status.text(texts[idx]+' '+Math.round(pct)+'%');
							if(pct<95) frameId=requestAnimationFrame(animateBar);
						}
						frameId=requestAnimationFrame(animateBar);
						$.ajax({
							url: ajaxurl, type:'POST',
							data:{ action:'wbtm_install_activate_wc', nonce:wbtmWcNonce },
							success: function(response){
								var minWait=wbtmWcIsInstalled?1500:3000, leftover=Math.max(0,minWait-(Date.now()-startTime));
								setTimeout(function(){
									isDone=true; cancelAnimationFrame(frameId); $fill.css('width','100%');
									if(response.success){
										$status.css('color','#039855').text(<?php echo wp_json_encode( __( 'Successfully Activated! 100%', 'bus-ticket-booking-with-seat-reservation' ) ); ?>);
										setTimeout(function(){ location.reload(); }, 1200);
									} else {
										$status.css('color','#d92d20').text(<?php echo wp_json_encode( __( 'Error: ', 'bus-ticket-booking-with-seat-reservation' ) ); ?> + (response.data||'Unknown error'));
										setTimeout(function(){ $progress.hide(); $info.show(); }, 5000);
									}
								}, leftover);
							},
							error: function(){
								isDone=true; cancelAnimationFrame(frameId); $fill.css('width','100%');
								$status.css('color','#d92d20').text(<?php echo wp_json_encode( __( 'A network error occurred. Please try again.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>);
								setTimeout(function(){ $progress.hide(); $info.show(); }, 5000);
							}
						});
					});
				});
				</script>
				<?php
			}

			/** PayPal / Stripe / Offline Configure modals (footer). Pro-only for PayPal/Stripe. */
			public function render_gateway_modals() {
				if ( ! $this->is_settings_screen() ) {
					return;
				}
				$pp_enabled  = $this->opt( 'wbtm_paypal_enable' ) === 'on';
				$pp_sandbox  = $this->opt( 'wbtm_paypal_sandbox' ) === 'on';
				$pp_client   = esc_attr( $this->opt( 'wbtm_paypal_client_id' ) );
				$pp_secret   = esc_attr( $this->opt( 'wbtm_paypal_secret' ) );
				$st_enabled  = $this->opt( 'wbtm_stripe_enable' ) === 'on';
				$st_sandbox  = $this->opt( 'wbtm_stripe_sandbox' ) === 'on';
				$st_test_pub = esc_attr( $this->opt( 'wbtm_stripe_test_pub' ) );
				$st_test_sec = esc_attr( $this->opt( 'wbtm_stripe_test_sec' ) );
				$st_live_pub = esc_attr( $this->opt( 'wbtm_stripe_live_pub' ) );
				$st_live_sec = esc_attr( $this->opt( 'wbtm_stripe_live_sec' ) );
				$off_enabled = $this->opt( 'wbtm_offline_enable' ) === 'on';
				$off_label   = esc_attr( $this->opt( 'wbtm_offline_label', __( 'Offline Payment', 'bus-ticket-booking-with-seat-reservation' ) ) );
				$nonce       = wp_create_nonce( 'wbtm_save_gateway' );
				$is_pro      = $this->is_pro();
				?>
				<style>
				.wbtm-gw-modal{display:none;position:fixed;inset:0;z-index:999999;background:rgba(10,10,30,0.65);align-items:center;justify-content:center;backdrop-filter:blur(3px);}
				.wbtm-gw-modal-box{background:#fff;border-radius:16px;width:540px;max-width:94vw;max-height:92vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,0.3);}
				.wbtm-gw-modal-header{padding:22px 26px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;}
				.wbtm-gw-modal-header h2{margin:0;font-size:19px;font-weight:700;color:#fff;display:flex;align-items:center;gap:12px;}
				.wbtm-gw-modal-close{background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:34px;height:34px;font-size:20px;line-height:1;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;}
				.wbtm-gw-modal-body{padding:26px 26px 10px;}
				.wbtm-gw-field{margin-bottom:20px;}
				.wbtm-gw-field label.wbtm-gw-label{display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:7px;}
				.wbtm-gw-field input[type="text"],.wbtm-gw-field input[type="password"]{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:14px;color:#111;background:#f9fafb;box-sizing:border-box;}
				.wbtm-gw-field input[type="text"]:focus,.wbtm-gw-field input[type="password"]:focus{border-color:#F12971;box-shadow:0 0 0 3px rgba(241,41,113,0.12);outline:none;background:#fff;}
				.wbtm-gw-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f9fafb;border-radius:10px;margin-bottom:20px;border:1.5px solid #e5e7eb;}
				.wbtm-gw-toggle-label{font-weight:600;font-size:14px;color:#111827;}
				.wbtm-gw-toggle-sub{font-size:12px;color:#6b7280;margin-top:2px;}
				.wbtm-gw-divider{border:none;border-top:1px solid #e5e7eb;margin:4px 0 20px;}
				.wbtm-gw-section-title{font-size:12px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:14px;}
				.wbtm-gw-modal-footer{padding:16px 26px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
				.wbtm-gw-save-btn{padding:11px 28px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;color:#fff;flex-shrink:0;}
				.wbtm-gw-save-msg{display:none;padding:9px 14px;border-radius:7px;font-size:13px;font-weight:500;flex:1;}
				.wbtm-gw-switch{position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0;}
				.wbtm-gw-switch input{opacity:0;width:0;height:0;}
				.wbtm-gw-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:26px;transition:0.3s;}
				.wbtm-gw-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);}
				.wbtm-gw-switch input:checked + .wbtm-gw-slider{background:#22c55e;}
				.wbtm-gw-switch input:checked + .wbtm-gw-slider:before{transform:translateX(22px);}
				</style>

				<?php if ( $is_pro ) : ?>
				<!-- PayPal Config Modal -->
				<div id="wbtm-paypal-modal" class="wbtm-gw-modal">
					<div class="wbtm-gw-modal-box">
						<div class="wbtm-gw-modal-header" style="background:linear-gradient(135deg,#003087 0%,#0079C1 100%);">
							<h2><?php esc_html_e( 'PayPal Configuration', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
							<button type="button" class="wbtm-gw-modal-close">&times;</button>
						</div>
						<div class="wbtm-gw-modal-body">
							<div class="wbtm-gw-toggle-row">
								<div>
									<div class="wbtm-gw-toggle-label"><?php esc_html_e( 'Enable PayPal', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
									<div class="wbtm-gw-toggle-sub"><?php esc_html_e( 'Accept payments via PayPal', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
								</div>
								<label class="wbtm-gw-switch"><input type="checkbox" data-field="wbtm_paypal_enable" <?php checked( $pp_enabled ); ?>><span class="wbtm-gw-slider"></span></label>
							</div>
							<div class="wbtm-gw-toggle-row">
								<div>
									<div class="wbtm-gw-toggle-label"><?php esc_html_e( 'Sandbox / Test Mode', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
									<div class="wbtm-gw-toggle-sub"><?php esc_html_e( 'Use sandbox credentials for testing', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
								</div>
								<label class="wbtm-gw-switch"><input type="checkbox" data-field="wbtm_paypal_sandbox" <?php checked( $pp_sandbox ); ?>><span class="wbtm-gw-slider"></span></label>
							</div>
							<hr class="wbtm-gw-divider">
							<p class="wbtm-gw-section-title"><?php esc_html_e( 'API Credentials', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'PayPal Client ID', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" data-field="wbtm_paypal_client_id" value="<?php echo $pp_client; ?>" placeholder="<?php esc_attr_e( 'Enter your PayPal Client ID', 'bus-ticket-booking-with-seat-reservation' ); ?>">
							</div>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'PayPal Secret Key', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="password" data-field="wbtm_paypal_secret" value="<?php echo $pp_secret; ?>" placeholder="<?php esc_attr_e( 'Enter your PayPal Secret Key', 'bus-ticket-booking-with-seat-reservation' ); ?>">
							</div>
						</div>
						<div class="wbtm-gw-modal-footer">
							<button type="button" class="wbtm-gw-save-btn" data-gateway="paypal" style="background:linear-gradient(135deg,#003087,#0079C1);"><?php esc_html_e( 'Save PayPal Settings', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<span class="wbtm-gw-save-msg"></span>
						</div>
					</div>
				</div>

				<!-- Stripe Config Modal -->
				<div id="wbtm-stripe-modal" class="wbtm-gw-modal">
					<div class="wbtm-gw-modal-box">
						<div class="wbtm-gw-modal-header" style="background:linear-gradient(135deg,#635bff 0%,#3f36c5 100%);">
							<h2><?php esc_html_e( 'Stripe Configuration', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
							<button type="button" class="wbtm-gw-modal-close">&times;</button>
						</div>
						<div class="wbtm-gw-modal-body">
							<div class="wbtm-gw-toggle-row">
								<div>
									<div class="wbtm-gw-toggle-label"><?php esc_html_e( 'Enable Stripe', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
									<div class="wbtm-gw-toggle-sub"><?php esc_html_e( 'Accept payments via Stripe', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
								</div>
								<label class="wbtm-gw-switch"><input type="checkbox" data-field="wbtm_stripe_enable" <?php checked( $st_enabled ); ?>><span class="wbtm-gw-slider"></span></label>
							</div>
							<div class="wbtm-gw-toggle-row">
								<div>
									<div class="wbtm-gw-toggle-label"><?php esc_html_e( 'Sandbox / Test Mode', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
									<div class="wbtm-gw-toggle-sub"><?php esc_html_e( 'Use test keys instead of live keys', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
								</div>
								<label class="wbtm-gw-switch"><input type="checkbox" data-field="wbtm_stripe_sandbox" <?php checked( $st_sandbox ); ?>><span class="wbtm-gw-slider"></span></label>
							</div>
							<hr class="wbtm-gw-divider">
							<p class="wbtm-gw-section-title"><?php esc_html_e( 'Test / Sandbox Keys', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'Test Publishable Key', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" data-field="wbtm_stripe_test_pub" value="<?php echo $st_test_pub; ?>" placeholder="pk_test_...">
							</div>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'Test Secret Key', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="password" data-field="wbtm_stripe_test_sec" value="<?php echo $st_test_sec; ?>" placeholder="sk_test_...">
							</div>
							<hr class="wbtm-gw-divider">
							<p class="wbtm-gw-section-title"><?php esc_html_e( 'Live Keys', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'Live Publishable Key', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" data-field="wbtm_stripe_live_pub" value="<?php echo $st_live_pub; ?>" placeholder="pk_live_...">
							</div>
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'Live Secret Key', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="password" data-field="wbtm_stripe_live_sec" value="<?php echo $st_live_sec; ?>" placeholder="sk_live_...">
							</div>
						</div>
						<div class="wbtm-gw-modal-footer">
							<button type="button" class="wbtm-gw-save-btn" data-gateway="stripe" style="background:linear-gradient(135deg,#635bff,#3f36c5);"><?php esc_html_e( 'Save Stripe Settings', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<span class="wbtm-gw-save-msg"></span>
						</div>
					</div>
				</div>

				<!-- Offline Payment Config Modal -->
				<div id="wbtm-offline-modal" class="wbtm-gw-modal">
					<div class="wbtm-gw-modal-box">
						<div class="wbtm-gw-modal-header" style="background:linear-gradient(135deg,#0f766e 0%,#115e59 100%);">
							<h2><?php esc_html_e( 'Offline Payment Configuration', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
							<button type="button" class="wbtm-gw-modal-close">&times;</button>
						</div>
						<div class="wbtm-gw-modal-body">
							<div class="wbtm-gw-toggle-row">
								<div>
									<div class="wbtm-gw-toggle-label"><?php esc_html_e( 'Enable Offline Payment', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
									<div class="wbtm-gw-toggle-sub"><?php esc_html_e( 'Let customers pay offline (bank transfer, cash, pay on pickup).', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
								</div>
								<label class="wbtm-gw-switch"><input type="checkbox" data-field="wbtm_offline_enable" <?php checked( $off_enabled ); ?>><span class="wbtm-gw-slider"></span></label>
							</div>
							<hr class="wbtm-gw-divider">
							<div class="wbtm-gw-field">
								<label class="wbtm-gw-label"><?php esc_html_e( 'Payment Label', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" data-field="wbtm_offline_label" value="<?php echo $off_label; ?>" placeholder="<?php esc_attr_e( 'e.g. Pay on Pickup / Bank Transfer', 'bus-ticket-booking-with-seat-reservation' ); ?>">
								<p style="margin:8px 0 0;font-size:12px;color:#6b7280;"><?php esc_html_e( 'This label is shown to customers on the frontend payment step.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							</div>
						</div>
						<div class="wbtm-gw-modal-footer">
							<button type="button" class="wbtm-gw-save-btn" data-gateway="offline" style="background:linear-gradient(135deg,#0f766e,#115e59);"><?php esc_html_e( 'Save Offline Settings', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<span class="wbtm-gw-save-msg"></span>
						</div>
					</div>
				<?php endif; ?>
				</div>

				<script>
				var wbtmGateway = <?php echo wp_json_encode( array(
					'nonce'    => $nonce,
					'enabled'  => __( 'Enabled', 'bus-ticket-booking-with-seat-reservation' ),
					'disabled' => __( 'Disabled', 'bus-ticket-booking-with-seat-reservation' ),
				) ); ?>;
				jQuery(function($){
					$(document).on('click', '#wbtm-paypal-configure-btn', function(e){ e.preventDefault(); $('#wbtm-paypal-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '#wbtm-stripe-configure-btn', function(e){ e.preventDefault(); $('#wbtm-stripe-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '#wbtm-offline-configure-btn', function(e){ e.preventDefault(); $('#wbtm-offline-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '.wbtm-gw-modal-close', function(){ $('.wbtm-gw-modal').fadeOut(200); });
					$(document).on('click', '.wbtm-gw-modal', function(e){ if ($(e.target).hasClass('wbtm-gw-modal')) $(this).fadeOut(200); });

					$(document).on('click', '.wbtm-gw-save-btn', function(e){
						e.preventDefault();
						var $btn=$(this), $box=$btn.closest('.wbtm-gw-modal-box'), gateway=$btn.data('gateway'),
						    $msg=$box.find('.wbtm-gw-save-msg'), fields={};
						$box.find('input[data-field]').each(function(){
							var key=$(this).data('field');
							fields[key]=($(this).attr('type')==='checkbox') ? ($(this).is(':checked')?'on':'off') : $(this).val();
						});
						$btn.prop('disabled',true).css('opacity','0.7'); $msg.hide();
						$.ajax({
							url: ajaxurl, type:'POST',
							data:{ action:'wbtm_save_gateway_settings', nonce:wbtmGateway.nonce, gateway:gateway, fields:fields },
							success: function(res){
								if(res.success){
									$msg.css({'color':'#0f5132','background':'#d1e7dd','border':'1px solid #badbcc'}).text(res.data).fadeIn(200);
									setTimeout(function(){ $msg.fadeOut(400); }, 1200);
									var $badge=$('.'+gateway+'-card .gateway-status');
									if($badge.length){
										var isEnabled = fields['wbtm_'+gateway+'_enable']==='on';
										$badge.text(isEnabled?wbtmGateway.enabled:wbtmGateway.disabled).toggleClass('active',isEnabled);
									}
								} else {
									$msg.css({'color':'#842029','background':'#f8d7da','border':'1px solid #f5c2c7'}).text(res.data).fadeIn(200);
									setTimeout(function(){ $msg.fadeOut(400); }, 1500);
								}
							},
							error: function(){
								$msg.css({'color':'#842029','background':'#f8d7da','border':'1px solid #f5c2c7'}).text('A network error occurred.').fadeIn(200);
								setTimeout(function(){ $msg.fadeOut(400); }, 1500);
							},
							complete: function(){ $btn.prop('disabled',false).css('opacity','1'); }
						});
					});
				});
				</script>
				<?php
			}

			/** Sub-tab switching + gateway card styling (footer). */
			public function payment_tabs_script() {
				if ( ! $this->is_settings_screen() ) {
					return;
				}
				$wc_active = $this->has_woo() ? 'true' : 'false';
				?>
				<style>
				:root{--wbtm-pay-accent:#F12971;}
				/* Sub-tab bar */
				.payment-sub-tabs-wrapper{margin:6px 0 22px;background:#fff;padding:6px;border-radius:12px;border:1px solid #e7e8ec;box-shadow:0 1px 2px rgba(16,24,40,0.04);display:inline-block;}
				.payment-sub-tabs.nav-tab-wrapper{border-bottom:none !important;padding:0 !important;margin:0 !important;display:flex;gap:6px;}
				.payment-sub-tabs .nav-tab{background:transparent;border:1px solid transparent;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:600;color:#50575e !important;text-decoration:none;margin:0;transition:all 0.18s ease;}
				.payment-sub-tabs .nav-tab:hover{background:#fbeaf1;color:var(--wbtm-pay-accent) !important;}
				.payment-sub-tabs .nav-tab-active,.payment-sub-tabs .nav-tab-active:hover{background:var(--wbtm-pay-accent);color:#fff !important;box-shadow:0 4px 12px rgba(241,41,113,0.28);}

				/* Custom Payment intro */
				.wbtm-gw-intro{margin:4px 0 18px;}
				.wbtm-gw-intro h3{margin:0 0 4px;font-size:16px;font-weight:700;color:#1d2327;}
				.wbtm-gw-intro p{margin:0;font-size:13px;color:#6b7280;max-width:680px;line-height:1.6;}

				/* Gateway cards (Custom Payment) */
				.payment-gateways-container th{display:none;}
				.payment-gateways-container td{padding:0 !important;}
				.gateway-card{border:none;border-radius:14px;margin-bottom:14px;box-shadow:0 6px 18px rgba(16,24,40,0.10);width:100%;box-sizing:border-box;color:#fff;overflow:hidden;transition:transform 0.18s ease,box-shadow 0.18s ease;}
				.gateway-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(16,24,40,0.16);}
				.gateway-card .gateway-header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 22px;}
				.gateway-card .gateway-id{display:flex;align-items:center;gap:14px;min-width:0;flex:1 1 0;}
				.gateway-card .gateway-icon{flex:0 0 auto;width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,0.16);display:flex;align-items:center;justify-content:center;}
				.gateway-card .gateway-meta{display:flex;flex-direction:column;min-width:0;}
				.gateway-card .gateway-name{font-size:16px;font-weight:700;color:#fff;line-height:1.3;}
				.gateway-card .gateway-sub{font-size:12px;color:rgba(255,255,255,0.82);line-height:1.4;}
				.gateway-card .gateway-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex:1 1 0;}
				.gateway-card .gateway-status{display:inline-block;min-width:78px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:0.4px;padding:4px 11px;border-radius:20px;background:rgba(255,255,255,0.2);color:#fff;font-weight:700;}
				.gateway-card .gateway-status.active{background:#fff;}
				.gateway-card.paypal-card{background:linear-gradient(135deg,#003087 0%,#0079C1 100%);}
				.gateway-card.paypal-card .gateway-status.active{color:#003087;}
				.gateway-card.stripe-card{background:linear-gradient(135deg,#635bff 0%,#3f36c5 100%);}
				.gateway-card.stripe-card .gateway-status.active{color:#635bff;}
				.gateway-card.offline-card{background:linear-gradient(135deg,#0f766e 0%,#115e59 100%);}
				.gateway-card.offline-card .gateway-status.active{color:#0f766e;}
				.gateway-card .gateway-configure-btn{cursor:pointer;color:#1d2327 !important;background:#fff !important;border:none !important;font-weight:600 !important;font-size:13px !important;border-radius:8px !important;padding:7px 16px !important;line-height:1.4 !important;box-shadow:0 2px 6px rgba(0,0,0,0.18) !important;transition:opacity 0.15s ease;}
				.gateway-card .gateway-configure-btn:hover{opacity:0.9;}
				.wbtm-gw-pro-badge{background:linear-gradient(135deg,#f6d365 0%,#fda085 100%);color:#fff;padding:5px 12px;border-radius:20px;font-weight:bold;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 2px 6px rgba(253,160,133,0.4);}

				/* Booking confirmation page */
				.wbtm-conf-page{margin-top:8px;padding:20px 22px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;background:#fafafb;border:1px solid #ececf0;border-radius:14px;}
				.wbtm-conf-page-label{flex:1 1 260px;}
				.wbtm-conf-page-label label{display:block;font-weight:700;font-size:14px;color:#1d2327;margin:0 0 4px;}
				.wbtm-conf-page-label span{display:block;font-size:12px;color:#6b7280;line-height:1.6;}
				.wbtm-conf-page-field{flex:0 0 auto;}
				.wbtm-conf-page-field select{width:100%;max-width:320px;border:1px solid #d1d5db;border-radius:8px;padding:7px 12px;font-size:13px;background:#fff;}

				/* WooCommerce sub-tab accordions (plain divs — this shell has no <table>) */
				.wbtm-acc-header .wbtm-acc-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none;background:#fff;border:1px solid #e7e8ec;border-radius:10px;padding:13px 16px;margin:14px 18px 4px;transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.2s ease;}
				.wbtm-acc-header .wbtm-acc-bar:hover{border-color:#d4b3c3;box-shadow:0 2px 8px rgba(16,24,40,0.06);}
				.wbtm-acc-header.open .wbtm-acc-bar{background:#fdf2f7;border-color:var(--wbtm-pay-accent);}
				.wbtm-acc-header .wbtm-acc-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#1d2327;margin:0;}
				.wbtm-acc-header.open .wbtm-acc-title{color:var(--wbtm-pay-accent);}
				.wbtm-acc-header .wbtm-acc-arrow{transition:transform 0.2s ease;color:#50575e;line-height:1;}
				.wbtm-acc-header.open .wbtm-acc-arrow{transform:rotate(180deg);color:var(--wbtm-pay-accent);}
				/* The accordion header already shows the title; hide the manager's own duplicate heading but keep its bar (it holds the "Open in WooCommerce" link). */
				.wc-payment-methods-field .wbtm-wc-pm-heading{display:none;}
				.wc-payment-methods-field .wbtm-wc-payment-manager{margin-top:4px;padding:6px 2px;}

				/* --- Align with the modern Global Settings shell ---
				   This shell renders each field as a 2-column
				   `.bm-gs__field-row > (.bm-gs__field-label-cell, .bm-gs__field-control-cell)`
				   grid (no <table>). The Payments tab's fields are all label-less
				   custom callbacks (Booking Mode, sub-tabs, WC manager, gateway
				   cards), so drop the now-empty label column and let the control
				   cell span full width instead of sitting in a half-width grid cell. */
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_booking_mode_selector,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_tabs_html,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_wc_payment_gateways_manager,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_gateways_ui,
				#bm-tab-payments .bm-gs__field-row.woocommerce-field.wbtm-acc-header {
					grid-template-columns:1fr;
				}
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_booking_mode_selector > .bm-gs__field-label-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_tabs_html > .bm-gs__field-label-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_wc_payment_gateways_manager > .bm-gs__field-label-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_gateways_ui > .bm-gs__field-label-cell {
					display:none;
				}
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_booking_mode_selector > .bm-gs__field-control-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_tabs_html > .bm-gs__field-control-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_wc_payment_gateways_manager > .bm-gs__field-control-cell,
				#bm-tab-payments .bm-gs__field-row.wbtm-field-wbtm_payment_gateways_ui > .bm-gs__field-control-cell {
					/* Match the section head's 18px horizontal rhythm on both sides
					   (instead of the base control-cell's asymmetric 10px/14px) now
					   that this cell spans the full row with no label column next
					   to it, and give it a bit more vertical breathing room since it
					   holds chunky elements (cards, tab bars) rather than a single input. */
					padding:16px 18px !important;width:100%;display:block;box-sizing:border-box;
				}

				/* Mobile: gateway card header wraps to two rows (icon/name/sub on
				   its own line, status + action below) instead of squeezing three
				   flex items — icon, status pill, and Configure button — onto one
				   narrow line. Sub-tab pills wrap instead of overflowing. */
				@media (max-width: 480px) {
					.payment-sub-tabs.nav-tab-wrapper{flex-wrap:wrap;}
					.gateway-card .gateway-header{flex-wrap:wrap;row-gap:10px;}
					.gateway-card .gateway-id{flex:1 1 100%;}
					.gateway-card .gateway-status{flex:0 0 auto;}
					.gateway-card .gateway-actions{flex:0 0 auto;justify-content:flex-start;margin-left:auto;}
				}
				</style>
				<script>
				jQuery(function($){
					var wcActive = <?php echo $wc_active; ?>;
					if ($('.payment-sub-tabs').length === 0) { return; }

					// --- WooCommerce sub-tab accordions: Payment Methods (open) + Additional Settings (collapsed) ---
					// This shell renders each field as a `.bm-gs__field-row` div (no <table>/<tr>),
					// so the accordion headers built below are plain divs too.
					var $methodsRows      = $('.bm-gs__field-row.wc-payment-methods-field');
					var $additionalRows   = $('.bm-gs__field-row.wc-additional-field');
					var $methodsHeader    = $();
					var $additionalHeader = $();

					function buildAccordionHeader(extraClass, title, isOpen){
						return $(
							'<div class="bm-gs__field-row woocommerce-field wbtm-acc-header '+extraClass+(isOpen?' open':'')+'">'+
								'<div class="wbtm-acc-bar">'+
									'<span class="wbtm-acc-title">'+title+'</span>'+
									'<span class="wbtm-acc-arrow dashicons dashicons-arrow-down-alt2"></span>'+
								'</div>'+
							'</div>'
						);
					}

					function refreshAccordions(){
						if (!$methodsHeader.length) { return; }
						if ($methodsHeader.hasClass('open')) { $methodsRows.show(); } else { $methodsRows.hide(); }
						if ($additionalHeader.hasClass('open')) { $additionalRows.show(); } else { $additionalRows.hide(); }
					}

					if ($methodsRows.length || $additionalRows.length) {
						// Anchor the accordion headers on the sub-tab row (the field row that
						// holds the .payment-sub-tabs-wrapper). Captured before it's detached below.
						var $toggleRow = $('.payment-sub-tabs-wrapper').closest('.bm-gs__field-row');
						$methodsHeader    = buildAccordionHeader('wbtm-acc-methods', <?php echo wp_json_encode( __( 'WooCommerce Payment Methods', 'bus-ticket-booking-with-seat-reservation' ) ); ?>, true);
						$additionalHeader = buildAccordionHeader('wbtm-acc-additional', <?php echo wp_json_encode( __( 'Additional Settings', 'bus-ticket-booking-with-seat-reservation' ) ); ?>, false);

						// Re-order: toggle -> [Methods header + rows] -> [Additional header + rows].
						$methodsRows.detach();
						$additionalRows.detach();
						$toggleRow.after($methodsHeader);
						$methodsHeader.after($methodsRows);
						$methodsRows.last().after($additionalHeader);
						$additionalHeader.after($additionalRows);

						// Exclusive toggle: opening one closes the other.
						$methodsHeader.find('.wbtm-acc-bar').on('click', function(){
							var willOpen = !$methodsHeader.hasClass('open');
							$methodsHeader.toggleClass('open', willOpen);
							if (willOpen) { $additionalHeader.removeClass('open'); }
							refreshAccordions();
						});
						$additionalHeader.find('.wbtm-acc-bar').on('click', function(){
							var willOpen = !$additionalHeader.hasClass('open');
							$additionalHeader.toggleClass('open', willOpen);
							if (willOpen) { $methodsHeader.removeClass('open'); }
							refreshAccordions();
						});
					}

					function updateTabs(){
						var activeTabId = $('.payment-sub-tabs .nav-tab-active').attr('href').replace('#','');
						$('.bm-gs__field-row.woocommerce-field, div.woocommerce-field, .bm-gs__field-row.no-woocommerce-field').hide();
						if (activeTabId === 'woocommerce-field') {
							$('div.woocommerce-field').show();
							if (wcActive) { $('.bm-gs__field-row.woocommerce-field').stop(true,true).show(); refreshAccordions(); }
						} else {
							$('.bm-gs__field-row.' + activeTabId).show();
						}
					}
					$('.payment-sub-tabs .nav-tab').on('click', function(e){
						e.preventDefault();
						$('.payment-sub-tabs .nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						updateTabs();
					});

					updateTabs();
				});
				</script>
				<?php
			}

			/** AJAX: save a single gateway's settings (real-time from its modal). */
			public function ajax_save_gateway_settings() {
				check_ajax_referer( 'wbtm_save_gateway', 'nonce' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$gateway  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
				$fields   = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
				$existing = get_option( self::OPTION, array() );
				if ( ! is_array( $existing ) ) {
					$existing = array();
				}

				$allowed = array(
					'paypal'  => array( 'wbtm_paypal_enable', 'wbtm_paypal_sandbox', 'wbtm_paypal_client_id', 'wbtm_paypal_secret' ),
					'stripe'  => array( 'wbtm_stripe_enable', 'wbtm_stripe_sandbox', 'wbtm_stripe_test_pub', 'wbtm_stripe_test_sec', 'wbtm_stripe_live_pub', 'wbtm_stripe_live_sec' ),
					'offline' => array( 'wbtm_offline_enable', 'wbtm_offline_label' ),
				);

				if ( ! isset( $allowed[ $gateway ] ) ) {
					wp_send_json_error( __( 'Invalid gateway.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				// All three custom-payment gateways (PayPal, Stripe, Offline) are Pro-only in
				// this plugin; never persist their settings from the free build.
				if ( ! $this->is_pro() ) {
					wp_send_json_error( __( 'This gateway is available in the Pro version.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$toggles = array( 'wbtm_paypal_enable', 'wbtm_paypal_sandbox', 'wbtm_stripe_enable', 'wbtm_stripe_sandbox', 'wbtm_offline_enable' );
				foreach ( $allowed[ $gateway ] as $key ) {
					$val = isset( $fields[ $key ] ) ? $fields[ $key ] : 'off';
					if ( in_array( $key, $toggles, true ) ) {
						$existing[ $key ] = ( 'on' === $val ) ? 'on' : 'off';
					} else {
						$existing[ $key ] = sanitize_text_field( $val );
					}
				}

				update_option( self::OPTION, $existing );
				wp_send_json_success( __( 'Settings saved successfully!', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			/** AJAX: persist the Booking Mode immediately when the card selection changes. */
			public function ajax_save_booking_mode() {
				check_ajax_referer( 'wbtm_save_booking_mode', 'nonce' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
				if ( ! in_array( $mode, array( 'woocommerce', 'standalone' ), true ) ) {
					wp_send_json_error( __( 'Invalid booking mode.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				// The choice is only meaningful when both systems are available; otherwise the
				// mode is auto-resolved and shouldn't be overridden.
				if ( class_exists( 'WBTM_Functions' ) && 'both' !== WBTM_Functions::mode_availability() ) {
					wp_send_json_error( __( 'Booking mode can only be changed when both WooCommerce and the Pro custom gateways are available.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				WBTM_Functions::set_booking_mode( $mode );

				$checker     = class_exists( 'WBTM_Payment_Status_Checker' ) ? new WBTM_Payment_Status_Checker() : null;
				$has_gateway = $checker ? $checker->has_gateway_for_active_mode() : true;

				wp_send_json_success( array(
					'mode'        => $mode,
					'message'     => __( 'Booking mode saved.', 'bus-ticket-booking-with-seat-reservation' ),
					'has_gateway' => $has_gateway,
				) );
			}

			/** AJAX: install &/or activate WooCommerce. */
			public function ajax_install_activate_wc() {
				check_ajax_referer( 'wbtm_install_wc', 'nonce' );
				if ( ! current_user_can( 'install_plugins' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';

				$plugin_file = 'woocommerce/woocommerce.php';

				if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
					$api = plugins_api( 'plugin_information', array(
						'slug'   => 'woocommerce',
						'fields' => array( 'sections' => false ),
					) );
					if ( is_wp_error( $api ) ) {
						wp_send_json_error( $api->get_error_message() );
					}
					$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$result   = $upgrader->install( $api->download_link );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( $result->get_error_message() );
					} elseif ( ! $result ) {
						wp_send_json_error( __( 'Installation failed. Please try manually.', 'bus-ticket-booking-with-seat-reservation' ) );
					}
				}

				// Activate via the options table to avoid loading woocommerce.php into this
				// process (which would clash with the plugin's own wc_price()/WC() fallback shims).
				$active = get_option( 'active_plugins', array() );
				if ( ! in_array( $plugin_file, $active, true ) ) {
					$active[] = $plugin_file;
					sort( $active );
					update_option( 'active_plugins', $active );
				}
				do_action( 'activate_' . $plugin_file );
				do_action( 'activated_plugin', $plugin_file, false );

				wp_send_json_success( __( 'WooCommerce activated successfully!', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			/**
			 * Keep gateway credentials when the Settings API saves the rest of the form.
			 * Only restores a key when it is ABSENT from the incoming value, so a gateway
			 * modal's own AJAX save (which carries new values) is never clobbered.
			 */
			public function preserve_gateway_keys( $new_value, $old_value ) {
				$protected = array(
					'wbtm_paypal_enable', 'wbtm_paypal_sandbox', 'wbtm_paypal_client_id', 'wbtm_paypal_secret',
					'wbtm_stripe_enable', 'wbtm_stripe_sandbox', 'wbtm_stripe_test_pub', 'wbtm_stripe_test_sec',
					'wbtm_stripe_live_pub', 'wbtm_stripe_live_sec',
					'wbtm_offline_enable', 'wbtm_offline_label',
				);
				if ( ! is_array( $new_value ) ) {
					return $new_value;
				}
				if ( is_array( $old_value ) ) {
					foreach ( $protected as $key ) {
						if ( ! isset( $new_value[ $key ] ) && isset( $old_value[ $key ] ) ) {
							$new_value[ $key ] = $old_value[ $key ];
						}
					}
				}

				// The Booking Mode card only renders when both systems are available; on any
				// other save keep the previously stored choice rather than dropping it.
				if ( ! isset( $new_value['wbtm_booking_mode'] ) && is_array( $old_value ) && isset( $old_value['wbtm_booking_mode'] ) ) {
					$new_value['wbtm_booking_mode'] = $old_value['wbtm_booking_mode'];
				}
				// Keep the legacy "Enable WooCommerce Payment" mirror in lock-step with the mode
				// so any older code still reading that flag agrees with booking_mode().
				if ( isset( $new_value['wbtm_booking_mode'] ) && in_array( $new_value['wbtm_booking_mode'], array( 'woocommerce', 'standalone' ), true ) ) {
					$new_value['wbtm_enable_wc_payment'] = ( 'woocommerce' === $new_value['wbtm_booking_mode'] ) ? 'on' : 'off';
				}

				return $new_value;
			}
		}

		new WBTM_Payment_Settings();
	endif;
