<?php
add_action('wbtm_from_bus_stops_list','wbtm_display_bustops_from_list');

function wbtm_display_bustops_from_list(){
    global $wbtmmain,$wbtmpublic;
    $start  	= isset( $_GET['bus_start_route'] ) ? strip_tags($_GET['bus_start_route']) : '';    
    ?>
    <div class="mage_form_list mage_input_select">
    <label for="bus_start_route"><span class="fa fa-map-marker" aria-hidden="true"></span>
        <?php echo $wbtmmain->bus_get_option('wbtm_from_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_from_text', 'label_setting_sec') : _e('From','bus-ticket-booking-with-seat-reservation'); ?>
    </label>
        <?php echo $wbtmmain->wbtm_get_bus_route_list( 'bus_start_route', $start ); ?>

    </div>
<?php
}



add_action('wbtm_to_bus_stops_list','wbtm_display_bustops_to_list');
function wbtm_display_bustops_to_list(){
    global $wbtmmain,$wbtmpublic;
    $end    	= isset( $_GET['bus_end_route'] ) ? strip_tags($_GET['bus_end_route']) : '';
    ?>
        <label>
			<i class="fa fa-map-marker" aria-hidden="true"></i><?php echo $wbtmmain->bus_get_option('wbtm_to_text', 'label_setting_sec','') ? $wbtmmain->bus_get_option('wbtm_to_text', 'label_setting_sec','') : _e('To:','bus-ticket-booking-with-seat-reservation'); ?>
			<?php echo $wbtmmain->wbtm_get_bus_route_list( 'bus_end_route', $end ); ?>
		</label>
<?php
}




