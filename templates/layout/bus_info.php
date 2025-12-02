<?php
/*
* Author: engr.sumonazma@gmail.com
* Copyright: mage-people.com
*/

if (!defined('ABSPATH')) {
    die; // Cannot access pages directly.
}

//if( isset( $_POST['nonce'] ) && wp_verify_nonce(  sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),'wtbm_ajax_nonce' ) ){

$post_id = $post_id ?? '';
$start_route = $start_route ?? '';
$end_route = $end_route ?? '';
$date = $date ?? '';
//$date = $_POST['date'] ?? '';
$all_info = $all_info ?? WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route);
$seat_price = $seat_price ?? WBTM_Functions::get_seat_price($post_id, $start_route, $end_route);
?>
<table>
    <tbody>
        <tr>
            <th>
                <span class="fas fa-map-marker-alt"></span>
                <?php echo esc_html( WBTM_Translations::text_bp() ); ?>
            </th>
            <td>
                <h6><?php echo esc_html($all_info['bp']); ?></h6>
                <?php echo esc_html($all_info['dp_time'] ? WBTM_Global_Function::date_format($all_info['bp_time'], 'full') : ''); ?>
            </td>
        </tr>
        <tr>
            <th>
                <span class="fas fa-map-marker-alt"></span>
                <?php echo esc_html( WBTM_Translations::text_dp() ); ?>
            </th>
            <td>
                <h6><?php echo esc_html($all_info['dp']); ?></h6>
                <?php echo esc_html($all_info['dp_time'] ? WBTM_Global_Function::date_format($all_info['dp_time'], 'full') : ''); ?>
            </td>
        </tr>
        <?php if ($all_info['start_point'] != $all_info['bp']) { ?>
            <tr>
                <th>
                    <span class="fas fa-map-marker-alt"></span>
                    <?php echo esc_html( WBTM_Translations::text_start_point() ); ?>
                </th>
                <td>
                    <h6><?php echo esc_html($all_info['start_point']); ?></h6>
                    <?php echo esc_html($all_info['start_time'] ? WBTM_Global_Function::date_format($all_info['start_time'], 'full') : ''); ?>
                </td>
            </tr>
        <?php } ?>
        <tr>
            <th>
                <span class="fa fa-calendar"></span>
                <?php echo esc_html( WBTM_Translations::text_date() ); ?>
            </th>
            <td><?php echo esc_html( WBTM_Global_Function::date_format($date) ); ?></td>
        </tr>
        <tr>
            <th>
                <span class="fas fa-bus"></span>
                <?php echo esc_html( WBTM_Translations::text_coach_type() ); ?>
            </th>
            <td>
                <?php 
                $bus_type = esc_html( WBTM_Functions::synchronize_bus_type($post_id) );
                echo esc_html( $bus_type );
                ?>
            </td>
        </tr>
        <tr>
            <th>
                <span class="fas fa-money-bill"></span>
                <?php echo esc_html( WBTM_Translations::text_fare() ); ?>
            </th>
            <td>
                <?php echo wp_kses_post( wc_price($seat_price ) ); ?>
                <small>/<?php echo esc_html( WBTM_Translations::text_seat() ); ?></small>
            </td>
        </tr>
    </tbody>
</table>
<?php
//}
