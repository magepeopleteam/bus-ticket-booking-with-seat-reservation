//==========Price settings=================//
(function ($) {
	"use strict";
	$(document).on('click', '.wbtm_add_category', function () {
		let parent = $(this).closest('.mp_settings_area');
		let target_item = $(this).next($('.mp_hidden_content')).find(' .mp_hidden_item');
		let item = target_item.html();
		load_sortable_datepicker(parent, item);
		let unique_id = Math.floor((Math.random() * 9999) + 9999);
		let sub_unique_id = Math.floor((Math.random() * 9999) + 99999);
		target_item.find('[name="wbtm_category_hidden_id[]"]').val(unique_id);
		target_item.find('[name*="wbtm_sub_category_hidden_id_"]').attr('name', 'wbtm_sub_category_hidden_id_' + unique_id + '[]').val(sub_unique_id);
		target_item.find('[name*="wbtm_service_name_"]').attr('name', 'wbtm_service_name_' + sub_unique_id + '[]');
		target_item.find('[name*="wbtm_service_img_"]').attr('name', 'wbtm_service_img_' + sub_unique_id + '[]');
		target_item.find('[name*="wbtm_service_details_"]').attr('name', 'wbtm_service_details_' + sub_unique_id + '[]');
		target_item.find('[name*="wbtm_service_price_"]').attr('name', 'wbtm_service_price_' + sub_unique_id + '[]');
		target_item.find('[name*="wbtm_service_duration_"]').attr('name', 'wbtm_service_duration_' + sub_unique_id + '[]');
	});
	$(document).on('click', '.wbtm_add_sub_category', function () {
		let parent = $(this).closest('.mp_settings_area');
		let target_item =$(this).next($('.mp_hidden_content')).find(' .mp_hidden_item');
		let item = target_item.html();
		load_sortable_datepicker(parent, item);
		let unique_id = Math.floor((Math.random() * 9999) + 99999);
		target_item.find('[name*="wbtm_sub_category_hidden_id_"]').val(unique_id);
		target_item.find('[name*="wbtm_service_name_"]').attr('name', 'wbtm_service_name_' + unique_id + '[]');
		target_item.find('[name*="wbtm_service_img_"]').attr('name', 'wbtm_service_img_' + unique_id + '[]');
		target_item.find('[name*="wbtm_service_details_"]').attr('name', 'wbtm_service_details_' + unique_id + '[]');
		target_item.find('[name*="wbtm_service_price_"]').attr('name', 'wbtm_service_price_' + unique_id + '[]');
		target_item.find('[name*="wbtm_service_duration_"]').attr('name', 'wbtm_service_duration_' + unique_id + '[]');
	});
	$(document).on('change', '[name="wbtm_category_active"]', function () {
		let parent=$(this).closest('.wbtm_price_settings');
		if (!$(this).is(":checked")) {
			let target=parent.find('[name="wbtm_sub_category_active"]');
			if(target.is(":checked")){
				target.next($('span')).trigger('click');
			}
		}
	});
	//========extra service settings===============//
	$(document).on('click', '.wbtm_add_group_service', function () {
		let parent = $(this).closest('.mp_settings_area');
		let target_item = $(this).next($('.mp_hidden_content')).find(' .mp_hidden_item');
		let item = target_item.html();
		load_sortable_datepicker(parent, item);
		let unique_id = Math.floor((Math.random() * 9999) + 9999);
		target_item.find('[name="wbtm_extra_hidden_name[]"]').val(unique_id);
		target_item.find('[name*="wbtm_extra_service_name_"]').attr('name', 'wbtm_extra_service_name_' + unique_id + '[]');
		target_item.find('[name*="wbtm_extra_service_img_"]').attr('name', 'wbtm_extra_service_img_' + unique_id + '[]');
		target_item.find('[name*="wbtm_extra_service_qty_"]').attr('name', 'wbtm_extra_service_qty_' + unique_id + '[]');
		target_item.find('[name*="wbtm_extra_service_price_"]').attr('name', 'wbtm_extra_service_price_' + unique_id + '[]');
		target_item.find('[name*="wbtm_extra_service_details_"]').attr('name', 'wbtm_extra_service_details_' + unique_id + '[]');
	});
}(jQuery));
//==========Date time settings=================//
(function ($) {
	"use strict";
	$(document).on('change', '.wbtm_settings_date_time  .wbtm_start_time .formControl', function () {
		let post_id = $('#post_ID').val();
		let start_time = $(this).val();
		if (start_time>=0 && post_id > 0) {
			let parent = $(this).closest('tr');
			let day_name = parent.find('[data-day-name]').data('day-name');
			let target=parent.find('.wbtm_end_time');
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				data: {
					"action": "get_wbtm_end_time_slot",
					"post_id": post_id,
					"day_name": day_name,
					"start_time": start_time,
				},
				beforeSend: function () {
					dLoader_xs_circle(target);
				},
				success: function (data) {
					target.html(data).promise().done(function (){
						target.find('.formControl').trigger('change');
					});
				}
			});
		}
	});
	$(document).on('change', '.wbtm_settings_date_time  .wbtm_end_time .formControl', function () {
		let parent = $(this).closest('tr');
		let post_id = $('#post_ID').val();
		let start_time = parent.find('.wbtm_start_time .formControl').val();
		let end_time = $(this).val();
		if (start_time>=0 && post_id > 0) {
			let day_name = parent.find('[data-day-name]').data('day-name');
			let target=parent.find('.wbtm_start_break_time');
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				data: {
					"action": "get_wbtm_start_break_time",
					"post_id": post_id,
					"day_name": day_name,
					"start_time": start_time,
					"end_time": end_time,
				},
				beforeSend: function () {
					dLoader_xs_circle(target);
				},
				success: function (data) {
					target.html(data).promise().done(function (){
						target.find('.formControl').trigger('change');
					});
				}
			});
		}
	});
	$(document).on('change', '.wbtm_settings_date_time  .wbtm_start_break_time .formControl', function () {
		let parent = $(this).closest('tr');
		let post_id = $('#post_ID').val();
		let start_time = $(this).val();
		let end_time = parent.find('.wbtm_end_time .formControl').val();
		if (start_time>=0 && post_id > 0) {
			let day_name = parent.find('[data-day-name]').data('day-name');
			let target=parent.find('.wbtm_end_break_time');
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				data: {
					"action": "get_wbtm_end_break_time",
					"post_id": post_id,
					"day_name": day_name,
					"start_time": start_time,
					"end_time": end_time,
				},
				beforeSend: function () {
					dLoader_xs_circle(target);
				},
				success: function (data) {
					target.html(data).promise().done(function (){
						target.find('.formControl').trigger('change');
					});
				}
			});
		}
	});
}(jQuery));
