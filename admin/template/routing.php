<div class="bus-stops-wrapper">
    <div class="bus-stops-left-col">
        <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <table class="repeatable-fieldset">
            <thead>
            <tr>
                <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th width="30px"><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <?php if ($route_disable_switch === 'on') : ?>
                    <th><?php _e('Disable', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody class="bd-point">
            <?php
            if ($wbbm_bus_bp) :
                $count = 0;
                foreach ($wbbm_bus_bp as $field) {
                    ?>
                    <tr>
                        <td align="center">
                            <select name="wbtm_bus_bp_stops_name[]" class='seat_type bus_stop_add_option'>
                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($terms as $term) {?>
                                    <option value="<?php echo $term->name; ?>" <?php echo ($term->name == $field['wbtm_bus_bp_stops_name'])?'Selected':'' ?>><?php echo $term->name; ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td align="center" width="30px">
                            <input type="text" data-clocklet name='wbtm_bus_bp_start_time[]' value="<?php if ($field['wbtm_bus_bp_start_time'] != '') echo esc_attr($field['wbtm_bus_bp_start_time']); ?>" class="text" placeholder="15:00">
                        </td>
                        <td align="center"><a class="button wbtm-remove-row-t" href="#"><i class="fas fa-minus-circle"></i>
                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a></td>
                        <?php if ($route_disable_switch === 'on') : ?>
                            <td class="route_disable_container">
                                <label class="switch route-disable-switch">
                                    <input type="checkbox" name="wbtm_bus_bp_start_disable[<?php echo $field['wbtm_bus_bp_stops_name'] ?>]" <?php echo $field['wbtm_bus_bp_start_disable'] === 'yes' ? "checked" : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php
                    $count++;
                }
            else :
                // show a blank one
            endif;
            ?>



                <tr style="display: none" class="more-bd-point">
                    <td align="center">
                        <select name="wbtm_bus_bp_stops_name[]" class='seat_type wbtm_boarding_point bus_stop_add_option'>
                            <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php foreach ($terms as $term) {?>
                                <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td align="center"><input type="text" data-clocklet name='wbtm_bus_bp_start_time[]' value="" class="text" placeholder="15:00"></td>
                    <td align="center"><a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a></td>
                    <?php if ($route_disable_switch === 'on') : ?>
                        <td class="route_disable_container">
                            <label class="switch route-disable-switch">
                                <input type="checkbox" name="wbtm_bus_bp_start_disable[]">
                                <span class="slider round"></span>
                            </label>
                        </td>
                    <?php endif; ?>
                </tr>





            </tbody>
        </table>

        <a class="button wbtom-tb-repeat-btn add-more-bd-point" href="#">
            <i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
        </a>


    </div>

    <div class="bus-stops-right-col">
        <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <table class="repeatable-fieldset">
            <tr>
                <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            </tr>
            <tbody class="bd-point">
            <?php
            if ($wbtm_bus_next_stops) :
                $count = 0;
                foreach ($wbtm_bus_next_stops as $field) {
                    ?>
                    <tr>
                        <td align="center">
                            <select name="wbtm_bus_next_stops_name[]" class='seat_type wbtm_dropping_point bus_stop_add_option'>
                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php
                                foreach ($terms as $term) { ?>
                                    <option value="<?php echo $term->name; ?>" <?php echo ($term->name == $field['wbtm_bus_next_stops_name'])?'Selected':'' ?> ><?php echo $term->name; ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time[]' value="<?php if ($field['wbtm_bus_next_end_time'] != '') echo esc_attr($field['wbtm_bus_next_end_time']); ?>" class="text" placeholder="15:00"></td>
                        <td align="center"><a class="button wbtm-remove-row-t" href="#"><i class="fas fa-minus-circle"></i>
                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a></td>
                    </tr>
                    <?php
                    $count++;
                }
            else :
                // show a blank one
            endif;
            ?>

            <!-- empty hidden one for jQuery -->

            <!-- empty hidden one for jQuery -->
            <tr style="display: none" class="more-bd-point">
                <td align="center">
                    <select name="wbtm_bus_next_stops_name[]" class='seat_type bus_stop_add_option'>
                        <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <?php
                        foreach ($terms as $term) {
                            ?>
                            <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time[]' value="" class="text" placeholder="15:00"></td>
                <td align="center">
                    <a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </a>
                </td>
            </tr>


            </tbody>
        </table>
        <a class="button add-more-bd-point wbtom-tb-repeat-btn" href="#">
            <i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
        </a>
    </div>

</div>


<!-- Return Routing -->
<div class="wbtm-only-for-return-enable">
    <h3 class="wbtm-single-return-header"><?php _e('Return Route', 'bus-ticket-booking-with-seat-reservation') ?>:</h3>
    <div class="bus-stops-wrapper">
        <div class="bus-stops-left-col">
            <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <table class="repeatable-fieldset">
                <thead>
                <tr>
                    <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th width="30px"><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <?php if ($route_disable_switch === 'on') : ?>
                        <th><?php _e('Disable', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php
                if ($wbbm_bus_bp_return) :
                    $count = 0;
                    foreach ($wbbm_bus_bp_return as $field) {
                        ?>
                        <tr>
                            <td align="center">
                                <select name="wbtm_bus_bp_stops_name_return[]" class='seat_type wbtm_boarding_point bus_stop_add_option'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                        ?>
                                        <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_bp_stops_name']) {
                                            echo "Selected";
                                        } ?>><?php echo $term->name; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td align="center" width="30px"><input type="text" data-clocklet name='wbtm_bus_bp_start_time_return[]' value="<?php if ($field['wbtm_bus_bp_start_time'] != '') echo esc_attr($field['wbtm_bus_bp_start_time']); ?>" class="text" placeholder="15:00"></td>
                            <td align="center"><a class="button wbtm-remove-row-t" href="#"><i class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a></td>
                            <?php if ($route_disable_switch === 'on') : ?>
                                <td class="route_disable_container">
                                    <label class="switch route-disable-switch">
                                        <input type="checkbox" name="wbtm_bus_bp_start_disable[<?php echo $field['wbtm_bus_bp_stops_name'] ?>]" <?php echo $field['wbtm_bus_bp_start_disable'] === 'yes' ? "checked" : '' ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php
                        $count++;
                    }
                else :
                    // show a blank one
                endif;
                ?>

                <!-- empty hidden one for jQuery -->
                <tr class="mtsa-empty-row-t">
                    <td align="center">
                        <select name="wbtm_bus_bp_stops_name_return[]" class='seat_type wbtm_boarding_point bus_stop_add_option'>
                            <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php
                            foreach ($terms as $term) {
                                ?>
                                <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                    <td align="center"><input type="text" data-clocklet name='wbtm_bus_bp_start_time_return[]' value="" class="text" placeholder="15:00"></td>
                    <td align="center"><a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a></td>
                    <?php if ($route_disable_switch === 'on') : ?>
                        <td class="route_disable_container">
                            <label class="switch route-disable-switch">
                                <input type="checkbox" name="wbtm_bus_bp_start_disable[]">
                                <span class="slider round"></span>
                            </label>
                        </td>
                    <?php endif; ?>
                </tr>


                </tbody>
            </table>
            <a class="button wbtom-tb-repeat-btn" href="#"><i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
            </a>
        </div>

        <div class="bus-stops-right-col">
            <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <table class="repeatable-fieldset">
                <tr>
                    <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                </tr>
                <tbody>
                <?php
                if ($wbtm_bus_next_stops_return) :
                    $count = 0;
                    foreach ($wbtm_bus_next_stops_return as $field) {
                        ?>
                        <tr>
                            <td align="center">
                                <select name="wbtm_bus_next_stops_name_return[]" class='seat_type bus_stop_add_option'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                        ?>
                                        <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_next_stops_name']) {
                                            echo "Selected";
                                        } ?>><?php echo $term->name; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time_return[]' value="<?php if ($field['wbtm_bus_next_end_time'] != '') echo esc_attr($field['wbtm_bus_next_end_time']); ?>" class="text" placeholder="15:00"></td>
                            <td align="center"><a class="button wbtm-remove-row-t" href="#"><i class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a></td>
                        </tr>
                        <?php
                        $count++;
                    }
                else :
                    // show a blank one
                endif;
                ?>

                <!-- empty hidden one for jQuery -->
                <tr class="mtsa-empty-row-t">
                    <td align="center">
                        <select name="wbtm_bus_next_stops_name_return[]" class='seat_type'>
                            <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php
                            foreach ($terms as $term) {
                                ?>
                                <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                    <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time_return[]' value="" class="text" placeholder="15:00"></td>
                    <td align="center"><a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a></td>
                </tr>
                </tbody>
            </table>
            <a class="button wbtom-tb-repeat-btn" href="#"><i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
            </a>
        </div>

    </div>
</div>