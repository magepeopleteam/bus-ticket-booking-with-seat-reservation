(function ($) {
    'use strict';

    $(document).ready(function ($) {
        $("#ja_date").datepicker({
            dateFormat: "yy-mm-dd"
        });

        $('#j_date_exchange').datepicker({
            dateFormat: "yy-mm-dd",
            minDate: 0
        });
        $(".the_select select").select2();
        $("#boarding_point, #drp_point").select2();

        //==============//
        $('.mage_default.mage_form_inline').closest('.fusion-text').css('transform', 'inherit');

        // Date field clear
        // $('.mage-clear-date').click(function(e) {
        //     e.preventDefault();
        //     $(this).siblings('.mage_form_control').val('');
        // })


        // Mearge Plugin js
        $('.mage-seat-qty input').on('input', function () {
            let $this = $(this);
            let parent = $this.parents('.mage_bus_seat_details');
            let price = $this.attr('data-price');
            let type = $this.attr('data-seat-type');
            let qty = $this.val();
            qty = qty > 0 ? qty : 0;
            let subtotal = price * qty;
            if (subtotal) {
                subtotal = subtotal.toFixed(2);
            }
            $this.parent().siblings('.mage-seat-price').find('.price-figure').text(wbtm_woo_price_format(subtotal));
            $this.parent().siblings('.mage-seat-price').attr('data-price', (subtotal));

            // Any date return
            if (type) {
                if (qty > 0) {
                    $('.wbtm_anydate_return_wrap').show();
                    $('.wbtm_anydate_return_switch label:nth-child(1)').trigger('click');
                    jQuery('.wbtm_anydate_return_switch label:first-child').addClass('active');
                    jQuery('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
                } else {
                    $('.wbtm_anydate_return_wrap').hide();
                }
            }
            // Any date return End

            let p = 0.00;
            $this.parents('.mage-seat-table').find('tbody tr').each(function () {
                if (parseFloat($(this).find('.mage-seat-price').attr('data-price'))) {
                    p = p + parseFloat($(this).find('.mage-seat-price').attr('data-price'));
                }

            });

            $this.parents('.mage-seat-table').find('.mage-price-total .price-figure').text(wbtm_woo_price_format(p)); // Subtotal Price
            $this.parents('.mage-seat-table').find('.mage-price-total .price-figure').attr('data-price-subtotal', p); // Subtotal Price

            // Enable Booking Button
            if (type == 'adult') {
                if (qty > 0) {
                    $('.no-seat-submit-btn').prop('disabled', false);
                } else {
                    $('.no-seat-submit-btn').prop('disabled', true);
                }
            }

            // Append Custom Registration Field
            if (type) {
                if (qty > 0) { // If Adult, Child or Infant have qty then Passenger info of Extra service will be remove
                    mageCustomRegField($('.mage-seat-qty input'), 'es', 0);
                }
                mageCustomRegField($(this), type, qty);
            }

            //mage_form_builder_conditional_show($this);

            // Grand Price
            mageGrandPrice(parent);

        });

        // Extra bag

        $('.wbtm-qty-change').click(function (e) {
            e.preventDefault();
            let changeType = $(this).attr('data-qty-change');
            let targetEle = $(this).siblings('.qty-input');
            let qty = parseInt(targetEle.val());
            let qtyUpdated = 0;

            if (changeType == 'inc') {
                qtyUpdated = (qty > 0 ? qty + 1 : 1);
            } else {
                qtyUpdated = (qty > 0 ? qty - 1 : 0);
            }

            targetEle.val(qtyUpdated); // Update qty
            targetEle.trigger('input');
        });

        // No seat book now validation
        $('.wbtm_bus_booking').submit(function (e) {
            $(this).find('.wbtm-booking-error').hide();

            let total = 0;
            let bus_type = $(this).find('input[name="wbtm_order_seat_plan"]').val();
            let bus_id = $(this).find('input[name="bus_id"]').val();
            let seat_available = $(this).find('input[name="seat_available"]').val();
            if (bus_type == 'no') {
                $(this).find('.mage-no-seat-right .mage-seat-table tbody tr').each(function () {
                    let qty = $(this).find('.qty-input').val();
                    if (qty) {
                        total += parseInt(qty);
                    }
                });

                if (seat_available >= total) {
                    return true;
                } else {
                    $(this).find('.wbtm-booking-error').show();
                    return false;
                }
            }
        });

        // Any date return
        $('.wbtm_anydate_return_wrap').hide();
        $('.mage_bus_seat_item,.mage_bus_seat_item .mage_bus_seat_icon,.mage_bus_seat_item .passenger_type_list ul li,.bus_handle').click(function (e) {
            $('.wbtm_anydate_return_wrap').hide();

            setTimeout(
                function () {
                    let seat_list = $('.mage_bus_selected_seat_list');
                    if (seat_list.children().length > 0) {
                        $('.wbtm_anydate_return_wrap').show();
                        $('.wbtm_anydate_return_switch label:nth-child(1)').trigger('click');
                        jQuery('.wbtm_anydate_return_switch label:first-child').addClass('active');
                        jQuery('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
                    } else {
                        $('.wbtm_anydate_return_wrap').hide();
                    }
                },
                1000);

        });

        // $('.mage-seat-qty input').on('input', function() {
        //     let $this = $(this);
        //     let type = $this.attr('data-seat-type');
        //     let qty = $this.val();
        //     qty = qty > 0 ? qty : 0;

        //     if (type) {
        //         if (qty > 0) {
        //             $('.wbtm_anydate_return_wrap').show();
        //             $('.wbtm_anydate_return_switch label:nth-child(1)').trigger('click');
        //             jQuery('.wbtm_anydate_return_switch label:first-child').addClass('active');
        //             jQuery('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
        //         } else {
        //             $('.wbtm_anydate_return_wrap').hide();
        //         }
        //     }

        // });

        $('.wbtm_anydate_return_switch label').click(function (e) {
            e.stopImmediatePropagation();
            e.preventDefault();

            let $this = jQuery(this);
            let parent = $this.parents('.mage_bus_seat_details');
            let target = jQuery('.wbtm_anydate_return_switch label');

            target.removeClass('active');
            target.find('.wbtm_anydate_return').prop('checked', false);
            $this.addClass('active');
            $this.find('.wbtm_anydate_return').prop('checked', true);
            let value = $this.find('.wbtm_anydate_return').val();
            let seat_plan_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage_bus_sub_total').find('.price-figure');
            let without_seat_plan_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage-seat-table').find('.mage-price-total .price-figure');
            let curr_symbol = php_vars.currency_symbol;


            if (value == 'on') {

                if (seat_plan_price.length > 0) {
                    let seat_list = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage_bus_selected_seat_list').children();
                    let amount = 0;
                    for (let i = 1; i <= seat_list.length; i++) {
                        let current_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage_bus_selected_seat_list').find('.mage_bus_selected_seat_item:nth-child(' + i + ') span').attr('data-current-price');
                        if (typeof current_price !== "undefined") {
                            amount += parseFloat(current_price);
                        }
                    }
                    let new_amount = amount * 2;
                    let thisSubtotal = seat_plan_price;
                    thisSubtotal.html(wbtm_woo_price_format(new_amount));
                    thisSubtotal.attr('data-price-subtotal', new_amount);
                    $this.parents('form').find('#wbtm_anydate_return_price').val(amount);
                } else {
                    let seat_list = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage-seat-table').find('tbody').children();
                    let amount = 0;
                    for (let i = 1; i <= seat_list.length; i++) {
                        let current_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage-seat-table').find('tbody tr:nth-child(' + i + ') .mage-seat-price').attr('data-price');

                        if (typeof current_price !== "undefined") {
                            amount += parseFloat(current_price);
                        }

                    }

                    let new_amount = amount * 2;
                    let thisSubtotal = without_seat_plan_price;
                    thisSubtotal.html(wbtm_woo_price_format(new_amount));
                    thisSubtotal.attr('data-price-subtotal', new_amount);
                    $this.parents('form').find('#wbtm_anydate_return_price').val(amount);

                }

            }

            if (value == 'off') {


                if (seat_plan_price.length > 0) {
                    let seat_list = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage_bus_selected_seat_list').children();
                    let amount = 0;
                    for (let i = 1; i <= seat_list.length; i++) {
                        let current_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage_bus_selected_seat_list').find('.mage_bus_selected_seat_item:nth-child(' + i + ') span').attr('data-current-price');
                        if (typeof current_price !== "undefined") {
                            amount += parseFloat(current_price);
                        }
                    }

                    let thisSubtotal = seat_plan_price;
                    thisSubtotal.html(amount.toFixed(2));
                    thisSubtotal.attr('data-price-subtotal', amount); // Subtotal Price
                } else {
                    let seat_list = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage-seat-table').find('tbody').children();
                    let amount = 0;
                    for (let i = 1; i <= seat_list.length; i++) {
                        let current_price = $this.parents('.wbtm_anydate_return_wrap').siblings('.mage-seat-table').find('tbody tr:nth-child(' + i + ') .mage-seat-price').attr('data-price');
                        if (typeof current_price !== "undefined") {
                            amount += parseFloat(current_price);
                        }
                    }

                    let thisSubtotal = without_seat_plan_price;
                    thisSubtotal.html(wbtm_woo_price_format(amount)); // Subtotal Price
                    thisSubtotal.attr('data-price-subtotal', amount); // Subtotal Price
                }

                $this.parents('form').find('#wbtm_anydate_return_price').val('');
            }

            mageGrandPrice(parent);
        });
        // Any date return END
    });
    //one way return
    $(document).on({
        click: function () {
            $('.mage_return_date').slideUp(300).removeClass('mage_hidden').find('input').val('');
        }
    }, '#one_way');
    $(document).on({
        click: function () {
            $('.mage_return_date').slideDown(300).removeClass('mage_hidden');
        }
    }, '#return');
    //qty inc dec
    $(document).on({
        click: function () {
            let target = $(this).siblings('input');
            let value = (parseInt(target.val()) - 1) > 0 ? (parseInt(target.val()) - 1) : 0;
            target.trigger('input');
            mageTicketQty(target, value);
        }
    }, '.mage_qty_dec');
    $(document).on({
        click: function () {
            let target = $(this).siblings('input');
            let value = parseInt(target.val()) + 1;
            target.trigger('input');
            mageTicketQty(target, value);
        }
    }, '.mage_qty_inc');
    $(document).on({
        keyup: function () {
            let target = $(this);
            let value = parseInt(target.val());
            mageTicketQty(target, value);
        }
    }, '.mage_form_qty input.mage_form_control');

    // Extra service
    // qty inc and dec
    $('.wbtm_extra_service_table .qty_dec').click(function (e) {
        e.preventDefault();
        let target = $(this).siblings('.extra-qty-box');
        let qty = target.val();
        let min = target.attr('min');

        if (qty >= 1) {
            qty = parseInt(qty) - 1
            target.val(qty);
        } else {
            target.val(0);
        }
        target.trigger('input');
        mage_form_builder_conditional_show($(this));
    });

    $('.wbtm_extra_service_table .qty_inc').click(function (e) {
        e.preventDefault();
        let target = $(this).siblings('.extra-qty-box');
        let qty = target.val();
        let max = target.attr('max');

        if (qty <= parseInt(max)) {
            qty = parseInt(qty) + 1
            target.val(qty);
        }
        target.trigger('input');
        mage_form_builder_conditional_show($(this));
    });
    // qty inc and dec END

    $('.wbtm_extra_service_table .extra-qty-box').on('input', function () {
        let parent = $(this).parents('.mage_bus_seat_details');
        let price = $(this).attr('data-price');
        let qty = $(this).val();
        let total = qty > 0 ? qty * price : 0;

        $(this).parents('tr').attr('data-total', total);

        mageGrandPrice(parent);
    });

    // Extra service END

    function mageGrandPrice(parent) {
        let bus_type = parent.find('input[name="wbtm_bus_type"]').val();
        let grand_ele = parent.find('.mage-grand-total .mage-price-figure');
        let bus_zero_price_allow = parent.find('input[name="wbtm_bus_zero_price_allow"]').val();
        let bagPerPrice = 0;
        let bagQty = 0;
        let bagPrice = 0;

        // price items
        // let seat_price = parent.find('.mage-price-total .price-figure').text(); // 1
        let seat_price = parseFloat(parent.find('.mage-price-total .price-figure').attr('data-price-subtotal')); // 1
        let extra_price = 0;
        parent.find('.wbtm_extra_service_table tbody tr').each(function () { // 2
            extra_price += parseFloat($(this).attr('data-total'));
        });

        // Extra bag price
        parent.find('.mage_customer_info_area input[name="extra_bag_quantity[]"]').each(function (index) {
            bagPerPrice = parseFloat($(this).attr('data-price'));
            bagQty += parseInt($(this).val());
            bagPrice += parseFloat($(this).val()) * bagPerPrice;
        });

        if (bus_zero_price_allow == 'yes') {
            let grand_total = seat_price + extra_price + bagPrice;
            grand_ele.text(wbtm_woo_price_format(grand_total));
            parent.find('button[name="add-to-cart"]').removeAttr('disabled');
            parent.find('.mage_bus_sub_total_price.mage-price-total .price-figure').text(grand_total.toFixed(2));
        } else {
            // Sum all items
            let grand_total = seat_price + extra_price + bagPrice;

            if (grand_total) {
                grand_ele.text(wbtm_woo_price_format(grand_total));
                parent.find('button[name="add-to-cart"]').prop('disabled', false);
                parent.find('input[name="csad_book_now"]').prop('disabled', false);
            } else {
                grand_ele.text("0.00");
                (bus_type == 'general') ? parent.find('button[name="add-to-cart"]').prop('disabled', true) : null;
                parent.find('input[name="csad_book_now"]').prop('disabled', true);
            }
        }

    }

    function mageTicketQty(target, value) {
        let minSeat = parseInt(target.attr('min'));
        let maxSeat = parseInt(target.attr('max'));
        target.siblings('.mage_qty_inc , .mage_qty_dec').removeClass('mage_disabled');
        if (value < minSeat || isNaN(value) || value === 0) {
            value = minSeat;
            target.siblings('.mage_qty_dec').addClass('mage_disabled');
        }
        if (value > maxSeat) {
            value = maxSeat;
            target.siblings('.mage_qty_inc').addClass('mage_disabled');
        }
        target.val(value);
        if (target.parents().hasClass('mage_bus_item')) {
            mage_bus_price_qty_calculation(target.parents('.mage_bus_item'));
        }
    }

    // input use drop down selector
    $(document).on('click', function (event) {
        let selectUl = $('.mage_input_select_list');
        if (!$(event.target).parents().hasClass('mage_input_select') && selectUl.is(':visible')) {
            let target = $('.mage_input_select input');
            target.each(function (index) {
                let input = $(this).val().toLowerCase();
                let flag = 0;
                $(this).parents('.mage_input_select').find('li').filter(function () {
                    if ($(this).attr('data-route').toLowerCase() === input) {
                        flag = 1;
                        mage_bus_dropping_point(selectUl);
                    }
                });
                if (flag < 1) {
                    $(this).val('');
                }
            });
            selectUl.slideUp(200);
        }
    });
    $(document).on({
        keyup: function () {
            let input = $(this).val().toLowerCase();
            $(this).parents('.mage_input_select').find('.mage_input_select_list').find('li').filter(function () {
                // $(this).toggle($(this).attr('data-route').toLowerCase().indexOf(input) > -1);
                // $('#wbtm_dropping_point_list').slideUp(100);
                let input_length = input.length;
                let target_str = $(this).attr('data-route').toLowerCase().substring(0, input_length);
                $(this).toggle($(this).attr('data-route').toLowerCase().substring(0, input_length) === input);
            });
            $('#wbtm_dropping_point_list').slideUp(100);
            $(this).parents('.mage_input_select').find('.mage_input_select_list').slideDown(200);
        },
        click: function () {
            $('#wbtm_dropping_point_list').slideUp(100);
            $(this).parents('.mage_input_select').find('.mage_input_select_list').slideDown(200);
        },
        blur: function () { }
    }, '.mage_input_select input');
    $(document).on({
        click: function () {
            let route = $(this).attr('data-route');
            $(this).parents('.mage_input_select_list').slideUp(200).parents('.mage_input_select').find('input').val(route);
            $('.mage_bus_dropping_point input').focus();
            mage_bus_dropping_point($(this));
        }
    }, '.mage_input_select_list li');

    function mage_bus_dropping_point(target) {
        let bus_id = target.parents('.mage_single_bus_search_page').attr('data-busId');
        if (target.parents().hasClass('mage_bus_boarding_point')) {
            var boarding_point = target.attr('data-route');
            if (boarding_point !== undefined) {
                $.ajax({
                    type: 'POST',
                    // url: wbtm_ajax.wbtm_ajaxurl,
                    url: wbtm_ajaxurl,
                    data: { "action": "wbtm_load_dropping_point", "boarding_point": boarding_point, bus_id: bus_id },
                    beforeSend: function () {
                        $('#wbtm_dropping_point_inupt').val('');
                        $('#wbtm_dropping_point_list').slideUp(200);
                        $('#wbtm_show_msg').html('<span>Loading..</span>').show();
                    },
                    success: function (data) {
                        $('#wbtm_show_msg').hide();
                        $('#wbtm_dropping_point_inupt').val('');
                        $('.mage_bus_dropping_point ul.mage_input_select_list').html(data).slideDown(250);
                    }
                });
                return false;
            }
        }
    }

    //bus price convert
    function mage_bus_price_convert(price, target, loader) {
        $.ajax({
            type: 'POST',
            // url: wbtm_ajax.wbtm_ajaxurl,
            url: wbtm_ajaxurl,
            data: { "action": "mage_bus_price_convert", "price": price },
            success: function (data) {
                target.html(data);
                if (loader) {
                    defaultLoaderFixedRemove();
                }
            },
            error: function (response) {
                console.log(response);
            }
        });

    }

    //bus details toggle
    $(document).on({
        click: function () {
            let target = $(this).parents('.mage_bus_item').find('.mage_bus_seat_details');
            if (target.is(':visible')) {
                target.slideUp(300);
            } else {
                $('.mage_bus_item').find('.mage_bus_seat_details').slideUp(300);
                target.slideDown(300);
            }
        }
    }, '.mage_bus_details_toggle');
    //bus seat selected price,qty calculation,extra price
    $(document).on({
        click: function (e, f) {
            let target = $(this);
            defaultLoaderFixed();
            mage_seat_selection(target, f);

            let seatType = parseInt($(this).attr('data-passenger-type'));
            if (seatType === 0) {
                seatType = 'Adult';
            }
            if (seatType === 1) {
                seatType = 'Child';
            }
            if (seatType === 2) {
                seatType = 'Infant';
            }

            //mageCustomRegField($(this), seatType, 1);
        }
    }, '.mage_bus_seat_item');
    $(document).on({
        click: function (e) {
            e.stopPropagation();
            let target = $(this);
            let passengerType = target.attr('data-seat-type');
            if (mage_seat_price_change(target, passengerType)) {
                if (target.parents('.mage_bus_seat_item').hasClass('mage_selected')) {
                    target.parents('.mage_bus_seat_item').trigger('click');
                }
                target.parents('.mage_bus_seat_item').trigger('click', [true]);
            }
        }
    }, '.mage_bus_seat_item li');
    $(document).on({
        click: function () {
            let target = $(this);
            let targetParents = target.parents('.mage_bus_item');
            let seatName = target.parents('.mage_bus_selected_seat_item').attr('data-seat-name');
            targetParents.find('.mage_bus_seat_plan [data-seat-name="' + seatName + '"]').trigger('click');
        }
    }, '.mage_bus_seat_unselect');

    function mage_seat_price_change(target, passengerType) {
        let price = target.attr('data-seat-price');
        target.parents('.mage_bus_seat_item').attr('data-price', price).attr('data-passenger-type', passengerType);
        return true;
    }

    function mage_seat_selection(target, is_sub) {
        let parents = target.parents('.mage_bus_item');
        let detail = target.parents('.mage_bus_seat_details');
        let seatName = target.attr('data-seat-name');
        let price = target.attr('data-price');
        let passengerType = target.attr('data-passenger-type');
        let busDd = target.attr('data-bus-dd');

        let start = parents.find('input[name="start_stops"]').val();
        let end = parents.find('input[name="end_stops"]').val();
        let j_date = $('input[name="journey_date"]').val();
        let r_date = $('input[name="return_date"]').val();
        let bus_id = parents.find('input[name="bus_id"]').val();

        let is_return = parents.attr('data-is-return');
        let has_seat = parents.find('.mage_bus_selected_seat_list').children().length;

        var totalPP = 0;

        if (target.hasClass('mage_selected')) { // Seat already selected
            defaultLoaderFixedRemove();
            target.removeClass('mage_selected');
            parents.find('.mage_bus_selected_seat_list [data-seat-name="' + seatName + '"]').slideUp(200, function () {
                $(this).remove();

                // ***
                has_seat = parents.find('.mage_bus_selected_seat_list').children().length;
                // console.log(has_seat);
                if (has_seat === 1) {
                    parents.find('.mage_bus_selected_seat_list .mage_bus_selected_seat_item').each(function () {
                        // $(this).find('.return_price_cal').siblings('span').removeAttr('data-current-price').show();
                        var p1 = $(this).find('.return_price_cal').siblings('span').attr('data-price');
                        $(this).find('.return_price_cal').siblings('span').attr('data-current-price', p1).show();
                        $(this).find('.return_price_cal').removeAttr('data-current-price');
                        var pp = $(this).find('.mage_old_price').siblings('span').attr('data-price');
                        $(this).find('.return_price_cal').addClass('mage_old_price').attr('data-current-price', pp);
                    });
                }
                // ***

                // ****
                parents.find('.mage_bus_selected_seat_list .mage_bus_selected_seat_item').each(function () {
                    $(this).find('.mage_selected_seat_price span').each(function () {
                        if (typeof $(this).attr('data-current-price') !== typeof undefined) {
                            totalPP += parseFloat($(this).attr('data-current-price'));
                        }
                    });
                });
                // parents.find('.mage_bus_sub_total .mage_bus_sub_total_price').text(php_vars.currency_symbol + totalPP);
                parents.find('.mage_bus_sub_total .mage_bus_sub_total_price').html(php_vars.currency_symbol + '<span class="price-figure">' + Number(totalPP).toFixed(2) + '</span>');
                // parents.find('.mage_bus_sub_total .mage_bus_sub_total_price').attr('data-price-subtotal', Number(totalPP).toFixed(2));
                mageGrandPrice(detail);
                // parents.find('.mage_bus_total_price').text(php_vars.currency_symbol + totalPP);
                // ****
                mage_bus_price_qty_calculation(parents);
            });
            if (parents.find('.mage_customer_info_area [data-seat-name="' + seatName + '"]').length > 0) {
                parents.find('.mage_customer_info_area [data-seat-name="' + seatName + '"]').slideUp(200, function () {
                    $(this).remove();
                });

            } else {

                // parents.find('.mage_customer_info_area').find('div[data-seat-selected-wrap="' + seatName + '"]').remove();
            }

            if (is_sub !== undefined) {
                mage_seat_selection(target);
            }

            // Remove Form builder
            wbtm_remove_form_builder(detail, seatName);


        } else { // Seat not selected
            target.addClass('mage_selected');
            has_seat = parents.find('.mage_bus_selected_seat_list').children().length;
            // parents.find('.mage_customer_info_area').append(mageCustomerInfoFormBus(parents, seatName, passengerType, busDd)).find('[data-seat-name="' + seatName + '"]').slideDown(200);

            $.ajax({
                type: 'POST',
                // url: wbtm_ajax.wbtm_ajaxurl,
                url: wbtm_ajaxurl,
                data: { "action": "mage_bus_selected_seat_item", "price": price, "seat_name": seatName, "passenger_type": passengerType, "start": start, "end": end, "j_date": j_date, "dd": busDd, "id": bus_id, "has_seat": has_seat, "is_return": is_return, "r_date": r_date },
                success: function (data) {
                    defaultLoaderFixedRemove();
                    parents.find('.mage_bus_selected_seat_list').append(data).slideDown(200);
                    mage_bus_price_qty_calculation(parents);

                    // Remove Discount price
                    has_seat = parents.find('.mage_bus_selected_seat_list').children().length;
                    if (has_seat > 1) {
                        parents.find('.mage_bus_selected_seat_list .mage_bus_selected_seat_item').each(function () {
                            $(this).find('.mage_old_price').siblings('span').removeAttr('data-current-price').hide();
                            var pp = $(this).find('.mage_old_price').attr('data-price');
                            $(this).find('.mage_old_price').removeClass('mage_old_price').attr('data-current-price', pp);
                        });
                    }
                    // Remove Discount price END

                    parents.find('.mage_bus_selected_seat_list .mage_bus_selected_seat_item').each(function () {
                        $(this).find('.mage_selected_seat_price span').each(function () {
                            if (typeof $(this).attr('data-current-price') !== typeof undefined) {
                                totalPP += parseFloat($(this).attr('data-current-price'));

                            }
                        });
                    });
                    parents.find('.mage_bus_sub_total .mage_bus_sub_total_price').html('<span class="price-figure" data-price-subtotal="' + totalPP.toFixed(2) + '">' + wbtm_woo_price_format(totalPP) + '</span>');
                    mageGrandPrice(detail);
                },
                error: function (response) {
                    console.log(response);
                }
            });

            // Load Form Builder New
            wbtm_seat_plan_form_builder_new(target, seatName, passengerType, busDd);
        }



    }

    //price qty calculation function
    function mage_bus_price_qty_calculation(parents) {
        let qty = 0;
        let subTotal = 0;
        let bagQty = 0;
        let bagPrice = 0;
        let bagPerPrice = 0;
        parents.find('.mage_bus_seat_item.mage_selected').each(function (index) {
            subTotal += parseFloat($(this).attr('data-price'));
            qty++;
        });
        parents.find('.mage_bus_total_qty').html(qty);
        // mage_bus_price_convert(subTotal, parents.find('.mage_bus_sub_total_price'),false);
        parents.find('.mage_customer_info_area input[name="extra_bag_quantity[]"]').each(function (index) {
            bagPerPrice = parseFloat($(this).attr('data-price'));
            bagQty += parseInt($(this).val());
            bagPrice += parseFloat($(this).val()) * bagPerPrice;
        });
        if (qty > 0) {
            parents.find('form.mage_bus_info_form').slideDown(250);
        } else {
            parents.find('form.mage_bus_info_form').slideUp(250);
        }
        if (bagQty > 0) {
            parents.find('.mage_bus_extra_bag_qty').html(bagQty);
            mage_bus_price_convert(bagPerPrice, parents.find('.mage_extra_bag_price'), false);
            mage_bus_price_convert(bagPrice, parents.find('.mage_bus_extra_bag_total_price'), false);
            parents.find('.mage_extra_bag').slideDown(200);
        } else {
            parents.find('.mage_extra_bag').slideUp(200);
        }
        let totalPrice = subTotal + (bagPrice > 0 ? parseFloat(bagPrice) : 0);
        // mage_bus_price_convert(totalPrice, parents.find('.mage_bus_total_price'),true);

        mageGrandPrice(parents);
    }

    function mage_bus_seat_item(seatName, price, passengerType) {
        let item = '<div class="flexEqual mage_bus_selected_seat_item" data-seat-name="' + seatName + '">';
        item += '<h6>' + seatName + '</h6>';
        item += '<h6>' + passengerType + '</h6>';
        item += '<h6>' + price + '</h6>';
        item += '<h6><span class="fa fa-trash mage_bus_seat_unselect"></span></h6>';
        item += '</div>';
        return item;
    }

    //customer form
    function mageCustomerInfoFormBus(parent, seatName, passengerType, busDd) {

        let formTitle = parent.find('input[name="mage_bus_title"]').val() + seatName;
        let currentTarget = parent.find('.mage_hidden_customer_info_form');
        console.log('lksjdfkl')
        if (currentTarget.length > 0) {
            currentTarget.append('<input type="hidden" name="custom_reg_user" value="no" />');
            currentTarget.find('input[name="seat_name[]"]').val(seatName);
            currentTarget.find('input[name="passenger_type[]"]').val(passengerType);
            currentTarget.find('input[name="bus_dd[]"]').val(busDd);
            currentTarget.find('.mage_form_list').attr('data-seat-name', seatName).find('.mage_title h5').html(formTitle);
            return currentTarget.html();
        } else {
            return '<div data-seat-selected-wrap="' + seatName + '"><input type="hidden" name="custom_reg_user" value="no" /><input type="hidden" name="bus_dd[]" value="' + busDd + '" /><input type="hidden" name="seat_name[]" value="' + seatName + '" /><input type="hidden" name="passenger_type[]" value="' + passengerType + '" /></div>';
        }


    }

    //loader default fixed
    function defaultLoaderFixed() {
        $('body').append('<div class="defaultLoaderFixed"><span></span></div>');
    }

    function defaultLoaderFixedRemove() {
        $('body').find('.defaultLoaderFixed').remove();
    }

    // Custom Reg Field New way
    function mageCustomRegField($this, seatType, qty, onlyES = false) {
        let bus_id = $this.parents('.mage_bus_item').attr('data-bus-id');
        $.ajax({
            url: wbtm_ajaxurl,
            type: 'POST',
            async: true,
            data: { busID: bus_id, seatType: seatType, seats: qty, onlyES: onlyES, action: 'wbtm_form_builder' },
            beforeSend: function () {
                $this.parents('.mage_bus_item').find('#wbtm-form-builder .wbtm-loading').show();
            },
            success: function (data) {
                let s = seatType.toLowerCase();
                if (data != '') {
                    $this.parents('.mage_bus_item').find("#wbtm-form-builder-" + s).html(data).find('.mage_hidden_customer_info_form').each(function (index) {
                        onlyES ? $(this).find('input[name="seat_name[]"]').remove() : null;
                        // $(this).find('.mage_title h5').html(seatType.toUpperCase()+' : '+(index+1));
                        if (seatType != 'es') {
                            let h = $(this).find('.mage_title h5').text();
                            $(this).find('.mage_title h5').html(h + ' ' + (index + 1));
                        }
                        $(this).removeClass('mage_hidden_customer_info_form').find('.mage_form_list').slideDown(200);
                    });

                } else {
                    $this.parents('.mage_bus_item').find("#wbtm-form-builder-" + s).empty();
                }
                $this.parents('.mage_bus_item').find('#wbtm-form-builder .wbtm-loading').hide();
            }
        });
    }

    // Seat plan Passenger info form (New)
    function wbtm_seat_plan_form_builder_new($this, seat_name, passengerType = null, busDd = null, onlyES = false) {
        let parent = $this.parents('.mage_bus_item');
        let bus_id = parent.attr('data-bus-id');
        let qty = 1;
        let seatType = seat_name;

        $.ajax({
            url: wbtm_ajaxurl,
            type: 'POST',
            async: true,
            data: { busID: bus_id, seatType: seatType, passengerType: passengerType, seats: qty, onlyES: onlyES, dd: busDd, action: 'wbtm_form_builder' },
            beforeSend: function () {
                parent.find('#wbtm-form-builder .wbtm-loading').show();
            },
            success: function (data) {

                if (data !== '') {
                    if (parent.find(".mage_customer_info_area").children().length == 0) {
                        parent.find(".mage_customer_info_area").html(data);
                    } else {
                        if (seat_name != 'ES') {
                            parent.find(".mage_customer_info_area").append(data);
                            parent.find(".mage_customer_info_area .seat_name_ES").remove();
                        }
                    }
                    onlyES ? parent.find('.mage_customer_info_area input[name="seat_name[]"]').remove() : null;

                } else {
                    // parent.find(".mage_customer_info_area").empty();

                    parent.find('.mage_customer_info_area').append(mageCustomerInfoFormBus(parent, seat_name, passengerType, busDd)).find('[data-seat-name="' + seat_name + '"]').slideDown(200);
                }
                // Loading hide
                parent.find('.wbtm-form-builder .wbtm-loading').hide();
            }
        });
    }

    function mage_form_builder_conditional_show($this) {
        let seat_plan_type = $this.parents('.mage_bus_item').find('input[name="wbtm_order_seat_plan"]').val();
        // ES qty
        let es_table = $this.parents('.mage_bus_item').find('.wbtm_extra_service_table');
        let es_qty = 0;
        es_table.find('tbody tr').each(function () {
            let tp = $(this).find('.extra-qty-box').val();
            es_qty += tp > 0 ? parseInt(tp) : 0;
        });

        // Seat qty
        let seat_qty = 0;
        if (seat_plan_type == 'yes') {
            $this.parents('.mage_bus_item').find('.mage_bus_selected_seat_list .mage_bus_selected_seat_item').each(function () {
                if ($this.attr('data-seat-name') != '') {
                    seat_qty += 1;
                }
            });

            if (es_qty > 0 && parseInt(seat_qty) < 1) { // Only es
                wbtm_seat_plan_form_builder_new($this, 'ES', '', '', true);
            }
            if (es_qty == 0) {
                $this.parents('.mage_bus_item').find(".mage_customer_info_area .seat_name_ES").remove();
            }

        } else {
            let parents = $this.parents('.mage-no-seat').find('.mage-seat-table');
            seat_qty = 0;
            parents.find('tbody tr').each(function () {
                let tp = $(this).find('.qty-input').val();
                seat_qty += tp > 0 ? parseInt(tp) : 0;
            });

            if (es_qty > 0 && seat_qty < 1) {
                mageCustomRegField(parents.find('.mage-seat-qty input'), 'es', "1", true);
            } else {
                mageCustomRegField(parents.find('.mage-seat-qty input'), 'es', "0", true);
            }
        }
    }

    function wbtm_remove_form_builder($this, seat_name) {
        $this.find(".mage_customer_info_area .seat_name_" + seat_name).remove(); //  without seat
        $this.find('.mage_customer_info_area').find('div[data-seat-selected-wrap="' + seat_name + '"]').remove(); // with seat
        // ES qty
        let es_table = $this.find('.wbtm_extra_service_table');
        let es_qty = 0;
        es_table.find('tbody tr').each(function () {
            let tp = $(this).find('.extra-qty-box').val();
            es_qty += tp > 0 ? parseInt(tp) : 0;
        });

        if (es_qty > 0) {
            wbtm_seat_plan_form_builder_new($this, 'ES', '', '', true);
        }
    }

    // currency format according to WooCommerce setting
    function wbtm_woo_price_format(price) {
        if (typeof price === 'string') {
            price = Number(price);
        }
        price = price.toFixed(2);
        // price = price.toString();
        // price = price.toFixed(mptbm_num_of_decimal);
        let price_text = '';
        if (mptbm_currency_position === 'right') {
            price_text = price + mptbm_currency_symbol;
        } else if (mptbm_currency_position === 'right_space') {
            price_text = price + ' ' + mptbm_currency_symbol;
        } else if (mptbm_currency_position === 'left') {
            price_text = mptbm_currency_symbol + price;
        } else {
            price_text = mptbm_currency_symbol + ' ' + price;
        }
        return price_text;
    }

})(jQuery);