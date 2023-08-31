<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	/**
	 * @since      1.0.0
	 * @package    WBTM_Plugin
	 * @subpackage WBTM_Plugin/includes
	 * @author     MagePeople team <magepeopleteam@gmail.com>
	 */
	class WBTM_Plugin_Functions {
		protected $loader;
		protected $plugin_name;
		protected $version;
		public function __construct() {
			$this->add_hooks();
			add_filter('mage_wc_products', array($this, 'add_cpt_to_wc_product'), 10, 1);
		}
		private function add_hooks() {
			add_action('init', array($this, 'direct_ticket_download'));
			add_action('wp_ajax_wbtm_seat_plan', array($this, 'wbtm_seat_plan'));
			add_action('wp_ajax_nopriv_wbtm_seat_plan', array($this, 'wbtm_seat_plan'));
			add_action('woocommerce_order_status_changed', array($this, 'wbtm_bus_ticket_seat_management'), 10, 4);
			add_action('wp_ajax_wbtm_seat_plan_dd', array($this, 'wbtm_seat_plan_dd'));
			add_action('wp_ajax_nopriv_wbtm_seat_plan_dd', array($this, 'wbtm_seat_plan_dd'));
			add_action('woocommerce_checkout_order_processed', array($this, 'bus_order_processed'), 10);
		}
		public function direct_ticket_download() {
			global $magepdf;
			if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'download_pdf_ticket') {
				$magepdf->generate_pdf($_REQUEST['order_id'], '', true);
			}
		}
		public function wbtm_get_driver_position($current_plan) {
			?>
			<select name="driver_seat_position">
				<option <?php if ($current_plan == 'driver_left') {
					echo 'Selected';
				} ?> value="driver_left"><?php _e('Left', 'bus-ticket-booking-with-seat-reservation'); ?></option>
				<option <?php if ($current_plan == 'driver_right') {
					echo 'Selected';
				} ?> value="driver_right"><?php _e('Right', 'bus-ticket-booking-with-seat-reservation'); ?></option>
				<?php do_action('wbtm_after_driver_position_dd'); ?>
			</select>
			<?php
		}
		public function wbtm_seat_plan() {
			$seat_col = strip_tags($_POST['seat_col']);
			$seat_row = strip_tags($_POST['seat_row']);
			?>
			<div>
				<script type="text/javascript">
					jQuery(document).ready(function ($) {
						$('#add-seat-row').on('click', function () {
							var row = $('.empty-row-seat.screen-reader-text').clone(true);
							row.removeClass('empty-row-seat screen-reader-text');
							row.insertBefore('#repeatable-fieldset-seat-one tbody>tr:last');
							var qtt = parseInt($('#seat_rows').val(), 10);
							$('#seat_rows').val(qtt + 1);
							return false;
						});
						$('.remove-seat-row').on('click', function () {
							$(this).parents('tr').remove();
							var qtt = parseInt($('#seat_rows').val(), 10);
							$('#seat_rows').val(qtt - 1);
							return false;
						});
					});
				</script>
				<table class="wbtm-seat-table" id="repeatable-fieldset-seat-one">
					<tbody>
					<?php
						for ($x = 1; $x <= $seat_row; $x++) {
							?>
							<tr>
								<?php
									for ($row = 1; $row <= $seat_col; $row++) {
										$seat_type_name = "seat_types" . $row;
										?>
										<td>
											<input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
										</td>
									<?php } ?>
								<td>
									<a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
									<input type="hidden" name="bus_seat_panels[]">
								</td>
							</tr>
							<?php
						}
					?>
					<!-- empty hidden one for jQuery -->
					<tr class="empty-row-seat screen-reader-text">
						<?php
							for ($row = 1; $row <= $seat_col; $row++) {
								$seat_type_name = "seat_types" . $row;
								?>
								<td align="center">
									<input type="text" value="" name="seat<?php echo $row; ?>[]" class="text">
								</td>
							<?php } ?>
						<td align="center">
							<a class="button remove-seat-row" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<input type="hidden" name="bus_seat_panels[]">
						</td>
					</tr>
					</tbody>
				</table>
				<p>
					<a id="add-seat-row" class="add-seat-row-btn" href="#">
						<i class="fas fa-plus"></i>
						Add Seat Row
					</a>
				</p>
			</div>
			<?php
			die();
		}
		public function wbtm_seat_plan_dd() {
			$seat_col = strip_tags($_POST['seat_col']);
			$seat_row = strip_tags($_POST['seat_row']);
			?>
			<div>
				<script type="text/javascript">
					jQuery(document).ready(function ($) {
						$('#add-seat-row-dd').on('click', function () {
							var row = $('.empty-row-seat-dd.screen-reader-text').clone(true);
							row.removeClass('empty-row-seat-dd screen-reader-text');
							row.insertBefore('#repeatable-fieldset-seat-one-dd tbody>tr:last');
							var qtt = parseInt($('#seat_rows_dd').val(), 10);
							$('#seat_rows_dd').val(qtt + 1);
							return false;
						});
						$('.remove-seat-row-dd').on('click', function () {
							$(this).parents('tr').remove();
							var qtt = parseInt($('#seat_rows_dd').val(), 10);
							$('#seat_rows_dd').val(qtt - 1);
							return false;
						});
					});
				</script>
				<table class="wbtm-seat-table" id="repeatable-fieldset-seat-one-dd" width="100%">
					<tbody>
					<?php
						for ($x = 1; $x <= $seat_row; $x++) {
							?>
							<tr>
								<?php
									for ($row = 1; $row <= $seat_col; $row++) {
										?>
										<td align="center">
											<input type="text" value="" name="dd_seat<?php echo $row; ?>[]" class="text">
										</td>
									<?php } ?>
								<td align="center">
									<a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
									<input type="hidden" name="bus_seat_panels_dd[]">
								</td>
							</tr>
							<?php
						}
					?>
					<!-- empty hidden one for jQuery -->
					<tr class="empty-row-seat-dd screen-reader-text">
						<?php
							for ($row = 1; $row <= $seat_col; $row++) {
								?>
								<td align="center">
									<input type="text" value="" name="dd_seat<?php echo $row; ?>[]" class="text">
								</td>
							<?php } ?>
						<td align="center">
							<a class="button remove-seat-row-dd" href="#"><?php _e('Remove', 'bus-ticket-booking-with-seat-reservation'); ?></a>
							<input type="hidden" name="bus_seat_panels_dd[]">
						</td>
					</tr>
					</tbody>
				</table>
				<p>
					<a id="add-seat-row-dd" class="add-seat-row-btn" href="#">
						<i class="fas fa-plus"></i>
						Add Seat Row
					</a>
				</p>
			</div>
			<?php
			die();
		}
		// Get Bus Settings Optiins Data
		public function bus_get_option($meta_key, $setting_name = '', $default = null) {
			$get_settings = get_option('wbtm_bus_settings');
			$get_val = isset($get_settings[$meta_key]) ? $get_settings[$meta_key] : '';
			$output = $get_val ? $get_val : $default;
			return $output;
		}
		public function wbtm_bus_seat_plan_dd($start, $date) {
			wbtm_seat_global($start, $date, 'dd');
		}
		// Getting all the bus stops name from a stop name
		public function wbtm_get_all_stops_after_this($bus_id, $val, $end) {
			//echo $end;
			$end_s = array($val);
			//Getting All boarding points
			$boarding_points = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true));
			$all_bp_stops = array();
			foreach ($boarding_points as $_boarding_points) {
				$all_bp_stops[] = $_boarding_points['wbtm_bus_bp_stops_name'];
			}
			$pos2 = array_search($end, $all_bp_stops);
			// if (sizeof($pos2) > 0) {
			if ($pos2 != '') {
				unset($all_bp_stops[$pos2]);
			}
			// print_r($all_bp_stops);
			// echo '<br/>';
			//Gettings Stops Name Before Droping Stops
			$start_stops = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true));
			$all_stops = array();
			if (is_array($start_stops) && sizeof($start_stops) > 0) {
				foreach ($start_stops as $_start_stops) {
					$all_stops[] = $_start_stops['wbtm_bus_next_stops_name'];
				}
			}
			$full_array = $all_stops;
			$mkey = array_search($end, $full_array);
			$newarray = array_slice($full_array, $mkey, count($full_array), true);
			// return $newarray;
			$myArrayInit = $full_array; //<-- Your actual array
			$offsetKey = $mkey; //<--- The offset you need to grab
			//Lets do the code....
			$n = array_keys($myArrayInit); //<---- Grab all the keys of your actual array and put in another array
			$count = array_search($offsetKey, $n); //<--- Returns the position of the offset from this array using search
			$new_arr = array_slice($myArrayInit, 0, $count + 1, true); //<--- Slice it with the 0 index as start and position+1 as the length parameter.
			$pos2 = array_search($end, $new_arr);
			// if (sizeof($pos2) > 0) {
			if ($pos2 != '') {
				unset($new_arr[$pos2]);
			}
			$res = array_merge($all_bp_stops, $new_arr);
			return $res;
			// print_r();
		}
		// Adding Custom Post to WC Prodct Data Filter.
		public function add_cpt_to_wc_product($data) {
			$WBTM_cpt = array('wbtm_bus');
			return array_merge($data, $WBTM_cpt);
		}
		// make page id
		public function wbtm_make_id($val) {
			return str_replace("-", "", $val);
		}
		// create bus js
		public function wbtm_seat_booking_js($id, $fare) {
			$fare = isset($fare) ? $fare : 0;
			$upper_price_percent = (int)get_post_meta(get_the_ID(), 'wbtm_seat_dd_price_parcent', true);
			?>
			<script>
				jQuery(document).ready(function ($) {
					$('#bus-booking-btn<?php echo $id; ?>').hide();
					$(document).on('remove_selection<?php echo $id; ?>', function (e, seatNumber, parents) {
						$('#selected_list<?php echo $id; ?>_' + seatNumber).remove();
						$('#seat<?php echo $id; ?>_' + seatNumber).removeClass('seat<?php echo $id; ?>_booked');
						$('#seat<?php echo $id; ?>_' + seatNumber).removeClass('seat_booked');
						wbt_calculate_total(parents);
						// wbt_update_passenger_form();
						wbtm_remove_form_builder(parents, seatNumber); // Seat form builder form remove
					})
					$(document).on('click', '.seat<?php echo $id; ?>_booked', function () {
						// $( document.body ).trigger( 'remove_selection<?php //echo $id;
						?>', [ $(this).data("seat") ] );
					})
					$(document).on('click', '.remove-seat-row<?php echo $id; ?>', function () {
						let parents = $(this).parents('.admin-bus-details');
						$(document.body).trigger('remove_selection<?php echo $id; ?>', [$(this).data("seat"), parents]);
					});
					jQuery('#start_stops<?php echo $id; ?>').on('change', function () {
						var start_time = jQuery(this).find(':selected').data('start');
						jQuery('#user_start_time<?php echo $id; ?>').val(start_time);
					});
					jQuery(".seat<?php echo $id; ?>_blank").on('click', function () {
						let parents = $(this).parents('.admin-bus-details');
						if (jQuery(this).hasClass('seat<?php echo $id; ?>_booked')) {
							jQuery(document.body).trigger('remove_selection<?php echo $id; ?>', [
								jQuery(this).data(
									"seat"), parents
							]);
							return;
						}
						jQuery(this).addClass('seat<?php echo $id; ?>_booked');
						jQuery(this).addClass('seat_booked');
						var seat<?php echo $id; ?>_name = jQuery(this).data("seat");
						var seat<?php echo $id; ?>_class = jQuery(this).data("sclass");
						var seat_pos = jQuery(this).data("seat-pos");
						if (seat_pos == 'upper') {
							var fare =
								<?php echo $fare + ($upper_price_percent != 0 ? (($fare * $upper_price_percent) / 100) : 0); ?>;
						} else {
							var fare = <?php echo $fare; ?>;
						}
						let seat_name = seat<?php echo $id; ?>_name;
						var foo = "<tr class='seat_selected_price' id='selected_list<?php echo $id; ?>_" +
							seat<?php echo $id; ?>_name +
							"'><td align=center><input type='hidden' name='passenger_label[]' value='Adult'/>" +
							"<input type='hidden' name='passenger_type[]' value='0'/>" +
							"<input type='hidden' name='seat_name[]' value='" + seat<?php echo $id; ?>_name + "'/>" +
							seat<?php echo $id; ?>_name +
							"</td><td align=center>Adult</td><td align=center><input class='seat_fare' type='hidden' name='seat_fare[]' value=" +
							fare + "><input type='hidden' name='bus_fare<?php echo $id; ?>' value=" + fare +
							">" + mp_price_format(fare) +
							"</td><td align=center><a class='button remove-seat-row<?php echo $id; ?>' data-seat='" +
							seat<?php echo $id; ?>_name + "'>X</a></td></tr>";
						jQuery(foo).insertAfter('.list_head<?php echo $id; ?>');
						var total_fare = jQuery('.bus_fare<?php echo $id; ?>').val();
						var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;
						//                  var totalFare = (rowCount * fare);
						//
						var totalFare = 0;
						jQuery('.selected-seat-table tbody tr').each(function () {
							if ($(this).hasClass('seat_selected_price')) {
								totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
							}
						});
						jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
						jQuery('#tq<?php echo $id; ?>').val(rowCount);
						// jQuery('#totalFare<?php echo $id; ?>').html("<?php echo get_woocommerce_currency_symbol(); ?> <span class='price-figure'>" + totalFare.toFixed(2) + "</span>");
						jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html(mp_price_format(totalFare));
						jQuery('#tfi<?php echo $id; ?>').val("<?php echo get_woocommerce_currency_symbol(); ?>" +
							totalFare);
						if (totalFare > 0) {
							jQuery('#bus-booking-btn<?php echo $id; ?>').show();
						}
						// alert(totalFare);
						mageGrandPrice(parents);
						// wbt_update_passenger_form(seat_name);
						wbtm_seat_plan_form_builder_new($(this), seat_name, true); // New
					});
					// *******Admin Ticket Purchase*******
					jQuery('.admin_<?php echo $id; ?> li').on('click', function () {
						const $this = $(this);
						let parents = $(this).parents('.admin-bus-details');
						const parent = $this.parents('.admin_<?php echo $id; ?>').siblings(
							'.seat<?php echo $id; ?>_blank');
						var price = $this.attr('data-seat-price');
						var label = $this.attr('data-seat-label');
						var passenger_type = $this.attr('data-seat-type');
						let seat_label = label;
						if (parent.hasClass('seat<?php echo $id; ?>_booked')) {
							jQuery(document.body).trigger('remove_selection<?php echo $id; ?>', [parent.data("seat")]);
						}
						parent.addClass('seat<?php echo $id; ?>_booked');
						parent.addClass('seat_booked');
						var seat<?php echo $id; ?>_name = parent.data("seat");
						var seat<?php echo $id; ?>_class = parent.data("sclass");
						var fare = price;
						let seat_name = seat<?php echo $id; ?>_name;
						let foo = "<tr class='seat_selected_price' id='selected_list<?php echo $id; ?>_" +
							seat<?php echo $id; ?>_name +
							"'><td align=center><input type='hidden' name='passenger_label[]' value='" + label + "'/>" +
							"<input type='hidden' name='passenger_type[]' value='" + passenger_type + "'/>" +
							"<input type='hidden' name='seat_name[]' value='" + seat<?php echo $id; ?>_name + "'/>" +
							seat<?php echo $id; ?>_name + "</td><td align=center>" + label +
							"</td><td align=center><input class='seat_fare' type='hidden' name='seat_fare[]' value=" +
							fare + "><input type='hidden' name='bus_fare<?php echo $id; ?>' value=" + fare +
							">" + mp_price_format(fare) +
							"</td><td align=center><a class='button remove-seat-row<?php echo $id; ?>' data-seat='" +
							seat<?php echo $id; ?>_name + "'>X</a></td></tr>";
						jQuery(foo).insertAfter('.list_head<?php echo $id; ?>');
						var total_fare = jQuery('.bus_fare<?php echo $id; ?>').val();
						var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;
						var totalFare = 0;
						jQuery('.selected-seat-table tbody tr').each(function () {
							// totalFare = totalFare + parseFloat($(this).find('.seat_selected_price').val());
							if ($(this).hasClass('seat_selected_price')) {
								totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
							}
						});
						jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
						jQuery('#tq<?php echo $id; ?>').val(rowCount);
						jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html(mp_price_format(totalFare));
						jQuery('#tfi<?php echo $id; ?>').val("<?php echo get_woocommerce_currency_symbol(); ?>" +
							totalFare.toFixed(2));
						if (totalFare > 0) {
							jQuery('#bus-booking-btn<?php echo $id; ?>').show();
						}
						mageGrandPrice(parents);
						// wbt_update_passenger_form(seat_name);
						wbtm_seat_plan_form_builder_new($(this), seat_name, true, seat_label); // New
					});
					// ******Admin Ticket Purchase (Dropdown)********
					// Show Grand Price
					function mageGrandPrice(parent) {
						let grand_ele = parent.find('.mage-grand-total .mage-price-figure');
						// price items
						let seat_price = parseFloat(parent.find('.mage-price-total span').attr('data-subtotal-price')); // 1
						let extra_price = 0;
						parent.find('.wbtm_extra_service_table tbody tr').each(function () { // 2
							extra_price += parseFloat($(this).attr('data-total'));
						});
						// Sum all items
						let grand_total = seat_price + extra_price;
						if (grand_total) {
							grand_ele.text(php_vars.currency_symbol + grand_total.toFixed(2));
							parent.find('.no-seat-submit-btn').prop('disabled', false);
							parent.find('button[name="add-to-cart-admin"]').prop('disabled', false);
						} else {
							grand_ele.text(php_vars.currency_symbol + "0.00");
							parent.find('.no-seat-submit-btn').prop('disabled', true);
							parent.find('button[name="add-to-cart-admin"]').prop('disabled', true);
						}
					}
					function wbt_calculate_total(parents) {
						var fare = <?php echo $fare; ?>;
						var rowCount = jQuery('.selected-seat-list<?php echo $id; ?> tr').length - 2;
						var totalFare = 0;
						jQuery('.selected-seat-table tbody tr').each(function () {
							if ($(this).hasClass('seat_selected_price')) {
								totalFare = totalFare + parseFloat($(this).find('.seat_fare').val());
							}
						})
						jQuery('#total_seat<?php echo $id; ?>_booked').html(rowCount);
						jQuery('#tq<?php echo $id; ?>').val(rowCount);
						jQuery('#totalFare<?php echo $id; ?>').attr('data-subtotal-price', totalFare).html("<?php echo get_woocommerce_currency_symbol(); ?> <span class='price-figure'>" + totalFare.toFixed(2) + "</span>");
						jQuery('#tfi<?php echo $id; ?>').val(totalFare.toFixed(2));
						mageGrandPrice(parents);
					}
					// Seat plan Passenger info form (New)
					function wbtm_seat_plan_form_builder_new($this, seat_name, onlyES = false, seat_label = '') {
						let parent = $this.parents('.admin-bus-details');
						let bus_id = parent.attr('data-bus-id');
						let qty = 1;
						let seatType = seat_name;
						let isSeatPlan = true;
						$.ajax({
							url: mp_ajax_url,
							type: 'POST',
							async: true,
							data: {
								busID: bus_id,
								seatType: seatType,
								seats: qty,
								onlyES: onlyES,
								action: 'wbtm_form_builder'
							},
							beforeSend: function () {
								parent.find('.wbtm-form-builder-loading').show();
							},
							success: function (data) {
								if (data !== '') {
									if (parent.find(".mage_customer_info_area").children().length == 0) {
										parent.find(".mage_customer_info_area").html(data).find('.seat_name_' + seat_name + ' .mage_title h5').html('Passenger Information<br>Seat Type: ' + seat_label + ' | Seat No: ' + seat_name);
									} else {
										if (seat_name != 'ES') {
											parent.find(".mage_customer_info_area").append(data).find('.seat_name_' + seat_name + ' .mage_title h5').html('Passenger Information<br>Seat Type: ' + seat_label + ' | Seat No: ' + seat_name);
											parent.find(".mage_customer_info_area .seat_name_ES").remove();
										}
									}
									onlyES ? parent.find('.mage_customer_info_area input[name="seat_name[]"]').remove() : null;
								} else {
									parent.find(".mage_customer_info_area").empty();
								}
								// Loading hide
								parent.find('.wbtm-form-builder-loading').hide();
							}
						});
					}
					function wbtm_remove_form_builder($this, seat_name) {
						$this.find(".mage_customer_info_area .seat_name_" + seat_name).remove();
						// ES qty
						let es_table = $this.find('.wbtm_extra_service_table');
						let es_qty = 0;
						es_table.find('tbody tr').each(function () {
							tp = $(this).find('.extra-qty-box').val();
							es_qty += tp > 0 ? parseInt(tp) : 0;
						});
						if (es_qty > 0) {
							wbtm_seat_plan_form_builder_new($this.find('.bus-info-sec'), 'ES', true);
						}
					}
					// Custom Reg Field New way
					function mageCustomRegField($this, seatType, qty, onlyES = false) {
						let parent = $this.parents('.admin-bus-details');
						let bus_id = parent.attr('data-bus-id');
						$.ajax({
							url: mp_ajax_url,
							type: 'POST',
							async: true,
							data: {
								busID: bus_id,
								seatType: seatType,
								seats: qty,
								onlyES: onlyES,
								action: 'wbtm_form_builder'
							},
							beforeSend: function () {
								parent.find('.wbtm-form-builder-loading').show();
							},
							success: function (data) {
								let s = seatType.toLowerCase();
								if (data !== '') {
									$(".wbtm-form-builder-" + s).html(data);
									$(".wbtm-form-builder-" + s).find('.mage_hidden_customer_info_form').each(function (index) {
										$(this).removeClass('mage_hidden_customer_info_form').find('.mage_form_list').slideDown(200);
										onlyES ? $(this).find('input[name="seat_name[]"]').remove() : null;
										$(this).find('.mage_title h5').html(seatType + ' : ' + (index + 1));
									});
								} else {
									parent.find(".wbtm-form-builder-" + s).empty();
								}
								parent.find('.wbtm-form-builder-loading').hide();
							}
						});
					}
					jQuery("#view_panel_<?php echo $id; ?>").click(function () {
						jQuery("#admin-bus-details<?php echo $id; ?>").slideToggle("slow", function () {
							// Animation complete.
						});
					});
					// Any date return
					jQuery('.wbtm_anydate_return_wrap').hide();
					jQuery('.blank_seat,.admin_passenger_type_list ul li,.selected-seat-table a.button').click(function (e) {
						// e.stopImmediatePropagation();
						e.preventDefault();
						jQuery('.wbtm_anydate_return_wrap').hide();
						let $this = jQuery(this);
						let parent = $this.parents('form');
						let seat_list = parent.find('.selected-seat-table tbody').children('.seat_selected_price');
					});
					jQuery('.mage-seat-qty input').on('input', function () {
						let $this = $(this);
						let type = $this.attr('data-seat-type');
						let qty = $this.val();
						qty = qty > 0 ? qty : 0;
					});
					// Any date return END
				});
			</script>
			<?php
		}
		public function wbtm_bus_seat_plan($current_plan, $start, $date, $return = false) {
			$global_plan = get_post_meta(get_the_id(), 'wbtm_bus_seats_info', true);
			if (!empty($global_plan)) {
				wbtm_seat_global($start, $date, '', $return);
			}
		}
		public function wbtm_get_this_bus_seat_plan() {
			$current_plan = get_post_meta(get_the_id(), 'seat_plan', true);
			$bus_meta = get_post_custom(get_the_id());
			if (array_key_exists('wbtm_seat_col', $bus_meta)) {
				$seat_col = $bus_meta['wbtm_seat_col'][0];
				$seat_col_arr = explode(",", $seat_col);
				$seat_column = count($seat_col_arr);
			}
			else {
				$seat_col = array();
				$seat_column = 0;
			}
			if (array_key_exists('wbtm_seat_row', $bus_meta)) {
				$seat_row = $bus_meta['wbtm_seat_row'][0];
				$seat_row_arr = explode(",", $seat_row);
			}
			else {
				$seat_row = array();
			}
			if ($current_plan) {
				$current_seat_plan = $current_plan;
			}
			else {
				if ($seat_column == 4) {
					$current_seat_plan = 'seat_plan_1';
				}
				else {
					$current_seat_plan = 'seat_plan_2';
				}
			}
			return $current_seat_plan;
		}
		public function wbtm_get_bus_start_time($start, $array) {
			if (is_array($array) && sizeof($array) > 0) {
				foreach ($array as $key => $val) {
					if ($val['wbtm_bus_bp_stops_name'] === $start) {
						return $val['wbtm_bus_bp_start_time'];
						// return $key;
					}
				}
			}
			return null;
		}
		public function wbtm_get_bus_end_time($end, $array) {
			foreach ($array as $key => $val) {
				if ($val['wbtm_bus_next_stops_name'] === $end) {
					return $val['wbtm_bus_next_end_time'];
					// return $key;
				}
			}
			return null;
		}
		public function wbtm_buffer_time_check($bp_time, $date) {
			$bus_start_time = date('H:i:s', strtotime($bp_time));
			if (!$bp_time) {
				return 'yes';
			}
			// Get the buffer time set by user
			$bus_buffer_time = $this->bus_get_option('bus_buffer_time', 'general_setting_sec', 0);
			if ($bus_buffer_time > 0) {
				// Convert bus start time into date format
				// $bus_buffer_time = $bus_buffer_time * 60;
				// Make bus search date & bus start time as date format
				$start_bus = $date . ' ' . $bus_start_time;
				// $diff = round((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 3600, 1); // In Hour
				$diff = round(((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 60), 1); // In Minute
				if (abs($diff) != $diff) {
					return 'no';
				}
				if ($diff >= (float)$bus_buffer_time) {
					return 'yes';
				}
				else {
					return 'no';
				}
			}
			else {
				$start_bus = $date . ' ' . $bus_start_time;
				$diff = round((strtotime($start_bus) - strtotime(current_time('Y-m-d H:i:s'))) / 60, 1); // In Minute
				if (abs($diff) != $diff) {
					return 'no';
				}
				return 'yes';
			}
		}
		public function wbtm_get_seat_status($seat, $date, $bus_id, $start, $end) {
			$args = array(
				'post_type' => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'relation' => 'AND',
						array(
							'key' => 'wbtm_seat',
							'value' => $seat,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_journey_date',
							'value' => $date,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_bus_id',
							'value' => $bus_id,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_status',
							'value' => array(1, 2),
							'compare' => 'IN',
						),
					),
					array(
						'relation' => 'OR',
						array(
							'key' => 'wbtm_boarding_point',
							'value' => $start,
							'compare' => 'LIKE',
						),
						array(
							'key' => 'wbtm_next_stops',
							'value' => $start,
							'compare' => 'LIKE',
						),
						array(
							'key' => 'wbtm_next_stops',
							'value' => $end,
							'compare' => 'LIKE',
						),
					),
				),
			);
			$q = new WP_Query($args);
			// $booking_id = $q->posts[0]->ID;
			$booking_id = (isset($q->posts[0]) ? $q->posts[0]->ID : null);
			$booking_status = get_post_meta($booking_id, 'wbtm_status', true);
			return $booking_status;
		}
		public function get_bus_start_time($bus_id) {
			$start_stop_array = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true) ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)) : array();
			$c = 1;
			$start_time = '';
			if (sizeof($start_stop_array) > 0) {
				foreach ($start_stop_array as $stops) {
					if ($c == 1) {
						$start_time = $stops['wbtm_bus_bp_start_time'];
					}
					# code...
					$c++;
				}
			}
			return $start_time;
		}
		// get bus price
		public function wbtm_get_bus_price($start, $end, $array, $seat_type = '') {
			foreach ($array as $key => $val) {
				if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
					//echo '<pre>';print_r($seat_type);echo '</pre>';die();
					if ('1' == $seat_type) {
						$price = $val['wbtm_bus_child_price'];
					}
					elseif ('2' == $seat_type) {
						$price = $val['wbtm_bus_infant_price'];
					}
					elseif ('3' == $seat_type) {
						$price = $val['wbtm_bus_special_price'];
					}
					else {
						$price = $val['wbtm_bus_price'];
					}
					return $price;
				}
			}
			return null;
		}
		public function wbtm_check_od_in_range($start_date, $end_date, $j_date) {
			// Convert to timestamp
			$start_ts = strtotime($start_date);
			$end_ts = strtotime($end_date);
			$user_ts = strtotime($j_date);
			// Check that user date is between start & end
			if (($user_ts >= $start_ts) && ($user_ts <= $end_ts)) {
				return 'yes';
			}
			else {
				return 'no';
			}
		}
		public function wbtm_array_strip($string, $allowed_tags = null) {
			if (is_array($string)) {
				foreach ($string as $k => $v) {
					$string[$k] = $this->wbtm_array_strip($v, $allowed_tags);
				}
				return $string;
			}
			return strip_tags($string, $allowed_tags);
		}
		public function update_bus_seat_status($order_id, $bus_id, $status) {
			$args = array(
				'post_type' => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => 'wbtm_bus_id',
						'value' => $bus_id,
						'compare' => '=',
					),
					array(
						'key' => 'wbtm_order_id',
						'value' => $order_id,
						'compare' => '=',
					),
				),
			);
			$q = new WP_Query($args);
			foreach ($q->posts as $bus) {
				# code...
				update_post_meta($bus->ID, 'wbtm_status', $status);
			}
		}
		public function create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, $_seats = null, $fare = null, $j_date = null, $add_datetime = null, $user_name = null, $user_email = null, $passenger_type = null, $passenger_type_num = null, $user_phone = null, $user_gender = null, $user_address = null, $wbtm_extra_bag_qty = null, $extra_bag_price = null, $usr_inf = null, $counter = null, $status = null, $order_meta = null, $wbtm_billing_type = null, $city_zone = null, $wbtm_pickpoint = null, $extra_services = array(), $user_additional = null,  $calculated_fare = null) {
			$add_datetime = current_time("Y-m-d") . ' class-functions.php' . mage_wp_time(current_time("H:i"));
			$name = '#' . $order_id . get_the_title($bus_id);
			$new_post = array(
				'post_title' => $name,
				'post_content' => '',
				'post_category' => array(),
				'tags_input' => array(),
				'post_status' => 'publish',
				'post_type' => 'wbtm_bus_booking',
			);
			//SAVE THE POST
			$pid = wp_insert_post($new_post);
			update_post_meta($pid, 'wbtm_order_id', $order_id);
			update_post_meta($pid, 'wbtm_bus_id', $bus_id);
			update_post_meta($pid, 'wbtm_user_id', $user_id);
			update_post_meta($pid, 'wbtm_boarding_point', $start);
			update_post_meta($pid, 'wbtm_next_stops', $next_stops);
			update_post_meta($pid, 'wbtm_droping_point', $end);
			update_post_meta($pid, 'wbtm_bus_start', $b_time);
			update_post_meta($pid, 'wbtm_user_start', $j_time);
			update_post_meta($pid, 'wbtm_seat', $_seats);
			update_post_meta($pid, 'wbtm_bus_fare', $fare);
			update_post_meta($pid, 'wbtm_journey_date', $j_date);
			update_post_meta($pid, 'wbtm_booking_date', $add_datetime);
			update_post_meta($pid, 'wbtm_status', $status);
			update_post_meta($pid, 'wbtm_ticket_status', 1);
			update_post_meta($pid, 'wbtm_user_name', $user_name);
			update_post_meta($pid, 'wbtm_user_email', $user_email);
			update_post_meta($pid, 'wbtm_user_phone', $user_phone);
			update_post_meta($pid, 'wbtm_user_gender', $user_gender);
			update_post_meta($pid, 'wbtm_user_address', $user_address);
			update_post_meta($pid, 'wbtm_user_extra_bag', $wbtm_extra_bag_qty);
			update_post_meta($pid, 'wbtm_user_extra_bag_price', $extra_bag_price);
			update_post_meta($pid, 'wbtm_passenger_type', $passenger_type);
			update_post_meta($pid, 'wbtm_passenger_type_num', $passenger_type_num);
			update_post_meta($pid, 'wbtm_billing_type', $wbtm_billing_type);
			update_post_meta($pid, 'wbtm_city_zone', $city_zone);
			update_post_meta($pid, 'wbtm_pickpoint', $wbtm_pickpoint);
			update_post_meta($pid, 'wbtm_user_additional', $user_additional);
			update_post_meta($pid, '_wbtm_tp', $calculated_fare);
			if ($wbtm_billing_type && $j_date && function_exists('mtsa_calculate_valid_date')) {
				$sub_end_date = mtsa_calculate_valid_date($j_date, $wbtm_billing_type);
				update_post_meta($pid, 'wbtm_sub_end_date', $sub_end_date);
			}
			if (!empty($extra_services)) {
				foreach ($extra_services as $service) {
					update_post_meta($pid, 'extra_services_type_qty_' . $service['name'], $service['qty']);
					update_post_meta($pid, 'extra_services_type_price_' . $service['name'], $service['price']);
				}
			}
			update_post_meta($pid, 'wbtm_extra_services', maybe_serialize($extra_services));
			// Custom Field
			if ($order_meta) {
				$get_custom_fields = $this->bus_get_option('custom_fields', 'general_setting_sec', 0);
				if ($get_custom_fields) {
					$get_custom_fields_arr = explode(',', $get_custom_fields);
					if ($get_custom_fields_arr) {
						foreach ($get_custom_fields_arr as $item) {
							if (isset($order_meta[$item][0])) {
								update_post_meta($pid, 'wbtm_custom_field_' . $item, $order_meta[$item][0]);
							}
							else {
								update_post_meta($pid, 'wbtm_custom_field_' . $item, null);
							}
						}
					}
				}
			}
		}
		public function bus_order_processed($order_id) {
			$order = wc_get_order($order_id);
			$order_meta = get_post_meta($order_id);
			$order_status = $order->get_status();
			if ($order_status != 'failed') {
				foreach ($order->get_items() as $item_id => $item_values) {
					$wbtm_bus_id = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_bus_id');
					if (get_post_type($wbtm_bus_id) == 'wbtm_bus') {
						$user_id = $order_meta['_customer_user'][0];
						$user_info_arr = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_passenger_info');
						$user_info_additional_arr = maybe_unserialize(MP_Global_Function::get_order_item_meta($item_id, '_wbtm_passenger_info_additional'));
						$user_single_info_arr = maybe_unserialize(MP_Global_Function::get_order_item_meta($item_id, '_wbtm_single_passenger_info'));
						$user_basic_info_arr = maybe_unserialize(MP_Global_Function::get_order_item_meta($item_id, '_wbtm_basic_passenger_info'));
						$wbtm_billing_type = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_billing_type');
						$wbtm_city_zone = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_city_zone');
						$wbtm_pickpoint = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_pickpoint');
						$extra_services = MP_Global_Function::get_order_item_meta($item_id, '_extra_services');
						$seat = MP_Global_Function::get_order_item_meta($item_id, 'Seats');
						$start = MP_Global_Function::get_order_item_meta($item_id, 'Start');
						$end = MP_Global_Function::get_order_item_meta($item_id, 'End');
						$j_date = MP_Global_Function::get_order_item_meta($item_id, 'Date');
						$j_time = MP_Global_Function::get_order_item_meta($item_id, 'Time');
						$bus_id = MP_Global_Function::get_order_item_meta($item_id, '_bus_id');
						$b_time = MP_Global_Function::get_order_item_meta($item_id, '_btime');
						$calculated_fare = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_tp');
						$seats = ($seat) ? explode(",", $seat) : null;
						$usr_inf = unserialize($user_info_arr);
						$counter = 0;
						$next_stops = maybe_serialize($this->wbtm_get_all_stops_after_this($bus_id, $start, $end));
						$extra_bag_price = get_post_meta($bus_id, 'wbtm_extra_bag_price', true) ? get_post_meta($bus_id, 'wbtm_extra_bag_price', true) : 0;
						$add_datetime = date("Y-m-d h:i:s");
						if ($seats) {
							foreach ($seats as $_seats) {
								// $fare = $this->wbtm_get_bus_price($start, $end, $price_arr, $usr_inf[$counter]['wbtm_passenger_type_num']);
								if (!empty($_seats)) {
									$fare = $user_basic_info_arr[$counter]['wbtm_seat_fare'];
									if (is_array($user_single_info_arr) && sizeof($user_single_info_arr) > 0) {
										if (isset($usr_inf[$counter]['wbtm_user_name'])) {
											if ($usr_inf[$counter]['wbtm_user_name'] != '') {
												$user_name = $usr_inf[$counter]['wbtm_user_name'];
											}
											else {
												$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
											}
										}
										else {
											$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
										}
										$passenger_type = isset($user_basic_info_arr[$counter]['wbtm_passenger_type']) ? $user_basic_info_arr[$counter]['wbtm_passenger_type'] : '';
										$passenger_type_num = isset($usr_inf[$counter]['wbtm_passenger_type_num']) ? $usr_inf[$counter]['wbtm_passenger_type_num'] : '';
										if (isset($usr_inf[$counter]['wbtm_user_email'])) {
											if ($usr_inf[$counter]['wbtm_user_email'] != '') {
												$user_email = $usr_inf[$counter]['wbtm_user_email'];
											}
											else {
												$user_email = $order_meta['_billing_email'][0];
											}
										}
										else {
											$user_email = $order_meta['_billing_email'][0];
										}
										if (isset($usr_inf[$counter]['wbtm_user_phone'])) {
											if ($usr_inf[$counter]['wbtm_user_phone'] != '') {
												$user_phone = $usr_inf[$counter]['wbtm_user_phone'];
											}
											else {
												$user_phone = $order_meta['_billing_phone'][0];
											}
										}
										else {
											$user_phone = $order_meta['_billing_phone'][0];
										}
										if (isset($usr_inf[$counter]['wbtm_user_address'])) {
											if ($usr_inf[$counter]['wbtm_user_address'] != '') {
												$user_address = $usr_inf[$counter]['wbtm_user_address'];
											}
											else {
												$user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
											}
										}
										else {
											$user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
										}
										$user_gender = isset($usr_inf[$counter]['wbtm_user_gender']) ? $usr_inf[$counter]['wbtm_user_gender'] : '';
										$user_additional = ($user_info_additional_arr ? maybe_serialize($user_info_additional_arr[$counter]) : '');
									}
									else {
										$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
										$passenger_type = isset($usr_inf[0]['wbtm_passenger_type']) ? $usr_inf[0]['wbtm_passenger_type'] : '';
										$passenger_type_num = isset($usr_inf[0]['wbtm_passenger_type_num']) ? $usr_inf[0]['wbtm_passenger_type_num'] : '';
										$user_email = $order_meta['_billing_email'][0];
										$user_phone = $order_meta['_billing_phone'][0];
										$user_address = (isset($order_meta['_billing_address_1']) ? $order_meta['_billing_address_1'][0] : '');
										$user_gender = '';
										$user_additional = '';
									}
									// echo $user_name;
									// die;
									if (isset($usr_inf[$counter]['wbtm_extra_bag_qty'])) {
										$wbtm_extra_bag_qty = $usr_inf[$counter]['wbtm_extra_bag_qty'];
										$fare = $fare + ($extra_bag_price * $wbtm_extra_bag_qty);
									}
									else {
										$wbtm_extra_bag_qty = 0;
										$extra_bag_price = 0;
									}
									// Extra Service with seat
									$extra_services_arr = maybe_unserialize($extra_services);
									// if($extra_services_arr) {
									//     foreach($extra_services_arr as $service) {
									//         $fare += $service['price'] * $service['qty'];
									//     }
									// }
									$this->create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, $_seats, $fare, $j_date, $add_datetime, $user_name, $user_email, $passenger_type, $passenger_type_num, $user_phone, $user_gender, $user_address, $wbtm_extra_bag_qty, $extra_bag_price, $usr_inf, $counter, 3, $order_meta, $wbtm_billing_type, $wbtm_city_zone, $wbtm_pickpoint, $extra_services_arr, $user_additional,$calculated_fare);
								}
								$counter++;
							}
						}
						else { // Only Extra Service
							$fare = 0;
							$extra_services_arr = maybe_unserialize($extra_services);
							if ($extra_services_arr) {
								foreach ($extra_services_arr as $service) {
									$fare += $service['price'] * $service['qty'];
								}
							}
							// Passenger Info
							if (is_array($user_single_info_arr) && sizeof($user_single_info_arr) > 0) {
								if (isset($usr_inf[$counter]['wbtm_user_name'])) {
									if ($usr_inf[$counter]['wbtm_user_name'] != '') {
										$user_name = $usr_inf[$counter]['wbtm_user_name'];
									}
									else {
										$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
									}
								}
								else {
									$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
								}
								if (isset($usr_inf[$counter]['wbtm_user_email'])) {
									if ($usr_inf[$counter]['wbtm_user_email'] != '') {
										$user_email = $usr_inf[$counter]['wbtm_user_email'];
									}
									else {
										$user_email = $order_meta['_billing_email'][0];
									}
								}
								else {
									$user_email = $order_meta['_billing_email'][0];
								}
								if (isset($usr_inf[$counter]['wbtm_user_phone'])) {
									if ($usr_inf[$counter]['wbtm_user_phone'] != '') {
										$user_phone = $usr_inf[$counter]['wbtm_user_phone'];
									}
									else {
										$user_phone = $order_meta['_billing_phone'][0];
									}
								}
								else {
									$user_phone = $order_meta['_billing_phone'][0];
								}
								if (isset($usr_inf[$counter]['wbtm_user_address'])) {
									if ($usr_inf[$counter]['wbtm_user_address'] != '') {
										$user_address = $usr_inf[$counter]['wbtm_user_address'];
									}
									else {
										$user_address = $order_meta['_billing_address_1'][0];
									}
								}
								else {
									$user_address = $order_meta['_billing_address_1'][0];
								}
								$user_gender = isset($usr_inf[$counter]['wbtm_user_gender']) ? $usr_inf[$counter]['wbtm_user_gender'] : '';
								$user_additional = ($user_info_additional_arr ? maybe_serialize($user_info_additional_arr[$counter]) : '');
							}
							else {
								$user_name = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
								$user_email = $order_meta['_billing_email'][0];
								$user_phone = $order_meta['_billing_phone'][0];
								$user_address = $order_meta['_billing_address_1'][0];
								$user_gender = '';
							}
							if (isset($usr_inf[$counter]['wbtm_extra_bag_qty'])) {
								$wbtm_extra_bag_qty = $usr_inf[$counter]['wbtm_extra_bag_qty'];
								$fare = $fare + ($extra_bag_price * $wbtm_extra_bag_qty);
							}
							else {
								$wbtm_extra_bag_qty = 0;
								$extra_bag_price = 0;
							}
							$this->create_bus_passenger($order_id, $bus_id, $user_id, $start, $next_stops, $end, $b_time, $j_time, null, $fare, $j_date, $add_datetime, $user_name, $user_email, null, null, $user_phone, $user_gender, $user_address, $wbtm_extra_bag_qty, $extra_bag_price, $usr_inf, $counter, 3, $order_meta, $wbtm_billing_type, $wbtm_city_zone, $wbtm_pickpoint, $extra_services_arr, $calculated_fare);
						}
					}
				}
			}
		}
		public function wbtm_bus_ticket_seat_management($order_id, $from_status, $to_status, $order) {
			global $wpdb;
			// Getting an instance of the order object
			$order = wc_get_order($order_id);
			$order_meta = get_post_meta($order_id);
			# Iterating through each order items (WC_Order_Item_Product objects in WC 3+)
			foreach ($order->get_items() as $item_id => $item_values) {
				$item_id = $item_id;
				$wbtm_bus_id = MP_Global_Function::get_order_item_meta($item_id, '_wbtm_bus_id');
				if (get_post_type($wbtm_bus_id) == 'wbtm_bus') {
					$bus_id = MP_Global_Function::get_order_item_meta($item_id, '_bus_id');
					if ($order->has_status('on-hold')) {
						$status = 4;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('pending')) {
						$status = 3;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('processing')) {
						$status = 1;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('cancelled')) {
						$status = 5;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('refunded')) {
						$status = 6;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('failed')) {
						$status = 7;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
					if ($order->has_status('completed')) {
						$status = 2;
						$this->update_bus_seat_status($order_id, $bus_id, $status);
					}
				}
			}
		}
		public function wbtm_get_available_seat($bus_id, $date) {
			$args = array(
				'post_type' => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'relation' => 'AND',
						array(
							'key' => 'wbtm_journey_date',
							'value' => $date,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_bus_id',
							'value' => $bus_id,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_seat',
							'value' => null,
							'compare' => '!='
						),
					),
					array(
						'relation' => 'OR',
						array(
							'key' => 'wbtm_status',
							'value' => 1,
							'compare' => '=',
						),
						array(
							'key' => 'wbtm_status',
							'value' => 2,
							'compare' => '=',
						),
					),
				),
			);
			$q = new WP_Query($args);
			$total_booking_id = $q->post_count;
			return $total_booking_id;
		}
		public function wbtm_find_product_in_cart($id) {
			$product_id = $id;
			$in_cart = false;
			foreach (WC()->cart->get_cart() as $cart_item) {
				$product_in_cart = $cart_item['product_id'];
				if ($product_in_cart === $product_id) {
					$in_cart = true;
				}
			}
			if ($in_cart) {
				return 'into-cart';
			}
			else {
				return 'not-in-cart';
			}
		}
		// Check order status based on 'Send Email on'
		public function wbtm_order_status_allow($order_status) {
			global $wbtmmain;
			$wbtm_email_status = $wbtmmain->bus_get_option('pdf_email_send_on', 'ticket_manager_settings', array());
			$return = false;
			if (!empty($wbtm_email_status) && $order_status) {
				$order_status = strtolower($order_status);
				if (in_array($order_status, $wbtm_email_status)) {
					$return = true;
				}
				else {
					$return = false;
				}
			}
			else {
				$return = false;
			}
			return $return;
		}
	}
	global $wbtmmain;
	$wbtmmain = new WBTM_Plugin_Functions();
	function wbtm_get_seat_type_label($key, $default) {
		global $wbtmmain;
		$metakey = "wbtm_seat_type_" . $key . "_label";
		return $wbtmmain->bus_get_option($metakey, '', $default);
	}
	/**
	 * The magical Datetime Function, Just call this function where you want display date or time, Pass the date or time and the format this will be return the date or time in the current wordpress saved datetime format and according the timezone.
	 */
	function get_wbtm_datetime($date, $type) {
		$date_format = get_option('date_format');
		$time_format = get_option('time_format');
		$wpdatesettings = $date_format . '  ' . $time_format;
		$timezone = wp_timezone_string();
		$timestamp = strtotime($date . ' ' . $timezone);
		if ($type == 'date') {
			return wp_date($date_format, $timestamp);
		}
		if ($type == 'date-time') {
			return wp_date($wpdatesettings, $timestamp);
		}
		if ($type == 'date-text') {
			return wp_date($date_format, $timestamp);
		}
		if ($type == 'date-time-text') {
			return wp_date($wpdatesettings, $timestamp, wp_timezone());
		}
		if ($type == 'time') {
			return wp_date($time_format, $timestamp, wp_timezone());
		}
		if ($type == 'day') {
			return wp_date('d', $timestamp);
		}
		if ($type == 'month') {
			return wp_date('M', $timestamp);
		}
	}
	function wbtm_convert_date_to_php($date) {
		$date_format = get_option('date_format');
		if ($date_format == 'Y-m-d' || $date_format == 'm/d/Y' || $date_format == 'm/d/Y') {
			if ($date_format == 'd/m/Y') {
				$date = str_replace('/', '-', $date);
			}
		}
		return date('Y-m-d', strtotime($date));
	}
	function wbtm_bus_target_page_filter_rewrite_rule() {
		add_rewrite_rule('^bus-search-list/?$', 'index.php?bussearchlist=busSearchDefault&pagename=bus-search-list', 'top');
	}
	add_action('init', 'wbtm_bus_target_page_filter_rewrite_rule');
	function wbtm_bus_target_page_query_var($vars) {
		$vars[''] = 'bussearchlist';
		return $vars;
	}
	add_filter('query_vars', 'wbtm_bus_target_page_query_var');
	function wbtm_wbtm_bus_target_page_template_chooser($template) {
		$plugin_path = plugin_dir_path(__DIR__);
		$template_name = $plugin_path . 'public/templates/bus-search-list.php';
		if (get_query_var('bussearchlist')) {
			$template = $template_name;
		}
		return $template;
	}
	add_filter('template_include', 'wbtm_wbtm_bus_target_page_template_chooser');
