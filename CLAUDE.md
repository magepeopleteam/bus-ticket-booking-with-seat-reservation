# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"Bus Ticket Booking with Seat Reservation" (WpBusTicketly) — a WordPress plugin, prefix `WBTM_` / `wbtm_`, that turns WooCommerce into a bus/train ticketing system with an interactive seat map. Requires PHP 8.0+ and WordPress. A separate paid add-on plugin (`addon-bus--ticket-booking-with-seat-pro`) is detected via `WBTM_Functions::is_pro_active()` and gates premium features throughout the codebase — check for that guard before assuming a feature is available.

**WooCommerce is optional (auto-detected), in progress.** The core plugin (CPT registration, admin settings, activation page creation, frontend search, seat plan display, coupon CPT/editing) always loads and no longer requires WooCommerce to boot — mirroring the same migration already done in the sibling plugin `ecab-taxi-booking-manager`. Mode check: `WBTM_Functions::is_wc_active()` (thin wrapper over `WBTM_Global_Function::check_woocommerce() === 1`); use this helper, not raw `check_woocommerce()` comparisons, for any new WC-conditional code. WC-specific integration is loaded/executed only when WC is active: `inc/WBTM_Woocommerce.php` (cart/checkout/order hooks), `admin/WBTM_Hidden_Product.php` (mirrors each bus to a hidden WC product), `inc/coupon/WBTM_Coupon_Cart.php` (coupon↔cart bridge), and `inc/class-functions.php` (My Account endpoint) are now conditionally required from their loaders rather than unconditionally. Price display goes through `WBTM_Global_Function::format_price()` (falls back to `number_format()` without WC) instead of bare WooCommerce `wc_price()`; the deeper tax-aware `WBTM_Global_Function::wc_price()`/`price_convert_raw()` methods have an early WC-inactive fallback before touching `WC_Tax`/`wc_get_product()`/`WC()`. **Still TODO (deferred by design, matching the ecab precedent):** actually completing a booking — add-to-cart, checkout, coupon application at checkout, the My Account booking dashboard's live order data — still requires WooCommerce; no standalone booking/payment flow exists yet. When adding new code that touches WooCommerce, guard it with `WBTM_Functions::is_wc_active()` / `function_exists()`, or route price output through `WBTM_Global_Function::format_price()`, rather than calling WC functions/classes directly. (The plugin still shows the WooCommerce installer prompt when WC is missing or "ghost-active" — see `inc/WBTM_Woo_Installer.php` — but that's now a suggestion, not a hard gate.)

There is no build system, package manager, or test suite in this repo (no composer.json, no phpunit, no webpack/grunt). The only script in `package.json` is `npm run frontend`, which runs `sass --watch assets/frontend/wbtm.scss assets/frontend/wbtm.css` — the one asset that isn't hand-edited CSS. Everything else (admin CSS/JS, global mp_style assets) is edited directly, no compilation step.

There IS a runnable local WordPress install one directory level up, at `/home/luna/Lerd/mage/bus-seat-plan` (this plugin lives at `wp-content/plugins/bus-ticket-booking-with-seat-reservation` inside it), with a working DB connection and WP-CLI (`wp`, aliased to `wp --allow-root`). Use it to actually reproduce bugs instead of only static-reading code: `wp plugin list --status=active` to check WooCommerce's state, `wp post list --post_type=wbtm_bus` for real bus posts to test against, and `wp eval '<inline php>'` to render an admin metabox/page/shortcode directly and catch any `\Throwable` — e.g. `do_action('add_meta_boxes', 'wbtm_bus', $post)` then invoke each registered callback via `$wp_meta_boxes`. **`wp eval-file` is broken/silent in this sandbox** (produces zero output even for trivial scripts) — always use `wp eval` with the code passed inline instead.

## Bootstrap / load order

