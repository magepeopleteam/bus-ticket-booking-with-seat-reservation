<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Extra_Service')) {
		class WBTM_Extra_Service {
			public function __construct() {
				add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_extra_service_item', array($this, 'extra_service_item'));
				add_action('wbtm_settings_save', [$this, 'settings_save']);
			}
			public function tab_content($post_id) {
				$extra_services = MP_Global_Function::get_post_info($post_id, 'wbtm_extra_services',[]);
				$display_ex = MP_Global_Function::get_post_info($post_id, 'show_extra_service', 'yes');
				$active_ex = $display_ex == 'no' ? '' : 'mActive';
				$checked_ex = $display_ex == 'no' ? '' : 'checked';
				?>
				<div class="tabsItem" data-tabs="#wbtm_settings_ex_service">
					<h3><?php esc_html_e('Extra Services', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					<p><?php esc_html_e('Add Extra Services for the passanger with bus seat reservation', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					
					<div class="">	
						<div class="_dLayout_bgLight_dFlex_justifyBetween">
							<div class="_dFlex_fdColumn">
								<label>
									<?php esc_html_e('Extra service', 'bus-ticket-booking-with-seat-reservation'); ?>
								</label>
								<span><?php esc_html_e('Here you can add extra services. Also can be on/off extra service', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							</div>
						</div>
						<div class="_dLayout_dFlex_justifyBetween">
							<div class="col_10 _dFlex_fdColumn">
								<label>
									<?php esc_html_e('Show/Hide Extra Service', 'bus-ticket-booking-with-seat-reservation'); ?>
								</label>
								<span><?php WBTM_Settings::info_text('show_extra_service'); ?></span>
							</div>
							<div class="col_2 dFlex _justifyEnd">
								<?php MP_Custom_Layout::switch_button('show_extra_service', $checked_ex); ?>
							</div>
						</div>
						<div data-collapse="#show_extra_service" class="<?php echo esc_attr($active_ex); ?>">
							<div class="_dLayout">
								<div class="mp_settings_area">
									<div class="ovAuto">
										<table>
											<thead>
											<tr>
		<!--										<th>--><?php ////esc_html_e('Service Icon', 'bus-ticket-booking-with-seat-reservation'); ?><!--</th>-->
												<th><?php esc_html_e('Service Name', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i></th>
												<th><?php esc_html_e('Service Price', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i></th>
												<th><?php esc_html_e('Available Qty', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i></th>
												<th><?php esc_html_e('Qty Box Type', 'bus-ticket-booking-with-seat-reservation'); ?></th>
												<th><?php esc_html_e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
											</tr>
											</thead>
											<tbody class="mp_sortable_area mp_item_insert">
											<?php
												if (sizeof($extra_services) > 0) {
													foreach ($extra_services as $extra_service) {
														$this->extra_service_item($extra_service);
													}
												}
											?>
											</tbody>
										</table>
									</div>
									<?php MP_Custom_Layout::add_new_button(esc_html__('Add Extra New Service', 'bus-ticket-booking-with-seat-reservation')); ?>
									<?php do_action('add_mp_hidden_table', 'wbtm_extra_service_item'); ?>
								</div>
							</div>
						</div>
						<?php do_action('add_wbtm_extra_service_content',$post_id); ?>
					</div>
				</div>
				<?php
			}
			public function extra_service_item($field = array()) {
				$field = $field ?: array();
				//$service_icon = array_key_exists('option_icon', $field) ? $field['option_icon'] : '';
				$service_name = array_key_exists('option_name', $field) ? $field['option_name'] : '';
				$service_price = array_key_exists('option_price', $field) ? $field['option_price'] : '';
				$service_qty = array_key_exists('option_qty', $field) ? $field['option_qty'] : '';
				$input_type = array_key_exists('option_qty_type', $field) ? $field['option_qty_type'] : 'inputbox';
				?>
				<tr class="mp_remove_area">
<!--					<td>--><?php ////do_action('mp_input_add_icon', 'ex_option_icon[]', $service_icon); ?><!--</td>-->
					<td>
						<label>
							<input type="text" class="formControl mp_name_validation" name="ex_option_name[]" placeholder="Ex: Cap" value="<?php echo esc_attr($service_name); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="ex_option_price[]" placeholder="Ex: 10" value="<?php echo esc_attr($service_price); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<input type="number" pattern="[0-9]*" step="1" class="formControl mp_number_validation" name="ex_option_qty[]" placeholder="Ex: 100" value="<?php echo esc_attr($service_qty); ?>"/>
						</label>
					</td>
					<td>
						<label>
							<select name="ex_option_qty_type[]" class='formControl'>
								<option value="inputbox" <?php echo esc_attr($input_type == 'inputbox' ? 'selected' : ''); ?>><?php esc_html_e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
								<option value="dropdown" <?php echo esc_attr($input_type == 'dropdown' ? 'selected' : ''); ?>><?php esc_html_e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
							</select>
						</label>
					</td>
					<td><?php MP_Custom_Layout::move_remove_button(); ?></td>
				</tr>
				<?php
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$new_extra_service = array();
					//$extra_icon = MP_Global_Function::get_submit_info('ex_option_icon', array());
					$extra_names = MP_Global_Function::get_submit_info('ex_option_name', array());
					$extra_price = MP_Global_Function::get_submit_info('ex_option_price', array());
					$extra_qty = MP_Global_Function::get_submit_info('ex_option_qty', array());
					$extra_qty_type = MP_Global_Function::get_submit_info('ex_option_qty_type', array());
					$extra_count = count($extra_names);
					for ($i = 0; $i < $extra_count; $i++) {
						if ($extra_names[$i] && $extra_price[$i] && $extra_qty[$i] > 0) {
							//$new_extra_service[$i]['option_icon'] = $extra_icon[$i] ?? '';
							$new_extra_service[$i]['option_name'] = $extra_names[$i];
							$new_extra_service[$i]['option_price'] = $extra_price[$i];
							$new_extra_service[$i]['option_qty'] = $extra_qty[$i];
							$new_extra_service[$i]['option_qty_type'] = $extra_qty_type[$i] ?? 'inputbox';
						}
					}
					update_post_meta($post_id, 'wbtm_extra_services', $new_extra_service);
					$display_ex = MP_Global_Function::get_submit_info('show_extra_service') ? 'yes' : 'no';
					update_post_meta($post_id, 'show_extra_service', $display_ex);
				}
			}
		}
		new WBTM_Extra_Service();
	}