// Function for create hidden product for bus
	function wbtm_create_hidden_event_product($post_id, $title) {
		$new_post = array(
			'post_title' => $title,
			'post_content' => '',
			'post_name' => uniqid(),
			'post_category' => array(),
			'tags_input' => array(),
			'post_status' => 'publish',
			'post_type' => 'product',
		);
		$pid = wp_insert_post($new_post);
		update_post_meta($post_id, 'link_wc_product', $pid);
		update_post_meta($pid, 'link_wbtm_bus', $post_id);
		update_post_meta($pid, '_price', 0.01);
		update_post_meta($pid, '_sold_individually', 'yes');
		update_post_meta($pid, '_virtual', 'yes');
		$terms = array('exclude-from-catalog', 'exclude-from-search');
		wp_set_object_terms($pid, $terms, 'product_visibility');
		update_post_meta($post_id, 'check_if_run_once', true);
	}
	function wbtm_on_post_publish($post_id, $post, $update) {
		if ($post->post_type == 'wbtm_bus' && $post->post_status == 'publish' && empty(get_post_meta($post_id, 'check_if_run_once'))) {
			// ADD THE FORM INPUT TO $new_post ARRAY
			$new_post = array(
				'post_title' => $post->post_title,
				'post_content' => '',
				'post_name' => uniqid(),
				'post_category' => array(), // Usable for custom taxonomies too
				'tags_input' => array(),
				'post_status' => 'publish', // Choose: publish, preview, future, draft, etc.
				'post_type' => 'product', //'post',page' or use a custom post type if you want to
			);
			//SAVE THE POST
			$pid = wp_insert_post($new_post);
			// $product_type = mep_get_option('mep_event_product_type', 'general_setting_sec', 'yes');
			update_post_meta($post_id, 'link_wc_product', $pid);
			update_post_meta($pid, 'link_wbtm_bus', $post_id);
			update_post_meta($pid, '_price', 0.01);
			update_post_meta($pid, '_sold_individually', 'yes');
			update_post_meta($pid, '_virtual', 'yes');
			$terms = array('exclude-from-catalog', 'exclude-from-search');
			wp_set_object_terms($pid, $terms, 'product_visibility');
			update_post_meta($post_id, 'check_if_run_once', true);
		}
	}
	add_action('wp_insert_post', 'wbtm_on_post_publish', 10, 3);
	function wbtm_count_hidden_wc_product($event_id) {
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => 'link_wbtm_bus',
					'value' => $event_id,
					'compare' => '=',
				),
			),
		);
		$loop = new WP_Query($args);
		return $loop->post_count;
	}
	add_action('save_post', 'wbtm_wc_link_product_on_save', 99, 1);
	function wbtm_wc_link_product_on_save($post_id) {
		if (get_post_type($post_id) == 'wbtm_bus') {
			//   if ( ! isset( $_POST['mep_event_reg_btn_nonce'] ) ||
			//   ! wp_verify_nonce( $_POST['mep_event_reg_btn_nonce'], 'mep_event_reg_btn_nonce' ) )
			//     return;
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}
			$event_name = get_the_title($post_id);
			if (wbtm_count_hidden_wc_product($post_id) == 0 || empty(get_post_meta($post_id, 'link_wc_product', true))) {
				wbtm_create_hidden_event_product($post_id, $event_name);
			}
			$product_id = get_post_meta($post_id, 'link_wc_product', true) ? get_post_meta($post_id, 'link_wc_product', true) : $post_id;
			set_post_thumbnail($product_id, get_post_thumbnail_id($post_id));
			wp_publish_post($product_id);
			// $product_type               = mep_get_option('mep_event_product_type', 'general_setting_sec','yes');
			$_tax_status = isset($_POST['_tax_status']) ? strip_tags($_POST['_tax_status']) : 'none';
			$_tax_class = isset($_POST['_tax_class']) ? strip_tags($_POST['_tax_class']) : '';
			update_post_meta($product_id, '_tax_status', $_tax_status);
			update_post_meta($product_id, '_tax_class', $_tax_class);
			update_post_meta($product_id, '_stock_status', 'instock');
			update_post_meta($product_id, '_manage_stock', 'no');
			update_post_meta($product_id, '_virtual', 'yes');
			update_post_meta($product_id, '_sold_individually', 'yes');
			// Update post
			$my_post = array(
				'ID' => $product_id,
				'post_title' => $event_name, // new title
				'post_name' => uniqid(), // do your thing here
			);
			// unhook this function so it doesn't loop infinitely
			remove_action('save_post', 'wbtm_wc_link_product_on_save');
			// update the post, which calls save_post again
			wp_update_post($my_post);
			// re-hook this function
			add_action('save_post', 'wbtm_wc_link_product_on_save');
			// Update the post into the database
		}
	}
	add_action('parse_query', 'wbtm_product_tags_sorting_query');
	function wbtm_product_tags_sorting_query($query) {
		global $pagenow;
		$taxonomy = 'product_visibility';
		$q_vars = &$query->query_vars;
		if ($pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == 'product') {
			$tax_query = array(
				[
					'taxonomy' => 'product_visibility',
					'field' => 'slug',
					'terms' => 'exclude-from-catalog',
					'operator' => 'NOT IN',
				]
			);
			$query->set('tax_query', $tax_query);
		}
	}
	function wbtm_find_product_in_cart($return = false) {
		$product_id = get_the_id();
		$jdate = $return ? $_GET['r_date'] : $_GET['j_date'];
		$jdate = mage_wp_date($jdate, 'Y-m-d');
		$start = $return ? $_GET['bus_end_route'] : $_GET['bus_start_route'];
		$end = $return ? $_GET['bus_start_route'] : $_GET['bus_end_route'];
		$cart = WC()->cart->get_cart();
		foreach ($cart as $cart_item) {
			if (array_key_exists('wbtm_bus_id', $cart_item) && $cart_item['wbtm_bus_id'] == $product_id && $cart_item['wbtm_start_stops'] == $start && $cart_item['wbtm_end_stops'] == $end && $cart_item['wbtm_journey_date'] == $jdate) {
				return 'mage_bus_in_cart';
			}
		}
		return null;
	}
	function wbtm_find_seat_in_cart($seat_name, $return = false) {
		$product_id = get_the_id();
		$cart = WC()->cart->get_cart();
		$jdate = $return ? $_GET['r_date'] : $_GET['j_date'];
		$jdate = mage_wp_date($jdate, 'Y-m-d');
		$start = $return ? $_GET['bus_end_route'] : $_GET['bus_start_route'];
		$end = $return ? $_GET['bus_start_route'] : $_GET['bus_end_route'];
		foreach ($cart as $cart_item) {
			if (array_key_exists('wbtm_bus_id', $cart_item) && $cart_item['wbtm_bus_id'] == $product_id && $cart_item['wbtm_start_stops'] == $start && $cart_item['wbtm_end_stops'] == $end && $cart_item['wbtm_journey_date'] == $jdate) {
				foreach ($cart_item['wbtm_seats'] as $item) {
					if ($item['wbtm_seat_name'] == $seat_name) {
						return true;
					}
				}
			}
		}
		return null;
	}
	add_action('rest_api_init', 'wbtm_bus_cunstom_fields_to_rest_init');
	if (!function_exists('wbtm_bus_cunstom_fields_to_rest_init')) {
		function wbtm_bus_cunstom_fields_to_rest_init() {
			register_rest_field('wbtm_bus', 'bus_informations', array(
				'get_callback' => 'wbtm_get_bus_custom_meta_for_api',
				'schema' => null,
			));
		}
	}
	if (!function_exists('wbtm_get_bus_custom_meta_for_api')) {
		function wbtm_get_bus_custom_meta_for_api($object) {
			$post_id = $object['id'];
			$post_meta = get_post_meta($post_id);
			$post_image = get_post_thumbnail_id($post_id);
			if ($post_image) {
				$post_meta["bus_feature_image"] = $post_image ? wp_get_attachment_image_src($post_image, 'full')[0] : null;
			}
			return $post_meta;
		}
	}
	function wbtm_get_price_including_tax($bus, $price, $args = array()) {
		$args = wp_parse_args($args, array(
			'qty' => '',
			'price' => '',
		));
		$_product = get_post_meta($bus, 'link_wc_product', true) ? get_post_meta($bus, 'link_wc_product', true) : $bus;
		$qty = '' !== $args['qty'] ? max(0.0, (float)$args['qty']) : 1;
		$product = wc_get_product($_product);
		$tax_with_price = get_option('woocommerce_tax_display_shop');
		if ('' === $price) {
			return '';
		}
		elseif (empty($qty)) {
			return 0.0;
		}
		$line_price = (float)$price * $qty;
		$return_price = $line_price;
		if ($product) {
			if ($product->is_taxable()) {
				if (!wc_prices_include_tax()) {
					// echo get_option( 'woocommerce_prices_include_tax' );
					$tax_rates = WC_Tax::get_rates($product->get_tax_class());
					$taxes = WC_Tax::calc_tax($line_price, $tax_rates, false);
					// print_r($tax_rates);
					if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
						$taxes_total = array_sum($taxes);
					}
					else {
						$taxes_total = array_sum(array_map('wc_round_tax_total', $taxes));
					}
					$return_price = $tax_with_price == 'excl' ? round($line_price, wc_get_price_decimals()) : round($line_price + $taxes_total, wc_get_price_decimals());
				}
				else {
					$tax_rates = WC_Tax::get_rates($product->get_tax_class());
					$base_tax_rates = WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));
					/**
					 * If the customer is excempt from VAT, remove the taxes here.
					 * Either remove the base or the user taxes depending on woocommerce_adjust_non_base_location_prices setting.
					 */
					if (!empty(WC()->customer) && WC()->customer->get_is_vat_exempt()) { // @codingStandardsIgnoreLine.
						$remove_taxes = apply_filters('woocommerce_adjust_non_base_location_prices', true) ? WC_Tax::calc_tax($line_price, $base_tax_rates, true) : WC_Tax::calc_tax($line_price, $tax_rates, true);
						if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
							$remove_taxes_total = array_sum($remove_taxes);
						}
						else {
							$remove_taxes_total = array_sum(array_map('wc_round_tax_total', $remove_taxes));
						}
						// $return_price = round( $line_price, wc_get_price_decimals() );
						$return_price = round($line_price - $remove_taxes_total, wc_get_price_decimals());
						/**
						 * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
						 * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
						 * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
						 */
					}
					else {
						$base_taxes = WC_Tax::calc_tax($line_price, $base_tax_rates, true);
						$modded_taxes = WC_Tax::calc_tax($line_price - array_sum($base_taxes), $tax_rates, false);
						if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
							$base_taxes_total = array_sum($base_taxes);
							$modded_taxes_total = array_sum($modded_taxes);
						}
						else {
							$base_taxes_total = array_sum(array_map('wc_round_tax_total', $base_taxes));
							$modded_taxes_total = array_sum(array_map('wc_round_tax_total', $modded_taxes));
						}
						$return_price = $tax_with_price == 'excl' ? round($line_price - $base_taxes_total, wc_get_price_decimals()) : round($line_price - $base_taxes_total + $modded_taxes_total, wc_get_price_decimals());
					}
				}
			}
		}
		// return 0;
		return apply_filters('woocommerce_get_price_including_tax', $return_price, $qty, $product);
	}
	add_action('manage_wbtm_bus_posts_columns', 'wbtm_posts_column_callback', 5, 2);
	function wbtm_posts_column_callback($column) {
		$name = WBTM_Functions::get_name();
		$date = $column['date'];
		$bus_cat = $column['taxonomy-wbtm_bus_cat'];
		// Remove
		unset($column['taxonomy-wbtm_bus_pickpoint']);
		unset($column['taxonomy-wbtm_bus_stops']);
		unset($column['taxonomy-mtsa_city_zone']);
		unset($column['date']);
		unset($column['taxonomy-wbtm_bus_cat']);
		$column['wbtm_coach_no'] = __('Coach no', 'bus-ticket-booking-with-seat-reservation');
		$column['wbtm_bus_type'] = $name . ' ' . __('Type', 'bus-ticket-booking-with-seat-reservation');
		$column['taxonomy-wbtm_bus_cat'] = __('Category', 'bus-ticket-booking-with-seat-reservation');
		if (is_plugin_active('bus-marketplace-addon/bus-marketplace.php')) {
			$column['wbtm_added_by'] = __('Added by', 'bus-ticket-booking-with-seat-reservation');
		}
		$column['date'] = $date;
		return $column;
	}
	add_action('manage_wbtm_bus_posts_custom_column', 'wbtm_posts_custom_column_callback', 5, 2);
	function wbtm_posts_custom_column_callback($column, $post_id) {
		switch ($column) {
			case 'wbtm_coach_no':
				echo "<span class=''>" . get_post_meta($post_id, 'wbtm_bus_no', true) . "</span>";
				break;
			case 'wbtm_bus_type':
				echo "<span class=''>" . wbtm_bus_type($post_id) . "</span>";
				break;
			case 'wbtm_added_by':
				$user_id = get_post_field('post_author', $post_id);
				echo "<span class=''>" . get_the_author_meta('display_name', $user_id) . ' [' . wbtm_get_user_role($user_id) . "]</span>";
				break;
		}
	}
	add_filter('manage_edit-wbtm_bus_stops_columns', 'wbtm_bus_stops_custom_column');
	function wbtm_bus_stops_custom_column($columns) {
		$columns['id'] = 'ID';
		return $columns;
	}
	add_filter('manage_wbtm_bus_stops_custom_column', 'wbtm_bus_stops_custom_column_callback', 10, 3);
	function wbtm_bus_stops_custom_column_callback($content, $column_name, $term_id) {
		switch ($column_name) {
			case 'id':
				$content = $term_id;
				break;
		}
		return $content;
	}
	
	function mage_bus_isset($parameter) {
		return isset($_GET[$parameter]) ? strip_tags($_GET[$parameter]) : false;
	}
	function mage_bus_label($var, $text, $is_return = false) {
		global $wbtmmain;
		if ($is_return) {
			return $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
		}
		else {
			echo $wbtmmain->bus_get_option($var, 'label_setting_sec') ? $wbtmmain->bus_get_option($var, 'label_setting_sec') : $text;
		}
	}
