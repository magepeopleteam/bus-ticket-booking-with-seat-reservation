<?php
if (!defined('ABSPATH')) exit;  // if direct access

class WBTMMetaBox
{
    public function __construct()
    {

        // Custom Metabox
        add_action('add_meta_boxes', array($this, 'add_meta_box_func'));
        // Tab lists
        add_action('wbtm_meta_box_tab_name', array($this, 'wbtm_add_meta_box_tab_name'), 20);
        // Tab Contents
        add_action('wbtm_meta_box_tab_content', array($this, 'wbtm_add_meta_box_tab_content'), 10);
        add_action('save_post', array($this, 'wbtm_bus_seat_panels_meta_save'));
        add_action('admin_menu', array($this, 'wbtm_remove_post_custom_fields'));
        /*Bus stop ajax*/
        add_action('wp_ajax_wbtm_add_bus_stope', [$this, 'wbtm_add_bus_stope']);
        add_action('wp_ajax_nopriv_wbtm_add_bus_stope', [$this, 'wbtm_add_bus_stope']);

        /*Bus stop ajax*/
        add_action('wp_ajax_wbtm_add_pickup', [$this, 'wbtm_add_pickup']);
        add_action('wp_ajax_nopriv_wbtm_add_pickup', [$this, 'wbtm_add_pickup']);
    }

    /*Add Bus stop ajax function*/
    public function wbtm_add_bus_stope()
    {
        if (isset($_POST['name'])) {
            $terms = wp_insert_term($_POST['name'], 'wbtm_bus_stops', $args = array('description' => $_POST['description']));

            if ( is_wp_error($terms) ) {
                echo json_encode(array(
                    'text' => 'error'
                ));
            }else{
                echo json_encode(array(
                    'text' => $_POST['name'],
                    'term_id' => $terms['term_id']
                ));
            }
        }
        die();
    }

    /*Add Pickup ajax function*/
    public function wbtm_add_pickup()
    {
        if (isset($_POST['name'])) {
            $terms = wp_insert_term($_POST['name'], 'wbtm_bus_pickpoint', $args = array('description' => $_POST['description']));

            if ( is_wp_error($terms) ) {
                echo json_encode(array(
                    'text' => 'error'
                ));
            }else{
                echo json_encode(array(
                    'text' => $_POST['name'],
                    'term_id' => $terms['term_id']
                ));
            }
        }
        die();
    }

    public function add_meta_box_func()
    {

        $bus_information =  mage_bus_setting_value('bus_menu_label', 'Bus').__(' Information :', 'bus-ticket-booking-with-seat-reservation');

        add_meta_box('wbtm_add_meta_box', '<span class="dashicons dashicons-info"></span>'.$bus_information . get_the_title(get_the_id()), array($this, 'mp_event_all_in_tab'), 'wbtm_bus', 'normal', 'high');
    }


    public function mp_event_all_in_tab()
    {
        $post_id = get_the_id();
        ?>

        <div class="mp_event_all_meta_in_tab mp_event_tab_area">
            <div class="mp_tab_menu">
                <ul>
                    <?php do_action('wbtm_meta_box_tab_name', $post_id); ?>
                </ul>
            </div>
            <div class="mp_tab_details">
                <?php do_action('wbtm_meta_box_tab_content', $post_id); ?>
            </div>
        </div>
        <?php
    }

