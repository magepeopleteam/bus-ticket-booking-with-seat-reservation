//======================================================Price Format==============//
function mp_price_format(price) {
	if (typeof price === 'string') {
		price = Number(price);
	}
	price = price.toFixed(mp_num_of_decimal);
	let total_part = price.toString().split(".");
	total_part[0] = total_part[0].replace(/\B(?=(\d{3})+(?!\d))/g, mp_currency_thousands_separator);
	price = total_part.join(mp_currency_decimal);
	let price_text = '';
	if (mp_currency_position === 'right') {
		price_text = price + mp_currency_symbol;
	} else if (mp_currency_position === 'right_space') {
		price_text = price + '&nbsp;' + mp_currency_symbol;
	} else if (mp_currency_position === 'left') {
		price_text = mp_currency_symbol + price;
	} else {
		price_text = mp_currency_symbol + '&nbsp;' + price;
	}
	return price_text;
}
//=======================================================Loader==============//
function dLoader(target) {
	if (target.find('div[class*="dLoader"]').length < 1) {
		target.addClass('pRelative').append('<div class="dLoader"><span class="fas fa-spinner fa-pulse"></span></div>');
	}
}
function dLoader_xs(target) {
	if (target.find('div[class*="dLoader"]').length < 1) {
		target.addClass('pRelative').append('<div class="dLoader_xs"><span class="fas fa-spinner fa-pulse"></span></div>');
	}
}
function simpleSpinner(target) {
	if (target.find('div[class*="simpleSpinner"]').length < 1) {
		target.addClass('pRelative').append('<div class="simpleSpinner"><span class="fas fa-spinner fa-pulse"></span></div>');
	}
}
function simpleSpinnerRemove(target = jQuery('body')) {
	target.removeClass('noScroll');
	target.removeClass('pRelative').find('div[class*="simpleSpinner"]').remove();
}
function dLoaderBody() {
	let body = jQuery('body');
	if (body.find('div[class*="dLoader"]').length < 1) {
		body.addClass('noScroll').append('<div class="dLoader pFixed"><span class="fas fa-spinner fa-pulse"></span></div>');
	}
}
function dLoaderBody_xs() {
	let body = jQuery('body');
	if (body.find('div[class*="dLoader"]').length < 1) {
		body.addClass('noScroll').append('<div class="dLoader_xs pFixed"><span class="fas fa-spinner fa-pulse"></span></div>');
	}
}
function dLoader_circle(target) {
	if (target.find('div[class*="dLoader"]').length < 1) {
		target.addClass('pRelative').append('<div class="dLoader border_spin_loader"><span class="circle"></span></div>');
	}
}
function dLoader_xs_circle(target) {
	if (target.find('div[class*="dLoader"]').length < 1) {
		target.addClass('pRelative').append('<div class="dLoader_xs border_spin_loader"><span class="circle"></span></div>');
	}
}
function dLoaderRemove(target = jQuery('body')) {
	target.removeClass('noScroll');
	target.removeClass('pRelative').find('div[class*="dLoader"]').remove();
}
function placeholderLoader(target) {
	target.addClass('placeholderLoader');
}
function placeholderLoaderRemove(target) {
	target.each(function () {
		target.removeClass('placeholderLoader');
	})
}
//======================================================Page Scroll==============//
function pageScrollTo(target) {
	jQuery('html, body').animate({
		scrollTop: target.offset().top -= 150
	}, 1000);
}
//====================================================Load Date picker==============//
function mp_load_date_picker(parent = jQuery('.mpStyle')) {
	parent.find(".date_type.hasDatepicker").each(function () {
		jQuery(this).removeClass('hasDatepicker').attr('id', '').removeData('datepicker').unbind();
	}).promise().done(function () {
		parent.find(".date_type").datepicker({
			dateFormat: mp_date_format,
			//showButtonPanel: true,
			autoSize: true,
			changeMonth: true,
			changeYear: true,
			onSelect: function (dateString, data) {
				let date = data.selectedYear + '-' + ('0' + (parseInt(data.selectedMonth) + 1)).slice(-2) + '-' + ('0' + parseInt(data.selectedDay)).slice(-2);
				jQuery(this).closest('label').find('input[type="hidden"]').val(date).trigger('change');
			},
			// closeText: 'Clear Date',
			// onClose: function (dateText, inst) {
			// 	if (jQuery(this).hasClass('ui-datepicker-close')) {
			// 		document.getElementById(this.id).reset();
			// 	}
			// }
		});
	});
	parent.find(".date_type_without_year.hasDatepicker").each(function () {
		jQuery(this).removeClass('hasDatepicker').attr('id', '').removeData('datepicker').unbind();
	}).promise().done(function () {
		parent.find(".date_type_without_year").datepicker({
			dateFormat: mp_date_format_without_year,
			//showButtonPanel: true,
			autoSize: true,
			changeMonth: true,
			changeYear: false,
			onSelect: function (dateString, data) {
				//console.log(mp_date_format_without_year);
				let date = ('0' + (parseInt(data.selectedMonth) + 1)).slice(-2) + '-' + ('0' + parseInt(data.selectedDay)).slice(-2);
				jQuery(this).closest('label').find('input[type="hidden"]').val(date).trigger('change');
			}
		});
	});
}
//========================================================Alert==============//
function mp_alert($this, attr = 'alert') {
	alert($this.data(attr));
}
//=====================================================Load initial=================//
(function ($) {
	"use strict";
	$(document).ready(function () {
		mp_load_date_picker();
		$('.mp_select2').select2({});
	});
}(jQuery));
//====================================================================Load Bg Image=================//
function loadBgImage() {
	jQuery('body').find('[data-bg-image]:visible').each(function () {
		let target = jQuery(this);
		let width = target.outerWidth();
		let height = target.outerHeight();
		if (target.css('background-image') === 'none' || width === 0 || height === 0) {
			let bg_url = target.data('bg-image');
			if (!bg_url || bg_url.width === 0 || bg_url.width === 'undefined') {
				bg_url = mp_empty_image_url;
			}
			mp_resize_bg_image_area(target, bg_url);
			target.css('background-image', 'url("' + bg_url + '")').promise().done(function () {
				dLoaderRemove(jQuery(this));
			});
		}
	});
	return true;
}
function mp_resize_bg_image_area(target, bg_url) {
	let tmpImg = new Image();
	tmpImg.src = bg_url;
	jQuery(tmpImg).one('load', function () {
		let imgWidth = tmpImg.width;
		let imgHeight = tmpImg.height;
		let height = target.outerWidth() * imgHeight / imgWidth;
		target.css({"min-height": height});
	});
}
(function ($) {
	let bg_image_load = false;
	$(document).ready(function () {
		$('body').find('[data-bg-image]').each(function () {
			dLoader($(this));
		});
		$(window).on('load', function () {
			load_initial();
		});
		if (!bg_image_load) {
			load_initial();
			$(document).scroll(function () {
				load_initial();
			});
		}
	});
	$(document).on('click', '[data-href]', function () {
		let href = $(this).data('href');
		if (href) {
			window.location.href = href;
		}
	});
	$(window).on('load , resize', function () {
		$('body').find('[data-bg-image]:visible').each(function () {
			let target = $(this);
			let bg_url = target.data('bg-image');
			if (!bg_url || bg_url.width === 0 || bg_url.width === 'undefined') {
				bg_url = mp_empty_image_url;
			}
			mp_resize_bg_image_area(target, bg_url);
		});
	});
	function load_initial() {
		if (!bg_image_load) {
			if (loadBgImage()) {
				bg_image_load = true;
				placeholderLoaderRemove($('.mpStyle.placeholderLoader'))
			}
		}
	}
}(jQuery));
//=============================================================================Change icon and text=================//
function content_icon_change(currentTarget) {
	let openIcon = currentTarget.data('open-icon');
	let closeIcon = currentTarget.data('close-icon');
	if (openIcon || closeIcon) {
		currentTarget.find('[data-icon]').toggleClass(closeIcon).toggleClass(openIcon);
	}
}
function content_text_change(currentTarget) {
	let openText = currentTarget.data('open-text');
	openText = openText ? openText.toString() : '';
	let closeText = currentTarget.data('close-text');
	closeText = closeText ? closeText : '';
	if (openText || closeText) {
		let text = currentTarget.find('[data-text]').html();
		text = text ? text.toString() : ''
		if (text !== openText) {
			currentTarget.find('[data-text]').html(openText);
		} else {
			currentTarget.find('[data-text]').html(closeText);
		}
	}
}
function content_class_change(currentTarget) {
	let clsName = currentTarget.data('add-class');
	if (clsName) {
		if (currentTarget.find('[data-class]').length > 0) {
			currentTarget.find('[data-class]').toggleClass(clsName);
		} else {
			currentTarget.toggleClass(clsName);
		}
	}
}
function content_input_value_change(currentTarget) {
	currentTarget.find('[data-value]').each(function () {
		let value = jQuery(this).val();
		if (value) {
			jQuery(this).val('');
		} else {
			jQuery(this).val(jQuery(this).data('value'));
		}
		jQuery(this).trigger('change');
	});
}
function mp_all_content_change($this) {
	loadBgImage();
	content_class_change($this);
	content_icon_change($this);
	content_text_change($this);
	content_input_value_change($this);
}
(function ($) {
	"use strict";
	$(document).on('click', '.mp_load_more_text_area [data-read]', function (e) {
		e.stopPropagation();
		let parent = $(this).closest('.mp_load_more_text_area');
		let open_text = parent.find('[data-read-open]').html();
		let close_text = parent.find('[data-read-close]').html();
		parent.find('[data-read-close]').html(open_text);
		parent.find('[data-read-open]').html(close_text);
		content_text_change($(this));
	});
	$(document).on('click', '[data-all-change]', function () {
		mp_all_content_change($(this));
	});
	$(document).on('click', '[data-icon-change]', function () {
		content_icon_change($(this));
	});
	$(document).on('click', '[data-text-change]', function () {
		content_text_change($(this));
	});
	$(document).on('click', '[data-class-change]', function () {
		content_class_change($(this));
	});
	$(document).on('click', '[data-value-change]', function () {
		content_input_value_change($(this));
	});
	$(document).on('keyup change', '.mpStyle [data-input-text]', function () {
		let input_value = $(this).val();
		let input_id = $(this).data('input-text');
		$("[data-input-change='" + input_id + "']").each(function () {
			$(this).html(input_value);
		});
	});
	$(document).on('keyup change', '.mpStyle [data-target-same-input]', function () {
		let input_value = $(this).val();
		let input_id = $(this).data('target-same-input');
		$("[data-same-input='" + input_id + "']").each(function () {
			$(this).val(input_value);
		});
	});
}(jQuery));
//==============================================================================Qty inc dec================//
(function ($) {
	"use strict";
	$(document).on("click", "div.mpStyle .decQty ,div.mpStyle .incQty", function () {
		let current = $(this);
		let target = current.closest('.qtyIncDec').find('input');
		let currentValue = parseInt(target.val());
		let value = current.hasClass('incQty') ? (currentValue + 1) : ((currentValue - 1) > 0 ? (currentValue - 1) : 0);
		let min = parseInt(target.attr('min'));
		let max = parseInt(target.attr('max'));
		target.parents('.qtyIncDec').find('.incQty , .decQty').removeClass('mpDisabled');
		if (value < min || isNaN(value) || value === 0) {
			value = min;
			target.parents('.qtyIncDec').find('.decQty').addClass('mpDisabled');
		}
		if (value > max) {
			value = max;
			target.parents('.qtyIncDec').find('.incQty').addClass('mpDisabled');
		}
		target.val(value).trigger('change').trigger('input');
	});
}(jQuery));
//==============================================================================Input use as select================//
(function ($) {
	"use strict";
	$(document).on("click", "div.mpStyle .mp_input_select .mp_input_select_list li", function (e) {
		e.preventDefault();
		let current = $(this);
		let parent = $(this).closest('.mp_input_select');
		let value = current.data('value');
		let text = current.html();
		parent.find('.mp_input_select_list').slideUp(250);
		if (parent.find('input[type="hidden"]').length > 0) {
			parent.find('input.formControl').val(text);
			parent.find('input[type="hidden"]').val(value).trigger('mp_change');
		} else {
			parent.find('input.formControl').val(value).trigger('mp_change');
		}
	});
	$(document).on({
		keyup: function () {
			let input = $(this).val().toLowerCase();
			$(this).closest('.mp_input_select').find('.mp_input_select_list li').each(function () {
				let input_length = input.length;
				$(this).toggle($(this).attr('data-value').toLowerCase().substring(0, input_length) === input);
			});
			$(this).closest('.mp_input_select').find('.mp_input_select_list').slideDown(200);
		},
		click: function () {
			$('body').find('.mp_input_select .mp_input_select_list').slideUp(250);
			let input = $(this).val().toLowerCase();
			$(this).closest('.mp_input_select').find('.mp_input_select_list li').each(function () {
				let data = $(this).attr('data-value').toLowerCase();
				if (!input || input === data) {
					$(this).slideDown('fast');
				}
			});
			$(this).closest('.mp_input_select').find('.mp_input_select_list').slideDown(250);
		}
	}, 'div.mpStyle .mp_input_select input.formControl');
}(jQuery));
//============================================================================Sticky================//
function mp_sticky_management() {
	if (jQuery('.mpStyle .mp_sticky_area').length > 0) {
		window.onscroll = function () {
			jQuery('.mpStyle .mp_sticky_area').each(function () {
				let current = jQuery(this);
				let target_scroll = current.find('.mp_sticky_on_scroll');
				let parent = current.closest('.mp_sticky_section');
				let target_content = parent.find('.mp_sticky_depend_area');
				let scroll_top = jQuery(window).scrollTop();
				let content_top = target_content.offset().top;
				let scroll_height = target_content.innerHeight() - target_scroll.innerHeight();
				if (jQuery('body').outerWidth() > 800) {
					if (scroll_top > content_top + scroll_height - 100) {
						if (!current.hasClass('stickyFixed')) {
							current.removeClass('mpSticky').addClass('stickyFixed');
						}

					} else if (scroll_top > content_top - 100) {
						if (!current.hasClass('mpSticky')) {
							current.addClass('mpSticky').removeClass('stickyFixed');
						}
					} else {
						current.removeClass('mpSticky').removeClass('stickyFixed');

					}

				} else {
					current.removeClass('mpSticky').removeClass('stickyFixed');
				}
			});
		};
	}
}
(function ($) {
	"use strict";
	$(document).ready(function () {
		mp_sticky_management();
	});
}(jQuery));
//============================================================================Tabs================//
(function ($) {
	"use strict";
	function active_next_tab(parent, targetTab) {
		parent.height(parent.height());
		let tabsContent = parent.find('.tabsContentNext:first');
		let target_tabContent = tabsContent.children('[data-tabs-next="' + targetTab + '"]');
		let index = target_tabContent.index() + 1;
		let num_of_tab = parent.find('.tabListsNext:first').children('[data-tabs-target-next]').length;
		let i = 1;
		for (i; i <= num_of_tab; i++) {
			let target_tab = parent.find('.tabListsNext:first').children('[data-tabs-target-next]:nth-child(' + i + ')');
			if (i <= index) {
				target_tab.addClass('active');
			} else {
				target_tab.removeClass('active');
			}
			if (i === index - 1) {
				mp_all_content_change(target_tab);
			}
		}
		if (index < 2 && num_of_tab > index) {
			parent.find('.nextTab_next').slideDown('fast');
			parent.find('.nextTab_prev').slideUp('fast');
		} else if (num_of_tab === index) {
			parent.find('.nextTab_next').slideUp('fast');
			parent.find('.nextTab_prev').slideDown('fast');
		} else {
			parent.find('.nextTab_next').slideDown('fast');
			parent.find('.nextTab_prev').slideDown('fast');
		}
		target_tabContent.slideDown(350);
		tabsContent.children('[data-tabs-next].active').slideUp(350).removeClass('active').promise().done(function () {
			target_tabContent.addClass('active').promise().done(function () {
				pageScrollTo(tabsContent);
				parent.height('auto').promise().done(function () {
					loadBgImage();
					mp_sticky_management();
					dLoaderRemove(parent);
				});
			});
		});
	}
	$(document).on('click', '.mpStyle .mpTabsNext .nextTab_prev_link', function () {
		let parent = $(this).closest('.mpTabsNext');
		if (parent.find('[data-tabs-target-next].active').length > 1) {
			parent.find('.nextTab_prev').trigger('click');
		}
	});
	$(document).on('click', '.mpStyle .mpTabsNext .nextTab_next', function () {
		let parent = $(this).closest('.mpTabsNext');
		let target = parent.find('.tabListsNext:first');
		let num_of_tab = target.children('[data-tabs-target-next].active').length + 1;
		let targetTab = target.children('[data-tabs-target-next]:nth-child(' + num_of_tab + ')').data('tabs-target-next');
		active_next_tab(parent, targetTab);
	});
	$(document).on('click', '.mpStyle .mpTabsNext .nextTab_prev', function () {
		let parent = $(this).closest('.mpTabsNext');
		let target = parent.find('.tabListsNext:first');
		let num_of_tab = target.children('[data-tabs-target-next].active').length - 1;
		let targetTab = target.children('[data-tabs-target-next]:nth-child(' + num_of_tab + ')').data('tabs-target-next');
		active_next_tab(parent, targetTab);
	});
	$(document).ready(function () {
		$('.mpStyle .mpTabs').each(function () {
			let tabLists = $(this).find('.tabLists:first');
			let activeTab = tabLists.find('[data-tabs-target].active');
			let targetTab = activeTab.length > 0 ? activeTab : tabLists.find('[data-tabs-target]').first();
			targetTab.trigger('click');
		});
		$('.mpStyle .mpTabsNext').each(function () {
			let parent = $(this);
			if (parent.find('[data-tabs-target-next].active').length < 1) {
				dLoader(parent);
				let tabLists = parent.find('.tabListsNext:first');
				let targetTab = tabLists.find('[data-tabs-target-next]').first().data('tabs-target-next')
				active_next_tab(parent, targetTab);
			}
		});
	});
	$(document).on('click', '.mpStyle [data-tabs-target]', function () {
		if (!$(this).hasClass('active')) {
			let tabsTarget = $(this).data('tabs-target');
			let parent = $(this).closest('.mpTabs');
			parent.height(parent.height());
			let tabLists = $(this).closest('.tabLists');
			let tabsContent = parent.find('.tabsContent:first');
			tabLists.find('[data-tabs-target].active').each(function () {
				$(this).removeClass('active').promise().done(function () {
					mp_all_content_change($(this))
				});
			});
			$(this).addClass('active').promise().done(function () {
				mp_all_content_change($(this))
			});
			tabsContent.children('[data-tabs="' + tabsTarget + '"]').slideDown(350);
			tabsContent.children('[data-tabs].active').slideUp(350).removeClass('active').promise().done(function () {
				tabsContent.children('[data-tabs="' + tabsTarget + '"]').addClass('active').promise().done(function () {
					//dLoaderRemove(tabsContent);
					loadBgImage();
					parent.height('auto');
				});
			});
		}
	});
}(jQuery));
//======================================================================Collapse=================//
(function ($) {
	"use strict";
	$(document).on('click', '[data-collapse-target]', function () {
		let currentTarget = $(this);
		let target_id = currentTarget.data('collapse-target');
		let close_id = currentTarget.data('close-target');
		let target = $('[data-collapse="' + target_id + '"]');
		if (target_close(close_id, target_id) && collapse_close_inside(currentTarget) && target_collapse(target, currentTarget)) {
			mp_all_content_change(currentTarget);
		}
	});
	$(document).on('change', 'select[data-collapse-target]', function () {
		let currentTarget = $(this);
		let value = currentTarget.val();
		currentTarget.find('option').each(function () {
			if ($(this).attr('data-option-target-multi')) {
				let target_ids = $(this).data('option-target-multi');
				target_ids = target_ids.toString().split(" ");
				target_ids.forEach(function (target_id) {
					let target = $('[data-collapse="' + target_id + '"]');
					target.slideUp(350).removeClass('mActive');
				});
			} else {
				let target_id = $(this).data('option-target');
				let target = $('[data-collapse="' + target_id + '"]');
				target.slideUp('fast').removeClass('mActive');
			}
		}).promise().done(function () {
			currentTarget.find('option').each(function () {
				let current_value = $(this).val();
				if (current_value === value) {
					if ($(this).attr('data-option-target-multi')) {
						let target_ids = $(this).data('option-target-multi');
						target_ids = target_ids.toString().split(" ");
						target_ids.forEach(function (target_id) {
							let target = $('[data-collapse="' + target_id + '"]');
							target.slideDown(350).removeClass('mActive');
						});
					} else {
						let target_id = $(this).data('option-target');
						let target = $('[data-collapse="' + target_id + '"]');
						target.slideDown(350).removeClass('mActive');
					}
				}
			});
		});
	});
	function target_close(close_id, target_id) {
		$('body').find('[data-close="' + close_id + '"]:not([data-collapse="' + target_id + '"])').slideUp(250);
		return true;
	}
	function target_collapse(target, $this) {
		if ($this.is('[type="radio"]')) {
			target.slideDown(250);
		} else {
			target.each(function () {
				$(this).slideToggle(250).toggleClass('mActive');
			});
		}
		return true;
	}
	function collapse_close_inside(currentTarget) {
		let parent_target_close = currentTarget.data('collapse-close-inside');
		if (parent_target_close) {
			$(parent_target_close).find('[data-collapse]').each(function () {
				if ($(this).hasClass('mActive')) {
					let collapse_id = $(this).data('collapse');
					let target_collapse = $('[data-collapse-target="' + collapse_id + '"]');
					if (collapse_id !== currentTarget.data('collapse-target')) {
						$(this).slideUp(250).removeClass('mActive');
						let clsName = target_collapse.data('add-class');
						if (clsName) {
							target_collapse.removeClass(clsName);
						}
						content_text_change(target_collapse);
						content_icon_change(target_collapse);
					}
				}
			})
		}
		return true;
	}
}(jQuery));
//=====================================================================Group Check box==========//
(function ($) {
	"use strict";
	$(document).on('click', '.groupCheckBox .customCheckboxLabel', function () {
		let parent = $(this).closest('.groupCheckBox');
		let value = '';
		let separator = ',';
		parent.find(' input[type="checkbox"]').each(function () {
			if ($(this).is(":checked")) {
				let currentValue = $(this).attr('data-checked');
				value = value + (value ? separator : '') + currentValue;
			}
		}).promise().done(function () {
			parent.find('input[type="hidden"]').val(value);
		});
	});
	// radio
	$(document).on('click', '[data-radio]', function () {
		let target = $(this).closest('label');
		let value = $(this).attr('data-radio');
		target.find('.customRadio').removeClass('active');
		$(this).addClass('active');
		target.find('input').val(value).trigger('change');
	});
	$(document).on('click', '.groupRadioBox [data-group-radio]', function () {
		let parent = $(this).closest('.groupRadioBox');
		let $this = $(this);
		let value = $this.data('group-radio');
		parent.find('[data-group-radio]').each(function () {
			$(this).removeClass('active');
		}).promise().done(function () {
			$this.addClass('active');
			parent.find('input[type="text"]').val(value);
		});
	});
	//Group radio like checkbox
	$(document).on('click', '.groupRadioCheck [data-radio-check]', function () {
		//e.stopPropagation();
		let parent = $(this).closest('.groupRadioCheck');
		let $this = $(this);
		if (!$this.hasClass('mpActive')) {
			let value = $this.data('radio-check');
			parent.find('.mpActive[data-radio-check]').each(function () {
				$(this).removeClass('mpActive');
				mp_all_content_change($(this));
			}).promise().done(function () {
				$this.addClass('mpActive');
				mp_all_content_change($this);
				parent.find('input[type="hidden"]').val(value).trigger('change');
			});
		}
	});
}(jQuery));
//=======================================================validation ==============//
function mp_check_required(input) {
	if (input.val() !== '') {
		input.removeClass('mpRequired');
		return true;
	} else {
		input.addClass('mpRequired');
		return false;
	}
}
(function ($) {
	"use strict";
	$(document).on('keyup change', '.mpStyle .mp_number_validation', function () {
		let n = $(this).val();
		$(this).val(n.replace(/\D/g, ''));
		return true;
	});
	$(document).on('keyup change', '.mpStyle .mp_price_validation', function () {
		let n = $(this).val();
		$(this).val(n.replace(/[^\d.]/g, ''));
		return true;
	});
	$(document).on('keyup change', '.mpStyle .mp_id_validation', function () {
		let n = $(this).val();
		$(this).val(n.replace(/[^\d_a-zA-Z]/g, ''));
		return true;
	});
	$(document).on('keyup change', '.mpStyle .mp_name_validation', function () {
		let n = $(this).val();
		$(this).val(n.replace(/[@%'":;&_]/g, ''));
		return true;
	});
	$(document).on('keyup change', '.mpStyle [required]', function () {
		mp_check_required($(this));
	});
}(jQuery));
//==========================================================pagination==========//
function mp_pagination_page_management(parent, pagination_page, total_item) {
	let per_page_item = parseInt(parent.find('input[name="pagination_per_page"]').val());
	let total_active_page = Math.floor(total_item / per_page_item) + ((total_item % per_page_item) > 0 ? 1 : 0);
	let page_limit_start = (pagination_page > 2) ? (pagination_page - 2) : 0;
	let page_limit_end = (pagination_page > 2) ? (pagination_page + 2) : 4;
	let limit_dif = total_active_page - pagination_page;
	if (total_active_page > 5 && limit_dif < 3) {
		page_limit_start = page_limit_start - ((limit_dif > 1) ? 1 : 2);
	}
	let total_page = parent.find('[data-pagination]').length;
	for (let i = 0; i < total_page; i++) {
		if (i < total_active_page && i >= page_limit_start && i <= page_limit_end) {
			parent.find('[data-pagination="' + i + '"]').slideDown(200);
		} else {
			parent.find('[data-pagination="' + i + '"]').slideUp(200);
		}
	}
	if (pagination_page > 0) {
		parent.find('.page_prev').removeAttr('disabled');
	} else {
		parent.find('.page_prev').prop('disabled', true);
	}
	if (pagination_page > 2 && total_active_page > 5) {
		parent.find('.ellipse_left').slideDown(200);
	} else {
		parent.find('.ellipse_left').slideUp(200);
	}
	if (pagination_page < total_active_page - 3 && total_active_page > 5) {
		parent.find('.ellipse_right').slideDown(200);
	} else {
		parent.find('.ellipse_right').slideUp(200);
	}
	if (pagination_page < total_active_page - 1) {
		parent.find('.page_next').removeAttr('disabled');
	} else {
		parent.find('.page_next').prop('disabled', true);
	}
	return true;
}
(function ($) {
	"use strict";
	$(document).on('click', '.pagination_area .page_prev', function (e) {
		e.preventDefault();
		let parent = $(this).closest('.pagination_area');
		let page_no = parseInt(parent.find('.active_pagination').data('pagination')) - 1;
		parent.find('[data-pagination="' + page_no + '"]').trigger('click');
	});
	$(document).on('click', '.pagination_area .page_next', function (e) {
		e.preventDefault();
		let parent = $(this).closest('.pagination_area');
		let page_no = parseInt(parent.find('.active_pagination').data('pagination')) + 1;
		parent.find('[data-pagination="' + page_no + '"]').trigger('click');
	});
	//*************** Pagination Load More ***************//
	$(document).on('click', 'div.mp_pagination_main_area  .pagination_load_more', function () {
		let pagination_page = parseInt($(this).attr('data-load-more'))+1;
		let parent = $(this).closest('div.mp_pagination_main_area');
		let per_page_item = parseInt(parent.find('input[name="pagination_per_page"]').val());
		let count=0;
		let end_item = per_page_item*pagination_page+per_page_item;
		$(this).attr('data-load-more', pagination_page).promise().done(function () {
			parent.find('.mp_pagination_item').each(function (){
				if(count<end_item){
					$(this).slideDown(250);
				}
				count++;
			});
		}).promise().done(function () {
			lode_more_init(parent);
		}).promise().done(function () {
			loadBgImage();
		});
	});
	function lode_more_init(parent) {
		if (parent.find('.mp_pagination_item:hidden').length === 0) {
			parent.find('[data-load-more]').attr('disabled', 'disabled');
		} else {
			parent.find('[data-load-more]').removeAttr('disabled');
		}
	}
	function load_more_scroll(parent, pagination_page) {
		let per_page_item = parseInt(parent.find('input[name="pagination_per_page"]').val());
		let start_item = pagination_page > 0 ? pagination_page * per_page_item : 0;
		let target = parent.find('.mp_pagination_item:nth-child(' + (start_item + 1) + ')');
		pageScrollTo(target);
	}
}(jQuery));
//==============================================================Modal / Popup==========//
(function ($) {
	"use strict";
	$(document).on('click', '.mpStyle [data-target-popup]', function () {
		let target = $(this).attr('data-active-popup', '').data('target-popup');
		$('body').addClass('noScroll').find('[data-popup="' + target + '"]').addClass('in').promise().done(function () {
			loadBgImage();
			return true;
		});
	});
	$(document).on('click', 'div.mpPopup  .popupClose', function () {
		$(this).closest('[data-popup]').removeClass('in');
		$('body').removeClass('noScroll').find('[data-active-popup]').removeAttr('data-active-popup');
		return true;
	});
}(jQuery));
//==============================================================Slider=================//
(function ($) {
	"use strict";
	//=================initial call============//
	$('.superSlider').each(function () {
		sliderItemActive($(this), 1);
	});
	//==============Slider===================//
	$(document).on('click', '.superSlider [data-slide-target]', function () {
		if (!$(this).hasClass('activeSlide')) {
			let activeItem = $(this).data('slide-target');
			let parent = $(this).closest('.superSlider');
			sliderItemActive(parent, activeItem);
			parent.find('[data-slide-target]').removeClass('activeSlide');
			$(this).addClass('activeSlide');
		}
	});
	$(document).on('click', '.superSlider .iconIndicator', function () {
		let parent = $(this).closest('.superSlider');
		let activeItem = parseInt(parent.find('.sliderAllItem').first().find('.sliderItem.activeSlide').data('slide-index'));
		if ($(this).hasClass('nextItem')) {
			++activeItem;
		} else {
			--activeItem;
		}
		sliderItemActive(parent, activeItem);
	});
	function sliderItemActive(parent, activeItem) {
		let itemLength = parent.find('.sliderAllItem').first().find('[data-slide-index]').length;
		let currentItem = getSliderItem(parent, activeItem);
		let activeCurrent = parseInt(parent.find('.sliderAllItem').first().find('.sliderItem.activeSlide').data('slide-index'));
		let i = 1;
		for (i; i <= itemLength; i++) {
			let target = parent.find('.sliderAllItem').first().find('[data-slide-index="' + i + '"]').first();
			if (i < currentItem && currentItem !== 1) {
				sliderClassControl(target, currentItem, activeCurrent, 'prevSlider', 'nextSlider');
			}
			if (i === currentItem) {
				parent.find('.sliderAllItem').first().find('[data-slide-index="' + currentItem + '"]').removeClass('prevSlider nextSlider').addClass('activeSlide');
			}
			if (i > currentItem && currentItem !== itemLength) {
				sliderClassControl(target, currentItem, activeCurrent, 'nextSlider', 'prevSlider');
			}
			if (i === itemLength && itemLength > 1) {
				if (currentItem === 1) {
					target = parent.find('.sliderAllItem').first().find('[data-slide-index="' + itemLength + '"]');
					sliderClassControl(target, currentItem, activeCurrent, 'prevSlider', 'nextSlider');
				}
				if (currentItem === itemLength) {
					target = parent.find('.sliderAllItem').first().find('[data-slide-index="1"]');
					sliderClassControl(target, currentItem, activeCurrent, 'nextSlider', 'prevSlider');
				}
			}
		}
	}
	function sliderClassControl(target, currentItem, activeCurrent, add_class, remove_class) {
		if (target.hasClass('activeSlide')) {
			if (currentItem > activeCurrent) {
				target.removeClass('activeSlide').addClass(add_class);
			} else {
				target.removeClass('activeSlide').removeClass(remove_class).addClass(add_class);
			}
		} else if (target.hasClass(remove_class)) {
			target.removeClass(remove_class).delay(600).addClass(add_class);
		} else {
			if (!target.hasClass(add_class)) {
				target.addClass(add_class);
			}
		}
	}
	function getSliderItem(parent, activeItem) {
		let itemLength = parent.find('.sliderAllItem').first().find('[data-slide-index]').length;
		activeItem = activeItem < 1 ? itemLength : activeItem;
		activeItem = activeItem > itemLength ? 1 : activeItem;
		return activeItem;
	}
	//popup
	$(document).on('click', '.superSlider [data-target-popup]', function () {
		let target = $(this).data('target-popup');
		let activeItem = $(this).data('slide-index');
		$('body').addClass('noScroll').find('[data-popup="' + target + '"]').addClass('in').promise().done(function () {
			sliderItemActive($(this), activeItem);
			loadBgImage();
		});
	});
	$(document).on('click', '.superSlider .popupClose', function () {
		$(this).closest('[data-popup]').removeClass('in');
		$('body').removeClass('noScroll');
	});
}(jQuery));
//======================================================================Outer Close==========//
(function ($) {
	"use strict";
	$(document).click(function (e) {
		let target = $(e.target);
		let popup = target.closest('[data-popup]');
		if (popup.length > 0) {
			let hLength = target.closest('.popupHeader').length;
			let bLength = target.closest('.popupBody').length;
			let fLength = target.closest('.popupFooter').length;
			if (hLength === 0 && bLength === 0 && fLength === 0) {
				popup.find('.popupClose').trigger('click');
			}
		}
		let mp_input_select_list = target.closest('.mp_input_select');
		if (mp_input_select_list.length === 0) {
			$('body').find('.mp_input_select_list').slideUp(250);
		}
	});
}(jQuery));