// check search day is off?
	function mage_check_search_day_off($id, $j_date, $return = false) {
		$db_day_prefix = 'offday_';
		if ($j_date) {
			$same_bus_return_setting_global = mage_bus_setting_value('same_bus_return_setting', 'disable');
			if ($same_bus_return_setting_global === 'enable') {
				$is_same_bus_return_allow = get_post_meta($id, 'wbtm_general_same_bus_return', true);
				$return_text = $return && $is_same_bus_return_allow === 'yes' ? '_return' : '';
			}
			else {
				$return_text = '';
			}
			$j_date_day = strtolower(date('D', strtotime($j_date)));
			$get_day = get_post_meta($id, $db_day_prefix . $j_date_day . $return_text, true);
			$get_day = ($get_day != null) ? strtolower($get_day) : null;
			if ($get_day == 'yes') {
				return true;
			}
			else {
				return false;
			}
		}
		else {
			return false;
		}
	}
// check search day is off? (NEW)
	function mage_check_search_day_off_new($id, $j_date, $return = false) {
		$get_day = null;
		if (get_post_meta($id, 'show_off_day', true) !== 'yes') {
			return false;
		}
		$db_day_prefix = 'offday_';
		$weekly_offday = get_post_meta($id, 'weekly_offday', true) ?: array();
		if ($j_date) {
			$same_bus_return_setting_global = mage_bus_setting_value('same_bus_return_setting', 'disable');
			if ($same_bus_return_setting_global === 'enable' && $return) {
				$weekly_offday = get_post_meta($id, 'weekly_offday_return', true) ?: array();
				$j_date_day = strtolower(date('N', strtotime($j_date)));
				if (in_array($j_date_day, $weekly_offday)) {
					$get_day = 'yes';
				}
			}
			else {
				$j_date_day = strtolower(date('N', strtotime($j_date)));
				if (in_array($j_date_day, $weekly_offday)) {
					$get_day = 'yes';
				}
			}
			if ($get_day == 'yes') {
				return true;
			}
			else {
				return false;
			}
		}
		else {
			return false;
		}
	}
