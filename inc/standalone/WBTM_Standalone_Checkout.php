<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * [wbtm-standalone-checkout] — the "pay for your booking" page only.
 * Payment RESULTS (success/failed/cancelled) live on a separate page via
 * WBTM_Standalone_Confirmation, so this shortcode has exactly one job.
 */
if ( ! class_exists( 'WBTM_Standalone_Checkout' ) ) {
	class WBTM_Standalone_Checkout {

		public function __construct() {
			add_shortcode( 'wbtm-standalone-checkout', array( $this, 'render' ) );
			add_filter( 'wbtm_get_standalone_checkout_html', array( $this, 'filter_checkout_html' ), 10, 2 );
			// Ensure a page hosting this shortcode exists (the landing/return target of the
			// Offline flow). One-time + idempotent; runs on new and existing free sites.
			add_action( 'init', array( $this, 'maybe_provision_checkout_page' ), 20 );
			// Not hooked to the free plugin's wbtm_add_frontend_script action: that's
			// gated by should_load_frontend_assets(), which only recognizes the free
			// plugin's own shortcodes — it doesn't know about this one, so assets would
			// silently never load on this page. Self-contained instead: check directly.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_on_checkout_page' ) );
			add_action( 'wbtm_add_frontend_script', array( $this, 'enqueue_on_bus_pages' ) );
			add_action( 'wp_footer', array( $this, 'render_frontend_shell' ) );
		}

		private function is_standalone_mode() {
			return class_exists( 'WBTM_Functions' ) && WBTM_Functions::booking_mode() === 'standalone';
		}

		public function filter_checkout_html( $html, $booking_id ) {
			return $this->render_checkout( absint( $booking_id ) );
		}

		/**
		 * Make sure the wbtm_checkout_page_id option points at a published page that
		 * hosts [wbtm-standalone-checkout] — the return/result target of the Offline
		 * flow. Idempotent via a one-time flag so deleting the page doesn't trigger a
		 * recreate loop (an admin can point the option elsewhere by hand).
		 */
		public function maybe_provision_checkout_page() {
			$page_id = (int) get_option( 'wbtm_checkout_page_id' );
			if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
				return;
			}
			if ( get_option( 'wbtm_checkout_page_created_v1' ) ) {
				return;
			}
			$existing = class_exists( 'WBTM_Global_Function' ) ? WBTM_Global_Function::get_page_by_slug( 'bus-booking-checkout' ) : null;
			if ( $existing ) {
				$existing_id = is_object( $existing ) ? (int) $existing->ID : (int) $existing;
				update_option( 'wbtm_checkout_page_id', $existing_id );
				update_option( 'wbtm_checkout_page_created_v1', 1 );
				return;
			}
			$new_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_name'    => 'bus-booking-checkout',
					'post_title'   => 'Bus Booking Checkout',
					'post_content' => '[wbtm-standalone-checkout]',
					'post_status'  => 'publish',
				)
			);
			if ( $new_id && ! is_wp_error( $new_id ) ) {
				update_option( 'wbtm_checkout_page_id', (int) $new_id );
			}
			update_option( 'wbtm_checkout_page_created_v1', 1 );
		}

		public function enqueue_on_checkout_page() {
			if ( ! is_singular() || ! has_shortcode( get_post()->post_content ?? '', 'wbtm-standalone-checkout' ) ) {
				return;
			}
			$this->enqueue_assets();
		}

		public function enqueue_on_bus_pages() {
			if ( ! $this->is_standalone_mode() ) {
				return;
			}
			$this->enqueue_assets();
		}

		private function enqueue_assets() {
			wp_enqueue_style( 'wbtm_standalone_theme', WBTM_PLUGIN_URL . '/assets/frontend/wbtm-standalone-theme.css', array(), WBTM_VERSION );
			wp_enqueue_script( 'wbtm_standalone_checkout', WBTM_PLUGIN_URL . '/assets/frontend/wbtm-standalone-checkout.js', array( 'jquery' ), WBTM_VERSION, true );
			wp_localize_script(
				'wbtm_standalone_checkout',
				'wbtm_standalone_checkout_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wbtm_standalone_checkout' ),
					'i18n'     => array(
						'processing'           => __( 'Processing…', 'bus-ticket-booking-with-seat-reservation' ),
						'select_gateway'       => __( 'Choose a payment method to continue.', 'bus-ticket-booking-with-seat-reservation' ),
						'error_generic'        => __( 'Something went wrong. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
						'pay_now'              => __( 'Confirm & Pay', 'bus-ticket-booking-with-seat-reservation' ),
						'loading_checkout'     => __( 'Preparing checkout…', 'bus-ticket-booking-with-seat-reservation' ),
						'loading_confirmation' => __( 'Loading your confirmation…', 'bus-ticket-booking-with-seat-reservation' ),
						'redirecting_payment'  => __( 'Redirecting to payment…', 'bus-ticket-booking-with-seat-reservation' ),
						'apply_coupon'         => __( 'Apply', 'bus-ticket-booking-with-seat-reservation' ),
						'applying_coupon'      => __( 'Applying…', 'bus-ticket-booking-with-seat-reservation' ),
						'enter_coupon'         => __( 'Please enter a coupon code.', 'bus-ticket-booking-with-seat-reservation' ),
					),
				)
			);
		}

		public function render_frontend_shell() {
			if ( ! $this->is_standalone_mode() ) {
				return;
			}

			$on_bus_page      = did_action( 'wbtm_add_frontend_script' );
			$on_checkout_page = is_singular() && has_shortcode( get_post()->post_content ?? '', 'wbtm-standalone-checkout' );

			if ( ! $on_bus_page && ! $on_checkout_page ) {
				return;
			}

			// Modal is needed both on the booking page (inline checkout) and on
			// the checkout page (when gateways return and we show confirmation
			// in-place).
			if ( $on_bus_page || $on_checkout_page ) {
				$this->render_checkout_modal_shell();
			}

			$this->render_page_loader_shell();
		}

		private function render_checkout_modal_shell() {
			?>
			<div id="wbtm-checkout-modal" class="wbtm-checkout-modal" role="dialog" aria-modal="true" aria-labelledby="wbtm-checkout-modal-title" aria-hidden="true">
				<div class="wbtm-checkout-modal__backdrop" tabindex="-1"></div>
				<div class="wbtm-checkout-modal__dialog">
					<button type="button" class="wbtm-checkout-modal__close" aria-label="<?php esc_attr_e( 'Close', 'bus-ticket-booking-with-seat-reservation' ); ?>">&times;</button>
					<div class="wbtm-checkout-modal__loader" aria-hidden="true">
						<div class="wbtm-checkout-modal__spinner" aria-hidden="true"></div>
						<p class="wbtm-checkout-modal__loader-text"><?php esc_html_e( 'Processing…', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</div>
					<div class="wbtm-checkout-modal__body"></div>
				</div>
			</div>
			<?php
		}

		private function render_page_loader_shell() {
			?>
			<div id="wbtm-page-loader" class="wbtm-page-loader" aria-hidden="true" role="status" aria-live="polite">
				<div class="wbtm-page-loader__inner">
					<div class="wbtm-page-loader__spinner" aria-hidden="true"></div>
					<p class="wbtm-page-loader__text"><?php esc_html_e( 'Loading your confirmation…', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				</div>
			</div>
			<?php
		}

		public function render() {
			$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
			if ( ! $booking_id || get_post_type( $booking_id ) !== 'wbtm_bus_booking' ) {
				return $this->render_notice( __( 'No booking found. Please start a new booking.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			$result = isset( $_GET['wbtm_booking_result'] ) ? sanitize_key( wp_unslash( $_GET['wbtm_booking_result'] ) ) : '';
			if ( in_array( $result, array( 'success', 'failed', 'cancelled' ), true ) && class_exists( 'WBTM_Standalone_Confirmation' ) ) {
				$confirmation_html = WBTM_Standalone_Confirmation::get_confirmation_card_html( $booking_id, $result );

				ob_start();
				?>
				<div id="wbtm-confirmation-modal-content" style="display:none;"><?php echo $confirmation_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<script>
					(function () {
						var el = document.getElementById('wbtm-confirmation-modal-content');
						if (!el) return;
						if (typeof jQuery === 'undefined') return;
						jQuery(function ($) {
							$(document).trigger('wbtm_standalone_checkout_open', [el.innerHTML]);
						});
					})();
				</script>
				<?php
				return ob_get_clean();
			}

			return $this->render_checkout( $booking_id );
		}

		private function render_notice( $text ) {
			ob_start();
			?>
			<div class="wbtm-ticket-page alignwide">
				<div class="wbtm-confirm-shell">
					<div class="wbtm-confirm-card">
						<p class="wbtm-confirm-text"><?php echo esc_html( $text ); ?></p>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private function gateway_icon( $id ) {
			$icons = array(
				'paypal'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h11a4 4 0 0 1 4 4c0 3-2.5 5-5.5 5H10l-1 5H5L7 5Z"/><path d="M10 14 9 19"/></svg>',
				'stripe'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18"/></svg>',
				'offline' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>',
			);
			return $icons[ $id ] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/></svg>';
		}

		private function render_checkout( $booking_id ) {
			$summary = WBTM_Standalone_Payment::get_booking_summary( $booking_id );
			if ( ! $summary ) {
				return $this->render_notice( __( 'No booking found. Please start a new booking.', 'bus-ticket-booking-with-seat-reservation' ) );
			}

			$bus_name = $summary['bus_name'];
			$total    = $summary['total'];
			$bp       = $summary['boarding_point'];
			$dp       = $summary['dropping_point'];
			$display_date  = $summary['journey_date'];
			$is_round_trip = ! empty( $summary['is_round_trip'] );
			$legs          = ! empty( $summary['legs'] ) ? $summary['legs'] : array();

			// Coupon applied earlier (e.g. a re-render after gateway return): reflect it
			// in the summary rows and the "Total due" figure.
			$coupon  = class_exists( 'WBTM_Standalone_Coupon' ) ? WBTM_Standalone_Coupon::get_group_coupon( $booking_id ) : null;
			$payable = $coupon ? WBTM_Standalone_Coupon::payable_total( $booking_id, $total ) : (float) $total;

			$current_user  = is_user_logged_in() ? wp_get_current_user() : null;
			$prefill_name  = $current_user ? $current_user->display_name : '';
			$prefill_email = $current_user ? $current_user->user_email : '';

			$gateways = class_exists( 'WBTM_Payment_Gateway_Manager' ) ? WBTM_Payment_Gateway_Manager::instance()->get_available_gateways() : array();

			ob_start();
			?>
			<div class="wbtm-ticket-page alignwide">
				<div class="wbtm-ticket-shell">
					<p class="wbtm-ticket-eyebrow"><?php esc_html_e( 'Almost there', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					<h1 class="wbtm-ticket-heading"><?php esc_html_e( 'Complete your booking', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>

					<div class="wbtm-checkout-grid wbtm-standalone-checkout" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
						<div class="wbtm-ticket-stub">
							<div class="wbtm-ticket-stub__header">
								<p class="wbtm-ticket-stub__label">
									<?php esc_html_e( 'Your Ticket', 'bus-ticket-booking-with-seat-reservation' ); ?>
									<?php if ( $is_round_trip ) : ?>
										<span class="wbtm-ticket-trip-badge"><?php esc_html_e( 'Round trip', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									<?php endif; ?>
								</p>
								<div class="wbtm-ticket-route">
									<span><?php echo esc_html( $bp ?: $bus_name ); ?></span>
									<?php if ( $dp ) : ?>
										<span class="wbtm-ticket-route__arrow">&rarr;</span>
										<span><?php echo esc_html( $dp ); ?></span>
									<?php endif; ?>
									<?php if ( $is_round_trip && ! empty( $legs[1]['dropping_point'] ) ) : ?>
										<span class="wbtm-ticket-route__arrow">&rarr;</span>
										<span><?php echo esc_html( $legs[1]['dropping_point'] ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( ! $is_round_trip && $display_date ) : ?>
									<div class="wbtm-ticket-route__time"><?php echo esc_html( $display_date ); ?></div>
								<?php endif; ?>
							</div>
							<div class="wbtm-ticket-perf"></div>
							<div class="wbtm-ticket-stub__body">
								<div class="wbtm-ticket-row">
									<span class="wbtm-ticket-row__label"><?php esc_html_e( 'Reference', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									<span class="wbtm-ticket-row__value">#<?php echo absint( $booking_id ); ?></span>
								</div>

								<?php if ( $is_round_trip && ! empty( $legs ) ) : ?>
									<?php // Round trip: itemize each journey leg (outbound + return) on its own. ?>
									<?php foreach ( $legs as $leg ) : ?>
										<div class="wbtm-ticket-leg">
											<div class="wbtm-ticket-leg__head">
												<span class="wbtm-ticket-leg__badge wbtm-ticket-leg__badge--<?php echo esc_attr( $leg['journey_type'] ); ?>"><?php echo esc_html( $leg['label'] ); ?></span>
												<span class="wbtm-ticket-leg__route">
													<?php echo esc_html( $leg['boarding_point'] ); ?>
													<?php if ( ! empty( $leg['dropping_point'] ) ) : ?>&rarr; <?php echo esc_html( $leg['dropping_point'] ); ?><?php endif; ?>
												</span>
											</div>
											<?php if ( ! empty( $leg['journey_date'] ) || ! empty( $leg['bus_name'] ) ) : ?>
												<div class="wbtm-ticket-leg__meta">
													<?php echo esc_html( $leg['journey_date'] ); ?>
													<?php if ( ! empty( $leg['journey_date'] ) && ! empty( $leg['bus_name'] ) ) : ?> &middot; <?php endif; ?>
													<?php echo esc_html( $leg['bus_name'] ); ?>
												</div>
											<?php endif; ?>
											<?php foreach ( $leg['tickets'] as $ticket ) : ?>
												<div class="wbtm-ticket-row">
													<span class="wbtm-ticket-row__label">
														<?php echo esc_html( $ticket['name'] ); ?>
														<?php if ( $ticket['seat'] ) : ?><span class="wbtm-ticket-row__sub"> · <?php echo esc_html( $ticket['seat'] ); ?></span><?php endif; ?>
													</span>
													<span class="wbtm-ticket-row__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $ticket['price'] ) ); ?></span>
												</div>
											<?php endforeach; ?>
											<?php foreach ( $leg['extra_services'] as $service ) :
												$qty        = max( 1, (int) ( $service['qty'] ?? 1 ) );
												$line_total = (float) ( $service['price'] ?? 0 ) * $qty;
												?>
												<div class="wbtm-ticket-row">
													<span class="wbtm-ticket-row__label">
														<?php echo esc_html( $service['name'] ?? '' ); ?>
														<span class="wbtm-ticket-row__sub"> &times; <?php echo esc_html( $qty ); ?></span>
													</span>
													<span class="wbtm-ticket-row__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $line_total ) ); ?></span>
												</div>
											<?php endforeach; ?>
											<div class="wbtm-ticket-row wbtm-ticket-leg__subtotal">
												<span class="wbtm-ticket-row__label"><?php esc_html_e( 'Subtotal', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
												<span class="wbtm-ticket-row__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $leg['subtotal'] ) ); ?></span>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<div class="wbtm-ticket-row">
										<span class="wbtm-ticket-row__label"><?php echo esc_html( WBTM_Translations::text_bus() ); ?></span>
										<span class="wbtm-ticket-row__value"><?php echo esc_html( $bus_name ); ?></span>
									</div>

									<?php if ( ! empty( $summary['tickets'] ) ) : ?>
										<p class="wbtm-ticket-section-label"><?php esc_html_e( 'Tickets', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
										<?php foreach ( $summary['tickets'] as $ticket ) : ?>
											<div class="wbtm-ticket-row">
												<span class="wbtm-ticket-row__label">
													<?php echo esc_html( $ticket['name'] ); ?>
													<?php if ( $ticket['seat'] ) : ?><span class="wbtm-ticket-row__sub"> · <?php echo esc_html( $ticket['seat'] ); ?></span><?php endif; ?>
												</span>
												<span class="wbtm-ticket-row__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $ticket['price'] ) ); ?></span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>

									<?php if ( ! empty( $summary['extra_services'] ) ) : ?>
										<p class="wbtm-ticket-section-label"><?php esc_html_e( 'Extra Services', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
										<?php foreach ( $summary['extra_services'] as $service ) :
											$qty        = max( 1, (int) ( $service['qty'] ?? 1 ) );
											$line_total = (float) ( $service['price'] ?? 0 ) * $qty;
											?>
											<div class="wbtm-ticket-row">
												<span class="wbtm-ticket-row__label">
													<?php echo esc_html( $service['name'] ?? '' ); ?>
													<span class="wbtm-ticket-row__sub"> &times; <?php echo esc_html( $qty ); ?></span>
												</span>
												<span class="wbtm-ticket-row__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $line_total ) ); ?></span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								<?php endif; ?>

								<div class="wbtm-ticket-row wbtm-ticket-coupon"<?php echo $coupon ? '' : ' style="display:none;"'; ?>>
									<span class="wbtm-ticket-row__label">
										<?php
										printf(
											/* translators: %s: coupon code */
											esc_html__( 'Coupon (%s)', 'bus-ticket-booking-with-seat-reservation' ),
											'<span class="wbtm-coupon-code">' . esc_html( $coupon ? $coupon['code'] : '' ) . '</span>'
										);
										?>
									</span>
									<span class="wbtm-ticket-row__value wbtm-coupon-discount"><?php echo $coupon ? '&minus;' . wp_kses_post( WBTM_Global_Function::format_price( $coupon['discount'] ) ) : ''; ?></span>
								</div>
								<div class="wbtm-ticket-total">
									<span class="wbtm-ticket-total__label"><?php esc_html_e( 'Total due', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									<span class="wbtm-ticket-total__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $payable ) ); ?></span>
								</div>
							</div>
						</div>

						<div class="wbtm-pay-panel">
							<?php if ( empty( $gateways ) ) : ?>
								<p class="wbtm-no-gateway"><?php esc_html_e( 'No payment method is available right now. Please contact us to complete this booking.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							<?php else : ?>
								<p class="wbtm-pay-panel__title"><?php esc_html_e( 'Traveler details', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
								<div class="wbtm-field-row">
									<label class="wbtm-field">
										<span><?php esc_html_e( 'Full name', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
										<input type="text" name="wbtm_billing_name" value="<?php echo esc_attr( $prefill_name ); ?>" required />
									</label>
									<label class="wbtm-field">
										<span><?php esc_html_e( 'Email', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
										<input type="email" name="wbtm_billing_email" value="<?php echo esc_attr( $prefill_email ); ?>" required />
									</label>
								</div>
								<label class="wbtm-field">
									<span><?php esc_html_e( 'Phone (optional)', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									<input type="text" name="wbtm_billing_phone" />
								</label>

								<?php // Coupon support at standalone checkout is a Pro feature (WBTM_Standalone_Coupon); hide the box in free. ?>
								<?php if ( class_exists( 'WBTM_Standalone_Coupon' ) ) : ?>
									<p class="wbtm-pay-panel__title"><?php esc_html_e( 'Coupon', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
									<div class="wbtm-coupon-box">
										<div class="wbtm-coupon-form"<?php echo $coupon ? ' style="display:none;"' : ''; ?>>
											<input type="text" name="wbtm_coupon_code" placeholder="<?php esc_attr_e( 'Coupon code', 'bus-ticket-booking-with-seat-reservation' ); ?>" />
											<button type="button" class="wbtm-coupon-apply-btn"><?php esc_html_e( 'Apply', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
										</div>
										<div class="wbtm-coupon-applied"<?php echo $coupon ? '' : ' style="display:none;"'; ?>>
											<span class="wbtm-coupon-applied__code"><?php echo esc_html( $coupon ? $coupon['code'] : '' ); ?></span>
											<button type="button" class="wbtm-coupon-remove-btn"><?php esc_html_e( 'Remove', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
										</div>
										<p class="wbtm-coupon-message" style="display:none;"></p>
									</div>
								<?php endif; ?>

								<p class="wbtm-pay-panel__title"><?php esc_html_e( 'Pay with', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
								<div class="wbtm-gateway-list">
									<?php foreach ( $gateways as $gateway ) : ?>
										<label class="wbtm-gateway-option">
											<input type="radio" name="wbtm_checkout_gateway" value="<?php echo esc_attr( $gateway->get_id() ); ?>" />
											<span class="wbtm-gateway-icon"><?php echo $this->gateway_icon( $gateway->get_id() ); // phpcs:ignore -- static, developer-authored SVG ?></span>
											<span class="wbtm-gateway-label"><?php echo esc_html( $gateway->get_title() ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>

								<button type="button" class="wbtm-pay-cta wbtm-checkout-pay-btn">
									<span class="wbtm-pay-cta-label"><?php esc_html_e( 'Confirm & Pay', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									<span class="wbtm-pay-cta-arrow" aria-hidden="true">&rarr;</span>
								</button>
								<p class="wbtm-pay-message" style="display:none;"></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
	new WBTM_Standalone_Checkout();
}
