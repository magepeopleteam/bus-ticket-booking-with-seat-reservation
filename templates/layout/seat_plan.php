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
	$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
	// Check if cabin config exists AND has at least one enabled cabin
	$has_cabin_config = !empty($cabin_config) && count(array_filter($cabin_config, function($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;

	if (($has_cabin_config && sizeof($cabin_config) > 0) || (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0)) {
		$date = $_POST['date'] ?? '';
		$bus_start_time=$bus_start_time??'';
		$seat_position = WBTM_Global_Function::get_post_info($post_id, 'driver_seat_position', 'driver_left');
		$show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
		$seat_infos_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
		$adult_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_infos[0]['price']);
		$categories = apply_filters('wbtm_seat_categories', [
			['slug' => 'standard', 'label' => __('Standard', 'bus-ticket-booking-with-seat-reservation'), 'multiplier' => 1.0],
			['slug' => 'premium', 'label' => __('Premium', 'bus-ticket-booking-with-seat-reservation'), 'multiplier' => 1.2],
			['slug' => 'vip', 'label' => __('VIP', 'bus-ticket-booking-with-seat-reservation'), 'multiplier' => 1.5],
		]);
		$category_multiplier = [];
		$category_label = [];
		foreach ($categories as $cat) { 
			$category_multiplier[$cat['slug']] = floatval($cat['multiplier']); 
			$category_label[$cat['slug']] = $cat['label'];
		}
		$enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
        $enable_blocking = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_blocking', 'yes');
        $enable_price_override = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_price_override', 'yes');
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
				<?php if ($has_cabin_config) { ?>
					<?php
					// Render cabin seat plans
					foreach ($cabin_config as $cabin_index => $cabin) {
						if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;

						$cabin_name = $cabin['name'] ?? 'Cabin ' . ($cabin_index + 1);
						$cabin_rows = $cabin['rows'] ?? 0;
						$cabin_cols = $cabin['cols'] ?? 0;
						$price_multiplier = $cabin['price_multiplier'] ?? 1.0;
						$cabin_seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $cabin_index, []);

						if ($cabin_rows > 0 && $cabin_cols > 0 && !empty($cabin_seat_infos)) {
							$cabin_price = $adult_price * $price_multiplier;
							?>
							<div class="wbtm_cabin_section">
								<div class="wbtm_cabin_header wbtm_cabin_toggle" data-cabin-index="<?php echo esc_attr($cabin_index); ?>" style="cursor: pointer;">
									<div class="wbtm_cabin_title_container">
										<h4 class="wbtm_cabin_title" id="cabin-<?php echo esc_attr($cabin_index); ?>-title"><?php echo esc_html($cabin_name); ?></h4>
										<?php if ($price_multiplier != 1.0): ?>
											<div class="wbtm_cabin_price_info">
												<?php if ($price_multiplier > 1.0): ?>
													<span class="wbtm_price_multiplier"><?php printf(esc_html__('+%d%% Premium', 'bus-ticket-booking-with-seat-reservation'), (($price_multiplier - 1.0) * 100)); ?></span>
												<?php elseif ($price_multiplier < 1.0): ?>
													<span class="wbtm_price_discount"><?php printf(esc_html__('-%d%% Discount', 'bus-ticket-booking-with-seat-reservation'), ((1.0 - $price_multiplier) * 100)); ?></span>
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</div>
									<div class="wbtm_cabin_toggle_icon">
										<span class="wbtm_toggle_arrow" aria-label="Toggle cabin seats">▼</span>
									</div>
								</div>

								<div class="wbtm_cabin_seat_plan ovAuto" style="display: none;" aria-expanded="false" role="region" aria-labelledby="cabin-<?php echo esc_attr($cabin_index); ?>-title" data-cabin-index="<?php echo esc_attr($cabin_index); ?>">
									<input type="hidden" name="wbtm_selected_seat_cabin_<?php echo esc_attr($cabin_index); ?>" value=""/>
									<input type="hidden" name="wbtm_selected_seat_type_cabin_<?php echo esc_attr($cabin_index); ?>" value=""/>
									<table>
										<thead>
										<tr>
											<th colspan="<?php echo esc_attr($cabin_cols); ?>">
												<div class="wbtm_cabin_direction">
													<span class="wbtm_direction_text"><?php esc_html_e('Front', 'bus-ticket-booking-with-seat-reservation'); ?></span>
												</div>
											</th>
										</tr>
										</thead>
										<tbody>
										<?php foreach ($cabin_seat_infos as $row_index => $seat_info): ?>
											<tr>
												<?php foreach ($seat_info as $seat_key => $seat_name): ?>
													<?php
                                                    // Skip meta keys (rotation/category/blocked/price_overrides)
                                                    if (strpos($seat_key, '_rotation') !== false || strpos($seat_key, '_cat') !== false || strpos($seat_key, '_blocked') !== false || strpos($seat_key, '_price_adult') !== false || strpos($seat_key, '_price_child') !== false || strpos($seat_key, '_price_infant') !== false) {
														continue;
													}
													?>
													<?php if ($seat_name): ?>
														<?php if ($seat_name == 'door' || $seat_name == 'wc'): ?>
															<td><?php echo esc_html($seat_name); ?></td>
														<?php else: ?>
															<?php
															$rotation = 0;
															if ($enable_rotation == 'yes' && isset($seat_info[$seat_key . '_rotation'])) {
																$rotation = intval($seat_info[$seat_key . '_rotation']);
															}
                                                            $rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';
                                                            $is_blocked = ($enable_blocking === 'yes') && isset($seat_info[$seat_key . '_blocked']) && $seat_info[$seat_key . '_blocked'] === 'yes';
                                                            $seat_cat = isset($seat_info[$seat_key . '_cat']) ? $seat_info[$seat_key . '_cat'] : 'standard';
                                                            $seat_cat_mult = isset($category_multiplier[$seat_cat]) ? $category_multiplier[$seat_cat] : 1.0;
                                                            $override_adult = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_adult']) && $seat_info[$seat_key . '_price_adult'] !== '') ? (float)$seat_info[$seat_key . '_price_adult'] : null;
                                                            $override_child = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_child']) && $seat_info[$seat_key . '_price_child'] !== '') ? (float)$seat_info[$seat_key . '_price_child'] : null;
                                                            $override_infant = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_infant']) && $seat_info[$seat_key . '_price_infant'] !== '') ? (float)$seat_info[$seat_key . '_price_infant'] : null;
                                                            // Check if we should show tooltip (multiple ticket types OR price overrides exist)
                                                            $has_price_overrides = ($override_adult !== null || $override_child !== null || $override_infant !== null);
                                                            $show_tooltip_cabin = (sizeof($ticket_infos) > 1) || $has_price_overrides;
																													
															// Enhanced by Shahnur Alam - 2025-10-08
															// Fix cabin seat availability check - use cabin-specific identifiers
															$cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $seat_name;
															$is_booked = in_array($cabin_seat_identifier, $seat_booked) || in_array($seat_name, $seat_booked);
															$is_in_cart = !$is_booked && (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $cabin_seat_identifier) || WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name));
															?>
															<th>
																<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
                                                                    <?php if ($is_blocked): ?>
                                                                        <div class="mp_seat seat_booked" title="<?php echo esc_attr__('Blocked', 'bus-ticket-booking-with-seat-reservation') . ' : ' . esc_attr($seat_name); ?>">
                                                                            <div class="seat_visual"></div>
                                                                            <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                            <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                            <?php } ?>
                                                                        </div>
                                                                    <?php elseif ($is_booked): ?>
                                                                        <div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name); ?>">
																			<div class="seat_visual"></div>
																			<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                            <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                            <?php } ?>
																		</div>
																	<?php elseif ($is_in_cart): ?>
                                                                        <div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name); ?>">
																			<div class="seat_visual"></div>
																			<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                            <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                            <?php } ?>
																		</div>
																	<?php else: ?>
                                                                        <div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
																			data-seat_name="<?php echo esc_attr($seat_name); ?>"
																			data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
																			data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                            data-seat_price="<?php echo esc_attr($override_adult !== null ? $override_adult : ($cabin_price * $seat_cat_mult)); ?>"
										data-seat_category="<?php echo esc_attr($seat_cat); ?>"
																			data-cabin_index="<?php echo esc_attr($cabin_index); ?>"
																		>
																			<div class="seat_visual"></div>
																			<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                            <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                            <?php } ?>
																		</div>
																		<?php if ($show_tooltip_cabin): ?>
																			<div class="wbtm_seat_item_list">
																				<ul class="mp_list">
																					<?php 
																					// Build ticket list - include all ticket types from route pricing, plus any with price overrides
																					$ticket_list_cabin = [];
																					foreach ($ticket_infos as $key => $ticket_info) {
																						$ticket_list_cabin[] = [
																							'name' => $ticket_info['name'],
																							'type' => $ticket_info['type'],
																							'key' => $key
																						];
																					}
																					// Add Child if not in list but has override
																					if ($override_child !== null && !in_array(1, array_column($ticket_list_cabin, 'key'))) {
																						$ticket_list_cabin[] = [
																							'name' => WBTM_Translations::text_child(),
																							'type' => 1,
																							'key' => 1
																						];
																					}
																					// Add Infant if not in list but has override
																					if ($override_infant !== null && !in_array(2, array_column($ticket_list_cabin, 'key'))) {
																						$ticket_list_cabin[] = [
																							'name' => WBTM_Translations::text_infant(),
																							'type' => 2,
																							'key' => 2
																						];
																					}
																					
																					foreach ($ticket_list_cabin as $ticket_item_cabin):
																						$key = $ticket_item_cabin['key'];
																						$ticket_info_cabin = isset($ticket_infos[$key]) ? $ticket_infos[$key] : null;
																						$ticket_price = $key > 0 && $ticket_info_cabin ? $ticket_info_cabin['price'] : $adult_price;
																						if ($key === 0 && $override_adult !== null) {
																							$ticket_price = $override_adult;
																						} elseif ($key === 1 && $override_child !== null) {
																							$ticket_price = $override_child;
																						} elseif ($key === 2 && $override_infant !== null) {
																							$ticket_price = $override_infant;
																						} elseif ($key === 1 && isset($seat_info[$seat_key . '_price_child']) && $seat_info[$seat_key . '_price_child'] !== '') {
																							$ticket_price = (float)$seat_info[$seat_key . '_price_child'];
																						} elseif ($key === 2 && isset($seat_info[$seat_key . '_price_infant']) && $seat_info[$seat_key . '_price_infant'] !== '') {
																							$ticket_price = (float)$seat_info[$seat_key . '_price_infant'];
																						} else {
																							$ticket_price = $ticket_price * $price_multiplier * $seat_cat_mult;
																						}
																					?>
																						<li class="justifyBetween"
																							data-seat_label="<?php echo esc_attr($ticket_item_cabin['name']); ?>"
																							data-seat_type="<?php echo esc_attr($ticket_item_cabin['type']); ?>"
																							data-seat_price="<?php echo esc_attr($ticket_price); ?>"
																						>
																							<span><?php echo esc_html($ticket_item_cabin['name']); ?></span>
																							-
																							<span><?php echo wc_price($ticket_price); ?></span>
																						</li>
																					<?php endforeach; ?>
																				</ul>
																			</div>
																		<?php endif; ?>
																	<?php endif; ?>
																</div>
															</th>
														<?php endif; ?>
													<?php else: ?>
														<td></td>
													<?php endif; ?>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								</div>

								<?php if (sizeof($cabin_config) > 1): ?>
									<div class="wbtm_cabin_separator"></div>
								<?php endif; ?>
							</div>
							<?php
						}
					}
					?>
				<?php } else { ?>
					<!-- Legacy single bus seat plan -->
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
                                        // Skip meta keys (rotation/category/blocked/price_overrides)
                                        if (strpos($seat_key, '_rotation') !== false || strpos($seat_key, '_cat') !== false || strpos($seat_key, '_blocked') !== false || strpos($seat_key, '_price_adult') !== false || strpos($seat_key, '_price_child') !== false || strpos($seat_key, '_price_infant') !== false) {
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
                                                $is_blocked = ($enable_blocking === 'yes') && isset($seat_info[$seat_key . '_blocked']) && $seat_info[$seat_key . '_blocked'] === 'yes';
                                                $seat_cat = isset($seat_info[$seat_key . '_cat']) ? $seat_info[$seat_key . '_cat'] : 'standard';
                                                $seat_cat_mult = isset($category_multiplier[$seat_cat]) ? $category_multiplier[$seat_cat] : 1.0;
                                                $override_adult = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_adult']) && $seat_info[$seat_key . '_price_adult'] !== '') ? (float)$seat_info[$seat_key . '_price_adult'] : null;
                                                $override_child = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_child']) && $seat_info[$seat_key . '_price_child'] !== '') ? (float)$seat_info[$seat_key . '_price_child'] : null;
                                                $override_infant = ($enable_price_override === 'yes' && isset($seat_info[$seat_key . '_price_infant']) && $seat_info[$seat_key . '_price_infant'] !== '') ? (float)$seat_info[$seat_key . '_price_infant'] : null;
                                                // Check if we should show tooltip (multiple ticket types OR price overrides exist)
                                                $has_price_overrides = ($override_adult !== null || $override_child !== null || $override_infant !== null);
                                                $show_tooltip = (sizeof($ticket_infos) > 1) || $has_price_overrides;
												?>
												<th>
													<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
														<?php 
														// Enhanced by Shahnur Alam - 2025-10-08
														// Check both legacy seat names and cabin-specific identifiers for backward compatibility
														$is_booked_legacy = in_array($seat_name, $seat_booked);
														$is_in_cart_legacy = !$is_booked_legacy && WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name);
														?>
                                                        <?php if ($is_blocked) { ?>
                                                            <div class="mp_seat seat_booked" title="<?php echo esc_attr__('Blocked', 'bus-ticket-booking-with-seat-reservation') . ' : ' . esc_attr($seat_name); ?>">
                                                                <div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                            </div>
                                                        <?php } elseif ($is_booked_legacy) { ?>
                                                            <div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name); ?>">
                                                                <div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                            </div>
														<?php } elseif ($is_in_cart_legacy) { ?>
                                                            <div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name); ?>">
																<div class="seat_visual"></div>
																<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
															</div>
														<?php } else { ?>
							<div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
																data-seat_name="<?php echo esc_attr($seat_name); ?>"
																data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
																data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                data-seat_price="<?php echo esc_attr($override_adult !== null ? $override_adult : ($adult_price * $seat_cat_mult)); ?>"
								data-seat_category="<?php echo esc_attr($seat_cat); ?>"
															>
																<div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                <?php } ?>
															</div>
															<?php if ($show_tooltip) { ?>
																<div class="wbtm_seat_item_list">
																	<ul class="mp_list">
                                    <?php 
                                    // Build ticket list - include all ticket types from route pricing, plus any with price overrides
                                    $ticket_list = [];
                                    foreach ($ticket_infos as $key => $ticket_info) {
                                        $ticket_list[] = [
                                            'name' => $ticket_info['name'],
                                            'type' => $ticket_info['type'],
                                            'key' => $key
                                        ];
                                    }
                                    // Add Child if not in list but has override
                                    if ($override_child !== null && !in_array(1, array_column($ticket_list, 'key'))) {
                                        $ticket_list[] = [
                                            'name' => WBTM_Translations::text_child(),
                                            'type' => 1,
                                            'key' => 1
                                        ];
                                    }
                                    // Add Infant if not in list but has override
                                    if ($override_infant !== null && !in_array(2, array_column($ticket_list, 'key'))) {
                                        $ticket_list[] = [
                                            'name' => WBTM_Translations::text_infant(),
                                            'type' => 2,
                                            'key' => 2
                                        ];
                                    }
                                    
                                    foreach ($ticket_list as $ticket_item) {
                                        $key = $ticket_item['key'];
                                        $ticket_info = isset($ticket_infos[$key]) ? $ticket_infos[$key] : null;
                                        $ticket_price = $key > 0 && $ticket_info ? $ticket_info['price'] : $adult_price; 
                                        
                                        if ($key === 0 && $override_adult !== null) {
                                            $ticket_price = $override_adult;
                                        } elseif ($key === 1 && $override_child !== null) {
                                            $ticket_price = $override_child;
                                        } elseif ($key === 2 && $override_infant !== null) {
                                            $ticket_price = $override_infant;
                                        } elseif ($key === 1 && isset($seat_info[$seat_key . '_price_child']) && $seat_info[$seat_key . '_price_child'] !== '') {
                                            $ticket_price = (float)$seat_info[$seat_key . '_price_child'];
                                        } elseif ($key === 2 && isset($seat_info[$seat_key . '_price_infant']) && $seat_info[$seat_key . '_price_infant'] !== '') {
                                            $ticket_price = (float)$seat_info[$seat_key . '_price_infant'];
                                        } else {
                                            $ticket_price = $ticket_price * $seat_cat_mult; 
                                        }
                                        ?>
																			<li class="justifyBetween"
																				data-seat_label="<?php echo esc_attr($ticket_item['name']); ?>"
																				data-seat_type="<?php echo esc_attr($ticket_item['type']); ?>"
																				data-seat_price="<?php echo esc_attr($ticket_price); ?>"
																			>
																				<span><?php echo esc_html($ticket_item['name']); ?></span>
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
                                        // Skip meta keys (rotation/category/blocked/price_overrides)
                                        if (strpos($seat_key, '_rotation') !== false || strpos($seat_key, '_cat') !== false || strpos($seat_key, '_blocked') !== false || strpos($seat_key, '_price_adult') !== false || strpos($seat_key, '_price_child') !== false || strpos($seat_key, '_price_infant') !== false) {
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
                                                $is_blocked = ($enable_blocking === 'yes') && isset($seat_info_dd[$seat_key . '_blocked']) && $seat_info_dd[$seat_key . '_blocked'] === 'yes';
                                                $seat_cat = isset($seat_info_dd[$seat_key . '_cat']) ? $seat_info_dd[$seat_key . '_cat'] : 'standard';
                                                $seat_cat_mult = isset($category_multiplier[$seat_cat]) ? $category_multiplier[$seat_cat] : 1.0;
                                                $override_adult = ($enable_price_override === 'yes' && isset($seat_info_dd[$seat_key . '_price_adult']) && $seat_info_dd[$seat_key . '_price_adult'] !== '') ? (float)$seat_info_dd[$seat_key . '_price_adult'] : null;
                                                $override_child = ($enable_price_override === 'yes' && isset($seat_info_dd[$seat_key . '_price_child']) && $seat_info_dd[$seat_key . '_price_child'] !== '') ? (float)$seat_info_dd[$seat_key . '_price_child'] : null;
                                                $override_infant = ($enable_price_override === 'yes' && isset($seat_info_dd[$seat_key . '_price_infant']) && $seat_info_dd[$seat_key . '_price_infant'] !== '') ? (float)$seat_info_dd[$seat_key . '_price_infant'] : null;
                                                // Check if we should show tooltip (multiple ticket types OR price overrides exist)
                                                $has_price_overrides_dd = ($override_adult !== null || $override_child !== null || $override_infant !== null);
                                                $show_tooltip_dd = (sizeof($ticket_infos) > 1) || $has_price_overrides_dd;
																									
												// Enhanced by Shahnur Alam - 2025-10-08
												// Fix upper deck seat availability check - support cabin-specific identifiers
												$seat_available = WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $info);
												?>
												<th>
													<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
                                                        <?php if ($is_blocked) { ?>
                                                            <div class="mp_seat seat_booked" title="<?php echo esc_attr__('Blocked', 'bus-ticket-booking-with-seat-reservation') . ' : ' . esc_attr($info); ?>">
                                                                <div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                            </div>
                                                        <?php } elseif ($seat_available > 0) { ?>
                                                            <div class="mp_seat seat_booked" title="<?php echo WBTM_Translations::text_already_sold() . ' : ' . esc_attr($info); ?>">
                                                                <div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                            </div>
														<?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $info)) { ?>
                                                            <div class="mp_seat seat_in_cart" title="<?php echo WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($info); ?>">
																<div class="seat_visual"></div>
																<div class="seat_number"><?php echo esc_html($info); ?></div>
															</div>
														<?php } else { ?>
						<div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($info); ?>"
																data-seat_name="<?php echo esc_attr($info); ?>"
																data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
																data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                data-seat_price="<?php echo esc_attr($override_adult !== null ? $override_adult : ($adult_price_dd * $seat_cat_mult)); ?>"
							data-seat_category="<?php echo esc_attr($seat_cat); ?>"
															>
																<div class="seat_visual"></div>
                                                                <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                                <?php if ($seat_cat !== 'standard') { $cat_label = $category_label[$seat_cat] ?? ucfirst($seat_cat); ?>
                                                                <div class="seat_tag seat_tag_<?php echo esc_attr($seat_cat); ?>"><?php echo esc_html($cat_label); ?></div>
                                                                <?php } ?>
															</div>
															<?php if ($show_tooltip_dd) { ?>
																<div class="wbtm_seat_item_list">
																	<ul class="mp_list">
																		<?php 
																		// Build ticket list - include all ticket types from route pricing, plus any with price overrides
																		$ticket_list_dd = [];
																		foreach ($ticket_infos as $key => $ticket_info) {
																			$ticket_list_dd[] = [
																				'name' => $ticket_info['name'],
																				'type' => $ticket_info['type'],
																				'key' => $key
																			];
																		}
																		// Add Child if not in list but has override
																		if ($override_child !== null && !in_array(1, array_column($ticket_list_dd, 'key'))) {
																			$ticket_list_dd[] = [
																				'name' => WBTM_Translations::text_child(),
																				'type' => 1,
																				'key' => 1
																			];
																		}
																		// Add Infant if not in list but has override
																		if ($override_infant !== null && !in_array(2, array_column($ticket_list_dd, 'key'))) {
																			$ticket_list_dd[] = [
																				'name' => WBTM_Translations::text_infant(),
																				'type' => 2,
																				'key' => 2
																			];
																		}
																		
																		foreach ($ticket_list_dd as $ticket_item_dd) {
																			$key = $ticket_item_dd['key'];
																			$ticket_info_dd = isset($ticket_infos[$key]) ? $ticket_infos[$key] : null;
																			$ticket_price = $key > 0 && $ticket_info_dd ? WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_info_dd['price']) : $adult_price;
																			$ticket_price = $ticket_price + ($ticket_price * $seat_dd_increase / 100);
																			
																			if ($key === 0 && $override_adult !== null) {
																				$ticket_price = $override_adult;
																			} elseif ($key === 1 && $override_child !== null) {
																				$ticket_price = $override_child;
																			} elseif ($key === 2 && $override_infant !== null) {
																				$ticket_price = $override_infant;
																			} elseif ($key === 1 && isset($seat_info_dd[$seat_key . '_price_child']) && $seat_info_dd[$seat_key . '_price_child'] !== '') {
																				$ticket_price = (float)$seat_info_dd[$seat_key . '_price_child'];
																			} elseif ($key === 2 && isset($seat_info_dd[$seat_key . '_price_infant']) && $seat_info_dd[$seat_key . '_price_infant'] !== '') {
																				$ticket_price = (float)$seat_info_dd[$seat_key . '_price_infant'];
																			} else {
																				$ticket_price = $ticket_price * $seat_cat_mult;
																			}
																		?>
																			<li class="justifyBetween"
																				data-seat_label="<?php echo esc_attr($ticket_item_dd['name']); ?>"
																				data-seat_type="<?php echo esc_attr($ticket_item_dd['type']); ?>"
																				data-seat_price="<?php echo esc_attr($ticket_price); ?>"
																			>
																				<span><?php echo esc_html($ticket_item_dd['name']); ?></span>
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
