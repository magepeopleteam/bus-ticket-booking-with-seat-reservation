<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Pricing_Routing')) {
		class WBTM_Pricing_Routing {
			public function __construct() {
				add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_settings_save', [$this, 'settings_save']);
				/*********************/
				add_action('wp_ajax_wbtm_reload_pricing', [$this, 'wbtm_reload_pricing']);
				add_action('wp_ajax_nopriv_wbtm_reload_pricing', [$this, 'wbtm_reload_pricing']);
			}
			public function tab_content($post_id) {
				$full_route_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_route_info',[]);
				$bus_stop_lists = MP_Global_Function::get_all_term_data('wbtm_bus_stops');
				?>
				<div class="tabsItem wbtm_settings_pricing_routing" data-tabs="#wbtm_settings_pricing_routing">
					
					<h3 class="pB_xs"><?php _e('Price And Routing Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>

					<div class="_dLayout_xs_mp_zero">
						<div class="_bgColor_2_padding dFlex">
							<label class="textBlack ">
								<?php esc_html_e('Routing Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
								<i class="fas fa-question-circle tool-tips"><?php WBTM_Settings::info_text('wbtm_routing_info'); ?></i>
							</label>
						</div>
						<div class="mpPanelBody _bgWhite">
							<div class="mp_settings_area">
								<div class="flexWrap mp_sortable_area mp_item_insert">
									<?php if (sizeof($full_route_infos) > 0) { 
										foreach ($full_route_infos as $full_route_info) { 
											$this->add_stops_item($bus_stop_lists, $full_route_info);
										} 
									} ?>
									<div class="_mB_xs mp_item_insert_before">
										<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Stops', 'bus-ticket-booking-with-seat-reservation'), 'mp_add_item', '_navy_blueButton_fullHeight'); ?>
									</div>
								</div>
								<div class="mp_hidden_content">
									<div class="mp_hidden_item">
										<?php $this->add_stops_item($bus_stop_lists); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="_mT"></div>
					<div class="_dLayout_xs_mp_zero ">
						<div class="_bgColor_2_padding dFlex">
							<label class="textBlack ">
								<?php esc_html_e('Pricing Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
								<i class="fas fa-question-circle tool-tips"><?php WBTM_Settings::info_text('wbtm_pricing_info'); ?></i>
							</label>
						</div>
						<div class="mpPanelBody _bgWhite">
							<div class="wbtm_price_setting_area">
								<?php $this->route_pricing($post_id, $full_route_infos); ?>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
			public function add_stops_item($bus_stop_lists, $full_route_info = []) {
				$palace = array_key_exists('place', $full_route_info) ? $full_route_info['place'] : '';
				$time = array_key_exists('time', $full_route_info) ? $full_route_info['time'] : '';
				$type = array_key_exists('type', $full_route_info) ? $full_route_info['type'] : '';
				//$interval = array_key_exists('interval', $full_route_info) ? $full_route_info['interval'] : 0;
				?>
				<div class="mp_remove_area col_12  alignCenter _mB_xs wbtm_stop_item">
					<div class="mpPanel col_12">
						
						<div data-collapse-target="#asdf" class="mpPanelHeader _bgLight_3_alignCenter justifyBetween">
							<h5 class="_textWhite"><?php esc_html_e('Stops Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
								<?php foreach ($bus_stop_lists as $bus_stop) { 
							
									echo ($bus_stop == $palace )? $bus_stop : '';
								}?>
								<?php echo esc_attr($time); ?>
								<?php echo esc_attr($type == 'bp' ? 'Bording' : ''); ?>
								<?php echo esc_attr($type == 'dp' ? 'Droping' : ''); ?>
								<?php echo esc_attr($type == 'both' ? 'Both' : ''); ?>
							</h5>
							<?php MP_Custom_Layout::move_remove_button(); ?>
						</div>
						<div data-collapse="#asdf" class="mpPanelBody">
							<label class="pB_xs">
								<span class="_w_75"><?php esc_html_e('Stop : ', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<select name="wbtm_route_place[]" class='formControl'>
									<option selected disabled><?php esc_html_e('Select bus stop', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<?php foreach ($bus_stop_lists as $bus_stop) { ?>
										<option value="<?php echo esc_attr($bus_stop); ?>" <?php echo esc_attr($bus_stop == $palace ? 'selected' : ''); ?>><?php echo esc_html($bus_stop); ?></option>
									<?php } ?>
								</select>
							</label>
							<label class="pB_xs">
								<span class="_w_75"><?php esc_html_e('Time : ', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<input type="time" name="wbtm_route_time[]" class='formControl' value="<?php echo esc_attr($time); ?>"/>
							</label>
							<label class="pB_xs">
								<span class="_w_75"><?php esc_html_e('Type : ', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<select name="wbtm_route_type[]" class='formControl'>
									<option selected disabled><?php esc_html_e('Select place type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="bp" <?php echo esc_attr($type == 'bp' ? 'selected' : ''); ?>><?php esc_html_e('Boarding ', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="dp" <?php echo esc_attr($type == 'dp' ? 'selected' : ''); ?>><?php esc_html_e('Dropping ', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="both" <?php echo esc_attr($type == 'both' ? 'selected' : ''); ?>><?php esc_html_e('Boarding & Dropping', 'bus-ticket-booking-with-seat-reservation'); ?></option>
								</select>
							</label>
<!--							<label>-->
<!--								<span class="_w_75">--><?php //esc_html_e('Interval : ', 'bus-ticket-booking-with-seat-reservation'); ?><!--</span>-->
<!--								<input type="number" pattern="[0-9]*" step="1" class="formControl mp_number_validation" name="wbtm_route_interval[]" placeholder="Ex: 1" value="--><?php //echo esc_attr($interval); ?><!--"/>-->
<!--							</label>-->
						</div>
					</div>
				</div>
				<?php
			}
			public function route_pricing($post_id, $full_route_infos) {
				//echo '<pre>';print_r(MP_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []));echo '</pre>';
				$all_price_info = [];
				if (sizeof($full_route_infos) > 0) {
					$price_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []);
					foreach ($full_route_infos as $key => $full_route_info) {
						if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') {
							$bp = $full_route_info['place'];
							$next_infos = array_slice($full_route_infos, $key + 1);
							if (sizeof($next_infos) > 0) {
								foreach ($next_infos as $next_info) {
									if ($next_info['type'] == 'dp' || $next_info['type'] == 'both') {
										$dp = $next_info['place'];
										$adult_price = '';
										$child_price = '';
										$infant_price = '';
										if (sizeof($price_infos) > 0) {
											foreach ($price_infos as $price_info) {
												if (strtolower($price_info['wbtm_bus_bp_price_stop']) == strtolower($bp) && strtolower($price_info['wbtm_bus_dp_price_stop']) == strtolower($dp)) {
													$adult_price = array_key_exists('wbtm_bus_price', $price_info) && $price_info['wbtm_bus_price'] ? (float)$price_info['wbtm_bus_price'] : '';
													$child_price = array_key_exists('wbtm_bus_child_price', $price_info) && $price_info['wbtm_bus_child_price'] ? (float)$price_info['wbtm_bus_child_price'] : '';
													$infant_price = array_key_exists('wbtm_bus_infant_price', $price_info) && $price_info['wbtm_bus_infant_price'] ? (float)$price_info['wbtm_bus_infant_price'] : '';
												}
											}
										}
										$all_price_info[] = [
											'bp' => $bp,
											'dp' => $dp,
											'adult_price' => $adult_price,
											'child_price' => $child_price,
											'infant_price' => $infant_price,
										];
									}
								}
							}
						}
					}
				}
				//echo '<pre>';print_r($all_price_info);echo '</pre>';
				if (sizeof($all_price_info) > 0) {
					?>
					<table>
						<thead>
						<tr>
							<th><?php esc_html_e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th><?php esc_html_e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th><?php esc_html_e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?>
								<sup class="required">*</sup>
							</th>
							<th><?php esc_html_e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th><?php esc_html_e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($all_price_info as $price_info) { ?>
							<tr>
								<th>
									<input type="hidden" name="wbtm_price_bp[]" value="<?php echo esc_attr($price_info['bp']); ?>"/>
									<?php echo esc_html($price_info['bp']); ?>
								</th>
								<th>
									<input type="hidden" name="wbtm_price_dp[]" value="<?php echo esc_attr($price_info['dp']); ?>"/>
									<?php echo esc_html($price_info['dp']); ?>
								</th>
								<td>
									<label>
										<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="wbtm_adult_price[]" placeholder="Ex: 10" value="<?php echo esc_attr($price_info['adult_price']); ?>" />
									</label>
								</td>
								<td>
									<label>
										<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="wbtm_child_price[]" placeholder="Ex: 10" value="<?php echo esc_attr($price_info['child_price']); ?>"/>
									</label>
								</td>
								<td>
									<label>
										<input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="wbtm_infant_price[]" placeholder="Ex: 10" value="<?php echo esc_attr($price_info['infant_price']); ?>"/>
									</label>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				<?php } else { ?>
					<div class="_dLayout_bgWarning_mZero">
						<h3><?php esc_html_e('Please Create Bus route .', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
					</div>
					<?php
				}
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
					$route_infos = [];
					$bp = [];
					$dp = [];
					$stops = MP_Global_Function::get_submit_info('wbtm_route_place', array());
					$times = MP_Global_Function::get_submit_info('wbtm_route_time', array());
					$types = MP_Global_Function::get_submit_info('wbtm_route_type', array());
					//$intervals = MP_Global_Function::get_submit_info('wbtm_route_interval', array());
					if (sizeof($stops) > 0) {
						foreach ($stops as $key => $stop) {
							if ($stop && $times[$key] && $types[$key]) {
								$route_infos[] = [
									'place' => $stop,
									'time' => $times[$key],
									'type' => $types[$key],
									//'interval' => max(0, $intervals[$key]),
								];
								
							}
						}
					}
					$count = sizeof($route_infos);
					if ($count > 0) {
						$route_infos[0]['type'] = 'bp';
						//$route_infos[0]['interval'] = 0;
						$route_infos[$count - 1]['type'] = 'dp';
						//$route_infos[$count - 1]['interval'] = 0;
						foreach ($route_infos as $route_info){
							if($route_info['type']=='bp'){
								$bp[]=$route_info['place'];
							}elseif ($route_info['type']=='dp'){
								$dp[]=$route_info['place'];
							}else{
								$bp[]=$route_info['place'];
								$dp[]=$route_info['place'];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_route_info', $route_infos);
					update_post_meta($post_id, 'wbtm_bus_bp_stops', $bp);
					update_post_meta($post_id, 'wbtm_bus_next_stops', $dp);
					if (sizeof($route_infos) > 0) {
						$route_direction = [];
						foreach ($route_infos as $route) {
							$route_direction[] = $route['place'];
						}
						$route_direction = array_unique($route_direction);
						update_post_meta($post_id, 'wbtm_route_direction', $route_direction);
					}
					/********************************************/
					$price_infos = [];
					$stops_bps = MP_Global_Function::get_submit_info('wbtm_price_bp', array());
					$stops_dps = MP_Global_Function::get_submit_info('wbtm_price_dp', array());
					$adult_price = MP_Global_Function::get_submit_info('wbtm_adult_price', array());
					$child_price = MP_Global_Function::get_submit_info('wbtm_child_price', array());
					$infant_price = MP_Global_Function::get_submit_info('wbtm_infant_price', array());
					if (sizeof($stops_bps) > 0) {
						foreach ($stops_bps as $key => $stops_bp) {
							if ($stops_bp && $stops_dps[$key] && $adult_price[$key]) {
								$price_infos[] = [
									'wbtm_bus_bp_price_stop' => $stops_bp,
									'wbtm_bus_dp_price_stop' => $stops_dps[$key],
									'wbtm_bus_price' => $adult_price[$key],
									'wbtm_bus_child_price' => $child_price[$key],
									'wbtm_bus_infant_price' => $infant_price[$key],
								];
							}
						}
					}
					update_post_meta($post_id, 'wbtm_bus_prices', $price_infos);
					//echo '<pre>';print_r($price_infos);echo '</pre>';die();
				}
			}
			/**************************/
			public function wbtm_reload_pricing() {
				$post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
				$route_infos = MP_Global_Function::data_sanitize($_POST['route_infos']);
				$this->route_pricing($post_id, $route_infos);
				die();
			}
		}
		new WBTM_Pricing_Routing();
	}