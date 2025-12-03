<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.

//if( isset( $_POST['nonce'] ) && wp_verify_nonce(  sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),'wtbm_ajax_nonce' ) ){

    /*$post_id = $post_id ?? '';
    $start_route = $start_route ?? '';
    $end_route = $end_route ?? '';
	$date = $bus_start_time ?? '';*/
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$show_extra_service = WBTM_Global_Function::get_post_info($post_id, 'show_extra_service', 'no');
	if ($show_extra_service == 'yes') {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$ex_services = WBTM_Global_Function::get_post_info($post_id, 'wbtm_extra_services', []);
		if (sizeof($ex_services) > 0) {
			?>
			<div class="wbtm_ex_service_area mB_xs">
				<h3 class="textTheme mT mB"><?php echo esc_html( WBTM_Translations::text_ex_service() ); ?> : </h3>
				<div class="mpPanel">
					
					<table class="_layoutFixed">
						<thead>
						<tr>
							<th class="_textLeft"><?php echo esc_html( WBTM_Translations::text_name() );?></th>
							<th class="_textCenter"><?php echo esc_html( WBTM_Translations::text_qty() ); ?></th>
							<th class="_textCenter"><?php echo esc_html( WBTM_Translations::text_price() );?></th>
						</tr>
						</thead>
						<tbody>

						<?php
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                        foreach ($ex_services as $ex_service) { ?>
							<?php
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$row_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ex_service['option_price']);
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$qty_type = $ex_service['option_qty_type'];
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$ex_name = $ex_service['option_name'];
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$total_ex = max($ex_service['option_qty'], 0);
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$sold = WBTM_Query::query_ex_service_sold($post_id, $date, $ex_name);
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
							$available_ex_service = $total_ex - $sold;
							?>
							<tr>
								<td class="_textLeft"><?php echo esc_html($ex_name); ?></td>
								<td class="_textCenter">
									<input type="hidden" name="extra_service_name[]" value="<?php echo esc_attr($ex_name); ?>">
									<?php WBTM_Custom_Layout::qty_input('extra_service_qty[]', $row_price, $available_ex_service, 0, 0, $available_ex_service, $qty_type, $ex_name); ?>
								</td>
								<td class="_textCenter"><?php echo wp_kses_post( wc_price($row_price) ); ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}
	}
	do_action('wbtm_registration_form_inside', $post_id);

//}