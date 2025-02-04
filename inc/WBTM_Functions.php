<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Functions' ) ) {
		class WBTM_Functions {
			public static function template_path( $file_name ): string {
				$template_path = get_stylesheet_directory() . '/templates/';
				$default_dir   = WBTM_PLUGIN_DIR . '/templates/';
				$dir           = is_dir( $template_path ) ? $template_path : $default_dir;
				$file_path     = $dir . $file_name;
				return locate_template( array( 'templates/' . $file_name ) ) ? $file_path : $default_dir . $file_name;
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
						$bus_ids = MP_Global_Function::get_all_post_id( WBTM_Functions::get_cpt() );
						if ( sizeof( $bus_ids ) > 0 ) {
							foreach ( $bus_ids as $bus_id ) {
								$routes     = MP_Global_Function::get_post_info( $bus_id, 'wbtm_bus_bp_stops', [] );
								$all_routes = array_merge( $all_routes, $routes );
							}
						}
					}
				}
				return array_unique( $all_routes );
			}
			public static function single_bus_route( $post_id, $start_route = '' ) {
				$all_routes       = [];
				$count_next       = 0;
				$full_route_infos = MP_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
				if ( sizeof( $full_route_infos ) > 0 ) {
					foreach ( $full_route_infos as $info ) {
						if ( $start_route ) {
							if ( $count_next > 0 && ( $info['type'] == 'dp' || $info['type'] == 'both' ) ) {
								$all_routes[] = $info['place'];
							}
							if ( ( $info['type'] == 'bp' || $info['type'] == 'both' ) && strtolower( $info['place'] ) == strtolower( $start_route ) ) {
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
			public static function get_ticket_info( $post_id, $start_route, $end_route ) {
				$ticket_infos = [];
				if ( $post_id && $start_route && $end_route ) {
					$price_infos = MP_Global_Function::get_post_info( $post_id, 'wbtm_bus_prices', [] );
					if ( sizeof( $price_infos ) > 0 ) {
						foreach ( $price_infos as $price_info ) {
							if ( strtolower( $price_info['wbtm_bus_bp_price_stop'] ) == strtolower( $start_route ) && strtolower( $price_info['wbtm_bus_dp_price_stop'] ) == strtolower( $end_route ) ) {
								$adult_price  = array_key_exists( 'wbtm_bus_price', $price_info ) && $price_info['wbtm_bus_price'] ? (float) $price_info['wbtm_bus_price'] : '';
								$child_price  = array_key_exists( 'wbtm_bus_child_price', $price_info ) && $price_info['wbtm_bus_child_price'] ? (float) $price_info['wbtm_bus_child_price'] : '';
								$infant_price = array_key_exists( 'wbtm_bus_infant_price', $price_info ) && $price_info['wbtm_bus_infant_price'] ? (float) $price_info['wbtm_bus_infant_price'] : '';
								if ( $adult_price && (float) $adult_price >= 0 ) {
									$ticket_infos[] = [
										'name'  => WBTM_Translations::text_adult(),
										'price' => MP_Global_Function::get_wc_raw_price( $post_id, $adult_price ),
										'type'  => 0
									];
								}
								if ( $child_price && (float) $child_price >= 0 ) {
									$ticket_infos[] = [
										'name'  => WBTM_Translations::text_child(),
										'price' => MP_Global_Function::get_wc_raw_price( $post_id, $child_price ),
										'type'  => 1
									];
								}
								if ( $infant_price && (float) $infant_price >= 0 ) {
									$ticket_infos[] = [
										'name'  => WBTM_Translations::text_infant(),
										'price' => MP_Global_Function::get_wc_raw_price( $post_id, $infant_price ),
										'type'  => 2
									];
								}
							}
						}
					}
				}
				return $ticket_infos;
			}
			public static function get_ticket_name( $type = 0 ) {
				$ticket[0] = WBTM_Translations::text_adult();
				$ticket[1] = WBTM_Translations::text_child();
				$ticket[2] = WBTM_Translations::text_infant();
				return $ticket[ $type ];
			}
			public static function get_route_all_date_info( $post_id, $all_dates = [] ) {
				$all_dates   = sizeof( $all_dates ) > 0 ? $all_dates : self::get_post_date( $post_id );
				$all_infos   = [];
				$route_infos = MP_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
				
				if ( sizeof( $all_dates ) > 0 ) {
					foreach ( $all_dates as $date ) {
						if ( $date ) {
							$prev_date      = $date;
							$prev_full_date = $date;
							$count          = 0;
							foreach ( $route_infos as $info ) {
								$current_date = date( 'Y-m-d H:i', strtotime( $prev_date . ' ' . $info['time'] ) );
								if (isset($info['next_day']) && $info['next_day'] == '1') {
									$current_date = date('Y-m-d H:i', strtotime($current_date . ' +1 day'));
								}
								if ($count > 0) {
									if ( strtotime( $prev_full_date ) > strtotime( $current_date ) ) {
										$current_date = date( 'Y-m-d H:i', strtotime( $current_date . ' +1 day' ) );
									}
								}
								$info['time']    = $current_date;
								$info['next_day'] = isset($info['next_day']) ? $info['next_day'] : '0';
								$all_infos[ $date ][] = $info;
								$prev_full_date       = $current_date;
								$prev_date            = date( 'Y-m-d', strtotime( $current_date ) );
								$count ++;
							}
						}
					}
				}
				return $all_infos;
		}

			public static function get_bus_all_info( $post_id, $date, $start_route, $end_route ) {
				if ( $post_id > 0 && $date && $start_route && $end_route ) {
					$all_dates   = WBTM_Functions::get_post_date( $post_id );
					$route_infos = WBTM_Functions::get_route_all_date_info( $post_id, $all_dates );
					if ( sizeof( $route_infos ) > 0 ) {
						$now_full = current_time( 'Y-m-d H:i' );
						foreach ( $route_infos as $route_info ) {
							$bp_date = '';
							if ( sizeof( $route_info ) > 0 ) {
								foreach ( $route_info as $info ) {
									if ( strtolower( $start_route ) == strtolower( $info['place'] ) && ( $info['type'] == 'bp' || $info['type'] == 'both' ) && strtotime( $date ) == strtotime( date( 'Y-m-d', strtotime( $info['time'] ) ) ) ) {
										$bp_date = $info['time'];
									}
									if ( $bp_date && strtolower( $end_route ) == strtolower( $info['place'] ) && ( $info['type'] == 'dp' || $info['type'] == 'both' ) ) {
										$slice_time = self::slice_buffer_time( $bp_date );
										if ( strtotime( $now_full ) < strtotime( $slice_time ) ) {
											$total_seat = MP_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 );
											$seat_type  = MP_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
											if ( $seat_type == 'wbtm_seat_plan' ) {
												$sold_seat = sizeof( array_unique( WBTM_Query::query_seat_booked( $post_id, $start_route, $end_route, $route_info[0]['time'] ) ) );
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
												'price'          => WBTM_Functions::get_seat_price( $post_id, $start_route, $end_route ),
												'total_seat'     => $total_seat,
												'sold_seat'      => $sold_seat,
												'available_seat' => max( 0, $available_seat ),
												'regi_status'    => MP_Global_Function::get_post_info( $post_id, 'wbtm_registration', 0 )
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
				usort( $all_dates, "MP_Global_Function::sort_date" );
				return $all_dates;
			}
			public static function get_route_date( $post_id, $start_route = '' ) {
				$all_dates = [];
				if ( $post_id > 0 ) {
					$date_infos  = self::get_post_date( $post_id );
					$route_infos = WBTM_Functions::get_route_all_date_info( $post_id, $date_infos );
					if ( sizeof( $route_infos ) > 0 ) {
						foreach ( $route_infos as $route_info ) {
							if ( sizeof( $route_info ) > 0 ) {
								foreach ( $route_info as $info ) {
									if ( $info['type'] == 'bp' || $info['type'] == 'both' ) {
										if ( $start_route ) {
											if ( $start_route == $info['place'] ) {
												$all_dates[] = date( 'Y-m-d', strtotime( $info['time'] ) );
											}
										} else {
											$all_dates[] = date( 'Y-m-d', strtotime( $info['time'] ) );
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
                    $show_on_dates = MP_Global_Function::get_post_info( $post_id, 'show_operational_on_day', 'no' );
                    $now           = current_time( 'Y-m-d' );
                    $year          = current_time( 'Y' );

                if ( $show_on_dates == 'yes' ) {
                    $on_dates = MP_Global_Function::get_post_info( $post_id, 'wbtm_particular_dates', array() );
                    if ( ! empty( $on_dates ) ) {
                        foreach ( $on_dates as $on_date ) {
                            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $on_date ) ) {
                                $date_item = $on_date;
                            } else {
                                $date_item = date( 'Y-m-d', strtotime( $year . '-' . $on_date ) );
                            }
                            if ( strtotime( $date_item ) < strtotime( $now ) ) {
                                $date_item = date( 'Y-m-d', strtotime( ($year + 1) . '-' . $on_date ) );
                            }
                            if ( strtotime( $date_item ) >= strtotime( $now ) ) {
                                $all_dates[] = $date_item;
                            }
                        }
                    }
                } else {
                    // Handling of regular operational dates without specific operational days
                    $sale_end_date = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_end_date' ) ?: MP_Global_Function::get_settings( 'wbtm_general_settings', 'ticket_sale_close_date' );
                    $sale_end_date = $sale_end_date ? date( 'Y-m-d', strtotime( $sale_end_date ) ) : '';
                    $active_days   = MP_Global_Function::get_post_info( $post_id, 'wbtm_active_days' ) ?: MP_Global_Function::get_settings( 'wbtm_general_settings', 'ticket_sale_max_date', 30 );
                    $start_date    = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_start_date', $now );
                    if ( strtotime( $now ) >= strtotime( $start_date ) ) {
                        $start_date = $now;
                    }
                    $end_date = date( 'Y-m-d', strtotime( $start_date . ' +' . $active_days . ' day' ) );

                    if ( $sale_end_date && strtotime( $sale_end_date ) < strtotime( $end_date ) ) {
                        $end_date = $sale_end_date;
                    }

                    if ( strtotime( $start_date ) < strtotime( $end_date ) ) {
                        $off_dates = [];

                        // Process defined off day ranges
                        $off_day_ranges = MP_Global_Function::get_post_info( $post_id, 'wbtm_offday_range', array() );
                        if ( sizeof( $off_day_ranges ) ) {
                            foreach ( $off_day_ranges as $off_day_range ) {
                                if ( isset( $off_day_range['from_date'] ) && isset( $off_day_range['to_date'] ) ) {
                                    $from_date = date( 'Y-m-d', strtotime( $off_day_range['from_date'] ) );
                                    $to_date   = date( 'Y-m-d', strtotime( $off_day_range['to_date'] ) );

                                    // Collect all off dates within this range
                                    $off_date_lists = MP_Global_Function::date_separate_period( $from_date, $to_date );
                                    foreach ( $off_date_lists as $off_date_list ) {
                                        $off_dates[] = $off_date_list->format( 'Y-m-d' );
                                    }
                                }
                            }
                        }

                        // Unique off dates generated from the ranges
                        $off_dates = array_unique( $off_dates );

                            $particular_off_dates = MP_Global_Function::get_post_info( $post_id, 'wbtm_off_dates', array() );
                            if ( sizeof( $particular_off_dates ) > 0 ) {
                                foreach ( $particular_off_dates as $particular_off_date ) {
                                    // Check if the date is already in 'Y-m-d' format
                                    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $particular_off_date ) ) {
                                        $processed_date = $particular_off_date;
                                    } else {
                                        // Assume date is in 'MM-DD' format, prepend year
                                        $processed_date = date( 'Y-m-d', strtotime( $year . '-' . $particular_off_date ) );
                                        // Move to next year if the date is in the past
                                        if ( strtotime( $processed_date ) < strtotime( $now ) ) {
                                            $processed_date = date( 'Y-m-d', strtotime( ($year + 1) . '-' . $particular_off_date ) );
                                        }
                                    }
                                    $off_dates[] = $processed_date;
                                }
                            }

                            // Remove duplicates from the off dates array
                            $off_dates = array_unique( $off_dates );
                            $off_days      = MP_Global_Function::get_post_info( $post_id, 'wbtm_off_days' );
                            $off_day_array = $off_days ? explode( ',', $off_days ) : [];
                            $repeat        = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_after', 1 );

                            // Generate the date range
                            $dates = MP_Global_Function::date_separate_period( $start_date, $end_date, $repeat );
                            foreach ( $dates as $date ) {
                                $date = $date->format( 'Y-m-d' );
                                if ( strtotime( $date ) >= strtotime( $now ) ) {
                                    $day = strtolower( date( 'l', strtotime( $date ) ) ); // Get the day of the week
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
				$buffer_time = MP_Global_Function::get_settings( 'wbtm_general_settings', 'bus_buffer_time', 0 ) * 60;
				if ( $buffer_time > 0 ) {
					$date = date( 'Y-m-d H:i', strtotime( $date ) - $buffer_time );
				}
				return $date;
			}
			//==========================//
			public static function get_seat_price( $post_id, $start_route, $end_route, $seat_type = 0, $dd = false ) {
				if ( $post_id && $start_route && $end_route ) {
					$ticket_infos = self::get_ticket_info( $post_id, $start_route, $end_route );
					if ( sizeof( $ticket_infos ) > 0 ) {
						foreach ( $ticket_infos as $ticket_info ) {
							if ( $ticket_info['type'] == $seat_type ) {
								$price          = $ticket_info['price'];
								$seat_plan_type = MP_Global_Function::get_post_info( $post_id, 'wbtm_seat_type_conf' );
								if ( $seat_plan_type == 'wbtm_seat_plan' && $dd ) {
									$seat_dd_increase = (int) MP_Global_Function::get_post_info( $post_id, 'wbtm_seat_dd_price_parcent', 0 );
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
				$show_extra_service = MP_Global_Function::get_post_info( $post_id, 'show_extra_service', 'no' );
				if ( $show_extra_service == 'yes' ) {
					$ex_services = MP_Global_Function::get_post_info( $post_id, 'wbtm_extra_services', [] );
					if ( sizeof( $ex_services ) > 0 ) {
						foreach ( $ex_services as $ex_service ) {
							if ( $ex_service['option_name'] == $service_name ) {
								$price = max( 0, $ex_service['option_price'] );
								$price = MP_Global_Function::wc_price( $post_id, $price );
								return MP_Global_Function::price_convert_raw( $price );
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
						$cart_date   = $cart_date ? date( 'Y-m-d', strtotime( $cart_date ) ) : '';
						$bp_date     = $bp_date ? date( 'Y-m-d', strtotime( $bp_date ) ) : '';
						if ( $cart_bus_id == $bus_id && $cart_bp == $bp && $cart_dp == $dp && strtotime( $cart_date ) == strtotime( $bp_date ) ) {
							$cart_seat_infos = array_key_exists( 'wbtm_seats', $cart_item ) ? $cart_item['wbtm_seats'] : '';
							if ( sizeof( $cart_seat_infos ) > 0 ) {
								foreach ( $cart_seat_infos as $seat_info ) {
									if ( array_key_exists( 'seat_name', $seat_info ) && $seat_info['seat_name'] == $seat_name ) {
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
				return MP_Global_Function::get_settings( 'wbtm_general_settings', 'label', esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ) );
			}
			public static function get_slug() {
				return MP_Global_Function::get_settings( 'wbtm_general_settings', 'bus', 'bus' );
			}
			public static function get_icon() {
				$svg = '<svg width="60" height="51" viewBox="0 0 60 51" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M24.2388 0.0864429L25.7288 0.0839404C26.7632 0.0824146 27.7975 0.0819873 28.832 0.0821094C29.4503 0.0821094 30.0687 0.081377 30.687 0.0801563C31.3833 0.0787525 32.0797 0.0767383 32.776 0.07448C33.7996 0.0717334 34.8232 0.0712451 35.8469 0.0713672C36.0709 0.0712451 36.2949 0.07094 36.5189 0.0704517C36.7791 0.0699024 37.0394 0.0691089 37.2997 0.0680713C45.2414 0.0392017 47.522 0.0309009 49.2498 1.00405C49.9457 1.39601 50.5519 1.94728 51.4023 2.72048L51.4028 2.72103C52.5543 4.04988 52.8179 5.03646 52.7754 6.76595L52.7686 7.29036C52.7628 7.71529 52.7539 8.14015 52.7444 8.56502L53.5508 8.51845C55.7673 8.52632 57.7653 9.09499 59.4443 10.5501C60.6074 12.8764 59.6877 16.8532 59.0139 19.2517C58.6736 20.0405 58.3959 20.46 57.7073 20.9722C57.4002 21.0286 57.2446 21.0572 57.0878 21.0691C56.9268 21.0814 56.7645 21.0761 56.4355 21.0653L55.7822 21.0556C55.6635 21.0378 55.5701 21.0238 55.4919 21.008C55.4052 20.9904 55.3369 20.9707 55.2734 20.9409C55.1254 20.8718 55.0026 20.749 54.7296 20.4759C54.7137 20.2223 54.7017 19.972 54.6934 19.7226C54.6877 19.5524 54.6837 19.3826 54.6813 19.2125C54.6776 18.9498 54.6776 18.6862 54.6811 18.419L54.6832 17.8036C54.686 17.1538 54.6922 16.504 54.6985 15.8542C54.701 15.4141 54.7032 14.9741 54.7053 14.534C54.7108 13.4542 54.7194 12.3744 54.7296 11.2946L52.7444 11.0464L52.7471 11.5306C52.7691 15.4201 52.7855 19.3095 52.7958 23.1991C52.8009 25.08 52.8079 26.961 52.8193 28.8419C52.8292 30.4816 52.8356 32.1212 52.8378 33.7609C52.8391 34.6289 52.8421 35.4968 52.8493 36.3648C52.8574 37.3343 52.8575 38.3038 52.8571 39.2734L52.8679 40.1431C52.8599 41.6914 52.7986 42.6287 51.7518 43.8014C50.8229 44.6814 50.3075 44.8619 49.0222 45.2903L48.7741 49.7569C47.7635 50.2622 47.3414 50.3168 46.2451 50.319L45.349 50.321L44.4161 50.3152L43.4802 50.321L42.585 50.319L41.7654 50.3174C41.4659 50.2893 41.2976 50.2735 41.1405 50.2229C40.939 50.158 40.7557 50.036 40.3377 49.7572L40.3372 49.7569V46.531H19.4931V49.7569C18.4825 50.2622 18.0604 50.3168 16.9642 50.319L16.0681 50.321L15.1351 50.3152L14.1992 50.321L13.3041 50.319L12.4844 50.3174C12.185 50.2893 12.0167 50.2735 11.8596 50.2229C11.658 50.158 11.4748 50.036 11.0568 49.7572L11.0562 49.7569C11.0174 49.5427 10.9964 49.4269 10.9862 49.3101C10.9742 49.1726 10.9771 49.0338 10.9835 48.7313L10.9908 48.1255L11.0097 47.4926L11.0198 46.8538C11.0286 46.3325 11.042 45.8114 11.0562 45.2903L10.4998 45.2089C9.77643 45.0326 9.38013 44.8073 8.79193 44.3598L8.2937 43.9914C7.57916 43.3158 7.13495 42.6558 6.99432 41.6716L6.9964 40.9764L6.99329 40.174L7.0014 39.3016L7.0011 38.3769C7.00153 37.5423 7.00525 36.7078 7.01044 35.8732C7.01459 35.091 7.01538 34.3087 7.01617 33.5264L7.01642 33.2543C7.0177 32.3358 7.0202 31.4174 7.02338 30.499C7.02594 29.7653 7.02899 29.0316 7.03229 28.2978C7.04065 26.4165 7.04474 24.5352 7.04846 22.6538C7.0531 20.3334 7.05969 18.013 7.06781 15.6925C7.07098 14.786 7.0744 13.8795 7.078 12.973C7.08051 12.3308 7.08319 11.6886 7.08594 11.0464L5.34888 11.2946L5.33398 12.1295C5.31519 13.1497 5.29315 14.1699 5.27014 15.19C5.26666 15.3526 5.26324 15.5152 5.26001 15.6778C5.25439 15.9571 5.24915 16.2364 5.2442 16.5158C5.23291 17.1503 5.21851 17.7846 5.20349 18.419L5.19482 19.0199C5.17133 19.8953 5.16254 20.2232 5.02393 20.4813C4.94092 20.6358 4.81146 20.7652 4.60449 20.9722L4.60229 20.9726L4.59955 20.9729C4.34528 21.011 4.20776 21.0316 4.06952 21.0436C3.90814 21.0576 3.74573 21.0601 3.39478 21.0653L2.74536 21.0866C1.90491 20.9321 1.64685 20.6544 1.13043 19.9796C0.233765 17.9011 0.0394897 15.7376 0.0137939 13.4968L0 12.8198C0.00952148 11.8062 0.0502319 11.1975 0.566345 10.3078C2.36505 8.70687 4.78479 8.52535 7.08594 8.56502L7.0423 7.82057V7.81984V7.81929C6.95599 5.73537 6.92511 4.99013 7.19006 4.37154C7.33752 4.02724 7.57654 3.72219 7.94861 3.24733L8.32666 2.85768L8.67365 2.49904C11.8582 -0.200544 17.4107 -0.0635815 21.8657 0.0463428C22.7032 0.0670337 23.5019 0.0867481 24.2388 0.0864429ZM24.1652 6.75331C24.6938 6.7524 25.2224 6.7513 25.751 6.75014C26.8495 6.74831 27.9481 6.74831 29.0466 6.74965C30.4437 6.75112 31.8406 6.74684 33.2376 6.74111C34.3259 6.73744 35.4142 6.73726 36.5026 6.73799C37.0173 6.73787 37.5321 6.73665 38.0469 6.73409C43.077 6.71242 44.6772 6.70552 46.0911 7.21523C46.7508 7.45302 47.3699 7.80324 48.2778 8.31685C48.8853 8.92433 48.8734 9.53237 48.8587 10.2905V10.2951C48.8561 10.4238 48.8536 10.5568 48.854 10.6948L48.8615 11.2831C48.8656 11.6442 48.8685 12.0054 48.8706 12.3665C48.8723 12.6492 48.8737 12.932 48.8749 13.2148L48.8834 14.5556C48.8887 15.4937 48.8915 16.4318 48.8932 17.3699C48.896 18.5716 48.9079 19.773 48.9221 20.9746C48.925 21.2692 48.9272 21.5638 48.929 21.8584C48.9328 22.4875 48.9341 23.1165 48.9346 23.7456C48.9362 24.1886 48.9401 24.6315 48.9467 25.0744C48.9551 25.6947 48.9542 26.3143 48.9509 26.9347L48.9648 27.4884C48.9534 28.189 48.9123 28.7455 48.4561 29.2976C44.3609 32.8057 37.2667 32.742 31.7675 32.6925H31.7642L31.2797 32.6882C31.1296 32.6869 30.9808 32.6857 30.8334 32.6848C30.5259 32.6827 30.2247 32.6814 29.9306 32.6815L29.3867 32.6822C25.8061 32.6817 22.2106 32.6254 18.6866 31.937L17.9886 31.8036C15.1982 31.2534 14.426 31.1011 13.7063 30.8164C13.431 30.7074 13.1633 30.5791 12.7932 30.4016L12.354 30.2024C11.7476 29.827 11.4399 29.521 11.0562 28.9128C10.8024 27.4871 10.821 26.0708 10.8401 24.6293L10.843 24.4055C10.843 23.9594 10.8425 23.5132 10.8415 23.0671C10.8411 22.1337 10.8468 21.2008 10.857 20.2675C10.8697 19.0714 10.8691 17.8757 10.8643 16.6795C10.8618 15.759 10.8653 14.8386 10.8708 13.9181C10.8729 13.4771 10.8733 13.036 10.8719 12.5949C10.8715 12.271 10.8732 11.9472 10.8765 11.6234C10.8793 11.3314 10.8833 11.0394 10.8878 10.7473L10.8825 10.1956C10.9051 9.29408 10.9819 8.8918 11.6095 8.22695C12.9818 7.41561 14.1765 7.19667 15.7426 7.04976L16.4022 6.98647C18.988 6.7585 21.5711 6.7513 24.1652 6.75331ZM48.02 34.8266L47.3783 34.8217C45.7564 34.7943 44.392 35.2532 42.9805 36.0412L42.5395 36.2951C41.6074 36.8006 40.8197 37.3009 40.1123 38.1009C39.4811 38.9972 39.4664 39.5016 39.5928 40.5756C39.6323 40.6151 39.6663 40.6491 39.7004 40.6785C39.7184 40.6941 39.7365 40.7084 39.7554 40.7215C39.9595 40.8631 40.2595 40.8664 41.6821 40.8819L42.4309 40.8858C44.6152 40.9326 47.0839 40.9285 48.7741 39.3349L48.7745 39.3316C48.8414 38.7061 48.8658 38.4782 48.873 38.2497C48.8753 38.1788 48.8759 38.1079 48.8756 38.025C48.8754 37.9557 48.8745 37.8781 48.8735 37.7851V37.782L48.8516 37.1946C48.8431 36.4976 48.8188 35.8129 48.7741 35.1164C48.6703 35.0127 48.61 34.9523 48.5378 34.9141C48.4374 34.8609 48.3143 34.8508 48.02 34.8266ZM12.3589 34.7752C15.181 34.8154 17.6597 36.2817 19.7412 38.0941C20.3275 38.9735 20.2994 39.4745 20.2429 40.4783L20.2375 40.5755C19.7841 41.0289 18.813 40.9654 18.0303 40.9142C17.7971 40.8989 17.5805 40.8848 17.3994 40.8857L16.6768 40.9012C14.7117 40.9118 12.5556 40.7486 11.0562 39.3348C10.9761 38.5871 10.951 37.9382 10.9786 37.1945L10.9859 36.6013C11.0051 35.6325 11.0137 35.1939 11.2371 34.9862C11.4218 34.8145 11.7533 34.8006 12.3589 34.7752ZM23.1732 2.07985L22.6463 2.08119C22.4789 2.08132 22.3055 2.06844 22.1341 2.05574C21.4312 2.00368 20.7637 1.95424 20.7009 2.81117L20.7028 3.35402C20.7009 3.89681 20.7009 3.89681 20.7338 4.34657C20.8206 4.43331 20.877 4.48976 20.9439 4.52779C21.0683 4.59859 21.2289 4.60573 21.6884 4.62624L22.6463 4.62685C23.2007 4.62819 23.755 4.62917 24.3094 4.62856C24.5839 4.62813 24.8585 4.62795 25.133 4.62789L25.5014 4.62795C26.3269 4.62856 27.1524 4.62886 27.978 4.62795C29.0553 4.62648 30.1326 4.62563 31.2099 4.62709C31.7462 4.62782 32.2823 4.62831 32.8186 4.62844C33.121 4.6285 33.4233 4.6285 33.7257 4.62837L34.9246 4.6277C35.5021 4.6285 36.0795 4.62923 36.657 4.62813L37.184 4.62685C37.4457 4.62667 37.7074 4.62502 37.9691 4.62074C38.2623 4.61592 38.5554 4.60781 38.8483 4.59474C38.9586 4.48445 39.0199 4.42317 39.0568 4.34969C39.103 4.25777 39.1111 4.14675 39.1294 3.89681L39.1275 3.35402C39.1294 2.81117 39.1294 2.81117 39.0965 2.36141C39.0098 2.27468 38.9533 2.21828 38.8864 2.18025C38.762 2.10945 38.6014 2.10225 38.1419 2.08174L37.184 2.08119C36.6296 2.07979 36.0753 2.07881 35.5209 2.07948C35.1235 2.08003 34.7262 2.08022 34.3289 2.0801C33.5034 2.07942 32.6779 2.07912 31.8524 2.08003C30.775 2.0815 29.6977 2.08235 28.6204 2.08095C27.7818 2.07979 26.9432 2.07924 26.1046 2.07961L24.9058 2.08034C24.3282 2.07948 23.7507 2.07875 23.1732 2.07985Z" fill="#9BA2A6"/>
				</svg>';
				return MP_Global_Function::get_settings( 'wbtm_general_settings', 'icon', 'data:image/svg+xml;base64,' . base64_encode( $svg ) );
			}

            public static function wbtm_left_filter_disppaly( $bus_types, $bus_titles, $start_routes, $filter_by_box, $left_filter_show ): void {
                ?>
                <div id="wbtm_bus_filter-options">
                    <div class="wbtm_left_filter_title_holder">
                        <div class="wbtm_bus_filter_title">
                            <span class="wbtm_bus_filter_title_text"> Filter </span>
                        </div>
                        <div class="wbtm_bus_filter_reset">
                            <span class="wbtm_bus_filter_reset_text wbtm_reset_<?php echo esc_attr( $filter_by_box ) ?>"> Reset </span>
                        </div>
                    </div>
                    <div class="wbtm_left_filter_element_holder">
                        <?php if( $left_filter_show['left_filter_type'] === 'on' && !empty( $bus_types ) ) {?>
                        <div class="wbtm_bus_filter_items">
                                <span class="wbtm_bus_toggle-header">Bus Type <span class="wbtm_bus_toggle-icon"></span></span>
                                <?php
                                $search_bus_types = array_unique($bus_types);
                                foreach ( $search_bus_types as $bus_type) {
                                    if( !empty( $bus_type ) ){
                                    ?>
                                    <div class="wbtm_bus_left_filter_checkbox_holder">
                                        <input type="checkbox" class="<?php echo $filter_by_box;?>" data-filter="wbtm_bus_type" value="<?php echo esc_attr( $bus_type );?>">
                                        <span><?php echo esc_attr( $bus_type );?></span>
                                    </div>
                                <?php } }?>
                            </div>
                        <?php }
						
						
                        if( $left_filter_show['left_filter_operator'] === 'on' && !empty( $bus_titles ) ) { ?>
                        <div class="wbtm_bus_filter_items">
                            <span class="wbtm_bus_toggle-header">Bus Operator <span class="wbtm_bus_toggle-icon"></span></span>
                            <?php
                            $search_bus_titles = array_unique( $bus_titles );
                            foreach ( $search_bus_titles as $bus_title ) {
                                if( !empty( $bus_title ) ){
                                ?>
                                <div class="wbtm_bus_left_filter_checkbox_holder">
                                    <input type="checkbox" class="<?php echo $filter_by_box;?>" data-filter="wbtm_bus_name" value="<?php echo esc_attr( $bus_title ); ?>">
                                    <span><?php echo esc_attr( $bus_title );?></span>
                                </div>
                            <?php } }?>
                        </div>
                        <?php }
						
                        if( $left_filter_show['left_filter_boarding'] === 'on' && is_array( $start_routes ) && count( $start_routes ) >0 ){
                        ?>
                        <div class="wbtm_bus_filter_items">
                            <span class="wbtm_bus_toggle-header">Boarding Point <span class="wbtm_bus_toggle-icon"></span></span>
                            <?php  foreach ( $start_routes as $route ){?>
                                <div class="wbtm_bus_left_filter_checkbox_holder">
                                    <input type="checkbox" class="<?php echo $filter_by_box?>" data-filter="wbtm_bus_start_route" value="<?php echo esc_attr($route); ?>">
                                    <span><?php echo esc_attr($route); ?></span>
                                </div>
                            <?php }?>
                        </div>
                        <?php }?>
                    </div>
                </div>
                <?php
            }
		}
	}