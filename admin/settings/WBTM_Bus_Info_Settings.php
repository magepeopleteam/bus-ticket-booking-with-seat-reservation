<?php
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
if ( ! class_exists( 'WBTM_Bus_Info_Settings' ) ) {
	class WBTM_Bus_Info_Settings {
		public function tab_content( $post_id ) {
			$info_title   = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_info_title' );
			$info_content = WBTM_Global_Function::get_post_info( $post_id, 'wbtm_bus_info_content' );
			?>
			<div class="tabsItem" data-tabs="#wbtm_bus_info">
				<div class="wbtm-bme__general-rows-title" style="margin-top:18px;"><?php esc_html_e( 'Bus Information', 'bus-ticket-booking-with-seat-reservation' ); ?></div>
				<div class="wbtm-bme__general-rows-box wbtm-bme__info-box">
					<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter wbtm-bme__info-field-row">
						<div class="col_6 _dFlex_fdColumn">
							<label><?php esc_html_e( 'Title', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
						</div>
						<div class="col_6 textRight">
							<input class="formControl max_300" name="wbtm_bus_info_title" type="text" value="<?php echo esc_attr( $info_title ); ?>" placeholder="<?php esc_attr_e( 'Enter title', 'bus-ticket-booking-with-seat-reservation' ); ?>"/>
						</div>
					</div>
					<div class="_dLayout_padding_dFlex_justifyBetween_alignCenter wbtm-bme__info-field-row">
						<div class="col_6 _dFlex_fdColumn">
							<label><?php esc_html_e( 'Content', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
						</div>
						<div class="col_6 textRight">
							<textarea class="formControl max_300 wbtm-bme__info-textarea" name="wbtm_bus_info_content" rows="4" placeholder="<?php esc_attr_e( 'Enter content', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_textarea( $info_content ); ?></textarea>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
