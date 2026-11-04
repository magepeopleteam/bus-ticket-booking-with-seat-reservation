<?php
/**
 * Operator-managed content for the native customer app home screen.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Home' ) ) {
	class WBTM_App_Home {
		const OPTION = 'wbtm_app_home';
		const PAGE = 'wbtm-app-home';
		const CAPABILITY = 'manage_options';
		const ACTION = 'wbtm_app_home_save';

		public function __construct() {
			add_filter( 'sanitize_option_' . self::OPTION, array( __CLASS__, 'sanitize' ) );
			add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
		}

		public static function sections() {
			return array( 'hero', 'intro', 'search', 'routes', 'content', 'features' );
		}

		public static function icons() {
			return array( 'bus-outline', 'shield-checkmark-outline', 'time-outline', 'ticket-outline', 'headset-outline', 'wallet-outline', 'location-outline', 'leaf-outline' );
		}

		public static function defaults() {
			return array(
				'welcome_text'    => '',
				'heading'         => '',
				'description'     => '',
				'search_title'    => '',
				'section_titles'  => array( 'routes' => '', 'features' => '', 'content' => '' ),
				'section_order'   => self::sections(),
				'hero_slides'     => array(),
				'content_cards'   => array(),
				'featured_routes' => array(),
				'features'        => array(),
			);
		}

		/** Public text is plain text; unknown keys and oversized collections are discarded. */
		public static function sanitize( $input ) {
			$input = is_array( $input ) ? $input : array();
			$output = self::defaults();
			foreach ( array( 'welcome_text', 'heading', 'description', 'search_title' ) as $key ) {
				$output[ $key ] = self::text( $input[ $key ] ?? '', 'description' === $key ? 1200 : 180, 'description' === $key );
			}
			$titles = isset( $input['section_titles'] ) && is_array( $input['section_titles'] ) ? $input['section_titles'] : array();
			foreach ( array_keys( $output['section_titles'] ) as $key ) {
				$output['section_titles'][ $key ] = self::text( $titles[ $key ] ?? '', 180 );
			}
			if ( isset( $input['section_order'] ) && is_array( $input['section_order'] ) ) {
				$output['section_order'] = array();
				foreach ( array_slice( $input['section_order'], 0, 20 ) as $section ) {
					if ( is_string( $section ) && in_array( $section, self::sections(), true ) && ! in_array( $section, $output['section_order'], true ) ) {
						$output['section_order'][] = $section;
					}
				}
				// Section ordering cannot remove the primary customer task.
				if ( ! in_array( 'search', $output['section_order'], true ) ) {
					$output['section_order'][] = 'search';
				}
			}
			foreach ( self::collections() as $group => $definition ) {
				$rows = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? array_slice( $input[ $group ], 0, $definition['limit'] ) : array();
				$ids = array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$item = array();
					foreach ( $definition['fields'] as $key => $label ) {
						$value = $row[ $key ] ?? '';
						if ( str_ends_with( $key, '_url' ) ) {
							$item[ $key ] = self::url( $value );
						} elseif ( 'icon' === $key ) {
							$item[ $key ] = is_string( $value ) && in_array( $value, self::icons(), true ) ? $value : 'bus-outline';
						} else {
							$item[ $key ] = self::text( $value, 'description' === $key ? 1200 : 180, 'description' === $key );
						}
					}
					if ( 'featured_routes' === $group && ( ! $item['boarding_point'] || ! $item['dropping_point'] || $item['boarding_point'] === $item['dropping_point'] ) ) {
						continue;
					}
					if ( 'featured_routes' !== $group && '' === $item['title'] && '' === ( $item['description'] ?? '' ) && '' === ( $item['image_url'] ?? '' ) ) {
						continue;
					}
					$id = isset( $row['id'] ) && is_string( $row['id'] ) ? substr( sanitize_key( $row['id'] ), 0, 64 ) : '';
					if ( '' === $id || in_array( $id, $ids, true ) ) {
						$id = $group . '-' . ( count( $ids ) + 1 );
						while ( in_array( $id, $ids, true ) ) {
							$id .= '-next';
						}
					}
					$ids[] = $id;
					$output[ $group ][] = array_merge( array( 'id' => $id ), $item );
				}
			}
			return $output;
		}

		private static function text( $value, $limit, $multiline = false ) {
			if ( ! is_string( $value ) ) {
				return '';
			}
			$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
		}

		private static function url( $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
				return '';
			}
			$value = trim( $value );
			$parts = wp_parse_url( $value );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return '';
			}
			return esc_url_raw( $value, array( 'http', 'https' ) );
		}

		public static function get() {
			return self::sanitize( get_option( self::OPTION, array() ) );
		}

		/** Never advertise shortcuts to stops removed from the operator's stop registry. */
		public static function public_config() {
			$output = self::get();
			if ( $output['featured_routes'] ) {
				$stops = get_terms( array( 'taxonomy' => 'wbtm_bus_stops', 'hide_empty' => false, 'fields' => 'names' ) );
				$stops = is_wp_error( $stops ) ? array() : $stops;
				$output['featured_routes'] = array_values( array_filter( $output['featured_routes'], function ( $route ) use ( $stops ) {
					return in_array( $route['boarding_point'], $stops, true ) && in_array( $route['dropping_point'], $stops, true );
				} ) );
			}
			return $output;
		}

		private static function collections() {
			$cards = array(
				'title'        => __( 'Title', 'addon-bus--ticket-booking-with-seat-pro' ),
				'description'  => __( 'Description', 'addon-bus--ticket-booking-with-seat-pro' ),
				'image_url'    => __( 'Image', 'addon-bus--ticket-booking-with-seat-pro' ),
				'button_label' => __( 'Button text', 'addon-bus--ticket-booking-with-seat-pro' ),
				'link_url'     => __( 'Button web address (optional)', 'addon-bus--ticket-booking-with-seat-pro' ),
			);
			return array(
				'hero_slides' => array( 'label' => __( 'Hero slider', 'addon-bus--ticket-booking-with-seat-pro' ), 'item' => __( 'Slide', 'addon-bus--ticket-booking-with-seat-pro' ), 'limit' => 8, 'fields' => $cards ),
				'content_cards' => array( 'label' => __( 'Content cards', 'addon-bus--ticket-booking-with-seat-pro' ), 'item' => __( 'Card', 'addon-bus--ticket-booking-with-seat-pro' ), 'limit' => 12, 'fields' => $cards ),
				'featured_routes' => array( 'label' => __( 'Featured journey shortcuts', 'addon-bus--ticket-booking-with-seat-pro' ), 'item' => __( 'Journey', 'addon-bus--ticket-booking-with-seat-pro' ), 'limit' => 12, 'fields' => array(
					'title' => __( 'Title', 'addon-bus--ticket-booking-with-seat-pro' ),
					'subtitle' => __( 'Subtitle', 'addon-bus--ticket-booking-with-seat-pro' ),
					'boarding_point' => __( 'Departure stop', 'addon-bus--ticket-booking-with-seat-pro' ),
					'dropping_point' => __( 'Arrival stop', 'addon-bus--ticket-booking-with-seat-pro' ),
					'image_url' => __( 'Image', 'addon-bus--ticket-booking-with-seat-pro' ),
				) ),
				'features' => array( 'label' => __( 'Benefits', 'addon-bus--ticket-booking-with-seat-pro' ), 'item' => __( 'Benefit', 'addon-bus--ticket-booking-with-seat-pro' ), 'limit' => 8, 'fields' => array(
					'title' => __( 'Title', 'addon-bus--ticket-booking-with-seat-pro' ),
					'description' => __( 'Description', 'addon-bus--ticket-booking-with-seat-pro' ),
					'icon' => __( 'Icon', 'addon-bus--ticket-booking-with-seat-pro' ),
				) ),
			);
		}

		public function register_menu() {
			add_submenu_page( 'edit.php?post_type=wbtm_bus', __( 'Mobile App Home', 'addon-bus--ticket-booking-with-seat-pro' ), __( 'Mobile App Home', 'addon-bus--ticket-booking-with-seat-pro' ), self::CAPABILITY, self::PAGE, array( $this, 'render_page' ) );
		}

		public function enqueue_assets() {
			$screen = get_current_screen();
			if ( ! $screen || 'wbtm_bus_page_' . self::PAGE !== $screen->id || ! current_user_can( self::CAPABILITY ) ) {
				return;
			}
			// https://developer.wordpress.org/reference/functions/wp_enqueue_media/
			wp_enqueue_media();
			wp_enqueue_style( 'wbtm-app-home', WBTM_PLUGIN_URL . '/assets/admin/wbtm_app_home.css', array(), (string) filemtime( __DIR__ . '/../assets/admin/wbtm_app_home.css' ) );
			wp_enqueue_script( 'wbtm-app-home', WBTM_PLUGIN_URL . '/assets/admin/wbtm_app_home.js', array( 'media-views' ), (string) filemtime( __DIR__ . '/../assets/admin/wbtm_app_home.js' ), true );
			wp_localize_script( 'wbtm-app-home', 'wbtmAppHome', array(
				'mediaTitle' => __( 'Choose app image', 'addon-bus--ticket-booking-with-seat-pro' ),
				'mediaButton' => __( 'Use image', 'addon-bus--ticket-booking-with-seat-pro' ),
			) );
		}

		public function save() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You cannot manage mobile app content.', 'addon-bus--ticket-booking-with-seat-pro' ), '', array( 'response' => 403 ) );
			}
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				wp_die( esc_html__( 'Submit the app home form to save changes.', 'addon-bus--ticket-booking-with-seat-pro' ), '', array( 'response' => 405 ) );
			}
			// https://developer.wordpress.org/reference/functions/check_admin_referer/
			check_admin_referer( self::ACTION );
			$input = isset( $_POST[ self::OPTION ] ) && is_array( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : array();
			update_option( self::OPTION, self::sanitize( $input ), false );
			wp_safe_redirect( admin_url( 'edit.php?post_type=wbtm_bus&page=' . self::PAGE . '&saved=1' ) );
			exit;
		}

		public function render_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You cannot manage mobile app content.', 'addon-bus--ticket-booking-with-seat-pro' ), '', array( 'response' => 403 ) );
			}
			$data = self::get();
			$terms = get_terms( array( 'taxonomy' => 'wbtm_bus_stops', 'hide_empty' => false, 'fields' => 'names' ) );
			$stops = is_wp_error( $terms ) ? array() : $terms;
			?>
			<div class="wrap wbtm-app-home">
				<h1><?php esc_html_e( 'Mobile App Home', 'addon-bus--ticket-booking-with-seat-pro' ); ?></h1>
				<p class="description"><?php esc_html_e( 'Create the home screen your customers see. Text, images and section order are read from this site by the app. Empty optional sections stay hidden. Journey fares and seat availability always come from live booking data.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p>
				<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'App home content saved.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<?php wp_nonce_field( self::ACTION ); ?>
					<div class="wbtm-app-home-panel">
						<h2><?php esc_html_e( 'Introduction and headings', 'addon-bus--ticket-booking-with-seat-pro' ); ?></h2>
						<div class="wbtm-app-home-grid">
							<?php
							foreach ( array( 'welcome_text' => __( 'Welcome text', 'addon-bus--ticket-booking-with-seat-pro' ), 'heading' => __( 'Home heading', 'addon-bus--ticket-booking-with-seat-pro' ), 'description' => __( 'Introduction', 'addon-bus--ticket-booking-with-seat-pro' ), 'search_title' => __( 'Journey search heading', 'addon-bus--ticket-booking-with-seat-pro' ) ) as $key => $label ) {
								$this->field( self::OPTION . '[' . $key . ']', $label, $data[ $key ], $key );
							}
							foreach ( array( 'routes' => __( 'Featured journeys heading', 'addon-bus--ticket-booking-with-seat-pro' ), 'content' => __( 'Content section heading', 'addon-bus--ticket-booking-with-seat-pro' ), 'features' => __( 'Benefits heading', 'addon-bus--ticket-booking-with-seat-pro' ) ) as $key => $label ) {
								$this->field( self::OPTION . '[section_titles][' . $key . ']', $label, $data['section_titles'][ $key ], $key );
							}
							?>
						</div>
					</div>
					<div class="wbtm-app-home-panel">
						<h2><?php esc_html_e( 'Section order', 'addon-bus--ticket-booking-with-seat-pro' ); ?></h2>
						<p><?php esc_html_e( 'Use the arrows to arrange sections. Uncheck optional sections to hide them. Journey search stays available.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p>
						<div data-order-list>
							<?php foreach ( array_merge( $data['section_order'], array_diff( self::sections(), $data['section_order'] ) ) as $section ) : ?>
								<div class="wbtm-app-home-order" data-order-row>
									<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[section_order][]' ); ?>" value="<?php echo esc_attr( $section ); ?>" <?php checked( in_array( $section, $data['section_order'], true ) ); ?> <?php echo 'search' === $section ? 'disabled' : ''; ?> /> <?php echo esc_html( $this->section_label( $section ) ); ?></label>
									<?php if ( 'search' === $section ) : ?><input type="hidden" name="<?php echo esc_attr( self::OPTION . '[section_order][]' ); ?>" value="search" /><?php endif; ?>
									<?php $this->order_buttons(); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php foreach ( self::collections() as $group => $definition ) : ?>
						<section class="wbtm-app-home-panel" data-collection="<?php echo esc_attr( $group ); ?>" data-limit="<?php echo esc_attr( $definition['limit'] ); ?>" data-item-label="<?php echo esc_attr( $definition['item'] ); ?>">
							<h2><?php echo esc_html( $definition['label'] ); ?></h2>
							<?php if ( 'hero_slides' === $group || 'content_cards' === $group ) : ?><p class="description"><?php esc_html_e( 'Choose images from the Media Library or enter public image URLs. A button without a web address opens journey search.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p><?php endif; ?>
							<?php if ( 'featured_routes' === $group ) : ?><p class="description"><?php esc_html_e( 'Choose registered stops. The app only shows shortcuts that remain in the live route list. These cards never contain fixed fares or seat counts.', 'addon-bus--ticket-booking-with-seat-pro' ); ?></p><?php endif; ?>
							<div data-rows>
								<?php foreach ( $data[ $group ] as $index => $row ) { $this->render_row( $group, $definition, $index, $row, $stops ); } ?>
							</div>
							<template data-template><?php $this->render_row( $group, $definition, '__INDEX__', array(), $stops ); ?></template>
							<button type="button" class="button button-secondary" data-add><?php echo esc_html( sprintf( __( 'Add %s', 'addon-bus--ticket-booking-with-seat-pro' ), $definition['item'] ) ); ?></button>
							<p class="description"><?php echo esc_html( sprintf( __( 'Maximum items: %d', 'addon-bus--ticket-booking-with-seat-pro' ), $definition['limit'] ) ); ?></p>
						</section>
					<?php endforeach; ?>
					<?php submit_button( __( 'Save app home', 'addon-bus--ticket-booking-with-seat-pro' ) ); ?>
				</form>
			</div>
			<?php
		}

		private function section_label( $section ) {
			$labels = array(
				'hero' => __( 'Hero slider', 'addon-bus--ticket-booking-with-seat-pro' ),
				'intro' => __( 'Introduction', 'addon-bus--ticket-booking-with-seat-pro' ),
				'search' => __( 'Journey search', 'addon-bus--ticket-booking-with-seat-pro' ),
				'routes' => __( 'Featured journeys', 'addon-bus--ticket-booking-with-seat-pro' ),
				'content' => __( 'Content cards', 'addon-bus--ticket-booking-with-seat-pro' ),
				'features' => __( 'Benefits', 'addon-bus--ticket-booking-with-seat-pro' ),
			);
			return $labels[ $section ];
		}

		private function order_buttons() {
			?>
			<div class="wbtm-app-home-actions">
				<button type="button" class="button" data-up aria-label="<?php esc_attr_e( 'Move up', 'addon-bus--ticket-booking-with-seat-pro' ); ?>">&uarr;</button>
				<button type="button" class="button" data-down aria-label="<?php esc_attr_e( 'Move down', 'addon-bus--ticket-booking-with-seat-pro' ); ?>">&darr;</button>
			</div>
			<?php
		}

		private function render_row( $group, $definition, $index, $row, $stops ) {
			$base = self::OPTION . '[' . $group . '][' . $index . ']';
			?>
			<div class="wbtm-app-home-item" data-item>
				<div class="wbtm-app-home-item-header">
					<h3 data-row-title><?php echo esc_html( $definition['item'] ); ?></h3>
					<?php $this->order_buttons(); ?>
					<button type="button" class="button-link-delete" data-remove><?php esc_html_e( 'Remove', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button>
				</div>
				<input type="hidden" data-item-id name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $row['id'] ?? '' ); ?>" />
				<div class="wbtm-app-home-grid">
					<?php foreach ( $definition['fields'] as $key => $label ) { $this->field( $base . '[' . $key . ']', $label, $row[ $key ] ?? '', $key, $stops ); } ?>
				</div>
			</div>
			<?php
		}

		private function field( $name, $label, $value, $key, $stops = array() ) {
			?>
			<label class="wbtm-app-home-field <?php echo 'description' === $key ? 'wbtm-app-home-wide' : ''; ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<?php if ( 'description' === $key ) : ?>
					<textarea name="<?php echo esc_attr( $name ); ?>" rows="3" maxlength="1200"><?php echo esc_textarea( $value ); ?></textarea>
				<?php elseif ( 'icon' === $key || in_array( $key, array( 'boarding_point', 'dropping_point' ), true ) ) : ?>
					<select name="<?php echo esc_attr( $name ); ?>">
						<option value=""><?php esc_html_e( 'Select', 'addon-bus--ticket-booking-with-seat-pro' ); ?></option>
						<?php foreach ( 'icon' === $key ? self::icons() : $stops as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $option ); ?></option>
						<?php endforeach; ?>
						<?php if ( $value && 'icon' !== $key && ! in_array( $value, $stops, true ) ) : ?><option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( sprintf( __( '%s (stop removed)', 'addon-bus--ticket-booking-with-seat-pro' ), $value ) ); ?></option><?php endif; ?>
					</select>
				<?php else : ?>
					<input type="<?php echo str_ends_with( $key, '_url' ) ? 'url' : 'text'; ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo str_ends_with( $key, '_url' ) ? '2048' : '180'; ?>" <?php echo 'image_url' === $key ? 'data-image-url' : ''; ?> />
					<?php if ( 'image_url' === $key ) : ?>
						<span class="wbtm-app-home-actions"><button type="button" class="button" data-image-choose><?php esc_html_e( 'Choose image', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button><button type="button" class="button" data-image-clear><?php esc_html_e( 'Clear image', 'addon-bus--ticket-booking-with-seat-pro' ); ?></button></span>
						<img data-image-preview alt="" <?php if ( $value ) : ?>src="<?php echo esc_url( $value ); ?>"<?php else : ?>hidden<?php endif; ?> />
					<?php endif; ?>
				<?php endif; ?>
			</label>
			<?php
		}
	}
	new WBTM_App_Home();
}
