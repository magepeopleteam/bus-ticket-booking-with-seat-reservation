<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

/**
 * @since      1.0.0
 * @package    WBTM_Plugin
 * @subpackage WBTM_Plugin/includes
 * @author     MagePeople team <magepeopleteam@gmail.com>
 */
class WBTM_Plugin_Functions
{

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        $this->add_hooks();
        add_filter('mage_wc_products', array($this, 'add_cpt_to_wc_product'), 10, 1);
    }

    private function add_hooks()
    {
        add_action('init', array($this, 'direct_ticket_download'));
        add_action('wp_head', array($this, 'wbtm_js_constant'), 5);
        add_action('admin_head', array($this, 'wbtm_js_constant'), 5);

        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('wp_ajax_wbtm_seat_plan', array($this, 'wbtm_seat_plan'));
        add_action('wp_ajax_nopriv_wbtm_seat_plan', array($this, 'wbtm_seat_plan'));
        add_action('woocommerce_order_status_changed', array($this, 'wbtm_bus_ticket_seat_management'), 10, 4);
        add_action('wp_ajax_wbtm_seat_plan_dd', array($this, 'wbtm_seat_plan_dd'));
        add_action('wp_ajax_nopriv_wbtm_seat_plan_dd', array($this, 'wbtm_seat_plan_dd'));
        add_action('woocommerce_checkout_order_processed', array($this, 'bus_order_processed'), 10);
    }

    public function wbtm_js_constant()
    {
?>
        <script type="text/javascript">
            let mptbm_currency_symbol = "<?php echo html_entity_decode(get_woocommerce_currency_symbol()); ?>";
            let mptbm_currency_position = "<?php echo get_option('woocommerce_currency_pos'); ?>";
            let mptbm_currency_decimal = "<?php echo wc_get_price_decimal_separator(); ?>";
            let mptbm_currency_thousands_separator = "<?php echo wc_get_price_thousand_separator(); ?>";
            let mptbm_num_of_decimal = "<?php echo get_option('woocommerce_price_num_decimals', 2); ?>";
        </script>
    <?php
    }

    public function direct_ticket_download()
    {
        global $magepdf;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'download_pdf_ticket') {
            $magepdf->generate_pdf($_REQUEST['order_id'], '', true);
        }
    }


    public function get_name()
    {
        $name = $this->bus_get_option('bus_menu_label', 'label_setting_sec', 'Bus');
        return $name;
    }


    public function get_slug()
    {
        $name = $this->bus_get_option('bus_menu_slug', 'label_setting_sec', 'bus');
        return $name;
    }


    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'bus-ticket-booking-with-seat-reservation',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    public function wbtm_get_driver_position($current_plan)
    {
    ?>
        <select name="driver_seat_position">
            <option <?php if ($current_plan == 'driver_left') {
                        echo 'Selected';
                    } ?> value="driver_left"><?php _e('Left', 'bus-ticket-booking-with-seat-reservation'); ?></option>
            <option <?php if ($current_plan == 'driver_right') {
                        echo 'Selected';
                    } ?> value="driver_right"><?php _e('Right', 'bus-ticket-booking-with-seat-reservation'); ?></option>
            <?php do_action('wbtm_after_driver_position_dd'); ?>
        </select>
    <?php
    }

    public function wbtm_seat_plan()
    {
        $seat_col = strip_tags($_POST['seat_col']);
        $seat_row = strip_tags($_POST['seat_row']);
    ?>

        <div>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#add-seat-row').on('click', function() {
                        var row = $('.empty-row-seat.screen-reader-text').clone(true);
                        row.removeClass('empty-row-seat screen-reader-text');
                        row.insertBefore('#repeatable-fieldset-seat-one tbody>tr:last');
                        var qtt = parseInt($('#seat_rows').val(), 10);
                        $('#seat_rows').val(qtt + 1);
                        return false;
                    });
                    $('.remove-seat-row').on('click', function() {
                        $(this).parents('tr').remove();
                        var qtt = parseInt($('#seat_rows').val(), 10);
                        $('#seat_rows').val(qtt - 1);

                        return false;
                    });
                });
            </script>
            <table id="repeatable-fieldset-seat-one" width="100%">
                <tbody>
                    <?php
                    for ($x = 1; $x <= $seat_row; $x++) {
                    ?>
                        <tr>
                            <?php
                            for ($row = 1; $row <= $seat_col; $row++) {
                                $seat_type_name = "seat_types" . $row;
                            ?>
                                <td align="center">
                                    <input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
                                    <?php wbtm_get_seat_type_list($seat_type_name); ?>
                                </td>
                            <?php } ?>
                            <td align="center"><a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                <input type="hidden" name="bus_seat_panels[]">
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <!-- empty hidden one for jQuery -->
                    <tr class="empty-row-seat screen-reader-text">
                        <?php
                        for ($row = 1; $row <= $seat_col; $row++) {
                            $seat_type_name = "seat_types" . $row;
                        ?>
                            <td align="center">
                                <input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
                                <?php wbtm_get_seat_type_list($seat_type_name); ?>


                            </td>
                        <?php } ?>
                        <td align="center"><a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a><input type="hidden" name="bus_seat_panels[]"></td>
                    </tr>
                </tbody>
            </table>
            <p><a id="add-seat-row" class="add-seat-row-btn" href="#"><i class="fas fa-plus"></i></a></p>
        </div>
    <?php
        die();
    }

    public function wbtm_seat_plan_dd()
    {
        $seat_col = strip_tags($_POST['seat_col']);
        $seat_row = strip_tags($_POST['seat_row']);
    ?>

        <div>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#add-seat-row-dd').on('click', function() {
                        var row = $('.empty-row-seat-dd.screen-reader-text').clone(true);
                        row.removeClass('empty-row-seat-dd screen-reader-text');
                        row.insertBefore('#repeatable-fieldset-seat-one-dd tbody>tr:last');
                        var qtt = parseInt($('#seat_rows_dd').val(), 10);
                        $('#seat_rows_dd').val(qtt + 1);
                        return false;
                    });
                    $('.remove-seat-row-dd').on('click', function() {
                        $(this).parents('tr').remove();
                        var qtt = parseInt($('#seat_rows_dd').val(), 10);
                        $('#seat_rows_dd').val(qtt - 1);
                        return false;
                    });
                });
            </script>
            <table id="repeatable-fieldset-seat-one-dd" width="100%">
                <tbody>
                    <?php
                    for ($x = 1; $x <= $seat_row; $x++) {
                    ?>
                        <tr>
                            <?php
                            for ($row = 1; $row <= $seat_col; $row++) {
                            ?>
                                <td align="center"><input type="text" value="" name="dd_seat<?php echo $row; ?>[]" class="text"></td>
                            <?php } ?>
                            <td align="center"><a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                <input type="hidden" name="bus_seat_panels_dd[]">
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <!-- empty hidden one for jQuery -->
                    <tr class="empty-row-seat-dd screen-reader-text">
                        <?php
                        for ($row = 1; $row <= $seat_col; $row++) {
                        ?>
                            <td align="center"><input type="text" value="" name="dd_seat<?php echo $row; ?>[]" class="text">
                            </td>
                        <?php } ?>
                        <td align="center"><a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a><input type="hidden" name="bus_seat_panels_dd[]"></td>
                    </tr>
                </tbody>
            </table>
            <p><a id="add-seat-row-dd" class="add-seat-row-btn" href="#"><i class="fas fa-plus"></i></a></p>
        </div>
    <?php
        die();
    }

    // Get Bus Settings Optiins Data
    public function bus_get_option($meta_key, $setting_name = '', $default = null)
    {
        $get_settings = get_option('wbtm_bus_settings');
        $get_val = isset($get_settings[$meta_key]) ? $get_settings[$meta_key] : '';
        $output = $get_val ? $get_val : $default;
        return $output;
    }

    public function wbtm_bus_seat_plan_dd($start, $date)
    {

        wbtm_seat_global($start, $date, 'dd');
    }

    // Getting all the bus stops name from a stop name
    public function wbtm_get_all_stops_after_this($bus_id, $val, $end)
    {
        //echo $end;

        $end_s = array($val);
        //Getting All boarding points
        $boarding_points = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true));
        $all_bp_stops = array();
        foreach ($boarding_points as $_boarding_points) {
            $all_bp_stops[] = $_boarding_points['wbtm_bus_bp_stops_name'];
        }
        $pos2 = array_search($end, $all_bp_stops);
        // if (sizeof($pos2) > 0) {
        if ($pos2 != '') {
            unset($all_bp_stops[$pos2]);
        }
        // print_r($all_bp_stops);
        // echo '<br/>';
        //Gettings Stops Name Before Droping Stops
        $start_stops = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true));
        $all_stops = array();
        if (is_array($start_stops) && sizeof($start_stops) > 0) {
            foreach ($start_stops as $_start_stops) {
                $all_stops[] = $_start_stops['wbtm_bus_next_stops_name'];
            }
        }
        $full_array = $all_stops;
        $mkey = array_search($end, $full_array);
        $newarray = array_slice($full_array, $mkey, count($full_array), true);
        // return $newarray;
        $myArrayInit = $full_array; //<-- Your actual array
        $offsetKey = $mkey; //<--- The offset you need to grab
        //Lets do the code....
        $n = array_keys($myArrayInit); //<---- Grab all the keys of your actual array and put in another array
        $count = array_search($offsetKey, $n); //<--- Returns the position of the offset from this array using search
        $new_arr = array_slice($myArrayInit, 0, $count + 1, true); //<--- Slice it with the 0 index as start and position+1 as the length parameter.
        $pos2 = array_search($end, $new_arr);
        // if (sizeof($pos2) > 0) {
        if ($pos2 != '') {
            unset($new_arr[$pos2]);
        }

        $res = array_merge($all_bp_stops, $new_arr);
        return $res;

        // print_r();
    }

    // Adding Custom Post to WC Prodct Data Filter.
    public function add_cpt_to_wc_product($data)
    {
        $WBTM_cpt = array('wbtm_bus');
        return array_merge($data, $WBTM_cpt);
    }

    // make page id
    public function wbtm_make_id($val)
    {
        return str_replace("-", "", $val);
    }

    // create bus js
    public function  wbtm_seat_booking_js($id, $fare)
    {
        $fare = isset($fare) ? $fare : 0;
        $upper_price_percent = (int)get_post_meta(get_the_ID(), 'wbtm_seat_dd_price_parcent', true);
    ?>
        <script>
            jQuery(document).ready(function($) {

                $('#bus-booking-btn<?php echo $id; ?>').hide();

                $(document).on('remove_selection<?php echo $id; ?>', function(e, seatNumber, parents) {

                    $('#selected_list<?php echo $id; ?>_' + seatNumber).remove();
                    $('#seat<?php echo $id; ?>_' + seatNumber).removeClass('seat<?php echo $id; ?>_booked');
                    $('#seat<?php echo $id; ?>_' + seatNumber).removeClass('seat_booked');

                    wbt_calculate_total(parents);
                    // wbt_update_passenger_form();
                    wbtm_remove_form_builder(parents, seatNumber); // Seat form builder form remove
                })

                $(document).on('click', '.seat<?php echo $id; ?>_booked', function() {
                    // $( document.body ).trigger( 'remove_selection<?php //echo $id; 
                                                                    ?>', [ $(this).data("seat") ] );
                })

                $(document).on('click', '.remove-seat-row<?php echo $id; ?>', function() {
                    let parents = $(this).parents('.admin-bus-details');
                    $(document.body).trigger('remove_selection<?php echo $id; ?>', [$(this).data("seat"), parents]);
                    parents.find('.wbtm_anydate_return_switch label:first-child').trigger('click');
                    parents.find('.wbtm_anydate_return_switch label:first-child').addClass('active');
                    parents.find('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
                });

                jQuery('#start_stops<?php echo $id; ?>').on('change', function() {
                    var start_time = jQuery(this).find(':selected').data('start');
                    jQuery('#user_start_time<?php echo $id; ?>').val(start_time);;
                });


                jQuery(".seat<?php echo $id; ?>_blank").on('click', function() {
                    let parents = $(this).parents('.admin-bus-details');

                    if (jQuery(this).hasClass('seat<?php echo $id; ?>_booked')) {

                        jQuery(document.body).trigger('remove_selection<?php echo $id; ?>', [jQuery(this).data(
                            "seat"), parents]);
                        return;
                    }

                    jQuery(this).addClass('seat<?php echo $id; ?>_booked');
                    jQuery(this).addClass('seat_booked');

                    var seat<?php echo $id; ?>_name = jQuery(this).data("seat");
                    var seat<?php echo $id; ?>_class = jQuery(this).data("sclass");

                    var seat_pos = jQuery(this).data("seat-pos");
                    if (seat_pos == 'upper') {
                        var fare =
                            <?php echo $fare + ($upper_price_percent != 0 ? (($fare * $upper_price_percent) / 100) : 0); ?>;
                    } else {
                        var fare = <?php echo $fare; ?>;
                    }

                    let seat_name = seat<?php echo $id; ?>_name;

                    var foo = "<tr class='seat_selected_price' id='selected_list<?php echo $id; ?>_" +
                        seat<?php echo $id; ?>_name +
                        "'><td align=center><input type='hidden' name='passenger_label[]' value='Adult'/>" +
                        "<input type='hidden' name='passenger_type[]' value='0'/>" +
                        "<input type='hidden' name='seat_name[]' value='" + seat<?php echo $id; ?>_name + "'/>" +
                        seat<?php echo $id; ?>_name +
                        "</td><td align=center>Adult</td><td align=center><input class='seat_fare' type='hidden' name='seat_fare[]' value=" +
                        fare + "><input type='hidden' name='bus_fare<?php echo $id; ?>' value=" + fare +
                        ">" + wbtm_woo_price_format(fare) +
                        "</td><td align=center><a class='button remove-seat-row<?php echo $id; ?>' data-seat='" +
                        seat<?php echo $id; ?>_name + "'>X</a></td></tr>";

                    jQuery(foo).insertAfter('.list_head<?php echo $id; ?>');

                    var total_fare = jQuery('.bus_fare<?php echo $id; ?>').val();
                    var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;
                    //                  var totalFare = (rowCount * fare);
                    //
                    var totalFare = 0;
                    jQuery('.selected-seat-table tbody tr').each(function() {
                        if ($(this).hasClass('seat_selected_price')) {
                            totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
                        }
                    });

                    jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
                    jQuery('#tq<?php echo $id; ?>').val(rowCount);
                    // jQuery('#totalFare<?php echo $id; ?>').html("<?php echo get_woocommerce_currency_symbol(); ?> <span class='price-figure'>" + totalFare.toFixed(2) + "</span>");
                    jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html(wbtm_woo_price_format(totalFare));
                    jQuery('#tfi<?php echo $id; ?>').val("<?php echo get_woocommerce_currency_symbol(); ?>" +
                        totalFare);
                    if (totalFare > 0) {
                        jQuery('#bus-booking-btn<?php echo $id; ?>').show();

                    }
                    // alert(totalFare);
                    mageGrandPrice(parents);
                    // wbt_update_passenger_form(seat_name);
                    wbtm_seat_plan_form_builder_new($(this), seat_name, true); // New

                });

                // *******Admin Ticket Purchase*******
                jQuery('.admin_<?php echo $id; ?> li').on('click', function() {
                    const $this = $(this);
                    let parents = $(this).parents('.admin-bus-details');
                    const parent = $this.parents('.admin_<?php echo $id; ?>').siblings(
                        '.seat<?php echo $id; ?>_blank');
                    var price = $this.attr('data-seat-price');
                    var label = $this.attr('data-seat-label');
                    var passenger_type = $this.attr('data-seat-type');
                    let seat_label = label;
                    if (parent.hasClass('seat<?php echo $id; ?>_booked')) {
                        jQuery(document.body).trigger('remove_selection<?php echo $id; ?>', [parent.data("seat")]);
                    }

                    parent.addClass('seat<?php echo $id; ?>_booked');
                    parent.addClass('seat_booked');

                    var seat<?php echo $id; ?>_name = parent.data("seat");
                    var seat<?php echo $id; ?>_class = parent.data("sclass");
                    var fare = price;
                    let seat_name = seat<?php echo $id; ?>_name;
                    let foo = "<tr class='seat_selected_price' id='selected_list<?php echo $id; ?>_" +
                        seat<?php echo $id; ?>_name +
                        "'><td align=center><input type='hidden' name='passenger_label[]' value='" + label + "'/>" +
                        "<input type='hidden' name='passenger_type[]' value='" + passenger_type + "'/>" +
                        "<input type='hidden' name='seat_name[]' value='" + seat<?php echo $id; ?>_name + "'/>" +
                        seat<?php echo $id; ?>_name + "</td><td align=center>" + label +
                        "</td><td align=center><input class='seat_fare' type='hidden' name='seat_fare[]' value=" +
                        fare + "><input type='hidden' name='bus_fare<?php echo $id; ?>' value=" + fare +
                        ">" + wbtm_woo_price_format(fare) +
                        "</td><td align=center><a class='button remove-seat-row<?php echo $id; ?>' data-seat='" +
                        seat<?php echo $id; ?>_name + "'>X</a></td></tr>";

                    jQuery(foo).insertAfter('.list_head<?php echo $id; ?>');

                    var total_fare = jQuery('.bus_fare<?php echo $id; ?>').val();
                    var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;

                    var totalFare = 0;
                    jQuery('.selected-seat-table tbody tr').each(function() {
                        // totalFare = totalFare + parseFloat($(this).find('.seat_selected_price').val());
                        if ($(this).hasClass('seat_selected_price')) {
                            totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
                        }
                    });

                    jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
                    jQuery('#tq<?php echo $id; ?>').val(rowCount);
                    jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html(wbtm_woo_price_format(totalFare));
                    jQuery('#tfi<?php echo $id; ?>').val("<?php echo get_woocommerce_currency_symbol(); ?>" +
                        totalFare.toFixed(2));
                    if (totalFare > 0) {
                        jQuery('#bus-booking-btn<?php echo $id; ?>').show();

                    }

                    mageGrandPrice(parents);
                    // wbt_update_passenger_form(seat_name);
                    wbtm_seat_plan_form_builder_new($(this), seat_name, true, seat_label); // New


                });

                // currency format according to WooCommerce setting
                function wbtm_woo_price_format(price) {
                    if (typeof price === 'string') {
                        price = Number(price);
                    }
                    price = price.toFixed(2);
                    // price = price.toString();
                    // price = price.toFixed(mptbm_num_of_decimal);
                    let price_text = '';
                    if (mptbm_currency_position === 'right') {
                        price_text = price + mptbm_currency_symbol;
                    } else if (mptbm_currency_position === 'right_space') {
                        price_text = price + ' ' + mptbm_currency_symbol;
                    } else if (mptbm_currency_position === 'left') {
                        price_text = mptbm_currency_symbol + price;
                    } else {
                        price_text = mptbm_currency_symbol + ' ' + price;
                    }
                    return price_text;
                }


                // ******Admin Ticket Purchase (Dropdown)********

                // Show Grand Price
                function mageGrandPrice(parent) {
                    let grand_ele = parent.find('.mage-grand-total .mage-price-figure');

                    // price items
                    let seat_price = parseFloat(parent.find('.mage-price-total span').attr('data-subtotal-price')); // 1

                    let extra_price = 0;
                    parent.find('.wbtm_extra_service_table tbody tr').each(function() { // 2
                        extra_price += parseFloat($(this).attr('data-total'));
                    });

                    // Sum all items
                    let grand_total = seat_price + extra_price;

                    if (grand_total) {
                        grand_ele.text(php_vars.currency_symbol + grand_total.toFixed(2));
                        parent.find('.no-seat-submit-btn').prop('disabled', false);
                        parent.find('button[name="add-to-cart-admin"]').prop('disabled', false);
                    } else {
                        grand_ele.text(php_vars.currency_symbol + "0.00");
                        parent.find('.no-seat-submit-btn').prop('disabled', true);
                        parent.find('button[name="add-to-cart-admin"]').prop('disabled', true);
                    }
                }

                function wbt_calculate_total(parents) {

                    var fare = <?php echo $fare; ?>;
                    var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;

                    var totalFare = 0;
                    jQuery('.selected-seat-table tbody tr').each(function() {
                        if ($(this).hasClass('seat_selected_price')) {
                            totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
                        }
                    })

                    jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
                    jQuery('#tq<?php echo $id; ?>').val(rowCount);
                    jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html("<?php echo get_woocommerce_currency_symbol(); ?> <span class='price-figure'>" + totalFare.toFixed(2) + "</span>");
                    jQuery('#tfi<?php echo $id; ?>').val(totalFare.toFixed(2));
                    // if (totalFare == 0) {
                    //     jQuery('#bus-booking-btn<?php echo $id; ?>').hide();
                    // }
                    // alert(totalFare);
                    mageGrandPrice(parents);
                }

                // function wbt_update_passenger_form(seat_name = '') {

                //     var input = jQuery('#tq<?php //echo $id; 
                                                ?>').val() || 0;
                //     var children = jQuery('#divParent<?php //echo $id; 
                                                        ?> > div').length || 0;

                //     if (input < children) {
                //         jQuery('#divParent<?php //echo $id; 
                                                ?>').empty();
                //         children = 0;
                //     }

                //     for (var i = children + 1; i <= input; i++) {

                //         jQuery('#divParent<?php //echo $id; 
                                                ?>').append(
                //             jQuery('<div/>')
                //             .attr("id", "newDiv" + i)
                //             .html("<?php //do_action('wbtm_reg_fields', '"+seat_name+"');
                                        ?>")
                //         );
                //     }
                // }

                // Seat plan Passenger info form (New)
                function wbtm_seat_plan_form_builder_new($this, seat_name, onlyES = false, seat_label = '') {

                    let parent = $this.parents('.admin-bus-details');
                    let bus_id = parent.attr('data-bus-id');

                    let qty = 1;
                    let seatType = seat_name;
                    let isSeatPlan = true;

                    $.ajax({
                        url: wbtm_ajaxurl,
                        type: 'POST',
                        async: true,
                        data: {
                            busID: bus_id,
                            seatType: seatType,
                            seats: qty,
                            onlyES: onlyES,
                            action: 'wbtm_form_builder'
                        },
                        beforeSend: function() {
                            parent.find('.wbtm-form-builder-loading').show();
                        },
                        success: function(data) {
                            if (data !== '') {

                                if (parent.find(".mage_customer_info_area").children().length == 0) {
                                    parent.find(".mage_customer_info_area").html(data).find('.seat_name_' + seat_name + ' .mage_title h5').html('Passenger Information<br>Seat Type: ' + seat_label + ' | Seat No: ' + seat_name);
                                } else {

                                    if (seat_name != 'ES') {
                                        parent.find(".mage_customer_info_area").append(data).find('.seat_name_' + seat_name + ' .mage_title h5').html('Passenger Information<br>Seat Type: ' + seat_label + ' | Seat No: ' + seat_name);
                                        parent.find(".mage_customer_info_area .seat_name_ES").remove();
                                    }

                                }
                                onlyES ? parent.find('.mage_customer_info_area input[name="seat_name[]"]').remove() : null;

                            } else {
                                parent.find(".mage_customer_info_area").empty();
                            }
                            // Loading hide
                            parent.find('.wbtm-form-builder-loading').hide();
                        }
                    });
                }






                function wbtm_remove_form_builder($this, seat_name) {
                    $this.find(".mage_customer_info_area .seat_name_" + seat_name).remove();
                    // ES qty
                    let es_table = $this.find('.wbtm_extra_service_table');
                    let es_qty = 0;
                    es_table.find('tbody tr').each(function() {
                        tp = $(this).find('.extra-qty-box').val();
                        es_qty += tp > 0 ? parseInt(tp) : 0;
                    });

                    if (es_qty > 0) {
                        wbtm_seat_plan_form_builder_new($this.find('.bus-info-sec'), 'ES', true);
                    }
                }

                // Custom Reg Field New way
                function mageCustomRegField($this, seatType, qty, onlyES = false) {
                    let parent = $this.parents('.admin-bus-details');
                    let bus_id = parent.attr('data-bus-id');

                    $.ajax({
                        url: wbtm_ajaxurl,
                        type: 'POST',
                        async: true,
                        data: {
                            busID: bus_id,
                            seatType: seatType,
                            seats: qty,
                            onlyES: onlyES,
                            action: 'wbtm_form_builder'
                        },
                        beforeSend: function() {
                            parent.find('.wbtm-form-builder-loading').show();
                        },
                        success: function(data) {
                            let s = seatType.toLowerCase();
                            if (data !== '') {
                                $(".wbtm-form-builder-" + s).html(data);
                                $(".wbtm-form-builder-" + s).find('.mage_hidden_customer_info_form').each(function(index) {
                                    $(this).removeClass('mage_hidden_customer_info_form').find('.mage_form_list').slideDown(200);
                                    onlyES ? $(this).find('input[name="seat_name[]"]').remove() : null;
                                    $(this).find('.mage_title h5').html(seatType + ' : ' + (index + 1));
                                });

                            } else {
                                parent.find(".wbtm-form-builder-" + s).empty();
                            }
                            parent.find('.wbtm-form-builder-loading').hide();
                        }
                    });
                }

                jQuery("#view_panel_<?php echo $id; ?>").click(function() {
                    jQuery("#admin-bus-details<?php echo $id; ?>").slideToggle("slow", function() {
                        // Animation complete.
                    });
                });

                // Any date return

                jQuery('.wbtm_anydate_return_wrap').hide();
                jQuery('.blank_seat,.admin_passenger_type_list ul li,.selected-seat-table a.button').click(function(e) {
                    // e.stopImmediatePropagation();
                    e.preventDefault();

                    jQuery('.wbtm_anydate_return_wrap').hide();
                    let $this = jQuery(this);
                    let parent = $this.parents('form');
                    let seat_list = parent.find('.selected-seat-table tbody').children('.seat_selected_price');

                    setTimeout(
                        function() {
                            if (seat_list.length > 0) {
                                jQuery('.wbtm_anydate_return_wrap').show();
                                jQuery('.wbtm_anydate_return_switch label:first-child').trigger('click');
                                jQuery('.wbtm_anydate_return_switch label:first-child').addClass('active');
                                jQuery('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
                            } else {
                                jQuery('.wbtm_anydate_return_wrap').hide();
                            }
                        },
                        1000);

                });

                jQuery('.mage-seat-qty input').on('input', function() {
                    let $this = $(this);
                    let type = $this.attr('data-seat-type');
                    let qty = $this.val();
                    qty = qty > 0 ? qty : 0;

                    if (type) {
                        if (qty > 0) {
                            jQuery('.wbtm_anydate_return_wrap').show();
                            jQuery('.wbtm_anydate_return_switch label:nth-child(1)').trigger('click');
                            jQuery('.wbtm_anydate_return_switch label:first-child').addClass('active');
                            jQuery('.wbtm_anydate_return_switch label:first-child .wbtm_anydate_return').prop('checked', true);
                        } else {
                            jQuery('.wbtm_anydate_return_wrap').hide();
                        }
                    }

                });

                jQuery('.wbtm_anydate_return_switch label').click(function(e) {
                    e.stopImmediatePropagation();
                    e.preventDefault();

                    let $this = jQuery(this);
                    let parent = $this.parents('form');
                    let target = jQuery('.wbtm_anydate_return_switch label');

                    target.removeClass('active');

                    $this.addClass('active');
                    target.find('.wbtm_anydate_return').prop('checked', false);

                    $this.find('.wbtm_anydate_return').prop('checked', true);
                    let value = $this.find('.wbtm_anydate_return').val();
                    let seat_plan_price = parent.find('.selected-seat-table tbody .mage-price-total .price-figure');
                    let without_seat_plan_price = parent.find('.mage-price-total .price-figure');

                    let curr_symbol = php_vars.currency_symbol;



                    if (value == 'on') {

                        if (seat_plan_price.length > 0) {
                            let seat_list = parent.find('.selected-seat-table tbody').children();
                            let amount = 0;
                            for (let i = 1; i <= seat_list.length; i++) {
                                let current_price = parent.find('.selected-seat-table tbody tr:nth-child(' + i + ') input.seat_fare').val();
                                if (typeof current_price !== "undefined") {
                                    amount += parseFloat(current_price);
                                }
                            }
                            let new_amount = amount * 2;
                            let thisSubtotal = seat_plan_price;
                            thisSubtotal.html(new_amount);
                            parent.find('#wbtm_anydate_return_price').val(amount);

                        } else {
                            let seat_list = parent.find('.mage-seat-table tbody').children();
                            let amount = 0;
                            for (let i = 1; i <= seat_list.length; i++) {
                                let current_price = parent.find('.mage-seat-table tbody tr:nth-child(' + i + ') .mage-seat-price').attr('data-price');

                                if (typeof current_price !== "undefined") {
                                    amount += parseFloat(current_price);
                                }

                            }

                            let new_amount = amount * 2;
                            let thisSubtotal = without_seat_plan_price;
                            thisSubtotal.html(new_amount);
                            $this.parents('form').find('#wbtm_anydate_return_price').val(amount);

                        }

                    }

                    if (value == 'off') {

                        if (seat_plan_price.length > 0) {
                            let seat_list = parent.find('.selected-seat-table tbody').children();
                            let amount = 0;
                            for (let i = 1; i <= seat_list.length; i++) {
                                let current_price = parent.find('.selected-seat-table tbody tr:nth-child(' + i + ') input.seat_fare').val();
                                if (typeof current_price !== "undefined") {
                                    amount += parseFloat(current_price);
                                }
                            }
                            let thisSubtotal = seat_plan_price;
                            thisSubtotal.html(amount);
                            parent.find('#wbtm_anydate_return_price').val('');

                        } else {
                            let seat_list = parent.find('.mage-seat-table tbody').children();
                            let amount = 0;
                            for (let i = 1; i <= seat_list.length; i++) {
                                let current_price = parent.find('.mage-seat-table tbody tr:nth-child(' + i + ') .mage-seat-price').attr('data-price');

                                if (typeof current_price !== "undefined") {
                                    amount += parseFloat(current_price);
                                }

                            }

                            let thisSubtotal = without_seat_plan_price;
                            thisSubtotal.html(amount);
                            $this.parents('form').find('#wbtm_anydate_return_price').val('');

                        }

                    }

                    mageGrandPrice(parent);
                });

                // Any date return END

            });
        </script>
        <?php
    }

    public function wbtm_bus_seat_plan($current_plan, $start, $date, $return = false)
    {
        $global_plan = get_post_meta(get_the_id(), 'wbtm_bus_seats_info', true);
        if (!empty($global_plan)) {
            wbtm_seat_global($start, $date, '', $return);
        } else {
            if ($current_plan == 'seat_plan_1') {
                wbtm_seat_plan_1($start, $date);
            }
            if ($current_plan == 'seat_plan_2') {
                wbtm_seat_plan_2($start, $date);
            }
            if ($current_plan == 'seat_plan_3') {
                wbtm_seat_plan_3($start, $date);
            }
        }
    }

    public function wbtm_get_this_bus_seat_plan()
    {
        $current_plan = get_post_meta(get_the_id(), 'seat_plan', true);
        $bus_meta = get_post_custom(get_the_id());
        if (array_key_exists('wbtm_seat_col', $bus_meta)) {
            $seat_col = $bus_meta['wbtm_seat_col'][0];
            $seat_col_arr = explode(",", $seat_col);
            $seat_column = count($seat_col_arr);
        } else {
            $seat_col = array();
            $seat_column = 0;
        }

        if (array_key_exists('wbtm_seat_row', $bus_meta)) {
            $seat_row = $bus_meta['wbtm_seat_row'][0];
            $seat_row_arr = explode(",", $seat_row);
        } else {
            $seat_row = array();
        }
        if ($current_plan) {
            $current_seat_plan = $current_plan;
        } else {
            if ($seat_column == 4) {
                $current_seat_plan = 'seat_plan_1';
            } else {
                $current_seat_plan = 'seat_plan_2';
            }
        }
        return $current_seat_plan;
    }

    public function wbtm_get_bus_start_time($start, $array)
    {
        if (is_array($array) && sizeof($array) > 0) {
            foreach ($array as $key => $val) {
                if ($val['wbtm_bus_bp_stops_name'] === $start) {
                    return $val['wbtm_bus_bp_start_time'];
                    // return $key;
                }
            }
        }
        return null;
    }

    public function wbtm_get_bus_end_time($end, $array)
    {
        foreach ($array as $key => $val) {
            if ($val['wbtm_bus_next_stops_name'] === $end) {
                return $val['wbtm_bus_next_end_time'];
                // return $key;
            }
        }
        return null;
    }

    public function wbtm_buffer_time_check($bp_time, $date)
    {
        $bus_start_time = date('H:i:s', strtotime($bp_time));
        if (!$bp_time) {
            return 'yes';
        }
        // Get the buffer time set by user
        $bus_buffer_time = $this->bus_get_option('bus_buffer_time', 'general_setting_sec', 0);
        if ($bus_buffer_time > 0) {
            // Convert bus start time into date format
            // $bus_buffer_time = $bus_buffer_time * 60;

            // Make bus search date & bus start time as date format
            $start_bus = $date . ' ' . $bus_start_time;

            // $diff = round((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 3600, 1); // In Hour
            $diff = round(((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 60), 1); // In Minute
            if (abs($diff) != $diff) {
                return 'no';
            }

            if ($diff >= (float) $bus_buffer_time) {
                return 'yes';
            } else {
                return 'no';
            }
        } else {
            $start_bus = $date . ' ' . $bus_start_time;
            $diff = round((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 60, 1); // In Minute
            if (abs($diff) != $diff) {
                return 'no';
            }
            return 'yes';
        }
    }

    public function wbtm_get_seat_status($seat, $date, $bus_id, $start, $end)
    {

        $args = array(
            'post_type' => 'wbtm_bus_booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'relation' => 'AND',
                    array(
                        'key' => 'wbtm_seat',
                        'value' => $seat,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_journey_date',
                        'value' => $date,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_bus_id',
                        'value' => $bus_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_status',
                        'value' => array(1, 2),
                        'compare' => 'IN',
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wbtm_boarding_point',
                        'value' => $start,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wbtm_next_stops',
                        'value' => $start,
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'wbtm_next_stops',
                        'value' => $end,
                        'compare' => 'LIKE',
                    ),
                ),
            ),
        );

        $q = new WP_Query($args);
        // $booking_id = $q->posts[0]->ID;
        $booking_id = (isset($q->posts[0]) ? $q->posts[0]->ID : null);
        $booking_status = get_post_meta($booking_id, 'wbtm_status', true);
        return $booking_status;
    }

    public function get_bus_start_time($bus_id)
    {
        $start_stop_array = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true) ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)) : array();
        $c = 1;
        $start_time = '';
        if (sizeof($start_stop_array) > 0) {
            foreach ($start_stop_array as $stops) {
                if ($c == 1) {
                    $start_time = $stops['wbtm_bus_bp_start_time'];
                }
                # code...
                $c++;
            }
        }
        return $start_time;
    }

    // get bus price
    public function wbtm_get_bus_price($start, $end, $array, $seat_type = '')
    {
        foreach ($array as $key => $val) {
            if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
                //echo '<pre>';print_r($seat_type);echo '</pre>';die();
                if ('1' == $seat_type) {
                    $price = $val['wbtm_bus_child_price'];
                } elseif ('2' == $seat_type) {
                    $price = $val['wbtm_bus_infant_price'];
                } elseif ('3' == $seat_type) {
                    $price = $val['wbtm_bus_special_price'];
                } else {
                    $price = $val['wbtm_bus_price'];
                }
                return $price;
            }
        }
        return null;
    }

    public function wbtm_check_od_in_range($start_date, $end_date, $j_date)
    {
        // Convert to timestamp
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        $user_ts = strtotime($j_date);

        // Check that user date is between start & end
        if (($user_ts >= $start_ts) && ($user_ts <= $end_ts)) {
            return 'yes';
        } else {
            return 'no';
        }
    }

    public function wbtm_array_strip($string, $allowed_tags = null)
    {
        if (is_array($string)) {
            foreach ($string as $k => $v) {
                $string[$k] = $this->wbtm_array_strip($v, $allowed_tags);
            }
            return $string;
        }
        return strip_tags($string, $allowed_tags);
    }

    public function wbtm_get_seat_cehck_before_order($seat, $date, $bus_id, $start)
    {
        global $wpdb;
        $total_booking_id = 0;
        $total_booking = 0;
        foreach ($seat as $_seat) {
            $the_bus_stops = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
            if (!is_array($the_bus_stops)) {
                $the_bus_stops = unserialize($the_bus_stops);
            }
            $the_bus_stops = array_column($the_bus_stops, 'wbtm_bus_bp_stops_name');

            $args = array(
                'post_type' => 'wbtm_bus_booking',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'wbtm_seat',
                            'value' => $_seat,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_journey_date',
                            'value' => $date,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_bus_id',
                            'value' => $bus_id,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_status',
                            'value' => 3,
                            'compare' => '!=',
                        ),
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => 'wbtm_next_stops',
                            'value' => $start,
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key' => 'wbtm_next_stops',
                            'value' => $start,
                            'compare' => 'LIKE',
                        ),
                    ),
                ),
            );

            $q = new WP_Query($args);
            $total_booking_id = $q->post_count + $total_booking;
        }

        return $total_booking_id;
    }

    public function wbtm_get_seat_cehck_before_place_order($seat, $date, $bus_id, $start)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "wbtm_bus_booking_list";
        $total_booking_id = 0;
        $total_booking = 0;

        foreach ($seat as $_seat) {

            $_seat = $_seat['wbtm_seat_name'];

            $args = array(
                'post_type' => 'wbtm_bus_booking',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'wbtm_seat',
                            'value' => $_seat,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_journey_date',
                            'value' => $date,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_bus_id',
                            'value' => $bus_id,
                            'compare' => '=',
                        ),
                        array(
                            'key' => 'wbtm_status',
                            'value' => 3,
                            'compare' => '!=',
                        ),
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key' => 'wbtm_next_stops',
                            'value' => $start,
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key' => 'wbtm_next_stops',
                            'value' => $start,
                            'compare' => 'LIKE',
                        ),
                    ),
                ),
            );
            $q = new WP_Query($args);
            $total_booking_id = $q->post_count + $total_booking;
        }
        return $total_booking_id;
    }

    public function wbtm_get_order_meta($item_id, $key)
    {
        global $wpdb;
        $value = '';
        $table_name = $wpdb->prefix . "woocommerce_order_itemmeta";
        $sql = 'SELECT meta_value FROM ' . $table_name . ' WHERE order_item_id =' . $item_id . ' AND meta_key="' . $key . '"';
        $results = $wpdb->get_results($sql);
        foreach ($results as $result) {
            $value = $result->meta_value;
        }
        return $value;
    }

    public function wbtm_get_order_seat_check($bus_id, $order_id, $seat, $bus_start, $date)
    {
        $args = array(
            'post_type' => 'wbtm_bus_booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_seat',
                    'value' => $seat,
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_journey_date',
                    'value' => $date,
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_bus_start',
                    'value' => $bus_start,
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_order_id',
                    'value' => $order_id,
                    'compare' => '=',
                ),
            ),
        );
        $q = new WP_Query($args);
        $total_booking_id = $q->post_count;
        return $total_booking_id;
    }

    public function update_bus_seat_status($order_id, $bus_id, $status)
    {
        $args = array(
            'post_type' => 'wbtm_bus_booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'wbtm_bus_id',
                    'value' => $bus_id,
                    'compare' => '=',
                ),
                array(
                    'key' => 'wbtm_order_id',
                    'value' => $order_id,
                    'compare' => '=',
                ),
            ),
        );
        $q = new WP_Query($args);
        foreach ($q->posts as $bus) {
            # code...
            update_post_meta($bus->ID, 'wbtm_status', $status);
        }
    }

    public function create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, $_seats = null, $fare = null, $j_date = null, $add_datetime = null, $user_name = null, $user_email = null, $passenger_type = null, $passenger_type_num = null, $user_phone = null, $user_gender = null, $user_address = null, $wbtm_extra_bag_qty = null, $extra_bag_price = null, $usr_inf = null, $counter = null, $status = null, $order_meta = null, $wbtm_billing_type = null, $city_zone = null, $wbtm_pickpoint = null, $extra_services = array(), $user_additional = null, $wbtm_is_return = 0, $wbtm_anydate_return = null, $wbtm_anydate_return_price = null, $calculated_fare = null)
    {

        $add_datetime = current_time("Y-m-d") . ' ' . mage_wp_time(current_time("H:i"));
        $name = '#' . $order_id . get_the_title($bus_id);
        $new_post = array(
            'post_title' => $name,
            'post_content' => '',
            'post_category' => array(),
            'tags_input' => array(),
            'post_status' => 'publish',
            'post_type' => 'wbtm_bus_booking',
        );

        //SAVE THE POST
        $pid = wp_insert_post($new_post);
        update_post_meta($pid, 'wbtm_order_id', $order_id);
        update_post_meta($pid, 'wbtm_bus_id', $bus_id);
        update_post_meta($pid, 'wbtm_user_id', $user_id);
        update_post_meta($pid, 'wbtm_boarding_point', $start);
        update_post_meta($pid, 'wbtm_next_stops', $next_stops);
        update_post_meta($pid, 'wbtm_droping_point', $end);
        update_post_meta($pid, 'wbtm_bus_start', $b_time);
        update_post_meta($pid, 'wbtm_user_start', $j_time);
        update_post_meta($pid, 'wbtm_seat', $_seats);
        update_post_meta($pid, 'wbtm_bus_fare', $fare);
        update_post_meta($pid, 'wbtm_journey_date', $j_date);
        update_post_meta($pid, 'wbtm_booking_date', $add_datetime);
        update_post_meta($pid, 'wbtm_status', $status);
        update_post_meta($pid, 'wbtm_ticket_status', 1);
        update_post_meta($pid, 'wbtm_user_name', $user_name);
        update_post_meta($pid, 'wbtm_user_email', $user_email);
        update_post_meta($pid, 'wbtm_user_phone', $user_phone);
        update_post_meta($pid, 'wbtm_user_gender', $user_gender);
        update_post_meta($pid, 'wbtm_user_address', $user_address);
        update_post_meta($pid, 'wbtm_user_extra_bag', $wbtm_extra_bag_qty);
        update_post_meta($pid, 'wbtm_user_extra_bag_price', $extra_bag_price);
        update_post_meta($pid, 'wbtm_passenger_type', $passenger_type);
        update_post_meta($pid, 'wbtm_passenger_type_num', $passenger_type_num);
        update_post_meta($pid, 'wbtm_billing_type', $wbtm_billing_type);
        update_post_meta($pid, 'wbtm_city_zone', $city_zone);
        update_post_meta($pid, 'wbtm_pickpoint', $wbtm_pickpoint);
        update_post_meta($pid, 'wbtm_user_additional', $user_additional);
        update_post_meta($pid, 'wbtm_is_return', $wbtm_is_return);
        update_post_meta($pid, '_wbtm_anydate_return', $wbtm_anydate_return);
        update_post_meta($pid, '_wbtm_anydate_return_price', $wbtm_anydate_return_price);
        update_post_meta($pid, '_wbtm_tp', $calculated_fare);

        if ($wbtm_billing_type && $j_date && function_exists('mtsa_calculate_valid_date')) {
            $sub_end_date = mtsa_calculate_valid_date($j_date, $wbtm_billing_type);
            update_post_meta($pid, 'wbtm_sub_end_date', $sub_end_date);
        }


        if (!empty($extra_services)) {
            foreach ($extra_services as $service) {
                update_post_meta($pid, 'extra_services_type_qty_' . $service['name'], $service['qty']);
                update_post_meta($pid, 'extra_services_type_price_' . $service['name'], $service['price']);
            }
        }
        update_post_meta($pid, 'wbtm_extra_services', maybe_serialize($extra_services));

        // Custom Field
        if ($order_meta) {
            $get_custom_fields = $this->bus_get_option('custom_fields', 'general_setting_sec', 0);
            if ($get_custom_fields) {
                $get_custom_fields_arr = explode(',', $get_custom_fields);
                if ($get_custom_fields_arr) {
                    foreach ($get_custom_fields_arr as $item) {
                        if (isset($order_meta[$item][0])) {
                            update_post_meta($pid, 'wbtm_custom_field_' . $item, $order_meta[$item][0]);
                        } else {
                            update_post_meta($pid, 'wbtm_custom_field_' . $item, null);
                        }
                    }
                }
            }
        }

        // if($_seats) {
        //     $reg_form_arr = unserialize(get_post_meta($bus_id, 'attendee_reg_form', true));
        //     if (is_array($reg_form_arr) && sizeof($reg_form_arr) > 0) {
        //         foreach ($reg_form_arr as $builder) {
        //             update_post_meta($pid, $builder['field_id'], $usr_inf[$counter][$builder['field_id']]);
        //         }
        //     }
        // }
    }

    public function bus_order_processed($order_id)
    {
        // Getting an instance of the order object
        $order = wc_get_order($order_id);
        $order_meta = get_post_meta($order_id);
        // echo '<pre>';
        // print_r($order_meta);
        // die;

        $order_status = $order->get_status();
        if ($order_status != 'failed') {

            # Iterating through each order items (WC_Order_Item_Product objects in WC 3+)
            foreach ($order->get_items() as $item_id => $item_values) {
                $product_id = $item_values->get_product_id();
                $item_data = $item_values->get_data();
                $product_id = $item_data['product_id'];
                $item_quantity = $item_values->get_quantity();
                $product = get_page_by_title($item_data['name'], OBJECT, 'wbtm_bus');
                $event_name = $item_data['name'];
                // $event_id = $product->ID;
                $item_id = $item_id;
                $wbtm_bus_id = $this->wbtm_get_order_meta($item_id, '_wbtm_bus_id');
                if (get_post_type($wbtm_bus_id) == 'wbtm_bus') {

                    $user_id = $order_meta['_customer_user'][0];
                    $bus_id = $this->wbtm_get_order_meta($item_id, '_bus_id');
                    $user_info_arr = $this->wbtm_get_order_meta($item_id, '_wbtm_passenger_info');
                    $user_info_additional_arr = maybe_unserialize($this->wbtm_get_order_meta($item_id, '_wbtm_passenger_info_additional'));
                    $user_single_info_arr = maybe_unserialize($this->wbtm_get_order_meta($item_id, '_wbtm_single_passenger_info'));
                    $user_basic_info_arr = maybe_unserialize($this->wbtm_get_order_meta($item_id, '_wbtm_basic_passenger_info'));
                    $wbtm_billing_type = $this->wbtm_get_order_meta($item_id, '_wbtm_billing_type');
                    $wbtm_city_zone = $this->wbtm_get_order_meta($item_id, '_wbtm_city_zone');
                    $wbtm_pickpoint = $this->wbtm_get_order_meta($item_id, '_wbtm_pickpoint');
                    $extra_services = $this->wbtm_get_order_meta($item_id, '_extra_services');
                    $seat = $this->wbtm_get_order_meta($item_id, 'Seats');
                    $start = $this->wbtm_get_order_meta($item_id, 'Start');
                    $end = $this->wbtm_get_order_meta($item_id, 'End');
                    $j_date = $this->wbtm_get_order_meta($item_id, 'Date');
                    $j_time = $this->wbtm_get_order_meta($item_id, 'Time');
                    $bus_id = $this->wbtm_get_order_meta($item_id, '_bus_id');
                    $b_time = $this->wbtm_get_order_meta($item_id, '_btime');
                    $extra_bag = $this->wbtm_get_order_meta($item_id, 'Extra Bag');
                    $wbtm_tp = $this->wbtm_get_order_meta($item_id, '_wbtm_tp');
                    $wbtm_is_return = $this->wbtm_get_order_meta($item_id, '_wbtm_is_return');
                    $wbtm_anydate_return = $this->wbtm_get_order_meta($item_id, '_wbtm_anydate_return');
                    $wbtm_anydate_return_price = $this->wbtm_get_order_meta($item_id, '_wbtm_anydate_return_price');
                    $calculated_fare = $this->wbtm_get_order_meta($item_id, '_wbtm_tp');
                    $seats = ($seat) ? explode(",", $seat) : null;
                    $usr_inf = unserialize($user_info_arr);

                    $counter = 0;
                    $next_stops = maybe_serialize($this->wbtm_get_all_stops_after_this($bus_id, $start, $end));
                    $price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));
                    $extra_bag_price = get_post_meta($bus_id, 'wbtm_extra_bag_price', true) ? get_post_meta($bus_id, 'wbtm_extra_bag_price', true) : 0;

                    $add_datetime = date("Y-m-d h:i:s");
                    // $fare = $this->wbtm_get_bus_price($start, $end, $price_arr);
                    if ($seats) {
                        foreach ($seats as $_seats) {
                            // $fare = $this->wbtm_get_bus_price($start, $end, $price_arr, $usr_inf[$counter]['wbtm_passenger_type_num']);
                            if (!empty($_seats)) {
                                $fare = $user_basic_info_arr[$counter]['wbtm_seat_fare'];

                                if (is_array($user_single_info_arr) && sizeof($user_single_info_arr) > 0) {

                                    if (isset($usr_inf[$counter]['wbtm_user_name'])) {
                                        if ($usr_inf[$counter]['wbtm_user_name'] != '') {
                                            $user_name = $usr_inf[$counter]['wbtm_user_name'];
                                        } else {
                                            $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                                        }
                                    } else {
                                        $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                                    }

                                    $passenger_type = isset($user_basic_info_arr[$counter]['wbtm_passenger_type']) ? $user_basic_info_arr[$counter]['wbtm_passenger_type'] : '';

                                    $passenger_type_num = isset($usr_inf[$counter]['wbtm_passenger_type_num']) ? $usr_inf[$counter]['wbtm_passenger_type_num'] : '';

                                    if (isset($usr_inf[$counter]['wbtm_user_email'])) {
                                        if ($usr_inf[$counter]['wbtm_user_email'] != '') {
                                            $user_email = $usr_inf[$counter]['wbtm_user_email'];
                                        } else {
                                            $user_email = $order_meta['_billing_email'][0];
                                        }
                                    } else {
                                        $user_email = $order_meta['_billing_email'][0];
                                    }

                                    if (isset($usr_inf[$counter]['wbtm_user_phone'])) {
                                        if ($usr_inf[$counter]['wbtm_user_phone'] != '') {
                                            $user_phone = $usr_inf[$counter]['wbtm_user_phone'];
                                        } else {
                                            $user_phone = $order_meta['_billing_phone'][0];
                                        }
                                    } else {
                                        $user_phone = $order_meta['_billing_phone'][0];
                                    }

                                    if (isset($usr_inf[$counter]['wbtm_user_address'])) {
                                        if ($usr_inf[$counter]['wbtm_user_address'] != '') {
                                            $user_address = $usr_inf[$counter]['wbtm_user_address'];
                                        } else {
                                            $user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
                                        }
                                    } else {
                                        $user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
                                    }

                                    $user_gender = isset($usr_inf[$counter]['wbtm_user_gender']) ? $usr_inf[$counter]['wbtm_user_gender'] : '';
                                    $user_additional = ($user_info_additional_arr ? maybe_serialize($user_info_additional_arr[$counter]) : '');
                                } else {
                                    $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                                    $passenger_type = isset($usr_inf[0]['wbtm_passenger_type']) ? $usr_inf[0]['wbtm_passenger_type'] : '';
                                    $passenger_type_num = isset($usr_inf[0]['wbtm_passenger_type_num']) ? $usr_inf[0]['wbtm_passenger_type_num'] : '';
                                    $user_email = $order_meta['_billing_email'][0];
                                    $user_phone = $order_meta['_billing_phone'][0];
                                    $user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
                                    $user_gender = '';
                                    $user_additional = '';
                                }

                                // echo $user_name;
                                // die;

                                if (isset($usr_inf[$counter]['wbtm_extra_bag_qty'])) {
                                    $wbtm_extra_bag_qty = $usr_inf[$counter]['wbtm_extra_bag_qty'];
                                    $fare = $fare + ($extra_bag_price * $wbtm_extra_bag_qty);
                                } else {
                                    $wbtm_extra_bag_qty = 0;
                                    $extra_bag_price = 0;
                                }

                                // Extra Service with seat
                                $extra_services_arr = maybe_unserialize($extra_services);
                                // if($extra_services_arr) {
                                //     foreach($extra_services_arr as $service) {
                                //         $fare += $service['price'] * $service['qty'];
                                //     }
                                // }

                                $this->create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, $_seats, $fare, $j_date, $add_datetime, $user_name, $user_email, $passenger_type, $passenger_type_num, $user_phone, $user_gender, $user_address, $wbtm_extra_bag_qty, $extra_bag_price, $usr_inf, $counter, 3, $order_meta, $wbtm_billing_type, $wbtm_city_zone, $wbtm_pickpoint, $extra_services_arr, $user_additional, $wbtm_is_return, $wbtm_anydate_return, $wbtm_anydate_return_price, $calculated_fare);
                            }

                            $counter++;
                        }
                    } else { // Only Extra Service
                        $fare = 0;
                        $extra_services_arr = maybe_unserialize($extra_services);
                        if ($extra_services_arr) {
                            foreach ($extra_services_arr as $service) {
                                $fare += $service['price'] * $service['qty'];
                            }
                        }

                        // Passenger Info
                        if (is_array($user_single_info_arr) && sizeof($user_single_info_arr) > 0) {

                            if (isset($usr_inf[$counter]['wbtm_user_name'])) {
                                if ($usr_inf[$counter]['wbtm_user_name'] != '') {
                                    $user_name = $usr_inf[$counter]['wbtm_user_name'];
                                } else {
                                    $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                                }
                            } else {
                                $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                            }

                            if (isset($usr_inf[$counter]['wbtm_user_email'])) {
                                if ($usr_inf[$counter]['wbtm_user_email'] != '') {
                                    $user_email = $usr_inf[$counter]['wbtm_user_email'];
                                } else {
                                    $user_email = $order_meta['_billing_email'][0];
                                }
                            } else {
                                $user_email = $order_meta['_billing_email'][0];
                            }

                            if (isset($usr_inf[$counter]['wbtm_user_phone'])) {
                                if ($usr_inf[$counter]['wbtm_user_phone'] != '') {
                                    $user_phone = $usr_inf[$counter]['wbtm_user_phone'];
                                } else {
                                    $user_phone = $order_meta['_billing_phone'][0];
                                }
                            } else {
                                $user_phone = $order_meta['_billing_phone'][0];
                            }

                            if (isset($usr_inf[$counter]['wbtm_user_address'])) {
                                if ($usr_inf[$counter]['wbtm_user_address'] != '') {
                                    $user_address = $usr_inf[$counter]['wbtm_user_address'];
                                } else {
                                    $user_address = $order_meta['_billing_address_1'][0];
                                }
                            } else {
                                $user_address = $order_meta['_billing_address_1'][0];
                            }

                            $user_gender = isset($usr_inf[$counter]['wbtm_user_gender']) ? $usr_inf[$counter]['wbtm_user_gender'] : '';
                            $user_additional = ($user_info_additional_arr ? maybe_serialize($user_info_additional_arr[$counter]) : '');
                        } else {
                            $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                            $user_email = $order_meta['_billing_email'][0];
                            $user_phone = $order_meta['_billing_phone'][0];
                            $user_address = $order_meta['_billing_address_1'][0];
                            $user_gender = '';
                            $user_additional = '';
                        }

                        if (isset($usr_inf[$counter]['wbtm_extra_bag_qty'])) {
                            $wbtm_extra_bag_qty = $usr_inf[$counter]['wbtm_extra_bag_qty'];
                            $fare = $fare + ($extra_bag_price * $wbtm_extra_bag_qty);
                        } else {
                            $wbtm_extra_bag_qty = 0;
                            $extra_bag_price = 0;
                        }

                        // $user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
                        // $user_email = $order_meta['_billing_email'][0];
                        // $user_phone = $order_meta['_billing_phone'][0];
                        // $user_address = $order_meta['_billing_address_1'][0];

                        $this->create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, null, $fare, $j_date, $add_datetime, $user_name, $user_email, null, null, $user_phone, $user_gender, $user_address, $wbtm_extra_bag_qty, $extra_bag_price, $usr_inf, $counter, 3, $order_meta, $wbtm_billing_type, $wbtm_city_zone, $wbtm_pickpoint, $extra_services_arr, $wbtm_is_return, $wbtm_anydate_return, $wbtm_anydate_return_price, $calculated_fare);
                    }
                }
            }
        }
    }

    public function wbtm_bus_ticket_seat_management($order_id, $from_status, $to_status, $order)
    {
        global $wpdb;
        // Getting an instance of the order object
        $order = wc_get_order($order_id);
        $order_meta = get_post_meta($order_id);

        # Iterating through each order items (WC_Order_Item_Product objects in WC 3+)
        foreach ($order->get_items() as $item_id => $item_values) {
            $item_id = $item_id;
            $wbtm_bus_id = $this->wbtm_get_order_meta($item_id, '_wbtm_bus_id');
            if (get_post_type($wbtm_bus_id) == 'wbtm_bus') {
                $bus_id = $this->wbtm_get_order_meta($item_id, '_bus_id');

                if ($order->has_status('on-hold')) {
                    $status = 4;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('pending')) {
                    $status = 3;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('processing')) {
                    $status = 1;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('cancelled')) {
                    $status = 5;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('refunded')) {
                    $status = 6;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('failed')) {
                    $status = 7;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
                if ($order->has_status('completed')) {
                    $status = 2;
                    $this->update_bus_seat_status($order_id, $bus_id, $status);
                }
            }
        }
    }

    public function wbtm_get_bus_route_list($name, $value = '')
    {

        global $post;
        if ($post) {
            $values = get_post_custom($post->ID);
        } else {
            $values = '';
        }

        if (is_array($values) && array_key_exists($name, $values)) {
            $seat_name = $name;
            $type_name = $values[$seat_name][0];
        } else {
            $type_name = '';
        }
        $terms = get_terms(array(
            // 'taxonomy' => 'wbtm_bus_route',
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false,
        ));
        if (!empty($terms) && !is_wp_error($terms)) : ob_start(); ?>
            <select name="<?php echo $name; ?>" class='seat_type' required>
                <option value=""><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                <?php foreach ($terms as $term) :
                    $selected = $type_name == $term->name ? 'selected' : '';
                    if (!empty($value)) {
                        $selected = $term->name == $value ? 'selected' : '';
                    }

                    printf('<option %s value="%s">%s</option>', $selected, $term->name, $term->name);
                endforeach; ?>
            </select>
    <?php endif;
        return ob_get_clean();
    }

    public function wbtm_bus_search_fileds($start, $end, $date, $r_date)
    {
        global $wbtmpublic, $start, $end, $date, $r_date;
        ob_start();
        $wbtmpublic->wbtm_template_part('bus-search-form-fields');
        $content = ob_get_clean();
        echo $content;
    }

    public function wbtm_get_available_seat($bus_id, $date)
    {
        $args = array(
            'post_type' => 'wbtm_bus_booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'relation' => 'AND',
                    array(
                        'key' => 'wbtm_journey_date',
                        'value' => $date,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_bus_id',
                        'value' => $bus_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_seat',
                        'value' => NULL,
                        'compare' => '!='
                    ),
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wbtm_status',
                        'value' => 1,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'wbtm_status',
                        'value' => 2,
                        'compare' => '=',
                    ),
                ),
            ),
        );
        $q = new WP_Query($args);
        $total_booking_id = $q->post_count;
        return $total_booking_id;
    }

    public function wbtm_find_product_in_cart($id)
    {
        $product_id = $id;
        $in_cart = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_in_cart = $cart_item['product_id'];
            if ($product_in_cart === $product_id) {
                $in_cart = true;
            }
        }

        if ($in_cart) {
            return 'into-cart';
        } else {
            return 'not-in-cart';
        }
    }

    // Check order status based on 'Send Email on' 
    public function wbtm_order_status_allow($order_status)
    {
        global $wbtmmain;
        $wbtm_email_status = $wbtmmain->bus_get_option('pdf_email_send_on', 'ticket_manager_settings', array());

        $return = false;

        if (!empty($wbtm_email_status) && $order_status) {
            $order_status = strtolower($order_status);

            if (in_array($order_status, $wbtm_email_status)) {
                $return = true;
            } else {
                $return = false;
            }
        } else {
            $return = false;
        }

        return $return;
    }
}

