<?php 
add_action('wbtm_form_journey_type_select','wbtm_display_form_radio_buttons');
function wbtm_display_form_radio_buttons(){
    global $wbtmmain,$wbtmpublic;
    $busr   	= isset( $_GET['bus-r'] ) ? strip_tags($_GET['bus-r']) : 'oneway';    
    ?>
		<label for="oneway">
			<input type="radio" class='wbtm_radio_btn' <?php if($busr=='oneway'){ echo 'checked'; } ?> id='oneway' name="bus-r" value='oneway'><?php echo $wbtmmain->bus_get_option('wbtm_one_way_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_one_way_text', 'label_setting_sec') : _e('One Way','bus-ticket-booking-with-seat-reservation'); ?>
		</label>
		<label for="return_date">
			<input type="radio" class='wbtm_radio_btn' <?php if($busr=='return'){ echo 'checked'; } ?> id='return_date' name="bus-r" value='return'><?php echo $wbtmmain->bus_get_option('wbtm_return_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_return_text', 'label_setting_sec') : _e('Return','bus-ticket-booking-with-seat-reservation'); ?>
		</label>
    <?php
}