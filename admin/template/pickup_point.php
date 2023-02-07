<div class="mpStyle">
    <div class="mpPopup" data-popup="#wbtm_pickup_popup">
        <div class="popupMainArea">
            <div class="popupHeader">
                <h4>
                    <?php esc_html_e( 'Add new Pickup', 'bus-ticket-booking-with-seat-reservation' ); ?>
                </h4>
                <span class="fas fa-times popupClose"></span>
            </div>
            <div class="popupBody pickup-form">

                <h6 class="textSuccess success_text" style="display: none;"><?php esc_html_e( 'Added Succesfully', 'bus-ticket-booking-with-seat-reservation' ); ?></h6>

                <h6 class="duplicate_text color_danger" style="display: none;"><?php esc_html_e( 'This Bus Stop Alreadd Exist', 'bus-ticket-booking-with-seat-reservation' ); ?></h6>

                <label>
                    <span class="w_200"><?php esc_html_e( 'Name:', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                    <input type="text"  class="formControl" id="pickup_name">
                </label>
                <p class="name_required"><?php esc_html_e( 'Name is required', 'bus-ticket-booking-with-seat-reservation' ); ?></p>

                <label class="mT">
                    <span class="w_200"><?php esc_html_e( 'Description:', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                    <textarea  id="pickup_description" rows="5" cols="50" class="formControl"></textarea>
                </label>

            </div>
            <div class="popupFooter">
                <div class="buttonGroup">
                    <button class="_themeButton submit-pickup" type="button"><?php esc_html_e( 'Save', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
                    <button class="_warningButton submit-pickup close_popup" type="button"><?php esc_html_e( 'Save & Close', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
                </div>
            </div>
        </div>

    </div>
    <div class="ra-text-center">
        <button type="button" class="_dButton_xs_bgBlue ra-picup-point-button" data-target-popup="#wbtm_pickup_popup">
            <span class="fas fa-plus-square"></span>
            Add new pickup point
        </button>
        <p class="ra-stopage-desc"><?php esc_html_e( "", 'bus-ticket-booking-with-seat-reservation' ); ?></p>
    </div>

</div>

<div class="wbtm_bus_pickpint_wrapper" data-isReturn="no">


    <div class="wbtm_left_col">
        <div class="wbtm_field_group boarding_points <?php echo $boarding_points_class ?>">
            <select  name="wbtm_pick_boarding" class="wbtm_pick_boarding">
                <option value=""><?php _e('Select Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                <?php foreach ($boarding_points_array as $stop) : ?>
                    <option value="<?php echo $stop->term_id ?>"><?php echo $stop->name ?></option>
                <?php endforeach; ?>
            </select>
            <button class="wbtm_add_pickpoint_this_city">
                <?php _e('Configure Pickup point', 'bus-ticket-booking-with-seat-reservation'); ?> <i class="fas fa-arrow-right"></i>
            </button>
        </div>
        <button class="ra-button-style open-routing-tab <?php echo $boarding_points_class ?>"><?php _e('Please configure route plan first, To add root plan click here', 'bus-ticket-booking-with-seat-reservation'); ?></button>
    </div>

    <?php $selected_city_pickpoints = get_post_meta($post->ID, 'wbtm_pickpoint_selected_city', true); ?>



    <div class="wbtm_right_col <?php echo ($selected_city_pickpoints == '' ? 'all-center' : ''); ?>">

        <div id="wbtm_pickpoint_selected_city">
            <?php if ($selected_city_pickpoints != '') {
                $selected_city_pickpoints = explode(',', $selected_city_pickpoints);
                foreach ($selected_city_pickpoints as $single) {
                    $get_pickpoints_data = get_post_meta($post->ID, 'wbtm_selected_pickpoint_name_' . $single, true); ?>

                    <div class="wbtm_selected_city_item">
                        <span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></span>
                        <h4 class="wbtm_pickpoint_title"><?php echo (mage_get_term($single, 'wbtm_bus_stops') ? mage_get_term($single, 'wbtm_bus_stops')->name : ''); ?></h4>
                        <input type="hidden" name="wbtm_pickpoint_selected_city[]" value="<?php echo $single; ?>">
                        <div class="pickpoint-adding-wrap">
                            <?php if ($get_pickpoints_data) {
                                $get_pickpoints_data = unserialize($get_pickpoints_data);
                                foreach ($get_pickpoints_data as $pickpoint) : ?>
                                    <div class="pickpoint-adding">
                                        <select class="pickup_add_option"  name="wbtm_selected_pickpoint_name_<?php echo $single; ?>[]">
                                            <?php
                                            if ($bus_pickpoints) {
                                                foreach ($bus_pickpoints as $bus_pickpoint) {
                                                    echo '<option value="' . $bus_pickpoint->slug . '" ' . ($bus_pickpoint->slug == $pickpoint['pickpoint'] ? "selected=selected" : '') . '>' . $bus_pickpoint->name . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <input type="text" name="wbtm_selected_pickpoint_time_<?php echo $single; ?>[]" value="<?php echo $pickpoint['time']; ?>">
                                        <button class="wbtm_remove_pickpoint">
                                            <i class="fas fa-minus-circle"></i>
                                        </button>
                                    </div>
                                <?php endforeach; } ?>
                        </div>
                        <button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i>
                            <?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </button>
                    </div>
                    <?php }
            } else {
                echo '<p class="blank-pickpoint" style="color: #FF9800;font-weight: 700;">' . __('No pickup point added yet!', 'bus-ticket-booking-with-seat-reservation') . '</p>';
            }
            ?>
        </div>
    </div>
</div>



<!-- Return  -->
<div class="wbtm-only-for-return-enable">
    <h3 class="wbtm-single-return-header"><?php _e('Pickup Point Return', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <div class="wbtm_bus_pickpint_wrapper" data-isReturn="yes">
        <div class="wbtm_left_col">
            <div class="wbtm_field_group">
                <?php if ($boarding_points_array) : ?>
                    <select name="wbtm_pick_boarding" class="wbtm_pick_boarding_return">
                        <option value=""><?php _e('Select Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        <?php foreach ($boarding_points_array as $stop) : ?>
                            <option value="<?php echo $stop->term_id ?>"><?php echo $stop->name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="wbtm_add_pickpoint_this_city">
                        <?php _e('Add Pickup point', 'bus-ticket-booking-with-seat-reservation'); ?>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                <?php else :
                    echo "<div style='padding: 10px 0;text-align: center;background: #d23838;color: #fff;border: 5px solid #ff2d2d;padding: 5px;font-size: 16px;display: block;margin: 20px;'>Please Enter some bus stops first. <a style='color:#fff' href='" . get_admin_url() . "edit-tags.php?taxonomy=wbtm_bus_stops&post_type=wbtm_bus'>Click here for bus stops</a></div>";
                endif; ?>
            </div>
        </div>
        <?php $selected_city_pickpoints_return = get_post_meta($post->ID, 'wbtm_pickpoint_selected_city_return', true); ?>
        <div class="wbtm_right_col <?php echo ($selected_city_pickpoints_return == '' ? 'all-center' : ''); ?>">
            <div id="wbtm_pickpoint_selected_city">

                <?php

                if ($selected_city_pickpoints_return != '') {

                    $selected_city_pickpoints_return = explode(',', $selected_city_pickpoints_return);
                    foreach ($selected_city_pickpoints_return as $single) {
                        $get_pickpoints_data = get_post_meta($post->ID, 'wbtm_selected_pickpoint_return_name_' . $single, true); ?>
                        <div class="wbtm_selected_city_item">
                            <span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></span>
                            <h4 class="wbtm_pickpoint_title"><?php echo (mage_get_term($single, 'wbtm_bus_stops') ? mage_get_term($single, 'wbtm_bus_stops')->name : ''); ?></h4>
                            <input type="hidden" name="wbtm_pickpoint_selected_city_return[]" value="<?php echo $single; ?>">
                            <div class="pickpoint-adding-wrap">
                                <?php

                                if ($get_pickpoints_data) {
                                    $get_pickpoints_data = unserialize($get_pickpoints_data);

                                    foreach ($get_pickpoints_data as $pickpoint) : ?>


                                        <div class="pickpoint-adding">
                                            <select class="pickup_add_option" name="wbtm_selected_pickpoint_return_name_<?php echo $single; ?>[]">
                                                <?php
                                                if ($bus_pickpoints) {
                                                    foreach ($bus_pickpoints as $bus_pickpoint) {
                                                        echo '<option value="' . $bus_pickpoint->slug . '" ' . ($bus_pickpoint->slug == $pickpoint['pickpoint'] ? "selected=selected" : '') . '>' . $bus_pickpoint->name . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <input type="text" name="wbtm_selected_pickpoint_return_time_<?php echo $single; ?>[]" value="<?php echo $pickpoint['time']; ?>">
                                            <button class="wbtm_remove_pickpoint"><i class="fas fa-minus-circle"></i>
                                            </button>
                                        </div>

                                    <?php
                                    endforeach;
                                } ?>
                            </div>
                            <button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i>
                                <?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </button>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p class="blank-pickpoint" style="color: #FF9800;font-weight: 700;">' . __('No pickup point added yet!', 'bus-ticket-booking-with-seat-reservation') . '</p>';
                }
                ?>

            </div>
        </div>
    </div>
</div>
<!-- Return END -->

<script>
    // Pickuppoint
    // Select Boarding point and hit add
    (function($) {
        $('.wbtm_add_pickpoint_this_city').click(function(e) {
            e.preventDefault();

            let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');
            let isReturn = parent.attr('data-isReturn');

            parent.find('.blank-pickpoint').remove();
            parent.find('.wbtm_right_col').removeClass('all-center');
            var get_boarding_point = parent.find('.wbtm_pick_boarding option:selected').val();

            // Validation
            if (get_boarding_point == '') {
                parent.find('.wbtm_pick_boarding').css({
                    'border': '1px solid red',
                    'color': 'red'
                }); // Not ok!!!
                return;
            } else {
                parent.find('.wbtm_pick_boarding').css({
                    'border': '1px solid #7e8993',
                    'color': '#8ac34a'
                }); // Ok

            }

            var get_boarding_point_name = parent.find('.wbtm_pick_boarding option:selected').text();
            parent.find('.wbtm_pick_boarding option:selected').remove();
            var html =
                '<div class="wbtm_selected_city_item"><span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></i></span>' +
                '<h4 class="wbtm_pickpoint_title">' + get_boarding_point_name + '</h4>' +
                '<input type="hidden" name=' + ((isReturn == "yes") ? "wbtm_pickpoint_selected_city_return[]" : "wbtm_pickpoint_selected_city[]") + ' value="' + get_boarding_point +
                '">' +
                '<div class="pickpoint-adding-wrap"><div class="pickpoint-adding">' +
                '<select class="pickup_add_option" name="' + ((isReturn == "yes") ? "wbtm_selected_pickpoint_return_name_" : "wbtm_selected_pickpoint_name_") + get_boarding_point + '[]">' +
                '<?php echo $pickpoints; ?>' +
                '</select>' +
                '<input type="text" name="' + ((isReturn == "yes") ? "wbtm_selected_pickpoint_return_time_" : "wbtm_selected_pickpoint_time_") + get_boarding_point +
                '[]" placeholder="15:00">' +
                '<button class="wbtm_remove_pickpoint"><i class="fas fa-minus-circle"></i></button>' +
                '</div></div>' +
                '<button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i> <?php _e("Add more", "bus-ticket-booking-with-seat-reservation"); ?></button>' +
                '</div>';


            if (parent.find('#wbtm_pickpoint_selected_city').children().length > 0) {
                parent.find('#wbtm_pickpoint_selected_city').append(html);
            } else {
                parent.find('#wbtm_pickpoint_selected_city').html(html);
            }

            parent.find('.wbtm_pick_boarding option:first').attr('selected', 'selected');

        });

        // Remove City for Pickpoint
        $(document).on('click', '.remove_city_for_pickpoint', function(e) {
            e.preventDefault();

            let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

            var city_name = $(this).siblings('.wbtm_pickpoint_title').text();
            var city_name_val = $(this).siblings('input').val();
            parent.find('.wbtm_pick_boarding').append('<option value="' + city_name_val + '">' + city_name +
                '</option>');
            $(this).parents('.wbtm_selected_city_item').remove();
        });

        // Adding more pickup point
        $(document).on('click', '.wbtm_add_more_pickpoint', function(e) {
            e.preventDefault();

            let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

            $adding_more = $(this).siblings('.pickpoint-adding-wrap').find('.pickpoint-adding:first').clone(
                true);
            $(this).siblings('.pickpoint-adding-wrap').append($adding_more);
        });

        // Remove More Pickpoint
        $(document).on('click', '.wbtm_remove_pickpoint', function(e) {
            e.preventDefault();

            let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

            // Remove wrapper
            if ($(this).parents('.pickpoint-adding-wrap').children().length == 1) {
                $(this).parents('.wbtm_selected_city_item').find('.remove_city_for_pickpoint').trigger(
                    'click');
            }

            // Remove Item
            $(this).parent().remove();


        });
        // Pickuppoint END
    })(jQuery)
</script>