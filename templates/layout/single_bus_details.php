<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? get_the_id();
	$full_route_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
	$bus_id = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_no');
?>
	<div class="_dLayout_dShadow_1">
		<div class="flexWrap">
			<div class="col_6 col_12_700">
				<div class="mR">
					<?php MP_Custom_Layout::bg_image($post_id); ?>
				</div>
			</div>
			<div class=" col_6 col_12_700">
				<div class="dLayout_xs">
					<h4>
						<?php the_title(); ?>
						<?php if ($bus_id) { ?>
							<small>( <?php echo esc_html($bus_id); ?> )</small>
						<?php } ?>
					</h4>
					<div class="divider"></div>
					<h6>
						<strong><?php echo WBTM_Translations::text_coach_type(); ?> :</strong>
						<?php echo MP_Global_Function::get_post_info($post_id, 'wbtm_bus_category'); ?>
					</h6>
					<h6>
						<strong><?php echo WBTM_Translations::text_passenger_capacity(); ?> :</strong>
						<?php echo MP_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0); ?>
					</h6>
					<div class="mp_wp_editor">
						<?php the_content(); ?>
					</div>
				</div>
				<div class="flexEqual">
					<div class="dLayout_xs mR_xs">
						<h5><?php echo WBTM_Translations::text_bp(); ?></h5>
						<div class="divider"></div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<ul class="mp_list">
								<?php foreach ($full_route_infos as $full_route_info) { ?>
									<?php if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span>
											<?php echo esc_html($full_route_info['place']) . ' (' . MP_Global_Function::date_format($full_route_info['time'], 'time') . ')'; ?>
										</li>
									<?php } ?>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
					<div class="dLayout_xs">
						<h5><?php echo WBTM_Translations::text_dp(); ?></h5>
						<div class="divider"></div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<ul class="mp_list">
								<?php foreach ($full_route_infos as $full_route_info) { ?>
									<?php if ($full_route_info['type'] == 'dp' || $full_route_info['type'] == 'both') { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span>
											<?php echo esc_html($full_route_info['place']) . ' (' . MP_Global_Function::date_format($full_route_info['time'], 'time') . ')'; ?>
										</li>
									<?php } ?>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
