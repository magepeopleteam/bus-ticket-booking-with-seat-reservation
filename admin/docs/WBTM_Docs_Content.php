<?php
	/*
   * @Author 		MagePeople Team
   * Copyright: 	mage-people.com
   *
   * Content source for the unified Documents screen.
   *
   * Everything the handbook shows lives here as plain data so the renderer
   * (WBTM_Docs) stays presentation-only and the copy can be translated,
   * searched and extended without touching markup.
   *
   * Chapter shape:
   *   'id'      => unique slug, used for the nav anchor and the URL hash
   *   'title'   => chapter heading
   *   'icon'    => FontAwesome class
   *   'plan'    => 'free' | 'pro'   (pro chapters render locked without the addon)
   *   'intro'   => short paragraph under the heading
   *   'blocks'  => ordered list of content blocks, each one of:
   *                  ['type' => 'image',  'file' => '<name>.jpg', 'caption' => '']
   *                  ['type' => 'text',   'text' => '']
   *                  ['type' => 'list',   'title' => '', 'items' => [ ['term','desc'] | 'plain' ]]
   *                  ['type' => 'steps',  'items' => ['step one', ...]]
   *                  ['type' => 'note'|'tip'|'warn', 'text' => '']
   *                  ['type' => 'table',  'head' => [], 'rows' => [[]]]
   *
   * Third-party addons can append their own chapters with the
   * 'wbtm_docs_chapters' filter.
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.

	if ( ! class_exists( 'WBTM_Docs_Content' ) ) {
		class WBTM_Docs_Content {

			/**
			 * Top-level groups shown in the sidebar, in order.
			 *
			 * @return array<string,array{title:string,icon:string}>
			 */
			public static function groups(): array {
				return array(
					'start'    => array(
						'title' => __( 'Getting Started', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-rocket',
					),
					'manage'   => array(
						'title' => __( 'Managing Your Fleet', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-bus',
					),
					'bus'      => array(
						'title' => __( 'Adding & Editing a Bus', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-pen-to-square',
					),
					'frontend' => array(
						'title' => __( 'The Customer Journey', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-user',
					),
					'orders'   => array(
						'title' => __( 'Bookings & Reporting', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-chart-line',
					),
					'settings' => array(
						'title' => __( 'Settings Reference', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-sliders',
					),
					'pro'      => array(
						'title' => __( 'PRO Features', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-crown',
					),
					'help'     => array(
						'title' => __( 'Reference & Help', 'bus-ticket-booking-with-seat-reservation' ),
						'icon'  => 'fas fa-life-ring',
					),
				);
			}

			/**
			 * Every documentation chapter.
			 *
			 * Called lazily (never at file-include time) so translation calls always
			 * run after the text domain is loaded — WordPress 6.7 warns otherwise.
			 *
			 * @return array<int,array>
			 */
			public static function chapters(): array {
				$label    = class_exists( 'WBTM_Functions' ) ? WBTM_Functions::get_name() : __( 'Bus', 'bus-ticket-booking-with-seat-reservation' );
				$chapters = array();

				/* ------------------------------------------------------------------ *
				 * GETTING STARTED
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'requirements',
					'group' => 'start',
					'plan'  => 'free',
					'icon'  => 'fas fa-server',
					'title' => __( 'System Requirements', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'To run the plugin comfortably your hosting environment should meet the following minimums. Anything lower may still work, but is not supported.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array(
							'type' => 'table',
							'head' => array( __( 'Requirement', 'bus-ticket-booking-with-seat-reservation' ), __( 'Minimum', 'bus-ticket-booking-with-seat-reservation' ), __( 'Recommended', 'bus-ticket-booking-with-seat-reservation' ) ),
							'rows' => array(
								array( __( 'WordPress', 'bus-ticket-booking-with-seat-reservation' ), '5.3', '6.0 ' . __( 'or newer', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( __( 'PHP', 'bus-ticket-booking-with-seat-reservation' ), '7.0', '8.1 ' . __( 'or newer', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( __( 'Database', 'bus-ticket-booking-with-seat-reservation' ), 'MySQL 5.7 / MariaDB 10.4', 'MySQL 8.0 / MariaDB 10.6' ),
								array( __( 'Web server', 'bus-ticket-booking-with-seat-reservation' ), 'Apache ' . __( 'or', 'bus-ticket-booking-with-seat-reservation' ) . ' Nginx', __( 'Either, with HTTPS enabled', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( __( 'PHP memory limit', 'bus-ticket-booking-with-seat-reservation' ), '128 MB', '256 MB' ),
							),
						),
						array(
							'type' => 'tip',
							'text' => __( 'You can check every one of these values on your own site under Bus → Settings → Status. That screen also has a "Copy report" button, which is the fastest way to give our support team the details they need.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'install-free',
					'group' => 'start',
					'plan'  => 'free',
					'icon'  => 'fas fa-download',
					'title' => __( 'Installing the free plugin', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The free plugin is published on WordPress.org, so you can install it straight from your dashboard without downloading anything.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'steps', 'items' => array(
							__( 'Log in to your WordPress admin panel.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Go to Plugins → Add New.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Type "Bus Ticket Booking with Seat Reservation" into the search box.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Click Install Now, then Activate.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'image', 'file' => 'install-wp-dashboard.jpg', 'caption' => __( 'The WordPress admin dashboard', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'install-plugins-add-new.jpg', 'caption' => __( 'Plugins → Add New', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'install-search-bus.jpg', 'caption' => __( 'Searching the plugin directory', 'bus-ticket-booking-with-seat-reservation' ) ),
						array(
							'type' => 'note',
							'text' => __( 'After activation a new "Bus" menu appears in the sidebar. On a brand-new site the plugin also imports a small set of sample buses in the background so you have something to look at straight away — see the Importing Demo Content chapter.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'install-pro',
					'group' => 'start',
					'plan'  => 'free',
					'icon'  => 'fas fa-crown',
					'title' => __( 'Installing the PRO addon', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The PRO addon is an extension, not a replacement. It needs both WooCommerce and the free plugin to be installed and active before it will run.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Prerequisites', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( 'WooCommerce', __( 'Handles the cart, checkout and payment gateways.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bus Ticket Booking with Seat Reservation', 'bus-ticket-booking-with-seat-reservation' ), __( 'The free plugin — PRO extends it and cannot run on its own.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'steps', 'items' => array(
							__( 'Purchase a plan — you receive the PRO plugin zip and a license key.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Download addon-bus-ticket-booking-with-seat-pro.zip from My Account.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Go to Plugins → Add New and click Upload Plugin.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Choose the zip file and click Install Now.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Activate the plugin.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Accept the prompt to install the MagePeople PDF Support plugin (needed for PDF tickets).', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Go to Bus → Settings → License, paste your license key and click Activate.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'image', 'file' => 'pro-purchase.jpg', 'caption' => __( 'Choosing a PRO plan', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'pro-upload-zip.jpg', 'caption' => __( 'Uploading the PRO zip file', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'pro-install.jpg', 'caption' => __( 'Installing the PRO addon', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'pro-activate.jpg', 'caption' => __( 'Activating the PRO addon', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'pro-pdf-prompt.jpg', 'caption' => __( 'The PDF library prompt shown after activation', 'bus-ticket-booking-with-seat-reservation' ) ),
						array(
							'type' => 'warn',
							'text' => __( 'Activate a license key to receive automatic updates. Without one the plugin keeps working, but it will not update itself.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				/* ------------------------------------------------------------------ *
				 * MANAGING YOUR FLEET
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'bus-list',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-list',
					'title' => sprintf( __( '%s List', 'bus-ticket-booking-with-seat-reservation' ), $label ),
					'intro' => __( 'The bus list is the central dashboard for your whole fleet. It combines statistics, filtering and per-bus actions on one screen.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-list.jpg', 'caption' => __( 'The modern bus list', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Summary cards', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Total Bus', 'bus-ticket-booking-with-seat-reservation' ), __( 'Every bus configured in the system, whatever its status.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Published', 'bus-ticket-booking-with-seat-reservation' ), __( 'Buses that are live and visible to customers.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'AC Coach', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many air-conditioned buses are registered.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Non AC Coach', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many non air-conditioned buses are registered.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Actions & filtering', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Add New Bus', 'bus-ticket-booking-with-seat-reservation' ), __( 'Opens the 4-step bus editor to create a new vehicle.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Status tabs', 'bus-ticket-booking-with-seat-reservation' ), __( 'Switch between All, Published, Draft and Trash.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Search bar', 'bus-ticket-booking-with-seat-reservation' ), __( 'Live search by bus name or coach number — results filter as you type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Type filter', 'bus-ticket-booking-with-seat-reservation' ), __( 'Narrow the list down to a single coach type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Grid / table toggle', 'bus-ticket-booking-with-seat-reservation' ), __( 'Switch between card and table layouts. Your choice is remembered.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Table columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Bus Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'Thumbnail, title, route (for example Berlin → Paris), number of stops and the schedule dates.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Coach No', 'bus-ticket-booking-with-seat-reservation' ), __( 'The unique identifier assigned to the vehicle.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bus Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'A shortcut to the seat plan configuration.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Coach Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Whether the vehicle is AC or Non AC.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Status', 'bus-ticket-booking-with-seat-reservation' ), __( 'The live publishing state.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'View on the frontend, edit, duplicate, or move to trash.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Duplicating a bus copies its entire configuration — seat plan, route, pricing — as a draft. It is by far the fastest way to add a second bus that runs a similar route.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-types',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-tags',
					'title' => __( 'Bus Types', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Bus types are the structural classifications of your fleet — AC, Non AC, Sleeper, Volvo, and so on. Customers can filter search results by them.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-type-manage.jpg', 'caption' => __( 'Bus Type management screen', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'bus-type-table.jpg', 'caption' => __( 'The bus type list', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The title of the classification, for example AC or Non AC.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'Optional notes. Shown as an em dash when empty.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Slug', 'bus-ticket-booking-with-seat-reservation' ), __( 'The URL-friendly version of the name, used in filters and links.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Count', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many buses are currently assigned to this type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Edit or delete the type.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'Deleting a type does not delete the buses assigned to it — those buses simply lose their type. Reassign them before deleting to avoid gaps in your search filters.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-stops',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-map-pin',
					'title' => __( 'Bus Stops', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Stops are the stations and cities your routes pass through. They are created once and then reused as boarding or dropping points on any bus.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-stops-manage.jpg', 'caption' => __( 'Bus Stops management screen', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'bus-stops-table.jpg', 'caption' => __( 'The bus stop list', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The location name, for example Berlin or Paris.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'Optional notes about the stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Slug', 'bus-ticket-booking-with-seat-reservation' ), __( 'The URL-friendly identifier.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Count', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many routes reference this stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Edit or delete the stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Renaming a stop is safe. The new name is migrated automatically across every route, fare table and existing booking, so routes never break and already-booked seats are never reopened.', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'type' => 'warn',
							'text' => __( 'Create all your stops before building routes. The route pricing matrix is generated from the stops you add to a bus, so adding stops later means revisiting the fare table.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pickup-dropoff',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-location-dot',
					'title' => __( 'Pickup & Drop-Off Points', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Pickup and drop-off points are precise meeting places inside a larger stop — a specific gate, terminal or street corner. They are managed as two separate lists.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'pickup-point-manage.jpg', 'caption' => __( 'Pickup point management', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'pickup-point-table.jpg', 'caption' => __( 'The pickup point list', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The title of the pickup station.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'Optional details about the location.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Under Bus Stop', 'bus-ticket-booking-with-seat-reservation' ), __( 'The parent stop this point belongs to. This is what links the two lists together.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Slug', 'bus-ticket-booking-with-seat-reservation' ), __( 'The URL-friendly identifier.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Count', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many buses reference this point.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Edit or delete the point.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'dropoff-point.jpg', 'caption' => __( 'Drop-off points use the same columns', 'bus-ticket-booking-with-seat-reservation' ) ),
						array(
							'type' => 'tip',
							'text' => __( 'Points are useful when one stop covers a wide area — "Bus Stand Gate 3" or "Railway Station North Exit" tells the passenger exactly where to wait. Enable them per bus in the Advanced step of the bus editor.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-features',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-star',
					'title' => __( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Features are the onboard amenities you advertise — Wi-Fi, air conditioning, charging ports, reclining seats. They appear on the search results card and the bus detail page.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-features-manage.jpg', 'caption' => __( 'Bus Features management', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'bus-features-table.jpg', 'caption' => __( 'The feature list, with icons', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The feature name together with its icon.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'Optional notes.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Slug', 'bus-ticket-booking-with-seat-reservation' ), __( 'The URL-friendly identifier.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Count', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many buses advertise this feature.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Edit or delete the feature.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Assign a FontAwesome icon when you create a feature. Icons make the search results card far easier to scan than plain text.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'coupons',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-ticket',
					'title' => __( 'Coupons & Discounts', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The coupon engine lets you create discount codes with detailed rules covering which buses they apply to, when they are valid, and who may redeem them.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'coupon-overview.jpg', 'caption' => __( 'The coupon dashboard', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Summary cards', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Total Coupons', 'bus-ticket-booking-with-seat-reservation' ), __( 'Every discount code created in the system.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Active Now', 'bus-ticket-booking-with-seat-reservation' ), __( 'Codes that are currently live and valid.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Redemptions', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many times coupons have been successfully applied.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Customer Savings', 'bus-ticket-booking-with-seat-reservation' ), __( 'The cumulative amount your passengers have saved.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'coupon-general.jpg', 'caption' => __( 'General & Discount tab', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'General & Discount', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Coupon Code', 'bus-ticket-booking-with-seat-reservation' ), __( 'The code customers type at checkout. Letters and numbers are converted to uppercase automatically.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Status', 'bus-ticket-booking-with-seat-reservation' ), __( 'A toggle that disables the coupon instantly without deleting it.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Discount Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Percentage of the eligible fare, a fixed amount per booking, or a fixed amount per seat.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Amount', 'bus-ticket-booking-with-seat-reservation' ), __( 'The discount value, interpreted according to the chosen type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Maximum Discount Cap', 'bus-ticket-booking-with-seat-reservation' ), __( 'An upper limit on the money saved. Only applies to percentage discounts.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'coupon-targeting.jpg', 'caption' => __( 'Targeting & Restrictions tab', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Targeting & Restrictions', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Which buses does this coupon apply to?', 'bus-ticket-booking-with-seat-reservation' ), __( 'All buses, or a chosen set of specific buses and bus types.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Minimum Fare', 'bus-ticket-booking-with-seat-reservation' ), __( 'The smallest cart total that makes the coupon valid. Use 0 for no minimum.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Minimum Seats', 'bus-ticket-booking-with-seat-reservation' ), __( 'The fewest seats that must be booked in one transaction. Use 0 for no minimum.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Maximum Seats', 'bus-ticket-booking-with-seat-reservation' ), __( 'The most seats a booking may contain and still qualify. Use 0 for no limit.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'coupon-validity.jpg', 'caption' => __( 'Validity & Schedule tab', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Validity & Schedule', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Active From / Active Until', 'bus-ticket-booking-with-seat-reservation' ), __( 'The date window in which the code can be redeemed. Leave either blank for no limit.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Valid Days of Week', 'bus-ticket-booking-with-seat-reservation' ), __( 'Restrict redemption to particular weekdays. Selecting none means every day is valid.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Travel Date From / Until', 'bus-ticket-booking-with-seat-reservation' ), __( 'Restrict the discount to journeys departing inside a travel window — separate from when the coupon is redeemed.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'coupon-usage.jpg', 'caption' => __( 'Usage & Eligibility tab', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Usage & Eligibility', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Total Usage Limit', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many times the coupon may be used overall. 0 means unlimited.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Limit Per Customer', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many times one user may redeem it. 0 means unlimited.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Limit Per Day', 'bus-ticket-booking-with-seat-reservation' ), __( 'A platform-wide daily cap. 0 means unlimited.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Require customer to be logged in', 'bus-ticket-booking-with-seat-reservation' ), __( 'Forces authentication before the coupon can be applied.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'First booking only', 'bus-ticket-booking-with-seat-reservation' ), __( 'Restricts the discount to brand-new customers.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Allow combining with other coupons', 'bus-ticket-booking-with-seat-reservation' ), __( 'Controls whether this promotion can stack with other active codes.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Restrict to User Roles', 'bus-ticket-booking-with-seat-reservation' ), __( 'Limits availability to chosen WordPress roles.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Restrict to Specific Emails', 'bus-ticket-booking-with-seat-reservation' ), __( 'A whitelist of addresses, one per line.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Usage Counter', 'bus-ticket-booking-with-seat-reservation' ), __( 'A live indicator showing how many times the coupon has been used so far.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
					),
				);

				$chapters[] = array(
					'id'    => 'terms',
					'group' => 'manage',
					'plan'  => 'free',
					'icon'  => 'fas fa-file-contract',
					'title' => __( 'Terms & Conditions', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Define the legal rules and ticketing policies passengers agree to when they book. Terms can be set globally here, or overridden for a single bus.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'terms-conditions.jpg', 'caption' => __( 'Terms & Condition screen', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'image', 'file' => 'terms-table.jpg', 'caption' => __( 'The terms list', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Fields', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Term Title', 'bus-ticket-booking-with-seat-reservation' ), __( 'The heading of the individual policy.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'The full policy text shown to the customer.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'A bus can carry its own Terms & Conditions from the bus editor. When both global and per-bus terms exist, the per-bus terms win for that bus.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				/* ------------------------------------------------------------------ *
				 * ADDING & EDITING A BUS
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'bus-step-1',
					'group' => 'bus',
					'plan'  => 'free',
					'icon'  => 'fas fa-circle-info',
					'title' => __( 'Step 1 — General Info', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The first step of the bus editor covers identification and the basic configuration everything else depends on.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-step1-general.jpg', 'caption' => __( 'General Info (step 1 of 4)', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Basic information', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Title', 'bus-ticket-booking-with-seat-reservation' ), __( 'The primary name of the vehicle, for example "Royal Bus".', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Description', 'bus-ticket-booking-with-seat-reservation' ), __( 'A rich-text editor with Visual and Code modes for route details or a service description.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Specifications', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Bus No', 'bus-ticket-booking-with-seat-reservation' ), __( 'A unique vehicle identifier or plate code, for example RB-1.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Coach Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'The structural category, chosen from the Bus Types you created.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Reservation on/off', 'bus-ticket-booking-with-seat-reservation' ), __( 'Enables or disables seat reservation for this bus. Turn it off for general boarding without assigned seats.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Boarding Time', 'bus-ticket-booking-with-seat-reservation' ), __( 'Show or hide the boarding time on the frontend.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Dropping Time', 'bus-ticket-booking-with-seat-reservation' ), __( 'Show or hide the dropping time on the frontend.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Features & media', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Bus Features', 'bus-ticket-booking-with-seat-reservation' ), __( 'Checkboxes for the onboard amenities this bus offers, plus a button to create new ones without leaving the page.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Featured Image', 'bus-ticket-booking-with-seat-reservation' ), __( 'The primary vehicle photo, used as the thumbnail across the plugin.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bus Logo', 'bus-ticket-booking-with-seat-reservation' ), __( 'The operator or company logo, shown on listings and detail pages.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Enable/Disable Gallery', 'bus-ticket-booking-with-seat-reservation' ), __( 'Turns on a photo gallery for the bus. Drag images to reorder them; the first is used as the featured image.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Payment Method', 'bus-ticket-booking-with-seat-reservation' ), __( 'A read-only summary of the active checkout configuration, with a shortcut to Payment Settings.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Set the Bus No and Coach Type before moving on. The pricing and seat layout steps both build on these basics.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-step-2',
					'group' => 'bus',
					'plan'  => 'free',
					'icon'  => 'fas fa-chair',
					'title' => __( 'Step 2 — Seat Configuration', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The seat editor is a drag-and-drop workspace for designing the interior of the vehicle: the seating matrix, the aisle, and fixtures such as doors and toilets.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-step2-seat-grid.jpg', 'caption' => __( 'Seat Configure (step 2 of 4)', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Seat type & global toggles', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Seat Plan', 'bus-ticket-booking-with-seat-reservation' ), __( 'Customers pick their own seat from an interactive map.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Without Seat Plan', 'bus-ticket-booking-with-seat-reservation' ), __( 'Customers buy a quantity of tickets and the system assigns capacity automatically — no seat map is shown.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Enable Multiple Cabin/Coach Configuration', 'bus-ticket-booking-with-seat-reservation' ), __( 'Splits the vehicle into several cabins or coaches, for train-style reservations.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Show Upper Deck', 'bus-ticket-booking-with-seat-reservation' ), __( 'Converts the layout into a double-decker with an independent upper level.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Layout settings', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Enable Seat-Wise Price Override', 'bus-ticket-booking-with-seat-reservation' ), __( 'Lets you set a custom fare on individual seats — for example a premium price for front-row seats.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Driver Position', 'bus-ticket-booking-with-seat-reservation' ), __( 'Places the driver on the left or the right of the layout.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seat Template', 'bus-ticket-booking-with-seat-reservation' ), __( 'Generates a complete layout in one click from a common configuration.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seat Numbering', 'bus-ticket-booking-with-seat-reservation' ), __( 'How labels are generated, for example sequential (1, 2, 3…) or row-letter based (A1, A2…).', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Grid Dimensions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Seat Rows, Seat Columns and Aisle Position define the shape of the matrix.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Generate Bus Seat', 'bus-ticket-booking-with-seat-reservation' ), __( 'Applies the settings above and renders the seating matrix.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Visual grid workspace', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'BUS FRONT indicator', 'bus-ticket-booking-with-seat-reservation' ), __( 'Marks the front orientation of the vehicle so the layout matches reality.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Drag-and-drop toolbar', 'bus-ticket-booking-with-seat-reservation' ), __( 'Door, Toilet, Driver, Window, Food Stall, Luggage, Stairs, Aisle, Exit and Clear can all be dropped onto the grid.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seating matrix', 'bus-ticket-booking-with-seat-reservation' ), __( 'The generated rows and columns with their labels, plus Add New Row and Enable Rotation controls.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( '"Without Seat Plan" cannot be combined with Multiple Cabin/Coach mode — cabins require a seat plan, so the option is disabled while cabins are on.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-decks',
					'group' => 'bus',
					'plan'  => 'free',
					'icon'  => 'fas fa-layer-group',
					'title' => __( 'Double-Decker Cabins & Decks', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Every cabin can carry an optional Upper Deck alongside its Lower Deck. Each deck is designed independently and priced independently, which suits double-decker buses and train carriages alike.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-step2-decks.jpg', 'caption' => __( 'Lower and Upper Deck configuration', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Lower Deck', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Price Multiplier (Lower Deck)', 'bus-ticket-booking-with-seat-reservation' ), __( 'Scales the base fare for this deck. 1.0 means the same as the base price.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Grid & template controls', 'bus-ticket-booking-with-seat-reservation' ), __( 'Template selector, numbering scheme, seat rows, seat columns and aisle position for this deck alone.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Preview canvas', 'bus-ticket-booking-with-seat-reservation' ), __( 'The interactive drag-and-drop workspace, with the full fixture toolbar and a Generate Seat Plan button.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Upper Deck', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Enable Upper Deck', 'bus-ticket-booking-with-seat-reservation' ), __( 'Turns this cabin into a double-decker. Until it is switched on the cabin behaves exactly as before.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Price Multiplier (Upper Deck)', 'bus-ticket-booking-with-seat-reservation' ), __( 'Independent pricing for the upper level — 1.5 makes upper-deck seats 50% more expensive, for instance.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Preview canvas', 'bus-ticket-booking-with-seat-reservation' ), __( 'A separate layout canvas with its own Generate Upper Deck Seat Plan button.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'text',
							'text' => __( 'On the booking page passengers switch between decks with a tab inside the cabin. Upper and lower seats are tracked separately end to end, so the same seat number on each deck never clashes for availability, holds, cart or orders. The chosen deck is shown everywhere the seat appears — booking list, passenger list, PDF and thermal tickets, email, admin counter orders, the booking calendar and CSV exports.', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'type' => 'note',
							'text' => __( 'Per-seat price overrides are stored per deck. Prices you saved before upgrading continue to apply to the Lower Deck exactly as before; only the Upper Deck needs its prices entering.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-step-3',
					'group' => 'bus',
					'plan'  => 'free',
					'icon'  => 'fas fa-route',
					'title' => __( 'Step 3 — Pricing & Route', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'This is the core step: where the bus goes, at what times, and how much each segment of the journey costs.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-step3-pricing-route.jpg', 'caption' => __( 'Pricing & Route (step 3 of 4)', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Boarding & dropping', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Stop List', 'bus-ticket-booking-with-seat-reservation' ), __( 'The route stops in order, each with its clock time and its role — Boarding, Dropping, or both.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Add New Stops', 'bus-ticket-booking-with-seat-reservation' ), __( 'Adds another station to the route. Stops must exist under Bus → Bus Stops first.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Return journey & passenger types', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Same bus return journey', 'bus-ticket-booking-with-seat-reservation' ), __( 'Lets the same bus appear in return-trip searches, with either a custom schedule or an automatically reversed route.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Passenger Types', 'bus-ticket-booking-with-seat-reservation' ), __( 'Ticket categories such as Adult, Child and Infant, each priced separately on every route segment.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Pricing', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Route Pricing Matrix', 'bus-ticket-booking-with-seat-reservation' ), __( 'A segment-by-segment fare table — Berlin → Frankfurt, Berlin → Hamburg and so on — broken down by passenger type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Full Bus Price', 'bus-ticket-booking-with-seat-reservation' ), __( 'A single charter price for booking the entire vehicle.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Full Bus Discount', 'bus-ticket-booking-with-seat-reservation' ), __( 'An optional reduction applied to the charter price.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seasonal Pricing', 'bus-ticket-booking-with-seat-reservation' ), __( 'Date-range rules that automatically adjust fares for weekends, holidays and peak periods.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( 'Always finish the route stops before setting prices. The pricing matrix is generated from the stops you define, so changing stops afterwards means revisiting the fares.', 'bus-ticket-booking-with-seat-reservation' ),
						),
						array(
							'type' => 'warn',
							'text' => __( 'Stop times must ascend down the route. A later stop with an earlier clock time is treated as a next-day arrival, which can make the bus appear in the following day\'s search results.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'bus-step-4',
					'group' => 'bus',
					'plan'  => 'free',
					'icon'  => 'fas fa-gears',
					'title' => __( 'Step 4 — Advanced', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The final step covers optional extras, precise pickup points, the operating calendar, tax and the passenger registration form.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'bus-step4-advanced.jpg', 'caption' => __( 'Advanced (step 4 of 4)', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Extra Services', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Show/Hide Extra Service', 'bus-ticket-booking-with-seat-reservation' ), __( 'Turns optional paid add-ons on or off for this bus.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Service Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The name shown to the customer, for example "Water" or "Extra Luggage".', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Service Price', 'bus-ticket-booking-with-seat-reservation' ), __( 'What the add-on costs.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Available Qty', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many units can be sold per departure.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Qty Box Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Whether the customer picks a quantity or simply toggles the service on.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Pickup / Drop-Off', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'On/Off Pickup Point', 'bus-ticket-booking-with-seat-reservation' ), __( 'Lets customers choose a precise pickup point instead of just the main boarding stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Drop-Off Point Settings', 'bus-ticket-booking-with-seat-reservation' ), __( 'The same for the destination end of the journey, with its own schedule.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Date settings & operating schedule', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Bus Operation Date Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Choose between Particular (a list of specific dates) and Repeated (a recurring schedule).', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Repeated Start / End Date', 'bus-ticket-booking-with-seat-reservation' ), __( 'The window inside which the recurring schedule runs.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Repeated after step count', 'bus-ticket-booking-with-seat-reservation' ), __( 'The interval between departures — every day, every second day, and so on.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Advanced day for booking', 'bus-ticket-booking-with-seat-reservation' ), __( 'How far ahead customers may book this bus.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Off Day', 'bus-ticket-booking-with-seat-reservation' ), __( 'Weekdays the bus never runs.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Excluded Dates', 'bus-ticket-booking-with-seat-reservation' ), __( 'Individual dates or date ranges to block, for holidays or maintenance.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Tax & registration form', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Tax Configure', 'bus-ticket-booking-with-seat-reservation' ), __( 'Applies your WooCommerce tax rules to this bus. If tax is not enabled in WooCommerce a warning panel links you straight to the right settings screen.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Registration Form', 'bus-ticket-booking-with-seat-reservation' ), __( 'The passenger details collected during booking. Passenger Name and Passenger Phone are active by default; Email, Address, Gender, NID/Passport, Date of Birth and Emergency Contact can be restored with one click, and Add New Field creates your own.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'Particular dates take priority over repeated days, and off days and excluded dates override both.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				/* ------------------------------------------------------------------ *
				 * THE CUSTOMER JOURNEY
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'front-search',
					'group' => 'frontend',
					'plan'  => 'free',
					'icon'  => 'fas fa-magnifying-glass',
					'title' => __( 'Search & Bus Details', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'What the passenger sees first: a search form, then a results list of matching departures.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'front-bus-details.jpg', 'caption' => __( 'The bus details page with its search form', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Search form', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'From / To', 'bus-ticket-booking-with-seat-reservation' ), __( 'Departure station and destination, with a button to reverse the route.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Journey Date', 'bus-ticket-booking-with-seat-reservation' ), __( 'A calendar selector. Sold-out dates are marked and cannot be chosen.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Search', 'bus-ticket-booking-with-seat-reservation' ), __( 'Runs the search and shows matching departures.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'image', 'file' => 'front-search-results.jpg', 'caption' => __( 'Search results', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'The result card', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Timeline & route', 'bus-ticket-booking-with-seat-reservation' ), __( 'Departure and arrival times with the total journey duration.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bus name & availability', 'bus-ticket-booking-with-seat-reservation' ), __( 'The service title and a live badge showing how many seats remain.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Onboard features', 'bus-ticket-booking-with-seat-reservation' ), __( 'The amenities you configured, shown as icons.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Quick toggles', 'bus-ticket-booking-with-seat-reservation' ), __( 'Buttons that reveal bus details, boarding and dropping points, features, or the photo gallery without leaving the page.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Starting fare', 'bus-ticket-booking-with-seat-reservation' ), __( 'The lowest ticket price for the selected route.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Book Seat', 'bus-ticket-booking-with-seat-reservation' ), __( 'Takes the passenger to seat selection.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Which filters appear beside the results — departure time, bus type, operator, boarding point and the sort bar — is controlled under Bus → Settings → Frontend Display.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'front-seats',
					'group' => 'frontend',
					'plan'  => 'free',
					'icon'  => 'fas fa-hand-pointer',
					'title' => __( 'Seat Selection & Booking', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The interactive booking page where passengers choose seats, add extras and enter their details.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'front-seat-selection.jpg', 'caption' => __( 'Seat selection and booking summary', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Seat layout', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Steering wheel indicator', 'bus-ticket-booking-with-seat-reservation' ), __( 'Shows the driver orientation so the map matches the real vehicle.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seating matrix', 'bus-ticket-booking-with-seat-reservation' ), __( 'Click a seat to select it. Selected seats are highlighted.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Status legend', 'bus-ticket-booking-with-seat-reservation' ), __( 'Colour-coded key for Available, Selected and Booked seats.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Extras & passenger details', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Extra Services panel', 'bus-ticket-booking-with-seat-reservation' ), __( 'The optional paid add-ons you configured, each with quantity controls.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Passenger forms', 'bus-ticket-booking-with-seat-reservation' ), __( 'One block per selected seat, labelled with its seat assignment, containing the fields from the registration form.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Booking summary', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Route information', 'bus-ticket-booking-with-seat-reservation' ), __( 'The chosen itinerary.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Itemised fare breakdown', 'bus-ticket-booking-with-seat-reservation' ), __( 'Every seat with its passenger type and individual price.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Totals', 'bus-ticket-booking-with-seat-reservation' ), __( 'Ticket subtotal and the final total price.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Book Now', 'bus-ticket-booking-with-seat-reservation' ), __( 'Confirms the selection and moves to checkout.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'Selected seats are held for a limited time while the customer completes checkout, so two people cannot buy the same seat. The hold duration is set under Bus → Settings → General → Seat Hold Duration.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'front-checkout',
					'group' => 'frontend',
					'plan'  => 'free',
					'icon'  => 'fas fa-credit-card',
					'title' => __( 'Checkout & Order Placement', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The final step of the booking, handled by WooCommerce when it is active.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'front-checkout.jpg', 'caption' => __( 'The checkout page', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Contact & billing', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Contact information', 'bus-ticket-booking-with-seat-reservation' ), __( 'The buyer\'s email address, used for the confirmation and ticket.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Billing address', 'bus-ticket-booking-with-seat-reservation' ), __( 'Country, name, street address, city, district, postal code and phone.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Payment & notes', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Payment options', 'bus-ticket-booking-with-seat-reservation' ), __( 'The gateways you enabled in WooCommerce.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Order notes', 'bus-ticket-booking-with-seat-reservation' ), __( 'An optional message from the customer.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Legal terms notice', 'bus-ticket-booking-with-seat-reservation' ), __( 'The automatic agreement statement linking to your terms and privacy policy.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Place Order', 'bus-ticket-booking-with-seat-reservation' ), __( 'Submits and processes the reservation.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Order summary', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Booking details', 'bus-ticket-booking-with-seat-reservation' ), __( 'Boarding and dropping stations with their dates and times.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Ticket information', 'bus-ticket-booking-with-seat-reservation' ), __( 'Seat type, seat numbers, quantities, prices and the passenger names and phone numbers.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Coupons', 'bus-ticket-booking-with-seat-reservation' ), __( 'A collapsible panel for entering a promotional code.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Totals', 'bus-ticket-booking-with-seat-reservation' ), __( 'Subtotal and the final amount due.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
					),
				);

				/* ------------------------------------------------------------------ *
				 * BOOKINGS & REPORTING
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'booking-list',
					'group' => 'orders',
					'plan'  => 'free',
					'icon'  => 'fas fa-clipboard-list',
					'title' => __( 'Booking List', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The master reservation dashboard: every booked seat across the whole fleet in one filterable table. The old standalone Passenger List was merged into this screen.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'booking-list.jpg', 'caption' => __( 'The Booking List', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Summary cards', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Total Bookings', 'bus-ticket-booking-with-seat-reservation' ), __( 'The number of individual seat reservations.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Revenue', 'bus-ticket-booking-with-seat-reservation' ), __( 'Cumulative income across all bookings.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Buses Booked', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many distinct vehicles have active reservations.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Booking', 'bus-ticket-booking-with-seat-reservation' ), __( 'The order and ID reference, plus which payment engine created it — WooCommerce or a native counter sale.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Customer', 'bus-ticket-booking-with-seat-reservation' ), __( 'Buyer name, email, phone and the passenger details captured on the form.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bus & Route', 'bus-ticket-booking-with-seat-reservation' ), __( 'The vehicle and its itinerary.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Journey Date', 'bus-ticket-booking-with-seat-reservation' ), __( 'Scheduled departure date and time.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Seat / Ticket', 'bus-ticket-booking-with-seat-reservation' ), __( 'The assigned seat number, its deck and cabin where relevant, and the passenger type.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total', 'bus-ticket-booking-with-seat-reservation' ), __( 'The cost of that individual seat.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Status', 'bus-ticket-booking-with-seat-reservation' ), __( 'The live fulfilment state of the reservation.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Booked On', 'bus-ticket-booking-with-seat-reservation' ), __( 'When the reservation was created.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Per-row management options.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Filters & bulk operations', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Filters', 'bus-ticket-booking-with-seat-reservation' ), __( 'An expandable panel to narrow the list by bus, date, passenger details or status.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Bulk Actions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Apply a status change or deletion to many rows at once.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Columns', 'bus-ticket-booking-with-seat-reservation' ), __( 'Choose which columns are visible.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'A "Booked by" badge marks counter and admin-created bookings with the staff member\'s name, so they are easy to tell apart from customers\' own checkouts.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'notifications',
					'group' => 'orders',
					'plan'  => 'free',
					'icon'  => 'fas fa-bell',
					'title' => __( 'Admin Notifications', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Real-time alerts about new orders, reservations and payment status, shown right in the WordPress admin bar.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'admin-notifications.jpg', 'caption' => __( 'The notification centre', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'items' => array(
							array( __( 'Notification badge', 'bus-ticket-booking-with-seat-reservation' ), __( 'An unread count in the top admin bar.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Dropdown centre', 'bus-ticket-booking-with-seat-reservation' ), __( 'Clicking the bell opens a panel listing recent system events and booking updates.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
					),
				);

				$chapters[] = array(
					'id'    => 'status',
					'group' => 'orders',
					'plan'  => 'free',
					'icon'  => 'fas fa-heart-pulse',
					'title' => __( 'System Status', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'A full environment report, found as the Status tab inside Bus → Settings. It is the first place to look when something behaves unexpectedly.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'What the report covers', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'WordPress', 'bus-ticket-booking-with-seat-reservation' ), __( 'Version, site and home URL, multisite state, language, debug mode, memory limit and cron status.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Server', 'bus-ticket-booking-with-seat-reservation' ), __( 'Web server software, operating system, HTTPS state and the server time.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Database', 'bus-ticket-booking-with-seat-reservation' ), __( 'Engine and version, table prefix and character set.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'PHP', 'bus-ticket-booking-with-seat-reservation' ), __( 'Version, memory limit, maximum execution time, upload and post size limits and maximum input variables.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'PHP extensions', 'bus-ticket-booking-with-seat-reservation' ), __( 'Whether the extensions the plugin needs — such as mbstring, GD, cURL and zip — are present.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Plugin & theme', 'bus-ticket-booking-with-seat-reservation' ), __( 'Plugin versions, the active theme, and add-on requirement checks such as the PDF library.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'The "Copy report" button copies the whole report to your clipboard in one click — paste it into a support ticket and we can diagnose most problems immediately.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				/* ------------------------------------------------------------------ *
				 * PRO FEATURES
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'pro-registration-form',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-id-card',
					'title' => __( 'Registration Form Builder', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Customise the passenger information form shown during booking. This is a per-bus tab, so every bus can collect exactly the data that route needs.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Default fields', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Field Label', 'bus-ticket-booking-with-seat-reservation' ), __( 'Rename any default field — change "Phone" to "Mobile Number", for example.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Required', 'bus-ticket-booking-with-seat-reservation' ), __( 'Mark a field mandatory or optional.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Active / Hidden', 'bus-ticket-booking-with-seat-reservation' ), __( 'Show or remove a field from the booking form entirely.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Custom fields', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Field Label', 'bus-ticket-booking-with-seat-reservation' ), __( 'The display name shown to the customer.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Field Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Text input, select dropdown, textarea, checkbox or radio button.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Options', 'bus-ticket-booking-with-seat-reservation' ), __( 'For dropdown, checkbox and radio fields — the choices, separated by commas.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Required', 'bus-ticket-booking-with-seat-reservation' ), __( 'Makes the custom field mandatory.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'Add as many custom fields as you need — National ID, passport number, emergency contact, and so on. The values appear on the booking list, the passenger exports and the PDF ticket.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-deposit',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-wallet',
					'title' => __( 'Deposit / Partial Payment', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Let customers pay a deposit up front and settle the balance later. Configure a global default, then override it per bus where needed.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Global settings (Bus → Settings → Deposit)', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Enable Deposit', 'bus-ticket-booking-with-seat-reservation' ), __( 'Turns the feature on across the site.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Let Customer Choose', 'bus-ticket-booking-with-seat-reservation' ), __( 'Yes shows both "Pay Deposit" and "Pay Full". No forces the deposit with no choice.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Default Deposit Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Percentage or fixed amount, used when a bus does not override it.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Default Deposit Value', 'bus-ticket-booking-with-seat-reservation' ), __( 'For example 30 for 30%, or 50 for a fixed amount.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Balance Due (days after booking)', 'bus-ticket-booking-with-seat-reservation' ), __( 'The deadline for settling the remainder. 0 means no deadline.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'How it works for the customer', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'A "Payment Option" section appears at checkout with the deposit and full-payment choices.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'When a deposit is chosen only that amount is charged; the remainder is recorded as a pending balance.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Customers settle the balance later from My Account → Pending Balances.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Deposit details appear on the PDF ticket and in confirmation emails.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Each bus can override the global values from its own Deposit tab in the bus editor — useful when long-distance routes need a bigger deposit than short hops.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-return-discount',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-arrows-rotate',
					'title' => __( 'Return Discount', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Reward passengers who book a round trip. The section appears inside the Pricing & Route step once PRO is active.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'items' => array(
							array( __( 'Discount Value', 'bus-ticket-booking-with-seat-reservation' ), __( 'The amount to take off the return leg.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Discount Type', 'bus-ticket-booking-with-seat-reservation' ), __( 'Percentage or fixed amount.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'steps', 'items' => array(
							__( 'The customer books the outbound leg (A → B).', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'They then book the return leg (B → A) on the same bus.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'The discount is applied automatically to the return fare in the cart.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( '"Same bus return journey" must be enabled in Pricing & Route for the discount to have anything to apply to.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-bidirectional',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-right-left',
					'title' => __( 'Bidirectional Search & Editable Return', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Two General settings that change how same-bus return routes behave for passengers boarding partway along the route.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'items' => array(
							array( __( 'Bidirectional Stop Search', 'bus-ticket-booking-with-seat-reservation' ), __( 'On a same-bus-return route, a passenger boarding at an intermediate stop can pick a destination in either direction. The "To" list and the fare resolve to the natural same-day leg.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Editable Return Route', 'bus-ticket-booking-with-seat-reservation' ), __( 'Gives the Return tab its own From / To selectors. "From" is locked to the outbound destination and only "To" can be changed; the return bus list reloads by AJAX, including next-day roll-forward.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'The journey badge on tickets, PDFs and the passenger list uses the journey role recorded at booking time, so bidirectional and reverse-direction legs are labelled correctly.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-chatbot',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-robot',
					'title' => __( 'AI Chatbot', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'A conversational widget that helps customers search buses, view seats, book tickets and manage their cart without touching the normal interface.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Providers', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Rule-Based', 'bus-ticket-booking-with-seat-reservation' ), __( 'Built-in keyword matching. Free, works out of the box, needs no API key.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'OpenAI', __( 'GPT-4o Mini, GPT-4o and related models.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'Anthropic Claude', __( 'Haiku, Sonnet and Opus models.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'xAI Grok', __( 'Grok 3 and Grok 3 Mini.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'Alibaba Qwen', __( 'Qwen Plus, Turbo and Max, with a configurable endpoint.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'Google Gemini', __( 'Gemini 2.0 Flash and Pro.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( 'DeepSeek', __( 'DeepSeek Chat and Reasoner.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'What the chatbot can do', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Search buses', 'bus-ticket-booking-with-seat-reservation' ), __( '"Find buses from Berlin to Paris tomorrow"', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Filter results', 'bus-ticket-booking-with-seat-reservation' ), __( '"Show me the cheapest option" or "morning buses only"', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'View seats', 'bus-ticket-booking-with-seat-reservation' ), __( '"Show available seats" — renders an interactive seat map.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Book seats', 'bus-ticket-booking-with-seat-reservation' ), __( '"Book seat A1 and A2" — adds them to the cart.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Manage cart', 'bus-ticket-booking-with-seat-reservation' ), __( '"Show my cart", "Remove seat B3", "Clear cart"', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Apply coupon', 'bus-ticket-booking-with-seat-reservation' ), __( '"Apply coupon SAVE20"', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Checkout', 'bus-ticket-booking-with-seat-reservation' ), __( '"Proceed to payment" — redirects to checkout.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Return journey', 'bus-ticket-booking-with-seat-reservation' ), __( '"I want to come back on Friday"', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Learning engine', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'Logs conversations to surface common questions and failed queries.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Learns popular routes and suggests them proactively.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Improves intent detection over time from real customer wording.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( 'API keys are stored in your site database. Treat them like passwords: use a key restricted to this site where your provider supports it, and rotate it if you suspect it has been exposed. Use "Test Connection" to verify a key before going live.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-pdf',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-file-pdf',
					'title' => __( 'PDF & Thermal Tickets', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Generate branded PDF tickets for customers, and compact thermal tickets for counter printers.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Ticket appearance', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Merge PDF Ticket', 'bus-ticket-booking-with-seat-reservation' ), __( 'Yes puts every seat in an order on one ticket. No generates one ticket per seat.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Thermal (POS) Ticket', 'bus-ticket-booking-with-seat-reservation' ), __( 'Produces a narrow receipt-style ticket for thermal printers, with a selectable width.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Ticket Download File Name', 'bus-ticket-booking-with-seat-reservation' ), __( 'The filename customers receive.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Logo & Background', 'bus-ticket-booking-with-seat-reservation' ), __( 'A custom header logo and a background image (about 680px wide) or solid colour.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Text Color', 'bus-ticket-booking-with-seat-reservation' ), __( 'The colour used for ticket text.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Company address, phone, e-mail', 'bus-ticket-booking-with-seat-reservation' ), __( 'Your contact details, printed on the ticket.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Terms & Condition', 'bus-ticket-booking-with-seat-reservation' ), __( 'A title and rich-text block shown in the ticket footer.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( 'PDF generation needs the MagePeople PDF Support plugin installed and active. Bus → Settings → Status tells you whether it is present.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-exports',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-file-export',
					'title' => __( 'Passenger Exports (PDF & CSV)', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Export the filtered passenger list as a PDF manifest or a CSV spreadsheet. Both exports respect whatever filters and sorting are currently applied.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Filtering before export', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'By bus', 'bus-ticket-booking-with-seat-reservation' ), __( 'Restrict to a single vehicle, or leave blank to search the whole fleet.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'By date', 'bus-ticket-booking-with-seat-reservation' ), __( 'Filter by journey date, order date or boarding date.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'By boarding / dropping point', 'bus-ticket-booking-with-seat-reservation' ), __( 'Narrow to passengers using a particular pickup or drop-off location.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'By passenger details', 'bus-ticket-booking-with-seat-reservation' ), __( 'Search name, email, phone, address and any custom fields.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'By order status', 'bus-ticket-booking-with-seat-reservation' ), __( 'Restrict to a WooCommerce order status.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Sort by Seat Number', 'bus-ticket-booking-with-seat-reservation' ), __( 'A checkbox that reorders the list alphanumerically (A1, A2, B1…). Unchecking it restores the original order, and exports follow whichever is active.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'The columns in each export are chosen under Bus → Settings → Export Columns. PDF and CSV keep separate column lists because the two generators read them independently.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-emails',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-envelope',
					'title' => __( 'Emails & Reminders', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Automatic notifications with the PDF ticket attached, low-seat alerts for you, and trip reminders for passengers.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Ticket email', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Send Ticket?', 'bus-ticket-booking-with-seat-reservation' ), __( 'Turns automatic sending on or off.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Send Email on', 'bus-ticket-booking-with-seat-reservation' ), __( 'Which order statuses trigger the mail — On Hold, Pending, Processing, Completed.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Email Subject & Content', 'bus-ticket-booking-with-seat-reservation' ), __( 'The subject line and a rich-text body.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'From Name / From Email', 'bus-ticket-booking-with-seat-reservation' ), __( 'The sender identity. Defaults to your WooCommerce email settings.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Alerts & reminders', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Admin Notification Email', 'bus-ticket-booking-with-seat-reservation' ), __( 'Where admin copies and threshold alerts are sent.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Minimum Seat Threshold', 'bus-ticket-booking-with-seat-reservation' ), __( 'When free seats drop to this number an alert is emailed to you.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Trip Reminder Email', 'bus-ticket-booking-with-seat-reservation' ), __( 'Sends passengers a reminder a set number of hours before departure, with its own subject and content.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'table',
							'head' => array( __( 'Placeholder', 'bus-ticket-booking-with-seat-reservation' ), __( 'Replaced with', 'bus-ticket-booking-with-seat-reservation' ) ),
							'rows' => array(
								array( '{customer_name}', __( 'The buyer\'s name', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '{bus_name}', __( 'The bus title', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '{journey_date}', __( 'The departure date', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '{order_id}', __( 'The order reference', 'bus-ticket-booking-with-seat-reservation' ) ),
							),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-calendar',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-calendar-days',
					'title' => __( 'Booking Calendar', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'A visual calendar of every booking, with month, week and day views and a detail panel for the selected day.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'booking-calendar.jpg', 'caption' => __( 'The booking calendar', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Summary cards', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Total Bookings', 'bus-ticket-booking-with-seat-reservation' ), __( 'All journey bookings in the visible period.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Confirmed', 'bus-ticket-booking-with-seat-reservation' ), __( 'Bookings that have been confirmed.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Pending', 'bus-ticket-booking-with-seat-reservation' ), __( 'Reservations awaiting confirmation.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Est. Revenue', 'bus-ticket-booking-with-seat-reservation' ), __( 'Estimated revenue from confirmed bookings only.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Toolbar', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Date navigation', 'bus-ticket-booking-with-seat-reservation' ), __( 'Today plus arrow controls to move through time.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Filters', 'bus-ticket-booking-with-seat-reservation' ), __( 'Narrow to a specific bus or booking status.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'View mode', 'bus-ticket-booking-with-seat-reservation' ), __( 'Switch between Month, Week and Day.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Journey Date / Order Date', 'bus-ticket-booking-with-seat-reservation' ), __( 'Plot bookings by when the bus departs, or by when the order was placed.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Grid & side panel', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Calendar grid', 'bus-ticket-booking-with-seat-reservation' ), __( 'Each day lists its bookings with time, passenger, route and seat.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Selected day panel', 'bus-ticket-booking-with-seat-reservation' ), __( 'Tabs for ALL, CONFIRMED, PENDING and PARTIAL. Each card shows time, status, passenger, bus, route, cabin, seat and fare.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Order detail modal', 'bus-ticket-booking-with-seat-reservation' ), __( 'Clicking a card opens the full order details.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'For double-decker vehicles the seat number is shown with a small "Upper Deck" tag and the cabin name on its own row, so a lower-deck A1 is never confused with an upper-deck A1.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-sales-report',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-chart-column',
					'title' => __( 'Sales Report', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Financial and operational analytics: ticket revenue, passenger volume, extra-service income and net totals, broken down by vehicle.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'image', 'file' => 'sales-report.jpg', 'caption' => __( 'The sales report', 'bus-ticket-booking-with-seat-reservation' ) ),
						array( 'type' => 'list', 'title' => __( 'Filters', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Select Bus', 'bus-ticket-booking-with-seat-reservation' ), __( 'Report on one vehicle at a time.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Boarding Point Date', 'bus-ticket-booking-with-seat-reservation' ), __( 'Target a specific boarding date.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Boarding / Dropping', 'bus-ticket-booking-with-seat-reservation' ), __( 'Report on a particular route segment.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Journey Date From / To', 'bus-ticket-booking-with-seat-reservation' ), __( 'Filter by when passengers travel.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Order Date From / To', 'bus-ticket-booking-with-seat-reservation' ), __( 'Filter by when the transaction was processed.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Search / Reset / Filter Results', 'bus-ticket-booking-with-seat-reservation' ), __( 'Free-text search, clear all filters, and run the query.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Summary cards', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'Ticket Sales', 'bus-ticket-booking-with-seat-reservation' ), __( 'Revenue from fares alone.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Passengers', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many travellers are registered.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Orders', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many transactions were processed.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Buses', 'bus-ticket-booking-with-seat-reservation' ), __( 'How many vehicles contributed to the report.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Extra Services', 'bus-ticket-booking-with-seat-reservation' ), __( 'Income from onboard add-ons.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Net Revenue', 'bus-ticket-booking-with-seat-reservation' ), __( 'Combined income after sales and extras.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Data table', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							array( __( 'SI / Bus', 'bus-ticket-booking-with-seat-reservation' ), __( 'Index and the bus profile with its type and number.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Total Passenger / Total Order', 'bus-ticket-booking-with-seat-reservation' ), __( 'Volumes per vehicle.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Ticket Sales, Extra Services, Fees, Discounts', 'bus-ticket-booking-with-seat-reservation' ), __( 'The financial breakdown for the row.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Net Total', 'bus-ticket-booking-with-seat-reservation' ), __( 'Final calculated revenue for the entry.', 'bus-ticket-booking-with-seat-reservation' ) ),
							array( __( 'Action', 'bus-ticket-booking-with-seat-reservation' ), __( 'Quick PDF and CSV export icons.', 'bus-ticket-booking-with-seat-reservation' ) ),
						) ),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-counter-sale',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-cash-register',
					'title' => __( 'Purchase Ticket (Counter Sales)', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Create bookings on behalf of customers straight from the dashboard — for walk-ins, phone bookings, or when someone needs help ordering.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'steps', 'items' => array(
							__( 'Search for buses by route and date, exactly as on the frontend.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Pick seats from the seat map, or enter a quantity for buses without a seat plan.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Fill in the passenger details using the registration form.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Create the order — it is marked Completed automatically, with no payment gateway involved.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'note',
							'text' => __( 'Full-bus (charter) booking is available here too, with its own pricing, and works in both WooCommerce and standalone payment modes.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-view-ticket',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-qrcode',
					'title' => __( 'View Ticket (Frontend)', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'A public page where passengers look up their ticket with a PIN. The page is created automatically when PRO is activated.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'steps', 'items' => array(
							__( 'The passenger enters the ticket PIN they received by email.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'The booking is looked up and the ticket details are displayed.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'For multi-seat bookings every seat is listed.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'A PDF download button is offered for each ticket.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Access control', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'Logged-in customers can view tickets that match their account or email address.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Administrators and Bus Staff can view any ticket.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'tip',
							'text' => __( 'Place the [view-ticket] shortcode on any page to add the lookup form somewhere else on your site.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'pro-staff-role',
					'group' => 'pro',
					'plan'  => 'pro',
					'icon'  => 'fas fa-user-shield',
					'title' => __( 'Bus Staff Role', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'A dedicated WordPress role for counter and operations staff, with access to bus screens only.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'What the role can do', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'Created automatically when the PRO addon is activated.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Has access to the bus list, booking list, counter sales, sales report and booking calendar.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Cannot reach pages, posts, plugins, themes, settings or WooCommerce configuration.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Can look up tickets on the frontend view-ticket page.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'steps', 'items' => array(
							__( 'Go to Users → Add New, or edit an existing user.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Set their role to "Bus Staff".', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'They will now see only the bus-related menu items when they log in.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
					),
				);

				/* ------------------------------------------------------------------ *
				 * REFERENCE & HELP
				 * ------------------------------------------------------------------ */

				$chapters[] = array(
					'id'    => 'shortcodes',
					'group' => 'help',
					'plan'  => 'free',
					'icon'  => 'fas fa-code',
					'title' => __( 'Shortcodes', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Drop these into any page, post or widget to place booking features wherever you need them.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array(
							'type' => 'table',
							'head' => array( __( 'Shortcode', 'bus-ticket-booking-with-seat-reservation' ), __( 'What it does', 'bus-ticket-booking-with-seat-reservation' ), __( 'Plan', 'bus-ticket-booking-with-seat-reservation' ) ),
							'rows' => array(
								array( '<code>[wbtm-bus-list]</code>', __( 'A grid or list of your buses. See the attribute table below for the full set of options.', 'bus-ticket-booking-with-seat-reservation' ), __( 'Free', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>[wbtm-bus-search-form]</code>', __( 'The search form on its own, for placing anywhere on the site.', 'bus-ticket-booking-with-seat-reservation' ), __( 'Free', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>[wbtm-bus-search]</code>', __( 'The search form together with its results.', 'bus-ticket-booking-with-seat-reservation' ), __( 'Free', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>[view-ticket]</code>', __( 'The PIN-based ticket lookup form for passengers. The page holding it is created automatically when PRO is activated.', 'bus-ticket-booking-with-seat-reservation' ), 'PRO' ),
								array( '<code>[wbtm_download_pdf order_id=""]</code>', __( 'A PDF ticket download button for a given order ID.', 'bus-ticket-booking-with-seat-reservation' ), 'PRO' ),
								array( '<code>[wbtm-standalone-checkout]</code>', __( 'The plugin\'s own checkout page, used in standalone (non-WooCommerce) payment mode.', 'bus-ticket-booking-with-seat-reservation' ), 'PRO' ),
								array( '<code>[wbtm-booking-confirmation]</code>', __( 'The thank-you / booking confirmation page for standalone payment mode.', 'bus-ticket-booking-with-seat-reservation' ), 'PRO' ),
							),
						),
						array( 'type' => 'text', 'text' => __( 'Attributes accepted by [wbtm-bus-list]:', 'bus-ticket-booking-with-seat-reservation' ) ),
						array(
							'type' => 'table',
							'head' => array( __( 'Attribute', 'bus-ticket-booking-with-seat-reservation' ), __( 'Default', 'bus-ticket-booking-with-seat-reservation' ), __( 'What it does', 'bus-ticket-booking-with-seat-reservation' ) ),
							'rows' => array(
								array( '<code>cat</code>', __( 'empty', 'bus-ticket-booking-with-seat-reservation' ), __( 'Limit the list to one bus type. Leave empty to include every type.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>show</code>', '9', __( 'How many buses are visible before pagination kicks in.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>column</code>', '3', __( 'How many columns the grid uses.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>style</code>', 'grid', __( 'Layout style for the listing.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>pagination</code>', 'yes', __( 'Turn pagination on or off. Use "no" to render every bus at once.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>pagination-style</code>', 'load_more', __( 'How additional buses are revealed.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>sort</code>', 'ASC', __( 'Sort direction — ASC or DESC.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>sort_by</code>', __( 'empty', 'bus-ticket-booking-with-seat-reservation' ), __( 'Which field to sort on. Empty uses the default order.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>start</code>', __( 'empty', 'bus-ticket-booking-with-seat-reservation' ), __( 'Only include buses departing from this stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
								array( '<code>end</code>', __( 'empty', 'bus-ticket-booking-with-seat-reservation' ), __( 'Only include buses arriving at this stop.', 'bus-ticket-booking-with-seat-reservation' ) ),
							),
						),
						array(
							'type' => 'note',
							'text' => __( 'Some older guides mention a [wbtm-bus-details] shortcode. It is not part of the plugin — link to the bus\'s own page instead, or use [wbtm-bus-list] with start and end to narrow the listing.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'videos',
					'group' => 'help',
					'plan'  => 'free',
					'icon'  => 'fas fa-circle-play',
					'title' => __( 'Video Tutorials', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Short walkthroughs covering the tasks people ask about most often.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'videos', 'items' => array(
							array( 'title' => __( 'General booking guide', 'bus-ticket-booking-with-seat-reservation' ), 'id' => 'fK1-JCuI9rY', 'plan' => 'free' ),
							array( 'title' => __( 'Setting ticket prices', 'bus-ticket-booking-with-seat-reservation' ), 'id' => '5XNiRwl9VAM', 'plan' => 'free' ),
							array( 'title' => __( 'Bus booking on a specific day', 'bus-ticket-booking-with-seat-reservation' ), 'id' => 'z18HXrPf0-Q', 'plan' => 'free' ),
							array( 'title' => __( 'Booking buffer time', 'bus-ticket-booking-with-seat-reservation' ), 'id' => '7McbXsaPHEg', 'plan' => 'free' ),
							array( 'title' => __( '2-door & 4-column seat plan', 'bus-ticket-booking-with-seat-reservation' ), 'id' => 'Mh_2UUKo8Nk', 'plan' => 'free' ),
							array( 'title' => __( '3-column seat plan', 'bus-ticket-booking-with-seat-reservation' ), 'id' => '2yEfMio10-I', 'plan' => 'free' ),
							array( 'title' => __( 'PDF ticket configuration', 'bus-ticket-booking-with-seat-reservation' ), 'id' => '8F_Jw2_alGw', 'plan' => 'pro' ),
							array( 'title' => __( 'Email notifications', 'bus-ticket-booking-with-seat-reservation' ), 'id' => 'hbc0kYd8zA8', 'plan' => 'pro' ),
							array( 'title' => __( 'Get a ticket from the admin', 'bus-ticket-booking-with-seat-reservation' ), 'id' => 'TmB_FEbQagk', 'plan' => 'pro' ),
							array( 'title' => __( 'Export the passenger list (CSV)', 'bus-ticket-booking-with-seat-reservation' ), 'id' => '9ODsKeFwMpY', 'plan' => 'pro' ),
						) ),
					),
				);

				$chapters[] = array(
					'id'    => 'demo-import',
					'group' => 'help',
					'plan'  => 'free',
					'icon'  => 'fas fa-box-open',
					'title' => __( 'Importing Demo Content', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'Sample buses, stops and routes let you see how everything fits together before building your own fleet.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array( 'type' => 'list', 'title' => __( 'Automatic import', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'On a brand-new site with no buses yet, the sample content is imported for you the first time you open a bus admin screen.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'It runs in the background, one bus per request, so it will not exhaust memory or stall the page on a small server.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'A small progress toast shows how far along it is, and an interrupted import resumes where it left off.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'It only ever runs once, and never on a site that already has buses — so your own data is never touched.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'list', 'title' => __( 'Manual import', 'bus-ticket-booking-with-seat-reservation' ), 'items' => array(
							__( 'For the full demo site — including pages and media — a WordPress export file is available separately.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array( 'type' => 'steps', 'items' => array(
							__( 'Download the demo archive from bus.mage-people.com/bus-dummy-content.zip and unzip it.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Go to Tools → Import and choose the WordPress importer, installing it first if prompted.', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Upload the XML file, assign the posts to a user, and tick "Download and import file attachments".', 'bus-ticket-booking-with-seat-reservation' ),
							__( 'Click Submit and wait for the import to finish.', 'bus-ticket-booking-with-seat-reservation' ),
						) ),
						array(
							'type' => 'warn',
							'text' => __( 'Run the manual import on a test site or a brand-new install. On a live site it adds buses and pages you will then have to clean up by hand.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				$chapters[] = array(
					'id'    => 'troubleshooting',
					'group' => 'help',
					'plan'  => 'free',
					'icon'  => 'fas fa-screwdriver-wrench',
					'title' => __( 'Troubleshooting', 'bus-ticket-booking-with-seat-reservation' ),
					'intro' => __( 'The problems that come up most often, and what usually fixes them.', 'bus-ticket-booking-with-seat-reservation' ),
					'blocks' => array(
						array(
							'type' => 'table',
							'head' => array( __( 'Symptom', 'bus-ticket-booking-with-seat-reservation' ), __( 'Usual cause and fix', 'bus-ticket-booking-with-seat-reservation' ) ),
							'rows' => array(
								array(
									__( 'Buses do not appear in search results', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'Check the operating schedule in step 4 — off days, excluded dates and the advance-booking limit all hide departures. Also confirm the route has a price for the selected segment.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'A bus appears on the wrong day', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'Route stop times must ascend down the route. A later stop with an earlier time is read as a next-day arrival.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( '"Cart error" when clicking Book Now', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'The bus has lost its link to its hidden WooCommerce product, which happens on imported or migrated sites. Re-saving the bus repairs the link automatically.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'A booking cannot be completed', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'No payment method is available. Check Bus → Settings → Payments and make sure a gateway is enabled for the active booking mode.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'PDF tickets are not generated', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'The MagePeople PDF Support plugin is missing or inactive. Bus → Settings → Status confirms whether it is present.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'Permalinks return 404 after changing the slug', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'Go to Settings → Permalinks and click Save Changes to flush the rewrite rules.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'The seat plan or editor looks out of date', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'A cached asset is being served. Hard-refresh the page; assets are versioned by file change time, so a reload picks up the new files.', 'bus-ticket-booking-with-seat-reservation' ),
								),
								array(
									__( 'Text still shows in English after translating', 'bus-ticket-booking-with-seat-reservation' ),
									__( 'The compiled language files are stale. Regenerate the .mo and .l10n.php files for your locale, or clear the translation cache.', 'bus-ticket-booking-with-seat-reservation' ),
								),
							),
						),
						array(
							'type' => 'tip',
							'text' => __( 'Before opening a support ticket, copy the report from Bus → Settings → Status and include it. It answers most of the questions we would otherwise have to ask.', 'bus-ticket-booking-with-seat-reservation' ),
						),
					),
				);

				/**
				 * Filter the documentation chapters.
				 *
				 * Addons can append their own chapters. Each chapter must supply at
				 * least 'id', 'group', 'title' and 'blocks'.
				 *
				 * @param array $chapters
				 */
				return apply_filters( 'wbtm_docs_chapters', $chapters );
			}
		}
	}