1. `woocommerce-bus.php` — plugin entry point. Defines `WBTM_PLUGIN_DIR`, `WBTM_PLUGIN_URL`, `WBTM_VERSION`. Loads `mp_global/WBTM_Global_File_Load.php` unconditionally, loads `inc/WBTM_Woo_Installer.php` in admin regardless of WooCommerce state, and **always** instantiates the main `Wbtm_Woocommerce_bus` class (which loads everything else via `inc/WBTM_Dependencies.php`) regardless of WooCommerce state — the class itself has no WC dependency; only the WC-specific sub-loads it triggers are conditional (see next point).
2. `mp_global/WBTM_Global_File_Load.php` — loads shared/vendored MagePeople framework code: `WBTM_Global_Function` (settings getters, WC detection), `WBTM_Setting_API` (generic Settings-API wrapper), `WBTM_Custom_Layout`, `WBTM_Custom_Slider`, `WBTM_Select_Icon_image`, `WBTM_Global_Style`. Also owns the conditional admin/frontend asset enqueue gating (see below).
3. `inc/WBTM_Dependencies.php` — the real plugin loader. Requires all of `inc/*` and `admin/WBTM_Admin.php` unconditionally, instantiates `WBTM_Coupon_Module` (which itself only loads `WBTM_Coupon_Cart` when WC is active), registers the `single_template`/`template_include` filters that route to `templates/single_page/*`, and does i18n/upgrade housekeeping. `inc/WBTM_Woocommerce.php` and `inc/class-functions.php` are only required here when `WBTM_Functions::is_wc_active()` is true; `admin/WBTM_Admin.php` applies the same conditional treatment to `admin/WBTM_Hidden_Product.php`.

Asset loading is **conditionally gated**, not global: `should_load_admin_assets()` and `should_load_frontend_assets()` in `mp_global/WBTM_Global_File_Load.php` check the current screen/post-type/shortcode presence before enqueuing the heavy jQuery UI / Select2 / Owl Carousel / FontAwesome / CodeMirror bundle, to avoid loading it site-wide. Both have filter escape hatches (`wbtm_load_admin_assets`, `wbtm_load_frontend_assets`) for page builders / custom layouts that need to force-load it.

## Core domain model

- **`wbtm_bus`** CPT (`admin/WBTM_CPT.php`) — a bus/route/train "product". Public, REST-enabled, custom capability type. Carries route stops, pickup/drop-off points, seat/cabin configuration, and pricing as post meta. Taxonomies (`admin/WBTM_Taxonomy.php`): `wbtm_bus_cat`, `wbtm_bus_stops`, `wbtm_bus_pickpoint`, `wbtm_bus_drop_off`, `wbtm_bus_feature`.
- **`wbtm_bus_booking`** CPT — non-public, non-queryable, created programmatically (not by hand) per booked ticket/seat; the record backing availability tracking and the customer's booking dashboard. Booking pages are deliberately deindexed (`inc/WBTM_Dependencies.php` adds noindex meta tags + robots.txt rules for this CPT — a privacy measure, don't remove).
- **Coupon CPT** (`inc/coupon/WBTM_Coupon_CPT.php`) — per-bus discount coupons, distinct from native WooCommerce coupons.

Route segments with zero fare are intentionally non-bookable (see `e061938`) — pricing of `0`/unset on a route segment is a "not for sale" signal, not a free-ticket signal.

## Seat reservation flow

Seat/cabin layout is configured per-bus in the admin (`admin/settings/WBTM_Seat_Configuration.php` defines the toolbar of placeable items — seats, door, driver, toilet, etc. — plus reusable seat *templates* with column patterns and numbering schemes) and stored as post meta (`wbtm_seat_cols`, `wbtm_seat_rows`, `wbtm_bus_seats_info`, `wbtm_cabin_config`, `wbtm_cabin_seats_info_{index}`). The drag-and-drop toolbar itself is a free-plugin feature; **per-seat price override** is Pro-gated (`WBTM_Functions::is_pro_active()`), while the toolbar/template UI is gated separately by `WBTM_Seat_Configuration::has_seat_toolbar_features()` — the two flags are independent, don't conflate them.

On the frontend, `templates/layout/seat_plan.php` renders the seat grid/cabin view by reading bus meta and cross-referencing live availability via `WBTM_Query::query_seat_booked()` and `WBTM_Functions::check_seat_in_cart()`, producing `.mp_seat` cells (`seat_available` / `seat_booked` / `seat_in_cart`) carrying `data-seat_name` / `data-seat_price` / `data-seat_type`. Selecting seats populates hidden inputs (`wbtm_selected_seat`, `wbtm_selected_seat_cabin_N`) that get posted to the AJAX add-to-cart handler.

## Booking flow (search → cart → order)

