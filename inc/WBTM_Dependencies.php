<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Dependencies')) {
		class WBTM_Dependencies {
			public function __construct() {
				add_action('init', array($this, 'language_load'));
				$this->load_file();
				add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'), 90);
				add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue'), 90);
				add_action('admin_head', array($this, 'add_admin_head'), 5);
				add_action('wp_head', array($this, 'add_frontend_head'), 5);
				add_filter('single_template', array($this, 'load_single_template'), 10);
				add_filter( 'template_include', array( $this, 'load_template' ) );
			}
			public function language_load(): void {
				$plugin_dir = basename(dirname(__DIR__)) . "/languages/";
				load_plugin_textdomain('bus-ticket-booking-with-seat-reservation', false, $plugin_dir);
			}
			private function load_file(): void {
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Functions.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Translations.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Query.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Layout.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Admin.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Shortcodes.php';
				require_once WBTM_PLUGIN_DIR . '/inc/WBTM_Woocommerce.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/inc/class-functions.php';
				//==================//
			}
			public function global_enqueue() {
				wp_enqueue_script('jquery');
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('jquery-ui-datepicker');
				wp_enqueue_style('mp_jquery_ui', WBTM_PLUGIN_URL . '/assets/helper/jquery-ui.min.css', array(), '1.13.2');
				wp_enqueue_style('mp_font_awesome', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css', array(), '5.15.4');
				wp_enqueue_style('mp_select_2', WBTM_PLUGIN_URL . '/assets/helper/select_2/select2.min.css', array(), '4.0.13');
				wp_enqueue_script('mp_select_2', WBTM_PLUGIN_URL . '/assets/helper/select_2/select2.min.js', array(), '4.0.13');
				wp_enqueue_style('mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time());
				wp_enqueue_script('mp_plugin_global', WBTM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array('jquery'), time(), true);
				wp_enqueue_style('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.css', array(), time());
				wp_enqueue_script('wbtm_global', WBTM_PLUGIN_URL . '/assets/global/wbtm_global.js', array('jquery'), time(), true);
				do_action('add_wbtm_common_script');
			}
			public function admin_enqueue() {
				$this->global_enqueue();
				wp_enqueue_editor();
				//admin script
				wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_style('wp-color-picker');
				wp_enqueue_script('wp-color-picker');
				wp_enqueue_style('wp-codemirror');
				wp_enqueue_script('wp-codemirror');
				//wp_enqueue_script('jquery-ui-accordion');
				//********//
				wp_enqueue_script('form-field-dependency', WBTM_PLUGIN_URL . '/assets/helper/form-field-dependency.js', array('jquery'), null, false);
				//loading Time picker
				wp_enqueue_style('jquery.timepicker.min', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css');
				wp_enqueue_script('jquery.timepicker.min', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array('jquery'), 1, true);

				// admin setting global
				wp_enqueue_script('mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array('jquery'), time(), true);
				wp_enqueue_style('mp_admin_settings', WBTM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time());
				// custom
				wp_enqueue_script('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.js', array('jquery'), time(), true);
				wp_enqueue_style('wbtm_admin', WBTM_PLUGIN_URL . '/assets/admin/wbtm_admin.css', array(), time());
				do_action('add_wbtm_admin_script');
			}
			public function frontend_enqueue() {
				$this->global_enqueue();
				wp_enqueue_style('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.css', array(), time());
				wp_enqueue_script('wbtm', WBTM_PLUGIN_URL . '/assets/frontend/wbtm.js', array('jquery'), time(), true);
				do_action('add_wbtm_frontend_script');
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
					let mp_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
					let mp_currency_symbol = "<?php echo get_woocommerce_currency_symbol(); ?>";
					let mp_currency_position = "<?php echo get_option('woocommerce_currency_pos'); ?>";
					let mp_currency_decimal = "<?php echo wc_get_price_decimal_separator(); ?>";
					let mp_currency_thousands_separator = "<?php echo wc_get_price_thousand_separator(); ?>";
					let mp_num_of_decimal = "<?php echo get_option('woocommerce_price_num_decimals', 2); ?>";
					let mp_empty_image_url = "<?php echo esc_attr(WBTM_PLUGIN_URL . '/assets/helper/images/no_image.png'); ?>";
					let mp_date_format = "<?php echo esc_attr(MP_Global_Function::get_settings('wbtm_general_settings', 'date_format', 'D d M , yy')); ?>";
					let mp_date_format_without_year ="<?php echo esc_attr(MP_Global_Function::get_settings('wbtm_general_settings', 'date_format_without_year', 'D d M')); ?>";
				</script>
				<?php
			}
			public function custom_css() {
				$custom_css = MP_Global_Function::get_settings('wbtm_custom_css', 'custom_css');
				ob_start();
				?>
				<style>
					<?php echo $custom_css; ?>
				</style>
				<?php
				echo ob_get_clean();
			}
			public function load_single_template($template) {
				global $post;
				if ($post->post_type == "wbtm_bus") {
					 $template=WBTM_Functions::template_path('single_page/single-bus.php');
				}
				return $template;
			}
			public function load_template( $template ): string {
				if (get_query_var('bussearchlist')) {
					$template = WBTM_Functions::template_path( 'single_page/bus-search-list.php' );
				}
				return $template;
			}
		}
		new WBTM_Dependencies();
	}
	