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
			public function __construct() {
				add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_settings_save', [$this, 'settings_save']);
				/*********************/
				add_action('wp_ajax_wbtm_create_seat_plan', [$this, 'wbtm_create_seat_plan']);
				add_action('wp_ajax_nopriv_wbtm_create_seat_plan', [$this, 'wbtm_create_seat_plan']);
				/*********************/
				add_action('wp_ajax_wbtm_create_seat_plan_dd', [$this, 'wbtm_create_seat_plan_dd']);
				add_action('wp_ajax_nopriv_wbtm_create_seat_plan_dd', [$this, 'wbtm_create_seat_plan_dd']);
			}
			public function tab_content($post_id) {
				$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf', 'wbtm_without_seat_plan');
				$total_seat = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);
				?>
				<div class="tabsItem wbtm_settings_seat" data-tabs="#wbtm_settings_seat">
					<h3><?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					<p><?php esc_html_e('Bus seat configuration. Plan your bus seat.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					
					
					<div class="">
						<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter_bgLight">
							<div class="col_6 _dFlex_fdColumn">
								<label>
									<?php esc_html_e('Seat Information', 'bus-ticket-booking-with-seat-reservation'); ?> 
								</label>
								<span><?php esc_html_e('Here you can plan seat of the bus.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							</div>
						</div>
						<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
							<div class="col_6 _dFlex_fdColumn">
								<label>
									<?php esc_html_e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i>
								</label>
								<span><?php WBTM_Settings::info_text('wbtm_seat_type_conf'); ?></span>
							</div>
							<div class="col_6 textRight">
								<select class="formControl max_300" name="wbtm_seat_type_conf" data-collapse-target required>
									<option disabled selected><?php esc_html_e('Please select ...', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="wbtm_seat_plan" data-option-target="#wbtm_seat_plan" <?php echo esc_attr($seat_type == 'wbtm_seat_plan' ? 'selected' : ''); ?>><?php esc_html_e('Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="wbtm_without_seat_plan" data-option-target="#wbtm_without_seat_plan" <?php echo esc_attr($seat_type == 'wbtm_without_seat_plan' ? 'selected' : ''); ?>><?php esc_html_e('Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?></option>
								</select>
							</div>
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
				/***************************/
				$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
				$checked_upper_desk = $show_upper_desk == 'yes' ? 'checked' : '';
				/***************************/
				$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
				$checked_rotation = $enable_rotation == 'yes' ? 'checked' : '';
				/***************************/
				$enable_advanced_features = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_advanced_seat_features');
				$checked_advanced = $enable_advanced_features == 'yes' ? 'checked' : '';
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
							
							<div class="_dFlex_justifyBetween_alignCenter">
								<label class="mp_zero">
									<?php esc_html_e('Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?>
								</label>
								
								<input type="hidden" name="wbtm_seat_rows_hidden" value="<?php echo esc_attr($seat_row); ?>"/>
								<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_rows" placeholder="Ex: 10" value="<?php echo esc_attr($seat_row); ?>"/>
								
							</div>
							<div class="divider"></div>
							<div class="_dFlex_justifyBetween_alignCenter">
								<label class="mp_zero">
									<?php esc_html_e('Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?>
								</label>
								<input type="hidden" name="wbtm_seat_cols_hidden" value="<?php echo esc_attr($seat_column); ?>"/>
								<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_cols" placeholder="Ex: 10" value="<?php echo esc_attr($seat_column); ?>"/>
							</div>
							<div class="divider"></div>
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
							<div class="_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Enable Rotation', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<span><?php esc_html_e('Enable seat rotation for individual seats', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								</div>
								<?php WBTM_Custom_Layout::switch_button('wbtm_enable_seat_rotation', $checked_rotation); ?>
							</div>
							<?php if ( class_exists( 'WBTM_Dependencies_Pro' ) ) { ?>
							<div class="divider"></div>
							<div class="_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Advanced Seat Features', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<span><?php esc_html_e('Enable seat blocking and price override functionality', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								</div>
								<?php WBTM_Custom_Layout::switch_button('wbtm_enable_advanced_seat_features', $checked_advanced); ?>
							</div>
							<?php } ?>
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
								<div class="_dFlex_justifyBetween_alignCenter">
									<label class="mp_zero">
										<?php esc_html_e('Seat Rows : ', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<input type="hidden" name="wbtm_seat_rows_dd_hidden" value="<?php echo esc_attr($seat_row); ?>"/>
									<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_rows_dd" placeholder="Ex: 10" value="<?php echo esc_attr($seat_row); ?>"/>
								</div>
								
								<div class="divider"></div>
								<div class="_dFlex_justifyBetween_alignCenter">
									<label class="flexEqual">
										<?php esc_html_e('Seat Columns : ', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<input type="hidden" name="wbtm_seat_cols_dd_hidden" value="<?php echo esc_attr($seat_column); ?>"/>
									<input type="number" pattern="[0-9]*" step="1" class="formControl max_300 wbtm_number_validation" name="wbtm_seat_cols_dd" placeholder="Ex: 10" value="<?php echo esc_attr($seat_column); ?>"/>
								</div>
								<div class="divider"></div>
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
			public function create_seat_plan($post_id, $seat_row, $seat_column, $dd = false) {
				if ($seat_row > 0 && $seat_column > 0) {
					$info_key = $dd ? 'wbtm_bus_seats_info_dd' : 'wbtm_bus_seats_info';
					$seat_infos = WBTM_Global_Function::get_post_info($post_id, $info_key, []);
					$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
					$rotation_class = $enable_rotation == 'yes' ? 'wbtm_enable_rotation' : '';
					?>
					<div class="wbtm_settings_area <?php echo esc_attr($rotation_class); ?>">
						<div class="ovAuto">
							<table>
								<tbody class="wbtm_item_insert wbtm_sortable_area">
								<?php for ($i = 0; $i < $seat_row; $i++) { ?>
									<?php $row_info = array_key_exists($i, $seat_infos) ? $seat_infos[$i] : []; ?>
									<?php $this->seat_plan_row($seat_column, $dd, $row_info); ?>
								<?php } ?>
								</tbody>
							</table>
						</div>
						<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add New Row', 'bus-ticket-booking-with-seat-reservation')); ?>
						<div class="wbtm_hidden_content">
							<table>
								<tbody class="wbtm_hidden_item">
								<?php $this->seat_plan_row($seat_column, $dd); ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php }
			}
			public function seat_plan_row($seat_column, $dd, $row_info = []) {
				$seat_key = $dd ? 'dd_seat' : 'seat';
				$post_id = get_the_ID();
				$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
				$enable_advanced_features = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_advanced_seat_features');
				?>
				<tr class="wbtm_remove_area">
					<?php for ($j = 1; $j <= $seat_column; $j++) { ?>
						<?php $key = $seat_key . $j; ?>
						<?php $seat_name = array_key_exists($key, $row_info) ? $row_info[$key] : ''; ?>
						<?php $seat_rotation = array_key_exists($key . '_rotation', $row_info) ? $row_info[$key . '_rotation'] : '0'; ?>
						<?php $seat_blocked = array_key_exists($key . '_blocked', $row_info) ? $row_info[$key . '_blocked'] : '0'; ?>
						<?php 
						// Get seat price overrides
						$seat_price_adult = array_key_exists($key . '_price_adult', $row_info) ? $row_info[$key . '_price_adult'] : '';
						$seat_price_child = array_key_exists($key . '_price_child', $row_info) ? $row_info[$key . '_price_child'] : '';
						$seat_price_infant = array_key_exists($key . '_price_infant', $row_info) ? $row_info[$key . '_price_infant'] : '';
						?>
						<th>
							<?php 
							$container_classes = ['wbtm_seat_container'];
							if ($enable_advanced_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) {
								$container_classes[] = 'wbtm_advanced_features_enabled';
							}
							?>
							<div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>">
								<label>
									<input type="text" class="formControl wbtm_id_validation"
										name="wbtm_<?php echo esc_attr($key); ?>[]"
										placeholder="<?php esc_attr_e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?>"
										value="<?php echo esc_attr($seat_name); ?>"
									/>
								</label>
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
											   class="wbtm_rotation_value" />
									</div>
								<?php } ?>
								<?php if ($enable_advanced_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) { ?>
								<div class="wbtm_seat_block_controls">
									<label>
										<input type="checkbox" class="wbtm_block_seat" 
											   name="wbtm_<?php echo esc_attr($key); ?>_blocked[]" 
											   value="1" 
											   <?php echo $seat_blocked == '1' ? 'checked' : ''; ?>
											   title="<?php esc_attr_e('Block this seat', 'bus-ticket-booking-with-seat-reservation'); ?>" />
										<?php esc_html_e('Block', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<input type="hidden" name="wbtm_<?php echo esc_attr($key); ?>_blocked_hidden[]" 
										   value="<?php echo esc_attr($seat_blocked); ?>" 
										   class="wbtm_blocked_value" />
								</div>
								<?php } ?>
								<!-- Seat Price Override Controls -->
								<?php if ($enable_advanced_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) { ?>
								<div class="wbtm_seat_price_controls">
									<div class="wbtm_seat_price_field">
										<input type="number" step="0.01" class="formControl wbtm_price_validation" 
											name="wbtm_<?php echo esc_attr($key); ?>_price_adult[]" 
											placeholder="<?php esc_attr_e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?>"
											value="<?php echo esc_attr($seat_price_adult); ?>" />
									</div>
									<div class="wbtm_seat_price_field">
										<input type="number" step="0.01" class="formControl wbtm_price_validation" 
											name="wbtm_<?php echo esc_attr($key); ?>_price_child[]" 
											placeholder="<?php esc_attr_e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?>"
											value="<?php echo esc_attr($seat_price_child); ?>" />
									</div>
									<div class="wbtm_seat_price_field">
										<input type="number" step="0.01" class="formControl wbtm_price_validation" 
											name="wbtm_<?php echo esc_attr($key); ?>_price_infant[]" 
											placeholder="<?php esc_attr_e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?>"
											value="<?php echo esc_attr($seat_price_infant); ?>" />
									</div>
								</div>
								<?php } ?>
							</div>
						</th>
					<?php } ?>
					<th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
				</tr>
				<?php
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$seat_type = WBTM_Global_Function::get_submit_info('wbtm_seat_type_conf');
					update_post_meta($post_id, 'wbtm_seat_type_conf', $seat_type);
					
					/***********************/
					$driver_seat_position = WBTM_Global_Function::get_submit_info('driver_seat_position');
					$rows = WBTM_Global_Function::get_submit_info('wbtm_seat_rows_hidden', 0);
					$columns = WBTM_Global_Function::get_submit_info('wbtm_seat_cols_hidden', 0);
					$wbtm_enable_seat_rotation = WBTM_Global_Function::get_submit_info('wbtm_enable_seat_rotation') ? 'yes' : 'no';
					$wbtm_enable_advanced_seat_features = WBTM_Global_Function::get_submit_info('wbtm_enable_advanced_seat_features') ? 'yes' : 'no';
					update_post_meta($post_id, 'driver_seat_position', $driver_seat_position);
					update_post_meta($post_id, 'wbtm_seat_rows', $rows);
					update_post_meta($post_id, 'wbtm_seat_cols', $columns);
					update_post_meta($post_id, 'wbtm_enable_seat_rotation', $wbtm_enable_seat_rotation);
					update_post_meta($post_id, 'wbtm_enable_advanced_seat_features', $wbtm_enable_advanced_seat_features);
					$lower_deck_info = [];
					$total_seat=0;
					if ($rows > 0 && $columns > 0) {
						for ($j = 1; $j <= $columns; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, []);
							$col_rotation_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_rotation', []);
							
							// Only process advanced features if enabled and Pro addon is active
							$col_blocked_infos = [];
							$col_price_adult_infos = [];
							$col_price_child_infos = [];
							$col_price_infant_infos = [];
							
							if ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) {
								$col_blocked_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_blocked', []);
								// Get seat price override info
								$col_price_adult_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_price_adult', []);
								$col_price_child_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_price_child', []);
								$col_price_infant_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_price_infant', []);
							}
							for ($i = 0; $i < $rows; $i++) {
								$lower_deck_info[$i]['seat' . $j] = $col_infos[$i];
								if ($wbtm_enable_seat_rotation == 'yes') {
									$lower_deck_info[$i]['seat' . $j . '_rotation'] = isset($col_rotation_infos[$i]) ? $col_rotation_infos[$i] : '0';
								}
								// Save blocked seat info and price override info only if advanced features are enabled and Pro addon is active
								if ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) {
									$lower_deck_info[$i]['seat' . $j . '_blocked'] = isset($col_blocked_infos[$i]) ? $col_blocked_infos[$i] : '0';
									$lower_deck_info[$i]['seat' . $j . '_price_adult'] = isset($col_price_adult_infos[$i]) ? $col_price_adult_infos[$i] : '';
									$lower_deck_info[$i]['seat' . $j . '_price_child'] = isset($col_price_child_infos[$i]) ? $col_price_child_infos[$i] : '';
									$lower_deck_info[$i]['seat' . $j . '_price_infant'] = isset($col_price_infant_infos[$i]) ? $col_price_infant_infos[$i] : '';
								}
								// Count seat only if it's not blocked (when advanced features are enabled and Pro addon is active) or always count (when disabled)
								$is_blocked = ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' ) && isset($col_blocked_infos[$i]) && $col_blocked_infos[$i] == '1');
								if ($col_infos[$i] && $col_infos[$i] != 'door' && $col_infos[$i] != 'wc' && !$is_blocked) {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info', $lower_deck_info);
					/***********************/
					$wbtm_show_upper_desk = WBTM_Global_Function::get_submit_info('wbtm_show_upper_desk') ? 'yes' : 'no';
					$rows_dd = WBTM_Global_Function::get_submit_info('wbtm_seat_rows_dd_hidden', 0);
					$cols_dd = WBTM_Global_Function::get_submit_info('wbtm_seat_cols_dd_hidden', 0);
					$wbtm_seat_dd_price_parcent = WBTM_Global_Function::get_submit_info('wbtm_seat_dd_price_parcent');
					update_post_meta($post_id, 'show_upper_desk', $wbtm_show_upper_desk);
					update_post_meta($post_id, 'wbtm_seat_rows_dd', $rows_dd);
					update_post_meta($post_id, 'wbtm_seat_cols_dd', $cols_dd);
					update_post_meta($post_id, 'wbtm_seat_dd_price_parcent', $wbtm_seat_dd_price_parcent);
					$upper_deck_info = [];
					if ($rows_dd > 0 && $cols_dd > 0) {
						for ($j = 1; $j <= $cols_dd; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j, []);
							$col_rotation_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_rotation', []);
							
							// Only process advanced features if enabled and Pro addon is active
							$col_blocked_infos = [];
							$col_price_adult_infos = [];
							$col_price_child_infos = [];
							$col_price_infant_infos = [];
							
							if ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) {
								$col_blocked_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_blocked', []);
								// Get seat price override info
								$col_price_adult_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_price_adult', []);
								$col_price_child_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_price_child', []);
								$col_price_infant_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_price_infant', []);
							}
							for ($i = 0; $i < $rows_dd; $i++) {
								$upper_deck_info[$i]['dd_seat' . $j] = $col_infos[$i];
								if ($wbtm_enable_seat_rotation == 'yes') {
									$upper_deck_info[$i]['dd_seat' . $j . '_rotation'] = isset($col_rotation_infos[$i]) ? $col_rotation_infos[$i] : '0';
								}
								// Save blocked seat info and price override info only if advanced features are enabled and Pro addon is active
								if ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' )) {
									$upper_deck_info[$i]['dd_seat' . $j . '_blocked'] = isset($col_blocked_infos[$i]) ? $col_blocked_infos[$i] : '0';
									$upper_deck_info[$i]['dd_seat' . $j . '_price_adult'] = isset($col_price_adult_infos[$i]) ? $col_price_adult_infos[$i] : '';
									$upper_deck_info[$i]['dd_seat' . $j . '_price_child'] = isset($col_price_child_infos[$i]) ? $col_price_child_infos[$i] : '';
									$upper_deck_info[$i]['dd_seat' . $j . '_price_infant'] = isset($col_price_infant_infos[$i]) ? $col_price_infant_infos[$i] : '';
								}
								// Count seat only if it's not blocked (when advanced features are enabled and Pro addon is active) or always count (when disabled)
								$is_blocked = ($wbtm_enable_advanced_seat_features == 'yes' && class_exists( 'WBTM_Dependencies_Pro' ) && isset($col_blocked_infos[$i]) && $col_blocked_infos[$i] == '1');
								if ($col_infos[$i] && $col_infos[$i] != 'door' && $col_infos[$i] != 'wc' && $wbtm_show_upper_desk=='yes' && !$is_blocked) {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $upper_deck_info);
					/***********************/
					$total_seat=$seat_type=='wbtm_seat_plan'?$total_seat:WBTM_Global_Function::get_submit_info('wbtm_get_total_seat', 0);
					update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
				}
			}
			/**************************/
			public function wbtm_create_seat_plan() {
				$post_id = WBTM_Global_Function::data_sanitize($_POST['post_id']);
				$row = WBTM_Global_Function::data_sanitize($_POST['row']);
				$column = WBTM_Global_Function::data_sanitize($_POST['column']);
				$this->create_seat_plan($post_id, $row, $column);
				die();
			}
			public function wbtm_create_seat_plan_dd() {
				$post_id = WBTM_Global_Function::data_sanitize($_POST['post_id']);
				$row = WBTM_Global_Function::data_sanitize($_POST['row']);
				$column = WBTM_Global_Function::data_sanitize($_POST['column']);
				$this->create_seat_plan($post_id, $row, $column, true);
				die();
			}
		}
		new WBTM_Seat_Configuration();
	}
?>