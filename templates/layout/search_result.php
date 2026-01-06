<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$search_info = $search_info ?? [];
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$start_route = $start_route ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$end_route = $end_route ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$post_id = $post_id ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$date = $date ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$journey_type = $journey_type ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$btn_show = $btn_show ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$left_filter_show = $left_filter_show ?? '';

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$label = WBTM_Functions::get_name();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
//echo '<pre>';	print_r($search_info);	echo '</pre>';
if (sizeof($bus_ids) > 0) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $bus_count = 0;

    // Collect all bus info first
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $bus_data = [];
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $bus_titles = [];
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $bus_types = [];
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $all_boarding_routes = [];
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    foreach ($bus_ids as $bus_id) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route);
        if (sizeof($all_info) > 0) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_data[] = [
                'bus_id'   => $bus_id,
                'all_info' => $all_info,
            ];
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_titles[] = get_the_title($bus_id);
            
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_types[] = $bus_type;
            
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $get_boarding_routes = WBTM_Functions::get_bus_route( $bus_id );
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            foreach ( $get_boarding_routes as $route ){
                if( !empty( $route ) ){
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                    $all_boarding_routes[] = $route;
                }
            }
        }

    }

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $all_boarding_routes = array_unique( $all_boarding_routes );

    if( $journey_type === 'start_journey' ){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $wbtm_bus_search = 'wbtm_bus_search_journey_start';
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $filter_by_box = 'filter-checkbox';
    }else{
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $wbtm_bus_search = 'wbtm_bus_search_journey_return';
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $filter_by_box = 'return_filter-checkbox';
    }

