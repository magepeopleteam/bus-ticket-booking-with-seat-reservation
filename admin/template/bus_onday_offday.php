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
                    <input type="text" name="wbtm_bus_on_dates" value="<?php echo $ondates; ?>" readonly>
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
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='7' id='sun' <?php echo ((in_array(7, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='mon'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='1' id='mon' <?php echo ((in_array(1, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='tue'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='2' id='tue' <?php echo ((in_array(2, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='wed'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='3' id='wed' <?php echo ((in_array(3, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='thu'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='4' id='thu' <?php echo ((in_array(4, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='fri'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='5' id='fri' <?php echo ((in_array(5, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                </label>
                <label for='sat'>
                    <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday[]" value='6' id='sat' <?php echo ((in_array(6, $weekly_offday)) ? 'Checked' : '') ?>>
                    <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
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
            <!-- retirn on day-->
            <div class="wbtm-sec-row">
                <h5 class="dFlex mpStyle">
                    <span class="pb-10"><b><?php _e('Enable offday settings', 'bus-ticket-booking-with-seat-reservation'); ?></b><?php _e('If you need to keep bus off for a certain date please enable it and configure offday', 'bus-ticket-booking-with-seat-reservation'); ?> </span>
                    <label class="roundSwitchLabel">
                        <input id="return-operational-on-day-control" name="return_show_operational_on_day" <?php echo ($return_show_operational_on_day == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
                        <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
                    </label>
                </h5>

                <div style="display: <?php echo ($return_show_operational_on_day == "yes" ? "block" : "none"); ?>" class="wbtm-ondates-wrapper return-operational-on-day">
                    <label for=""><?php _e('Operational Onday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                    <div class="wbtm-ondates-inner">
                        <input type="text" name="wbtm_bus_on_dates_return" value="<?php echo $ondates_return; ?>" readonly>
                    </div>
                </div>
            </div>
            <!--return off day-->
            <div class="">
                <h5 class="dFlex mpStyle">
                    <span class="pb-10"><b><?php _e('Enable offday settings', 'bus-ticket-booking-with-seat-reservation'); ?></b><?php _e('If you need to keep bus off for a certain date please enable it and configure offday', 'bus-ticket-booking-with-seat-reservation'); ?> </span>
                    <label class="roundSwitchLabel">
                        <input id="return-off-day-control" name="return_show_off_day" <?php echo ($return_show_off_day == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
                        <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
                    </label>
                </h5>

                <div style="display: <?php echo ($return_show_off_day == "yes" ? "block" : "none"); ?>" class="wbtm-dayoff-wrapper return-off-day">
                    <div class="wbtm-offdates-wrapper">
                        <label for=""><?php _e('Operational Offday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <div class="wbtm-offdates-inner">
                            <table class="repeatable-fieldset-offday">
                                <thead>
                                    <tr>
                                        <th><?php _e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                        <th><?php _e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
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
                                                <td align="left"><input type="text" id="<?php echo 'db_offday_to_' . $count . '_r'; ?>" class="repeatable-offday-to-field" name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31" value="<?php echo $field['to_date'] ?>" /></td>
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
                                        <td align="left"><input type="text" class="repeatable-offday-from-field" name='wbtm_od_offdate_from_return[]' placeholder="2020-12-31" /></td>
                                        <td align="left"><input type="text" class="repeatable-offday-to-field" name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31" /></td>
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

                    <div class="wbtm-dayoff-wrapper">
                        <label for="">Offdays</label>

                        <div class='wbtm-dayoff-inner'>
                            <label for='r_sun'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='7' id='r_sun' <?php echo ((in_array(7, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_mon'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='1' id='r_mon' <?php echo ((in_array(1, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_tue'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='2' id='r_tue' <?php echo ((in_array(2, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_wed'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='3' id='r_wed' <?php echo ((in_array(3, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_thu'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='4' id='r_thu' <?php echo ((in_array(4, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_fri'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='5' id='r_fri' <?php echo ((in_array(5, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='r_sat'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="weekly_offday_return[]" value='6' id='r_sat' <?php echo ((in_array(6, $weekly_offday_return)) ? 'Checked' : '') ?>>
                                <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                        </div>



                    </div>
                </div>
            </div>
            <!--end return off day-->
        </div>
    </div>
</div>
<!-- Return End -->