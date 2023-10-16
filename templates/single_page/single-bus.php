<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	get_header();
	the_post();
	$post_id = get_the_id();
	$values = get_post_custom($post_id);
	$bus_id = $values['wbtm_bus_no'][0];
	$label = WBTM_Functions::get_name();
	$start_route = isset($_POST['bus_start_route']) ? MP_Global_Function::data_sanitize($_POST['bus_start_route']) : '';
	$end_route = isset($_POST['bus_end_route']) ? MP_Global_Function::data_sanitize($_POST['bus_end_route']) : '';
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates($post_id);
	$j_date = $_POST['j_date'] ?? '';
	$j_date = $j_date ? date('Y-m-d', strtotime($j_date)) : '';
	$all_info = WBTM_Functions::get_bus_all_info($post_id, $j_date, $start_route, $end_route);
	$seat_price = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route);
	$start_stops = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_bp_stops', []);
	$end_stops = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_next_stops', []);
	do_action('wbtm_before_single_bus_search_page');
	do_action('woocommerce_before_single_product');
	//echo '<pre>';print_r($wp_roles->roles);echo '</pre>';
?>
	<div class="mpStyle" id="wbtm_area">
		<?php require WBTM_Functions::template_path('layout/single_bus_details.php'); ?>
		<?php require WBTM_Functions::template_path('layout/search_form_only.php'); ?>
		<?php if ($start_route && $end_route && $j_date) { ?>
			<div class="_dLayout_dShadow_1">
				<?php WBTM_Layout::next_date_suggestion($all_dates, false); ?>
			</div>
			<div class="_dLayout_dShadow_1">
				<?php if ($seat_price) { ?>
					<?php do_action('wbtm_search_result', $start_route, $end_route, $j_date, $post_id); ?>
				<?php } else { ?>
					<?php WBTM_Layout::msg(WBTM_Translations::text_serch_no_msg()); ?>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
<?php
	do_action('wbtm_after_single_bus_search_page');
	get_footer();