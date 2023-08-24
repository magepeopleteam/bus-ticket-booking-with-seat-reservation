<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Dependencies' ) ) {
		class WBTM_Dependencies {
			public function __construct() {
				add_action( 'init', array( $this, 'language_load' ) );
				$this->load_file();
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ), 90 );
				add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ), 90 );
				add_action( 'admin_head', array( $this, 'add_admin_head' ), 5 );
				add_action( 'wp_head', array( $this, 'add_frontend_head' ), 5 );
			}
			
			public function language_load(): void {
				$plugin_dir = basename( dirname( __DIR__ ) ) . "/languages/";
				load_plugin_textdomain( 'bus-ticket-booking-with-seat-reservation', false, $plugin_dir );
			}
			
			private function load_file(): void {
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Functions.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Admin.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/includes/class-plugin-loader.php';
				require_once WBTM_PLUGIN_DIR . '/includes/class-add-bus-info-to-cart.php';
				require_once WBTM_PLUGIN_DIR . '/includes/class-remove-bus-info-to-cart.php';
				require_once WBTM_PLUGIN_DIR . '/includes/class-upgrade.php';
				require_once WBTM_PLUGIN_DIR . '/includes/class-functions.php';
				require_once WBTM_PLUGIN_DIR . '/includes/class-permissions.php';
				require_once WBTM_PLUGIN_DIR . '/includes/my-account/bus-ticket.php';
				//=======================//
				require_once WBTM_PLUGIN_DIR . '/public/seat-template/seat_plan.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/get_form_data.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/title.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/bus-stops-list.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/dates.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/radio-button.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/button.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/search-form/footer.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/next-day-tabs.php';
				require_once WBTM_PLUGIN_DIR . '/public/class-plugin-public.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/layout/helper.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/layout/ajax.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/layout/bus-search-form.php';
				require_once WBTM_PLUGIN_DIR . '/public/template-hooks/layout/bus-search-page.php';
				//==================//
				
			}
			
			public function global_enqueue() {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-ui-core' );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				//wp_localize_script( 'jquery', 'mep_ajax', array( 'mep_ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
				wp_localize_script( 'wbtm_ajax_enq', 'wbtm_ajax', array( 'wbtm_ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
				wp_enqueue_script( 'wbtm_ajax_enq' );
				wp_enqueue_style( 'mp_jquery_ui', WBTM_PLUGIN_URL . '/assets/helper/jquery-ui.min.css', array(), '1.13.2' );
				wp_enqueue_style( 'mp_font_awesome', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css', array(), '5.15.4' );
				wp_enqueue_style( 'mp_select_2', WBTM_PLUGIN_URL . '/assets/helper/select_2/select2.min.css', array(), '4.0.13' );
				wp_enqueue_script( 'mp_select_2', WBTM_PLUGIN_URL . '/assets/helper/select_2/select2.min.js', array(), '4.0.13' );
				//wp_enqueue_style( 'mp_owl_carousel', WBTM_PLUGIN_URL . '/assets/helper/owl_carousel/owl.carousel.min.css', array(), '2.3.4' );
				//wp_enqueue_script( 'mp_owl_carousel', WBTM_PLUGIN_URL . '/assets/helper/owl_carousel/owl.carousel.min.js', array(), '2.3.4' );
				wp_enqueue_style( 'mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time() );
				wp_enqueue_script( 'mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array( 'jquery' ), time(), true );
				do_action( 'add_wbtm_common_script' );
			}
			
			public function admin_enqueue( $hook ) {
				global $post;
				$this->global_enqueue();
				wp_enqueue_editor();
				//admin script
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-codemirror' );
				wp_enqueue_script( 'wp-codemirror' );
				//wp_enqueue_script('jquery-ui-accordion');
				//********//
				//loading pick plugin
				wp_enqueue_style( 'mage-options-framework', WBTM_PLUGIN_URL . '/assets/helper/pick_plugin/mage-options-framework.css' );
				wp_enqueue_script( 'magepeople-options-framework', WBTM_PLUGIN_URL . '/assets/helper/pick_plugin/mage-options-framework.js', array( 'jquery' ) );
				wp_localize_script( 'PickpluginsOptionsFramework', 'PickpluginsOptionsFramework_ajax', array( 'PickpluginsOptionsFramework_ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
				wp_enqueue_script( 'form-field-dependency', WBTM_PLUGIN_URL . '/assets/helper/form-field-dependency.js', array( 'jquery' ), null, false );
				//loading Time picker
				wp_enqueue_style( 'jquery.timepicker.min', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css' );
				wp_enqueue_script( 'jquery.timepicker.min', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array( 'jquery' ), 1, true );
				// multi date picker
				wp_register_script( 'multidatepicker-wbtm', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-multidatespicker/1.6.6/jquery-ui.multidatespicker.js', array( 'jquery' ), 1, true );
				wp_enqueue_script( 'multidatepicker-wbtm' );
				// admin setting global
				wp_enqueue_script( 'mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array( 'jquery' ), time(), true );
				wp_enqueue_style( 'mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time() );
				// custom
				wp_enqueue_script( 'wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.js', array( 'jquery' ), time(), true );
				wp_enqueue_style( 'wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.css', array(), time() );
				do_action( 'add_mpwem_admin_script' );
			}
			
			public function frontend_enqueue() {
				$this->global_enqueue();
				wp_enqueue_style( 'mage_style', WBTM_PLUGIN_URL . '/assets/frontend/mage_style.css', array(), time() );
				wp_enqueue_style( 'wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.css', array(), time() );
				wp_enqueue_script( 'wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.js', array( 'jquery' ), time(), true );
				do_action( 'add_wbtm_frontend_script' );
			}
			
			public function add_admin_head() {
				$this->js_constant();
			}
			
			public function add_frontend_head() {
				$this->js_constant();
				$this->custom_css();
			}
			
			public function js_constant() {
				?>
				<script type="text/javascript">
					let mp_ajax_url = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
					let mp_currency_symbol = "<?php echo get_woocommerce_currency_symbol(); ?>";
					let mp_currency_position = "<?php echo get_option( 'woocommerce_currency_pos' ); ?>";
					let mp_currency_decimal = "<?php echo wc_get_price_decimal_separator(); ?>";
					let mp_currency_thousands_separator = "<?php echo wc_get_price_thousand_separator(); ?>";
					let mp_num_of_decimal = "<?php echo get_option( 'woocommerce_price_num_decimals', 2 ); ?>";
					let mp_empty_image_url = "<?php echo esc_attr( WBTM_PLUGIN_URL . '/assets/helper/images/no_image.png' ); ?>";
					let mp_date_format = "'D d M , yy'";
					let wbtm_ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
				</script>
				<?php
			}
			
			public function custom_css() {
				$custom_css         = MP_Global_Function::get_settings( 'mep_settings_custom_css', 'mep_custom_css' );
				$not_available_hide = MP_Global_Function::get_settings( 'general_setting_sec', 'mep_hide_not_available_event_from_list_page', 'no' );
				ob_start();
				?>
				<style>
					<?php echo $custom_css; ?>
					<?php  if($not_available_hide == 'yes'){ ?>
					.event-no-availabe-seat { display: none !important; }
					<?php } 	?>
				</style>
				<?php
				echo ob_get_clean();
			}
		}
		new WBTM_Dependencies();
	}
	