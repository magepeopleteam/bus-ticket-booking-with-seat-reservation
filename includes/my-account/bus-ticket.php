<?php
if (!defined('ABSPATH')) {
    die;
}

/* 
* Ticket Panel
*/

/**
 * Register new endpoint to use inside My Account page.
 *
 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
 */

/**
 * Add new query var.
 *
 * @param array $vars
 * @return array
 */
// function register_my_session(){
//     if( ! session_id() ) {
//         session_start();
//     }
// }

// add_action('init', 'register_my_session');

function wbtm_myaccount_query_vars($vars)
{
    $vars[] = 'bus-panel';

    return $vars;
}

add_filter('query_vars', 'wbtm_myaccount_query_vars', 0);


/**
 * Custom help to add new items into an array after a selected item.
 *
 * @param array $items
 * @param array $new_items
 * @param string $after
 * @return array
 */
function wbtm_bus_panel_insert_after_helper($items, $new_items, $after)
{
    // Search for the item position and +1 since is after the selected item key.
    $position = array_search($after, array_keys($items)) + 1;

    // Insert the new item.
    $array = array_slice($items, 0, $position, true);
    $array += $new_items;
    $array += array_slice($items, $position, count($items) - $position, true);

    return $array;
}

/**
 * Insert the new endpoint into the My Account menu.
 *
 * @param array $items
 * @return array
 */
function wbtm_bus_panel_menu_items($items)
{
    $new_items = array();
    $new_items['bus-panel'] = mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');

    // Add the new item after `orders`.
    return wbtm_bus_panel_insert_after_helper($items, $new_items, 'orders');
}

add_filter('woocommerce_account_menu_items', 'wbtm_bus_panel_menu_items');

/**
 * Endpoint HTML content.
 */
function wbtm_bus_panel_endpoint_content()
{
    global $magepdf;

    $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
    $user_id = get_current_user_id();
    $myaccount_link = get_permalink(wc_get_page_id('myaccount'));

    if ($mode === 'ticket-exchange') {
        if (isset($_GET['order_id'])) {
            do_action('wbtm_ticket_exchange', $_GET['order_id']);
        }
        return;
    }

    ob_start();

    if (isset($_SESSION['msg'])) {
        echo '<p class="mefs-notification">' . $_SESSION['msg'] . '</p>';
        // Destroy Message
        unset($_SESSION['msg']);
    }

    // Get tickets
    $meta_query = array(
        'relation' => 'AND',
        array(
            'key' => 'wbtm_user_id',
            'value' => $user_id,
            'compare' => '='
        ),
        array(
            'relation' => 'OR',
            array(
                'key' => 'wbtm_status',
                'value' => 1,
                'compare' => '='
            ),
            array(
                'key' => 'wbtm_status',
                'value' => 2,
                'compare' => '='
            ),
        ),
    );

    $args = array(
        'post_type' => 'wbtm_bus_booking',
        'posts_per_page' => -1,
        'order' => 'DESC',
        'meta_query' => $meta_query
    );
    $passengers = new WP_Query($args);

    // Is pdf plguin active
    $is_show_ticket = is_plugin_active('magepeople-pdf-support-master/mage-pdf.php') ? true : false;

    echo '<div class="wbtm_myaccount_wrapper">';

    ?>

    <table>
        <thead>
        <tr>
            <th><?php _e('Order no', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <th><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Name', 'bus-ticket-booking-with-seat-reservation') ?></th>
            <th><?php _e('Order Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <th><?php _e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <th><?php _e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <th><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <th><?php _e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <?php if ($is_show_ticket) : ?>
                <th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        while ($passengers->have_posts()) :
            $passengers->the_post();
            $id = get_the_ID();
            $order_id = get_post_meta($id, 'wbtm_order_id', true);
            $order = wc_get_order($order_id);
            $booking_date = get_post_meta($id, 'wbtm_booking_date', true);
            $booking_date = explode(' ', $booking_date);
            $download_url = $is_show_ticket ? $magepdf->get_invoice_ajax_url(array('order_id' => $order_id)) : '';
            ?>
            <tr>
                <td><a href="<?php echo $myaccount_link . 'view-order/' . $order_id; ?>">#<?php echo $order_id; ?></a>
                </td>
                <td><?php echo get_the_title(get_post_meta($id, 'wbtm_bus_id', true)); ?></td>
                <td><?php echo mage_wp_date($booking_date[0]) . ' ' . mage_wp_time($booking_date[1]); ?></td>
                <td><?php echo mage_wp_date(get_post_meta($id, 'wbtm_journey_date', true)) . ' ' . mage_wp_time(get_post_meta($id, 'wbtm_bus_start', true)); ?></td>
                <td><?php echo get_post_meta($id, 'wbtm_seat', true); ?></td>
                <td><?php echo get_post_meta($id, 'wbtm_pickpoint', true); ?></td>
                <td>
                    <?php
                    if ($order) {
                        echo ucfirst($order->get_status());
                    }
                    ?>
                </td>

                <?php if ($is_show_ticket) : ?>
                    <td>
                        <?php if($order) : ?>
                            <a class="wbtm-btn order-table-btn"
                           href="<?php echo $order->get_view_order_url(); ?>"><?php _e('Show Order', 'bus-ticket-booking-with-seat-reservation') ?></a>
                        <?php endif ?>
                        <a class="wbtm-btn order-table-btn"
                           href="<?php echo $download_url; ?>"><?php _e('Download Ticket', 'bus-ticket-booking-with-seat-reservation') ?></a>
                        <?php do_action('wbtm_bus_panel_action', $order_id) ?>
                    </td>
                <?php endif; ?>

            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php

    echo '</div>';
    $output = ob_get_contents();
}

add_action('woocommerce_account_bus-panel_endpoint', 'wbtm_bus_panel_endpoint_content');

/*
 * Change endpoint title.
 *
 * @param string $title
 * @return string
 */
function wbtm_bus_panel_endpoint_title($title)
{
    global $wp_query;

    $is_endpoint = isset($wp_query->query_vars['bus-panel']);

    if ($is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
        // New page title.
        $title = mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');

        remove_filter('the_title', 'wbtm_bus_panel_endpoint_title');
    }

    return $title;
}

add_filter('the_title', 'wbtm_bus_panel_endpoint_title');
