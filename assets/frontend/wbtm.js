jQuery(document).ready(function ($) {

    $(document).on( 'click', '#wbtm_search_location_toggle', function () {

        let toggleBtn = $(this);
        let startInput = $('input[name="bus_start_route"]');
        let endInput   = $('input[name="bus_end_route"]');
        let startVal = startInput.val();
        let endVal   = endInput.val();

        if (startVal !== '' && startVal === endVal) {
            let alertMsg = $('.wbtm_dropping_point').data('alert') || 'You select Wrong Route !';
            alert(alertMsg);
            return;
        }

        // $('.wbtm_start_point, .wbtm_dropping_point').addClass('swap-animation');

        setTimeout(function () {
            startInput.val(endVal);
            endInput.val(startVal);
            // $('.wbtm_start_point, .wbtm_dropping_point').removeClass('swap-animation');
        }, 300);

        toggleBtn.toggleClass('rotate');
    });

    // Mobile: collapsible "Filters" panel on search results.
    // Delegated handler so it also works for AJAX-injected result markup.
    $(document).on('click', '.wbtm-mobile-filter-toggle', function () {
        var holder = $(this).closest('.wbtm_bus_left_filter_holder');
        holder.toggleClass('wbtm-mobile-open');
        $(this).attr('aria-expanded', holder.hasClass('wbtm-mobile-open') ? 'true' : 'false');
    });

});