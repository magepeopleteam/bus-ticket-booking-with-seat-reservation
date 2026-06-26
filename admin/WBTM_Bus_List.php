<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Bus_List' ) ) {
		/**
		 * New responsive card/table design for the Bus fleet list screen.
		 *
		 * Controlled by the global setting "New Bus List Design" (General Settings).
		 * When enabled the default edit.php?post_type=wbtm_bus list is replaced by a
		 * fully responsive grid/table view. When disabled the classic WordPress list
		 * table is shown, so the toggle works in both directions without side effects.
		 */
		class WBTM_Bus_List {
			const PAGE_SLUG = 'wbtm_bus_list';

			public function __construct() {
				add_filter( 'wbtm_filter_general_settings', [ $this, 'register_setting' ] );
				add_action( 'admin_menu', [ $this, 'register_page' ] );
				add_action( 'load-edit.php', [ $this, 'maybe_redirect_to_new_design' ] );
				add_action( 'admin_action_wbtm_duplicate_bus', [ $this, 'handle_duplicate' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
				add_filter( 'parent_file', [ $this, 'highlight_menu' ] );
				add_filter( 'submenu_file', [ $this, 'highlight_submenu' ] );
				add_filter( 'admin_title', [ $this, 'admin_title' ], 10, 2 );
			}

			/**
			 * Set the browser tab title on the orphan list page (WordPress can't
			 * resolve it automatically because the page has no parent menu entry).
			 */
			public function admin_title( $admin_title, $title ) {
				if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
					return $admin_title;
				}
				$is_trash = isset( $_GET['wbtm_status'] ) && sanitize_text_field( wp_unslash( $_GET['wbtm_status'] ) ) === 'trash';
				$label    = WBTM_Functions::get_name() . ' ' . esc_html__( 'Lists', 'bus-ticket-booking-with-seat-reservation' );
				if ( $is_trash ) {
					$label = esc_html__( 'Trash', 'bus-ticket-booking-with-seat-reservation' ) . ' - ' . $label;
				}

				return $label . ' &lsaquo; ' . get_bloginfo( 'name' ) . ' &#8212; WordPress';
			}

			/**
			 * Keep the Bus CPT top menu open while viewing the orphan page.
			 */
			public function highlight_menu( $parent_file ) {
				global $pagenow;
				if ( $pagenow === 'admin.php' && ( $_GET['page'] ?? '' ) === self::PAGE_SLUG ) {
					return 'edit.php?post_type=wbtm_bus';
				}

				return $parent_file;
			}

			public function highlight_submenu( $submenu_file ) {
				if ( ( $_GET['page'] ?? '' ) === self::PAGE_SLUG ) {
					return 'edit.php?post_type=wbtm_bus';
				}

				return $submenu_file;
			}

			/**
			 * Whether the new design is currently turned on.
			 */
			public static function is_enabled(): bool {
				return WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'new_bus_list_design', 'enable' ) === 'enable';
			}

			/**
			 * Append the enable/disable toggle to the General settings tab.
			 */
			public function register_setting( $fields ) {
				$fields[] = array(
					'name'    => 'new_bus_list_design',
					'label'   => esc_html__( 'New Bus List Design', 'bus-ticket-booking-with-seat-reservation' ),
					'desc'    => esc_html__( 'Enable the new responsive card/table design for the bus list screen. Disable to use the classic WordPress list.', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => 'select',
					'default' => 'enable',
					'options' => array(
						'enable'  => esc_html__( 'Enable', 'bus-ticket-booking-with-seat-reservation' ),
						'disable' => esc_html__( 'Disable', 'bus-ticket-booking-with-seat-reservation' ),
					),
				);

				return $fields;
			}

			/**
			 * Register the hidden admin page that renders the new design.
			 */
			public function register_page() {
				add_submenu_page(
					'', // Hidden: reachable via redirect / direct link only.
					esc_html__( 'Bus Fleet', 'bus-ticket-booking-with-seat-reservation' ),
					esc_html__( 'Bus Fleet', 'bus-ticket-booking-with-seat-reservation' ),
					'edit_wbtm_buses',
					self::PAGE_SLUG,
					[ $this, 'render_page' ]
				);
			}

			/**
			 * Send the default CPT list to the new design when the setting is on.
			 */
			public function maybe_redirect_to_new_design() {
				if ( ! self::is_enabled() ) {
					return;
				}
				if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
					return;
				}
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
				if ( $post_type !== 'wbtm_bus' ) {
					return;
				}
				// Let the classic list handle Trash and explicit "classic" requests.
				$status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';
				$view   = isset( $_GET['wbtm_view'] ) ? sanitize_text_field( wp_unslash( $_GET['wbtm_view'] ) ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				if ( $status === 'trash' || $view === 'classic' ) {
					return;
				}
				if ( ! current_user_can( 'edit_wbtm_buses' ) ) {
					return;
				}
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
				exit;
			}

			/**
			 * Handle the "Duplicate" row action.
			 *
			 * Fires on admin.php?action=wbtm_duplicate_bus (the core admin_action_{action}
			 * hook). Validates the nonce + capability, clones the bus as a draft and then
			 * sends the user straight to the new draft's edit screen — the same flow
			 * WooCommerce uses for "Duplicate product".
			 */
			public function handle_duplicate() {
				$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
				if ( ! $post_id ) {
					wp_die( esc_html__( 'No bus was specified to duplicate.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				check_admin_referer( 'wbtm_duplicate_bus_' . $post_id );

				// Same capability gate the rest of this screen uses.
				if ( ! current_user_can( 'edit_wbtm_buses' ) ) {
					wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$post = get_post( $post_id );
				if ( ! $post || $post->post_type !== 'wbtm_bus' ) {
					wp_die( esc_html__( 'The item you are trying to duplicate does not exist.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				$new_id = $this->duplicate_bus( $post );
				if ( is_wp_error( $new_id ) || ! $new_id ) {
					wp_die( esc_html__( 'Sorry, the item could not be duplicated. Please try again.', 'bus-ticket-booking-with-seat-reservation' ) );
				}

				wp_safe_redirect( admin_url( sprintf( 'post.php?post=%d&action=edit', $new_id ) ) );
				exit;
			}

			/**
			 * Create a draft copy of a bus, including its taxonomies, all meta and the
			 * featured image. Returns the new post ID or a WP_Error.
			 *
			 * @param WP_Post $post Source bus post.
			 * @return int|WP_Error
			 */
			private function duplicate_bus( $post ) {
				/* translators: %s: original bus title */
				$new_title = sprintf( esc_html__( '%s (Copy)', 'bus-ticket-booking-with-seat-reservation' ), $post->post_title );

				$new_id = wp_insert_post( wp_slash( array(
					'post_title'     => $new_title,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_status'    => 'draft', // Always a draft so an incomplete clone is never live.
					'post_type'      => $post->post_type,
					'post_author'    => get_current_user_id(),
					'post_parent'    => $post->post_parent,
					'menu_order'     => $post->menu_order,
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
				) ), true );

				if ( is_wp_error( $new_id ) || ! $new_id ) {
					return $new_id;
				}

				// Copy every taxonomy attached to the bus (categories, stops, pick/drop points, features).
				foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
					$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						wp_set_object_terms( $new_id, $terms, $taxonomy );
					}
				}

				// Copy all custom fields (including _thumbnail_id, so the featured image carries over).
				// Editor-internal keys are intentionally skipped so the clone starts clean.
				$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' );
				$meta = get_post_meta( $post->ID );
				if ( is_array( $meta ) ) {
					foreach ( $meta as $key => $values ) {
						if ( in_array( $key, $skip, true ) ) {
							continue;
						}
						foreach ( $values as $value ) {
							// get_post_meta() returns raw (still-serialized) values; unserialize then
							// re-slash so add_post_meta() stores arrays/objects and special chars intact.
							add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
						}
					}
				}

				/**
				 * Fires after a bus has been duplicated, so add-ons can copy their own data.
				 *
				 * @param int     $new_id New (draft) bus ID.
				 * @param WP_Post $post   Original bus post.
				 */
				do_action( 'wbtm_bus_duplicated', $new_id, $post );

				return $new_id;
			}

			/**
			 * Load the dedicated CSS/JS only on the new design screen.
			 */
			public function enqueue_assets( $hook ) {
				if ( $hook !== 'admin_page_' . self::PAGE_SLUG ) {
					return;
				}
				wp_enqueue_style(
					'wbtm-bus-list-font',
					'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
					array(),
					null
				);
				wp_enqueue_style( 'wbtm_bus_list', WBTM_PLUGIN_URL . '/assets/admin/wbtm_bus_list.css', array(), WBTM_VERSION );
				wp_enqueue_script( 'wbtm_bus_list', WBTM_PLUGIN_URL . '/assets/admin/wbtm_bus_list.js', array( 'jquery' ), WBTM_VERSION, true );
			}

			/**
			 * Collect the fleet data once, shared by stats / grid / table.
			 */
			private function get_buses( $statuses = array( 'publish', 'draft', 'pending', 'private' ) ): array {
				$query = new WP_Query( array(
					'post_type'      => 'wbtm_bus',
					'post_status'    => $statuses,
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				) );
				$buses = array();
				foreach ( $query->posts as $post ) {
					$pid       = $post->ID;
					$category  = WBTM_Global_Function::get_post_info( $pid, 'wbtm_bus_category' );
					$seat_conf = WBTM_Global_Function::get_post_info( $pid, 'wbtm_seat_type_conf' );
					$is_nonac  = $category && stripos( $category, 'non' ) !== false;
					$is_ac     = $category && ! $is_nonac && stripos( $category, 'ac' ) !== false;

					// Route + schedule info for the Name column.
					$route_dir  = WBTM_Global_Function::get_post_info( $pid, 'wbtm_route_direction', array() );
					$route_dir  = is_array( $route_dir ) ? array_values( array_filter( array_map( 'trim', $route_dir ) ) ) : array();
					$has_return = WBTM_Global_Function::get_post_info( $pid, 'wbtm_same_bus_return_enabled', 'no' ) === 'yes';

					$buses[]   = array(
						'id'           => $pid,
						'title'        => get_the_title( $pid ) ?: esc_html__( '(no title)', 'bus-ticket-booking-with-seat-reservation' ),
						'coach_no'     => WBTM_Global_Function::get_post_info( $pid, 'wbtm_bus_no' ),
						'category'     => $category,
						'is_ac'        => $is_ac,
						'is_nonac'     => $is_nonac,
						'type'         => $is_ac ? 'AC' : ( $is_nonac ? 'Non AC' : '' ),
						'bus_type'     => $seat_conf === 'wbtm_seat_plan'
							? esc_html__( 'Seat Plan', 'bus-ticket-booking-with-seat-reservation' )
							: esc_html__( 'Without Seat Plan', 'bus-ticket-booking-with-seat-reservation' ),
						'status'       => $post->post_status,
						'thumb'        => get_the_post_thumbnail_url( $pid, 'medium_large' ),
						'author'       => get_the_author_meta( 'display_name', $post->post_author ),
						'route'        => $route_dir,
						'start_point'  => $route_dir ? reset( $route_dir ) : '',
						'end_point'    => $route_dir ? end( $route_dir ) : '',
						'has_return'   => $has_return,
						'journey_date' => $this->journey_date_text( $pid ),
						'view_link'    => get_permalink( $pid ),
						'edit_link'    => get_edit_post_link( $pid, 'raw' ),
						'trash_link'   => get_delete_post_link( $pid ),
						'restore_link' => wp_nonce_url( admin_url( sprintf( 'post.php?post=%d&action=untrash', $pid ) ), 'untrash-post_' . $pid ),
						'delete_link'  => get_delete_post_link( $pid, '', true ),
						'duplicate_link' => wp_nonce_url(
							admin_url( sprintf( 'admin.php?action=wbtm_duplicate_bus&post=%d', $pid ) ),
							'wbtm_duplicate_bus_' . $pid
						),
					);
				}

				return $buses;
			}

			private function initials( $name ): string {
				$name  = trim( wp_strip_all_tags( (string) $name ) );
				if ( $name === '' ) {
					return '?';
				}
				$parts = preg_split( '/\s+/', $name );
				$first = mb_substr( $parts[0], 0, 1 );
				$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';

				return mb_strtoupper( $first . $last );
			}

			/**
			 * Human readable journey date / schedule for a bus.
			 * Handles both "Particular" dates and "Repeated" start–end range.
			 */
			private function journey_date_text( $post_id ): string {
				$date_type = WBTM_Global_Function::get_post_info( $post_id, 'show_operational_on_day', 'no' );
				if ( $date_type === 'yes' ) {
					$dates = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_particular_dates', array() );
					$dates = is_array( $dates ) ? array_values( array_filter( $dates ) ) : array();
					if ( empty( $dates ) ) {
						return '';
					}
					$count = count( $dates );
					$first = $dates[0];
					if ( $count === 1 ) {
						return $first;
					}

					return sprintf(
						/* translators: 1: first date, 2: number of remaining dates */
						esc_html__( '%1$s +%2$d more', 'bus-ticket-booking-with-seat-reservation' ),
						$first,
						$count - 1
					);
				}

				$start = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_repeated_start_date' );
				$end   = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_repeated_end_date' );
				if ( ! $start && ! $end ) {
					return '';
				}
				$start_fmt = $start ? WBTM_Global_Function::date_format( $start, 'date' ) : '';
				$end_fmt   = $end ? WBTM_Global_Function::date_format( $end, 'date' ) : '';
				if ( $start_fmt && $end_fmt ) {
					return $start_fmt . ' – ' . $end_fmt;
				}

				return $start_fmt ?: $end_fmt;
			}

			/**
			 * Render the extra route / journey / return details shown under the
			 * bus name in both the grid card and the list (table) view.
			 */
			private function name_extra_info( $b ): void {
				$has_route   = ! empty( $b['start_point'] ) && ! empty( $b['end_point'] );
				$has_journey = ! empty( $b['journey_date'] );
				if ( ! $has_route && ! $has_journey ) {
					return;
				}
				?>
				<div class="wbtm-name-extra">
					<?php if ( $has_route ) : ?>
						<span class="wbtm-name-row wbtm-name-route" title="<?php echo esc_attr( implode( ' → ', $b['route'] ) ); ?>">
							<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="10" r="3"/><path d="M12 21s-7-6.3-7-11a7 7 0 0114 0c0 4.7-7 11-7 11z"/></svg>
							<span class="wbtm-route-txt"><?php echo esc_html( $b['start_point'] ); ?> <span class="wbtm-route-arrow">&rarr;</span> <?php echo esc_html( $b['end_point'] ); ?></span>
							<?php if ( count( $b['route'] ) > 2 ) : ?>
								<span class="wbtm-route-stops"><?php echo esc_html( sprintf( _n( '%d stop', '%d stops', count( $b['route'] ), 'bus-ticket-booking-with-seat-reservation' ), count( $b['route'] ) ) ); ?></span>
							<?php endif; ?>
						</span>
					<?php endif; ?>
					<?php if ( $has_journey ) : ?>
						<span class="wbtm-name-row wbtm-name-date">
							<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
							<span><?php echo esc_html( $b['journey_date'] ); ?></span>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $b['has_return'] ) && $has_route ) : ?>
						<span class="wbtm-name-row wbtm-name-return">
							<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 00-4-4H4"/></svg>
							<span><?php echo esc_html( $b['end_point'] ); ?> <span class="wbtm-route-arrow">&rarr;</span> <?php echo esc_html( $b['start_point'] ); ?></span>
							<span class="wbtm-return-tag"><?php esc_html_e( 'Return', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
						</span>
					<?php endif; ?>
				</div>
				<?php
			}

			private function status_label( $status ): string {
				switch ( $status ) {
					case 'publish':
						return esc_html__( 'Published', 'bus-ticket-booking-with-seat-reservation' );
					case 'draft':
						return esc_html__( 'Draft', 'bus-ticket-booking-with-seat-reservation' );
					case 'pending':
						return esc_html__( 'Pending', 'bus-ticket-booking-with-seat-reservation' );
					case 'private':
						return esc_html__( 'Private', 'bus-ticket-booking-with-seat-reservation' );
					default:
						return esc_html( ucfirst( $status ) );
				}
			}

			public function render_page() {
				$name = WBTM_Functions::get_name();
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_trash = isset( $_GET['wbtm_status'] ) && sanitize_text_field( wp_unslash( $_GET['wbtm_status'] ) ) === 'trash';

				// Active fleet always drives the stat cards & tab counts.
				$active    = $this->get_buses();
				$total     = count( $active );
				$published = 0;
				$draft     = 0;
				$ac        = 0;
				$nonac     = 0;
				foreach ( $active as $b ) {
					if ( $b['status'] === 'publish' ) {
						$published++;
					} elseif ( $b['status'] === 'draft' ) {
						$draft++;
					}
					if ( $b['is_ac'] ) {
						$ac++;
					} elseif ( $b['is_nonac'] ) {
						$nonac++;
					}
				}
				$status_counts = wp_count_posts( 'wbtm_bus' );
				$trash         = isset( $status_counts->trash ) ? (int) $status_counts->trash : 0;

				// Grid shows trashed buses in trash view, active fleet otherwise.
				$buses     = $is_trash ? $this->get_buses( array( 'trash' ) ) : $active;
				$base_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
				$trash_url = add_query_arg( 'wbtm_status', 'trash', $base_url );
				$add_url   = admin_url( 'post-new.php?post_type=wbtm_bus' );
				$classic   = admin_url( 'edit.php?post_type=wbtm_bus&wbtm_view=classic' );
				?>
				<div class="wrap wbtm-fleet-wrap">
					<div class="wbtm-fleet">

						<div class="wbtm-page-header">
							<div class="wbtm-page-title"><?php echo esc_html( $name ); ?> <?php esc_html_e( 'Lists', 'bus-ticket-booking-with-seat-reservation' ); ?>
								<span><?php echo esc_html( sprintf( _n( '%d bus', '%d buses', $total, 'bus-ticket-booking-with-seat-reservation' ), $total ) ); ?></span>
							</div>
							<div class="wbtm-header-actions">
								<a class="wbtm-classic-link" href="<?php echo esc_url( $classic ); ?>">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
									<?php esc_html_e( 'Classic view', 'bus-ticket-booking-with-seat-reservation' ); ?>
								</a>
								<a class="wbtm-add-btn" href="<?php echo esc_url( $add_url ); ?>">
									<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
									<?php printf( esc_html__( 'Add New %s', 'bus-ticket-booking-with-seat-reservation' ), esc_html( $name ) ); ?>
								</a>
							</div>
						</div>

						<div class="wbtm-stats">
							<div class="wbtm-stat-card">
								<div class="wbtm-stat-icon red">
									<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="13" rx="2"/><path d="M3 11h18"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/></svg>
								</div>
								<div><div class="wbtm-stat-num"><?php echo esc_html( $total ); ?></div><div class="wbtm-stat-label"><?php printf( esc_html__( 'Total %s', 'bus-ticket-booking-with-seat-reservation' ), esc_html( $name ) ); ?></div></div>
							</div>
							<div class="wbtm-stat-card">
								<div class="wbtm-stat-icon green">
									<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
								</div>
								<div><div class="wbtm-stat-num"><?php echo esc_html( $published ); ?></div><div class="wbtm-stat-label"><?php esc_html_e( 'Published', 'bus-ticket-booking-with-seat-reservation' ); ?></div></div>
							</div>
							<div class="wbtm-stat-card">
								<div class="wbtm-stat-icon blue">
									<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.5 19a4.5 4.5 0 100-9H4"/><path d="M12.5 5a3 3 0 110 6H3"/><path d="M17 14a3 3 0 110 6h-2"/></svg>
								</div>
								<div><div class="wbtm-stat-num"><?php echo esc_html( $ac ); ?></div><div class="wbtm-stat-label"><?php esc_html_e( 'AC Coach', 'bus-ticket-booking-with-seat-reservation' ); ?></div></div>
							</div>
							<div class="wbtm-stat-card">
								<div class="wbtm-stat-icon orange">
									<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.59 4.59A2 2 0 1111 8H2"/><path d="M12.59 11.41A2 2 0 1114 15H2"/><path d="M19.59 6.41A2 2 0 1121 10H2"/></svg>
								</div>
								<div><div class="wbtm-stat-num"><?php echo esc_html( $nonac ); ?></div><div class="wbtm-stat-label"><?php esc_html_e( 'Non AC Coach', 'bus-ticket-booking-with-seat-reservation' ); ?></div></div>
							</div>
						</div>

						<div class="wbtm-filters">
							<div class="wbtm-tab-pills">
								<?php if ( $is_trash ) : ?>
									<a class="wbtm-tab-pill" href="<?php echo esc_url( $base_url ); ?>"><?php printf( esc_html__( 'All (%d)', 'bus-ticket-booking-with-seat-reservation' ), $total ); ?></a>
									<a class="wbtm-tab-pill" href="<?php echo esc_url( $base_url ); ?>"><?php printf( esc_html__( 'Published (%d)', 'bus-ticket-booking-with-seat-reservation' ), $published ); ?></a>
									<a class="wbtm-tab-pill" href="<?php echo esc_url( $base_url ); ?>"><?php printf( esc_html__( 'Draft (%d)', 'bus-ticket-booking-with-seat-reservation' ), $draft ); ?></a>
									<span class="wbtm-tab-pill wbtm-tab-trash active">
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
										<?php printf( esc_html__( 'Trash (%d)', 'bus-ticket-booking-with-seat-reservation' ), $trash ); ?>
									</span>
								<?php else : ?>
									<button class="wbtm-tab-pill wbtm-filter-pill active" data-status=""><?php printf( esc_html__( 'All (%d)', 'bus-ticket-booking-with-seat-reservation' ), $total ); ?></button>
									<button class="wbtm-tab-pill wbtm-filter-pill" data-status="publish"><?php printf( esc_html__( 'Published (%d)', 'bus-ticket-booking-with-seat-reservation' ), $published ); ?></button>
									<button class="wbtm-tab-pill wbtm-filter-pill" data-status="draft"><?php printf( esc_html__( 'Draft (%d)', 'bus-ticket-booking-with-seat-reservation' ), $draft ); ?></button>
									<a class="wbtm-tab-pill wbtm-tab-trash" href="<?php echo esc_url( $trash_url ); ?>" title="<?php esc_attr_e( 'View trashed buses', 'bus-ticket-booking-with-seat-reservation' ); ?>">
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
										<?php printf( esc_html__( 'Trash (%d)', 'bus-ticket-booking-with-seat-reservation' ), $trash ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="wbtm-search-box">
								<svg class="wbtm-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
								<input type="text" placeholder="<?php esc_attr_e( 'Search buses...', 'bus-ticket-booking-with-seat-reservation' ); ?>" id="wbtmSearchInput" autocomplete="off">
								<button type="button" class="wbtm-search-clear" id="wbtmSearchClear" aria-label="<?php esc_attr_e( 'Clear search', 'bus-ticket-booking-with-seat-reservation' ); ?>">
									<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
								</button>
							</div>
							<select class="wbtm-filter-select" id="wbtmTypeFilter">
								<option value=""><?php esc_html_e( 'All Types', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
								<option value="AC"><?php esc_html_e( 'AC', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
								<option value="Non AC"><?php esc_html_e( 'Non AC', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
							</select>
							<div class="wbtm-view-toggle">
								<button class="wbtm-vtog active" id="wbtmGridBtn" title="<?php esc_attr_e( 'Grid view', 'bus-ticket-booking-with-seat-reservation' ); ?>">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
								</button>
								<button class="wbtm-vtog" id="wbtmListBtn" title="<?php esc_attr_e( 'List view', 'bus-ticket-booking-with-seat-reservation' ); ?>">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
								</button>
							</div>
						</div>

						<?php if ( empty( $buses ) ) : ?>
							<div class="wbtm-no-data">
								<?php if ( $is_trash ) : ?>
									<p><?php esc_html_e( 'Trash is empty.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
									<a class="wbtm-classic-link" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Back to list', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
								<?php else : ?>
									<p><?php printf( esc_html__( 'No %s found yet.', 'bus-ticket-booking-with-seat-reservation' ), esc_html( strtolower( $name ) ) ); ?></p>
									<a class="wbtm-add-btn" href="<?php echo esc_url( $add_url ); ?>"><?php printf( esc_html__( 'Add your first %s', 'bus-ticket-booking-with-seat-reservation' ), esc_html( $name ) ); ?></a>
								<?php endif; ?>
							</div>
						<?php else : ?>

						<div class="wbtm-bus-grid" id="wbtmBusGrid">
							<?php foreach ( $buses as $b ) :
								$type_badge_class = $b['is_ac'] ? 'ac' : 'nonac';
								$type_badge_text  = $b['type'] ?: esc_html__( 'Coach', 'bus-ticket-booking-with-seat-reservation' );
								?>
								<div class="wbtm-bus-card" data-name="<?php echo esc_attr( strtolower( $b['title'] . ' ' . $b['coach_no'] ) ); ?>" data-type="<?php echo esc_attr( $b['type'] ); ?>" data-status="<?php echo esc_attr( $b['status'] ); ?>">
									<div class="wbtm-bus-thumb">
										<?php if ( $b['thumb'] ) : ?>
											<img src="<?php echo esc_url( $b['thumb'] ); ?>" alt="<?php echo esc_attr( $b['title'] ); ?>">
										<?php else : ?>
											<div class="wbtm-thumb-placeholder">
												<svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="13" rx="2"/><path d="M3 11h18"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/></svg>
											</div>
										<?php endif; ?>
										<div class="wbtm-bus-thumb-overlay"></div>
										<div class="wbtm-bus-thumb-badges">
											<span class="wbtm-thumb-badge <?php echo esc_attr( $type_badge_class ); ?>"><?php echo esc_html( $type_badge_text ); ?></span>
											<span class="wbtm-thumb-badge nonac"><?php echo esc_html( $b['bus_type'] ); ?></span>
										</div>
										<?php if ( $b['coach_no'] ) : ?><div class="wbtm-bus-coach-no"><?php echo esc_html( $b['coach_no'] ); ?></div><?php endif; ?>
										<div class="wbtm-bus-actions-top">
											<?php if ( $is_trash ) : ?>
												<a class="wbtm-act-btn restore" href="<?php echo esc_url( $b['restore_link'] ); ?>" title="<?php esc_attr_e( 'Restore', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9 9 0 00-7 3.3"/><polyline points="3 4 3 8 7 8"/></svg>
												</a>
												<a class="wbtm-act-btn del" href="<?php echo esc_url( $b['delete_link'] ); ?>" title="<?php esc_attr_e( 'Delete Permanently', 'bus-ticket-booking-with-seat-reservation' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this bus? This cannot be undone.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>');">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
												</a>
											<?php else : ?>
												<?php if ( $b['view_link'] ) : ?>
												<a class="wbtm-act-btn view" href="<?php echo esc_url( $b['view_link'] ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View on frontend', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
												</a>
												<?php endif; ?>
												<a class="wbtm-act-btn edit" href="<?php echo esc_url( $b['edit_link'] ); ?>" title="<?php esc_attr_e( 'Edit', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
												</a>
												<a class="wbtm-act-btn dup" href="<?php echo esc_url( $b['duplicate_link'] ); ?>" title="<?php esc_attr_e( 'Duplicate', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
												</a>
												<a class="wbtm-act-btn del" href="<?php echo esc_url( $b['trash_link'] ); ?>" title="<?php esc_attr_e( 'Move to Trash', 'bus-ticket-booking-with-seat-reservation' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this bus to Trash?', 'bus-ticket-booking-with-seat-reservation' ) ); ?>');">
													<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
												</a>
											<?php endif; ?>
										</div>
									</div>
									<div class="wbtm-bus-body">
										<?php if ( $is_trash ) : ?>
											<span class="wbtm-bus-name"><?php echo esc_html( $b['title'] ); ?></span>
										<?php else : ?>
											<a class="wbtm-bus-name" href="<?php echo esc_url( $b['edit_link'] ); ?>"><?php echo esc_html( $b['title'] ); ?></a>
										<?php endif; ?>
										<div class="wbtm-bus-meta">
											<span class="wbtm-meta-pill type"><?php echo esc_html( $b['bus_type'] ); ?></span>
											<?php if ( $b['type'] ) : ?>
												<span class="wbtm-meta-pill <?php echo $b['is_ac'] ? 'coach' : 'nonac-pill'; ?>"><?php echo esc_html( $b['is_ac'] ? esc_html__( 'AC Coach', 'bus-ticket-booking-with-seat-reservation' ) : esc_html__( 'Non AC', 'bus-ticket-booking-with-seat-reservation' ) ); ?></span>
											<?php endif; ?>
										</div>
										<?php $this->name_extra_info( $b ); ?>
										<div class="wbtm-bus-footer">
											<div class="wbtm-bus-author"><span class="wbtm-author-avatar"><?php echo esc_html( $this->initials( $b['author'] ) ); ?></span> <?php echo esc_html( $b['author'] ); ?></div>
											<span class="wbtm-status-dot status-<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( $this->status_label( $b['status'] ) ); ?></span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<table class="wbtm-bus-table" id="wbtmBusTable">
							<thead>
								<tr>
									<th><?php printf( esc_html__( '%s Name', 'bus-ticket-booking-with-seat-reservation' ), esc_html( $name ) ); ?></th>
									<th><?php esc_html_e( 'Coach No', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
									<th><?php printf( esc_html__( '%s Type', 'bus-ticket-booking-with-seat-reservation' ), esc_html( $name ) ); ?></th>
									<th><?php echo esc_html( WBTM_Translations::text_coach_type() ); ?></th>
									<th><?php esc_html_e( 'Status', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $buses as $b ) : ?>
									<tr class="wbtm-row" data-name="<?php echo esc_attr( strtolower( $b['title'] . ' ' . $b['coach_no'] ) ); ?>" data-type="<?php echo esc_attr( $b['type'] ); ?>" data-status="<?php echo esc_attr( $b['status'] ); ?>">
										<td class="wbtm-name-cell" data-label="<?php esc_attr_e( 'Name', 'bus-ticket-booking-with-seat-reservation' ); ?>">
											<div class="wbtm-name-wrap">
												<div class="wbtm-name-thumb">
													<?php if ( $b['thumb'] ) : ?>
														<img src="<?php echo esc_url( $b['thumb'] ); ?>" alt="<?php echo esc_attr( $b['title'] ); ?>">
													<?php else : ?>
														<span class="wbtm-name-thumb-ph">
															<svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="13" rx="2"/><path d="M3 11h18"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/></svg>
														</span>
													<?php endif; ?>
												</div>
												<div class="wbtm-name-info">
													<?php if ( $is_trash ) : ?>
														<span class="wbtm-name-title"><?php echo esc_html( $b['title'] ); ?></span>
													<?php else : ?>
														<a class="wbtm-name-title" href="<?php echo esc_url( $b['edit_link'] ); ?>"><?php echo esc_html( $b['title'] ); ?></a>
													<?php endif; ?>
													<?php $this->name_extra_info( $b ); ?>
												</div>
											</div>
										</td>
										<td data-label="<?php esc_attr_e( 'Coach No', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_html( $b['coach_no'] ?: '-' ); ?></td>
										<td data-label="<?php esc_attr_e( 'Type', 'bus-ticket-booking-with-seat-reservation' ); ?>"><span class="wbtm-t-badge type"><?php echo esc_html( $b['bus_type'] ); ?></span></td>
										<td data-label="<?php esc_attr_e( 'Coach', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php if ( $b['type'] ) : ?><span class="wbtm-t-badge <?php echo $b['is_ac'] ? 'ac' : 'nonac'; ?>"><?php echo esc_html( $b['type'] ); ?></span><?php else : ?>-<?php endif; ?></td>
										<td data-label="<?php esc_attr_e( 'Status', 'bus-ticket-booking-with-seat-reservation' ); ?>"><span class="wbtm-status-dot status-<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( $this->status_label( $b['status'] ) ); ?></span></td>
										<td data-label="<?php esc_attr_e( 'Actions', 'bus-ticket-booking-with-seat-reservation' ); ?>">
											<div class="wbtm-table-actions">
											<?php if ( $is_trash ) : ?>
												<a class="wbtm-icon-btn restore" href="<?php echo esc_url( $b['restore_link'] ); ?>" title="<?php esc_attr_e( 'Restore', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Restore', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9 9 0 00-7 3.3"/><polyline points="3 4 3 8 7 8"/></svg>
												</a>
												<a class="wbtm-icon-btn del" href="<?php echo esc_url( $b['delete_link'] ); ?>" title="<?php esc_attr_e( 'Delete Permanently', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Delete Permanently', 'bus-ticket-booking-with-seat-reservation' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this bus? This cannot be undone.', 'bus-ticket-booking-with-seat-reservation' ) ); ?>');">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
												</a>
											<?php else : ?>
												<?php if ( $b['view_link'] ) : ?>
												<a class="wbtm-icon-btn view" href="<?php echo esc_url( $b['view_link'] ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View on frontend', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'View on frontend', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
												</a>
												<?php endif; ?>
												<a class="wbtm-icon-btn edit" href="<?php echo esc_url( $b['edit_link'] ); ?>" title="<?php esc_attr_e( 'Edit', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
												</a>
												<a class="wbtm-icon-btn dup" href="<?php echo esc_url( $b['duplicate_link'] ); ?>" title="<?php esc_attr_e( 'Duplicate', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
												</a>
												<a class="wbtm-icon-btn del" href="<?php echo esc_url( $b['trash_link'] ); ?>" title="<?php esc_attr_e( 'Move to Trash', 'bus-ticket-booking-with-seat-reservation' ); ?>" aria-label="<?php esc_attr_e( 'Move to Trash', 'bus-ticket-booking-with-seat-reservation' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this bus to Trash?', 'bus-ticket-booking-with-seat-reservation' ) ); ?>');">
													<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
												</a>
											<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="wbtm-empty" id="wbtmEmptyMsg"><?php esc_html_e( 'No buses found matching your search.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>

						<div class="wbtm-pagination" id="wbtmPagination">
							<div class="wbtm-page-info" id="wbtmPageInfo"></div>
							<div class="wbtm-page-btns" id="wbtmPageBtns"></div>
						</div>

						<?php endif; ?>
					</div>
				</div>
				<?php
			}
		}

		new WBTM_Bus_List();
	}
