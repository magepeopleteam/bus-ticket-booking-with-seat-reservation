<?php
function wbtm_seat_global($b_start, $date, $type = '')
{
    global $wbtmmain;

    $seat_panel_settings = get_option('wbtm_bus_settings');
    $driver_image = $seat_panel_settings['diriver_image'] ? wp_get_attachment_url($seat_panel_settings['diriver_image'], 'full') : WBTM_PLUGIN_URL . '/public/images/driver-default.png';

    $blank_seat_image = $seat_panel_settings['seat_blank_image'] ? wp_get_attachment_url($seat_panel_settings['seat_blank_image'], 'full') : WBTM_PLUGIN_URL . '/public/css/images/seat-empty.png';

    $blank_active_image = $seat_panel_settings['seat_active_image'] ? wp_get_attachment_url($seat_panel_settings['seat_active_image'], 'full') : WBTM_PLUGIN_URL . '/public/css/images/seat-selected.png';

    $blank_booked_image = $seat_panel_settings['seat_booked_image'] ? wp_get_attachment_url($seat_panel_settings['seat_booked_image'], 'full') : WBTM_PLUGIN_URL . '/public/css/images/seat-booked.png';

    $blank_sold_image = $seat_panel_settings['seat_sold_image'] ? wp_get_attachment_url($seat_panel_settings['seat_sold_image'], 'full') : WBTM_PLUGIN_URL . '/public/css/images/seat-sold.png';

    $useer_deck_title = $seat_panel_settings['useer_deck_title'] ? $seat_panel_settings['useer_deck_title'] : __('Upper Deck', 'bus-ticket-booking-with-seat-reservation');

    ?>
    <style>
        /* html body .admin-bus-details td a {
         height: 50px;
     } */
        .blank_seat {
            background: url(<?php echo $blank_seat_image; ?>) no-repeat center center !important;
            min-height: 44px;
        }

        .seat_booked, .seat_booked:hover {
            background: url(<?php echo $blank_active_image; ?>) no-repeat center center !important;
            min-height: 44px;
        }

        span.booked-seat {
            background: url(<?php echo $blank_booked_image; ?>) no-repeat center center !important;
            min-height: 44px;
        }

        span.confirmed-seat {
            background: url(<?php echo $blank_sold_image; ?>) no-repeat center center !important;
            min-height: 44px;
        }
    </style>

    <?php

    if ($type && $type == 'dd') {

        $seats = get_post_meta(get_the_id(), 'wbtm_bus_seats_info_dd', true);
        $current_driver_position = get_post_meta(get_the_id(), 'driver_seat_position', true);
        $seatrows = get_post_meta(get_the_id(), 'wbtm_seat_rows_dd', true);
        $seatcols = get_post_meta(get_the_id(), 'wbtm_seat_rows_dd', true);
        if ($current_driver_position) {
            $current_driver = $current_driver_position;
        } else {
            $current_driver = 'driver_right';
        }

        $start = isset($_GET['bus_start_route']) ? strip_tags($_GET['bus_start_route']) : '';
        $end = isset($_GET['bus_end_route']) ? strip_tags($_GET['bus_end_route']) : '';
        $bus_bp_array = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
        $bus_dp_array = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
        $bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
        $dp_time = $wbtmmain->wbtm_get_bus_end_time($end, $bus_dp_array);
        if (is_array($seats) && sizeof($seats) > 0) {
            ?>

            <div class="bus-seat-panel-dd">
                <h6><?php echo $useer_deck_title; ?></h6>
                <table class="bus-seats" width="300" border="1" style="width: 211px;
    border: 0px solid #ddd;">
                    <?php
                    foreach ($seats as $_seats) {
                        ?>
                        <tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
                            <?php
                            for ($x = 1; $x <= $seatcols; $x++) {
                                $text_field_name = "dd_seat" . $x;
                                $seat_name = $_seats[$text_field_name];
                                $get_seat_status = $wbtmmain->wbtm_get_seat_status($_seats[$text_field_name], $date, get_the_id(), $b_start, $end);
                                if ($get_seat_status) {
                                    $seat_status = $get_seat_status;
                                } else {
                                    $seat_status = 0;
                                }
                                ?>
                                <td align="center">
                                    <?php

                                    if ($_seats[$text_field_name]) { ?>
                                        <?php if ($seat_status == 1) { ?> <span
                                                class="booked-seat"><?php echo $seat_name; ?></span>
                                        <?php } elseif ($seat_status == 2) { ?><span
                                                class="confirmed-seat"><?php echo $seat_name; ?></span>
                                        <?php } else { ?>
                                            <a data-seat='<?php echo $_seats[$text_field_name]; ?>'
                                               id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
                                               data-sclass='Economic'
                                               class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
                                                <?php echo $_seats[$text_field_name]; ?></a>
                                        <?php }
                                    } ?>
                                </td>
                                <?php
                            }
                            ?>
                        </tr>
                    <?php } ?>
                </table>
            </div>
            <?php

        }
    } else {
        $seats = get_post_meta(get_the_id(), 'wbtm_bus_seats_info', true);
        $current_driver_position = get_post_meta(get_the_id(), 'driver_seat_position', true);
        $seatrows = get_post_meta(get_the_id(), 'wbtm_seat_rows', true);
        $seatcols = get_post_meta(get_the_id(), 'wbtm_seat_cols', true);
        if ($current_driver_position) {
            $current_driver = $current_driver_position;
        } else {
            $current_driver = 'driver_right';
        }

        $start = isset($_GET['bus_start_route']) ? strip_tags($_GET['bus_start_route']) : '';
        $end = isset($_GET['bus_end_route']) ? strip_tags($_GET['bus_end_route']) : '';
        $bus_bp_array = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
        $bus_dp_array = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
        $bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
        $dp_time = $wbtmmain->wbtm_get_bus_end_time($end, $bus_dp_array);
        ?>

        <div class="bus-seat-panel-ss">
            <div style='border: 1px solid #ddd;padding: 5px;width:204px; text-align:<?php if ($current_driver == 'driver_left') {
                echo 'left';
            } else {
                echo 'right';
            } ?>'>
                <img src="<?php echo $driver_image; ?>" alt="">
            </div>
			<?php
                // upper deck
                $seats_dd = get_post_meta(get_the_id(), 'wbtm_bus_seats_info_dd', true);
                $upper_deck = (!empty(get_option('wbtm_bus_settings')) ? get_option('wbtm_bus_settings')['useer_deck_title'] : '');
                if(!empty($seats_dd)) {
                    echo '<strong style="width:216px;background:#f1f1f1;text-align: center;display: block;font-size: 11px;color: #4CAF50;">'.__('Lower Deck', 'bus-ticket-booking-with-seat-reservation').'</strong>';
                }
            ?>
            <table class="bus-seats" width="300" border="1" style="width: 220px;margin-left:-2px;
    border: 0px solid #ddd;">
                <?php
                foreach ($seats as $_seats) {
                    ?>
                    <tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
                        <?php
                        for ($x = 1; $x <= $seatcols; $x++) {
                            $text_field_name = "seat" . $x;
                            $seat_name = $_seats[$text_field_name];
                            $get_seat_status = $wbtmmain->wbtm_get_seat_status($_seats[$text_field_name], $date, get_the_id(), $b_start, $end);
                            if ($get_seat_status) {
                                $seat_status = $get_seat_status;
                            } else {
                                $seat_status = 0;
                            }
                            ?>
                            <td align="center" class="mage-admin-bus-seat <?php echo ( $_seats[$text_field_name] == '' ? 'bus-col-divider' : '' ) ?>">
                                <?php
                                if ($_seats[$text_field_name]) { ?>
                                    <?php if ($seat_status == 1) { ?> <span
                                            class="booked-seat"><?php echo $seat_name; ?></span>
                                    <?php } elseif ($seat_status == 2) { ?><span
                                            class="confirmed-seat"><?php echo $seat_name; ?></span>
                                    <?php } else { ?>
                                        <a data-seat='<?php echo $_seats[$text_field_name]; ?>' data-seat-pos="lower"
                                           id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
                                           data-sclass='Economic'
                                           class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
                                            <?php echo $_seats[$text_field_name]; ?></a>
                                            <?php mage_bus_passenger_type_admin(false, false) ?>
                                    <?php }
                                } ?>
                            </td>
                            <?php
                        }
                        ?>
                    </tr>
                <?php } ?>
            </table>
        <?php
            
            $seat_col_dd = get_post_meta(get_the_id(), 'wbtm_seat_cols_dd', true);
            
            if( is_array($seats_dd) && sizeof($seats_dd) > 0 ) : 
            if(!empty($seats_dd)) {
                echo '<strong style="width: 216px;background:#f1f1f1;text-align: center;display: block;font-size: 11px;color: #4CAF50;">'.__($upper_deck, 'bus-ticket-booking-with-seat-reservation').'</strong>';
            }
            ?>
            <table class="bus-seats" width="300" border="1" style="width: 220px;margin-left:-2px;
    border: 0px solid #ddd;">

                <?php
                foreach( $seats_dd as $_seats ) : ?>
                <tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
                    <?php for ($x = 1; $x <= $seat_col_dd; $x++) : 
                        
                        $text_field_name = "dd_seat" . $x;
                        $seat_name = $_seats[$text_field_name];
                        $get_seat_status = $wbtmmain->wbtm_get_seat_status($_seats[$text_field_name], $date, get_the_id(), $b_start, $end);
                        if ($get_seat_status) {
                            $seat_status = $get_seat_status;
                        } else {
                            $seat_status = 0;
                        }
                    
                    ?>
                    <td align="center" class="mage-admin-bus-seat <?php echo ( $_seats[$text_field_name] == '' ? 'bus-col-divider' : '' ) ?>">
                        
                        <?php 
                        if( $_seats[$text_field_name] ) : ?>

                        <?php if ($seat_status == 1) { ?> <span
                                            class="booked-seat"><?php echo $seat_name; ?></span>
                        <?php } elseif ($seat_status == 2) { ?><span
                                class="confirmed-seat"><?php echo $seat_name; ?></span>
                        <?php } else { ?>
                            <a data-seat='<?php echo $_seats[$text_field_name]; ?>' data-seat-pos="upper"
                                id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
                                data-sclass='Economic'
                                class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
                                <?php echo $_seats[$text_field_name]; ?></a>
                                <?php mage_bus_passenger_type_admin(false, true) ?>
                        <?php } ?>

                        <?php endif; ?>

                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php endif; ?>
        </div>
        <?php


    }

}

?>