<?php
/** Limited, revocable connection to a white-label application build dashboard. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Builder_Connection' ) ) {
	class WBTM_App_Builder_Connection {
		const OPTION = 'wbtm_app_builder_connection';
		const PAGE   = 'wbtm-app-builder-connection';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'rest_api_init', array( $this, 'routes' ) );
		}

		public function menu() {
			add_submenu_page( 'edit.php?post_type=wbtm_bus', __( 'App Build Connection', 'bus-ticket-booking-with-seat-reservation' ), __( 'App Build Connection', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', self::PAGE, array( $this, 'page' ) );
		}

		public function routes() {
			foreach ( array( 'manifest', 'release' ) as $route ) {
				register_rest_route( 'wbtm-app/v1', '/builder/' . $route, array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'authorize' ),
					'callback'            => array( $this, $route ),
				) );
			}
		}

		/** No WP user login, customer records, payments or administration scope. */
		public function authorize( $request ) {
			$token = (string) $request->get_header( 'X-WBTM-Builder-Token' );
			$state = get_option( self::OPTION, array() );
			if ( ! is_ssl() || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || empty( $state['hash'] ) || ! hash_equals( $state['hash'], hash( 'sha256', $token ) ) ) {
				return new WP_Error( 'wbtm_builder_unauthorized', __( 'The app build connection is not authorized.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 401 ) );
			}
			if ( 'yes' !== WBTM_App_Settings::get()['app_enabled'] ) {
				return new WP_Error( 'wbtm_app_disabled', __( 'Enable the Mobile App API before connecting the build dashboard.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
			}
			return true;
		}

		public function manifest() {
			$config = rest_do_request( new WP_REST_Request( 'GET', '/wbtm-app/v1/config' ) );
			if ( $config->is_error() ) {
				return $config;
			}
			$response = new WP_REST_Response( array(
				'schema_version' => 1,
				'config'         => $config->get_data(),
				'plugins'        => array(
					'free' => defined( 'WBTM_VERSION' ) ? WBTM_VERSION : '',
					'pro'  => class_exists( 'WBTM_Function_PRO' ),
					'pdf'  => class_exists( 'WBTM_Pro_Pdf' ) && is_callable( array( 'WBTM_Pro_Pdf', 'is_mpdf_available' ) ) && WBTM_Pro_Pdf::is_mpdf_available(),
				),
				'website'        => array( 'home' => home_url( '/' ), 'download_block' => 'wbtm/app-download', 'download_shortcode' => '[wbtm_app_download]' ),
			) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		}

		/** Updating a download URL must preserve every live booking/brand setting. */
		public function release( $request ) {
			$data = $request->get_json_params();
			$url  = is_array( $data ) && isset( $data['url'] ) && is_string( $data['url'] ) ? WBTM_App_Promotion::apk_url( $data['url'] ) : '';
			$uri  = wp_parse_url( $url );
			if ( ! $url || ! is_array( $uri ) || 'https' !== ( $uri['scheme'] ?? '' ) || ! empty( $uri['port'] ) || ! empty( $uri['query'] ) || ! empty( $uri['fragment'] ) || ! isset( $data['version'], $data['sha256'], $data['size'], $data['project_id'] ) || ! is_int( $data['version'] ) || $data['version'] < 1 || ! is_int( $data['size'] ) || $data['size'] < 1024 || $data['size'] > 104857600 || ! is_string( $data['sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $data['sha256'] ) || ! is_string( $data['project_id'] ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $data['project_id'] ) ) {
				return new WP_Error( 'wbtm_builder_invalid_release', __( 'The app release details are invalid.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 400 ) );
			}
			$previous = get_option( 'wbtm_app_builder_release', array() );
			if ( ! empty( $previous['project_id'] ) && ( $previous['project_id'] !== $data['project_id'] || (int) $previous['version'] > $data['version'] || ( (int) $previous['version'] === $data['version'] && $previous['sha256'] !== $data['sha256'] ) ) ) {
				return new WP_Error( 'wbtm_builder_release_conflict', __( 'A different app identity or newer release is already published. An administrator must reset the build connection before replacing it.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
			}
			$settings = get_option( WBTM_App_Settings::OPTION, array() );
			$settings['app_android_apk_url'] = $url;
			update_option( WBTM_App_Settings::OPTION, $settings, false );
			update_option( 'wbtm_app_builder_release', array( 'project_id' => $data['project_id'], 'version' => $data['version'], 'sha256' => $data['sha256'], 'size' => $data['size'], 'url' => $url, 'published' => time() ), false );
			$response = new WP_REST_Response( array( 'ok' => true, 'url' => $url, 'shortcode' => '[wbtm_app_download]' ) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		}

		public function page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Administrator access required.', 'bus-ticket-booking-with-seat-reservation' ) );
			}
			$token = '';
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				check_admin_referer( self::PAGE );
				$action = isset( $_POST['connection_action'] ) && is_string( $_POST['connection_action'] ) ? sanitize_key( wp_unslash( $_POST['connection_action'] ) ) : '';
				if ( 'create' === $action ) {
					$token = bin2hex( random_bytes( 32 ) );
					update_option( self::OPTION, array( 'hash' => hash( 'sha256', $token ), 'created' => time() ), false );
				} elseif ( 'revoke' === $action ) {
					delete_option( self::OPTION );
				} elseif ( 'reset' === $action ) {
					delete_option( self::OPTION );
					delete_option( 'wbtm_app_builder_release' );
				}
			}
			$connected = ! empty( get_option( self::OPTION, array() )['hash'] );
			nocache_headers();
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'App Build Connection', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
				<div class="card">
					<h2><?php esc_html_e( 'Connect your branded customer app', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
					<p><?php esc_html_e( 'Enable the Mobile App API and set your app name and logo under Bus → Settings → Mobile App. Create a connection code below, then enter your HTTPS website address and code in your app build dashboard.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					<p><?php esc_html_e( 'This connection can read public app configuration and publish an Android download URL. It cannot log in as a WordPress user, read customer records, place bookings or take payments.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					<p><strong><?php echo esc_html( $connected ? __( 'Connection enabled', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Connection disabled', 'bus-ticket-booking-with-seat-reservation' ) ); ?></strong></p>
					<?php if ( $token ) : ?>
						<p><?php esc_html_e( 'Copy this code into your dashboard now. It is shown once. Creating a new code revokes the old one.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						<textarea readonly rows="3" class="large-text code" aria-label="<?php esc_attr_e( 'App build connection code', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_textarea( $token ); ?></textarea>
					<?php endif; ?>
					<form method="post">
						<?php wp_nonce_field( self::PAGE ); ?>
						<p><button class="button button-primary" name="connection_action" value="create"><?php esc_html_e( 'Create new connection code', 'bus-ticket-booking-with-seat-reservation' ); ?></button> <button class="button" name="connection_action" value="revoke"><?php esc_html_e( 'Revoke connection', 'bus-ticket-booking-with-seat-reservation' ); ?></button></p>
					</form>
					<details><summary><?php esc_html_e( 'Replace an existing app identity', 'bus-ticket-booking-with-seat-reservation' ); ?></summary>
						<p><?php esc_html_e( 'Resetting permits a new app project to replace the published release. Existing installations may require a separate app installation. Keep the original project and signing key for normal updates.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						<form method="post"><?php wp_nonce_field( self::PAGE ); ?><button class="button" name="connection_action" value="reset"><?php esc_html_e( 'Reset release identity and revoke connection', 'bus-ticket-booking-with-seat-reservation' ); ?></button></form>
					</details>
				</div>
			</div>
			<?php
		}
	}
	new WBTM_App_Builder_Connection();
}
