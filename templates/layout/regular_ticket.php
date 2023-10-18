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
	$ticket_infos = $ticket_infos ?? WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route);
?>
	<table class="_layoutFixed_textCenter">
		<thead>
		<tr>
			<th><?php echo WBTM_Translations::text_ticket_type(); ?></th>
			<th><?php echo WBTM_Translations::text_qty(); ?></th>
			<th><?php echo WBTM_Translations::text_price(); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($ticket_infos as $ticket_info) { ?>
			<tr>
				<th><?php echo esc_html($ticket_info['name']); ?></th>
				<td>
					<input type="hidden" name="wbtm_passenger_type[]" value="<?php echo esc_attr($ticket_info['type']); ?>">
					<?php MP_Custom_Layout::qty_input('wbtm_seat_qty[]', $ticket_info['price'], $all_info['available_seat'], 0, 0, $all_info['available_seat']); ?>
				</td>
				<th><?php echo wc_price($ticket_info['price']); ?></th>
			</tr>
		<?php } ?>
		</tbody>
	</table>
<?php