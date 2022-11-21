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
						text: data.text
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


}(jQuery));