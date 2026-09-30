<?php
/**
 * Loads the package through its own loader, NOT through Composer's
 * autoloader — which is the point: a bundled copy inside a WordPress
 * plugin has no vendor/ directory, and this suite proves that path
 * works rather than testing a configuration real users never hit.
 *
 * No WordPress is loaded. appneck-sdk.php detects the absence of
 * add_action and loads immediately.
 */
require_once dirname( __DIR__ ) . '/appneck-sdk.php';

// Test support classes. Required explicitly rather than autoloaded,
// for the same reason the SDK itself is: this suite deliberately runs
// with no Composer autoloader present.
require_once __DIR__ . '/FakeTransport.php';
require_once __DIR__ . '/RecordingLogger.php';
