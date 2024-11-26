<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Taxonomy')) {
		class WBTM_Taxonomy {
			public function __construct() {
            add_action('init', [$this, 'taxonomy']);
            add_action('wbtm_bus_features_add_form_fields', [$this, 'add_feature_image_field']);
            add_action('wbtm_bus_features_edit_form_fields', [$this, 'edit_feature_image_field']);
            add_action('created_wbtm_bus_features', [$this, 'save_feature_image'], 10, 2);
            add_action('edited_wbtm_bus_features', [$this, 'save_feature_image'], 10, 2);
        }

		 // Add image upload field to the term creation screen
		 public function add_feature_image_field() {
            ?>
            <div class="form-field">
                <label for="feature_image"><?php esc_html_e('Feature Image', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                <input type="text" name="feature_image" id="feature_image" value="" class="feature_image_field" />
                <button class="upload_image_button button"><?php esc_html_e('Upload Image', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                <script>
                    jQuery(document).ready(function($) {
                        var custom_uploader;
                        $('.upload_image_button').click(function(e) {
                            e.preventDefault();
                            if (custom_uploader) {
                                custom_uploader.open();
                                return;
                            }
                            custom_uploader = wp.media.frames.file_frame = wp.media({
                                title: '<?php esc_html_e("Choose Image", "bus-ticket-booking-with-seat-reservation"); ?>',
                                button: {
                                    text: '<?php esc_html_e("Use this image", "bus-ticket-booking-with-seat-reservation"); ?>'
                                },
                                multiple: false
                            });
                            custom_uploader.on('select', function() {
								var attachment = custom_uploader.state().get('selection').first().toJSON();
                            $('#feature_image').val(attachment.url);
                            // Display the uploaded image
                            if (attachment.url) {
                                $('#feature_image').next('img').remove(); // Remove previous image if any
                                $('<img src="' + attachment.url + '" style="max-width: 50%; height: auto; margin-top: 10px;" />').insertAfter('#feature_image');
                            }
                            });
                            custom_uploader.open();
                        });
                    });
                </script>
            </div>
            <?php
        }

        public function edit_feature_image_field($term) {
    $feature_image = get_term_meta($term->term_id, 'feature_image', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="feature_image"><?php esc_html_e('Feature Image', 'bus-ticket-booking-with-seat-reservation'); ?></label></th>
        <td>
            <input type="text" name="feature_image" id="feature_image" value="<?php echo esc_attr($feature_image); ?>" class="feature_image_field" />
            <button class="upload_image_button button"><?php esc_html_e('Upload Image', 'bus-ticket-booking-with-seat-reservation'); ?></button>
            <?php if ($feature_image) : ?>
                <br/>
                <img src="<?php echo esc_url($feature_image); ?>" alt="<?php esc_html_e('Feature Image', 'bus-ticket-booking-with-seat-reservation'); ?>" style="max-width: 100%; height: auto; margin-top: 10px;">
            <?php else : ?>
                <p><?php esc_html_e('No image uploaded.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
            <?php endif; ?>
            <script>
                jQuery(document).ready(function($) {
                    var custom_uploader;
                    $('.upload_image_button').click(function(e) {
                        e.preventDefault();
                        if (custom_uploader) {
                            custom_uploader.open();
                            return;
                        }
                        custom_uploader = wp.media.frames.file_frame = wp.media({
                            title: '<?php esc_html_e("Choose Image", "bus-ticket-booking-with-seat-reservation"); ?>',
                            button: {
                                text: '<?php esc_html_e("Use this image", "bus-ticket-booking-with-seat-reservation"); ?>'
                            },
                            multiple: false
                        });
                        custom_uploader.on('select', function() {
                            var attachment = custom_uploader.state().get('selection').first().toJSON();
                            $('#feature_image').val(attachment.url);
                            // Display the uploaded image
                            if (attachment.url) {
                                $('#feature_image').next('img').remove(); // Remove previous image if any
                                $('<img src="' + attachment.url + '" style="max-width: 50%; height: auto; margin-top: 10px;" />').insertAfter('#feature_image');
                            }
                        });
                        custom_uploader.open();
                    });
                });
            </script>
        </td>
    </tr>
    <?php
}


        // Save the image URL when a new term is created or edited
        public function save_feature_image($term_id) {
            if (isset($_POST['feature_image'])) {
                update_term_meta($term_id, 'feature_image', esc_url($_POST['feature_image']));
            }
        }
			public function taxonomy() {
				$name = WBTM_Functions::get_name();
				$labels = array(
                'name' => esc_html__('Features', 'bus-ticket-booking-with-seat-reservation'),
                'singular_name' => esc_html__('Feature', 'bus-ticket-booking-with-seat-reservation'),
                'menu_name' => esc_html__('Features', 'bus-ticket-booking-with-seat-reservation'),
                'all_items' => esc_html__('All Features', 'bus-ticket-booking-with-seat-reservation'),
                'new_item_name' => esc_html__('New Feature Name', 'bus-ticket-booking-with-seat-reservation'),
                'add_new_item' => esc_html__('Add New Feature', 'bus-ticket-booking-with-seat-reservation'),
                'edit_item' => esc_html__('Edit Feature', 'bus-ticket-booking-with-seat-reservation'),
                'update_item' => esc_html__('Update Feature', 'bus-ticket-booking-with-seat-reservation'),
                'view_item' => esc_html__('View Feature', 'bus-ticket-booking-with-seat-reservation'),
                'search_items' => esc_html__('Search Features', 'bus-ticket-booking-with-seat-reservation'),
                'not_found' => esc_html__('Not Found', 'bus-ticket-booking-with-seat-reservation'),
                'no_terms' => esc_html__('No Features', 'bus-ticket-booking-with-seat-reservation'),
                'items_list' => esc_html__('Features List', 'bus-ticket-booking-with-seat-reservation'),
                'items_list_navigation' => esc_html__('Features List Navigation', 'bus-ticket-booking-with-seat-reservation'),
            );

            $args = [
                'hierarchical' => true,
                'public' => true,
                'labels' => $labels,
                'show_ui' => true,
                'show_admin_column' => true,
                'update_count_callback' => '_update_post_term_count',
                'query_var' => true,
                'rewrite' => ['slug' => 'bus-feature'],
                'show_in_rest' => true,
                'rest_base' => 'bus_feature',
                'meta_box_cb' => false,
            ];

            register_taxonomy('wbtm_bus_features', 'wbtm_bus', $args);
				$labels = array(
					'name' => $name . ' ' . esc_html__(' Type', 'bus-ticket-booking-with-seat-reservation'),
					'singular_name' => _x($name . ' Type', 'bus-ticket-booking-with-seat-reservation'),
					'menu_name' => _x($name . ' Type', 'bus-ticket-booking-with-seat-reservation'),
					'all_items' => esc_html__('All', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item_colon' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Type:', 'bus-ticket-booking-with-seat-reservation'),
					'new_item_name' => _x('New  ' . $name . '  Type Name', 'bus-ticket-booking-with-seat-reservation'),
					'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . _x('Type', 'bus-ticket-booking-with-seat-reservation'),
					'edit_item' => _x('Edit  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'update_item' => _x('Update  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'view_item' => _x('View  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'add_or_remove_items' => _x('Add or remove  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'popular_items' => _x('Popular  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'search_items' => _x('Search  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					'no_terms' => _x('No  ' . $name . '  Type', 'bus-ticket-booking-with-seat-reservation'),
					'items_list' => _x($name . ' Type list', 'bus-ticket-booking-with-seat-reservation'),
					'items_list_navigation' => _x($name . ' Type list navigation', 'bus-ticket-booking-with-seat-reservation'),
				);
				$args = [
					'hierarchical' => true,
					"public" => true,
					'labels' => $labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => ['slug' => 'bus-category'],
					'show_in_rest' => true,
					'rest_base' => 'bus_cat',
					'meta_box_cb' => false,
				];
				register_taxonomy('wbtm_bus_cat', 'wbtm_bus', $args);
				$bus_stops_labels = array(
					'singular_name' => _x($name . ' Stops', 'bus-ticket-booking-with-seat-reservation'),
					'name' => _x($name . ' Stops', 'bus-ticket-booking-with-seat-reservation'),
				);
				$bus_stops_args = [
					'hierarchical' => true,
					"public" => true,
					'labels' => $bus_stops_labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => ['slug' => 'bus-stops'],
					'show_in_rest' => true,
					'rest_base' => 'bus_stops',
					'meta_box_cb' => false,
				];
				register_taxonomy('wbtm_bus_stops', 'wbtm_bus', $bus_stops_args);
				$labels = array(
					'name' => $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'singular_name' => esc_html__($name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'menu_name' => esc_html__($name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'all_items' => esc_html__('Allx ' . $name . ' Bus Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'parent_item_colon' => _x('Parent ' . $name . ' Pickup Point:', 'bus-ticket-booking-with-seat-reservation'),
					'new_item_name' => _x('New ' . $name . ' Pickup Point Name', 'bus-ticket-booking-with-seat-reservation'),
					'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'edit_item' => _x('Edit ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'update_item' => _x('Update ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'view_item' => _x('View ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
					'add_or_remove_items' => _x('Add or remove ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
					'popular_items' => _x('Popular' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'search_items' => _x('Search' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
					'no_terms' => _x('No' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
					'items_list' => _x($name . ' Pickup Point list', 'bus-ticket-booking-with-seat-reservation'),
					'items_list_navigation' => _x($name . ' Pickup Point list navigation', 'bus-ticket-booking-with-seat-reservation'),
				);
				$args = array(
					'hierarchical' => true,
					"public" => true,
					'labels' => $labels,
					'show_ui' => true,
					'show_admin_column' => true,
					'update_count_callback' => '_update_post_term_count',
					'query_var' => true,
					'rewrite' => array('slug' => 'bus-pickuppoint'),
					'show_in_rest' => false,
					'rest_base' => 'bus_pickpoint',
					'meta_box_cb' => false,
				);
				register_taxonomy('wbtm_bus_pickpoint', 'wbtm_bus', $args);
                $labels_drop_off = array(
                    'name' => $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'singular_name' => esc_html__($name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'menu_name' => esc_html__($name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'all_items' => esc_html__('Allx ' . $name . ' Bus Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'parent_item' => esc_html__('Parent', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'parent_item_colon' => _x('Parent ' . $name . ' Drop-Off Point:', 'bus-ticket-booking-with-seat-reservation'),
                    'new_item_name' => _x('New ' . $name . ' Drop-Off Point Name', 'bus-ticket-booking-with-seat-reservation'),
                    'add_new_item' => esc_html__('Add New', 'bus-ticket-booking-with-seat-reservation') . ' ' . $name . ' ' . esc_html__('Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'edit_item' => _x('Edit ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'update_item' => _x('Update ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'view_item' => _x('View ' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'separate_items_with_commas' => _x('Separate Category with commas', 'bus-ticket-booking-with-seat-reservation'),
                    'add_or_remove_items' => _x('Add or remove ' . $name . ' Pickup Point', 'bus-ticket-booking-with-seat-reservation'),
                    'choose_from_most_used' => _x('Choose from the most used', 'bus-ticket-booking-with-seat-reservation'),
                    'popular_items' => _x('Popular' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'search_items' => _x('Search' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'not_found' => _x('Not Found', 'bus-ticket-booking-with-seat-reservation'),
                    'no_terms' => _x('No' . $name . ' Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'),
                    'items_list' => _x($name . ' Drop-Off Point list', 'bus-ticket-booking-with-seat-reservation'),
                    'items_list_navigation' => _x($name . ' Drop-Off Point list navigation', 'bus-ticket-booking-with-seat-reservation'),
                );
                $args_drop_off = array(
                    'hierarchical' => true,
                    "public" => true,
                    'labels' => $labels_drop_off,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'update_count_callback' => '_update_post_term_count',
                    'query_var' => true,
                    'rewrite' => array('slug' => 'bus-drop_off'),
                    'show_in_rest' => false,
                    'rest_base' => 'bus_drop_off',
                    'meta_box_cb' => false,
                );
                register_taxonomy('wbtm_bus_drop_off', 'wbtm_bus', $args_drop_off);
			}
		}
		new WBTM_Taxonomy();
	}