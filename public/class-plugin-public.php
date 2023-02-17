<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 * @package    WBTM_Plugin
 * @subpackage WBTM_Plugin/public
 * @author     MagePeople team <magepeopleteam@gmail.com>
 */
class WBTM_Plugin_Public {

	private $plugin_name;

	private $version;

	public function __construct() {
		$this->load_public_dependencies();
		add_action( 'wp_enqueue_scripts', array($this,'enqueue_styles'));
		add_action( 'wp_enqueue_scripts', array($this,'enqueue_scripts'));
		//add_filter('single_template', array($this,'WBTM_register_custom_single_template'), 10);
		add_filter('template_include', array($this,'WBTM_register_custom_tax_template'));
	}

	private function load_public_dependencies() {
		require_once WBTM_PLUGIN_DIR . 'public/shortcode/shortcode.php';
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'bus-jquery-ui-css', WBTM_PLUGIN_URL . 'public/css/jquery-ui.css', array(), '', 'all' );
		wp_enqueue_style ('wbtm-select2-style-cdn',"https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css",null,1); 
		wp_enqueue_style('wbtm-font-awesome-css-cdn-5.2.0', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.2.0/css/all.min.css", null, 1);
		wp_enqueue_style('wbtm-font-awesome-css-cdn', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css", null, 1);		
		wp_enqueue_style( 'bus-public-css', WBTM_PLUGIN_URL . 'public/css/style.css', array(), time(), 'all' );
		wp_enqueue_style( 'bus-default_style', WBTM_PLUGIN_URL . 'public/css/mage_style.css', array(), time(), 'all' );

	}


	public function enqueue_scripts() {
        wp_enqueue_script('jquery', '', array(), false, true);
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script ('wbtm-select2-style-cdn',"https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js",null,1);
		wp_enqueue_script( 'bus-public-js', WBTM_PLUGIN_URL . 'public/js/bus_script.js', array( 'jquery' ), time(), true );
        wp_enqueue_script( 'wbtm-bus-public-js', WBTM_PLUGIN_URL . 'public/js/wbtm_bus_script.js', array( 'jquery' ), time(), true );
		wp_localize_script( 'bus-public-js', 'php_vars', array('currency_symbol' => get_woocommerce_currency_symbol()) );

	}


	public function WBTM_register_custom_single_template($template) {
		global $post;
		if ($post->post_type == "wbtm_bus"){
			$template_name = 'single-bus.php';
			$template_path = 'bus-ticket-booking-with-seat-reservation/';
			$default_path = WBTM_PLUGIN_DIR. 'public/templates/';
			
			$bus_type = get_post_meta($post->ID, 'wbtm_seat_type_conf', true);
			if($bus_type === 'wbtm_seat_subscription') {
				if(is_plugin_active('addon-bus-ticket-subscription/plugin.php')) {
					$template_path = WP_PLUGIN_DIR. '/addon-bus-ticket-subscription/inc/';
					$default_path = WP_PLUGIN_DIR. '/addon-bus-ticket-subscription/inc/';
				} else {
					$template_name = 'template-not-found.php';
				}
			}

			if($bus_type === 'wbtm_seat_private') {
				if(is_plugin_active('addon-bus-ticket-private/plugin.php')) {
					$template_path = WP_PLUGIN_DIR. '/addon-bus-ticket-private/inc/';
					$default_path = WP_PLUGIN_DIR. '/addon-bus-ticket-private/inc/';
				} else {
					$template_name = 'template-not-found.php';
				}
			}

			$template = locate_template( array($template_path . $template_name) );

			if ( ! $template ) :
				$template = $default_path . $template_name;
			endif;
			return $template;
		}
		return $template;
	}


	public function wbtm_template_part($template_part) {		 
				  $template_name = $template_part.'.php';				  
		          $template_path = 'wbtm-bus/';
		          $default_path = WBTM_PLUGIN_DIR. 'public/templates/'; 
		          $template = locate_template( array($template_path . $template_name) );
		        if ( ! $template ) :
		          $template = $default_path . $template_name;
		        endif;		   		  
		return include($template);
	}


	public function WBTM_register_custom_tax_template( $template ){
	    if( is_tax('wbtm_video_cat')){
	        $template = WBTM_PLUGIN_DIR.'public/templates/taxonomy-category.php';
	    }    
	    return $template;
	}

}
global $wbtmpublic;
$wbtmpublic = new WBTM_Plugin_Public();