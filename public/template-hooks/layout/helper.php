<?php
function mage_bus_isset($parameter)
{
    return isset($_GET[$parameter]) ? strip_tags($_GET[$parameter]) : false;
}

function mage_bus_translate($parameter)
{
    return isset($_GET[$parameter]) ? strip_tags($_GET[$parameter]) : false;
}

function mage_bus_text($text)
{
    _e($text, 'bus-ticket-booking-with-seat-reservation');
}

function mage_bus_label($var, $text, $is_return = false)
{
    global $wbtmmain;
    if ($is_return) {
        return $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
    } else {
        echo $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
    }
}

// check search day is off?
function mage_check_search_day_off($id, $j_date, $return = false)
{

    $db_day_prefix = 'offday_';
    if ($j_date) {
        $same_bus_return_setting_global = mage_bus_setting_value('same_bus_return_setting', 'disable');
        if ($same_bus_return_setting_global === 'enable') {
            $is_same_bus_return_allow = get_post_meta($id, 'wbtm_general_same_bus_return', true);
            $return_text = $return && $is_same_bus_return_allow === 'yes' ? '_return' : '';
        } else {
            $return_text = '';
        }
        $j_date_day = strtolower(date('D', strtotime($j_date)));
        $get_day = get_post_meta($id, $db_day_prefix . $j_date_day . $return_text, true);
        $get_day = ($get_day != null) ? strtolower($get_day) : null;

        if ($get_day == 'yes') {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

// check search day is off? (NEW)
function mage_check_search_day_off_new($id, $j_date, $return = false)
{
    $get_day = null;
    $db_day_prefix = 'offday_';
    $weekly_offday = get_post_meta($id, 'weekly_offday', true) ?: array();
    if ($j_date) {
        $same_bus_return_setting_global = mage_bus_setting_value('same_bus_return_setting', 'disable');
        if ($same_bus_return_setting_global === 'enable' && $return) {
            $weekly_offday = get_post_meta($id, 'weekly_offday_return', true) ?: array();
            $j_date_day = strtolower(date('N', strtotime($j_date)));
            if (in_array($j_date_day, $weekly_offday)) {
                $get_day = 'yes';
            }
        } else {
            $j_date_day = strtolower(date('N', strtotime($j_date)));
            if (in_array($j_date_day, $weekly_offday)) {
                $get_day = 'yes';
            }
        }

        if ($get_day == 'yes') {
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
function mage_convert_date_format($date, $format)
{
    $setting_format = get_option('date_format');

    if (!$date) {
        return null;
    }

    if (preg_match('/\s/', $setting_format)) {

        return date($format, strtotime($date));
    } else {
        $setting_format__dashed = str_replace('/', '-', $setting_format);
        $setting_format__dashed = str_replace('.', '-', $setting_format__dashed);

        $dash_date = str_replace('/', '-', $date);
        $dash_date = str_replace('.', '-', $dash_date);
        // echo $setting_format__dashed.'<br>';
        // echo $dash_date.'<br>';
        $date_f = DateTime::createFromFormat($setting_format__dashed, $dash_date);
        if ($date_f) {
            $res = $date_f->format($format);
            return $res;
        } else {
            return null;
        }
    }
}

// check bus on Date
function mage_bus_on_date($id, $j_date)
{
    if ($j_date) {
        $is_on_date = 'no';
        $on_dates = get_post_meta($id, 'wbtm_bus_on_dates', true);
        if ($on_dates) {
            $is_on_date = 'has';
            $on_dates = explode(', ', $on_dates);

            foreach ($on_dates as $date) {
                $date = date('Y-m-d', strtotime($date));
                if ($j_date == $date) {
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

function mage_route_list($single_bus, $start_route, $is_hide_consider = false)
{
    echo '<ul class="mage_input_select_list">';
    if ($single_bus) {
        if ($start_route) {
            $start_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
            foreach ($start_stops as $route) {
                echo '<li data-route="' . $route['wbtm_bus_bp_stops_name'] . '"><span><i class="fas fa-map-marker"></i></span>' . $route['wbtm_bus_bp_stops_name'] . '</li>';
            }
        } else {
            $end_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
            foreach ($end_stops as $route) {
                echo '<li data-route="' . $route['wbtm_bus_next_stops_name'] . '"><span><i class="fas fa-map-marker"></i></span>' . $route['wbtm_bus_next_stops_name'] . '</li>';
            }
        }
    } else {
        $routes = get_terms(array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false,
        ));
        foreach ($routes as $route) {
            if ($is_hide_consider) {
                $get_term = get_term_by('name', $route->name, 'wbtm_bus_stops');
                $is_hide_on_boarding = get_term_meta($get_term->term_id, 'wbtm_is_hide_global_boarding', true);
                if ($is_hide_on_boarding !== 'yes') {
                    echo '<li data-route="' . $route->name . '"><span class="fa fa-map-marker"></span>' . $route->name . '</li>';
                }
            } else {
                echo '<li data-route="' . $route->name . '"><span class="fa fa-map-marker"></span>' . $route->name . '</li>';
            }
        }
    }
    echo '</ul>';
}

function mage_search_bus_query($return, $start = false, $end = false)
{
    if (!$start) {
        $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    }
    if (!$end) {
        $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    }
    $args = array(
        'post_type' => array('wbtm_bus'),
        // 'p' => 2622, // TEST
        'posts_per_page' => -1,
        'order' => 'ASC',
        'orderby' => 'meta_value',
        // 'meta_key' => 'wbtm_bus_start_time',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_bus_bp_stops',
                    'value' => $start,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'wbtm_bus_bp_stops_return',
                    'value' => $start,
                    'compare' => 'LIKE',
                )
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'wbtm_bus_next_stops',
                    'value' => $end,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'wbtm_bus_next_stops_return',
                    'value' => $end,
                    'compare' => 'LIKE',
                )
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

    if(apply_filters('wbtm_specific_bus_in_search_query', array())) {
        $args['post__in'] = apply_filters('wbtm_specific_bus_in_search_query', array());
    }

    // echo '<pre>'; print_r($args); die;

    return $args;
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
function mage_bus_end_has_prev($start, $end, $boarding_array)
{
    $s = $e = '';
    $strict = 2;
    if ($end && $start && is_array($boarding_array) && !empty($boarding_array)) {

        $s = $start;
        $e = $end;

        $rearrange_array = array_column($boarding_array, 'wbtm_bus_bp_stops_name');
        $start_pos = array_search($s, $rearrange_array);
        $end_pos = array_search($e, $rearrange_array);

        if ($end_pos === 0) {
            $strict = 3;
        }

        if ($end_pos == false && is_bool($end_pos)) {
            return false;
        } else {
            if ($end_pos > $start_pos && !is_bool($start_pos)) {
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
function mage_bus_start_has_next($start, $end, $dropping_array)
{
    $s = $e = '';
    $strict = 2;
    $strict2 = 2;
    if ($end && $start && is_array($dropping_array) && !empty($dropping_array)) {

        $s = $start;
        $e = $end;

        $rearrange_array = array_column($dropping_array, 'wbtm_bus_next_stops_name');
        $start_pos = array_search($s, $rearrange_array);
        $end_pos = array_search($e, $rearrange_array);
        // return $end_pos.' '.$start_pos;
        if ($end_pos === 0) {
            $strict = 3;
        }
        // if($start_pos === 0) {
        //     $strict2 = 3;
        // }

        if ($end_pos == false && is_bool($end_pos)) {
            return false;
        } else {
            if ($end_pos > $start_pos && !is_bool($start_pos)) {
                return false; // Ok
            } else {
                return true;
            }
        }
    }

    return true; // Not ok
}

function mage_bus_title()
{
?>
    <div class="mage_flex_mediumRadiusTop mage_bus_list_title ">
        <div class="mage_bus_img flexCenter">
            <h6><?php mage_bus_label('wbtm_image_text', __('Image', 'bus-ticket-booking-with-seat-reservation'));
                ?></h6>
        </div>
        <div class="mage_bus_info flexEqual flexCenter">
            <div class="flexEqual">
                <h6><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Name', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
                <h6 class="mage_hidden_xxs"><?php mage_bus_label('wbtm_schedule_text', __('Schedule', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
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

function mage_get_bus_seat_plan_type()
{
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
function mage_bus_off_date_check($return)
{
    $start_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_start', true));
    $end_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_end', true));
    $date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));

    return (($start_date <= $date) && ($end_date >= $date)) ? false : true;
}

//bus off date check
function mage_bus_off_day_check($return)
{
    $current_day = 'offday_' . strtolower(date('D', strtotime($return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date')))));
    return get_post_meta(get_the_id(), $current_day, true) == 'yes' ? false : true;
}

//bus setting on date
function mage_bus_on_date_setting_check($return)
{
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
function mage_buffer_time_check($return)
{
    $date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));
    $buffer_time = mage_bus_setting_value('bus_buffer_time', 0);
    $start_time = strtotime($date . ' ' . date('H:i:s', strtotime(mage_bus_time($return, false))));
    $current_time = strtotime(current_time('Y-m-d H:i:s'));
    $dif = round(($start_time - $current_time) / 3600, 1);
    return ($dif >= $buffer_time) ? true : false;
}

//return bus time
function mage_bus_time($return, $dropping)
{
    if ($dropping) {
        $start = mage_bus_isset($return ? 'bus_start_route' : 'bus_end_route');
    } else {
        $start = mage_bus_isset($return ? 'bus_end_route' : 'bus_start_route');
    }

    $determine_route = mage_determine_route(get_the_id(), $return);
    if ($determine_route == 'wbtm_bus_bp_stops') {
        $meta_key = $dropping ? 'wbtm_bus_next_stops' : 'wbtm_bus_bp_stops';
    } else {
        $meta_key = $dropping ? 'wbtm_bus_next_stops_return' : 'wbtm_bus_bp_stops_return';
    }

    $return = false;
    $array_key = $dropping ? 'wbtm_bus_next_stops_name' : 'wbtm_bus_bp_stops_name';
    $array_value = $dropping ? 'wbtm_bus_next_end_time' : 'wbtm_bus_bp_start_time';
    $array = maybe_unserialize(get_post_meta(get_the_id(), $meta_key, true));
    if($array) {
        foreach ($array as $key => $val) {
            if ($val[$array_key] == $start) {
                $return = $val[$array_value];
                break;
            }
        }
    }
    return $return;
}

//return setting value
function mage_bus_setting_value($key, $default = null)
{
    $settings = get_option('wbtm_bus_settings');
    $val = isset($settings[$key]) ? $settings[$key] : null;
    return $val ? $val : $default;
}

//return check bus on off
function mage_bus_run_on_date($return)
{
    if (((mage_bus_off_date_check($return) && mage_bus_off_day_check($return)) || mage_bus_on_date_setting_check($return)) && mage_buffer_time_check($return)) {
        return true;
    }
    return false;
}

//bus type return (ac/non ac)
function mage_bus_type($id = null)
{
    $bus_id = ($id ? $id : get_the_ID());
    return get_the_terms($bus_id, 'wbtm_bus_cat') ? get_the_terms($bus_id, 'wbtm_bus_cat')[0]->name : '';
}

// bus total seat
function mage_bus_total_seat()
{
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

function mage_bus_total_seat_new()
{
    $id = get_the_ID();
    $seat_type_conf = get_post_meta($id, 'wbtm_seat_type_conf', true);
    $total_seat = 0;

    if ($seat_type_conf == 'wbtm_seat_plan') {
        $seats_rows = get_post_meta($id, 'wbtm_bus_seats_info', true);
        $seat_col = get_post_meta($id, 'wbtm_seat_cols', true);

        if ($seats_rows && $seat_col) {
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
function mage_bus_available_seat($return)
{
    return mage_bus_total_seat_new() - mage_bus_sold_seat($return);
}

//sold seat return
function mage_bus_sold_seat($return)
{
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
function mage_bus_seat_price($bus_id, $start, $end, $dd, $seat_type = null, $return_price = false, $count = 0)
{
    $flag = false;

    $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));

    if (!empty($price_arr) && is_array($price_arr)) {
        foreach ($price_arr as $value) {
            if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
                $flag = true;
                break;
            }
        }
    } else {
        $flag = false;
    }

    if (!$flag) {
        $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices_return', true));
        if (!empty($price_arr) && is_array($price_arr)) {
            foreach ($price_arr as $value) {
                if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
                    $flag = true;
                    break;
                }
            }
        }

        if (!$flag) {
            return false;
        }
    }

    $return_price_data = false;
    if ($flag) {
        $seat_dd_increase = (int)get_post_meta($bus_id, 'wbtm_seat_dd_price_parcent', true);
        // $seat_dd_increase = 10;
        $dd_price_increase = ($dd && $seat_dd_increase) ? $seat_dd_increase : 0;

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
}

function mage_bus_seat_prices($bus_id, $start, $end)
{
    $flag = false;
    $price_arr = array();

    $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));

    if (!empty($price_arr) && is_array($price_arr)) {
        foreach ($price_arr as $value) {
            if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
                $flag = true;
                break;
            }
        }
    } else {
        $flag = false;
    }

    if (!$flag) {
        $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices_return', true));
        if (!empty($price_arr) && is_array($price_arr)) {
            foreach ($price_arr as $value) {
                if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
                    $flag = true;
                    break;
                }
            }
        }

        if (!$flag) {
            return false;
        }
    }

    // With Tax
    // $return_price_data = wc_price(wbtm_get_price_including_tax($bus_id, $total_fare));

    return $price_arr;
}

function mage_bus_passenger_type($return, $dd)
{
    $id = get_the_id();
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    // $price_arr = maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
    $price_arr = mage_bus_seat_prices($id, $start, $end);
    $seat_panel_settings = get_option('wbtm_bus_settings');
    $adult_label = mage_bus_setting_value('wbtm_seat_type_adult_label');
    $child_label = mage_bus_setting_value('wbtm_seat_type_child_label');
    $infant_label = mage_bus_setting_value('wbtm_seat_type_infant_label');
    $special_label = mage_bus_setting_value('wbtm_seat_type_special_label');
    if ($price_arr) {
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
                        if ($val['wbtm_bus_price'] !== '') {
                            $price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="' . $adult_label . '">' . $adult_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_child_price'] != '') {
                            $price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="' . $child_label . '">' . $child_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_infant_price'] != '') {
                            $price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="' . $infant_label . '">' . $infant_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
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
}

function mage_bus_passenger_type_admin($return, $dd)
{
    global $wbtmmain;
    $id = get_the_id();
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $price_arr = $return ? maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices_return', true)) : maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
    $seat_panel_settings = get_option('wbtm_bus_settings');
    $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
    $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
    $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
    $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
    $rdate = isset($_GET['r_date']) ? sanitize_text_field($_GET['r_date']) : date('Y-m-d');
    if (isset($_GET['j_date'])) {
        $rdate = $return ? sanitize_text_field($_GET['r_date']) : sanitize_text_field($_GET['j_date']);
    } else {
        $rdate = date('Y-m-d');
    }
    $uid = get_the_id() . $wbtmmain->wbtm_make_id($rdate);
    foreach ($price_arr as $key => $val) {
        if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
            if (mage_bus_multiple_passenger_type_check($id, $start, $end, $return)) {
                $dd_price_increase = 0;
                if ($dd) {
                    $seat_dd_increase = (int)get_post_meta($id, 'wbtm_seat_dd_price_parcent', true);
                    $dd_price_increase = $seat_dd_increase ? $seat_dd_increase : 0;
                }
            ?>
                <div class="<?php echo 'admin_' . $uid; ?> admin_passenger_type_list">
                    <ul>
                        <?php
                        if ($val['wbtm_bus_price'] > 0) {
                            $price = $val['wbtm_bus_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_price'] * $dd_price_increase / 100) : 0);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="' . $adult_label . '">' . $adult_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_child_price'] > 0) {
                            $price = $val['wbtm_bus_child_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_child_price'] * $dd_price_increase / 100) : 0);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="' . $child_label . '">' . $child_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        if ($val['wbtm_bus_infant_price'] > 0) {
                            $price = $val['wbtm_bus_infant_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100) : 0);
                            echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="' . $infant_label . '">' . $infant_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
                        }
                        ?>
                    </ul>
                </div>
        <?php
            }
        }
    }
}

function mage_bus_multiple_passenger_type_check($id, $start, $end, $return = false)
{
    $price_arr = $return ? maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices_return', true)) : maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
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
function mage_bus_in_cart($seat_name)
{
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
function mage_bus_seat_status($field_name, $return)
{
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
    $booking_id = (isset($q->posts[0]) ? $q->posts[0]->ID : null);
    // return $booking_id;
    return get_post_meta($booking_id, 'wbtm_status', true) ? get_post_meta($booking_id, 'wbtm_status', true) : 0;
}

// Get seat Booking Data
function get_seat_booking_data($seat_name, $search_start, $search_end, $all_stopages_name, $return, $bus_id = null, $start = null, $end = null, $date = null)
{
    if (!$seat_name) {
        return false;
    }
    // Return
    $data = array(
        'status' => null,
        'has_booked' => false
    );

    if (!$start) {
        $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    }

    if (!$end) {
        $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    }

    if (!$date) {
        $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    }

    $j_dates = array(mage_wp_date($date, 'Y-m-d'));

    $bus_id = $bus_id ? $bus_id : get_the_id();

    $bus_start_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points

    // If trip is midnight trip
    if (mage_bus_is_midnight_trip($bus_start_stops_arr, $start, $end)) {
        $prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
        array_push($j_dates, $prev_date);
    }

    // Seat booked show policy in search
    $seat_booked_status_default = array(1, 2);
    $seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);

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
                    'value' => $j_dates,
                    'compare' => 'IN'
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'wbtm_status',
                    'value' => $seat_booked_status,
                    'compare' => 'IN'
                ),
            )
        ),
    );
    $q = new WP_Query($args);

    //     echo $date.'<br>';
    //     echo $q->found_posts.'<br>';

    if ($q->found_posts > 0) {
        foreach ($q->posts as $post) {
            $data['status'] = null;
            $data['has_booked'] = false;

            $bid = $post->ID;
            $boarding = get_post_meta($bid, 'wbtm_boarding_point', true);
            $dropping = get_post_meta($bid, 'wbtm_droping_point', true);
            $status = get_post_meta($bid, 'wbtm_status', true);

            $get_seat_boarding_position = array_search($boarding, $all_stopages_name);
            $get_seat_droping_position = array_search($dropping, $all_stopages_name);

            $get_seat_droping_position = (is_bool($get_seat_droping_position) && !$get_seat_droping_position ? count($all_stopages_name) : $get_seat_droping_position); // Last Stopage position assign


            // echo $get_seat_boarding_position.'<br>';
            // echo $search_start.'<br>';
            // echo $get_seat_droping_position.'<br>';
            // echo $search_end.'<br>';

            if (($get_seat_boarding_position > $search_start) && ($get_seat_boarding_position >= $search_end)) {
                $data['status'] = $status;
                $data['has_booked'] = false;
            } elseif (($search_start >= $get_seat_droping_position) && ($search_end > $get_seat_droping_position)) {
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

function mage_partial_without_seat_booked_count($return = false, $bus_id = null, $start = null, $end = null, $date = null)
{
    $sold_seats = 0;
    $midnight_sold_seats = 0;
    $midnight_trip_check = false;
    $bus_id = $bus_id ? $bus_id : get_the_ID();
    if (!$date) {
        $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    }

    $j_dates = array(mage_wp_date($date, 'Y-m-d'));

    if (!$start) {
        $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    }

    if (!$end) {
        $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    }

    $bus_start_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points
    $bus_end_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true)); // $bus_id bus end points

    // If trip is midnight trip
    if (mage_bus_is_midnight_trip($bus_start_stops_arr, $start, $end)) {
        $prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
        array_push($j_dates, $prev_date);
    }

    // Seat booked show policy in search
    $seat_booked_status_default = array(1, 2);
    $seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);

    if ($bus_start_stops_arr && $bus_end_stops_arr) {
        $bus_stops = array_column($bus_start_stops_arr, 'wbtm_bus_bp_stops_name'); // remove time
        $bus_ends = array_column($bus_end_stops_arr, 'wbtm_bus_next_stops_name'); // remove time
        $bus_stops_merge = array_merge($bus_stops, $bus_ends); // Bus start and stop merge
        $bus_stops_unique = array_values(array_unique($bus_stops_merge)); // Make stops unique

        $sp = array_search($start, $bus_stops_unique); // Get search start position in all bus stops
        $ep = array_search($end, $bus_stops_unique); // Get search end position in all bus stops

        $f = mage_array_slice($bus_stops_unique, 0, $sp + 1);
        $l = mage_array_slice($bus_stops_unique, $ep, (count($bus_stops_unique) - 1));

        $where = mage_intermidiate_available_seat_condition($start, $end, $bus_stops_unique);
        // echo '<pre>';print_r($where);die;

        $args = array(
            'post_type' => 'wbtm_bus_booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'relation' => 'AND',
                    $where,
                    array(
                        'key' => 'wbtm_journey_date',
                        'value' => $j_dates,
                        'compare' => 'IN'
                    ),
                    array(
                        'key' => 'wbtm_bus_id',
                        'value' => $bus_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'wbtm_status',
                        'value' => $seat_booked_status,
                        'compare' => 'IN'
                    ),
                )
            ),
        );
        $q = new WP_Query($args);

        $sold_seats = $q->found_posts;
    }

    return $sold_seats + $midnight_sold_seats;
}

function mage_bus_is_midnight_trip($bus_start_stops_arr, $start = null, $end = null)
{
    $return = false;
    $start_point = '';
    $start_point_time = '';
    $boarding_point = '';
    $boarding_point_time = '';

    if ($bus_start_stops_arr) {
        $i = 0;
        foreach ($bus_start_stops_arr as $stops) {
            if ($i == 0) {
                $start_point = $stops['wbtm_bus_bp_stops_name']; // Start Point
                $start_point_time = $stops['wbtm_bus_bp_start_time']; // Start Point
            }

            if ($start) { // Get $start data
                if ($stops['wbtm_bus_bp_stops_name'] == $start) {
                    $boarding_point = $stops['wbtm_bus_bp_stops_name']; // Boarding Point
                    $boarding_point_time = $stops['wbtm_bus_bp_start_time']; // Boarding Point
                    break;
                }
            } else { // Get last data of Array
                if ((count($bus_start_stops_arr) - 1) == $i) {
                    $boarding_point = $stops['wbtm_bus_bp_stops_name']; // Boarding Point
                    $boarding_point_time = $stops['wbtm_bus_bp_start_time']; // Boarding Point
                    break;
                }
            }

            $i++;
        }

        // Start Time
        $start_hour = '';
        if ($start_point_time) {
            $start_hour = explode(':', $start_point_time);
            $start_hour = $start_hour ? (int)$start_hour[0] : null;
        }

        // Start Time
        $boarding_hour = '';
        if ($boarding_point_time) {
            $boarding_hour = explode(':', $boarding_point_time);
            $boarding_hour = $boarding_hour ? (int)$boarding_hour[0] : null;
        }

        // Check date is changed
        if ($start_hour && $boarding_hour) {
            if (($start_hour > $boarding_hour) || ($boarding_hour == 24)) {
                $return = true;
            }
        }
    }

    return $return;
}

function check_bus_is_return($bus_id, $boarding, $dropping, $bus_start_point_arr = null, $bus_end_point_arr = null)
{
    $is_return = false;

    $bus_start_point_arr = $bus_start_point_arr ? $bus_start_point_arr : get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
    $bus_end_point_arr = $bus_end_point_arr ? $bus_end_point_arr : get_post_meta($bus_id, 'wbtm_bus_next_stops', true);

    if ($bus_start_point_arr && $bus_end_point_arr) {
        $bus_start_point_flat = array_column($bus_start_point_arr, 'wbtm_bus_bp_stops_name');
        $bus_end_point_flat = array_column($bus_end_point_arr, 'wbtm_bus_next_stops_name');

        $all_stops = array_unique(array_merge($bus_start_point_flat, $bus_end_point_flat), SORT_REGULAR); // all stopage but unique

        $boarding_pos = array_search($boarding, $all_stops); // boarding position
        $dropping_pos = array_search($dropping, $all_stops); // dropping position

        if ($dropping_pos < $boarding_pos) {
            $is_return = true;
        }
    }

    return $is_return;
}


// Get Boarding and Dropping date (also midnigh trip)
function mage_get_bus_stops_date($bus_id, $date, $boarding, $dropping, $return = false)
{
    $return_text = $return ? 'return_' : '';
    $boarding_point_time = '';
    $dropping_point_time = '';
    $date = mage_date_format_issue($date);
    $data = array(
        'boarding' => $date,
        'boarding_time' => null,
        'dropping' => $date,
        'dropping_time' => null
    );

    // check is bus is return
    $is_same_bus_return_allow = get_post_meta($bus_id, 'wbtm_general_same_bus_return', true);
    $is_return = ($is_same_bus_return_allow == 'yes' ? check_bus_is_return($bus_id, $boarding, $dropping) : false);
    //    var_dump($is_return).'<br>';

    $bus_start_stops_arr = $is_return ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops_return', true)) : maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points
    $bus_next_stops_arr = $is_return ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops_return', true)) : maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true)); // $bus_id bus start points

    if ($bus_start_stops_arr) {
        foreach ($bus_start_stops_arr as $stop) {
            if ($boarding) { // Get $start data
                if ($stop['wbtm_bus_bp_stops_name'] == $boarding) {
                    $boarding_point_time = $stop['wbtm_bus_bp_start_time']; // Boarding Point
                    $data['boarding_time'] = mage_wp_time($stop['wbtm_bus_bp_start_time']);
                    break;
                }
            }
        }
    }

    if ($bus_next_stops_arr) {
        foreach ($bus_next_stops_arr as $stop) {
            if ($dropping) { // Get $start data
                if ($stop['wbtm_bus_next_stops_name'] == $dropping) {
                    $dropping_point_time = $stop['wbtm_bus_next_end_time']; // Dropping Point
                    $data['dropping_time'] = mage_wp_time($stop['wbtm_bus_next_end_time']);
                    break;
                }
            }
        }
    }

    $boarding_hour = '';
    if ($boarding_point_time) {
        $boarding_hour = explode(':', $boarding_point_time);
        $boarding_hour = $boarding_hour ? (int)$boarding_hour[0] : null;
    }

    $dropping_hour = '';
    if ($dropping_point_time) {
        $dropping_hour = explode(':', $dropping_point_time);
        $dropping_hour = $dropping_hour ? (int)$dropping_hour[0] : null;
    }

    // Check date is changed
    $wbtm_route_summary = maybe_unserialize(get_post_meta($bus_id, $return_text . 'wbtm_route_summary', true));
    $get_travel_day = 0;
    if ($wbtm_route_summary) {
        foreach ($wbtm_route_summary as $td) {
            if (isset($td['boarding']) && isset($td['dropping']) && isset($td['travel_day'])) {
                if ($td['boarding'] === $boarding && $td['dropping'] === $dropping) {
                    $get_travel_day = $td['travel_day'];
                    break;
                }
            }
            
        }
    }

    if ($boarding_hour && $dropping_hour) {
        // if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
        //     $data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
        // }

        if ($get_travel_day == 1) {
            if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
                $data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
            } else {
                $data['dropping'] = date('Y-m-d', strtotime($date));
            }
        } elseif ($get_travel_day == 2) {
            $data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
        } elseif ($get_travel_day == 3) {
            $data['dropping'] = date('Y-m-d', strtotime('+2 day', strtotime($date)));
        } elseif ($get_travel_day == 4) {
            $data['dropping'] = date('Y-m-d', strtotime('+3 day', strtotime($date)));
        } else {
            if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
                $data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
            }
        }
    }

    // Get boarding and dropping datetime difference
    $boarding_dateTime = new DateTime($data['boarding'] . ' ' . $data['boarding_time']);
    $dropping_dateTime = new DateTime($data['dropping'] . ' ' . $data['dropping_time']);
    $interval = $boarding_dateTime->diff($dropping_dateTime);

    $data['interval'] = $interval->format('%a days %h hours %i minutes');

    return $data;
}

function mage_date_format_issue($date)
{
    $date_format = get_option('date_format');

    if ($date) {
        if ($date_format == 'm/d/Y') {
            $date = str_replace('-', '/', $date);
        }

        if ($date_format == 'd/m/Y') {
            $date = str_replace('/', '-', $date);
        }
    }
    return $date;
}

// Mage array slice
function mage_array_slice($arr, $s, $e = null): array
{
    return $arr ? array_slice($arr, $s, $e) : array();
}

// Get bus stops position in all bus stops
function mage_intermidiate_available_seat_condition($start, $end, $all_stops)
{
    $where = array();
    $sp = array_search($start, $all_stops);
    $ep = array_search($end, $all_stops);

    if ($sp == 0) {

        $where = array(
            array(
                'key' => 'wbtm_boarding_point',
                'value' => mage_array_slice($all_stops, 0, $ep),
                'compare' => 'IN'
            ),
            array(
                'key' => 'wbtm_droping_point',
                'value' => mage_array_slice($all_stops, $sp),
                'compare' => 'IN'
            ),
        );
    } elseif ($ep == (count($all_stops) - 1)) {

        $where = array(
            array(
                'key' => 'wbtm_boarding_point',
                'value' => mage_array_slice($all_stops, 0, $ep),
                'compare' => 'IN'
            ),
            array(
                'key' => 'wbtm_droping_point',
                'value' => mage_array_slice($all_stops, $sp + 1),
                'compare' => 'IN'
            ),
        );
    } else {

        $where = array(
            array(
                'key' => 'wbtm_boarding_point',
                'value' => mage_array_slice($all_stops, 0, $ep),
                'compare' => 'IN'
            ),
            array(
                'key' => 'wbtm_droping_point',
                'value' => mage_array_slice($all_stops, $ep),
                'compare' => 'IN'
            ),
        );
    }

    return $where;
}

//find seat Droping Point
function mage_bus_seat_droping_point($field_name, $point, $return)
{
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
    $booking_id = (isset($q->posts[0]) ? $q->posts[0]->ID : null);
    return get_post_meta($booking_id, $point, true) ? get_post_meta($booking_id, $point, true) : 0;
}

// Return Array
function mage_bus_get_all_stopages($post_id)
{
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

function mage_bus_get_option($option, $section, $default = '')
{
    $options = get_option($section);

    if (isset($options[$option])) {
        return $options[$option];
    }

    return $default;
}

// Check Cart has Oppsite route
// Note: $return_discount === 2
function mage_cart_has_opposite_route($current_start, $current_stop, $current_j_date, $return = false, $current_r_date = null)
{
    global $woocommerce;

    $data = 0;
    $items = $woocommerce->cart->get_cart();
    if (count($items) > 0) {

        $wbtm_start_stops_current = $current_start;
        $wbtm_end_stops_current = $current_stop;
        $journey_date_current = $current_j_date;


        // foreach( $items as $item => $value ) {
        //     if( ($value['is_return'] == 1) ) {
        //         return 0;
        //     }
        // }

        if ($journey_date_current) {
            $journey_date_current = new DateTime($journey_date_current);
        }

        if ($current_r_date) {
            $current_r_date = new DateTime($current_r_date);
        }


        foreach ($items as $item => $value) {

            if (array_key_exists('wbtm_journey_date', $value) && $value['wbtm_journey_date']) {
                $cart_j_date = new DateTime($value['wbtm_journey_date']);
            }

            if ($return) { // Return
                if (($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops'])) {
                    $data = 1;
                    break;
                } else {
                    $data = 0;
                }
            } else { // Not return
                if (array_key_exists('wbtm_end_stops', $value) && ($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops'])) {
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

function mage_cart_has_opposite_route_P()
{
    global $woocommerce;

    $items = $woocommerce->cart->get_cart();
    if (count($items) > 0) {

        foreach ($items as $item => $value) {

            foreach ($items as $k => $v) {
                if (count($v['wbtm_passenger_info']) > 1) {
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
function mage_time_24_to_12($time, $full = true)
{
    $t = '';
    if ($time && strpos($time, ':') !== false) {
        if (strpos($time, 'AM') || strpos($time, 'am') || strpos($time, 'PM') || strpos($time, 'pm')) {
            $time = trim(str_replace('AM', '', $time));
            $time = trim(str_replace('am', '', $time));
            $time = trim(str_replace('PM', '', $time));
            $time = trim(str_replace('pm', '', $time));
        }

        $t = explode(':', $time);
        $h = $t[0];
        $m = $t[1];
        $tm = ($h < 12) ? 'am' : 'pm';

        if (!$full) {
            return $tm;
        } else {
            if ($h > 12) {
                $tt = $h - 12;
                $t = $tt . ':' . $m . ' ' . $tm;
            } elseif ($h == '00' || $h == '24') {
                $t = '00' . ':' . $m . ' am';
            } else {
                $t = $h . ':' . $m . ' ' . $tm;
            }
        }
        // $t = $tm;
    }

    return $t;
}

// Convert to wp time format
function mage_wp_time($time)
{
    $wp_time_format = get_option('time_format');

    if ($time && $wp_time_format) {
        $time = date($wp_time_format, strtotime($time));
    }

    return $time;
}

function mage_wp_date($date, $format = false)
{
    $wp_date_format = get_option('date_format');

    $date = mage_date_format_issue($date);

    if ($date && $format) {
        $date = date($format, strtotime($date));

        return $date;
    }

    if ($date && $wp_date_format) {
        $date = date($wp_date_format, strtotime($date));
    }

    return $date;
}

// function mage_wp_date($date,$type = 'date',$u_format = null){
//     $date_format        = get_option( 'date_format' );
//     $time_format        = get_option( 'time_format' );
//     $wpdatesettings     = $date_format.'  '.$time_format; 
//     $timezone           = wp_timezone_string();
//     $timestamp          = strtotime( $date . ' '. $timezone);

//     if($u_format){
//         return date( $u_format, $timestamp );    
//     }
//     if($type == 'date'){
//         return wp_date( $date_format, $timestamp );    
//     }
//     if($type == 'date-time'){
//         return wp_date( $wpdatesettings, $timestamp );    
//     }
//     if($type == 'date-text'){

//         return wp_date( $date_format, $timestamp );    
//     }

//     if($type == 'date-time-text'){
//         return wp_date( $wpdatesettings, $timestamp, wp_timezone() );    
//     }
//     if($type == 'time'){
//         return wp_date( $time_format, $timestamp, wp_timezone());
//     }

//     if($type == 'day'){
//         return wp_date( 'd', $timestamp );    
//     }
//     if($type == 'Dday'){
//         return wp_date( 'D', $timestamp );    
//     }
//     if($type == 'month'){
//         return wp_date( 'M', $timestamp );    
//     }
// }

// Extra services qty check
function extra_service_qty_check($bus_id, $start, $end, $j_date, $service_type)
{

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
    if ($ress->found_posts > 0) {
        while ($ress->have_posts()) {
            $ress->the_post();
            $id = get_the_ID();
            $qty = get_post_meta($id, 'extra_services_type_qty_' . $service_type, true);
            $count_q += ($qty ? (int)$qty : 0);
        }
        wp_reset_postdata();
    }

    return $count_q;
}


function wbtm_extra_services_section($bus_id)
{
    $start = isset($_GET['bus_start_route']) ? $_GET['bus_start_route'] : '';
    $end = isset($_GET['bus_end_route']) ? $_GET['bus_end_route'] : '';
    $j_date = isset($_GET['j_date']) ? $_GET['j_date'] : '';


    $extra_services = get_post_meta($bus_id, 'mep_events_extra_prices', true);

    if ($extra_services) :
        // ob_start();
        ?>
        <div class="wbtm_extra_service_wrap">
            <p class="wbtm_heading"><strong><?php echo __('Extra Service', 'Extra Service:'); ?></strong></p>
            <table class='wbtm_extra_service_table ra_extra_service_table'>
                <thead>
                    <tr>
                        <td align="left"><?php echo __('Name', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
                        <td class="mage_text_center"><?php echo __('Quantity', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
                        <td class="mage_text_center"><?php echo __('Price', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count_extra = 0;
                    foreach ($extra_services as $field) {
                        $total_extra_service = (int)$field['option_qty'];
                        $qty_type = $field['option_qty_type'];
                        // $total_sold = extra_service_qty_check($bus_id, $start, $end, $j_date, $field['option_name']);
                        $total_sold = 0;

                        $ext_left = ($total_extra_service - $total_sold);
                        // echo '<pre>';print_r($field);
                        if (!isset($field['option_name']) || !isset($field['option_price'])) {
                            continue;
                        }
                        $actual_price = strip_tags(wc_price($field['option_price']));
                        $data_price = str_replace(get_woocommerce_currency_symbol(), '', $actual_price);
                        $data_price = str_replace(wc_get_price_thousand_separator(), '', $data_price);
                        $data_price = str_replace(wc_get_price_decimal_separator(), '.', $data_price);
                    ?>

                        <tr data-total="0">
                            <td align="Left"><?php echo $field['option_name']; ?>
                                <div class="xtra-item-left"><?php echo $ext_left; ?>
                                    <?php _e('Left:', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </div>
                                <!-- <input type="hidden" name='mep_event_start_date_es[]' value='<?php //echo $event_date; 
                                                                                                    ?>'> -->
                            </td>
                            <td class="mage_text_center">
                                <?php
                                if ($ext_left > 0) {
                                    if ($qty_type == 'dropdown') { ?>
                                        <select name="extra_service_qty[]" id="eventpxtp_<?php echo $count_extra;
                                                                                            ?>" class='extra-qty-box' data-price='<?php echo $data_price; ?>'>
                                            <?php for ($i = 0; $i <= $ext_left; $i++) { ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?><?php echo $field['option_name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    <?php } else { ?>
                                        <div class="mage_input_group">
                                            <button class="fa fa-minus qty_dec" style="font-size:9px"></button>
                                            <input size="4" inputmode="numeric" type="text" class='extra-qty-box' name='extra_service_qty[]' data-price='<?php echo wbtm_get_price_including_tax($bus_id, $data_price); ?>' value='0' min="0" max="<?php echo $ext_left; ?>">
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
                                if ($user) {
                                    $user_meta = get_userdata($user);
                                    $user_roles = $user_meta->roles;
                                }

                                if (in_array('bus_sales_agent', $user_roles, true)) {
                                    echo '<input class="extra_service_per_price" type="text" name="extra_service_price[]" value="' . wbtm_get_price_including_tax($bus_id, $field['option_price']) . '" style="width: 80px"/>';
                                    if ($ext_left > 0) { ?>
                                        <p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0; ?></p>
                                        <input type="hidden" name='extra_service_name[]' value='<?php echo $field['option_name']; ?>'>
                                    <?php }
                                } else {
                                    echo wc_price(wbtm_get_price_including_tax($bus_id, $field['option_price']));
                                    if ($ext_left > 0) { ?>
                                        <p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0; ?></p>
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
    // return ob_get_contents();
    endif;
}

// Extra services END


// Get bus type
function wbtm_bus_type($bus_id)
{
    $type = 'Seat Plan';
    $get_bus_type = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
    if ($get_bus_type) {
        switch ($get_bus_type) {
            case 'wbtm_seat_private':
                $type = 'Private';
                break;
            case 'wbtm_seat_subscription':
                $type = 'Subscription';
                break;
            case 'wbtm_without_seat_plan':
                $type = 'Without plan';
                break;
            default:
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
function mage_single_bus_show($id, $start, $end, $j_date, $bus_bp_array, $return = false)
{
    global $wbtmmain;
    $has_bus = false;
    $bus_next_stops_array = get_post_meta($id, 'wbtm_bus_next_stops', true) ? get_post_meta($id, 'wbtm_bus_next_stops', true) : [];
    $bus_next_stops_array = maybe_unserialize($bus_next_stops_array);

    // Intermidiate Route
    $o_1 = mage_bus_end_has_prev($start, $end, $bus_bp_array);
    $o_2 = mage_bus_start_has_next($start, $end, $bus_next_stops_array);

    if ($o_1 && $o_2) {
        return;
    }
    // Intermidiate Route END

    // Buffer Time Calculation
    $bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
    $is_buffer = $wbtmmain->wbtm_buffer_time_check($bp_time, date('Y-m-d', strtotime($j_date)));
    // Buffer Time Calculation END

    if ($is_buffer == 'yes') {
        // Operational on day
        $is_on_date = false;
        $bus_on_dates = array();
        // $bus_on_date = get_post_meta($id, 'wbtm_bus_on_dates', true);
        $bus_on_date = mage_determine_ondate($id, $return, $start, $end);
        if ($bus_on_date != null) {
            $bus_on_dates = explode(', ', $bus_on_date);
            $is_on_date = true;
        }

        if ($is_on_date) {
            if (in_array($j_date, $bus_on_dates)) {
                $has_bus = true;
            }
        } else {

            // Offday schedule check
            // $bus_stops_times = get_post_meta($id, 'wbtm_bus_bp_stops', true);
            $bus_offday_schedules = get_post_meta($id, 'wbtm_offday_schedule', true);

            // Get Bus Start Time
            $start_time = '';
            foreach ($bus_bp_array as $stop) {
                if ($stop['wbtm_bus_bp_stops_name'] == $start) {
                    $start_time = $stop['wbtm_bus_bp_start_time'];
                    break;
                }
            }

            $start_time = mage_time_24_to_12($start_time); // Time convert 24 to 12

            $offday_current_bus = false;
            if (!empty($bus_offday_schedules)) {
                $s_datetime = new DateTime($j_date . ' ' . $start_time);

                foreach ($bus_offday_schedules as $item) {

                    $c_iterate_date_from = $item['from_date'];
                    $c_iterate_datetime_from = new DateTime($c_iterate_date_from . ' ' . (isset($item['from_time'])?$item['from_time']:''));

                    $c_iterate_date_to = $item['to_date'];
                    $c_iterate_datetime_to = new DateTime($c_iterate_date_to . ' ' . (isset($item['to_time'])?$item['to_time']:''));

                    if ($s_datetime >= $c_iterate_datetime_from && $s_datetime <= $c_iterate_datetime_to) {
                        $offday_current_bus = true;
                        break;
                    }
                }
            }

            // Check Offday and date
            if (!$offday_current_bus && !mage_check_search_day_off_new($id, $j_date)) {
                $has_bus = true;
            }
        }
    }

    return $has_bus;
}

// Is one way route or Return route
function mage_determine_direction($id, $is_return, $start = null, $end = null)
{
    $route_key = 'one'; // Default value

    if (!$start) {
        $start = $is_return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    }

    if (!$end) {
        $end = $is_return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    }

    $one_way_start = get_post_meta($id, 'wbtm_bus_bp_stops', true);
    $one_way_end = get_post_meta($id, 'wbtm_bus_next_stops', true);

    $return_start = get_post_meta($id, 'wbtm_bus_bp_stops_return', true);
    $return_end = get_post_meta($id, 'wbtm_bus_next_stops_return', true);

    if (!empty($one_way_start) && !empty($one_way_end)) {
        $one_way_start = array_column(maybe_unserialize($one_way_start), 'wbtm_bus_bp_stops_name');
        $one_way_end = array_column(maybe_unserialize($one_way_end), 'wbtm_bus_next_stops_name');
        $one_s = array_search($start, $one_way_start);
        $one_e = array_search($end, $one_way_end);

        if (($one_s == $one_e) && in_array($start, $one_way_start) && in_array($end, $one_way_end)) {
            $route_key = 'one';
        } else {
            if ($return_start && $return_end) {
                $return_start = array_column(maybe_unserialize($return_start), 'wbtm_bus_bp_stops_name');
                $return_end = array_column(maybe_unserialize($return_end), 'wbtm_bus_next_stops_name');
                // $return_s = array_search($start, $return_start);
                // $return_e = array_search($end, $return_end);

                // if (($return_s == $return_e) && in_array($start, $return_start) && in_array($end, $return_end)) {
                //     $route_key = 'return';
                // }
                if (in_array($start, $return_start) && in_array($end, $return_end)) {
                    $route_key = 'return';
                }
            }
        }
    }

    return $route_key;
}

// Is one way route or Return route
function mage_determine_route($id, $is_return, $start = null, $end = null)
{

    $direction = mage_determine_direction($id, $is_return, $start, $end);

    if ($direction == 'return') {
        $route_key = 'wbtm_bus_bp_stops_return';
    } else {
        $route_key = 'wbtm_bus_bp_stops';
    }

    return $route_key;
}


// Get Pickup Point
function mage_determine_pickuppoint($id, $is_return, $start, $end)
{
    $start_id = mage_get_term_by_name($start, 'wbtm_bus_stops') ? mage_get_term_by_name($start, 'wbtm_bus_stops')->term_id : null;
    if ($start_id) {
        $direction = mage_determine_direction($id, $is_return, $start, $end);
        if ($direction == 'return') {
            $pickup_point = get_post_meta($id, 'wbtm_selected_pickpoint_return_name_' . $start_id, true);
        } else {
            $pickup_point = get_post_meta($id, 'wbtm_selected_pickpoint_name_' . $start_id, true);
        }
    } else {
        $pickup_point = array();
    }

    return $pickup_point;
}

// Get ondates
function mage_determine_ondate($id, $is_return, $start, $end)
{

    $direction = mage_determine_direction($id, $is_return, $start, $end);

    if ($direction == 'return') {
        $ondates = get_post_meta($id, 'wbtm_bus_on_dates_return', true);
    } else {
        $ondates = get_post_meta($id, 'wbtm_bus_on_dates', true);
    }

    return $ondates;
}

// Get offdates
function mage_determine_offdate($id, $is_return, $start, $end)
{

    $direction = mage_determine_direction($id, $is_return, $start, $end);

    if ($direction == 'return') {
        $offdates = get_post_meta($id, 'wbtm_offday_schedule_return', true);
    } else {
        $offdates = get_post_meta($id, 'wbtm_offday_schedule', true);
    }

    return $offdates;
}

// Partial seat booked count
function mage_partial_seat_booked_count($return, $seat = null, $bus_id = null, $start = null, $end = null, $date = null)
{
    $partial_seat_booked = 0;
    // return $partial_seat_booked;

    $bus_id = $bus_id ? $bus_id : get_the_ID();

    $bus_type = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
    if ($bus_type == 'wbtm_without_seat_plan') {
        return mage_partial_without_seat_booked_count($return, $bus_id, $start, $end, $date); // For without seat plan
    }

    if (!$start) {
        $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    }

    if (!$end) {
        $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    }

    if (!$date) {
        $date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
    }
    $date = mage_wp_date($date, 'Y-m-d');

    $all_stopages_name = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
    $all_stopages_name = maybe_unserialize($all_stopages_name);


    // If trip is midnight trip
    // if(mage_bus_is_midnight_trip($all_stopages_name, $start, $end)) {
    //     $date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
    // }

    $all_stopages_name = array_column($all_stopages_name, 'wbtm_bus_bp_stops_name');

    $partial_route_condition = false; // init value
    $get_search_start_position = array_search($start, $all_stopages_name);
    $get_search_droping_position = array_search($end, $all_stopages_name);

    $get_search_droping_position = (is_bool($get_search_droping_position) && !$get_search_droping_position ? count($all_stopages_name) : $get_search_droping_position); // Last Stopage position assign

    if ($seat) {

        $partial_seat_booked = get_seat_booking_data($seat, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, $bus_id, $start, $end, $date);
    } else {

        $lower_seats = get_post_meta($bus_id, 'wbtm_bus_seats_info', true);
        $upper_seats = get_post_meta($bus_id, 'wbtm_bus_seats_info_dd', true);

        $lower_seat_booked_count = 0;
        $upper_seat_booked_count = 0;

        if ($lower_seats) {
            foreach ($lower_seats as $f_seat) {
                foreach ($f_seat as $key => $val) {
                    if ($val != '') {
                        $get_booking_data = get_seat_booking_data($val, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, $bus_id, $start, $end, $date);
                        if ($get_booking_data['has_booked']) {
                            $lower_seat_booked_count++;
                        }
                    }
                }
            }
        }

        if ($upper_seats) {
            foreach ($upper_seats as $f_seat) {
                foreach ($f_seat as $key => $val) {
                    if ($val != '') {
                        $get_booking_data = get_seat_booking_data($val, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, null, null, null, $date);
                        if ($get_booking_data['has_booked']) {
                            $upper_seat_booked_count++;
                        }
                    }
                }
            }
        }

        $partial_seat_booked = $lower_seat_booked_count + $upper_seat_booked_count;
    }

    return $partial_seat_booked;
}

// Get any term object by term_id
function mage_get_term($term_id, $taxonomy)
{
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ));
    $return_term = null;
    if ($terms) {
        foreach ($terms as $s) {
            if ($s->term_id == $term_id) {
                $return_term = $s;
                break;
            }
        }
    }

    return $return_term;
}

// Get any term object by term name
function mage_get_term_by_name($term_name, $taxonomy)
{
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ));
    $return_term = null;
    if ($terms) {
        foreach ($terms as $s) {
            if ($s->name == $term_name) {
                $return_term = $s;
                break;
            }
        }
    }

    return $return_term;
}

// Get wp option
function wbtm_search_target_page()
{
    $get_settings = get_option('wbtm_bus_settings');
    $get_page = isset($get_settings['search_target_page']) ? $get_settings['search_target_page'] : 'bus-search-list';

    return $get_page;
}

// Get Extra Price
function extra_price($extra_services)
{
    $price = 0;
    if (is_array($extra_services)) {
        foreach ($extra_services as $service) {
            $price += $service['price'] * $service['qty'];
        }
    }

    return $price;
}

// Disabeled route bus remove from the search result
function wbtm_removed_the_disabled_route_bus($start, $end, $bus_boarding_array, $bus_next_stops_array)
{

    $is_route_disabled = false;

    // Global Setting
    $settings = get_option('wbtm_bus_settings');
    $route_disable_switch = isset($settings['route_disable_switch']) ? $settings['route_disable_switch'] : 'off';
    if ($route_disable_switch !== 'on') {
        return false;
    }

    if (!is_array($bus_boarding_array) || !is_array($bus_next_stops_array)) {
        return false;
    }

    // Checking Boarding disable
    $boarding_index = 0;
    foreach ($bus_boarding_array as $route) {

        if ($start != $route['wbtm_bus_bp_stops_name']) {
            $boarding_index++;
            continue; // if search boarding point not matched
        }

        if (isset($route['wbtm_bus_bp_start_disable'])) {
            if ($route['wbtm_bus_bp_start_disable'] === 'yes') {
                $boarding_disable_index = $boarding_index;
            }
        }

        $boarding_index++;
    }

    // Checking Dropping disable
    if (isset($boarding_disable_index)) {
        if (isset($bus_next_stops_array[$boarding_disable_index])) {
            if ($end == $bus_next_stops_array[$boarding_disable_index]['wbtm_bus_next_stops_name']) {
                $is_route_disabled = true; // this search boarding point is disabled
            }
        }
    }

    return $is_route_disabled;
}

// Get Admin Route summary
function admin_route_summary($post, $wbbm_bus_bp, $wbtm_bus_next_stops, $return = false)
{
    $return_text = $return ? 'return_' : '';
    $wbtm_route_summary = maybe_unserialize(get_post_meta($post->ID, $return_text . 'wbtm_route_summary', true));
    ?>
    <div class="wbtm-route-summary-container">
        <div class="wbtm-route-summary-inner">
            <div class="wbtm-route-summary-title">
                <h3><?php _e('Route summary', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                <span><?php _e('This is the route summary according to the top route section. <br> If some trips need more than 24 hours please explicitly configure it from this summary.', 'bus-ticket-booking-with-seat-reservation') ?></span>
            </div>
            <table class="wbtm-table wbtm-table--route-summary">
                <thead>
                    <tr>
                        <th><?php _e('Sl', 'bus-ticket-booking-with-seat-reservation') ?></th>
                        <th><?php _e('Boarding', 'bus-ticket-booking-with-seat-reservation') ?></th>
                        <th><?php _e('Dropping', 'bus-ticket-booking-with-seat-reservation') ?></th>
                        <th><?php _e('Trip day', 'bus-ticket-booking-with-seat-reservation') ?></th>
                        <th><?php _e('Trip time', 'bus-ticket-booking-with-seat-reservation') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($wbbm_bus_bp) :
                        $travel_days = array(
                            '1' => __('Less than 1 day', 'bus-ticket-booking-with-seat-reservation'),
                            '2' => __('More than 1 day', 'bus-ticket-booking-with-seat-reservation'),
                            '3' => __('More than 2 days', 'bus-ticket-booking-with-seat-reservation'),
                            '4' => __('More than 3 days', 'bus-ticket-booking-with-seat-reservation'),
                        );
                        $sl = 0;
                        $i = 0;
                        foreach ($wbbm_bus_bp as $bp) :
                            $j = 0;
                            foreach ($wbtm_bus_next_stops as $dp) :
                                if ($i <= $j) :
                                    $get_stops_dates = mage_get_bus_stops_date($post->ID, date('Y-m-d'), $bp['wbtm_bus_bp_stops_name'], $dp['wbtm_bus_next_stops_name'], $return);
                    ?>
                                    <tr>
                                        <td><?php echo $sl + 1; ?></td>
                                        <td>
                                            <?php echo $bp['wbtm_bus_bp_stops_name'] ?>
                                            <input type="hidden" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][boarding]" value="<?php echo $bp['wbtm_bus_bp_stops_name'] ?>">
                                        </td>
                                        <td>
                                            <?php echo $dp['wbtm_bus_next_stops_name'] ?>
                                            <input type="hidden" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][dropping]" value="<?php echo $dp['wbtm_bus_next_stops_name'] ?>">
                                        </td>
                                        <td>
                                            <!-- Travel days loop -->
                                            <?php foreach ($travel_days as $key => $td) :
                                                $wbtm_route_day_check = '';
                                                if($wbtm_route_summary) {
                                                    $wbtm_route_day_check = (isset($wbtm_route_summary[$sl]['travel_day']) ? ($wbtm_route_summary[$sl]['travel_day'] == $key ? 'checked' : '') : ($key == 1 ? 'checked' : ''));
                                                }
                                                ?>
                                                <label for="<?php echo $return_text ?>wbtm_route_days_<?php echo $sl . $key; ?>" class="wbtm-radio-label"><input type="radio" id="<?php echo $return_text ?>wbtm_route_days_<?php echo $sl . $key; ?>" value="<?php echo $key ?>" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][travel_day]" <?php echo $wbtm_route_day_check ?>><?php echo $td; ?></label>
                                            <?php endforeach ?>
                                        </td>
                                        <td><?php echo ($wbtm_route_summary ? $get_stops_dates['interval'] : ''); ?></td>
                                    </tr>
                    <?php $sl++;
                                endif;
                                $j++;
                            endforeach;
                            $i++;
                        endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
        <button class="wbtm_route_summary_btn"><?php _e('Expand Route Summary', 'bus-ticket-booking-with-seat-reservation') ?></button>
    </div>
<?php
}


function wbtm_get_user_role($user_ID)
{
    global $wp_roles;

    $user_data = get_userdata($user_ID);
    $user_role_slug = $user_data->roles;
    $user_role_nr = 0;
    $user_role_list = '';
    foreach($user_role_slug as $user_role){
        //count user role nrs
        $user_role_nr++;
        //add comma separation of there is more then one role 
        if($user_role_nr > 1) { $user_role_list .= ", "; }
        //add role name 
        $user_role_list .= translate_user_role($wp_roles->roles[$user_role]['name']);
    }

    //return user role list
    return $user_role_list;
}