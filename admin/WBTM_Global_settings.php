<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Global_settings' ) ) {
		class WBTM_Global_settings {
			protected $settings_api;

			public function __construct() {
				$this->settings_api = new WBTM_Setting_API;
				add_action( 'admin_menu', array( $this, 'global_settings_menu' ) );
				add_action( 'admin_init', array( $this, 'admin_init' ) );
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'settings_sec_reg' ) );
				add_filter( 'wbtm_settings_sec_fields', array( $this, 'settings_sec_fields' ) );
				add_filter( 'wbtm_settings_sec_reg', array( $this, 'global_sec_reg' ), 90 );
				add_action( 'wsa_form_bottom_wbtm_license_settings', [ $this, 'license_settings' ], 5 );
				add_action( 'wbtm_basic_license_list', [ $this, 'licence_area' ] );
			}

			public function global_settings_menu() {
				$cpt = WBTM_Functions::get_cpt();
				add_submenu_page( 'edit.php?post_type=' . $cpt, esc_html__( ' Settings', 'bus-ticket-booking-with-seat-reservation' ), esc_html__( ' Settings', 'bus-ticket-booking-with-seat-reservation' ), 'manage_options', 'wbtm_settings_page', array( $this, 'settings_page' ) );
			}

			public function settings_page() {
				?>
                <div class="wbtm_style wbtm_global_settings">
                    <div class="mpPanel">
                        <div class="mpPanelHeader"><?php esc_html_e( ' Global Settings', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
                        <div class="mpPanelBody mp_zero">
                            <div class="wbtm_tabs leftTabs">
								<?php $this->settings_api->show_navigation(); ?>
                                <div class="tabsContent">
									<?php $this->settings_api->show_forms(); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}

			public function admin_init() {
				$this->settings_api->set_sections( $this->get_settings_sections() );
				$this->settings_api->set_fields( $this->get_settings_fields() );
				$this->settings_api->admin_init();
			}

			public function get_settings_sections() {
				$sections = array();

				return apply_filters( 'wbtm_settings_sec_reg', $sections );
			}

			public function get_settings_fields() {
				$settings_fields = array();

				return apply_filters( 'wbtm_settings_sec_fields', $settings_fields );
			}

			public function settings_sec_reg( $default_sec ): array {
				$label    = WBTM_Functions::get_name();
				$sections = array(
					array(
						'id'    => 'wbtm_general_settings',
						'title' => $label . ' ' . esc_html__( 'Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'wbtm_global_settings',
						'title' => esc_html__( 'Global Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
				);

				return array_merge( $default_sec, $sections );
			}

			public function global_sec_reg( $default_sec ): array {
				$sections = array(
					array(
						'id' => 'wbtm_slider_settings',
						'title' => esc_html__('Slider Settings', 'bus-ticket-booking-with-seat-reservation')
					),
					array(
						'id'    => 'wbtm_style_settings',
						'title' => esc_html__( 'Style Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'wbtm_custom_css',
						'title' => esc_html__( 'Custom CSS', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'wbtm_license_settings',
						'title' => esc_html__( 'Mage-People License', 'bus-ticket-booking-with-seat-reservation' )
					)
				);

				return array_merge( $default_sec, $sections );
			}

			public function settings_sec_fields( $default_fields ): array {
				$label           = WBTM_Functions::get_name();
				$current_date    = current_time( 'Y-m-d' );
				$settings_fields = array(
					'wbtm_general_settings' => apply_filters( 'wbtm_filter_general_settings', array(
						array(
							'name'    => 'set_book_status',
							'label'   => esc_html__( 'Seat Booked Status', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Please Select when and which order status Seat Will be Booked/Reduced.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'multicheck',
							'default' => array(
								'processing' => 'processing',
								'completed'  => 'completed'
							),
							'options' => array(
								'on-hold'    => esc_html__( 'On Hold', 'bus-ticket-booking-with-seat-reservation' ),
								'pending'    => esc_html__( 'Pending', 'bus-ticket-booking-with-seat-reservation' ),
								'processing' => esc_html__( 'Processing', 'bus-ticket-booking-with-seat-reservation' ),
								'completed'  => esc_html__( 'Completed', 'bus-ticket-booking-with-seat-reservation' ),
							)
						),
						array(
							'name'        => 'label',
							'label'       => $label . ' ' . esc_html__( 'Label', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'If you like to change the label in the dashboard menu, you can change it here.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'text',
							'default'     => 'Bus',
							'placeholder' => $label . ' ' . esc_html__( 'Label', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'        => 'slug',
							'label'       => $label . ' ' . esc_html__( 'Slug', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please enter the slug name you want. Remember, after changing this slug; you need to flush permalink; go to', 'bus-ticket-booking-with-seat-reservation' ) . '<strong>' . esc_html__( 'Settings-> Permalinks', 'bus-ticket-booking-with-seat-reservation' ) . '</strong> ' . esc_html__( 'hit the Save Settings button.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'text',
							'default'     => 'bus',
							'placeholder' => $label . ' ' . esc_html__( 'Slug', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'    => 'icon',
							'label'   => $label . ' ' . esc_html__( 'Icon', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to change the  icon in the dashboard menu, you can change it from here, and the Dashboard icon only supports the Dashicons, So please go to ', 'bus-ticket-booking-with-seat-reservation' ) . '<a href=https://developer.wordpress.org/resource/dashicons/#calendar-alt target=_blank>' . esc_html__( 'Dashicons Library.', 'bus-ticket-booking-with-seat-reservation' ) . '</a>' . esc_html__( 'and copy your icon code and paste it here.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'text',
							'default' => ''
						),
						array(
							'name'    => 'bus_return_show',
							'label'   => esc_html__( 'Show return Date Search', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Disable if you don\'t want to show return field in search. By default Enable', 'bus-ticket-booking-with-seat-reservation' ),
							'default' => 'enable',
							'type'    => 'select',
							'options' => array(
								'disable' => esc_html__( 'Disable', 'bus-ticket-booking-with-seat-reservation' ),
								'enable'  => esc_html__( 'Enable', 'bus-ticket-booking-with-seat-reservation' ),
							),
						),
						array(
							'name'        => 'ticket_sale_close_date',
							'label'       => esc_html__( 'Ticket sale off after date', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please select Seat sale off after date . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation' ),
							'default'     => '',
							'type'        => 'datepicker',
							'date_format' => 'dd-mm-yy',
							'placeholder' => current_time( 'Y-m-d' ),
						),
						array(
							'name'        => 'ticket_sale_max_date',
							'label'       => esc_html__( 'Maximum advanced day Sale', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please select Maximum advanced day Ticket Sale . if you dont want to off sale then it will be blank', 'bus-ticket-booking-with-seat-reservation' ),
							'default'     => '30',
							'type'        => 'number',
							'placeholder' => 30,
						),
						array(
							'name'        => 'bus_buffer_time',
							'label'       => esc_html__( 'Buffer Time', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'        => esc_html__( 'Please enter here car buffer time in minute. By default is 0', 'bus-ticket-booking-with-seat-reservation' ),
							'type'        => 'number',
							'default'     => 0,
							'placeholder' => esc_html__( 'Ex:50', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'name'    => 'show_hide_view_seats_button',
							'label'   => esc_html__( 'Show/hide view seats button', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to hide view seats button from search list, if registration off.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'show',
							'options' => array(
								'show' => esc_html__( 'Show', 'bus-ticket-booking-with-seat-reservation' ),
								'hide' => esc_html__( 'Hide', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'active_redirect_page',
							'label'   => esc_html__( 'Active Redirect page', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to Active Redirect page,please select on', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'search_page_redirect',
							'label'   => esc_html__( 'Search result page', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to redirect Search result page , please select below page', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'pages',
							'default' => WBTM_Global_Function::get_id_by_slug( 'search-result' ),
						),
						array(
							'name'    => 'make_processing_completed',
							'label'   => esc_html__( 'Turn order status processing to completed automatically', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to make woocommerce processing status to completed automatically select ON', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'checkout_redirect_after_booking',
							'label'   => esc_html__( 'Redirect to checkout after booking', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to redirect users directly to checkout after booking instead of showing the cart notice, select ON', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'cart_empty_after_search',
							'label'   => esc_html__( 'Empty cart after new search', 'bus-ticket-booking-with-seat-reservation' ),
                            'desc'  => esc_html__( 'Enable this option to automatically clear the cart whenever a user performs a new search, ensuring only the latest selection is added.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'off',
							'options' => array(
								'on'  => esc_html__( 'ON', 'bus-ticket-booking-with-seat-reservation' ),
								'off' => esc_html__( 'OFF', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
					) ),
					'wbtm_global_settings'  => apply_filters( 'wbtm_filter_global_settings', array(
						array(
							'name'    => 'disable_block_editor',
							'label'   => esc_html__( 'Disable Block/Gutenberg Editor', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to disable WordPress\'s new Block/Gutenberg editor, please select Yes.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'yes',
							'options' => array(
								'yes' => esc_html__( 'Yes', 'bus-ticket-booking-with-seat-reservation' ),
								'no'  => esc_html__( 'No', 'bus-ticket-booking-with-seat-reservation' )
							)
						),
						array(
							'name'    => 'date_format',
							'label'   => esc_html__( 'Date Picker Format', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to change Date Picker Format, please select format. Default  is D d M , yy.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'D d M , yy',
							'options' => array(
								'yy-mm-dd'   => $current_date,
								'yy/mm/dd'   => date_i18n( 'Y/m/d', strtotime( $current_date ) ),
								'yy-dd-mm'   => date_i18n( 'Y-d-m', strtotime( $current_date ) ),
								'yy/dd/mm'   => date_i18n( 'Y/d/m', strtotime( $current_date ) ),
								'dd-mm-yy'   => date_i18n( 'd-m-Y', strtotime( $current_date ) ),
								'dd/mm/yy'   => date_i18n( 'd/m/Y', strtotime( $current_date ) ),
								'mm-dd-yy'   => date_i18n( 'm-d-Y', strtotime( $current_date ) ),
								'mm/dd/yy'   => date_i18n( 'm/d/Y', strtotime( $current_date ) ),
								'd M , yy'   => date_i18n( 'j M , Y', strtotime( $current_date ) ),
								'D d M , yy' => date_i18n( 'D j M , Y', strtotime( $current_date ) ),
								'M d , yy'   => date_i18n( 'M  j, Y', strtotime( $current_date ) ),
								'D M d , yy' => date_i18n( 'D M  j, Y', strtotime( $current_date ) ),
							)
						),
						array(
							'name'    => 'date_format_short',
							'label'   => esc_html__( 'Short Date  Format', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'If you want to change Short Date  Format, please select format. Default  is M , Y.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'select',
							'default' => 'M , Y',
							'options' => array(
								'D , M d' => date_i18n( 'D , M d', strtotime( $current_date ) ),
								'M , Y'   => date_i18n( 'M , Y', strtotime( $current_date ) ),
								'M , y'   => date_i18n( 'M , y', strtotime( $current_date ) ),
								'M - Y'   => date_i18n( 'M - Y', strtotime( $current_date ) ),
								'M - y'   => date_i18n( 'M - y', strtotime( $current_date ) ),
								'F , Y'   => date_i18n( 'F , Y', strtotime( $current_date ) ),
								'F , y'   => date_i18n( 'F , y', strtotime( $current_date ) ),
								'F - Y'   => date_i18n( 'F - y', strtotime( $current_date ) ),
								'F - y'   => date_i18n( 'F - y', strtotime( $current_date ) ),
								'm - Y'   => date_i18n( 'm - Y', strtotime( $current_date ) ),
								'm - y'   => date_i18n( 'm - y', strtotime( $current_date ) ),
								'm , Y'   => date_i18n( 'm , Y', strtotime( $current_date ) ),
								'm , y'   => date_i18n( 'm , y', strtotime( $current_date ) ),
								'F'       => date_i18n( 'F', strtotime( $current_date ) ),
								'm'       => date_i18n( 'm', strtotime( $current_date ) ),
								'M'       => date_i18n( 'M', strtotime( $current_date ) ),
							)
						),
					) ),
					'wbtm_slider_settings' => array(
						array(
							'name' => 'slider_type',
							'label' => esc_html__('Slider Type', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Type Default Slider', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'slider',
							'options' => array(
								'slider' => esc_html__('Slider', 'bus-ticket-booking-with-seat-reservation'),
								'single_image' => esc_html__('Post Thumbnail', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'slider_style',
							'label' => esc_html__('Slider Style', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Style Default Style One', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'style_1',
							'options' => array(
								'style_1' => esc_html__('Style One', 'bus-ticket-booking-with-seat-reservation'),
								'style_2' => esc_html__('Style Two', 'bus-ticket-booking-with-seat-reservation'),
							)
						),
						array(
							'name' => 'indicator_visible',
							'label' => esc_html__('Slider Indicator Visible?', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Indicator Visible or Not? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'indicator_type',
							'label' => esc_html__('Slider Indicator Type', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Indicator Type Default Icon', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'icon',
							'options' => array(
								'icon' => esc_html__('Icon Indicator', 'bus-ticket-booking-with-seat-reservation'),
								'image' => esc_html__('image Indicator', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'showcase_visible',
							'label' => esc_html__('Slider Showcase Visible?', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Showcase Visible or Not? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'showcase_position',
							'label' => esc_html__('Slider Showcase Position', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Showcase Position Default Right', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'right',
							'options' => array(
								'top' => esc_html__('At Top Position', 'bus-ticket-booking-with-seat-reservation'),
								'right' => esc_html__('At Right Position', 'bus-ticket-booking-with-seat-reservation'),
								'bottom' => esc_html__('At Bottom Position', 'bus-ticket-booking-with-seat-reservation'),
								'left' => esc_html__('At Left Position', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'popup_image_indicator',
							'label' => esc_html__('Slider Popup Image Indicator', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Popup Indicator Image ON or Off? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						),
						array(
							'name' => 'popup_icon_indicator',
							'label' => esc_html__('Slider Popup Icon Indicator', 'bus-ticket-booking-with-seat-reservation'),
							'desc' => esc_html__('Please Select Slider Popup Indicator Icon ON or Off? Default ON', 'bus-ticket-booking-with-seat-reservation'),
							'type' => 'select',
							'default' => 'on',
							'options' => array(
								'on' => esc_html__('ON', 'bus-ticket-booking-with-seat-reservation'),
								'off' => esc_html__('Off', 'bus-ticket-booking-with-seat-reservation')
							)
						)
					),
					'wbtm_style_settings'   => apply_filters( 'wbtm_filter_style_settings', array(
						array(
							'name'    => 'theme_color',
							'label'   => esc_html__( 'Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#ff4500'
						),
						array(
							'name'    => 'theme_alternate_color',
							'label'   => esc_html__( 'Theme Alternate Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Theme Alternate  Color that means, if background theme color then it will be text color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#fff'
						),
						array(
							'name'    => 'default_text_color',
							'label'   => esc_html__( 'Default Text Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Text  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#303030'
						),
						array(
							'name'    => 'default_font_size',
							'label'   => esc_html__( 'Default Font Size', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Default Font Size(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '15'
						),
						array(
							'name'    => 'font_size_h1',
							'label'   => esc_html__( 'Font Size h1 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Main Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '35'
						),
						array(
							'name'    => 'font_size_h2',
							'label'   => esc_html__( 'Font Size h2 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h2 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '25'
						),
						array(
							'name'    => 'font_size_h3',
							'label'   => esc_html__( 'Font Size h3 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h3 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '22'
						),
						array(
							'name'    => 'font_size_h4',
							'label'   => esc_html__( 'Font Size h4 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h4 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '20'
						),
						array(
							'name'    => 'font_size_h5',
							'label'   => esc_html__( 'Font Size h5 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h5 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'font_size_h6',
							'label'   => esc_html__( 'Font Size h6 Title', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size h6 Title(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '16'
						),
						array(
							'name'    => 'button_font_size',
							'label'   => esc_html__( 'Button Font Size ', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Button(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'button_color',
							'label'   => esc_html__( 'Button Text Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Button Text  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#FFF'
						),
						array(
							'name'    => 'button_bg',
							'label'   => esc_html__( 'Button Background Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Button Background  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#222'
						),
						array(
							'name'    => 'font_size_label',
							'label'   => esc_html__( 'Label Font Size ', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Type Font Size Label(in PX Unit).', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'number',
							'default' => '18'
						),
						array(
							'name'    => 'warning_color',
							'label'   => esc_html__( 'Warning Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Warning  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#E67C30'
						),
						array(
							'name'    => 'section_bg',
							'label'   => esc_html__( 'Section Background color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Background  Color.', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#FAFCFE'
						),
					) ),
					'wbtm_custom_css'       => apply_filters( 'wbtm_filter_custom_css', array(
						array(
							'name'  => 'custom_css',
							'label' => esc_html__( 'Custom CSS', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'  => esc_html__( 'Write Your Custom CSS Code Here', 'bus-ticket-booking-with-seat-reservation' ),
							'type'  => 'textarea',
						)
					) )
				);

				return array_merge( $default_fields, $settings_fields );
			}

			public function license_settings() {
				?>
                <div class="wbtm_license_settings">
                    <h3><?php esc_html_e( 'Mage-People License', 'bus-ticket-booking-with-seat-reservation' ); ?></h3>
                    <div class="_dFlex">
                        <span class="fas fa-info-circle _mR_xs"></span>
                        <i><?php esc_html_e( 'Thanking you for using our Mage-People plugin. Our some plugin  free and no license is required. We have some Additional addon to enhance feature of this plugin functionality. If you have any addon you need to enter a valid license for that plugin below.', 'bus-ticket-booking-with-seat-reservation' ); ?>                    </i>
                    </div>
                    <div class="divider"></div>
                    <div class="dLayout mp_basic_license_area">
						<?php $this->licence_area(); ?>
                    </div>
                </div>
				<?php
			}

			public function licence_area() {
				?>
                <table>
                    <thead>
                    <tr>
                        <th colspan="4"><?php esc_html_e( 'Plugin Name', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Order No', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="2"><?php esc_html_e( 'Expire on', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="3"><?php esc_html_e( 'License Key', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                        <th colspan="2"><?php esc_html_e( 'Action', 'bus-ticket-booking-with-seat-reservation' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
					<?php do_action( 'wbtm_license_page_plugin_list' ); ?>
                    </tbody>
                </table>
				<?php
			}
		}
		new  WBTM_Global_settings();
	}