global $wbtmmain;
$wbtmmain = new WBTM_Plugin_Functions();

function wbtm_get_seat_type_list($name, $bus_id = '')
{
    ob_start();
    ?>
    <!-- <div class='field-select2-wrapper'>
<select name="<?php echo $name; ?>[]" id="" class="select2" multiple>
    <option value="adult"><?php echo wbtm_get_seat_type_label('adult', 'Adult'); ?></option>
    <option value="child"><?php echo wbtm_get_seat_type_label('child', 'Child'); ?></option>
    <option value="infant"><?php echo wbtm_get_seat_type_label('infant', 'Infant'); ?></option>
    <option value="special"><?php echo wbtm_get_seat_type_label('special', 'Special'); ?></option>
    <?php do_action('wbtm_seat_type_list_init'); ?>
</select>
</div> -->
<?php
    //echo ob_get_clean();

    echo '';
}

function wbtm_get_seat_type_label($key, $default)
{
    global $wbtmmain;
    $metakey = "wbtm_seat_type_" . $key . "_label";
    return $wbtmmain->bus_get_option($metakey, '', $default);
}

/**
 * The magical Datetime Function, Just call this function where you want display date or time, Pass the date or time and the format this will be return the date or time in the current wordpress saved datetime format and according the timezone.
 */
