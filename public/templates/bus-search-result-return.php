<?php
global $wbtmmain;
$start              = isset( $_GET['bus_start_route'] ) ? sanitize_text_field($_GET['bus_start_route']) : '';
$end                = isset( $_GET['bus_end_route'] ) ? sanitize_text_field($_GET['bus_end_route']) : '';
$rdate               = isset( $_GET['r_date'] ) ? sanitize_text_field($_GET['r_date']) : date('Y-m-d');
$values             = get_post_custom( get_the_id() );
$term               = get_the_terms(get_the_id(),'wbtm_bus_cat');
$total_seat         = $values['wbtm_total_seat'][0];
$sold_seat          = $wbtmmain->wbtm_get_available_seat(get_the_id(),$rdate);
$available_seat     = ($total_seat - $sold_seat);
$price_arr          = maybe_unserialize(get_post_meta(get_the_id(),'wbtm_bus_prices',true));    
$bus_bp_array       = maybe_unserialize(get_post_meta(get_the_id(),'wbtm_bus_bp_stops',true));
$bus_dp_array       = maybe_unserialize(get_post_meta(get_the_id(),'wbtm_bus_next_stops',true)); 
$bp_time            = $wbtmmain->wbtm_get_bus_start_time($end, $bus_bp_array);
$dp_time            = $wbtmmain->wbtm_get_bus_end_time($start, $bus_dp_array);
$od_start_date      = get_post_meta(get_the_id(),'wbtm_od_start',true);  
$od_end_date        = get_post_meta(get_the_id(),'wbtm_od_end',true);
$od_range           = $wbtmmain->wbtm_check_od_in_range($od_start_date, $od_end_date, $rdate);
// $oday               = get_post_meta(get_the_id(),$od_name,true);     
$wbtm_bus_on        = array();
$wbtm_bus_on_dates  = get_post_meta(get_the_id(),'wbtm_bus_on_dates',true); 
    $date_format        = get_option( 'date_format' );
    $time_format        = get_option( 'time_format' );
    $datetimeformat     = $date_format.'  '.$time_format; 
?>
<tr class="<?php echo $wbtmmain->wbtm_find_product_in_cart(get_the_id()); ?>">
            <td class='wbtm-mobile-hide'><div class="bus-thumb-list"><?php the_post_thumbnail('thumb'); ?></div></td>
            <td><?php the_title(); ?></td>
            <td class='wbtm-mobile-hide'><?php echo $end; ?></td>
            <td class='wbtm-mobile-hide'><?php echo $values['wbtm_bus_no'][0]; ?></td>
            <td class='wbtm-mobile-hide'><?php echo date($time_format, strtotime($bp_time)); ?></td>
            <td class='wbtm-mobile-hide'><?php echo $start; ?></td>
            <td><?php echo get_woocommerce_currency_symbol(); ?><?php echo $wbtmmain->wbtm_get_bus_price($end,$start, $price_arr); ?></td>
            <td class='wbtm-mobile-hide'><?php  if(!empty($term)){ echo $term[0]->name;} ?></td>
            <td class='wbtm-mobile-hide'><?php echo date($time_format, strtotime($dp_time)); ?></td>
            <td align="center"><span class='available-seat'><?php echo $available_seat; ?></span></td>
            <td><button id="view_panel_<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>" class='view-seat-btn'><?php echo $wbtmmain->bus_get_option('wbtm_view_seats_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_view_seats_text', 'label_setting_sec') : _e('View Seats','bus-ticket-booking-with-seat-reservation'); ?>    
            </button></td>
        </tr>
        <tr style='display: none;' class="admin-bus-details" id="admin-bus-details<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>">
            <td colspan="11">
                <?php
                    $bus_meta           = get_post_custom(get_the_id());
                    $seat_col           = $bus_meta['wbtm_seat_col'][0];
                    $seat_row           = $bus_meta['wbtm_seat_row'][0];
                    $next_stops_arr     =  get_post_meta(get_the_id(), 'wbtm_bus_next_stops', true);
                    $wbtm_bus_bp_stops  =  get_post_meta(get_the_id(), 'wbtm_bus_bp_stops', true);
                    $seat_col_arr       = explode(",",$seat_col);
                    $seat_row_arr       = explode(",",$seat_row);
                    $seat_column        = count($seat_col_arr);
                    $count              = 1;
                    $term               = get_the_terms(get_the_id(),'wbtm_bus_cat');
                    $price_arr          = maybe_unserialize(get_post_meta(get_the_id(),'wbtm_bus_prices',true));  

                    if($seat_column==4){
                        $seat_style     = 2;
                    }elseif ($seat_column==3) {
                        # code...
                        $seat_style     = 1;
                    }else{
                        $seat_style     = 999;
                    }
                ?>
