<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('WBTM_Settings')) {
    class WBTM_Settings {
        public function __construct() {
            add_action('add_meta_boxes', [$this, 'settings_meta']);
            add_action('save_post', array($this, 'save_settings'), 99, 1);
            add_action('wbtm_settings_tab', array($this, 'settings_tab'), 99);
        }
        //************************//
        public function settings_meta() {
            $label = WBTM_Functions::get_name();
            $cpt = WBTM_Functions::get_cpt();
            add_meta_box('mp_meta_box_panel', $label . esc_html__(' Information Settings : ', 'bus-ticket-booking-with-seat-reservation') . get_the_title(get_the_id()), array($this, 'settings'), $cpt, 'normal', 'high');
        }
        //******************************//
        public function settings() {
            $post_id = get_the_id();
            wp_nonce_field('wbtm_type_nonce', 'wbtm_type_nonce');
            $this->settings_tab($post_id);
            
        }
        public function settings_tab($post_id){
?>
            <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
            <div class="mpStyle">
                <div class="mpTabs leftTabs">
                    <ul class="tabLists">
                        <li data-tabs-target="#wbtm_general_info">
                            <span class="fas fa-tools"></span><?php esc_html_e('General Info', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <li data-tabs-target="#wbtm_settings_seat">
                            <span class="fas fa-chair"></span><?php esc_html_e('Seat Configure', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <li data-tabs-target="#wbtm_settings_pricing_routing">
                            <span class="fas fa-file-invoice-dollar"></span><?php esc_html_e('Pricing & Route', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <li data-tabs-target="#wbtm_settings_ex_service">
                            <span class="fas fa-list"></span><?php echo WBTM_Translations::text_ex_service(); ?>
                        </li>
                        <li data-tabs-target="#wbtm_settings_pickup_point">
                            <span class="fas fa-route"></span><?php esc_html_e('Pickup/Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <?php do_action('add_wbtm_add_setting_menu', $post_id); ?>
                        <li data-tabs-target="#wbtm_settings_date">
                            <span class="fas fa-calendar-alt"></span><?php esc_html_e('Date Settings', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <li data-tabs-target="#wbtm_settings_tax">
                            <span class="fas fa-hand-holding-usd"></span><?php esc_html_e('Tax Configure', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </li>
                        <?php if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) { ?>
                            <li data-tabs-target="#mp_pp_deposits_type">
                                <span class=""></span>&nbsp;&nbsp;<?php esc_html_e('Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </li>
                        <?php } ?>
                    </ul>
                    <div class="tabsContent tab-content">
                        <?php do_action('add_wbtm_settings_tab_content', $post_id); ?>
                        <?php if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) { ?>
                            <div class="tabsItem" data-tabs="#mp_pp_deposits_type">
                                <h5><?php esc_html_e('Partial Payment Settings : ', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
                                <div class="divider"></div>
                                <?php do_action('wcpp_partial_product_settings', get_post_custom($post_id)); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php
        }
        public static function description_array($key) {
            $des = array(
                'wbtm_bus_no' => esc_html__('Please add your unique bus id', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_bus_category' => esc_html__('Please add your bus category', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_reservation' => esc_html__('Turn on or off, bus seat registration', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_reservation_tips' => esc_html__('By default Registration is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
                'show_boarding_time' => esc_html__('By default Boarding Time is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
                'show_dropping_time' => esc_html__('By default Dropping Time is ON but you can keep it off by switching this option', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_seat_type_conf' => esc_html__('Please select your bus seat type . Default Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_get_total_seat' => esc_html__('Please Type your bus total seat.', 'bus-ticket-booking-with-seat-reservation'),
                'show_operational_on_day' => esc_html__('Select Particular Date or Repeated Date.', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_routing_info' => esc_html__('Here you can set bus route for stopage and dropping', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_pricing_info' => esc_html__('Please configure bus route price. Before price setting must be complete route configuration .', 'bus-ticket-booking-with-seat-reservation'),
                'show_extra_service' => esc_html__('Turn On or Off Extra service.', 'bus-ticket-booking-with-seat-reservation'),
                'show_pickup_point' => esc_html__('Turn On or Off pickup point.', 'bus-ticket-booking-with-seat-reservation'),
                'show_drop_off_point' => esc_html__('Turn On or Off drop-off point.', 'bus-ticket-booking-with-seat-reservation'),
                'tax_class' => esc_html__('To add any new tax class , Please go to WooCommerce ->Settings->Tax Area', 'bus-ticket-booking-with-seat-reservation'),
                //================//
                'mp_slider_images' => esc_html__('Please upload images for gallery', 'bus-ticket-booking-with-seat-reservation'),
                //''          => esc_html__( '', 'bus-ticket-booking-with-seat-reservation' ),
            );
            $des = apply_filters('wbtm_filter_description_array', $des);
            return $des[$key];
        }
        public static function info_text($key) {
            $data = self::description_array($key);
            if ($data) {
                ?>
                <?php echo esc_html($data); ?>
                <?php
            }
        }
        public function save_settings($post_id) {
            if (!isset($_POST['wbtm_type_nonce']) || !wp_verify_nonce($_POST['wbtm_type_nonce'], 'wbtm_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
                return;
            }
            do_action('wbtm_settings_save', $post_id);
            do_action('wcpp_partial_settings_saved', $post_id);
        }
    }
    new WBTM_Settings();
}