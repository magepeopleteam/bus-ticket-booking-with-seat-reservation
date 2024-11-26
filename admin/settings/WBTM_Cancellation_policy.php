<?php
	/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/


if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('WBTM_Cancellation_policy')) {
    class WBTM_Cancellation_policy {
        public function __construct() {
            add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
            add_action('wbtm_settings_save', [$this, 'settings_save']);
        }

        public function tab_content($post_id) {
            $cancellation_policy = get_post_meta($post_id, 'wbtm_cancellation_policy', true);
            ?>
            <div class="tabsItem" data-tabs="#wbtm_cancellation_policy">
                <h3><?php esc_html_e('Cancellation Policy', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                
                <div class="_dLayout_padding_bgLight">
                    <div class="col_6 _dFlex_fdColumn">
                        <span><?php esc_html_e('Write your cancellation policy here. This will be displayed to users.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                    </div>
                </div>
                <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter">
                    <div class="col_12 textLeft ss">
                        <textarea id="wbtm_cancellation_policy_textarea" class="formControl" name="wbtm_cancellation_policy" rows="5"><?php echo wp_kses_post($cancellation_policy); ?></textarea>
                    </div>
                </div>
            </div>
            <script type="text/javascript" src="//js.nicedit.com/nicEdit-latest.js"></script> 
            <script type="text/javascript">
                bkLib.onDomLoaded(function() {
                    new nicEditor({fullPanel : true}).panelInstance('wbtm_cancellation_policy_textarea');
                });
            </script>
            <style>
                #wbtm_cancellation_policy_textarea {
                    width: 976px !important;
                }
                .nicEdit-panelContain {
                    width: 976px !important;
                }
                .nicEdit-main {
                    width: 976px !important;
                }
            </style>
            <?php
        }

        public function settings_save($post_id) {

            if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
                if (isset($_POST['wbtm_cancellation_policy'])) {
                    $cancellation_policy = wp_kses_post($_POST['wbtm_cancellation_policy']);
                    update_post_meta($post_id, 'wbtm_cancellation_policy', $cancellation_policy);
                }
            }
        }
    }

    new WBTM_Cancellation_policy();
}
