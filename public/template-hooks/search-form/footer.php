<?php
add_action('wbtm_after_search_form_fields','wbtm_form_footer_script');
function wbtm_form_footer_script(){
    ?>
	<script>
			<?php if(isset($_GET['bus-r']) && $_GET['bus-r']=='oneway'){ ?>
					jQuery('.return-date-sec').hide();
			<?php }elseif(isset($_GET['bus-r']) && $_GET['bus-r']=='return'){ ?>
					jQuery('.return-date-sec').show();
			<?php }else{ ?>
					jQuery('.return-date-sec').hide();
			<?php } ?>
					jQuery('#oneway').on('click', function () {
					    jQuery('.return-date-sec').hide();
					}); 
					jQuery('#return_date').on('click', function () {
					    jQuery('.return-date-sec').show();
					});      
	</script>
    <?php
}