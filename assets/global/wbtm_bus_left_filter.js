(function ($) {

    $(document).ready(function () {

        /**
         * Apply all active filters to bus cards.
         *
         * @param {string} journeyClass  CSS class on each bus card (wbtm_bus_search_journey_start | _return)
         * @param {string} checkboxClass CSS class on the filter checkboxes (filter-checkbox | return_filter-checkbox)
         */
        function filterBuses(journeyClass, checkboxClass) {

            /* ── Collect checked filter values ──────────────────────────── */
            var textFilters = {};    // key → [values]  for text-based filters
            var timeRanges  = [];    // [{min, max}]     for departure-time filters

            $('.' + checkboxClass + ':checked').each(function () {
                var key = $(this).data('filter');
                var val = $(this).val();

                if (key === 'wbtm_departure_time') {
                    /* value is encoded as "min-max" (e.g. "6-12") */
                    var parts = val.split('-');
                    if (parts.length === 2) {
                        timeRanges.push({
                            min: parseInt(parts[0], 10),
                            max: parseInt(parts[1], 10)
                        });
                    }
                } else {
                    if (!textFilters[key]) { textFilters[key] = []; }
                    textFilters[key].push(val);
                }
            });

            /* ── Apply to every bus card ─────────────────────────────────── */
            $('.' + journeyClass).each(function () {
                var $bus    = $(this);
                var showBus = true;

                /* Text filters: AND across keys, OR within same key */
                $.each(textFilters, function (key, values) {
                    if (!showBus) { return false; } // early exit

                    if (key === 'wbtm_bus_start_route') {
                        var routeMatch = false;
                        $bus.find('input[name="wbtm_bus_start_route"]').each(function () {
                            if (values.indexOf($(this).val()) !== -1) {
                                routeMatch = true;
                                return false;
                            }
                        });
                        if (!routeMatch) { showBus = false; }
                    }

                    if (key === 'wbtm_bus_name') {
                        var busName = $bus.find('input[name="wbtm_bus_name"]').val();
                        if (values.indexOf(busName) === -1) { showBus = false; }
                    }

                    if (key === 'wbtm_bus_type') {
                        var busType = $bus.find('input[name="wbtm_bus_type"]').val();
                        if (values.indexOf(busType) === -1) { showBus = false; }
                    }
                });

                /* Departure-time filter: OR across selected ranges.
                   Night (22:00–05:59) has max < min (wraps midnight), so the
                   cross-midnight check uses OR instead of AND. */
                if (showBus && timeRanges.length > 0) {
                    var bpTime   = ($bus.attr('data-bp-time') || '').toString();
                    var timePart = bpTime.indexOf(' ') !== -1 ? bpTime.split(' ')[1] : bpTime;
                    var hour     = timePart ? parseInt(timePart.split(':')[0], 10) : -1;
                    var inRange  = false;

                    for (var i = 0; i < timeRanges.length; i++) {
                        var r = timeRanges[i];
                        if (r.max < r.min) {
                            // Cross-midnight range (e.g. Night: 22–6)
                            if (hour >= r.min || hour < r.max) { inRange = true; break; }
                        } else {
                            if (hour >= r.min && hour < r.max) { inRange = true; break; }
                        }
                    }
                    if (!inRange) { showBus = false; }
                }

                showBus ? $bus.fadeIn(350) : $bus.fadeOut(250);
            });
        }

        /* ── Event bindings ──────────────────────────────────────────────── */

        $(document).on('change', '.filter-checkbox', function () {
            filterBuses('wbtm_bus_search_journey_start', 'filter-checkbox');
        });

        $(document).on('change', '.return_filter-checkbox', function () {
            filterBuses('wbtm_bus_search_journey_return', 'return_filter-checkbox');
        });

        /* Reset — show all cards and clear checkboxes */
        $(document).on('click', '.wbtm_reset_filter-checkbox, .wbtm-filter-reset-btn', function () {
            $(this).closest('.wbtm-filter-card, #wbtm_bus_filter-options')
                   .find('input[type="checkbox"]').prop('checked', false);
            $('.wbtm_bus_search_journey_start').fadeIn(350);
        });

        $(document).on('click', '.wbtm_reset_return_filter-checkbox', function () {
            $(this).closest('.wbtm-filter-card, #wbtm_bus_filter-options')
                   .find('input[type="checkbox"]').prop('checked', false);
            $('.wbtm_bus_search_journey_return').fadeIn(350);
        });

    });

}(jQuery));
