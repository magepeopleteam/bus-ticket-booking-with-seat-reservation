<?php
	/*
	 * @Author 		MagePeople Team
	 * Copyright: 	mage-people.com
	 *
	 * Elementor widgets for the embeddable booking shortcodes. Loaded only from
	 * WBTM_Elementor::register_widgets(), after Elementor has defined Widget_Base.
	 * Each widget maps its controls to shortcode attributes and renders through
	 * WBTM_Event_Shortcodes::render_shortcode(), so the output is identical to
	 * the shortcode.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	use Elementor\Controls_Manager;
	use Elementor\Widget_Base;
	if ( ! class_exists( 'WBTM_Elementor_Widget_Base' ) ) {
		abstract class WBTM_Elementor_Widget_Base extends Widget_Base {
			public function get_categories(): array {
				return array( WBTM_Elementor::CATEGORY );
			}
			/** Seats, prices and dates change constantly: never cache the output. */
			protected function is_dynamic_content(): bool {
				return true;
			}
			public function is_reload_preview_required() {
				return true;
			}
			protected static function inherit_options(): array {
				return array(
					''    => esc_html__( 'Use global setting', 'bus-ticket-booking-with-seat-reservation' ),
					'yes' => esc_html__( 'Yes', 'bus-ticket-booking-with-seat-reservation' ),
					'no'  => esc_html__( 'No', 'bus-ticket-booking-with-seat-reservation' ),
				);
			}
			protected function add_date_control( string $description ): void {
				$this->add_control( 'date', array(
					'label'          => esc_html__( 'Travel date', 'bus-ticket-booking-with-seat-reservation' ),
					'type'           => Controls_Manager::DATE_TIME,
					'picker_options' => array( 'enableTime' => false, 'dateFormat' => 'Y-m-d' ),
					'description'    => $description,
				) );
			}
			protected function add_style_control(): void {
				$this->add_control( 'style', array(
					'label'   => esc_html__( 'Result style', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''     => esc_html__( 'Default', 'bus-ticket-booking-with-seat-reservation' ),
						'flix' => esc_html__( 'Flix', 'bus-ticket-booking-with-seat-reservation' ),
					),
				) );
			}
			/** Y-m-d part of a DATE_TIME control value. */
			protected static function date_value( $value ): string {
				return WBTM_Bus_Scope::sanitize_date( substr( (string) $value, 0, 10 ) );
			}
		}
	}
	if ( ! class_exists( 'WBTM_Elementor_Bus_Booking_Widget' ) ) {
		class WBTM_Elementor_Bus_Booking_Widget extends WBTM_Elementor_Widget_Base {
			public function get_name(): string {
				return 'wbtm-bus-booking';
			}
			public function get_title(): string {
				return esc_html__( 'Bus Booking', 'bus-ticket-booking-with-seat-reservation' );
			}
			public function get_icon(): string {
				return 'eicon-bus';
			}
			public function get_keywords(): array {
				return array( 'bus', 'ticket', 'booking', 'seat', 'trip' );
			}
			protected function register_controls(): void {
				$this->start_controls_section( 'section_bus', array(
					'label' => esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				) );
				$this->add_control( 'bus_id', array(
					'label'       => esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ),
					'type'        => Controls_Manager::SELECT2,
					'options'     => WBTM_Elementor::bus_options(),
					'multiple'    => false,
					'label_block' => true,
				) );
				$this->add_control( 'details', array(
					'label'        => esc_html__( 'Show bus details', 'bus-ticket-booking-with-seat-reservation' ),
					'description'  => esc_html__( 'Stops, schedule, features, terms and photos above the booking form.', 'bus-ticket-booking-with-seat-reservation' ),
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => 'yes',
				) );
				$this->add_control( 'from', array(
					'label'   => esc_html__( 'Pre-select "From"', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => Controls_Manager::SELECT,
					'options' => WBTM_Elementor::stop_options(),
					'default' => '',
				) );
				$this->add_control( 'to', array(
					'label'   => esc_html__( 'Pre-select "To"', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => Controls_Manager::SELECT,
					'options' => WBTM_Elementor::stop_options(),
					'default' => '',
				) );
				$this->add_date_control( esc_html__( 'Optional. Only this outbound date can be booked.', 'bus-ticket-booking-with-seat-reservation' ) );
				$this->add_control( 'auto_search', array(
					'label'   => esc_html__( 'Search automatically', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => Controls_Manager::SELECT,
					'options' => self::inherit_options(),
					'default' => '',
				) );
				$this->add_style_control();
				$this->end_controls_section();
			}
			protected function render(): void {
				$s = $this->get_settings_for_display();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode renderer escapes its own output.
				echo WBTM_Event_Shortcodes::render_shortcode( 'wbtm-bus-booking', array(
					'id'          => absint( $s['bus_id'] ?? 0 ),
					'details'     => ( $s['details'] ?? '' ) === 'yes' ? 'yes' : 'no',
					'from'        => (string) ( $s['from'] ?? '' ),
					'to'          => (string) ( $s['to'] ?? '' ),
					'date'        => self::date_value( $s['date'] ?? '' ),
					'auto_search' => (string) ( $s['auto_search'] ?? '' ),
					'style'       => (string) ( $s['style'] ?? '' ),
				) );
			}
		}
	}
	if ( ! class_exists( 'WBTM_Elementor_Bus_Event_Widget' ) ) {
		class WBTM_Elementor_Bus_Event_Widget extends WBTM_Elementor_Widget_Base {
			public function get_name(): string {
				return 'wbtm-bus-event';
			}
			public function get_title(): string {
				return esc_html__( 'Bus Event Booking', 'bus-ticket-booking-with-seat-reservation' );
			}
			public function get_icon(): string {
				return 'eicon-calendar';
			}
			public function get_keywords(): array {
				return array( 'bus', 'event', 'concert', 'festival', 'ticket', 'booking' );
			}
			protected function register_controls(): void {
				$this->start_controls_section( 'section_buses', array(
					'label' => esc_html__( 'Buses', 'bus-ticket-booking-with-seat-reservation' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				) );
				$this->add_control( 'event', array(
					'label'       => WBTM_Bus_Events::label(),
					'type'        => Controls_Manager::SELECT,
					'options'     => WBTM_Elementor::event_options(),
					'default'     => '',
					'description' => esc_html__( 'Shows every bus assigned to this event and uses its destination and date.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				$this->add_control( 'bus_ids', array(
					'label'       => esc_html__( 'Or pick buses', 'bus-ticket-booking-with-seat-reservation' ),
					'type'        => Controls_Manager::SELECT2,
					'options'     => WBTM_Elementor::bus_options(),
					'multiple'    => true,
					'label_block' => true,
				) );
				$this->add_control( 'destination', array(
					'label'       => esc_html__( 'Destination', 'bus-ticket-booking-with-seat-reservation' ),
					'type'        => Controls_Manager::SELECT,
					'options'     => WBTM_Elementor::stop_options(),
					'default'     => '',
					'description' => esc_html__( 'Optional. Overrides the event destination; on its own it shows every bus serving this stop.', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				$this->add_date_control( esc_html__( 'Optional. Overrides the event date; only this outbound date can be booked.', 'bus-ticket-booking-with-seat-reservation' ) );
				$this->add_control( 'from', array(
					'label'   => esc_html__( 'Pre-select "From"', 'bus-ticket-booking-with-seat-reservation' ),
					'type'    => Controls_Manager::SELECT,
					'options' => WBTM_Elementor::stop_options(),
					'default' => '',
				) );
				$this->end_controls_section();
				$this->start_controls_section( 'section_display', array(
					'label' => esc_html__( 'Display', 'bus-ticket-booking-with-seat-reservation' ),
					'tab'   => Controls_Manager::TAB_CONTENT,
				) );
				$this->add_control( 'title', array(
					'label'       => esc_html__( 'Heading', 'bus-ticket-booking-with-seat-reservation' ),
					'type'        => Controls_Manager::TEXT,
					'placeholder' => esc_html__( 'Defaults to the event name', 'bus-ticket-booking-with-seat-reservation' ),
				) );
				foreach ( array(
					'header'           => esc_html__( 'Event header', 'bus-ticket-booking-with-seat-reservation' ),
					'overview'         => esc_html__( 'Departure overview', 'bus-ticket-booking-with-seat-reservation' ),
					'lock_destination' => esc_html__( 'Lock destination', 'bus-ticket-booking-with-seat-reservation' ),
					'auto_search'      => esc_html__( 'Search automatically', 'bus-ticket-booking-with-seat-reservation' ),
				) as $key => $label ) {
					$this->add_control( $key, array(
						'label'   => $label,
						'type'    => Controls_Manager::SELECT,
						'options' => self::inherit_options(),
						'default' => '',
					) );
				}
				$this->add_style_control();
				$this->end_controls_section();
			}
			protected function render(): void {
				$s       = $this->get_settings_for_display();
				$bus_ids = isset( $s['bus_ids'] ) && is_array( $s['bus_ids'] ) ? implode( ',', array_map( 'absint', $s['bus_ids'] ) ) : '';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode renderer escapes its own output.
				echo WBTM_Event_Shortcodes::render_shortcode( 'wbtm-bus-event', array(
					'event'            => (string) ( $s['event'] ?? '' ),
					'bus_ids'          => $bus_ids,
					'destination'      => (string) ( $s['destination'] ?? '' ),
					'date'             => self::date_value( $s['date'] ?? '' ),
					'from'             => (string) ( $s['from'] ?? '' ),
					'title'            => (string) ( $s['title'] ?? '' ),
					'header'           => (string) ( $s['header'] ?? '' ),
					'overview'         => (string) ( $s['overview'] ?? '' ),
					'lock_destination' => (string) ( $s['lock_destination'] ?? '' ),
					'auto_search'      => (string) ( $s['auto_search'] ?? '' ),
					'style'            => (string) ( $s['style'] ?? '' ),
				) );
			}
		}
	}
