<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
//if( isset( $_POST['nonce'] ) && wp_verify_nonce(  sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),'wtbm_ajax_nonce' ) ){
	?>
    <div class="mpRow justifyBetween _dLayout">
        <?php
//        $post_id = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
        /*$display_pickup_point = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
        $pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);*/
        ?>
        <div class="col_5 col_5_1000 col_6_900 col_12_800">
            <?php
            if ($display_pickup_point == 'yes' && sizeof($pickup_points) > 0) {
               /* $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
                $start_route = isset( $_POST['start_route'] ) ? sanitize_text_field( wp_unslash( $_POST['start_route'] ) ) : '';
                $end_route = isset( $_POST['end_route'] ) ? sanitize_text_field( wp_unslash( $_POST['end_route'] ) ) : '';*/
                foreach ($pickup_points as $wbtm_resgistration_pickup_point) {
                    if ($wbtm_resgistration_pickup_point['bp_point'] == $start_route) {
                        $wbtm_resgistration_pickup_infos = $wbtm_resgistration_pickup_point['pickup_info'];
                        if (sizeof($wbtm_resgistration_pickup_infos) > 0) {
                            ?>
                            <h6><?php echo esc_html( WBTM_Translations::text_pickup_point() ); ?></h6>
                            <div class="divider"></div>
                            <h4 class="textTheme"><i class="fas fa-map-marker-alt"></i>  <?php echo esc_html($start_route); ?></h4>
                            <div class="wbtm_pickup_poin pickup-point">
                                <?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                foreach ($wbtm_resgistration_pickup_infos as $wtbm_resgistration_pickup_info) {
                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                    $registration_pickup_time = gmdate('Y-m-d H:i', strtotime($date . ' ' . $wtbm_resgistration_pickup_info['time']));
                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                    $registration_pickup_time = WBTM_Global_Function::date_format($registration_pickup_time, 'time'); ?>
                                    <div class="point"><i class="far fa-dot-circle"></i> <?php echo esc_html($registration_pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($wtbm_resgistration_pickup_info['pickup_point']); ?> </div>
                                <?php } ?>
                            </div>
                            <?php
                        }
                    }
                }
            }
            ?>
        </div>
        <div class="col_5 col_5_1000 col_6_900 col_12_800">
            <?php
            $wbtm_display_drop_off_point = WBTM_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
            $wbtm_drop_off_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
            if ($wbtm_display_drop_off_point == 'yes' && sizeof($wbtm_drop_off_points) > 0) {
                /*$date = $date ?? '';
                $end_route = $end_route ?? '';*/

                foreach ($wbtm_drop_off_points as $wbtm_drop_registration_off_point) {
                    if ($wbtm_drop_registration_off_point['dp_point'] == $end_route) {
                        $wbtm_reg_pickup_infos = $wbtm_drop_registration_off_point['drop_off_info'];
                        if (sizeof($wbtm_reg_pickup_infos) > 0) {
                            ?>
                            <h6><?php echo esc_html( WBTM_Translations::text_drop_off_point() ); ?></h6>
                            <div class="divider"></div>
                            <h4 class="textTheme"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($end_route); ?></h4>
                            <div class="wbtm_pickup_point drop-off-point">

                                <?php foreach ($wbtm_reg_pickup_infos as $wbtm_reg_pickup_info) { ?>
                                    <?php $wbtm_reg_pickup_time = gmdate('Y-m-d H:i', strtotime($date . ' ' . $wbtm_reg_pickup_info['time'])); ?>
                                    <?php $wbtm_reg_pickup_time = WBTM_Global_Function::date_format($wbtm_reg_pickup_time, 'time'); ?>
                                    <div class="point"><i class="far fa-dot-circle"></i> <?php echo esc_html($wbtm_reg_pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($wbtm_reg_pickup_info['drop_off_point']); ?> </div>

                                <?php } ?>

                            </div>
                            <?php
                        }
                    }
                }
            }
            ?>
        </div>
    </div>
<?php //}?>