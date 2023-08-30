//==========Price settings=================//
(function ($) {
	"use strict";

}(jQuery));
//==========Date time settings=================//
(function ($) {
	"use strict";
}(jQuery));
//===========================================//
(function ($) {
	$(document).ready(function () {
		// Init
		wbtmSeatTypeConf();
		// Seat Type Selection
		$('select[name="wbtm_seat_type_conf"]').change(function () {
			wbtmSeatTypeConf();
		});
		// Route summary
		$('.wbtm_route_summary_btn').click(function (e) {
			e.preventDefault();
			const target = $(this).siblings('.wbtm-route-summary-inner');
			if (target.is(':visible')) {
				target.slideUp();
				$(this).text('Expand Route summary')
			} else {
				target.slideDown();
				$(this).text('Collapse Route summary')
			}
		})
		$("input[name='wbtm_bus_on_dates']").multiDatesPicker({
			dateFormat: "mm-dd",
			minDate: 0,
			beforeShow: function (input, inst) {
				$(inst.dpDiv).addClass('wbtm-hide-year');
			},
		});
		$("input[name='wbtm_bus_on_dates_return']").multiDatesPicker({
			dateFormat: "yy-mm-dd",
			minDate: 0,
			beforeShow: function (input, inst) {
				$(inst.dpDiv).addClass('wbtm-hide-year');
			},
		});
		// Off Dates
		$('.add-offday-row').on('click', function (e) {
			e.preventDefault();
			let datePickerOpt = {
				dateFormat: "mm-dd",
				minDate: 0,
				onClose: function (selectedDate) {
					$("#offday_to" + now).datepicker("option", "minDate", selectedDate);
				}
			};
			let now = Date.now();
			let parent = $(this).parents('.wbtm-offdates-wrapper');
			let row = parent.find('.empty-row-offday.screen-reader-text').clone(true);
			row.removeClass('empty-row-offday screen-reader-text');
			row.insertBefore(parent.find('.repeatable-fieldset-offday > tbody>tr:last'));
			row.find(".repeatable-offday-from-field").attr('id', 'offday_from' + now);
			row.find(".repeatable-offday-to-field").attr('id', 'offday_to' + now);
			$("#offday_from" + now).datepicker(datePickerOpt);
			$("#offday_to" + now).datepicker({
				dateFormat: "mm-dd",
				minDate: 0,
				beforeShow: function (input, inst) {
					const from_date = $("#offday_from" + now).val();
					console.log(from_date);
					$(inst.dpDiv).addClass('wbtm-hide-year');
				},
			});
		});
		$('.remove-bp-row').on('click', function () {
			$(this).parents('tr').remove();
			return false;
		});
		// Off Dates END
		// Repeatable Table
		$('.wbtom-tb-repeat-btn').on('click', function (e) {
			e.preventDefault();
			let tableFor = $(this).siblings('.repeatable-fieldset');
			let row = tableFor.find('.mtsa-empty-row-t').clone(true);
			row.removeClass('mtsa-empty-row-t');
			row.insertBefore(tableFor.find('tbody>tr:last'));
		});
		$('.wbtm-remove-row-t').on('click', function () {
			// if ( $(this).parents('tbody').children().length > 2 ) {
			$(this).parents('tr').remove();
			// }
			return false;
		});
		// Repeatable Table END
		// Extra service
		$('#add-row').on('click', function () {
			var row = $('.empty-row.screen-reader-text').clone(true);
			row.removeClass('empty-row screen-reader-text');
			row.insertBefore('#repeatable-fieldset-one tbody>tr:last');
			return false;
		});
		$('.remove-row').on('click', function () {
			if (confirm('Are You Sure , Remove this row ? \n\n 1. Ok : To Remove . \n 2. Cancel : To Cancel .')) {
				$(this).parents('tr').remove();
			} else {
				return false;
			}
		});
		// Extra service END
		// Tab script
		$('.mp_tab_menu').each(function () {
			$(this).find('ul li:first-child').trigger('click');
		});
		if ($('[name="mep_org_address"]').val() > 0) {
			$('.mp_event_address').slideUp(250);
		}
		$(document).on('click', '[data-target-tabs]', function () {
			if (!$(this).hasClass('active')) {
				let tabsTarget = $(this).attr('data-target-tabs');
				let targetParent = $(this).closest('.mp_event_tab_area').find('.mp_tab_details').first();
				targetParent.children('.mp_tab_item:visible').slideUp('fast');
				targetParent.children('.mp_tab_item[data-tab-item="' + tabsTarget + '"]').slideDown(250);
				$(this).siblings('li.active').removeClass('active');
				$(this).addClass('active');
			}
			return false;
		});
		// Tab script END
		// Hit Bulk Checkbox
		$('.wbtm_bulkcheck_hit').change(function () {
			let col_no = $(this).attr('data-col-no');
			if ($(this).is(":checked")) {
				$('.wbtm_permission_table tbody tr').each(function () {
					let el = $(this).find('td').eq(col_no);
					el.find('.wbtm_perm_checkbox').prop("checked", true);
				});
			} else {
				$('.wbtm_permission_table tbody tr').each(function () {
					let el = $(this).find('td').eq(col_no);
					el.find('.wbtm_perm_checkbox').prop("checked", false);
				});
			}
		});
		// notification
		// Route disable: Change routing
		$(document).on('change', '.wbtm_boarding_point', function () {
			const selectedRoute = $(this).find("option:selected").val();
			const newNameAttrValue = `wbtm_bus_bp_start_disable[${selectedRoute}]`;
			const parent = $(this).parents('tr');
			const target = parent.find('.route_disable_container input[type="checkbox"]');
			target.attr('name', newNameAttrValue);
			console.log(target.attr('name'));
		})
	});
	// Functions
	function wbtmSeatTypeConf() {
		let value = $('select[name="wbtm_seat_type_conf').find('option:selected').val();
		$('#mtsa_city_zone').hide();
		$('#mtpa_car_type').hide();
		if (value === 'wbtm_seat_plan') {
			$('.wbtm-seat-plan-wrapper').show();
			wbtmPriceType('general');
			$('.wbtm-seat-count').hide();
			$('#wbtm_same_bus_return').show();
		} else {
			$('.wbtm-seat-count').show();
			$('.wbtm-seat-plan-wrapper').hide();
			if (value === 'wbtm_without_seat_plan') {
				wbtmPriceType('general');
				$('#wbtm_same_bus_return').show();
			} else if (value === 'wbtm_seat_private') {
				$('#mtpa_car_type').show();
				wbtmPriceType('private');
				$('#wbtm_same_bus_return').hide();
			} else {
				$('#mtsa_city_zone').show();
				wbtmPriceType('subscription');
				wbtmSubscriptionRouteType();
				$('#wbtm_same_bus_return').hide();
			}
		}
	}
	// Same Bus Return condition
	function wbtmPriceType(priceType) {
		let routeTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_routing"]');
		let pickuppointTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_pickuppoint"]');
		let extraService = $('#wbtm_extra_service');
		if (priceType === 'subscription') { // Subscription
			$('#wbtm_general_price').hide();
			$('#wbtm_private_price').hide();
			$('#wbtm_subs_price').show();
			extraService.hide();
		} else if (priceType === 'private') { // Private
			$('#wbtm_subs_price').hide();
			$('#wbtm_general_price').hide();
			$('#wbtm_private_price').show();
			routeTab.hide();
			extraService.hide();
		} else { // General
			$('#wbtm_subs_price').hide();
			$('#wbtm_private_price').hide();
			$('#wbtm_general_price').show();
			routeTab.show();
			pickuppointTab.show();
			//extraService.show();
		}
	}
	function wbtmSubscriptionRouteType() {
		let $this = $('input[name="wbtm_subcsription_route_type"]:checked');
		let targetTable = $('#wbtm_subs_price #mtsa-repeatable-fieldset-ticket-type');
		let routeTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_routing"]');
		let pickuppointTab = $('.mp_event_tab_area').find('li[data-target-tabs="#wbtm_pickuppoint"]');
		let type = $this.val();
		routeTab.hide();
		pickuppointTab.hide();
		if (type == 'wbtm_boarding_dropping') {
			targetTable.find('.wbtm_city_zone').hide();
			targetTable.find('.' + type).show();
		} else {
			targetTable.find('.wbtm_boarding_dropping').hide();
			targetTable.find('.' + type).show();
		}
	}
})(jQuery);
jQuery(document).ready(function(){
	jQuery('.bus-stops-wrapper input.text').timepicker({
		timeFormat: 'H:mm',
		interval: 15,
		minTime: '00:00',
		maxTime: '23:59',
		dynamic: true,
		dropdown: true,
		scrollbar: true
	});

	jQuery('.pickpoint-adding input[type="text"]').timepicker({
		timeFormat: 'H:mm',
		interval: 15,
		minTime: '00:00',
		maxTime: '23:59',
		dynamic: true,
		dropdown: true,
		scrollbar: true
	});
});
//============Welcome========================//
jQuery(document).ready(function () {
	jQuery('.wbtm_welcome_wrap ul.tabs li').click(function () {
		var tab_id = jQuery(this).attr('data-tab');
		jQuery('.wbtm_welcome_wrap ul.tabs li').removeClass('current');
		jQuery('.wbtm_welcome_wrap .tab-content').removeClass('current');
		jQuery(this).addClass('current');
		jQuery("#" + tab_id).addClass('current');
	});
	jQuery('.wbtm_welcome_wrap ul.accordion .toggle').click(function (e) {
		e.preventDefault();
		var $this = jQuery(this);
		if ($this.next().hasClass('show')) {
			$this.next().removeClass('show');
			$this.removeClass('active');
			$this.next().slideUp(350);
		} else {
			$this.parent().parent().find('li .inner').removeClass('show');
			$this.parent().parent().find('li a').removeClass('active');
			$this.parent().parent().find('li .inner').slideUp(350);
			$this.next().toggleClass('show');
			$this.toggleClass('active');
			$this.next().slideToggle(350);
		}
	});
});
//==========Modal / Popup==========//
(function ($) {
	"use strict";
	/*add bus stop*/
	$(".submit-bus-stop").click(function (e) {
		e.preventDefault();
		let $this = $(this);
		let target = $this.closest('.mpPopup').find('.bus-stop-form');
		let name = $("#bus_stop_name").val().trim();
		$(".success_text").slideUp('fast');
		if (!name) {
			$(".name_required").show();
		} else {
			let description = $("#bus_stop_description").val().trim();
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				dataType: 'JSON',
				data: {
					"action": "wbtm_add_bus_stope",
					"name": name,
					"description": description,
				},
				beforeSend: function () {
					dLoader(target);
				},
				success: function (data) {
					if (data.text == 'error') {
						$(".name_required").hide();
						$("#bus_stop_name").val("");
						$("#bus_stop_description").val("");
						$(".duplicate_text").slideDown('fast');
						setTimeout(function () {
							$('.duplicate_text').fadeOut('fast');
						}, 3000); // <-- time in milliseconds
						dLoaderRemove(target);
					} else {
						$('.bus_stop_add_option').append($('<option>', {
							value: data.text,
							text: data.text,
							'data-term_id': data.term_id
						}));
						$(".name_required").hide();
						$("#bus_stop_name").val("");
						$("#bus_stop_description").val("");
						$(".success_text").slideDown('fast');
						setTimeout(function () {
							$('.success_text').fadeOut('fast');
						}, 1000); // <-- time in milliseconds
						dLoaderRemove(target);
						if ($this.hasClass('close_popup')) {
							$this.delay(2000).closest('.popupMainArea').find('.popupClose').trigger('click');
						}
					}
				}
			});
		}
	});
	/*add pickup point*/
	$(".submit-pickup").click(function (e) {
		e.preventDefault();
		let $this = $(this);
		let target = $this.closest('.mpPopup').find('.pickup-form');
		let name = $("#pickup_name").val().trim();
		$(".success_text").slideUp('fast');
		if (!name) {
			$(".name_required").show();
		} else {
			let description = $("#pickup_description").val().trim();
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				dataType: 'JSON',
				data: {
					"action": "wbtm_add_pickup",
					"name": name,
					"description": description,
				},
				beforeSend: function () {
					dLoader(target);
				},
				success: function (data) {
					if (data.text == 'error') {
						$(".name_required").hide();
						$("#pickup_name").val("");
						$("#pickup_description").val("");
						$(".duplicate_text").slideDown('fast');
						setTimeout(function () {
							$('.duplicate_text').fadeOut('fast');
						}, 1000); // <-- time in milliseconds
						dLoaderRemove(target);
					} else {
						$('.pickup_add_option').append($('<option>', {
							value: data.text,
							text: data.text,
							'data-term_id': data.term_id
						}));
						$(".name_required").hide();
						$("#pickup_name").val("");
						$("#pickup_description").val("");
						$(".success_text").slideDown('fast');
						setTimeout(function () {
							$('.success_text').fadeOut('fast');
						}, 1000); // <-- time in milliseconds
						dLoaderRemove(target);
						if ($this.hasClass('close_popup')) {
							$this.delay(2000).closest('.popupMainArea').find('.popupClose').trigger('click');
						}
					}
				}
			});
		}
	});
	$("#upper-desk-control").click(function () {
		$("#upper-desk").slideToggle("slow");
	});
	$("#pickup-point-control").click(function () {
		$("#pickup-point").slideToggle("slow");
	});
	$("#operational-on-day-control").click(function () {
		$(".operational-on-day").slideToggle("slow");
	});
	$("#off-day-control").click(function () {
		$(".off-day").slideToggle("slow");
	});
	$("#return-operational-on-day-control").click(function () {
		$(".return-operational-on-day").slideToggle("slow");
	});
	$("#return-off-day-control").click(function () {
		$(".return-off-day").slideToggle("slow");
	});
	$("#extra-service-control").click(function () {
		$(".extra-service").slideToggle("slow");
	});
	$(".add-more-bd-point").click(function (e) {
		e.preventDefault();
		$(this).siblings().children('.bd-point, .bd-point-return').append('<tr>' + $(this).siblings().children().children(".more-bd-point").html() + '</tr>');
		$(this).parent().find('input.text').timepicker({
			timeFormat: 'H:mm',
			interval: 15,
			minTime: '00:00',
			maxTime: '23:59',
			dynamic: true,
			dropdown: true,
			scrollbar: true
		});
	});
	$(document).on('click', '.remove-bp-row', function (e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		return false;
	});
	$(document).on('click', '.open-routing-tab', function (e) {
		e.preventDefault();
		//$(this).removeClass();
		$(".wbtm_routing_tab").click();
		return false;
	});
	$(document).on('click', '.wbtm_pickuppoint_tab', function (e) {
		e.preventDefault();
		$('.wbtm_pick_boarding').html("<option value=''>Select Boarding Point</option>");
		$('.wbtm_pick_boarding_return').html("<option value=''>Select Boarding Point</option>");
		let options = '';
		$(".boarding-point tr").each(function (index) {
			// console.log( index + ": " + $(this).find(":selected").val() );
			options = options + $(this).find(":selected").val();
			if (options) {
				$('.boarding_points').show();
				$('.open-routing-tab').hide();
			} else {
				$('.open-routing-tab').show();
				$('.boarding_points').hide();
			}
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				// remove city that has exit on db
				let has_city_in_db = false;
				const selected_city_id = $('.wbtm_pick_boarding').attr('data-selected-city-id');
				if (selected_city_id) {
					const selected_city_id_arr = selected_city_id.split(',');
					has_city_in_db = selected_city_id_arr.includes(String(term_id))
				}
				if (has_city_in_db) {
					return true;
				}
				// END
				$('.wbtm_pick_boarding').append("<option value=" + term_id + ">" + $(this).find(":selected").val() + "</option>")
			}
		});
		$(".boarding-point-return tr").each(function (index) {
			// console.log( index + ": " + $(this).find(":selected").val() );
			options = options + $(this).find(":selected").val();
			if (options) {
				$('.boarding_points').show();
				$('.open-routing-tab').hide();
			} else {
				$('.open-routing-tab').show();
				$('.boarding_points').hide();
			}
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				// remove city that has exit on db
				let has_city_in_db = false;
				const selected_city_id = $('.wbtm_pick_boarding_return').attr('data-selected-city-id');
				if (selected_city_id) {
					const selected_city_id_arr = selected_city_id.split(',');
					has_city_in_db = selected_city_id_arr.includes(String(term_id))
				}
				if (has_city_in_db) {
					return true;
				}
				// END
				$('.wbtm_pick_boarding_return').append("<option value=" + term_id + ">" + $(this).find(":selected").val() + "</option>")
			}
		});
		return false;
	});
	/*seat pricing start*/
	$(document).on('change', '.wbtm_bus_stops_route', function (e) {
		e.preventDefault();
		var new_bus = $('#price_bus_record').val();
		var return_class = $('#return_class').val();
		if (new_bus == '') {
			var route_row = '';
			var i = 0;
			$(".boarding-point tr").each(function (index) {
				var j = 0;
				let term_id = $(this).find(':selected').data('term_id');
				if (term_id) {
					var boarding_point = $(this).find(":selected").val();
					$(".dropping-point tr").each(function (index) {
						if (i <= j) {
							let term_id = $(this).find(':selected').data('term_id');
							if (term_id) {
								var dropping_point = $(this).find(":selected").val();
								route_row += '<tr class="temprary-record-price"><td>' + boarding_point + '</td><td>' + dropping_point + '</td><td class="wbtm-wid-15">\n' +
									'    <input type="hidden" name="wbtm_bus_bp_price_stop[]" value="' + boarding_point + '" class="text">\n' +
									'    <input type="hidden" name="wbtm_bus_dp_price_stop[]" value="' + dropping_point + '" class="text">\n' +
									'    <input type="text" class="widefat" name="wbtm_bus_price[]" placeholder="1500" value="">\n' +
									'    <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_price_return[]" placeholder="Adult Return Price" value="">\n' +
									'</td> <td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_child_price[]" placeholder="1200" value="">\n' +
									'        <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_child_price_return[]" placeholder="Child return price" value="">\n' +
									'    </td><td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_infant_price[]" placeholder="1000" value="">\n' +
									'        <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_infant_price_return[]" placeholder="Infant return price" value="">\n' +
									'    </td><td>\n' +
									'                        <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>\n' +
									'                    </td></tr>';
							}
						}
						j++;
					});
				}
				i++
			});
			$('.temprary-record-price').remove();
			$('.auto-generated').append(route_row);
			var route_row_return = '';
			var i = 0;
			$(".boarding-point-return tr").each(function (index) {
				var j = 0;
				let term_id = $(this).find(':selected').data('term_id');
				if (term_id) {
					var boarding_point = $(this).find(":selected").val();
					$(".dropping-point-return tr").each(function (index) {
						if (i <= j) {
							let term_id = $(this).find(':selected').data('term_id');
							if (term_id) {
								var dropping_point = $(this).find(":selected").val();
								route_row_return += '<tr class="temprary-record-price-return"><td>' + boarding_point + '</td><td>' + dropping_point + '</td><td class="wbtm-wid-15">\n' +
									'    <input type="hidden" name="wbtm_bus_bp_price_stop_return[]" value="' + boarding_point + '" class="text">\n' +
									'    <input type="hidden" name="wbtm_bus_dp_price_stop_return[]" value="' + dropping_point + '" class="text">\n' +
									'    <input type="text" class="widefat" name="wbtm_bus_price_r[]" placeholder="1500" value="">\n' +
									'    <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_price_return_discount[]" placeholder="Adult Return Price" value="">\n' +
									'</td> <td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_child_price_r[]" placeholder="1200" value="">\n' +
									'        <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_child_price_return_discount[]" placeholder="Child return price" value="">\n' +
									'    </td><td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_infant_price_r[]" placeholder="1000" value="">\n' +
									'        <input type="text" class="widefat ' + return_class + '" name="wbtm_bus_infant_price_return_discount[]" placeholder="Infant return price" value="">\n' +
									'    </td><td>\n' +
									'                        <button class="button remove-price-row"><span class="dashicons dashicons-trash"></span></button>\n' +
									'                    </td></tr>';
							}
						}
						j++;
					});
				}
				i++
			});
			$('.temprary-record-price-return').remove();
			$('.auto-generated-return').append(route_row_return);
		}
		$('.ra_bus_bp_price_stop').html("<option value=''>Select boarding point</option>");
		$(".boarding-point tr").each(function (index) {
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				$('.ra_bus_bp_price_stop').append("<option value='" + $(this).find(":selected").val() + "'>" + $(this).find(":selected").val() + "</option>")
			}
		});
		$('.ra_bus_dp_price_stop').html("<option value=''>Select Dropping Point</option>");
		$(".dropping-point tr").each(function (index) {
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				$('.ra_bus_dp_price_stop').append("<option value='" + $(this).find(":selected").val() + "'>" + $(this).find(":selected").val() + "</option>")
			}
		});
		return false;
	});
	$(document).on('change', '.ra_bus_bp_price_stop', function (e) {
		e.preventDefault();
		$(this).even().removeClass("ra_bus_bp_price_stop");
	});
	$(document).on('change', '.ra_bus_dp_price_stop', function (e) {
		e.preventDefault();
		$(this).even().removeClass("ra_bus_dp_price_stop");
	});
	$('.wbtm-tb-repeat-btn').on('click', function (e) {
		e.preventDefault();
		let tableFor = $(this).siblings('.repeatable-fieldset');
		let row = tableFor.find('.mtsa-empty-row-t').clone(true);
		row.removeClass('mtsa-empty-row-t');
		row.insertAfter(tableFor.find('tbody>tr:last'));
	});
	$(document).on('click', '.remove-price-row', function (e) {
		e.preventDefault();
		$(this).parents('tr').remove();
		return false;
	});
	/*seat pricing end*/
	$(document).on('click', '.ra_seat_price', function (e) {
		e.preventDefault();
		$('.ra_bus_bp_price_stop').html("<option value=''>Select Boarding Point</option>");
		$(".boarding-point tr").each(function (index) {
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				$('.ra_bus_bp_price_stop').append("<option value=" + $(this).find(":selected").val() + ">" + $(this).find(":selected").val() + "</option>")
			}
		});
		$('.ra_bus_dp_price_stop').html("<option value=''>Select Dropping Point</option>");
		$(".dropping-point tr").each(function (index) {
			let term_id = $(this).find(':selected').data('term_id');
			if (term_id) {
				$('.ra_bus_dp_price_stop').append("<option value=" + $(this).find(":selected").val() + ">" + $(this).find(":selected").val() + "</option>")
			}
		});
		return false;
	});
}(jQuery));