<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * Customer + admin notification email for the Standalone/Custom Payment
 * booking flow. Deliberately reuses the SAME "Email Settings" tab fields as
 * the WooCommerce/PDF-ticket flow (WBTM_Pro_Mail) — pdf_send_status,
 * pdf_email_status, pdf_email_subject, pdf_email_content,
 * pdf_admin_notification_email, pdf_email_form_name, pdf_email — rather than
 * a separate settings group, so admins configure notifications once for
 * both booking flows.
 *
 * A pure static utility — invoked directly by WBTM_Standalone_Payment at its
 * status-change points (create_booking(), mark_group_status()) rather than
 * hooking anything itself, so unlike every other file here it does not
 * self-instantiate at the bottom.
 */
if ( ! class_exists( 'WBTM_Standalone_Mail' ) ) {
	class WBTM_Standalone_Mail {

		const SECTION      = 'wbtm_email_settings';
		const BRAND_COLOR  = '#F12971';
		const BRAND_COLOR2 = '#c81158';

		private static function setting( $key, $default = '' ) {
			return WBTM_Global_Function::get_settings( self::SECTION, $key, $default );
		}

		/**
		 * Entry point — called whenever a Standalone booking's status changes.
		 * Gated by the same pdf_send_status/pdf_email_status settings the
		 * WooCommerce/PDF flow uses. Idempotent per (booking, status): a gateway
		 * return that hits handle_payment_return() twice won't resend.
		 */
		public static function maybe_send_status_emails( $booking_id, $status ) {
			if ( self::setting( 'pdf_send_status', 'yes' ) !== 'yes' ) {
				return;
			}

			$allowed = array_filter( (array) self::setting( 'pdf_email_status', array( 'processing', 'completed' ) ) );
			if ( ! empty( $allowed ) && ! in_array( $status, $allowed, true ) ) {
				return;
			}

			$sent_key = '_wbtm_standalone_mail_sent_' . sanitize_key( $status );
			if ( get_post_meta( $booking_id, $sent_key, true ) ) {
				return;
			}

			$summary = class_exists( 'WBTM_Standalone_Payment' ) ? WBTM_Standalone_Payment::get_booking_summary( $booking_id ) : null;
			if ( ! $summary || ! is_email( $summary['customer_email'] ) ) {
				return;
			}

			if ( self::send_mail( $summary, $status ) ) {
				update_post_meta( $booking_id, $sent_key, 1 );
			}
		}

		private static function status_label( $status ) {
			$labels = array(
				'pending'    => __( 'Pending Payment', 'bus-ticket-booking-with-seat-reservation' ),
				'processing' => __( 'Payment Received', 'bus-ticket-booking-with-seat-reservation' ),
				'on-hold'    => __( 'Awaiting Confirmation', 'bus-ticket-booking-with-seat-reservation' ),
				'completed'  => __( 'Completed', 'bus-ticket-booking-with-seat-reservation' ),
				'cancelled'  => __( 'Cancelled', 'bus-ticket-booking-with-seat-reservation' ),
				'failed'     => __( 'Payment Failed', 'bus-ticket-booking-with-seat-reservation' ),
			);
			return $labels[ $status ] ?? ucfirst( $status );
		}

		/**
		 * One email, sent to the customer and (if configured) the admin
		 * notification address together — matching WBTM_Pro_Mail's own
		 * recipient handling for the WooCommerce/PDF flow exactly.
		 */
		private static function send_mail( $summary, $status ) {
			$form_name  = self::setting( 'pdf_email_form_name' ) ?: get_bloginfo( 'name' );
			$form_email = self::setting( 'pdf_email' ) ?: get_option( 'admin_email' );
			$headers    = array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $form_name, $form_email ),
			);

			$admin_notify_email = self::setting( 'pdf_admin_notification_email', '' );
			$recipients         = array_filter( array( $summary['customer_email'], $admin_notify_email ), 'is_email' );

			$subject = self::replace_placeholders( self::setting( 'pdf_email_subject', 'PDF Ticket Confirmation' ), $summary, $status );
			$message = self::replace_placeholders( self::setting( 'pdf_email_content', 'Here is PDF Ticket Confirmation Attachment' ), $summary, $status );
			$body    = self::wrap_email( self::status_label( $status ), $message, $summary );

			$attachments = array();
			if ( self::setting( 'pdf_send_status', 'yes' ) === 'yes' ) {
				$pdf_path = self::generate_ticket_pdf( $summary );
				if ( $pdf_path ) {
					$attachments[] = $pdf_path;
				}
			}

			return (bool) wp_mail( implode( ',', $recipients ), $subject, $body, $headers, $attachments );
		}

		/**
		 * Generates the same PDF ticket the WooCommerce/PDF flow uses (mPDF via
		 * WBTM_Pro_Pdf::generate_pdf(), template/WBTM_Pdf.php → template/pdf/default.php)
		 * for a Standalone booking group. Those templates read plain post meta on
		 * whatever ids attendee_query('wbtm_order_id' => ...) finds — the exact same
		 * meta keys WBTM_Standalone_Payment::insert_booking_records() writes onto its
		 * wbtm_bus_booking posts — so no WooCommerce order is needed. Returns the
		 * written file path, or null if mPDF isn't installed / generation failed.
		 */
		private static function generate_ticket_pdf( $summary ) {
			if ( ! class_exists( 'WBTM_Pro_Pdf' ) ) {
				return null;
			}
			$upload_dir = wp_upload_dir();
			$pdf_path   = $upload_dir['basedir'] . '/Ticket-' . $summary['booking_id'] . '.pdf';

			do_action( 'wbtm_generate_pdf', array( 'wbtm_order_id' => $summary['booking_id'] ), $pdf_path, 'mail' );

			// generate_pdf() writes the file synchronously via mPDF, but poll briefly
			// rather than assuming it's already flushed to disk (mirrors WBTM_Pro_Mail's
			// own wait loop for the same reason).
			$wait = 0;
			while ( ! file_exists( $pdf_path ) && $wait < 10 ) {
				usleep( 200000 );
				$wait++;
			}

			return file_exists( $pdf_path ) ? $pdf_path : null;
		}

		/** Same placeholders as WBTM_Pro_Mail::mail_content(), plus {order_id} mapped to the booking reference. */
		private static function replace_placeholders( $text, $summary, $status ) {
			$map = array(
				'{customer_name}' => $summary['customer_name'] ?: __( 'Customer', 'bus-ticket-booking-with-seat-reservation' ),
				'{bus_name}'      => $summary['bus_name'],
				'{journey_date}'  => $summary['journey_date'],
				'{order_id}'      => $summary['booking_id'],
			);
			return strtr( (string) $text, $map );
		}

		/** Itemized ticket + extra-service breakdown, appended below the admin's configured message. */
		private static function summary_table_html( $summary ) {
			ob_start();
			?>
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2430;">
				<tr>
					<td colspan="2" style="padding:0 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9096a2;"><?php esc_html_e( 'Trip', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
				</tr>
				<tr>
					<td style="padding:4px 0;color:#6b7280;"><?php esc_html_e( 'Bus', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
					<td style="padding:4px 0;text-align:right;font-weight:600;"><?php echo esc_html( $summary['bus_name'] ); ?></td>
				</tr>
				<?php if ( $summary['boarding_point'] || $summary['dropping_point'] ) : ?>
				<tr>
					<td style="padding:4px 0;color:#6b7280;"><?php esc_html_e( 'Route', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
					<td style="padding:4px 0;text-align:right;font-weight:600;"><?php echo esc_html( trim( $summary['boarding_point'] . ' → ' . $summary['dropping_point'], ' →' ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $summary['journey_date'] ) : ?>
				<tr>
					<td style="padding:4px 0;color:#6b7280;"><?php esc_html_e( 'Departs', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
					<td style="padding:4px 0;text-align:right;font-weight:600;"><?php echo esc_html( $summary['journey_date'] ); ?></td>
				</tr>
				<?php endif; ?>

				<tr>
					<td colspan="2" style="padding:16px 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9096a2;border-top:1px solid #eef0f3;"><?php esc_html_e( 'Tickets', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
				</tr>
				<?php foreach ( $summary['tickets'] as $ticket ) : ?>
				<tr>
					<td style="padding:4px 0;color:#374151;">
						<?php echo esc_html( $ticket['name'] ); ?><?php if ( $ticket['seat'] ) : ?><span style="color:#9096a2;"> &middot; <?php echo esc_html( $ticket['seat'] ); ?></span><?php endif; ?>
					</td>
					<td style="padding:4px 0;text-align:right;"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $ticket['price'] ) ); ?></td>
				</tr>
				<?php endforeach; ?>

				<?php if ( ! empty( $summary['extra_services'] ) ) : ?>
				<tr>
					<td colspan="2" style="padding:16px 0 10px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9096a2;border-top:1px solid #eef0f3;"><?php esc_html_e( 'Extra Services', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
				</tr>
				<?php
				foreach ( $summary['extra_services'] as $service ) :
					$qty        = max( 1, (int) ( $service['qty'] ?? 1 ) );
					$line_total = (float) ( $service['price'] ?? 0 ) * $qty;
					?>
				<tr>
					<td style="padding:4px 0;color:#374151;">
						<?php echo esc_html( $service['name'] ?? '' ); ?>
						<span style="color:#9096a2;"> &times; <?php echo esc_html( $qty ); ?></span>
					</td>
					<td style="padding:4px 0;text-align:right;"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $line_total ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php endif; ?>

				<tr>
					<td style="padding:14px 0 0;border-top:2px solid #1f2430;font-weight:700;"><?php esc_html_e( 'Total', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
					<td style="padding:14px 0 0;border-top:2px solid #1f2430;text-align:right;font-weight:700;"><?php echo wp_kses_post( WBTM_Global_Function::format_price( $summary['total'] ) ); ?></td>
				</tr>
			</table>
			<?php
			return ob_get_clean();
		}

		private static function customer_cta_url() {
			if ( is_user_logged_in() && function_exists( 'wc_get_account_endpoint_url' ) ) {
				return wc_get_account_endpoint_url( 'bus-booking-dashboard' );
			}
			return home_url( '/' );
		}

		private static function wrap_email( $heading, $message_html, $summary ) {
			$site_name = get_bloginfo( 'name' );
			$cta_url   = self::customer_cta_url();

			ob_start();
			?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f5f7;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
					<tr>
						<td style="background:linear-gradient(135deg,<?php echo esc_attr( self::BRAND_COLOR ); ?>,<?php echo esc_attr( self::BRAND_COLOR2 ); ?>);padding:26px 28px;">
							<div style="font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,0.8);"><?php echo esc_html( $site_name ); ?></div>
							<div style="font-size:22px;font-weight:700;color:#fff;margin-top:6px;"><?php echo esc_html( $heading ); ?></div>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 28px 0;font-size:14px;line-height:1.65;color:#1f2430;">
							<?php echo wp_kses_post( wpautop( $message_html ) ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:0 28px 8px;">
							<?php echo self::summary_table_html( $summary ); // phpcs:ignore -- self-built above, every value already escaped ?>
						</td>
					</tr>
					<tr>
						<td style="padding:8px 28px 30px;">
							<a href="<?php echo esc_url( $cta_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( self::BRAND_COLOR ); ?>;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:8px;"><?php esc_html_e( 'View my booking', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
						</td>
					</tr>
					<tr>
						<td style="padding:18px 28px;background:#f9fafb;border-top:1px solid #eef0f3;font-size:12px;color:#9096a2;">
							<?php
							printf(
								/* translators: %s: booking reference number */
								esc_html__( 'Booking reference #%s — this is an automated message, please do not reply directly to this email.', 'bus-ticket-booking-with-seat-reservation' ),
								esc_html( $summary['booking_id'] )
							);
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
			<?php
			return ob_get_clean();
		}
	}
}
