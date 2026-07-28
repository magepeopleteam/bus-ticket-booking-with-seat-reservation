//==================================================Search area==================//
(function ($) {
	"use strict";

	let wbtm_bus_start_end = '';
	function wbtm_set_search_button_state(parent, is_loading) {
		parent.find('.wbtm_search_action_button:visible').each(function () {
			let button = $(this);
			let default_html = button.data('default-html');
			let loading_text = button.data('loading-text') || (typeof wbtm_strings !== 'undefined' ? wbtm_strings.searching : 'Searching...');

			if (!default_html) {
				default_html = button.html();
				button.data('default-html', default_html);
			}

			if (is_loading) {
				button
					.addClass('wbtm_is_loading')
					.prop('disabled', true)
					.html('<span class="fas fa-spinner fa-spin" aria-hidden="true"></span><span class="wbtm_search_button_text">' + loading_text + '</span>');
			} else {
				button
					.removeClass('wbtm_is_loading')
					.prop('disabled', false)
					.html(default_html);
			}
		});
	}

	$(document).on("submit", "#wbtm_area form.mpForm", function () {
		let parent = $(this).closest('#wbtm_area');
		wbtm_set_search_button_state(parent, true);
	});

	$(document).on("click", "#wbtm_area button.get_wbtm_bus_list", function (e) {
		e.preventDefault();
		let parent = $(this).closest('#wbtm_area');
		if ($(this).hasClass('wbtm_is_loading')) {
			return false;
		}
		let start = parent.find('input[name="bus_start_route"]');
		let end = parent.find('input[name="bus_end_route"]');
		let j_date = parent.find('input[name="j_date"]');
		let style = parent.find('input[name="wbtm_list_style"]');
		let btn_show = parent.find('input[name="wbtm_list_btn_show"]');
		let left_filter_input = parent.find('input[name="wbtm_left_filter_show"]');
		let wbtm_left_filter_type = parent.find('input[name="wbtm_left_filter_type"]');
		let wbtm_left_filter_operator = parent.find('input[name="wbtm_left_filter_operator"]');
		let wbtm_left_filter_boarding = parent.find('input[name="wbtm_left_filter_boarding"]');


		let left_filter_show = {
			left_filter_input: left_filter_input.val(),
			left_filter_type: wbtm_left_filter_type.val(),
			left_filter_operator: wbtm_left_filter_operator.val(),
			left_filter_boarding: wbtm_left_filter_boarding.val(),
		}

		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		if (!wbtm_check_required(start)) {
			wbtm_set_search_button_state(parent, false);
			start.trigger('click');
			return false;
		}
		if (!wbtm_check_required(end)) {
			wbtm_set_search_button_state(parent, false);
			end.trigger('click');
			return false;
		}
		if (!wbtm_check_required(j_date)) {
			wbtm_set_search_button_state(parent, false);
			j_date.siblings('input').focus();
			return false;
		} else {
			let r_date = parent.find('input[name="r_date"]');
			let post_id = parent.find('[name="wbtm_post_id"]').val();
			$.ajax({
				type: "POST",
				url: wbtm_ajax_url,
				data: {
					action: "get_wbtm_bus_list",
					nonce: wbtm_nonce,
					start_route: start.val(),
					end_route: end.val(),
					j_date: j_date.val(),
					r_date: r_date.val(),
					post_id: post_id,
					style: style.val(),
					btn_show: btn_show.val(),
					wbtm_bus_start_end_id: wbtm_bus_start_end,
					left_filter_show: JSON.stringify(left_filter_show),
					// backend_order: window.location.href.search("wbtm_backend_order"),
				},
				beforeSend: function () {
					wbtm_set_search_button_state(parent, true);
					wbtm_loader(parent.find(".wbtm_search_result"));
				},
				success: function (data) {
					parent
						.find(".wbtm_search_result")
						.html(data)
						.promise()
						.done(function () {
							wbtm_loaderRemove(parent.find(".wbtm_search_area"));
							wbtm_set_search_button_state(parent, false);
							wbtm_loadBgImage();
						});
				},
				error: function (response) {
					wbtm_set_search_button_state(parent, false);
					wbtm_loaderRemove(parent.find(".wbtm_search_area"));
					wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_bus_list : 'Could not load buses. Please try again.');
					console.log(response);
				},
			});
		}
	});
	$(document).on("click", "#wbtm_area button.wbtm_next_date", function () {
		wbtm_bus_start_end = $(this).closest('.wbtm-bus-lists').attr('id');
		let date = $(this).data('date');
		let parent = $(this).closest('#wbtm_area');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		// $('body').find('#wbtm_selected_bus_notification').slideUp('fast');
		let name = $(this).closest('#wbtm_return_container').length > 0 ? 'r_date' : 'j_date';
		parent.find('input[name=' + name + ']').val(date).promise().done(function () {
			parent.find('.get_wbtm_bus_list').trigger('click');
		});

		// $("#wbtm_date_return_route_start").fadeOut();
		$("#wbtm_date_return_route_return").fadeIn();

	});
	$(document).on("mp_change", "div.wbtm_search_area .wbtm_start_point input.formControl", function () {
		let current = $(this);
		let start_route = current.val();
		let parent = current.closest('.wbtm_search_area');
		let target = parent.find('.wbtm_dropping_point');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		parent.find('.wbtm_dropping_point .wbtm_input_select_list').remove();
		target.find('input.formControl').val('');
		wbtm_loader_xs(target.find('.marker'));
		let exit_route = 0;
		parent.find('.wbtm_start_point .wbtm_input_select_list li').each(function () {
			let current_route = $(this).data('value');
			if (current_route === start_route) {
				exit_route = 1;
			}
		}).promise().done(function () {
			if (exit_route > 0) {
				let post_id = parent.find('[name="wbtm_post_id"]').val();
				$.ajax({
					type: 'POST',
					url: wbtm_ajax_url,
					data: {
						"action": "get_wbtm_dropping_point",
						"start_route": start_route,
						"post_id": post_id,
						"nonce": wbtm_nonce,
					},
					success: function (data) {
						// Data loads in the background as before (still needed to
						// populate valid destinations for this start route) — just no
						// longer force-opens the Drop-Off Point field afterwards, so
						// selecting "From" doesn't yank focus away before the user is
						// ready to pick "To" themselves.
						target.append(data).promise().done(function () {
							wbtm_loaderRemove(parent);
						});
					},
					error: function (response) {
						wbtm_loaderRemove(parent);
						wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_dropping_point : 'Could not load destinations. Please try again.');
						console.log(response);
					}
				});
			} else {
				wbtm_loaderRemove(parent);
				wbtm_alert(target);
				current.val('').trigger('click');
			}
		});
	});
	$(document).on("mp_change", "div.wbtm_search_area .wbtm_dropping_point input.formControl", function () {
		let current = $(this);
		let end_route = current.val();
		let parent = current.closest('.wbtm_search_area');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		let exit_route = 0;
		parent.find('.wbtm_dropping_point .wbtm_input_select_list li').each(function () {
			let current_route = $(this).data('value');
			if (current_route === end_route) {
				exit_route = 1;
			}
		}).promise().done(function () {
			wbtm_load_journey_date(parent);
		}).promise().done(function () {
			// Valid dates still load in the background above (still needed so the
			// Journey Date calendar only shows bookable dates) — just no longer
			// force-focuses/opens that picker afterwards, same reasoning as the
			// Drop-Off Point auto-open removed above.
			if (exit_route === 0) {
				current.val('').trigger('click');
			}
		});
	});
	function wbtm_load_journey_date(parent) {
		let post_id = parent.find('[name="wbtm_post_id"]').val();
		let start_route = parent.find('[name="bus_start_route"]').val();
		let end_route = parent.find('[name="bus_end_route"]').val();
		let target = parent.find('.wbtm_journey_date');
		$.ajax({
			type: 'POST',
			url: wbtm_ajax_url,
			data: {
				"action": "get_wbtm_journey_date",
				"start_route": start_route,
				"end_route": end_route,
				"post_id": post_id,
				"nonce": wbtm_nonce,
			},
			beforeSend: function () {
				wbtm_loader_xs(target.find('.calendar'));
			},
			success: function (data) {
				target.html(data);
				wbtm_loaderRemove(target);
			},
			error: function (response) {
				wbtm_loaderRemove(target);
				wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_journey_date : 'Could not load journey dates. Please try again.');
				console.log(response);
			}
		});
	}
	$(document).on("change", "#wbtm_area input[name='j_date']", function () {
		let date = $(this).val();
		let parent = $(this).closest('#wbtm_area');
		let target = parent.find('.wbtm_return_date');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		if (target.length > 0 && date) {
			let start_route = parent.find('[name="bus_start_route"]').val();
			let end_route = parent.find('input[name="bus_end_route"]').val();
			let post_id = parent.find('[name="wbtm_post_id"]').val();
			$.ajax({
				type: 'POST',
				url: wbtm_ajax_url,
				data: {
					"action": "get_wbtm_return_date",
					"start_route": start_route,
					"end_route": end_route,
					"j_date": date,
					"post_id": post_id,
					"nonce": wbtm_nonce,
				},
				beforeSend: function () {
					wbtm_loader_xs(target.find('.calendar'));
				},
				success: function (data) {
					target.html(data);
					wbtm_loaderRemove(target);
				},
				error: function (response) {
					wbtm_loaderRemove(target);
					wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_return_date : 'Could not load return dates. Please try again.');
					console.log(response);
				}
			});
		}
	});
	$(document).on("click", "#wbtm_area #wbtm_journey_date", function () {
		let parent = $(this).closest('#wbtm_area');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		let start = parent.find('input[name="bus_start_route"]').val();
		if (!start) {
			wbtm_alert($(this));
		}
	});
}(jQuery));
//====================================================================//
(function ($) {
	"use strict";
	$(document).on("click", "#get_wbtm_bus_details", function () {
		let parent = $(this).closest(".wbtm_bus_list_area");
		let currentButton = $(this);
		let post_id = $(this).attr("data-bus_id");
		let target = parent.find("[data-row_id=" + post_id + "]");
		$("body").find(".woocommerce-notices-wrapper").slideUp("fast");
		if ($(this).hasClass("mActive")) {
			target.find(">div").slideUp("fast");
			wbtm_all_content_change($(this));
		} else {
			parent.find("#get_wbtm_bus_details.mActive").each(function () {
				$(this).trigger("click");
			});
			let start = parent.find('input[name="wbtm_start_route"]').val();
			let end = parent.find('input[name="wbtm_end_route"]').val();
			let date = parent.find('input[name="wbtm_date"]').val();
			let j_date = parent.find('input[name="j_date"]').val();
			let r_date = parent.find('input[name="r_date"]').val();
			if (start && end && date && post_id) {
				$.ajax({
					type: "POST",
					url: wbtm_ajax_url,
					data: {
						action: "get_wbtm_bus_details",
						start_route: start,
						end_route: end,
						post_id: post_id,
						nonce: wbtm_nonce,
						date: date,
						j_date: j_date,
						r_date: r_date,
						// Fixed by Shahnur - 2026-04-23 02:50 PM (Asia/Dhaka)
						// Each bus row can resolve to a different fare leg, so read it from the clicked result.
						wbtm_price_leg:
							currentButton.attr("data-price-leg") ||
							parent.find('input[name="wbtm_price_leg"]').val() ||
							"outbound",
						backend_order: window.location.href.search("wbtm_backend_order"),
					},
					beforeSend: function () {
						wbtm_loader(parent);
					},
					success: function (data) {
						target.html(data);
						wbtm_loaderRemove(parent);
						wbtm_loadBgImage();

						// // Auto-select first available seat or set quantity to 1
						// setTimeout(function() {
						//   // Check if it's seat plan layout
						//   if (target.find('.wbtm_seat_plan_area').length > 0) {
						//     // Auto-click first available seat in seat plan
						//     var firstAvailableSeat = target.find('.seat_available').first();
						//     if (firstAvailableSeat.length > 0) {
						//       firstAvailableSeat.trigger('click');
						//     }
						//   } else {
						//     // For without seat plan, set first quantity input to 1
						//     var firstQtyInput = target.find('input[name="wbtm_seat_qty[]"]').first();
						//     if (firstQtyInput.length > 0 && firstQtyInput.val() == 0) {
						//       firstQtyInput.val(1).trigger('change');
						//     }
						//   }
						// }, 100);
					},
					error: function (response) {
						wbtm_loaderRemove(parent);
						wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_seat_plan : 'Could not load the seat plan. Please try again.');
						console.log(response);
					},
				});
			}
			wbtm_all_content_change($(this));
		}
	});
})(jQuery);
//====================================================================//
(function ($) {
	"use strict";
	function wbtm_set_loading_button_state(button, is_loading) {
		let default_html = button.data('default-html');
		let loading_text = button.data('loading-text') || (typeof wbtm_strings !== 'undefined' ? wbtm_strings.loading : 'Loading...');

		if (!default_html) {
			default_html = button.html();
			button.data('default-html', default_html);
		}

		if (is_loading) {
			button
				.addClass('wbtm_is_loading')
				.prop('disabled', true)
				.html('<span class="fas fa-spinner fa-spin" aria-hidden="true"></span><span class="wbtm_loading_button_text">' + loading_text + '</span>');
		} else {
			button
				.removeClass('wbtm_is_loading')
				.prop('disabled', false)
				.html(default_html);
		}
	}

	function wbtm_price_calculation(parent) {
		let total_qty = wbtm_seat_qty(parent);
		wbtm_seat_calculation(parent, total_qty);
		wbtm_attendee_management(parent, total_qty);
		let target_summary = parent.find('.wbtm_total');
		let target_sub_total = parent.find('.wbtm_sub_total');
		let total = wbtm_ticket_price(parent);
		target_sub_total.html(wbtm_price_format(total));
		if (total_qty > 0) {
			parent.find('.wbtm_ex_service_area').slideDown('fast');
			parent.find('.wbtm_form_submit_area').slideDown('fast');
			total = total + wbtm_ex_service_price(parent);
			target_summary.html(wbtm_price_format(total));
		} else {
			parent.find('.wbtm_ex_service_area').slideUp('fast');
			parent.find('.wbtm_form_submit_area').slideUp('fast');
			target_summary.html(wbtm_price_format(total));
		}
		// Added by Shahnur 2026-06-02 — keep the raw numeric total so it can be sent to the
		// server without depending on locale-formatted text (e.g. "39,00 lei" was collapsing to 3900).
		target_summary.attr('data-raw-total', total);
		wbtm_update_summary_preview(parent, total_qty);
	}
	// Read-only "Booking Summary" preview card (see templates/layout/
	// booking_summary_preview.php) — mirrors the rows .wbtm_selected_seat_details
	// already built above and the .wbtm_sub_total text this same function just
	// set, rather than recomputing anything itself, so it can never drift out of
	// sync with the real totals. Its own Book Now button (below) just re-clicks
	// the real #wbtm_add_to_cart button instead of duplicating the add-to-cart logic.
	// Loaded and visible before any seat is picked (not hidden/shown like
	// .wbtm_ex_service_area / .wbtm_form_submit_area above) — only its Book Now
	// button toggles disabled/enabled based on whether a seat is selected yet.
	function wbtm_update_summary_preview(parent, total_qty) {
		let preview = parent.find('.wbtm_booking_summary_preview');
		if (!preview.length) {
			return;
		}
		let preview_rows = preview.find('.wbtm_summary_preview_rows');
		let book_now_btn = preview.find('.wbtm_summary_preview_book_now');
		if (preview_rows.data('wbtm-empty-html') === undefined) {
			// Capture the PHP-rendered "No seat selected yet" placeholder row once,
			// before it's ever replaced, so it can be restored later without
			// duplicating its translated text here in JS.
			preview_rows.data('wbtm-empty-html', preview_rows.html());
		}
		if (total_qty > 0) {
			preview_rows.html('');
			parent.find('.wbtm_selected_seat_details .wbtm_item_insert .wbtm_remove_area').each(function () {
				let row = $(this);
				let clone = $('<tr></tr>');
				clone.append($('<td></td>').html(row.find('.insert_seat_label').html()));
				clone.append($('<td></td>').html(row.find('.insert_seat_name').html()));
				clone.append($('<td></td>').html(row.find('.insert_seat_price').html()));
				preview_rows.append(clone);
			});
			preview.find('.wbtm_summary_preview_subtotal').html(parent.find('.wbtm_sub_total').html());
			preview.find('.wbtm_summary_preview_total').html(parent.find('.wbtm_total').html());
			book_now_btn.prop('disabled', false);
		} else {
			preview_rows.html(preview_rows.data('wbtm-empty-html'));
			preview.find('.wbtm_summary_preview_subtotal').html(parent.find('.wbtm_sub_total').html());
			preview.find('.wbtm_summary_preview_total').html(parent.find('.wbtm_total').html());
			book_now_btn.prop('disabled', true);
		}
	}
	$(document).on('click', '.wbtm_registration_area .wbtm_summary_preview_book_now', function () {
		let book_now_btn = $(this);
		if (book_now_btn.prop('disabled') || book_now_btn.hasClass('wbtm_is_loading')) {
			return;
		}
		wbtm_set_loading_button_state(book_now_btn, true);
		book_now_btn.closest('.wbtm_registration_area').find('#wbtm_add_to_cart').trigger('click');
	});
	function wbtm_ticket_price(parent) {
		let total = 0;
		if (parent.find('.wbtm_seat_plan_area').length > 0) {
			parent.find('.seat_available.seat_selected').each(function () {
				total = total + parseFloat($(this).attr('data-seat_price'));
			});
		} else {
			parent.find('[name="wbtm_seat_qty[]"]').each(function () {
				let qty = parseInt($(this).val());
				let price = parseFloat($(this).attr('data-price'));
				price = price && price >= 0 ? price : 0;
				total = total + price * qty;
			});
		}
		return total;
	}
	function wbtm_seat_qty(parent) {
		let total_qty = 0;
		if (parent.find('.wbtm_seat_plan_area').length > 0) {
			parent.find('.seat_available.seat_selected').each(function () {
				total_qty++;
			});
		} else {
			parent.find('[name="wbtm_seat_qty[]"]').each(function () {
				total_qty = total_qty + parseInt($(this).val());
			});
		}
		return total_qty;
	}
	function wbtm_ex_service_price(parent) {
		let total = 0
		parent.find('[name="extra_service_qty[]"]').each(function () {
			let ex_qty = parseInt($(this).val());
			let ex_price = $(this).attr('data-price');
			ex_price = ex_price && ex_price >= 0 ? ex_price : 0;
			total = total + parseFloat(ex_price) * ex_qty;
		});
		return total;
	}
	$(document).on('change', '.wbtm_registration_area [name="wbtm_seat_qty[]"]', function () {
		let parent = $(this).closest('.wbtm_registration_area');
		wbtm_price_calculation(parent);
	});
	$(document).on('change', '.wbtm_registration_area [name="extra_service_qty[]"]', function () {
		let parent = $(this).closest('.wbtm_registration_area');
		wbtm_price_calculation(parent);
	});
	$(document).on('click', '.wbtm_registration_area .seat_available', function () {
		let current = $(this);
		let parent = current.closest('.wbtm_registration_area');

		// Allow multiple seat selection within the same cabin/coach
		// No restrictions needed - users can select multiple seats per cabin

		if (current.hasClass('seat_selected')) {
			let target = current.closest('.mp_seat_item').find('.wbtm_seat_item_list li:first-child');
			if (target.length > 0) {
				let seat_label = target.attr('data-seat_label');
				let seat_price = target.attr('data-seat_price');
				let seat_type = target.attr('data-seat_type');
				current.attr('data-seat_label', seat_label).attr('data-seat_price', seat_price).attr('data-seat_type', seat_type);
			}
		}
		current.toggleClass('seat_selected').promise().done(function () {
			wbtm_price_calculation(parent);
		});
	});
	$(document).on('click', '.wbtm_registration_area .wbtm_seat_item_list li', function () {
		let current = $(this);
		let target = current.closest('.mp_seat_item').find('.seat_available');
		let seat_label = current.attr('data-seat_label');
		let seat_price = current.attr('data-seat_price');
		let seat_type = current.attr('data-seat_type');
		let parent = current.closest('.wbtm_registration_area');
		target.attr('data-seat_label', seat_label).attr('data-seat_price', seat_price).attr('data-seat_type', seat_type).promise().done(function () {
			if (target.hasClass('seat_selected')) {
				wbtm_price_calculation(parent);
			} else {
				target.trigger('click');
			}
		});
	});
	$(document).on('click', '.wbtm_registration_area .wbtm_selected_seat_details .wbtm_item_remove', function () {
		let current = $(this);
		let current_tr = current.closest('tr');
		let seat_name = current_tr.attr('data-seat_name');
		let seat_type = current_tr.attr('data-seat_type');
		let cabin_index = current_tr.attr('data-cabin_index');
		let parent = current.closest('.wbtm_registration_area');

		parent.find('.seat_available.seat_selected').each(function () {
			let seat_name_match = $(this).attr('data-seat_name') === seat_name;
			let seat_type_match = $(this).attr('data-seat_type') === seat_type;
			let cabin_index_match = true;

			// For cabin seats, also check cabin index
			if (cabin_index) {
				cabin_index_match = $(this).attr('data-cabin_index') === cabin_index;
			} else {
				cabin_index_match = !$(this).attr('data-cabin_index');
			}

			if (seat_name_match && seat_type_match && cabin_index_match) {
				$(this).trigger('click');
				return false;
			}
		});
	});
	function wbtm_seat_calculation(parent, total_qty) {
		if (parent.find('.wbtm_seat_plan_area').length > 0) {
			// Handle legacy seat plan (single bus)
			let upper_area = parent.find('.wbtm_seat_plan_lower');
			if (upper_area.length > 0) {
				let upper_target = parent.find('[name="wbtm_selected_seat"]');
				let upper_target_type = parent.find('[name="wbtm_selected_seat_type"]');
				let seats = '';
				let seats_type = '';
				upper_area.find('.seat_available.seat_selected').each(function () {
					seats = seats ? seats + ',' + $(this).attr('data-seat_name') : $(this).attr('data-seat_name');
					seats_type = seats_type ? seats_type + ',' + $(this).attr('data-seat_type') : $(this).attr('data-seat_type');
				}).promise().done(function () {
					upper_target.val(seats);
					upper_target_type.val(seats_type);
				});
			}

			let lower_area = parent.find('.wbtm_seat_plan_upper');
			if (lower_area.length > 0) {
				let lower_target = parent.find('[name="wbtm_selected_seat_dd"]');
				let lower_target_type = parent.find('[name="wbtm_selected_seat_dd_type"]');
				let seats_dd = '';
				let seats_dd_type = '';
				lower_area.find('.seat_available.seat_selected').each(function () {
					seats_dd = seats_dd ? seats_dd + ',' + $(this).attr('data-seat_name') : $(this).attr('data-seat_name');
					seats_dd_type = seats_dd_type ? seats_dd_type + ',' + $(this).attr('data-seat_type') : $(this).attr('data-seat_type');
				}).promise().done(function () {
					lower_target.val(seats_dd);
					lower_target_type.val(seats_dd_type);
				});
			}

			// Handle multi-cabin seat plans (lower deck + optional upper deck).
			// A cabin can be a double-decker coach: selected seats are collected
			// per deck pane and written to that deck's own hidden inputs so the
			// server can rebuild the deck-scoped "cabin_{i}[_dd]_{seat}" identifier.
			parent.find('.wbtm_cabin_section').each(function () {
				let cabin_section = $(this);
				let cabin_index = cabin_section.find('.wbtm_cabin_seat_plan').attr('data-cabin-index');
				if (cabin_index !== undefined) {
					// Lower deck
					let cabin_target = parent.find('[name="wbtm_selected_seat_cabin_' + cabin_index + '"]');
					let cabin_target_type = parent.find('[name="wbtm_selected_seat_type_cabin_' + cabin_index + '"]');
					let seats = '';
					let seats_type = '';
					cabin_section.find('.wbtm_deck_pane_lower .seat_available.seat_selected').each(function () {
						seats = seats ? seats + ',' + $(this).attr('data-seat_name') : $(this).attr('data-seat_name');
						seats_type = seats_type ? seats_type + ',' + $(this).attr('data-seat_type') : $(this).attr('data-seat_type');
					});
					cabin_target.val(seats);
					cabin_target_type.val(seats_type);

					// Upper deck (only present on double-decker cabins)
					let cabin_target_dd = parent.find('[name="wbtm_selected_seat_cabin_dd_' + cabin_index + '"]');
					if (cabin_target_dd.length) {
						let cabin_target_dd_type = parent.find('[name="wbtm_selected_seat_type_cabin_dd_' + cabin_index + '"]');
						let seats_up = '';
						let seats_up_type = '';
						cabin_section.find('.wbtm_deck_pane_upper .seat_available.seat_selected').each(function () {
							seats_up = seats_up ? seats_up + ',' + $(this).attr('data-seat_name') : $(this).attr('data-seat_name');
							seats_up_type = seats_up_type ? seats_up_type + ',' + $(this).attr('data-seat_type') : $(this).attr('data-seat_type');
						});
						cabin_target_dd.val(seats_up);
						cabin_target_dd_type.val(seats_up_type);
					}
				}
			});

			wbtm_selected_seat_details(parent, total_qty)
		}
	}
	function wbtm_selected_seat_details(parent, total_qty) {
		if (parent.find('.wbtm_seat_plan_area').length > 0) {
			let target = parent.find('.wbtm_selected_seat_details .wbtm_item_insert');
			if (total_qty > 0) {
				let item_length = target.find('.wbtm_remove_area').length;
				//if (item_length !== total_qty) {
				let hidden_target_tr = parent.find('.wbtm_item_hidden .wbtm_remove_area');
				parent.find('.seat_available.seat_selected').each(function () {
					let seat_name = $(this).attr('data-seat_name');
					let seat_type = $(this).attr('data-seat_type');
					let cabin_index = $(this).attr('data-cabin_index');

					// Create unique identifier for cabin seats (seat_name + cabin_index)
					let seat_identifier = cabin_index ? seat_name + '_cabin_' + cabin_index : seat_name;

					// Check if this seat (considering cabin) is already in the summary
					let existing_row;
					if (cabin_index) {
						existing_row = target.find('.wbtm_remove_area[data-seat_name="' + seat_name + '"][data-cabin_index="' + cabin_index + '"]');
					} else {
						existing_row = target.find('.wbtm_remove_area[data-seat_name="' + seat_name + '"]').not('[data-cabin_index]');
					}

					if (existing_row.length > 0) {
						// If type matches, do nothing, otherwise update the row
						if (existing_row.attr('data-seat_type') !== seat_type) {
							let seat_label = $(this).attr('data-seat_label');
							let seat_price = $(this).attr('data-seat_price');

							existing_row.attr('data-seat_type', seat_type);
							existing_row.find('.insert_seat_label').html(seat_label);
							existing_row.find('.insert_seat_price').html(wbtm_price_format(seat_price));
						}

						// Clean up duplicates if any
						if (existing_row.length > 1) {
							existing_row.not(':first').remove();
						}
					} else {
						wbtm_reload_selected_seat($(this), hidden_target_tr, target);
					}
				}).promise().done(function () {
					item_length = target.find('.wbtm_remove_area').length;
					if (item_length !== total_qty) {
						target.find('.wbtm_remove_area').each(function () {
							let seat_name = $(this).attr('data-seat_name');
							let cabin_index = $(this).attr('data-cabin_index');

							// Find matching selected seat considering cabin context
							let matching_selected_seat;
							if (cabin_index) {
								matching_selected_seat = parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"][data-cabin_index="' + cabin_index + '"]');
							} else {
								matching_selected_seat = parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"]').not('[data-cabin_index]');
							}

							if (matching_selected_seat.length === 0) {
								$(this).remove();
							}
						});
					}
				});
				//}
			} else {
				target.html('');
			}
		}
	}
	function wbtm_reload_selected_seat(current, hidden_target_tr, target) {
		let seat_label = current.attr('data-seat_label');
		let seat_price = current.attr('data-seat_price');
		let seat_name = current.attr('data-seat_name');
		let seat_type = current.attr('data-seat_type');
		let cabin_index = current.attr('data-cabin_index');

		// Set cabin index if it exists
		if (cabin_index) {
			hidden_target_tr.attr('data-cabin_index', cabin_index);
		}

		hidden_target_tr.attr('data-seat_type', seat_type).attr('data-seat_name', seat_name).promise().done(function () {
			hidden_target_tr.find('.insert_seat_label').html(seat_label);
			hidden_target_tr.find('.insert_seat_name').html(seat_name);
			hidden_target_tr.find('.insert_seat_price').html(wbtm_price_format(seat_price));
		}).promise().done(function () {
			target.append(hidden_target_tr.clone());
		});
	}
	// Passenger Information fields are visually reordered so the DEFAULT fields
	// come FIRST — Passenger Name, Email, Phone, Date of Birth, Gender, Address
	// (see templates/layout/WBTM_Attendee_form.php's form_item() loop) — followed
	// by the admin-configured custom form fields, which keep their own relative
	// order. This used to be done with CSS `order` + :has() selectors, but
	// interacting with the Date of Birth datepicker (which mutates the DOM —
	// adds a dynamic id/hasDatepicker class, appends/removes the calendar
	// popup) was re-triggering :has() re-evaluation and visibly reshuffling
	// the grid, swapping Passenger Name and Date of Birth on screen. Moving
	// the actual DOM nodes once, right when the panel is inserted, is stable
	// against that — nothing about opening the datepicker touches DOM order
	// afterwards. Safe to call repeatedly (e.g. once per seat) since moving
	// an already-correctly-placed field is a no-op.
	//
	// NOTE: the defaults are moved to the FRONT (prepend), not the end. Using
	// append() here pushed every default field *after* the custom fields, so the
	// panel rendered as [Custom1, Custom2, …, Name, …] — i.e. Passenger Name
	// ended up last. Iterating field_order in reverse and prepending puts the
	// first selector (Passenger Name) first in the DOM, ahead of the untouched
	// custom fields.
	function wbtm_reorder_attendee_fields(form_target) {
		var field_order = [
			'input[name="wbtm_full_name[]"]',
			'input[name="wbtm_reg_email[]"]',
			'input[name="wbtm_reg_phone[]"]',
			'input[name="date_of_birth[]"]',
			'select[name="wbtm_user_gender[]"]',
			'textarea[name="wbtm_reg_address[]"]'
		];
		form_target.find('.wbtm_attendee_item .mpPanelBody').each(function () {
			var panel_body = $(this);
			for (var i = field_order.length - 1; i >= 0; i--) {
				var field = panel_body.find('.mp_form_item').has(field_order[i]);
				if (field.length) {
					panel_body.prepend(field);
				}
			}
		});
	}
	function wbtm_attendee_management(parent, total_qty) {
		let form_target = parent.find('.wbtm_attendee_area');
		if (form_target.length > 0 && total_qty > 0) {
			form_target.slideDown(250);
			let form_length = form_target.find('.wbtm_attendee_item').length;
			if (form_length !== total_qty) {
				let hidden_target = parent.find('.wbtm_hidden_form');
				if (parent.find('.wbtm_seat_plan_area').length > 0) {
					parent.find('.seat_available.seat_selected').each(function () {
						let seat_name = $(this).attr('data-seat_name');
						let cabin_index = $(this).attr('data-cabin_index');

						// Check if this seat (considering cabin) is already in the attendee form
						let seat_already_in_form = false;
						if (cabin_index) {
							seat_already_in_form = form_target.find('[data-seat_name="' + seat_name + '"][data-cabin_index="' + cabin_index + '"]').length > 0;
						} else {
							seat_already_in_form = form_target.find('[data-seat_name="' + seat_name + '"]').not('[data-cabin_index]').length > 0;
						}

						if (!seat_already_in_form) {
							let attendee_item = hidden_target.find('.wbtm_attendee_item');
							attendee_item.attr('data-seat_name', seat_name);
							if (cabin_index) {
								attendee_item.attr('data-cabin_index', cabin_index);
							}
							hidden_target.find('.wbtm_seat_name').html(seat_name).promise().done(function () {
								form_target.append(hidden_target.html());
							}).promise().done(function () {
								wbtm_load_date_picker(parent);
								wbtm_reorder_attendee_fields(form_target);
							});
						}
					}).promise().done(function () {
						form_length = form_target.find('.wbtm_attendee_item').length;
						if (form_length !== total_qty) {
							form_target.find('.wbtm_attendee_item').each(function () {
								let seat_name = $(this).attr('data-seat_name');
								let cabin_index = $(this).attr('data-cabin_index');

								// Find matching selected seat considering cabin context
								let matching_selected_seat;
								if (cabin_index) {
									matching_selected_seat = parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"][data-cabin_index="' + cabin_index + '"]');
								} else {
									matching_selected_seat = parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"]').not('[data-cabin_index]');
								}

								if (matching_selected_seat.length === 0) {
									$(this).remove();
								}
							});
						}
					});
				} else {
					if (form_length > total_qty) {
						for (let i = form_length; i > total_qty; i--) {
							form_target.find('.wbtm_attendee_item:last-child').slideUp(250).remove();
						}
					} else {
						for (let i = form_length; i < total_qty; i++) {
							hidden_target.find('.wbtm_seat_name').html(i + 1).promise().done(function () {
								form_target.append(hidden_target.html());
							}).promise().done(function () {
								wbtm_load_date_picker(parent);
								wbtm_reorder_attendee_fields(form_target);
							});
						}
					}
				}
			}
		} else {
			form_target.html('').slideUp(250);
		}
	}

	// Handle cabin seat plan toggle — accordion: opening one cabin collapses
	// every other cabin within the same seat plan area (outbound and return
	// each get their own independent accordion, since each has its own
	// .wbtm_seat_plan_area — see the "each" loop below).
	$(document).on('click', '.wbtm_cabin_toggle', function () {
		let header = $(this);
		let cabin_section = header.closest('.wbtm_cabin_section');
		let seat_plan_area = cabin_section.closest('.wbtm_seat_plan_area');
		let seat_plan = cabin_section.find('.wbtm_cabin_seat_plan');
		let arrow = header.find('.wbtm_toggle_arrow');
		let isExpanded = seat_plan.attr('aria-expanded') === 'true';

		seat_plan_area.find('.wbtm_cabin_section').not(cabin_section).each(function () {
			let other_section = $(this);
			let other_plan = other_section.find('.wbtm_cabin_seat_plan');
			if (other_plan.attr('aria-expanded') === 'true') {
				other_plan.stop(true, true).slideUp(300).attr('aria-expanded', 'false');
				other_section.removeClass('expanded').addClass('collapsed');
				other_section.find('.wbtm_toggle_arrow').text('▼');
			}
		});

		if (isExpanded) {
			seat_plan.stop(true, true).slideUp(300).attr('aria-expanded', 'false');
			cabin_section.removeClass('expanded').addClass('collapsed');
			arrow.text('▼'); // Show down arrow when collapsed
		} else {
			seat_plan.stop(true, true).slideDown(300).attr('aria-expanded', 'true');
			cabin_section.removeClass('collapsed').addClass('expanded');
			arrow.text('▲'); // Show up arrow when expanded
		}
	});

	// Handle Lower/Upper deck switch inside a double-decker cabin — shows the
	// chosen deck's seat pane and hides the other. Selection state is preserved
	// because both panes stay in the DOM (just hidden), so seats picked on one
	// deck remain selected when switching back.
	$(document).on('click', '.wbtm_cabin_deck_tab', function () {
		let tab = $(this);
		let deck = tab.attr('data-deck');
		let plan = tab.closest('.wbtm_cabin_seat_plan');
		plan.find('.wbtm_cabin_deck_tab').removeClass('wbtm_cabin_deck_tab_active');
		tab.addClass('wbtm_cabin_deck_tab_active');
		plan.find('.wbtm_deck_pane').hide();
		plan.find('.wbtm_deck_pane[data-deck="' + deck + '"]').show();
	});

	// Initialize cabin arrows and classes on page load
	$(document).ready(function () {
		$('.wbtm_cabin_section').each(function () {
			let cabin_section = $(this);
			let seat_plan = cabin_section.find('.wbtm_cabin_seat_plan');
			let arrow = cabin_section.find('.wbtm_toggle_arrow');

			if (seat_plan.attr('aria-expanded') === 'true') {
				cabin_section.addClass('expanded');
				arrow.text('▲'); // Up arrow for expanded
			} else {
				cabin_section.addClass('collapsed');
				arrow.text('▼'); // Down arrow for collapsed
			}
		});
	});


	$(document).on('click', '.wbtm_return_icon', function (e) {

		let parent = $(this).closest('.wbtm_return_bus_lists_holder');
		let listHolder = parent.find('#wbtm_return_container');
		listHolder.toggle(200);

	});
	$(document).on('click', '.wbtm_departure_icon', function (e) {

		let parent = $(this).closest('.wbtm_departure_bus_lists_holder');
		let listHolder = parent.find('#start_bus');
		listHolder.toggle(200);

	});

	$(document).on('click', '.wtbm_start_route', function (e) {

		$('#wbtm_date_return_route_start').removeClass('wbtm_tab_active');
		$('#wbtm_date_start_route').addClass('wbtm_tab_active');

		$('#wbtm_return_container').fadeOut();
		let parent = $(this).closest('.wbtm_departure_bus_lists_holder');
		let listHolder = parent.find('#start_bus');
		listHolder.fadeIn(200);

	});

	$(document).on('click', '.wtbm_return_route', function (e) {
		let outboundCard = $('#wbtm_seleced_start_bus .wbtm_selected_bus_card');
		if (outboundCard.length === 0) {
			e.preventDefault();
			// Fixed by Shahnur - 2026-04-23 03:00 PM (Asia/Dhaka)
			// Do not allow return-tab browsing before the outbound bus is placed.
			wbtm_toast($(this).data('alert') || (typeof wbtm_strings !== 'undefined' ? wbtm_strings.place_departure_first : 'Please place departure bus first.'));
			return false;
		}
		wtbm_active_return_bus_tab_data();

	});

	function wtbm_active_return_bus_tab_data() {
		$('#wbtm_date_start_route').removeClass('wbtm_tab_active');
		$('#wbtm_date_return_route_start').addClass('wbtm_tab_active');

		$('#start_bus').fadeOut();
		let parent = $(this).closest('.wbtm_return_bus_lists_holder');
		let listHolder = $('#wbtm_return_container');
		listHolder.fadeIn(200);
		wbtm_filter_return_buses_by_outbound_time();
	}

	function wbtm_parse_bus_datetime(value) {
		if (!value) {
			return null;
		}

		let normalized = String(value).trim();
		if (!normalized) {
			return null;
		}

		normalized = normalized.replace(' ', 'T');
		if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(normalized)) {
			normalized += ':00';
		}

		let timestamp = Date.parse(normalized);
		return Number.isNaN(timestamp) ? null : timestamp;
	}

	function wbtm_normalize_date_only(value) {
		let timestamp = wbtm_parse_bus_datetime(value);
		if (timestamp !== null) {
			return new Date(timestamp).toISOString().slice(0, 10);
		}

		let fallback = String(value || '').trim();
		return fallback || null;
	}

	/**
	 * Filter return buses so that, on same-day return journeys,
	 * only buses departing AFTER the outbound bus arrives are shown.
	 */
	function wbtm_filter_return_buses_by_outbound_time() {
		var outboundCard = $('#wbtm_seleced_start_bus .wbtm_selected_bus_card');
		if (outboundCard.length === 0) {
			// No outbound bus selected yet — show all return buses
			$('#wbtm_return_container .wtbm_bus_counter').show();
			return;
		}

		var outboundBpTime = outboundCard.data('outbound-bp-time');
		var outboundDpTime = outboundCard.data('outbound-dp-time');
		var jDate          = outboundCard.data('j-date');
		var rDate          = outboundCard.data('r-date');
		var normalizedJDate = wbtm_normalize_date_only(jDate);
		var normalizedRDate = wbtm_normalize_date_only(rDate);

		// Only filter when return date equals journey date and we know the outbound travel window.
		if (!outboundBpTime || !outboundDpTime || !normalizedJDate || !normalizedRDate || normalizedJDate !== normalizedRDate) {
			$('#wbtm_return_container .wtbm_bus_counter').show();
			return;
		}

		var outboundArrivalTimestamp = wbtm_parse_bus_datetime(outboundDpTime);
		if (outboundArrivalTimestamp === null) {
			$('#wbtm_return_container .wtbm_bus_counter').show();
			return;
		}

		$('#wbtm_return_container .wtbm_bus_counter').each(function () {
			var busBpTime = $(this).data('bp-time');
			var sameBusReturn = String($(this).data('same-bus-return') || '0') === '1';
			var busDepartureTimestamp = wbtm_parse_bus_datetime(busBpTime);
			// Fixed by Shahnur - 2026-04-23 03:42 PM (Asia/Dhaka)
			// Apply same-day time validation only to buses that explicitly enable same-bus return trips.
			if (sameBusReturn && busDepartureTimestamp !== null && busDepartureTimestamp < outboundArrivalTimestamp) {
				$(this).hide();
			} else {
				$(this).show();
			}
		});
	}

	/**
	 * Editable Return Route (Pro): the return "From" is fixed to the outbound
	 * destination (you return from where you arrived), so only the "To" is editable.
	 * When the customer picks the return "To", reload the return bus list for the
	 * fixed From -> chosen To.
	 */
	$(document).on('mp_change', '#wbtm_return_container .wbtm_return_dropping_point input.formControl', function () {
		let container = $(this).closest('#wbtm_return_container');
		wbtm_reload_return_bus_list(container);
	});

	function wbtm_reload_return_bus_list(container) {
		let return_start = container.find('.wbtm_return_start_point input.formControl').val();
		let return_end = container.find('.wbtm_return_dropping_point input.formControl').val();
		if (!return_start || !return_end) {
			return;
		}
		// Guard against selecting the same place for From and To.
		if (return_start === return_end) {
			let alertMsg = container.find('.wbtm_return_dropping_point').data('alert') || 'You select Wrong Route !';
			wbtm_toast(alertMsg);
			return;
		}
		let listContainer = container.find('.wbtm_return_bus_lists_container');
		let leftFilter = container.data('left-filter');
		try {
			leftFilter = (typeof leftFilter === 'string') ? leftFilter : JSON.stringify(leftFilter || {});
		} catch (e) {
			leftFilter = '{}';
		}
		// On a same-day round trip, tell the server the outbound arrival time so it can
		// roll the return forward to the next day when no same-day bus departs after it.
		let outboundCard = $('#wbtm_seleced_start_bus .wbtm_selected_bus_card');
		let floorTime = '';
		if (outboundCard.length) {
			let jd = wbtm_normalize_date_only(outboundCard.data('j-date'));
			let rd = wbtm_normalize_date_only(container.data('r-date'));
			if (jd && rd && jd === rd) {
				floorTime = outboundCard.data('outbound-dp-time') || '';
			}
		}
		$.ajax({
			type: 'POST',
			url: wbtm_ajax_url,
			data: {
				action: 'get_wbtm_return_bus_list',
				post_id: container.data('post-id'),
				return_start: return_start,
				return_end: return_end,
				j_date: container.data('j-date'),
				r_date: container.data('r-date'),
				style: container.data('style'),
				btn_show: container.data('btn-show'),
				left_filter_show: leftFilter,
				floor_time: floorTime,
				nonce: wbtm_nonce
			},
			beforeSend: function () {
				wbtm_loader(listContainer);
			},
			success: function (data) {
				listContainer.html(data).promise().done(function () {
					wbtm_loaderRemove(listContainer);
					// Re-apply same-day time filter against the selected outbound bus.
					wbtm_filter_return_buses_by_outbound_time();
				});
			},
			error: function (response) {
				wbtm_loaderRemove(listContainer);
				wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_return_buses : 'Could not load return buses. Please try again.');
				console.log(response);
			}
		});
	}


	function wbtm_is_standalone_booking_mode() {
		return typeof wbtm_wc_vars !== 'undefined' && wbtm_wc_vars.booking_mode === 'standalone';
	}

	// Standalone/Custom Payment mode has no WooCommerce cart to stage a round trip in,
	// so the outbound leg's booking group id is held here after it is staged. The
	// return leg is then linked to this group so both legs check out as one order.
	var wbtm_standalone_outbound_group_id = 0;

	function wbtm_begin_standalone_checkout_loading() {
		if (wbtm_is_standalone_booking_mode()) {
			$(document).trigger('wbtm_standalone_checkout_loading');
		}
	}

	function wbtm_end_standalone_checkout_loading() {
		$(document).trigger('wbtm_standalone_checkout_loading_end');
	}

	function wbtm_reset_summary_book_now_buttons() {
		$('.wbtm_registration_area .wbtm_summary_preview_book_now.wbtm_is_loading').each(function () {
			wbtm_set_loading_button_state($(this), false);
		});
	}

	function wbtm_handle_standalone_checkout_response(response) {
		if (response.data && response.data.checkout_modal && response.data.checkout_html) {
			$(document).trigger('wbtm_standalone_checkout_open', [response.data.checkout_html]);
			return true;
		}
		if (response.data && response.data.redirect_url) {
			window.location.href = response.data.redirect_url;
			return true;
		}
		return false;
	}

	$(document).on('click', '#wbtm_add_to_cart', function (e) {
		e.preventDefault();

		let this_btn = $(this);
		if (this_btn.hasClass('wbtm_is_loading')) {
			wbtm_reset_summary_book_now_buttons();
			return false;
		}
		let form = this_btn.closest('form');

		let isValid = true;
		form.find('input[required], select[required], textarea[required]').each(function () {
			if ($(this).val().trim() === '') {
				$(this).addClass('wbtm_input_error');
				isValid = false;
			} else {
				$(this).removeClass('wbtm_input_error');
			}
		});
		if (!isValid) {
			wbtm_reset_summary_book_now_buttons();
			alert(typeof wbtm_strings !== 'undefined' ? wbtm_strings.fill_required_fields : 'Please fill all required fields');
			return;
		}
		wbtm_set_loading_button_state(this_btn, true);
		// Fixed by Shahnur 2026-06-02 — send the raw numeric total, not the locale-formatted text.
		// Reading ".wbtm_total" text (e.g. "39,00 lei") corrupted the value server-side (39 -> 3900).
		let priceTotalEl = $(this).closest('.wbtm_form_submit_area').find('.wbtm_total');
		let priceVal = priceTotalEl.attr('data-raw-total');
		if (typeof priceVal === 'undefined' || priceVal === '') {
			// Fallback: strip everything except digits, separators and minus, then normalise to a dot decimal.
			let raw = (priceTotalEl.text() || '').replace(/[^0-9.,-]/g, '');
			if (wbtm_currency_thousands_separator) {
				raw = raw.split(wbtm_currency_thousands_separator).join('');
			}
			if (wbtm_currency_decimal && wbtm_currency_decimal !== '.') {
				raw = raw.replace(wbtm_currency_decimal, '.');
			}
			priceVal = parseFloat(raw) || 0;
		}

		let burPosition = this_btn.closest('.wbtm-bus-lists').attr('id');
		let numberOfBuses = $('#wbtm_return_container .wtbm_bus_counter').length;
		// A round trip was requested whenever the return container is present (it only
		// renders when a return date was chosen). We must show the Return tab in that
		// case even if the default (exact-reverse) leg currently has no bus — e.g. a
		// reverse-direction outbound whose mirror leg isn't bookable — so the customer
		// can pick a valid return route (Editable Return Route) or "Checkout Without Return",
		// instead of being silently redirected to checkout.
		let returnRequested = $('#wbtm_return_container').length > 0;

		let wbtm_cabin_mode_enabled = form.find(':input[name=wbtm_cabin_mode_enabled]').val();

		const cabinSeats = [];
		$('input[name^="wbtm_selected_seat_cabin_"]').each(function () {
			const value = $(this).val();
			if (!value) return;
			const cabin = this.name.replace('wbtm_selected_seat_cabin_', '');
			cabinSeats.push({
				cabin: cabin,
				seat: value
			});
		});

		const cabinSeatTypes = [];
		$('input[name^="wbtm_selected_seat_type_cabin_"]').each(function () {
			const value = $(this).val();
			if (!value) return;
			const cabin = this.name.replace('wbtm_selected_seat_type_cabin_', '');
			cabinSeatTypes.push({
				cabin: cabin,
				seat: value
			});
		});


		// data = JSON.stringify(data);
		let extraServiceNames = form
			.find(':input[name="extra_service_name[]"]')
			.map(function () {
				return $(this).val();
			})
			.get();
		let extraServiceQty = form
			.find(':input[name="extra_service_qty[]"]')
			.map(function () {
				return $(this).val();
			})
			.get();

		// Include attendee fields for both seat-plan and without-seat-plan flows.
		// We only collect from the rendered attendee area to avoid the hidden template copy.
		let attendeeFormData = {};
		form.find('.wbtm_attendee_area :input[name]').each(function () {
			let input = $(this);
			let rawName = input.attr('name') || '';
			if (!rawName) {
				return;
			}
			let fieldName = rawName.endsWith('[]') ? rawName.slice(0, -2) : rawName;
			if (!fieldName) {
				return;
			}
			if (!Array.isArray(attendeeFormData[fieldName])) {
				attendeeFormData[fieldName] = [];
			}
			let value = input.val();
			attendeeFormData[fieldName].push(value ?? '');
		});

		let requestData = {
			"action": "wbtm_ajax_add_to_cart",
			"price_val": encodeURIComponent(priceVal),
			"wbtm_post_id": form.find(':input[name=wbtm_post_id]').val(),
			"wbtm_price_leg": form.find(':input[name=wbtm_price_leg]').val() || "outbound",
			// Journey role (which tab booked from), independent of the internal fare leg.
			"wbtm_journey_type": (burPosition === 'return_bus') ? 'return' : 'departure',
			// Round-trip context for Standalone/Custom Payment mode: whether a return was
			// requested (so the outbound leg is staged instead of checked out), and the
			// staged outbound booking group the return leg must be linked to.
			"wbtm_round_trip": returnRequested ? '1' : '0',
			"wbtm_return_group_id": (burPosition === 'return_bus') ? wbtm_standalone_outbound_group_id : 0,
			// When re-staging the outbound leg (e.g. after "Change Departure"), tell the
			// server which previously-staged outbound to discard so it stops holding seats.
			"wbtm_prev_outbound_group_id": (burPosition === 'return_bus') ? 0 : wbtm_standalone_outbound_group_id,
			"wbtm_start_point": form.find(':input[name=wbtm_start_point]').val(),
			"wbtm_cabin_mode_enabled": wbtm_cabin_mode_enabled,
			"wbtm_start_time": form.find(':input[name=wbtm_start_time]').val(),
			"wbtm_bp_place": form.find(':input[name=wbtm_bp_place]').val(),
			"wbtm_bp_time": form.find(':input[name=wbtm_bp_time]').val(),
			"wbtm_pickup_point": form.find(':input[name=wbtm_pickup_point]').val(),
			"wbtm_seat_qty": form.find(':input[name="wbtm_seat_qty[]"]').map(function () { return $(this).val(); }).get(),
			"wbtm_passenger_type": form.find(':input[name="wbtm_passenger_type[]"]').map(function () { return $(this).val(); }).get(),
			"wbtm_seat_price": form.find(':input[name="wbtm_seat_price[]"]').map(function () { return $(this).val(); }).get(),
			"wbtm_dp_place": form.find(':input[name=wbtm_dp_place]').val(),
			"wbtm_dp_time": form.find(':input[name=wbtm_dp_time]').val(),
			"wbtm_drop_off_point": form.find(':input[name=wbtm_drop_off_point]').val(),
			"bus_start_route": form.find(':input[name=bus_start_route]').val(),
			"bus_end_route": form.find(':input[name=bus_end_route]').val(),
			"j_date": form.find(':input[name=j_date]').val(),
			"r_date": form.find(':input[name=r_date]').val(),
			"wbtm_form_nonce": form.find(':input[name=wbtm_form_nonce]').val(),
			"_wp_http_referer": form.find(':input[name=_wp_http_referer]').val(),
			"wbtm_selected_seat": form.find(':input[name=wbtm_selected_seat]').val(),
			"wbtm_selected_seat_type": form.find(':input[name=wbtm_selected_seat_type]').val(),
			"wbtm_selected_seat_dd": form.find(':input[name=wbtm_selected_seat_dd]').val(),
			"wbtm_selected_seat_dd_type": form.find(':input[name=wbtm_selected_seat_dd_type]').val(),
			"extra_service_name": extraServiceNames,
			"extra_service_qty": extraServiceQty,
			"cabinSeats": JSON.stringify(cabinSeats),
			"cabinSeatTypes": JSON.stringify(cabinSeatTypes),
		};

		$.each(attendeeFormData, function (fieldName, values) {
			requestData[fieldName] = values;
		});

		// Suppresses the unconditional WC-checkout redirect below (complete:) once we've
		// already navigated somewhere else ourselves (standalone checkout, or we're
		// showing the inline login/register panel instead of proceeding).
		let suppressCompleteRedirect = false;

		function handleBookingFailure(response) {
			if (response.data && typeof response.data === 'object' && response.data.require_login) {
				// Custom Payment mode may require an account. Its inline login/register
				// panel (if loaded) handles it and calls trySubmitBooking again once
				// the visitor is authenticated — same requestData, fresh nonce.
				suppressCompleteRedirect = true;
				wbtm_set_loading_button_state(this_btn, false);
				wbtm_end_standalone_checkout_loading();
				wbtm_reset_summary_book_now_buttons();
				$(document).trigger('wbtm_require_login', [requestData, trySubmitBooking]);
				return;
			}
			wbtm_set_loading_button_state(this_btn, false);
			wbtm_end_standalone_checkout_loading();
			wbtm_reset_summary_book_now_buttons();
			var message = (response.data && typeof response.data === 'object' && response.data.message) || response.data;
			alert(message || (typeof wbtm_strings !== 'undefined' ? wbtm_strings.failed_add_ticket : 'Failed to add ticket'));
		}

		function trySubmitBooking() {
			wbtm_begin_standalone_checkout_loading();
			$.ajax({
				url: wbtm_ajax_url,
				type: 'POST',
				// data: data,

				data: requestData,
				success: function (response) {

					if (response.success) {
						// Standalone round trip: the outbound leg is staged (no checkout yet)
						// and its booking group id is returned so the return leg can join it.
						if (response.data && response.data.outbound_group_id) {
							wbtm_standalone_outbound_group_id = response.data.outbound_group_id;
						}
						// Standalone/custom booking mode: no WC cart involved — Pro opens an
						// inline checkout modal (preferred) or falls back to the checkout page.
						if (wbtm_handle_standalone_checkout_response(response)) {
							suppressCompleteRedirect = true;
							wbtm_set_loading_button_state(this_btn, false);
							wbtm_reset_summary_book_now_buttons();
							return;
						}
						wbtm_end_standalone_checkout_loading();
						wbtm_reset_summary_book_now_buttons();
						$("#wbtm_seleced_start_bus").html(response.data.selected_bus);
						$(document.body).trigger('wc_update_cart');
						// Re-apply same-day return bus filter based on newly selected outbound bus
						wbtm_filter_return_buses_by_outbound_time();
					} else {
						handleBookingFailure(response);
					}
				},
				// wp_send_json_error() answers with a real HTTP error status (401 for
				// require_login, 400 for validation, etc.), so jQuery routes it here
				// instead of success: — but it still parses the JSON body into
				// jqXHR.responseJSON, which is where require_login/the real message live.
				error: function (jqXHR) {
					if (jqXHR.responseJSON) {
						handleBookingFailure(jqXHR.responseJSON);
					} else {
						wbtm_set_loading_button_state(this_btn, false);
						wbtm_end_standalone_checkout_loading();
						wbtm_reset_summary_book_now_buttons();
						wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.error_generic : 'Something went wrong. Please try again.');
					}
				},
				complete: function () {
					if (suppressCompleteRedirect) {
						return;
					}
					if (burPosition === 'start_bus') {
						if (returnRequested) {
							wbtm_set_loading_button_state(this_btn, false);
							// Show the Return tab so the customer can choose a return bus (or
							// change the return route when Editable Return Route is on, or use
							// "Checkout Without Return"). Previously this checked the return bus
							// count and silently jumped to checkout when the default reverse leg
							// had no bus — breaking round trips for reverse-direction searches.
							wtbm_active_return_bus_tab_data();

						} else {
							window.location.href = wbtm_wc_vars.checkout_url;
						}
					} else {
						window.location.href = wbtm_wc_vars.checkout_url;
					}
				}
			});
		}

		// Fast client-side gate: skip the round trip when we already know a Custom
		// Payment booking needs an account. The server re-checks this authoritatively
		// inside WBTM_Standalone_Payment::create_booking() regardless.
		if (typeof wbtm_wc_vars !== 'undefined' && wbtm_wc_vars.login_required && !wbtm_wc_vars.is_logged_in) {
			suppressCompleteRedirect = true;
			wbtm_set_loading_button_state(this_btn, false);
			wbtm_reset_summary_book_now_buttons();
			$(document).trigger('wbtm_require_login', [requestData, trySubmitBooking]);
		} else {
			trySubmitBooking();
		}

	});

	$(document).on('click', '.wbtm_full_bus_book_now', function () {
		let this_btn = $(this);
		let parent = this_btn.closest('.wbtm_bus_list_area');
		let defaultHtml = this_btn.html();
		let startDate = parent.find(':input[name=j_date]').val() || this_btn.attr('data-date') || '';
		let returnDate = parent.find(':input[name=r_date]').val() || '';
		let fullBusJourneyType = this_btn.closest('#return_bus').length ? 'return' : 'departure';
		// A round trip was requested whenever the return container is present.
		let fullBusReturnRequested = $('#wbtm_return_container').length > 0;
		let requestData = {
			"action": "wbtm_ajax_add_to_cart",
			"wbtm_booking_mode": "full_bus",
			"price_val": encodeURIComponent(this_btn.attr('data-price') || 0),
			"wbtm_post_id": this_btn.attr('data-bus-id'),
			"wbtm_price_leg": this_btn.attr('data-price-leg') || "outbound",
			"wbtm_journey_type": fullBusJourneyType,
			// Round-trip context for Standalone/Custom Payment mode (ignored by the WC
			// cart flow, which only reads these in Pro's WBTM_Standalone_Payment): whether
			// a return was requested so the outbound leg is staged instead of checked out,
			// and the staged outbound group id the return leg must be linked to.
			"wbtm_round_trip": fullBusReturnRequested ? '1' : '0',
			"wbtm_return_group_id": (fullBusJourneyType === 'return') ? wbtm_standalone_outbound_group_id : 0,
			"wbtm_prev_outbound_group_id": (fullBusJourneyType === 'return') ? 0 : wbtm_standalone_outbound_group_id,
			"wbtm_start_point": this_btn.attr('data-start-point'),
			"wbtm_start_time": this_btn.attr('data-start-time'),
			"wbtm_bp_place": this_btn.attr('data-bp-place'),
			"wbtm_bp_time": this_btn.attr('data-bp-time'),
			"wbtm_dp_place": this_btn.attr('data-dp-place'),
			"wbtm_dp_time": this_btn.attr('data-dp-time'),
			"bus_start_route": parent.find(':input[name=bus_start_route]').val(),
			"bus_end_route": parent.find(':input[name=bus_end_route]').val(),
			"j_date": startDate,
			"r_date": returnDate,
			"wbtm_form_nonce": this_btn.attr('data-form-nonce'),
			"wbtm_selected_seat": "",
			"wbtm_cabin_mode_enabled": "no"
		};

		function handleFullBusBookingFailure(response) {
			if (response.data && typeof response.data === 'object' && response.data.require_login) {
				this_btn.prop('disabled', false).html(defaultHtml);
				$(document).trigger('wbtm_require_login', [requestData, trySubmitFullBusBooking]);
				return;
			}
			this_btn.prop('disabled', false).html(defaultHtml);
			var message = (response.data && typeof response.data === 'object' && response.data.message) || response.data;
			alert(message || 'Failed to add ticket');
		}

		function trySubmitFullBusBooking() {
			this_btn.prop('disabled', true).html(this_btn.attr('data-loading-text') || 'Loading...');
			$.ajax({
				url: wbtm_ajax_url,
				type: 'POST',
				data: requestData,
				success: function (response) {
					if (response.success) {
						// Standalone round trip: the outbound leg is staged (no checkout yet)
						// and its booking group id comes back so the return leg can join it.
						if (response.data && response.data.outbound_group_id) {
							wbtm_standalone_outbound_group_id = response.data.outbound_group_id;
						}
						// Standalone/Custom Payment mode answers with an inline checkout
						// modal (preferred) or a checkout-page redirect — handle and stop here.
						if (wbtm_handle_standalone_checkout_response(response)) {
							this_btn.prop('disabled', false).html(defaultHtml);
							return;
						}
						$("#wbtm_seleced_start_bus").html(response.data.selected_bus);
						$(document.body).trigger('wc_update_cart');
						wbtm_filter_return_buses_by_outbound_time();
						if (this_btn.closest('#start_bus').length && $('#return_bus .wtbm_bus_counter').length > 0) {
							wtbm_active_return_bus_tab_data();
						} else if (!wbtm_is_standalone_booking_mode()) {
							// WooCommerce cart flow: the outbound is in the cart, go to checkout.
							// In standalone the staged outbound card carries its own Checkout button
							// ("checkout without return"), so no forced redirect here.
							window.location.href = wbtm_wc_vars.checkout_url;
						}
					} else {
						handleFullBusBookingFailure(response);
					}
				},
				// See the identical comment in the regular-booking handler above: non-2xx
				// responses land here, not in success:, but jQuery still parses the JSON
				// body into jqXHR.responseJSON.
				error: function (jqXHR) {
					if (jqXHR.responseJSON) {
						handleFullBusBookingFailure(jqXHR.responseJSON);
					} else {
						wbtm_toast(typeof wbtm_strings !== 'undefined' ? wbtm_strings.failed_add_ticket : 'Failed to add ticket');
						this_btn.prop('disabled', false).html(defaultHtml);
					}
				},
				complete: function () {
					this_btn.prop('disabled', false).html(defaultHtml);
				}
			});
		}

		if (typeof wbtm_wc_vars !== 'undefined' && wbtm_wc_vars.login_required && !wbtm_wc_vars.is_logged_in) {
			$(document).trigger('wbtm_require_login', [requestData, trySubmitFullBusBooking]);
		} else {
			trySubmitFullBusBooking();
		}
	});

	$(document).on('click', '.wbtm-full-bus-tooltip-toggle', function (e) {
		e.preventDefault();
		e.stopPropagation();
		let tooltip = $(this).closest('.wbtm-full-bus-tooltip');
		let isOpen = tooltip.hasClass('is-open');
		$('.wbtm-full-bus-tooltip.is-open').removeClass('is-open').find('.wbtm-full-bus-tooltip-toggle').attr('aria-expanded', 'false');
		tooltip.toggleClass('is-open', !isOpen);
		$(this).attr('aria-expanded', !isOpen ? 'true' : 'false');
	});

	$(document).on('click', function () {
		$('.wbtm-full-bus-tooltip.is-open').removeClass('is-open').find('.wbtm-full-bus-tooltip-toggle').attr('aria-expanded', 'false');
	});


}(jQuery));

