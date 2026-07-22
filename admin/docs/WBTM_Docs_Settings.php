<?php
	/*
   * @Author 		MagePeople Team
   * Copyright: 	mage-people.com
   *
   * Settings Reference for the Documents screen.
   *
   * The field list is read live from the same filters the settings screen
   * itself uses ('wbtm_settings_sec_reg' / 'wbtm_settings_sec_fields'), so a
   * setting can never go undocumented: adding a field anywhere — free plugin,
   * PRO addon or a third-party addon — makes it appear here automatically,
   * with its own label, description, type and default.
   *
   * Where the plugin's own inline description is terse or missing, a longer
   * plain-English explanation from notes() is used instead.
   *
   * Nothing here ever reads a saved option value. The reference documents what
   * a field does, not what it is currently set to, which also means secrets
   * such as chatbot API keys are never rendered.
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.

	if ( ! class_exists( 'WBTM_Docs_Settings' ) ) {
		class WBTM_Docs_Settings {

			/**
			 * Which plugin owns each settings section, and how to describe it.
			 *
			 * Sections not listed here still render — they are simply treated as
			 * free and described with their own title — so an unknown addon
			 * section is never dropped.
			 *
			 * @return array<string,array{plan:string,tab:string,intro:string}>
			 */
			public static function section_meta(): array {
				return array(
					'wbtm_general_settings'          => array(
						'plan'  => 'free',
						'tab'   => __( 'Bus Settings', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Core booking behaviour: when a seat counts as sold, how the menu is labelled, how far ahead tickets may be bought, and where customers are sent after booking.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_global_settings'           => array(
						'plan'  => 'free',
						'tab'   => __( 'General', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Site-wide behaviour that is not specific to one bus — seat holds, the editor used for buses, and date formats.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_payment_settings'          => array(
						'plan'  => 'free',
						'tab'   => __( 'Payments', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Which booking engine processes a sale, which gateways are offered, and which payment statuses confirm a booking.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_frontend_display_settings' => array(
						'plan'  => 'free',
						'tab'   => __( 'Frontend Display', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Show or hide each filter beside the search results, and decide what happens when a route has no price.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_slider_settings'           => array(
						'plan'  => 'free',
						'tab'   => __( 'Slider Settings', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'The image slider shown on bus pages — its type, style and indicators.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_style_settings'            => array(
						'plan'  => 'free',
						'tab'   => __( 'Style Settings', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Colours and typography for every frontend booking screen, so the plugin matches your theme.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_promo_settings'            => array(
						'plan'  => 'free',
						'tab'   => __( 'Promo Banner', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'An optional promotional banner in the search results sidebar.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_license_settings'          => array(
						'plan'  => 'free',
						'tab'   => __( 'License', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'License keys for MagePeople premium addons. A key is what enables automatic updates; the plugin keeps working without one.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_deposit_settings'          => array(
						'plan'  => 'pro',
						'tab'   => __( 'Deposit / Partial Pay', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Global defaults for partial payment. Individual buses can override any of these from their own Deposit tab.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_pdf_settings'              => array(
						'plan'  => 'pro',
						'tab'   => __( 'PDF Settings', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Branding and layout of the customer ticket, in both full-page PDF and thermal receipt formats.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_passenger_pdf_settings'    => array(
						'plan'  => 'pro',
						'tab'   => __( 'Export Columns — PDF', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Which columns appear in the PDF passenger manifest. Each toggle adds or removes one column.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_passenger_csv_settings'    => array(
						'plan'  => 'pro',
						'tab'   => __( 'Export Columns — CSV', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Which columns appear in the CSV passenger export. Kept separate from the PDF list because the two generators read them independently.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_email_settings'            => array(
						'plan'  => 'pro',
						'tab'   => __( 'Email Settings', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'Ticket emails, admin alerts and passenger trip reminders.', 'bus-ticket-booking-with-seat-reservation' ),
					),
					'wbtm_ai_chatbot_settings'       => array(
						'plan'  => 'pro',
						'tab'   => __( 'AI Chatbot', 'bus-ticket-booking-with-seat-reservation' ),
						'intro' => __( 'The conversational booking assistant: which provider and model it uses, and how the widget looks.', 'bus-ticket-booking-with-seat-reservation' ),
					),
				);
			}

			/**
			 * Longer explanations for fields whose built-in description is terse,
			 * missing, or purely internal. Keyed by field name.
			 *
			 * Anything not listed here falls back to the field's own 'desc'.
			 *
			 * @return array<string,string>
			 */
			public static function notes(): array {
				return array(
					/* --- Bus Settings ------------------------------------------- */
					'set_book_status'                 => __( 'Decides at which order statuses a seat stops being sellable. Include a status too late in the flow and the same seat can be sold twice; too early and abandoned carts block real customers.', 'bus-ticket-booking-with-seat-reservation' ),
					'label'                           => __( 'Renames the plugin throughout the dashboard — useful if you run coaches, shuttles or trains rather than buses.', 'bus-ticket-booking-with-seat-reservation' ),
					'slug'                            => __( 'The URL segment used for bus pages. After changing it, visit Settings → Permalinks and click Save Changes, or bus links will return 404.', 'bus-ticket-booking-with-seat-reservation' ),
					'icon'                            => __( 'The dashboard menu icon. Accepts a FontAwesome or Dashicons class, or an uploaded image.', 'bus-ticket-booking-with-seat-reservation' ),
					'bus_return_show'                 => __( 'Shows or hides the return-date field in the search form. Turn it off if you only sell one-way tickets.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_close_date'          => __( 'A hard stop date after which no ticket can be sold at all. Leave blank to keep selling indefinitely.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_max_date'            => __( 'How many days into the future customers may book. Limits how far the calendar opens.', 'bus-ticket-booking-with-seat-reservation' ),
					'bus_buffer_time'                 => __( 'Hides departures that are closer than this many hours away, giving your counter staff time to finalise the manifest.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_cutoff_enable'       => __( 'Stops ticket sales a set time before each departure, so you can plan routes and dispatch on schedule. Disabled by default, which leaves existing sites unaffected.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_cutoff_type'         => __( 'Choose between a rolling cut-off (a fixed number of hours before departure) and a clock cut-off (a specific time on a day before departure).', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_cutoff_hours'        => __( 'For the rolling style: how many hours before departure sales close. For example 12 closes a 10:00 departure at 22:00 the night before.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_cutoff_days_before'  => __( 'For the clock style: how many days before departure the cut-off time falls. 1 means the day before.', 'bus-ticket-booking-with-seat-reservation' ),
					'ticket_sale_cutoff_clock'        => __( 'For the clock style: the time of day, in 24-hour HH:MM format, at which sales close.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_hide_view_seats_button'     => __( 'Shows or hides the seat-preview button on the search results card.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_hide_bus_details_tabs'      => __( 'Shows or hides the details, boarding points, features and gallery tabs on the results card.', 'bus-ticket-booking-with-seat-reservation' ),
					'active_redirect_page'            => __( 'Whether booking sends the customer straight on to another page, and which one.', 'bus-ticket-booking-with-seat-reservation' ),
					'search_page_redirect'            => __( 'The page holding your search shortcode. Used whenever the plugin needs to send a customer back to search results.', 'bus-ticket-booking-with-seat-reservation' ),
					'make_processing_completed'       => __( 'Automatically moves processing orders to completed. Handy when there is no fulfilment step between payment and travel.', 'bus-ticket-booking-with-seat-reservation' ),
					'auto_complete_paid_orders'       => __( 'Completes an order as soon as payment succeeds, so the ticket email goes out immediately.', 'bus-ticket-booking-with-seat-reservation' ),
					'checkout_redirect_after_booking' => __( 'Sends the customer straight to checkout after selecting seats, instead of leaving them on the cart page.', 'bus-ticket-booking-with-seat-reservation' ),
					'cart_empty_after_search'         => __( 'Clears the cart whenever a new search is run. Prevents customers accidentally paying for a previous route they abandoned.', 'bus-ticket-booking-with-seat-reservation' ),
					'calendar_soldout_highlight'      => __( 'Marks fully-booked dates in the date picker so customers cannot select them.', 'bus-ticket-booking-with-seat-reservation' ),
					'bus_search_list_direction_icon'  => __( 'The icon shown between the origin and destination on search result cards.', 'bus-ticket-booking-with-seat-reservation' ),
					'next_date_showing_search'        => __( 'When nothing runs on the chosen date, offers the next available departure instead of an empty result page.', 'bus-ticket-booking-with-seat-reservation' ),
					'new_bus_list_design'             => __( 'Switches the admin bus list between the modern card/table screen and the classic WordPress list table.', 'bus-ticket-booking-with-seat-reservation' ),
					'bidirectional_route_search'      => __( 'On a same-bus-return route, lets a passenger boarding at an intermediate stop travel in either direction. See the Bidirectional Search chapter.', 'bus-ticket-booking-with-seat-reservation' ),
					'editable_return_route'           => __( 'Gives the Return tab its own From / To selectors, with From locked to the outbound destination. See the Bidirectional Search chapter.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- General ------------------------------------------------ */
					'seat_hold_minutes'               => __( 'How long a selected seat is reserved while the customer completes checkout. Too short and customers lose seats mid-payment; too long and seats sit idle.', 'bus-ticket-booking-with-seat-reservation' ),
					'disable_block_editor'            => __( 'Turns off the block editor for buses so the plugin\'s own editor is always used.', 'bus-ticket-booking-with-seat-reservation' ),
					'date_format'                     => __( 'The date format used by every date picker in the plugin.', 'bus-ticket-booking-with-seat-reservation' ),
					'date_format_short'               => __( 'The compact date format used where space is tight, such as result cards and calendar cells.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- Payments ----------------------------------------------- */
					'wbtm_booking_mode_selector'       => __( 'Chooses the engine that processes bookings: WooCommerce (its cart, checkout and gateways) or the plugin\'s own standalone checkout. Only modes that can actually run are offered.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_payment_tabs_html'           => __( 'The tab strip that switches between the WooCommerce and standalone panels. Presentation only — it stores no value of its own.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_wc_payment_gateways_manager' => __( 'An inline manager for enabling and ordering WooCommerce payment gateways without leaving this screen.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_wc_add_to_cart_redirect'     => __( 'Where the customer lands after seats are added to the cart — stay put, go to the cart, or go straight to checkout.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_wc_require_login'            => __( 'Requires a customer account before a booking can be completed. Useful when passengers need to look up their own tickets later.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_wc_show_billing_info'        => __( 'Shows the full billing address block at checkout. Turn it off for a shorter form when you do not need invoicing details.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_wc_confirm_status'           => __( 'Which payment statuses count as a confirmed booking. This drives the seat count, the ticket email and the calendar.', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_payment_gateways_ui'         => __( 'The standalone-mode gateway list. Presentation only — the individual methods store their own settings.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- Frontend Display --------------------------------------- */
					'show_filter_panel'               => __( 'Master switch for the whole filter sidebar beside search results. Turning it off hides every filter at once.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_filter_departure_time'      => __( 'Shows the departure-time filter (morning, afternoon, evening, night).', 'bus-ticket-booking-with-seat-reservation' ),
					'show_filter_bus_type'            => __( 'Shows the coach type filter, built from your Bus Types.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_filter_bus_operator'        => __( 'Shows the operator filter. Only useful when you list several operators.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_filter_boarding_point'      => __( 'Shows the boarding point filter.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_sort_bar'                   => __( 'Shows the sort bar above the results (by price, departure time and so on).', 'bus-ticket-booking-with-seat-reservation' ),
					'unpriced_route_action'           => __( 'What to do when a bus runs a route that has no fare configured — hide it from results, or show it as unavailable.', 'bus-ticket-booking-with-seat-reservation' ),
					'unpriced_route_message'          => __( 'The message shown in place of a price when an unpriced route is displayed rather than hidden.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- Deposit (PRO) ------------------------------------------ */
					'deposit_enabled'                 => __( 'Master switch for partial payment across the whole site.', 'bus-ticket-booking-with-seat-reservation' ),
					'deposit_offer_choice'            => __( 'Yes lets the customer choose between paying a deposit and paying in full. No forces the deposit with no choice offered.', 'bus-ticket-booking-with-seat-reservation' ),
					'deposit_type'                    => __( 'Whether the default deposit is a percentage of the fare or a fixed amount. Individual buses can override this.', 'bus-ticket-booking-with-seat-reservation' ),
					'deposit_value'                   => __( 'The default deposit amount, read according to the type above — 30 means 30% for a percentage deposit.', 'bus-ticket-booking-with-seat-reservation' ),
					'deposit_balance_due_days'        => __( 'How many days after booking the balance must be settled. 0 means no deadline.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- PDF (PRO) ---------------------------------------------- */
					'merge_pdf_ticket'                => __( 'Yes puts every seat in one order onto a single ticket. No produces one ticket per seat, which suits separate travellers.', 'bus-ticket-booking-with-seat-reservation' ),
					'thermal_ticket_enable'           => __( 'Produces a narrow receipt-style ticket suited to counter thermal printers.', 'bus-ticket-booking-with-seat-reservation' ),
					'thermal_ticket_width'            => __( 'The paper width of the thermal ticket. Match it to your printer or the layout will be clipped.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_ticket_filename'             => __( 'The filename customers see when they download their ticket.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_logo'                        => __( 'The logo printed in the ticket header.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_bg'                          => __( 'A background image for the ticket. About 680px wide works best.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_bg_color'                    => __( 'A solid background colour, used when no background image is set.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_text_color'                  => __( 'The colour of the ticket text. Keep the contrast high or printed tickets become hard to read.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_address'                     => __( 'Your company address, printed on the ticket.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_phone'                       => __( 'A contact phone number for passengers, printed on the ticket.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_email'                       => __( 'A contact email address, printed on the ticket.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_tc_title'                    => __( 'The heading above the terms block in the ticket footer.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_tc_text'                     => __( 'The terms text printed in the ticket footer — cancellation policy, baggage rules and so on.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- Email (PRO) -------------------------------------------- */
					'pdf_send_status'                 => __( 'Master switch for automatic ticket emails.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_email_status'                => __( 'Which order statuses trigger the ticket email. Choosing several means the customer may receive more than one.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_email_subject'               => __( 'The subject line of the ticket email.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_email_content'               => __( 'The body of the ticket email. Supports the {customer_name}, {bus_name}, {journey_date} and {order_id} placeholders.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_admin_notification_email'    => __( 'Where admin copies and low-seat alerts are delivered. Leave blank to use the site admin address.', 'bus-ticket-booking-with-seat-reservation' ),
					'pdf_email_form_name'             => __( 'The sender name on outgoing mail. Defaults to your WooCommerce email settings.', 'bus-ticket-booking-with-seat-reservation' ),
					'minimum_seat_treshold'           => __( 'When free seats on a departure fall to this number, an alert is emailed so you can add capacity.', 'bus-ticket-booking-with-seat-reservation' ),
					'seat_treshold_email_content'     => __( 'The body of the low-seat alert. Supports the {bus_name} and {journey_date} placeholders.', 'bus-ticket-booking-with-seat-reservation' ),
					'reminder_enabled'                => __( 'Sends passengers a reminder before their trip.', 'bus-ticket-booking-with-seat-reservation' ),
					'reminder_hours_before'           => __( 'How many hours before departure the reminder goes out.', 'bus-ticket-booking-with-seat-reservation' ),
					'reminder_email_subject'          => __( 'The subject line of the trip reminder.', 'bus-ticket-booking-with-seat-reservation' ),
					'reminder_email_content'          => __( 'The body of the trip reminder. Supports the same placeholders as the ticket email.', 'bus-ticket-booking-with-seat-reservation' ),
					'admin_notifications_enabled'     => __( 'Turns the dashboard notification centre and its admin-bar badge on or off.', 'bus-ticket-booking-with-seat-reservation' ),

					/* --- AI Chatbot (PRO) --------------------------------------- */
					'chatbot_enabled'                 => __( 'Master switch for the chat widget on the frontend.', 'bus-ticket-booking-with-seat-reservation' ),
					'ai_provider'                     => __( 'Which engine answers customers. Rule-Based is free and needs no key; the other providers call an external AI service and require an API key.', 'bus-ticket-booking-with-seat-reservation' ),
					'chatbot_name'                    => __( 'The name shown in the widget header.', 'bus-ticket-booking-with-seat-reservation' ),
					'welcome_message'                 => __( 'The first message a visitor sees when they open the chat.', 'bus-ticket-booking-with-seat-reservation' ),
					'primary_color'                   => __( 'The accent colour of the chat widget.', 'bus-ticket-booking-with-seat-reservation' ),
					'chatbot_position'                => __( 'Which corner of the screen the widget sits in.', 'bus-ticket-booking-with-seat-reservation' ),
					'show_on_pages'                   => __( 'Where the widget appears — everywhere, or only on bus-related pages.', 'bus-ticket-booking-with-seat-reservation' ),
				);
			}

			/**
			 * Build the full settings reference.
			 *
			 * @return array<int,array> One entry per settings section, in the order
			 *                          the settings screen registers them.
			 */
			public static function build(): array {
				$sections = apply_filters( 'wbtm_settings_sec_reg', array() );
				$fields   = apply_filters( 'wbtm_settings_sec_fields', array() );
				$meta     = self::section_meta();
				$notes    = self::notes();
				$out      = array();

				if ( ! is_array( $sections ) ) {
					$sections = array();
				}
				if ( ! is_array( $fields ) ) {
					$fields = array();
				}

				foreach ( $sections as $section ) {
					if ( empty( $section['id'] ) ) {
						continue;
					}
					$id   = $section['id'];
					$info = isset( $meta[ $id ] ) ? $meta[ $id ] : array();
					$rows = array();

					if ( ! empty( $fields[ $id ] ) && is_array( $fields[ $id ] ) ) {
						foreach ( $fields[ $id ] as $field ) {
							if ( empty( $field['name'] ) ) {
								continue;
							}
							$name  = $field['name'];
							$label = isset( $field['label'] ) ? wp_strip_all_tags( (string) $field['label'] ) : '';
							$desc  = isset( $notes[ $name ] )
								? $notes[ $name ]
								: ( isset( $field['desc'] ) ? wp_strip_all_tags( (string) $field['desc'] ) : '' );

							$rows[] = array(
								'name'    => $name,
								// Some rows are pure UI blocks with no label of their own.
								'label'   => '' !== $label ? $label : $name,
								'desc'    => $desc,
								'type'    => isset( $field['type'] ) ? (string) $field['type'] : 'text',
								'default' => self::readable_default( $field ),
								'options' => self::readable_options( $field ),
							);
						}
					}

					$out[] = array(
						'id'     => $id,
						'title'  => isset( $section['title'] ) ? wp_strip_all_tags( (string) $section['title'] ) : $id,
						'tab'    => isset( $info['tab'] ) ? $info['tab'] : '',
						'plan'   => isset( $info['plan'] ) ? $info['plan'] : 'free',
						'intro'  => isset( $info['intro'] ) ? $info['intro'] : '',
						'option' => $id,
						'fields' => $rows,
					);
				}

				return $out;
			}

			/**
			 * Human-readable default value. Never reads a saved option — only the
			 * declared default — so no configured secret can leak into the page.
			 *
			 * @param array $field Field definition.
			 * @return string
			 */
			private static function readable_default( array $field ): string {
				if ( ! array_key_exists( 'default', $field ) ) {
					return '—';
				}
				$default = $field['default'];
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

				if ( is_array( $default ) ) {
					$labels = array();
					foreach ( $default as $key => $value ) {
						$lookup   = is_string( $value ) ? $value : $key;
						$labels[] = isset( $options[ $lookup ] ) ? wp_strip_all_tags( (string) $options[ $lookup ] ) : (string) $lookup;
					}
					return $labels ? implode( ', ', $labels ) : '—';
				}
				if ( is_bool( $default ) ) {
					return $default
						? __( 'On', 'bus-ticket-booking-with-seat-reservation' )
						: __( 'Off', 'bus-ticket-booking-with-seat-reservation' );
				}
				$default = wp_strip_all_tags( (string) $default );
				if ( '' === trim( $default ) ) {
					return __( 'Empty', 'bus-ticket-booking-with-seat-reservation' );
				}
				if ( isset( $options[ $default ] ) ) {
					return wp_strip_all_tags( (string) $options[ $default ] );
				}
				return $default;
			}

			/**
			 * The available choices for a field, as readable labels.
			 *
			 * @param array $field Field definition.
			 * @return array<int,string>
			 */
			private static function readable_options( array $field ): array {
				if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
					return array();
				}
				$out = array();
				foreach ( $field['options'] as $value ) {
					$value = wp_strip_all_tags( (string) $value );
					if ( '' !== $value ) {
						$out[] = $value;
					}
				}
				return $out;
			}

			/**
			 * Friendly name for a field type.
			 *
			 * @param string $type Raw type from the field definition.
			 * @return string
			 */
			public static function friendly_type( string $type ): string {
				$map = array(
					'text'       => __( 'Text', 'bus-ticket-booking-with-seat-reservation' ),
					'textarea'   => __( 'Long text', 'bus-ticket-booking-with-seat-reservation' ),
					'number'     => __( 'Number', 'bus-ticket-booking-with-seat-reservation' ),
					'select'     => __( 'Choice', 'bus-ticket-booking-with-seat-reservation' ),
					'multicheck' => __( 'Multiple choice', 'bus-ticket-booking-with-seat-reservation' ),
					'checkbox'   => __( 'On / off', 'bus-ticket-booking-with-seat-reservation' ),
					'toggle'     => __( 'On / off', 'bus-ticket-booking-with-seat-reservation' ),
					'radio'      => __( 'Choice', 'bus-ticket-booking-with-seat-reservation' ),
					'color'      => __( 'Colour', 'bus-ticket-booking-with-seat-reservation' ),
					'file'       => __( 'Image / file', 'bus-ticket-booking-with-seat-reservation' ),
					'icon_image' => __( 'Icon or image', 'bus-ticket-booking-with-seat-reservation' ),
					'wysiwyg'    => __( 'Rich text', 'bus-ticket-booking-with-seat-reservation' ),
					'datepicker' => __( 'Date', 'bus-ticket-booking-with-seat-reservation' ),
					'password'   => __( 'Secret key', 'bus-ticket-booking-with-seat-reservation' ),
					'pages'      => __( 'Page picker', 'bus-ticket-booking-with-seat-reservation' ),
					'url'        => __( 'URL', 'bus-ticket-booking-with-seat-reservation' ),
					'html'       => __( 'Information', 'bus-ticket-booking-with-seat-reservation' ),
				);
				return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( $type );
			}
		}
	}
