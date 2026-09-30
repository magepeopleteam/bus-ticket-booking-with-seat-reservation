<?php

namespace Appneck\Sdk;

/**
 * Gathers the site/environment payload POST /sdk/v1/installations wants.
 *
 * Every getter here is defensive to the point of paranoia, and that is
 * the whole design. This code runs on WordPress installs going back to
 * 5.9, on hosts with functions disabled, on sites where WooCommerce may
 * or may not exist, during an `uninstall.php` request where almost
 * nothing is loaded, and inside `register_activation_hook` where the
 * plugin's own bootstrap has not run. A notice emitted here is a notice
 * printed into someone's admin screen; a fatal is their white screen.
 *
 * So: no function is called without function_exists, no constant read
 * without defined, no array indexed without isset, and every field is
 * allowed to be absent. The server treats all of them as nullable
 * (InstallationController's validation rules) — a missing field is
 * simply unknown, never an error.
 */
final class Environment {

	/** @var string|null Absolute path to the host plugin's main file. */
	private $plugin_file;

	/** @var array<string, mixed> */
	private $overrides;

	/**
	 * @param string|null          $plugin_file For reading the Version header.
	 * @param array<string, mixed> $overrides   Values the host plugin knows
	 *                                          better than this class can
	 *                                          detect — a plugin_version it
	 *                                          already has in a constant, a
	 *                                          country from its own settings.
	 *                                          Also how tests pin a domain.
	 */
	public function __construct( $plugin_file = null, array $overrides = array() ) {
		$this->plugin_file = $plugin_file;
		$this->overrides   = $overrides;
	}

	/**
	 * The registration payload. Null-valued fields are stripped rather
	 * than sent as null: the server's rules are `nullable`, so either
	 * works, but omitting keeps the signed body smaller and makes a real
	 * value distinguishable from a field this SDK cannot determine.
	 *
	 * @return array<string, mixed>
	 */
	public function collect() {
		$payload = array(
			'site_domain'         => $this->site_domain(),
			'plugin_version'      => $this->plugin_version(),
			'php_version'         => $this->php_version(),
			'wordpress_version'   => $this->wordpress_version(),
			'woocommerce_version' => $this->woocommerce_version(),
			'locale'              => $this->locale(),
			'timezone'            => $this->timezone(),
			'country'             => $this->country(),
			'is_multisite'        => $this->is_multisite(),
			'server_type'         => $this->server_type(),
			'site_admins'         => $this->site_admins(),
		);

		// Caller-supplied values win over anything detected here.
		$payload = array_merge( $payload, $this->overrides );

		return array_filter(
			$payload,
			static function ( $value ) {
				// Keep `false` (is_multisite) and `0`; drop only null/''.
				return null !== $value && '' !== $value;
			}
		);
	}

