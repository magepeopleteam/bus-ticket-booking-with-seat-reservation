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

            add_action('wp_ajax_mpcrbm_save_term_and_condition', [ $this, 'mpcrbm_save_term_and_condition' ]);
            add_action('wp_ajax_mpcrbm_delete_term', [ $this, 'mpcrbm_delete_term' ]);

        }

        public function mpcrbm_save_term_and_condition() {
            check_ajax_referer( 'wbtm_admin_nonce', 'nonce' );

            error_log( print_r( [ '$_POST' => $_POST ], true ) );
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
        public function mpcrbm_delete_term() {
            check_ajax_referer( 'wbtm_admin_nonce', 'nonce' );

            error_log( print_r( [ '$_POST' => $_POST ], true ) );

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
            <div class="mpcrbm_faq_container">
                <h2><?php esc_attr_e( 'Manage Term & Condition', 'car-rental-manager' );?></h2>
                <button id="mpcrbm_add_term_condition_btn" class="btn-primary"><i class="mi mi-plus"></i> <?php esc_attr_e( 'Term & Condition', 'car-rental-manager' );?></button>

                <table class="widefat mpcrbm_faq_table">
                    <thead>
                    <tr>
                        <th><?php esc_attr_e( 'Term Title', 'car-rental-manager' );?></th>
                        <th><?php esc_attr_e( 'Description', 'car-rental-manager' );?></th>
                        <th><?php esc_attr_e( 'Action', 'car-rental-manager' );?></th>
                    </tr>
                    </thead>
                    <tbody id="mpcrbm_term_condition_list">
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
                                    <button class="button mpcrbm_edit_term"><?php esc_attr_e( 'Edit', 'car-rental-manager' );?></button>
                                    <button class="button mpcrbm_delete_term"><?php esc_attr_e( 'Delete', 'car-rental-manager' );?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3"><?php esc_attr_e( 'No Term And Condition found.', 'car-rental-manager' );?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- Popup Modal -->
                <div id="mpcrbm_term_condition_modal" class="mpcrbm_faq_modal" style="display: none">
                    <div class="mpcrbm_modal_content">
                        <h3 id="mpcrbm_term_modal_title"><?php esc_attr_e( 'Add Term & Condition', 'car-rental-manager' );?></h3>
                        <input type="hidden" id="mpcrbm_term_condition_key" value="">
                        <label><?php esc_attr_e( 'Question', 'car-rental-manager' );?>:</label>
                        <input type="text" id="mpcrbm_term_condition_title" class=" mpcrbm_faq_title regular-text"><br><br>

                        <label>Answer:</label>
                        <div id="mpcrbm_term_condition_editor_container">
                            <?php
                            wp_editor( '', 'mpcrbm_term_condition_answer_editor', [
                                'textarea_name' => 'mpcrbm_term_condition_answer',
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
                        <button id="mpcrbm_save_term_condition_btn" class="button button-primary">Save</button>
                        <button id="mpcrbm_cancel_term_condition_btn" class="button">Cancel</button>
                    </div>
                </div>
            </div>
        <?php }

    }

    new WBTM_Term_Condition_Setting();
}