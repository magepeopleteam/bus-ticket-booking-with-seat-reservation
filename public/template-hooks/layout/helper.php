<?php
function mage_bus_isset($parameter) {
    return isset($_GET[$parameter]) ? strip_tags($_GET[$parameter]) : false;
}

function mage_bus_translate($parameter) {
    return isset($_GET[$parameter]) ? strip_tags($_GET[$parameter]) : false;
}

function mage_bus_text($text) {
    _e($text, 'bus-ticket-booking-with-seat-reservation');
}

function mage_bus_label($var, $text, $is_return = false) {
    global $wbtmmain;
    if($is_return) {
        return $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
    } else {
        echo $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
    }
}

// check search day is off?
function mage_check_search_day_off($id, $j_date) {

    $db_day_prefix = 'offday_';
    if( $j_date ) {
        $j_date_day = strtolower(date('D', strtotime($j_date)));
        $get_day = get_post_meta( $id, $db_day_prefix.$j_date_day, true );
        $get_day = ($get_day != null) ? strtolower($get_day) : null;

        if($get_day == 'yes') {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
    
}

// convert date formate
// function mage_convert_date_format($date, $format) {
//     $wp_date_format = get_option('date_format');
//     if(strpos($wp_date_format, ' ') || strpos($wp_date_format, ',')) {
//         $wp_date_format = 'Y-m-d';
//     } else {
//         $wp_date_format = str_replace('/', '-', $wp_date_format);
//     }
    
//     $myDateTime = date_create_from_format($wp_date_format, $date);
//     $final = date_format($myDateTime, 'Y-m-d');
//     return $final;
// }

// convert date formate
function mage_convert_date_format($date, $format) {
    $setting_format = get_option('date_format');

    if(!$date) {
        return null;
    }
    
    if( preg_match('/\s/',$setting_format) ) {

        return date($format, strtotime($date));

    } else {
        $setting_format__dashed = str_replace('/', '-', $setting_format);
        $setting_format__dashed = str_replace('.', '-', $setting_format__dashed);

        $dash_date = str_replace('/', '-', $date);
        $dash_date = str_replace('.', '-', $dash_date);
        // echo $setting_format__dashed.'<br>';
        // echo $dash_date.'<br>';
        $date_f = DateTime::createFromFormat($setting_format__dashed , $dash_date);
        if($date_f) {
            $res = $date_f->format($format);
            return $res;
        } else {
            return null;
        }
        
    }
}

// check bus on Date
function mage_bus_on_date($id, $j_date) {
    if($j_date) {
        $is_on_date = 'no';
        $on_dates = get_post_meta( $id, 'wbtm_bus_on_dates', true );
        if($on_dates) {
            $is_on_date = 'has';
            $on_dates = explode(', ', $on_dates);

            foreach($on_dates as $date) {
                $date = date('Y-m-d', strtotime($date));
                if( $j_date == $date ) {
                    $is_on_date = 'yes';
                break;
                }
            }

        }

        return $is_on_date;

    } else {
        return null;
    }
}

function mage_route_list($single_bus, $start_route) {
    echo '<ul class="mage_input_select_list">';
    if ($single_bus) {
        if ($start_route) {
            $start_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
            foreach ($start_stops as $route) {
                echo '<li data-route="' . $route['wbtm_bus_bp_stops_name'] . '"><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_bp_stops_name'] . '</li>';
            }
        } else {
            $end_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
            foreach ($end_stops as $route) {
                echo '<li data-route="' . $route['wbtm_bus_next_stops_name'] . '"><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_next_stops_name'] . '</li>';
            }
        }
    } else {
        $routes = get_terms(array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false,
        ));
        foreach ($routes as $route) {
            echo '<li data-route="' . $route->name . '"><span class="fa fa-map-marker"></span>' . $route->name . '</li>';
        }
    }
    echo '</ul>';
}

function mage_search_bus_query($return) {
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    return array(
        'post_type' => array('wbtm_bus'),
        'posts_per_page' => -1,
        'order' => 'ASC',
        'orderby' => 'meta_value',
        // 'meta_key' => 'wbtm_bus_start_time',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'wbtm_bus_bp_stops',
                'value' => $start,
                'compare' => 'LIKE',
            ),
            array(
                'key' => 'wbtm_bus_next_stops',
                'value' => $end,
                'compare' => 'LIKE',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_seat_type_conf',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => 'wbtm_seat_type_conf',
                    'value' => 'wbtm_seat_plan',
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_seat_type_conf',
                    'value' => 'wbtm_without_seat_plan',
                    'compare' => '=',
                ),
            )
        )

    );
}

