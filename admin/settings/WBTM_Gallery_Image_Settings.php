<?php
/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('WBTM_Gallery_Image_Settings')) {
    class WBTM_Gallery_Image_Settings{
        public function __construct() {
            add_action( 'wbtm_add_settings_tab_content', [$this,'add_tabs_content'] );
            add_action('save_post', array($this, 'settings_save'), 99, 1);
        }

        public function section_header(){
            ?>
            <h2><?php esc_html_e( 'Gallery Configuration', 'car-rental-manager' ); ?></h2>
            <p><?php esc_html_e( 'Here you can configure gallery', 'car-rental-manager' ); ?></p>

            <?php
        }

        public function panel_header( $title, $description ){
            ?>
            <section class="bg-light">
                <h6><?php echo esc_html( $title ); ?></h6>
                <span><?php echo esc_html( $description ); ?></span>
            </section>
            <?php
        }

        public function add_tabs_content( $post_id ) {
            wp_nonce_field( 'wbtm_save_gallery_image_nonce', 'wbtm_gallery_image_nonce' );
            ?>
            <div class="tabsItem" data-tabs="#wbtm_settings_gallery_images">
                <?php $this->section_header(); ?>
                <?php $this->panel_header('Gallery ','Please upload gallary images size in ratio 4:3. Ex: Image size width=1200px and height=900px. gallery and feature image should be in same size.'); ?>
                <section>
                    <div  id="field-wrapper-<?php echo esc_attr($post_id); ?>" class="<?php if(!empty($depends)) echo 'dependency-field'; ?> field-wrapper field-media-multi-wrapper field-media-multi-wrapper-<?php echo esc_attr($post_id); ?>">
                        <div class='button upload' id='media_upload_<?php echo esc_attr($post_id); ?>'>
                            <?php echo __('Upload','pickplugins-options-framework');?>
                        </div>
                        <div class='button clear' id='media_clear_<?php echo $post_id; ?>'>
                            <?php echo __('Clear','pickplugins-options-framework');?>
                        </div>
                        <div class="wbtm_gallery-images-lists media-list-<?php echo esc_attr($post_id); ?> ">
                            <?php
                            $gallery_images = get_post_meta($post_id,'wbtm_gallery_images',true);
                            $gallery_images = $gallery_images ? $gallery_images : [];

                            if(!empty($gallery_images) && is_array($gallery_images)):
                                foreach ($gallery_images as $image ):
                                    $media_url	= wp_get_attachment_url( $image );
                                    $media_type	= get_post_mime_type( $image );
                                    $media_title= get_the_title( $image );
                                    ?>
                                    <div class="wbtm_gallery-image">
                                        <div class="wbtm_gallery_image_remove" onclick="jQuery(this).parent().remove()">X</i></div>

                                        <img class="wbtm_gallery-images" id='media_preview_<?php echo esc_attr($post_id); ?>' src='<?php echo esc_attr($media_url); ?>' />
                                        <input type='hidden' name='wbtm_gallery_images[]' value='<?php echo esc_attr($image); ?>' />
                                    </div>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </div>
                </section>
                <script>
                    jQuery(document).ready(function($){
                        $('#media_upload_<?php echo esc_attr($post_id); ?>').click(function() {
                            //var send_attachment_bkp = wp.media.editor.send.attachment;
                            wp.media.editor.send.attachment = function(props, attachment) {
                                attachment_id = attachment.id;
                                attachment_url = attachment.url;
                                html = '<div class=" wbtm_gallery-image">';
                                html += '<span class="wbtm_gallery_image_remove" onclick="jQuery(this).parent().remove()">X</i></span>';
                                html += '<img src="'+attachment_url+'" class="wbtm_gallery-images"/>';
                                html += '<input type="hidden" name="wbtm_gallery_images[]" value="'+attachment_id+'" />';
                                html += '</div>';
                                $('.media-list-<?php echo esc_attr($post_id); ?>').append(html);
                                //wp.media.editor.send.attachment = send_attachment_bkp;
                            }
                            wp.media.editor.open($(this));
                            return false;
                        });
                        $('#media_clear_<?php echo esc_attr($post_id); ?>').click(function() {
                            $('.media-list-<?php echo esc_attr($post_id); ?> .gallery-image').remove();
                        })
                    });
                </script>
            </div>
            <?php
        }
        public function settings_save($post_id) {

            if ( ! isset( $_POST['wbtm_gallery_image_nonce'] ) ) {
                return;
            }
            if ( ! wp_verify_nonce( $_POST['wbtm_gallery_image_nonce'], 'wbtm_save_gallery_image_nonce' ) ) {
                return;
            }

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            if ( get_post_type( $post_id ) == WBTM_Functions::get_cpt() ) {

                $gallery_images = isset( $_POST['wbtm_gallery_images'] ) ?  $_POST['wbtm_gallery_images']  : [];
                update_post_meta($post_id, 'wbtm_gallery_images', $gallery_images);

            }
        }


    }

    new WBTM_Gallery_Image_Settings();
}