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
					<h5><?php esc_html_e('Tax Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
					<div class="divider"></div>
					<?php
						$tax_status = MP_Global_Function::get_post_info($post_id, '_tax_status');
						$tax_class = MP_Global_Function::get_post_info($post_id, '_tax_class');
						$all_tax_class = MP_Global_Function::all_tax_list();
					?>
					<?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
						<div class="_dLayout_xs_mp_zero">
							<div class="_bgColor_2_padding_xs">
								<label class="max_700">
									<span class="max_300"><?php esc_html_e('Tax status', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									<select class="formControl" name="_tax_status">
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
								</label>
							</div>
							<div class="_padding_xs">
								<label class="max_700">
									<span class="max_300"><?php esc_html_e('Tax class', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									<select class="formControl" name="_tax_class">
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
								</label>
								<?php WBTM_Settings::info_text('_tax_class'); ?>
							</div>
						</div>
					<?php }else{ ?>
						<div class="_dLayout_bgWarning_mZero">
							<h3 class="_textCenter"><?php esc_html_e('Tax not Active', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
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