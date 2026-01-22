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
				if ($hook != 'wbtm_bus_page_wbtm-analytics') {
					return;
				}
				wp_enqueue_style('wbtm-analytics-style', plugins_url('/assets/css/wbtm-analytics.css', dirname(__FILE__)));
				wp_enqueue_script('chart-js', plugins_url('/assets/js/chart.js', dirname(__FILE__)), array(), '4.5.1', true);
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
			$stats = array(
				'total_bookings' => 0,
				'total_revenue' => 0,
				'gross_revenue' => 0,
				'net_revenue' => 0,
				'total_refunds' => 0,
				'total_discounts' => 0,
				'total_cancellations' => 0,
				'cancelled_revenue_loss' => 0,
				'coupon_usage_count' => 0,
				'total_coupon_discount' => 0,
				'total_seats' => 0,
				'popular_routes' => array(),
				'monthly_revenue' => array(),
				'bus_occupancy' => 0,
				'peak_hours' => array(),
				'ticket_types' => array(),
				'bus_types' => array(),
				'return_customers' => 0,
				'occupancy_rates' => array(),
				'weekly_stats' => array(),
				'profit_margin' => 0,
				'avg_order_value' => 0
			);
		try {
			// Build query args with filters
			$args = array(
				'post_type' => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'post_status' => 'any'
			);
			
			// Apply meta query filters
			$meta_query = array('relation' => 'AND');
			
			// Filter by date range
			if (!empty($_GET['date_from'])) {
				$meta_query[] = array(
					'key' => 'wbtm_booking_date',
					'value' => sanitize_text_field($_GET['date_from']),
					'compare' => '>=',
					'type' => 'DATE'
				);
			}
			if (!empty($_GET['date_to'])) {
				$meta_query[] = array(
					'key' => 'wbtm_booking_date',
					'value' => sanitize_text_field($_GET['date_to']),
					'compare' => '<=',
					'type' => 'DATE'
				);
			}
			
			// Filter by bus
			if (!empty($_GET['bus_id'])) {
				$meta_query[] = array(
					'key' => 'wbtm_bus_id',
					'value' => intval($_GET['bus_id']),
					'compare' => '='
				);
			}
			
			// Filter by route
			if (!empty($_GET['route'])) {
				$route_parts = explode('|', sanitize_text_field($_GET['route']));
				if (count($route_parts) == 2) {
					$meta_query[] = array(
						'key' => 'wbtm_boarding_point',
						'value' => $route_parts[0],
						'compare' => '='
					);
					$meta_query[] = array(
						'key' => 'wbtm_dropping_point',
						'value' => $route_parts[1],
						'compare' => '='
					);
				}
			}
			
			if (count($meta_query) > 1) {
				$args['meta_query'] = $meta_query;
			}
			
			$bookings = get_posts($args);
				// Initialize monthly revenue array for last 6 months
				for ($i = 5; $i >= 0; $i--) {
					$month = gmdate('M Y', strtotime("-$i months"));
					$stats['monthly_revenue'][$month] = 0;
				}
				
			// Track processed orders to avoid counting the same order multiple times
			$processed_orders = array();
			$cancelled_orders = array();
			
			foreach ($bookings as $booking) {
				$order_id = get_post_meta($booking->ID, 'wbtm_order_id', true);
				if ($order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					$order_status = $order->get_status();
					
					// Apply order status filter if set
					if (!empty($_GET['order_status'])) {
						$filter_status = sanitize_text_field($_GET['order_status']);
						if ($order_status != $filter_status && 'wc-' . $order_status != $filter_status) {
							continue; // Skip this order if it doesn't match filter
						}
					}
					
					// Track cancelled/refunded orders
					if (in_array($order_status, array('cancelled', 'refunded', 'wc-cancelled', 'wc-refunded', 'failed', 'wc-failed'))) {
							if (!in_array($order_id, $cancelled_orders)) {
								$cancelled_orders[] = $order_id;
								$stats['total_cancellations']++;
								$cancelled_amount = $order->get_total();
								$stats['cancelled_revenue_loss'] += $cancelled_amount;
							}
							continue; // Skip to next booking
						}
						
						// Include both processing and completed orders
						if (in_array($order_status, array('processing', 'completed', 'wc-processing', 'wc-completed'))) {
							// Only count each order once to avoid inflating revenue
							if (!in_array($order_id, $processed_orders)) {
								$processed_orders[] = $order_id;
								
								// Get gross total (before discounts)
								$subtotal = 0;
								foreach ($order->get_items() as $item) {
									$subtotal += $item->get_subtotal();
								}
								$stats['gross_revenue'] += $subtotal;
								
								// Get order total (after discounts)
								$total = $order->get_total();
								
								// Calculate discount amount
								$discount_total = $order->get_discount_total();
								$stats['total_discounts'] += $discount_total;
								
								// Check for coupon usage
								$coupons_used = $order->get_coupon_codes();
								if (!empty($coupons_used)) {
									$stats['coupon_usage_count']++;
									$stats['total_coupon_discount'] += $discount_total;
								}
								
								// Subtract refunds if any
								$refunded_amount = $order->get_total_refunded();
								if ($refunded_amount > 0) {
									$stats['total_refunds'] += $refunded_amount;
								}
								
								$net_total = $total - $refunded_amount;
								
								// Only add if net total is positive
								if ($net_total > 0) {
									$stats['total_bookings']++;
									$stats['total_revenue'] += $net_total;
									$stats['net_revenue'] += $net_total;
									$month = gmdate('M Y', strtotime($order->get_date_created()));
									if (isset($stats['monthly_revenue'][$month])) {
										$stats['monthly_revenue'][$month] += $net_total;
									}
								}
							} else {
								// Order already counted, just increment booking count
								$stats['total_bookings']++;
							}
								
								// Add route data only for valid orders
								$bp = get_post_meta($booking->ID, 'wbtm_boarding_point', true);
								$dp = get_post_meta($booking->ID, 'wbtm_dropping_point', true);
								if ($bp && $dp) {
									$route = $bp . ' - ' . $dp;
									if (!isset($stats['popular_routes'][$route])) {
										$stats['popular_routes'][$route] = 0;
									}
									$stats['popular_routes'][$route]++;
								}
								// Fix: Count seats properly
								// First try to get seat numbers
								$seat_numbers = get_post_meta($booking->ID, 'wbtm_seat', true);
								if ($seat_numbers) {
									// If seat numbers exist, count them
									if (is_array($seat_numbers)) {
										$stats['total_seats'] += count($seat_numbers);
									} else {
										$stats['total_seats'] += 1; // Single seat
									}
								} else {
									// Fallback to seat quantity
									$seat_qty = (int)get_post_meta($booking->ID, 'wbtm_seat_qty', true);
									if ($seat_qty > 0) {
										$stats['total_seats'] += $seat_qty;
									} else {
										// If no explicit quantity, count as 1 seat per booking
										$stats['total_seats'] += 1;
									}
								}
								// Calculate peak booking hours
								$booking_time = get_post_time('H', false, $booking->ID);
								if (!isset($stats['peak_hours'][$booking_time])) {
									$stats['peak_hours'][$booking_time] = 0;
								}
								$stats['peak_hours'][$booking_time]++;
								// Track ticket types
								$ticket_type = get_post_meta($booking->ID, 'wbtm_ticket', true);
								if (!isset($stats['ticket_types'][$ticket_type])) {
									$stats['ticket_types'][$ticket_type] = 0;
								}
								$stats['ticket_types'][$ticket_type]++;
								// Track bus types and revenue (use net_total for revenue)
								$bus_id = get_post_meta($booking->ID, 'wbtm_bus_id', true);
								$bus_type = get_post_meta($bus_id, 'wbtm_bus_type', true);
								if (!isset($stats['bus_types'][$bus_type])) {
									$stats['bus_types'][$bus_type] = array(
										'revenue' => 0,
										'bookings' => 0,
										'seats' => 0
									);
								}
								$stats['bus_types'][$bus_type]['bookings']++;
								// Use net_total instead of total for accurate revenue tracking
								if (isset($net_total)) {
									$stats['bus_types'][$bus_type]['revenue'] += $net_total;
								}
								// Calculate occupancy rates
								$total_seats = get_post_meta($bus_id, 'wbtm_total_seat', true);
								if ($total_seats) {
									$occupancy = ($total_seats > 0) ? ($stats['total_seats'] / $total_seats) * 100 : 0;
									$stats['occupancy_rates'][$bus_id] = $occupancy;
								}
								// Weekly booking patterns
								$booking_day = gmdate('w', strtotime($booking->post_date));
								if (!isset($stats['weekly_stats'][$booking_day])) {
									$stats['weekly_stats'][$booking_day] = 0;
								}
								$stats['weekly_stats'][$booking_day]++;
							}
						}
					}
				}
					// Sort and limit popular routes
					if (!empty($stats['popular_routes'])) {
						arsort($stats['popular_routes']);
						$stats['popular_routes'] = array_slice($stats['popular_routes'], 0, 5, true);
					}
			} catch (Exception $e) {
			}
			// Calculate return customer rate
			$unique_customers = array_unique(array_column($bookings, 'post_author'));
			$repeat_customers = array_diff_assoc(array_column($bookings, 'post_author'), $unique_customers);
			$stats['return_customers'] = count($repeat_customers);
			
			// Calculate average order value
			$total_orders = count($processed_orders);
			if ($total_orders > 0) {
				$stats['avg_order_value'] = $stats['net_revenue'] / $total_orders;
			}
			
			// Calculate profit margin percentage (assuming gross - discounts - refunds = net)
			if ($stats['gross_revenue'] > 0) {
				$stats['profit_margin'] = (($stats['net_revenue'] / $stats['gross_revenue']) * 100);
			}
			
			return $stats;
		}
		public function analytics_dashboard_page() {
			$stats = $this->get_booking_stats();
			?>
                <div class="wrap wbtm-analytics-dashboard">
                    <h1><?php esc_html_e('Bus Booking Analytics Dashboard', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
                    
                    <!-- Analytics Filters Section -->
                    <div class="wbtm-analytics-filters">
                        <h2 class="wbtm-filter-title">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e('Filter Analytics', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </h2>
                        <form id="wbtm-analytics-filter-form" method="get" action="">
                            <input type="hidden" name="post_type" value="wbtm_bus">
                            <input type="hidden" name="page" value="wbtm-analytics">
                            
                            <div class="wbtm-filter-grid">
                                <!-- Date Range Filter -->
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_date_from">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <?php esc_html_e('From Date', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <input type="date" 
                                           id="wbtm_date_from" 
                                           name="date_from" 
                                           class="wbtm-filter-input"
                                           value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                                </div>
                                
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_date_to">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <?php esc_html_e('To Date', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <input type="date" 
                                           id="wbtm_date_to" 
                                           name="date_to" 
                                           class="wbtm-filter-input"
                                           value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                                </div>
                                
                                <!-- Bus Filter -->
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_bus_filter">
                                        <span class="dashicons dashicons-bus"></span>
                                        <?php esc_html_e('Select Bus', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <select id="wbtm_bus_filter" name="bus_id" class="wbtm-filter-select">
                                        <option value=""><?php esc_html_e('All Buses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php
                                        $buses = get_posts(array(
                                            'post_type' => 'wbtm_bus',
                                            'posts_per_page' => -1,
                                            'post_status' => 'publish'
                                        ));
                                        foreach ($buses as $bus) {
                                            $selected = (isset($_GET['bus_id']) && $_GET['bus_id'] == $bus->ID) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($bus->ID) . '" ' . $selected . '>' . esc_html($bus->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Route Filter -->
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_route_filter">
                                        <span class="dashicons dashicons-location"></span>
                                        <?php esc_html_e('Route', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <select id="wbtm_route_filter" name="route" class="wbtm-filter-select">
                                        <option value=""><?php esc_html_e('All Routes', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <?php
                                        // Get unique routes
                                        global $wpdb;
                                        $routes = $wpdb->get_results("
                                            SELECT DISTINCT 
                                                CONCAT(pm1.meta_value, ' - ', pm2.meta_value) as route_name,
                                                pm1.meta_value as boarding,
                                                pm2.meta_value as dropping
                                            FROM {$wpdb->postmeta} pm1
                                            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                                            WHERE pm1.meta_key = 'wbtm_boarding_point' 
                                            AND pm2.meta_key = 'wbtm_dropping_point'
                                            AND pm1.meta_value != '' 
                                            AND pm2.meta_value != ''
                                            ORDER BY pm1.meta_value
                                        ");
                                        foreach ($routes as $route) {
                                            $route_value = $route->boarding . '|' . $route->dropping;
                                            $selected = (isset($_GET['route']) && $_GET['route'] == $route_value) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($route_value) . '" ' . $selected . '>' . esc_html($route->route_name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- Order Status Filter -->
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_status_filter">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php esc_html_e('Order Status', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <select id="wbtm_status_filter" name="order_status" class="wbtm-filter-select">
                                        <option value=""><?php esc_html_e('All Statuses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="completed" <?php selected(isset($_GET['order_status']) ? $_GET['order_status'] : '', 'completed'); ?>><?php esc_html_e('Completed', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="processing" <?php selected(isset($_GET['order_status']) ? $_GET['order_status'] : '', 'processing'); ?>><?php esc_html_e('Processing', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="cancelled" <?php selected(isset($_GET['order_status']) ? $_GET['order_status'] : '', 'cancelled'); ?>><?php esc_html_e('Cancelled', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="refunded" <?php selected(isset($_GET['order_status']) ? $_GET['order_status'] : '', 'refunded'); ?>><?php esc_html_e('Refunded', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    </select>
                                </div>
                                
                                <!-- Quick Date Presets -->
                                <div class="wbtm-filter-group">
                                    <label for="wbtm_quick_date">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php esc_html_e('Quick Select', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    </label>
                                    <select id="wbtm_quick_date" class="wbtm-filter-select wbtm-quick-date-select">
                                        <option value=""><?php esc_html_e('Select Period', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="today"><?php esc_html_e('Today', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="yesterday"><?php esc_html_e('Yesterday', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="last7days"><?php esc_html_e('Last 7 Days', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="last30days"><?php esc_html_e('Last 30 Days', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="thismonth"><?php esc_html_e('This Month', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="lastmonth"><?php esc_html_e('Last Month', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                        <option value="thisyear"><?php esc_html_e('This Year', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="wbtm-filter-actions">
                                <button type="submit" class="button button-primary wbtm-filter-btn">
                                    <span class="dashicons dashicons-search"></span>
                                    <?php esc_html_e('Apply Filters', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </button>
                                <a href="<?php echo admin_url('edit.php?post_type=wbtm_bus&page=wbtm-analytics'); ?>" class="button wbtm-reset-btn">
                                    <span class="dashicons dashicons-image-rotate"></span>
                                    <?php esc_html_e('Reset Filters', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </a>
                                <button type="button" class="button wbtm-export-btn" id="wbtm_export_analytics">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Export to CSV', 'bus-ticket-booking-with-seat-reservation'); ?>
                                </button>
                            </div>
                            
                            <!-- Active Filters Display -->
                            <?php if (!empty($_GET['date_from']) || !empty($_GET['date_to']) || !empty($_GET['bus_id']) || !empty($_GET['route']) || !empty($_GET['order_status'])): ?>
                            <div class="wbtm-active-filters">
                                <strong><?php esc_html_e('Active Filters:', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
                                <?php if (!empty($_GET['date_from'])): ?>
                                    <span class="wbtm-filter-badge">
                                        <?php echo esc_html__('From:', 'bus-ticket-booking-with-seat-reservation') . ' ' . esc_html($_GET['date_from']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($_GET['date_to'])): ?>
                                    <span class="wbtm-filter-badge">
                                        <?php echo esc_html__('To:', 'bus-ticket-booking-with-seat-reservation') . ' ' . esc_html($_GET['date_to']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($_GET['bus_id'])): ?>
                                    <span class="wbtm-filter-badge">
                                        <?php echo esc_html__('Bus:', 'bus-ticket-booking-with-seat-reservation') . ' ' . esc_html(get_the_title($_GET['bus_id'])); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($_GET['route'])): ?>
                                    <span class="wbtm-filter-badge">
                                        <?php 
                                        $route_parts = explode('|', $_GET['route']);
                                        echo esc_html__('Route:', 'bus-ticket-booking-with-seat-reservation') . ' ' . esc_html($route_parts[0] . ' - ' . $route_parts[1]); 
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($_GET['order_status'])): ?>
                                    <span class="wbtm-filter-badge">
                                        <?php echo esc_html__('Status:', 'bus-ticket-booking-with-seat-reservation') . ' ' . esc_html(ucfirst($_GET['order_status'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Financial Overview Section -->
                    <h2 class="wbtm-section-title"><?php esc_html_e('Financial Overview', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                    <div class="wbtm-stats-grid wbtm-stats-grid-4">
                        <!-- Gross Revenue Card -->
                        <div class="wbtm-stat-card wbtm-card-blue">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-chart-line"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['gross_revenue'])); ?></h3>
                                <p><?php esc_html_e('Gross Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Before discounts', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Net Revenue Card -->
                        <div class="wbtm-stat-card wbtm-card-success">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-money-alt"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['net_revenue'])); ?></h3>
                                <p><?php esc_html_e('Net Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('After all deductions', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Total Discounts Card -->
                        <div class="wbtm-stat-card wbtm-card-warning">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-tag"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['total_discounts'])); ?></h3>
                                <p><?php esc_html_e('Total Discounts', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle">
                                    <?php 
                                    $discount_percent = $stats['gross_revenue'] > 0 ? ($stats['total_discounts'] / $stats['gross_revenue']) * 100 : 0;
                                    echo sprintf(esc_html__('%s%% of gross', 'bus-ticket-booking-with-seat-reservation'), number_format($discount_percent, 1)); 
                                    ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Total Refunds Card -->
                        <div class="wbtm-stat-card wbtm-card-danger">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-undo"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['total_refunds'])); ?></h3>
                                <p><?php esc_html_e('Total Refunds', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Refunded amount', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coupon & Cancellation Stats -->
                    <h2 class="wbtm-section-title"><?php esc_html_e('Coupons & Cancellations', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                    <div class="wbtm-stats-grid wbtm-stats-grid-4">
                        <!-- Coupon Usage Card -->
                        <div class="wbtm-stat-card wbtm-card-purple">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-tickets"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo esc_html($stats['coupon_usage_count']); ?></h3>
                                <p><?php esc_html_e('Orders with Coupons', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle">
                                    <?php 
                                    $coupon_rate = $stats['total_bookings'] > 0 ? ($stats['coupon_usage_count'] / $stats['total_bookings']) * 100 : 0;
                                    echo sprintf(esc_html__('%s%% usage rate', 'bus-ticket-booking-with-seat-reservation'), number_format($coupon_rate, 1)); 
                                    ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Coupon Discount Amount Card -->
                        <div class="wbtm-stat-card wbtm-card-orange">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-megaphone"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['total_coupon_discount'])); ?></h3>
                                <p><?php esc_html_e('Coupon Discounts', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Total saved by customers', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Cancellations Card -->
                        <div class="wbtm-stat-card wbtm-card-red">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-dismiss"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo esc_html($stats['total_cancellations']); ?></h3>
                                <p><?php esc_html_e('Cancellations', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Cancelled orders', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Cancelled Revenue Loss Card -->
                        <div class="wbtm-stat-card wbtm-card-dark-red">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['cancelled_revenue_loss'])); ?></h3>
                                <p><?php esc_html_e('Lost Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('From cancellations', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <h2 class="wbtm-section-title"><?php esc_html_e('Performance Metrics', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                    <div class="wbtm-stats-grid wbtm-stats-grid-4">
                        <!-- Total Bookings Card -->
                        <div class="wbtm-stat-card wbtm-card-teal">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-tickets-alt"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo esc_html($stats['total_bookings']); ?></h3>
                                <p><?php esc_html_e('Total Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Completed orders', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Average Order Value Card -->
                        <div class="wbtm-stat-card wbtm-card-indigo">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-cart"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo wp_kses_post(wc_price($stats['avg_order_value'])); ?></h3>
                                <p><?php esc_html_e('Avg Order Value', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Per order', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Profit Margin Card -->
                        <div class="wbtm-stat-card wbtm-card-green">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-chart-area"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo number_format($stats['profit_margin'], 1); ?>%</h3>
                                <p><?php esc_html_e('Profit Margin', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('Net vs Gross', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                        
                        <!-- Total Seats Booked Card -->
                        <div class="wbtm-stat-card wbtm-card-cyan">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-groups"></span>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo esc_html($stats['total_seats']); ?></h3>
                                <p><?php esc_html_e('Total Seats Booked', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                <small class="stat-subtitle"><?php esc_html_e('All passengers', 'bus-ticket-booking-with-seat-reservation'); ?></small>
                            </div>
                        </div>
                    </div>
                    <!-- Charts Section -->
                    <div class="wbtm-charts-grid">
                        <div class="wbtm-chart-card">
                            <h2><?php esc_html_e('Popular Routes', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                            <canvas id="routesChart"></canvas>
                        </div>
                        <div class="wbtm-chart-card">
                            <h2><?php esc_html_e('Monthly Revenue', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    <!-- Advanced Analytics Section -->
                    <div class="wbtm-advanced-stats">
                        <h2><?php esc_html_e('Advanced Analytics', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                        <div class="wbtm-charts-grid">
                            <!-- Peak Hours Chart -->
                            <div class="wbtm-chart-card">
                                <h3><?php esc_html_e('Peak Booking Hours', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                            <!-- Weekly Pattern Chart -->
                            <div class="wbtm-chart-card">
                                <h3><?php esc_html_e('Weekly Booking Pattern', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                <canvas id="weeklyPatternChart"></canvas>
                            </div>
                            <!-- Ticket Types Chart -->
                            <div class="wbtm-chart-card">
                                <h3><?php esc_html_e('Ticket Type Distribution', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                <canvas id="ticketTypesChart"></canvas>
                            </div>
                            <!-- Bus Type Performance -->
                            <div class="wbtm-chart-card">
                                <h3><?php esc_html_e('Bus Type Performance', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                <canvas id="busTypeChart"></canvas>
                            </div>
                        </div>
                        <!-- Customer Insights -->
                        <div class="wbtm-insight-cards">
                            <div class="wbtm-stat-card">
                                <h4><?php esc_html_e('Return Customer Rate', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                <p>
									<?php
										$return_rate = ($stats['total_bookings'] > 0)
											? round(($stats['return_customers'] / $stats['total_bookings']) * 100, 1)
											: 0;
										echo esc_html($return_rate . '%');
									?>
                                </p>
                            </div>
                            <div class="wbtm-stat-card">
                                <h4><?php esc_html_e('Average Occupancy Rate', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                <p>
									<?php
										$avg_occupancy = (!empty($stats['occupancy_rates']))
											? round(array_sum($stats['occupancy_rates']) / count($stats['occupancy_rates']), 1)
											: 0;
										echo esc_html($avg_occupancy . '%');
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
