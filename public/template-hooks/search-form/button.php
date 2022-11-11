<?php
add_action('wbtm_form_submit_button','wbtm_display_form_button');
function wbtm_display_form_button(){
global $wbtmmain;
    ?>
		<button type="submit"><i class='fa fa-search'></i><?php echo $wbtmmain->bus_get_option('wbtm_search_buses_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_search_buses_text', 'label_setting_sec') : _e('Search Buses','bus-ticket-booking-with-seat-reservation'); ?></button>
    <?php
}