<?php


function wbtm_get_style($name,$default){
    global $wbtmmain;
    return $wbtmmain->bus_get_option($name,'',$default);
}


add_action('wp_head','wbtm_add_custom_css_codes');
function wbtm_add_custom_css_codes(){

    
    ob_start();
?>
<style>
ul.mage_list_inline li.mage_active {
    background-color: <?php echo wbtm_get_style('wbtm_search_next_date_active_bg_color','#777'); ?>;
}
ul.mage_list_inline li.mage_active a {
    color: <?php echo wbtm_get_style('wbtm_search_next_date_active_text_color','#fff'); ?>;
}
ul.mage_list_inline li {
    background-color: <?php echo wbtm_get_style('wbtm_search_next_date_bg_color','#f2f2f2'); ?>;
}
ul.mage_list_inline li a {
    color: <?php echo wbtm_get_style('wbtm_search_next_date_text_color','#0a4b78'); ?>;
}
[class*='bgLight'] {
    background-color: <?php echo wbtm_get_style('wbtm_search_route_list_title_bg_color','#777'); ?>;
}
.bgLight_mar_t_textCenter_radius_pad_xs_justifyAround.mage_title h4 {
    color: <?php echo wbtm_get_style('wbtm_search_route_list_title_text_color','#000'); ?>;
}
.mage_bus_list_title, .mage_bus_list_title {
    background-color:<?php echo wbtm_get_style('wbtm_search_list_table_bg_color','#0a4b78'); ?>;
    color:<?php echo wbtm_get_style('wbtm_search_list_table_text_color','#fff'); ?>;
}
button.mage_button_xs.mage_bus_details_toggle {
    background: <?php echo wbtm_get_style('wbtm_view_seat_btn_bg_color','#0a4b78'); ?>;
    color:<?php echo wbtm_get_style('wbtm_view_seat_btn_text_color','#fff'); ?>;
}

form.mage_form button[class*='mage_button'] {
    background: <?php echo wbtm_get_style('wbtm_book_now_btn_bg_color','#0a4b78'); ?>;
    color:<?php echo wbtm_get_style('wbtm_book_now_btn_text_color','#fff'); ?>;
}
form.mage_form button[class*='mage_button_search'] {
    color: <?php echo wbtm_get_style('wbtm_search_btn_text_color','#fff'); ?>;
    background-color: <?php echo wbtm_get_style('wbtm_search_btn_bg_color','#0a4b78'); ?>;
    border: 1px solid <?php echo wbtm_get_style('wbtm_search_btn_bg_color','#0a4b78'); ?>;
}
.wbtm-details-page-list-total-avl-seat, .flexEqual.mage_bus_selected_list, .mage_customer_info_area .mage_title {
    background: <?php echo wbtm_get_style('wbtm_search_list_bus_details_title_bg_color','#ddd'); ?>;
    color:<?php echo wbtm_get_style('wbtm_search_list_bus_details_title_text_color','#000'); ?>!important;
}

</style>
<?php
    echo ob_get_clean();
}