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
				$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
				$cabin_count = count($cabin_config);
				$cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');
				$checked_cabin_mode = $cabin_mode_enabled == 'yes' ? 'checked' : '';
				?>
				<div class="tabsItem wbtm_settings_seat" data-tabs="#wbtm_settings_seat">
					<h3><?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					<p><?php esc_html_e('Configure seats for bus/train with support for multiple cabins/coaches.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					
					
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
									<?php esc_html_e('Enable Cabin/Coach Configuration', 'bus-ticket-booking-with-seat-reservation'); ?>
								</label>
								<span><?php esc_html_e('Turn ON to configure multiple cabins/coaches, turn OFF for single bus/train.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
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
										<?php esc_html_e('Enable Rotation', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<span><?php esc_html_e('Enable seat rotation for individual seats', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								</div>
								<?php WBTM_Custom_Layout::switch_button('wbtm_enable_seat_rotation', $checked_rotation); ?>
							</div>
							<div class="divider"></div>

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
				?>
				<tr class="wbtm_remove_area">
					<?php for ($j = 1; $j <= $seat_column; $j++) { ?>
						<?php $key = $seat_key . $j; ?>
						<?php $seat_name = array_key_exists($key, $row_info) ? $row_info[$key] : ''; ?>
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
			$rotation_class = $enable_rotation == 'yes' ? 'wbtm_enable_rotation' : '';
			?>
			<div class="wbtm_cabin_settings_area <?php echo esc_attr($rotation_class); ?>">
				<div class="ovAuto">
					<table>
						<tbody class="wbtm_cabin_item_insert wbtm_sortable_area">
						<?php for ($i = 0; $i < $rows; $i++) { ?>
							<?php $row_info = array_key_exists($i, $seat_infos) ? $seat_infos[$i] : []; ?>
							<?php $this->cabin_seat_plan_row($cols, $cabin_index, $row_info); ?>
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
													   disabled />
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

	public function cabin_seat_plan_row($cols, $cabin_index, $row_info = []) {
		$seat_key_prefix = 'cabin_' . $cabin_index . '_seat';
		$post_id = get_the_ID();
		$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
		?>
		<tr class="wbtm_remove_area">
			<?php for ($j = 1; $j <= $cols; $j++) { ?>
				<?php $key = $seat_key_prefix . $j; ?>
				<?php $seat_name = array_key_exists($key, $row_info) ? $row_info[$key] : ''; ?>
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
					</div>
				</th>
			<?php } ?>
			<th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
		</tr>
		<?php
	}

	public function save_cabin_seat_plans($post_id, $cabin_config) {
		$total_seat = 0;
		$has_enabled_cabin = false;
		$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');

		foreach ($cabin_config as $cabin_index => $cabin) {
			if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;

			$has_enabled_cabin = true;
			$rows = $cabin['rows'] ?? 0;
			$cols = $cabin['cols'] ?? 0;
			$cabin_seat_info = [];

			if ($rows > 0 && $cols > 0) {
				for ($j = 1; $j <= $cols; $j++) {
					$col_infos = WBTM_Global_Function::get_submit_info('wbtm_cabin_' . $cabin_index . '_seat' . $j, null);
					$col_rotation_infos = WBTM_Global_Function::get_submit_info('wbtm_cabin_' . $cabin_index . '_seat' . $j . '_rotation', null);

					// Ensure we have arrays - handle both single values and arrays
					if ($col_infos === null) {
						$col_infos = [];
					} elseif (is_array($col_infos)) {
						$col_infos = array_values($col_infos);
					} else {
						$col_infos = [$col_infos];
					}

					if ($col_rotation_infos === null) {
						$col_rotation_infos = [];
					} elseif (is_array($col_rotation_infos)) {
						$col_rotation_infos = array_values($col_rotation_infos);
					} else {
						$col_rotation_infos = [$col_rotation_infos];
					}

				// Process only the exact number of rows configured (no more, no less)
				// This prevents hidden template rows from being processed and saved
				for ($i = 0; $i < $rows; $i++) {
					// Only process if this row index exists in the submitted data
					if (isset($col_infos[$i])) {
						$seat_value = $col_infos[$i];
						$rotation_value = $col_rotation_infos[$i] ?? '0';

						$cabin_seat_info[$i]['cabin_' . $cabin_index . '_seat' . $j] = $seat_value;
						if ($enable_rotation == 'yes') {
							$cabin_seat_info[$i]['cabin_' . $cabin_index . '_seat' . $j . '_rotation'] = $rotation_value;
						}
						if ($seat_value && $seat_value != 'door' && $seat_value != 'wc') {
							$total_seat++;
						}
					}
				}
				}
			}
			update_post_meta($post_id, 'wbtm_cabin_seats_info_' . $cabin_index, $cabin_seat_info);
		}
		
		// Only update total_seat if there are enabled cabins
		// Otherwise, let the legacy seat plan logic handle it
		if ($has_enabled_cabin) {
			update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
		}
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
							<label><?php printf(esc_html__('Cabin %d Configuration', 'bus-ticket-booking-with-seat-reservation'), $index + 1); ?></label>
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
							<label><?php printf(esc_html__('Cabin %d Preview', 'bus-ticket-booking-with-seat-reservation'), $index + 1); ?></label>
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

			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
		// Save cabin mode enabled status
		$cabin_mode_enabled = WBTM_Global_Function::get_submit_info('wbtm_cabin_mode_enabled') ? 'yes' : 'no';
		update_post_meta($post_id, 'wbtm_cabin_mode_enabled', $cabin_mode_enabled);

		// Save cabin configuration
		$cabin_count = WBTM_Global_Function::get_submit_info('wbtm_cabin_count', 1);
		$cabin_names = WBTM_Global_Function::get_submit_info('wbtm_cabin_name', []);
		$cabin_enabled = WBTM_Global_Function::get_submit_info('wbtm_cabin_enabled', []);
		$cabin_rows = WBTM_Global_Function::get_submit_info('wbtm_cabin_rows', []);
		$cabin_cols = WBTM_Global_Function::get_submit_info('wbtm_cabin_cols', []);
		$cabin_price_multipliers = WBTM_Global_Function::get_submit_info('wbtm_cabin_price_multiplier', []);

			$cabin_config = [];
			for ($i = 0; $i < $cabin_count; $i++) {
				$cabin_config[] = [
					'name' => $cabin_names[$i] ?? 'Cabin ' . ($i + 1),
					'enabled' => isset($cabin_enabled[$i]) ? 'yes' : 'no',
					'rows' => intval($cabin_rows[$i] ?? 0),
					'cols' => intval($cabin_cols[$i] ?? 0),
					'price_multiplier' => floatval($cabin_price_multipliers[$i] ?? 1.0)
				];
			}
			update_post_meta($post_id, 'wbtm_cabin_config', $cabin_config);

			// Save cabin seat plans only when cabin mode is enabled
			if ($cabin_mode_enabled === 'yes') {
				$this->save_cabin_seat_plans($post_id, $cabin_config);
			}

					$seat_type = WBTM_Global_Function::get_submit_info('wbtm_seat_type_conf');
					update_post_meta($post_id, 'wbtm_seat_type_conf', $seat_type);
					
					/***********************/
					$driver_seat_position = WBTM_Global_Function::get_submit_info('driver_seat_position');
					$rows = WBTM_Global_Function::get_submit_info('wbtm_seat_rows_hidden', 0);
					$columns = WBTM_Global_Function::get_submit_info('wbtm_seat_cols_hidden', 0);
					$wbtm_enable_seat_rotation = WBTM_Global_Function::get_submit_info('wbtm_enable_seat_rotation') ? 'yes' : 'no';
					update_post_meta($post_id, 'driver_seat_position', $driver_seat_position);
					update_post_meta($post_id, 'wbtm_seat_rows', $rows);
					update_post_meta($post_id, 'wbtm_seat_cols', $columns);
					update_post_meta($post_id, 'wbtm_enable_seat_rotation', $wbtm_enable_seat_rotation);
					$lower_deck_info = [];
					$total_seat=0;
					if ($rows > 0 && $columns > 0) {
						for ($j = 1; $j <= $columns; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, null);
							$col_rotation_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j . '_rotation', null);

							// Ensure we have arrays - handle both single values and arrays
							if ($col_infos === null) {
								$col_infos = [];
							} elseif (is_array($col_infos)) {
								$col_infos = array_values($col_infos);
							} else {
								$col_infos = [$col_infos];
							}

							if ($col_rotation_infos === null) {
								$col_rotation_infos = [];
							} elseif (is_array($col_rotation_infos)) {
								$col_rotation_infos = array_values($col_rotation_infos);
							} else {
								$col_rotation_infos = [$col_rotation_infos];
							}

							for ($i = 0; $i < $rows; $i++) {
								$seat_value = $col_infos[$i] ?? '';
								$rotation_value = $col_rotation_infos[$i] ?? '0';

								$lower_deck_info[$i]['seat' . $j] = $seat_value;
								if ($wbtm_enable_seat_rotation == 'yes') {
									$lower_deck_info[$i]['seat' . $j . '_rotation'] = $rotation_value;
								}
								if ($seat_value && $seat_value != 'door' && $seat_value != 'wc') {
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
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j, null);
							$col_rotation_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j . '_rotation', null);

							// Ensure we have arrays - handle both single values and arrays
							if ($col_infos === null) {
								$col_infos = [];
							} elseif (is_array($col_infos)) {
								$col_infos = array_values($col_infos);
							} else {
								$col_infos = [$col_infos];
							}

							if ($col_rotation_infos === null) {
								$col_rotation_infos = [];
							} elseif (is_array($col_rotation_infos)) {
								$col_rotation_infos = array_values($col_rotation_infos);
							} else {
								$col_rotation_infos = [$col_rotation_infos];
							}

							for ($i = 0; $i < $rows_dd; $i++) {
								$seat_value = $col_infos[$i] ?? '';
								$rotation_value = $col_rotation_infos[$i] ?? '0';

								$upper_deck_info[$i]['dd_seat' . $j] = $seat_value;
								if ($wbtm_enable_seat_rotation == 'yes') {
									$upper_deck_info[$i]['dd_seat' . $j . '_rotation'] = $rotation_value;
								}
								if ($seat_value && $seat_value != 'door' && $seat_value != 'wc' && $wbtm_show_upper_desk=='yes') {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $upper_deck_info);
					/***********************/
				// Only update total_seat for legacy seat plans
				// Cabin configuration already set the correct total_seat in save_cabin_seat_plans
				$has_cabin_config = $cabin_mode_enabled === 'yes' && !empty($cabin_config) && count(array_filter($cabin_config, function($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;
				
				if (!$has_cabin_config) {
					// Legacy seat plan logic
					$total_seat=$seat_type=='wbtm_seat_plan'?$total_seat:WBTM_Global_Function::get_submit_info('wbtm_get_total_seat', 0);
					update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
				}
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
