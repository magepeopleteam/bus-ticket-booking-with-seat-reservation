<div class="mp_tab_item" data-tab-item="#wbtm_ticket_panel" style="display:block;">
    <h3><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <hr />
    <?php $this->wbtm_bus_ticket_type(); ?>
</div>
<div class="mp_tab_item" data-tab-item="#wbtm_routing">
    <div class="row">
        <div class="col-md-6">
            <div class="wbtm_tab_content_heading">
                <h3><?php esc_html_e(' Routing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <div class="wbtm-section-info">
                    <span><i class="fas fa-info-circle"></i></span>
                    <div class="wbtm-section-info-content">
                        <?php echo $routing_info; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mpStyle">
                <div class="mpPopup" data-popup="#wbtm_route_popup">
                    <div class="popupMainArea">
                        <div class="popupHeader">
                            <h4>
                                <?php esc_html_e('Add New Bus Stop', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </h4>
                            <span class="fas fa-times popupClose"></span>
                        </div>
                        <div class="popupBody bus-stop-form">
                            <h6 class="textSuccess success_text" style="display: none;">Added Succesfully</h6>
                            <h6 class="duplicate_text color_danger" style="display: none;"><?php esc_html_e('This Bus Stop Alreadd Exist', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
                            <label>
                                <span class="w_200"><?php esc_html_e('Name:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                <input type="text" class="formControl" id="bus_stop_name">
                            </label>
                            <p class="name_required"><?php esc_html_e('Name is required', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                            <label class="mT">
                                <span class="w_200"><?php esc_html_e('Description:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                <textarea id="bus_stop_description" rows="5" cols="50" class="formControl"></textarea>
                            </label>

                        </div>
                        <div class="popupFooter">
                            <div class="buttonGroup">
                                <button class="_themeButton submit-bus-stop" type="button"><?php esc_html_e('Save', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                <button class="_warningButton submit-bus-stop close_popup" type="button"><?php esc_html_e('Save & Close', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                            </div>
                        </div>
                    </div>

                </div>
                <button type="button" class="_dButton_xs_bgBlue" data-target-popup="#wbtm_route_popup">
                    <span class="fas fa-plus-square"></span>
                    Add New Bus Stop
                </button>
                <p class="ra-stopage-desc"><?php esc_html_e("You can't start routing until you add bus stoppages. If you haven't added any yet, you can do so from here.", 'bus-ticket-booking-with-seat-reservation'); ?></p>
            </div>

        </div>
    </div>

    <hr />
    <!-- ADD ROUTING-->
    <div class="bus-stops-wrapper">
        <!-- ADD BOARDING POINT-->
        <div class="bus-stops-left-col">
            <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <table class="repeatable-fieldset">
                <thead>
                    <tr>
                        <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <?php if ($route_disable_switch === 'on') : ?>
                            <th><?php _e('Disable', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bd-point boarding-point">
                    <?php if ($wbbm_bus_bp) : $count = 0;
                        foreach ($wbbm_bus_bp as $field) {  ?>
                            <tr>
                                <td align="center">
                                    <select name="wbtm_bus_bp_stops_name[]" class='seat_type bus_stop_add_option wbtm_bus_stops_route'>
                                        <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php foreach ($terms as $term) { ?>
                                            <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>" <?php echo ($term->name == $field['wbtm_bus_bp_stops_name']) ? 'Selected' : '' ?>><?php echo $term->name; ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td align="center" width="30px">
                                    <input type="text" data-clocklet name='wbtm_bus_bp_start_time[]' value="<?php if ($field['wbtm_bus_bp_start_time'] != '') echo esc_attr($field['wbtm_bus_bp_start_time']); ?>" class="text" placeholder="15:00">
                                </td>
                                <td align="center"><a class="button wbtm-remove-row-t" href="#"><i class="fas fa-minus-circle"></i>
                                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a>
                                </td>
                                <?php if ($route_disable_switch === 'on') : ?>
                                    <td class="route_disable_container">
                                        <label class="switch route-disable-switch">
                                            <input type="checkbox" name="wbtm_bus_bp_start_disable[<?php echo $field['wbtm_bus_bp_stops_name'] ?>]"  <?php echo (isset($field['wbtm_bus_bp_start_disable']) && $field['wbtm_bus_bp_start_disable'] === 'yes') ? "checked" : '' ?>>
                                            <span class="slider round"></span>
                                        </label>
                                    </td>
                                <?php endif; ?>
                            </tr>
                    <?php $count++;
                        }
                    else :
                    // show a blank one
                    endif; ?>

                    <tr style="display: none" class="more-bd-point">
                        <td align="center">
                            <select name="wbtm_bus_bp_stops_name[]" class='seat_type wbtm_boarding_point bus_stop_add_option wbtm_bus_stops_route'>
                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($terms as $term) { ?>
                                    <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td align="center"><input type="text" data-clocklet name='wbtm_bus_bp_start_time[]' value="" class="text" placeholder="15:00"></td>
                        <td align="center">
                            <a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a>
                        </td>
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

        <!-- ADD DROPPING POINT-->
        <div class="bus-stops-right-col">
            <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <table class="repeatable-fieldset">
                <tr>
                    <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                </tr>
                <tbody class="bd-point dropping-point">
                    <?php
                    if ($wbtm_bus_next_stops) :
                        $count = 0;
                        foreach ($wbtm_bus_next_stops as $field) {
                    ?>
                            <tr>
                                <td align="center">
                                    <select name="wbtm_bus_next_stops_name[]" class='seat_type wbtm_dropping_point bus_stop_add_option wbtm_bus_stops_route'>
                                        <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php
                                        foreach ($terms as $term) { ?>
                                            <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>" <?php echo ($term->name == $field['wbtm_bus_next_stops_name']) ? 'Selected' : '' ?>>
                                                <?php echo $term->name; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time[]' value="<?php if ($field['wbtm_bus_next_end_time'] != '') echo esc_attr($field['wbtm_bus_next_end_time']); ?>" class="text" placeholder="15:00"></td>
                                <td align="center">
                                    <a class="button wbtm-remove-row-t" href="#">
                                        <i class="fas fa-minus-circle"></i><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a>
                                </td>
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
                            <select name="wbtm_bus_next_stops_name[]" class='seat_type bus_stop_add_option wbtm_bus_stops_route'>
                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($terms as $term) { ?>
                                    <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>">
                                        <?php echo $term->name; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td align="center">
                            <input type="text" data-clocklet name='wbtm_bus_next_end_time[]' value="" class="text" placeholder="15:00">
                        </td>
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
    <!-- Bus route summary -->
    <?php admin_route_summary($post, $wbbm_bus_bp, $wbtm_bus_next_stops); ?>
    <!-- Bus route summary end -->




    <!-- ADD Return ROUTING-->
    <div class="wbtm-only-for-return-enable">
        <h3 class="wbtm-single-return-header"><?php _e('Return Route', 'bus-ticket-booking-with-seat-reservation') ?>:</h3>
        <div class="bus-stops-wrapper">
            <!-- ADD Return BOARDING POINT-->
            <div class="bus-stops-left-col">
                <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <table class="repeatable-fieldset" border="1px">
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
                    <tbody class="bd-point-return boarding-point-return">
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
                                                <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>" <?php echo ($term->name == $field['wbtm_bus_bp_stops_name']) ? 'Selected' : '' ?>>
                                                    <?php echo $term->name; ?>
                                                </option>
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
                                                <input type="checkbox" name="wbtm_bus_bp_start_disable[<?php echo $field['wbtm_bus_bp_stops_name'] ?>]" <?php echo (isset($field['wbtm_bus_bp_start_disable']) && $field['wbtm_bus_bp_start_disable'] === 'yes') ? "checked" : '' ?>>
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
                        <tr style="display: none" class="more-bd-point">
                            <td align="center">
                                <select name="wbtm_bus_bp_stops_name_return[]" class='seat_type wbtm_boarding_point bus_stop_add_option wbtm_bus_stops_route'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                    ?>
                                        <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>">
                                            <?php echo $term->name; ?>
                                        </option>
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
                <a class="button wbtom-tb-repeat-btn add-more-bd-point" href="#">
                    <i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                </a>
            </div>

            <!-- ADD Return DROPPING POINT-->
            <div class="bus-stops-right-col">
                <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <table class="repeatable-fieldset">
                    <tr>
                        <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    </tr>
                    <tbody class="bd-point-return boarding-point-return dropping-point-return">
                        <?php
                        if ($wbtm_bus_next_stops_return) :
                            $count = 0;
                            foreach ($wbtm_bus_next_stops_return as $field) {
                                ?>
                                <tr>
                                    <td align="center">
                                        <select name="wbtm_bus_next_stops_name_return[]" class='seat_type bus_stop_add_option wbtm_bus_stops_route'>
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
                        <tr style="display: none" class="more-bd-point">
                            <td align="center">
                                <select name="wbtm_bus_next_stops_name_return[]" class='seat_type wbtm_bus_stops_route'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                    ?>
                                        <option data-term_id="<?php echo $term->term_id; ?>" value="<?php echo $term->name; ?>">
                                            <?php echo $term->name; ?>
                                        </option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td align="center">
                                <input type="text" data-clocklet name='wbtm_bus_next_end_time_return[]' value="" class="text" placeholder="15:00">
                            </td>
                            <td align="center">
                                <a class="button remove-bp-row" href="#"><i class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a></td>
                        </tr>
                    </tbody>
                </table>
                <a class="button wbtom-tb-repeat-btn add-more-bd-point" href="#"><i class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                </a>
            </div>
        </div>
        <!-- Bus route summary -->
        <?php admin_route_summary($post, $wbbm_bus_bp_return, $wbtm_bus_next_stops_return, true); ?>
        <!-- Bus route summary end -->
    </div>


</div>