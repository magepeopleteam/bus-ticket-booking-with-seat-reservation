<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Seat_Configuration')) {
		class WBTM_Seat_Configuration {
			private static $non_seat_items = [
				'door'           => ['icon' => 'fa-door-open',       'label' => 'Door'],
				'toilet'         => ['icon' => 'fa-restroom',        'label' => 'Toilet'],
				'wc'             => ['icon' => 'fa-restroom',        'label' => 'Toilet'],
				'driver'         => ['icon' => 'fa-user-tie',        'label' => 'Driver'],
				'window'         => ['icon' => 'fa-window-maximize', 'label' => 'Window'],
				'food_stall'     => ['icon' => 'fa-utensils',        'label' => 'Food Stall'],
				'luggage'        => ['icon' => 'fa-suitcase',        'label' => 'Luggage'],
				'stairs'         => ['icon' => 'fa-level-up-alt',    'label' => 'Stairs'],
				'aisle'          => ['icon' => 'fa-arrows-alt-h',    'label' => 'Aisle'],
				'emergency_exit' => ['icon' => 'fa-sign-out-alt',    'label' => 'Exit'],
			];
			private static function is_non_seat_keyword($value) {
				return isset(self::$non_seat_items[strtolower(trim($value))]);
			}
			public static function is_non_seat_item($value) {
				return self::has_seat_toolbar_features() && self::is_non_seat_keyword($value);
			}
			public static function get_non_seat_item_data($value) {
				if (!self::has_seat_toolbar_features()) {
					return null;
				}
				$key = strtolower(trim($value));
				return self::$non_seat_items[$key] ?? null;
			}
			public static function get_non_seat_keywords() {
				return array_keys(self::$non_seat_items);
			}
			public static function normalize_saved_seat_value($value) {
				$value = trim((string) $value);
				if ($value !== '' && !self::has_seat_toolbar_features() && self::is_non_seat_keyword($value)) {
					return '';
				}
				return $value;
			}
			public static function is_seat_price_override_enabled($post_id) {
				/**
				 * FIX: Add a backward-compatible seat-wise price override feature toggle.
				 * AUTHOR: shahnur alam
				 * ISSUE: #WBTM-SEAT-002
				 * SOLVED: 2026-05-15
				 * CONTEXT: Seat-specific pricing already existed in production, so the new enable/disable control must default to ON for older buses while still allowing admins to turn the feature off explicitly.
				 */
				return self::has_pro_seat_features() && WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_price_override', 'yes') === 'yes';
			}
			/**
			 * Per-seat ticket PRICE OVERRIDE remains a Pro feature — unrelated to
			 * the drag-and-drop toolbar below, kept gated exactly as before.
			 */
			public static function has_pro_seat_features() {
				return class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
			}
			/**
			 * Non-seat toolbar items (Door, Toilet, Driver, Window, Food Stall,
			 * Luggage, Stairs, Aisle, Emergency Exit, Eraser) are a free-plugin
			 * feature — always available, independent of Pro license status.
			 */
			public static function has_seat_toolbar_features() {
				return true;
			}
			public static function get_toolbar_items() {
				$toolbar = [];
				$seen = [];
				foreach (self::$non_seat_items as $keyword => $data) {
					if ($keyword === 'wc') {
						continue;
					}
					if (in_array($data['label'], $seen, true)) {
						continue;
					}
					$toolbar[$keyword] = $data;
					$seen[] = $data['label'];
				}
				return $toolbar;
			}
			/**
			 * Predefined seat templates — a REPEATING COLUMN PATTERN, not a fixed
			 * seat count. Row count stays admin-controlled (the existing "Seat
			 * Rows" field) so one template fits buses of any length; only the
			 * column arrangement (and therefore "Seat Columns") is derived from
			 * the template. 'aisle' cells reuse the existing non-seat toolbar
			 * keyword, so the result is a normal, fully-editable seat grid —
			 * the template is a one-time fill, not a new stored data shape.
			 */
			public static function get_seat_templates() {
				return [
					'2_2' => [
						'label'   => esc_html__('2 + 2 Standard (aisle center)', 'bus-ticket-booking-with-seat-reservation'),
						'pattern' => ['seat', 'seat', 'aisle', 'seat', 'seat'],
					],
					'2_1' => [
						'label'   => esc_html__('2 + 1 Business (aisle right of pair)', 'bus-ticket-booking-with-seat-reservation'),
						'pattern' => ['seat', 'seat', 'aisle', 'seat'],
					],
					'1_2' => [
						'label'   => esc_html__('1 + 2 Business (aisle left of pair)', 'bus-ticket-booking-with-seat-reservation'),
						'pattern' => ['seat', 'aisle', 'seat', 'seat'],
					],
					'1_1' => [
						'label'   => esc_html__('1 + 1 VIP / Sleeper', 'bus-ticket-booking-with-seat-reservation'),
						'pattern' => ['seat', 'aisle', 'seat'],
					],
					'3_2' => [
						'label'   => esc_html__('3 + 2 Large Coach', 'bus-ticket-booking-with-seat-reservation'),
						'pattern' => ['seat', 'seat', 'seat', 'aisle', 'seat', 'seat'],
					],
				];
			}
			/** Seat-numbering schemes offered alongside a template. */
			public static function get_seat_numbering_schemes() {
				return [
					'sequential' => esc_html__('Sequential (1, 2, 3…)', 'bus-ticket-booking-with-seat-reservation'),
					'row_letter' => esc_html__('Row Letter (A1, A2, B1…)', 'bus-ticket-booking-with-seat-reservation'),
				];
			}
			/**
			 * Renders the "Predefined Seat Template" picker (template + numbering
			 * scheme + Apply button). $scope is '' for the lower deck or '_dd' for
			 * the upper deck, so the generated field names/classes target the
			 * right row/column inputs and grid — see wbtm_admin.js applySeatTemplate().
			 */
			public function render_seat_template_picker($scope = '', $seat_row = 0, $seat_column = 0) {
				if (!self::has_seat_toolbar_features()) {
					return;
				}
				$templates = self::get_seat_templates();
				$schemes   = self::get_seat_numbering_schemes();
				$is_dd        = ($scope === '_dd');
				$label_class  = $is_dd ? 'flexEqual' : 'mp_zero';
				$rows_label   = $is_dd ? __('Seat Rows : ', 'bus-ticket-booking-with-seat-reservation') : __('Seat Rows', 'bus-ticket-booking-with-seat-reservation');
				$cols_label   = $is_dd ? __('Seat Columns : ', 'bus-ticket-booking-with-seat-reservation') : __('Seat Columns', 'bus-ticket-booking-with-seat-reservation');
				$aisle_label  = $is_dd ? __('Aisle Position : ', 'bus-ticket-booking-with-seat-reservation') : __('Aisle Position', 'bus-ticket-booking-with-seat-reservation');
				$aisle_title  = __('Choose aisle position after column (Left to Right). 0 = no automatic aisle.', 'bus-ticket-booking-with-seat-reservation');
				?>
				<div class="wbtm_seat_template_picker" data-scope="<?php echo esc_attr($scope); ?>">
					<div class="_dFlex_justifyBetween_alignCenter">
						<div class="col_6 _dFlex_fdColumn">
							<label>
								<?php esc_html_e('Seat Template', 'bus-ticket-booking-with-seat-reservation'); ?>
							</label>
							<span><?php esc_html_e('Generate a complete seat layout in one click, then edit freely as usual.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
						<div class="col_6 textRight">
							<select class="formControl max_300 wbtm_seat_template_select">
								<option value=""><?php esc_html_e('-- No template --', 'bus-ticket-booking-with-seat-reservation'); ?></option>
								<?php foreach ($templates as $key => $tpl) : ?>
									<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($tpl['label']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="divider"></div>
					<div class="_dFlex_justifyBetween_alignCenter">
						<div class="col_6 _dFlex_fdColumn">
							<label>
								<?php esc_html_e('Seat Numbering', 'bus-ticket-booking-with-seat-reservation'); ?>
							</label>
							<span><?php esc_html_e('How seat labels are generated when the template is applied.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
						<div class="col_6 textRight">
							<select class="formControl max_300 wbtm_seat_numbering_select">
								<?php foreach ($schemes as $key => $label) : ?>
									<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="divider"></div>
					<div class="_dFlex_justifyBetween_alignCenter">
						<label class="<?php echo esc_attr($label_class); ?>">
							<?php echo esc_html($rows_label); ?>
						</label>
						<input type="hidden" name="wbtm_seat_rows<?php echo esc_attr($scope); ?>_hidden" value="<?php echo esc_attr($seat_row); ?>"/>
						<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_rows<?php echo esc_attr($scope); ?>" placeholder="Ex: 10" value="<?php echo esc_attr($seat_row); ?>"/>
					</div>
					<div class="divider"></div>
					<div class="_dFlex_justifyBetween_alignCenter">
						<label class="<?php echo esc_attr($label_class); ?>">
							<?php echo esc_html($cols_label); ?>
						</label>
						<input type="hidden" name="wbtm_seat_cols<?php echo esc_attr($scope); ?>_hidden" value="<?php echo esc_attr($seat_column); ?>"/>
						<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_cols<?php echo esc_attr($scope); ?>" placeholder="Ex: 10" value="<?php echo esc_attr($seat_column); ?>"/>
					</div>
					<div class="divider"></div>
					<div class="_dFlex_justifyBetween_alignCenter">
						<label class="<?php echo esc_attr($label_class); ?>" title="<?php echo esc_attr($aisle_title); ?>">
							<?php echo esc_html($aisle_label); ?>
						</label>
						<input type="number" min="0" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_aisle_after_col<?php echo esc_attr($scope); ?>" placeholder="Ex: 2 (0=none)" value="0" title="<?php echo esc_attr($aisle_title); ?>"/>
					</div>
					<div class="divider"></div>
					<button type="button" class="_themeButton_xs_mT_xs wbtm_apply_seat_template">
						<span class="fas fa-magic"></span>
						<span class="mL_xs"><?php esc_html_e('Apply Template', 'bus-ticket-booking-with-seat-reservation'); ?></span>
					</button>
					<div class="divider"></div>
				</div>
				<?php
			}
			/**
			 * FIX: Disable seat-price actions for non-sellable seat-grid items.
			 * AUTHOR: shahnur alam
			 * ISSUE: #WBTM-SEAT-001
			 * SOLVED: 2026-05-15
			 * CONTEXT: Drag-and-drop items like Door, Window, and Aisle are layout markers, not sellable seats, so the per-seat ticket-price button must not appear active for them.
			 */
			private static function render_seat_price_button($scope, $seat_name = '', $cabin_index = null, $is_template = false, $is_feature_enabled = true) {
				$is_non_seat = !$is_template && !empty($seat_name) && self::is_non_seat_item($seat_name);
				$is_disabled = $is_template || !$is_feature_enabled || $is_non_seat;
				$classes = ['button', 'button-small', 'wbtm_seat_price_view'];
				if ($is_disabled) {
					$classes[] = 'wbtm_seat_price_view_disabled';
				}
				if (!$is_feature_enabled) {
					$title = esc_attr__('Enable Seat-wise Price Override to manage custom seat fares', 'bus-ticket-booking-with-seat-reservation');
				} elseif ($is_non_seat) {
					$title = esc_attr__('Price overrides apply only to sellable seats', 'bus-ticket-booking-with-seat-reservation');
				} else {
					$title = esc_attr__('View or override ticket prices for this seat', 'bus-ticket-booking-with-seat-reservation');
				}
				?>
				<button type="button"
						class="<?php echo esc_attr(implode(' ', $classes)); ?>"
						data-override-scope="<?php echo esc_attr($scope); ?>"
						data-seat-price-feature-enabled="<?php echo esc_attr($is_feature_enabled ? '1' : '0'); ?>"
						data-default-title="<?php echo esc_attr__('View or override ticket prices for this seat', 'bus-ticket-booking-with-seat-reservation'); ?>"
						data-disabled-title="<?php echo esc_attr__('Price overrides apply only to sellable seats', 'bus-ticket-booking-with-seat-reservation'); ?>"
						data-feature-disabled-title="<?php echo esc_attr__('Enable Seat-wise Price Override to manage custom seat fares', 'bus-ticket-booking-with-seat-reservation'); ?>"
						<?php if ($cabin_index !== null) { ?>
						data-cabin-index="<?php echo esc_attr($cabin_index); ?>"
						<?php } ?>
						<?php if ($is_template) { ?>
						data-price-view-template="1"
						<?php } ?>
						title="<?php echo esc_attr($title); ?>"
						<?php echo $is_disabled ? 'disabled' : ''; ?>>
					<?php esc_html_e('View', 'bus-ticket-booking-with-seat-reservation'); ?>
				</button>
				<?php
			}
			public static function count_actual_seats($post_id) {
				$total = 0;
				// Fallback stays 'wbtm_without_seat_plan' (not the admin-UI default below) —
				// this feeds live seat-availability/checkout math (WBTM_Woocommerce.php), so
				// any existing bus saved before this field existed must keep its original
				// counting behavior rather than silently switching to seat-grid counting.
				$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf', 'wbtm_without_seat_plan');
				if ($seat_type !== 'wbtm_seat_plan') {
					return (int) WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
				}
				$cabin_mode = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');
				$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
				$has_cabin = $cabin_mode === 'yes' && !empty($cabin_config) && count(array_filter($cabin_config, function ($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;
				if ($has_cabin) {
					foreach ($cabin_config as $index => $cabin) {
						if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;
						$cabin_seats = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $index, []);
						foreach ($cabin_seats as $row) {
							foreach ($row as $key => $val) {
								if (strpos($key, '_rotation') !== false) continue;
								$val = self::normalize_saved_seat_value($val);
								if (!empty($val) && !self::is_non_seat_item($val)) {
									$total++;
								}
							}
						}
					}
				} else {
					$seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
					foreach ($seat_infos as $row) {
						foreach ($row as $key => $val) {
							if (strpos($key, '_rotation') !== false) continue;
							$val = self::normalize_saved_seat_value($val);
							if (!empty($val) && !self::is_non_seat_item($val)) {
								$total++;
							}
						}
					}
					$show_upper = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
					if ($show_upper === 'yes') {
						$upper_seats = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
						foreach ($upper_seats as $row) {
							foreach ($row as $key => $val) {
								if (strpos($key, '_rotation') !== false) continue;
								$val = self::normalize_saved_seat_value($val);
								if (!empty($val) && !self::is_non_seat_item($val)) {
									$total++;
								}
							}
						}
					}
				}
				return $total;
			}
			public function __construct() {
				add_action('wbtm_add_settings_tab_content', [$this, 'tab_content']);
				/*********************/
				add_action('wp_ajax_wbtm_create_seat_plan', [$this, 'wbtm_create_seat_plan']);
				/*********************/
				add_action('wp_ajax_wbtm_create_seat_plan_dd', [$this, 'wbtm_create_seat_plan_dd']);
			}
			public function tab_content($post_id) {
				// Fallback stays 'wbtm_without_seat_plan' — same as count_actual_seats() —
				// so an existing bus that predates this field (no meta saved) keeps
				// rendering exactly as it always has. Only a brand-new, never-published
				// post (auto-draft) is forced to 'wbtm_seat_plan' below, satisfying the
				// "Seat Plan selected by default" request without touching existing buses.
				$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf', 'wbtm_without_seat_plan');
				if (empty(get_post_meta($post_id, 'wbtm_seat_type_conf', true)) && get_post_status($post_id) === 'auto-draft') {
					$seat_type = 'wbtm_seat_plan';
				}
				$total_seat = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
				$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
				$cabin_count = count($cabin_config);
				$cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');
				$checked_cabin_mode = $cabin_mode_enabled == 'yes' ? 'checked' : '';
				$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
				$checked_upper_desk = $show_upper_desk == 'yes' ? 'checked' : '';
				?>
                <div class="tabsItem wbtm_settings_seat" data-tabs="#wbtm_settings_seat">
                    <h3><?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <p><?php esc_html_e('Configure seats for bus/train with support for multiple cabins/coaches.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<?php
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					$wbtm_price_overrides = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_price_overrides', []);
					$has_pro_seat_features = self::has_pro_seat_features();
					if (!is_array($wbtm_price_overrides)) {
						$wbtm_price_overrides = [];
					}
					?>
					<?php if ($has_pro_seat_features) { ?>
						<?php // Textarea avoids HTML attribute size/encoding limits for JSON; value is synced only via JS. ?>
						<textarea name="wbtm_seat_price_overrides" id="wbtm_seat_price_overrides_field" class="wbtm_seat_price_overrides_field" rows="1" cols="40" autocomplete="off" tabindex="-1" aria-hidden="true"><?php echo esc_textarea(!empty($wbtm_price_overrides) ? wp_json_encode($wbtm_price_overrides) : '{}'); ?></textarea>
						<div id="wbtm_seat_price_modal" class="wbtm_seat_price_modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wbtm_seat_price_modal_title">
							<div class="wbtm_seat_price_modal_overlay"></div>
							<div class="wbtm_seat_price_modal_box">
								<div class="wbtm_seat_price_modal_header">
									<h4 id="wbtm_seat_price_modal_title"><?php esc_html_e('Per-seat ticket prices', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
									<button type="button" class="wbtm_seat_price_modal_close" aria-label="<?php esc_attr_e('Close', 'bus-ticket-booking-with-seat-reservation'); ?>">&times;</button>
								</div>
								<p class="wbtm_seat_price_modal_hint"><?php esc_html_e('If you set a specific price, it will override the default route fare. Seat-wise pricing remains fixed and is not affected by route settings. Leaving the field empty will apply the default route fare automatically.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
								<p class="wbtm_seat_price_modal_seat_label"><strong><?php esc_html_e('Seat:', 'bus-ticket-booking-with-seat-reservation'); ?></strong> <span class="wbtm_seat_price_modal_seat_name"></span></p>
								<div class="wbtm_seat_price_modal_body"></div>
								<div class="wbtm_seat_price_modal_footer">
									<button type="button" class="button button-primary wbtm_seat_price_modal_save"><?php esc_html_e('Save', 'bus-ticket-booking-with-seat-reservation'); ?></button>
									<button type="button" class="button wbtm_seat_price_modal_cancel"><?php esc_html_e('Cancel', 'bus-ticket-booking-with-seat-reservation'); ?></button>
								</div>
							</div>
						</div>
					<?php } ?>
                    <div class="">
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter_bgLight">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Vehicle Type Configuration', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php esc_html_e('Configure cabins/coaches for train or multiple deck support for bus.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Enable Multiple Cabin/Coach Configuration', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php esc_html_e('With Enabling this option you can configure multiple cabin option in single booking area, this is mostly needed for Train Ticket where it has multiple cabin.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </div>
                            <div class="col_6 textRight">
								<?php WBTM_Custom_Layout::switch_button('wbtm_cabin_mode_enabled', $checked_cabin_mode); ?>
                            </div>
                        </div>
                        <div class="wbtm_cabin_mode_fields" style="display: <?php echo esc_attr($cabin_mode_enabled == 'yes' ? 'block' : 'none'); ?>;">
                            <div class="divider"></div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e('Number of Cabins/Coaches', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i>
                                    </label>
                                    <span><?php esc_html_e('Enter number of cabins for train or 1 for single bus.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                </div>
                                <div class="col_6 textRight">
                                    <input type="number" min="1" max="20" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_cabin_count" placeholder="Ex: 1" value="<?php echo esc_attr($cabin_count > 0 ? $cabin_count : 1); ?>"/>
                                    <button type="button" class="button button-primary wbtm_configure_cabins"><?php esc_html_e('Configure Cabins', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                </div>
                            </div>
                            <div class="wbtm_cabin_configuration" style="display: <?php echo esc_attr($cabin_count > 0 ? 'block' : 'none'); ?>;">
								<?php $this->render_cabin_configuration($post_id, $cabin_config); ?>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter_bgLight">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Seat Information', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php esc_html_e('Here you can plan seat of the bus/train.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter wbtm_seat_type_selection_header">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Seat Type Selection', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i>
                                </label>
                                <span><?php WBTM_Settings::info_text('wbtm_seat_type_conf'); ?></span>
                            </div>
                        </div>
                        <div class="_dLayout_padding">
                            <div class="wbtm_seat_type_cards">
                                <div class="wbtm_seat_type_card <?php echo esc_attr($seat_type == 'wbtm_seat_plan' ? 'wbtm_seat_type_card_active' : ''); ?>" data-seat-type-card="wbtm_seat_plan" role="button" tabindex="0">
                                    <span class="wbtm_seat_type_card_check"><span class="fas fa-check"></span></span>
                                    <span class="wbtm_seat_type_card_icon"><span class="fas fa-th-large"></span></span>
                                    <span class="wbtm_seat_type_card_title"><?php esc_html_e('Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                    <span class="wbtm_seat_type_card_desc"><?php esc_html_e('Show seat layout and allow customers to select their seats manually during booking.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                </div>
                                <div class="wbtm_seat_type_card <?php echo esc_attr($seat_type == 'wbtm_without_seat_plan' ? 'wbtm_seat_type_card_active' : ''); ?>" data-seat-type-card="wbtm_without_seat_plan" role="button" tabindex="0">
                                    <span class="wbtm_seat_type_card_check"><span class="fas fa-check"></span></span>
                                    <span class="wbtm_seat_type_card_icon"><span class="fas fa-ban"></span></span>
                                    <span class="wbtm_seat_type_card_title"><?php esc_html_e('Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                    <span class="wbtm_seat_type_card_desc"><?php esc_html_e('Do not show seat layout. Customers will not select seats; system assigns automatically.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                </div>
                            </div>
                            <select class="wbtm_seat_type_conf_hidden" name="wbtm_seat_type_conf" data-collapse-target required>
                                <option disabled <?php echo esc_attr($seat_type ? '' : 'selected'); ?>><?php esc_html_e('Please select ...', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="wbtm_seat_plan" data-option-target="#wbtm_seat_plan" <?php echo esc_attr($seat_type == 'wbtm_seat_plan' ? 'selected' : ''); ?>><?php esc_html_e('Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="wbtm_without_seat_plan" data-option-target="#wbtm_without_seat_plan" <?php echo esc_attr($seat_type == 'wbtm_without_seat_plan' ? 'selected' : ''); ?>><?php esc_html_e('Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            </select>
                        </div>
                        <div class="_dLayout_padding <?php echo esc_attr($seat_type == 'wbtm_without_seat_plan' ? 'mActive' : ''); ?>" data-collapse="#wbtm_without_seat_plan">
                            <div class="_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e('Total Seat', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i>
                                    </label>
									<?php WBTM_Settings::info_text('wbtm_get_total_seat'); ?>
                                </div>
                                <div class="col_6 textRight">
                                    <input type="number" min="0" pattern="[0-9]*" step="1" class="formControl wbtm_number_validation max_300" name="wbtm_get_total_seat" placeholder="Ex: 100" value="<?php echo esc_attr($total_seat); ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-collapse="#wbtm_seat_plan" class="_dLayout <?php echo esc_attr($seat_type == 'wbtm_seat_plan' ? 'mActive' : ''); ?>">
                        <div class="_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Show Upper Deck', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php esc_html_e('Turn On or Off upper deck seat plan', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </div>
							<?php WBTM_Custom_Layout::switch_button('wbtm_show_upper_desk', $checked_upper_desk); ?>
                        </div>
                        <div class="divider"></div>
						<?php $this->lower_seat_plan_settings($post_id); ?>
						<?php $this->dd_seat_plan_settings($post_id); ?>
                    </div>
                </div>
				<?php
			}
			public function lower_seat_plan_settings($post_id) {
				$seat_row = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
				$seat_column = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
				$seat_position = WBTM_Global_Function::get_post_info($post_id, 'driver_seat_position', 'driver_left');
				$has_pro_seat_features = self::has_pro_seat_features();
				$enable_seat_price_override = self::is_seat_price_override_enabled($post_id);
				$checked_seat_price_override = $enable_seat_price_override ? 'checked' : '';
				?>
                <div class="mpPanel mT">
                    <div class="_padding_dFlex_justifyBetween_alignCenter_bgLight">
                        <div class="_dFlex_fdColumn">
                            <label><?php esc_html_e('Lower Deck', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <span><?php esc_html_e('Lower deck seat plan', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        </div>
                    </div>
                    <div class="mpPanelBody mp_zero _dFlex">
                        <div class="_dlayout_bR_bgWhite_padding_xs col_6">
                            <?php if ($has_pro_seat_features) { ?>
                            <div class="_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e('Enable Seat-wise Price Override', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <span><?php esc_html_e('Turn on to set a custom fare for individual sellable seats.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                </div>
								<?php WBTM_Custom_Layout::switch_button('wbtm_enable_seat_price_override', $checked_seat_price_override); ?>
                            </div>
                            <div class="divider"></div>
							<?php } ?>
							<div class="_dFlex_justifyBetween_alignCenter">
                                <label class="mp_zero">
									<?php esc_html_e('Driver Position', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <select class="formControl max_300" name="driver_seat_position">
                                    <option disabled selected><?php esc_html_e('Please select ...', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="driver_left" <?php echo esc_attr($seat_position == 'driver_left' ? 'selected' : ''); ?>><?php esc_html_e('Left', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="driver_right" <?php echo esc_attr($seat_position == 'driver_right' ? 'selected' : ''); ?>><?php esc_html_e('Right', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                </select>
                            </div>
                            <div class="divider"></div>
							<?php $this->render_seat_template_picker('', $seat_row, $seat_column); ?>
							<?php WBTM_Custom_Layout::add_new_button(esc_html__('Generate Bus Seat', 'bus-ticket-booking-with-seat-reservation'), 'wbtm_create_seat_plan', '_themeButton_xs_mT_xs'); ?>
                        </div>
                        <div class="wbtm_seat_plan_settings col_6">
							<div class="mB textCenter">
                                <label><?php esc_html_e('Bus Front', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <div class="divider"></div>
                            </div>
                            <div class="wbtm_seat_plan_preview">
								<?php $this->create_seat_plan($post_id, $seat_row, $seat_column); ?>
                            </div>
                            <div class="divider"></div>
                        </div>
                    </div>
                </div>
				<?php
			}
			public function dd_seat_plan_settings($post_id) {
				$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
				$active = $show_upper_desk == 'yes' ? 'mActive' : '';
				//echo '<pre>'; print_r($seat_infos_dd); echo '</pre>';
				$seat_row = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows_dd', 0);
				$seat_column = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols_dd', 0);
				$price_increase = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_dd_price_parcent');
				?>
                <div class="<?php echo esc_attr($active); ?>" data-collapse="#wbtm_show_upper_desk">
                    <div class="mpPanel mT">
                        <div class="_padding_dFlex_justifyBetween_alignCenter_bgLight">
                            <div class="_dFlex_fdColumn">
                                <label><?php esc_html_e('Upper Deck', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <span><?php esc_html_e('You can make Upper Deck seat plan', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </div>
                        </div>
                        <div class="mpPanelBody mp_zero _dFlex">
                            <div class="_bR_bgWhite_padding_xs col_6">
                                <?php $this->render_seat_template_picker('_dd', $seat_row, $seat_column); ?>
                                <div class="_dFlex_justifyBetween_alignCenter">
                                    <label class="flexEqual">
										<?php esc_html_e('Price Increase : ', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_price_validation" name="wbtm_seat_dd_price_parcent" placeholder="Ex: 10" value="<?php echo esc_attr($price_increase); ?>"/>
                                </div>
                                <div class="divider"></div>
								<?php WBTM_Custom_Layout::add_new_button(esc_html__('Create seat Plan', 'bus-ticket-booking-with-seat-reservation'), 'wbtm_create_seat_plan_dd', '_themeButton_xs_mT_xs'); ?>
                            </div>
                            <div class="wbtm_seat_plan_settings col_6">
                                <div class="mB textCenter">
                                    <label><?php esc_html_e('Bus Front', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </div>
                                <div class="divider"></div>
                                <div class="wbtm_seat_plan_preview_dd">
									<?php $this->create_seat_plan($post_id, $seat_row, $seat_column, true); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
			public function render_seat_item_toolbar() {
				if (!self::has_seat_toolbar_features()) {
					return;
				}
				$items = self::get_toolbar_items();
				?>
				<div class="wbtm_seat_toolbar">
					<div class="wbtm_seat_toolbar_label"><?php esc_html_e('Drag items to seat grid:', 'bus-ticket-booking-with-seat-reservation'); ?></div>
					<div class="wbtm_seat_toolbar_items">
						<?php foreach ($items as $keyword => $data) : ?>
							<div class="wbtm_draggable_item" data-item-type="<?php echo esc_attr($keyword); ?>" title="<?php echo esc_attr($data['label']); ?>">
								<span class="fas <?php echo esc_attr($data['icon']); ?>"></span>
								<span class="wbtm_toolbar_item_label"><?php echo esc_html($data['label']); ?></span>
							</div>
						<?php endforeach; ?>
						<div class="wbtm_draggable_item wbtm_draggable_eraser" data-item-type="" title="<?php esc_attr_e('Eraser - clear cell', 'bus-ticket-booking-with-seat-reservation'); ?>">
							<span class="fas fa-eraser"></span>
							<span class="wbtm_toolbar_item_label"><?php esc_html_e('Clear', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
					</div>
				</div>
				<?php
			}
			public function create_seat_plan($post_id, $seat_row, $seat_column, $dd = false) {
				if ($seat_row > 0 && $seat_column > 0) {
					$info_key = $dd ? 'wbtm_bus_seats_info_dd' : 'wbtm_bus_seats_info';
					$seat_infos = WBTM_Global_Function::get_post_info($post_id, $info_key, []);
					// Rotation is enabled independently per deck (lower vs upper),
					// so its toggle can live inline with each deck's own "Add New
					// Row" button instead of one setting shared by both decks.
					$rotation_key = $dd ? 'wbtm_enable_seat_rotation_dd' : 'wbtm_enable_seat_rotation';
					$enable_rotation = WBTM_Global_Function::get_post_info($post_id, $rotation_key);
					$checked_rotation = $enable_rotation == 'yes' ? 'checked' : '';
					$enable_seat_price_override = self::is_seat_price_override_enabled($post_id);
					$rotation_class = $enable_rotation == 'yes' ? 'wbtm_enable_rotation' : '';
					?>
                    <div class="wbtm_settings_area <?php echo esc_attr($rotation_class); ?>">
						<?php $this->render_seat_item_toolbar(); ?>
                        <div class="ovAuto">
                            <table>
                                <tbody class="wbtm_item_insert wbtm_sortable_area">
								<?php for ($i = 0; $i < $seat_row; $i++) { ?>
									<?php $row_info = array_key_exists($i, $seat_infos) ? $seat_infos[$i] : []; ?>
									<?php $this->seat_plan_row($seat_column, $dd, $row_info, $enable_seat_price_override); ?>
								<?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="wbtm_seat_row_actions">
							<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add New Row', 'bus-ticket-booking-with-seat-reservation')); ?>
                            <span class="wbtm_seat_rotation_inline_toggle">
                                <label class="wbtm_seat_rotation_text_toggle">
                                    <input type="checkbox" name="<?php echo esc_attr($rotation_key); ?>" class="wbtm_seat_rotation_checkbox" <?php echo esc_attr($checked_rotation); ?>>
                                    <span class="wbtm_seat_rotation_inline_label">
                                        <span class="fas fa-redo-alt wbtm_seat_rotation_icon"></span>
                                        <span class="wbtm_seat_rotation_text_enable"><?php esc_html_e('Enable Rotation', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                        <span class="wbtm_seat_rotation_text_disable"><?php esc_html_e('Disable Rotation', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                    </span>
                                </label>
                            </span>
                        </div>
                        <div class="wbtm_hidden_content">
                            <table>
                                <tbody class="wbtm_hidden_item">
								<?php $this->seat_plan_row($seat_column, $dd, [], $enable_seat_price_override); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
				<?php }
			}
			public function seat_plan_row($seat_column, $dd, $row_info = [], $enable_seat_price_override = true) {
				$seat_key = $dd ? 'dd_seat' : 'seat';
				$post_id = get_the_ID();
				$rotation_key = $dd ? 'wbtm_enable_seat_rotation_dd' : 'wbtm_enable_seat_rotation';
				$enable_rotation = WBTM_Global_Function::get_post_info($post_id, $rotation_key);
				?>
                <tr class="wbtm_remove_area">
					<?php for ($j = 1; $j <= $seat_column; $j++) { ?>
						<?php $key = $seat_key . $j; ?>
						<?php $seat_name = array_key_exists($key, $row_info) ? self::normalize_saved_seat_value($row_info[$key]) : ''; ?>
						<?php $seat_rotation = array_key_exists($key . '_rotation', $row_info) ? $row_info[$key . '_rotation'] : '0'; ?>
                        <th>
                            <div class="wbtm_seat_container">
                                <label>
                                    <input type="text" class="formControl wbtm_id_validation"
                                           name="wbtm_<?php echo esc_attr($key); ?>[]"
                                           placeholder="<?php esc_attr_e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?>"
                                           value="<?php echo esc_attr($seat_name); ?>"
                                    />
                                </label>
								<?php self::render_seat_price_button($dd ? 'u' : 'l', $seat_name, null, false, $enable_seat_price_override); ?>
								<?php if ($enable_rotation == 'yes') { ?>
                                    <div class="wbtm_seat_rotation_controls">
                                        <button type="button" class="wbtm_rotate_seat _whiteButton_xs"
                                                data-seat-key="<?php echo esc_attr($key); ?>"
                                                data-rotation="<?php echo esc_attr($seat_rotation); ?>"
                                                title="<?php esc_attr_e('Rotate Seat', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                            <span class="fas fa-redo-alt mp_zero"></span>
                                        </button>
                                        <input type="hidden" name="wbtm_<?php echo esc_attr($key); ?>_rotation[]"
                                               value="<?php echo esc_attr($seat_rotation); ?>"
                                               class="wbtm_rotation_value"/>
                                    </div>
								<?php } ?>
                            </div>
                        </th>
					<?php } ?>
                    <th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
                </tr>
				<?php
			}
			public function render_cabin_seat_plan($post_id, $cabin_index, $rows, $cols) {
				if ($rows > 0 && $cols > 0) {
					$seat_key_prefix = 'cabin_' . $cabin_index . '_seat';
					$seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $cabin_index, []);
					$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
					$enable_seat_price_override = self::is_seat_price_override_enabled($post_id);
					$rotation_class = $enable_rotation == 'yes' ? 'wbtm_enable_rotation' : '';
					?>
                    <div class="wbtm_cabin_settings_area <?php echo esc_attr($rotation_class); ?>">
						<?php $this->render_seat_item_toolbar(); ?>
                        <div class="ovAuto">
                            <table>
                                <tbody class="wbtm_cabin_item_insert wbtm_sortable_area">
								<?php for ($i = 0; $i < $rows; $i++) { ?>
									<?php $row_info = array_key_exists($i, $seat_infos) ? $seat_infos[$i] : []; ?>
									<?php $this->cabin_seat_plan_row($cols, $cabin_index, $row_info, $enable_seat_price_override); ?>
								<?php } ?>
                                </tbody>
                            </table>
                        </div>
						<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add New Row', 'bus-ticket-booking-with-seat-reservation')); ?>
                        <div class="wbtm_cabin_hidden_content" style="display: none;">
                            <table>
                                <tbody class="wbtm_cabin_hidden_item">
								<?php
									// Create template row with disabled inputs to prevent form submission
									// Template row is used by JavaScript for adding new rows dynamically
								?>
                                <tr class="wbtm_remove_area wbtm_template_row">
									<?php for ($j = 1; $j <= $cols; $j++) { ?>
										<?php $key = 'cabin_' . $cabin_index . '_seat' . $j; ?>
                                        <th>
                                                                <div class="wbtm_seat_container">
                                                <label>
                                                    <input type="text" class="formControl wbtm_id_validation"
                                                           name="wbtm_template_<?php echo esc_attr($key); ?>[]"
                                                           placeholder="<?php esc_attr_e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?>"
                                                           value=""
                                                           disabled
                                                   />
                                                </label>
												<?php self::render_seat_price_button('c', '', $cabin_index, true, $enable_seat_price_override); ?>
												<?php if ($enable_rotation == 'yes') { ?>
                                                    <div class="wbtm_seat_rotation_controls">
                                                        <button type="button" class="wbtm_rotate_seat _whiteButton_xs"
                                                                data-seat-key="<?php echo esc_attr($key); ?>"
                                                                data-rotation="0"
                                                                title="<?php esc_attr_e('Rotate Seat', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                                            <span class="fas fa-redo-alt mp_zero"></span>
                                                        </button>
                                                        <input type="hidden" name="wbtm_template_<?php echo esc_attr($key); ?>_rotation[]"
                                                               value="0"
                                                               class="wbtm_rotation_value"
                                                               disabled/>
                                                    </div>
												<?php } ?>
                                            </div>
                                        </th>
									<?php } ?>
                                    <th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
					<?php
				}
			}
			public function cabin_seat_plan_row($cols, $cabin_index, $row_info = [], $enable_seat_price_override = true) {
				$seat_key_prefix = 'cabin_' . $cabin_index . '_seat';
				$post_id = get_the_ID();
				$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
				?>
                <tr class="wbtm_remove_area">
					<?php for ($j = 1; $j <= $cols; $j++) { ?>
						<?php $key = $seat_key_prefix . $j; ?>
						<?php $seat_name = array_key_exists($key, $row_info) ? self::normalize_saved_seat_value($row_info[$key]) : ''; ?>
						<?php $seat_rotation = array_key_exists($key . '_rotation', $row_info) ? $row_info[$key . '_rotation'] : '0'; ?>
                        <th>
                            <div class="wbtm_seat_container">
                                <label>
                                    <input type="text" class="formControl wbtm_id_validation"
                                           name="wbtm_<?php echo esc_attr($key); ?>[]"
                                           placeholder="<?php esc_attr_e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?>"
                                           value="<?php echo esc_attr($seat_name); ?>"
                                    />
                                </label>
								<?php self::render_seat_price_button('c', $seat_name, $cabin_index, false, $enable_seat_price_override); ?>
								<?php if ($enable_rotation == 'yes') { ?>
                                    <div class="wbtm_seat_rotation_controls">
                                        <button type="button" class="wbtm_rotate_seat _whiteButton_xs"
                                                data-seat-key="<?php echo esc_attr($key); ?>"
                                                data-rotation="<?php echo esc_attr($seat_rotation); ?>"
                                                title="<?php esc_attr_e('Rotate Seat', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                            <span class="fas fa-redo-alt mp_zero"></span>
                                        </button>
                                        <input type="hidden" name="wbtm_<?php echo esc_attr($key); ?>_rotation[]"
                                               value="<?php echo esc_attr($seat_rotation); ?>"
                                               class="wbtm_rotation_value"/>
                                    </div>
								<?php } ?>
                            </div>
                        </th>
					<?php } ?>
                    <th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
                </tr>
				<?php
			}
			public function render_cabin_configuration($post_id, $cabin_config = []) {
				$cabin_count = count($cabin_config);
				if ($cabin_count == 0) {
					$cabin_config = [
						[
							'name' => 'Cabin 1',
							'rows' => 0,
							'cols' => 0,
							'enabled' => 'yes'
						]
					];
				}
				?>
                <div class="wbtm_cabin_list">
					<?php foreach ($cabin_config as $index => $cabin): ?>
                        <div class="mpPanel wbtm_cabin_item" data-cabin-index="<?php echo esc_attr($index); ?>">
                            <div class="_padding_dFlex_justifyBetween_alignCenter_bgLight">
                                <div class="_dFlex_fdColumn">
                                    <label><?php echo esc_html(sprintf('Cabin %d Configuration', $index + 1)); ?></label>
                                    <span><?php esc_html_e('Configure seat layout for this cabin.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                </div>
                            </div>
                            <div class="mpPanelBody">
                                <div class="_dFlex">
                                    <div class="col_6 _bR">
                                        <div class="_dFlex_justifyBetween_alignCenter">
                                            <label><?php esc_html_e('Cabin Name', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                            <input type="text" class="formControl max_200" name="wbtm_cabin_name[]" placeholder="Ex: First Class" value="<?php echo esc_attr($cabin['name'] ?? 'Cabin ' . ($index + 1)); ?>"/>
                                        </div>
                                        <div class="divider"></div>
                                        <div class="_dFlex_justifyBetween_alignCenter">
                                            <label><?php esc_html_e('Enable Cabin', 'bus-ticket-booking-with-seat-reservation'); ?></label>
											<?php WBTM_Custom_Layout::switch_button('wbtm_cabin_enabled[]', ($cabin['enabled'] ?? 'yes') == 'yes' ? 'checked' : ''); ?>
                                        </div>
                                        <div class="divider"></div>
                                        <!-- Cabin fields that should be hidden when disabled -->
                                        <div class="wbtm_cabin_fields">
                                            <div class="_dFlex_justifyBetween_alignCenter">
                                                <label><?php esc_html_e('Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                                <input type="number" min="0" pattern="[0-9]*" step="1" class="formControl max_200 wbtm_number_validation" name="wbtm_cabin_rows[]" placeholder="Ex: 10" value="<?php echo esc_attr($cabin['rows'] ?? 0); ?>"/>
                                            </div>
                                            <div class="divider"></div>
                                            <div class="_dFlex_justifyBetween_alignCenter">
                                                <label><?php esc_html_e('Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                                <input type="number" min="0" pattern="[0-9]*" step="1" class="formControl max_200 wbtm_number_validation" name="wbtm_cabin_cols[]" placeholder="Ex: 4" value="<?php echo esc_attr($cabin['cols'] ?? 0); ?>"/>
                                            </div>
                                            <div class="divider"></div>
                                            <div class="_dFlex_justifyBetween_alignCenter">
                                                <label><?php esc_html_e('Price Multiplier', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                                <input type="number" min="0" step="0.01" class="formControl max_200" name="wbtm_cabin_price_multiplier[]" placeholder="Ex: 1.0" value="<?php echo esc_attr($cabin['price_multiplier'] ?? 1.0); ?>"/>
                                                <span class="help-text"><?php esc_html_e('1.0 = same price, 1.2 = 20% higher, 0.8 = 20% lower', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                            </div>
                                            <div class="divider"></div>
                                            <button type="button" class="button button-secondary wbtm_generate_cabin_seats" data-cabin-index="<?php echo esc_attr($index); ?>"><?php esc_html_e('Generate Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                        </div>
                                    </div>
                                    <div class="col_6 wbtm_cabin_fields">
                                        <div class="wbtm_cabin_seat_preview" data-cabin-index="<?php echo esc_attr($index); ?>">
                                            <label><?php echo esc_html(sprintf('Cabin %d Preview', $index + 1)); ?></label>
                                            <div class="wbtm_cabin_seat_plan">
												<?php
													$cabin_rows = $cabin['rows'] ?? 0;
													$cabin_cols = $cabin['cols'] ?? 0;
													if ($cabin_rows > 0 && $cabin_cols > 0) {
														$this->render_cabin_seat_plan($post_id, $index, $cabin_rows, $cabin_cols);
													}
												?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
					<?php endforeach; ?>
                </div>
				<?php
			}
			/**************************/
			public function wbtm_create_seat_plan() {
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbtm_admin_nonce' ) ) {
					wp_send_json_error( 'Invalid nonce!' );
				}
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( 'Unauthorized' );
				}
				$post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
				$row     = isset( $_POST['row'] ) ? intval( wp_unslash( $_POST['row'] ) ) : 0;
				$column  = isset( $_POST['column'] ) ? intval( wp_unslash( $_POST['column'] ) ) : 0;
				if ( ! $post_id || ! $row || ! $column ) {
					wp_send_json_error( 'Invalid parameters' );
				}
				$this->create_seat_plan( $post_id, $row, $column );
				die();
			}
			public function wbtm_create_seat_plan_dd() {
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbtm_admin_nonce' ) ) {
					wp_send_json_error( 'Invalid nonce!' );
				}
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( 'Unauthorized' );
				}
				$post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
				$row     = isset( $_POST['row'] ) ? intval( wp_unslash( $_POST['row'] ) ) : 0;
				$column  = isset( $_POST['column'] ) ? intval( wp_unslash( $_POST['column'] ) ) : 0;
				if ( ! $post_id || ! $row || ! $column ) {
					wp_send_json_error( 'Invalid parameters' );
				}
				$this->create_seat_plan( $post_id, $row, $column, true );
				die();
			}
		}
		new WBTM_Seat_Configuration();
	}
