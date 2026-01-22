//==========Price settings=================//
(function ($) {
    "use strict";
    function wbtm_reload_pricing(parent) {
        let post_id = $('[name="wbtm_post_id"]').val();
        let target = parent.find(".wbtm_price_setting_area");
        let places = {};
        let types = {};
        let count = 0;
        parent.find(".wbtm_stop_item").each(function () {
            let infos = {};
            let place = $(this).find('[name="wbtm_route_place[]"]').val();
            let time = $(this).find('[name="wbtm_route_time[]"]').val();
            let type = $(this).find('[name="wbtm_route_type[]"]').val();
            if (place && time && type) {
                places[count] = place;
                types[count] = count < 1 ? "bp" : type;
                count++;
            }
        }).promise().done(function () {
            if (count > 1) {
                types[count - 1] = "dp";
                $.ajax({
                    type: "POST",
                    url: wbtm_admin_var.url,
                    data: {
                        action: "wbtm_reload_pricing",
                        post_id: post_id,
                        places: places,
                        types: types,
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

        // Sync all other inputs with the same name
        $('input[name="wbtm_enable_seat_rotation"]').not(this).prop('checked', isEnabled);

        let $seatPlanContainer = $('.wbtm_seat_plan_settings');
        let $cabinContainer = $('.wbtm_cabin_settings_area');

        if (isEnabled) {
            // Show rotation controls immediately
            $seatPlanContainer.addClass('wbtm_enable_rotation');
            $seatPlanContainer.find('.wbtm_seat_rotation_controls').show();
            $cabinContainer.addClass('wbtm_enable_rotation');
            $cabinContainer.find('.wbtm_seat_rotation_controls').show();

            // Add rotation controls to existing seats if they don't have them
            // Target both seat plan and cabin containers
            $('.wbtm_seat_container').each(function () {
                let $container = $(this);
                if ($container.find('.wbtm_seat_rotation_controls').length === 0) {
                    let $input = $container.find('input[class*="wbtm_id_validation"]');
                    if ($input.length > 0) {
                        let inputName = $input.attr('name');
                        if (inputName) {
                            // Extract key: remove wbtm_ prefix and [] suffix
                            // Handle potential template_ prefix for hidden rows
                            let seatKey = inputName.replace('wbtm_', '').replace('template_', '').replace('[]', '');

                            // If it was a template input, we need to be careful, but here we likely deal with real inputs or template inputs
                            // The rotation input name should match the text input name pattern
                            // If text input is wbtm_template_cabin_1_seat1[], rotation should be wbtm_template_cabin_1_seat1_rotation[]
                            // The helper above stripped template_, but we might need it back for the rotation name if the text input had it

                            let rotationInputName = inputName.replace('[]', '') + '_rotation[]';

                            // Simplified approach: rely on the fact that seatKey is used for data attributes primarily
                            // and constructing the input name.
                            // Let's stick to the existing logic but make it robust for cabins

                            seatKey = inputName.replace('wbtm_', '').replace('[]', '');
                            // Check if it has template_
                            let isTemplate = seatKey.indexOf('template_') === 0;

                            let rotationControls = `
                                <div class="wbtm_seat_rotation_controls">
                                  <button type="button" class="wbtm_rotate_seat _whiteButton_xs" 
                                          data-seat-key="${seatKey.replace('template_', '')}" 
                                          data-rotation="0"
                                          title="Rotate Seat">
                                    <span class="fas fa-redo-alt mp_zero"></span>
                                  </button>
                                  <input type="hidden" name="wbtm_${seatKey}_rotation[]" 
                                         value="0" 
                                         class="wbtm_rotation_value" 
                                         ${isTemplate ? 'disabled' : ''} />
                                </div>
                              `;
                            $container.append(rotationControls);
                        }
                    }
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
        if ($rotationToggle.first().is(':checked')) {
            $('.wbtm_seat_plan_settings').addClass('wbtm_enable_rotation');
            $('.wbtm_cabin_settings_area').addClass('wbtm_enable_rotation');
            $('.wbtm_seat_rotation_controls').show();
        }
    });

    // Seat Type Palette Drag & Drop Logic
    $(document).on('dragstart', '.wbtm_palette_item', function (e) {
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('value'));
        e.originalEvent.dataTransfer.effectAllowed = 'copy';
        $(this).css('opacity', '0.5');
    });

    $(document).on('dragend', '.wbtm_palette_item', function (e) {
        $(this).css('opacity', '1');
    });

    $(document).on('dragover', '.wbtm_seat_container', function (e) {
        e.preventDefault(); // Necessary to allow dropping
        e.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('drag-over');
    });

    $(document).on('dragleave', '.wbtm_seat_container', function (e) {
        $(this).removeClass('drag-over');
    });

    $(document).on('drop', '.wbtm_seat_container', function (e) {
        e.preventDefault();
        $(this).removeClass('drag-over');

        var value = e.originalEvent.dataTransfer.getData('text/plain');
        var input = $(this).find('input[class*="wbtm_id_validation"]');

        if (input.length) {
            // Handle reserved items - prepend "reserved:" to existing seat name instead of replacing it
            if (value === 'reserved:' || value.startsWith('reserved:')) {
                var currentValue = input.val().trim();
                // If seat already has a name, prepend "reserved:" to keep the seat name visible
                if (currentValue && !currentValue.toLowerCase().startsWith('reserved')) {
                    input.val('reserved:' + currentValue).trigger('change');
                } else if (!currentValue) {
                    // If empty, just set to "reserved:" (user can add seat name manually)
                    input.val(value).trigger('change');
                } else {
                    // Already has reserved prefix, just update if needed
                    input.val(value).trigger('change');
                }
            } else {
                // For other items (door, wc, food, etc.), replace the value as before
                input.val(value).trigger('change');
            }

            // Visual feedback
            input.css('background-color', '#e3f2fd');
            setTimeout(function () {
                input.css('background-color', '');
            }, 500);
        }
    });

})(jQuery);
