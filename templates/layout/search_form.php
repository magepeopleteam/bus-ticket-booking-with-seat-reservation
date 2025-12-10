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
		if (isset($_GET['wbtm_form_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['wbtm_form_nonce'])), 'wbtm_form_nonce')) {
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
		?>
        <div id="wbtm_area">
            <input type="hidden" name='wbtm_list_style' value="<?php echo esc_attr($style); ?>"/>
            <input type="hidden" name='wbtm_list_btn_show' value="<?php echo esc_attr($btn_show); ?>"/>
            <input type="hidden" name='wbtm_left_filter_show' value="<?php echo esc_attr($left_filter); ?>"/>
            <input type="hidden" name='wbtm_left_filter_type' value="<?php echo esc_attr($left_filter_type); ?>"/>
            <input type="hidden" name='wbtm_left_filter_operator' value="<?php echo esc_attr($left_filter_operator); ?>"/>
            <input type="hidden" name='wbtm_left_filter_boarding' value="<?php echo esc_attr($left_filter_boarding); ?>"/>
            <div class="_dLayout wbtm_search_area <?php echo esc_attr($form_style_class); ?>">
				<?php if ($buy_ticket_text) { ?>
                    <h4><?php echo esc_html($buy_ticket_text); ?></h4>
				<?php } ?>
                <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <form action="<?php echo esc_attr($redirect_url); ?>" method="get" class="mpForm">
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
                                <?php if ( $return_date_show == 'enable' && $post_id == 0 ) { ?>
                                    <div class="wtbm_inputList wbtm_return_date">
                                        <?php WBTM_Layout::return_date_picker( $post_id, $end_route, $start_route, $start_time, $end_time ); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="wtbm_bus_search_button_holder" style="display: flex">
                            <div class="_dFlex_fdColumn_justifyBetween_fullHeight">
                                <span>&nbsp;</span>
                                <?php if ( $active_redirect_page == 'on' && $search_page_redirect ) {
                                    $redirect_btn_display = 'block';
                                    $ajax_btn_display = 'none';
                                }else{
                                    $redirect_btn_display = 'none';
                                    $ajax_btn_display = 'block';
                                }?>
                                    <button type="submit" class="_themeButton_radius wbtm_bus_submit" style="display: <?php echo esc_attr( $redirect_btn_display );?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} else { ?>
                                    <button type="button" class="_themeButton_radius get_wbtm_bus_list" style=" display: <?php echo esc_attr( $ajax_btn_display ); ?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} ?>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="_ovHidden wbtm_search_result">
				<?php WBTM_Layout::wbtm_bus_list($post_id, $start_route, $end_route, $start_time, $end_time, $style, $btn_show, $search_info); ?>
            </div>
        </div>
		<?php
//do_action('wbtm_after_search_list');
//	}