function get_wbtm_datetime($date, $type)
{
    $date_format = get_option('date_format');
    $time_format = get_option('time_format');
    $wpdatesettings = $date_format . '  ' . $time_format;
    $timezone = wp_timezone_string();
    $timestamp = strtotime($date . ' ' . $timezone);

    if ($type == 'date') {
        return wp_date($date_format, $timestamp);
    }
    if ($type == 'date-time') {
        return wp_date($wpdatesettings, $timestamp);
    }
    if ($type == 'date-text') {

        return wp_date($date_format, $timestamp);
    }

    if ($type == 'date-time-text') {
        return wp_date($wpdatesettings, $timestamp, wp_timezone());
    }
    if ($type == 'time') {
        return wp_date($time_format, $timestamp, wp_timezone());
    }

    if ($type == 'day') {
        return wp_date('d', $timestamp);
    }
    if ($type == 'month') {
        return wp_date('M', $timestamp);
    }
}

function wbtm_convert_datepicker_dateformat()
{
    $date_format = get_option('date_format');
    // return $date_format;
    // $php_d     = array('F', 'j', 'Y', 'm','d','D','M','y');
    // $js_d   = array('d', 'M', 'yy','mm','dd','tt','mm','yy');
    $dformat = str_replace('d', 'dd', $date_format);
    $dformat = str_replace('m', 'mm', $dformat);
    $dformat = str_replace('Y', 'yy', $dformat);

    if ($date_format == 'Y-m-d' || $date_format == 'm/d/Y' || $date_format == 'd/m/Y' || $date_format == 'Y/d/m' || $date_format == 'Y-d-m') {
        return str_replace('/', '-', $dformat);
    } elseif ($date_format == 'Y.m.d' || $date_format == 'm.d.Y' || $date_format == 'd.m.Y' || $date_format == 'Y.d.m' || $date_format == 'Y.d.m') {
        return str_replace('.', '-', $dformat);
    } else {
        return 'yy-mm-dd';
    }
}

