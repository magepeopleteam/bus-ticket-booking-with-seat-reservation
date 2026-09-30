<?php

namespace Appneck\Sdk\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The version-safe loader (see appneck-sdk.php for the pattern and why).
 *
 * Each scenario runs in its own PHP process via tests/loader-scenario.php,
 * because the loader records its decision in a constant and constants
 * cannot be undefined between cases.
 *
 * The fixture bootstraps define an UNGUARDED class, so if the loader ever
 * let two copies through, the scenario reports a real
 * "Cannot redeclare class" fatal rather than passing quietly.
 */
class LoaderTest extends TestCase {

	/**
	 * @param string $versions Comma-separated, in include order.
	 * @return array<string, mixed>
	 */
	private function run_scenario( $versions, $mode = 'normal' ) {
		$command = escapeshellcmd( PHP_BINARY )
			. ' ' . escapeshellarg( __DIR__ . '/loader-scenario.php' )
			. ' ' . escapeshellarg( $versions )
			. ' ' . escapeshellarg( $mode )
			. ' 2>&1';

		$output = shell_exec( $command );
		$decoded = json_decode( (string) $output, true );

		$this->assertIsArray(
			$decoded,
			"Scenario did not return JSON — it probably fatally errored.\nOutput was:\n" . $output
		);

		return $decoded;
	}

	public function test_two_copies_do_not_fatal_and_the_newer_one_wins(): void {
		$result = $this->run_scenario( '0.1.0,0.9.0' );

		$this->assertNull( $result['fatal'], 'A fatal error escaped the loader' );
		$this->assertSame( array( '0.9.0' ), $result['loaded'], 'Exactly one copy should load' );
		$this->assertSame( '0.9.0', $result['loaded_version'] );
		$this->assertSame( '0.9.0', $result['probe_version'] );
	}

	/**
	 * The case a plain class_exists() guard gets WRONG: the older copy is
	 * included first, so first-one-wins would leave the site running
	 * stale SDK code even though a newer copy is present.
	 */
	public function test_the_newest_wins_even_when_an_older_copy_is_included_first(): void {
		$result = $this->run_scenario( '0.2.0,0.1.0' );

		$this->assertNull( $result['fatal'] );
		$this->assertSame( array( '0.2.0' ), $result['loaded'] );
	}

	/**
	 * The case a naive string sort gets wrong: "0.10.0" sorts BEFORE
	 * "0.9.0" as a string, and this bug is invisible until the tenth
	 * release. version_compare gets it right.
	 */
	public function test_version_ordering_is_numeric_not_lexicographic(): void {
		$result = $this->run_scenario( '0.9.0,0.10.0' );

		$this->assertSame( array( '0.10.0' ), $result['loaded'] );
		$this->assertSame( '0.10.0', $result['loaded_version'] );
	}

	public function test_many_copies_still_load_exactly_once(): void {
		$result = $this->run_scenario( '1.0.0,0.4.2,2.3.1,0.9.9,2.3.0' );

		$this->assertNull( $result['fatal'] );
		$this->assertCount( 1, $result['loaded'] );
		$this->assertSame( '2.3.1', $result['loaded_version'] );
	}

	public function test_identical_versions_load_once(): void {
		$result = $this->run_scenario( '1.1.0,1.1.0,1.1.0' );

		$this->assertNull( $result['fatal'] );
		$this->assertSame( array( '1.1.0' ), $result['loaded'] );
	}

	public function test_a_single_copy_loads_normally(): void {
		$result = $this->run_scenario( '0.1.0' );

		$this->assertSame( array( '0.1.0' ), $result['loaded'] );
	}

	/**
	 * Included after plugins_loaded has already fired — a theme, a late
	 * mu-plugin, a plugin activated mid-request. Must load immediately
	 * rather than waiting for a hook that has been and gone.
	 */
	public function test_it_loads_immediately_when_plugins_loaded_already_fired(): void {
		$result = $this->run_scenario( '0.1.0,0.5.0', 'late' );

		$this->assertNull( $result['fatal'] );
		$this->assertCount( 1, $result['loaded'] );

		// The first include wins here, and that is correct rather than a
		// bug: once the hook has fired there is no later moment at which
		// a newer copy could still register, so deferring would mean
		// never loading. Documented in appneck-sdk.php.
		$this->assertSame( array( '0.1.0' ), $result['loaded'] );
	}

	/**
	 * Nothing is loaded, and no class is defined, merely by including the
	 * loader — that is the property which makes registration safe.
	 */
	public function test_including_the_loader_defines_no_sdk_classes(): void {
		// Comments stripped first — the file discusses classes at length,
		// and matching on the word would only prove it mentions them.
		$code = php_strip_whitespace( dirname( __DIR__ ) . '/appneck-sdk.php' );

		$this->assertSame(
			0,
			preg_match( '/\b(final\s+|abstract\s+)*(class|interface|trait|enum)\s+\w/', $code ),
			'appneck-sdk.php must declare no types — see its own doc comment for why'
		);

		// And it must not load the bootstrap at include time either;
		// only appneck_sdk_load_latest() may do that, after the registry
		// has had a chance to fill.
		$this->assertSame(
			1,
			preg_match_all( '/require_once\s+\$bootstrap;/', $code ),
			'The bootstrap must be required in exactly one place'
		);
	}

	/**
	 * Every type under src/ must appear in bootstrap.php's registry.
	 *
	 * This is the failure that hides: composer.json also declares PSR-4
	 * autoloading, so a class added to src/ and forgotten here works
	 * perfectly for anyone developing with Composer, and fatals only on
	 * the BUNDLED, no-vendor install that most real plugins ship — after
	 * release, on somebody else's site, with a "Class not found" that
	 * names a file plainly sitting on disk.
	 *
	 * Asserted by walking the directory rather than against a hardcoded
	 * list, so it keeps holding for classes nobody has written yet.
	 */
	public function test_every_class_under_src_is_registered_in_the_bootstrap(): void {
		$root      = dirname( __DIR__ );
		$bootstrap = file_get_contents( $root . '/bootstrap.php' );

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS )
		);

		$checked = 0;

		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			// src/Foo/Bar.php  ->  Appneck\Sdk\Foo\Bar
			$relative = substr( $file->getPathname(), strlen( $root . '/src/' ), -4 );
			$class    = 'Appneck\\Sdk\\' . str_replace( '/', '\\', $relative );

			// The registry writes them escaped, as PHP string literals.
			$needle = "'" . str_replace( '\\', '\\\\', $class ) . "'";

			$this->assertStringContainsString(
				$needle,
				$bootstrap,
				$class . ' is not registered in bootstrap.php — it would be missing on a no-Composer install.'
			);

			$this->assertTrue(
				class_exists( $class ) || interface_exists( $class ),
				$class . ' is registered but did not load.'
			);

			++$checked;
		}

		// A guard on the guard: a broken path would otherwise make this
		// test pass by iterating nothing at all.
		$this->assertGreaterThan( 20, $checked );
	}
}