	/**
	 * The site's host, not its full URL — the server's Site aggregate is
	 * keyed by domain (journal 8.4), and sending a path would fragment
	 * one site into several.
	 *
	 * home_url() rather than site_url(): home_url is the address visitors
	 * use, which is what a plugin company recognises as "their customer's
	 * site". site_url can point at a /wp subdirectory.
	 *
	 * @return string|null
	 */
	public function site_domain() {
		$url = null;

		if ( function_exists( 'home_url' ) ) {
			$url = home_url();
		} elseif ( function_exists( 'get_bloginfo' ) ) {
			$url = get_bloginfo( 'url' );
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$host = parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $host : null;
	}

	/**
	 * Read from the plugin's own file header.
	 *
	 * get_file_data(), not get_plugin_data(): the latter lives in
	 * wp-admin/includes/plugin.php, which is NOT loaded on front-end
	 * requests or during uninstall.php, so calling it there is a fatal.
	 * get_file_data() is in wp-includes and always available.
	 *
	 * @return string|null
	 */
	public function plugin_version() {
		if ( null === $this->plugin_file || ! function_exists( 'get_file_data' ) ) {
			return null;
		}

		if ( ! is_string( $this->plugin_file ) || ! is_file( $this->plugin_file ) ) {
			return null;
		}

		$data = get_file_data( $this->plugin_file, array( 'Version' => 'Version' ), 'plugin' );

		if ( ! is_array( $data ) || empty( $data['Version'] ) ) {
			return null;
		}

		return (string) $data['Version'];
	}

	/**
	 * The host plugin's own display name, read from the same file header.
	 * NOT part of collect()'s registration payload — the server already
	 * knows the product's name from the API key. This exists so the consent
	 * prompt can say which plugin is asking, which is the difference
	 * between a question a site owner can answer and one they cannot.
	 *
	 * @return string|null
	 */
	public function plugin_name() {
		if ( null === $this->plugin_file || ! function_exists( 'get_file_data' ) ) {
			return null;
		}

		if ( ! is_string( $this->plugin_file ) || ! is_file( $this->plugin_file ) ) {
			return null;
		}

		$data = get_file_data( $this->plugin_file, array( 'Name' => 'Plugin Name' ), 'plugin' );

		if ( ! is_array( $data ) || empty( $data['Name'] ) ) {
			return null;
		}

		return (string) $data['Name'];
	}

	/** @return string */
	public function php_version() {
		// phpversion() can be disabled; the constant cannot.
		return defined( 'PHP_VERSION' ) ? PHP_VERSION : (string) phpversion();
	}

	/** @return string|null */
	public function wordpress_version() {
		// $wp_version is the source of truth and is set before plugins
		// load, so it is readable even in contexts where get_bloginfo is
		// filtered by a security plugin trying to hide the version.
		if ( isset( $GLOBALS['wp_version'] ) && is_string( $GLOBALS['wp_version'] ) ) {
			return $GLOBALS['wp_version'];
		}

		if ( function_exists( 'get_bloginfo' ) ) {
			$version = get_bloginfo( 'version' );

			return is_string( $version ) && '' !== $version ? $version : null;
		}

		return null;
	}

	/**
	 * Absent on the overwhelming majority of sites, which is normal and
	 * not a failure. Three sources, cheapest first.
	 *
	 * @return string|null
	 */
	public function woocommerce_version() {
		if ( defined( 'WC_VERSION' ) ) {
			return (string) WC_VERSION;
		}

		if ( defined( 'WOOCOMMERCE_VERSION' ) ) {
			return (string) WOOCOMMERCE_VERSION;
		}

		// Last resort: the class exists but neither constant is defined,
		// which happens with some partial/bundled Woo installs.
		if ( class_exists( 'WooCommerce', false ) && isset( $GLOBALS['woocommerce'] ) ) {
			$woo = $GLOBALS['woocommerce'];

			if ( is_object( $woo ) && isset( $woo->version ) && is_string( $woo->version ) ) {
				return $woo->version;
			}
		}

		return null;
	}

	/** @return string|null */
	public function locale() {
		if ( function_exists( 'get_locale' ) ) {
			$locale = get_locale();

			return is_string( $locale ) && '' !== $locale ? $locale : null;
		}

		return null;
	}

	/**
	 * An IANA identifier where the site has one ("Europe/London"). A site
	 * configured with a raw UTC offset has no identifier, and
	 * wp_timezone_string() returns "+05:30" for those — sent as-is
	 * rather than mangled into a fake identifier.
	 *
	 * @return string|null
	 */
	public function timezone() {
		if ( function_exists( 'wp_timezone_string' ) ) {
			$timezone = wp_timezone_string();

			if ( is_string( $timezone ) && '' !== $timezone ) {
				return $timezone;
			}
		}

		if ( function_exists( 'get_option' ) ) {
			$timezone = get_option( 'timezone_string' );

			if ( is_string( $timezone ) && '' !== $timezone ) {
				return $timezone;
			}
		}

		return null;
	}

	/**
	 * Deliberately NOT geolocated from an IP address.
	 *
	 * journal 11.2 forbids reading, storing or logging IP addresses
	 * anywhere in the telemetry path, and inferring a country from one
	 * would be doing exactly that with an extra step. Only used when the
	 * site itself has already declared a country for its own purposes —
	 * WooCommerce's configured store base — which is a setting the site
	 * owner chose, not an observation about their visitors.
	 *
	 * @return string|null
	 */
	public function country() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();

		if ( ! is_object( $wc ) || ! isset( $wc->countries ) || ! is_object( $wc->countries ) ) {
			return null;
		}

		if ( ! method_exists( $wc->countries, 'get_base_country' ) ) {
			return null;
		}

		$country = $wc->countries->get_base_country();

		return is_string( $country ) && '' !== $country ? $country : null;
	}

	/** @return bool */
	public function is_multisite() {
		return function_exists( 'is_multisite' ) ? (bool) is_multisite() : false;
	}

	/**
	 * "nginx/1.27.5", "Apache". Truncated because the server's column is
	 * a 255-char string and some hosts put a paragraph in here.
	 *
	 * @return string|null
	 */
	public function server_type() {
		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || ! is_string( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return null;
		}

		$software = $_SERVER['SERVER_SOFTWARE'];

		if ( function_exists( 'sanitize_text_field' ) ) {
			$software = sanitize_text_field( $software );
		} else {
			$software = trim( strip_tags( $software ) );
		}

		if ( '' === $software ) {
			return null;
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $software, 0, 255 ) : substr( $software, 0, 255 );
	}