// check bus on Date
	function mage_search_bus_query($return, $start = false, $end = false) {
		if (!$start) {
			$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		}
		if (!$end) {
			$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		}
		$args = array(
			'post_type' => array('wbtm_bus'),
			// 'p' => 2622, // TEST
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'meta_value',
			// 'meta_key' => 'wbtm_bus_start_time',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key' => 'wbtm_bus_bp_stops',
						'value' => $start,
						'compare' => 'LIKE',
					),
					array(
						'key' => 'wbtm_bus_bp_stops_return',
						'value' => $start,
						'compare' => 'LIKE',
					)
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'wbtm_bus_next_stops',
						'value' => $end,
						'compare' => 'LIKE',
					),
					array(
						'key' => 'wbtm_bus_next_stops_return',
						'value' => $end,
						'compare' => 'LIKE',
					)
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'wbtm_seat_type_conf',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key' => 'wbtm_seat_type_conf',
						'value' => 'wbtm_seat_plan',
						'compare' => '=',
					),
					array(
						'key' => 'wbtm_seat_type_conf',
						'value' => 'wbtm_without_seat_plan',
						'compare' => '=',
					),
				)
			)
		);
		if (apply_filters('wbtm_specific_bus_in_search_query', array())) {
			$args['post__in'] = apply_filters('wbtm_specific_bus_in_search_query', array());
		}
		// echo '<pre>'; print_r($args); die;
		return $args;
	}
	/*
	* Mainly working for Bus search result
	* To check bus search end point has before search start point in the Bus Boarding points array
	* if return false, then its ok for being in search result
	* if return true, then its not ok for being in search result
	* @param1 search start point
	* @param2 search end point
	* @param3 The Bus Boarding points array
	* @return Bool
	*/
	function mage_bus_end_has_prev($start, $end, $boarding_array) {
		$s = $e = '';
		$strict = 2;
		if ($end && $start && is_array($boarding_array) && !empty($boarding_array)) {
			$s = $start;
			$e = $end;
			$rearrange_array = array_column($boarding_array, 'wbtm_bus_bp_stops_name');
			$start_pos = array_search($s, $rearrange_array);
			$end_pos = array_search($e, $rearrange_array);
			if ($end_pos === 0) {
				$strict = 3;
			}
			if ($end_pos == false && is_bool($end_pos)) {
				return false;
			}
			else {
				if ($end_pos > $start_pos && !is_bool($start_pos)) {
					return false; // Ok
				}
				else {
					return true;
				}
			}
		}
		return true; // Not ok
	}
	/*
	* Mainly working for Bus search result
	* To check bus search start point has after search end point in the Bus Dropping points array
	* if return false, then its ok for being in search result
	* if return true, then its not ok for being in search result
	* @param1 search start point
	* @param2 search end point
	* @param3 The Bus Boarding points array
	* @return Bool
	*/
	function mage_bus_start_has_next($start, $end, $dropping_array) {
		$s = $e = '';
		$strict = 2;
		$strict2 = 2;
		if ($end && $start && is_array($dropping_array) && !empty($dropping_array)) {
			$s = $start;
			$e = $end;
			$rearrange_array = array_column($dropping_array, 'wbtm_bus_next_stops_name');
			$start_pos = array_search($s, $rearrange_array);
			$end_pos = array_search($e, $rearrange_array);
			// return $end_pos.' '.$start_pos;
			if ($end_pos === 0) {
				$strict = 3;
			}
			// if($start_pos === 0) {
			//     $strict2 = 3;
			// }
			if ($end_pos == false && is_bool($end_pos)) {
				return false;
			}
			else {
				if ($end_pos > $start_pos && !is_bool($start_pos)) {
					return false; // Ok
				}
				else {
					return true;
				}
			}
		}
		return true; // Not ok
	}
	function mage_bus_title() {
		$label=WBTM_Functions::get_name();
		?>
		<div class="dFlex mage_flex_mediumRadiusTop mage_bus_list_title ">
			<div class="mage_bus_img flexCenter">
				<h6><?php esc_html_e('Image', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
			</div>
			<div class="mage_bus_info flexEqual flexCenter">
				<div class="flexEqual">
					<h6><?php echo esc_html($label) .' '. esc_html__('Name', 'bus-ticket-booking-with-seat-reservation'); ?></h6>
					<h6 class="mage_hidden_xxs"><?php mage_bus_label('wbtm_schedule_text', __('Schedule', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
				</div>
				<div class="flexEqual flexCenter textCenter">
					<h6 class="mage_hidden_xxs"><?php mage_bus_label('wbtm_type_text', __('Coach Type', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
					<h6 class="mage_hidden_xs"><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
					<h6 class="mage_hidden_md"><?php mage_bus_label('wbtm_seats_available_text', __('Available', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
					<h6><?php mage_bus_label('wbtm_view_text', __('Action', 'bus-ticket-booking-with-seat-reservation')); ?></h6>
				</div>
			</div>
		</div>
		<?php
	}
	function mage_get_bus_seat_plan_type() {
		$id = get_the_id();
		$seat_cols = get_post_meta($id, 'wbtm_seat_cols', true);
		$seats = get_post_meta($id, 'wbtm_bus_seats_info', true);
		if ($seat_cols && $seat_cols > 0 && is_array($seats) && sizeof($seats) > 0) {
			return (int)$seat_cols;
		}
		else {
			$current_plan = get_post_meta($id, 'seat_plan', true);
			$bus_meta = get_post_custom($id);
			if (array_key_exists('wbtm_seat_col', $bus_meta)) {
				$seat_col = $bus_meta['wbtm_seat_col'][0];
				$seat_col_arr = explode(",", $seat_col);
				$seat_column = count($seat_col_arr);
			}
			else {
				$seat_column = 0;
			}
			if ($current_plan) {
				$current_seat_plan = $current_plan;
			}
			else {
				if ($seat_column == 4) {
					$current_seat_plan = 'seat_plan_1';
				}
				else {
					$current_seat_plan = 'seat_plan_2';
				}
			}
			return $current_seat_plan;
		}
	}
//bus off date check
	function mage_bus_off_date_check($return) {
		$start_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_start', true));
		$end_date = strtotime(get_post_meta(get_the_id(), 'wbtm_od_end', true));
		$date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));
		return (($start_date <= $date) && ($end_date >= $date)) ? false : true;
	}
//bus off date check
	function mage_bus_off_day_check($return) {
		$current_day = 'offday_' . strtolower(date('D', strtotime($return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date')))));
		return get_post_meta(get_the_id(), $current_day, true) == 'yes' ? false : true;
	}
//bus setting on date
	function mage_bus_on_date_setting_check($return) {
		$mage_bus_on_dates = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_on_dates', true));
		$date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));
		$mage_bus_on = array();
		if (!empty($mage_bus_on_dates) && is_array($mage_bus_on_dates)) {
			foreach ($mage_bus_on_dates as $value) {
				$mage_bus_on[] = $value['wbtm_on_date_name'];
			}
			return in_array($date, $mage_bus_on) ? true : false;
		}
		else {
			return false;
		}
	}
//buffer time check
	function mage_buffer_time_check($return) {
		$date = wbtm_convert_date_to_php(mage_bus_isset($return ? 'r_date' : 'j_date'));
		$buffer_time = mage_bus_setting_value('bus_buffer_time', 0);
		$start_time = strtotime($date . ' ' . date('H:i:s', strtotime(mage_bus_time($return, false))));
		$current_time = strtotime(current_time('Y-m-d H:i:s'));
		$dif = round(($start_time - $current_time) / 3600, 1);
		return ($dif >= $buffer_time) ? true : false;
	}
//return bus time
	function mage_bus_time($return, $dropping) {
		if ($dropping) {
			$start = mage_bus_isset($return ? 'bus_start_route' : 'bus_end_route');
		}
		else {
			$start = mage_bus_isset($return ? 'bus_end_route' : 'bus_start_route');
		}
		$determine_route = mage_determine_route(get_the_id(), $return);
		if ($determine_route == 'wbtm_bus_bp_stops') {
			$meta_key = $dropping ? 'wbtm_bus_next_stops' : 'wbtm_bus_bp_stops';
		}
		else {
			$meta_key = $dropping ? 'wbtm_bus_next_stops_return' : 'wbtm_bus_bp_stops_return';
		}
		$return = false;
		$array_key = $dropping ? 'wbtm_bus_next_stops_name' : 'wbtm_bus_bp_stops_name';
		$array_value = $dropping ? 'wbtm_bus_next_end_time' : 'wbtm_bus_bp_start_time';
		$array = maybe_unserialize(get_post_meta(get_the_id(), $meta_key, true));
		if ($array) {
			foreach ($array as $key => $val) {
				if ($val[$array_key] == $start) {
					$return = $val[$array_value];
					break;
				}
			}
		}
		return $return;
	}
//return setting value
	function mage_bus_setting_value($key, $default = null) {
		$settings = get_option('wbtm_bus_settings');
		$val = isset($settings[$key]) ? $settings[$key] : null;
		return $val ? $val : $default;
	}
//return check bus on off
	function mage_bus_run_on_date($return) {
		if (((mage_bus_off_date_check($return) && mage_bus_off_day_check($return)) || mage_bus_on_date_setting_check($return)) && mage_buffer_time_check($return)) {
			return true;
		}
		return false;
	}
//bus type return (ac/non ac)
	function mage_bus_type($id = null) {
		$bus_id = ($id ? $id : get_the_ID());
		return get_the_terms($bus_id, 'wbtm_bus_cat') ? get_the_terms($bus_id, 'wbtm_bus_cat')[0]->name : '';
	}
// bus total seat
	function mage_bus_total_seat_new($bus_id = '') {
		$id = $bus_id ? $bus_id : get_the_ID();
		$seat_type_conf = get_post_meta($id, 'wbtm_seat_type_conf', true);
		$total_seat = 0;
		if ($seat_type_conf == 'wbtm_seat_plan') {
			$seats_rows = get_post_meta($id, 'wbtm_bus_seats_info', true);
			$seat_col = get_post_meta($id, 'wbtm_seat_cols', true);
			if ($seats_rows && $seat_col) {
				foreach ($seats_rows as $seat) {
					for ($i = 1; $i <= (int)$seat_col; $i++) {
						$seat_name = strtolower($seat["seat" . $i]);
						if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
							$total_seat++;
						}
					}
				}
				$seats_dd = get_post_meta($id, 'wbtm_bus_seats_info_dd', true);
				$seat_col_dd = get_post_meta($id, 'wbtm_seat_cols_dd', true);
				if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
					foreach ($seats_dd as $seat) {
						for ($i = 1; $i <= $seat_col_dd; $i++) {
							$seat_name = $seat["dd_seat" . $i] ?? '';
							if ($seat_name != 'door' && $seat_name != 'wc' && $seat_name != '') {
								$total_seat++;
							}
						}
					}
				}
			}
		}
		else {
			$total_seat = get_post_meta($id, 'wbtm_total_seat', true);
		}
		return $total_seat;
	}
//sold seat return
//seat price
	function mage_bus_seat_price($bus_id, $start, $end, $dd, $seat_type = null, $return_price = false, $count = 0) {
		$flag = false;
		$price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));
		if (!empty($price_arr) && is_array($price_arr)) {
			foreach ($price_arr as $value) {
				if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
					$flag = true;
					break;
				}
			}
		}
		else {
			$flag = false;
		}
		if (!$flag) {
			$price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices_return', true));
			if (!empty($price_arr) && is_array($price_arr)) {
				foreach ($price_arr as $value) {
					if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
						$flag = true;
						break;
					}
				}
			}
			if (!$flag) {
				return false;
			}
		}
		$return_price_data = false;
		if ($flag) {
			$seat_dd_increase = (int)get_post_meta($bus_id, 'wbtm_seat_dd_price_parcent', true);
			// $seat_dd_increase = 10;
			$dd_price_increase = ($dd && $seat_dd_increase) ? $seat_dd_increase : 0;
			foreach ($price_arr as $key => $val) {
				$p_start = strtolower($val['wbtm_bus_bp_price_stop']);
				$p_end = strtolower($val['wbtm_bus_dp_price_stop']);
				$start = strtolower($start);
				$end = strtolower($end);
				if ($p_start === $start && $p_end === $end && !$return_price) { // Not return
					if (1 == $seat_type) {
						$price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);
					}
					elseif (2 == $seat_type) {
						$price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);
					}
					elseif (3 == $seat_type) {
						$price = $val['wbtm_bus_special_price'] + ($val['wbtm_bus_special_price'] * $dd_price_increase / 100);
					}
					else {
						$price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
					}
					$return_price_data = $price;
					break;
				}
				if ($p_start === $start && $p_end === $end && $return_price) { // Return
					if (1 == $seat_type) {
						$p = (($val['wbtm_bus_child_price_return']) ?: $val['wbtm_bus_child_price']);
						$price = $p + ($p * $dd_price_increase / 100);
					}
					elseif (2 == $seat_type) {
						$p = (($val['wbtm_bus_infant_price_return']) ?: $val['wbtm_bus_infant_price']);
						$price = $p + ($p * $dd_price_increase / 100);
					}
					elseif (3 == $seat_type) {
						$p = (($val['wbtm_bus_special_price']) ?: 0);
						$price = $p + ($p * $dd_price_increase / 100);
					}
					else {
						$p = (($val['wbtm_bus_price_return']) ?: $val['wbtm_bus_price']);
						$price = $p + ($p * $dd_price_increase / 100);
					}
					$return_price_data = $price;
					break;
				}
			}
			return $return_price_data;
		}
	}
	function mage_bus_seat_prices($bus_id, $start, $end) {
		$flag = false;
		$price_arr = array();
		$price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices', true));
		if (!empty($price_arr) && is_array($price_arr)) {
			foreach ($price_arr as $value) {
				if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
					$flag = true;
					break;
				}
			}
		}
		else {
			$flag = false;
		}
		if (!$flag) {
			$price_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_prices_return', true));
			if (!empty($price_arr) && is_array($price_arr)) {
				foreach ($price_arr as $value) {
					if ((strtolower($value['wbtm_bus_bp_price_stop']) == strtolower($start)) && (strtolower($value['wbtm_bus_dp_price_stop']) == strtolower($end))) {
						$flag = true;
						break;
					}
				}
			}
			if (!$flag) {
				return false;
			}
		}
		// With Tax
		// $return_price_data = wc_price(wbtm_get_price_including_tax($bus_id, $total_fare));
		return $price_arr;
	}
	function mage_bus_passenger_type($return, $dd) {
		$id = get_the_id();
		$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		// $price_arr = maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
		$price_arr = mage_bus_seat_prices($id, $start, $end);
		$seat_panel_settings = get_option('wbtm_bus_settings');
		$adult_label = mage_bus_setting_value('wbtm_seat_type_adult_label');
		$child_label = mage_bus_setting_value('wbtm_seat_type_child_label');
		$infant_label = mage_bus_setting_value('wbtm_seat_type_infant_label');
		$special_label = mage_bus_setting_value('wbtm_seat_type_special_label');
		if ($price_arr) {
			foreach ($price_arr as $key => $val) {
				if (strtolower($val['wbtm_bus_bp_price_stop']) === strtolower($start) && strtolower($val['wbtm_bus_dp_price_stop']) === strtolower($end)) {
					// if (mage_bus_multiple_passenger_type_check($id, $start, $end)) {
					$dd_price_increase = 0;
					if ($dd) {
						$seat_dd_increase = (int)get_post_meta($id, 'wbtm_seat_dd_price_parcent', true);
						$dd_price_increase = $seat_dd_increase ? $seat_dd_increase : 0;
					}
					?>
					<div class="passenger_type_list">
						<ul>
							<?php
								if ($val['wbtm_bus_price'] !== '') {
									$price = $val['wbtm_bus_price'] + ($val['wbtm_bus_price'] * $dd_price_increase / 100);
									echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="' . $adult_label . '">' . $adult_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
								if ($val['wbtm_bus_child_price'] != '') {
									$price = $val['wbtm_bus_child_price'] + ($val['wbtm_bus_child_price'] * $dd_price_increase / 100);
									echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="' . $child_label . '">' . $child_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
								if ($val['wbtm_bus_infant_price'] != '') {
									$price = $val['wbtm_bus_infant_price'] + ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100);
									echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="' . $infant_label . '">' . $infant_label . ' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
								// if ($val['wbtm_bus_special_price'] > 0) {
								//     $price = $val['wbtm_bus_special_price'] + ($val['wbtm_bus_special_price'] * $dd_price_increase / 100);
								//     echo '<li data-seat-price="' . $price . '" data-seat-type="3" data-seat-label="'. $special_label .'">' . $special_label.' ' . wc_price($price) . __('/Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								// }
							?>
						</ul>
					</div>
					<?php
					// }
				}
			}
		}
	}
	function mage_bus_passenger_type_admin($return, $dd) {
		global $wbtmmain;
		$id = get_the_id();
		$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		$price_arr = $return ? maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices_return', true)) : maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
		$seat_panel_settings = get_option('wbtm_bus_settings');
		$adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
		$child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
		$infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
		$special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
		$rdate = isset($_GET['r_date']) ? sanitize_text_field($_GET['r_date']) : date('Y-m-d');
		if (isset($_GET['j_date'])) {
			$rdate = $return ? sanitize_text_field($_GET['r_date']) : sanitize_text_field($_GET['j_date']);
		}
		else {
			$rdate = date('Y-m-d');
		}
		$uid = get_the_id() . $wbtmmain->wbtm_make_id($rdate);
		foreach ($price_arr as $key => $val) {
			if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
				if (mage_bus_multiple_passenger_type_check($id, $start, $end, $return)) {
					$dd_price_increase = 0;
					if ($dd) {
						$seat_dd_increase = (int)get_post_meta($id, 'wbtm_seat_dd_price_parcent', true);
						$dd_price_increase = $seat_dd_increase ? $seat_dd_increase : 0;
					}
					?>
					<div class="<?php echo 'admin_' . $uid; ?> admin_passenger_type_list">
						<ul>
							<?php
								if ($val['wbtm_bus_price'] > 0) {
									$price = $val['wbtm_bus_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_price'] * $dd_price_increase / 100) : 0);
									echo '<li data-seat-price="' . $price . '" data-seat-type="0" data-seat-label="' . $adult_label . '">' . $adult_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
								if ($val['wbtm_bus_child_price'] > 0) {
									$price = $val['wbtm_bus_child_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_child_price'] * $dd_price_increase / 100) : 0);
									echo '<li data-seat-price="' . $price . '" data-seat-type="1" data-seat-label="' . $child_label . '">' . $child_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
								if ($val['wbtm_bus_infant_price'] > 0) {
									$price = $val['wbtm_bus_infant_price'] + ($dd_price_increase != 0 ? ($val['wbtm_bus_infant_price'] * $dd_price_increase / 100) : 0);
									echo '<li data-seat-price="' . $price . '" data-seat-type="2" data-seat-label="' . $infant_label . '">' . $infant_label . ' ' . wc_price($price) . __('/ Seat', 'bus-ticket-booking-with-seat-reservation') . '</li>';
								}
							?>
						</ul>
					</div>
					<?php
				}
			}
		}
	}
	function mage_bus_multiple_passenger_type_check($id, $start, $end, $return = false) {
		$price_arr = $return ? maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices_return', true)) : maybe_unserialize(get_post_meta($id, 'wbtm_bus_prices', true));
		foreach ($price_arr as $key => $val) {
			if ($val['wbtm_bus_bp_price_stop'] === $start && $val['wbtm_bus_dp_price_stop'] === $end) {
				if ($val['wbtm_bus_price'] && ($val['wbtm_bus_child_price'] || $val['wbtm_bus_infant_price'])) {
					return true;
				}
			}
		}
		return false;
	}
// Get seat Booking Data
	function get_seat_booking_data($seat_name, $search_start, $search_end, $all_stopages_name, $return, $bus_id = null, $start = null, $end = null, $date = null) {
		if (!$seat_name) {
			return false;
		}
		// Return
		$data = array(
			'status' => null,
			'has_booked' => false
		);
		if (!$start) {
			$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		}
		if (!$end) {
			$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		}
		if (!$date) {
			$date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
		}
		$j_dates = array(mage_wp_date($date, 'Y-m-d'));
		$bus_id = $bus_id ? $bus_id : get_the_id();
		$bus_start_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points
		// If trip is midnight trip
		if (mage_bus_is_midnight_trip($bus_start_stops_arr, $start, $end)) {
			$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
			array_push($j_dates, $prev_date);
		}
		// Seat booked show policy in search
		$seat_booked_status_default = array(1, 2);
		$seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);
		$args = array(
			'post_type' => 'wbtm_bus_booking',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'relation' => 'AND',
					array(
						'key' => 'wbtm_seat',
						'value' => $seat_name,
						'compare' => '='
					),
					array(
						'key' => 'wbtm_journey_date',
						'value' => $j_dates,
						'compare' => 'IN'
					),
					array(
						'key' => 'wbtm_bus_id',
						'value' => $bus_id,
						'compare' => '='
					),
					array(
						'key' => 'wbtm_status',
						'value' => $seat_booked_status,
						'compare' => 'IN'
					),
				)
			),
		);
		$q = new WP_Query($args);
		//     echo $date.'<br>';
		//     echo $q->found_posts.'<br>';
		if ($q->found_posts > 0) {
			foreach ($q->posts as $post) {
				$data['status'] = null;
				$data['has_booked'] = false;
				$bid = $post->ID;
				$boarding = get_post_meta($bid, 'wbtm_boarding_point', true);
				$dropping = get_post_meta($bid, 'wbtm_droping_point', true);
				$status = get_post_meta($bid, 'wbtm_status', true);
				$get_seat_boarding_position = array_search($boarding, $all_stopages_name);
				$get_seat_droping_position = array_search($dropping, $all_stopages_name);
				$get_seat_droping_position = (is_bool($get_seat_droping_position) && !$get_seat_droping_position ? count($all_stopages_name) : $get_seat_droping_position); // Last Stopage position assign
				// echo $get_seat_boarding_position.'<br>';
				// echo $search_start.'<br>';
				// echo $get_seat_droping_position.'<br>';
				// echo $search_end.'<br>';
				if (($get_seat_boarding_position > $search_start) && ($get_seat_boarding_position >= $search_end)) {
					$data['status'] = $status;
					$data['has_booked'] = false;
				}
				elseif (($search_start >= $get_seat_droping_position) && ($search_end > $get_seat_droping_position)) {
					$data['status'] = $status;
					$data['has_booked'] = false;
				}
				else {
					$data['status'] = $status;
					$data['has_booked'] = true;
					break;
				}
			}
		}
		return $data;
	}
	function mage_partial_without_seat_booked_count($return = false, $bus_id = null, $start = null, $end = null, $date = null) {
		$sold_seats = 0;
		$midnight_sold_seats = 0;
		$midnight_trip_check = false;
		$bus_id = $bus_id ? $bus_id : get_the_ID();
		if (!$date) {
			$date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
		}
		$j_dates = array(mage_wp_date($date, 'Y-m-d'));
		if (!$start) {
			$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		}
		if (!$end) {
			$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		}
		$bus_start_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points
		$bus_end_stops_arr = maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true)); // $bus_id bus end points
		// If trip is midnight trip
		if (mage_bus_is_midnight_trip($bus_start_stops_arr, $start, $end)) {
			$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
			array_push($j_dates, $prev_date);
		}
		// Seat booked show policy in search
		$seat_booked_status_default = array(1, 2);
		$seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);
		if ($bus_start_stops_arr && $bus_end_stops_arr) {
			$bus_stops = array_column($bus_start_stops_arr, 'wbtm_bus_bp_stops_name'); // remove time
			$bus_ends = array_column($bus_end_stops_arr, 'wbtm_bus_next_stops_name'); // remove time
			$bus_stops_merge = array_merge($bus_stops, $bus_ends); // Bus start and stop merge
			$bus_stops_unique = array_values(array_unique($bus_stops_merge)); // Make stops unique
			$sp = array_search($start, $bus_stops_unique); // Get search start position in all bus stops
			$ep = array_search($end, $bus_stops_unique); // Get search end position in all bus stops
			$f = mage_array_slice($bus_stops_unique, 0, $sp + 1);
			$l = mage_array_slice($bus_stops_unique, $ep, (count($bus_stops_unique) - 1));
			$where = mage_intermidiate_available_seat_condition($start, $end, $bus_stops_unique);
			// echo '<pre>';print_r($where);die;
			$args = array(
				'post_type' => 'wbtm_bus_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'relation' => 'AND',
						$where,
						array(
							'key' => 'wbtm_seat',
							'value' => '',
							'compare' => '!='
						),
						array(
							'key' => 'wbtm_journey_date',
							'value' => $j_dates,
							'compare' => 'IN'
						),
						array(
							'key' => 'wbtm_bus_id',
							'value' => $bus_id,
							'compare' => '='
						),
						array(
							'key' => 'wbtm_status',
							'value' => $seat_booked_status,
							'compare' => 'IN'
						),
					)
				),
			);
			$q = new WP_Query($args);
			$sold_seats = $q->found_posts;
		}
		return $sold_seats + $midnight_sold_seats;
	}
	function mage_bus_is_midnight_trip($bus_start_stops_arr, $start = null, $end = null) {
		$return = false;
		$start_point = '';
		$start_point_time = '';
		$boarding_point = '';
		$boarding_point_time = '';
		if ($bus_start_stops_arr) {
			$i = 0;
			foreach ($bus_start_stops_arr as $stops) {
				if ($i == 0) {
					$start_point = $stops['wbtm_bus_bp_stops_name']; // Start Point
					$start_point_time = $stops['wbtm_bus_bp_start_time']; // Start Point
				}
				if ($start) { // Get $start data
					if ($stops['wbtm_bus_bp_stops_name'] == $start) {
						$boarding_point = $stops['wbtm_bus_bp_stops_name']; // Boarding Point
						$boarding_point_time = $stops['wbtm_bus_bp_start_time']; // Boarding Point
						break;
					}
				}
				else { // Get last data of Array
					if ((count($bus_start_stops_arr) - 1) == $i) {
						$boarding_point = $stops['wbtm_bus_bp_stops_name']; // Boarding Point
						$boarding_point_time = $stops['wbtm_bus_bp_start_time']; // Boarding Point
						break;
					}
				}
				$i++;
			}
			// Start Time
			$start_hour = '';
			if ($start_point_time) {
				$start_hour = explode(':', $start_point_time);
				$start_hour = $start_hour ? (int)$start_hour[0] : null;
			}
			// Start Time
			$boarding_hour = '';
			if ($boarding_point_time) {
				$boarding_hour = explode(':', $boarding_point_time);
				$boarding_hour = $boarding_hour ? (int)$boarding_hour[0] : null;
			}
			// Check date is changed
			if ($start_hour && $boarding_hour) {
				if (($start_hour > $boarding_hour) || ($boarding_hour == 24)) {
					$return = true;
				}
			}
		}
		return $return;
	}
	function check_bus_is_return($bus_id, $boarding, $dropping, $bus_start_point_arr = null, $bus_end_point_arr = null) {
		$is_return = false;
		$bus_start_point_arr = $bus_start_point_arr ? $bus_start_point_arr : get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
		$bus_end_point_arr = $bus_end_point_arr ? $bus_end_point_arr : get_post_meta($bus_id, 'wbtm_bus_next_stops', true);
		if ($bus_start_point_arr && $bus_end_point_arr) {
			$bus_start_point_flat = array_column($bus_start_point_arr, 'wbtm_bus_bp_stops_name');
			$bus_end_point_flat = array_column($bus_end_point_arr, 'wbtm_bus_next_stops_name');
			$all_stops = array_unique(array_merge($bus_start_point_flat, $bus_end_point_flat), SORT_REGULAR); // all stopage but unique
			$boarding_pos = array_search($boarding, $all_stops); // boarding position
			$dropping_pos = array_search($dropping, $all_stops); // dropping position
			if ($dropping_pos < $boarding_pos) {
				$is_return = true;
			}
		}
		return $is_return;
	}
