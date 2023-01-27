(function ($) {
	"use strict";
	$(document).on('change', '.ttbm_settings [name="ttbm_type"]', function (e) {
		e.preventDefault();
		let ttbm_type = $(this).val();
		let parent = $(this).closest('.ttbm_settings');
		if (ttbm_type === 'hotel') {
			parent.find('.ttbm_ticket_config').slideUp(250);
			parent.find('.ttbm_tour_hotel_setting').slideDown(250);
		} else {
			parent.find('.ttbm_ticket_config').slideDown(250);
			parent.find('.ttbm_tour_hotel_setting').slideUp(250);
		}
	});
	//*********Pricing************//
	$(document).on('click', '.ttbm_price_config  .mp_add_item', function (e) {
		if (e.result) {
			let parent = $(this).closest('.ttbm_price_config');
			let unique_id = new Date().getTime();
			parent.find('table tbody tr:last-child').find('[data-input-text]').attr('data-input-text', unique_id);
			parent.find('table tbody tr:last-child').find('[name="ttbm_hidden_ticket_text[]"]').val(unique_id);
		}
	});
	//*********Add F.A.Q Item************//
	$(document).on('click', '.ttbm_add_faq_content', function () {
		let $this = $(this);
		let parent = $this.closest('.tabsItem');
		let dt = new Date();
		let time = dt.getHours() + ":" + dt.getMinutes() + ":" + dt.getSeconds();
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {"action": "get_ttbm_add_faq_content", "id": time},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				$this.before(data);
				tinymce.execCommand('mceAddEditor', true, time);
				dLoaderRemove(parent);
			},
			error: function (response) {
				console.log(response);
			}
		});
		return false;
	});
	//*********Day wise details************//
	$(document).on('click', '.ttbm_add_day_wise_details', function () {
		let $this = $(this);
		let parent = $this.closest('.tabsItem');
		let dt = new Date();
		let time = dt.getHours() + ":" + dt.getMinutes() + ":" + dt.getSeconds();
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {"action": "get_ttbm_add_day_wise_details", "id": time},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				$this.before(data);
				tinymce.execCommand('mceAddEditor', true, time);
				dLoaderRemove(parent);
			},
			error: function (response) {
				console.log(response);
			}
		});
		return false;
	});
	//*****Location****************//
	$(document).on('click', '.ttbm_settings_general [data-target-popup]', function () {
		let target = $(this).closest('.ttbm_settings_general').find('.ttbm_location_form_area');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "load_ttbm_location_form"
			},
			beforeSend: function () {
				simpleSpinner(target);
			},
			success: function (data) {
				target.html(data).slideDown('fast').promise().done(function () {
					simpleSpinnerRemove(target);
				});
			}
		});
	});
	$(document).on('click', '.ttbm_settings_general  .popupClose', function (e) {
		if (e.result) {
			$(this).closest('.ttbm_settings_general').find('.ttbm_location_form_area').html('');
		}
	});
	$(document).on('click', '.ttbm_new_location_save,.ttbm_new_location_save_close', function () {
		ttbm_new_location_save($(this));
	});

	function ttbm_new_location_save($this) {
		let parent = $this.closest('.popupMainArea');
		parent.find('.ttbm_success_info').slideUp('fast');
		let name = parent.find('[name="ttbm_new_location_name"]').val();
		let description = parent.find('[name="ttbm_location_description"]').val();
		let address = parent.find('[name="ttbm_location_address"]').val();
		let country = parent.find('[name="ttbm_location_country"]').val();
		let image = parent.find('[name="ttbm_location_image"]').val();
		if (!name) {
			parent.find('[data-required="ttbm_new_location_name"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_new_location_name"]').slideUp('fast');
		}
		if (!image) {
			parent.find('[data-required="ttbm_location_image"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_location_image"]').slideUp('fast');
		}
		if (name && image) {
			$.ajax({
				type: 'POST',
				url: ttbm_ajax_url,
				data: {
					"action": "ttbm_new_location_save",
					"name": name,
					"description": description,
					"address": address,
					"country": country,
					"image": image
				},
				beforeSend: function () {
					dLoader(parent);
				},
				success: function () {
					parent.find('[name="ttbm_new_location_name"]').val('');
					parent.find('[name="ttbm_location_description"]').val('');
					parent.find('[name="ttbm_location_address"]').val('');
					parent.find('[name="ttbm_location_country"]').val('');
					parent.find('[name="ttbm_location_image"]').val('');
					$this.closest('.popupMainArea').find('.mp_remove_single_image').trigger('click');
					parent.find('.ttbm_success_info').slideDown('fast');
					ttbm_reload_location();
					dLoaderRemove(parent);
					if (($this).hasClass('ttbm_new_location_save_close')) {
						$this.closest('.popupMainArea').find('.popupClose').trigger('click');
					}
					return true;
				},
				error: function (response) {
					console.log(response);
				}
			});
		}
		return false;
	}

	function ttbm_reload_location() {
		let ttbm_id = $('[name="post_id"]').val();
		let parent = $('.ttbm_location_select_area');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "ttbm_reload_location_list",
				"ttbm_id": ttbm_id
			},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				parent.empty().append(data).promise().done(function () {
					parent.find('.ttbm_select2').select2({});
				});
				return true;
			},
			error: function (response) {
				console.log(response);
			}
		});
	}

	//*******Feature**************//
	$(document).on('click', '.ttbm_settings_feature [data-target-popup]', function () {
		let target = $(this).closest('.ttbm_settings_feature').find('.ttbm_feature_form_area');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "load_ttbm_feature_form"
			},
			beforeSend: function () {
				simpleSpinner(target);
			},
			success: function (data) {
				target.html(data).slideDown('fast').promise().done(function () {
					simpleSpinnerRemove(target);
				});
			}
		});
	});
	$(document).on('click', '.ttbm_settings_feature  .popupClose', function (e) {
		if (e.result) {
			$(this).closest('.ttbm_settings_feature').find('.ttbm_feature_form_area').html('');
		}
	});
	$(document).on('click', '.ttbm_new_feature_save,.ttbm_new_feature_save_close', function () {
		ttbm_new_feature_save($(this));
	});

	function ttbm_new_feature_save($this) {
		let parent = $this.closest('.popupMainArea');
		parent.find('.ttbm_success_info').slideUp('fast');
		let feature_name = parent.find('[name="ttbm_feature_name"]').val();
		let feature_description = parent.find('[name="ttbm_feature_description"]').val();
		let feature_icon = parent.find('[name="ttbm_feature_icon"]').val();
		if (!feature_name) {
			parent.find('[data-required="ttbm_feature_name"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_feature_name"]').slideUp('fast');
		}
		if (!feature_icon) {
			parent.find('[data-required="ttbm_feature_icon"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_feature_icon"]').slideUp('fast');
		}
		if (feature_name && feature_icon) {
			$.ajax({
				type: 'POST',
				url: ttbm_ajax_url,
				data: {
					"action": "ttbm_new_feature_save",
					"feature_name": feature_name,
					"feature_description": feature_description,
					"feature_icon": feature_icon
				},
				beforeSend: function () {
					dLoader(parent);
				},
				success: function () {
					parent.find('[name="ttbm_feature_name"]').val('');
					parent.find('[name="ttbm_feature_description"]').val('');
					parent.find('[name="ttbm_feature_icon"]').val('');
					$this.closest('.popupMainArea').find('.remove_input_icon').trigger('click');
					parent.find('.ttbm_success_info').slideDown('fast');
					ttbm_reload_feature_list();
					dLoaderRemove(parent);
					if (($this).hasClass('ttbm_new_feature_save_close')) {
						$this.closest('.popupMainArea').find('.popupClose').trigger('click');
					}
					return true;
				},
				error: function (response) {
					console.log(response);
				}
			});
		}
		return false;
	}

	function ttbm_reload_feature_list() {
		let ttbm_id = $('[name="post_id"]').val();
		let parent = $('.ttbm_features_table');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "ttbm_reload_feature_list",
				"ttbm_id": ttbm_id
			},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				parent.empty().append(data);
				return true;
			},
			error: function (response) {
				console.log(response);
			}
		});
	}

	//*******Activity**************//
	$(document).on('click', '.ttbm_settings_activities [data-target-popup]', function () {
		let target = $(this).closest('.ttbm_settings_activities').find('.ttbm_activity_form_area');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "load_ttbm_activity_form"
			},
			beforeSend: function () {
				simpleSpinner(target);
			},
			success: function (data) {
				target.html(data).slideDown('fast').promise().done(function () {
					simpleSpinnerRemove(target);
				});
			}
		});
	});
	$(document).on('click', '.ttbm_settings_activities  .popupClose', function (e) {
		if (e.result) {
			$(this).closest('.ttbm_settings_activities').find('.ttbm_activity_form_area').html('');
		}
	});
	$(document).on('click', '.ttbm_new_activity_save,.ttbm_new_activity_save_close', function () {
		ttbm_new_activity_save($(this));
	});

	function ttbm_new_activity_save($this) {
		let parent = $this.closest('.popupMainArea');
		parent.find('.ttbm_success_info').slideUp('fast');
		let activity_name = parent.find('[name="ttbm_activity_name"]').val();
		let activity_description = parent.find('[name="ttbm_activity_description"]').val();
		let activity_icon = parent.find('[name="ttbm_activity_icon"]').val();
		if (!activity_name) {
			parent.find('[data-required="ttbm_feature_name"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_feature_name"]').slideUp('fast');
		}
		if (!activity_icon) {
			parent.find('[data-required="ttbm_activity_icon"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_activity_icon"]').slideUp('fast');
		}
		if (activity_name && activity_icon) {
			$.ajax({
				type: 'POST',
				url: ttbm_ajax_url,
				data: {
					"action": "ttbm_new_activity_save",
					"activity_name": activity_name,
					"activity_description": activity_description,
					"activity_icon": activity_icon
				},
				beforeSend: function () {
					dLoader(parent);
				},
				success: function () {
					parent.find('[name="ttbm_activity_name"]').val('');
					parent.find('[name="ttbm_activity_description"]').val('');
					parent.find('[name="ttbm_activity_icon"]').val('');
					$this.closest('.popupMainArea').find('.remove_input_icon').trigger('click');
					parent.find('.ttbm_success_info').slideDown('fast');
					ttbm_reload_activity_list();
					dLoaderRemove(parent);
					if (($this).hasClass('ttbm_new_activity_save_close')) {
						$this.closest('.popupMainArea').find('.popupClose').trigger('click');
					}
					return true;
				},
				error: function (response) {
					console.log(response);
				}
			});
		}
		return false;
	}

	function ttbm_reload_activity_list() {
		let ttbm_id = $('[name="post_id"]').val();
		let parent = $('.ttbm_activities_table');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "ttbm_reload_activity_list",
				"ttbm_id": ttbm_id
			},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				parent.empty().append(data).promise().done(function () {
					parent.find('.ttbm_select2').select2({});
				});
				return true;
			},
			error: function (response) {
				console.log(response);
			}
		});
	}

	//*****Place you see****************//
	$(document).on('click', '.ttbm_settings_place_you_see [data-target-popup]', function () {
		let target = $(this).closest('.ttbm_settings_place_you_see').find('.ttbm_place_you_see_form_area');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "load_ttbm_place_you_see_form"
			},
			beforeSend: function () {
				simpleSpinner(target);
			},
			success: function (data) {
				target.html(data).slideDown('fast').promise().done(function () {
					simpleSpinnerRemove(target);
				});
			}
		});
	});
	$(document).on('click', '.ttbm_settings_place_you_see  .popupClose', function (e) {
		if (e.result) {
			$(this).closest('.ttbm_settings_place_you_see').find('.ttbm_place_you_see_form_area').html('');
		}
	});
	$(document).on('click', '.ttbm_new_place_you_see_save,.ttbm_new_place_you_see_save_close', function () {
		ttbm_new_place_save($(this));
	});

	function ttbm_new_place_save($this) {
		let parent = $this.closest('.popupMainArea');
		parent.find('.ttbm_success_info').slideUp('fast');
		let place_name = parent.find('[name="ttbm_place_name"]').val();
		let place_description = parent.find('[name="ttbm_place_description"]').val();
		let place_image = parent.find('[name="ttbm_place_image"]').val();
		if (!place_name) {
			parent.find('[data-required="ttbm_place_name"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_place_name"]').slideUp('fast');
		}
		if (!place_image) {
			parent.find('[data-required="ttbm_place_image"]').slideDown('fast');
		} else {
			parent.find('[data-required="ttbm_place_image"]').slideUp('fast');
		}
		if (place_name && place_image) {
			$.ajax({
				type: 'POST',
				url: ttbm_ajax_url,
				data: {
					"action": "ttbm_new_place_save",
					"place_name": place_name,
					"place_description": place_description,
					"place_image": place_image
				},
				beforeSend: function () {
					dLoader(parent);
				},
				success: function () {
					parent.find('[name="ttbm_place_name"]').val('');
					parent.find('[name="ttbm_place_description"]').val('');
					parent.find('[name="ttbm_place_image"]').val('');
					$this.closest('.popupMainArea').find('.mp_remove_single_image').trigger('click');
					parent.find('.ttbm_success_info').slideDown('fast');
					ttbm_reload_place_you_see();
					dLoaderRemove(parent);
					if (($this).hasClass('ttbm_new_place_you_see_save_close')) {
						$this.closest('.popupMainArea').find('.popupClose').trigger('click');
					}
					return true;
				},
				error: function (response) {
					console.log(response);
				}
			});
		}
		return false;
	}

	function ttbm_reload_place_you_see() {
		let ttbm_id = $('[name="post_id"]').val();
		let parent = $('.ttbm_place_you_see_table');
		$.ajax({
			type: 'POST',
			url: ttbm_ajax_url,
			data: {
				"action": "ttbm_reload_place_you_see_list",
				"ttbm_id": ttbm_id
			},
			beforeSend: function () {
				dLoader(parent);
			},
			success: function (data) {
				parent.empty().append(data);
				return true;
			},
			error: function (response) {
				console.log(response);
			}
		});
	}
}(jQuery));
//====================//
(function ($) {
	"use strict";
	$(document).ready(function () {
		ttbm_travel_type_change();
	});
	$(document).on('change', '#ttbm_travel_type', function () {
		ttbm_travel_type_change();
	});

	function ttbm_travel_type_change() {
		let ticket_type = $('#ttbm_travel_type').val();
		let fixed = {
			0: '#mage_row_ttbm_travel_reg_end_date',
			1: '#mage_row_ttbm_travel_start_date',
			2: '#mage_row_ttbm_travel_start_date_time',
			3: '#mage_row_ttbm_travel_end_date'
		};
		let particular = {
			0: '#mage_row_ttbm_particular_dates'
		};
		let repeated = {
			0: '#mage_row_ttbm_travel_repeated_after',
			1: '#mage_row_mep_disable_ticket_time',
			2: '#mage_row_mep_ticket_times_global',
			3: '#mage_row_mep_ticket_times_sat',
			4: '#mage_row_mep_ticket_times_sun',
			5: '#mage_row_mep_ticket_times_mon',
			6: '#mage_row_mep_ticket_times_tue',
			7: '#mage_row_mep_ticket_times_wed',
			8: '#mage_row_mep_ticket_times_thu',
			9: '#mage_row_mep_ticket_times_fri',
			10: '#mage_row_mep_ticket_offdays',
			11: '#mage_row_mep_ticket_off_dates',
			12: '#mage_row_ttbm_travel_repeated_start_date',
			13: '#mage_row_ttbm_travel_repeated_end_date',
			14: '.ttbm_special_on_dates_setting',
		};
		if (ticket_type === 'fixed') {
			ttbm_travel_type(fixed, particular, repeated)
		}
		if (ticket_type === 'particular') {
			ttbm_travel_type(particular, fixed, repeated)
		}
		if (ticket_type === 'repeated') {
			ttbm_travel_type(repeated, particular, fixed)
		}
	}

	function ttbm_travel_type(visible, hidden_1, hidden_2) {
		for (let id in hidden_1) {
			$(hidden_1[id]).slideUp('fast');
		}
		for (let id in hidden_2) {
			$(hidden_2[id]).slideUp('fast');
		}
		for (let id in visible) {
			$(visible[id]).slideDown('fast');
		}
	}
}(jQuery));
//==========Setup=================//
jQuery(document).ready(function ($) {
	$(document).on('click', '.welcome-tabs .tab-nav', function () {
		$(this).parent().parent().children('.tab-navs').children('.tab-nav').removeClass('active');
		$(this).addClass('active');
		id = $(this).attr('data-id');
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