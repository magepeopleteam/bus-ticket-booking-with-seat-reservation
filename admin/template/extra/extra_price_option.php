<div id="wbtm_extra_service" style="margin-top:20px">
    <h3 style="margin:0;"><?php _e('Extra service Area :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <p class="event_meta_help_txt" style="margin: 0 0 15px 0;">
        <?php _e('Extra Service as Product that you can sell and it is not included on ticket', 'bus-ticket-booking-with-seat-reservation'); ?>
    </p>
    <div class="mp_ticket_type_table">
        <table id="repeatable-fieldset-one" style="width:100%">
            <thead>
            <tr>
                <th title="<?php _e('Extra Service Name', 'bus-ticket-booking-with-seat-reservation'); ?>">
                    <?php _e('Name', 'bus-ticket-booking-with-seat-reservation'); ?>
                </th>
                <th title="<?php _e('Extra Service Price', 'bus-ticket-booking-with-seat-reservation'); ?>">
                    <?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?>
                </th>
                <th title="<?php _e('Available Qty', 'bus-ticket-booking-with-seat-reservation'); ?>">
                    <?php _e('Available', 'bus-ticket-booking-with-seat-reservation'); ?>
                </th>
                <th title="<?php _e('Qty Box Type', 'bus-ticket-booking-with-seat-reservation'); ?>" style="min-width: 140px;">
                    <?php _e('Qty Box', 'bus-ticket-booking-with-seat-reservation'); ?>
                </th>
                <th></th>
            </tr>
            </thead>

            <tbody class="mp_event_type_sortable">
            <?php
            if ($mep_events_extra_prices) : foreach ($mep_events_extra_prices as $field) {
                $qty_type = esc_attr($field['option_qty_type']); ?>
                    <tr>
                        <td>
                            <input type="text" class="mp_formControl" name="option_name[]" placeholder="Ex: Cap" value="<?php if ($field['option_name'] != '') {
                                echo esc_attr($field['option_name']);
                            } ?>" />
                        </td>

                        <td>
                            <input type="number" step="0.001" class="mp_formControl" name="option_price[]" placeholder="Ex: 10" value="<?php if ($field['option_price'] != '') {
                                echo esc_attr($field['option_price']);
                            } else {
                                echo '';
                            } ?>" />
                        </td>

                        <td>
                            <input type="number" class="mp_formControl" name="option_qty[]" placeholder="Ex: 100" value="<?php if ($field['option_qty'] != '') {
                                echo esc_attr($field['option_qty']);
                            } else {
                                echo '';
                            } ?>" />
                        </td>

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
                <td><input type="number" class="mp_formControl" name="option_qty[]" placeholder="Ex: 100" value="" />
                </td>

                <td><select name="option_qty_type[]" class='mp_formControl'>
                        <option value=""><?php _e('Please Select Type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <option value="inputbox"><?php _e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <option value="dropdown"><?php _e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                    </select></td>
                <td>
                    <button class="button remove-row"><span class="dashicons dashicons-trash" style="margin-top: 3px;color: red;"></span><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </button>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <p>
        <button id="add-row" class="button" style="background:green; color:white;"><span class="dashicons dashicons-plus-alt" style="margin-top: 3px;color: white;"></span><?php _e('Add Extra Price', 'bus-ticket-booking-with-seat-reservation'); ?>
        </button>
    </p>
</div>