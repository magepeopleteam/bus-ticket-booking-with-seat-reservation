<?php
/** New-booking metadata parity; self-cleaning and no orders, mail or payments. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
$post_id = 0; $checks = 0; $added = 0; $updated = 0;
$assert = static function( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$seed = static function( $id, $post ) { if ( 'wbtm_bus_booking' === $post->post_type && 'draft' === $post->post_status ) { add_post_meta( $id, '_native_meta_fixture_existing', 'old' ); } };
$observe_add = static function( $meta_id, $id, $key ) use ( &$added ) { if ( '_native_meta_fixture_unicode' === $key ) { ++$added; } };
$observe_update = static function( $meta_id, $id, $key ) use ( &$updated ) { if ( '_native_meta_fixture_existing' === $key ) { ++$updated; } };
add_action( 'wp_insert_post', $seed, 10, 2 ); add_action( 'added_post_meta', $observe_add, 10, 3 ); add_action( 'updated_post_meta', $observe_update, 10, 3 );
try {
	$meta = array( '_native_meta_fixture_existing' => 'new', '_native_meta_fixture_unicode' => 'ঢাকা', '_native_meta_fixture_array' => array( 'seat' => 'A1', 'enabled' => false ), '_native_meta_fixture_zero' => 0 );
	$post_id = WBTM_Cart_Helper::add_cpt_data( 'wbtm_bus_booking', 'Native metadata fixture', $meta, 'draft' );
	$assert( $post_id > 0, 'A real booking post is created.' );
	$assert( 'new' === get_post_meta( $post_id, '_native_meta_fixture_existing', true ) && 1 === count( get_post_meta( $post_id, '_native_meta_fixture_existing' ) ), 'Metadata supplied by save hooks is updated without duplicate values.' );
	$assert( 'ঢাকা' === get_post_meta( $post_id, '_native_meta_fixture_unicode', true ), 'Unicode passenger values are preserved.' );
	$assert( $meta['_native_meta_fixture_array'] === get_post_meta( $post_id, '_native_meta_fixture_array', true ), 'Structured metadata uses core serialization unchanged.' );
	$assert( '0' === get_post_meta( $post_id, '_native_meta_fixture_zero', true ), 'Zero-valued metadata is retained.' );
	$assert( 1 === $added && 1 === $updated, 'Normal WordPress add/update metadata hooks still fire.' );
	WP_CLI::success( $checks . ' booking metadata parity checks passed.' );
} finally {
	remove_action( 'wp_insert_post', $seed, 10 ); remove_action( 'added_post_meta', $observe_add, 10 ); remove_action( 'updated_post_meta', $observe_update, 10 ); if ( $post_id ) { wp_delete_post( $post_id, true ); }
}
