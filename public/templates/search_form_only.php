<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? 0;
	$form_style=$form_style??'';
	/**************/
	$search_path = $search_path ?? '';
	if ($search_path) {
		$target_page = $search_path;
	}
	else {
		$target_page = MP_Global_Function::get_settings('wbtm_bus_settings', 'search_target_page');
		$target_page = $target_page ? get_post_field('post_name', $target_page) : 'bus-search-list';
	}
	$form_url = $post_id>0 ?'': get_site_url() . '/' . $target_page;
	/**************/
	$buy_ticket_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_buy_ticket_text', esc_html__('BUY TICKET:', 'bus-ticket-booking-with-seat-reservation'));
	$placeholder_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_please_select_text', esc_html__('Please Select', 'bus-ticket-booking-with-seat-reservation'));
	/**************/
	$start_route = isset($_GET['bus_start_route']) ? MP_Global_Function::data_sanitize($_GET['bus_start_route']) : '';
	$from_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_from_text', esc_html__('From:', 'bus-ticket-booking-with-seat-reservation'));
	/**************/
	$end_route = isset($_GET['bus_end_route']) ? MP_Global_Function::data_sanitize($_GET['bus_end_route']) : '';
	$to_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_to_text', esc_html__('To:', 'bus-ticket-booking-with-seat-reservation'));
	//================//
	$date_format = MP_Global_Function::date_picker_format('wbtm_bus_settings');
	$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates($post_id);
	//================//
	$j_date = $_GET['j_date'] ?? '';
	$hidden_j_date = $j_date ? date('Y-m-d', strtotime($j_date)) : '';
	$visible_j_date = $j_date ? date_i18n($date_format, strtotime($j_date)) : '';
	$journey_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_date_of_journey_text', esc_html__('Date of Journey', 'bus-ticket-booking-with-seat-reservation'));
	//================//
	$r_date = $_GET['r_date'] ?? '';
	$return_date_show = MP_Global_Function::get_settings('wbtm_bus_settings', 'bus_return_show', 'enable');
	$hidden_r_date = $r_date ? date('Y-m-d', strtotime($r_date)) : '';
	$visible_r_date = $r_date ? date_i18n($date_format, strtotime($r_date)) : '';
	$return_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_return_date_text', esc_html__('Return Date (Optional)', 'bus-ticket-booking-with-seat-reservation'));
	//================//
	$submit_text = MP_Global_Function::get_settings('wbtm_bus_settings', 'wbtm_search_buses_text', esc_html__('Search', 'bus-ticket-booking-with-seat-reservation'));
//echo '<pre>';print_r(MP_Global_Function::get_post_info(2630, 'wbtm_bus_on_dates',array()));echo '</pre>';
	//echo '<pre>'; print_r(WBTM_Functions::get_all_dates()); echo '</pre>';
	$form_style_class=$form_style=='horizontal'?'inputHorizontal':'inputInline';
?>
	<div class="_dLayout_dShadow_3 wbtm_search_area <?php echo esc_attr($form_style_class); ?>">
		<?php if ($buy_ticket_text) { ?>
			<h4><?php echo esc_html($buy_ticket_text); ?></h4>
		<?php } ?>
		<form action="<?php echo esc_url($form_url); ?>" method="get" class="mpForm">
			<input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
			<div class="inputList mp_input_select wbtm_start_point">
				<label class="fdColumn">
					<span><i class="fas fa-map-marker"></i> <?php echo esc_html($from_text); ?></span>
					<input type="text" class="formControl" name="bus_start_route" value="<?php echo esc_attr($start_route); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
				</label>
				<?php WBTM_Layout::route_list('',$post_id); ?>
			</div>
			<div class="inputList mp_input_select wbtm_dropping_point" data-alert="<?php esc_attr_e('You select Wrong Route !', 'bus-ticket-booking-with-seat-reservation'); ?>">
				<label class="fdColumn ">
					<span><i class="fas fa-map-marker"></i> <?php echo esc_html($to_text); ?></span>
					<input type="text" class="formControl" name="bus_end_route" value="<?php echo esc_attr($end_route); ?>" placeholder="<?php echo esc_attr($placeholder_text); ?>" autocomplete="off" required/>
				</label>
				<?php //WBTM_Layout::route_list(); ?>
			</div>
			<div class="inputList">
				<label class="fdColumn">
					<span><i class="fas fa-calendar-alt"></i> <?php echo esc_html($journey_text); ?></span>
					<input type="hidden" name="j_date" value="<?php echo esc_attr($hidden_j_date); ?>" required/>
					<input id="wbtm_journey_date" type="text" value="<?php echo esc_attr($visible_j_date); ?>" class="formControl " placeholder="<?php echo esc_attr($now); ?>" readonly required/>
				</label>
			</div>
			<?php if ($return_date_show == 'enable' && $post_id ==0) { ?>
				<div class="inputList">
					<label class="fdColumn">
						<span><i class="fas fa-calendar-alt"></i> <?php echo esc_html($return_text); ?></span>
						<input type="hidden" name="r_date" value="<?php echo esc_attr($hidden_r_date); ?>"/>
						<input id="wbtm_return_date" type="text" value="<?php echo esc_attr($visible_r_date); ?>" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
					</label>
				</div>
			<?php } ?>
			<div class="inputList">
				<div class="fdColumn justifyBetween fullHeight">
					<span>&nbsp;</span>
					<button type="submit" class="_themeButton_radius wbtm_get_bus_list">
						<span class="fas fa-search mR_xs"></span><?php echo esc_html($submit_text); ?>
					</button>
				</div>
			</div>
		</form>
		<?php //do_action('wbtm_after_search_form'); ?>
	</div>
<?php
	do_action('mp_load_date_picker_js', '#wbtm_journey_date', $all_dates);
	do_action('mp_load_date_picker_js', '#wbtm_return_date', $all_dates);