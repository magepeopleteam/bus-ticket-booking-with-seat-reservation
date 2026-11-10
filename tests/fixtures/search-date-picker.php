<?php
/** CLI fixture: render the real AJAX date handlers without WordPress or a database. */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ );
$fixture = json_decode( $argv[1], true );
$_POST   = $fixture;

function add_action( ...$args ) {}
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_js( $value ) { return addslashes( $value ); }
function __( $value, ...$args ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function wp_verify_nonce( $value, $action ) { return 'test' === $value; }
function get_option( $name ) { return array(); }
function current_time( $format ) { return '2026-11-10'; }
function date_i18n( $format, $time ) { return gmdate( $format, $time ); }
function apply_filters( $hook, $dates, ...$args ) {
	return empty( $GLOBALS['fixture']['scope_date'] ) ? $dates : array_values( array_intersect( $dates, array( $GLOBALS['fixture']['scope_date'] ) ) );
}
function do_action( $hook, ...$args ) {
	if ( 'wbtm_load_date_picker_js' === $hook ) {
		( new WBTM_Global_Function() )->date_picker_js( ...$args );
	}
}
class WBTM_Functions {
	public static function get_all_dates( ...$args ) { return $GLOBALS['fixture']['dates'] ?? array( '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21' ); }
}
class WBTM_Translations {
	public static function text_journey_date() { return 'Journey Date'; }
	public static function text_return_date() { return 'Return Date (Optional)'; }
	public static function text_select_route() { return 'Select route'; }
	public static function text_date_available_status() { return 'Available'; }
	public static function text_date_unavailable_status() { return 'Unavailable'; }
}
require dirname( __DIR__, 2 ) . '/mp_global/class/WBTM_Global_Function.php';
require dirname( __DIR__, 2 ) . '/inc/WBTM_Layout.php';
$layout = new WBTM_Layout();
if ( 'get_wbtm_return_date' === $fixture['action'] ) {
	$layout->get_wbtm_return_date();
} else {
	$layout->get_wbtm_journey_date();
}