function wbtm_convert_date_to_php($date)
{

    $date_format = get_option('date_format');
    if ($date_format == 'Y-m-d' || $date_format == 'm/d/Y' || $date_format == 'm/d/Y') {
        if ($date_format == 'd/m/Y') {
            $date = str_replace('/', '-', $date);
        }
    }
    return date('Y-m-d', strtotime($date));
}

function wbtm_displayDates($date1, $date2, $format = 'd-m-Y')
{
    $dates = array();
    $current = strtotime($date1);
    $date2 = strtotime($date2);
    $stepVal = '+1 day';
    while ($current <= $date2) {
        $dates[] = date($format, $current);
        $current = strtotime($stepVal, $current);
    }
    return  $dates;
}


function wbtm_get_busstop_name($id)
{
    $category = get_term_by('id', $id, 'wbtm_bus_stops');
    return $category->name;
}

add_action('wp_ajax_wbtm_load_dropping_point', 'wbtm_load_dropping_point');
add_action('wp_ajax_nopriv_wbtm_load_dropping_point', 'wbtm_load_dropping_point');
function wbtm_load_dropping_point()
{
    $boardingPoint = strip_tags($_POST['boarding_point']);
    $busId = isset($_POST['bus_id']) ? strip_tags($_POST['bus_id']) : null;
    $category = get_term_by('name', $boardingPoint, 'wbtm_bus_stops');
    $allStopArr = get_terms(array(
        'taxonomy' => 'wbtm_bus_stops',
        'hide_empty' => false,
    ));

    if ($busId) {
        $dropingarray = array();
        $prices = get_post_meta($busId, 'wbtm_bus_prices', true);
        if ($prices) {
            foreach ($prices as $price) {
                if ($price['wbtm_bus_bp_price_stop'] == $boardingPoint) {
                    $dropingarray[] = $price['wbtm_bus_dp_price_stop'];
                }
            }

            if (sizeof($dropingarray) > 0) {
                foreach ($dropingarray as $dp) {
                    echo "<li data-route='$dp'><span class='fa fa-map-marker'></span>$dp</li>";
                }
            } else {
                foreach ($allStopArr as $dp) {
                    $name = $dp->name;
                    echo "<li data-route='$name'><span class='fa fa-map-marker'></span>$name</li>";
                }
            }
        }
    } else {
        $dropingarray = get_term_meta($category->term_id, 'wbtm_bus_routes_name_list', true) ? maybe_unserialize(get_term_meta($category->term_id, 'wbtm_bus_routes_name_list', true)) : array();
        if (sizeof($dropingarray) > 0) {
            foreach ($dropingarray as $dp) {
                $name = $dp['wbtm_bus_routes_name'];
                echo "<li data-route='$name'><span class='fa fa-map-marker'></span>$name</li>";
            }
        } else {
            foreach ($allStopArr as $dp) {
                $name = $dp->name;
                echo "<li data-route='$name'><span class='fa fa-map-marker'></span>$name</li>";
            }
        }
    }
    die();
}

