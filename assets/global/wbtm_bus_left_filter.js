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

                var $details = $bus.next('.wbtm_bus_details');
                if (showBus) {
                    $bus.stop(true, true).show();
                } else {
                    $bus.stop(true, true).hide();
                    // The AJAX-loaded details panel is a separate sibling. Hiding
                    // only the result card left an orphaned seat/details panel in
                    // the filtered list.
                    $details.stop(true, true).hide();
                }
            });

            updateVisibleCounts();
        }

        function updateVisibleCounts() {
            $('.wbtm_bus_list_area').each(function () {
                var $area = $(this);
                var visible = $area.children('.wtbm_bus_counter:visible').length;
                $area.find('.wbtm-list-count strong').first().text(visible);
            });
        }

        /**
         * Reorder a result card together with its immediately following details
         * panel. The old Sort by select had no event handler, and sorting only the
         * cards would detach each seat/details panel from its bus.
         */
        function sortResults($select) {
            var mode  = $select.val();
            var $area = $select.closest('.wbtm_bus_list_area');
            var rows  = $area.children('.wtbm_bus_counter').map(function (index) {
                return {
                    card: this,
                    details: $(this).next('.wbtm_bus_details')[0] || null,
                    index: index
                };
            }).get();

            function numberValue(row, attr) {
                var value = parseFloat($(row.card).attr(attr));
                return Number.isFinite(value) ? value : null;
            }

            rows.sort(function (a, b) {
                var av;
                var bv;

                if (mode === 'price_asc' || mode === 'price_desc') {
                    av = numberValue(a, 'data-price');
                    bv = numberValue(b, 'data-price');
                } else if (mode === 'duration_asc') {
                    av = numberValue(a, 'data-duration');
                    bv = numberValue(b, 'data-duration');
                } else {
                    av = numberValue(a, 'data-departure');
                    bv = numberValue(b, 'data-departure');
                }

                // Unpriced/invalid rows always stay at the bottom.
                if (av === null && bv === null) { return a.index - b.index; }
                if (av === null) { return 1; }
                if (bv === null) { return -1; }

                var comparison = av - bv;
                if (mode === 'latest' || mode === 'price_desc') {
                    comparison *= -1;
                }
                return comparison || (a.index - b.index);
            });

            $.each(rows, function (_, row) {
                $area.append(row.card);
                if (row.details) {
                    $area.append(row.details);
                }
            });
        }

        /* ── Event bindings ──────────────────────────────────────────────── */

        $(document).on('change', '.filter-checkbox', function () {
            filterBuses('wbtm_bus_search_journey_start', 'filter-checkbox');
        });

        $(document).on('change', '.return_filter-checkbox', function () {
            filterBuses('wbtm_bus_search_journey_return', 'return_filter-checkbox');
        });

        /* Reset the filter panel that owns the clicked button. This works for
           both outbound and return panels (the old generic reset always reset
           the outbound list). */
        $(document).on('click', '.wbtm_reset_filter-checkbox, .wbtm-filter-reset-btn, .wbtm_reset_return_filter-checkbox', function () {
            var $panel = $(this).closest('.wbtm-filter-card, #wbtm_bus_filter-options');
            var isReturn = $panel.find('.return_filter-checkbox').length > 0;
            $panel.find('input[type="checkbox"]').prop('checked', false);
            filterBuses(
                isReturn ? 'wbtm_bus_search_journey_return' : 'wbtm_bus_search_journey_start',
                isReturn ? 'return_filter-checkbox' : 'filter-checkbox'
            );
        });

        $(document).on('change', '.wbtm-sort-select', function () {
            sortResults($(this));
        });

        updateVisibleCounts();

    });

}(jQuery));
