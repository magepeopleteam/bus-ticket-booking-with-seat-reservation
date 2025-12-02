//==========Price settings=================//
(function ($) {
    "use strict";
    function wbtm_reload_pricing(parent) {
        let post_id = $('[name="wbtm_post_id"]').val();
        let target = parent.find(".wbtm_price_setting_area");
        let route_infos = {};
        let count = 0;
        parent
            .find(".wbtm_stop_item")
            .each(function () {
                let infos = {};
                let place = $(this).find('[name="wbtm_route_place[]"]').val();
                let time = $(this).find('[name="wbtm_route_time[]"]').val();
                let type = $(this).find('[name="wbtm_route_type[]"]').val();
                if (place && time && type) {
                    infos["place"] = place;
                    infos["type"] = count < 1 ? "bp" : type;
                    route_infos[count] = infos;
                    count++;
                }
            })
            .promise()
            .done(function () {
                if (count > 1) {
                    route_infos[count - 1]["type"] = "dp";
                    $.ajax({
                        type: "POST",
                        url: wbtm_admin_var.url,
                        data: {
                            action: "wbtm_reload_pricing",
                            post_id: post_id,
                            route_infos: route_infos,
                            nonce: wbtm_admin_var.nonce
                        },
                        beforeSend: function () {
                            wbtm_loader(target);
                        },
                        success: function (data) {
                            target.html(data);
                            wbtm_loaderRemove(parent);
                        },
                        error: function (response) {
                            console.log(response);
                        },
                    });
                } else {
                    target.html("");
                }
            });
    }
    $(document).on(
        "click",
        ".wbtm_settings_pricing_routing .wbtm_stop_item .wbtm_item_remove",
        function (e) {
            if (e.result) {
                wbtm_reload_pricing($(".wbtm_settings_pricing_routing"));
            }
        }
    );
    $(document).on(
        "change",
        '.wbtm_settings_pricing_routing [name="wbtm_route_place[]"],.wbtm_settings_pricing_routing [name="wbtm_route_type[]"]',
        function () {
            wbtm_reload_pricing($(".wbtm_settings_pricing_routing"));
        }
    );
})(jQuery);
//==========Seat plan settings=================//
(function ($) {
    "use strict";
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_create_seat_plan",
        function (e) {
            let parent = $(this).closest(".wbtm_settings_seat");
            let target = parent.find(".wbtm_seat_plan_preview");
            let post_id = $('[name="wbtm_post_id"]').val();
            let row = parseInt(parent.find('[name="wbtm_seat_rows"]').val());
            let column = parseInt(parent.find('[name="wbtm_seat_cols"]').val());
            if (row > 0 && column > 0) {
                $.ajax({
                    type: "POST",
                    url: wbtm_admin_var.url,
                    data: {
                        action: "wbtm_create_seat_plan",
                        post_id: post_id,
                        row: row,
                        column: column,
                        nonce: wbtm_admin_var.nonce
                    },
                    beforeSend: function () {
                        wbtm_loader(target);
                    },
                    success: function (data) {
                        parent.find('[name="wbtm_seat_cols_hidden"]').val(column);
                        parent.find('[name="wbtm_seat_rows_hidden"]').val(row);
                        target.html(data);
                        //wbtm_loaderRemove(parent);
                    },
                    error: function (response) {
                        console.log(response);
                    },
                });
            } else {
                alert("Number  of row & column must be greater than 0");
            }
        }
    );
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_seat_plan_preview .wbtm_item_remove",
        function (e) {
            if (e.result) {
                let parent = $(".wbtm_settings_seat");
                let target = parent.find('[name="wbtm_seat_rows"]');
                let value = parseInt(target.val()) - 1;
                target.val(value);
                parent.find('[name="wbtm_seat_rows_hidden"]').val(value);
            }
        }
    );
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_seat_plan_preview .wbtm_add_item",
        function (e) {
            if (e.result) {
                let parent = $(".wbtm_settings_seat");
                let target = parent.find('[name="wbtm_seat_rows"]');
                let value = parseInt(target.val()) + 1;
                target.val(value);
                parent.find('[name="wbtm_seat_rows_hidden"]').val(value);
            }
        }
    );
    //=============================//
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_create_seat_plan_dd",
        function (e) {
            let parent = $(this).closest(".wbtm_settings_seat");
            let target = parent.find(".wbtm_seat_plan_preview_dd");
            let post_id = $('[name="wbtm_post_id"]').val();
            let row = parseInt(parent.find('[name="wbtm_seat_rows_dd"]').val());
            let column = parseInt(parent.find('[name="wbtm_seat_cols_dd"]').val());
            if (row > 0 && column > 0) {
                $.ajax({
                    type: "POST",
                    url: wbtm_admin_var.url,
                    data: {
                        action: "wbtm_create_seat_plan_dd",
                        post_id: post_id,
                        row: row,
                        column: column,
                        nonce: wbtm_admin_var.nonce
                    },
                    beforeSend: function () {
                        wbtm_loader(target);
                    },
                    success: function (data) {
                        parent.find('[name="wbtm_seat_cols_dd_hidden"]').val(column);
                        parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(row);
                        target.html(data);
                        //wbtm_loaderRemove(parent);
                    },
                    error: function (response) {
                        console.log(response);
                    },
                });
            } else {
                alert("Number  of row & column must be greater than 0");
            }
        }
    );
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_seat_plan_preview_dd .wbtm_item_remove",
        function (e) {
            if (e.result) {
                let parent = $(".wbtm_settings_seat");
                let target = parent.find('[name="wbtm_seat_rows_dd"]');
                let value = parseInt(target.val()) - 1;
                target.val(value);
                parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(value);
            }
        }
    );
    $(document).on(
        "click",
        ".wbtm_settings_seat .wbtm_seat_plan_preview_dd .wbtm_add_item",
        function (e) {
            if (e.result) {
                let parent = $(".wbtm_settings_seat");
                let target = parent.find('[name="wbtm_seat_rows_dd"]');
                let value = parseInt(target.val()) + 1;
                target.val(value);
                parent.find('[name="wbtm_seat_rows_dd_hidden"]').val(value);
            }
        }
    );
})(jQuery);
//==========Pickup settings=================//
(function ($) {
    "use strict";
    $(document).on("click", ".wbtm_add_group_pickup", function () {
        let parent = $(this).closest(".wbtm_settings_area");
        let target_item = $(this)
            .next($(".wbtm_hidden_content"))
            .find(" .wbtm_hidden_item");
        let item = target_item.html();
        wbtm_load_sortable_datepicker(parent, item);
        let unique_id = Math.floor(Math.random() * 9999 + 9999);
        target_item.find('[name="wbtm_pickup_unique_id[]"]').val(unique_id);
        target_item
            .find('[name*="wbtm_bp_pickup"]')
            .attr("name", "wbtm_bp_pickup[" + unique_id + "]");
        target_item
            .find('[name*="wbtm_pickup_name"]')
            .attr("name", "wbtm_pickup_name[" + unique_id + "][]");
        target_item
            .find('[name*="wbtm_pickup_time"]')
            .attr("name", "wbtm_pickup_time[" + unique_id + "][]");
    });
    $(document).on("click", ".wbtm_add_group_drop_off", function () {
        let parent = $(this).closest(".wbtm_settings_area");
        let target_item = $(this)
            .next($(".wbtm_hidden_content"))
            .find(" .wbtm_hidden_item");
        let item = target_item.html();
        wbtm_load_sortable_datepicker(parent, item);
        let unique_id = Math.floor(Math.random() * 9999 + 9999);
        target_item.find('[name="wbtm_drop_off_unique_id[]"]').val(unique_id);
        target_item
            .find('[name*="wbtm_dp_pickup"]')
            .attr("name", "wbtm_dp_pickup[" + unique_id + "]");
        target_item
            .find('[name*="wbtm_drop_off_name"]')
            .attr("name", "wbtm_drop_off_name[" + unique_id + "][]");
        target_item
            .find('[name*="wbtm_drop_off_time"]')
            .attr("name", "wbtm_drop_off_time[" + unique_id + "][]");
    });
})(jQuery);
//==========Seat Rotation=================//
(function ($) {
    "use strict";
    // Handle seat rotation button clicks
    $(document).on("click", ".wbtm_rotate_seat", function (e) {
        e.preventDefault();
        let $button = $(this);
        let $rotationInput = $button.siblings('.wbtm_rotation_value');
        let currentRotation = parseInt($rotationInput.val()) || 0;
        // Calculate next rotation (0 -> 90 -> 180 -> 270 -> 0)
        let newRotation = (currentRotation + 90) % 360;
        // Update the hidden input value
        $rotationInput.val(newRotation);
        // Update button appearance
        $button.removeClass('rotated-90 rotated-180 rotated-270');
        if (newRotation > 0) {
            $button.addClass('rotated-' + newRotation);
        }
        // Update data attribute for visual feedback
        $button.attr('data-rotation', newRotation);
    });
    // Initialize rotation buttons when seat plan is loaded
    $(document).on('DOMNodeInserted', '.wbtm_seat_plan_preview, .wbtm_seat_plan_preview_dd, .wbtm_cabin_seat_plan', function () {
        initializeRotationButtons();
    });
    // Also initialize on page load
    $(document).ready(function () {
        initializeRotationButtons();
    });
    // Initialize rotation buttons after generating cabin seats
    $(document).on('click', '.wbtm_generate_cabin_seats', function () {
        setTimeout(function () {
            initializeRotationButtons();
        }, 100);
    });
    function initializeRotationButtons() {
        $('.wbtm_rotate_seat').each(function () {
            let $button = $(this);
            let rotation = parseInt($button.attr('data-rotation')) || 0;
            // Apply initial rotation class
            if (rotation > 0) {
                $button.removeClass('rotated-90 rotated-180 rotated-270');
                $button.addClass('rotated-' + rotation);
            }
        });
    }
    // Handle rotation setting toggle
    $(document).on('change', 'input[name="wbtm_enable_seat_rotation"]', function () {
        let isEnabled = $(this).is(':checked');
        let $seatPlanContainer = $('.wbtm_seat_plan_settings');
        let $cabinContainer = $('.wbtm_cabin_settings_area');
        if (isEnabled) {
            // Show rotation controls immediately
            $seatPlanContainer.addClass('wbtm_enable_rotation');
            $seatPlanContainer.find('.wbtm_seat_rotation_controls').show();
            $cabinContainer.addClass('wbtm_enable_rotation');
            $cabinContainer.find('.wbtm_seat_rotation_controls').show();
            // Add rotation controls to existing seats if they don't have them
            $seatPlanContainer.find('.wbtm_seat_container').each(function () {
                let $container = $(this);
                if ($container.find('.wbtm_seat_rotation_controls').length === 0) {
                    let $input = $container.find('input[class*="wbtm_id_validation"]');
                    let inputName = $input.attr('name');
                    let seatKey = inputName.replace('wbtm_', '').replace('[]', '');
                    let rotationControls = `
            <div class="wbtm_seat_rotation_controls">
              <button type="button" class="wbtm_rotate_seat _whiteButton_xs" 
                      data-seat-key="${seatKey}" 
                      data-rotation="0"
                      title="Rotate Seat">
                <span class="fas fa-redo-alt mp_zero"></span>
              </button>
              <input type="hidden" name="wbtm_${seatKey}_rotation[]" 
                     value="0" 
                     class="wbtm_rotation_value" />
            </div>
          `;
                    $container.append(rotationControls);
                }
            });
        } else {
            // Hide rotation controls immediately
            $seatPlanContainer.removeClass('wbtm_enable_rotation');
            $seatPlanContainer.find('.wbtm_seat_rotation_controls').hide();
            $cabinContainer.removeClass('wbtm_enable_rotation');
            $cabinContainer.find('.wbtm_seat_rotation_controls').hide();
        }
    });
    // Initialize rotation setting on page load
    $(document).ready(function () {
        let $rotationToggle = $('input[name="wbtm_enable_seat_rotation"]');
        if ($rotationToggle.is(':checked')) {
            $('.wbtm_seat_plan_settings').addClass('wbtm_enable_rotation');
            $('.wbtm_cabin_settings_area').addClass('wbtm_enable_rotation');
            $('.wbtm_seat_rotation_controls').show();
        }
    });
})(jQuery);
