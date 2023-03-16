jQuery(document).ready(function ($) {
	$(document).on('click', '.welcome-tabs .tab-nav', function () {
		$(this).parent().parent().children('.tab-navs').children('.tab-nav').removeClass('active');
		$(this).addClass('active');
		let id = $(this).attr('data-id');
		$(this).parent().parent().children('.tab-content').removeClass('active');
		$(this).parent().parent().children('.tab-content#' + id).addClass('active');
		if (id === 'start') {
			$('.prev').slideUp('fast');
			$('.next').slideDown('fast');
		}
		if (id === 'general') {
			$('.prev').slideDown('fast');
			$('.next').slideDown('fast');
		}
		if (id === 'done') {
			$('.prev').slideDown('fast');
			$('.next').slideUp('fast');
		}
	})

	$(document).on('click', '.welcome-tabs .next-prev .next', function () {
		welcomeTabs = $('.welcome-tabs .tab-nav');
		welcomeTabsContent = $('.welcome-tabs .tab-content ');
		totalTab = welcomeTabs.length;
		for (i = 0; i < welcomeTabs.length; i++) {
			tab = welcomeTabs[i];
			content = welcomeTabsContent[i];
			if (tab.classList.contains('active')) {
				currentTabIndex = i;
				tab.classList.remove('active');
				content.classList.remove('active');
			}
		}
		for (j = 0; j <= currentTabIndex; j++) {
			tab = welcomeTabs[j];
			tab.classList.add('done');
		}
		if (typeof welcomeTabs[currentTabIndex + 1] != 'undefined') {
			welcomeTabs[currentTabIndex + 1].classList.add('active');
			welcomeTabsContent[currentTabIndex + 1].classList.add('active');
		}
		if (currentTabIndex === 0) {
			$('.prev').slideDown('fast');
		}
		if (currentTabIndex === 1) {
			$('.next').slideUp('fast');
		}
	})

	$(document).on('click', '.welcome-tabs .next-prev .prev', function () { 
		welcomeTabs = $('.welcome-tabs .tab-nav');
		welcomeTabsContent = $('.welcome-tabs .tab-content ');
		for (i = 0; i < welcomeTabs.length; i++) {
			tab = welcomeTabs[i];
			content = welcomeTabsContent[i];
			if (tab.classList.contains('active')) {
				currentTabIndex = i;
				tab.classList.remove('active');
				content.classList.remove('active');
			}
		}
		welcomeTabs[currentTabIndex - 1].classList.remove('done');
		if (typeof welcomeTabs[currentTabIndex - 1] != 'undefined') {
			welcomeTabs[currentTabIndex - 1].classList.add('active');
			welcomeTabsContent[currentTabIndex - 1].classList.add('active');
		}
		if (currentTabIndex === 1) {
			$('.prev').slideUp('fast');
		}
		if (currentTabIndex === 2) {
			$('.next').slideDown('fast');
		}
	})
});