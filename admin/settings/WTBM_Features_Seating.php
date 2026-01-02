<?php
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
        }

        public function wtbm_save_bus_features() {

            check_ajax_referer( 'wtbm_ajax_nonce', 'nonce' );

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
                    );
                }
            }

            return $features_array;
        }

        public function term_tab_content( $post_id ){

            $features = self::get_all_bus_features();
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
                                    <?php echo esc_html( $feature['name'] ); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
                <input type="hidden" id="wtbm_added_feature" name="wtbm_added_feature" value="<?php echo esc_attr( $selected );?>">
            </div>

        <?php }

    }

    new WTBM_Features_Seating();
}