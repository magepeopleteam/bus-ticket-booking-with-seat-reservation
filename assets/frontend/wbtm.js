jQuery(document).ready(function ($) {

    // Desktop collapses the sidebar sideways (rail slides left), so its caret
    // points left/right; mobile collapses it like an accordion, so its caret
    // keeps the up/down arrow. Same button, glyph swapped to match the motion.
    function syncFilterToggleState() {
        // 1099px, not 767px: search_result.php's own layout also stacks the
        // sidebar full-width above the list from 768-1099 (its "no room for a
        // 270px rail" tablet breakpoint), so that range needs the same
        // accordion-style toggle as true mobile, not the side-by-side rail.
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 1099px)').matches;
        $('.wbtm_bus_left_filter_holder .wbtm-mobile-filter-toggle').each(function () {
            var $holder = $(this).closest('.wbtm_bus_left_filter_holder');
            var expanded = isMobile
                ? $holder.hasClass('wbtm-mobile-open')
                : !$holder.hasClass('wbtm-filter-collapsed');
            $(this).attr('aria-expanded', expanded ? 'true' : 'false');

            var $caretIcon = $(this).find('.wbtm-mobile-filter-caret i');
            if (isMobile) {
                $caretIcon.removeClass('fa-chevron-left fa-chevron-right')
                    .toggleClass('fa-chevron-up', expanded)
                    .toggleClass('fa-chevron-down', !expanded);
            } else {
                $caretIcon.removeClass('fa-chevron-up fa-chevron-down')
                    .toggleClass('fa-chevron-left', expanded)
                    .toggleClass('fa-chevron-right', !expanded);
            }
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
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 1099px)').matches;

        if (isMobile) {
            holder.removeClass('wbtm-filter-collapsed').toggleClass('wbtm-mobile-open');
        } else {
            holder.removeClass('wbtm-mobile-open').toggleClass('wbtm-filter-collapsed');
        }

        syncFilterToggleState();
    });

    // Mobile starts results-first (collapsed); desktop starts with filters open.
    // Search results are AJAX-injected, so repeat the state sync after requests.
    syncFilterToggleState();
    $(document).ajaxComplete(syncFilterToggleState);

});