/* 
* Mainly working for Bus search result
* To check bus search end point has before search start point in the Bus Boarding points array
* if return false, then its ok for being in search result
* if return true, then its not ok for being in search result
* @param1 search start point
* @param2 search end point
* @param3 The Bus Boarding points array
* @return Bool
*/
function mage_bus_end_has_prev($start, $end, $boarding_array) {
    $s = $e = '';
    $strict = 2;
    if($end && $start && is_array($boarding_array) && !empty($boarding_array)) {

        $s = $start;
        $e = $end;

        $rearrange_array = array_column($boarding_array, 'wbtm_bus_bp_stops_name');
        $start_pos = array_search($s, $rearrange_array);
        $end_pos = array_search($e, $rearrange_array);

        if($end_pos === 0) {
            $strict = 3;
        }
        
        if($end_pos == false && is_bool($end_pos)) {
            return false;
        } else {
            if( $end_pos > $start_pos && !is_bool($start_pos) ) {
                return false; // Ok
            } else {
                return true;
            }
        }

    }

    return true; // Not ok
}

/* 
* Mainly working for Bus search result
* To check bus search start point has after search end point in the Bus Dropping points array
* if return false, then its ok for being in search result
* if return true, then its not ok for being in search result
* @param1 search start point
* @param2 search end point
* @param3 The Bus Boarding points array
* @return Bool
*/
function mage_bus_start_has_next($start, $end, $dropping_array) {
    $s = $e = '';
    $strict = 2;
    $strict2 = 2;
    if($end && $start && is_array($dropping_array) && !empty($dropping_array)) {

        $s = $start;
        $e = $end;

        $rearrange_array = array_column($dropping_array, 'wbtm_bus_next_stops_name');
        $start_pos = array_search($s, $rearrange_array);
        $end_pos = array_search($e, $rearrange_array);
        // return $end_pos.' '.$start_pos;
        if($end_pos === 0) {
            $strict = 3;
        }
        // if($start_pos === 0) {
        //     $strict2 = 3;
        // }

        if($end_pos == false && is_bool($end_pos)) {
            return false;
        } else {
            if( $end_pos > $start_pos && !is_bool($start_pos) ) {
                return false; // Ok
            } else {
                return true;
            }
        }

    }

    return true; // Not ok
}

