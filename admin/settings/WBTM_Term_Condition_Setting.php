<?php

/*
	   * @Author 		MagePeople Team
	   * Copyright: 	mage-people.com
	   */
if ( ! defined( 'ABSPATH' ) ) {
    die;
} // Cannot access pages directly.

if ( ! class_exists( 'WBTM_Term_Condition_Setting' ) ) {
    class WBTM_Term_Condition_Setting{

        private $term_option_key = 'wbtm_term_condition_list';
        public function __construct(){

            add_action('wp_ajax_wtbm_save_term_and_condition', [ $this, 'wtbm_save_term_and_condition' ]);
            add_action('wp_ajax_wtbm_delete_term', [ $this, 'wtbm_delete_term' ]);

        }

        public function wtbm_save_term_and_condition() {
            check_ajax_referer( 'wbtm_admin_nonce', 'nonce' );

            $title  = sanitize_text_field( wp_unslash( $_POST['title']  ?? '' ) );
            $answer = wp_kses_post( $_POST['answer'] ?? '' );
            $key    = sanitize_text_field( $_POST['key'] ?? '' );

            if ( empty( $title ) || empty( $answer ) ) {
                wp_send_json_error( 'Title and Answer are required.' );
            }

            $term_condition = get_option( $this->term_option_key, [] );
            if ( $key === '' ) {
                $key = uniqid( 'term_' );
            }

            $term_condition[$key] = [
                'title'  => $title,
                'answer' => $answer,
            ];

            update_option( $this->term_option_key, $term_condition );

            wp_send_json_success( 'Saved successfully.' );
        }

        /**
         * Delete TERM (AJAX)
         */
        public function wtbm_delete_term() {
            check_ajax_referer( 'wbtm_admin_nonce', 'nonce' );

            $key = sanitize_text_field( $_POST['key'] ?? '' );
            $term_condition = get_option( $this->term_option_key, [] );

            if ( isset( $term_condition[$key] ) ) {
                unset( $term_condition[$key] );
                update_option( $this->term_option_key, $term_condition );
                wp_send_json_success( 'Term & Condition deleted.' );
            } else {
                wp_send_json_error( 'Term & Condition found.' );
            }
        }

        public static function term_and_condition_display(){
            $term_and_conditions = get_option( 'wbtm_term_condition_list', [] );
            ?>
            <div class="wtbm_taxonomies_content_holder">
                <div class="wtbm_faq_container">
                    <h2><?php esc_attr_e( 'Manage Term & Condition', 'bus-ticket-booking-with-seat-reservation' );?></h2>
                    <button id="wtbm_add_term_condition_btn" class="wtbm_add_term_condition_btn btn-primary"><i class="mi mi-plus"></i> <?php esc_attr_e( '+Term & Condition', 'bus-ticket-booking-with-seat-reservation' );?></button>

                    <table class="widefat wtbm_faq_table">
                        <thead>
                        <tr>
                            <th><?php esc_attr_e( 'Term Title', 'bus-ticket-booking-with-seat-reservation' );?></th>
                            <th><?php esc_attr_e( 'Description', 'bus-ticket-booking-with-seat-reservation' );?></th>
                            <th><?php esc_attr_e( 'Action', 'bus-ticket-booking-with-seat-reservation' );?></th>
                        </tr>
                        </thead>
                        <tbody id="wtbm_term_condition_list">
                        <?php if ( ! empty( $term_and_conditions ) ) : ?>
                            <?php foreach ( $term_and_conditions as $key => $term_and_condition ) :
                                ?>
                                <tr
                                    data-key="<?php echo esc_attr( $key ); ?>"
                                    data-title="<?php echo esc_attr( $term_and_condition['title'] ); ?>"
                                >
                                    <td class="faq-title">
                                        <?php echo array_key_exists( 'title', $term_and_condition ) ? esc_html( $term_and_condition['title'] ) : ''; ?>
                                    </td>

                                    <td class="faq-answer">
                                        <?php
                                        $answer = array_key_exists( 'answer', $term_and_condition ) ? $term_and_condition['answer'] : '';
                                        echo wp_kses_post( wp_trim_words( $answer, 15 ) );
                                        ?>
                                    </td>

                                    <td>
                                        <button class="button wtbm_edit_term"><?php esc_attr_e( 'Edit', 'bus-ticket-booking-with-seat-reservation' );?></button>
                                        <button class="button wtbm_delete_term"><?php esc_attr_e( 'Delete', 'bus-ticket-booking-with-seat-reservation' );?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3"><?php esc_attr_e( 'No Term And Condition found.', 'bus-ticket-booking-with-seat-reservation' );?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Popup Modal -->
                    <div id="wtbm_term_condition_modal" class="wtbm_term_modal" style="display: none">
                        <div class="wtbm_modal_content">
                            <input type="hidden" id="wtbm_term_condition_key" value="">
                            <label><?php esc_attr_e( 'Term', 'bus-ticket-booking-with-seat-reservation' );?>:</label>
                            <input type="text" id="wtbm_term_condition_title" class=" wtbm_faq_title regular-text"><br><br>

                            <label><?php esc_attr_e( 'Condition', 'bus-ticket-booking-with-seat-reservation' );?>:</label>
                            <div id="wtbm_term_condition_editor_container">
                                <?php
                                wp_editor( '', 'wtbm_term_condition_answer_editor', [
                                    'textarea_name' => 'wtbm_term_condition_answer',
                                    'textarea_rows' => 8,
                                    'media_buttons' => true,
                                    'editor_height' => 250,
                                    'tinymce' => [
                                        'toolbar1' => 'bold italic underline | bullist numlist | link unlink | undo redo | formatselect',
                                    ],
                                ] );
                                ?>
                            </div>

                            <br>
                            <button id="wtbm_save_term_condition_btn" class="button _term_condition_save_btn"><?php esc_attr_e( 'Save', 'bus-ticket-booking-with-seat-reservation' );?></button>
                            <button id="wtbm_cancel_term_condition_btn" class="button _term_condition_cancel_btn"><?php esc_attr_e( 'Cancel', 'bus-ticket-booking-with-seat-reservation' );?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php }

    }

    new WBTM_Term_Condition_Setting();
}