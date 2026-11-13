<?php
/**
 * Run with: php tests/wcml-untranslated-cart.php
 * Bus tickets must survive WCML's cart language switch when their hidden product has no
 * translation in the visitor's language. No WordPress bootstrap, database, session or order.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
$hooks = array();

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_filter( $hook, $callback, $priority, $accepted_args );
}

function remove_action( $hook, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['hooks'][ $hook ][ $priority ] ?? array() as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS['hooks'][ $hook ][ $priority ][ $index ] );
		}
	}
}

function has_filter( $hook ) {
	return ! empty( array_filter( $GLOBALS['hooks'][ $hook ] ?? array() ) );
}

function apply_filters( $hook, $value, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) {
		foreach ( $group as list( $callback, $accepted_args ) ) {
			$value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
		}
	}
	return $value;
}

function do_action( $hook, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) {
		foreach ( $group as list( $callback, $accepted_args ) ) {
			$callback( ...array_slice( $args, 0, $accepted_args ) );
		}
	}
}

function absint( $value ) { return abs( (int) $value ); }
function get_post_type( $id ) { return in_array( (int) $id, array( 100, 101 ), true ) ? 'wbtm_bus' : 'page'; }

class WBTM_Functions {
	public static function get_cpt() { return 'wbtm_bus'; }
}

require dirname( __DIR__ ) . '/inc/WBTM_Woocommerce.php';

// Only the bus plugin's WCML brackets matter here; drop its other cart hooks.
foreach ( $GLOBALS['hooks']['woocommerce_before_calculate_totals'] as $priority => $group ) {
	if ( ! in_array( $priority, array( 99, 101 ), true ) ) {
		unset( $GLOBALS['hooks']['woocommerce_before_calculate_totals'][ $priority ] );
	}
}

$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};

/*
 * WPML stub: hidden bus products 200/201 and product 300 exist only in English;
 * product 400 has an Italian translation (410), and so does bus product 202 (212).
 */
$language     = 'en';
$translations = array( 'it' => array( 400 => 410, 410 => 410, 202 => 212, 212 => 212 ) );
$wpml_active  = static function () use ( &$language, $translations ) {
	add_filter( 'wpml_current_language', static function () use ( &$language ) { return $language; } );
	add_filter(
		'wpml_object_id',
		static function ( $id, $type, $return_original, $lang ) use ( $translations ) {
			if ( 'en' === $lang ) {
				return $id;
			}
			return $translations[ $lang ][ $id ] ?? ( $return_original ? $id : null );
		},
		10,
		4
	);
};

/*
 * Model of WCML_Cart::woocommerce_calculate_totals() (WCML 5.6/5.7) with products not set to
 * "display as translated": keep a line whose product is already in the current language,
 * re-key a line whose product has a translation, drop everything else. Like WCML's duplicate
 * merge, it can run calculate_totals() again while it is unhooked.
 */
$nested = false;
$wcml   = static function ( $cart ) use ( &$wcml, &$nested ) {
	$lang = apply_filters( 'wpml_current_language', null );
	$new  = array();
	foreach ( $cart->cart_contents as $key => $item ) {
		$tr = apply_filters( 'wpml_object_id', $item['product_id'], 'product', false, $lang );
		if ( $tr === $item['product_id'] ) {
			$new[ $key ] = $item;
		} elseif ( null !== $tr ) {
			$item['product_id']        = $tr;
			$new[ 'tr-' . $key . '-' . $tr ] = $item;
		}
	}
	if ( $nested ) {
		remove_action( 'woocommerce_before_calculate_totals', $wcml, 100 );
		do_action( 'woocommerce_before_calculate_totals', $cart );
		add_action( 'woocommerce_before_calculate_totals', $wcml, 100 );
	}
	$cart->cart_contents = $new;
};

$line = static function ( $bus_id, $product_id, $extra = array() ) {
	return array_merge( array( 'wbtm_bus_id' => $bus_id, 'product_id' => $product_id, 'quantity' => 1, 'wbtm_bp_time' => '2026-11-20 10:00' ), $extra );
};
$cart = new stdClass();
$calc = static function ( array $contents ) use ( $cart ) {
	$cart->cart_contents = $contents;
	do_action( 'woocommerce_before_calculate_totals', $cart );
	return $cart->cart_contents;
};

// Without WPML nothing is held and the bracket stays balanced.
$result = $calc( array( 'a' => $line( 100, 200 ) ) );
$assert( array( 'a' ) === array_keys( $result ), 'Without WPML the cart must be untouched.' );

$wpml_active();
add_action( 'woocommerce_before_calculate_totals', $wcml, 100 );

// Default language: WCML keeps the line, nothing to restore.
$result = $calc( array( 'a' => $line( 100, 200 ) ) );
$assert( array( 'a' ) === array_keys( $result ), 'Default language must keep the ticket unchanged.' );

// Italian page: the untranslated hidden product must not lose the ticket.
$language = 'it';
$ticket   = $line( 100, 200, array( 'wbtm_seats' => array( array( 'seat_name' => 'A1', 'ticket_qty' => 1 ) ) ) );
$result   = $calc( array( 'a' => $ticket ) );
$assert( array( 'a' ) === array_keys( $result ), 'Italian page must keep the bus ticket under its original key.' );
$assert( $ticket === $result['a'], 'The restored ticket must keep every booking field.' );

// Mixed cart: tickets stay in place, translated lines are re-keyed, other untranslated products still drop.
$result = $calc(
	array(
		'a' => $line( 100, 200 ),
		'n' => array( 'product_id' => 400, 'quantity' => 1 ),
		'x' => array( 'product_id' => 300, 'quantity' => 1 ),
		'b' => $line( 101, 201, array( 'wbtm_price_leg' => 'return' ) ),
	)
);
$assert( array( 'a', 'b', 'tr-n-410' ) === array_keys( $result ), 'Tickets must keep their order; translated lines follow.' );
$assert( 'return' === $result['b']['wbtm_price_leg'], 'The return leg must be restored as the return leg.' );
$assert( ! isset( $result['x'] ), 'A normal untranslated product must still follow the WCML setting.' );

// A bus product that does have a translation is switched by WCML, never duplicated.
$result = $calc( array( 'c' => $line( 100, 202 ) ) );
$assert( array( 'tr-c-212' ) === array_keys( $result ), 'A translated bus product must not be restored twice.' );

// wbtm_bus_id that is not a bus must not be resurrected.
$result = $calc( array( 'f' => $line( 999, 200 ) ) );
$assert( array() === $result, 'Lines without a real bus must not be restored.' );

// WCML re-running calculate_totals() inside its own callback must not lose the outer snapshot.
$nested = true;
$result = $calc( array( 'a' => $line( 100, 200 ), 'b' => $line( 101, 201 ) ) );
$assert( array( 'a', 'b' ) === array_keys( $result ), 'A nested calculate_totals() must keep both tickets.' );
$nested = false;

// The snapshot stack must be empty after every run.
$stack = new ReflectionProperty( 'WBTM_Woocommerce', 'wcml_held_bus_items' );
$stack->setAccessible( true ); // Needed on PHP 8.0, the plugin's minimum.
$owner = null;
foreach ( $GLOBALS['hooks']['woocommerce_before_calculate_totals'][99] as $entry ) {
	$owner = $entry[0][0];
}
$assert( array() === $stack->getValue( $owner ), 'Every held snapshot must be released.' );

echo $checks . " WCML untranslated-cart checks passed.\n";
