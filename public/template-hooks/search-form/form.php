<?php 
add_action('wbtm_search_form','wbtm_display_search_form');
function wbtm_display_search_form(){
    mage_bus_search_form();
}