function mage_bus_title() {
    ?>
    <div class="mage_flex_mediumRadiusTop mage_bus_list_title ">
        <div class="mage_bus_img flexCenter"><h6><?php mage_bus_label('wbtm_image_text', __('Image', 'bus-ticket-booking-with-seat-reservation'));        
        ?></h6></div>
        <div class="mage_bus_info flexEqual flexCenter">
            <div class="flexEqual">
                <h6><?php mage_bus_label('wbtm_bus_name_text', __('Bus', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
                <h6 class="mage_hidden_xxs"><?php  mage_bus_label('wbtm_schedule_text', __('Schedule', 'bus-ticket-booking-with-seat-reservation'));  ?></h6>
            </div>
            <div class="flexEqual flexCenter textCenter">
                <h6 class="mage_hidden_xxs"><?php mage_bus_label('wbtm_type_text', __('Coach Type', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
                <h6 class="mage_hidden_xs"><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
                <h6 class="mage_hidden_md"><?php mage_bus_label('wbtm_seats_available_text', __('Available', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
                <h6><?php mage_bus_label('wbtm_view_text', __('Action', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
            </div>
        </div>
    </div>
    <?php
}

function mage_get_bus_seat_plan_type() {
    $id = get_the_id();
    $seat_cols = get_post_meta($id, 'wbtm_seat_cols', true);
    $seats = get_post_meta($id, 'wbtm_bus_seats_info', true);
    if ($seat_cols && $seat_cols > 0 && is_array($seats) && sizeof($seats) > 0) {
        return (int)$seat_cols;
    } else {
        $current_plan = get_post_meta($id, 'seat_plan', true);
        $bus_meta = get_post_custom($id);
        if (array_key_exists('wbtm_seat_col', $bus_meta)) {
            $seat_col = $bus_meta['wbtm_seat_col'][0];
            $seat_col_arr = explode(",", $seat_col);
            $seat_column = count($seat_col_arr);
        } else {
            $seat_column = 0;
        }
        if ($current_plan) {
            $current_seat_plan = $current_plan;
        } else {
            if ($seat_column == 4) {
                $current_seat_plan = 'seat_plan_1';
            } else {
                $current_seat_plan = 'seat_plan_2';
            }
        }
        return $current_seat_plan;
    }
}

//bus off date check
function mage_bus_off_date_check($return) {
    $start_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_start', true));
    $end_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_end', true));
    $date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));

    return (($start_date <= $date) && ($end_date >= $date)) ? false : true;
}

//bus off date check
function mage_bus_off_day_check($return) {
    $current_day = 'offday_' . strtolower(date('D', strtotime($return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date')))));
    return get_post_meta(get_the_id(), $current_day, true) == 'yes' ? false : true;
}

//bus setting on date
function mage_bus_on_date_setting_check($return) {
    $mage_bus_on_dates = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_on_dates', true));
    $date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));

    $mage_bus_on = array();
    if (!empty($mage_bus_on_dates) && is_array($mage_bus_on_dates)) {
        foreach ($mage_bus_on_dates as $value) {
            $mage_bus_on[] = $value['wbtm_on_date_name'];
        }
        return in_array($date, $mage_bus_on) ? true : false;
    } else {
        return false;
    }
}

//buffer time check
function mage_buffer_time_check($return) {
    $date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));
    $buffer_time = mage_bus_setting_value('bus_buffer_time', 0);
    $start_time = strtotime($date . ' ' . date('H:i:s', strtotime(mage_bus_time($return, false))));
    $current_time = strtotime(current_time('Y-m-d H:i:s'));
    $dif = round(($start_time - $current_time) / 3600, 1);
    return ($dif >= $buffer_time) ? true : false;
}

//return bus time
function mage_bus_time($return, $dropping) {
    if ($dropping) {
        $start = mage_bus_isset($return ? 'bus_start_route' : 'bus_end_route');
    } else {
        $start = mage_bus_isset($return ? 'bus_end_route' : 'bus_start_route');
    }

    $meta_key = $dropping ? 'wbtm_bus_next_stops' : 'wbtm_bus_bp_stops';
    $array_key = $dropping ? 'wbtm_bus_next_stops_name' : 'wbtm_bus_bp_stops_name';
    $array_value = $dropping ? 'wbtm_bus_next_end_time' : 'wbtm_bus_bp_start_time';
    $array = maybe_unserialize(get_post_meta(get_the_id(), $meta_key, true));
    foreach ($array as $key => $val) {
        if ($val[$array_key] === $start) {
            return $val[$array_value];
        }
    }
    return false;
}

//return setting value
function mage_bus_setting_value($key, $default = null) {
    $settings = get_option('wbtm_bus_settings');
    $val = isset($settings[$key]) ? $settings[$key] : null;
    return $val ? $val : $default;
}

//return check bus on off
function mage_bus_run_on_date($return) {
    if (((mage_bus_off_date_check($return) && mage_bus_off_day_check($return)) || mage_bus_on_date_setting_check($return)) && mage_buffer_time_check($return)) {
        return true;
    }
    return false;

}

//bus type return (ac/non ac)
function mage_bus_type() {
    return get_the_terms(get_the_id(), 'wbtm_bus_cat') ? get_the_terms(get_the_id(), 'wbtm_bus_cat')[0]->name : '';
}

// bus total seat
function mage_bus_total_seat() {
    $bus_id = get_the_id();
    $seat_plan_type = mage_get_bus_seat_plan_type();
    if ($seat_plan_type > 0) {
        $seats_rows = get_post_meta($bus_id, 'wbtm_bus_seats_info', true);
        $seat_col = get_post_meta($bus_id, 'wbtm_seat_cols', true);
        $total_seat = 0;
        foreach ($seats_rows as $seat) {
            for ($i = 1; $i <= $seat_col; $i++) {
                $seat_name = strtolower($seat["seat" . $i]);
                if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
                    $total_seat++;
                }
            }
        }
        $seats_dd = get_post_meta($bus_id, 'wbtm_bus_seats_info_dd', true);
        $seat_col_dd = get_post_meta($bus_id, 'wbtm_seat_rows_dd', true);
        if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
            foreach ($seats_dd as $seat) {
                for ($i = 1; $i <= $seat_col_dd; $i++) {
                    $seat_name = isset($seat["dd_seat" . $i]) ? $seat["dd_seat" . $i] : '';
                    if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
                        $total_seat++;
                    }
                }
            }
        }
        return $total_seat;
    } else {
        $bus_meta = get_post_custom($bus_id);
        $seats_rows = explode(",", $bus_meta['wbtm_seat_row'][0]);
        $seat_col = $bus_meta['wbtm_seat_col'][0];
        $seat_col_arr = explode(",", $seat_col);
        return count($seats_rows) * count($seat_col_arr);
    }
}

function mage_bus_total_seat_new() {
    $id = get_the_ID();
    $seat_type_conf = get_post_meta($id, 'wbtm_seat_type_conf', true);
    $total_seat = 0;

    if($seat_type_conf == 'wbtm_seat_plan') {
        $seats_rows = get_post_meta($id, 'wbtm_bus_seats_info', true);
        $seat_col = get_post_meta($id, 'wbtm_seat_cols', true);

        if($seats_rows && $seat_col) {
            foreach ($seats_rows as $seat) {
                for ($i = 1; $i <= (int)$seat_col; $i++) {
                    $seat_name = strtolower($seat["seat" . $i]);
                    if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
                        $total_seat++;
                    }
                }
            }
            $seats_dd = get_post_meta($id, 'wbtm_bus_seats_info_dd', true);
            $seat_col_dd = get_post_meta($id, 'wbtm_seat_cols_dd', true);
            if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
                foreach ($seats_dd as $seat) {
                    for ($i = 1; $i <= $seat_col_dd; $i++) {
                        $seat_name = isset($seat["dd_seat" . $i]) ? $seat["dd_seat" . $i] : '';
                        if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
                            $total_seat++;
                        }
                    }
                }
            }
        }
    } else {
        $total_seat = get_post_meta($id, 'wbtm_total_seat', true);
    }

    return $total_seat;

}

//bus available seat
function mage_bus_available_seat($return) {
    return mage_bus_total_seat_new() - mage_bus_sold_seat($return);
}

