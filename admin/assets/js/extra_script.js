//==========Modal / Popup==========//
(function ($) {
	"use strict";

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
	$("#extra-service-control").click(function(){
		$(".extra-service").slideToggle("slow");
	});
<<<<<<< HEAD
=======



>>>>>>> aaa2bd6ffa7d4cc5f84b0b24ea525974cc3557c6




	$(".add-more-bd-point").click(function(e){
		e.preventDefault();
		$(this).siblings().children('.bd-point').append('<tr>'+$(this).siblings().children().children(".more-bd-point").html()+'</tr>');
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
		//$(this).removeClass();
		//$( ".wbtm_pickuppoint_tab" ).click();

		$('.wbtm_pick_boarding').html("<option value=''>Select Boarding Point</option>");

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


<<<<<<< HEAD


=======
>>>>>>> aaa2bd6ffa7d4cc5f84b0b24ea525974cc3557c6
		return false;
	});




}(jQuery));