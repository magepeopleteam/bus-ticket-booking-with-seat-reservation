<?php
add_action('wp_ajax_mage_bus_price_convert', 'mage_bus_price_convert');
add_action('wp_ajax_nopriv_mage_bus_price_convert', 'mage_bus_price_convert');
function mage_bus_price_convert(){
    echo wc_price(strip_tags($_POST['price']));
    die();
}
add_action('wp_ajax_mage_bus_selected_seat_item', 'mage_bus_selected_seat_item');
add_action('wp_ajax_nopriv_mage_bus_selected_seat_item', 'mage_bus_selected_seat_item');
function mage_bus_selected_seat_item(){
    $return_discount = 0;
    $price_final = 0;
    $dd = false;

    $post_seat_name = isset($_POST['seat_name']) ? sanitize_text_field($_POST['seat_name']) : '';
    $post_bus_id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
    $post_start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
    $post_end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
    $post_passenger_type = isset($_POST['passenger_type']) ? sanitize_text_field($_POST['passenger_type']) : '';
    $post_dd = isset($_POST['dd']) ? sanitize_text_field($_POST['dd']) : '';
    $post_price = isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '';
    $post_j_date = isset($_POST['j_date']) ? sanitize_text_field($_POST['j_date']) : '';
    $post_r_date = isset($_POST['r_date']) ? sanitize_text_field($_POST['r_date']) : '';
    $post_is_return = isset($_POST['is_return']) ? sanitize_text_field($_POST['is_return']) : '';

    // Return Discount setting
    $settings = get_option('wbtm_bus_settings');
    $val = mage_bus_setting_value('bus_return_discount');
    $is_return_discount_enable = $val ? $val : 'no';
    // $price_final = '<span data-current-price="'.$post_price.'" style="margin-right:0!important">'.wc_price($post_price).'</span>';
    ?>
    <div class="flexEqual mage_bus_selected_seat_item" data-seat-name="<?php echo $post_seat_name; ?>">
        <h6><?php echo $post_seat_name; ?></h6>
        <?php
        if(mage_bus_multiple_passenger_type_check($post_bus_id,$post_start,$post_end)){
            $seat_panel_settings = get_option('wbtm_bus_settings');
            $adult_label = mage_bus_setting_value('wbtm_seat_type_adult_label');
            $child_label = mage_bus_setting_value('wbtm_seat_type_child_label');
            $infant_label = mage_bus_setting_value('wbtm_seat_type_infant_label');
            $special_label = mage_bus_setting_value('wbtm_seat_type_special_label');
            if(1==$post_passenger_type){
                $type=$child_label;
            }elseif(2==$post_passenger_type){
                $type=$infant_label;
            }elseif(3==$post_passenger_type){
                $type=$special_label;
            }else{
                $type=$adult_label;
            }
            echo '<h6>'.$type.'</h6>';
        }

        $dd = ($post_dd == 'yes') ? true : false;
        $price_final = '<span data-current-price="'.wbtm_get_price_including_tax($post_bus_id, $post_price).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($post_bus_id, $post_price)).'</span>';
        // if($_POST['has_seat'] == 0) {
            if($post_is_return && $post_r_date) {
                $return_discount = mage_cart_has_opposite_route($post_start, $post_end, $post_j_date, true, $post_r_date); // Return
            } else {
                $return_discount = mage_cart_has_opposite_route($post_start, $post_end, $post_j_date); // No return
            }
            $is_multiple_passenger = mage_cart_has_opposite_route_P();
            
            if($is_return_discount_enable == 'yes') {
                if($return_discount == 1 && !$is_multiple_passenger) {
                    $price = mage_bus_seat_price($post_bus_id, $post_start, $post_end, $dd, $post_passenger_type, true);
                    if($price != $post_price) {
                        $price_final = '<span data-old-price="'.$post_price.'" data-price="'.$price.'" data-current-price="'.wbtm_get_price_including_tax($post_bus_id, $price).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($post_bus_id, $price)).'</span>';
                        $price_final .= '<span class="return_price_cal mage_old_price" data-price="'.$post_price.'" style="display:block">'.wc_price(wbtm_get_price_including_tax($post_bus_id, $post_price)).'</span>';
                    } else {
                        $price_final = '<span data-old-price="'.$post_price.'" data-price="'.$price.'" data-current-price="'.wbtm_get_price_including_tax($post_bus_id, $price).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($post_bus_id, $price)).'</span>';
                    }
                }
            }
        
        // }
        ?>
        <h6 class="mage_selected_seat_price"><?php echo $price_final; ?></h6>
        <h6><span class="fa fa-trash mage_bus_seat_unselect"></span></h6>
    </div>
    <?php
    die();
}

add_action('wp_ajax_wbtm_form_builder', 'wbtm_form_builder_callback');
add_action('wp_ajax_nopriv_wbtm_form_builder', 'wbtm_form_builder_callback');
function wbtm_form_builder_callback() {
    $busId = isset($_POST['busID']) ? sanitize_text_field($_POST['busID']) : '';
    $seatType = isset($_POST['seatType']) ? sanitize_text_field($_POST['seatType']) : '';
    $passengerType = isset($_POST['passenger_type']) ? sanitize_text_field($_POST['passenger_type']) : 0;
    $seats = isset($_POST['seats']) ? sanitize_text_field($_POST['seats']) : '';
    $post_dd = isset($_POST['dd']) ? sanitize_text_field($_POST['dd']) : '';
    if($post_dd) {
        $dd = ($post_dd == 'yes' ? 'yes' : 'no');
    }
    if (class_exists('WbtmProFunction')) {
        for ($i = 1; $i <= $seats; $i++) {
            WbtmProFunction::bus_hidden_customer_info_form($busId, $seatType, $passengerType, $dd);
        }
    } else {
        // echo '<input type="hidden" name="custom_reg_user" value="no" />';
    }
    exit;
}