function wbtm_load_sortable_datepicker(parent, item) {
    if (parent.find(".wbtm_item_insert_before").length > 0) {
        jQuery(item)
            .insertBefore(parent.find(".wbtm_item_insert_before").first())
            .promise()
            .done(function () {
                parent.find(".wbtm_sortable_area").sortable({
                    handle: jQuery(this).find(".wbtm_sortable_button"),
                });
                wbtm_load_date_picker(parent);
            });
    } else {
        parent
            .find(".wbtm_item_insert")
            .first()
            .append(item)
            .promise()
            .done(function () {
                parent.find(".wbtm_sortable_area").sortable({
                    handle: jQuery(this).find(".wbtm_sortable_button"),
                });
                wbtm_load_date_picker(parent);
            });
    }
    return true;
}
(function ($) {
    "use strict";
    $(document).ready(function () {
        //=========Short able==============//
        $(document)
            .find(".wbtm_sortable_area")
            .sortable({
                handle: $(this).find(".wbtm_sortable_button"),
            });
    });
    //=========upload image==============//
    $(document).on("click", ".wbtm_add_single_image", function () {
        let parent = $(this);
        parent.find(".wbtm_single_image_item").remove();
        wp.media.editor.send.attachment = function (props, attachment) {
            let attachment_id = attachment.id;
            let attachment_url = attachment.url;
            let html =
                '<div class="wbtm_single_image_item" data-image-id="' +
                attachment_id +
                '"><span class="fas fa-times circleIcon_xs wbtm_remove_single_image"></span>';
            html += '<img src="' + attachment_url + '" alt="' + attachment_id + '"/>';
            html += "</div>";
            parent.append(html);
            parent.find("input").val(attachment_id);
            parent.find("button").slideUp("fast");
        };
        wp.media.editor.open($(this));
        return false;
    });
    $(document).on("click", ".wbtm_remove_single_image", function (e) {
        e.stopPropagation();
        let parent = $(this).closest(".wbtm_add_single_image");
        $(this).closest(".wbtm_single_image_item").remove();
        parent.find("input").val("");
        parent.find("button").slideDown("fast");
    });
    $(document).on("click", ".wbtm_remove_multi_image", function () {
        let parent = $(this).closest(".wbtm_multi_image_area");
        let current_parent = $(this).closest(".wbtm_multi_image_item");
        let img_id = current_parent.data("image-id");
        current_parent.remove();
        let all_img_ids = parent.find(".wbtm_multi_image_value").val();
        all_img_ids = all_img_ids.replace("," + img_id, "");
        all_img_ids = all_img_ids.replace(img_id + ",", "");
        all_img_ids = all_img_ids.replace(img_id, "");
        parent.find(".wbtm_multi_image_value").val(all_img_ids);
    });
    $(document).on("click", ".add_multi_image", function () {
        let parent = $(this).closest(".wbtm_multi_image_area");
        wp.media.editor.send.attachment = function (props, attachment) {
            let attachment_id = attachment.id;
            let attachment_url = attachment.url;
            let html =
                '<div class="wbtm_multi_image_item" data-image-id="' +
                attachment_id +
                '"><span class="fas fa-times circleIcon_xs wbtm_remove_multi_image"></span>';
            html += '<img src="' + attachment_url + '" alt="' + attachment_id + '"/>';
            html += "</div>";
            parent.find(".wbtm_multi_image").append(html);
            let value = parent.find(".wbtm_multi_image_value").val();
            value = value ? value + "," + attachment_id : attachment_id;
            parent.find(".wbtm_multi_image_value").val(value);
        };
        wp.media.editor.open($(this));
        return false;
    });
    //=========Remove Setting Item ==============//
    $(document).on("click", ".wbtm_item_remove", function (e) {
        e.preventDefault();
        if (
            confirm(
                "Are You Sure , Remove this row ? \n\n 1. Ok : To Remove . \n 2. Cancel : To Cancel ."
            )
        ) {
            $(this).closest(".wbtm_remove_area").slideUp(250).remove();
            return true;
        } else {
            return false;
        }
    });
    // ==================
    $(document).ready(function () {
        addCollapseId();
    });
    function addCollapseId() {
        let collapseId = 0;
        $(".wbtm_stop_item").each(function (i) {
            $(this)
                .find(".wbtm_stop_item_header")
                .attr("data-collapse-target", "d" + i);
            $(this)
                .find(".wbtm_stop_item_content")
                .attr("data-collapse", "d" + i);
            collapseId = i++;
        });
        $(".wbtm_hidden_item .wbtm_stop_item")
            .find(".wbtm_stop_item_header")
            .attr("data-collapse-target", "d" + collapseId);
        $(".wbtm_hidden_item .wbtm_stop_item")
            .find(".wbtm_stop_item_content")
            .attr("data-collapse", "d" + collapseId);
        // input field uncollapse for last element
        // ====
    }
    //=========Add Setting Item==============//
    $(document).on("click", ".wbtm_add_item", function () {
        // on click event. add collpase id for last child
        addCollapseId();
        $(".wbtm_stop_item:last-child .wbtm_stop_item_content").css(
            "display",
            "block"
        );
        let parent = $(this).closest(".wbtm_settings_area");
        let item = $(this)
            .next($(".wbtm_hidden_content"))
            .find(" .wbtm_hidden_item")
            .html();
        if (!item || item === "undefined" || item === " ") {
            item = parent
                .find(".wbtm_hidden_content")
                .first()
                .find(".wbtm_hidden_item")
                .html();
        }
        wbtm_load_sortable_datepicker(parent, item);
        parent.find(".wbtm_item_insert").find(".wbtm_add_select2").select2({});
        return true;
    });
})(jQuery);
(function ($) {
    "use strict";
    //=================select icon=========================//
    /*$(document).on("click", ".wbtm_add_icon_image_area button.wbtm_icon_add", function () {
            let target_popup = $(".wbtm_add_icon_popup");
            target_popup.find(".iconItem").click(function () {
                let parent = $("[data-active-popup]").closest(".wbtm_add_icon_image_area");
                let icon_class = $(this).data("icon-class");
                if (icon_class) {
                    parent.find('input[type="hidden"]').val(icon_class);
                    parent.find(".wbtm_add_icon_image_button_area").slideUp("fast");
                    parent.find(".wbtm_image_item").slideUp("fast");
                    parent.find(".wbtm_icon_item").slideDown("fast");
                    parent.find("[data-add-icon]").removeAttr("class").addClass(icon_class);
                    target_popup.find(".iconItem").removeClass("active");
                    target_popup.find(".popupClose").trigger("click");
                }
            });
            target_popup.find("[data-icon-menu]").click(function () {
                if (!$(this).hasClass("active")) {
                    let target = $(this);
                    let tabsTarget = target.data("icon-menu");
                    target_popup.find("[data-icon-menu]").removeClass("active");
                    target.addClass("active");
                    target_popup.find("[data-icon-list]").each(function () {
                        let targetItem = $(this).data("icon-list");
                        if (tabsTarget === "all_item" || targetItem === tabsTarget) {
                            $(this).slideDown(250);
                        } else {
                            $(this).slideUp(250);
                        }
                    });
                }
                return false;
            });
            target_popup.find(".popupClose").click(function () {
                target_popup.find('[data-icon-menu="all_item"]').trigger("click");
                target_popup.find(".iconItem").removeClass("active");
            });
        }
    );*/

    // Popup open
    $(document).on("click", ".wbtm_icon_add", function () {
        $(".wbtm_add_icon_popup").addClass("active");
    });
    $(document).on("click", ".wbtm_add_icon_popup .popupClose", function () {
        let popup = $(".wbtm_add_icon_popup");
        popup.removeClass("active");
        popup.find(".iconItem").removeClass("active");
        popup.find('[data-icon-menu="all_item"]').click();
    });
    $(document).on("click", ".wbtm_add_icon_popup .iconItem", function () {
        let iconClass = $(this).data("icon-class");
        if (!iconClass) return;

        let parent = $(".wbtm_add_icon_image_area"); // সহজভাবে parent ধরা

        parent.find('input[type="hidden"]').val(iconClass);
        parent.find(".wbtm_add_icon_image_button_area, .wbtm_image_item").hide();
        parent.find(".wbtm_icon_item").show();
        parent.find("[data-add-icon]").attr("class", iconClass);

        $(".wbtm_add_icon_popup .popupClose").click();
    });

    $(document).on("click", ".wbtm_add_icon_popup [data-icon-menu]", function (e) {
        e.preventDefault();
        let menu = $(this);
        let target = menu.data("icon-menu");
        $(".wbtm_add_icon_popup [data-icon-menu]").removeClass("active");
        menu.addClass("active");
        $(".wbtm_add_icon_popup [data-icon-list]").each(function () {
            let list = $(this);
            let type = list.data("icon-list");

            if (target === "all_item" || target === type) {
                list.show();
            } else {
                list.hide();
            }
        });
    });


    $(document).on("click", ".wbtm_add_icon_image_area .wbtm_icon_remove", function () {
            let parent = $(this).closest(".wbtm_add_icon_image_area");
            parent.find('input[type="hidden"]').val("");
            parent.find("[data-add-icon]").removeAttr("class");
            parent.find(".wbtm_icon_item").slideUp("fast");
            parent.find(".wbtm_add_icon_image_button_area").slideDown("fast");
        }
    );
    //=================select Single image=========================//
    $(document).on("click", "button.wbtm_image_add", function () {
        let $this = $(this);
        let parent = $this.closest(".wbtm_add_icon_image_area");
        wp.media.editor.send.attachment = function (props, attachment) {
            let attachment_id = attachment.id;
            let attachment_url = attachment.url;
            parent.find('input[type="hidden"]').val(attachment_id);
            parent.find(".wbtm_icon_item").slideUp("fast");
            parent.find("img").attr("src", attachment_url);
            parent.find(".wbtm_image_item").slideDown("fast");
            parent.find(".wbtm_add_icon_image_button_area").slideUp("fast");
        };
        wp.media.editor.open($this);
        return false;
    });
    $(document).on("click", ".wbtm_add_icon_image_area .wbtm_image_remove", function () {
            let parent = $(this).closest(".wbtm_add_icon_image_area");
            parent.find('input[type="hidden"]').val("");
            parent.find("img").attr("src", "");
            parent.find(".wbtm_image_item").slideUp("fast");
            parent.find(".wbtm_add_icon_image_button_area").slideDown("fast");
        }
    );

    // Cabin configuration functionality
    $(document).on('click', '.wbtm_configure_cabins', function() {
        let parent = $(this).closest('.wbtm_settings_seat');
        let cabin_count_input = parent.find('input[name="wbtm_cabin_count"]');
        let cabin_count = parseInt(cabin_count_input.val()) || 1;

        if (cabin_count < 1 || cabin_count > 20) {
            alert('Please enter a valid number of cabins (1-20)');
            return;
        }

        // Show cabin configuration section
        parent.find('.wbtm_cabin_configuration').slideDown('fast');

        // Update the number of cabin items
        let cabin_list = parent.find('.wbtm_cabin_list');
        let current_cabin_count = cabin_list.find('.wbtm_cabin_item').length;

        if (current_cabin_count < cabin_count) {
            // Add more cabins
            for (let i = current_cabin_count; i < cabin_count; i++) {
                let cabin_html = `
                    <div class="mpPanel wbtm_cabin_item" data-cabin-index="${i}">
                        <div class="_padding_dFlex_justifyBetween_alignCenter_bgLight">
                            <div class="_dFlex_fdColumn">
                                <label>Cabin ${i + 1} Configuration</label>
                                <span>Configure seat layout for this cabin.</span>
                            </div>
                        </div>
                        <div class="mpPanelBody">
                            <div class="_dFlex">
                                <div class="col_6 _bR">
                                    <div class="_dFlex_justifyBetween_alignCenter">
                                        <label>Cabin Name</label>
                                        <input type="text" class="formControl max_200" name="wbtm_cabin_name[]" placeholder="Ex: First Class" value="Cabin ${i + 1}"/>
                                    </div>
                                    <div class="divider"></div>

                                    <div class="_dFlex_justifyBetween_alignCenter">
                                        <label>Enable Cabin</label>
                                        <input type="checkbox" name="wbtm_cabin_enabled[]" value="1" checked/>
                                    </div>
                                    <div class="divider"></div>

                                    <div class="_dFlex_justifyBetween_alignCenter">
                                        <label>Seat Rows</label>
                                        <input type="number" min="0" pattern="[0-9]*" step="1" class="formControl max_200 wbtm_number_validation" name="wbtm_cabin_rows[]" placeholder="Ex: 10" value="0"/>
                                    </div>
                                    <div class="divider"></div>

                                    <div class="_dFlex_justifyBetween_alignCenter">
                                        <label>Seat Columns</label>
                                        <input type="number" min="0" pattern="[0-9]*" step="1" class="formControl max_200 wbtm_number_validation" name="wbtm_cabin_cols[]" placeholder="Ex: 4" value="0"/>
                                    </div>
                                    <div class="divider"></div>

                                    <div class="_dFlex_justifyBetween_alignCenter">
                                        <label>Price Multiplier</label>
                                        <input type="number" min="0" step="0.01" class="formControl max_200" name="wbtm_cabin_price_multiplier[]" placeholder="Ex: 1.0" value="1.0"/>
                                        <span class="help-text">1.0 = same price, 1.2 = 20% higher, 0.8 = 20% lower</span>
                                    </div>
                                    <div class="divider"></div>

                                    <button type="button" class="button button-secondary wbtm_generate_cabin_seats" data-cabin-index="${i}">Generate Seat Plan</button>
                                </div>
                                <div class="col_6">
                                    <div class="wbtm_cabin_seat_preview" data-cabin-index="${i}">
                                        <label>Cabin ${i + 1} Preview</label>
                                        <div class="wbtm_cabin_seat_plan">
                                            <!-- Seat plan will be generated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                cabin_list.append(cabin_html);
            }
        } else if (current_cabin_count > cabin_count) {
            // Remove excess cabins
            cabin_list.find('.wbtm_cabin_item:gt(' + (cabin_count - 1) + ')').remove();
        }
    });

    // Generate cabin seat plan
    $(document).on('click', '.wbtm_generate_cabin_seats', function() {
        let button = $(this);
        let cabin_item = button.closest('.wbtm_cabin_item');
        let cabin_index = button.attr('data-cabin-index');
        let rows_input = cabin_item.find('input[name="wbtm_cabin_rows[]"]');
        let cols_input = cabin_item.find('input[name="wbtm_cabin_cols[]"]');
        let rows = parseInt(rows_input.val()) || 0;
        let cols = parseInt(cols_input.val()) || 0;

        if (rows <= 0 || cols <= 0) {
            alert('Please enter valid row and column numbers');
            return;
        }

        // Generate seat plan preview
        let seat_plan_html = `
            <table>
                <thead>
                    <tr>
                        <th colspan="${cols}">
                            <div class="wbtm_cabin_direction">
                                <span class="wbtm_direction_text">Front</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
        `;

        for (let i = 0; i < rows; i++) {
            seat_plan_html += '<tr>';
            for (let j = 1; j <= cols; j++) {
                seat_plan_html += `
                    <th>
                        <div class="wbtm_seat_container">
                            <label>
                                <input type="text" class="formControl wbtm_id_validation"
                                    name="wbtm_cabin_${cabin_index}_seat${j}[]"
                                    placeholder="Blank"
                                    value=""
                                />
                            </label>
                            <div class="wbtm_seat_rotation_controls">
                                <button type="button" class="wbtm_rotate_seat _whiteButton_xs" 
                                        data-seat="cabin_${cabin_index}_seat${j}" 
                                        data-rotation="0"
                                        title="Rotate Seat">
                                    <span class="fas fa-redo"></span>
                                </button>
                                <input type="hidden" name="wbtm_cabin_${cabin_index}_seat${j}_rotation[]" 
                                       value="0" 
                                       class="wbtm_rotation_value" />
                            </div>
                        </div>
                    </th>
                `;
            }
            seat_plan_html += `<th>
                <div class="allCenter">
                    <div class="buttonGroup max_100">
                        <button class="_whiteButton_xs wbtm_item_remove" type="button">
                            <span class="fas fa-trash-alt mp_zero"></span>
                        </button>
                        <button class="_whiteButton_xs wbtm_sortable_button" type="button">
                            <span class="fas fa-arrows-alt mp_zero"></span>
                        </button>
                    </div>
                </div>
            </th></tr>`;
        }

        seat_plan_html += `
                </tbody>
            </table>
        `;

        // Update the row count input
        let generate_rows_input = cabin_item.find('input[name="wbtm_cabin_rows[]"]');
        if (generate_rows_input.length > 0) {
            generate_rows_input.val(rows);
        }

        cabin_item.find('.wbtm_cabin_seat_plan').html(seat_plan_html);
    });

    // Handle row deletion for cabin seat plans
    $(document).on('click', '.wbtm_cabin_seat_plan .wbtm_item_remove', function() {
        let button = $(this);
        let cabin_item = button.closest('.wbtm_cabin_item');
        let cabin_index = cabin_item.attr('data-cabin-index');

        if (!cabin_index) {
            console.error('Cabin index not found');
            return;
        }

        let delete_rows_input = cabin_item.find('input[name="wbtm_cabin_rows[]"]');
        if (delete_rows_input.length > 0) {
            let current_rows = parseInt(delete_rows_input.val()) || 0;

            if (current_rows > 0) {
                delete_rows_input.val(current_rows - 1);
            }
        }

        // Remove the table row
        button.closest('tr').remove();
    });

    // Handle adding new rows for cabin seat plans
    $(document).on('click', '.wbtm_cabin_seat_plan .wbtm_add_item', function() {
        let button = $(this);
        let cabin_item = button.closest('.wbtm_cabin_item');
        let cabin_index = cabin_item.attr('data-cabin-index');

        if (!cabin_index) {
            console.error('Cabin index not found');
            return;
        }

        let hidden_content = cabin_item.find('.wbtm_cabin_hidden_content');
        let seat_plan_area = cabin_item.find('.wbtm_cabin_seat_plan tbody');
        let cols_input = cabin_item.find('input[name="wbtm_cabin_cols[]"]');
        let cols = parseInt(cols_input.val()) || 0;

        if (cols <= 0) {
            alert('Please set the number of columns first');
            return;
        }

        // Generate new row HTML directly
        let new_row = '<tr class="wbtm_remove_area">';
        for (let j = 1; j <= cols; j++) {
            new_row += `
                <th>
                    <div class="wbtm_seat_container">
                        <label>
                            <input type="text" class="formControl wbtm_id_validation"
                                name="wbtm_cabin_${cabin_index}_seat${j}[]"
                                placeholder="Blank"
                                value=""
                            />
                        </label>
                        <div class="wbtm_seat_rotation_controls">
                            <button type="button" class="wbtm_rotate_seat _whiteButton_xs" 
                                    data-seat="cabin_${cabin_index}_seat${j}" 
                                    data-rotation="0"
                                    title="Rotate Seat">
                                <span class="fas fa-redo"></span>
                            </button>
                            <input type="hidden" name="wbtm_cabin_${cabin_index}_seat${j}_rotation[]" 
                                   value="0" 
                                   class="wbtm_rotation_value" />
                        </div>
                    </div>
                </th>
            `;
        }
        new_row += `
            <th>
                <div class="allCenter">
                    <div class="buttonGroup max_100">
                        <button class="_whiteButton_xs wbtm_item_remove" type="button">
                            <span class="fas fa-trash-alt mp_zero"></span>
                        </button>
                        <button class="_whiteButton_xs wbtm_sortable_button" type="button">
                            <span class="fas fa-arrows-alt mp_zero"></span>
                        </button>
                    </div>
                </div>
            </th>
        </tr>`;

        seat_plan_area.append(new_row);

        // Update the row count
        let add_rows_input = cabin_item.find('input[name="wbtm_cabin_rows[]"]');
        if (add_rows_input.length > 0) {
            let current_rows = parseInt(add_rows_input.val()) || 0;
            add_rows_input.val(current_rows + 1);
        }
    });

    // Handle cabin enable/disable toggle (individual cabins)
    function toggleCabinFields(checkbox) {
        let cabin_item = checkbox.closest('.wbtm_cabin_item');
        let cabin_fields = cabin_item.find('.wbtm_cabin_fields');
        
        if (checkbox.is(':checked')) {
            cabin_fields.show();
        } else {
            cabin_fields.hide();
            
            // When any individual cabin is disabled, automatically disable the main cabin mode
            let cabin_mode_checkbox = $('input[name="wbtm_cabin_mode_enabled"]');
            if (cabin_mode_checkbox.is(':checked')) {
                cabin_mode_checkbox.data('programmatic-change', true).prop('checked', false).trigger('change');
            }
        }
    }

    // Handle master cabin mode enable/disable toggle
    function toggleCabinModeFields(checkbox) {
        let cabin_mode_fields = $('.wbtm_cabin_mode_fields');
        let traditional_seat_plan = $('[data-collapse="#wbtm_seat_plan"]');
        let seat_type_select = $('select[name="wbtm_seat_type_conf"]');
        let seat_type = seat_type_select.val();
        
        if (checkbox.is(':checked')) {
            // Auto-select "Seat Plan" when cabin mode is enabled
            if (seat_type !== 'wbtm_seat_plan') {
                seat_type_select.val('wbtm_seat_plan').trigger('change');
                seat_type = 'wbtm_seat_plan';
            }
            
            // Show cabin configuration
            cabin_mode_fields.slideDown(300);
            // Hide traditional seat plan since we're now in seat plan mode with cabin config
            traditional_seat_plan.slideUp(300);
        } else {
            // When cabin mode is disabled, automatically disable all individual cabin toggles
            $('input[name="wbtm_cabin_enabled[]"]').each(function() {
                if ($(this).is(':checked')) {
                    $(this).data('programmatic-change', true).prop('checked', false).trigger('change');
                }
            });
            
            // Hide cabin configuration
            cabin_mode_fields.slideUp(300);
            // Show traditional seat plan if seat type is 'wbtm_seat_plan'
            if (seat_type === 'wbtm_seat_plan') {
                traditional_seat_plan.slideDown(300);
            }
        }
    }

    // Initialize cabin field visibility on page load
    $(document).ready(function() {
        // Initialize individual cabin toggles
        $('input[name="wbtm_cabin_enabled[]"]').each(function() {
            toggleCabinFields($(this));
        });

        // Initialize master cabin mode toggle
        let cabin_mode_checkbox = $('input[name="wbtm_cabin_mode_enabled"]');
        if (cabin_mode_checkbox.length > 0) {
            toggleCabinModeFields(cabin_mode_checkbox);
            
            // Also check seat type selection - if cabin mode is enabled but seat type is 'without_seat_plan',
            // we should still show the traditional interface
            let seat_type = $('select[name="wbtm_seat_type_conf"]').val();
            if (cabin_mode_checkbox.is(':checked') && seat_type === 'wbtm_seat_plan') {
                $('[data-collapse="#wbtm_seat_plan"]').hide();
            }
        }
    });

    // Handle individual cabin enable checkbox change
    $(document).on('change', 'input[name="wbtm_cabin_enabled[]"]', function() {
        // Prevent infinite loops by checking if this change was programmatically triggered
        if (!$(this).data('programmatic-change')) {
            toggleCabinFields($(this));
        }
        // Reset the flag
        $(this).removeData('programmatic-change');
    });

    // Handle master cabin mode enable checkbox change
    $(document).on('change', 'input[name="wbtm_cabin_mode_enabled"]', function() {
        // Prevent infinite loops by checking if this change was programmatically triggered
        if (!$(this).data('programmatic-change')) {
            toggleCabinModeFields($(this));
        }
        // Reset the flag
        $(this).removeData('programmatic-change');
    });

    // Handle seat type selection change - ensure proper visibility
    $(document).on('change', 'select[name="wbtm_seat_type_conf"]', function() {
        let seat_type = $(this).val();
        let cabin_mode_checkbox = $('input[name="wbtm_cabin_mode_enabled"]');
        let traditional_seat_plan = $('[data-collapse="#wbtm_seat_plan"]');
        
        // If cabin mode is enabled, force seat type to be 'wbtm_seat_plan'
        if (cabin_mode_checkbox.is(':checked') && seat_type !== 'wbtm_seat_plan') {
            $(this).val('wbtm_seat_plan');
            seat_type = 'wbtm_seat_plan';
            // Show alert to inform user
            alert('Cabin/Coach Configuration requires Seat Plan mode. Seat type has been automatically set to "Seat Plan".');
        }
        
        // If seat type is 'wbtm_seat_plan' and cabin mode is enabled, hide traditional seat plan
        if (seat_type === 'wbtm_seat_plan' && cabin_mode_checkbox.is(':checked')) {
            traditional_seat_plan.hide();
        } else if (seat_type === 'wbtm_seat_plan') {
            // If seat type is 'wbtm_seat_plan' but cabin mode is disabled, show traditional seat plan
            traditional_seat_plan.show();
        }
    });

})(jQuery);
