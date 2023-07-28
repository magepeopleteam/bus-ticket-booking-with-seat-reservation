function mpwpb_price_calculation($this) {
	let parent = $this.closest('form.mpwpb_registration');
	let price = 0;
	parent.find('.mpwpb_service_area .mpwpb_service_item[data-price].mpActive').each(function () {
		let current_price = jQuery(this).data('price') ?? 0;
		current_price = current_price && current_price > 0 ? current_price : 0;
		price = price + parseFloat(current_price);
	});
	parent.find('.mpwpb_extra_service_item').each(function () {
		let service_name = jQuery(this).find('[name="mpwpb_extra_service_type[]"]').val();
		if (service_name) {
			let ex_target = jQuery(this).find('[name="mpwpb_extra_service_qty[]');
			let ex_qty = parseInt(ex_target.val());
			let ex_price = ex_target.data('price');
			ex_price = ex_price && ex_price > 0 ? ex_price : 0;
			price = price + parseFloat(ex_price) * ex_qty;
		}
	});
	parent.find('.mpwpb_total_bill').html(mp_price_format(price));
}
//Registration
(function ($) {
	"use strict";
	$(document).ready(function () {
		$('form.mpwpb_registration').each(function () {
			let parent = $(this);
			let target = parent.find('.all_service_area');
			dLoader(target);
			if (parent.find('.mpwpb_category_area').length > 0) {
				parent.find('.mpwpb_category_area').slideDown(350).promise().done(function () {
					loadBgImage();
					dLoaderRemove(target);
				});
			} else {
				parent.find('.mpwpb_service_area').slideDown(350).promise().done(function () {
					loadBgImage();
					dLoaderRemove(target);
				});
			}
		});
	});
	//==========tab============//
	$(document).on('click', 'form.mpwpb_registration .mptbm_service_tab', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		parent.find('.all_service_area').slideDown(350);
		parent.find('.mpwpb_date_time_area,.mpwpb_summary_area').slideUp(300);
		loadBgImage();
		pageScrollTo(parent.find('.all_service_area'));
	});
	$(document).on('click', 'form.mpwpb_registration .mpwpb_date_time_tab', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		parent.find('.mpwpb_date_time_area').slideDown(350);
		parent.find('.all_service_area,.mpwpb_summary_area').slideUp(300)
		loadBgImage();
		pageScrollTo(parent.find('.mpwpb_date_time_area'));
	});
	$(document).on('click', 'form.mpwpb_registration .mptbm_summary_tab', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		parent.find('.mpwpb_summary_area').slideDown(350);
		parent.find('.all_service_area,.mpwpb_date_time_area').slideUp(300)
		loadBgImage();
		pageScrollTo(parent.find('.mpwpb_summary_area'));
	});
	//==========category============//
	function refresh_sub_category(parent) {
		parent.find('.mpwpb_service_area,.mpwpb_extra_service_area,.next_date_time_area,.mpwpb_date_time_area,.mpwpb_summary_area').slideUp(350);
		let target_sub_category = parent.find('.mpwpb_sub_category_area');
		parent.find('[name="mpwpb_sub_category"]').val('');
		if (target_sub_category.length > 0) {
			//target_sub_category.slideUp('fast');
			parent.find('.mpwpb_summary_item[data-sub-category]').slideUp('fast');
			parent.find('.mpwpb_summary_area[data-sub-category]').slideUp('fast');
			let category = parent.find('[name="mpwpb_category"]').val();
			target_sub_category.find('.mpwpb_sub_category_item[data-category]').each(function () {
				$(this).removeClass('mpActive');
				if ($(this).data('category') === category) {
					$(this).slideDown(350);
				} else {
					$(this).slideUp(350);
				}
			});
		}
	}
	function refresh_service(parent) {
		parent.find('.mpwpb_extra_service_area,.next_date_time_area,.mpwpb_date_time_area,.mpwpb_summary_area').slideUp(350);
		let target_sub_category = parent.find('.mpwpb_sub_category_area');
		let target_service = parent.find('.mpwpb_service_area');
		parent.find('[name="mpwpb_service"]').val('');
		parent.find('.mpwpb_summary_item[data-service]').slideUp('fast');
		parent.find('.mpwpb_summary_area[data-service]').slideUp('fast');
		//target_service.slideUp('fast');
		let category = parent.find('[name="mpwpb_category"]').val();
		let sub_category = parent.find('[name="mpwpb_sub_category"]').val();
		target_service.find('.mpwpb_service_item[data-category]').each(function () {
			$(this).removeClass('mpActive');
			if ($(this).data('category') === category) {
				if (target_sub_category.length > 0) {
					if ($(this).data('sub-category') === sub_category) {
						$(this).slideDown(350);
					} else {
						$(this).slideUp(350);
					}
				} else {
					$(this).slideDown(350);
				}
			} else {
				$(this).slideUp(350);
			}
		});
	}
	$(document).on('click', 'form.mpwpb_registration .mpwpb_category_item', function () {
		let current = $(this);
		let category = current.data('category');
		if (category && !current.hasClass('mpActive')) {
			let parent = current.closest('form.mpwpb_registration');
			let target_sub_category = parent.find('.mpwpb_sub_category_area');
			let target_service = parent.find('.mpwpb_service_area');
			parent.find('.mpwpb_summary_area_left').slideDown('fast');
			parent.find('.mpwpb_summary_item[data-category]').slideDown('fast').find('h6').html(category);
			parent.find('.mpwpb_summary_area[data-category]').slideDown('fast').find('h6').html(category);
			parent.find('[name="mpwpb_category"]').val(category).promise().done(function () {
				refresh_sub_category(parent);
				refresh_service(parent);
			}).promise().done(function () {
				parent.find('.mpwpb_category_item.mpActive').each(function () {
					$(this).removeClass('mpActive');
				}).promise().done(function () {
					current.addClass('mpActive');
					mpwpb_price_calculation(current);
				});
				if (target_sub_category.length > 0) {
					target_sub_category.slideDown(250);
					target_service.slideUp('fast');
					loadBgImage();
					pageScrollTo(target_sub_category);
				} else {
					if (target_service.length > 0) {
						target_service.slideDown(250);
						pageScrollTo(target_service);
						loadBgImage();
					}
				}
			});
		}
	});
	//=========sub category=============//
	$(document).on('click', 'form.mpwpb_registration .mpwpb_sub_category_item', function () {
		let current = $(this);
		let parent = current.closest('form.mpwpb_registration');
		let category = parent.find('[name="mpwpb_category"]').val();
		let sub_category = current.data('sub-category');
		if (category && sub_category && !current.hasClass('mpActive')) {
			//let target_sub_category = parent.find('.mpwpb_sub_category_area');
			let target_service = parent.find('.mpwpb_service_area');
			parent.find('.mpwpb_summary_area_left').slideDown('fast');
			parent.find('.mpwpb_summary_item[data-sub-category]').slideDown('fast').find('h6').html(sub_category);
			parent.find('.mpwpb_summary_area[data-sub-category]').slideDown('fast').find('h6').html(sub_category);
			parent.find('[name="mpwpb_sub_category"]').val(sub_category).promise().done(function () {
				refresh_service(parent);
			}).promise().done(function () {
				parent.find('.mpwpb_sub_category_item.mpActive').each(function () {
					$(this).removeClass('mpActive');
				}).promise().done(function () {
					current.addClass('mpActive');
					mpwpb_price_calculation(current);
					target_service.slideDown(250);
					loadBgImage();
					pageScrollTo(target_service);
				});
			});
		}
	});
	//==========service============//
	$(document).on('click', 'form.mpwpb_registration .mpwpb_service_item', function () {
		let current = $(this);
		let parent = $(this).closest('form.mpwpb_registration');
		if (!current.hasClass('mpActive')) {
			let service = current.data('service');
			let price = parseFloat(current.data('price'));
			parent.find('[name="mpwpb_service"]').val(service);
			parent.find('.mpwpb_summary_item[data-service]').slideDown('fast').find('h6').html(service);
			parent.find('.mpwpb_summary_area[data-service]').slideDown('fast').find('h6').html(service);
			parent.find('.mpwpb_summary_item').find('.service_price').html(mp_price_format(price));
			parent.find('.mpwpb_summary_area').find('.service_price').html(mp_price_format(price));
			parent.find('.mpwpb_service_item.mpActive').each(function () {
				$(this).removeClass('mpActive');
			}).promise().done(function () {
				current.addClass('mpActive');
				mpwpb_price_calculation(current);
				let target_extra_service = parent.find('.mpwpb_extra_service_area');
				parent.find('.mpwpb_summary_area_left').slideDown('fast');
				parent.find('.next_date_time_area').slideDown('fast');
				if (target_extra_service.length > 0) {
					target_extra_service.slideDown(350);
					loadBgImage();
					pageScrollTo(target_extra_service);
				} else {
					parent.find('.mpwpb_service_next').trigger('click');
				}
			});
		}
	});
	$(document).on('click', 'form.mpwpb_registration .mpwpb_service_next', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		let service = parent.find('[name="mpwpb_service"]').val();
		if (service) {
			parent.find('.all_service_area').slideUp(350);
			parent.find('.mpwpb_date_time_tab').addClass('mpActive').removeClass('mpDisabled').trigger('click');
			loadBgImage();
			pageScrollTo(parent.find('.mpwpb_date_time_area'));
		} else {
			mp_alert($(this));
		}
	});
	//==========date============//
	$(document).on('change', 'form.mpwpb_registration [name="mpwpb_date"]', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		let date = parent.find('[name="mpwpb_date"]').val();
		if (date) {
			let current_date = parent.find('.mpwpb_date_time_area [data-radio-check="' + date + '"]').data('date');
			parent.find('.mpwpb_summary_item[data-date]').slideDown('fast').find('h6').html(current_date);
		} else {
			parent.find('.mpwpb_summary_item[data-date]').slideUp('fast');
		}
	});
	$(document).on('click', 'form.mpwpb_registration .mpwpb_date_time_next', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		let date = parent.find('[name="mpwpb_date"]').val();
		if (date) {
			parent.find('.mptbm_summary_tab').addClass('mpActive').removeClass('mpDisabled').trigger('click');
			//parent.find('[name="mpwpb_payment_system"]').trigger('change');
			pageScrollTo(parent.find('.mpwpb_summary_area'));
		} else {
			mp_alert($(this));
		}
	});
	$(document).on('click', 'form.mpwpb_registration .mpwpb_date_time_prev', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		parent.find('.mptbm_service_tab').addClass('mpActive').removeClass('mpDisabled').trigger('click');
	});
	//========Extra service==============//
	$(document).on('change', 'form.mpwpb_registration [name="mpwpb_extra_service_qty[]"]', function () {
		$(this).closest('.mpwpb_extra_service_item').find('[name="mpwpb_extra_service_type[]"]').trigger('change');
	});
	$(document).on('change', 'form.mpwpb_registration [name="mpwpb_extra_service_type[]"]', function () {
		let parent = $(this).closest('form.mpwpb_registration');
		let service_name = $(this).data('value');
		let service_value = $(this).val();
		if (service_value) {
			let qty = $(this).closest('.mpwpb_extra_service_item').find('[name="mpwpb_extra_service_qty[]"]').val();
			parent.find('[data-extra-service="' + service_name + '"]').slideDown(350).find('.ex_service_qty').html('x' + qty);
		} else {
			parent.find('[data-extra-service="' + service_name + '"]').slideUp(350);
		}
		mpwpb_price_calculation($(this));
	});
	$(document).on('click', 'form.mpwpb_registration .mpwpb_price_calculation', function () {
		mpwpb_price_calculation($(this));
	});
	//======================//
	$(document).on('change', 'form.mpwpb_registration [name="mpwpb_payment_system"]', function () {
		let current = $(this);
		let target = current.closest('form.mpwpb_registration').find('.mpwpb_direct_order_info');
		let payment_system = current.val();
		if (payment_system === 'direct_order') {
			target.slideDown(350);
			target.find('[name="mpwpb_bill_name"]').attr('required', 'required');
			target.find('[name="mpwpb_bill_email"]').attr('required', 'required');
		} else {
			target.slideUp(350);
			target.find('[name="mpwpb_bill_name"]').removeAttr('required');
			target.find('[name="mpwpb_bill_email"]').removeAttr('required');
		}
	});
	//======================//
	$(document).on("click", "form.mpwpb_registration .mpwpb_book_now[type='button']", function () {
		let current = $(this);
		let parent = current.closest('form.mpwpb_registration');
		let date = parent.find('[name="mpwpb_date"]').val();
		let service = parent.find('[name="mpwpb_service"]').val();
		if (date && service) {
			let payment_system = parent.find('[name="mpwpb_payment_system"]').val();
			if (payment_system) {
				let error = 0;
				parent.find('.formControl').each(function () {
					if ($(this).is(':required') && $(this).val() === '') {
						$(this).addClass('mpRequired');
						error = 1;
					} else {
						$(this).removeClass('mpRequired');
					}
				});
				if (error < 1) {
					if (payment_system === 'woocommerce') {
						parent.attr('action', '').promise().done(function () {
							parent.find('.mpwpb_add_to_cart').trigger('click');
						});
					}
					if (payment_system === 'direct_order') {
						parent.submit();
					}
				}
			} else {
				mp_alert($(this));
			}
		}
	});
}(jQuery));