<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * [wbtm-booking-confirmation] — where WBTM_Standalone_Payment::redirect_to_result()
 * sends the customer after a Custom Payment attempt, success, failure, or
 * cancellation alike. This is the page selected by the "Booking Confirmation
 * Page" setting in Payments settings (auto-created + pre-selected on Pro
 * activation — see wbtm-pro.php::on_activation_page_create()).
 */
if ( ! class_exists( 'WBTM_Standalone_Confirmation' ) ) {
	class WBTM_Standalone_Confirmation {

		public function __construct() {
			add_shortcode( 'wbtm-booking-confirmation', array( $this, 'render' ) );
			// Same reasoning as WBTM_Standalone_Checkout: should_load_frontend_assets()
			// doesn't know this shortcode, so check for it directly instead.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		}

		public function enqueue_frontend_assets() {
			if ( ! is_singular() || ! has_shortcode( get_post()->post_content ?? '', 'wbtm-booking-confirmation' ) ) {
				return;
			}
			wp_enqueue_style( 'wbtm_standalone_theme', WBTM_PLUGIN_URL . '/assets/frontend/wbtm-standalone-theme.css', array(), WBTM_VERSION );
		}

		public function render() {
			$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
			$result     = isset( $_GET['wbtm_booking_result'] ) ? sanitize_key( wp_unslash( $_GET['wbtm_booking_result'] ) ) : '';

			if ( ! $booking_id || get_post_type( $booking_id ) !== 'wbtm_bus_booking' || ! in_array( $result, array( 'success', 'failed', 'cancelled' ), true ) ) {
				return $this->render_empty_state();
			}

			return self::get_confirmation_page_html( $booking_id, $result );
		}

		private function render_empty_state() {
			ob_start();
			?>
			<div class="wbtm-ticket-page alignwide">
				<div class="wbtm-confirm-shell">
					<div class="wbtm-confirm-card">
						<p class="wbtm-confirm-title"><?php esc_html_e( 'No booking to show', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						<p class="wbtm-confirm-text"><?php esc_html_e( 'This page shows your booking status after payment. Start a new search to book a trip.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						<div class="wbtm-confirm-actions">
							<a class="wbtm-confirm-btn is-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
						</div>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		private static function stamp_icon( $result ) {
			$icons = array(
				'success'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4 10-10"/></svg>',
				'failed'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
				'cancelled' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5v5M14.5 9.5v5"/></svg>',
			);
			return $icons[ $result ];
		}

		public static function get_confirmation_card_html( $booking_id, $result ) {
			$copy = array(
				'success'   => array(
					'title' => __( 'Booking confirmed', 'bus-ticket-booking-with-seat-reservation' ),
					'text'  => __( 'Payment received — your seat is booked. We\'ve emailed your confirmation.', 'bus-ticket-booking-with-seat-reservation' ),
				),
				'failed'    => array(
					'title' => __( 'Payment could not be verified', 'bus-ticket-booking-with-seat-reservation' ),
					'text'  => __( 'Nothing was charged. Try again, or choose a different payment method.', 'bus-ticket-booking-with-seat-reservation' ),
				),
				'cancelled' => array(
					'title' => __( 'Payment cancelled', 'bus-ticket-booking-with-seat-reservation' ),
					'text'  => __( 'You cancelled before paying. Your seat is only held for a short time — try again soon to keep it.', 'bus-ticket-booking-with-seat-reservation' ),
				),
			);
			$c = $copy[ $result ];

			$summary  = WBTM_Standalone_Payment::get_booking_summary( $booking_id );
			$bus_name = $summary['bus_name'];
			$bp       = $summary['boarding_point'];
			$dp       = $summary['dropping_point'];
			$total    = $summary['total'];
			$display_date  = $summary['journey_date'];
			$is_round_trip = ! empty( $summary['is_round_trip'] );
			$legs          = ! empty( $summary['legs'] ) ? $summary['legs'] : array();

			// Reflect a coupon applied at checkout in the summary rows and the total.
			$coupon  = class_exists( 'WBTM_Standalone_Coupon' ) ? WBTM_Standalone_Coupon::get_group_coupon( $booking_id ) : null;
			$payable = $coupon ? WBTM_Standalone_Coupon::payable_total( $booking_id, $total ) : (float) $total;

			$home_url = home_url( '/' );
			$account_url = ( is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) )
				? wc_get_account_endpoint_url( 'bus-booking-dashboard' )
				: '';
			$retry_url = 'success' !== $result
				? add_query_arg( 'booking_id', $booking_id, WBTM_Standalone_Payment::get_checkout_page_url() )
				: '';

			// Same "Seat Booked Status" setting that gates ticket download in the
			// My Bookings portal (WBTM_Customer_Portal::is_ticket_ready()) — a
			// paid-but-not-yet-confirmed booking (e.g. still 'on-hold' for an
			// offline gateway) must not offer a ticket that isn't valid yet.
			$download_url = ( 'success' === $result
				&& class_exists( 'WBTM_Customer_Portal' ) && WBTM_Customer_Portal::is_ticket_ready( $summary['status'] )
				&& class_exists( 'WBTM_Pro_Pdf' ) && WBTM_Pro_Pdf::is_mpdf_available() )
				? WBTM_Customer_Portal::mint_download_url( $booking_id, 30 * MINUTE_IN_SECONDS )
				: '';

			ob_start();
			?>
			<div class="wbtm-confirm-card is-<?php echo esc_attr( $result ); ?>">
				<div class="wbtm-confirm-stamp"><?php echo self::stamp_icon( $result ); // phpcs:ignore -- static, developer-authored SVG ?></div>
				<h1 class="wbtm-confirm-title"><?php echo esc_html( $c['title'] ); ?></h1>
				<p class="wbtm-confirm-text"><?php echo esc_html( $c['text'] ); ?></p>
				<div class="wbtm-confirm-ref"><?php echo esc_html__( 'Ref #', 'bus-ticket-booking-with-seat-reservation' ) . absint( $booking_id ); ?></div>

				<div class="wbtm-confirm-summary">
					<?php if ( $is_round_trip && ! empty( $legs ) ) : ?>
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
					<?php if ( $bp || $dp ) : ?>
						<div class="wbtm-ticket-row">
							<span class="wbtm-ticket-row__label"><?php esc_html_e( 'Route', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<span class="wbtm-ticket-row__value"><?php echo esc_html( $bp . ' → ' . $dp ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( $display_date ) : ?>
						<div class="wbtm-ticket-row">
							<span class="wbtm-ticket-row__label"><?php esc_html_e( 'Departs', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<span class="wbtm-ticket-row__value"><?php echo esc_html( $display_date ); ?></span>
						</div>
					<?php endif; ?>
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
					<?php if ( $coupon ) : ?>
						<div class="wbtm-ticket-row wbtm-ticket-coupon">
							<span class="wbtm-ticket-row__label">
								<?php
								printf(
									/* translators: %s: coupon code */
									esc_html__( 'Coupon (%s)', 'bus-ticket-booking-with-seat-reservation' ),
									esc_html( $coupon['code'] )
								);
								?>
							</span>
							<span class="wbtm-ticket-row__value">&minus;<?php echo wp_kses_post( WBTM_Global_Function::format_price( $coupon['discount'] ) ); ?></span>
						</div>
					<?php endif; ?>
					<div class="wbtm-ticket-total">
						<span class="wbtm-ticket-total__label"><?php echo 'success' === $result ? esc_html__( 'Paid', 'bus-ticket-booking-with-seat-reservation' ) : esc_html__( 'Total due', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						<span class="wbtm-ticket-total__value"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $payable ) ); ?></span>
					</div>
				</div>

				<div class="wbtm-confirm-actions">
					<?php if ( $download_url ) : ?>
						<a class="wbtm-confirm-btn is-primary" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download Ticket', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
						<?php if ( $account_url ) : ?>
							<a class="wbtm-confirm-btn is-secondary" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'View my bookings', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
						<?php endif; ?>
					<?php elseif ( $retry_url ) : ?>
						<a class="wbtm-confirm-btn is-primary" href="<?php echo esc_url( $retry_url ); ?>"><?php esc_html_e( 'Try again', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
					<?php elseif ( $account_url ) : ?>
						<a class="wbtm-confirm-btn is-primary" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'View my bookings', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
					<?php endif; ?>
					<a class="wbtm-confirm-btn is-secondary" href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'Back to home', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		public static function get_confirmation_page_html( $booking_id, $result ) {
			ob_start();
			?>
			<div class="wbtm-ticket-page alignwide">
				<div class="wbtm-confirm-shell">
					<?php echo self::get_confirmation_card_html( $booking_id, $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
	new WBTM_Standalone_Confirmation();
}
