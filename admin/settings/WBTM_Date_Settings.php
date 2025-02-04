<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Date_Settings' ) ) {
		class WBTM_Date_Settings {
			public function __construct() {
				add_action( 'add_wbtm_settings_tab_content', [ $this, 'tab_content' ] );
				add_action( 'wbtm_settings_save', [ $this, 'settings_save' ] );
			}

			public function tab_content( $post_id ) {
				$date_format = MP_Global_Function::date_picker_format();
				$now         = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$date_type   = MP_Global_Function::get_post_info( $post_id, 'show_operational_on_day', 'no' );
				/*********************/
				$repeated_start_date         = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_start_date' );
				$hidden_repeated_start_date  = $repeated_start_date ? date( 'Y-m-d', strtotime( $repeated_start_date ) ) : '';
				$visible_repeated_start_date = $repeated_start_date ? date_i18n( $date_format, strtotime( $repeated_start_date ) ) : '';
				$repeated_end_date           = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_end_date' );
				$hidden_repeated_end_date    = $repeated_end_date ? date( 'Y-m-d', strtotime( $repeated_end_date ) ) : '';
				$visible_repeated_end_date   = $repeated_end_date ? date_i18n( $date_format, strtotime( $repeated_end_date ) ) : '';
				$repeated_after              = MP_Global_Function::get_post_info( $post_id, 'wbtm_repeated_after', 1 );
				$active_days                 = MP_Global_Function::get_post_info( $post_id, 'wbtm_active_days' );
				/******************************/
				$off_days      = MP_Global_Function::get_post_info( $post_id, 'wbtm_off_days' );
				$off_day_array = $off_days ? explode( ',', $off_days ) : [];
				$days          = MP_Global_Function::week_day();
				?>
                <div class="tabsItem" data-tabs="#wbtm_settings_date">
                    <h3><?php esc_html_e( 'Date Settings', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
                    <p><?php esc_html_e( 'Bus date settings will help to operation a bus in a particular or repeated date.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                    <div class="">
                        <div class="_dLayout_bgLight">
                            <div class="col_6 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e( 'Date Information', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                </label>
                                <span><?php esc_html_e( 'Here you can set bus seat booking date.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                            </div>
                        </div>
                        <div class="_dLayoutd_dFlex_alignCenter_">
                            <div class="col_8 _dFlex_fdColumn">
                                <label>
									<?php esc_html_e( 'Bus Operation Date Type', 'bus-ticket-booking-with-seat-reservation' ); ?><i class="textRequired">&nbsp;*</i>
                                </label>
                                <span>
									<?php WBTM_Settings::info_text( 'show_operational_on_day' ); ?>
								</span>
                            </div>
                            <div class="col_4 textRight">
                                <select class="formControl max_300" name="show_operational_on_day" data-collapse-target required>
                                    <option disabled selected><?php esc_html_e( 'Please select ...', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
                                    <option value="yes" data-option-target="#mp_particular" <?php echo esc_attr( $date_type == 'yes' ? 'selected' : '' ); ?>><?php esc_html_e( 'Particular', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
                                    <option value="no" data-option-target="#mp_repeated" <?php echo esc_attr( $date_type == 'no' ? 'selected' : '' ); ?>><?php esc_html_e( 'Repeated', 'bus-ticket-booking-with-seat-reservation' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="<?php echo esc_attr( $date_type == 'yes' ? 'mActive' : '' ); ?>" data-collapse="#mp_particular">
                            <div class="_dLayout_dFlex_justifyBetween">
                                <div class="col_8 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Particular Dates', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Particular Dates', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="mp_settings_area max_400 ">
                                    <div class="mp_item_insert mp_sortable_area">
										<?php
											$particular_date_lists = MP_Global_Function::get_post_info( $post_id, 'wbtm_particular_dates', array() );
											if ( sizeof( $particular_date_lists ) ) {
												foreach ( $particular_date_lists as $particular_date ) {
													if ( $particular_date ) {
                                                        $has_year = true;
														$this->particular_date_item( 'wbtm_particular_dates[]', $particular_date,$has_year);
													}
												}
											}
										?>
                                    </div>
                                    <div class="_dFlex_justifyEnd">
										<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add New Particular date', 'bus-ticket-booking-with-seat-reservation' ) ); ?>
                                    </div>
                                    <div class="mp_hidden_content">
                                        <div class="mp_hidden_item">
											<?php $this->particular_date_item( 'wbtm_particular_dates[]' ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="<?php echo esc_attr( $date_type == 'no' ? 'mActive' : '' ); ?>" data-collapse="#mp_repeated">
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Repeated Start Date', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Select repeated start date.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_6 textRight">
                                    <label>
                                        <input type="hidden" name="wbtm_repeated_start_date" value="<?php echo esc_attr( $hidden_repeated_start_date ); ?>"/>
                                        <input type="text" readonly name="" class="formControl date_type max_300" value="<?php echo esc_attr( $visible_repeated_start_date ); ?>" placeholder="<?php echo esc_attr( $now ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Repeated End Date', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Select repeated end date.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_6 textRight">
                                    <label>
                                        <input type="hidden" name="wbtm_repeated_end_date" value="<?php echo esc_attr( $hidden_repeated_end_date ); ?>"/>
                                        <input type="text" readonly name="" class="formControl max_300 date_type" value="<?php echo esc_attr( $visible_repeated_end_date ); ?>" placeholder="<?php echo esc_attr( $now ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Repeated after', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Set repeated date step count.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_6 textRight">
                                    <input type="text" name="wbtm_repeated_after" class="formControl max_300 mp_number_validation" value="<?php echo esc_attr( $repeated_after ); ?>"/>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Maximum advanced day for booking', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Set maximum advanced day for booking.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_6 textRight">
                                    <input type="text" name="wbtm_active_days" class="formControl max_300 mp_number_validation" value="<?php echo esc_attr( $active_days ); ?>"/>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                                <div class="col_2 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Off Day', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Select days for off day.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_10 textRight groupCheckBox">
                                    <input type="hidden" name="wbtm_off_days" value="<?php echo esc_attr( $off_days ); ?>"/>
									<?php foreach ( $days as $key => $day ) { ?>
                                        <label class="customCheckboxLabel max_200">
                                            <input type="checkbox" <?php echo esc_attr( in_array( $key, $off_day_array ) ? 'checked' : '' ); ?> data-checked="<?php echo esc_attr( $key ); ?>"/>
                                            <span class="customCheckbox"><?php echo esc_html( $day ); ?></span>
                                        </label>
									<?php } ?>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignStart">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Off Dates', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Select dates for off day.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_6 _dFlex_justifyEnd">
                                    <div class="mp_settings_area max_400">
                                        <div class="mp_item_insert mp_sortable_area">
											<?php
												$off_day_lists = MP_Global_Function::get_post_info( $post_id, 'wbtm_off_dates', array() );

												if ( sizeof( $off_day_lists ) ) {
													foreach ( $off_day_lists as $off_day ) {
														if ( $off_day ) {
															$has_year = true;
															$this->particular_date_item( 'wbtm_off_dates[]', $off_day, $has_year);
														}
													}
												}
											?>
                                        </div>
                                        <div class="_dFlex_justifyEnd">
											<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add New Off date', 'bus-ticket-booking-with-seat-reservation' ) ); ?>
                                        </div>
                                        <div class="mp_hidden_content">
                                            <div class="mp_hidden_item">
												<?php $this->particular_date_item( 'wbtm_off_dates[]' ); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="_dLayout_padding_dFlex_justifyBetween_alignStart">
                                <div class="col_6 _dFlex_fdColumn">
                                    <label>
										<?php esc_html_e( 'Off Dates in Range', 'bus-ticket-booking-with-seat-reservation' ); ?>
                                    </label>
                                    <span><?php esc_html_e( 'Select date range for off day.', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
                                </div>
                                <div class="col_8">
                                    <div class="mp_settings_area _fullWidth">
                                        <div class="mp_item_insert mp_sortable_area">
											<?php
												$off_day_ranges = MP_Global_Function::get_post_info( $post_id, 'wbtm_offday_range', array() );
												if ( sizeof( $off_day_ranges ) ) {
													foreach ( $off_day_ranges as $off_day_range ) {
														if ( sizeof( $off_day_range ) > 0 && $off_day_range['from_date'] && $off_day_range['to_date'] ) {
															$this->off_day_range( $off_day_range['from_date'], $off_day_range['to_date'] );
														}
													}
												}
											?>
                                        </div>
                                        <div class="_dFlex_justifyEnd">
											<?php MP_Custom_Layout::add_new_button( esc_html__( 'Add New Off date range', 'bus-ticket-booking-with-seat-reservation' ) ); ?>
                                        </div>
                                        <div class="mp_hidden_content">
                                            <div class="mp_hidden_item">
												<?php $this->off_day_range('wbtm_offday_range'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}

			public function particular_date_item( $name, $date = '', $has_year='' ) {
				?>
                <div class="mp_remove_area">
                    <div class="justifyBetween">
						<?php $this->date_item_without_year( $name, $date, $has_year ); ?>
						<?php MP_Custom_Layout::move_remove_button(); ?>
                    </div>
                    <div class="divider"></div>
                </div>
				<?php
			}

			public function off_day_range( $from_date = '', $to_date = '',$has_year='' ) {
				?>
                <div class="mp_remove_area">
                    <div class="justifyBetween">
						<?php $this->date_item_without_year( 'wbtm_from_off_date[]', $from_date,$has_year ); ?>
						<?php $this->date_item_without_year( 'wbtm_to_off_date[]', $to_date,$has_year ); ?>
						<?php MP_Custom_Layout::move_remove_button(); ?>
                    </div>
                    <div class="divider"></div>
                </div>
				<?php
			}

			public function date_item_without_year( $name, $date = '', $has_year= '' ) {
			
					$date_format  = MP_Global_Function::date_picker_format();
					$now          = date_i18n($date_format);
					$hidden_date  = $date ? date('Y-m-d', strtotime( $date )) : '';
					$visible_date = $date ? date_i18n($date_format, strtotime( $date )) : '';
					?>
					<label class="_fullWidth_mR">
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $hidden_date ); ?>"/>
						<input value="<?php echo esc_attr( $visible_date ); ?>" class="formControl date_type" placeholder="<?php echo esc_attr( $now ); ?>"/>
					</label>
					<?php
				
				
			}

			/*************************************/
			public function settings_save( $post_id ) {
				if ( get_post_type( $post_id ) == WBTM_Functions::get_cpt() ) {
					//************************************//
					$date_type = MP_Global_Function::get_submit_info( 'show_operational_on_day', 'no' );
					update_post_meta( $post_id, 'show_operational_on_day', $date_type );
					//**********************//
                    $particular_dates = MP_Global_Function::get_submit_info( 'wbtm_particular_dates', array() );
                    $particular = array();
                    if ( ! empty( $particular_dates ) ) {
                        foreach ( $particular_dates as $particular_date ) {
                            if ( ! empty( $particular_date ) ) {
                                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $particular_date ) ) {
                                    $particular[] = $particular_date;
                                } else {
                                    $particular[] = date( 'Y-m-d', strtotime( date( 'Y' ) . '-' . $particular_date ) );
                                }
                            }
                        }
                    }
                    update_post_meta( $post_id, 'wbtm_particular_dates', array_unique( $particular ) );
					//*************************//
					$repeated_start_date = MP_Global_Function::get_submit_info( 'wbtm_repeated_start_date' );
					$repeated_start_date = $repeated_start_date ? date( 'Y-m-d', strtotime( $repeated_start_date ) ) : '';
					update_post_meta( $post_id, 'wbtm_repeated_start_date', $repeated_start_date );
					//**********************//
					$repeated_end_date = MP_Global_Function::get_submit_info( 'wbtm_repeated_end_date' );
					$repeated_end_date = $repeated_end_date ? date( 'Y-m-d', strtotime( $repeated_end_date ) ) : '';
					update_post_meta( $post_id, 'wbtm_repeated_end_date', $repeated_end_date );
					//**********************//
					$repeated_after = MP_Global_Function::get_submit_info( 'wbtm_repeated_after', 1 );
					update_post_meta( $post_id, 'wbtm_repeated_after', $repeated_after );
					$active_days = MP_Global_Function::get_submit_info( 'wbtm_active_days' );
					update_post_meta( $post_id, 'wbtm_active_days', $active_days );
					//**********************//
					$off_days = MP_Global_Function::get_submit_info( 'wbtm_off_days', array() );
					update_post_meta( $post_id, 'wbtm_off_days', $off_days );
					//**********************//
					$off_dates  = MP_Global_Function::get_submit_info( 'wbtm_off_dates', array() );
					$_off_dates = array();
					if ( sizeof( $off_dates ) > 0 ) {
						foreach ( $off_dates as $off_date ) {
							if ( $off_date ) {
								$_off_dates[] = $off_date;
							}
						}
					}
					update_post_meta( $post_id, 'wbtm_off_dates', $_off_dates );
					//**********************//
					$off_schedules = [];
					$from_dates    = MP_Global_Function::get_submit_info( 'wbtm_from_date', array() );
					$to_dates      = MP_Global_Function::get_submit_info( 'wbtm_to_date', array() );
					if ( sizeof( $from_dates ) > 0 ) {
						foreach ( $from_dates as $key => $from_date ) {
							if ( $from_date && $to_dates[ $key ] ) {
								$off_schedules[] = [
									'from_date' => $from_date,
									'to_date'   => $to_dates[ $key ],
								];
							}
						}
					}
					update_post_meta( $post_id, 'wbtm_offday_schedule', $off_schedules );

                    //***********************************//
                    // Collect From and To Dates for Off Day Ranges
$off_date_ranges = [];
$from_dates      = MP_Global_Function::get_submit_info('wbtm_from_off_date', array());
$to_dates        = MP_Global_Function::get_submit_info('wbtm_to_off_date', array());

if (sizeof($from_dates) > 0) {
    foreach ($from_dates as $key => $from_date) {
        // Ensure both dates are present and valid
        if ($from_date && $to_dates[$key]) {
            $off_date_ranges[] = [
                'from_date' => $from_date,
                'to_date'   => $to_dates[$key],
            ];
        }
    }
}
update_post_meta($post_id, 'wbtm_offday_range', $off_date_ranges);

				}
			}
		}
		new WBTM_Date_Settings();
	}