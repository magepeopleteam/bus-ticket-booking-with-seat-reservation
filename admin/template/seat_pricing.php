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
            <tbody>

            <?php
            if (!empty($prices)) :
                foreach ($prices as $price) : ?>
                    <tr>
                        <td class="wbtm-wid-25">
                            <select name="wbtm_bus_bp_price_stop[]" style="width: 100%">
                                <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($routes as $route) : ?>
                                    <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_bp_price_stop'] ? 'selected' : '') ?>>
                                        <?php echo $route->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="wbtm-wid-25">
                            <select name="wbtm_bus_dp_price_stop[]" style="width: 100%">
                                <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($routes as $route) : ?>
                                    <option value="<?php echo $route->name; ?>" <?php echo ($route->name == $price['wbtm_bus_dp_price_stop'] ? 'selected' : '') ?>>
                                        <?php echo $route->name; ?></option>
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
                            <button class="button wbtm-remove-row-t"><span class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>
                <?php
                endforeach;
            endif ?>

            <!-- empty hidden one for jQuery -->
            <tr class="mtsa-empty-row-t">
                <td>
                    <select name="wbtm_bus_bp_price_stop[]" style="width: 100%">
                        <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <?php if ($routes) : foreach ($routes as $route) : ?>
                            <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                </td>
                <td>
                    <select name="wbtm_bus_dp_price_stop[]" style="width: 100%">
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
                    <button class="button wbtm-remove-row-t"><span class="dashicons dashicons-trash"></span></button>
                </td>
            </tr>
            </tbody>
        </table>
        <button class="button wbtom-tb-repeat-btn" style="background:green; color:white;"><span class="dashicons dashicons-plus-alt" style="margin-top: 3px;color: white;"></span><?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
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
                <tbody>

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
                                <button class="button wbtm-remove-row-t"><span class="dashicons dashicons-trash"></span></button>
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
                        <button class="button wbtm-remove-row-t"><span class="dashicons dashicons-trash"></span></button>
                    </td>
                </tr>
                </tbody>
            </table>
            <button class="button wbtom-tb-repeat-btn" style="background:green; color:white;"><span class="dashicons dashicons-plus-alt" style="margin-top: 3px;color: white;"></span><?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
            </button>
        </div>
    </div>
    <!-- Return Price END -->
</div>