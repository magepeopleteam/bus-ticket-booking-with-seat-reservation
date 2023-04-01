<?php
$mage_bus_total_seats_availabel = 0;
function mage_bus_search_page()
{
    global $wbtmmain;
    $global_target = $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec') ? get_post_field('post_name', $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec')) : 'bus-search-list';
    echo '<div class="mage">';
    mage_bus_search_form($global_target);
    echo "<div id='wbtm_search_result_section' style='margin-top:20px'>";
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {

        mage_next_date_suggestion(false, false, $global_target);

        echo '<div class="wbtm_search_part">';
        mage_bus_route_title(false);
        mage_bus_search_list(false);
        echo '</div>';
    }
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && (isset($_GET['r_date']))) {
        if ($_GET['r_date']) {
            $return_trip_text = mage_bus_label('wbtm_return_trip_text_heading', __('Return Trip', 'bus-ticket-booking-with-seat-reservation'), true) . ':';
            echo '<p style="margin:40px 0 7px;color: #587275;text-decoration: underline;font-family: sans-serif;text-align:center;font-size: 1.8em!important;">' . $return_trip_text . '</p>';
            mage_next_date_suggestion(true, false, $global_target);
            echo '<div class="wbtm_search_part" id="wbtm_return_container">';
            mage_bus_route_title(true);
            mage_bus_search_list(true);
            echo '</div>';
        }
    }
    echo '</div></div>';

    do_action('wbtm_after_search_list');
}


//bus search list
function mage_bus_search_list($return)
{
    global $wbtmmain;
    $is_old_date = false;
    $bus_list = mage_search_bus_query($return);
    $bus_list_loop = new WP_Query($bus_list);
    $j_date = $return ? $_GET['r_date'] : $_GET['j_date'];

    // Check is old date
    $j_date_ = date('Y-m-d', strtotime($j_date));
    if($j_date_ < date('Y-m-d')) {
        $is_old_date = true;
    }

    $j_date = mage_wp_date($j_date, 'Y-m-d');
    $start = $_GET['bus_start_route'];
    $end = $_GET['bus_end_route'];

    if ($return) {
        $start = $_GET['bus_end_route'];
        $end = $_GET['bus_start_route'];
    }


    //$j_date = mage_convert_date_format($j_date, 'Y-m-d');

    echo '<div class="mar_t mage_bus_lists">';
    mage_bus_title();

    $has_bus_data = array();
    $bus_index = 0;
    if(!$is_old_date) {
        while ($bus_list_loop->have_posts()) {
            $has_bus = false;
            $is_buffer = null;

            $bus_list_loop->the_post();
            $id = get_the_id();

            $bus_bp_array = get_post_meta($id, 'wbtm_bus_bp_stops', true) ? get_post_meta($id, 'wbtm_bus_bp_stops', true) : [];
            $bus_bp_array = maybe_unserialize($bus_bp_array);

            if ($bus_bp_array) {
                $bus_next_stops_array = get_post_meta($id, 'wbtm_bus_next_stops', true) ? get_post_meta($id, 'wbtm_bus_next_stops', true) : [];
                $bus_next_stops_array = maybe_unserialize($bus_next_stops_array);

                // Intermidiate Route
                $o_1 = mage_bus_end_has_prev($start, $end, $bus_bp_array);
                $o_2 = mage_bus_start_has_next($start, $end, $bus_next_stops_array);

                if ($o_1 && $o_2) {
                    continue;
                }

                // Intermidiate Route END

                // Buffer Time Calculation
                $bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
                $is_buffer = $wbtmmain->wbtm_buffer_time_check($bp_time, date('Y-m-d', strtotime($j_date)));
                // Buffer Time Calculation END

                // Midnight Calculation
                $is_midnight = mage_bus_is_midnight_trip($bus_bp_array, $start);
                if ($is_midnight) {
                    $j_date = date('Y-m-d', strtotime('-1 day', strtotime($j_date)));
                }
                // Midnight Calculation END

                if ($is_buffer == 'yes') {
                    // Operational on day
                    $is_on_date = false;
                    $bus_on_dates = array();
                    //                $bus_on_date = get_post_meta($id, 'wbtm_bus_on_dates', true);
                    $bus_on_date = mage_determine_ondate($id, $return, $start, $end);
                    if ($bus_on_date != null) {
                        $bus_on_dates = explode(', ', $bus_on_date);
                        $is_on_date = true;
                    }

                    if ($is_on_date) {

                        // echo $id.'<br>';
                        // echo '<pre>';print_r($bus_on_dates);
                        // die;
                        if (in_array($j_date, $bus_on_dates)) {
                            $has_bus = true;
                        }
                    } else {

                        // Offday schedule check
                        // $bus_stops_times = get_post_meta($id, 'wbtm_bus_bp_stops', true);
                        //                    $bus_offday_schedules = get_post_meta($id, 'wbtm_offday_schedule', true);
                        $bus_offday_schedules = mage_determine_offdate($id, $return, $start, $end);

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
                            $s_datetime = date('Y-m-d H:i:s', strtotime($j_date));

                            foreach ($bus_offday_schedules as $item) {

                                $c_iterate_date_from = $item['from_date'];
//                                $c_iterate_datetime_from = date('Y-m-d H:i:s', strtotime($c_iterate_date_from . ' ' . $item['from_time']));
                                $c_iterate_datetime_from = date('Y-m-d H:i:s', strtotime($c_iterate_date_from));

                                $c_iterate_date_to = $item['to_date'];
//                                $c_iterate_datetime_to = date('Y-m-d H:i:s', strtotime($c_iterate_date_to . ' ' . $item['to_time']));
                                $c_iterate_datetime_to = date('Y-m-d H:i:s', strtotime($c_iterate_date_to));

                                if (($s_datetime >= $c_iterate_datetime_from) && ($s_datetime <= $c_iterate_datetime_to)) {
                                    $offday_current_bus = true;
                                    break;
                                }
                            }
                        }

                        // Check Offday and date
                        if (!$offday_current_bus && !mage_check_search_day_off_new($id, $j_date, $return)) {
                            $has_bus = true;
                        }
                    }
                }
            }
            // var_dump($has_bus);

            // Has Bus
            if ($has_bus === true) {
                $has_bus_data[$bus_index]['return'] = $return;
                $has_bus_data[$bus_index]['id'] = $id;
            }

            $bus_index++;
        }
    }

    // Final list showing
    if (!empty($has_bus_data)) {
        mage_bus_list_sorting($has_bus_data, $start, $return); // Bus list sorting
    } else {
        echo '<p class="no-bus-found">';

        mage_bus_label('wbtm_no_bus_found_text', __('No', 'bus-ticket-booking-with-seat-reservation').' '.mage_bus_setting_value('bus_menu_label', 'Bus').' '.__('Found!', 'bus-ticket-booking-with-seat-reservation'));

        echo '</p>';
    }

    echo '<div class="mediumRadiusBottom mage_bus_list_title "></div>';
    echo '</div>';
    wp_reset_query();
}

