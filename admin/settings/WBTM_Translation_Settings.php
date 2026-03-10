<?php
if (!defined('ABSPATH')) {
    die;
}

class WBTM_Translation_Settings {
    private $option_name = 'wbtm_translations';
    private $save_action = 'wbtm_save_translations';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_translation_menu'), 25);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_' . $this->save_action, array($this, 'save_translations'));
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
        register_setting('wbtm_translation_settings', $this->option_name, array(
	        'type'              => 'array',
	        'sanitize_callback' => array($this, 'sanitize_translations'),
        ));
    }
    
    public function sanitize_translations($input) {
        if (!is_array($input)) {
            return array();
        }
        $sanitized = array();
        foreach ($input as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
        }
        return $sanitized;
    }

    public function save_translations() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage translations.', 'bus-ticket-booking-with-seat-reservation'));
        }

        check_admin_referer($this->save_action, 'wbtm_translation_nonce');

        $translations = isset($_POST[$this->option_name]) ? wp_unslash($_POST[$this->option_name]) : array();
        $sanitized_translations = $this->sanitize_translations($translations);

        update_option($this->option_name, $sanitized_translations);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post_type'                 => 'wbtm_bus',
                    'page'                      => 'wbtm-translations',
                    'wbtm_translations_updated' => '1',
                ),
                admin_url('edit.php')
            )
        );
        exit;
    }

    private function get_default_translation_value($method) {
        $disable_saved_translations = static function ($pre_option, $option = '', $default_value = false) {
            return array();
        };

        add_filter('pre_option_' . $this->option_name, $disable_saved_translations, 10, 3);
        $default_value = call_user_func(array('WBTM_Translations', $method));
        remove_filter('pre_option_' . $this->option_name, $disable_saved_translations);

        return $default_value;
    }

    private function get_all_translation_methods() {
        $methods = get_class_methods('WBTM_Translations');
        $text_methods = array();
        foreach ($methods as $method) {
            if (strpos($method, 'text_') === 0 || strpos($method, 'duration_') === 0) {
                $text_methods[] = $method;
            }
        }
        sort($text_methods);
        return $text_methods;
    }
    
    private function categorize_methods($methods) {
        $categories = array(
            'general' => array('label' => 'General Labels', 'icon' => 'fas fa-language', 'keywords' => array('journey', 'date', 'from', 'to', 'search', 'please', 'note', 'terms')),
            'booking' => array('label' => 'Booking & Orders', 'icon' => 'fas fa-ticket-alt', 'keywords' => array('booking', 'order', 'payment', 'transaction', 'billing')),
            'passenger' => array('label' => 'Passenger Information', 'icon' => 'fas fa-users', 'keywords' => array('passenger', 'mobile', 'email', 'gender', 'adult', 'child', 'infant', 'nid', 'age')),
            'bus' => array('label' => 'Bus & Route', 'icon' => 'fas fa-bus', 'keywords' => array('bus', 'route', 'departure', 'arrival', 'schedule', 'duration', 'operator')),
            'seat' => array('label' => 'Seat & Coach', 'icon' => 'fas fa-chair', 'keywords' => array('seat', 'coach', 'available', 'sold', 'cart', 'upper', 'deck', 'plan')),
            'location' => array('label' => 'Boarding & Dropping', 'icon' => 'fas fa-map-marker-alt', 'keywords' => array('bp', 'dp', 'boarding', 'dropping', 'pickup', 'drop_off', 'start_point', 'pin')),
            'pricing' => array('label' => 'Price & Payment', 'icon' => 'fas fa-money-bill', 'keywords' => array('price', 'fare', 'total', 'qty', 'tax', 'discount', 'sub_total')),
            'actions' => array('label' => 'Buttons & Actions', 'icon' => 'fas fa-toggle-on', 'keywords' => array('book', 'view', 'close', 'proceed', 'cancel', 'print', 'buy', 'action')),
            'messages' => array('label' => 'Messages & Status', 'icon' => 'fas fa-info-circle', 'keywords' => array('no_', 'error', 'success', 'failed', 'pending', 'confirmed', 'cancelled', 'status', 'msg', 'wrong', 'security')),
            'misc' => array('label' => 'Miscellaneous', 'icon' => 'fas fa-ellipsis-h', 'keywords' => array())
        );
        
        $categorized = array();
        foreach ($categories as $cat_key => $cat_data) {
            $categorized[$cat_key] = array(
                'label' => $cat_data['label'],
                'icon' => $cat_data['icon'],
                'methods' => array()
            );
        }
        
        foreach ($methods as $method) {
            $placed = false;
            foreach ($categories as $cat_key => $cat_data) {
                if ($cat_key === 'misc') continue;
                foreach ($cat_data['keywords'] as $keyword) {
                    if (stripos($method, $keyword) !== false) {
                        $categorized[$cat_key]['methods'][] = $method;
                        $placed = true;
                        break 2;
                    }
                }
            }
            if (!$placed) {
                $categorized['misc']['methods'][] = $method;
            }
        }
        
        return $categorized;
    }

    public function settings_page() {
        $translations = get_option($this->option_name, array());
        $all_methods = $this->get_all_translation_methods();
        $sections = $this->categorize_methods($all_methods);
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
            .wbtm-method-name {
                font-size: 11px;
                color: #999;
                font-family: monospace;
                margin-top: 3px;
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
                <h1><?php esc_html_e('Bus Ticket Booking Translations', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
            </div>

            <?php if (isset($_GET['wbtm_translations_updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Translations updated successfully.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr($this->save_action); ?>" />
                <?php wp_nonce_field($this->save_action, 'wbtm_translation_nonce'); ?>
                
                <div class="wbtm-tabs">
                    <?php foreach ($sections as $section_id => $section) : ?>
                        <button type="button" class="wbtm-tab" data-target="#<?php echo esc_attr($section_id); ?>">
                            <i class="<?php echo esc_attr($section['icon']); ?>"></i>
                            <?php echo esc_html($section['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($sections as $section_id => $section) : ?>
                    <?php if (empty($section['methods'])) continue; ?>
                    <div id="<?php echo esc_attr($section_id); ?>" class="wbtm-tab-content">
                        <?php foreach ($section['methods'] as $method) :
                            if (method_exists('WBTM_Translations', $method)) :
                                $default_value = $this->get_default_translation_value($method);
                                $current_value = isset($translations[$method]) ? $translations[$method] : '';
                                $display_value = $current_value !== '' ? $current_value : $default_value;
                                $label = ucwords(str_replace('_', ' ', str_replace('text_', '', $method)));
                        ?>
                        <div class="wbtm-field-row">
                            <div class="wbtm-field-label">
                                <strong><?php echo esc_html($label); ?></strong>
                                <div class="wbtm-method-name"><?php echo esc_html($method); ?>()</div>
                            </div>
                            <div class="wbtm-field-input">
                                <input type="text" 
                                    name="<?php echo esc_attr($this->option_name . '[' . $method . ']'); ?>"
                                    value="<?php echo esc_attr($display_value); ?>"
                                    class="wbtm-input"
                                    placeholder="<?php echo esc_attr($default_value); ?>"
                                />
                                <div class="wbtm-description">
                                    Default: <code><?php echo esc_html($default_value); ?></code>
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
