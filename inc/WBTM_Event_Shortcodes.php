<?php
	/*
	 * @Author 		MagePeople Team
	 * Copyright: 	mage-people.com
	 *
	 * Embeddable booking shortcodes for page builders (Elementor, Gutenberg, …):
	 *
	 *  [wbtm-bus-booking id="123"]       One bus: its details plus the booking form
	 *                                     (route → date → seats → add to cart) on any page.
	 *  [wbtm-bus-event event="milan"]     Every bus of an event / destination / ID list:
	 *                                     a departure overview (stop, time, price, seats)
	 *                                     and one booking form scoped to those buses.
	 *
	 * Both reuse the standard search form and result templates, so seat plans,
	 * pricing, extra services, cart and checkout behave exactly as on the bus
	 * page; only the set of buses (WBTM_Bus_Scope) and the field presets differ.
	 * Also owns the "Event Booking Pages" settings tab.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Event_Shortcodes' ) ) {
		class WBTM_Event_Shortcodes {
			const SETTINGS = 'wbtm_event_settings';
			/** @var WBTM_Event_Shortcodes|null */
			private static $instance = null;
			public function __construct() {
				self::$instance = $this;
				add_shortcode( 'wbtm-bus-booking', array( $this, 'bus_booking' ) );
				add_shortcode( 'wbtm-bus-event', array( $this, 'bus_event' ) );
				add_action( 'wbtm_add_frontend_enqueue', array( $this, 'enqueue' ), 95 );
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'settings_section' ), 20 );
				add_filter( 'wbtm_settings_sec_fields', array( $this, 'settings_fields' ), 20 );
			}
			/**
			 * Render one of this class's shortcodes from an attribute array (used by
			 * the Elementor widgets, so values never pass through shortcode parsing).
			 */
			public static function render_shortcode( string $tag, array $atts ): string {
				if ( ! self::$instance ) {
					return '';
				}
				return $tag === 'wbtm-bus-booking' ? self::$instance->bus_booking( $atts ) : self::$instance->bus_event( $atts );
			}
			//==========================// assets
			public function enqueue(): void {
				$css = WBTM_PLUGIN_DIR . '/assets/frontend/wbtm_event_booking.css';
				$js  = WBTM_PLUGIN_DIR . '/assets/frontend/wbtm_event_booking.js';
				wp_enqueue_style( 'wbtm_event_booking', WBTM_PLUGIN_URL . '/assets/frontend/wbtm_event_booking.css', array(), file_exists( $css ) ? filemtime( $css ) : WBTM_VERSION );
				wp_enqueue_script( 'wbtm_event_booking', WBTM_PLUGIN_URL . '/assets/frontend/wbtm_event_booking.js', array( 'jquery', 'wbtm_global' ), file_exists( $js ) ? filemtime( $js ) : WBTM_VERSION, true );
				wp_localize_script( 'wbtm_event_booking', 'wbtm_event_strings', array(
					'stop_unavailable' => __( 'This boarding point is not available for this trip.', 'bus-ticket-booking-with-seat-reservation' ),
					'no_route'         => __( 'No bus from this boarding point goes to the destination. Please choose another stop.', 'bus-ticket-booking-with-seat-reservation' ),
					'date_unavailable' => __( 'There is no departure from this stop on the event date.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
			}
			//==========================// helpers
			/** Show/Hide toggle of the Event Booking Pages settings tab. */
			public static function setting_on( string $key ): bool {
				return WBTM_Global_Function::get_settings( self::SETTINGS, $key, 'show' ) !== 'hide';
			}
			/** Shortcode yes/no attribute; '' falls back to $default. */
			private static function flag( $value, bool $default ): bool {
				$value = strtolower( trim( (string) $value ) );
				if ( $value === '' ) {
					return $default;
				}
				return in_array( $value, array( 'yes', '1', 'true', 'on', 'show' ), true );
			}
			private static function notice( string $message ): string {
				// Configuration hints are only useful to people who can fix them.
				if ( ! current_user_can( 'edit_posts' ) ) {
					return '';
				}
				return '<div class="wbtm_style wbtm_container"><div class="wbtm_event_notice">' . esc_html( $message ) . '</div></div>';
			}
			/**
			 * Render the standard search form (+ AJAX results area) under a scope.
			 *
			 * @param int   $post_id Bus ID for a single-bus form, 0 for multi-bus.
			 * @param array $params  search_form.php parameters.
			 * @param int[] $ids     Scope bus IDs (multi-bus only).
			 */
			private static function render_search_form( int $post_id, array $params, array $ids, string $date, array $presets ): void {
				$params['force_ajax'] = 1;
				WBTM_Bus_Scope::push( $ids, $date, $presets );
				try {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook (prints cart notices), as the other bus shortcodes do.
					do_action( 'woocommerce_before_single_product' );
					require WBTM_Functions::template_path( 'layout/search_form.php' );
					do_action( 'wbtm_after_search_result_section', $params );
				} finally {
					WBTM_Bus_Scope::pop();
				}
			}
			private static function form_params( array $atts ): array {
				$style = sanitize_key( (string) ( $atts['style'] ?? '' ) );
				return array(
					'style'                => $style === 'flix' ? 'flix' : '',
					'left_filter'          => self::flag( $atts['left_filter'] ?? '', true ) ? 'on' : 'off',
					'left_filter_type'     => 'on',
					'left_filter_operator' => 'on',
					'left_filter_boarding' => 'on',
				);
			}
			/**
			 * Resolve the filter attributes shared by the event, search and list
			 * shortcodes. The first event supplies defaults for destination and date.
			 *
			 * @return array{0: ?WP_Term, 1: string, 2: string, 3: ?int[]}|string
			 *         [event term, destination, date, scope IDs (null = unscoped)],
			 *         or an error message when the event does not exist.
			 */
			public static function context( array $atts, bool $date_filters_buses = false ) {
				$event_keys = array_filter( array_map( 'trim', explode( ',', (string) ( $atts['event'] ?? '' ) ) ) );
				$term       = $event_keys ? WBTM_Bus_Events::get_event( reset( $event_keys ) ) : null;
				if ( $event_keys && ! $term ) {
					return sprintf(
						/* translators: %s: event slug/ID given in the shortcode */
						__( 'Bus event: no event matches "%s".', 'bus-ticket-booking-with-seat-reservation' ),
						implode( ', ', $event_keys )
					);
				}
				$destination = sanitize_text_field( (string) ( $atts['destination'] ?? '' ) );
				if ( $destination === '' && $term ) {
					$destination = WBTM_Bus_Events::event_destination( (int) $term->term_id );
				}
				$date = WBTM_Bus_Scope::sanitize_date( (string) ( $atts['date'] ?? '' ) );
				if ( $date === '' && $term ) {
					$date = WBTM_Bus_Events::event_date( (int) $term->term_id );
				}
				$ids = WBTM_Bus_Scope::resolve( array(
					'bus_ids'     => $atts['bus_ids'] ?? '',
					'event'       => $atts['event'] ?? '',
					'cat'         => $atts['cat'] ?? '',
					'destination' => $destination,
					'date'        => $date,
				), $date_filters_buses );
				return array( $term, $destination, $date, $ids );
			}
			/**
			 * Scope a [wbtm-bus-search] by its event / bus_ids / destination / date
			 * attributes. Returns false when none was given (render unchanged).
			 *
			 * @return array{0: int[], 1: string, 2: array}|false|string [ids, date, presets],
			 *         false = unscoped, string = error message.
			 */
			public static function search_scope( array $params ) {
				$given = false;
				foreach ( array( 'event', 'bus_ids', 'destination', 'date' ) as $key ) {
					if ( trim( (string) ( $params[ $key ] ?? '' ) ) !== '' ) {
						$given = true;
					}
				}
				if ( ! $given ) {
					return false;
				}
				// cat is deliberately not passed: [wbtm-bus-search] has always accepted
				// and ignored it, and starting to honour it would change existing pages.
				$context = self::context( array(
					'event'       => $params['event'] ?? '',
					'bus_ids'     => $params['bus_ids'] ?? '',
					'destination' => $params['destination'] ?? '',
					'date'        => $params['date'] ?? '',
				) );
				if ( is_string( $context ) ) {
					return $context;
				}
				list( , $destination, $date, $ids ) = $context;
				return array(
					$ids === null ? array() : ( $ids ? $ids : array( 0 ) ),
					$date,
					array(
						'from'        => '',
						'to'          => $destination,
						'lock_to'     => false,
						'auto_search' => false,
					),
				);
			}
			/** Error/hint markup for editors, nothing for visitors. */
			public static function editor_notice( string $message ): string {
				return self::notice( $message );
			}
			//==========================// [wbtm-bus-booking]
			public function bus_booking( $atts ): string {
				WBTM_Global_File_Load::ensure_frontend_assets();
				$atts   = shortcode_atts( array(
					'id'          => '',
					'details'     => 'yes',
					'from'        => '',
					'to'          => '',
					'date'        => '',
					'auto_search' => '',
					'style'       => '',
					'left_filter' => '',
				), $atts, 'wbtm-bus-booking' );
				$bus_id = absint( $atts['id'] );
				if ( ! $bus_id || ! in_array( $bus_id, WBTM_Bus_Scope::published_bus_ids( array( $bus_id ) ), true ) ) {
					return self::notice( __( 'Bus booking: set id="" to the ID of a published bus.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				$presets = array(
					'from'        => sanitize_text_field( $atts['from'] ),
					'to'          => sanitize_text_field( $atts['to'] ),
					'lock_to'     => false,
					'auto_search' => self::flag( $atts['auto_search'], self::setting_on( 'event_auto_search' ) ),
				);
				$post_id = $bus_id;
				ob_start();
				?>
				<div class="wbtm_style wbtm_container wbtm_single_modern wbtm_bus_booking_embed">
					<?php
					if ( self::flag( $atts['details'], true ) ) {
						require WBTM_Functions::template_path( 'layout/single_bus_details.php' );
					}
					self::render_search_form( $post_id, self::form_params( $atts ), array(), WBTM_Bus_Scope::sanitize_date( $atts['date'] ), $presets );
					?>
				</div>
				<?php
				return (string) ob_get_clean();
			}
			//==========================// [wbtm-bus-event]
			public function bus_event( $atts ): string {
				WBTM_Global_File_Load::ensure_frontend_assets();
				$atts = shortcode_atts( array(
					'event'            => '',
					'bus_ids'          => '',
					'cat'              => '',
					'destination'      => '',
					'from'             => '',
					'date'             => '',
					'title'            => '',
					'header'           => '',
					'overview'         => '',
					'lock_destination' => '',
					'auto_search'      => '',
					'style'            => '',
					'left_filter'      => '',
				), $atts, 'wbtm-bus-event' );
				$context = self::context( $atts );
				if ( is_string( $context ) ) {
					return self::notice( $context );
				}
				list( $term, $destination, $date, $ids ) = $context;
				if ( $ids === null ) {
					return self::notice( __( 'Bus event: add event="", bus_ids="", cat="" or destination="" to choose which buses to show.', 'bus-ticket-booking-with-seat-reservation' ) );
				}
				$title = sanitize_text_field( $atts['title'] );
				if ( $title === '' && $term ) {
					$title = $term->name;
				}
				$presets = array(
					'from'        => sanitize_text_field( $atts['from'] ),
					'to'          => $destination,
					'lock_to'     => $destination !== '' && self::flag( $atts['lock_destination'], self::setting_on( 'event_lock_destination' ) ),
					'auto_search' => self::flag( $atts['auto_search'], self::setting_on( 'event_auto_search' ) ),
				);
				ob_start();
				?>
				<div class="wbtm_event_booking">
					<?php if ( self::flag( $atts['header'], self::setting_on( 'event_header' ) ) && ( $title !== '' || $date || $destination !== '' ) ) { ?>
						<div class="wbtm_style wbtm_container wbtm_event_header">
							<?php if ( $title !== '' ) { ?>
								<h2 class="wbtm_event_title"><?php echo esc_html( $title ); ?></h2>
							<?php } ?>
							<div class="wbtm_event_meta">
								<?php if ( $date ) { ?>
									<span><i class="fas fa-calendar-alt" aria-hidden="true"></i> <?php echo esc_html( WBTM_Global_Function::date_format( $date ) ); ?></span>
								<?php } ?>
								<?php if ( $destination !== '' ) { ?>
									<span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?php echo esc_html( $destination ); ?></span>
								<?php } ?>
							</div>
							<?php if ( $term && $term->description !== '' ) { ?>
								<div class="wbtm_event_description"><?php echo wp_kses_post( wpautop( $term->description ) ); ?></div>
							<?php } ?>
						</div>
					<?php } ?>
					<?php if ( ! $ids ) { ?>
						<div class="wbtm_style wbtm_container">
							<div class="wbtm_event_empty"><?php esc_html_e( 'No buses are available for this event yet.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
						</div>
					<?php } else { ?>
						<?php
						if ( self::flag( $atts['overview'], self::setting_on( 'event_overview' ) ) ) {
							self::render_overview( $ids, $destination, $date );
						}
						?>
						<div class="wbtm_style wbtm_container wbtm_event_form">
							<?php self::render_search_form( 0, self::form_params( $atts ), $ids, $date, $presets ); ?>
						</div>
					<?php } ?>
				</div>
				<?php
				return (string) ob_get_clean();
			}
			/** " (+1)" when the arrival falls on a later calendar day than the departure. */
			private static function day_offset( string $from, string $to ): string {
				$days = (int) round( ( strtotime( gmdate( 'Y-m-d', strtotime( $to ) ) ) - strtotime( gmdate( 'Y-m-d', strtotime( $from ) ) ) ) / DAY_IN_SECONDS );
				return $days > 0 ? ' (+' . $days . ')' : '';
			}
			/**
			 * Departure overview: one card per bus listing the stops a customer can
			 * board at. With a destination and date, each stop shows the real
			 * departure/arrival time, fare and seats left for that day (the same
			 * get_bus_all_info() the search results use); otherwise the timetable.
			 *
			 * @param int[] $ids
			 */
			private static function render_overview( array $ids, string $destination, string $date ): void {
				$dest_key       = $destination !== '' ? WBTM_Bus_Scope::normalize_place( $destination ) : '';
				$unpriced_hide  = WBTM_Global_Function::get_settings( 'wbtm_frontend_display_settings', 'unpriced_route_action', 'message' ) === 'hide';
				$cards          = array();
				foreach ( $ids as $bus_id ) {
					$route_infos = WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_route_info', array() );
					if ( ! is_array( $route_infos ) || ! $route_infos ) {
						continue;
					}
					$rows       = array();
					$seen       = array();
					$dest_found = false;
					foreach ( $route_infos as $info ) {
						$place = isset( $info['place'] ) ? (string) $info['place'] : '';
						$type  = $info['type'] ?? '';
						if ( $place === '' ) {
							continue;
						}
						$key = WBTM_Bus_Scope::normalize_place( $place );
						if ( $dest_key !== '' && $key === $dest_key ) {
							$dest_found = true;
							if ( ! $date ) {
								break; // Timetable mode: only stops before the destination.
							}
							continue;
						}
						if ( isset( $seen[ $key ] ) || ! in_array( $type, array( 'bp', 'both' ), true ) ) {
							continue;
						}
						$seen[ $key ] = true;
						if ( $dest_key !== '' && $date ) {
							// Live mode: also covers same-bus-return legs, so stops after the
							// destination on the stored route are tried too.
							$all_info = WBTM_Functions::get_bus_all_info( $bus_id, $date, $place, $destination );
							if ( ! $all_info ) {
								continue;
							}
							$priced = $all_info['price'] !== false;
							if ( ! $priced && $unpriced_hide ) {
								continue;
							}
							$rows[] = array(
								'place'     => $place,
								'depart'    => WBTM_Global_Function::date_format( $all_info['bp_time'], 'time' ),
								'arrive'    => WBTM_Global_Function::date_format( $all_info['dp_time'], 'time' ) . self::day_offset( $all_info['bp_time'], $all_info['dp_time'] ),
								'price'     => $priced ? $all_info['price'] : null,
								'seats'     => (int) $all_info['available_seat'],
								'bookable'  => $priced && (int) $all_info['available_seat'] > 0 && ( $all_info['regi_status'] ?? 'yes' ) !== 'no',
							);
						} else {
							$rows[] = array(
								'place'    => $place,
								'depart'   => ! empty( $info['time'] ) ? WBTM_Global_Function::date_format( $info['time'], 'time' ) : '',
								'arrive'   => '',
								'price'    => null,
								'seats'    => null,
								'bookable' => true,
							);
						}
					}
					// Timetable mode with a destination: the bus must actually reach it.
					if ( $dest_key !== '' && ! $date && ! $dest_found ) {
						continue;
					}
					if ( $rows ) {
						$cards[] = array( 'bus_id' => $bus_id, 'rows' => $rows );
					}
				}
				$live = $dest_key !== '' && $date;
				?>
				<div class="wbtm_style wbtm_container wbtm_event_overview">
					<h3 class="wbtm_event_overview_title"><?php esc_html_e( 'Available departures', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
					<?php if ( ! $cards ) { ?>
						<div class="wbtm_event_empty"><?php esc_html_e( 'No departures are open for booking right now.', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
					<?php } ?>
					<div class="wbtm_event_cards">
						<?php foreach ( $cards as $card ) {
							$bus_id = $card['bus_id'];
							$coach  = WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_bus_category' );
							$bus_no = WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_bus_no' );
							?>
							<div class="wbtm_event_card">
								<div class="wbtm_event_card_head">
									<strong class="wbtm_event_bus_name"><?php echo esc_html( get_the_title( $bus_id ) ); ?></strong>
									<span class="wbtm_event_bus_meta">
										<?php echo esc_html( implode( ' · ', array_filter( array( $coach, $bus_no ) ) ) ); ?>
									</span>
								</div>
								<table class="wbtm_event_table">
									<thead>
									<tr>
										<th><?php echo esc_html( WBTM_Translations::text_bp() ); ?></th>
										<th><?php esc_html_e( 'Departs', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
										<?php if ( $live ) { ?>
											<th><?php esc_html_e( 'Arrives', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
											<th><?php esc_html_e( 'Price', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
											<th><?php esc_html_e( 'Seats left', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
										<?php } ?>
										<th><span class="screen-reader-text"><?php esc_html_e( 'Book', 'bus-ticket-booking-with-seat-reservation' ); ?></span></th>
									</tr>
									</thead>
									<tbody>
									<?php foreach ( $card['rows'] as $row ) { ?>
										<tr>
											<td data-label="<?php echo esc_attr( WBTM_Translations::text_bp() ); ?>"><?php echo esc_html( $row['place'] ); ?></td>
											<td data-label="<?php esc_attr_e( 'Departs', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_html( $row['depart'] ); ?></td>
											<?php if ( $live ) { ?>
												<td data-label="<?php esc_attr_e( 'Arrives', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_html( $row['arrive'] ); ?></td>
												<td data-label="<?php esc_attr_e( 'Price', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<?php
													if ( $row['price'] === null ) {
														esc_html_e( 'Not available', 'bus-ticket-booking-with-seat-reservation' );
													} else {
														echo wp_kses_post( WBTM_Global_Function::format_price( $row['price'] ) );
													}
													?>
												</td>
												<td data-label="<?php esc_attr_e( 'Seats left', 'bus-ticket-booking-with-seat-reservation' ); ?>">
													<?php echo $row['seats'] > 0 ? esc_html( (string) $row['seats'] ) : esc_html__( 'Sold out', 'bus-ticket-booking-with-seat-reservation' ); ?>
												</td>
											<?php } ?>
											<td class="wbtm_event_action">
												<?php if ( $row['bookable'] ) { ?>
													<button type="button" class="_themeButton_radius wbtm_event_book_btn" data-from="<?php echo esc_attr( $row['place'] ); ?>"><?php esc_html_e( 'Book', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
												<?php } ?>
											</td>
										</tr>
									<?php } ?>
									</tbody>
								</table>
							</div>
						<?php } ?>
					</div>
				</div>
				<?php
			}
			//==========================// settings tab
			public function settings_section( $sections ): array {
				$sections   = is_array( $sections ) ? $sections : array();
				$sections[] = array(
					'id'    => self::SETTINGS,
					'title' => esc_html__( 'Event Booking Pages', 'bus-ticket-booking-with-seat-reservation' ),
				);
				return $sections;
			}
			public function settings_fields( $fields ): array {
				$fields   = is_array( $fields ) ? $fields : array();
				$toggle   = static function ( $value ) {
					return $value === 'hide' ? 'hide' : 'show';
				};
				$fields[ self::SETTINGS ] = apply_filters( 'wbtm_filter_event_settings', array(
					array(
						'name'              => 'event_label',
						'label'             => esc_html__( 'Event label (singular)', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'What you call an event in the admin, e.g. Concert or Festival.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'text',
						'default'           => '',
						'placeholder'       => __( 'Event', 'bus-ticket-booking-with-seat-reservation' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					array(
						'name'              => 'event_label_plural',
						'label'             => esc_html__( 'Event label (plural)', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'Used for the admin menu and the bus editor, e.g. Concerts.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'text',
						'default'           => '',
						'placeholder'       => __( 'Events', 'bus-ticket-booking-with-seat-reservation' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					array(
						'name'              => 'event_header',
						'label'             => esc_html__( 'Event header', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'Show the event name, date, destination and description above the booking area.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'toggle',
						'icon'              => 'fas fa-heading',
						'default'           => 'show',
						'sanitize_callback' => $toggle,
					),
					array(
						'name'              => 'event_overview',
						'label'             => esc_html__( 'Departure overview', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'List every bus with its boarding stops, times, fares and seats left, each with a Book button.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'toggle',
						'icon'              => 'fas fa-list',
						'default'           => 'show',
						'sanitize_callback' => $toggle,
					),
					array(
						'name'              => 'event_lock_destination',
						'label'             => esc_html__( 'Lock destination', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'When the event has a destination, fix the "To" field to it so customers only choose where they board.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'toggle',
						'icon'              => 'fas fa-lock',
						'default'           => 'show',
						'sanitize_callback' => $toggle,
					),
					array(
						'name'              => 'event_auto_search',
						'label'             => esc_html__( 'Search automatically', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'              => esc_html__( 'Show matching buses as soon as From, To and the date are known, without pressing Search.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'              => 'toggle',
						'icon'              => 'fas fa-bolt',
						'default'           => 'show',
						'sanitize_callback' => $toggle,
					),
					array(
						'name'  => 'event_shortcode_reference',
						'label' => esc_html__( 'Shortcode reference', 'bus-ticket-booking-with-seat-reservation' ),
						'desc'  => esc_html__( 'Copy-ready examples of the booking shortcodes. Nothing here is saved.', 'bus-ticket-booking-with-seat-reservation' ),
						'type'  => 'html',
						'html' => self::reference_html(),
					),
				) );
				return $fields;
			}
			private static function reference_html(): string {
				$rows = array(
					array( '[wbtm-bus-event event="milan-concert"]', __( 'All buses of an event with a departure overview and one booking form. Takes the destination and date from the event.', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( '[wbtm-bus-event destination="Milan" date="2026-11-14"]', __( 'All buses that serve a stop, pinned to one travel date.', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( '[wbtm-bus-event bus_ids="12,15,18" destination="Milan"]', __( 'A hand-picked list of buses.', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( '[wbtm-bus-booking id="12"]', __( 'One bus: details and booking form. Optional from="", to="", date="", details="no".', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( '[wbtm-bus-search event="milan-concert"]', __( 'The regular search form, limited with event, bus_ids, destination or date.', 'bus-ticket-booking-with-seat-reservation' ) ),
					array( '[wbtm-bus-list event="milan-concert" date="2026-11-14"]', __( 'The bus grid, limited with event, bus_ids, destination or date.', 'bus-ticket-booking-with-seat-reservation' ) ),
				);
				$html  = '<div class="bm-gs__field-row wbtm-field-event_shortcode_reference"><div style="width:100%">';
				$html .= '<p>' . esc_html__( 'Paste a shortcode into any page, or use the "Bus Booking" and "Bus Event Booking" widgets in Elementor. Attributes for [wbtm-bus-event]: event, bus_ids, cat, destination, from, date (YYYY-MM-DD), title, header, overview, lock_destination, auto_search (yes/no), style (flix).', 'bus-ticket-booking-with-seat-reservation' ) . '</p>';
				$html .= '<table class="widefat striped"><tbody>';
				foreach ( $rows as $row ) {
					$html .= '<tr><td><code>' . esc_html( $row[0] ) . '</code></td><td>' . esc_html( $row[1] ) . '</td></tr>';
				}
				$html .= '</tbody></table></div></div>';
				return $html;
			}
		}
		new WBTM_Event_Shortcodes();
	}