function mage_bus_list_sorting($has_bus_data, $start_route, $return, $sort = 'ASC')
{

    $wbtm_bus_bp_stops_array = array();
    foreach ($has_bus_data as $bus) {
        $which_way = mage_determine_route($bus['id'], $return);
        $wbtm_bus_bp_stops = get_post_meta($bus['id'], $which_way, true);
        if ($wbtm_bus_bp_stops) {
            $wbtm_bus_bp_stops_array[$bus['id']] = array_values(maybe_unserialize($wbtm_bus_bp_stops));
        }
    }

    $target_bus_start_time = array();
    foreach ($wbtm_bus_bp_stops_array as $key => $stops) {
        foreach ($stops as $stop) {
            if ($stop['wbtm_bus_bp_stops_name'] == $start_route) {
                $target_bus_start_time[$key] = $stop['wbtm_bus_bp_start_time'];
            }
        }
    }

    // Sorting By $sort
    if ($sort == 'DESC') {
        arsort($target_bus_start_time);
    } else {
        asort($target_bus_start_time);
    }


    $final_sorted_ids = array();
    foreach ($target_bus_start_time as $id => $bus) {
        $final_sorted_ids[] = $id;
    }

    $sorted_bus_list = new WP_Query(array(
        'post_type' => 'wbtm_bus',
        'posts_per_page' => -1,
        'post__in' => $final_sorted_ids,
        'orderby' => array('post__in' => 'asc')
    ));

    while ($sorted_bus_list->have_posts()) {
        $sorted_bus_list->the_post();
        mage_bus_search_item($return, get_the_ID());
    }
}

function mage_bus_search_item($return, $id)
{

    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $bus_id = get_the_id();
    $seat_price = mage_bus_seat_price($bus_id, $start, $end, false);
    $values = get_post_custom($id);
    $start_time = mage_bus_time($return, false);
    $end_time = mage_bus_time($return, true);
    $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    $get_stops_dates = mage_get_bus_stops_date($bus_id, $date, $start, $end, $return);
    $arrival_date = $get_stops_dates['dropping'];
    $starting_date = $get_stops_dates['boarding'];

    $show_dropping_time = isset($values['show_dropping_time'][0]) ? $values['show_dropping_time'][0] : 'yes';

    $show_boarding_time = isset($values['show_boarding_time'][0]) ? $values['show_boarding_time'][0] : 'yes';

    $cart_class = wbtm_find_product_in_cart($return);

    $zero_price_allow = get_post_meta($bus_id, 'zero_price_allow', true) ?: 'no';

    // Check this route has price if not, return
    // $check_has_price = mage_bus_seat_price($bus_id, $start, $end, false);
    if (($zero_price_allow === 'no' && !$seat_price) || $seat_price === '') {
        return;
    }

    // Partial route available
    $partial_seat_booked = mage_partial_seat_booked_count($return);
    // Partial route available END

?>
    <div class="mage_bus_item <?php echo $cart_class; ?>" data-bus-id="<?php echo $bus_id; ?>" data-is-return="<?php echo $return; ?>">
        <div class="mage_flex">
            <?php $alt_image = (wp_get_attachment_url(mage_bus_setting_value('alter_image'))) ? wp_get_attachment_url(mage_bus_setting_value('alter_image')) : 'https://i.imgur.com/807vGSc.png'; ?>
            <div class="mage_bus_img flexCenter"><?php echo has_post_thumbnail() ? the_post_thumbnail('thumb') : "<img src=" . $alt_image . ">" ?></div>
            <div class="mage_bus_info flexEqual_flexCenter">
                <div class="flexEqual_flexCenter">
                    <h6>
                        <strong class="dBlock_mar_zero"><?php echo '<a href="' . get_the_permalink() . '">' . get_the_title() . '</a>'; ?></strong>
                        <small class="dBlock"><?php echo $values['wbtm_bus_no'][0]; ?></small>
                        <?php
                        if ($cart_class) {
                            echo '<span class="dBlock_mar_t_xs"><span class="fa fa-shopping-cart"></span>';
                            mage_bus_label('wbtm_already_in_cart_text', __('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'));
                            echo '</span>';
                        }
                        ?>
                    </h6>
                    <div class="mage_hidden_xxs">
                        <h6>
                            <span class="fa fa-angle-double-right"></span>
                            <span><?php echo $start; ?> <?php echo ($show_boarding_time == 'yes' ? sprintf('(%s %s)', mage_wp_date($starting_date), mage_wp_time($start_time)) : null); ?>
                            </span>
                        </h6>
                        <h6>
                            <span class="fa fa-stop"></span>
                            <span><?php echo $end; ?> <?php echo ($show_dropping_time == 'yes' ? sprintf('(%s %s)', mage_wp_date($arrival_date), mage_wp_time($end_time)) : null); ?>
                            </span>
                        </h6>
                    </div>
                </div>
                <div class="flexEqual_flexCenter_textCenter">
                    <h6 class="mage_hidden_xxs"><?php echo mage_bus_type(); ?></h6>
                    <h6 class="mage_hidden_xs">
                        <strong><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?></strong>/<span><?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                    </h6>
                    <h6 class="mage_hidden_md">
                        <?php echo (mage_bus_total_seat_new() - $partial_seat_booked) . ' / ' . mage_bus_total_seat_new(); ?>
                    </h6>
                    <button type="button" class="mage_button_xs mage_bus_details_toggle"><?php mage_bus_label('wbtm_view_seats_text', __('View Seats', 'bus-ticket-booking-with-seat-reservation')); ?></button>
                </div>
            </div>
        </div>
        <?php mage_bus_item_seat_details($return, $partial_seat_booked); ?>
    </div>
<?php
}