// add_action('wbtm_after_search_result_section','wbtm_search_result_list_script');

function wbtm_search_result_list_script()
{
    ob_start();
?>
    <script>
        jQuery('#mage_bus_search_button').on('click', function() {

            var bus_start_route = jQuery('#wbtm_starting_point_inupt').val();
            var bus_end_route = jQuery('#wbtm_dropping_point_inupt').val();
            var j_date = jQuery('#j_date').val();
            var r_date = jQuery('#r_date').val();
            jQuery.ajax({
                type: 'GET',
                url: wbtm_ajax.wbtm_ajaxurl,
                data: {
                    "action": "wbtm_ajax_search_bus",
                    "bus_start_route": bus_start_route,
                    "bus_end_route": bus_end_route,
                    "j_date": j_date,
                    "r_date": r_date
                },
                beforeSend: function() {
                    jQuery('#wbtm_search_result_section').html(
                        '<span class=wbtm-loading-animation><img src="<?php echo WBTM_PLUGIN_URL . 'public/images/'; ?>loading.gif"</span>'
                    );
                },
                success: function(data) {
                    jQuery('#wbtm_search_result_section').html(data);
                }
            });
            return false;


        });


        jQuery('.wbtm_next_day_search').on('click', function() {

            var bus_start_route = jQuery(this).data('sroute');
            var bus_end_route = jQuery(this).data('eroute');
            var j_date = jQuery(this).data('jdate');
            var r_date = jQuery(this).data('rdate');
            jQuery.ajax({
                type: 'GET',
                url: wbtm_ajax.wbtm_ajaxurl,
                data: {
                    "action": "wbtm_ajax_search_bus_tab",
                    "bus_start_route": bus_start_route,
                    "bus_end_route": bus_end_route,
                    "j_date": j_date,
                    "r_date": r_date
                },
                beforeSend: function() {
                    jQuery('.wbtm_search_part').html(
                        '<span class=wbtm-loading-animation><img src="<?php echo WBTM_PLUGIN_URL . 'public/images/'; ?>loading.gif"</span>'
                    );
                },
                success: function(data) {
                    jQuery('.wbtm_search_part').html(data);
                    jQuery('#j_date').val(j_date);


                }
            });
            return false;


        });
    </script>
<?php
    echo ob_get_clean();
}

