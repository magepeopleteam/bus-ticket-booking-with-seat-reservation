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
            var collapsing = !holder.hasClass('wbtm-filter-collapsed');
            holder.removeClass('wbtm-mobile-open').toggleClass('wbtm-filter-collapsed');
            if (collapsing) {
                // Re-clip immediately (see wtbm_search.css's .wbtm-filter-slide
                // rules) -- overflow itself can't be transitioned/delayed (no
                // browser actually runs a transition for it, verified: it
                // always applies instantly regardless of any transition-delay
                // declared on it), so "clip during the whole open animation,
                // release only once fully open" has to be driven from here
                // instead, not CSS alone. Collapsing needs no delay: clipping
                // should engage the instant the panel starts shrinking, same
                // as it already did before this existed.
                holder.removeClass('wbtm-filter-settled');
            }
            // Expanding: wbtm-filter-settled is added by the width
            // transitionend handler below once the open animation actually
            // finishes, not here -- see that handler for why.
        }

        syncFilterToggleState();
    });

    // Only actually lifts .wbtm-filter-slide's clipping (wtbm_search.css:
    // .wbtm_bus_left_filter_holder.wbtm-filter-settled .wbtm-filter-slide)
    // once the sidebar's own width transition has fully finished opening --
    // not the instant the click removes wbtm-filter-collapsed. Doing it
    // immediately (the previous approach, and CSS transition-delay tricks
    // that tried to fake a delay on `overflow` itself, which turned out not
    // to support transitioning at all -- browsers apply it the instant the
    // rule changes, full stop) uncovers the filter card's own fixed 270px
    // width right away, while the holder is still ~0px wide -- it renders in
    // full immediately and overflows out over the bus list, exactly like a
    // plain `display:block` reveal, instead of being progressively revealed
    // by the holder's own growing width like the collapse direction already
    // does correctly.
    $(document).on('transitionend', '.wbtm_bus_left_filter_holder', function (e) {
        if (e.originalEvent && e.originalEvent.propertyName !== 'width') {
            return;
        }
        var holder = $(this);
        // Guard against a stale event from a transition the user has since
        // reversed (e.g. clicked open then collapsed again before the open
        // animation finished) -- only settle if it's actually still open.
        if (!holder.hasClass('wbtm-filter-collapsed') && !holder.hasClass('wbtm-mobile-open')) {
            holder.addClass('wbtm-filter-settled');
        }
    });

    // wbtm-filter-settled (see the transitionend handler above) is normally
    // only added once an *open* animation actually finishes -- but on a
    // fresh page load/AJAX render there was no animation, the holder just
    // starts expanded (desktop's default) directly from server-rendered
    // HTML, so nothing would ever add it and .wbtm-filter-card's
    // position:sticky would stay broken until the user happened to collapse
    // and reopen the panel once. Settle it immediately here instead --
    // deliberately NOT folded into syncFilterToggleState() itself, since
    // that function also runs synchronously inside the click handler above,
    // right when a collapse-to-open transition is *starting* -- exactly the
    // moment this needs to stay unsettled.
    function settleAlreadyExpandedFilterHolders() {
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 1099px)').matches;
        if (isMobile) {
            return;
        }
        $('.wbtm_bus_left_filter_holder').not('.wbtm-filter-collapsed').addClass('wbtm-filter-settled');
    }

    // Mobile starts results-first (collapsed); desktop starts with filters open.
    // Search results are AJAX-injected, so repeat the state sync after requests.
    syncFilterToggleState();
    settleAlreadyExpandedFilterHolders();
    $(document).ajaxComplete(function () {
        syncFilterToggleState();
        settleAlreadyExpandedFilterHolders();
    });

});
