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
				add_action('wbtm_add_settings_tab_content', [$this, 'tab_content']);
				add_action('wbtm_ticket_type_item', [$this, 'ticket_type_item']);
				/*********************/
				add_action('wp_ajax_wbtm_reload_pricing', [$this, 'wbtm_reload_pricing']);
			}
			public function tab_content($post_id) {
				$full_route_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
				$bus_stop_lists = WBTM_Global_Function::get_all_term_data('wbtm_bus_stops');
				$ticket_types = WBTM_Functions::get_ticket_types($post_id);
				?>
                <div class="tabsItem wbtm_settings_pricing_routing" data-tabs="#wbtm_settings_pricing_routing">
                    <h3 class="pB_xs"><?php esc_html_e('Price And Routing Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <p><?php esc_html_e('Here you can configure Price And Routing for a bus.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    <div class="">
                        <div class="_dLayout_padding_bgLight">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e('Boarding and Dropping Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </label>
                                <span><?php WBTM_Settings::info_text('wbtm_routing_info'); ?></span>
                            </div>
                        </div>
                        <div class="_dLayout_padding">
                            <div class="wbtm_settings_area">
                                <div class="mp_stop_items wbtm_sortable_area wbtm_item_insert">
									<?php if (sizeof($full_route_infos) > 0) {
										foreach ($full_route_infos as $key => $full_route_info) {
											$this->add_stops_item($bus_stop_lists, $full_route_info, $key);
										}
									} ?>
                                    <div class="_mB_xs wbtm_item_insert_before"></div>
                                </div>
                                <div class="justifyCenter">
									<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add New Stops', 'bus-ticket-booking-with-seat-reservation'), 'wbtm_add_item', '_themeButton_xs_fullHeight'); ?>
                                </div>
                                <!-- create new bus route -->
                                <div class="wbtm_hidden_content">
                                    <div class="wbtm_hidden_item">
										<?php $this->add_stops_item($bus_stop_lists, [], 0); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="_mT"></div>
                    <div class="_dLayout_padding_bgLight ">
                        <div class="_dFlex_fdColumn">
                            <label>
								<?php esc_html_e('Pricing Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <span><?php WBTM_Settings::info_text('wbtm_pricing_info'); ?></span>
                        </div>
                    </div>
                    <div class="_dLayout_padding">
                        <div class="wbtm_settings_area wbtm_ticket_type_area">
                            <div class="ovAuto">
                                <table>
                                    <thead>
                                    <tr>
                                        <th><?php esc_html_e('Passenger Type', 'bus-ticket-booking-with-seat-reservation'); ?><i class="textRequired">&nbsp;*</i></th>
                                        <th><?php esc_html_e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody class="wbtm_sortable_area wbtm_item_insert">
									<?php foreach ($ticket_types as $ticket_type) {
										$this->ticket_type_item($ticket_type);
									} ?>
                                    </tbody>
                                </table>
                            </div>
							<?php WBTM_Custom_Layout::add_new_button(esc_html__('Add Passenger Type', 'bus-ticket-booking-with-seat-reservation')); ?>
							<?php do_action('wbtm_hidden_table', 'wbtm_ticket_type_item'); ?>
                        </div>
                        <div class="_mT"></div>
                        <div class="wbtm_price_setting_area">
							<?php $this->route_pricing($post_id, $full_route_infos, $ticket_types); ?>
                        </div>
                    </div>
					<?php do_action('wbtm_add_return_discount', $post_id); ?>
                </div>
				<?php
			}
			public function add_stops_item($bus_stop_lists, $full_route_info = [], $key = 0) {
				$palace = array_key_exists('place', $full_route_info) ? $full_route_info['place'] : '';
				$time = array_key_exists('time', $full_route_info) ? $full_route_info['time'] : '';
				$type = array_key_exists('type', $full_route_info) ? $full_route_info['type'] : '';
				//$interval = array_key_exists('interval', $full_route_info) ? $full_route_info['interval'] : 0;
				$next_day = array_key_exists('next_day', $full_route_info) ? $full_route_info['next_day'] : false;
				?>
                <div class="wbtm_remove_area col_12_mB  wbtm_stop_item ">
                    <div class="_bgLight_dFlex_justifyBetween_alignCenter wbtm_stop_item_header" data-collapse-target="">
						<?php
							$location = '';
							foreach ($bus_stop_lists as $bus_stop) {
								if ($bus_stop == $palace) {
									$location = $palace;
								}
							}
						?>
                        <div class="col_4 mp_zero">
							<?php if (empty($location)): ?>
                                <label><?php esc_html_e('Add Stop', 'bus-ticket-booking-with-seat-reservation'); ?></label>
							<?php else: ?>
                                <label><?php echo esc_html($location); ?></label>
                                <span>
									<?php echo esc_html(($type == 'bp') ? ' (Bording) ' : ''); ?>
									<?php echo esc_html(($type == 'dp') ? ' (Dropping) ' : ''); ?>
									<?php echo esc_html(($type == 'both') ? ' (Bording+Dropping) ' : ''); ?>
								</span>
							<?php endif; ?>
                        </div>
                        <label class="col_4 _mp_zero _dFlex_alignCenter">
							<?php if ($time): ?>
                                <i class="far fa-clock"></i> <input class="_zeroBorder_mp_zero" type="time" value="<?php echo esc_attr($time); ?>" readonly>
							<?php else: ?>
                                <i class="far fa-clock"></i>&nbsp;--:-- --
							<?php endif; ?>
                        </label>
						<?php WBTM_Custom_Layout::edit_move_remove_button(); ?>
                    </div>
                    <div class="wbtm_stop_item_content" data-collapse="">
                        <div class="_dFlex_justifyCenter_alignCenter ">
                            <div class="col_4 _dFlex_justifyCenter_alignCenter">
                                <label class="_mp_zero _mR"><?php esc_html_e('Stop : ', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <select name="wbtm_route_place[]" class='formControl max_200 _mL_xs'>
                                    <option selected disabled><?php esc_html_e('Select bus stop', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<?php foreach ($bus_stop_lists as $bus_stop) { ?>
                                        <option value="<?php echo esc_attr($bus_stop); ?>" <?php echo esc_attr($bus_stop == $palace ? 'selected' : ''); ?>><?php echo esc_html($bus_stop); ?></option>
									<?php } ?>
                                </select>
                            </div>
                            <div class="col_4 _dFlex_justifyCenter_alignCenter">
                                <label class="mp_zero"><?php esc_html_e('Time : ', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <input type="time" name="wbtm_route_time[]" class='formControl max_200 _mL_xs' value="<?php echo esc_attr($time); ?>"/>
                            </div>
                            <div class="col_4 _dFlex_justifyCenter_alignCenter">
                                <label class="mp_zero"><?php esc_html_e('Type : ', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <select name="wbtm_route_type[]" class='formControl max_200 _mL_xs'>
                                    <option selected disabled><?php esc_html_e('Select place type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="bp" <?php echo esc_attr($type == 'bp' ? 'selected' : ''); ?>><?php esc_html_e('Boarding ', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="dp" <?php echo esc_attr($type == 'dp' ? 'selected' : ''); ?>><?php esc_html_e('Dropping ', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="both" <?php echo esc_attr($type == 'both' ? 'selected' : ''); ?>><?php esc_html_e('Boarding & Dropping', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="_dFlex_justifyCenter_alignCenter ">
                            <div class="col_12 _margin _dFlex_justifyCenter_alignCenter next-day-dropping-checkbox" style="display: <?php echo ($type == 'dp' || $type == 'both') ? 'block' : 'none'; ?>;">
                                <label class="mp_zero"><?php esc_html_e('Next Day Dropping: ', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                <input type="hidden" name="wbtm_route_next_day[<?php echo esc_attr($key); ?>]" value="0"/>
                                <input type="checkbox" name="wbtm_route_next_day[<?php echo esc_attr($key); ?>]" value="1" <?php echo esc_attr($next_day ? 'checked' : ''); ?> />
                            </div>
                        </div>
                        <script>
                            jQuery(document).ready(function ($) {
                                // Handle showing/hiding checkbox when selecting "Dropping" or "Boarding & Dropping"
                                $('select[name="wbtm_route_type[]"]').on('change', function () {
                                    var type = $(this).val();
                                    var nextDayCheckbox = $(this).closest('.wbtm_stop_item').find('.next-day-dropping-checkbox');
                                    // Show or hide the "Next Day Dropping" checkbox based on the selected type
                                    if (type == 'dp' || type == 'both') {
                                        nextDayCheckbox.show();
                                    } else {
                                        nextDayCheckbox.hide();
                                    }
                                });
                                // Trigger the change event on page load to ensure the checkbox visibility is correct
                                // $('select[name="wbtm_route_type[]"]').each(function () {
                                //     $(this).trigger('change');
                                // });
                            });
                        </script>
                    </div>
                </div>
				<?php
			}
			public function ticket_type_item($ticket_type = []) {
				$ticket_type = is_array($ticket_type) ? $ticket_type : [];
				$ticket_type_id = array_key_exists('id', $ticket_type) ? $ticket_type['id'] : '';
				$ticket_type_label = array_key_exists('label', $ticket_type) ? $ticket_type['label'] : '';
				?>
                <tr class="wbtm_remove_area wbtm_ticket_type_item">
                    <td>
                        <input type="hidden" name="wbtm_ticket_type_id[]" value="<?php echo esc_attr($ticket_type_id); ?>"/>
                        <label>
                            <input type="text" class="formControl wbtm_name_validation" name="wbtm_ticket_type_label[]" placeholder="<?php esc_attr_e('Ex: Adult', 'bus-ticket-booking-with-seat-reservation'); ?>" value="<?php echo esc_attr($ticket_type_label); ?>"/>
                        </label>
                    </td>
                    <td class="_w_100">
						<?php WBTM_Custom_Layout::move_remove_button(); ?>
                    </td>
                </tr>
				<?php
			}
			private function get_route_price_key($bp, $dp) {
				return strtolower(trim($bp) . '||' . trim($dp));
			}
			private function sanitize_ticket_types($ticket_types, $post_id = 0) {
				if (!is_array($ticket_types) || sizeof($ticket_types) === 0) {
					return WBTM_Functions::get_ticket_types($post_id);
				}
				$normalized_ticket_types = [];
				$used_ids = [];
				foreach ($ticket_types as $index => $ticket_type) {
					if (!is_array($ticket_type)) {
						continue;
					}
					$label = array_key_exists('label', $ticket_type) ? sanitize_text_field($ticket_type['label']) : '';
					if (!$label) {
						continue;
					}
					$ticket_type_id = array_key_exists('id', $ticket_type) ? $ticket_type['id'] : '';
					$ticket_type_id = WBTM_Functions::generate_ticket_type_id($ticket_type_id, $label, $used_ids, $index);
					$normalized_ticket_types[] = [
						'id' => $ticket_type_id,
						'label' => $label,
					];
					$used_ids[] = $ticket_type_id;
				}
				return sizeof($normalized_ticket_types) > 0 ? $normalized_ticket_types : WBTM_Functions::get_ticket_types($post_id);
			}
			private function sanitize_price_map($price_map = []) {
				$sanitized_price_map = [];
				if (!is_array($price_map)) {
					return $sanitized_price_map;
				}
				foreach ($price_map as $route_key => $route_prices) {
					if (!is_array($route_prices)) {
						continue;
					}
					$sanitized_route_key = sanitize_text_field($route_key);
					foreach ($route_prices as $ticket_type_id => $ticket_price) {
						$sanitized_ticket_type_id = sanitize_key($ticket_type_id);
						$sanitized_price_map[$sanitized_route_key][$sanitized_ticket_type_id] = $ticket_price === '' ? '' : (float) sanitize_text_field((string) $ticket_price);
					}
				}
				return $sanitized_price_map;
			}
			private function find_route_price_info($price_infos, $bp, $dp) {
				if (sizeof($price_infos) > 0) {
					foreach ($price_infos as $price_info) {
						if (
							strtolower($price_info['wbtm_bus_bp_price_stop']) == strtolower($bp) &&
							strtolower($price_info['wbtm_bus_dp_price_stop']) == strtolower($dp)
						) {
							return $price_info;
						}
					}
				}
				return [];
			}
			public function route_pricing($post_id, $full_route_infos, $ticket_types = [], $request_price_map = []) {
				$ticket_types = $this->sanitize_ticket_types($ticket_types, $post_id);
				$request_price_map = $this->sanitize_price_map($request_price_map);
				$all_price_info = [];
				if (sizeof($full_route_infos) > 0) {
					$price_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []);
					foreach ($full_route_infos as $key => $full_route_info) {
						if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') {
							$bp = $full_route_info['place'];
							$next_infos = array_slice($full_route_infos, $key + 1);
							if (sizeof($next_infos) > 0) {
								foreach ($next_infos as $next_info) {
									if ($next_info['type'] == 'dp' || $next_info['type'] == 'both') {
										$dp = $next_info['place'];
										$route_price_key = $this->get_route_price_key($bp, $dp);
										$route_prices = [];
										$stored_price_info = $this->find_route_price_info($price_infos, $bp, $dp);
										foreach ($ticket_types as $ticket_type) {
											$ticket_type_id = $ticket_type['id'];
											$route_prices[$ticket_type_id] = array_key_exists($route_price_key, $request_price_map) && array_key_exists($ticket_type_id, $request_price_map[$route_price_key]) ? $request_price_map[$route_price_key][$ticket_type_id] : WBTM_Functions::get_ticket_price_by_type($stored_price_info, $ticket_type_id);
											}
										$all_price_info[] = [
											'bp' => $bp,
											'dp' => $dp,
											'route_price_key' => $route_price_key,
											'prices' => $route_prices,
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
                            <th colspan="2">
                                <div class="_dFlex_justifyBetween ">
                                    <div class="col_5 _textLeft_pL_xs">
                                        <span><?php esc_html_e('Boarding', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                    </div>
                                    <div class="col_5 _textRight_pR_xs">
                                        <span><?php esc_html_e('Dropping', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                    </div>
                                </div>
                            </th>
							<?php foreach ($ticket_types as $index => $ticket_type) { ?>
                                <th>
									<?php echo esc_html($ticket_type['label']); ?>
									<?php if ($index === 0) { ?>
                                        <sup class="required">*</sup>
									<?php } ?>
                                </th>
							<?php } ?>
                        </tr>
                        </thead>
                        <tbody>
						<?php foreach ($all_price_info as $row_index => $price_info) { ?>
                            <tr data-price-key="<?php echo esc_attr($price_info['route_price_key']); ?>">
                                <td colspan="2">
                                    <div class="_dFlex_justifyBetween_pT_xs">
                                        <div class="col_5 _textLeft_pL_xs">
                                            <input type="hidden" name="wbtm_price_bp[]" value="<?php echo esc_attr($price_info['bp']); ?>"/>
                                            <span><?php echo esc_html($price_info['bp']); ?></span>
                                        </div>
                                        <div class="col_2 long-arrow">
                                        </div>
                                        <div class="col_5 _textRight_pR_xs">
                                            <input type="hidden" name="wbtm_price_dp[]" value="<?php echo esc_attr($price_info['dp']); ?>"/>
                                            <span><?php echo esc_html($price_info['dp']); ?></span>
                                        </div>
                                    </div>
                                </td>
								<?php foreach ($ticket_types as $ticket_type) { ?>
                                    <td>
                                        <label>
                                            <input
                                                type="number"
                                                pattern="[0-9]*"
                                                step="0.01"
                                                class="formControl wbtm_price_validation"
                                                data-ticket-type="<?php echo esc_attr($ticket_type['id']); ?>"
                                                name="wbtm_ticket_price[<?php echo esc_attr($row_index); ?>][<?php echo esc_attr($ticket_type['id']); ?>]"
                                                placeholder="Ex: 10"
                                                value="<?php echo esc_attr(array_key_exists($ticket_type['id'], $price_info['prices']) ? $price_info['prices'][$ticket_type['id']] : ''); ?>"
                                            />
                                        </label>
                                    </td>
								<?php } ?>
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
			public function wbtm_reload_pricing() {
				if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wbtm_admin_nonce')) {
					wp_send_json_error('Invalid nonce!'); // Prevent unauthorized access
				}
				$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
				$places = isset($_POST['places']) ? array_map('sanitize_text_field', wp_unslash($_POST['places'])) : [];
				$types = isset($_POST['types']) ? array_map('sanitize_text_field', wp_unslash($_POST['types'])) : [];
				$ticket_types_json = isset($_POST['ticket_types_json']) ? wp_unslash($_POST['ticket_types_json']) : '[]';
				$price_map_json = isset($_POST['price_map_json']) ? wp_unslash($_POST['price_map_json']) : '{}';
				$ticket_types = json_decode($ticket_types_json, true);
				$price_map = json_decode($price_map_json, true);
				$route_infos = [];
                if(sizeof($places)>0){
                    foreach ($places as $key=>$place){
	                    $route_infos[$key]['place'] = $place;
	                    $route_infos[$key]['type'] = $types[$key];
                    }
                }
				$this->route_pricing($post_id, $route_infos, $ticket_types, $price_map);
				die();
			}
		}
		new WBTM_Pricing_Routing();
	}