?>
<div class="wbtm_search_result_holder">
    <?php
    if( !empty($left_filter_show['left_filter_input']) && $left_filter_show['left_filter_input'] === 'on' && $left_filter_show['left_filter_input'] && count( $bus_titles ) > 0 ){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
     $width = 'calc( 100% - 180px )'
    ?>
    <div class="wbtm_bus_left_filter_holder">
      <?php
        echo wp_kses_post( WBTM_Functions::wbtm_left_filter_disppaly( $bus_types, $bus_titles, $all_boarding_routes, $filter_by_box, $left_filter_show ) );
      ?>
    </div>
    <?php  }else{
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $width = '100%';
    }?>

    <div id="wbtm-bus-popup" class="wbtm-bus-popup">
        <div class="wbtm-bus-popup-inner">
            <span class="wbtm-popup-close">&times;</span>
            <div class="wbtm-popup-content">
                <!-- AJAX content loads here -->
            </div>
        </div>
    </div>
    <div class="wbtm_bus_list_area" style="width: <?php echo esc_html( $width )?>">
        <input type="hidden" name="bus_start_route" value="<?php echo esc_attr(array_key_exists('bus_start_route', $search_info) ? $search_info['bus_start_route'] : ''); ?>" />
        <input type="hidden" name="bus_end_route" value="<?php echo esc_attr(array_key_exists('bus_end_route', $search_info) ? $search_info['bus_end_route'] : ''); ?>" />
        <input type="hidden" name="j_date" value="<?php echo esc_attr(array_key_exists('j_date', $search_info) ? $search_info['j_date'] : ''); ?>" />
        <input type="hidden" name="r_date" value="<?php echo esc_attr(array_key_exists('r_date', $search_info) ? $search_info['r_date'] : ''); ?>" />
        <input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>" />
        <input type="hidden" name="wbtm_end_route" value="<?php echo esc_attr($end_route); ?>" />
        <input type="hidden" name="wbtm_date" value="<?php echo esc_attr(gmdate('Y-m-d', strtotime($date))); ?>" />

        <?php


        // Sort bus data by 'bp_time' in 24-hour format
        usort($bus_data, function ($a, $b) {
            return strtotime($a['all_info']['bp_time']) - strtotime($b['all_info']['bp_time']);
        });

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        foreach ($bus_data as $key => $bus) {

            $popup_tabs = array(
                'wbtm_bus_details' => 'Bus Details',
                'wbtm_bus_boarding_dropping' => 'Boarding/Dripping Points',
                'wbtm_bus_image' => 'Bus Photo',
                'wbtm_bus_term_condition' => 'Term & Conditions',
                'wbtm_bus_feature' => 'Bus Features',
            );

            $bus_id = $bus['bus_id'];


            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $all_info = $bus['all_info'];
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_count++;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $price = $all_info['price'];

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $next_day = isset($all_info['next_day']) ? $all_info['next_day'] : '0'; // Default to '0' if not set
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bp_time = $all_info['bp_time'];
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $dp_time = $all_info['dp_time'];

            // Adjust dp_time if next_day is '1'
            if ($next_day == '1') {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $dp_timestamp += 24 * 60 * 60; // Add 24 hours in seconds
            }

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_boarding_routes = WBTM_Functions::get_bus_route( $bus_id );
            $selected_feature_ids = get_post_meta( $bus_id, 'wbbm_bus_features_term_id', true );
            $added_term_condition = get_post_meta( $bus_id, 'wbtm_term_condition_list', true );
            $gallery_images = get_post_meta( $bus_id, 'wbtm_gallery_images', true );

            if( empty( $bus_boarding_routes ) ){
                unset( $popup_tabs['wbtm_bus_details'] );
            }
            if( !is_array($selected_feature_ids) && empty( $selected_feature_ids ) ){
                unset( $popup_tabs['wbtm_bus_feature'] );
            }
            if(  !is_array( $added_term_condition ) && empty( $added_term_condition ) ){
                unset( $popup_tabs['wbtm_bus_term_condition'] );
            }
            if( !is_array( $gallery_images ) && empty( $gallery_images ) ){
                unset( $popup_tabs['wbtm_bus_image'] );
            }


            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bp_timestamp = strtotime($bp_time);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $dp_timestamp = strtotime($dp_time);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $duration_seconds = $dp_timestamp - $bp_timestamp;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $duration_hours = floor($duration_seconds / 3600);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $duration_minutes = floor(($duration_seconds % 3600) / 60);
            /*$duration_formatted = sprintf(
                _x('%d H %d M', 'Duration format (hours and minutes)', 'bus-ticket-booking-with-seat-reservation'),
                $duration_hours,
                $duration_minutes
            );*/
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $duration_text = esc_html( '%1$d H %2$d M' );
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $duration_formatted = str_replace(
                ['%1$d', '%2$d'],
                [$duration_hours, $duration_minutes],
                $duration_text
            );
            ?>

            <div class="wbtm-bus-list wtbm_bus_counter <?php echo esc_attr( $wbtm_bus_search ); echo esc_attr(WBTM_Global_Function::check_product_in_cart($bus_id) ? 'in_cart' : ''); ?>" id="wbtm_bust_list">
                <input type="hidden" name="wbtm_bus_name" value="<?php echo esc_attr( get_the_title( $bus_id ) ); ?>" />
                <?php 
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
                ?>
                <input type="hidden" name="wbtm_bus_type" value="<?php echo esc_attr($bus_type); ?>" />
                <?php if( is_array( $bus_boarding_routes ) && count( $bus_boarding_routes ) > 0 ){
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                    foreach ( $bus_boarding_routes as $boarding_route ){
                    ?>
                <input type="hidden" name="wbtm_bus_start_route" value="<?php echo esc_attr($boarding_route); ?>" />
                <?php } }?>

                <div class="wbtm-bus-image ">
                    <?php WBTM_Functions::logo_thumbnail_display($bus_id); ?>
                </div>
                <div class="wbtm_bus_info_details_holder">
                    <div class="wbtm_bus_info_holder">
                        <div class="wbtm-bus-name">
                            <h5 class="_textTheme" data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>"><?php echo esc_html( get_the_title( $bus_id ) ); ?></h5>
                            <p><?php echo esc_html(WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_no')); ?></p>
                        </div>
                        <div class="wbtm-bus-route">
                            <h6>
                                <i class="fas fa-dot-circle"></i>
                                <span class="route"><?php echo esc_html($all_info['bp']) ?></span>
                                <?php if($all_info['bp_time']): ?>
                                    <span class="time">(<?php echo esc_html(WBTM_Global_Function::date_format($all_info['bp_time'],'time')); ?>)</span>
                                <?php endif; ?>
                            </h6>
                            <h6>
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="route"><?php echo esc_html($all_info['dp']) ?></span>
                                <?php if($all_info['bp_time']): ?>
                                    <span class="time">(<?php echo esc_html(WBTM_Global_Function::date_format($all_info['dp_time'],'time')); ?>)</span>
                                <?php endif; ?>
                            </h6>
                            <h6>
                                <i class="far fa-clock"></i><?php echo esc_html( WBTM_Translations::duration_text() ); ?><?php echo esc_html($duration_formatted); ?>
                            </h6>
                        </div>
                        <div class="wbtm-seat-info">
                            <?php
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
                            ?>
                            <span class="<?php echo esc_attr($bus_type=='AC')?'ac':'none-ac'; ?>"><?php echo esc_attr($bus_type); ?></span>

                        </div>
                        <div class="wbtm-seat-info">
                            <h6 class="seats-available"><?php echo esc_html($all_info['available_seat']); ?>/<?php echo esc_html($all_info['total_seat']); ?></h6>
                            <span><?php echo esc_html( WBTM_Translations::text_available() ); ?></span>
                        </div>
                        <div class="wbtm-seat-info">
                            <h6 class="seats-price"><?php echo wp_kses_post( wc_price($price) ); ?></h6>
                            <span><?php echo esc_html( WBTM_Translations::text_fare() . '/' . WBTM_Translations::text_seat() ); ?></span>
                        </div>

                    </div>
                    <div class="wbtm_bus_details_tabs_holder" style="display: flex; justify-content: space-between">
                        <!--<div class="wbtm_bus_details_tabs">
                            <span class="wbtm_bus_details_tab" id="wbtm_bus_details" data-post-id="<?php /*echo $bus_id; */?>"><?php /*esc_html_e( 'Bus Details', 'bus-ticket-booking-with-seat-reservation' );*/?></span>
                            <span class="wbtm_bus_details_tab" id="wbtm_bus_boarding_dropping" data-post-id="<?php /*echo $bus_id; */?>"><?php /*esc_html_e( 'Boarding/Dripping Points', 'bus-ticket-booking-with-seat-reservation' );*/?></span>
                            <span class="wbtm_bus_details_tab" id="wbtm_bus_image" data-post-id="<?php /*echo $bus_id; */?>"><?php /*esc_html_e( 'Bus Photo', 'bus-ticket-booking-with-seat-reservation' );*/?></span>
                            <span class="wbtm_bus_details_tab" id="wbtm_bus_term_condition" data-post-id="<?php /*echo $bus_id; */?>"><?php /*esc_html_e( 'Term & Conditions', 'bus-ticket-booking-with-seat-reservation' );*/?></span>
                            <span class="wbtm_bus_details_tab" id="wbtm_bus_feature" data-post-id="<?php /*echo $bus_id; */?>"><?php /*esc_html_e( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' );*/?></span>
                        </div>-->
                        <?php

                        echo wp_kses_post( WBTM_Functions::single_bus_details_popup_tabs( $bus_id, $popup_tabs ) );

                        if ($btn_show == 'hide' and $all_info['regi_status'] == 'no') {
                            WBTM_Layout::trigger_view_seat_details();
                        }
                        ?>
                        <div class="wbtm-seat-book <?php echo esc_html( $btn_show ); ?>">
                            <button type="button" class="_themeButton_xs" id="get_wbtm_bus_details"
                                    data-bus_id="<?php echo esc_attr($bus_id); ?>"
                                    data-open-text="<?php echo esc_attr(WBTM_Translations::text_view_seat()); ?>"
                                    data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
                                    data-add-class="mActive">
                                <span data-text><?php echo esc_html(WBTM_Translations::text_view_seat()); ?></span>
                            </button>
                        </div>
                    </div>


                </div>

            </div>
            <div class="wbtm_bus_details mT_xs" data-row_id="<?php echo esc_attr($bus_id); ?>">
                <!--  bus details will display here -->
            </div>
        <?php } ?>
        <?php if ($bus_count == 0) : ?>
            <div>
                <?php WBTM_Layout::msg(WBTM_Translations::text_no_bus()); ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php
} else {
    WBTM_Layout::msg(WBTM_Translations::text_no_bus());
}
//echo '<pre>';	print_r($bus_ids);	echo '</pre>';