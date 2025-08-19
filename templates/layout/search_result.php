<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
$search_info = $search_info ?? [];
$start_route = $start_route ?? '';
$end_route = $end_route ?? '';
$post_id = $post_id ?? '';
$date = $date ?? '';
$journey_type = $journey_type ?? '';
$btn_show = $btn_show ?? '';
$left_filter_show = $left_filter_show ?? '';

$label = WBTM_Functions::get_name();
$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
//echo '<pre>';	print_r($search_info);	echo '</pre>';
if (sizeof($bus_ids) > 0) {
    $bus_count = 0;

    // Collect all bus info first
    $bus_data = [];

    $bus_titles = [];
    $bus_types = [];
    $all_boarding_routes = [];
    foreach ($bus_ids as $bus_id) {
        $all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route);
        if (sizeof($all_info) > 0) {
            $bus_data[] = [
                'bus_id'   => $bus_id,
                'all_info' => $all_info,
            ];
            $bus_titles[] = get_the_title($bus_id);
            
            // Use the synchronize_bus_type function to ensure bus type is consistent
            $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
            $bus_types[] = $bus_type;
            
            // Log bus types for debugging
           // error_log('Bus ID: ' . $bus_id . ' - Title: ' . get_the_title($bus_id) . ' - Bus Type: ' . $bus_type . ' - Date: ' . $date);
            
            $get_boarding_routes = WBTM_Functions::get_bus_route( $bus_id );
            foreach ( $get_boarding_routes as $route ){
                if( !empty( $route ) ){
                    $all_boarding_routes[] = $route;
                }
            }
        }

    }
    $all_boarding_routes = array_unique( $all_boarding_routes );

    if( $journey_type === 'start_journey' ){
        $wbtm_bus_search = 'wbtm_bus_search_journey_start';
        $filter_by_box = 'filter-checkbox';
    }else{
        $wbtm_bus_search = 'wbtm_bus_search_journey_return';
        $filter_by_box = 'return_filter-checkbox';
    }

