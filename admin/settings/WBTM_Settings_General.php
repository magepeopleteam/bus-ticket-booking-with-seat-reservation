<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Settings_General')) {
		class WBTM_Settings_General {
			public function __construct() {
				add_action('wbtm_add_settings_tab_content', [$this, 'tab_content']);
			}
			public function tab_content($post_id) {
				$bus_no = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_no');
				$logo = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_logo');
				$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category');
				$bus_categories = WBTM_Global_Function::get_all_term_data('wbtm_bus_cat');
				$display_wbtm_registration = WBTM_Global_Function::get_post_info($post_id, 'wbtm_registration', 'yes');
				$checked_wbtm_registration = $display_wbtm_registration == 'no' ? '' : 'checked';
				?>
                <div class="tabsItem" data-tabs="#wbtm_general_info">
                    <h3><?php esc_html_e('General Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <p><?php esc_html_e('Bus General Settings', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    <div class="_dLayout_padding_bgLight">
                        <div class="col_6 _dFlex_fdColumn">
                            <label>
								<?php esc_html_e('Bus Information', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <span><?php esc_html_e('Here you can set bus number, category and seat reservation on/off', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        </div>
                    </div>
                    <div class="">
                        <!-- if bus transporter panel active it will show title field -->
						<?php do_action('wbtm_general_settings_fields', $post_id); ?>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Bus Logo', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php _e('Add your logo','bus-ticket-booking-with-seat-reservation') ?></span>
                            </div>
                            <div >
                                <?php
									$image_id = get_post_meta( $post_id, 'wbtm_bus_logo', true );
									do_action( 'wbtm_add_single_image', 'wbtm_bus_logo', $image_id );
								?>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Bus no', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php WBTM_Settings::info_text('wbtm_bus_no'); ?></span>
                            </div>
                            <div class="col_6 textRight">
                                <input class="formControl wbtm_name_validation max_300" name="wbtm_bus_no" value="<?php echo esc_attr($bus_no); ?>"/>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label class="col_6">
									<?php echo esc_html(WBTM_Translations::text_coach_type()); ?>
                                </label>
                                <span><?php WBTM_Settings::info_text('wbtm_bus_category'); ?></span>
                            </div>
                            <div class="col_6 textRight">
                                <select class="formControl max_300" name="wbtm_bus_category" data-collapse-target required>
                                    <option disabled selected><?php echo esc_attr(WBTM_Translations::text_please_select()); ?></option>
									<?php foreach ($bus_categories as $bus_category) { ?>
                                        <option value="<?php echo esc_attr($bus_category); ?>" <?php echo esc_attr($bus_category == $seat_type ? 'selected' : ''); ?>><?php echo esc_html($bus_category); ?></option>
									<?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Reservation on/off', 'bus-ticket-booking-with-seat-reservation'); ?> <i class="fas fa-question-circle tool-tips"><span><?php WBTM_Settings::info_text('wbtm_reservation_tips'); ?></span></i>
                                </label>
                                <span><?php WBTM_Settings::info_text('wbtm_reservation'); ?></span>
                            </div>
                            <div class="col_6 textRight">
								<?php WBTM_Custom_Layout::switch_button('wbtm_registration', $checked_wbtm_registration); ?>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
		}
		new WBTM_Settings_General();
	}