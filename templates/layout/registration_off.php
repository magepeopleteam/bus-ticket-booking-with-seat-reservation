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
                foreach ($pickup_points as $resgistration_pickup_point) {
                    if ($resgistration_pickup_point['bp_point'] == $start_route) {
                        $resgistration_pickup_infos = $resgistration_pickup_point['pickup_info'];
                        if (sizeof($resgistration_pickup_infos) > 0) {
                            ?>
                            <h6><?php echo esc_html( WBTM_Translations::text_pickup_point() ); ?></h6>
                            <div class="divider"></div>
                            <h4 class="textTheme"><i class="fas fa-map-marker-alt"></i>  <?php echo esc_html($start_route); ?></h4>
                            <div class="wbtm_pickup_poin pickup-point">
                                <?php foreach ($resgistration_pickup_infos as $pickup_info) { ?>
                                    <?php $pickup_time = gmdate('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
                                    <?php $pickup_time = WBTM_Global_Function::date_format($pickup_time, 'time'); ?>
                                    <div class="point"><i class="far fa-dot-circle"></i> <?php echo esc_html($pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($pickup_info['pickup_point']); ?> </div>
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
            $display_drop_off_point = WBTM_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
            $drop_off_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
            if ($display_drop_off_point == 'yes' && sizeof($drop_off_points) > 0) {
                $date = $date ?? '';
                $end_route = $end_route ?? '';

                foreach ($drop_off_points as $drop_registration_off_point) {
                    if ($drop_registration_off_point['dp_point'] == $end_route) {
                        $pickup_infos = $drop_registration_off_point['drop_off_info'];
                        if (sizeof($pickup_infos) > 0) {
                            ?>
                            <h6><?php echo esc_html( WBTM_Translations::text_drop_off_point() ); ?></h6>
                            <div class="divider"></div>
                            <h4 class="textTheme"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($end_route); ?></h4>
                            <div class="wbtm_pickup_point drop-off-point">

                                <?php foreach ($pickup_infos as $pickup_info) { ?>
                                    <?php $pickup_time = gmdate('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
                                    <?php $pickup_time = WBTM_Global_Function::date_format($pickup_time, 'time'); ?>
                                    <div class="point"><i class="far fa-dot-circle"></i> <?php echo esc_html($pickup_time); ?><span class="fas fa-long-arrow-alt-right _mR_xs_mL_xs"></span><?php echo esc_html($pickup_info['drop_off_point']); ?> </div>

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