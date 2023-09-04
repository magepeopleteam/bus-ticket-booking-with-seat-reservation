<?php 
global $wbtmmain;
?>
    <thead>
        <tr>
            <th class='wbtm-mobile-hide'></th>
            <th><?php echo $wbtmmain->bus_get_option('wbtm_bus_name_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_bus_name_text', 'label_setting_sec') : _e('Bus Name','bus-ticket-booking-with-seat-reservation'); ?>  
            </th>
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_departing_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_departing_text', 'label_setting_sec') : _e('DEPARTING','bus-ticket-booking-with-seat-reservation'); ?> 
            </th> 
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_coach_no_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_coach_no_text', 'label_setting_sec') : _e('COACH NO','bus-ticket-booking-with-seat-reservation'); ?>  
            </th>
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') : _e('STARTING','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_end_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_end_text', 'label_setting_sec') : _e('END','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th><?php echo $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') : _e('FARE','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_type_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_type_text', 'label_setting_sec') : _e('TYPE','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th class='wbtm-mobile-hide'><?php echo $wbtmmain->bus_get_option('wbtm_arrival_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_arrival_text', 'label_setting_sec') : _e('ARRIVAL','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th><?php echo $wbtmmain->bus_get_option('wbtm_seats_available_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_seats_available_text', 'label_setting_sec') : _e('SEATS AVAILABLE','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
            <th><?php echo $wbtmmain->bus_get_option('wbtm_view_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_view_text', 'label_setting_sec') : _e('VIEW','bus-ticket-booking-with-seat-reservation'); ?> 
            </th>
        </tr>
    </thead>