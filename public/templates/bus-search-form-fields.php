<?php do_action('wbtm_before_search_form_fields'); ?>
                    <div class="search-fields">
					  <div class="fields-li">
						<?php do_action('wbtm_from_bus_stops_list'); ?>
					  </div>
					  <div class="fields-li">
					  	<?php do_action('wbtm_to_bus_stops_list'); ?>						 
					  </div>
					  <div class="fields-li">
					  	<?php do_action('wbtm_form_journey_date'); ?>				
					  </div>
					  <div class="fields-li return-date-sec">
						<?php do_action('wbtm_form_return_date'); ?>
					  </div> 													
					 <div class="fields-li">
					   <div class="search-radio-sec">
					   	<?php do_action('wbtm_form_journey_type_select'); ?>
						</div>
						<?php do_action('wbtm_form_submit_button'); ?>
					  </div>
					</div>
<?php do_action('wbtm_after_search_form_fields'); ?>