//====================================================================//
// Sort bus results client-side by the chosen attribute.
// Each .wbtm-bus-list card already carries data-bp-time, data-price and
// data-duration. The immediately following .wbtm_bus_details sibling is the
// seat-plan container, so it must move with its card. Works inside both the
// main result holder and the return-bus container.
(function ($) {
	"use strict";

	function wbtm_sort_bus_results(holder, mode) {
		if (!holder || !holder.length) {
			return;
		}
		var cards = holder.children('.wbtm-bus-list');
		if (cards.length < 2) {
			return;
		}
		var pairs = [];
		cards.each(function () {
			var card = $(this);
			var details = card.next('.wbtm_bus_details');
			pairs.push({ card: card, details: details.length ? details : null });
		});
		pairs.sort(function (a, b) {
			var av, bv;
			switch (mode) {
				case 'latest':
					av = $(a.card).data('bp-time');
					bv = $(b.card).data('bp-time');
					return wbtm_sort_cmp_text(bv, av); // descending
				case 'price_asc':
					av = parseFloat($(a.card).data('price')) || 0;
					bv = parseFloat($(b.card).data('price')) || 0;
					return av - bv;
				case 'price_desc':
					av = parseFloat($(a.card).data('price')) || 0;
					bv = parseFloat($(b.card).data('price')) || 0;
					return bv - av;
				case 'duration_asc':
					av = parseInt($(a.card).data('duration'), 10) || 0;
					bv = parseInt($(b.card).data('duration'), 10) || 0;
					return av - bv;
				case 'earliest':
				default:
					av = $(a.card).data('bp-time');
					bv = $(b.card).data('bp-time');
					return wbtm_sort_cmp_text(av, bv); // ascending
			}
		});
		$.each(pairs, function (i, pair) {
			holder.append(pair.card);
			if (pair.details) {
				holder.append(pair.details);
			}
		});
	}

	// Compare bp-time strings ("YYYY-MM-DD HH:MM[:SS]"). Parse locally rather
	// than reusing wbtm_parse_bus_datetime, which is scoped to a different IIFE.
	function wbtm_sort_cmp_text(a, b) {
		var ta = wbtm_sort_parse_ts(a);
		var tb = wbtm_sort_parse_ts(b);
		if (ta === null && tb === null) return 0;
		if (ta === null) return 1;
		if (tb === null) return -1;
		return ta - tb;
	}
	function wbtm_sort_parse_ts(value) {
		if (!value) return null;
		var s = String(value).trim().replace(' ', 'T');
		if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(s)) s += ':00';
		var t = Date.parse(s);
		return Number.isNaN(t) ? null : t;
	}

	$(document).on('change', '#wbtm_sort_select', function () {
		var mode = $(this).val();
		// Main result list
		wbtm_sort_bus_results($('.wbtm_search_result_holder').first(), mode);
		// Return-bus container, if present
		wbtm_sort_bus_results($('#wbtm_return_container .wbtm_return_bus_lists_container').first(), mode);
	});

	// Default the select to "earliest" to match the server-side usort order.
	$(document).ready(function () {
		$('#wbtm_sort_select').val('earliest');
	});
}(jQuery));


