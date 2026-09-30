<?php

namespace Appneck\Sdk\Tests\Integration;

use Appneck\Sdk\Config;
use Appneck\Sdk\License;
use Appneck\Sdk\LicenseClient;
use Appneck\Sdk\Storage\ArrayLicenseStore;
use Appneck\Sdk\Tests\Integration\Support\IntegrationTestCase;

/**
 * The full licensing round trip against a real backend: activate →
 * validate → deactivate → validate.
 *
 * The last step is the one worth having. Everything up to it could pass
 * against a server that simply answered `valid: true` to anything, and
 * the only way to prove the deactivation actually released the slot is
 * to ask again afterwards and get `not_activated_on_domain` back — a
 * DIFFERENT rejection from `license_not_found`, which is what a wrong
 * key or a wrong product would produce.
 *
 * It also proves the thing journal §23.1 exists for, incidentally and for
 * free: this test never registers an installation, so every call here is
 * made by a "site" the Tracking side has never heard of.
 *
 * The fixtures are real rows created through the real Org Panel API and
 * cleaned up in tearDown — a license plan and a license. Licenses have no
 * DELETE (journal §22.13: lifecycle transitions are `action` verbs, and
 * the row is a permanent commercial record), so cleanup cancels rather
 * than removes, which is the closest thing to "gone" the domain has.
 */
class LicenseCheckTest extends IntegrationTestCase {

	/** @var string|null */
	private $plan_id = null;

	/** @var string|null */
	private $license_id = null;

	/** @var \Appneck\Sdk\Tests\Integration\Support\OrgPanelClient|null */
	private $panel = null;

	protected function tearDown(): void {
		if ( null !== $this->panel && null !== $this->license_id ) {
			$this->panel->patch( $this->licenses_path() . '/' . $this->license_id, array( 'action' => 'cancel' ) );
		}

		if ( null !== $this->panel && null !== $this->plan_id ) {
			$this->panel->post( $this->plans_path() . '/' . $this->plan_id . '/archive', array() );
		}

		parent::tearDown();
	}

	private function plans_path() {
		$credentials = $this->credentials();

		return '/app/v1/organizations/' . $credentials->organization_id()
			. '/products/' . $credentials->product_id() . '/license-plans';
	}

	private function licenses_path() {
		$credentials = $this->credentials();

		return '/app/v1/organizations/' . $credentials->organization_id()
			. '/products/' . $credentials->product_id() . '/licenses';
	}

	private function sdk_license( $domain ) {
		$credentials = $this->credentials();

		$license = new License(
			new LicenseClient(
				new Config(
					$credentials->api_key(),
					$credentials->product_secret(),
					$credentials->base_url()
				)
			),
			new ArrayLicenseStore()
		);

		return $license->set_domain( $domain );
	}

	public function test_activate_validate_deactivate_validate_against_a_real_backend(): void {
		$this->require_fixture_authoring();

		$this->panel = $this->org_panel_client();

		$plan = $this->panel->post(
			$this->plans_path(),
			array(
				'name'             => 'SDK integration plan ' . bin2hex( random_bytes( 3 ) ),
				'activation_limit' => 3,
				'validity_days'    => null,
			)
		);

		$this->assertCheck( 'License plan created', isset( $plan['body']['id'] ), json_encode( $plan ) );
		$this->plan_id = $plan['body']['id'];

		$issued = $this->panel->post(
			$this->licenses_path(),
			array(
				'license_plan_id' => $this->plan_id,
				'customer_name'   => 'SDK Integration Test',
				'customer_email'  => 'sdk-integration@example.com',
			)
		);

		$this->assertCheck( 'License issued', isset( $issued['body']['one_time_key'] ), json_encode( $issued ) );
		$this->license_id = $issued['body']['id'];

		$key     = $issued['body']['one_time_key'];
		$domain  = $this->random_domain( 'sdk-license' );
		$license = $this->sdk_license( $domain );

		// 1. Activate.
		$activate = $license->activate( $key );
		$this->assertCheck( 'activate returned 2xx', $activate->ok(), 'HTTP ' . $activate->status() . ' ' . $activate->raw_body() );
		$this->assertCheck( 'activate says valid', true === $activate->get( 'valid' ), $activate->raw_body() );
		$this->assertCheck( 'the key is now stored locally', $license->has_license() );

		// 2. Validate — should be granted on this domain.
		$validate = $license->validate( $key );
		$this->assertCheck( 'validate returned 2xx', $validate->ok(), 'HTTP ' . $validate->status() . ' ' . $validate->raw_body() );
		$this->assertCheck( 'validate says valid', true === $validate->get( 'valid' ), $validate->raw_body() );
		$this->assertCheck( 'is_valid() is true from cache', $license->is_valid() );

		// 3. Deactivate.
		$deactivate = $license->deactivate( $key );
		$this->assertCheck( 'deactivate returned 2xx', $deactivate->ok(), 'HTTP ' . $deactivate->status() . ' ' . $deactivate->raw_body() );
		$this->assertCheck( 'deactivate says success', true === $deactivate->get( 'success' ), $deactivate->raw_body() );
		$this->assertCheck( 'local state was cleared', ! $license->has_license() );

		// 4. Validate again — the slot must genuinely be released. A
		//    fresh License instance, so nothing can be answered from the
		//    cache the previous steps warmed.
		$after = $this->sdk_license( $domain )->validate( $key );

		$this->assertCheck( 'final validate returned 2xx', $after->ok(), 'HTTP ' . $after->status() . ' ' . $after->raw_body() );
		$this->assertCheck( 'final validate says invalid', false === $after->get( 'valid' ), $after->raw_body() );
		$this->assertCheck(
			'final validate reason is not_activated_on_domain',
			'not_activated_on_domain' === $after->get( 'reason' ),
			'Got: ' . $after->raw_body()
		);
	}
}