	/**
	 * Every WordPress user holding the "administrator" role, name and
	 * email — who to contact about this site, not just what it's running.
	 *
	 * get_users() lives in wp-includes/user.php, always loaded, unlike
	 * plugin_inventory()'s get_plugins(). Still wrapped in
	 * function_exists: this runs inside uninstall.php too, where even
	 * wp-includes is only partially bootstrapped.
	 *
	 * @return array<int, array{name: string, email: string}>
	 */
	public function site_admins() {
		if ( ! function_exists( 'get_users' ) ) {
			return array();
		}

		$users = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'display_name', 'user_email' ),
			)
		);

		if ( ! is_array( $users ) ) {
			return array();
		}

		$admins = array();

		foreach ( $users as $user ) {
			$name  = ( is_object( $user ) && isset( $user->display_name ) ) ? (string) $user->display_name : '';
			$email = ( is_object( $user ) && isset( $user->user_email ) ) ? (string) $user->user_email : '';

			if ( '' === $name && '' === $email ) {
				continue;
			}

			$admins[] = array(
				'name'  => $name,
				'email' => $email,
			);
		}

		return $admins;
	}

	/**
	 * The site's plugin and theme inventory — what is installed, what is
	 * switched on, and which theme is running.
	 *
	 * Deliberately NOT part of collect(): that is the registration
	 * payload, whose fields all map to a column on `installations`. This
	 * one is an unbounded, changing list that the server stores as its own
	 * snapshot row (`installation_environments`), and it rides the
	 * heartbeat rather than registration because it is a fact that keeps
	 * changing after the plugin is first activated.
	 *
	 * Same paranoia as the rest of this class, and it earns it here more
	 * than anywhere: get_plugins() lives in wp-admin/includes/plugin.php,
	 * which is NOT loaded on a front-end request — and a heartbeat fires
	 * from WP-Cron, which IS a front-end request. So the file is required
	 * defensively rather than assumed, and every failure returns null so a
	 * site whose inventory cannot be read still sends its heartbeat.
	 *
	 * @return array{plugins: array<int, array<string, mixed>>, theme: array<string, mixed>}|null
	 */
	public function plugin_inventory() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'wp_get_theme' ) ) {
			return null;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			if ( ! defined( 'ABSPATH' ) || ! is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				return null;
			}

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// A require that loaded something other than what we expected
			// (a stripped install, a mu-plugin shadowing the path).
			if ( ! function_exists( 'get_plugins' ) ) {
				return null;
			}
		}

		$all_plugins = get_plugins();

		if ( ! is_array( $all_plugins ) ) {
			return null;
		}

		$active_plugins = get_option( 'active_plugins', array() );

		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}

		$plugins = array();

		foreach ( $all_plugins as $slug => $data ) {
			$plugins[] = array(
				// The slug is the "folder/file.php" path WordPress keys
				// active_plugins by — sent as-is so the two lists can be
				// compared server-side without re-deriving anything.
				'slug'    => (string) $slug,
				'name'    => isset( $data['Name'] ) ? (string) $data['Name'] : '',
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
				'active'  => in_array( $slug, $active_plugins, true ),
			);
		}

		$theme = wp_get_theme();

		if ( ! is_object( $theme ) || ! method_exists( $theme, 'get' ) ) {
			return null;
		}

		$parent = method_exists( $theme, 'parent' ) ? $theme->parent() : null;

		$theme_data = array(
			'name'         => (string) $theme->get( 'Name' ),
			'version'      => (string) $theme->get( 'Version' ),
			'author'       => (string) $theme->get( 'Author' ),
			// null, not '', when there is no parent: the absence of a
			// parent theme is a fact, and an empty string would be
			// indistinguishable from a parent whose name failed to read.
			'parent_theme' => ( is_object( $parent ) && method_exists( $parent, 'get' ) )
				? (string) $parent->get( 'Name' )
				: null,
		);

		return array(
			'plugins' => $plugins,
			'theme'   => $theme_data,
		);
	}

	/**
	 * A UUID for a brand-new installation. The CLIENT generates this —
	 * journal 8.4: the plugin creates the id locally on first activation
	 * and resends it on every request, so the server never issues one.
	 *
	 * @return string
	 */
	public static function generate_installation_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		// WP 4.7+ always has the above; this covers WP-CLI and test
		// contexts where only part of wp-includes is loaded.
		$bytes = function_exists( 'random_bytes' )
			? random_bytes( 16 )
			: pack( 'N4', mt_rand(), mt_rand(), mt_rand(), mt_rand() );

		// Set the version (4) and variant bits, per RFC 4122.
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		$hex = bin2hex( $bytes );

		return substr( $hex, 0, 8 ) . '-'
			. substr( $hex, 8, 4 ) . '-'
			. substr( $hex, 12, 4 ) . '-'
			. substr( $hex, 16, 4 ) . '-'
			. substr( $hex, 20, 12 );
	}
}