// Get Boarding and Dropping date (also midnigh trip)
	function mage_get_bus_stops_date($bus_id, $date, $boarding, $dropping, $return = false) {
		$return_text = $return ? 'return_' : '';
		$boarding_point_time = '';
		$dropping_point_time = '';
		$date = mage_date_format_issue($date);
		$data = array(
			'boarding' => $date,
			'boarding_time' => null,
			'dropping' => $date,
			'dropping_time' => null
		);
		// check is bus is return
		$is_same_bus_return_allow = get_post_meta($bus_id, 'wbtm_general_same_bus_return', true);
		$is_return = ($is_same_bus_return_allow == 'yes' ? check_bus_is_return($bus_id, $boarding, $dropping) : false);
		//    var_dump($is_return).'<br>';
		$bus_start_stops_arr = $is_return ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops_return', true)) : maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_bp_stops', true)); // $bus_id bus start points
		$bus_next_stops_arr = $is_return ? maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops_return', true)) : maybe_unserialize(get_post_meta($bus_id, 'wbtm_bus_next_stops', true)); // $bus_id bus start points
		if ($bus_start_stops_arr) {
			foreach ($bus_start_stops_arr as $stop) {
				if ($boarding) { // Get $start data
					if ($stop['wbtm_bus_bp_stops_name'] == $boarding) {
						$boarding_point_time = $stop['wbtm_bus_bp_start_time']; // Boarding Point
						$data['boarding_time'] = mage_wp_time($stop['wbtm_bus_bp_start_time']);
						break;
					}
				}
			}
		}
		if ($bus_next_stops_arr) {
			foreach ($bus_next_stops_arr as $stop) {
				if ($dropping) { // Get $start data
					if ($stop['wbtm_bus_next_stops_name'] == $dropping) {
						$dropping_point_time = $stop['wbtm_bus_next_end_time']; // Dropping Point
						$data['dropping_time'] = mage_wp_time($stop['wbtm_bus_next_end_time']);
						break;
					}
				}
			}
		}
		$boarding_hour = '';
		if ($boarding_point_time) {
			$boarding_hour = explode(':', $boarding_point_time);
			$boarding_hour = $boarding_hour ? (int)$boarding_hour[0] : null;
		}
		$dropping_hour = '';
		if ($dropping_point_time) {
			$dropping_hour = explode(':', $dropping_point_time);
			$dropping_hour = $dropping_hour ? (int)$dropping_hour[0] : null;
		}
		// Check date is changed
		$wbtm_route_summary = maybe_unserialize(get_post_meta($bus_id, $return_text . 'wbtm_route_summary', true));
		$get_travel_day = 0;
		if ($wbtm_route_summary) {
			foreach ($wbtm_route_summary as $td) {
				if (isset($td['boarding']) && isset($td['dropping']) && isset($td['travel_day'])) {
					if ($td['boarding'] === $boarding && $td['dropping'] === $dropping) {
						$get_travel_day = $td['travel_day'];
						break;
					}
				}
			}
		}
		if ($boarding_hour && $dropping_hour) {
			// if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
			//     $data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
			// }
			if ($get_travel_day == 1) {
				if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
					$data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
				}
				else {
					$data['dropping'] = date('Y-m-d', strtotime($date));
				}
			}
			elseif ($get_travel_day == 2) {
				$data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
			}
			elseif ($get_travel_day == 3) {
				$data['dropping'] = date('Y-m-d', strtotime('+2 day', strtotime($date)));
			}
			elseif ($get_travel_day == 4) {
				$data['dropping'] = date('Y-m-d', strtotime('+3 day', strtotime($date)));
			}
			else {
				if (($boarding_hour > $dropping_hour) || ($dropping_hour == 24)) {
					$data['dropping'] = date('Y-m-d', strtotime('+1 day', strtotime($date)));
				}
			}
		}
		// Get boarding and dropping datetime difference
		$boarding_dateTime = new DateTime($data['boarding'] . ' ' . $data['boarding_time']);
		$dropping_dateTime = new DateTime($data['dropping'] . ' ' . $data['dropping_time']);
		$interval = $boarding_dateTime->diff($dropping_dateTime);
		$data['interval'] = $interval->format('%a days %h hours %i minutes');
		return $data;
	}
	function mage_date_format_issue($date) {
		$date_format = get_option('date_format');
		if ($date) {
			if ($date_format == 'm/d/Y') {
				$date = str_replace('-', '/', $date);
			}
			if ($date_format == 'd/m/Y') {
				$date = str_replace('/', '-', $date);
			}
		}
		return $date;
	}
// Mage array slice
	function mage_array_slice($arr, $s, $e = null): array {
		return $arr ? array_slice($arr, $s, $e) : array();
	}
// Get bus stops position in all bus stops
	function mage_intermidiate_available_seat_condition($start, $end, $all_stops) {
		$where = array();
		$sp = array_search($start, $all_stops);
		$ep = array_search($end, $all_stops);
		if ($sp == 0) {
			$where = array(
				array(
					'key' => 'wbtm_boarding_point',
					'value' => mage_array_slice($all_stops, 0, $ep),
					'compare' => 'IN'
				),
				array(
					'key' => 'wbtm_droping_point',
					'value' => mage_array_slice($all_stops, $sp),
					'compare' => 'IN'
				),
			);
		}
		elseif ($ep == (count($all_stops) - 1)) {
			$where = array(
				array(
					'key' => 'wbtm_boarding_point',
					'value' => mage_array_slice($all_stops, 0, $ep),
					'compare' => 'IN'
				),
				array(
					'key' => 'wbtm_droping_point',
					'value' => mage_array_slice($all_stops, $sp + 1),
					'compare' => 'IN'
				),
			);
		}
		else {
			$where = array(
				array(
					'key' => 'wbtm_boarding_point',
					'value' => mage_array_slice($all_stops, 0, $ep),
					'compare' => 'IN'
				),
				array(
					'key' => 'wbtm_droping_point',
					'value' => mage_array_slice($all_stops, $ep),
					'compare' => 'IN'
				),
			);
		}
		return $where;
	}
// Return Array
	function mage_bus_get_all_stopages($post_id) {
		$total_stopage = 0;
		$all_stopage = get_post_meta($post_id, 'wbtm_bus_prices', true);
		if ($all_stopage) {
			$input = (is_array($all_stopage) ? $all_stopage : unserialize($all_stopage));
			$input = array_column($input, 'wbtm_bus_bp_price_stop');
			$all_stopage = array_unique($input);
			$all_stopage = array_values($all_stopage);
			return $all_stopage;
		}
		return;
	}
// Check Cart has Oppsite route
// Note: $return_discount === 2
	function mage_cart_has_opposite_route($current_start, $current_stop, $current_j_date, $return = false, $current_r_date = null) {
		global $woocommerce;
		$data = 0;
		$items = $woocommerce->cart->get_cart();
		if (count($items) > 0) {
			$wbtm_start_stops_current = $current_start;
			$wbtm_end_stops_current = $current_stop;
			$journey_date_current = $current_j_date;
			// foreach( $items as $item => $value ) {
			//     if( ($value['is_return'] == 1) ) {
			//         return 0;
			//     }
			// }
			if ($journey_date_current) {
				$journey_date_current = new DateTime($journey_date_current);
			}
			if ($current_r_date) {
				$current_r_date = new DateTime($current_r_date);
			}
			foreach ($items as $item => $value) {
				if (array_key_exists('wbtm_journey_date', $value) && $value['wbtm_journey_date']) {
					$cart_j_date = new DateTime($value['wbtm_journey_date']);
				}
				if ($return) { // Return
					if (($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops'])) {
						$data = 1;
						break;
					}
					else {
						$data = 0;
					}
				}
				else { // Not return
					if (array_key_exists('wbtm_end_stops', $value) && ($wbtm_start_stops_current == $value['wbtm_end_stops']) && ($wbtm_end_stops_current == $value['wbtm_start_stops'])) {
						$data = 1;
						break;
					}
					else {
						$data = 0;
					}
				}
			}
		}
		return $data;
	}
	function mage_cart_has_opposite_route_P() {
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();
		if (count($items) > 0) {
			foreach ($items as $item => $value) {
				foreach ($items as $k => $v) {
					if (count($v['wbtm_passenger_info']) > 1) {
						return 1;
					}
					else {
						return 0;
					}
				}
			}
		}
	}
	/* Convert 24 to 12 time
	@param 1 24 hours time string
	@param 2 Bool
	Is show 12 hours time with am/pm.
	Is true show full time
	Is false show only am/pm
	*/
	function mage_time_24_to_12($time, $full = true) {
		$t = '';
		if ($time && strpos($time, ':') !== false) {
			if (strpos($time, 'AM') || strpos($time, 'am') || strpos($time, 'PM') || strpos($time, 'pm')) {
				$time = trim(str_replace('AM', '', $time));
				$time = trim(str_replace('am', '', $time));
				$time = trim(str_replace('PM', '', $time));
				$time = trim(str_replace('pm', '', $time));
			}
			$t = explode(':', $time);
			$h = $t[0];
			$m = $t[1];
			$tm = ($h < 12) ? 'am' : 'pm';
			if (!$full) {
				return $tm;
			}
			else {
				if ($h > 12) {
					$tt = $h - 12;
					$t = $tt . ':' . $m . ' ' . $tm;
				}
				elseif ($h == '00' || $h == '24') {
					$t = '00' . ':' . $m . ' am';
				}
				else {
					$t = $h . ':' . $m . ' ' . $tm;
				}
			}
			// $t = $tm;
		}
		return $t;
	}
// Convert to wp time format
	function mage_wp_time($time) {
		$wp_time_format = get_option('time_format');
		if ($time && $wp_time_format) {
			$time = date($wp_time_format, strtotime($time));
		}
		return $time;
	}
	function mage_wp_date($date, $format = false) {
		$wp_date_format = get_option('date_format');
		$date = mage_date_format_issue($date);
		if ($date && $format) {
			$date = date($format, strtotime($date));
			return $date;
		}
		if ($date && $wp_date_format) {
			$date = date($wp_date_format, strtotime($date));
		}
		return $date;
	}
// Extra services qty check
	function extra_service_qty_check($bus_id, $start, $end, $j_date, $service_type) {
		$count_q = 0;
		$argss = array(
			'post_type' => 'wbtm_bus_booking',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'relation' => 'AND',
					array(
						'key' => 'wbtm_boarding_point',
						'compare' => '=',
						'value' => $start,
					),
					array(
						'key' => 'wbtm_droping_point',
						'compare' => '=',
						'value' => $end,
					),
					array(
						'key' => 'wbtm_bus_id',
						'compare' => '=',
						'value' => $bus_id,
					),
					array(
						'key' => 'wbtm_journey_date',
						'compare' => '=',
						'value' => $j_date,
					),
					array(
						'key' => 'wbtm_status',
						'compare' => 'IN',
						'value' => array(1, 2),
					),
				),
			)
		);
		$ress = new WP_Query($argss);
		// echo '<pre>'; print_r($ress);
		if ($ress->found_posts > 0) {
			while ($ress->have_posts()) {
				$ress->the_post();
				$id = get_the_ID();
				$qty = get_post_meta($id, 'extra_services_type_qty_' . $service_type, true);
				$count_q += ($qty ? (int)$qty : 0);
			}
			wp_reset_postdata();
		}
		return $count_q;
	}
	function wbtm_extra_services_section($bus_id) {
		$start = isset($_GET['bus_start_route']) ? $_GET['bus_start_route'] : '';
		$end = isset($_GET['bus_end_route']) ? $_GET['bus_end_route'] : '';
		$j_date = isset($_GET['j_date']) ? $_GET['j_date'] : '';
		$extra_services = get_post_meta($bus_id, 'mep_events_extra_prices', true);
		if ($extra_services) :
			// ob_start();
			?>
			<div class="wbtm_extra_service_wrap">
				<p class="wbtm_heading">
					<strong><?php echo __('Extra Service', 'Extra Service:'); ?></strong>
				</p>
				<table class='wbtm_extra_service_table ra_extra_service_table'>
					<thead>
					<tr>
						<td align="left"><?php echo __('Name', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
						<td class="mage_text_center"><?php echo __('Quantity', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
						<td class="mage_text_center"><?php echo __('Price', 'bus-ticket-booking-with-seat-reservation'); ?>:</td>
					</tr>
					</thead>
					<tbody>
					<?php
						$count_extra = 0;
						foreach ($extra_services as $field) {
							$total_extra_service = (int)$field['option_qty'];
							$qty_type = $field['option_qty_type'];
							$total_sold = extra_service_qty_check($bus_id, $start, $end, $j_date, $field['option_name']);
							// $total_sold = 0;
							$ext_left = ($total_extra_service - $total_sold);
							// echo '<pre>';print_r($field);
							if (!isset($field['option_name']) || !isset($field['option_price'])) {
								continue;
							}
							$actual_price = strip_tags(wc_price($field['option_price']));
							$data_price = str_replace(get_woocommerce_currency_symbol(), '', $actual_price);
							$data_price = str_replace(wc_get_price_thousand_separator(), '', $data_price);
							$data_price = str_replace(wc_get_price_decimal_separator(), '.', $data_price);
							?>
							<tr data-total="0">
								<td align="Left"><?php echo $field['option_name']; ?>
									<div class="xtra-item-left"><?php echo $ext_left; ?>
										<?php _e('Left:', 'bus-ticket-booking-with-seat-reservation'); ?>
									</div>
									<!-- <input type="hidden" name='mep_event_start_date_es[]' value='<?php //echo $event_date;
									?>'> -->
								</td>
								<td class="mage_text_center">
									<?php
										if ($ext_left > 0) {
											if ($qty_type == 'dropdown') { ?>
												<select name="extra_service_qty[]" id="eventpxtp_<?php echo $count_extra;
												?>" style="min-width:93px;background:#fff;color:#000;border-radius:5px" class='extra-qty-box' data-price='<?php echo $data_price; ?>'>
													<?php for ($i = 0; $i <= $ext_left; $i++) { ?>
														<option value="<?php echo $i; ?>"><?php echo $i; ?><?php echo $field['option_name']; ?></option>
													<?php } ?>
												</select>
											<?php } else { ?>
												<div class="mage_input_group">
													<button class="fa fa-minus qty_dec" style="font-size:9px"></button>
													<input size="4" inputmode="numeric" type="text" class='extra-qty-box' name='extra_service_qty[]' data-price='<?php echo wbtm_get_price_including_tax($bus_id, $data_price); ?>' value='0' min="0" max="<?php echo $ext_left; ?>">
													<button class="fa fa-plus qty_inc" style="font-size:9px"></button>
												</div>
											<?php }
										}
										else {
											echo __('Not Available', 'bus-ticket-booking-with-seat-reservation');
										} ?>
								</td>
								<td class="mage_text_center">
									<?php
										$user = get_current_user_id();
										$user_roles = array();
										if ($user) {
											$user_meta = get_userdata($user);
											$user_roles = $user_meta->roles;
										}
										if (in_array('bus_sales_agent', $user_roles, true)) {
											echo '<input class="extra_service_per_price" type="text" name="extra_service_price[]" value="' . wbtm_get_price_including_tax($bus_id, $field['option_price']) . '" style="width: 80px"/>';
											if ($ext_left > 0) { ?>
												<p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0; ?></p>
												<input type="hidden" name='extra_service_name[]' value='<?php echo $field['option_name']; ?>'>
											<?php }
										}
										else {
											echo wc_price(wbtm_get_price_including_tax($bus_id, $field['option_price']));
											if ($ext_left > 0) { ?>
												<p style="display: none;" class="price_jq"><?php echo $data_price > 0 ? $data_price : 0; ?></p>
												<input type="hidden" name='extra_service_name[]' value='<?php echo $field['option_name']; ?>'>
												<input type="hidden" name='extra_service_price[]' value='<?php echo $field['option_price']; ?>'>
											<?php }
										}
									?>
								</td>
							</tr>
							<?php
							$count_extra++;
						}
					?>
					</tbody>
				</table>
			</div>
		<?php
			// return ob_get_contents();
		endif;
	}
// Extra services END
// Get bus type
	function wbtm_bus_type($bus_id) {
		$type = 'Seat Plan';
		$get_bus_type = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
		if ($get_bus_type) {
			switch ($get_bus_type) {
				case 'wbtm_seat_private':
					$type = 'Private';
					break;
				case 'wbtm_seat_subscription':
					$type = 'Subscription';
					break;
				case 'wbtm_without_seat_plan':
					$type = 'Without plan';
					break;
				default:
					$type = 'Seat Plan';
			}
		}
		return $type;
	}
// Is one way route or Return route
	function mage_determine_direction($id, $is_return, $start = null, $end = null) {
		$route_key = 'one'; // Default value
		if (!$start) {
			$start = $is_return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		}
		if (!$end) {
			$end = $is_return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		}
		$one_way_start = get_post_meta($id, 'wbtm_bus_bp_stops', true);
		$one_way_end = get_post_meta($id, 'wbtm_bus_next_stops', true);
		$return_start = get_post_meta($id, 'wbtm_bus_bp_stops_return', true);
		$return_end = get_post_meta($id, 'wbtm_bus_next_stops_return', true);
		if (!empty($one_way_start) && !empty($one_way_end)) {
			$one_way_start = array_column(maybe_unserialize($one_way_start), 'wbtm_bus_bp_stops_name');
			$one_way_end = array_column(maybe_unserialize($one_way_end), 'wbtm_bus_next_stops_name');
			$one_s = array_search($start, $one_way_start);
			$one_e = array_search($end, $one_way_end);
			if (($one_s == $one_e) && in_array($start, $one_way_start) && in_array($end, $one_way_end)) {
				$route_key = 'one';
			}
			else {
				if ($return_start && $return_end) {
					$return_start = array_column(maybe_unserialize($return_start), 'wbtm_bus_bp_stops_name');
					$return_end = array_column(maybe_unserialize($return_end), 'wbtm_bus_next_stops_name');
					// $return_s = array_search($start, $return_start);
					// $return_e = array_search($end, $return_end);
					// if (($return_s == $return_e) && in_array($start, $return_start) && in_array($end, $return_end)) {
					//     $route_key = 'return';
					// }
					if (in_array($start, $return_start) && in_array($end, $return_end)) {
						$route_key = 'return';
					}
				}
			}
		}
		return $route_key;
	}
// Is one way route or Return route
	function mage_determine_route($id, $is_return, $start = null, $end = null) {
		$direction = mage_determine_direction($id, $is_return, $start, $end);
		if ($direction == 'return') {
			$route_key = 'wbtm_bus_bp_stops_return';
		}
		else {
			$route_key = 'wbtm_bus_bp_stops';
		}
		return $route_key;
	}
// Get Pickup Point
	function mage_determine_pickuppoint($id, $is_return, $start, $end) {
		$start_id = mage_get_term_by_name($start, 'wbtm_bus_stops') ? mage_get_term_by_name($start, 'wbtm_bus_stops')->term_id : null;
		if ($start_id) {
			$direction = mage_determine_direction($id, $is_return, $start, $end);
			if ($direction == 'return') {
				$pickup_point = get_post_meta($id, 'wbtm_selected_pickpoint_return_name_' . $start_id, true);
			}
			else {
				$pickup_point = get_post_meta($id, 'wbtm_selected_pickpoint_name_' . $start_id, true);
			}
		}
		else {
			$pickup_point = array();
		}
		return $pickup_point;
	}
// Get ondates
	function mage_determine_ondate($id, $is_return, $start, $end) {
		$direction = mage_determine_direction($id, $is_return, $start, $end);
		if ($direction == 'return') {
			$ondates = get_post_meta($id, 'wbtm_bus_on_dates_return', true);
		}
		else {
			$ondates = get_post_meta($id, 'wbtm_bus_on_dates', true);
		}
		return $ondates;
	}
// Get offdates
	function mage_determine_offdate($id, $is_return, $start, $end) {
		$direction = mage_determine_direction($id, $is_return, $start, $end);
		if ($direction == 'return') {
			$offdates = get_post_meta($id, 'wbtm_offday_schedule_return', true);
		}
		else {
			$offdates = get_post_meta($id, 'wbtm_offday_schedule', true);
		}
		return $offdates;
	}
// Partial seat booked count
	function mage_partial_seat_booked_count($return, $seat = null, $bus_id = null, $start = null, $end = null, $date = null) {
		$partial_seat_booked = 0;
		// return $partial_seat_booked;
		$bus_id = $bus_id ? $bus_id : get_the_ID();
		$bus_type = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
		if ($bus_type == 'wbtm_without_seat_plan') {
			return mage_partial_without_seat_booked_count($return, $bus_id, $start, $end, $date); // For without seat plan
		}
		if (!$start) {
			$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		}
		if (!$end) {
			$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		}
		if (!$date) {
			$date = $return ? wbtm_convert_date_to_php(mage_bus_isset('r_date')) : wbtm_convert_date_to_php(mage_bus_isset('j_date'));
		}
		$date = mage_wp_date($date, 'Y-m-d');
		$all_stopages_name = get_post_meta($bus_id, 'wbtm_bus_bp_stops', true);
		$all_stopages_name = maybe_unserialize($all_stopages_name);
		// If trip is midnight trip
		// if(mage_bus_is_midnight_trip($all_stopages_name, $start, $end)) {
		//     $date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
		// }
		$all_stopages_name = array_column($all_stopages_name, 'wbtm_bus_bp_stops_name');
		$partial_route_condition = false; // init value
		$get_search_start_position = array_search($start, $all_stopages_name);
		$get_search_droping_position = array_search($end, $all_stopages_name);
		$get_search_droping_position = (is_bool($get_search_droping_position) && !$get_search_droping_position ? count($all_stopages_name) : $get_search_droping_position); // Last Stopage position assign
		if ($seat) {
			$partial_seat_booked = get_seat_booking_data($seat, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, $bus_id, $start, $end, $date);
		}
		else {
			$lower_seats = get_post_meta($bus_id, 'wbtm_bus_seats_info', true);
			$upper_seats = get_post_meta($bus_id, 'wbtm_bus_seats_info_dd', true);
			$lower_seat_booked_count = 0;
			$upper_seat_booked_count = 0;
			if ($lower_seats) {
				foreach ($lower_seats as $f_seat) {
					foreach ($f_seat as $key => $val) {
						if ($val != '') {
							$get_booking_data = get_seat_booking_data($val, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, $bus_id, $start, $end, $date);
							if ($get_booking_data['has_booked']) {
								$lower_seat_booked_count++;
							}
						}
					}
				}
			}
			if ($upper_seats) {
				foreach ($upper_seats as $f_seat) {
					foreach ($f_seat as $key => $val) {
						if ($val != '') {
							$get_booking_data = get_seat_booking_data($val, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return, null, null, null, $date);
							if ($get_booking_data['has_booked']) {
								$upper_seat_booked_count++;
							}
						}
					}
				}
			}
			$partial_seat_booked = $lower_seat_booked_count + $upper_seat_booked_count;
		}
		return $partial_seat_booked;
	}
// Get any term object by term_id
	function mage_get_term($term_id, $taxonomy) {
		$terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false
		));
		$return_term = null;
		if ($terms) {
			foreach ($terms as $s) {
				if ($s->term_id == $term_id) {
					$return_term = $s;
					break;
				}
			}
		}
		return $return_term;
	}
// Get any term object by term name
	function mage_get_term_by_name($term_name, $taxonomy) {
		$terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false
		));
		$return_term = null;
		if ($terms) {
			foreach ($terms as $s) {
				if ($s->name == $term_name) {
					$return_term = $s;
					break;
				}
			}
		}
		return $return_term;
	}
// Get Extra Price
	function extra_price($extra_services) {
		$price = 0;
		if (is_array($extra_services)) {
			foreach ($extra_services as $service) {
				$price += $service['price'] * $service['qty'];
			}
		}
		return $price;
	}
// Disabeled route bus remove from the search result
	function wbtm_removed_the_disabled_route_bus($start, $end, $bus_boarding_array, $bus_next_stops_array) {
		$is_route_disabled = false;
		// Global Setting
		$settings = get_option('wbtm_bus_settings');
		$route_disable_switch = isset($settings['route_disable_switch']) ? $settings['route_disable_switch'] : 'off';
		if ($route_disable_switch !== 'on') {
			return false;
		}
		if (!is_array($bus_boarding_array) || !is_array($bus_next_stops_array)) {
			return false;
		}
		// Checking Boarding disable
		$boarding_index = 0;
		foreach ($bus_boarding_array as $route) {
			if ($start != $route['wbtm_bus_bp_stops_name']) {
				$boarding_index++;
				continue; // if search boarding point not matched
			}
			if (isset($route['wbtm_bus_bp_start_disable'])) {
				if ($route['wbtm_bus_bp_start_disable'] === 'yes') {
					$boarding_disable_index = $boarding_index;
				}
			}
			$boarding_index++;
		}
		// Checking Dropping disable
		if (isset($boarding_disable_index)) {
			if (isset($bus_next_stops_array[$boarding_disable_index])) {
				if ($end == $bus_next_stops_array[$boarding_disable_index]['wbtm_bus_next_stops_name']) {
					$is_route_disabled = true; // this search boarding point is disabled
				}
			}
		}
		return $is_route_disabled;
	}
