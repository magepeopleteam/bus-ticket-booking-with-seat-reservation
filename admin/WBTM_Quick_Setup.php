<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Quick_Setup')) {
		class WBTM_Quick_Setup {
			public function __construct() {
				add_action('admin_menu', array($this, 'quick_setup_menu'));
			}
			public function quick_setup_menu() {
				$status = MP_Global_Function::check_woocommerce();
				if ($status == 1) {
					add_submenu_page('edit.php?post_type=wbtm_bus', __('Quick Setup', 'bus-ticket-booking-with-seat-reservation'), '<span style="color:#10dd10">' . esc_html__('Quick Setup', 'bus-ticket-booking-with-seat-reservation') . '</span>', 'manage_options', 'wbtm_quick_setup', array($this, 'quick_setup'));
					add_submenu_page('wbtm_bus', esc_html__('Quick Setup', 'bus-ticket-booking-with-seat-reservation'), '<span style="color:#10dd10">' . esc_html__('Quick Setup', 'bus-ticket-booking-with-seat-reservation') . '</span>', 'manage_options', 'wbtm_quick_setup', array($this, 'quick_setup'));
				}
				else {
					add_menu_page(esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'), esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'), 'manage_options', 'wbtm_bus', array($this, 'quick_setup'), 'bus-icon.svg', 6);
					add_submenu_page('wbtm_bus', esc_html__('Quick Setup', 'bus-ticket-booking-with-seat-reservation'), '<span style="color:#10dd17">' . esc_html__('Quick Setup', 'bus-ticket-booking-with-seat-reservation') . '</span>', 'manage_options', 'wbtm_quick_setup', array($this, 'quick_setup'));
				}
			}
			public function quick_setup() {
				$status = MP_Global_Function::check_woocommerce();
				if (isset($_POST['active_woo_btn']) && (isset($_POST['wbtm_qs_nonce']) && wp_verify_nonce($_POST['wbtm_qs_nonce'], 'wbtm_qs_nonce'))) {
					?>
					<script>
						dLoaderBody();
					</script>
					<?php
					activate_plugin('woocommerce/woocommerce.php');
					Wbtm_Woocommerce_bus::on_activation_page_create();
					?>
					<script>
						(function ($) {
							"use strict";
							$(document).ready(function () {
								let wbtm_admin_location = window.location.href;
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?post_type=wbtm_bus&page=wbtm_quick_setup', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_quick_setup', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								window.location.href = wbtm_admin_location;
							});
						}(jQuery));
					</script>
					<?php
				}
				if (isset($_POST['install_and_active_woo_btn']) && (isset($_POST['wbtm_qs_nonce']) && wp_verify_nonce($_POST['wbtm_qs_nonce'], 'wbtm_qs_nonce'))) {
					echo '<div style="display:none">';
					include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
					include_once(ABSPATH . 'wp-admin/includes/file.php');
					include_once(ABSPATH . 'wp-admin/includes/misc.php');
					include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
					$plugin = 'woocommerce';
					$api = plugins_api('plugin_information', array(
						'slug' => $plugin,
						'fields' => array(
							'short_description' => false,
							'sections' => false,
							'requires' => false,
							'rating' => false,
							'ratings' => false,
							'downloaded' => false,
							'last_updated' => false,
							'added' => false,
							'tags' => false,
							'compatibility' => false,
							'homepage' => false,
							'donate_link' => false,
						),
					));
					$title = 'title';
					$url = 'url';
					$nonce = 'nonce';
					$woocommerce_plugin = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('title', 'url', 'nonce', 'plugin', 'api')));
					$woocommerce_plugin->install($api->download_link);
					activate_plugin('woocommerce/woocommerce.php');
					Wbtm_Woocommerce_bus::on_activation_page_create();
					echo '</div>';
					?>
					<script>
						(function ($) {
							"use strict";
							$(document).ready(function () {
								let wbtm_admin_location = window.location.href;
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?post_type=wbtm_bus&page=wbtm_quick_setup', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_quick_setup', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
								window.location.href = wbtm_admin_location;
							});
						}(jQuery));
					</script>
					<?php
				}
				if (isset($_POST['finish_quick_setup']) && (isset($_POST['wbtm_qs_nonce']) && wp_verify_nonce($_POST['wbtm_qs_nonce'], 'wbtm_qs_nonce'))) {
					$label = isset($_POST['bus_menu_label']) ? sanitize_text_field($_POST['bus_menu_label']) : 'Bus';
					$slug = isset($_POST['bus_menu_slug']) ? sanitize_text_field($_POST['bus_menu_slug']) : 'bus';
					$general_settings_data = get_option('wbtm_general_settings');
					$update_general_settings_arr = [
						'label' => $label,
						'slug' => $slug
					];
					$new_general_settings_data =$general_settings_data &&  is_array($general_settings_data) ? array_replace($general_settings_data, $update_general_settings_arr) : $update_general_settings_arr;
					update_option('wbtm_general_settings', $new_general_settings_data);
					wp_redirect(admin_url('edit.php?post_type=wbtm_bus'));
				}
				?>
				<div class="mpStyle">
					<div class="_dShadow_6_adminLayout">
						<form method="post" action="">
                            <?php wp_nonce_field('wbtm_qs_nonce', 'wbtm_qs_nonce'); ?>
							<div class="mpTabsNext">
								<div class="tabListsNext _max_700_mAuto">
									<div data-tabs-target-next="#wbtm_qs_welcome" class="tabItemNext" data-open-text="1" data-close-text=" " data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>1</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('Welcome', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
									</div>
									<div data-tabs-target-next="#wbtm_qs_general" class="tabItemNext" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>2</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('General', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
									</div>
									<div data-tabs-target-next="#wbtm_qs_done" class="tabItemNext" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>3</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('Done', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
									</div>
								</div>
								<div class="tabsContentNext _infoLayout_mT">
									<?php
										$this->setup_welcome_content();
										$this->setup_general_content();
										$this->setup_content_done();
									?>
								</div>
								<?php if ($status == 1) { ?>
									<div class="justifyBetween">
										<button type="button" class="_mpBtn_dBR nextTab_prev">
											<span>&longleftarrow;<?php esc_html_e('Previous', 'bus-ticket-booking-with-seat-reservation'); ?></span>
										</button>
										<div></div>
										<button type="button" class="_themeButton_dBR nextTab_next">
											<span><?php esc_html_e('Next', 'bus-ticket-booking-with-seat-reservation'); ?>&longrightarrow;</span>
										</button>
									</div>
								<?php } ?>
							</div>
						</form>
					</div>
				</div>
				<?php
			}
			public function setup_welcome_content() {
				$status = MP_Global_Function::check_woocommerce();
				?>
				<div data-tabs-next="#wbtm_qs_welcome">
					<h2><?php esc_html_e('Bus Booking with Seat Reservation For Woocommerce Plugin', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('Bus Booking with Seat Reservation Plugin for WooCommerce for your site, Please go step by step and choose some options to get started.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<div class="_dLayout_mT_alignCenter justifyBetween">
						<h5>
							<?php if ($status == 1) {
								esc_html_e('Woocommerce already installed and activated', 'bus-ticket-booking-with-seat-reservation');
							}
							elseif ($status == 0) {
								esc_html_e('Woocommerce need to install and active', 'bus-ticket-booking-with-seat-reservation');
							}
							else {
								esc_html_e('Woocommerce already install , please activate it', 'bus-ticket-booking-with-seat-reservation');
							} ?>
						</h5>
						<?php if ($status == 1) { ?>
							<h5>
								<span class="fas fa-check-circle textSuccess"></span>
							</h5>
						<?php } elseif ($status == 0) { ?>
							<button class="_warningButton_dBR" type="submit" name="install_and_active_woo_btn"><?php esc_html_e('Install & Active Now', 'bus-ticket-booking-with-seat-reservation'); ?></button>
						<?php } else { ?>
							<button class="_themeButton_dBR" type="submit" name="active_woo_btn"><?php esc_html_e('Active Now', 'bus-ticket-booking-with-seat-reservation'); ?></button>
						<?php } ?>
					</div>
				</div>
				<?php
			}
			public function setup_general_content() {
				$label = MP_Global_Function::get_settings('wbtm_general_settings','label',esc_html__('Bus', 'bus-ticket-booking-with-seat-reservation'));
				$slug = MP_Global_Function::get_settings('wbtm_general_settings','slug','bus');
				?>
				<div data-tabs-next="#wbtm_qs_general">
					<div class="section">
						<h2><?php esc_html_e('General settings', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
						<p class="mTB_xs"><?php esc_html_e('Choose some general option.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						<div class="_dLayout_mT">
							<label class="_fullWidth">
								<span class="min_200"><?php esc_html_e('Bus Label:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<input type="text" class="formControl" name="bus_menu_label" value='<?php echo esc_attr($label); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Bus post type label on the entire plugin.', 'bus-ticket-booking-with-seat-reservation'); ?>
							</i>
							<div class="divider"></div>
							<label class="_fullWidth">
								<span class="min_200"><?php esc_html_e('Bus Slug:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<input type="text" class="formControl" name="bus_menu_slug" value='<?php echo esc_attr($slug); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Bus slug on the entire plugin. Remember after changing this slug you need to flush permalinks. Just go to Settings->Permalinks hit the Save Settings button', 'bus-ticket-booking-with-seat-reservation'); ?>
							</i>
						</div>
					</div>
				</div>
				<?php
			}
			public function setup_content_done() {
				?>
				<div data-tabs-next="#wbtm_qs_done">
					<h2><?php esc_html_e('Finalize Setup', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('You are about to Finish & Save Bus Booking with Seat Reservation For Woocommerce Plugin setup process', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<div class="mT allCenter">
						<button type="submit" name="finish_quick_setup" class="_themeButton_dBR"><?php esc_html_e('Finish & Save', 'bus-ticket-booking-with-seat-reservation'); ?></button>
					</div>
				</div>
				<?php
			}
		}
		new WBTM_Quick_Setup();
	}