add_action('wp_ajax_wbtm_ajax_search_bus', 'wbtm_ajax_search_bus');
add_action('wp_ajax_nopriv_wbtm_ajax_search_bus', 'wbtm_ajax_search_bus');
function wbtm_ajax_search_bus()
{
    global $wbtmmain;

    $global_target = $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec') ? get_post_field('post_name', $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec')) : 'bus-search-list';

    echo '<div class="mage_ajax_search_result">';
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {
        mage_next_date_suggestion(false, false, $global_target);
        echo '<div class="wbtm_search_part">';
        mage_bus_route_title(false);
        mage_bus_search_list(false);
        echo '</div>';
    }
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['r_date'])) {
        mage_next_date_suggestion(false, false, $global_target);
        echo '<div class="wbtm_search_part">';
        mage_bus_route_title(true);
        mage_bus_search_list(true);
        echo '</div>';
    }
    echo '</div>';

    die();
}

add_action('wp_ajax_wbtm_ajax_search_bus_tab', 'wbtm_ajax_search_bus_tab');
add_action('wp_ajax_nopriv_wbtm_ajax_search_bus_tab', 'wbtm_ajax_search_bus_tab');
function wbtm_ajax_search_bus_tab()
{
    echo '<div class="mage_ajax_search_result">';
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['j_date'])) {

        echo '<div class="wbtm_search_part">';
        mage_bus_route_title(false);
        mage_bus_search_list(false);
        echo '</div>';
    }
    if (isset($_GET['bus_start_route']) && ($_GET['bus_end_route']) && ($_GET['r_date'])) {

        echo '<div class="wbtm_search_part">';
        mage_bus_route_title(true);
        mage_bus_search_list(true);
        echo '</div>';
    }
    echo '</div>';

    die();
}