1. `[wbtm-bus-search-form]` / `[wbtm-bus-search]` shortcodes (`inc/WBTM_Shortcodes.php`) render `templates/layout/search_form.php`; results come back via AJAX into `templates/layout/search_result.php` (or `search_result_flix.php` for the alternate "flix" style — see the `style="flix"` shortcode attribute).
2. Selecting a result loads bus details via AJAX (`inc/WBTM_Single_Bus_Details.php`, `wp_ajax_wbtm_load_bus_details`), assembling tabs (seat plan, pricing, pickup/drop-off, extra services) through `WBTM_Functions::single_bus_details_tabs*`.
3. Submitting the booking form hits `WBTM_Booking_Controller::wbtm_ajax_add_to_cart()` (`inc/WBTM_Booking_Controller.php`, always loaded regardless of WooCommerce state — it's the entry point for both flows). It checks `WBTM_Functions::booking_mode()`: in `standalone` mode it fires `wbtm_standalone_add_booking` (handled by the Pro plugin's `WBTM_Standalone_Payment`, entirely independent of WooCommerce); in `woocommerce` mode it falls through to `WC()->cart->add_to_cart()`, which wires seat/route selection into WooCommerce via `woocommerce_add_cart_item_data`, `woocommerce_before_calculate_totals` (pricing + duplicate-booking prevention), and `woocommerce_get_item_data`/`cart_item_thumbnail` for cart display (all still in `inc/WBTM_Woocommerce.php`, only loaded when WooCommerce is active). Availability/lock/cart-parsing logic used by both flows lives in the always-loaded `inc/WBTM_Cart_Helper.php`; `WBTM_Woocommerce` keeps the same method names as thin delegating wrappers so its own WC-flow callers are unaffected.
4. At checkout: `woocommerce_after_checkout_validation`, `woocommerce_checkout_create_order_line_item` (persists seat/route data onto the order line item), `woocommerce_checkout_order_processed` / `woocommerce_store_api_checkout_order_processed` (block-checkout support), then post-purchase `woocommerce_thankyou` / `order_status_changed` / `payment_complete` hooks create/update the corresponding `wbtm_bus_booking` record(s).

Templates are resolved via `WBTM_Functions::get_template()` (`inc/WBTM_Functions.php`), which checks the active theme first via `locate_template()` before falling back to the plugin's own `templates/` directory — so a site's theme can override any template by copying it into `theme/templates/...`.

## Coupon engine (`inc/coupon/`)

A self-contained module loaded once from `WBTM_Coupon_Module` (holds shared constants `CPT`, `META`, `ORDER_RECORDED`):
- `WBTM_Coupon_CPT.php` — CPT registration + admin list columns.
- `WBTM_Coupon_Meta.php` — metabox editor: code, discount type/amount, bus/category applicability, date and travel-date windows, day-of-week restriction, usage limits (total/per-user/per-day), role/email restriction, stacking rules.
- `WBTM_Coupon_Engine.php` — pure validation/calculation logic (`evaluate()`), no WC/cart coupling.
- `WBTM_Coupon_Cart.php` — bridges the engine into WooCommerce cart totals and records usage counters (`used_count`, `user_log`, `day_log`) back onto the coupon post via `sync_order_discount_shares`.

All coupon configuration is stored as **one serialized meta array per post**, not individual meta keys — read/write through the module's accessors rather than `get_post_meta()` directly with a guessed key.

## Admin settings: classic vs "modern" UI

`admin/settings/*` are the original settings section classes (one per concern: general, pricing/routing, seat configuration, taxes, pickup points, gallery, terms, translations, etc.), each with its own render + save logic wired through `WBTM_Settings` (`admin/WBTM_Settings.php`). `admin/settings-modern/WBTM_Settings_Modern.php` is **not a rewrite** — it's an alternate UI shell (a 4-step wizard: General/Seat/Pricing/Advanced) that reflection-instantiates and reuses the exact same classic section classes for rendering and the same shared save handler (`WBTM_Settings::save_settings`), so field names/JS hooks/AJAX endpoints are shared and must stay in sync across both. Which UI a user sees is a per-user choice (user meta `wbtm_bus_edit_ui`, default `modern`), not a global site setting — both code paths are live simultaneously and both self-instantiate + hook `add_meta_boxes`. When changing a settings field, changes to the classic section class automatically apply to both UIs; don't duplicate logic into the modern class.

## Key files for orientation

- `inc/WBTM_Functions.php` — large grab-bag of static helper functions (formatting, pro-gating, template tab assembly); the closest thing to a domain utility layer.
- `inc/WBTM_Query.php` — DB/meta query helpers (availability, booked seats).
- `mp_global/class/WBTM_Global_Function.php` — cross-cutting helpers shared with the mp_global framework layer (settings getters, `check_woocommerce()`).
- `admin/WBTM_CPT.php` / `admin/WBTM_Taxonomy.php` — CPT/taxonomy registration.
- `inc/WBTM_Woocommerce.php` — all WooCommerce cart/checkout/order integration hooks.
