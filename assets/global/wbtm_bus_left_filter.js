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

        function filterBuses() {
            const selectedFilters = {};

            $('.filter-checkbox:checked').each(function () {
                const filterKey = $(this).data('filter');
                const filterValue = $(this).val();

                if (!selectedFilters[filterKey]) {
                    selectedFilters[filterKey] = [];
                }
                selectedFilters[filterKey].push(filterValue);
            });

            $('.wbtm-bust-list').each(function () {
                const $bus = $(this);
                let showBus = true;

                $.each(selectedFilters, function (key, values) {
                    if (key === "wbtm_start_route") {
                        const startRoute = $bus.find('input[name="wbtm_bus_start_route"]').val();
                        if (!values.includes(startRoute)) {
                            showBus = false;
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

        $(document).on('change', '.filter-checkbox', function() {
            filterBuses();
            // filterBuses_single();
        });
    });


}(jQuery));