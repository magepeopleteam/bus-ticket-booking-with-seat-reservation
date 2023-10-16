<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$start_route = isset($_POST['bus_start_route']) ? MP_Global_Function::data_sanitize($_POST['bus_start_route']) : '';
	$end_route = isset($_POST['bus_end_route']) ? MP_Global_Function::data_sanitize($_POST['bus_end_route']) : '';
	$j_date = $j_date = $_POST['j_date'] ?? '';
	$r_date = $r_date = $_POST['r_date'] ?? '';
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates();
?>
	<div class="mpStyle" id="wbtm_area">
		<?php require WBTM_Functions::template_path('layout/search_form_only.php'); ?>
		
		<?php if ($start_route && $end_route && $j_date) { ?>
			<div class="_dLayout_dShadow_1">
				<?php WBTM_Layout::next_date_suggestion($all_dates); ?>
				<?php WBTM_Layout::route_title(); ?>
				<?php do_action('wbtm_search_result',$start_route,$end_route,$j_date); ?>
				<div class="wbtm_search_part _mT_xs">
					<?php //mage_bus_search_list(false); ?>
				</div>
			</div>
		<?php } ?>
		
		<?php if ($start_route && $end_route && $r_date) { ?>
			<div class="_dLayout_dShadow_1" id="wbtm_return_container">
				<h4 class="textCenter"><?php echo WBTM_Translations::text_return_trip(); ?></h4>
				<div class="divider"></div>
				<?php WBTM_Layout::next_date_suggestion($all_dates, true); ?>
				<?php WBTM_Layout::route_title(true); ?>
				<?php do_action('wbtm_search_result',$end_route,$start_route,$r_date); ?>
				<div class="wbtm_search_part _mT_xs">
					<?php //mage_bus_search_list(true); ?>
				</div>
			</div>
		<?php } ?>
	</div>
<?php
//do_action('wbtm_after_search_list');