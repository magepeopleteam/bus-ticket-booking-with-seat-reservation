<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
?>
	<div class="justifyStart pT_xs">
		<h5 class="col_6"><?php echo WBTM_Translations::text_ticket_sub_total(); ?></h5>
		<h5 class="wbtm_sub_total col_6 paddingLeft_xs"><?php echo wc_price(0); ?></h5>
	</div>
