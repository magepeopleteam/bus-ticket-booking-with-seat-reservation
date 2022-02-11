<?php
add_action('wbtm_bus_search_next_day_tab','wbtm_display_next_day_tabs');
function wbtm_display_next_day_tabs(){
      $date_format        = get_option( 'date_format' );
      $time_format        = get_option( 'time_format' );
      $datetimeformat     = $date_format.'  '.$time_format; 
    $date                           = isset($_GET['j_date']) ? wbtm_convert_date_to_php($_GET['j_date']) : date('Y-m-d');
    $tab_date                       = isset($_GET['tab_date']) ? $_GET['tab_date'] : $date;
    $next_date                      = date('Y-m-d', strtotime($tab_date .' +1 day'));
    $day_after_next_date            = date('Y-m-d', strtotime($tab_date .' +2 day'));
    $day_after_day_after_next_date  = date('Y-m-d', strtotime($tab_date .' +3 day'));    
    ?>
    <ul>
        <li class=<?php if($date == $tab_date){ echo 'current-tab'; } ?>><a href="<?php echo get_site_url(); ?>/bus-search-list/?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $tab_date; ?>&r_date=<?php echo strip_tags($_GET['r_date']); ?>&bus-r=<?php echo strip_tags($_GET['bus-r']); ?>&tab_date=<?php echo $tab_date; ?>"><?php echo date_i18n($date_format, strtotime($tab_date)) ?></a></li>
        <li class=<?php if($date == $next_date){ echo 'current-tab'; } ?>><a href="<?php echo get_site_url(); ?>/bus-search-list/?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $next_date; ?>&r_date=<?php echo strip_tags($_GET['r_date']); ?>&bus-r=<?php echo strip_tags($_GET['bus-r']); ?>&tab_date=<?php echo $tab_date; ?>"><?php echo date_i18n($date_format, strtotime($next_date)) ?></a></li>
        <li class=<?php if($date == $day_after_next_date){ echo 'current-tab'; } ?>><a href="<?php echo get_site_url(); ?>/bus-search-list/?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $day_after_next_date; ?>&r_date=<?php echo strip_tags($_GET['r_date']); ?>&bus-r=<?php echo strip_tags($_GET['bus-r']); ?>&tab_date=<?php echo $tab_date; ?>"><?php echo date_i18n($date_format, strtotime($day_after_next_date)) ?></a></li>
        <li class=<?php if($date == $day_after_day_after_next_date){ echo 'current-tab'; } ?>><a href="<?php echo get_site_url(); ?>/bus-search-list/?bus_start_route=<?php echo strip_tags($_GET['bus_start_route']); ?>&bus_end_route=<?php echo strip_tags($_GET['bus_end_route']); ?>&j_date=<?php echo $day_after_day_after_next_date; ?>&r_date=<?php echo strip_tags($_GET['r_date']); ?>&bus-r=<?php echo strip_tags($_GET['bus-r']); ?>&tab_date=<?php echo $tab_date; ?>"><?php echo date_i18n($date_format, strtotime($day_after_day_after_next_date)) ?></a></li>
    </ul>
    <?php
}