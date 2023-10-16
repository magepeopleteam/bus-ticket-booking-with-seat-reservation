<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? 0;
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates($post_id);
	if (sizeof($all_dates) > 0) {
		$buy_ticket_text = WBTM_Translations::text_buy_ticket();
		$placeholder_text = WBTM_Translations::text_please_select();
		/**************/
		$start_route = isset($_POST['bus_start_route']) ? MP_Global_Function::data_sanitize($_POST['bus_start_route']) : '';
		/**************/
		$end_route = isset($_POST['bus_end_route']) ? MP_Global_Function::data_sanitize($_POST['bus_end_route']) : '';
		//================//
		$date_format = MP_Global_Function::date_picker_format('wbtm_general_settings');
		$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
		//================//
		$j_date = $_POST['j_date'] ?? '';
		$hidden_j_date = $j_date ? date('Y-m-d', strtotime($j_date)) : '';
		$visible_j_date = $j_date ? date_i18n($date_format, strtotime($j_date)) : '';
		//================//
		$r_date = $_POST['r_date'] ?? '';
		$return_date_show = MP_Global_Function::get_settings('wbtm_general_settings', 'bus_return_show', 'enable');
		$hidden_r_date = $r_date ? date('Y-m-d', strtotime($r_date)) : '';
		$visible_r_date = $r_date ? date_i18n($date_format, strtotime($r_date)) : '';
		//================//
		//echo '<pre>'; print_r(WBTM_Functions::get_all_dates()); echo '</pre>';
		$form_style = $form_style ?? '';
		$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
		?>
		<div class="_dLayout_dShadow_1 wbtm_search_area <?php echo esc_attr($form_style_class); ?>">
			<?php if ($buy_ticket_text) { ?>
				<h4><?php echo esc_html($buy_ticket_text); ?></h4>
			<?php } ?>
			<form action="" method="post" class="mpForm">
				<input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
				<div class="inputList mp_input_select wbtm_start_point">
					<label class="fdColumn">
						<span><i class="fas fa-map-marker"></i> <?php echo WBTM_Translations::text_from(); ?> : </span>
						<input type="text" class="formControl" name="bus_start_route" value="<?php echo esc_attr($start_route); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
					</label>
					<?php WBTM_Layout::route_list($post_id); ?>
				</div>
				<div class="inputList mp_input_select wbtm_dropping_point" data-alert="<?php echo WBTM_Translations::text_select_wrong_route(); ?>">
					<label class="fdColumn ">
						<span><i class="fas fa-map-marker"></i> <?php echo esc_html(WBTM_Translations::text_to()); ?> : </span>
						<input type="text" class="formControl" name="bus_end_route" value="<?php echo esc_attr($end_route); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
					</label>
					<?php WBTM_Layout::route_list($post_id, $start_route); ?>
				</div>
				<div class="inputList">
					<label class="fdColumn">
						<span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_journey_date(); ?></span>
						<input type="hidden" name="j_date" value="<?php echo esc_attr($hidden_j_date); ?>" required/>
						<input id="wbtm_journey_date" type="text" value="<?php echo esc_attr($visible_j_date); ?>" class="formControl " placeholder="<?php echo esc_attr($now); ?>" readonly required/>
					</label>
				</div>
				<?php if ($return_date_show == 'enable' && $post_id == 0) { ?>
					<div class="inputList">
						<label class="fdColumn">
							<span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_return_date(); ?></span>
							<input type="hidden" name="r_date" value="<?php echo esc_attr($hidden_r_date); ?>"/>
							<input id="wbtm_return_date" type="text" value="<?php echo esc_attr($visible_r_date); ?>" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
						</label>
					</div>
				<?php } ?>
				<div class="inputList">
					<div class="fdColumn justifyBetween fullHeight">
						<span>&nbsp;</span>
						<button type="submit" class="_themeButton_radius wbtm_get_bus_list">
							<span class="fas fa-search mR_xs"></span><?php echo WBTM_Translations::text_search(); ?>
						</button>
					</div>
				</div>
			</form>
			<?php //do_action('wbtm_after_search_form');
			?>
		</div>
		<?php
		do_action('mp_load_date_picker_js', '#wbtm_journey_date', $all_dates);
		do_action('mp_load_date_picker_js', '#wbtm_return_date', $all_dates);
	}
	else {
		WBTM_Layout::msg(WBTM_Translations::text_bus_close_msg());
	}