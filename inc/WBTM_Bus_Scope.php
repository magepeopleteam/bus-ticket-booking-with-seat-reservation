<?php
	/*
	 * @Author 		MagePeople Team
	 * Copyright: 	mage-people.com
	 *
	 * Bus scope: restricts the search/booking pipeline to a fixed set of buses
	 * (and optionally a fixed outbound travel date) for one shortcode instance.
	 *
	 * Without a scope the search form works across every published bus (global
	 * search) or across exactly one bus (single bus page, $post_id > 0). A scope
	 * adds the case in between: "every bus of this event / to this destination /
	 * from this list". It is resolved once while the shortcode renders, written
	 * into the form as the hidden `wbtm_bus_scope` input, and sent back by
	 * wbtm_global.js with every route / date / bus-list AJAX request, where
	 * from_request() re-activates it before the core handler runs.
	 *
	 * The scope only narrows what the existing queries return (WP_Query post__in
	 * and an outbound-date whitelist); every booking rule — prices, seats, sale
	 * windows, cart validation — still runs unchanged. The bus IDs come from the
	 * client, so they are re-validated against published wbtm_bus posts on every
	 * request; a forged scope can only narrow a public search further.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Bus_Scope' ) ) {
		class WBTM_Bus_Scope {
			/** Request field carrying the comma-separated bus IDs. */
			const FIELD = 'wbtm_bus_scope';
			/** Request field carrying the fixed outbound date (Y-m-d). */
			const DATE_FIELD = 'wbtm_scope_date';
			/** Front-end AJAX actions whose results depend on the scope. */
			const AJAX_ACTIONS = array(
				'get_wbtm_dropping_point',
				'get_wbtm_journey_date',
				'get_wbtm_return_date',
				'get_wbtm_bus_list',
				'get_wbtm_return_bus_list',
				'get_wbtm_soldout_dates',
			);
			/**
			 * Active scopes, innermost last. A stack (not a single value) so a
			 * scoped shortcode rendered inside another one restores the outer
			 * scope when it finishes.
			 *
			 * @var array<int, array{ids: int[], date: string}>
			 */
			private static $stack = array();
			public function __construct() {
				add_filter( 'wbtm_bus_query_args', array( __CLASS__, 'filter_query_args' ) );
				add_filter( 'wbtm_route_list_bus_ids', array( __CLASS__, 'filter_bus_ids' ) );
				add_filter( 'wbtm_journey_dates', array( __CLASS__, 'filter_journey_dates' ) );
				add_action( 'wbtm_search_form_hidden_fields', array( __CLASS__, 'render_hidden_field' ), 10, 2 );
				// Priority 1: activate the scope before the core handler (priority 10) runs.
				foreach ( self::AJAX_ACTIONS as $action ) {
					add_action( 'wp_ajax_' . $action, array( __CLASS__, 'from_request' ), 1 );
					add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'from_request' ), 1 );
				}
			}
			//==========================//
			public static function push( array $ids, string $date = '', array $presets = array() ): void {
				$clean = self::sanitize_ids( $ids );
				// A non-empty list with no valid ID (e.g. [0]) means "no bus matches";
				// keep it as [0] so the filters match nothing instead of everything.
				if ( $ids && ! $clean ) {
					$clean = array( 0 );
				}
				self::$stack[] = array(
					'ids'     => $clean,
					'date'    => self::sanitize_date( $date ),
					'presets' => $presets,
				);
			}
			public static function pop(): void {
				array_pop( self::$stack );
			}
			public static function active(): bool {
				return ! empty( self::$stack );
			}
			/** @return int[] Bus IDs of the active scope; empty = not restricted by bus. */
			public static function ids(): array {
				return self::active() ? self::$stack[ count( self::$stack ) - 1 ]['ids'] : array();
			}
			/** Fixed outbound date (Y-m-d) of the active scope, or ''. */
			public static function date(): string {
				return self::active() ? self::$stack[ count( self::$stack ) - 1 ]['date'] : '';
			}
			public static function presets(): array {
				return self::active() ? self::$stack[ count( self::$stack ) - 1 ]['presets'] : array();
			}
			/**
			 * Re-activate the scope sent back by wbtm_global.js. Runs before the
			 * core AJAX handler, which verifies the nonce itself; nothing here
			 * writes data or reveals anything beyond the public search.
			 */
			public static function from_request(): void {
				// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only narrowing of a public search; the core handler verifies the nonce.
				$raw_ids  = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
				$raw_date = isset( $_POST[ self::DATE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::DATE_FIELD ] ) ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				$ids  = self::published_bus_ids( self::parse_ids( $raw_ids ) );
				$date = self::sanitize_date( $raw_date );
				// An explicit scope whose buses are all gone (deleted/unpublished)
				// must match nothing, not fall back to every bus.
				if ( $raw_ids !== '' && empty( $ids ) ) {
					$ids = array( 0 );
				}
				if ( $ids || $date ) {
					self::push( $ids, $date );
				}
			}
			//==========================// filters
			public static function filter_query_args( $args ) {
				$ids = self::ids();
				if ( ! $ids || ! is_array( $args ) ) {
					return $args;
				}
				if ( ! empty( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
					$ids = array_values( array_intersect( array_map( 'intval', $args['post__in'] ), $ids ) );
				}
				// post__in => [0] matches nothing; an empty array would match everything.
				$args['post__in'] = $ids ? $ids : array( 0 );
				return $args;
			}
			public static function filter_bus_ids( $bus_ids ) {
				$ids = self::ids();
				if ( $ids && is_array( $bus_ids ) ) {
					$bus_ids = array_values( array_intersect( array_map( 'intval', $bus_ids ), $ids ) );
				}
				return $bus_ids;
			}
			public static function filter_journey_dates( $dates ) {
				$date = self::date();
				if ( $date && is_array( $dates ) ) {
					$dates = array_values( array_filter( $dates, function ( $d ) use ( $date ) {
						return gmdate( 'Y-m-d', strtotime( $d ) ) === $date;
					} ) );
				}
				return $dates;
			}
			/**
			 * Print the scope (and the shortcode's field presets) into the search
			 * form, where wbtm_global.js and wbtm_bus_scope.js read it back.
			 */
			public static function render_hidden_field( $post_id = 0, $params = array() ): void {
				if ( ! self::active() ) {
					return;
				}
				$presets = self::presets();
				$attrs   = array(
					'data-date'        => self::date(),
					'data-from'        => $presets['from'] ?? '',
					'data-to'          => $presets['to'] ?? '',
					'data-lock-to'     => ! empty( $presets['lock_to'] ) ? '1' : '',
					'data-auto-search' => ! empty( $presets['auto_search'] ) ? '1' : '',
				);
				$out = '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( implode( ',', self::ids() ) ) . '"';
				foreach ( $attrs as $name => $value ) {
					$out .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
				}
				echo $out . '/>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped above.
			}
			//==========================// resolving shortcode attributes
			/**
			 * Turn shortcode filter attributes into a bus ID list.
			 *
			 * Every given criterion narrows the result (AND). Returns null when no
			 * criterion was given at all (= no scope, keep the global search).
			 *
			 * @param array $atts bus_ids, event, cat, destination, date (Y-m-d; only
			 *                    applied when $date_filters_buses is true).
			 * @return int[]|null
			 */
			public static function resolve( array $atts, bool $date_filters_buses = false ): ?array {
				$ids        = null;
				$restricted = false;
				$bus_ids    = self::parse_ids( (string) ( $atts['bus_ids'] ?? '' ) );
				if ( ( $atts['bus_ids'] ?? '' ) !== '' ) {
					$restricted = true;
					$ids        = $bus_ids;
				}
				$events = trim( (string) ( $atts['event'] ?? '' ) );
				if ( $events !== '' ) {
					$restricted = true;
					$term_ids   = array();
					foreach ( array_filter( array_map( 'trim', explode( ',', $events ) ) ) as $event ) {
						$term = WBTM_Bus_Events::get_event( $event );
						if ( $term ) {
							$term_ids[] = (int) $term->term_id;
						}
					}
					$event_bus_ids = $term_ids ? get_posts( array(
						'post_type'      => WBTM_Functions::get_cpt(),
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => WBTM_Bus_Events::TAXONOMY,
								'field'    => 'term_id',
								'terms'    => $term_ids,
							),
						),
					) ) : array();
					$ids = self::narrow( $ids, $event_bus_ids );
				}
				$cat = trim( (string) ( $atts['cat'] ?? '' ) );
				if ( $cat !== '' && $cat !== '0' ) {
					$restricted = true;
					$ids        = self::narrow( $ids, WBTM_Query::get_bus_id( '', '', $cat ) );
				}
				$destination = trim( (string) ( $atts['destination'] ?? '' ) );
				if ( $destination !== '' ) {
					$restricted = true;
					$ids        = self::narrow( $ids, self::bus_ids_serving_stop( $destination ) );
				}
				$date = self::sanitize_date( (string) ( $atts['date'] ?? '' ) );
				if ( $date_filters_buses && $date ) {
					$restricted = true;
					$candidates = $ids ?? WBTM_Global_Function::get_all_post_id( WBTM_Functions::get_cpt() );
					$ids        = array_values( array_filter( $candidates, function ( $bus_id ) use ( $date ) {
						return in_array( $date, WBTM_Functions::get_route_date( (int) $bus_id ), true );
					} ) );
				}
				if ( ! $restricted ) {
					return null;
				}
				return self::published_bus_ids( $ids ?? array() );
			}
			/**
			 * Published buses whose route touches the stop (boarding or dropping),
			 * matched exactly (case-insensitive) — the meta LIKE used by the core
			 * search would also match "Milano Centrale" for "Milan".
			 *
			 * @return int[]
			 */
			public static function bus_ids_serving_stop( string $stop ): array {
				$needle = self::normalize_place( $stop );
				$ids    = array();
				foreach ( WBTM_Global_Function::get_all_post_id( WBTM_Functions::get_cpt() ) as $bus_id ) {
					$stops = array_merge(
						(array) WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_bus_bp_stops', array() ),
						(array) WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_bus_next_stops', array() )
					);
					foreach ( $stops as $place ) {
						if ( is_string( $place ) && self::normalize_place( $place ) === $needle ) {
							$ids[] = (int) $bus_id;
							break;
						}
					}
				}
				return $ids;
			}
			public static function normalize_place( string $place ): string {
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $place ) ) : strtolower( trim( $place ) );
			}
			//==========================// sanitizing
			/** @return int[] */
			public static function parse_ids( string $raw ): array {
				return self::sanitize_ids( preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: array() );
			}
			/** @return int[] */
			public static function sanitize_ids( array $ids ): array {
				return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			}
			/** Y-m-d of a valid calendar date, or ''. */
			public static function sanitize_date( string $date ): string {
				$date = trim( $date );
				if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
					return $date;
				}
				return '';
			}
			/**
			 * Keep only IDs of published buses, preserving the given order.
			 *
			 * @param int[] $ids
			 * @return int[]
			 */
			public static function published_bus_ids( array $ids ): array {
				$ids = self::sanitize_ids( $ids );
				if ( ! $ids ) {
					return array();
				}
				return array_map( 'intval', get_posts( array(
					'post_type'      => WBTM_Functions::get_cpt(),
					'post_status'    => 'publish',
					'post__in'       => $ids,
					'orderby'        => 'post__in',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				) ) );
			}
			/**
			 * @param int[]|null $current null = not restricted yet.
			 * @param int[]      $allowed
			 * @return int[]
			 */
			private static function narrow( ?array $current, array $allowed ): array {
				$allowed = self::sanitize_ids( $allowed );
				return $current === null ? $allowed : array_values( array_intersect( $current, $allowed ) );
			}
		}
		new WBTM_Bus_Scope();
	}
