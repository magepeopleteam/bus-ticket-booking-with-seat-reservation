/*
 * Event / embedded booking forms ([wbtm-bus-event], [wbtm-bus-booking], scoped
 * [wbtm-bus-search]).
 *
 * The scoped form is the standard search form; its hidden `wbtm_bus_scope`
 * input (inc/WBTM_Bus_Scope.php) carries presets as data attributes:
 *   data-from         pre-select this boarding point
 *   data-to           pre-select this destination once its list has loaded
 *   data-lock-to      "1": the destination cannot be changed
 *   data-date         fixed outbound date (Y-m-d)
 *   data-auto-search  "1": run the search once From, To and date are set
 *
 * The core form loads each step by AJAX (From -> To list -> journey dates ->
 * results), so the presets are applied from ajaxComplete as each step returns;
 * wbtm_global.js keeps doing all the actual work.
 */
(function ($) {
	"use strict";

	function scopeInput(area) {
		return area.find('input[name="wbtm_bus_scope"]').first();
	}
	function scopedAreas() {
		return $('div#wbtm_area').filter(function () {
			return scopeInput($(this)).length > 0;
		});
	}
	function preset(area, key) {
		return String(scopeInput(area).attr('data-' + key) || '');
	}
	function norm(value) {
		return String(value || '').trim().toLowerCase();
	}
	function message(text) {
		if (!text) {
			return;
		}
		if (typeof wbtm_toast === 'function') {
			wbtm_toast(text);
		} else {
			window.alert(text);
		}
	}
	function strings() {
		return typeof wbtm_event_strings !== 'undefined' ? wbtm_event_strings : {};
	}
	// <li data-value> of a .wbtm_input_select list matching `value`, case-insensitively.
	function findListItem(holder, value) {
		let wanted = norm(value);
		return holder.find('.wbtm_input_select_list li').filter(function () {
			return norm($(this).attr('data-value')) === wanted;
		}).first();
	}
	function setStart(area, place) {
		let item = findListItem(area.find('.wbtm_start_point'), place);
		if (!item.length) {
			message(strings().stop_unavailable);
			return false;
		}
		area.find('.wbtm_start_point input.formControl').val(item.attr('data-value')).trigger('mp_change');
		return true;
	}
	function maybeAutoSearch(area) {
		if (preset(area, 'auto-search') !== '1') {
			return;
		}
		let start = area.find('input[name="bus_start_route"]').val();
		let end = area.find('input[name="bus_end_route"]').val();
		let date = area.find('input[name="j_date"]').val();
		if (start && end && date) {
			area.find('button.get_wbtm_bus_list').first().trigger('click');
		}
	}
	function showDate(area, ymd) {
		let parts = ymd.split('-');
		let input = area.find('#wbtm_journey_date');
		if (!input.length || parts.length !== 3 || !$.datepicker) {
			return;
		}
		let dateObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
		let format = typeof wbtm_date_format !== 'undefined' ? wbtm_date_format : 'yy-mm-dd';
		input.val($.datepicker.formatDate(format, dateObj));
	}
	// The form an AJAX step belongs to: same bus (post_id) and same From value.
	function areaForRequest(params) {
		let areas = scopedAreas();
		if (areas.length < 2) {
			return areas.first();
		}
		return areas.filter(function () {
			let area = $(this);
			return String(area.find('[name="wbtm_post_id"]').val() || '0') === String(params.get('post_id') || '0')
				&& area.find('input[name="bus_start_route"]').val() === (params.get('start_route') || '');
		}).first();
	}

	function init(area) {
		if (area.data('wbtmScopeReady')) {
			return;
		}
		area.data('wbtmScopeReady', true);
		let to = preset(area, 'to');
		if (to && preset(area, 'lock-to') === '1') {
			area.addClass('wbtm_scope_lock_to');
			area.find('.wbtm_dropping_point input.formControl').prop('readonly', true).val(to);
			// Boarding at the destination itself is never a valid trip here.
			findListItem(area.find('.wbtm_start_point'), to).remove();
		}
		let from = preset(area, 'from');
		if (from) {
			setStart(area, from);
		}
	}

	$(function () {
		scopedAreas().each(function () {
			init($(this));
		});
	});

	// User picked a journey date themselves.
	$(document).on('change', '#wbtm_area input[name="j_date"]', function () {
		let area = $(this).closest('#wbtm_area');
		if (scopeInput(area).length && !area.data('wbtmPresetDate')) {
			maybeAutoSearch(area);
		}
		area.removeData('wbtmPresetDate');
	});

	$(document).ajaxComplete(function (event, xhr, settings) {
		if (!settings || typeof settings.data !== 'string' || settings.data.indexOf('wbtm_bus_scope=') === -1) {
			return;
		}
		let params = new URLSearchParams(settings.data);
		let action = params.get('action');
		let area = areaForRequest(params);
		if (!area.length) {
			return;
		}
		if (action === 'get_wbtm_dropping_point') {
			let to = preset(area, 'to');
			if (!to) {
				return;
			}
			let holder = area.find('.wbtm_dropping_point');
			let item = findListItem(holder, to);
			if (item.length) {
				holder.find('input.formControl').val(item.attr('data-value')).trigger('mp_change');
			} else if (preset(area, 'lock-to') === '1') {
				holder.find('input.formControl').val(to);
				message(strings().no_route);
			}
		} else if (action === 'get_wbtm_journey_date') {
			let date = preset(area, 'date');
			if (!date) {
				return;
			}
			let parts = date.split('-');
			let dmy = parseInt(parts[2], 10) + '-' + parseInt(parts[1], 10) + '-' + parts[0];
			// The picker script lists bookable days as "j-n-Y" strings.
			if (String(xhr.responseText || '').indexOf('"' + dmy + '"') === -1) {
				message(strings().date_unavailable);
				return;
			}
			showDate(area, date);
			// Triggers the core return-date reload; flagged so it does not also
			// count as the customer's own date pick.
			area.data('wbtmPresetDate', true);
			area.find('input[name="j_date"]').val(date).trigger('change');
			maybeAutoSearch(area);
		}
	});

	// Departure overview "Book" buttons.
	$(document).on('click', '.wbtm_event_booking .wbtm_event_book_btn', function (e) {
		e.preventDefault();
		let area = $(this).closest('.wbtm_event_booking').find('div#wbtm_area').first();
		if (!area.length) {
			return;
		}
		if (setStart(area, $(this).attr('data-from'))) {
			$('html, body').animate({scrollTop: area.offset().top - 80}, 300);
		}
	});
}(jQuery));
