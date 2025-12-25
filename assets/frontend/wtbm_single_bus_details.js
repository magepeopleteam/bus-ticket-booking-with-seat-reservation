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

});