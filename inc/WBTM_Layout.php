<?php
	/*
	* @Author 		engr.sumonazma@gmail.com
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Layout')) {
		class WBTM_Layout {
			public function __construct() {
				add_action('wbtm_search_result', [$this, 'search_result'], 10, 9);
				/*********************/
				add_action('wp_ajax_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				add_action('wp_ajax_nopriv_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
				/**************************/
				add_action('wp_ajax_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
				add_action('wp_ajax_nopriv_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
				/**************************/
				add_action('wp_ajax_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
				add_action('wp_ajax_nopriv_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
				/**************************/
				add_action('wp_ajax_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
				add_action('wp_ajax_nopriv_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
				/**************************/
				add_action('wp_ajax_get_wbtm_return_bus_list', [$this, 'get_wbtm_return_bus_list']);
				add_action('wp_ajax_nopriv_get_wbtm_return_bus_list', [$this, 'get_wbtm_return_bus_list']);
				/**************************/
				add_action('wp_ajax_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				add_action('wp_ajax_nopriv_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
				/**************************/
				// Async "chunk" that greys out sold-out dates after the calendar is already shown.
				add_action('wp_ajax_get_wbtm_soldout_dates', [$this, 'get_wbtm_soldout_dates']);
				add_action('wp_ajax_nopriv_get_wbtm_soldout_dates', [$this, 'get_wbtm_soldout_dates']);
				/**************************/
			}
			public function get_wbtm_soldout_dates() {
				if ( isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce') ) {
					if ( ! self::soldout_highlight_enabled() ) {
						wp_send_json_success(['soldout' => []]);
					}
					$post_id     = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route   = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$leg         = isset($_POST['leg']) ? sanitize_text_field(wp_unslash($_POST['leg'])) : 'outbound';
					$j_date      = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
					$soldout     = WBTM_Functions::get_soldout_dates_fast($post_id, $start_route, $end_route);
					$out = [];
					foreach ( $soldout as $so_date ) {
						// Return leg can only be on/after the outbound date.
						if ( $leg === 'return' && $j_date && strtotime($so_date) < strtotime($j_date) ) {
							continue;
						}
						$out[] = gmdate('j-n-Y', strtotime($so_date)); // datepicker d-m-Y key
					}
					wp_send_json_success(['soldout' => array_values(array_unique($out))]);
				}
				wp_send_json_error();
			}
			public function search_result($start_route, $end_route, $date, $post_id = '', $style = '', $btn_show = '', $search_info = [], $journey_type = '', $left_filter_show = [] ) {
				if ($style == 'flix') {
					require WBTM_Functions::template_path('layout/search_result_flix.php');
				} else {
					require WBTM_Functions::template_path('layout/search_result.php');
				}
			}
			public function get_wbtm_dropping_point() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					self::route_list($post_id, $start_route);
					die();
				}
			}
			public function get_wbtm_journey_date() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					self::journey_date_picker($post_id, $start_route, $end_route);
					die();
				}
			}
			public function get_wbtm_return_date() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
					self::return_date_picker($post_id, $end_route, $start_route, $j_date);
					die();
				}
			}
			public function get_wbtm_bus_list() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
					$r_date = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
					$style = isset($_POST['style']) ? sanitize_text_field(wp_unslash($_POST['style'])) : '';
					$btn_show = isset($_POST['btn_show']) ? sanitize_text_field(wp_unslash($_POST['btn_show'])) : '';
                    $left_filter_show = isset($_POST['left_filter_show'])
                        ? json_decode( sanitize_text_field( wp_unslash($_POST['left_filter_show'] ) ), true )
                        : [];

					$bus_start_end_id = isset( $_POST['wbtm_bus_start_end_id']) ? sanitize_text_field( wp_unslash( $_POST['wbtm_bus_start_end_id'])) : '';
					$search_info['bus_start_route'] = $start_route;
					$search_info['bus_end_route'] = $end_route;
					$search_info['j_date'] = $j_date;
					$search_info['r_date'] = $r_date;
					self::wbtm_bus_list($post_id, $start_route, $end_route, $j_date, $r_date, $style, $btn_show, $search_info, $left_filter_show, $bus_start_end_id );
					$redirect_enabled = WBTM_Global_Function::get_settings('wbtm_general_settings', 'cart_empty_after_search', 'off');
					if ($redirect_enabled === 'on' && WC()->cart->get_cart_contents_count() > 0) {
						WC()->cart->empty_cart();
					}
					die();
				}
			}
			/**
			 * Re-render only the Return leg's title + bus list for a customer-chosen
			 * return From/To (Pro: Editable Return Route).
			 */
			public function get_wbtm_return_bus_list() {
				if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wtbm_ajax_nonce' ) ) {
					$post_id      = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
					$return_start = isset( $_POST['return_start'] ) ? sanitize_text_field( wp_unslash( $_POST['return_start'] ) ) : '';
					$return_end   = isset( $_POST['return_end'] ) ? sanitize_text_field( wp_unslash( $_POST['return_end'] ) ) : '';
					$j_date       = isset( $_POST['j_date'] ) ? sanitize_text_field( wp_unslash( $_POST['j_date'] ) ) : '';
					$r_date       = isset( $_POST['r_date'] ) ? sanitize_text_field( wp_unslash( $_POST['r_date'] ) ) : '';
					$style        = isset( $_POST['style'] ) ? sanitize_text_field( wp_unslash( $_POST['style'] ) ) : '';
					$btn_show     = isset( $_POST['btn_show'] ) ? sanitize_text_field( wp_unslash( $_POST['btn_show'] ) ) : '';
					$left_filter_show = isset( $_POST['left_filter_show'] )
						? json_decode( sanitize_text_field( wp_unslash( $_POST['left_filter_show'] ) ), true )
						: [];
					if ( ! is_array( $left_filter_show ) ) {
						$left_filter_show = [];
					}
					// Only honour this endpoint when the Pro feature is on.
					if ( ! self::is_editable_return_enabled( $post_id ) ) {
						die();
					}
					if ( $return_start && $return_end && $r_date ) {
						$search_info = array(
							'bus_start_route' => $return_start,
							'bus_end_route'   => $return_end,
							'j_date'          => $j_date,
							'r_date'          => $r_date,
						);
						$floor_time = isset( $_POST['floor_time'] ) ? sanitize_text_field( wp_unslash( $_POST['floor_time'] ) ) : '';
					self::render_return_bus_list_inner( $post_id, $return_start, $return_end, $j_date, $r_date, $style, $btn_show, $search_info, $left_filter_show, $floor_time );
					}
					die();
				}
			}
			public function get_wbtm_bus_details() {
				if (isset($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wtbm_ajax_nonce')) {
					/*$post_id = WBTM_Global_Function::data_sanitize($_POST['post_id']);
					$start_route = WBTM_Global_Function::data_sanitize($_POST['start_route']);
					$end_route = WBTM_Global_Function::data_sanitize($_POST['end_route']);
					$date = $_POST['date'] ?? '';*/
					$post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
					$start_route = isset($_POST['start_route']) ? sanitize_text_field(wp_unslash($_POST['start_route'])) : '';
					$end_route = isset($_POST['end_route']) ? sanitize_text_field(wp_unslash($_POST['end_route'])) : '';
					$date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
					$backend_order = isset($_POST['backend_order']) ? sanitize_text_field(wp_unslash($_POST['backend_order'])) : '';
					$link_wc_product = WBTM_Global_Function::get_post_info($post_id, 'link_wc_product');
					$display_drop_off_point = WBTM_Global_Function::get_post_info($post_id, 'show_drop_off_point', 'no');
					$drop_off_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_drop_off_point', []);
					$drop_off_required = WBTM_Global_Function::get_post_info($post_id, 'wbtm_dropping_point_required', 'no');
					$display_pickup_point = WBTM_Global_Function::get_post_info($post_id, 'show_pickup_point', 'no');
					$pickup_points = WBTM_Global_Function::get_post_info($post_id, 'wbtm_pickup_point', []);
					$seat_type = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
					if ($post_id > 0 && $start_route && $end_route && $date) {
						$wbtm_price_leg = ( isset( $_POST['wbtm_price_leg'] ) && sanitize_text_field( wp_unslash( $_POST['wbtm_price_leg'] ) ) === 'return' ) ? 'return' : 'outbound';
						$wbtm_price_leg = WBTM_Functions::resolve_price_leg_for_od_pair( $post_id, $start_route, $end_route, $wbtm_price_leg );
						$all_info = WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route, $wbtm_price_leg);
						$seat_price = $seat_price ?? WBTM_Functions::get_seat_price($post_id, $start_route, $end_route, 0, false, $wbtm_price_leg);
						$ticket_infos = $ticket_infos ?? WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route, $wbtm_price_leg);
//                    $seat_column = $seat_column ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
//                    $seat_row = $seat_row ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
//                    $seat_infos = $seat_infos ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
//                    $cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
						$cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');
						$wbtm_bus_type = esc_html(WBTM_Functions::synchronize_bus_type($post_id));
						$display_wbtm_registration = WBTM_Global_Function::get_post_info($post_id, 'wbtm_registration', 'yes');
						if ($all_info['available_seat'] > 0) {
							$seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
							$seat_row = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
							$seat_column = WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
							$cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
							$has_cabin_config = !empty($cabin_config) && count(array_filter($cabin_config, function ($c) { return ($c['enabled'] ?? 'yes') === 'yes'; })) > 0;
							$bus_start_time = $all_info['start_time'];
							$j_date = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
							$r_date = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
							$bus_start_route = isset($_POST['bus_start_route']) ? sanitize_text_field(wp_unslash($_POST['bus_start_route'])) : '';
							$bus_end_route = isset($_POST['bus_end_route']) ? sanitize_text_field(wp_unslash($_POST['bus_end_route'])) : '';
							?>
                            <div class="wbtm_registration_area _bgWhite mT">
                                <form action="" method="post" class="">
                                    <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                                    <input type="hidden" name='wbtm_start_point' value='<?php echo esc_attr($all_info['start_point']); ?>'/>
                                    <input type="hidden" name='wbtm_start_time' value='<?php echo esc_attr($bus_start_time); ?>'/>
                                    <input type="hidden" name='wbtm_bp_place' value='<?php echo esc_attr($all_info['bp']); ?>'/>
                                    <input type="hidden" name='wbtm_bp_time' value='<?php echo esc_attr($all_info['bp_time']); ?>'/>
                                    <input type="hidden" name='wbtm_dp_place' value='<?php echo esc_attr($all_info['dp']); ?>'/>
                                    <input type="hidden" name='wbtm_dp_time' value='<?php echo esc_attr($all_info['dp_time']); ?>'/>
                                    <input type="hidden" name='bus_start_route' value='<?php echo esc_attr($bus_start_route); ?>'/>
                                    <input type="hidden" name='bus_end_route' value='<?php echo esc_attr($bus_end_route); ?>'/>
                                    <input type="hidden" name='j_date' value='<?php echo esc_attr($j_date); ?>'/>
                                    <input type="hidden" name='r_date' value='<?php echo esc_attr($r_date); ?>'/>
                                    <input type="hidden" name='wbtm_cabin_mode_enabled' value='<?php echo esc_attr($cabin_mode_enabled); ?>'/>
                                    <input type="hidden" name="wbtm_price_leg" value="<?php echo esc_attr( $wbtm_price_leg ); ?>"/>
									<?php
										wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce');
										// Check for cabin configuration or legacy seat plan
										if ($seat_type == 'wbtm_seat_plan' && ($has_cabin_config || (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0))) {
											require WBTM_Functions::template_path('layout/registration_seat_plan.php');
										} else {
											require WBTM_Functions::template_path('layout/registration_without_seat_plan.php');
										}
									?>
                                </form>
								<?php do_action('wbtm_attendee_form_hidden', $post_id); ?>
                            </div>
							<?php
						} else {
							WBTM_Layout::msg(WBTM_Translations::text_no_seat());
						}
					}
					die();
				}
			}
			public static function wbtm_bus_list($post_id, $start_route, $end_route, $j_date, $r_date, $style = '', $btn_show = '', $search_info = [], $left_filter_show = [], $bus_start_end_id = '' ) {

				if ($start_route && $end_route && $j_date) {
                    if( $bus_start_end_id === 'start_bus' ){
                        $start_bus = 'block';
                        $return_bus = 'none';
                        $start_bus_tab = 'wbtm_tab_active';
                        $return_bus_tab = '';
                    }else if( $bus_start_end_id === 'wbtm_return_container' ){
                        $start_bus = 'none';
                        $return_bus = 'block';

                        $start_bus_tab = '';
                        $return_bus_tab = 'wbtm_tab_active';
                    }else{
                        $start_bus = 'block';
                        $return_bus = 'none';

                        $start_bus_tab = 'wbtm_tab_active';
                        $return_bus_tab = '';
                    }

                    $next_date = WBTM_Global_Function::get_settings('wbtm_general_settings', 'next_date_showing_search', 'no');
                    ?>
                    <div class="wbtm-bus-lists" id="wbtm_start_container">


                        <div class="wbtm_departure_bus_lists_holder">
                            <div class="wbtm_bus_tab_wrapper">
                                <div class=" wtbm_start_route <?php echo esc_attr( $start_bus_tab );?>" id="wbtm_date_start_route" >
                                    <?php esc_attr_e( 'Departure Bus', 'bus-ticket-booking-with-seat-reservation' )?>
                                </div>

                                <?php  if ( ( $post_id == 0 || WBTM_Functions::is_same_bus_return_enabled( $post_id ) ) && $start_route && $end_route && $r_date) { ?>
                                    <div class="wtbm_return_route <?php echo esc_attr( $return_bus_tab );?>" id="wbtm_date_return_route_start" data-alert="<?php echo esc_attr__( 'Please place departure bus first.', 'bus-ticket-booking-with-seat-reservation' ); ?>">
                                        <?php esc_attr_e( 'Return Bus', 'bus-ticket-booking-with-seat-reservation' )?>
                                    </div>
                                <?php }?>
                            </div>

                            <div class="wbtm_seleced_start_bus" id="wbtm_seleced_start_bus"></div>
                            <div class="wbtm-bus-lists" id="start_bus" style="display: <?php echo esc_attr( $start_bus );?>">

                                <?php if( $next_date === 'yes' ){?>
                                    <div class="wbtm-date-suggetion">
                                        <?php self::next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date); ?>
                                    </div>
                                <?php }?>
                                <?php self::route_title('Departure', $start_route, $end_route, $j_date, $r_date); ?>

                                <?php do_action('wbtm_search_result', $start_route, $end_route, $j_date, $post_id, $style, $btn_show, $search_info, 'start_journey', $left_filter_show); ?>
                            </div>
                        </div>

                    </div>
                    <div class="wbtm_return_bus_lists_holder" id="wbtm_return_bus_lists_holder">
                        <?php }
                        if ( ( $post_id == 0 || WBTM_Functions::is_same_bus_return_enabled( $post_id ) ) && $start_route && $end_route && $r_date) {
                            // Return leg defaults to the exact reverse of the outbound trip.
                            $return_start = $end_route;
                            $return_end   = $start_route;
                            $editable_return = self::is_editable_return_enabled( $post_id );
                            ?>
                        <div class="wbtm-bus-lists" id="wbtm_return_container" style="display: <?php echo esc_attr( $return_bus );?>"
                             data-post-id="<?php echo esc_attr( $post_id ); ?>"
                             data-j-date="<?php echo esc_attr( $j_date ); ?>"
                             data-r-date="<?php echo esc_attr( $r_date ); ?>"
                             data-style="<?php echo esc_attr( $style ); ?>"
                             data-btn-show="<?php echo esc_attr( $btn_show ); ?>"
                             data-left-filter="<?php echo esc_attr( wp_json_encode( $left_filter_show ) ); ?>">
                            <?php if( $next_date === 'yes' ){?>
                                <div class="wbtm-date-suggetion">
                                    <?php self::next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date, true); ?>
                                </div>
                            <?php }
                            if ( $editable_return ) {
                                self::render_return_route_selectors( $post_id, $return_start, $return_end );
                            }
                            ?>
                             <div class="wbtm_return_bus_lists_container" >
                                 <?php self::render_return_bus_list_inner( $post_id, $return_start, $return_end, $j_date, $r_date, $style, $btn_show, $search_info, $left_filter_show ); ?>
                            </div>
                        </div>
                    </div>
				    <?php }
			}
			/**
			 * Whether the editable return-route feature (Pro) is enabled.
			 *
			 * @param int $post_id Bus post ID (0 for global search).
			 */
			public static function is_editable_return_enabled( $post_id = 0 ) {
				return (bool) apply_filters( 'wbtm_enable_editable_return_route', false, $post_id );
			}
			/**
			 * Render the title + bus list for the return leg.
			 *
			 * @param array<string, mixed> $search_info
			 * @param array<string, mixed> $left_filter_show
			 */
			public static function render_return_bus_list_inner( $post_id, $return_start, $return_end, $j_date, $r_date, $style = '', $btn_show = '', $search_info = [], $left_filter_show = [], $floor_time = null ) {
				$effective_r_date = $r_date;
				// Same-bus-return round trips: the mirror leg must depart AFTER the outbound
				// arrives. If the requested (same-day) return date has no such bus, roll forward
				// to the next operational day that does.
				if ( $post_id > 0 && WBTM_Functions::is_same_bus_return_enabled( $post_id ) && $j_date && $r_date
					&& gmdate( 'Y-m-d', strtotime( $j_date ) ) === gmdate( 'Y-m-d', strtotime( $r_date ) ) ) {
					$floor_ts = null;
					if ( $floor_time ) {
						$floor_ts = strtotime( $floor_time );
					} else {
						$out_start = isset( $search_info['bus_start_route'] ) ? $search_info['bus_start_route'] : '';
						$out_end   = isset( $search_info['bus_end_route'] ) ? $search_info['bus_end_route'] : '';
						if ( $out_start && $out_end ) {
							$out_times = WBTM_Functions::get_od_leg_datetimes( $post_id, $out_start, $out_end, $j_date );
							if ( ! empty( $out_times['dp_time'] ) ) {
								$floor_ts = strtotime( $out_times['dp_time'] );
							}
						}
					}
					if ( $floor_ts ) {
						$effective_r_date = WBTM_Functions::resolve_return_date_after( $post_id, $return_start, $return_end, $r_date, $floor_ts );
					}
				}
				// Keep the return booking's date metadata aligned with what is displayed.
				$search_info['r_date'] = $effective_r_date;
				// route_title() with $return=true displays $end_route -> $start_route, so pass them
				// reversed to render "$return_start -> $return_end" with return styling.
				self::route_title( 'Return', $return_end, $return_start, $j_date, $effective_r_date, true );
				?>
                <div class="wbtm-bus-lists" id="return_bus">
                    <?php do_action( 'wbtm_search_result', $return_start, $return_end, $effective_r_date, $post_id == 0 ? '' : $post_id, $style, $btn_show, $search_info, 'return_journey', $left_filter_show ); ?>
                </div>
				<?php
			}
			/**
			 * Render editable From / To selectors for the return leg (Pro feature).
			 * Reuses the same markup/behaviour as the main search From/To inputs.
			 */
			public static function render_return_route_selectors( $post_id, $return_start, $return_end ) {
				$placeholder_text = WBTM_Translations::text_please_select();
				?>
                <div class="wbtm_return_route_selectors">
                    <div class="wtbm_inputList wbtm_return_start_point wbtm_return_from_fixed">
                        <label class="wtbm_fdColumn">
                            <?php echo esc_html( WBTM_Translations::text_from() ); ?>
                            <div class="marker">
                                <i class="fas fa-map-marker-alt"></i>
                                <!-- Return "From" is fixed to the outbound destination (you return from where you arrived); only "To" is editable. -->
                                <input type="text" class="formControl" name="wbtm_return_start_route" value="<?php echo esc_attr( $return_start ); ?>" readonly="readonly" tabindex="-1" autocomplete="off"/>
                                <i class="fas fa-lock wbtm_return_from_lock" aria-hidden="true"></i>
                            </div>
                        </label>
                    </div>
                    <div class="wtbm_inputList wbtm_input_select wbtm_return_dropping_point" data-alert="<?php echo esc_attr( WBTM_Translations::text_select_wrong_route() ); ?>">
                        <label class="wtbm_fdColumn">
                            <?php echo esc_html( WBTM_Translations::text_to() ); ?>
                            <div class="marker">
                                <i class="fas fa-map-marker-alt wtbm_icon_margin"></i>
                                <input type="text" class="formControl" name="wbtm_return_end_route" value="<?php echo esc_attr( $return_end ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off"/>
                            </div>
                        </label>
                        <?php self::route_list( $post_id, $return_start ); ?>
                    </div>
                </div>
				<?php
			}
			public static function wbtm_get_time_diff($start_time, $end_time) {
				$start = new DateTime($start_time);
				$end = new DateTime($end_time);
				$interval = $start->diff($end);
				$result = [];
				if ($interval->d > 0) {
					$result[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
				}
				if ($interval->h > 0) {
					$result[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
				}
				if ($interval->i > 0) {
					$result[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
				}
				if (empty($result)) {
					return '0 minutes';
				}
				return implode(' ', $result);
			}
			public static function selected_bus_display($data) {
				$bus_id = $data['post_id'];
				$bus_title = get_the_title($bus_id);
				$bus_number = get_post_meta($bus_id, 'wbtm_bus_no', true);
				$start_date_format = date("d F, Y", strtotime($data['j_date']));
				$start_time = date("h:i A", strtotime($data['wbtm_bp_time']));
				$end_time = date("h:i A", strtotime($data['wbtm_dp_time']));
				$time_diff = self::wbtm_get_time_diff($data['wbtm_bp_time'], $data['wbtm_dp_time']);
				ob_start();
                $checkout_url = wc_get_checkout_url();

				?>
                <div class="wbtm_selected_bus_card" data-outbound-bus-id="<?php echo esc_attr( $bus_id ); ?>" data-outbound-bp-time="<?php echo esc_attr($data['wbtm_bp_time']); ?>" data-outbound-dp-time="<?php echo esc_attr($data['wbtm_dp_time']); ?>" data-j-date="<?php echo esc_attr($data['j_date']); ?>" data-r-date="<?php echo esc_attr(isset($data['r_date']) ? $data['r_date'] : ''); ?>" data-same-bus-return="<?php echo WBTM_Functions::is_same_bus_return_enabled( $bus_id ) ? '1' : '0'; ?>">
                    <div class="wbtm_selected_bus_image">
						<?php WBTM_Functions::logo_thumbnail_display($bus_id); ?>
                    </div>
                    <div class="wbtm_selected_bus_img_name">
						<?php echo esc_html($bus_title); ?> <span><?php echo esc_html($bus_number); ?></span>
                    </div>
                    <div class="wbtm_selected_bus_info">
                        <div class="wbtm_selected_bus_date"><?php echo esc_attr($start_date_format); ?></div>
                        <div class="wbtm_selected_bus_route">
                            <div class="wbtm_selected_bus_left">
                                <div class="wbtm_selected_bus_time"><?php echo esc_attr($start_time) ?></div>
                                <div class="wbtm_selected_bus_city"><?php echo esc_attr($data['wbtm_bp_place']); ?></div>
                            </div>
                            <div class="wbtm_selected_bus_center">
                                <div class="wbtm_selected_bus_icon">🚍</div>
                                <div class="wbtm_selected_bus_duration"><?php echo esc_attr($time_diff); ?></div>
                                <div class="wbtm_selected_bus_arrow">➜</div>
                            </div>
                            <div class="wbtm_selected_bus_right">
                                <div class="wbtm_selected_bus_time"><?php echo esc_attr($end_time) ?></div>
                                <div class="wbtm_selected_bus_city"><?php echo esc_attr($data['wbtm_dp_place']); ?></div>
                            </div>
                        </div>
                        <div class="wbtm_selected_bus_seats">
                            Seat: <?php echo esc_attr($data['wbtm_selected_seat']); ?>
                        </div>
                    </div>
                    <div class="wbtm_selected_bus_payment">
                        <div class="wbtm_selected_bus_label"><?php esc_html_e('Total Amount to be Paid', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                        <div class="wbtm_selected_bus_price"><?php echo wp_kses_post( wc_price( $data['price_val'] ) ); ?></div>
                        <button class="wbtm_selected_bus_btn"><a href="<?php echo esc_attr( $checkout_url );?>" style="text-decoration: none; color: #FFFFFF"><?php esc_html_e('Checkout Without Return', 'bus-ticket-booking-with-seat-reservation'); ?></a></button>
                        <div class="" style="padding: 5px 0">
                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="cart-link">
                                <?php esc_attr_e('View Cart', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </a>
                        </div>

                    </div>
                </div>
				<?php
				return ob_get_clean();
			}
			public static function route_list($post_id = 0, $start_route = '') {
				$all_routes = WBTM_Functions::get_bus_route($post_id, $start_route);
				if (sizeof($all_routes) > 0) {
					?>
                    <ul class="wbtm_input_select_list">
						<?php foreach ($all_routes as $route) { ?>
                            <li data-value="<?php echo esc_attr($route); ?>">
								<?php echo esc_html($route); ?>
                            </li>
						<?php } ?>
                    </ul>
					<?php
				}
			}
			public static function next_date_suggestion($post_id, $start_route, $end_route, $j_date, $r_date, $return = false) {
				$route = $return ? $end_route : $start_route;
				$all_dates = WBTM_Functions::get_all_dates($post_id, $route);
				$total_date = sizeof($all_dates);
				if ($total_date > 0) {
					$active_date = $return ? $r_date : $j_date;
					if ($start_route && $end_route && $j_date) {
						$key = array_search($active_date, $all_dates);
						$start_key = $key > 2 ? $key - 2 : 0;
						$start_key = $total_date - 3 <= $key ? max(0, $total_date - 6) : $start_key;
						$all_dates = array_slice($all_dates, $start_key, 6);
						?>
                        <div class="_xs_equalChild ">
							<?php foreach ($all_dates as $date) { ?>
								<?php $btn_class = strtotime($date) == strtotime($active_date) ? 'active' : ''; ?>
                                <button type="button" class="wbtm_next_date <?php echo esc_attr($btn_class); ?>" data-date="<?php echo esc_attr($date); ?>">
									<?php echo esc_html(WBTM_Global_Function::date_format($date)); ?>
                                </button>
							<?php } ?>
                        </div>
						<?php
					}
				} else {
					WBTM_Layout::msg(WBTM_Translations::text_bus_close_msg());
				}
			}
			public static function route_title_old($start_route, $end_route, $j_date, $r_date, $return = false) {
				$start = $return ? $end_route : $start_route;
				$end = $return ? $start_route : $end_route;
				$date = $return ? $r_date : $j_date;
				if ($date) {
					?>
                    <div>
						<?php echo esc_html($start); ?>
                        <span class="fas fa-long-arrow-alt-right _mLR_xs"></span>
						<?php echo esc_html($end); ?>
                    </div>
                    <div>
						<?php echo esc_attr(WBTM_Global_Function::date_format($date)); ?>
                    </div>
					<?php
				}
			}
            public static function route_title($start_end,$start_route,$end_route,$j_date,$r_date,$return = false) {
                $start = $return ? $end_route : $start_route;
                $end = $return ? $start_route : $end_route;
                $date = $return ? $r_date : $j_date;

                // Weekday must match the leg's own date (the return leg may be rolled to a later day).
                $day = date_i18n("l", strtotime($date));
                $start_loc = strtoupper(substr($start, 0, 3));
                $end_loc = strtoupper(substr($end, 0, 3));

                $show_hide_class = 'wbtm_departure_icon';
                if( $start_end ==='Return'){
                    $show_hide_class = 'wbtm_return_icon';
                }
                if ($date) {
                    $label = WBTM_Global_Function::get_settings('wbtm_general_settings','label',esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'));
                    // Translate the start_end label
                    $translated_label = $start_end;
                    if ($start_end === 'Departure') {
                        $translated_label = esc_html__('Departure', 'bus-ticket-booking-with-seat-reservation').' '.$label;
                    } elseif ($start_end === 'Return') {
                        $translated_label = esc_html__('Return', 'bus-ticket-booking-with-seat-reservation').' '.$label;
                    }
                    ?>
                    <div class="wbtm_search_route_container">
                        <div class="wbtm_search_route_return_date">
                            <div class="wbtm_search_route_label"><?php echo esc_html( $translated_label );?></div>
                            <div class="wbtm_search_route_date"><?php echo esc_attr( WBTM_Global_Function::date_format($date) ); ?></div>
                            <div class="wbtm_search_route_day"><?php echo esc_attr( $day );?></div>
                        </div>
                        <div class="wbtm_search_route_cities_wrapper">
                            <div class="wbtm_search_route_city_section">
                                <div class="wbtm_search_route_city"><?php echo esc_attr( $start );?></div>
                                <div class="wbtm_search_route_airport_code"><?php echo esc_attr( $start_loc );?></div>
                            </div>
                            <div class="wbtm_search_route_icon_wrapper">
								<?php
									$default_icon_class = 'fas fa-bus';
									$redirect_icon = WBTM_Global_Function::get_settings('wbtm_general_settings', 'bus_search_list_direction_icon', 'fas fa-bus');
									$is_fa_icon = ( $redirect_icon && preg_match( '/^(fa[srlbdt]?)\s+fa-/', $redirect_icon ) );
									$is_image_id = ( $redirect_icon && is_numeric( $redirect_icon ) );
								?>
                                <span class="wbtm_search_route_bus_icon">
									<?php if ( $is_fa_icon ) : ?>
										<i class="<?php echo esc_attr( $redirect_icon ); ?>"></i>
									<?php elseif ( $is_image_id ) : ?>
										<img src="<?php echo esc_url( wp_get_attachment_url( $redirect_icon ) ); ?>" alt="">
									<?php else : ?>
										<i class="<?php echo esc_attr( $default_icon_class ); ?>"></i>
									<?php endif; ?>
								</span>
                            </div>
                            <div class="wbtm_search_route_city_section wbtm_search_route_city_section_right">
                                <div class="wbtm_search_route_city"><?php echo esc_attr( $end );?></div>
                                <div class="wbtm_search_route_airport_code"><?php echo esc_attr( $end_loc );?></div>
                            </div>
                            <div class="wbtm_search_route_dropdown_icon" style="display: none">
                                <span class=" <?php echo esc_attr( $show_hide_class );?> wbtm_search_route_arrow_icon">
									<i class="fas fa-chevron-down"></i>
								</span>
                            </div>
                        </div>
                    </div>

                    <?php
                }
            }

			public static function journey_date_picker($post_id = '', $start_route = '', $end_route = '', $date = '') {
				$date_format = WBTM_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
                <label class="wtbm_fdColumn">
					<?php echo esc_html(WBTM_Translations::text_journey_date()); ?>
                    <div class="calendar">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="hidden" name="j_date" value="<?php echo esc_attr($hidden_date); ?>" required/>
                        <input id="wbtm_journey_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl " placeholder="<?php echo esc_attr($now); ?>" data-alert="<?php echo esc_html(WBTM_Translations::text_select_route()); ?>" readonly required/>
                    </div>
                </label>
				<?php
			if ($start_route) {
				$all_dates = WBTM_Functions::get_all_dates($post_id, $start_route, $end_route);
				// Render the calendar instantly; sold-out greying loads as a separate async
				// "chunk" (see get_wbtm_soldout_dates) so the heavy availability scan never
				// blocks the date picker.
				$async = self::soldout_highlight_enabled()
					? ['post_id' => $post_id, 'start' => $start_route, 'end' => $end_route, 'leg' => 'outbound']
					: [];
				do_action('wbtm_load_date_picker_js', '#wbtm_journey_date', $all_dates, [], $async);
			}
		}
			/**
			 * Whether sold-out dates should be greyed-out in the date picker.
			 *
			 * Computing this scans seat availability for every date in the sales window,
			 * so it is opt-in (default OFF) to keep the calendar/schedule load fast.
			 */
			public static function soldout_highlight_enabled() {
				return WBTM_Global_Function::get_settings( 'wbtm_general_settings', 'calendar_soldout_highlight', 'off' ) === 'on';
			}
			public static function return_date_picker($post_id = '', $end_route = '', $start_route = '', $j_date = '', $date = '') {
				$date_format = WBTM_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
                <label class="wtbm_fdColumn">
					<?php echo esc_html(WBTM_Translations::text_return_date()); ?>
                    <div class="calendar">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="hidden" name="r_date" value="<?php echo esc_attr($hidden_date); ?>"/>
                        <input id="wbtm_return_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
                    </div>
                </label>
				<?php
			if ($end_route && $j_date) {
				$all_dates = WBTM_Functions::get_all_dates($post_id, $end_route, $start_route);
				if (sizeof($all_dates) > 0) {
					$j_date = strtotime($j_date);
					$date_list = [];
					foreach ($all_dates as $date) {
						if (strtotime($date) >= $j_date) {
							$date_list[] = $date;
						}
					}
					// Instant render; sold-out greying arrives via the async chunk, filtered
					// server-side to dates on/after the outbound journey date.
					$async = self::soldout_highlight_enabled()
						? ['post_id' => $post_id, 'start' => $end_route, 'end' => $start_route, 'leg' => 'return', 'j_date' => gmdate('Y-m-d', $j_date)]
						: [];
					do_action('wbtm_load_date_picker_js', '#wbtm_return_date', $date_list, [], $async);
				}
			}
			}
			public static function msg($msg, $class = '') {
				?>
                <div class="_mZero_textCenter <?php echo esc_attr($class); ?>">
                    <label class="_textTheme"><?php echo esc_html($msg); ?></label>
                </div>
				<?php
			}
			public static function trigger_view_seat_details() {
				?>
                <script type="text/javascript">
                    var get_wbtm_bus_details = document.getElementById("get_wbtm_bus_details");
                    get_wbtm_bus_details.click();
                </script>
				<?php
			}
		}
		new WBTM_Layout();
	}
