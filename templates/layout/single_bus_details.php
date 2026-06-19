<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$post_id = $post_id ?? get_the_id();
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$full_route_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$bus_id = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_no');

    $coach_type     = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category');
    $total_seat     = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0);

    $all_term_condition = get_option( 'wbtm_term_condition_list', [] );
    $added_term_condition = get_post_meta( $post_id, 'wbtm_term_condition_list', true );
    $selected_term_condition = [];
    if (!empty($added_term_condition) && !empty( $all_term_condition ) ) {
        foreach ($added_term_condition as $term_key) {
            if (isset($all_term_condition[$term_key])) {
                $selected_term_condition[$term_key] = $all_term_condition[$term_key];
            }
        }
    }

    $all_features = WTBM_Features_Seating::get_all_bus_features();
    $selected_feature_ids = get_post_meta( $post_id, 'wbbm_bus_features_term_id', true );
    $feature_lists = WBTM_Functions::getSelectedFeatures( $all_features, $selected_feature_ids );

//    error_log( print_r( [ '$feature_lists' => $feature_lists ], true ) );

    /*
     * Return journey route. When same-bus return is enabled we show a separate
     * Boarding/Dropping section for the return leg. Prefer a custom return
     * timetable (wbtm_return_route_info); otherwise fall back to the reversed
     * outbound route (bp/dp swapped) — mirroring the search logic.
     */
    $return_route_infos = [];
    if ( WBTM_Functions::is_same_bus_return_enabled( $post_id ) ) {
        $custom_return = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_return_route_info', [] );
        if ( is_array( $custom_return ) && count( $custom_return ) > 1 ) {
            $return_route_infos = $custom_return;
        } else {
            $return_route_infos = WBTM_Functions::reverse_wbtm_route_infos( $full_route_infos );
        }
    }

