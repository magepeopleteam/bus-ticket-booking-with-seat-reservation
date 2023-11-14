<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	//================//
	$post_id = $post_id ?? 0;
	$return_date_show = MP_Global_Function::get_settings('wbtm_general_settings', 'bus_return_show', 'enable');
	//================//
	$form_style = $form_style ?? '';
	$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
	//================//
	$buy_ticket_text = WBTM_Translations::text_buy_ticket();
	$placeholder_text = WBTM_Translations::text_please_select();
	//echo '<pre>'; print_r(WBTM_Functions::get_all_dates()); echo '</pre>';
?>
	<div id="wbtm_area">
		<div class="_dLayout_dShadow_1 wbtm_search_area <?php echo esc_attr($form_style_class); ?>">
			<?php if ($buy_ticket_text) { ?>
				<h4><?php echo esc_html($buy_ticket_text); ?></h4>
			<?php } ?>
			<div class="mpForm">
				<input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
				<div class="inputList mp_input_select wbtm_start_point">
					<label class="fdColumn">
						<span><i class="fas fa-map-marker"></i> <?php echo WBTM_Translations::text_from(); ?> : </span>
						<input type="text" class="formControl" name="bus_start_route" value="" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
					</label>
					<?php WBTM_Layout::route_list($post_id); ?>
				</div>
				<div class="inputList mp_input_select wbtm_dropping_point" data-alert="<?php echo WBTM_Translations::text_select_wrong_route(); ?>">
					<label class="fdColumn ">
						<span><i class="fas fa-map-marker"></i> <?php echo esc_html(WBTM_Translations::text_to()); ?> : </span>
						<input type="text" class="formControl" name="bus_end_route" value="" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
					</label>
					<?php WBTM_Layout::route_list($post_id); ?>
				</div>
				<div class="inputList wbtm_journey_date">
					<?php WBTM_Layout::journey_date_picker(); ?>
				</div>
				<?php if ($return_date_show == 'enable' && $post_id == 0) { ?>
					<div class="inputList wbtm_return_date">
						<?php WBTM_Layout::return_date_picker(); ?>
					</div>
				<?php } ?>
				<div class="inputList">
					<div class="fdColumn justifyBetween fullHeight">
						<span>&nbsp;</span>
						<button type="button" class="_themeButton_radius get_wbtm_bus_list">
							<span class="fas fa-search mR_xs"></span><?php echo WBTM_Translations::text_search(); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<div class="wbtm_search_result"></div>
	</div>
<?php
//do_action('wbtm_after_search_list');