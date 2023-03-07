<div class="mp_tab_item" data-tab-item="#wbtm_seat_price">
    <div class="wbtm_tab_content_heading">
        <h3><?php _e(' Seat Pricing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <div class="wbtm-section-info">
            <span><i class="fas fa-info-circle"></i></span>
            <div class="wbtm-section-info-content">
                <?php _e('Individual prices for boarding point to dropping point with seat types.', 'bus-ticket-booking-with-seat-reservation'); ?>
            </div>
        </div>
    </div>
    <hr />

    <input type="hidden" id="price_bus_record" value="<?php echo ($prices=='')?$prices:count($prices) ?>">
    <input type="hidden" id="return_class" value="<?php echo $return_class ?>">

    <div id="wbtm_general_price" class="wbtm_content_wrapper">
        <div class="wbtm_content_inner">
            <table id="mtsa-repeatable-fieldset-ticket-type" class="mtsa-table repeatable-fieldset">
                <thead>
                <tr>
                    <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                </tr>
                </thead>
                <tbody class="auto-generated">
                <?php if (!empty($prices)) :
                    foreach ($prices as $price) : ?>
                        <tr>
                            <td class="wbtm-wid-25">
                                <select name="wbtm_bus_bp_price_stop[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_bp_price_stop'] ? 'selected' : '') ?>>
                                            <?php echo $route->name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="wbtm-wid-25">
                                <select name="wbtm_bus_dp_price_stop[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_dp_price_stop'] ? 'selected' : '') ?>>
                                            <?php echo $route->name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_price[]" placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_price']; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_price_return[]" placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo isset($price['wbtm_bus_price_return']) ? $price['wbtm_bus_price_return'] : ''; ?>" />
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_child_price[]" placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_child_price']; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_child_price_return[]" placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo isset($price['wbtm_bus_child_price_return']) ? $price['wbtm_bus_child_price_return'] : ''; ?>" />
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_infant_price[]" placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo isset($price['wbtm_bus_infant_price']) ? $price['wbtm_bus_infant_price'] : ''; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_infant_price_return[]" placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo isset($price['wbtm_bus_infant_price_return']) ? $price['wbtm_bus_infant_price_return'] : ''; ?>" />
                            </td>
                            <td class="wbtm-wid-5">
                                <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>
                            </td>
                        </tr>
                    <?php endforeach; endif ?>

            <!-- empty hidden one for jQuery -->
            <tr class="mtsa-empty-row-t">
                <td>
                    <select name="wbtm_bus_bp_price_stop[]" class="ra_bus_bp_price_stop" style="width: 100%">
                        <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <?php if ($routes) : foreach ($routes as $route) : ?>
                            <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                </td>
                <td>
                    <select name="wbtm_bus_dp_price_stop[]" class="ra_bus_dp_price_stop" style="width: 100%">
                        <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <?php if ($routes) : foreach ($routes as $route) : ?>
                            <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                </td>
                <td>
                    <input type="text" class="widefat" name="wbtm_bus_price[]" placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_price_return[]" placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                </td>
                <td>
                    <input type="text" class="widefat" name="wbtm_bus_child_price[]" placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_child_price_return[]" placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                </td>
                <td>
                    <input type="text" class="widefat" name="wbtm_bus_infant_price[]" placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_infant_price_return[]" placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                </td>
                <td>
                    <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>
                </td>
            </tr>
            </tbody>
        </table>
        <button class="button wbtm-tb-repeat-btn" style="background:green; color:white;">
            <span class="dashicons dashicons-plus-alt" style="margin:0px 3px;color: white;"></span>
            <?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
        </button>
    </div>

    <!-- Return Price -->
    <div class="wbtm-only-for-return-enable">
        <h3 class="wbtm-single-return-header"><?php _e('Return Seat Price', 'bus-ticket-booking-with-seat-reservation') ?>:</h3>
        <div class="wbtm_content_inner">
            <table id="mtsa-repeatable-fieldset-ticket-type" class="mtsa-table repeatable-fieldset">
                <thead>
                <tr>
                    <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                </tr>
                </thead>
                <tbody class="auto-generated-return">

                <?php
                if (!empty($prices_return)) :
                    foreach ($prices_return as $price) : ?>
                        <tr>
                            <td class="wbtm-wid-25">
                                <select name="wbtm_bus_bp_price_stop_return[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_bp_price_stop'] ? 'selected' : '') ?>>
                                            <?php echo $route->name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="wbtm-wid-25">
                                <select name="wbtm_bus_dp_price_stop_return[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_dp_price_stop'] ? 'selected' : '') ?>>
                                            <?php echo $route->name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_price_r[]" placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_price']; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_price_return_discount[]" placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_price_return']; ?>" />
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_child_price_r[]" placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_child_price']; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_child_price_return_discount[]" placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_child_price_return']; ?>" />
                            </td>
                            <td class="wbtm-wid-15">
                                <input type="text" class="widefat" name="wbtm_bus_infant_price_r[]" placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_infant_price']; ?>" />
                                <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_infant_price_return_discount[]" placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="<?php echo $price['wbtm_bus_infant_price_return']; ?>" />
                            </td>
                            <td class="wbtm-wid-5">
                                <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>
                            </td>
                        </tr>
                    <?php
                    endforeach;
                endif ?>

                <!-- empty hidden one for jQuery -->
                <tr class="mtsa-empty-row-t">
                    <td>
                        <select name="wbtm_bus_bp_price_stop_return[]" style="width: 100%">
                            <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php if ($routes) : foreach ($routes as $route) : ?>
                                <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                            <?php endforeach;
                            endif; ?>
                        </select>
                    </td>
                    <td>
                        <select name="wbtm_bus_dp_price_stop_return[]" style="width: 100%">
                            <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php if ($routes) : foreach ($routes as $route) : ?>
                                <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                            <?php endforeach;
                            endif; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="widefat" name="wbtm_bus_price_r[]" placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                        <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_price_return_discount[]" placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    </td>
                    <td>
                        <input type="text" class="widefat" name="wbtm_bus_child_price_r[]" placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                        <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_child_price_return_discount[]" placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    </td>
                    <td>
                        <input type="text" class="widefat" name="wbtm_bus_infant_price_r[]" placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                        <input type="text" class="widefat <?php echo $return_class; ?>" name="wbtm_bus_infant_price_return_discount[]" placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>" value="" />
                    </td>
                    <td>
                        <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>
                    </td>
                </tr>
                </tbody>
            </table>
            <button class="button wbtm-tb-repeat-btn" style="background:green; color:white;">
                <span class="dashicons dashicons-plus-alt" style="margin: 0px 3px;color: white;">
                </span><?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
            </button>
        </div>
    </div>
    <!-- Return Price END -->
</div>


    <div id="wbtm_subs_price">
            <?php echo do_action('wbtm_subscription_price'); ?>
        </div>

        <?php
        if (has_action('wbtm_private_price')) {
            echo do_action('wbtm_private_price');
        }
        ?>



    <h5 class="dFlex mpStyle">
        <span class="pb-10"><b class="ra-enable-button"><?php _e('Enable extra service :', 'bus-ticket-booking-with-seat-reservation'); ?></b>
            <label class="roundSwitchLabel">
                <input id="extra-service-control" name="show_extra_service" <?php echo ($show_extra_service == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
                <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
            </label>
        </span>
        <p><?php _e('You can offer extra services or sell products along with tickets by enabling this option. ', 'bus-ticket-booking-with-seat-reservation'); ?></p>
    </h5>


    <div style="margin-top:20px;display: <?php echo ($show_extra_service == "yes" ? "block" : "none"); ?>" id="wbtm_extra_service" class="extra-service">
        <h3 style="margin:0;"><?php _e('Extra service Area :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <p class="event_meta_help_txt" style="margin: 0 0 15px 0;">
            <?php _e('Extra Service as Product that you can sell and it is not included on ticket', 'bus-ticket-booking-with-seat-reservation'); ?>
        </p>
        <div class="mp_ticket_type_table">
            <table id="repeatable-fieldset-one" style="width:100%">
                <thead>
                <tr>
                    <th title="<?php _e('Extra Service Name', 'bus-ticket-booking-with-seat-reservation'); ?>">
                        <?php _e('Name', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th title="<?php _e('Extra Service Price', 'bus-ticket-booking-with-seat-reservation'); ?>">
                        <?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th title="<?php _e('Available Qty', 'bus-ticket-booking-with-seat-reservation'); ?>">
                        <?php _e('Available', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th title="<?php _e('Qty Box Type', 'bus-ticket-booking-with-seat-reservation'); ?>" style="min-width: 140px;">
                        <?php _e('Qty Box', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody class="mp_event_type_sortable">
                <?php

                if ($mep_events_extra_prices) :

                    foreach ($mep_events_extra_prices as $field) {
                        $qty_type = esc_attr($field['option_qty_type']);
                        ?>
                        <tr>
                            <td><input type="text" class="mp_formControl" name="option_name[]" placeholder="Ex: Cap" value="<?php if ($field['option_name'] != '') {
                                    echo esc_attr($field['option_name']);
                                } ?>" /></td>

                            <td><input type="number" step="0.001" class="mp_formControl" name="option_price[]" placeholder="Ex: 10" value="<?php if ($field['option_price'] != '') {
                                    echo esc_attr($field['option_price']);
                                } else {
                                    echo '';
                                } ?>" /></td>

                            <td><input type="number" class="mp_formControl" name="option_qty[]" placeholder="Ex: 100" value="<?php if ($field['option_qty'] != '') {
                                    echo esc_attr($field['option_qty']);
                                } else {
                                    echo '';
                                } ?>" /></td>

                            <td align="center">
                                <select name="option_qty_type[]" class='mp_formControl'>
                                    <option value="inputbox" <?php if ($qty_type == 'inputbox') {
                                        echo "Selected";
                                    } ?>><?php _e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="dropdown" <?php if ($qty_type == 'dropdown') {
                                        echo "Selected";
                                    } ?>><?php _e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                </select>
                            </td>
                            <td>
                                <div class="mp_event_remove_move">
                                    <button class="button remove-row" type="button"><span class="dashicons dashicons-trash" style="margin-top: 3px;color: red;"></span></button>
                                    <!-- <div class="mp_event_type_sortable_button"><span class="dashicons dashicons-move"></span></div> -->
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                else :
                    // show a blank one
                endif;
                ?>

                <!-- empty hidden one for jQuery -->
                <tr class="empty-row screen-reader-text">
                    <td><input type="text" class="mp_formControl" name="option_name[]" placeholder="Ex: Cap" /></td>
                    <td><input type="number" class="mp_formControl" step="0.001" name="option_price[]" placeholder="Ex: 10" value="" /></td>
                    <td><input type="number" class="mp_formControl" name="option_qty[]" placeholder="Ex: 100" value="" /></td>
                    <td>
                        <select name="option_qty_type[]" class='mp_formControl'>
                            <option value=""><?php _e('Please Select Type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="inputbox"><?php _e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="dropdown"><?php _e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        </select>
                    </td>
                    <td>
                        <button class="button remove-row">
                            <span class="dashicons dashicons-trash" style="margin-top: 3px;color: red;"></span><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <p>
            <button id="add-row" class="button" style="background:green; color:white;">
                <span class="dashicons dashicons-plus-alt" style="margin: 0px 3px;;color: white;">
                </span><?php _e('Add Extra Price', 'bus-ticket-booking-with-seat-reservation'); ?>
            </button>
        </p>
    </div>


</div>



