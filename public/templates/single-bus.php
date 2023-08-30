<?php
	get_header();
	the_post();
	$post_id = get_the_id();
	$all_dates = $all_dates ?? WBTM_Functions::get_all_dates($post_id);
	$values = get_post_custom($post_id);
	/**
	 * Hook: wbtm_before_single_bus_search_page.
	 */
	do_action('wbtm_before_single_bus_search_page');
?>
	<div class="mage mage_single_bus_search_page" data-busId="<?php echo $post_id; ?>">
		<?php do_action('woocommerce_before_single_product'); ?>
		<div class="post-content-wrap">
			<?php echo the_content(); ?>
		</div>
		<div class="mage_default">
			<div class="flexEqual">
				<?php $alt_image = (wp_get_attachment_url(mage_bus_setting_value('alter_image'))) ? wp_get_attachment_url(mage_bus_setting_value('alter_image')) : 'https://i.imgur.com/807vGSc.png'; ?>
				<div class="mage_xs_full"><?php echo has_post_thumbnail() ? the_post_thumbnail('full') : "<img width='557' height='358' src=" . $alt_image . ">" ?></div>
				<div class="ml_25 mage_xs_full">
					<div class="mage_default_bDot">
						<h4><?php the_title(); ?>
							<small>( <?php echo $values['wbtm_bus_no'][0]; ?> )</small>
						</h4>
						<h6 class="mar_t_xs">
							<strong><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Type', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong><?php echo mage_bus_type(); ?></h6>
						<h6 class="mar_t_xs">
							<strong><?php _e('Passenger Capacity :', 'bus-ticket-booking-with-seat-reservation'); ?></strong><?php echo mage_bus_total_seat_new(); ?></h6>
						<?php if (mage_bus_run_on_date(false) && isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) { ?>
							<h6 class="mar_t_xs">
								<span><?php _e('Fare :', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<strong><?php echo wc_price(mage_bus_seat_price($post_id, mage_bus_isset('bus_start_route'), mage_bus_isset('bus_end_route'), false)); ?></strong>
								/
								<span><?php _e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							</h6>
						<?php } ?>
					</div>
					<div class="flexEqual_mar_t mage_bus_drop_board">
						<div class="mage_default_bDot">
							<h5><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
							<ul class="mage_list mar_t_xs">
								<?php
									$start_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
									foreach ($start_stops as $route) {
										echo '<li><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_bp_stops_name'] . '</li>';
									}
								?>
							</ul>
						</div>
						<div class="mage_default_bDot">
							<h5><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
							<ul class="mage_list mar_t_xs">
								<?php
									$end_stops = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
									foreach ($end_stops as $route) {
										echo '<li><span class="fa fa-map-marker"></span>' . $route['wbtm_bus_next_stops_name'] . '</li>';
									}
								?>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="mpStyle">
			<?php require WBTM_Functions::template_path('search_form_only.php'); ?>
			<?php WBTM_Layout::next_date_suggestion($all_dates,false,$post_id); ?>
		</div>
		<?php
			if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {
				$start = $_GET['bus_start_route'];
				$end = $_GET['bus_end_route'];
				$check_has_price = mage_bus_seat_price($post_id, $start, $end, false);
				// Final
				if (sizeof($all_dates)>0 && $check_has_price !== '') {
					mage_bus_search_item(false, $post_id);
				}
				else {
					echo '<div class="wbtm-warnig">';
					_e("This", 'bus-ticket-booking-with-seat-reservation');
					echo ' ' . mage_bus_setting_value('bus_menu_label', 'Bus') . ' ';
					_e("isn't available on this search criteria, Please try", 'bus-ticket-booking-with-seat-reservation');
					echo '</div>';
				}
			}
		?>
	</div>
<?php
	do_action('wbtm_after_single_bus_search_page');
	get_footer();