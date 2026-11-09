<?php
/** Customer profile, device ownership and a verified account-erasure workflow. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Account' ) ) {
	class WBTM_App_Account {
		const DEVICE_PREFIX = 'wbtm_app_device_';
		const DEVICE_META   = '_wbtm_app_device';
		const DELETE_META   = '_wbtm_app_deletion_request';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
			add_action( 'user_request_action_confirmed', array( $this, 'request_confirmed' ) );
			add_shortcode( 'wbtm_app_delete_account', array( $this, 'deletion_form' ) );
			add_action( 'admin_post_wbtm_app_delete_account', array( $this, 'web_deletion_request' ) );
			add_action( 'admin_post_nopriv_wbtm_app_delete_account', array( $this, 'web_deletion_request' ) );
			add_action( 'delete_user', array( __CLASS__, 'remove_all_devices' ) );
		}

		public function register_routes() {
			register_rest_route( WBTM_App_Config::REST_NAMESPACE, '/me', array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'profile' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ),
				array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_profile' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ),
				array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_account' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ),
			) );
			register_rest_route( WBTM_App_Config::REST_NAMESPACE, '/devices', array(
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'register_device' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ),
				array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_device' ), 'permission_callback' => array( 'WBTM_App_Auth', 'require_user' ) ),
			) );
		}

		public function profile() {
			$user = wp_get_current_user();
			$data = WBTM_App_Auth::user_data( $user );
			$data['phone'] = (string) get_user_meta( $user->ID, 'billing_phone', true );
			$request_id    = absint( get_user_meta( $user->ID, self::DELETE_META, true ) );
			$data['deletion_request'] = array( 'status' => $request_id ? 'pending' : 'none' );
			if ( $request_id ) {
				$data['deletion_request']['requested_at'] = get_post_time( DATE_ATOM, true, $request_id );
			}
			return $data;
		}

		public function update_profile( $request ) {
			$rate = WBTM_App_Auth::rate_limit( 'profile', 30, (string) get_current_user_id() );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$name  = sanitize_text_field( WBTM_App_Auth::string_param( $request, 'name', 200 ) );
			$phone = trim( WBTM_App_Auth::string_param( $request, 'phone', 60 ) );
			if ( $request->has_param( 'phone' ) && ( ! is_string( $request->get_param( 'phone' ) ) || strlen( $request->get_param( 'phone' ) ) > 60 || ( '' !== $phone && ! preg_match( '/^[+0-9 ()-]{3,60}$/D', $phone ) ) ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_invalid_phone', __( 'Enter a valid phone number.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			}
			if ( $request->has_param( 'name' ) ) {
				if ( '' === $name ) {
					return WBTM_App_Auth::error( 'wbtm_app_invalid_name', __( 'Enter your name.', 'addon-bus--ticket-booking-with-seat-pro' ) );
				}
				$result = wp_update_user( array( 'ID' => get_current_user_id(), 'display_name' => $name ) );
				if ( is_wp_error( $result ) ) {
					return WBTM_App_Auth::error( 'wbtm_app_profile_failed', __( 'Unable to update the profile.', 'addon-bus--ticket-booking-with-seat-pro' ), 500 );
				}
			}
			if ( $request->has_param( 'phone' ) ) {
				update_user_meta( get_current_user_id(), 'billing_phone', $phone );
			}
			return $this->profile();
		}

		public function delete_account( $request ) {
			$user = wp_get_current_user();
			$rate = WBTM_App_Auth::rate_limit( 'delete_account', 5, (string) $user->ID );
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			if ( ! wp_check_password( WBTM_App_Auth::string_param( $request, 'password', 4096 ), $user->user_pass, $user->ID ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_password_required', __( 'Confirm your current password to request account deletion.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			$request_id = wp_create_user_request( $user->user_email, 'remove_personal_data', array( 'wbtm_app_account' => $user->ID ), 'confirmed' );
			if ( is_wp_error( $request_id ) ) {
				if ( 'duplicate_request' !== $request_id->get_error_code() ) {
					return WBTM_App_Auth::error( 'wbtm_app_deletion_failed', __( 'Unable to submit the deletion request. Please contact support.', 'addon-bus--ticket-booking-with-seat-pro' ), 500 );
				}
				$existing = get_posts( array( 'post_type' => 'user_request', 'post_status' => array( 'request-pending', 'request-confirmed' ), 'name' => 'remove_personal_data', 'title' => $user->user_email, 'numberposts' => 1, 'fields' => 'ids' ) );
				$request_id = $existing ? $existing[0] : 0;
				if ( ! $request_id ) {
					return WBTM_App_Auth::error( 'wbtm_app_deletion_failed', __( 'Please contact support about your existing deletion request.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
				}
				wp_update_post( array( 'ID' => $request_id, 'post_status' => 'request-confirmed', 'post_content' => wp_json_encode( array( 'wbtm_app_account' => $user->ID ) ) ) );
			}
			$this->mark_deletion_pending( $user, $request_id );
			wp_set_current_user( 0 );
			return new WP_REST_Response( array( 'status' => 'pending', 'message' => __( 'Your account deletion request has been received and you have been signed out on all devices. Support will process it. Required booking and financial records may be retained as described in the privacy policy.', 'addon-bus--ticket-booking-with-seat-pro' ) ), 202 );
		}

		private function mark_deletion_pending( $user, $request_id ) {
			update_user_meta( $user->ID, self::DELETE_META, $request_id );
			WBTM_App_Auth::revoke_all( $user->ID );
			self::remove_all_devices( $user->ID );
			if ( class_exists( 'WBTM_Admin_Notifications' ) ) {
				WBTM_Admin_Notifications::create_notice( __( 'Customer app account deletion requested. Review Tools > Erase Personal Data.', 'addon-bus--ticket-booking-with-seat-pro' ), 'info', admin_url( 'erase-personal-data.php' ) );
			}
		}

		public function request_confirmed( $request_id ) {
			$post = get_post( $request_id );
			$data = $post ? json_decode( $post->post_content, true ) : array();
			$user = ! empty( $data['wbtm_app_account'] ) ? get_user_by( 'id', absint( $data['wbtm_app_account'] ) ) : false;
			if ( $user && WBTM_App_Auth::customer_allowed( $user ) && 'remove_personal_data' === $post->post_name && strtolower( $post->post_title ) === strtolower( $user->user_email ) ) {
				$this->mark_deletion_pending( $user, $request_id );
			}
		}

		/** Place this shortcode on the public account-deletion information page. */
		public function deletion_form() {
			ob_start();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<p><?php esc_html_e( 'Request deletion of your customer app account. A confirmation link will be emailed to the account address. Required booking and financial records may be retained as explained in the privacy policy.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p>
				<label><?php esc_html_e( 'Account email', 'addon-bus--ticket-booking-with-seat-pro' ); ?> <input type="email" name="email" required maxlength="254" autocomplete="email"></label>
				<input type="hidden" name="action" value="wbtm_app_delete_account">
				<?php wp_nonce_field( 'wbtm_app_delete_account', '_wbtm_app_delete_nonce' ); ?>
				<button type="submit"><?php esc_html_e( 'Request account deletion', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button>
			</form>
			<?php
			return ob_get_clean();
		}

		public function web_deletion_request() {
			check_admin_referer( 'wbtm_app_delete_account', '_wbtm_app_delete_nonce' );
			$rate = WBTM_App_Auth::rate_limit( 'web_delete_account', 3 );
			// The public privacy path remains usable after the app API is switched off.
			if ( ! is_wp_error( $rate ) ) {
				$email = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
				$user  = get_user_by( 'email', $email );
				if ( $user && WBTM_App_Auth::customer_allowed( $user ) ) {
					$id = wp_create_user_request( $email, 'remove_personal_data', array( 'wbtm_app_account' => $user->ID ) );
					if ( ! is_wp_error( $id ) ) {
						wp_send_user_request( $id );
					}
				}
			}
			wp_die( esc_html__( 'If this account exists, you will receive a confirmation email. Once confirmed, support will process the request. You do not need to reinstall the app.', 'addon-bus--ticket-booking-with-seat-pro' ), esc_html__( 'Account deletion request', 'addon-bus--ticket-booking-with-seat-pro' ), array( 'response' => 200, 'back_link' => true ) );
		}

		public function register_eraser( $erasers ) {
			$erasers['wbtm-app-account'] = array( 'eraser_friendly_name' => __( 'Bus customer app account', 'addon-bus--ticket-booking-with-seat-pro' ), 'callback' => array( $this, 'erase_account' ) );
			return $erasers;
		}

		/** Staff execute this through WordPress Tools > Erase Personal Data. */
		public function erase_account( $email, $page = 1 ) {
			$result = array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
			$user   = get_user_by( 'email', $email );
			$id     = $user ? absint( get_user_meta( $user->ID, self::DELETE_META, true ) ) : 0;
			if ( ! $id || ! in_array( get_post_status( $id ), array( 'request-confirmed', 'request-completed' ), true ) ) {
				return $result;
			}
			if ( ! current_user_can( 'erase_others_personal_data' ) || is_multisite() || array_diff( $user->roles, array( 'customer', 'subscriber' ) ) ) {
				$result['items_retained'] = true;
				$result['messages'][] = __( 'A site administrator must review this account deletion. Multisite and privileged accounts require manual review.', 'addon-bus--ticket-booking-with-seat-pro' );
				return $result;
			}
			$bookings = get_posts( array( 'post_type' => 'wbtm_bus_booking', 'post_status' => 'any', 'posts_per_page' => 100, 'fields' => 'ids', 'meta_key' => 'wbtm_user_id', 'meta_value' => $user->ID, 'orderby' => 'ID', 'order' => 'ASC' ) );
			foreach ( $bookings as $booking ) {
				update_post_meta( $booking, 'wbtm_user_id', 0 );
				update_post_meta( $booking, '_wbtm_app_account_deleted', gmdate( DATE_ATOM ) );
			}
			$result['items_removed']  = true;
			$result['items_retained'] = ! empty( $bookings );
			if ( $bookings ) {
				$result['messages'][] = __( 'The app account link was removed. Passenger and financial records were retained; apply the operator retention policy and WooCommerce erasers separately.', 'addon-bus--ticket-booking-with-seat-pro' );
			}
			if ( count( $bookings ) === 100 ) {
				$result['done'] = false;
				return $result;
			}
			WBTM_App_Auth::revoke_all( $user->ID );
			self::remove_all_devices( $user->ID );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			// Reassign authored records to 0: never cascade-delete bookings or orders.
			if ( ! wp_delete_user( $user->ID, 0 ) ) {
				$result['items_retained'] = true;
				$result['messages'][] = __( 'Unable to delete the customer account. Please review it manually.', 'addon-bus--ticket-booking-with-seat-pro' );
			}
			return $result;
		}

		private static function valid_token( $token ) {
			return is_string( $token ) && preg_match( '/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]{10,200}\]$/D', $token );
		}

		public function register_device( $request ) {
			$settings = WBTM_App_Settings::get();
			$token    = WBTM_App_Auth::string_param( $request, 'token', 250 );
			if ( 'yes' !== $settings['app_push_enabled'] || ! $settings['app_expo_project_id'] || $settings['app_expo_project_id'] !== WBTM_App_Auth::string_param( $request, 'project_id', 36 ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_push_disabled', __( 'Push notifications are not configured for this app project.', 'addon-bus--ticket-booking-with-seat-pro' ), 403 );
			}
			if ( ! self::valid_token( $token ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_invalid_device', __( 'Invalid device push token.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			}
			$user_id = get_current_user_id();
			$key     = hash( 'sha256', $token );
			$selector = WBTM_App_Auth::current_session();
			if ( ! $selector ) {
				return WBTM_App_Auth::error( 'wbtm_app_unauthorized', __( 'Please sign in again.', 'addon-bus--ticket-booking-with-seat-pro' ), 401 );
			}
			// Match logout's session -> device lock order. Re-check the credential under
			// that lock: permission checks can precede a concurrent logout/revocation.
			return WBTM_App_Auth::locked( 'session_' . $selector, function () use ( $request, $selector, $user_id, $key, $token ) {
				$valid = WBTM_App_Auth::require_user( $request );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				if ( WBTM_App_Auth::current_session() !== $selector || get_current_user_id() !== $user_id ) {
					return WBTM_App_Auth::error( 'wbtm_app_unauthorized', __( 'Please sign in again.', 'addon-bus--ticket-booking-with-seat-pro' ), 401 );
				}
				return WBTM_App_Auth::locked( 'device_' . $key, function () use ( $selector, $user_id, $key, $token ) {
					$existing = get_option( self::DEVICE_PREFIX . $key, array() );
					if ( ! empty( $existing['user_id'] ) && (int) $existing['user_id'] !== $user_id ) {
						return WBTM_App_Auth::error( 'wbtm_app_device_in_use', __( 'This notification device belongs to another account. Sign out there first.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
					}
					$keys = array_unique( get_user_meta( $user_id, self::DEVICE_META ) );
					if ( count( $keys ) >= 10 && ! in_array( $key, $keys, true ) ) {
						return WBTM_App_Auth::error( 'wbtm_app_device_limit', __( 'Remove an old notification device before adding another.', 'addon-bus--ticket-booking-with-seat-pro' ), 409 );
					}
					update_option( self::DEVICE_PREFIX . $key, array( 'token' => $token, 'user_id' => $user_id, 'session' => $selector ), false );
					if ( ! in_array( $key, $keys, true ) ) {
						add_user_meta( $user_id, self::DEVICE_META, $key );
					}
					return array( 'registered' => true );
				} );
			} );
		}

		/** Return only a current owner's live device; lazily remove stale bindings. */
		public static function device_for_push( $key, $user_id ) {
			$device = get_option( self::DEVICE_PREFIX . $key, array() );
			if ( ! is_array( $device ) || (int) ( $device['user_id'] ?? 0 ) !== (int) $user_id ) {
				return false;
			}
			$selector = $device['session'] ?? '';
			if ( ! is_string( $selector ) || ! preg_match( '/^[a-f0-9]{24}$/D', $selector ) ) {
				self::remove_device( $key, $user_id );
				return false;
			}
			$result = WBTM_App_Auth::locked( 'session_' . $selector, function () use ( $key, $user_id, $selector ) {
				return WBTM_App_Auth::locked( 'device_' . $key, function () use ( $key, $user_id, $selector ) {
					$current = get_option( self::DEVICE_PREFIX . $key, array() );
					if ( ! is_array( $current ) || (int) ( $current['user_id'] ?? 0 ) !== (int) $user_id || ( $current['session'] ?? '' ) !== $selector ) {
						return false;
					}
					if ( ! self::valid_token( $current['token'] ?? '' ) || ! WBTM_App_Auth::session_active( $selector, $user_id ) ) {
						delete_option( self::DEVICE_PREFIX . $key );
						delete_user_meta( $user_id, self::DEVICE_META, $key );
						return false;
					}
					return $current;
				} );
			} );
			return is_array( $result ) ? $result : false;
		}

		public function delete_device( $request ) {
			$token = WBTM_App_Auth::string_param( $request, 'token', 250 );
			if ( ! self::valid_token( $token ) ) {
				return WBTM_App_Auth::error( 'wbtm_app_invalid_device', __( 'Invalid device push token.', 'addon-bus--ticket-booking-with-seat-pro' ) );
			}
			self::remove_device( hash( 'sha256', $token ), get_current_user_id() );
			return array( 'registered' => false );
		}

		public static function remove_device( $key, $user_id, $selector = '' ) {
			return WBTM_App_Auth::locked( 'device_' . $key, function () use ( $key, $user_id, $selector ) {
				$device = get_option( self::DEVICE_PREFIX . $key, array() );
				if ( ! empty( $device['user_id'] ) && (int) $device['user_id'] === (int) $user_id && ( '' === $selector || ( isset( $device['session'] ) && hash_equals( $device['session'], $selector ) ) ) ) {
					delete_option( self::DEVICE_PREFIX . $key );
					delete_user_meta( $user_id, self::DEVICE_META, $key );
				}
				return true;
			} );
		}

		public static function remove_session_devices( $user_id, $selector ) {
			foreach ( get_user_meta( $user_id, self::DEVICE_META ) as $key ) {
				$device = get_option( self::DEVICE_PREFIX . $key, array() );
				if ( isset( $device['session'] ) && hash_equals( $device['session'], $selector ) ) {
					self::remove_device( $key, $user_id, $selector );
				}
			}
		}

		public static function remove_all_devices( $user_id ) {
			foreach ( get_user_meta( $user_id, self::DEVICE_META ) as $key ) {
				self::remove_device( $key, $user_id );
			}
			delete_user_meta( $user_id, self::DEVICE_META );
		}
	}
	new WBTM_App_Account();
}
