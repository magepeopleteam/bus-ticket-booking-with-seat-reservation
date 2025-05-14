<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
?>

	<table class="_layoutFixed_dLayout_mB_xs">
		<tr>
			<td colspan="2"><h5 class="col_6"><?php echo WBTM_Translations::text_ticket_sub_total(); ?></h5></td>
			<td colspan="2" style="text-align: left;"><h5 class="wbtm_sub_total"><?php echo wc_price(0); ?></h5></td>
		</tr>
	</table>
