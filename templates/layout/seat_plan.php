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
	
	if (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0) {
		$date = $_POST['date'] ?? '';
		$bus_start_time=$bus_start_time??'';
		$seat_position = WBTM_Global_Function::get_post_info($post_id, 'driver_seat_position', 'driver_left');
		$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
		$seat_infos_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
		$adult_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_infos[0]['price']);
		$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
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
								<?php foreach ($seat_info as $seat_key => $seat_name) { ?>
									<?php 
									// Skip rotation keys (they end with _rotation)
									if (strpos($seat_key, '_rotation') !== false) {
										continue;
									}
									?>
									<?php if ($seat_name) { ?>
										<?php if ($seat_name == 'door' || $seat_name == 'wc') { ?>
											<td><?php echo esc_html($seat_name); ?></td>
										<?php } else { ?>
											<?php 
											$rotation = 0;
											if ($enable_rotation == 'yes' && isset($seat_info[$seat_key . '_rotation'])) {
												$rotation = intval($seat_info[$seat_key . '_rotation']);
											}
											$rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';
											?>
											<th>
												<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
													<?php //$sold_seats = WBTM_Query:: query_total_booked($post_id, $start_route, $end_route, $date, '', $seat_name); ?>
													<?php if (in_array($seat_name,$seat_booked)) { ?>
														<div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name); ?>"><?php echo esc_html($seat_name); ?></div>
													<?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name)) { ?>
														<div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name); ?>"><?php echo esc_html($seat_name); ?></div>
													<?php } else { ?>
														<div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
															data-seat_name="<?php echo esc_attr($seat_name); ?>"
															data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
															data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
															data-seat_price="<?php echo esc_attr($adult_price); ?>"
														>
															<?php echo esc_html($seat_name); ?>
														</div>
														<?php if (sizeof($ticket_infos) > 1) { ?>
															<div class="wbtm_seat_item_list">
																<ul class="mp_list">
																	<?php foreach ($ticket_infos as $key => $ticket_info) { ?>
																		<?php $ticket_price = $key > 0 ? $ticket_info['price'] : $adult_price; ?>
																		<li class="justifyBetween"
																			data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
																			data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
																			data-seat_price="<?php echo esc_attr($ticket_price); ?>"
																		>
																			<span><?php echo esc_html($ticket_info['name']); ?></span>
																			-
																			<span><?php echo wc_price($ticket_price); ?></span>
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
									<?php foreach ($seat_info_dd as $seat_key => $info) { ?>
										<?php 
										// Skip rotation keys (they end with _rotation)
										if (strpos($seat_key, '_rotation') !== false) {
											continue;
										}
										?>
										<?php if ($info) { ?>
											<?php if ($info == 'door' || $info == 'wc') { ?>
												<td><?php echo esc_html($info) ?></td>
											<?php } else { ?>
												<?php 
												$rotation = 0;
												if ($enable_rotation == 'yes' && isset($seat_info_dd[$seat_key . '_rotation'])) {
													$rotation = intval($seat_info_dd[$seat_key . '_rotation']);
												}
												$rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';
												?>
												<th>
													<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
														<?php $seat_available = WBTM_Query:: query_total_booked($post_id, $start_route, $end_route, $date, '', $info); ?>
														<?php if ($seat_available > 0) { ?>
															<div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($info); ?>"><?php echo esc_html($info); ?></div>
														<?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $info)) { ?>
															<div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($info); ?>"><?php echo esc_html($info); ?></div>
														<?php } else { ?>
															<div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($info); ?>"
																data-seat_name="<?php echo esc_attr($info); ?>"
																data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
																data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
																data-seat_price="<?php echo esc_attr($adult_price_dd); ?>"
															>
																<?php echo esc_html($info); ?>
															</div>
															<?php if (sizeof($ticket_infos) > 1) { ?>
																<div class="wbtm_seat_item_list">
																	<ul class="mp_list">
																		<?php foreach ($ticket_infos as $key => $ticket_info) { ?>
																			<?php
																			$ticket_price = $key > 0 ? WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_info['price']) : $adult_price;
																			$ticket_price = $ticket_price + ($ticket_price * $seat_dd_increase / 100);
																			?>
																			<li class="justifyBetween"
																				data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
																				data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
																				data-seat_price="<?php echo esc_attr($ticket_price); ?>"
																			>
																				<span><?php echo esc_html($ticket_info['name']); ?></span>
																				-
																				<span><?php echo wc_price($ticket_price); ?></span>
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