<div class="mp_tab_item" data-tab-item="#wbtm_pickuppoint">
    <h3><?php _e(' Pickup Point :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
    <h5 class="dFlex mpStyle">
        <span class="pb-10"><b class="ra-enable-button"><?php _e('Enable pickup point :', 'bus-ticket-booking-with-seat-reservation'); ?></b>
            <label class="roundSwitchLabel">
            <input id="pickup-point-control" name="show_pickup_point" <?php echo ($show_pickup_point == "yes" ? " checked" : ""); ?> value="yes" type="checkbox">
            <span class="roundSwitch" data-collapse-target="#ttbm_display_related"></span>
        </label>
        </span>

        <p><?php _e('Do you have multiple pickup point for single boarding point then enable this to add pickup point ', 'bus-ticket-booking-with-seat-reservation'); ?></p>
    </h5>
    <hr />
    <div style="display: <?php echo ($show_pickup_point == "yes" ? "block" : "none"); ?>" id="pickup-point">
        <?php $this->wbtmPickupPoint(); ?>
    </div>
</div>


<div class="mp_tab_item" data-tab-item="#wbtm_bus_off_on_date">
    <h3><?php echo mage_bus_setting_value('bus_menu_label', 'Bus').' '.esc_html__('Onday & Offday', 'bus-ticket-booking-with-seat-reservation').':'; ?></h3>
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