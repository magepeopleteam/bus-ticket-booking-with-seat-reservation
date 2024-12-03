(function ($) {

    $(document).ready(function () {
        // Function to filter buses
        function filterBuses_single() {
            const selectedFilters = {};

            $('.filter-checkbox:checked').each(function () {
                const filterKey = $(this).data('filter');
                const filterValue = $(this).val();
                selectedFilters[filterKey] = filterValue;
            });

            // Iterate over each bus
            $('.wbtm-bust-list').each(function () {
                const $bus = $(this);
                let showBus = true;

                $.each(selectedFilters, function (key, value) {
                    if (key === "wbtm_start_route") {
                        const startRoute = $bus.find('input[name="wbtm_bus_start_route"]').val();
                        if (startRoute !== value) {
                            showBus = false;
                        }
                    }
                    if (key === "wbtm_bus_name") {
                        const startName = $bus.find('input[name="wbtm_bus_name"]').val();
                        if (startName !== value) {
                            showBus = false;
                        }
                    }
                    if (key === "wbtm_bus_type") {
                        const busType = $bus.find('input[name="wbtm_bus_type"]').val();
                        if (busType !== value) {
                            showBus = false;
                        }
                    }
                });

                if (showBus) {
                    $bus.fadeIn(1000);
                } else {
                    $bus.fadeOut(500);
                }
            });
        }

        function filterBuses( search_form, checkbox ) {
            // alert( search_form );
            const selectedFilters = {};

            $('.' + checkbox + ':checked').each(function () {
            // $('.filter-checkbox:checked').each(function () {
                const filterKey = $(this).data('filter');
                const filterValue = $(this).val();

                if (!selectedFilters[filterKey]) {
                    selectedFilters[filterKey] = [];
                }
                selectedFilters[filterKey].push(filterValue);
            });

            $('.'+search_form).each(function () {
                const $bus = $(this);
                let showBus = true;

                $.each(selectedFilters, function (key, values) {
                    console.log( key );
                    if (key === "wbtm_bus_start_route") {
                        let hasMatchingRoute = false;
                        $bus.find('input[name="wbtm_bus_start_route"]').each(function () {
                            const startRoute = $(this).val();
                            if (values.includes(startRoute)) {
                                hasMatchingRoute = true;
                                return false;
                            }
                        });
                        if (!hasMatchingRoute) {
                            showBus = false;
                            return false;
                        }
                    }
                    if (key === "wbtm_bus_name") {
                        const busName = $bus.find('input[name="wbtm_bus_name"]').val();
                        if (!values.includes(busName)) {
                            showBus = false;
                        }
                    }
                    if (key === "wbtm_bus_type") {
                        const busType = $bus.find('input[name="wbtm_bus_type"]').val();
                        if (!values.includes(busType)) {
                            showBus = false;
                        }
                    }
                });

                // Show or hide the bus
                if (showBus) {
                    $bus.fadeIn(600);
                } else {
                    $bus.fadeOut(400);
                }
            });
        }

        $(document).on('click', '.wbtm_reset_filter-checkbox', function() {
            $('.filter-checkbox:checked').prop('checked', false);
            $('.wbtm_bus_search_journey_start').fadeIn(600);
        });

        $(document).on('click', '.wbtm_reset_return_filter-checkbox', function() {
            $('.return_filter-checkbox:checked').prop('checked', false);
            $('.wbtm_bus_search_journey_return').fadeIn(600);
        });

        $(document).on('change', '.filter-checkbox', function() {
            filterBuses( 'wbtm_bus_search_journey_start', 'filter-checkbox');
            // filterBuses_single();
        });
        $(document).on('change', '.return_filter-checkbox', function() {
            filterBuses( 'wbtm_bus_search_journey_return', 'return_filter-checkbox');
            // filterBuses_single();
        });


    });


}(jQuery));