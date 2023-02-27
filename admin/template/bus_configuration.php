<div class="wbtm-item-row">
    <label class="item-label"><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ';
                                _e('No', 'bus-ticket-booking-with-seat-reservation'); ?></label>
    <input type="text" name="wbtm_bus_no" value="<?php echo $coach_no; ?>">
</div>
<div class="wbtm-item-row">
    <label class="item-label"><?php _e('Total Seat', 'bus-ticket-booking-with-seat-reservation'); ?></label>
    <input type="number" name="wbtm_total_seat" value="<?php echo $total_seat; ?>">
</div>

<div id="wbtm_show_dropping_time" class="wbtm-item-row">
    <label class="item-label"><?php _e("Show boarding time", "bus-ticket-booking-with-seat-reservation") ?></label>
    <input type="radio" id="show_boarding_time_no" name="show_boarding_time" <?php echo (($show_boarding_time == "no" || $show_boarding_time == '') ? " checked" : ""); ?> value="no"> <label for="show_boarding_time_no"> <?php _e('No', 'bus-ticket-booking-with-seat-reservation') ?></label>
    <input type="radio" id="show_boarding_time_yes" name="show_boarding_time" <?php echo ($show_boarding_time == "yes" ? " checked" : ""); ?> value="yes" style="margin-left: 20px"> <label for="show_boarding_time_yes"> <?php _e('Yes', 'bus-ticket-booking-with-seat-reservation') ?></label>
</div>

<div id="wbtm_show_dropping_time" class="wbtm-item-row">
    <label class="item-label"><?php _e("Show dropping time", "bus-ticket-booking-with-seat-reservation") ?></label>
    <input type="radio" id="show_dropping_time_no" name="show_dropping_time" <?php echo (($show_dropping_time == "no" || $show_dropping_time == '') ? " checked" : ""); ?> value="no"> <label for="show_dropping_time_no"> <?php _e('No', 'bus-ticket-booking-with-seat-reservation') ?></label>
    <input type="radio" id="show_dropping_time_yes" name="show_dropping_time" <?php echo ($show_dropping_time == "yes" ? " checked" : ""); ?> value="yes" style="margin-left: 20px"> <label for="show_dropping_time_yes"> <?php _e('Yes', 'bus-ticket-booking-with-seat-reservation') ?></label>
</div>

<?php do_action('mdpa_assign_driver', $as_driver); ?>
<!-- Assign Driver -->

