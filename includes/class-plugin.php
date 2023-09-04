<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	/**
	 * @since      1.0.0
	 * @package    WBTM_Plugin
	 * @subpackage WBTM_Plugin/includes
	 * @author     MagePeople team <magepeopleteam@gmail.com>
	 */
	class Wbtm_Plugin {
		protected $loader;
		protected $plugin_name;
		protected $version;
		
		public function __construct() {
			$this->load_dependencies();
			add_action( 'init', array( $this, 'wbtm_add_endpoint' ), 10 );
		}
		
		private function load_dependencies() {
			$this->loader = new Wbtm_Plugin_Loader();
			// WBTM_UPGRADE::run_upgrade();
		}
		
		public function run() {
			$this->loader->run();
		}
		
		public function get_plugin_name() {
			return $this->plugin_name;
		}
		
		public function get_loader() {
			return $this->loader;
		}
		
		public function get_version() {
			return $this->version;
		}
		
		function wbtm_add_endpoint() {
			add_rewrite_endpoint( 'bus-panel', EP_ROOT | EP_PAGES );
		}
	}
