<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Tax_Settings')) {
		class WBTM_Tax_Settings {
			public function __construct() {
				add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_settings_save', [$this, 'settings_save']);
			}
			public function tab_content($post_id) {
				?>
				<div class="tabsItem" data-tabs="#wbtm_settings_tax">
					<h3><?php esc_html_e('Tax Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					<p><?php esc_html_e('Bus tax Configuration settings.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<?php
						$tax_status = MP_Global_Function::get_post_info($post_id, '_tax_status');
						$tax_class = MP_Global_Function::get_post_info($post_id, '_tax_class');
						$all_tax_class = MP_Global_Function::all_tax_list();
					?>
					<div class="_dLayout_padding_bgLight">
						<div class="col_6 _dFlex_fdColumn">
							<label>
								<?php esc_html_e('Tax Settings Information', 'bus-ticket-booking-with-seat-reservation'); ?> 
							</label>
							<span><?php esc_html_e('Here you can configure tax settings.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
					</div>
					<?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
						<div class="">
							<div class="_dLayout_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Tax status', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<span>
										<?php esc_html_e('Select tax status type.', 'bus-ticket-booking-with-seat-reservation'); ?>
									</span>
								</div>
								<div class="col_6 textRight">
									<select class="formControl max_300" name="_tax_status">
											<option disabled selected><?php echo WBTM_Translations::text_please_select(); ?></option>
											<option value="taxable" <?php echo esc_attr($tax_status == 'taxable' ? 'selected' : ''); ?>>
												<?php esc_html_e('Taxable', 'bus-ticket-booking-with-seat-reservation'); ?>
											</option>
											<option value="shipping" <?php echo esc_attr($tax_status == 'shipping' ? 'selected' : ''); ?>>
												<?php esc_html_e('Shipping only', 'bus-ticket-booking-with-seat-reservation'); ?>
											</option>
											<option value="none" <?php echo esc_attr($tax_status == 'none' ? 'selected' : ''); ?>>
												<?php esc_html_e('None', 'bus-ticket-booking-with-seat-reservation'); ?>
											</option>
										</select>
								</div>
							</div>

							<div class="_dLayout_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Tax class', 'bus-ticket-booking-with-seat-reservation'); ?>
									</label>
									<?php WBTM_Settings::info_text('tax_class'); ?>
								</div>
								<div class="col_6 textRight">
									<select class="formControl max_300" name="_tax_class">
										<option disabled selected><?php echo WBTM_Translations::text_please_select(); ?></option>
										<option value="standard" <?php echo esc_attr($tax_class == 'standard' ? 'selected' : ''); ?>>
											<?php esc_html_e('Standard', 'bus-ticket-booking-with-seat-reservation'); ?>
										</option>
										<?php if (sizeof($all_tax_class) > 0) { ?>
											<?php foreach ($all_tax_class as $key => $class) { ?>
												<option value="<?php echo esc_attr($key); ?>" <?php echo esc_attr($tax_class == $key ? 'selected' : ''); ?>>
													<?php echo esc_html($class); ?>
												</option>
											<?php } ?>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					<?php }else{ ?>
						<div class="_dLayout_dFlex_justifyCenter">
							<?php WBTM_Layout::msg(esc_html__('Tax not active. Please add Tax settings from woocommerce.', 'bus-ticket-booking-with-seat-reservation')); ?>
						</div>
					<?php } ?>
				</div>
				<?php
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$tax_status = MP_Global_Function::get_submit_info('_tax_status','none');
					$tax_class = MP_Global_Function::get_submit_info('_tax_class');
					update_post_meta($post_id, '_tax_status', $tax_status);
					update_post_meta($post_id, '_tax_class', $tax_class);
				}
			}
		}
		new WBTM_Tax_Settings();
	}