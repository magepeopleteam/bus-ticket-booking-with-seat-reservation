<div class="mp_tab_item" data-tab-item="#wbtm_ticket_panel" style="display:block;">
    <h3><?php echo mage_bus_setting_value('bus_menu_label', 'Bus').' '. __('Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <hr />
    <?php $this->wbtm_bus_ticket_type(); ?>
</div>
<div class="mp_tab_item" data-tab-item="#wbtm_routing">
    <div class="row">
        <div class="col-md-6">
            <div class="wbtm_tab_content_heading">
                <h3><?php esc_html_e(' Routing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>

                <div class="wbtm-section-info">
                    <span><i class="fas fa-info-circle"></i></span>
                    <div class="wbtm-section-info-content">
                        <?php echo $routing_info; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mpStyle">
                <div class="mpPopup" data-popup="#wbtm_route_popup">
                    <div class="popupMainArea">
                        <div class="popupHeader">
                            <h4>
                                <?php esc_html_e( 'Add New Bus Stop', 'bus-ticket-booking-with-seat-reservation' ); ?>
                            </h4>
                            <span class="fas fa-times popupClose"></span>
                        </div>
                        <div class="popupBody bus-stop-form">
                            <h6 class="textSuccess success_text" style="display: none;">Added Succesfully</h6>
                            <label>
                                <span class="w_200"><?php esc_html_e( 'Name:', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                <input type="text"  class="formControl" id="bus_stop_name">
                            </label>
                            <p class="name_required"><?php esc_html_e( 'Name is required', 'bus-ticket-booking-with-seat-reservation' ); ?></p>

                            <label class="mT">
                                <span class="w_200"><?php esc_html_e( 'Description:', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                <textarea  id="bus_stop_description" rows="5" cols="50" class="formControl"></textarea>
                            </label>

                        </div>
                        <div class="popupFooter">
                            <div class="buttonGroup">
                                <button class="_themeButton submit-bus-stop" type="button"><?php esc_html_e( 'Save', 'tour-booking-manager' ); ?></button>
                                <button class="_warningButton submit-bus-stop close_popup" type="button"><?php esc_html_e( 'Save & Close', 'tour-booking-manager' ); ?></button>
                            </div>
                        </div>
                    </div>

                </div>
                <button type="button" class="_dButton_xs_bgBlue" data-target-popup="#wbtm_route_popup">
                    <span class="fas fa-plus-square"></span>
                    Add New Bus Stop
                </button>
            </div>

        </div>
    </div>
    <hr />
    <?php $this->wbtmRouting(); ?>
</div>




<div class="mp_tab_item" data-tab-item="#wbtm_seat_price">
    <div class="wbtm_tab_content_heading">

        <h3><?php _e(' Seat Pricing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        <div class="wbtm-section-info">
            <span><i class="fas fa-info-circle"></i></span>
            <div class="wbtm-section-info-content">
                <?php _e('Individual prices for boarding point to dropping point with seat types.', 'bus-ticket-booking-with-seat-reservation'); ?>
            </div>
        </div>

    </div>
    <hr />
    <?php $this->wbtmPricing(); ?>
</div>
<div class="mp_tab_item" data-tab-item="#wbtm_pickuppoint">
    <h3><?php _e(' Pickup Point :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <h5 class="dFlex mpStyle">
        <span class="pb-10"><b>Enable pickup point :</b>  Do you have multiple pickup point for single boarding point then enable this to add pickup point. </span>
        <label class="roundSwitchLabel">
            <input id="pickup-point-control" name="show_pickup_point" <?php echo ($show_pickup_point == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
            <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
        </label>
    </h5>
    <hr />
    <div style="display: <?php echo ($show_pickup_point == "yes" ? "block" : "none"); ?>" id="pickup-point">
        <?php $this->wbtmPickupPoint(); ?>
    </div>
</div>
<div class="mp_tab_item" data-tab-item="#wbtm_bus_off_on_date">
    <h3><?php _e(' Bus Onday & Offday:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <hr />
    <?php $this->wbtmBusOnDate(); ?>
</div>
<div class="mp_tab_item" data-tab-item="#wbtm_bus_tax">
    <h3><?php _e(' Bus Tax Settings:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <hr />
    <?php $this->wbtm_tax($tour_id); ?>
</div>
<div class="mp_tab_item tab-content" data-tab-item="#_mep_pp_deposits_type">
    <h3><?php _e(' Partial Payment Settings:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <hr />
    <?php $this->partial_payment($tour_id); ?>
</div>