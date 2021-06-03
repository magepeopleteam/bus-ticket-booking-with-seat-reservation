<?php
if (!defined('ABSPATH')) exit;  // if direct access

class WBTMMetaBox
{
    public function __construct()
    {
        // $this->meta_boxs();
        // add_action('add_meta_boxes', array($this, 'wbtm_bus_meta_box_add'));

        // Custom Metabox
        add_action('add_meta_boxes', array($this, 'add_meta_box_func'));
        // Tab lists
        add_action('ttbm_meta_box_tab_name', array($this, 'ttbm_add_meta_box_tab_name'), 20);
        // Tab Contents
        add_action('ttbm_meta_box_tab_content', array($this, 'ttbm_add_meta_box_tab_content'), 10);

        add_action('save_post', array($this, 'wbtm_bus_seat_panels_meta_save'));
        add_action('admin_menu', array($this, 'wbtm_remove_post_custom_fields'));
    }

    public function add_meta_box_func()
    {
        add_meta_box('ttbm_add_meta_box', __('<span class="dashicons dashicons-info"></span>Bus Information : ', 'bus-ticket-booking-with-seat-reservation') . get_the_title(get_the_id()), array($this, 'mp_event_all_in_tab'), 'wbtm_bus', 'normal', 'high');
    }

    public function mp_event_all_in_tab()
    {
        $post_id = get_the_id();
        ?>
        <div class="mp_event_all_meta_in_tab mp_event_tab_area">
            <div class="mp_tab_menu">
                <ul>
                    <?php do_action('ttbm_meta_box_tab_name', $post_id); ?>
                </ul>
            </div>
            <div class="mp_tab_details">
                <?php do_action('ttbm_meta_box_tab_content', $post_id); ?>
            </div>
        </div>
        <?php
    }

