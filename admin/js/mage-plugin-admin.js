(function ($) {
  $(document).ready(function () {

    // Init
    wbtmSeatTypeConf();
    wbtmSameBusReturn();
    
    // Seat Type Selection
    $('select[name="wbtm_seat_type_conf"]').change(function () {
      wbtmSeatTypeConf();
    });

    // Single bus return Config
    $('input[name="wbtm_general_same_bus_return"]').change(function() {
      wbtmSameBusReturn();
    });

    // Route summary
    $('.wbtm_route_summary_btn').click(function(e) {
      e.preventDefault();
      const target = $(this).siblings('.wbtm-route-summary-inner');
      if(target.is(':visible')) {
        target.slideUp();
        $(this).text('Expand Route summary')
      } else {
        target.slideDown();
        $(this).text('Collapse Route summary')
      }
    })
  
    // $("#od_start, #on_start, #on_end, #j_date").datepicker({
    //   dateFormat: "yy-mm-dd",
    //   minDate: 0
    // });
    // $("#od_end, #ja_date").datepicker({
    //   dateFormat: "yy-mm-dd"
    //   // minDate:0
    // });

    $("input[name='wbtm_bus_on_dates']").multiDatesPicker({
      dateFormat: "yy-mm-dd",
      // minDate:0
    });

    $("input[name='wbtm_bus_on_dates_return']").multiDatesPicker({
      dateFormat: "yy-mm-dd",
      // minDate:0
    });

    // Off Dates
  $('.add-offday-row').on('click', function (e) {
      e.preventDefault();
      let datePickerOpt = {
        dateFormat: "yy-mm-dd",
        minDate: 0
      };
      let now = Date.now();
      let parent = $(this).parents('.wbtm-offdates-wrapper');
      let row = parent.find('.empty-row-offday.screen-reader-text').clone(true);
      row.removeClass('empty-row-offday screen-reader-text');
      row.insertBefore(parent.find('.repeatable-fieldset-offday > tbody>tr:last'));
      row.find(".repeatable-offday-from-field").attr('id', 'offday_from'+ now);
      row.find(".repeatable-offday-to-field").attr('id', 'offday_to'+ now);

      $("#offday_from"+now).datepicker(datePickerOpt);
      $("#offday_to"+now).datepicker(datePickerOpt);

  });

  $('.remove-bp-row').on('click', function () {
      $(this).parents('tr').remove();
      return false;
  });
    // Off Dates END

    // Repeatable Table
    $('.wbtom-tb-repeat-btn').on('click', function (e) {
      e.preventDefault();
      let tableFor = $(this).siblings('.repeatable-fieldset');
      let row = tableFor.find('.mtsa-empty-row-t').clone(true);
      row.removeClass('mtsa-empty-row-t');
      row.insertBefore(tableFor.find('tbody>tr:last'));
    });

    $('.wbtm-remove-row-t').on('click', function () {
      // if ( $(this).parents('tbody').children().length > 2 ) {
        $(this).parents('tr').remove();
      // }

        return false;
    });
    // Repeatable Table END

    // Extra service
    $('#add-row').on('click', function () {
      var row = $('.empty-row.screen-reader-text').clone(true);
      row.removeClass('empty-row screen-reader-text');
      row.insertBefore('#repeatable-fieldset-one tbody>tr:last');
      return false;
    });

    $('.remove-row').on('click', function () {
        if (confirm('Are You Sure , Remove this row ? \n\n 1. Ok : To Remove . \n 2. Cancel : To Cancel .')) {
            $(this).parents('tr').remove();
        } else {
            return false;
        }
    });

    // Extra service END
  
    // Tab script
    $('.mp_tab_menu').each(function () {
      $(this).find('ul li:first-child').trigger('click');
    });
    if ($('[name="mep_org_address"]').val() > 0) {
      $('.mp_event_address').slideUp(250);
    }
    
    $(document).on('click', '[data-target-tabs]', function () {
      if (!$(this).hasClass('active')) {
        let tabsTarget = $(this).attr('data-target-tabs');
        let targetParent = $(this).closest('.mp_event_tab_area').find('.mp_tab_details').first();
        targetParent.children('.mp_tab_item:visible').slideUp('fast');
        targetParent.children('.mp_tab_item[data-tab-item="' + tabsTarget + '"]').slideDown(250);
        $(this).siblings('li.active').removeClass('active');
        $(this).addClass('active');
      }
      return false;
    });
    // Tab script END
    

    // Hit Bulk Checkbox
    $('.wbtm_bulkcheck_hit').change(function () {
      let col_no = $(this).attr('data-col-no');

      if ($(this).is(":checked")) {

        $('.wbtm_permission_table tbody tr').each(function () {
          let el = $(this).find('td').eq(col_no);
          el.find('.wbtm_perm_checkbox').prop("checked", true);
        });

      } else {
        
        $('.wbtm_permission_table tbody tr').each(function () {
          let el = $(this).find('td').eq(col_no);
          el.find('.wbtm_perm_checkbox').prop("checked", false);
        });

      }
    });

    // notification

    // Route disable: Change routing
    $(document).on('change', '.wbtm_boarding_point', function() {
      const selectedRoute = $(this).find("option:selected").val();
      const newNameAttrValue = `wbtm_bus_bp_start_disable[${selectedRoute}]`;
      const parent = $(this).parents('tr');
      const target = parent.find('.route_disable_container input[type="checkbox"]');
      target.attr('name', newNameAttrValue);
      console.log(target.attr('name'));
    })
    
  
  });
  
  // Functions
  function wbtmSeatTypeConf() {
    let value = $('select[name="wbtm_seat_type_conf').find('option:selected').val();
    

    $('#mtsa_city_zone').hide();
    $('#mtpa_car_type').hide();

    if (value === 'wbtm_seat_plan') {
      $('.wbtm-seat-plan-wrapper').show();
      wbtmPriceType('general');
      $('#wbtm_same_bus_return').show();
    } else {
      $('.wbtm-seat-plan-wrapper').hide();

      if (value === 'wbtm_without_seat_plan') {
        wbtmPriceType('general');
        $('#wbtm_same_bus_return').show();

      } else if (value === 'wbtm_seat_private') {
        $('#mtpa_car_type').show();
        wbtmPriceType('private');
        $('#wbtm_same_bus_return').hide();

      } else {

        $('#mtsa_city_zone').show();
        wbtmPriceType('subscription');
        wbtmSubscriptionRouteType();
        $('#wbtm_same_bus_return').hide();
      }
    }

  }

  // Same Bus Return condition
  function wbtmSameBusReturn() {
    let currentVal = $('input[name="wbtm_general_same_bus_return"]:checked').val();
    if(currentVal === 'yes') {
        $('.wbtm-only-for-return-enable').removeClass('this_disabled').show();
        $('.wbtm-only-for-return-enable').find('input[type="text"], input[type="hidden"], select').prop('disabled', false)
    } else {
        $('.wbtm-only-for-return-enable').addClass('this_disabled').hide();
        $('.wbtm-only-for-return-enable').find('input[type="text"], input[type="hidden"], select').prop('disabled', true)
    }
  }

  function wbtmPriceType(priceType) {
    let routeTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_routing"]');
    let pickuppointTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_pickuppoint"]');
    let extraService = $('#wbtm_extra_service');

    if (priceType === 'subscription') { // Subscription
      $('#wbtm_general_price').hide();
      $('#wbtm_private_price').hide();
      $('#wbtm_subs_price').show();
      extraService.hide();
      
    } else if (priceType === 'private') { // Private
      $('#wbtm_subs_price').hide();
      $('#wbtm_general_price').hide();
      $('#wbtm_private_price').show();
      routeTab.hide();
      extraService.hide();
      
    } else { // General
      $('#wbtm_subs_price').hide();
      $('#wbtm_private_price').hide();
      $('#wbtm_general_price').show();
      routeTab.show();
      pickuppointTab.show();
      //extraService.show();
    }
  }

  function wbtmToggleRouteTime(state) {
    let routeTab = $('.mp_tab_item[data-tab-item="#wbtm_routing"]');
    let routeTable = routeTab.find('.repeatable-fieldset');

    if (state == 'hide') {
      routeTable.find('input[name="wbtm_bus_next_end_time[]"]').parent().hide();
      routeTable.find('input[name="wbtm_bus_bp_start_time[]"]').parent().hide();
      routeTable.find('tbody tr th').eq('1').hide();
    } else {
      routeTable.find('input[name="wbtm_bus_next_end_time[]"]').parent().show();
      routeTable.find('input[name="wbtm_bus_bp_start_time[]"]').parent().show();
      routeTable.find('tbody tr th').eq('1').show();
    }

  }

  function wbtmSubscriptionRouteType() {
    let $this = $('input[name="wbtm_subcsription_route_type"]:checked');
    let targetTable = $('#wbtm_subs_price #mtsa-repeatable-fieldset-ticket-type');
    let routeTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_routing"]');
    let pickuppointTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_pickuppoint"]');
    let type = $this.val();

    routeTab.hide();
    pickuppointTab.hide();
    if (type == 'wbtm_boarding_dropping') {
        targetTable.find('.wbtm_city_zone').hide();
        targetTable.find('.' + type).show();
    } else {
        targetTable.find('.wbtm_boarding_dropping').hide();
        targetTable.find('.' + type).show();
    }
  }
  
})(jQuery);