<div class="wbtm-content-wrappers">
    <div >
    <div class="bus-seat-panel">
            <?php         
                $wbtmmain->wbtm_bus_seat_plan($wbtmmain->wbtm_get_this_bus_seat_plan(),$end,$rdate);         
                $wbtmmain->wbtm_bus_seat_plan_dd($end,$rdate); 
            ?> 
       </div>
       <div class="bus-info-sec">
        <?php 
        $price_arr = maybe_unserialize(get_post_meta(get_the_id(),'wbtm_bus_prices',true));
        $fare = $wbtmmain->wbtm_get_bus_price($end,$start, $price_arr);
        ?>
            <form action="" method='post'>
                <div class="top-search-section">                    
                    <div class="leaving-list">
                        <input type="hidden"  name='journey_date' class="text" value='<?php echo $rdate; ?>'/>
                        <input type="hidden" name='start_stops' value="<?php echo $end; ?>" class="hidden"/>
                        <input type='hidden' value='<?php echo $start; ?>' name='end_stops'/>
                        <h6><?php echo $wbtmmain->bus_get_option('wbtm_route_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_route_text', 'label_setting_sec') : _e('Route','bus-ticket-booking-with-seat-reservation'); ?></h6>
                        <div class="selected_route">
                            <?php printf( '<span>%s <i class="fa fa-long-arrow-right"></i> %s<span>', $end, $start ); ?>
                             (<?php echo get_woocommerce_currency_symbol(); ?><?php echo $wbtmmain->wbtm_get_bus_price($end,$start, $price_arr); ?>)
                        </div>
                    </div>                    
                    <div class="leaving-list">
                        <h6><?php echo $wbtmmain->bus_get_option('wbtm_date_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_date_text', 'label_setting_sec') : _e('Date:','bus-ticket-booking-with-seat-reservation'); ?></h6>
                        <div class="selected_date">
                            <?php printf( '<span>%s</span>', date_i18n( $date_format, strtotime( $rdate ) ) ); ?>
                        </div>
                    </div>   
                    <div class="leaving-list">
                        <h6><?php _e('Start & Arrival Time','bus-ticket-booking-with-seat-reservation'); ?></h6>
                        <div class="selected_date">
                            <?php  
                                
           
                                echo date($time_format, strtotime($bp_time)).' <i class="fa fa-long-arrow-right"></i> '.date($time_format, strtotime($dp_time));
                            ?>
                        <input type="hidden" value="<?php echo date($time_format, strtotime($bp_time)); ?>" name="user_start_time" id='user_start_time<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>'>
                        <input type="hidden" name="bus_start_time" value="<?php echo date($time_format, strtotime($bp_time)); ?>" id='bus_start_time'>                            
                        </div>
                    </div>                                    
                </div>
                <div class="seat-selected-list-fare">
                    <table class="selected-seat-list<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>">
                        <tr class='list_head<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>'>
                            <th><?php echo $wbtmmain->bus_get_option('wbtm_seat_no_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_seat_no_text', 'label_setting_sec') : _e('Seat No','bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php echo $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_fare_text', 'label_setting_sec') : _e('Fare','bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th width='50'><?php echo $wbtmmain->bus_get_option('wbtm_remove_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_remove_text', 'label_setting_sec') : _e('Remove','bus-ticket-booking-with-seat-reservation'); 
                             ?>
                             </th>
                        </tr>
                        <tr>
                            <td align="center"> 
                                <?php echo $wbtmmain->bus_get_option('wbtm_total_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_total_text', 'label_setting_sec') : _e('Total','bus-ticket-booking-with-seat-reservation'); 
                             ?>
                            <span id='total_seat<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>_booked'></span><input type="hidden" value="" id="tq<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>" name='total_seat' class="number"/></td>
                            
                            <td align="center"><input type="hidden" value="" id="tfi<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>" class="number"/><span id="totalFare<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>"></span></td><td></td>
                        </tr>
                    </table>
                    <div id="divParent<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>"></div>
                    <input type="hidden" name="bus_id" value="<?php echo get_the_id(); ?>">
                    <button id='bus-booking-btn<?php echo get_the_id().$wbtmmain->wbtm_make_id($rdate); ?>' type="submit" name="add-to-cart" value="<?php echo esc_attr(get_the_id()); ?>" class="single_add_to_cart_button button alt btn-mep-event-cart"> <?php echo $wbtmmain->bus_get_option('wbtm_book_now_text', 'label_setting_sec') ? $wbtmmain->bus_get_option('wbtm_book_now_text', 'label_setting_sec') : _e('Book Now','bus-ticket-booking-with-seat-reservation'); 
                             ?>         
                     </button>
                </div>
            </form>
        </div>
    </div>
<?php 
$uid = get_the_id().$wbtmmain->wbtm_make_id($rdate);
// do_action('wbtm_search_seat_js',$uid,100);  
$wbtmmain->wbtm_seat_booking_js($uid,$fare);
?>
</div>
</td>
</tr>  