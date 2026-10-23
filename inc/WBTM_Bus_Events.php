<?php
	/*
	 * @Author 		MagePeople Team
	 * Copyright: 	mage-people.com
	 *
	 * Events: groups buses that serve one concert, festival or other event, so a
	 * single page can show and sell all of them with [wbtm-bus-event event="..."].
	 *
	 * Stored as the non-public taxonomy `wbtm_bus_event` on `wbtm_bus`. Each event
	 * term may carry a default destination stop and an event (travel) date as term
	 * meta; shortcode attributes override both.
	 *
	 * Buses are assigned from the General tab of the bus editor (both the classic
	 * and the modern editor render that tab), because the modern editor hides the
	 * WordPress sidebar where a taxonomy box would normally appear. The native box
	 * is disabled for the same reason, so only one control ever writes the terms.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Bus_Events' ) ) {
		class WBTM_Bus_Events {
			const TAXONOMY        = 'wbtm_bus_event';
			const META_DATE       = 'wbtm_event_date';
			const META_DESTINATION = 'wbtm_event_destination';
			const SETTINGS        = 'wbtm_event_settings';
			public function __construct() {
				add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
				add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'add_term_fields' ) );
				add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'edit_term_fields' ) );
				add_action( 'created_' . self::TAXONOMY, array( $this, 'save_term_fields' ) );
				add_action( 'edited_' . self::TAXONOMY, array( $this, 'save_term_fields' ) );
				add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( $this, 'term_columns' ) );
				add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( $this, 'term_column_content' ), 10, 3 );
				add_action( 'wbtm_general_settings_fields', array( $this, 'bus_editor_field' ), 20 );
				add_action( 'wbtm_settings_save', array( $this, 'save_bus_events' ) );
			}
			/** Singular label admins chose for events (e.g. "Concert"). */
			public static function label(): string {
				$label = trim( (string) WBTM_Global_Function::get_settings( self::SETTINGS, 'event_label', '' ) );
				return $label !== '' ? $label : __( 'Event', 'bus-ticket-booking-with-seat-reservation' );
			}
			/** Plural label admins chose for events (e.g. "Concerts"). */
			public static function label_plural(): string {
				$label = trim( (string) WBTM_Global_Function::get_settings( self::SETTINGS, 'event_label_plural', '' ) );
				return $label !== '' ? $label : __( 'Events', 'bus-ticket-booking-with-seat-reservation' );
			}
			public function register_taxonomy(): void {
				$label  = self::label();
				$plural = self::label_plural();
				register_taxonomy( self::TAXONOMY, WBTM_Functions::get_cpt(), array(
					'labels'             => array(
						'name'          => $plural,
						'singular_name' => $label,
						'menu_name'     => $plural,
						/* translators: %s: plural event label, e.g. "Events" */
						'all_items'     => sprintf( __( 'All %s', 'bus-ticket-booking-with-seat-reservation' ), $plural ),
						/* translators: %s: event label */
						'add_new_item'  => sprintf( __( 'Add New %s', 'bus-ticket-booking-with-seat-reservation' ), $label ),
						/* translators: %s: event label */
						'edit_item'     => sprintf( __( 'Edit %s', 'bus-ticket-booking-with-seat-reservation' ), $label ),
						/* translators: %s: plural event label */
						'search_items'  => sprintf( __( 'Search %s', 'bus-ticket-booking-with-seat-reservation' ), $plural ),
						/* translators: %s: plural event label */
						'not_found'     => sprintf( __( 'No %s found', 'bus-ticket-booking-with-seat-reservation' ), $plural ),
					),
					'hierarchical'       => true,
					'public'             => false,
					'publicly_queryable' => false,
					'show_ui'            => true,
					'show_in_menu'       => true,
					'show_admin_column'  => true,
					'show_in_nav_menus'  => false,
					'show_tagcloud'      => false,
					'show_in_rest'       => true,
					'rest_base'          => 'bus_event',
					'query_var'          => false,
					'rewrite'            => false,
					'meta_box_cb'        => false,
				) );
			}
			/**
			 * Look up an event by slug, ID or exact name.
			 *
			 * @param string|int $event
			 */
			public static function get_event( $event ): ?WP_Term {
				$event = is_string( $event ) ? trim( $event ) : $event;
				if ( $event === '' || $event === 0 ) {
					return null;
				}
				$term = false;
				if ( is_numeric( $event ) ) {
					$term = get_term_by( 'id', (int) $event, self::TAXONOMY );
				}
				if ( ! $term ) {
					$term = get_term_by( 'slug', sanitize_title( (string) $event ), self::TAXONOMY );
				}
				if ( ! $term ) {
					$term = get_term_by( 'name', (string) $event, self::TAXONOMY );
				}
				return $term instanceof WP_Term ? $term : null;
			}
			public static function event_date( int $term_id ): string {
				return WBTM_Bus_Scope::sanitize_date( (string) get_term_meta( $term_id, self::META_DATE, true ) );
			}
			public static function event_destination( int $term_id ): string {
				return trim( (string) get_term_meta( $term_id, self::META_DESTINATION, true ) );
			}
			/** @return string[] every stop name used by any bus, for the destination picker. */
			private static function all_stops(): array {
				return WBTM_Global_Function::get_all_term_data( 'wbtm_bus_stops' );
			}
			//==========================// term screen
			public function add_term_fields(): void {
				wp_nonce_field( 'wbtm_event_term', 'wbtm_event_term_nonce' );
				?>
				<div class="form-field">
					<label for="wbtm_event_date"><?php esc_html_e( 'Event date', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
					<input type="date" name="<?php echo esc_attr( self::META_DATE ); ?>" id="wbtm_event_date" value=""/>
					<p><?php esc_html_e( 'Outbound travel date. Event pages only offer departures on this date. Leave empty to allow every date.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				</div>
				<div class="form-field">
					<label for="wbtm_event_destination"><?php esc_html_e( 'Destination stop', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
					<?php $this->destination_input( '' ); ?>
					<p><?php esc_html_e( 'The stop nearest the venue. Event pages pre-fill it as "To", so customers only choose where they board.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
				</div>
				<?php
			}
			public function edit_term_fields( $term ): void {
				wp_nonce_field( 'wbtm_event_term', 'wbtm_event_term_nonce' );
				$term_id = (int) $term->term_id;
				?>
				<tr class="form-field">
					<th scope="row"><label for="wbtm_event_date"><?php esc_html_e( 'Event date', 'bus-ticket-booking-with-seat-reservation' ); ?></label></th>
					<td>
						<input type="date" name="<?php echo esc_attr( self::META_DATE ); ?>" id="wbtm_event_date" value="<?php echo esc_attr( self::event_date( $term_id ) ); ?>"/>
						<p class="description"><?php esc_html_e( 'Outbound travel date. Event pages only offer departures on this date. Leave empty to allow every date.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row"><label for="wbtm_event_destination"><?php esc_html_e( 'Destination stop', 'bus-ticket-booking-with-seat-reservation' ); ?></label></th>
					<td>
						<?php $this->destination_input( self::event_destination( $term_id ) ); ?>
						<p class="description"><?php esc_html_e( 'The stop nearest the venue. Event pages pre-fill it as "To", so customers only choose where they board.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row"><?php esc_html_e( 'Shortcode', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
					<td><code>[wbtm-bus-event event="<?php echo esc_html( $term->slug ); ?>"]</code></td>
				</tr>
				<?php
			}
			private function destination_input( string $value ): void {
				?>
				<input type="text" name="<?php echo esc_attr( self::META_DESTINATION ); ?>" id="wbtm_event_destination" value="<?php echo esc_attr( $value ); ?>" list="wbtm_event_stop_list" autocomplete="off"/>
				<datalist id="wbtm_event_stop_list">
					<?php foreach ( self::all_stops() as $stop ) { ?>
						<option value="<?php echo esc_attr( $stop ); ?>"></option>
					<?php } ?>
				</datalist>
				<?php
			}
			public function save_term_fields( $term_id ): void {
				if ( ! isset( $_POST['wbtm_event_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_event_term_nonce'] ) ), 'wbtm_event_term' ) ) {
					return;
				}
				$taxonomy = get_taxonomy( self::TAXONOMY );
				if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
					return;
				}
				$date        = isset( $_POST[ self::META_DATE ] ) ? WBTM_Bus_Scope::sanitize_date( sanitize_text_field( wp_unslash( $_POST[ self::META_DATE ] ) ) ) : '';
				$destination = isset( $_POST[ self::META_DESTINATION ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_DESTINATION ] ) ) : '';
				update_term_meta( $term_id, self::META_DATE, $date );
				update_term_meta( $term_id, self::META_DESTINATION, $destination );
			}
			public function term_columns( $columns ) {
				$slug_pos = array_search( 'slug', array_keys( $columns ), true );
				$extra    = array(
					'wbtm_event_date'        => __( 'Event date', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_event_destination' => __( 'Destination', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_event_shortcode'   => __( 'Shortcode', 'bus-ticket-booking-with-seat-reservation' ),
				);
				if ( $slug_pos === false ) {
					return array_merge( $columns, $extra );
				}
				return array_slice( $columns, 0, $slug_pos + 1, true ) + $extra + array_slice( $columns, $slug_pos + 1, null, true );
			}
			public function term_column_content( $content, $column, $term_id ) {
				$term_id = (int) $term_id;
				if ( $column === 'wbtm_event_date' ) {
					$date = self::event_date( $term_id );
					return $date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ) : '&mdash;';
				}
				if ( $column === 'wbtm_event_destination' ) {
					$destination = self::event_destination( $term_id );
					return $destination !== '' ? esc_html( $destination ) : '&mdash;';
				}
				if ( $column === 'wbtm_event_shortcode' ) {
					$term = get_term( $term_id, self::TAXONOMY );
					return $term instanceof WP_Term ? '<code>[wbtm-bus-event event="' . esc_html( $term->slug ) . '"]</code>' : '';
				}
				return $content;
			}
			//==========================// bus editor (General tab)
			public function bus_editor_field( $post_id ): void {
				$post_id  = (int) $post_id;
				$events   = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ) );
				$events   = is_wp_error( $events ) ? array() : $events;
				$assigned = $post_id ? wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) ) : array();
				$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );
				$label    = self::label();
				$manage   = admin_url( 'edit-tags.php?taxonomy=' . self::TAXONOMY . '&post_type=' . WBTM_Functions::get_cpt() );
				?>
				<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter wbtm_bus_events_field">
					<div class="col_6 _dFlex_fdColumn">
						<label>
							<?php echo esc_html( self::label_plural() ); ?>
						</label>
						<span>
							<?php
							/* translators: %s: event label, e.g. "event" */
							echo esc_html( sprintf( __( 'Show this bus on the booking page of each selected %s.', 'bus-ticket-booking-with-seat-reservation' ), strtolower( $label ) ) );
							?>
							<a href="<?php echo esc_url( $manage ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage', 'bus-ticket-booking-with-seat-reservation' ); ?></a>
						</span>
					</div>
					<div class="col_6 textRight">
						<input type="hidden" name="wbtm_bus_events_present" value="1"/>
						<?php if ( $events ) { ?>
							<div class="wbtm_bus_events_list" style="display:inline-flex;flex-direction:column;align-items:flex-start;gap:4px;max-height:180px;overflow:auto;text-align:left;">
								<?php foreach ( $events as $event ) { ?>
									<label style="display:flex;align-items:center;gap:6px;">
										<input type="checkbox" name="wbtm_bus_events[]" value="<?php echo esc_attr( $event->term_id ); ?>" <?php checked( in_array( (int) $event->term_id, $assigned, true ) ); ?>/>
										<span><?php echo esc_html( $event->name ); ?></span>
									</label>
								<?php } ?>
							</div>
						<?php } ?>
						<input class="formControl max_300" type="text" name="wbtm_bus_event_new" value="" placeholder="<?php
							/* translators: %s: event label */
							echo esc_attr( sprintf( __( 'New %s name (optional)', 'bus-ticket-booking-with-seat-reservation' ), strtolower( $label ) ) );
						?>" style="margin-top:6px;"/>
					</div>
				</div>
				<?php
			}
			/**
			 * Hooked on wbtm_settings_save, which only fires after the bus editor's
			 * own nonce, autosave and capability checks have passed.
			 */
			public function save_bus_events( $post_id ): void {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WBTM_Settings::save_settings before this action fires.
				if ( empty( $_POST['wbtm_bus_events_present'] ) || get_post_type( $post_id ) !== WBTM_Functions::get_cpt() ) {
					return;
				}
				$ids      = isset( $_POST['wbtm_bus_events'] ) && is_array( $_POST['wbtm_bus_events'] ) ? array_map( 'absint', wp_unslash( $_POST['wbtm_bus_events'] ) ) : array();
				$new_name = isset( $_POST['wbtm_bus_event_new'] ) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bus_event_new'] ) ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				if ( $new_name !== '' ) {
					$taxonomy = get_taxonomy( self::TAXONOMY );
					if ( $taxonomy && current_user_can( $taxonomy->cap->edit_terms ) ) {
						$existing = term_exists( $new_name, self::TAXONOMY );
						$created  = $existing ? $existing : wp_insert_term( $new_name, self::TAXONOMY );
						if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
							$ids[] = (int) $created['term_id'];
						}
					}
				}
				// Integer IDs only: wp_set_object_terms() treats strings as names/slugs
				// and silently skips IDs that do not exist in the taxonomy.
				wp_set_object_terms( $post_id, array_values( array_unique( array_filter( $ids ) ) ), self::TAXONOMY );
			}
		}
		new WBTM_Bus_Events();
	}
