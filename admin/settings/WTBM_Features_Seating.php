<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

/*
	   * @Author 		MagePeople Team
	   * Copyright: 	mage-people.com
	   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}
if ( ! class_exists( 'WTBM_Features_Seating' ) ) {
    class WTBM_Features_Seating{
        public function __construct() {
            add_action( 'wbtm_add_settings_tab_content', [ $this, 'term_tab_content' ], 10, 1 );
            add_action('wp_ajax_wtbm_save_bus_features', [ $this, 'wtbm_save_bus_features' ] );
            add_action( 'wp_ajax_wbtm_bme_create_bus_feature', [ $this, 'ajax_create_bus_feature' ] );

            add_action( 'wbtm_bus_feature_add_form_fields', [ $this, 'wbtm_bus_feature_add_icon_field' ] );
            add_action( 'wbtm_bus_feature_edit_form_fields',  [ $this, 'wbtm_bus_feature_edit_icon_field' ] );
            add_action( 'created_wbtm_bus_feature', [ $this, 'wbtm_save_bus_feature_icon'] );
            add_action( 'edited_wbtm_bus_feature', [ $this, 'wbtm_save_bus_feature_icon'] );
        }
        function wbtm_save_bus_feature_icon( $term_id ) {
            if ( isset( $_POST['wbtm_bus_feature_icon'] ) ) {
                $bus_feature_icon = sanitize_text_field( wp_unslash( $_POST['wbtm_bus_feature_icon'] ) );
                update_term_meta( $term_id, 'wbtm_bus_feature_icon', $bus_feature_icon );
            }
        }


        function wbtm_bus_feature_add_icon_field() {
            do_action('wbtm_input_add_icon', 'wbtm_bus_feature_icon');
            ?>
            <?php
        }

        function wbtm_bus_feature_edit_icon_field( $term ) {
            $bus_feature_icon  = get_term_meta( $term->term_id, 'wbtm_bus_feature_icon', true );

            ?>
            <tr class="form-field term-icon-wrap">
                <th scope="row">
                    <label for="wbtm_bus_feature_icon"><?php esc_html_e( 'Feature Icon', 'car-rental-manager' ); ?></label>
                </th>
                <td>
                    <?php
                    do_action('wbtm_input_add_icon', 'wbtm_bus_feature_icon', $bus_feature_icon);
                    ?>
                </td>
            </tr>
            <?php
        }

        public function wtbm_save_bus_features() {

            check_ajax_referer( 'wtbm_ajax_nonce', 'nonce' );

            if ( ! current_user_can( 'edit_post', ( isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0 ) ) ) {
                wp_send_json_error( __( 'You do not have permission to perform this action.', 'bus-ticket-booking-with-seat-reservation' ) );
            }

            $post_id  = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : '';
            $features  = isset( $_POST['features'] ) ? sanitize_text_field( wp_unslash( $_POST['features'] ) ) : '';
            $feature_ids = [];
            if( $post_id ){
                $feature_ids = array_filter( array_map( 'intval', explode( ',', $features ) ) );
                update_post_meta( $post_id, 'wbbm_bus_features_term_id', $feature_ids );
            }


            wp_send_json_success( array(
                'saved_features' => $feature_ids,
            ) );
        }

        public function ajax_create_bus_feature() {
            check_ajax_referer( 'wtbm_ajax_nonce', 'nonce' );
            if ( ! current_user_can( 'manage_categories' ) ) {
                wp_send_json_error( 'Permission denied.' );
            }
            $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
            $name    = isset( $_POST['feature_name'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_name'] ) ) : '';
            $icon    = isset( $_POST['feature_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_icon'] ) ) : '';
            if ( ! $name ) {
                wp_send_json_error( 'Feature name is required.' );
            }
            $term = wp_insert_term( $name, 'wbtm_bus_feature' );
            if ( is_wp_error( $term ) ) {
                wp_send_json_error( $term->get_error_message() );
            }
            $term_id = (int) $term['term_id'];
            if ( $icon ) {
                update_term_meta( $term_id, 'wbtm_bus_feature_icon', $icon );
            }
            if ( $post_id ) {
                $current = get_post_meta( $post_id, 'wbbm_bus_features_term_id', true );
                if ( ! is_array( $current ) ) { $current = []; }
                $current[] = $term_id;
                update_post_meta( $post_id, 'wbbm_bus_features_term_id', $current );
            }
            wp_send_json_success( array(
                'term_id' => $term_id,
                'name'    => $name,
                'icon'    => $icon,
            ) );
        }

        public static function get_all_bus_features(){
            $bus_features = get_terms( array(
                'taxonomy'   => 'wbtm_bus_feature',
                'hide_empty' => false,
            ) );

            $features_array = array();

            if ( ! is_wp_error( $bus_features ) ) {
                foreach ( $bus_features as $feature ) {
                    $features_array[] = array(
                        'term_id' => $feature->term_id,
                        'name'    => $feature->name,
                        'slug'    => $feature->slug,
                        'parent'  => $feature->parent,
                        'icon'  => get_term_meta( $feature->term_id, 'wbtm_bus_feature_icon', true ),
                    );
                }
            }

            return $features_array;
        }

        public function term_tab_content( $post_id ){

            $features = self::get_all_bus_features();
//            error_log( print_r( [ '$features' => $features ], true ) );
            $get_selected_features = get_post_meta( $post_id, 'wbbm_bus_features_term_id', true );
            $selected = '';
            if( !empty( $get_selected_features ) ){
                $selected = implode( ',', $get_selected_features );
            }else{
                $get_selected_features = [];
            }

            ?>
            <div class="tabsItem" data-tabs="#wbtm_bus_feature_settings">

                <h3><?php esc_html_e('Feature Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <p><?php esc_html_e('Bus Feature', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                <div class="_dLayout_padding_bgLight" style="margin-bottom: 10px">
                    <div class="col_6 _dFlex_fdColumn">
                        <label>
                            <?php esc_html_e('Feature', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <span><?php esc_html_e('Here you can set bus feature', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                    </div>
                </div>

                <div class="wtbm_all_selected_term_condition">
                    <div class="wtbm_all_term_condition">
                        <h3><?php esc_html_e( 'Available Feature', 'car-rental-manager' ); ?></h3>

                        <div class="wtbm-bus-features">
                            <?php foreach ( $features as $feature ) : ?>
                                <label>

                                    <input type="checkbox"
                                           class="wtbm_bus_feature_checkbox"
                                           data-term-id="<?php echo esc_attr( $feature['term_id'] ); ?>"
                                        <?php checked( in_array( (int) $feature['term_id'], $get_selected_features, true ) ); ?>
                                    >
<!--                                    <span class="wbtm_bus_feature_icon"><i class="--><?php //echo esc_attr( $feature['icon']);?><!--"></i></span>--><?php //echo esc_html( $feature['name'] ); ?>
                                    <span class="wbtm_bus_feature_icon <?php echo esc_attr( $feature['icon']);?>"></span><?php echo esc_html( $feature['name'] ); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
                <input type="hidden" id="wtbm_added_feature" name="wtbm_added_feature" value="<?php echo esc_attr( $selected );?>">
                <?php ob_start(); do_action( 'wbtm_input_add_icon', 'wbtm_bme_feat_icon_trigger' ); ob_end_clean(); ?>
            </div>

        <?php }

    }

    new WTBM_Features_Seating();
}