<div class="wbtm-item-row wbtm-seat-type-conf">
    <label class="item-label"><?php _e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></label>
    <select name="wbtm_seat_type_conf" id="">
        <option value=""><?php _e('Select Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
        <option value="wbtm_seat_plan" <?php echo (($wbtm_seat_type_conf == 'wbtm_seat_plan') ? 'selected' : '') ?>>
            <?php _e('Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
        </option>
        <option value="wbtm_without_seat_plan" <?php echo (($wbtm_seat_type_conf == 'wbtm_without_seat_plan') ? 'selected' : '') ?>><?php _e('Without Seat
                    Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
        </option>
        <?php do_action('wbtm_seat_type_subscription', $wbtm_seat_type_conf) ?>
        <?php do_action('wbtm_seat_type_private', $wbtm_seat_type_conf) ?>
    </select>
</div>

<?php if (mage_bus_setting_value('same_bus_return_setting', 'disable') == 'enable') : ?>
    <div id="wbtm_same_bus_return" class="wbtm-item-row">
        <label class="item-label"><?php echo __("Return same", "bus-ticket-booking-with-seat-reservation") . ' ' . mage_bus_setting_value('bus_menu_label'); ?></label>
        <input type="radio" id="wbtm_same_bus_return_no" name="wbtm_general_same_bus_return" <?php echo (($wbtm_general_same_bus_return == "no" || $wbtm_general_same_bus_return == '') ? " checked" : ""); ?> value="no"> <label for="wbtm_same_bus_return_no"> <?php _e('No', 'bus-ticket-booking-with-seat-reservation') ?></label>
        <input type="radio" id="wbtm_same_bus_return_yes" name="wbtm_general_same_bus_return" <?php echo ($wbtm_general_same_bus_return == "yes" ? " checked" : ""); ?> value="yes" style="margin-left: 20px"> <label for="wbtm_same_bus_return_yes"> <?php _e('Yes', 'bus-ticket-booking-with-seat-reservation') ?></label>
    </div>
<?php endif; ?>

<div id="mtsa_city_zone" class="wbtm-item-row">
    <?php do_action('wbtm_subscription_route_type', $subscription_type); ?>
</div>

<?php if (has_action('wbtm_car_type')) : ?>
    <div id="mtpa_car_type" class="wbtm-item-row">
        <?php do_action('wbtm_car_type', $mtpa_car_type); ?>
    </div>
<?php endif; ?>

<div id="wbtm_zero_price_allow" class="wbtm-item-row">
    <label class="item-label"><?php _e("Zero Price Allow?", "bus-ticket-booking-with-seat-reservation") ?></label>
    <input type="radio" id="zero_price_allow_no" name="zero_price_allow" <?php echo (($zero_price_allow == "no" || $zero_price_allow == '') ? " checked" : ""); ?> value="no"> <label for="zero_price_allow_no"> <?php _e('No', 'bus-ticket-booking-with-seat-reservation') ?></label>
    <input type="radio" id="zero_price_allow_yes" name="zero_price_allow" <?php echo ($zero_price_allow == "yes" ? " checked" : ""); ?> value="yes" style="margin-left: 20px"> <label for="zero_price_allow_yes"> <?php _e('Yes', 'bus-ticket-booking-with-seat-reservation') ?></label>
</div>

<div class="wbtm-seat-plan-wrapper">
    <h2 class="wbtm-deck-title"><?php _e('Seat Plan for Lower Deck', 'bus-ticket-booking-with-seat-reservation') ?></h2>
    <div class="wbtm-lower-bus-seat-maker-wrapper">
        <div class="wbtm-control-part">
            <h3 class="wbtm-seat-title"><?php _e('Seat Maker', 'bus-ticket-booking-with-seat-reservation') ?></h3>
            <p class="wbtm-control-row">
                <strong><?php _e('Driver Seat Position', 'bus-ticket-booking-with-seat-reservation'); ?>
                    :</strong>
                <span>
                    <?php
                    if (array_key_exists('driver_seat_position', $values)) {
                        $position = $values['driver_seat_position'][0];
                    } else {
                        $position = 'left';
                    }
                    $wbtmmain->wbtm_get_driver_position($position);
                    ?>
                </span>
            </p>
            <p class="wbtm-control-row">
                <strong><?php _e('Total Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                <input type="number" value='<?php if (array_key_exists('wbtm_seat_cols', $values)) {
                                                echo $values['wbtm_seat_cols'][0];
                                            } ?>' name="seat_col" id='seat_col' style="width: 70px;" pattern="[1-9]*" inputmode="numeric" min="0" max="">
            </p>
            <p class="wbtm-control-row">
                <strong><?php _e('Total Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                <input type="number" value='<?php if (array_key_exists('wbtm_seat_rows', $values)) {
                                                echo $values['wbtm_seat_rows'][0];
                                            } ?>' name="seat_rows" id='seat_rows' style="width: 70px;" pattern="[1-9]*" inputmode="numeric" min="0" max="">
            </p>
            <p class="wbtm-control-row">
                <button id="create_seat_plan" class="create_seat_plan"><span class="dashicons dashicons-plus"></span><?php _e('Create Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
            </p>
        </div>


        <div class="wbtm-preview-part">
            <h3 class="wbtm-seat-title"><?php _e('Seat Preview', 'bus-ticket-booking-with-seat-reservation') ?></h3>
            <div id="seat_result" style="flex-basis: 100%;">
                <?php
                if (array_key_exists('wbtm_bus_seats_info', $values)) {
                    $old = $values['wbtm_bus_seats_info'][0];
                    $seatrows = $values['wbtm_seat_rows'][0];
                    $seatcols = $values['wbtm_seat_cols'][0];
                    $seats = unserialize($old);
                ?>
                    <!--suppress JSJQueryEfficiency -->
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            $('#add-seat-row').on('click', function() {
                                var row = $('.empty-row-seat.screen-reader-text').clone(true);
                                row.removeClass('empty-row-seat screen-reader-text');
                                row.insertBefore('#repeatable-fieldset-seat-one tbody>tr:last');
                                var qtt = parseInt($('#seat_rows').val(), 10);
                                $('#seat_rows').val(qtt + 1);
                                return false;
                            });
                            $('.remove-seat-row').on('click', function() {
                                $(this).parents('tr').remove();
                                var qtt = parseInt($('#seat_rows').val(), 10);
                                $('#seat_rows').val(qtt - 1);
                                return false;
                            });
                        });
                    </script>

                    <table class="wbtm-seat-table" id="repeatable-fieldset-seat-one">
                        <tbody>
                            <?php

                            foreach ($seats as $_seats) {
                            ?>
                                <tr>
                                    <?php
                                    for ($x = 1; $x <= $seatcols; $x++) {
                                        $text_field_name = "seat" . $x;
                                        $seat_type_name = "seat_types" . $x;
                                    ?>
                                        <td align="center">
                                            <input type="text" value="<?php echo $_seats[$text_field_name]; ?>" name="<?php echo $text_field_name; ?>[]" class="text">
                                            <?php wbtm_get_seat_type_list($seat_type_name, $post->ID); ?>

                                        </td>
                                    <?php
                                    }
                                    ?>
                                    <td align="center"><a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a>
                                        <input type="hidden" name="bus_seat_panels[]">
                                    </td>
                                </tr>
                            <?php } ?>
                            <!-- empty hidden one for jQuery -->
                            <tr class="empty-row-seat screen-reader-text">
                                <?php
                                for ($row = 1; $row <= $seatcols; $row++) {
                                    $seat_type_name = "seat_types" . $row;
                                ?>
                                    <td align="center">
                                        <input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
                                        <?php wbtm_get_seat_type_list($seat_type_name); ?>
                                    </td>
                                <?php } ?>
                                <td align="center"><a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a><input type="hidden" name="bus_seat_panels[]"></td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="add-seat-row" class="add-seat-row-btn">
                        <i class="fas fa-plus"></i> <?php _e('Add Seat Row', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </div>

                <?php } ?>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {

            jQuery("#create_seat_plan").click(function(e) {
                e.preventDefault();
                seat_col = jQuery("#seat_col").val().trim();
                seat_row = jQuery("#seat_rows").val().trim();
                jQuery.ajax({
                    type: 'POST',
                    // url:wbtm_ajax.wbtm_ajaxurl,
                    url: wbtm_ajaxurl,
                    data: {
                        "action": "wbtm_seat_plan",
                        "seat_col": seat_col,
                        "seat_row": seat_row
                    },
                    beforeSend: function() {
                        jQuery('#seat_result').html(
                            '<span class=search-text style="display:block;background:#ddd:color:#000:font-weight:bold;text-align:center">Creating Seat Plan...</span>'
                        );
                    },
                    success: function(data) {
                        jQuery('#seat_result').html(data);
                    }
                });
                return false;
            });

        });
    </script>


    <!-- Double Decker Seat Plan Here -->

    <h5 class="dFlex mpStyle">
        <span class="mR">SEAT PLAN FOR UPPER DECK</span>
        <label class="roundSwitchLabel">
            <input id="upper-desk-control" name="show_upper_desk" <?php echo ($show_upper_desk == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
            <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
        </label>
    </h5>

    <div style="display: <?php echo ($show_upper_desk == "yes" ? "block" : "none"); ?> " id="upper-desk">

        <h2 class="wbtm-deck-title"><?php _e('Seat Plan For Upper Deck', 'bus-ticket-booking-with-seat-reservation') ?></h2>

        <div class="wbtm-lower-bus-seat-maker-wrapper">
            <div class="wbtm-control-part">
                <h3 class="wbtm-seat-title"><?php _e('Seat Maker', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                <p class="wbtm-control-row">
                    <strong><?php _e('Total Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                    <input type="number" value='<?php if (array_key_exists('wbtm_seat_cols_dd', $values)) {
                                                    echo $values['wbtm_seat_cols_dd'][0];
                                                } ?>' name="seat_col_dd" id='seat_col_dd' style="width: 70px;" pattern="[1-9]*" inputmode="numeric" min="0" max="">
                </p>
                <p class="wbtm-control-row">
                    <strong><?php _e('Total Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                    <input type="number" value='<?php if (array_key_exists('wbtm_seat_rows_dd', $values)) {
                                                    echo $values['wbtm_seat_rows_dd'][0];
                                                } ?>' name="seat_rows_dd" id='seat_rows_dd' style="width: 70px;" pattern="[1-9]*" inputmode="numeric" min="0" max="">
                </p>
                <p class="wbtm-control-row" style="position: relative">
                    <strong><?php _e('Price Increase', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                    <input type="number" value='<?php if (array_key_exists('wbtm_seat_dd_price_parcent', $values)) {
                                                    echo $values['wbtm_seat_dd_price_parcent'][0];
                                                } ?>' name="wbtm_seat_dd_price_parcent" id='wbtm_seat_dd_price_parcent' style="width: 70px;"><span style="position: absolute;right: 0px;top: 15px;color: #555;font-weight:bold">%</span>
                </p>
                <p class="wbtm-control-row">
                    <button id="create_seat_plan_dd" class="create_seat_plan"><span class="dashicons dashicons-plus"></span><?php _e('Create Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </button>
                </p>
            </div>

            <div class="wbtm-preview-part">
                <h3 class="wbtm-seat-title"><?php _e('Seat Preview', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                <div id="seat_result_dd">
                    <?php
                    if (array_key_exists('wbtm_bus_seats_info_dd', $values)) {
                        $old = $values['wbtm_bus_seats_info_dd'][0];
                        $seatrows = $values['wbtm_seat_rows_dd'][0];
                        $seatcols = $values['wbtm_seat_cols_dd'][0];
                        $seats = unserialize($old);
                    ?>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                $('#add-seat-row-dd').on('click', function() {
                                    var row = $('.empty-row-seat-dd.screen-reader-text').clone(true);
                                    row.removeClass('empty-row-seat-dd screen-reader-text');
                                    row.insertBefore('#repeatable-fieldset-seat-one-dd tbody>tr:last');
                                    var qtt = parseInt($('#seat_rows_dd').val(), 10);
                                    $('#seat_rows_dd').val(qtt + 1);
                                    return false;
                                });
                                $('.remove-seat-row-dd').on('click', function() {
                                    $(this).parents('tr').remove();
                                    var qtt = parseInt($('#seat_rows_dd').val(), 10);
                                    $('#seat_rows_dd').val(qtt - 1);
                                    return false;
                                });
                            });
                        </script>
                        <table class="wbtm-seat-table" id="repeatable-fieldset-seat-one-dd" width="100%">
                            <tbody>
                                <?php
                                if (is_array($seats) && sizeof($seats) > 0) {
                                    foreach ($seats as $_seats) {
                                ?>
                                        <tr>
                                            <?php
                                            for ($x = 1; $x <= $seatcols; $x++) {
                                                $text_field_name = "dd_seat" . $x;
                                            ?>
                                                <td align="center"><input type="text" value="<?php echo $_seats[$text_field_name]; ?>" name="<?php echo $text_field_name; ?>[]" class="text">
                                                </td>
                                            <?php
                                            }
                                            ?>
                                            <td align="center"><a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                                <input type="hidden" name="bus_seat_panels_dd[]">
                                            </td>
                                        </tr>
                                <?php }
                                } ?>
                                <!-- empty hidden one for jQuery -->
                                <tr class="empty-row-seat-dd screen-reader-text">
                                    <?php
                                    for ($row = 1; $row <= $seatcols; $row++) {
                                    ?>
                                        <td align="center"><input type="text" value="" name="dd_seat<?php echo $row; ?>[]" class="text"></td>
                                    <?php } ?>
                                    <td align="center"><a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a><input type="hidden" name="bus_seat_panels_dd[]"></td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="add-seat-row-dd" class="add-seat-row-btn"><i class="fas fa-plus"></i> <?php _e('Add Seat Row', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>


        <script type="text/javascript">
            jQuery("#create_seat_plan_dd").click(function(e) {
                e.preventDefault();
                // alert('Yes');
                seat_col = jQuery("#seat_col_dd").val().trim();
                seat_row = jQuery("#seat_rows_dd").val().trim();
                jQuery.ajax({
                    type: 'POST',
                    // url:wbtm_ajax.wbtm_ajaxurl,
                    url: wbtm_ajaxurl,
                    data: {
                        "action": "wbtm_seat_plan_dd",
                        "seat_col": seat_col,
                        "seat_row": seat_row
                    },
                    beforeSend: function() {
                        jQuery('#seat_result_dd').html(
                            '<span class=search-text style="display:block;background:#ddd:color:#000:font-weight:bold;text-align:center">Creating Seat Plan...</span>'
                        );
                    },
                    success: function(data) {
                        jQuery('#seat_result_dd').html(data);
                    }
                });
                return false;
            });
        </script>

    </div>
    <!-- double decker end -->
</div>