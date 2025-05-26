<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? WBTM_Global_Function::data_sanitize($_POST['post_id']);
	$start_route = $start_route ?? WBTM_Global_Function::data_sanitize($_POST['start_route']);
	$end_route = $end_route ?? WBTM_Global_Function::data_sanitize($_POST['end_route']);
	$ticket_infos = $ticket_infos ?? WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route);
	
	$seat_row = $seat_row ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
	$seat_column = $seat_column ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
	$seat_infos = $seat_infos ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
	
	$blocked_seats = get_post_meta($post_id, 'wbtm_blocked_seats', true);
	if (!is_array($blocked_seats)) $blocked_seats = [];
	$blocked_seats_dd = get_post_meta($post_id, 'wbtm_blocked_seats_dd', true);
	if (!is_array($blocked_seats_dd)) $blocked_seats_dd = [];
	
	$seat_types = get_post_meta($post_id, 'wbtm_seat_types', true);
	if (!is_array($seat_types)) $seat_types = [];
	$seat_labels = get_post_meta($post_id, 'wbtm_seat_labels', true);
	if (!is_array($seat_labels)) $seat_labels = [];
	$seat_types_dd = get_post_meta($post_id, 'wbtm_seat_types_dd', true);
	if (!is_array($seat_types_dd)) $seat_types_dd = [];
	$seat_labels_dd = get_post_meta($post_id, 'wbtm_seat_labels_dd', true);
	if (!is_array($seat_labels_dd)) $seat_labels_dd = [];
	
	if (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0) {
		$date = $_POST['date'] ?? '';
		$bus_start_time=$bus_start_time??'';
		$seat_position = WBTM_Global_Function::get_post_info($post_id, 'driver_seat_position', 'driver_left');
		$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
		$seat_infos_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
		$adult_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_infos[0]['price']);
		//echo current($seat_infos)['price'];
		$seat_booked=WBTM_Query:: query_seat_booked($post_id, $start_route, $end_route, $bus_start_time);
		$seat_count = 0;
		foreach ($seat_infos as $seats) {
			foreach ($seats as $seat) {
				if (!empty($seat)) {
					$seat_count++;
				}
			}
		}
		$user_email = '';
		if (is_user_logged_in()) {
			$current_user = wp_get_current_user();
			$user_email = $current_user->user_email;
		} elseif (isset($_POST['billing_email'])) {
			$user_email = sanitize_email($_POST['billing_email']);
		}
		?>
		<div class="_dLayout_xs">
			<?php //echo '<pre>'; print_r($seat_booked); echo '</pre>'; ?>
			<div class="wbtm_seat_plan_area">
				<div class="wbtm_seat_plan_lower ovAuto">
					<input type="hidden" name="wbtm_selected_seat" value=""/>
					<input type="hidden" name="wbtm_selected_seat_type" value=""/>
					<table>
						<thead>
						<tr>
							<th colspan="<?php echo esc_attr($seat_column); ?>">
								<div class="mp_driver_image <?php echo esc_attr($seat_position == 'driver_left' ? '' : 'fRight'); ?>">
									<?php WBTM_Custom_Layout::bg_image('', WBTM_PLUGIN_URL . '/assets/images/wbtm-driving-wheel.svg'); ?>
								</div>
							</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($seat_infos as $seat_info) { ?>
							<tr>
								<?php foreach ($seat_info as $seat_name) { ?>
									<?php if ($seat_name) { ?>
										<?php if ($seat_name == 'door' || $seat_name == 'wc') { ?>
											<td><?php echo esc_html($seat_name); ?></td>
										<?php } elseif (isset($blocked_seats[$seat_name])) { ?>
											<th>
												<div class="mp_seat seat_blocked" title="<?php echo esc_attr__('Blocked', 'bus-ticket-booking-with-seat-reservation') . ' : ' . esc_attr($seat_name); ?>">
													<?php echo esc_html($seat_name); ?>
												</div>
											</th>
										<?php } else { ?>
											<th>
												<div class="mp_seat_item">
													<?php $type = isset($seat_types[$seat_name]) ? $seat_types[$seat_name] : ''; ?>
													<?php $label = isset($seat_labels[$seat_name]) ? $seat_labels[$seat_name] : ''; ?>
													<?php if ($type) { ?>
														<span class="wbtm_seat_type_badge wbtm_seat_type_<?php echo esc_attr($type); ?>" title="<?php echo esc_attr(ucfirst($type)); ?>">
															<?php echo esc_html(ucfirst($type)); ?>
														</span>
													<?php } ?>
													<?php if ($label) { ?>
														<span class="wbtm_seat_label" title="<?php echo esc_attr($label); ?>">(<?php echo esc_html($label); ?>)</span>
													<?php } ?>
													<?php if (in_array($seat_name,$seat_booked)) { ?>
														<div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name); ?>"><?php echo esc_html($seat_name); ?></div>
													<?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name)) { ?>
														<div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name); ?>"><?php echo esc_html($seat_name); ?></div>
													<?php } else { ?>
														<?php
														// For each seat, build tooltip with correct prices for each type
														$tooltip_parts = [];
														foreach ([0,1,2] as $ticket_type) {
															$type_name = WBTM_Functions::get_ticket_name($ticket_type);
															$price_data = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_type, false, $seat_name, $date, $user_email);
															if (is_array($price_data) && isset($price_data['promo_percent'])) {
																$price = $price_data['price'];
																$original = $price_data['original_price'];
																$promo = $price_data['promo_percent'];
																$tooltip_parts[] = esc_html($type_name) . ': ' . esc_html($price) . get_woocommerce_currency_symbol() . " (was $original, -$promo%)";
															} else {
																$price = is_array($price_data) ? $price_data['price'] : $price_data;
																$tooltip_parts[] = esc_html($type_name) . ': ' . esc_html($price) . get_woocommerce_currency_symbol();
															}
														}
														$seat_tooltip = implode(' | ', $tooltip_parts);
														?>
														<?php
														$price_data = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_infos[0]['type'], false, $seat_name, $date, $user_email);
														$seat_price_json = is_array($price_data) ? $price_data : [ 'price' => $price_data ];
														?>
														<div class="mp_seat seat_available" title="<?php echo esc_attr($seat_tooltip); ?>"
															data-seat_name="<?php echo esc_attr($seat_name); ?>"
															data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
															data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
															data-seat_price='<?php echo json_encode($seat_price_json); ?>'
														>
															<?php echo esc_html($seat_name); ?>
														</div>
														<?php if (sizeof($ticket_infos) > 1) { ?>
															<div class="wbtm_seat_item_list">
																<ul class="mp_list">
																	<?php foreach ($ticket_infos as $key => $ticket_info) { ?>
																		<?php 
																		$ticket_price = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_info['type'], false, $seat_name, $date, $user_email);
																		$ticket_price_val = is_array($ticket_price) ? $ticket_price['price'] : $ticket_price;
																		?>
																		<li class="justifyBetween"
																			data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
																			data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
																			data-seat_price="<?php echo esc_attr($ticket_price_val); ?>"
																		>
																			<span><?php echo esc_html($ticket_info['name']); ?></span>
																			-
																			<span><?php echo wc_price($ticket_price_val); ?></span>
																		</li>
																	<?php } ?>
																</ul>
															</div>
														<?php } ?>
													<?php } ?>
												</div>
											</th>
										<?php } ?>
									<?php } else { ?>
										<td></td>
									<?php } ?>
								<?php } ?>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<?php if ($show_upper_desk == 'yes' && sizeof($seat_infos_dd) > 0) { ?>
					<?php
					$seat_dd_increase = (int)WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_dd_price_parcent', 0);
					$adult_price_dd = $adult_price + ($adult_price * $seat_dd_increase / 100);
					?>
					<div class="wbtm_seat_plan_upper ovAuto">
						<input type="hidden" name="wbtm_selected_seat_dd" value=""/>
						<input type="hidden" name="wbtm_selected_seat_dd_type" value=""/>
						<div class="divider"></div>
						<h4 class="_textCenter_textTheme"><?php echo WBTM_Translations::text_upper_deck(); ?></h4>
						<div class="divider"></div>
						<table>
							<tbody>
							<?php foreach ($seat_infos_dd as $seat_info_dd) { ?>
								<tr>
									<?php foreach ($seat_info_dd as $info) { ?>
										<?php if ($info) { ?>
											<?php if ($info == 'door' || $info == 'wc') { ?>
												<td><?php echo esc_html($info) ?></td>
											<?php } elseif (isset($blocked_seats_dd[$info])) { ?>
												<th>
													<div class="mp_seat seat_blocked" title="<?php echo esc_attr__('Blocked', 'bus-ticket-booking-with-seat-reservation') . ' : ' . esc_attr($info); ?>">
														<?php echo esc_html($info); ?>
													</div>
												</th>
											<?php } else { ?>
												<th>
													<div class="mp_seat_item">
														<?php $type = isset($seat_types_dd[$info]) ? $seat_types_dd[$info] : ''; ?>
														<?php $label = isset($seat_labels_dd[$info]) ? $seat_labels_dd[$info] : ''; ?>
														<?php if ($type) { ?>
															<span class="wbtm_seat_type_badge wbtm_seat_type_<?php echo esc_attr($type); ?>" title="<?php echo esc_attr(ucfirst($type)); ?>">
																<?php echo esc_html(ucfirst($type)); ?>
															</span>
														<?php } ?>
														<?php if ($label) { ?>
															<span class="wbtm_seat_label" title="<?php echo esc_attr($label); ?>">(<?php echo esc_html($label); ?>)</span>
														<?php } ?>
														<?php $seat_available = WBTM_Query:: query_total_booked($post_id, $start_route, $end_route, $date, '', $info); ?>
														<?php if ($seat_available > 0) { ?>
															<div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($info); ?>"><?php echo esc_html($info); ?></div>
														<?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $info)) { ?>
															<div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($info); ?>"><?php echo esc_html($info); ?></div>
														<?php } else { ?>
															<?php
															// For each seat, build tooltip with correct prices for each type
															$tooltip_parts = [];
															foreach ([0,1,2] as $ticket_type) {
																$type_name = WBTM_Functions::get_ticket_name($ticket_type);
																$price_data = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_type, true, $info, $date, $user_email);
																if (is_array($price_data) && isset($price_data['promo_percent'])) {
																	$price = $price_data['price'];
																	$original = $price_data['original_price'];
																	$promo = $price_data['promo_percent'];
																	$tooltip_parts[] = esc_html($type_name) . ': ' . esc_html($price) . get_woocommerce_currency_symbol() . " (was $original, -$promo%)";
																} else {
																	$price = is_array($price_data) ? $price_data['price'] : $price_data;
																	$tooltip_parts[] = esc_html($type_name) . ': ' . esc_html($price) . get_woocommerce_currency_symbol();
																}
															}
															$seat_tooltip = implode(' | ', $tooltip_parts);
															?>
															<?php
															$price_data = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_infos[0]['type'], true, $info, $date, $user_email);
															$seat_price_json = is_array($price_data) ? $price_data : [ 'price' => $price_data ];
															?>
															<div class="mp_seat seat_available" title="<?php echo esc_attr($seat_tooltip); ?>"
																data-seat_name="<?php echo esc_attr($info); ?>"
																data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
																data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
																data-seat_price='<?php echo json_encode($seat_price_json); ?>'
															>
																<?php echo esc_html($info); ?>
															</div>
															<?php if (sizeof($ticket_infos) > 1) { ?>
																<div class="wbtm_seat_item_list">
																	<ul class="mp_list">
																		<?php foreach ($ticket_infos as $key => $ticket_info) { ?>
																			<?php 
																			$ticket_price = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_info['type'], true, $info, $date, $user_email);
																			$ticket_price_val = is_array($ticket_price) ? $ticket_price['price'] : $ticket_price;
																			?>
																			<li class="justifyBetween"
																				data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
																				data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
																				data-seat_price="<?php echo esc_attr($ticket_price_val); ?>"
																			>
																				<span><?php echo esc_html($ticket_info['name']); ?></span>
																				-
																				<span><?php echo wc_price($ticket_price_val); ?></span>
																			</li>
																		<?php } ?>
																	</ul>
																</div>
															<?php } ?>
														<?php } ?>
													</div>
												</th>
											<?php } ?>
										<?php } else { ?>
											<td></td>
										<?php } ?>
									<?php } ?>
								</tr>
							<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php
		//echo '<pre>'; print_r($seat_infos); echo '</pre>';
	}
	else {
		WBTM_Layout::msg(WBTM_Translations::text_no_seat_plan());
	}