function wbtm_bus_target_page_filter_rewrite_rule()
{
    add_rewrite_rule(
        '^bus-search-list/?$',
        'index.php?bussearchlist=busSearchDefault&pagename=bus-search-list',
        'top'
    );
}

add_action('init', 'wbtm_bus_target_page_filter_rewrite_rule');

function wbtm_bus_target_page_query_var($vars)
{
    $vars[''] = 'bussearchlist';
    return $vars;
}

add_filter('query_vars', 'wbtm_bus_target_page_query_var');

function wbtm_wbtm_bus_target_page_template_chooser($template)
{
    global $wp_query;
    $plugin_path = plugin_dir_path(__DIR__);
    $template_name = $plugin_path . 'public/templates/bus-search-list.php';
    //echo get_query_var( 'bussearchlist' );
    if (get_query_var('bussearchlist')) {
        $template = $template_name;
    }
    return $template;
}

add_filter('template_include', 'wbtm_wbtm_bus_target_page_template_chooser');

// Function for create hidden product for bus
function wbtm_create_hidden_event_product($post_id, $title)
{
    $new_post = array(
        'post_title' => $title,
        'post_content' => '',
        'post_name' => uniqid(),
        'post_category' => array(),
        'tags_input' => array(),
        'post_status' => 'publish',
        'post_type' => 'product',
    );

    $pid = wp_insert_post($new_post);

    update_post_meta($post_id, 'link_wc_product', $pid);
    update_post_meta($pid, 'link_wbtm_bus', $post_id);
    update_post_meta($pid, '_price', 0.01);

    update_post_meta($pid, '_sold_individually', 'yes');
    update_post_meta($pid, '_virtual', 'yes');
    $terms = array('exclude-from-catalog', 'exclude-from-search');
    wp_set_object_terms($pid, $terms, 'product_visibility');
    update_post_meta($post_id, 'check_if_run_once', true);
}

function wbtm_on_post_publish($post_id, $post, $update)
{
    if ($post->post_type == 'wbtm_bus' && $post->post_status == 'publish' && empty(get_post_meta($post_id, 'check_if_run_once'))) {

        // ADD THE FORM INPUT TO $new_post ARRAY
        $new_post = array(
            'post_title' => $post->post_title,
            'post_content' => '',
            'post_name' => uniqid(),
            'post_category' => array(), // Usable for custom taxonomies too
            'tags_input' => array(),
            'post_status' => 'publish', // Choose: publish, preview, future, draft, etc.
            'post_type' => 'product', //'post',page' or use a custom post type if you want to
        );
        //SAVE THE POST
        $pid = wp_insert_post($new_post);
        // $product_type = mep_get_option('mep_event_product_type', 'general_setting_sec', 'yes');
        update_post_meta($post_id, 'link_wc_product', $pid);
        update_post_meta($pid, 'link_wbtm_bus', $post_id);
        update_post_meta($pid, '_price', 0.01);
        update_post_meta($pid, '_sold_individually', 'yes');
        update_post_meta($pid, '_virtual', 'yes');
        $terms = array('exclude-from-catalog', 'exclude-from-search');
        wp_set_object_terms($pid, $terms, 'product_visibility');
        update_post_meta($post_id, 'check_if_run_once', true);
    }
}

add_action('wp_insert_post', 'wbtm_on_post_publish', 10, 3);

function wbtm_count_hidden_wc_product($event_id)
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'link_wbtm_bus',
                'value' => $event_id,
                'compare' => '=',
            ),
        ),
    );
    $loop = new WP_Query($args);
    return $loop->post_count;
}

add_action('save_post', 'wbtm_wc_link_product_on_save', 99, 1);
function wbtm_wc_link_product_on_save($post_id)
{

    if (get_post_type($post_id) == 'wbtm_bus') {

        //   if ( ! isset( $_POST['mep_event_reg_btn_nonce'] ) ||
        //   ! wp_verify_nonce( $_POST['mep_event_reg_btn_nonce'], 'mep_event_reg_btn_nonce' ) )
        //     return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $event_name = get_the_title($post_id);

        if (wbtm_count_hidden_wc_product($post_id) == 0 || empty(get_post_meta($post_id, 'link_wc_product', true))) {
            wbtm_create_hidden_event_product($post_id, $event_name);
        }

        $product_id = get_post_meta($post_id, 'link_wc_product', true) ? get_post_meta($post_id, 'link_wc_product', true) : $post_id;
        set_post_thumbnail($product_id, get_post_thumbnail_id($post_id));
        wp_publish_post($product_id);

        // $product_type               = mep_get_option('mep_event_product_type', 'general_setting_sec','yes');

        $_tax_status = isset($_POST['_tax_status']) ? strip_tags($_POST['_tax_status']) : 'none';
        $_tax_class = isset($_POST['_tax_class']) ? strip_tags($_POST['_tax_class']) : '';

        update_post_meta($product_id, '_tax_status', $_tax_status);
        update_post_meta($product_id, '_tax_class', $_tax_class);
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_sold_individually', 'yes');

        // Update post
        $my_post = array(
            'ID' => $product_id,
            'post_title' => $event_name, // new title
            'post_name' => uniqid(), // do your thing here
        );

        // unhook this function so it doesn't loop infinitely
        remove_action('save_post', 'wbtm_wc_link_product_on_save');
        // update the post, which calls save_post again
        wp_update_post($my_post);
        // re-hook this function
        add_action('save_post', 'wbtm_wc_link_product_on_save');
        // Update the post into the database

    }
}

add_action('parse_query', 'wbtm_product_tags_sorting_query');
function wbtm_product_tags_sorting_query($query)
{
    global $pagenow;

    $taxonomy = 'product_visibility';

    $q_vars = &$query->query_vars;

    if ($pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == 'product') {

        $tax_query = array([
            'taxonomy' => 'product_visibility',
            'field' => 'slug',
            'terms' => 'exclude-from-catalog',
            'operator' => 'NOT IN',
        ]);
        $query->set('tax_query', $tax_query);
    }
}

function wbtm_find_product_in_cart($return = false)
{
    $product_id = get_the_id();

    $jdate = $return ? $_GET['r_date'] : $_GET['j_date'];
    $jdate = mage_wp_date($jdate, 'Y-m-d');
    $start = $return ? $_GET['bus_end_route'] : $_GET['bus_start_route'];
    $end = $return ? $_GET['bus_start_route'] : $_GET['bus_end_route'];
    $cart = WC()->cart->get_cart();
    foreach ($cart as $cart_item) {
        if (array_key_exists('wbtm_bus_id', $cart_item) && $cart_item['wbtm_bus_id'] == $product_id && $cart_item['wbtm_start_stops'] == $start && $cart_item['wbtm_end_stops'] == $end && $cart_item['wbtm_journey_date'] == $jdate) {

            return 'mage_bus_in_cart';
        }
    }
    return null;
}

function wbtm_find_seat_in_cart($seat_name, $return = false)
{

    $product_id = get_the_id();
    $cart = WC()->cart->get_cart();
    $jdate = $return ? $_GET['r_date'] : $_GET['j_date'];
    $jdate = mage_wp_date($jdate, 'Y-m-d');
    $start = $return ? $_GET['bus_end_route'] : $_GET['bus_start_route'];
    $end = $return ? $_GET['bus_start_route'] : $_GET['bus_end_route'];
    foreach ($cart as $cart_item) {
        if (array_key_exists('wbtm_bus_id', $cart_item) && $cart_item['wbtm_bus_id'] == $product_id && $cart_item['wbtm_start_stops'] == $start && $cart_item['wbtm_end_stops'] == $end && $cart_item['wbtm_journey_date'] == $jdate) {
            foreach ($cart_item['wbtm_seats'] as $item) {
                if ($item['wbtm_seat_name'] == $seat_name) {
                    return true;
                }
            }
        }
    }
    return null;
}

