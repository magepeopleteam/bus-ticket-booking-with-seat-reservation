<?php
/*
* Author: engr.sumonazma@gmail.com
* Copyright: mage-people.com
*/

if (!defined('ABSPATH')) {
    die; // Cannot access pages directly.
}
//if( isset( $_POST['nonce'] ) && wp_verify_nonce(  sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),'wtbm_ajax_nonce' ) ){

    /*$post_id = $post_id ?? '';
    $backend_order = $backend_order ?? '';
    $link_wc_product = WBTM_Global_Function::get_post_info($post_id, 'link_wc_product');*/

    ?>
    <div class="_dLayout_xs col_12 wbtm_form_submit_area mT_xs">
        <div class="justifyBetween _alignCenter">
            <div>
                <h5><?php echo esc_html( WBTM_Translations::text_total() ) ; ?> : <span class="wbtm_total _textTheme"><?php echo wp_kses_post( wc_price(0 ) ); ?></span></h5>
            </div>
            <?php if ($backend_order > 0) { ?>
                <button type="submit" class="_themeButton">
                    <?php echo esc_html( WBTM_Translations::text_book_now() ); ?>
                </button>
            <?php } else { ?>
               <!-- <button type="submit" class="_themeButton" name="add-to-cart" value="<?php /*echo esc_attr($link_wc_product); */?>">
                    <?php /*echo WBTM_Translations::text_book_now(); */?>
                </button>-->

                <button type="button" id="wbtm_add_to_cart" class="_themeButton" value="<?php echo esc_attr($link_wc_product);?>">
                    <?php echo esc_html( WBTM_Translations::text_book_now() ); ?>
                </button>
            <?php } ?>
        </div>
    </div>
    <?php
//}
// End of PHP script
