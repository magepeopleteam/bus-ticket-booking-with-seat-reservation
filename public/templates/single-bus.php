<?php
	get_header();
	the_post();
	$post_id = get_the_id();
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates($post_id);
	$values = get_post_custom($post_id);
	$bus_id = $values['wbtm_bus_no'][0];
	$label = WBTM_Functions::get_name();
	$start_route = isset($_GET['bus_start_route']) ? MP_Global_Function::data_sanitize($_GET['bus_start_route']) : '';
	$end_route = isset($_GET['bus_end_route']) ? MP_Global_Function::data_sanitize($_GET['bus_end_route']) : '';
	$j_date = $_GET['j_date'] ?? '';
	$j_date = $j_date ? date('Y-m-d', strtotime($j_date)) : '';
	$seat_price = WBTM_Functions::get_seat_price($post_id, $start_route, $end_route);
	$start_stops = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_bp_stops', []);
	$end_stops = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_next_stops', []);
	do_action('wbtm_before_single_bus_search_page');
	do_action('woocommerce_before_single_product');
?>
	<div class="mpStyle">
		<div class="_dLayout_dShadow_3">
			<div class="flexWrap">
				<div class="col_6 col_12_700">
					<?php MP_Custom_Layout::bg_image($post_id); ?>
				</div>
				<div class="col_6 col_12_700">
					<div class="dLayout_xs">
						<h4>
							<?php the_title(); ?>
							<?php if ($bus_id) { ?>
								<small>( <?php echo esc_html($bus_id); ?> )</small>
							<?php } ?>
						</h4>
						<div class="divider"></div>
						<h6>
							<strong><?php echo esc_html($label) . ' ' . esc_html__('Type :', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
							<?php echo WBTM_Functions::get_bus_type($post_id); ?>
						</h6>
						<h6>
							<strong><?php esc_html_e('Passenger Capacity :', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
							<?php echo WBTM_Functions::get_total_seat($post_id); ?>
						</h6>
						<?php if ($start_route && $end_route && $j_date && $seat_price) { ?>
							<h6>
								<strong><?php esc_html_e('Fare :', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
								<?php echo wc_price($seat_price); ?>
								<small>/<?php esc_html_e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></small>
							</h6>
						<?php } ?>
						<div class="mp_wp_editor">
							<?php the_content(); ?>
						</div>
					</div>
					<div class="flexEqual">
						<div class="dLayout_xs mR_xs">
							<h5><?php esc_html_e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
							<div class="divider"></div>
							<?php if (sizeof($start_stops) > 0) { ?>
								<ul class="mp_list">
									<?php foreach ($start_stops as $start_stop) { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span>
											<?php echo esc_html($start_stop['wbtm_bus_bp_stops_name']).' ('.MP_Global_Function::date_format($start_stop['wbtm_bus_bp_start_time'],'time').' )'; ?>
										</li>
									<?php } ?>
								</ul>
							<?php } ?>
						</div>
						<div class="dLayout_xs">
							<h5><?php esc_html_e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
							<div class="divider"></div>
							<?php if (sizeof($end_stops) > 0) { ?>
								<ul class="mp_list">
									<?php foreach ($end_stops as $end_stop) { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span><?php echo esc_html($end_stop['wbtm_bus_next_stops_name']); ?>
										</li>
									<?php } ?>
								</ul>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php require WBTM_Functions::template_path('search_form_only.php'); ?>
		<?php if ($start_route && $end_route && $j_date) { ?>
			<div class="_dLayout_dShadow_3">
				<?php WBTM_Layout::next_date_suggestion($all_dates, false, $post_id); ?>
			</div>
			<div class="_dLayout_dShadow_3">
				<?php if ($seat_price) { ?>
					<?php mage_bus_search_item(false, $post_id); ?>
				<?php } else { ?>
					<h3><?php echo esc_html__("This", 'bus-ticket-booking-with-seat-reservation') . ' ' . $label . ' ' . esc_html__("isn't available on this search criteria, Please try", 'bus-ticket-booking-with-seat-reservation'); ?></h3>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
<?php
	do_action('wbtm_after_single_bus_search_page');
	get_footer();