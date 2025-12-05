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

    let searchParent = $('#wbtm_area');

    if( searchParent.find('#bus_start_route').length ) {
        let width = parseInt(searchParent.find('#bus_start_route').outerWidth());
        let marginLeft = width + 2 ;
        $("#wbtm_search_location_toggle").css({
            "display":  'flex',
            "margin-left":  marginLeft,
        });
    }



});