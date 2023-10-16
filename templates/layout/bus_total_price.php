<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
?>
	<div class="divider"></div>
	<div class="justifyBetween">
		<h4><?php echo WBTM_Translations::text_ticket_sub_total(); ?></h4>
		<h4 class="wbtm_sub_total"><?php echo wc_price(0); ?></h4>
	</div>
<?php