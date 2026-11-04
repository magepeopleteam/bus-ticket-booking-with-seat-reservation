<?php
/** WooCommerce registered additional checkout fields, without profile writes. */
if ( ! defined( 'ABSPATH' ) ) { die; }

class WBTM_App_Native_Fields {
	private static function service() {
		$class = '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';
		return class_exists( $class ) && function_exists( 'wc_get_container' ) ? wc_get_container()->get( $class ) : null;
	}
	private static function contexts() { return array( array( 'address', 'billing', 'billing_' ), array( 'contact', 'other', 'wc_contact/' ), array( 'order', 'other', 'wc_order/' ) ); }
	public static function schema() {
		$result = array( 'billing_fields' => array(), 'order_fields' => array() ); $service = self::service(); if ( ! $service ) { return $result; }
		foreach ( self::contexts() as $context ) {
			foreach ( $service->get_fields_for_location( $context[0] ) as $key => $field ) {
				if ( $service->is_conditional_field( $field ) || ! empty( $field['validation'] ) || ! in_array( $field['type'], array( 'text', 'select', 'checkbox' ), true ) ) { return WBTM_App_Native_Checkout::error( 'This checkout field requires an additional native adapter: ' . $field['label'], 409, 'wbtm_app_field_adapter_required' ); }
				$options = array(); foreach ( $field['options'] ?? array() as $option ) { if ( is_array( $option ) && isset( $option['value'], $option['label'] ) ) { $options[] = array( 'value' => (string) $option['value'], 'label' => WBTM_App_Inventory::text( $option['label'] ) ); } }
				$value = $service->get_field_from_object( $key, WC()->customer, $context[1] );
				$result[ 'billing' === $context[1] ? 'billing_fields' : 'order_fields' ][] = array( 'id' => $context[2] . $key, 'label' => WBTM_App_Inventory::text( $field['label'] ), 'type' => $field['type'], 'required' => $service->is_required_field( $field ), 'options' => $options, 'value' => is_scalar( $value ) ? (string) $value : '', 'placeholder' => '' );
			}
		}
		return $result;
	}
	public static function validate( array $intent ) {
		$service = self::service(); if ( ! $service ) { return array(); } $schema = self::schema(); if ( is_wp_error( $schema ) ) { return $schema; } $result = array();
		foreach ( self::contexts() as $context ) {
			$values = array();
			foreach ( $service->get_fields_for_location( $context[0] ) as $key => $field ) {
				$raw = 'billing' === $context[1] ? ( $intent['billing'][ $key ] ?? $intent['fields'][ $context[2] . $key ] ?? '' ) : ( $intent['fields'][ $context[2] . $key ] ?? '' );
				if ( ! is_scalar( $raw ) || strlen( (string) $raw ) > 2000 ) { return WBTM_App_Native_Checkout::error( 'Invalid checkout field: ' . $field['label'] ); }
				$value = 'checkbox' === $field['type'] ? in_array( $raw, array( true, 1, '1', 'yes', 'on' ), true ) : sanitize_text_field( (string) $raw );
				$value = $service->sanitize_field( $key, $value );
				if ( $service->is_required_field( $field ) && ( '' === $value || false === $value ) ) { return WBTM_App_Native_Checkout::error( $field['label'] . ' is required.' ); }
				$errors = $service->validate_field( $field, $value ); if ( is_wp_error( $errors ) && $errors->has_errors() ) { return WBTM_App_Native_Checkout::error( implode( ' ', $errors->get_error_messages() ) ); }
				$values[ $key ] = $value; $result[] = array( 'key' => $key, 'group' => $context[1], 'value' => $value );
			}
			$errors = $service->validate_fields_for_location( $values, $context[0], $context[1] ); if ( $errors->has_errors() ) { return WBTM_App_Native_Checkout::error( implode( ' ', $errors->get_error_messages() ) ); }
		}
		return $result;
	}
	public static function persist( $order, array $fields ) {
		$service = self::service(); if ( ! $service ) { return; }
		foreach ( $fields as $field ) { $service->persist_field_for_order( $field['key'], $field['value'], $order, $field['group'], false ); }
	}
}
