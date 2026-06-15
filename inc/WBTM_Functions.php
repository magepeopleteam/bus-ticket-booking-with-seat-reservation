<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Functions' ) ) {
		class WBTM_Functions {
			public static function is_pro_active() {
				if ( class_exists( 'WBTM_Dependencies_Pro' ) || class_exists( 'Wbtm_Woocommerce_bus_Pro' ) ) {
					return true;
				}
				if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				return function_exists( 'is_plugin_active' )
					&& is_plugin_active( 'addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php' )
					&& file_exists( WP_PLUGIN_DIR . '/addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php' );
			}
			public static function template_path( $file_name ): string {
				$template_path = get_stylesheet_directory() . '/templates/';
				$default_dir   = WBTM_PLUGIN_DIR . '/templates/';
				$dir           = is_dir( $template_path ) ? $template_path : $default_dir;
				$file_path     = $dir . $file_name;
				return locate_template( array( 'templates/' . $file_name ) ) ? $file_path : $default_dir . $file_name;
			}
			// Fixed by Shahnur — Pro-only full bus feature gate 2026-05-07 01:55 PM
			public static function is_full_bus_feature_enabled() {
				return self::is_pro_active();
			}
			//==========================//
			public static function get_bus_route( $post_id = 0, $start_route = '' ) {
				$all_routes = [];
				if ( $post_id > 0 ) {
					$all_routes = self::single_bus_route( $post_id, $start_route );
				} else {
					if ( $start_route ) {
						$bus_ids = WBTM_Query::get_bus_id( $start_route );
						if ( sizeof( $bus_ids ) > 0 ) {
							foreach ( $bus_ids as $bus_id ) {
								$routes     = self::single_bus_route( $bus_id, $start_route );
								$all_routes = array_merge( $all_routes, $routes );
							}
						}
					} else {
						$bus_ids = WBTM_Global_Function::get_all_post_id( WBTM_Functions::get_cpt() );
						if ( sizeof( $bus_ids ) > 0 ) {
							foreach ( $bus_ids as $bus_id ) {
								$routes     = WBTM_Global_Function::get_post_info( $bus_id, 'wbtm_bus_bp_stops', [] );
								$all_routes = array_merge( $all_routes, $routes );
							}
						}
					}
				}
				return array_unique( $all_routes );
			}
			public static function single_bus_route( $post_id, $start_route = '' ) {
				$forward = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
				if ( ! $start_route ) {
					return self::single_bus_route_from_infos( $forward, '' );
				}
				$forward_dests = self::single_bus_route_from_infos( $forward, $start_route );
				$r             = $forward_dests;

				if ( self::is_same_bus_return_enabled( $post_id ) ) {
					// Destinations reachable on the return leg from this same boarding point.
					$return_dests = [];
					foreach ( self::get_same_bus_return_route_candidates( $post_id, $forward ) as $infos ) {
						$candidate = self::single_bus_route_from_infos( $infos, $start_route );
						if ( sizeof( $candidate ) > 0 ) {
							$return_dests = $candidate;
							break;
						}
					}

					/**
					 * Bidirectional stop search (Pro feature).
					 *
					 * When TRUE, a passenger boarding at an intermediate stop can pick a
					 * destination in EITHER direction, so the forward and return
					 * destination lists are merged (e.g. from Nelspruit you can reach both
					 * O.R. Tambo outbound and Marloth Park on the return leg).
					 *
					 * When FALSE (default / free), behaviour is unchanged: the return leg
					 * is only used as a fallback for terminal stops that have no forward
					 * destination (e.g. the final stop of the outbound route).
					 *
					 * @param bool   $enabled     Default false.
					 * @param int    $post_id     Bus post ID.
					 * @param string $start_route Selected boarding point.
					 */
					$bidirectional = (bool) apply_filters( 'wbtm_enable_bidirectional_route_search', false, $post_id, $start_route );

					if ( $bidirectional ) {
						// Offer every stop reachable later on EITHER physical leg (by position) so
						// intermediate->intermediate trips work even when stops are boarding-only.
						$merged = [];
						foreach ( self::get_same_bus_physical_legs( $post_id, $forward ) as $leg ) {
							$merged = self::merge_unique_routes( $merged, self::dests_after_by_position( $leg, $start_route ) );
						}
						$r = $merged;
					} elseif ( sizeof( $forward_dests ) === 0 ) {
						$r = $return_dests;
					}
				}
				return $r;
			}

			/**
			 * Merge two route-place lists, preserving order and removing
			 * case-insensitive duplicates.
			 *
			 * @param array<int, string> $a
			 * @param array<int, string> $b
			 * @return array<int, string>
			 */
			private static function merge_unique_routes( $a, $b ) {
				$out  = [];
				$seen = [];
				foreach ( array_merge( (array) $a, (array) $b ) as $place ) {
					$key = strtolower( (string) $place );
					if ( $place !== '' && $place !== null && ! isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$out[]        = $place;
					}
				}
				return $out;
			}

			/**
			 * @param array<int, array<string, mixed>> $full_route_infos
			 * @return array<int, string>
			 */
			private static function single_bus_route_from_infos( $full_route_infos, $start_route = '' ) {
				$all_routes = [];
				$count_next = 0;
				if ( sizeof( $full_route_infos ) > 0 ) {
					foreach ( $full_route_infos as $info ) {
						if ( $start_route ) {
							if ( $count_next > 0 && ( $info['type'] == 'dp' || $info['type'] == 'both' ) ) {
								$all_routes[] = $info['place'];
							}
							if ( ( $info['type'] == 'bp' || $info['type'] == 'both' ) && strtolower( (string) $info['place'] ) === strtolower( (string) $start_route ) ) {
								$count_next = 1;
							}
						} else {
							if ( $info['type'] == 'bp' || $info['type'] == 'both' ) {
								$all_routes[] = $info['place'];
							}
						}
					}
				}
				return $all_routes;
			}

			public static function is_same_bus_return_enabled( $post_id ) {
				return WBTM_Global_Function::get_post_info( $post_id, 'wbtm_same_bus_return_enabled', 'no' ) === 'yes';
			}

			/**
			 * Reverse outbound stops for return direction; swap bp/dp types.
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 * @return array<int, array<string, mixed>>
			 */
			public static function reverse_wbtm_route_infos( $route_infos ) {
				if ( ! is_array( $route_infos ) || count( $route_infos ) < 2 ) {
					return is_array( $route_infos ) ? $route_infos : [];
				}
				$rev = array_reverse( $route_infos );
				foreach ( $rev as $k => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$t = isset( $row['type'] ) ? $row['type'] : '';
					if ( $t === 'bp' ) {
						$rev[ $k ]['type'] = 'dp';
					} elseif ( $t === 'dp' ) {
						$rev[ $k ]['type'] = 'bp';
					}
				}
				return $rev;
			}

			/**
			 * Ordered return-route definitions to try when same-bus return is on (custom timetable may be entered forward or reverse).
			 *
			 * @param array<int, array<string, mixed>> $forward_route_infos
			 * @return array<int, array<int, array<string, mixed>>>
			 */
			private static function get_same_bus_return_route_candidates( $post_id, $forward_route_infos ) {
				$custom = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_return_route_info', [] );
				$rev_forward = self::reverse_wbtm_route_infos( $forward_route_infos );
				$out         = [];
				if ( is_array( $custom ) && count( $custom ) > 1 ) {
					$out[] = $custom;
					$out[] = self::reverse_wbtm_route_infos( $custom );
				}
				$out[] = $rev_forward;
				return $out;
			}

			/**
			 * Whether this place can be used as a boarding point on the route row list.
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 */
			private static function wbtm_route_infos_has_boarding_place( $route_infos, $place ) {
				if ( ! is_array( $route_infos ) || $place === '' || $place === null ) {
					return false;
				}
				foreach ( $route_infos as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['place'] ) ) {
						continue;
					}
					$t = isset( $row['type'] ) ? $row['type'] : '';
					if ( ( $t === 'bp' || $t === 'both' ) && strtolower( (string) $row['place'] ) === strtolower( (string) $place ) ) {
						return true;
					}
				}
				return false;
			}

			/**
			 * Whether start can board before end can drop along the ordered route (bp/both then later dp/both).
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 */
			private static function wbtm_route_infos_support_od_leg( $route_infos, $start_route, $end_route ) {
				if ( ! is_array( $route_infos ) || $start_route === '' || $end_route === '' ) {
					return false;
				}
				$started = false;
				foreach ( $route_infos as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['place'] ) ) {
						continue;
					}
					$t = isset( $row['type'] ) ? $row['type'] : '';
					if ( ! $started && ( $t === 'bp' || $t === 'both' ) && strtolower( (string) $row['place'] ) === strtolower( (string) $start_route ) ) {
						$started = true;
						continue;
					}
					if ( $started && ( $t === 'dp' || $t === 'both' ) && strtolower( (string) $row['place'] ) === strtolower( (string) $end_route ) ) {
						return true;
					}
				}
				return false;
			}

			/**
		 * @param array<int, string> $dir
		 */
			public static function route_place_index( $dir, $place ) {
				if ( ! is_array( $dir ) || $place === '' || $place === null ) {
					return null;
				}
				foreach ( $dir as $i => $p ) {
					if ( strtolower( (string) $p ) === strtolower( (string) $place ) ) {
						return (int) $i;
					}
				}
				return null;
			}

			public static function resolve_price_leg_for_od_pair( $post_id, $start_route, $end_route, $fallback_leg = 'outbound' ) {
				$fallback_leg = $fallback_leg === 'return' ? 'return' : 'outbound';
				if ( ! $post_id || ! $start_route || ! $end_route ) {
					return $fallback_leg;
				}
				$resolved = self::resolve_od_leg( $post_id, $start_route, $end_route );
				return $resolved['leg'] !== null ? $resolved['leg'] : $fallback_leg;
			}

			/**
			 * Route rows used for date/time math (forward vs return leg).
			 *
			 * @param array<int, array<string, mixed>>|null $forward_route_infos
			 * @return array<int, array<string, mixed>>
			 */
			public static function resolve_route_infos_for_od_pair( $post_id, $start_route, $end_route, $forward_route_infos = null ) {
				$resolved = self::resolve_od_leg( $post_id, $start_route, $end_route, $forward_route_infos );
				return $resolved['route_infos'];
			}

			/**
			 * Decide which leg (outbound vs return) and which route-row set serves an
			 * origin/destination pair, choosing the leg that connects the two stops with
			 * the SHORTEST forward (non day-wrapping) travel time.
			 *
			 * This is more robust than ordering stops via the stored `wbtm_route_direction`
			 * list: on a bidirectional "same bus return" trip a pair such as Berlin -> Hamburg
			 * exists on BOTH legs (outbound 07:00->08:00 and the reversed return 14:00->13:00+1d).
			 * We always want the natural same-day leg, regardless of how the direction array
			 * happens to be ordered.
			 *
			 * @param array<int, array<string, mixed>>|null $forward_route_infos
			 * @return array{leg: string|null, route_infos: array<int, array<string, mixed>>}
			 */
			private static function resolve_od_leg( $post_id, $start_route, $end_route, $forward_route_infos = null ) {
				if ( $forward_route_infos === null ) {
					$forward_route_infos = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
				}
				if ( ! is_array( $forward_route_infos ) ) {
					$forward_route_infos = [];
				}
				$result = [ 'leg' => null, 'route_infos' => $forward_route_infos ];
				if ( count( $forward_route_infos ) < 2 || ! $start_route || ! $end_route ) {
					return $result;
				}

				$same_bus_return = $post_id && self::is_same_bus_return_enabled( $post_id );

				// Physical legs the bus actually runs (index 0 = outbound, 1 = return).
				$candidates = $same_bus_return
					? self::get_same_bus_physical_legs( $post_id, $forward_route_infos )
					: [ $forward_route_infos ];

				$ref_date = current_time( 'Y-m-d' );
				$best_dur = null;
				foreach ( $candidates as $index => $candidate_rows ) {
					// Same-bus-return (bidirectional) buses connect any stop to a later stop on a
					// leg regardless of bp/dp type; normal buses honour the configured types.
					$supported = $same_bus_return
						? self::route_infos_support_od_by_position( $candidate_rows, $start_route, $end_route )
						: self::wbtm_route_infos_support_od_leg( $candidate_rows, $start_route, $end_route );
					if ( ! $supported ) {
						continue;
					}
					$times = self::od_leg_times_from_infos( $post_id, $candidate_rows, $start_route, $end_route, $ref_date );
					if ( empty( $times ) ) {
						continue;
					}
					$duration = strtotime( $times['dp_time'] ) - strtotime( $times['bp_time'] );
					// Strict "<" so the outbound leg (checked first) wins ties.
					if ( $best_dur === null || $duration < $best_dur ) {
						$best_dur               = $duration;
						$result['leg']         = $index === 0 ? 'outbound' : 'return';
						$result['route_infos'] = $candidate_rows;
					}
				}
				return $result;
			}

			/**
			 * The two physical legs a same-bus-return bus runs: [outbound, return].
			 * The return leg is the custom return timetable when set, else the reversed outbound.
			 *
			 * @param array<int, array<string, mixed>> $forward_route_infos
			 * @return array<int, array<int, array<string, mixed>>>
			 */
			private static function get_same_bus_physical_legs( $post_id, $forward_route_infos ) {
				$legs   = [ $forward_route_infos ];
				$custom = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_return_route_info', [] );
				if ( is_array( $custom ) && count( $custom ) > 1 ) {
					$legs[] = $custom;
				} else {
					$legs[] = self::reverse_wbtm_route_infos( $forward_route_infos );
				}
				return $legs;
			}

			/**
			 * Stops reachable after $start_route on a leg, by position (ignoring bp/dp type).
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 * @return array<int, string>
			 */
			private static function dests_after_by_position( $route_infos, $start_route ) {
				$out     = [];
				$started = false;
				if ( is_array( $route_infos ) ) {
					foreach ( $route_infos as $row ) {
						if ( ! is_array( $row ) || ! isset( $row['place'] ) || $row['place'] === '' ) {
							continue;
						}
						if ( $started ) {
							$out[] = $row['place'];
						} elseif ( strtolower( (string) $row['place'] ) === strtolower( (string) $start_route ) ) {
							$started = true;
						}
					}
				}
				return $out;
			}

			/**
			 * Whether $end_route appears after $start_route on a leg, by position (type-agnostic).
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 */
			private static function route_infos_support_od_by_position( $route_infos, $start_route, $end_route ) {
				if ( ! is_array( $route_infos ) || $start_route === '' || $end_route === '' ) {
					return false;
				}
				$started = false;
				foreach ( $route_infos as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['place'] ) ) {
						continue;
					}
					$place = strtolower( (string) $row['place'] );
					if ( ! $started && $place === strtolower( (string) $start_route ) ) {
						$started = true;
						continue;
					}
					if ( $started && $place === strtolower( (string) $end_route ) ) {
						return true;
					}
				}
				return false;
			}

			/**
			 * Boarding/dropping datetimes for an OD pair within a specific set of route rows on a
			 * given date, honouring day-wrapping. Same-bus-return buses match stops by position
			 * (type-agnostic) so a boarding-only intermediate stop still yields times when used as
			 * a drop point in the opposite direction.
			 *
			 * @param array<int, array<string, mixed>> $route_infos
			 * @return array<string, string>
			 */
			private static function od_leg_times_from_infos( $post_id, $route_infos, $start_route, $end_route, $date ) {
				if ( ! is_array( $route_infos ) || count( $route_infos ) < 2 || ! $start_route || ! $end_route || ! $date ) {
					return [];
				}
				$ignore_types = $post_id && self::is_same_bus_return_enabled( $post_id );
				$expanded = self::get_route_all_date_info( $post_id, [ gmdate( 'Y-m-d', strtotime( $date ) ) ], $route_infos );
				foreach ( $expanded as $rows ) {
					$bp_time = '';
					foreach ( $rows as $info ) {
						$is_bp = $ignore_types || $info['type'] === 'bp' || $info['type'] === 'both';
						$is_dp = $ignore_types || $info['type'] === 'dp' || $info['type'] === 'both';
						if ( ! $bp_time && $is_bp && strtolower( (string) $info['place'] ) === strtolower( (string) $start_route ) ) {
							$bp_time = $info['time'];
							continue;
						}
						if ( $bp_time && $is_dp && strtolower( (string) $info['place'] ) === strtolower( (string) $end_route ) ) {
							return [ 'bp_time' => $bp_time, 'dp_time' => $info['time'] ];
						}
					}
				}
				return [];
			}

			/**
			 * Public: boarding/dropping datetimes for an OD pair on a date, using the
			 * automatically resolved leg (outbound vs return).
			 *
			 * @return array<string, string>
			 */
			public static function get_od_leg_datetimes( $post_id, $start_route, $end_route, $date ) {
				if ( ! $post_id || ! $start_route || ! $end_route || ! $date ) {
					return [];
				}
				$route_infos = self::resolve_route_infos_for_od_pair( $post_id, $start_route, $end_route );
				return self::od_leg_times_from_infos( $post_id, $route_infos, $start_route, $end_route, $date );
			}

			/**
			 * Earliest operational date on/after $r_date whose return bus departs at or after
			 * $floor_ts (the outbound arrival). Lets a same-day round trip whose mirror leg only
			 * runs earlier in the day roll forward to the next available day.
			 *
			 * @param int $floor_ts Unix timestamp the return boarding must be >=.
			 * @return string Y-m-d
			 */
			public static function resolve_return_date_after( $post_id, $return_start, $return_end, $r_date, $floor_ts ) {
				$base = $r_date ? gmdate( 'Y-m-d', strtotime( $r_date ) ) : '';
				if ( ! $post_id || ! $return_start || ! $return_end || ! $base || ! $floor_ts ) {
					return $base;
				}
				$dates = self::get_route_date( $post_id, $return_start );
				$dates = array_unique( is_array( $dates ) ? $dates : [] );
				usort( $dates, 'WBTM_Global_Function::sort_date' );
				foreach ( $dates as $date ) {
					$date = gmdate( 'Y-m-d', strtotime( $date ) );
					if ( strtotime( $date ) < strtotime( $base ) ) {
						continue;
					}
					$times = self::get_od_leg_datetimes( $post_id, $return_start, $return_end, $date );
					if ( ! empty( $times ) && strtotime( $times['bp_time'] ) >= $floor_ts ) {
						return $date;
					}
				}
				return $base;
			}

			/**
			 * Boarding dates from a given route definition (forward or return).
			 *
			 * @param array<int, array<string, mixed>> $route_infos_raw
			 * @return array<int, string>
			 */
			private static function collect_boarding_dates_for_route_infos( $post_id, $date_infos, $route_infos_raw, $start_route ) {
				$out = [];
				if ( ! is_array( $route_infos_raw ) || count( $route_infos_raw ) === 0 || ! $start_route ) {
					return $out;
				}
				$expanded = self::get_route_all_date_info( $post_id, $date_infos, $route_infos_raw );
				foreach ( $expanded as $route_info ) {
					if ( sizeof( $route_info ) > 0 ) {
						foreach ( $route_info as $info ) {
							if ( ( $info['type'] === 'bp' || $info['type'] === 'both' ) && strtolower( (string) $start_route ) === strtolower( (string) $info['place'] ) ) {
								$out[] = gmdate( 'Y-m-d', strtotime( $info['time'] ) );
							}
						}
					}
				}
				return $out;
			}

			public static function default_ticket_types() {
				return [
					[
						'id'    => 'adult',
						'label' => WBTM_Translations::text_adult(),
					],
					[
						'id'    => 'child',
						'label' => WBTM_Translations::text_child(),
					],
					[
						'id'    => 'infant',
						'label' => WBTM_Translations::text_infant(),
					],
				];
			}

			public static function legacy_ticket_type_aliases() {
				return [
					'adult'  => 'adult',
					'0'      => 'adult',
					0        => 'adult',
					'child'  => 'child',
					'1'      => 'child',
					1        => 'child',
					'infant' => 'infant',
					'2'      => 'infant',
					2        => 'infant',
				];
			}

			public static function generate_ticket_type_id( $raw_id = '', $label = '', $used_ids = [], $index = 0 ) {
				$ticket_type_id = sanitize_key( $raw_id );
				if ( ! $ticket_type_id && $label ) {
					$ticket_type_id = str_replace( '-', '_', sanitize_title( $label ) );
				}
				if ( ! $ticket_type_id ) {
					$ticket_type_id = 'ticket_type_' . ( absint( $index ) + 1 );
				}
				$base_ticket_type_id = $ticket_type_id;
				$suffix              = 2;
				while ( in_array( $ticket_type_id, $used_ids, true ) ) {
					$ticket_type_id = $base_ticket_type_id . '_' . $suffix;
					$suffix ++;
				}
				return $ticket_type_id;
			}

			public static function get_ticket_types( $post_id = 0 ) {
				$stored_ticket_types = $post_id > 0 ? WBTM_Global_Function::get_post_info( $post_id, 'wbtm_ticket_types', [] ) : [];
				$source_ticket_types = is_array( $stored_ticket_types ) && sizeof( $stored_ticket_types ) > 0 ? $stored_ticket_types : self::default_ticket_types();
				$ticket_types        = [];
				$used_ids            = [];

				foreach ( $source_ticket_types as $index => $ticket_type ) {
					if ( ! is_array( $ticket_type ) ) {
						$ticket_type = [
							'label' => $ticket_type,
						];
					}
					$label = array_key_exists( 'label', $ticket_type ) ? sanitize_text_field( $ticket_type['label'] ) : '';
					if ( ! $label && array_key_exists( 'name', $ticket_type ) ) {
						$label = sanitize_text_field( $ticket_type['name'] );
					}
					if ( ! $label && array_key_exists( 'id', $ticket_type ) ) {
						$label = self::get_ticket_name( $ticket_type['id'] );
					}
					if ( ! $label ) {
						continue;
					}
					$requested_id = array_key_exists( 'id', $ticket_type ) ? $ticket_type['id'] : '';
					if ( ! $requested_id && array_key_exists( 'slug', $ticket_type ) ) {
						$requested_id = $ticket_type['slug'];
					}
					$ticket_type_id = self::generate_ticket_type_id( $requested_id, $label, $used_ids, $index );
					$ticket_types[] = [
						'id'    => $ticket_type_id,
						'label' => $label,
					];
					$used_ids[] = $ticket_type_id;
				}

				return sizeof( $ticket_types ) > 0 ? $ticket_types : self::default_ticket_types();
			}

			public static function get_ticket_type_map( $post_id = 0 ) {
				$ticket_type_map = [];
				foreach ( self::get_ticket_types( $post_id ) as $ticket_type ) {
					$ticket_type_map[ $ticket_type['id'] ] = $ticket_type['label'];
				}
				return $ticket_type_map;
			}

			public static function get_ticket_price_by_type( $price_info, $ticket_type_id ) {
				$ticket_type_id = (string) $ticket_type_id;
				$dynamic_prices = array_key_exists( 'wbtm_ticket_prices', $price_info ) && is_array( $price_info['wbtm_ticket_prices'] ) ? $price_info['wbtm_ticket_prices'] : [];
				if ( array_key_exists( $ticket_type_id, $dynamic_prices ) && $dynamic_prices[ $ticket_type_id ] !== '' ) {
					return (float) $dynamic_prices[ $ticket_type_id ];
				}

				$legacy_aliases = self::legacy_ticket_type_aliases();
				$legacy_ticket  = array_key_exists( $ticket_type_id, $legacy_aliases ) ? $legacy_aliases[ $ticket_type_id ] : $ticket_type_id;
				if ( $legacy_ticket === 'adult' && array_key_exists( 'wbtm_bus_price', $price_info ) && $price_info['wbtm_bus_price'] !== '' ) {
					return (float) $price_info['wbtm_bus_price'];
				}
				if ( $legacy_ticket === 'child' && array_key_exists( 'wbtm_bus_child_price', $price_info ) && $price_info['wbtm_bus_child_price'] !== '' ) {
					return (float) $price_info['wbtm_bus_child_price'];
				}
				if ( $legacy_ticket === 'infant' && array_key_exists( 'wbtm_bus_infant_price', $price_info ) && $price_info['wbtm_bus_infant_price'] !== '' ) {
					return (float) $price_info['wbtm_bus_infant_price'];
				}
				return '';
			}

			/**
			 * Passenger types for the per-seat pricing modal: any type with at least one route fare in
			 * wbtm_bus_prices, or a non-empty saved per-seat override (so existing overrides stay editable).
			 *
			 * @param int $post_id Bus post ID.
			 * @return array<int, array{id: string, label: string}>
			 */
			public static function get_ticket_types_for_seat_price_modal( $post_id ) {
				$post_id = absint( $post_id );
				if ( ! $post_id || ! self::is_pro_active() ) {
					return [];
				}
				$all_types = self::get_ticket_types( $post_id );
				if ( empty( $all_types ) ) {
					return [];
				}
				$has_route = [];
				$price_infos = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_prices', [] );
				if ( is_array( $price_infos ) && sizeof( $price_infos ) > 0 ) {
					foreach ( $all_types as $tt ) {
						$tid = (string) $tt['id'];
						foreach ( $price_infos as $row ) {
							$p = self::get_ticket_price_by_type( $row, $tid );
							if ( $p !== '' ) {
								$has_route[ $tid ] = true;
								break;
							}
						}
					}
				}
				$has_override = [];
				$overrides    = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_price_overrides', [] );
				if ( is_array( $overrides ) && sizeof( $overrides ) > 0 ) {
					foreach ( $overrides as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						foreach ( $row as $tid => $val ) {
							if ( $val !== '' && $val !== null ) {
								$has_override[ (string) $tid ] = true;
							}
						}
					}
				}
				$out = [];
				foreach ( $all_types as $tt ) {
					$tid = (string) $tt['id'];
					if ( ! empty( $has_route[ $tid ] ) || ! empty( $has_override[ $tid ] ) ) {
						$out[] = $tt;
					}
				}
				return $out;
			}

			public static function get_legacy_price_fields( $ticket_prices = [] ) {
				return [
					'wbtm_bus_price'        => array_key_exists( 'adult', $ticket_prices ) ? $ticket_prices['adult'] : '',
					'wbtm_bus_child_price'  => array_key_exists( 'child', $ticket_prices ) ? $ticket_prices['child'] : '',
					'wbtm_bus_infant_price' => array_key_exists( 'infant', $ticket_prices ) ? $ticket_prices['infant'] : '',
				];
			}

			/**
			 * Outbound vs return fares: rows with wbtm_price_leg === 'return' are used when $price_leg is 'return'.
			 * Missing wbtm_price_leg on stored rows counts as outbound. If no return row matches, falls back to outbound.
			 *
			 * @param bool $try_reverse_return When leg is return, try swapped boarding/dropping on return-priced rows once (avoids recursion).
			 */
			public static function get_ticket_info( $post_id, $start_route, $end_route, $price_leg = 'outbound', $try_reverse_return = true ) {
				$price_leg    = self::resolve_price_leg_for_od_pair( $post_id, $start_route, $end_route, $price_leg );
				$ticket_infos = [];
				if ( $post_id && $start_route && $end_route ) {
					$price_infos  = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_prices', [] );
					$ticket_types = self::get_ticket_types( $post_id );

					if ( sizeof( $price_infos ) > 0 ) {
						foreach ( $price_infos as $price_info ) {
							$row_leg = ( isset( $price_info['wbtm_price_leg'] ) && $price_info['wbtm_price_leg'] === 'return' ) ? 'return' : 'outbound';
							if ( $row_leg !== $price_leg ) {
								continue;
							}
							if ( strtolower( (string) $price_info['wbtm_bus_bp_price_stop'] ) === strtolower( (string) $start_route ) && strtolower( (string) $price_info['wbtm_bus_dp_price_stop'] ) === strtolower( (string) $end_route ) ) {
								foreach ( $ticket_types as $ticket_type ) {
									$ticket_price = self::get_ticket_price_by_type( $price_info, $ticket_type['id'] );
									if ( $ticket_price !== '' ) {
										$ticket_infos[] = [
											'name'  => $ticket_type['label'],
											'price' => WBTM_Global_Function::get_wc_raw_price( $post_id, $ticket_price ),
											'type'  => $ticket_type['id'],
										];
									}
								}
							}
						}
					}
				}
				if ( sizeof( $ticket_infos ) === 0 && $try_reverse_return
					&& ( $price_leg === 'return' || self::is_same_bus_return_enabled( $post_id ) ) ) {
					// On a same-bus-return bus a city-pair is the same physical segment in both
					// directions, so when this direction has no fare row configured, fall back to
					// the mirror pair's fare (resolve_price_leg picks the right leg for it).
					$reverse = self::get_ticket_info( $post_id, $end_route, $start_route, $price_leg, false );
					if ( sizeof( $reverse ) > 0 ) {
						return $reverse;
					}
					if ( $price_leg !== 'outbound' ) {
						return self::get_ticket_info( $post_id, $start_route, $end_route, 'outbound', false );
					}
				}
				return $ticket_infos;
			}

			public static function get_full_bus_pricing( $post_id, $start_route, $end_route, $price_leg = 'outbound', $try_reverse_return = true ) {
				if ( ! self::is_full_bus_feature_enabled() ) {
					return [];
				}
				$price_leg = self::resolve_price_leg_for_od_pair( $post_id, $start_route, $end_route, $price_leg );
				if ( $post_id && $start_route && $end_route ) {
					$price_infos = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_prices', [] );
					if ( sizeof( $price_infos ) > 0 ) {
						foreach ( $price_infos as $price_info ) {
							$row_leg = ( isset( $price_info['wbtm_price_leg'] ) && $price_info['wbtm_price_leg'] === 'return' ) ? 'return' : 'outbound';
							if ( $row_leg !== $price_leg ) {
								continue;
							}
							if (
								strtolower( (string) $price_info['wbtm_bus_bp_price_stop'] ) === strtolower( (string) $start_route ) &&
								strtolower( (string) $price_info['wbtm_bus_dp_price_stop'] ) === strtolower( (string) $end_route ) &&
								isset( $price_info['wbtm_full_bus_price'] ) &&
								$price_info['wbtm_full_bus_price'] !== ''
							) {
								$base_price = WBTM_Global_Function::get_wc_raw_price( $post_id, (float) $price_info['wbtm_full_bus_price'] );
								$discount = self::calculate_full_bus_discount( $post_id, $base_price, isset( $price_info['wbtm_full_bus_discount'] ) ? $price_info['wbtm_full_bus_discount'] : '' );
								$discount = min( (float) $base_price, max( 0, (float) $discount ) );
								return [
									'base_price' => (float) $base_price,
									'discount'   => $discount,
									'final_price' => max( 0, (float) $base_price - $discount ),
								];
							}
						}
					}
				}
				if ( $price_leg === 'return' && $try_reverse_return ) {
					$reverse_return_price = self::get_full_bus_pricing( $post_id, $end_route, $start_route, 'return', false );
					if ( sizeof( $reverse_return_price ) > 0 ) {
						return $reverse_return_price;
					}
					return self::get_full_bus_pricing( $post_id, $start_route, $end_route, 'outbound', false );
				}
				return [];
			}

			public static function get_full_bus_price( $post_id, $start_route, $end_route, $price_leg = 'outbound', $try_reverse_return = true ) {
				$pricing = self::get_full_bus_pricing( $post_id, $start_route, $end_route, $price_leg, $try_reverse_return );
				return sizeof( $pricing ) > 0 ? $pricing['final_price'] : '';
			}

			public static function calculate_full_bus_discount( $post_id, $base_price, $discount_value ) {
				$discount_value = trim( (string) $discount_value );
				if ( $discount_value === '' ) {
					return 0;
				}
				if ( substr( $discount_value, -1 ) === '%' ) {
					$percent = max( 0, (float) str_replace( '%', '', $discount_value ) );
					$percent = min( 100, $percent );
					return ( (float) $base_price * $percent ) / 100;
				}
				return WBTM_Global_Function::get_wc_raw_price( $post_id, max( 0, (float) $discount_value ) );
			}

			public static function full_bus_booking_button( $post_id, $all_info, $date, $price_leg = 'outbound', $btn_show = '' ) {
				if ( ! self::is_full_bus_feature_enabled() ) {
					return '';
				}
				if ( ! is_array( $all_info ) || empty( $all_info['bp'] ) || empty( $all_info['dp'] ) || empty( $all_info['available_seat'] ) || empty( $all_info['total_seat'] ) ) {
					return '';
				}
				if ( (int) $all_info['available_seat'] < (int) $all_info['total_seat'] ) {
					return '';
				}
				$full_bus_pricing = self::get_full_bus_pricing( $post_id, $all_info['bp'], $all_info['dp'], $price_leg );
				$full_bus_price = sizeof( $full_bus_pricing ) > 0 ? $full_bus_pricing['final_price'] : '';
				if ( $full_bus_price === '' || (float) $full_bus_price <= 0 ) {
					return '';
				}
				ob_start();
				?>
				<div class="wbtm-full-bus-booking <?php echo esc_attr( $btn_show ); ?>">
					<button
						type="button"
						class="_themeButton_xs wbtm_full_bus_book_now"
						data-bus-id="<?php echo esc_attr( $post_id ); ?>"
						data-start-point="<?php echo esc_attr( $all_info['start_point'] ); ?>"
						data-start-time="<?php echo esc_attr( $all_info['start_time'] ); ?>"
						data-bp-place="<?php echo esc_attr( $all_info['bp'] ); ?>"
						data-bp-time="<?php echo esc_attr( $all_info['bp_time'] ); ?>"
						data-dp-place="<?php echo esc_attr( $all_info['dp'] ); ?>"
						data-dp-time="<?php echo esc_attr( $all_info['dp_time'] ); ?>"
						data-date="<?php echo esc_attr( $date ); ?>"
						data-price-leg="<?php echo esc_attr( $price_leg ); ?>"
						data-price="<?php echo esc_attr( $full_bus_price ); ?>"
						data-available-seat="<?php echo esc_attr( $all_info['available_seat'] ); ?>"
						data-form-nonce="<?php echo esc_attr( wp_create_nonce( 'wbtm_form_nonce' ) ); ?>"
						data-loading-text="<?php echo esc_attr__( 'Booking full bus...', 'bus-ticket-booking-with-seat-reservation' ); ?>"
					>
						<?php esc_html_e( 'Book Full Bus', 'bus-ticket-booking-with-seat-reservation' ); ?>
					</button>
					<div class="wbtm-full-bus-tooltip">
						<button type="button" class="wbtm-full-bus-tooltip-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Full bus price details', 'bus-ticket-booking-with-seat-reservation' ); ?>">
							<span aria-hidden="true">?</span>
						</button>
						<div class="wbtm-full-bus-tooltip-panel" role="status">
							<span><?php esc_html_e( 'Full Bus', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
							<?php if ( ! empty( $full_bus_pricing['discount'] ) ) { ?>
								<del><?php echo wp_kses_post( wc_price( $full_bus_pricing['base_price'] ) ); ?></del>
								<small><?php echo esc_html( sprintf( __( 'Discount %s', 'bus-ticket-booking-with-seat-reservation' ), wp_strip_all_tags( wc_price( $full_bus_pricing['discount'] ) ) ) ); ?></small>
							<?php } ?>
							<strong><?php echo wp_kses_post( wc_price( $full_bus_price ) ); ?></strong>
						</div>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}

			/**
			 * From booking AJAX / add-to-cart POST.
			 */
			public static function get_requested_price_leg() {
				if ( empty( $_POST['wbtm_price_leg'] ) ) {
					return 'outbound';
				}
				$leg = sanitize_text_field( wp_unslash( $_POST['wbtm_price_leg'] ) );
				return $leg === 'return' ? 'return' : 'outbound';
			}
			public static function get_ticket_name( $type = 0, $post_id = 0 ) {
				$ticket_type_map = self::get_ticket_type_map( $post_id );
				if ( array_key_exists( $type, $ticket_type_map ) ) {
					return $ticket_type_map[ $type ];
				}

				$legacy_aliases = self::legacy_ticket_type_aliases();
				$legacy_ticket  = array_key_exists( $type, $legacy_aliases ) ? $legacy_aliases[ $type ] : '';
				if ( $legacy_ticket && array_key_exists( $legacy_ticket, $ticket_type_map ) ) {
					return $ticket_type_map[ $legacy_ticket ];
				}

				if ( is_string( $type ) && $type !== '' ) {
					return ucwords( str_replace( [ '_', '-' ], ' ', $type ) );
				}
				return '';
			}
			public static function get_route_all_date_info( $post_id, $all_dates = [], $route_infos_override = null ) {
				$all_dates   = sizeof( $all_dates ) > 0 ? $all_dates : self::get_post_date( $post_id );
				$all_infos   = [];
				$route_infos = $route_infos_override !== null
					? $route_infos_override
					: WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );

				if ( sizeof( $all_dates ) > 0 ) {
					foreach ( $all_dates as $date ) {
						if ( $date ) {
							$prev_date      = $date;
							$prev_full_date = $date;
							$count          = 0;
							foreach ( $route_infos as $info ) {
								$current_date = gmdate( 'Y-m-d H:i', strtotime( $prev_date . ' ' . $info['time'] ) );
								if (isset($info['next_day']) && $info['next_day'] == '1') {
									$current_date = gmdate('Y-m-d H:i', strtotime($current_date . ' +1 day'));
								}
								if ($count > 0) {
									if ( strtotime( $prev_full_date ) > strtotime( $current_date ) ) {
										$current_date = gmdate( 'Y-m-d H:i', strtotime( $current_date . ' +1 day' ) );
									}
								}
								$info['time']    = $current_date;
								$info['next_day'] = isset($info['next_day']) ? $info['next_day'] : '0';
								$all_infos[ $date ][] = $info;
								$prev_full_date       = $current_date;
								$prev_date            = gmdate( 'Y-m-d', strtotime( $current_date ) );
								$count ++;
							}
						}
					}
				}
				return $all_infos;
		}

			public static function get_bus_all_info( $post_id, $date, $start_route, $end_route, $price_leg = 'outbound' ) {
				if ( $post_id > 0 && $date && $start_route && $end_route ) {
					$all_dates        = WBTM_Functions::get_post_date( $post_id );
					$resolved_leg     = self::resolve_route_infos_for_od_pair( $post_id, $start_route, $end_route );
					// Same-bus-return buses connect stops by position, so a boarding-only intermediate
					// stop can still serve as a drop point on the opposite-direction leg.
					$ignore_types     = self::is_same_bus_return_enabled( $post_id );
					$route_date_infos = WBTM_Functions::get_route_all_date_info( $post_id, $all_dates, $resolved_leg );
					if ( sizeof( $route_date_infos ) > 0 ) {
						$now_full = current_time( 'Y-m-d H:i' );
						foreach ( $route_date_infos as $route_info ) {
							$bp_date = '';
							if ( sizeof( $route_info ) > 0 ) {
								foreach ( $route_info as $info ) {
									if ( strtolower( (string) $start_route ) === strtolower( (string) $info['place'] ) && ( $ignore_types || $info['type'] == 'bp' || $info['type'] == 'both' ) && strtotime( $date ) == strtotime( gmdate( 'Y-m-d', strtotime( $info['time'] ) ) ) ) {
										$bp_date = $info['time'];
									}
									if ( $bp_date && strtolower( (string) $end_route ) === strtolower( (string) $info['place'] ) && ( $ignore_types || $info['type'] == 'dp' || $info['type'] == 'both' ) ) {
										$slice_time = self::slice_buffer_time( $bp_date );
										if ( strtotime( $now_full ) < strtotime( $slice_time ) ) {
											$seat_type  = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
											if ( $seat_type == 'wbtm_seat_plan' && class_exists( 'WBTM_Seat_Configuration' ) ) {
												$total_seat = WBTM_Seat_Configuration::count_actual_seats( $post_id );
											} else {
												$total_seat = (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
											}
											if ( $seat_type == 'wbtm_seat_plan' ) {
												$sold_seat = max(
													sizeof( array_unique( WBTM_Query::query_seat_booked( $post_id, $start_route, $end_route, $route_info[0]['time'] ) ) ),
													WBTM_Query::query_total_booked( $post_id, $start_route, $end_route, $route_info[0]['time'] )
												);
											} else {
												$sold_seat = WBTM_Query::query_total_booked( $post_id, $start_route, $end_route, $route_info[0]['time'] );
											}
											$available_seat = $total_seat - $sold_seat;
											return [
												'start_point'    => $route_info[0]['place'],
												'start_time'     => $route_info[0]['time'],
												'bp'             => $start_route,
												'bp_time'        => $bp_date,
												'dp'             => $end_route,
												'dp_time'        => $info['time'],
												'price'          => WBTM_Functions::get_seat_price( $post_id, $start_route, $end_route, 0, false, $price_leg ),
												'total_seat'     => $total_seat,
												'sold_seat'      => $sold_seat,
												'available_seat' => max( 0, $available_seat ),
												'regi_status'    => WBTM_Global_Function::get_post_info( $post_id, 'wbtm_registration', 0 )
											];
										}
									}
								}
							}
						}
					}
				}
				return [];
			}
			/**
			 * Get dates where the bus is fully booked (no available seats).
			 *
			 * @param int    $post_id     Bus post ID (0 for general search).
			 * @param string $start_route Starting route/boarding point.
			 * @param string $end_route   Ending route/dropping point.
			 * @return array Array of sold-out dates in Y-m-d format.
			 */
			public static function get_soldout_dates( $post_id = 0, $start_route = '', $end_route = '' ) {
				$soldout_dates = [];
				$all_dates     = self::get_all_dates( $post_id, $start_route, $end_route );

				if ( empty( $all_dates ) || ! $start_route || ! $end_route ) {
					return $soldout_dates;
				}

				// For specific bus (single bus page)
				if ( $post_id > 0 ) {
					// Batch every date's booking counts into one query up-front; the per-date
					// availability checks below then resolve from cache instead of hitting the DB.
					WBTM_Query::prime_booked_map( $post_id, $start_route, $end_route );
					foreach ( $all_dates as $date ) {
						$all_info = self::get_bus_all_info( $post_id, $date, $start_route, $end_route );
						if ( ! empty( $all_info ) && isset( $all_info['available_seat'] ) && (int) $all_info['available_seat'] <= 0 ) {
							$soldout_dates[] = $date;
						}
					}
				} else {
					// For general search (multiple buses) — a date is sold out only if ALL buses on that date are sold out
					$bus_ids = WBTM_Query::get_bus_id( $start_route, $end_route );
					if ( sizeof( $bus_ids ) > 0 ) {
						// Batch each bus's booking counts once before the date loop, so the
						// dates × buses availability checks resolve from cache (no per-cell query).
						foreach ( $bus_ids as $bus_id ) {
							WBTM_Query::prime_booked_map( $bus_id, $start_route, $end_route );
						}
						foreach ( $all_dates as $date ) {
							$all_buses_soldout = true;
							foreach ( $bus_ids as $bus_id ) {
								$bus_dates = self::get_route_date( $bus_id, $start_route );
								if ( ! in_array( $date, $bus_dates ) ) {
									continue;
								}
								$all_info = self::get_bus_all_info( $bus_id, $date, $start_route, $end_route );
								if ( empty( $all_info ) || ! isset( $all_info['available_seat'] ) || (int) $all_info['available_seat'] > 0 ) {
									$all_buses_soldout = false;
									break;
								}
							}
							if ( $all_buses_soldout ) {
								$soldout_dates[] = $date;
							}
						}
					}
				}

				return array_unique( $soldout_dates );
			}
			/**
			 * Fast sold-out date scan for the async calendar "chunk".
			 *
			 * Same result as get_soldout_dates() but built from ONE booking query per bus
			 * (future dates only) instead of two queries per date. No caching — fresh on
			 * every call — so it is safe to drive a live calendar from it.
			 */
			public static function get_soldout_dates_fast( $post_id = 0, $start_route = '', $end_route = '' ) {
				$soldout_dates = [];
				$all_dates     = self::get_all_dates( $post_id, $start_route, $end_route );
				if ( empty( $all_dates ) || ! $start_route || ! $end_route ) {
					return $soldout_dates;
				}
				$min_date = current_time( 'Y-m-d' );

				if ( $post_id > 0 ) {
					$avail = self::bus_availability_map( $post_id, $start_route, $end_route, $min_date );
					foreach ( $all_dates as $date ) {
						if ( isset( $avail[ $date ] ) && (int) $avail[ $date ] <= 0 ) {
							$soldout_dates[] = $date;
						}
					}
				} else {
					$bus_ids = WBTM_Query::get_bus_id( $start_route, $end_route );
					if ( sizeof( $bus_ids ) > 0 ) {
						$bus_avail = [];
						foreach ( $bus_ids as $bus_id ) {
							$bus_avail[ $bus_id ] = self::bus_availability_map( $bus_id, $start_route, $end_route, $min_date );
						}
						foreach ( $all_dates as $date ) {
							$all_buses_soldout = true;
							foreach ( $bus_ids as $bus_id ) {
								if ( ! in_array( $date, self::get_route_date( $bus_id, $start_route ) ) ) {
									continue;
								}
								// No booking entry for the date => seats free; otherwise check availability.
								if ( ! isset( $bus_avail[ $bus_id ][ $date ] ) || (int) $bus_avail[ $bus_id ][ $date ] > 0 ) {
									$all_buses_soldout = false;
									break;
								}
							}
							if ( $all_buses_soldout ) {
								$soldout_dates[] = $date;
							}
						}
					}
				}
				return array_unique( $soldout_dates );
			}
			/**
			 * Build a [ date => available_seat ] map for a single bus/route from a single
			 * booking query. Mirrors get_bus_all_info()'s seat-count rules exactly
			 * (seat-plan: max(distinct booked seats, total booked units); else total units).
			 * Only dates that have bookings appear in the map.
			 */
			private static function bus_availability_map( $post_id, $start, $end, $min_date ) {
				$map = [];
				$seat_type = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
				if ( $seat_type == 'wbtm_seat_plan' && class_exists( 'WBTM_Seat_Configuration' ) ) {
					$total_seat = WBTM_Seat_Configuration::count_actual_seats( $post_id );
				} else {
					$total_seat = (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
				}
				$rows = WBTM_Query::query_booking_rows_for_route( $post_id, $start, $end, $min_date );
				if ( empty( $rows ) ) {
					return $map;
				}
				$units   = [];
				$seats   = [];
				$counted = []; // booking_id => true, so a booking counts once even if meta rows duplicate
				foreach ( $rows as $row ) {
					if ( empty( $row->start_time ) ) {
						continue;
					}
					$date = gmdate( 'Y-m-d', strtotime( $row->start_time ) );
					if ( empty( $counted[ $row->booking_id ] ) ) {
						$counted[ $row->booking_id ] = true;
						$full = (int) $row->full_count;
						$units[ $date ] = ( isset( $units[ $date ] ) ? $units[ $date ] : 0 ) + ( ( $row->booking_mode === 'full_bus' && $full > 0 ) ? $full : 1 );
					}
					$seat_name = $row->seat;
					if ( class_exists( 'WBTM_Seat_Configuration' ) ) {
						$seat_name = WBTM_Seat_Configuration::normalize_saved_seat_value( $seat_name );
					}
					if ( $seat_name && ! ( class_exists( 'WBTM_Seat_Configuration' ) && WBTM_Seat_Configuration::is_non_seat_item( $seat_name ) ) ) {
						if ( ! isset( $seats[ $date ] ) ) {
							$seats[ $date ] = [];
						}
						$seats[ $date ][ $seat_name ] = true;
					}
				}
				$is_seat_plan = ( $seat_type == 'wbtm_seat_plan' );
				foreach ( $units as $date => $u ) {
					$sold = $is_seat_plan ? max( ( isset( $seats[ $date ] ) ? count( $seats[ $date ] ) : 0 ), $u ) : $u;
					$map[ $date ] = $total_seat - $sold;
				}
				return $map;
			}
			//==========================//
			public static function get_all_dates( $post_id = 0, $start_route = '' ,$end_route='') {
				$all_dates = [];
				if ( $post_id > 0 ) {
					$all_dates = self::get_route_date( $post_id, $start_route );
				} else {
					if ( $start_route ) {
						$bus_ids = WBTM_Query::get_bus_id( $start_route ,$end_route);
						if ( sizeof( $bus_ids ) > 0 ) {
							foreach ( $bus_ids as $bus_id ) {
								$dates     = self::get_route_date( $bus_id, $start_route );
								$all_dates = array_merge( $all_dates, $dates );
							}
						}
					}
				}
				$all_dates = array_unique( $all_dates );
				usort( $all_dates, "WBTM_Global_Function::sort_date" );
				return $all_dates;
			}
			public static function get_route_date( $post_id, $start_route = '' ) {
				$all_dates = [];
				if ( $post_id > 0 ) {
					$date_infos   = self::get_post_date( $post_id );
					$forward_ri   = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
					$all_dates    = array_merge(
						$all_dates,
						self::collect_boarding_dates_for_route_infos( $post_id, $date_infos, $forward_ri, $start_route )
					);
					if ( self::is_same_bus_return_enabled( $post_id ) && $start_route ) {
						foreach ( self::get_same_bus_return_route_candidates( $post_id, $forward_ri ) as $return_ri ) {
							if ( self::wbtm_route_infos_has_boarding_place( $return_ri, $start_route ) ) {
								$all_dates = array_merge(
									$all_dates,
									self::collect_boarding_dates_for_route_infos( $post_id, $date_infos, $return_ri, $start_route )
								);
							}
						}
					}
					if ( ! $start_route && sizeof( $all_dates ) === 0 ) {
						$route_infos = WBTM_Functions::get_route_all_date_info( $post_id, $date_infos );
						if ( sizeof( $route_infos ) > 0 ) {
							foreach ( $route_infos as $route_info ) {
								if ( sizeof( $route_info ) > 0 ) {
									foreach ( $route_info as $info ) {
										if ( $info['type'] == 'bp' || $info['type'] == 'both' ) {
											$all_dates[] = gmdate( 'Y-m-d', strtotime( $info['time'] ) );
										}
									}
								}
							}
						}
					}
				}
				return array_unique( $all_dates );
			}
			public static function get_post_date( $post_id ) {
                $all_dates = [];
                if ( $post_id > 0 ) {
                    $show_on_dates = WBTM_Global_Function::get_post_info( $post_id, 'show_operational_on_day', 'no' );
                    $now           = current_time( 'Y-m-d' );
                    $year          = current_time( 'Y' );

                if ( $show_on_dates == 'yes' ) {
                    $on_dates = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_particular_dates', array() );
                    if ( ! empty( $on_dates ) ) {
                        foreach ( $on_dates as $on_date ) {
                            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $on_date ) ) {
                                $date_item = $on_date;
                            } else {
                                $date_item = gmdate( 'Y-m-d', strtotime( $year . '-' . $on_date ) );
                            }
                            if ( strtotime( $date_item ) < strtotime( $now ) ) {
                                $date_item = gmdate( 'Y-m-d', strtotime( ($year + 1) . '-' . $on_date ) );
                            }
                            if ( strtotime( $date_item ) >= strtotime( $now ) ) {
                                $all_dates[] = $date_item;
                            }
                        }
                    }
                } else {
                    // Handling of regular operational dates without specific operational days
                    $sale_end_date = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_repeated_end_date' ) ?: WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'ticket_sale_close_date' );
                    $sale_end_date = $sale_end_date ? gmdate( 'Y-m-d', strtotime( $sale_end_date ) ) : '';
                    $active_days   = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_active_days' ) ?: WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'ticket_sale_max_date', 30 );
                    $start_date    = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_repeated_start_date', $now );
                    if ( strtotime( $now ) >= strtotime( $start_date ) ) {
                        $start_date = $now;
                    }
                    $end_date = gmdate( 'Y-m-d', strtotime( $start_date . ' +' . $active_days . ' day' ) );

                    if ( $sale_end_date && strtotime( $sale_end_date ) < strtotime( $end_date ) ) {
                        $end_date = $sale_end_date;
                    }

                    if ( strtotime( $start_date ) < strtotime( $end_date ) ) {
                        $off_dates = [];

                        // Process defined off day ranges
                        $off_day_ranges = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_offday_range', array() );
                        if ( sizeof( $off_day_ranges ) ) {
                            foreach ( $off_day_ranges as $off_day_range ) {
                                if ( isset( $off_day_range['from_date'] ) && isset( $off_day_range['to_date'] ) ) {
                                    $from_date = gmdate( 'Y-m-d', strtotime( $off_day_range['from_date'] ) );
                                    $to_date   = gmdate( 'Y-m-d', strtotime( $off_day_range['to_date'] ) );

                                    // Collect all off dates within this range
                                    $off_date_lists = WBTM_Global_Function::date_separate_period( $from_date, $to_date );
                                    foreach ( $off_date_lists as $off_date_list ) {
                                        $off_dates[] = $off_date_list->format( 'Y-m-d' );
                                    }
                                }
                            }
                        }

                        // Unique off dates generated from the ranges
                        $off_dates = array_unique( $off_dates );

                            $particular_off_dates = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_off_dates', array() );
                            if ( sizeof( $particular_off_dates ) > 0 ) {
                                foreach ( $particular_off_dates as $particular_off_date ) {
                                    // Check if the date is already in 'Y-m-d' format
                                    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $particular_off_date ) ) {
                                        $processed_date = $particular_off_date;
                                    } else {
                                        // Assume date is in 'MM-DD' format, prepend year
                                        $processed_date = gmdate( 'Y-m-d', strtotime( $year . '-' . $particular_off_date ) );
                                        // Move to next year if the date is in the past
                                        if ( strtotime( $processed_date ) < strtotime( $now ) ) {
                                            $processed_date = gmdate( 'Y-m-d', strtotime( ($year + 1) . '-' . $particular_off_date ) );
                                        }
                                    }
                                    $off_dates[] = $processed_date;
                                }
                            }

                            // Remove duplicates from the off dates array
                            $off_dates = array_unique( $off_dates );
                            $off_days      = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_off_days' );
                            $off_day_array = $off_days ? explode( ',', $off_days ) : [];
                            $repeat        = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_repeated_after', 1 );

                            // Generate the date range
                            $dates = WBTM_Global_Function::date_separate_period( $start_date, $end_date, $repeat );
                            foreach ( $dates as $date ) {
                                $date = $date->format( 'Y-m-d' );
                                if ( strtotime( $date ) >= strtotime( $now ) ) {
                                    $day = strtolower( gmdate( 'l', strtotime( $date ) ) ); // Get the day of the week
                                    // Add date if it is not an off date and not an off day
                                    if ( ! in_array( $date, $off_dates ) && ! in_array( $day, $off_day_array ) ) {
                                        $all_dates[] = $date;
                                    }
                                }
                            }
                        }
                    }
                }
                return array_unique( $all_dates ); // Return unique available dates
            }

			public static function slice_buffer_time( $date ) {
				$buffer_time = WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'bus_buffer_time', 0 ) * 60;
				if ( $buffer_time > 0 ) {
					$date = gmdate( 'Y-m-d H:i', strtotime( $date ) - $buffer_time );
				}
				return $date;
			}
			//==========================//
			/**
			 * Storage key for per-seat ticket price overrides (post meta wbtm_seat_price_overrides).
			 * Lower deck: l|{seatLabel}, upper: u|{seatLabel}, cabin: c|{index}|{seatLabel}.
			 *
			 * @param string       $seat_name     Seat label (e.g. A1).
			 * @param bool         $is_upper_deck Upper deck leg uses u| prefix when $cabin_index is null.
			 * @param int|null     $cabin_index   Null for lower/upper; integer cabin index for cabin coaches.
			 */
			public static function seat_price_override_storage_key( $seat_name, $is_upper_deck, $cabin_index ) {
				$seat_name = trim( (string) $seat_name );
				if ( $seat_name === '' ) {
					return '';
				}
				if ( null !== $cabin_index && $cabin_index !== '' ) {
					return 'c|' . (int) $cabin_index . '|' . $seat_name;
				}
				return ( $is_upper_deck ? 'u' : 'l' ) . '|' . $seat_name;
			}

			/**
			 * @param string $storage_key From seat_price_override_storage_key().
			 * @param string $ticket_type_id Ticket type id (slug) as used in wbtm_ticket_types.
			 * @return float|null Override amount in store raw price units, or null to use route fare.
			 */
			public static function get_seat_price_override_raw( $post_id, $storage_key, $ticket_type_id ) {
				if ( ! $post_id || $storage_key === '' || ! self::is_pro_active() ) {
					return null;
				}
				if ( class_exists( 'WBTM_Seat_Configuration' ) && ! WBTM_Seat_Configuration::is_seat_price_override_enabled( $post_id ) ) {
					return null;
				}
				$map = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_price_overrides', [] );
				if ( ! is_array( $map ) || ! isset( $map[ $storage_key ] ) || ! is_array( $map[ $storage_key ] ) ) {
					return null;
				}
				$row  = $map[ $storage_key ];
				$tkey = (string) $ticket_type_id;
				if ( ! isset( $row[ $tkey ] ) || $row[ $tkey ] === '' || $row[ $tkey ] === null ) {
					return null;
				}
				return max( 0, (float) $row[ $tkey ] );
			}

			public static function get_seat_price( $post_id, $start_route, $end_route, $seat_type = 0, $dd = false, $price_leg = 'outbound', $seat_name = '', $cabin_index = null ) {
				if ( $post_id && $start_route && $end_route ) {
					$ticket_infos = self::get_ticket_info( $post_id, $start_route, $end_route, $price_leg );
					if ( sizeof( $ticket_infos ) > 0 ) {
						$requested_seat_type = (string) $seat_type;
						if ( $requested_seat_type === '' || $requested_seat_type === '0' ) {
							$requested_seat_type = (string) $ticket_infos[0]['type'];
						}
						foreach ( $ticket_infos as $ticket_info ) {
							if ( (string) $ticket_info['type'] === $requested_seat_type ) {
								$price          = $ticket_info['price'];
								$key            = self::seat_price_override_storage_key( $seat_name, (bool) $dd, $cabin_index );
								$override_price = self::get_seat_price_override_raw( $post_id, $key, $requested_seat_type );
								if ( null !== $override_price ) {
									$price = $override_price;
								}
								$seat_plan_type = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
								if ( $seat_plan_type == 'wbtm_seat_plan' && $dd ) {
									$seat_dd_increase = (int) WBTM_Global_Function::get_post_info( $post_id, 'wbtm_seat_dd_price_parcent', 0 );
									$price            = $price + ( $price * $seat_dd_increase / 100 );
								}
								return $price;
							}
						}
					}
				}
				return false;
			}
			public static function get_ex_service_price( $post_id, $service_name ) {
				$show_extra_service = WBTM_Global_Function::get_post_info( $post_id, 'show_extra_service', 'no' );
				if ( $show_extra_service == 'yes' ) {
					$ex_services = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_extra_services', [] );
					if ( sizeof( $ex_services ) > 0 ) {
						foreach ( $ex_services as $ex_service ) {
							if ( $ex_service['option_name'] == $service_name ) {
								$price = max( 0, $ex_service['option_price'] );
								$price = WBTM_Global_Function::wc_price( $post_id, $price );
								return WBTM_Global_Function::price_convert_raw( $price );
							}
						}
					}
				}
				return false;
			}
			//==========================//
			public static function check_seat_in_cart( $bus_id, $bp, $dp, $bp_date, $seat_name ) {
				$cart_items = WC()->cart->get_cart();
				if ( sizeof( $cart_items ) > 0 ) {
					foreach ( $cart_items as $cart_item ) {
						$cart_bus_id = array_key_exists( 'wbtm_bus_id', $cart_item ) ? $cart_item['wbtm_bus_id'] : '';
						$cart_bp     = array_key_exists( 'wbtm_bp_place', $cart_item ) ? $cart_item['wbtm_bp_place'] : '';
						$cart_dp     = array_key_exists( 'wbtm_dp_place', $cart_item ) ? $cart_item['wbtm_dp_place'] : '';
						$cart_date   = array_key_exists( 'wbtm_bp_time', $cart_item ) ? $cart_item['wbtm_bp_time'] : '';
						$cart_date   = $cart_date ? gmdate( 'Y-m-d', strtotime( $cart_date ) ) : '';
						$bp_date     = $bp_date ? gmdate( 'Y-m-d', strtotime( $bp_date ) ) : '';
						if ( $cart_bus_id == $bus_id && $cart_bp == $bp && $cart_dp == $dp && strtotime( $cart_date ) == strtotime( $bp_date ) ) {
							$cart_seat_infos = array_key_exists( 'wbtm_seats', $cart_item ) ? $cart_item['wbtm_seats'] : '';
							$cabin_seat_infos = array_key_exists( 'wbtm_cabin_seats', $cart_item ) ? $cart_item['wbtm_cabin_seats'] : '';

							// Check regular seats
							if ( is_array( $cart_seat_infos ) && sizeof( $cart_seat_infos ) > 0 ) {
								foreach ( $cart_seat_infos as $seat_info ) {
									if ( array_key_exists( 'seat_name', $seat_info ) && $seat_info['seat_name'] == $seat_name ) {
										return true;
									}
								}
							}

							// Check cabin seats
							if ( is_array( $cabin_seat_infos ) && sizeof( $cabin_seat_infos ) > 0 ) {
								// Enhanced by Shahnur Alam - 2025-10-08
								// Fix cabin seat cart check - use cabin-specific identifiers
								foreach ( $cabin_seat_infos as $seat_info ) {
									$cart_seat_name = array_key_exists( 'seat_name', $seat_info ) ? $seat_info['seat_name'] : '';
									$cabin_index = array_key_exists( 'cabin_index', $seat_info ) ? $seat_info['cabin_index'] : '';
													
									// Create cabin-specific identifier for comparison
									$cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $cart_seat_name;
													
									if ( $cabin_seat_identifier == $seat_name ) {
										return true;
									}
								}
							}
						}
					}
				}
				return false;
			}
			//==========================//
			public static function get_order_status_text( $key ) {
				$status = array(
					'1' => 'processing',
					'2' => 'completed',
					'3' => 'pending',
					'4' => 'on-hold',
					'5' => 'canceled',
				);
				return array_key_exists( $key, $status ) ? $status[ $key ] : $key;
			}
			public static function week_day_num_to_text( $key ) {
				$day = array(
					'0' => 'sunday',
					'1' => 'monday',
					'2' => 'tuesday',
					'3' => 'wednesday',
					'4' => 'thursday',
					'5' => 'friday',
					'6' => 'saturday',
				);
				return array_key_exists( $key, $day ) ? $day[ $key ] : $key;
			}
			//==========================//
			public static function wbtm_get_user_role( $user_ID ) {
				global $wp_roles;
				$user_role_list = '';
				$user_data      = get_userdata( $user_ID );
				$user_role_slug = $user_data->roles;
				if ( is_array( $user_role_slug ) && sizeof( $user_role_slug ) > 0 ) {
					$user_role_nr = 0;
					foreach ( $user_role_slug as $user_role ) {
						$user_role_nr ++;
						if ( $user_role_nr > 1 ) {
							$user_role_list .= ", ";
						}
						$user_role_list .= translate_user_role( $wp_roles->roles[ $user_role ]['name'] );
					}
				}
				return $user_role_list;
			}
			//==========================//
			public static function get_cpt(): string {
				return 'wbtm_bus';
			}
			public static function get_name() {
				return WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'label', esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ) );
			}
			public static function get_slug() {
				return WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'bus', 'bus' );
			}
			public static function get_icon() {
				$icon = WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'icon', 'fas fa-bus' );
				if ( $icon ) {
					// If it's a dashicons class, return as-is for WP menu
					if ( strpos( $icon, 'dashicons' ) === 0 ) {
						return $icon;
					}
					// If it's a FontAwesome class (fa, fas, far, fab), convert to SVG data URI for WP menu
					if ( preg_match( '/^(fa[srlbdt]?)\s+fa-/', $icon ) ) {
						$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M5 2c-1.7 0-3 1.3-3 3v7c0 1.1.9 2 2 2v2h2v-2h8v2h2v-2c1.1 0 2-.9 2-2V5c0-1.7-1.3-3-3-3H5zm0 2h10c.6 0 1 .4 1 1v4H4V5c0-.6.4-1 1-1zm0 7a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm10 0a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM5 6h2v2H5V6zm4 0h6v2H9V6z"/></svg>';
						return 'data:image/svg+xml;base64,' . base64_encode( $svg );
					}
					// Otherwise return as-is (could be a data URI or URL)
					return $icon;
				}
				// Fallback SVG bus icon
				$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M5 2c-1.7 0-3 1.3-3 3v7c0 1.1.9 2 2 2v2h2v-2h8v2h2v-2c1.1 0 2-.9 2-2V5c0-1.7-1.3-3-3-3H5zm0 2h10c.6 0 1 .4 1 1v4H4V5c0-.6.4-1 1-1zm0 7a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm10 0a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM5 6h2v2H5V6zm4 0h6v2H9V6z"/></svg>';
				return 'data:image/svg+xml;base64,' . base64_encode( $svg );
			}

            public static function wbtm_left_filter_disppaly( $bus_types, $bus_titles, $start_routes, $filter_by_box, $left_filter_show ): void {
                ?>
                <div id="wbtm_bus_filter-options">
                    <div class="wbtm_left_filter_title_holder">
                        <div class="wbtm_bus_filter_title">
                            <span class="wbtm_bus_filter_title_text"><?php echo esc_html(WBTM_Translations::text_filter()); ?></span>
                        </div>
                        <div class="wbtm_bus_filter_reset">
                            <span class="wbtm_bus_filter_reset_text wbtm_reset_<?php echo esc_attr( $filter_by_box ) ?>"><?php echo esc_html(WBTM_Translations::text_reset()); ?></span>
                        </div>
                    </div>
                    <div class="wbtm_left_filter_element_holder">
                        <?php if( $left_filter_show['left_filter_type'] === 'on' && !empty( $bus_types ) ) {?>
                        <div class="wbtm_bus_filter_items">
                                <span class="wbtm_bus_toggle-header"><?php echo esc_html(WBTM_Translations::text_bus_type()); ?> <span class="wbtm_bus_toggle-icon"></span></span>
                                <?php
                                // Get global list of bus types from taxonomy
                                $bus_cat_terms = get_terms(array(
                                    'taxonomy' => 'wbtm_bus_cat',
                                    'hide_empty' => false,
                                ));
                                
                                $unique_bus_types = array();
                                if (!is_wp_error($bus_cat_terms) && !empty($bus_cat_terms)) {
                                    foreach ($bus_cat_terms as $term) {
                                        $unique_bus_types[] = $term->name;
                                    }
                                }
                                
                                // If no terms found, fall back to the passed array
                                if (empty($unique_bus_types)) {
                                    $unique_bus_types = array_unique($bus_types);
                                }
                                
                                foreach ( $unique_bus_types as $bus_type) {
                                    if( !empty( $bus_type ) ){
                                    ?>
                                    <div class="wbtm_bus_left_filter_checkbox_holder">
                                        <input type="checkbox" class="<?php echo esc_attr( $filter_by_box );?>" data-filter="wbtm_bus_type" value="<?php echo esc_attr( $bus_type );?>">
                                        <span><?php echo esc_attr( $bus_type );?></span>
                                    </div>
                                <?php } }?>
                            </div>
                        <?php }
						
						
                        if( $left_filter_show['left_filter_operator'] === 'on' && !empty( $bus_titles ) ) { ?>
                        <div class="wbtm_bus_filter_items">
                            <span class="wbtm_bus_toggle-header"><?php echo esc_html(WBTM_Translations::text_bus_operator()); ?> <span class="wbtm_bus_toggle-icon"></span></span>
                            <?php
                            $search_bus_titles = array_unique( $bus_titles );
                            foreach ( $search_bus_titles as $bus_title ) {
                                if( !empty( $bus_title ) ){
                                ?>
                                <div class="wbtm_bus_left_filter_checkbox_holder">
                                    <input type="checkbox" class="<?php echo esc_attr( $filter_by_box );?>" data-filter="wbtm_bus_name" value="<?php echo esc_attr( $bus_title ); ?>">
                                    <span><?php echo esc_attr( $bus_title );?></span>
                                </div>
                            <?php } }?>
                        </div>
                        <?php }
						
                        if( $left_filter_show['left_filter_boarding'] === 'on' && is_array( $start_routes ) && count( $start_routes ) >0 ){
                        ?>
                        <div class="wbtm_bus_filter_items">
                            <span class="wbtm_bus_toggle-header"><?php echo esc_html(WBTM_Translations::text_boarding_point()); ?> <span class="wbtm_bus_toggle-icon"></span></span>
                            <?php  foreach ( $start_routes as $route ){?>
                                <div class="wbtm_bus_left_filter_checkbox_holder">
                                    <input type="checkbox" class="<?php echo esc_attr( $filter_by_box );?>" data-filter="wbtm_bus_start_route" value="<?php echo esc_attr($route); ?>">
                                    <span><?php echo esc_attr($route); ?></span>
                                </div>
                            <?php }?>
                        </div>
                        <?php }?>
                    </div>
                </div>
                <?php
            }
            
            /**
             * Synchronize bus type between taxonomy and post meta
             * 
             * This function ensures that the bus type stored in post meta (wbtm_bus_category)
             * is consistent with the taxonomy terms (wbtm_bus_cat).
             * 
             * @param int $post_id The bus post ID
             * @return string The synchronized bus type
             */
            public static function synchronize_bus_type($post_id) {
                // Get the current bus type from post meta
                $meta_bus_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_category', '');
                
                // Get the taxonomies for this bus
                $terms = get_the_terms($post_id, 'wbtm_bus_cat');
                
                if (is_array($terms) && !empty($terms)) {
                    // Get the first term name
                    $term_name = WBTM_Global_Function::data_sanitize($terms[0]->name);
                    
                    // If the post meta doesn't match the taxonomy term, update it
                    if ($meta_bus_type !== $term_name) {
                        update_post_meta($post_id, 'wbtm_bus_category', $term_name);
                        
                        // Also update the term_order to ensure this term is always first
                        if (count($terms) > 1) {
                            // Store all term IDs to maintain all associations
                            $term_ids = array_map(function($term) {
                                return $term->term_id;
                            }, $terms);
                            
                            // First remove all terms
                            wp_set_object_terms($post_id, array(), 'wbtm_bus_cat');
                            
                            // Then add them back with the first term first
                            wp_set_object_terms($post_id, $term_ids, 'wbtm_bus_cat', false);
                        }
                        
                        return $term_name;
                    }
                    
                    return $meta_bus_type;
                } elseif (!empty($meta_bus_type)) {
                    // If we have a meta bus type but no taxonomy terms, create the term
                    $term = term_exists($meta_bus_type, 'wbtm_bus_cat');
                    if (!$term) {
                        $term = wp_insert_term($meta_bus_type, 'wbtm_bus_cat');
                    }
                    
                    if (!is_wp_error($term)) {
                        // Get the term ID
                        $term_id = is_array($term) ? $term['term_id'] : $term;
                        
                        // Set the term for the post
                        wp_set_object_terms($post_id, intval($term_id), 'wbtm_bus_cat', false);
                    }
                    
                    return $meta_bus_type;
                }
                
                return '';
            }

			public static function logo_thumbnail_display($bus_id){
				$bus_logo = get_post_meta( $bus_id, 'wbtm_bus_logo', true );
				$thumbnail = get_the_post_thumbnail_url( $bus_id );
				$bus_logo = !empty($bus_logo)?wp_get_attachment_url( $bus_logo):$thumbnail;
				$default_logo = WBTM_PLUGIN_URL . '/assets/images/bus-logo.svg';
				?>
				<img src="<?php echo esc_url($bus_logo); ?>" onerror="this.onerror=null; this.src='<?php echo esc_url($default_logo); ?>';">
				<?php
			}
			public static function single_bus_details_tabs( $bus_id) {
				$tabs = [
					'wbtm_bus_details'           => __( 'Bus Details', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_bus_boarding_dropping' => __( 'Boarding/Dropping Points', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_bus_feature'           => __( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_bus_term_condition'    => __( 'Term & Conditions', 'bus-ticket-booking-with-seat-reservation' ),
					'wbtm_bus_image'             => __( 'Bus Photo', 'bus-ticket-booking-with-seat-reservation' ),
				];
				return apply_filters( 'wbtm_single_bus_details_tabs', $tabs, $bus_id );
			}
			public static function single_bus_details_tabs_filtered( $bus_id ) {

				$tabs = WBTM_Functions::single_bus_details_tabs( $bus_id );
				$boarding_routes  = WBTM_Functions::get_bus_route( $bus_id );
				$feature_ids = get_post_meta( $bus_id, 'wbbm_bus_features_term_id', true );
				$term_condition = get_post_meta( $bus_id, 'wbtm_term_condition_list', true );
				$gallery_images       = get_post_meta( $bus_id, 'wbtm_gallery_images', true );

				if ( empty( $boarding_routes ) ) {
					unset( $tabs['wbtm_bus_boarding_dropping'] );
				}
				if ( empty( $feature_ids ) ) {
					unset( $tabs['wbtm_bus_feature'] );
				}
				if ( empty( $term_condition ) ) {
					unset( $tabs['wbtm_bus_term_condition'] );
				}
				if ( empty( $gallery_images ) ) {
					unset( $tabs['wbtm_bus_image'] );
				}
				return $tabs;
			}


            public static function single_bus_details_popup_tabs( $bus_id, $popup_tabs ) {
                ob_start(); 
			?>
                <div class="wbtm_bus_popup_links">
					<?php foreach ( $popup_tabs as $key => $tab ):?>
						<span class="wbtm_bus_popup_link" id="<?php echo esc_attr( $key );?>"  data-post-id="<?php echo esc_attr( $bus_id ); ?>"><?php echo esc_html( $tab );?></span>
					<?php endforeach ?>
                </div>
            <?php
                return ob_get_clean();
            }

            public static function getSelectedFeatures( $all_features, $selected_ids ) {

                $selected_features = [];
                if( is_array( $all_features ) && !empty( $all_features ) && is_array( $selected_ids ) && !empty( $selected_ids ) ){
                    $selected_features = array_filter($all_features, function($feature) use ($selected_ids) {
                        return in_array($feature['term_id'], $selected_ids);
                    });
                }

                // Reindex array
                return array_values($selected_features);
            }

		}
	}
