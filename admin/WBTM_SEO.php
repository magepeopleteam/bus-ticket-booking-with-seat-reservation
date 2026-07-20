<?php
	/**
	 * SEO plugin adapter and modern bus-editor SEO panel.
	 *
	 * Rank Math, Yoast SEO and All in One SEO use different storage. This class
	 * keeps the bus UI provider-neutral while leaving each SEO plugin as the
	 * source of truth for its own metadata and analysis score.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}
	if ( ! class_exists( 'WBTM_SEO' ) ) {
		class WBTM_SEO {
			public function __construct() {
				add_action( 'save_post_wbtm_bus', array( $this, 'save' ), 30, 2 );
			}

			/**
			 * Return the active supported SEO provider.
			 *
			 * @return array|null Provider key/label or null when none is active.
			 */
			public static function provider() {
				static $provider = false;
				if ( $provider !== false ) {
					return $provider;
				}
				if ( defined( 'WPSEO_VERSION' ) ) {
					$provider = array( 'key' => 'yoast', 'label' => 'Yoast SEO' );
				} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
					$provider = array( 'key' => 'aioseo', 'label' => 'All in One SEO' );
				} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
					$provider = array( 'key' => 'rankmath', 'label' => 'Rank Math' );
				} else {
					$provider = null;
				}

				return apply_filters( 'wbtm_seo_provider', $provider );
			}

			/** Fetch AIOSEO rows in one query for the fleet list. */
			public static function aioseo_scores( array $ids ): array {
				global $wpdb;
				$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
				if ( empty( $ids ) ) {
					return array();
				}
				$table = $wpdb->prefix . 'aioseo_posts';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
					return array();
				}
				$in = implode( ',', $ids );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( "SELECT post_id, seo_score, title, description, keyphrases, canonical_url FROM {$table} WHERE post_id IN ({$in})", ARRAY_A );
				$map  = array();
				foreach ( (array) $rows as $row ) {
					$map[ (int) $row['post_id'] ] = $row;
				}

				return $map;
			}

			/** Return normalized SEO data for one post. */
			public static function get_data( $post_id, ?array $provider = null, array $aioseo_map = array() ): array {
				$provider = $provider ?: self::provider();
				$data     = array(
					'analyzed'   => false,
					'score'      => null,
					'rating'     => 'na',
					'label'      => esc_html__( 'Not analyzed', 'bus-ticket-booking-with-seat-reservation' ),
					'keyword'    => '',
					'title'      => '',
					'description' => '',
					'canonical'  => '',
					'has_desc'   => false,
					'has_title'  => false,
					'provider'   => $provider['label'] ?? '',
					'provider_key' => $provider['key'] ?? '',
				);
				if ( ! $provider ) {
					return $data;
				}
				$raw = '';

				switch ( $provider['key'] ) {
					case 'yoast':
						$raw                 = get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );
						$data['keyword']     = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
						$data['title']       = (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
						$data['description'] = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
						$data['canonical']   = (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
						break;

					case 'aioseo':
						$row = $aioseo_map[ $post_id ] ?? self::aioseo_row( $post_id );
						$raw = $row['seo_score'] ?? '';
						if ( $row ) {
							$data['title']       = (string) ( $row['title'] ?? '' );
							$data['description'] = (string) ( $row['description'] ?? '' );
							$data['canonical']   = (string) ( $row['canonical_url'] ?? '' );
							$keyphrases          = json_decode( (string) ( $row['keyphrases'] ?? '' ), true );
							if ( is_array( $keyphrases ) && ! empty( $keyphrases['focus']['keyphrase'] ) ) {
								$data['keyword'] = (string) $keyphrases['focus']['keyphrase'];
							}
						}
						break;

					case 'rankmath':
						$raw                 = get_post_meta( $post_id, 'rank_math_seo_score', true );
						$keywords            = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
						$data['keyword']     = trim( explode( ',', $keywords )[0] ?? '' );
						$data['title']       = (string) get_post_meta( $post_id, 'rank_math_title', true );
						$data['description'] = (string) get_post_meta( $post_id, 'rank_math_description', true );
						$data['canonical']   = (string) get_post_meta( $post_id, 'rank_math_canonical_url', true );
						break;

					default:
						return $data;
				}

				$data['has_desc']  = $data['description'] !== '';
				$data['has_title'] = $data['title'] !== '';
				if ( $raw !== '' && $raw !== false && $raw !== null ) {
					$data['analyzed'] = true;
					$data['score']    = (int) $raw;
					$rating           = self::rating( (int) $raw, $provider['key'] );
					$data['rating']   = $rating['rating'];
					$data['label']    = $rating['label'];
				}

				return apply_filters( 'wbtm_seo_data', $data, $post_id, $provider );
			}

			/** Provider-specific score bands. */
			public static function rating( int $score, string $key ): array {
				$good = esc_html__( 'Good', 'bus-ticket-booking-with-seat-reservation' );
				$ok   = esc_html__( 'Needs work', 'bus-ticket-booking-with-seat-reservation' );
				$bad  = esc_html__( 'Poor', 'bus-ticket-booking-with-seat-reservation' );
				$na   = esc_html__( 'Not analyzed', 'bus-ticket-booking-with-seat-reservation' );
				if ( $key === 'aioseo' ) {
					if ( $score >= 80 ) { return array( 'rating' => 'good', 'label' => $good ); }
					if ( $score >= 50 ) { return array( 'rating' => 'ok', 'label' => $ok ); }
				} elseif ( $key === 'rankmath' ) {
					if ( $score > 80 ) { return array( 'rating' => 'good', 'label' => $good ); }
					if ( $score > 50 ) { return array( 'rating' => 'ok', 'label' => $ok ); }
				} else {
					if ( $score > 70 ) { return array( 'rating' => 'good', 'label' => $good ); }
					if ( $score > 40 ) { return array( 'rating' => 'ok', 'label' => $ok ); }
				}
				if ( $score > 0 ) {
					return array( 'rating' => 'bad', 'label' => $bad );
				}

				return array( 'rating' => 'na', 'label' => $na );
			}

			/** Write normalized SEO data to the active plugin's native storage. */
			public static function update_data( $post_id, array $values, ?array $provider = null ) {
				$provider = $provider ?: self::provider();
				if ( ! $provider ) {
					return new WP_Error( 'wbtm_no_seo_provider', __( 'No supported SEO plugin is active.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				$keyword     = sanitize_text_field( $values['keyword'] ?? '' );
				$title       = sanitize_text_field( $values['title'] ?? '' );
				$description = sanitize_textarea_field( $values['description'] ?? '' );
				$canonical   = esc_url_raw( $values['canonical'] ?? '' );

				switch ( $provider['key'] ) {
					case 'yoast':
						update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
						update_post_meta( $post_id, '_yoast_wpseo_title', $title );
						update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
						update_post_meta( $post_id, '_yoast_wpseo_canonical', $canonical );
						break;

					case 'aioseo':
						$result = self::update_aioseo( $post_id, $keyword, $title, $description, $canonical );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						break;

					case 'rankmath':
						update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
						update_post_meta( $post_id, 'rank_math_title', $title );
						update_post_meta( $post_id, 'rank_math_description', $description );
						update_post_meta( $post_id, 'rank_math_canonical_url', $canonical );
						break;

					default:
						return new WP_Error( 'wbtm_unsupported_seo_provider', __( 'The active SEO plugin is not supported.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				clean_post_cache( $post_id );
				do_action( 'wbtm_seo_data_updated', $post_id, $provider, compact( 'keyword', 'title', 'description', 'canonical' ) );

				return true;
			}

			/** Save manually edited modern-editor fields. */
			public function save( $post_id, $post ) {
				if ( ! isset( $_POST['wbtm_seo_present'], $_POST['wbtm_type_nonce'] ) ) {
					return;
				}
				$nonce = sanitize_text_field( wp_unslash( $_POST['wbtm_type_nonce'] ) );
				if ( ! wp_verify_nonce( $nonce, 'wbtm_type_nonce' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
					return;
				}
				if ( ! $post || $post->post_type !== 'wbtm_bus' || ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
				self::update_data( $post_id, array(
					'keyword'     => isset( $_POST['wbtm_seo_focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_seo_focus_keyword'] ) ) : '',
					'title'       => isset( $_POST['wbtm_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_seo_title'] ) ) : '',
					'description' => isset( $_POST['wbtm_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wbtm_seo_description'] ) ) : '',
					'canonical'   => isset( $_POST['wbtm_seo_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['wbtm_seo_canonical'] ) ) : '',
				) );
			}

			/** Render the dedicated SEO step in the modern editor. */
			public function tab_content( $post_id ) {
				$provider = self::provider();
				?>
				<div class="tabsItem wbtm-seo-editor" data-tabs="#wbtm_settings_seo">
					<?php if ( ! $provider ) : ?>
						<div class="wbtm-seo-editor__empty">
							<span class="dashicons dashicons-search"></span>
							<h3><?php esc_html_e( 'Connect an SEO plugin', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
							<p><?php esc_html_e( 'Install and activate Rank Math, Yoast SEO, or All in One SEO. This panel will then edit that plugin’s native SEO fields.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						</div>
					<?php else :
						$data   = self::get_data( $post_id, $provider );
						$audit  = self::audit( $post_id, $data );
						$ai     = apply_filters( 'wbtm_seo_ai_status', array( 'available' => false, 'enabled' => false ), $post_id, $provider );
						$score  = $data['score'] === null ? '–' : (int) $data['score'];
						?>
						<input type="hidden" name="wbtm_seo_present" value="1"/>
						<div class="wbtm-seo-editor__summary">
							<div class="wbtm-seo-editor__score wbtm-seo-editor__score--<?php echo esc_attr( $data['rating'] ); ?>">
								<strong><?php echo esc_html( $score ); ?></strong><span>/100</span>
							</div>
							<div>
								<span class="wbtm-seo-editor__provider"><?php echo esc_html( $provider['label'] ); ?></span>
								<h3><?php echo esc_html( $data['label'] ); ?></h3>
								<p><?php esc_html_e( 'The SEO plugin calculates the official score. Save the bus after editing so its analyzer can refresh.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							</div>
							<?php if ( ! empty( $ai['available'] ) ) : ?>
								<button type="button" class="wbtm-bme__btn wbtm-bme__btn--primary wbtm-seo-ai-button" data-wbtm-seo-ai data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wbtm_ai_seo_' . $post_id ) ); ?>" <?php disabled( empty( $ai['enabled'] ) ); ?>>
									<span class="dashicons dashicons-superhero-alt"></span>
									<span data-wbtm-seo-ai-label><?php esc_html_e( 'AI Auto-Fix SEO', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</button>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $ai['available'] ) && empty( $ai['enabled'] ) ) : ?>
							<div class="wbtm-seo-editor__ai-note"><?php echo wp_kses_post( $ai['message'] ?? '' ); ?></div>
						<?php elseif ( ! empty( $ai['available'] ) ) : ?>
							<div class="wbtm-seo-editor__ai-note wbtm-seo-editor__ai-note--privacy"><?php esc_html_e( 'AI generation sends this bus name, description, and route stops to your selected AI provider. API credentials remain server-side.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
						<?php endif; ?>
						<div class="wbtm-seo-editor__grid">
							<div class="wbtm-seo-editor__fields">
								<label for="wbtm-seo-focus-keyword"><?php esc_html_e( 'Focus keyphrase', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" id="wbtm-seo-focus-keyword" name="wbtm_seo_focus_keyword" class="formControl" value="<?php echo esc_attr( $data['keyword'] ); ?>"/>

								<label for="wbtm-seo-title"><?php esc_html_e( 'SEO title', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="text" id="wbtm-seo-title" name="wbtm_seo_title" class="formControl" maxlength="70" value="<?php echo esc_attr( $data['title'] ); ?>"/>
								<span class="wbtm-seo-editor__counter" data-seo-counter="wbtm-seo-title" data-good-min="30" data-good-max="60"><?php echo esc_html( mb_strlen( $data['title'] ) ); ?>/60</span>

								<label for="wbtm-seo-description"><?php esc_html_e( 'Meta description', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<textarea id="wbtm-seo-description" name="wbtm_seo_description" class="formControl" rows="4" maxlength="170"><?php echo esc_textarea( $data['description'] ); ?></textarea>
								<span class="wbtm-seo-editor__counter" data-seo-counter="wbtm-seo-description" data-good-min="120" data-good-max="160"><?php echo esc_html( mb_strlen( $data['description'] ) ); ?>/160</span>

								<label for="wbtm-seo-canonical"><?php esc_html_e( 'Canonical URL', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<input type="url" id="wbtm-seo-canonical" name="wbtm_seo_canonical" class="formControl" value="<?php echo esc_attr( $data['canonical'] ); ?>" placeholder="<?php echo esc_attr( get_permalink( $post_id ) ); ?>"/>
								<p class="description"><?php esc_html_e( 'Leave empty to use this bus URL. Only set this when another URL is the preferred original.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							</div>
							<div class="wbtm-seo-editor__audit" data-wbtm-seo-audit>
								<h4><?php esc_html_e( 'SEO readiness', 'bus-ticket-booking-with-seat-reservation' ); ?> <span><?php echo esc_html( $audit['score'] ); ?>%</span></h4>
								<?php foreach ( $audit['checks'] as $check ) : ?>
									<div class="wbtm-seo-editor__check <?php echo $check['pass'] ? 'is-pass' : 'is-fail'; ?>">
										<span class="dashicons <?php echo $check['pass'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
										<span><?php echo esc_html( $check['label'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="wbtm-seo-editor__result" data-wbtm-seo-ai-result hidden></div>
					<?php endif; ?>
				</div>
				<?php
			}

			private static function aioseo_row( $post_id ): array {
				$rows = self::aioseo_scores( array( $post_id ) );

				return $rows[ $post_id ] ?? array();
			}

			private static function update_aioseo( $post_id, $keyword, $title, $description, $canonical ) {
				global $wpdb;
				$table = $wpdb->prefix . 'aioseo_posts';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
					return new WP_Error( 'wbtm_aioseo_table_missing', __( 'The All in One SEO data table is unavailable.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				$keyphrases = wp_json_encode( array(
					'focus'      => array( 'keyphrase' => $keyword, 'score' => 0 ),
					'additional' => array(),
				) );
				$values = array(
					'title'         => $title,
					'description'   => $description,
					'keyphrases'    => $keyphrases,
					'canonical_url' => $canonical,
					'post_type'     => get_post_type( $post_id ),
					'updated'       => current_time( 'mysql' ),
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d", $post_id ) );
				if ( $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$ok = $wpdb->update( $table, $values, array( 'post_id' => $post_id ) );
				} else {
					$values['post_id'] = $post_id;
					$values['created'] = current_time( 'mysql' );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$ok = $wpdb->insert( $table, $values );
				}
				if ( $ok === false ) {
					return new WP_Error( 'wbtm_aioseo_update_failed', __( 'All in One SEO could not save the generated fields.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				return true;
			}

			private static function audit( $post_id, array $data ): array {
				$post        = get_post( $post_id );
				$keyword     = trim( $data['keyword'] );
				$title       = trim( $data['title'] );
				$description = trim( $data['description'] );
				$content     = $post ? wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) : '';
				$word_count  = count( preg_split( '/\s+/u', trim( $content ), -1, PREG_SPLIT_NO_EMPTY ) );
				$contains    = static function( $haystack, $needle ) {
					return $needle !== '' && mb_stripos( (string) $haystack, $needle ) !== false;
				};
				$checks = array(
					array( 'pass' => $keyword !== '', 'label' => __( 'A focus keyphrase is set', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => mb_strlen( $title ) >= 30 && mb_strlen( $title ) <= 60, 'label' => __( 'SEO title is 30–60 characters', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => mb_strlen( $description ) >= 120 && mb_strlen( $description ) <= 160, 'label' => __( 'Meta description is 120–160 characters', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => $contains( $title, $keyword ), 'label' => __( 'Keyphrase appears in the SEO title', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => $contains( $description, $keyword ), 'label' => __( 'Keyphrase appears in the description', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => has_post_thumbnail( $post_id ), 'label' => __( 'A featured image is set', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( 'pass' => $word_count >= 200, 'label' => __( 'Bus description contains at least 200 words', 'bus-ticket-booking-with-seat-reservation' ) ),
				);
				$passed = count( array_filter( $checks, static fn( $check ) => $check['pass'] ) );

				return array( 'score' => (int) round( 100 * $passed / count( $checks ) ), 'checks' => $checks );
			}
		}

		new WBTM_SEO();
	}
