jQuery(document).ready(function ($) {
    // ===== GALLERY SLIDER FUNCTIONALITY =====
    $(document).on('click', '.wbtm_gallery_slider_prev', function(e) {
        e.preventDefault();
        var $slider = $(this).closest('.wbtm_gallery_slider');
        var $slides = $slider.find('.wbtm_gallery_slide');
        var $dots = $slider.find('.wbtm_gallery_slider_dot');
        var $current = $slides.filter('.active');
        var currentIndex = parseInt($current.data('index'));
        var total = parseInt($slider.data('total'));
        var newIndex = (currentIndex - 1 + total) % total;
        
        $slides.removeClass('active');
        $dots.removeClass('active');
        $slides.filter('[data-index="' + newIndex + '"]').addClass('active');
        $dots.filter('[data-index="' + newIndex + '"]').addClass('active');
        $slider.find('.wbtm_gallery_slider_current').text(newIndex + 1);
    });

    $(document).on('click', '.wbtm_gallery_slider_next', function(e) {
        e.preventDefault();
        var $slider = $(this).closest('.wbtm_gallery_slider');
        var $slides = $slider.find('.wbtm_gallery_slide');
        var $dots = $slider.find('.wbtm_gallery_slider_dot');
        var $current = $slides.filter('.active');
        var currentIndex = parseInt($current.data('index'));
        var total = parseInt($slider.data('total'));
        var newIndex = (currentIndex + 1) % total;
        
        $slides.removeClass('active');
        $dots.removeClass('active');
        $slides.filter('[data-index="' + newIndex + '"]').addClass('active');
        $dots.filter('[data-index="' + newIndex + '"]').addClass('active');
        $slider.find('.wbtm_gallery_slider_current').text(newIndex + 1);
    });

    $(document).on('click', '.wbtm_gallery_slider_dot', function(e) {
        e.preventDefault();
        var $dot = $(this);
        var $slider = $dot.closest('.wbtm_gallery_slider');
        var $slides = $slider.find('.wbtm_gallery_slide');
        var $dots = $slider.find('.wbtm_gallery_slider_dot');
        var newIndex = parseInt($dot.data('index'));
        
        $slides.removeClass('active');
        $dots.removeClass('active');
        $slides.filter('[data-index="' + newIndex + '"]').addClass('active');
        $dot.addClass('active');
        $slider.find('.wbtm_gallery_slider_current').text(newIndex + 1);
    });

    // Auto-play slider (optional - advances every 5 seconds)
    function initAutoPlay($slider) {
        var interval = setInterval(function() {
            if (!$slider.is(':visible')) return;
            var $slides = $slider.find('.wbtm_gallery_slide');
            var $dots = $slider.find('.wbtm_gallery_slider_dot');
            var $current = $slides.filter('.active');
            var currentIndex = parseInt($current.data('index'));
            var total = parseInt($slider.data('total'));
            var newIndex = (currentIndex + 1) % total;
            
            $slides.removeClass('active');
            $dots.removeClass('active');
            $slides.filter('[data-index="' + newIndex + '"]').addClass('active');
            $dots.filter('[data-index="' + newIndex + '"]').addClass('active');
            $slider.find('.wbtm_gallery_slider_current').text(newIndex + 1);
        }, 5000);
        
        $slider.data('autoplay-interval', interval);
    }

    // Initialize auto-play for all sliders
    $('.wbtm_gallery_slider').each(function() {
        initAutoPlay($(this));
    });

    // Pause auto-play on hover
    $(document).on('mouseenter', '.wbtm_gallery_slider', function() {
        var interval = $(this).data('autoplay-interval');
        if (interval) clearInterval(interval);
    });

    $(document).on('mouseleave', '.wbtm_gallery_slider', function() {
        initAutoPlay($(this));
    });

    // ===== POPUP LIGHTBOX FUNCTIONALITY =====
    let currentIndex = 0;
    const $popup = $('.wbtm_gallery_image_popup_wrapper');
    const $popupImages = $('.wbtm_gallery_image_popup_item');

    $(document).on('click', '.wbtm_gallery_slide img', function() {
        var $slider = $(this).closest('.wbtm_gallery_slider');
        var $activeSlide = $slider.find('.wbtm_gallery_slide.active');
        currentIndex = parseInt($activeSlide.data('index'));
        $popup.fadeIn(300);
        showPopupImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_next', function() {
        currentIndex = (currentIndex + 1) % $popupImages.length;
        showPopupImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_prev', function() {
        currentIndex = (currentIndex - 1 + $popupImages.length) % $popupImages.length;
        showPopupImage(currentIndex);
    });

    $(document).on('click', '.wbtm_gallery_image_popup_close, .wbtm_gallery_image_popup_overlay', function() {
        $popup.fadeOut(300);
    });

    function showPopupImage(index) {
        $popupImages.removeClass('active').css({ opacity: 0 });
        $popupImages.eq(index).addClass('active').animate({ opacity: 1 }, 300);
    }

    // Keyboard navigation for popup
    $(document).on('keydown', function(e) {
        if ($popup.is(':visible')) {
            if (e.key === 'ArrowRight') {
                currentIndex = (currentIndex + 1) % $popupImages.length;
                showPopupImage(currentIndex);
            } else if (e.key === 'ArrowLeft') {
                currentIndex = (currentIndex - 1 + $popupImages.length) % $popupImages.length;
                showPopupImage(currentIndex);
            } else if (e.key === 'Escape') {
                $popup.fadeOut(300);
            }
        }
    });

    // ===== TABS FUNCTIONALITY =====
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

    // ===== AJAX POPUP FOR BUS DETAILS =====
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
