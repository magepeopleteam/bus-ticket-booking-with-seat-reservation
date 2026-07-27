<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

/**
 * Inline login/registration for the Standalone (Custom Payment) booking flow.
 *
 * Renders a footer modal + handles the two nopriv AJAX actions it posts to.
 * The booking submission itself lives in assets/global/wbtm_global.js (free
 * plugin) — it fires a 'wbtm_require_login' jQuery event carrying the original
 * request payload and a retry callback; wbtm-standalone-auth.js listens for
 * that event, and on successful login/registration here simply calls the
 * retry callback again so the exact same booking request goes through, now
 * authenticated.
 */
if ( ! class_exists( 'WBTM_Standalone_Auth' ) ) {
	class WBTM_Standalone_Auth {

		const NONCE_ACTION = 'wbtm_standalone_auth';

		public function __construct() {
			add_action( 'wp_footer', array( $this, 'render_modal' ) );
			add_action( 'wbtm_add_frontend_script', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'wp_ajax_nopriv_wbtm_standalone_login', array( $this, 'ajax_login' ) );
			add_action( 'wp_ajax_nopriv_wbtm_standalone_register', array( $this, 'ajax_register' ) );
			add_action( 'wp_ajax_wbtm_standalone_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
		}

		private function should_render() {
			return ! is_user_logged_in()
				&& class_exists( 'WBTM_Functions' )
				&& WBTM_Functions::login_required()
				&& WBTM_Functions::booking_mode() === 'standalone'
				&& did_action( 'wbtm_add_frontend_script' );
		}

		public function enqueue_frontend_assets() {
			wp_enqueue_style( 'wbtm_standalone_auth', WBTM_PLUGIN_URL . '/assets/frontend/wbtm-standalone-auth.css', array(), WBTM_VERSION );
			wp_enqueue_script( 'wbtm_standalone_auth', WBTM_PLUGIN_URL . '/assets/frontend/wbtm-standalone-auth.js', array( 'jquery' ), WBTM_VERSION, true );
			wp_localize_script(
				'wbtm_standalone_auth',
				'wbtm_standalone_auth_params',
				array(
					'ajax_url'            => admin_url( 'admin-ajax.php' ),
					'nonce'               => wp_create_nonce( self::NONCE_ACTION ),
					'min_password_length' => 8,
					'i18n'                => array(
						'processing'       => esc_html__( 'Please wait…', 'bus-ticket-booking-with-seat-reservation' ),
						'log_in'           => esc_html__( 'Log In', 'bus-ticket-booking-with-seat-reservation' ),
						'create_account'   => esc_html__( 'Create Account & Continue', 'bus-ticket-booking-with-seat-reservation' ),
						'error_generic'    => esc_html__( 'Something went wrong. Please try again.', 'bus-ticket-booking-with-seat-reservation' ),
						'password_too_short' => sprintf(
							/* translators: %d: minimum password length */
							esc_html__( 'Password must be at least %d characters.', 'bus-ticket-booking-with-seat-reservation' ),
							8
						),
					),
				)
			);
		}

		public function render_modal() {
			if ( ! $this->should_render() ) {
				return;
			}
			?>
			<div id="wbtm-auth-modal" class="wbtm-auth-modal" role="dialog" aria-modal="true" aria-labelledby="wbtm-auth-modal-title">
				<div class="wbtm-auth-modal-box">
					<div class="wbtm-auth-modal-header">
						<h2 id="wbtm-auth-modal-title"><?php esc_html_e( 'Sign in to continue', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
						<button type="button" class="wbtm-auth-modal-close" aria-label="<?php esc_attr_e( 'Close', 'bus-ticket-booking-with-seat-reservation' ); ?>">&times;</button>
					</div>
					<p class="wbtm-auth-modal-subtitle"><?php esc_html_e( 'Your selected seats are saved — just log in or create an account to finish booking.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>

					<div class="wbtm-auth-tabs" role="tablist">
						<button type="button" class="wbtm-auth-tab is-active" data-tab="login" role="tab" aria-selected="true">
							<?php esc_html_e( 'Log In', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</button>
						<button type="button" class="wbtm-auth-tab" data-tab="register" role="tab" aria-selected="false">
							<?php esc_html_e( 'Register', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</button>
					</div>

					<p class="wbtm-auth-error" style="display:none;" role="alert"></p>

					<form class="wbtm-auth-panel wbtm-auth-panel-login is-active" data-mode="login">
						<label class="wbtm-auth-field">
							<span><?php esc_html_e( 'Email or Username', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<input type="text" name="login" autocomplete="username" required />
						</label>
						<label class="wbtm-auth-field wbtm-auth-field-password">
							<span><?php esc_html_e( 'Password', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<span class="wbtm-auth-password-wrap">
								<input type="password" name="password" autocomplete="current-password" required />
								<button type="button" class="wbtm-auth-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'bus-ticket-booking-with-seat-reservation' ); ?>">👁</button>
							</span>
						</label>
						<a class="wbtm-auth-lost-password" href="<?php echo esc_url( wp_lostpassword_url() ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Lost your password?', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</a>
						<button type="submit" class="wbtm-auth-submit">
							<?php esc_html_e( 'Log In', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</button>
					</form>

					<form class="wbtm-auth-panel wbtm-auth-panel-register" data-mode="register">
						<label class="wbtm-auth-field">
							<span><?php esc_html_e( 'Full Name', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<input type="text" name="name" autocomplete="name" required />
						</label>
						<label class="wbtm-auth-field">
							<span><?php esc_html_e( 'Email', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<input type="email" name="email" autocomplete="email" required />
						</label>
						<label class="wbtm-auth-field wbtm-auth-field-password">
							<span><?php esc_html_e( 'Password', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<span class="wbtm-auth-password-wrap">
								<input type="password" name="password" autocomplete="new-password" required minlength="8" />
								<button type="button" class="wbtm-auth-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'bus-ticket-booking-with-seat-reservation' ); ?>">👁</button>
							</span>
						</label>
						<button type="submit" class="wbtm-auth-submit">
							<?php esc_html_e( 'Create Account & Continue', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</button>
					</form>
				</div>
			</div>
			<?php
		}

		public function ajax_login() {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

			if ( ! $login || ! $password ) {
				wp_send_json_error( array( 'message' => __( 'Please enter your email/username and password.', 'bus-ticket-booking-with-seat-reservation' ) ), 400 );
			}

			if ( is_email( $login ) ) {
				$existing = get_user_by( 'email', $login );
				if ( $existing ) {
					$login = $existing->user_login;
				}
			}

			$user = wp_signon(
				array(
					'user_login'    => $login,
					'user_password' => $password,
					'remember'      => true,
				),
				is_ssl()
			);

			if ( is_wp_error( $user ) ) {
				// Deliberately generic — don't reveal whether the account exists.
				wp_send_json_error( array( 'message' => __( 'The email/username or password you entered is incorrect.', 'bus-ticket-booking-with-seat-reservation' ) ), 401 );
			}

			wp_set_current_user( $user->ID );

			// Do NOT mint the fresh wbtm_form_nonce here: wp_signon() only queues the
			// logged_in cookie via a Set-Cookie header, it can't update $_COOKIE on this
			// same request — so wp_create_nonce() would still hash against the old
			// (guest) session token and silently produce a nonce that fails verification
			// on the very next request. The client fetches it via ajax_refresh_nonce()
			// once the browser has actually received and can resend the real cookie.
			wp_send_json_success( array( 'logged_in' => true ) );
		}

		public function ajax_register() {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );

			$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

			if ( ! $name || ! $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Please provide your name and a valid email address.', 'bus-ticket-booking-with-seat-reservation' ) ), 400 );
			}
			if ( strlen( $password ) < 8 ) {
				wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'bus-ticket-booking-with-seat-reservation' ) ), 400 );
			}
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please log in instead.', 'bus-ticket-booking-with-seat-reservation' ) ), 409 );
			}

			$username = $this->generate_username_from_email( $email );

			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => $password,
					'display_name' => $name,
					'nickname'     => $name,
					'role'         => 'subscriber',
				)
			);

			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 400 );
			}

			$user = get_user_by( 'id', $user_id );
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, true, is_ssl() );
			// wp_insert_user() doesn't fire wp_login itself — mirrors the manual login-hook
			// call every "register-inline-during-checkout" implementation needs.
			do_action( 'wp_login', $user->user_login, $user );

			// Fire-and-forget welcome email; must never block or fail the booking flow.
			wp_new_user_notification( $user_id, null, 'user' );

			// See the comment in ajax_login() — same reason no nonce is minted here.
			wp_send_json_success( array( 'logged_in' => true ) );
		}

		/**
		 * Called by the client immediately after a successful login/register AJAX
		 * response, in a genuinely new request where the browser has now attached the
		 * real logged_in cookie — so wp_create_nonce() hashes against the correct
		 * session token and the returned nonce actually verifies on the booking retry.
		 *
		 * No nonce check here by design (same reasoning as the free plugin's own
		 * comment on this exact problem): a nonce minted in the same request/response
		 * cycle as the login is unreliable, and this endpoint only ever returns a
		 * nonce scoped to the caller's own already-authenticated session — it has no
		 * side effects and leaks nothing across users.
		 */
		public function ajax_refresh_nonce() {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => __( 'Not logged in.', 'bus-ticket-booking-with-seat-reservation' ) ), 401 );
			}
			wp_send_json_success( array( 'form_nonce' => wp_create_nonce( 'wbtm_form_nonce' ) ) );
		}

		private function generate_username_from_email( $email ) {
			$local    = substr( $email, 0, strpos( $email, '@' ) );
			$base     = sanitize_user( $local, true );
			if ( ! $base ) {
				$base = 'customer';
			}
			$username = $base;
			$suffix   = 1;
			while ( username_exists( $username ) ) {
				$username = $base . $suffix;
				++$suffix;
			}
			return $username;
		}
	}
	new WBTM_Standalone_Auth();
}
