(function ($) {
    // alert('ok');

    $(document).ready(function () {
        // Function to filter buses
        function filterBuses() {
            // Get selected filter criteria
            const selectedFilters = {};

            $('.filter-checkbox:checked').each(function () {
                const filterKey = $(this).data('filter'); // e.g., 'bus_start_route', 'AC'
                const filterValue = $(this).val();        // e.g., 'Paris', 'AC'
                selectedFilters[filterKey] = filterValue;
            });

            // Iterate over each bus
            $('.wbtm-bust-list').each(function () {
                const $bus = $(this);
                let showBus = true;

                // Check if the bus matches all selected filters
                $.each(selectedFilters, function (key, value) {
                    if (key === "wbtm_start_route") {
                        const startRoute = $bus.find('input[name="wbtm_start_route"]').val();
                        if (startRoute !== value) {
                            showBus = false;
                        }
                    }
                    if (key === "wbtm_bus_name") {
                        const startName = $bus.find('input[name="wbtm_bus_name"]').val();
                        console.log( startName );
                        if (startName !== value) {
                            showBus = false;
                        }
                    }
                    if (key === "AC" || key === "Non AC") {
                        const busType = $bus.find('.wbtm-seat-info h6').first().text(); // Check AC/Non AC
                        if (busType !== value) {
                            showBus = false;
                        }
                    }
                });

                // Show or hide the bus
                if (showBus) {
                    $bus.show();
                } else {
                    $bus.hide();
                }
            });
        }

        // Trigger filtering on checkbox change
        // $('.filter-checkbox').on('change', filterBuses);
        $(document).on('change', '.filter-checkbox', function() {
            filterBuses();
        });
    });


}(jQuery));