//sold seat return
function mage_bus_sold_seat($return) {
    $bus_id = get_the_id();
    $date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
    $args = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_journey_date',
                    'value' => $date,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_seat',
                    'value' => NULL,
                    'compare' => '!='
                )
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_status',
                    'value' => 1,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_status',
                    'value' => 2,
                    'compare' => '='
                )
            )
        )
    );
    $q = new WP_Query($args);
    return $q->post_count > 0 ? $q->post_count : 0;
}

//seat price
function mage_bus_seat_price($bus_id,$start, $end, $dd, $seat_type = null, $return_price = false) {
    $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));
    // var_dump($return_price);
    // echo '<pre>'; print_r($start);
    // echo '<pre>'; print_r($price_arr); die;
    if(!empty($price_arr) && is_array($price_arr)) {
        // $price_arr = array_values($price_arr);
        foreach($price_arr as $value) {
            if( (strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end)) && ($value['wbtm_bus_price'] == 0 || $value['wbtm_bus_price'] == null) ) {
                return false;
            }
        }
    } else {
        return false;
    }

    $seat_dd_increase = (int)get_post_meta($bus_id, 'wbtm_seat_dd_price_parcent', true);
    // $seat_dd_increase = 10;
    $dd_price_increase = ($dd && $seat_dd_increase) ? $seat_dd_increase : 0;

    $return_price_data = false;
    foreach ($price_arr as $key => $val) {
        $p_start = strtolower($val['wbtm_bus_bp_price_stop']);
        $p_end = strtolower($val['wbtm_bus_dp_price_stop']);

        $start = strtolower($start);
        $end = strtolower($end);
        if ($p_start === $start && $p_end === $end && !$return_price) { // Not return
            if (1 == $seat_type) {

                $price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);

            } elseif (2 == $seat_type) {

                $price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);

            } elseif (3 == $seat_type) {
                
                $price = $val['wbtm_bus_special_price'] + ($val['wbtm_bus_special_price'] * $dd_price_increase / 100);

            } else {
                $price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
            }
            $return_price_data = $price;
            break;
        }
        if ($p_start === $start && $p_end === $end && $return_price) { // Return
            if (1 == $seat_type) {
                $p = (($val['wbtm_bus_child_price_return']) ? $val['wbtm_bus_child_price_return'] : $val['wbtm_bus_child_price']);
                $price = $p + ($p * $dd_price_increase / 100);

            } elseif (2 == $seat_type) {
                $p = (($val['wbtm_bus_infant_price_return']) ? $val['wbtm_bus_infant_price_return'] : $val['wbtm_bus_infant_price']);
                $price = $p + ($p * $dd_price_increase / 100);

            } elseif (3 == $seat_type) {
                $p = (($val['wbtm_bus_special_price']) ? $val['wbtm_bus_special_price'] : 0);
                $price = $p + ($p * $dd_price_increase / 100);

            } else {
                $p = (($val['wbtm_bus_price_return']) ? $val['wbtm_bus_price_return'] : $val['wbtm_bus_price']);
                $price = $p + ($p * $dd_price_increase / 100);
            }
            $return_price_data = $price;
            break;
        }
    }

    return $return_price_data;
}

function mage_bus_passenger_type($return, $dd) {
    $id = get_the_id();
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $price_arr = maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
    $seat_panel_settings = get_option('wbtm_bus_settings');
    $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
    $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
    $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
    $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
    foreach ($price_arr as $key => $val) {
        if (strtolower($val['wbtm_bus_bp_price_stop']) === strtolower($start) && strtolower($val['wbtm_bus_dp_price_stop']) === strtolower($end)) {
            // if (mage_bus_multiple_passenger_type_check($id, $start, $end)) {
                $dd_price_increase = 0;
                if ($dd) {
                    $seat_dd_increase = (int)get_post_meta($id, 'wbtm_seat_dd_price_parcent', true);
                    $dd_price_increase = $seat_dd_increase ? $seat_dd_increase : 0;
                }
                ?>
                <div class="passenger_type_list">
                    <ul>
                        <?php
                        if ($val['wbtm_bus_price'] > 0) {
                            $price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="'. $adult_label .'">' . $adult_label.' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_child_price'] > 0) {
                            $price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="'. $child_label .'">' . $child_label.' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_infant_price'] > 0) {
                            $price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="'. $infant_label .'">' . $infant_label .' '. wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        // if ($val['wbtm_bus_special_price'] > 0) {
                        //     $price = $val['wbtm_bus_special_price'] + ($val['wbtm_bus_special_price'] * $dd_price_increase / 100);
                        //     echo '<li data-seat-price="' . $price . '" data-seat-type="3" data-seat-label="'. $special_label .'">' . $special_label.' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        // }
                        ?>
                    </ul>
                </div>
                <?php
            // }
        }
    }
}

function mage_bus_passenger_type_admin($return, $dd) {
    global $wbtmmain;
    $id = get_the_id();
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $price_arr = maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
    $seat_panel_settings = get_option('wbtm_bus_settings');
    $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
    $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
    $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
    $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
    $rdate               = isset( $_GET['j_date'] ) ? sanitize_text_field($_GET['j_date']) : date('Y-m-d');
    $uid = get_the_id().$wbtmmain->wbtm_make_id($rdate);
    foreach ($price_arr as $key => $val) {
        if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
            if (mage_bus_multiple_passenger_type_check($id, $start, $end)) {
                $dd_price_increase = 0;
                if ($dd) {
                    $seat_dd_increase = (int)get_post_meta($id, 'wbtm_seat_dd_price_parcent', true);
                    $dd_price_increase = $seat_dd_increase ? $seat_dd_increase : 0;
                }
                ?>
                <div class="<?php echo 'admin_'.$uid; ?> admin_passenger_type_list">
                    <ul>
                        <?php
                        if ($val['wbtm_bus_price'] > 0) {
                            $price = $val['wbtm_bus_price'] + ( $dd_price_increase != 0 ? ($val['wbtm_bus_price'] * $dd_price_increase / 100) : 0 );
                            echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="'. $adult_label .'">' . $adult_label.' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_child_price'] > 0) {
                            $price = $val['wbtm_bus_child_price'] + ( $dd_price_increase != 0 ? ($val['wbtm_bus_child_price'] * $dd_price_increase / 100) : 0 );
                            echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="'. $child_label .'">' . $child_label.' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_infant_price'] > 0) {
                            $price = $val['wbtm_bus_infant_price'] + ( $dd_price_increase != 0 ? ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100) : 0 );
                            echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="'. $infant_label .'">' . $infant_label .' '. wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        ?>
                    </ul>
                </div>
                <?php
            }
        }
    }
}

