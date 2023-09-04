<?php
// Please Do not change this php variables::::::::::::::::::::::::::::::::::::::::
global $wbtmmain;
$bp_arr = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true));
$dp_arr = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true));
$price_arr = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_prices', true));
$total_dp = count($dp_arr) - 1;
$term = get_the_terms(get_the_id(), 'wbtm_bus_cat');

$count = 1;
$bp = '';
$dp = '';

foreach ($bp_arr as $_bp_arr) {
    if ($count == 1) {
        $bp = $_bp_arr['wbtm_bus_bp_stops_name'];
    }
    $count++;
}

$count = 0;
foreach ($dp_arr as $_dp_arr) {
    if ($count == $total_dp) {
        $dp = $_dp_arr['wbtm_bus_next_stops_name'];
    }
    $count++;
}
// You can change the html codes below::::::::::::::::::::::::::::::::::::::::::::::
?>
<div class="wbtm-bus-lists">
    <div class="bus-thumb">
        <?php the_post_thumbnail('full'); ?>
    </div>
    <div>
        <h2><?php the_title(); ?></h2>
        <ul>
            <li><strong>
                    <?php echo $wbtmmain->bus_get_option('wbtm_type_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_type_text', 'label_setting_sec') : _e('Type:', 'bus-ticket-booking-with-seat-reservation'); ?>
                </strong> <?php echo(isset($term[0]) ? $term[0]->name : ''); ?></li>
            <li><strong>
                    <?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('No', 'bus-ticket-booking-with-seat-reservation'); ?>:
                </strong> <?php echo get_post_meta(get_the_id(), 'wbtm_bus_no', true); ?></li>
            <li><strong>
                    <?php echo $wbtmmain->bus_get_option('wbtm_from_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_from_text', 'label_setting_sec') : _e('Start From:', 'bus-ticket-booking-with-seat-reservation'); ?>
                </strong> <?php echo $bp; ?> </li>
            <li><strong>
                    <?php echo $wbtmmain->bus_get_option('wbtm_end_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_end_text', 'label_setting_sec') : _e('End', 'bus-ticket-booking-with-seat-reservation'); ?>
                </strong> <?php echo $dp; ?>
            </li>
            <li><strong><?php echo $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') : _e('Fare', 'bus-ticket-booking-with-seat-reservation'); ?></strong> <?php echo get_woocommerce_currency_symbol() . $wbtmmain->wbtm_get_bus_price($bp, $dp, $price_arr); ?>
            </li>
        </ul>
        <a href="<?php the_permalink(); ?>" class='btn wbtm-bus-list-btn'>
            <?php echo $wbtmmain->bus_get_option('wbtm_book_now_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_book_now_text', 'label_setting_sec') : _e('Book Now', 'bus-ticket-booking-with-seat-reservation'); ?>
        </a>
    </div>
</div>