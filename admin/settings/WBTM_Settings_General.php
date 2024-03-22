<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Settings_General')) {
		class WBTM_Settings_General {
			public function __construct() {
				add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_settings_save', [$this, 'settings_save']);
			}
			public function tab_content($post_id) {
				$bus_no = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_no');
				$seat_type = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_category');
				$bus_categories = MP_Global_Function::get_all_term_data('wbtm_bus_cat');
				$display_wbtm_registration = MP_Global_Function::get_post_info($post_id, 'wbtm_registration', 'yes');
				$checked_wbtm_registration = $display_wbtm_registration == 'no' ? '' : 'checked';
				?>
				<div class="tabsItem" data-tabs="#wbtm_general_info">
					<h3 class="pB_xs"><?php esc_html_e('General Information Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					
					<div class="_mp_zero">
						<!-- if bus transporter panel active it will show title field -->
						<?php do_action('wbtm_general_settings_fields',$post_id);?>
						<div class="component _padding dFlex">
							<label class="col_6">
								<?php esc_html_e('Bus no', 'bus-ticket-booking-with-seat-reservation'); ?> 
								<i class="fas fa-question-circle tool-tips"><?php WBTM_Settings::info_text('wbtm_bus_no'); ?></i>
							</label>
							<div class="col_6">
								<input class="formControl mp_name_validation max_300" name="wbtm_bus_no" value="<?php echo esc_attr($bus_no); ?>"/>
							</div>
						</div>
						<div class="component _padding dFlex">
							<label class="col_6">
								<?php echo WBTM_Translations::text_coach_type(); ?>
								<i class="fas fa-question-circle tool-tips"><?php WBTM_Settings::info_text('wbtm_bus_category'); ?></i>
							</label>
							<div class="col_6">
								<select class="formControl max_300" name="wbtm_bus_category" data-collapse-target required>
									<option disabled selected><?php echo WBTM_Translations::text_please_select(); ?></option>
									<?php foreach ($bus_categories as $bus_category) { ?>
										<option value="<?php echo esc_attr($bus_category); ?>" <?php echo esc_attr($bus_category == $seat_type ? 'selected' : ''); ?>><?php echo esc_html($bus_category); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="component _padding_dFlex">
							<label class="col_6">
								<?php esc_html_e('Registration on/off', 'bus-ticket-booking-with-seat-reservation'); ?> 
								<i class="fas fa-question-circle tool-tips"><?php WBTM_Settings::info_text('wbtm_registration'); ?></i>
							</label>
							<div class="col_6">
								<?php MP_Custom_Layout::switch_button('wbtm_registration', $checked_wbtm_registration); ?>
							</div>							
						</div>
						<!--						<div class="_bgColor_2_padding_xs">-->
						<!--							<div class="_max_700_dFlex">-->
						<!--								<span class="_max_300_fs_label">--><?php //esc_html_e('Show Boarding time', 'bus-ticket-booking-with-seat-reservation'); ?><!--</span>-->
						<!--								--><?php //MP_Custom_Layout::switch_button('show_boarding_time', $checked_bp_time); ?>
						<!--							</div>-->
						<!--							--><?php //WBTM_Settings::info_text('show_boarding_time'); ?>
						<!--						</div>-->
						<!--						<div class="_padding_xs">-->
						<!--							<div class="_max_700_dFlex ">-->
						<!--								<span class="_max_300_fs_label">--><?php //esc_html_e('Show Dropping time', 'bus-ticket-booking-with-seat-reservation'); ?><!--</span>-->
						<!--								--><?php //MP_Custom_Layout::switch_button('show_dropping_time', $checked_dp_time); ?>
						<!--							</div>-->
						<!--							--><?php //WBTM_Settings::info_text('show_dropping_time'); ?>
						<!--						</div>-->
					</div>
				</div>
				<?php
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$bus_no = MP_Global_Function::get_submit_info('wbtm_bus_no');
					update_post_meta($post_id, 'wbtm_bus_no', $bus_no);
					$bus_category = MP_Global_Function::get_submit_info('wbtm_bus_category');
					update_post_meta($post_id, 'wbtm_bus_category', $bus_category);
					$wbtm_registration = MP_Global_Function::get_submit_info('wbtm_registration') ? 'yes' : 'no';
					update_post_meta($post_id, 'wbtm_registration', $wbtm_registration);
////					$display_bp_time = MP_Global_Function::get_submit_info('show_boarding_time') ? 'yes' : 'no';
//					update_post_meta($post_id, 'show_boarding_time', $display_bp_time);
//					$display_dp_time = MP_Global_Function::get_submit_info('show_dropping_time') ? 'yes' : 'no';
//					update_post_meta($post_id, 'show_dropping_time', $display_dp_time);
				}
			}
		}
		new WBTM_Settings_General();
	}