function mage_bus_multiple_passenger_type_check($id, $start, $end) {
    $price_arr = maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
    foreach ($price_arr as $key => $val) {
        if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
            if ($val['wbtm_bus_price'] && ($val['wbtm_bus_child_price'] || $val['wbtm_bus_infant_price'])) {
                return true;
            }
        }
    }
    return false;
}

// check product in cart
function mage_bus_in_cart($seat_name) {
    $product_id = get_the_id();
    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] === $product_id) {
            if ($seat_name) {
                foreach ($cart_item['wbtm_seats'] as $item) {
                    if ($item['wbtm_seat_name'] == $seat_name) {
                        return true;
                    }
                }
            } else {
                return true;
            }
        }
    }
    return false;
}

//find seat status
function mage_bus_seat_status($field_name, $return) {
    $date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $bus_id = get_the_id();
    $args = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => 1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_seat',
                    'value' => $field_name,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_journey_date',
                    'value' => $date,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '='
                ),
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_boarding_point',
                    'value' => $start,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wbtm_next_stops',
                    'value' => $start,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wbtm_next_stops',
                    'value' => $end,
                    'compare' => 'LIKE'
                ),
            )
        ),
    );
    $q = new WP_Query($args);
    $booking_id = ( isset($q->posts[0]) ? $q->posts[0]->ID : null );
    // return $booking_id;
    return get_post_meta($booking_id, 'wbtm_status', true) ? get_post_meta($booking_id, 'wbtm_status', true) : 0;
}

// Get seat Booking Data
function get_seat_booking_data($seat_name, $search_start, $search_end, $all_stopages_name, $return) {
    if(!$seat_name) {
        return false;
    }
    // Return
    $data = array(
        'status' => null,
        'has_booked' => false
    );
    $date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $bus_id = get_the_id();
    $args = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_seat',
                    'value' => $seat_name,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_journey_date',
                    'value' => $date,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '='
                ),
            )
        ),
    );
    $q = new WP_Query($args);

    if($q->posts) {
        foreach($q->posts as $post) {
            $data['has_booked'] = false;
            $bid = $post->ID;
            $boarding = get_post_meta($bid, 'wbtm_boarding_point', true);
            $dropping = get_post_meta($bid, 'wbtm_droping_point', true);
            $status = get_post_meta($bid, 'wbtm_status', true);

            $get_seat_boarding_position = array_search($boarding, $all_stopages_name);
            $get_seat_droping_position  = array_search($dropping, $all_stopages_name);

            $get_seat_droping_position = (is_bool($get_seat_droping_position) && !$get_seat_droping_position ? count($all_stopages_name) : $get_seat_droping_position); // Last Stopage position assign

            if ( ($get_seat_boarding_position > $search_start) && ($get_seat_boarding_position >= $search_end) ) {
                $data['status'] = $status;
                $data['has_booked'] = false;
            } elseif ( ($search_start >= $get_seat_droping_position) && ($search_end > $get_seat_droping_position) ) {
                $data['status'] = $status;
                $data['has_booked'] = false;
            } else {
                $data['status'] = $status;
                $data['has_booked'] = true;
                break;
            }
        }
    }

    return $data;
}

//find seat Droping Point
function mage_bus_seat_droping_point($field_name, $point, $return) {
    $date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $bus_id = get_the_id();
    $args = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_seat',
                    'value' => $field_name,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_journey_date',
                    'value' => $date,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '='
                ),
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_boarding_point',
                    'value' => $start,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wbtm_next_stops',
                    'value' => $start,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'wbtm_next_stops',
                    'value' => $end,
                    'compare' => 'LIKE'
                ),
            )
        ),
    );
    $q = new WP_Query($args);
    // $booking_id = $q->posts[0]->ID;
    $booking_id = ( isset($q->posts[0]) ? $q->posts[0]->ID : null );
    return get_post_meta($booking_id, $point, true) ? get_post_meta($booking_id, $point, true) : 0;
}

