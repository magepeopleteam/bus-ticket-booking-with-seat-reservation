<?php
/**
 * Bootstrap for `composer test:integration` (phpunit-integration.xml).
 *
 * Separate from tests/bootstrap.php on purpose: the unit suite proves the
 * SDK loads with NOTHING WordPress-shaped present, so its bootstrap stays
 * minimal. These tests need the opposite — enough of a fake WordPress that
 * the real Lifecycle/Telemetry/Consent/Survey/Announcements classes can
 * run unmodified against a real backend — so they get their own.
 */

require __DIR__ . '/wp-http-polyfill.php';
require dirname( __DIR__ ) . '/wp-option-polyfill.php';
require dirname( __DIR__ ) . '/wp-cron-polyfill.php';
require dirname( __DIR__ ) . '/wp-hook-polyfill.php';
require dirname( __DIR__ ) . '/wp-filter-polyfill.php';
require dirname( __DIR__ ) . '/wp-admin-polyfill.php';

require dirname( __DIR__, 2 ) . '/appneck-sdk.php';

// add_action now exists (wp-hook-polyfill.php), so the loader deferred to
// `plugins_loaded` — which nothing in this harness fires. Calling it
// directly is the documented, idempotent way to load early.
appneck_sdk_load_latest();

// After the SDK, so the Logger interface it implements exists.
require dirname( __DIR__ ) . '/RecordingLogger.php';

require __DIR__ . '/Support/EnvCredentials.php';
require __DIR__ . '/Support/OrgPanelClient.php';
require __DIR__ . '/Support/IntegrationTestCase.php';