    // Tab lists
    public function wbtm_add_meta_box_tab_name($tour_id)
    {
        $vehicle_name = mage_bus_setting_value('bus_menu_label', 'Bus');
        $label_bus_configuration = $vehicle_name . ' ' . __('Configuration', 'bus-ticket-booking-with-seat-reservation');
        ?>
        <li data-target-tabs="#wbtm_ticket_panel" class="active">
            <i class="fas fa-sliders-h"></i>&nbsp;&nbsp;<?php echo $label_bus_configuration; ?>
        </li>
        <li class="wbtm_routing_tab" data-target-tabs="#wbtm_routing">
            <i class="fas fa-map-marked-alt"></i>&nbsp;&nbsp;<?php echo __('Routing', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>
        <li data-target-tabs="#wbtm_seat_price" class="ra_seat_price">
            <span class="dashicons dashicons-money-alt"></span>&nbsp;&nbsp;<?php _e('Seat price', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <li class="wbtm_pickuppoint_tab" data-target-tabs="#wbtm_pickuppoint">
            <span class="dashicons dashicons-flag"></span>&nbsp;&nbsp;<?php echo __('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <li data-target-tabs="#wbtm_bus_off_on_date">
            <span class="dashicons dashicons-calendar-alt"></span>&nbsp;&nbsp;<?php echo $vehicle_name . ' ' . __('Onday & Offday', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>

        <?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
        <li data-target-tabs="#wbtm_bus_tax">
            <span class="dashicons dashicons-admin-settings"></span>&nbsp;&nbsp;<?php _e('Tax', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>
    <?php } ?>

        <?php if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) : ?>
        <li data-target-tabs="#_mep_pp_deposits_type">
            <span class=""></span>&nbsp;&nbsp;<?php _e('Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?>
        </li>
    <?php endif;
    }

    // Tab Contents
    public function wbtm_add_meta_box_tab_content($tour_id)
    {
        global $post;
        $values = get_post_custom($post->ID);
        $show_pickup_point = array_key_exists('show_pickup_point', $values) ? $values['show_pickup_point'][0] : '';

        $show_extra_service = array_key_exists('show_extra_service', $values) ? $values['show_extra_service'][0] : '';

        $weekly_offday = maybe_unserialize(get_post_meta($post->ID, 'weekly_offday', true));


        $this->wbtmRouting();
        $this->wbtmPricing();



        require_once WBTM_PLUGIN_DIR . 'admin/template/meta_box_tab_content.php';
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

    function partial_payment($post_id)
    {
        $values = get_post_custom($post_id);

        do_action('wcpp_partial_product_settings', $values);
    }

    function get_all_tax_list($current_tax = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_tax_rate_classes';
        $result = $wpdb->get_results("SELECT * FROM $table_name");

        foreach ($result as $tax) {
            ?>
            <option value="<?php echo $tax->slug; ?>" <?php if ($current_tax == $tax->slug) {
                echo 'Selected';
            } ?>><?php echo $tax->name; ?></option>
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
        $as_driver = array_key_exists('as_driver', $values) ? $values['as_driver'][0] : null;

        $wbtm_general_same_bus_return = array_key_exists('wbtm_general_same_bus_return', $values) ? $values['wbtm_general_same_bus_return'][0] : '';

        $show_boarding_time = array_key_exists('show_boarding_time', $values) ? $values['show_boarding_time'][0] : 'yes';
        $show_dropping_time = array_key_exists('show_dropping_time', $values) ? $values['show_dropping_time'][0] : 'yes';
        $show_upper_desk = array_key_exists('show_upper_desk', $values) ? $values['show_upper_desk'][0] : '';


        $subscription_type = array_key_exists('mtsa_subscription_route_type', $values) ? $values['mtsa_subscription_route_type'][0] : 'wbtm_city_zone';

        $mtpa_car_type = array_key_exists('mtpa_car_type', $values) ? $values['mtpa_car_type'][0] : '';

       // echo mage_bus_setting_value('bus_menu_label', 'Bus');exit;

        $settings = get_option('wbtm_bus_settings');
       // echo '<pre>';print_r($settings); echo '<pre>';die;
        $same_bus_return_val = isset($settings['same_bus_return_setting']) ? $settings['same_bus_return_setting'] : 'disable';

        $zero_price_allow = array_key_exists('zero_price_allow', $values) ? $values['zero_price_allow'][0] : 'no';

        require_once WBTM_PLUGIN_DIR . 'admin/template/bus_configuration.php';


    }




    public function wbtmRouting()
    {
        global $post;
        $wbbm_bus_bp = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops', true));



       // echo '<pre>';print_r($wbbm_bus_bp);echo '<pre>';die;
        $wbtm_bus_next_stops = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_next_stops', true));

        $wbbm_bus_bp_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops_return', true));
        $wbtm_bus_next_stops_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_next_stops_return', true));

        $values = get_post_custom($post->ID);

        $get_terms_default_attributes = array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false
        );
        $terms = get_terms($get_terms_default_attributes);

        // Global Setting
        $settings = get_option('wbtm_bus_settings');
        $route_disable_switch = isset($settings['route_disable_switch']) ? $settings['route_disable_switch'] : 'off';


        $routing_info = esc_html('Boarding Time & Dropping Time should not be empty.', 'bus-ticket-booking-with-seat-reservation');
        $routing_info .= '<br>';
        $routing_info .= esc_html('If you set those field empty you might get unwanted result.', 'bus-ticket-booking-with-seat-reservation');
        $routing_info .= '<br>';
        $routing_info .= esc_html('If you want to hide dropping time from your customer.', 'bus-ticket-booking-with-seat-reservation');
        $routing_info .= '<br>';
        $routing_info .= esc_html('Just set "Show dropping time" to "no" from Bus configuration tab.', 'bus-ticket-booking-with-seat-reservation');



        require_once WBTM_PLUGIN_DIR . 'admin/template/routing.php';


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


        $mep_events_extra_prices = get_post_meta($post->ID, 'mep_events_extra_prices', true);
        wp_nonce_field('mep_events_extra_price_nonce', 'mep_events_extra_price_nonce');


        $values = get_post_custom($post->ID);
        $show_extra_service = array_key_exists('show_extra_service', $values) ? $values['show_extra_service'][0] : '';


        require_once WBTM_PLUGIN_DIR . 'admin/template/seat_pricing.php';








    }


    // Pickup Point
    public function wbtmPickupPoint()
    {

        global $wbtmmain, $wbtmcore, $post;


        $boarding_points = maybe_unserialize(get_post_meta($post->ID, 'wbtm_bus_bp_stops', true));
        $bus_stops = get_terms(array(
            'taxonomy' => 'wbtm_bus_stops',
            'hide_empty' => false
        ));
        $boarding_points_array = array();
        if ($boarding_points && $bus_stops) {
            $boarding_points = array_column($boarding_points, 'wbtm_bus_bp_stops_name');
            foreach ($bus_stops as $s) {
                foreach ($boarding_points as $item) {
                    if ($item == $s->name) {
                        $boarding_points_array[] = $s;
                    }
                }
            }
        }

        $boarding_points_class = ($boarding_points_array == array())?'ra-display-button':'ra-display-boarding-point';

        // Pickup  point 
        $bus_pickpoints = get_terms(array(
            'taxonomy' => 'wbtm_bus_pickpoint',
            'hide_empty' => false
        ));

        $pickpoints = '';
        if ($bus_pickpoints) {
            foreach ($bus_pickpoints as $points) {
                $pickpoints .= '<option value="' . $points->name . '">' . str_replace("'", '', $points->name) . '</option>';
            }
        }

        require_once WBTM_PLUGIN_DIR . 'admin/template/pickup_point.php';


    }

    public function wbtmBusOnDate()
    {
        global $post;
        $values = get_post_custom($post->ID);

        $ondates = get_post_meta($post->ID, 'wbtm_bus_on_dates', true);
        $wbtm_offday_schedule = maybe_unserialize(get_post_meta($post->ID, 'wbtm_offday_schedule', true));
        $show_operational_on_day = array_key_exists('show_operational_on_day', $values) ? $values['show_operational_on_day'][0] : '';
        $show_off_day = array_key_exists('show_off_day', $values) ? $values['show_off_day'][0] : '';
        $weekly_offday = array_key_exists('weekly_offday', $values) ? maybe_unserialize($values['weekly_offday'][0]) : '';
        if(!is_array($weekly_offday)){
            $weekly_offday = array();
        }

        // Return
        $ondates_return = get_post_meta($post->ID, 'wbtm_bus_on_dates_return', true);
        $wbtm_offday_schedule_return = maybe_unserialize(get_post_meta($post->ID, 'wbtm_offday_schedule_return', true));

        $return_show_operational_on_day = array_key_exists('return_show_operational_on_day', $values) ? $values['return_show_operational_on_day'][0] : '';
        $return_show_off_day = array_key_exists('return_show_off_day', $values) ? $values['return_show_off_day'][0] : '';
        $weekly_offday_return = array_key_exists('weekly_offday_return', $values) ? maybe_unserialize($values['weekly_offday_return'][0]) : '';
        if(!is_array($weekly_offday_return)){
            $weekly_offday_return = array();
        }





        require_once WBTM_PLUGIN_DIR . 'admin/template/bus_onday_offday.php';

        ?>


        <?php
    }





    function wbtm_bus_seat_panels_meta_save($post_id)
    {

        global $post, $wbtmmain;
        if ($post) {
            $pid = $post->ID;
            if ($post->post_type != 'wbtm_bus') {
                return;
            }

            // echo '<pre>'; print_r($_POST); die;


            // Seat Type Conf
            $wbtm_seat_type_conf = $_POST['wbtm_seat_type_conf'];
            $wbtm_bus_no = $_POST['wbtm_bus_no'];
            $wbtm_total_seat = $_POST['wbtm_total_seat'];
            $as_driver = isset($_POST['as_driver']) ? $_POST['as_driver'] : null;
            $wbtm_general_same_bus_return = isset($_POST['wbtm_general_same_bus_return']) ? $_POST['wbtm_general_same_bus_return'] : 'no';
            $show_dropping_time = isset($_POST['show_dropping_time']) ? $_POST['show_dropping_time'] : 'yes';
            $show_boarding_time = isset($_POST['show_boarding_time']) ? $_POST['show_boarding_time'] : 'yes';
            $show_upper_desk = isset($_POST['show_upper_desk']) ? $_POST['show_upper_desk'] : 'no';



            $show_pickup_point = isset($_POST['show_pickup_point']) ? $_POST['show_pickup_point'] : 'no';
            $show_extra_service = isset($_POST['show_extra_service']) ? $_POST['show_extra_service'] : 'no';
            $zero_price_allow = isset($_POST['zero_price_allow']) ? $_POST['zero_price_allow'] : 'no';


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
                        if (isset($_POST['wbtm_bus_bp_start_disable'])) {
                            $bus_boarding_points[$i]['wbtm_bus_bp_start_disable'] = array_key_exists($point, $_POST['wbtm_bus_bp_start_disable']) ? 'yes' : 'no';
                        }
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
            update_post_meta($pid, 'wbtm_route_summary', maybe_serialize($_POST['wbtm_route_summary']));
            if(isset($_POST['return_wbtm_route_summary'])) {
                update_post_meta($pid, 'return_wbtm_route_summary', maybe_serialize($_POST['return_wbtm_route_summary']));
            }
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

                        if (isset($_POST['wbtm_bus_bp_start_disable'])) {
                            $bus_boarding_points_return[$i]['wbtm_bus_bp_start_disable'] = array_key_exists($point, $_POST['wbtm_bus_bp_start_disable']) ? 'yes' : 'no';
                        }
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
                    if ($point && $dropping_points[$i] && $adult_prices[$i] !== '') {

                        if ($zero_price_allow === 'no' && !$adult_prices[$i]) {
                            continue;
                        }

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
                    if ($point && $dropping_points_return[$i] && $adult_prices_return[$i] !== '') {

                        if ($zero_price_allow === 'no' && !$adult_prices_return[$i]) {
                            continue;
                        }

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
            $_tax_status = isset($_POST['_tax_status']) ? strip_tags($_POST['_tax_status']) : 'none';
            $_tax_class = isset($_POST['_tax_class']) ? strip_tags($_POST['_tax_class']) : '';

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
            $names = isset($_POST['option_name']) ? $_POST['option_name'] : array();
            $urls = $_POST['option_price'];
            $qty = $_POST['option_qty'];
            $qty_type = $_POST['option_qty_type'];
            $order_id = 0;
            $count = count($names);

            for ($i = 0; $i < $count; $i++) {
                if ($names[$i] != '') :
                    $extra_service_new[$i]['option_name'] = stripslashes(strip_tags($names[$i]));
                else :
                    continue;
                endif;

                if ($urls[$i] != '') :
                    $extra_service_new[$i]['option_price'] = stripslashes(strip_tags($urls[$i]));
                else :
                    $extra_service_new[$i]['option_price'] = 0;
                endif;

                if ($qty[$i] != '') :
                    $extra_service_new[$i]['option_qty'] = stripslashes(strip_tags($qty[$i]));
                else :
                    $extra_service_new[$i]['option_qty'] = 0;
                endif;

                if ($qty_type[$i] != '') :
                    $extra_service_new[$i]['option_qty_type'] = stripslashes(strip_tags($qty_type[$i]));
                else :
                    $extra_service_new[$i]['option_qty_type'] = 'inputbox';
                endif;
            }

            update_post_meta($post_id, 'mep_events_extra_prices', $extra_service_new ? $extra_service_new : null);
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


            if ($wbtm_general_same_bus_return === 'yes') { // All return data
                // Route
                update_post_meta($pid, 'wbtm_bus_bp_stops_return', $bus_boarding_points_return);
                update_post_meta($pid, 'wbtm_bus_next_stops_return', $bus_dropping_points_return);
                // Seat Price
                update_post_meta($pid, 'wbtm_bus_prices_return', $seat_prices_return);

            }



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

            // Partial Payment
            do_action('wcpp_partial_settings_saved', $pid);
            // Partial Payment END

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
            $prev_as_driver = get_post_meta($pid, 'as_driver', true);
            $as_driver ? update_user_meta($prev_as_driver, 'for_bus', $pid) : update_user_meta($prev_as_driver, 'for_bus', null);
            update_post_meta($pid, 'as_driver', $as_driver);

            update_post_meta($pid, 'wbtm_general_same_bus_return', $wbtm_general_same_bus_return);
            update_post_meta($pid, 'show_boarding_time', $show_boarding_time);
            update_post_meta($pid, 'show_dropping_time', $show_dropping_time);
            update_post_meta($pid, 'show_upper_desk', $show_upper_desk);
            update_post_meta($pid, 'show_pickup_point', $show_pickup_point);
            update_post_meta($pid, 'show_extra_service', $show_extra_service);

            //offday onday
            $offday_schedule_array = array();
            $offday_date_from = $_POST['wbtm_od_offdate_from'];
            $offday_date_to = $_POST['wbtm_od_offdate_to'];
            if (is_array($offday_date_from) && !empty($offday_date_from)) {
                $i = 0;
                for ($i = 0; $i < count($offday_date_from); $i++) {
                    if ($offday_date_from[$i] != '') {
                        $offday_schedule_array[$i]['from_date'] = $offday_date_from[$i];
                        $offday_schedule_array[$i]['to_date'] = $offday_date_to[$i];

                    }
                }
            }

            $od = isset($_POST['weekly_offday']) ? $_POST['weekly_offday'] : '';


            $ondates = $_POST['wbtm_bus_on_dates'];
            $show_off_day = isset($_POST['show_off_day']) ? $_POST['show_off_day'] : 'no';
            $show_operational_on_day = isset($_POST['show_operational_on_day'])?$_POST['show_operational_on_day']:'';

            update_post_meta($pid, 'wbtm_bus_on_dates', $ondates);
            update_post_meta($pid, 'wbtm_offday_schedule', $offday_schedule_array);
            update_post_meta($pid, 'weekly_offday', $od);
            update_post_meta($pid, 'show_operational_on_day', $show_operational_on_day);
            update_post_meta($pid, 'show_off_day', $show_off_day);

            //offday onday return
            $ondates_return = isset($_POST['wbtm_bus_on_dates_return']) ? $_POST['wbtm_bus_on_dates_return'] : '';
            $offday_schedule_return_array = array();
            $offday_date_from_return = $_POST['wbtm_od_offdate_from_return'];
            $offday_date_to_return = $_POST['wbtm_od_offdate_to_return'];

            if (is_array($offday_date_from_return) && !empty($offday_date_from_return)) {
                $i = 0;
                for ($i = 0; $i < count($offday_date_from_return); $i++) {
                    if ($offday_date_from_return[$i] != '') {
                        $offday_schedule_return_array[$i]['from_date'] = $offday_date_from_return[$i];
                        $offday_schedule_return_array[$i]['to_date'] = $offday_date_to_return[$i];
                    }
                }
            }
            $od_return = isset($_POST['weekly_offday_return']) ? $_POST['weekly_offday_return'] : '';
            $return_show_off_day = isset($_POST['return_show_off_day']) ? $_POST['return_show_off_day'] : 'no';
            $return_show_operational_on_day = ($ondates_return)?'yes':'no';

            update_post_meta($pid, 'wbtm_bus_on_dates_return', $ondates_return);
            update_post_meta($pid, 'wbtm_offday_schedule_return', $offday_schedule_return_array);
            update_post_meta($pid, 'weekly_offday_return', $od_return);
            update_post_meta($pid, 'return_show_operational_on_day', $return_show_operational_on_day);
            update_post_meta($pid, 'return_show_off_day', $return_show_off_day);
            //end offday onday


            update_post_meta($pid, 'wbtm_bus_prices', $seat_prices);
            update_post_meta($pid, 'zero_price_allow', $zero_price_allow);


            update_post_meta($pid, '_price', 0);
            $driver_seat_position = strip_tags($_POST['driver_seat_position']);
            update_post_meta($pid, 'driver_seat_position', $driver_seat_position);
            update_post_meta($pid, '_sold_individually', 'yes');
        }
    }








} // Class End

new WBTMMetaBox();
