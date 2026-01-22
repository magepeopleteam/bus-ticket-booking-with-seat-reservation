jQuery(document).ready(function ($) {
    let searchTimer;
    let currentStatus = 'all';
    const tableBody = $('.wbtm-orders-table tbody');
    const container = $('.wbtm-dashboard-container');

    $('#wbtm-order-search').on('keyup', function () {
        clearTimeout(searchTimer);
        const search = $(this).val();

        searchTimer = setTimeout(function () {
            performSearch(search, currentStatus);
        }, 500);
    });

    // Handle Stats Card Clicking
    $(document).on('click', '.wbtm-stat-card-clickable', function () {
        $('.wbtm-stat-card-clickable').removeClass('active');
        $(this).addClass('active');

        currentStatus = $(this).data('filter');
        const search = $('#wbtm-order-search').val();
        performSearch(search, currentStatus);
    });

    // Handle Modal Opening
    $(document).on('click', '.open-wbtm-modal', function (e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        const modal = $('#wbtm-order-modal');
        const modalBody = $('#wbtm-modal-body');

        // Show loading state in modal
        modal.show();
        modalBody.html('<div class="wbtm-modal-loader-container"><div class="wbtm-loader"></div></div>');

        $.ajax({
            url: wbtm_my_account_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wbtm_get_order_details',
                nonce: wbtm_my_account_vars.nonce,
                order_id: orderId
            },
            success: function (response) {
                if (response.success) {
                    modalBody.html(response.data);
                } else {
                    modalBody.html('<div class="wbtm-error">' + response.data + '</div>');
                }
            }
        });
    });

    // Handle Modal Closing
    $(document).on('click', '.wbtm-close', function () {
        $('#wbtm-order-modal').hide();
    });

    $(window).on('click', function (event) {
        if ($(event.target).is('#wbtm-order-modal')) {
            $('#wbtm-order-modal').hide();
        }
    });

    function performSearch(search, status) {
        // Show loader
        container.addClass('wbtm-loading-overlay');

        $.ajax({
            url: wbtm_my_account_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wbtm_my_account_search',
                nonce: wbtm_my_account_vars.nonce,
                search: search,
                status: status
            },
            success: function (response) {
                if (response.success) {
                    tableBody.html(response.data);
                }
            },
            complete: function () {
                container.removeClass('wbtm-loading-overlay');
            }
        });
    }
});
