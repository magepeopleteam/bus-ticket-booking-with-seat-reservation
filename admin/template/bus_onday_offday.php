<div class="wbtm-content-wrapper">
    <div class="wbtm-content-inner">
        <div class="wbtm-sec-row">
            <h5 class="dFlex mpStyle">
                <span class="pb-10"><b>Enable Operation on day settings :</b> If you want to operate bus on a certain date please enable it and configure operational day.</span>
                <label class="roundSwitchLabel">
                    <input id="operational-on-day-control" name="show_operational_on_day" <?php echo ($show_operational_on_day == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
                    <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
                </label>
            </h5>
            <div style="display: <?php echo ($show_operational_on_day == "yes" ? "block" : "none"); ?>" class="wbtm-ondates-wrapper operational-on-day">
                <label for=""><?php _e('Operational Onday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                <div class="wbtm-ondates-inner">
                    <input type="text" name="wbtm_bus_on_dates" value="<?php echo $ondates; ?>">
                </div>
            </div>

        </div>


        <h5 class="dFlex mpStyle">
            <span class="pb-10"><b>Enable offday settings</b> If you need to keep bus off for a certain date please enable it and configure offday</span>
            <label class="roundSwitchLabel">
                <input id="off-day-control" name="show_off_day" <?php echo ($show_off_day == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
                <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
            </label>
        </h5>

        <div style="display: <?php echo ($show_off_day == "yes" ? "block" : "none"); ?>" class="wbtm-dayoff-wrapper off-day">

            <div class="wbtm-offdates-wrapper">
                <label for=""><?php _e('Operational Offday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                <div class="wbtm-offdates-inner">
                    <table class="repeatable-fieldset-offday">
                        <tr>
                            <th><?php _e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th></th>
                        </tr>
                        <tbody>
                        <?php
                        if ($wbtm_offday_schedule) :
                            $count = 0;
                            foreach ($wbtm_offday_schedule as $field) {
                                ?>
                                <tr class="">
                                    <td align="left"><input type="text" id="<?php echo 'db_offday_from_' . $count; ?>" class="repeatable-offday-from-field" name='wbtm_od_offdate_from[]' placeholder="2020-12-31" value="<?php echo $field['from_date'] ?>" /></td>

                                    <td align="left"><input type="text" id="<?php echo 'db_offday_to_' . $count; ?>" class="repeatable-offday-to-field" name='wbtm_od_offdate_to[]' placeholder="2020-12-31" value="<?php echo $field['to_date'] ?>" /></td>

                                    <td align="left">
                                        <a class="button remove-bp-row" href="#">
                                            <i class="fas fa-minus-circle"></i>
                                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a>
                                    </td>
                                </tr>

                                <script>
                                    (function($) {
                                        setTimeout(function() {
                                            $("#db_offday_from_<?php echo $count ?>").datepicker({
                                                dateFormat: "yy-mm-dd",
                                                minDate: 0
                                            });
                                            $("#db_offday_to_<?php echo $count ?>").datepicker({
                                                dateFormat: "yy-mm-dd",
                                                minDate: 0
                                            });
                                        }, 400);
                                    })(jQuery)
                                </script>
                                <?php

                                $count++;
                            }
                        else :
                            // show a blank one
                        endif;
                        ?>

                        <!-- empty hidden one for jQuery -->
                        <tr class="empty-row-offday screen-reader-text">
                            <td align="left"><input type="text" class="repeatable-offday-from-field" name='wbtm_od_offdate_from[]' placeholder="2020-12-31" />
                            </td>

                            <td align="left"><input type="text" class="repeatable-offday-to-field" name='wbtm_od_offdate_to[]' placeholder="2020-12-31" /></td>

                            <td align="left">
                                <a class="button remove-bp-row" href="#">
                                    <i class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <p style="margin: 0 0 0 4px;border-radius: 5px;">
                        <a class="button add-offday-row" href="#"><i class="fas fa-plus"></i>
                            <?php _e('Add More offdate', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a>
                    </p>
                </div>
            </div>


            <label for="">Offdays</label>
            <div class='wbtm-dayoff-inner'>
                <label for='sun'>
                    <input type="checkbox" id='sun' style="text-align: left;width: auto;" name="offday_sun" value='yes' <?php if (array_key_exists('offday_sun', $values)) {
                        if ($values['offday_sun'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?> /> <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='mon'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_mon" value='yes' id='mon' <?php if (array_key_exists('offday_mon', $values)) {
                        if ($values['offday_mon'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='tue'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_tue" value='yes' id='tue' <?php if (array_key_exists('offday_tue', $values)) {
                        if ($values['offday_tue'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='wed'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_wed" value='yes' id='wed' <?php if (array_key_exists('offday_wed', $values)) {
                        if ($values['offday_wed'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='thu'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_thu" value='yes' id='thu' <?php if (array_key_exists('offday_thu', $values)) {
                        if ($values['offday_thu'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='fri'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_fri" value='yes' id='fri' <?php if (array_key_exists('offday_fri', $values)) {
                        if ($values['offday_fri'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='sat'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="offday_sat" value='yes' id='sat' <?php if (array_key_exists('offday_sat', $values)) {
                        if ($values['offday_sat'][0] == 'yes') {
                            echo 'Checked';
                        }
                    } ?>> <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
            </div>
        </div>
    </div>
</div>

<!-- Return -->
<div class="wbtm-only-for-return-enable">
    <div class="wbtm-content-wrapper">
        <h3 class="wbtm-single-return-header"><?php _e('Return', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <div class="wbtm-content-inner">
            <div class="wbtm-sec-row">
                <div class="wbtm-ondates-wrapper">
                    <label for=""><?php _e('Operational Onday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                    <div class="wbtm-ondates-inner">
                        <input type="text" name="wbtm_bus_on_dates_return" value="<?php echo $ondates_return; ?>">
                    </div>
                </div>
                <div class="wbtm-offdates-wrapper">
                    <label for=""><?php _e('Operational Offday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                    <div class="wbtm-offdates-inner">
                        <table class="repeatable-fieldset-offday">
                            <thead>
                            <tr>
                                <th><?php _e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th class="th-time"><?php _e('From Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th><?php _e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th class="th-time"><?php _e('To Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            if ($wbtm_offday_schedule_return) :
                                $count = 0;
                                foreach ($wbtm_offday_schedule_return as $field) {
                                    ?>
                                    <tr class="">
                                        <td align="left"><input type="text" id="<?php echo 'db_offday_from_' . $count . '_r'; ?>" class="repeatable-offday-from-field" name='wbtm_od_offdate_from_return[]' placeholder="2020-12-31" value="<?php echo $field['from_date'] ?>" /></td>
                                        <td align="left"><input type="text" class="repeatable-offtime-from-field" name='wbtm_od_offtime_from_return[]' placeholder="09:00 am" value="<?php echo $field['from_time'] ?>" /></td>
                                        <td align="left"><input type="text" id="<?php echo 'db_offday_to_' . $count . '_r'; ?>" class="repeatable-offday-to-field" name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31" value="<?php echo $field['to_date'] ?>" /></td>
                                        <td align="left"><input type="text" class="repeatable-offtime-to-field" name='wbtm_od_offtime_to_return[]' placeholder="09:59 pm" value="<?php echo $field['to_time'] ?>" /></td>
                                        <td align="left">
                                            <a class="button remove-bp-row" href="#">
                                                <i class="fas fa-minus-circle"></i>
                                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </a>
                                        </td>
                                    </tr>

                                    <script>
                                        (function($) {
                                            setTimeout(function() {
                                                $("#db_offday_from_<?php echo $count . '_r' ?>").datepicker({
                                                    dateFormat: "yy-mm-dd",
                                                    minDate: 0
                                                });
                                                $("#db_offday_to_<?php echo $count . '_r' ?>").datepicker({
                                                    dateFormat: "yy-mm-dd",
                                                    minDate: 0
                                                });
                                            }, 400);
                                        })(jQuery)
                                    </script>
                                    <?php

                                    $count++;
                                }
                            else :
                                // show a blank one
                            endif;
                            ?>

                            <!-- empty hidden one for jQuery -->
                            <tr class="empty-row-offday screen-reader-text">
                                <td align="left"><input type="text" class="repeatable-offday-from-field" name='wbtm_od_offdate_from_return[]' placeholder="2020-12-31" />
                                </td>
                                <td align="left"><input type="text" class="repeatable-offtime-from-field" name='wbtm_od_offtime_from_return[]' placeholder="09:00 am" /></td>
                                <td align="left"><input type="text" class="repeatable-offday-to-field" name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31" /></td>
                                <td align="left"><input type="text" class="repeatable-offtime-to-field" name='wbtm_od_offtime_to_return[]' placeholder="09:59 pm" /></td>
                                <td align="left">
                                    <a class="button remove-bp-row" href="#">
                                        <i class="fas fa-minus-circle"></i>
                                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <p style="margin: 0 0 0 4px;border-radius: 5px;">
                            <a class="button add-offday-row" href="#"><i class="fas fa-plus"></i>
                                <?php _e('Add More offdate', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="wbtm-dayoff-wrapper">
                <label for="">Offdays</label>
                <div class='wbtm-dayoff-inner'>
                    <label for='sun_return'>
                        <input type="checkbox" id='sun_return' style="text-align: left;width: auto;" name="offday_sun_return" value='yes' <?php if (array_key_exists('offday_sun_return', $values)) {
                            if ($values['offday_sun_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?> /> <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='mon_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_mon_return" value='yes' id='mon_return' <?php if (array_key_exists('offday_mon_return', $values)) {
                            if ($values['offday_mon_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='tue_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_tue_return" value='yes' id='tue_return' <?php if (array_key_exists('offday_tue_return', $values)) {
                            if ($values['offday_tue_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='wed_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_wed_return" value='yes' id='wed_return' <?php if (array_key_exists('offday_wed_return', $values)) {
                            if ($values['offday_wed_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='thu_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_thu_return" value='yes' id='thu_return' <?php if (array_key_exists('offday_thu_return', $values)) {
                            if ($values['offday_thu_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='fri_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_fri_return" value='yes' id='fri_return' <?php if (array_key_exists('offday_fri_return', $values)) {
                            if ($values['offday_fri_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                    <label for='sat_return'>
                        <input type="checkbox" style="text-align: left;width: auto;" name="offday_sat_return" value='yes' id='sat_return' <?php if (array_key_exists('offday_sat_return', $values)) {
                            if ($values['offday_sat_return'][0] == 'yes') {
                                echo 'Checked';
                            }
                        } ?>> <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Return End -->