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
			$hook = add_submenu_page( 'edit.php?post_type=wbtm_bus', __( 'App Build Connection', 'bus-ticket-booking-with-seat-reservation' ), __( 'App Build Connection', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', self::PAGE, array( $this, 'page' ) );
			if ( class_exists( 'WBTM_App_Home' ) ) {
				// Keep the registered route for bookmarks, without a second menu item.
				remove_submenu_page( 'edit.php?post_type=wbtm_bus', self::PAGE );
				add_action( 'load-' . $hook, array( $this, 'redirect_to_workspace' ) );
			}
		}

		public static function workspace_url() {
			return admin_url( 'edit.php?post_type=wbtm_bus&page=wbtm-app-home&app_tab=connection' );
		}

		public function redirect_to_workspace() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Administrator access required.', 'bus-ticket-booking-with-seat-reservation' ), '', array( 'response' => 403 ) );
			}
			// Preserve submissions from an already open legacy connection form.
			wp_safe_redirect( self::workspace_url(), 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? 307 : 302 );
			exit;
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
			$variant = self::request_variant( $request );
			if ( is_wp_error( $variant ) ) {
				return $variant;
			}
			if ( 'operator' === $variant ) {
				if ( ! class_exists( 'WBTM_Operator_Auth' ) || ! class_exists( 'WBTM_Operator_Settings' ) || ! is_callable( array( 'WBTM_Operator', 'enabled' ) ) ) {
					return new WP_Error( 'wbtm_operator_builder_unavailable', __( 'Operator App builds require the compatible PRO operations provider.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
				}
				if ( ! WBTM_Operator::enabled() ) {
					return new WP_Error( 'wbtm_operator_builder_disabled', __( 'Enable Operator App and resolve the operations provider status before building.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
				}
				return true;
			}
			if ( 'yes' !== WBTM_App_Settings::get()['app_enabled'] ) {
				return new WP_Error( 'wbtm_app_disabled', __( 'Enable the Mobile App API before connecting the build dashboard.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
			}
			return true;
		}

		/**
		 * Read the strict build variant, retaining the legacy customer default.
		 *
		 * @param WP_REST_Request $request Limited connector request.
		 * @return string|WP_Error Valid variant or input error.
		 */
		private static function request_variant( $request ) {
			$data    = $request->get_json_params();
			$variant = is_array( $data ) && array_key_exists( 'app_variant', $data ) ? $data['app_variant'] : 'customer';
			if ( ! in_array( $variant, array( 'customer', 'operator' ), true ) ) {
				return new WP_Error( 'wbtm_builder_invalid_variant', __( 'Choose Customer App or Operator App.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 400 ) );
			}
			return $variant;
		}

		/**
		 * Identify the two limited routes independent of customer API enablement.
		 *
		 * @param WP_REST_Request $request Incoming REST request.
		 * @return bool Whether the connector owns this request's access gate.
		 */
		public static function is_operator_builder_request( $request ) {
			return 'POST' === $request->get_method() && in_array( $request->get_route(), array( '/wbtm-app/v1/builder/manifest', '/wbtm-app/v1/builder/release' ), true ) && 'operator' === self::request_variant( $request );
		}

		/**
		 * Normalize public configuration for the selected app variant.
		 *
		 * @param WP_REST_Request $request Authorized limited connector request.
		 * @return WP_REST_Response|WP_Error Build configuration or provider error.
		 */
		public function manifest( $request ) {
			$variant = self::request_variant( $request );
			if ( is_wp_error( $variant ) ) {
				return $variant;
			}
			$config = rest_do_request( new WP_REST_Request( 'GET', 'operator' === $variant ? '/wbtm-operator/v1/config' : '/wbtm-app/v1/config' ) );
			if ( $config->is_error() ) {
				return $config;
			}
			$config_data = $config->get_data();
			if ( 'operator' === $variant ) {
				// Map only public operator branding and declared capabilities, never arbitrary data.
				if ( 1 !== ( $config_data['schema_version'] ?? null ) || true !== ( $config_data['enabled'] ?? null ) || home_url( '/' ) !== ( $config_data['site_url'] ?? '' ) ) {
					return new WP_Error( 'wbtm_operator_builder_unavailable', __( 'The Operator App configuration is incompatible or unavailable.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
				}
				$logo = is_string( $config_data['logo'] ?? null ) ? esc_url_raw( $config_data['logo'], array( 'https' ) ) : '';
				$uri  = wp_parse_url( $logo );
				$name = is_string( $config_data['name'] ?? null ) ? sanitize_text_field( $config_data['name'] ) : '';
				if ( '' === $name || ! $logo || ! is_array( $uri ) || 'https' !== ( $uri['scheme'] ?? '' ) || empty( $uri['host'] ) || ! empty( $uri['user'] ) || ! empty( $uri['pass'] ) || ! empty( $uri['port'] ) || ! empty( $uri['fragment'] ) ) {
					return new WP_Error( 'wbtm_operator_builder_branding', __( 'Choose an Operator App name and HTTPS logo before building.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
				}
				$features = array(
					'pro'      => true,
					'operator' => true,
				);
				foreach ( array( 'qr_scan', 'manual_checkin', 'manifest_pdf', 'manifest_csv' ) as $feature ) {
					$features[ $feature ] = true === ( $config_data['capabilities'][ $feature ] ?? false );
				}
				$primary_color = sanitize_hex_color( $config_data['primary_color'] ?? '' );
				$config_data   = array(
					'schema_version'   => 1,
					'site'             => array( 'url' => home_url( '/' ) ),
					'branding'         => array(
						'name'          => $name,
						'logo_url'      => $logo,
						'primary_color' => $primary_color ? $primary_color : '#5445d8',
					),
					'backend_features' => $features,
					'release'          => array( 'ios' => array( 'store_url' => '' ) ),
				);
			}
			$response = new WP_REST_Response( array(
				'schema_version' => 1,
				'app_variant'    => $variant,
				'capabilities'   => array( 'operator' => 'operator' === $variant ),
				'config'         => $config_data,
				'plugins'        => array(
					'free' => defined( 'WBTM_VERSION' ) ? WBTM_VERSION : '',
					'pro'  => class_exists( 'WBTM_Function_PRO' ),
					'pdf'  => class_exists( 'WBTM_Pro_Pdf' ) && is_callable( array( 'WBTM_Pro_Pdf', 'is_mpdf_available' ) ) && WBTM_Pro_Pdf::is_mpdf_available(),
				),
				'website'        => array(
					'home'                      => home_url( '/' ),
					'download_block'            => 'wbtm/app-download',
					'download_block_attributes' => array( 'appVariant' => $variant ),
					'download_shortcode'        => 'operator' === $variant ? '[wbtm_operator_app_download]' : '[wbtm_app_download]',
				),
			) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		}

		/** Updating a download URL must preserve every live booking/brand setting. */
		public function release( $request ) {
			$variant = self::request_variant( $request );
			if ( is_wp_error( $variant ) ) {
				return $variant;
			}
			$data = $request->get_json_params();
			$url  = is_array( $data ) && isset( $data['url'] ) && is_string( $data['url'] ) ? WBTM_App_Promotion::apk_url( $data['url'] ) : '';
			$uri  = wp_parse_url( $url );
			if ( ! $url || ! is_array( $uri ) || 'https' !== ( $uri['scheme'] ?? '' ) || ! empty( $uri['port'] ) || ! empty( $uri['query'] ) || ! empty( $uri['fragment'] ) || ! isset( $data['version'], $data['sha256'], $data['size'], $data['project_id'] ) || ! is_int( $data['version'] ) || $data['version'] < 1 || ! is_int( $data['size'] ) || $data['size'] < 1024 || $data['size'] > 104857600 || ! is_string( $data['sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $data['sha256'] ) || ! is_string( $data['project_id'] ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $data['project_id'] ) ) {
				return new WP_Error( 'wbtm_builder_invalid_release', __( 'The app release details are invalid.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 400 ) );
			}
			$release_option = 'operator' === $variant ? 'wbtm_operator_builder_release' : 'wbtm_app_builder_release';
			$previous       = get_option( $release_option, array() );
			if ( ! empty( $previous['project_id'] ) && ( $previous['project_id'] !== $data['project_id'] || (int) $previous['version'] > $data['version'] || ( (int) $previous['version'] === $data['version'] && $previous['sha256'] !== $data['sha256'] ) ) ) {
				return new WP_Error( 'wbtm_builder_release_conflict', __( 'A different app identity or newer release is already published. An administrator must reset the build connection before replacing it.', 'bus-ticket-booking-with-seat-reservation' ), array( 'status' => 409 ) );
			}
			$settings_option = 'operator' === $variant ? WBTM_Operator_Settings::OPTION : WBTM_App_Settings::OPTION;
			$settings        = get_option( $settings_option, array() );
			$settings        = is_array( $settings ) ? $settings : array();
			$settings[ 'operator' === $variant ? 'android_apk_url' : 'app_android_apk_url' ] = $url;
			update_option( $settings_option, $settings, false );
			update_option(
				$release_option,
				array(
					'project_id' => $data['project_id'],
					'version'    => $data['version'],
					'sha256'     => $data['sha256'],
					'size'       => $data['size'],
					'url'        => $url,
					'published'  => time(),
				),
				false
			);
			$response = new WP_REST_Response(
				array(
					'ok'               => true,
					'url'              => $url,
					'app_variant'      => $variant,
					'shortcode'        => 'operator' === $variant ? '[wbtm_operator_app_download]' : '[wbtm_app_download]',
					'block'            => 'wbtm/app-download',
					'block_attributes' => array( 'appVariant' => $variant ),
				)
			);
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		}

		/** Process only the connection form; home-content saves use their own action. */
		public static function handle_actions() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Administrator access required.', 'bus-ticket-booking-with-seat-reservation' ), '', array( 'response' => 403 ) );
			}
			$token = '';
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['connection_action'] ) ) {
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
				} elseif ( 'reset_operator' === $action ) {
					delete_option( 'wbtm_operator_builder_release' );
				}
			}
			return $token;
		}

		/** This panel owns separate nonce-protected forms, never nested in the home form. */
		public static function render_panel( $token = '' ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$state = get_option( self::OPTION, array() );
			$connected = ! empty( $state['hash'] );
			$settings = WBTM_App_Settings::get();
			$release = get_option( 'wbtm_app_builder_release', array() );
			?>
				<h2><?php esc_html_e( 'Build connection', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
					<p><?php esc_html_e( 'Connect this website to your app build dashboard. Choose Customer App or Operator App in the dashboard; each has separate branding, signing identity and release history. Operator App requires compatible PRO and its own enablement.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				<div class="wbtm-build-grid">
				<div class="wbtm-build-card">
					<div class="wbtm-build-card-heading"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><h3><?php esc_html_e( 'Website connection', 'bus-ticket-booking-with-seat-reservation' ); ?></h3><span class="wbtm-home-status <?php echo $connected ? 'is-enabled' : ''; ?>"><?php echo esc_html( $connected ? __( 'Code active', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Not configured', 'bus-ticket-booking-with-seat-reservation' ) ); ?></span></div>
					<label class="wbtm-app-home-field"><span><?php esc_html_e( 'Website address', 'bus-ticket-booking-with-seat-reservation' ); ?></span><input type="url" readonly value="<?php echo esc_url( home_url( '/' ) ); ?>" /></label>
					<?php if ( 'yes' !== $settings['app_enabled'] ) : ?>
						<p class="wbtm-build-message"><?php esc_html_e( 'Customer App requires the Mobile App API in App branding & settings. Operator App uses its separate PRO enablement and can connect while the customer API is disabled.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					<?php endif; ?>
					<?php if ( $token ) : ?>
						<div class="wbtm-build-code"><label class="wbtm-app-home-field"><span><?php esc_html_e( 'Your new connection code', 'bus-ticket-booking-with-seat-reservation' ); ?></span><textarea readonly rows="3" class="large-text code" data-connection-code><?php echo esc_textarea( $token ); ?></textarea></label><button type="button" class="button" data-copy-connection><?php esc_html_e( 'Copy code', 'bus-ticket-booking-with-seat-reservation' ); ?></button><span class="wbtm-build-copy-status" data-copy-status role="status"></span><p><?php esc_html_e( 'Copy this code into your dashboard now. It is shown once. Creating a new code revokes the old one.', 'bus-ticket-booking-with-seat-reservation' ); ?></p></div>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( self::workspace_url() ); ?>" data-connection-form>
						<?php wp_nonce_field( self::PAGE ); ?>
						<div class="wbtm-app-home-actions"><button class="button button-primary" name="connection_action" value="create"><?php esc_html_e( 'Create new connection code', 'bus-ticket-booking-with-seat-reservation' ); ?></button><button class="button" name="connection_action" value="revoke" <?php disabled( ! $connected ); ?>><?php esc_html_e( 'Revoke connection', 'bus-ticket-booking-with-seat-reservation' ); ?></button></div>
					</form>
					<p class="description"><?php esc_html_e( 'The shared code allows public app configuration and download publishing for both app types. Creating or revoking a code affects both dashboard connections. WordPress logins, passengers, customer records and payments remain private.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				</div>
				<div class="wbtm-build-card wbtm-build-guide"><h3><?php esc_html_e( 'From website to app', 'bus-ticket-booking-with-seat-reservation' ); ?></h3><ol><li><strong><?php esc_html_e( 'Prepare your app', 'bus-ticket-booking-with-seat-reservation' ); ?></strong><span><?php esc_html_e( 'Set your name and logo in App branding & settings, then manage your home screen in these tabs.', 'bus-ticket-booking-with-seat-reservation' ); ?></span></li><li><strong><?php esc_html_e( 'Connect your dashboard', 'bus-ticket-booking-with-seat-reservation' ); ?></strong><span><?php esc_html_e( 'Add this website address and your new connection code to the build dashboard.', 'bus-ticket-booking-with-seat-reservation' ); ?></span></li><li><strong><?php esc_html_e( 'Build and publish', 'bus-ticket-booking-with-seat-reservation' ); ?></strong><span><?php esc_html_e( 'Build your Android app, then publish the download to this website from your dashboard.', 'bus-ticket-booking-with-seat-reservation' ); ?></span></li></ol></div>
				</div>
				<?php if ( ! empty( $release['url'] ) ) : ?>
					<div class="wbtm-build-card wbtm-build-release"><span class="dashicons dashicons-download" aria-hidden="true"></span><div><h3><?php esc_html_e( 'Published Android release', 'bus-ticket-booking-with-seat-reservation' ); ?></h3><p><?php echo esc_html( sprintf( __( 'Version code %1$d · %2$s', 'bus-ticket-booking-with-seat-reservation' ), (int) $release['version'], size_format( (int) $release['size'], 1 ) ) ); ?></p></div><a class="button" href="<?php echo esc_url( $release['url'] ); ?>"><?php esc_html_e( 'Download APK', 'bus-ticket-booking-with-seat-reservation' ); ?></a></div>
				<?php endif; ?>
					<details class="wbtm-build-advanced"><summary><?php esc_html_e( 'Replace an existing app identity', 'bus-ticket-booking-with-seat-reservation' ); ?></summary>
						<p><?php esc_html_e( 'Resetting permits a new app project to replace the published release. Existing installations may require a separate app installation. Keep the original project and signing key for normal updates.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						<form method="post" action="<?php echo esc_url( self::workspace_url() ); ?>" data-connection-form><?php wp_nonce_field( self::PAGE ); ?><button class="button" name="connection_action" value="reset"><?php esc_html_e( 'Reset Customer App release identity and revoke shared connection', 'bus-ticket-booking-with-seat-reservation' ); ?></button></form>
						<form method="post" action="<?php echo esc_url( self::workspace_url() ); ?>" data-connection-form><?php wp_nonce_field( self::PAGE ); ?><button class="button" name="connection_action" value="reset_operator"><?php esc_html_e( 'Reset Operator App release identity only', 'bus-ticket-booking-with-seat-reservation' ); ?></button></form>
					</details>
			<?php
		}

		public function page() {
			$token = self::handle_actions();
			nocache_headers();
			echo '<div class="wrap">';
			self::render_panel( $token );
			echo '</div>';
		}
	}
	new WBTM_App_Builder_Connection();
}
