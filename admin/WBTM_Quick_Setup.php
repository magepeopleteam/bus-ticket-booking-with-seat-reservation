<?php
	if ( ! defined( 'ABSPATH' ) ) {
        die;
    } // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Quick_Setup' ) ) {
        class WBTM_Quick_Setup {
            public function __construct() {
                //if ( ! class_exists( 'TTBM_Dependencies' ) ) {
                    add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 10, 1 );
                //}
                add_action( 'admin_menu', array( $this, 'quick_setup_menu' ) );
                add_action( 'wbtm_quick_setup_content_start', array( $this, 'setup_welcome_content' ) );
                add_action( 'wbtm_quick_setup_content_general', array( $this, 'setup_general_content' ) );
                add_action( 'wbtm_quick_setup_content_done', array( $this, 'setup_content_done' ) );
            }
            public function add_admin_scripts() {
                wp_enqueue_script('mp_admin_settingshh', WBTM_PLUGIN_URL . '/admin/assets/js/mp_admin_settings.js', array('jquery'), time(), true);
                wp_enqueue_style('mp_admin_settingsrtt', WBTM_PLUGIN_URL . '/admin/assets/css/mp_admin_settings.css', array(), time());
                wp_enqueue_script('wbtm admin script', WBTM_PLUGIN_URL . '/admin/assets/js/wbtm_admin_script.js', array('jquery'), time(), true);
                wp_enqueue_style('wbtm admin style', WBTM_PLUGIN_URL . '/admin/assets/css/wbtm_admin_style.css', array(), time());
                wp_enqueue_style('mp_font_awesomettt', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.3/css/all.min.css', array(), '5.15.3');
            }
            public function quick_setup_menu() {
                $status = Wbtm_Woocommerce_bus::check_woocommerce();
                //echo $status;
                if ( $status === 'yes' ) {
                    //echo $status;exit();
                    add_submenu_page( 'edit.php?post_type=wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd10">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                    add_submenu_page( 'wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd10">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                } else {
                    add_menu_page( esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ), esc_html__( 'Bus', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', 'wbtm_bus', array( $this, 'quick_setup' ), 'dashicons-slides', 6 );
                    add_submenu_page( 'wbtm_bus', esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ), '<span style="color:#10dd17">' . esc_html__( 'Quick Setup', 'bus-ticket-booking-with-seat-reservation' ) . '</span>', 'manage_options', 'wbtm_quick_setup', array( $this, 'quick_setup' ) );
                }
            }
            public function quick_setup() {
                $mep_settings_tab   = array();
                $mep_settings_tab[] = array(
                    'id'       => 'start',
                    'title'    => '<i class="far fa-thumbs-up"></i>' . esc_html__( 'Welcome', 'bus-ticket-booking-with-seat-reservation' ),
                    'priority' => 1,
                    'active'   => true,
                );
                $mep_settings_tab[] = array(
                    'id'       => 'general',
                    'title'    => '<i class="fas fa-list-ul"></i>' . esc_html__( 'General', 'bus-ticket-booking-with-seat-reservation' ),
                    'priority' => 2,
                    'active'   => false,
                );
                $mep_settings_tab[] = array(
                    'id'       => 'done',
                    'title'    => '<i class="fas fa-pencil-alt"></i>' . esc_html__( 'Done', 'bus-ticket-booking-with-seat-reservation' ),
                    'priority' => 4,
                    'active'   => false,
                );
                $mep_settings_tab   = apply_filters( 'qa_welcome_tabs', $mep_settings_tab );
                $tabs_sorted        = array();
                foreach ( $mep_settings_tab as $page_key => $tab ) {
                    $tabs_sorted[ $page_key ] = $tab['priority'] ?? 0;
                }
                array_multisort( $tabs_sorted, SORT_ASC, $mep_settings_tab );
               // echo '<pre>';print_r($_POST);echo '</pre>';exit();
                if ( isset( $_POST['active_woo_btn'] ) ) {
                    activate_plugin( 'woocommerce/woocommerce.php' );
                    ?>
                    <script>
                        let ttbm_admin_location = window.location.href;
                        ttbm_admin_location = ttbm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
                        window.location.href = ttbm_admin_location;
                    </script>
                    <?php
                }
                if ( isset( $_POST['install_and_active_woo_btn'] ) ) {
                    echo '<div style="display:none">';
                    include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); //for plugins_api..
                    $plugin = 'woocommerce';
                    $api    = plugins_api( 'plugin_information', array(
                        'slug'   => $plugin,
                        'fields' => array(
                            'short_description' => false,
                            'sections'          => false,
                            'requires'          => false,
                            'rating'            => false,
                            'ratings'           => false,
                            'downloaded'        => false,
                            'last_updated'      => false,
                            'added'             => false,
                            'tags'              => false,
                            'compatibility'     => false,
                            'homepage'          => false,
                            'donate_link'       => false,
                        ),
                    ) );
                    //includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
                    include_once( ABSPATH . 'wp-admin/includes/file.php' );
                    include_once( ABSPATH . 'wp-admin/includes/misc.php' );
                    include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
                    $woocommerce_plugin = new Plugin_Upgrader( new Plugin_Installer_Skin( compact( 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
                    $woocommerce_plugin->install( $api->download_link );
                    activate_plugin( 'woocommerce/woocommerce.php' );
                    echo '</div>';
                    ?>
                    <script>
                        let ttbm_admin_location = window.location.href;
                        ttbm_admin_location = ttbm_admin_location.replace('admin.php?page=wbtm_bus', 'edit.php?post_type=wbtm_bus&page=wbtm_quick_setup');
                        window.location.href = ttbm_admin_location;
                    </script>
                    <?php
                }
                if ( isset( $_POST['finish_quick_setup'] ) ) {
                    $bus_menu_label                = isset( $_POST['bus_menu_label'] ) ? sanitize_text_field( $_POST['bus_menu_label'] ) : 'Bus';
                    $bus_menu_slug              = isset( $_POST['bus_menu_slug'] ) ? sanitize_text_field( $_POST['bus_menu_slug'] ) : 'Bus';

                    $general_settings_data       = get_option( 'wbtm_bus_settings' );
                    $update_general_settings_arr = [
                        'bus_menu_label' => $bus_menu_label,
                        'bus_menu_slug' => $bus_menu_slug,
                    ];
                    $new_general_settings_data   = is_array( $general_settings_data ) ? array_replace( $general_settings_data, $update_general_settings_arr ) : $update_general_settings_arr;

                    update_option( 'wbtm_bus_settings', $new_general_settings_data );
                    flush_rewrite_rules();
                    wp_redirect( admin_url( 'edit.php?post_type=wbtm_bus' ) );
                }
                ?>
                <div id="wbtm_quick_setup" class="wrap">
                    <div id="icon-tools" class="icon32"><br></div>
                    <h2></h2>
                    <form method="post" action="">
                        <input type="hidden" name="qa_hidden" value="Y">
                        <div class="welcome-tabs">
                            <ul class="tab-navs">
                                <?php
                                foreach ( $mep_settings_tab as $tab ) {
                                    $id     = $tab['id'];
                                    $title  = $tab['title'];
                                    $active = $tab['active'];
                                    $hidden = $tab['hidden'] ?? false;
                                    ?>
                                    <li class="tab-nav <?php echo $active ? 'active' : ''; ?> <?php echo $hidden ? 'hidden' : ''; ?> " data-id="<?php echo esc_html( $id ); ?>"><?php echo $title; ?></li>
                                <?php } ?>
                            </ul>
                            <?php
                            foreach ( $mep_settings_tab as $tab ) {
                                $id     = $tab['id'];
                                $active = $tab['active'];
                                ?>
                                <div class="tab-content <?php echo $active ? 'active' : ''; ?>" id="<?php echo esc_html( $id ); ?>">
                                    <?php do_action( 'wbtm_quick_setup_content_' . $id ); ?>
                                    <?php do_action( 'wbtm_quick_setup_content_after', $tab ); ?>
                                </div>
                            <?php } ?>
                            <div class="next-prev">
                                <div class="prev"><span>&longleftarrow;<?php esc_html_e( 'Previous', 'bus-ticket-booking-with-seat-reservation' ); ?></span></div>
                                <div class="next"><span><?php esc_html_e( 'Next', 'bus-ticket-booking-with-seat-reservation' ); ?>&longrightarrow;</span></div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php
            }
            public function setup_welcome_content() {
                $status = Wbtm_Woocommerce_bus::check_woocommerce();
                ?>
                <h2><?php esc_html_e( 'Bus Ticket Booking For Woocommerce Plugin', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
                <p><?php esc_html_e( 'Thanks for choosing Bus Ticket Booking Plugin for WooCommerce for your site, Please go step by step and choose some options to get started.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                <table class="wc_status_table widefat" id="status">
                    <tr>
                        <td data-export-label="WC Version">
                            <?php if ( $status === 'yes' ) {
                                esc_html_e( 'Woocommerce already installed and activated', 'bus-ticket-booking-with-seat-reservation' );
                            } elseif ( $status === 'no' ) {
                                esc_html_e( 'Woocommerce already install , please activate it', 'bus-ticket-booking-with-seat-reservation' );
                            } else {
                                esc_html_e( 'Woocommerce need to install and active', 'bus-ticket-booking-with-seat-reservation' );
                            } ?>
                        </td>
                        <td class="help"><span class="woocommerce-help-tip"></span></td>
                        <td class="woo_btn_td">
                            <?php if ( $status === 'yes' ) { ?>
                                <span class="fas fa-check-circle"></span>
                            <?php } elseif ( $status === 'no' ) { ?>
                                <button class="button" type="submit" name="active_woo_btn"><?php esc_html_e( 'Active Now', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
                            <?php } else { ?>
                                <button class="button" type="submit" name="install_and_active_woo_btn"><?php esc_html_e( 'Install & Active Now', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
                <?php
            }
            public function setup_general_content() {
                $general_data = get_option( 'wbtm_bus_settings' );
                $label        = $general_data['bus_menu_label'] ?? 'Bus';
                $slug         = $general_data['bus_menu_slug'] ?? 'Bus';
                ?>
                <div class="section">
                    <h2><?php esc_html_e( 'General settings', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
                    <p class="description section-description"><?php echo __( 'Choose some general option.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                    <table class="wc_status_table widefat" id="status">
                        <tr>
                            <td><?php esc_html_e( 'Bus Label:', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
                            <td>
                                <label><input type="text" name="bus_menu_label" value='<?php echo esc_html( $label ); ?>'/></label>
                                <p class="info"><?php esc_html_e( 'It will change the Bus post type label on the entire plugin.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Bus Slug:', 'bus-ticket-booking-with-seat-reservation' ); ?></td>
                            <td>
                                <label><input type="text" name="bus_menu_slug" value='<?php echo esc_html( $slug ); ?>'/></label>
                                <p class="info"><?php esc_html_e( 'It will change the Bus slug on the entire plugin. Remember after changing this slug you need to flush permalinks. Just go to Settings->Permalinks hit the Save Settings button', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php
            }
            public function setup_content_done() {
                ?>
                <div class="section">
                    <h2><?php esc_html_e( 'Finalize Setup', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
                    <p class="description section-description"><?php esc_html_e( 'You are about to Finish & Save Bus Ticket Booking Manager For Woocommerce Plugin setup process', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
                    <div class="setup_save_finish_area">
                        <button type="submit" name="finish_quick_setup" class="button setup_save_finish"><?php esc_html_e( 'Finish & Save', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
                    </div>
                </div>
                <?php
            }
        }
        new WBTM_Quick_Setup();
    }