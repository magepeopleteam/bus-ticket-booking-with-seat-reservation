<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? MP_Global_Function::data_sanitize($_POST['post_id']);
	$start_route = $start_route ?? MP_Global_Function::data_sanitize($_POST['start_route']);
	$end_route = $end_route ?? MP_Global_Function::data_sanitize($_POST['end_route']);
	$date = $_POST['date'] ?? '';
	$all_info = $all_info ?? WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route);
	$seat_price = $seat_price ?? WBTM_Functions::get_seat_price($post_id, $start_route, $end_route);
	//echo '<pre>';print_r($all_info);echo '</pre>';
?>
	<table>
		<tbody>
		<tr>
			<th>
				<span class="fas fa-map-marker-alt"></span>
				<?php echo WBTM_Translations::text_bp(); ?>
			</th>
			<td>
				<h6><?php echo esc_html($all_info['bp']); ?></h6>
				<?php echo esc_html($all_info['dp_time'] ? MP_Global_Function::date_format($all_info['bp_time'], 'full') : ''); ?>
			</td>
		</tr>
		<tr>
			<th>
				<span class="fas fa-map-marker-alt"></span>
				<?php echo WBTM_Translations::text_dp(); ?>
			</th>
			<td>
				<h6><?php echo esc_html($all_info['dp']); ?></h6>
				<?php echo esc_html($all_info['dp_time'] ? MP_Global_Function::date_format($all_info['dp_time'], 'full') : ''); ?>
			</td>
		</tr>
		<?php if ($all_info['start_point'] != $all_info['bp']) { ?>
			<tr>
				<th>
					<span class="fas fa-map-marker-alt"></span>
					<?php echo WBTM_Translations::text_start_point(); ?>
				</th>
				<td>
					<h6><?php echo esc_html($all_info['start_point']); ?></h6>
					<?php echo esc_html($all_info['start_time'] ? MP_Global_Function::date_format($all_info['start_time'], 'full') : ''); ?>
				</td>
			</tr>
		<?php } ?>
		<tr>
			<th>
				<span class="fa fa-calendar"></span>
				<?php echo WBTM_Translations::text_date(); ?>
			</th>
			<td><?php echo MP_Global_Function::date_format($date); ?></td>
		</tr>
		<tr>
			<th>
				<span class="fas fa-bus"></span>
				<?php echo WBTM_Translations::text_coach_type(); ?>
			</th>
			<td><?php echo MP_Global_Function::get_post_info($post_id, 'wbtm_bus_category'); ?></td>
		</tr>
		<tr>
			<th>
				<span class="fas fa-money-bill"></span>
				<?php echo WBTM_Translations::text_fare(); ?>
			</th>
			<td>
				<?php echo wc_price($seat_price); ?>
				<small>/<?php echo WBTM_Translations::text_seat(); ?></small>
			</td>
		</tr>
		</tbody>
	</table>
<?php