//====================================================================//
// Seat-hold countdown (WBTM_Seat_Hold): selecting a seat asks the server
// for a temporary hold; the badge in the selected-seat summary counts down
// the hold TTL, and on expiry the seat plan is refreshed so released seats
// show up again.
(function ($) {
	"use strict";

	var wbtm_hold_countdown = null;

	function wbtm_hold_strings() {
		return (typeof wbtm_strings !== 'undefined') ? wbtm_strings : {};
	}

	// Cabin seats are held under the cabin_{index}_{seat} identifier — the same
	// convention the booking records use (see WBTM_Seat_Hold::seat_identifier()).
	function wbtm_hold_seat_identifier(seat) {
		var seat_name = seat.attr('data-seat_name');
		var cabin_index = seat.attr('data-cabin_index');
		return cabin_index ? 'cabin_' + cabin_index + '_' + seat_name : seat_name;
	}

	// Route/date context the hold endpoints need, read from the booking form.
	function wbtm_hold_context(parent) {
		var form = parent.closest('form').length ? parent.closest('form') : parent;
		return {
			bus_id: form.find(':input[name=wbtm_post_id]').val(),
			date: form.find(':input[name=wbtm_bp_time]').val() || form.find(':input[name=j_date]').val(),
			start: form.find(':input[name=wbtm_bp_place]').val(),
			end: form.find(':input[name=wbtm_dp_place]').val()
		};
	}

	function wbtm_hold_badge(parent) {
		var badge = parent.find('.wbtm_hold_badge');
		if (!badge.length) {
			// The redesigned layout hides .wbtm_selected_seat_details (display:none)
			// and shows the .wbtm_booking_summary_preview card instead, so a badge
			// appended to the old table would never be seen. Prefer the visible
			// summary card, falling back to the classic table for layouts (e.g. the
			// without-seat-plan flow or theme overrides) that have no preview card.
			var summary = parent.find('.wbtm_booking_summary_preview');
			if (!summary.length) {
				summary = parent.find('.wbtm_selected_seat_details');
			}
			if (!summary.length) {
				return $();
			}
			badge = $('<div class="wbtm_hold_badge" role="status"></div>');
			summary.append(badge);
		}
		return badge;
	}

	function wbtm_hold_badge_text(remaining) {
		var minutes = Math.floor(remaining / 60);
		var seconds = remaining % 60;
		var label = wbtm_hold_strings().seats_held_for || 'Seats held for';
		return label + ' ' + ('0' + minutes).slice(-2) + ':' + ('0' + seconds).slice(-2);
	}

	function wbtm_hold_clear_timer(parent) {
		if (wbtm_hold_countdown) {
			clearInterval(wbtm_hold_countdown);
			wbtm_hold_countdown = null;
		}
		parent.find('.wbtm_hold_badge').remove();
		parent.removeData('wbtm_hold_expires');
	}

	function wbtm_hold_on_expired(parent) {
		wbtm_hold_clear_timer(parent);
		if (typeof wbtm_toast === 'function') {
			wbtm_toast(wbtm_hold_strings().seat_hold_expired || 'Your seat hold has expired.');
		}
		// Re-run the existing seat-plan refresh: collapse the open bus details and
		// reload them so released/other-held seats render with fresh availability.
		var bus_list = parent.closest('.wbtm_bus_list_area');
		var toggle = bus_list.find('#get_wbtm_bus_details.mActive');
		if (toggle.length) {
			toggle.trigger('click');
			setTimeout(function () {
				toggle.trigger('click');
			}, 300);
		}
	}

	function wbtm_hold_start_timer(parent, expires) {
		if (!expires) {
			return;
		}
		parent.data('wbtm_hold_expires', expires);
		if (wbtm_hold_countdown) {
			clearInterval(wbtm_hold_countdown);
		}
		wbtm_hold_countdown = setInterval(function () {
			var remaining = Math.round(parent.data('wbtm_hold_expires') - Date.now() / 1000);
			if (remaining <= 0) {
				wbtm_hold_on_expired(parent);
				return;
			}
			var badge = wbtm_hold_badge(parent);
			if (!badge.length) {
				wbtm_hold_clear_timer(parent);
				return;
			}
			badge.text(wbtm_hold_badge_text(remaining)).toggleClass('wbtm_hold_badge--urgent', remaining < 60);
		}, 1000);
		// Render the badge immediately instead of after the first tick.
		var initial = Math.round(expires - Date.now() / 1000);
		if (initial > 0) {
			wbtm_hold_badge(parent).text(wbtm_hold_badge_text(initial)).toggleClass('wbtm_hold_badge--urgent', initial < 60);
		}
	}

	function wbtm_hold_request(parent, seats) {
		var context = wbtm_hold_context(parent);
		if (!context.bus_id || !context.date || !seats.length) {
			return;
		}
		$.ajax({
			type: 'POST',
			url: wbtm_ajax_url,
			data: {
				action: 'wbtm_hold_seats',
				nonce: wbtm_nonce,
				bus_id: context.bus_id,
				date: context.date,
				start: context.start,
				end: context.end,
				seats: seats
			},
			success: function (response) {
				if (!response || !response.success) {
					return;
				}
				var data = response.data || {};
				if (data.expires) {
					wbtm_hold_start_timer(parent, data.expires);
				}
				// Seats the server refused (booked, or held by another customer) are
				// toggled back off so the customer isn't heading for a submit error.
				if (data.conflicts && data.conflicts.length) {
					$.each(data.conflicts, function (_, identifier) {
						parent.find('.seat_available.seat_selected').each(function () {
							if (wbtm_hold_seat_identifier($(this)) === identifier) {
								$(this).trigger('click');
								if (typeof wbtm_toast === 'function') {
									wbtm_toast(identifier + ' ' + (wbtm_hold_strings().seat_hold_conflict || 'is no longer available.'));
								}
								return false;
							}
						});
					});
				}
			}
		});
	}

	function wbtm_release_request(parent, seats) {
		var context = wbtm_hold_context(parent);
		if (!context.bus_id || !context.date || !seats.length) {
			return;
		}
		$.ajax({
			type: 'POST',
			url: wbtm_ajax_url,
			data: {
				action: 'wbtm_release_seats',
				nonce: wbtm_nonce,
				bus_id: context.bus_id,
				date: context.date,
				seats: seats
			}
		});
	}

	// Bound after the main seat handler above, so the seat_selected class already
	// reflects the new state when this runs.
	$(document).on('click', '.wbtm_registration_area .seat_available', function () {
		var seat = $(this);
		var parent = seat.closest('.wbtm_registration_area');
		var identifier = wbtm_hold_seat_identifier(seat);
		if (!identifier) {
			return;
		}
		if (seat.hasClass('seat_selected')) {
			wbtm_hold_request(parent, [identifier]);
		} else {
			wbtm_release_request(parent, [identifier]);
			// Last seat deselected (deselect-all): clear the countdown badge.
			if (!parent.find('.seat_available.seat_selected').length) {
				wbtm_hold_clear_timer(parent);
			}
		}
	});

	// Booking submit: the server takes over the seats (booking-time validation),
	// so the countdown badge is cleared locally.
	$(document).on('click', '#wbtm_add_to_cart', function () {
		var parent = $(this).closest('.wbtm_registration_area');
		if (parent.length) {
			wbtm_hold_clear_timer(parent);
		}
	});
}(jQuery));
