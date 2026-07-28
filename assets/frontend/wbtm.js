jQuery(document).ready(function ($) {

    function syncFilterToggleState() {
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        $('.wbtm_bus_left_filter_holder .wbtm-mobile-filter-toggle').each(function () {
            var $holder = $(this).closest('.wbtm_bus_left_filter_holder');
            var expanded = isMobile
                ? $holder.hasClass('wbtm-mobile-open')
                : !$holder.hasClass('wbtm-filter-collapsed');
            $(this).attr('aria-expanded', expanded ? 'true' : 'false');
        });
    }

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

    // Responsive collapsible "Filters" panel on search results.
    // Delegated handler so it also works for AJAX-injected result markup.
    $(document).on('click', '.wbtm-mobile-filter-toggle', function () {
        var holder = $(this).closest('.wbtm_bus_left_filter_holder');
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        var expanded;

        if (isMobile) {
            holder.removeClass('wbtm-filter-collapsed').toggleClass('wbtm-mobile-open');
            expanded = holder.hasClass('wbtm-mobile-open');
        } else {
            holder.removeClass('wbtm-mobile-open').toggleClass('wbtm-filter-collapsed');
            expanded = !holder.hasClass('wbtm-filter-collapsed');
        }

        $(this).attr('aria-expanded', expanded ? 'true' : 'false');
    });

    // Mobile starts results-first (collapsed); desktop starts with filters open.
    // Search results are AJAX-injected, so repeat the state sync after requests.
    syncFilterToggleState();
    $(document).ajaxComplete(syncFilterToggleState);

});