function mage_bus_item_seat_details($return, $partial_seat_booked = 0)
{
    global $mage_bus_total_seats_availabel;
    $bus_id = get_the_id();

    // Search Data
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    // $start_time = get_wbtm_datetime(mage_bus_time($return, false), 'time');
    $start_time = mage_wp_time(mage_bus_time($return, false));
    // $end_time = get_wbtm_datetime(mage_bus_time($return, true), 'time');
    $end_time = mage_wp_time(mage_bus_time($return, true));
    $return_date = isset($_GET['r_date']) && $_GET['r_date'] != '' ? $_GET['r_date'] : null;
    $seat_price = mage_bus_seat_price($bus_id, $start, $end, false);

    $show_dropping_time = get_post_meta($bus_id, 'show_dropping_time', true);
    $show_dropping_time = $show_dropping_time ? $show_dropping_time : 'yes';

    $show_boarding_time = get_post_meta($bus_id, 'show_boarding_time', true);
    $show_boarding_time = $show_boarding_time ? $show_boarding_time : 'yes';

    // Pickpoint
    // $pickpoints = get_post_meta($bus_id, 'wbtm_selected_pickpoint_name_'.strtolower($start), true);
    $pickpoints = mage_determine_pickuppoint($bus_id, $return, $start, $end);
    if ($pickpoints != '') {
        $pickpoints = maybe_unserialize($pickpoints);
    }
    

    // $partial_seat_booked = mage_partial_seat_booked_count($return);
    $seat_available = mage_bus_total_seat_new() - $partial_seat_booked;

    // Bus Seat Type
    $bus_seat_type_conf = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);

    $seat_panel_settings = get_option('wbtm_bus_settings');
    $adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
    $child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
    $infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
    $special_label = $seat_panel_settings['wbtm_seat_type_special_label'];

    $any_date_return_switch = !empty($seat_panel_settings['any_day_return']) ? $seat_panel_settings['any_day_return'] : 'off';
    // Bus Zero Price
    $bus_zero_price_allow = get_post_meta($bus_id, 'zero_price_allow') ? get_post_meta($bus_id, 'zero_price_allow')[0] : '';

    if ($bus_seat_type_conf === 'wbtm_without_seat_plan') {
        // Price
        // $seatPrices = get_post_meta($bus_id, 'wbtm_bus_prices', true);
        $seatPrices = mage_bus_seat_prices($bus_id, $start, $end);
        $available_seat_type = array();
        if ($seatPrices) {
            $i = 0;
            foreach ($seatPrices as $price) {
                if (strtolower($price['wbtm_bus_bp_price_stop']) == strtolower($start) && strtolower($price['wbtm_bus_dp_price_stop']) == strtolower($end)) {
                    if ((float)$price['wbtm_bus_price'] > 0 || $bus_zero_price_allow === 'yes') {
                        $available_seat_type[$i]['type'] = 'Adult';
                        $available_seat_type[$i]['price'] = $price['wbtm_bus_price'];
                        $i++;
                    }
                    if ((float)$price['wbtm_bus_child_price'] >= 0 && $price['wbtm_bus_child_price'] != '') {
                        $available_seat_type[$i]['type'] = 'Child';
                        $available_seat_type[$i]['price'] = $price['wbtm_bus_child_price'];
                        $i++;
                    }
                    if ((float)$price['wbtm_bus_infant_price'] >= 0 && $price['wbtm_bus_infant_price'] != '') {
                        $available_seat_type[$i]['type'] = 'Infant';
                        $available_seat_type[$i]['price'] = $price['wbtm_bus_infant_price'];
                        $i++;
                    }
                    break;
                }  // end foreach
            }
        } // end if
    } // end if

