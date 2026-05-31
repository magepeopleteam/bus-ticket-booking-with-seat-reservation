<?php
/*
 * @Author      engr.sumonazma@gmail.com
 * Copyright:   mage-people.com
 * 
 * Modern Multi-Step Modal for Bus Add/Edit
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('WBTM_Bus_Modal')) {
    class WBTM_Bus_Modal {
        private static $instance = null;
        private static $modal_printed = false;
        
        public static function get_instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('add_meta_boxes', [$this, 'register_modal_metabox'], 999);
            add_action('admin_head', [$this, 'hide_default_editor']);
            // Ensure modal HTML is available for block editor screens
            add_action('admin_footer', [$this, 'render_modal_in_footer']);
            add_action('save_post', [$this, 'save_bus_data'], 10, 2);
            add_action('wp_ajax_wbtm_get_bus_data', [$this, 'ajax_get_bus_data']);
            add_action('wp_ajax_wbtm_save_bus_modal', [$this, 'ajax_save_bus']);
        }

        public function render_modal_in_footer() {
            // Avoid duplicating output if meta box already printed the modal
            if (self::$modal_printed) {
                return;
            }

            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || !isset($screen->post_type) || $screen->post_type !== 'wbtm_bus') {
                return;
            }

            // Only render in footer when block editor is active to avoid duplicate output
            $post_id = 0;
            if (isset($_GET['post'])) {
                $post_id = absint($_GET['post']);
            } elseif (isset($_GET['post_ID'])) {
                $post_id = absint($_GET['post_ID']);
            }

            $post = $post_id ? get_post($post_id) : null;

            if (function_exists('use_block_editor_for_post')) {
                if ($post && use_block_editor_for_post($post)) {
                    // Render the same modal markup used in the meta box
                    $this->render_modal_metabox($post ?: (object) ['ID' => 0]);
                }
            }
        }
        
        public function hide_default_editor() {
            // Use the WP Screen API for robust detection
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || !isset($screen->post_type)) {
                return;
            }

            if ($screen->post_type !== 'wbtm_bus') {
                return;
            }

            echo '<style>
                #post-body-content { display: none !important; }
                #wbtm_meta_box_panel { display: none !important; }
                .wbtm_style { display: none !important; }
                #wbtm_bus_modal_box { background: transparent; border: none; box-shadow: none; }
                #wbtm_bus_modal_box .inside { padding: 0; margin: 0; }
                #wbtm_bus_modal_box .postbox-header { display: none; }
                #wbtm_bus_modal_box .handlediv { display: none; }
                .wbtm-modal-wrapper { display: block !important; opacity: 1 !important; visibility: visible !important; }
                .wbtm-modal-wrapper.open { display: block !important; }
            </style>';
        }
        
        public function enqueue_assets($hook) {
            // Use current screen API for reliable detection
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || !isset($screen->post_type)) {
                return;
            }

            // Only load on post edit/new screens for our CPT
            if ($screen->post_type !== 'wbtm_bus') {
                return;
            }

            if ($hook !== 'post.php' && $hook !== 'post-new.php' && (!isset($screen->base) || $screen->base !== 'post')) {
                return;
            }

            wp_enqueue_style(
                'wbtm-bus-modal-css',
                WBTM_PLUGIN_URL . '/admin/wbtm_bus_modal.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'wbtm-bus-modal-js',
                WBTM_PLUGIN_URL . '/admin/wbtm_bus_modal.js',
                ['jquery'],
                '1.0.0',
                true
            );

            // Determine post ID more reliably in admin
            $post_id = 0;
            if (isset($_GET['post'])) {
                $post_id = absint($_GET['post']);
            } elseif (isset($_GET['post_ID'])) {
                $post_id = absint($_GET['post_ID']);
            }

            wp_localize_script('wbtm-bus-modal-js', 'wbtmModal', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wbtm_modal_nonce'),
                'postId' => $post_id,
                'isNew' => $post_id === 0,
                'i18n' => [
                    'add' => __('Add New Bus', 'bus-ticket-booking-with-seat-reservation'),
                    'edit' => __('Edit Bus', 'bus-ticket-booking-with-seat-reservation'),
                    'saving' => __('Saving...', 'bus-ticket-booking-with-seat-reservation'),
                    'saved' => __('Bus Saved Successfully!', 'bus-ticket-booking-with-seat-reservation'),
                    'error' => __('Error saving bus. Please try again.', 'bus-ticket-booking-with-seat-reservation'),
                ],
            ]);
        }
        
        public function register_modal_metabox() {
            $label = WBTM_Functions::get_name();
            add_meta_box(
                'wbtm_bus_modal_box',
                $label . ' ' . __('Manager', 'bus-ticket-booking-with-seat-reservation'),
                [$this, 'render_modal_metabox'],
                'wbtm_bus',
                'normal',
                'high'
            );
        }
        
        public function render_modal_metabox($post) {
            // mark that modal HTML has been printed (prevents footer duplication)
            self::$modal_printed = true;
            $post_id = $post->ID;
            wp_nonce_field('wbtm_modal_nonce', 'wbtm_modal_nonce');
            
            // Load existing data for edit
            $is_edit = $post_id > 0;
            $bus_name = $is_edit ? get_the_title($post_id) : '';
            $bus_no = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_no');
            $bus_category = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category');
            $bus_logo = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_logo');
            $reservation_on = WBTM_Global_Function::get_post_info($post_id, 'wbtm_registration', 'yes');
            $seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
            $total_seat = WBTM_Global_Function::get_post_info($post_id, 'wbtm_get_total_seat');
            
            $bus_categories = WBTM_Global_Function::get_all_term_data('wbtm_bus_cat');
            $bus_stops = WBTM_Global_Function::get_all_term_data('wbtm_bus_stops');
            
            // Route info
            $route_info = WBTM_Global_Function::get_post_info($post_id, 'wbtm_route_info', []);
            $bus_prices = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_prices', []);
            $same_bus_return = WBTM_Global_Function::get_post_info($post_id, 'wbtm_same_bus_return_enabled', 'no');
            
            // Date settings
            $date_type = WBTM_Global_Function::get_post_info($post_id, 'show_operational_on_day', 'no');
            $repeated_start = WBTM_Global_Function::get_post_info($post_id, 'wbtm_repeated_start_date');
            $repeated_end = WBTM_Global_Function::get_post_info($post_id, 'wbtm_repeated_end_date');
            $repeated_after = WBTM_Global_Function::get_post_info($post_id, 'wbtm_repeated_after', 1);
            $off_days = WBTM_Global_Function::get_post_info($post_id, 'wbtm_off_days');
            $particular_dates = WBTM_Global_Function::get_post_info($post_id, 'wbtm_particular_dates', []);
            
            // Extra services
            $extra_services = WBTM_Global_Function::get_post_info($post_id, 'wbtm_extra_services', []);
            $show_extra_service = WBTM_Global_Function::get_post_info($post_id, 'show_extra_service');
            
            // Pickup points
            $pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
            $show_pickup = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
            $show_boarding_time = WBTM_Global_Function::get_post_info($post_id, 'show_boarding_time', 'yes');
            $show_dropping_time = WBTM_Global_Function::get_post_info($post_id, 'show_dropping_time', 'yes');
            
            // Seat configuration
            $seat_rows = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
            $seat_cols = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 4);
            
            // Gallery
            $gallery_images = get_post_meta($post_id, 'mp_slider_images', true);
            $feature_image_id = get_post_thumbnail_id($post_id);
            
            // Get ticket types
            $ticket_types = WBTM_Functions::get_ticket_types($post_id);
            
            $modal_title = $is_edit ? sprintf(__('Edit %s: %s', 'bus-ticket-booking-with-seat-reservation'), WBTM_Functions::get_name(), $bus_name) : sprintf(__('Add New %s', 'bus-ticket-booking-with-seat-reservation'), WBTM_Functions::get_name());
            ?>
            
            <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
            <input type="hidden" name="wbtm_modal_mode" value="<?php echo $is_edit ? 'edit' : 'add'; ?>"/>
            
            <!-- Hidden modal container - will be shown via JavaScript -->
            <div class="wbtm-modal-wrapper" id="wbtmModalWrapper" style="display:none;">
                <div class="wbtm-modal-overlay" id="wbtmModalOverlay"></div>
                <div class="wbtm-modal-container">
                    <div class="wbtm-modal">
                        <div class="wbtm-modal-header">
                            <div class="wbtm-modal-header-inner">
                                <div class="wbtm-modal-title-block">
                                    <div class="wbtm-modal-badge">
                                        <svg class="wbtm-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="1" y="3" width="15" height="13" rx="2"/>
                                            <path d="M16 8h4l3 3v5h-7V8z"/>
                                            <circle cx="5.5" cy="18.5" r="2.5"/>
                                            <circle cx="18.5" cy="18.5" r="2.5"/>
                                        </svg>
                                        Mage People — Bus Manager
                                    </div>
                                    <h2 class="wbtm-modal-title" id="wbtmModalTitle"><?php echo esc_html($modal_title); ?></h2>
                                    <p class="wbtm-modal-subtitle">Complete all steps to configure your bus service</p>
                                </div>
                                <button type="button" class="wbtm-modal-close" id="wbtmModalClose" aria-label="Close modal">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 6L6 18M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Step tabs -->
                            <div class="wbtm-steps-nav" id="wbtmStepsNav">
                                <button type="button" class="wbtm-step-tab active" data-step="0">
                                    <span class="wbtm-step-num">1</span>
                                    <svg class="wbtm-step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span class="wbtm-step-label">General</span>
                                </button>
                                <button type="button" class="wbtm-step-tab" data-step="1">
                                    <span class="wbtm-step-num">2</span>
                                    <svg class="wbtm-step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span class="wbtm-step-label">All Settings</span>
                                </button>
                                <button type="button" class="wbtm-step-tab" data-step="2">
                                    <span class="wbtm-step-num">3</span>
                                    <svg class="wbtm-step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                                    <span class="wbtm-step-label">Review</span>
                                </button>
                            </div>
                            
                            <div class="wbtm-progress-bar">
                                <div class="wbtm-progress-fill" id="wbtmProgressFill" style="width: 33.33%"></div>
                            </div>
                        </div>
                        
                        <div class="wbtm-modal-body" id="wbtmModalBody">
                            <!-- Step 0: General Info -->
                            <div class="wbtm-step-panel active" data-step="0" id="wbtmStep0">
                                <?php $this->render_general_step($post_id, $bus_name, $bus_no, $bus_category, $bus_logo, $reservation_on, $bus_categories); ?>
                            </div>
                            
                            <!-- Step 1: All Settings (Original Full Settings Tabs) -->
                            <div class="wbtm-step-panel" data-step="1" id="wbtmStep1">
                                <div class="wbtm-original-settings">
                                    <?php 
                                    // Render the original settings tabs with ALL functionality
                                    wp_nonce_field('wbtm_type_nonce', 'wbtm_type_nonce');
                                    do_action('wbtm_add_settings_tab_content', $post_id); 
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Step 2: Review -->
                            <div class="wbtm-step-panel" data-step="2" id="wbtmStep2">
                                <?php $this->render_review_step($post_id); ?>
                            </div>
                            
                            <!-- Success Panel -->
                            <div class="wbtm-success-panel" id="wbtmSuccessPanel" style="display: none;">
                                <div class="wbtm-success-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                </div>
                                <h3 class="wbtm-success-title"><?php _e('Bus Saved Successfully!', 'bus-ticket-booking-with-seat-reservation'); ?> 🎉</h3>
                                <p class="wbtm-success-desc"><?php _e('Your bus configuration has been saved. You can now publish it.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                            </div>
                        </div>
                        
                        <div class="wbtm-modal-footer">
                            <div class="wbtm-footer-left">
                                <span class="wbtm-step-indicator">Step <span id="wbtmCurrentStep">1</span> of 9</span>
                                <div class="wbtm-step-dots" id="wbtmStepDots"></div>
                            </div>
                            <div class="wbtm-footer-right">
                                <button type="button" class="wbtm-btn wbtm-btn-secondary" id="wbtmPrevBtn" style="display: none;">
                                    <?php _e('Back', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </button>
                                <button type="button" class="wbtm-btn wbtm-btn-primary" id="wbtmNextBtn">
                                    <?php _e('Continue', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                                        <path d="M9 18l6-6-6-6"/>
                                    </svg>
                                </button>
                                <button type="button" class="wbtm-btn wbtm-btn-success" id="wbtmSaveBtn" style="display: none;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                    <?php _e('Save Bus', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Trigger button -->
            <div class="wbtm-modal-trigger" id="wbtmModalTrigger">
                <button type="button" class="wbtm-open-modal-btn" id="wbtmOpenModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <?php echo $is_edit ? __('Edit Bus Configuration', 'bus-ticket-booking-with-seat-reservation') : __('Configure New Bus', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
            </div>
            
            <!-- Hidden publish controls - synced from modal -->
            <div id="wbtmHiddenControls" style="display:none;">
                <input type="hidden" name="wbtm_form_submitted" value="1"/>
                <input type="hidden" name="wbtm_bus_no_hidden" id="wbtmBusNoHidden"/>
                <input type="hidden" name="wbtm_bus_category_hidden" id="wbtmBusCategoryHidden"/>
                <input type="hidden" name="wbtm_reservation_hidden" id="wbtmReservationHidden"/>
            </div>
            <?php
        }
        
        private function render_general_step($post_id, $bus_name, $bus_no, $bus_category, $bus_logo, $reservation_on, $bus_categories) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Bus Information', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-form-grid wbtm-cols-2">
                    <div class="wbtm-field wbtm-col-full">
                        <label class="wbtm-field-label"><?php _e('Bus Name', 'bus-ticket-booking-with-seat-reservation'); ?> <span class="wbtm-req">*</span></label>
                        <input type="text" class="wbtm-form-control" name="wbtm_bus_name" id="wbtmBusName" value="<?php echo esc_attr($bus_name); ?>" placeholder="<?php esc_attr_e('e.g. Badger Bus Service', 'bus-ticket-booking-with-seat-reservation'); ?>">
                    </div>
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Bus Number / ID', 'bus-ticket-booking-with-seat-reservation'); ?> <span class="wbtm-req">*</span></label>
                        <input type="text" class="wbtm-form-control" name="wbtm_bus_no_modal" id="wbtmBusNo" value="<?php echo esc_attr($bus_no); ?>" placeholder="<?php esc_attr_e('e.g. badger-01', 'bus-ticket-booking-with-seat-reservation'); ?>">
                        <p class="wbtm-field-hint"><?php _e('Unique identifier for this bus', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    </div>
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php echo esc_html(WBTM_Translations::text_coach_type()); ?> <span class="wbtm-req">*</span></label>
                        <select class="wbtm-form-control" name="wbtm_bus_category_modal" id="wbtmCoachType">
                            <option value=""><?php echo esc_html(WBTM_Translations::text_please_select()); ?></option>
                            <?php foreach ($bus_categories as $category) { ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($bus_category, $category); ?>><?php echo esc_html($category); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Logo & Branding', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-upload-zone" id="wbtmLogoUpload">
                    <div class="wbtm-upload-icon">🚌</div>
                    <div class="wbtm-upload-title"><?php _e('Upload Bus Logo', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    <div class="wbtm-upload-hint"><?php _e('PNG or SVG recommended · Max 2MB', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    <input type="hidden" name="wbtm_bus_logo_modal" id="wbtmBusLogo" value="<?php echo esc_attr($bus_logo); ?>">
                </div>
                <div class="wbtm-upload-preview" id="wbtmLogoPreview"></div>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('Reservation On/Off', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Enable or disable seat reservation for this bus', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="wbtm_reservation_modal" id="wbtmReservationToggle" <?php checked($reservation_on, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
            </div>
            <?php
        }
        
        private function render_seat_step($post_id, $seat_type, $seat_cols, $seat_rows, $total_seat) {
            $has_pro_seat = class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
            $seat_types = [
                'wbtm_without_seat_plan' => __('Without Seat Plan', 'bus-ticket-booking-with-seat-reservation'),
                'wbtm_seat_plan' => __('With Seat Plan', 'bus-ticket-booking-with-seat-reservation'),
            ];
            $driver_positions = ['left' => __('Left', 'bus-ticket-booking-with-seat-reservation'), 'right' => __('Right', 'bus-ticket-booking-with-seat-reservation')];
            $driver_pos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_driver_position', 'left');
            $seat_price_override = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_price_override', 'yes');
            $cabin_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_cabin', 'no');
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Vehicle Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    
                <div class="wbtm-form-grid wbtm-cols-3">
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <select class="wbtm-form-control" name="wbtm_seat_type_conf" id="wbtmSeatType" onchange="toggleSeatPlan(this.value);">
                            <?php foreach ($seat_types as $type => $label) { ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($seat_type, $type); ?>><?php echo esc_html($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Total Seats', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <input type="number" class="wbtm-form-control" name="wbtm_get_total_seat" id="wbtmTotalSeat" value="<?php echo esc_attr($total_seat); ?>" min="1" max="1000">
                    </div>
                </div>
                    
                <div id="wbtmSeatPlanConfig" style="<?php echo $seat_type === 'wbtm_seat_plan' ? '' : 'display:none;'; ?>">
                    <div class="wbtm-form-grid wbtm-cols-3">
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Driver Position', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <select class="wbtm-form-control" name="wbtm_bus_driver_position">
                                <?php foreach ($driver_positions as $val => $label) { ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($driver_pos, $val); ?>><?php echo esc_html($label); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Seat Columns', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <input type="number" class="wbtm-form-control" name="wbtm_seat_cols" id="wbtmSeatCols" value="<?php echo esc_attr($seat_cols); ?>" min="2" max="10">
                        </div>
                    </div>
                        
                    <?php if ($has_pro_seat) { ?>
                    <div class="wbtm-toggle-row">
                        <div class="wbtm-toggle-info">
                            <div class="wbtm-toggle-label"><?php _e('Enable Cabin/Coach Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                            <div class="wbtm-toggle-desc"><?php _e('Turn ON to configure multiple cabins/coaches; OFF for single bus', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        </div>
                        <label class="wbtm-toggle">
                            <input type="checkbox" name="wbtm_enable_cabin" value="yes" <?php checked($cabin_enabled, 'yes'); ?>>
                            <span class="wbtm-toggle-track"></span>
                            <span class="wbtm-toggle-thumb"></span>
                        </label>
                    </div>
                    <div class="wbtm-toggle-row">
                        <div class="wbtm-toggle-info">
                            <div class="wbtm-toggle-label"><?php _e('Enable Seat-wise Price Override', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                            <div class="wbtm-toggle-desc"><?php _e('Set a custom fare for individual sellable seats', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        </div>
                        <label class="wbtm-toggle">
                            <input type="checkbox" name="wbtm_enable_seat_price_override" value="yes" <?php checked($seat_price_override, 'yes'); ?>>
                            <span class="wbtm-toggle-track"></span>
                            <span class="wbtm-toggle-thumb"></span>
                        </label>
                    </div>
                    <?php } ?>
                        
                    <div class="wbtm-section">
                        <div class="wbtm-section-title"><?php _e('Seat Plan Preview', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-seat-grid-wrap">
                            <div class="wbtm-seat-legend">
                                <div class="wbtm-legend-item"><span class="wbtm-legend-box available"></span> <?php _e('Available', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                <div class="wbtm-legend-item"><span class="wbtm-legend-box driver"></span> <?php _e('Driver', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                <div class="wbtm-legend-item"><span class="wbtm-legend-box blank"></span> <?php _e('Blank', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                            </div>
                            <div class="wbtm-bus-front-label"><?php _e('🚌 Bus Front', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                            <div id="wbtmSeatGridPreview" class="wbtm-seat-grid"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        
        private function render_pricing_step($post_id, $route_info, $bus_prices, $bus_stops, $ticket_types, $same_bus_return) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Route Stops', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-route-list" id="wbtmRouteList">
                    <?php 
                    if (!empty($route_info)) {
                        foreach ($route_info as $key => $route) {
                            $this->render_route_stop_item($bus_stops, $route, $key);
                        }
                    }
                    ?>
                </div>
                <button type="button" class="wbtm-add-stop-btn" onclick="wbtmAddRouteStop()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <?php _e('Add New Stop', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
                    
                <!-- Hidden template for new route stop -->
                <script type="text/template" id="wbtmRouteStopTemplate">
                    <?php $this->render_route_stop_item($bus_stops, [], '{{INDEX}}'); ?>
                </script>
            </div>
                
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Pricing Matrix', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-pricing-table-wrap">
                    <table class="wbtm-price-table">
                        <thead>
                            <tr>
                                <th><?php _e('Boarding', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <th></th>
                                <th><?php _e('Dropping', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                <?php foreach ($ticket_types as $ticket_type) {
                                    $tt_name = '';
                                    if (is_array($ticket_type) && isset($ticket_type['name'])) {
                                        $tt_name = $ticket_type['name'];
                                    } elseif (is_object($ticket_type) && isset($ticket_type->name)) {
                                        $tt_name = $ticket_type->name;
                                    } elseif (is_string($ticket_type)) {
                                        $tt_name = $ticket_type;
                                    }
                                    ?>
                                    <th><?php echo esc_html($tt_name); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody id="wbtmPricingTableBody">
                            <?php 
                            if (!empty($bus_prices)) {
                                foreach ($bus_prices as $price) {
                                    if (isset($price['wbtm_price_leg']) && $price['wbtm_price_leg'] === 'return') continue;
                                    $this->render_pricing_row($price, $ticket_types);
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Return Journey', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('Enable Same Bus for Return Trips', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Allow this bus to appear in reverse direction search results', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="wbtm_same_bus_return_enabled" value="yes" <?php checked($same_bus_return, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
            </div>
            <?php
        }
        
        private function render_route_stop_item($bus_stops, $route, $index) {
            $place = isset($route['place']) ? $route['place'] : '';
            $time = isset($route['time']) ? $route['time'] : '';
            $type = isset($route['type']) ? $route['type'] : 'bp';
            $types = [
                'bp' => __('Boarding', 'bus-ticket-booking-with-seat-reservation'),
                'dp' => __('Dropping', 'bus-ticket-booking-with-seat-reservation'),
                'both' => __('Both', 'bus-ticket-booking-with-seat-reservation'),
            ];
            ?>
            <div class="wbtm-route-stop" data-index="<?php echo esc_attr($index); ?>">
                <div class="wbtm-route-stop-line">
                    <div class="wbtm-route-dot"></div>
                    <div class="wbtm-route-connector"></div>
                </div>
                <div class="wbtm-route-card">
                    <input type="text" class="wbtm-form-control wbtm-route-place-auto" name="wbtm_route_place[]" value="<?php echo esc_attr($place); ?>" placeholder="<?php esc_attr_e('Stop name', 'bus-ticket-booking-with-seat-reservation'); ?>" list="wbtmBusStopsList">
                    <input type="time" class="wbtm-form-control" name="wbtm_route_time[]" value="<?php echo esc_attr($time); ?>">
                    <select class="wbtm-form-control" name="wbtm_route_type[]">
                        <?php foreach ($types as $val => $label) { ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($type, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php } ?>
                    </select>
                    <div class="wbtm-route-actions">
                        <button type="button" class="wbtm-icon-btn danger" onclick="wbtmRemoveRouteStop(this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php
        }
        
        private function render_pricing_row($price, $ticket_types) {
            $bp = isset($price['wbtm_bus_bp_price_stop']) ? $price['wbtm_bus_bp_price_stop'] : '';
            $dp = isset($price['wbtm_bus_dp_price_stop']) ? $price['wbtm_bus_dp_price_stop'] : '';
            ?>
            <tr class="wbtm-price-row">
                <td><?php echo esc_html($bp); ?></td>
                <td class="wbtm-route-arrow">›</td>
                <td><?php echo esc_html($dp); ?></td>
                <?php foreach ($ticket_types as $ticket_type) {
                    $tt_id = '';
                    if (is_array($ticket_type) && isset($ticket_type['id'])) {
                        $tt_id = $ticket_type['id'];
                    } elseif (is_object($ticket_type) && isset($ticket_type->id)) {
                        $tt_id = $ticket_type->id;
                    }
                    $price_val = ($tt_id !== '' && isset($price['wbtm_ticket_prices'][$tt_id])) ? $price['wbtm_ticket_prices'][$tt_id] : '';
                    ?>
                    <td><input type="number" class="wbtm-price-input" name="wbtm_price_<?php echo esc_attr($bp); ?>_<?php echo esc_attr($dp); ?>_<?php echo esc_attr($tt_id); ?>" value="<?php echo esc_attr($price_val); ?>" placeholder="0"></td>
                <?php } ?>
            </tr>
            <?php
        }
        
        private function render_services_step($post_id, $extra_services, $show_extra_service) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Extra Services', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('Show/Hide Extra Service', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Turn on or off extra services for passengers', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="show_extra_service" value="yes" <?php checked($show_extra_service, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
                    
                <div class="wbtm-service-list" id="wbtmServiceList">
                    <div class="wbtm-service-header">
                        <span><?php _e('Service Name', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        <span><?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        <span><?php _e('Qty', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        <span><?php _e('Qty Type', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        <span></span>
                    </div>
                    <?php 
                    if (!empty($extra_services)) {
                        foreach ($extra_services as $key => $service) {
                            $this->render_service_row($service, $key);
                        }
                    }
                    ?>
                </div>
                <button type="button" class="wbtm-add-stop-btn" onclick="wbtmAddService()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <?php _e('Add Extra Service', 'bus-ticket-booking-with-seat-reservation'); ?>
                </button>
            </div>
            <?php
        }
        
        private function render_service_row($service, $index) {
            $name = isset($service['ex_name']) ? $service['ex_name'] : '';
            $price = isset($service['ex_price']) ? $service['ex_price'] : '';
            $qty = isset($service['ex_qty']) ? $service['ex_qty'] : '';
            $type = isset($service['ex_type']) ? $service['ex_type'] : 'input_box';
            $qty_types = ['input_box' => __('Input Box', 'bus-ticket-booking-with-seat-reservation'), 'checkbox' => __('Checkbox', 'bus-ticket-booking-with-seat-reservation')];
            ?>
            <div class="wbtm-service-row">
                <input type="text" class="wbtm-form-control" name="wbtm_service_name[]" value="<?php echo esc_attr($name); ?>" placeholder="<?php esc_attr_e('Service name', 'bus-ticket-booking-with-seat-reservation'); ?>">
                <input type="number" class="wbtm-form-control" name="wbtm_service_price[]" value="<?php echo esc_attr($price); ?>" placeholder="0">
                <input type="number" class="wbtm-form-control" name="wbtm_service_qty[]" value="<?php echo esc_attr($qty); ?>" placeholder="0">
                <select class="wbtm-form-control" name="wbtm_service_type[]">
                    <?php foreach ($qty_types as $val => $label) { ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($type, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php } ?>
                </select>
                <button type="button" class="wbtm-icon-btn danger" onclick="wbtmRemoveService(this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <?php
        }
        
        private function render_pickup_step($post_id, $pickup_points, $bus_stops, $show_pickup, $show_boarding_time, $show_dropping_time) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Pickup Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('On/Off Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Turn on or off pickup point for this bus', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="show_pickup_point" value="yes" <?php checked($show_pickup, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('Boarding Point Required', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Make boarding point selection mandatory', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="show_boarding_time" value="yes" <?php checked($show_boarding_time, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
                
                <!-- Pickup points configuration will go here -->
                <?php if (empty($pickup_points)) { ?>
                <div class="wbtm-empty-state">
                    <div class="wbtm-empty-text"><?php _e('No pickup points configured. Add pickup points to enable boarding options for passengers.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                </div>
                <?php } else { ?>
                <div class="wbtm-pickup-list" id="wbtmPickupList">
                    <?php foreach ($pickup_points as $pickup) { 
                        $bp_point = isset($pickup['bp_point']) ? $pickup['bp_point'] : '';
                        ?>
                        <div class="wbtm-pickup-item">
                            <span class="wbtm-pickup-bp"><?php echo esc_html($bp_point); ?></span>
                        </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Drop-Off Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-toggle-row">
                    <div class="wbtm-toggle-info">
                        <div class="wbtm-toggle-label"><?php _e('Show Dropping Time', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-toggle-desc"><?php _e('Display estimated dropping time to passengers', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <label class="wbtm-toggle">
                        <input type="checkbox" name="show_dropping_time" value="yes" <?php checked($show_dropping_time, 'yes'); ?>>
                        <span class="wbtm-toggle-track"></span>
                        <span class="wbtm-toggle-thumb"></span>
                    </label>
                </div>
            </div>
            <?php
        }
        
        private function render_date_step($post_id, $date_type, $repeated_start, $repeated_end, $repeated_after, $off_days, $particular_dates) {
            $off_days_array = $off_days ? array_map('trim', explode(',', $off_days)) : [];
            $day_names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Date Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-form-grid wbtm-cols-2">
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Operation Date Type', 'bus-ticket-booking-with-seat-reservation'); ?> <span class="wbtm-req">*</span></label>
                        <select class="wbtm-form-control" name="show_operational_on_day" id="wbtmDateType" onchange="toggleDateFields(this.value);">
                            <option value="repeated" <?php selected($date_type, 'repeated'); ?>><?php _e('Repeated', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="particular" <?php selected($date_type, 'particular'); ?>><?php _e('Particular Date', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        </select>
                    </div>
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Maximum Advanced Booking Days', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <input type="number" class="wbtm-form-control" name="wbtm_max_advance_booking" value="90" min="1" max="365">
                    </div>
                </div>
                    
                <div id="wbtmRepeatedFields" style="<?php echo $date_type === 'particular' ? 'display:none;' : ''; ?>">
                    <div class="wbtm-form-grid wbtm-cols-2">
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Repeated Start Date', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <input type="date" class="wbtm-form-control" name="wbtm_repeated_start_date" value="<?php echo esc_attr($repeated_start); ?>">
                        </div>
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Repeated End Date', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <input type="date" class="wbtm-form-control" name="wbtm_repeated_end_date" value="<?php echo esc_attr($repeated_end); ?>">
                        </div>
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Repeat Every (days)', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <input type="number" class="wbtm-form-control" name="wbtm_repeated_after" value="<?php echo esc_attr($repeated_after); ?>" min="1" max="30">
                        </div>
                    </div>
                </div>
                    
                <div id="wbtmParticularFields" style="<?php echo $date_type !== 'particular' ? 'display:none;' : ''; ?>">
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Select Dates', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <textarea class="wbtm-form-control" name="wbtm_particular_dates_text" rows="3" placeholder="<?php esc_attr_e('YYYY-MM-DD, one per line or comma separated', 'bus-ticket-booking-with-seat-reservation'); ?>"><?php 
                            if (!empty($particular_dates) && is_array($particular_dates)) {
                                echo esc_textarea(implode("\n", $particular_dates));
                            }
                        ?></textarea>
                    </div>
                </div>
                    
                <div class="wbtm-form-grid" style="margin-top: 16px;">
                    <label class="wbtm-field-label" style="display:block;margin-bottom:8px"><?php _e('Off Days', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                    <div class="wbtm-day-chips">
                        <?php foreach ($day_names as $day) { ?>
                        <div class="wbtm-day-chip">
                            <input type="checkbox" id="offday_<?php echo esc_attr($day); ?>" name="wbtm_off_days[]" value="<?php echo esc_attr($day); ?>" <?php checked(in_array($day, $off_days_array)); ?>>
                            <label for="offday_<?php echo esc_attr($day); ?>"><?php echo esc_html($day); ?></label>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php
        }
        
        private function render_registration_step($post_id) {
            $registration_fields = WBTM_Global_Function::get_post_info($post_id, 'wbtm_registration_fields', []);
            $default_fields = [
                'passenger_name' => ['label' => __('Passenger Name', 'bus-ticket-booking-with-seat-reservation'), 'required' => true, 'visible' => true],
                'passenger_email' => ['label' => __('Passenger Email', 'bus-ticket-booking-with-seat-reservation'), 'required' => true, 'visible' => true],
                'passenger_phone' => ['label' => __('Passenger Phone', 'bus-ticket-booking-with-seat-reservation'), 'required' => false, 'visible' => false],
                'passenger_address' => ['label' => __('Passenger Address', 'bus-ticket-booking-with-seat-reservation'), 'required' => false, 'visible' => false],
                'passenger_gender' => ['label' => __('Gender', 'bus-ticket-booking-with-seat-reservation'), 'required' => false, 'visible' => false],
            ];
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Passenger Form Fields', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <table class="wbtm-reg-table">
                    <thead>
                        <tr>
                            <th><?php _e('Default Label', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Custom Label', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Required', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                            <th><?php _e('Visibility', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($default_fields as $field_key => $field_defaults) {
                            $saved = isset($registration_fields[$field_key]) ? $registration_fields[$field_key] : [];
                            $label = isset($saved['label']) ? $saved['label'] : $field_defaults['label'];
                            $required = isset($saved['required']) ? $saved['required'] : $field_defaults['required'];
                            $visible = isset($saved['visible']) ? $saved['visible'] : $field_defaults['visible'];
                        ?>
                        <tr class="wbtm-reg-row">
                            <td><?php echo esc_html($field_defaults['label']); ?></td>
                            <td><input type="text" class="wbtm-form-control" name="wbtm_reg_label[<?php echo esc_attr($field_key); ?>]" value="<?php echo esc_attr($label); ?>"></td>
                            <td>
                                <select class="wbtm-form-control" name="wbtm_reg_required[<?php echo esc_attr($field_key); ?>]">
                                    <option value="required" <?php selected($required, true); ?>><?php _e('Required', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="optional" <?php selected($required, false); ?>><?php _e('Optional', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                </select>
                            </td>
                            <td>
                                <select class="wbtm-form-control" name="wbtm_reg_visible[<?php echo esc_attr($field_key); ?>]">
                                    <option value="show" <?php selected($visible, true); ?>><?php _e('Show', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    <option value="hidden" <?php selected($visible, false); ?>><?php _e('Hidden', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <?php
        }
        
        private function render_tax_step($post_id) {
            $tax_status = WBTM_Global_Function::get_post_info($post_id, '_tax_status', '');
            $tax_class = WBTM_Global_Function::get_post_info($post_id, '_tax_class', '');
            $all_tax_class = WBTM_Global_Function::all_tax_list();
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Tax Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <p class="wbtm-field-hint" style="margin-bottom:16px"><?php _e('Configure tax settings for this bus service.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                
                <?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
                    <div class="wbtm-form-grid wbtm-cols-2">
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Tax Status', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <select class="wbtm-form-control" name="_tax_status">
                                <option value="" <?php selected($tax_status, ''); ?>><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="taxable" <?php selected($tax_status, 'taxable'); ?>><?php _e('Taxable', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="shipping" <?php selected($tax_status, 'shipping'); ?>><?php _e('Shipping only', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="none" <?php selected($tax_status, 'none'); ?>><?php _e('None', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            </select>
                        </div>
                        <div class="wbtm-field">
                            <label class="wbtm-field-label"><?php _e('Tax Class', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                            <select class="wbtm-form-control" name="_tax_class">
                                <option value="" <?php selected($tax_class, ''); ?>><?php _e('Please Select', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="standard" <?php selected($tax_class, 'standard'); ?>><?php _e('Standard', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <?php if (sizeof($all_tax_class) > 0) { ?>
                                    <?php foreach ($all_tax_class as $key => $class) { ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($tax_class, $key); ?>><?php echo esc_html($class); ?></option>
                                    <?php } ?>
                                <?php } ?>
                            </select>
                            <span class="wbtm-field-hint"><?php _e('To add new tax classes, go to WooCommerce → Settings → Tax', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="wbtm-empty-state">
                        <span class="wbtm-notice-icon">⚠️</span>
                        <span><?php _e('Tax calculation is not enabled in WooCommerce. Please enable taxes from WooCommerce → Settings → General.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                    </div>
                <?php } ?>
            </div>
            <?php
        }
        
        private function render_features_step($post_id) {
            $features = WTBM_Features_Seating::get_all_bus_features();
            $get_selected_features = get_post_meta($post_id, 'wbbm_bus_features_term_id', true);
            $selected_feature_ids = [];
            if (!empty($get_selected_features) && is_array($get_selected_features)) {
                $selected_feature_ids = array_map('intval', $get_selected_features);
            }
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Bus Features', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <p class="wbtm-field-hint" style="margin-bottom:16px"><?php _e('Select features available on this bus.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                
                <?php if (!empty($features)) { ?>
                    <div class="wbtm-features-grid">
                        <?php foreach ($features as $feature) { 
                            $is_checked = in_array((int) $feature['term_id'], $selected_feature_ids, true);
                        ?>
                            <label class="wbtm-feature-item">
                                <input type="checkbox" 
                                       class="wtbm_bus_feature_checkbox" 
                                       name="wtbm_bus_features[]" 
                                       value="<?php echo esc_attr($feature['term_id']); ?>"
                                       <?php checked($is_checked); ?>>
                                <span class="wbtm-feature-icon <?php echo esc_attr($feature['icon']); ?>"></span>
                                <span class="wbtm-feature-name"><?php echo esc_html($feature['name']); ?></span>
                            </label>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="wbtm-empty-state">
                        <div class="wbtm-empty-text"><?php _e('No bus features found. Add features from Bus Features menu.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                <?php } ?>
                <input type="hidden" id="wtbm_added_feature" name="wtbm_added_feature" value="<?php echo esc_attr(implode(',', $selected_feature_ids)); ?>">
            </div>
            <?php
        }
        
        private function render_terms_step($post_id) {
            $term_option_key = 'wbtm_term_condition_list';
            $terms = get_option($term_option_key, []);
            $added_terms = get_post_meta($post_id, $term_option_key, true);
            $selected_terms_data = [];
            if (!empty($added_terms) && !empty($terms)) {
                foreach ($added_terms as $term_key) {
                    if (isset($terms[$term_key])) {
                        $selected_terms_data[$term_key] = $terms[$term_key];
                    }
                }
            }
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Term & Condition', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <p class="wbtm-field-hint" style="margin-bottom:16px"><?php _e('Select terms and conditions for this bus service.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                
                <div class="wbtm-terms-layout">
                    <div class="wbtm-terms-available">
                        <h4><?php _e('Available Terms', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                        <?php if (!empty($terms)) { ?>
                            <div class="wbtm-terms-list">
                                <?php foreach ($terms as $key => $term) { 
                                    if (isset($selected_terms_data[$key])) continue;
                                ?>
                                    <div class="wbtm-term-row">
                                        <span class="wbtm-term-title"><?php echo esc_html($term['title']); ?></span>
                                        <button type="button" class="wbtm-btn-sm wtbm_add_term_condition" data-key="<?php echo esc_attr($key); ?>" data-title="<?php echo esc_attr($term['title']); ?>">
                                            <?php _e('Add', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </button>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p><?php _e('No terms available. Create terms from the Term & Condition menu.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                        <?php } ?>
                    </div>
                    
                    <div class="wbtm-terms-selected">
                        <h4><?php _e('Selected Terms', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                        <div class="wbtm-selected-terms-list" id="wbtmSelectedTermsList">
                            <?php if (!empty($selected_terms_data)) { ?>
                                <?php foreach ($selected_terms_data as $key => $term) { ?>
                                    <div class="wbtm-selected-term-row" data-key="<?php echo esc_attr($key); ?>">
                                        <span class="wbtm-term-title"><?php echo esc_html($term['title']); ?></span>
                                        <button type="button" class="wbtm-btn-sm danger wtbm_remove_term_condition" data-key="<?php echo esc_attr($key); ?>">
                                            <?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </button>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="wbtm-no-terms"><?php _e('No terms added yet.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="wtbm_added_term_condition_input" name="wtbm_added_term_condition" value='<?php echo esc_attr(json_encode($selected_terms_data)); ?>'>
            </div>
            <?php
        }
        
        private function render_gallery_step($post_id, $gallery_images, $feature_image_id) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Gallery Images', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-field-hint" style="margin-bottom:16px"><?php _e('Upload gallery images in 4:3 ratio. Recommended size: 1200×900px. Gallery and feature image should be the same size.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-upload-zone" id="wbtmGalleryUpload">
                    <div class="wbtm-upload-icon">🖼️</div>
                    <div class="wbtm-upload-title"><?php _e('Click to Upload Gallery Images', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    <div class="wbtm-upload-hint"><?php _e('Multiple images supported · JPG, PNG, WebP · Max 5MB each', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                </div>
                <div class="wbtm-upload-preview" id="wbtmGalleryPreview">
                    <?php if (!empty($gallery_images) && is_array($gallery_images)) { 
                        foreach ($gallery_images as $image_id) {
                            $image_url = wp_get_attachment_url($image_id);
                            if ($image_url) { ?>
                    <img src="<?php echo esc_url($image_url); ?>" class="wbtm-preview-img" alt="preview">
                    <?php } } } ?>
                </div>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Feature Image', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-upload-zone" id="wbtmFeatureUpload">
                    <div class="wbtm-upload-icon">⭐</div>
                    <div class="wbtm-upload-title"><?php _e('Set Feature Image', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    <div class="wbtm-upload-hint"><?php _e('This is the main display image for the bus listing', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                </div>
            </div>
            
            <?php
        }
        
        private function render_review_step($post_id) {
            ?>
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Review & Confirm', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-review-grid">
                    <div class="wbtm-review-card">
                        <div class="wbtm-review-card-title"><?php _e('General Info', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Bus Name', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-name">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Bus Number', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-no">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Coach Type', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-coach">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Reservation', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-res"><span class="wbtm-badge-pill green"><?php _e('Enabled', 'bus-ticket-booking-with-seat-reservation'); ?></span></span></div>
                    </div>
                    <div class="wbtm-review-card">
                        <div class="wbtm-review-card-title"><?php _e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-seattype">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Total Seats', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-seats">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Driver Position', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val">Left</span></div>
                    </div>
                    <div class="wbtm-review-card">
                        <div class="wbtm-review-card-title"><?php _e('Route', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Origin', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-origin">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Destination', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-destination">—</span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Total Stops', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-stops">0</span></div>
                    </div>
                    <div class="wbtm-review-card">
                        <div class="wbtm-review-card-title"><?php _e('Services & Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Extra Services', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-services">0 <?php _e('active', 'bus-ticket-booking-with-seat-reservation'); ?></span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Date Type', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val" id="rv-datetype"><?php _e('Repeated', 'bus-ticket-booking-with-seat-reservation'); ?></span></div>
                        <div class="wbtm-review-item"><span class="review-key"><?php _e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></span><span class="review-val"><span class="wbtm-badge-pill green"><?php _e('Published', 'bus-ticket-booking-with-seat-reservation'); ?></span></span></div>
                    </div>
                </div>
            </div>
            
            <div class="wbtm-section">
                <div class="wbtm-section-title"><?php _e('Publish Settings', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <div class="wbtm-form-grid wbtm-cols-2">
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Visibility', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <select class="wbtm-form-control" name="wbtm_visibility">
                            <option value="public"><?php _e('Public', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="private"><?php _e('Private', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="password"><?php _e('Password Protected', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        </select>
                    </div>
                    <div class="wbtm-field">
                        <label class="wbtm-field-label"><?php _e('Publish Status', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                        <select class="wbtm-form-control" name="wbtm_status">
                            <option value="publish"><?php _e('Published', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="draft"><?php _e('Draft', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            <option value="pending"><?php _e('Pending Review', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <datalist id="wbtmBusStopsList">
                <?php foreach (WBTM_Global_Function::get_all_term_data('wbtm_bus_stops') as $stop) { ?>
                <option value="<?php echo esc_attr($stop); ?>">
                <?php } ?>
            </datalist>
            <?php
        }
        
        public function save_bus_data($post_id, $post) {
            if (!isset($_POST['wbtm_modal_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['wbtm_modal_nonce']), 'wbtm_modal_nonce')) {
                return;
            }
            
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            
            if (get_post_type($post_id) !== 'wbtm_bus') {
                return;
            }
            
            // Save Bus Features (from modal checkbox inputs)
            if (isset($_POST['wtbm_bus_features'])) {
                $feature_ids = array_map('intval', (array) $_POST['wtbm_bus_features']);
                update_post_meta($post_id, 'wbbm_bus_features_term_id', $feature_ids);
            } elseif (isset($_POST['wtbm_added_feature'])) {
                // Fallback: parse from hidden input
                $feature_ids = array_filter(array_map('intval', explode(',', sanitize_text_field(wp_unslash($_POST['wtbm_added_feature'])))));
                update_post_meta($post_id, 'wbbm_bus_features_term_id', $feature_ids);
            }
            
            // Save Term & Conditions (from modal)
            if (isset($_POST['wtbm_added_term_condition'])) {
                $raw_data = wp_unslash($_POST['wtbm_added_term_condition']);
                $data = json_decode($raw_data, true);
                if (JSON_ERROR_NONE === json_last_error() && is_array($data)) {
                    // The terms are stored as an array of keys in the original system
                    // We need to extract just the keys from the JSON object
                    $term_keys = array_keys($data);
                    update_post_meta($post_id, 'wbtm_term_condition_list', $term_keys);
                }
            }
            
            // Tax, Gallery, and other fields are handled by WBTM_Settings::save_settings()
            // which runs on the same save_post hook
        }
        
        public function ajax_get_bus_data() {
            check_ajax_referer('wbtm_modal_nonce', 'nonce');
            
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            
            if (!$post_id) {
                $data = [
                    'success' => false,
                    'message' => 'Invalid post ID',
                ];
            } else {
                $data = [
                    'success' => true,
                    'post_id' => $post_id,
                    'bus_name' => get_the_title($post_id),
                    'bus_no' => WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_no'),
                    'bus_category' => WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category'),
                ];
            }
            wp_send_json($data);
        }
        
        public function ajax_save_bus() {
            check_ajax_referer('wbtm_modal_nonce', 'nonce');
            
            // Data is saved via normal WordPress post save mechanism
            // This is just for quick save functionality
            
            wp_send_json_success([
                'message' => 'Bus data saved successfully',
            ]);
        }
    }
    
    // Initialize
    WBTM_Bus_Modal::get_instance();
}