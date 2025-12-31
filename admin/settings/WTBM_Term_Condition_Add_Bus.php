<?php
/*
	   * @Author 		MagePeople Team
	   * Copyright: 	mage-people.com
	   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}
if ( ! class_exists( 'WTBM_Term_Condition_Add_Bus' ) ) {
    class WTBM_Term_Condition_Add_Bus{

        private $term_option_key = 'wbtm_term_condition_list';
        public function __construct() {
            add_action( 'wbtm_add_settings_tab_content', [ $this, 'term_tab_content' ], 10, 1 );
            add_action('wp_ajax_wtbm_save_added_term_condition', [ $this, 'wtbm_save_added_term_condition' ] );
        }
        public function term_tab_content( $post_id ){

            $terms = get_option( $this->term_option_key, [] );
            $added_terms = get_post_meta( $post_id, $this->term_option_key, true );
            $selected_terms_data = [];
            if (!empty($added_terms) && !empty( $terms ) ) {
                foreach ($added_terms as $term_key) {
                    if (isset($terms[$term_key])) {
                        $selected_terms_data[$term_key] = $terms[$term_key];
                    }
                }
            }

            ?>
            <div class="tabsItem wbtm_settings_term_condition" data-tabs="#wbtm_settings_term_condition">

                <h3><?php esc_html_e('Term And Condition Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                <p><?php esc_html_e('Bus Term And Condition', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                <div class="_dLayout_padding_bgLight">
                    <div class="col_6 _dFlex_fdColumn">
                        <label>
                            <?php esc_html_e('Term And Condition', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </label>
                        <span><?php esc_html_e('Here you can set bus term & condition', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                    </div>
                </div>

                <div class="wtbm_all_selected_term_condition">
                    <div class="wtbm_all_term_condition">
                        <h3><?php esc_html_e( 'Available Term & Condition', 'car-rental-manager' ); ?></h3>
                        <div class="wtbm_term_all_question">
                            <?php if (!empty($terms)) : ?>
                                <?php foreach ($terms as $key => $term) :
                                    if ( isset( $selected_terms_data[$key] ) ) continue;
                                    ?>
                                    <div class="wtbm_term_item"
                                         data-key="<?php echo esc_attr($key); ?>"
                                         data-title="<?php echo esc_attr( $term['title'] ); ?>"
                                    >
                                        <div class="wtbm_term_title"><?php echo esc_html($term['title']); ?></div>
                                        <button type="button" class="button button-small wtbm_add_term_condition"><?php esc_html_e( 'Add', 'car-rental-manager' ); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p><?php esc_html_e( 'No Term & Condition available.', 'car-rental-manager' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="wtbm_selected_term_question_box">
                        <h3><?php esc_html_e( 'Added Term & Condition', 'car-rental-manager' ); ?></h3>
                        <div class="wtbm_selected_term_condition">
                            <?php if (!empty($selected_terms_data)) : ?>
                                <?php foreach ($selected_terms_data as $key => $term) : ?>
                                    <div class="wtbm_selected_item"
                                         data-key="<?php echo esc_attr($key); ?>"
                                         data-title="<?php echo esc_attr( $term['title'] ); ?>"
                                    >
                                        <div class="wtbm_term_title"><?php echo esc_html($term['title']); ?></div>
                                        <button type="button" class="button button-small wtbm_remove_term_condition"><?php esc_html_e( 'Remove', 'car-rental-manager' ); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p><?php esc_html_e( 'No Term & Condition added yet.', 'car-rental-manager' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="wtbm_added_term_condition_input" name="wtbm_added_term_condition" value="<?php echo esc_attr(json_encode($selected_terms_data)); ?>">
            </div>

        <?php }

        function wtbm_save_added_term_condition() {
            check_ajax_referer( 'wtbm_ajax_nonce', 'nonce' );

            $post_id =  isset( $_POST['wtbm_added_term']) ?  intval( $_POST['post_id']) : '';
            $data = isset( $_POST['wtbm_added_term']) ? json_decode(stripslashes( $_POST[ 'wtbm_added_term' ] ), true) : [];

            if (!current_user_can('edit_post', $post_id ) ) {
                wp_send_json_error(['message' => 'You do not have permission to edit this post.']);
            }
            if ( $post_id && is_array( $data ) ) {
                update_post_meta( $post_id, $this->term_option_key, $data );

                wp_send_json_success(['message' => 'FAQ saved successfully!', 'data' => $data]);
            } else {
                wp_send_json_error(['message' => 'Invalid data']);
            }
        }

    }

    new WTBM_Term_Condition_Add_Bus();
}