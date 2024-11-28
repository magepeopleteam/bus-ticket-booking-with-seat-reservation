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
    $is_enabled = get_post_meta($post_id, 'wbtm_cancellation_policy_enabled', true) === 'yes'; // check if the policy is enabled
    ?>
    <div class="tabsItem" data-tabs="#wbtm_cancellation_policy">
        <h3><?php esc_html_e('Cancellation Policy', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        
        <div class="_dLayout_padding_bgLight">
            <div class="col_6 _dFlex_fdColumn">
                <span><?php esc_html_e('Enable Cancellation Policy', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <label class="switch">
                    <input type="checkbox" id="wbtm_cancellation_policy_toggle" name="wbtm_cancellation_policy_enable" value="yes" <?php checked($is_enabled); ?> />
                    <span class="slider round"></span> 
                </label>
                <span><?php esc_html_e('Write your cancellation policy here. This will be displayed to users.', 'bus-ticket-booking-with-seat-reservation'); ?></span>
            </div>
        </div>
        
        <div class="_dLayout_padding_dFlex_justifyBetween_alignCenter" id="cancellation_policy_container" style="<?php echo $is_enabled ? '' : 'display:none;'; ?>">
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
        
        // Toggle visibility of the cancellation policy textarea
        document.getElementById('wbtm_cancellation_policy_toggle').addEventListener('change', function() {
            var container = document.getElementById('cancellation_policy_container');
            container.style.display = this.checked ? '' : 'none'; // Show or hide based on toggle state
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
        /* Toggle switch styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
    <?php
}

public function settings_save($post_id) {
    if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
        if (isset($_POST['wbtm_cancellation_policy_enable'])) {
            $enabled = ($_POST['wbtm_cancellation_policy_enable'] === 'yes') ? 'yes' : 'no';
            update_post_meta($post_id, 'wbtm_cancellation_policy_enabled', $enabled);
        } else {
            update_post_meta($post_id, 'wbtm_cancellation_policy_enabled', 'no');
        }
        
        if (isset($_POST['wbtm_cancellation_policy'])) {
            $cancellation_policy = wp_kses_post($_POST['wbtm_cancellation_policy']);
            update_post_meta($post_id, 'wbtm_cancellation_policy', $cancellation_policy);
        }
    }
}

    }

    new WBTM_Cancellation_policy();
}