<?php
	/*
	* @Author 		MagePeople Team
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
				// Reposition the submenu after every menu (core CPT items + all add-ons)
				// is registered, so it lands directly under "Purchase Ticket".
				add_action('admin_menu', array($this, 'reorder_submenu'), 9999);
				add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
				add_action('wp_ajax_wbtm_bkl_delete', array($this, 'ajax_delete'));
				add_action('wp_ajax_wbtm_bkl_change_status', array($this, 'ajax_change_status'));
				add_action('wp_ajax_wbtm_bkl_bulk_change_status', array($this, 'ajax_bulk_change_status'));
				add_action('wp_ajax_wbtm_bkl_resend', array($this, 'ajax_resend'));
				add_action('wp_ajax_wbtm_bkl_add_note', array($this, 'ajax_add_note'));
				add_action('wp_ajax_wbtm_bkl_save_columns', array($this, 'ajax_save_columns'));
				add_action('admin_post_wbtm_bkl_export_csv', array($this, 'handle_export_csv'));
				add_action('admin_post_wbtm_bkl_export_pdf', array($this, 'handle_export_pdf'));
				add_action('admin_post_wbtm_bkl_export_thermal', array($this, 'handle_export_thermal'));
			}

			public function register_menu() {
				add_submenu_page(
					'edit.php?post_type=wbtm_bus',
					esc_html__('Booking List', 'bus-ticket-booking-with-seat-reservation'),
					esc_html__('Booking List', 'bus-ticket-booking-with-seat-reservation'),
					// Free installs: admins only (manage_options). The Pro add-on raises
					// this to 'wbtm_staff_access' via the filter so the screen can serve
					// as the Bus Staff landing page (it replaced the Pro Passenger List).
					// Destructive/admin-only controls stay gated on manage_options
					// separately (see $is_admin and the AJAX handlers).
					apply_filters('wbtm_booking_list_capability', 'manage_options'),
					self::PAGE_SLUG,
					array($this, 'render_page')
				);
			}

			/**
			 * Move the "Booking List" submenu directly beneath "Purchase Ticket"
			 * (wbtm_backend_order) — the slot the retired Passenger List used to hold —
			 * rather than leaving it appended at the bottom of the Bus menu. Runs on a
			 * very late admin_menu priority so all submenus are present, and matches by
			 * page slug so it's resilient to index shifts caused by other add-ons.
			 */
			public function reorder_submenu() {
				global $submenu;
				$parent = 'edit.php?post_type=wbtm_bus';
				if (empty($submenu[$parent]) || !is_array($submenu[$parent])) {
					return;
				}
				$items = array_values($submenu[$parent]);

				// Pull our own item out of the list.
				$our_item = null;
				foreach ($items as $i => $it) {
					if (isset($it[2]) && $it[2] === self::PAGE_SLUG) {
						$our_item = $it;
						unset($items[$i]);
						break;
					}
				}
				if (null === $our_item) {
					return;
				}
				$items = array_values($items);

				// Insert right after "Purchase Ticket"; fall back to the end if it's gone.
				$insert_at = count($items);
				foreach ($items as $i => $it) {
					if (isset($it[2]) && $it[2] === 'wbtm_backend_order') {
						$insert_at = $i + 1;
						break;
					}
				}
				array_splice($items, $insert_at, 0, array($our_item));
				$submenu[$parent] = $items;
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
					// QR check-in is delivered by the separate "QR Code" add-on. When it is
					// active the Booking List reuses its already-registered AJAX endpoint
					// (wbtm_bulk_update_ticket_status, secured with wbtm_pro_admin_nonce) so
					// there is no duplicate handler. When it is not active these stay inert.
					'qrActive' => class_exists('WBTM_QR_CODE_Functions'),
					'qrNonce'  => wp_create_nonce('wbtm_pro_admin_nonce'),
					// Export modal: the JS assembles wbtm_bl_* args from the chosen scope
					// and posts them to admin-post.php, one nonce per format handler.
					// 'currentFilters' is the filter bar's live state, so the "Current view"
					// scope exports exactly what is on screen; 'today' comes from the server
					// so a day-boundary export follows the site's timezone, not the browser's.
					'exportBase'     => admin_url('admin-post.php'),
					'exportNonces'   => array(
						'csv'     => wp_create_nonce('wbtm_bkl_export_csv'),
						'pdf'     => wp_create_nonce('wbtm_bkl_export_pdf'),
						'thermal' => wp_create_nonce('wbtm_bkl_export_thermal'),
					),
					'currentFilters' => $this->current_filter_query_args(),
					'today'          => current_time('Y-m-d'),
					'maxThermal'     => self::MAX_THERMAL_TICKETS,
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
						'columnsSaved'      => esc_html__('Column preferences saved.', 'bus-ticket-booking-with-seat-reservation'),
						'columnsError'      => esc_html__('Could not save column preferences.', 'bus-ticket-booking-with-seat-reservation'),
						'checkinDone'       => esc_html__('Check-in updated.', 'bus-ticket-booking-with-seat-reservation'),
						'checkinError'      => esc_html__('Could not update check-in.', 'bus-ticket-booking-with-seat-reservation'),
						'confirmCheckin'    => esc_html__('Check in %d booking(s)?', 'bus-ticket-booking-with-seat-reservation'),
						'confirmRevoke'     => esc_html__('Revoke check-in for %d booking(s)?', 'bus-ticket-booking-with-seat-reservation'),
						'exportNoFormat'    => esc_html__('Please choose an export format.', 'bus-ticket-booking-with-seat-reservation'),
						'exportNoRows'      => esc_html__('Tick the bookings you want to export first, or choose a different scope.', 'bus-ticket-booking-with-seat-reservation'),
						'exportBadRange'    => esc_html__('Please pick both a From and a To date.', 'bus-ticket-booking-with-seat-reservation'),
						'exportRangeOrder'  => esc_html__('The From date cannot be after the To date.', 'bus-ticket-booking-with-seat-reservation'),
						'exportTooManyThermal' => esc_html__('Thermal printing is capped at %d tickets per run. Narrow the selection down.', 'bus-ticket-booking-with-seat-reservation'),
						'exportStarted'     => esc_html__('Preparing your export…', 'bus-ticket-booking-with-seat-reservation'),
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
			 * AJAX: resend the e-voucher (PDF ticket) for a booking row. Optionally
			 * to a different email address (the admin can correct a wrong/changed
			 * address). Delegates to the PRO mailer via the wbtm_send_mail action,
			 * forcing it past the once-per-recipient guard.
			 */
			public function ajax_resend() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options')) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$id    = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;
				$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
				if (!$id || get_post_type($id) !== 'wbtm_bus_booking') {
					wp_send_json_error(array('message' => esc_html__('Invalid booking.', 'bus-ticket-booking-with-seat-reservation')));
				}
				$result = $this->resend_evoucher($id, $email);
				if (is_wp_error($result)) {
					wp_send_json_error(array('message' => $result->get_error_message()));
				}
				wp_send_json_success(array(
					'email'   => $result,
					/* translators: %s: recipient email address */
					'message' => sprintf(esc_html__('E-Voucher resent to %s.', 'bus-ticket-booking-with-seat-reservation'), $result),
				));
			}

			/**
			 * Shared resend routine. Returns the recipient email on success or a
			 * WP_Error explaining why it could not send.
			 *
			 * @param int    $booking_id wbtm_bus_booking post id.
			 * @param string $email      Optional new recipient; blank = keep billing email.
			 * @return string|WP_Error
			 */
			private function resend_evoucher($booking_id, $email = '') {
				$order_id = get_post_meta($booking_id, 'wbtm_order_id', true);
				if (!$order_id) {
					return new WP_Error('no_order', esc_html__('No order is linked to this booking.', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Standalone / Custom Payment booking: wbtm_order_id points at the group-head
				// wbtm_bus_booking (not a real WooCommerce order), so resend via the standalone
				// mailer instead of the WooCommerce e-voucher path.
				if (get_post_type($order_id) === 'wbtm_bus_booking') {
					return $this->resend_standalone_evoucher((int) $order_id, $email);
				}
				// The e-voucher mailer only handles real WooCommerce orders (PRO).
				if (!function_exists('wc_get_order') || !has_action('wbtm_send_mail')) {
					return new WP_Error('unsupported', esc_html__('E-Voucher resend requires WooCommerce and the PRO add-on.', 'bus-ticket-booking-with-seat-reservation'));
				}
				$order = wc_get_order($order_id);
				if (!$order) {
					return new WP_Error('no_wc_order', esc_html__('This booking is not linked to a WooCommerce order, so the e-voucher cannot be resent from here.', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Correct the billing email if a new one was supplied.
				if ($email && is_email($email) && strcasecmp($order->get_billing_email(), $email) !== 0) {
					$order->set_billing_email($email);
					$order->save();
					$order = wc_get_order($order_id);
				}
				$target = $order->get_billing_email();
				// Force past the once-per-recipient guard, then trigger the send.
				add_filter('wbtm_ticket_mail_force_resend', '__return_true');
				do_action('wbtm_send_mail', $order_id);
				remove_filter('wbtm_ticket_mail_force_resend', '__return_true');
				// Confirm delivery via the meta the mailer writes on success.
				$sent    = get_post_meta($order_id, '_wbtm_email_sent', true);
				$sent_to = (string) get_post_meta($order_id, '_wbtm_email_sent_to', true);
				if ($sent && $target && strcasecmp($sent_to, $target) === 0) {
					return $target;
				}
				return new WP_Error('not_sent', esc_html__('The e-voucher could not be sent. Check that "Send Ticket?" is enabled and the order status is eligible under Email settings.', 'bus-ticket-booking-with-seat-reservation'));
			}

			/**
			 * Resend the confirmation email for a Standalone / Custom Payment booking
			 * (Offline etc.), which has no WooCommerce order. Uses the standalone mailer's
			 * admin force-resend, which bypasses the "already sent" + status gates but still
			 * honours the "Send Ticket?" master switch. When a new email is supplied it is
			 * written across the whole booking group first.
			 *
			 * @param int    $head_id Group-head wbtm_bus_booking id (its wbtm_order_id === itself).
			 * @param string $email   Optional new recipient; blank keeps the stored email.
			 * @return string|WP_Error Recipient email on success.
			 */
			private function resend_standalone_evoucher($head_id, $email = '') {
				if (!class_exists('WBTM_Standalone_Mail') || !method_exists('WBTM_Standalone_Mail', 'force_resend')) {
					return new WP_Error('unsupported', esc_html__('Standalone confirmation email requires the booking engine (update the plugin/add-on).', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Correct the recipient across the whole booking group if a new one was given.
				if ($email && is_email($email)) {
					$group = get_posts(array(
						'post_type'      => 'wbtm_bus_booking',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_key'       => 'wbtm_order_id',
						'meta_value'     => $head_id,
					));
					if (empty($group)) {
						$group = array($head_id);
					}
					foreach ($group as $gid) {
						update_post_meta($gid, 'wbtm_user_email', $email);
					}
				}
				$sent = WBTM_Standalone_Mail::force_resend($head_id);
				if ($sent) {
					return (string) get_post_meta($head_id, 'wbtm_user_email', true);
				}
				return new WP_Error('not_sent', esc_html__('The confirmation email could not be sent. Check that "Send Ticket?" is enabled under Email settings and the booking has a valid customer email.', 'bus-ticket-booking-with-seat-reservation'));
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
			 * Whether the standalone "QR Code" add-on is active. Its presence adds a
			 * live "Check In" column plus bulk/row check-in actions to this list; when
			 * absent every QR affordance is omitted so nothing here depends on it.
			 */
			private function qr_active() {
				return class_exists('WBTM_QR_CODE_Functions');
			}

			/**
			 * Toggleable content columns (the leading checkbox + trailing Actions
			 * columns are always shown, so they're not listed here). The "Check In"
			 * column only exists while the QR add-on is active.
			 */
			private function get_columns() {
				$cols = array(
					'booking'      => esc_html__('Booking', 'bus-ticket-booking-with-seat-reservation'),
					'customer'     => esc_html__('Customer', 'bus-ticket-booking-with-seat-reservation'),
					'bus_route'    => esc_html__('Bus & Route', 'bus-ticket-booking-with-seat-reservation'),
					'journey_date' => esc_html__('Journey Date', 'bus-ticket-booking-with-seat-reservation'),
					'seat_ticket'  => esc_html__('Seat / Ticket', 'bus-ticket-booking-with-seat-reservation'),
					'total'        => esc_html__('Total', 'bus-ticket-booking-with-seat-reservation'),
					'status'       => esc_html__('Status', 'bus-ticket-booking-with-seat-reservation'),
					'booked_on'    => esc_html__('Booked On', 'bus-ticket-booking-with-seat-reservation'),
				);
				if ($this->qr_active()) {
					$cols['check_in'] = esc_html__('Check In', 'bus-ticket-booking-with-seat-reservation');
				}
				return $cols;
			}

			/**
			 * Per-user column show/hide preferences (Pro). Everything defaults to
			 * visible; a saved preference only ever hides columns the user turned off.
			 * Free installs always get the full default set (the settings UI is locked).
			 */
			private function get_column_visibility() {
				$defaults = array();
				foreach (array_keys($this->get_columns()) as $key) {
					$defaults[$key] = true;
				}
				if (!$this->is_pro()) {
					return $defaults;
				}
				$saved = get_user_meta(get_current_user_id(), 'wbtm_bkl_column_visibility', true);
				if (!is_array($saved)) {
					return $defaults;
				}
				// Merge so newly-added columns default to visible for existing users.
				return array_merge($defaults, array_intersect_key($saved, $defaults));
			}

			/**
			 * Inline style attribute that hides a cell when its column is toggled off.
			 * Returns '' for a visible column so the markup stays clean.
			 */
			private function col_style($vis, $key) {
				return (isset($vis[$key]) && !$vis[$key]) ? ' style="display:none;"' : '';
			}

			/**
			 * AJAX (Pro): persist this user's column show/hide choices. Keyed to the
			 * current user via user meta, so it never affects other admins/staff.
			 */
			public function ajax_save_columns() {
				check_ajax_referer('wbtm_bkl_actions', 'nonce');
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'bus-ticket-booking-with-seat-reservation')), 403);
				}
				$raw = isset($_POST['columns']) && is_array($_POST['columns']) ? wp_unslash($_POST['columns']) : array();
				$visibility = array();
				foreach (array_keys($this->get_columns()) as $key) {
					// A column is visible unless it was explicitly sent as off ('0'/false).
					$val = isset($raw[$key]) ? sanitize_text_field($raw[$key]) : '1';
					$visibility[$key] = !($val === '0' || $val === 'false' || $val === '');
				}
				update_user_meta(get_current_user_id(), 'wbtm_bkl_column_visibility', $visibility);
				wp_send_json_success(array(
					'visibility' => $visibility,
					'message'    => esc_html__('Column preferences saved.', 'bus-ticket-booking-with-seat-reservation'),
				));
			}

			/**
			 * Pro-only: stream all matching bookings (respecting the current filters)
			 * as a CSV download. Gated on is_pro() so the Export dialog is never wired
			 * up to a working action in free installs.
			 */
			public function handle_export_csv() {
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_die(esc_html__('You do not have permission to do that.', 'bus-ticket-booking-with-seat-reservation'));
				}
				check_admin_referer('wbtm_bkl_export_csv');

				$args = $this->build_query_args(-1, 1);
				$ids  = get_posts(array_merge($args, array('fields' => 'ids')));

				// Every passenger-form field (built-in + custom, e.g. "Emergency Contact
				// Name") becomes a trailing column, so anything the booking form collects
				// leaves in the export — see passenger_field_columns().
				$pax_cols = $this->passenger_field_columns($ids);

				nocache_headers();
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=wbtm-bookings-' . gmdate('Y-m-d') . '.csv');
				$out = fopen('php://output', 'w');
				// 'Total' is tax-inclusive (store prices include tax); 'Tax (incl.)' is the
				// portion of it that is tax and 'Net (excl. Tax)' the remainder — they are
				// breakdowns of Total, not additions to it.
				$header = array('Booking ID', 'Order ID', 'Source', 'Customer', 'Email', 'Phone', 'Address', 'Passenger', 'Journey Leg', 'Bus', 'Boarding', 'Dropping', 'Journey Date', 'Seat', 'Ticket', 'Fare', 'Extra Services', 'Extra Service Details', 'Total', 'Tax (incl.)', 'Net (excl. Tax)', 'Payment Plan', 'Deposit Paid', 'Remaining Due', 'Balance Due Date', 'Status', 'Booked On');
				foreach ($pax_cols as $pax_label) {
					$header[] = $pax_label;
				}
				fputcsv($out, $header);
				foreach ($ids as $id) {
					$bus_id = (int) get_post_meta($id, 'wbtm_bus_id', true);
					$pax    = $this->passenger_bits($id);
					$phone  = get_post_meta($id, 'wbtm_user_phone', true) ?: $pax['phone'];
					$addr   = get_post_meta($id, 'wbtm_user_address', true) ?: $pax['address'];
					$leg    = get_post_meta($id, 'wbtm_journey_type', true) === 'return' ? 'Return' : 'Outbound';
					$fare   = (float) get_post_meta($id, 'wbtm_bus_fare', true);
					$extras = $this->extra_services_total($id);
					$tax    = $this->booking_tax($id);
					$dep    = $this->deposit_info($id);
					$row    = array(
						$id,
						get_post_meta($id, 'wbtm_order_id', true),
						$this->booking_source($id) === 'standalone' ? 'Custom' : 'WooCommerce',
						get_post_meta($id, 'wbtm_user_name', true),
						get_post_meta($id, 'wbtm_user_email', true),
						$phone,
						$addr,
						$pax['name'],
						$leg,
						$bus_id ? get_the_title($bus_id) : '',
						get_post_meta($id, 'wbtm_boarding_point', true),
						get_post_meta($id, 'wbtm_dropping_point', true),
						get_post_meta($id, 'wbtm_boarding_time', true) ?: get_post_meta($id, 'wbtm_booking_date', true),
						$this->seat_label($id),
						get_post_meta($id, 'wbtm_ticket', true),
						$fare,
						$extras,
						$this->extra_services_label($id),
						$fare + $extras,
						$tax,
						round(max(0, ($fare + $extras) - $tax), 2),
						$dep['is_deposit'] ? ($dep['remaining'] > 0 ? 'Deposit' : 'Deposit (settled)') : 'Full',
						$dep['paid'],
						$dep['remaining'],
						$dep['due_date'],
						get_post_meta($id, 'wbtm_order_status', true),
						get_post_meta($id, 'wbtm_booking_date', true) ?: get_the_date('Y-m-d H:i', $id),
					);
					if (!empty($pax_cols)) {
						$answers = $this->passenger_field_answers($id);
						foreach ($pax_cols as $field_id => $pax_label) {
							$row[] = $this->passenger_field_answer($answers, $field_id, $pax_label);
						}
					}
					fputcsv($out, $row);
				}
				fclose($out);
				exit;
			}

			/**
			 * The passenger-form columns an export should carry: field_id => label.
			 *
			 * Buses configure their booking form as two post-meta lists — the built-in
			 * fields (wbtm_attendee_info) and the admin's own additions
			 * (wbtm_custom_attendee_info) — and every booked seat stores whatever the
			 * passenger typed under the same field ids. The bus config is read first so
			 * headers use the configured labels in the configured order (and stay
			 * consistent even when a passenger left a field blank); a second pass over
			 * the bookings themselves picks up any field that has since been removed or
			 * renamed in the form, so previously-collected answers can never silently
			 * drop out of the export.
			 *
			 * Only buses present in the exported set are inspected, so a filtered export
			 * gets exactly that bus's fields rather than every field on the site.
			 */
			private function passenger_field_columns(array $ids) {
				$cols    = array();
				$bus_ids = array();
				foreach ($ids as $id) {
					$bus_id = (int) get_post_meta($id, 'wbtm_bus_id', true);
					if ($bus_id > 0) {
						$bus_ids[$bus_id] = true;
					}
				}
				foreach (array_keys($bus_ids) as $bus_id) {
					foreach (array('wbtm_attendee_info', 'wbtm_custom_attendee_info') as $meta_key) {
						$fields = get_post_meta($bus_id, $meta_key, true);
						if (!is_array($fields)) {
							continue;
						}
						foreach ($fields as $field) {
							if (!is_array($field) || empty($field['field_id'])) {
								continue;
							}
							// 'active' absent means active (older bus configs predate the flag).
							if (array_key_exists('active', $field) && !$field['active']) {
								continue;
							}
							$field_id = (string) $field['field_id'];
							if (isset($cols[$field_id])) {
								continue;
							}
							$label = '';
							if (!empty($field['field_label'])) {
								$label = $field['field_label'];
							} elseif (!empty($field['d_label'])) {
								$label = $field['d_label'];
							}
							$cols[$field_id] = $label !== '' ? $label : $this->humanize_field_id($field_id);
						}
					}
				}
				// Labels already claimed by the bus config, lowercased for comparison. A
				// booking-level field carrying one of these is the SAME question under a
				// different id (a renamed field id, an older form revision), so it must
				// merge into the existing column — passenger_field_answer() finds its value
				// by label. Adding a second column instead printed the answer twice.
				$claimed = array();
				foreach ($cols as $existing_label) {
					$claimed[strtolower(trim((string) $existing_label))] = true;
				}
				foreach ($ids as $id) {
					$info = get_post_meta($id, 'wbtm_attendee_info', true);
					if (!is_array($info)) {
						continue;
					}
					foreach ($info as $field_id => $field) {
						// Legacy bookings store a numerically-indexed list instead of a
						// field_id map; those are matched by label in passenger_field_answer().
						if (!is_string($field_id) || $field_id === '' || isset($cols[$field_id])) {
							continue;
						}
						$label = (is_array($field) && !empty($field['name'])) ? $field['name'] : '';
						$label = $label !== '' ? $label : $this->humanize_field_id($field_id);
						$key   = strtolower(trim($label));
						if (isset($claimed[$key])) {
							continue;
						}
						$claimed[$key]   = true;
						$cols[$field_id] = $label;
					}
				}
				return $cols;
			}

			/**
			 * A booking's passenger-form answers, indexed both by field id and by the
			 * label stored alongside each answer. The label index is what lets legacy
			 * bookings (stored as a numeric list of {name, value} pairs rather than a
			 * field_id map) still line up with the columns.
			 */
			private function passenger_field_answers($id) {
				$answers = array('by_id' => array(), 'by_label' => array());
				$info    = get_post_meta($id, 'wbtm_attendee_info', true);
				if (!is_array($info)) {
					return $answers;
				}
				foreach ($info as $field_id => $field) {
					$value = is_array($field) ? ($field['value'] ?? '') : $field;
					$value = is_array($value) ? implode(', ', $value) : (string) $value;
					if (is_string($field_id) && $field_id !== '') {
						$answers['by_id'][$field_id] = $value;
					}
					$label = (is_array($field) && !empty($field['name'])) ? (string) $field['name'] : '';
					if ($label !== '' && !isset($answers['by_label'][$label])) {
						$answers['by_label'][$label] = $value;
					}
				}
				return $answers;
			}

			/**
			 * One passenger-form answer for one column — by field id, falling back to
			 * the column label for legacy bookings. Missing answers export as blank so
			 * every row keeps the same column count as the header.
			 */
			private function passenger_field_answer(array $answers, $field_id, $label) {
				if (isset($answers['by_id'][$field_id])) {
					return $answers['by_id'][$field_id];
				}
				if ($label !== '' && isset($answers['by_label'][$label])) {
					return $answers['by_label'][$label];
				}
				return '';
			}

			/**
			 * One booking's answered passenger-form fields as an escaped "Label: value"
			 * strip for the PDF export, or '' when the passenger filled nothing in.
			 * Column order follows $pax_cols so the strips stay consistent down the page.
			 */
			private function passenger_field_strip($id, array $pax_cols) {
				if (empty($pax_cols)) {
					return '';
				}
				$answers = $this->passenger_field_answers($id);
				$bits    = array();
				foreach ($pax_cols as $field_id => $label) {
					$value = $this->passenger_field_answer($answers, $field_id, $label);
					if ($value === '') {
						continue;
					}
					$bits[] = '<span class="lbl">' . esc_html($label) . ':</span> ' . esc_html($value);
				}
				return $bits ? implode(' &nbsp;&bull;&nbsp; ', $bits) : '';
			}

			/**
			 * A price formatted for the PDF.
			 *
			 * Amounts are deliberately NEVER bold. mPDF resolves each weight to a
			 * separate font file, and the bold faces bundled with it are missing several
			 * currency glyphs that the regular faces have — the Bangladeshi Taka (৳)
			 * among them — so a bold price printed the amount with a tofu box where the
			 * symbol should be. Emphasis in the totals row comes from its tint and rule
			 * instead of weight, which costs nothing and always renders.
			 *
			 * @param float $amount Value to format.
			 * @param bool  $markup Wrap in a span (false when the caller escapes the
			 *                      result itself, e.g. inside a translated sentence).
			 */
			private function pdf_money($amount, $markup = true) {
				$price = wp_strip_all_tags(WBTM_Global_Function::format_price($amount));
				// format_price() returns an HTML entity for many symbols (&#2547; for ৳);
				// mPDF needs the real character, and this string is escaped from here on.
				$price = html_entity_decode($price, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
				return $markup ? '<span style="font-weight:normal;">' . esc_html($price) . '</span>' : $price;
			}

			/**
			 * Readable header for a field that carries no configured label
			 * (wbtm_emergency_contact => "Emergency Contact").
			 */
			private function humanize_field_id($field_id) {
				$clean = preg_replace('/^wbtm[_-]/', '', (string) $field_id);
				return ucwords(str_replace(array('_', '-'), ' ', $clean));
			}

			/**
			 * Pro-only: a full booking-list PDF export. Unlike the Pro per-bus manifest
			 * (which needs a bus selected), this renders EVERY matching booking —
			 * respecting the current filters — as a landscape table, so admins can
			 * export the whole list to PDF without picking a bus. Built with the same
			 * mPDF the Pro add-on ships, but from our own query (the CSV's twin).
			 */
			public function handle_export_pdf() {
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_die(esc_html__('You do not have permission to do that.', 'bus-ticket-booking-with-seat-reservation'));
				}
				check_admin_referer('wbtm_bkl_export_pdf');
				if (!class_exists('WBTM_Pro_Pdf') || !WBTM_Pro_Pdf::is_mpdf_available()) {
					wp_die(esc_html__('PDF generation is unavailable. Please install the MagePeople PDF Support plugin.', 'bus-ticket-booking-with-seat-reservation'));
				}

				$args = $this->build_query_args(-1, 1);
				$ids  = get_posts(array_merge($args, array('fields' => 'ids')));

				// Same passenger-form fields the CSV exports — see passenger_field_columns().
				$pax_cols = $this->passenger_field_columns($ids);

				$rows = '';
				$grand_total  = 0;
				$grand_extras = 0;
				$grand_tax    = 0;
				$bus_seen     = array();
				foreach ($ids as $id) {
					$bus_id = (int) get_post_meta($id, 'wbtm_bus_id', true);
					if ($bus_id) {
						$bus_seen[$bus_id] = true;
					}
					$order_id = get_post_meta($id, 'wbtm_order_id', true);
					$pax    = $this->passenger_bits($id);
					$name   = get_post_meta($id, 'wbtm_user_name', true) ?: $pax['name'];
					$phone  = get_post_meta($id, 'wbtm_user_phone', true) ?: $pax['phone'];
					$bp     = get_post_meta($id, 'wbtm_boarding_point', true);
					$dp     = get_post_meta($id, 'wbtm_dropping_point', true);
					$jdate  = get_post_meta($id, 'wbtm_boarding_time', true) ?: get_post_meta($id, 'wbtm_booking_date', true);
					$seat   = get_post_meta($id, 'wbtm_seat', true);
					$ticket = get_post_meta($id, 'wbtm_ticket', true);
					$fare   = (float) get_post_meta($id, 'wbtm_bus_fare', true);
					$extras = $this->extra_services_total($id);
					$total  = $fare + $extras;
					// $total is tax-inclusive already; $tax is the slice of it that is tax.
					$tax    = $this->booking_tax($id);
					$dep    = $this->deposit_info($id);
					$status = get_post_meta($id, 'wbtm_order_status', true);
					$leg    = get_post_meta($id, 'wbtm_journey_type', true) === 'return' ? esc_html__('Return', 'bus-ticket-booking-with-seat-reservation') : esc_html__('Outbound', 'bus-ticket-booking-with-seat-reservation');
					$source = $this->booking_source($id) === 'standalone' ? esc_html__('Custom', 'bus-ticket-booking-with-seat-reservation') : esc_html__('WooCommerce', 'bus-ticket-booking-with-seat-reservation');
					$grand_total  += $total;
					$grand_extras += $extras;
					$grand_tax    += $tax;

					$route = trim($bp . ($dp ? ' → ' . $dp : ''));
					$cust  = '<strong>' . esc_html($name ?: '—') . '</strong>' . ($phone ? '<br><span class="sub">' . esc_html($phone) . '</span>' : '');
					$seatt = '<strong>' . esc_html($this->seat_label($id)) . '</strong>' . ($ticket ? '<br><span class="sub">' . esc_html($ticket) . '</span>' : '');
					$exlabel = $this->extra_services_label($id);
					$excell  = $extras > 0
						? $this->pdf_money($extras) . ($exlabel ? '<br><span class="sub">' . esc_html($exlabel) . '</span>' : '')
						: '<span class="nil">—</span>';
					$taxcell = $tax > 0 ? $this->pdf_money($tax) : '<span class="nil">—</span>';
					// Deposit bookings show what was actually collected vs still owed,
					// since the Total column is the full (tax-inclusive) ticket price.
					$depnote = '';
					if ($dep['is_deposit'] && $dep['remaining'] > 0) {
						$depnote = '<br><span class="due">' . esc_html(sprintf(
							/* translators: 1: amount paid so far, 2: outstanding balance. */
							__('paid %1$s · due %2$s', 'bus-ticket-booking-with-seat-reservation'),
							$this->pdf_money($dep['paid'], false),
							$this->pdf_money($dep['remaining'], false)
						)) . '</span>';
					}

					$rows .= '<tr>'
						. '<td><strong>#' . esc_html($order_id ?: $id) . '</strong><br><span class="sub">ID ' . esc_html($id) . '</span></td>'
						. '<td><span class="tag">' . esc_html($source) . '</span></td>'
						. '<td>' . $cust . '</td>'
						. '<td>' . esc_html($bus_id ? get_the_title($bus_id) : '—') . '</td>'
						. '<td>' . esc_html($route ?: '—') . '</td>'
						. '<td>' . esc_html($jdate ?: '—') . '</td>'
						. '<td>' . $seatt . '</td>'
						. '<td class="num">' . $this->pdf_money($fare) . '</td>'
						. '<td class="num">' . $excell . '</td>'
						. '<td class="num">' . $taxcell . '</td>'
						. '<td class="num strong-num">' . $this->pdf_money($total) . $depnote . '</td>'
						. '<td>' . esc_html(ucfirst(str_replace('wc-', '', (string) $status)) ?: '—') . '</td>'
						. '<td>' . esc_html($leg) . '</td>'
						. '</tr>';
					// Passenger-form answers (built-in + custom fields such as "Emergency
					// Contact Name") ride along as a full-width continuation strip under the
					// booking rather than one column per field: a booking form can carry any
					// number of fields, and extra columns would collapse this already
					// 13-column landscape table. Bookings with nothing filled in get no strip.
					$pax_strip = $this->passenger_field_strip($id, $pax_cols);
					if ($pax_strip !== '') {
						$rows .= '<tr><td class="pax" colspan="13">' . $pax_strip . '</td></tr>';
					}
				}
				if ($rows === '') {
					$rows = '<tr><td colspan="13" class="empty">' . esc_html__('No bookings found.', 'bus-ticket-booking-with-seat-reservation') . '</td></tr>';
				}

				$generated = date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
				$count     = count($ids);
				$net       = max(0, $grand_total - $grand_tax);

				// Light, print-first palette: white paper, slate ink, hairline rules, and the
				// plugin's rose used only for the accent rule, the summary figures and the
				// totals row. Nothing here relies on a dark fill, so it stays legible on a
				// mono office printer and doesn't drink toner.
				$html  = '<style>'
					// freesans, not sans-serif: mPDF's default (DejaVu) has no glyph for
					// several currency signs — the Bangladeshi Taka (৳) among them — and
					// printed a tofu box. See pdf_money() for why amounts are never bold.
					. 'body{font-family:freesans;color:#1f2937;font-size:8.6pt;}'
					. '.doc-head{width:100%;border-collapse:collapse;margin:0 0 2mm;}'
					. '.doc-head td{padding:0;border:0;vertical-align:bottom;}'
					. '.doc-title{font-size:16pt;font-weight:bold;color:#0f172a;letter-spacing:-.2pt;}'
					. '.doc-sub{font-size:8pt;color:#94a3b8;padding-top:1mm;}'
					. '.doc-org{text-align:right;font-size:9.5pt;font-weight:bold;color:#475569;}'
					. '.org-host{font-size:7.5pt;font-weight:normal;color:#a8b3c2;}'
					. '.rule{height:2px;background:#e63946;font-size:0;line-height:0;margin:0 0 4mm;}'
					// Summary strip: four light cards, figures in rose, labels in muted caps.
					. '.sum{width:100%;border-collapse:separate;border-spacing:2.5mm 0;margin:0 0 4mm;}'
					. '.sum td{background:#f8fafc;border:1px solid #eaeff5;padding:2.6mm 3mm;width:25%;}'
					. '.sum .k{font-size:7pt;color:#94a3b8;text-transform:uppercase;letter-spacing:.3pt;}'
					. '.sum .v{font-size:12pt;color:#e63946;padding-top:.6mm;}'
					. 'table.list{width:100%;border-collapse:collapse;}'
					. 'table.list th{background:#f7f9fc;text-align:left;padding:2.4mm 2mm;font-size:7pt;font-weight:bold;'
						. 'text-transform:uppercase;letter-spacing:.3pt;color:#64748b;border-bottom:1px solid #dde5ee;}'
					. 'table.list td{padding:2.4mm 2mm;border-bottom:1px solid #eef2f7;vertical-align:top;line-height:1.35;}'
					. 'table.list td.num{text-align:right;white-space:nowrap;}'
					. 'table.list td.strong-num{color:#0f172a;}'
					. '.sub{color:#98a4b3;font-size:7.4pt;}'
					. '.nil{color:#cbd5e1;}'
					. '.due{color:#c0392b;font-size:7.4pt;}'
					. '.tag{background:#eef2f7;color:#5b6878;font-size:7pt;padding:.6mm 1.4mm;}'
					// Passenger-form answers: a tinted continuation strip under each booking.
					. 'td.pax{background:#f9fbfd;color:#475569;font-size:7.4pt;padding:1.6mm 2mm 2mm;border-bottom:1px solid #eef2f7;}'
					. 'td.pax .lbl{color:#9aa6b5;}'
					. 'td.empty{text-align:center;color:#a8b3c2;padding:12mm 2mm;}'
					. 'tfoot td{background:#fdf2f4;border-top:1.5px solid #e63946;border-bottom:0;color:#8f1d28;padding:2.8mm 2mm;}'
					. 'tfoot .lbl{text-align:right;font-weight:bold;text-transform:uppercase;font-size:7.5pt;letter-spacing:.3pt;}'
					. '</style>';

				$html .= '<table class="doc-head"><tr>'
					. '<td><div class="doc-title">' . esc_html__('Booking List', 'bus-ticket-booking-with-seat-reservation') . '</div>'
					. '<div class="doc-sub">' . sprintf(
						/* translators: 1: number of bookings, 2: date/time generated. */
						esc_html__('%1$s booking(s) · generated %2$s', 'bus-ticket-booking-with-seat-reservation'),
						esc_html(number_format_i18n($count)),
						esc_html($generated)
					) . '</div></td>'
					// Explicit <br>: mPDF ignores display:block on an inline element, so a
					// styled <small> alone left the host glued to the site name.
					. '<td class="doc-org">' . esc_html(get_bloginfo('name'))
					. '<br><span class="org-host">' . esc_html(wp_parse_url(home_url(), PHP_URL_HOST)) . '</span></td>'
					. '</tr></table><div class="rule"></div>';

				$summary = array(
					array(esc_html__('Bookings', 'bus-ticket-booking-with-seat-reservation'), esc_html(number_format_i18n($count))),
					array(esc_html__('Revenue (incl. tax)', 'bus-ticket-booking-with-seat-reservation'), $this->pdf_money($grand_total)),
					array(esc_html__('Net (excl. tax)', 'bus-ticket-booking-with-seat-reservation'), $this->pdf_money($net)),
					array(esc_html__('Buses', 'bus-ticket-booking-with-seat-reservation'), esc_html(number_format_i18n(count($bus_seen)))),
				);
				$html .= '<table class="sum"><tr>';
				foreach ($summary as $card) {
					$html .= '<td><div class="k">' . $card[0] . '</div><div class="v">' . $card[1] . '</div></td>';
				}
				$html .= '</tr></table>';

				// Fixed column widths (percent of the table): with auto layout a long extra
				// service name stole space from the money columns and pushed "Tax (incl.)"
				// onto two lines. Widths total 100 and are ordered as the row cells above.
				$columns = array(
					array(__('Booking', 'bus-ticket-booking-with-seat-reservation'), 7, 'left'),
					array(__('Source', 'bus-ticket-booking-with-seat-reservation'), 7, 'left'),
					array(__('Customer', 'bus-ticket-booking-with-seat-reservation'), 12, 'left'),
					array(__('Bus', 'bus-ticket-booking-with-seat-reservation'), 7, 'left'),
					array(__('Route', 'bus-ticket-booking-with-seat-reservation'), 9, 'left'),
					array(__('Journey Date', 'bus-ticket-booking-with-seat-reservation'), 8.5, 'left'),
					array(__('Seat / Ticket', 'bus-ticket-booking-with-seat-reservation'), 6.5, 'left'),
					array(__('Fare', 'bus-ticket-booking-with-seat-reservation'), 6.5, 'right'),
					array(__('Extra Services', 'bus-ticket-booking-with-seat-reservation'), 10, 'right'),
					array(__('Tax (incl.)', 'bus-ticket-booking-with-seat-reservation'), 5.5, 'right'),
					array(__('Total', 'bus-ticket-booking-with-seat-reservation'), 7, 'right'),
					array(__('Status', 'bus-ticket-booking-with-seat-reservation'), 7.5, 'left'),
					array(__('Leg', 'bus-ticket-booking-with-seat-reservation'), 6.5, 'left'),
				);
				$html .= '<table class="list"><thead><tr>';
				foreach ($columns as $column) {
					$html .= '<th style="width:' . esc_attr($column[1]) . '%;text-align:' . esc_attr($column[2]) . ';">'
						. esc_html($column[0]) . '</th>';
				}
				$html .= '</tr></thead><tbody>' . $rows . '</tbody>'
					. '<tfoot><tr>'
					. '<td colspan="8" class="lbl">' . esc_html__('Grand Total', 'bus-ticket-booking-with-seat-reservation') . '</td>'
					. '<td class="num">' . $this->pdf_money($grand_extras) . '</td>'
					. '<td class="num">' . $this->pdf_money($grand_tax) . '</td>'
					. '<td class="num">' . $this->pdf_money($grand_total) . '</td>'
					. '<td colspan="2"></td></tr></tfoot>'
					. '</table>';

				// Raise PCRE limits for large lists (mirrors the Pro generator's guard),
				// so mPDF's WriteHTML() never fatals on a long table.
				$needed = max(1000000, strlen($html) * 3);
				if ((int) ini_get('pcre.backtrack_limit') < $needed) { @ini_set('pcre.backtrack_limit', (string) $needed); }
				if ((int) ini_get('pcre.recursion_limit') < 200000) { @ini_set('pcre.recursion_limit', '200000'); }

				try {
					$mpdf = new \Mpdf\Mpdf(array(
						'mode'          => 'utf-8',
						'format'        => 'A4-L',
						'margin_top'    => 12,
						'margin_bottom' => 14,
						'margin_left'   => 10,
						'margin_right'  => 10,
						// Match the stylesheet so the page footer resolves the same glyphs.
						'default_font'  => 'freesans',
					));
					// Repeat the column headers on every page and number the pages, so a
					// multi-page manifest is still readable once it is off the screen.
					$mpdf->SetHTMLFooter(
						'<table width="100%" style="font-family:freesans;font-size:7pt;color:#a8b3c2;border-top:1px solid #eef2f7;padding-top:1.5mm;"><tr>'
						. '<td>' . esc_html(get_bloginfo('name')) . '</td>'
						. '<td style="text-align:right;">' . esc_html__('Page', 'bus-ticket-booking-with-seat-reservation') . ' {PAGENO} / {nbpg}</td>'
						. '</tr></table>'
					);
					$mpdf->WriteHTML($html);
					$mpdf->Output('bookings-' . gmdate('Y-m-d') . '.pdf', 'D');
				} catch (\Throwable $e) {
					error_log('WBTM Booking List PDF export failed — ' . $e->getMessage());
					wp_die(esc_html__('Could not generate the PDF. Please try again.', 'bus-ticket-booking-with-seat-reservation'));
				}
				exit;
			}

			/**
			 * Pro-only: print one thermal (POS) receipt ticket per matching booking as a
			 * single roll. The receipt layout itself is the Pro add-on's, so this resolves
			 * the booking set from the shared query builder and hands the id list to the
			 * Pro generator (which already owns roll width, height estimation and the
			 * per-ticket template) rather than duplicating any of that here.
			 *
			 * Capped at MAX_THERMAL_TICKETS: this is meant for a counter or a departure,
			 * the ids travel in the redirect URL, and nobody prints thousands of receipts
			 * by accident. Over the cap we stop and say so instead of silently truncating.
			 */
			const MAX_THERMAL_TICKETS = 200;

			public function handle_export_thermal() {
				if (!current_user_can('manage_options') || !$this->is_pro()) {
					wp_die(esc_html__('You do not have permission to do that.', 'bus-ticket-booking-with-seat-reservation'));
				}
				check_admin_referer('wbtm_bkl_export_thermal');
				if (!class_exists('WBTM_Pro_Pdf') || !WBTM_Pro_Pdf::is_mpdf_available()) {
					wp_die(esc_html__('PDF generation is unavailable. Please install the MagePeople PDF Support plugin.', 'bus-ticket-booking-with-seat-reservation'));
				}
				if (!class_exists('WBTM_Global_Function') || WBTM_Global_Function::get_settings('wbtm_pdf_settings', 'thermal_ticket_enable', 'yes') !== 'yes') {
					wp_die(esc_html__('Thermal (POS) tickets are turned off in PDF settings.', 'bus-ticket-booking-with-seat-reservation'));
				}

				$args = $this->build_query_args(-1, 1);
				$ids  = get_posts(array_merge($args, array('fields' => 'ids')));
				if (empty($ids)) {
					wp_die(esc_html__('No bookings matched, so there is nothing to print.', 'bus-ticket-booking-with-seat-reservation'));
				}
				if (count($ids) > self::MAX_THERMAL_TICKETS) {
					wp_die(esc_html(sprintf(
						/* translators: 1: number of matching bookings, 2: maximum allowed. */
						__('That selection is %1$s bookings and thermal printing is capped at %2$s tickets per run. Narrow it down — by bus, departure date, or by ticking rows — and try again.', 'bus-ticket-booking-with-seat-reservation'),
						number_format_i18n(count($ids)),
						number_format_i18n(self::MAX_THERMAL_TICKETS)
					)));
				}

				// get_pdf_url() ends in wp_nonce_url(), which esc_html()s the result for use
				// in an href — so its separators arrive as "&amp;". Sent as a Location
				// header that would become a parameter literally named "amp;attendee_ids"
				// and the roll would come back blank, so decode back to a real URL first.
				$pdf_url = wp_specialchars_decode(WBTM_Pro_Pdf::get_pdf_url(array(
					'attendee_ids' => implode(',', array_map('absint', $ids)),
					'thermal'      => 1,
				)), ENT_QUOTES);
				wp_safe_redirect($pdf_url);
				exit;
			}

			/**
			 * Aggregate stats for the bookings the CURRENT FILTERS select.
			 *
			 * This deliberately runs build_query_args() — the exact same query the
			 * table and the CSV/PDF exports use — rather than its own global SQL, so
			 * the cards can never disagree with the rows on screen or with a bus-wise
			 * export. With no filters set it naturally covers every booking.
			 *
			 * Everything is summed per booking (not in SQL) because the pieces simply
			 * aren't SQL-summable: extra services live in a serialized blob, and tax
			 * lives on the WooCommerce order, not on the booking post. One
			 * update_meta_cache() primes the meta for the whole set so the per-booking
			 * helpers below don't each fire their own query.
			 */
			private function get_stats() {
				$ids = get_posts(array_merge($this->build_query_args(-1, 1), array('fields' => 'ids')));
				$empty = array(
					'total_bookings'    => 0,
					'total_revenue'     => 0.0,
					'total_buses'       => 0,
					'total_tax'         => 0.0,
					'total_outstanding' => 0.0,
				);
				if (empty($ids)) {
					return $empty;
				}
				update_meta_cache('post', $ids);

				$revenue = 0.0;
				$tax     = 0.0;
				$due     = 0.0;
				$buses   = array();
				foreach ($ids as $id) {
					// Revenue is the tax-inclusive amount customers actually paid
					// (fare + extras); $tax is the slice of it that is tax, so the two
					// must never be added together.
					$revenue += $this->booking_total($id);
					// Summed unrounded, then rounded once — rounding each seat first
					// would drift on multi-seat lines.
					$tax     += $this->booking_tax_raw($id);
					$dep      = $this->deposit_info($id);
					$due     += $dep['remaining'];
					$bus_id   = (int) get_post_meta($id, 'wbtm_bus_id', true);
					if ($bus_id > 0) {
						$buses[$bus_id] = true;
					}
				}
				return array(
					'total_bookings'    => count($ids),
					'total_revenue'     => $revenue,
					'total_buses'       => count($buses),
					'total_tax'         => round($tax, 2),
					'total_outstanding' => $due,
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

				// Explicit booking ids (the export modal's "Selected bookings" scope).
				// get_filter_values() collapses an unparseable list to array(0) rather
				// than array(), because WP_Query ignores an EMPTY post__in and would
				// silently widen a hand-picked export to every booking.
				if (!empty($f['ids'])) {
					$args['post__in'] = $f['ids'];
				}

				// Per-page override (Pro).
				if ($per_page > 0 && $f['per_page'] > 0) {
					$args['posts_per_page'] = $f['per_page'];
				}

				// Sort (Pro). Journey/seat sorts order by the matching meta value.
				switch ($f['sort']) {
					case 'oldest':
						$args['orderby'] = 'date';
						$args['order']   = 'ASC';
						break;
					case 'journey_asc':
						$args['meta_key'] = 'wbtm_boarding_time';
						$args['orderby']  = 'meta_value';
						$args['order']    = 'ASC';
						break;
					case 'journey_desc':
						$args['meta_key'] = 'wbtm_boarding_time';
						$args['orderby']  = 'meta_value';
						$args['order']    = 'DESC';
						break;
					case 'seat':
						$args['meta_key'] = 'wbtm_seat';
						$args['orderby']  = 'meta_value';
						$args['order']    = 'ASC';
						break;
					case 'newest':
					default:
						$args['orderby'] = 'date';
						$args['order']   = 'DESC';
						break;
				}

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
				if ($f['phone'] !== '') {
					// Billing phone or any passenger phone captured in attendee info.
					$meta_query[] = array(
						'relation' => 'OR',
						array('key' => 'wbtm_user_phone', 'value' => $f['phone'], 'compare' => 'LIKE'),
						array('key' => 'wbtm_attendee_info', 'value' => $f['phone'], 'compare' => 'LIKE'),
					);
				}
				if ($f['passenger'] !== '') {
					// Passenger-level match: attendee info (name/email/phone/address/custom
					// fields are stored serialized in wbtm_attendee_info) or the billing name.
					$meta_query[] = array(
						'relation' => 'OR',
						array('key' => 'wbtm_attendee_info', 'value' => $f['passenger'], 'compare' => 'LIKE'),
						array('key' => 'wbtm_user_name', 'value' => $f['passenger'], 'compare' => 'LIKE'),
					);
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
				$allowed_sort = array('newest', 'oldest', 'journey_asc', 'journey_desc', 'seat');
				$sort = isset($_GET['wbtm_bl_sort']) ? sanitize_key(wp_unslash($_GET['wbtm_bl_sort'])) : '';
				if (!in_array($sort, $allowed_sort, true)) {
					$sort = 'newest';
				}
				$per_page = isset($_GET['wbtm_bl_per_page']) ? absint($_GET['wbtm_bl_per_page']) : 0;
				if ($per_page < 1 || $per_page > 500) {
					$per_page = self::PER_PAGE;
				}
				// Hand-picked booking ids, used by the export modal's "Selected bookings"
				// scope. A present-but-unparseable list becomes array(0) — a deliberately
				// unmatchable post__in — so a malformed request exports nothing rather
				// than everything (WP_Query ignores an empty post__in).
				$ids = array();
				if (isset($_GET['wbtm_bl_ids'])) {
					$raw_ids  = wp_unslash($_GET['wbtm_bl_ids']);
					$requested = is_array($raw_ids) ? $raw_ids : array_filter(array_map('trim', explode(',', (string) $raw_ids)), 'strlen');
					$ids       = array_values(array_filter(array_map('absint', $requested)));
					if (empty($ids) && !empty($requested)) {
						$ids = array(0);
					}
				}
				return array(
					'ids'          => $ids,
					'search'       => isset($_GET['wbtm_bl_s']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_s'])) : '',
					'status'       => isset($_GET['wbtm_bl_status']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_status'])) : '',
					'bus_id'       => isset($_GET['wbtm_bl_bus']) ? absint($_GET['wbtm_bl_bus']) : 0,
					'payment'      => isset($_GET['wbtm_bl_payment']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_payment'])) : '',
					'ticket'       => isset($_GET['wbtm_bl_ticket']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_ticket'])) : '',
					'phone'        => isset($_GET['wbtm_bl_phone']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_phone'])) : '',
					'passenger'    => isset($_GET['wbtm_bl_passenger']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_passenger'])) : '',
					'journey_from' => isset($_GET['wbtm_bl_journey_from']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_journey_from'])) : '',
					'journey_to'   => isset($_GET['wbtm_bl_journey_to']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_journey_to'])) : '',
					'booked_from'  => isset($_GET['wbtm_bl_booked_from']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_booked_from'])) : '',
					'booked_to'    => isset($_GET['wbtm_bl_booked_to']) ? sanitize_text_field(wp_unslash($_GET['wbtm_bl_booked_to'])) : '',
					'sort'         => $sort,
					'per_page'     => $per_page,
				);
			}

			/**
			 * The currently-active filters as raw wbtm_bl_* query args, so the CSV/PDF
			 * export links carry them to admin-post.php (a fresh request that wouldn't
			 * otherwise see the on-screen filters). Only non-empty filters are included
			 * — so with nothing filtered the exports naturally cover every booking
			 * (whole-booking export), and with a bus/date/search set they export just
			 * that subset (e.g. bus-wise). 'sort' is carried too so ordering matches;
			 * per_page is intentionally omitted (exports are never paginated).
			 */
			private function current_filter_query_args() {
				$f = $this->get_filter_values();
				$map = array(
					'wbtm_bl_s'            => $f['search'],
					'wbtm_bl_status'       => $f['status'],
					'wbtm_bl_bus'          => $f['bus_id'],
					'wbtm_bl_payment'      => $f['payment'],
					'wbtm_bl_ticket'       => $f['ticket'],
					'wbtm_bl_phone'        => $f['phone'],
					'wbtm_bl_passenger'    => $f['passenger'],
					'wbtm_bl_journey_from' => $f['journey_from'],
					'wbtm_bl_journey_to'   => $f['journey_to'],
					'wbtm_bl_booked_from'  => $f['booked_from'],
					'wbtm_bl_booked_to'    => $f['booked_to'],
					'wbtm_bl_sort'         => $f['sort'],
					'wbtm_bl_ids'          => $f['ids'] ? implode(',', $f['ids']) : '',
				);
				$args = array();
				foreach ($map as $key => $value) {
					if ($value !== '' && $value !== 0 && $value !== null) {
						$args[$key] = $value;
					}
				}
				return $args;
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

			/**
			 * Deck-aware display label for a booking's stored seat. Shows the cabin
			 * name and, for a double-decker cabin's upper deck, the "Upper Deck"
			 * marker (e.g. "Coach A - Upper Deck - U3"). Non-cabin seats unchanged.
			 */
			private function seat_label($id) {
				if (!class_exists('WBTM_Functions')) {
					return get_post_meta($id, 'wbtm_seat', true);
				}
				return WBTM_Functions::format_cabin_seat_label(
					get_post_meta($id, 'wbtm_seat', true),
					get_post_meta($id, 'wbtm_cabin_info', true)
				);
			}
			private function source_badge($source) {
				if ($source === 'standalone') {
					return '<span class="wbtm-bkl-source wbtm-bkl-source-custom" title="' . esc_attr__('Booked via Custom Payment (no WooCommerce order)', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-money-alt"></span>' . esc_html__('Custom', 'bus-ticket-booking-with-seat-reservation') . '</span>';
				}
				return '<span class="wbtm-bkl-source wbtm-bkl-source-woo" title="' . esc_attr__('Booked via the WooCommerce cart/checkout', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-cart"></span>' . esc_html__('WooCommerce', 'bus-ticket-booking-with-seat-reservation') . '</span>';
			}

			/**
			 * "Booked by <operator>" badge for counter / admin-created bookings.
			 *
			 * The Pro "Purchase Ticket" screen (WBTM_Backend_Order) stamps every
			 * booking record it creates with wbtm_booked_by = the logged-in operator's
			 * user id. Customer-placed orders never carry that meta, so they stay
			 * unbadged — this is the only reliable way to tell a staff booking apart
			 * from a customer's own WooCommerce checkout (both are real WC orders).
			 */
			private function booked_by_badge($id) {
				$operator_id = (int) get_post_meta($id, 'wbtm_booked_by', true);
				if ($operator_id <= 0) {
					return '';
				}
				$user = get_userdata($operator_id);
				$name = $user ? ($user->display_name ?: $user->user_login) : '';
				/* translators: %s: staff/operator display name who created the booking. */
				$label = $name
					? sprintf(esc_html__('Booked by %s', 'bus-ticket-booking-with-seat-reservation'), $name)
					: esc_html__('Booked by admin', 'bus-ticket-booking-with-seat-reservation');
				return '<span class="wbtm-bkl-source wbtm-bkl-source-admin" title="' . esc_attr($label) . '"><span class="dashicons dashicons-admin-users"></span>' . esc_html($label) . '</span>';
			}

			/**
			 * Outbound / Return tag for a booking row.
			 *
			 * Returns a "Return" tag for a return leg, and an "Outbound" tag for the
			 * departure leg only when the same order group also has a return leg (i.e.
			 * it's a genuine round trip) — so plain one-way bookings stay untagged.
			 */
			private function leg_badge($id, $order_id) {
				$journey  = get_post_meta($id, 'wbtm_journey_type', true) ?: 'departure';
				$group_id = $order_id ? $order_id : $id;

				if ($journey === 'return') {
					return '<span class="wbtm-bkl-leg wbtm-bkl-leg-return" title="' . esc_attr__('Return journey leg', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-undo"></span>' . esc_html__('Return', 'bus-ticket-booking-with-seat-reservation') . '</span>';
				}

				// Departure leg: only label it "Outbound" when a matching return leg exists.
				$return_leg = new WP_Query(array(
					'post_type'      => 'wbtm_bus_booking',
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'meta_query'     => array(
						'relation' => 'AND',
						array('key' => 'wbtm_order_id', 'value' => $group_id, 'compare' => '='),
						array('key' => 'wbtm_journey_type', 'value' => 'return', 'compare' => '='),
					),
				));
				if (!empty($return_leg->posts)) {
					return '<span class="wbtm-bkl-leg wbtm-bkl-leg-outbound" title="' . esc_attr__('Outbound journey leg', 'bus-ticket-booking-with-seat-reservation') . '"><span class="dashicons dashicons-arrow-right-alt"></span>' . esc_html__('Outbound', 'bus-ticket-booking-with-seat-reservation') . '</span>';
				}

				return '';
			}

			public function render_page() {
				// Admins always (manage_options); Bus Staff via wbtm_staff_access when the
				// Pro add-on is active (the cap simply doesn't exist on free-only installs,
				// where current_user_can() returns false for it — so admins still get in).
				if (!current_user_can('manage_options') && !current_user_can('wbtm_staff_access')) {
					return;
				}
				if (isset($_GET['action'], $_GET['booking']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'view') {
					$this->render_detail(absint($_GET['booking']));
					return;
				}
				// Bus Staff (wbtm_staff_access without manage_options) get a view + QR
				// check-in experience; every destructive/admin-only control is gated on
				// $is_admin, which is always true for administrators — so the admin UI is
				// unchanged and only non-admin staff see the reduced set.
				$is_admin  = current_user_can('manage_options');
				$is_pro    = $this->is_pro();
				$query     = $this->query_bookings();
				$vis       = $this->get_column_visibility();
				$page_base = add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php'));
				?>
				<div class="wbtm-bkl-wrap">

					<div class="wbtm-bkl-header">
						<div>
							<h1 class="wbtm-bkl-title"><span class="dashicons dashicons-clipboard"></span><?php esc_html_e('Booking List', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
							<p class="wbtm-bkl-subtitle"><?php esc_html_e('Every booked seat across all buses, in one place.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						</div>
						<div class="wbtm-bkl-header-actions">
							<?php if ($is_pro && $is_admin) : ?>
								<button type="button" id="wbtm-bkl-export-open" class="wbtm-bkl-pro-cta">
									<span class="dashicons dashicons-download"></span><?php esc_html_e('Export', 'bus-ticket-booking-with-seat-reservation'); ?>
								</button>
							<?php elseif (!$is_pro && $is_admin) : ?>
								<a href="https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/" target="_blank" rel="noopener noreferrer" class="wbtm-bkl-pro-cta"><span class="dashicons dashicons-star-filled"></span><?php esc_html_e('Upgrade to PRO', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<?php endif; ?>
						</div>
					</div>

					<?php
						// Free installs blur the stats and the filter bar together behind ONE
						// PRO badge. They used to carry a lock overlay each, which stacked two
						// identical badges down the screen; the sections themselves still
						// render (blurred, inert) so the shape of the feature is visible.
						if (!$is_pro) : ?>
						<div class="wbtm-bkl-locked wbtm-bkl-locked-group is-locked">
							<?php $this->render_stats($is_pro, false); ?>
							<?php $this->render_filters($is_pro, false); ?>
							<div class="wbtm-bkl-lock-overlay">
								<?php echo wp_kses_post($this->pro_badge_html()); ?>
								<p><?php esc_html_e('Booking analytics, search, filtering and the CSV / PDF / thermal exports are all PRO features.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
							</div>
						</div>
					<?php else : ?>
						<?php $this->render_stats($is_pro); ?>
						<?php $this->render_filters($is_pro); ?>
					<?php endif; ?>

					<div class="wbtm-bkl-table-wrap">
						<div class="wbtm-bkl-table-toolbar">
							<div class="wbtm-bkl-bulk-bar">
								<?php if ($is_admin) : ?>
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
								<?php endif; ?>
								<?php if ($is_pro && $this->qr_active()) : ?>
									<span class="wbtm-bkl-qr-bulk">
										<button type="button" id="wbtm-bkl-bulk-checkin" class="button wbtm-bkl-btn-checkin"><span class="dashicons dashicons-yes"></span><?php esc_html_e('Check In', 'bus-ticket-booking-with-seat-reservation'); ?></button>
										<button type="button" id="wbtm-bkl-bulk-revoke" class="button wbtm-bkl-btn-revoke"><span class="dashicons dashicons-undo"></span><?php esc_html_e('Revoke', 'bus-ticket-booking-with-seat-reservation'); ?></button>
									</span>
								<?php endif; ?>
								<span class="wbtm-bkl-count">
									<?php
										/* translators: %s: number of bookings. */
										echo esc_html(sprintf(_n('%s booking', '%s bookings', $query->found_posts, 'bus-ticket-booking-with-seat-reservation'), number_format_i18n($query->found_posts)));
									?>
								</span>
							</div>
							<?php if ($is_pro && $is_admin) : ?>
								<div class="wbtm-bkl-toolbar-right">
									<button type="button" id="wbtm-bkl-columns-toggle" class="button wbtm-bkl-columns-toggle" aria-expanded="false" title="<?php esc_attr_e('Show / hide columns', 'bus-ticket-booking-with-seat-reservation'); ?>">
										<span class="dashicons dashicons-columns"></span><?php esc_html_e('Columns', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
									<?php $this->render_column_settings($vis); ?>
								</div>
							<?php endif; ?>
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
									<th data-col="booking"<?php echo $this->col_style($vis, 'booking'); ?>><?php esc_html_e('Booking', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="customer"<?php echo $this->col_style($vis, 'customer'); ?>><?php esc_html_e('Customer', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="bus_route"<?php echo $this->col_style($vis, 'bus_route'); ?>><?php esc_html_e('Bus & Route', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="journey_date"<?php echo $this->col_style($vis, 'journey_date'); ?>><?php esc_html_e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="seat_ticket"<?php echo $this->col_style($vis, 'seat_ticket'); ?>><?php esc_html_e('Seat / Ticket', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="total"<?php echo $this->col_style($vis, 'total'); ?>><?php esc_html_e('Total', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="status"<?php echo $this->col_style($vis, 'status'); ?>><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<th data-col="booked_on"<?php echo $this->col_style($vis, 'booked_on'); ?>><?php esc_html_e('Booked On', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<?php if ($this->qr_active()) : ?>
										<th data-col="check_in"<?php echo $this->col_style($vis, 'check_in'); ?>><?php esc_html_e('Check In', 'bus-ticket-booking-with-seat-reservation'); ?></th>
									<?php endif; ?>
									<th class="wbtm-bkl-col-actions"><?php esc_html_e('Actions', 'bus-ticket-booking-with-seat-reservation'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ($query->have_posts()) : ?>
									<?php while ($query->have_posts()) : $query->the_post(); ?>
										<?php $this->render_row(get_the_ID(), $is_pro, $vis, $is_admin); ?>
									<?php endwhile; wp_reset_postdata(); ?>
								<?php else : ?>
									<tr><td colspan="<?php echo (int) ($this->qr_active() ? 11 : 10); ?>" class="wbtm-bkl-empty"><span class="dashicons dashicons-clipboard"></span><p><?php esc_html_e('No bookings found.', 'bus-ticket-booking-with-seat-reservation'); ?></p></td></tr>
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
					<?php if ($is_pro && $is_admin) { $this->render_export_modal(); } ?>
				</div>
				<?php
			}

			/**
			 * The single "Export" dialog behind the header button: pick a format, pick
			 * what to export, optionally narrow it further. The JS turns those choices
			 * into the same wbtm_bl_* query args the on-screen filter bar uses, so every
			 * scope runs through build_query_args() — one query builder for the screen
			 * and all three exports, rather than per-scope query code.
			 */
			private function render_export_modal() {
				$pdf_available = class_exists('WBTM_Pro_Pdf') && WBTM_Pro_Pdf::is_mpdf_available();
				$thermal_available = $pdf_available
					&& class_exists('WBTM_Global_Function')
					&& WBTM_Global_Function::get_settings('wbtm_pdf_settings', 'thermal_ticket_enable', 'yes') === 'yes';
				$buses    = get_posts(array('post_type' => 'wbtm_bus', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
				$statuses = $this->get_status_options();
				$today    = current_time('Y-m-d');
				$formats  = array(
					'csv' => array(
						'icon'      => 'dashicons-media-spreadsheet',
						'label'     => __('CSV', 'bus-ticket-booking-with-seat-reservation'),
						'desc'      => __('Spreadsheet — every column, including passenger form fields.', 'bus-ticket-booking-with-seat-reservation'),
						'available' => true,
						'why'       => '',
					),
					'pdf' => array(
						'icon'      => 'dashicons-media-document',
						'label'     => __('PDF', 'bus-ticket-booking-with-seat-reservation'),
						'desc'      => __('Printable landscape manifest with totals.', 'bus-ticket-booking-with-seat-reservation'),
						'available' => $pdf_available,
						'why'       => __('Needs the MagePeople PDF Support plugin.', 'bus-ticket-booking-with-seat-reservation'),
					),
					'thermal' => array(
						'icon'      => 'dashicons-printer',
						'label'     => __('Thermal tickets', 'bus-ticket-booking-with-seat-reservation'),
						'desc'      => __('One POS receipt ticket per booking, on a 58/80mm roll.', 'bus-ticket-booking-with-seat-reservation'),
						'available' => $thermal_available,
						'why'       => $pdf_available
							? __('Turn on Thermal (POS) Ticket in PDF settings.', 'bus-ticket-booking-with-seat-reservation')
							: __('Needs the MagePeople PDF Support plugin.', 'bus-ticket-booking-with-seat-reservation'),
					),
				);
				// Scope rows. 'view' mirrors today's behaviour (export exactly what the
				// filter bar selected) and stays the default so the button keeps doing
				// the least surprising thing.
				$scopes = array(
					'view' => array(
						'icon'  => 'dashicons-visibility',
						'label' => __('Current view', 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __('Everything the filter bar is showing right now.', 'bus-ticket-booking-with-seat-reservation'),
					),
					'selected' => array(
						'icon'  => 'dashicons-yes',
						'label' => __('Selected bookings', 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __('Only the rows you ticked in the list.', 'bus-ticket-booking-with-seat-reservation'),
					),
					'today' => array(
						'icon'  => 'dashicons-calendar-alt',
						'label' => __("Today's bookings", 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __('Booked today — the day-end sales sheet.', 'bus-ticket-booking-with-seat-reservation'),
					),
					'departing_today' => array(
						// dashicons has no bus glyph — dashicons-car is the closest that exists.
						'icon'  => 'dashicons-car',
						'label' => __('Departing today', 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __("Travelling today — the driver's manifest.", 'bus-ticket-booking-with-seat-reservation'),
					),
					'range' => array(
						'icon'  => 'dashicons-calendar',
						'label' => __('Date range', 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __('Pick the dates and whether they mean travel or booking.', 'bus-ticket-booking-with-seat-reservation'),
					),
					'all' => array(
						'icon'  => 'dashicons-database',
						'label' => __('All bookings', 'bus-ticket-booking-with-seat-reservation'),
						'desc'  => __('The whole book, ignoring the filter bar.', 'bus-ticket-booking-with-seat-reservation'),
					),
				);
				?>
				<div id="wbtm-bkl-export-modal" class="wbtm-bkl-modal wbtm-bkl-modal-export" style="display:none;" data-today="<?php echo esc_attr($today); ?>">
					<div class="wbtm-bkl-modal-card">
						<div class="wbtm-bkl-modal-head">
							<h2><span class="dashicons dashicons-download"></span><?php esc_html_e('Export Bookings', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
							<span class="wbtm-bkl-modal-close dashicons dashicons-no-alt" role="button" aria-label="<?php esc_attr_e('Close', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
						</div>
						<div class="wbtm-bkl-modal-body">

							<div class="wbtm-bkl-export-section">
								<h3 class="wbtm-bkl-export-legend"><?php esc_html_e('Format', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
								<div class="wbtm-bkl-export-formats">
									<?php $first = true; foreach ($formats as $key => $fmt) : ?>
										<label class="wbtm-bkl-export-format<?php echo $fmt['available'] ? '' : ' is-disabled'; ?>"
											title="<?php echo esc_attr($fmt['available'] ? $fmt['desc'] : $fmt['why']); ?>">
											<input type="radio" name="wbtm_bkl_export_format" value="<?php echo esc_attr($key); ?>"
												<?php checked($first && $fmt['available']); ?> <?php disabled(!$fmt['available']); ?>>
											<span class="dashicons <?php echo esc_attr($fmt['icon']); ?>"></span>
											<span class="wbtm-bkl-export-format-text">
												<strong><?php echo esc_html($fmt['label']); ?></strong>
												<small><?php echo esc_html($fmt['available'] ? $fmt['desc'] : $fmt['why']); ?></small>
											</span>
										</label>
									<?php $first = false; endforeach; ?>
								</div>
							</div>

							<div class="wbtm-bkl-export-section">
								<h3 class="wbtm-bkl-export-legend"><?php esc_html_e('What to export', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
								<div class="wbtm-bkl-export-scopes">
									<?php foreach ($scopes as $key => $scope) : ?>
										<label class="wbtm-bkl-export-scope" data-scope="<?php echo esc_attr($key); ?>">
											<input type="radio" name="wbtm_bkl_export_scope" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'view'); ?>>
											<span class="dashicons <?php echo esc_attr($scope['icon']); ?>"></span>
											<span class="wbtm-bkl-export-scope-text">
												<strong>
													<?php echo esc_html($scope['label']); ?>
													<?php if ($key === 'selected') : ?>
														<span class="wbtm-bkl-export-count" id="wbtm-bkl-export-selected-count">(0)</span>
													<?php endif; ?>
												</strong>
												<small><?php echo esc_html($scope['desc']); ?></small>
											</span>
										</label>
									<?php endforeach; ?>
								</div>

								<div class="wbtm-bkl-export-range" id="wbtm-bkl-export-range" hidden>
									<div class="wbtm-bkl-export-field">
										<label for="wbtm-bkl-export-basis"><?php esc_html_e('Dates refer to', 'bus-ticket-booking-with-seat-reservation'); ?></label>
										<select id="wbtm-bkl-export-basis">
											<option value="journey"><?php esc_html_e('Journey date (travel)', 'bus-ticket-booking-with-seat-reservation'); ?></option>
											<option value="booked"><?php esc_html_e('Booked date (sale)', 'bus-ticket-booking-with-seat-reservation'); ?></option>
										</select>
									</div>
									<div class="wbtm-bkl-export-field">
										<label for="wbtm-bkl-export-from"><?php esc_html_e('From', 'bus-ticket-booking-with-seat-reservation'); ?></label>
										<input type="date" id="wbtm-bkl-export-from" value="<?php echo esc_attr($today); ?>">
									</div>
									<div class="wbtm-bkl-export-field">
										<label for="wbtm-bkl-export-to"><?php esc_html_e('To', 'bus-ticket-booking-with-seat-reservation'); ?></label>
										<input type="date" id="wbtm-bkl-export-to" value="<?php echo esc_attr($today); ?>">
									</div>
								</div>
							</div>

							<div class="wbtm-bkl-export-section" id="wbtm-bkl-export-refine">
								<h3 class="wbtm-bkl-export-legend"><?php esc_html_e('Narrow it down', 'bus-ticket-booking-with-seat-reservation'); ?> <small><?php esc_html_e('optional', 'bus-ticket-booking-with-seat-reservation'); ?></small></h3>
								<div class="wbtm-bkl-export-refine-row">
									<div class="wbtm-bkl-export-field">
										<label for="wbtm-bkl-export-bus"><?php esc_html_e('Bus', 'bus-ticket-booking-with-seat-reservation'); ?></label>
										<select id="wbtm-bkl-export-bus">
											<option value=""><?php esc_html_e('All buses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
											<?php foreach ($buses as $bus) : ?>
												<option value="<?php echo esc_attr($bus->ID); ?>"><?php echo esc_html(get_the_title($bus)); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="wbtm-bkl-export-field">
										<label for="wbtm-bkl-export-status"><?php esc_html_e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></label>
										<select id="wbtm-bkl-export-status">
											<option value=""><?php esc_html_e('All statuses', 'bus-ticket-booking-with-seat-reservation'); ?></option>
											<?php foreach ($statuses as $key => $label) : ?>
												<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<p class="wbtm-bkl-export-note" id="wbtm-bkl-export-refine-note" hidden>
									<span class="dashicons dashicons-info-outline"></span>
									<?php esc_html_e('The filter bar and your row selection already decide the rows, so these two are ignored for this scope.', 'bus-ticket-booking-with-seat-reservation'); ?>
								</p>
							</div>

							<div class="wbtm-bkl-modal-actions">
								<button type="button" class="wbtm-bkl-btn wbtm-bkl-btn-outline wbtm-bkl-modal-close"><?php esc_html_e('Cancel', 'bus-ticket-booking-with-seat-reservation'); ?></button>
								<button type="button" id="wbtm-bkl-export-run" class="wbtm-bkl-btn wbtm-bkl-btn-primary"><span class="dashicons dashicons-download"></span><?php esc_html_e('Export', 'bus-ticket-booking-with-seat-reservation'); ?></button>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			/**
			 * @param bool $is_pro   Whether Pro is active (unlocks the real figures).
			 * @param bool $own_lock Render this section's own PRO overlay. False when the
			 *                       caller has already wrapped several sections in one
			 *                       shared lock, so only a single badge is shown.
			 */
			private function render_stats($is_pro, $own_lock = true) {
				$stats = $this->get_stats();
				$cards = array(
					array('dashicons-cart', $is_pro ? number_format_i18n($stats['total_bookings']) : '&bull;&bull;&bull;', esc_html__('Total Bookings', 'bus-ticket-booking-with-seat-reservation')),
					array('dashicons-money-alt', $is_pro ? WBTM_Global_Function::format_price($stats['total_revenue']) : '&bull;&bull;&bull;', esc_html__('Total Revenue', 'bus-ticket-booking-with-seat-reservation')),
					array('dashicons-admin-multisite', $is_pro ? number_format_i18n($stats['total_buses']) : '&bull;&bull;&bull;', esc_html__('Buses Booked', 'bus-ticket-booking-with-seat-reservation')),
				);
				// Tax is WooCommerce-only and prices here are tax-inclusive, so this is
				// the slice of Total Revenue that is tax — not an extra amount on top.
				// Hidden entirely when no tax was ever collected (e.g. standalone mode).
				if ($stats['total_tax'] > 0) {
					$cards[] = array('dashicons-analytics', $is_pro ? WBTM_Global_Function::format_price($stats['total_tax']) : '&bull;&bull;&bull;', esc_html__('Tax (incl. in revenue)', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Only meaningful once the Pro deposit add-on has taken a part-payment.
				if ($stats['total_outstanding'] > 0) {
					$cards[] = array('dashicons-clock', $is_pro ? WBTM_Global_Function::format_price($stats['total_outstanding']) : '&bull;&bull;&bull;', esc_html__('Outstanding Balance', 'bus-ticket-booking-with-seat-reservation'));
				}
				?>
				<div class="wbtm-bkl-locked<?php echo ($is_pro || !$own_lock) ? '' : ' is-locked'; ?>">
					<?php
						// The cards describe the filtered set, so say so when a filter is
						// active — otherwise a bus-wise total reads like a site-wide one.
						// Sort is excluded: it reorders the set, it doesn't narrow it.
						$scope_args = $this->current_filter_query_args();
						unset($scope_args['wbtm_bl_sort']);
						$active_filters = $is_pro ? count($scope_args) : 0;
					?>
					<?php if ($active_filters > 0) : ?>
						<p class="wbtm-bkl-stats-scope">
							<span class="dashicons dashicons-filter"></span>
							<?php esc_html_e('Showing totals for the current filter only.', 'bus-ticket-booking-with-seat-reservation'); ?>
							<a href="<?php echo esc_url(add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php'))); ?>"><?php esc_html_e('Clear filters', 'bus-ticket-booking-with-seat-reservation'); ?></a>
						</p>
					<?php endif; ?>
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
					<?php if (!$is_pro && $own_lock) : ?>
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

			/**
			 * @param bool $is_pro   Whether Pro is active (unlocks search/filtering).
			 * @param bool $own_lock Render this section's own PRO overlay — see render_stats().
			 */
			private function render_filters($is_pro, $own_lock = true) {
				$f = $this->get_filter_values();
				$statuses = $this->get_status_options();
				$buses    = $is_pro ? get_posts(array('post_type' => 'wbtm_bus', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC')) : array();
				$payments = $is_pro ? $this->get_distinct_meta_values('wbtm_billing_type') : array();
				$tickets  = $is_pro ? $this->get_distinct_meta_values('wbtm_ticket') : array();
				// The panel starts collapsed and only auto-opens when a filter is actually
				// narrowing the list, so the reason for a short list is never hidden.
				// Excluded from that test: sort/per_page always have defaults, and 'ids' is
				// export-only (the filter bar never sets it) — and being an array it would
				// have passed the scalar test below even when empty, wedging the panel open.
				$active_probe = $f;
				unset($active_probe['sort'], $active_probe['per_page'], $active_probe['ids']);
				$has_active_filter = $is_pro && count(array_filter($active_probe, function ($v) { return $v !== '' && $v !== 0; })) > 0;
				$sort_options = array(
					'newest'       => __('Newest first', 'bus-ticket-booking-with-seat-reservation'),
					'oldest'       => __('Oldest first', 'bus-ticket-booking-with-seat-reservation'),
					'journey_asc'  => __('Journey date ↑', 'bus-ticket-booking-with-seat-reservation'),
					'journey_desc' => __('Journey date ↓', 'bus-ticket-booking-with-seat-reservation'),
					'seat'         => __('Seat', 'bus-ticket-booking-with-seat-reservation'),
				);
				?>
				<div class="wbtm-bkl-filters-panel">
					<button type="button" class="wbtm-bkl-filters-toggle" aria-expanded="<?php echo $has_active_filter ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e('Filters', 'bus-ticket-booking-with-seat-reservation'); ?>
						<?php if ($has_active_filter) : ?><span class="wbtm-bkl-filters-active-dot"></span><?php endif; ?>
						<span class="dashicons dashicons-arrow-down-alt2 wbtm-bkl-filters-arrow"></span>
					</button>
					<div class="wbtm-bkl-filters-body<?php echo $has_active_filter ? ' is-open' : ''; ?>">
						<div class="wbtm-bkl-locked wbtm-bkl-locked-filters<?php echo ($is_pro || !$own_lock) ? '' : ' is-locked'; ?>">
							<form method="get" class="wbtm-bkl-filters" <?php echo $is_pro ? '' : 'aria-hidden="true"'; ?>>
								<input type="hidden" name="post_type" value="wbtm_bus">
								<input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

								<div class="wbtm-bkl-filter-field wbtm-bkl-filter-wide">
									<span class="dashicons dashicons-search"></span>
									<input type="text" name="wbtm_bl_s" value="<?php echo esc_attr($f['search']); ?>" placeholder="<?php esc_attr_e('Search customer, email or order #', 'bus-ticket-booking-with-seat-reservation'); ?>" <?php disabled(!$is_pro); ?>>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Passenger', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<input type="text" name="wbtm_bl_passenger" value="<?php echo esc_attr($f['passenger']); ?>" placeholder="<?php esc_attr_e('Passenger name / email / field', 'bus-ticket-booking-with-seat-reservation'); ?>" <?php disabled(!$is_pro); ?>>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Phone', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<input type="text" name="wbtm_bl_phone" value="<?php echo esc_attr($f['phone']); ?>" placeholder="<?php esc_attr_e('Billing or passenger phone', 'bus-ticket-booking-with-seat-reservation'); ?>" <?php disabled(!$is_pro); ?>>
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

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Sort By', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_sort" <?php disabled(!$is_pro); ?>>
										<?php foreach ($sort_options as $key => $label) : ?>
											<option value="<?php echo esc_attr($key); ?>" <?php selected($f['sort'], $key); ?>><?php echo esc_html($label); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="wbtm-bkl-filter-group">
									<label><?php esc_html_e('Per Page', 'bus-ticket-booking-with-seat-reservation'); ?></label>
									<select name="wbtm_bl_per_page" <?php disabled(!$is_pro); ?>>
										<?php foreach (array(20, 50, 100, 200) as $pp) : ?>
											<option value="<?php echo esc_attr($pp); ?>" <?php selected($f['per_page'], $pp); ?>><?php echo esc_html($pp); ?></option>
										<?php endforeach; ?>
									</select>
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
							<?php if (!$is_pro && $own_lock) : ?>
								<div class="wbtm-bkl-lock-overlay">
									<?php echo wp_kses_post($this->pro_badge_html()); ?>
									<p><?php esc_html_e('Search, filtering & the exports are available in PRO.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php
			}

			/**
			 * Pull the primary passenger's name/phone/email/address out of the
			 * per-seat wbtm_attendee_info meta (whatever custom field keys exist),
			 * so the Booking List can surface passenger-level data — not just billing.
			 */
			private function passenger_bits($id) {
				$out  = array('name' => '', 'phone' => '', 'email' => '', 'address' => '');
				$info = get_post_meta($id, 'wbtm_attendee_info', true);
				if (!is_array($info)) {
					return $out;
				}
				foreach ($info as $key => $field) {
					$val = is_array($field) ? ($field['value'] ?? '') : $field;
					$val = is_array($val) ? implode(', ', $val) : (string) $val;
					$val = trim($val);
					if ($val === '') {
						continue;
					}
					$lk = strtolower((string) $key);
					if ($out['name'] === '' && strpos($lk, 'name') !== false) {
						$out['name'] = $val;
					} elseif ($out['phone'] === '' && (strpos($lk, 'phone') !== false || strpos($lk, 'mobile') !== false)) {
						$out['phone'] = $val;
					} elseif ($out['email'] === '' && strpos($lk, 'email') !== false) {
						$out['email'] = $val;
					} elseif ($out['address'] === '' && strpos($lk, 'address') !== false) {
						$out['address'] = $val;
					}
				}
				return $out;
			}

			/**
			 * Sum of a booking's extra services (wbtm_extra_services is a per-seat
			 * array of {name, price, qty}). These are billed on top of the seat fare
			 * but were previously ignored by the list Total, stats, and CSV/PDF
			 * exports — only the detail view counted them.
			 */
			private function extra_services_total($id) {
				$svcs = get_post_meta($id, 'wbtm_extra_services', true);
				if (!is_array($svcs)) {
					return 0.0;
				}
				$sum = 0.0;
				foreach ($svcs as $svc) {
					$sum += (float) ($svc['price'] ?? 0) * (int) ($svc['qty'] ?? 1);
				}
				return $sum;
			}

			/**
			 * The true per-seat total shown in the list / exports: seat fare plus any
			 * extra services booked against that seat. (Coupon/booking-fee shares are
			 * a separate Pro concern and intentionally not folded in here.)
			 */
			private function booking_total($id) {
				return (float) get_post_meta($id, 'wbtm_bus_fare', true) + $this->extra_services_total($id);
			}

			/**
			 * Per-seat tax for a booking, read straight from WooCommerce.
			 *
			 * Tax is a purely WooCommerce concept here: it is computed by WC_Tax on the
			 * hidden mirror product at checkout and stored on the WC order line item —
			 * never on the booking post. Each booking post maps to one seat inside a
			 * line item (wbtm_item_id); the line item carries WooCommerce's own tax for
			 * all its seats, so we divide by the line quantity for this seat's exact
			 * share. Because this store runs tax-INCLUSIVE prices, this tax is the
			 * portion already contained within booking_total() (fare + extras), NOT an
			 * amount to add on top — callers display it as "of which X is tax".
			 *
			 * Returns 0.0 when WooCommerce is absent (standalone mode has no tax at
			 * all), the booking has no WC order/line item (admin-created "Custom"
			 * bookings), the order was deleted, or the line simply carries no tax.
			 */
			private function booking_tax($id) {
				return round($this->booking_tax_raw($id), 2);
			}

			/**
			 * booking_tax() without the rounding — see that method for the full story.
			 * Totalling many seats must sum the raw shares and round once at the end,
			 * otherwise a multi-seat line item drifts (e.g. 3 seats sharing $10.00 tax
			 * would round to 3 x $3.33 = $9.99). Display always uses booking_tax().
			 */
			private function booking_tax_raw($id) {
				if (!function_exists('wc_get_order')) {
					return 0.0; // standalone (no-WooCommerce) mode: no tax exists
				}
				$order_id = (int) get_post_meta($id, 'wbtm_order_id', true);
				$item_id  = (int) get_post_meta($id, 'wbtm_item_id', true);
				if ($order_id <= 0 || $item_id <= 0) {
					return 0.0;
				}
				// Cache per line item for the request — a page of rows shares few orders.
				static $cache = array();
				if (!array_key_exists($item_id, $cache)) {
					$cache[$item_id] = 0.0;
					$order = wc_get_order($order_id);
					if ($order) {
						$item = $order->get_item($item_id);
						if ($item) {
							$qty = max(1, (int) $item->get_quantity());
							$cache[$item_id] = (float) $item->get_total_tax() / $qty;
						}
					}
				}
				return $cache[$item_id];
			}

			/**
			 * Deposit / partial-payment state for a booking, sourced from the meta the
			 * Pro deposit add-on writes onto each booking post
			 * (wbtm_payment_plan / wbtm_deposit_paid / wbtm_remaining_due /
			 * wbtm_balance_due_date). Returns:
			 *   is_deposit : bool  — this booking was placed on a deposit plan
			 *   paid       : float — amount actually collected so far
			 *   remaining  : float — balance still due (0 once fully paid)
			 *   due_date   : string— balance due date (Y-m-d) or ''
			 * When the add-on isn't active or no deposit was taken, is_deposit is false
			 * and the UI simply omits the deposit lines.
			 */
			private function deposit_info($id) {
				$plan = (string) get_post_meta($id, 'wbtm_payment_plan', true);
				$paid = get_post_meta($id, 'wbtm_deposit_paid', true);
				$is_deposit = ($plan === 'deposit' || $plan === 'fully_paid' || ($paid !== '' && $paid !== false));
				if (!$is_deposit) {
					return array('is_deposit' => false, 'paid' => 0.0, 'remaining' => 0.0, 'due_date' => '');
				}
				return array(
					'is_deposit' => true,
					'paid'       => (float) $paid,
					'remaining'  => max(0.0, (float) get_post_meta($id, 'wbtm_remaining_due', true)),
					'due_date'   => (string) get_post_meta($id, 'wbtm_balance_due_date', true),
				);
			}

			/**
			 * Human-readable "Name x qty, …" summary of a booking's extra services,
			 * for the CSV export's detail column. Empty string when there are none.
			 */
			private function extra_services_label($id) {
				$svcs = get_post_meta($id, 'wbtm_extra_services', true);
				if (!is_array($svcs)) {
					return '';
				}
				$parts = array();
				foreach ($svcs as $svc) {
					$name = isset($svc['name']) ? (string) $svc['name'] : '';
					if ($name === '') {
						continue;
					}
					$qty = (int) ($svc['qty'] ?? 1);
					$parts[] = $name . ' x ' . max(1, $qty);
				}
				return implode(', ', $parts);
			}

			/**
			 * Ticket / order / thermal PDF download URLs for a booking, mirroring the
			 * three downloads the Pro Passenger List offered per row. All are produced
			 * by the Pro PDF generator (WBTM_Pro_Pdf), so they're only available when
			 * the Pro add-on is active AND mPDF is present; the thermal (POS) ticket
			 * additionally honours the plugin's "thermal_ticket_enable" setting.
			 * Returns an empty array when PDF generation isn't available at all.
			 */
			private function pdf_links($id, $order_id) {
				if (!class_exists('WBTM_Pro_Pdf') || !WBTM_Pro_Pdf::is_mpdf_available()) {
					return array();
				}
				$thermal_enabled = class_exists('WBTM_Global_Function')
					&& WBTM_Global_Function::get_settings('wbtm_pdf_settings', 'thermal_ticket_enable', 'yes') === 'yes';
				return array(
					'ticket'  => WBTM_Pro_Pdf::get_pdf_url(array('attendee_id' => $id)),
					'thermal' => $thermal_enabled ? WBTM_Pro_Pdf::get_pdf_url(array('attendee_id' => $id, 'thermal' => 1)) : '',
				);
			}

			private function render_row($id, $is_pro, $vis = null, $is_admin = null) {
				if (!is_array($vis)) {
					$vis = $this->get_column_visibility();
				}
				if ($is_admin === null) {
					$is_admin = current_user_can('manage_options');
				}
				$order_id  = get_post_meta($id, 'wbtm_order_id', true);
				$bus_id    = (int) get_post_meta($id, 'wbtm_bus_id', true);
				$user_name = get_post_meta($id, 'wbtm_user_name', true);
				$user_email = get_post_meta($id, 'wbtm_user_email', true);
				$user_phone = get_post_meta($id, 'wbtm_user_phone', true);
				$pax        = $this->passenger_bits($id);
				$row_phone  = $user_phone ?: $pax['phone'];
				$bp        = get_post_meta($id, 'wbtm_boarding_point', true);
				$dp        = get_post_meta($id, 'wbtm_dropping_point', true);
				$bp_time   = get_post_meta($id, 'wbtm_boarding_time', true);
				$booking_date = get_post_meta($id, 'wbtm_booking_date', true);
				$seat      = get_post_meta($id, 'wbtm_seat', true);
				$ticket    = get_post_meta($id, 'wbtm_ticket', true);
				$fare      = (float) get_post_meta($id, 'wbtm_bus_fare', true);
				$extras    = $this->extra_services_total($id);
				$row_total = $fare + $extras;
				// Prices are tax-inclusive, so $row_total already contains the tax —
				// $row_tax is a breakdown of that amount, never added on top of it.
				$row_tax   = $this->booking_tax($id);
				$deposit   = $this->deposit_info($id);
				$status    = get_post_meta($id, 'wbtm_order_status', true);
				$bus_title = $bus_id ? get_the_title($bus_id) : '';
				$journey_date = $bp_time ?: $booking_date;
				$wc_active = class_exists('WBTM_Functions') && WBTM_Functions::is_wc_active();
				$reference = '#' . ($order_id ? $order_id : $id);
				?>
				<tr data-row-id="<?php echo esc_attr($id); ?>">
					<td class="wbtm-bkl-col-check"><input type="checkbox" class="wbtm-bkl-row-check" value="<?php echo esc_attr($id); ?>"></td>
					<td data-col="booking" data-label="<?php echo esc_attr__('Booking', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'booking'); ?>>
						<strong><?php echo esc_html($reference); ?></strong>
						<?php echo wp_kses_post($this->source_badge($this->booking_source($id))); ?>
						<?php echo wp_kses_post($this->booked_by_badge($id)); ?>
						<span class="wbtm-bkl-sub">ID <?php echo esc_html($id); ?></span>
					</td>
					<td data-col="customer" data-label="<?php echo esc_attr__('Customer', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'customer'); ?>>
						<strong><?php echo esc_html($user_name ?: ($pax['name'] ?: '—')); ?></strong>
						<?php if ($user_email) : ?><br><small><?php echo esc_html($user_email); ?></small><?php endif; ?>
						<?php if ($row_phone) : ?><br><small><span class="dashicons dashicons-phone" style="font-size:12px;width:12px;height:12px;vertical-align:-1px;"></span> <?php echo esc_html($row_phone); ?></small><?php endif; ?>
						<?php if ($pax['name'] && $user_name && strcasecmp($pax['name'], $user_name) !== 0) : ?><br><small class="wbtm-bkl-pax-name"><?php esc_html_e('Passenger:', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo esc_html($pax['name']); ?></small><?php endif; ?>
					</td>
					<td data-col="bus_route" data-label="<?php echo esc_attr__('Bus & Route', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'bus_route'); ?>>
						<?php if ($bus_id) : ?>
							<a href="<?php echo esc_url(get_edit_post_link($bus_id)); ?>"><?php echo esc_html($bus_title); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
						<?php echo wp_kses_post($this->leg_badge($id, $order_id)); ?>
						<?php if ($bp || $dp) : ?><br><small><?php echo esc_html($bp); ?> &rarr; <?php echo esc_html($dp); ?></small><?php endif; ?>
					</td>
					<td data-col="journey_date" data-label="<?php echo esc_attr__('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'journey_date'); ?>><?php echo esc_html($journey_date ?: '—'); ?></td>
					<td data-col="seat_ticket" data-label="<?php echo esc_attr__('Seat / Ticket', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'seat_ticket'); ?>>
						<?php if ($seat) : ?><span class="wbtm-bkl-pill"><?php echo esc_html($this->seat_label($id)); ?></span><?php endif; ?>
						<?php if ($ticket) : ?><br><small><?php echo esc_html($ticket); ?></small><?php endif; ?>
					</td>
					<td data-col="total" data-label="<?php echo esc_attr__('Total', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'total'); ?>>
						<strong><?php echo wp_kses_post(WBTM_Global_Function::format_price($row_total)); ?></strong>
						<?php if ($extras > 0) : ?><br><small title="<?php esc_attr_e('Includes extra services', 'bus-ticket-booking-with-seat-reservation'); ?>"><?php
							/* translators: %s: formatted extra-services amount. */
							echo esc_html(sprintf(__('incl. %s extras', 'bus-ticket-booking-with-seat-reservation'), wp_strip_all_tags(WBTM_Global_Function::format_price($extras))));
						?></small><?php endif; ?>
						<?php if ($row_tax > 0) : ?><br><small class="wbtm-bkl-tax-note" title="<?php esc_attr_e('Tax already included in this total (WooCommerce)', 'bus-ticket-booking-with-seat-reservation'); ?>"><?php
							/* translators: %s: formatted tax amount contained in the total. */
							echo esc_html(sprintf(__('incl. %s tax', 'bus-ticket-booking-with-seat-reservation'), wp_strip_all_tags(WBTM_Global_Function::format_price($row_tax))));
						?></small><?php endif; ?>
						<?php if ($deposit['is_deposit']) : ?>
							<?php if ($deposit['remaining'] > 0) : ?>
								<br><span class="wbtm-bkl-deposit-note" title="<?php esc_attr_e('Deposit paid — balance still outstanding', 'bus-ticket-booking-with-seat-reservation'); ?>"><?php
									/* translators: 1: amount paid so far, 2: outstanding balance. */
									echo esc_html(sprintf(
										__('Paid %1$s · Due %2$s', 'bus-ticket-booking-with-seat-reservation'),
										wp_strip_all_tags(WBTM_Global_Function::format_price($deposit['paid'])),
										wp_strip_all_tags(WBTM_Global_Function::format_price($deposit['remaining']))
									));
								?></span>
							<?php else : ?>
								<br><span class="wbtm-bkl-deposit-note is-settled" title="<?php esc_attr_e('Deposit booking — balance settled in full', 'bus-ticket-booking-with-seat-reservation'); ?>"><?php esc_html_e('Balance settled', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td data-col="status" data-label="<?php echo esc_attr__('Status', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'status'); ?>><?php echo wp_kses_post($this->status_badge($status)); ?></td>
					<td data-col="booked_on" data-label="<?php echo esc_attr__('Booked On', 'bus-ticket-booking-with-seat-reservation'); ?>"<?php echo $this->col_style($vis, 'booked_on'); ?>><?php echo esc_html($booking_date ?: get_the_date('Y-m-d H:i', $id)); ?></td>
					<?php if ($this->qr_active()) : ?>
						<td data-col="check_in" data-label="<?php echo esc_attr__('Check In', 'bus-ticket-booking-with-seat-reservation'); ?>" class="wbtm-bkl-checkin-cell"<?php echo $this->col_style($vis, 'check_in'); ?>><?php do_action('wbtm_qr_ticket_status_text', $id); ?></td>
					<?php endif; ?>
					<td class="wbtm-bkl-col-actions" data-label="<?php echo esc_attr__('Actions', 'bus-ticket-booking-with-seat-reservation'); ?>">
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

								<?php if ($is_admin && $wc_active && $order_id && $this->booking_source($id) === 'woocommerce') : ?>
									<a class="wbtm-bkl-dropdown-item" href="<?php echo esc_url(admin_url('post.php?post=' . absint($order_id) . '&action=edit')); ?>">
										<span class="dashicons dashicons-cart"></span><?php esc_html_e('View WC Order', 'bus-ticket-booking-with-seat-reservation'); ?>
									</a>
								<?php endif; ?>

								<?php if ($is_pro && $is_admin) : ?>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-change-status-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>" data-status="<?php echo esc_attr($status); ?>">
										<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
								<?php elseif (!$is_pro && $is_admin) : ?>
									<span class="wbtm-bkl-dropdown-item wbtm-bkl-locked-trigger">
										<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
										<span class="wbtm-bkl-mini-pro"><?php esc_html_e('PRO', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									</span>
								<?php endif; ?>

								<?php if ($is_pro && $this->qr_active()) : ?>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-checkin-btn" data-id="<?php echo esc_attr($id); ?>" data-action="checkin">
										<span class="dashicons dashicons-yes"></span><?php esc_html_e('Check In', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-checkin-btn" data-id="<?php echo esc_attr($id); ?>" data-action="revoke">
										<span class="dashicons dashicons-undo"></span><?php esc_html_e('Revoke Check-In', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
								<?php endif; ?>

								<?php
									$pdf = $is_pro ? $this->pdf_links($id, $order_id) : array();
									if (!empty($pdf)) :
								?>
									<div class="wbtm-bkl-dropdown-divider" role="separator"></div>
									<a class="wbtm-bkl-dropdown-item" href="<?php echo esc_url($pdf['ticket']); ?>" target="_blank" rel="noopener noreferrer" download>
										<span class="dashicons dashicons-media-document"></span><?php esc_html_e('Download Ticket (PDF)', 'bus-ticket-booking-with-seat-reservation'); ?>
									</a>
									<?php if (!empty($pdf['thermal'])) : ?>
										<a class="wbtm-bkl-dropdown-item" href="<?php echo esc_url($pdf['thermal']); ?>" target="_blank" rel="noopener noreferrer" download>
											<span class="dashicons dashicons-printer"></span><?php esc_html_e('Thermal (POS) Ticket', 'bus-ticket-booking-with-seat-reservation'); ?>
										</a>
									<?php endif; ?>
								<?php elseif (!$is_pro) : ?>
									<div class="wbtm-bkl-dropdown-divider" role="separator"></div>
									<span class="wbtm-bkl-dropdown-item wbtm-bkl-locked-trigger">
										<span class="dashicons dashicons-media-document"></span><?php esc_html_e('Download Ticket (PDF)', 'bus-ticket-booking-with-seat-reservation'); ?>
										<span class="wbtm-bkl-mini-pro"><?php esc_html_e('PRO', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									</span>
								<?php endif; ?>

								<?php
									// Resend the confirmation email. WooCommerce bookings use the PRO
									// e-voucher mailer; Standalone / Custom Payment (Offline) bookings
									// use the standalone mailer's force-resend. Show for whichever path
									// this booking supports.
									$wbtm_src = $this->booking_source($id);
									$wbtm_can_resend = $is_admin && (
										($is_pro && $wc_active && $order_id && 'woocommerce' === $wbtm_src) ||
										('standalone' === $wbtm_src && class_exists('WBTM_Standalone_Mail') && method_exists('WBTM_Standalone_Mail', 'force_resend'))
									);
									if ($wbtm_can_resend) :
								?>
									<div class="wbtm-bkl-dropdown-divider" role="separator"></div>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-resend-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>" data-email="<?php echo esc_attr(get_post_meta($id, 'wbtm_user_email', true)); ?>">
										<span class="dashicons dashicons-email-alt"></span><?php esc_html_e('Resend E-Voucher', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
								<?php endif; ?>

								<?php if ($is_admin) : ?>
									<div class="wbtm-bkl-dropdown-divider" role="separator"></div>
									<button type="button" class="wbtm-bkl-dropdown-item wbtm-bkl-del-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>">
										<span class="dashicons dashicons-trash"></span><?php esc_html_e('Delete', 'bus-ticket-booking-with-seat-reservation'); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
				<?php
			}

			/**
			 * Pro-only: the per-user "show / hide columns" popover. Styled with the
			 * Booking List's own design tokens (no passenger-list markup). Saved via
			 * AJAX (ajax_save_columns) and re-applied live to the table by the JS.
			 */
			private function render_column_settings($vis) {
				?>
				<div id="wbtm-bkl-columns-panel" class="wbtm-bkl-columns-panel" style="display:none;">
					<div class="wbtm-bkl-columns-panel-head">
						<span class="dashicons dashicons-columns"></span>
						<strong><?php esc_html_e('Show / Hide Columns', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
						<span class="wbtm-bkl-columns-close dashicons dashicons-no-alt" role="button" aria-label="<?php esc_attr_e('Close', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
					</div>
					<div class="wbtm-bkl-columns-panel-body">
						<?php foreach ($this->get_columns() as $key => $label) :
							$checked = !isset($vis[$key]) || $vis[$key]; ?>
							<label class="wbtm-bkl-columns-opt">
								<input type="checkbox" class="wbtm-bkl-column-toggle" data-col="<?php echo esc_attr($key); ?>" <?php checked($checked); ?>>
								<span><?php echo esc_html($label); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="wbtm-bkl-columns-panel-foot">
						<span class="wbtm-bkl-columns-msg" aria-live="polite"></span>
						<button type="button" id="wbtm-bkl-columns-save" class="wbtm-bkl-btn wbtm-bkl-btn-primary wbtm-bkl-btn-sm"><span class="dashicons dashicons-saved"></span><?php esc_html_e('Save', 'bus-ticket-booking-with-seat-reservation'); ?></button>
					</div>
				</div>
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
				if (!current_user_can('manage_options') && !current_user_can('wbtm_staff_access')) {
					return;
				}
				if (!$this->is_pro() || get_post_type($id) !== 'wbtm_bus_booking') {
					wp_safe_redirect(add_query_arg(array('post_type' => 'wbtm_bus', 'page' => self::PAGE_SLUG), admin_url('edit.php')));
					exit;
				}
				// Admin-only controls (change status, notes, WC order link) are gated on
				// $is_admin below; staff get a read-only detail view.
				$is_admin = current_user_can('manage_options');

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
				// For a full-bus booking, wbtm_bus_fare already holds the FINAL (discounted)
				// price. The fare-breakdown below lists the pre-discount base fare plus a
				// separate discount line, so the fare row shows the base and the total is
				// base − discount (+ services) — adding $fare on top of that would count the
				// full-bus price twice (the list row total uses $fare directly, unaffected).
				$fare_line   = $full_bus_base > 0 ? $full_bus_base : $fare;
				$grand_total = ($full_bus_base > 0 ? max(0, $full_bus_base - $full_bus_discount) : $fare) + $services_total;
				// Tax-inclusive pricing: $grand_total already contains $detail_tax, so the
				// tax is rendered as a breakdown line under the total, never added to it.
				$detail_tax     = $this->booking_tax($id);
				$detail_deposit = $this->deposit_info($id);

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
								<?php echo wp_kses_post($this->leg_badge($id, $order_id)); ?>
							</h1>
							<p class="wbtm-bkl-subtitle"><?php esc_html_e('Booked on', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo esc_html($booking_date ?: get_the_date('Y-m-d H:i', $id)); ?></p>
						</div>
						<div class="wbtm-bkl-header-actions">
							<?php if ($is_admin) : ?>
								<button type="button" class="wbtm-bkl-btn wbtm-bkl-btn-outline wbtm-bkl-change-status-btn" data-id="<?php echo esc_attr($id); ?>" data-ref="<?php echo esc_attr($reference); ?>" data-status="<?php echo esc_attr($status); ?>">
									<span class="dashicons dashicons-update"></span><?php esc_html_e('Change Status', 'bus-ticket-booking-with-seat-reservation'); ?>
								</button>
							<?php endif; ?>
							<?php if ($is_admin && $wc_active && $order_id && $this->booking_source($id) === 'woocommerce') : ?>
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
											<dt><?php esc_html_e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo $seat ? '<span class="wbtm-bkl-pill">' . esc_html($this->seat_label($id)) . '</span>' : '—'; ?></dd>
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
											<?php $detail_pax = $this->passenger_bits($id); ?>
											<dt><?php esc_html_e('Phone', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($user_phone ?: ($detail_pax['phone'] ?: '—')); ?></dd>
											<?php $detail_address = get_post_meta($id, 'wbtm_user_address', true); if (!$detail_address) { $detail_address = $detail_pax['address']; } ?>
											<dt><?php esc_html_e('Address', 'bus-ticket-booking-with-seat-reservation'); ?></dt><dd><?php echo esc_html($detail_address ?: '—'); ?></dd>
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
													// Each answer carries the form label it was collected under; fall
													// back to the field id only when that label is missing.
													$field_label = (is_array($field) && !empty($field['name'])) ? $field['name'] : '';
													if ($field_label === '') {
														$field_label = is_string($field_key) ? $this->humanize_field_id($field_key) : __('Field', 'bus-ticket-booking-with-seat-reservation');
													}
												?>
													<dt><?php echo esc_html($field_label); ?></dt>
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
												<td><?php echo esc_html($ticket ?: esc_html__('Seat fare', 'bus-ticket-booking-with-seat-reservation')); ?> <?php echo $seat ? '(' . esc_html($this->seat_label($id)) . ')' : ''; ?></td>
												<td>1</td>
												<td><?php echo wp_kses_post(WBTM_Global_Function::format_price($fare_line)); ?></td>
											</tr>
											<?php if ($full_bus_base && $full_bus_discount > 0) : ?>
												<tr>
													<td><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Full Bus Discount', 'bus-ticket-booking-with-seat-reservation'); ?></td>
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
											<?php if ($detail_tax > 0) : ?>
												<tr class="wbtm-bkl-tax-row">
													<td colspan="2"><?php esc_html_e('Of which tax (incl.)', 'bus-ticket-booking-with-seat-reservation'); ?></td>
													<td><?php echo wp_kses_post(WBTM_Global_Function::format_price($detail_tax)); ?></td>
												</tr>
												<tr class="wbtm-bkl-tax-row">
													<td colspan="2"><?php esc_html_e('Net (excl. tax)', 'bus-ticket-booking-with-seat-reservation'); ?></td>
													<td><?php echo wp_kses_post(WBTM_Global_Function::format_price(max(0, $grand_total - $detail_tax))); ?></td>
												</tr>
											<?php endif; ?>
											<?php if ($detail_deposit['is_deposit']) : ?>
												<tr class="wbtm-bkl-deposit-row">
													<td colspan="2"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Deposit Paid', 'bus-ticket-booking-with-seat-reservation'); ?></td>
													<td><?php echo wp_kses_post(WBTM_Global_Function::format_price($detail_deposit['paid'])); ?></td>
												</tr>
												<tr class="wbtm-bkl-deposit-row<?php echo $detail_deposit['remaining'] > 0 ? ' is-due' : ''; ?>">
													<td colspan="2">
														<span class="dashicons dashicons-clock"></span> <?php esc_html_e('Balance Remaining', 'bus-ticket-booking-with-seat-reservation'); ?>
														<?php if ($detail_deposit['remaining'] > 0 && $detail_deposit['due_date']) : ?>
															<small>(<?php
																/* translators: %s: balance due date. */
																echo esc_html(sprintf(__('due by %s', 'bus-ticket-booking-with-seat-reservation'), date_i18n(get_option('date_format'), strtotime($detail_deposit['due_date']))));
															?>)</small>
														<?php endif; ?>
													</td>
													<td><?php echo wp_kses_post(WBTM_Global_Function::format_price($detail_deposit['remaining'])); ?></td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>

						</div>

						<div class="wbtm-bkl-detail-sidebar">

							<div class="wbtm-bkl-detail-card">
								<div class="wbtm-bkl-detail-card-header"><span class="dashicons dashicons-admin-comments"></span><?php esc_html_e('Notes', 'bus-ticket-booking-with-seat-reservation'); ?></div>
								<div class="wbtm-bkl-detail-card-body">
									<?php if ($is_admin) : ?>
									<div class="wbtm-bkl-note-form">
										<textarea id="wbtm-bkl-note-input" rows="3" placeholder="<?php esc_attr_e('Add a private note…', 'bus-ticket-booking-with-seat-reservation'); ?>"></textarea>
										<button type="button" id="wbtm-bkl-note-add" class="wbtm-bkl-btn wbtm-bkl-btn-primary wbtm-bkl-btn-sm" data-id="<?php echo esc_attr($id); ?>">
											<span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e('Add Note', 'bus-ticket-booking-with-seat-reservation'); ?>
										</button>
									</div>
									<?php endif; ?>
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
