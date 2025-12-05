<?php
	/*
	* @Author 		engr.sumonazma@gmail.com
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Settings_Pickup_Point')) {
		class WBTM_Settings_Pickup_Point {
			public function __construct() {
				add_action('wbtm_add_settings_tab_content', [$this, 'tab_content']);
			}
			public function tab_content($post_id) {
				//echo '<pre>'; print_r($pickup_points); echo '</pre>';
				?>
                <div class="tabsItem" data-tabs="#wbtm_settings_pickup_point">
                    <h3><?php esc_html_e('Pickup And Drop-Off Point Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <p><?php esc_html_e('Here you can set bus pick up and drop off point settings.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<?php $this->pickup_point($post_id); ?>
					<?php $this->drop_off_point($post_id); ?>
                </div>
				<?php
			}
			//*********//
			public function pickup_point($post_id) {
				$pickup_points_list = WBTM_Global_Function::get_all_term_data('wbtm_bus_pickpoint');
				$display_pickup_point = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
				$active_pickup_point = $display_pickup_point == 'no' ? '' : 'mActive';
				$checked_pickup_point = $display_pickup_point == 'no' ? '' : 'checked';
				$pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
				$bp_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_bp_stops', []);
				$wbtm_pickup_point_required = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point_required', 'no');
				$checked_wbtm_pickup_point_required = $wbtm_pickup_point_required == 'no' ? '' : 'checked';
				?>
                <div class="">
                    <div class="_dLayout_bgLight">
                        <div class="_dFlex_fdColumn">
                            <label>
								<?php esc_html_e('Pick up settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <span><?php esc_html_e('Here you can set pickup location', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        </div>
                    </div>
                    <div class="_dLayout dFlex _justifyBetween ">
                        <div class="col_10_dFlex_fdColumn">
                            <label>
								<?php esc_html_e('On/Off Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
							<?php WBTM_Settings::info_text('show_pickup_point'); ?>
                        </div>
                        <div class="col_2_dFlex _justifyEnd">
							<?php WBTM_Custom_Layout::switch_button('show_pickup_point', $checked_pickup_point); ?>
                        </div>
                    </div>
                    <div data-collapse="#show_pickup_point" class="<?php echo esc_attr($active_pickup_point); ?>">
						<?php if (sizeof($bp_points) > 0) { ?>
							<?php if (sizeof($pickup_points_list) > 0) { ?>
                                <div class="wbtm_settings_area">
                                    <div class="_dLayout dFlex _justifyBetween ">
                                        <div class="col_10_dFlex_fdColumn">
                                            <label><?php esc_html_e('Boarding point Required?', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                            <span><?php esc_html_e('Turn On or Off Boarding point Required?', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                        </div>
										<?php WBTM_Custom_Layout::switch_button('wbtm_pickup_point_required', $checked_wbtm_pickup_point_required); ?>
                                    </div>
                                    <div class="_dLayout">
                                        <div class="ovAuto">
                                            <table>
                                                <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                    <th colspan="3"><?php esc_html_e('Pickup Info', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                    <th class="_w_100"><?php echo esc_html(WBTM_Translations::text_action()); ?></th>
                                                </tr>
                                                </thead>
                                                <tbody class="wbtm_sortable_area wbtm_item_insert">
												<?php
													if (sizeof($pickup_points) > 0) {
														foreach ($pickup_points as $pickup_point) {
															$this->bp_point_item($pickup_points_list, $bp_points, $pickup_point);
														}
													}
												?>
                                                </tbody>
                                            </table>
                                        </div>
										<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add More Point', 'bus-ticket-booking-with-seat-reservation'), 'wbtm_add_group_pickup'); ?>
                                        <div class="wbtm_hidden_content">
                                            <table>
                                                <tbody class="wbtm_hidden_item">
												<?php $this->bp_point_item($pickup_points_list, $bp_points); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
							<?php } else { ?>
								<?php WBTM_Layout::msg(esc_html__('You have no Pickup Point . Please add Pickup Point and save ,then you can edit pickup point.', 'bus-ticket-booking-with-seat-reservation')); ?>
							<?php } ?>
						<?php } else { ?>
							<?php WBTM_Layout::msg(esc_html__('You have no Bus route . Please add Price and Route and save ,then you can edit pickup point.', 'bus-ticket-booking-with-seat-reservation')); ?>
						<?php } ?>
                    </div>
                </div>
                <div class="_mB"></div>
				<?php
			}
			public function bp_point_item($pickup_points, $bp_points, $pickup_data = []) {
				$pickup_data = $pickup_data ?: array();
				$bp_pickup_point = array_key_exists('bp_point', $pickup_data) ? $pickup_data['bp_point'] : '';
				$pickup_infos = array_key_exists('pickup_info', $pickup_data) ? $pickup_data['pickup_info'] : [];
				if (sizeof($bp_points) > 0 && sizeof($pickup_points) > 0) {
					$unique_name = uniqid();
					?>
                    <tr class="wbtm_remove_area">
                        <td>
                            <label>
                                <input type="hidden" name="wbtm_pickup_unique_id[]" value="<?php echo esc_attr($unique_name); ?>"/>
                                <select name="wbtm_bp_pickup[<?php echo esc_attr($unique_name); ?>]" class='formControl'>
                                    <option selected disabled><?php echo esc_html(WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_bp()); ?></option>
									<?php foreach ($bp_points as $bp_point) { ?>
                                        <option value="<?php echo esc_attr($bp_point); ?>" <?php echo esc_attr($bp_point == $bp_pickup_point ? 'selected' : ''); ?>><?php echo esc_html($bp_point); ?></option>
									<?php } ?>
                                </select>
                            </label>
                        </td>
                        <td colspan="3">
                            <div class="wbtm_settings_area">
                                <table>
                                    <tbody class="wbtm_sortable_area wbtm_item_insert">
									<?php if (sizeof($pickup_infos) > 0) { ?>
										<?php foreach ($pickup_infos as $pickup_info) { ?>
											<?php $this->pickup_point_item($unique_name, $pickup_points, $pickup_info); ?>
										<?php } ?>
									<?php } ?>
                                    </tbody>
                                </table>
								<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add Pickup Point', 'bus-ticket-booking-with-seat-reservation')); ?>
                                <div class="wbtm_hidden_content">
                                    <table>
                                        <tbody class="wbtm_hidden_item">
										<?php $this->pickup_point_item($unique_name, $pickup_points); ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                        <td class="_w_100"><?php WBTM_Custom_Layout::move_remove_button(); ?></td>
                    </tr>
					<?php
				}
			}
			public function pickup_point_item($unique_name, $pickup_points, $pickup_info = []) {
				$bp_point = array_key_exists('pickup_point', $pickup_info) ? $pickup_info['pickup_point'] : '';
				$bp_time = array_key_exists('time', $pickup_info) ? $pickup_info['time'] : '';
				?>
                <tr class="wbtm_remove_area">
                    <td>
                        <label>
                            <select name="wbtm_pickup_name[<?php echo esc_attr($unique_name); ?>][]" class='formControl'>
                                <option selected disabled><?php echo esc_html(WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_pickup_point()); ?></option>
								<?php foreach ($pickup_points as $pickup_point) { ?>
                                    <option value="<?php echo esc_attr($pickup_point); ?>" <?php echo esc_attr(strtolower($pickup_point) == strtolower($bp_point) ? 'selected' : ''); ?>><?php echo esc_html($pickup_point); ?></option>
								<?php } ?>
                            </select>
                        </label>
                    </td>
                    <td>
                        <label class="_mR">
                            <input type="time" name="wbtm_pickup_time[<?php echo esc_attr($unique_name); ?>][]" class='formControl' value="<?php echo esc_attr($bp_time); ?>"/>
                        </label>
                    </td>
                    <td class="_w_100"><?php WBTM_Custom_Layout::move_remove_button(); ?></td>
                </tr>
				<?php
			}
			//*********//
			public function drop_off_point($post_id) {
				$drop_off_points_list = WBTM_Global_Function::get_all_term_data('wbtm_bus_drop_off');
				$display_drop_off_point = WBTM_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
				$active_drop_off_point = $display_drop_off_point == 'no' ? '' : 'mActive';
				$checked_drop_off_point = $display_drop_off_point == 'no' ? '' : 'checked';
				$drop_off_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
				$dp_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_next_stops', []);
				$wbtm_dropping_point_required = WBTM_Global_Function::get_post_info($post_id, 'wbtm_dropping_point_required', 'no');
				$checked_wbtm_dropping_point_required = $wbtm_dropping_point_required == 'no' ? '' : 'checked';
				?>
                <div class="">
                    <div class="_dLayout_bgLight">
                        <div class="_dFlex_fdColumn">
                            <label>
								<?php esc_html_e('Drop-off settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <span><?php esc_html_e('Here you can set drop-off location.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        </div>
                    </div>
                    <div class="_dLayout_dFlex_justifyBetween ">
                        <div class="col_8 _dFlex_fdColumn">
                            <label>
								<?php esc_html_e('Drop-Off Point Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <span>
                            <?php WBTM_Settings::info_text('show_drop_off_point'); ?>
                        </span>
                        </div>
                        <div class="col_2 dFlex _justifyEnd">
							<?php WBTM_Custom_Layout::switch_button('show_drop_off_point', $checked_drop_off_point); ?>
                        </div>
                    </div>
                    <div data-collapse="#show_drop_off_point" class="_dLayout <?php echo esc_attr($active_drop_off_point); ?>">
						<?php if (sizeof($dp_points) > 0) { ?>
							<?php if (sizeof($drop_off_points_list) > 0) { ?>
                                <div class=" wbtm_settings_area">
                                    <div class="_dLayout_dFlex_justifyBetween">
                                        <div class="_dFlex_fdColumn">
                                            <label><?php esc_html_e('Dropping point Required?', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                            <span><?php esc_html_e('Turn On or Off Dropping point Required?', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                        </div>
										<?php WBTM_Custom_Layout::switch_button('wbtm_dropping_point_required', $checked_wbtm_dropping_point_required); ?>
                                    </div>
                                    <div class="_dLayout">
                                        <div class="ovAuto">
                                            <table>
                                                <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                    <th colspan="3"><?php esc_html_e('Drop-Off Info', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                    <th class="_w_100"><?php echo esc_html(WBTM_Translations::text_action()); ?></th>
                                                </tr>
                                                </thead>
                                                <tbody class="wbtm_sortable_area wbtm_item_insert">
												<?php
													if (sizeof($drop_off_points) > 0) {
														foreach ($drop_off_points as $drop_off) {
															$this->dp_point_item($drop_off_points_list, $dp_points, $drop_off);
														}
													}
												?>
                                                </tbody>
                                            </table>
                                        </div>
										<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add More Point', 'bus-ticket-booking-with-seat-reservation'), 'wbtm_add_group_drop_off'); ?>
                                        <div class="wbtm_hidden_content">
                                            <table>
                                                <tbody class="wbtm_hidden_item">
												<?php $this->dp_point_item($drop_off_points_list, $dp_points); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
							<?php } else { ?>
								<?php WBTM_Layout::msg(esc_html__('You have no Drop-Off Point. Please add Drop-Off Point from left sidebar menu.', 'bus-ticket-booking-with-seat-reservation')); ?>
							<?php } ?>
						<?php } else { ?>
							<?php WBTM_Layout::msg(esc_html__('You have no Bus route. Please add Price and Route and save ,then you can edit Drop-Off point.', 'bus-ticket-booking-with-seat-reservation')); ?>
						<?php } ?>
                    </div>
                </div>
				<?php
			}
			public function dp_point_item($drop_off_points, $dp_points, $drop_off_data = []) {
				$drop_off_data = $drop_off_data ?: array();
				$drop_off_point = array_key_exists('dp_point', $drop_off_data) ? $drop_off_data['dp_point'] : '';
				$drop_off_infos = array_key_exists('drop_off_info', $drop_off_data) ? $drop_off_data['drop_off_info'] : [];
				if (sizeof($dp_points) > 0 && sizeof($drop_off_points) > 0) {
					$unique_name = uniqid();
					?>
                    <tr class="wbtm_remove_area">
                        <td>
                            <label>
                                <input type="hidden" name="wbtm_drop_off_unique_id[]" value="<?php echo esc_attr($unique_name); ?>"/>
                                <select name="wbtm_dp_pickup[<?php echo esc_attr($unique_name); ?>]" class='formControl'>
                                    <option selected disabled><?php echo esc_html(WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_dp()); ?></option>
									<?php foreach ($dp_points as $dp_point) { ?>
                                        <option value="<?php echo esc_attr($dp_point); ?>" <?php echo esc_attr($dp_point == $drop_off_point ? 'selected' : ''); ?>><?php echo esc_html($dp_point); ?></option>
									<?php } ?>
                                </select>
                            </label>
                        </td>
                        <td colspan="3">
                            <div class="wbtm_settings_area">
                                <table>
                                    <tbody class="wbtm_sortable_area wbtm_item_insert">
									<?php if (sizeof($drop_off_infos) > 0) { ?>
										<?php foreach ($drop_off_infos as $drop_off_info) { ?>
											<?php $this->drop_off_point_item($unique_name, $drop_off_points, $drop_off_info); ?>
										<?php } ?>
									<?php } ?>
                                    </tbody>
                                </table>
								<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add Drop-Off Point', 'bus-ticket-booking-with-seat-reservation')); ?>
                                <div class="wbtm_hidden_content">
                                    <table>
                                        <tbody class="wbtm_hidden_item">
										<?php $this->drop_off_point_item($unique_name, $drop_off_points); ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                        <td class="_w_100"><?php WBTM_Custom_Layout::move_remove_button(); ?></td>
                    </tr>
					<?php
				}
			}
			public function drop_off_point_item($unique_name, $drop_off_points, $drop_off_info = []) {
				$dp_point = array_key_exists('drop_off_point', $drop_off_info) ? $drop_off_info['drop_off_point'] : '';
				$dp_time = array_key_exists('time', $drop_off_info) ? $drop_off_info['time'] : '';
				?>
                <tr class="wbtm_remove_area">
                    <td>
                        <label>
                            <select name="wbtm_drop_off_name[<?php echo esc_attr($unique_name); ?>][]" class='formControl'>
                                <option selected disabled><?php echo esc_html(WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_drop_off_point()); ?></option>
								<?php foreach ($drop_off_points as $drop_off_point) { ?>
                                    <option value="<?php echo esc_attr($drop_off_point); ?>" <?php echo esc_attr(strtolower($drop_off_point) == strtolower($dp_point) ? 'selected' : ''); ?>><?php echo esc_html($drop_off_point); ?></option>
								<?php } ?>
                            </select>
                        </label>
                    </td>
                    <td>
                        <label class="_mR">
                            <input type="time" name="wbtm_drop_off_time[<?php echo esc_attr($unique_name); ?>][]" class='formControl' value="<?php echo esc_attr($dp_time); ?>"/>
                        </label>
                    </td>
                    <td class="_w_100"><?php WBTM_Custom_Layout::move_remove_button(); ?></td>
                </tr>
				<?php
			}
		}
		new WBTM_Settings_Pickup_Point();
	}