// Get Admin Route summary
	function admin_route_summary($post, $wbbm_bus_bp, $wbtm_bus_next_stops, $return = false) {
		$return_text = $return ? 'return_' : '';
		$wbtm_route_summary = maybe_unserialize(get_post_meta($post->ID, $return_text . 'wbtm_route_summary', true));
		?>
		<div class="wbtm-route-summary-container">
			<div class="wbtm-route-summary-inner">
				<div class="wbtm-route-summary-title">
					<h3><?php _e('Route summary', 'bus-ticket-booking-with-seat-reservation') ?></h3>
					<span><?php _e('This is the route summary according to the top route section. <br> If some trips need more than 24 hours please explicitly configure it from this summary.', 'bus-ticket-booking-with-seat-reservation') ?></span>
				</div>
				<table class="wbtm-table wbtm-table--route-summary">
					<thead>
					<tr>
						<th><?php _e('Sl', 'bus-ticket-booking-with-seat-reservation') ?></th>
						<th><?php _e('Boarding', 'bus-ticket-booking-with-seat-reservation') ?></th>
						<th><?php _e('Dropping', 'bus-ticket-booking-with-seat-reservation') ?></th>
						<th><?php _e('Trip day', 'bus-ticket-booking-with-seat-reservation') ?></th>
						<th><?php _e('Trip time', 'bus-ticket-booking-with-seat-reservation') ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ($wbbm_bus_bp) :
						$travel_days = array(
							'1' => __('Less than 1 day', 'bus-ticket-booking-with-seat-reservation'),
							'2' => __('More than 1 day', 'bus-ticket-booking-with-seat-reservation'),
							'3' => __('More than 2 days', 'bus-ticket-booking-with-seat-reservation'),
							'4' => __('More than 3 days', 'bus-ticket-booking-with-seat-reservation'),
						);
						$sl = 0;
						$i = 0;
						foreach ($wbbm_bus_bp as $bp) :
							$j = 0;
							foreach ($wbtm_bus_next_stops as $dp) :
								if ($i <= $j) :
									$get_stops_dates = mage_get_bus_stops_date($post->ID, date('Y-m-d'), $bp['wbtm_bus_bp_stops_name'], $dp['wbtm_bus_next_stops_name'], $return);
									?>
									<tr>
										<td><?php echo $sl + 1; ?></td>
										<td>
											<?php echo $bp['wbtm_bus_bp_stops_name'] ?>
											<input type="hidden" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][boarding]" value="<?php echo $bp['wbtm_bus_bp_stops_name'] ?>">
										</td>
										<td>
											<?php echo $dp['wbtm_bus_next_stops_name'] ?>
											<input type="hidden" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][dropping]" value="<?php echo $dp['wbtm_bus_next_stops_name'] ?>">
										</td>
										<td>
											<!-- Travel days loop -->
											<?php foreach ($travel_days as $key => $td) :
												$wbtm_route_day_check = '';
												if ($wbtm_route_summary) {
													$wbtm_route_day_check = (isset($wbtm_route_summary[$sl]['travel_day']) ? ($wbtm_route_summary[$sl]['travel_day'] == $key ? 'checked' : '') : ($key == 1 ? 'checked' : ''));
												}
												?>
												<label for="<?php echo $return_text ?>wbtm_route_days_<?php echo $sl . $key; ?>" class="wbtm-radio-label">
													<input type="radio" id="<?php echo $return_text ?>wbtm_route_days_<?php echo $sl . $key; ?>" value="<?php echo $key ?>" name="<?php echo $return_text ?>wbtm_route_summary[<?php echo $sl; ?>][travel_day]" <?php echo $wbtm_route_day_check ?>><?php echo $td; ?></label>
											<?php endforeach ?>
										</td>
										<td><?php echo($wbtm_route_summary ? $get_stops_dates['interval'] : ''); ?></td>
									</tr>
									<?php $sl++;
								endif;
								$j++;
							endforeach;
							$i++;
						endforeach;
					endif; ?>
					</tbody>
				</table>
			</div>
			<button class="wbtm_route_summary_btn"><?php _e('Expand Route Summary', 'bus-ticket-booking-with-seat-reservation') ?></button>
		</div>
		<?php
	}
	function wbtm_get_user_role($user_ID) {
		global $wp_roles;
		$user_data = get_userdata($user_ID);
		$user_role_slug = $user_data->roles;
		$user_role_nr = 0;
		$user_role_list = '';
		foreach ($user_role_slug as $user_role) {
			//count user role nrs
			$user_role_nr++;
			//add comma separation of there is more then one role
			if ($user_role_nr > 1) {
				$user_role_list .= ", ";
			}
			//add role name
			$user_role_list .= translate_user_role($wp_roles->roles[$user_role]['name']);
		}
		//return user role list
		return $user_role_list;
	}
