<?php
/**
 * Runs ONE version-collision scenario in its own PHP process, and prints
 * the outcome as JSON.
 *
 * A separate process per scenario is not laziness: the loader's whole
 * job is to decide, once per process, which copy of the SDK wins, and it
 * records that in a constant. Constants cannot be undefined, so two
 * scenarios in one process would contaminate each other.
 *
 * Usage:
 *   php loader-scenario.php 0.1.0,0.9.0,0.2.0        # normal: hook fires later
 *   php loader-scenario.php 0.1.0,0.9.0 late         # SDK included after plugins_loaded
 *
 * Each "version" is a real copy of the real loader with its version
 * literal rewritten, pointing at a bootstrap that defines an UNGUARDED
 * class. Unguarded on purpose: if the loader ever let two copies through,
 * PHP raises "Cannot redeclare class" — the exact fatal this whole
 * mechanism exists to prevent — and this script reports it instead of
 * quietly passing.
 */

require __DIR__ . '/wp-hook-polyfill.php';

$versions = isset( $argv[1] ) ? explode( ',', $argv[1] ) : array();
$mode     = isset( $argv[2] ) ? $argv[2] : 'normal';

$fixture_root = sys_get_temp_dir() . '/appneck-sdk-loader-' . getmypid();
@mkdir( $fixture_root, 0777, true );

$real_loader = file_get_contents( dirname( __DIR__ ) . '/appneck-sdk.php' );

$copies = array();

foreach ( $versions as $version ) {
	$dir = $fixture_root . '/' . str_replace( '.', '_', $version );
	@mkdir( $dir, 0777, true );

	// The real loader, with only the version literal changed — so this
	// exercises the shipped registry logic, not a reimplementation.
	$loader = preg_replace(
		"/\\\$appneck_sdk_this_version\s*=\s*'[^']+';/",
		"\$appneck_sdk_this_version = '" . $version . "';",
		$real_loader,
		1
	);

	file_put_contents( $dir . '/appneck-sdk.php', $loader );
	file_put_contents(
		$dir . '/bootstrap.php',
		"<?php\n"
		. "\$GLOBALS['appneck_probe_loaded'][] = '" . $version . "';\n"
		. "class Appneck_Sdk_Loader_Probe { const VERSION = '" . $version . "'; }\n"
	);

	$copies[] = $dir . '/appneck-sdk.php';
}

$GLOBALS['appneck_probe_loaded'] = array();

$result = array(
	'scenario'       => $versions,
	'mode'           => $mode,
	'fatal'          => null,
	'loaded'         => array(),
	'loaded_version' => null,
	'probe_version'  => null,
);

try {
	if ( 'late' === $mode ) {
		// plugins_loaded has already fired before the SDK is included —
		// a theme, a mu-plugin loading late, or a plugin activated
		// mid-request. Waiting for a hook that has been and gone would
		// mean never loading at all.
		appneck_test_do_action( 'plugins_loaded' );

		foreach ( $copies as $copy ) {
			require_once $copy;
		}
	} else {
		// The normal WordPress sequence: every plugin file is included
		// first (each SDK copy registering as it goes), then the hook
		// fires once.
		foreach ( $copies as $copy ) {
			require_once $copy;
		}

		appneck_test_do_action( 'plugins_loaded' );
	}
} catch ( \Throwable $e ) {
	$result['fatal'] = get_class( $e ) . ': ' . $e->getMessage();
}

$result['loaded']         = $GLOBALS['appneck_probe_loaded'];
$result['loaded_version'] = defined( 'APPNECK_SDK_LOADED_VERSION' ) ? APPNECK_SDK_LOADED_VERSION : null;
$result['probe_version']  = class_exists( 'Appneck_Sdk_Loader_Probe', false )
	? Appneck_Sdk_Loader_Probe::VERSION
	: null;

// Clean up the fixtures — this script may run many times.
foreach ( $copies as $copy ) {
	@unlink( $copy );
	@unlink( dirname( $copy ) . '/bootstrap.php' );
	@rmdir( dirname( $copy ) );
}
@rmdir( $fixture_root );

echo json_encode( $result );
