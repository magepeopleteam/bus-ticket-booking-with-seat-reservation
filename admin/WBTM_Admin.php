<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'WBTM_Admin' ) ) {
		class WBTM_Admin {
			public function __construct() {
				$this->load_file();
				add_action( 'init', [ $this, 'add_dummy_data' ] );
				//add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
				add_action( 'upgrader_process_complete', [ $this, 'flush_rewrite' ], 0 );
			}
			
			public function flush_rewrite() {
				flush_rewrite_rules();
			}
			
			private function load_file(): void {
				
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-form-fields-generator.php';
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-form-fields-wrapper.php';
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-meta-box.php';
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-taxonomy-edit.php';
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-theme-page.php';
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-menu-page.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-setting-page.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_CPT.php';
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Taxonomy.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-meta-box.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-license.php';
				
				require_once WBTM_PLUGIN_DIR . '/lib/classes/class-wc-product-data.php';
				// require_once WBTM_PLUGIN_DIR . '/admin/class/class-menu-page.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-tax-meta.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-custom-css.php';
				require_once WBTM_PLUGIN_DIR . '/admin/class/class-welcome-page.php';
				//==================//
				require_once WBTM_PLUGIN_DIR . '/admin/WBTM_Dummy_Import.php';
				//==================//
				//==================//
			}
			
			public function add_dummy_data() {
				new WBTM_Dummy_Import();
			}
			
			//************Disable Gutenberg************************//
			public function disable_gutenberg( $current_status, $post_type ) {
				$user_status = MP_Global_Function::get_settings( 'general_setting_sec', 'mep_disable_block_editor', 'yes' );
				if ( $post_type === 'mep_events' && $user_status == 'yes' ) {
					return false;
				}
				
				return $current_status;
			}
		}
		new WBTM_Admin();
	}