?>
    <form class="mage_form wbtm_bus_booking" action="" method="post">
        <div class="mage_bus_seat_details">
            <input type="hidden" name='journey_date' value='<?php echo mage_wp_date($date, 'Y-m-d'); ?>' />
            <input type="hidden" name='return_date' value='<?php echo mage_wp_date($return_date, 'Y-m-d'); ?>' />
            <input type="hidden" name='start_stops' value="<?php echo $start; ?>" />
            <input type='hidden' name='end_stops' value='<?php echo $end; ?>' />
            <input type="hidden" name="user_start_time" value="<?php echo mage_bus_time($return, false); ?>" />
            <input type="hidden" name="bus_start_time" value="<?php echo mage_bus_time($return, false); ?>" />
            <input type="hidden" name="bus_id" value="<?php echo $bus_id; ?>" />
            <input type="hidden" name="seat_available" value="<?php echo $seat_available; ?>" />
            <input type="hidden" name='total_seat' value="0" />
            <input type="hidden" name="wbtm_bus_type" value="general" />
            <input type="hidden" name="wbtm_bus_zero_price_allow" value="<?php echo $bus_zero_price_allow; ?>" />
            <input type="hidden" name="wbtm_anydate_return_price" id="wbtm_anydate_return_price" value="" />
            <input type="hidden" name="wbtm_bus_no" value="<?php echo  get_post_meta($bus_id, 'wbtm_bus_no', true)  ?>">
            <input type="hidden" name="wbtm_bus_name" value="<?php echo get_the_title() ?>">
            <?php
            if ($return) {
                echo '<input type="hidden" name="wbtm_booking_now" value="return">';
            }
            if ($bus_seat_type_conf === 'wbtm_without_seat_plan') : ?>

                <!-- Seat type = No seat -->
                <input type="hidden" name="wbtm_order_seat_plan" value="no">
                <input type="hidden" name="custom_reg_user" value="no" />
                <div class="mage-no-seat">
                    <div class="mage-no-seat-inner">
                        <div class="mage-no-seat-left">
                            <table class="mage-seat-table mage-bus-short-info">
                                <tr>
                                    <th><i class="fas fa-map-marker"></i>
                                        <?php mage_bus_label('wbtm_boarding_points_text', __('Boarding', 'bus-ticket-booking-with-seat-reservation')); ?>
                                        :
                                    </th>
                                    <td><?php echo $start; ?> <?php echo ($show_boarding_time == 'yes' ? sprintf('(%s)',  mage_wp_time($start_time)) : null); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fas fa-map-marker"></i>
                                        <?php mage_bus_label('wbtm_dropping_points_text', __('Dropping', 'bus-ticket-booking-with-seat-reservation')) ?>
                                        :
                                    </th>
                                    <td><?php echo $end; ?> <?php echo ($show_dropping_time == 'yes' ? sprintf('(%s)',  mage_wp_time($end_time)) : null); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-bus" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_type_text', __('Coach Type', 'bus-ticket-booking-with-seat-reservation')); ?>
                                        :
                                    </th>
                                    <td><?php echo mage_bus_type(); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-calendar" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_date_text', __('Date', 'bus-ticket-booking-with-seat-reservation')); ?>
                                        :
                                    </th>
                                    <td><?php echo mage_wp_date($date); ?></td>
                                </tr>
                                <?php if ($show_boarding_time == 'yes') { ?>
                                    <tr>
                                        <th><i class="fa fa-clock-o" aria-hidden="true"></i>
                                            <?php mage_bus_label('wbtm_start_time_text', __('Start Time', 'bus-ticket-booking-with-seat-reservation')) ?>
                                            :
                                        </th>
                                        <td><?php echo $start_time; ?></td>
                                    </tr>
                                <?php } ?>

                                <tr>
                                    <th><i class="fas fa-map-marker"></i>
                                        <?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?>
                                        :
                                    </th>
                                    <td><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?> /
                                        <?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?>
                                    </td>
                                </tr>
                            </table>
                            <div class="mage-grand-total">
                                <p><strong><?php _e('Grand Total', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        :</strong> <span class="mage-price-figure">0.00</span></p>
                            </div>
                        </div>
                        <div class="mage-no-seat-right">
                            <table class="mage-seat-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Type', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                        <th><?php _e('Quantity', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                        <th><?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                        <th><?php _e('SubTotal', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_seat_type as $type) :
                                        if (($type['price'] >= 0 && $type['price'] != '') || $bus_zero_price_allow === 'yes') : ?>
                                            <tr>
                                                <td><?php echo wbtm_get_seat_type_label(strtolower($type['type']), $type['type']) ?></td>
                                                <td class="mage-seat-qty">
                                                    <button class="wbtm-qty-change wbtm-qty-dec" data-qty-change="dec">-
                                                    </button>
                                                    <input class="qty-input" type="text" data-seat-type="<?php echo strtolower($type['type']); ?>" data-price="<?php echo $type['price']; ?>" name="seat_qty[]" />
                                                    <button class="wbtm-qty-change wbtm-qty-inc" data-qty-change="inc">+
                                                    </button>
                                                    <input type="hidden" name="passenger_type[]" value="<?php echo $type['type'] ?>">
                                                    <input type="hidden" name="bus_dd[]" value="no">
                                                </td>
                                                <td><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $type['price'])) . '<sub> / ' . __("Seat", "bus-ticket-booking-with-seat-reservation") . '</sub>'; ?>
                                                </td>
                                                <td class="mage-seat-price">
                                                    <?php echo '<span class="price-figure">0.00</span>' ?>
                                                </td>
                                            </tr>
                                    <?php endif;
                                    endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4"></td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td><strong><?php _e('Total', 'bus-ticket-booking-with-seat-reservation'); ?>
                                                :</strong></td>
                                        <td class="mage-price-total">
                                            <strong><span class="price-figure">0.00</span></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php if ($any_date_return_switch == 'on') : ?>
                                <div class="wbtm_anydate_return_wrap">
                                    <div class="wbtm_anydate_return_col">
                                        <?php _e('Any Date Return:', 'bus-ticket-booking-with-seat-reservation') ?><br>
                                        <small><?php mage_bus_label('wbtm_anydate_return_desc_text', __('Same ticket will be valid for return up to next 15 days', 'bus-ticket-booking-with-seat-reservation')); ?></small>
                                    </div>
                                    <div class="wbtm_anydate_return_col">
                                        <div class="wbtm_anydate_return_switch">
                                            <label for="wbtm_anydate_return_off"><input type="radio" name="wbtm_anydate_return" class="wbtm_anydate_return" value="off" id="wbtm_anydate_return_off"> <span>off</span></label><label for="wbtm_anydate_return_on"><input type="radio" name="wbtm_anydate_return" class="wbtm_anydate_return" value="on" id="wbtm_anydate_return_on"> <span>on</span></label>
                                        </div>
                                    </div>

                                </div>
                            <?php endif; ?>

                            <?php if ($pickpoints) : ?>
                                <div class="wbtm-pickpoint-wrap">
                                    <label for="wbtm-pickpoint-no-seat"><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?>
                                        <span class="wbtm_required">*</span></label>
                                    <select name="wbtm_pickpoint" id="wbtm-pickpoint-no-seat" required>
                                        <option value=""><?php _e('Select Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?></option>
                                        <?php foreach ($pickpoints as $point) :
                                            $d = ucfirst($point['pickpoint']) . ' [' . $point['time'] . ']';
                                        ?>
                                            <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <!-- Extra Services -->
                            <?php
                            wbtm_extra_services_section($bus_id);
                            ?>
                            <!-- Extra Services END -->
                        </div>
                    </div>
                    <p class="wbtm-booking-error"><?php _e('Seat limit exceeded!', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    <div id="wbtm-form-builder">
                        <img class="wbtm-loading" src="<?php echo plugin_dir_url(__FILE__) . '../../' . '/images/new-loading.gif'; ?>" alt="" />
                        <div id="wbtm-form-builder-adult" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
                        <div id="wbtm-form-builder-child" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
                        <div id="wbtm-form-builder-infant" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
                        <div id="wbtm-form-builder-es" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
                    </div>
                    <?php if (mage_bus_total_seat_new() > $partial_seat_booked) :
                        do_action('wbtm_before_add_cart_btn', $bus_id, false);
                    ?>
                        <button class="mage_button no-seat-submit-btn" disabled type="submit" name="add-to-cart" value="<?php echo get_post_meta($bus_id, 'link_wc_product', true); ?>" class="single_add_to_cart_button">
                            <?php mage_bus_label('wbtm_book_now_text', __('Book Now', 'bus-ticket-booking-with-seat-reservation')); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <!-- No Seat Plan END -->
            <?php else : ?>
                <!-- Seat Plan -->
                <input type="hidden" name="wbtm_order_seat_plan" value="yes">
                <div class="mage_flex_justifyBetween">
                    <?php
                    $seat_plan_type = mage_get_bus_seat_plan_type();
                    if ($seat_plan_type > 0) {
                        $bus_width = 250;
                    } else {
                        $bus_width = 250;
                    }

                    mage_bus_seat_plan($seat_plan_type, $bus_width, $seat_price, $return);
                    ?>
                    <div class="mage_bus_customer_sec mage_default" style="box-sizing:border-box;width: calc(100% - 8px - <?php echo $bus_width; ?>px);">
                        <div class="flexEqual" style="align-items:flex-start">
                            <div class="mage_bus_details_short">
                                <h6>
                                    <span class='wbtm-details-page-list-label'><span class="fa fa-map-marker"></span><?php
                                                                                                                        mage_bus_label('wbtm_boarding_points_text', __('Boarding', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                    <?php echo $start; ?> <?php echo ($show_boarding_time == 'yes' ? sprintf('(%s)',  mage_wp_time($start_time)) : null); ?>
                                </h6>
                                <h6 class="mar_t_xs">

                                    <span class='wbtm-details-page-list-label'> <span class="fa fa-map-marker"></span><?php mage_bus_label('wbtm_dropping_points_text', __('Dropping', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                    <?php echo $end; ?> <?php echo ($show_dropping_time == 'yes' ? sprintf('(%s)',  mage_wp_time($end_time)) : null); ?>
                                </h6>
                                <h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'><i class="fa fa-bus" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_type_text', __('Coach Type:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                    <?php echo mage_bus_type(); ?>
                                </h6>
                                <h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'><i class="fa fa-calendar" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_date_text', __('Date:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                    <?php echo mage_wp_date($date); ?>
                                </h6>
                                <?php if ($show_boarding_time == 'yes') { ?>
                                    <h6 class="mar_t_xs">
                                        <span class='wbtm-details-page-list-label'><i class="fa fa-clock-o" aria-hidden="true"></i>
                                            <?php mage_bus_label('wbtm_start_time_text', __('Start Time:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                        <?php echo $start_time; ?>
                                    </h6>
                                <?php } ?>
                                <h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'>
                                        <i class="fa fa-money" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_fare_text', __('Fare:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                    <?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?>/
                                    <span><?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                </h6>
                                <h6 class="mar_t_xs wbtm-details-page-list-total-avl-seat">
                                    <strong><?php echo $mage_bus_total_seats_availabel //mage_bus_available_seat($return);
                                            ?></strong>
                                    <span><?php mage_bus_label('wbtm_seat_available_text', __('Seat Available', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                                </h6>
                            </div>
                            <div class="textCenter mage_bus_seat_list">
                                <div class="flexEqual mage_bus_selected_list">
                                    <h6>
                                        <strong><?php mage_bus_label('wbtm_seat_no_text', __('Seat No', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                    </h6>
                                    <?php
                                    if (mage_bus_multiple_passenger_type_check($bus_id, $start, $end)) {
                                    ?>
                                        <h6><strong><?php mage_bus_text('Type'); ?></strong></h6>
                                    <?php
                                    }
                                    ?>
                                    <h6>
                                        <strong><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                    </h6>
                                    <h6>
                                        <strong><?php mage_bus_label('wbtm_remove_text', __('Remove', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                    </h6>
                                </div>
                                <div class="mage_bus_selected_seat_list"></div>
                                <div class="mage_bus_selected_list mage_bus_sub_total padding">
                                    <h5>
                                        <strong><?php mage_bus_label('wbtm_qty_text', __('Qty :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                        <span class="mage_bus_total_qty">0</span>
                                    </h5>
                                    <h5>
                                        <strong><?php mage_bus_label('wbtm_sub_total_text', __('Sub Total :', 'bus-ticket-booking-with-seat-reservation')); ?></strong><strong class="mage_bus_sub_total_price mage-price-total"> <span class="price-figure">0.00</span></strong>
                                    </h5>
                                    <div class="mage_extra_bag">
                                        <h5>
                                            <strong><?php mage_bus_label('wbtm_extra_bag_text', __('Extra Bag :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                            <span class="mage_bus_extra_bag_qty">0</span>x
                                            <span class="mage_extra_bag_price"><?php echo wc_price(0); ?></span>=
                                            <strong class="mage_bus_extra_bag_total_price"><?php echo wc_price(0); ?></strong>
                                        </h5>
                                    </div>
                                </div>
                                <?php if ($any_date_return_switch == 'on') : ?>
                                    <div class="wbtm_anydate_return_wrap">
                                        <div class="wbtm_anydate_return_col">
                                            <?php _e('Any Date Return:', 'bus-ticket-booking-with-seat-reservation') ?><br>
                                            <small><?php mage_bus_label('wbtm_anydate_return_desc_text', __('Same ticket will be valid for return up to next 15 days', 'bus-ticket-booking-with-seat-reservation')); ?></small>
                                        </div>
                                        <div class="wbtm_anydate_return_col">
                                            <div class="wbtm_anydate_return_switch">
                                                <label for="wbtm_anydate_return_off"><input type="radio" name="wbtm_anydate_return" class="wbtm_anydate_return" value="off" id="wbtm_anydate_return_off"> <span>off</span></label><label for="wbtm_anydate_return_on"><input type="radio" name="wbtm_anydate_return" class="wbtm_anydate_return" value="on" id="wbtm_anydate_return_on"> <span>on</span></label>
                                            </div>
                                        </div>

                                    </div>
                                <?php endif; ?>
                                <?php if ($pickpoints) : ?>
                                    <div class="wbtm-pickpoint-wrap" style="margin-top:20px">
                                        <label for="wbtm-pickpoint-no-seat"><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?>
                                            <span class="wbtm_required">*</span></label>
                                        <select name="wbtm_pickpoint" id="wbtm-pickpoint-no-seat" required>
                                            <option value=""><?php _e('Select Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?></option>
                                            <?php foreach ($pickpoints as $point) :
                                                $d = ucfirst($point['pickpoint']) . (($point['time']) ? ' [' . $point['time'] . ']' : '');
                                            ?>
                                                <option value="<?php echo $d; ?>"><?php echo $d ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <!-- Extra Services -->
                                <?php
                                wbtm_extra_services_section($bus_id);
                                ?>
                                <!-- Extra Services END -->
                            </div>
                        </div>
                        <div class="mage_customer_info_area">
                            <!-- <input type="hidden" name="custom_reg_user" value="no" /> -->
                        </div>
                        <div class="flexEqual flexCenter textCenter_mar_t">
                            <h4>
                                <strong><?php mage_bus_label('wbtm_total_text', __('Total :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
                                <strong class="mage_bus_total_price mage-grand-total"> <span class="mage-price-figure">0.00</span></strong>
                            </h4>
                            <div>
                                <?php if (mage_bus_total_seat_new() > $partial_seat_booked) :
                                    do_action('wbtm_before_add_cart_btn', $bus_id, false);
                                ?>
                                    <button class="mage_button" type="submit" disabled name="add-to-cart" value="<?php echo get_post_meta($bus_id, 'link_wc_product', true); //echo esc_attr(get_the_id());
                                                                                                                    ?>" style="max-width:100%">
                                        <?php mage_bus_label('wbtm_book_now_text', __('Book Now', 'bus-ticket-booking-with-seat-reservation')); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Seat Plan END -->

            <?php endif; ?>
        </div>
    </form>
    <?php
    // if ($bus_seat_type_conf === 'wbtm_seat_plan') {
    //     do_action('mage_bus_hidden_customer_info_form');
    // }
    ?>
<?php
}

//bus seat plan
function mage_bus_seat_plan($seat_plan_type, $bus_width, $price, $return)
{
    global $mage_bus_total_seats_availabel;
    $bus_id = get_the_id();
    $current_driver_position = get_post_meta($bus_id, 'driver_seat_position', true);
    $seat_panel_settings = get_option('wbtm_bus_settings');
    if (isset($seat_panel_settings['diriver_image'])) {
        if ($seat_panel_settings['diriver_image'] != '') {
            $driver_image = wp_get_attachment_url($seat_panel_settings['diriver_image']);
        } else {
            $driver_image = WBTM_PLUGIN_URL . 'public/images/driver-default.png';
        }
    } else {
        $driver_image = WBTM_PLUGIN_URL . 'public/images/driver-default.png';
    }

    $all_stopages_name = mage_bus_get_all_stopages(get_the_id());

    // upper deck
    $seats_dd = get_post_meta($bus_id, 'wbtm_bus_seats_info_dd', true);

    $seat_html = '';
?>
    <div class="mage_bus_seat_plan" style="box-sizing:border-box;width: <?php echo $bus_width; ?>px;">
        <?php
        $upper_deck = (isset($seat_panel_settings['useer_deck_title']) ? $seat_panel_settings['useer_deck_title'] : __('Upper Deck', 'bus-ticket-booking-with-seat-reservation'));
        $lower_deck = (isset($seat_panel_settings['lower_deck_title']) ? $seat_panel_settings['lower_deck_title'] : __('Lower Deck', 'bus-ticket-booking-with-seat-reservation'));
        if (!empty($seats_dd)) {
            echo '<strong class="deck-type-text">' . __($lower_deck, 'bus-ticket-booking-with-seat-reservation') . '</strong>';
        }
        ?>
        <div class="mage_default_pad_xs">
            <div class="flexEqual">
                <div class="padding"><img class="driver_img <?php echo ($current_driver_position == 'driver_left') ? 'mageLeft' : 'mageRight'; ?>" src="<?php echo $driver_image; ?>" alt=""></div>
            </div>
            <?php
            $mage_bus_total_seats_availabel = mage_bus_total_seat_new();
            if ($seat_plan_type > 0) {
                $seats_rows = get_post_meta($bus_id, 'wbtm_bus_seats_info', true) ? get_post_meta($bus_id, 'wbtm_bus_seats_info', true) : [];
                $seat_col = get_post_meta($bus_id, 'wbtm_seat_cols', true);
                // $seat_html .= '<div class="defaultLoaderFixed"><span></span></div>';
                foreach ($seats_rows as $seat) {
                    $seat_html .= '<div class="flexEqual mage_bus_seat">';
                    for ($i = 1; $i <= $seat_col; $i++) {
                        $seat_name = $seat["seat" . $i];
                        $seat_html .= mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, 0);
                    }
                    $seat_html .= '</div>';
                }
                echo $seat_html;
            } elseif ($seat_plan_type == 'seat_plan_1' || $seat_plan_type == 'seat_plan_2' || $seat_plan_type == 'seat_plan_3') {
                $bus_meta = get_post_custom($bus_id);
                if (isset($bus_meta['wbtm_seat_row'][0])) {
                    $seats_rows = explode(",", $bus_meta['wbtm_seat_row'][0]);
                    $seat_col = $bus_meta['wbtm_seat_col'][0];
                    $seat_col_arr = explode(",", $seat_col);
                    foreach ($seats_rows as $seat) {
                        echo '<div class="flexEqual mage_bus_seat">';
                        foreach ($seat_col_arr as $seat_col) {
                            $seat_name = $seat . $seat_col;
                            // $mage_bus_total_seats_availabel = mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, $seat_col, $all_stopages_name, $mage_bus_total_seats_availabel);
                            echo mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, $seat_col);
                        }
                        echo '</div>';
                    }
                }
            } else {
                echo 'Please update Your Seat Plan !';
            }
            ?>
        </div>
        <?php

        $seat_col_dd = get_post_meta($bus_id, 'wbtm_seat_cols_dd', true);

        if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
            $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
            $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
            $price = mage_bus_seat_price($bus_id, $start, $end, true);

            if (!empty($seats_dd)) {
                echo '<strong class="deck-type-text">' . __($upper_deck, 'bus-ticket-booking-with-seat-reservation') . '</strong>';
            }
            echo '<div class="mage_default_pad_xs_mar_t" style="margin-top: 4px!important;">';

            foreach ($seats_dd as $seat) {
                echo '<div class="flexEqual mage_bus_seat">';
                for ($i = 1; $i <= $seat_col_dd; $i++) {
                    $seat_name = $seat["dd_seat" . $i];
                    echo mage_bus_seat($seat_plan_type, $seat_name, $price, true, $return, 0);
                }
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    </div>
    <?php
}

//bus seat place
function mage_bus_seat($seat_plan_type, $seat_name, $price, $dd, $return, $seat_col)
{
    global $mage_bus_total_seats_availabel;
    $seat_panel_settings = get_option('wbtm_bus_settings');
    $blank_seat_img = $seat_panel_settings['seat_blank_image'];
    $cart_seat_img = $seat_panel_settings['seat_active_image'];
    $block_seat_img = $seat_panel_settings['seat_booked_image'];
    $sold_seat_img = $seat_panel_settings['seat_sold_image'];

    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');

    ob_start();

    if (strtolower($seat_name) == 'door') {
        echo '<div></div>';
    } elseif (strtolower($seat_name) == 'wc') {
        echo '<div></div>';
    } elseif ($seat_name == '') {
        echo '<div></div>';
    } else {

        // GET status, boarding_point, dropping_point
        $all_stopages_name = get_post_meta(get_the_ID(), 'wbtm_bus_bp_stops', true);
        $all_stopages_name = is_array($all_stopages_name) ? $all_stopages_name : unserialize($all_stopages_name);
        $all_stopages_name = array_column($all_stopages_name, 'wbtm_bus_bp_stops_name');

        $partial_route_condition = false; // init value
        $get_search_start_position = array_search($start, $all_stopages_name);
        $get_search_droping_position = array_search($end, $all_stopages_name);

        $get_search_droping_position = (is_bool($get_search_droping_position) && !$get_search_droping_position ? count($all_stopages_name) : $get_search_droping_position); // Last Stopage position assign

        $get_booking_data = get_seat_booking_data($seat_name, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return);
        $seat_status = $get_booking_data['status'];
        $partial_route_condition = $get_booking_data['has_booked'];


        // Seat booked show policy in search
        $seat_booked_status_default = array(1, 2);
        $seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);
        // Seat booked show policy in search

        if (wbtm_find_seat_in_cart($seat_name, $return)) {
    ?>
            <div class="flex_justifyCenter mage_seat_in_cart" title="<?php _e('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'); ?>">
                <?php
                if ($cart_seat_img) {
                    echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($cart_seat_img) . '" alt="Block" /></div>';
                } else {
                    echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
                }
                ?>
            </div>
        <?php
        } elseif (($seat_status == 1 || $seat_status == 3 || $seat_status == 4 || $seat_status == 5 || $seat_status == 6 || $seat_status == 7) && in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) {
            $mage_bus_total_seats_availabel--; // for seat available
        ?>
            <div class="flex_justifyCenter mage_seat_booked" title="<?php _e('Already Booked By another!', 'bus-ticket-booking-with-seat-reservation'); ?>">
                <?php
                if ($block_seat_img) {
                    echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($block_seat_img) . '" alt="Block" /></div>';
                } else {
                    echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
                }
                ?>
            </div>
        <?php
        } elseif (in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) {
            $mage_bus_total_seats_availabel--; // for seat available
        ?>
            <div class="flex_justifyCenter mage_seat_confirmed" title="<?php _e('Already Sold By another!', 'bus-ticket-booking-with-seat-reservation'); ?>">
                <?php
                if ($sold_seat_img) {
                    echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($sold_seat_img) . '" alt="Block" /></div>';
                } else {
                    echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
                }
                ?>
            </div>
        <?php
        } else {
        ?>
            <div class="flex_justifyCenter mage_bus_seat_item" data-bus-dd="<?php echo $dd ? 'yes' : 'no'; ?>" data-price="<?php echo $price; ?>" data-seat-name="<?php echo $seat_name; ?>" data-passenger-type="0">
                <?php
                if ($blank_seat_img) {
                    echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($blank_seat_img) . '" alt="Block" /></div>';
                } else {
                    echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
                }
                ?>
                <?php mage_bus_passenger_type($return, $dd) ?>
            </div>
        <?php
        }
        if (($seat_plan_type == 'seat_plan_1' && $seat_col == 2) || ($seat_plan_type == 'seat_plan_2' && $seat_col == 1) || ($seat_plan_type == 'seat_plan_3' && $seat_col == 2)) {
            echo '<div></div>';
        }
    }

    return ob_get_clean();
}

//next 6  date suggestion
function mage_next_date_suggestion($return, $single_bus, $target)
{
    $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    $date = wbtm_convert_date_to_php($date);
    if ($date) {
        $tab_date = isset($_GET['tab_date']) ? $_GET['tab_date'] : mage_wp_date(mage_bus_isset('j_date'), 'Y-m-d');
        $tab_date_r = isset($_GET['tab_date_r']) ? $_GET['tab_date_r'] : mage_wp_date(mage_bus_isset('r_date'), 'Y-m-d');
        $next_date = $return ? $tab_date_r : $tab_date;

        $next_date_text = $next_date;
        ?>
        <div class="mage_default_xs">
            <ul class="mage_list_inline flexEqual mage_next_date">
                <?php
                for ($i = 0; $i < 6; $i++) {
                ?>
                    <li class="<?php echo $date == $next_date ? 'mage_active' : ''; ?>">
                        <a href="<?php echo $single_bus ? '' : get_site_url() . '/' . $target; ?>?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $return ? strip_tags($_GET['j_date']) : $next_date_text; ?>&r_date=<?php echo $return ? $next_date : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>&bus-r=<?php echo (isset($_GET['bus-r']) ? strip_tags($_GET['bus-r']) : ''); ?>&tab_date=<?php echo $tab_date; ?>&tab_date_r=<?php echo $tab_date_r; ?>" data-sroute='<?php echo strip_tags($_GET['bus_start_route']); ?>' data-eroute='<?php echo strip_tags($_GET['bus_end_route']); ?>' data-jdate='<?php echo $return ? strip_tags($_GET['j_date']) : $next_date; ?>' data-rdate='<?php echo $return ? $next_date : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>' class='wbtm_next_day_search'>
                            <?php echo get_wbtm_datetime($next_date, 'date-text') ?>
                            <?php //echo mage_wp_date($next_date);
                            ?>
                        </a>
                    </li>
                <?php
                    $next_date = date('Y-m-d', strtotime($next_date . ' +1 day'));
                    // $next_date_text = get_wbtm_datetime($next_date, 'date-text');
                    $next_date_text = $next_date;
                }
                ?>
            </ul>
        </div>
    <?php
    }
}



//nearest max 6  date suggestion for single bus
function mage_next_date_suggestion_single($return, $single_bus, $target)
{
    $j_date = mage_bus_isset('j_date');
    $j_date = wbtm_convert_date_to_php($j_date);

    $wbtm_bus_on_dates = get_post_meta(get_the_id(), 'wbtm_bus_on_dates', true) ? maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_on_dates', true)) : [];

    $wbtm_offday_schedules = get_post_meta(get_the_id(), 'wbtm_offday_schedule', true)?get_post_meta(get_the_id(), 'wbtm_offday_schedule', true):[];
    $weekly_offday = get_post_meta(get_the_id(), 'weekly_offday', true) ? get_post_meta(get_the_id(), 'weekly_offday', true):[];

   // echo '<pre>'; echo print_r($wbtm_offday_schedules); echo '<pre>';


    if ($wbtm_bus_on_dates) {
    ?>
        <div class="mage_default_xs">
            <ul class="mage_list_inline flexEqual mage_next_date">
                <?php
                $wbtm_bus_on_dates_arr = explode(',', $wbtm_bus_on_dates);
                foreach ($wbtm_bus_on_dates_arr as $i => $ondate) {
                    if ($j_date <= wbtm_convert_date_to_php($ondate)) {
                        $ondate = ($i) ? wbtm_convert_date_to_php($ondate) : $j_date;
                ?>
                        <li class="<?php echo $j_date == $ondate ? 'mage_active' : ''; ?>">
                            <a href="<?php echo $single_bus ? '' : get_site_url() . '/' . $target; ?>?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $return ? strip_tags($_GET['j_date']) : $ondate; ?>&r_date=<?php echo $return ? $ondate : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>&bus-r=<?php echo (isset($_GET['bus-r']) ? strip_tags($_GET['bus-r']) : ''); ?>" data-sroute='<?php echo strip_tags($_GET['bus_start_route']); ?>' data-eroute='<?php echo strip_tags($_GET['bus_end_route']); ?>' data-jdate='<?php echo $return ? strip_tags($_GET['j_date']) : $next_date; ?>' data-rdate='<?php echo $return ? $next_date : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>' class='wbtm_next_day_search'>
                                <?php
                                echo get_wbtm_datetime($ondate, 'date-text')
                                ?>
                            </a>
                        </li>
                <?php
                    }
                }
                ?>

            </ul>
        </div>

        <?php
    }elseif ($wbtm_offday_schedules || $weekly_offday){


        $alloffdays = array();
        foreach ($wbtm_offday_schedules as $wbtm_offday_schedule) {
            $alloffdays =  array_unique(array_merge($alloffdays, displayDates($wbtm_offday_schedule['from_date'], $wbtm_offday_schedule['to_date'])));;
        }

        $offday = array();
        foreach ($alloffdays as $alloffday) {
            $offday[] =  date('Y-m-d', strtotime($alloffday));
        }
        $next_date = $j_date;

        $weekly_offday = get_post_meta(get_the_id(), 'weekly_offday', true) ? get_post_meta(get_the_id(), 'weekly_offday', true) : [];

    ?>
        <div class="mage_default_xs">
            <ul class="mage_list_inline flexEqual mage_next_date">
                <?php
                $i = 0;
                for ($m = 1; $m < 6; $i++) {
                    if (!in_array($next_date, $offday) and !in_array(date('w', strtotime($next_date)), $weekly_offday) and $m < 6) {
                        $m++;
                ?>
                        <li class="<?php echo $j_date == $next_date ? 'mage_active' : ''; ?>">
                            <a href="<?php echo $single_bus ? '' : get_site_url() . '/' . $target; ?>?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $return ? strip_tags($_GET['j_date']) : $next_date_text; ?>&r_date=<?php echo $return ? $next_date : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>&bus-r=<?php echo (isset($_GET['bus-r']) ? strip_tags($_GET['bus-r']) : ''); ?>" data-sroute='<?php echo strip_tags($_GET['bus_start_route']); ?>' data-eroute='<?php echo strip_tags($_GET['bus_end_route']); ?>' data-jdate='<?php echo $return ? strip_tags($_GET['j_date']) : $next_date; ?>' data-rdate='<?php echo $return ? $next_date : (isset($_GET['r_date']) ? strip_tags($_GET['r_date']) : ''); ?>' class='wbtm_next_day_search'>
                                <?php echo get_wbtm_datetime($next_date, 'date-text') ?>
                            </a>
                        </li>
                <?php
                    }
                    $next_date = date('Y-m-d', strtotime($next_date . ' +1 day'));
                    // $next_date_text = get_wbtm_datetime($next_date, 'date-text');
                    $next_date_text = $next_date;
                }
                ?>
            </ul>
        </div>

        <?php
    }else{
        mage_next_date_suggestion(false, true, $target);

    }
}



// bus list title
function mage_bus_route_title($return)
{
    $start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
    $end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
    $date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
    ?>
    <div class="bgLight_mar_t_textCenter_radius_pad_xs_justifyAround mage_title">
        <h4>
            <strong>
                <span><?php echo $start; ?></span>
                <span class="fa fa-long-arrow-right"></span>
                <span><?php echo $end; ?></span>
            </strong>
        </h4>
        <!-- <h4><strong><?php //echo mage_wp_date($date); 
                            ?></strong></h4> -->
        <h4><strong><?php echo get_wbtm_datetime($date, 'date-text'); ?></strong></h4>
    </div>
    <?php
}

add_action('wbtm_after_search_list', 'wbtm_booking_return_scroll');
function wbtm_booking_return_scroll()
{
    if (isset($_POST['return_date'])) {
    ?>
        <script>
            const id = "wbtm_return_container";
            const yOffset = 30;
            const element = document.getElementById(id);
            const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;

            window.scrollTo({
                top: y,
                behavior: 'smooth'
            });
        </script>
    <?php
    }
}
