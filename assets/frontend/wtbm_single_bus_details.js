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


    $(document).on('click','.wbtm_bus_popup_link', function () {
        let post_id = $(this).data('post-id');
        let tab_id = $(this).attr('id');
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
                $('.wbtm_bus_popup_holder').removeClass('active');
                $('#' + tab_id + '_popup_tab').addClass('active').trigger('click');
                $("#" + tab_id + '_content').addClass('active').show();
            }
        });
    });

    // Close popup
    $(document).on('click', '.wbtm-popup-close, #wbtm-bus-popup', function (e) {
        if ($(e.target).is('#wbtm-bus-popup, .wbtm-popup-close')) {
            $('#wbtm-bus-popup').fadeOut();
        }
    });
    // popup tabs target content
    $(document).on('click', '.wbtm_bus_detail_popup_tab', function () {
        $('.wbtm_bus_detail_popup_tab').removeClass('active');
        $('.wbtm_bus_popup_holder').removeClass('active');
        $('.wbtm_bus_popup_holder').hide();
        $(this).addClass('active');
        let targetId = $(this).data('tab-id');
        if (targetId) {
            let $target = $('#' + targetId);
            if ($target.length) {
                $target.addClass('active');
                $target.show();
            }
        }
    });
});