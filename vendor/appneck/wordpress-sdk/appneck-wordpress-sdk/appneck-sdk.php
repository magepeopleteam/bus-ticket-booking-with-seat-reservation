<?php
/**
 * Appneck WordPress SDK — version-safe loader.
 *
 * THIS FILE DEFINES NO CLASSES, AND MUST NEVER DEFINE ANY.
 *
 * Several independent plugins on one WordPress site may each bundle their
 * own copy of this SDK. If every copy simply `require`d its own classes,
 * the second one to load would raise "Cannot redeclare class
 * Appneck\Sdk\Client" — a fatal that takes down the WHOLE SITE, not just
 * the offending plugin. Guarding each class with `class_exists()` avoids
 * the fatal but picks the wrong winner: whichever plugin happened to load
 * first wins, even if it bundles a year-old copy, so a plugin shipping a
 * newer SDK silently runs against older code.
 *
 * The fix, which is the pattern Action Scheduler uses (and Freemius does
 * a variant of): every copy REGISTERS its version in a process-global
 * registry at include time without loading anything, and then exactly one
 * copy — the highest version registered — actually loads its classes,
 * once WordPress has given every plugin a chance to register.
 *
 * How a copy of this SDK behaves:
 *
 *   1. include time (during each plugin's own load): append
 *      version => bootstrap path to $GLOBALS['appneck_sdk_versions'].
 *      Nothing else. No classes, no side effects, no I/O.
 *   2. `plugins_loaded` priority 0: appneck_sdk_load_latest() sorts the
 *      registry with version_compare(), and requires the bootstrap of
 *      the highest version only.
 *
 * Two things in this file are deliberately frozen and must stay
 * backward-compatible forever, because the copy that defines them may be
 * ANY version present on the site — including one written before the
 * version you are editing now:
 *
 *   - the shape of $GLOBALS['appneck_sdk_versions'] (string version =>
 *     string absolute path to a bootstrap file), and
 *   - the behaviour of appneck_sdk_load_latest().
 *
 * Both are guarded so the first copy to define them wins. Change their
 * contract and you break every site where an older copy loads first.
 *
 * @package Appneck\Sdk
 */

// Not `defined('ABSPATH')`-guarded on purpose: this file must also be
// includable from a plain PHP process (the package's own test suite runs
// it with no WordPress present). Nothing here touches the filesystem,
// the network, or WordPress state, so there is nothing to protect.

$appneck_sdk_this_version = '0.1.0';
$appneck_sdk_this_bootstrap = __DIR__ . '/bootstrap.php';

if ( ! isset( $GLOBALS['appneck_sdk_versions'] ) || ! is_array( $GLOBALS['appneck_sdk_versions'] ) ) {
	$GLOBALS['appneck_sdk_versions'] = array();
}

// First registrant of a given version number wins. Two copies of the
// same version are the same code, so which file path is used doesn't
// matter — and preferring the first avoids a plugin update changing
// which directory the SDK is served from mid-request.
if ( ! isset( $GLOBALS['appneck_sdk_versions'][ $appneck_sdk_this_version ] ) ) {
	$GLOBALS['appneck_sdk_versions'][ $appneck_sdk_this_version ] = $appneck_sdk_this_bootstrap;
}

unset( $appneck_sdk_this_version, $appneck_sdk_this_bootstrap );

if ( ! function_exists( 'appneck_sdk_load_latest' ) ) {
	/**
	 * Load the highest registered version of the SDK, once.
	 *
	 * Idempotent and safe to call directly: a plugin that needs the SDK
	 * before `plugins_loaded` can call this itself. The cost of calling
	 * it early is that copies belonging to plugins which have not loaded
	 * yet cannot have registered, so an older bundled copy may win —
	 * which is exactly why the normal path waits for `plugins_loaded`.
	 *
	 * @return string|null The loaded version, or null if nothing loaded.
	 */
	function appneck_sdk_load_latest() {
		if ( defined( 'APPNECK_SDK_LOADED_VERSION' ) ) {
			return APPNECK_SDK_LOADED_VERSION;
		}

		if ( empty( $GLOBALS['appneck_sdk_versions'] ) || ! is_array( $GLOBALS['appneck_sdk_versions'] ) ) {
			return null;
		}

		$versions = $GLOBALS['appneck_sdk_versions'];

		// version_compare as the key comparator, NOT a string sort:
		// "0.10.0" is newer than "0.9.0" but sorts before it as a string,
		// and that class of bug is invisible until the tenth release.
		uksort( $versions, 'version_compare' );

		end( $versions );
		$winning_version = key( $versions );
		$bootstrap = $versions[ $winning_version ];

		if ( ! is_string( $bootstrap ) || ! is_file( $bootstrap ) ) {
			return null;
		}

		// Defined BEFORE the require so that anything the bootstrap
		// touches can already see which version won, and so a fatal
		// inside the bootstrap cannot leave a second copy trying again.
		define( 'APPNECK_SDK_LOADED_VERSION', $winning_version );
		define( 'APPNECK_SDK_LOADED_PATH', dirname( $bootstrap ) );

		require_once $bootstrap;

		return $winning_version;
	}
}

if ( function_exists( 'add_action' ) && function_exists( 'did_action' ) ) {
	if ( did_action( 'plugins_loaded' ) ) {
		// Loaded late — by a theme, a must-use plugin loading after the
		// fact, or a plugin activated mid-request. No further copies can
		// register, so waiting for a hook that already fired would mean
		// never loading at all.
		appneck_sdk_load_latest();
	} else {
		// Priority 0: after every plugin file has been included (so all
		// copies have registered), before any normal `plugins_loaded`
		// callback runs. Registering the same named function at the same
		// priority more than once is a no-op in WordPress, so N bundled
		// copies still produce exactly one call.
		add_action( 'plugins_loaded', 'appneck_sdk_load_latest', 0 );
	}
} else {
	// No WordPress in the process (the package's own tests, WP-CLI
	// bootstraps, static analysis). Nothing is going to fire a hook, so
	// load immediately.
	appneck_sdk_load_latest();
}
