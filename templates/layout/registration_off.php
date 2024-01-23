<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	?>
	<div class="flexWrap">
<?php
	$post_id = $post_id ?? MP_Global_Function::data_sanitize($_POST['post_id']);
	$display_pickup_point = MP_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
	$pickup_points = MP_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
	if ($display_pickup_point == 'yes' && sizeof($pickup_points) > 0) {
		$date = $_POST['date'] ?? '';
		$start_route = $start_route ?? MP_Global_Function::data_sanitize($_POST['start_route']);
		$end_route = $end_route ?? MP_Global_Function::data_sanitize($_POST['end_route']);
		//echo '<pre>'; print_r($pickup_points); echo '</pre>';
		foreach ($pickup_points as $pickup_point) {
			if ($pickup_point['bp_point'] == $start_route) {
				$pickup_infos = $pickup_point['pickup_info'];
				if (sizeof($pickup_infos) > 0) {
					?>
					<div class="wbtm_pickup_point col_6 col_12_800">
						<h6><?php echo WBTM_Translations::text_pickup_point(); ?></h6>
						<div class="divider"></div>
						<h4><?php echo esc_html($start_route); ?></h4>
						<ul class="mp_list">
						<?php foreach ($pickup_infos as $pickup_info) { ?>
							<?php $pickup_time = date('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
							<?php $pickup_time = MP_Global_Function::date_format($pickup_time, 'time'); ?>
							<li><span class="fas fa-clock _mR_xs_textTheme"></span> <?php echo esc_html($pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($pickup_info['pickup_point']); ?> </li>

						<?php } ?>
						</ul>
					</div>
					<?php
				}
			}
		}
	}
	$display_drop_off_point = MP_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
	$drop_off_points = MP_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
	if ($display_drop_off_point == 'yes' && sizeof($drop_off_points) > 0) {
		$date = $_POST['date'] ?? '';
		$end_route = $end_route ?? MP_Global_Function::data_sanitize($_POST['end_route']);
		//echo '<pre>'; print_r($drop_off_points); echo '</pre>';
		foreach ($drop_off_points as $drop_off_point) {
			if ($drop_off_point['dp_point'] == $end_route) {
				$pickup_infos = $drop_off_point['drop_off_info'];
				if (sizeof($pickup_infos) > 0) {
					?>
					<div class="wbtm_pickup_point col_6 col_12_800">
						<h6><?php echo WBTM_Translations::text_drop_off_point(); ?></h6>
						<div class="divider"></div>
						<h4><?php echo esc_html($end_route); ?></h4>
						<ul class="mp_list">
							<?php foreach ($pickup_infos as $pickup_info) { ?>
								<?php $pickup_time = date('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
								<?php $pickup_time = MP_Global_Function::date_format($pickup_time, 'time'); ?>
								<li><span class="fas fa-clock _mR_xs_textTheme"></span> <?php echo esc_html($pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($pickup_info['drop_off_point']); ?> </li>

							<?php } ?>
						</ul>
					</div>
					<?php
				}
			}
		}
	}
	?>
	</div>
<?php