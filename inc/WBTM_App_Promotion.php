<?php
/**
 * Opt-in, server-rendered app download card for public bus search pages.
 *
 * @package WBTM_Pro
 */

// phpcs:disable WordPress.Files.FileName -- Preserve the established WBTM module filename convention.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Promotion' ) ) {
	/** Uses existing global app settings without adding a public write endpoint. */
	class WBTM_App_Promotion {
		const STYLE_HANDLE = 'wbtm-app-promotion';
		const BLOCK_NAME   = 'wbtm/app-download';

		/**
		 * Avoid duplicate cards when a theme renders the content more than once.
		 *
		 * @var array
		 */
		private $rendered = array();

		/** Register the public shortcode, content filter and scoped asset hook. */
		public function __construct() {
			add_shortcode( 'wbtm_app_download', array( $this, 'shortcode' ) );
			add_shortcode( 'wbtm_operator_app_download', array( $this, 'operator_shortcode' ) );
			add_action( 'init', array( $this, 'register_block' ) );
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			// Insert generated HTML after wpautop and do_shortcode have formatted
			// the page; otherwise wpautop adds breaks and empty grid children.
			add_filter( 'the_content', array( $this, 'prepend_to_search' ), 12 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/** Download branding remains available independently of native API opt-in. */
		public function register_routes() {
			register_rest_route(
				'wbtm/v1',
				'/app-download-info',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'download_info' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/** Only deliberately public branding is exposed, never raw settings. */
		public function download_info() {
			$name     = self::app_name( WBTM_App_Settings::get() );
			$response = new WP_REST_Response(
				array(
					'name'     => $name,
					'filename' => self::apk_filename( $name ),
				)
			);
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}

		/**
		 * Use the same administrator app name/site-title fallback everywhere.
		 *
		 * @param array $settings Sanitized presentation settings.
		 * @return string Public app name.
		 */
		private static function app_name( $settings ) {
			return $settings['app_name'] ? $settings['app_name'] : html_entity_decode( wp_strip_all_tags( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' );
		}

		/**
		 * Direct installation links must be absolute HTTPS URLs without userinfo.
		 *
		 * @param mixed $value Candidate download URL.
		 * @return string Validated URL, or an empty string.
		 */
		public static function apk_url( $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 2048 ) {
				return '';
			}
			$url   = esc_url_raw( trim( $value ), array( 'https' ) );
			$parts = wp_parse_url( $url );
			return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] )
				&& 'https' === strtolower( $parts['scheme'] ) && '' !== $parts['host']
				&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? $url : '';
		}

		/**
		 * Suggest the current app name without unsafe path/control characters.
		 * The download host must send the matching Content-Disposition filename.
		 *
		 * @param string $name Public administrator app name (or site-title fallback).
		 * @return string A bounded UTF-8 Android filename.
		 */
		public static function apk_filename( $name ) {
			$stem = preg_replace( '/[\x{0000}-\x{001f}\x{007f}-\x{009f}\x{061c}\x{200e}\x{200f}\x{202a}-\x{202e}\x{2066}-\x{2069}\x{feff}]/u', '', $name );
			$stem = preg_replace( '/[\\\\\/<>:"|?*%]+/u', ' ', $stem );
			$stem = trim( preg_replace( '/\s+/u', ' ', $stem ), ' .' );
			$stem = rtrim( preg_replace( '/(?:\.apk)+$/iu', '', $stem ), ' .' );
			// Split on Unicode codepoints so truncation cannot cut UTF-8 bytes.
			$bounded = '';
			foreach ( preg_split( '//u', $stem, -1, PREG_SPLIT_NO_EMPTY ) as $character ) {
				if ( strlen( $bounded . $character ) > 180 ) {
					break;
				}
				$bounded .= $character;
			}
			$stem = rtrim( $bounded, ' .' );
			$stem = '' !== $stem ? $stem : 'app';
			if ( preg_match( '/^(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/iu', $stem ) ) {
				$stem = '_' . $stem;
			}
			return $stem . '.apk';
		}

		/**
		 * Check whether the administrator configured a download card.
		 *
		 * @param array $settings Sanitized global app options.
		 * @return bool
		 */
		private static function available( $settings ) {
			return 'yes' === ( $settings['app_promo_enabled'] ?? 'no' ) && '' !== self::apk_url( $settings['app_android_apk_url'] ?? '' );
		}

		/**
		 * Resolve only public presentation from the selected settings owner.
		 *
		 * @param string $variant Customer or operator.
		 * @return array Sanitized banner settings; empty if unavailable.
		 */
		private static function banner_settings( $variant ) {
			if ( 'customer' === $variant ) {
				return WBTM_App_Settings::get();
			}
			if ( 'operator' !== $variant || ! class_exists( 'WBTM_Operator_Settings' ) ) {
				return array();
			}
			$operator = WBTM_Operator_Settings::get();
			if ( 'yes' !== $operator['enabled'] ) {
				return array();
			}
			return array(
				'app_name'               => $operator['name'],
				'app_logo'               => $operator['logo'],
				'app_primary_color'      => $operator['primary_color'],
				'app_android_apk_url'    => self::apk_url( $operator['android_apk_url'] ),
				'app_promo_enabled'      => $operator['promo_enabled'] ?? 'no',
				'app_promo_title'        => $operator['promo_title'] ?? '',
				'app_promo_description'  => $operator['promo_description'] ?? '',
				'app_promo_button_label' => $operator['promo_button_label'] ?? '',
			);
		}

		/**
		 * Find explicit placement of this variant without running page shortcodes.
		 *
		 * @param string $content Raw page content.
		 * @param string $variant Customer or operator.
		 * @return bool Whether the variant is explicitly placed.
		 */
		private static function has_placement( $content, $variant ) {
			preg_match_all( '/' . get_shortcode_regex( array( 'wbtm_app_download', 'wbtm_operator_app_download' ) ) . '/s', $content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				if ( '[' === $match[1] && ']' === $match[6] ) {
					continue;
				}
				$attributes = shortcode_parse_atts( $match[3] );
				$selected   = 'wbtm_operator_app_download' === $match[2] ? 'operator' : ( $attributes['app'] ?? 'customer' );
				if ( $selected === $variant ) {
					return true;
				}
			}
			$blocks = parse_blocks( $content );
			while ( $blocks ) {
				$block = array_pop( $blocks );
				if ( self::BLOCK_NAME === $block['blockName'] && ( $block['attrs']['appVariant'] ?? 'customer' ) === $variant ) {
					return true;
				}
				$blocks = array_merge( $blocks, $block['innerBlocks'] );
			}
			return false;
		}

		/**
		 * Detect core bus search shortcodes without rendering their forms.
		 *
		 * @param string $content Page content.
		 * @return bool
		 */
		private static function has_search( $content ) {
			return has_shortcode( $content, 'wbtm-bus-search' ) || has_shortcode( $content, 'wbtm-bus-search-form' );
		}

		/** Enqueue only on eligible public content, before the theme prints its head. */
		public function enqueue_assets() {
			if ( is_admin() || ! is_singular() || is_feed() ) {
				return;
			}
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
				return;
			}
			if ( ! ( self::available( self::banner_settings( 'customer' ) ) && ( self::has_search( $post->post_content ) || self::has_placement( $post->post_content, 'customer' ) ) )
				&& ! ( self::available( self::banner_settings( 'operator' ) ) && self::has_placement( $post->post_content, 'operator' ) ) ) {
				return;
			}
			$file = '/assets/frontend/wbtm-app-promotion.css';
			wp_enqueue_style( self::STYLE_HANDLE, WBTM_PLUGIN_URL . $file, array(), (string) filemtime( WBTM_PLUGIN_DIR . $file ) );
		}

		/**
		 * Leave search markup, forms and query handling intact. Explicit placement wins.
		 *
		 * @param string $content Formatted main post content.
		 * @return string
		 */
		public function prepend_to_search( $content ) {
			if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}
			$post = get_post();
			// Shortcodes are already rendered at this priority. Inspect the raw
			// saved content so explicit placement still suppresses auto insertion.
			if ( ! $post instanceof WP_Post || post_password_required( $post )
				|| ! self::has_search( $post->post_content ) || self::has_placement( $post->post_content, 'customer' ) ) {
				return $content;
			}
			return $this->shortcode() . $content;
		}

		/** Register a real page block so its text and design are editable in Gutenberg. */
		public function register_block() {
			$file = '/assets/admin/wbtm_app_promotion.js';
			wp_register_script( 'wbtm-app-promotion-editor', WBTM_PLUGIN_URL . $file, array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ), (string) filemtime( WBTM_PLUGIN_DIR . $file ), true );
			$file = '/assets/frontend/wbtm-app-promotion.css';
			wp_register_style( self::STYLE_HANDLE, WBTM_PLUGIN_URL . $file, array(), (string) filemtime( WBTM_PLUGIN_DIR . $file ) );
			register_block_type( WBTM_PLUGIN_DIR . '/blocks/app-download', array( 'render_callback' => array( $this, 'render_block' ) ) );
		}

		/**
		 * Share editable banner copy with Shortcode widgets, including Elementor.
		 * The app identity and APK destination always come from global settings.
		 *
		 * @param array|string $attributes Shortcode attributes supplied by WordPress.
		 * @return string Rendered download banner, once per request instance.
		 */
		public function shortcode( $attributes = array() ) {
			$variant = is_array( $attributes ) ? ( $attributes['app'] ?? 'customer' ) : 'customer';
			if ( ! in_array( $variant, array( 'customer', 'operator' ), true ) || is_admin() || is_feed() || ! empty( $this->rendered[ $variant ] ) ) {
				return '';
			}
			$fields     = array(
				'heading'          => 'heading',
				'highlight'        => 'highlight',
				'description'      => 'description',
				'badge'            => 'badge',
				'button_label'     => 'buttonLabel',
				'button_note'      => 'buttonNote',
				'feature_one'      => 'featureOne',
				'feature_two'      => 'featureTwo',
				'feature_three'    => 'featureThree',
				'trust_note'       => 'trustNote',
				'accent_color'     => 'accentColor',
				'background_color' => 'backgroundColor',
				'icon_style'       => 'iconStyle',
			);
			$attributes = shortcode_atts(
				array_fill_keys( array_keys( $fields ), null ),
				is_array( $attributes ) ? $attributes : array(),
				'wbtm_app_download'
			);
			$banner     = array(
				'iconStyle'  => 'logo',
				'appVariant' => $variant,
			);
			foreach ( $fields as $key => $field ) {
				if ( is_string( $attributes[ $key ] ) ) {
					$banner[ $field ] = $attributes[ $key ];
				}
			}
			$html = $this->render_block( $banner );
			if ( $html ) {
				$this->rendered[ $variant ] = true;
			}
			return $html;
		}

		/**
		 * Explicit Operator App alias; attributes cannot change the selected app.
		 *
		 * @param array|string $attributes Shortcode presentation attributes.
		 * @return string Rendered operator banner.
		 */
		public function operator_shortcode( $attributes = array() ) {
			$attributes        = is_array( $attributes ) ? $attributes : array();
			$attributes['app'] = 'operator';
			return $this->shortcode( $attributes );
		}

		/**
		 * Only page copy and cosmetic choices can override global app settings.
		 *
		 * @param array $attributes Block presentation attributes.
		 * @return string Rendered banner or empty when disabled.
		 */
		public function render_block( $attributes ) {
			$attributes = is_array( $attributes ) ? $attributes : array();
			$variant    = $attributes['appVariant'] ?? 'customer';
			$settings   = self::banner_settings( $variant );
			if ( is_feed() || ! self::available( $settings ) ) {
				return '';
			}
			$operator  = 'operator' === $variant;
			$text      = static function ( $key, $fallback = '' ) use ( $attributes ) {
				return isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ? sanitize_text_field( wp_html_excerpt( $attributes[ $key ], 1000, '' ) ) : $fallback;
			};
			$name      = self::app_name( $settings );
			$title     = $text( 'heading', $settings['app_promo_title'] ? $settings['app_promo_title'] : $name );
			$highlight = $text( 'highlight', ! $operator && empty( $settings['app_promo_title'] ) && ! isset( $attributes['heading'] ) ? __( 'in your pocket', 'bus-ticket-booking-with-seat-reservation' ) : '' );
			if ( '' === $title && '' === $highlight ) {
				$title = $settings['app_promo_title'] ? $settings['app_promo_title'] : $name;
			}
			$description = $text( 'description', ( '' !== $settings['app_promo_description'] ? $settings['app_promo_description'] : ( $operator ? __( 'Keep your assigned journeys, passenger manifests and attendance together — ready for your next departure.', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Search journeys, compare fares, choose your tickets and keep your bookings together — all from your phone.', 'bus-ticket-booking-with-seat-reservation' ) ) ) );
			$label       = $text( 'buttonLabel', $settings['app_promo_button_label'] ? $settings['app_promo_button_label'] : __( 'Download for Android', 'bus-ticket-booking-with-seat-reservation' ) );
			if ( '' === $label ) {
				$label = $settings['app_promo_button_label'] ? $settings['app_promo_button_label'] : __( 'Download for Android', 'bus-ticket-booking-with-seat-reservation' );
			}
			$badge       = $text( 'badge', $operator ? __( 'Operator Android app', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Android app', 'bus-ticket-booking-with-seat-reservation' ) );
			$kicker      = $text( 'buttonNote', __( 'Direct APK download', 'bus-ticket-booking-with-seat-reservation' ) );
			$note        = $text( 'trustNote', $operator ? __( 'Approved operator account required', 'bus-ticket-booking-with-seat-reservation' ) : __( 'For Android phones', 'bus-ticket-booking-with-seat-reservation' ) );
			$features    = array_filter(
				array(
					$text( 'featureOne', $operator ? __( 'Assigned journeys', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Search journeys', 'bus-ticket-booking-with-seat-reservation' ) ),
					$text( 'featureTwo', $operator ? __( 'Passenger manifests', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Choose tickets', 'bus-ticket-booking-with-seat-reservation' ) ),
					$text( 'featureThree', $operator ? __( 'Attendance status', 'bus-ticket-booking-with-seat-reservation' ) : __( 'View bookings', 'bus-ticket-booking-with-seat-reservation' ) ),
				)
			);
			$page_accent = ! $operator ? sanitize_hex_color( $text( 'accentColor' ) ) : '';
			$accent      = $page_accent ? $page_accent : $settings['app_primary_color'];
			$background  = sanitize_hex_color( $text( 'backgroundColor' ) );
			$background  = $background ? $background : '#f7f5ff';
			$logo        = 'logo' === ( $attributes['iconStyle'] ?? 'bus' ) ? ( $settings['app_logo'] ? $settings['app_logo'] : ( $operator ? '' : wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) ) ) : '';
			$heading_id  = wp_unique_id( 'wbtm-app-download-' );
			$style       = '--wbtm-app-accent:' . $accent . ';--wbtm-app-on-accent:' . self::contrast_color( $accent ) . ';--wbtm-app-soft:' . $background . ';';
			wp_enqueue_style( self::STYLE_HANDLE );
			ob_start();
			?>
			<section data-app-variant="<?php echo esc_attr( $variant ); ?>" class="wbtm-app-promotion <?php echo 'full' === ( $attributes['align'] ?? '' ) ? 'alignfull' : 'alignwide'; ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" style="<?php echo esc_attr( $style ); ?>">
				<div class="wbtm-app-promotion__inner">
					<div class="wbtm-app-promotion__icon" aria-hidden="true">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
					<?php elseif ( $operator ) : ?>
						<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M11 4H5a1 1 0 0 0-1 1v6m17-7h6a1 1 0 0 1 1 1v6M4 21v6a1 1 0 0 0 1 1h6m10 0h6a1 1 0 0 0 1-1v-6M3 16h26M9 10h4v3H9zm10 0h4v3h-4zM9 20h4v3H9zm10 0h4v3h-4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php else : ?>
						<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M4 7h17a7 7 0 0 1 7 7v10H4V7Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M4 16h24M11 7v9m8-9v9M5 20h3m15 0h4" stroke="currentColor" stroke-width="2.4"/><circle cx="10" cy="24" r="3" fill="white" stroke="currentColor" stroke-width="2.4"/><circle cx="23" cy="24" r="3" fill="white" stroke="currentColor" stroke-width="2.4"/></svg>
					<?php endif; ?>
					</div>
					<div class="wbtm-app-promotion__content">
						<?php if ( $badge ) : ?><p class="wbtm-app-promotion__eyebrow"><span class="wbtm-app-promotion__dot" aria-hidden="true"></span><svg viewBox="0 0 16 20" fill="none" aria-hidden="true"><rect x="3" y="4" width="10" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m5 2 1 2m5-2-1 2M1 7v6m14-6v6M6 16v3m4-3v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><?php echo esc_html( $badge ); ?></p><?php endif; ?>
						<h2 id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $title ); ?><?php if ( $highlight ) : ?> <span><?php echo esc_html( $highlight ); ?></span><?php endif; ?></h2>
						<?php if ( $description ) : ?><p class="wbtm-app-promotion__description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
						<?php if ( $features ) : ?><ul class="wbtm-app-promotion__features"><?php foreach ( $features as $index => $feature ) : ?><li><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><?php if ( 0 === $index ) : ?><circle cx="6" cy="15" r="2" stroke="currentColor" stroke-width="1.5"/><circle cx="14" cy="5" r="2" stroke="currentColor" stroke-width="1.5"/><path d="M6 13V7h8" stroke="currentColor" stroke-width="1.5"/><?php elseif ( 1 === $index ) : ?><path d="M5 4v8a3 3 0 0 0 3 3h7M8 4h5a2 2 0 0 1 2 2v5H8V4Zm-3 9-2 4m11-2 2 2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><?php else : ?><path d="M3 5h14v4a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4V5Zm7 0v11" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 1"/><?php endif; ?></svg><?php echo esc_html( $feature ); ?></li><?php endforeach; ?></ul><?php endif; ?>
					</div>
					<div class="wbtm-app-promotion__action">
						<a class="wbtm-app-promotion__download" href="<?php echo esc_url( $settings['app_android_apk_url'], array( 'https' ) ); ?>" download="<?php echo esc_attr( self::apk_filename( $name ) ); ?>" rel="noopener" aria-label="<?php echo esc_attr( $label . ' · ' . $name ); ?>"><span class="wbtm-app-promotion__download-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 16v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><?php if ( $kicker ) : ?><small><?php echo esc_html( $kicker ); ?></small><?php endif; ?><strong><?php echo esc_html( $label ); ?></strong></span></a>
						<?php if ( $note ) : ?><p class="wbtm-app-promotion__note"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 3v4c0 4-6 7-6 7s-6-3-6-7V5l6-3Z" stroke="currentColor" stroke-width="1.4"/><path d="m7 9 2 2 4-4" stroke="currentColor" stroke-width="1.4"/></svg><?php echo esc_html( $note ); ?></p><?php endif; ?>
					</div>
				</div>
			</section>
			<?php
			return ob_get_clean();
		}

		/**
		 * Choose the higher WCAG contrast ratio for any valid administrator color.
		 *
		 * @param string $hex Sanitized hexadecimal brand color.
		 * @return string Black or white text color.
		 */
		private static function contrast_color( $hex ) {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$luminance = 0;
			foreach ( array( 0.2126, 0.7152, 0.0722 ) as $index => $weight ) {
				$channel    = hexdec( substr( $hex, $index * 2, 2 ) ) / 255;
				$luminance += ( $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 ) ) * $weight;
			}
			return ( $luminance + 0.05 ) / 0.05 >= 1.05 / ( $luminance + 0.05 ) ? '#000000' : '#ffffff';
		}
	}
	new WBTM_App_Promotion();
}
