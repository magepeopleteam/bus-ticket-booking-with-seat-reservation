<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

class WbtmAddToCart
{

    public function __construct()
    {
        $this->add_hooks();
    }


    private function add_hooks()
    {
        add_filter('woocommerce_add_to_cart_validation', array($this, 'wbtm_check_seat_available_or_not'), 10, 5);
        add_filter('woocommerce_add_cart_item_data', array($this, 'wbtm_add_custom_fields_text_to_cart_item'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'wbtm_add_custom_price'));
        add_filter('woocommerce_get_item_data', array($this, 'wbtm_display_custom_fields_text_cart'), 10, 2);
        add_action('woocommerce_after_checkout_validation', array($this, 'rei_after_checkout_validation'));
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'wbtm_add_custom_fields_text_to_order_items'), 10, 4);
    }

    public function wbtm_check_seat_available_or_not($passed, $product_id, $quantity, $variation_id = '', $variations = '')
    {
        global $wbtmmain;
        $seat_name = $wbtmmain->wbtm_array_strip($_POST['seat_name']);
        $journey_date = sanitize_text_field($_POST['journey_date']);
        $bus_id = sanitize_text_field($_POST['bus_id']);
        $start_stops = sanitize_text_field($_POST['start_stops']);
        $end_stops = sanitize_text_field($_POST['end_stops']);
        // $check_before_order = $wbtmmain->wbtm_get_seat_cehck_before_order($seat_name, $journey_date, $bus_id, $start_stops, $end_stops);
        // if ($check_before_order > 0) {
        //     $passed = false;
        //     wc_add_notice(__('Sorry, Your Selected Seat Already Booked by another user', 'bus-ticket-booking-with-seat-reservation'), 'error');
        // }
        return $passed;
    }


    public function wbtm_add_custom_fields_text_to_cart_item($cart_item_data, $product_id, $variation_id)
    {
        global $wbtmmain;
        echo '<pre>';
        print_r($_POST);
        die;

        $bus_id = sanitize_text_field($_POST['bus_id']);

        if (get_post_type($bus_id) === 'wbtm_bus') {
            $return_discount = 0;
            $return_discount = mage_cart_has_opposite_route($_POST['start_stops'], $_POST['end_stops'], $_POST['journey_date']);

            $product_id = get_post_meta($product_id, 'link_wbtm_bus', true) ? get_post_meta($product_id, 'link_wbtm_bus', true) : $product_id;

            $tp = get_post_meta($product_id, '_price', true);
            $price_arr = maybe_unserialize(get_post_meta($product_id, 'wbtm_bus_prices', true));
            $new = array();
            $user = array();
            $user_s = array();
            $user_b = array();
            
            $start_stops = sanitize_text_field($_POST['start_stops']);
            $end_stops = sanitize_text_field($_POST['end_stops']);
            $journey_date = sanitize_text_field($_POST['journey_date']);
            $seat_name = $wbtmmain->wbtm_array_strip($_POST['seat_name']);
            $passenger_type = $wbtmmain->wbtm_array_strip($_POST['passenger_type']);
            $bus_dd = $wbtmmain->wbtm_array_strip($_POST['bus_dd']);

            //echo '<pre>';print_r($bus_dd);echo '</pre>';die();
            $total_seat = sanitize_text_field($_POST['total_seat']);
            $count_seat = count($seat_name);
            $total_fare = 0;
            $main_fare = array();
            $original_fare = 0;
            $return_fare = 0;
            $is_return = false;
            $passenger = array();
            $passenger_type_num = array();
            $adult_label = $child_label = $infant_label = $special_label = '';
            if (mage_bus_multiple_passenger_type_check($bus_id, $start_stops, $end_stops)) {
                $seat_panel_settings = get_option('wbtm_bus_settings');
                $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
                $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
                $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
                $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
            }
            $dd = false;
            if ($return_discount == 1 && count($passenger_type) == 1) {
                $return_discount = 2;
            }
            for ($x = 0; $x < count($passenger_type); $x++) {
                if ($bus_dd[$x] == 'yes') {
                    $dd = true;
                }
                if (1 == $passenger_type[$x]) {
                    $passenger[$x] = $child_label;
                } elseif (2 == $passenger_type[$x]) {
                    $passenger[$x] = $infant_label;
                } elseif (3 == $passenger_type[$x]) {
                    $passenger[$x] = $special_label;
                } else {
                    $passenger[$x] = $adult_label;
                }

                $passenger_type_num[$x] = $passenger_type[$x];
                if ($return_discount == 2) {
                    $is_return = true;
                } else {
                    $is_return = false;
                }
                $main_fare[$x] = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $dd, $passenger_type[$x]);
                $original_fare = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $dd, $passenger_type[$x]);
                $return_fare = mage_bus_seat_price($bus_id, $start_stops, $end_stops, $dd, $passenger_type[$x], true);

                $total_fare = $main_fare[$x] + $total_fare;

                // Custom reg user no
                if (!isset($_POST['custom_reg_user']) || $_POST['custom_reg_user'] != 'yes') {
                    $user_b[$x]['wbtm_seat_fare'] = stripslashes(strip_tags($main_fare[$x]));
                }
            }


            $user_start_time = sanitize_text_field($_POST['user_start_time']);
            $bus_start_time = sanitize_text_field($_POST['bus_start_time']);

            $ext_bag = 0;
            $extra_bag_price = get_post_meta($product_id, 'wbtm_extra_bag_price', true) ? get_post_meta($product_id, 'wbtm_extra_bag_price', true) : 0;

            if (isset($_POST['custom_reg_user']) && $_POST['custom_reg_user'] == 'yes') {

                $wbtm_user_name = isset($_POST['wbtm_user_name']) ? $_POST['wbtm_user_name'] : '';
                $wbtm_user_email = isset($_POST['wbtm_user_email']) ? $_POST['wbtm_user_email'] : '';
                $wbtm_user_phone = isset($_POST['wbtm_user_phone']) ? $_POST['wbtm_user_phone'] : '';
                $wbtm_user_address = isset($_POST['wbtm_user_address']) ? $_POST['wbtm_user_address'] : '';
                $wbtm_user_gender = isset($_POST['wbtm_user_gender']) ? $_POST['wbtm_user_gender'] : '';
                $extra_bag_quantity = isset($_POST['extra_bag_quantity']) ? $_POST['extra_bag_quantity'] : 0;
                // $count_user = count($wbtm_user_name);
                for ($iu = 0; $iu < $count_seat; $iu++) {


                    $user[$iu]['wbtm_bus_id'] = stripslashes(strip_tags($bus_id));

                    $user[$iu]['wbtm_seat_name'] = stripslashes(strip_tags($seat_name[$iu]));
                    $user[$iu]['wbtm_passenger_type'] = stripslashes(strip_tags($passenger[$iu]));
                    $user[$iu]['wbtm_passenger_type_num'] = stripslashes(strip_tags($passenger_type_num[$iu]));

                    $user[$iu]['wbtm_journey_date'] = stripslashes(strip_tags($journey_date));

                    $user[$iu]['wbtm_bus_start_time'] = stripslashes(strip_tags($bus_start_time));

                    $user[$iu]['wbtm_boarding_point'] = stripslashes(strip_tags($start_stops));

                    $user[$iu]['wbtm_dropping_point'] = stripslashes(strip_tags($end_stops));

                    $user[$iu]['wbtm_seat_fare'] = stripslashes(strip_tags($main_fare[$iu]));


                    if ($wbtm_user_name) {
                        if ($wbtm_user_name[$iu] != '') :
                            $user[$iu]['wbtm_user_name'] = stripslashes(strip_tags($wbtm_user_name[$iu]));
                        endif;
                    }

                    if ($wbtm_user_email) {
                        if ($wbtm_user_email[$iu] != '') :
                            $user[$iu]['wbtm_user_email'] = stripslashes(strip_tags($wbtm_user_email[$iu]));
                        endif;
                    }

                    if ($wbtm_user_phone) {
                        if ($wbtm_user_phone[$iu] != '') :
                            $user[$iu]['wbtm_user_phone'] = stripslashes(strip_tags($wbtm_user_phone[$iu]));
                        endif;
                    }

                    if ($wbtm_user_address) {
                        if ($wbtm_user_address[$iu] != '') :
                            $user[$iu]['wbtm_user_address'] = stripslashes(strip_tags($wbtm_user_address[$iu]));
                        endif;
                    }

                    if ($wbtm_user_gender) {
                        if (!empty($wbtm_user_gender)) {
                            if ($wbtm_user_gender[$iu] != '') :
                                $user[$iu]['wbtm_user_gender'] = stripslashes(strip_tags($wbtm_user_gender[$iu]));
                            endif;
                        }
                    }
                    $reg_form_arr = unserialize(get_post_meta($product_id, 'attendee_reg_form', true));
                    if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
                        foreach ($reg_form_arr as $reg_form) {
                            $user[$iu][$reg_form['field_id']] = stripslashes(strip_tags($_POST[$reg_form['field_id']][$iu]));
                        }
                    }

                    if ($extra_bag_quantity) {
                        if ($extra_bag_quantity[$iu] != '') :
                            $user[$iu]['wbtm_extra_bag_qty'] = stripslashes(strip_tags($extra_bag_quantity[$iu]));
                            $ext_bag = $ext_bag + $extra_bag_quantity[$iu];
                        endif;
                        $user[$iu]['wbtm_extra_bag_price'] = stripslashes(strip_tags($extra_bag_quantity[$iu] * $extra_bag_price));
                    }


                }
                for ($us = 0; $us < $count_seat; $us++) {
                    if ($wbtm_user_name) {
                        if ($wbtm_user_name[$us] != '') :
                            $user_s[$us]['wbtm_user_name'] = stripslashes(strip_tags($wbtm_user_name[$us]));
                        endif;
                    }

                    if ($wbtm_user_email) {
                        if ($wbtm_user_email[$us] != '') :
                            $user_s[$us]['wbtm_user_email'] = stripslashes(strip_tags($wbtm_user_email[$us]));
                        endif;
                    }

                    if ($wbtm_user_phone) {
                        if ($wbtm_user_phone[$us] != '') :
                            $user_s[$us]['wbtm_user_phone'] = stripslashes(strip_tags($wbtm_user_phone[$us]));
                        endif;
                    }

                    if ($wbtm_user_address) {
                        if ($wbtm_user_address[$us] != '') :
                            $user_s[$us]['wbtm_user_address'] = stripslashes(strip_tags($wbtm_user_address[$us]));
                        endif;
                    }

                    if ($wbtm_user_gender) {
                        if (!empty($wbtm_user_gender)) {
                            if ($wbtm_user_gender[$us] != '') :
                                $user_s[$us]['wbtm_user_gender'] = stripslashes(strip_tags($wbtm_user_gender[$us]));
                            endif;
                        }
                    }


                    $reg_form_arr = unserialize(get_post_meta($product_id, 'attendee_reg_form', true));
                    if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
                        foreach ($reg_form_arr as $reg_form) {
                            $user_s[$us][$reg_form['field_id']] = stripslashes(strip_tags($_POST[$reg_form['field_id']][$us]));
                        }
                    }
                    if ($extra_bag_quantity) {
                        if ($extra_bag_quantity[$us] != '') :
                            $user_s[$us]['wbtm_extra_bag_qty'] = stripslashes(strip_tags($extra_bag_quantity[$us]));
                            // $ext_bag = $ext_bag + $extra_bag_quantity[$us];
                        endif;
                    }
                    // $user_s[$us]['wbtm_extra_bag_price'] = stripslashes(strip_tags($extra_bag_quantity[$us] * $extra_bag_price));

                    if ($extra_bag_price) {
                        if (!empty($extra_bag_price)) {
                            if ($extra_bag_price[$us] != '') :
                                $user_s[$us]['wbtm_extra_bag_price'] = stripslashes(strip_tags($extra_bag_quantity[$us] * $extra_bag_price));
                            endif;
                        }
                    }

                }


                $cart_item_data['extra_bag_quantity'] = $ext_bag;


            } else {
                $user = array();
                $user_s = array();
                $cart_item_data['extra_bag_quantity'] = 0;
            }
            
            $count = count($seat_name);
            for ($i = 0; $i < $count; $i++) {

                if ($seat_name[$i] != '') :
                    $new[$i]['wbtm_seat_name'] = stripslashes(strip_tags($seat_name[$i]));
                endif;
            }
            // $extra_bag_price         = 100;
            $extra_bag_price = ($extra_bag_price * $ext_bag);


            $total_fare = $total_fare + $extra_bag_price;
            // $total_fare              = 349;
            $cart_item_data['wbtm_seats'] = $new;
            $cart_item_data['wbtm_start_stops'] = $start_stops;
            $cart_item_data['wbtm_end_stops'] = $end_stops;
            $cart_item_data['wbtm_journey_date'] = $journey_date;
            $cart_item_data['wbtm_journey_time'] = $user_start_time;
            $cart_item_data['wbtm_bus_time'] = $bus_start_time;
            $cart_item_data['wbtm_total_seats'] = $total_seat;

            $cart_item_data['wbtm_seat_original_fare'] = $original_fare;
            $cart_item_data['wbtm_seat_return_fare'] = $return_fare;
            $cart_item_data['is_return'] = $is_return;

            $cart_item_data['wbtm_passenger_info'] = $user;
            $cart_item_data['wbtm_single_passenger_info'] = $user_s;
            $cart_item_data['wbtm_basic_passenger_info'] = $user_b;
            $cart_item_data['wbtm_tp'] = $total_fare;
            $cart_item_data['wbtm_bus_id'] = $bus_id;
            $cart_item_data['bus_id'] = $product_id;
            $cart_item_data['line_total'] = $total_fare;
            $cart_item_data['line_subtotal'] = $total_fare;
            
        }
        return $cart_item_data;
    }

    function wbtm_add_custom_price($cart_object)
    {
        foreach ($cart_object->cart_contents as $key => $value) {
            $eid = $value['bus_id'];
            if (get_post_type($eid) == 'wbtm_bus') {
                $cp = $value['wbtm_tp'];
                $value['data']->set_price($cp);
                $new_price = $value['data']->get_price();
                $value['data']->set_price($cp);
                $value['data']->set_regular_price($cp);
                $value['data']->set_sale_price($cp);
                $value['data']->set_sold_individually('yes');
            }
        }
    }

    function wbtm_display_custom_fields_text_cart($item_data, $cart_item)
    {
        global $wbtmmain;
        if( get_post_type($cart_item['bus_id']) === 'wbtm_bus' ) {
            $wbtm_events_extra_prices = $cart_item['wbtm_seats'];
            $extra_bag_quantity = $cart_item['extra_bag_quantity'];
            $passenger_info = $cart_item['wbtm_passenger_info'];
            $basic_passenger_info = $cart_item['wbtm_basic_passenger_info'];
            $date_format = get_option('date_format');
            $time_format = get_option('time_format');
            $datetimeformat = $date_format . '  ' . $time_format;
            
            if ($wbtm_events_extra_prices) {
                $extra_bag_price = get_post_meta($cart_item['bus_id'], 'wbtm_extra_bag_price', true);
                // echo '<pre>'; print_r($passenger_info); die;
                if (is_array($passenger_info) && sizeof($passenger_info) > 0) {
                    
                    foreach ($passenger_info as $_passenger) {
                        //echo '<pre>';print_r($_passenger);echo '</pre>';
                        ?>
                        <ul class=event-custom-price>

                            <?php
                            if (isset($_passenger['wbtm_user_name'])) {
                                ?>
                                <li>
                                    <strong><?php mage_bus_label('wbtm_cart_name_text', __('Name:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_user_name']; ?>
                                </li>
                                <?php
                            }
                            if (isset($_passenger['wbtm_user_email'])) {
                                ?>
                                <li>
                                    <strong><?php mage_bus_label('wbtm_cart_email_text', __('Email:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_user_email']; ?>
                                </li>
                                <?php
                            }
                            if (isset($_passenger['wbtm_user_phone'])) {
                                ?>
                                <li>
                                    <strong><?php mage_bus_label('wbtm_cart_phone_text', __('Phone:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_user_phone']; ?>
                                </li>
                                <?php
                            }
                            if (isset($_passenger['wbtm_user_gender'])) {
                                ?>
                                <li>
                                    <strong><?php mage_bus_label('wbtm_cart_gender_text', __('Gender:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_user_gender']; ?>
                                </li>
                                <?php
                            }
                            if (isset($_passenger['wbtm_user_address'])) {
                                ?>
                                <li>
                                    <strong><?php mage_bus_label('wbtm_cart_address_text', __('Address:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_user_address']; ?>
                                </li>
                                <?php
                            }

                            $reg_form_arr = unserialize(get_post_meta($cart_item['bus_id'], 'attendee_reg_form', true));
                            if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
                                foreach ($reg_form_arr as $builder) {
                                    ?>
                                    <li>
                                        <strong><?php echo $builder['field_label'] . ':</strong> ' . $_passenger[$builder['field_id']]; ?>
                                    </li>
                                    <?php
                                }
                            }
                            ?>


                            <li>
                                <strong><?php mage_bus_label('wbtm_seat_no_text', __('Seat No', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo $_passenger['wbtm_seat_name']; ?>
                            </li>
                            <?php if ($_passenger['wbtm_passenger_type']) { ?>
                                <li>
                                    <strong><?php _e('Passenger Type', ''); ?>:</strong> <?php echo $_passenger['wbtm_passenger_type']; ?>
                                </li>
                            <?php } ?>
                            <li>
                                <strong><?php mage_bus_label('wbtm_cart_journey_date_text', __('Journey Date', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo get_wbtm_datetime($_passenger['wbtm_journey_date'], 'date-text'); ?>
                            </li>
                            <li>
                                <strong><?php mage_bus_label('wbtm_start_time_text', __('Start Time', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo date($time_format, strtotime($_passenger['wbtm_bus_start_time'])).' '.mage_time_24_to_12($_passenger['wbtm_bus_start_time']); ?>
                            </li>
                            <li>
                                <strong><?php mage_bus_label('wbtm_boarding_points_text', __('Boarding Point', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo $_passenger['wbtm_boarding_point']; ?>
                            </li>
                            <li>
                                <strong><?php mage_bus_label('wbtm_dropping_points_text', __('Dropping Point', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo $_passenger['wbtm_dropping_point']; ?>
                            </li>
                            <li>
                                <strong><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?>:</strong> <?php echo wc_price($_passenger['wbtm_seat_fare']); ?>
                            </li>
                            <?php
                            if (isset($_passenger['wbtm_extra_bag_qty'])) {
                                if ($_passenger['wbtm_extra_bag_qty'] > 0) {
                                    ?>
                                    <li>
                                        <strong><?php mage_bus_label('wbtm_extra_bag_text', __('Extra Bag:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo $_passenger['wbtm_extra_bag_qty']; ?>
                                    </li>
                                    <li>
                                        <strong><?php mage_bus_label('wbtm_extra_bag_price_text', __('Extra Bag Price:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo wc_price($_passenger['wbtm_extra_bag_price']); ?>
                                    </li>

                                    <li>
                                        <strong><?php mage_bus_label('wbtm_total_text', __('Total:', 'bus-ticket-booking-with-seat-reservation')); ?></strong> <?php echo wc_price($_passenger['wbtm_seat_fare']) . ' + ' . wc_price($_passenger['wbtm_extra_bag_price']) . ' = ' . wc_price($_passenger['wbtm_seat_fare'] + $_passenger['wbtm_extra_bag_price']); ?>
                                    </li>
                                    <?php
                                }
                            }
                            ?>


                        </ul>
                        
                        <?php
                        if ( ($cart_item['line_subtotal'] == $cart_item['wbtm_seat_return_fare']) && ($cart_item['is_return'] == 1) && ($cart_item['wbtm_seat_original_fare'] > $cart_item['wbtm_seat_return_fare']) ) {
                            $percent = ($cart_item['wbtm_seat_return_fare'] * 100) / $cart_item['wbtm_seat_original_fare'];
                            $percent = 100 - $percent;
                            echo '<p style="color:#af7a2d;font-size: 14px;line-height: 1em;"><strong>' . __('Congratulation!', 'bus-ticket-booking-with-seat-reservation') . '</strong> <span> ' . __('For a round trip, you got', 'bus-ticket-booking-with-seat-reservation') . ' <span style="font-weight:600">' . number_format($percent, 2) . '%</span> ' . __('discount on this trip', 'bus-ticket-booking-with-seat-reservation') . '</span></p>';
                        }
                    }
                    
                } else {

                    ?>
                    <ul class='event-custom-price'>
                        <li>
                            <?php echo $wbtmmain->bus_get_option('wbtm_seat_list_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_seat_list_text', 'label_setting_sec') . ': ' : __('Seat List:', 'bus-ticket-booking-with-seat-reservation');
                            $seat_lists = array_column($wbtm_events_extra_prices, 'wbtm_seat_name');
                            echo implode(', ', $seat_lists);
                            ?>
                        </li>
                        <li><?php echo $wbtmmain->bus_get_option('wbtm_select_journey_date_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_select_journey_date_text', 'label_setting_sec') . ': ' : __('Journey Date:', 'bus-ticket-booking-with-seat-reservation');
                            ?><?php echo $cart_item['wbtm_journey_date']; ?></li>
                        <li><?php echo $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_starting_text', 'label_setting_sec') . ': ' : __('Journey Time:', 'bus-ticket-booking-with-seat-reservation');
                            ?><?php echo $cart_item['wbtm_journey_time'].' '.mage_time_24_to_12($cart_item['wbtm_journey_time']); ?></li>
                        <li><?php echo $wbtmmain->bus_get_option('wbtm_boarding_points_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_boarding_points_text', 'label_setting_sec') . ': ' : __('Boarding Point:', 'bus-ticket-booking-with-seat-reservation');
                            ?><?php echo $cart_item['wbtm_start_stops']; ?></li>
                        <li><?php echo $wbtmmain->bus_get_option('wbtm_dropping_points_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_dropping_points_text', 'label_setting_sec') . ': ' : __('Dropping Point:', 'bus-ticket-booking-with-seat-reservation'); ?><?php echo $cart_item['wbtm_end_stops']; ?></li>

                        <?php if ($extra_bag_quantity > 0) { ?>
                            <li><?php _e('Extra Bag: ', 'bus-ticket-booking-with-seat-reservation');
                                echo '(' . $cart_item['extra_bag_quantity'] . ' x ' . $extra_bag_price . ') = ' . wc_price($cart_item['extra_bag_quantity'] * $extra_bag_price); ?></li>
                        <?php } ?>
                    </ul>
                    <?php
                    if ( ($cart_item['line_subtotal'] == $cart_item['wbtm_seat_return_fare']) && ($cart_item['is_return'] == 1) && ($cart_item['wbtm_seat_original_fare'] > $cart_item['wbtm_seat_return_fare']) ) {
                        $percent = ($cart_item['wbtm_seat_return_fare'] * 100) / $cart_item['wbtm_seat_original_fare'];
                        $percent = 100 - $percent;
                        echo '<p style="color:#af7a2d;font-size: 14px;line-height: 1em;"><strong>' . __('Congratulation!', 'bus-ticket-booking-with-seat-reservation') . '</strong> <span> ' . __('For a round trip, you got', 'bus-ticket-booking-with-seat-reservation') . ' <span style="font-weight:600">' . number_format($percent, 2) . '%</span> ' . __('discount on this trip', 'bus-ticket-booking-with-seat-reservation') . '</span></p>';
                    }
                }
            }
        }
        return $item_data;
    }


    function rei_after_checkout_validation($posted)
    {
        global $woocommerce, $wbtmmain;
        $items = $woocommerce->cart->get_cart();
        foreach ($items as $item => $values) {
            if (get_post_type($values['bus_id']) == 'wbtm_bus') {
                $wbtm_seats = $values['wbtm_seats'];
                $wbtm_passenger_info = $values['wbtm_passenger_info'];
                $wbtm_basic_passenger_info = $values['basic_passenger_info'];
                $wbtm_start_stops = $values['wbtm_start_stops'];
                $wbtm_end_stops = $values['wbtm_end_stops'];
                $wbtm_journey_date = $values['wbtm_journey_date'];
                $wbtm_journey_time = $values['wbtm_journey_time'];
                $wbtm_bus_start_time = $values['wbtm_bus_time'];
                $wbtm_bus_id = $values['wbtm_bus_id'];
            }
        }

        // echo $se = $wbtm_seats[0]['w/btm_seat_name'];

        // $check_before_order = $wbtmmain->wbtm_get_seat_cehck_before_place_order($wbtm_seats, $wbtm_journey_date, $wbtm_bus_id, $wbtm_start_stops);

        // if ($check_before_order > 0) {
        //     WC()->cart->empty_cart();
        //     wc_add_notice(__("Sorry, Your Selected Seat Already Booked by another user", 'woocommerce'), 'error');

        // }
    }

    function wbtm_add_custom_fields_text_to_order_items($item, $cart_item_key, $values, $order)
    {
        $eid = $values['bus_id'];
        if (get_post_type($eid) == 'wbtm_bus') {
            $wbtm_seats = $values['wbtm_seats'];
            $wbtm_passenger_info = $values['wbtm_passenger_info'];
            $wbtm_single_passenger_info = $values['wbtm_single_passenger_info'];
            $wbtm_basic_passenger_info = $values['wbtm_basic_passenger_info'];
            $wbtm_start_stops = $values['wbtm_start_stops'];
            $wbtm_end_stops = $values['wbtm_end_stops'];
            $wbtm_journey_date = $values['wbtm_journey_date'];
            $wbtm_journey_time = $values['wbtm_journey_time'];
            $wbtm_bus_start_time = $values['wbtm_bus_time'];
            $wbtm_bus_id = $values['wbtm_bus_id'];
            $extra_bag_quantity = $values['extra_bag_quantity'];
            $wbtm_tp = $values['wbtm_tp'];

            $seat = "";
            foreach ($wbtm_seats as $field) {
                // $item->add_meta_data( __( esc_attr($field['wbtm_seat_name'])));
                $seat .= $field['wbtm_seat_name'] . ",";
            }

            // .$seat =0;
            $item->add_meta_data('Seats', $seat);
            $item->add_meta_data('Start', $wbtm_start_stops);
            $item->add_meta_data('End', $wbtm_end_stops);
            $item->add_meta_data('Date', $wbtm_journey_date);
            $item->add_meta_data('Time', $wbtm_journey_time);
            $item->add_meta_data('Extra Bag', $extra_bag_quantity);
            $item->add_meta_data('_wbtm_tp', $wbtm_tp);
            $item->add_meta_data('_bus_id', $wbtm_bus_id);
            $item->add_meta_data('_btime', $wbtm_bus_start_time);
            $item->add_meta_data('_wbtm_passenger_info', $wbtm_passenger_info);
            $item->add_meta_data('_wbtm_single_passenger_info', $wbtm_single_passenger_info);
            $item->add_meta_data('_wbtm_basic_passenger_info', $wbtm_basic_passenger_info);

            $item->add_meta_data('_wbtm_bus_id', $eid);
        }


    }

}

new WbtmAddToCart();