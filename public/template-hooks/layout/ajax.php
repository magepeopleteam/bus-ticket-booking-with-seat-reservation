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

    // Return Discoutn setting
    $settings = get_option('wbtm_bus_settings');
    $val = $settings['bus_return_discount'];
    $is_return_discount_enable = $val ? $val : 'no';
    // $price_final = '<span data-current-price="'.$_POST["price"].'" style="margin-right:0!important">'.wc_price($_POST["price"]).'</span>';
    ?>
    <div class="flexEqual mage_bus_selected_seat_item" data-seat-name="<?php echo $_POST['seat_name']; ?>">
        <h6><?php echo $_POST['seat_name']; ?></h6>
        <?php
        if(mage_bus_multiple_passenger_type_check($_POST['id'],$_POST['start'],$_POST['end'])){
            $seat_panel_settings = get_option('wbtm_bus_settings');
            $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
            $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
            $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
            $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
            if(1==$_POST['passenger_type']){
                $type=$child_label;
            }elseif(2==$_POST['passenger_type']){
                $type=$infant_label;
            }elseif(3==$_POST['passenger_type']){
                $type=$special_label;
            }else{
                $type=$adult_label;
            }
            echo '<h6>'.$type.'</h6>';
        }

        $dd = ($_POST['dd'] == 'yes') ? true : false;
        $price_final = '<span data-current-price="'.wbtm_get_price_including_tax($_POST["id"], $_POST["price"]).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($_POST["id"], $_POST["price"])).'</span>';
        // if($_POST['has_seat'] == 0) {
            if($_POST['is_return'] && $_POST['r_date']) {
                $return_discount = mage_cart_has_opposite_route($_POST['start'], $_POST['end'], $_POST['j_date'], true, $_POST['r_date']); // Return
            } else {
                $return_discount = mage_cart_has_opposite_route($_POST['start'], $_POST['end'], $_POST['j_date']); // No return
            }
            $is_multiple_passenger = mage_cart_has_opposite_route_P();
            
            if($is_return_discount_enable == 'yes') {
                if($return_discount == 1 && !$is_multiple_passenger) {
                    $price = mage_bus_seat_price($_POST['id'], $_POST['start'], $_POST['end'], $dd, $_POST['passenger_type'], true);
                    if($price != $_POST['price']) {
                        $price_final = '<span data-old-price="'.$_POST["price"].'" data-price="'.$price.'" data-current-price="'.wbtm_get_price_including_tax($_POST["id"], $price).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($_POST["id"], $price)).'</span>';
                        $price_final .= '<span class="return_price_cal mage_old_price" data-price="'.$_POST["price"].'" style="display:block">'.wc_price(wbtm_get_price_including_tax($_POST['id'], $_POST["price"])).'</span>';
                    } else {
                        $price_final = '<span data-old-price="'.$_POST["price"].'" data-price="'.$price.'" data-current-price="'.wbtm_get_price_including_tax($_POST["id"], $price).'" style="margin-right:0!important">'.wc_price(wbtm_get_price_including_tax($_POST['id'], $price)).'</span>';
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
    $busId = $_POST['busID'];
    $seatType = $_POST['seatType'];
    $passengerType = isset($_POST['passengerType']) ? $_POST['passengerType'] : 0;
    $seats = $_POST['seats'];
    if (class_exists('WbtmProFunction')) {
        for ($i = 1; $i <= $seats; $i++) {
            WbtmProFunction::bus_hidden_customer_info_form($busId, $seatType, $passengerType);
        }
    } else {
        // echo '<input type="hidden" name="custom_reg_user" value="no" />';
    }
    exit;
}