// Return Array
function mage_bus_get_all_stopages($post_id) {
    $total_stopage = 0;

    $all_stopage = get_post_meta($post_id, 'wbtm_bus_prices', true);

    if ($all_stopage) {

        $input = (is_array($all_stopage) ? $all_stopage : unserialize($all_stopage));

        $input = array_column($input, 'wbtm_bus_bp_price_stop');
        $all_stopage = array_unique($input);
        $all_stopage = array_values($all_stopage);

        return $all_stopage;
    }

    return;
}

function mage_bus_get_option($option, $section, $default = '') {
    $options = get_option($section);

    if (isset($options[$option])) {
        return $options[$option];
    }

    return $default;
}

// Check Cart has Oppsite route
// Note: $return_discount === 2
function mage_cart_has_opposite_route($current_start, $current_stop, $current_j_date, $return = false, $current_r_date = null) {
    global $woocommerce;
    
    $data = 0;
    $items = $woocommerce->cart->get_cart();
    if(count($items) > 0) {

        $wbtm_start_stops_current   = $current_start;
        $wbtm_end_stops_current     = $current_stop;
        $journey_date_current       = $current_j_date;


        // foreach( $items as $item => $value ) {
        //     if( ($value['is_return'] == 1) ) {
        //         return 0;
        //     }
        // }

        if($journey_date_current) {
            $journey_date_current = new DateTime($journey_date_current);
        }

        if($current_r_date) {
            $current_r_date = new DateTime($current_r_date);
        }


        foreach( $items as $item => $value ) {

            if(array_key_exists('wbtm_journey_date',$value) && $value['wbtm_journey_date']) {
                $cart_j_date = new DateTime($value['wbtm_journey_date']);
            }

            if($return) { // Return
                if( ($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops']) ) {
                    $data = 1;
                    break;
                } else {
                    $data = 0;
                }
            } else { // Not return
                if( array_key_exists('wbtm_end_stops',$value) && ($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops']) ) {
                    $data = 1;
                    break;
                } else {
                    $data = 0;
                }
            }

        }
    }

    return $data;
}

function mage_cart_has_opposite_route_P() {
    global $woocommerce;
    
    $items = $woocommerce->cart->get_cart();
    if(count($items) > 0) {

        foreach( $items as $item => $value ) {

            foreach($items as $k => $v) {
                if(count($v['wbtm_passenger_info']) > 1) {
                    return 1;
                } else {
                    return 0;
                }
            }

        }
    }
}

// Convert 24 to 12 time
// function mage_time_24_to_12($time) {
//     $t = '';
//     if($time && strpos($time, ':') !== false) {
//         $t = explode(':', $time);
//         $tm = ((int)$t[0] < 12) ? 'am' : 'pm';
//         $t = $tm;
//     }

//     return $t;
// }


/* Convert 24 to 12 time
@param 1 24 hours time string
@param 2 Bool
Is show 12 hours time with am/pm.
Is true show full time
Is false show only am/pm
*/
function mage_time_24_to_12($time, $full = true) {
    $t = '';
    if($time && strpos($time, ':') !== false) {
        if(strpos($time, 'AM') || strpos($time, 'am') || strpos($time, 'PM') || strpos($time, 'pm')) {
            $time = trim(str_replace('AM', '', $time));
            $time = trim(str_replace('am', '', $time));
            $time = trim(str_replace('PM', '', $time));
            $time = trim(str_replace('pm', '', $time));
        }

        $t = explode(':', $time);
        $h = $t[0];
        $m = $t[1];
        $tm = ($h < 12) ? 'am' : 'pm';
        
        if(!$full) {
            return $tm;

        } else {
            if($h > 12) {
                $tt = $h - 12;
                $t = $tt.':'.$m.' '.$tm;
            } elseif ($h == '00' || $h == '24') {
                $t = '00'.':'.$m.' am';
            } else {
                $t = $h.':'.$m.' '.$tm;
            }
        }
        // $t = $tm;
    }

    return $t;
}

// Convert to wp time format
function mage_wp_time($time) {
    $wp_time_format = get_option('time_format');

    if($time && $wp_time_format) {
        $time  = date($wp_time_format, strtotime($time));
    }

    return $time;
}

function mage_wp_date($date, $format = false) {
    $wp_date_format = get_option('date_format');

    if($date && $format) {
        $date = date($format, strtotime($date));

        return $date;
    }

    if($date && $wp_date_format) {
        $date  = date($wp_date_format, strtotime($date));
    }

    return $date;
}

// Extra services qty check
function extra_service_qty_check($bus_id, $start, $end, $j_date, $service_type) {

    $count_q = 0;

    $argss = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_boarding_point',
                    'compare' => '=',
                    'value' => $start,
                ),
                array(
                    'key' => 'wbtm_droping_point',
                    'compare' => '=',
                    'value' => $end,
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'compare' => '=',
                    'value' => $bus_id,
                ),
                array(
                    'key' => 'wbtm_status',
                    'compare' => 'IN',
                    'value' => array(1, 2),
                ),
            ),
        )
    );

    $ress = new WP_Query($argss);
    // echo '<pre>'; print_r($ress);
    if($ress->found_posts > 0) {
        while($ress->have_posts()) {
            $ress->the_post();
            $id = get_the_ID();
            $qty = get_post_meta($id, 'extra_services_type_qty_'.$service_type, true);
            $count_q += ($qty ? (int)$qty : 0);
        }
        wp_reset_postdata();
    }

    return $count_q;
}


