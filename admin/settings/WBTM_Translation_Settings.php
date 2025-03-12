<?php
if (!defined('ABSPATH')) {
    die;
}

class WBTM_Translation_Settings {
    private $option_name = 'wbtm_translations';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_translation_menu'), 25);
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_translation_menu() {
        add_submenu_page(
            'edit.php?post_type=wbtm_bus',
            __('Translation Settings', 'bus-ticket-booking-with-seat-reservation'),
            __('Translations', 'bus-ticket-booking-with-seat-reservation'),
            'manage_options',
            'wbtm-translations',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('wbtm_translation_settings', $this->option_name);
    }

    public function settings_page() {
        $translations = get_option($this->option_name, array());
        $sections = array(
            'general-translations' => array(
                'label' => __('General Labels', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-language',
                'items' => array('journey_date', 'return_date', 'date', 'from', 'to', 'search', 'note', 'terms_conditions')
            ),
            'booking-translations' => array(
                'label' => __('Booking Labels', 'bus-ticket-booking-with-seat-reservation'), 
                'icon' => 'fas fa-ticket-alt',
                'items' => array('booking_date', 'booking_id', 'booking_status', 'payment_status', 'transaction_id', 'booking_success', 'booking_failed')
            ),
            'passenger-translations' => array(
                'label' => __('Passenger Information', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-users',
                'items' => array('passenger_details', 'mobile_number', 'email', 'gender', 'passenger_info', 'adult', 'child', 'infant')
            ),
            'bus-translations' => array(
                'label' => __('Bus Information', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-bus',
                'items' => array('departure_time', 'arrival_time', 'route_details', 'bus_type', 'coach_type', 'seat_type', 'schedule')
            ),
            'seat-translations' => array(
                'label' => __('Seat Information', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-chair',
                'items' => array('seat_details', 'seat_selection', 'seat_unavailable', 'text_available_seat', 'text_already_sold')
            ),
            'button-translations' => array(
                'label' => __('Button Labels', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-toggle-on',
                'items' => array('proceed', 'cancel', 'print_ticket', 'book_now', 'view_seat')
            ),
            'error-messages' => array(
                'label' => __('Error Messages', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-exclamation-triangle',
                'items' => array('error_required_field', 'error_invalid_email', 'error_invalid_phone')
            ),
            'ticket-status' => array(
                'label' => __('Ticket Status', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-info-circle',
                'items' => array('ticket_confirmed', 'ticket_pending', 'ticket_cancelled')
            ),
            'passenger-form' => array(
                'label' => __('Passenger Form', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-user-edit',
                'items' => array('passenger_name', 'age', 'nid', 'mobile_number', 'email', 'gender')
            ),
            'journey-info' => array(
                'label' => __('Journey Information', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-route',
                'items' => array('boarding_point', 'dropping_point', 'journey_time', 'distance')
            ),
            'price-info' => array(
                'label' => __('Price Information', 'bus-ticket-booking-with-seat-reservation'),
                'icon' => 'fas fa-money-bill',
                'items' => array('tax', 'discount', 'grand_total', 'payment_pending', 'payment_complete', 'payment_failed')
            )
        );
        ?>
        <style>
            .wbtm-translation-wrapper {
                margin: 20px;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .wbtm-translation-header {
                border-bottom: 2px solid #2271b1;
                padding-bottom: 15px;
                margin-bottom: 30px;
            }
            .wbtm-translation-header h1 {
                color: #2271b1;
                margin: 0;
                font-size: 24px;
            }
            .wbtm-tabs {
                display: flex;
                margin-bottom: 20px;
            }
            .wbtm-tab {
                padding: 10px 20px;
                background: #f0f0f1;
                border: none;
                margin-right: 5px;
                cursor: pointer;
                border-radius: 4px 4px 0 0;
            }
            .wbtm-tab.active {
                background: #2271b1;
                color: #fff;
            }
            .wbtm-tab-content {
                display: none;
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 0 4px 4px 4px;
            }
            .wbtm-tab-content.active {
                display: block;
            }
            .wbtm-field-row {
                display: flex;
                margin-bottom: 15px;
                align-items: center;
                padding: 10px;
                border-bottom: 1px solid #f0f0f1;
            }
            .wbtm-field-row:hover {
                background: #f8f9fa;
            }
            .wbtm-field-label {
                flex: 0 0 30%;
                font-weight: 500;
            }
            .wbtm-field-input {
                flex: 0 0 70%;
            }
            .wbtm-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .wbtm-input:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            .wbtm-description {
                color: #666;
                font-size: 12px;
                margin-top: 5px;
            }
            .wbtm-submit {
                margin-top: 20px;
                text-align: right;
            }
            .wbtm-submit .button-primary {
                background: #2271b1;
                border-color: #2271b1;
                padding: 5px 20px;
                height: auto;
                text-shadow: none;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                $('.wbtm-tab').click(function() {
                    $('.wbtm-tab').removeClass('active');
                    $('.wbtm-tab-content').removeClass('active');
                    $(this).addClass('active');
                    $($(this).data('target')).addClass('active');
                });
                // Activate first tab
                $('.wbtm-tab:first').click();
            });
        </script>

        <div class="wbtm-translation-wrapper">
            <div class="wbtm-translation-header">
                <h1><?php _e('Bus Ticket Booking Translations', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wbtm_translation_settings'); ?>
                
                <div class="wbtm-tabs">
                    <?php foreach ($sections as $section_id => $section) : ?>
                        <button type="button" class="wbtm-tab" data-target="#<?php echo esc_attr($section_id); ?>">
                            <i class="<?php echo esc_attr($section['icon']); ?>"></i>
                            <?php echo esc_html($section['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($sections as $section_id => $section) : ?>
                    <div id="<?php echo esc_attr($section_id); ?>" class="wbtm-tab-content">
                        <?php foreach ($section['items'] as $field) :
                            $method = 'text_' . $field;
                            if (method_exists('WBTM_Translations', $method)) :
                                $default_value = call_user_func(array('WBTM_Translations', $method));
                                $value = isset($translations[$method]) ? $translations[$method] : $default_value;
                                $label = ucwords(str_replace('_', ' ', $field));
                        ?>
                        <div class="wbtm-field-row">
                            <div class="wbtm-field-label">
                                <?php echo esc_html($label); ?>
                            </div>
                            <div class="wbtm-field-input">
                                <input type="text" 
                                    name="<?php echo esc_attr($this->option_name . '[' . $method . ']'); ?>"
                                    value="<?php echo esc_attr($value); ?>"
                                    class="wbtm-input"
                                />
                                <div class="wbtm-description">
                                    <?php echo sprintf(__('Default: %s', 'bus-ticket-booking-with-seat-reservation'), $default_value); ?>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                <?php endforeach; ?>

                <div class="wbtm-submit">
                    <?php submit_button(__('Save Changes', 'bus-ticket-booking-with-seat-reservation')); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

new WBTM_Translation_Settings();
