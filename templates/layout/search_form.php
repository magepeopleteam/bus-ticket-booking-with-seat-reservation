<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	//================//
	$start_route = MP_Global_Function::get_submit_info( 'wbtm_bp_place' );
	$start_route = $start_route ?: MP_Global_Function::get_submit_info_get_method( 'bus_start_route' );
	$start_time  = MP_Global_Function::get_submit_info( 'j_date' );
	$start_time  = $start_time ?: MP_Global_Function::get_submit_info_get_method( 'j_date' );
	$start_time  = $start_time ? date( 'Y-m-d', strtotime( $start_time ) ) : '';
	//===============//
	$end_route = MP_Global_Function::get_submit_info( 'wbtm_dp_place' );
	$end_route = $end_route ?: MP_Global_Function::get_submit_info_get_method( 'bus_end_route' );
	$end_time  = MP_Global_Function::get_submit_info( 'r_date' );
	$end_time  = $end_time ?: MP_Global_Function::get_submit_info_get_method( 'r_date' );
	$end_time  = $end_time ? date( 'Y-m-d', strtotime( $end_time ) ) : '';
	//================//
	$post_id          = $post_id ?? 0;
	$return_date_show = MP_Global_Function::get_settings( 'wbtm_general_settings', 'bus_return_show', 'enable' );
	//================//
	$form_style       = $form_style ?? '';
	$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
	//================//
	$buy_ticket_text  = WBTM_Translations::text_buy_ticket();
	$placeholder_text = WBTM_Translations::text_please_select();
	$style            = $style ?? '';
	$global_settings  = get_option( 'wbtm_general_settings' );
	$btn_show         = ( is_array( $global_settings ) && array_key_exists( 'show_hide_view_seats_button', $global_settings ) ) ? $global_settings['show_hide_view_seats_button'] : 'show';
	/****************************/
	$active_redirect_page = MP_Global_Function::get_settings( 'wbtm_general_settings', 'active_redirect_page', 'off' );
	$search_page_redirect = MP_Global_Function::get_settings( 'wbtm_general_settings', 'search_page_redirect' );
	$redirect_url         = $active_redirect_page == 'on' && $search_page_redirect && $post_id == 0 ? get_home_url() . '/' . get_page_uri( $search_page_redirect ) : '';
	$redirect_url         = is_admin() ? '' : $redirect_url;
    /*********************************/
	$search_info['bus_start_route']=$start_route;
	$search_info['bus_end_route']=$end_route;
	$search_info['j_date']=$start_time;
	$search_info['r_date']=$end_time;
?>
    <div id="wbtm_area">
        <input type="hidden" name='wbtm_list_style' value="<?php echo esc_attr( $style ); ?>"/>
        <input type="hidden" name='wbtm_list_btn_show' value="<?php echo esc_attr( $btn_show ); ?>"/>
        <div class="_dLayout_dShadow_1 wbtm_search_area <?php echo esc_attr( $form_style_class ); ?>">
			<?php if ( $buy_ticket_text ) { ?>
                <h4><?php echo esc_html( $buy_ticket_text ); ?></h4>
			<?php } ?>
            <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr( $post_id ); ?>"/>
            <form action="<?php echo esc_attr( $redirect_url ); ?>" method="get" class="mpForm">
                <?php wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce'); ?>
				<?php if ( is_admin() ) { ?>
                    <input type="hidden" name="post_type" value="wbtm_bus"/>
                    <input type="hidden" name="page" value="wbtm_backend_order"/>
				<?php } ?>
                <div class="inputList mp_input_select wbtm_start_point">
                    <label class="fdColumn">
                        <span><i class="fas fa-map-marker"></i> <?php echo WBTM_Translations::text_from(); ?> : </span>
                        <input type="text" class="formControl" name="bus_start_route" value="<?php echo esc_attr( $start_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                    </label>
					<?php WBTM_Layout::route_list( $post_id ); ?>
                </div>
                <div class="inputList mp_input_select wbtm_dropping_point" data-alert="<?php echo WBTM_Translations::text_select_wrong_route(); ?>">
                    <label class="fdColumn ">
                        <span><i class="fas fa-map-marker"></i> <?php echo esc_html( WBTM_Translations::text_to() ); ?> : </span>
                        <input type="text" class="formControl" name="bus_end_route" value="<?php echo esc_attr( $end_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                    </label>
					<?php WBTM_Layout::route_list( $post_id ); ?>
                </div>
                <div class="inputList wbtm_journey_date">
					<?php WBTM_Layout::journey_date_picker( $post_id, $start_route, $end_route, $start_time ); ?>
                </div>
				<?php if ( $return_date_show == 'enable' && $post_id == 0 ) { ?>
                    <div class="inputList wbtm_return_date">
						<?php WBTM_Layout::return_date_picker( $post_id, $end_route, $start_route, $start_time, $end_time ); ?>
                    </div>
				<?php } ?>
                <div class="inputList">
                    <div class="_dFlex_fdColumn_justifyBetween_fullHeight">
                        <span>&nbsp;</span>
						<?php if ( $active_redirect_page == 'on' && $search_page_redirect ) { ?>
                            <button type="submit" class="_themeButton_radius wbtm_bus_submit">
                                <span class="fas fa-search mR_xs"></span><?php echo WBTM_Translations::text_search(); ?>
                            </button>
						<?php } else { ?>
                            <button type="button" class="_themeButton_radius get_wbtm_bus_list">
                                <span class="fas fa-search mR_xs"></span><?php echo WBTM_Translations::text_search(); ?>
                            </button>
						<?php } ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="_ovHidden wbtm_search_result">
			<?php WBTM_Layout::wbtm_bus_list( $post_id, $start_route, $end_route, $start_time, $end_time, $style, $btn_show,$search_info ); ?>
        </div>
    </div>
<?php
//do_action('wbtm_after_search_list');