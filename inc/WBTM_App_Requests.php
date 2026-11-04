<?php
/** Operator review of customer app cancellation/change requests. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Requests' ) ) {
	class WBTM_App_Requests {
		const PAGE = 'wbtm_app_requests';
		const CAPABILITY = 'manage_options';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'wbtm_app_booking_request', array( $this, 'notify_operator' ), 10, 3 );
			add_action( 'admin_post_wbtm_app_review_request', array( $this, 'handle_review' ) );
		}

		public function menu() {
			add_submenu_page( 'edit.php?post_type=wbtm_bus', __( 'App Requests', 'addon-bus--ticket-booking-with-seat-pro' ), __( 'App Requests', 'addon-bus--ticket-booking-with-seat-pro' ), self::CAPABILITY, self::PAGE, array( $this, 'render_page' ) );
		}

		public static function url( $booking_id = 0 ) {
			$args = array( 'post_type' => 'wbtm_bus', 'page' => self::PAGE );
			if ( $booking_id ) {
				$args['booking_id'] = absint( $booking_id );
			}
			return add_query_arg( $args, admin_url( 'edit.php' ) );
		}

		private static function key( $kind ) {
			return '_wbtm_app_' . $kind . '_request';
		}

		public static function fingerprint( $request ) {
			return hash( 'sha256', wp_json_encode( $request ) );
		}

		public static function nonce_action( $post_id, $kind, $fingerprint ) {
			return 'wbtm_app_review_' . absint( $post_id ) . '_' . $kind . '_' . $fingerprint;
		}

		public function notify_operator( $user_id, $booking_id, $kind ) {
			if ( ! in_array( $kind, array( 'cancel', 'change' ), true ) ) {
				return 0;
			}
			$rows = get_posts( array( 'post_type' => 'wbtm_bus_booking', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'wbtm_order_id', 'value' => absint( $booking_id ) ), array( 'key' => 'wbtm_user_id', 'value' => absint( $user_id ) ) ) ) );
			$request = $rows ? get_post_meta( $rows[0], self::key( $kind ), true ) : false;
			if ( ! is_array( $request ) || 'requested' !== ( $request['status'] ?? '' ) || ! class_exists( 'WBTM_Admin_Notifications' ) ) {
				return 0;
			}
			$fingerprint = self::fingerprint( $request );
			$notice_key = '_wbtm_app_' . $kind . '_notice';
			if ( $fingerprint === get_post_meta( $rows[0], $notice_key, true ) ) {
				return 0;
			}
			$message = 'cancel' === $kind
				? __( 'Customer requested booking cancellation. Operator review is required.', 'addon-bus--ticket-booking-with-seat-pro' )
				: __( 'Customer requested a booking change. Operator review is required.', 'addon-bus--ticket-booking-with-seat-pro' );
			$notice_id = WBTM_Admin_Notifications::create_notice( $message, 'info', self::url( $booking_id ), sprintf( __( 'Booking #%d. Reviewing a request does not change the booking, payment or seats.', 'addon-bus--ticket-booking-with-seat-pro' ), absint( $booking_id ) ) );
			if ( $notice_id ) {
				update_post_meta( $notice_id, '_wbtm_app_request_booking', absint( $booking_id ) );
				update_post_meta( $rows[0], $notice_key, $fingerprint );
			}
			return $notice_id;
		}

		public function query_requests( $page = 1, $booking_id = 0 ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_forbidden', __( 'You cannot review app requests.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			$meta = array( 'relation' => 'AND', array( 'relation' => 'OR', array( 'key' => self::key( 'cancel' ), 'compare' => 'EXISTS' ), array( 'key' => self::key( 'change' ), 'compare' => 'EXISTS' ) ) );
			if ( $booking_id ) {
				$meta[] = array( 'key' => 'wbtm_order_id', 'value' => absint( $booking_id ) );
			}
			return new WP_Query( array( 'post_type' => 'wbtm_bus_booking', 'post_status' => 'publish', 'posts_per_page' => 20, 'paged' => max( 1, absint( $page ) ), 'orderby' => 'ID', 'order' => 'DESC', 'meta_query' => $meta ) );
		}

		/** Compare-and-swap protects a newer customer request from a stale admin form. */
		public function review_request( $post_id, $kind, $decision, $fingerprint, $nonce ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_forbidden', __( 'You cannot review app requests.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			if ( ! in_array( $kind, array( 'cancel', 'change' ), true ) || ! in_array( $decision, array( 'reviewed', 'declined' ), true ) || ! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || 'wbtm_bus_booking' !== get_post_type( $post_id ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_invalid', __( 'Invalid app request.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			}
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::nonce_action( $post_id, $kind, $fingerprint ) ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_nonce', __( 'The form expired. Reload this page and try again.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			$prior = get_post_meta( $post_id, self::key( $kind ), true );
			if ( ! is_array( $prior ) || 'requested' !== ( $prior['status'] ?? '' ) || ! hash_equals( self::fingerprint( $prior ), $fingerprint ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_changed', __( 'This request changed or was already reviewed. Reload the page.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
			}
			$next = array_merge( $prior, array( 'status' => $decision, 'reviewed_at' => gmdate( DATE_ATOM ), 'reviewed_by' => get_current_user_id() ) );
			if ( ! update_post_meta( $post_id, self::key( $kind ), $next, $prior ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_request_changed', __( 'The request changed before it could be saved. Reload the page.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
			}
			return $next;
		}

		public function handle_review() {
			$read = static function ( $key ) { return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; };
			$post_id = absint( $read( 'booking_row' ) );
			$result = $this->review_request( $post_id, $read( 'kind' ), $read( 'decision' ), $read( 'fingerprint' ), $read( '_wbtm_app_request_nonce' ) );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $result->get_error_data()['status'] ) );
			}
			wp_safe_redirect( self::url( get_post_meta( $post_id, 'wbtm_order_id', true ) ) );
			exit;
		}

		public function render_page() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
			$page = isset( $_GET['paged'] ) && is_string( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
			$booking_id = isset( $_GET['booking_id'] ) && is_string( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
			$query = $this->query_requests( $page, $booking_id );
			if ( is_wp_error( $query ) ) {
				wp_die( esc_html( $query->get_error_message() ), '', array( 'response' => 403 ) );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'App Requests', 'addon-bus--ticket-booking-with-seat-pro' ); ?></h1>
				<p><?php esc_html_e( 'Review customer cancellation and change requests. Use the existing booking and payment tools to perform any approved change or refund, then mark the request reviewed. These buttons only update the request status; they do not cancel bookings, refund money or move seats.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p>
				<?php if ( $booking_id ) : ?><p><a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'View all app requests', 'addon-bus--ticket-booking-with-seat-pro' ); ?></a></p><?php endif; ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Booking / customer', 'addon-bus--ticket-booking-with-seat-pro' ); ?></th><th><?php esc_html_e( 'Journey', 'addon-bus--ticket-booking-with-seat-pro' ); ?></th><th><?php esc_html_e( 'Request', 'addon-bus--ticket-booking-with-seat-pro' ); ?></th><th><?php esc_html_e( 'Operator review', 'addon-bus--ticket-booking-with-seat-pro' ); ?></th></tr></thead>
					<tbody>
					<?php if ( ! $query->posts ) : ?><tr><td colspan="4"><?php esc_html_e( 'No app requests found.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $query->posts as $post ) : ?>
						<?php foreach ( array( 'cancel', 'change' ) as $kind ) : ?>
							<?php
							$request = get_post_meta( $post->ID, self::key( $kind ), true );
							if ( ! is_array( $request ) ) { continue; }
							$order_id = absint( get_post_meta( $post->ID, 'wbtm_order_id', true ) );
							$owner = get_user_by( 'id', absint( get_post_meta( $post->ID, 'wbtm_user_id', true ) ) );
							$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
							$status = $request['status'] ?? 'requested';
							$status_labels = array( 'requested' => __( 'Requested', 'addon-bus--ticket-booking-with-seat-pro' ), 'reviewed' => __( 'Reviewed', 'addon-bus--ticket-booking-with-seat-pro' ), 'declined' => __( 'Declined', 'addon-bus--ticket-booking-with-seat-pro' ) );
							?>
							<tr>
								<td><strong><?php echo esc_html( sprintf( __( 'Booking #%d', 'addon-bus--ticket-booking-with-seat-pro' ), $order_id ) ); ?></strong><br><?php echo esc_html( $owner ? $owner->display_name : get_post_meta( $post->ID, 'wbtm_user_name', true ) ); ?><br><?php echo esc_html( $owner ? $owner->user_email : get_post_meta( $post->ID, 'wbtm_user_email', true ) ); ?><?php if ( $order ) : ?><br><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open order', 'addon-bus--ticket-booking-with-seat-pro' ); ?></a><?php endif; ?></td>
								<td><?php echo esc_html( get_the_title( absint( get_post_meta( $post->ID, 'wbtm_bus_id', true ) ) ) ); ?><br><?php echo esc_html( get_post_meta( $post->ID, 'wbtm_boarding_point', true ) . ' → ' . get_post_meta( $post->ID, 'wbtm_dropping_point', true ) ); ?><br><?php echo esc_html( get_post_meta( $post->ID, 'wbtm_boarding_time', true ) ); ?></td>
								<td><strong><?php echo esc_html( 'cancel' === $kind ? __( 'Cancellation request', 'addon-bus--ticket-booking-with-seat-pro' ) : __( 'Change request', 'addon-bus--ticket-booking-with-seat-pro' ) ); ?></strong><p><?php echo nl2br( esc_html( $request['message'] ?? '' ) ); ?></p><?php echo esc_html( $request['requested_at'] ?? '' ); ?></td>
								<td><strong><?php echo esc_html( $status_labels[ $status ] ?? $status ); ?></strong>
								<?php if ( 'requested' === $status ) : ?>
									<?php $fingerprint = self::fingerprint( $request ); ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="wbtm_app_review_request"><input type="hidden" name="booking_row" value="<?php echo esc_attr( $post->ID ); ?>"><input type="hidden" name="kind" value="<?php echo esc_attr( $kind ); ?>"><input type="hidden" name="fingerprint" value="<?php echo esc_attr( $fingerprint ); ?>">
										<?php wp_nonce_field( self::nonce_action( $post->ID, $kind, $fingerprint ), '_wbtm_app_request_nonce' ); ?>
										<p><button class="button" name="decision" value="reviewed"><?php esc_html_e( 'Mark reviewed', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button> <button class="button" name="decision" value="declined"><?php esc_html_e( 'Decline request', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button></p>
									</form>
								<?php elseif ( ! empty( $request['reviewed_at'] ) ) : ?><p><?php echo esc_html( $request['reviewed_at'] ); ?></p><?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php
				$pagination = paginate_links( array( 'base' => str_replace( '999999999', '%#%', add_query_arg( 'paged', 999999999, self::url( $booking_id ) ) ), 'format' => '', 'current' => $page, 'total' => $query->max_num_pages ) );
				if ( is_string( $pagination ) ) { echo wp_kses_post( $pagination ); }
				?>
			</div>
			<?php
		}
	}
	new WBTM_App_Requests();
}
