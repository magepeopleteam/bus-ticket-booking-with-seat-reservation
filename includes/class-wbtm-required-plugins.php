<?php
/**
 *  Author: MagePeople Team
 *  Developer: Ariful
 * 	Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WBTM_Required_Plugins')) {

class WBTM_Required_Plugins
{
	public function __construct() {
		add_action('admin_notices',array($this,'wbtm_admin_notices'));
        add_action( 'admin_menu', array( $this, 'wbtm_plugins_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'wbtm_plugin_activate' ) );
	}

	public function wbtm_plugin_page_location(){

		$location = 'plugins.php';

		return $location;	
	}

	public function wbtm_plugins_admin_menu() {
			add_submenu_page(
				$this->wbtm_plugin_page_location(),
				__( 'Install WBTM Plugins', 'bus-ticket-booking-with-seat-reservation' ),
				__( 'Install WBTM Plugins', 'bus-ticket-booking-with-seat-reservation' ),
				'manage_options',
				'wbtm-plugins',
				array($this,'wbtm_plugin_page')
			);
    }

	public function wbtm_chk_plugin_folder_exist($slug){
		$plugin_dir = ABSPATH . 'wp-content/plugins/'.$slug;
		if(is_dir($plugin_dir)){
			return true;
		}
		else{
			return false;
		}		
	}

	public function wbtm_plugin_activate(){
		if(isset($_GET['wbtm_plugin_activate']) && !is_plugin_active( $_GET['wbtm_plugin_activate'] )){
			$slug = $_GET['wbtm_plugin_activate'];
			$activate = activate_plugin( $slug );
			$url = admin_url( $this->wbtm_plugin_page_location().'?page=wbtm-plugins' );
			echo '<script>
			var url = "'.$url.'";
			window.location.replace(url);
			</script>';
		}
		else{
			return false;
		}
	}

	public function wbtm_mpdf_plugin_install(){

		if(!current_user_can('administrator')) {
			exit;
		}

		if(isset($_GET['wbtm_plugin_install']) && $this->wbtm_chk_plugin_folder_exist($_GET['wbtm_plugin_install']) == false){
			$slug = $_GET['wbtm_plugin_install'];
			if($slug != 'magepeople-pdf-support-master'){
				$action = 'install-plugin';
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => $action,
							'plugin' => $slug
						),
						admin_url( 'update.php' )
					),
					$action.'_'.$slug
				);
				if(isset($url)){
					echo '<script>
						str = "'.$url.'";
						var url = str.replace(/&amp;/g, "&");
						window.location.replace(url);
						</script>';
				}


			}
			elseif($slug == 'magepeople-pdf-support-master'){

				include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
				include_once( ABSPATH . 'wp-admin/includes/file.php' );
				include_once( ABSPATH . 'wp-admin/includes/misc.php' );
				include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
				$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin() );
				$upgrader->install('https://github.com/magepeopleteam/magepeople-pdf-support/archive/master.zip');
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}	

	public function wbtm_wp_plugin_activation_url($slug){
		if($this->wbtm_plugin_page_location() == 'plugins.php'){
			$url = admin_url($this->wbtm_plugin_page_location()).'?page=wbtm-plugins&wbtm_plugin_activate='.$slug;
		}
		else{
			$url = admin_url($this->wbtm_plugin_page_location()).'&page=wbtm-plugins&wbtm_plugin_activate='.$slug;
		}

		return $url;
	}

	public function wbtm_plugin_page(){
		$pdflibrary = 'mpdf';	
		$button_wc = '';
		$button_wbtm = '';
		$button_mpdf = '';

		/* WooCommerce */
		if($this->wbtm_chk_plugin_folder_exist('woocommerce') == false) {;
			$button_wc = '<a href="'.esc_url($this->wbtm_wp_plugin_installation_url('woocommerce')).'" class="wbtm_plugin_btn">'.esc_html__('Install','bus-ticket-booking-with-seat-reservation').'</a>';
		}
		elseif($this->wbtm_chk_plugin_folder_exist('woocommerce') == true && !is_plugin_active( 'woocommerce/woocommerce.php')){
			$button_wc = '<a href="'.esc_url($this->wbtm_wp_plugin_activation_url('woocommerce/woocommerce.php')).'" class="wbtm_plugin_btn">'.esc_html__('Activate','bus-ticket-booking-with-seat-reservation').'</a>';
		}
		else{
			$button_wc = '<span class="wbtm_plugin_status">'.esc_html__('Activated','bus-ticket-booking-with-seat-reservation').'</span>';
		}

		/* Bus Ticket Booking Manager */
		if($this->wbtm_chk_plugin_folder_exist('bus-ticket-booking-with-seat-reservation') == false) {;
			$button_wbtm = '<a href="'.esc_url($this->wbtm_wp_plugin_installation_url('bus-ticket-booking-with-seat-reservation')).'" class="wbtm_plugin_btn">'.esc_html__('Install','bus-ticket-booking-with-seat-reservation').'</a>';
		}
		elseif($this->wbtm_chk_plugin_folder_exist('bus-ticket-booking-with-seat-reservation') == true && !is_plugin_active( 'bus-ticket-booking-with-seat-reservation/woocommerce-bus.php')){
			$button_wbtm = '<a href="'.esc_url($this->wbtm_wp_plugin_activation_url('bus-ticket-booking-with-seat-reservation/woocommerce-bus.php')).'" class="wbtm_plugin_btn">'.esc_html__('Activate','bus-ticket-booking-with-seat-reservation').'</a>';
		}
		else{
			$button_wbtm = '<span class="wbtm_plugin_status">'.esc_html__('Activated','bus-ticket-booking-with-seat-reservation').'</span>';
		}
		
		/* MagePeople PDF Support */
		if(is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php') && $pdflibrary == 'mpdf'){
			if($this->wbtm_chk_plugin_folder_exist('magepeople-pdf-support-master') == false) {;
				$button_mpdf = '<a href="'.esc_url($this->wbtm_wp_plugin_installation_url('magepeople-pdf-support-master')).'" class="wbtm_plugin_btn">'.esc_html__('Install','bus-ticket-booking-with-seat-reservation').'</a>';
			}
			elseif($this->wbtm_chk_plugin_folder_exist('magepeople-pdf-support-master') == true && !is_plugin_active( 'magepeople-pdf-support-master/mage-pdf.php')){
				$button_mpdf = '<a href="'.esc_url($this->wbtm_wp_plugin_activation_url('magepeople-pdf-support-master/mage-pdf.php')).'" class="wbtm_plugin_btn">'.esc_html__('Activate','bus-ticket-booking-with-seat-reservation').'</a>';
			}
			else{
				$button_mpdf = '<span class="wbtm_plugin_status">'.esc_html__('Activated','bus-ticket-booking-with-seat-reservation').'</span>';
			}
		}		
		?>
		<div class="wrap wbtm_plugin_page_wrap">
			<table>
				<thead>
					<tr>
						<th colspan="2"><?php esc_html_e('	
Bus Ticket Booking with Seat Reservation Required Plugins','bus-ticket-booking-with-seat-reservation'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e('WooCommerce','bus-ticket-booking-with-seat-reservation'); ?></td>
						<td><?php echo $button_wc; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e('	
Bus Ticket Booking with Seat Reservation','bus-ticket-booking-with-seat-reservation'); ?></td>
						<td><?php echo $button_wbtm; ?></td>
					</tr>
					<?php if (is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php') && $pdflibrary == 'mpdf') {  ?>
					<tr>
						<td><?php esc_html_e('MagePeople PDF Support','bus-ticket-booking-with-seat-reservation'); ?></td>
						<td><?php echo $button_mpdf; ?></td>
					</tr>
					<?php } ?>										
				</tbody>
			</table>
		</div>
		<style>
		.wbtm_plugin_page_wrap{
			margin-left: 15px;
			margin-right: 15px;			
		}
		.wbtm_plugin_page_wrap table{
			width: 100%;
			border-collapse: collapse;
			border: 1px solid #d3d3d3;
		}
		.wbtm_plugin_page_wrap table tr{
			border-bottom: 1px solid #d3d3d3;
			background-color: #fff;
		}
		.wbtm_plugin_page_wrap table tr th{
			background: #162748;
			color: #fff;
		}
		.wbtm_plugin_page_wrap table tr th,
		.wbtm_plugin_page_wrap table tr td{
			padding: 15px;
			text-align: left;
		}
		.wbtm_plugin_page_wrap .wbtm_plugin_status{
			color: #1c931c;
		}
		.wbtm_plugin_page_wrap .wbtm_plugin_btn{
			background-color: #22D02D;
			color: #fff;
			text-decoration: none;
			padding: 8px;
			transition: 0.2s;
			border-radius: 5px;
		}
		.wbtm_plugin_page_wrap .wbtm_plugin_btn:hover{
			background-color: #0FA218;
			color: #fff;
			transition: 0.2s;
		}
		</style>
		<?php

		$this->wbtm_mpdf_plugin_install();
	}

	public function wbtm_wp_plugin_installation_url($slug){

		if($slug){

			$url = admin_url($this->wbtm_plugin_page_location()).'?page=wbtm-plugins&wbtm_plugin_install='.$slug;			
		}
		else{

			$url = '';
		}

		return $url;		
	}

	public function wbtm_required_plugin_list(){

		$pdflibrary = 'mpdf';
		
		$list = array();

		if( $this->wbtm_chk_plugin_folder_exist('woocommerce') == false ) {
			$list[] = __('WooCommerce','bus-ticket-booking-with-seat-reservation');
		}
		if( $this->wbtm_chk_plugin_folder_exist('bus-ticket-booking-with-seat-reservation')  == false) {
			$list[] = __('	
			Bus Ticket Booking with Seat Reservation','bus-ticket-booking-with-seat-reservation');			
		}
		if (is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php') && $pdflibrary == 'mpdf') {				
			if( $this->wbtm_chk_plugin_folder_exist('magepeople-pdf-support-master')  == false) {
				$list[] = __('MagePeople PDF Support','bus-ticket-booking-with-seat-reservation');			
			}
		}
		return $list;		
	}

	public function wbtm_inactive_plugin_list(){

		$pdflibrary = 'mpdf';
		
		$list = array();

		if($this->wbtm_chk_plugin_folder_exist('woocommerce') == true && !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$list[] = __('WooCommerce','bus-ticket-booking-with-seat-reservation');
		}
		if($this->wbtm_chk_plugin_folder_exist('bus-ticket-booking-with-seat-reservation') == true && !is_plugin_active( 'bus-ticket-booking-with-seat-reservation/woocommerce-bus.php' ) ) {
			$list[] = __('	
			Bus Ticket Booking with Seat Reservation','bus-ticket-booking-with-seat-reservation');			
		}
		if (is_plugin_active('addon-bus--ticket-booking-with-seat-pro/wbtm-pro.php') && $pdflibrary == 'mpdf') {				
			if($this->wbtm_chk_plugin_folder_exist('magepeople-pdf-support-master') == true && !is_plugin_active( 'magepeople-pdf-support-master/mage-pdf.php' ) ) {
				$list[] = __('MagePeople PDF Support','bus-ticket-booking-with-seat-reservation');			
			}
		}
		return $list;		
	}	

	public function wbtm_admin_notices(){

		$pdflibrary = 'mpdf';

		$url = admin_url($this->wbtm_plugin_page_location()).'?page=wbtm-plugins';	
		
		$required_plugins = $this->wbtm_required_plugin_list();
		$inactive_plugins = $this->wbtm_inactive_plugin_list();
		$total_r_plugins = count($required_plugins);
		$total_i_plugins = count($inactive_plugins);

		if($total_r_plugins > 0 || $total_i_plugins > 0){
		?>
		<div class="notice notice-success is-dismissible">
			<?php
			echo '<p>';
			echo '<strong>';

			if($total_r_plugins > 0){
				$i = 1;
				if($total_r_plugins == 1){
					echo __('	
					Bus Ticket Booking with Seat Reservation required the following plugin: ','bus-ticket-booking-with-seat-reservation');
				}
				else{
					echo __('	
					Bus Ticket Booking with Seat Reservation required the following plugins: ','bus-ticket-booking-with-seat-reservation');
				}

				echo '<i>';
				
				foreach ($required_plugins as $plugin) {
					if($i < $total_r_plugins){
						echo $plugin.', ';
					}
					else{
						echo $plugin.'.';
					}
	
					$i++;
				}
				echo '</i>';
				echo '<br/>';
			}

			if($total_i_plugins > 0){
				$i = 1;
				echo __('	
					Bus Ticket Booking with Seat Reservation:','bus-ticket-booking-with-seat-reservation');
				echo '<br>';
				if($total_i_plugins == 1){
					echo __('The following required plugin is currently inactive: ','bus-ticket-booking-with-seat-reservation');
				}
				else{
					echo __('The following required plugins are currently inactive: ','bus-ticket-booking-with-seat-reservation');
				}				

				echo '<i>';

				foreach ($inactive_plugins as $plugin) {
					if($i < $total_i_plugins){
						echo $plugin.', ';
					}
					else{
						echo $plugin.'.';
					}

					$i++;
				}
				echo '</i>';
				echo '<br/>';
			}

			if($total_r_plugins > 0){
				echo '<a href="'.esc_url($url).'">';
				echo __('Begin installing plugins','bus-ticket-booking-with-seat-reservation');
				echo '</a>';
			}

			if($total_r_plugins > 0 && $total_i_plugins > 0){
				echo ' | ';
			}
			
			if($total_i_plugins > 0){
				echo '<a href="'.esc_url($url).'">';
				echo __('Activate installed plugin','bus-ticket-booking-with-seat-reservation');
				echo '</a>';
			}

			echo '</strong>';
			echo '</p>';
			?>
		</div>
		<?php
		}	
	}
}
}
new WBTM_Required_Plugins();
