<?php 
add_action('wbtm_form_journey_date','wbtm_display_form_journey_date');
function wbtm_display_form_journey_date(){
    global $wbtmmain,$wbtmpublic;
    $date   	= isset( $_GET['j_date'] ) ? strip_tags($_GET['j_date']) : date('Y-m-d');    
?>
<label for='j_date'>
	 <i class="fa fa-calendar" aria-hidden="true"></i><?php echo $wbtmmain->bus_get_option('wbtm_date_of_journey_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_date_of_journey_text', 'label_setting_sec') : _e('Date of Journey:','bus-ticket-booking-with-seat-reservation'); ?>
	 <input readonly type="text" id="j_date" name="j_date" value="<?php echo $date; ?>">
</label>
<?php
}


add_action('wbtm_form_return_date','wbtm_display_form_return_date');
function wbtm_display_form_return_date(){
    global $wbtmmain,$wbtmpublic;
    $r_date     = isset( $_GET['r_date'] ) ? strip_tags($_GET['r_date']) : date('Y-m-d');    
?>
<label for='r_date'>
	<i class="fa fa-calendar" aria-hidden="true"></i><?php echo $wbtmmain->bus_get_option('wbtm_return_date_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_return_date_text', 'label_setting_sec') : _e('Return Date:','bus-ticket-booking-with-seat-reservation'); ?>							 
	<input type="text" readonly id="r_date" name="r_date" value="<?php echo $r_date; ?>">
</label>
<?php
}