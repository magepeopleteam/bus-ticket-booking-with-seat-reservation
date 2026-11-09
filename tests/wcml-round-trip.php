<?php
/**
 * Run with: php tests/wcml-round-trip.php
 * Exercises WooCommerce cart identities and the WCML duplicate-product hook.
 * No WordPress bootstrap, database writes, sessions or orders are used.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'WC_ABSPATH', dirname( __DIR__, 2 ) . '/woocommerce/' );
$filters = array();

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}

function apply_filters( $hook, $value, ...$args ) {
	$callbacks = $GLOBALS['filters'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) {
		foreach ( $group as list( $callback, $accepted_args ) ) {
			$value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
		}
	}
	return $value;
}

function absint( $value ) { return abs( (int) $value ); }
function get_post_type( $id ) { return 100 === (int) $id ? 'wbtm_bus' : 'product'; }

class WBTM_Functions {
	public static function get_cpt() { return 'wbtm_bus'; }
}

require dirname( __DIR__ ) . '/inc/WBTM_Woocommerce.php';
require dirname( __DIR__, 2 ) . '/woocommerce/includes/class-wc-cart.php';

// Avoid initializing WooCommerce's session and action handlers in this unit test.
$cart   = ( new ReflectionClass( 'WC_Cart' ) )->newInstanceWithoutConstructor();
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};

// Model WCML's product-only check, including its supported opt-out filter.
$found = static function ( $product_id, $data ) use ( $cart ) {
	$key   = $cart->generate_cart_id( $product_id, 0, array(), $data );
	$found = (bool) $cart->find_product_in_cart( $key );
	foreach ( $cart->cart_contents as $item ) {
		if ( $item['product_id'] === $product_id && $item['quantity'] > 0
			&& apply_filters( 'wcml_add_to_cart_sold_individually', true, $data, $product_id, $item['quantity'] ) ) {
			return true;
		}
	}
	return $found;
};

$outbound = array(
	'wbtm_bus_id'    => 100,
	'wbtm_bp_place'  => 'Firenze',
	'wbtm_dp_place'  => 'Asmana Wellness World',
	'wbtm_bp_time'   => '2026-11-10 11:30',
	'wbtm_price_leg' => 'outbound',
);
$return   = array_replace( $outbound, array(
	'wbtm_bp_place'  => 'Asmana Wellness World',
	'wbtm_dp_place'  => 'Firenze',
	'wbtm_bp_time'   => '2026-11-10 17:00',
	'wbtm_price_leg' => 'return',
) );
$key = $cart->generate_cart_id( 200, 0, array(), $outbound );
$cart->cart_contents[ $key ] = array_merge( $outbound, array( 'product_id' => 200, 'quantity' => 1 ) );

$assert( ! $found( 200, $return ), 'A same-bus return must not be rejected as an outbound duplicate.' );
$assert( $found( 200, $outbound ), 'An exact duplicate outbound must remain blocked by WooCommerce.' );
$assert( ! $found( 200, array_replace( $outbound, array( 'wbtm_bp_time' => '2026-11-11 11:30' ) ) ), 'A different travel date must have its own cart identity.' );
$assert( apply_filters( 'wcml_add_to_cart_sold_individually', true, array() ), 'Normal WooCommerce products must keep WCML duplicate protection.' );
$assert( apply_filters( 'wcml_add_to_cart_sold_individually', true, array( 'wbtm_bus_id' => 300 ) ), 'Invalid bus metadata must not disable protection.' );
$assert( ! apply_filters( 'wcml_add_to_cart_sold_individually', false, array() ), 'An earlier opt-out must be preserved.' );
$return_key = $cart->generate_cart_id( 200, 0, array(), $return );
$cart->cart_contents[ $return_key ] = array_merge( $return, array( 'product_id' => 200, 'quantity' => 1 ) );
$assert( $found( 200, $return ), 'An exact duplicate return must remain blocked.' );

echo $checks . " WCML round-trip checks passed.\n";
