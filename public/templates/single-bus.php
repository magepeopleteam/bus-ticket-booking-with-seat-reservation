<?php
get_header();
the_post();
$id = get_the_id();
$values = get_post_custom($id);
?>
    <div class="mage mage_single_bus_search_page">
        <?php do_action('woocommerce_before_single_product'); ?>
        <div class="post-content-wrap">
        <?php echo the_content();?>
        </div>
        <div class="mage_default">
            <div class="flexEqual">
                <div class="mage_xs_full"><?php the_post_thumbnail('full'); ?></div>
                <div class="ml_25 mage_xs_full">
                    <div class="mage_default_bDot">
                        <h4><?php the_title(); ?><small>( <?php echo $values['wbtm_bus_no'][0]; ?> )</small></h4>
                        <h6 class="mar_t_xs"><strong><?php _e('Bus Type :', 'bus-ticket-booking-with-seat-reservation'); ?></strong><?php echo mage_bus_type(); ?></h6>
                        <h6 class="mar_t_xs"><strong><?php _e('Passenger Capacity :', 'bus-ticket-booking-with-seat-reservation'); ?></strong><?php echo mage_bus_total_seat(); ?></h6>
                        <?php if (mage_bus_run_on_date(false) && isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) { ?>
                            <h6 class="mar_t_xs">
                                <span><?php _e('Fare :', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                <strong><?php echo wc_price(mage_bus_seat_price($id,mage_bus_isset('bus_start_route'), mage_bus_isset('bus_end_route'),false)); ?></strong>/
                                <span><?php _e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                            </h6>
                        <?php } ?>
                    </div>
                    <div class="flexEqual_mar_t mage_bus_drop_board">
                        <div class="mage_default_bDot">
                            <h5><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
                            <ul class="mage_list mar_t_xs">
                                <?php
                                $start_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
                                foreach ($start_stops as $route) {
                                    echo '<li><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_bp_stops_name'] . '</li>';
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="mage_default_bDot">
                            <h5><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
                            <ul class="mage_list mar_t_xs">
                                <?php
                                $end_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
                                foreach ($end_stops as $route) {
                                    echo '<li><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_next_stops_name'] . '</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mage_default mage_form_inline">

            <?php

            $global_target = $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec') ? get_post_field('post_name', $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec')) : 'bus-search-list';
            if(isset($params)) {
                $target = $params['search-page'] ? $params['search-page'] : $global_target;
            } else {
                $target = $global_target;
            }

            mage_bus_search_form_only(true, $target); ?>
        </div>
        <?php
        //  if (mage_bus_run_on_date(false) && isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) { 
         if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {
            
            $start = $_GET['bus_start_route'];
            $end = $_GET['bus_end_route'];
            $j_date = $_GET['j_date'];
            $j_date = mage_convert_date_format($j_date, 'Y-m-d');
            $check_has_price = mage_bus_seat_price($id, $start, $end, false);
            $has_bus = false;

            $bus_bp_array = get_post_meta($id, 'wbtm_bus_bp_stops', true) ? get_post_meta($id, 'wbtm_bus_bp_stops', true) : [];
            $bus_bp_array = maybe_unserialize($bus_bp_array);

            if($bus_bp_array) {
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
            }

            // Final
            mage_next_date_suggestion(false, true);
            if ($has_bus && $check_has_price) {

                mage_bus_search_item(false, $id);

            } else {
                echo '<div class="wbtm-warnig">';
                _e('This bus available only in the particular date. :) ', 'bus-ticket-booking-with-seat-reservation');
                echo '</div>';
            }
        } 
        ?>
    </div>


<?php
get_footer();