// Global offdates process
	function wbtm_off_by_global_offdates($j_date) {
		$is_off = false;
		$current_date = date('d-m-Y', strtotime($j_date));
		$current_year = date('Y', strtotime($j_date));
		$settings = get_option('wbtm_bus_settings');
		$global_offdates = isset($settings['wbtm_bus_global_offdates']) ? $settings['wbtm_bus_global_offdates'] : [];
		if ($global_offdates) {
			$global_offdays_arr = explode(', ', $global_offdates);
			foreach ($global_offdays_arr as $goffdate) {
				if ($current_date == date('d-m-Y', strtotime($goffdate . '-' . $current_year))) {
					$is_off = true;
					break;
				}
			}
		}
		if (!$is_off) {
			$global_offdays = isset($settings['wbtm_bus_global_offdays']) ? $settings['wbtm_bus_global_offdays'] : [];
			if ($global_offdays) {
				$j_date_day = strtolower(date('w', strtotime($j_date)));
				if (in_array($j_date_day, $global_offdays)) {
					$is_off = true;
				}
			}
		}
		return $is_off;
	}

	add_action('wp_ajax_mage_bus_selected_seat_item', 'mage_bus_selected_seat_item');
	add_action('wp_ajax_nopriv_mage_bus_selected_seat_item', 'mage_bus_selected_seat_item');
	function mage_bus_selected_seat_item() {
		$return_discount = 0;
		$price_final = 0;
		$dd = false;
		$post_seat_name = isset($_POST['seat_name']) ? sanitize_text_field($_POST['seat_name']) : '';
		$post_bus_id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
		$post_start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
		$post_end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
		$post_passenger_type = isset($_POST['passenger_type']) ? sanitize_text_field($_POST['passenger_type']) : '';
		$post_dd = isset($_POST['dd']) ? sanitize_text_field($_POST['dd']) : '';
		$post_price = isset($_POST['price']) ? sanitize_text_field($_POST['price']) : '';
		$post_j_date = isset($_POST['j_date']) ? sanitize_text_field($_POST['j_date']) : '';
		$post_r_date = isset($_POST['r_date']) ? sanitize_text_field($_POST['r_date']) : '';
		$post_is_return = isset($_POST['is_return']) ? sanitize_text_field($_POST['is_return']) : '';
		// Return Discount setting
		$settings = get_option('wbtm_bus_settings');
		$val = mage_bus_setting_value('bus_return_discount');
		$is_return_discount_enable = $val ? $val : 'no';
		// $price_final = '<span data-current-price="'.$post_price.'" style="margin-right:0!important">'.wc_price($post_price).'</span>';
		?>
		<div class="flexEqual mage_bus_selected_seat_item" data-seat-name="<?php echo $post_seat_name; ?>">
			<h6><?php echo $post_seat_name; ?></h6>
			<?php
				if (mage_bus_multiple_passenger_type_check($post_bus_id, $post_start, $post_end)) {
					$seat_panel_settings = get_option('wbtm_bus_settings');
					$adult_label = mage_bus_setting_value('wbtm_seat_type_adult_label');
					$child_label = mage_bus_setting_value('wbtm_seat_type_child_label');
					$infant_label = mage_bus_setting_value('wbtm_seat_type_infant_label');
					$special_label = mage_bus_setting_value('wbtm_seat_type_special_label');
					if (1 == $post_passenger_type) {
						$type = $child_label;
					}
					elseif (2 == $post_passenger_type) {
						$type = $infant_label;
					}
					elseif (3 == $post_passenger_type) {
						$type = $special_label;
					}
					else {
						$type = $adult_label;
					}
					echo '<h6>' . $type . '</h6>';
				}
				$dd = ($post_dd == 'yes') ? true : false;
				$price_final = '<span data-current-price="' . wbtm_get_price_including_tax($post_bus_id, $post_price) . '" style="margin-right:0!important">' . wc_price(wbtm_get_price_including_tax($post_bus_id, $post_price)) . '</span>';
				// if($_POST['has_seat'] == 0) {
				if ($post_is_return && $post_r_date) {
					$return_discount = mage_cart_has_opposite_route($post_start, $post_end, $post_j_date, true, $post_r_date); // Return
				}
				else {
					$return_discount = mage_cart_has_opposite_route($post_start, $post_end, $post_j_date); // No return
				}
				$is_multiple_passenger = mage_cart_has_opposite_route_P();
				if ($is_return_discount_enable == 'yes') {
					if ($return_discount == 1 && !$is_multiple_passenger) {
						$price = mage_bus_seat_price($post_bus_id, $post_start, $post_end, $dd, $post_passenger_type, true);
						if ($price != $post_price) {
							$price_final = '<span data-old-price="' . $post_price . '" data-price="' . $price . '" data-current-price="' . wbtm_get_price_including_tax($post_bus_id, $price) . '" style="margin-right:0!important">' . wc_price(wbtm_get_price_including_tax($post_bus_id, $price)) . '</span>';
							$price_final .= '<span class="return_price_cal mage_old_price" data-price="' . $post_price . '" style="display:block">' . wc_price(wbtm_get_price_including_tax($post_bus_id, $post_price)) . '</span>';
						}
						else {
							$price_final = '<span data-old-price="' . $post_price . '" data-price="' . $price . '" data-current-price="' . wbtm_get_price_including_tax($post_bus_id, $price) . '" style="margin-right:0!important">' . wc_price(wbtm_get_price_including_tax($post_bus_id, $price)) . '</span>';
						}
					}
				}
				// }
			?>
			<h6 class="mage_selected_seat_price"><?php echo $price_final; ?></h6>
			<h6>
				<span class="fa fa-trash mage_bus_seat_unselect"></span>
			</h6>
		</div>
		<?php
		die();
	}
	add_action('wp_ajax_wbtm_form_builder', 'wbtm_form_builder_callback');
	add_action('wp_ajax_nopriv_wbtm_form_builder', 'wbtm_form_builder_callback');
	function wbtm_form_builder_callback() {
		$busId = isset($_POST['busID']) ? sanitize_text_field($_POST['busID']) : '';
		$seatType = isset($_POST['seatType']) ? sanitize_text_field($_POST['seatType']) : '';
		$passengerType = isset($_POST['passenger_type']) ? sanitize_text_field($_POST['passenger_type']) : 0;
		$seats = isset($_POST['seats']) ? sanitize_text_field($_POST['seats']) : '';
		$post_dd = isset($_POST['dd']) ? sanitize_text_field($_POST['dd']) : '';
		if ($post_dd) {
			$dd = ($post_dd == 'yes' ? 'yes' : 'no');
		}
		if (class_exists('WbtmProFunction')) {
			for ($i = 1; $i <= $seats; $i++) {
				WbtmProFunction::bus_hidden_customer_info_form($busId, $seatType, $passengerType, $dd);
			}
		}
		else {
			// echo '<input type="hidden" name="custom_reg_user" value="no" />';
		}
		exit;
	}
	function wbtm_myaccount_query_vars($vars) {
		$vars[] = 'bus-panel';
		return $vars;
	}
	add_filter('query_vars', 'wbtm_myaccount_query_vars', 0);
	/**
	 * Custom help to add new items into an array after a selected item.
	 * @param array $items
	 * @param array $new_items
	 * @param string $after
	 * @return array
	 */
	function wbtm_bus_panel_insert_after_helper($items, $new_items, $after) {
		// Search for the item position and +1 since is after the selected item key.
		$position = array_search($after, array_keys($items)) + 1;
		// Insert the new item.
		$array = array_slice($items, 0, $position, true);
		$array += $new_items;
		$array += array_slice($items, $position, count($items) - $position, true);
		return $array;
	}
	/**
	 * Insert the new endpoint into the My Account menu.
	 * @param array $items
	 * @return array
	 */
	function wbtm_bus_panel_menu_items($items) {
		$new_items = array();
		$new_items['bus-panel'] = mage_bus_setting_value('bus_menu_label', 'Bus') . ' class-functions.php' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');
		// Add the new item after `orders`.
		return wbtm_bus_panel_insert_after_helper($items, $new_items, 'orders');
	}
	add_filter('woocommerce_account_menu_items', 'wbtm_bus_panel_menu_items');
	/**
	 * Endpoint HTML content.
	 */
	function wbtm_bus_panel_endpoint_content() {
		global $magepdf;
		$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
		$user_id = get_current_user_id();
		$myaccount_link = get_permalink(wc_get_page_id('myaccount'));
		if ($mode === 'ticket-exchange') {
			if (isset($_GET['order_id'])) {
				do_action('wbtm_ticket_exchange', $_GET['order_id']);
			}
			return;
		}
		ob_start();
		if (isset($_SESSION['msg'])) {
			echo '<p class="mefs-notification">' . $_SESSION['msg'] . '</p>';
			// Destroy Message
			unset($_SESSION['msg']);
		}
		// Get tickets
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => 'wbtm_user_id',
				'value' => $user_id,
				'compare' => '='
			),
			array(
				'relation' => 'OR',
				array(
					'key' => 'wbtm_status',
					'value' => 1,
					'compare' => '='
				),
				array(
					'key' => 'wbtm_status',
					'value' => 2,
					'compare' => '='
				),
			),
		);
		$args = array(
			'post_type' => 'wbtm_bus_booking',
			'posts_per_page' => -1,
			'order' => 'DESC',
			'meta_query' => $meta_query
		);
		$passengers = new WP_Query($args);
		// Is pdf plguin active
		$is_show_ticket = is_plugin_active('magepeople-pdf-support-master/mage-pdf.php') ? true : false;
		echo '<div class="wbtm_myaccount_wrapper">';
		?>
		<table>
			<thead>
			<tr>
				<th><?php _e('Order no', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<th><?php echo mage_bus_setting_value('bus_menu_label', 'Bus') . ' class-functions.php' . __('Name', 'bus-ticket-booking-with-seat-reservation') ?></th>
				<th><?php _e('Order Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<th><?php _e('Journey Date', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<th><?php _e('Seat', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<th><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<th><?php _e('Status', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<?php if ($is_show_ticket) : ?>
					<th><?php _e('Action', 'bus-ticket-booking-with-seat-reservation'); ?></th>
				<?php endif; ?>
			</tr>
			</thead>
			<tbody>
			<?php
				while ($passengers->have_posts()) :
					$passengers->the_post();
					$id = get_the_ID();
					$order_id = get_post_meta($id, 'wbtm_order_id', true);
					$order = wc_get_order($order_id);
					$booking_date = get_post_meta($id, 'wbtm_booking_date', true);
					$booking_date = explode(' ', $booking_date);
					$download_url = $is_show_ticket ? $magepdf->get_invoice_ajax_url(array('order_id' => $order_id)) : '';
					?>
					<tr>
						<td>
							<a href="<?php echo $myaccount_link . 'view-order/' . $order_id; ?>">#<?php echo $order_id; ?></a>
						</td>
						<td><?php echo get_the_title(get_post_meta($id, 'wbtm_bus_id', true)); ?></td>
						<td><?php echo mage_wp_date($booking_date[0]) . ' class-functions.php' . mage_wp_time($booking_date[1]); ?></td>
						<td><?php echo mage_wp_date(get_post_meta($id, 'wbtm_journey_date', true)) . ' class-functions.php' . mage_wp_time(get_post_meta($id, 'wbtm_bus_start', true)); ?></td>
						<td><?php echo get_post_meta($id, 'wbtm_seat', true); ?></td>
						<td><?php echo get_post_meta($id, 'wbtm_pickpoint', true); ?></td>
						<td>
							<?php
								if ($order) {
									echo ucfirst($order->get_status());
								}
							?>
						</td>
						<?php if ($is_show_ticket) : ?>
							<td>
								<?php if ($order) : ?>
									<a class="wbtm-btn order-table-btn"
										href="<?php echo $order->get_view_order_url(); ?>"><?php _e('Show Order', 'bus-ticket-booking-with-seat-reservation') ?></a>
								<?php endif ?>
								<a class="wbtm-btn order-table-btn"
									href="<?php echo $download_url; ?>"><?php _e('Download Ticket', 'bus-ticket-booking-with-seat-reservation') ?></a>
								<?php do_action('wbtm_bus_panel_action', $order_id) ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		<?php
		echo '</div>';
		$output = ob_get_contents();
	}
	add_action('woocommerce_account_bus-panel_endpoint', 'wbtm_bus_panel_endpoint_content');
	/*
	 * Change endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	function wbtm_bus_panel_endpoint_title($title) {
		global $wp_query;
		$is_endpoint = isset($wp_query->query_vars['bus-panel']);
		if ($is_endpoint && !is_admin() && is_main_query() && in_the_loop() && is_account_page()) {
			// New page title.
			$title = mage_bus_setting_value('bus_menu_label', 'Bus') . ' class-functions.php' . __('Ticket', 'bus-ticket-booking-with-seat-reservation');
			remove_filter('the_title', 'wbtm_bus_panel_endpoint_title');
		}
		return $title;
	}
	add_filter('the_title', 'wbtm_bus_panel_endpoint_title');
	$mage_bus_total_seats_availabel = 0;
//bus search list
	function mage_bus_search_list($return) {
		global $wbtmmain;
		$is_old_date = false;
		$bus_list = mage_search_bus_query($return);
		$bus_list_loop = new WP_Query($bus_list);
		$j_date = $return ? $_GET['r_date'] : $_GET['j_date'];
		// Check is old date
		$j_date_ = date('Y-m-d', strtotime($j_date));
		if ($j_date_ < date('Y-m-d')) {
			$is_old_date = true;
		}
		$j_date = mage_wp_date($j_date, 'Y-m-d');
		$start = $_GET['bus_start_route'];
		$end = $_GET['bus_end_route'];
		if ($return) {
			$start = $_GET['bus_end_route'];
			$end = $_GET['bus_start_route'];
		}
		//$j_date = mage_convert_date_format($j_date, 'Y-m-d');
		echo '<div class="mar_t mage_bus_lists">';
		mage_bus_title();
		$has_bus_data = array();
		$bus_index = 0;
		if (!$is_old_date) {
			while ($bus_list_loop->have_posts()) {
				$has_bus = false;
				$is_buffer = null;
				$p_j_date = $j_date;
				$bus_list_loop->the_post();
				$id = get_the_id();
				$bus_bp_array = get_post_meta($id, 'wbtm_bus_bp_stops', true) ? get_post_meta($id, 'wbtm_bus_bp_stops', true) : [];
				$bus_bp_array = maybe_unserialize($bus_bp_array);
				if ($bus_bp_array) {
					$bus_next_stops_array = get_post_meta($id, 'wbtm_bus_next_stops', true) ? get_post_meta($id, 'wbtm_bus_next_stops', true) : [];
					$bus_next_stops_array = maybe_unserialize($bus_next_stops_array);
					// Intermidiate Route
					$o_1 = mage_bus_end_has_prev($start, $end, $bus_bp_array);
					$o_2 = mage_bus_start_has_next($start, $end, $bus_next_stops_array);
					if ($o_1 && $o_2) {
						continue;
					}
					// Intermidiate Route END
					// Buffer Time Calculation
					$bp_time = $wbtmmain->wbtm_get_bus_start_time($start, $bus_bp_array);
					$is_buffer = $wbtmmain->wbtm_buffer_time_check($bp_time, date('Y-m-d', strtotime($p_j_date)));
					// Buffer Time Calculation END
					// Midnight Calculation
					$is_midnight = mage_bus_is_midnight_trip($bus_bp_array, $start);
					if ($is_midnight) {
						$p_j_date = date('Y-m-d', strtotime('-1 day', strtotime($p_j_date)));
					}
					// Midnight Calculation END
					if ($is_buffer == 'yes') {
						// Operational on day
						$is_on_date = false;
						$bus_on_dates = array();
						//$bus_on_date = get_post_meta($id, 'wbtm_bus_on_dates', true);
						$bus_on_date = mage_determine_ondate($id, $return, $start, $end);
						$show_operational_on_day = get_post_meta($id, 'show_operational_on_day', true);
						if ($bus_on_date != null && $show_operational_on_day == 'yes') {
							$bus_on_dates = explode(', ', $bus_on_date);
							$is_on_date = true;
						}
						if ($is_on_date) {
							if (in_array(date('m-d', strtotime($p_j_date)), $bus_on_dates)) {
								$has_bus = true;
							}
						}
						else {
							// Offday schedule check
							// $bus_stops_times = get_post_meta($id, 'wbtm_bus_bp_stops', true);
							// $bus_offday_schedules = get_post_meta($id, 'wbtm_offday_schedule', true);
							$bus_offday_schedules = mage_determine_offdate($id, $return, $start, $end);
							// Get Bus Start Time
							$start_time = '';
							foreach ($bus_bp_array as $stop) {
								if ($stop['wbtm_bus_bp_stops_name'] == $start) {
									$start_time = $stop['wbtm_bus_bp_start_time'];
									break;
								}
							}
							$start_time = mage_time_24_to_12($start_time); // Time convert 24 to 12
							$offday_current_bus = false; // Bus is running
							$s_datetime = date('Y-m-d H:i:s', strtotime($p_j_date));
							if (wbtm_off_by_global_offdates($p_j_date)) { // Global off dates and days check
								$offday_current_bus = true; // Bus is off
							}
							else { // Local offdates check
								if (!empty($bus_offday_schedules) && get_post_meta($id, 'show_off_day', true) === 'yes') {
									foreach ($bus_offday_schedules as $item) {
										$c_iterate_date_from = $item['from_date'];
										// $c_iterate_datetime_from = date('Y-m-d H:i:s', strtotime($c_iterate_date_from . ' ' . $item['from_time']));
										$c_iterate_datetime_from = date('Y-m-d H:i:s', strtotime(date('Y', strtotime($p_j_date)) . '-' . $c_iterate_date_from));
										$c_iterate_date_to = $item['to_date'];
										// $c_iterate_datetime_to = date('Y-m-d H:i:s', strtotime($c_iterate_date_to . ' ' . $item['to_time']));
										$c_iterate_datetime_to = date('Y-m-d H:i:s', strtotime(date('Y', strtotime($p_j_date)) . '-' . $c_iterate_date_to));
										if (($s_datetime >= $c_iterate_datetime_from) && ($s_datetime <= $c_iterate_datetime_to)) {
											$offday_current_bus = true; // Bus is off
											break;
										}
									}
								}
							}
							// Check Offday and date
							// if $offday_current_bus = false && mage_check_search_day_off_new = false
							if (!$offday_current_bus && !mage_check_search_day_off_new($id, $p_j_date, $return)) {
								$has_bus = true;
							}
						}
					}
				}
				// var_dump($has_bus);
				// Has Bus
				if ($has_bus === true) {
					$has_bus_data[$bus_index]['return'] = $return;
					$has_bus_data[$bus_index]['id'] = $id;
				}
				$bus_index++;
			}
		}
		// Final list showing
		if (!empty($has_bus_data)) {
			mage_bus_list_sorting($has_bus_data, $start, $return); // Bus list sorting
		}
		else {
			echo '<p class="no-bus-found">';
			mage_bus_label('wbtm_no_bus_found_text', __('No', 'bus-ticket-booking-with-seat-reservation') . ' bus-search-form.php' . mage_bus_setting_value('bus_menu_label', 'Bus') . ' ' . __('Found!', 'bus-ticket-booking-with-seat-reservation'));
			echo '</p>';
		}
		echo '<div class="mediumRadiusBottom mage_bus_list_title "></div>';
		echo '</div>';
		wp_reset_query();
	}
	function mage_bus_list_sorting($has_bus_data, $start_route, $return, $sort = 'ASC') {
		$wbtm_bus_bp_stops_array = array();
		foreach ($has_bus_data as $bus) {
			$which_way = mage_determine_route($bus['id'], $return);
			$wbtm_bus_bp_stops = get_post_meta($bus['id'], $which_way, true);
			if ($wbtm_bus_bp_stops) {
				$wbtm_bus_bp_stops_array[$bus['id']] = array_values(maybe_unserialize($wbtm_bus_bp_stops));
			}
		}
		$target_bus_start_time = array();
		foreach ($wbtm_bus_bp_stops_array as $key => $stops) {
			foreach ($stops as $stop) {
				if ($stop['wbtm_bus_bp_stops_name'] == $start_route) {
					$target_bus_start_time[$key] = $stop['wbtm_bus_bp_start_time'];
				}
			}
		}
		// Sorting By $sort
		uasort($target_bus_start_time, "wbtm_bus_sort_by_time");
		$final_sorted_ids = array();
		foreach ($target_bus_start_time as $id => $bus) {
			$final_sorted_ids[] = $id;
		}
		$sorted_bus_list = new WP_Query(array(
			'post_type' => 'wbtm_bus',
			'posts_per_page' => -1,
			'post__in' => $final_sorted_ids,
			'orderby' => array('post__in' => 'asc')
		));
		while ($sorted_bus_list->have_posts()) {
			$sorted_bus_list->the_post();
			mage_bus_search_item($return, get_the_ID());
		}
	}
	function wbtm_bus_sort_by_time($a, $b) {
		$orderBy = mage_bus_setting_value('bus_search_list_order') ? mage_bus_setting_value('bus_search_list_order') : 'asc';
		if (strtotime($a) == strtotime($b)) {
			return 0;
		}
		if ($orderBy === 'asc') {
			return (strtotime($a) < strtotime($b)) ? -1 : 1;
		}
		else {
			return (strtotime($a) < strtotime($b)) ? 1 : -1;
		}
	}
	function mage_bus_search_item($return, $id) {
		$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		$bus_id = get_the_id();
		$seat_price = mage_bus_seat_price($bus_id, $start, $end, false);
		$values = get_post_custom($id);
		$start_time = mage_bus_time($return, false);
		$end_time = mage_bus_time($return, true);
		$date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
		$get_stops_dates = mage_get_bus_stops_date($bus_id, $date, $start, $end, $return);
		$arrival_date = $get_stops_dates['dropping'];
		$starting_date = $get_stops_dates['boarding'];
		$show_dropping_time = isset($values['show_dropping_time'][0]) ? $values['show_dropping_time'][0] : 'yes';
		$show_boarding_time = isset($values['show_boarding_time'][0]) ? $values['show_boarding_time'][0] : 'yes';
		$cart_class = wbtm_find_product_in_cart($return);
		$zero_price_allow = get_post_meta($bus_id, 'zero_price_allow', true) ?: 'no';
		// Check this route has price if not, return
		// $check_has_price = mage_bus_seat_price($bus_id, $start, $end, false);
		if (($zero_price_allow === 'no' && !$seat_price) || $seat_price === '') {
			return;
		}
		// Partial route available
		$partial_seat_booked = mage_partial_seat_booked_count($return);
		// Partial route available END
		?>
		<div class="mage_bus_item <?php echo $cart_class; ?>" data-bus-id="<?php echo $bus_id; ?>" data-is-return="<?php echo $return; ?>">
			<div class="mage_flex">
				<?php $alt_image = (wp_get_attachment_url(mage_bus_setting_value('alter_image'))) ? wp_get_attachment_url(mage_bus_setting_value('alter_image')) : 'https://i.imgur.com/807vGSc.png'; ?>
				<div class="mage_bus_img flexCenter"><?php echo has_post_thumbnail() ? the_post_thumbnail('thumb') : "<img src=" . $alt_image . ">" ?></div>
				<div class="mage_bus_info flexEqual_flexCenter">
					<div class="flexEqual_flexCenter">
						<h6>
							<strong class="dBlock_mar_zero"><?php echo '<a href="' . get_the_permalink() . '">' . get_the_title() . '</a>'; ?></strong>
							<small class="dBlock"><?php echo $values['wbtm_bus_no'][0]; ?></small>
							<?php
								if ($cart_class) {
									echo '<span class="dBlock_mar_t_xs"><span class="fa fa-shopping-cart"></span>';
									mage_bus_label('wbtm_already_in_cart_text', __('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'));
									echo '</span>';
								}
							?>
						</h6>
						<div class="mage_hidden_xxs">
							<h6>
								<span class="fa fa-angle-double-right"></span>
								<span><?php echo $start; ?><?php echo($show_boarding_time == 'yes' ? sprintf('(%s %s)', mage_wp_date($starting_date), mage_wp_time($start_time)) : null); ?>
                            </span>
							</h6>
							<h6>
								<span class="fa fa-stop"></span>
								<span><?php echo $end; ?><?php echo($show_dropping_time == 'yes' ? sprintf('(%s %s)', mage_wp_date($arrival_date), mage_wp_time($end_time)) : null); ?>
                            </span>
							</h6>
						</div>
					</div>
					<div class="flexEqual_flexCenter_textCenter">
						<h6 class="mage_hidden_xxs"><?php echo mage_bus_type(); ?></h6>
						<h6 class="mage_hidden_xs">
							<strong><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?></strong>
							/
							<span><?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?></span>
						</h6>
						<h6 class="mage_hidden_md">
							<?php echo (mage_bus_total_seat_new() - $partial_seat_booked) . ' / ' . mage_bus_total_seat_new(); ?>
						</h6>
						<button type="button" class="mage_button_xs mage_bus_details_toggle"><?php mage_bus_label('wbtm_view_seats_text', __('View Seats', 'bus-ticket-booking-with-seat-reservation')); ?></button>
					</div>
				</div>
			</div>
			<?php mage_bus_item_seat_details($return, $partial_seat_booked); ?>
		</div>
		<?php
	}
	function mage_bus_item_seat_details($return, $partial_seat_booked = 0) {
		global $mage_bus_total_seats_availabel;
		$bus_id = get_the_id();
		// Search Data
		$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		$date = $return ? mage_bus_isset('r_date') : mage_bus_isset('j_date');
		// $start_time = get_wbtm_datetime(mage_bus_time($return, false), 'time');
		$start_time = mage_wp_time(mage_bus_time($return, false));
		// $end_time = get_wbtm_datetime(mage_bus_time($return, true), 'time');
		$end_time = mage_wp_time(mage_bus_time($return, true));
		$return_date = isset($_GET['r_date']) && $_GET['r_date'] != '' ? $_GET['r_date'] : null;
		$seat_price = mage_bus_seat_price($bus_id, $start, $end, false);
		$show_dropping_time = get_post_meta($bus_id, 'show_dropping_time', true);
		$show_dropping_time = $show_dropping_time ? $show_dropping_time : 'yes';
		$show_boarding_time = get_post_meta($bus_id, 'show_boarding_time', true);
		$show_boarding_time = $show_boarding_time ? $show_boarding_time : 'yes';
		// Pickpoint
		// $pickpoints = get_post_meta($bus_id, 'wbtm_selected_pickpoint_name_'.strtolower($start), true);
		$pickpoints = mage_determine_pickuppoint($bus_id, $return, $start, $end);
		if ($pickpoints != '') {
			$pickpoints = maybe_unserialize($pickpoints);
		}
		// $partial_seat_booked = mage_partial_seat_booked_count($return);
		$seat_available = mage_bus_total_seat_new() - $partial_seat_booked;
		// Bus Seat Type
		$bus_seat_type_conf = get_post_meta($bus_id, 'wbtm_seat_type_conf', true);
		$seat_panel_settings = get_option('wbtm_bus_settings');
		$adult_label = $seat_panel_settings['wbtm_seat_type_adult_label'];
		$child_label = $seat_panel_settings['wbtm_seat_type_child_label'];
		$infant_label = $seat_panel_settings['wbtm_seat_type_infant_label'];
		$special_label = $seat_panel_settings['wbtm_seat_type_special_label'];
		// Bus Zero Price
		$bus_zero_price_allow = get_post_meta($bus_id, 'zero_price_allow') ? get_post_meta($bus_id, 'zero_price_allow')[0] : '';
		if ($bus_seat_type_conf === 'wbtm_without_seat_plan') {
			// Price
			// $seatPrices = get_post_meta($bus_id, 'wbtm_bus_prices', true);
			$seatPrices = mage_bus_seat_prices($bus_id, $start, $end);
			$available_seat_type = array();
			if ($seatPrices) {
				$i = 0;
				foreach ($seatPrices as $price) {
					if (strtolower($price['wbtm_bus_bp_price_stop']) == strtolower($start) && strtolower($price['wbtm_bus_dp_price_stop']) == strtolower($end)) {
						if ((float)$price['wbtm_bus_price'] > 0 || $bus_zero_price_allow === 'yes') {
							$available_seat_type[$i]['type'] = 'Adult';
							$available_seat_type[$i]['price'] = $price['wbtm_bus_price'];
							$i++;
						}
						if ((float)$price['wbtm_bus_child_price'] >= 0 && $price['wbtm_bus_child_price'] != '') {
							$available_seat_type[$i]['type'] = 'Child';
							$available_seat_type[$i]['price'] = $price['wbtm_bus_child_price'];
							$i++;
						}
						if ((float)$price['wbtm_bus_infant_price'] >= 0 && $price['wbtm_bus_infant_price'] != '') {
							$available_seat_type[$i]['type'] = 'Infant';
							$available_seat_type[$i]['price'] = $price['wbtm_bus_infant_price'];
							$i++;
						}
						break;
					}  // end foreach
				}
			} // end if
		} // end if
		?>
		<form class="mage_form wbtm_bus_booking" action="" method="post">
			<div class="mage_bus_seat_details">
				<input type="hidden" name='journey_date' value='<?php echo mage_wp_date($date, 'Y-m-d'); ?>'/>
				<input type="hidden" name='return_date' value='<?php echo mage_wp_date($return_date, 'Y-m-d'); ?>'/>
				<input type="hidden" name='start_stops' value="<?php echo $start; ?>"/>
				<input type='hidden' name='end_stops' value='<?php echo $end; ?>'/>
				<input type="hidden" name="user_start_time" value="<?php echo mage_bus_time($return, false); ?>"/>
				<input type="hidden" name="bus_start_time" value="<?php echo mage_bus_time($return, false); ?>"/>
				<input type="hidden" name="bus_id" value="<?php echo $bus_id; ?>"/>
				<input type="hidden" name="seat_available" value="<?php echo $seat_available; ?>"/>
				<input type="hidden" name='total_seat' value="0"/>
				<input type="hidden" name="wbtm_bus_type" value="general"/>
				<input type="hidden" name="wbtm_bus_zero_price_allow" value="<?php echo $bus_zero_price_allow; ?>"/>
				<input type="hidden" name="wbtm_bus_no" value="<?php echo get_post_meta($bus_id, 'wbtm_bus_no', true) ?>">
				<input type="hidden" name="wbtm_bus_name" value="<?php echo get_the_title() ?>">
				<?php
					if ($return) {
						echo '<input type="hidden" name="wbtm_booking_now" value="return">';
					}
					if ($bus_seat_type_conf === 'wbtm_without_seat_plan') : ?>
						<!-- Seat type = No seat -->
						<input type="hidden" name="wbtm_order_seat_plan" value="no">
						<input type="hidden" name="custom_reg_user" value="no"/>
						<div class="mage-no-seat">
							<div class="mage-no-seat-inner">
								<div class="mage-no-seat-left">
									<table class="mage-seat-table mage-bus-short-info">
										<tr>
											<th>
												<i class="fas fa-map-marker"></i>
												<?php mage_bus_label('wbtm_boarding_points_text', __('Boarding', 'bus-ticket-booking-with-seat-reservation')); ?>
												:
											</th>
											<td><?php echo $start; ?><?php echo($show_boarding_time == 'yes' && $start_time ? sprintf('(%s)', mage_wp_time($start_time)) : null); ?></td>
										</tr>
										<tr>
											<th>
												<i class="fas fa-map-marker"></i>
												<?php mage_bus_label('wbtm_dropping_points_text', __('Dropping', 'bus-ticket-booking-with-seat-reservation')) ?>
												:
											</th>
											<td><?php echo $end; ?><?php echo($show_dropping_time == 'yes' && $end_time ? sprintf('(%s)', mage_wp_time($end_time)) : null); ?></td>
										</tr>
										<?php if (mage_bus_type()) : ?>
											<tr>
												<th>
													<i class="fa fa-bus" aria-hidden="true"></i>
													<?php mage_bus_label('wbtm_type_text', __('Coach Type', 'bus-ticket-booking-with-seat-reservation')); ?>
													:
												</th>
												<td><?php echo mage_bus_type(); ?></td>
											</tr>
										<?php endif; ?>
										<tr>
											<th>
												<i class="fa fa-calendar" aria-hidden="true"></i>
												<?php mage_bus_label('wbtm_date_text', __('Date', 'bus-ticket-booking-with-seat-reservation')); ?>
												:
											</th>
											<td><?php echo mage_wp_date($date); ?></td>
										</tr>
										<?php if ($show_boarding_time == 'yes' && $start_time) { ?>
											<tr>
												<th>
													<i class="fa fa-clock-o" aria-hidden="true"></i>
													<?php mage_bus_label('wbtm_start_time_text', __('Start Time', 'bus-ticket-booking-with-seat-reservation')) ?>
													:
												</th>
												<td><?php echo $start_time; ?></td>
											</tr>
										<?php } ?>
										<tr>
											<th>
												<i class="fas fa-map-marker"></i>
												<?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?>
												:
											</th>
											<td><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?> /
												<?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?>
											</td>
										</tr>
									</table>
									<div class="mage-grand-total">
										<p>
											<strong><?php _e('Grand Total', 'bus-ticket-booking-with-seat-reservation'); ?> :</strong>
											<span class="mage-price-figure"><?php echo wc_price(0); ?></span>
										</p>
									</div>
								</div>
								<div class="mage-no-seat-right">
									<table class="mage-seat-table">
										<thead>
										<tr>
											<th><?php _e('Type', 'bus-ticket-booking-with-seat-reservation'); ?></th>
											<th><?php _e('Quantity', 'bus-ticket-booking-with-seat-reservation'); ?></th>
											<th><?php _e('Price', 'bus-ticket-booking-with-seat-reservation'); ?></th>
											<th><?php _e('SubTotal', 'bus-ticket-booking-with-seat-reservation'); ?></th>
										</tr>
										</thead>
										<tbody>
										<?php foreach ($available_seat_type as $type) :
											if (($type['price'] >= 0 && $type['price'] != '') || $bus_zero_price_allow === 'yes') : ?>
												<tr>
													<td><?php echo wbtm_get_seat_type_label(strtolower($type['type']), $type['type']) ?></td>
													<td class="mage-seat-qty">
														<button class="wbtm-qty-change wbtm-qty-dec" data-qty-change="dec">-
														</button>
														<input class="qty-input" type="text" data-seat-type="<?php echo strtolower($type['type']); ?>" data-price="<?php echo $type['price']; ?>" data-max-qty="<?php echo $seat_available; ?>" name="seat_qty[]"/>
														<button class="wbtm-qty-change wbtm-qty-inc" data-qty-change="inc">+
														</button>
														<input type="hidden" name="passenger_type[]" value="<?php echo $type['type'] ?>">
														<input type="hidden" name="bus_dd[]" value="no">
													</td>
													<td><?php echo wc_price(wbtm_get_price_including_tax($bus_id, $type['price'])) . '<sub> / ' . __("Seat", "bus-ticket-booking-with-seat-reservation") . '</sub>'; ?>
													</td>
													<td class="mage-seat-price">
														<span class="price-figure"><?php echo wc_price(0); ?></span>
													</td>
												</tr>
											<?php endif;
										endforeach; ?>
										</tbody>
										<tfoot>
										<tr>
											<td colspan="4"></td>
										</tr>
										<tr>
											<td></td>
											<td></td>
											<td>
												<strong><?php _e('Total', 'bus-ticket-booking-with-seat-reservation'); ?>
													:
												</strong>
											</td>
											<td class="mage-price-total">
												<strong>
													<span class="price-figure"><?php echo wc_price(0); ?></span>
												</strong>
											</td>
										</tr>
										</tfoot>
									</table>
									
									<?php if ($pickpoints) : ?>
										<div class="wbtm-pickpoint-wrap">
											<label for="wbtm-pickpoint-no-seat"><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?>
												<span class="wbtm_required">*</span>
											</label>
											<select name="wbtm_pickpoint" id="wbtm-pickpoint-no-seat" required>
												<option value=""><?php _e('Select Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?></option>
												<?php foreach ($pickpoints as $point) :
													$pickupTime = $point['time'] ? ' [' . $point['time'] . ']' : '';
													$d = ucfirst($point['pickpoint']) . $pickupTime;
													?>
													<option value="<?php echo $d; ?>"><?php echo $d; ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									<?php endif; ?>
									<!-- Extra Services -->
									<?php
										wbtm_extra_services_section($bus_id);
									?>
									<!-- Extra Services END -->
								</div>
							</div>
							<p class="wbtm-booking-error"><?php _e('Seat limit exceeded!', 'bus-ticket-booking-with-seat-reservation'); ?></p>
							<div id="wbtm-form-builder">
								<img class="wbtm-loading" src="<?php echo plugin_dir_url(__FILE__) . '../public/' . '/images/new-loading.gif'; ?>" alt=""/>
								<div id="wbtm-form-builder-adult" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
								<div id="wbtm-form-builder-child" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
								<div id="wbtm-form-builder-infant" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
								<div id="wbtm-form-builder-es" class="wbtm-form-builder-type-wrapper mage_customer_info_area"></div>
							</div>
							<?php if (mage_bus_total_seat_new($bus_id) > $partial_seat_booked) :
								do_action('wbtm_before_add_cart_btn', $bus_id, false);
								if (apply_filters('mage_bus_current_user_type', 'passenger') === 'counter_agent') :
									do_action('csad_booking_button'); ?>
								<?php else : ?>
									<button class="mage_button no-seat-submit-btn" disabled type="submit" name="add-to-cart" value="<?php echo get_post_meta($bus_id, 'link_wc_product', true); ?>" class="single_add_to_cart_button">
										<?php mage_bus_label('wbtm_book_now_text', __('Book Now', 'bus-ticket-booking-with-seat-reservation')); ?>
									</button>
								<?php endif; ?>
							<?php endif; ?>
						</div>
						<!-- No Seat Plan END -->
					<?php else : ?>
						<!-- Seat Plan -->
						<input type="hidden" name="wbtm_order_seat_plan" value="yes">
						<div class="mage_flex_justifyBetween">
							<?php
								$seat_plan_type = mage_get_bus_seat_plan_type();
								if ($seat_plan_type > 0) {
									$bus_width = 250;
								}
								else {
									$bus_width = 250;
								}
								mage_bus_seat_plan($seat_plan_type, $bus_width, $seat_price, $return);
							?>
							<div class="mage_bus_customer_sec mage_default" style="box-sizing:border-box;width: calc(100% - 8px - <?php echo $bus_width; ?>px);">
								<div class="flexEqual" style="align-items:flex-start">
									<div class="mage_bus_details_short">
										<h6>
                                    <span class='wbtm-details-page-list-label'><span class="fa fa-map-marker"></span><?php
		                                    mage_bus_label('wbtm_boarding_points_text', __('Boarding', 'bus-ticket-booking-with-seat-reservation')); ?></span>
											<?php echo $start; ?> <?php echo($show_boarding_time == 'yes' ? sprintf('(%s)', mage_wp_time($start_time)) : null); ?>
										</h6>
										<h6 class="mar_t_xs">
											<span class='wbtm-details-page-list-label'> <span class="fa fa-map-marker"></span><?php mage_bus_label('wbtm_dropping_points_text', __('Dropping', 'bus-ticket-booking-with-seat-reservation')); ?></span>
											<?php echo $end; ?> <?php echo($show_dropping_time == 'yes' ? sprintf('(%s)', mage_wp_time($end_time)) : null); ?>
										</h6>
										<?php if (mage_bus_type()) : ?>
											<h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'><i class="fa fa-bus" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_type_text', __('Coach Type:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
												<?php echo mage_bus_type(); ?>
											</h6>
										<?php endif; ?>
										<h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'><i class="fa fa-calendar" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_date_text', __('Date:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
											<?php echo mage_wp_date($date); ?>
										</h6>
										<?php if ($show_boarding_time == 'yes') { ?>
											<h6 class="mar_t_xs">
                                        <span class='wbtm-details-page-list-label'><i class="fa fa-clock-o" aria-hidden="true"></i>
                                            <?php mage_bus_label('wbtm_start_time_text', __('Start Time:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
												<?php echo $start_time; ?>
											</h6>
										<?php } ?>
										<h6 class="mar_t_xs">
                                    <span class='wbtm-details-page-list-label'>
                                        <i class="fa fa-money" aria-hidden="true"></i>
                                        <?php mage_bus_label('wbtm_fare_text', __('Fare:', 'bus-ticket-booking-with-seat-reservation')); ?></span>
											<?php echo wc_price(wbtm_get_price_including_tax($bus_id, $seat_price)); ?>/
											<span><?php mage_bus_label('wbtm_seat_text', __('Seat', 'bus-ticket-booking-with-seat-reservation')); ?></span>
										</h6>
										<h6 class="mar_t_xs wbtm-details-page-list-total-avl-seat">
											<strong><?php echo $mage_bus_total_seats_availabel
												?></strong>
											<span><?php mage_bus_label('wbtm_seat_available_text', __('Seat Available', 'bus-ticket-booking-with-seat-reservation')); ?></span>
										</h6>
									</div>
									<div class="textCenter mage_bus_seat_list">
										<div class="flexEqual mage_bus_selected_list">
											<h6>
												<strong><?php mage_bus_label('wbtm_seat_no_text', __('Seat No', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											</h6>
											<?php
												if (mage_bus_multiple_passenger_type_check($bus_id, $start, $end)) {
													?>
													<h6>
														<strong><?php _e('Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
													</h6>
													<?php
												}
											?>
											<h6>
												<strong><?php mage_bus_label('wbtm_fare_text', __('Fare', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											</h6>
											<h6>
												<strong><?php mage_bus_label('wbtm_remove_text', __('Remove', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
											</h6>
										</div>
										<div class="mage_bus_selected_seat_list"></div>
										<div class="mage_bus_selected_list mage_bus_sub_total padding">
											<h5>
												<strong><?php mage_bus_label('wbtm_qty_text', __('Qty :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
												<span class="mage_bus_total_qty">0</span>
											</h5>
											<h5>
												<strong><?php mage_bus_label('wbtm_sub_total_text', __('Seat Price :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
												<strong class="mage_bus_sub_total_price mage-price-total">
													<span class="price-figure"><?php echo wc_price(0); ?></span>
												</strong>
											</h5>
											<div class="mage_extra_bag">
												<h5>
													<strong><?php mage_bus_label('wbtm_extra_bag_text', __('Extra Bag :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
													<span class="mage_bus_extra_bag_qty">0</span>
													x
													<span class="mage_extra_bag_price"><?php echo wc_price(0); ?></span>
													=
													<strong class="mage_bus_extra_bag_total_price"><?php echo wc_price(0); ?></strong>
												</h5>
											</div>
										</div>
										<?php if ($pickpoints) : ?>
											<div class="wbtm-pickpoint-wrap" style="margin-top:20px">
												<label for="wbtm-pickpoint-no-seat"><?php _e('Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?>
													<span class="wbtm_required">*</span>
												</label>
												<select name="wbtm_pickpoint" id="wbtm-pickpoint-no-seat" required>
													<option value=""><?php _e('Select Pickup Point', 'bus-ticket-booking-with-seat-reservation') ?></option>
													<?php foreach ($pickpoints as $point) :
														$pickupTime = $point['time'] ? ' [' . $point['time'] . ']' : '';
														$d = ucfirst($point['pickpoint']) . $pickupTime;
														?>
														<option value="<?php echo $d; ?>"><?php echo $d ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										<?php endif; ?>
										<!-- Extra Services -->
										<?php
											wbtm_extra_services_section($bus_id);
										?>
										<!-- Extra Services END -->
									</div>
								</div>
								<div class="mage_customer_info_area">
									<!-- <input type="hidden" name="custom_reg_user" value="no" /> -->
								</div>
								<div class="flexEqual flexCenter textCenter_mar_t">
									<h4>
										<strong><?php mage_bus_label('wbtm_total_text', __('Total :', 'bus-ticket-booking-with-seat-reservation')); ?></strong>
										<strong class="mage_bus_total_price mage-grand-total">
											<span class="mage-price-figure"><?php echo wc_price(0); ?></span>
										</strong>
									</h4>
									<div>
										<?php if (mage_bus_total_seat_new($bus_id) > $partial_seat_booked) :
											do_action('wbtm_before_add_cart_btn', $bus_id, false);
											if (apply_filters('mage_bus_current_user_type', 'passenger') === 'counter_agent') :
												do_action('csad_booking_button'); ?>
											<?php else : ?>
												<button class="mage_button" type="submit" disabled name="add-to-cart" value="<?php echo get_post_meta($bus_id, 'link_wc_product', true); ?>" style="max-width:100%"><?php mage_bus_label('wbtm_book_now_text', __('Book Now', 'bus-ticket-booking-with-seat-reservation')); ?></button>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</div>
						<!-- Seat Plan END -->
					<?php endif; ?>
			</div>
		</form>
		<?php
		// if ($bus_seat_type_conf === 'wbtm_seat_plan') {
		//     do_action('mage_bus_hidden_customer_info_form');
		// }
		?>
		<?php
	}
//bus seat plan
	function mage_bus_seat_plan($seat_plan_type, $bus_width, $price, $return) {
		global $mage_bus_total_seats_availabel;
		$bus_id = get_the_id();
		$current_driver_position = get_post_meta($bus_id, 'driver_seat_position', true);
		$seat_panel_settings = get_option('wbtm_bus_settings');
		if (isset($seat_panel_settings['diriver_image'])) {
			if ($seat_panel_settings['diriver_image'] != '') {
				$driver_image = wp_get_attachment_url($seat_panel_settings['diriver_image']);
			}
			else {
				$driver_image = WBTM_PLUGIN_URL . '/assets/helper/images/driver-default.png';
			}
		}
		else {
			$driver_image = WBTM_PLUGIN_URL . '/assets/helper/images/driver-default.png';
		}
		$all_stopages_name = mage_bus_get_all_stopages(get_the_id());
		// upper deck
		$seats_dd = get_post_meta($bus_id, 'wbtm_bus_seats_info_dd', true);
		$seat_html = '';
		?>
		<div class="mage_bus_seat_plan" style="box-sizing:border-box;width: <?php echo $bus_width; ?>px;">
			<?php
				$upper_deck = (isset($seat_panel_settings['useer_deck_title']) ? $seat_panel_settings['useer_deck_title'] : __('Upper Deck', 'bus-ticket-booking-with-seat-reservation'));
				$lower_deck = (isset($seat_panel_settings['lower_deck_title']) ? $seat_panel_settings['lower_deck_title'] : __('Lower Deck', 'bus-ticket-booking-with-seat-reservation'));
				if (!empty($seats_dd)) {
					echo '<strong class="deck-type-text">' . __($lower_deck, 'bus-ticket-booking-with-seat-reservation') . '</strong>';
				}
			?>
			<div class="mage_default_pad_xs">
				<div class="flexEqual">
					<div class="padding">
						<img class="driver_img <?php echo ($current_driver_position == 'driver_left') ? 'mageLeft' : 'mageRight'; ?>" src="<?php echo $driver_image; ?>" alt="">
					</div>
				</div>
				<?php
					$mage_bus_total_seats_availabel = mage_bus_total_seat_new();
					if ($seat_plan_type > 0) {
						$seats_rows = get_post_meta($bus_id, 'wbtm_bus_seats_info', true) ? get_post_meta($bus_id, 'wbtm_bus_seats_info', true) : [];
						$seat_col = get_post_meta($bus_id, 'wbtm_seat_cols', true);
						// $seat_html .= '<div class="defaultLoaderFixed"><span></span></div>';
						foreach ($seats_rows as $seat) {
							$seat_html .= '<div class="flexEqual mage_bus_seat">';
							for ($i = 1; $i <= $seat_col; $i++) {
								$seat_name = $seat["seat" . $i];
								$seat_html .= mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, 0);
							}
							$seat_html .= '</div>';
						}
						echo $seat_html;
					}
					elseif ($seat_plan_type == 'seat_plan_1' || $seat_plan_type == 'seat_plan_2' || $seat_plan_type == 'seat_plan_3') {
						$bus_meta = get_post_custom($bus_id);
						if (isset($bus_meta['wbtm_seat_row'][0])) {
							$seats_rows = explode(",", $bus_meta['wbtm_seat_row'][0]);
							$seat_col = $bus_meta['wbtm_seat_col'][0];
							$seat_col_arr = explode(",", $seat_col);
							foreach ($seats_rows as $seat) {
								echo '<div class="flexEqual mage_bus_seat">';
								foreach ($seat_col_arr as $seat_col) {
									$seat_name = $seat . $seat_col;
									// $mage_bus_total_seats_availabel = mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, $seat_col, $all_stopages_name, $mage_bus_total_seats_availabel);
									echo mage_bus_seat($seat_plan_type, $seat_name, $price, false, $return, $seat_col);
								}
								echo '</div>';
							}
						}
					}
					else {
						echo 'Please update Your Seat Plan !';
					}
				?>
			</div>
			<?php
				$seat_col_dd = get_post_meta($bus_id, 'wbtm_seat_cols_dd', true);
				if (is_array($seats_dd) && sizeof($seats_dd) > 0) {
					$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
					$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
					$price = mage_bus_seat_price($bus_id, $start, $end, true);
					if (!empty($seats_dd)) {
						echo '<strong class="deck-type-text">' . __($upper_deck, 'bus-ticket-booking-with-seat-reservation') . '</strong>';
					}
					echo '<div class="mage_default_pad_xs_mar_t" style="margin-top: 4px!important;">';
					foreach ($seats_dd as $seat) {
						echo '<div class="flexEqual mage_bus_seat">';
						for ($i = 1; $i <= $seat_col_dd; $i++) {
							$seat_name = $seat["dd_seat" . $i];
							echo mage_bus_seat($seat_plan_type, $seat_name, $price, true, $return, 0);
						}
						echo '</div>';
					}
					echo '</div>';
				}
			?>
		</div>
		<?php
	}
//bus seat place
	function mage_bus_seat($seat_plan_type, $seat_name, $price, $dd, $return, $seat_col) {
		global $mage_bus_total_seats_availabel;
		$seat_panel_settings = get_option('wbtm_bus_settings');
		$blank_seat_img = $seat_panel_settings['seat_blank_image'];
		$cart_seat_img = $seat_panel_settings['seat_active_image'];
		$block_seat_img = $seat_panel_settings['seat_booked_image'];
		$sold_seat_img = $seat_panel_settings['seat_sold_image'];
		$start = $return ? mage_bus_isset('bus_end_route') : mage_bus_isset('bus_start_route');
		$end = $return ? mage_bus_isset('bus_start_route') : mage_bus_isset('bus_end_route');
		ob_start();
		if (strtolower($seat_name) == 'door') {
			echo '<div></div>';
		}
		elseif (strtolower($seat_name) == 'wc') {
			echo '<div></div>';
		}
		elseif ($seat_name == '') {
			echo '<div></div>';
		}
		else {
			// GET status, boarding_point, dropping_point
			$all_stopages_name = get_post_meta(get_the_ID(), 'wbtm_bus_bp_stops', true);
			$all_stopages_name = is_array($all_stopages_name) ? $all_stopages_name : unserialize($all_stopages_name);
			$all_stopages_name = array_column($all_stopages_name, 'wbtm_bus_bp_stops_name');
			$partial_route_condition = false; // init value
			$get_search_start_position = array_search($start, $all_stopages_name);
			$get_search_droping_position = array_search($end, $all_stopages_name);
			$get_search_droping_position = (is_bool($get_search_droping_position) && !$get_search_droping_position ? count($all_stopages_name) : $get_search_droping_position); // Last Stopage position assign
			$get_booking_data = get_seat_booking_data($seat_name, $get_search_start_position, $get_search_droping_position, $all_stopages_name, $return);
			$seat_status = $get_booking_data['status'];
			$partial_route_condition = $get_booking_data['has_booked'];
			// Seat booked show policy in search
			$seat_booked_status_default = array(1, 2);
			$seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);
			// Seat booked show policy in search
			if (wbtm_find_seat_in_cart($seat_name, $return)) {
				?>
				<div class="flex_justifyCenter mage_seat_in_cart" title="<?php _e('Already Added in cart !', 'bus-ticket-booking-with-seat-reservation'); ?>">
					<?php
						if ($cart_seat_img) {
							echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($cart_seat_img) . '" alt="Block" /></div>';
						}
						else {
							echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
						}
					?>
				</div>
				<?php
			}
			elseif (($seat_status == 1 || $seat_status == 3 || $seat_status == 4 || $seat_status == 5 || $seat_status == 6 || $seat_status == 7) && in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) {
				$mage_bus_total_seats_availabel--; // for seat available
				?>
				<div class="flex_justifyCenter mage_seat_booked" title="<?php _e('Already Booked By another!', 'bus-ticket-booking-with-seat-reservation'); ?>">
					<?php
						if ($block_seat_img) {
							echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($block_seat_img) . '" alt="Block" /></div>';
						}
						else {
							echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
						}
					?>
				</div>
				<?php
			}
			elseif (in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) {
				$mage_bus_total_seats_availabel--; // for seat available
				?>
				<div class="flex_justifyCenter mage_seat_confirmed" title="<?php _e('Already Sold By another!', 'bus-ticket-booking-with-seat-reservation'); ?>">
					<?php
						if ($sold_seat_img) {
							echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($sold_seat_img) . '" alt="Block" /></div>';
						}
						else {
							echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
						}
					?>
				</div>
				<?php
			}
			else {
				?>
				<div class="flex_justifyCenter mage_bus_seat_item" data-bus-dd="<?php echo $dd ? 'yes' : 'no'; ?>" data-price="<?php echo $price; ?>" data-seat-name="<?php echo $seat_name; ?>" data-passenger-type="0">
					<?php
						if ($blank_seat_img) {
							echo '<div><p>' . $seat_name . '</p><img src="' . wp_get_attachment_url($blank_seat_img) . '" alt="Block" /></div>';
						}
						else {
							echo '<span class="mage_bus_seat_icon">' . $seat_name . '<span class="bus_handle"></span></span>';
						}
					?>
					<?php mage_bus_passenger_type($return, $dd) ?>
				</div>
				<?php
			}
			if (($seat_plan_type == 'seat_plan_1' && $seat_col == 2) || ($seat_plan_type == 'seat_plan_2' && $seat_col == 1) || ($seat_plan_type == 'seat_plan_3' && $seat_col == 2)) {
				echo '<div></div>';
			}
		}
		return ob_get_clean();
	}
	function wbtm_seat_global($b_start, $date, $type = '', $return = false) {
		global $wbtmmain;
		$seat_panel_settings = get_option('wbtm_bus_settings');
		$driver_image = $seat_panel_settings['diriver_image'] ? wp_get_attachment_url($seat_panel_settings['diriver_image'], 'full') : WBTM_PLUGIN_URL . '/assets/helper/images/driver-default.png';
		$blank_seat_image = $seat_panel_settings['seat_blank_image'] ? wp_get_attachment_url($seat_panel_settings['seat_blank_image'], 'full') : WBTM_PLUGIN_URL . '/assets/helper/images/seat-empty.png';
		$blank_active_image = $seat_panel_settings['seat_active_image'] ? wp_get_attachment_url($seat_panel_settings['seat_active_image'], 'full') : WBTM_PLUGIN_URL . '/assets/helper/images/seat-selected.png';
		$blank_booked_image = $seat_panel_settings['seat_booked_image'] ? wp_get_attachment_url($seat_panel_settings['seat_booked_image'], 'full') : WBTM_PLUGIN_URL . '/assets/helper/images/seat-booked.png';
		$blank_sold_image = $seat_panel_settings['seat_sold_image'] ? wp_get_attachment_url($seat_panel_settings['seat_sold_image'], 'full') : WBTM_PLUGIN_URL . '/assets/helper/images/seat-sold.png';
		$useer_deck_title = ($seat_panel_settings['useer_deck_title'] != '' ? $seat_panel_settings['useer_deck_title'] : __('Upper Deck', 'bus-ticket-booking-with-seat-reservation'));
		?>
		<style>
			/* html body .admin-bus-details td a {
			 height: 50px;
		 } */
			.blank_seat {
				background: url(<?php echo $blank_seat_image; ?>) no-repeat center center !important;
				min-height: 44px;
			}
			.seat_booked, .seat_booked:hover {
				background: url(<?php echo $blank_active_image; ?>) no-repeat center center !important;
				min-height: 44px;
			}
			span.booked-seat {
				background: url(<?php echo $blank_booked_image; ?>) no-repeat center center !important;
				min-height: 44px;
			}
			span.confirmed-seat {
				background: url(<?php echo $blank_sold_image; ?>) no-repeat center center !important;
				min-height: 44px;
			}
		</style>
		<?php
		$price_arr = maybe_unserialize(get_post_meta(get_the_id(), 'wbtm_bus_prices' . ($return ? "_return" : ""), true));
		if ($type && $type == 'dd') {
			$seats = get_post_meta(get_the_id(), 'wbtm_bus_seats_info_dd', true);
			$seatcols = get_post_meta(get_the_id(), 'wbtm_seat_rows_dd', true);
			$end = isset($_GET['bus_end_route']) ? strip_tags($_GET['bus_end_route']) : '';
			if (is_array($seats) && sizeof($seats) > 0) {
				?>
				<div class="bus-seat-panel-dd">
					<h6><?php echo $useer_deck_title; ?></h6>
					<table class="bus-seats" width="300" border="1" style="width: 211px; border: 0;">
						<?php
							foreach ($seats as $_seats) {
								?>
								<tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
									<?php
										for ($x = 1; $x <= $seatcols; $x++) {
											$text_field_name = "dd_seat" . $x;
											$seat_name = $_seats[$text_field_name];
											$get_seat_status = $wbtmmain->wbtm_get_seat_status($_seats[$text_field_name], $date, get_the_id(), $b_start, $end);
											if ($get_seat_status) {
												$seat_status = $get_seat_status;
											}
											else {
												$seat_status = 0;
											}
											?>
											<td align="center">
												<?php
													if ($_seats[$text_field_name]) { ?>
														<?php if ($seat_status == 1) { ?>
															<span class="booked-seat"><?php echo $seat_name; ?></span>
														<?php } elseif ($seat_status == 2) { ?>
															<span class="confirmed-seat"><?php echo $seat_name; ?></span>
														<?php } else { ?>
															<a data-seat='<?php echo $_seats[$text_field_name]; ?>'
																id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
																data-sclass='Economic'
																class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
																<?php echo $_seats[$text_field_name]; ?></a>
														<?php }
													} ?>
											</td>
											<?php
										}
									?>
								</tr>
							<?php } ?>
					</table>
				</div>
				<?php
			}
		}
		else {
			$seats = get_post_meta(get_the_id(), 'wbtm_bus_seats_info', true);
			$current_driver_position = get_post_meta(get_the_id(), 'driver_seat_position', true);
			$seatcols = get_post_meta(get_the_id(), 'wbtm_seat_cols', true);
			if ($current_driver_position) {
				$current_driver = $current_driver_position;
			}
			else {
				$current_driver = 'driver_right';
			}
			$start = isset($_GET['bus_start_route']) ? strip_tags($_GET['bus_start_route']) : '';
			$end = isset($_GET['bus_end_route']) ? strip_tags($_GET['bus_end_route']) : '';
			$fare = $wbtmmain->wbtm_get_bus_price($start, $end, $price_arr);
			?>
			<div class="bus-seat-panel-ss">
				<div style='border: 1px solid #ddd;padding: 5px;width:204px; text-align:<?php if ($current_driver == 'driver_left') {
					echo 'left';
				}
				else {
					echo 'right';
				} ?>'>
					<img src="<?php echo $driver_image; ?>" alt="">
				</div>
				<?php
					// upper deck
					$seats_dd = get_post_meta(get_the_id(), 'wbtm_bus_seats_info_dd', true);
					if (!empty($seats_dd)) {
						echo '<strong style="width:216px;background:#f1f1f1;text-align: center;display: block;font-size: 11px;color: #4CAF50;">' . __('Lower Deck', 'bus-ticket-booking-with-seat-reservation') . '</strong>';
					}
				?>
				<table class="bus-seats" width="300" border="1" style="width: 220px;margin-left:-2px;
    border: 0px solid #ddd;">
					<?php foreach ($seats as $_seats) { ?>
						<tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
							<?php
								for ($x = 1; $x <= $seatcols; $x++) {
									$text_field_name = "seat" . $x;
									$seat_name = $_seats[$text_field_name];
									// Intermidiate route check
									// GET status, boarding_point, dropping_point
									$all_stopages_name = get_post_meta(get_the_ID(), 'wbtm_bus_bp_stops', true);
									$all_stopages_name = is_array($all_stopages_name) ? $all_stopages_name : unserialize($all_stopages_name);
									$all_stopages_name = array_column($all_stopages_name, 'wbtm_bus_bp_stops_name');
									$get_search_start_position = array_search($start, $all_stopages_name);
									$get_search_droping_position = array_search($end, $all_stopages_name);
									$get_search_droping_position = (is_bool($get_search_droping_position) && !$get_search_droping_position ? count($all_stopages_name) : $get_search_droping_position); // Last Stopage position assign
									$get_booking_data = get_seat_booking_data($seat_name, $get_search_start_position, $get_search_droping_position, $all_stopages_name, false, get_the_ID());
									$seat_status = isset($get_booking_data['status']) ? $get_booking_data['status'] : 0;
									$partial_route_condition = isset($get_booking_data['has_booked']) ? $get_booking_data['has_booked'] : false;
									// Seat booked show policy in search
									$seat_booked_status_default = array(1, 2);
									$seat_booked_status = (isset(get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status']) ? get_option('wbtm_bus_settings')['bus_seat_booked_on_order_status'] : $seat_booked_status_default);
									// Intermidiate route check End
									?>
									<td align="center" class="mage-admin-bus-seat <?php echo($_seats[$text_field_name] == '' ? 'bus-col-divider' : '') ?>">
										<?php
											if ($_seats[$text_field_name]) { ?>
												<?php if (in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) { ?>
													<span class="booked-seat"><?php echo $seat_name; ?></span>
												<?php } elseif (in_array($seat_status, $seat_booked_status) && $partial_route_condition === true) { ?>
													<span class="confirmed-seat"><?php echo $seat_name; ?></span>
												<?php } else { ?>
													<a data-seat='<?php echo $_seats[$text_field_name]; ?>' data-seat-pos="lower" data-seat-fare="<?php echo $fare ?>" data-seat-uid='<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
														id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
														data-sclass='Economic'
														class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
														<?php echo $_seats[$text_field_name]; ?></a>
													<?php mage_bus_passenger_type_admin($return, false) ?>
												<?php }
											} ?>
									</td>
									<?php
								}
							?>
						</tr>
					<?php } ?>
				</table>
				<?php
					$seat_col_dd = get_post_meta(get_the_id(), 'wbtm_seat_cols_dd', true);
					$upper_price_percent = (int)get_post_meta(get_the_ID(), 'wbtm_seat_dd_price_parcent', true);
					$fare = $wbtmmain->wbtm_get_bus_price($start, $end, $price_arr);
					if ($fare) {
						$fare = $fare + ($upper_price_percent != 0 ? (($fare * $upper_price_percent) / 100) : 0);
					}
					if (is_array($seats_dd) && sizeof($seats_dd) > 0) :
						if (!empty($seats_dd)) {
							echo '<strong style="width: 216px;background:#f1f1f1;text-align: center;display: block;font-size: 11px;color: #4CAF50;">' . $useer_deck_title . '</strong>';
						}
						?>
						<table class="bus-seats" width="300" border="1" style="width: 220px;margin-left:-2px;
    border: 0px solid #ddd;">
							<?php
								foreach ($seats_dd as $_seats) : ?>
									<tr class="seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_lists ">
										<?php for ($x = 1; $x <= $seat_col_dd; $x++) :
											$text_field_name = "dd_seat" . $x;
											$seat_name = $_seats[$text_field_name];
											$get_seat_status = $wbtmmain->wbtm_get_seat_status($_seats[$text_field_name], $date, get_the_id(), $b_start, $end);
											if ($get_seat_status) {
												$seat_status = $get_seat_status;
											}
											else {
												$seat_status = 0;
											}
											?>
											<td align="center"
												class="mage-admin-bus-seat <?php echo($_seats[$text_field_name] == '' ? 'bus-col-divider' : '') ?>">
												<?php
													if ($_seats[$text_field_name]) : ?>
														<?php if ($seat_status == 1) { ?>
															<span class="booked-seat"><?php echo $seat_name; ?></span>
														<?php } elseif ($seat_status == 2) { ?>
															<span class="confirmed-seat"><?php echo $seat_name; ?></span>
														<?php } else { ?>
															<a data-seat='<?php echo $_seats[$text_field_name]; ?>'
																data-seat-pos="upper"
																data-seat-fare="<?php echo $fare ?>"
																data-seat-uid='<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
																id='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_<?php echo $_seats[$text_field_name]; ?>'
																data-sclass='Economic'
																class='seat<?php echo get_the_id() . $wbtmmain->wbtm_make_id($date); ?>_blank blank_seat'>
																<?php echo $_seats[$text_field_name]; ?></a>
															<?php mage_bus_passenger_type_admin($return, true) ?>
														<?php } ?>
													<?php endif; ?>
											</td>
										<?php endfor; ?>
									</tr>
								<?php endforeach; ?>
						</table>
					<?php endif; ?>
			</div>
			<?php
		}
	}