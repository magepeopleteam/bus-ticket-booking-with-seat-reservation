<?php
/*
 * @Author        engr.sumonazma@gmail.com
 * Copyright:     mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if( isset( $_POST['nonce'] ) && wp_verify_nonce(  sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),'wtbm_ajax_nonce' ) ){

    $post_id = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
    $display_pickup_point = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
    $pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
    $pickup_required = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point_required', 'no');

    if ($display_pickup_point == 'yes' && sizeof($pickup_points) > 0) {
        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $start_route = isset($_POST['start_route']) ? sanitize_text_field( wp_unslash($_POST['start_route'] ) ) : '';
        $end_route = isset($_POST['end_route']) ? sanitize_text_field( wp_unslash($_POST['start_route'] ) ) : '';

        foreach ($pickup_points as $pickup_point) {
            if ($pickup_point['bp_point'] == $start_route) {
                $pickup_infos = $pickup_point['pickup_info'];
                if (sizeof($pickup_infos) > 0) {
                    ?>
                    <div class="wbtm_pickup_point _bgLight padding_xs mB mT">
                        <label class="justifyBetween">
                            <span class="_mR"><?php echo esc_html( WBTM_Translations::text_pickup_point() ); ?></span>
                            <select class="formControl" name="wbtm_pickup_point" id="wbtm_pickup_point" <?php echo ($pickup_required == 'yes') ? 'required' : ''; ?>>
                                <option selected value=""><?php echo esc_html( WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_pickup_point() ); ?></option>
                                <?php foreach ($pickup_infos as $pickup_info) { ?>
                                    <?php $pickup_time = gmdate('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
                                    <?php $pickup_time = WBTM_Global_Function::date_format($pickup_time, 'time'); ?>
                                    <option value="<?php echo esc_attr($pickup_info['pickup_point'] . ' ' . esc_html($pickup_time ) ) ?>"><?php echo esc_html($pickup_info['pickup_point']) . ' ' . ' (' . esc_html($pickup_time) . ')'; ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>
                    <script>
                        // Ensure a pickup point is selected when required
                        document.querySelector('form').addEventListener('submit', function (e) {
                            var pickupRequired = <?php echo json_encode($pickup_required); ?>;
                            var pickupPoint = document.getElementById('wbtm_pickup_point').value;
                            if (pickupRequired === 'yes' && pickupPoint.trim() === '') {
                                e.preventDefault();
                                alert("<?php echo esc_html( WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_pickup_point() ); ?>");
                            }
                        });
                    </script>
                    <?php
                }
            }
        }
    }
}
?>
