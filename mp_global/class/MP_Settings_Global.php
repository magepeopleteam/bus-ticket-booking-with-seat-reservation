<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MP_Settings_Global' ) ) {
		class MP_Settings_Global {
			public function __construct() {
				add_filter( 'mp_settings_sec_reg', array( $this, 'settings_sec_reg' ), 10, 1 );
				add_filter( 'mp_settings_sec_reg', array( $this, 'global_sec_reg' ), 90, 1 );
				add_filter( 'mp_settings_sec_fields', array( $this, 'settings_sec_fields' ), 10, 1 );
				add_action( 'wsa_form_bottom_mp_basic_license_settings', [ $this, 'license_settings' ], 5 );
				add_action( 'mp_basic_license_list', [ $this, 'licence_area' ] );
			}
			public function settings_sec_reg( $default_sec ): array {
				$sections = array(
					array(
						'id'    => 'mp_global_settings',
						'title' => esc_html__( 'Global Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
				);
				return array_merge( $default_sec, $sections );
			}
			public function global_sec_reg( $default_sec ): array {
				$sections = array(
					array(
						'id'    => 'mp_style_settings',
						'title' => esc_html__( 'Style Settings', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'mp_add_custom_css',
						'title' => esc_html__( 'Custom CSS', 'bus-ticket-booking-with-seat-reservation' )
					),
					array(
						'id'    => 'mp_basic_license_settings',
						'title' => esc_html__( 'Mage-People License', 'bus-ticket-booking-with-seat-reservation' )
					)
				);
				return array_merge( $default_sec, $sections );
			}
			public function settings_sec_fields( $default_fields ): array {
				$current_date    = current_time( 'Y-m-d' );
				$settings_fields = array(
					'mp_global_settings' => apply_filters( 'filter_mp_global_settings', array(
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
					'mp_style_settings'  => apply_filters( 'filter_mp_style_settings', array(
						array(
							'name'    => 'theme_color',
							'label'   => esc_html__( 'Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'desc'    => esc_html__( 'Select Default Theme Color', 'bus-ticket-booking-with-seat-reservation' ),
							'type'    => 'color',
							'default' => '#F12971'
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
					'mp_add_custom_css'  => apply_filters( 'filter_mp_add_custom_css', array(
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
                <div class="mp_basic_license_settings">
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
					<?php do_action( 'mp_license_page_plugin_list' ); ?>
                    </tbody>
                </table>
				<?php
			}
		}
		new MP_Settings_Global();
	}