//==========Modal / Popup==========//
(function ($) {
	"use strict";
               /*add bus stop*/
	$(".submit-bus-stop").click(function(e) {
		e.preventDefault();
		let $this=$(this);
		let target=$this.closest('.mpPopup').find('.bus-stop-form');
		let name = $("#bus_stop_name").val().trim();
		$(".success_text").slideUp('fast');
		if(!name){
			$(".name_required").show();
		}else {
			let description = $("#bus_stop_description").val().trim();

			$.ajax({
				type: 'POST',
				// url:wbtm_ajax.wbtm_ajaxurl,
				url: wbtm_ajaxurl,
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

					if(data.text == 'error'){

						$(".name_required").hide();
						$("#bus_stop_name").val("");
						$("#bus_stop_description").val("");
						$(".duplicate_text").slideDown('fast');
						setTimeout(function() {
							$('.duplicate_text').fadeOut('fast');
						}, 3000); // <-- time in milliseconds
						dLoaderRemove(target);
					}else{
						$('.bus_stop_add_option').append($('<option>', {
							value: data.text,
							text: data.text,
							'data-term_id': data.term_id
						}));

						$(".name_required").hide();
						$("#bus_stop_name").val("");
						$("#bus_stop_description").val("");
						$(".success_text").slideDown('fast');
						setTimeout(function() {
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
	$(".submit-pickup").click(function(e) {
		e.preventDefault();
		let $this=$(this);
		let target=$this.closest('.mpPopup').find('.pickup-form');
		let name = $("#pickup_name").val().trim();
		$(".success_text").slideUp('fast');
		if(!name){
			$(".name_required").show();
		}else {
			let description = $("#pickup_description").val().trim();

			$.ajax({
				type: 'POST',
				// url:wbtm_ajax.wbtm_ajaxurl,
				url: wbtm_ajaxurl,
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

					if(data.text == 'error') {

						$(".name_required").hide();
						$("#pickup_name").val("");
						$("#pickup_description").val("");
						$(".duplicate_text").slideDown('fast');
						setTimeout(function() {
							$('.duplicate_text').fadeOut('fast');
						}, 1000); // <-- time in milliseconds
						dLoaderRemove(target);

					}else{

						$('.pickup_add_option').append($('<option>', {
							value: data.text,
							text: data.text,
							'data-term_id': data.term_id
						}));

						$(".name_required").hide();
						$("#pickup_name").val("");
						$("#pickup_description").val("");
						$(".success_text").slideDown('fast');
						setTimeout(function() {
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


	$("#upper-desk-control").click(function(){
		$("#upper-desk").slideToggle("slow");
	});

	$("#pickup-point-control").click(function(){
		$("#pickup-point").slideToggle("slow");
	});

	$("#operational-on-day-control").click(function(){
		$(".operational-on-day").slideToggle("slow");
	});

	$("#off-day-control").click(function(){
		$(".off-day").slideToggle("slow");
	});

	$("#return-operational-on-day-control").click(function(){
		$(".return-operational-on-day").slideToggle("slow");
	});

	$("#return-off-day-control").click(function(){
		$(".return-off-day").slideToggle("slow");
	});



	$("#extra-service-control").click(function(){
		$(".extra-service").slideToggle("slow");
	});


	$(".add-more-bd-point").click(function(e){
		e.preventDefault();
		$(this).siblings().children('.bd-point, .bd-point-return').append('<tr>'+$(this).siblings().children().children(".more-bd-point").html()+'</tr>');
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



	$(document).on('click','.remove-bp-row',function (e){
		e.preventDefault();
		$(this).parents('tr').remove();
		return false;
	});

	$(document).on('click','.open-routing-tab',function (e){
		e.preventDefault();
		//$(this).removeClass();
		$( ".wbtm_routing_tab" ).click();
		return false;
	});

	$(document).on('click','.wbtm_pickuppoint_tab',function (e){
		e.preventDefault();
		$('.wbtm_pick_boarding').html("<option value=''>Select Boarding Point</option>");
		$('.wbtm_pick_boarding_return').html("<option value=''>Select Boarding Point</option>");
		let options = '';
		$( ".boarding-point tr" ).each(function( index ) {
			console.log( index + ": " + $(this).find(":selected").val() );

			options = options+$(this).find(":selected").val();

			if(options){
				$('.boarding_points').show();
				$('.open-routing-tab').hide();
			}else{
				$('.open-routing-tab').show();
				$('.boarding_points').hide();
			}
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.wbtm_pick_boarding').append("<option value="+term_id+">"+$(this).find(":selected").val()+"</option>")
			}
		});

		$( ".boarding-point-return tr" ).each(function( index ) {
			console.log( index + ": " + $(this).find(":selected").val() );

			options = options+$(this).find(":selected").val();

			if(options){
				$('.boarding_points').show();
				$('.open-routing-tab').hide();
			}else{
				$('.open-routing-tab').show();
				$('.boarding_points').hide();
			}
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.wbtm_pick_boarding_return').append("<option value="+term_id+">"+$(this).find(":selected").val()+"</option>")
			}
		});

		return false;
	});

	/*seat pricing start*/

	$(document).on('change','.wbtm_bus_stops_route',function (e){
		e.preventDefault();

		var new_bus = $('#price_bus_record').val();
		var return_class = $('#return_class').val();


		if(new_bus==''){
			var route_row = '';
			var i = 0;
			$( ".boarding-point tr" ).each(function( index ) {
				var j = 0;
				let term_id = $(this).find(':selected').data('term_id');
				if(term_id){
					var boarding_point = $(this).find(":selected").val();
					$( ".dropping-point tr" ).each(function( index ) {
						if (i <= j) {
							let term_id = $(this).find(':selected').data('term_id');
							if(term_id){
								var dropping_point = $(this).find(":selected").val();
									route_row += '<tr class="temprary-record-price"><td>'+boarding_point+'</td><td>'+dropping_point+'</td><td class="wbtm-wid-15">\n' +
										'    <input type="hidden" name="wbtm_bus_bp_price_stop[]" value="'+boarding_point+'" class="text">\n' +
										'    <input type="hidden" name="wbtm_bus_dp_price_stop[]" value="'+dropping_point+'" class="text">\n' +
										'    <input type="text" class="widefat" name="wbtm_bus_price[]" placeholder="1500" value="">\n' +
										'    <input type="text" class="widefat '+return_class+'" name="wbtm_bus_price_return[]" placeholder="Adult Return Price" value="">\n' +
										'</td> <td class="wbtm-wid-15">\n' +
										'        <input type="text" class="widefat" name="wbtm_bus_child_price[]" placeholder="1200" value="">\n' +
										'        <input type="text" class="widefat '+return_class+'" name="wbtm_bus_child_price_return[]" placeholder="Child return price" value="">\n' +
										'    </td><td class="wbtm-wid-15">\n' +
										'        <input type="text" class="widefat" name="wbtm_bus_infant_price[]" placeholder="1000" value="">\n' +
										'        <input type="text" class="widefat '+return_class+'" name="wbtm_bus_infant_price_return[]" placeholder="Infant return price" value="">\n' +
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
			$( ".boarding-point-return tr" ).each(function( index ) {
				var j = 0;
				let term_id = $(this).find(':selected').data('term_id');
				if(term_id){
					var boarding_point = $(this).find(":selected").val();
					$( ".dropping-point-return tr" ).each(function( index ) {
						if (i <= j) {
							let term_id = $(this).find(':selected').data('term_id');
							if(term_id){
								var dropping_point = $(this).find(":selected").val();
								route_row_return += '<tr class="temprary-record-price-return"><td>'+boarding_point+'</td><td>'+dropping_point+'</td><td class="wbtm-wid-15">\n' +
									'    <input type="hidden" name="wbtm_bus_bp_price_stop_return[]" value="'+boarding_point+'" class="text">\n' +
									'    <input type="hidden" name="wbtm_bus_dp_price_stop_return[]" value="'+dropping_point+'" class="text">\n' +
									'    <input type="text" class="widefat" name="wbtm_bus_price_r[]" placeholder="1500" value="">\n' +
									'    <input type="text" class="widefat '+return_class+'" name="wbtm_bus_price_return_discount[]" placeholder="Adult Return Price" value="">\n' +
									'</td> <td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_child_price_r[]" placeholder="1200" value="">\n' +
									'        <input type="text" class="widefat '+return_class+'" name="wbtm_bus_child_price_return_discount[]" placeholder="Child return price" value="">\n' +
									'    </td><td class="wbtm-wid-15">\n' +
									'        <input type="text" class="widefat" name="wbtm_bus_infant_price_r[]" placeholder="1000" value="">\n' +
									'        <input type="text" class="widefat '+return_class+'" name="wbtm_bus_infant_price_return_discount[]" placeholder="Infant return price" value="">\n' +
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

		$('.ra_bus_bp_price_stop').html("<option value=''>Select Boarging Point</option>");
		$( ".boarding-point tr" ).each(function( index ) {
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.ra_bus_bp_price_stop').append("<option value='"+$(this).find(":selected").val()+"'>"+$(this).find(":selected").val()+"</option>")
			}
		});

		$('.ra_bus_dp_price_stop').html("<option value=''>Select Dropping Point</option>");
		$( ".dropping-point tr" ).each(function( index ) {
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.ra_bus_dp_price_stop').append("<option value='"+$(this).find(":selected").val()+"'>"+$(this).find(":selected").val()+"</option>")
			}
		});


		return false;



	});

	$(document).on('change','.ra_bus_bp_price_stop',function (e){
		e.preventDefault();
		$( this ).even().removeClass( "ra_bus_bp_price_stop" );
	});

	$(document).on('change','.ra_bus_dp_price_stop',function (e){
		e.preventDefault();
		$( this ).even().removeClass( "ra_bus_dp_price_stop" );
	});

	$('.wbtm-tb-repeat-btn').on('click', function (e) {
		e.preventDefault();
		let tableFor = $(this).siblings('.repeatable-fieldset');
		let row = tableFor.find('.mtsa-empty-row-t').clone(true);
		row.removeClass('mtsa-empty-row-t');
		row.insertAfter(tableFor.find('tbody>tr:last'));
	});



	$(document).on('click','.remove-price-row',function (e){
		e.preventDefault();
		$(this).parents('tr').remove();
		return false;
	});


	/*seat pricing end*/







	$(document).on('click','.ra_seat_price',function (e){
		e.preventDefault();

		$('.ra_bus_bp_price_stop').html("<option value=''>Select Boarging Point</option>");
		$( ".boarding-point tr" ).each(function( index ) {
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.ra_bus_bp_price_stop').append("<option value="+$(this).find(":selected").val()+">"+$(this).find(":selected").val()+"</option>")
			}
		});

		$('.ra_bus_dp_price_stop').html("<option value=''>Select Dropping Point</option>");
		$( ".dropping-point tr" ).each(function( index ) {
			let term_id = $(this).find(':selected').data('term_id');
			if(term_id){
				$('.ra_bus_dp_price_stop').append("<option value="+$(this).find(":selected").val()+">"+$(this).find(":selected").val()+"</option>")
			}
		});

		return false;
	});




}(jQuery));