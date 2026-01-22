<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} 
    // Cannot access pages directly.
	/*$post_id = $post_id ?? WBTM_Global_Function::data_sanitize($_POST['post_id']);
	$start_route = $start_route ?? WBTM_Global_Function::data_sanitize($_POST['start_route']);
	$end_route = $end_route ?? WBTM_Global_Function::data_sanitize($_POST['end_route']);*/

    /*$post_id = $post_id ?? '';
    $start_route = $start_route ?? '';
    $end_route = $end_route ?? '';
    $date = $date ?? '';

	*/

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $ticket_infos = $ticket_infos ?? WBTM_Functions::get_ticket_info($post_id, $start_route, $end_route);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $seat_column = $seat_column ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $seat_row = $seat_row ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $seat_infos = $seat_infos ?? WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $cabin_config = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_config', []);
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $cabin_mode_enabled = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_mode_enabled', 'no');

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $has_cabin_seat_plan = false;
    if ($cabin_mode_enabled === 'yes' && !empty($cabin_config)) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        foreach ($cabin_config as $index => $cabin) {
            if (($cabin['enabled'] ?? 'yes') !== 'yes') {
                continue;
            }
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $rows = intval($cabin['rows'] ?? 0);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $cols = intval($cabin['cols'] ?? 0);
            if ($rows <= 0 || $cols <= 0) {
                continue;
            }
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $cabin_seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $index, []);
            if (!empty($cabin_seat_infos)) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $has_cabin_seat_plan = true;
                break;
            }
        }
    }

    if ($has_cabin_seat_plan || (sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0)) {
    //		$date = $_POST['date'] ?? '';
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $bus_start_time=$bus_start_time??'';
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $seat_position = WBTM_Global_Function::get_post_info($post_id, 'driver_seat_position', 'driver_left');
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $show_upper_desk = WBTM_Global_Function::get_post_info($post_id, 'show_upper_desk');
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $seat_infos_dd = WBTM_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info_dd', []);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $adult_price = WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_infos[0]['price']);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $enable_rotation = WBTM_Global_Function::get_post_info($post_id, 'wbtm_enable_seat_rotation');
            //echo current($seat_infos)['price'];
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $seat_booked=WBTM_Query:: query_seat_booked($post_id, $start_route, $end_route, $bus_start_time);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $seat_count = 0;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            foreach ($seat_infos as $seats) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                foreach ($seats as $seat) {
                    if (!empty($seat)) {
                        $seat_count++;
                    }
                }
            }
            // Fetch reopened seats for this bus and date
            $all_reopened = WBTM_Global_Function::get_post_info($post_id, '_wbtm_reopened_seats', []);
            $date_key = gmdate('Y-m-d', strtotime($date));
            $current_reopened = isset($all_reopened[$date_key]) ? $all_reopened[$date_key] : [];
            ?>
            <div class="_dLayout_xs">
                <?php //echo '<pre>'; print_r($seat_booked); echo '</pre>'; ?>
                <div class="wbtm_seat_plan_area">
                    <?php if ($has_cabin_seat_plan) { ?>
                        <?php
                        // Render cabin seat plans
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                        foreach ($cabin_config as $cabin_index => $cabin) {
                            if (($cabin['enabled'] ?? 'yes') !== 'yes') continue;
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $cabin_name = $cabin['name'] ?? 'Cabin ' . ($cabin_index + 1);
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $cabin_rows = $cabin['rows'] ?? 0;
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $cabin_cols = $cabin['cols'] ?? 0;
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $price_multiplier = $cabin['price_multiplier'] ?? 1.0;
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                            $cabin_seat_infos = WBTM_Global_Function::get_post_info($post_id, 'wbtm_cabin_seats_info_' . $cabin_index, []);

                            if ($cabin_rows > 0 && $cabin_cols > 0 && !empty($cabin_seat_infos)) {
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                $cabin_price = $adult_price * $price_multiplier;
                                ?>
                                <div class="wbtm_cabin_section">
                                    <div class="wbtm_cabin_header wbtm_cabin_toggle" data-cabin-index="<?php echo esc_attr($cabin_index); ?>" style="cursor: pointer;">
                                        <div class="wbtm_cabin_title_container">
                                            <h4 class="wbtm_cabin_title" id="cabin-<?php echo esc_attr($cabin_index); ?>-title"><?php echo esc_html($cabin_name); ?></h4>
                                            <?php if ($price_multiplier != 1.0): ?>
                                                <div class="wbtm_cabin_price_info">
                                                    <?php if ($price_multiplier > 1.0): ?>
                                                        <span class="wbtm_price_multiplier">
                                                            <?php
                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                            $premium = ( $price_multiplier - 1.0 ) * 100;
                                                            echo esc_html( '+' . number_format_i18n( $premium ) . '% Premium' );
                                                            ?>
                                                        </span>
                                                    <?php elseif ($price_multiplier < 1.0): ?>
                                                        <span class="wbtm_price_discount">
                                                            <?php
                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                            $discount = ( 1.0 - $price_multiplier ) * 100;
                                                            echo esc_html( '-' . number_format_i18n( $discount ) . '% Discount' );
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="wbtm_cabin_toggle_icon">
                                            <span class="wbtm_toggle_arrow" aria-label="Toggle cabin seats">▼</span>
                                        </div>
                                    </div>

                                    <div class="wbtm_cabin_seat_plan ovAuto" style="display: none;" aria-expanded="false" role="region" aria-labelledby="cabin-<?php echo esc_attr($cabin_index); ?>-title" data-cabin-index="<?php echo esc_attr($cabin_index); ?>">
                                        <input type="hidden" name="wbtm_selected_seat_cabin_<?php echo esc_attr($cabin_index); ?>" value=""/>
                                        <input type="hidden" name="wbtm_selected_seat_type_cabin_<?php echo esc_attr($cabin_index); ?>" value=""/>
                                        <table>
                                            <thead>
                                            <tr>
                                                <th colspan="<?php echo esc_attr($cabin_cols); ?>">
                                                    <div class="wbtm_cabin_direction">
                                                        <span class="wbtm_direction_text"><?php esc_html_e('Front', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                                                    </div>
                                                </th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                            foreach ($cabin_seat_infos as $row_index => $seat_info): ?>
                                                <tr>
                                                    <?php
                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                    foreach ($seat_info as $seat_key => $seat_name): ?>
                                                        <?php
                                                        // Skip rotation keys (they end with _rotation)
                                                        if (strpos($seat_key, '_rotation') !== false) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <?php if ($seat_name): ?>
                                                            <?php
                                                            $seat_name_lower = strtolower($seat_name);
                                                            $special_types = ['door', 'wc', 'toilet', 'food', 'kitchen', 'staff', 'devider'];
                                                            if (in_array($seat_name_lower, $special_types)) {
                                                                $icon_class = '';
                                                                if ($seat_name_lower == 'door') $icon_class = 'fas fa-door-open';
                                                                elseif ($seat_name_lower == 'wc' || $seat_name_lower == 'toilet') $icon_class = 'fas fa-restroom';
                                                                elseif ($seat_name_lower == 'food' || $seat_name_lower == 'kitchen') $icon_class = 'fas fa-utensils';
                                                                elseif ($seat_name_lower == 'staff') $icon_class = 'fas fa-user-tie';
                                                                ?>
                                                                <td>
                                                                    <div class="wbtm_special_seat wbtm_type_<?php echo esc_attr($seat_name_lower); ?>" title="<?php echo esc_attr($seat_name_lower == 'devider' ? '' : ucfirst($seat_name)); ?>">
                                                                        <?php if ($seat_name_lower != 'devider'): ?>
                                                                            <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                                                            <span class="wbtm_special_label"><?php echo esc_html($seat_name_lower == 'wc' ? 'Toilet' : ucfirst($seat_name)); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            <?php } else { ?>
                                                                <?php
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $rotation = 0;
                                                                if ($enable_rotation == 'yes' && isset($seat_info[$seat_key . '_rotation'])) {
                                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                    $rotation = intval($seat_info[$seat_key . '_rotation']);
                                                                }
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';

                                                                $is_reserved = false;
                                                                if (stripos($seat_name, 'reserved') === 0) {
                                                                    $raw_seat_name = $seat_name;
                                                                    if (stripos($seat_name, 'reserved:') === 0) {
                                                                        $seat_name = trim(substr($seat_name, 9));
                                                                    } else {
                                                                        // Handle "reserved" without colon - extract seat name if possible
                                                                        $seat_name = trim(str_ireplace('reserved', '', $seat_name));
                                                                    }
                                                                    if (empty($seat_name)) {
                                                                        $seat_name = $raw_seat_name;
                                                                    }
                                                                    $cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $seat_name;
                                                                    if (!in_array($seat_name, $current_reopened) && !in_array($cabin_seat_identifier, $current_reopened)) {
                                                                        $is_reserved = true;
                                                                    }
                                                                }

                                                                // Enhanced by Shahnur Alam - 2025-10-08
                                                                // Fix cabin seat availability check - use cabin-specific identifiers
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $cabin_seat_identifier = 'cabin_' . $cabin_index . '_' . $seat_name;
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $is_booked = $is_reserved || in_array($cabin_seat_identifier, $seat_booked) || in_array($seat_name, $seat_booked);
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $is_in_cart = !$is_booked && (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $cabin_seat_identifier) || WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name));
                                                                ?>
                                                                <th>
                                                                    <div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
                                                                         <?php if ($is_booked || $is_reserved): ?>
                                                                             <div class="mp_seat seat_booked <?php echo $is_reserved ? 'seat_reserved' : ''; ?>" title="<?php echo $is_reserved ? esc_attr__('Reserved', 'bus-ticket-booking-with-seat-reservation') : esc_html( WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name) ); ?>">
                                                                                  <div class="seat_visual"></div>
                                                                                  <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                                  <?php if ($is_reserved && current_user_can('manage_options')): ?>
                                                                                      <div class="wbtm_reopen_action" data-seat="<?php echo esc_attr($cabin_seat_identifier); ?>" data-post_id="<?php echo esc_attr($post_id); ?>" data-date="<?php echo esc_attr($date_key); ?>" title="<?php esc_attr_e('Reopen for online sales', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                                                                          <i class="fas fa-unlock"></i>
                                                                                      </div>
                                                                                  <?php endif; ?>
                                                                              </div>
                                                                        <?php elseif ($is_in_cart): ?>
                                                                            <div class="mp_seat seat_in_cart" title="<?php echo esc_html( WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name) ); ?>">
                                                                                <div class="seat_visual"></div>
                                                                                <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
                                                                                data-seat_name="<?php echo esc_attr($seat_name); ?>"
                                                                                data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
                                                                                data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                                data-seat_price="<?php echo esc_attr($cabin_price); ?>"
                                                                                data-cabin_index="<?php echo esc_attr($cabin_index); ?>"
                                                                            >
                                                                                <div class="seat_visual"></div>
                                                                                <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                            </div>
                                                                            <?php if (sizeof($ticket_infos) > 1): ?>
                                                                                <div class="wbtm_seat_item_list">
                                                                                    <ul class="mp_list">
                                                                                        <?php
                                                                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                        foreach ($ticket_infos as $key => $ticket_info): ?>
                                                                                            <?php
                                                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                            $ticket_price = $key > 0 ? $ticket_info['price'] : $adult_price;
                                                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                            $ticket_price = $ticket_price * $price_multiplier;
                                                                                            ?>
                                                                                            <li class="justifyBetween"
                                                                                                data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
                                                                                                data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
                                                                                                data-seat_price="<?php echo esc_attr($ticket_price); ?>"
                                                                                            >
                                                                                                <span><?php echo esc_html($ticket_info['name']); ?></span>
                                                                                                -
                                                                                                <span><?php echo wp_kses_post( wc_price( $ticket_price ) ); ?></span>
                                                                                            </li>
                                                                                        <?php endforeach; ?>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </th>
                                                            <?php } ?>
                                                        <?php else: ?>
                                                            <td></td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if (sizeof($cabin_config) > 1): ?>
                                        <div class="wbtm_cabin_separator"></div>
                                    <?php endif; ?>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    <?php } else { ?>
                        <!-- Legacy single bus seat plan -->
                        <div class="wbtm_seat_plan_lower ovAuto">
                            <input type="hidden" name="wbtm_selected_seat" value=""/>
                            <input type="hidden" name="wbtm_selected_seat_type" value=""/>
                            <table>
                                <thead>
                                <tr>
                                    <th colspan="<?php echo esc_attr($seat_column); ?>">
                                        <div class="mp_driver_image <?php echo esc_attr($seat_position == 'driver_left' ? '' : 'fRight'); ?>">
                                            <?php WBTM_Custom_Layout::bg_image('', WBTM_PLUGIN_URL . '/assets/images/wbtm-driving-wheel.svg'); ?>
                                        </div>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                foreach ($seat_infos as $seat_info) { ?>
                                    <tr>
                                        <?php
                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                        foreach ($seat_info as $seat_key => $seat_name) { ?>
                                            <?php
                                            // Skip rotation keys (they end with _rotation)
                                            if (strpos($seat_key, '_rotation') !== false) {
                                                continue;
                                            }
                                            ?>
                                            <?php if ($seat_name) { ?>
                                                <?php
                                                $seat_name_lower = strtolower($seat_name);
                                                $special_types = ['door', 'wc', 'toilet', 'food', 'kitchen', 'staff', 'devider'];
                                                if (in_array($seat_name_lower, $special_types)) {
                                                    $icon_class = '';
                                                    if ($seat_name_lower == 'door') $icon_class = 'fas fa-door-open';
                                                    elseif ($seat_name_lower == 'wc' || $seat_name_lower == 'toilet') $icon_class = 'fas fa-restroom';
                                                    elseif ($seat_name_lower == 'food' || $seat_name_lower == 'kitchen') $icon_class = 'fas fa-utensils';
                                                    elseif ($seat_name_lower == 'staff') $icon_class = 'fas fa-user-tie';
                                                    ?>
                                                    <td>
                                                        <div class="wbtm_special_seat wbtm_type_<?php echo esc_attr($seat_name_lower); ?>" title="<?php echo esc_attr($seat_name_lower == 'devider' ? '' : ucfirst($seat_name)); ?>">
                                                            <?php if ($seat_name_lower != 'devider'): ?>
                                                                <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                                                <span class="wbtm_special_label"><?php echo esc_html($seat_name_lower == 'wc' ? 'Toilet' : ucfirst($seat_name)); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                <?php } else { ?>
                                                    <?php
                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                    $rotation = 0;
                                                    if ($enable_rotation == 'yes' && isset($seat_info[$seat_key . '_rotation'])) {
                                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                        $rotation = intval($seat_info[$seat_key . '_rotation']);
                                                    }
                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                    $rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';
                                                    ?>
                                                    <th>
                                                        <div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
                                                            <?php
                                                                $is_reserved_legacy = false;
                                                                if (stripos($seat_name, 'reserved') === 0) {
                                                                    $raw_seat_name = $seat_name;
                                                                    if (stripos($seat_name, 'reserved:') === 0) {
                                                                        $seat_name = trim(substr($seat_name, 9));
                                                                    } else {
                                                                        // Handle "reserved" without colon - extract seat name if possible
                                                                        $seat_name = trim(str_ireplace('reserved', '', $seat_name));
                                                                    }
                                                                    if (empty($seat_name)) {
                                                                        $seat_name = $raw_seat_name;
                                                                    }
                                                                    if (!in_array($seat_name, $current_reopened)) {
                                                                        $is_reserved_legacy = true;
                                                                    }
                                                                }

                                                                // Enhanced by Shahnur Alam - 2025-10-08
                                                                // Check both legacy seat names and cabin-specific identifiers for backward compatibility
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $is_booked_legacy = $is_reserved_legacy || in_array($seat_name, $seat_booked);
                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                $is_in_cart_legacy = !$is_booked_legacy && WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $seat_name);
                                                                ?>
                                                                 <?php if ($is_booked_legacy || $is_reserved_legacy) { ?>
                                                                     <div class="mp_seat seat_booked <?php echo $is_reserved_legacy ? 'seat_reserved' : ''; ?>" title="<?php echo $is_reserved_legacy ? esc_attr__('Reserved', 'bus-ticket-booking-with-seat-reservation') : esc_html( WBTM_Translations::text_already_sold() . ' : ' . esc_attr($seat_name) ); ?>">
                                                                          <div class="seat_visual"></div>
                                                                          <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                          <?php if ($is_reserved_legacy && current_user_can('manage_options')): ?>
                                                                              <div class="wbtm_reopen_action" data-seat="<?php echo esc_attr($seat_name); ?>" data-post_id="<?php echo esc_attr($post_id); ?>" data-date="<?php echo esc_attr($date_key); ?>" title="<?php esc_attr_e('Reopen for online sales', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                                                                  <i class="fas fa-unlock"></i>
                                                                              </div>
                                                                          <?php endif; ?>
                                                                      </div>
                                                            <?php } elseif ($is_in_cart_legacy) { ?>
                                                                <div class="mp_seat seat_in_cart" title="<?php echo esc_html( WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($seat_name) ); ?>">
                                                                    <div class="seat_visual"></div>
                                                                    <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                </div>
                                                            <?php } else { ?>
                                                                <div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($seat_name); ?>"
                                                                    data-seat_name="<?php echo esc_attr($seat_name); ?>"
                                                                    data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
                                                                    data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                    data-seat_price="<?php echo esc_attr($adult_price); ?>"
                                                                >
                                                                    <div class="seat_visual"></div>
                                                                    <div class="seat_number"><?php echo esc_html($seat_name); ?></div>
                                                                </div>
                                                                <?php if (sizeof($ticket_infos) > 1) { ?>
                                                                    <div class="wbtm_seat_item_list">
                                                                        <ul class="mp_list">
                                                                            <?php
                                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                            foreach ($ticket_infos as $key => $ticket_info) {
                                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                 $ticket_price = $key > 0 ? $ticket_info['price'] : $adult_price; ?>
                                                                                <li class="justifyBetween"
                                                                                    data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
                                                                                    data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
                                                                                    data-seat_price="<?php echo esc_attr($ticket_price); ?>"
                                                                                >
                                                                                    <span><?php echo esc_html($ticket_info['name']); ?></span>
                                                                                    -
                                                                                    <span><?php echo wp_kses_post( wc_price($ticket_price) ); ?></span>
                                                                                </li>
                                                                            <?php } ?>
                                                                        </ul>
                                                                    </div>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        </div>
                                                    </th>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <td></td>
                                            <?php } ?>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                    <?php if ($show_upper_desk == 'yes' && sizeof($seat_infos_dd) > 0) { ?>
                        <?php
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                        $seat_dd_increase = (int)WBTM_Global_Function::get_post_info($post_id, 'wbtm_seat_dd_price_parcent', 0);
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                        $adult_price_dd = $adult_price + ($adult_price * $seat_dd_increase / 100);
                        ?>
                        <div class="wbtm_seat_plan_upper ovAuto">
                            <input type="hidden" name="wbtm_selected_seat_dd" value=""/>
                            <input type="hidden" name="wbtm_selected_seat_dd_type" value=""/>
                            <div class="divider"></div>
                            <h4 class="_textCenter_textTheme"><?php echo esc_html( WBTM_Translations::text_upper_deck() ); ?></h4>
                            <div class="divider"></div>
                            <table>
                                <tbody>
                                <?php
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                foreach ($seat_infos_dd as $seat_info_dd) { ?>
                                    <tr>
                                        <?php
                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                        foreach ($seat_info_dd as $seat_key => $info) { ?>
                                            <?php
                                            // Skip rotation keys (they end with _rotation)
                                            if (strpos($seat_key, '_rotation') !== false) {
                                                continue;
                                            }
                                            ?>
                                            <?php if ($info) { ?>
                                                <?php
                                                $seat_name_lower = strtolower($info);
                                                $special_types = ['door', 'wc', 'toilet', 'food', 'kitchen', 'staff', 'devider'];
                                                if (in_array($seat_name_lower, $special_types)) {
                                                    $icon_class = '';
                                                    if ($seat_name_lower == 'door') $icon_class = 'fas fa-door-open';
                                                    elseif ($seat_name_lower == 'wc' || $seat_name_lower == 'toilet') $icon_class = 'fas fa-restroom';
                                                    elseif ($seat_name_lower == 'food' || $seat_name_lower == 'kitchen') $icon_class = 'fas fa-utensils';
                                                    elseif ($seat_name_lower == 'staff') $icon_class = 'fas fa-user-tie';
                                                    ?>
                                                    <th>
                                                        <div class="wbtm_special_seat wbtm_type_<?php echo esc_attr($seat_name_lower); ?>" title="<?php echo esc_attr($seat_name_lower == 'devider' ? '' : ucfirst($info)); ?>">
                                                            <?php if ($seat_name_lower != 'devider'): ?>
                                                                <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                                                <span class="wbtm_special_label"><?php echo esc_html($seat_name_lower == 'wc' ? 'Toilet' : ucfirst($info)); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </th>
                                                <?php } else { ?>
                                                    <?php
                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                    $rotation = 0;
                                                    if ($enable_rotation == 'yes' && isset($seat_info_dd[$seat_key . '_rotation'])) {
                                                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                        $rotation = intval($seat_info_dd[$seat_key . '_rotation']);
                                                    }
                                                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                    $rotation_class = $rotation > 0 ? 'wbtm_seat_rotated_' . $rotation : '';

                                                     $is_reserved_dd = false;
                                                     if (stripos($info, 'reserved') === 0) {
                                                         $raw_info = $info;
                                                         if (stripos($info, 'reserved:') === 0) {
                                                             $info = trim(substr($info, 9));
                                                         } else {
                                                             // Handle "reserved" without colon - extract seat name if possible
                                                             $info = trim(str_ireplace('reserved', '', $info));
                                                         }
                                                         if (empty($info)) {
                                                             $info = $raw_info;
                                                         }
                                                         if (!in_array($info, $current_reopened)) {
                                                             $is_reserved_dd = true;
                                                         }
                                                     }

                                                     // Enhanced by Shahnur Alam - 2025-10-08
                                                     // Fix upper deck seat availability check - support cabin-specific identifiers
                                                     // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                     $seat_available = WBTM_Query::query_total_booked($post_id, $start_route, $end_route, $date, '', $info);
                                                     ?>
                                                     <th>
                                                         <div class="mp_seat_item <?php echo esc_attr($rotation_class); ?>">
                                                            <?php if ($seat_available > 0 || $is_reserved_dd) { ?>
                                                                <div class="mp_seat seat_booked <?php echo $is_reserved_dd ? 'seat_reserved' : ''; ?>" title="<?php echo $is_reserved_dd ? esc_attr__('Reserved', 'bus-ticket-booking-with-seat-reservation') : esc_html( WBTM_Translations::text_already_sold() . ' : ' . esc_attr($info) ); ?>">
                                                                     <div class="seat_visual"></div>
                                                                     <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                                     <?php if ($is_reserved_dd && current_user_can('manage_options')): ?>
                                                                         <div class="wbtm_reopen_action" data-seat="<?php echo esc_attr($info); ?>" data-post_id="<?php echo esc_attr($post_id); ?>" data-date="<?php echo esc_attr($date_key); ?>" title="<?php esc_attr_e('Reopen for online sales', 'bus-ticket-booking-with-seat-reservation'); ?>">
                                                                             <i class="fas fa-unlock"></i>
                                                                         </div>
                                                                     <?php endif; ?>
                                                                 </div>
                                                            <?php } elseif (WBTM_Functions::check_seat_in_cart($post_id, $start_route, $end_route, $date, $info)) { ?>
                                                                <div class="mp_seat seat_in_cart" title="<?php echo esc_html( WBTM_Translations::text_already_in_cart() . ' :  ' . esc_attr($info) ); ?>">
                                                                    <div class="seat_visual"></div>
                                                                    <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                                </div>
                                                            <?php } else { ?>
                                                                <div class="mp_seat seat_available" title="<?php echo esc_attr(WBTM_Translations::text_available_seat()) . '  : ' . esc_attr($info); ?>"
                                                                    data-seat_name="<?php echo esc_attr($info); ?>"
                                                                    data-seat_label="<?php echo esc_attr($ticket_infos[0]['name']); ?>"
                                                                    data-seat_type="<?php echo esc_attr($ticket_infos[0]['type']); ?>"
                                                                    data-seat_price="<?php echo esc_attr($adult_price_dd); ?>"
                                                                >
                                                                    <div class="seat_visual"></div>
                                                                    <div class="seat_number"><?php echo esc_html($info); ?></div>
                                                                </div>
                                                                <?php if (sizeof($ticket_infos) > 1) { ?>
                                                                    <div class="wbtm_seat_item_list">
                                                                        <ul class="mp_list">
                                                                            <?php
                                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                            foreach ($ticket_infos as $key => $ticket_info) { ?>
                                                                                <?php
                                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                $ticket_price = $key > 0 ? WBTM_Global_Function::get_wc_raw_price($post_id, $ticket_info['price']) : $adult_price;
                                                                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                                                                                $ticket_price = $ticket_price + ($ticket_price * $seat_dd_increase / 100);
                                                                                ?>
                                                                                <li class="justifyBetween"
                                                                                    data-seat_label="<?php echo esc_attr($ticket_info['name']); ?>"
                                                                                    data-seat_type="<?php echo esc_attr($ticket_info['type']); ?>"
                                                                                    data-seat_price="<?php echo esc_attr($ticket_price); ?>"
                                                                                >
                                                                                    <span><?php echo esc_html($ticket_info['name']); ?></span>
                                                                                    -
                                                                                    <span><?php echo wp_kses_post( wc_price($ticket_price) ); ?></span>
                                                                                </li>
                                                                            <?php } ?>
                                                                        </ul>
                                                                    </div>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        </div>
                                                    </th>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <td></td>
                                            <?php } ?>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php
            //echo '<pre>'; print_r($seat_infos); echo '</pre>';
        }
    else {
            WBTM_Layout::msg(WBTM_Translations::text_no_seat_plan());
        }
