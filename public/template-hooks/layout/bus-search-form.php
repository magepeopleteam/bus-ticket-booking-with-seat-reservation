<?php
add_action('wbtm_search_form', 'mage_bus_search_form_only');
function mage_bus_search_form_only($single_bus, $target, $custom_url='')
{
    $has_return_route = false;
    $bus_return_show = mage_bus_setting_value('bus_return_show', 'enable');
    if ($single_bus) {
        $has_return_route = get_post_meta(get_the_ID(), 'wbtm_general_same_bus_return', true);
    }
    if($custom_url) {
        $form_url = $custom_url;
    } elseif($single_bus) {
        $form_url = $single_bus;
    } else {
        $form_url = get_permalink(get_page_by_path($target));
    }
?>
    <h4>
        <?php mage_bus_label('wbtm_buy_ticket_text', __('BUY TICKET:', 'bus-ticket-booking-with-seat-reservation')); ?>
    </h4>

    <form action="<?php echo $form_url; ?>" method="get" class="mage_form">
        <?php do_action('active_date',$single_bus,get_the_ID()) ?>
        <div class="mage_form_list">
            <div class="mage_input_select mage_bus_boarding_point">
                <label>
                    <span><i class="fas fa-map-marker"></i> <?php mage_bus_label('wbtm_from_text', __('From:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                    <input type="text" id='wbtm_starting_point_inupt' class="mage_form_control" name="bus_start_route" value="<?php echo mage_bus_isset('bus_start_route'); ?>" placeholder="<?php mage_bus_label('wbtm_please_select_text', __('Please Select', 'bus-ticket-booking-with-seat-reservation')) ?>" autocomplete="off" required />
                </label>
                <?php mage_route_list($single_bus, true, true); ?>
            </div>
        </div>

        <div class="mage_form_list">
            <div class="mage_input_select mage_bus_dropping_point">
                <label>
                    <span><i class="fas fa-map-marker"></i> <?php mage_bus_label('wbtm_to_text', __('To:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                    <span id='wbtm_show_msg'></span>
                    <input type="text" id='wbtm_dropping_point_inupt' class="mage_form_control" name="bus_end_route" value="<?php echo mage_bus_isset('bus_end_route'); ?>" placeholder="<?php mage_bus_label('wbtm_please_select_text', __('Please Select', 'bus-ticket-booking-with-seat-reservation')); ?>" autocomplete="off" required />
                </label>
                <ul id='wbtm_dropping_point_list' class="mage_input_select_list"></ul>
            </div>
        </div>
        <div class="mage_form_list">
            <label>
                <span><i class="fas fa-calendar-alt"></i> <?php mage_bus_label('wbtm_date_of_journey_text', __('Date of Journey', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                <input type="text" class="mage_form_control" id="<?php echo apply_filters('wbtm_journey_date_input_id', 'j_date'); ?>" name="j_date" value="<?php echo mage_bus_isset('j_date'); ?>" placeholder="<?php echo current_time(get_option('date_format')); ?>" autocomplete="off" required />
                <!-- <span class="mage-clear-date">x</span> -->
            </label>
        </div>
        <?php
        $return = (mage_bus_isset('bus-r') == 'oneway') ? false : true;
        if ($bus_return_show == 'enable') :

            if (!$single_bus || $has_return_route=='yes') {
        ?>
                <div class="mage_form_list mage_return_date <?php echo $return ? '' : 'mage_hidden' ?>">
                    <label>

                        <span><i class="fas fa-calendar-alt"></i> <?php mage_bus_label('wbtm_return_date_text', __('Return Date (Optional)', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                        <input type="text" class="mage_form_control" id="r_date" name="r_date" value="<?php echo mage_bus_isset('r_date'); ?>" placeholder="<?php echo current_time(get_option('date_format')); ?>" autocomplete="off" />
                        <!-- <span class="mage-clear-date">x</span> -->
                    </label>
                </div>
        <?php }
        endif; ?>
        <div class="mage_form_list justifyBetween_column">
            <label for="" style="visibility:hidden">None</label>
            <div class="mage_form_search">
                <button id='mage_bus_search_button' type="submit" class="mage_button_search">
                    <span><i class="fas fa-search"></i></span>
                    <?php mage_bus_label('wbtm_search_buses_text', __('Search', 'bus-ticket-booking-with-seat-reservation')); ?>
                    <?php //echo mage_bus_setting_value('bus_menu_label', 'Bus').' '. __('Search', 'bus-ticket-booking-with-seat-reservation') 
                    ?>
                </button>
            </div>
        </div>
    </form>
<?php
    do_action('wbtm_search_form_end');
}

function mage_bus_search_form($target, $custom_url='')
{
    do_action('wbtm_before_search_form');
?>
    <div class="mage_default mage_form_inline">
        <?php mage_bus_search_form_only(false, $target, $custom_url); ?>
    </div>
<?php
    do_action('wbtm_after_search_form');
}

function mage_bus_search_form_horizontal($target)
{
    do_action('wbtm_before_search_form');
?>
    <div class="mage_default mage_form_horizontal">
        <?php mage_bus_search_form_only(false, $target); ?>
    </div>
<?php
    do_action('wbtm_after_search_form');
}
