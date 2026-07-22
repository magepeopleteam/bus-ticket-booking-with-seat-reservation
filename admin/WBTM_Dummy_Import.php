<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Dummy_Import' ) ) {
		class WBTM_Dummy_Import {

			/**
			 * Final "import completed" flag (kept for backward compatibility with
			 * sites that already imported the demo data before this refactor).
			 */
			const DONE_OPTION = 'wbtm_bus_seat_plan_data_input_done';

			/**
			 * In-flight progress state for the chunked importer.
			 * Shape: array( 'phase' => 'tax'|'posts', 'index' => int ).
			 */
			const STATE_OPTION = 'wbtm_dummy_import_state';

			/**
			 * How many bus posts to insert per AJAX request. One keeps each
			 * request tiny so even hosts with a very low PHP memory_limit /
			 * max_execution_time (e.g. default shared hosting) never time out.
			 */
			const BATCH_SIZE = 1;

			public function __construct() {
				// The demo is now imported in small AJAX batches instead of one
				// heavy synchronous admin_init pass, so low-memory hosts don't
				// fatal on the first plugin page load.
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
				add_action( 'admin_footer', array( $this, 'render_progress_ui' ) );
				add_action( 'wp_ajax_wbtm_dummy_import_batch', array( $this, 'ajax_import_batch' ) );
			}

			/**
			 * Whether the demo import should run/resume for this site.
			 *
			 * A *fresh* import only starts when there are no published buses yet,
			 * but an already-started import always resumes (even though the first
			 * inserted bus makes the count non-zero), otherwise batching would
			 * abort itself after the first post.
			 *
			 * @return bool
			 */
			private function is_eligible() {
				if ( 'yes' === get_option( self::DONE_OPTION, 'no' ) ) {
					return false;
				}
				if ( ! post_type_exists( 'wbtm_bus' ) ) {
					return false; // Plugin/CPT not ready.
				}
				// Resume an in-progress import regardless of current post count.
				if ( is_array( get_option( self::STATE_OPTION, null ) ) ) {
					return true;
				}
				// Fresh start only on an empty site.
				return 0 === (int) wp_count_posts( 'wbtm_bus' )->publish;
			}

			/**
			 * Only run the auto-importer on this plugin's own admin screens
			 * (the post-activation redirect lands on edit.php?post_type=wbtm_bus),
			 * so we never surprise the user with a background import + reload
			 * while they are working on an unrelated admin page.
			 *
			 * @return bool
			 */
			private function is_plugin_screen() {
				if ( ! function_exists( 'get_current_screen' ) ) {
					return false;
				}
				$screen = get_current_screen();
				if ( ! $screen ) {
					return false;
				}
				if ( isset( $screen->post_type ) && 0 === strpos( (string) $screen->post_type, 'wbtm_' ) ) {
					return true;
				}
				// Plugin settings / welcome / dashboard pages live under the bus menu.
				return isset( $screen->id ) && false !== strpos( (string) $screen->id, 'wbtm' );
			}

			/**
			 * Enqueue the tiny importer script only when an import is pending.
			 */
			public function enqueue_assets() {
				if ( ! current_user_can( 'edit_wbtm_bus' ) || ! $this->is_plugin_screen() || ! $this->is_eligible() ) {
					return;
				}

				wp_enqueue_style(
					'wbtm-dummy-import',
					WBTM_PLUGIN_URL . '/assets/admin/wbtm_dummy_import.css',
					array(),
					WBTM_VERSION
				);
				wp_enqueue_script(
					'wbtm-dummy-import',
					WBTM_PLUGIN_URL . '/assets/admin/wbtm_dummy_import.js',
					array( 'jquery' ),
					WBTM_VERSION,
					true
				);
				wp_localize_script( 'wbtm-dummy-import', 'wbtm_dummy_import', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wbtm_dummy_import' ),
					'i18n'     => array(
						'importing' => __( 'Importing demo data...', 'bus-ticket-booking-with-seat-reservation' ),
						'done'      => __( 'Demo data imported successfully.', 'bus-ticket-booking-with-seat-reservation' ),
						'error'     => __( 'Demo import could not finish. It will retry on the next page load.', 'bus-ticket-booking-with-seat-reservation' ),
					),
				) );
			}

			/**
			 * Print the small progress toast (hidden until JS starts a batch).
			 */
			public function render_progress_ui() {
				if ( ! current_user_can( 'edit_wbtm_bus' ) || ! $this->is_plugin_screen() || ! $this->is_eligible() ) {
					return;
				}
				?>
				<div id="wbtm-dummy-import-toast" class="wbtm-di-toast" style="display:none;">
					<div class="wbtm-di-toast-inner">
						<span class="wbtm-di-spinner" aria-hidden="true"></span>
						<div class="wbtm-di-body">
							<p id="wbtm-di-text" class="wbtm-di-text"><?php esc_html_e( 'Importing demo data...', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							<div class="wbtm-di-bar"><div id="wbtm-di-fill" class="wbtm-di-fill"></div></div>
						</div>
					</div>
				</div>
				<?php
			}

			/**
			 * AJAX: process a single demo-import batch and report progress.
			 * The browser calls this repeatedly until 'done' is true.
			 */
			public function ajax_import_batch() {
				check_ajax_referer( 'wbtm_dummy_import', 'nonce' );

				if ( ! current_user_can( 'edit_wbtm_bus' ) ) {
					wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bus-ticket-booking-with-seat-reservation' ) ) );
				}

				// Nothing to do (already imported, or a fresh start on a non-empty site).
				if ( ! $this->is_eligible() ) {
					update_option( self::DONE_OPTION, 'yes' );
					delete_option( self::STATE_OPTION );
					wp_send_json_success( array( 'done' => true, 'percent' => 100 ) );
				}

				// Give this one small batch a little headroom without demanding a lot.
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				if ( function_exists( 'wp_raise_memory_limit' ) ) {
					wp_raise_memory_limit( 'admin' );
				}

				$state = get_option( self::STATE_OPTION, array( 'phase' => 'tax', 'index' => 0 ) );
				$state = wp_parse_args( (array) $state, array( 'phase' => 'tax', 'index' => 0 ) );

				// --- Phase 1: taxonomies (categories, stops, pickup points) ---
				if ( 'tax' === $state['phase'] ) {
					$this->insert_taxonomies();
					update_option( self::STATE_OPTION, array( 'phase' => 'posts', 'index' => 0 ) );
					wp_send_json_success( array(
						'done'    => false,
						'percent' => 10,
						'message' => __( 'Bus categories & stops created...', 'bus-ticket-booking-with-seat-reservation' ),
					) );
				}

				// --- Phase 2: bus posts, BATCH_SIZE at a time ---
				$buses = $this->dummy_cpt()['custom_post']['wbtm_bus'];
				$total = count( $buses );
				$index = (int) $state['index'];

				$processed = 0;
				while ( $index < $total && $processed < self::BATCH_SIZE ) {
					if ( isset( $buses[ $index ] ) ) {
						$this->insert_single_bus( 'wbtm_bus', $buses[ $index ] );
					}
					$index++;
					$processed++;
				}

				if ( $index < $total ) {
					update_option( self::STATE_OPTION, array( 'phase' => 'posts', 'index' => $index ) );
					$percent = 10 + (int) round( ( $index / $total ) * 85 );
					wp_send_json_success( array(
						'done'    => false,
						'percent' => $percent,
						/* translators: 1: imported count, 2: total count */
						'message' => sprintf( __( 'Importing buses %1$d / %2$d...', 'bus-ticket-booking-with-seat-reservation' ), $index, $total ),
					) );
				}

				// --- All done: flush rewrite rules once and mark complete ---
				flush_rewrite_rules();
				update_option( self::DONE_OPTION, 'yes' );
				delete_option( self::STATE_OPTION );

				wp_send_json_success( array(
					'done'    => true,
					'percent' => 100,
					'message' => __( 'Demo data imported successfully.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
			}

			/**
			 * Insert all demo taxonomy terms. Idempotent: only fills a taxonomy
			 * that currently has no terms, exactly like the original importer.
			 */
			private function insert_taxonomies() {
				$dummy_taxonomies = $this->dummy_taxonomy();
				if ( ! array_key_exists( 'taxonomy', $dummy_taxonomies ) ) {
					return;
				}
				foreach ( $dummy_taxonomies['taxonomy'] as $taxonomy => $dummy_taxonomy ) {
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$check_terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
					if ( ! is_string( $check_terms ) && sizeof( $check_terms ) != 0 ) {
						continue; // Already populated.
					}
					foreach ( $dummy_taxonomy as $taxonomy_data ) {
						$term = wp_insert_term( $taxonomy_data['name'], $taxonomy );
						if ( is_wp_error( $term ) ) {
							continue;
						}
						if ( array_key_exists( 'tax_data', $taxonomy_data ) ) {
							foreach ( $taxonomy_data['tax_data'] as $meta_key => $data ) {
								update_term_meta( $term['term_id'], $meta_key, $data );
							}
						}
					}
				}
			}

			/**
			 * Insert a single demo bus post with its taxonomy terms and meta.
			 * Mirrors the per-post logic of the original bulk importer.
			 *
			 * @param string $custom_post Post type slug.
			 * @param array  $dummy_data  One bus definition from dummy_cpt().
			 * @return int|false New post ID, or false on failure.
			 */
			private function insert_single_bus( $custom_post, $dummy_data ) {
				$args = array(
					'post_status' => 'publish',
					'post_type'   => $custom_post,
				);
				if ( isset( $dummy_data['name'] ) ) {
					$args['post_title'] = $dummy_data['name'];
				}
				if ( isset( $dummy_data['content'] ) ) {
					$args['post_content'] = $dummy_data['content'];
				}
				$post_id = wp_insert_post( $args );
				if ( ! $post_id || is_wp_error( $post_id ) ) {
					return false;
				}
				if ( array_key_exists( 'taxonomy_terms', $dummy_data ) && count( $dummy_data['taxonomy_terms'] ) ) {
					foreach ( $dummy_data['taxonomy_terms'] as $taxonomy_term ) {
						wp_set_object_terms( $post_id, $taxonomy_term['terms'], $taxonomy_term['taxonomy_name'], true );
					}
				}
				if ( array_key_exists( 'post_data', $dummy_data ) ) {
					foreach ( $dummy_data['post_data'] as $meta_key => $data ) {
						if ( $meta_key == 'feature_image' ) {
							// media_sideload_image() lives in admin media includes,
							// which are not loaded during an AJAX request.
							require_once ABSPATH . 'wp-admin/includes/media.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/image.php';
							$desc  = 'The Demo Dummy Image of the bus booking';
							$image = media_sideload_image( $data, $post_id, $desc, 'id' );
							if ( ! is_wp_error( $image ) ) {
								set_post_thumbnail( $post_id, $image );
							}
						} else {
							update_post_meta( $post_id, $meta_key, $data );
						}
					}
				}
				return $post_id;
			}

			public function dummy_taxonomy(): array {
				return [
					'taxonomy' => [
						'wbtm_bus_cat'       => [
							0 => [ 'name' => 'AC' ],
							1 => [ 'name' => 'Non AC' ],
						],
						'wbtm_bus_stops'     => [
							0 => [ 'name' => 'Berlin' ],
							1 => [ 'name' => 'Frankfurt' ],
							2 => [ 'name' => 'Hamburg' ],
							3 => [ 'name' => 'Paris' ],
						],
						'wbtm_bus_pickpoint' => [
							0 => [ 'name' => 'Berlin' ],
							1 => [ 'name' => 'Frankfurt' ],
							2 => [ 'name' => 'Hamburg' ],
							3 => [ 'name' => 'Paris' ],
						],
					],
				];
			}

			public function dummy_cpt(): array {
				return [
					'custom_post' => [
						'wbtm_bus' => [
							0 => [
								'name'      => 'Flix Bus Service',
								'post_data' => [
									//general
									'wbtm_bus_no'                => 'Flixbus-01',
									'wbtm_bus_category'          => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf'        => 'wbtm_seat_plan',
									'driver_seat_position'       => 'driver_left',
									'wbtm_seat_rows'             => '8',
									'wbtm_seat_cols'             => '5',
									'wbtm_get_total_seat'        => '64',
									'wbtm_bus_seats_info'        => $this->seat_info(),
									//upper desk
									'show_upper_desk'            => 'yes',
									'wbtm_seat_rows_dd'          => '8',
									'wbtm_seat_cols_dd'          => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd'     => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction'       => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'          => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'        => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'            => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '08:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '09:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '11:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '22:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'            => $this->seat_price(),
									//Extra service
									'show_extra_service'         => 'yes',
									'wbtm_extra_services'        => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'          => 'no',
									'wbtm_pickup_point'          => [],
									// date settings
									'show_operational_on_day'    => 'no',
									'wbtm_particular_dates'      => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date'   => gmdate( 'Y-m-d', strtotime( ' +5 day' ) ),
									'wbtm_repeated_end_date'     => gmdate( 'Y-m-d', strtotime( ' +100 day' ) ),
									'wbtm_repeated_after'        => '1',
									'wbtm_active_days'           => '90',
									'wbtm_off_days'              => 'saturday,sunday',
									'wbtm_off_dates'             => [
										gmdate( 'm-d', strtotime( ' +15 day' ) ),
										gmdate( 'm-d', strtotime( ' +25 day' ) ),
										gmdate( 'm-d', strtotime( ' +45 day' ) ),
										gmdate( 'm-d', strtotime( ' +55 day' ) ),
										gmdate( 'm-d', strtotime( ' +75 day' ) ),
										gmdate( 'm-d', strtotime( ' +90 day' ) ),
									],
									'wbtm_offday_schedule'       => [
										0 => [ 'from_date' => '01-25', 'to_date' => '01-28' ],
										1 => [ 'from_date' => '02-20', 'to_date' => '02-25' ],
										2 => [ 'from_date' => '04-10', 'to_date' => '04-12' ],
										3 => [ 'from_date' => '08-10', 'to_date' => '08-12' ],
										4 => [ 'from_date' => '11-11', 'to_date' => '12-12' ],
									]
								],
							],
							1 => [
								'name'      => 'Mega Bus Express',
								'post_data' => [
									//general
									'wbtm_bus_no'              => 'Megabus-01',
									'wbtm_bus_category'        => 'AC',
									//lower seat
									'wbtm_seat_type_conf'      => 'wbtm_seat_plan',
									'driver_seat_position'     => 'driver_left',
									'wbtm_seat_rows'           => '8',
									'wbtm_seat_cols'           => '5',
									'wbtm_get_total_seat'      => '32',
									'wbtm_bus_seats_info'      => $this->seat_info(),
									//upper desk
									'show_upper_desk'          => 'no',
									//price & Routing
									'wbtm_route_direction'     => [ 'Berlin', 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_bus_bp_stops'        => [ 'Berlin', 'Hamburg', 'Frankfurt' ],
									'wbtm_bus_next_stops'      => [ 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_route_info'          => [
										0 => [ 'place' => 'Berlin', 'type' => 'bp', 'time' => '08:00' ],
										1 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '09:30' ],
										2 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00' ],
										3 => [ 'place' => 'Paris', 'type' => 'dp', 'time' => '22:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'          => $this->seat_price_return(),
									//Extra service
									'show_extra_service'       => 'yes',
									'wbtm_extra_services'      => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'        => 'no',
									'wbtm_pickup_point'        => [],
									// date settings
									'show_operational_on_day'  => 'no',
									'wbtm_particular_dates'    => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date' => gmdate( 'Y-m-d', strtotime( ' +2 day' ) ),
									'wbtm_repeated_end_date'   => gmdate( 'Y-m-d', strtotime( ' +150 day' ) ),
									'wbtm_repeated_after'      => '1',
									'wbtm_active_days'         => '90',
									'wbtm_off_days'            => 'saturday,sunday',
									'wbtm_off_dates'           => [
										gmdate( 'm-d', strtotime( ' +10 day' ) ),
										gmdate( 'm-d', strtotime( ' +20 day' ) ),
										gmdate( 'm-d', strtotime( ' +30 day' ) ),
										gmdate( 'm-d', strtotime( ' +40 day' ) ),
										gmdate( 'm-d', strtotime( ' +45 day' ) ),
										gmdate( 'm-d', strtotime( ' +110 day' ) ),
									]
								],
							],
							2 => [
								'name'      => 'BYD Express',
								'post_data' => [
									//general
									'wbtm_bus_no'                => 'Bydbus-01',
									'wbtm_bus_category'          => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf'        => 'wbtm_seat_plan',
									'driver_seat_position'       => 'driver_left',
									'wbtm_seat_rows'             => '8',
									'wbtm_seat_cols'             => '5',
									'wbtm_get_total_seat'        => '64',
									'wbtm_bus_seats_info'        => $this->seat_info(),
									//upper desk
									'show_upper_desk'            => 'yes',
									'wbtm_seat_rows_dd'          => '8',
									'wbtm_seat_cols_dd'          => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd'     => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction'       => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'          => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'        => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'            => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '11:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '12:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '01:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '03:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'            => $this->seat_price(),
									//Extra service
									'show_extra_service'         => 'yes',
									'wbtm_extra_services'        => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'          => 'no',
									'wbtm_pickup_point'          => [],
									// date settings
									'show_operational_on_day'    => 'no',
									'wbtm_particular_dates'      => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09' ],
									'wbtm_repeated_start_date'   => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
									'wbtm_repeated_end_date'     => gmdate( 'Y-m-d', strtotime( ' +100 day' ) ),
									'wbtm_repeated_after'        => '3',
									'wbtm_active_days'           => '90',
									'wbtm_off_days'              => '',
									'wbtm_off_dates'             => [
										gmdate( 'm-d', strtotime( ' +2 day' ) ),
										gmdate( 'm-d', strtotime( ' +7 day' ) ),
										gmdate( 'm-d', strtotime( ' +30 day' ) ),
										gmdate( 'm-d', strtotime( ' +45 day' ) ),
									]
								],
							],
							3 => [
								'name'      => 'RED Coach',
								'post_data' => [
									//general
									'wbtm_bus_no'                => 'Redbus-01',
									'wbtm_bus_category'          => 'AC',
									//lower seat
									'wbtm_seat_type_conf'        => 'wbtm_seat_plan',
									'driver_seat_position'       => 'driver_left',
									'wbtm_seat_rows'             => '8',
									'wbtm_seat_cols'             => '5',
									'wbtm_get_total_seat'        => '64',
									'wbtm_bus_seats_info'        => $this->seat_info(),
									//upper desk
									'show_upper_desk'            => 'yes',
									'wbtm_seat_rows_dd'          => '8',
									'wbtm_seat_cols_dd'          => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd'     => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction'       => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'          => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'        => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'            => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '11:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '12:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '01:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '03:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'            => $this->seat_price(),
									//Extra service
									'show_extra_service'         => 'yes',
									'wbtm_extra_services'        => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'          => 'no',
									'wbtm_pickup_point'          => [],
									// date settings
									'show_operational_on_day'    => 'yes',
									'wbtm_particular_dates'      => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09' ],
									'wbtm_repeated_start_date'   => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
								],
							],
							4 => [
								'name'      => 'Bonanza Bus',
								'post_data' => [
									//general
									'wbtm_bus_no'              => 'Bonanzabus-01',
									'wbtm_bus_category'        => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf'      => 'wbtm_seat_plan',
									'driver_seat_position'     => 'driver_left',
									'wbtm_seat_rows'           => '8',
									'wbtm_seat_cols'           => '5',
									'wbtm_get_total_seat'      => '32',
									'wbtm_bus_seats_info'      => $this->seat_info(),
									//upper desk
									'show_upper_desk'          => 'no',
									//price & Routing
									'wbtm_route_direction'     => [ 'Berlin', 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_bus_bp_stops'        => [ 'Berlin', 'Hamburg', 'Frankfurt' ],
									'wbtm_bus_next_stops'      => [ 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_route_info'          => [
										0 => [ 'place' => 'Berlin', 'type' => 'bp', 'time' => '08:00' ],
										1 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '09:30' ],
										2 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00' ],
										3 => [ 'place' => 'Paris', 'type' => 'dp', 'time' => '22:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'          => $this->seat_price_return(),
									//Extra service
									'show_extra_service'       => 'yes',
									'wbtm_extra_services'      => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'        => 'no',
									'wbtm_pickup_point'        => [],
									// date settings
									'show_operational_on_day'  => 'no',
									'wbtm_particular_dates'    => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date' => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
									'wbtm_repeated_after'      => '1',
									'wbtm_active_days'         => '90',
								],
							],
							5 => [
								'name'      => 'Berlin Linien Bus',
								'post_data' => [
									//general
									'wbtm_bus_no'                => 'BerlinLinien-Bus-01',
									'wbtm_bus_category'          => 'AC',
									//lower seat
									'wbtm_seat_type_conf'        => 'wbtm_seat_plan',
									'driver_seat_position'       => 'driver_left',
									'wbtm_seat_rows'             => '8',
									'wbtm_seat_cols'             => '5',
									'wbtm_get_total_seat'        => '32',
									'wbtm_bus_seats_info'        => $this->seat_info(),
									//upper desk
									'show_upper_desk'            => 'no',
									'wbtm_seat_rows_dd'          => '8',
									'wbtm_seat_cols_dd'          => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd'     => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction'       => [ 'Berlin', 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_bus_bp_stops'          => [ 'Berlin', 'Hamburg', 'Frankfurt' ],
									'wbtm_bus_next_stops'        => [ 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_route_info'            => [
										0 => [ 'place' => 'Berlin', 'type' => 'bp', 'time' => '08:00' ],
										1 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '09:30' ],
										2 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00' ],
										3 => [ 'place' => 'Paris', 'type' => 'dp', 'time' => '22:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'            => $this->seat_price_return(),
									//Extra service
									'show_extra_service'         => 'yes',
									'wbtm_extra_services'        => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'          => 'no',
									'wbtm_pickup_point'          => [],
									// date settings
									'show_operational_on_day'    => 'no',
									'wbtm_particular_dates'      => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date'   => gmdate( 'Y-m-d', strtotime( ' +2 day' ) ),
									'wbtm_repeated_after'        => '1',
									'wbtm_active_days'           => '90',
									'wbtm_off_days'              => 'saturday,sunday',
									'wbtm_off_dates'             => [
										gmdate( 'm-d', strtotime( ' +10 day' ) ),
										gmdate( 'm-d', strtotime( ' +20 day' ) ),
										gmdate( 'm-d', strtotime( ' +30 day' ) ),
										gmdate( 'm-d', strtotime( ' +40 day' ) ),
										gmdate( 'm-d', strtotime( ' +45 day' ) ),
										gmdate( 'm-d', strtotime( ' +110 day' ) ),
									],
								],
							],
							6 => [
								'name'      => 'Royal Bus',
								'post_data' => [
									//general
									'wbtm_bus_no'              => 'royal_706',
									'wbtm_bus_category'        => 'AC',
									//lower seat
									'wbtm_seat_type_conf'      => 'wbtm_seat_plan',
									'driver_seat_position'     => 'driver_left',
									'wbtm_seat_rows'           => '8',
									'wbtm_seat_cols'           => '5',
									'wbtm_get_total_seat'      => '32',
									'wbtm_bus_seats_info'      => $this->seat_info(),
									//upper desk
									'show_upper_desk'          => 'no',
									//price & Routing
									'wbtm_route_direction'     => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'        => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'      => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'          => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '09:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '12:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '01:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'          => $this->seat_price(),
									//Extra service
									'show_extra_service'       => 'yes',
									'wbtm_extra_services'      => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'        => 'no',
									'wbtm_pickup_point'        => [],
									// date settings
									'show_operational_on_day'  => 'no',
									'wbtm_particular_dates'    => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date' => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
									'wbtm_repeated_after'      => '1',
									'wbtm_active_days'         => '90',
								],
							],
							7 => [
								'name'      => 'Bold Bus',
								'post_data' => [
									//general
									'wbtm_bus_no'                => 'bold_706',
									'wbtm_bus_category'          => 'AC',
									//lower seat
									'wbtm_seat_type_conf'        => 'wbtm_seat_plan',
									'driver_seat_position'       => 'driver_left',
									'wbtm_seat_rows'             => '8',
									'wbtm_seat_cols'             => '5',
									'wbtm_get_total_seat'        => '32',
									'wbtm_bus_seats_info'        => $this->seat_info(),
									//upper desk
									'show_upper_desk'            => 'no',
									'wbtm_seat_rows_dd'          => '8',
									'wbtm_seat_cols_dd'          => '5',
									'wbtm_seat_dd_price_parcent' => '10',
									'wbtm_bus_seats_info_dd'     => $this->seat_info_dd(),
									//price & Routing
									'wbtm_route_direction'       => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'          => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'        => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'            => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '09:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '12:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '01:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'            => $this->seat_price(),
									//Extra service
									'show_extra_service'         => 'yes',
									'wbtm_extra_services'        => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'          => 'no',
									'wbtm_pickup_point'          => [],
									// date settings
									'show_operational_on_day'    => 'yes',
									'wbtm_particular_dates'      => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date'   => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
									'wbtm_repeated_after'        => '1',
									'wbtm_active_days'           => '90',
								],
							],
							8 => [
								'name'      => 'Eco Move',
								'post_data' => [
									//general
									'wbtm_bus_no'              => 'eco_706',
									'wbtm_bus_category'        => 'Non AC',
									//lower seat
									'wbtm_seat_type_conf'      => 'wbtm_seat_plan',
									'driver_seat_position'     => 'driver_left',
									'wbtm_seat_rows'           => '8',
									'wbtm_seat_cols'           => '5',
									'wbtm_get_total_seat'      => '32',
									'wbtm_bus_seats_info'      => $this->seat_info(),
									//upper desk
									'show_upper_desk'          => 'no',
									//price & Routing
									'wbtm_route_direction'     => [ 'Paris', 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_bus_bp_stops'        => [ 'Paris', 'Frankfurt', 'Hamburg' ],
									'wbtm_bus_next_stops'      => [ 'Frankfurt', 'Hamburg', 'Berlin' ],
									'wbtm_route_info'          => [
										0 => [ 'place' => 'Paris', 'type' => 'bp', 'time' => '09:00' ],
										1 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '10:30' ],
										2 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '12:00' ],
										3 => [ 'place' => 'Berlin', 'type' => 'dp', 'time' => '01:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'          => $this->seat_price(),
									//Extra service
									'show_extra_service'       => 'yes',
									'wbtm_extra_services'      => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'        => 'no',
									'wbtm_pickup_point'        => [],
									// date settings
									'show_operational_on_day'  => 'no',
									'wbtm_particular_dates'    => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date' => gmdate( 'Y-m-d', strtotime( ' +1 day' ) ),
									'wbtm_repeated_after'      => '1',
									'wbtm_active_days'         => '90',
								],
							],
							9 => [
								'name'      => 'Badger Bus Service',
								'post_data' => [
									//general
									'wbtm_bus_no'              => 'badger-01',
									'wbtm_bus_category'        => 'AC',
									//lower seat
									'wbtm_seat_type_conf'      => 'wbtm_seat_plan',
									'driver_seat_position'     => 'driver_left',
									'wbtm_seat_rows'           => '8',
									'wbtm_seat_cols'           => '5',
									'wbtm_get_total_seat'      => '32',
									'wbtm_bus_seats_info'      => $this->seat_info(),
									//upper desk
									'show_upper_desk'          => 'no',
									//price & Routing
									'wbtm_route_direction'     => [ 'Berlin', 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_bus_bp_stops'        => [ 'Berlin', 'Hamburg', 'Frankfurt' ],
									'wbtm_bus_next_stops'      => [ 'Hamburg', 'Frankfurt', 'Paris' ],
									'wbtm_route_info'          => [
										0 => [ 'place' => 'Berlin', 'type' => 'bp', 'time' => '08:00' ],
										1 => [ 'place' => 'Hamburg', 'type' => 'both', 'time' => '09:30' ],
										2 => [ 'place' => 'Frankfurt', 'type' => 'both', 'time' => '11:00' ],
										3 => [ 'place' => 'Paris', 'type' => 'dp', 'time' => '22:30' ],
									],
									// Seat Price
									'wbtm_bus_prices'          => $this->seat_price_return(),
									//Extra service
									'show_extra_service'       => 'yes',
									'wbtm_extra_services'      => $this->ex_service(),
									// Pickup Points
									'show_pickup_point'        => 'no',
									'wbtm_pickup_point'        => [],
									// date settings
									'show_operational_on_day'  => 'no',
									'wbtm_particular_dates'    => [ '01-01', '02-02', '03-03', '04-04', '05-05', '06-06', '07-07', '08-08', '09-09', '10-10', '11-11', '12-12' ],
									'wbtm_repeated_start_date' => gmdate( 'Y-m-d', strtotime( ' +5 day' ) ),
									'wbtm_repeated_after'      => '1',
									'wbtm_active_days'         => '90',
									'wbtm_off_days'            => 'saturday,sunday',
									'wbtm_off_dates'           => [
										gmdate( 'm-d', strtotime( ' +15 day' ) ),
										gmdate( 'm-d', strtotime( ' +25 day' ) ),
										gmdate( 'm-d', strtotime( ' +45 day' ) ),
										gmdate( 'm-d', strtotime( ' +55 day' ) ),
										gmdate( 'm-d', strtotime( ' +75 day' ) ),
										gmdate( 'm-d', strtotime( ' +90 day' ) ),
									],
								],
							],
						],
					],
				];
			}

			public function seat( $args = [] ): array {
				$seat = [];
				if ( sizeof( $args ) > 0 ) {
					$count = 1;
					foreach ( $args as $arg ) {
						$seat[ 'seat' . $count ] = $arg;
						$count ++;
					}
				}

				return $seat;
			}

			public function dd_seat( $args = [] ): array {
				$seat = [];
				if ( sizeof( $args ) > 0 ) {
					$count = 1;
					foreach ( $args as $arg ) {
						$seat[ 'dd_seat' . $count ] = $arg;
						$count ++;
					}
				}

				return $seat;
			}

			public function price( $args = [] ): array {
				$price_info = [];
				if ( sizeof( $args ) > 0 ) {
					$price_info['wbtm_bus_bp_price_stop'] = $args[0];
					$price_info['wbtm_bus_dp_price_stop'] = $args[1];
					$price_info['wbtm_bus_price']         = $args[2];
					$price_info['wbtm_bus_child_price']   = $args[3];
					$price_info['wbtm_bus_infant_price']  = $args[4];
				}

				return $price_info;
			}

			public function seat_info(): array {
				return array(
					0 => $this->seat( [ 'A1', 'A2', '', 'A3', 'A4' ] ),
					1 => $this->seat( [ 'B1', 'B2', '', 'B3', 'B4' ] ),
					2 => $this->seat( [ 'C1', 'C2', '', 'C3', 'C4' ] ),
					3 => $this->seat( [ 'D1', 'D2', '', 'D3', 'D4' ] ),
					4 => $this->seat( [ 'E1', 'E2', '', 'E3', 'E4' ] ),
					5 => $this->seat( [ 'F1', 'F2', '', 'F3', 'F4' ] ),
					6 => $this->seat( [ 'G1', 'G2', '', 'G3', 'G4' ] ),
					7 => $this->seat( [ 'H1', 'H2', '', 'H3', 'H4' ] ),
				);
			}

			public function seat_price(): array {
				return array(
					0 => $this->price( [ 'Paris', 'Frankfurt', 10, '', '' ] ),
					1 => $this->price( [ 'Paris', 'Hamburg', 15, '', '' ] ),
					2 => $this->price( [ 'Paris', 'Berlin', 20, '', '' ] ),
					3 => $this->price( [ 'Frankfurt', 'Hamburg', 7, '', '' ] ),
					4 => $this->price( [ 'Frankfurt', 'Berlin', 12, '', '' ] ),
					5 => $this->price( [ 'Hamburg', 'Berlin', 8, '', '' ] )
				);
			}

			public function seat_price_return(): array {
				return array(
					0 => $this->price( [ 'Berlin', 'Hamburg', 10, '', '' ] ),
					1 => $this->price( [ 'Berlin', 'Frankfurt', 15, '', '' ] ),
					2 => $this->price( [ 'Berlin', 'Paris', 20, '', '' ] ),
					3 => $this->price( [ 'Hamburg', 'Frankfurt', 7, '', '' ] ),
					4 => $this->price( [ 'Hamburg', 'Paris', 12, '', '' ] ),
					5 => $this->price( [ 'Frankfurt', 'Paris', 8, '', '' ] )
				);
			}

			public function seat_info_dd(): array {
				return array(
					0 => $this->dd_seat( [ 'S1', 'S2', '', 'S3', 'S4' ] ),
					1 => $this->dd_seat( [ 'T1', 'T2', '', 'T3', 'T4' ] ),
					2 => $this->dd_seat( [ 'U1', 'U2', '', 'U3', 'U4' ] ),
					3 => $this->dd_seat( [ 'V1', 'V2', '', 'V3', 'V4' ] ),
					4 => $this->dd_seat( [ 'W1', 'W2', '', 'W3', 'W4' ] ),
					5 => $this->dd_seat( [ 'X1', 'X2', '', 'X3', 'X4' ] ),
					6 => $this->dd_seat( [ 'Y1', 'Y2', '', 'Y3', 'Y4' ] ),
					7 => $this->dd_seat( [ 'Z1', 'Z2', '', 'Z3', 'Z4' ] ),
				);
			}

			public function ex_service(): array {
				return [
					0 => [ 'option_name' => 'Welcome Drink', 'option_price' => '50', 'option_qty' => '500', 'option_qty_type' => 'inputbox', ],
					1 => [ 'option_name' => 'Cap', 'option_price' => '70', 'option_qty' => '500', 'option_qty_type' => 'inputbox', ],
				];
			}
		}
	}