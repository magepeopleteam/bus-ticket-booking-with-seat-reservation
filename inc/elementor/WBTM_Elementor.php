<?php
	/*
	 * @Author 		MagePeople Team
	 * Copyright: 	mage-people.com
	 *
	 * Elementor integration: a "Bus Booking" widget category with
	 *  - Bus Booking        → [wbtm-bus-booking]
	 *  - Bus Event Booking  → [wbtm-bus-event]
	 *
	 * The widget classes extend \Elementor\Widget_Base, which only exists once
	 * Elementor has loaded, so they are required inside the registration hook.
	 * Without Elementor this file only adds two hooks that never fire.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Elementor' ) ) {
		class WBTM_Elementor {
			const CATEGORY = 'wbtm-bus-booking';
			public function __construct() {
				add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
				add_filter( 'wbtm_load_frontend_assets', array( $this, 'load_frontend_assets' ) );
			}
			/**
			 * The asset gate reads post_content, but Elementor keeps a page's layout
			 * in `_elementor_data`, so pages built with these widgets (or with a
			 * Shortcode widget holding a bus shortcode) would get the form without
			 * its scripts. The editor preview needs them before a widget is saved.
			 */
			public function load_frontend_assets( $load ) {
				if ( $load ) {
					return $load;
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of Elementor's preview flag.
				if ( isset( $_GET['elementor-preview'] ) ) {
					return true;
				}
				if ( ! is_singular() ) {
					return $load;
				}
				$data = get_post_meta( (int) get_queried_object_id(), '_elementor_data', true );
				if ( ! is_string( $data ) || $data === '' ) {
					return $load;
				}
				return strpos( $data, '"widgetType":"wbtm-' ) !== false
					|| strpos( $data, '[wbtm' ) !== false
					|| strpos( $data, '[view-ticket' ) !== false;
			}
			public function register_category( $elements_manager ): void {
				$elements_manager->add_category( self::CATEGORY, array(
					'title' => esc_html__( 'Bus Booking', 'bus-ticket-booking-with-seat-reservation' ),
					'icon'  => 'fa fa-bus',
				) );
			}
			public function register_widgets( $widgets_manager ): void {
				if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
					return;
				}
				require_once __DIR__ . '/WBTM_Elementor_Widgets.php';
				$widgets_manager->register( new WBTM_Elementor_Bus_Booking_Widget() );
				$widgets_manager->register( new WBTM_Elementor_Bus_Event_Widget() );
			}
			/** @return array<int|string, string> published bus ID => title */
			public static function bus_options(): array {
				$options = array();
				foreach ( WBTM_Global_Function::get_all_post_id( WBTM_Functions::get_cpt() ) as $bus_id ) {
					/* translators: 1: bus title, 2: bus post ID */
					$options[ (string) $bus_id ] = sprintf( __( '%1$s (#%2$d)', 'bus-ticket-booking-with-seat-reservation' ), get_the_title( $bus_id ), $bus_id );
				}
				return $options;
			}
			/** @return array<string, string> event slug => name */
			public static function event_options(): array {
				$options = array( '' => esc_html__( '— None —', 'bus-ticket-booking-with-seat-reservation' ) );
				$terms   = get_terms( array( 'taxonomy' => WBTM_Bus_Events::TAXONOMY, 'hide_empty' => false ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$options[ $term->slug ] = $term->name;
					}
				}
				return $options;
			}
			/** @return array<string, string> stop name => stop name */
			public static function stop_options(): array {
				$options = array( '' => esc_html__( '— None —', 'bus-ticket-booking-with-seat-reservation' ) );
				foreach ( WBTM_Global_Function::get_all_term_data( 'wbtm_bus_stops' ) as $stop ) {
					$options[ $stop ] = $stop;
				}
				return $options;
			}
		}
		new WBTM_Elementor();
	}
