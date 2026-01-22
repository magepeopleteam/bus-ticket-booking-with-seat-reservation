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

?>
	<div class="_dLayout_dShadow_1">
		<div class="flexWrap">
			<div class="col_6 col_12_700">
				<div class="mR">
<!--					--><?php //WBTM_Custom_Layout::bg_image($post_id); ?>
					<?php WBTM_Custom_Layout::bg_image_new($post_id); ?>
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
						<strong><?php echo esc_html( WBTM_Translations::text_coach_type() ); ?> :</strong>
						<?php echo esc_html( WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category') ); ?>
					</h6>
					<h6>
						<strong><?php echo esc_html( WBTM_Translations::text_passenger_capacity() ); ?> :</strong>
						<?php echo esc_html( WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat', 0) ); ?>
					</h6>
					<div class="mp_wp_editor">
						<?php the_content(); ?>
					</div>
				</div>
				<div class="flexEqual">
					<div class="dLayout_xs mR_xs">
						<h5><?php echo esc_html( WBTM_Translations::text_bp() ); ?></h5>
						<div class="divider"></div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<ul class="mp_list">
								<?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                foreach ($full_route_infos as $full_route_info) { ?>
									<?php if ($full_route_info['type'] == 'bp' || $full_route_info['type'] == 'both') { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span>
											<?php echo esc_html($full_route_info['place']) . ' (' . esc_html( WBTM_Global_Function::date_format($full_route_info['time'], 'time') . ')' ); ?>
										</li>
									<?php } ?>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>
					<div class="dLayout_xs">
						<h5><?php echo esc_html( WBTM_Translations::text_dp() ); ?></h5>
						<div class="divider"></div>
						<?php if (sizeof($full_route_infos) > 0) { ?>
							<ul class="mp_list">
								<?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                foreach ($full_route_infos as $full_route_info) { ?>
									<?php if ($full_route_info['type'] == 'dp' || $full_route_info['type'] == 'both') { ?>
										<li>
											<span class="fa fa-map-marker _mR_xs_textTheme"></span>
											<?php echo esc_html($full_route_info['place']) . ' (' . esc_html( WBTM_Global_Function::date_format($full_route_info['time'], 'time') . ')' ); ?>
										</li>
									<?php } ?>
								<?php } ?>
							</ul>
						<?php } ?>
					</div>

				</div>
                <?php if( !empty( $feature_lists ) ){?>
                    <div class="wtbm_term_wrapper">

                        <h4><?php echo esc_html( WBTM_Translations::text_features() );?></h4>
                        <?php foreach ( $feature_lists as $key => $value ){
                            ?>
                            <div class="wbtm_bus_feature_items">
                                <div class="wtbm_term_content">
                                    <span><?php echo esc_attr( $value['name'] );?></span>
                                </div>
                            </div>
                        <?php }?>
                    </div>
                <?php }?>
                <?php if( !empty( $selected_term_condition ) ){?>
                <div class="wtbm_term_wrapper">

                    <h4><?php echo esc_html( WBTM_Translations::text_term_condition() );?></h4>
                    <?php foreach ( $selected_term_condition as $key => $value ){
                        ?>
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

		</div>
	</div>
<?php
