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
	$style = $style ?? '';
	$label = WBTM_Functions::get_name();
	$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
	if (sizeof($bus_ids) > 0) {
		$bus_count = 0;

		?>
		<div class="wbtm_bus_list_area">
			<input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>"/>
			<input type="hidden" name="wbtm_end_route" value="<?php echo esc_attr($end_route); ?>"/>
			<input type="hidden" name="wbtm_date" value="<?php echo esc_attr(date('Y-m-d', strtotime($date))); ?>"/>
			
			<?php foreach ($bus_ids as $bus_id) : ?>
				<?php //echo '<pre>'; print_r(WBTM_Query:: query_seat_booked($bus_id, $start_route, $end_route, $date)); echo '</pre>'; ?>
				<?php  
					$all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route); 
					if (sizeof($all_info) > 0) :
						$bus_count++;
						$price = $all_info['price'];
						
				?>
				<!-- default style -->
				<div class="wbtm-bust-list _dFlex_mT">
					<div class="wbtm-bus-image ">
						<?php MP_Custom_Layout::bg_image($bus_id); ?>
					</div>
					<div class="wbtm-bus-name text-start">
						<h5 class="_textTheme" data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>"><?php echo get_the_title($bus_id); ?></h5>
						<p><?php echo esc_html(MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_no')); ?></p>
					</div>
					<div class="wbtm-bus-route text-start">
						<h6>
							<span class="fa fa-map-pin"></span>
							<?php echo esc_html($all_info['bp']) . ' ' . esc_html($all_info['bp_time'] ? '(' . MP_Global_Function::date_format($all_info['bp_time'], 'time') . ')' : ''); ?>
						</h6>
						<h6>
							<i class="fas fa-map-marker-alt"></i>
							<?php echo esc_html($all_info['dp']) . ' ' . esc_html($all_info['dp_time'] ? '(' . MP_Global_Function::date_format($all_info['dp_time'], 'time') . ')' : ''); ?>
						</h6>
					</div>
					<div class="wbtm-seat-info text-center">
						<div>
							<h6><?php echo MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_category'); ?></h6>
							<p><?php echo WBTM_Translations::text_coach_type(); ?></p>
						</div>
						<div>
							<h6><?php echo esc_html($all_info['available_seat']); ?>/<?php echo esc_html($all_info['total_seat']); ?></h6>
							<p><?php echo WBTM_Translations::text_available(); ?></p>
						</div>
						<div>
							<h6><?php echo wc_price($price); ?></h6>
							<p><?php echo WBTM_Translations::text_fare().'/' . WBTM_Translations::text_seat(); ?></p>
						</div>
					</div>
					<div class="wbtm-seat-book">
						<button type="button" class="_themeButton_xs" id="get_wbtm_bus_details"
							data-bus_id="<?php echo esc_attr($bus_id); ?>"
							data-open-text="<?php echo esc_attr(WBTM_Translations::text_view_seat()); ?>"
							data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
							data-add-class="mActive">
							<span data-text><?php echo esc_html(WBTM_Translations::text_view_seat()); ?></span>
						</button>
					</div>
					
				</div>
				<div class="wbtm_bus_details mT_xs" data-row_id="<?php echo esc_attr($bus_id); ?>">
					<!--  bus details will display here -->
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ($bus_count == 0) : ?>
				<div>
					<?php WBTM_Layout::msg(WBTM_Translations::text_no_bus()); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
	else {
		WBTM_Layout::msg(WBTM_Translations::text_no_bus());
	}
//echo '<pre>';	print_r($bus_ids);	echo '</pre>';