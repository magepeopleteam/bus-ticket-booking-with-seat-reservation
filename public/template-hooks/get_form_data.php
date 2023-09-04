<?php
add_action('wbtm_before_search_form','wbtm_get_form_data');
add_action('before_search_form_fields','wbtm_get_form_data');

function wbtm_get_form_data(){
global $wbtmmain,$wbtmpublic;
    $start  	= isset( $_GET['bus_start_route'] ) ? strip_tags($_GET['bus_start_route']) : '';
    $end    	= isset( $_GET['bus_end_route'] ) ? strip_tags($_GET['bus_end_route']) : '';
    $date   	= isset( $_GET['j_date'] ) ? strip_tags(wbtm_convert_date_to_php($_GET['j_date'])) : date('Y-m-d');
    $r_date     = isset( $_GET['r_date'] ) ? strip_tags(wbtm_convert_date_to_php($_GET['r_date'])) : date('Y-m-d');
}