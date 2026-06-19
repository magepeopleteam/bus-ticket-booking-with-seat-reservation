<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Global_settings' ) ) {
		class WBTM_Global_settings {
			protected $settings_api;

			public function __construct() {
				$this->settings_api = new WBTM_Setting_API;
				add_action( 'admin_menu', array( $this, 'global_settings_menu' ) );
				add_action( 'admin_init', array( $this, 'admin_init' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_new_settings_assets' ) );
				add_action( 'admin_head', array( $this, 'dynamic_menu_icon' ) );
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'settings_sec_reg' ) );
				add_filter( 'wbtm_settings_sec_fields', array( $this, 'settings_sec_fields' ) );
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'global_sec_reg' ), 90 );
				add_action( 'wbtm_wsa_form_bottom_wbtm_license_settings', [ $this, 'license_settings' ] );
				add_action( 'wbtm_basic_license_list', [ $this, 'licence_area' ] );
			}

			public function dynamic_menu_icon() {
				WBTM_Functions::output_dynamic_menu_icon_css();
			}

			public function enqueue_new_settings_assets($hook) {
				// Only load on global settings page
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
				if ($page !== 'wbtm_settings_page') return;

				wp_enqueue_style(
					'wbtm-global-settings',
					WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-global-settings.css',
					[],
					WBTM_VERSION
				);
				wp_enqueue_script(
					'wbtm-global-settings-js',
					WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-global-settings.js',
					['jquery'],
					WBTM_VERSION,
					true
				);
				wp_enqueue_style('wp-color-picker');
				wp_enqueue_script('wp-color-picker');
				wp_enqueue_editor();
				wp_enqueue_script('jquery-ui-datepicker');
			}

			public function global_settings_menu() {
				$cpt = WBTM_Functions::get_cpt();
				add_submenu_page( 'edit.php?post_type=' . $cpt, esc_html__( ' Settings', 'bus-ticket-booking-with-seat-reservation' ), esc_html__( ' Settings', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', 'wbtm_settings_page', array( $this, 'settings_page' ) );
				add_submenu_page( 'edit.php?post_type=' . $cpt, esc_html__( ' Term And Condition', 'bus-ticket-booking-with-seat-reservation' ), esc_html__( ' Term And Condition', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', 'wbtm_term_and_condition_page', array( $this, 'wbtm_term_and_condition' ) );
			}

			public function settings_page() {
				$this->render_new_settings_page();
			}

			public function render_new_settings_page() {
				$sections_raw = $this->settings_api->get_sections();
				$fields       = $this->settings_api->get_fields();

				// Sections are numeric-indexed; convert to associative by 'id'
				$sections = [];
				if (is_array($sections_raw)) {
					foreach ($sections_raw as $sec) {
						if (isset($sec['id'])) {
							$sections[$sec['id']] = $sec;
						}
					}
				}

				$tab_configs  = $this->get_tab_configs();

				// Track which section IDs are handled by tab_configs
				$handled_section_ids = [];
				foreach ($tab_configs as $tab_id => $config) {
					if (isset($config['section_id'])) {
						$handled_section_ids[] = $config['section_id'];
					}
				}

				// Build visible tabs from configs that match registered sections
				$visible_tabs = [];
				foreach ($tab_configs as $tab_id => $config) {
					$section_id = isset($config['section_id']) ? $config['section_id'] : $tab_id;
					$is_license = ($section_id === 'wbtm_license_settings');
					if (isset($sections[$section_id]) || $is_license) {
						$visible_tabs[$tab_id] = $config;
					}
				}

				// Fallback: add any registered section not already handled
				foreach ($sections as $section_id => $section) {
					if (!in_array($section_id, $handled_section_ids, true)) {
						$visible_tabs[$section_id] = [
							'title'      => $section['title'],
							'icon'       => 'fas fa-cog',
							'subtitle'   => '',
							'section_id' => $section_id,
						];
					}
				}

				$first_tab = !empty($visible_tabs) ? array_key_first($visible_tabs) : '';
				$label = WBTM_Functions::get_name();

				// Enqueue assets
				add_action('admin_footer', function() use ($tab_configs, $first_tab) {
					$meta = [];
					foreach ($tab_configs as $id => $cfg) {
						$meta[$id] = [$cfg['title'], isset($cfg['subtitle']) ? $cfg['subtitle'] : ''];
					}

					// Build AI provider map if the section exists
					$ai_provider_map = [];
					$ai_provider_all = [];
					if (class_exists('WBTM_AI_Settings')) {
						$providers = WBTM_AI_Settings::get_providers();
						foreach ($providers as $slug => $cfg) {
							if ($slug === 'rule_based' || empty($cfg['models'])) {
								$ai_provider_map[$slug] = [];
							} else {
								$cls = [$slug . '_api_key'];
								if (!empty($cfg['has_custom_endpoint'])) $cls[] = $slug . '_endpoint';
								$cls[] = $slug . '_model';
								$ai_provider_map[$slug] = $cls;
							}
						}
						foreach ($ai_provider_map as $classes) {
							foreach ($classes as $cls) $ai_provider_all[] = $cls;
						}
					}
					$has_ai = !empty($ai_provider_all);
					?>
					<style>
						.bm-gs__field-row.wbtm-hidden { display: none !important; }
						.wbtm_add_icon_image_area .wbtm_icon_remove,
						.wbtm_add_icon_image_area .wbtm_image_remove { cursor: pointer; z-index: 2; position: relative; }
					</style>
					<script>
						window.bmGs = window.bmGs || {};
						window.bmGs.tabMeta = <?php echo wp_json_encode($meta); ?>;
						window.bmGs.defaultTab = <?php echo wp_json_encode($first_tab); ?>;
						jQuery(function($){
							// Save button — find and submit the active tab's form.
							// Uses HTMLFormElement.prototype.submit to avoid the "id=submit"
							// shadow bug (WP's submit_button() creates <input id="submit">).
							$('#bm-save-btn').on('click', function(e){
								e.preventDefault();
								var $f = $('.bm-gs__tab-panel.bm-gs--active').find('form').first();
								if ($f.length) { HTMLFormElement.prototype.submit.call($f[0]); }
							});
							// Icon / image remove — instant hide + show add buttons
							$(document).on('click', '.wbtm_add_icon_image_area .wbtm_icon_remove', function(e){
								e.stopPropagation();
								var p = $(this).closest('.wbtm_add_icon_image_area');
								p.find('input[type="hidden"]').val('');
								p.find('[data-add-icon]').removeAttr('class');
								p.find('.wbtm_icon_item, .wbtm_image_item').removeClass('dNone').hide();
								p.find('.wbtm_add_icon_image_button_area').removeClass('dNone').show();
							});
							$(document).on('click', '.wbtm_add_icon_image_area .wbtm_image_remove', function(e){
								e.stopPropagation();
								var p = $(this).closest('.wbtm_add_icon_image_area');
								p.find('input[type="hidden"]').val('');
								p.find('img').attr('src', '');
								p.find('.wbtm_icon_item, .wbtm_image_item').removeClass('dNone').hide();
								p.find('.wbtm_add_icon_image_button_area').removeClass('dNone').show();
							});
							// Color pickers — standard WP picker, init once per field
							$('.wbtm-color-field').each(function(){
								var $field = $(this);
								if (!$field.closest('.wp-picker-container').length) {
									$field.wpColorPicker();
								}
							});
							// File upload triggers
							$('.wbtm-file-upload-trigger').on('click', function(e){
								e.preventDefault();
								var btn = $(this), target = btn.data('target');
								var input = $('input[name="'+target+'"]');
								var frame = wp.media({ title: '<?php echo esc_js(__('Select File', 'bus-ticket-booking-with-seat-reservation')); ?>', multiple: false });
								frame.on('select', function(){
									var attachment = frame.state().get('selection').first().toJSON();
									input.val(attachment.url);
									input.trigger('change');
								});
								frame.open();
							});
							// Datepicker
							$('.wbtm-datepicker').datepicker({
								dateFormat: 'dd-mm-yy',
								changeMonth: true,
								changeYear: true,
							});
							<?php if ($has_ai): ?>
							// AI Chatbot provider toggle
							var aiMap = <?php echo wp_json_encode($ai_provider_map); ?>;
							var aiAll = <?php echo wp_json_encode($ai_provider_all); ?>;
							var $aiProvider = $('select[name="wbtm_ai_chatbot_settings[ai_provider]"]');
							function applyAiVisibility(selected) {
								aiAll.forEach(function(cls){
									$('.wbtm-field-' + cls).addClass('wbtm-hidden');
								});
								var toShow = aiMap[selected] || [];
								toShow.forEach(function(cls){
									$('.wbtm-field-' + cls).removeClass('wbtm-hidden');
								});
							}
							if ($aiProvider.length) {
								applyAiVisibility($aiProvider.val());
								$aiProvider.on('change', function(){ applyAiVisibility($(this).val()); });
							}
							<?php endif; ?>
						});
					</script>
					<?php
				}, 1);

				?>
				<div class="bm-gs__root">
					<div class="bm-gs__wrap">
						<!-- Mobile overlay -->
						<div class="bm-gs__overlay" id="bm-overlay"></div>

						<!-- SIDEBAR -->
						<div class="bm-gs__sidebar" id="bm-sidebar">
							<div class="bm-gs__sb-header">
								<div class="bm-gs__sb-plugin-label"><?php echo esc_html($label); ?></div>
								<div class="bm-gs__sb-title">
									<span class="bm-gs__sb-dot"></span>
									<?php esc_html_e('Global Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
								</div>
							</div>
							<nav class="bm-gs__sb-nav">
								<?php foreach ($visible_tabs as $tab_id => $config): ?>
									<button type="button" class="bm-gs__nav-item<?php echo $tab_id === $first_tab ? ' bm-gs--active' : ''; ?>" data-tab="<?php echo esc_attr($tab_id); ?>">
										<span class="bm-gs__nav-icon <?php echo esc_attr(isset($config['icon']) ? $config['icon'] : 'fas fa-cog'); ?>"></span>
										<?php echo esc_html($config['title']); ?>
									</button>
								<?php endforeach; ?>
							</nav>
							<div class="bm-gs__sb-footer">
								<?php 
								$has_pro = class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
								$badge_class = $has_pro ? 'bm-gs--pro' : '';
								$badge_text  = $has_pro 
									? esc_html__('PRO plan active', 'bus-ticket-booking-with-seat-reservation')
									: esc_html__('Free plan active', 'bus-ticket-booking-with-seat-reservation');
								?>
								<div class="bm-gs__lic-badge <?php echo $badge_class; ?>">
									<span class="bm-gs__lic-dot"></span>
									<span class="bm-gs__lic-text"><?php echo $badge_text; ?></span>
								</div>
							</div>
						</div>

						<!-- MAIN -->
						<div class="bm-gs__main">
							<div class="bm-gs__topbar">
								<button type="button" class="bm-gs__menu-btn" id="bm-menu-btn" aria-label="<?php esc_attr_e('Open menu', 'bus-ticket-booking-with-seat-reservation'); ?>">
									<span class="fas fa-bars"></span>
								</button>
								<span class="bm-gs__topbar-title" id="bm-topbar-title"><?php 
									echo esc_html(isset($visible_tabs[$first_tab]) ? $visible_tabs[$first_tab]['title'] : '');
								?></span>
								<span class="bm-gs__topbar-sep">&rsaquo;</span>
								<span class="bm-gs__topbar-sub" id="bm-topbar-sub"><?php 
									echo esc_html(isset($visible_tabs[$first_tab]) ? (isset($visible_tabs[$first_tab]['subtitle']) ? $visible_tabs[$first_tab]['subtitle'] : '') : '');
								?></span>
								<?php if (!empty($visible_tabs)): ?>
								<button type="button" class="bm-gs__save-btn" id="bm-save-btn">
									<span class="fas fa-save"></span>
									<span class="bm-gs__save-text"><?php esc_html_e('Save Changes', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								</button>
								<?php endif; ?>
							</div>
							<div class="bm-gs__content">
								<?php 
								foreach ($visible_tabs as $tab_id => $config):
									$section_id = isset($config['section_id']) ? $config['section_id'] : $tab_id;
									$is_license = ($section_id === 'wbtm_license_settings');
								?>
								<div class="bm-gs__tab-panel<?php echo $tab_id === $first_tab ? ' bm-gs--active' : ''; ?>" id="bm-tab-<?php echo esc_attr($tab_id); ?>">
									<?php if ($is_license): ?>
										<div class="bm-gs__section-card">
											<div class="bm-gs__section-head">
												<span class="bm-gs__section-icon fas fa-key"></span>
												<span class="bm-gs__section-head-label"><?php esc_html_e('Mage-People License', 'bus-ticket-booking-with-seat-reservation'); ?></span>
											</div>
											<div class="bm-gs__info-note">
												<p><?php esc_html_e('Some plugins are free and require no license. Additional addons require a valid license key entered below to unlock their features.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
											</div>
											<div class="bm-gs__table-wrap">
												<table class="bm-gs__lic-table">
													<thead><tr>
														<th colspan="4"><?php esc_html_e('Plugin', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th><?php esc_html_e('Type', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th><?php esc_html_e('Order No', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th colspan="2"><?php esc_html_e('Expires', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th colspan="3"><?php esc_html_e('License Key', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
														<th colspan="2"><?php esc_html_e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
													</tr></thead>
													<tbody>
														<?php do_action('wbtm_license_page_plugin_list'); ?>
													</tbody>
												</table>
											</div>
										</div>
									<?php elseif (isset($sections[$section_id]) && isset($fields[$section_id])): ?>
										<form method="post" action="options.php">
											<?php 
												settings_fields($section_id);
												$this->render_section_cards($section_id, $fields[$section_id]);
											?>
											<div style="display:none;"><?php submit_button(); ?></div>
										</form>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			private function get_tab_configs() {
				$label = WBTM_Functions::get_name();
				return [
					'bus' => [
						'title'      => $label . ' ' . esc_html__('Settings', 'bus-ticket-booking-with-seat-reservation'),
						'icon'       => 'fas fa-bus',
						'subtitle'   => esc_html__('General booking behavior', 'bus-ticket-booking-with-seat-reservation'),
						'section_id' => 'wbtm_general_settings',
					],
					'global' => [
						'title'      => esc_html__('General', 'bus-ticket-booking-with-seat-reservation'),
						'icon'       => 'fas fa-sliders-h',
						'subtitle'   => esc_html__('Date & editor settings', 'bus-ticket-booking-with-seat-reservation'),
						'section_id' => 'wbtm_global_settings',
					],
					'deposit' => [
						'title'      => esc_html__('Deposit / Partial Pay', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-wallet',
						'subtitle'   => esc_html__('Partial payment configuration', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_deposit_settings',
					],
					'pdf' => [
						'title'      => esc_html__('PDF Settings', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-file-pdf',
						'subtitle'   => esc_html__('Ticket PDF customization', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_pdf_settings',
					],
					'pdflist' => [
						'title'      => esc_html__('PDF Passenger List', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-list-check',
						'subtitle'   => esc_html__('Column visibility for PDF export', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_passenger_pdf_settings',
					],
					'csv' => [
						'title'      => esc_html__('CSV Settings', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-file-csv',
						'subtitle'   => esc_html__('Column visibility for CSV export', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_passenger_csv_settings',
					],
					'email' => [
						'title'      => esc_html__('Email Settings', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-envelope',
						'subtitle'   => esc_html__('Ticket & notification emails', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_email_settings',
					],
					'chatbot' => [
						'title'      => esc_html__('AI Chatbot', 'addon-bus--ticket-booking-with-seat-pro'),
						'icon'       => 'fas fa-robot',
						'subtitle'   => esc_html__('Chatbot configuration', 'addon-bus--ticket-booking-with-seat-pro'),
						'section_id' => 'wbtm_ai_chatbot_settings',
					],
					'slider' => [
						'title'      => esc_html__('Slider Settings', 'bus-ticket-booking-with-seat-reservation'),
						'icon'       => 'fas fa-images',
						'subtitle'   => esc_html__('Search slider display', 'bus-ticket-booking-with-seat-reservation'),
						'section_id' => 'wbtm_slider_settings',
					],
					'style' => [
						'title'      => esc_html__('Style Settings', 'bus-ticket-booking-with-seat-reservation'),
						'icon'       => 'fas fa-palette',
						'subtitle'   => esc_html__('Colors & typography', 'bus-ticket-booking-with-seat-reservation'),
						'section_id' => 'wbtm_style_settings',
					],
					'license' => [
						'title'      => esc_html__('License', 'bus-ticket-booking-with-seat-reservation'),
						'icon'       => 'fas fa-key',
						'subtitle'   => esc_html__('Plugin license keys', 'bus-ticket-booking-with-seat-reservation'),
						'section_id' => 'wbtm_license_settings',
					],
				];
			}

			private function get_card_groups($section_id) {
				$groups = [
					'wbtm_general_settings' => [
						'booking' => [
							'icon'     => 'fas fa-calendar-check',
							'label'    => esc_html__('Booking behavior', 'bus-ticket-booking-with-seat-reservation'),
							'field_names' => ['set_book_status', 'label', 'slug', 'icon', 'bus_buffer_time'],
						],
						'search' => [
							'icon'     => 'fas fa-search',
							'label'    => esc_html__('Search & display', 'bus-ticket-booking-with-seat-reservation'),
							'field_names' => ['bus_return_show', 'ticket_sale_close_date', 'ticket_sale_max_date', 'show_hide_view_seats_button', 'show_hide_bus_details_tabs', 'next_date_showing_search', 'calendar_soldout_highlight', 'bus_search_list_direction_icon'],
						],
						'checkout' => [
							'icon'     => 'fas fa-shopping-cart',
							'label'    => esc_html__('Checkout & cart', 'bus-ticket-booking-with-seat-reservation'),
							'field_names' => ['active_redirect_page', 'search_page_redirect', 'checkout_redirect_after_booking', 'cart_empty_after_search', 'auto_complete_paid_orders', 'make_processing_completed'],
						],
					],
					'wbtm_global_settings' => [
						'general' => [
							'icon'     => 'fas fa-calendar',
							'label'    => esc_html__('Date & editor', 'bus-ticket-booking-with-seat-reservation'),
							'field_names' => ['disable_block_editor', 'date_format', 'date_format_short'],
						],
					],
					'wbtm_style_settings' => [
						'colors' => [
							'icon'     => 'fas fa-palette',
							'label'    => esc_html__('Colors', 'bus-ticket-booking-with-seat-reservation'),
							'layout'   => 'compact',
							'field_names' => ['theme_color', 'theme_alternate_color', 'default_text_color', 'button_color', 'button_bg', 'warning_color', 'section_bg'],
						],
						'typography' => [
							'icon'     => 'fas fa-text-height',
							'label'    => esc_html__('Typography (px)', 'bus-ticket-booking-with-seat-reservation'),
							'layout'   => 'compact',
							'field_names' => ['default_font_size', 'font_size_h1', 'font_size_h2', 'font_size_h3', 'font_size_h4', 'font_size_h5', 'font_size_h6', 'button_font_size', 'font_size_label'],
						],
					],
					'wbtm_passenger_pdf_settings' => [
						'checklist' => [
							'icon'   => 'fas fa-list-check',
							'label'  => esc_html__('PDF passenger list – column visibility', 'addon-bus--ticket-booking-with-seat-pro'),
							'layout' => 'checklist',
							'field_names' => [], // auto: all fields go into checklist grid
						],
					],
					'wbtm_passenger_csv_settings' => [
						'checklist' => [
							'icon'   => 'fas fa-file-csv',
							'label'  => esc_html__('CSV export – column visibility', 'addon-bus--ticket-booking-with-seat-pro'),
							'layout' => 'checklist',
							'field_names' => [],
						],
					],
				];
				return isset($groups[$section_id]) ? $groups[$section_id] : [];
			}

			private function render_section_cards($section_id, $fields) {
				if (empty($fields)) return;

				$card_groups = $this->get_card_groups($section_id);
				$assigned    = [];

				if (!empty($card_groups)) {
					// Render fields in their defined card groups
					foreach ($card_groups as $card_key => $card) {
						$is_checklist = (isset($card['layout']) && $card['layout'] === 'checklist');

						if ($is_checklist) {
							// Checklist layout: all remaining/unassigned checkbox fields in a grid
							$checklist_fields = [];
							foreach ($fields as $field) {
								$fname = isset($field['name']) ? $field['name'] : '';
								$checklist_fields[] = $field;
								if ($fname) $assigned[] = $fname;
							}
							if (empty($checklist_fields)) continue;
							?>
							<div class="bm-gs__section-card">
								<div class="bm-gs__section-head">
									<span class="bm-gs__section-icon <?php echo esc_attr($card['icon']); ?>"></span>
									<span class="bm-gs__section-head-label"><?php echo esc_html($card['label']); ?></span>
								</div>
								<div class="bm-gs__checklist-grid">
									<?php foreach ($checklist_fields as $field): 
										$this->render_checklist_item($section_id, $field);
									endforeach; ?>
								</div>
							</div>
							<?php
							continue;
						}

						$is_compact = (isset($card['layout']) && $card['layout'] === 'compact');

						$card_fields = [];
						foreach ($card['field_names'] as $fname) {
							foreach ($fields as $field) {
								if (isset($field['name']) && $field['name'] === $fname) {
									$card_fields[] = $field;
									$assigned[]    = $fname;
									break;
								}
							}
						}
						if (empty($card_fields)) continue;
						?>
						<div class="bm-gs__section-card">
							<div class="bm-gs__section-head">
								<span class="bm-gs__section-icon <?php echo esc_attr($card['icon']); ?>"></span>
								<span class="bm-gs__section-head-label"><?php echo esc_html($card['label']); ?></span>
							</div>
							<?php if ($is_compact): ?>
								<div class="bm-gs__compact-grid">
									<?php foreach ($card_fields as $field): 
										$this->render_compact_item($section_id, $field);
									endforeach; ?>
								</div>
							<?php else: ?>
								<?php foreach ($card_fields as $field): 
									$this->render_field_row($section_id, $field);
								endforeach; ?>
							<?php endif; ?>
						</div>
						<?php
					}
				}

				// Render any remaining unassigned fields in their own card(s)
				$remaining = [];
				foreach ($fields as $field) {
					$fname = isset($field['name']) ? $field['name'] : '';
					if ($fname && !in_array($fname, $assigned, true)) {
						$remaining[] = $field;
					} elseif (!$fname) {
						$remaining[] = $field;
					}
				}
				if (!empty($remaining)) {
					// Determine card label from section title
					$sections = $this->settings_api->get_sections();
					$card_label = '';
					if (is_array($sections)) {
						foreach ($sections as $sec) {
							if (isset($sec['id']) && $sec['id'] === $section_id) {
								$card_label = isset($sec['title']) ? $sec['title'] : '';
								break;
							}
						}
					}
					?>
					<div class="bm-gs__section-card">
						<?php if ($card_label): ?>
						<div class="bm-gs__section-head">
							<span class="bm-gs__section-icon fas fa-sliders-h"></span>
							<span class="bm-gs__section-head-label"><?php echo esc_html($card_label); ?></span>
						</div>
						<?php endif; ?>
						<?php foreach ($remaining as $field): 
							$this->render_field_row($section_id, $field);
						endforeach; ?>
					</div>
					<?php
				}
			}

			private function render_field_row($section_id, $field) {
				$label = isset($field['label']) ? $field['label'] : '';
				$desc  = isset($field['desc']) ? $field['desc'] : '';
				$type  = isset($field['type']) ? $field['type'] : 'text';
				$name  = isset($field['name']) ? $field['name'] : '';
				$options = WBTM_Global_Function::get_settings($section_id, $name, isset($field['default']) ? $field['default'] : '');
				$row_class = 'wbtm-field-' . esc_attr($name);

				// Mark provider-specific rows for AI Chatbot toggle
				$is_ai_provider_row = false;
				if ($section_id === 'wbtm_ai_chatbot_settings' && !in_array($name, ['chatbot_enabled','ai_provider','chatbot_name','welcome_message','primary_color','chatbot_position','show_on_pages'], true)) {
					$is_ai_provider_row = true;
					$row_class .= ' wbtm-ai-provider-row';
				}

				if ($type === 'html') {
					echo wp_kses_post(isset($field['html']) ? $field['html'] : '');
					return;
				}
				?>
				<div class="bm-gs__field-row <?php echo $row_class; ?>">
					<div class="bm-gs__field-label-cell">
						<?php if ($label): ?>
							<div class="bm-gs__field-label"><?php echo esc_html($label); ?></div>
						<?php endif; ?>
						<?php if ($desc): ?>
							<div class="bm-gs__field-hint"><?php echo wp_kses_post($desc); ?></div>
						<?php endif; ?>
					</div>
					<div class="bm-gs__field-control-cell">
						<?php $this->render_form_control($section_id, $field, $options); ?>
					</div>
				</div>
				<?php
			}

			private function render_compact_item($section_id, $field) {
				$label   = isset($field['label']) ? $field['label'] : '';
				$name    = isset($field['name']) ? $field['name'] : '';
				$type    = isset($field['type']) ? $field['type'] : 'text';
				$value   = WBTM_Global_Function::get_settings($section_id, $name, isset($field['default']) ? $field['default'] : '');
				$id      = $section_id . '[' . $name . ']';
				$id_attr = esc_attr($id);
				?>
				<div class="bm-gs__compact-item">
					<label class="bm-gs__compact-label"><?php echo esc_html($label); ?></label>
					<?php if ($type === 'color'): ?>
						<input type="text" class="bm-gs__color-field wbtm-color-field" name="<?php echo $id_attr; ?>" value="<?php echo esc_attr($value); ?>" data-default-color="<?php echo esc_attr(isset($field['default']) ? $field['default'] : ''); ?>" style="width:100%;max-width:100%;box-sizing:border-box;">
					<?php elseif ($type === 'number'): ?>
						<input class="bm-gs__input bm-gs__input--sm" type="number" name="<?php echo $id_attr; ?>" value="<?php echo esc_attr($value); ?>" style="width:100%;max-width:100%;">
					<?php else: ?>
						<input class="bm-gs__input" type="text" name="<?php echo $id_attr; ?>" value="<?php echo esc_attr($value); ?>">
					<?php endif; ?>
				</div>
				<?php
			}

			private function render_checklist_item($section_id, $field) {
				$label = isset($field['label']) ? $field['label'] : '';
				$name  = isset($field['name']) ? $field['name'] : '';
				$value = WBTM_Global_Function::get_settings($section_id, $name, isset($field['default']) ? $field['default'] : '');
				$id    = $section_id . '[' . $name . ']';
				$checked = ($value === 'yes' || $value === '1' || $value === 'on') ? ' checked' : '';
				?>
				<label class="bm-gs__checklist-item">
					<input type="checkbox" name="<?php echo esc_attr($id); ?>" value="yes"<?php echo $checked; ?>>
					<span><?php echo esc_html($label); ?></span>
				</label>
				<?php
			}

			private function render_form_control($section_id, $field, $value) {
				$type  = isset($field['type']) ? $field['type'] : 'text';
				$name  = isset($field['name']) ? $field['name'] : '';
				$id    = $section_id . '[' . $name . ']';
				$id_attr = esc_attr($id);
				$class = isset($field['class']) ? $field['class'] : '';
				$placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';

				switch ($type) {
					case 'multicheck':
						$field_options = isset($field['options']) ? $field['options'] : [];
						$value = is_array($value) ? $value : (array) $value;
						echo '<div class="bm-gs__checkbox-group">';
						foreach ($field_options as $opt_key => $opt_label) {
							$checked = in_array($opt_key, $value) ? ' checked' : '';
							echo '<label class="bm-gs__checkbox-item">';
							echo '<input type="checkbox" name="' . esc_attr($id) . '[]" value="' . esc_attr($opt_key) . '"' . $checked . '>';
							echo esc_html($opt_label) . '</label>';
						}
						echo '</div>';
						break;

					case 'select':
					case 'pages':
					case 'wbtm_select2':
					case 'wbtm_select2_role':
						$field_options = isset($field['options']) ? $field['options'] : [];
						if ($type === 'pages') {
							$field_options = [];
							$pages = get_pages();
							foreach ($pages as $page) {
								$field_options[$page->ID] = $page->post_title;
							}
						}
						echo '<select class="bm-gs__select ' . esc_attr($class) . '" name="' . $id_attr . '">';
						foreach ($field_options as $opt_key => $opt_label) {
							$selected = ((string) $opt_key === (string) $value) ? ' selected' : '';
							echo '<option value="' . esc_attr($opt_key) . '"' . $selected . '>' . esc_html($opt_label) . '</option>';
						}
						echo '</select>';
						break;

					case 'text':
					case 'url':
					case 'password':
					case 'email':
						$input_type = ($type === 'password') ? 'password' : (($type === 'email') ? 'email' : (($type === 'url') ? 'url' : 'text'));
						echo '<input class="bm-gs__input ' . esc_attr($class) . '" type="' . esc_attr($input_type) . '" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
						break;

					case 'number':
						echo '<input class="bm-gs__input bm-gs__input--sm ' . esc_attr($class) . '" type="number" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
						break;

					case 'textarea':
						echo '<textarea class="bm-gs__textarea ' . esc_attr($class) . '" name="' . $id_attr . '" placeholder="' . esc_attr($placeholder) . '" rows="4">' . esc_textarea($value) . '</textarea>';
						break;

					case 'color':
						// Standard WP color picker on a single text input. wpColorPicker()
						// builds its own swatch + "Select Color" control on init, so we must
						// NOT also render a custom trigger button (that produced a duplicate picker).
						$default_color = isset($field['default']) ? $field['default'] : '';
						echo '<input type="text" class="bm-gs__color-field wbtm-color-field" name="' . $id_attr . '" value="' . esc_attr($value) . '" data-default-color="' . esc_attr($default_color) . '">';
						break;

					case 'file':
						$file_url = is_string($value) ? $value : (is_array($value) && isset($value['url']) ? $value['url'] : '');
						$file_id  = is_array($value) && isset($value['id']) ? $value['id'] : '';
						echo '<button type="button" class="bm-gs__img-btn wbtm-file-upload-trigger" data-target="' . $id_attr . '">';
						echo '<span class="fas fa-upload"></span> ';
						$file_url ? esc_html_e('Change File', 'bus-ticket-booking-with-seat-reservation') : esc_html_e('Choose File', 'bus-ticket-booking-with-seat-reservation');
						echo '</button>';
						echo '<input type="hidden" class="wbtm-file-url" name="' . $id_attr . '" value="' . esc_attr($file_url) . '">';
						if ($file_url) {
							echo '<span class="bm-gs__badge bm-gs__badge--active" style="margin-left:8px;">' . esc_html(basename($file_url)) . '</span>';
						}
						break;

					case 'wysiwyg':
						echo '<div class="bm-gs__wysiwyg">';
						wp_editor($value, str_replace(['[',']'], ['_',''], $id), [
							'textarea_name' => $id,
							'textarea_rows' => 6,
							'media_buttons' => false,
							'teeny' => true,
						]);
						echo '</div>';
						break;

					case 'datepicker':
						echo '<input class="bm-gs__input wbtm-datepicker ' . esc_attr($class) . '" type="text" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" autocomplete="off">';
						break;

					case 'checkbox':
						$checked = ($value == 'on' || $value == '1') ? ' checked' : '';
						echo '<label class="bm-gs__checkbox-item">';
						echo '<input type="checkbox" name="' . $id_attr . '" value="1"' . $checked . '>';
						echo esc_html(isset($field['checkbox_label']) ? $field['checkbox_label'] : '');
						echo '</label>';
						break;

					case 'radio':
						$field_options = isset($field['options']) ? $field['options'] : [];
						echo '<div class="bm-gs__checkbox-group">';
						foreach ($field_options as $opt_key => $opt_label) {
							$checked = ((string) $opt_key === (string) $value) ? ' checked' : '';
							echo '<label class="bm-gs__checkbox-item">';
							echo '<input type="radio" name="' . $id_attr . '" value="' . esc_attr($opt_key) . '"' . $checked . '>';
							echo esc_html($opt_label) . '</label>';
						}
						echo '</div>';
						break;

					case 'switch_button':
						$checked = ($value == 'on' || $value == '1' || $value == 'yes') ? ' checked' : '';
						echo '<label class="bm-gs__checkbox-item">';
						echo '<input type="checkbox" name="' . $id_attr . '" value="1"' . $checked . '>';
						echo '</label>';
						break;

					case 'icon':
						// Wrap in .wbtm_style so the icon-picker popup & button CSS applies.
						// WBTM_Select_Icon_image outputs classes (_mpBtn_xs, dNone, fdColumn etc.)
						// that are scoped under .wbtm_style in wbtm_plugin_global.css.
						echo '<div class="wbtm_style" style="display:inline-block;">';
						if ( has_action( 'wbtm_input_add_icon' ) ) {
							do_action( 'wbtm_input_add_icon', $id, $value );
						} else {
							echo '<input class="bm-gs__input bm-gs__input--sm" type="text" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="fas fa-bus">';
						}
						echo '</div>';
						break;

					case 'icon_image':
						// Wrap in .wbtm_style so old CSS classes apply
						echo '<div class="wbtm_style" style="display:inline-block;">';
						if ( has_action( 'wbtm_add_icon_image' ) ) {
							$icon  = '';
							$image = '';
							if ( $value && is_numeric( $value ) ) {
								$image = $value;
							} elseif ( $value ) {
								$icon = $value;
							}
							do_action( 'wbtm_add_icon_image', $id, $icon, $image );
						} else {
							echo '<input class="bm-gs__input" type="text" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="fas fa-bus or image URL">';
						}
						echo '</div>';
						break;

					default:
						echo '<input class="bm-gs__input ' . esc_attr($class) . '" type="text" name="' . $id_attr . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
						break;
				}
			}
			public function wbtm_term_and_condition() {
				?>
                <div class="wbtm_style wbtm_global_settings">
                    <div class="mpPanel">
                        <div class="mpPanelHeader"><?php esc_html_e( ' Term And Condition', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
                        <div class="mpPanelBody mp_zero">
                            <?php
                            WBTM_Term_Condition_Setting::term_and_condition_display();
                            ?>
                        </div>
                    </div>
                </div>
				<?php
			}

			public function admin_init() {
				// Only register settings sections/fields on WBTM settings pages to avoid
				// unnecessary DB writes (add_option / register_setting) on every admin page load.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$option_page = isset( $_POST['option_page'] ) ? sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) : '';
				
				$is_wbtm_page = ( $post_type === 'wbtm_bus' || strpos( $page, 'wbtm_' ) === 0 || strpos( $option_page, 'wbtm_' ) === 0 );
				if ( ! $is_wbtm_page ) {
					return;
				}
				$this->settings_api->set_sections( $this->get_settings_sections() );
				$this->settings_api->set_fields( $this->get_settings_fields() );
				$this->settings_api->admin_init();
			}

			public function get_settings_sections() {
				$sections = array();

				return apply_filters( 'wbtm_settings_sec_reg', $sections );
			}

			public function get_settings_fields() {
				$settings_fields = array();

				return apply_filters( 'wbtm_settings_sec_fields', $settings_fields );
			}

			public function settings_sec_reg( $default_sec ): array {
				$label    = WBTM_Functions::get_name();
				$sections = array(
					array(
						'id'    => 'wbtm_general_settings',
						'title' => $label . ' ' . esc_html__( 'Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'wbtm_global_settings',
						'title' => esc_html__( 'Global Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
				);

				return array_merge( $default_sec, $sections );
			}

			public function global_sec_reg( $default_sec ): array {
				$sections = array(
					array(
						'id' => 'wbtm_slider_settings',
						'title' => esc_html__('Slider Settings', 'bus-ticket-booking-with-seat-reservation')
					),
					array(
						'id'    => 'wbtm_style_settings',
						'title' => esc_html__( 'Style Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'wbtm_license_settings',
						'title' => esc_html__( 'Mage-People License', 'bus-ticket-booking-with-seat-reservation' )
					)
				);

				return array_merge( $default_sec, $sections );
			}

			public function settings_sec_fields( $default_fields ): array {
				$label           = WBTM_Functions::get_name();
				$current_date    = current_time( 'Y-m-d' );
				$settings_fields = array(
					'wbtm_general_settings' => apply_filters( 'wbtm_filter_general_settings', array(
						array(
							'name'    => 'set_book_status',
							'label'   => esc_html__( 'Seat Booked Status', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Please Select when and which order status Seat Will be Booked/Reduced.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'multicheck',
							'default' => array(
								'processing' => 'processing',
								'completed'  => 'completed'
							),
							'options' => array(
								'on-hold'    => esc_html__( 'On Hold', 'bus-ticket-booking-with-seat-reservation' ),
								'pending'    => esc_html__( 'Pending', 'bus-ticket-booking-with-seat-reservation' ),
								'processing' => esc_html__( 'Processing', 'bus-ticket-booking-with-seat-reservation' ),
								'completed'  => esc_html__( 'Completed', 'bus-ticket-booking-with-seat-reservation' ),
							)
						),
						array(
							'name'        => 'label',
							'label'       => $label . ' ' . esc_html__( 'Label', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'If you like to change the label in the dashboard menu, you can change it here.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'text',
							'default'     => 'Bus',
							'placeholder' => $label . ' ' . esc_html__( 'Label', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'        => 'slug',
							'label'       => $label . ' ' . esc_html__( 'Slug', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please enter the slug name you want. Remember, after changing this slug; you need to flush permalink; go to', 'bus-ticket-booking-with-seat-reservation' ) . '<strong>' . esc_html__( 'Settings-> Permalinks', 'bus-ticket-booking-with-seat-reservation' ) . '</strong> ' . esc_html__( 'hit the Save Settings button.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'text',
							'default'     => 'bus',
							'placeholder' => $label . ' ' . esc_html__( 'Slug', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'    => 'icon',
							'label'   => $label . ' ' . esc_html__( 'Icon', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Choose a FontAwesome/Dashicons icon or upload an image to use as the dashboard menu icon. Go to ', 'bus-ticket-booking-with-seat-reservation' ) . '<a href=https://developer.wordpress.org/resource/dashicons/#calendar-alt target=_blank>' . esc_html__( 'Dashicons Library', 'bus-ticket-booking-with-seat-reservation' ) . '</a>' . esc_html__( ' to copy an icon code.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'icon_image',
							'default' => 'fas fa-bus'
						),
						array(
							'name'    => 'bus_return_show',
							'label'   => esc_html__( 'Show return Date Search', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Disable if you don\'t want to show return field in search. By default Enable', 'bus-ticket-booking-with-seat-reservation' ),
							'default' => 'enable',
							'type'    => 'select',
							'options' => array(
								'disable' => esc_html__( 'Disable', 'bus-ticket-booking-with-seat-reservation' ),
								'enable'  => esc_html__( 'Enable', 'bus-ticket-booking-with-seat-reservation' ),
							),
						),
						array(
							'name'        => 'ticket_sale_close_date',
							'label'       => esc_html__( 'Ticket sale off after date', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please select Seat sale off after date . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation' ),
							'default'     => '',
							'type'        => 'datepicker',
							'date_format' => 'dd-mm-yy',
							'placeholder' => current_time( 'Y-m-d' ),
						),
						array(
							'name'        => 'ticket_sale_max_date',
							'label'       => esc_html__( 'Maximum advanced day Sale', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please select Maximum advanced day Ticket Sale . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation' ),
							'default'     => '30',
							'type'        => 'number',
							'placeholder' => 30,
						),
						array(
							'name'        => 'bus_buffer_time',
							'label'       => esc_html__( 'Buffer Time', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please enter here car buffer time in minute. By default is 0', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'number',
							'default'     => 0,
							'placeholder' => esc_html__( 'Ex:50', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'    => 'show_hide_view_seats_button',
							'label'   => esc_html__( 'Show/hide view seats button', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to hide view seats button from search list, if registration off.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'show',
							'options' => array(
								'show' => esc_html__( 'Show', 'bus-ticket-booking-with-seat-reservation' ),
								'hide' => esc_html__( 'Hide', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'show_hide_bus_details_tabs',
							'label'   => esc_html__( 'Show/hide bus details tabs', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Show or hide the Bus Details / Boarding-Dropping Points tabs in the search result list.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'show',
							'options' => array(
								'show' => esc_html__( 'Show', 'bus-ticket-booking-with-seat-reservation' ),
								'hide' => esc_html__( 'Hide', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'active_redirect_page',
							'label'   => esc_html__( 'Active Redirect page', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to Active Redirect page,please select on', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'search_page_redirect',
							'label'   => esc_html__( 'Search result page', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to redirect Search result page , please select below page', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'pages',
							'default' => WBTM_Global_Function::get_id_by_slug( 'search-result' ),
						),
						array(
							'name'    => 'make_processing_completed',
							'label'   => esc_html__( 'Turn order status processing to completed automatically', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to make woocommerce processing status to completed automatically select ON', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'auto_complete_paid_orders',
							'label'   => esc_html__( 'Auto Complete Paid Orders', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Automatically mark WooCommerce orders as Completed when a successful payment is received (fixes stuck Pending/Processing orders).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'checkout_redirect_after_booking',
							'label'   => esc_html__( 'Redirect to checkout after booking', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to redirect users directly to checkout after booking instead of showing the cart notice, select ON', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'cart_empty_after_search',
							'label'   => esc_html__( 'Empty cart after new search', 'bus-ticket-booking-with-seat-reservation' ),
                            'desc'  => esc_html__( 'Enable this option to automatically clear the cart whenever a user performs a new search, ensuring only the latest selection is added.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'calendar_soldout_highlight',
							'label'   => esc_html__( 'Highlight sold-out dates in calendar', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Grey-out fully booked dates in the date picker. This checks seat availability for every date in the sales window, so on busy sites it can slow down how fast the calendar and schedules open. Leave OFF if the calendar feels slow.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'bus_search_list_direction_icon',
							'label'   => esc_html__( 'Bus search list direction icon', 'bus-ticket-booking-with-seat-reservation' ),
                            'desc'  => esc_html__( 'Select a FontAwesome icon or upload an image to display as the direction icon in the bus search list.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'icon_image',
							'default' => 'fas fa-bus',
						),
                        array(
                            'name'    => 'next_date_showing_search',
                            'label'   => esc_html__( 'Show next Date In Search Result', 'bus-ticket-booking-with-seat-reservation' ),
                            'desc'    => esc_html__( 'If you want to show next date in search result, please select Yes.', 'bus-ticket-booking-with-seat-reservation' ),
                            'type'    => 'select',
                            'default' => 'no',
                            'options' => array(
                                'yes' => esc_html__( 'Yes', 'bus-ticket-booking-with-seat-reservation' ),
                                'no'  => esc_html__( 'No', 'bus-ticket-booking-with-seat-reservation' )
                            )
                        ),
						
					) ),
					'wbtm_global_settings'  => apply_filters( 'wbtm_filter_global_settings', array(
						array(
							'name'    => 'disable_block_editor',
							'label'   => esc_html__( 'Disable Block/Gutenberg Editor', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to disable WordPress\'s new Block/Gutenberg editor, please select Yes.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'yes',
							'options' => array(
								'yes' => esc_html__( 'Yes', 'bus-ticket-booking-with-seat-reservation' ),
								'no'  => esc_html__( 'No', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'date_format',
							'label'   => esc_html__( 'Date Picker Format', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to change Date Picker Format, please select format. Default  is D d M , yy.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'D d M , yy',
							'options' => array(
								'yy-mm-dd'   => $current_date,
								'yy/mm/dd'   => date_i18n( 'Y/m/d', strtotime( $current_date ) ),
								'yy-dd-mm'   => date_i18n( 'Y-d-m', strtotime( $current_date ) ),
								'yy/dd/mm'   => date_i18n( 'Y/d/m', strtotime( $current_date ) ),
								'dd-mm-yy'   => date_i18n( 'd-m-Y', strtotime( $current_date ) ),
								'dd/mm/yy'   => date_i18n( 'd/m/Y', strtotime( $current_date ) ),
								'mm-dd-yy'   => date_i18n( 'm-d-Y', strtotime( $current_date ) ),
								'mm/dd/yy'   => date_i18n( 'm/d/Y', strtotime( $current_date ) ),
								'd M , yy'   => date_i18n( 'j M , Y', strtotime( $current_date ) ),
								'D d M , yy' => date_i18n( 'D j M , Y', strtotime( $current_date ) ),
								'M d , yy'   => date_i18n( 'M  j, Y', strtotime( $current_date ) ),
								'D M d , yy' => date_i18n( 'D M  j, Y', strtotime( $current_date ) ),
							)
						),
						array(
							'name'    => 'date_format_short',
							'label'   => esc_html__( 'Short Date  Format', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to change Short Date  Format, please select format. Default  is M , Y.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'M , Y',
							'options' => array(
								'D , M d' => date_i18n( 'D , M d', strtotime( $current_date ) ),
								'M , Y'   => date_i18n( 'M , Y', strtotime( $current_date ) ),
								'M , y'   => date_i18n( 'M , y', strtotime( $current_date ) ),
								'M - Y'   => date_i18n( 'M - Y', strtotime( $current_date ) ),
								'M - y'   => date_i18n( 'M - y', strtotime( $current_date ) ),
								'F , Y'   => date_i18n( 'F , Y', strtotime( $current_date ) ),
								'F , y'   => date_i18n( 'F , y', strtotime( $current_date ) ),
								'F - Y'   => date_i18n( 'F - y', strtotime( $current_date ) ),
								'F - y'   => date_i18n( 'F - y', strtotime( $current_date ) ),
								'm - Y'   => date_i18n( 'm - Y', strtotime( $current_date ) ),
								'm - y'   => date_i18n( 'm - y', strtotime( $current_date ) ),
								'm , Y'   => date_i18n( 'm , Y', strtotime( $current_date ) ),
								'm , y'   => date_i18n( 'm , y', strtotime( $current_date ) ),
								'F'       => date_i18n( 'F', strtotime( $current_date ) ),
								'm'       => date_i18n( 'm', strtotime( $current_date ) ),
								'M'       => date_i18n( 'M', strtotime( $current_date ) ),
							)
						),
					) ),
					'wbtm_slider_settings' => array(
						array(
							'name' => 'slider_type',
							'label' => esc_html__('Slider Type', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Type Default Slider', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'slider',
							'options' => array(
								'slider' => esc_html__('Slider', 'bus-ticket-booking-with-seat-reservation'),
								'single_image' => esc_html__('Post Thumbnail', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'slider_style',
							'label' => esc_html__('Slider Style', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Style Default Style One', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'style_1',
							'options' => array(
								'style_1' => esc_html__('Style One', 'bus-ticket-booking-with-seat-reservation'),
								'style_2' => esc_html__('Style Two', 'bus-ticket-booking-with-seat-reservation'),
							)
						),
						array(
							'name' => 'indicator_visible',
							'label' => esc_html__('Slider Indicator Visible?', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Indicator Visible or Not? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'indicator_type',
							'label' => esc_html__('Slider Indicator Type', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Indicator Type Default Icon', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'icon',
							'options' => array(
								'icon' => esc_html__('Icon Indicator', 'bus-ticket-booking-with-seat-reservation'),
								'image' => esc_html__('image Indicator', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'showcase_visible',
							'label' => esc_html__('Slider Showcase Visible?', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Showcase Visible or Not? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'showcase_position',
							'label' => esc_html__('Slider Showcase Position', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Showcase Position Default Right', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'right',
							'options' => array(
								'top' => esc_html__('At Top Position', 'bus-ticket-booking-with-seat-reservation'),
								'right' => esc_html__('At Right Position', 'bus-ticket-booking-with-seat-reservation'),
								'bottom' => esc_html__('At Bottom Position', 'bus-ticket-booking-with-seat-reservation'),
								'left' => esc_html__('At Left Position', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'popup_image_indicator',
							'label' => esc_html__('Slider Popup Image Indicator', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Popup Indicator Image ON or Off? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'popup_icon_indicator',
							'label' => esc_html__('Slider Popup Icon Indicator', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Popup Indicator Icon ON or Off? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						)
					),
					'wbtm_style_settings'   => apply_filters( 'wbtm_filter_style_settings', array(
						array(
							'name'    => 'theme_color',
							'label'   => esc_html__( 'Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#ff4500'
						),
						array(
							'name'    => 'theme_alternate_color',
							'label'   => esc_html__( 'Theme Alternate Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Theme Alternate  Color that means, if background theme color then it will be text color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#fff'
						),
						array(
							'name'    => 'default_text_color',
							'label'   => esc_html__( 'Default Text Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Text  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#303030'
						),
						array(
							'name'    => 'default_font_size',
							'label'   => esc_html__( 'Default Font Size', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Default Font Size(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '15'
						),
						array(
							'name'    => 'font_size_h1',
							'label'   => esc_html__( 'Font Size h1 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Main Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '35'
						),
						array(
							'name'    => 'font_size_h2',
							'label'   => esc_html__( 'Font Size h2 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h2 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '25'
						),
						array(
							'name'    => 'font_size_h3',
							'label'   => esc_html__( 'Font Size h3 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h3 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '22'
						),
						array(
							'name'    => 'font_size_h4',
							'label'   => esc_html__( 'Font Size h4 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h4 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '20'
						),
						array(
							'name'    => 'font_size_h5',
							'label'   => esc_html__( 'Font Size h5 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h5 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'font_size_h6',
							'label'   => esc_html__( 'Font Size h6 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h6 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '16'
						),
						array(
							'name'    => 'button_font_size',
							'label'   => esc_html__( 'Button Font Size ', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Button(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'button_color',
							'label'   => esc_html__( 'Button Text Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Button Text  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#FFF'
						),
						array(
							'name'    => 'button_bg',
							'label'   => esc_html__( 'Button Background Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Button Background  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#222'
						),
						array(
							'name'    => 'font_size_label',
							'label'   => esc_html__( 'Label Font Size ', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Label(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'warning_color',
							'label'   => esc_html__( 'Warning Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Warning  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#E67C30'
						),
						array(
							'name'    => 'section_bg',
							'label'   => esc_html__( 'Section Background color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Background  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#FAFCFE'
						),
					) ),
				);

				return array_merge( $default_fields, $settings_fields );
			}

			public function license_settings() {
				?>
                <div class="wbtm_license_settings">
                    <h3><?php esc_html_e( 'Mage-People License', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
                    <div class="_dFlex">
                        <span class="fas fa-info-circle _mR_xs"></span>
                        <i><?php esc_html_e( 'Thanking you for using our Mage-People plugin. Our some plugin  free and no license is required. We have some Additional addon to enhance feature of this plugin functionality. If you have any addon you need to enter a valid license for that plugin below.', 'bus-ticket-booking-with-seat-reservation' ); ?>                    </i>
                    </div>
                    <div class="divider"></div>
                    <div class="dLayout mp_basic_license_area">
						<?php $this->licence_area(); ?>
                    </div>
                </div>
				<?php
			}

			public function licence_area() {
				?>
                <table>
                    <thead>
                    <tr>
                        <th colspan="4"><?php esc_html_e( 'Plugin Name', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Order No', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="2"><?php esc_html_e( 'Expire on', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="3"><?php esc_html_e( 'License Key', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="2"><?php esc_html_e( 'Action', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
					<?php do_action( 'wbtm_license_page_plugin_list' ); ?>
                    </tbody>
                </table>
				<?php
			}
		}
		new  WBTM_Global_settings();
	}
