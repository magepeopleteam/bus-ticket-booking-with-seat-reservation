<?php 
add_action('wbtm_search_form_title','wbtm_display_search_form_title');
function wbtm_display_search_form_title(){
global $wbtmmain;
 echo $wbtmmain->bus_get_option('wbtm_buy_ticket_text', 'label_setting_sec', _e('BUY TICKET bbb:','bus-ticket-booking-with-seat-reservation'));
}