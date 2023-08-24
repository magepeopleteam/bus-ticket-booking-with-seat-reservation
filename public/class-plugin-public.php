<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	/**
	 * @package    WBTM_Plugin
	 * @subpackage WBTM_Plugin/public
	 * @author     MagePeople team <magepeopleteam@gmail.com>
	 */
	class WBTM_Plugin_Public {
		public function __construct() {
			add_filter( 'template_include', array( $this, 'WBTM_register_custom_tax_template' ) );
		}

		
		public function wbtm_template_part( $template_part ) {
			$template_name = $template_part . '.php';
			$template_path = 'wbtm-bus/';
			$default_path  = WBTM_PLUGIN_DIR . '/public/templates/';
			$template      = locate_template( array( $template_path . $template_name ) );
			if ( ! $template ) :
				$template = $default_path . $template_name;
			endif;
			
			return include( $template );
		}
		
		public function WBTM_register_custom_tax_template( $template ) {
			if ( is_tax( 'wbtm_video_cat' ) ) {
				$template = WBTM_PLUGIN_DIR . '/public/templates/taxonomy-category.php';
			}
			
			return $template;
		}
	}
	global $wbtmpublic;
	$wbtmpublic = new WBTM_Plugin_Public();