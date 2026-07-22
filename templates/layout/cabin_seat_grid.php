<?php
	/*
	* @Author 		MagePeople Team
	* Copyright: 	mage-people.com
	*
	* Renders ONE deck of a cabin's seat grid (lower or upper). Extracted from
	* seat_plan.php so a double-decker cabin/coach can render both decks with a
	* single source of truth. Expects these variables already in scope from the
	* caller (seat_plan.php cabin loop):
	*   $post_id, $start_route, $end_route, $date, $ticket_infos, $seat_booked,
	*   $wbtm_pl, $enable_rotation
	* plus these per-deck parameters set immediately before the require:
	*   $wbtm_grid_cabin_index      int    cabin position (0-based)
	*   $wbtm_grid_cols             int    columns for THIS deck
	*   $wbtm_grid_seat_infos       array  saved seat rows for THIS deck
	*   $wbtm_grid_price_multiplier float  cabin price multiplier
	*   $wbtm_grid_deck             string 'lower' | 'upper'
	*/
	if (!defined('ABSPATH')) {
		die;
	}
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$wbtm_grid_is_upper = ($wbtm_grid_deck === 'upper');
	// Upper deck seats live under a distinct "cabin_{i}_dd_{seat}" identifier so
	// a seat labelled "A1" on the upper deck never collides with "A1" on the
	// lower deck of the same coach — for booked/in-cart checks AND client-side
	// disambiguation via data-cabin_index.
	$wbtm_grid_id_prefix        = 'cabin_' . $wbtm_grid_cabin_index . ($wbtm_grid_is_upper ? '_dd_' : '_');
	$wbtm_grid_data_cabin_index = $wbtm_grid_is_upper ? ($wbtm_grid_cabin_index . '_dd') : $wbtm_grid_cabin_index;
	?>
	<table>
		<thead>
		<tr>
			<th colspan="<?php echo esc_attr($wbtm_grid_cols); ?>">
				<div class="wbtm_cabin_direction">
					<span class="wbtm_direction_text"><?php esc_html_e('Front', 'bus-ticket-booking-with-seat-reservation'); ?></span>
				</div>
			</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($wbtm_grid_seat_infos as $row_index => $seat_info): ?>
			<tr>
				<?php foreach ($seat_info as $seat_key => $seat_name): ?>
					<?php
					// Skip rotation keys (they end with _rotation)
					if (strpos($seat_key, '_rotation') !== false) {
						continue;
					}
					$seat_name = wbtm_normalize_seat_value_check($seat_name);
					?>
					<?php if ($seat_name): ?>
						<?php if (wbtm_is_non_seat_item_check($seat_name)):
							$ns_data = wbtm_get_non_seat_data_check($seat_name);
							?>
							<?php if (strtolower(trim($seat_name)) === 'aisle'): ?>
								<td class="wbtm_aisle_blank"></td>
							<?php else: ?>
								<td class="wbtm_non_seat_item" title="<?php echo esc_attr($ns_data['label']); ?>">
									<span class="wbtm_non_seat_icon fas <?php echo esc_attr($ns_data['icon']); ?>"></span>
									<span class="wbtm_non_seat_label"><?php echo esc_html($ns_data['label']); ?></span>
								</td>
							<?php endif; ?>
						<?php else: ?>
							<?php
							$rotation = 0;
							if ($enable_rotation == 'yes' && isset($seat_info[$seat_key . '_rotation'])) {
								$rotation = intval($seat_info[$seat_key . '_rotation']);
							}
							$rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';

							$cabin_seat_identifier = $wbtm_grid_id_prefix . $seat_name;
							// Lower deck keeps the legacy bare-name fallback (older
							// bookings/cart entries predate cabin identifiers); the
							// upper deck is new, so it matches ONLY on its own
							// deck-scoped identifier to avoid cross-deck collisions.
							$is_booked = in_array($cabin_seat_identifier, $seat_booked)
								|| (!$wbtm_grid_is_upper && in_array($seat_name, $seat_booked));
							$is_in_cart = !$is_booked && (
								WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $cabin_seat_identifier)
								|| (!$wbtm_grid_is_upper && WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name))
							);
							$cell_base_cabin = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_infos[0]['type'], false, $wbtm_pl, $seat_name, $wbtm_grid_cabin_index, $date);
							if ($cell_base_cabin === false) {
								$cell_base_cabin = 0;
							}
							$cabin_price = floatval($cell_base_cabin) * floatval($wbtm_grid_price_multiplier);
							?>
							<th>
								<div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
									<?php if ($is_booked): ?>
										<div class="mp_seat seat_booked" title="<?php echo esc_html( WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name) ); ?>">
											<div class="seat_visual"></div>
											<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
										</div>
									<?php elseif ($is_in_cart): ?>
										<div class="mp_seat seat_in_cart" title="<?php echo esc_html( WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name) ); ?>">
											<div class="seat_visual"></div>
											<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
										</div>
									<?php else: ?>
										<div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
											 data-seat_name="<?php echo esc_attr($seat_name); ?>"
											 data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
											 data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
											 data-seat_price="<?php echo esc_attr($cabin_price); ?>"
											 data-cabin_index="<?php echo esc_attr($wbtm_grid_data_cabin_index); ?>"
										>
											<div class="seat_visual"></div>
											<div class="seat_number"><?php echo esc_html($seat_name); ?></div>
										</div>
										<?php if (sizeof($ticket_infos) > 1): ?>
											<div class="wbtm_seat_item_list">
												<ul class="mp_list">
													<?php foreach ($ticket_infos as $key => $ticket_info): ?>
														<?php
														$cell_t_cabin = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, $ticket_info['type'], false, $wbtm_pl, $seat_name, $wbtm_grid_cabin_index, $date);
														if ($cell_t_cabin === false) {
															$cell_t_cabin = 0;
														}
														$ticket_price = floatval($cell_t_cabin) * floatval($wbtm_grid_price_multiplier);
														?>
														<li class="justifyBetween"
															data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
															data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
															data-seat_price="<?php echo esc_attr($ticket_price); ?>"
														>
															<span><?php echo esc_html($ticket_info['name']); ?></span>
															-
															<span><?php echo wp_kses_post( WBTM_Global_Function::format_price( $ticket_price ) ); ?></span>
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
	<?php
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
