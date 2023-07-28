<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	require_once WBTM_PLUGIN_DIR . 'admin/MP_Global_Function.php';
	require_once WBTM_PLUGIN_DIR . 'admin/MP_Global_Style.php';
	if (!class_exists('WBTM_Quick_Setup')) 
    {
		class WBTM_Quick_Setup 
        {
			public function __construct() 
            {
				//if ( ! class_exists( 'TTBM_Dependencies' ) ) {
                    add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 10, 1 );
                //}
				//echo "<pre>";print_r(WBTM_PLUGIN_URL);exit;
				add_action('admin_menu', array($this, 'quick_setup_menu'));
			}

			public function add_admin_scripts() 
            {
				wp_enqueue_style('mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time());
				wp_enqueue_script('mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array('jquery'), time(), true);
				wp_enqueue_style('mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time());
				wp_enqueue_script('mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array('jquery'), time(), true);
				wp_enqueue_style('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.css', array(), time());
				wp_enqueue_script('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.js', array('jquery'), time(), true);
				wp_enqueue_style('mp_font_awesome', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css', array(), '5.15.4');
			}

			public function quick_setup_menu() 
            {
				$status = Wbtm_Woocommerce_bus::check_woocommerce();
                if ( $status === 'yes' ) 
                {
                    add_submenu_page( 'edit.php?post_type=wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd10">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                    add_submenu_page( 'wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd10">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                } 
                else 
                {
                    add_menu_page( esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ), esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', 'wbtm_bus', array( $this, 'quick_setup' ), 'dashicons-slides', 6 );
                    add_submenu_page( 'wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd17">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                }
			}

			public function quick_setup() 
			{
				if (isset($_POST['active_woo_btn'])) 
				{
					?>
					<script>
						dLoaderBody();
					</script>
					<?php
					activate_plugin('woocommerce/woocommerce.php');
					?>
					<script>
						let wbtm_admin_location = window.location.href;
						wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
						window.location.href = wbtm_admin_location;
					</script>
					<?php
				}

				if (isset($_POST['install_and_active_woo_btn'])) 
				{
					echo '<div style="display:none">';
					include_once(ABSPATH . 'wp-admin/includes/plugin-install.php'); //for plugins_api..
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
					//includes necessary for Plugin_Upgrade and Plugin_Installer_Skin
					include_once(ABSPATH . 'wp-admin/includes/file.php');
					include_once(ABSPATH . 'wp-admin/includes/misc.php');
					include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
					$woocommerce_plugin = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('title', 'url', 'nonce', 'plugin', 'api')));
					$woocommerce_plugin->install($api->download_link);
					activate_plugin('woocommerce/woocommerce.php');
					echo '</div>';
					?>
					<script>
						let wbtm_admin_location = window.location.href;
						wbtm_admin_location = wbtm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
						window.location.href = wbtm_admin_location;
					</script>
					<?php
				}

				if (isset($_POST['finish_quick_setup'])) 
				{
					// $label = isset($_POST['bus_menu_label']) ? sanitize_text_field($_POST['bus_menu_label']) : 'bus-ticket-booking-with-seat-reservation';
					// $slug = isset($_POST['bus_menu_slug']) ? sanitize_text_field($_POST['bus_menu_slug']) : 'bus-ticket-booking-with-seat-reservation';
					// $general_settings_data = get_option('wbtm_general_settings');
					// $update_general_settings_arr = [
					// 	'label' => $label,
					// 	'slug' => $slug
					// ];
					// $new_general_settings_data = is_array($general_settings_data) ? array_replace($general_settings_data, $update_general_settings_arr) : $update_general_settings_arr;
					// update_option('wbtm_general_settings', $new_general_settings_data);
					// flush_rewrite_rules();
					// wp_redirect(admin_url('edit.php?post_type=wbtm_bus'));
					$wbtm_cpt_label                = isset( $_POST['bus_menu_label'] ) ? sanitize_text_field( $_POST['bus_menu_label'] ) : 'Bus';
                    $wbtm_cpt_slug              = isset( $_POST['bus_menu_slug'] ) ? sanitize_text_field( $_POST['bus_menu_slug'] ) : 'Bus';

                    $general_settings_data       = get_option( 'wbtm_bus_settings' );
                    $update_general_settings_arr = [
                        'bus_menu_label' => $wbtm_cpt_label,
                        'bus_menu_slug' => $wbtm_cpt_slug,
                    ];
                    $new_general_settings_data   = is_array( $general_settings_data ) ? array_replace( $general_settings_data, $update_general_settings_arr ) : $update_general_settings_arr;
                    update_option( 'wbtm_bus_settings', $new_general_settings_data );
                    update_option( 'wbtm_quick_setup_done','yes');
					flush_rewrite_rules();
                    wp_redirect( admin_url( 'edit.php?post_type=wbtm_bus' ) );
				}

				$next_disable = '';

				$status = MP_Global_Function::check_woocommerce();				
				if($status != 1)
				{
					$next_disable = 'disabled';
				}

				?>
				<div class="mpStyle">
					<div class=_dShadow_6_adminLayout">
						<form method="post" action="">
							<div class="mpTabsNext">
								<div class="tabListsNext _max_700_mAuto">
									<div data-tabs-target-next="#wbtm_qs_welcome" class="tabItemNext">
										<h4 class="circleIcon">1</h4>
										<h5 class="circleTitle"><?php esc_html_e('Welcome', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
									</div>
									<div data-tabs-target-next="#wbtm_qs_general" class="tabItemNext">
										<h4 class="circleIcon">2</h4>
										<h5 class="circleTitle"><?php esc_html_e('General', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
									</div>
									<div data-tabs-target-next="#wbtm_qs_done" class="tabItemNext">
										<h4 class="circleIcon">3</h4>
										<h5 class="circleTitle"><?php esc_html_e('Done', 'bus-ticket-booking-with-seat-reservation'); ?></h5>
									</div>
								</div>
								<div class="tabsContentNext _infoLayout_mT">
									<?php
										$this->setup_welcome_content();
										$this->setup_general_content();
										$this->setup_content_done();
									?>
								</div>
								<div class="justifyBetween">
									<button type="button" class="mpBtn nextTab_prev">
										<span>&longleftarrow;<?php esc_html_e('Previous', 'bus-ticket-booking-with-seat-reservation'); ?></span>
									</button>
									<div></div>

									<button type="button" class="themeButton nextTab_next" <?php echo $next_disable; ?>>
										<span><?php esc_html_e('Next', 'bus-ticket-booking-with-seat-reservation'); ?>&longrightarrow;</span>
									</button>
								</div>
							</div>
						</form>
					</div>
				</div>
				<?php
			}

			public function setup_welcome_content() 
            {
				$status = MP_Global_Function::check_woocommerce();
				?>
				<div data-tabs-next="#wbtm_qs_welcome">
					<h2><?php esc_html_e('Bus Booking Manager For Woocommerce Plugin', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('Bus Booking Manager Plugin for WooCommerce for your site, Please go step by step and choose some options to get started.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<div class="_dLayout_mT_alignCenter justifyBetween">
						<h5>
							<?php if ($status == 1) 
                            {
								esc_html_e('Woocommerce already installed and activated', 'bus-ticket-booking-with-seat-reservation');
							} 
                            elseif ($status == 0) 
                            {
								esc_html_e('Woocommerce need to install and active', 'bus-ticket-booking-with-seat-reservation');
							} 
                            else 
                            {
								esc_html_e('Woocommerce already install , please activate it', 'bus-ticket-booking-with-seat-reservation');
							} ?>
						</h5>
						<?php if ($status == 1) { ?>
							<h5><span class="fas fa-check-circle textSuccess"></span></h5>
						<?php } elseif ($status == 0) { ?>
							<button class="warningButton" type="submit"
								name="install_and_active_woo_btn"><?php esc_html_e('Install & Active Now', 'bus-ticket-booking-with-seat-reservation'); ?></button>
						<?php } else { ?>
							<button class="themeButton" type="submit"
								name="active_woo_btn"><?php esc_html_e('Active Now', 'bus-ticket-booking-with-seat-reservation'); ?></button>
						<?php } ?>
					</div>
				</div>
				<?php
			}

			public function setup_general_content() 
            {
				// $label = MP_Global_Function::get_settings('wbtm_general_settings', 'label', 'Bus Booking');
				// $slug = MP_Global_Function::get_settings('wbtm_general_settings', 'slug', 'bus-booking');
				$general_data = get_option( 'wbtm_bus_settings' );
                $label        = $general_data['bus_menu_label'] ?? 'Bus';
                $slug         = $general_data['bus_menu_slug'] ?? 'Bus';
				
				?>
				<div data-tabs-next="#wbtm_qs_general">
					<div class="section">
						<h2><?php esc_html_e('General settings', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
						<p class="mTB_xs"><?php esc_html_e('Choose some general option.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						<div class="_dLayout_mT">
							<label class="fullWidth">
                            <span
								class="min_300"><?php esc_html_e('Bus Booking Manager Label:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<input type="text" class="formControl" name="bus_menu_label"
									value='<?php echo esc_attr($label); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Bus Booking Manager post type label on the entire plugin.', 'bus-ticket-booking-with-seat-reservation'); ?>
							</i>
							<div class="divider"></div>
							<label class="fullWidth">
                            <span
								class="min_300"><?php esc_html_e('Bus Booking Manager Slug:', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								<input type="text" class="formControl" name="bus_menu_slug"
									value='<?php echo esc_attr($slug); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Bus Booking Manager slug on the entire plugin. Remember after changing this slug you need to flush permalinks. Just go to Settings->Permalinks hit the Save Settings button', 'bus-ticket-booking-with-seat-reservation'); ?>
							</i>
						</div>
					</div>
				</div>
				<?php
			}

			public function setup_content_done() 
            {
				?>
				<div data-tabs-next="#wbtm_qs_done">
					<h2><?php esc_html_e('Finalize Setup', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('You are about to Finish & Save Bus Booking Manager For Woocommerce Plugin setup process', 'bus-ticket-booking-with-seat-reservation'); ?></p>
					<div class="mT allCenter">
						<button type="submit" name="finish_quick_setup"
							class="themeButton"><?php esc_html_e('Finish & Save', 'bus-ticket-booking-with-seat-reservation'); ?></button>
					</div>
				</div>
				<?php
			}
		}
        
		new WBTM_Quick_Setup();
	}