    // Tab lists
    public function ttbm_add_meta_box_tab_name($tour_id)
    {
        $label_bus_configuration = __('Bus Configuration', 'bus-ticket-booking-with-seat-reservation');
        ?>
        <li data-target-tabs="#wbtm_ticket_panel" class="active"><span
                    class="dashicons dashicons-id"></span>&nbsp;&nbsp;<?php echo $label_bus_configuration; ?>
        </li>

        <li data-target-tabs="#wbtm_routing"><span
                    class="dashicons dashicons-palmtree"></span>&nbsp;&nbsp;<?php echo __('Routing', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <li data-target-tabs="#wbtm_seat_price"><span
                    class="dashicons dashicons-money-alt"></span>&nbsp;&nbsp;<?php _e('Seat price', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <li data-target-tabs="#wbtm_pickuppoint"><span
                    class="dashicons dashicons-flag"></span>&nbsp;&nbsp;<?php echo __('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <li data-target-tabs="#wbtm_bus_off_on_date"><span
                    class="dashicons dashicons-calendar-alt"></span>&nbsp;&nbsp;<?php echo __('Bus Onday & Offday', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
		<li data-target-tabs="#wbtm_bus_tax">
			<span class="dashicons dashicons-admin-settings"></span>&nbsp;&nbsp;<?php _e('Tax', 'bus-ticket-booking-with-seat-reservation'); ?>
		</li>
		<?php } ?>
        <?php

    }

    // Tab Contents
    public function ttbm_add_meta_box_tab_content($tour_id)
    {
        ?>
        <div class="mp_tab_item" data-tab-item="#wbtm_ticket_panel" style="display:block;">
            <h3><?php _e(' Bus Configuration :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtm_bus_ticket_type(); ?>
        </div>
        <div class="mp_tab_item" data-tab-item="#wbtm_routing">
            <h3><?php _e(' Routing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtmRouting(); ?>
        </div>

        <div class="mp_tab_item" data-tab-item="#wbtm_seat_price">
            <h3><?php _e(' Seat Pricing :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtmPricing(); ?>
        </div>
        <div class="mp_tab_item" data-tab-item="#wbtm_pickuppoint">
            <h3><?php _e(' Pickup Point :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtmPickupPoint(); ?>
        </div>
        <div class="mp_tab_item" data-tab-item="#wbtm_bus_off_on_date">
            <h3><?php _e(' Bus Onday & Offday:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtmBusOnDate(); ?>
        </div>
        <div class="mp_tab_item" data-tab-item="#wbtm_bus_tax">
            <h3><?php _e(' Bus Tax Settings:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <hr/>
            <?php $this->wbtm_tax($tour_id); ?>
        </div>
        <?php
    }

    // END*****************

    // public function wbtm_bus_meta_box_add()
    // {
    //     add_meta_box('wbtm-bus-ticket-type', '<span class="dashicons dashicons-id" style="color: #0071a1;"></span>Bus Ticket Panel', array($this, 'wbtm_bus_ticket_type'), 'wbtm_bus', 'normal', 'high');
    // }

    function wbtm_remove_post_custom_fields()
    {
        // remove_meta_box( 'tagsdiv-wbtm_seat' , 'wbtm_bus' , 'side' );
        remove_meta_box('wbtm_seat_typediv', 'wbtm_bus', 'side');
        remove_meta_box('wbtm_bus_stopsdiv', 'wbtm_bus', 'side');
        remove_meta_box('wbtm_bus_routediv', 'wbtm_bus', 'side');
    }

    function wbtm_tax($post_id) 
	{
        // echo $post_id;
		$values = get_post_custom($post_id);
		wp_nonce_field('mep_event_reg_btn_nonce', 'mep_event_reg_btn_nonce');
		if (array_key_exists('_tax_status', $values)) {
			$tx_status = $values['_tax_status'][0];
		} else {
			$tx_status = '';
		}

		if (array_key_exists('_tax_class', $values)) {
			$tx_class = $values['_tax_class'][0];
		} else {
			$tx_class = '';
		}
	?>
		<table>
			<tr>
				<th><span><?php _e('Tax status:', 'bus-ticket-booking-with-seat-reservation'); ?></span></th>
				<td colspan="3">
					<label>
						<select class="mp_formControl" name="_tax_status">
							<option value="taxable" <?php echo ($tx_status == 'taxable') ? 'selected' : ''; ?>><?php _e('Taxable', 'bus-ticket-booking-with-seat-reservation'); ?></option>
							<option value="shipping" <?php echo ($tx_status == 'shipping') ? 'selected' : ''; ?>><?php _e('Shipping only', 'bus-ticket-booking-with-seat-reservation'); ?></option>
							<option value="none" <?php echo ($tx_status == 'none') ? 'selected' : ''; ?>><?php _e('None', 'bus-ticket-booking-with-seat-reservation'); ?></option>
						</select>
					</label>
				</td>
			</tr>
			<tr>
				<th><span><?php _e('Tax class:', 'bus-ticket-booking-with-seat-reservation'); ?></span></th>
				<td colspan="3">				
					<label>
						<select class="mp_formControl" name="_tax_class">
							<option value="standard" <?php echo ($tx_class == 'standard') ? 'selected' : ''; ?>><?php _e('Standard', 'bus-ticket-booking-with-seat-reservation'); ?></option>
							<?php $this->get_all_tax_list($tx_class); ?>
						</select>
					</label>
					<p class="event_meta_help_txt">
						<?php _e('To add any new tax class , Please go to WooCommerce ->Settings->Tax Area', 'bus-ticket-booking-with-seat-reservation'); ?>
					</p>
				</td>
			</tr>
		</table>
	<?php
	}

    function get_all_tax_list($current_tax=null){
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_tax_rate_classes';
        $result = $wpdb->get_results( "SELECT * FROM $table_name" );
      
        foreach ( $result as $tax ){
        ?>
        <option value="<?php echo $tax->slug;  ?>" <?php if($current_tax == $tax->slug ){ echo 'Selected'; } ?>><?php echo $tax->name;  ?></option>
        <?php
        }
      }


    public function wbtm_bus_ticket_type()
    {
        global $post, $wbtmmain;
        $values = get_post_custom($post->ID);

        $wbtm_seat_type_conf = array_key_exists('wbtm_seat_type_conf', $values) ? $values['wbtm_seat_type_conf'][0] : 'wbtm_seat_plan';

        $coach_no = array_key_exists('wbtm_bus_no', $values) ? $values['wbtm_bus_no'][0] : '';
        $total_seat = array_key_exists('wbtm_total_seat', $values) ? $values['wbtm_total_seat'][0] : '';

        $wbtm_general_same_bus_return = array_key_exists('wbtm_general_same_bus_return', $values) ? $values['wbtm_general_same_bus_return'][0] : '';

        $subscription_type = array_key_exists('mtsa_subscription_route_type', $values) ? $values['mtsa_subscription_route_type'][0] : 'wbtm_city_zone';

        $mtpa_car_type = array_key_exists('mtpa_car_type', $values) ? $values['mtpa_car_type'][0] : '';
        ?>
        <div class="wbtm-item-row">
            <label class="item-label"><?php _e('Coach No', 'bus-ticket-booking-with-seat-reservation'); ?></label>
            <input type="text" name="wbtm_bus_no" value="<?php echo $coach_no; ?>">
        </div>
        <div class="wbtm-item-row">
            <label class="item-label"><?php _e('Total Seat', 'bus-ticket-booking-with-seat-reservation'); ?></label>
            <input type="number" name="wbtm_total_seat" value="<?php echo $total_seat; ?>">
        </div>
        <div class="wbtm-item-row wbtm-seat-type-conf">
            <label class="item-label"><?php _e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></label>
            <select name="wbtm_seat_type_conf" id="">
                <option value=""><?php _e('Select Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                <option value="wbtm_seat_plan" <?php echo(($wbtm_seat_type_conf == 'wbtm_seat_plan') ? 'selected' : '') ?>>
                    <?php _e('Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                </option>
                <option value="wbtm_without_seat_plan"
                    <?php echo(($wbtm_seat_type_conf == 'wbtm_without_seat_plan') ? 'selected' : '') ?>><?php _e('Without Seat
                    Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                </option>
                <?php do_action('wbtm_seat_type_subscription', $wbtm_seat_type_conf) ?>
                <?php do_action('wbtm_seat_type_private', $wbtm_seat_type_conf) ?>
            </select>
        </div>

        <div id="wbtm_same_bus_return" class="wbtm-item-row">
            <label class="item-label"><?php _e("Same Bus return", "bus-ticket-booking-with-seat-reservation") ?></label>
            <input type="radio" id="wbtm_same_bus_return_no" name="wbtm_general_same_bus_return" <?php echo (($wbtm_general_same_bus_return == "no" || $wbtm_general_same_bus_return == '') ? " checked" : ""); ?> value="no"> <label for="wbtm_same_bus_return_no"><?php _e('No', 'bus-ticket-booking-with-seat-reservation') ?></label>
            <input type="radio" id="wbtm_same_bus_return_yes" name="wbtm_general_same_bus_return" <?php echo ($wbtm_general_same_bus_return == "yes" ? " checked" : ""); ?> value="yes" style="margin-left: 20px"> <label for="wbtm_same_bus_return_yes"><?php _e('Yes', 'bus-ticket-booking-with-seat-reservation') ?></label>
        </div>

        <div id="mtsa_city_zone" class="wbtm-item-row">
            <?php do_action('wbtm_subscription_route_type', $subscription_type); ?>
        </div>

        <?php if (has_action('wbtm_car_type')) : ?>
        <div id="mtpa_car_type" class="wbtm-item-row">
            <?php do_action('wbtm_car_type', $mtpa_car_type); ?>
        </div>
    <?php endif; ?>

        <div class="wbtm-seat-plan-wrapper">
            <h2 class="wbtm-deck-title"><?php _e('Seat Plan for Lower Deck', 'bus-ticket-booking-with-seat-reservation') ?></h2>
            <div class="wbtm-lower-bus-seat-maker-wrapper">
                <div class="wbtm-control-part">
                    <h3 class="wbtm-seat-title"><?php _e('Seat Maker', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                    <p class="wbtm-control-row">
                        <strong><?php _e('Driver Seat Position', 'bus-ticket-booking-with-seat-reservation'); ?>
                            :</strong>
                        <span>
                    <?php
                    if (array_key_exists('driver_seat_position', $values)) {
                        $position = $values['driver_seat_position'][0];
                    } else {
                        $position = 'left';
                    }
                    $wbtmmain->wbtm_get_driver_position($position);
                    ?>
                </span>
                    </p>
                    <p class="wbtm-control-row">
                        <strong><?php _e('Total Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                        <input type="number" value='<?php if (array_key_exists('wbtm_seat_cols', $values)) {
                            echo $values['wbtm_seat_cols'][0];
                        } ?>' name="seat_col" id='seat_col' style="width: 70px;" pattern="[1-9]*" inputmode="numeric"
                               min="0" max="">
                    </p>
                    <p class="wbtm-control-row">
                        <strong><?php _e('Total Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                        <input type="number" value='<?php if (array_key_exists('wbtm_seat_rows', $values)) {
                            echo $values['wbtm_seat_rows'][0];
                        } ?>' name="seat_rows" id='seat_rows' style="width: 70px;" pattern="[1-9]*" inputmode="numeric"
                               min="0" max="">
                    </p>
                    <p class="wbtm-control-row">
                        <button id="create_seat_plan" class="create_seat_plan"><span
                                    class="dashicons dashicons-plus"></span><?php _e('Create Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </button>
                    </p>
                </div>


                <div class="wbtm-preview-part">
                    <h3 class="wbtm-seat-title"><?php _e('Seat Preview', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                    <div id="seat_result" style="flex-basis: 100%;">
                        <?php
                        if (array_key_exists('wbtm_bus_seats_info', $values)) {
                            $old = $values['wbtm_bus_seats_info'][0];
                            $seatrows = $values['wbtm_seat_rows'][0];
                            $seatcols = $values['wbtm_seat_cols'][0];
                            $seats = unserialize($old);
                            ?>
                            <!--suppress JSJQueryEfficiency -->
                            <script type="text/javascript">
                                jQuery(document).ready(function ($) {
                                    $('#add-seat-row').on('click', function () {
                                        var row = $('.empty-row-seat.screen-reader-text').clone(true);
                                        row.removeClass('empty-row-seat screen-reader-text');
                                        row.insertBefore('#repeatable-fieldset-seat-one tbody>tr:last');
                                        var qtt = parseInt($('#seat_rows').val(), 10);
                                        $('#seat_rows').val(qtt + 1);
                                        return false;
                                    });
                                    $('.remove-seat-row').on('click', function () {
                                        $(this).parents('tr').remove();
                                        var qtt = parseInt($('#seat_rows').val(), 10);
                                        $('#seat_rows').val(qtt - 1);
                                        return false;
                                    });
                                });
                            </script>

                            <table class="wbtm-seat-table" id="repeatable-fieldset-seat-one">
                                <tbody>
                                <?php

                                foreach ($seats as $_seats) {
                                    ?>
                                    <tr>
                                        <?php
                                        for ($x = 1; $x <= $seatcols; $x++) {
                                            $text_field_name = "seat" . $x;
                                            $seat_type_name = "seat_types" . $x;
                                            ?>
                                            <td align="center">
                                                <input type="text" value="<?php echo $_seats[$text_field_name]; ?>"
                                                       name="<?php echo $text_field_name; ?>[]" class="text">
                                                <?php wbtm_get_seat_type_list($seat_type_name, $post->ID); ?>

                                            </td>
                                            <?php
                                        }
                                        ?>
                                        <td align="center"><a class="button remove-seat-row"
                                                              href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </a>
                                            <input type="hidden" name="bus_seat_panels[]">
                                        </td>
                                    </tr>
                                <?php } ?>
                                <!-- empty hidden one for jQuery -->
                                <tr class="empty-row-seat screen-reader-text">
                                    <?php
                                    for ($row = 1; $row <= $seatcols; $row++) {
                                        $seat_type_name = "seat_types" . $row;
                                        ?>
                                        <td align="center">
                                            <input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
                                            <?php wbtm_get_seat_type_list($seat_type_name); ?>
                                        </td>
                                    <?php } ?>
                                    <td align="center"><a class="button remove-seat-row"
                                                          href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a><input type="hidden" name="bus_seat_panels[]"></td>
                                </tr>
                                </tbody>
                            </table>

                            <div id="add-seat-row" class="add-seat-row-btn">
                                <i class="fas fa-plus"></i>
                            </div>

                        <?php } ?>

                    </div>
                </div>
            </div>

            <script type="text/javascript">
                jQuery(document).ready(function ($) {

                    jQuery("#create_seat_plan").click(function (e) {
                        e.preventDefault();
                        seat_col = jQuery("#seat_col").val().trim();
                        seat_row = jQuery("#seat_rows").val().trim();
                        jQuery.ajax({
                            type: 'POST',
                            // url:wbtm_ajax.wbtm_ajaxurl,
                            url: wbtm_ajaxurl,
                            data: {
                                "action": "wbtm_seat_plan",
                                "seat_col": seat_col,
                                "seat_row": seat_row
                            },
                            beforeSend: function () {
                                jQuery('#seat_result').html(
                                    '<span class=search-text style="display:block;background:#ddd:color:#000:font-weight:bold;text-align:center">Creating Seat Plan...</span>'
                                );
                            },
                            success: function (data) {
                                jQuery('#seat_result').html(data);
                            }
                        });
                        return false;
                    });

                });
            </script>


            <!-- Double Decker Seat Plan Here -->

            <h2 class="wbtm-deck-title"><?php _e('Seat Plan For Upper Deck', 'bus-ticket-booking-with-seat-reservation') ?></h2>
            <div class="wbtm-lower-bus-seat-maker-wrapper">
                <div class="wbtm-control-part">
                    <h3 class="wbtm-seat-title"><?php _e('Seat Maker', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                    <p class="wbtm-control-row">
                        <strong><?php _e('Total Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                        <input type="number" value='<?php if (array_key_exists('wbtm_seat_cols_dd', $values)) {
                            echo $values['wbtm_seat_cols_dd'][0];
                        } ?>' name="seat_col_dd" id='seat_col_dd' style="width: 70px;" pattern="[1-9]*"
                               inputmode="numeric"
                               min="0" max="">
                    </p>
                    <p class="wbtm-control-row">
                        <strong><?php _e('Total Seat Rows', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                        <input type="number" value='<?php if (array_key_exists('wbtm_seat_rows_dd', $values)) {
                            echo $values['wbtm_seat_rows_dd'][0];
                        } ?>' name="seat_rows_dd" id='seat_rows_dd' style="width: 70px;" pattern="[1-9]*"
                               inputmode="numeric" min="0" max="">
                    </p>
                    <p class="wbtm-control-row" style="position: relative">
                        <strong><?php _e('Price Increase', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                        <input type="number" value='<?php if (array_key_exists('wbtm_seat_dd_price_parcent', $values)) {
                            echo $values['wbtm_seat_dd_price_parcent'][0];
                        } ?>' name="wbtm_seat_dd_price_parcent" id='wbtm_seat_dd_price_parcent' style="width: 70px;"
                               pattern="[1-9]*" inputmode="numeric" min="0" max=""><span
                                style="position: absolute;right: 4px;top: 5px;color: #555;">%</span>
                    </p>
                    <p class="wbtm-control-row">
                        <button id="create_seat_plan_dd" class="create_seat_plan"><span
                                    class="dashicons dashicons-plus"></span><?php _e('Create Seat Plan', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </button>
                    </p>
                </div>

                <div class="wbtm-preview-part">
                    <h3 class="wbtm-seat-title"><?php _e('Seat Preview', 'bus-ticket-booking-with-seat-reservation') ?></h3>
                    <div id="seat_result_dd">
                        <?php
                        if (array_key_exists('wbtm_bus_seats_info_dd', $values)) {
                            $old = $values['wbtm_bus_seats_info_dd'][0];
                            $seatrows = $values['wbtm_seat_rows_dd'][0];
                            $seatcols = $values['wbtm_seat_cols_dd'][0];
                            $seats = unserialize($old);
                            ?>
                            <script type="text/javascript">
                                jQuery(document).ready(function ($) {
                                    $('#add-seat-row-dd').on('click', function () {
                                        var row = $('.empty-row-seat-dd.screen-reader-text').clone(true);
                                        row.removeClass('empty-row-seat-dd screen-reader-text');
                                        row.insertBefore('#repeatable-fieldset-seat-one-dd tbody>tr:last');
                                        var qtt = parseInt($('#seat_rows_dd').val(), 10);
                                        $('#seat_rows_dd').val(qtt + 1);
                                        return false;
                                    });
                                    $('.remove-seat-row-dd').on('click', function () {
                                        $(this).parents('tr').remove();
                                        var qtt = parseInt($('#seat_rows_dd').val(), 10);
                                        $('#seat_rows_dd').val(qtt - 1);
                                        return false;
                                    });
                                });
                            </script>
                            <table class="wbtm-seat-table" id="repeatable-fieldset-seat-one-dd" width="100%">
                                <tbody>
                                <?php
                                if (is_array($seats) && sizeof($seats) > 0) {
                                    foreach ($seats as $_seats) {
                                        ?>
                                        <tr>
                                            <?php
                                            for ($x = 1; $x <= $seatcols; $x++) {
                                                $text_field_name = "dd_seat" . $x;
                                                ?>
                                                <td align="center"><input type="text"
                                                                          value="<?php echo $_seats[$text_field_name]; ?>"
                                                                          name="<?php echo $text_field_name; ?>[]"
                                                                          class="text">
                                                </td>
                                                <?php
                                            }
                                            ?>
                                            <td align="center"><a class="button remove-seat-row-dd"
                                                                  href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                                <input type="hidden" name="bus_seat_panels_dd[]">
                                            </td>
                                        </tr>
                                    <?php }
                                } ?>
                                <!-- empty hidden one for jQuery -->
                                <tr class="empty-row-seat-dd screen-reader-text">
                                    <?php
                                    for ($row = 1; $row <= $seatcols; $row++) {
                                        ?>
                                        <td align="center"><input type="text" value=""
                                                                  name="dd_seat<?php echo $row; ?>[]"
                                                                  class="text"></td>
                                    <?php } ?>
                                    <td align="center"><a class="button remove-seat-row-dd"
                                                          href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a><input
                                                type="hidden" name="bus_seat_panels_dd[]"></td>
                                </tr>
                                </tbody>
                            </table>
                            <div id="add-seat-row-dd" class="add-seat-row-btn"><i class="fas fa-plus"></i></div>
                        <?php } ?>
                    </div>
                </div>


                <script type="text/javascript">
                    jQuery("#create_seat_plan_dd").click(function (e) {
                        e.preventDefault();
                        // alert('Yes');
                        seat_col = jQuery("#seat_col_dd").val().trim();
                        seat_row = jQuery("#seat_rows_dd").val().trim();
                        jQuery.ajax({
                            type: 'POST',
                            // url:wbtm_ajax.wbtm_ajaxurl,
                            url: wbtm_ajaxurl,
                            data: {
                                "action": "wbtm_seat_plan_dd",
                                "seat_col": seat_col,
                                "seat_row": seat_row
                            },
                            beforeSend: function () {
                                jQuery('#seat_result_dd').html(
                                    '<span class=search-text style="display:block;background:#ddd:color:#000:font-weight:bold;text-align:center">Creating Seat Plan...</span>'
                                );
                            },
                            success: function (data) {
                                jQuery('#seat_result_dd').html(data);
                            }
                        });
                        return false;
                    });
                </script>

            </div>
            <!-- double decker end -->
        </div>
        <?php
    }


    function wbtm_bus_seat_panels_meta_save($post_id)
    {
        // echo '<pre>'; print_r($_POST); die;
        global $post, $wbtmmain;
        if ($post) {
            $pid = $post->ID;
            if ($post->post_type != 'wbtm_bus') {
                return;
            }


            // Seat Type Conf
            $wbtm_seat_type_conf = $_POST['wbtm_seat_type_conf'];
            $wbtm_bus_no = $_POST['wbtm_bus_no'];
            $wbtm_total_seat = $_POST['wbtm_total_seat'];
            $wbtm_general_same_bus_return = isset($_POST['wbtm_general_same_bus_return']) ? $_POST['wbtm_general_same_bus_return'] : 'no';
            

            // Routing
            $bus_boarding_points = array();
            $bus_dropping_points = array();
            $boarding_points = $_POST['wbtm_bus_bp_stops_name'];
            $boarding_time = isset($_POST['wbtm_bus_bp_start_time']) ? $_POST['wbtm_bus_bp_start_time'] : '';
            $dropping_points = $_POST['wbtm_bus_next_stops_name'];
            $dropping_time = isset($_POST['wbtm_bus_next_end_time']) ? $_POST['wbtm_bus_next_end_time'] : '';

            if (!empty($boarding_points)) {
                $i = 0;
                foreach ($boarding_points as $point) {
                    if ($point != '') {
                        $bus_boarding_points[$i]['wbtm_bus_bp_stops_name'] = $point;
                        $bus_boarding_points[$i]['wbtm_bus_bp_start_time'] = $boarding_time[$i];
                    }
                    $i++;
                }
            }

            if (!empty($dropping_points)) {
                $i = 0;
                foreach ($dropping_points as $point) {
                    if ($point != '') {
                        $bus_dropping_points[$i]['wbtm_bus_next_stops_name'] = $point;
                        $bus_dropping_points[$i]['wbtm_bus_next_end_time'] = $dropping_time[$i];
                    }
                    $i++;
                }
            }
            update_post_meta($pid, 'wbtm_bus_bp_stops', $bus_boarding_points);
            update_post_meta($pid, 'wbtm_bus_next_stops', $bus_dropping_points);
            // Routing END

            // Return Routing
            $bus_boarding_points_return = array();
            $bus_dropping_points_return = array();
            $boarding_points_return = isset($_POST['wbtm_bus_bp_stops_name_return']) ? $_POST['wbtm_bus_bp_stops_name_return'] : '';
            $boarding_time_return = isset($_POST['wbtm_bus_bp_start_time_return']) ? $_POST['wbtm_bus_bp_start_time_return'] : '';
            $dropping_points_return = isset($_POST['wbtm_bus_next_stops_name_return']) ? $_POST['wbtm_bus_next_stops_name_return'] : '';
            $dropping_time_return = isset($_POST['wbtm_bus_next_end_time_return']) ? $_POST['wbtm_bus_next_end_time_return'] : '';

            if (!empty($boarding_points_return)) {
                $i = 0;
                foreach ($boarding_points_return as $point) {
                    if ($point != '') {
                        $bus_boarding_points_return[$i]['wbtm_bus_bp_stops_name'] = $point;
                        $bus_boarding_points_return[$i]['wbtm_bus_bp_start_time'] = $boarding_time_return[$i];
                    }
                    $i++;
                }

            }

            if (!empty($dropping_points_return)) {
                $i = 0;
                foreach ($dropping_points_return as $point) {
                    if ($point != '') {
                        $bus_dropping_points_return[$i]['wbtm_bus_next_stops_name'] = $point;
                        $bus_dropping_points_return[$i]['wbtm_bus_next_end_time'] = $dropping_time_return[$i];
                    }
                    $i++;
                }

            }
            // Return Routing END


            // Seat Prices
            $seat_prices = array();
            $boarding_points = $_POST['wbtm_bus_bp_price_stop'];
            $dropping_points = $_POST['wbtm_bus_dp_price_stop'];
            $adult_prices = $_POST['wbtm_bus_price'];
            $adult_prices_return = $_POST['wbtm_bus_price_return'];
            $child_prices = $_POST['wbtm_bus_child_price'];
            $child_prices_return = $_POST['wbtm_bus_child_price_return'];
            $infant_prices = $_POST['wbtm_bus_infant_price'];
            $infant_prices_return = $_POST['wbtm_bus_infant_price_return'];

            if (!empty($boarding_points)) {
                $i = 0;
                foreach ($boarding_points as $point) {
                    if ($point && $dropping_points[$i] && $adult_prices[$i]) {
                        $seat_prices[$i]['wbtm_bus_bp_price_stop'] = $point;
                        $seat_prices[$i]['wbtm_bus_dp_price_stop'] = $dropping_points[$i];
                        $seat_prices[$i]['wbtm_bus_price'] = $adult_prices[$i];
                        $seat_prices[$i]['wbtm_bus_price_return'] = $adult_prices_return[$i];
                        $seat_prices[$i]['wbtm_bus_child_price'] = $child_prices[$i];
                        $seat_prices[$i]['wbtm_bus_child_price_return'] = $child_prices_return[$i];
                        $seat_prices[$i]['wbtm_bus_infant_price'] = $infant_prices[$i];
                        $seat_prices[$i]['wbtm_bus_infant_price_return'] = $infant_prices_return[$i];
                    }

                    $i++;
                }
            }

            // Return Route Price
            $seat_prices_return = array();
            $boarding_points_return = isset($_POST['wbtm_bus_bp_price_stop_return']) ? $_POST['wbtm_bus_bp_price_stop_return'] : '';
            $dropping_points_return = isset($_POST['wbtm_bus_dp_price_stop_return']) ? $_POST['wbtm_bus_dp_price_stop_return'] : '';
            $adult_prices_return = isset($_POST['wbtm_bus_price_r']) ? $_POST['wbtm_bus_price_r'] : '';
            $adult_prices_return_discount = isset($_POST['wbtm_bus_price_return_discount']) ? $_POST['wbtm_bus_price_return_discount'] : '';
            $child_prices_return = isset($_POST['wbtm_bus_child_price_r']) ? $_POST['wbtm_bus_child_price_r'] : '';
            $child_prices_return_discount = isset($_POST['wbtm_bus_child_price_return_discount']) ? $_POST['wbtm_bus_child_price_return_discount'] : '';
            $infant_prices_return = isset($_POST['wbtm_bus_infant_price_r']) ? $_POST['wbtm_bus_infant_price_r'] : '';
            $infant_prices_return_discount = isset($_POST['wbtm_bus_infant_price_return_discount']) ? $_POST['wbtm_bus_infant_price_return_discount'] : '';

            if (!empty($boarding_points_return)) {
                $i = 0;
                foreach ($boarding_points_return as $point) {
                    if ($point && $dropping_points_return[$i] && $adult_prices_return[$i]) {
                        $seat_prices_return[$i]['wbtm_bus_bp_price_stop'] = $point;
                        $seat_prices_return[$i]['wbtm_bus_dp_price_stop'] = $dropping_points_return[$i];
                        $seat_prices_return[$i]['wbtm_bus_price'] = $adult_prices_return[$i];
                        $seat_prices_return[$i]['wbtm_bus_price_return'] = $adult_prices_return_discount[$i];
                        $seat_prices_return[$i]['wbtm_bus_child_price'] = $child_prices_return[$i];
                        $seat_prices_return[$i]['wbtm_bus_child_price_return'] = $child_prices_return_discount[$i];
                        $seat_prices_return[$i]['wbtm_bus_infant_price'] = $infant_prices_return[$i];
                        $seat_prices_return[$i]['wbtm_bus_infant_price_return'] = $infant_prices_return_discount[$i];
                    }

                    $i++;
                }

            }
            // Return Route Price

            // Subscription Price
            $subscription_route_type = isset($_POST['wbtm_subcsription_route_type']) ? $_POST['wbtm_subcsription_route_type'] : '';
            if (isset($_POST['mtsa_billing_price_adult'])) {
                $mtsa_bus_subs_prices = array();
                $mtsa_bus_zone = $_POST['mtsa_bus_zone'];
                $mtsa_boarding_point = $_POST['mtsa_boarding_point'];
                $mtsa_dropping_point = $_POST['mtsa_dropping_point'];
                $mtsa_billing_type = $_POST['mtsa_billing_type'];
                $mtsa_checking_limit = $_POST['mtsa_checking_limit'];

                $mtsa_billing_price_adult = $_POST['mtsa_billing_price_adult'];
                $mtsa_billing_price_child = $_POST['mtsa_billing_price_child'];
                $mtsa_billing_price_infant = $_POST['mtsa_billing_price_infant'];


                $count = count($mtsa_billing_price_adult);
                for ($r = 0; $r < $count; $r++) {
                    if ($mtsa_billing_price_adult[$r] != '') {
                        $mtsa_bus_subs_prices[$r]['mtsa_bus_zone'] = $mtsa_bus_zone[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_boarding_point'] = $mtsa_boarding_point[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_dropping_point'] = $mtsa_dropping_point[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_billing_type'] = $mtsa_billing_type[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_checking_limit'] = $mtsa_checking_limit[$r];

                        $mtsa_bus_subs_prices[$r]['mtsa_billing_price_adult'] = $mtsa_billing_price_adult[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_billing_price_child'] = $mtsa_billing_price_child[$r];
                        $mtsa_bus_subs_prices[$r]['mtsa_billing_price_infant'] = $mtsa_billing_price_infant[$r];
                    }
                }


                update_post_meta($pid, 'mtsa_bus_subs_prices', $mtsa_bus_subs_prices);

            }
            update_post_meta($pid, 'mtsa_subscription_route_type', $subscription_route_type);

            // Tax
            $_tax_status 			= isset($_POST['_tax_status']) ? strip_tags($_POST['_tax_status']) : 'none';
            $_tax_class 			= isset($_POST['_tax_class']) ? strip_tags($_POST['_tax_class']) : '';

            update_post_meta($pid, '_tax_status', $_tax_status);
            update_post_meta($pid, '_tax_class', $_tax_class);

            // Private Pricing
            if (isset($_POST['mtpa_private_boarding_point'])) {
                $mtsa_private_price_array = array();
                $p_boarding = $_POST['mtpa_private_boarding_point'];
                $p_dropping = $_POST['mtpa_private_dropping_point'];
                $p_price = $_POST['mtpa_private_price_adult'];
                $count = count($p_boarding);
                for ($r = 0; $r < $count; $r++) {
                    if ($p_boarding[$r] != '') {
                        $mtsa_private_price_array[$r]['mtpa_private_boarding_point'] = $p_boarding[$r];
                        $mtsa_private_price_array[$r]['mtpa_private_dropping_point'] = $p_dropping[$r];
                        $mtsa_private_price_array[$r]['mtpa_private_price_adult'] = $p_price[$r];
                    }
                }
                update_post_meta($pid, 'mtsa_bus_private_prices', $mtsa_private_price_array);
            }

            // echo '<pre>'; print_r($mtsa_bus_subs_prices); die;
            // Subscription Price END
            // Seat Prices END

            // Extra services
            $extra_service_old = get_post_meta($post_id, 'mep_events_extra_prices', true);
            $extra_service_new = array();
            $names = $_POST['option_name'];
            $urls = $_POST['option_price'];
            $qty = $_POST['option_qty'];
            $qty_type = $_POST['option_qty_type'];
            $order_id = 0;
            $count = count($names);

            for ($i = 0; $i < $count; $i++) {
                if ($names[$i] != '') :
                    $extra_service_new[$i]['option_name'] = stripslashes(strip_tags($names[$i]));
                endif;

                if ($urls[$i] != '') :
                    $extra_service_new[$i]['option_price'] = stripslashes(strip_tags($urls[$i]));
                endif;

                if ($qty[$i] != '') :
                    $extra_service_new[$i]['option_qty'] = stripslashes(strip_tags($qty[$i]));
                endif;

                if ($qty_type[$i] != '') :
                    $extra_service_new[$i]['option_qty_type'] = stripslashes(strip_tags($qty_type[$i]));
                endif;
            }

            if (!empty($extra_service_new) && $extra_service_new != $extra_service_old) {
                update_post_meta($post_id, 'mep_events_extra_prices', $extra_service_new);
            } elseif (empty($extra_service_new) && $extra_service_old) {
                delete_post_meta($post_id, 'mep_events_extra_prices', $extra_service_old);
            }
            // Extra services END

            // ******Pickup Point******
            $selected_city_key = 'wbtm_pickpoint_selected_city';
            $selected_pickpoint_name = 'wbtm_selected_pickpoint_name_';
            $selected_pickpoint_time = 'wbtm_selected_pickpoint_time_';

            if (isset($_POST['wbtm_pickpoint_selected_city'])) {
                $selected_city = $_POST['wbtm_pickpoint_selected_city'];


                if (!empty($selected_city)) {

                    $selected_city_str = implode(',', $selected_city);

                    // If need delete
                    $prev_selected_city = get_post_meta($pid, $selected_city_key, true);
                    if ($prev_selected_city) {
                        $prev_selected_city = explode(',', $prev_selected_city);

                        $diff = array_diff($prev_selected_city, $selected_city);
                        if (!empty($diff)) {

                            $diff = array_values($diff);
                            foreach ($diff as $s) {
                                delete_post_meta($pid, 'wbtm_selected_pickpoint_name_' . $s);
                            }
                        }
                    }
                    // If need delete END

                    update_post_meta($pid, $selected_city_key, $selected_city_str);

                    foreach ($selected_city as $city) {
                        $m_array = array();
                        $i = 0;
                        foreach ($_POST[$selected_pickpoint_name . $city] as $pickpoint) {

                            $m_array[$i] = array(
                                'pickpoint' => $_POST[$selected_pickpoint_name . $city][$i],
                                'time' => $_POST[$selected_pickpoint_time . $city][$i],
                            );

                            $i++;
                        }

                        update_post_meta($pid, $selected_pickpoint_name . $city, serialize($m_array));
                    }

                }
            } else {
                // If need delete
                $prev_selected_city = get_post_meta($pid, $selected_city_key, true);
                if ($prev_selected_city) {
                    $prev_selected_city = explode(',', $prev_selected_city);

                    delete_post_meta($pid, $selected_city_key);

                    foreach ($prev_selected_city as $s) {
                        delete_post_meta($pid, 'wbtm_selected_pickpoint_name_' . $s);
                    }
                }
                // If need delete END
            }
            // Pickup Point END

            // ******Pickup Point Return******
            $selected_city_key = 'wbtm_pickpoint_selected_city_return';
            $selected_pickpoint_name = 'wbtm_selected_pickpoint_return_name_';
            $selected_pickpoint_time = 'wbtm_selected_pickpoint_return_time_';
            // echo '<pre>'; print_r($_POST); die;
            if (isset($_POST['wbtm_pickpoint_selected_city_return'])) {
                $selected_city = $_POST['wbtm_pickpoint_selected_city_return'];


                if (!empty($selected_city)) {

                    $selected_city_str = implode(',', $selected_city);

                    // If need delete
                    $prev_selected_city = get_post_meta($pid, $selected_city_key, true);
                    if ($prev_selected_city) {
                        $prev_selected_city = explode(',', $prev_selected_city);

                        $diff = array_diff($prev_selected_city, $selected_city);
                        if (!empty($diff)) {

                            $diff = array_values($diff);
                            foreach ($diff as $s) {
                                delete_post_meta($pid, 'wbtm_selected_pickpoint_return_name_' . $s);
                            }
                        }
                    }
                    // If need delete END

                    update_post_meta($pid, $selected_city_key, $selected_city_str);

                    foreach ($selected_city as $city) {
                        $m_array = array();
                        $i = 0;
                        foreach ($_POST[$selected_pickpoint_name . $city] as $pickpoint) {

                            $m_array[$i] = array(
                                'pickpoint' => $_POST[$selected_pickpoint_name . $city][$i],
                                'time' => $_POST[$selected_pickpoint_time . $city][$i],
                            );

                            $i++;
                        }

                        update_post_meta($pid, $selected_pickpoint_name . $city, serialize($m_array));
                    }

                }
            } else {
                // If need delete
                $prev_selected_city = get_post_meta($pid, $selected_city_key, true);
                if ($prev_selected_city) {
                    $prev_selected_city = explode(',', $prev_selected_city);

                    delete_post_meta($pid, $selected_city_key);

                    foreach ($prev_selected_city as $s) {
                        delete_post_meta($pid, 'wbtm_selected_pickpoint_return_name_' . $s);
                    }
                }
                // If need delete END
            }
            // Pickup Point Return END

            $wbtm_car_type = isset($_POST['mtpa_car_type']) ? $_POST['mtpa_car_type'] : null;
            update_post_meta($pid, 'mtpa_car_type', $wbtm_car_type);
            // Car Type

            // Car Type END

            // Ondates & offdates
            $ondates = $_POST['wbtm_bus_on_dates'];
            update_post_meta($pid, 'wbtm_bus_on_dates', $ondates);
            // Offday schedule
            $offday_schedule_array = array();
            $offday_date_from = $_POST['wbtm_od_offdate_from'];
            $offday_date_to = $_POST['wbtm_od_offdate_to'];
            $offday_time_from = $_POST['wbtm_od_offtime_from'];
            $offday_time_to = $_POST['wbtm_od_offtime_to'];

            if (is_array($offday_date_from) && !empty($offday_date_from)) {
                $i = 0;
                for ($i = 0; $i < count($offday_date_from); $i++) {
                    if ($offday_date_from[$i] != '') {
                        $offday_schedule_array[$i]['from_date'] = $offday_date_from[$i];
                        $offday_schedule_array[$i]['from_time'] = $offday_time_from[$i];
                        $offday_schedule_array[$i]['to_date'] = $offday_date_to[$i];
                        $offday_schedule_array[$i]['to_time'] = $offday_time_to[$i];
                    }
                }
            }
            update_post_meta($pid, 'wbtm_offday_schedule', $offday_schedule_array);
            $od_sun = isset($_POST['offday_sun']) ? strip_tags($_POST['offday_sun']) : '';
            $od_mon = isset($_POST['offday_mon']) ? strip_tags($_POST['offday_mon']) : '';
            $od_tue = isset($_POST['offday_tue']) ? strip_tags($_POST['offday_tue']) : '';
            $od_wed = isset($_POST['offday_wed']) ? strip_tags($_POST['offday_wed']) : '';
            $od_thu = isset($_POST['offday_thu']) ? strip_tags($_POST['offday_thu']) : '';
            $od_fri = isset($_POST['offday_fri']) ? strip_tags($_POST['offday_fri']) : '';
            $od_sat = isset($_POST['offday_sat']) ? strip_tags($_POST['offday_sat']) : '';
            update_post_meta($pid, 'offday_sun', $od_sun);
            update_post_meta($pid, 'offday_mon', $od_mon);
            update_post_meta($pid, 'offday_tue', $od_tue);
            update_post_meta($pid, 'offday_wed', $od_wed);
            update_post_meta($pid, 'offday_thu', $od_thu);
            update_post_meta($pid, 'offday_fri', $od_fri);
            update_post_meta($pid, 'offday_sat', $od_sat);
            // Offday schedule END
            // Ondates & offdates END

            // Ondates & offdates Return
            $ondates_return = isset($_POST['wbtm_bus_on_dates_return']) ? $_POST['wbtm_bus_on_dates_return'] : '';

            // Offday schedule
            $offday_schedule_array = array();
            $offday_date_from = isset($_POST['wbtm_od_offdate_from_return']) ? $_POST['wbtm_od_offdate_from_return'] : '';
            $offday_date_to = isset($_POST['wbtm_od_offdate_to_return']) ? $_POST['wbtm_od_offdate_to_return'] : '';
            $offday_time_from = isset($_POST['wbtm_od_offtime_from_return']) ? $_POST['wbtm_od_offtime_from_return'] : '';
            $offday_time_to = isset($_POST['wbtm_od_offtime_to_return']) ? $_POST['wbtm_od_offtime_to_return'] : '';

            if (is_array($offday_date_from) && !empty($offday_date_from)) {
                $i = 0;
                for ($i = 0; $i < count($offday_date_from); $i++) {
                    if ($offday_date_from[$i] != '') {
                        $offday_schedule_array[$i]['from_date'] = $offday_date_from[$i];
                        $offday_schedule_array[$i]['from_time'] = $offday_time_from[$i];
                        $offday_schedule_array[$i]['to_date'] = $offday_date_to[$i];
                        $offday_schedule_array[$i]['to_time'] = $offday_time_to[$i];
                    }
                }

            }


            $od_sun = isset($_POST['offday_sun_return']) ? strip_tags($_POST['offday_sun_return']) : '';
            $od_mon = isset($_POST['offday_mon_return']) ? strip_tags($_POST['offday_mon_return']) : '';
            $od_tue = isset($_POST['offday_tue_return']) ? strip_tags($_POST['offday_tue_return']) : '';
            $od_wed = isset($_POST['offday_wed_return']) ? strip_tags($_POST['offday_wed_return']) : '';
            $od_thu = isset($_POST['offday_thu_return']) ? strip_tags($_POST['offday_thu_return']) : '';
            $od_fri = isset($_POST['offday_fri_return']) ? strip_tags($_POST['offday_fri_return']) : '';
            $od_sat = isset($_POST['offday_sat_return']) ? strip_tags($_POST['offday_sat_return']) : '';

            if($wbtm_general_same_bus_return === 'yes') { // All return data
                // Route
                update_post_meta($pid, 'wbtm_bus_bp_stops_return', $bus_boarding_points_return);
                update_post_meta($pid, 'wbtm_bus_next_stops_return', $bus_dropping_points_return);

                // Seat Price
                update_post_meta($pid, 'wbtm_bus_prices_return', $seat_prices_return);

                // On dates
                update_post_meta($pid, 'wbtm_bus_on_dates_return', $ondates_return);

                // Off dates
                update_post_meta($pid, 'wbtm_offday_schedule_return', $offday_schedule_array);

                // Day Off
                $od_sun ? update_post_meta($pid, 'offday_sun_return', $od_sun) : null;
                $od_mon ? update_post_meta($pid, 'offday_mon_return', $od_mon) : null;
                $od_tue ? update_post_meta($pid, 'offday_tue_return', $od_tue) : null;
                $od_wed ? update_post_meta($pid, 'offday_wed_return', $od_wed) : null;
                $od_thu ? update_post_meta($pid, 'offday_thu_return', $od_thu) : null;
                $od_fri ? update_post_meta($pid, 'offday_fri_return', $od_fri) : null;
                $od_sat ? update_post_meta($pid, 'offday_sat_return', $od_sat) : null;
            }
            // Offday schedule Return END
            // Ondates & offdates Return END


            if (isset($_POST['seat_col']) && isset($_POST['seat_rows']) && isset($_POST['bus_seat_panels'])) {
                $seat_col = strip_tags($_POST['seat_col']);
                $seat_row = strip_tags($_POST['seat_rows']);
                $old = get_post_meta($post_id, 'wbtm_bus_seats_info', true);
                $new = array();
                $bus_seat_panels = $_POST['bus_seat_panels'];
                $count = count($bus_seat_panels) - 2;
                for ($r = 0; $r <= $count; $r++) {
                    for ($x = 1; $x <= $seat_col; $x++) {
                        $text_field_name = "seat" . $x;
                        $seat_type_name = "seat_types" . $x;
                        $new[$r][$text_field_name] = stripslashes(strip_tags($_POST[$text_field_name][$r]));
                        //$new[$r][$seat_type_name] = implode(',',$_POST[$seat_type_name][$r] );
                    }
                }

                $bus_start_time = $wbtmmain->get_bus_start_time($post_id);
                update_post_meta($post_id, 'wbtm_bus_start_time', $bus_start_time);

                if (!empty($new) && $new != $old)
                    update_post_meta($post_id, 'wbtm_bus_seats_info', $new);
                elseif (empty($new) && $old)
                    delete_post_meta($post_id, 'wbtm_bus_seats_info', $old);

                $update_seat_col = update_post_meta($pid, 'wbtm_seat_cols', $seat_col);
                $update_seat_row = update_post_meta($pid, 'wbtm_seat_rows', $seat_row);
            }

            // maybe_unserialize()

            // Save Double Deacker Seat Data

            if (isset($_POST['seat_col_dd']) && isset($_POST['seat_rows_dd']) && isset($_POST['bus_seat_panels_dd'])) {
                // echo '<pre>'; print_r($_POST); die;
                $seat_col_dd = strip_tags($_POST['seat_col_dd']);
                $seat_row_dd = strip_tags($_POST['seat_rows_dd']);
                $wbtm_seat_dd_price_parcent = strip_tags($_POST['wbtm_seat_dd_price_parcent']);
                $old = get_post_meta($post_id, 'wbtm_bus_seats_info_dd', true);
                $new_dd = array();
                $bus_seat_panels_dd = $_POST['bus_seat_panels_dd'];
                $count = count($bus_seat_panels_dd) - 2;
                for ($r = 0; $r <= $count; $r++) {
                    for ($x = 1; $x <= $seat_col_dd; $x++) {
                        $text_field_name = "dd_seat" . $x;
                        $new_dd[$r][$text_field_name] = stripslashes(strip_tags($_POST[$text_field_name][$r]));
                    }
                }

                if (!empty($new) && $new != $old)
                    update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $new_dd);
                elseif (empty($new) && $old)
                    delete_post_meta($post_id, 'wbtm_bus_seats_info_dd', $old);

                update_post_meta($pid, 'wbtm_seat_cols_dd', $seat_col_dd);
                update_post_meta($pid, 'wbtm_seat_rows_dd', $seat_row_dd);
                update_post_meta($pid, 'wbtm_seat_dd_price_parcent', $wbtm_seat_dd_price_parcent);
            }

            update_post_meta($pid, 'wbtm_seat_type_conf', $wbtm_seat_type_conf);
            update_post_meta($pid, 'wbtm_bus_no', $wbtm_bus_no);
            update_post_meta($pid, 'wbtm_total_seat', $wbtm_total_seat);
            update_post_meta($pid, 'wbtm_general_same_bus_return', $wbtm_general_same_bus_return);
            update_post_meta($pid, 'wbtm_bus_prices', $seat_prices);
            


            update_post_meta($pid, '_price', 0);
            $driver_seat_position = strip_tags($_POST['driver_seat_position']);
            $update_wbtm_driver_seat_position = update_post_meta($pid, 'driver_seat_position', $driver_seat_position);
            $update_seat_stock_status = update_post_meta($pid, '_sold_individually', 'yes');
        }
    }

    public function wbtmInfo()
    {


        // $bus_information = array(
        //     'page_nav' => __('<i class="fas fa-cog"></i> Nav Title 2', 'bus-ticket-booking-with-seat-reservation'),
        //     'priority' => 10,
        //     'sections' => array(
        //         'section_2' => array(
        //             'title' => __('', 'bus-ticket-booking-with-seat-reservation'),
        //             'description' => __('', 'bus-ticket-booking-with-seat-reservation'),
        //             'options' => array(

        //                 array(
        //                     'id' => 'wbtm_bus_no',
        //                     'title' => __('Coach No', 'bus-ticket-booking-with-seat-reservation'),
        //                     'details' => __('Please enter coach no here', 'bus-ticket-booking-with-seat-reservation'),
        //                     'type' => 'text',
        //                     'placeholder' => __('Coach No', 'bus-ticket-booking-with-seat-reservation'),
        //                 ),

        //                 array(
        //                     'id' => 'wbtm_total_seat',
        //                     'title' => __('Total Seat', 'bus-ticket-booking-with-seat-reservation'),
        //                     'details' => __('Please enter Total Seat here', 'bus-ticket-booking-with-seat-reservation'),
        //                     'type' => 'text',
        //                     'placeholder' => __('Total Seat', 'bus-ticket-booking-with-seat-reservation'),
        //                 ),

        //             )
        //         ),

        //     ),
        // );

        // $info_args = array(
        //     'meta_box_id' => 'bus_meta_boxes_info',
        //     'meta_box_title' => '<span class="dashicons dashicons-info"></span>' . __('Bus Information', 'bus-ticket-booking-with-seat-reservation'),
        //     //'callback'       => '_meta_box_callback',
        //     'screen' => array('wbtm_bus'),
        //     'context' => 'normal', // 'normal', 'side', and 'advanced'
        //     'priority' => 'high', // 'high', 'low'
        //     'callback_args' => array(),
        //     'nav_position' => 'none', // right, top, left, none
        //     'item_name' => "MagePeople",
        //     'item_version' => "2.0",
        //     'panels' => array(
        //         'bus_information' => $bus_information
        //     ),
        // );

        // new AddMetaBox($info_args);
    }

    public function wbtmRouting()
    {
        global $post;
        $wbbm_bus_bp = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops', true));
        $wbtm_bus_next_stops = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_next_stops', true));

        $wbbm_bus_bp_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops_return', true));
        $wbtm_bus_next_stops_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_next_stops_return', true));

        $values = get_post_custom($post->ID);

        $get_terms_default_attributes = array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false
        );
        $terms = get_terms($get_terms_default_attributes);
        if ($terms) {
            ?>

            <div class="bus-stops-wrapper">
                <div class="bus-stops-left-col">
                    <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <table class="repeatable-fieldset">
                        <tr>
                            <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th width="30px"><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th></th>
                        </tr>
                        <tbody>
                        <?php
                        if ($wbbm_bus_bp) :
                            $count = 0;
                            foreach ($wbbm_bus_bp as $field) {
                                ?>
                                <tr>
                                    <td align="center">
                                        <select name="wbtm_bus_bp_stops_name[]" class='seat_type'>
                                            <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                            <?php
                                            foreach ($terms as $term) {
                                                ?>
                                                <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_bp_stops_name']) {
                                                    echo "Selected";
                                                } ?>><?php echo $term->name; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td align="center" width="30px"><input type="text" data-clocklet
                                                                           name='wbtm_bus_bp_start_time[]'
                                                                           value="<?php if ($field['wbtm_bus_bp_start_time'] != '') echo esc_attr($field['wbtm_bus_bp_start_time']); ?>"
                                                                           class="text"></td>
                                    <td align="center"><a class="button wbtm-remove-row-t" href="#"><i
                                                    class="fas fa-minus-circle"></i>
                                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a></td>
                                </tr>
                                <?php
                                $count++;
                            }
                        else :
                            // show a blank one
                        endif;
                        ?>

                        <!-- empty hidden one for jQuery -->
                        <tr class="mtsa-empty-row-t">
                            <td align="center">
                                <select name="wbtm_bus_bp_stops_name[]" class='seat_type'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                        ?>
                                        <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td align="center"><input type="text" data-clocklet name='wbtm_bus_bp_start_time[]' value=""
                                                      class="text"></td>
                            <td align="center"><a class="button remove-bp-row" href="#"><i
                                            class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a></td>
                        </tr>
                        </tbody>
                    </table>
                    <a class="button wbtom-tb-repeat-btn" href="#"><i
                                class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </a>
                </div>

                <div class="bus-stops-right-col">
                    <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                    <table class="repeatable-fieldset">
                        <tr>
                            <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th></th>
                        </tr>
                        <tbody>
                        <?php
                        if ($wbtm_bus_next_stops) :
                            $count = 0;
                            foreach ($wbtm_bus_next_stops as $field) {
                                ?>
                                <tr>
                                    <td align="center">
                                        <select name="wbtm_bus_next_stops_name[]" class='seat_type'>
                                            <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                            <?php
                                            foreach ($terms as $term) {
                                                ?>
                                                <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_next_stops_name']) {
                                                    echo "Selected";
                                                } ?>><?php echo $term->name; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time[]'
                                                              value="<?php if ($field['wbtm_bus_next_end_time'] != '') echo esc_attr($field['wbtm_bus_next_end_time']); ?>"
                                                              class="text"></td>
                                    <td align="center"><a class="button wbtm-remove-row-t" href="#"><i
                                                    class="fas fa-minus-circle"></i>
                                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a></td>
                                </tr>
                                <?php
                                $count++;
                            }
                        else :
                            // show a blank one
                        endif;
                        ?>

                        <!-- empty hidden one for jQuery -->
                        <tr class="mtsa-empty-row-t">
                            <td align="center">
                                <select name="wbtm_bus_next_stops_name[]" class='seat_type'>
                                    <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php
                                    foreach ($terms as $term) {
                                        ?>
                                        <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </td>
                            <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time[]' value=""
                                                      class="text"></td>
                            <td align="center"><a class="button remove-bp-row" href="#"><i
                                            class="fas fa-minus-circle"></i>
                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a></td>
                        </tr>
                        </tbody>
                    </table>
                    <a class="button wbtom-tb-repeat-btn" href="#"><i
                                class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </a>
                </div>

            </div>

            <!-- Return Routing -->
            <div class="wbtm-only-for-return-enable">
                <h3 class="wbtm-single-return-header"><?php _e('Return Route', 'bus-ticket-booking-with-seat-reservation') ?>:</h3>
                <div class="bus-stops-wrapper">
                    <div class="bus-stops-left-col">
                        <h3 class="bus-tops-sec-title"><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <table class="repeatable-fieldset">
                            <tr>
                                <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th width="30px"><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th></th>
                            </tr>
                            <tbody>
                            <?php
                            if ($wbbm_bus_bp_return) :
                                $count = 0;
                                foreach ($wbbm_bus_bp_return as $field) {
                                    ?>
                                    <tr>
                                        <td align="center">
                                            <select name="wbtm_bus_bp_stops_name_return[]" class='seat_type'>
                                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                                <?php
                                                foreach ($terms as $term) {
                                                    ?>
                                                    <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_bp_stops_name']) {
                                                        echo "Selected";
                                                    } ?>><?php echo $term->name; ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td align="center" width="30px"><input type="text" data-clocklet
                                                                            name='wbtm_bus_bp_start_time_return[]'
                                                                            value="<?php if ($field['wbtm_bus_bp_start_time'] != '') echo esc_attr($field['wbtm_bus_bp_start_time']); ?>"
                                                                            class="text"></td>
                                        <td align="center"><a class="button wbtm-remove-row-t" href="#"><i
                                                        class="fas fa-minus-circle"></i>
                                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </a></td>
                                    </tr>
                                    <?php
                                    $count++;
                                }
                            else :
                                // show a blank one
                            endif;
                            ?>

                            <!-- empty hidden one for jQuery -->
                            <tr class="mtsa-empty-row-t">
                                <td align="center">
                                    <select name="wbtm_bus_bp_stops_name_return[]" class='seat_type'>
                                        <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php
                                        foreach ($terms as $term) {
                                            ?>
                                            <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td align="center"><input type="text" data-clocklet name='wbtm_bus_bp_start_time_return[]' value=""
                                                        class="text"></td>
                                <td align="center"><a class="button remove-bp-row" href="#"><i
                                                class="fas fa-minus-circle"></i>
                                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a></td>
                            </tr>
                            </tbody>
                        </table>
                        <a class="button wbtom-tb-repeat-btn" href="#"><i
                                    class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a>
                    </div>

                    <div class="bus-stops-right-col">
                        <h3 class="bus-tops-sec-title"><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <table class="repeatable-fieldset">
                            <tr>
                                <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th><?php _e('Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th></th>
                            </tr>
                            <tbody>
                            <?php
                            if ($wbtm_bus_next_stops_return) :
                                $count = 0;
                                foreach ($wbtm_bus_next_stops_return as $field) {
                                    ?>
                                    <tr>
                                        <td align="center">
                                            <select name="wbtm_bus_next_stops_name_return[]" class='seat_type'>
                                                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                                <?php
                                                foreach ($terms as $term) {
                                                    ?>
                                                    <option value="<?php echo $term->name; ?>" <?php if ($term->name == $field['wbtm_bus_next_stops_name']) {
                                                        echo "Selected";
                                                    } ?>><?php echo $term->name; ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time_return[]'
                                                                value="<?php if ($field['wbtm_bus_next_end_time'] != '') echo esc_attr($field['wbtm_bus_next_end_time']); ?>"
                                                                class="text"></td>
                                        <td align="center"><a class="button wbtm-remove-row-t" href="#"><i
                                                        class="fas fa-minus-circle"></i>
                                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </a></td>
                                    </tr>
                                    <?php
                                    $count++;
                                }
                            else :
                                // show a blank one
                            endif;
                            ?>

                            <!-- empty hidden one for jQuery -->
                            <tr class="mtsa-empty-row-t">
                                <td align="center">
                                    <select name="wbtm_bus_next_stops_name_return[]" class='seat_type'>
                                        <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php
                                        foreach ($terms as $term) {
                                            ?>
                                            <option value="<?php echo $term->name; ?>"><?php echo $term->name; ?>
                                            </option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td align="center"><input type="text" data-clocklet name='wbtm_bus_next_end_time_return[]' value=""
                                                        class="text"></td>
                                <td align="center"><a class="button remove-bp-row" href="#"><i
                                                class="fas fa-minus-circle"></i>
                                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a></td>
                            </tr>
                            </tbody>
                        </table>
                        <a class="button wbtom-tb-repeat-btn" href="#"><i
                                    class="fas fa-plus"></i><?php _e('Add More', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a>
                    </div>

                </div>
            </div>

            <?php
        } else {
            echo "<div style='padding: 10px 0;text-align: center;background: #d23838;color: #fff;border: 5px solid #ff2d2d;padding: 5px;font-size: 16px;display: block;margin: 20px;'>Please Enter some bus stops first. <a style='color:#fff' href='" . get_admin_url() . "edit-tags.php?taxonomy=wbtm_bus_stops&post_type=wbtm_bus'>Click here for bus stops</a></div>";
        }
    }

    public function wbtmPricing()
    {

        global $wbtmmain, $wbtmcore, $post;

        $settings = get_option('wbtm_bus_settings');
        $val = isset($settings['bus_return_discount']) ? $settings['bus_return_discount'] : 'no';
        if ($val == 'yes') {
            $return_class = 'mage-return-class-enable';
        } else {
            $return_class = 'mage-return-class-disable';
        }

        // Boarding Points
        $boarding_points = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops', true));
        if ($boarding_points) {
            $boarding_points = array_column($boarding_points, 'wbtm_bus_bp_stops_name');
        }
        // Boarding Points
        $dropping_points = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_next_stops', true));
        if ($dropping_points) {
            $dropping_points = array_column($dropping_points, 'wbtm_bus_next_stops_name');
        }
        // Routing
        $get_routes = array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false
        );
        $routes = get_terms($get_routes);
        // Prices
        $prices = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_prices', true));
        $prices_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_prices_return', true));
        ?>

        <div id="wbtm_general_price" class="wbtm_content_wrapper">
            <div class="wbtm_content_inner">
                <table id="mtsa-repeatable-fieldset-ticket-type" class="mtsa-table repeatable-fieldset">
                    <thead>
                    <tr>
                        <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php
                    if (!empty($prices)) :
                        foreach ($prices as $price) : ?>
                            <tr>
                                <td class="wbtm-wid-25">
                                    <select name="wbtm_bus_bp_price_stop[]" style="width: 100%">
                                        <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php foreach ($routes as $route) : ?>
                                            <option value="<?php echo $route->name; ?>"
                                                <?php echo($route->name == $price['wbtm_bus_bp_price_stop'] ? 'selected' : '') ?>>
                                                <?php echo $route->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="wbtm-wid-25">
                                    <select name="wbtm_bus_dp_price_stop[]" style="width: 100%">
                                        <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php foreach ($routes as $route) : ?>
                                            <option value="<?php echo $route->name; ?>"
                                                <?php echo($route->name == $price['wbtm_bus_dp_price_stop'] ? 'selected' : '') ?>>
                                                <?php echo $route->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="wbtm-wid-15">
                                    <input type="text" class="widefat"
                                           name="wbtm_bus_price[]"
                                           placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_price']; ?>"/>
                                    <input type="text" class="widefat <?php echo $return_class; ?>"
                                           name="wbtm_bus_price_return[]"
                                           placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_price_return']; ?>"/>
                                </td>
                                <td class="wbtm-wid-15">
                                    <input type="text" class="widefat"
                                           name="wbtm_bus_child_price[]"
                                           placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_child_price']; ?>"/>
                                    <input type="text" class="widefat <?php echo $return_class; ?>"
                                           name="wbtm_bus_child_price_return[]"
                                           placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_child_price_return']; ?>"/>
                                </td>
                                <td class="wbtm-wid-15">
                                    <input type="text" class="widefat"
                                           name="wbtm_bus_infant_price[]"
                                           placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_infant_price']; ?>"/>
                                    <input type="text" class="widefat <?php echo $return_class; ?>"
                                           name="wbtm_bus_infant_price_return[]"
                                           placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                           value="<?php echo $price['wbtm_bus_infant_price_return']; ?>"/>
                                </td>
                                <td class="wbtm-wid-5">
                                    <button class="button wbtm-remove-row-t"><span
                                                class="dashicons dashicons-trash"></span></button>
                                </td>
                            </tr>
                        <?php
                        endforeach; endif ?>

                    <!-- empty hidden one for jQuery -->
                    <tr class="mtsa-empty-row-t">
                        <td>
                            <select name="wbtm_bus_bp_price_stop[]" style="width: 100%">
                                <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php if ($routes) : foreach ($routes as $route) : ?>
                                    <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                        <td>
                            <select name="wbtm_bus_dp_price_stop[]" style="width: 100%">
                                <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php if ($routes) : foreach ($routes as $route) : ?>
                                    <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="widefat"
                                   name="wbtm_bus_price[]"
                                   placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                            <input type="text" class="widefat <?php echo $return_class; ?>"
                                   name="wbtm_bus_price_return[]"
                                   placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                        </td>
                        <td>
                            <input type="text" class="widefat"
                                   name="wbtm_bus_child_price[]"
                                   placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                            <input type="text" class="widefat <?php echo $return_class; ?>"
                                   name="wbtm_bus_child_price_return[]"
                                   placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                        </td>
                        <td>
                            <input type="text" class="widefat"
                                   name="wbtm_bus_infant_price[]"
                                   placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                            <input type="text" class="widefat <?php echo $return_class; ?>"
                                   name="wbtm_bus_infant_price_return[]"
                                   placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                   value=""/>
                        </td>
                        <td>
                            <button class="button wbtm-remove-row-t"><span
                                        class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <button class="button wbtom-tb-repeat-btn" style="background:green; color:white;"><span
                            class="dashicons dashicons-plus-alt"
                            style="margin-top: 3px;color: white;"></span><?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
            </div>

            <!-- Return Price -->
            <div class="wbtm-only-for-return-enable">
                <h3 class="wbtm-single-return-header"><?php _e('Return Seat Price', 'bus-ticket-booking-with-seat-reservation') ?>:</h3>
                <div class="wbtm_content_inner">
                    <table id="mtsa-repeatable-fieldset-ticket-type" class="mtsa-table repeatable-fieldset">
                        <thead>
                        <tr>
                            <th><?php _e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Adult Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Child Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Infant Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        </tr>
                        </thead>
                        <tbody>

                        <?php
                        if (!empty($prices_return)) :
                            foreach ($prices_return as $price) : ?>
                                <tr>
                                    <td class="wbtm-wid-25">
                                        <select name="wbtm_bus_bp_price_stop_return[]" style="width: 100%">
                                            <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                            <?php foreach ($routes as $route) : ?>
                                                <option value="<?php echo $route->name; ?>"
                                                    <?php echo($route->name == $price['wbtm_bus_bp_price_stop'] ? 'selected' : '') ?>>
                                                    <?php echo $route->name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="wbtm-wid-25">
                                        <select name="wbtm_bus_dp_price_stop_return[]" style="width: 100%">
                                            <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                            <?php foreach ($routes as $route) : ?>
                                                <option value="<?php echo $route->name; ?>"
                                                    <?php echo($route->name == $price['wbtm_bus_dp_price_stop'] ? 'selected' : '') ?>>
                                                    <?php echo $route->name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="wbtm-wid-15">
                                        <input type="text" class="widefat"
                                            name="wbtm_bus_price_r[]"
                                            placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_price']; ?>"/>
                                        <input type="text" class="widefat <?php echo $return_class; ?>"
                                            name="wbtm_bus_price_return_discount[]"
                                            placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_price_return']; ?>"/>
                                    </td>
                                    <td class="wbtm-wid-15">
                                        <input type="text" class="widefat"
                                            name="wbtm_bus_child_price_r[]"
                                            placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_child_price']; ?>"/>
                                        <input type="text" class="widefat <?php echo $return_class; ?>"
                                            name="wbtm_bus_child_price_return_discount[]"
                                            placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_child_price_return']; ?>"/>
                                    </td>
                                    <td class="wbtm-wid-15">
                                        <input type="text" class="widefat"
                                            name="wbtm_bus_infant_price_r[]"
                                            placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_infant_price']; ?>"/>
                                        <input type="text" class="widefat <?php echo $return_class; ?>"
                                            name="wbtm_bus_infant_price_return_discount[]"
                                            placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                            value="<?php echo $price['wbtm_bus_infant_price_return']; ?>"/>
                                    </td>
                                    <td class="wbtm-wid-5">
                                        <button class="button wbtm-remove-row-t"><span
                                                    class="dashicons dashicons-trash"></span></button>
                                    </td>
                                </tr>
                            <?php
                            endforeach; endif ?>

                        <!-- empty hidden one for jQuery -->
                        <tr class="mtsa-empty-row-t">
                            <td>
                                <select name="wbtm_bus_bp_price_stop_return[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php if ($routes) : foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </td>
                            <td>
                                <select name="wbtm_bus_dp_price_stop_return[]" style="width: 100%">
                                    <option value=""><?php _e('Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <?php if ($routes) : foreach ($routes as $route) : ?>
                                        <option value="<?php echo $route->name; ?>"><?php echo $route->name; ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="widefat"
                                    name="wbtm_bus_price_r[]"
                                    placeholder="<?php _e('1500', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                                <input type="text" class="widefat <?php echo $return_class; ?>"
                                    name="wbtm_bus_price_return_discount[]"
                                    placeholder="<?php _e('Adult Return Price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                            </td>
                            <td>
                                <input type="text" class="widefat"
                                    name="wbtm_bus_child_price_r[]"
                                    placeholder="<?php _e('1200', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                                <input type="text" class="widefat <?php echo $return_class; ?>"
                                    name="wbtm_bus_child_price_return_discount[]"
                                    placeholder="<?php _e('Child return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                            </td>
                            <td>
                                <input type="text" class="widefat"
                                    name="wbtm_bus_infant_price_r[]"
                                    placeholder="<?php _e('1000', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                                <input type="text" class="widefat <?php echo $return_class; ?>"
                                    name="wbtm_bus_infant_price_return_discount[]"
                                    placeholder="<?php _e('Infant return price', 'bus-ticket-booking-with-seat-reservation') ?>"
                                    value=""/>
                            </td>
                            <td>
                                <button class="button wbtm-remove-row-t"><span
                                            class="dashicons dashicons-trash"></span></button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <button class="button wbtom-tb-repeat-btn" style="background:green; color:white;"><span
                                class="dashicons dashicons-plus-alt"
                                style="margin-top: 3px;color: white;"></span><?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </button>
                </div>
            </div>
            <!-- Return Price END -->
        </div>

        <div id="wbtm_subs_price">
            <?php echo do_action('wbtm_subscription_price'); ?>
        </div>

        <?php
        if (has_action('wbtm_private_price')) {
            echo do_action('wbtm_private_price');
        }


        $this->wbtm_extra_price_option($post->ID);

    }

    // Pickup Point
    public function wbtmPickupPoint()
    {

        global $wbtmmain, $wbtmcore, $post;

        // Boarding Points
        $boarding_points = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops', true));
        if ($boarding_points) {
            $boarding_points = array_column($boarding_points, 'wbtm_bus_bp_stops_name');
        } else {
            $boarding_points = array();
            $bus_stops = get_terms(array(
                'taxonomy' => 'wbtm_bus_stops',
                'hide_empty' => false
            ));

            if($bus_stops) {
                foreach($bus_stops as $s) {
                    $boarding_points[] = $s->name;
                }
            }
        }

        // Pickup  point 
        $bus_pickpoints = get_terms(array(
            'taxonomy' => 'wbtm_bus_pickpoint',
            'hide_empty' => false
        ));

        $pickpoints = '';
        if ($bus_pickpoints) {
            foreach ($bus_pickpoints as $points) {
                $pickpoints .= '<option value="' . $points->slug . '">' . str_replace("'", '', $points->name) . '</option>';
            }
        }
        ?>

        <div class="wbtm_bus_pickpint_wrapper" data-isReturn="no">
            <div class="wbtm_left_col">
                <div class="wbtm_field_group">
                    <?php if ($boarding_points) : ?>
                        <select name="wbtm_pick_boarding" class="wbtm_pick_boarding">
                            <option value=""><?php _e('Select Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <?php foreach ($boarding_points as $stop) :
                                $stop_slug = $stop;
                                $stop_slug = strtolower($stop_slug);
                                $stop_slug = preg_replace('/[^A-Za-z0-9-]/', '_', $stop_slug);
                                ?>
                                <option value="<?php echo $stop_slug ?>"><?php echo $stop ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="wbtm_add_pickpoint_this_city"><?php _e('Add Pickup point', 'bus-ticket-booking-with-seat-reservation'); ?>
                            <i class="fas fa-arrow-right"></i></button>
                    <?php else :
                        echo "<div style='padding: 10px 0;text-align: center;background: #d23838;color: #fff;border: 5px solid #ff2d2d;padding: 5px;font-size: 16px;display: block;margin: 20px;'>Please Enter some bus stops first. <a style='color:#fff' href='" . get_admin_url() . "edit-tags.php?taxonomy=wbtm_bus_stops&post_type=wbtm_bus'>Click here for bus stops</a></div>";
                    endif; ?>
                </div>
            </div>
            <?php $selected_city_pickpoints = get_post_meta($post->ID, 'wbtm_pickpoint_selected_city', true); ?>
            <div class="wbtm_right_col <?php echo($selected_city_pickpoints == '' ? 'all-center' : ''); ?>">
                <div id="wbtm_pickpoint_selected_city">

                    <?php

                    if ($selected_city_pickpoints != '') {

                        $selected_city_pickpoints = explode(',', $selected_city_pickpoints);
                        foreach ($selected_city_pickpoints as $single) {
                            $get_pickpoints_data = get_post_meta($post->ID, 'wbtm_selected_pickpoint_name_' . $single, true); ?>
                            <div class="wbtm_selected_city_item">
                                <span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></span>
                                <h4 class="wbtm_pickpoint_title"><?php echo ucfirst($single); ?></h4>
                                <input type="hidden" name="wbtm_pickpoint_selected_city[]"
                                       value="<?php echo $single; ?>">
                                <div class="pickpoint-adding-wrap">
                                    <?php

                                    if ($get_pickpoints_data) {
                                        $get_pickpoints_data = unserialize($get_pickpoints_data);

                                        foreach ($get_pickpoints_data as $pickpoint) : ?>


                                            <div class="pickpoint-adding">
                                                <select name="wbtm_selected_pickpoint_name_<?php echo $single; ?>[]">
                                                    <?php
                                                    if ($bus_pickpoints) {
                                                        foreach ($bus_pickpoints as $bus_pickpoint) {
                                                            echo '<option value="' . $bus_pickpoint->slug . '" ' . ($bus_pickpoint->slug == $pickpoint['pickpoint'] ? "selected=selected" : '') . '>' . $bus_pickpoint->name . '</option>';
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                                <input type="text"
                                                       name="wbtm_selected_pickpoint_time_<?php echo $single; ?>[]"
                                                       value="<?php echo $pickpoint['time']; ?>">
                                                <button class="wbtm_remove_pickpoint"><i
                                                            class="fas fa-minus-circle"></i>
                                                </button>
                                            </div>

                                        <?php
                                        endforeach;
                                    } ?>
                                </div>
                                <button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i>
                                    <?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </button>
                            </div>
                            <?php
                        }

                    } else {
                        echo '<p class="blank-pickpoint" style="color: #FF9800;font-weight: 700;">' . __('No pickup point added yet!', 'bus-ticket-booking-with-seat-reservation') . '</p>';
                    }
                    ?>

                </div>
            </div>
        </div>

        <!-- Return  -->
        <div class="wbtm-only-for-return-enable">
            <h3 class="wbtm-single-return-header"><?php _e('Pickup Point Return', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <div class="wbtm_bus_pickpint_wrapper" data-isReturn="yes">
                <div class="wbtm_left_col">
                    <div class="wbtm_field_group">
                        <?php if ($boarding_points) : ?>
                            <select name="wbtm_pick_boarding" class="wbtm_pick_boarding">
                                <option value=""><?php _e('Select Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php foreach ($boarding_points as $stop) :
                                    $stop_slug = $stop;
                                    $stop_slug = strtolower($stop_slug);
                                    $stop_slug = preg_replace('/[^A-Za-z0-9-]/', '_', $stop_slug);
                                    ?>
                                    <option value="<?php echo $stop_slug ?>"><?php echo $stop ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="wbtm_add_pickpoint_this_city"><?php _e('Add Pickup point', 'bus-ticket-booking-with-seat-reservation'); ?>
                                <i class="fas fa-arrow-right"></i></button>
                        <?php else :
                            echo "<div style='padding: 10px 0;text-align: center;background: #d23838;color: #fff;border: 5px solid #ff2d2d;padding: 5px;font-size: 16px;display: block;margin: 20px;'>Please Enter some bus stops first. <a style='color:#fff' href='" . get_admin_url() . "edit-tags.php?taxonomy=wbtm_bus_stops&post_type=wbtm_bus'>Click here for bus stops</a></div>";
                        endif; ?>
                    </div>
                </div>
                <?php $selected_city_pickpoints_return = get_post_meta($post->ID, 'wbtm_pickpoint_selected_city_return', true); ?>
                <div class="wbtm_right_col <?php echo($selected_city_pickpoints_return == '' ? 'all-center' : ''); ?>">
                    <div id="wbtm_pickpoint_selected_city">

                        <?php

                        if ($selected_city_pickpoints_return != '') {

                            $selected_city_pickpoints_return = explode(',', $selected_city_pickpoints_return);
                            foreach ($selected_city_pickpoints_return as $single) {
                                $get_pickpoints_data = get_post_meta($post->ID, 'wbtm_selected_pickpoint_return_name_' . $single, true); ?>
                                <div class="wbtm_selected_city_item">
                                    <span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></span>
                                    <h4 class="wbtm_pickpoint_title"><?php echo ucfirst($single); ?></h4>
                                    <input type="hidden" name="wbtm_pickpoint_selected_city_return[]"
                                        value="<?php echo $single; ?>">
                                    <div class="pickpoint-adding-wrap">
                                        <?php

                                        if ($get_pickpoints_data) {
                                            $get_pickpoints_data = unserialize($get_pickpoints_data);

                                            foreach ($get_pickpoints_data as $pickpoint) : ?>


                                                <div class="pickpoint-adding">
                                                    <select name="wbtm_selected_pickpoint_return_name_<?php echo $single; ?>[]">
                                                        <?php
                                                        if ($bus_pickpoints) {
                                                            foreach ($bus_pickpoints as $bus_pickpoint) {
                                                                echo '<option value="' . $bus_pickpoint->slug . '" ' . ($bus_pickpoint->slug == $pickpoint['pickpoint'] ? "selected=selected" : '') . '>' . $bus_pickpoint->name . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <input type="text"
                                                        name="wbtm_selected_pickpoint_return_time_<?php echo $single; ?>[]"
                                                        value="<?php echo $pickpoint['time']; ?>">
                                                    <button class="wbtm_remove_pickpoint"><i
                                                                class="fas fa-minus-circle"></i>
                                                    </button>
                                                </div>

                                            <?php
                                            endforeach;
                                        } ?>
                                    </div>
                                    <button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i>
                                        <?php _e('Add more', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </button>
                                </div>
                                <?php
                            }

                        } else {
                            echo '<p class="blank-pickpoint" style="color: #FF9800;font-weight: 700;">' . __('No pickup point added yet!', 'bus-ticket-booking-with-seat-reservation') . '</p>';
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
        <!-- Return END -->

        <script>
            // Pickuppoint
            // Select Boarding point and hit add
            (function ($) {
                $('.wbtm_add_pickpoint_this_city').click(function (e) {
                    e.preventDefault();

                    let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');
                    let isReturn = parent.attr('data-isReturn');

                    parent.find('.blank-pickpoint').remove();
                    parent.find('.wbtm_right_col').removeClass('all-center');
                    var get_boarding_point = parent.find('.wbtm_pick_boarding option:selected').val();

                    // Validation
                    if (get_boarding_point == '') {
                        parent.find('.wbtm_pick_boarding').css({
                            'border': '1px solid red',
                            'color': 'red'
                        }); // Not ok!!!
                        return;
                    } else {
                        parent.find('.wbtm_pick_boarding').css({
                            'border': '1px solid #7e8993',
                            'color': '#8ac34a'
                        }); // Ok

                    }

                    var get_boarding_point_name = parent.find('.wbtm_pick_boarding option:selected').text();
                    parent.find('.wbtm_pick_boarding option:selected').remove();
                    var html =
                        '<div class="wbtm_selected_city_item"><span class="remove_city_for_pickpoint"><i class="fas fa-minus-circle"></i></i></span>' +
                        '<h4 class="wbtm_pickpoint_title">' + get_boarding_point_name + '</h4>' +
                        '<input type="hidden" name='+ (isReturn ? "wbtm_pickpoint_selected_city_return[]" : "wbtm_pickpoint_selected_city[]") +' value="' + get_boarding_point +
                        '">' +
                        '<div class="pickpoint-adding-wrap"><div class="pickpoint-adding">' +
                        '<select name="' + (isReturn ? "wbtm_selected_pickpoint_return_name_" : "wbtm_selected_pickpoint_name_") + get_boarding_point + '[]">' +
                        '<?php echo $pickpoints; ?>' +
                        '</select>' +
                        '<input type="text" name="' + (isReturn ? "wbtm_selected_pickpoint_return_time_" : "wbtm_selected_pickpoint_time_") + get_boarding_point +
                        '[]" placeholder="Pickup Time">' +
                        '<button class="wbtm_remove_pickpoint"><i class="fas fa-minus-circle"></i></button>' +
                        '</div></div>' +
                        '<button class="wbtm_add_more_pickpoint"><i class="fas fa-plus"></i> <?php _e("Add more", "bus-ticket-booking-with-seat-reservation"); ?></button>' +
                        '</div>';


                    if (parent.find('#wbtm_pickpoint_selected_city').children().length > 0) {
                        parent.find('#wbtm_pickpoint_selected_city').append(html);
                    } else {
                        parent.find('#wbtm_pickpoint_selected_city').html(html);
                    }

                    parent.find('.wbtm_pick_boarding option:first').attr('selected', 'selected');

                });

                // Remove City for Pickpoint
                $(document).on('click', '.remove_city_for_pickpoint', function (e) {
                    e.preventDefault();

                    let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

                    var city_name = $(this).siblings('.wbtm_pickpoint_title').text();
                    var city_name_val = $(this).siblings('input').val();
                    parent.find('.wbtm_pick_boarding').append('<option value="' + city_name_val + '">' + city_name +
                        '</option>');
                    $(this).parents('.wbtm_selected_city_item').remove();
                });

                // Adding more pickup point
                $(document).on('click', '.wbtm_add_more_pickpoint', function (e) {
                    e.preventDefault();

                    let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

                    $adding_more = $(this).siblings('.pickpoint-adding-wrap').find('.pickpoint-adding:first').clone(
                        true);
                    $(this).siblings('.pickpoint-adding-wrap').append($adding_more);
                });

                // Remove More Pickpoint
                $(document).on('click', '.wbtm_remove_pickpoint', function (e) {
                    e.preventDefault();

                    let parent = $(this).parents('.wbtm_bus_pickpint_wrapper');

                    // Remove wrapper
                    if ($(this).parents('.pickpoint-adding-wrap').children().length == 1) {
                        $(this).parents('.wbtm_selected_city_item').find('.remove_city_for_pickpoint').trigger(
                            'click');
                    }

                    // Remove Item
                    $(this).parent().remove();


                });
                // Pickuppoint END
            })(jQuery)
        </script>

        <?php

    }

    public function wbtmBusOnDate()
    {
        global $post;
        $values = get_post_custom($post->ID);
        $ondates = get_post_meta($post->ID, 'wbtm_bus_on_dates', true);
        $wbtm_offday_schedule = maybe_unserialize(get_post_meta($post->ID, 'wbtm_offday_schedule', true));

        // Return
        $ondates_return = get_post_meta($post->ID, 'wbtm_bus_on_dates_return', true);
        $wbtm_offday_schedule_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_offday_schedule_return', true));
        ?>
        <div class="wbtm-content-wrapper">
            <div class="wbtm-content-inner">
                <div class="wbtm-sec-row">
                    <div class="wbtm-ondates-wrapper">
                        <label for=""><?php _e('Operational Onday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <div class="wbtm-ondates-inner">
                            <input type="text" name="wbtm_bus_on_dates" value="<?php echo $ondates; ?>">
                        </div>
                    </div>
                    <div class="wbtm-offdates-wrapper">
                        <label for=""><?php _e('Operational Offday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <div class="wbtm-offdates-inner">
                            <table class="repeatable-fieldset-offday">
                                <tr>
                                    <th><?php _e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    <th class="th-time"><?php _e('From Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    <th><?php _e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    <th class="th-time"><?php _e('To Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                    <th></th>
                                </tr>
                                <tbody>
                                <?php
                                if ($wbtm_offday_schedule) :
                                    $count = 0;
                                    foreach ($wbtm_offday_schedule as $field) {
                                        ?>
                                        <tr class="">
                                            <td align="left"><input type="text"
                                                                    id="<?php echo 'db_offday_from_' . $count; ?>"
                                                                    class="repeatable-offday-from-field"
                                                                    name='wbtm_od_offdate_from[]'
                                                                    placeholder="2020-12-31"
                                                                    value="<?php echo $field['from_date'] ?>"/></td>
                                            <td align="left"><input type="text" class="repeatable-offtime-from-field"
                                                                    name='wbtm_od_offtime_from[]' placeholder="09:00 am"
                                                                    value="<?php echo $field['from_time'] ?>"/></td>
                                            <td align="left"><input type="text"
                                                                    id="<?php echo 'db_offday_to_' . $count; ?>"
                                                                    class="repeatable-offday-to-field"
                                                                    name='wbtm_od_offdate_to[]' placeholder="2020-12-31"
                                                                    value="<?php echo $field['to_date'] ?>"/></td>
                                            <td align="left"><input type="text" class="repeatable-offtime-to-field"
                                                                    name='wbtm_od_offtime_to[]' placeholder="09:59 pm"
                                                                    value="<?php echo $field['to_time'] ?>"/></td>
                                            <td align="left">
                                                <a class="button remove-bp-row" href="#">
                                                    <i class="fas fa-minus-circle"></i>
                                                    <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                                </a>
                                            </td>
                                        </tr>

                                        <script>
                                            (function ($) {
                                                setTimeout(function () {
                                                    $("#db_offday_from_<?php echo $count ?>").datepicker({
                                                        dateFormat: "yy-mm-dd",
                                                        minDate: 0
                                                    });
                                                    $("#db_offday_to_<?php echo $count ?>").datepicker({
                                                        dateFormat: "yy-mm-dd",
                                                        minDate: 0
                                                    });
                                                }, 400);
                                            })(jQuery)
                                        </script>
                                        <?php

                                        $count++;
                                    }
                                else :
                                    // show a blank one
                                endif;
                                ?>

                                <!-- empty hidden one for jQuery -->
                                <tr class="empty-row-offday screen-reader-text">
                                    <td align="left"><input type="text" class="repeatable-offday-from-field"
                                                            name='wbtm_od_offdate_from[]' placeholder="2020-12-31"/>
                                    </td>
                                    <td align="left"><input type="text" class="repeatable-offtime-from-field"
                                                            name='wbtm_od_offtime_from[]' placeholder="09:00 am"/></td>
                                    <td align="left"><input type="text" class="repeatable-offday-to-field"
                                                            name='wbtm_od_offdate_to[]' placeholder="2020-12-31"/></td>
                                    <td align="left"><input type="text" class="repeatable-offtime-to-field"
                                                            name='wbtm_od_offtime_to[]' placeholder="09:59 pm"/></td>
                                    <td align="left">
                                        <a class="button remove-bp-row" href="#">
                                            <i class="fas fa-minus-circle"></i>
                                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <p style="margin: 0 0 0 4px;border-radius: 5px;">
                                <a class="button add-offday-row" href="#"><i class="fas fa-plus"></i>
                                    <?php _e('Add More offdate', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="wbtm-dayoff-wrapper">
                    <label for="">Offdays</label>
                    <div class='wbtm-dayoff-inner'>
                        <label for='sun'>
                            <input type="checkbox" id='sun' style="text-align: left;width: auto;" name="offday_sun"
                                   value='yes' <?php if (array_key_exists('offday_sun', $values)) {
                                if ($values['offday_sun'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?> /> <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='mon'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_mon" value='yes'
                                   id='mon' <?php if (array_key_exists('offday_mon', $values)) {
                                if ($values['offday_mon'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='tue'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_tue" value='yes'
                                   id='tue' <?php if (array_key_exists('offday_tue', $values)) {
                                if ($values['offday_tue'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='wed'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_wed" value='yes'
                                   id='wed' <?php if (array_key_exists('offday_wed', $values)) {
                                if ($values['offday_wed'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='thu'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_thu" value='yes'
                                   id='thu' <?php if (array_key_exists('offday_thu', $values)) {
                                if ($values['offday_thu'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='fri'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_fri" value='yes'
                                   id='fri' <?php if (array_key_exists('offday_fri', $values)) {
                                if ($values['offday_fri'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <label for='sat'>
                            <input type="checkbox" style="text-align: left;width: auto;" name="offday_sat" value='yes'
                                   id='sat' <?php if (array_key_exists('offday_sat', $values)) {
                                if ($values['offday_sat'][0] == 'yes') {
                                    echo 'Checked';
                                }
                            } ?>> <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return -->
        <div class="wbtm-only-for-return-enable">
            <div class="wbtm-content-wrapper">
                <h3 class="wbtm-single-return-header"><?php _e('Return', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <div class="wbtm-content-inner">
                    <div class="wbtm-sec-row">
                        <div class="wbtm-ondates-wrapper">
                            <label for=""><?php _e('Operational Onday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <div class="wbtm-ondates-inner">
                                <input type="text" name="wbtm_bus_on_dates_return" value="<?php echo $ondates_return; ?>">
                            </div>
                        </div>
                        <div class="wbtm-offdates-wrapper">
                            <label for=""><?php _e('Operational Offday', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <div class="wbtm-offdates-inner">
                                <table class="repeatable-fieldset-offday">
                                    <thead>
                                        <tr>
                                            <th><?php _e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            <th class="th-time"><?php _e('From Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            <th><?php _e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            <th class="th-time"><?php _e('To Time', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if ($wbtm_offday_schedule_return) :
                                        $count = 0;
                                        foreach ($wbtm_offday_schedule_return as $field) {
                                            ?>
                                            <tr class="">
                                                <td align="left"><input type="text"
                                                                        id="<?php echo 'db_offday_from_' . $count.'_r'; ?>"
                                                                        class="repeatable-offday-from-field"
                                                                        name='wbtm_od_offdate_from_return[]'
                                                                        placeholder="2020-12-31"
                                                                        value="<?php echo $field['from_date'] ?>"/></td>
                                                <td align="left"><input type="text" class="repeatable-offtime-from-field"
                                                                        name='wbtm_od_offtime_from_return[]' placeholder="09:00 am"
                                                                        value="<?php echo $field['from_time'] ?>"/></td>
                                                <td align="left"><input type="text"
                                                                        id="<?php echo 'db_offday_to_' . $count.'_r'; ?>"
                                                                        class="repeatable-offday-to-field"
                                                                        name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31"
                                                                        value="<?php echo $field['to_date'] ?>"/></td>
                                                <td align="left"><input type="text" class="repeatable-offtime-to-field"
                                                                        name='wbtm_od_offtime_to_return[]' placeholder="09:59 pm"
                                                                        value="<?php echo $field['to_time'] ?>"/></td>
                                                <td align="left">
                                                    <a class="button remove-bp-row" href="#">
                                                        <i class="fas fa-minus-circle"></i>
                                                        <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                                    </a>
                                                </td>
                                            </tr>

                                            <script>
                                                (function ($) {
                                                    setTimeout(function () {
                                                        $("#db_offday_from_<?php echo $count.'_r' ?>").datepicker({
                                                            dateFormat: "yy-mm-dd",
                                                            minDate: 0
                                                        });
                                                        $("#db_offday_to_<?php echo $count.'_r' ?>").datepicker({
                                                            dateFormat: "yy-mm-dd",
                                                            minDate: 0
                                                        });
                                                    }, 400);
                                                })(jQuery)
                                            </script>
                                            <?php

                                            $count++;
                                        }
                                    else :
                                        // show a blank one
                                    endif;
                                    ?>

                                    <!-- empty hidden one for jQuery -->
                                    <tr class="empty-row-offday screen-reader-text">
                                        <td align="left"><input type="text" class="repeatable-offday-from-field"
                                                                name='wbtm_od_offdate_from_return[]' placeholder="2020-12-31"/>
                                        </td>
                                        <td align="left"><input type="text" class="repeatable-offtime-from-field"
                                                                name='wbtm_od_offtime_from_return[]' placeholder="09:00 am"/></td>
                                        <td align="left"><input type="text" class="repeatable-offday-to-field"
                                                                name='wbtm_od_offdate_to_return[]' placeholder="2020-12-31"/></td>
                                        <td align="left"><input type="text" class="repeatable-offtime-to-field"
                                                                name='wbtm_od_offtime_to_return[]' placeholder="09:59 pm"/></td>
                                        <td align="left">
                                            <a class="button remove-bp-row" href="#">
                                                <i class="fas fa-minus-circle"></i>
                                                <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                                <p style="margin: 0 0 0 4px;border-radius: 5px;">
                                    <a class="button add-offday-row" href="#"><i class="fas fa-plus"></i>
                                        <?php _e('Add More offdate', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="wbtm-dayoff-wrapper">
                        <label for="">Offdays</label>
                        <div class='wbtm-dayoff-inner'>
                            <label for='sun_return'>
                                <input type="checkbox" id='sun_return' style="text-align: left;width: auto;" name="offday_sun_return"
                                    value='yes' <?php if (array_key_exists('offday_sun_return', $values)) {
                                    if ($values['offday_sun_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?> /> <?php _e('Sunday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='mon_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_mon_return" value='yes'
                                    id='mon_return' <?php if (array_key_exists('offday_mon_return', $values)) {
                                    if ($values['offday_mon_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Monday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='tue_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_tue_return" value='yes'
                                    id='tue_return' <?php if (array_key_exists('offday_tue_return', $values)) {
                                    if ($values['offday_tue_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Tuesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='wed_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_wed_return" value='yes'
                                    id='wed_return' <?php if (array_key_exists('offday_wed_return', $values)) {
                                    if ($values['offday_wed_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Wednesday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='thu_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_thu_return" value='yes'
                                    id='thu_return' <?php if (array_key_exists('offday_thu_return', $values)) {
                                    if ($values['offday_thu_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Thursday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='fri_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_fri_return" value='yes'
                                    id='fri_return' <?php if (array_key_exists('offday_fri_return', $values)) {
                                    if ($values['offday_fri_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Friday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                            <label for='sat_return'>
                                <input type="checkbox" style="text-align: left;width: auto;" name="offday_sat_return" value='yes'
                                    id='sat_return' <?php if (array_key_exists('offday_sat_return', $values)) {
                                    if ($values['offday_sat_return'][0] == 'yes') {
                                        echo 'Checked';
                                    }
                                } ?>> <?php _e('Saturday', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Return End -->
        <?php
    }

    public function wbtm_extra_price_option($post_id)
    {
        $mep_events_extra_prices = get_post_meta($post_id, 'mep_events_extra_prices', true);
        wp_nonce_field('mep_events_extra_price_nonce', 'mep_events_extra_price_nonce');
        ?>
        <div id="wbtm_extra_service" style="margin-top:20px">
            <h3 style="margin:0;"><?php _e('Extra service Area :', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
            <p class="event_meta_help_txt" style="margin: 0 0 15px 0;">
                <?php _e('Extra Service as Product that you can sell and it is not included on ticket', 'bus-ticket-booking-with-seat-reservation'); ?>
            </p>
            <div class="mp_ticket_type_table">
                <table id="repeatable-fieldset-one" style="width:100%">
                    <thead>
                    <tr>
                        <th title="<?php _e('Extra Service Name', 'bus-ticket-booking-with-seat-reservation'); ?>">
                            <?php _e('Name', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th title="<?php _e('Extra Service Price', 'bus-ticket-booking-with-seat-reservation'); ?>">
                            <?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th title="<?php _e('Available Qty', 'bus-ticket-booking-with-seat-reservation'); ?>">
                            <?php _e('Available', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th title="<?php _e('Qty Box Type', 'bus-ticket-booking-with-seat-reservation'); ?>"
                            style="min-width: 140px;">
                            <?php _e('Qty Box', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody class="mp_event_type_sortable">
                    <?php

                    if ($mep_events_extra_prices) :

                        foreach ($mep_events_extra_prices as $field) {
                            $qty_type = esc_attr($field['option_qty_type']);
                            ?>
                            <tr>
                                <td><input type="text" class="mp_formControl" name="option_name[]" placeholder="Ex: Cap"
                                           value="<?php if ($field['option_name'] != '') {
                                               echo esc_attr($field['option_name']);
                                           } ?>"/></td>

                                <td><input type="number" step="0.001" class="mp_formControl" name="option_price[]"
                                           placeholder="Ex: 10"
                                           value="<?php if ($field['option_price'] != '') {
                                               echo esc_attr($field['option_price']);
                                           } else {
                                               echo '';
                                           } ?>"/></td>

                                <td><input type="number" class="mp_formControl" name="option_qty[]"
                                           placeholder="Ex: 100" value="<?php if ($field['option_qty'] != '') {
                                        echo esc_attr($field['option_qty']);
                                    } else {
                                        echo '';
                                    } ?>"/></td>

                                <td align="center">
                                    <select name="option_qty_type[]" class='mp_formControl'>
                                        <option value="inputbox" <?php if ($qty_type == 'inputbox') {
                                            echo "Selected";
                                        } ?>><?php _e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="dropdown" <?php if ($qty_type == 'dropdown') {
                                            echo "Selected";
                                        } ?>><?php _e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <div class="mp_event_remove_move">
                                        <button class="button remove-row" type="button"><span
                                                    class="dashicons dashicons-trash"
                                                    style="margin-top: 3px;color: red;"></span></button>
                                        <!-- <div class="mp_event_type_sortable_button"><span class="dashicons dashicons-move"></span></div> -->
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    else :
                        // show a blank one
                    endif;
                    ?>

                    <!-- empty hidden one for jQuery -->
                    <tr class="empty-row screen-reader-text">
                        <td><input type="text" class="mp_formControl" name="option_name[]" placeholder="Ex: Cap"/></td>
                        <td><input type="number" class="mp_formControl" step="0.001" name="option_price[]"
                                   placeholder="Ex: 10"
                                   value=""/></td>
                        <td><input type="number" class="mp_formControl" name="option_qty[]" placeholder="Ex: 100"
                                   value=""/>
                        </td>

                        <td><select name="option_qty_type[]" class='mp_formControl'>
                                <option value=""><?php _e('Please Select Type', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="inputbox"><?php _e('Input Box', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="dropdown"><?php _e('Dropdown List', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            </select></td>
                        <td>
                            <button class="button remove-row"><span class="dashicons dashicons-trash"
                                                                    style="margin-top: 3px;color: red;"></span><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p>
                <button id="add-row" class="button" style="background:green; color:white;"><span
                            class="dashicons dashicons-plus-alt"
                            style="margin-top: 3px;color: white;"></span><?php _e('Add Extra Price', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
            </p>
        </div>
        <?php
    }


} // Class End

new WBTMMetaBox();