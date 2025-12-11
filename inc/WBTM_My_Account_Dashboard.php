<?php
/*
 * @Author: MagePeople Team
 * Copyright: mage-people.com
 */

if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('WBTM_My_Account_Dashboard')) {
    class WBTM_My_Account_Dashboard
    {
        public function __construct()
        {
            add_action('init', array($this, 'add_endpoints'));
            add_filter('query_vars', array($this, 'add_query_vars'), 0);
            add_filter('woocommerce_account_menu_items', array($this, 'add_menu_items'));
            add_action('woocommerce_account_bus-booking-dashboard_endpoint', array($this, 'bus_booking_dashboard_content'));
            add_filter('the_title', array($this, 'endpoint_title'));
            
            // AJAX handlers for dashboard functionality
            add_action('wp_ajax_wbtm_get_user_bookings', array($this, 'get_user_bookings'));
            add_action('wp_ajax_wbtm_get_booking_details', array($this, 'get_booking_details'));
            add_action('wp_ajax_wbtm_update_attendee_info', array($this, 'update_attendee_info'));
            
            // Enqueue scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
        }

        /**
         * Register new endpoint to use inside My Account page.
         */
        public function add_endpoints()
        {
            add_rewrite_endpoint('bus-booking-dashboard', EP_ROOT | EP_PAGES);
        }

        /**
         * Add new query var.
         */
        public function add_query_vars($vars)
        {
            $vars[] = 'bus-booking-dashboard';
            return $vars;
        }

        /**
         * Insert the new endpoint into the My Account menu.
         */
        public function add_menu_items($items)
        {
            $new_items = array();
            $label = WBTM_Functions::get_name();
            $new_items['bus-booking-dashboard'] = $label . ' ' . __('Booking Dashboard', 'bus-ticket-booking-with-seat-reservation');

            // Add the new item after `orders`.
            $after = 'orders';
            $position = array_search($after, array_keys($items)) + 1;

            // Insert the new item.
            $array = array_slice($items, 0, $position, true);
            $array += $new_items;
            $array += array_slice($items, $position, count($items) - $position, true);

            return $array;
        }

        /**
         * Change endpoint title.
         */
        public function endpoint_title($title)
        {
            global $wp_query;
            $is_endpoint = isset($wp_query->query_vars['bus-booking-dashboard']);

            if ($is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
                $label = WBTM_Functions::get_name();
                $title = $label . ' ' . __('Booking Dashboard', 'bus-ticket-booking-with-seat-reservation');
                remove_filter('the_title', array($this, 'endpoint_title'));
            }

            return $title;
        }

        /**
         * Endpoint HTML content.
         */
        public function bus_booking_dashboard_content()
        {
            $current_user = wp_get_current_user();
            $user_id = get_current_user_id();
            
            if (!$user_id) {
                echo '<p>' . __('Please log in to view your bookings.', 'bus-ticket-booking-with-seat-reservation') . '</p>';
                return;
            }

            ?>
            <div class="wbtm-my-account-dashboard">
                <div class="wbtm-dashboard-header">
                    <h2><?php echo WBTM_Functions::get_name() . ' ' . __('Booking Dashboard', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                    <p><?php _e('View and manage all your bus bookings in one place', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                </div>

                <!-- Dashboard Stats -->
                <div class="wbtm-dashboard-stats">
                    <div class="wbtm-stat-card">
                        <div class="wbtm-stat-number" id="total-bookings">0</div>
                        <div class="wbtm-stat-label"><?php _e('TOTAL BOOKINGS', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <div class="wbtm-stat-card">
                        <div class="wbtm-stat-number" id="upcoming-bookings">0</div>
                        <div class="wbtm-stat-label"><?php _e('UPCOMING', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <div class="wbtm-stat-card">
                        <div class="wbtm-stat-number" id="completed-bookings">0</div>
                        <div class="wbtm-stat-label"><?php _e('COMPLETED', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="wbtm-dashboard-filters">
                    <div class="wbtm-search-container">
                        <div class="wbtm-search-row">
                            <select id="wbtm-search-type" class="wbtm-search-select">
                                <option value="order_id"><?php _e('Order ID', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="bus_name"><?php _e('Bus Name', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="journey_date"><?php _e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                <option value="route"><?php _e('Route', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                            </select>
                            <input type="text" id="wbtm-search-bookings" placeholder="<?php esc_attr_e('Enter search term...', 'bus-ticket-booking-with-seat-reservation'); ?>" />
                            <input type="date" id="wbtm-search-date" placeholder="<?php esc_attr_e('Select date...', 'bus-ticket-booking-with-seat-reservation'); ?>" style="display: none;" />
                        </div>
                        <div class="wbtm-search-buttons">
                            <button type="button" class="wbtm-search-btn" id="wbtm-search-btn">
                                <i class="fas fa-search"></i> <?php _e('Search', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </button>
                            <button type="button" class="wbtm-reset-btn" id="wbtm-reset-btn">
                                <i class="fas fa-undo"></i> <?php _e('Reset', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="wbtm-bookings-container">
                    <div class="wbtm-bookings-header">
                        <div class="wbtm-header-cell"><?php _e('ORDER', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-header-cell"><?php _e('BUS BOOKING DETAILS', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-header-cell"><?php _e('TICKETS', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-header-cell"><?php _e('PRICE', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-header-cell"><?php _e('STATUS', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm-header-cell"><?php _e('ACTIONS', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                    </div>
                    <div class="wbtm-bookings-list" id="wbtm-bookings-list">
                        <div class="wbtm-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <?php _e('Loading bookings...', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="wbtm-pagination" id="wbtm-pagination" style="display: none;">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>

            <!-- Booking Details Modal -->
            <div id="wbtm-booking-modal" class="wbtm-modal" style="display: none;">
                <div class="wbtm-modal-content">
                    <div class="wbtm-modal-header">
                        <h3><?php _e('Booking Details', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <button type="button" class="wbtm-modal-close" id="wbtm-modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="wbtm-modal-body" id="wbtm-modal-body">
                        <!-- Booking details will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Edit Attendee Modal -->
            <div id="wbtm-edit-modal" class="wbtm-modal" style="display: none;">
                <div class="wbtm-modal-content">
                    <div class="wbtm-modal-header">
                        <h3><?php _e('Edit Attendee Information', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <button type="button" class="wbtm-modal-close" id="wbtm-edit-modal-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="wbtm-modal-body" id="wbtm-edit-modal-body">
                        <!-- Edit form will be loaded here -->
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Get user bookings via AJAX
         */
        public function get_user_bookings()
        {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('Please log in to view bookings.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbtm_dashboard_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed.', 'bus-ticket-booking-with-seat-reservation')));
            }

            $user_id = get_current_user_id();
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $search = isset($_POST['search']) ? $_POST['search'] : '';
            $per_page = 10;

            // Get user's bus bookings from wbtm_bus_booking posts
            $filter_args = array(
                'wbtm_user_id' => $user_id
            );

            // Add search filter if provided
            if (!empty($search)) {
                if (is_array($search) && isset($search['type']) && isset($search['term'])) {
                    // Enhanced search with type
                    $search_type = sanitize_text_field($search['type']);
                    $search_term = sanitize_text_field($search['term']);
                    
                    switch ($search_type) {
                        case 'order_id':
                            $filter_args['wbtm_order_id'] = $search_term;
                            break;
                        case 'bus_name':
                            // Search for buses by name and get their IDs
                            $bus_posts = get_posts(array(
                                'post_type' => 'wbtm_bus',
                                'post_status' => 'publish',
                                's' => $search_term,
                                'posts_per_page' => -1,
                                'fields' => 'ids'
                            ));
                            if (!empty($bus_posts)) {
                                $filter_args['wbtm_bus_id'] = $bus_posts;
                            } else {
                                // No buses found, set impossible condition
                                $filter_args['wbtm_bus_id'] = array(0);
                            }
                            break;
                        case 'journey_date':
                            $filter_args['wbtm_boarding_time'] = $search_term;
                            break;
                        case 'route':
                            // For route search, we'll need to filter by boarding and dropping points
                            $route_parts = explode('→', $search_term);
                            if (count($route_parts) == 2) {
                                $boarding = trim($route_parts[0]);
                                $dropping = trim($route_parts[1]);
                                $filter_args['wbtm_boarding_point'] = $boarding;
                                $filter_args['wbtm_dropping_point'] = $dropping;
                            } else {
                                // Search in both boarding and dropping points
                                $filter_args['route_search'] = $search_term;
                            }
                            break;
                    }
                } else {
                    // Simple search (backward compatibility)
                    $search_term = sanitize_text_field($search);
                    $filter_args['wbtm_order_id'] = $search_term;
                }
            }

            // Handle special search cases that need custom queries
            $need_custom_query = isset($filter_args['route_search']) || (isset($filter_args['wbtm_bus_id']) && is_array($filter_args['wbtm_bus_id']));
            
            if ($need_custom_query) {
                // Use custom query for bus name and route searches
                $bookings_query = $this->custom_attendee_query($filter_args, $per_page, $page);
                $all_bookings_query = $this->custom_attendee_query(array('wbtm_user_id' => $user_id), -1, 1);
            } else if (class_exists('WBTM_Function_PRO')) {
                $bookings_query = WBTM_Function_PRO::attendee_query($filter_args, $per_page, $page);
                $all_bookings_query = WBTM_Function_PRO::attendee_query(array('wbtm_user_id' => $user_id), -1, 1);
            } else {
                // Fallback query if PRO class not available
                $args = array(
                    'post_type' => 'wbtm_bus_booking',
                    'posts_per_page' => $per_page,
                    'paged' => $page,
                    'meta_query' => array(
                        array(
                            'key' => 'wbtm_user_id',
                            'value' => $user_id,
                            'compare' => '='
                        ),
                        array(
                            'key' => 'wbtm_order_status',
                            'value' => array('completed', 'processing', 'on-hold'),
                            'compare' => 'IN'
                        )
                    )
                );

                if (!empty($search)) {
                    if (is_array($search) && isset($search['type']) && isset($search['term'])) {
                        $search_type = sanitize_text_field($search['type']);
                        $search_term = sanitize_text_field($search['term']);
                        
                        switch ($search_type) {
                            case 'order_id':
                                $args['meta_query'][] = array(
                                    'key' => 'wbtm_order_id',
                                    'value' => $search_term,
                                    'compare' => 'LIKE'
                                );
                                break;
                            case 'journey_date':
                                $args['meta_query'][] = array(
                                    'key' => 'wbtm_boarding_time',
                                    'value' => $search_term,
                                    'compare' => 'LIKE'
                                );
                                break;
                            case 'route':
                                $args['meta_query'][] = array(
                                    'relation' => 'OR',
                                    array(
                                        'key' => 'wbtm_boarding_point',
                                        'value' => $search_term,
                                        'compare' => 'LIKE'
                                    ),
                                    array(
                                        'key' => 'wbtm_dropping_point',
                                        'value' => $search_term,
                                        'compare' => 'LIKE'
                                    )
                                );
                                break;
                        }
                    } else {
                        // Simple search fallback
                        $search_term = sanitize_text_field($search);
                        $args['meta_query'][] = array(
                            'key' => 'wbtm_order_id',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        );
                    }
                }

                $bookings_query = new WP_Query($args);
                
                // Get all bookings for stats
                $all_args = $args;
                $all_args['posts_per_page'] = -1;
                $all_args['paged'] = 1;
                unset($all_args['meta_query'][2]); // Remove search filter for stats
                $all_bookings_query = new WP_Query($all_args);
            }

            $bookings = array();
            $stats = array('total' => 0, 'upcoming' => 0, 'completed' => 0);

            // Process current page bookings
            if ($bookings_query->have_posts()) {
                foreach ($bookings_query->posts as $booking_post) {
                    $booking_data = $this->format_booking_data($booking_post);
                    if ($booking_data) {
                        $bookings[] = $booking_data;
                    }
                }
            }

            // Calculate stats from all user bookings
            if ($all_bookings_query->have_posts()) {
                foreach ($all_bookings_query->posts as $booking_post) {
                    $stats['total']++;
                    $journey_date = get_post_meta($booking_post->ID, 'wbtm_boarding_time', true);
                    if ($journey_date && strtotime($journey_date) > current_time('timestamp')) {
                        $stats['upcoming']++;
                    } else {
                        $stats['completed']++;
                    }
                }
            }

            wp_send_json_success(array(
                'bookings' => $bookings,
                'stats' => $stats,
                'pagination' => array(
                    'current_page' => $page,
                    'total_pages' => $bookings_query->max_num_pages,
                    'total_items' => $bookings_query->found_posts
                )
            ));
        }

        /**
         * Custom attendee query for complex searches
         */
        private function custom_attendee_query($filter_args = array(), $per_page = -1, $page = 1)
        {
            $meta_query = array();
            
            // Always filter by user ID
            if (isset($filter_args['wbtm_user_id'])) {
                $meta_query[] = array(
                    'key' => 'wbtm_user_id',
                    'value' => $filter_args['wbtm_user_id'],
                    'compare' => '='
                );
            }
            
            // Handle bus ID (single or array)
            if (isset($filter_args['wbtm_bus_id'])) {
                if (is_array($filter_args['wbtm_bus_id'])) {
                    $meta_query[] = array(
                        'key' => 'wbtm_bus_id',
                        'value' => $filter_args['wbtm_bus_id'],
                        'compare' => 'IN'
                    );
                } else {
                    $meta_query[] = array(
                        'key' => 'wbtm_bus_id',
                        'value' => $filter_args['wbtm_bus_id'],
                        'compare' => '='
                    );
                }
            }
            
            // Handle route search
            if (isset($filter_args['route_search'])) {
                $route_term = $filter_args['route_search'];
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wbtm_boarding_point',
                        'value' => $route_term,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'wbtm_dropping_point',
                        'value' => $route_term,
                        'compare' => 'LIKE'
                    )
                );
            }
            
            // Handle other standard filters
            $standard_filters = array(
                'wbtm_order_id' => '=',
                'wbtm_boarding_time' => 'LIKE',
                'wbtm_boarding_point' => '=',
                'wbtm_dropping_point' => '='
            );
            
            foreach ($standard_filters as $key => $compare) {
                if (isset($filter_args[$key]) && $filter_args[$key]) {
                    $meta_query[] = array(
                        'key' => $key,
                        'value' => $filter_args[$key],
                        'compare' => $compare
                    );
                }
            }
            
            $args = array(
                'post_type' => 'wbtm_bus_booking',
                'post_status' => 'publish',
                'meta_query' => $meta_query,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            return new WP_Query($args);
        }

        /**
         * Format booking data for display
         */
        private function format_booking_data($booking_post)
        {
            $booking_id = $booking_post->ID;
            $order_id = get_post_meta($booking_id, 'wbtm_order_id', true);
            $bus_id = get_post_meta($booking_id, 'wbtm_bus_id', true);
            
            if (!$bus_id || !$order_id) return null;

            $bus_name = get_the_title($bus_id);
            $journey_date = get_post_meta($booking_id, 'wbtm_boarding_time', true);
            $boarding_point = get_post_meta($booking_id, 'wbtm_boarding_point', true);
            $dropping_point = get_post_meta($booking_id, 'wbtm_dropping_point', true);
            $seat = get_post_meta($booking_id, 'wbtm_seat', true);
            $fare = get_post_meta($booking_id, 'wbtm_bus_fare', true);
            $order_status = get_post_meta($booking_id, 'wbtm_order_status', true);
            $booking_date = get_post_meta($booking_id, 'wbtm_booking_date', true);

            // Get WooCommerce order for additional details
            $wc_order = wc_get_order($order_id);
            $order_total = $wc_order ? $wc_order->get_total() : $fare;
            
            // Group bookings by order ID to show consolidated view
            static $processed_orders = array();
            
            if (!isset($processed_orders[$order_id])) {
                // Get all bookings for this order
                $order_bookings = get_posts(array(
                    'post_type' => 'wbtm_bus_booking',
                    'meta_query' => array(
                        array(
                            'key' => 'wbtm_order_id',
                            'value' => $order_id,
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => -1
                ));

                $attendees = array();
                $has_extra_services = false;
                foreach ($order_bookings as $booking) {
                    $extra_services = get_post_meta($booking->ID, 'wbtm_extra_services', true);
                    if (is_array($extra_services) && !empty($extra_services)) {
                        $has_extra_services = true;
                    }
                    
                    $attendees[] = array(
                        'id' => $booking->ID,
                        'seat' => get_post_meta($booking->ID, 'wbtm_seat', true)
                    );
                }

                // Generate PDF URL using the pro addon method
                $pdf_url = '';
                if (class_exists('WBTM_Pro_Pdf')) {
                    $pdf_url = WBTM_Pro_Pdf::get_pdf_url(array('wbtm_order_id' => $order_id));
                }

                $processed_orders[$order_id] = array(
                    'order_id' => $order_id,
                    'order_number' => $wc_order ? $wc_order->get_order_number() : $order_id,
                    'bus_name' => $bus_name,
                    'journey_date' => $journey_date ? date('M j, Y g:i A', strtotime($journey_date)) : '',
                    'boarding_point' => $boarding_point,
                    'dropping_point' => $dropping_point,
                    'status' => $order_status,
                    'total' => $order_total,
                    'attendees' => $attendees,
                    'ticket_count' => count($attendees),
                    'order_date' => $booking_date ? date('M j, Y', strtotime($booking_date)) : '',
                    'has_extra_services' => $has_extra_services,
                    'pdf_url' => $pdf_url
                );
                
                return $processed_orders[$order_id];
            }
            
            // Skip if already processed
            return null;
        }

        /**
         * Get detailed booking information via AJAX
         */
        public function get_booking_details()
        {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('Please log in to view booking details.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbtm_dashboard_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed.', 'bus-ticket-booking-with-seat-reservation')));
            }

            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            if (!$order_id) {
                wp_send_json_error(array('message' => __('Invalid order ID.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Get booking posts for this order
            $user_id = get_current_user_id();
            $bookings = get_posts(array(
                'post_type' => 'wbtm_bus_booking',
                'meta_query' => array(
                    array(
                        'key' => 'wbtm_order_id',
                        'value' => $order_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'wbtm_user_id',
                        'value' => $user_id,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => -1
            ));

            if (empty($bookings)) {
                wp_send_json_error(array('message' => __('Booking not found or access denied.', 'bus-ticket-booking-with-seat-reservation')));
            }

            $details = $this->get_detailed_booking_info_from_posts($bookings, $order_id);
            wp_send_json_success($details);
        }

        /**
         * Get detailed booking information from booking posts
         */
        private function get_detailed_booking_info_from_posts($bookings, $order_id)
        {
            if (empty($bookings)) return null;

            $first_booking = $bookings[0];
            $bus_id = get_post_meta($first_booking->ID, 'wbtm_bus_id', true);
            $bus_name = get_the_title($bus_id);
            
            // Get WooCommerce order details
            $wc_order = wc_get_order($order_id);
            
            // Get all attendee details
            $attendees = array();
            foreach ($bookings as $booking) {
                $attendee_info = $this->get_attendee_details_from_post($booking->ID);
                if ($attendee_info) {
                    $attendees[] = $attendee_info;
                }
            }

            return array(
                'order' => array(
                    'id' => $order_id,
                    'number' => $wc_order ? $wc_order->get_order_number() : $order_id,
                    'date' => $wc_order ? $wc_order->get_date_created()->format('M j, Y g:i A') : get_post_meta($first_booking->ID, 'wbtm_booking_date', true),
                    'status' => get_post_meta($first_booking->ID, 'wbtm_order_status', true),
                    'total' => $wc_order ? $wc_order->get_total() : ''
                ),
                'bus' => array(
                    'id' => $bus_id,
                    'name' => $bus_name
                ),
                'journey' => array(
                    'date' => get_post_meta($first_booking->ID, 'wbtm_boarding_time', true),
                    'boarding_point' => get_post_meta($first_booking->ID, 'wbtm_boarding_point', true),
                    'dropping_point' => get_post_meta($first_booking->ID, 'wbtm_dropping_point', true),
                    'pickup_point' => get_post_meta($first_booking->ID, 'wbtm_pickup_point', true),
                    'drop_off_point' => get_post_meta($first_booking->ID, 'wbtm_drop_off_point', true)
                ),
                'attendees' => $attendees
            );
        }

        /**
         * Get attendee details from booking post
         */
        private function get_attendee_details_from_post($booking_id)
        {
            $attendee_info = array(
                'id' => $booking_id,
                'seat' => get_post_meta($booking_id, 'wbtm_seat', true),
                'fare' => get_post_meta($booking_id, 'wbtm_bus_fare', true)
            );

            // Get extra services information
            $extra_services = get_post_meta($booking_id, 'wbtm_extra_services', true);
            if (is_array($extra_services) && !empty($extra_services)) {
                $attendee_info['extra_services'] = $extra_services;
            }

            // Get custom attendee information
            if (class_exists('WBTM_Function_PRO')) {
                $custom_info = WBTM_Function_PRO::get_attendee_info($booking_id);
                if (is_array($custom_info)) {
                    $formatted_fields = array();
                    foreach ($custom_info as $field) {
                        if (isset($field['name']) && isset($field['value'])) {
                            $formatted_fields[$field['name']] = array(
                                'label' => $field['name'],
                                'value' => $field['value']
                            );
                        }
                    }
                    $attendee_info['custom_fields'] = $formatted_fields;
                }
            }

            // Get stored attendee info
            $stored_info = get_post_meta($booking_id, 'wbtm_attendee_info', true);
            if (is_array($stored_info)) {
                if (!isset($attendee_info['custom_fields'])) {
                    $attendee_info['custom_fields'] = array();
                }
                foreach ($stored_info as $field) {
                    if (isset($field['name']) && isset($field['value'])) {
                        $attendee_info['custom_fields'][$field['name']] = array(
                            'label' => $field['name'],
                            'value' => $field['value']
                        );
                    }
                }
            }

            return $attendee_info;
        }

        /**
         * Update attendee information via AJAX
         */
        public function update_attendee_info()
        {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => __('Please log in to update attendee information.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wbtm_dashboard_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed.', 'bus-ticket-booking-with-seat-reservation')));
            }

            $booking_id = isset($_POST['attendee_id']) ? intval($_POST['attendee_id']) : 0;
            $field_data = isset($_POST['field_data']) ? $_POST['field_data'] : array();

            if (!$booking_id) {
                wp_send_json_error(array('message' => __('Invalid booking ID.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Verify user owns this booking record
            $user_id = get_current_user_id();
            $booking_user_id = get_post_meta($booking_id, 'wbtm_user_id', true);
            
            if ($booking_user_id != $user_id) {
                wp_send_json_error(array('message' => __('Access denied.', 'bus-ticket-booking-with-seat-reservation')));
            }

            // Update attendee information
            $updated = false;
            $attendee_info = get_post_meta($booking_id, 'wbtm_attendee_info', true);
            if (!is_array($attendee_info)) {
                $attendee_info = array();
            }

            foreach ($field_data as $field_name => $value) {
                $field_name = sanitize_text_field($field_name);
                $value = sanitize_text_field($value);
                
                // Update in attendee_info array
                $field_found = false;
                foreach ($attendee_info as &$field) {
                    if (isset($field['name']) && $field['name'] === $field_name) {
                        $field['value'] = $value;
                        $field_found = true;
                        $updated = true;
                        break;
                    }
                }
                
                // If field not found, add it
                if (!$field_found) {
                    $attendee_info[] = array(
                        'name' => $field_name,
                        'value' => $value
                    );
                    $updated = true;
                }

                // Also update direct meta fields for common fields
                $meta_key_map = array(
                    'full_name' => 'wbtm_user_name',
                    'email' => 'wbtm_user_email',
                    'phone' => 'wbtm_user_phone'
                );

                if (isset($meta_key_map[$field_name])) {
                    update_post_meta($booking_id, $meta_key_map[$field_name], $value);
                }
            }

            if ($updated) {
                update_post_meta($booking_id, 'wbtm_attendee_info', $attendee_info);
                wp_send_json_success(array('message' => __('Attendee information updated successfully.', 'bus-ticket-booking-with-seat-reservation')));
            } else {
                wp_send_json_error(array('message' => __('No changes were made.', 'bus-ticket-booking-with-seat-reservation')));
            }
        }

        /**
         * Enqueue scripts and styles
         */
        public function enqueue_scripts()
        {
            if (is_account_page()) {
                wp_enqueue_style('wbtm-my-account-dashboard', WBTM_PLUGIN_URL . '/assets/css/my-account-dashboard.css', array(), '1.0.0');
                wp_enqueue_script('wbtm-my-account-dashboard', WBTM_PLUGIN_URL . '/assets/js/my-account-dashboard.js', array('jquery'), '1.0.0', true);
                
                wp_localize_script('wbtm-my-account-dashboard', 'wbtm_dashboard_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wbtm_dashboard_nonce'),
                    'pdf_nonce' => wp_create_nonce('wbtm_generate_pdf'),
                    'pdf_enabled' => class_exists('WBTM_Pro_Pdf'),
                    'strings' => array(
                        'loading' => __('Loading...', 'bus-ticket-booking-with-seat-reservation'),
                        'error' => __('An error occurred. Please try again.', 'bus-ticket-booking-with-seat-reservation'),
                        'confirm_update' => __('Are you sure you want to update this information?', 'bus-ticket-booking-with-seat-reservation')
                    )
                ));
            }
        }

    }

    new WBTM_My_Account_Dashboard();
}
