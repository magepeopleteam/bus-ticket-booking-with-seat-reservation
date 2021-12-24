<?php
add_action('wbtm_search_form', 'mage_bus_search_form_only');
function mage_bus_search_form_only($single_bus, $target)
{
    $has_return_route = false;
    $bus_return_show = mage_bus_setting_value('bus_return_show', 'enable');
    if ($single_bus) {
        $has_return_route = get_post_meta(get_the_ID(), 'wbtm_bus_bp_stops_return', true);
    }
    ?>
    <h4>
        <?php
        mage_bus_label('wbtm_buy_ticket_text', __('BUY TICKET:', 'bus-ticket-booking-with-seat-reservation'));
        ?>


    </h4>
    <form action="<?php echo $single_bus ? '' : get_site_url() . '/' . $target . '/'; ?>" method="get"
          class="mage_form">
        <div class="mage_form_list">
            <div class="mage_input_select mage_bus_boarding_point">
                <label>
                    <span class="fa fa-map-marker"><?php mage_bus_label('wbtm_from_text', __('From:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                    <input type="text" id='wbtm_starting_point_inupt' class="mage_form_control" name="bus_start_route"
                           value="<?php echo mage_bus_isset('bus_start_route'); ?>"
                           placeholder="<?php mage_bus_label('wbtm_please_select_text', __('Please Select', 'bus-ticket-booking-with-seat-reservation')) ?>"
                           autocomplete="off" required/>
                </label>
                <?php mage_route_list($single_bus, true, true); ?>
            </div>
        </div>
        <div class="mage_form_list">
            <div class="mage_input_select mage_bus_dropping_point">
                <label>
                    <span class="fa fa-map-marker"><?php mage_bus_label('wbtm_to_text', __('To:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                    <span id='wbtm_show_msg'></span>
                    <input type="text" id='wbtm_dropping_point_inupt' class="mage_form_control" name="bus_end_route"
                           value="<?php echo mage_bus_isset('bus_end_route'); ?>"
                           placeholder="<?php mage_bus_label('wbtm_please_select_text', __('Please Select', 'bus-ticket-booking-with-seat-reservation')); ?>"
                           autocomplete="off" required/>
                </label>
                <ul id='wbtm_dropping_point_list' class="mage_input_select_list"></ul>
            </div>
        </div>
        <div class="mage_form_list">
            <label>
                <span class="fa fa-calendar"><?php mage_bus_label('wbtm_date_of_journey_text', __('Date of Journey', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                <input type="text" class="mage_form_control"
                       id="<?php echo apply_filters('wbtm_journey_date_input_id', 'j_date'); ?>" name="j_date"
                       value="<?php echo mage_bus_isset('j_date'); ?>"
                       placeholder="<?php echo current_time(get_option('date_format')); ?>" autocomplete="off"
                       required/>
            </label>
        </div>
        <?php
        $return = (mage_bus_isset('bus-r') == 'oneway') ? false : true;
        if ($bus_return_show == 'enable') :
            if (!$single_bus || $has_return_route) {
                ?>
                <div class="mage_form_list mage_return_date <?php echo $return ? '' : 'mage_hidden' ?>">
                    <label>

                        <span class="fa fa-calendar"><?php mage_bus_label('wbtm_return_date_text', __('Return Date (Optional)', 'bus-ticket-booking-with-seat-reservation')); ?></span>
                        <input type="text" class="mage_form_control" id="r_date" name="r_date"
                               value="<?php echo mage_bus_isset('r_date'); ?>"
                               placeholder="<?php echo current_time(get_option('date_format')); ?>" autocomplete="off"/>
                    </label>
                </div>
            <?php }
        endif; ?>
        <div class="mage_form_list justifyBetween_column">
            <label for="" style="visibility:hidden">None</label>
            <div class="mage_form_search">
                <button id='mage_bus_search_button' type="submit" class="mage_button_search">
                    <span class="fa fa-search"></span>
                    <?php mage_bus_label('wbtm_search_buses_text', __('Search', 'bus-ticket-booking-with-seat-reservation')); ?>
                </button>
            </div>
        </div>
    </form>
    <?php
    do_action('wbtm_search_form_end');
}

function mage_bus_search_form($target)
{
    do_action('wbtm_before_search_form');
    ?>
    <div class="mage_default mage_form_inline">
        <?php mage_bus_search_form_only(false, $target); ?>
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