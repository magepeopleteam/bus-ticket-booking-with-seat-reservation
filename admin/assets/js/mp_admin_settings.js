function load_sortable_datepicker(parent, item) {
	parent.find('.mp_item_insert').first().append(item).promise().done(function () {
		parent.find('.mp_sortable_area').sortable({
			handle: jQuery(this).find('.mp_sortable_button')
		});
		parent.find(".date_type").removeClass('hasDatepicker').attr('id', '').removeData('datepicker').unbind().datepicker({
			monthNames: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
			monthNamesShort: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
			dayNames: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
			dayNamesShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
			dayNamesMin: ["S", "M", "T", "W", "T", "F", "S", "S"],
			dateFormat: "yy-mm-dd"
		});
	});
	return true;
}
(function ($) {
	"use strict";
	$(document).ready(function () {
		$(".mpStyle .date_type").datepicker({
			monthNames: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
			monthNamesShort: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
			dayNames: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
			dayNamesShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
			dayNamesMin: ["S", "M", "T", "W", "T", "F", "S", "S"],
			dateFormat: "yy-mm-dd"
		});
		$('.ttbm_select2').select2({});
		$('.field-select2-wrapper select').select2({});
		$("ul.select2-selection__rendered").sortable({
			containment: 'parent'
		});
		//=========Short able==============//
		$(document).find('.mp_sortable_area').sortable({
			handle: $(this).find('.mp_sortable_button')
		});
	});
	//=========upload image==============//
	$(document).on('click', '.mp_add_single_image', function () {
		let parent = $(this);
		parent.find('.mp_single_image_item').remove();
		wp.media.editor.send.attachment = function (props, attachment) {
			let attachment_id = attachment.id;
			let attachment_url = attachment.url;
			let html = '<div class="mp_single_image_item" data-image-id="' + attachment_id + '"><span class="fas fa-times circleIcon_xs mp_remove_single_image"></span>';
			html += '<img src="' + attachment_url + '" alt="' + attachment_id + '"/>';
			html += '</div>';
			parent.append(html);
			parent.find('input').val(attachment_id);
			parent.find('button').slideUp('fast');
		}
		wp.media.editor.open($(this));
		return false;
	});
	$(document).on('click', '.mp_remove_single_image', function (e) {
		e.stopPropagation();
		let parent = $(this).closest('.mp_add_single_image');
		$(this).closest('.mp_single_image_item').remove();
		parent.find('input').val('');
		parent.find('button').slideDown('fast');
	});
	$(document).on('click', '.mp_remove_multi_image', function () {
		let parent = $(this).closest('.mp_multi_image_area');
		let current_parent = $(this).closest('.mp_multi_image_item');
		let img_id = current_parent.data('image-id');
		current_parent.remove();
		let all_img_ids = parent.find('.mp_multi_image_value').val();
		all_img_ids = all_img_ids.replace(',' + img_id, '')
		all_img_ids = all_img_ids.replace(img_id + ',', '')
		all_img_ids = all_img_ids.replace(img_id, '')
		parent.find('.mp_multi_image_value').val(all_img_ids);
	});
	$(document).on('click', '.add_multi_image', function () {
		let parent = $(this).closest('.mp_multi_image_area');
		wp.media.editor.send.attachment = function (props, attachment) {
			let attachment_id = attachment.id;
			let attachment_url = attachment.url;
			let html = '<div class="mp_multi_image_item" data-image-id="' + attachment_id + '"><span class="fas fa-times circleIcon_xs mp_remove_multi_image"></span>';
			html += '<img src="' + attachment_url + '" alt="' + attachment_id + '"/>';
			html += '</div>';
			parent.find('.mp_multi_image').append(html);
			let value = parent.find('.mp_multi_image_value').val();
			value = value ? value + ',' + attachment_id : attachment_id;
			parent.find('.mp_multi_image_value').val(value);
		}
		wp.media.editor.open($(this));
		return false;
	});
	//=========Remove Setting Item ==============//
	$(document).on('click', '.mp_item_remove,.mp_remove_icon', function () {
		if (confirm('Are You Sure , Remove this row ? \n\n 1. Ok : To Remove . \n 2. Cancel : To Cancel .')) {
			$(this).closest('.mp_remove_area').slideUp(250, function () {
				$(this).remove();
			});
			return true;
		}
		return false;
	});
	//=========Add Setting Item==============//
	$(document).on('click', '.mp_add_item', function () {
		let parent = $(this).closest('.mp_settings_area');
		let item = parent.find('.mp_hidden_content').first().find('.mp_hidden_item').html();
		load_sortable_datepicker(parent, item);
		parent.find('.mp_item_insert').find('.add_ttbm_select2').select2({});
		return true;
	});


}(jQuery));