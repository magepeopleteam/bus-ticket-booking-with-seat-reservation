<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$start_route = isset($_GET['bus_start_route']) ? MP_Global_Function::data_sanitize($_GET['bus_start_route']) : '';
	$end_route = isset($_GET['bus_end_route']) ? MP_Global_Function::data_sanitize($_GET['bus_end_route']) : '';
	$j_date = $j_date = $_GET['j_date'] ?? '';
	$r_date = $r_date = $_GET['r_date'] ?? '';
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates();
?>
	<div class="mpStyle">
		<?php require WBTM_Functions::template_path('search_form_only.php'); ?>
		
		<?php if ($start_route && $end_route && $j_date) { ?>
			<div class="_dLayout_dShadow_3">
				<?php
					WBTM_Layout::next_date_suggestion($all_dates);
					WBTM_Layout::route_title();
					echo '<div class="wbtm_search_part _mT_xs">';
					mage_bus_search_list(false);
					echo '</div>'; ?>
			</div>
		<?php } ?>
		
		<?php if ($start_route && $end_route && $r_date) { ?>
			<div class="_dLayout_dShadow_3">
				<?php
					$return_trip_text = mage_bus_label('wbtm_return_trip_text_heading', __('Return Trip', 'bus-ticket-booking-with-seat-reservation'), true) . ':';
					echo '<p style="margin:40px 0 7px;color: #587275;text-decoration: underline;font-family: sans-serif;text-align:center;font-size: 1.8em!important;">' . $return_trip_text . '</p>';
					WBTM_Layout::next_date_suggestion($all_dates, true);
					WBTM_Layout::route_title(true);
					echo '<div class="wbtm_search_part _mT_xs" id="wbtm_return_container">';
					mage_bus_search_list(true);
					echo '</div>'; ?>
			</div>
		<?php } ?>
	</div>
<?php
//do_action('wbtm_after_search_list');