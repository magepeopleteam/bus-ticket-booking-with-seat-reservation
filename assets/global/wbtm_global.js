//==================================================Search area==================//
(function ($) {
	"use strict";
	$(document).on("click", "#wbtm_area button.get_wbtm_bus_list", function (e) {
		e.preventDefault();
		let parent = $(this).closest('#wbtm_area');
		let start = parent.find('input[name="bus_start_route"]');
		let end = parent.find('input[name="bus_end_route"]');
		let j_date = parent.find('input[name="j_date"]');
		let style = parent.find('input[name="wbtm_list_style"]');
		let btn_show = parent.find('input[name="wbtm_list_btn_show"]');
		let left_filter_input = parent.find('input[name="wbtm_left_filter_show"]');
		let wbtm_left_filter_type = parent.find('input[name="wbtm_left_filter_type"]');
		let wbtm_left_filter_operator = parent.find('input[name="wbtm_left_filter_operator"]');
		let wbtm_left_filter_boarding = parent.find('input[name="wbtm_left_filter_boarding"]');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		if (!mp_check_required(start)) {
			start.trigger('click');
			return false;
		}
		if (!mp_check_required(end)) {
			end.trigger('click');
			return false;
		}
		if (!mp_check_required(j_date)) {
			j_date.siblings('input').focus();
			return false;
		} else {
			let r_date = parent.find('input[name="r_date"]');
			let post_id = parent.find('[name="wbtm_post_id"]').val();
			$.ajax({
        type: "POST",
        url: mp_ajax_url,
        data: {
          action: "get_wbtm_bus_list",
          start_route: start.val(),
          end_route: end.val(),
          j_date: j_date.val(),
          r_date: r_date.val(),
          post_id: post_id,
          style: style.val(),
          btn_show: btn_show.val(),
		  left_filter_show:{
			  left_filter_input : left_filter_input.val(),
			  left_filter_type : wbtm_left_filter_type.val(),
			  left_filter_operator : wbtm_left_filter_operator.val(),
			  left_filter_boarding : wbtm_left_filter_boarding.val(),
		  },
          backend_order: window.location.href.search("wbtm_backend_order"),
        },
        beforeSend: function () {
          dLoader(parent.find(".wbtm_search_area"));
        },
		  success: function (data) {
          parent
            .find(".wbtm_search_result")
            .html(data)
            .promise()
            .done(function () {
              dLoaderRemove(parent.find(".wbtm_search_area"));
              loadBgImage();
            });
        },
        error: function (response) {
          console.log(response);
        },
      });
		}
	});
	$(document).on("click", "#wbtm_area button.wbtm_next_date", function () {
		let date = $(this).data('date');
		let parent = $(this).closest('#wbtm_area');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		let name = $(this).closest('#wbtm_return_container').length > 0 ? 'r_date' : 'j_date';
		parent.find('input[name=' + name + ']').val(date).promise().done(function () {
			parent.find('.get_wbtm_bus_list,.wbtm_bus_submit').trigger('click');
		});
	});
	$(document).on("mp_change", "div.wbtm_search_area .wbtm_start_point input.formControl", function () {
		let current = $(this);
		let start_route = current.val();
		let parent = current.closest('.wbtm_search_area');
		let target = parent.find('.wbtm_dropping_point');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		parent.find('.wbtm_dropping_point .mp_input_select_list').remove();
		target.find('input.formControl').val('');
		dLoader_xs(parent);
		let exit_route = 0;
		parent.find('.wbtm_start_point .mp_input_select_list li').each(function () {
			let current_route = $(this).data('value');
			if (current_route === start_route) {
				exit_route = 1;
			}
		}).promise().done(function () {
			if (exit_route > 0) {
				let post_id = parent.find('[name="wbtm_post_id"]').val();
				$.ajax({
					type: 'POST',
					url: mp_ajax_url,
					data: {
						"action": "get_wbtm_dropping_point",
						"start_route": start_route,
						"post_id": post_id,
					},
					success: function (data) {
						target.append(data).promise().done(function () {
							dLoaderRemove(parent);
							target.find('input.formControl').trigger('click');
						});
					},
					error: function (response) {
						console.log(response);
					}
				});
			} else {
				dLoaderRemove(parent);
				mp_alert(target);
				current.val('').trigger('click');
			}
		});
		//alert(start_route);
	});
	$(document).on("mp_change", "div.wbtm_search_area .wbtm_dropping_point input.formControl", function () {
		let current = $(this);
		let end_route = current.val();
		let parent = current.closest('.wbtm_search_area');
		$('body').find('.woocommerce-notices-wrapper').slideUp('fast');
		let exit_route = 0;
		parent.find('.wbtm_dropping_point .mp_input_select_list li').each(function () {
			let current_route = $(this).data('value');
			if (current_route === end_route) {
				exit_route = 1;
			}
		}).promise().done(function () {
			wbtm_load_journey_date(parent);
		}).promise().done(function (){
			if (exit_route > 0) {
				parent.find('input[name="j_date"]').siblings('input').focus();
			} else {
				current.val('').trigger('click');
			}
		});
		//alert(start_route);
	});
	function wbtm_load_journey_date(parent) {
		let post_id = parent.find('[name="wbtm_post_id"]').val();
		let start_route = parent.find('[name="bus_start_route"]').val();
		let end_route = parent.find('[name="bus_end_route"]').val();
		let target = parent.find('.wbtm_journey_date');
		$.ajax({
			type: 'POST',
			url: mp_ajax_url,
			data: {
				"action": "get_wbtm_journey_date",
				"start_route": start_route,
				"end_route": end_route,
				"post_id": post_id,
			},
			beforeSend: function () {
				dLoader_xs(target);
			},
			success: function (data) {
				target.html(data);
				dLoaderRemove(target);
			},
			error: function (response) {
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
			//alert(date);
			//alert(end_route);
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				data: {
					"action": "get_wbtm_return_date",
					"start_route": start_route,
					"end_route": end_route,
					"j_date": date,
					"post_id": post_id,
				},
				beforeSend: function () {
					dLoader_xs(target);
				},
				success: function (data) {
					target.html(data);
					dLoaderRemove(target);
				},
				error: function (response) {
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
			mp_alert($(this));
		}
	});
}(jQuery));
//====================================================================//
(function ($) {
  "use strict";
  $(document).on("click", "#get_wbtm_bus_details", function () {
    let parent = $(this).closest(".wbtm_bus_list_area");
    let post_id = $(this).attr("data-bus_id");
    let target = parent.find("[data-row_id=" + post_id + "]");
    $("body").find(".woocommerce-notices-wrapper").slideUp("fast");
    if ($(this).hasClass("mActive")) {
      target.find(">div").slideUp("fast");
      mp_all_content_change($(this));
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
          url: mp_ajax_url,
          data: {
            action: "get_wbtm_bus_details",
            start_route: start,
            end_route: end,
            post_id: post_id,
            date: date,
			  j_date: j_date,
			  r_date: r_date,
            backend_order: window.location.href.search("wbtm_backend_order"),
          },
          beforeSend: function () {
            dLoader(parent);
          },
          success: function (data) {
            target.html(data);
            dLoaderRemove(parent);
            loadBgImage();
          },
          error: function (response) {
            console.log(response);
          },
        });
      }
      mp_all_content_change($(this));
    }
  });
})(jQuery);
//====================================================================//
(function ($) {
	"use strict";
	function wbtm_price_calculation(parent) {
		let total_qty = wbtm_seat_qty(parent);
		wbtm_seat_calculation(parent, total_qty);
		wbtm_attendee_management(parent, total_qty);
		let target_summary = parent.find('.wbtm_total');
		let target_sub_total = parent.find('.wbtm_sub_total');
		let total = wbtm_ticket_price(parent);
		target_sub_total.html(mp_price_format(total));
		if (total_qty > 0) {
			parent.find('.wbtm_ex_service_area').slideDown('fast');
			parent.find('.wbtm_form_submit_area').slideDown('fast');
			total = total + wbtm_ex_service_price(parent);
			target_summary.html(mp_price_format(total));
		} else {
			parent.find('.wbtm_ex_service_area').slideUp('fast');
			parent.find('.wbtm_form_submit_area').slideUp('fast');
			target_summary.html(mp_price_format(total));
		}
	}
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
	$(document).on('click', '.wbtm_registration_area .wbtm_selected_seat_details .mp_item_remove', function () {
		let current = $(this);
		let current_tr = current.closest('tr');
		let seat_name = current_tr.attr('data-seat_name');
		let seat_type = current_tr.attr('data-seat_type');
		let parent = current.closest('.wbtm_registration_area');
		parent.find('.seat_available.seat_selected').each(function () {
			if ($(this).attr('data-seat_name') === seat_name && $(this).attr('data-seat_type') === seat_type) {
				$(this).trigger('click');
				return false;
			}
		});
	});
	function wbtm_seat_calculation(parent, total_qty) {
		if (parent.find('.wbtm_seat_plan_area').length > 0) {
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
						if (target.find('[data-seat_name="' + seat_name + '"]').length === 0) {
							wbtm_reload_selected_seat($(this),hidden_target_tr,target);
						}else{
							if (target.find('[data-seat_name="' + seat_name + '"]').length === 1 && target.find('[data-seat_type="' + seat_type + '"]').length === 0) {
								target.find('[data-seat_name="' + seat_name + '"]').remove();
								wbtm_reload_selected_seat($(this),hidden_target_tr,target);
							}
						}
					}).promise().done(function () {
						item_length = target.find('.wbtm_remove_area').length;
						if (item_length !== total_qty) {
							target.find('.wbtm_remove_area').each(function () {
								let seat_name = $(this).attr('data-seat_name');
								if (parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"]').length === 0) {
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
	function wbtm_reload_selected_seat(current,hidden_target_tr,target){
		let seat_label = current.attr('data-seat_label');
		let seat_price = current.attr('data-seat_price');
		let seat_name = current.attr('data-seat_name');
		let seat_type = current.attr('data-seat_type');
		hidden_target_tr.attr('data-seat_type', seat_type).attr('data-seat_name', seat_name).promise().done(function () {
			hidden_target_tr.find('.insert_seat_label').html(seat_label);
			hidden_target_tr.find('.insert_seat_name').html(seat_name);
			hidden_target_tr.find('.insert_seat_price').html(mp_price_format(seat_price));
		}).promise().done(function () {
			target.append(hidden_target_tr.clone());
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
						if (form_target.find('[data-seat_name="' + seat_name + '"]').length === 0) {
							hidden_target.find('.wbtm_attendee_item').attr('data-seat_name', seat_name);
							hidden_target.find('.wbtm_seat_name').html(seat_name).promise().done(function () {
								form_target.append(hidden_target.html());
							}).promise().done(function () {
								mp_load_date_picker(parent);
							});
						}
					}).promise().done(function () {
						form_length = form_target.find('.wbtm_attendee_item').length;
						if (form_length !== total_qty) {
							form_target.find('.wbtm_attendee_item').each(function () {
								let seat_name = $(this).attr('data-seat_name');
								if (parent.find('.seat_available.seat_selected[data-seat_name="' + seat_name + '"]').length === 0) {
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
								mp_load_date_picker(parent);
							});
						}
					}
				}
			}
		} else {
			form_target.html('').slideUp(250);
		}
	}
}(jQuery));