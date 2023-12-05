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
	$ticket_infos = WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route);
	if (sizeof($ticket_infos) > 0) {
		?>
		<div class="mpRow">
			<div class="col_4 col_5_1000 col_6_900 col_12_800 padding">
				<?php require WBTM_Functions::template_path('layout/seat_plan.php'); ?>
			</div>
			<div class="col_8 col_7_1000 col_6_900 col_12_800 ">
				<div class="mpRow">
					<div class="padding col_6 col_12_1000 col_6_800 col_12_700 wbtm_bus_details_area">
						<div class="_dLayout_xs "><?php require WBTM_Functions::template_path('layout/bus_info.php'); ?></div>
					</div>
					<div class="padding col_6 col_12_1000 col_6_800 col_12_700">
						<div class="_dLayout_xs">
							<?php require WBTM_Functions::template_path('layout/selected_seat.php'); ?>
							<?php require WBTM_Functions::template_path('layout/bus_total_price.php'); ?>
							<?php require WBTM_Functions::template_path('layout/pickup_point.php'); ?>
							<?php require WBTM_Functions::template_path('layout/drop_off_point.php'); ?>
							<?php require WBTM_Functions::template_path('layout/extra_service.php'); ?>
						</div>
					</div>
					<?php do_action('wbtm_attendee_form', $post_id); ?>
					<?php require WBTM_Functions::template_path('layout/add_to_cart.php'); ?>
				</div>
			</div>
		</div>
		<?php
	}
	else {
		WBTM_Layout::msg(WBTM_Translations::text_no_ticket());
	}