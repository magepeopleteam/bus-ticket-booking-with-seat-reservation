<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('WBTM_Single_Bus_Details')) {
    class WBTM_Single_Bus_Details{
        public function __construct() {
            add_action('wp_ajax_wbtm_load_bus_details', array( $this, 'wbtm_load_bus_details' ) );
            add_action('wp_ajax_nopriv_wbtm_load_bus_details',array( $this,  'wbtm_load_bus_details' ) );
        }

        function wbtm_load_bus_details() {
            check_ajax_referer( 'wtbm_ajax_nonce', 'nonce' );

            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : '';
            if (!$post_id) {
                wp_die();
            }

            ob_start();
            self::wbtm_render_bus_details_popup( $post_id );
            echo ob_get_clean();
            wp_die();
        }

        public static function wbtm_render_bus_details_popup( $post_id = 0 ) {

            if ( ! defined( 'ABSPATH' ) ) {
                return;
            }

            $post_id = $post_id ? intval( $post_id ) : get_the_ID();
            if ( ! $post_id ) {
                return;
            }

            // Make post data available
            $post = get_post( $post_id );
            if ( ! $post ) {
                return;
            }
            setup_postdata( $post );

            $full_route_infos = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_route_info', [] );
            $bus_id           = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_no' );

            // Terms & Conditions
            $all_term_condition   = get_option( 'wbtm_term_condition_list', [] );
            $added_term_condition = get_post_meta( $post_id, 'wbtm_term_condition_list', true );
            $selected_term_condition = [];

            if ( ! empty( $added_term_condition ) && ! empty( $all_term_condition ) ) {
                foreach ( $added_term_condition as $term_key ) {
                    if ( isset( $all_term_condition[ $term_key ] ) ) {
                        $selected_term_condition[ $term_key ] = $all_term_condition[ $term_key ];
                    }
                }
            }

            // Features
            $all_features        = WTBM_Features_Seating::get_all_bus_features();
            $selected_feature_ids = get_post_meta( $post_id, 'wbbm_bus_features_term_id', true );
            $feature_lists       = WBTM_Functions::getSelectedFeatures( $all_features, $selected_feature_ids );
            ?>

            <div class="_dLayout_dShadow_1" style="border-radius: 10px">
                <div class="flexWrap">
                    <div class="wbtm_bus_details_holder">
                        <div class="mR">
                            <?php WBTM_Custom_Layout::bg_image_new( $post_id ); ?>
                        </div>
                    </div>

                    <div class="wbtm_bus_details_holder" >

                        <div class="wbtm_bus_detail_popup_tabs">
                            <div class="wbtm_bus_detail_popup_tab" id="wbtm_bus_detail_popup_tab"><?php esc_html_e( 'Bus Details', 'bus-ticket-booking-with-seat-reservation' );?></div>
                            <div class="wbtm_bus_detail_popup_tab" id="wbtm_bus_boarding_dropping_popup_tab"><?php esc_html_e( 'Boarding & Dropping', 'bus-ticket-booking-with-seat-reservation' );?></div>
                            <div class="wbtm_bus_detail_popup_tab" id="wbtm_bus_feature_popup_tab"><?php esc_html_e( 'Bus Feature', 'bus-ticket-booking-with-seat-reservation' );?></div>
                            <div class="wbtm_bus_detail_popup_tab" id="wbtm_bus_term_condition_popup_tab"><?php esc_html_e( 'Bus Term & Condition', 'bus-ticket-booking-with-seat-reservation' );?></div>
                            <div class="wbtm_bus_detail_popup_tab" id="wbtm_bus_photos_popup_tab"><?php esc_html_e( 'Images', 'bus-ticket-booking-with-seat-reservation' );?></div>
                        </div>

                        <div class="dLayout_xs" id="wbtm_bus_details_holder">
                            <h4>
                                <?php echo esc_html( get_the_title( $post_id ) ); ?>
                                <?php if ( $bus_id ) : ?>
                                    <small>( <?php echo esc_html( $bus_id ); ?> )</small>
                                <?php endif; ?>
                            </h4>

                            <div class="divider"></div>

                            <h6>
                                <strong><?php echo esc_html( WBTM_Translations::text_coach_type() ); ?> :</strong>
                                <?php echo esc_html( WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_category' ) ); ?>
                            </h6>

                            <h6>
                                <strong><?php echo esc_html( WBTM_Translations::text_passenger_capacity() ); ?> :</strong>
                                <?php echo esc_html( WBTM_Global_Function::get_post_info( $post_id, 'wbtm_get_total_seat', 0 ) ); ?>
                            </h6>

                            <div class="mp_wp_editor">
                                <?php echo apply_filters( 'the_content', $post->post_content ); ?>
                            </div>
                        </div>

                        <div class="flexEqual" id="wbtm_bus_boarding_dropping_holder">
                            <!-- Boarding Points -->
                            <div class="dLayout_xs mR_xs">
                                <h5><?php echo esc_html( WBTM_Translations::text_bp() ); ?></h5>
                                <div class="divider"></div>

                                <?php if ( ! empty( $full_route_infos ) ) : ?>
                                    <ul class="mp_list">
                                        <?php foreach ( $full_route_infos as $info ) : ?>
                                            <?php if ( $info['type'] === 'bp' || $info['type'] === 'both' ) : ?>
                                                <li>
                                                    <span class="fa fa-map-marker _mR_xs_textTheme"></span>
                                                    <?php
                                                    echo esc_html(
                                                        $info['place'] . ' (' .
                                                        WBTM_Global_Function::date_format( $info['time'], 'time' ) . ')'
                                                    );
                                                    ?>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <!-- Dropping Points -->
                            <div class="dLayout_xs" >
                                <h5><?php echo esc_html( WBTM_Translations::text_dp() ); ?></h5>
                                <div class="divider"></div>

                                <?php if ( ! empty( $full_route_infos ) ) : ?>
                                    <ul class="mp_list">
                                        <?php foreach ( $full_route_infos as $info ) : ?>
                                            <?php if ( $info['type'] === 'dp' || $info['type'] === 'both' ) : ?>
                                                <li>
                                                    <span class="fa fa-map-marker _mR_xs_textTheme"></span>
                                                    <?php
                                                    echo esc_html(
                                                        $info['place'] . ' (' .
                                                        WBTM_Global_Function::date_format( $info['time'], 'time' ) . ')'
                                                    );
                                                    ?>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Features -->
                        <?php if ( ! empty( $feature_lists ) ) : ?>
                            <div class="wtbm_term_wrapper_popup" id="wbtm_bus_feature_holder">
                                <h4><?php esc_html_e( 'Features', 'bus-ticket-booking-with-seat-reservation' ); ?></h4>
                                <?php foreach ( $feature_lists as $feature ) : ?>
                                    <div class="wbtm_bus_feature_items">
                                        <div class="wtbm_term_content">
                                            <span><?php echo esc_html( $feature['name'] ); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Terms & Conditions -->
                        <?php if ( ! empty( $selected_term_condition ) ) : ?>
                            <div class="wtbm_term_wrapper_popup" id="wbtm_bus_term_condition_holder">
                                <h4><?php esc_html_e( 'Terms & Condition', 'bus-ticket-booking-with-seat-reservation' ); ?></h4>

                                <?php foreach ( $selected_term_condition as $term ) : ?>
                                    <div class="wtbm_term_item">
                                        <div class="wtbm_term_header">
                                            <h5 class="wtbm_term_title"><?php echo esc_html( $term['title'] ); ?></h5>
                                        </div>
                                        <div class="wtbm_term_content">
                                            <p><?php echo wp_kses_post( $term['answer'] ); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            wp_reset_postdata();
        }


    }

    new WBTM_Single_Bus_Details();
}