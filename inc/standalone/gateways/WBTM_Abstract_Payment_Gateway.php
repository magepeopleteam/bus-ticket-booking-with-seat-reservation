<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access pages directly.

require_once __DIR__ . '/WBTM_Payment_Gateway_Interface.php';

if ( ! class_exists( 'WBTM_Abstract_Payment_Gateway' ) ) {
	abstract class WBTM_Abstract_Payment_Gateway implements WBTM_Payment_Gateway_Interface {

		const OPTION = 'wbtm_payment_settings';

		protected $id;
		protected $title;
		protected $settings;

		public function __construct() {
			$this->settings = get_option( self::OPTION, array() );
			$this->init_gateway();
		}

		/** Set $this->id and $this->title. */
		abstract protected function init_gateway();

		public function get_id() {
			return $this->id;
		}

		public function get_title() {
			return $this->title;
		}

		public function is_enabled() {
			return ( $this->get_setting( 'enable' ) === 'on' );
		}

		protected function get_setting( $key, $default = '' ) {
			$full_key = 'wbtm_' . $this->id . '_' . $key;
			return array_key_exists( $full_key, $this->settings ) && $this->settings[ $full_key ] !== ''
				? $this->settings[ $full_key ]
				: $default;
		}

		protected function is_sandbox() {
			return $this->get_setting( 'sandbox', 'off' ) === 'on';
		}
	}
}
