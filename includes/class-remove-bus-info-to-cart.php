<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.


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

        $items = $woocommerce->cart->get_cart();
        // echo '<pre>'; print_r($items); die;
        if($items) {
            $item_count = count($items);
            foreach($items as $key => $value) {
                if( $value['is_return'] && $item_count == 1 ) { // If cart item is single and has return route
                    wbtm_update_cart_return_price($key, true); // Update Return Price to original
                    
                } elseif( $value['is_return'] == 1 && $item_count > 1 ) { // If cart item is more than 1 and has return route

                    $start = $value['wbtm_start_stops'];
                    $stop = $value['wbtm_end_stops'];
                    $j_date = $value['wbtm_journey_date'];

                    $has_one_way = wbtm_check_has_one_way($start, $stop, $j_date);
                    
                     if(!$has_one_way) {
                        
                        wbtm_update_cart_return_price($key, true); // Update Return Price to original
                    } else {
                        
                        wbtm_update_cart_return_price($key, false); // Update Return Price to return
                    }
                    
                } elseif( $value['is_return'] == 2 && $item_count > 1 ) { // If cart item is more than 1 and has return route (Cart item delete happend)

                    $start = $value['wbtm_start_stops'];
                    $stop = $value['wbtm_end_stops'];
                    $j_date = $value['wbtm_journey_date'];

                    $has_one_way = wbtm_check_has_one_way($start, $stop, $j_date);
                    
                    if(!$has_one_way) {
                        wbtm_update_cart_return_price($key, true); // Update Return Price to original
                    } else {
                        wbtm_update_cart_return_price($key, false); // Update Return Price to return
                    }
                    
                } else {
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
                $cart_item['line_subtotal']                                 = $cart_item['wbtm_seat_original_fare'];
                $cart_item['wbtm_tp']                                       = $cart_item['wbtm_seat_original_fare'];
                $cart_item['line_total']                                    = $cart_item['wbtm_seat_original_fare'];
                // $cart_item['wbtm_passenger_info'][0]['wbtm_seat_fare']      = $cart_item['wbtm_seat_original_fare'];
                $cart_item['is_return']                                     = 2;

            WC()->cart->cart_contents[$key] = $cart_item;
            break;
            }
        }
        
    } else {

        foreach($cart as $id => $cart_item) {
            if($id == $key) {
                $cart_item['line_subtotal']                                 = $cart_item['wbtm_seat_return_fare'];
                $cart_item['wbtm_tp']                                       = $cart_item['wbtm_seat_return_fare'];
                $cart_item['line_total']                                    = $cart_item['wbtm_seat_return_fare'];
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