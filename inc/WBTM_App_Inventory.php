<?php
/** Stateless native-app views over the website's authoritative inventory/pricing. */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'WBTM_App_Inventory' ) ) {
	class WBTM_App_Inventory {
		public static function error( $message, $status = 400, $code = 'wbtm_app_invalid' ) {
			return new WP_Error( $code, $message, array( 'status' => $status ) );
		}

		public static function text( $value ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		/** Readable native Text content; encoded markup remains literal text, never HTML. */
		public static function description( $value ) {
			if ( ! is_scalar( $value ) ) { return ''; }
			$value = wp_check_invalid_utf8( (string) $value );
			$value = preg_replace( '/<(script|style)\b[^>]*>.*?(?:<\/\1\s*>|$)/is', '', $value );
			$value = preg_replace( '/<li\b[^>]*>/i', "\n• ", $value );
			$value = preg_replace( '/<br\b[^>]*>/i', "\n", $value );
			$value = preg_replace( '/<\/?(?:p|div|section|article|header|footer|h[1-6]|blockquote|ul|ol|pre|table|tr|dl|dt|dd|hr)\b[^>]*>/i', "\n\n", $value );
			$value = preg_replace( '/<\/(?:td|th)\s*>/i', ' ', $value );
			// Strip real markup before decoding, so encoded markup remains literal text.
			$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
			$value = preg_replace( '/[^\S\n]+/u', ' ', $value );
			$value = preg_replace( '/ *\n */', "\n", $value );
			$value = preg_replace( '/^• *$/m', '', $value );
			return trim( preg_replace( '/\n{3,}/', "\n\n", $value ) );
		}

		/** Public amenities use the same saved feature terms as website search. */
		public static function features( $id ) {
			$features = array();
			foreach ( (array) get_post_meta( $id, 'wbbm_bus_features_term_id', true ) as $term_id ) {
				$term = get_term( absint( $term_id ), 'wbtm_bus_feature' );
				if ( $term && ! is_wp_error( $term ) ) {
					$name = self::text( $term->name );
					if ( '' !== $name ) { $features[] = $name; }
				}
			}
			return array_values( array_unique( $features ) );
		}

		/** Elapsed minutes respect the operator timezone and overnight/DST dates. */
		public static function duration_minutes( array $trip ) {
			$times = array();
			foreach ( array( 'bp_time', 'dp_time' ) as $key ) {
				$value = self::text( $trip[ $key ] ?? '' );
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2})?$/D', $value ) ) { return null; }
				$value = str_replace( 'T', ' ', $value );
				if ( 16 === strlen( $value ) ) { $value .= ':00'; }
				$time = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, wp_timezone() );
				if ( ! $time || $time->format( 'Y-m-d H:i:s' ) !== $value ) { return null; }
				$times[] = $time->getTimestamp();
			}
			return $times[1] >= $times[0] ? (int) floor( ( $times[1] - $times[0] ) / 60 ) : null;
		}

		public static function trip( array $input ) {
			$raw_id = $input['bus_id'] ?? $input['id'] ?? 0;
			$id   = is_scalar( $raw_id ) ? absint( $raw_id ) : 0;
			$bp   = self::text( $input['bp'] ?? '' );
			$dp   = self::text( $input['dp'] ?? '' );
			$date = self::text( $input['date'] ?? '' );
			$day  = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
			if ( ! $day || $day->format( 'Y-m-d' ) !== $date || $bp === $dp || ! $bp || ! $dp || get_post_type( $id ) !== 'wbtm_bus' || get_post_status( $id ) !== 'publish' ) {
				return self::error( 'Select a published bus, valid stops and a YYYY-MM-DD date.' );
			}
			if ( class_exists( 'WBTM_App_Lifecycle' ) ) { WBTM_App_Lifecycle::expire_bus( $id ); }
			$leg = WBTM_Functions::resolve_price_leg_for_od_pair( $id, $bp, $dp, 'return' === ( $input['price_leg'] ?? '' ) ? 'return' : 'outbound' );
			$all = WBTM_Functions::get_bus_all_info( $id, $date, $bp, $dp, $leg );
			if ( ! $all || 'no' === ( $all['regi_status'] ?? '' ) || ! WBTM_Functions::route_price_configured( $id, $bp, $dp, $leg ) ) {
				return self::error( 'This departure is unavailable or ticket sales have closed.', 409, 'wbtm_app_trip_unavailable' );
			}
			return array_merge( $all, array( 'bus_id' => $id, 'date' => $date, 'price_leg' => $leg ) );
		}

		public static function detail( array $input ) {
			$trip = self::trip( $input );
			if ( is_wp_error( $trip ) ) {
				return $trip;
			}
			$id      = $trip['bus_id'];
			$tickets = array();
			foreach ( WBTM_Functions::get_ticket_info( $id, $trip['bp'], $trip['dp'], $trip['price_leg'] ) as $type ) {
				$price = WBTM_Functions::get_seat_price( $id, $trip['bp'], $trip['dp'], $type['type'], false, $trip['price_leg'], '', null, $trip['bp_time'] );
				if ( false !== $price ) {
					$tickets[] = array( 'id' => (string) $type['type'], 'name' => wp_strip_all_tags( $type['name'] ), 'price' => (float) $price );
				}
			}
			$seat_plan = 'wbtm_seat_plan' === get_post_meta( $id, 'wbtm_seat_type_conf', true );
			return array(
				'id' => $id, 'name' => get_the_title( $id ), 'description' => self::description( get_post_field( 'post_content', $id ) ),
				'image_url' => get_the_post_thumbnail_url( $id, 'large' ) ?: '',
				'type' => self::text( get_post_meta( $id, 'wbtm_bus_category', true ) ),
				'features' => self::features( $id ), 'duration_minutes' => self::duration_minutes( $trip ),
				'trip' => $trip, 'layouts' => $seat_plan ? self::layouts( $trip ) : array(),
				'ticket_types' => $tickets, 'extras' => self::extras( $trip ),
				'return_discount' => array( 'enabled' => class_exists( 'WBTM_return_discount' ) && (float) get_post_meta( $id, 'bus_return_discount', true ) > 0, 'value' => max( 0, (float) get_post_meta( $id, 'bus_return_discount', true ) ), 'type' => 'fixed' === get_post_meta( $id, 'bus_return_discount_type', true ) ? 'fixed' : 'percent' ),
				'passenger_fields' => self::passenger_fields( $id ),
				'pickup_points' => self::points( $trip, false ), 'dropoff_points' => self::points( $trip, true ),
				'pickup_required' => 'yes' === get_post_meta( $id, 'wbtm_pickup_point_required', true ),
				'dropoff_required' => 'yes' === get_post_meta( $id, 'wbtm_dropping_point_required', true ),
				'booking_modes' => array( 'seats' => $seat_plan, 'quantity' => ! $seat_plan, 'full_bus' => ! empty( WBTM_Functions::get_full_bus_pricing( $id, $trip['bp'], $trip['dp'], $trip['price_leg'] ) ) ),
			);
		}

		public static function layouts( array $trip ) {
			$id      = $trip['bus_id'];
			$booked  = array_map( 'strval', WBTM_Query::query_seat_booked( $id, $trip['bp'], $trip['dp'], $trip['start_time'] ) );
			$whole_bus = WBTM_Cart_Helper::route_has_full_bus_booking( $id, $trip['bp'], $trip['dp'], $trip['start_time'], true );
			$cabins  = get_post_meta( $id, 'wbtm_cabin_config', true );
			$sources = array();
			if ( 'yes' === get_post_meta( $id, 'wbtm_cabin_mode_enabled', true ) && is_array( $cabins ) ) {
				foreach ( $cabins as $index => $cabin ) {
					if ( 'yes' !== ( $cabin['enabled'] ?? 'yes' ) ) { continue; }
					foreach ( array( false, true ) as $upper ) {
						if ( $upper && 'yes' !== ( $cabin['upper_enabled'] ?? 'no' ) ) { continue; }
						$sources[] = array( 'key' => 'wbtm_cabin_seats_info' . ( $upper ? '_dd' : '' ) . '_' . $index, 'cabin_index' => $index, 'name' => self::text( $cabin['name'] ?? 'Cabin' ), 'upper' => $upper, 'multiplier' => (float) ( $cabin[ $upper ? 'upper_price_multiplier' : 'price_multiplier' ] ?? 1 ) );
					}
				}
			} else {
				$sources[] = array( 'key' => 'wbtm_bus_seats_info', 'cabin_index' => null, 'name' => 'Lower deck', 'upper' => false, 'multiplier' => 1 );
				if ( 'yes' === get_post_meta( $id, 'show_upper_desk', true ) ) {
					$sources[] = array( 'key' => 'wbtm_bus_seats_info_dd', 'cabin_index' => null, 'name' => 'Upper deck', 'upper' => true, 'multiplier' => 1 );
				}
			}
			$layouts = array();
			foreach ( $sources as $source ) {
				$grid = get_post_meta( $id, $source['key'], true );
				if ( ! is_array( $grid ) || ! $grid ) { continue; }
				$rows = array();
				foreach ( $grid as $row ) {
					$cells = array();
					foreach ( (array) $row as $key => $value ) {
						if ( false !== strpos( (string) $key, '_rotation' ) ) { continue; }
						$name = WBTM_Seat_Configuration::normalize_saved_seat_value( $value );
						if ( '' === $name ) { $cells[] = null; continue; }
						$nonseat    = WBTM_Seat_Configuration::is_non_seat_item( $name );
						$canonical  = null !== $source['cabin_index'] ? WBTM_Functions::cabin_seat_identifier( $source['cabin_index'], $name, $source['upper'] ) : $name;
						$identifier = null !== $source['cabin_index'] ? $canonical : ( $source['upper'] ? 'u|' : 'l|' ) . $name;
						$held       = WBTM_Seat_Hold::get_hold_owner( $id, WBTM_Seat_Hold::normalize_date( $trip['start_time'] ), $canonical );
						$rotation   = isset( $row[ $key . '_rotation' ] ) && is_scalar( $row[ $key . '_rotation' ] ) ? (int) $row[ $key . '_rotation' ] : 0;
						$rotation   = in_array( $rotation, array( 0, 90, 180, 270 ), true ) ? $rotation : 0;
						$cells[]    = array(
							'id' => $identifier, 'name' => $name, 'type' => $nonseat ? 'non_seat' : 'seat',
							'status' => $whole_bus ? 'booked' : ( $held ? 'held' : ( in_array( $canonical, $booked, true ) ? 'booked' : 'available' ) ),
							'layout' => WBTM_Seat_Configuration::get_seat_layout_type( $id, $canonical ),
							'canonical' => $canonical,
							'rotation' => $rotation,
						);
					}
					$rows[] = $cells;
				}
				$layouts[] = array( 'id' => $source['key'], 'name' => $source['name'], 'deck' => $source['upper'] ? 'upper' : 'lower', 'cabin_index' => $source['cabin_index'], 'multiplier' => $source['multiplier'], 'rows' => $rows );
			}
			return $layouts;
		}

		public static function extras( array $trip ) {
			$result = array();
			if ( 'yes' !== get_post_meta( $trip['bus_id'], 'show_extra_service', true ) ) { return $result; }
			foreach ( (array) get_post_meta( $trip['bus_id'], 'wbtm_extra_services', true ) as $service ) {
				if ( ! is_array( $service ) || empty( $service['option_name'] ) ) { continue; }
				$name = self::text( $service['option_name'] );
				$max  = max( 0, (int) ( $service['option_qty'] ?? 0 ) - WBTM_Query::query_ex_service_sold( $trip['bus_id'], $trip['start_time'], $name ) );
				$result[] = array( 'name' => $name, 'price' => (float) WBTM_Functions::get_ex_service_price( $trip['bus_id'], $name ), 'charge_type' => WBTM_Functions::get_ex_service_charge_type( $trip['bus_id'], $name ), 'max_quantity' => 'unlimited' === ( $service['option_qty_type'] ?? '' ) ? 20 : min( 20, $max ) );
			}
			return $result;
		}

		public static function passenger_fields( $id ) {
			$fields = array();
			foreach ( array( 'wbtm_attendee_info', 'wbtm_custom_attendee_info' ) as $key ) {
				foreach ( (array) get_post_meta( $id, $key, true ) as $form ) {
					if ( ! is_array( $form ) || ! in_array( $form['active'] ?? 0, array( 1, '1', true, 'yes', 'on' ), true ) || empty( $form['field_id'] ) ) { continue; }
					$options = array();
					$arguments = $form['args'] ?? array();
					if ( is_string( $arguments ) ) { $arguments = explode( ',', $arguments ); }
					foreach ( (array) $arguments as $option ) {
						if ( is_array( $option ) && isset( $option['value'] ) ) { $options[] = array( 'value' => self::text( $option['value'] ), 'label' => self::text( $option['label'] ?? $option['value'] ) ); }
						elseif ( is_scalar( $option ) && '' !== self::text( $option ) ) { $options[] = array( 'value' => self::text( $option ), 'label' => self::text( $option ) ); }
					}
					$type = self::text( $form['filed_type'] ?? 'text' );
					$fields[] = array( 'id' => sanitize_key( $form['field_id'] ), 'label' => self::text( $form['field_label'] ?? $form['d_label'] ?? $form['field_id'] ), 'type' => $type, 'required' => ! empty( $form['required'] ), 'options' => $options );
				}
			}
			return $fields;
		}

		public static function points( array $trip, $dropoff ) {
			$result = array();
			$key    = $dropoff ? 'wbtm_drop_off_point' : 'wbtm_pickup_point';
			foreach ( (array) get_post_meta( $trip['bus_id'], $key, true ) as $row ) {
				if ( ! is_array( $row ) || ( $row[ $dropoff ? 'dp_point' : 'bp_point' ] ?? '' ) !== $trip[ $dropoff ? 'dp' : 'bp' ] ) { continue; }
				foreach ( (array) ( $row[ $dropoff ? 'drop_off_info' : 'pickup_info' ] ?? array() ) as $point ) {
					if ( ! is_array( $point ) ) { continue; }
					$name = self::text( $point[ $dropoff ? 'drop_off_point' : 'pickup_point' ] ?? '' );
					if ( $name ) {
						$time = ! empty( $point['time'] ) ? WBTM_Global_Function::date_format( $trip['date'] . ' ' . $point['time'], 'time' ) : '';
						$result[] = trim( $name . ' ' . $time );
					}
				}
			}
			return array_values( array_unique( $result ) );
		}

		public static function passengers( array $quote, $input ) {
			$rows = is_array( $input ) ? array_values( $input ) : array();
			if ( count( $rows ) > 20 || ( $quote['passenger_fields'] && count( $rows ) !== $quote['passenger_count'] ) ) { return self::error( 'Complete the details for each passenger.' ); }
			$result = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) { return self::error( 'Invalid passenger details.' ); }
				$values = array();
				foreach ( $quote['passenger_fields'] as $field ) {
					$raw = $row[ $field['id'] ] ?? '';
					if ( 'checkbox' === $field['type'] && is_array( $raw ) ) {
						if ( count( $raw ) > count( $field['options'] ) || array_filter( $raw, static function( $item ) { return ! is_string( $item ); } ) ) { return self::error( 'Check passenger field: ' . $field['label'] ); }
						$raw = implode( ',', $raw );
					}
					$value = self::text( $raw );
					if ( strlen( $value ) > 2000 ) { return self::error( 'Check passenger field: ' . $field['label'] ); }
					$allowed = array_column( $field['options'], 'value' ); $invalid_choice = false;
					if ( 'checkbox' === $field['type'] && '' !== $value ) {
						$chosen = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) ) );
						$invalid_choice = (bool) array_diff( $chosen, $allowed );
						$value = implode( ',', array_values( array_intersect( $allowed, $chosen ) ) );
					} elseif ( '' !== $value && $allowed ) { $invalid_choice = ! in_array( $value, $allowed, true ); }
					if ( ( $field['required'] && '' === $value ) || ( '' !== $value && 'email' === $field['type'] && ! is_email( $value ) ) || $invalid_choice ) { return self::error( 'Check passenger field: ' . $field['label'] ); }
					$values[ $field['id'] ] = array( 'name' => $field['label'], 'value' => $value );
				}
				$result[] = $values;
			}
			return $result;
		}

		/** Price every selection using the same fare helper as the web cart. */
		public static function quote( array $input, $selection_summary = false ) {
			$detail = self::detail( $input );
			if ( is_wp_error( $detail ) ) { return $detail; }
			$trip = $detail['trip'];
			$id = $trip['bus_id'];
			$selected = $input['selections'] ?? array();
			$full = 'full_bus' === ( $input['booking_mode'] ?? '' );
			if ( ! is_array( $selected ) || count( $selected ) > 20 || ( ! $full && ! $selected ) ) { return self::error( 'Select between one and twenty tickets.' ); }
			$tickets = array(); $count = 0; $base = 0; $seen = array(); $full_pricing = array();
			$types = array_column( $detail['ticket_types'], 'id' );
			$seats = array();
			foreach ( $detail['layouts'] as $layout ) {
				foreach ( $layout['rows'] as $row ) {
					foreach ( $row as $cell ) { if ( $cell && 'seat' === $cell['type'] ) { $seats[ $cell['id'] ] = array_merge( $cell, array( 'cabin_index' => $layout['cabin_index'], 'cabin_name' => $layout['name'], 'is_upper' => 'upper' === $layout['deck'], 'multiplier' => $layout['multiplier'] ) ); } }
				}
			}
			if ( $full ) {
				$full_pricing = WBTM_Functions::get_full_bus_pricing( $id, $trip['bp'], $trip['dp'], $trip['price_leg'] );
				if ( ! $full_pricing || $trip['available_seat'] < $trip['total_seat'] ) { return self::error( 'The whole bus is unavailable.', 409 ); }
				$tickets[] = array( 'ticket_name' => 'Full Bus', 'ticket_type' => 'full_bus', 'seat_name' => 'Full Bus', 'ticket_price' => $full_pricing['final_price'], 'ticket_qty' => 1, 'date' => $trip['bp_time'] );
				$count = 1; $base = $full_pricing['final_price'];
			} else {
				foreach ( $selected as $selection ) {
					if ( ! is_array( $selection ) ) { return self::error( 'Invalid ticket selection.' ); }
					$type = self::text( $selection['ticket_type'] ?? '' );
					if ( ! in_array( $type, $types, true ) ) { return self::error( 'This passenger type is unavailable.' ); }
					$seat = null; $qty = 1;
					if ( $detail['booking_modes']['seats'] ) {
						$seat_id = self::text( $selection['seat_id'] ?? '' );
						if ( ! isset( $seats[ $seat_id ] ) || isset( $seen[ $seat_id ] ) ) { return self::error( 'Select valid, distinct seats.' ); }
						$seat = $seats[ $seat_id ]; $seen[ $seat_id ] = true;
					} else {
						$qty = filter_var( $selection['quantity'] ?? 0, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'max_range' => 20 ) ) );
						if ( ! $qty ) { return self::error( 'Ticket quantity must be between one and twenty.' ); }
					}
					$price = WBTM_Functions::get_seat_price( $id, $trip['bp'], $trip['dp'], $type, $seat && null === $seat['cabin_index'] && $seat['is_upper'], $trip['price_leg'], $seat ? $seat['name'] : '', $seat ? $seat['cabin_index'] : null, $trip['bp_time'] );
					if ( false === $price || $price < 0 ) { return self::error( 'The selected fare is unavailable.' ); }
					if ( $seat && null !== $seat['cabin_index'] ) { $price *= $seat['multiplier']; }
					$ticket = array( 'ticket_name' => WBTM_Functions::get_ticket_name( $type, $id ), 'ticket_type' => $type, 'ticket_qty' => $qty, 'ticket_price' => (float) $price, 'seat_name' => $seat ? $seat['name'] : WBTM_Functions::get_ticket_name( $type, $id ), 'date' => $trip['bp_time'] );
					if ( $seat ) {
						$ticket['seat_identifier'] = $seat['canonical']; $ticket['dd'] = $seat['is_upper'] ? 1 : 0;
						if ( null !== $seat['cabin_index'] ) { $ticket = array_merge( $ticket, array( 'cabin_index' => $seat['cabin_index'], 'cabin_name' => $seat['cabin_name'], 'is_upper' => $seat['is_upper'], 'price_multiplier' => $seat['multiplier'] ) ); }
					}
					$tickets[] = $ticket; $count += $qty; $base += $price * $qty;
				}
			}
			if ( $count > 20 ) { return self::error( 'A booking can contain at most twenty passengers.' ); }
			$extras = array(); $extra_total = 0; $allowed_extras = array_column( $detail['extras'], null, 'name' );
			if ( ! is_array( $input['extras'] ?? array() ) || count( $input['extras'] ?? array() ) > 20 ) { return self::error( 'Invalid extra services.' ); }
			foreach ( $input['extras'] ?? array() as $extra ) {
				$name = self::text( is_array( $extra ) ? ( $extra['name'] ?? '' ) : '' ); $qty = is_array( $extra ) ? filter_var( $extra['quantity'] ?? 0, FILTER_VALIDATE_INT ) : false;
				if ( ! isset( $allowed_extras[ $name ] ) || ! $qty || $qty > $allowed_extras[ $name ]['max_quantity'] || $qty < 1 || isset( $extras[ $name ] ) ) { return self::error( 'This extra service or quantity is unavailable.' ); }
				$extras[ $name ] = array( 'name' => $name, 'qty' => $qty, 'price' => $allowed_extras[ $name ]['price'], 'charge_type' => $allowed_extras[ $name ]['charge_type'], 'date' => $trip['bp_time'] );
				$extra_total += WBTM_Functions::ex_service_line_total( $extras[ $name ], $count );
			}
			foreach ( array( 'pickup', 'dropoff' ) as $point ) {
				$value = self::text( $input[ $point ] ?? '' );
				if ( ( ! $selection_summary && $detail[ $point . '_required' ] && ! $value ) || ( $value && ! in_array( $value, $detail[ $point . '_points' ], true ) ) ) { return self::error( 'Select a valid ' . $point . ' point.' ); }
			}
			return array( 'trip' => $trip, 'tickets' => $tickets, 'extras' => array_values( $extras ), 'passenger_count' => $count, 'passenger_fields' => $full ? array() : $detail['passenger_fields'], 'base_price' => $base, 'total' => $base + $extra_total, 'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD', 'booking_mode' => $full ? 'full_bus' : 'seat', 'full_bus' => $full_pricing, 'pickup' => self::text( $input['pickup'] ?? '' ), 'dropoff' => self::text( $input['dropoff'] ?? '' ), 'input' => $input );
		}
	}
}
