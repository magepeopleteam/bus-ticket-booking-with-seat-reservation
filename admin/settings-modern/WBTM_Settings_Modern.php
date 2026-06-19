<?php
	/*
	 * Modern bus add/edit editor (parallel to the classic tabbed metabox).
	 *
	 * Design: a 4-step wizard (General Info, Seat Configure, Pricing & Route, Advanced)
	 * matching the approved mockup. This class ONLY renders a modern shell and reuses the
	 * EXISTING classic section render methods for the body, so every field name, JS hook,
	 * AJAX endpoint and the shared save handler (WBTM_Settings::save_settings) keep working
	 * unchanged. Switching is per-user (user meta); the classic path is never modified.
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}
	if ( ! class_exists( 'WBTM_Settings_Modern' ) ) {
		class WBTM_Settings_Modern {
			/** User-meta key holding each admin's preferred editor: 'classic' (default) | 'modern'. */
			const USER_META = 'wbtm_bus_edit_ui';

			/** Cache of reflection-built section renderers (no constructor side effects). */
			private $section_cache = array();

			public function __construct() {
				add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ), 99 );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 90 );
				add_filter( 'admin_body_class', array( $this, 'body_class' ) );
				add_action( 'wp_ajax_wbtm_set_bus_edit_ui', array( $this, 'ajax_set_ui' ) );
				add_action( 'save_post', array( $this, 'save_feature_image' ), 20 );
			}

			/* ------------------------------------------------------------------ *
			 *  Preference helpers
			 * ------------------------------------------------------------------ */

			/** Current user's editor preference. Defaults to classic so nothing changes until opt-in. */
			public static function current_ui() {
				$ui = get_user_meta( get_current_user_id(), self::USER_META, true );
				return $ui === 'modern' ? 'modern' : 'classic';
			}

			private function is_modern() {
				return self::current_ui() === 'modern';
			}

			/** True only on the add/edit screen of the bus CPT. */
			private function is_bus_edit_screen() {
				if ( ! function_exists( 'get_current_screen' ) ) {
					return false;
				}
				$screen = get_current_screen();
				return $screen && $screen->base === 'post' && $screen->post_type === WBTM_Functions::get_cpt();
			}

			/* ------------------------------------------------------------------ *
			 *  Metaboxes
			 * ------------------------------------------------------------------ */

			public function register_meta_boxes() {
				$cpt = WBTM_Functions::get_cpt();

				// Editor-style switcher — always available so users can flip either direction.
				add_meta_box(
					'wbtm_ui_switcher',
					esc_html__( 'Editor Style', 'bus-ticket-booking-with-seat-reservation' ),
					array( $this, 'render_switcher' ),
					$cpt,
					'side',
					'high'
				);

				if ( $this->is_modern() ) {
					// Replace the classic panel with the modern shell (classic file untouched).
					remove_meta_box( 'wbtm_meta_box_panel', $cpt, 'normal' );
					add_meta_box(
						'wbtm_modern_meta_box_panel',
						esc_html__( 'Bus Settings', 'bus-ticket-booking-with-seat-reservation' ),
						array( $this, 'render_modern' ),
						$cpt,
						'normal',
						'high'
					);
				}
			}

			/* ------------------------------------------------------------------ *
			 *  Step registry
			 * ------------------------------------------------------------------ */

			/**
			 * The 4 wizard steps. Each section reuses a classic renderer:
			 * [ class, method, title, subtitle ].
			 */
			private function get_steps() {
				return array(
					array(
						'id'       => 'general',
						'label'    => __( 'General Info', 'bus-ticket-booking-with-seat-reservation' ),
						'sections' => array(
							array( 'WBTM_Settings_General', 'tab_content', __( 'General Settings', 'bus-ticket-booking-with-seat-reservation' ), __( 'Bus general settings — number, category, logo and seat reservation.', 'bus-ticket-booking-with-seat-reservation' ) ),
						),
					),
					array(
						'id'       => 'seat',
						'label'    => __( 'Seat Configure', 'bus-ticket-booking-with-seat-reservation' ),
						'sections' => array(
							array( 'WBTM_Seat_Configuration', 'tab_content', __( 'Seat Configuration', 'bus-ticket-booking-with-seat-reservation' ), __( 'Configure seats for the bus or train, with support for multiple cabins, coaches and decks.', 'bus-ticket-booking-with-seat-reservation' ) ),
						),
					),
					array(
						'id'       => 'pricing',
						'label'    => __( 'Pricing & Route', 'bus-ticket-booking-with-seat-reservation' ),
						'sections' => array(
							array( 'WBTM_Pricing_Routing', 'tab_content', __( 'Price & Routing', 'bus-ticket-booking-with-seat-reservation' ), __( 'Configure boarding/dropping stops, return journey and ticket pricing.', 'bus-ticket-booking-with-seat-reservation' ) ),
						),
					),
					array(
						'id'       => 'advanced',
						'label'    => __( 'Advanced', 'bus-ticket-booking-with-seat-reservation' ),
						'sections' => array(
							array( 'WBTM_Extra_Service', 'tab_content', WBTM_Translations::text_ex_service(), __( 'Optional paid add-ons passengers can choose during booking.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WBTM_Settings_Pickup_Point', 'tab_content', __( 'Pickup / Drop-Off Point', 'bus-ticket-booking-with-seat-reservation' ), __( 'Define boarding pickup and drop-off points with times.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WBTM_Date_Settings', 'tab_content', __( 'Date Settings', 'bus-ticket-booking-with-seat-reservation' ), __( 'Operating dates, repeats and off-day schedules.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WBTM_Tax_Settings', 'tab_content', __( 'Tax Configure', 'bus-ticket-booking-with-seat-reservation' ), __( 'WooCommerce tax status and class for this bus.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WBTM_Gallery_Image_Settings', 'add_tabs_content', __( 'Gallery Image', 'bus-ticket-booking-with-seat-reservation' ), __( 'Images shown on the bus details and listings.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WTBM_Term_Condition_Add_Bus', 'term_tab_content', __( 'Term & Condition', 'bus-ticket-booking-with-seat-reservation' ), __( 'Terms shown to passengers for this bus.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'WTBM_Features_Seating', 'term_tab_content', __( 'Bus Feature', 'bus-ticket-booking-with-seat-reservation' ), __( 'Highlight amenities and features of this bus.', 'bus-ticket-booking-with-seat-reservation' ) ),
						),
					),
				);
			}

			/**
			 * Build (once) a renderer instance WITHOUT invoking the constructor, so the
			 * classic add_action hooks are not registered twice. The original singletons
			 * (created in their own files) keep all their AJAX / sub-action hooks active.
			 */
			private function section_instance( $class ) {
				if ( ! array_key_exists( $class, $this->section_cache ) ) {
					$this->section_cache[ $class ] = null;
					if ( class_exists( $class ) ) {
						try {
							$ref = new ReflectionClass( $class );
							$this->section_cache[ $class ] = $ref->newInstanceWithoutConstructor();
						} catch ( \ReflectionException $e ) {
							$this->section_cache[ $class ] = null;
						}
					}
				}
				return $this->section_cache[ $class ];
			}

			/* ------------------------------------------------------------------ *
			 *  Modern shell
			 * ------------------------------------------------------------------ */

			public function render_modern( $post ) {
				$post_id    = (int) $post->ID;
				$steps      = $this->get_steps();
				$total      = count( $steps );
				$bus_title  = get_the_title( $post_id );
				$bus_title  = $bus_title !== '' ? $bus_title : WBTM_Functions::get_name();
				$list_url   = admin_url( 'edit.php?post_type=' . WBTM_Functions::get_cpt() );

				// Shared plumbing — MUST match the classic save handler.
				wp_nonce_field( 'wbtm_type_nonce', 'wbtm_type_nonce' );
				?>
				<input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr( $post_id ); ?>"/>
<?php // The wbtm_style class keeps classic JS (collapse, validation, datepicker, lazy images) working for the reused sections. ?>
				<div class="wbtm-bme wbtm_style" id="wbtm-bme" data-total="<?php echo esc_attr( $total ); ?>" data-step="general">

					<header class="wbtm-bme__topbar">
						<a class="wbtm-bme__back" href="<?php echo esc_url( $list_url ); ?>">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
							<?php echo esc_html( sprintf( __( 'Back to %s', 'bus-ticket-booking-with-seat-reservation' ), WBTM_Functions::get_name() ) ); ?>
						</a>
						<input type="text" class="wbtm-bme__ttl wbtm-bme__ttl-input" id="wbtm-bme-title" value="<?php echo esc_attr( $bus_title ); ?>" placeholder="<?php esc_attr_e( 'Bus name', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Bus name', 'bus-ticket-booking-with-seat-reservation' ); ?>"/>
						<div class="wbtm-bme__acts">
							<button type="button" class="wbtm-bme__btn" data-bme-ui="classic"><?php esc_html_e( 'Classic editor', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<button type="button" class="wbtm-bme__btn wbtm-bme__btn--primary" data-bme-save><?php esc_html_e( 'Update', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						</div>
					</header>

					<div class="wbtm-bme__wrap">

						<div class="wbtm-bme__stepper">
							<?php foreach ( $steps as $i => $step ) : ?>
								<?php if ( $i > 0 ) : ?>
									<div class="wbtm-bme__conn" data-bme-conn="<?php echo esc_attr( $i ); ?>"></div>
								<?php endif; ?>
								<div class="wbtm-bme__step<?php echo $i === 0 ? ' active' : ''; ?>" data-bme-go="<?php echo esc_attr( $step['id'] ); ?>" data-bme-index="<?php echo esc_attr( $i ); ?>">
									<div class="wbtm-bme__num"><?php echo esc_html( $i + 1 ); ?></div>
									<div class="wbtm-bme__lab"><?php echo esc_html( $step['label'] ); ?></div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="wbtm-bme__body">
							<div class="wbtm-bme__main">
								<?php foreach ( $steps as $i => $step ) : ?>
									<section class="wbtm-bme__panel<?php echo $i === 0 ? ' active' : ''; ?>" data-bme-panel="<?php echo esc_attr( $step['id'] ); ?>">
										<?php
										foreach ( $step['sections'] as $section ) {
											$this->render_section_card( $section, $post_id );
										}
										?>
									</section>
								<?php endforeach; ?>
							</div>
							<?php $this->render_preview_rail( $post_id ); ?>
						</div>

					</div>

					<div class="wbtm-bme__navbar">
						<div class="wbtm-bme__navinner">
							<button type="button" class="wbtm-bme__btn wbtm-bme__nav-back" data-bme-prev disabled><?php esc_html_e( 'Back', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							<div class="wbtm-bme__stepof" data-bme-stepof><?php echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'bus-ticket-booking-with-seat-reservation' ), 1, $total ) ); ?></div>
							<button type="button" class="wbtm-bme__btn wbtm-bme__btn--primary" data-bme-next><?php esc_html_e( 'Next Step', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						</div>
					</div>

					<div class="wbtm-bme__toast" data-bme-toast>
						<span class="dashicons dashicons-yes-alt"></span>
						<span data-bme-toast-msg><?php esc_html_e( 'Saved', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
					</div>
				</div>
				<?php
			}

			/**
			 * Sticky right-rail preview: bus image, key info, gallery and features.
			 * A live snapshot of the bus the admin is editing (data from post meta).
			 */
			private function render_preview_rail( $post_id ) {
				$bus_name = get_the_title( $post_id );
				$bus_no   = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_no' );
				$coach    = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_category' );
				$seats    = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );

				$logo_id  = (int) get_post_meta( $post_id, 'wbtm_bus_logo', true );
				$gallery  = get_post_meta( $post_id, 'wbtm_gallery_images', true );
				$gallery  = is_array( $gallery ) ? array_values( array_filter( array_map( 'intval', $gallery ) ) ) : array();
				$feat_ids = get_post_meta( $post_id, 'wbbm_bus_features_term_id', true );
				$feat_ids = is_array( $feat_ids ) ? array_values( array_filter( array_map( 'intval', $feat_ids ) ) ) : array();

				$thumb_id = (int) get_post_thumbnail_id( $post_id );
				$hero     = '';
				if ( $thumb_id ) {
					$hero = wp_get_attachment_image_url( $thumb_id, 'medium' );
				} elseif ( $logo_id ) {
					$hero = wp_get_attachment_image_url( $logo_id, 'medium' );
				} elseif ( ! empty( $gallery ) ) {
					$hero = wp_get_attachment_image_url( $gallery[0], 'medium' );
				}
				?>
				<aside class="wbtm-bme__rail">
					<div class="wbtm-bme__rail-card">
						<div class="wbtm-bme__rail-hero">
							<img class="wbtm-bme__rail-hero-img" id="wbtm-bme-hero-img" src="<?php echo esc_url( $hero ); ?>" alt="" style="<?php echo $hero ? '' : 'display:none'; ?>"/>
							<span class="dashicons dashicons-bus wbtm-bme__rail-hero-ph" style="<?php echo $hero ? 'display:none' : ''; ?>"></span>
							<?php if ( $coach ) : ?>
								<span class="wbtm-bme__rail-badge"><?php echo esc_html( $coach ); ?></span>
							<?php endif; ?>
							<div class="wbtm-bme__rail-hero-acts">
								<button type="button" class="wbtm-bme__hero-btn" data-bme-feat-set><span class="dashicons dashicons-camera"></span><?php echo esc_html( $thumb_id ? __( 'Change', 'bus-ticket-booking-with-seat-reservation' ) : __( 'Set image', 'bus-ticket-booking-with-seat-reservation' ) ); ?></button>
								<button type="button" class="wbtm-bme__hero-btn wbtm-bme__hero-btn--rm" data-bme-feat-remove style="<?php echo $thumb_id ? '' : 'display:none'; ?>"><?php esc_html_e( 'Remove', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
							</div>
							<input type="hidden" id="wbtm-bme-thumbnail" name="wbtm_bme_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>"/>
						</div>
						<div class="wbtm-bme__rail-info">
							<h3 class="wbtm-bme__rail-name" id="wbtm-bme-rail-name"><?php echo esc_html( $bus_name !== '' ? $bus_name : __( 'Untitled bus', 'bus-ticket-booking-with-seat-reservation' ) ); ?></h3>
							<div class="wbtm-bme__rail-stats">
								<div><span><?php esc_html_e( 'Bus No', 'bus-ticket-booking-with-seat-reservation' ); ?></span><strong><?php echo esc_html( $bus_no !== '' ? $bus_no : '—' ); ?></strong></div>
								<div><span><?php esc_html_e( 'Seats', 'bus-ticket-booking-with-seat-reservation' ); ?></span><strong><?php echo esc_html( $seats ? $seats : '0' ); ?></strong></div>
							</div>
						</div>
					</div>

					<div class="wbtm-bme__rail-card">
						<div class="wbtm-bme__rail-title">
							<span class="dashicons dashicons-format-gallery"></span><?php esc_html_e( 'Gallery', 'bus-ticket-booking-with-seat-reservation' ); ?>
							<button type="button" class="wbtm-bme__rail-manage" data-bme-goto="advanced" data-bme-scroll="#wbtm_settings_gallery_images"><?php esc_html_e( 'Manage', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						</div>
						<?php if ( ! empty( $gallery ) ) : ?>
							<div class="wbtm-bme__rail-gallery">
								<?php foreach ( array_slice( $gallery, 0, 6 ) as $gid ) :
									$g = wp_get_attachment_image_url( $gid, 'thumbnail' );
									if ( $g ) :
										?>
										<img src="<?php echo esc_url( $g ); ?>" alt=""/>
									<?php endif; endforeach; ?>
							</div>
						<?php else : ?>
							<div class="wbtm-bme__rail-empty"><?php esc_html_e( 'No gallery images yet. Click Manage to upload.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
						<?php endif; ?>
					</div>

					<div class="wbtm-bme__rail-card">
						<div class="wbtm-bme__rail-title">
							<span class="dashicons dashicons-star-filled"></span><?php esc_html_e( 'Features', 'bus-ticket-booking-with-seat-reservation' ); ?>
							<button type="button" class="wbtm-bme__rail-manage" data-bme-goto="advanced" data-bme-scroll="#wbtm_bus_feature_settings"><?php esc_html_e( 'Manage', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						</div>
						<?php if ( ! empty( $feat_ids ) ) : ?>
							<div class="wbtm-bme__rail-features">
								<?php foreach ( $feat_ids as $tid ) :
									$term = get_term( $tid, 'wbtm_bus_feature' );
									if ( ! $term || is_wp_error( $term ) ) {
										continue;
									}
									$icon = get_term_meta( $tid, 'wbtm_bus_feature_icon', true );
									?>
									<span class="wbtm-bme__chip"><?php if ( $icon ) : ?><i class="<?php echo esc_attr( $icon ); ?>"></i><?php endif; ?><?php echo esc_html( $term->name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="wbtm-bme__rail-empty"><?php esc_html_e( 'No features selected yet. Click Manage to add.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
						<?php endif; ?>
					</div>
				</aside>
				<?php
			}

			/** Wrap one reused classic section in a modern card. */
			private function render_section_card( $section, $post_id ) {
				list( $class, $method, $title, $subtitle ) = $section;
				$instance = $this->section_instance( $class );
				if ( ! $instance || ! method_exists( $instance, $method ) ) {
					return;
				}
				?>
				<div class="wbtm-bme__card" data-has-head>
					<div class="wbtm-bme__card-head">
						<h2><span class="wbtm-bme__dot"></span><?php echo esc_html( $title ); ?></h2>
						<?php if ( $subtitle ) : ?>
							<p><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
					<div class="wbtm-bme__card-body">
						<?php $instance->$method( $post_id ); ?>
					</div>
				</div>
				<?php
			}

			/* ------------------------------------------------------------------ *
			 *  Editor-style switcher (side metabox)
			 * ------------------------------------------------------------------ */

			public function render_switcher() {
				$ui = self::current_ui();
				?>
				<div class="wbtm-ui-switch" data-bme-switch>
					<button type="button" class="wbtm-ui-switch__opt<?php echo $ui === 'classic' ? ' is-active' : ''; ?>" data-bme-ui="classic"><?php esc_html_e( 'Classic', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
					<button type="button" class="wbtm-ui-switch__opt<?php echo $ui === 'modern' ? ' is-active' : ''; ?>" data-bme-ui="modern"><?php esc_html_e( 'Modern', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
				</div>
				<p class="howto" style="margin:8px 2px 0;color:#646970;font-size:12px;">
					<?php esc_html_e( 'Choose how the bus editor looks for your account. This only affects you.', 'bus-ticket-booking-with-seat-reservation' ); ?>
				</p>
				<style>
					.wbtm-ui-switch{display:flex;gap:0;border:1px solid #dcdfe5;border-radius:8px;overflow:hidden;}
					.wbtm-ui-switch__opt{flex:1;border:0;background:#fff;color:#475569;font-weight:600;padding:8px 10px;cursor:pointer;}
					.wbtm-ui-switch__opt+.wbtm-ui-switch__opt{border-left:1px solid #dcdfe5;}
					.wbtm-ui-switch__opt.is-active{background:#e11d48;color:#fff;}
				</style>
				<?php
			}

			/**
			 * Save the WordPress feature image (post thumbnail) chosen from the modern
			 * rail. Only acts when the modern field is submitted, so classic is untouched.
			 */
			public function save_feature_image( $post_id ) {
				if ( ! array_key_exists( 'wbtm_bme_thumbnail_id', $_POST ) ) {
					return;
				}
				if ( get_post_type( $post_id ) !== WBTM_Functions::get_cpt() ) {
					return;
				}
				if ( ! isset( $_POST['wbtm_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_type_nonce'] ) ), 'wbtm_type_nonce' ) ) {
					return;
				}
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
				$thumb = (int) wp_unslash( $_POST['wbtm_bme_thumbnail_id'] );
				if ( $thumb > 0 ) {
					set_post_thumbnail( $post_id, $thumb );
				} else {
					delete_post_thumbnail( $post_id );
				}
			}

			public function ajax_set_ui() {
				check_ajax_referer( 'wbtm_bme_ui', 'nonce' );
				if ( ! current_user_can( 'edit_posts' ) ) {
					wp_send_json_error( 'forbidden' );
				}
				$ui = ( isset( $_POST['ui'] ) && sanitize_text_field( wp_unslash( $_POST['ui'] ) ) === 'modern' ) ? 'modern' : 'classic';
				update_user_meta( get_current_user_id(), self::USER_META, $ui );
				wp_send_json_success( array( 'ui' => $ui ) );
			}

			/* ------------------------------------------------------------------ *
			 *  Assets & body class
			 * ------------------------------------------------------------------ */

			public function body_class( $classes ) {
				if ( $this->is_bus_edit_screen() && $this->is_modern() ) {
					$classes .= ' wbtm-bme-active';
				}
				return $classes;
			}

			/** Cache-bust on file change so edits show without a manual hard-refresh. */
			private function asset_ver( $rel_path ) {
				$file = WBTM_PLUGIN_DIR . $rel_path;
				return file_exists( $file ) ? (string) filemtime( $file ) : WBTM_VERSION;
			}

			public function enqueue() {
				if ( ! $this->is_bus_edit_screen() ) {
					return;
				}
				// The switcher button (classic mode) needs the tiny AJAX handler too.
				wp_enqueue_script( 'wbtm-bus-edit-modern', WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-bus-edit-modern.js', array( 'jquery' ), $this->asset_ver( '/assets/admin/js/wbtm-bus-edit-modern.js' ), true );
				wp_localize_script(
					'wbtm-bus-edit-modern',
					'wbtmBme',
					array(
						'ajax'    => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'wbtm_bme_ui' ),
						'listUrl' => admin_url( 'edit.php?post_type=' . WBTM_Functions::get_cpt() ),
						'savedTxt'=> esc_html__( 'Saved', 'bus-ticket-booking-with-seat-reservation' ),
						'savingTxt'=> esc_html__( 'Saving…', 'bus-ticket-booking-with-seat-reservation' ),
						'nextTxt' => esc_html__( 'Next Step', 'bus-ticket-booking-with-seat-reservation' ),
						'updateTxt'=> esc_html__( 'Update', 'bus-ticket-booking-with-seat-reservation' ),
						'featTitle'=> esc_html__( 'Select feature image', 'bus-ticket-booking-with-seat-reservation' ),
						'featBtn'  => esc_html__( 'Use image', 'bus-ticket-booking-with-seat-reservation' ),
						'featSet'  => esc_html__( 'Feature image set', 'bus-ticket-booking-with-seat-reservation' ),
						'featRemoved'=> esc_html__( 'Feature image removed', 'bus-ticket-booking-with-seat-reservation' ),
					)
				);

				if ( $this->is_modern() ) {
					wp_enqueue_style( 'wbtm-bme-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap', array(), null );
					wp_enqueue_style( 'wbtm-bus-edit-modern', WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-bus-edit-modern.css', array(), $this->asset_ver( '/assets/admin/css/wbtm-bus-edit-modern.css' ) );
				}
			}
		}

		new WBTM_Settings_Modern();
	}