?>
<div class="wbtm_search_result_holder">
    <?php
    if( !empty($left_filter_show['left_filter_input']) && $left_filter_show['left_filter_input'] === 'on' && $left_filter_show['left_filter_input'] && count( $bus_titles ) > 0 ){
     $width = 'calc( 100% - 180px )'
    ?>
    <div class="wbtm_bus_left_filter_holder">
      <?php
        echo WBTM_Functions::wbtm_left_filter_disppaly( $bus_types, $bus_titles, $all_boarding_routes, $filter_by_box, $left_filter_show );
      ?>
    </div>
    <?php  }else{
        $width = '100%';
    }?>
    <div class="wbtm_bus_list_area" style="width: <?php echo $width?>">
        <input type="hidden" name="bus_start_route" value="<?php echo esc_attr(array_key_exists('bus_start_route', $search_info) ? $search_info['bus_start_route'] : ''); ?>" />
        <input type="hidden" name="bus_end_route" value="<?php echo esc_attr(array_key_exists('bus_end_route', $search_info) ? $search_info['bus_end_route'] : ''); ?>" />
        <input type="hidden" name="j_date" value="<?php echo esc_attr(array_key_exists('j_date', $search_info) ? $search_info['j_date'] : ''); ?>" />
        <input type="hidden" name="r_date" value="<?php echo esc_attr(array_key_exists('r_date', $search_info) ? $search_info['r_date'] : ''); ?>" />
        <input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>" />
        <input type="hidden" name="wbtm_end_route" value="<?php echo esc_attr($end_route); ?>" />
        <input type="hidden" name="wbtm_date" value="<?php echo esc_attr(date('Y-m-d', strtotime($date))); ?>" />

        <?php


        // Sort bus data by 'bp_time' in 24-hour format
        usort($bus_data, function ($a, $b) {
            return strtotime($a['all_info']['bp_time']) - strtotime($b['all_info']['bp_time']);
        });

        foreach ($bus_data as $key => $bus) {
            $bus_id = $bus['bus_id'];
            $all_info = $bus['all_info'];
            $bus_count++;
            $price = $all_info['price'];

            // Check if next_day exists and set a default value if not
            $next_day = isset($all_info['next_day']) ? $all_info['next_day'] : '0'; // Default to '0' if not set
            $bp_time = $all_info['bp_time'];
            $dp_time = $all_info['dp_time'];

            // Adjust dp_time if next_day is '1'
            if ($next_day == '1') {
                $dp_timestamp += 24 * 60 * 60; // Add 24 hours in seconds
            }

            $bus_boarding_routes = WBTM_Functions::get_bus_route( $bus_id );

            $bp_timestamp = strtotime($bp_time);
            $dp_timestamp = strtotime($dp_time);
            $duration_seconds = $dp_timestamp - $bp_timestamp;

            $duration_hours = floor($duration_seconds / 3600);
            $duration_minutes = floor(($duration_seconds % 3600) / 60);
            /* translators: %d: hours, %d: minutes */
            $duration_formatted = sprintf(
                _x('%d H %d M', 'Duration format (hours and minutes)', 'bus-ticket-booking-with-seat-reservation'),
                $duration_hours,
                $duration_minutes
            );
            ?>

            <div class="wbtm-bus-list  <?php echo $wbtm_bus_search; echo esc_attr(WBTM_Global_Function::check_product_in_cart($bus_id) ? 'in_cart' : ''); ?>" id="wbtm_bust_list">
                <input type="hidden" name="wbtm_bus_name" value="<?php echo esc_attr( get_the_title( $bus_id ) ); ?>" />
                <?php 
                // Get the bus type directly
                $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
                ?>
                <input type="hidden" name="wbtm_bus_type" value="<?php echo esc_attr($bus_type); ?>" />
                <?php if( is_array( $bus_boarding_routes ) && count( $bus_boarding_routes ) > 0 ){
                    foreach ( $bus_boarding_routes as $boarding_route ){
                    ?>
                <input type="hidden" name="wbtm_bus_start_route" value="<?php echo esc_attr($boarding_route); ?>" />
                <?php } }?>

                <div class="wbtm-bus-image ">
                    <?php WBTM_Custom_Layout::bg_image($bus_id); ?>
                </div>
                <div class="wbtm-bus-name">
                    <h5 class="_textTheme" data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>"><?php echo get_the_title($bus_id); ?></h5>
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
                        <i class="far fa-clock"></i><?php echo WBTM_Translations::duration_text(); ?><?php echo esc_html($duration_formatted); ?>
                    </h6>
                </div>
                <div class="wbtm-seat-info">
                        <?php 
                        $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
                        ?>
                        <span class="<?php echo esc_attr($bus_type=='AC')?'ac':'none-ac'; ?>"><?php echo esc_attr($bus_type); ?></span>
                        
                    </div>
                <div class="wbtm-seat-info">
                    <h6 class="seats-available"><?php echo esc_html($all_info['available_seat']); ?>/<?php echo esc_html($all_info['total_seat']); ?></h6>
                    <span><?php echo WBTM_Translations::text_available(); ?></span>
                </div>
                <div class="wbtm-seat-info">
                        <h6 class="seats-price"><?php echo wc_price($price); ?></h6>
                        <span><?php echo WBTM_Translations::text_fare() . '/' . WBTM_Translations::text_seat(); ?></span>
                </div>
                <?php
                if ($btn_show == 'hide' and $all_info['regi_status'] == 'no') {
                    WBTM_Layout::trigger_view_seat_details();
                }
                ?>
                <div class="wbtm-seat-book <?php echo $btn_show; ?>">
                    <button type="button" class="_themeButton_xs" id="get_wbtm_bus_details"
                            data-bus_id="<?php echo esc_attr($bus_id); ?>"
                            data-open-text="<?php echo esc_attr(WBTM_Translations::text_view_seat()); ?>"
                            data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
                            data-add-class="mActive">
                        <span data-text><?php echo esc_html(WBTM_Translations::text_view_seat()); ?></span>
                    </button>
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