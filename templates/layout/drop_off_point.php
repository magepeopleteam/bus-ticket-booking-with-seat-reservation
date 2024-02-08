<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
$post_id = $post_id ?? MP_Global_Function::data_sanitize($_POST['post_id']);
$display_pickup_point = MP_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
$pickup_points = MP_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
if ($display_pickup_point == 'yes' && sizeof($pickup_points) > 0) {
    $date = $_POST['date'] ?? '';
    $end_route = $end_route ?? MP_Global_Function::data_sanitize($_POST['end_route']);
    //echo '<pre>'; print_r($pickup_points); echo '</pre>';
    foreach ($pickup_points as $pickup_point) {
        if ($pickup_point['dp_point'] == $end_route) {
            $pickup_infos = $pickup_point['drop_off_info'];
            if (sizeof($pickup_infos) > 0) {
                ?>
                <div class="wbtm_pickup_point _bgLight padding_xs">
                    <label class="justifyBetween">
                        <span class="_mR_xs"><?php echo WBTM_Translations::text_drop_off_point(); ?></span>
                        <select class="formControl" name="wbtm_drop_off_point">
                            <option selected value=" "><?php echo WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_drop_off_point(); ?></option>
                            <?php foreach ($pickup_infos as $pickup_info) { ?>
                                <?php $pickup_time = date('Y-m-d H:i', strtotime($date . ' ' . $pickup_info['time'])); ?>
                                <?php $pickup_time = MP_Global_Function::date_format($pickup_time, 'time'); ?>
                                <option value="<?php echo esc_attr($pickup_info['drop_off_point'] . ' ' . $pickup_time) ?>"><?php echo esc_html($pickup_info['drop_off_point']) . ' ' . ' (' . $pickup_time . ')'; ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
                <?php
            }
        }
    }
}