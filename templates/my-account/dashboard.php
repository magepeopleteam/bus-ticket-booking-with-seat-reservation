<?php
/**
 * My Account Bus Bookings Dashboard
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wbtm-dashboard-container">
    <div class="wbtm-dashboard-header">
        <h2><?php esc_html_e('My Bus Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
        
        <div class="wbtm-search-box">
            <span class="wbtm-search-icon fas fa-search"></span>
            <input type="text" id="wbtm-order-search" class="wbtm-search-input" placeholder="<?php esc_attr_e('Search Order ID or Bus Name...', 'bus-ticket-booking-with-seat-reservation'); ?>">
        </div>
    </div>

    <div class="wbtm-stats-cards">
        <div class="wbtm-stat-card wbtm-stat-card-clickable" data-filter="all">
            <span class="wbtm-stat-icon fas fa-shopping-cart" style="color: #2271b1;"></span>
            <div class="wbtm-stat-info">
                <span class="wbtm-stat-label"><?php esc_html_e('Total Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-stat-value"><?php echo esc_html($stats['total_bookings']); ?></span>
            </div>
        </div>
        <div class="wbtm-stat-card wbtm-stat-card-clickable" data-filter="all">
            <span class="wbtm-stat-icon fas fa-ticket-alt" style="color: #6366f1;"></span>
            <div class="wbtm-stat-info">
                <span class="wbtm-stat-label"><?php esc_html_e('Total Tickets', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-stat-value"><?php echo esc_html($stats['total_tickets']); ?></span>
            </div>
        </div>
        <div class="wbtm-stat-card wbtm-stat-card-clickable" data-filter="completed">
            <span class="wbtm-stat-icon fas fa-check-circle" style="color: #10b981;"></span>
            <div class="wbtm-stat-info">
                <span class="wbtm-stat-label"><?php esc_html_e('Completed', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-stat-value"><?php echo esc_html($stats['completed']); ?></span>
            </div>
        </div>
        <div class="wbtm-stat-card wbtm-stat-card-clickable" data-filter="cancelled">
            <span class="wbtm-stat-icon fas fa-times-circle" style="color: #ef4444;"></span>
            <div class="wbtm-stat-info">
                <span class="wbtm-stat-label"><?php esc_html_e('Cancellations', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-stat-value"><?php echo esc_html($stats['cancelled']); ?></span>
            </div>
        </div>
        <div class="wbtm-stat-card wbtm-stat-card-clickable" data-filter="refunded">
            <span class="wbtm-stat-icon fas fa-undo-alt" style="color: #f59e0b;"></span>
            <div class="wbtm-stat-info">
                <span class="wbtm-stat-label"><?php esc_html_e('Refunds', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-stat-value"><?php echo esc_html($stats['refunded']); ?></span>
            </div>
        </div>
    </div>

    <table class="wbtm-orders-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Order ID', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php esc_html_e('Bus Name', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php esc_html_e('Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                <th><?php esc_html_e('Actions', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $this->render_orders_table($orders); ?>
        </tbody>
    </table>

    <!-- Modal for Order Details -->
    <div id="wbtm-order-modal" class="wbtm-modal">
        <div class="wbtm-modal-content">
            <div class="wbtm-modal-header">
                <h3><?php esc_html_e('Booking Details', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <span class="wbtm-close">&times;</span>
            </div>
            <div id="wbtm-modal-body" class="wbtm-modal-body">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>
