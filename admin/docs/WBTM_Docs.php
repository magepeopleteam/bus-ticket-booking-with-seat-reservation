<?php
	/*
   * @Author 		MagePeople Team
   * Copyright: 	mage-people.com
   *
   * Unified Documents screen.
   *
   * One submenu that documents the whole product. Free chapters always render;
   * PRO chapters render in full when the addon is active and as a locked
   * preview when it is not, so the free user can see exactly what upgrading
   * adds without the docs quietly hiding half the product.
   *
   * Content lives in WBTM_Docs_Content; the settings reference is built live
   * from the settings API by WBTM_Docs_Settings. This class only renders.
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.

	if ( ! class_exists( 'WBTM_Docs' ) ) {
		class WBTM_Docs {

			const SLUG = 'wbtm_docs_page';

			public function __construct() {
				add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			}

			/** Is the PRO addon available? */
			private function pro_active(): bool {
				return class_exists( 'WBTM_Functions' ) && WBTM_Functions::is_pro_active();
			}

			public function register_menu(): void {
				$cpt = class_exists( 'WBTM_Functions' ) ? WBTM_Functions::get_cpt() : 'wbtm_bus';
				add_submenu_page(
					'edit.php?post_type=' . $cpt,
					esc_html__( 'Documents', 'bus-ticket-booking-with-seat-reservation' ),
					esc_html__( 'Documents', 'bus-ticket-booking-with-seat-reservation' ),
					'manage_options',
					self::SLUG,
					array( $this, 'render_page' )
				);
			}

			/** Only load our assets on our own screen. */
			public function enqueue_assets(): void {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check, no state change.
				$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
				if ( self::SLUG !== $page ) {
					return;
				}
				$css = WBTM_PLUGIN_DIR . '/assets/admin/css/wbtm-docs.css';
				$js  = WBTM_PLUGIN_DIR . '/assets/admin/js/wbtm-docs.js';
				wp_enqueue_style(
					'wbtm-docs',
					WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-docs.css',
					array(),
					file_exists( $css ) ? filemtime( $css ) : WBTM_VERSION
				);
				wp_enqueue_script(
					'wbtm-docs',
					WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-docs.js',
					array( 'jquery' ),
					file_exists( $js ) ? filemtime( $js ) : WBTM_VERSION,
					true
				);
				wp_localize_script(
					'wbtm-docs',
					'wbtmDocsI18n',
					array(
						'noResults' => esc_html__( 'Nothing matched that search.', 'bus-ticket-booking-with-seat-reservation' ),
						'matches'   => esc_html__( 'matches', 'bus-ticket-booking-with-seat-reservation' ),
					)
				);
			}

			/* ============================ RENDER ============================ */

			public function render_page(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to view this page.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$pro       = $this->pro_active();
				$groups    = WBTM_Docs_Content::groups();
				$chapters  = WBTM_Docs_Content::chapters();
				$settings  = WBTM_Docs_Settings::build();
				$by_group  = array();

				foreach ( $chapters as $chapter ) {
					if ( empty( $chapter['id'] ) || empty( $chapter['group'] ) ) {
						continue;
					}
					$by_group[ $chapter['group'] ][] = $chapter;
				}
				?>
				<div class="wrap wbtm-docs-wrap">

					<div class="wbtm-docs-header">
						<div class="wbtm-docs-header-text">
							<h1><?php esc_html_e( 'Documents', 'bus-ticket-booking-with-seat-reservation' ); ?></h1>
							<p>
								<?php
								if ( $pro ) {
									esc_html_e( 'The complete handbook for the plugin and the PRO addon — every screen, every field, every setting.', 'bus-ticket-booking-with-seat-reservation' );
								} else {
									esc_html_e( 'The complete handbook for the plugin — every screen, every field, every setting. PRO chapters are included too, so you can see what the addon adds.', 'bus-ticket-booking-with-seat-reservation' );
								}
								?>
							</p>
						</div>
						<div class="wbtm-docs-header-side">
							<span class="wbtm-docs-plan <?php echo $pro ? 'is-pro' : 'is-free'; ?>">
								<i class="fas <?php echo $pro ? 'fa-crown' : 'fa-cube'; ?>"></i>
								<?php
								echo $pro
									? esc_html__( 'Free + PRO active', 'bus-ticket-booking-with-seat-reservation' )
									: esc_html__( 'Free version', 'bus-ticket-booking-with-seat-reservation' );
								?>
							</span>
							<?php if ( ! $pro ) : ?>
								<a class="wbtm-docs-upgrade" target="_blank" rel="noopener noreferrer"
								   href="https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/">
									<i class="fas fa-crown"></i> <?php esc_html_e( 'Get PRO', 'bus-ticket-booking-with-seat-reservation' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<div class="wbtm-docs-search-bar">
						<i class="fas fa-magnifying-glass"></i>
						<input type="search" id="wbtm-docs-search"
						       placeholder="<?php esc_attr_e( 'Search the handbook — try "seat hold", "cut-off" or "coupon"…', 'bus-ticket-booking-with-seat-reservation' ); ?>"
						       autocomplete="off" />
						<button type="button" id="wbtm-docs-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'bus-ticket-booking-with-seat-reservation' ); ?>">&times;</button>
						<span class="wbtm-docs-search-count" id="wbtm-docs-search-count"></span>
					</div>

					<div class="wbtm-docs-layout">

						<aside class="wbtm-docs-nav" id="wbtm-docs-nav">
							<?php foreach ( $groups as $group_id => $group ) : ?>
								<?php
								$is_settings_group = ( 'settings' === $group_id );
								if ( ! $is_settings_group && empty( $by_group[ $group_id ] ) ) {
									continue;
								}
								?>
								<div class="wbtm-docs-nav-group" data-group="<?php echo esc_attr( $group_id ); ?>">
									<h3><i class="<?php echo esc_attr( $group['icon'] ); ?>"></i> <?php echo esc_html( $group['title'] ); ?></h3>
									<ul>
										<?php if ( $is_settings_group ) : ?>
											<?php foreach ( $settings as $section ) : ?>
												<li>
													<a href="#set-<?php echo esc_attr( $section['id'] ); ?>"
													   data-target="set-<?php echo esc_attr( $section['id'] ); ?>">
														<?php echo esc_html( '' !== $section['tab'] ? $section['tab'] : $section['title'] ); ?>
														<?php if ( 'pro' === $section['plan'] ) : ?>
															<span class="wbtm-docs-pill">PRO</span>
														<?php endif; ?>
													</a>
												</li>
											<?php endforeach; ?>
										<?php else : ?>
											<?php foreach ( $by_group[ $group_id ] as $chapter ) : ?>
												<li>
													<a href="#doc-<?php echo esc_attr( $chapter['id'] ); ?>"
													   data-target="doc-<?php echo esc_attr( $chapter['id'] ); ?>">
														<?php echo esc_html( $chapter['title'] ); ?>
														<?php if ( 'pro' === $chapter['plan'] ) : ?>
															<span class="wbtm-docs-pill">PRO</span>
														<?php endif; ?>
													</a>
												</li>
											<?php endforeach; ?>
										<?php endif; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</aside>

						<main class="wbtm-docs-content" id="wbtm-docs-content">

							<?php
							foreach ( $groups as $group_id => $group ) :
								if ( 'settings' === $group_id ) {
									$this->render_settings_group( $group, $settings, $pro );
									continue;
								}
								if ( empty( $by_group[ $group_id ] ) ) {
									continue;
								}
								?>
								<section class="wbtm-docs-group-head">
									<h2><i class="<?php echo esc_attr( $group['icon'] ); ?>"></i> <?php echo esc_html( $group['title'] ); ?></h2>
								</section>
								<?php
								foreach ( $by_group[ $group_id ] as $chapter ) {
									$this->render_chapter( $chapter, $pro );
								}
							endforeach;
							?>

							<p class="wbtm-docs-no-results" id="wbtm-docs-no-results" hidden></p>

							<section class="wbtm-docs-chapter wbtm-docs-support" id="doc-support">
								<h3><i class="fas fa-life-ring"></i> <?php esc_html_e( 'Still stuck?', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
								<p><?php esc_html_e( 'If the handbook did not answer your question, we are happy to help. Include the report from Bus → Settings → Status so we can see your environment straight away.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
								<p class="wbtm-docs-links">
									<a class="wbtm-docs-btn" target="_blank" rel="noopener noreferrer" href="https://mage-people.com/contact-us/">
										<i class="fas fa-headset"></i> <?php esc_html_e( 'Contact support', 'bus-ticket-booking-with-seat-reservation' ); ?>
									</a>
									<a class="wbtm-docs-btn is-ghost" target="_blank" rel="noopener noreferrer" href="https://docs.mage-people.com/plugins/wpbusticketly/overview">
										<i class="fas fa-book"></i> <?php esc_html_e( 'Online documentation', 'bus-ticket-booking-with-seat-reservation' ); ?>
									</a>
								</p>
							</section>

						</main>
					</div>
				</div>
				<?php
			}

			/* ---------------------------- CHAPTERS ---------------------------- */

			/**
			 * @param array $chapter Chapter definition.
			 * @param bool  $pro     Whether the PRO addon is active.
			 */
			private function render_chapter( array $chapter, bool $pro ): void {
				$is_pro  = ( isset( $chapter['plan'] ) && 'pro' === $chapter['plan'] );
				$locked  = ( $is_pro && ! $pro );
				$classes = 'wbtm-docs-chapter';
				if ( $is_pro ) {
					$classes .= ' is-pro';
				}
				if ( $locked ) {
					$classes .= ' is-locked';
				}
				?>
				<section class="<?php echo esc_attr( $classes ); ?>" id="doc-<?php echo esc_attr( $chapter['id'] ); ?>">
					<h3>
						<i class="<?php echo esc_attr( isset( $chapter['icon'] ) ? $chapter['icon'] : 'fas fa-file-lines' ); ?>"></i>
						<?php echo esc_html( $chapter['title'] ); ?>
						<?php if ( $is_pro ) : ?>
							<span class="wbtm-docs-pill">PRO</span>
						<?php endif; ?>
					</h3>

					<?php if ( ! empty( $chapter['intro'] ) ) : ?>
						<p class="wbtm-docs-intro"><?php echo esc_html( $chapter['intro'] ); ?></p>
					<?php endif; ?>

					<?php if ( $locked ) : ?>
						<div class="wbtm-docs-locked-note">
							<i class="fas fa-lock"></i>
							<span><?php esc_html_e( 'This is a PRO feature. The reference below describes how it works once the addon is active.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<a target="_blank" rel="noopener noreferrer" href="https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/">
								<?php esc_html_e( 'See PRO plans', 'bus-ticket-booking-with-seat-reservation' ); ?>
							</a>
						</div>
					<?php endif; ?>

					<?php
					if ( ! empty( $chapter['blocks'] ) && is_array( $chapter['blocks'] ) ) {
						foreach ( $chapter['blocks'] as $block ) {
							$this->render_block( $block );
						}
					}
					?>
				</section>
				<?php
			}

			/**
			 * @param array $block A single content block.
			 */
			private function render_block( array $block ): void {
				$type = isset( $block['type'] ) ? $block['type'] : 'text';

				switch ( $type ) {

					case 'text':
						if ( ! empty( $block['text'] ) ) {
							echo '<p>' . esc_html( $block['text'] ) . '</p>';
						}
						break;

					case 'note':
					case 'tip':
					case 'warn':
						if ( empty( $block['text'] ) ) {
							break;
						}
						$icons = array( 'note' => 'fa-circle-info', 'tip' => 'fa-lightbulb', 'warn' => 'fa-triangle-exclamation' );
						printf(
							'<div class="wbtm-docs-callout is-%1$s"><i class="fas %2$s"></i><span>%3$s</span></div>',
							esc_attr( $type ),
							esc_attr( $icons[ $type ] ),
							esc_html( $block['text'] )
						);
						break;

					case 'image':
						if ( empty( $block['file'] ) ) {
							break;
						}
						// Chapters can come from the 'wbtm_docs_chapters' filter, so the
						// filename is treated as untrusted: strip any path, then allow
						// only a plain lowercase image name. Nothing outside the bundled
						// docs folder can be referenced.
						$file = basename( sanitize_file_name( (string) $block['file'] ) );
						if ( ! preg_match( '/^[a-z0-9][a-z0-9\-]*\.(jpg|png)$/', $file ) ) {
							break;
						}
						$path = WBTM_PLUGIN_DIR . '/assets/admin/docs/wbtm-doc-' . $file;
						if ( ! file_exists( $path ) ) {
							break;
						}
						$caption = isset( $block['caption'] ) ? $block['caption'] : '';
						?>
						<figure class="wbtm-docs-figure">
							<img src="<?php echo esc_url( WBTM_PLUGIN_URL . '/assets/admin/docs/wbtm-doc-' . $file ); ?>"
							     alt="<?php echo esc_attr( $caption ); ?>" loading="lazy" decoding="async" />
							<?php if ( '' !== $caption ) : ?>
								<figcaption><?php echo esc_html( $caption ); ?></figcaption>
							<?php endif; ?>
						</figure>
						<?php
						break;

					case 'list':
						if ( empty( $block['items'] ) || ! is_array( $block['items'] ) ) {
							break;
						}
						if ( ! empty( $block['title'] ) ) {
							echo '<h4>' . esc_html( $block['title'] ) . '</h4>';
						}
						echo '<ul class="wbtm-docs-list">';
						foreach ( $block['items'] as $item ) {
							if ( is_array( $item ) ) {
								$term = isset( $item[0] ) ? $item[0] : '';
								$desc = isset( $item[1] ) ? $item[1] : '';
								printf(
									'<li><strong>%1$s</strong>%2$s</li>',
									esc_html( $term ),
									'' !== $desc ? ' — ' . esc_html( $desc ) : ''
								);
							} else {
								echo '<li>' . esc_html( $item ) . '</li>';
							}
						}
						echo '</ul>';
						break;

					case 'steps':
						if ( empty( $block['items'] ) || ! is_array( $block['items'] ) ) {
							break;
						}
						if ( ! empty( $block['title'] ) ) {
							echo '<h4>' . esc_html( $block['title'] ) . '</h4>';
						}
						echo '<ol class="wbtm-docs-steps">';
						foreach ( $block['items'] as $item ) {
							echo '<li>' . esc_html( $item ) . '</li>';
						}
						echo '</ol>';
						break;

					case 'table':
						if ( empty( $block['rows'] ) || ! is_array( $block['rows'] ) ) {
							break;
						}
						echo '<div class="wbtm-docs-table-scroll"><table class="wbtm-docs-table">';
						if ( ! empty( $block['head'] ) && is_array( $block['head'] ) ) {
							echo '<thead><tr>';
							foreach ( $block['head'] as $th ) {
								echo '<th>' . esc_html( $th ) . '</th>';
							}
							echo '</tr></thead>';
						}
						echo '<tbody>';
						foreach ( $block['rows'] as $row ) {
							if ( ! is_array( $row ) ) {
								continue;
							}
							echo '<tr>';
							foreach ( $row as $cell ) {
								// Cells may carry a <code> wrapper for shortcodes; nothing else is allowed.
								echo '<td>' . wp_kses( $cell, array( 'code' => array(), 'strong' => array(), 'em' => array() ) ) . '</td>';
							}
							echo '</tr>';
						}
						echo '</tbody></table></div>';
						break;

					case 'videos':
						if ( empty( $block['items'] ) || ! is_array( $block['items'] ) ) {
							break;
						}
						echo '<div class="wbtm-docs-videos">';
						foreach ( $block['items'] as $video ) {
							if ( empty( $video['id'] ) ) {
								continue;
							}
							$vid   = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $video['id'] );
							$title = isset( $video['title'] ) ? $video['title'] : '';
							?>
							<div class="wbtm-docs-video">
								<div class="wbtm-docs-video-frame">
									<iframe src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $vid ); ?>"
									        title="<?php echo esc_attr( $title ); ?>" loading="lazy"
									        allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
								</div>
								<span>
									<?php echo esc_html( $title ); ?>
									<?php if ( isset( $video['plan'] ) && 'pro' === $video['plan'] ) : ?>
										<span class="wbtm-docs-pill">PRO</span>
									<?php endif; ?>
								</span>
							</div>
							<?php
						}
						echo '</div>';
						break;
				}
			}

			/* ------------------------ SETTINGS REFERENCE ------------------------ */

			/**
			 * @param array $group    Group definition from WBTM_Docs_Content::groups().
			 * @param array $settings Output of WBTM_Docs_Settings::build().
			 * @param bool  $pro      Whether the PRO addon is active.
			 */
			private function render_settings_group( array $group, array $settings, bool $pro ): void {
				$total = 0;
				foreach ( $settings as $section ) {
					$total += count( $section['fields'] );
				}
				?>
				<section class="wbtm-docs-group-head">
					<h2><i class="<?php echo esc_attr( $group['icon'] ); ?>"></i> <?php echo esc_html( $group['title'] ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: number of settings, 2: number of tabs. */
							esc_html__( 'Every one of the %1$d settings under Bus → Settings, across all %2$d tabs. This list is generated from the plugin itself, so it is never out of date.', 'bus-ticket-booking-with-seat-reservation' ),
							(int) $total,
							count( $settings )
						);
						?>
						<?php if ( ! $pro ) : ?>
							<em><?php esc_html_e( 'PRO tabs appear here once the addon is active.', 'bus-ticket-booking-with-seat-reservation' ); ?></em>
						<?php endif; ?>
					</p>
				</section>

				<?php foreach ( $settings as $section ) : ?>
					<section class="wbtm-docs-chapter<?php echo 'pro' === $section['plan'] ? ' is-pro' : ''; ?>"
					         id="set-<?php echo esc_attr( $section['id'] ); ?>">
						<h3>
							<i class="fas fa-sliders"></i>
							<?php echo esc_html( '' !== $section['tab'] ? $section['tab'] : $section['title'] ); ?>
							<?php if ( 'pro' === $section['plan'] ) : ?>
								<span class="wbtm-docs-pill">PRO</span>
							<?php endif; ?>
						</h3>

						<?php if ( '' !== $section['intro'] ) : ?>
							<p class="wbtm-docs-intro"><?php echo esc_html( $section['intro'] ); ?></p>
						<?php endif; ?>

						<?php if ( empty( $section['fields'] ) ) : ?>
							<p class="wbtm-docs-empty">
								<?php esc_html_e( 'This tab has no stored fields — it renders its own interface instead.', 'bus-ticket-booking-with-seat-reservation' ); ?>
							</p>
						<?php else : ?>
							<div class="wbtm-docs-table-scroll">
								<table class="wbtm-docs-table wbtm-docs-settings-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Setting', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
											<th><?php esc_html_e( 'What it does', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
											<th><?php esc_html_e( 'Type', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
											<th><?php esc_html_e( 'Default', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $section['fields'] as $field ) : ?>
											<tr>
												<td>
													<strong><?php echo esc_html( $field['label'] ); ?></strong>
													<code class="wbtm-docs-key"><?php echo esc_html( $field['name'] ); ?></code>
												</td>
												<td>
													<?php echo '' !== $field['desc'] ? esc_html( $field['desc'] ) : '<span class="wbtm-docs-muted">&mdash;</span>'; ?>
													<?php if ( ! empty( $field['options'] ) ) : ?>
														<span class="wbtm-docs-options">
															<?php foreach ( $field['options'] as $option ) : ?>
																<span class="wbtm-docs-option"><?php echo esc_html( $option ); ?></span>
															<?php endforeach; ?>
														</span>
													<?php endif; ?>
												</td>
												<td><span class="wbtm-docs-type"><?php echo esc_html( WBTM_Docs_Settings::friendly_type( $field['type'] ) ); ?></span></td>
												<td><?php echo esc_html( $field['default'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
				<?php
			}
		}
	}
