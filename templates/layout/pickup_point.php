<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
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
					<div class="wbtm_pickup_point _bgLight padding_xs mB mT">
						<label class="justifyBetween">
							<span class="_mR"><?php echo WBTM_Translations::text_pickup_point(); ?></span>
							<select class="formControl" name="wbtm_pickup_point">
								<option selected value=" "><?php echo WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_pickup_point(); ?></option>
								<?php foreach ($pickup_infos as $pickup_info) { ?>
									<?php $pickup_time = date('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
									<?php $pickup_time = MP_Global_Function::date_format($pickup_time, 'time'); ?>
									<option value="<?php echo esc_attr($pickup_info['pickup_point'] . ' ' . $pickup_time) ?>"><?php echo esc_html($pickup_info['pickup_point']) . ' ' . ' (' . $pickup_time . ')'; ?></option>
								<?php } ?>
							</select>
						</label>
						
					</div>
					<?php
				}
			}
		}
	}