<?php
	/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/


if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('WBTM_Features')) {
    class WBTM_Features {
        public function __construct() {
            add_action('add_wbtm_settings_tab_content', [$this, 'tab_content']);
            add_action('wbtm_settings_save', [$this, 'settings_save']);
        }

        public function tab_content($post_id) {
    $terms = get_terms([
        'taxonomy' => 'wbtm_bus_features',
        'hide_empty' => false,
    ]);
    $selected_features = get_post_meta($post_id, 'selected_bus_features', true);
    $selected_features = is_array($selected_features) ? $selected_features : [];

    ?>
    <div class="tabsItem" data-tabs="#wbtm_features">
        <h3><?php esc_html_e('All Features', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
        
        <div class="_dLayout_padding_bgLight">
    <div class="col_12">
        <p><?php esc_html_e('Select the features you want to include.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
        <ul>
            <?php foreach ($terms as $term): ?>
                <?php $feature_image = get_term_meta($term->term_id, 'feature_image', true); ?>
                <li>
                    <label style="display: flex; align-items: center; margin-bottom: 10px;">
                        <input type="checkbox" name="selected_bus_features[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array($term->term_id, $selected_features)); ?> style="margin-right: 10px;" />
                        <div style="flex-grow: 1; display: flex; align-items: center;">
                            <p style="margin: 0; flex-grow: 1; overflow-wrap: break-word;"><?php echo esc_html($term->name); ?></p>
                            <?php if ($feature_image): ?>
                                <img src="<?php echo esc_url($feature_image); ?>" alt="<?php echo esc_attr($term->name); ?>" style="max-width: 50px; height: auto; margin-left: 20px;" />
                            <?php endif; ?>
                        </div>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

        <style>
            

        </style>
    </div>
    <?php
}


public function settings_save($post_id) {
    if (get_post_type($post_id) == WBTM_Functions::get_cpt()) {
        if (isset($_POST['selected_bus_features'])) {
            $selected_features = array_map('intval', $_POST['selected_bus_features']);
            update_post_meta($post_id, 'selected_bus_features', $selected_features);
        } else {
            delete_post_meta($post_id, 'selected_bus_features');
        }
    }
}

    }

    new WBTM_Features();
}