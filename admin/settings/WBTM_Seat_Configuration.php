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
				$advanced_options_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_advanced_seat_options', 'no');
				?>
				<div class="tabsItem wbtm_settings_seat" data-tabs="#wbtm_settings_seat">
					<h3><?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					<p><?php esc_html_e('Bus seat configuration. Plan your bus seat.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					
					
					<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter_bgLight">
						<div class="col_6 _dFlex_fdColumn">
							<label>
								<?php esc_html_e('Enable Advanced Seat Options', 'bus-ticket-booking-with-seat-reservation'); ?>
							</label>
							<span><?php esc_html_e('Show/hide block, type, custom label, seat price, promo, date range for seats.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
						<div class="col_6 textRight">
							<label class="switch">
								<input type="checkbox" id="wbtm_advanced_seat_options" name="wbtm_advanced_seat_options" value="yes" <?php echo ($advanced_options_enabled == 'yes') ? 'checked' : ''; ?> />
								<span class="slider round"></span>
							</label>
						</div>
					</div>
					<script>
					document.addEventListener('DOMContentLoaded', function() {
						var advOpt = document.getElementById('wbtm_advanced_seat_options');
						function toggleAdvancedOptions() {
							var show = advOpt.checked;
							document.body.classList.toggle('wbtm-advanced-seat-options-enabled', show);
						}
						advOpt.addEventListener('change', toggleAdvancedOptions);
						toggleAdvancedOptions();
					});
					</script>
					<style>
					.wbtm-advanced-seat-options-enabled .wbtm_advanced_seat_option { display: block !important; }
					.wbtm_advanced_seat_option { display: none; }
					</style>
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
				$info_key = $dd ? 'wbtm_bus_seats_info_dd' : 'wbtm_bus_seats_info';
				$blocked_key = $dd ? 'wbtm_blocked_seats_dd' : 'wbtm_blocked_seats';
				$type_key = $dd ? 'wbtm_seat_types_dd' : 'wbtm_seat_types';
				$label_key = $dd ? 'wbtm_seat_labels_dd' : 'wbtm_seat_labels';
				$price_key = $dd ? 'wbtm_seat_prices_dd' : 'wbtm_seat_prices';
				$promo_flag_key = $dd ? 'wbtm_seat_promo_flags_dd' : 'wbtm_seat_promo_flags';
				$promo_price_key = $dd ? 'wbtm_seat_promo_prices_dd' : 'wbtm_seat_promo_prices';
				$promo_rules_key = $dd ? 'wbtm_seat_promo_rules_dd' : 'wbtm_seat_promo_rules';
				$seat_infos = WBTM_Global_Function::get_post_info($post_id, $info_key, []);
				$blocked_seats = get_post_meta($post_id, $blocked_key, true);
				if (!is_array($blocked_seats)) $blocked_seats = [];
				$seat_types = get_post_meta($post_id, $type_key, true);
				if (!is_array($seat_types)) $seat_types = [];
				$seat_labels = get_post_meta($post_id, $label_key, true);
				if (!is_array($seat_labels)) $seat_labels = [];
				$seat_prices = get_post_meta($post_id, $price_key, true);
				if (!is_array($seat_prices)) $seat_prices = [];
				$promo_flags = get_post_meta($post_id, $promo_flag_key, true);
				if (!is_array($promo_flags)) $promo_flags = [];
				$promo_prices = get_post_meta($post_id, $promo_price_key, true);
				if (!is_array($promo_prices)) $promo_prices = [];
				$promo_rules = get_post_meta($post_id, $promo_rules_key, true);
				if (!is_array($promo_rules)) $promo_rules = [];
				?>
				<div class=" wbtm_settings_area">
					<div class="ovAuto">
						<table>
							<tbody class="wbtm_item_insert wbtm_sortable_area">
							<?php for ($i = 0; $i < $seat_row; $i++) { ?>
								<?php $row_info = array_key_exists($i, $seat_infos) ? $seat_infos[$i] : []; ?>
								<?php $this->seat_plan_row($seat_column, $dd, $post_id, $row_info, $blocked_seats, $seat_types, $seat_labels, $seat_prices, $promo_flags, $promo_prices, $promo_rules); ?>
							<?php } ?>
							</tbody>
						</table>
					</div>
					<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add New Row', 'bus-ticket-booking-with-seat-reservation')); ?>
					<div class="wbtm_hidden_content">
						<table>
							<tbody class="wbtm_hidden_item">
							<?php $this->seat_plan_row($seat_column, $dd, $post_id, [], $blocked_seats, $seat_types, $seat_labels, $seat_prices, $promo_flags, $promo_prices, $promo_rules); ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php
			}
			public function seat_plan_row($seat_column, $dd, $post_id, $row_info = [], $blocked_seats = [], $seat_types = [], $seat_labels = [], $seat_prices = [], $promo_flags = [], $promo_prices = [], $promo_rules = []) {
				$seat_key = $dd ? 'dd_seat' : 'seat';
				$blocked_key = $dd ? 'wbtm_blocked_seats_dd' : 'wbtm_blocked_seats';
				$type_key = $dd ? 'wbtm_seat_types_dd' : 'wbtm_seat_types';
				$label_key = $dd ? 'wbtm_seat_labels_dd' : 'wbtm_seat_labels';
				$price_key = $dd ? 'wbtm_seat_prices_dd' : 'wbtm_seat_prices';
				$promo_flag_key = $dd ? 'wbtm_seat_promo_flags_dd' : 'wbtm_seat_promo_flags';
				$promo_price_key = $dd ? 'wbtm_seat_promo_prices_dd' : 'wbtm_seat_promo_prices';
				$promo_rules_key = $dd ? 'wbtm_seat_promo_rules_dd' : 'wbtm_seat_promo_rules';
				$seat_type_options = [
					'standard' => __('Standard', 'bus-ticket-booking-with-seat-reservation'),
					'vip' => __('VIP', 'bus-ticket-booking-with-seat-reservation'),
					'economy' => __('Economy', 'bus-ticket-booking-with-seat-reservation'),
					'sleeper' => __('Sleeper', 'bus-ticket-booking-with-seat-reservation'),
				];
				if (empty($blocked_seats) && isset(
					$_GET['post'],
					$_GET['action']) && $_GET['action'] === 'edit') {
					$post_id = intval($_GET['post']);
					$blocked_seats = get_post_meta($post_id, $blocked_key, true);
					if (!is_array($blocked_seats)) $blocked_seats = [];
					$seat_types = get_post_meta($post_id, $type_key, true);
					if (!is_array($seat_types)) $seat_types = [];
					$seat_labels = get_post_meta($post_id, $label_key, true);
					if (!is_array($seat_labels)) $seat_labels = [];
					$seat_prices = get_post_meta($post_id, $price_key, true);
					if (!is_array($seat_prices)) $seat_prices = [];
					$promo_flags = get_post_meta($post_id, $promo_flag_key, true);
					if (!is_array($promo_flags)) $promo_flags = [];
					$promo_prices = get_post_meta($post_id, $promo_price_key, true);
					if (!is_array($promo_prices)) $promo_prices = [];
					$promo_rules = get_post_meta($post_id, $promo_rules_key, true);
					if (!is_array($promo_rules)) $promo_rules = [];
				}
				// Add modern CSS for seat price group (only once, at the top of seat_plan_row)
				if (empty($GLOBALS['wbtm_seat_price_style'])) {
					echo '<style>
					.wbtm_seat_type_prices_modern {
						background: #fff7f2;
						border: 1.5px solid #ff6600;
						border-radius: 10px;
						padding: 10px 12px 8px 12px;
						margin: 8px 0 0 0;
						box-shadow: 0 2px 8px rgba(255,102,0,0.06);
					}
					.wbtm_seat_type_prices_modern .wbtm_type_row_modern {
						display: flex;
						align-items: center;
						margin-bottom: 7px;
						gap: 10px;
					}
					.wbtm_seat_type_prices_modern label {
						font-size: 13px;
						min-width: 60px;
						color: #333;
						margin-bottom: 0;
					}
					.wbtm_seat_type_prices_modern input[type=number] {
						width: 70px;
						border-radius: 6px;
						border: 1px solid #ff6600;
						padding: 3px 7px;
						font-size: 14px;
						margin-right: 4px;
						background: #fff;
						transition: border 0.2s;
					}
					.wbtm_seat_type_prices_modern input[type=number]:focus {
						border: 1.5px solid #ff6600;
						outline: none;
					}
					.wbtm_seat_type_prices_modern .wbtm_promo_check_modern {
						accent-color: #ff6600;
						width: 18px;
						height: 18px;
						margin-right: 2px;
					}
					.wbtm_seat_type_prices_modern .wbtm_promo_label_modern {
						font-size: 12px;
						color: #ff6600;
						font-weight: 500;
						margin-right: 6px;
						margin-left: 2px;
					}
					.wbtm_seat_type_prices_modern .wbtm_percent_modern {
						font-size: 12px;
						color: #888;
						margin-left: 2px;
					}
					</style>';
					$GLOBALS['wbtm_seat_price_style'] = true;
				}
				?>
				<tr class="wbtm_remove_area">
					<?php for ($j = 1; $j <= $seat_column; $j++) { ?>
						<?php $key = $seat_key . $j; ?>
						<?php $seat_name = array_key_exists($key, $row_info) ? $row_info[$key] : ''; ?>
						<th>
							<label>
								<input type="text" class="formControl wbtm_id_validation"
									name="wbtm_<?php echo esc_attr($key); ?>[]"
									placeholder="<?php esc_attr_e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?>"
									value="<?php echo esc_attr($seat_name); ?>"
								/>
							</label>
							<?php if ($seat_name && $seat_name !== 'door' && $seat_name !== 'wc') { ?>
								<div class="wbtm_advanced_seat_option">
									<div class="wbtm_seat_type_prices_modern">
										<div style="margin-top:4px;">
											<label style="font-size:11px;">
												<input type="checkbox" name="<?php echo $blocked_key; ?>[<?php echo esc_attr($seat_name); ?>][]"
													value="1" <?php echo (isset($blocked_seats[$seat_name])) ? 'checked' : ''; ?> />
												<?php esc_html_e('Block', 'bus-ticket-booking-with-seat-reservation'); ?>
											</label>
										</div>
										<div style= "display: flex; gap: 10px;">
										<div style="margin-top:4px;">
											<label style="font-size:11px;">
												<?php esc_html_e('Type:', 'bus-ticket-booking-with-seat-reservation'); ?>
												<select name="<?php echo $type_key; ?>[<?php echo esc_attr($seat_name); ?>]">
													<option value="">-</option>
													<?php foreach ($seat_type_options as $type_val => $type_label) { ?>
														<option value="<?php echo esc_attr($type_val); ?>" <?php echo (isset($seat_types[$seat_name]) && $seat_types[$seat_name] == $type_val) ? 'selected' : ''; ?>><?php echo esc_html($type_label); ?></option>
													<?php } ?>
												</select>
											</label>
										</div>
										<div style="margin-top:4px;">
											<label style="font-size:11px;">
												<?php esc_html_e('Label:', 'bus-ticket-booking-with-seat-reservation'); ?>
												<input type="text" name="<?php echo $label_key; ?>[<?php echo esc_attr($seat_name); ?>]" value="<?php echo isset($seat_labels[$seat_name]) ? esc_attr($seat_labels[$seat_name]) : ''; ?>" placeholder="e.g. Window, Reserved for Women" style="width:100%; font-size:11px;" />
											</label>
										</div>
										</div>
									</div>
									<div style="margin-top:4px;">
										<?php echo '<div class="wbtm_seat_type_prices_modern">';
										foreach ([0 => 'Adult', 1 => 'Child', 2 => 'Infant'] as $type => $type_label) {
											$price = isset($seat_prices[$seat_name][$type]) ? $seat_prices[$seat_name][$type] : '';
											$promo_flag = isset($promo_flags[$seat_name][$type]) ? $promo_flags[$seat_name][$type] : '';
											$promo_price = isset($promo_prices[$seat_name][$type]) ? $promo_prices[$seat_name][$type] : '';
											echo '<div class="wbtm_type_row_modern">';
											echo '<label>' . esc_html($type_label) . ':</label>';
											echo '<input type="number" step="0.01" min="0" name="' . $price_key . '[' . esc_attr($seat_name) . '][' . $type . ']" value="' . esc_attr($price) . '" placeholder="Price" />';
											echo '<input type="checkbox" class="wbtm_promo_check_modern" name="' . $promo_flag_key . '[' . esc_attr($seat_name) . '][' . $type . ']" value="1" ' . ($promo_flag ? 'checked' : '') . ' />';
											echo '<span class="wbtm_promo_label_modern">Promo</span>';
											echo '<input type="number" step="0.01" min="0" name="' . $promo_price_key . '[' . esc_attr($seat_name) . '][' . $type . ']" value="' . esc_attr($promo_price) . '" placeholder="%" style="width:45px;" />';
											echo '<span class="wbtm_percent_modern">%</span>';
											echo '</div>';
										}
										echo '</div>'; ?>
									</div>
									<?php
									$seat_date_ranges = get_post_meta($post_id, 'wbtm_seat_date_ranges', true);
									if (!is_array($seat_date_ranges)) $seat_date_ranges = [];
									$date_ranges = isset($seat_date_ranges[$seat_name]) ? $seat_date_ranges[$seat_name] : [];
									echo '<div class="wbtm_seat_date_range_section" style="margin-top:10px; background:#f9f9f9; border:1px solid #eee; border-radius:8px; padding:10px 12px;">';
									echo "<strong style=\"color:#ff6600; cursor:pointer;\" onclick=\"this.nextElementSibling.style.display = (this.nextElementSibling.style.display==''||this.nextElementSibling.style.display=='block') ? 'none' : 'block';\">Date Range Pricing &#9660;</strong>";
									echo '<div class="wbtm_seat_date_range_list" style="display:none;">';
									if (!empty($date_ranges)) {
										foreach ($date_ranges as $idx => $range) {
											echo '<div class="wbtm_seat_date_range_row" style="margin-bottom:10px; border-bottom:1px dashed #ff6600; padding-bottom:8px;">';
											echo '<label style="font-size:12px;">Start: <input type="date" name="wbtm_seat_date_ranges['.esc_attr($seat_name).']['.$idx.'][start]" value="'.esc_attr($range['start'] ?? '').'" style="margin-right:8px;" /></label>';
											echo '<label style="font-size:12px;">End: <input type="date" name="wbtm_seat_date_ranges['.esc_attr($seat_name).']['.$idx.'][end]" value="'.esc_attr($range['end'] ?? '').'" style="margin-right:8px;" /></label>';
											foreach ([0 => 'Adult', 1 => 'Child', 2 => 'Infant'] as $type => $type_label) {
												$price = isset($range['prices'][$type]) ? $range['prices'][$type] : '';
												$promo_flag = isset($range['promo_flags'][$type]) ? $range['promo_flags'][$type] : '';
												$promo = isset($range['promos'][$type]) ? $range['promos'][$type] : '';
												echo '<div style="display:flex;align-items:center;gap:6px;margin:4px 0 2px 0;">';
												echo '<span style="min-width:45px;font-size:12px;">'.esc_html($type_label).':</span>';
												echo '<input type="number" step="0.01" min="0" name="wbtm_seat_date_ranges['.esc_attr($seat_name).']['.$idx.'][prices]['.$type.']" value="'.esc_attr($price).'" placeholder="Price" style="width:60px;" />';
												echo '<input type="checkbox" name="wbtm_seat_date_ranges['.esc_attr($seat_name).']['.$idx.'][promo_flags]['.$type.']" value="1" '.($promo_flag?'checked':'').' style="accent-color:#ff6600;" />';
												echo '<span style="font-size:11px;color:#ff6600;">Promo</span>';
												echo '<input type="number" step="0.01" min="0" name="wbtm_seat_date_ranges['.esc_attr($seat_name).']['.$idx.'][promos]['.$type.']" value="'.esc_attr($promo).'" placeholder="%" style="width:40px;" />';
												echo '<span style="font-size:11px;color:#888;">%</span>';
												echo '</div>';
											}
											echo '<button type="button" onclick="this.parentNode.remove();" style="margin-top:2px;color:#fff;background:#ff6600;border:none;border-radius:4px;padding:2px 10px;">Remove</button>';
											echo '</div>';
										}
									}
									echo '<button type="button" onclick="wbtmAddSeatDateRange(this,\'' . esc_js($seat_name) . '\');" style="margin-top:6px;color:#fff;background:#ff6600;border:none;border-radius:4px;padding:2px 10px;">+ Add Date Range</button>';
									echo '</div>';
									echo '</div>';
									?>
								</div>
							<?php } ?>
						</th>
					<?php } ?>
					<th> <?php WBTM_Custom_Layout::move_remove_button(); ?> </th>
				</tr>
				<script>
				function wbtmAddPromoRule(btn, key, seat) {
					var wrap = btn.parentNode;
					var idx = wrap.querySelectorAll('.wbtm_promo_rule_row').length;
					var html = `<div class="wbtm_promo_rule_row" style="margin-bottom:2px;">
						<input type="date" name="${key}[${seat}][${idx}][start]" style="width:100px; font-size:11px;" />
						<input type="date" name="${key}[${seat}][${idx}][end]" style="width:100px; font-size:11px;" />
						<input type="text" name="${key}[${seat}][${idx}][user]" placeholder="User/Email (optional)" style="width:120px; font-size:11px;" />
						<input type="number" step="0.01" min="0" name="${key}[${seat}][${idx}][price]" placeholder="Promo Price" style="width:70px; font-size:11px;" />
						<button type="button" class="remove_promo_rule" onclick="this.parentNode.remove();">&times;</button>
					</div>`;
					btn.insertAdjacentHTML('beforebegin', html);
				}
				</script>
				<?php if (empty($GLOBALS['wbtm_seat_date_range_js'])): ?>
				<script>
				function wbtmAddSeatDateRange(btn, seat) {
					var list = btn.parentNode;
					var idx = list.querySelectorAll(".wbtm_seat_date_range_row").length;
					var html = `<div class="wbtm_seat_date_range_row" style="margin-bottom:10px; border-bottom:1px dashed #ff6600; padding-bottom:8px;">
						<label style="font-size:12px;">Start: <input type="date" name="wbtm_seat_date_ranges[${seat}][${idx}][start]" style="margin-right:8px;" /></label>
						<label style="font-size:12px;">End: <input type="date" name="wbtm_seat_date_ranges[${seat}][${idx}][end]" style="margin-right:8px;" /></label>`;
					[0,1,2].forEach(function(type) {
						var typeLabel = type==0?'Adult':(type==1?'Child':'Infant');
						html += `<div style=\"display:flex;align-items:center;gap:6px;margin:4px 0 2px 0;\">` +
							`<span style=\"min-width:45px;font-size:12px;\">${typeLabel}:</span>` +
							`<input type=\"number\" step=\"0.01\" min=\"0\" name=\"wbtm_seat_date_ranges[${seat}][${idx}][prices][${type}]\" placeholder=\"Price\" style=\"width:60px;\" />` +
							`<input type=\"checkbox\" name=\"wbtm_seat_date_ranges[${seat}][${idx}][promo_flags][${type}]\" value=\"1\" style=\"accent-color:#ff6600;\" />` +
							`<span style=\"font-size:11px;color:#ff6600;\">Promo</span>` +
							`<input type=\"number\" step=\"0.01\" min=\"0\" name=\"wbtm_seat_date_ranges[${seat}][${idx}][promos][${type}]\" placeholder=\"%\" style=\"width:40px;\" />` +
							`<span style=\"font-size:11px;color:#888;\">%</span>` +
							`</div>`;
					});
					html += `<button type=\"button\" onclick=\"this.parentNode.remove();\" style=\"margin-top:2px;color:#fff;background:#ff6600;border:none;border-radius:4px;padding:2px 10px;\">Remove</button>`;
					html += `</div>`;
					btn.insertAdjacentHTML('beforebegin', html);
				}
				</script>
				<?php $GLOBALS['wbtm_seat_date_range_js'] = true; endif; ?>
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
					update_post_meta($post_id, 'driver_seat_position', $driver_seat_position);
					update_post_meta($post_id, 'wbtm_seat_rows', $rows);
					update_post_meta($post_id, 'wbtm_seat_cols', $columns);
					$lower_deck_info = [];
					$total_seat=0;
					if ($rows > 0 && $columns > 0) {
						for ($j = 1; $j <= $columns; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, []);
							for ($i = 0; $i < $rows; $i++) {
								$lower_deck_info[$i]['seat' . $j] = $col_infos[$i];
								if ($col_infos[$i] && $col_infos[$i] != 'door' && $col_infos[$i] != 'wc') {
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
							for ($i = 0; $i < $rows_dd; $i++) {
								$upper_deck_info[$i]['dd_seat' . $j] = $col_infos[$i];
								if ($col_infos[$i] && $col_infos[$i] != 'door' && $col_infos[$i] != 'wc' && $wbtm_show_upper_desk=='yes') {
									$total_seat++;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $upper_deck_info);
					/***********************/
					$total_seat=$seat_type=='wbtm_seat_plan'?$total_seat:WBTM_Global_Function::get_submit_info('wbtm_get_total_seat', 0);
					update_post_meta($post_id, 'wbtm_get_total_seat', $total_seat);
					// Save blocked seats for lower deck
					$blocked_seats = [];
					for ($j = 1; $j <= $columns; $j++) {
						$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, []);
						for ($i = 0; $i < $rows; $i++) {
							$seat_name = $col_infos[$i];
							if ($seat_name && $seat_name != 'door' && $seat_name != 'wc') {
								if (isset($_POST['wbtm_blocked_seats'][$seat_name])) {
									$blocked_seats[$seat_name] = 1;
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_blocked_seats', $blocked_seats);
					// Save blocked seats for upper deck
					$blocked_seats_dd = [];
					if ($rows_dd > 0 && $cols_dd > 0) {
						for ($j = 1; $j <= $cols_dd; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j, []);
							for ($i = 0; $i < $rows_dd; $i++) {
								$seat_name = $col_infos[$i];
								if ($seat_name && $seat_name != 'door' && $seat_name != 'wc' && $wbtm_show_upper_desk=='yes') {
									if (isset($_POST['wbtm_blocked_seats_dd'][$seat_name])) {
										$blocked_seats_dd[$seat_name] = 1;
									}
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_blocked_seats_dd', $blocked_seats_dd);
					// Save seat types and labels for lower deck
					$seat_types = [];
					$seat_labels = [];
					for ($j = 1; $j <= $columns; $j++) {
						$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, []);
						for ($i = 0; $i < $rows; $i++) {
							$seat_name = $col_infos[$i];
							if ($seat_name && $seat_name != 'door' && $seat_name != 'wc') {
								if (isset($_POST['wbtm_seat_types'][$seat_name])) {
									$seat_types[$seat_name] = sanitize_text_field($_POST['wbtm_seat_types'][$seat_name]);
								}
								if (isset($_POST['wbtm_seat_labels'][$seat_name])) {
									$seat_labels[$seat_name] = sanitize_text_field($_POST['wbtm_seat_labels'][$seat_name]);
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_seat_types', $seat_types);
					update_post_meta($post_id, 'wbtm_seat_labels', $seat_labels);
					// Save seat prices, promo flags, promo prices, and promo rules for lower deck
					$seat_prices = [];
					$promo_flags = [];
					$promo_prices = [];
					for ($j = 1; $j <= $columns; $j++) {
						$col_infos = WBTM_Global_Function::get_submit_info('wbtm_seat' . $j, []);
						for ($i = 0; $i < $rows; $i++) {
							$seat_name = $col_infos[$i];
							if ($seat_name && $seat_name != 'door' && $seat_name != 'wc') {
								foreach ([0, 1, 2] as $type) {
									if (isset($_POST['wbtm_seat_prices'][$seat_name][$type])) {
										$seat_prices[$seat_name][$type] = floatval($_POST['wbtm_seat_prices'][$seat_name][$type]);
									}
									if (isset($_POST['wbtm_seat_promo_flags'][$seat_name][$type])) {
										$promo_flags[$seat_name][$type] = 1;
									} else {
										$promo_flags[$seat_name][$type] = 0;
									}
									if (isset($_POST['wbtm_seat_promo_prices'][$seat_name][$type])) {
										$promo_prices[$seat_name][$type] = floatval($_POST['wbtm_seat_promo_prices'][$seat_name][$type]);
									}
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_seat_prices', $seat_prices);
					update_post_meta($post_id, 'wbtm_seat_promo_flags', $promo_flags);
					update_post_meta($post_id, 'wbtm_seat_promo_prices', $promo_prices);
					// Save seat types and labels for upper deck
					$seat_types_dd = [];
					$seat_labels_dd = [];
					if ($rows_dd > 0 && $cols_dd > 0) {
						for ($j = 1; $j <= $cols_dd; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j, []);
							for ($i = 0; $i < $rows_dd; $i++) {
								$seat_name = $col_infos[$i];
								if ($seat_name && $seat_name != 'door' && $seat_name != 'wc' && $wbtm_show_upper_desk=='yes') {
									if (isset($_POST['wbtm_seat_types_dd'][$seat_name])) {
										$seat_types_dd[$seat_name] = sanitize_text_field($_POST['wbtm_seat_types_dd'][$seat_name]);
									}
									if (isset($_POST['wbtm_seat_labels_dd'][$seat_name])) {
										$seat_labels_dd[$seat_name] = sanitize_text_field($_POST['wbtm_seat_labels_dd'][$seat_name]);
									}
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_seat_types_dd', $seat_types_dd);
					update_post_meta($post_id, 'wbtm_seat_labels_dd', $seat_labels_dd);
					// Save seat prices, promo flags, promo prices, and promo rules for upper deck
					$seat_prices_dd = [];
					$promo_flags_dd = [];
					$promo_prices_dd = [];
					$promo_rules_dd = [];
					if ($rows_dd > 0 && $cols_dd > 0) {
						for ($j = 1; $j <= $cols_dd; $j++) {
							$col_infos = WBTM_Global_Function::get_submit_info('wbtm_dd_seat' . $j, []);
							for ($i = 0; $i < $rows_dd; $i++) {
								$seat_name = $col_infos[$i];
								if ($seat_name && $seat_name != 'door' && $seat_name != 'wc' && $wbtm_show_upper_desk=='yes') {
									if (isset($_POST['wbtm_seat_prices_dd'][$seat_name])) {
										$seat_prices_dd[$seat_name] = floatval($_POST['wbtm_seat_prices_dd'][$seat_name]);
									}
									if (isset($_POST['wbtm_seat_promo_flags_dd'][$seat_name])) {
										$promo_flags_dd[$seat_name] = 1;
									}
									if (isset($_POST['wbtm_seat_promo_prices_dd'][$seat_name])) {
										$promo_prices_dd[$seat_name] = floatval($_POST['wbtm_seat_promo_prices_dd'][$seat_name]);
									}
									if (isset($_POST['wbtm_seat_promo_rules_dd'][$seat_name])) {
										$promo_rules_dd[$seat_name] = $_POST['wbtm_seat_promo_rules_dd'][$seat_name];
									}
								}
							}
						}
					}
					update_post_meta($post_id, 'wbtm_seat_prices_dd', $seat_prices_dd);
					update_post_meta($post_id, 'wbtm_seat_promo_flags_dd', $promo_flags_dd);
					update_post_meta($post_id, 'wbtm_seat_promo_prices_dd', $promo_prices_dd);
					update_post_meta($post_id, 'wbtm_seat_promo_rules_dd', $promo_rules_dd);
					// Save wbtm_seat_date_ranges for lower deck
					$seat_date_ranges = [];
					if (isset($_POST['wbtm_seat_date_ranges']) && is_array($_POST['wbtm_seat_date_ranges'])) {
						foreach ($_POST['wbtm_seat_date_ranges'] as $seat_name => $ranges) {
							if (!is_array($ranges)) continue;
							foreach ($ranges as $idx => $range) {
								$start = isset($range['start']) ? sanitize_text_field($range['start']) : '';
								$end = isset($range['end']) ? sanitize_text_field($range['end']) : '';
								$prices = isset($range['prices']) && is_array($range['prices']) ? array_map('floatval', $range['prices']) : [];
								$promo_flags = isset($range['promo_flags']) && is_array($range['promo_flags']) ? array_map(function($v){return $v?1:0;}, $range['promo_flags']) : [];
								$promos = isset($range['promos']) && is_array($range['promos']) ? array_map('floatval', $range['promos']) : [];
								$seat_date_ranges[$seat_name][$idx] = [
									'start' => $start,
									'end' => $end,
									'prices' => $prices,
									'promo_flags' => $promo_flags,
									'promos' => $promos,
								];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_seat_date_ranges', $seat_date_ranges);
					// Save advanced seat options
					$advanced_options_enabled = isset($_POST['wbtm_advanced_seat_options']) && $_POST['wbtm_advanced_seat_options'] === 'yes' ? 'yes' : 'no';
					update_post_meta($post_id, 'wbtm_advanced_seat_options', $advanced_options_enabled);
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