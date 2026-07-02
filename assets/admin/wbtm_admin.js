//==========Price settings=================//
(function ($) {
    "use strict";
    function wbtm_collect_ticket_types(parent) {
        let ticketTypes = [];
        parent.find('.wbtm_ticket_type_item').each(function () {
            let label = $(this).find('[name="wbtm_ticket_type_label[]"]').val();
            if (!label) {
                return;
            }
            ticketTypes.push({
                id: $(this).find('[name="wbtm_ticket_type_id[]"]').val(),
                label: label
            });
        });
        return ticketTypes;
    }
    function wbtm_collect_price_map(parent) {
        let priceMap = {};
        parent.find('.wbtm_price_setting_area tr[data-price-key]').each(function () {
            let routeKey = $(this).attr('data-price-key');
            if (!routeKey) {
                return;
            }
            priceMap[routeKey] = {};
            $(this).find('[data-ticket-type]').each(function () {
                priceMap[routeKey][$(this).attr('data-ticket-type')] = $(this).val();
            });
            priceMap[routeKey].__full_bus = $(this).find('[data-full-bus-price]').val() || "";
            priceMap[routeKey].__full_bus_discount = $(this).find('[data-full-bus-discount]').val() || "";
        });
        return priceMap;
    }
    function wbtm_reload_pricing(parent) {
        let post_id = $('[name="wbtm_post_id"]').val();
        let target = parent.find(".wbtm_price_setting_area");
        let places = {};
        let types = {};
        let ticketTypes = wbtm_collect_ticket_types(parent);
        let priceMap = wbtm_collect_price_map(parent);
        let count = 0;
        parent.find(".wbtm_stop_item").each(function () {
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
                types[count - 1]= "dp";
                $.ajax({
                    type: "POST",
                    url: wbtm_admin_var.url,
                    data: {
                        action: "wbtm_reload_pricing",
                        post_id: post_id,
                        places: places,
                        types: types,
                        ticket_types_json: JSON.stringify(ticketTypes),
                        price_map_json: JSON.stringify(priceMap),
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
        ".wbtm_settings_pricing_routing .wbtm_stop_item .wbtm_item_remove, .wbtm_settings_pricing_routing .wbtm_ticket_type_item .wbtm_item_remove",
        function () {
            setTimeout(function () {
                wbtm_reload_pricing($(".wbtm_settings_pricing_routing"));
            }, 300);
        }
    );
    $(document).on(
        "change",
        '.wbtm_settings_pricing_routing [name="wbtm_route_place[]"], .wbtm_settings_pricing_routing [name="wbtm_route_type[]"], .wbtm_settings_pricing_routing [name="wbtm_ticket_type_label[]"]',
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
                        // Numbered per whichever "Seat Numbering" scheme is
                        // currently selected, with an optional single aisle
                        // inserted at the chosen "Aisle Position" — no full
                        // template needed for a simple custom layout.
                        if (window.wbtmSeatNumbering) {
                            let numbering = parent.find('.wbtm_seat_template_picker[data-scope=""] .wbtm_seat_numbering_select').val() || 'sequential';
                            let aislePos = parseInt(parent.find('[name="wbtm_seat_aisle_after_col"]').val()) || 0;
                            let pattern = window.wbtmSeatNumbering.buildAislePattern(column, aislePos);
                            window.wbtmSeatNumbering.fill(target, pattern, numbering);
                        }
                        $(document).trigger("wbtm_seat_plan_dom_updated");
                        //wbtm_loaderRemove(parent);
                    },
                    error: function (response) {
                        console.log(response);
                    },
                });
            } else {
                alert(typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_row_col_error ? wbtm_admin_var.seat_row_col_error : 'Number of rows & columns must be greater than 0');
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
                        // Numbered per whichever "Seat Numbering" scheme is
                        // currently selected, with an optional single aisle
                        // inserted at the chosen "Aisle Position" — no full
                        // template needed for a simple custom layout.
                        if (window.wbtmSeatNumbering) {
                            let numbering = parent.find('.wbtm_seat_template_picker[data-scope="_dd"] .wbtm_seat_numbering_select').val() || 'sequential';
                            let aislePos = parseInt(parent.find('[name="wbtm_seat_aisle_after_col_dd"]').val()) || 0;
                            let pattern = window.wbtmSeatNumbering.buildAislePattern(column, aislePos);
                            window.wbtmSeatNumbering.fill(target, pattern, numbering);
                        }
                        $(document).trigger("wbtm_seat_plan_dom_updated");
                        //wbtm_loaderRemove(parent);
                    },
                    error: function (response) {
                        console.log(response);
                    },
                });
            } else {
                alert(typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_row_col_error ? wbtm_admin_var.seat_row_col_error : 'Number of rows & columns must be greater than 0');
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
    // Applies (or removes) the rotation-controls display state on a single
    // scope — either one deck's own .wbtm_settings_area, or the cabin area.
    function wbtmApplyRotationState($scopeContainer, enabled) {
        if (!$scopeContainer || !$scopeContainer.length) { return; }
        if (enabled) {
            $scopeContainer.addClass('wbtm_enable_rotation');
            $scopeContainer.find('.wbtm_seat_rotation_controls').show();
            // Add rotation controls to existing seats if they don't have them
            $scopeContainer.find('.wbtm_seat_container').each(function () {
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
            $scopeContainer.removeClass('wbtm_enable_rotation');
            $scopeContainer.find('.wbtm_seat_rotation_controls').hide();
        }
    }
    // Handle rotation setting toggle — each deck now has its OWN independent
    // checkbox (wbtm_enable_seat_rotation for lower, _dd for upper), rendered
    // inline next to that deck's own "Add New Row" button (create_seat_plan()
    // in WBTM_Seat_Configuration.php). Toggling one only affects its own
    // deck's grid — never both. Cabin mode has no checkbox of its own and has
    // always followed the lower-deck key, so that coupling is preserved.
    $(document).on('change', 'input[name="wbtm_enable_seat_rotation"], input[name="wbtm_enable_seat_rotation_dd"]', function () {
        let isEnabled = $(this).is(':checked');
        let isLowerDeck = $(this).attr('name') === 'wbtm_enable_seat_rotation';
        wbtmApplyRotationState($(this).closest('.wbtm_settings_area'), isEnabled);
        if (isLowerDeck) {
            wbtmApplyRotationState($('.wbtm_cabin_settings_area'), isEnabled);
        }
    });
    // Initialize rotation state on page load, per deck independently. (Note:
    // WBTM_Seat_Configuration::create_seat_plan() already bakes the
    // wbtm_enable_rotation class into the server-rendered HTML, so this is a
    // defensive re-sync — e.g. for older saved seats missing the controls.)
    $(document).ready(function () {
        $('input[name="wbtm_enable_seat_rotation"]').each(function () {
            let isEnabled = $(this).is(':checked');
            wbtmApplyRotationState($(this).closest('.wbtm_settings_area'), isEnabled);
            wbtmApplyRotationState($('.wbtm_cabin_settings_area'), isEnabled);
        });
        $('input[name="wbtm_enable_seat_rotation_dd"]').each(function () {
            wbtmApplyRotationState($(this).closest('.wbtm_settings_area'), $(this).is(':checked'));
        });
    });
})(jQuery);
//==========Seat Plan Drag & Drop Non-Seat Items=================//
(function ($) {
    "use strict";

    // Per-seat PRICE OVERRIDE stays Pro-gated; the drag-and-drop TOOLBAR
    // (door/toilet/driver/etc.) is a free-plugin feature — separate flags.
    let proSeatFeaturesEnabled = !!(typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.pro_seat_features_enabled);
    let seatToolbarEnabled = !!(typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_toolbar_enabled);
    let nonSeatItems = (typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.non_seat_items) ? wbtm_admin_var.non_seat_items : {};
    let wbtmDblClickRemoveTitle = (typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.nonseat_badge_title) ? wbtm_admin_var.nonseat_badge_title : 'Double click to Remove';

    function isNonSeatItem(val) {
        return val && nonSeatItems.hasOwnProperty(val.toLowerCase().trim());
    }

    function getNonSeatIcon(val) {
        let key = val.toLowerCase().trim();
        return nonSeatItems[key] || '';
    }

    function applyBadge($container, itemType) {
        $container.find('.wbtm_nonseat_badge').remove();
        if (!seatToolbarEnabled) {
            $container.removeClass('wbtm_has_nonseat');
            return;
        }
        if (!itemType) {
            $container.removeClass('wbtm_has_nonseat');
            return;
        }
        let icon = getNonSeatIcon(itemType);
        if (!icon) return;
        let $badge = $('<span class="wbtm_nonseat_badge" title="' + wbtmDblClickRemoveTitle + '"><span class="fas ' + icon + '"></span></span>');
        $container.addClass('wbtm_has_nonseat').append($badge);
    }

    function refreshAllBadges($scope) {
        if (!seatToolbarEnabled) {
            ($scope || $(document)).find('.wbtm_nonseat_badge').remove();
            ($scope || $(document)).find('.wbtm_seat_container').removeClass('wbtm_has_nonseat');
            return;
        }
        ($scope || $(document)).find('.wbtm_seat_container').each(function () {
            let $c = $(this);
            let val = $c.find('input.formControl').val();
            if (val && isNonSeatItem(val)) {
                applyBadge($c, val);
            } else {
                $c.find('.wbtm_nonseat_badge').remove();
                $c.removeClass('wbtm_has_nonseat');
            }
        });
    }

    function initDragDrop($scope) {
        if (!seatToolbarEnabled) {
            return;
        }
        $scope = $scope || $(document);

        $scope.find('.wbtm_draggable_item').each(function () {
            if ($(this).hasClass('ui-draggable')) return;
            $(this).draggable({
                helper: 'clone',
                appendTo: 'body',
                zIndex: 10000,
                revert: 'invalid',
                revertDuration: 200,
                cursor: 'grabbing',
                start: function () {
                    $('.wbtm_seat_container').addClass('wbtm_drop_highlight');
                },
                stop: function () {
                    $('.wbtm_seat_container').removeClass('wbtm_drop_highlight');
                }
            });
        });

        $scope.find('.wbtm_seat_container').each(function () {
            if ($(this).hasClass('ui-droppable')) return;
            $(this).droppable({
                accept: '.wbtm_draggable_item',
                hoverClass: 'wbtm_drop_hover',
                tolerance: 'pointer',
                drop: function (event, ui) {
                    let itemType = ui.draggable.attr('data-item-type');
                    let $input = $(this).find('input.formControl');
                    $input.val(itemType).trigger('change');
                    applyBadge($(this), itemType);
                }
            });
        });

        refreshAllBadges($scope);
    }

    // General robustness fix: re-init drag/drop + badges any time the seat
    // grid is (re)rendered, via the event both "Generate Bus Seat" and the
    // new seat-template flow below already fire on AJAX success. This is a
    // reliable alternative to the 'DOMNodeInserted' listener further down,
    // which some browsers no longer fire (that listener is left in place for
    // older browsers; this one guarantees it works everywhere).
    $(document).on('wbtm_seat_plan_dom_updated', function () {
        initDragDrop();
        refreshAllBadges();
    });

    //==========Predefined Seat Template=================//
    // Fills 'A1' style row letters: 0 -> A, 1 -> B ... 25 -> Z, 26 -> AA ...
    function wbtmSeatTemplateRowLetter(n) {
        let s = '';
        n = n + 1;
        while (n > 0) {
            let rem = (n - 1) % 26;
            s = String.fromCharCode(65 + rem) + s;
            n = Math.floor((n - 1) / 26);
        }
        return s;
    }

    // Walks the freshly (re)generated grid and fills each cell per the
    // template's repeating column pattern + chosen numbering scheme. Every
    // cell is set via the SAME input.formControl the admin would type into
    // by hand, so the result stays fully editable — no new data shape.
    function fillSeatTemplateValues($target, pattern, numbering) {
        let seq = 0;
        $target.find('tr.wbtm_remove_area').each(function (rowIndex) {
            let rowLetter = wbtmSeatTemplateRowLetter(rowIndex);
            let seatInRow = 0;
            $(this).find('.wbtm_seat_container').each(function (colIndex) {
                let cellType = pattern[colIndex % pattern.length];
                let $input = $(this).find('input.formControl');
                let value;
                if (cellType === 'seat') {
                    if (numbering === 'row_letter') {
                        seatInRow++;
                        value = rowLetter + seatInRow;
                    } else {
                        seq++;
                        value = String(seq);
                    }
                } else {
                    value = cellType; // e.g. 'aisle' — an existing non-seat toolbar keyword
                }
                $input.val(value).trigger('change');
            });
        });
    }

    // Builds a plain column pattern with a single optional aisle inserted
    // right after column `aislePos` (1-based, "left to right"). aislePos = 0
    // (or out of range) means no aisle — every column is just a seat.
    function wbtmBuildAislePattern(column, aislePos) {
        let pattern = [];
        for (let i = 0; i < column; i++) {
            pattern.push('seat');
        }
        if (aislePos > 0 && aislePos < column) {
            pattern[aislePos] = 'aisle';
        }
        return pattern;
    }

    // Exposed so the plain "Generate Bus Seat" / "Create seat Plan" handlers
    // (separate IIFE above) can auto-number too, using whichever numbering
    // scheme + aisle position is currently selected.
    window.wbtmSeatNumbering = {
        fill: fillSeatTemplateValues,
        buildAislePattern: wbtmBuildAislePattern
    };

    // Regenerates the grid through the SAME AJAX action the manual "Generate
    // Bus Seat" button uses (wbtm_create_seat_plan / _dd), then auto-fills
    // it. $picker is the .wbtm_seat_template_picker wrapper that was clicked
    // from, so lower-deck and upper-deck pickers never cross-read each other.
    function applySeatTemplate($picker) {
        let scope = $picker.data('scope') || '';
        let templateKey = $picker.find('.wbtm_seat_template_select').val();
        let numbering = $picker.find('.wbtm_seat_numbering_select').val();
        let templates = (typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_templates) ? wbtm_admin_var.seat_templates : {};
        let pattern = templateKey ? templates[templateKey] : null;

        if (!pattern) {
            alert((typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_template_pick_error) ? wbtm_admin_var.seat_template_pick_error : 'Please choose a seat template first.');
            return;
        }

        let parent = $('.wbtm_settings_seat');
        let row = parseInt(parent.find('[name="wbtm_seat_rows' + scope + '"]').val());
        let column = pattern.length;

        if (!(row > 0)) {
            alert((typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.seat_row_col_error) ? wbtm_admin_var.seat_row_col_error : 'Number of rows & columns must be greater than 0');
            return;
        }

        // Columns are derived from the template — reflect that back into the
        // (still fully editable) Seat Columns field before generating.
        parent.find('[name="wbtm_seat_cols' + scope + '"]').val(column);

        let target = parent.find(scope === '_dd' ? '.wbtm_seat_plan_preview_dd' : '.wbtm_seat_plan_preview');
        let post_id = $('[name="wbtm_post_id"]').val();
        let action = scope === '_dd' ? 'wbtm_create_seat_plan_dd' : 'wbtm_create_seat_plan';

        $.ajax({
            type: 'POST',
            url: wbtm_admin_var.url,
            data: {
                action: action,
                post_id: post_id,
                row: row,
                column: column,
                nonce: wbtm_admin_var.nonce
            },
            beforeSend: function () {
                wbtm_loader(target);
            },
            success: function (data) {
                parent.find('[name="wbtm_seat_cols' + scope + '_hidden"]').val(column);
                parent.find('[name="wbtm_seat_rows' + scope + '_hidden"]').val(row);
                target.html(data);
                fillSeatTemplateValues(target, pattern, numbering);
                $(document).trigger('wbtm_seat_plan_dom_updated');
            },
            error: function (response) {
                console.log(response);
            }
        });
    }

    $(document).on('click', '.wbtm_apply_seat_template', function () {
        applySeatTemplate($(this).closest('.wbtm_seat_template_picker'));
    });

    // Toggle between "no template" mode (Seat Columns + Aisle Position +
    // Generate Bus Seat button) and "template chosen" mode (those two fields
    // hidden — the template supplies columns/aisle — and Apply Template
    // shown instead). Never both buttons at once. Seat Rows always stays
    // visible; when it ends up the only field left it switches to a plain
    // single-line layout (wbtm-bme__seat-row-solo) instead of the cramped
    // stacked grid cell.
    function wbtmToggleSeatTemplateMode($picker) {
        if (!$picker || !$picker.length) { return; }
        let scope = $picker.data('scope') || '';
        let hasTemplate = !!$picker.find('.wbtm_seat_template_select').val();
        let $generateBtn = $picker.siblings(scope === '_dd' ? '.wbtm_create_seat_plan_dd' : '.wbtm_create_seat_plan');
        let $applyBtn = $picker.find('.wbtm_apply_seat_template');
        let $colsRow = $picker.find('[name="wbtm_seat_cols' + scope + '"]').closest('._dFlex_justifyBetween_alignCenter');
        let $aisleRow = $picker.find('[name="wbtm_seat_aisle_after_col' + scope + '"]').closest('._dFlex_justifyBetween_alignCenter');
        let $rowsRow = $picker.find('[name="wbtm_seat_rows' + scope + '"]').closest('._dFlex_justifyBetween_alignCenter');

        $generateBtn.toggle(!hasTemplate);
        $applyBtn.toggle(hasTemplate);
        $colsRow.toggle(!hasTemplate);
        $aisleRow.toggle(!hasTemplate);
        $rowsRow.toggleClass('wbtm-bme__seat-row-solo', hasTemplate);
    }

    $(document).on('change', '.wbtm_seat_template_select', function () {
        wbtmToggleSeatTemplateMode($(this).closest('.wbtm_seat_template_picker'));
    });

    function wbtmInitSeatTemplateToggles() {
        $('.wbtm_seat_template_picker').each(function () {
            wbtmToggleSeatTemplateMode($(this));
        });
    }

    $(document).ready(function () {
        initDragDrop();
        wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
        wbtmInitSeatTemplateToggles();
    });

    $(document).on('DOMNodeInserted', '.wbtm_seat_plan_preview, .wbtm_seat_plan_preview_dd, .wbtm_cabin_seat_plan', function () {
        setTimeout(function () {
            initDragDrop();
            wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
        }, 50);
    });

    $(document).on('click', '.wbtm_generate_cabin_seats', function () {
        setTimeout(function () {
            initDragDrop();
            wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
        }, 150);
    });

    $(document).on('change', '.wbtm_seat_container input.formControl', function () {
        let $c = $(this).closest('.wbtm_seat_container');
        let val = $(this).val();
        if (val && isNonSeatItem(val)) {
            applyBadge($c, val);
        } else {
            $c.find('.wbtm_nonseat_badge').remove();
            $c.removeClass('wbtm_has_nonseat');
        }
        wbtmSyncSeatPriceButtonState($c);
        if ($(this).closest('.wbtm_settings_seat').length) {
            wbtmSyncSeatPriceBadges($(this).closest('.wbtm_settings_seat'));
        }
    });

    $(document).on('dblclick', '.wbtm_nonseat_badge', function () {
        let $c = $(this).closest('.wbtm_seat_container');
        $c.find('input.formControl').val('').trigger('change');
        $(this).remove();
        $c.removeClass('wbtm_has_nonseat');
    });

    // Per-seat ticket price overrides (Seat Configuration admin).
    function wbtmGetOverridesField() {
        let $scoped = $('.wbtm_settings_seat #wbtm_seat_price_overrides_field');
        if ($scoped.length) {
            return $scoped;
        }
        return $('#wbtm_seat_price_overrides_field');
    }
    function wbtmReadOverridesState() {
        let $f = wbtmGetOverridesField();
        if (!$f.length) {
            return {};
        }
        try {
            let parsed = JSON.parse($f.val() || '{}');
            if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
                return {};
            }
            return parsed;
        } catch (err) {
            return {};
        }
    }
    function wbtmWriteOverridesState(obj) {
        let $f = wbtmGetOverridesField();
        if ($f.length) {
            $f.val(JSON.stringify(obj));
        }
    }
    function wbtmSeatPriceOverrideFeatureEnabled() {
        if (!proSeatFeaturesEnabled) {
            return false;
        }
        return $('input[name="wbtm_enable_seat_price_override"]').is(':checked');
    }
    function wbtmBuildScopeKey(scope, cabinIndex, seatName) {
        seatName = (seatName || '').trim();
        if (!seatName) {
            return '';
        }
        if (scope === 'c') {
            return 'c|' + String(parseInt(cabinIndex, 10)) + '|' + seatName;
        }
        return scope + '|' + seatName;
    }
    function wbtmGetSavedPriceForType(row, typeId) {
        if (!row || typeof row !== 'object') {
            return '';
        }
        let id = String(typeId);
        if (Object.prototype.hasOwnProperty.call(row, id)) {
            let v = row[id];
            if (v != null && String(v) !== '') {
                return String(v);
            }
        }
        return '';
    }
    function wbtmCountOverridesForKey(all, key) {
        if (!key || !all || typeof all !== 'object' || !all[key] || typeof all[key] !== 'object') {
            return 0;
        }
        let row = all[key];
        let n = 0;
        $.each(row, function (tid, val) {
            if (val !== '' && val !== null && val !== undefined && !isNaN(parseFloat(val))) {
                n++;
            }
        });
        return n;
    }
    function wbtmSyncSeatPriceButtonState($container) {
        let $btn = $container.find('.wbtm_seat_price_view').first();
        if (!$btn.length || $btn.attr('data-price-view-template') === '1') {
            return false;
        }
        let seatName = $.trim($container.find('input.formControl').first().val() || '');
        let featureEnabled = wbtmSeatPriceOverrideFeatureEnabled();
        let isNonSeat = seatName && isNonSeatItem(seatName);
        let isDisabled = !featureEnabled || !!isNonSeat;
        let defaultTitle = $btn.attr('data-default-title') || '';
        let disabledTitle = $btn.attr('data-disabled-title') || defaultTitle;
        let featureDisabledTitle = $btn.attr('data-feature-disabled-title') || defaultTitle;
        $btn.attr('data-seat-price-feature-enabled', featureEnabled ? '1' : '0');
        $btn.prop('disabled', isDisabled);
        $btn.toggleClass('wbtm_seat_price_view_disabled', isDisabled);
        if (!featureEnabled) {
            $btn.attr('title', featureDisabledTitle);
        } else {
            $btn.attr('title', isNonSeat ? disabledTitle : defaultTitle);
        }
        if (isDisabled) {
            $btn.find('.wbtm_seat_price_badge').remove();
        }
        return isDisabled;
    }
    function wbtmSyncSeatPriceBadges($root) {
        $root = $root && $root.length ? $root : $('.wbtm_settings_seat');
        if (!$root || !$root.length) {
            return;
        }
        let all = wbtmReadOverridesState();
        $root.find('.wbtm_seat_price_view').each(function () {
            let $btn = $(this);
            if ($btn.attr('data-price-view-template') === '1') {
                $btn.find('.wbtm_seat_price_badge').remove();
                return;
            }
            let $container = $btn.closest('.wbtm_seat_container');
            if (wbtmSyncSeatPriceButtonState($container)) {
                return;
            }
            let seatName = $.trim($container.find('input.formControl').first().val() || '');
            if (!seatName) {
                $btn.find('.wbtm_seat_price_badge').remove();
                return;
            }
            let scope = $btn.attr('data-override-scope') || 'l';
            let cabinIdx = $btn.attr('data-cabin-index');
            let key = wbtmBuildScopeKey(scope, cabinIdx, seatName);
            let c = wbtmCountOverridesForKey(all, key);
            let $badge = $btn.find('.wbtm_seat_price_badge');
            if (c > 0) {
                if (!$badge.length) {
                    $badge = $('<span class="wbtm_seat_price_badge" aria-hidden="true"></span>');
                    $btn.append($badge);
                }
                $badge.text(String(c));
            } else {
                $badge.remove();
            }
        });
    }

    $(document).on('wbtm_seat_plan_dom_updated', function () {
        wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
    });
    $(document).on('change', 'input[name="wbtm_enable_seat_price_override"]', function () {
        wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
        if (!wbtmSeatPriceOverrideFeatureEnabled()) {
            $('#wbtm_seat_price_modal').hide();
            wbtmPriceModalKey = '';
        }
    });

    var wbtmPriceModalKey = '';
    $(document).on('click', '.wbtm_seat_price_view:not(:disabled)', function (e) {
        e.preventDefault();
        if ($(this).hasClass('wbtm_seat_price_view_disabled')) {
            return;
        }
        let ticketTypes = (typeof wbtm_admin_var !== 'undefined' && wbtm_admin_var.ticket_types) ? wbtm_admin_var.ticket_types : [];
        if (!ticketTypes.length) {
            alert((wbtm_admin_var && wbtm_admin_var.seat_price_no_types) ? wbtm_admin_var.seat_price_no_types : '');
            return;
        }
        let $btn = $(this);
        let $container = $btn.closest('.wbtm_seat_container');
        let seatName = $.trim($container.find('input.formControl').first().val() || '');
        if (!seatName) {
            alert((wbtm_admin_var && wbtm_admin_var.seat_price_need_name) ? wbtm_admin_var.seat_price_need_name : '');
            return;
        }
        let scope = $btn.attr('data-override-scope') || 'l';
        let cabinIdx = $btn.attr('data-cabin-index');
        wbtmPriceModalKey = wbtmBuildScopeKey(scope, cabinIdx, seatName);
        if (!wbtmPriceModalKey) {
            return;
        }
        let all = wbtmReadOverridesState();
        let row = all[wbtmPriceModalKey] || {};
        let $modal = $('#wbtm_seat_price_modal');
        let $body = $modal.find('.wbtm_seat_price_modal_body');
        $body.empty();
        $modal.find('.wbtm_seat_price_modal_seat_name').text(seatName);
        ticketTypes.forEach(function (tt) {
            let v = wbtmGetSavedPriceForType(row, tt.id);
            let $r = $('<div class="wbtm_seat_price_row"/>');
            $r.append($('<label class="wbtm_seat_price_label"/>').text(tt.label + ' (' + tt.id + ')'));
            let $inp = $('<input type="number" class="formControl wbtm_seat_price_input" step="0.01" min="0"/>');
            $inp.attr('data-type-id', String(tt.id));
            if (v !== '') {
                $inp.val(v);
            }
            $r.append($inp);
            $body.append($r);
        });
        $modal.show();
    });
    $(document).on('click', '.wbtm_seat_price_modal_close, .wbtm_seat_price_modal_cancel, .wbtm_seat_price_modal_overlay', function (e) {
        e.preventDefault();
        $('#wbtm_seat_price_modal').hide();
    });
    $(document).on('click', '.wbtm_seat_price_modal_save', function (e) {
        e.preventDefault();
        if (!wbtmPriceModalKey) {
            return;
        }
        let all = wbtmReadOverridesState();
        let newRow = {};
        $('#wbtm_seat_price_modal .wbtm_seat_price_input').each(function () {
            let tid = $(this).attr('data-type-id');
            let val = $.trim($(this).val());
            if (val !== '' && !isNaN(parseFloat(val))) {
                newRow[String(tid)] = String(Math.max(0, parseFloat(val)));
            }
        });
        if ($.isEmptyObject(newRow)) {
            delete all[wbtmPriceModalKey];
        } else {
            all[wbtmPriceModalKey] = newRow;
        }
        wbtmWriteOverridesState(all);
        wbtmSyncSeatPriceBadges($('.wbtm_settings_seat'));
        $('#wbtm_seat_price_modal').hide();
    });
})(jQuery);
