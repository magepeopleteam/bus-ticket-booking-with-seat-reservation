<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
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
		add_action('init', array($this, 'wbtm_add_endpoint'), 10);
	}

	private function load_dependencies() {
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-wc-product-data.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-form-fields-generator.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-form-fields-wrapper.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-meta-box.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-taxonomy-edit.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-theme-page.php';
		require_once WBTM_PLUGIN_DIR . 'lib/classes/class-menu-page.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-plugin-loader.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-add-bus-info-to-cart.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-remove-bus-info-to-cart.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-upgrade.php';
		require_once WBTM_PLUGIN_DIR . 'public/seat-template/seat_plan.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-functions.php';
		require_once WBTM_PLUGIN_DIR . 'includes/class-permissions.php';
		require_once WBTM_PLUGIN_DIR . 'admin/class-plugin-admin.php';
		require_once WBTM_PLUGIN_DIR . 'public/template-hooks/templating.php';
		require_once WBTM_PLUGIN_DIR . 'public/class-plugin-public.php';
		require_once WBTM_PLUGIN_DIR . 'includes/my-account/bus-ticket.php';	
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

		add_rewrite_endpoint( 'bus-panel',  EP_ROOT | EP_PAGES );

	}

}
