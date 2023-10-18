<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$start_route = $start_route ?? '';
	$end_route = $end_route ?? '';
	$post_id = $post_id ?? '';
	$date = $date ?? '';
	$label = WBTM_Functions::get_name();
	$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
	if (sizeof($bus_ids) > 0) {
		$bus_count = 0;
		?>
		<div class="wbtm_bus_list_area">
			<input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>"/>
			<input type="hidden" name="wbtm_end_route" value="<?php echo esc_attr($end_route); ?>"/>
			<input type="hidden" name="wbtm_date" value="<?php echo esc_attr(date('Y-m-d', strtotime($date))); ?>"/>
			<table class="_layoutFixed">
				<thead>
				<tr>
					<th><?php echo WBTM_Translations::text_image(); ?></th>
					<th colspan="4">
						<div class="flexEqual">
							<span><?php echo esc_html($label) . ' ' . WBTM_Translations::text_name(); ?></span>
							<span><?php echo WBTM_Translations::text_schedule(); ?></span>
						</div>
					</th>
					<th colspan="4" class="_textCenter">
						<div class="flexEqual">
							<span><?php echo WBTM_Translations::text_coach_type(); ?></span>
							<span>
								<?php echo WBTM_Translations::text_fare(); ?>
								<sub><?php echo '/' . WBTM_Translations::text_seat(); ?></sub>
							</span>
							<span><?php echo WBTM_Translations::text_available(); ?></span>
							<span><?php echo WBTM_Translations::text_action(); ?></span>
						</div>
					</th>
				</tr>
				</thead>
				<tbody class="_bgWhite">
				<?php foreach ($bus_ids as $bus_id) { ?>
					<?php
					$all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route);
					if (sizeof($all_info) > 0) {
						$bus_count++;
						$price = $all_info['price'];
						?>
						<tr class="bus_item_row">
							<td><?php MP_Custom_Layout::bg_image($bus_id); ?></td>
							<td colspan="4">
								<div class="flexEqual">
									<div>
										<h6 class="_textTheme" data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>"><?php echo get_the_title($bus_id); ?></h6>
										<small class="dBlock"><?php echo esc_html(MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_no')); ?></small>
										<?php if (WBTM_Functions::check_bus_in_cart($bus_id)) { ?>
											<h6 class="textSuccess"><?php echo WBTM_Translations::text_already_in_cart(); ?></h6>
										<?php } ?>
									</div>
									<div class="_fdColumn">
										<h6>
											<span class="fa fa-angle-double-right"></span>
											<?php echo esc_html($all_info['bp']) . ' ' . esc_html($all_info['bp_time'] ? ' (' . MP_Global_Function::date_format($all_info['bp_time'], 'full') . ' )' : ''); ?>
										</h6>
										<h6>
											<span class="fa fa-stop"></span>
											<?php echo esc_html($all_info['dp']) . ' ' . esc_html($all_info['dp_time'] ? ' (' . MP_Global_Function::date_format($all_info['dp_time'], 'full') . ' )' : ''); ?>
										</h6
									</div>
								</div>
							</td>
							<td colspan="4" class="_textCenter">
								<div class="flexEqual">
									<h6><?php echo MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_category'); ?></h6>
									<div>
										<strong><?php echo wc_price($price); ?></strong>
									</div>
									<h6> <?php echo esc_html($all_info['available_seat']); ?>/<?php echo esc_html($all_info['total_seat']); ?> </h6>
									<div class="_allCenter">
										<button type="button" class="_dButton_xs" id="get_wbtm_bus_details"
											data-bus_id="<?php echo esc_attr($bus_id); ?>"
											data-open-text="<?php echo esc_attr(WBTM_Translations::text_view_seat()); ?>"
											data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
											data-add-class="mActive"
										>
											<span data-text><?php echo esc_html(WBTM_Translations::text_view_seat()); ?></span>
										</button>
									</div>
								</div>
							</td>
						</tr>
						<tr data-row_id="<?php echo esc_attr($bus_id); ?>">
							<td colspan="9" class="wbtm_bus_details"></td>
						</tr>
					<?php } ?>
				<?php } ?>
				<?php if ($bus_count == 0) { ?>
					<tr>
						<td colspan="9">
							<?php WBTM_Layout::msg(WBTM_Translations::text_no_bus()); ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php
	}
	else {
		WBTM_Layout::msg(WBTM_Translations::text_no_bus());
	}
//echo '<pre>';	print_r($bus_ids);	echo '</pre>';