<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('WBTM_Analytics_Dashboard')) {
    class WBTM_Analytics_Dashboard {
        public function __construct() {
            add_action('admin_menu', array($this, 'add_analytics_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        }

        public function add_analytics_menu() {
            add_submenu_page(
                'edit.php?post_type=wbtm_bus', 
                __('Analytics Dashboard', 'bus-ticket-booking-with-seat-reservation'),
                __('Analytics', 'bus-ticket-booking-with-seat-reservation'),
                'manage_options',
                'wbtm-analytics',
                array($this, 'analytics_dashboard_page')
            );
        }

        public function enqueue_analytics_scripts($hook) {
            if($hook != 'wbtm_bus_page_wbtm-analytics') {
                return;
            }
            
            wp_enqueue_style('wbtm-analytics-style', plugins_url('/assets/css/wbtm-analytics.css', dirname(__FILE__)));
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
            wp_enqueue_script('wbtm-analytics-js', plugins_url('/assets/js/wbtm-analytics.js', dirname(__FILE__)), array('jquery', 'chart-js'), '1.0', true);
            
            // Add this: Localize the script with new data
            $stats = $this->get_booking_stats();
            wp_localize_script('wbtm-analytics-js', 'wbtmAnalytics', array(
                'popularRoutes' => $stats['popular_routes'],
                'months' => array_keys($stats['monthly_revenue']),
                'revenue' => array_values($stats['monthly_revenue'])
             ));

            // Add advanced stats to JavaScript
            wp_localize_script('wbtm-analytics-js', 'wbtmAdvancedAnalytics', array(
                'peakHours' => $stats['peak_hours'],
                'ticketTypes' => $stats['ticket_types'],
                'busTypes' => $stats['bus_types'],
                'weeklyStats' => $stats['weekly_stats'],
                'occupancyRates' => $stats['occupancy_rates']
            ));
        }

        public function get_booking_stats() {
            global $wpdb;
            
            // Add error logging function
            $log_file = WP_CONTENT_DIR . '/debug-analytics.log';
            error_log("\n=== Starting analytics calculation - " . date('Y-m-d H:i:s') . " ===\n", 3, $log_file);
            
            $stats = array(
                'total_bookings' => 0,
                'total_revenue' => 0,
                'total_seats' => 0,
                'popular_routes' => array(),
                'monthly_revenue' => array(),
                'bus_occupancy' => 0,
                'peak_hours' => array(),
                'ticket_types' => array(),
                'bus_types' => array(),
                'return_customers' => 0,
                'occupancy_rates' => array(),
                'weekly_stats' => array()
            );

            try {
                // Get all bookings regardless of status
                $args = array(
                    'post_type' => 'wbtm_bus_booking',
                    'posts_per_page' => -1,
                    'post_status' => 'any'
                );

                $bookings = get_posts($args);
                error_log("Raw bookings found: " . count($bookings) . "\n", 3, $log_file);

                // Debug post types
                $post_types = get_post_types([], 'names');
                error_log("Available post types: " . print_r($post_types, true) . "\n", 3, $log_file);

                // Initialize monthly revenue array for last 6 months
                for($i = 5; $i >= 0; $i--) {
                    $month = date('M Y', strtotime("-$i months"));
                    $stats['monthly_revenue'][$month] = 0;
                }

                foreach($bookings as $booking) {
                    $order_id = get_post_meta($booking->ID, 'wbtm_order_id', true);
                    
                    // Debug booking data
                    error_log("\nProcessing Booking:\n", 3, $log_file);
                    error_log("Booking ID: {$booking->ID}\n", 3, $log_file);
                    error_log("Post Status: {$booking->post_status}\n", 3, $log_file);
                    error_log("Order ID: {$order_id}\n", 3, $log_file);
                    
                    // Get all meta for debugging
                    $all_meta = get_post_meta($booking->ID);
                    error_log("All booking meta: " . print_r($all_meta, true) . "\n", 3, $log_file);

                    if($order_id) {
                        $order = wc_get_order($order_id);
                        if($order) {
                            $order_status = $order->get_status();
                            $total = $order->get_total();
                            
                            error_log("Order Status: {$order_status}, Total: {$total}\n", 3, $log_file);

                            // Include both processing and completed orders
                            if(in_array($order_status, array('processing', 'completed', 'wc-processing', 'wc-completed'))) {
                                $stats['total_bookings']++;
                                $stats['total_revenue'] += $total;
                                
                                $month = date('M Y', strtotime($order->get_date_created()));
                                if(isset($stats['monthly_revenue'][$month])) {
                                    $stats['monthly_revenue'][$month] += $total;
                                }

                                // Add route data only for valid orders
                                $bp = get_post_meta($booking->ID, 'wbtm_boarding_point', true);
                                $dp = get_post_meta($booking->ID, 'wbtm_dropping_point', true);
                                
                                if($bp && $dp) {
                                    $route = $bp . ' - ' . $dp;
                                    if(!isset($stats['popular_routes'][$route])) {
                                        $stats['popular_routes'][$route] = 0;
                                    }
                                    $stats['popular_routes'][$route]++;
                                }

                                // Fix: Count seats properly
                                // First try to get seat numbers
                                $seat_numbers = get_post_meta($booking->ID, 'wbtm_seat', true);
                                if($seat_numbers) {
                                    // If seat numbers exist, count them
                                    if(is_array($seat_numbers)) {
                                        $stats['total_seats'] += count($seat_numbers);
                                    } else {
                                        $stats['total_seats'] += 1; // Single seat
                                    }
                                } else {
                                    // Fallback to seat quantity
                                    $seat_qty = (int)get_post_meta($booking->ID, 'wbtm_seat_qty', true);
                                    if($seat_qty > 0) {
                                        $stats['total_seats'] += $seat_qty;
                                    } else {
                                        // If no explicit quantity, count as 1 seat per booking
                                        $stats['total_seats'] += 1;
                                    }
                                }

                                error_log("Seats counted for booking {$booking->ID}: " . $stats['total_seats'] . "\n", 3, $log_file);

                                // Calculate peak booking hours
                                $booking_time = get_post_time('H', false, $booking->ID);
                                if(!isset($stats['peak_hours'][$booking_time])) {
                                    $stats['peak_hours'][$booking_time] = 0;
                                }
                                $stats['peak_hours'][$booking_time]++;

                                // Track ticket types
                                $ticket_type = get_post_meta($booking->ID, 'wbtm_ticket', true);
                                if(!isset($stats['ticket_types'][$ticket_type])) {
                                    $stats['ticket_types'][$ticket_type] = 0;
                                }
                                $stats['ticket_types'][$ticket_type]++;

                                // Track bus types and revenue
                                $bus_id = get_post_meta($booking->ID, 'wbtm_bus_id', true);
                                $bus_type = get_post_meta($bus_id, 'wbtm_bus_type', true);
                                if(!isset($stats['bus_types'][$bus_type])) {
                                    $stats['bus_types'][$bus_type] = array(
                                        'revenue' => 0,
                                        'bookings' => 0,
                                        'seats' => 0
                                    );
                                }
                                $stats['bus_types'][$bus_type]['bookings']++;
                                $stats['bus_types'][$bus_type]['revenue'] += $total;

                                // Calculate occupancy rates
                                $total_seats = get_post_meta($bus_id, 'wbtm_total_seat', true);
                                if($total_seats) {
                                    $occupancy = ($total_seats > 0) ? ($stats['total_seats'] / $total_seats) * 100 : 0;
                                    $stats['occupancy_rates'][$bus_id] = $occupancy;
                                }

                                // Weekly booking patterns
                                $booking_day = date('w', strtotime($booking->post_date));
                                if(!isset($stats['weekly_stats'][$booking_day])) {
                                    $stats['weekly_stats'][$booking_day] = 0;
                                }
                                $stats['weekly_stats'][$booking_day]++;
                            }
                        } else {
                            error_log("Order not found for ID: {$order_id}\n", 3, $log_file);
                        }
                    }
                }

                // Sort and limit popular routes
                if(!empty($stats['popular_routes'])) {
                    arsort($stats['popular_routes']);
                    $stats['popular_routes'] = array_slice($stats['popular_routes'], 0, 5, true);
                }

                error_log("\nFinal Statistics:\n" . print_r($stats, true) . "\n", 3, $log_file);

            } catch (Exception $e) {
                error_log("Error in analytics: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 3, $log_file);
            }

            // Calculate return customer rate
            $unique_customers = array_unique(array_column($bookings, 'post_author'));
            $repeat_customers = array_diff_assoc(array_column($bookings, 'post_author'), $unique_customers);
            $stats['return_customers'] = count($repeat_customers);

            return $stats;
        }

        public function analytics_dashboard_page() {
            $stats = $this->get_booking_stats();
            ?>
            <div class="wrap wbtm-analytics-dashboard">
                <h1><?php _e('Bus Booking Analytics Dashboard', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
                
                <div class="wbtm-stats-grid">
                    <!-- Summary Cards -->
                    <div class="wbtm-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-tickets-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo esc_html($stats['total_bookings']); ?></h3>
                            <p><?php _e('Total Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                        </div>
                    </div>

                    <div class="wbtm-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo wc_price($stats['total_revenue']); ?></h3>
                            <p><?php _e('Total Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                        </div>
                    </div>

                    <div class="wbtm-stat-card">
                        <div class="stat-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo esc_html($stats['total_seats']); ?></h3>
                            <p><?php _e('Total Seats Booked', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="wbtm-charts-grid">
                    <div class="wbtm-chart-card">
                        <h2><?php _e('Popular Routes', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                        <canvas id="routesChart"></canvas>
                    </div>
                    
                    <div class="wbtm-chart-card">
                        <h2><?php _e('Monthly Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Advanced Analytics Section -->
                <div class="wbtm-advanced-stats">
                    <h2><?php _e('Advanced Analytics', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                    
                    <div class="wbtm-charts-grid">
                        <!-- Peak Hours Chart -->
                        <div class="wbtm-chart-card">
                            <h3><?php _e('Peak Booking Hours', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                            <canvas id="peakHoursChart"></canvas>
                        </div>

                        <!-- Weekly Pattern Chart -->
                        <div class="wbtm-chart-card">
                            <h3><?php _e('Weekly Booking Pattern', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                            <canvas id="weeklyPatternChart"></canvas>
                        </div>

                        <!-- Ticket Types Chart -->
                        <div class="wbtm-chart-card">
                            <h3><?php _e('Ticket Type Distribution', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                            <canvas id="ticketTypesChart"></canvas>
                        </div>

                        <!-- Bus Type Performance -->
                        <div class="wbtm-chart-card">
                            <h3><?php _e('Bus Type Performance', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                            <canvas id="busTypeChart"></canvas>
                        </div>
                    </div>

                    <!-- Customer Insights -->
                    <div class="wbtm-insight-cards">
                        <div class="wbtm-stat-card">
                            <h4><?php _e('Return Customer Rate', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                            <p>
                                <?php 
                                $return_rate = ($stats['total_bookings'] > 0) 
                                    ? round(($stats['return_customers'] / $stats['total_bookings']) * 100, 1) 
                                    : 0;
                                echo $return_rate . '%';
                                ?>
                            </p>
                        </div>

                        <div class="wbtm-stat-card">
                            <h4><?php _e('Average Occupancy Rate', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                            <p>
                                <?php 
                                $avg_occupancy = (!empty($stats['occupancy_rates'])) 
                                    ? round(array_sum($stats['occupancy_rates']) / count($stats['occupancy_rates']), 1) 
                                    : 0;
                                echo $avg_occupancy . '%';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            
            // Ensure data is properly sanitized for JavaScript
            $js_data = array(
                'peakHours' => !empty($stats['peak_hours']) ? $stats['peak_hours'] : array(),
                'ticketTypes' => !empty($stats['ticket_types']) ? $stats['ticket_types'] : array(),
                'busTypes' => !empty($stats['bus_types']) ? $stats['bus_types'] : array(),
                'weeklyStats' => !empty($stats['weekly_stats']) ? $stats['weekly_stats'] : array(),
                'occupancyRates' => !empty($stats['occupancy_rates']) ? $stats['occupancy_rates'] : array()
            );

            wp_localize_script('wbtm-analytics-js', 'wbtmAdvancedAnalytics', $js_data);
        }
    }
    new WBTM_Analytics_Dashboard();
}
