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
	$show_extra_service = MP_Global_Function::get_post_info($post_id, 'show_extra_service', 'no');
	if ($show_extra_service == 'yes') {
		$ex_services = MP_Global_Function::get_post_info($post_id, 'wbtm_extra_services', []);
		if (sizeof($ex_services) > 0) {
			?>
			<div class="wbtm_ex_service_area">
				<div class="divider"></div>
				<h5><?php echo WBTM_Translations::text_ex_service(); ?> : </h5>
				<div class="divider"></div>
				<table class="_layoutFixed_textCenter">
					<thead>
					<tr>
						<th><?php echo WBTM_Translations::text_name();?></th>
						<th><?php echo WBTM_Translations::text_qty(); ?></th>
						<th><?php echo WBTM_Translations::text_price();?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($ex_services as $ex_service) { ?>
						<?php
						$row_price = MP_Global_Function::get_wc_raw_price($post_id, $ex_service['option_price']);
						$qty_type = $ex_service['option_qty_type'];
						$ex_name = $ex_service['option_name'];
						$total_ex = max($ex_service['option_qty'], 0);
						$sold = WBTM_Query::query_ex_service_sold($post_id, $date, $ex_name);
						$available_ex_service = $total_ex - $sold;
						?>
						<tr>
							<th><?php echo esc_html($ex_name); ?></th>
							<td>
								<input type="hidden" name="extra_service_name[]" value="<?php echo esc_attr($ex_name); ?>">
								<?php MP_Custom_Layout::qty_input('extra_service_qty[]', $row_price, $available_ex_service, 0, 0, $available_ex_service, $qty_type, $ex_name); ?>
							</td>
							<th><?php echo wc_price($row_price); ?></th>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}