function wbtm_extra_services_section($bus_id) {
    $start = isset($_GET['bus_start_route']) ? $_GET['bus_start_route'] : '';
    $end = isset($_GET['bus_end_route']) ? $_GET['bus_end_route'] : '';
    $j_date = isset($_GET['j_date']) ? $_GET['j_date'] : '';


    $extra_services = get_post_meta($bus_id, 'mep_events_extra_prices', true);

    if($extra_services) :
        ob_start();
    ?>
    <div class="wbtm_extra_service_wrap">
    <p class="wbtm_heading"><strong><?php echo __('Extra Service', 'Extra Service:'); ?></strong></p>
    <table class='wbtm_extra_service_table'>
        <thead>
        <tr>
            <td align="left"><?php echo __('Name:', 'bus-ticket-booking-with-seat-reservation'); ?></td>
            <td class="mage_text_center"><?php echo __('Quantity:', 'bus-ticket-booking-with-seat-reservation'); ?></td>
            <td class="mage_text_center"><?php echo __('Price:', 'bus-ticket-booking-with-seat-reservation'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?php
        $count_extra = 0;
        foreach ($extra_services as $field) {
            $total_extra_service = (int) $field['option_qty'];
            $qty_type = $field['option_qty_type'];
            // $total_sold = extra_service_qty_check($bus_id, $start, $end, $j_date, $field['option_name']);
            $total_sold = 0;

            $ext_left = ($total_extra_service - $total_sold);
            
            $actual_price=strip_tags(wc_price($field['option_price']));
            $data_price=str_replace(get_woocommerce_currency_symbol(), '', $actual_price);
            $data_price=str_replace(wc_get_price_thousand_separator(), '', $data_price);
            $data_price=str_replace(wc_get_price_decimal_separator(), '.', $data_price);
            ?>
            
            <tr data-total="0">
                <td align="Left"><?php echo $field['option_name']; ?>
                    <div class="xtra-item-left"><?php echo $ext_left; ?>
                        <?php _e('Left:', 'bus-ticket-booking-with-seat-reservation');  ?>
                    </div>
                    <!-- <input type="hidden" name='mep_event_start_date_es[]' value='<?php //echo $event_date; ?>'> -->
                </td>
                <td class="mage_text_center">
                    <?php
                    if ($ext_left > 0) {
                        if ($qty_type == 'dropdown') { ?>
                            <select name="extra_service_qty[]" id="eventpxtp_<?php echo $count_extra;
                                                                                    ?>" class='extra-qty-box' data-price='<?php echo $data_price; ?>'>
                                <?php for ($i = 0; $i <= $ext_left; $i++) { ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $field['option_name']; ?></option>
                                <?php } ?>
                            </select>
                        <?php } else { ?>
                            <div class="mage_input_group">
                                <button class="fa fa-minus qty_dec" style="font-size:9px"></button>
                                <input size="4" inputmode="numeric" type="text" class='extra-qty-box' name='extra_service_qty[]' data-price='<?php echo $data_price; ?>' value='0' min="0" max="<?php echo $ext_left; ?>">
                                <button class="fa fa-plus qty_inc" style="font-size:9px"></button>
                            </div>
                    <?php }
                    } else {
                        echo __('Not Available', 'bus-ticket-booking-with-seat-reservation');
                    } ?>
                </td>
                <td class="mage_text_center">
                    <?php 
                        $user = get_current_user_id();
                        $user_roles = array();
                        if($user) {
                            $user_meta = get_userdata($user);
                            $user_roles = $user_meta->roles;
                        }

                        if ( in_array( 'bus_sales_agent', $user_roles, true ) ) {
                            echo '<input class="extra_service_per_price" type="text" name="extra_service_price[]" value="'.$field['option_price'].'" style="width: 80px"/>';
                            if ($ext_left > 0) { ?>
                                <p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0;  ?></p>
                                <input type="hidden" name='extra_service_name[]' value='<?php echo $field['option_name']; ?>'>
                            <?php }
                        } else {
                            echo wc_price($field['option_price']);
                            if ($ext_left > 0) { ?>
                                <p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0;  ?></p>
                                <input type="hidden" name='extra_service_name[]' value='<?php echo $field['option_name']; ?>'>
                                <input type="hidden" name='extra_service_price[]' value='<?php echo $field['option_price']; ?>'>
                            <?php }
                        }
                    
                        ?>
                </td>
            </tr>
            <?php
            $count_extra++;
        }
        ?>
        </tbody>
    </table>
    </div>

    <?php
    return ob_get_contents();
    endif;
}
// Extra services END


// Get bus type
function wbtm_bus_type($bus_id) {
    $type = 'Seat Plan';
    $get_bus_type = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
    if($get_bus_type) {
        switch ($get_bus_type) {
            case 'wbtm_seat_private' :
                $type = 'Private';
                break;
            case 'wbtm_seat_subscription' :
                $type = 'Subscription';
                break;
            case 'wbtm_without_seat_plan' :
                $type = 'Without plan';
                break;
            default :
                $type = 'Seat Plan';
        }
    }
    return $type;
}

// Pagination 
function wbtm_pagination($current_page, $pages)
{
    $main_link = '?post_type=wbtm_bus&page=passenger_list';
    $bus_id = (isset($_GET['bus_id']) ? $_GET['bus_id'] : null);
    if ($bus_id) {
        $bus_id = explode('-', $bus_id);
        $bus_id = $bus_id[0];

        $main_link .= '&bus_id=' . $bus_id;
    }

    $j_date = (isset($_GET['j_date']) ? $_GET['j_date'] : null);
    if ($j_date) {
        $main_link .= '&j_date=' . $j_date;
    }

    // The "back" link
    $prevlink = ($current_page > 1) ? '<a class="mage_paginate_link" href="' . $main_link . '&paged=1" title="First page">&laquo;</a> <a class="mage_paginate_link" href="' . $main_link . '&paged=' . ($current_page - 1) . '" title="Previous page">&lsaquo;</a>' : '<span class="disabled">&laquo;</span> <span class="disabled">&lsaquo;</span>';

    // The "forward" link
    $nextlink = ($current_page < $pages) ? '<a class="mage_paginate_link" href="' . $main_link . '&paged=' . ($current_page + 1) . '" title="Next page">&rsaquo;</a> <a class="mage_paginate_link" href="' . $main_link . '&paged=' . $pages . '" title="Last page">&raquo;</a>' : '<span class="disabled">&rsaquo;</span> <span class="disabled">&raquo;</span>';

    // Display the paging information
    $output = '<div class="mage-pagination"><p>' . $prevlink . ' Page ' . $current_page . ' of ' . $pages . ' ' . $nextlink . ' </p></div>';
    return $output;
}

// Single page bus show operation
function mage_single_bus_show($id, $start, $end, $j_date, $bus_bp_array, $has_bus = false) {
    global $wbtmmain;

    $bus_next_stops_array = get_post_meta($id, 'wbtm_bus_next_stops', true) ? get_post_meta($id, 'wbtm_bus_next_stops', true) : [];
    $bus_next_stops_array = maybe_unserialize($bus_next_stops_array);

    // Intermidiate Route
    $o_1 = mage_bus_end_has_prev($start, $end, $bus_bp_array);
    $o_2 = mage_bus_start_has_next($start, $end, $bus_next_stops_array);

    // if ($o_1 && $o_2) {
    //     return;
    // }
    // Intermidiate Route END

    // Buffer Time Calculation
    $bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
    $is_buffer = $wbtmmain->wbtm_buffer_time_check($bp_time, date('Y-m-d', strtotime($j_date)));
    // Buffer Time Calculation END

    if ($is_buffer == 'yes') {
        // Operational on day
        $is_on_date = false;
        $bus_on_dates = array();
        $bus_on_date = get_post_meta($id, 'wbtm_bus_on_dates', true);
        if( $bus_on_date != null ) {
            $bus_on_dates = explode( ', ', $bus_on_date );
            $is_on_date = true;
        }

        if( $is_on_date ) {
            if( in_array( $j_date, $bus_on_dates ) ) {
                $has_bus = true;
            }
        } else {

            // Offday schedule check
            // $bus_stops_times = get_post_meta($id, 'wbtm_bus_bp_stops', true);
            $bus_offday_schedules = get_post_meta($id, 'wbtm_offday_schedule', true);
            
            // Get Bus Start Time
            $start_time = '';
            foreach($bus_bp_array as $stop) {
                if($stop['wbtm_bus_bp_stops_name'] == $start) {
                    $start_time = $stop['wbtm_bus_bp_start_time'];
                    break;
                }
            }

            $start_time = mage_time_24_to_12($start_time); // Time convert 24 to 12

            $offday_current_bus = false;
            if(!empty($bus_offday_schedules)) {
                $s_datetime = new DateTime( $j_date.' '.$start_time );

                foreach($bus_offday_schedules as $item) {

                    $c_iterate_date_from = $item['from_date'];
                    $c_iterate_datetime_from = new DateTime( $c_iterate_date_from.' '.$item['from_time'] );

                    $c_iterate_date_to = $item['to_date'];
                    $c_iterate_datetime_to = new DateTime( $c_iterate_date_to.' '.$item['to_time'] );

                    if( $s_datetime >= $c_iterate_datetime_from && $s_datetime <= $c_iterate_datetime_to ) {
                        $offday_current_bus = true;
                        break;
                    }
                }
            }

            // Check Offday and date
            if(!$offday_current_bus && !mage_check_search_day_off($id, $j_date)) {
                $has_bus = true;
            }
        }

    }

    return $has_bus;
}