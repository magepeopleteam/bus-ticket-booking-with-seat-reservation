<?php
	/*
	* @Author 		engr.sumonazma@gmail.com
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Booking_List')) {
		/**
		 * Admin "Booking List" screen — a per-seat booking grid over the wbtm_bus_booking
		 * CPT (one post per booked seat, see WBTM_Woocommerce::add_billing_data()).
		 *
		 * Always registered in the free plugin. Mirrors the sibling rental plugin's
		 * free-tier "Bookings" teaser pattern:
		 *   - Stats block and the filter/search bar are fully blurred behind a PRO
		 *     overlay in free installs (no real data rendered behind the blur).
		 *   - "View" / "Change Status" row actions are locked with a mini PRO tag.
		 *   - Only Delete (AJAX + confirm modal) actually works in free installs.
		 *   - When WBTM_Functions::is_pro_active() is true, the stats/filters unlock
		 *     with real data and become functional in place — no separate Pro page.
		 */
		class WBTM_Booking_List {
			const PAGE_SLUG = 'wbtm_booking_list';
			const PER_PAGE  = 20;

			public function __construct() {
				add_action('admin_menu', array($this, 'register_menu'));
				add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
				add_action('wp_ajax_wbtm_bkl_delete', array($this, 'ajax_delete'));
				add_action('wp_ajax_wbtm_bkl_change_status', array($this, 'ajax_change_status'));
				add_action('wp_ajax_wbtm_bkl_bulk_change_status', array($this, 'ajax_bulk_change_status'));
				add_action('wp_ajax_wbtm_bkl_add_note', array($this, 'ajax_add_note'));
				add_action('admin_post_wbtm_bkl_export_csv', array($this, 'handle_export_csv'));
			}

			public function register_menu() {
				add_submenu_page(
					'edit.php?post_type=wbtm_bus',
					esc_html__('Booking List', 'bus-ticket-booking-with-seat-reservation'),
					esc_html__('Booking List', 'bus-ticket-booking-with-seat-reservation'),
					'manage_options',
					self::PAGE_SLUG,
					array($this, 'render_page')
				);
			}

			private function is_bookings_screen($hook) {
				return is_string($hook) && false !== strpos($hook, self::PAGE_SLUG);
			}

			public function enqueue_assets($hook) {
				if (!$this->is_bookings_screen($hook)) {
					return;
				}
				wp_enqueue_style('dashicons');
				// Versioned by filemtime() rather than the static WBTM_VERSION constant so
				// browsers never serve a stale copy of these two files across edits — the
				// same approach the rental plugin's equivalent free-tier screen uses.
				$css_path = WBTM_PLUGIN_DIR . '/assets/admin/css/wbtm-booking-list.css';
				$js_path  = WBTM_PLUGIN_DIR . '/assets/admin/js/wbtm-booking-list.js';
				wp_enqueue_style('wbtm-booking-list', WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-booking-list.css', array(), file_exists($css_path) ? filemtime($css_path) : WBTM_VERSION);
				// Depends on wbtm-toast (registered globally in WBTM_Global_File_Load::admin_enqueue())
				// so window.wbtmToast is guaranteed defined before this file's click handlers run.
				wp_enqueue_script('wbtm-booking-list', WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-booking-list.js', array('jquery', 'wbtm-toast'), file_exists($js_path) ? filemtime($js_path) : WBTM_VERSION, true);
				wp_localize_script('wbtm-booking-list', 'wbtmBookingList', array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('wbtm_bkl_actions'),
					'i18n'    => array(
						'proOnly'     => esc_html__('This is a PRO feature. Upgrade to unlock it.', 'bus-ticket-booking-with-seat-reservation'),
						'deleted'     => esc_html__('Booking deleted.', 'bus-ticket-booking-with-seat-reservation'),
						'deleteError' => esc_html__('Could not delete the booking.', 'bus-ticket-booking-with-seat-reservation'),
						'statusUpdated' => esc_html__('Status updated.', 'bus-ticket-booking-with-seat-reservation'),
						'statusError'   => esc_html__('Could not update the status.', 'bus-ticket-booking-with-seat-reservation'),
						'chooseBulkAction'  => esc_html__('Please choose a bulk action.', 'bus-ticket-booking-with-seat-reservation'),
						'selectAtLeastOne'  => esc_html__('Please select at least one booking.', 'bus-ticket-booking-with-seat-reservation'),
						'selectedBookings'  => esc_html__('selected booking(s)', 'bus-ticket-booking-with-seat-reservation'),
						'confirmBulkStatus' => esc_html__('Change status of %d booking(s)?', 'bus-ticket-booking-with-seat-reservation'),
					),
				));
			}

			private function is_pro() {
				return class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active();
			}

			/**
			 * Extract the sanitized array of booking IDs a request refers to,
			 * whether it sent a single scalar `booking_id` (row actions) or an
			 * array `booking_id[]` (bulk action bar) — one code path for both.
			 */
			private function get_requested_ids() {
				if (!isset($_POST['booking_id'])) {
					return array();
				}
				$raw = wp_unslash($_POST['booking_id']);
				$ids = is_array($raw) ? array_map('absint', $raw) : array(absint($raw));
				$ids = array_filter(array_unique($ids));
				return array_values(array_filter($ids, function ($id) {
					return get_post_type($id) === 'wbtm_bus_booking';
				}));
			}

			/**
			 * AJAX delete — single row or bulk selection, works in both free and
			 * Pro installs (matches the free-tier "only Delete works" scope).
			 */
			public function ajax_delete() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options')) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$ids = $this->get_requested_ids();
				if (empty($ids)) {
					wp_send_json_error(array('message' => esc_html__('Invalid booking.', 'bus-ticket-booking-with-seat-reservation')));
				}
				foreach ($ids as $id) {
					wp_delete_post($id, true);
				}
				wp_send_json_success(array(
					'ids'     => $ids,
					'message' => count($ids) > 1
						/* translators: %d: number of bookings deleted. */
						? sprintf(esc_html__('%d bookings deleted.', 'bus-ticket-booking-with-seat-reservation'), count($ids))
						: esc_html__('Booking deleted.', 'bus-ticket-booking-with-seat-reservation'),
				));
			}

			/**
			 * Applies a status to one booking: updates the record meta, keeps a
			 * linked WooCommerce order's status in sync, and logs the transition.
			 * Shared by the single-row and bulk status-change AJAX handlers.
			 */
			private function set_booking_status($id, $status) {
				$old_status = get_post_meta($id, 'wbtm_order_status', true);
				update_post_meta($id, 'wbtm_order_status', $status);

				$order_id = get_post_meta($id, 'wbtm_order_id', true);
				if ($order_id && class_exists('WBTM_Functions') && WBTM_Functions::is_wc_active() && function_exists('wc_get_order')) {
					$order = wc_get_order($order_id);
					if ($order) {
						$order->update_status($status);
					}
				}

				return $this->append_log($id, array(
					'type' => 'status',
					'from' => $old_status,
					'to'   => $status,
					'by'   => wp_get_current_user()->display_name,
					'time' => current_time('mysql'),
				));
			}

			/**
			 * AJAX status change (single row) — a real Pro feature (not a stub).
			 * Gated on is_pro() server-side so a free install can never reach it
			 * even if the request is crafted by hand.
			 */
			public function ajax_change_status() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$id     = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;
				$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
				$options = $this->get_status_options();
				if (!$id || get_post_type($id) !== 'wbtm_bus_booking' || !isset($options[$status])) {
					wp_send_json_error(array('message' => esc_html__('Invalid request.', 'bus-ticket-booking-with-seat-reservation')));
				}
				$log_entry = $this->set_booking_status($id, $status);

				wp_send_json_success(array(
					'id'        => $id,
					'status'    => $status,
					'label'     => $options[$status],
					'badge'     => $this->status_badge($status),
					'log_entry' => $this->render_log_entry($log_entry),
					'message'   => esc_html__('Status updated.', 'bus-ticket-booking-with-seat-reservation'),
				));
			}

			/**
			 * AJAX bulk status change — same Pro gate as the single-row version.
			 */
			public function ajax_bulk_change_status() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$status  = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
				$options = $this->get_status_options();
				$ids     = $this->get_requested_ids();
				if (empty($ids) || !isset($options[$status])) {
					wp_send_json_error(array('message' => esc_html__('Invalid request.', 'bus-ticket-booking-with-seat-reservation')));
				}
				$updated = array();
				foreach ($ids as $id) {
					$this->set_booking_status($id, $status);
					$updated[$id] = $this->status_badge($status);
				}
				wp_send_json_success(array(
					'updated' => $updated,
					/* translators: %d: number of bookings updated. */
					'message' => sprintf(esc_html__('%d bookings updated.', 'bus-ticket-booking-with-seat-reservation'), count($updated)),
				));
			}

			/**
			 * AJAX: add a private admin note to a booking. Pro-gated like the rest
			 * of the detail view (the detail page itself is unreachable in free
			 * installs, but the handler still checks server-side).
			 */
			public function ajax_add_note() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$id   = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;
				$note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
				if (!$id || get_post_type($id) !== 'wbtm_bus_booking' || $note === '') {
					wp_send_json_error(array('message' => esc_html__('Please write a note first.', 'bus-ticket-booking-with-seat-reservation')));
				}
				$log_entry = $this->append_log($id, array(
					'type' => 'note',
					'note' => $note,
					'by'   => wp_get_current_user()->display_name,
					'time' => current_time('mysql'),
				));
				wp_send_json_success(array(
					'log_entry' => $this->render_log_entry($log_entry),
				));
			}

			/**
			 * Append an entry to a booking's activity/notes log (single meta array,
			 * newest first — matches how the rest of this plugin stores per-post
			 * structured data as one serialized meta value rather than many keys).
			 */
			private function append_log($id, $entry) {
				$log = get_post_meta($id, 'wbtm_bkl_log', true);
				$log = is_array($log) ? $log : array();
				array_unshift($log, $entry);
				update_post_meta($id, 'wbtm_bkl_log', $log);
				return $entry;
			}

			private function get_log($id) {
				$log = get_post_meta($id, 'wbtm_bkl_log', true);
				return is_array($log) ? $log : array();
			}

			private function render_log_entry($entry) {
				$type = isset($entry['type']) ? $entry['type'] : 'status';
				$when = !empty($entry['time']) ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry['time']) : '';
				$by   = isset($entry['by']) ? $entry['by'] : '';
				ob_start();
				?>
				<div class="wbtm-bkl-log-entry wbtm-bkl-log-<?php echo esc_attr($type); ?>">
					<?php if ($type === 'note') : ?>
						<p class="wbtm-bkl-log-text"><?php echo nl2br(esc_html($entry['note'])); ?></p>
					<?php elseif ($type === 'created') : ?>
						<p class="wbtm-bkl-log-text"><span class="dashicons dashicons-cart"></span><?php esc_html_e('Booking placed.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<?php else : ?>
						<p class="wbtm-bkl-log-text">
							<span class="dashicons dashicons-update"></span>
							<?php
								$options = $this->get_status_options();
								$to_label = isset($options[$entry['to']]) ? $options[$entry['to']] : $entry['to'];
								if (!empty($entry['from']) && isset($options[$entry['from']])) {
									printf(
										/* translators: 1: previous status, 2: new status */
										esc_html__('Status changed from %1$s to %2$s.', 'bus-ticket-booking-with-seat-reservation'),
										esc_html($options[$entry['from']]),
										esc_html($to_label)
									);
								} else {
									printf(
										/* translators: %s: new status */
										esc_html__('Status set to %s.', 'bus-ticket-booking-with-seat-reservation'),
										esc_html($to_label)
									);
								}
							?>
						</p>
					<?php endif; ?>
					<span class="wbtm-bkl-log-meta"><?php echo esc_html($by); ?> &middot; <?php echo esc_html($when); ?></span>
				</div>
				<?php
				return ob_get_clean();
			}

			/**
			 * Status slug => label map shared by the filter bar and the row
			 * "Change Status" dropdown.
			 */
			private function get_status_options() {
				return array(
					'completed'  => esc_html__('Completed', 'bus-ticket-booking-with-seat-reservation'),
					'processing' => esc_html__('Processing', 'bus-ticket-booking-with-seat-reservation'),
					'pending'    => esc_html__('Pending', 'bus-ticket-booking-with-seat-reservation'),
					'on-hold'    => esc_html__('On Hold', 'bus-ticket-booking-with-seat-reservation'),
					'cancelled'  => esc_html__('Cancelled', 'bus-ticket-booking-with-seat-reservation'),
					'refunded'   => esc_html__('Refunded', 'bus-ticket-booking-with-seat-reservation'),
				);
			}

			/**
			 * Pro-only: stream all matching bookings (respecting the current filters)
			 * as a CSV download. Gated on is_pro() so "Export CSV" is never wired up
			 * to a working action in free installs.
			 */
			public function handle_export_csv() {
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_die(esc_html__('You do not have permission to do that.', 'bus-ticket-booking-with-seat-reservation'));
				}
				check_admin_referer('wbtm_bkl_export_csv');

				$args = $this->build_query_args(-1, 1);
				$ids  = get_posts(array_merge($args, array('fields' => 'ids')));

				nocache_headers();
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=wbtm-bookings-' . gmdate('Y-m-d') . '.csv');
				$out = fopen('php://output', 'w');
				fputcsv($out, array('Booking ID', 'Order ID', 'Source', 'Customer', 'Email', 'Bus', 'Boarding', 'Dropping', 'Journey Date', 'Seat', 'Ticket', 'Total', 'Status', 'Booked On'));
				foreach ($ids as $id) {
					$bus_id = (int) get_post_meta($id, 'wbtm_bus_id', true);
					fputcsv($out, array(
						$id,
						get_post_meta($id, 'wbtm_order_id', true),
						$this->booking_source($id) === 'standalone' ? 'Custom' : 'WooCommerce',
						get_post_meta($id, 'wbtm_user_name', true),
						get_post_meta($id, 'wbtm_user_email', true),
						$bus_id ? get_the_title($bus_id) : '',
						get_post_meta($id, 'wbtm_boarding_point', true),
						get_post_meta($id, 'wbtm_dropping_point', true),
						get_post_meta($id, 'wbtm_boarding_time', true) ?: get_post_meta($id, 'wbtm_booking_date', true),
						get_post_meta($id, 'wbtm_seat', true),
						get_post_meta($id, 'wbtm_ticket', true),
						get_post_meta($id, 'wbtm_bus_fare', true),
						get_post_meta($id, 'wbtm_order_status', true),
						get_post_meta($id, 'wbtm_booking_date', true) ?: get_the_date('Y-m-d H:i', $id),
					));
				}
				fclose($out);
				exit;
			}

			/**
			 * Lightweight aggregate stats via a single query — avoids loading every
			 * booking post into memory just to sum a meta value.
			 */
			private function get_stats() {
				global $wpdb;
				$row = $wpdb->get_row(
					"SELECT COUNT(DISTINCT p.ID) as total_bookings,
							COALESCE(SUM(CAST(fare.meta_value AS DECIMAL(10,2))), 0) as total_revenue,
							COUNT(DISTINCT bus.meta_value) as total_buses
					 FROM {$wpdb->posts} p
					 LEFT JOIN {$wpdb->postmeta} fare ON fare.post_id = p.ID AND fare.meta_key = 'wbtm_bus_fare'
					 LEFT JOIN {$wpdb->postmeta} bus ON bus.post_id = p.ID AND bus.meta_key = 'wbtm_bus_id'
					 WHERE p.post_type = 'wbtm_bus_booking' AND p.post_status = 'publish'"
				);
				return array(
					'total_bookings' => $row ? (int) $row->total_bookings : 0,
					'total_revenue'  => $row ? (float) $row->total_revenue : 0,
					'total_buses'    => $row ? (int) $row->total_buses : 0,
				);
			}

			/**
			 * Filters only take effect for Pro — the free tier never reads these
			 * request params for its own query (the filter bar is disabled markup),
			 * but build_query_args() is shared with the CSV export.
			 */
			private function build_query_args($per_page, $paged) {
				$args = array(
					'post_type'      => 'wbtm_bus_booking',
					'post_status'    => 'publish',
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);
				if (!$this->is_pro()) {
					return $args;
				}
				$f = $this->get_filter_values();

				$meta_query = array('relation' => 'AND');
				if ($f['search'] !== '') {
					$meta_query[] = array(
						'relation' => 'OR',
						array('key' => 'wbtm_user_name', 'value' => $f['search'], 'compare' => 'LIKE'),
						array('key' => 'wbtm_user_email', 'value' => $f['search'], 'compare' => 'LIKE'),
						array('key' => 'wbtm_order_id', 'value' => $f['search'], 'compare' => 'LIKE'),
					);
				}
				if ($f['status'] !== '') {
					$meta_query[] = array('key' => 'wbtm_order_status', 'value' => $f['status'], 'compare' => '=');
				}
				if ($f['bus_id']) {
					$meta_query[] = array('key' => 'wbtm_bus_id', 'value' => $f['bus_id'], 'compare' => '=');
				}
				if ($f['payment'] !== '') {
					$meta_query[] = array('key' => 'wbtm_billing_type', 'value' => $f['payment'], 'compare' => '=');
				}
				if ($f['ticket'] !== '') {
					$meta_query[] = array('key' => 'wbtm_ticket', 'value' => $f['ticket'], 'compare' => '=');
				}
				if ($f['journey_from'] !== '' || $f['journey_to'] !== '') {
					$meta_query[] = $this->date_range_clause('wbtm_boarding_time', $f['journey_from'], $f['journey_to']);
				}
				if ($f['booked_from'] !== '' || $f['booked_to'] !== '') {
					$meta_query[] = $this->date_range_clause('wbtm_booking_date', $f['booked_from'], $f['booked_to']);
				}
				if (count($meta_query) > 1) {
					$args['meta_query'] = $meta_query;
				}
				return $args;
			}

			/**
			 * Booking/journey dates are stored as plain 'Y-m-d H:i[:s]' strings, not
			 * dedicated date meta — WP_Meta_Query's DATE type still parses that fine
			 * via MySQL's DATE(), so a single range clause covers "from only",
			 * "to only", and "both" without three separate branches.
			 */
			private function date_range_clause($key, $from, $to) {
				if ($from !== '' && $to !== '') {
					return array('key' => $key, 'value' => array($from, $to), 'compare' => 'BETWEEN', 'type' => 'DATE');
				}
				if ($from !== '') {
					return array('key' => $key, 'value' => $from, 'compare' => '>=', 'type' => 'DATE');
				}
				return array('key' => $key, 'value' => $to, 'compare' => '<=', 'type' => 'DATE');
			}

			/**
			 * Sanitized filter-bar request params, shared by the query builder,
			 * the filter form (to re-populate values), and the "is a filter
			 * active" check used to auto-expand the collapsible panel.
			 */
			private function get_filter_values() {
				return array(
					'search'       => isset($_GET['wbtm_bl_s']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_s'])) : '',
					'status'       => isset($_GET['wbtm_bl_status']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_status'])) : '',
					'bus_id'       => isset($_GET['wbtm_bl_bus']) ? absint($_GET['wbtm_bl_bus']) : 0,
					'payment'      => isset($_GET['wbtm_bl_payment']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_payment'])) : '',
					'ticket'       => isset($_GET['wbtm_bl_ticket']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_ticket'])) : '',
					'journey_from' => isset($_GET['wbtm_bl_journey_from']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_journey_from'])) : '',
					'journey_to'   => isset($_GET['wbtm_bl_journey_to']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_journey_to'])) : '',
					'booked_from'  => isset($_GET['wbtm_bl_booked_from']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_booked_from'])) : '',
					'booked_to'    => isset($_GET['wbtm_bl_booked_to']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_booked_to'])) : '',
				);
			}

			private function query_bookings() {
				$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
				return new WP_Query($this->build_query_args(self::PER_PAGE, $paged));
			}

			private function status_badge($status) {
				$status = strtolower((string) $status);
				$status = str_replace('wc-', '', $status);
				$map = array(
					'completed'  => array('green', esc_html__('Completed', 'bus-ticket-booking-with-seat-reservation')),
					'processing' => array('blue', esc_html__('Processing', 'bus-ticket-booking-with-seat-reservation')),
					'pending'    => array('orange', esc_html__('Pending', 'bus-ticket-booking-with-seat-reservation')),
					'on-hold'    => array('orange', esc_html__('On Hold', 'bus-ticket-booking-with-seat-reservation')),
					'cancelled'  => array('red', esc_html__('Cancelled', 'bus-ticket-booking-with-seat-reservation')),
					'refunded'   => array('red', esc_html__('Refunded', 'bus-ticket-booking-with-seat-reservation')),
					'failed'     => array('red', esc_html__('Failed', 'bus-ticket-booking-with-seat-reservation')),
				);
				if (isset($map[$status])) {
					list($color, $label) = $map[$status];
				} else {
					$color = 'blue';
					$label = $status !== '' ? ucfirst($status) : esc_html__('N/A', 'bus-ticket-booking-with-seat-reservation');
				}
				return '<span class="wbtm-bkl-badge ' . esc_attr($color) . '">' . esc_html($label) . '</span>';
			}

			private function pro_badge_html() {
				return '<span class="wbtm-bkl-pro-badge"><span class="dashicons dashicons-lock"></span>' . esc_html__('PRO', 'bus-ticket-booking-with-seat-reservation') . '</span>';
			}

			/**
			 * Which flow actually processed this booking. There's no dedicated
			 * "source" meta key — WBTM_Standalone_Payment::insert_booking_records()
			 * (Pro) sets wbtm_order_id to a wbtm_bus_booking post id (itself for the
			 * first seat in a group, that same first-seat id for the rest) because it
			 * has no real WooCommerce order to key on, whereas the WooCommerce flow
			 * (WBTM_Woocommerce::add_billing_data()) always sets it to a genuine
			 * WC_Order id. So "does wbtm_order_id point at one of our own booking
			 * posts?" is a reliable, no-schema-change way to tell them apart, and it
			 * stays correct whether or not WooCommerce (or HPOS) is active.
			 */
			private function booking_source($id) {
				$order_id = get_post_meta($id, 'wbtm_order_id', true);
				if ($order_id && get_post_type($order_id) === 'wbtm_bus_booking') {
					return 'standalone';
				}
				return 'woocommerce';
			}

			private function source_badge($source) {
				if ($source === 'standalone') {
					return '<span class="wbtm-bkl-source wbtm-bkl-source-custom" title="' . esc_attr__('Booked via Custom Payment (no WooCommerce order)', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-money-alt"></span>' . esc_html__('Custom', 'bus-ticket-booking-with-seat-reservation') . '</span>';
				}
				return '<span class="wbtm-bkl-source wbtm-bkl-source-woo" title="' . esc_attr__('Booked via the WooCommerce cart/checkout', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-cart"></span>' . esc_html__('WooCommerce', 'bus-ticket-booking-with-seat-reservation') . '</span>';
			}

			public function render_page() {
				if (!current_user_can('manage_options')) {
					return;
				}
				if (isset($_GET['action'], $_GET['booking']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'view') {
					$this->render_detail(absint($_GET['booking']));
					return;
				}
				$is_pro = $this->is_pro();
				$query  = $this->query_bookings();
				$page_base = add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php'));
				?>
				<div class="wbtm-bkl-wrap">

					<div class="wbtm-bkl-header">
						<div>
							<h1 class="wbtm-bkl-title"><span class="dashicons dashicons-clipboard"></span><?php esc_html_e('Booking List', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
							<p class="wbtm-bkl-subtitle"><?php esc_html_e('Every booked seat across all buses, in one place.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						</div>
						<div class="wbtm-bkl-header-actions">
							<?php if ($is_pro) : ?>
								<?php
									$export_url = wp_nonce_url(
										add_query_arg('action', 'wbtm_bkl_export_csv', admin_url('admin-post.php')),
										'wbtm_bkl_export_csv'
									);
								?>
								<a href="<?php echo esc_url($export_url); ?>" class="wbtm-bkl-pro-cta"><span class="dashicons dashicons-download"></span><?php esc_html_e('Export CSV', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<?php else : ?>
								<a href="https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/" target="_blank" rel="noopener noreferrer" class="wbtm-bkl-pro-cta"><span class="dashicons dashicons-star-filled"></span><?php esc_html_e('Upgrade to PRO', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<?php endif; ?>
						</div>
					</div>

					<?php $this->render_stats($is_pro); ?>

					<?php $this->render_filters($is_pro); ?>

					<div class="wbtm-bkl-table-wrap">
						<div class="wbtm-bkl-table-toolbar">
							<div class="wbtm-bkl-bulk-bar">
								<select id="wbtm-bkl-bulk-action">
									<option value="-1"><?php esc_html_e('Bulk actions', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<option value="delete"><?php esc_html_e('Delete', 'bus-ticket-booking-with-seat-reservation'); ?></option>
									<?php foreach ($this->get_status_options() as $slug => $label) : ?>
										<option value="status:<?php echo esc_attr($slug); ?>" <?php echo $is_pro ? '' : 'data-pro="1"'; ?>>
											<?php
												/* translators: %s: status label. */
												echo esc_html(sprintf(__('Change status to: %s', 'bus-ticket-booking-with-seat-reservation'), $label));
												if (!$is_pro) echo ' ' . esc_html__('(PRO)', 'bus-ticket-booking-with-seat-reservation');
											?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="button" id="wbtm-bkl-bulk-apply" class="button"><?php esc_html_e('Apply', 'bus-ticket-booking-with-seat-reservation'); ?></button>
								<span class="wbtm-bkl-count">
									<?php
										/* translators: %s: number of bookings. */
										echo esc_html(sprintf(_n('%s booking', '%s bookings', $query->found_posts, 'bus-ticket-booking-with-seat-reservation'), number_format_i18n($query->found_posts)));
									?>
								</span>
							</div>
							<?php if ($query->max_num_pages > 1) : ?>
								<div class="wbtm-bkl-pagination wbtm-bkl-pagination-top">
									<?php
										echo paginate_links(array(
											'base'      => add_query_arg('paged', '%#%'),
											'format'    => '',
											'current'   => max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1),
											'total'     => (int) $query->max_num_pages,
											'prev_text' => '&lsaquo;',
											'next_text' => '&rsaquo;',
										));
									?>
								</div>
							<?php endif; ?>
						</div>
						<table class="wbtm-bkl-table">
							<thead>
								<tr>
									<th class="wbtm-bkl-col-check"><input type="checkbox" id="wbtm-bkl-select-all"></th>
									<th><?php esc_html_e('Booking', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Customer', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Bus & Route', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Seat / Ticket', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Total', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th><?php esc_html_e('Booked On', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th class="wbtm-bkl-col-actions"><?php esc_html_e('Actions', 'bus-ticket-booking-with-seat-reservation'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ($query->have_posts()) : ?>
									<?php while ($query->have_posts()) : $query->the_post(); ?>
										<?php $this->render_row(get_the_ID(), $is_pro); ?>
									<?php endwhile; wp_reset_postdata(); ?>
								<?php else : ?>
									<tr><td colspan="10" class="wbtm-bkl-empty"><span class="dashicons dashicons-clipboard"></span><p><?php esc_html_e('No bookings found.', 'bus-ticket-booking-with-seat-reservation'); ?></p></td></tr>
								<?php endif; ?>
							</tbody>
						</table>

						<?php if ($query->max_num_pages > 1) : ?>
							<div class="wbtm-bkl-pagination">
								<?php
									echo paginate_links(array(
										'base'      => add_query_arg('paged', '%#%'),
										'format'    => '',
										'current'   => max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1),
										'total'     => (int) $query->max_num_pages,
										'prev_text' => '&lsaquo;',
										'next_text' => '&rsaquo;',
									));
								?>
							</div>
						<?php endif; ?>
					</div>

					<?php $this->render_delete_modal(); ?>
					<?php $this->render_status_modal(); ?>
				</div>
				<?php
			}

			private function render_stats($is_pro) {
				$stats = $this->get_stats();
				$cards = array(
					array('dashicons-cart', $is_pro ? number_format_i18n($stats['total_bookings']) : '&bull;&bull;&bull;', esc_html__('Total Bookings', 'bus-ticket-booking-with-seat-reservation')),
					array('dashicons-money-alt', $is_pro ? WBTM_Global_Function::format_price($stats['total_revenue']) : '&bull;&bull;&bull;', esc_html__('Total Revenue', 'bus-ticket-booking-with-seat-reservation')),
					array('dashicons-admin-multisite', $is_pro ? number_format_i18n($stats['total_buses']) : '&bull;&bull;&bull;', esc_html__('Buses Booked', 'bus-ticket-booking-with-seat-reservation')),
				);
				?>
				<div class="wbtm-bkl-locked<?php echo $is_pro ? '' : ' is-locked'; ?>">
					<div class="wbtm-bkl-stats" <?php echo $is_pro ? '' : 'aria-hidden="true"'; ?>>
						<?php foreach ($cards as $card) : ?>
							<div class="wbtm-bkl-stat">
								<span class="wbtm-bkl-stat-icon dashicons <?php echo esc_attr($card[0]); ?>"></span>
								<div>
									<span class="wbtm-bkl-stat-value"><?php echo wp_kses_post($card[1]); ?></span>
									<span class="wbtm-bkl-stat-label"><?php echo esc_html($card[2]); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if (!$is_pro) : ?>
						<div class="wbtm-bkl-lock-overlay">
							<?php echo wp_kses_post($this->pro_badge_html()); ?>
							<p><?php esc_html_e('Booking analytics & revenue insights are a PRO feature.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						</div>
					<?php endif; ?>
				</div>
				<?php
			}

			/**
			 * Distinct, non-empty values already used for a given booking meta key —
			 * powers the Payment Method / Ticket Type dropdowns without hardcoding a
			 * gateway or ticket-type list that would drift from what's actually booked.
			 */
			private function get_distinct_meta_values($meta_key) {
				global $wpdb;
				return $wpdb->get_col($wpdb->prepare(
					"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE p.post_type = 'wbtm_bus_booking' AND p.post_status = 'publish'
					 AND pm.meta_key = %s AND pm.meta_value != ''
					 ORDER BY pm.meta_value ASC",
					$meta_key
				));
			}

			private function render_filters($is_pro) {
				$f = $this->get_filter_values();
				$statuses = $this->get_status_options();
				$buses    = $is_pro ? get_posts(array('post_type' => 'wbtm_bus', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC')) : array();
				$payments = $is_pro ? $this->get_distinct_meta_values('wbtm_billing_type') : array();
				$tickets  = $is_pro ? $this->get_distinct_meta_values('wbtm_ticket') : array();
				$has_active_filter = $is_pro && count(array_filter($f, function ($v) { return $v !== '' && $v !== 0; })) > 0;
				?>
				<div class="wbtm-bkl-filters-panel">
					<button type="button" class="wbtm-bkl-filters-toggle" aria-expanded="<?php echo $has_active_filter ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e('Filters', 'bus-ticket-booking-with-seat-reservation'); ?>
						<?php if ($has_active_filter) : ?><span class="wbtm-bkl-filters-active-dot"></span><?php endif; ?>
						<span class="dashicons dashicons-arrow-down-alt2 wbtm-bkl-filters-arrow"></span>
					</button>
					<div class="wbtm-bkl-filters-body<?php echo $has_active_filter ? ' is-open' : ''; ?>">
						<div class="wbtm-bkl-locked wbtm-bkl-locked-filters<?php echo $is_pro ? '' : ' is-locked'; ?>">
							<form method="get" class="wbtm-bkl-filters" <?php echo $is_pro ? '' : 'aria-hidden="true"'; ?>>
								<input type="hidden" name="post_type" value="wbtm_bus">
								<input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

								<div class="wbtm-bkl-filter-field wbtm-bkl-filter-wide">
									<span class="dashicons dashicons-search"></span>
									<input type="text" name="wbtm_bl_s" value="<?php echo esc_attr($f['search']); ?>" placeholder="<?php esc_attr_e('Search customer, email or order #', 'bus-ticket-booking-with-seat-reservation'); ?>" <?php disabled(!$is_pro); ?>>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_status" <?php disabled(!$is_pro); ?>>
										<option value=""><?php esc_html_e('All Statuses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
										<?php foreach ($statuses as $key => $label) : ?>
											<option value="<?php echo esc_attr($key); ?>" <?php selected($f['status'], $key); ?>><?php echo esc_html($label); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Bus', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_bus" <?php disabled(!$is_pro); ?>>
										<option value=""><?php esc_html_e('All Buses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
										<?php foreach ($buses as $bus) : ?>
											<option value="<?php echo esc_attr($bus->ID); ?>" <?php selected($f['bus_id'], $bus->ID); ?>><?php echo esc_html(get_the_title($bus)); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Payment Method', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_payment" <?php disabled(!$is_pro); ?>>
										<option value=""><?php esc_html_e('All Methods', 'bus-ticket-booking-with-seat-reservation'); ?></option>
										<?php foreach ($payments as $method) : ?>
											<option value="<?php echo esc_attr($method); ?>" <?php selected($f['payment'], $method); ?>><?php echo esc_html(ucfirst($method)); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Ticket Type', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_ticket" <?php disabled(!$is_pro); ?>>
										<option value=""><?php esc_html_e('All Ticket Types', 'bus-ticket-booking-with-seat-reservation'); ?></option>
										<?php foreach ($tickets as $ticket) : ?>
											<option value="<?php echo esc_attr($ticket); ?>" <?php selected($f['ticket'], $ticket); ?>><?php echo esc_html($ticket); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="wbtm-bkl-filter-group wbtm-bkl-filter-daterange">
									<label><?php esc_html_e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<div class="wbtm-bkl-filter-daterange-inputs">
										<input type="date" name="wbtm_bl_journey_from" value="<?php echo esc_attr($f['journey_from']); ?>" <?php disabled(!$is_pro); ?>>
										<span>&ndash;</span>
										<input type="date" name="wbtm_bl_journey_to" value="<?php echo esc_attr($f['journey_to']); ?>" <?php disabled(!$is_pro); ?>>
									</div>
								</div>

								<div class="wbtm-bkl-filter-group wbtm-bkl-filter-daterange">
									<label><?php esc_html_e('Booked On', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<div class="wbtm-bkl-filter-daterange-inputs">
										<input type="date" name="wbtm_bl_booked_from" value="<?php echo esc_attr($f['booked_from']); ?>" <?php disabled(!$is_pro); ?>>
										<span>&ndash;</span>
										<input type="date" name="wbtm_bl_booked_to" value="<?php echo esc_attr($f['booked_to']); ?>" <?php disabled(!$is_pro); ?>>
									</div>
								</div>

								<div class="wbtm-bkl-filter-actions">
									<?php if ($is_pro) : ?>
										<button type="submit" class="button button-primary"><?php esc_html_e('Apply Filters', 'bus-ticket-booking-with-seat-reservation'); ?></button>
										<?php if ($has_active_filter) : ?>
											<a class="button" href="<?php echo esc_url(add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php'))); ?>"><?php esc_html_e('Reset', 'bus-ticket-booking-with-seat-reservation'); ?></a>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</form>
							<?php if (!$is_pro) : ?>
								<div class="wbtm-bkl-lock-overlay">
									<?php echo wp_kses_post($this->pro_badge_html()); ?>
									<p><?php esc_html_e('Search, filtering & CSV export are available in PRO.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php
			}

			private function render_row($id, $is_pro) {
				$order_id  = get_post_meta($id, 'wbtm_order_id', true);
				$bus_id    = (int) get_post_meta($id, 'wbtm_bus_id', true);
				$user_name = get_post_meta($id, 'wbtm_user_name', true);
				$user_email = get_post_meta($id, 'wbtm_user_email', true);
				$bp        = get_post_meta($id, 'wbtm_boarding_point', true);
				$dp        = get_post_meta($id, 'wbtm_dropping_point', true);
				$bp_time   = get_post_meta($id, 'wbtm_boarding_time', true);
				$booking_date = get_post_meta($id, 'wbtm_booking_date', true);
				$seat      = get_post_meta($id, 'wbtm_seat', true);
				$ticket    = get_post_meta($id, 'wbtm_ticket', true);
				$fare      = get_post_meta($id, 'wbtm_bus_fare', true);
				$status    = get_post_meta($id, 'wbtm_order_status', true);
				$bus_title = $bus_id ? get_the_title($bus_id) : '';
				$journey_date = $bp_time ?: $booking_date;
				$wc_active = class_exists('WBTM_Functions') && WBTM_Functions::is_wc_active();
				$reference = '#' . ($order_id ? $order_id : $id);
				?>
				<tr data-row-id="<?php echo esc_attr($id); ?>">
					<td class="wbtm-bkl-col-check"><input type="checkbox" class="wbtm-bkl-row-check" value="<?php echo esc_attr($id); ?>"></td>
					<td>
						<strong><?php echo esc_html($reference); ?></strong>
						<?php echo wp_kses_post($this->source_badge($this->booking_source($id))); ?>
						<span class="wbtm-bkl-sub">ID <?php echo esc_html($id); ?></span>
					</td>
					<td>
						<strong><?php echo esc_html($user_name ?: '—'); ?></strong>
						<?php if ($user_email) : ?><br><small><?php echo esc_html($user_email); ?></small><?php endif; ?>
					</td>
					<td>
						<?php if ($bus_id) : ?>
							<a href="<?php echo esc_url(get_edit_post_link($bus_id)); ?>"><?php echo esc_html($bus_title); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
						<?php if ($bp || $dp) : ?><br><small><?php echo esc_html($bp); ?> &rarr; <?php echo esc_html($dp); ?></small><?php endif; ?>
					</td>
					<td><?php echo esc_html($journey_date ?: '—'); ?></td>
					<td>
						<?php if ($seat) : ?><span class="wbtm-bkl-pill"><?php echo esc_html($seat); ?></span><?php endif; ?>
						<?php if ($ticket) : ?><br><small><?php echo esc_html($ticket); ?></small><?php endif; ?>
					</td>
					<td><strong><?php echo wp_kses_post(WBTM_Global_Function::format_price($fare)); ?></strong></td>
					<td><?php echo wp_kses_post($this->status_badge($status)); ?></td>
					<td><?php echo esc_html($booking_date ?: get_the_date('Y-m-d H:i', $id)); ?></td>
					<td class="wbtm-bkl-col-actions">
						<div class="wbtm-bkl-dropdown">
							<button type="button" class="wbtm-bkl-dropdown-toggle" aria-haspopup="true" aria-expanded="false" title="<?php esc_attr_e('Actions', 'bus-ticket-booking-with-seat-reservation'); ?>">
								<span class="dashicons dashicons-ellipsis"></span>
							</button>
							<div class="wbtm-bkl-dropdown-menu">
								<?php if ($is_pro) : ?>
									<a class="wbtm-bkl-dropdown-item" href="<?php echo esc_url(add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG, 'action' => 'view', 'booking' => $id), admin_url('edit.php'))); ?>">
										<span class="dashicons dashicons-visibility"></span><?php esc_html_e('View Details', 'bus-ticket-booking-with-seat-reservation'); ?>
									</a>
								<?php else : ?>
									<span class="wbtm-bkl-dropdown-item wbtm-bkl-locked-trigger">
										<span class="dashicons dashicons-visibility"></span><?php esc_html_e('View Details', 'bus-ticket-booking-with-seat-reservation'); ?>
										<span class="wbtm-bkl-mini-pro"><?php esc_html_e('PRO', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									</span>
								<?php endif; ?>

								<?php if ($wc_active && $order_id && $this->booking_source($id) === 'woocommerce') : ?>
									<a class="wbtm-bkl-dropdown-item" href="<?php echo esc_url(admin_url('post.php?post=' . absint($order_id) . '&action=edit')); ?>">
										<span class="dashicons dashicons-cart"></span><?php esc_html_e('View WC Order', 'bus-ticket-booking-with-seat-reservation'); ?>
									</a>
								<?php endif; ?>

								<?php if ($is_pro) : ?>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-change-status-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>" data-status="<?php echo esc_attr($status); ?>">
										<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
								<?php else : ?>
									<span class="wbtm-bkl-dropdown-item wbtm-bkl-locked-trigger">
										<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
										<span class="wbtm-bkl-mini-pro"><?php esc_html_e('PRO', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									</span>
								<?php endif; ?>

								<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-del-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>">
									<span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete', 'bus-ticket-booking-with-seat-reservation'); ?>
								</button>
							</div>
						</div>
					</td>
				</tr>
				<?php
			}

			private function render_delete_modal() {
				?>
				<div id="wbtm-bkl-delete-modal" class="wbtm-bkl-modal" style="display:none;">
					<div class="wbtm-bkl-modal-card">
						<div class="wbtm-bkl-modal-head">
							<h2><span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete Booking', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
							<span class="wbtm-bkl-modal-close dashicons dashicons-no-alt" role="button" aria-label="<?php esc_attr_e('Close', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
						</div>
						<div class="wbtm-bkl-modal-body">
							<input type="hidden" id="wbtm-bkl-delete-id" value="">
							<p>
								<?php
									printf(
										/* translators: %s: booking reference. */
										esc_html__('Delete booking %s permanently? This cannot be undone.', 'bus-ticket-booking-with-seat-reservation'),
										'<strong id="wbtm-bkl-delete-ref">#0</strong>'
									);
								?>
							</p>
							<div class="wbtm-bkl-modal-actions">
								<button type="button" class="wbtm-bkl-btn wbtm-bkl-btn-outline wbtm-bkl-modal-close"><?php esc_html_e('Cancel', 'bus-ticket-booking-with-seat-reservation'); ?></button>
								<button type="button" id="wbtm-bkl-delete-confirm" class="wbtm-bkl-btn wbtm-bkl-btn-danger"><span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete', 'bus-ticket-booking-with-seat-reservation'); ?></button>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			/**
			 * Shared "Change Status" modal — rendered once at the bottom of both the
			 * list page and the detail page, opened from either the row dropdown or
			 * the detail-page header button (same trigger class, same JS handler).
			 */
			private function render_status_modal() {
				?>
				<div id="wbtm-bkl-status-modal" class="wbtm-bkl-modal" style="display:none;">
					<div class="wbtm-bkl-modal-card">
						<div class="wbtm-bkl-modal-head">
							<h2><span class="dashicons dashicons-update"></span><?php esc_html_e('Change Booking Status', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
							<span class="wbtm-bkl-modal-close dashicons dashicons-no-alt" role="button" aria-label="<?php esc_attr_e('Close', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
						</div>
						<div class="wbtm-bkl-modal-body">
							<input type="hidden" id="wbtm-bkl-status-modal-id" value="">
							<p class="wbtm-bkl-modal-subtitle"><?php esc_html_e('Booking', 'bus-ticket-booking-with-seat-reservation'); ?> <strong id="wbtm-bkl-status-modal-ref">#0</strong></p>
							<div class="wbtm-bkl-status-options" id="wbtm-bkl-status-modal-options" role="radiogroup" aria-label="<?php esc_attr_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?>">
								<?php foreach ($this->get_status_options() as $slug => $label) : ?>
									<label class="wbtm-bkl-status-option-row wbtm-bkl-status-dot-<?php echo esc_attr($slug); ?>">
										<input type="radio" name="wbtm_bkl_status_modal_option" value="<?php echo esc_attr($slug); ?>">
										<span class="wbtm-bkl-status-dot"></span>
										<span class="wbtm-bkl-status-option-label"><?php echo esc_html($label); ?></span>
										<span class="dashicons dashicons-yes-alt wbtm-bkl-status-check"></span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="wbtm-bkl-modal-actions">
								<button type="button" class="wbtm-bkl-btn wbtm-bkl-btn-outline wbtm-bkl-modal-close"><?php esc_html_e('Cancel', 'bus-ticket-booking-with-seat-reservation'); ?></button>
								<button type="button" id="wbtm-bkl-status-modal-save" class="wbtm-bkl-btn wbtm-bkl-btn-primary"><span class="dashicons dashicons-saved"></span><?php esc_html_e('Save Status', 'bus-ticket-booking-with-seat-reservation'); ?></button>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			/**
			 * Booking detail page — full page render (not an AJAX modal), reached
			 * via ?action=view&booking=ID under the same admin page. Mirrors the
			 * rental plugin's Pro "Bookings" detail layout: a two-card info row,
			 * a fare-breakdown table, and a sidebar with Notes + Activity Log.
			 * Pro-gated: a free install is redirected back to the list even if the
			 * URL is visited directly.
			 */
			private function render_detail($id) {
				if (!current_user_can('manage_options')) {
					return;
				}
				if (!$this->is_pro() || get_post_type($id) !== 'wbtm_bus_booking') {
					wp_safe_redirect(add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php')));
					exit;
				}

				$order_id   = get_post_meta($id, 'wbtm_order_id', true);
				$bus_id     = (int) get_post_meta($id, 'wbtm_bus_id', true);
				$user_id    = (int) get_post_meta($id, 'wbtm_user_id', true);
				$user_name  = get_post_meta($id, 'wbtm_user_name', true);
				$user_email = get_post_meta($id, 'wbtm_user_email', true);
				$user_phone = get_post_meta($id, 'wbtm_user_phone', true);
				$bp         = get_post_meta($id, 'wbtm_boarding_point', true);
				$dp         = get_post_meta($id, 'wbtm_dropping_point', true);
				$bp_time    = get_post_meta($id, 'wbtm_boarding_time', true);
				$dp_time    = get_post_meta($id, 'wbtm_dropping_time', true);
				$booking_date = get_post_meta($id, 'wbtm_booking_date', true);
				$seat       = get_post_meta($id, 'wbtm_seat', true);
				$ticket     = get_post_meta($id, 'wbtm_ticket', true);
				$fare       = (float) get_post_meta($id, 'wbtm_bus_fare', true);
				$status     = get_post_meta($id, 'wbtm_order_status', true);
				$billing_type = get_post_meta($id, 'wbtm_billing_type', true);
				$extra_services = get_post_meta($id, 'wbtm_extra_services', true);
				$extra_services = is_array($extra_services) ? $extra_services : array();
				$full_bus_base  = (float) get_post_meta($id, 'wbtm_full_bus_base_price', true);
				$full_bus_discount = (float) get_post_meta($id, 'wbtm_full_bus_discount', true);
				$attendee_info  = get_post_meta($id, 'wbtm_attendee_info', true);
				$attendee_info  = is_array($attendee_info) ? $attendee_info : array();
				$bus_title  = $bus_id ? get_the_title($bus_id) : '';
				$journey_date = $bp_time ?: $booking_date;
				$wc_active  = class_exists('WBTM_Functions') && WBTM_Functions::is_wc_active();
				$reference  = '#' . ($order_id ? $order_id : $id);
				$account    = $user_id ? get_userdata($user_id) : false;

				$services_total = 0;
				foreach ($extra_services as $svc) {
					$services_total += (float) ($svc['price'] ?? 0) * (int) ($svc['qty'] ?? 1);
				}
				$grand_total = $fare + $services_total + ($full_bus_base ? max(0, $full_bus_base - $full_bus_discount) : 0);

				$log = $this->get_log($id);
				if (empty($log)) {
					$log = array(array(
						'type' => 'created',
						'by'   => $user_name ?: esc_html__('Guest', 'bus-ticket-booking-with-seat-reservation'),
						'time' => $booking_date ?: get_the_date('Y-m-d H:i:s', $id),
					));
				}
				$notes = array_values(array_filter($log, function ($e) { return isset($e['type']) && $e['type'] === 'note'; }));

				$back_url = add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php'));
				?>
				<div class="wbtm-bkl-wrap">

					<div class="wbtm-bkl-header">
						<div>
							<h1 class="wbtm-bkl-title">
								<span class="dashicons dashicons-clipboard"></span><?php echo esc_html($reference); ?>
								<span class="wbtm-bkl-badge-inline wbtm-bkl-current-status" data-booking-id="<?php echo esc_attr($id); ?>"><?php echo wp_kses_post($this->status_badge($status)); ?></span>
								<?php echo wp_kses_post($this->source_badge($this->booking_source($id))); ?>
							</h1>
							<p class="wbtm-bkl-subtitle"><?php esc_html_e('Booked on', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo esc_html($booking_date ?: get_the_date('Y-m-d H:i', $id)); ?></p>
						</div>
						<div class="wbtm-bkl-header-actions">
							<button type="button" class="wbtm-bkl-btn wbtm-bkl-btn-outline wbtm-bkl-change-status-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>" data-status="<?php echo esc_attr($status); ?>">
								<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
							</button>
							<?php if ($wc_active && $order_id && $this->booking_source($id) === 'woocommerce') : ?>
								<a href="<?php echo esc_url(admin_url('post.php?post=' . absint($order_id) . '&action=edit')); ?>" class="wbtm-bkl-btn wbtm-bkl-btn-outline"><span class="dashicons dashicons-cart"></span><?php esc_html_e('View WC Order', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<?php endif; ?>
							<a href="<?php echo esc_url($back_url); ?>" class="wbtm-bkl-btn wbtm-bkl-btn-outline"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e('Back to Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></a>
						</div>
					</div>

					<div class="wbtm-bkl-detail-columns">
						<div class="wbtm-bkl-detail-main">

							<div class="wbtm-bkl-detail-grid">
								<div class="wbtm-bkl-detail-card">
									<div class="wbtm-bkl-detail-card-header"><span class="dashicons dashicons-media-text"></span><?php esc_html_e('Booking Details', 'bus-ticket-booking-with-seat-reservation'); ?></div>
									<div class="wbtm-bkl-detail-card-body">
										<dl class="wbtm-bkl-dl">
											<dt><?php esc_html_e('Booking ID', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd>#<?php echo esc_html($id); ?></dd>
											<?php if ($order_id) : ?><dt><?php esc_html_e('Order ID', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd>#<?php echo esc_html($order_id); ?></dd><?php endif; ?>
											<dt><?php esc_html_e('Bus', 'bus-ticket-booking-with-seat-reservation'); ?></dt>
											<dd><?php echo $bus_id ? '<a href="' . esc_url(get_edit_post_link($bus_id)) . '">' . esc_html($bus_title) . '</a>' : '—'; ?></dd>
											<?php if ($bp || $dp) : ?>
												<dt><?php esc_html_e('Route', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($bp); ?> &rarr; <?php echo esc_html($dp); ?></dd>
											<?php endif; ?>
											<dt><?php esc_html_e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($journey_date ?: '—'); ?></dd>
											<?php if ($dp_time) : ?><dt><?php esc_html_e('Arrival', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($dp_time); ?></dd><?php endif; ?>
											<dt><?php esc_html_e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo $seat ? '<span class="wbtm-bkl-pill">' . esc_html($seat) . '</span>' : '—'; ?></dd>
											<dt><?php esc_html_e('Ticket Type', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($ticket ?: '—'); ?></dd>
											<dt><?php esc_html_e('Payment Method', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($billing_type ? ucfirst($billing_type) : '—'); ?></dd>
										</dl>
									</div>
								</div>

								<div class="wbtm-bkl-detail-card">
									<div class="wbtm-bkl-detail-card-header"><span class="dashicons dashicons-admin-users"></span><?php esc_html_e('Customer', 'bus-ticket-booking-with-seat-reservation'); ?></div>
									<div class="wbtm-bkl-detail-card-body">
										<dl class="wbtm-bkl-dl">
											<dt><?php esc_html_e('Name', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($user_name ?: '—'); ?></dd>
											<dt><?php esc_html_e('Email', 'bus-ticket-booking-with-seat-reservation'); ?></dt>
											<dd><?php echo $user_email ? '<a href="mailto:' . esc_attr($user_email) . '">' . esc_html($user_email) . '</a>' : '—'; ?></dd>
											<dt><?php esc_html_e('Phone', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($user_phone ?: '—'); ?></dd>
											<dt><?php esc_html_e('Account', 'bus-ticket-booking-with-seat-reservation'); ?></dt>
											<dd><?php echo $account ? esc_html($account->display_name . ' (' . $account->user_email . ')') : esc_html__('Guest', 'bus-ticket-booking-with-seat-reservation'); ?></dd>
										</dl>
									</div>
								</div>
							</div>

							<?php if (!empty($attendee_info)) : ?>
								<div class="wbtm-bkl-detail-section">
									<h2 class="wbtm-bkl-section-title"><span class="dashicons dashicons-id"></span><?php esc_html_e('Passenger Info', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
									<div class="wbtm-bkl-detail-card">
										<div class="wbtm-bkl-detail-card-body">
											<dl class="wbtm-bkl-dl">
												<?php foreach ($attendee_info as $field_key => $field) :
													$value = is_array($field) ? ($field['value'] ?? '') : $field;
													$value = is_array($value) ? implode(', ', $value) : $value;
													if ($value === '') continue;
												?>
													<dt><?php echo esc_html(is_string($field_key) ? ucwords(str_replace('_', ' ', $field_key)) : esc_html__('Field', 'bus-ticket-booking-with-seat-reservation')); ?></dt>
													<dd><?php echo esc_html($value); ?></dd>
												<?php endforeach; ?>
											</dl>
										</div>
									</div>
								</div>
							<?php endif; ?>

							<div class="wbtm-bkl-detail-section">
								<h2 class="wbtm-bkl-section-title"><span class="dashicons dashicons-cart"></span><?php esc_html_e('Fare Breakdown', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
								<div class="wbtm-bkl-table-wrap wbtm-bkl-table-wrap-plain">
									<table class="wbtm-bkl-table wbtm-bkl-table-plain">
										<thead>
											<tr><th><?php esc_html_e('Item', 'bus-ticket-booking-with-seat-reservation'); ?></th><th><?php esc_html_e('Qty', 'bus-ticket-booking-with-seat-reservation'); ?></th><th><?php esc_html_e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></th></tr>
										</thead>
										<tbody>
											<tr>
												<td><?php echo esc_html($ticket ?: esc_html__('Seat fare', 'bus-ticket-booking-with-seat-reservation')); ?> <?php echo $seat ? '(' . esc_html($seat) . ')' : ''; ?></td>
												<td>1</td>
												<td><?php echo wp_kses_post(WBTM_Global_Function::format_price($fare)); ?></td>
											</tr>
											<?php if ($full_bus_base) : ?>
												<tr>
													<td><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Full Bus Discount', 'bus-ticket-booking-with-seat-reservation'); ?></td>
													<td>1</td>
													<td>&minus;<?php echo wp_kses_post(WBTM_Global_Function::format_price($full_bus_discount)); ?></td>
												</tr>
											<?php endif; ?>
											<?php foreach ($extra_services as $svc) : ?>
												<tr class="wbtm-bkl-addon-row">
													<td><span class="dashicons dashicons-plus-alt2"></span> <?php echo esc_html($svc['name'] ?? ''); ?></td>
													<td><?php echo esc_html($svc['qty'] ?? 1); ?></td>
													<td><?php echo wp_kses_post(WBTM_Global_Function::format_price(($svc['price'] ?? 0) * ($svc['qty'] ?? 1))); ?></td>
												</tr>
											<?php endforeach; ?>
											<tr class="wbtm-bkl-total-row">
												<td colspan="2"><strong><?php esc_html_e('Total', 'bus-ticket-booking-with-seat-reservation'); ?></strong></td>
												<td><strong><?php echo wp_kses_post(WBTM_Global_Function::format_price($grand_total)); ?></strong></td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>

						</div>

						<div class="wbtm-bkl-detail-sidebar">

							<div class="wbtm-bkl-detail-card">
								<div class="wbtm-bkl-detail-card-header"><span class="dashicons dashicons-admin-comments"></span><?php esc_html_e('Notes', 'bus-ticket-booking-with-seat-reservation'); ?></div>
								<div class="wbtm-bkl-detail-card-body">
									<div class="wbtm-bkl-note-form">
										<textarea id="wbtm-bkl-note-input" rows="3" placeholder="<?php esc_attr_e('Add a private note…', 'bus-ticket-booking-with-seat-reservation'); ?>"></textarea>
										<button type="button" id="wbtm-bkl-note-add" class="wbtm-bkl-btn wbtm-bkl-btn-primary wbtm-bkl-btn-sm" data-id="<?php echo esc_attr($id); ?>">
											<span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Add Note', 'bus-ticket-booking-with-seat-reservation'); ?>
										</button>
									</div>
									<div class="wbtm-bkl-log-list" id="wbtm-bkl-notes-list">
										<?php if (empty($notes)) : ?>
											<p class="wbtm-bkl-log-empty"><?php esc_html_e('No notes yet.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
										<?php else : foreach ($notes as $entry) { echo $this->render_log_entry($entry); } endif; ?>
									</div>
								</div>
							</div>

							<div class="wbtm-bkl-detail-card">
								<div class="wbtm-bkl-detail-card-header"><span class="dashicons dashicons-clock"></span><?php esc_html_e('Activity Log', 'bus-ticket-booking-with-seat-reservation'); ?></div>
								<div class="wbtm-bkl-detail-card-body">
									<div class="wbtm-bkl-log-list" id="wbtm-bkl-activity-log">
										<?php foreach ($log as $entry) { echo $this->render_log_entry($entry); } ?>
									</div>
								</div>
							</div>

						</div>
					</div>

					<?php $this->render_status_modal(); ?>
				</div>
				<?php
			}
		}
		new WBTM_Booking_List();
	}
