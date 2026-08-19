<?php
	/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	// Read-only preview: never a second checkout path. It only mirrors what
	// wbtm_price_calculation() already writes into .wbtm_selected_seat_details /
	// .wbtm_sub_total (see wbtm_update_summary_preview() in wbtm_global.js), and
	// its own Book Now button just re-triggers the real #wbtm_add_to_cart click
	// handler — no seat/cart logic is duplicated here.
?>
<div class="wbtm_booking_summary_preview">
	<h4 class="wbtm_booking_summary_title"><?php esc_html_e('Booking Summary', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
	<?php if (!empty($start_route) && !empty($end_route)) { ?>
		<p class="wbtm_booking_summary_route">
			<?php
				echo esc_html(
					sprintf(
						/* translators: 1: start route name, 2: end route name */
						__('Route: %1$s → %2$s', 'bus-ticket-booking-with-seat-reservation'),
						$start_route,
						$end_route
					)
				);
			?>
		</p>
	<?php } ?>
	<div class="wbtm_booking_summary_card">
	<table class="_layoutFixed_textLeft wbtm_booking_summary_table">
		<?php // Placeholder row shown before any seat is picked; wbtm_update_summary_preview()
		  // in wbtm_global.js caches this exact markup the first time it runs and restores
		  // it whenever the selection count drops back to zero, so the "no seat yet" text
		  // never needs to be duplicated/translated a second time in JS. ?>
		<tbody class="wbtm_summary_preview_rows">
		<tr class="wbtm_summary_preview_empty_row">
			<td colspan="3"><?php esc_html_e('No seat selected yet', 'bus-ticket-booking-with-seat-reservation'); ?></td>
		</tr>
		</tbody>
	</table>
	<div class="wbtm_booking_summary_subtotal justifyBetween">
		<span><?php esc_html_e('Ticket Sub total', 'bus-ticket-booking-with-seat-reservation'); ?></span>
		<span class="wbtm_summary_preview_subtotal"><?php echo wp_kses_post( WBTM_Global_Function::format_price(0) ); ?></span>
	</div>
	<?php // Extra services, one line each, between the ticket subtotal and the
	  // grand total — the card used to jump straight from one to the other with
	  // nothing explaining the difference. Rows and the services subtotal are
	  // filled by wbtm_update_summary_services() in wbtm_global.js from the same
	  // wbtm_ex_service_lines() the running total is summed from. Hidden until a
	  // service is actually taken. ?>
	<div class="wbtm_summary_preview_services" style="display:none;">
		<div class="wbtm_summary_preview_service_rows"></div>
		<?php // Only worth printing when more than one service was taken — with a
		  // single line it just repeats the amount immediately above it. Toggled
		  // by wbtm_update_summary_services(). ?>
		<div class="wbtm_summary_services_subtotal justifyBetween" style="display:none;">
			<span><?php echo esc_html( WBTM_Translations::text_ex_service_sub_total() ); ?></span>
			<span class="wbtm_summary_preview_services_total"><?php echo wp_kses_post( WBTM_Global_Function::format_price(0) ); ?></span>
		</div>
	</div>
	<?php // Grand total — ticket subtotal + extra services, mirrored from
	  // .wbtm_total (the same value the real footer/#wbtm_add_to_cart uses),
	  // not recomputed here. See wbtm_update_summary_preview() in
	  // wbtm_global.js. ?>
	<div class="wbtm_booking_summary_total justifyBetween">
		<span><?php esc_html_e('Total Price', 'bus-ticket-booking-with-seat-reservation'); ?></span>
		<span class="wbtm_summary_preview_total"><?php echo wp_kses_post( WBTM_Global_Function::format_price(0) ); ?></span>
	</div>
	</div>
	<?php // Disabled by default — enabled only once a seat is selected, see
	  // wbtm_update_summary_preview() in wbtm_global.js. A native disabled
	  // button never dispatches click events, so the existing delegated
	  // click handler for this button can't fire while it's inactive. ?>
	<button type="button" class="wbtm_summary_preview_book_now" disabled>
		<?php esc_html_e('Book Now', 'bus-ticket-booking-with-seat-reservation'); ?>
	</button>
	<p class="wbtm_booking_summary_note">
		<?php esc_html_e('Free cancellation up to 24 hours before departure. Terms apply.', 'bus-ticket-booking-with-seat-reservation'); ?>
	</p>
</div>
