<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
$search_info = $search_info ?? [];
$start_route = $start_route ?? '';
$end_route = $end_route ?? '';
$post_id = $post_id ?? '';
$date = $date ?? '';
$btn_show = $btn_show ?? '';
$label = WBTM_Functions::get_name();
$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
if (sizeof($bus_ids) > 0) {
	$bus_count = 0;

?>
	<!-- new layout -->

	<div class="wbtm_bus_list_area">
		<input type="hidden" name="bus_start_route" value="<?php echo esc_attr(array_key_exists('bus_start_route', $search_info) ? $search_info['bus_start_route'] : ''); ?>" />
		<input type="hidden" name="bus_end_route" value="<?php echo esc_attr(array_key_exists('bus_end_route', $search_info) ? $search_info['bus_end_route'] : ''); ?>" />
		<input type="hidden" name="j_date" value="<?php echo esc_attr(array_key_exists('j_date', $search_info) ? $search_info['j_date'] : ''); ?>" />
		<input type="hidden" name="r_date" value="<?php echo esc_attr(array_key_exists('r_date', $search_info) ? $search_info['r_date'] : ''); ?>" />
		<input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>" />
		<input type="hidden" name="wbtm_end_route" value="<?php echo esc_attr($end_route); ?>" />
		<input type="hidden" name="wbtm_date" value="<?php echo esc_attr(date('Y-m-d', strtotime($date))); ?>" />

		<?php
		// Collect all bus info first
		$bus_data = [];

		foreach ($bus_ids as $bus_id) {
			$all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route);
			if (sizeof($all_info) > 0) {
				$bus_data[] = [
					'bus_id'   => $bus_id,
					'all_info' => $all_info,
				];
			}
		}

		// Sort bus data by 'bp_time' in 24-hour format
		usort($bus_data, function ($a, $b) {
			return strtotime($a['all_info']['bp_time']) - strtotime($b['all_info']['bp_time']);
		});

		// Now loop through the sorted data
		foreach ($bus_data as $bus) {
			$bus_id = $bus['bus_id'];
			$all_info = $bus['all_info'];
			$bus_count++;
			$price = $all_info['price'];
		?>

			<!-- short code new style flix if set -->
			<div class="wbtm-bus-flix-style <?php echo esc_attr(MP_Global_Function::check_product_in_cart($post_id) ? 'in_cart' : ''); ?>">
				<div class="title">
					<h5 data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>"><?php echo get_the_title($bus_id); ?></h5>
					<p><span><?php echo esc_html(MP_Global_Function::get_post_info($bus_id, 'wbtm_bus_no')); ?></span></p>
				</div>
				<div class="route">
					<div class="route-info">
						<div class="from">
							<h2 class="textTheme"><?php echo esc_html($all_info['bp_time'] ? MP_Global_Function::date_format($all_info['bp_time'], 'time') : ''); ?></h2>
							<p><strong><?php echo esc_html($all_info['bp']); ?></strong></p>
						</div>
						<div class="duration textCenter">
							<strong class="time"><?php echo MP_Global_Function::date_difference($all_info['bp_time'], $all_info['dp_time']); ?></strong>
						</div>
						<div class="to">
							<h2 class="textTheme"><?php echo esc_html($all_info['dp_time'] ? MP_Global_Function::date_format($all_info['dp_time'], 'time') : ''); ?></h2>
							<p><strong><?php echo esc_html($all_info['dp']); ?></strong></p>
						</div>
					</div>
				</div>
				<div class="feature">
					<div class="items">
						<p><?php echo WBTM_Translations::text_available(); ?> <strong><?php echo esc_html($all_info['available_seat']); ?>/<?php echo esc_html($all_info['total_seat']); ?></strong></p>
					</div>
				</div>
				<div class="price">
					<h4 class="textTheme"><?php echo wc_price($price); ?></h4>
				</div>
				<?php
				if ($btn_show == 'hide' && $all_info['regi_status'] == 'no') {
					WBTM_Layout::trigger_view_seat_details();
				}
				?>
				<button type="button" class="_themeButton_xs wbtm-seat-book <?php echo $btn_show; ?>" id="get_wbtm_bus_details"
					data-bus_id="<?php echo esc_attr($bus_id); ?>"
					data-open-text="<?php echo esc_attr(WBTM_Translations::text_view_seat()); ?>"
					data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
					data-add-class="mActive">
					<?php echo esc_html(WBTM_Translations::text_view_seat()); ?>
				</button>
			</div>

			<div class="wbtm_bus_details mT_xs" data-row_id="<?php echo esc_attr($bus_id); ?>">
				<!-- bus details will display here -->
			</div>

		<?php } ?>

		<?php if ($bus_count == 0) : ?>
			<div>
				<?php WBTM_Layout::msg(WBTM_Translations::text_no_bus()); ?>
			</div>
		<?php endif; ?>
	</div>

<?php
} else {
	WBTM_Layout::msg(WBTM_Translations::text_no_bus());
}
//echo '<pre>';	print_r($bus_ids);	echo '</pre>';