<?php
/**
 * @Author 		mage-people.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('WBTM_My_Account')) {
    class WBTM_My_Account {
        private $endpoint = 'bus-dashboard';

        public function __construct() {
            add_action('init', array($this, 'register_endpoint'));
            add_filter('woocommerce_get_query_vars', array($this, 'add_query_vars'));
            add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
            add_action('woocommerce_account_' . $this->endpoint . '_endpoint', array($this, 'endpoint_content'));
            add_action('wp_ajax_wbtm_my_account_search', array($this, 'ajax_search_orders'));
            add_action('wp_ajax_wbtm_get_order_details', array($this, 'ajax_get_order_details'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            
            // Flush rewrite rules once when this class is loaded
            add_action('init', array($this, 'maybe_flush_rewrites'), 999);
        }

        public function register_endpoint() {
            add_rewrite_endpoint($this->endpoint, EP_PAGES);
        }

        public function maybe_flush_rewrites() {
            if (get_transient('wbtm_flush_rewrites_my_account')) {
                return;
            }
            flush_rewrite_rules();
            set_transient('wbtm_flush_rewrites_my_account', true, DAY_IN_SECONDS);
        }

        public function add_query_vars($vars) {
            $vars[$this->endpoint] = $this->endpoint;
            return $vars;
        }

        public function add_account_menu_item($items) {
            $new_items = array();
            foreach ($items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'dashboard') {
                    $new_items[$this->endpoint] = esc_html__('Bus Bookings', 'bus-ticket-booking-with-seat-reservation');
                }
            }
            return $new_items;
        }

        public function enqueue_assets() {
            if (is_account_page()) {
                wp_enqueue_style('wbtm-my-account', WBTM_PLUGIN_URL . '/assets/frontend/wbtm_my_account.css', array(), time());
                wp_enqueue_script('wbtm-my-account', WBTM_PLUGIN_URL . '/assets/frontend/wbtm_my_account.js', array('jquery'), time(), true);
                wp_localize_script('wbtm-my-account', 'wbtm_my_account_vars', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('wbtm_my_account_nonce')
                ));
            }
        }

        public function endpoint_content() {
            $user_id = get_current_user_id();
            $orders = $this->get_bus_orders($user_id);
            $stats = $this->get_dashboard_stats($user_id);
            
            $template_path = WBTM_PLUGIN_DIR . '/templates/my-account/dashboard.php';
            if (file_exists($template_path)) {
                include $template_path;
            }
        }

        public function get_dashboard_stats($user_id) {
            $stats = array(
                'total_bookings' => 0,
                'total_tickets'  => 0,
                'completed'      => 0,
                'cancelled'      => 0,
                'refunded'       => 0,
            );

            // Define valid statuses to avoid counting junk/failed records
            $valid_statuses = array('completed', 'processing', 'on-hold', 'cancelled', 'refunded');

            $attendees = get_posts(array(
                'post_type'      => 'wbtm_bus_booking',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'   => 'wbtm_user_id',
                        'value' => $user_id,
                    ),
                    array(
                        'key'     => 'wbtm_order_status',
                        'value'   => $valid_statuses,
                        'compare' => 'IN'
                    )
                ),
            ));

            $order_ids = array();
            $active_order_ids = array();

            foreach ($attendees as $attendee) {
                $status = get_post_meta($attendee->ID, 'wbtm_order_status', true);
                $order_id = get_post_meta($attendee->ID, 'wbtm_order_id', true);
                
                if (!$order_id) continue;

                if (in_array($status, array('completed', 'processing', 'on-hold'))) {
                    $active_order_ids[] = $order_id;
                    $stats['total_tickets']++;
                }

                if ($status === 'completed') $stats['completed']++;
                elseif ($status === 'cancelled') $stats['cancelled']++;
                elseif ($status === 'refunded') $stats['refunded']++;
            }

            $stats['total_bookings'] = count(array_unique($active_order_ids));

            return $stats;
        }

        public function get_bus_orders($user_id, $search = '', $status = 'all') {
            $default_statuses = array('wc-completed', 'wc-processing', 'wc-on-hold');
            
            if ($status === 'completed') {
                $query_status = array('wc-completed');
            } elseif ($status === 'cancelled') {
                $query_status = array('wc-cancelled');
            } elseif ($status === 'refunded') {
                $query_status = array('wc-refunded');
            } else {
                $query_status = $default_statuses;
            }

            $args = array(
                'customer_id' => $user_id,
                'limit'       => -1,
                'status'      => $query_status,
            );

            if (!empty($search)) {
                $args['prefix'] = $search; // This is actually for order ID search in wc_get_orders
                // For more advanced search, we might need a custom query
            }

            $orders = wc_get_orders($args);
            $bus_orders = array();

            foreach ($orders as $order) {
                $is_bus_order = false;
                $booking_details = array();
                
                foreach ($order->get_items() as $item_id => $item) {
                    $bus_id = $item->get_meta('_wbtm_bus_id');
                    if ($bus_id) {
                        $is_bus_order = true;
                        $booking_details[] = array(
                            'item_id' => $item_id,
                            'bus_id'  => $bus_id,
                            'bus_name' => get_the_title($bus_id),
                            'journey_date' => $item->get_meta('_wbtm_bp_time'),
                        );
                    }
                }

                if ($is_bus_order) {
                    if (!empty($search)) {
                        // If searching by bus name
                        $match = false;
                        if (strpos((string)$order->get_id(), $search) !== false) $match = true;
                        foreach($booking_details as $detail) {
                            if (stripos($detail['bus_name'], $search) !== false) $match = true;
                        }
                        if (!$match) continue;
                    }

                    $bus_orders[] = array(
                        'order_id' => $order->get_id(),
                        'order_date' => $order->get_date_created()->date('Y-m-d'),
                        'status' => $order->get_status(),
                        'total' => $order->get_formatted_order_total(),
                        'bookings' => $booking_details
                    );
                }
            }

            return $bus_orders;
        }

        public function ajax_search_orders() {
            check_ajax_referer('wbtm_my_account_nonce', 'nonce');
            
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
            $user_id = get_current_user_id();
            $orders = $this->get_bus_orders($user_id, $search, $status);
            
            ob_start();
            $this->render_orders_table($orders);
            $html = ob_get_clean();
            
            wp_send_json_success($html);
        }

        public function render_orders_table($orders) {
            if (empty($orders)) {
                echo '<tr><td colspan="5" style="text-align:center;">' . esc_html__('No orders found.', 'bus-ticket-booking-with-seat-reservation') . '</td></tr>';
                return;
            }

            foreach ($orders as $order) {
                $status_name = wc_get_order_status_name($order['status']);
                $bus_details = array();
                foreach($order['bookings'] as $booking) {
                    $date_str = '';
                    if (!empty($booking['journey_date'])) {
                        $date_str = ' <span class="wbtm-journey-date">(' . date_i18n(get_option('date_format'), strtotime($booking['journey_date'])) . ')</span>';
                    }
                    $bus_details[] = '<div class="wbtm-bus-name-row">' . esc_html($booking['bus_name']) . $date_str . '</div>';
                }
                ?>
                <tr>
                    <td><strong>#<?php echo esc_html($order['order_id']); ?></strong></td>
                    <td><?php echo implode(' ', $bus_details); ?></td>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($order['order_date'])); ?></td>
                    <td><span class="wbtm-status status-<?php echo esc_attr($order['status']); ?>"><?php echo esc_html($status_name); ?></span></td>
                    <td class="wbtm-actions">
                        <button type="button" class="wbtm-btn btn-view open-wbtm-modal" data-order-id="<?php echo esc_attr($order['order_id']); ?>">
                            <span class="fas fa-eye"></span> <?php esc_html_e('View', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </button>
                        <?php if (class_exists('WBTM_Pro_Pdf')) : 
                            $pdf_url = WBTM_Pro_Pdf::get_pdf_url(array('wbtm_order_id' => $order['order_id']));
                            ?>
                            <a href="<?php echo esc_url($pdf_url); ?>" class="wbtm-btn btn-pdf" target="_blank">
                                <span class="fas fa-download"></span> <?php esc_html_e('Ticket', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }

        public function ajax_get_order_details() {
            check_ajax_referer('wbtm_my_account_nonce', 'nonce');
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            if (!$order_id) wp_send_json_error('Invalid Order ID');

            $order = wc_get_order($order_id);
            if (!$order || $order->get_customer_id() !== get_current_user_id()) {
                wp_send_json_error('Unauthorized');
            }

            // Get attendee bookings for this order
            $attendees = get_posts(array(
                'post_type'  => 'wbtm_bus_booking',
                'meta_query' => array(
                    array(
                        'key'   => 'wbtm_order_id',
                        'value' => $order_id,
                    ),
                ),
                'posts_per_page' => -1,
                'order' => 'ASC'
            ));

            ob_start();
            ?>
            <div class="wbtm-modal-details wbtm_style">
                <?php if (!empty($attendees)) : ?>
                    <?php foreach ($attendees as $attendee) : 
                        $a_id = $attendee->ID;
                    ?>
                        <div class="wbtm-attendee-card">
                            <div class="wbtm-details-row">
                                <?php if (class_exists('WBTM_Layout_Pro')) : ?>
                                    <div class="wbtm-details-col">
                                        <?php WBTM_Layout_Pro::service_info($a_id); ?>
                                    </div>
                                    <div class="wbtm-details-col">
                                        <?php WBTM_Layout_Pro::billing_info($a_id); ?>
                                    </div>
                                    <div class="wbtm-details-col">
                                        <h4><?php esc_html_e('Passenger Information', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                        <?php 
                                        WBTM_Layout_Pro::seat_info($a_id);
                                        do_action('wbtm_add_after_seat_info', $a_id);
                                        ?>
                                    </div>
                                <?php else : 
                                    $bus_id = get_post_meta($a_id, 'wbtm_bus_id', true);
                                    $seat = get_post_meta($a_id, 'wbtm_seat', true);
                                    $b_time = get_post_meta($a_id, 'wbtm_boarding_time', true);
                                    $price = get_post_meta($a_id, 'wbtm_bus_fare', true);
                                    $extra_services = get_post_meta($a_id, 'wbtm_extra_services', true);
                                    ?>
                                    <div class="wbtm-details-col" style="flex: 1;">
                                        <h4><?php esc_html_e('Passenger Information', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                        <ul class="mp_list">
                                            <li><strong><?php esc_html_e('Bus Name', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong> <?php echo get_the_title($bus_id); ?></li>
                                            <li><strong><?php esc_html_e('Seat No', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong> <?php echo esc_html($seat); ?></li>
                                            <li><strong><?php esc_html_e('Price', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong> <?php echo wc_price($price); ?></li>
                                            <li><strong><?php esc_html_e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong> <?php echo date_i18n(get_option('date_format'), strtotime($b_time)); ?></li>
                                            
                                            <?php if (!empty($extra_services) && is_array($extra_services)) : ?>
                                                <li class="wbtm-extra-services-wrapper" style="display: block; border-top: 1px dashed #ddd; margin-top: 10px; padding-top: 10px;">
                                                    <strong><?php esc_html_e('Extra Services', 'bus-ticket-booking-with-seat-reservation'); ?>:</strong>
                                                    <ul style="list-style: none; padding: 5px 0 0 10px; margin: 0;">
                                                        <?php foreach ($extra_services as $service) : ?>
                                                            <li style="display: flex; justify-content: space-between; border: none; padding: 2px 0;">
                                                                <span><?php echo esc_html($service['name']); ?> (<?php echo $service['qty']; ?>)</span>
                                                                <span><?php echo wc_price($service['price'] * $service['qty']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="wbtm-item-divider"></div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="wbtm-bus-item">
                        <p><?php esc_html_e('No detailed attendee information found for this order.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="wbtm-modal-footer">
                    <div class="wbtm-modal-total">
                        <span><?php esc_html_e('Total Paid:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                        <strong><?php echo $order->get_formatted_order_total(); ?></strong>
                    </div>
                </div>
            </div>
            <?php
            $html = ob_get_clean();
            wp_send_json_success($html);
        }
    }
    
    new WBTM_My_Account();
}
