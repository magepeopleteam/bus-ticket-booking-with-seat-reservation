<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

function outdated_item_remove() {
    global $woocommerce;
    $has_outdate = false;
    $items = $woocommerce->cart->get_cart();

    if($items) {
        $buffer_time = mage_bus_setting_value('bus_buffer_time');
        $buffer_time_sec = ($buffer_time && is_numeric($buffer_time)) ? $buffer_time * 60 * 60 : 0;
        $current = current_time('Y-m-d H:i');
        $c_str = strtotime($current);
        foreach($items as $key => $value) {
            if(isset($value['wbtm_bus_id'])) {
                $j_datetime = $value["wbtm_journey_date"]." ".($value["wbtm_journey_time"] ? $value["wbtm_journey_time"] : '23:59');
                $j_str = strtotime($j_datetime) - $buffer_time_sec; // journey time in seconds less buffer
                if($c_str > $j_str) {
                    $woocommerce->cart->remove_cart_item($key);
                    $has_outdate = true;
                }
            }
        }
    }

    return ($has_outdate ? $woocommerce->cart->get_cart() : $items);
}

add_action('template_redirect', 'wbtm_cart_item_have_two_way_route', 10);

// Main Function
function wbtm_cart_item_have_two_way_route() {
    global $woocommerce;
    $settings = get_option('wbtm_bus_settings');
    $val = isset($settings['bus_return_discount']) ? $settings['bus_return_discount'] : 'no';
    $is_return_discount_enable = $val ? $val : 'no';
    if($is_return_discount_enable == 'no') {
        return false;
    }

    if( is_cart() || is_checkout() ) {

        $items = outdated_item_remove(); // Remove outdated item
        $count_have_return = 0;
        if($items) {
            $item_count = count($items);
            foreach($items as $key => $value) {
                // echo $key.' ----> '. $value['is_return'].'<br>';
                if( $value['is_return'] && $item_count == 1 ) { // If cart item is single and has return route
                    wbtm_update_cart_return_price($key, true); // Update Return Price to original
                    
                } elseif( ($value['is_return'] == 1 || $value['is_return'] == 2 || $value['is_return'] == '') && $item_count > 1 ) { // If cart item is more than 1 and has return route

                    $start = $value['wbtm_start_stops'];
                    $stop = $value['wbtm_end_stops'];
                    $j_date = $value['wbtm_journey_date'];

                    $has_one_way = wbtm_check_has_one_way($start, $stop, $j_date);
                    //var_dump($has_one_way);
                    if(!$has_one_way) {
                        wbtm_update_cart_return_price($key, true); // Update Return Price to original
                    } else {
                        $count_have_return++;
                        if(($count_have_return % 2) == 0) { // Only single return route get discount (One way and return way) nothing else
                            wbtm_update_cart_return_price($key, false); // Update Return Price to return
                        } else {
                            wbtm_update_cart_return_price($key, true); // Update Return Price to original
                        }
                    }
                    
                } 
                // elseif( $value['is_return'] == 2 && $item_count > 1 ) { // If cart item is more than 1 and has return route (Cart item delete happend)

                //     $start = $value['wbtm_start_stops'];
                //     $stop = $value['wbtm_end_stops'];
                //     $j_date = $value['wbtm_journey_date'];

                //     $has_one_way = wbtm_check_has_one_way($start, $stop, $j_date);
                //     if(!$has_one_way) {
                //         wbtm_update_cart_return_price($key, true); // Update Return Price to original
                //     } else {
                //         wbtm_update_cart_return_price($key, false); // Update Return Price to return
                //     }
                    
                // } 
                else {
                    // Nothing to do!
                }
            }
        }

    }
    
}

// Check One way route is exits or not
function wbtm_check_has_one_way($start, $stop, $j_date) {
    global $woocommerce;

    $items = $woocommerce->cart->get_cart();
    $return = null;
    foreach($items as $key => $value) {

        // if($value['wbtm_journey_date']) {
        //     $cart_j_date = new DateTime($value['wbtm_journey_date']);
        // }

        if( ($start == $value['wbtm_end_stops']) && ($stop == $value['wbtm_start_stops']) ) {
            $return = 1;
            break;
        } else {
            $return = 0;
        }

    }

    return $return;
}

// Update Return Price
function wbtm_update_cart_return_price($key, $return, $recall = false) {

    $cart = WC()->cart->cart_contents;

    if($return) {
        foreach($cart as $id => $cart_item) {
            if($id == $key) {
                $ticket_price = $cart_item['wbtm_seat_original_fare'];
                $extra_service = extra_price($cart_item['extra_services']);
                $any_date_return_price = $ticket_price;
                $total_price = $ticket_price + $extra_service;
                if($cart_item['wbtm_anydate_return'] == 'on') {
                    $total_price = $total_price + $any_date_return_price;
                }

                $cart_item['line_subtotal']                                 = $total_price;
                $cart_item['wbtm_tp']                                       = $total_price;
                $cart_item['line_total']                                    = $total_price;
                $cart_item['wbtm_anydate_return_price']                     = $any_date_return_price;
                // $cart_item['wbtm_passenger_info'][0]['wbtm_seat_fare']      = $cart_item['wbtm_seat_original_fare'];
                $cart_item['is_return']                                     = 2;

            WC()->cart->cart_contents[$key] = $cart_item;
            break;
            }
        }
        
    } else {

        foreach($cart as $id => $cart_item) {
            if($id == $key) {
                $ticket_price = $cart_item['wbtm_seat_return_fare'];
                $extra_service = extra_price($cart_item['extra_services']);
                $any_date_return_price = $ticket_price;
                $total_price = $ticket_price + $extra_service;
                if($cart_item['wbtm_anydate_return'] == 'on') {
                    $total_price = $total_price + $any_date_return_price;
                }

                $cart_item['line_subtotal']                                 = $total_price;
                $cart_item['wbtm_tp']                                       = $total_price;
                $cart_item['line_total']                                    = $ticket_price;
                $cart_item['wbtm_anydate_return_price']                     = $any_date_return_price;
                // $cart_item['wbtm_passenger_info'][0]['wbtm_seat_fare']      = $cart_item['wbtm_seat_return_fare'];
                $cart_item['is_return']                                     = 1;

                WC()->cart->cart_contents[$key] = $cart_item;

                if(!$recall) {
                    $this_start = $cart_item['wbtm_start_stops'];
                    $this_stop = $cart_item['wbtm_end_stops'];
                }

                break;
            }
        }

        if(isset($this_start) && isset($this_stop)) {
            foreach($cart as $id => $cart_item) {

                if( $this_start == $cart_item['wbtm_end_stops'] && $this_stop == $cart_item['wbtm_start_stops'] ) {
                    wbtm_update_cart_return_price($id, false, true);
                }

            }
        }
    }

    WC()->cart->set_session(); // Finaly Update Cart
}