<?php

namespace Appneck\Sdk\Tests\Integration\Support;

/**
 * Everything the integration suite needs to reach a real backend, read
 * once from the environment rather than hardcoded CLI positional args.
 *
 * Every value has a sensible default matching this project's own Docker
 * dev stack (the `nginx` service name on the compose network, and
 * `demo@example.com` / `password` — the dev fixture `DemoSeeder` itself
 * prints to the console as seeded, not a secret). Defaults exist so a
 * contributor working inside this monorepo's own dev stack can run
 * `composer test:integration` with nothing set; anyone pointing this at a
 * different backend overrides every value that matters via real env vars.
 *
 * `configured()` is deliberately narrower than "every var has a value" —
 * the defaults mean that's always true. It answers "is there a real
 * *product* to test against", which only a human (or CI) setting
 * APPNECK_SDK_TEST_API_KEY can answer, and is what gates the whole suite
 * from running unintentionally for a developer who has never heard of it.
 */
final class EnvCredentials {

	public function base_url() {
		return $this->env( 'APPNECK_SDK_TEST_BASE_URL', 'http://nginx' );
	}

	public function api_key() {
		return $this->env( 'APPNECK_SDK_TEST_API_KEY', '' );
	}

	public function product_secret() {
		return $this->env( 'APPNECK_SDK_TEST_PRODUCT_SECRET', '' );
	}

	/** A second product's credentials, with nothing configured on it — the cross-product-isolation / zero case. */
	public function second_api_key() {
		return $this->env( 'APPNECK_SDK_TEST_SECOND_API_KEY', '' );
	}

	public function second_product_secret() {
		return $this->env( 'APPNECK_SDK_TEST_SECOND_PRODUCT_SECRET', '' );
	}

	/** Needed to author/delete survey questions and announcements through the real Org Panel API, not just the SDK zone. */
	public function organization_id() {
		return $this->env( 'APPNECK_SDK_TEST_ORGANIZATION_ID', '' );
	}

	/** The product id owning api_key()/product_secret() above, in the same organization. */
	public function product_id() {
		return $this->env( 'APPNECK_SDK_TEST_PRODUCT_ID', '' );
	}

	public function org_email() {
		return $this->env( 'APPNECK_SDK_TEST_ORG_EMAIL', 'demo@example.com' );
	}

	public function org_password() {
		return $this->env( 'APPNECK_SDK_TEST_ORG_PASSWORD', 'password' );
	}

	/**
	 * Whether there's a real product to test the SDK zone against. False
	 * is the normal, expected state for a developer who has never set
	 * these — the whole suite skips rather than failing for them.
	 */
	public function configured() {
		return '' !== $this->api_key() && '' !== $this->product_secret();
	}

	/**
	 * Whether Org-Panel-authored fixtures (survey questions,
	 * announcements) can be created for this run. Narrower than
	 * configured() — the org bearer-token login and product/organization
	 * ids are only needed by the two tests that author real data.
	 */
	public function can_author_fixtures() {
		return $this->configured() && '' !== $this->organization_id() && '' !== $this->product_id();
	}

	public function has_second_product() {
		return '' !== $this->second_api_key() && '' !== $this->second_product_secret();
	}

	private function env( $name, $default ) {
		$value = getenv( $name );

		return false !== $value && '' !== $value ? $value : $default;
	}
}
