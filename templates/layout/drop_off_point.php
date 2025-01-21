<?php
/*
* @Author        engr.sumonazma@gmail.com
* Copyright:     mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

$post_id = $post_id ?? MP_Global_Function::data_sanitize($_POST['post_id']);
$display_drop_off_point = MP_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
$drop_off_points = MP_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
$drop_off_required = MP_Global_Function::get_post_info($post_id, 'wbtm_dropping_point_required', 'no');

if ($display_drop_off_point == 'yes' && sizeof($drop_off_points) > 0) {
    $date = $_POST['date'] ?? '';
    $end_route = $end_route ?? MP_Global_Function::data_sanitize($_POST['end_route']);

    foreach ($drop_off_points as $drop_off_point) {
        if ($drop_off_point['dp_point'] == $end_route) {
            $drop_off_infos = $drop_off_point['drop_off_info'];
            if (sizeof($drop_off_infos) > 0) {
                ?>
                <div class="wbtm_pickup_point _bgLight padding_xs">
                    <label class="justifyBetween">
                        <span class="_mR_xs"><?php echo WBTM_Translations::text_drop_off_point(); ?></span>
                        <select class="formControl" name="wbtm_drop_off_point" id="wbtm_drop_off_point" <?php echo ($drop_off_required == 'yes') ? 'required' : ''; ?>>
                            <option selected value=""><?php echo WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_drop_off_point(); ?></option>
                            <?php foreach ($drop_off_infos as $drop_off_info) { ?>
                                <?php $drop_off_time = date('Y-m-d H:i', strtotime($date . ' ' . $drop_off_info['time'])); ?>
                                <?php $drop_off_time = MP_Global_Function::date_format($drop_off_time, 'time'); ?>
                                <option value="<?php echo esc_attr($drop_off_info['drop_off_point'] . ' ' . $drop_off_time) ?>"><?php echo esc_html($drop_off_info['drop_off_point']) . ' ' . ' (' . $drop_off_time . ')'; ?></option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
                <script>
                    // Ensure a drop-off point is selected when required
                    document.querySelector('form').addEventListener('submit', function (e) {
                        var dropOffRequired = <?php echo json_encode($drop_off_required); ?>;
                        var dropOffPoint = document.getElementById('wbtm_drop_off_point').value;
                        if (dropOffRequired === 'yes' && dropOffPoint.trim() === '') {
                            e.preventDefault();
                            alert("<?php echo WBTM_Translations::text_please_select() . ' ' . WBTM_Translations::text_drop_off_point(); ?>");
                        }
                    });
                </script>
                <?php
            }
        }
    }
}
?>