add_action('woocommerce_order_item_display_meta_value', 'mage_woocommerce_order_item_display_meta_value', 10, 3);
function mage_woocommerce_order_item_display_meta_value($value, $meta, $item)
{
    if ('Date' === $meta->key) {
        $value = mage_wp_date($value);
    }

    return $value;
}

add_action('woocommerce_after_order_itemmeta', 'mage_show_passenger_info_in_order_details', 10, 3);
function mage_show_passenger_info_in_order_details($item_id, $item, $_product)
{
?>
    <style type="text/css">
        .th__title {
            text-transform: capitalize;
            display: inline-block;
            min-width: 140px;
            font-weight: 700
        }

        ul.mage_passenger_list {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            width: 100%;
            border-radius: 3px;
        }

        ul.mage_passenger_list li {
            border-bottom: 1px dashed #ddd;
            padding: 5px 0 10px;
            color: #888;
        }

        ul.mage_passenger_list li h3 {
            padding: 0;
            margin: 0;
            color: #555;
        }
    </style>
    <?php

    $passenger_data = wc_get_order_item_meta($item_id, '_wbtm_passenger_info', true);
    // echo '<pre>'; print_r($passenger_data); die;
    $passenger_data_additional = wc_get_order_item_meta($item_id, '_wbtm_passenger_info_additional', true);
    // echo '<pre>'; print_r($passenger_data_additional); die;
    if ($passenger_data) {
        $event_id = wc_get_order_item_meta($item_id, 'event_id', true);
        $counter = 0;
        if (!empty($passenger_data)) {
            foreach ($passenger_data as $key => $value) {
                echo '<ul class="mage_passenger_list">';
                echo "<li><h3>" . __('Passenger', 'bus-ticket-booking-with-seat-reservation') . ": " . ($counter + 1) . "</h3></li>";
                echo (isset($value['wbtm_user_name'])) ? "<li><span class='th__title'>" . __('Name', 'bus-ticket-booking-with-seat-reservation') . ":</span> $value[wbtm_user_name]</li>" : "";
                echo (isset($value['wbtm_user_email'])) ? "<li><span class='th__title'>" . __('Email', 'bus-ticket-booking-with-seat-reservation') . ":</span> $value[wbtm_user_email]</li>" : "";
                echo (isset($value['wbtm_user_phone'])) ? "<li><span class='th__title'>" . __('Phone', 'bus-ticket-booking-with-seat-reservation') . ":</span> $value[wbtm_user_phone]</li>" : "";
                echo (isset($value['wbtm_user_gender'])) ? "<li><span class='th__title'>" . __('Gender', 'bus-ticket-booking-with-seat-reservation') . ":</span> $value[wbtm_user_gender]</li>" : "";
                echo (isset($value['wbtm_extra_bag_qty'])) ? "<li><span class='th__title'>" . __('Extra Bag', 'bus-ticket-booking-with-seat-reservation') . ":</span> $value[wbtm_extra_bag_qty]</li>" : null;

                // Additional
                if (is_array($passenger_data_additional) && !empty($passenger_data_additional)) {
                    foreach ($passenger_data_additional[$counter] as $data) {
                        echo ($data['name']) ? "<li><span class='th__title'>" . $data['name'] . ":</span> $data[value]</li>" : '';
                    }
                }

                echo '</ul>';
                $counter++;
            }
        }
    } else {
        if (isset($_GET['post'])) {
            $order_meta = get_post_meta($_GET['post']);

            echo '<ul class="mage_passenger_list">';
            echo "<li><h3>" . __('Passenger', 'bus-ticket-booking-with-seat-reservation') . "</h3></li>";

            echo ($order_meta['_billing_first_name'][0]) ? "<li><span class='th__title'>" . __('Name', 'bus-ticket-booking-with-seat-reservation') . ":</span>" . $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0] . "</li>" : "";

            echo ($order_meta['_billing_email'][0]) ? "<li><span class='th__title'>" . __('Email', 'bus-ticket-booking-with-seat-reservation') . ":</span>" . $order_meta['_billing_email'][0] . "</li>" : "";
            echo ($order_meta['_billing_phone'][0]) ? "<li><span class='th__title'>" . __('Phone', 'bus-ticket-booking-with-seat-reservation') . ":</span>" . $order_meta['_billing_phone'][0] . "</li>" : "";

            echo '</ul>';
        }
    }
}

// Ajax Issue
add_action('wp_head', 'wbtm_ajax_url', 5);
add_action('admin_head', 'wbtm_ajax_url', 5);
function wbtm_ajax_url()
{
    ?>
    <script type="text/javascript">
        var wbtm_ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    </script>
<?php
}

add_action('rest_api_init', 'wbtm_bus_cunstom_fields_to_rest_init');
if (!function_exists('wbtm_bus_cunstom_fields_to_rest_init')) {
    function wbtm_bus_cunstom_fields_to_rest_init()
    {
        register_rest_field('wbtm_bus', 'bus_informations', array(
            'get_callback' => 'wbtm_get_bus_custom_meta_for_api',
            'schema' => null,
        ));
    }
}
if (!function_exists('wbtm_get_bus_custom_meta_for_api')) {
    function wbtm_get_bus_custom_meta_for_api($object)
    {
        $post_id = $object['id'];
        $post_meta = get_post_meta($post_id);
        $post_image = get_post_thumbnail_id($post_id);
        if ($post_image) {
            $post_meta["bus_feature_image"] = $post_image ? wp_get_attachment_image_src($post_image, 'full')[0] : null;
        }
        return $post_meta;
    }
}


function wbtm_get_price_including_tax($bus, $price, $args = array())
{

    $args = wp_parse_args(
        $args,
        array(
            'qty' => '',
            'price' => '',
        )
    );

    $_product = get_post_meta($bus, 'link_wc_product', true) ? get_post_meta($bus, 'link_wc_product', true) : $bus;

    $qty = '' !== $args['qty'] ? max(0.0, (float)$args['qty']) : 1;

    $product = wc_get_product($_product);


    $tax_with_price = get_option('woocommerce_tax_display_shop');


    if ('' === $price) {
        return '';
    } elseif (empty($qty)) {
        return 0.0;
    }

    $line_price = (float) $price * $qty;
    $return_price = $line_price;

    if ($product) {
        if ($product->is_taxable()) {


            if (!wc_prices_include_tax()) {
                // echo get_option( 'woocommerce_prices_include_tax' );
                $tax_rates = WC_Tax::get_rates($product->get_tax_class());
                $taxes = WC_Tax::calc_tax($line_price, $tax_rates, false);

                // print_r($tax_rates);

                if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {

                    $taxes_total = array_sum($taxes);
                } else {

                    $taxes_total = array_sum(array_map('wc_round_tax_total', $taxes));
                }

                $return_price = $tax_with_price == 'excl' ? round($line_price, wc_get_price_decimals()) : round($line_price + $taxes_total, wc_get_price_decimals());
            } else {


                $tax_rates = WC_Tax::get_rates($product->get_tax_class());
                $base_tax_rates = WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));

                /**
                 * If the customer is excempt from VAT, remove the taxes here.
                 * Either remove the base or the user taxes depending on woocommerce_adjust_non_base_location_prices setting.
                 */
                if (!empty(WC()->customer) && WC()->customer->get_is_vat_exempt()) { // @codingStandardsIgnoreLine.
                    $remove_taxes = apply_filters('woocommerce_adjust_non_base_location_prices', true) ? WC_Tax::calc_tax($line_price, $base_tax_rates, true) : WC_Tax::calc_tax($line_price, $tax_rates, true);

                    if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
                        $remove_taxes_total = array_sum($remove_taxes);
                    } else {
                        $remove_taxes_total = array_sum(array_map('wc_round_tax_total', $remove_taxes));
                    }

                    // $return_price = round( $line_price, wc_get_price_decimals() );
                    $return_price = round($line_price - $remove_taxes_total, wc_get_price_decimals());
                    /**
                     * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
                     * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
                     * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
                     */
                } else {
                    $base_taxes = WC_Tax::calc_tax($line_price, $base_tax_rates, true);
                    $modded_taxes = WC_Tax::calc_tax($line_price - array_sum($base_taxes), $tax_rates, false);

                    if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
                        $base_taxes_total = array_sum($base_taxes);
                        $modded_taxes_total = array_sum($modded_taxes);
                    } else {
                        $base_taxes_total = array_sum(array_map('wc_round_tax_total', $base_taxes));
                        $modded_taxes_total = array_sum(array_map('wc_round_tax_total', $modded_taxes));
                    }

                    $return_price = $tax_with_price == 'excl' ? round($line_price - $base_taxes_total, wc_get_price_decimals()) : round($line_price - $base_taxes_total + $modded_taxes_total, wc_get_price_decimals());
                }
            }
        }
    }
    // return 0;
    return apply_filters('woocommerce_get_price_including_tax', $return_price, $qty, $product);
}

add_action('manage_wbtm_bus_posts_columns', 'wbtm_posts_column_callback', 5, 2);
function wbtm_posts_column_callback($column)
{
    global $wbtmmain;
    $name = $wbtmmain->get_name();

    $date = $column['date'];
    $bus_cat = $column['taxonomy-wbtm_bus_cat'];
    // Remove
    unset($column['taxonomy-wbtm_bus_pickpoint']);
    unset($column['taxonomy-wbtm_bus_stops']);
    unset($column['taxonomy-mtsa_city_zone']);
    unset($column['date']);
    unset($column['taxonomy-wbtm_bus_cat']);

    $column['wbtm_coach_no'] = __('Coach no', 'bus-ticket-booking-with-seat-reservation');
    $column['wbtm_bus_type'] = $name . ' ' . __('Type', 'bus-ticket-booking-with-seat-reservation');
    $column['taxonomy-wbtm_bus_cat'] = __('Category', 'bus-ticket-booking-with-seat-reservation');
    if (is_plugin_active('bus-marketplace-addon/bus-marketplace.php')) {
        $column['wbtm_added_by'] = __('Added by', 'bus-ticket-booking-with-seat-reservation');
    }
    $column['date'] = $date;
    return $column;
}

add_action('manage_wbtm_bus_posts_custom_column', 'wbtm_posts_custom_column_callback', 5, 2);
function wbtm_posts_custom_column_callback($column, $post_id)
{
    switch ($column) {
        case 'wbtm_coach_no':
            echo "<span class=''>" . get_post_meta($post_id, 'wbtm_bus_no', true) . "</span>";
            break;
        case 'wbtm_bus_type':
            echo "<span class=''>" . wbtm_bus_type($post_id) . "</span>";
            break;
        case 'wbtm_added_by':
            $user_id = get_post_field('post_author', $post_id);
            echo "<span class=''>" . get_the_author_meta('display_name', $user_id) . ' [' . wbtm_get_user_role($user_id) . "]</span>";
            break;
    }
}

add_filter('manage_edit-wbtm_bus_stops_columns', 'wbtm_bus_stops_custom_column');
function wbtm_bus_stops_custom_column($columns)
{
    $columns['id'] = 'ID';
    return $columns;
}

add_filter('manage_wbtm_bus_stops_custom_column', 'wbtm_bus_stops_custom_column_callback', 10, 3);

function wbtm_bus_stops_custom_column_callback($content, $column_name, $term_id)
{
    switch ($column_name) {
        case 'id':
            $content = $term_id;
            break;
    }
    return $content;
}
