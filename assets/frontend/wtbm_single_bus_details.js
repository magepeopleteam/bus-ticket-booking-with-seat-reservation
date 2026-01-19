jQuery(document).ready(function ($) {
    $(document).on("click", ".wbtm_gallery_image", function() {
        let feature_image = $('#wbtm_car_details_feature_image');
        let gallery_image = $(this);
        let gallery_url = gallery_image.attr('src');
        let feature_url = feature_image.attr('src');
        feature_image.addClass('wbtm_gallery_image_fade_out');
        gallery_image.addClass('wbtm_gallery_image_fade_out');
        setTimeout(function() {
            feature_image.attr('src', gallery_url).removeClass('wbtm_gallery_image_fade_out').addClass('wbtm_gallery_image_fade_in');
            gallery_image.attr('src', feature_url).removeClass('wbtm_gallery_image_fade_out').addClass('wbtm_gallery_image_fade_in');

            setTimeout(function() {
                feature_image.removeClass('wbtm_gallery_image_fade_in');
                gallery_image.removeClass('wbtm_gallery_image_fade_in');
            }, 300);
        }, 300);
    });
    $(".wbtm_car_details_tabs button").on("click", function(){
        var tabId = $(this).data('tab');

        $('.wbtm_car_details_tabs button').removeClass('active');
        $(this).addClass('active');

        $('html, body').animate({
            scrollTop: $('#' + tabId).offset().top - 50
        }, 400);

        const target = $('#' + tabId);
        target.addClass('focus-highlight');

        setTimeout(() => {
            target.removeClass('focus-highlight');
        }, 800);
    });


    let currentIndex = 0;
    const $popup = $('.wbtm_gallery_image_popup_wrapper');
    const $images = $('.wbtm_gallery_image_popup_item');

    $(document).on('click', '.wbtm_car_image_details', function() {
        $popup.fadeIn(300);
        showImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_next', function() {
        currentIndex = (currentIndex + 1) % $images.length;
        showImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_prev', function() {
        currentIndex = (currentIndex - 1 + $images.length) % $images.length;
        showImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_close, .wbtm_gallery_image_popup_overlay', function() {
        $popup.fadeOut(300);
    });

    function showImage(index) {
        $images.removeClass('active').css({ opacity: 0 });
        $images.eq(index).addClass('active').animate({ opacity: 1 }, 300);
    }


    $(document).on('click','.wbtm_bus_details_tab', function () {
        let post_id = $(this).data('post-id');
        let clicked_tab_id = $(this).attr('id');
        let busContentId = clicked_tab_id+'_holder';

        let targetPopupMap = {
            'wbtm_bus_details': 'wbtm_bus_detail_popup_tab',
            'wbtm_bus_boarding_dropping': 'wbtm_bus_boarding_dropping_popup_tab',
            'wbtm_bus_image': 'wbtm_bus_photos_popup_tab',
            'wbtm_bus_term_condition': 'wbtm_bus_term_condition_popup_tab',
            'wbtm_bus_feature': 'wbtm_bus_feature_popup_tab',
        };

        let targetClickId = targetPopupMap[clicked_tab_id];

        $('#wbtm-bus-popup').fadeIn();
        $('.wbtm-popup-content').html('<p>Loading...</p>');

        $.ajax({
            url: wbtm_ajax_url,
            type: 'POST',
            data: {
                action: 'wbtm_load_bus_details',
                post_id: post_id,
                nonce: wbtm_nonce,
            },
            success: function (response) {
                $('.wbtm-popup-content').html(response);

                $('.wbtm_bus_detail_popup_tab').removeClass('active');
                $("#"+targetClickId).addClass('active');

                setTimeout(function () {
                    let container = $('.wbtm-bus-popup-inner');
                    let target = $('#' + busContentId);

                    if (target.length) {
                        console.log('here');
                        container.animate({
                            scrollTop: container.scrollTop() + target.position().top - 20
                        }, 500);
                    }
                }, 50);
            }
        });
    });

    // Close popup
    $(document).on('click', '.wbtm-popup-close, #wbtm-bus-popup', function (e) {
        if ($(e.target).is('#wbtm-bus-popup, .wbtm-popup-close')) {
            $('#wbtm-bus-popup').fadeOut();
        }
    });

    $(document).on('click', '.wbtm_bus_detail_popup_tab', function () {

        // active tab
        $('.wbtm_bus_detail_popup_tab').removeClass('active');
        $(this).addClass('active');

        let clicked_id = $(this).attr('id');

        let targetMap = {
            'wbtm_bus_detail_popup_tab': 'wbtm_bus_details_holder',
            'wbtm_bus_boarding_dropping_popup_tab': 'wbtm_bus_boarding_dropping_holder',
            'wbtm_bus_feature_popup_tab': 'wbtm_bus_feature_holder',
            'wbtm_bus_term_condition_popup_tab': 'wbtm_bus_term_condition_holder',
            'wbtm_bus_photos_popup_tab': 'wbtm_bus_photos_popup_holder'
        };

        let targetId = targetMap[clicked_id];

        if (targetId && $('#' + targetId).length) {
            let container = $('.wbtm-bus-popup-inner');
            let target = $('#' + targetId);

            container.animate({
                scrollTop: container.scrollTop() + target.position().top - 20
            }, 500);
        }
    });



});