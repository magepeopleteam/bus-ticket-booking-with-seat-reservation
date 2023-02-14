<?php
get_header();
the_post();
$id = get_the_id();
$values = get_post_custom($id);
/**
 * Hook: wbtm_before_single_bus_search_page.
 */
do_action('wbtm_before_single_bus_search_page');
?>
    <div class="mage mage_single_bus_search_page" data-busId="<?php echo $id; ?>">
        <?php do_action('woocommerce_before_single_product'); ?>
        <div class="post-content-wrap">
            <?php echo the_content();?>
        </div>
        <div class="mage_default">
            <div class="flexEqual">
                <?php $alt_image = (wp_get_attachment_url(mage_bus_setting_value('alter_image' )))?wp_get_attachment_url(mage_bus_setting_value('alter_image' )):'https://i.imgur.com/807vGSc.png'; ?>
                <div class="mage_xs_full"><?php echo has_post_thumbnail()?the_post_thumbnail('full'): "<img width='557' height='358' src=".$alt_image .">" ?></div>

                <div class="ml_25 mage_xs_full">
                    <div class="mage_default_bDot">
                        <h4><?php the_title(); ?><small>( <?php echo $values['wbtm_bus_no'][0]; ?> )</small></h4>
                        <h6 class="mar_t_xs"><strong><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Type', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong><?php echo mage_bus_type(); ?></h6>
                        <h6 class="mar_t_xs"><strong><?php _e('Passenger Capacity :', 'bus-ticket-booking-with-seat-reservation'); ?></strong><?php echo mage_bus_total_seat_new(); ?></h6>
                        <?php if (mage_bus_run_on_date(false) && isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) { ?>
                            <h6 class="mar_t_xs">
                                <span><?php _e('Fare :', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                <strong><?php echo wc_price(mage_bus_seat_price($id,mage_bus_isset('bus_start_route'), mage_bus_isset('bus_end_route'),false)); ?></strong>/
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
        <div class="mage_default mage_form_inline">

            <?php

            $global_target = $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec') ? get_post_field('post_name', $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec')) : 'bus-search-list';
            if(isset($params)) {
                $target = $params['search-page'] ? $params['search-page'] : $global_target;
            } else {
               $target = $global_target;
            }

            mage_bus_search_form_only(true, $target); ?>
        </div>
        <?php
        //  if (mage_bus_run_on_date(false) && isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) { 
         if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {
            
            $start = $_GET['bus_start_route'];
            $end = $_GET['bus_end_route'];
            $j_date = $_GET['j_date'];
            $j_date = mage_wp_date($j_date, 'Y-m-d');
            $r_date = isset($_GET['r_date']) ? $_GET['r_date'] : null;
            if($r_date) {
                $r_date = mage_wp_date($r_date, 'Y-m-d');
            }
            $check_has_price = mage_bus_seat_price($id, $start, $end, false);
            $has_bus = false;
            $has_bus_return = false;

            $bus_bp_array = get_post_meta($id, 'wbtm_bus_bp_stops', true) ? get_post_meta($id, 'wbtm_bus_bp_stops', true) : [];
            $bus_bp_array = maybe_unserialize($bus_bp_array);

            if($bus_bp_array) {
                $has_bus = mage_single_bus_show($id, $start, $end, $j_date, $bus_bp_array, $has_bus);
                if($r_date) {
                    $has_bus_return = mage_single_bus_show($id, $end, $start, $r_date, $bus_bp_array, true);
                }
            }

            // Final

             mage_next_date_suggestion_single(false, true, $target);


            if ($has_bus && $check_has_price !== '') {

                mage_bus_search_item(false, $id);

            } else {
                echo '<div class="wbtm-warnig">';
                _e("This", 'bus-ticket-booking-with-seat-reservation');
                echo ' '.mage_bus_setting_value('bus_menu_label', 'Bus').' ';
                _e("isn't available on this search criteria, Please try", 'bus-ticket-booking-with-seat-reservation');
                echo '</div>';
            }

            if($r_date) {
                if ($has_bus_return && $check_has_price) {
                    echo '<div class="wbtm_return_header">'.__("Return Trip", "bus-ticket-booking-with-seat-reservation").'</div>';
                    mage_bus_search_item(true, $id);
    
                } else {
                    echo '<div class="wbtm-warnig">';
                    _e("This", 'bus-ticket-booking-with-seat-reservation');
                    echo ' '.mage_bus_setting_value('bus_menu_label', 'Bus').' ';
                    _e('available only in the particular date. :) ', 'bus-ticket-booking-with-seat-reservation');
                    echo '</div>';
                }
            }
        } 
        ?>
    </div>

<?php
/**
 * Hook: wbtm_after_single_bus_search_page.
 */
do_action('wbtm_after_single_bus_search_page');
get_footer();