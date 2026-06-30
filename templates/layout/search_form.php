<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	//================//
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$post_id = $post_id ?? 0;
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$params = $params ?? [];
//	wbtm_load_search_form($post_id, $params);
//	function wbtm_load_search_form($post_id, $params) {

		$style = array_key_exists('style', $params) ? $params['style'] : '';
		$form_style = array_key_exists('style', $params) ? $params['style'] : '';
		$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
//		$left_filter = array_key_exists('left_filter_input', $params) ? $params['left_filter_input'] : '';
		$left_filter = array_key_exists('left_filter', $params) ? $params['left_filter'] : '';
		if (is_page()) {
			$left_filter = $left_filter ?: 'on';
		} else {
			$left_filter = 'off';
		}

		$left_filter_type = array_key_exists('left_filter_type', $params) && $params['left_filter_type']? $params['left_filter_type'] : 'on';
		$left_filter_operator = array_key_exists('left_filter_operator', $params) && $params['left_filter_operator']? $params['left_filter_operator'] : 'on';
		$left_filter_boarding = array_key_exists('left_filter_boarding', $params) && $params['left_filter_boarding']? $params['left_filter_boarding'] : 'on';
        //====================//
		$start_route = '';
		$end_route = '';
		$start_time = '';
		$end_time = '';
		// Allow processing of GET parameters for direct links (shareable URLs)
	// OR when nonce is present and valid (form submissions)
	$has_search_params = isset($_GET['bus_start_route']) || isset($_GET['bus_end_route']) || isset($_GET['j_date']);
	$nonce_valid = isset($_GET['wbtm_form_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['wbtm_form_nonce'])), 'wbtm_form_nonce');
	
	if ($nonce_valid || $has_search_params) {

			$start_route = isset($_POST['wbtm_bp_place']) ? sanitize_text_field(wp_unslash($_POST['wbtm_bp_place'])) : '';
			$start_route = $start_route ?: (isset($_GET['bus_start_route']) ? sanitize_text_field(wp_unslash($_GET['bus_start_route'])) : '');
			//===============//
			$start_time = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
			$start_time = $start_time ?: (isset($_GET['j_date']) ? sanitize_text_field(wp_unslash($_GET['j_date'])) : '');
			$start_time = $start_time ? gmdate('Y-m-d', strtotime($start_time)) : '';
			//===============//
			$end_route = isset($_POST['wbtm_dp_place']) ? sanitize_text_field(wp_unslash($_POST['wbtm_dp_place'])) : '';
			$end_route = $end_route ?: (isset($_GET['bus_end_route']) ? sanitize_text_field(wp_unslash($_GET['bus_end_route'])) : '');
			//===============//
			$end_time = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
			$end_time = $end_time ?: (isset($_GET['r_date']) ? sanitize_text_field(wp_unslash($_GET['r_date'])) : '');
			$end_time = $end_time ? gmdate('Y-m-d', strtotime($end_time)) : '';


            $left_filter = isset($_GET['wbtm_left_filter_show']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_show'])) : 'off';
            $left_filter_type = isset($_GET['wbtm_left_filter_type']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_type'])) : 'on';
            $left_filter_operator = isset($_GET['wbtm_left_filter_operator']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_operator'])) : 'on';
            $left_filter_boarding = isset($_GET['wbtm_left_filter_boarding']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_boarding'])) : 'on';
			//===============//
		}
		$return_date_show = WBTM_Global_Function::get_settings('wbtm_general_settings', 'bus_return_show', 'enable');
		$buy_ticket_text = WBTM_Translations::text_buy_ticket();
		$placeholder_text = WBTM_Translations::text_please_select();
		$global_settings = get_option('wbtm_general_settings');
		$btn_show = (is_array($global_settings) && array_key_exists('show_hide_view_seats_button', $global_settings)) ? $global_settings['show_hide_view_seats_button'] : 'show';
		/****************************/
		$active_redirect_page = WBTM_Global_Function::get_settings('wbtm_general_settings', 'active_redirect_page', 'off');
		$search_page_redirect = WBTM_Global_Function::get_settings('wbtm_general_settings', 'search_page_redirect');
		$redirect_url = $active_redirect_page == 'on' && $search_page_redirect && $post_id == 0 ? get_home_url() . '/' . get_page_uri($search_page_redirect) : '';
		$redirect_url = is_admin() ? '' : $redirect_url;
		/*********************************/
		$search_info['bus_start_route'] = $start_route;
		$search_info['bus_end_route'] = $end_route;
		$search_info['j_date'] = $start_time;
		$search_info['r_date'] = $end_time;



        $left_filter_show = array(
            'left_filter_input'     => $left_filter,
            'left_filter_type'     => $left_filter_type,
            'left_filter_operator' => $left_filter_operator,
            'left_filter_boarding' => $left_filter_boarding,
        );
		?>
        <div id="wbtm_area">
            <style>
            /* ============================================================
               WBTM Pill-Bar Search Form — scoped to .wbtm-bar-redesign
               All rules prefixed with #wbtm_area .wbtm-bar-redesign so
               they never bleed into other plugin areas.
               ============================================================ */

            /* Outer wrapper */
            #wbtm_area .wbtm_search_area.wbtm-bar-redesign {
                background: transparent;
                padding: 0;
            }
            #wbtm_area .wbtm-bar-redesign h4 {
                display: none;
            }

            /* ── Pill container ──────────────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_input_fields_holder {
                display:      flex;
                flex-wrap:    nowrap;
                align-items:  stretch;
                background:   #ffffff;
                border-radius: 10px;
                border:       1.5px solid #dde1e7;
                box-shadow:   0 2px 16px rgba(0,0,0,.09);
                min-height:   64px;
                padding:      0;
            }

            /* ── Row groups ──────────────────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wbtm_input_fields_holder,
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location,
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_date {
                display:        flex;
                flex-direction: row;
                align-items:    stretch;
                flex:           1;
                min-width:      0;
            }

            /* ── Each field segment ──────────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList {
                flex:         1;
                min-width:    110px;
                position:     relative;
                padding:      30px 40px;
                border-right: 1.5px solid #dde1e7;
                display:      flex;
                align-items:  center;
                cursor:       pointer;
                transition:   background 0.15s ease;
            }
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList:last-child {
                border-right: none;
            }
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList:hover {
                background: #f5f7fb;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location .wtbm_inputList:first-child:hover {
                border-radius: 9px 0 0 9px;
            }

            /* ── Field label ("From", "Journey Date" …) ──────────── */
            #wbtm_area .wbtm-bar-redesign label.wtbm_fdColumn {
                display:        flex;
                flex-direction: column;
                width:          100%;
                font-size:      11px;
                font-weight:    700;
                color:          #777;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                line-height:    1.2;
                cursor:         pointer;
                margin:         0;
            }

            /* ── Icon + value row ────────────────────────────────── */
            /* wbtm.css sets .wbtm_search_area .marker i { position: absolute; left: 10px }
               and compensates with input { padding-left: 30px }.
               We reset both here so the flex gap controls spacing instead. */
            #wbtm_area .wbtm-bar-redesign .marker,
            #wbtm_area .wbtm-bar-redesign .calendar {
                position:    static !important;  /* override position:relative used as absolute-icon parent */
                display:     flex !important;
                align-items: center !important;
                gap:         7px !important;
                margin-top:  5px;
                font-size:   15px;
                color:       #1a1a1a;
            }
            #wbtm_area .wbtm-bar-redesign .marker > i,
            #wbtm_area .wbtm-bar-redesign .calendar > i {
                position:    static !important;  /* reset from position:absolute */
                top:         auto !important;
                left:        auto !important;
                transform:   none !important;
                font-size:   15px !important;
                color:       #666 !important;
                flex-shrink: 0 !important;
                width:       16px !important;
                text-align:  center !important;
            }

            /* ── Input styled as plain readable text ─────────────── */
            #wbtm_area .wbtm-bar-redesign .formControl {
                border:       0 !important;
                background:   transparent !important;
                padding:      0 !important;         /* removes original 30px-left that accommodated absolute icon */
                padding-left: 0 !important;
                margin:       0 !important;
                font-size:    15px !important;
                font-weight:  600 !important;
                color:        #1a1a1a !important;
                box-shadow:   none !important;
                outline:      none !important;
                height:       auto !important;
                line-height:  1.3 !important;
                width:        100%;
                min-width:    0;
                cursor:       pointer;
            }
            #wbtm_area .wbtm-bar-redesign .formControl::placeholder {
                color:       #aaa !important;
                font-weight: 400 !important;
            }

            /* ── Swap toggle ⇄ (sits inline between From and To) ─── */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle {
                flex:            0 0 auto;
                align-self:      center;
                width:           34px;
                height:          34px;
                margin:          0 2px;
                border-radius:   50%;
                background:      #fff;
                border:          1.5px solid #dde1e7;
                box-shadow:      0 1px 5px rgba(0,0,0,.09);
                display:         flex;
                align-items:     center;
                justify-content: center;
                cursor:          pointer;
                transition:      background 0.15s;
                z-index:         2;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle:hover {
                background: #f0f2f5;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle i {
                font-size:  13px;
                color:      #555;
                transition: transform 0.25s;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle.rotate i {
                transform: rotate(180deg);
            }

            /* ── Dropdown list — floats above content ─────────────── */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list {
                position:      absolute !important;
                top:           calc(100% + 8px) !important;
                left:          0 !important;
                width:         max-content !important;
                min-width:     100% !important;
                background:    #fff !important;
                border:        1.5px solid #dde1e7 !important;
                border-radius: 14px !important;
                box-shadow:    0 8px 28px rgba(0,0,0,.13) !important;
                z-index:       9999 !important;
                margin:        0 !important;
            }

            /* ── Search button section ───────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wtbm_bus_search_button_holder {
                display:     flex;
                align-items: center;
                padding:     6px;
                flex-shrink: 0;
            }
            /* Stack both buttons inside the holder; PHP inline style="display:none/block"
               controls which one is visible — we must NOT override display with !important
               or both buttons would show at once. */
            #wbtm_area .wbtm-bar-redesign .search_button_holder {
                display:        flex;
                flex-direction: column;
                align-items:    stretch;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_button_spacer {
                display: none !important;
            }

            /* ── Button holder: vertically center the button ────────── */
            #wbtm_area .wbtm-bar-redesign .search_button_holder {
                justify-content: center !important;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_button_spacer {
                display: none !important;
            }

            /* ── Search / Modify button ───────────────────────────── */
            /* No "display" property here — PHP inline style="display:none/block" controls
               which of the two buttons is shown. We only restyle the visible one. */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_action_button {
                border-radius: 50px !important;
                padding:       13px 26px !important;
                font-size:     15px !important;
                font-weight:   600 !important;
                white-space:   nowrap !important;
                height:        auto !important;
                line-height:   1.3 !important;
                align-items:   center !important;
                gap:           7px !important;
                border:        none !important;
                cursor:        pointer;
                text-align:    center;
            }

            /* ── Mobile: stack vertically ────────────────────────── */
            @media (max-width: 767px) {
                #wbtm_area .wbtm-bar-redesign .wbtm_search_input_fields_holder {
                    flex-direction: column;
                    border-radius:  10px;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_input_fields_holder,
                #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location,
                #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_date {
                    flex-direction: column;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_inputList {
                    border-right:  none;
                    border-bottom: 1.5px solid #dde1e7;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_inputList:last-child {
                    border-bottom: none;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location {
                    position: relative;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle {
                    position:  absolute;
                    right:     14px;
                    top:       50%;
                    transform: translateY(-50%);
                    margin:    0;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_bus_search_button_holder {
                    padding: 10px;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_search_action_button {
                    width:           100% !important;
                    justify-content: center !important;
                }
            }
            </style>

            <div class="_dLayout wbtm_search_area wbtm-bar-redesign <?php echo esc_attr($form_style_class); ?>">
				<?php if ($buy_ticket_text) { ?>
                    <h4><?php echo esc_html($buy_ticket_text); ?></h4>
				<?php } ?>
                <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <form action="<?php echo esc_attr($redirect_url); ?>" method="get" class="mpForm">

                    <input type="hidden" name='wbtm_list_style' value="<?php echo esc_attr($style); ?>"/>
                    <input type="hidden" name='wbtm_list_btn_show' value="<?php echo esc_attr($btn_show); ?>"/>
                    <?php /* Redesigned bar always enables the left filter sidebar regardless of is_page() context */ ?>
                    <input type="hidden" name='wbtm_left_filter_show' value="on"/>
                    <input type="hidden" name='wbtm_left_filter_type' value="<?php echo esc_attr($left_filter_type); ?>"/>
                    <input type="hidden" name='wbtm_left_filter_operator' value="<?php echo esc_attr($left_filter_operator); ?>"/>
                    <input type="hidden" name='wbtm_left_filter_boarding' value="<?php echo esc_attr($left_filter_boarding); ?>"/>

					<?php wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce'); ?>
					<?php if (is_admin()) { ?>
                        <input type="hidden" name="post_type" value="wbtm_bus"/>
                        <input type="hidden" name="page" value="wbtm_backend_order"/>
					<?php } ?>

                    <div class="wbtm_search_input_fields_holder">
                        <div class="wbtm_input_fields_holder">
                            <div class="wbtm_input_start_end_location">
                                
                                <div class="wtbm_inputList wbtm_input_select wbtm_start_point">
                                    <label class="wtbm_fdColumn">
                                        <?php echo esc_html( WBTM_Translations::text_from() ); ?>
                                        <div class="marker">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <input type="text" class="formControl" name="bus_start_route" id="bus_start_route" value="<?php echo esc_attr( $start_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                                        </div>
                                    </label>
                                    <?php WBTM_Layout::route_list( $post_id ); ?>
                                </div>
                                <div class="wbtm_search_location_toggle" id="wbtm_search_location_toggle" title="Swap locations">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="wtbm_inputList wbtm_input_select wbtm_dropping_point" data-alert="<?php echo esc_html( WBTM_Translations::text_select_wrong_route() ); ?>">
                                    <label class="wtbm_fdColumn ">
                                        <?php echo esc_html( WBTM_Translations::text_to() ); ?>
                                        <div class="marker">
                                            <i class="fas fa-map-marker-alt wtbm_icon_margin"></i>
                                            <input type="text" class="formControl" name="bus_end_route" value="<?php echo esc_attr( $end_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                                        </div>
                                    </label>
                                    <?php WBTM_Layout::route_list( $post_id ); ?>
                                </div>
                            </div>
                            <div class="wbtm_input_start_end_date">
                                <div class="wtbm_inputList wbtm_journey_date">
                                    <?php WBTM_Layout::journey_date_picker( $post_id, $start_route, $end_route, $start_time ); ?>
                                </div>
                                <?php if ( $return_date_show == 'enable' && ( $post_id == 0 || WBTM_Functions::is_same_bus_return_enabled( $post_id ) ) ) { ?>
                                    <div class="wtbm_inputList wbtm_return_date">
                                        <?php WBTM_Layout::return_date_picker( $post_id, $end_route, $start_route, $start_time, $end_time ); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="wtbm_bus_search_button_holder" style="display: flex">
                            <div class="_dFlex_fdColumn_justifyBetween_fullHeight search_button_holder">
                                <span class="wbtm_search_button_spacer" aria-hidden="true">&nbsp;</span>
                                <?php if ( $active_redirect_page == 'on' && $search_page_redirect ) {
                                    $redirect_btn_display = 'block';
                                    $ajax_btn_display = 'none';
                                }else{
                                    $redirect_btn_display = 'none';
                                    $ajax_btn_display = 'block';
                                }?>
                                    <button type="submit" class="_themeButton_radius wbtm_bus_submit wbtm_search_action_button" data-loading-text="<?php echo esc_attr__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ); ?>" style="display: <?php echo esc_attr( $redirect_btn_display );?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} else { ?>
                                    <button type="button" class="_themeButton_radius get_wbtm_bus_list wbtm_search_action_button" data-loading-text="<?php echo esc_attr__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ); ?>" style=" display: <?php echo esc_attr( $ajax_btn_display ); ?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} ?>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="_ovHidden wbtm_search_result">
				<?php WBTM_Layout::wbtm_bus_list($post_id, $start_route, $end_route, $start_time, $end_time, $style, $btn_show, $search_info, $left_filter_show ); ?>
            </div>
        </div>
		<?php
//do_action('wbtm_after_search_list');
//	}