?>
	<div class="wbtm_single_modern_card">

		<!-- ── TOP: SLIDER + SERVICE INFO ── -->
		<div class="wbtm_sm_top">

			<!-- IMAGE SLIDER -->
			<div class="wbtm_sm_slider">
				<?php WBTM_Custom_Layout::bg_image_new($post_id); ?>
			</div>

			<!-- SERVICE INFO -->
			<div class="wbtm_sm_info">
				<div class="wbtm_sm_header">
					<h4 class="wbtm_sm_name">
						<?php echo esc_html( get_the_title( $post_id ) ); ?>
						<?php if ($bus_id) { ?>
							<span class="wbtm_sm_code">( <?php echo esc_html($bus_id); ?> )</span>
						<?php } ?>
					</h4>
					<div class="wbtm_sm_tags">
						<?php if ($coach_type) { ?>
							<span class="wbtm_sm_tag wbtm_sm_tag_gray">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="13" rx="2"/><path d="M3 11h18"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="16.5" cy="18" r="1.5"/></svg>
								<?php echo esc_html($coach_type); ?>
							</span>
						<?php } ?>
						<span class="wbtm_sm_tag wbtm_sm_tag_blue">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
							<?php
								/* translators: %s: number of passenger seats */
								echo esc_html( sprintf( _n( '%s Passenger', '%s Passengers', (int) $total_seat, 'bus-ticket-booking-with-seat-reservation' ), number_format_i18n( (int) $total_seat ) ) );
							?>
						</span>
						<span class="wbtm_sm_tag wbtm_sm_tag_green">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
							<?php esc_html_e( 'Available', 'bus-ticket-booking-with-seat-reservation' ); ?>
						</span>
					</div>
				</div>

				<div class="wbtm_sm_routes">
					<!-- Boarding -->
					<div class="wbtm_sm_route_box wbtm_sm_boarding">
						<div class="wbtm_sm_route_title">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></svg>
							<?php echo esc_html( WBTM_Translations::text_bp() ); ?>
						</div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<?php
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							foreach ($full_route_infos as $full_route_info) { ?>
								<?php if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') { ?>
									<div class="wbtm_sm_stop">
										<span class="wbtm_sm_pin"></span>
										<span class="wbtm_sm_stop_name"><?php echo esc_html($full_route_info['place']); ?></span>
										<span class="wbtm_sm_stop_time"><?php echo esc_html( WBTM_Global_Function::date_format($full_route_info['time'], 'time') ); ?></span>
									</div>
								<?php } ?>
							<?php } ?>
						<?php } ?>
					</div>

					<!-- Dropping -->
					<div class="wbtm_sm_route_box wbtm_sm_dropping">
						<div class="wbtm_sm_route_title">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
							<?php echo esc_html( WBTM_Translations::text_dp() ); ?>
						</div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<?php
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							foreach ($full_route_infos as $full_route_info) { ?>
								<?php if ($full_route_info['type'] == 'dp' || $full_route_info['type'] == 'both') { ?>
									<div class="wbtm_sm_stop">
										<span class="wbtm_sm_pin"></span>
										<span class="wbtm_sm_stop_name"><?php echo esc_html($full_route_info['place']); ?></span>
										<span class="wbtm_sm_stop_time"><?php echo esc_html( WBTM_Global_Function::date_format($full_route_info['time'], 'time') ); ?></span>
									</div>
								<?php } ?>
							<?php } ?>
						<?php } ?>
					</div>
				</div>

				<?php if ( ! empty( $return_route_infos ) ) { ?>
				<!-- ── RETURN JOURNEY ── -->
			<div class="wbtm_sm_return_section">
				<div class="wbtm_sm_return_head">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
					<?php esc_html_e( 'Return Journey', 'bus-ticket-booking-with-seat-reservation' ); ?>
				</div>
				<div class="wbtm_sm_routes">
					<!-- Return Boarding -->
					<div class="wbtm_sm_route_box wbtm_sm_boarding">
						<div class="wbtm_sm_route_title">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></svg>
							<?php echo esc_html( WBTM_Translations::text_bp() ); ?>
						</div>
						<?php
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						foreach ( $return_route_infos as $return_route_info ) {
							if ( ! is_array( $return_route_info ) || empty( $return_route_info['type'] ) ) {
								continue;
							}
							if ( 'bp' === $return_route_info['type'] || 'both' === $return_route_info['type'] ) { ?>
								<div class="wbtm_sm_stop">
									<span class="wbtm_sm_pin"></span>
									<span class="wbtm_sm_stop_name"><?php echo esc_html( $return_route_info['place'] ); ?></span>
									<span class="wbtm_sm_stop_time"><?php echo esc_html( WBTM_Global_Function::date_format( $return_route_info['time'], 'time' ) ); ?></span>
								</div>
							<?php }
						} ?>
					</div>

					<!-- Return Dropping -->
					<div class="wbtm_sm_route_box wbtm_sm_dropping">
						<div class="wbtm_sm_route_title">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
							<?php echo esc_html( WBTM_Translations::text_dp() ); ?>
						</div>
						<?php
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
						foreach ( $return_route_infos as $return_route_info ) {
							if ( ! is_array( $return_route_info ) || empty( $return_route_info['type'] ) ) {
								continue;
							}
							if ( 'dp' === $return_route_info['type'] || 'both' === $return_route_info['type'] ) { ?>
								<div class="wbtm_sm_stop">
									<span class="wbtm_sm_pin"></span>
									<span class="wbtm_sm_stop_name"><?php echo esc_html( $return_route_info['place'] ); ?></span>
									<span class="wbtm_sm_stop_time"><?php echo esc_html( WBTM_Global_Function::date_format( $return_route_info['time'], 'time' ) ); ?></span>
								</div>
							<?php }
						} ?>
					</div>
				</div>
			</div>
		<?php } ?>
			</div>
		</div>

		<?php
			$bus_description = get_post_field( 'post_content', $post_id );
			if ( trim( wp_strip_all_tags( $bus_description ) ) !== '' ) { ?>
			<div class="wbtm_sm_description mp_wp_editor">
				<?php echo apply_filters( 'the_content', $bus_description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core the_content filter handles escaping ?>
			</div>
		<?php } ?>

		<?php if( !empty( $feature_lists ) ){?>
			<div class="wtbm_term_wrapper wbtm_sm_section">
				<h4><?php esc_html_e( 'Features', 'bus-ticket-booking-with-seat-reservation' );?></h4>
				<div class="wbtm_sm_feature_grid">
					<?php foreach ( $feature_lists as $key => $value ){ ?>
						<div class="wbtm_bus_feature_items">
							<div class="wtbm_term_content">
								<span><?php echo esc_attr( $value['name'] );?></span>
							</div>
						</div>
					<?php }?>
				</div>
			</div>
		<?php }?>

		<?php if( !empty( $selected_term_condition ) ){?>
			<div class="wtbm_term_wrapper wbtm_sm_section">
				<h4><?php esc_html_e( 'Terms & Condition', 'bus-ticket-booking-with-seat-reservation' );?></h4>
				<?php foreach ( $selected_term_condition as $key => $value ){ ?>
					<div class="wtbm_term_item">
						<div class="wtbm_term_header">
							<h5 class="wtbm_term_title"><?php echo esc_html( $value['title'] );?></h5>
						</div>
						<div class="wtbm_term_content">
							<p><?php echo wp_kses_post( $value['answer'] );?></p>
						</div>
					</div>
				<?php }?>
			</div>
		<?php }?>
	</div>
<?php
