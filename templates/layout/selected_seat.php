<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
?>
	<div class="wbtm_selected_seat_details">
		<table class="_layoutFixed_textLeft">
			<thead>
			<tr>
				<th><?php echo WBTM_Translations::text_ticket_type(); ?></th>
				<th><?php echo WBTM_Translations::text_seat_name();?></th>
				<th><?php echo WBTM_Translations::text_price(); ?></th>
				<th class="_textCenter"><?php echo WBTM_Translations::text_action(); ?></th>
			</tr>
			</thead>
			<tbody class="wbtm_item_insert">
			</tbody>
		</table>
		<div class="wbtm_item_hidden">
			<table>
				<tbody>
				<tr class="wbtm_remove_area" data-seat_type="" data-seat_name="">
					<td class="insert_seat_label"></td>
					<td class="insert_seat_name"></td>
					<td class="insert_seat_price"></td>
					<td>
						<div class="allCenter"><?php MP_Custom_Layout::remove_button(); ?></div>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
<?php
