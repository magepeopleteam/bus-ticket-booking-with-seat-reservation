<?php 
function wbtm_seat_plan_3($b_start,$date){
    global $wbtmmain;
	$seat_style = 2;
    $bus_meta           = get_post_custom(get_the_id());
    $seat_col           = $bus_meta['wbtm_seat_col'][0];
    $seat_row           = $bus_meta['wbtm_seat_row'][0];
    $next_stops_arr     = get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true);
    $wbtm_bus_bp_stops  = get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true);
    $seat_col_arr       = explode(",",$seat_col);
    $seat_row_arr       = explode(",",$seat_row);
    $seat_column        = count($seat_col_arr);
    $count              = 1;
    $term               = get_the_terms(get_the_id(),'wbtm_bus_cat');
    $price_arr          = get_post_meta(get_the_id(),'wbtm_bus_prices',true); 
    
$current_driver_position = get_post_meta(get_the_id(),'driver_seat_position',true);
if($current_driver_position){
    $current_driver = $current_driver_position;
}else{
    $current_driver = 'driver_right';
}    
 ?>     

 <?php
     $start  = isset( $_GET['bus_start_route'] ) ? strip_tags($_GET['bus_start_route']) : '';
     $end    = isset( $_GET['bus_end_route'] ) ? strip_tags($_GET['bus_end_route']) : '';
     $bus_bp_array = get_post_meta(get_the_id(),'wbtm_bus_bp_stops',true);
     $bus_dp_array = get_post_meta(get_the_id(),'wbtm_bus_next_stops',true);
     $bp_time =  $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
     $dp_time = $wbtmmain->wbtm_get_bus_end_time($end, $bus_dp_array);
  if($wbtmmain->wbtm_buffer_time_check($bp_time,$date) == 'yes'){
  ?>

 
        <div class="bus-seat-panel">
        <img src="<?php echo plugin_dir_url( __FILE__ ).'images/'.$current_driver.'.png'; ?>">
        <?php 
        $wbtm_numeric_seat_status = get_option('wbtm_numeric_seat');
        if($wbtm_numeric_seat_status){
                    $numeric_seat_status = $wbtm_numeric_seat_status;
                }else{
                     $numeric_seat_status = 'off';
                }
        if($numeric_seat_status=='on'){
            $numeric_seat = get_post_meta(get_the_id(),'wbtm_numeric_total_seat',true);
        }else{
            $numeric_seat = '';
        }
            if($numeric_seat){
                $wbtmmain->get_numeric_seat_plan($numeric_seat,$date);
            }else{
            ?>
            <table class="bus-seats" width="300" border="1" style="width: 211px;
    border: 0px solid #ddd;">
                <?php foreach ( $seat_row_arr as $seat_row ) { ?>
                    <tr class="seat<?php echo get_the_id().$wbtmmain->wbtm_make_id($date); ?>_lists ">
                        <?php foreach ( $seat_col_arr as $seat_col ) {
                            $thez               = (int)($seat_col%$seat_style);
                            $seat_name          = $seat_row.$seat_col;
                            $get_seat_status    = $wbtmmain->wbtm_get_seat_status($seat_name,$date,get_the_id(),$b_start);
                            if ($get_seat_status) {

                            $seat_status        = $get_seat_status[0]->status;
                        }else{
                           $seat_status         = 0; 
                        }
                        ?>                        
                        <td>
                            <?php if( $seat_status == 1 ) { ?> <span class="booked-seat"><?php echo $seat_name; ?></span>
                            <?php } elseif($seat_status==2) { ?><span class="confirmed-seat"><?php echo $seat_name; ?></span>
                            <?php } else { ?> <a data-seat='<?php echo $seat_row.$seat_col; ?>' id='seat<?php echo get_the_id().$wbtmmain->wbtm_make_id($date); ?>_<?php echo $seat_row.$seat_col; ?>' data-sclass='Economic' class='seat<?php echo get_the_id().$wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'><?php echo $seat_row.$seat_col; ?></a>
                            <?php } ?>
                        </td>                        
                        <?php if($seat_col==$seat_style){ echo "<td class='no-border empty-lane'></td>"; } ?>
                        <?php $count++;} ?>
                    </tr>
                <?php } ?>  
            </table>
        <?php } ?>
        </div> 
        <?php }else{ ?>

<table>
     <tr>
  <td colspan="10" style="text-align: center;"><?php _e('No Bus Found, Try Another Date.','bus-ticket-booking-with-seat-reservation'); ?></td>
  </tr>
</table>


 <?php } } ?>