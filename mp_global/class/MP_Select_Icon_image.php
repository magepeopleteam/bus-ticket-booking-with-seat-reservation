<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MP_Select_Icon_image')) {
		$GLOBALS['mp_icon_popup_exit'] = false;
		class MP_Select_Icon_image {
			public function __construct() {
				add_action('mp_input_add_icon', array($this, 'load_icon'), 10, 2);
				add_action('mp_add_single_image', array($this, 'add_single_image'), 10, 2);
				add_action('mp_add_multi_image', array($this, 'add_multi_image'), 10, 2);
				add_action('mp_add_icon_image', array($this, 'add_icon_image'), 10, 3);
			}
			public function load_icon($name, $icon = '') {
				$icon_class = $icon ? '' : 'dNone';
				$button_active_class = $icon ? 'dNone' : '';
				?>
                <div class="mp_add_icon_image_area fdColumn">
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($icon); ?>"/>
                    <div class="mp_icon_item <?php echo esc_attr($icon_class); ?>">
                        <div class="allCenter">
                            <span class="<?php echo esc_attr($icon); ?>" data-add-icon></span>
                        </div>
                        <span class="fas fa-times mp_remove_icon mp_icon_remove" title="<?php esc_html_e('Remove Icon', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
                    </div>
                    <div class="mp_add_icon_image_button_area <?php echo esc_attr($button_active_class); ?>">
                        <div class="flexEqual">
                        <button class="_mpBtn_xs mp_icon_add" type="button" data-target-popup="#mp_add_icon_popup">
                            <span class="fas fa-plus"></span><?php esc_html_e('Icon', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                        </div>
                    </div>
                </div>
				<?php
				add_action('admin_footer', array($this, 'icon_popup'));
			}
			public function icon_popup() {
				if (!$GLOBALS['mp_icon_popup_exit']) {
					$GLOBALS['mp_icon_popup_exit'] = true;
					?>
                    <div class="mp_add_icon_popup mpPopup mpStyle" data-popup="#mp_add_icon_popup">
                        <div class="popupMainArea fullWidth">
                            <div class="popupHeader allCenter">
                                <h2 class="_mR"><?php esc_html_e('Select Icon', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <label class="min_300">
                                    <input type="text" class="formControl mp_name_validation" name="mp_select_icon_name" placeholder="<?php esc_attr_e('Icon/class name....', 'bus-ticket-booking-with-seat-reservation'); ?>" />
                                </label>
                                <span class="fas fa-times popupClose"></span>
                            </div>
                            <div class="popupBody">
								<?php
									$icons = $this->all_icon_array();
									if (sizeof($icons) > 0) {
										$total_icon = 0;
										foreach ($icons as $icon) {
											$total_icon += sizeof($icon['icon']);
										}
										?>
                                        <div class="dFlex">
                                            <ul class="popupIconMenu">
                                                <li class="active" data-icon-menu="all_item" data-icon-title="all_item">
													<?php esc_html_e('All Icon', 'bus-ticket-booking-with-seat-reservation'); ?>&nbsp;(
                                                    <strong><?php echo esc_html($total_icon); ?></strong>
                                                    )
                                                </li>
												<?php foreach ($icons as $key => $icon) { ?>
                                                    <li data-icon-menu="<?php echo esc_attr($key); ?>">
														<?php echo esc_html($icon['title']) . '&nbsp;(<strong>' . sizeof($icon['icon']) . '</strong>)'; ?>
                                                    </li>
												<?php } ?>
                                            </ul>
                                            <div class="popup_all_icon">
												<?php foreach ($icons as $key => $icon) { ?>
                                                    <div class="popupTabItem" data-icon-list="<?php echo esc_attr($key); ?>" data-icon-title="<?php echo esc_attr($icon['title']); ?>">
                                                        <h5 class="textTheme"><?php echo esc_html($icon['title']) . '&nbsp;(<strong>' . sizeof($icon['icon']) . '</strong>)'; ?></h5>
                                                        <div class="divider"></div>
                                                        <div class="itemIconArea">
															<?php foreach ($icon['icon'] as $icon => $item) { ?>
                                                                <div class="iconItem allCenter" data-icon-class="<?php echo esc_attr($icon); ?>" data-icon-name="<?php echo esc_attr($item); ?>" title="<?php echo esc_attr($item); ?>">
                                                                    <span class="<?php echo esc_attr($icon); ?>"></span>
                                                                </div>
															<?php } ?>
                                                        </div>
                                                    </div>
												<?php } ?>
                                            </div>
                                        </div>
									<?php } ?>
                            </div>
                        </div>
                    </div>
					<?php
				}
			}
			//======image========//
			public function add_single_image($name, $image_id = '') {
				?>
                <div class="mp_add_single_image">
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($image_id); ?>"/>
					<?php if ($image_id) { ?>
                        <div class="mp_single_image_item" data-image-id="<?php echo esc_attr($image_id); ?>'">
                            <span class="fas fa-times circleIcon_xs mp_remove_single_image"></span>
                            <img src="<?php echo wp_get_attachment_image_url($image_id, 'medium') ?>" alt="<?php echo esc_attr($image_id); ?>"/>
                        </div>
					<?php } ?>
                    <button type="button" class="_dButton_xs_bgColor_1_fullWidth <?php echo esc_attr($image_id ? 'dNone' : ''); ?>">
                        <span class="fas fa-images mR_xs"></span><?php esc_html_e('Image', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </button>
                </div>
				<?php
			}
			public function add_multi_image($name, $images) {
				$images = is_array($images) ? MP_Global_Function::array_to_string($images) : $images;
				?>
                <div class="mp_multi_image_area">
                    <input type="hidden" class="mp_multi_image_value" name="<?php echo esc_attr($name); ?>" value="<?php esc_attr_e($images); ?>"/>
                    <div class="mp_multi_image">
						<?php
							$all_images = explode(',', $images);
							if ($images && sizeof($all_images) > 0) {
								foreach ($all_images as $image) {
									?>
                                    <div class="mp_multi_image_item" data-image-id="<?php esc_attr_e($image); ?>">
                                        <span class="fas fa-times circleIcon_xs mp_remove_multi_image"></span>
                                        <img src="<?php echo MP_Global_Function::get_image_url('', $image, 'medium'); ?>" alt="<?php esc_attr_e($image); ?>"/>
                                    </div>
									<?php
								}
							}
						?>
                    </div>
                    <button type="button" class="_dButton_bgColor_1 add_multi_image">
                        <span class="fas fa-images mR_xs"></span><?php esc_html_e('Image', 'bus-ticket-booking-with-seat-reservation'); ?>
                    </button>
                </div>
				<?php
			}
			//==============//
			public function add_icon_image($name, $icon = '', $image = '') {
				$icon_class = $icon ? '' : 'dNone';
				$image_class = $image ? '' : 'dNone';
				$value = $image ?: $icon;
				$button_active_class = $icon || $image ? 'dNone' : '';
				?>
                <div class="mp_add_icon_image_area fdColumn">
                    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"/>
                    <div class="mp_icon_item <?php echo esc_attr($icon_class); ?>">
                        <div class="allCenter">
                            <span class="<?php echo esc_attr($icon); ?>" data-add-icon></span>
                        </div>
                        <span class="fas fa-times mp_remove_icon mp_icon_remove" title="<?php esc_html_e('Remove Icon', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
                    </div>
                    <div class="mp_image_item <?php echo esc_attr($image_class); ?>">
                        <img class="" src="<?php echo esc_attr(MP_Global_Function::get_image_url('', $image, 'medium')); ?>" alt="">
                        <span class="fas fa-times mp_remove_icon mp_image_remove" title="<?php esc_html_e('Remove Image', 'bus-ticket-booking-with-seat-reservation'); ?>"></span>
                    </div>
                    <div class="mp_add_icon_image_button_area <?php echo esc_attr($button_active_class); ?>">
                        <div class="flexEqual">
                            <button class="_mpBtn_xs mp_image_add" type="button">
                                <span class="fas fa-images"></span><?php esc_html_e('Image', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                            <button class="_mpBtn_xs mp_icon_add" type="button" data-target-popup="#mp_add_icon_popup">
                                <span class="fas fa-plus"></span><?php esc_html_e('Icon', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                        </div>
                    </div>
                </div>
				<?php
				add_action('admin_footer', array($this, 'icon_popup'));
			}
			//==============//
			public function all_icon_array(): array {
				return [
					0 => [
						'title' => 'Accessibility',
						'icon' => [
							'fab fa-accessible-icon' => 'Accessible Icon',
							'fas fa-american-sign-language-interpreting' => 'American Sign Language Interpreting',
							'fas fa-assistive-listening-systems' => 'Assistive Listening Systems',
							'fas fa-audio-description' => 'Audio Description',
							'fas fa-blind' => 'Blind',
							'fas fa-braille' => 'Braille',
							'fas fa-closed-captioning' => 'Closed Captioning',
							'far fa-closed-captioning' => 'Closed Captioning',
							'fas fa-deaf' => 'Deaf',
							'fas fa-low-vision' => 'Low Vision',
							'fas fa-phone-volume' => 'Phone Volume',
							'fas fa-question-circle' => 'Question Circle',
							'far fa-question-circle' => 'Question Circle',
							'fas fa-tty' => 'Tty',
							'fas fa-universal-access' => 'Universal Access',
							'fas fa-wheelchair' => 'Wheelchair',
							'fas fa-sign-language' => 'Sign Language'
						]
					],
					1 => [
						'title' => 'Alert icons',
						'icon' => [
							'fas fa-bell' => 'Bell',
							'far fa-bell' => 'Bell',
							'fas fa-bell-slash' => 'Bell Slash',
							'far fa-bell-slash' => 'Bell Slash',
							'fas fa-exclamation' => 'Exclamation',
							'fas fa-exclamation-circle' => 'Exclamation Circle',
							'fas fa-exclamation-triangle' => 'Exclamation Triangle	',
							'fas fa-radiation' => 'Radiation',
							'fas fa-radiation-alt' => 'Radiation Alt',
							'fas fa-skull-crossbones' => 'Skull Crossbones'
						]
					],
					2 => [
						'title' => 'Animals icons',
						'icon' => [
							'fas fa-cat' => 'Cat',
							'fas fa-crow' => 'Crow',
							'fas fa-dog' => 'Dog',
							'fas fa-dove' => 'Dove',
							'fas fa-dragon' => 'Dragon',
							'fas fa-feather' => 'Feather',
							'fas fa-feather-alt' => 'Feather Alt',
							'fas fa-fish' => 'Fish',
							'fas fa-frog' => 'Frog',
							'fas fa-hippo' => 'Hippo',
							'fas fa-horse' => 'Horse',
							'fas fa-horse-head' => 'Horse Head',
							'fas fa-kiwi-bird' => 'Kiwi Bird',
							'fas fa-otter' => 'Otter',
							'fas fa-paw' => 'Paw',
							'fas fa-spider' => 'Spider'
						]
					],
					3 => [
						'title' => 'Arrows icons',
						'icon' => [
							'fas fa-angle-double-down' => 'Angle Double Down',
							'fas fa-angle-double-left' => 'Angle Double Left',
							'fas fa-angle-double-right' => 'Angle Double Right',
							'fas fa-angle-double-up' => 'Angle Double Up',
							'fas fa-angle-down' => 'Angle Down',
							'fas fa-angle-left' => 'Angle Left',
							'fas fa-angle-right' => 'Angle Right',
							'fas fa-angle-up' => 'Angle Up',
							'fas fa-arrow-alt-circle-down' => 'Arrow Alt Circle Down',
							'far fa-arrow-alt-circle-down' => 'Arrow Alt Circle Down',
							'fas fa-arrow-alt-circle-left' => 'Arrow Alt Circle Left',
							'far fa-arrow-alt-circle-left' => 'Arrow Alt Circle Left',
							'fas fa-arrow-alt-circle-right' => 'Arrow Alt Circle Right',
							'far fa-arrow-alt-circle-right' => 'Arrow Alt Circle Right',
							'fas fa-arrow-alt-circle-up' => 'Arrow Alt Circle Up',
							'far fa-arrow-alt-circle-up' => 'Arrow Alt Circle Up',
							'fas fa-arrow-circle-down' => 'Arrow Circle Down',
							'fas fa-arrow-circle-left' => 'Arrow Circle Left',
							'fas fa-arrow-circle-right' => 'Arrow Circle Right',
							'fas fa-arrow-circle-up' => 'Arrow Circle Up',
							'fas fa-arrow-down' => 'Arrow Down',
							'fas fa-arrow-left' => 'Arrow Left',
							'fas fa-arrow-right' => 'Arrow Right',
							'fas fa-arrow-up' => 'Arrow Up',
							'fas fa-arrows-alt' => 'Arrows Alt',
							'fas fa-arrows-alt-h' => 'Arrows Alt H',
							'fas fa-arrows-alt-v' => 'Arrows Alt V',
							'fas fa-caret-down' => 'Caret Down',
							'fas fa-caret-left' => 'Caret Left',
							'fas fa-caret-right' => 'Caret Right',
							'fas fa-caret-square-down' => 'Caret Square Down',
							'far fa-caret-square-down' => 'Caret Square Down',
							'fas fa-caret-square-left' => 'Caret Square Left',
							'far fa-caret-square-left' => 'Caret Square Left',
							'fas fa-caret-square-right' => 'Caret Square Right',
							'far fa-caret-square-right' => 'Caret Square Right',
							'fas fa-caret-square-up' => 'Caret Square Up',
							'far fa-caret-square-up' => 'Caret Square Up',
							'fas fa-caret-up' => 'Caret Up',
							'fas fa-cart-arrow-down' => 'Cart Arrow Down',
							'fas fa-chart-line' => 'Chart Line',
							'fas fa-chevron-circle-down' => 'Chevron Circle Down',
							'fas fa-chevron-circle-left' => 'Chevron Circle Left',
							'fas fa-chevron-circle-right' => 'Chevron Circle Right',
							'fas fa-chevron-circle-up' => 'Chevron Circle Up',
							'fas fa-chevron-down' => 'Chevron Down',
							'fas fa-chevron-left' => 'Chevron Left',
							'fas fa-chevron-right' => 'Chevron Right',
							'fas fa-chevron-up' => 'Chevron Up',
							'fas fa-cloud-download-alt' => 'Cloud Download Alt',
							'fas fa-cloud-upload-alt' => 'Cloud Upload Alt',
							'fas fa-compress-alt' => 'Compress Alt',
							'fas fa-compress-arrows-alt' => 'Compress Arrows Alt',
							'fas fa-download' => 'Download',
							'fas fa-exchange-alt' => 'Exchange Alt',
							'fas fa-expand-alt' => 'Expand Alt',
							'fas fa-expand-arrows-alt' => 'Expand Arrows Alt',
							'fas fa-external-link-alt' => 'External Link Alt',
							'fas fa-external-link-square-alt' => 'External Link Square Alt',
							'fas fa-hand-point-down' => 'Hand Point Down',
							'far fa-hand-point-down' => 'Hand Point Down',
							'fas fa-hand-point-left' => 'Hand Point Left',
							'far fa-hand-point-left' => 'Hand Point Left',
							'fas fa-hand-point-right' => 'Hand Point Right',
							'far fa-hand-point-right' => 'Hand Point Right',
							'fas fa-hand-point-up' => 'Hand Point Up',
							'far fa-hand-point-up' => 'Hand Point Up',
							'fas fa-hand-pointer' => 'Hand Pointer',
							'far fa-hand-pointer' => 'Hand Pointer',
							'fas fa-history' => 'History',
							'fas fa-level-down-alt' => 'Level Down Alt',
							'fas fa-level-up-alt' => 'Level Up Alt',
							'fas fa-location-arrow' => 'Location Arrow',
							'fas fa-long-arrow-alt-down' => 'Long Arrow Alt Down',
							'fas fa-long-arrow-alt-left' => 'Long Arrow Alt Left',
							'fas fa-long-arrow-alt-right' => 'Long Arrow Alt Right',
							'fas fa-long-arrow-alt-up' => 'Long Arrow Alt Up',
							'fas fa-mouse-pointer' => 'Mouse Pointer',
							'fas fa-play' => 'Play',
							'fas fa-random' => 'Random',
							'fas fa-recycle' => 'Recycle',
							'fas fa-redo' => 'Redo',
							'fas fa-redo-alt' => 'Redo Alt',
							'fas fa-reply' => 'Reply',
							'fas fa-reply-all' => 'Reply All',
							'fas fa-retweet' => 'Retweet',
							'fas fa-share' => 'Share',
							'fas fa-share-square' => 'Share Square',
							'far fa-share-square' => 'Share Square',
							'fas fa-sign-in-alt' => 'Sign In Alt',
							'fas fa-sign-out-alt' => 'Sign Out Alt',
							'fas fa-sort' => 'Sort',
							'fas fa-sort-alpha-down' => 'Sort Alpha Down',
							'fas fa-sort-alpha-down-alt' => 'Sort Alpha Down Alt',
							'fas fa-sort-alpha-up' => 'Sort Alpha Up',
							'fas fa-sort-alpha-up-alt' => 'Sort Alpha Up Alt',
							'fas fa-sort-amount-down' => 'Sort Amount Down',
							'fas fa-sort-amount-down-alt' => 'Sort Amount Down Alt',
							'fas fa-sort-amount-up' => 'Sort Amount Up',
							'fas fa-sort-amount-up-alt' => 'Sort Amount Up Alt',
							'fas fa-sort-down' => 'Sort Down',
							'fas fa-sort-numeric-down' => 'Sort Numeric Down',
							'fas fa-sort-numeric-down-alt' => 'Sort Numeric Down Alt',
							'fas fa-sort-numeric-up' => 'Sort Numeric Up',
							'fas fa-sort-numeric-up-alt' => 'Sort Numeric Up Alt',
							'fas fa-sort-up' => 'Sort Up',
							'fas fa-sync' => 'Sync',
							'fas fa-sync-alt' => 'Sync Alt',
							'fas fa-text-height' => 'Text Height',
							'fas fa-text-width' => 'Text Width',
							'fas fa-undo' => 'Undo',
							'fas fa-undo-alt' => 'Undo Alt',
							'fas fa-upload' => 'Upload'
						]
					],
					4 => [
						'title' => 'Audio & Video icons',
						'icon' => [
							'fas fa-audio-description' => 'Audio Description',
							'fas fa-backward' => 'Backward',
							'fas fa-broadcast-tower' => 'Broadcast Tower',
							'fas fa-circle' => 'Circle',
							'far fa-circle' => 'Circle',
							'fas fa-closed-captioning' => 'Closed Captioning',
							'far fa-closed-captioning' => 'Closed Captioning',
							'fas fa-compress' => 'Compress',
							'fas fa-compress-alt' => 'Compress Alt',
							'fas fa-compress-arrows-alt' => 'Compress Arrows Alt',
							'fas fa-eject' => 'Eject',
							'fas fa-expand' => 'Expand',
							'fas fa-expand-alt' => 'Expand Alt',
							'fas fa-expand-arrows-alt' => 'Expand Arrows Alt',
							'fas fa-fast-backward' => 'Fast Backward',
							'fas fa-fast-forward' => 'Fast Forward',
							'fas fa-file-audio' => 'File Audio',
							'far fa-file-audio' => 'File Audio',
							'fas fa-file-video' => 'File Video',
							'far fa-file-video' => 'File Video',
							'fas fa-film' => 'Film',
							'fas fa-forward' => 'Forward',
							'fas fa-headphones' => 'Headphones',
							'fas fa-microphone' => 'Microphone',
							'fas fa-microphone-alt' => 'Microphone Alt',
							'fas fa-microphone-alt-slash' => 'Microphone Alt Slash',
							'fas fa-microphone-slash' => 'Microphone Slash',
							'fas fa-music' => 'Music',
							'fas fa-pause' => 'Pause',
							'fas fa-pause-circle' => 'Pause Circle',
							'far fa-pause-circle' => 'Pause Circle',
							'fas fa-phone-volume' => 'Phone Volume',
							'fas fa-photo-video' => 'Photo Video',
							'fas fa-play' => 'Play',
							'fas fa-play-circle' => 'Play Circle',
							'far fa-play-circle' => 'Play Circle',
							'fas fa-podcast' => 'Podcast',
							'fas fa-random' => 'Random',
							'fas fa-redo' => 'Redo',
							'fas fa-redo-alt' => 'Redo Alt',
							'fas fa-rss' => 'Rss',
							'fas fa-rss-square' => 'Rss Square',
							'fas fa-step-backward' => 'Step Backward',
							'fas fa-step-forward' => 'Step Forward',
							'fas fa-stop' => 'Stop',
							'fas fa-stop-circle' => 'Stop Circle',
							'fas fa-sync' => 'Sync',
							'fas fa-sync-alt' => 'Sync Alt',
							'fas fa-tv' => 'Tv',
							'fas fa-undo' => 'Undo',
							'fas fa-undo-alt' => 'Undo Alt',
							'fas fa-video' => 'Video',
							'fas fa-volume-down' => 'Volume Down',
							'fas fa-volume-mute' => 'Volume Mute',
							'fas fa-volume-off' => 'Volume Off',
							'fas fa-volume-up' => 'Volume Up',
							'fab fa-youtube' => 'Youtube'
						]
					],
					5 => [
						'title' => 'Automotive icons',
						'icon' => [
							'fas fa-air-freshener' => 'Air Freshener',
							'fas fa-ambulance' => 'Ambulance',
							'fas fa-bus' => 'Bus',
							'fas fa-bus-alt' => 'Bus Alt',
							'fas fa-car' => 'Car',
							'fas fa-car-alt' => 'Car Alt',
							'fas fa-car-battery' => 'Car Battery',
							'fas fa-car-crash' => 'Car Crash',
							'fas fa-car-side' => 'Car Side',
							'fas fa-caravan' => 'Caravan',
							'fas fa-charging-station' => 'Charging Station',
							'fas fa-gas-pump' => 'Gas Pump',
							'fas fa-motorcycle' => 'Motorcycle',
							'fas fa-oil-can' => 'Oil Can',
							'fas fa-shuttle-van' => 'Shuttle Van',
							'fas fa-tachometer-alt' => 'Tachometer Alt',
							'fas fa-taxi' => 'Taxi',
							'fas fa-trailer' => 'Trailer',
							'fas fa-truck' => 'Truck',
							'fas fa-truck-monster' => 'Truck Monster',
							'fas fa-truck-pickup' => 'Truck Pickup'
						]
					],
					6 => [
						'title' => 'Autumn icons',
						'icon' => [
							'fas fa-apple-alt' => 'Apple Alt',
							'fas fa-campground' => 'Campground',
							'fas fa-cloud-sun' => 'Cloud Sun',
							'fas fa-drumstick-bite' => 'Drumstick Bite',
							'fas fa-football-ball' => 'Football Ball',
							'fas fa-hiking' => 'Hiking',
							'fas fa-mountain' => 'Mountain',
							'fas fa-tractor' => 'Tractor',
							'fas fa-tree' => 'Tree',
							'fas fa-wind' => 'Wind',
							'fas fa-wine-bottle' => 'Wine Bottle'
						]
					],
					7 => [
						'title' => 'Beverage icons',
						'icon' => [
							'fas fa-beer' => 'Beer',
							'fas fa-blender' => 'Blender',
							'fas fa-cocktail' => 'Cocktail',
							'fas fa-coffee' => 'Coffee',
							'fas fa-flask' => 'Flask',
							'fas fa-glass-cheers' => 'Glass Cheers',
							'fas fa-glass-martini' => 'Glass Martini',
							'fas fa-glass-martini-alt' => 'Glass Martini Alt',
							'fas fa-glass-whiskey' => 'Glass Whiskey',
							'fas fa-mug-hot' => 'Mug Hot',
							'fas fa-wine-bottle' => 'Wine Bottle',
							'fas fa-wine-glass' => 'Wine Glass',
							'fas fa-wine-glass-alt' => 'Wine Glass Alt'
						]
					],
					8 => [
						'title' => 'Buildings icons',
						'icon' => [
							'fas fa-archway' => 'Archway',
							'fas fa-building' => 'Building',
							'far fa-building' => 'Building',
							'fas fa-campground' => 'Campground',
							'fas fa-church' => 'Church',
							'fas fa-city' => 'City',
							'fas fa-clinic-medical' => 'Clinic Medical',
							'fas fa-dungeon' => 'Dungeon',
							'fas fa-gopuram' => 'Gopuram',
							'fas fa-home' => 'Home',
							'fas fa-hospital' => 'Hospital',
							'far fa-hospital' => 'Hospital',
							'fas fa-hospital-alt' => 'Hospital Alt',
							'fas fa-hospital-user' => 'Hospital User',
							'fas fa-hotel' => 'Hotel',
							'fas fa-house-damage' => 'House Damage',
							'fas fa-igloo' => 'Igloo',
							'fas fa-industry' => 'Industry',
							'fas fa-kaaba' => 'Kaaba',
							'fas fa-landmark' => 'Landmark',
							'fas fa-monument' => 'Monument',
							'fas fa-mosque' => 'Mosque',
							'fas fa-place-of-worship' => 'Place Of Worship',
							'fas fa-school' => 'School',
							'fas fa-store' => 'Store',
							'fas fa-store-alt' => 'Store Alt',
							'fas fa-synagogue' => 'Synagogue',
							'fas fa-torii-gate' => 'Torii Gate',
							'fas fa-university' => 'University',
							'fas fa-vihara' => 'Vihara',
							'fas fa-warehouse' => 'Warehouse'
						]
					],
					9 => [
						'title' => 'Business icons',
						'icon' => [
							'fas fa-address-book' => 'Address Book',
							'far fa-address-book' => 'Address Book',
							'fas fa-address-card' => 'Address Card',
							'far fa-address-card' => 'Address Card',
							'fas fa-archive' => 'Archive',
							'fas fa-balance-scale' => 'Balance Scale',
							'fas fa-balance-scale-left' => 'Balance Scale Left',
							'fas fa-balance-scale-right' => 'Balance Scale Right',
							'fas fa-birthday-cake' => 'Birthday Cake',
							'fas fa-book' => 'Book',
							'fas fa-briefcase' => 'Briefcase',
							'fas fa-bullhorn' => 'Bullhorn',
							'fas fa-bullseye' => 'Bullseye',
							'fas fa-business-time' => 'Business Time',
							'fas fa-calculator' => 'Calculator',
							'fas fa-calendar' => 'Calendar',
							'far fa-calendar' => 'Calendar',
							'fas fa-calendar-alt' => 'Calendar Alt',
							'far fa-calendar-alt' => 'Calendar Alt',
							'fas fa-certificate' => 'Certificate',
							'fas fa-chart-area' => 'Chart Area',
							'fas fa-chart-bar' => 'Chart Bar',
							'far fa-chart-bar' => 'Chart Bar',
							'fas fa-chart-line' => 'Chart Line',
							'fas fa-chart-pie' => 'Chart Pie',
							'fas fa-clipboard' => 'Clipboard',
							'far fa-clipboard' => 'Clipboard',
							'fas fa-coffee' => 'Coffee',
							'fas fa-columns' => 'Columns',
							'fas fa-compass' => 'Compass',
							'far fa-compass' => 'Compass',
							'fas fa-copy' => 'Copy',
							'far fa-copy' => 'Copy',
							'fas fa-copyright' => 'Copyright',
							'far fa-copyright' => 'Copyright',
							'fas fa-cut' => 'Cut',
							'fas fa-edit' => 'Edit',
							'far fa-edit' => 'Edit',
							'fas fa-envelope' => 'Envelope',
							'far fa-envelope' => 'Envelope',
							'fas fa-envelope-open' => 'Envelope Open',
							'far fa-envelope-open' => 'Envelope Open',
							'fas fa-envelope-square' => 'Envelope Square',
							'fas fa-eraser' => 'Eraser',
							'fas fa-fax' => 'Fax',
							'fas fa-file' => 'File',
							'far fa-file' => 'File',
							'fas fa-file-alt' => 'File Alt',
							'far fa-file-alt' => 'File Alt',
							'fas fa-folder' => 'Folder',
							'far fa-folder' => 'Folder',
							'fas fa-folder-minus' => 'Folder Minus',
							'fas fa-folder-open' => 'Folder Open',
							'far fa-folder-open' => 'Folder Open',
							'fas fa-folder-plus' => 'Folder Plus',
							'fas fa-glasses' => 'Glasses',
							'fas fa-globe' => 'Globe',
							'fas fa-highlighter' => 'Highlighter',
							'fas fa-laptop-house' => 'Laptop House',
							'fas fa-marker' => 'Marker',
							'fas fa-paperclip' => 'Paperclip',
							'fas fa-paste' => 'Paste',
							'fas fa-pen' => 'Pen',
							'fas fa-pen-alt' => 'Pen Alt',
							'fas fa-pen-fancy' => 'Pen Fancy',
							'fas fa-pen-nib' => 'Pen Nib',
							'fas fa-pen-square' => 'Pen Square',
							'fas fa-pencil-alt' => 'Pencil Alt',
							'fas fa-percent' => 'Percent',
							'fas fa-phone' => 'Phone',
							'fas fa-phone-alt' => 'Phone Alt',
							'fas fa-phone-slash' => 'Phone Slash',
							'fas fa-phone-square' => 'Phone Square',
							'fas fa-phone-square-alt' => 'Phone Square Alt',
							'fas fa-phone-volume' => 'Phone Volume',
							'fas fa-print' => 'Print',
							'fas fa-project-diagram' => 'Project Diagram',
							'fas fa-registered' => 'Registered',
							'far fa-registered' => 'Registered',
							'fas fa-save' => 'Save',
							'far fa-save' => 'Save',
							'fas fa-sitemap' => 'Sitemap',
							'fas fa-socks' => 'Socks',
							'fas fa-sticky-note' => 'Sticky Note',
							'far fa-sticky-note' => 'Sticky Note',
							'fas fa-stream' => 'Stream',
							'fas fa-table' => 'Table',
							'fas fa-tag' => 'Tag',
							'fas fa-tags' => 'Tags',
							'fas fa-tasks' => 'Tasks',
							'fas fa-thumbtack' => 'Thumbtack',
							'fas fa-trademark' => 'Trademark',
							'fas fa-wallet' => 'Wallet'
						]
					],
					10 => [
						'title' => 'Camping icons',
						'icon' => [
							'fas fa-binoculars' => 'Binoculars',
							'fas fa-faucet' => 'Faucet',
							'fas fa-fire' => 'Fire',
							'fas fa-fire-alt' => 'Fire Alt',
							'fas fa-first-aid' => 'First Aid',
							'fas fa-map' => 'Map',
							'far fa-map' => 'Map',
							'fas fa-map-marked' => 'Map Marked',
							'fas fa-map-marked-alt' => 'Map Marked Alt',
							'fas fa-map-signs' => 'Map Signs',
							'fas fa-route' => 'Route',
							'fas fa-toilet-paper' => 'Toilet Paper'
						]
					],
					11 => [
						'title' => 'Charity icons',
						'icon' => [
							'fas fa-dollar-sign' => 'Dollar Sign',
							'fas fa-donate' => 'Donate',
							'fas fa-dove' => 'Dove',
							'fas fa-gift' => 'Gift',
							'fas fa-hand-holding-heart' => 'Hand Holding Heart',
							'fas fa-hand-holding-usd' => 'Hand Holding Usd',
							'fas fa-hand-holding-water' => 'Hand Holding Water',
							'fas fa-hands-helping' => 'Hands Helping',
							'fas fa-handshake' => 'Handshake',
							'far fa-handshake' => 'Handshake',
							'fas fa-heart' => 'Heart',
							'far fa-heart' => 'Heart',
							'fas fa-leaf' => 'Leaf',
							'fas fa-parachute-box' => 'Parachute Box',
							'fas fa-piggy-bank' => 'Piggy Bank',
							'fas fa-ribbon' => 'Ribbon',
							'fas fa-seedling' => 'Seedling'
						]
					],
					12 => [
						'title' => 'Chat icons',
						'icon' => [
							'fas fa-comment' => 'Comment',
							'far fa-comment' => 'Comment',
							'fas fa-comment-alt' => 'Comment Alt',
							'far fa-comment-alt' => 'Comment Alt',
							'fas fa-comment-dots' => 'Comment Dots',
							'far fa-comment-dots' => 'Comment Dots',
							'fas fa-comment-medical' => 'Comment Medical',
							'fas fa-comment-slash' => 'Comment Slash',
							'fas fa-comments' => 'Comments',
							'far fa-comments' => 'Comments',
							'fas fa-frown' => 'Frown',
							'far fa-frown' => 'Frown',
							'fas fa-icons' => 'Icons',
							'fas fa-meh' => 'Meh',
							'far fa-meh' => 'Meh',
							'fas fa-poo' => 'Poo',
							'fas fa-quote-left' => 'Quote Left',
							'fas fa-quote-right' => 'Quote Right',
							'fas fa-smile' => 'Smile',
							'far fa-smile' => 'Smile',
							'fas fa-sms' => 'Sms',
							'fas fa-video-slash' => 'Video Slash'
						]
					],
					13 => [
						'title' => 'Chess icons',
						'icon' => [
							'fas fa-chess' => 'Chess',
							'fas fa-chess-bishop' => 'Chess Bishop',
							'fas fa-chess-board' => 'Chess Board',
							'fas fa-chess-king' => 'Chess King',
							'fas fa-chess-knight' => 'Chess Knight',
							'fas fa-chess-pawn' => 'Chess Pawn',
							'fas fa-chess-queen' => 'Chess Queen',
							'fas fa-chess-rook' => 'Chess Rook',
							'fas fa-square-full' => 'Square Full'
						]
					],
					14 => [
						'title' => 'Childhood icons',
						'icon' => [
							'fas fa-baby' => 'Baby',
							'fas fa-baby-carriage' => 'Baby Carriage',
							'fas fa-bath' => 'Bath',
							'fas fa-biking' => 'Biking',
							'fas fa-birthday-cake' => 'Birthday Cake',
							'fas fa-cookie' => 'Cookie',
							'fas fa-cookie-bite' => 'Cookie Bite',
							'fas fa-gamepad' => 'Gamepad',
							'fas fa-ice-cream' => 'Ice Cream',
							'fas fa-mitten' => 'Mitten',
							'fas fa-robot' => 'Robot',
							'fas fa-school' => 'School',
							'fas fa-shapes' => 'Shapes',
							'fas fa-snowman' => 'Snowman'
						]
					],
					15 => [
						'title' => 'Clothing icons',
						'icon' => [
							'fas fa-graduation-cap' => 'Graduation Cap',
							'fas fa-hat-cowboy' => 'Hat Cowboy',
							'fas fa-hat-cowboy-side' => 'Hat Cowboy Side',
							'fas fa-hat-wizard' => 'Hat Wizard',
							'fas fa-mitten' => 'Mitten',
							'fas fa-shoe-prints' => 'Shoe Prints',
							'fas fa-socks' => 'Socks',
							'fas fa-tshirt' => 'Tshirt',
							'fas fa-user-tie' => 'User Tie'
						]
					],
					16 => [
						'title' => 'Code icons',
						'icon' => [
							'fas fa-archive' => 'Archive',
							'fas fa-barcode' => 'Barcode',
							'fas fa-bug' => 'Bug',
							'fas fa-code' => 'Code',
							'fas fa-code-branch' => 'Code Branch',
							'fas fa-coffee' => 'Coffee',
							'fas fa-file-code' => 'File Code',
							'far fa-file-code' => 'File Code',
							'fas fa-filter' => 'Filter',
							'fas fa-fire-extinguisher' => 'Fire Extinguisher',
							'fas fa-keyboard' => 'Keyboard',
							'far fa-keyboard' => 'Keyboard',
							'fas fa-laptop-code' => 'Laptop Code',
							'fas fa-microchip' => 'Microchip',
							'fas fa-project-diagram' => 'Project Diagram',
							'fas fa-qrcode' => 'Qrcode',
							'fas fa-shield-alt' => 'Shield Alt',
							'fas fa-sitemap' => 'Sitemap',
							'fas fa-stream' => 'Stream',
							'fas fa-terminal' => 'Terminal',
							'fas fa-user-secret' => 'User Secret',
							'fas fa-window-close' => 'Window Close',
							'far fa-window-close' => 'Window Close',
							'fas fa-window-maximize' => 'Window Minimize',
							'far fa-window-maximize' => 'Window Minimize',
							'fas fa-window-minimize' => 'Window Minimize',
							'far fa-window-minimize' => 'Window Minimize',
							'fas fa-window-restore' => 'Window Restore',
							'far fa-window-restore' => 'Window Restore',
						]
					],
					17 => [
						'title' => 'Construction icons',
						'icon' => [
							'fas fa-brush' => 'Brush',
							'fas fa-drafting-compass' => 'Drafting Compass',
							'fas fa-dumpster' => 'Dumpster',
							'fas fa-hammer' => 'Hammer',
							'fas fa-hard-hat' => 'Hard Hat',
							'fas fa-paint-roller' => 'Paint Roller',
							'fas fa-pencil-alt' => 'Pencil Alt',
							'fas fa-pencil-ruler' => 'Pencil Ruler',
							'fas fa-ruler' => 'Ruler',
							'fas fa-ruler-combined' => 'Ruler Combined',
							'fas fa-ruler-horizontal' => 'Ruler Horizontal',
							'fas fa-ruler-vertical' => 'Ruler Vertical',
							'fas fa-screwdriver' => 'Screwdriver',
							'fas fa-toolbox' => 'Toolbox',
							'fas fa-tools' => 'Tools',
							'fas fa-truck-pickup' => 'Truck Pickup',
							'fas fa-wrench' => 'Wrench',
						]
					],
					18 => [
						'title' => 'Currency icons',
						'icon' => [
							'fab fa-bitcoin' => 'Bitcoin',
							'fab fa-btc' => 'Btc',
							'fas fa-dollar-sign' => 'Dollar Sign',
							'fab fa-ethereum' => 'Ethereum',
							'fas fa-euro-sign' => 'Euro Sign',
							'fab fa-gg' => 'Gg',
							'fab fa-gg-circle' => 'Gg Circle',
							'fas fa-hryvnia' => 'Hryvnia',
							'fas fa-lira-sign' => 'Lira Sign',
							'fas fa-money-bill' => 'Money Bill',
							'fas fa-money-bill-alt' => 'Money Bill Alt',
							'far fa-money-bill-alt' => 'Money Bill Alt',
							'fas fa-money-bill-wave' => 'Money Bill Wave',
							'fas fa-money-bill-wave-alt' => 'Money Bill Wave Alt',
							'fas fa-money-check' => 'Money Check',
							'fas fa-money-check-alt' => 'Money Check Alt',
							'fas fa-pound-sign' => 'Pound Sign',
							'fas fa-ruble-sign' => 'Ruble Sign',
							'fas fa-rupee-sign' => 'Rupee Sign',
							'fas fa-shekel-sign' => 'Shekel Sign',
							'fas fa-tenge' => 'Tenge',
							'fas fa-won-sign' => 'Won Sign',
							'fas fa-yen-sign' => 'Yen Sign'
						]
					],
					19 => [
						'title' => 'Design icons',
						'icon' => [
							'fas fa-adjust' => 'Adjust',
							'fas fa-bezier-curve' => 'Bezier Curve',
							'fas fa-brush' => 'Brush',
							'fas fa-clone' => 'Clone',
							'far fa-clone' => 'Clone',
							'fas fa-crop' => 'Crop',
							'fas fa-crop-alt' => 'Crop Alt',
							'fas fa-crosshairs' => 'Crosshairs',
							'fas fa-drafting-compass' => 'Drafting Compass',
							'fas fa-draw-polygon' => 'Draw Polygon',
							'fas fa-eye' => 'Eye',
							'far fa-eye' => 'Eye',
							'fas fa-eye-dropper' => 'Eye Dropper',
							'fas fa-eye-slash' => 'Eye Slash',
							'far fa-eye-slash' => 'Eye Slash',
							'fas fa-fill' => 'Fill',
							'fas fa-fill-drip' => 'Fill Drip',
							'fas fa-highlighter' => 'Highlighter',
							'fas fa-icons' => 'Icons',
							'fas fa-layer-group' => 'Layer Group',
							'fas fa-magic' => 'Magic',
							'fas fa-marker' => 'Marker',
							'fas fa-object-group' => 'Object Group',
							'far fa-object-group' => 'Object Group',
							'fas fa-object-ungroup' => 'Object Ungroup',
							'far fa-object-ungroup' => 'Object Ungroup',
							'fas fa-paint-brush' => 'Paint Brush',
							'fas fa-paint-roller' => 'Paint Roller',
							'fas fa-palette' => 'Palette',
							'fas fa-paste' => 'Paste',
							'fas fa-pen' => 'Pen',
							'fas fa-pen-alt' => 'Pen Alt',
							'fas fa-pen-fancy' => 'Pen Fancy',
							'fas fa-pen-nib' => 'Pen Nib',
							'fas fa-pencil-alt' => 'Pencil Alt',
							'fas fa-pencil-ruler' => 'Pencil Ruler',
							'fas fa-ruler-combined' => 'Ruler Combined',
							'fas fa-ruler-horizontal' => 'Ruler Horizontal',
							'fas fa-ruler-vertical' => 'Ruler Vertical',
							'fas fa-splotch' => 'Splotch',
							'fas fa-spray-can' => 'Spray Can',
							'fas fa-stamp' => 'Stamp',
							'fas fa-swatchbook' => 'Swatchbook',
							'fas fa-tint' => 'Tint',
							'fas fa-tint-slash' => 'Tint Slash',
							'fas fa-vector-square' => 'Vector Square'
						]
					],
					20 => [
						'title' => 'Editors icons',
						'icon' => [
							'fas fa-align-center' => 'Align Center',
							'fas fa-align-justify' => 'Align Justify',
							'fas fa-align-left' => 'Align Left',
							'fas fa-align-right' => 'Align Right',
							'fas fa-bold' => 'Bold',
							'fas fa-border-all' => 'Border All',
							'fas fa-border-none' => 'Border None',
							'fas fa-border-style' => 'Border Style',
							'fas fa-heading' => 'Heading',
							'fas fa-i-cursor' => 'Cursor',
							'fas fa-indent' => 'Indent',
							'fas fa-italic' => 'Italic',
							'fas fa-link' => 'Link',
							'fas fa-list' => 'List',
							'fas fa-list-alt' => 'List Alt',
							'far fa-list-alt' => 'List Alt',
							'fas fa-list-ol' => 'List Ol',
							'fas fa-list-ul' => 'List Ul',
							'fas fa-outdent' => 'Outdent',
							'fas fa-paper-plane' => 'Paper Plane',
							'far fa-paper-plane' => 'Paper Plane',
							'fas fa-paperclip' => 'Paperclip',
							'fas fa-paragraph' => 'Paragraph',
							'fas fa-remove-format' => 'Remove Format',
							'fas fa-screwdriver' => 'Screwdriver',
							'fas fa-spell-check' => 'Spell Check',
							'fas fa-strikethrough' => 'Strikethrough',
							'fas fa-subscript' => 'Subscript',
							'fas fa-superscript' => 'Superscript',
							'fas fa-trash-restore' => 'Trash Restore',
							'fas fa-trash-restore-alt' => 'Trash Restore Alt',
							'fas fa-underline' => 'Underline',
							'fas fa-unlink' => 'Unlink'
						]
					],
					21 => [
						'title' => 'Emoji icons',
						'icon' => [
							'fas fa-angry' => 'Angry',
							'far fa-angry' => 'Angry',
							'fas fa-dizzy' => 'Dizzy',
							'far fa-dizzy' => 'Dizzy',
							'fas fa-flushed' => 'Flushed',
							'far fa-flushed' => 'Flushed',
							'fas fa-frown' => 'Frown',
							'far fa-frown' => 'Frown',
							'fas fa-frown-open' => 'Frown Open',
							'far fa-frown-open' => 'Frown Open',
							'fas fa-grimace' => 'Grimace',
							'far fa-grimace' => 'Grimace',
							'fas fa-grin' => 'Grin',
							'far fa-grin' => 'Grin',
							'fas fa-grin-alt' => 'Grin Alt',
							'far fa-grin-alt' => 'Grin Alt',
							'fas fa-grin-beam' => 'Grin Beam',
							'far fa-grin-beam' => 'Grin Beam',
							'fas fa-grin-beam-sweat' => 'Grin Beam Sweat',
							'far fa-grin-beam-sweat' => 'Grin Beam Sweat',
							'fas fa-grin-hearts' => 'Grin Hearts',
							'far fa-grin-hearts' => 'Grin Hearts',
							'fas fa-grin-squint' => 'Grin Squint',
							'far fa-grin-squint' => 'Grin Squint',
							'fas fa-grin-squint-tears' => 'Grin Squint Tears',
							'far fa-grin-squint-tears' => 'Grin Squint Tears',
							'fas fa-grin-stars' => 'Grin Stars',
							'far fa-grin-stars' => 'Grin Stars',
							'fas fa-grin-tears' => 'Grin Tears',
							'far fa-grin-tears' => 'Grin Tears',
							'fas fa-grin-tongue' => 'Grin Tongue',
							'far fa-grin-tongue' => 'Grin Tongue',
							'fas fa-grin-tongue-squint' => 'Grin Tongue Squint',
							'far fa-grin-tongue-squint' => 'Grin Tongue Squint',
							'fas fa-grin-tongue-wink' => 'Grin Tongue Wink',
							'far fa-grin-tongue-wink' => 'Grin Tongue Wink',
							'fas fa-grin-wink' => 'Grin Wink',
							'far fa-grin-wink' => 'Grin Wink',
							'fas fa-kiss' => 'Kiss',
							'far fa-kiss' => 'Kiss',
							'fas fa-kiss-beam' => 'Kiss Beam',
							'far fa-kiss-beam' => 'Kiss Beam',
							'fas fa-kiss-wink-heart' => 'Kiss Wink Heart',
							'far fa-kiss-wink-heart' => 'Kiss Wink Heart',
							'fas fa-laugh' => 'Laugh',
							'far fa-laugh' => 'Laugh',
							'fas fa-laugh-beam' => 'Laugh Beam',
							'far fa-laugh-beam' => 'Laugh Beam',
							'fas fa-laugh-squint' => 'Laugh Squint',
							'far fa-laugh-squint' => 'Laugh Squint',
							'fas fa-laugh-wink' => 'Laugh Wink',
							'far fa-laugh-wink' => 'Laugh Wink',
							'fas fa-meh-blank' => 'Meh Blank',
							'far fa-meh-blank' => 'Meh Blank',
							'fas fa-meh-rolling-eyes' => 'Meh Rolling Eyes',
							'far fa-meh-rolling-eyes' => 'Meh Rolling Eyes',
							'fas fa-sad-cry' => 'Sad Cry',
							'far fa-sad-cry' => 'Sad Cry',
							'fas fa-sad-tear' => 'Sad Tear',
							'far fa-sad-tear' => 'Sad Tear',
							'fas fa-smile' => 'Smile',
							'far fa-smile' => 'Smile',
							'fas fa-smile-beam' => 'Smile Beam',
							'far fa-smile-beam' => 'Smile Beam',
							'fas fa-smile-wink' => 'Smile Wink',
							'far fa-smile-wink' => 'Smile Wink',
							'fas fa-surprise' => 'Surprise',
							'far fa-surprise' => 'Surprise',
							'fas fa-tired' => 'Tired',
							'far fa-tired' => 'Tired'
						]
					],
					23 => [
						'title' => 'Energy icons',
						'icon' => [
							'fas fa-atom' => 'Atom',
							'fas fa-battery-empty' => 'Battery Empty',
							'fas fa-battery-full' => 'Battery Full',
							'fas fa-battery-half' => 'Battery Half',
							'fas fa-battery-quarter' => 'Battery Quarter',
							'fas fa-battery-three-quarters' => 'Battery Three Quarters',
							'fas fa-broadcast-tower' => 'Broadcast Tower',
							'fas fa-burn' => 'Burn',
							'fas fa-charging-station' => 'Charging Station',
							'fas fa-fan' => 'Fan',
							'fas fa-gas-pump' => 'Gas Pump',
							'fas fa-leaf' => 'Leaf',
							'fas fa-lightbulb' => 'Lightbulb',
							'far fa-lightbulb' => 'Lightbulb',
							'fas fa-plug' => 'Plug',
							'fas fa-poop' => 'Poop',
							'fas fa-power-off' => 'Power Off',
							'fas fa-radiation' => 'Radiation',
							'fas fa-radiation-alt' => 'Radiation Alt',
							'fas fa-seedling' => 'Seedling',
							'fas fa-solar-panel' => 'Solar Panel',
							'fas fa-sun' => 'Sun',
							'far fa-sun' => 'Sun',
							'fas fa-water' => 'Water',
							'fas fa-wind' => 'Wind',
						]
					],
					24 => [
						'title' => 'Finance icons',
						'icon' => [
							'fas fa-credit-card' => 'Credit Card',
							'far fa-credit-card' => 'Credit Card',
							'fas fa-file-invoice' => 'File Invoice',
							'fas fa-file-invoice-dollar' => 'Hand Holding Usd'
						]
					],
					25 => [
						'title' => 'Fitness icons',
						'icon' => [
							'fas fa-bicycle' => 'Bicycle',
							'fas fa-biking' => 'Biking',
							'fas fa-running' => 'Running',
							'fas fa-shoe-prints' => 'Shoe Prints',
							'fas fa-skating' => 'Skating',
							'fas fa-skiing' => 'Skiing',
							'fas fa-skiing-nordic' => 'Skiing Nordic',
							'fas fa-snowboarding' => 'Snowboarding',
							'fas fa-spa' => 'Spa',
							'fas fa-swimmer' => 'Swimmer',
							'fas fa-walking' => 'Walking'
						]
					],
					26 => [
						'title' => 'Food icons',
						'icon' => [
							'fas fa-bacon' => 'Bacon',
							'fas fa-bone' => 'Bone',
							'fas fa-bread-slice' => 'Bread Slice',
							'fas fa-candy-cane' => 'Candy Cane',
							'fas fa-carrot' => 'Carrot',
							'fas fa-cheese' => 'Cheese',
							'fas fa-cloud-meatball' => 'Cloud Meatball',
							'fas fa-cookie' => 'Cookie',
							'fas fa-drumstick-bite' => 'Drumstick Bite',
							'fas fa-egg' => 'Egg',
							'fas fa-fish' => 'Fish',
							'fas fa-hamburger' => 'Hamburger',
							'fas fa-hotdog' => 'Hotdog',
							'fas fa-ice-cream' => 'Ice Cream',
							'fas fa-lemon' => 'Lemon',
							'far fa-lemon' => 'Lemon',
							'fas fa-pepper-hot' => 'Pepper Hot',
							'fas fa-pizza-slice' => 'Pizza Slice',
							'fas fa-seedling' => 'Seedling',
							'fas fa-stroopwafel' => 'Stroopwafel',
						]
					],
					27 => [
						'title' => 'Animals icons',
						'icon' => [
							'fas fa-apple-alt' => 'fa-apple-alt',
							'fas fa-carrot' => 'Carrot',
							'fas fa-leaf' => 'Leaf',
							'fas fa-lemon' => 'Lemon',
							'far fa-lemon' => 'Lemon',
							'fas fa-pepper-hot' => 'Pepper Hot',
							'fas fa-seedling' => 'Seedling'
						]
					],
					28 => [
						'title' => 'Games icons',
						'icon' => [
							'fas fa-chess' => 'Chess',
							'fas fa-chess-bishop' => 'Chess Bishop',
							'fas fa-chess-board' => 'Chess Board',
							'fas fa-chess-king' => 'Chess King',
							'fas fa-chess-knight' => 'Chess Knight',
							'fas fa-chess-pawn' => 'Chess Pawn',
							'fas fa-chess-queen' => 'Chess Queen',
							'fas fa-chess-rook' => 'Chess Rook',
							'fas fa-dice' => 'Dice',
							'fas fa-dice-d20' => 'Dice D20',
							'fas fa-dice-d6' => 'Dice D6',
							'fas fa-dice-five' => 'Dice Five',
							'fas fa-dice-four' => 'Dice Four',
							'fas fa-dice-one' => 'Dice One',
							'fas fa-dice-six' => 'Dice Six',
							'fas fa-dice-three' => 'Dice Three',
							'fas fa-dice-two' => 'Dice Two',
							'fas fa-gamepad' => 'Gamepad',
							'fas fa-ghost' => 'Ghost',
							'fas fa-headset' => 'Headset',
							'fas fa-playstation' => 'Playstation',
							'fas fa-puzzle-piece' => 'Puzzle Piece',
							'fas fa-steam' => 'Steam',
							'fas fa-steam-square' => 'Steam Square',
							'fas fa-steam-symbol' => 'Steam Symbol',
							'fas fa-twitch' => 'Twitch',
							'fas fa-xbox' => 'Xbox'
						]
					],
					29 => [
						'title' => 'Health icons',
						'icon' => [
							'fas fa-medkit' => 'Medkit',
							'fas fa-plus-square' => 'Plus Square',
							'far fa-plus-square' => 'Plus Square',
							'fas fa-prescription' => 'Prescription',
							'fas fa-stethoscope' => 'Stethoscope',
							'fas fa-user-md' => 'User Md',
							'fas fa-wheelchair' => 'Wheelchair'
						]
					],
					30 => [
						'title' => 'Holiday icons',
						'icon' => [
							'fas fa-candy-cane' => 'Candy Cane',
							'fas fa-carrot' => 'Carrot',
							'fas fa-cookie-bite' => 'Cookie Bite',
							'fas fa-gift' => 'Gift',
							'fas fa-gifts' => 'Gifts',
							'fas fa-glass-cheers' => 'Glass Cheers',
							'fas fa-holly-berry' => 'Holly Berry',
							'fas fa-mug-hot' => 'Mug Hot',
							'fas fa-sleigh' => 'Sleigh',
							'fas fa-snowman' => 'Snowman'
						]
					],
					31 => [
						'title' => 'Interfaces icons',
						'icon' => [
							'fas fa-award' => 'Award',
							'fas fa-ban' => 'Ban',
							'fas fa-bars' => 'Bars',
							'fas fa-beer' => 'Beer',
							'fas fa-blog' => 'Blog',
							'fas fa-calendar-check' => 'Calendar Check',
							'far fa-calendar-check' => 'Calendar Check',
							'fas fa-calendar-minus' => 'Calendar Minus',
							'far fa-calendar-minus' => 'Calendar Minus',
							'fas fa-calendar-plus' => 'Calendar Plus',
							'far fa-calendar-plus' => 'Calendar Plus',
							'fas fa-calendar-times' => 'Calendar Times',
							'far fa-calendar-times' => 'Calendar Times',
							'fas fa-certificate' => 'Certificate',
							'fas fa-check' => 'Check',
							'fas fa-check-circle' => 'Check Circle',
							'far fa-check-circle' => 'Check Circle',
							'fas fa-check-double' => 'Check Double',
							'fas fa-check-square' => 'Check Square',
							'far fa-check-square' => 'Check Square',
							'fas fa-circle' => 'Circle',
							'far fa-circle' => 'Circle',
							'fas fa-clipboard' => 'Clipboard',
							'far fa-clipboard' => 'Clipboard',
							'fas fa-clone' => 'Clone',
							'far fa-clone' => 'Clone',
							'fas fa-cloud' => 'Cloud',
							'fas fa-cloud-download-alt' => 'Cloud Download Alt',
							'fas fa-cloud-upload-alt' => 'Cloud Upload Alt',
							'fas fa-cog' => 'Cog',
							'fas fa-cogs' => 'Cogs',
							'fas fa-database' => 'Database',
							'fas fa-dot-circle' => 'Dot Circle',
							'far fa-dot-circle' => 'Dot Circle',
							'fas fa-download' => 'Download',
							'fas fa-ellipsis-h' => 'Ellipsis H',
							'fas fa-ellipsis-v' => 'Ellipsis V',
							'fas fa-exclamation' => 'Exclamation',
							'fas fa-exclamation-circle' => 'Exclamation Circle',
							'fas fa-exclamation-triangle' => 'Exclamation Triangle',
							'fas fa-external-link-alt' => 'External Link Alt',
							'fas fa-external-link-square-alt' => 'External Link Square Alt',
							'fas fa-file-download' => 'File Download',
							'fas fa-file-export' => 'File Export',
							'fas fa-file-import' => 'File Import',
							'fas fa-file-upload' => 'File Upload',
							'fas fa-filter' => 'Filter',
							'fas fa-fingerprint' => 'Fingerprint',
							'fas fa-flag' => 'Flag',
							'far fa-flag' => 'Flag',
							'fas fa-flag-checkered' => 'Flag Checkered',
							'fas fa-grip-horizontal' => 'Grip Horizontal',
							'fas fa-grip-lines' => 'Grip Lines',
							'fas fa-grip-lines-vertical' => 'Grip Lines Vertical',
							'fas fa-grip-vertical' => 'Grip Vertical',
							'fas fa-hashtag' => 'Hashtag',
							'fas fa-info' => 'Info',
							'fas fa-info-circle' => 'Info Circle',
							'fas fa-language' => 'Language',
							'fas fa-magic' => 'Magic',
							'fas fa-medal' => 'Medal',
							'fas fa-minus' => 'Minus',
							'fas fa-minus-circle' => 'Minus Circle',
							'fas fa-minus-square' => 'Minus Square',
							'far fa-minus-square' => 'Minus Square',
							'fas fa-plus-circle' => 'Plus Square',
							'fas fa-plus-square' => 'Plus Square',
							'far fa-question' => 'Question',
							'fas fa-search' => 'Search',
							'fas fa-search-minus' => 'Search Minus',
							'fas fa-search-plus' => 'Search Plus',
							'fas fa-share' => 'Share',
							'fas fa-share-alt' => 'Share Alt',
							'fas fa-share-alt-square' => 'Share Alt Square',
							'fas fa-share-square' => 'Share Square',
							'far fa-share-square' => 'Share Square',
							'fas fa-shield-alt' => 'Shield Alt',
							'fas fa-sign-in-alt' => 'Sign In Alt',
							'fas fa-sign-out-alt' => 'Sign Out Alt',
							'fas fa-signal' => 'Signal',
							'fas fa-sitemap' => 'Sitemap',
							'fas fa-sliders-h' => 'Sliders H',
							'fas fa-sort' => 'Sort',
							'fas fa-star' => 'fa-star',
							'far fa-star' => 'fa-star',
							'fas fa-star-half' => 'fa-star-half',
							'far fa-star-half' => 'fa-star-half',
							'fas fa-sync' => 'fa-sync',
							'fas fa-sync-alt' => 'fa-sync-alt',
							'fas fa-thumbs-down' => 'fa-thumbs-down',
							'far fa-thumbs-down' => 'fa-thumbs-down',
							'fas fa-thumbs-up' => 'fa-thumbs-up',
							'far fa-thumbs-up' => 'fa-thumbs-up',
							'fas fa-times' => 'fa-times',
							'fas fa-times-circle' => 'fa-times-circle',
							'far fa-times-circle' => 'fa-times-circle',
							'fas fa-toggle-off' => 'fa-toggle-off',
							'fas fa-toggle-on' => 'fa-toggle-on',
							'fas fa-tools' => 'fa-tools',
							'fas fa-trash' => 'fa-trash',
							'fas fa-trash-alt' => 'fa-trash-alt',
							'far fa-trash-alt' => 'fa-trash-alt',
							'fas fa-trash-restore' => 'fa-trash-restore',
							'fas fa-trash-restore-alt' => 'fa-trash-restore-alt',
							'fas fa-trophy' => 'Trophy',
							'fas fa-user' => 'User',
							'far fa-user' => 'User',
							'fas fa-user-alt' => 'User Alt',
							'fas fa-user-circle' => 'User Circle',
							'far fa-user-circle' => 'User Circle',
							'fas fa-volume-down' => 'Volume Down',
							'fas fa-volume-mute' => 'Volume Mute',
							'fas fa-volume-off' => 'Volume Off',
							'fas fa-volume-up' => 'Volume Up',
							'fas fa-wifi' => 'Wifi',
							'fas fa-wrench' => 'Wrench',
						]
					],
					32 => [
						'title' => 'Payments icons',
						'icon' => [
							'fab fa-alipay' => 'Alipay',
							'fab fa-amazon-pay' => 'Amazon Pay',
							'fab fa-apple-pay' => 'Apple Pay',
							'fas fa-bookmark' => 'Bookmark',
							'far fa-bookmark' => 'Bookmark',
							'fas fa-camera' => 'Camera',
							'fas fa-camera-retro' => 'Camera Retro',
							'fab fa-cc-amazon-pay' => 'Cc Amazon Pay',
							'fab fa-cc-amex' => 'Cc Amex',
							'fab fa-cc-apple-pay' => 'Cc Apple Pay',
							'fab fa-cc-diners-club' => 'Cc Diners Club',
							'fab fa-cc-discover' => 'Cc Discover',
							'fab fa-cc-jcb' => 'Cc Jcb',
							'fab fa-cc-mastercard' => 'Cc Mastercard',
							'fab fa-cc-paypal' => 'Cc Paypal',
							'fab fa-cc-stripe' => 'Cc Stripe',
							'fas fa-cc-visa' => 'Cc Visa',
							'fab fa-ethereum' => 'Ethereum',
							'fas fa-gem' => 'Gem',
							'far fa-gem' => 'Gem',
							'fas fa-google-pay' => 'Google Pay',
							'fab fa-google-wallet' => 'Google Wallet',
							'fas fa-key' => 'Key',
							'fas fa-money-check' => 'Money Check',
							'fas fa-money-check-alt' => 'oney Check Alt',
							'fab fa-paypal' => 'Paypal',
							'fas fa-receipt' => 'Receipt',
							'fas fa-shopping-bag' => 'Shopping Bag',
							'fas fa-shopping-basket' => 'Shopping Basket',
							'fas fa-shopping-cart' => 'Shopping Cart',
							'fab fa-stripe' => 'Stripe',
							'fab fa-stripe-s' => 'Stripe S'
						]
					],
					33 => [
						'title' => 'Music icons',
						'icon' => [
							'fas fa-drum' => 'Drum',
							'fas fa-drum-steelpan' => 'Drum Steelpan',
							'fas fa-guitar' => 'Guitar',
							'fas fa-music' => 'Music',
							'fab fa-napster' => 'Napster',
							'fas fa-play' => 'Play',
							'fas fa-record-vinyl' => 'Record Vinyl',
							'fas fa-soundcloud' => 'Soundcloud',
							'fas fa-spotify' => 'Spotify'
						]
					],
					34 => [
						'title' => 'Moving icons',
						'icon' => [
							'fas fa-box-open' => 'Box Open',
							'fas fa-caravan' => 'Caravan',
							'fas fa-couch' => 'Couch',
							'fas fa-dolly' => 'Dolly',
							'fas fa-people-carry' => 'People Carry',
							'fas fa-route' => 'Route',
							'fas fa-sign' => 'Sign',
							'fas fa-suitcase' => 'Suitcase',
							'fas fa-tape' => 'Tape',
							'fas fa-trailer' => 'Trailer',
							'fas fa-truck-loading' => 'Truck Loading',
							'fas fa-truck-moving' => 'Truck Moving',
							'fas fa-wine-glass' => 'Wine Glass'
						]
					],
					35 => [
						'title' => 'Mathematics icons',
						'icon' => [
							'fas fa-divide' => 'Divide',
							'fas fa-equals' => 'Equals',
							'fas fa-greater-than' => 'Greater Than',
							'fas fa-greater-than-equal' => 'Greater Than Equal',
							'fas fa-infinity' => 'Infinity',
							'fas fa-less-than' => 'Less Than',
							'fas fa-less-than-equal' => 'Less Than Equal',
							'fas fa-minus' => 'Minus',
							'fas fa-not-equal' => 'Not Equal',
							'fas fa-percentage' => 'Percentage',
							'fas fa-plus' => 'Plus',
							'fas fa-square-root-alt' => 'Square Root Alt',
							'fas fa-subscript' => 'Subscript',
							'fas fa-superscript' => 'Superscript',
							'fas fa-times' => 'Times',
							'fas fa-wave-square' => 'Wave Square',
						]
					],
					36 => [
						'title' => 'Logistics icons',
						'icon' => [
							'fas fa-box' => 'Box',
							'fas fa-boxes' => 'Boxes',
							'fas fa-clipboard-check' => 'Clipboard Check	',
							'fas fa-clipboard-list' => 'Clipboard List',
							'fas fa-dolly' => 'Dolly',
							'fas fa-dolly-flatbed' => 'Dolly Flatbed',
							'fas fa-hard-hat' => 'Hard Hat',
							'fas fa-pallet' => 'Pallet',
							'fas fa-shipping-fast' => 'Shipping Fast',
							'fas fa-truck' => 'Truck',
							'fas fa-warehouse' => 'Warehouse',
						]
					],
					37 => [
						'title' => 'Weather icons',
						'icon' => [
							'fas fa-bolt' => 'Bolt',
							'fas fa-cloud' => 'Cloud',
							'fas fa-cloud-meatball' => 'Cloud Meatball',
							'fas fa-cloud-moon' => 'Cloud Moon',
							'fas fa-cloud-moon-rain' => 'Cloud Moon Rain',
							'fas fa-cloud-rain' => 'Cloud Rain',
							'fas fa-cloud-showers-heavy' => 'Cloud Showers Heavy',
							'fas fa-cloud-sun' => 'Cloud Sun',
							'fas fa-cloud-sun-rain' => 'Cloud Sun Rain',
							'fas fa-meteor' => 'Meteor',
							'fas fa-moon' => 'Moon',
							'far fa-moon' => 'Moon',
							'fas fa-poo-storm' => 'Poo Storm',
							'fas fa-rainbow' => 'Rainbow',
							'fas fa-smog' => 'Smog',
							'fas fa-snowflake' => 'Snowflake',
							'far fa-snowflake' => 'Snowflake',
							'fas fa-sun' => 'Sun',
							'far fa-sun' => 'Sun',
							'fas fa-temperature-high' => 'Temperature High',
							'fas fa-temperature-low' => 'Temperature Low',
							'fas fa-umbrella' => 'Umbrella',
							'fas fa-water' => 'Water',
							'fas fa-wind' => 'Wind'
						]
					],
					38 => [
						'title' => 'Pharmacy icons',
						'icon' => [
							'fas fa-band-aid' => 'Band Aid',
							'fas fa-book-medical' => 'Book Medical',
							'fas fa-cannabis' => 'Cannabis',
							'fas fa-capsules' => 'Capsules',
							'fas fa-clinic-medical' => 'Clinic Medical',
							'fas fa-disease' => 'Disease',
							'fas fa-eye-dropper' => 'Eye Dropper',
							'fas fa-file-medical' => 'File Medical',
							'fas fa-file-prescription' => 'File Prescription',
							'fas fa-first-aid' => 'First Aid',
							'fas fa-flask' => 'Flask',
							'fas fa-history' => 'History',
							'fas fa-joint' => 'Joint',
							'fas fa-laptop-medical' => 'Laptop Medical',
							'fas fa-mortar-pestle' => 'Mortar Pestle',
							'fas fa-notes-medical' => 'Notes Medical',
							'fas fa-pills' => 'Pills',
							'fas fa-prescription' => 'Prescription',
							'fas fa-prescription-bottle' => 'Prescription Bottle',
							'fas fa-prescription-bottle-alt' => 'Prescription Bottle Alt',
							'fas fa-receipt' => 'Receipt',
							'fas fa-skull-crossbones' => 'Skull Crossbones',
							'fas fa-syringe' => 'Syringe',
							'fas fa-tablets' => 'Tablets',
							'fas fa-thermometer' => 'Thermometer',
							'fas fa-vial' => 'Vial',
							'fas fa-vials' => 'Vials'
						]
					],
					39 => [
						'title' => 'Sports icons',
						'icon' => [
							'fas fa-baseball-ball' => 'Baseball Ball',
							'fas fa-basketball-ball' => 'Basketball Ball',
							'fas fa-biking' => 'Biking',
							'fas fa-bowling-ball' => 'Bowling Ball',
							'fas fa-dumbbell' => 'Dumbbell',
							'fas fa-football-ball' => 'Football Ball',
							'fas fa-futbol' => 'Futbol',
							'far fa-futbol' => 'Futbol',
							'fas fa-golf-ball' => 'Golf Ball',
							'fas fa-hockey-puck' => 'Hockey Puck',
							'fas fa-quidditch' => 'Quidditch',
							'fas fa-running' => 'Running',
							'fas fa-skating' => 'Skating',
							'fas fa-skiing' => 'Skiing',
							'fas fa-skiing-nordic' => 'Skiing Nordic',
							'fas fa-snowboarding' => 'Snowboarding',
							'fas fa-swimmer' => 'Swimmer',
							'fas fa-table-tennis' => 'Table Tennis',
							'fas fa-volleyball-ball' => 'Volleyball Ball',
						]
					],
					40 => [
						'title' => 'Medical icons',
						'icon' => [
							'fas fa-allergies' => 'Allergies',
							'fas fa-ambulance' => 'Ambulance',
							'fas fa-bacteria' => 'Bacteria',
							'fas fa-bacterium' => 'Bacterium',
							'fas fa-band-aid' => 'Band Aid',
							'fas fa-biohazard' => 'Biohazard',
							'fas fa-bone' => 'Bone',
							'fas fa-bong' => 'Bong',
							'fas fa-brain' => 'Brain',
							'fas fa-id-card-alt' => 'fa-id-card-alt',
							'fas fa-lungs' => 'Lungs',
							'fas fa-lungs-virus' => 'Lungs Virus',
							'fas fa-microscope' => 'Microscope',
							'fas fa-smoking' => 'Smoking',
							'fas fa-smoking-ban' => 'Smoking Ban',
							'fas fa-star-of-life' => 'Star Of Life',
							'fas fa-teeth' => 'Teeth',
							'fas fa-teeth-open' => 'Teeth Open',
							'fas fa-thermometer' => 'Thermometer',
							'fas fa-tooth' => 'Tooth',
							'fas fa-user-md' => 'User Md',
							'fas fa-user-nurse' => 'User Nurse',
							'fas fa-virus' => 'Virus',
							'fas fa-virus-slash' => 'Virus Slash',
							'fas fa-viruses' => 'Viruses',
							'fas fa-weight' => 'Weight',
							'fas fa-x-ray' => 'Ray',
						]
					],
					41 => [
						'title' => 'Summer icons',
						'icon' => [
							'fas fa-anchor' => 'Anchor',
							'fas fa-fish' => 'Fish',
							'fas fa-hotdog' => 'Hotdog',
							'fas fa-swimming-pool' => 'Swimming Pool',
							'fas fa-umbrella-beach' => 'Umbrella Beach',
							'fas fa-volleyball-ball' => 'Volleyball Ball',
							'fas fa-water' => 'Water'
						]
					],
					42 => [
						'title' => 'Security icons',
						'icon' => [
							'fas fa-door-closed' => 'Door Closed',
							'fas fa-door-open' => 'Door Open',
							'fas fa-file-contract' => 'File Contract',
							'fas fa-file-signature' => 'File Signature',
							'fas fa-id-badge' => 'd Badge',
							'far fa-id-badge' => 'd Badge',
							'fas fa-id-card' => 'Id Card',
							'far fa-id-card' => 'Id Card',
							'fas fa-lock' => 'Lock',
							'fas fa-lock-open' => 'Lock Open',
							'fas fa-mask' => 'Mask',
							'fas fa-passport' => 'Passport',
							'fas fa-unlock' => 'Unlock',
							'fas fa-unlock-alt' => 'Unlock Alt',
							'fas fa-user-lock' => 'User Lock',
							'fas fa-user-secret' => 'User Secret',
							'fas fa-user-shield' => 'User Shield'
						]
					],
					43 => [
						'title' => 'Halloween icons',
						'icon' => [
							'fas fa-book-dead' => 'Book Dead',
							'fas fa-broom' => 'Broom',
							'fas fa-cat' => 'Cat',
							'fas fa-cloud-moon' => 'Cloud Moon',
							'fas fa-crow' => 'Crow',
							'fas fa-ghost' => 'Ghost',
							'fas fa-hat-wizard' => 'Hat Wizard',
							'fas fa-mask' => 'Mask',
							'fas fa-skull-crossbones' => 'Skull Crossbones',
							'fas fa-spider' => 'Spider',
							'fas fa-toilet-paper' => 'Toilet Paper'
						]
					],
					44 => [
						'title' => 'Religion icons',
						'icon' => [
							'fas fa-ankh' => 'Ankh',
							'fas fa-atom' => 'Atom',
							'fas fa-bahai' => 'Bahai',
							'fas fa-bible' => 'Bible',
							'fas fa-church' => 'Church',
							'fas fa-cross' => 'Cross',
							'fas fa-dharmachakra' => 'Dharmachakra',
							'fas fa-dove' => 'Dove',
							'fas fa-gopuram' => 'Gopuram',
							'fas fa-hamsa' => 'Hamsa',
							'fas fa-hanukiah' => 'Hanukiah',
							'fas fa-jedi' => 'Jedi',
							'fas fa-journal-whills' => 'Journal Whills',
							'fas fa-kaaba' => 'Kaaba',
							'fas fa-khanda' => 'Khanda',
							'fas fa-menorah' => 'Menorah',
							'fas fa-mosque' => 'Mosque',
							'fas fa-om' => 'Om',
							'fas fa-pastafarianism' => 'Pastafarianism',
							'fas fa-peace' => 'Peace',
							'fas fa-place-of-worship' => 'Place Of Worship',
							'fas fa-pray' => 'Pray',
							'fas fa-praying-hands' => 'Praying Hands',
							'fas fa-quran' => 'Quran',
							'fas fa-star-and-crescent' => 'Star And Crescent',
							'fas fa-star-of-david' => 'Star Of David',
							'fas fa-synagogue' => 'Synagogue',
							'fas fa-torah' => 'Torah',
							'fas fa-torii-gate' => 'Torii Gate',
							'fas fa-vihara' => 'Vihara',
							'fas fa-yin-yang' => 'Yin Yang',
						]
					],
					45 => [
						'title' => 'Genders icons',
						'icon' => [
							'fas fa-genderless' => 'Genderless',
							'fas fa-mars' => 'Mars',
							'fas fa-mars-double' => 'Mars Double',
							'fas fa-mars-stroke' => 'Mars Stroke',
							'fas fa-mars-stroke-h' => 'Mars Stroke H',
							'fas fa-mars-stroke-v' => 'Mars Stroke V',
							'fas fa-mercury' => 'Mercury',
							'fas fa-neuter' => 'Neuter',
							'fas fa-transgender' => 'Transgender',
							'fas fa-transgender-alt' => 'Transgender Alt',
							'fas fa-venus' => 'Venus',
							'fas fa-venus-double' => 'Venus Double',
							'fas fa-venus-mars' => 'Venus Mars'
						]
					],
					46 => [
						'title' => 'Science Fiction icons',
						'icon' => [
							'fab fa-atom' => 'Atom',
							'fab fa-galactic-republic' => 'Galactic Republic',
							'fab fa-galactic-senate' => 'Galactic Senate',
							'fas fa-globe' => 'Globe',
							'fas fa-hand-spock' => 'Hand Spock',
							'far fa-hand-spock' => 'Hand Spock',
							'fas fa-jedi' => 'Jedi',
							'fab fa-jedi-order' => 'Jedi Order',
							'fas fa-journal-whills' => 'Journal Whills',
							'fas fa-meteor' => 'Meteor',
							'fas fa-moon' => 'Moon',
							'far fa-moon' => 'Moon',
							'fab fa-old-republic' => 'Old Republic',
							'fas fa-robot' => 'Robot',
							'fas fa-rocket' => 'Rocket',
							'fas fa-satellite' => 'Satellite',
							'fas fa-satellite-dish' => 'Satellite Dish',
							'fas fa-space-shuttle' => 'Space Shuttle',
							'fas fa-user-astronaut' => 'User Astronaut'
						]
					],
					47 => [
						'title' => 'Spinners icons',
						'icon' => [
							'fas fa-asterisk' => 'Asterisk',
							'fas fa-atom' => 'Atom',
							'fas fa-bahai' => 'Bahai',
							'fas fa-certificate' => 'Certificate',
							'fas fa-circle-notch' => 'Circle Notch',
							'fas fa-cog' => 'Cog',
							'fas fa-compact-disc' => 'Compact Disc',
							'fas fa-compass' => 'Compass',
							'fas fa-crosshairs' => 'Crosshairs',
							'fas fa-dharmachakra' => 'Dharmachakra',
							'fas fa-fan' => 'Fan',
							'fas fa-life-ring' => 'Life Ring',
							'fas fa-palette' => 'Palette',
							'fas fa-ring' => 'Ring',
							'fas fa-slash' => 'Slash',
							'fas fa-snowflake' => 'Snowflake',
							'fas fa-spinner' => 'Spinner',
							'fas fa-stroopwafel' => 'Stroopwafel',
							'fas fa-sun' => 'Sun',
							'fas fa-sync' => 'Sync',
							'fas fa-sync-alt' => 'Sync Alt',
							'fas fa-yin-yang' => 'Yin Yang',
						]
					],
					48 => [
						'title' => 'Toggle icons',
						'icon' => [
							'fas fa-bullseye' => 'Bullseye',
							'fas fa-check-circle' => 'Check Circle',
							'far fa-check-circle' => 'Check Circle',
							'fas fa-circle' => 'Circle',
							'far fa-circle' => 'Circle',
							'fas fa-dot-circle' => 'Dot Circle',
							'far fa-dot-circle' => 'Dot Circle',
							'fas fa-microphone' => 'Microphone',
							'fas fa-microphone-slash' => 'Microphone Slash',
							'fas fa-star' => 'Star',
							'far fa-star' => 'Star',
							'fas fa-star-half' => 'Star Half',
							'far fa-star-half' => 'Star Half',
							'fas fa-star-half-alt' => 'Star Half Alt',
							'fas fa-toggle-off' => 'Toggle Off',
							'fas fa-toggle-on' => 'Toggle On',
							'fas fa-wifi' => 'Wifi',
						]
					],
					49 => [
						'title' => 'Tabletop Gaming icons',
						'icon' => [
							'fab fa-acquisitions-incorporated' => 'Acquisitions Incorporated',
							'fas fa-book-dead' => 'Book Dead',
							'fab fa-critical-role' => 'Critical Role',
							'fab fa-d-and-d' => 'D And D',
							'fab fa-d-and-d-beyond' => 'D And D Beyond',
							'fas fa-dice-d20' => 'Dice D20',
							'fas fa-dice-d6' => 'Dice D6',
							'fas fa-dragon' => 'Dragon',
							'fas fa-dungeon' => 'Dungeon',
							'fab fa-fantasy-flight-games' => 'Fantasy Flight Games',
							'fas fa-fist-raised' => 'ist Raised',
							'fas fa-hat-wizard' => 'Hat Wizard',
							'fas fa-penny-arcade' => 'Penny Arcade',
							'fas fa-ring' => 'Ring',
							'fas fa-scroll' => 'Scroll',
							'fas fa-skull-crossbones' => 'Skull Crossbones',
							'fab fa-wizards-of-the-coast' => 'Wizards Of The Coast'
						]
					],
					50 => [
						'title' => 'Writing icons',
						'icon' => [
							'fas fa-archive' => 'Archive',
							'fas fa-blog' => 'Blog',
							'fas fa-book' => 'Book',
							'fas fa-bookmark' => 'Bookmark',
							'far fa-bookmark' => 'Bookmark',
							'fas fa-edit' => 'Edit',
							'far fa-edit' => 'Edit',
							'fas fa-envelope' => 'Envelope',
							'far fa-envelope' => 'Envelope',
							'fas fa-envelope-open' => 'Envelope Open',
							'far fa-envelope-open' => 'Envelope Open',
							'fas fa-eraser' => 'Eraser',
							'fas fa-file' => 'File',
							'far fa-file' => 'File',
							'fas fa-file-alt' => 'File Alt',
							'far fa-file-alt' => 'File Alt',
							'fas fa-folder' => 'Folder',
							'far fa-folder' => 'Folder',
							'fas fa-folder-open' => 'Folder Open',
							'far fa-folder-open' => 'Folder Open',
							'fas fa-keyboard' => 'Keyboard',
							'far fa-keyboard' => 'Keyboard',
							'fas fa-newspaper' => 'Newspaper',
							'far fa-newspaper' => 'Newspaper',
							'fas fa-paper-plane' => 'Paper Plane',
							'far fa-paper-plane' => 'Paper Plane',
							'fas fa-paperclip' => 'Paperclip',
							'fas fa-paragraph' => 'Paragraph',
							'fas fa-pen' => 'Pen',
							'fas fa-pen-alt' => 'Pen Alt',
							'fas fa-pen-square' => 'Pen Square',
							'fas fa-pencil-alt' => 'Pencil Alt',
							'fas fa-quote-left' => 'Quote Left',
							'fas fa-quote-right' => 'Quote Right',
							'fas fa-sticky-note' => 'Sticky Note',
							'far fa-sticky-note' => 'Sticky Note',
							'fas fa-thumbtack' => 'Thumbtack',
						]
					],
					51 => [
						'title' => 'Winter icons',
						'icon' => [
							'fas fa-glass-whiskey' => 'Glass Whiskey	',
							'fas fa-icicles' => 'Icicles',
							'fas fa-igloo' => 'Igloo',
							'fas fa-mitten' => 'Mitten',
							'fas fa-skating' => 'Skating',
							'fas fa-skiing' => 'Skiing',
							'fas fa-skiing-nordic' => 'Skiing Nordic',
							'fas fa-snowboarding' => 'Snowboarding',
							'fas fa-snowplow' => 'Snowplow',
							'fas fa-tram' => 'Tram',
						]
					],
					52 => [
						'title' => 'Vehicles icons',
						'icon' => [
							'fab fa-accessible-icon' => 'Accessible Icon',
							'fas fa-ambulance' => 'Ambulance',
							'fas fa-baby-carriage' => 'Baby Carriage',
							'fas fa-bicycle' => 'Bicycle',
							'fas fa-bus' => 'Bus',
							'fas fa-bus-alt' => 'Bus Alt',
							'fas fa-car' => 'Car',
							'fas fa-car-alt' => 'Car Alt',
							'fas fa-car-crash' => 'Car Crash',
							'fas fa-car-side' => 'Car Side',
							'fas fa-fighter-jet' => 'Fighter Jet',
							'fas fa-helicopter' => 'Helicopter',
							'fas fa-horse' => 'Horse',
							'fas fa-motorcycle' => 'Motorcycle',
							'fas fa-paper-plane' => 'Paper Plane',
							'far fa-paper-plane' => 'Paper Plane',
							'fas fa-plane' => 'Plane',
							'fas fa-rocket' => 'Rocket',
							'fas fa-ship' => 'Ship',
							'fas fa-shopping-cart' => 'Shopping Cart',
							'fas fa-shuttle-van' => 'Shuttle Van',
							'fas fa-sleigh' => 'Sleigh',
							'fas fa-snowplow' => 'Snowplow',
							'fas fa-space-shuttle' => 'Space Shuttle',
							'fas fa-subway' => 'Subway',
							'fas fa-taxi' => 'Taxi',
							'fas fa-tractor' => 'Tractor',
							'fas fa-train' => 'Train',
							'fas fa-tram' => 'Tram',
							'fas fa-truck' => 'Truck',
							'fas fa-truck-monster' => 'Truck Monster',
							'fas fa-truck-pickup' => 'Truck Pickup',
							'fas fa-wheelchair' => 'Wheelchair'
						]
					],
					53 => [
						'title' => 'Science icons',
						'icon' => [
							'fas fa-atom' => 'Atom',
							'fas fa-biohazard' => 'Biohazard',
							'fas fa-brain' => 'Brain',
							'fas fa-burn' => 'Burn',
							'fas fa-capsules' => 'Capsules',
							'fas fa-clipboard-check' => 'Clipboard Check',
							'fas fa-disease' => 'Disease',
							'fas fa-dna' => 'Dna',
							'fas fa-eye-dropper' => 'Eye Dropper',
							'fas fa-filter' => 'Filter',
							'fas fa-fire' => 'Fire',
							'fas fa-fire-alt' => 'Fire Alt',
							'fas fa-flask' => 'Flask',
							'fas fa-frog' => 'Frog',
							'fas fa-magnet' => 'Magnet',
							'fas fa-microscope' => 'Microscope',
							'fas fa-mortar-pestle' => 'Mortar Pestle',
							'fas fa-pills' => 'Pills',
							'fas fa-prescription-bottle' => 'Prescription Bottle',
							'fas fa-radiation' => 'Radiation',
							'fas fa-radiation-alt' => 'Radiation Alt',
							'fas fa-seedling' => 'Seedling',
							'fas fa-skull-crossbones' => 'Skull Crossbones',
							'fas fa-syringe' => 'Syringe',
							'fas fa-tablets' => 'Tablets',
							'fas fa-temperature-high' => 'Temperature High',
							'fas fa-temperature-low' => 'Temperature Low',
							'fas fa-vial' => 'Vial',
							'fas fa-vials' => 'Vials'
						]
					],
					54 => [
						'title' => 'Maritime icons',
						'icon' => [
							'fas fa-anchor' => 'Anchor',
							'fas fa-binoculars' => 'Binoculars',
							'fas fa-compass' => 'Compass',
							'far fa-compass' => 'Compass',
							'fas fa-dharmachakra' => 'Dharmachakra',
							'fas fa-frog' => 'Frog',
							'fas fa-ship' => 'Ship',
							'fas fa-skull-crossbones' => 'Skull Crossbones',
							'fas fa-swimmer' => 'Swimmer',
							'fas fa-water' => 'Water',
							'fas fa-wind' => 'Wind',
						]
					],
					55 => [
						'title' => 'Images icons',
						'icon' => [
							'fas fa-adjust' => 'Adjust',
							'fas fa-bolt' => 'Bolt',
							'fas fa-camera' => 'Camera',
							'fas fa-camera-retro' => 'Camera Retro',
							'fas fa-chalkboard' => 'Chalkboard',
							'fas fa-clone' => 'Clone',
							'far fa-clone' => 'Clone',
							'fas fa-compress' => 'Compress',
							'fas fa-compress-arrows-alt' => 'Compress Arrows Alt',
							'fas fa-expand' => 'Expand',
							'fas fa-eye' => 'Eye',
							'far fa-eye' => 'Eye',
							'fas fa-eye-dropper' => 'Eye Dropper',
							'fas fa-eye-slash' => 'Eye Slash',
							'far fa-eye-slash' => 'Eye Slash',
							'fas fa-file-image' => 'File Image',
							'far fa-file-image' => 'File Image',
							'fas fa-film' => 'Film',
							'fas fa-id-badge' => 'Id Badge',
							'far fa-id-badge' => 'Id Badge',
							'fas fa-id-card' => 'Id Card',
							'far fa-id-card' => 'Id Card',
							'fas fa-image' => 'Image',
							'far fa-image' => 'Image',
							'fas fa-images' => 'Images',
							'far fa-images' => 'Images',
							'fas fa-photo-video' => 'Photo Video',
							'fas fa-portrait' => 'Portrait',
							'fas fa-sliders-h' => 'Sliders H',
							'fas fa-tint' => 'Tint',
							'fab fa-unsplash' => 'Unsplash',
						]
					],
					56 => [
						'title' => 'Shapes icons',
						'icon' => [
							'fas fa-bookmark' => 'Bookmark',
							'far fa-bookmark' => 'Bookmark',
							'fas fa-calendar' => 'Calendar',
							'far fa-calendar' => 'Calendar',
							'fas fa-certificate' => 'Certificate',
							'fas fa-circle' => 'Circle',
							'far fa-circle' => 'Circle',
							'fas fa-cloud' => 'Cloud',
							'fas fa-comment' => 'Comment',
							'far fa-comment' => 'Comment',
							'fas fa-file' => 'File',
							'far fa-file' => 'File',
							'fas fa-folder' => 'Folder',
							'far fa-folder' => 'Folder',
							'fas fa-heart' => 'Heart',
							'far fa-heart' => 'Heart',
							'fas fa-heart-broken' => 'Heart Broken',
							'fas fa-map-marker' => 'Map Marker',
							'fas fa-play' => 'Play',
							'fas fa-shapes' => 'Shapes',
							'fas fa-square' => 'Square',
							'far fa-square' => 'Square',
							'fas fa-star' => 'Star',
							'far fa-star' => 'Star',
						]
					],
					57 => [
						'title' => 'Hotel icons',
						'icon' => [
							'fas fa-baby-carriage' => 'Baby Carriage',
							'fas fa-bath' => 'Bath',
							'fas fa-bed' => 'Bed',
							'fas fa-briefcase' => 'Briefcase',
							'fas fa-car' => 'Car',
							'fas fa-cocktail' => 'Cocktail',
							'fas fa-coffee' => 'Coffee',
							'fas fa-concierge-bell' => 'Concierge Bell',
							'fas fa-dice' => 'Dice',
							'fas fa-dice-five' => 'Dice Five',
							'fas fa-door-closed' => 'Door Closed',
							'fas fa-door-open' => 'Door Open',
							'fas fa-dumbbell' => 'Dumbbell',
							'fas fa-glass-martini' => 'Glass Martini',
							'fas fa-glass-martini-alt' => 'Glass Martini Alt',
							'fas fa-hot-tub' => 'Hot Tub',
							'fas fa-hotel' => 'Hotel',
							'fas fa-infinity' => 'Infinity',
							'fas fa-key' => 'Key',
							'fas fa-luggage-cart' => 'Luggage Cart',
							'fas fa-shower' => 'Shower',
							'fas fa-shuttle-van' => 'Shuttle Van',
							'fas fa-smoking' => 'Smoking',
							'fas fa-smoking-ban' => 'Smoking Ban',
							'fas fa-snowflake' => 'Snowflake',
							'far fa-snowflake' => 'Snowflake',
							'fas fa-spa' => 'Spa',
							'fas fa-suitcase' => 'Suitcase',
							'fas fa-suitcase-rolling' => 'Suitcase Rolling',
							'fas fa-swimmer' => 'Swimmer',
							'fas fa-swimming-pool' => 'Swimming Pool',
							'fas fa-tv' => 'Tv',
							'fas fa-umbrella-beach' => 'Umbrella Beach',
							'fas fa-utensils' => 'Utensils',
							'fas fa-wheelchair' => 'Wheelchair',
							'fas fa-wifi' => 'Wifi',
						]
					]
				];
			}
		}
		new MP_Select_Icon_image();
	}