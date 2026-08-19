<?php
/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$search_info = $search_info ?? [];
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$start_route = $start_route ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$end_route = $end_route ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$post_id = $post_id ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$date = $date ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$journey_type = $journey_type ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$btn_show = $btn_show ?? '';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$left_filter_show = $left_filter_show ?? '';

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$label = WBTM_Functions::get_name();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$bus_ids = $post_id > 0 ? [$post_id] : WBTM_Query::get_bus_id($start_route, $end_route);
//echo '<pre>';	print_r($search_info);	echo '</pre>';
if (sizeof($bus_ids) > 0) {
   
    $bus_count = 0;

    // Collect all bus info first
   
    $bus_data = [];
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $bus_titles = [];
   
    $bus_types = [];
   
    $all_boarding_routes = [];
   
    $wbtm_price_leg = ( $journey_type === 'return_journey' ) ? 'return' : 'outbound';

    // Unpriced-route handling (Global Settings -> Frontend Display -> Unpriced Routes).
    // A segment with no fare configured returns price === false from get_bus_all_info().
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_unpriced_action = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'unpriced_route_action', 'message');
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_unpriced_msg = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'unpriced_route_message', __('This route is not currently available for booking.', 'bus-ticket-booking-with-seat-reservation'));

    foreach ($bus_ids as $bus_id) {
        $wbtm_price_leg = WBTM_Functions::resolve_price_leg_for_od_pair(
            $bus_id,
            $start_route,
            $end_route,
            ( $journey_type === 'return_journey' ) ? 'return' : 'outbound'
        );

        $all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route, $wbtm_price_leg);
        if (sizeof($all_info) > 0) {

            // No fare configured for this exact origin -> destination segment.
            $route_priced = ($all_info['price'] !== false);
            if (!$route_priced && $wbtm_unpriced_action === 'hide') {
                // Hidden entirely: not listed, not filtered, not counted.
                continue;
            }

            $bus_data[] = [
                'bus_id'   => $bus_id,
                'all_info' => $all_info,
                'price_leg' => $wbtm_price_leg,
                'route_priced' => $route_priced,
            ];
           
            $bus_titles[] = get_the_title($bus_id);
            
           
            $bus_type = WBTM_Functions::synchronize_bus_type($bus_id);
           
            $bus_types[] = $bus_type;
            
           
            $get_boarding_routes = WBTM_Functions::get_bus_route( $bus_id );
           
            foreach ( $get_boarding_routes as $route ){
                if( !empty( $route ) ){
                   
                    $all_boarding_routes[] = $route;
                }
            }
        }

    }

   
    $all_boarding_routes = array_unique( $all_boarding_routes );

    if( $journey_type === 'start_journey' ){
       
        $wbtm_bus_search = 'wbtm_bus_search_journey_start';
       
        $filter_by_box = 'filter-checkbox';
    }else{
       
        $wbtm_bus_search = 'wbtm_bus_search_journey_return';
       
        $filter_by_box = 'return_filter-checkbox';
    }

?>
<style>
/* ==================================================================
   WBTM Modern Bus-List Result Layout
   ================================================================== */

/* ── Outer container: tab-bar + route header + results ─────────── */
.wbtm_departure_bus_lists_holder,
.wbtm_return_bus_lists_holder  { background: transparent; margin-bottom: 20px; border-radius: 12px; overflow: hidden; }

/* Step indicator: "① Select Departure Bus ---- ② Select Return Bus".
   WBTM_Layout::wbtm_bus_list() renders this wrapper for BOTH search styles
   (default + flix), so its styling now lives once in the shared
   assets/frontend/wtbm_search.css rather than duplicated in this
   style-specific inline block -- the flix template never had its own copy,
   which is why it fell back to that file's old (unstyled-for-this-purpose)
   version: a permanently-"active" solid gradient pill, since a one-way
   search only ever renders the single start tab. */
div#wbtm_date_start_route { height: 50px; }

/* ── Selected bus summary card (FlixBus style) ──────────────────── */
.wbtm_selected_bus_card {
    display:       flex;
    align-items:   stretch;
    background:    #fff;
    border:        1px solid #e2e6ea;
    border-radius: 10px;
    overflow:      hidden;
    margin-bottom: 12px;
    box-shadow:    0 1px 6px rgba(0,0,0,.06);
}
.wbtm_selbus_date_badge {
    background:      #16213e;
    color:           #fff;
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    padding:         14px 18px;
    min-width:       64px;
    flex-shrink:     0;
    text-align:      center;
    border-radius:   10px;
}
.wbtm_selbus_month {
    font-size:      11px;
    font-weight:    700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    opacity:        0.75;
}
.wbtm_selbus_day {
    font-size:   30px;
    font-weight: 800;
    line-height: 1;
    margin-top:  3px;
}
.wbtm_selbus_body {
    flex:           1;
    padding:        12px 18px;
    display:        flex;
    flex-direction: column;
    gap:            5px;
    min-width:      0;
}
.wbtm_selbus_toprow {
    display:     flex;
    align-items: center;
    gap:         8px;
    flex-wrap:   wrap;
}
.wbtm_selbus_trip_label {
    background:    #dbeafe;
    color:         #1d6eb5;
    font-size:     11px;
    font-weight:   700;
    padding:       2px 9px;
    border-radius: 20px;
    white-space:   nowrap;
}
.wbtm_selbus_busname {
    font-size:   13px;
    color:       #444;
    font-weight: 500;
}
.wbtm_selbus_busname em {
    font-style: normal;
    color:      #999;
    margin-left: 4px;
}
.wbtm_selbus_change_btn {
    margin-left:     auto;
    font-size:       13px;
    color:           #16213e;
    font-weight:     600;
    text-decoration: none;
    white-space:     nowrap;
    cursor:          pointer;
}
.wbtm_selbus_change_btn:hover { text-decoration: underline; }
.wbtm_selbus_route {
    display:     flex;
    align-items: center;
    gap:         10px;
    font-size:   20px;
    font-weight: 700;
    color:       #111;
    line-height: 1.2;
}
.wbtm_selbus_arrow { color: #555; font-size: 18px; }
.wbtm_selbus_details {
    display:     flex;
    align-items: center;
    gap:         14px;
    flex-wrap:   wrap;
}
.wbtm_selbus_times,
.wbtm_selbus_seat {
    display:     flex;
    align-items: center;
    gap:         4px;
    font-size:   13px;
    color:       #555;
}
.wbtm_selbus_price {
    font-size:   15px;
    font-weight: 700;
    color:       #16213e;
}
.wbtm_selbus_actions {
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    gap:             6px;
    padding:         12px 18px;
    border-left:     1px solid #eee;
    flex-shrink:     0;
    min-width:       140px;
}
.wbtm_selected_bus_btn {
    background:    var(--wbtm_color_theme, #e8510f);
    color:         #fff;
    border:        none;
    border-radius: 6px;
    padding:       8px 14px;
    font-size:     13px;
    font-weight:   600;
    cursor:        pointer;
    width:         100%;
    text-align:    center;
}
.wbtm_selbus_cart_link {
    font-size:       12px;
    color:           #555;
    text-decoration: underline;
}

/* ── Return journey header banner ───────────────────────────────── */
.wbtm-return-journey-header {
    padding:       14px 0 12px;
    margin-bottom: 6px;
}
.wbtm-return-journey-title {
    font-size:   20px;
    font-weight: 700;
    color:       #111827;
    line-height: 1.25;
    margin:      0 0 4px;
}
.wbtm-return-journey-sub {
    font-size:  13px;
    color:      #6b7280;
    margin:     0;
}

/* Route summary card (.wbtm_search_route_container, rendered by
   WBTM_Layout::route_title() -- shared by both search styles) used to be
   force-hidden here (`display:none !important`) with its real styling
   trapped behind a `.__unused` class that was never applied, so it never
   actually rendered on either style. It now lives, fused with the step
   banner above it into one shared white card, in
   assets/frontend/wtbm_search.css -- see [[project_busly_wbtm_flix_style]]. */

/* ── Page-level layout: sidebar + results ──────────────────────── */
.wbtm_search_result_holder {
    display:     flex;
    gap:         10px;
    align-items: flex-start;
}
.wbtm_bus_left_filter_holder {
    flex:         0 0 270px;
    min-width:    0;
    border:       none !important;
    background:   transparent !important;
    padding-left: 0px !important;
}
.wbtm_bus_list_area {
    flex:      1;
    min-width: 0;
    padding:   0;
}

/* ── Left filter card (custom redesign) ──────────────────────────── */
.wbtm-filter-card {
    background:    #fff;
    border:        1px solid #e8ecf0;
    border-radius: 12px;
    padding:       18px 16px;
    position:      sticky;
    top:           20px;
}
/* Header row: "Filters" + "Reset" */
.wbtm-filter-header {
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    padding-bottom:  12px;
    margin-bottom:   14px;
    border-bottom:   1px solid #f0f2f5;
}
.wbtm-filter-header-title {
    font-size:   18px;
    font-weight: 700;
    color:       #111;
}
.wbtm-filter-reset-btn {
    font-size:       12px;
    color:           #aaa;
    cursor:          pointer;
    text-decoration: underline;
    transition:      color 0.15s;
}
.wbtm-filter-reset-btn:hover { color: var(--wbtm_color_theme, #e8510f); }

/* Each group */
.wbtm-filter-section { margin-bottom: 18px; }
.wbtm-filter-section-label {
    font-size:      11px;
    font-weight:    700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color:          var(--wbtm_color_theme, #e8510f);
    margin-bottom:  10px;
}

/* Checkbox rows */
.wbtm-filter-cb-row {
    display:     flex;
    align-items: center;
    gap:         9px;
    padding:     5px 0;
    cursor:      pointer;
    font-size:   14px;
    color:       #333;
    line-height: 1.3;
    user-select: none;
}
.wbtm-filter-cb-row input[type="checkbox"] {
    width:        17px;
    height:       17px;
    border:       2px solid #ccc;
    border-radius: 4px;
    cursor:       pointer;
    accent-color: var(--wbtm_color_theme, #e8510f);
    flex-shrink:  0;
    margin:       0;
}

/* Member Discount promo card */
.wbtm-member-promo {
    margin-top:  18px;
    background:  linear-gradient(140deg, #1a2a6c 0%, #263f9f 100%);
    border-radius: 12px;
    padding:     18px 16px 20px;
    color:       #fff;
    position:    relative;
    overflow:    hidden;
}
.wbtm-member-promo::after {
    content:  '🏷';
    position: absolute;
    bottom:   -14px;
    right:    -4px;
    font-size: 90px;
    opacity:  .15;
    line-height: 1;
}
.wbtm-member-promo-title {
    font-size:   16px;
    font-weight: 700;
    margin:      0 0 8px;
}
.wbtm-member-promo-desc {
    font-size:   13px;
    opacity:     .85;
    margin:      0 0 14px;
    line-height: 1.5;
}
.wbtm-member-promo-btn {
    display:         inline-block;
    background:      var(--wbtm_color_theme, #e8510f);
    color:           #fff !important;
    padding:         8px 20px;
    border-radius:   20px;
    font-size:       13px;
    font-weight:     600;
    text-decoration: none;
    transition:      background 0.15s;
}
.wbtm-member-promo-btn:hover { background: #c84200; }

/* ── Count + sort header ────────────────────────────────────────── */
.wbtm-list-header {
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    margin-bottom:   14px;
}
.wbtm-list-count {
    font-size:   16px;
    color:       #444;
}
.wbtm-list-count strong {
    color:       #111;
    font-weight: 700;
}
.wbtm-list-sort {
    font-size:  14px;
    color:      #666;
    display:    flex;
    align-items: center;
    gap:        8px;
    flex-shrink: 0;
    white-space: nowrap;
}
.wbtm-sort-label-text { font-weight: 600; color: #222; white-space: nowrap; }
.wbtm-sort-select {
    padding: 6px 28px 6px 10px;
    border: 1px solid #d8dcea;
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    color: #222;
    cursor: pointer;
    line-height: 1.4;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 8px center;
}
.wbtm-sort-select:focus { outline: 2px solid #2563eb; outline-offset: 1px; }

/* ── Bus card ───────────────────────────────────────────────────── */
/* Must NOT use !important on display — jQuery fadeOut() sets display:none inline
   and !important would block the filter animation. Use higher specificity instead. */
.wbtm_bus_list_area .wbtm_search_result_holder .wbtm-bus-list {
    display:         block;
    justify-content: unset;
}
.wbtm-bus-list {
    background:    #fff;
    border:        1px solid #eaecf2;
    border-radius: 16px;
    margin-bottom: 10px;
    padding:       0;
    overflow:      hidden;
    box-shadow:    0 1px 4px rgba(0,0,0,.04);
}
.wbtm-bus-list,
.wbtm_search_result .wbtm-bus-list,
.wbtm-bus-list *,
.wbtm_search_result .wbtm-bus-list * {
    transition: none !important;
    animation:  none !important;
}

.wbtm-bus-list { box-shadow: 0 1px 4px rgba(0,0,0,.04) !important; }
.wbtm-bus-list.in_cart {
    border-color: #16a34a;
    box-shadow:   0 0 0 2px rgba(22,163,74,.15);
}
.wbtm-bus-list.wbtm-sold-out { opacity: .6; }
.wbtm-soldout-badge {
    display: inline-block;
    background: #dc2626;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    letter-spacing: .3px;
    margin-bottom: 8px;
}
.wbtm-seat-book button.wbtm-disabled,
.wbtm-seat-book button:disabled {
    cursor: not-allowed;
    opacity: .5;
    pointer-events: none;
}

/* Card layout: photo | (journey strip over bus info) | price
   ---------------------------------------------------------------------------
   These four panels used to sit side by side in one unwrappable flex row, which
   left the info panel roughly 180px wide however large the screen was. Its
   amenity chips and detail buttons then dropped to one per line, the panel grew
   several hundred pixels tall, and the photo — stretched to match — turned the
   whole card into a tall block. Below the desktop width the row could not fit at
   all and simply ran off the side of the page.

   A grid fixes the cause rather than the symptom: the journey strip and the bus
   info share ONE column, stacked, so the info panel gets the full width of that
   column for its chips and buttons and the card stays a compact strip. The photo
   and price span both rows down either side. Markup is unchanged — only the
   placement of the four panels the template already renders. */
.wbtm-card-wrap {
    display:               grid;
    grid-template-columns: minmax(140px, 232px) minmax(0, 1fr) minmax(150px, 200px);
    /* heading line / journey strip / bus info — the middle column's three rows */
    grid-template-rows:    auto auto 1fr;
    align-items:           stretch;
    width:                 100%;
    min-height:            132px;
    background:      linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    border:          1px solid #e5edf7;
    border-radius:   24px;
    overflow:        hidden;
    box-shadow:      0 14px 38px rgba(15, 23, 42, 0.06);
    transition:      transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.wbtm-card-wrap:hover {
    transform:    translateY(-2px);
    border-color: #d5e3f5;
    box-shadow:   0 22px 48px rgba(15, 23, 42, 0.1);
}

/* ── Bus / boat photo (per-departure card thumbnail) ────────────── */
.wbtm-card-photo {
    grid-column:     1;
    grid-row:        1 / -1;   /* full height, beside all three stacked rows */
    align-self:      stretch;
    position:        relative;  /* anchors the bus/boat type badge */
    display:         flex;
    align-items:     center;      /* vertical-center the image in the card */
    justify-content: center;      /* horizontal-center */
    overflow:        hidden;
    background:      #eef3fb;
    border-right:    1px solid #e5edf7;
}
/* height:100% !important beats themes that force `img { height:auto }`, so the
   photo fills the column instead of sitting top-aligned; the flex centering
   above keeps it centred if a theme still wins the height rule. */
.wbtm-card-photo img {
    width:           100% !important;
    height:          100% !important;
    max-width:       100%;
    /* The photo column stretches to the tallest column, and the info column
       grows tall whenever its amenity chips and detail buttons wrap onto many
       lines — an operator listing seven amenities and five buttons stretched
       this image to several hundred pixels and made the card enormous. The
       tablet breakpoint stops the info column wrapping like that in the first
       place; this cap is the backstop for a fleet with more chips than any
       breakpoint can keep inline. The column stays flex-centred, so a capped
       image sits centred against the panel instead of being distorted. */
    max-height:      260px;
    object-fit:      cover;
    object-position: center;
    display:         block;
}

/* ── LEFT: times + duration track ───────────────────────────────── */
.wbtm-card-head {
    grid-column: 2;
    grid-row:    1;
    padding:     16px 22px 4px;
    background:  #ffffff;
    min-width:   0;
}

.wbtm-card-times {
    grid-column:           2;
    grid-row:              2;
    display:               grid;
    grid-template-columns: auto 1fr auto;
    grid-template-rows:    auto auto;
    align-items:           center;
    align-content:         center;
    row-gap:               0;
    column-gap:            14px;
    /* The duration pill is absolutely positioned above the track, so the strip
       needs enough head room to contain it — with the heading line now directly
       above, too little padding let the pill ride up over the operator name. */
    padding:               30px 22px 14px;
    background:            linear-gradient(135deg, #f8fbff 0%, #f2f6ff 100%);
    /* Banded between the heading line and the bus info now rather than sitting
       beside them, so the divider that separated the columns becomes a rule
       above and below the strip. */
    border-top:            1px solid #e5edf7;
    border-bottom:         1px solid #e5edf7;
    position:              relative;
}
.wbtm-time-depart,
.wbtm-time-arrive {
    font-size:   26px;
    font-weight: 800;
    color:       #0f172a;
    line-height: 1;
    letter-spacing: -0.03em;
}
.wbtm-time-depart { grid-column: 1; grid-row: 1; }
.wbtm-time-arrive { grid-column: 3; grid-row: 1; text-align: right; }
.wbtm-city-depart {
    grid-column: 1;
    grid-row:    2;
    font-size:   12px;
    font-weight: 600;
    color:       #64748b;
    margin-top:  6px;
    letter-spacing: 0.01em;
}
.wbtm-city-arrive {
    grid-column: 3;
    grid-row:    2;
    font-size:   12px;
    font-weight: 600;
    color:       #64748b;
    margin-top:  6px;
    text-align:  right;
    letter-spacing: 0.01em;
}
.wbtm-duration-track-wrap {
    grid-column:     2;
    grid-row:        1 / span 2;
    position:        relative;
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    gap:             4px;
    min-width:       96px;
}
/* The duration pill, dot, line and icon are the card's accent. They were
   hardcoded indigo while the rest of the plugin (filter sidebar, buttons)
   already followed the operator's configured Style → Theme Colour, so an
   operator who set their brand colour still got an indigo track here. They
   read --wbtm_color_theme now, with the old indigo kept as the fallback so a
   site that never set the variable looks exactly as it did. */
.wbtm-track-duration {
    position:      absolute;
    bottom:        100%;
    left:          50%;
    transform:     translateX(-50%);
    margin-bottom: 7px;
    font-size:     10px;
    font-weight:   700;
    color:         var(--wbtm_color_theme, #4338ca);
    white-space:   nowrap;
    line-height:   1;
    background:    #f4f6fb;
    padding:       5px 9px;
    border-radius: 20px;
    border:        1px solid var(--wbtm_color_theme_77, rgba(99, 102, 241, 0.14));
}
.wbtm-track-dot {
    width:         11px;
    height:        11px;
    border-radius: 50%;
    background:    #fff;
    border:        2px solid #94a3b8;
    flex-shrink:   0;
    box-shadow:    0 0 0 4px rgba(148, 163, 184, 0.08);
}
.wbtm-track-line {
    position:   relative;
    width:      100%;
    height:     0;
    min-width:  56px;
    border-top: 2px dashed var(--wbtm_color_theme_77, #c7d2fe);
}
.wbtm-track-line::after {
    content:         '\f207';
    font-family:     'Font Awesome 5 Free';
    font-weight:     900;
    position:        absolute;
    top:             50%;
    left:            50%;
    transform:       translate(-50%, -50%);
    width:           30px;
    height:          30px;
    border-radius:   50%;
    background:      linear-gradient(135deg, var(--wbtm_color_theme_cc, #6366f1) 0%, var(--wbtm_color_theme, #4338ca) 100%);
    display:         flex;
    align-items:     center;
    justify-content: center;
    color:           var(--wbtm_color_theme_alter, #fff);
    font-size:       11px;
    line-height:     30px;
    box-shadow:      0 10px 20px rgba(67,56,202,.22);
}

/* ── MIDDLE: bus info ────────────────────────────────────────────── */
.wbtm-card-info {
    grid-column:    2;
    grid-row:       3;
    min-width:      0;          /* let it shrink inside the grid track */
    padding:        16px 22px 18px;
    display:        flex;
    flex-direction: column;
    gap:            10px;
    background:     #ffffff;
}
/* Operator name and vehicle number share the header line — name left, number
   pushed to the far right — instead of stacking as two rows of their own. */
.wbtm-operator-row {
    display:     flex;
    align-items: center;
    gap:         10px;
    flex-wrap:   wrap;
}
.wbtm-operator-row .wbtm-coach-number { margin-left: auto; }
.wbtm-operator-name {
    font-size:   20px;
    font-weight: 800;
    color:       #0f172a;
    cursor:      pointer;
    letter-spacing: -0.02em;
}
.wbtm-coach-number {
    display:       inline-flex;
    align-items:   center;
    gap:           4px;
    width:         fit-content;
    color:         #475569;
    background:    #f8fafc;
    border:        1px solid #e2e8f0;
    border-radius: 999px;
    padding:       4px 9px;
    font-size:     11px;
    font-weight:   600;
}
.wbtm-coach-number strong {
    color: #0f172a;
}
/* Sits on the photo, bottom-left, like the type chip on a ferry listing. Takes
   the operator's configured Style → Theme Colour so it matches the rest of the
   card rather than introducing a colour of its own. */
.wbtm-type-badge {
    position:      absolute;
    left:          10px;
    bottom:        10px;
    z-index:       2;
    max-width:     calc(100% - 20px);
    overflow:      hidden;
    text-overflow: ellipsis;
    font-size:     10px;
    font-weight:   700;
    padding:       4px 11px;
    border-radius: 20px;
    white-space:   nowrap;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    color:         var(--wbtm_color_theme_alter, #ffffff);
    background:    var(--wbtm_color_theme, #e8510f);
    box-shadow:    0 4px 12px rgba(15, 23, 42, 0.22);
}
.wbtm-type-badge--top {
    background: #dcfce7;
    color:      #15803d;
}
.wbtm-type-badge--std {
    background: #f1f5f9;
    color:      #64748b;
}
.wbtm-amenities {
    display:  flex;
    flex-wrap: wrap;
    gap:      6px;
}
/* Amenity chips. These were meant to be pills but never rendered as any: the
   rule said `border-radius: none`, which is not a valid value — the whole
   declaration was dropped — and the background was plain white with no border,
   so the amenities came out as a run of loose text. Tinted from the operator's
   configured Style → Theme Colour so the row matches the rest of the card;
   the flat fallbacks in front of each color-mix() keep older browsers on a
   neutral tint rather than no chip at all. */
.wbtm-amenity {
    display:       inline-flex;
    align-items:   center;
    gap:           5px;
    font-size:     11.5px;
    font-weight:   600;
    line-height:   1.5;
    color:         #41525f;
    background:    #f4f7fb;
    background:    color-mix(in srgb, var(--wbtm_color_theme, #e8510f) 7%, #ffffff);
    padding:       4px 10px;
    border-radius: 999px;
    border:        1px solid #e6ecf4;
    border:        1px solid color-mix(in srgb, var(--wbtm_color_theme, #e8510f) 18%, #ffffff);
    white-space:   nowrap;
}
.wbtm-amenity i {
    color:     var(--wbtm_color_theme, #64748b);
    font-size: 11px;
}
.wbtm-seats-avail {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #166534;
    padding: 6px 10px;
    align-self: flex-start;
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    border-radius: 999px;
}
.wbtm-card-info .wbtm_bus_details_tabs_holder {
    margin-top:  auto;
    padding-top: 12px;
    border-top:  1px dashed #dbe5f0;
}
.wbtm-card-info .wbtm_bus_popup_links {
    display:    flex !important;
    flex-wrap:  wrap;
    gap:        8px;
    visibility: visible !important;
    opacity:    1 !important;
    transform:  none !important;
}
.wbtm-card-info .wbtm_bus_popup_link {
    font-size:     12px;
    font-weight:   600;
    color:         #334155;
    background:    #f8fafc;
    border:        1px solid #dbe4f0;
    border-radius: 999px;
    padding:       7px 12px;
    cursor:        pointer;
    transition:    background 0.18s, color 0.18s, border-color 0.18s, transform 0.18s;
}
.wbtm-card-info .wbtm_bus_popup_link:hover {
    color:        var(--wbtm_color_theme, #4338ca);
    background:   #f4f6fb;
    border-color: var(--wbtm_color_theme_77, #c7d2fe);
    transform:    translateY(-1px);
}

/* ── RIGHT: price + book ─────────────────────────────────────────── */
.wbtm-card-price {
    grid-column:     3;
    grid-row:        1 / -1;   /* full height, beside both stacked rows */
    min-width:       0;
    padding:         20px 18px;
    display:         flex;
    flex-direction:  column;
    align-items:     flex-start;
    justify-content: center;
    gap:             10px;
    border-left:     1px solid #e5edf7;
    background:      linear-gradient(180deg, #fcfdff 0%, #f6f8ff 100%);
}
.wbtm-starting-from {
    font-size:     11px;
    font-weight:   600;
    color:         #94a3b8;
    text-align:    left;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.wbtm-price-value {
    font-size:   34px;
    font-weight: 800;
    color:       #111827;
    text-align:  left;
    line-height: 1;
    letter-spacing: -0.04em;
}
.wbtm-price-value .woocommerce-Price-amount {
    font-size: inherit !important;
    color:     inherit !important;
}
/* Route with no fare configured: shown in place of the price + Book button. */
.wbtm-route-unavailable {
    display:       flex;
    align-items:   center;
    gap:           8px;
    text-align:    left;
    background:    #fff7ed;
    border:        1px solid #fed7aa;
    color:         #b45309;
    border-radius: 10px;
    padding:       10px 12px;
    font-size:     13px;
    font-weight:   600;
    line-height:   1.4;
}
.wbtm-route-unavailable i {
    font-size:  15px;
    flex-shrink: 0;
}
.wbtm-card-price .wbtm-seat-book {
    width:      100%;
    margin-top: 4px;
}
.wbtm-card-price .wbtm-seat-book._themeButton_xs,
.wbtm-card-price ._themeButton_xs,
.wbtm-card-price #get_wbtm_bus_details {
    width:           100% !important;
    border-radius:   16px !important;
    padding:         13px 18px !important;
    font-size:       14px !important;
    font-weight:     700 !important;
    text-align:      center !important;
    display:         flex !important;
    align-items:     center !important;
    justify-content: center !important;
    gap:             6px !important;
    cursor:          pointer;
    white-space:     nowrap;
    border:          none !important;
    box-shadow:      0 14px 26px rgba(232,81,15,.24) !important;
    transition:      transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s !important;
}

.wbtm-card-price #get_wbtm_bus_details:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 32px rgba(232,81,15,.28) !important;
}

/* Seat-expansion area */
.wbtm_bus_details { border-top: 1px solid #eaecf2; }


/* ── Tablet / narrow desktop: stay compact, stay on ONE row ──────
   Between 768px and the full desktop width the card had no rules of its own,
   so it kept desktop paddings and type on a column that could not fit them:
   every panel was squeezed to its min-width, the amenity chips and detail
   buttons dropped to one per line, and the photo stretched to match. Same
   four columns here, just sized for the space — which is what keeps the card
   the compact strip it is on desktop rather than a tall block. */
/* Below 1100px the 270px filter rail costs the results more than it is worth —
   it left the card barely 200px of content to fit three tracks into, which is
   what pushed the detail buttons back onto separate lines. The rail moves above
   the list instead, so the cards keep the full page width and stay compact. */
@media (min-width: 768px) and (max-width: 1099px) {
    .wbtm_search_result_holder   { flex-direction: column; gap: 12px; }
    .wbtm_bus_left_filter_holder { flex: none; width: 100%; }
    .wbtm_bus_list_area          { width: 100% !important; padding: 0; }
    .wbtm-filter-card            { position: static; }
}

@media (min-width: 768px) and (max-width: 1199px) {
    /* Narrower photo and price rails, and type a step down, so the three-track
       shape — the thing that keeps the card a compact strip — survives the
       smaller column instead of collapsing back into stacked rows. */
    .wbtm-card-wrap {
        grid-template-columns: minmax(96px, 132px) minmax(0, 1fr) minmax(124px, 158px);
    }
    .wbtm-card-photo img { max-height: 190px; }
    .wbtm-card-head     { padding: 13px 12px 0; }

    .wbtm-card-times {
        padding:    26px 12px 12px;
        column-gap: 6px;
    }
    .wbtm-time-depart,
    .wbtm-time-arrive { font-size: 20px; }
    .wbtm-city-depart,
    .wbtm-city-arrive { font-size: 11px; margin-top: 4px; }
    .wbtm-duration-track-wrap { min-width: 58px; }
    .wbtm-track-line          { min-width: 30px; }
    .wbtm-track-duration      { font-size: 9px; padding: 4px 7px; margin-bottom: 8px; }

    .wbtm-card-info {
        padding: 13px 12px 14px;
        gap:     7px;
    }
    .wbtm-operator-name { font-size: 16px; }
    .wbtm-coach-number  { font-size: 10px; padding: 3px 8px; }
    .wbtm-amenity       { font-size: 11px; padding: 2px 6px; gap: 4px; }
    .wbtm-seats-avail   { font-size: 11px; padding: 4px 9px; }
    .wbtm-card-info .wbtm_bus_details_tabs_holder { padding-top: 9px; }
    .wbtm-card-info .wbtm_bus_popup_links         { gap: 6px; }
    .wbtm-card-info .wbtm_bus_popup_link          { font-size: 11px; padding: 5px 9px; }

    .wbtm-card-price {
        padding: 14px 12px;
        gap:     7px;
    }
    .wbtm-price-value   { font-size: 23px; }
    .wbtm-starting-from { font-size: 10px; letter-spacing: 0.08em; }
    .wbtm-card-price .wbtm-seat-book._themeButton_xs,
    .wbtm-card-price ._themeButton_xs,
    .wbtm-card-price #get_wbtm_bus_details {
        padding:       11px 12px !important;
        font-size:     13px !important;
        border-radius: 13px !important;
    }
}

/* ── Mobile ─────────────────────────────────────────────────────── */
@media (max-width: 767px) {
    .wbtm_search_result_holder  { flex-direction: column; gap: 12px; max-width: 100%; }
    .wbtm_bus_left_filter_holder { flex: none; width: 100%; }
    .wbtm_bus_list_area { width: 100% !important; padding: 0; }
    .wbtm-filter-card { position: static; }

    /* Step indicator (round-trip): shrink so both steps fit */
    .wbtm_bus_tab_wrapper:has(.wtbm_return_route) { padding: 10px 12px; }
    .wbtm_bus_tab_wrapper .wtbm_start_route,
    .wbtm_bus_tab_wrapper .wtbm_return_route { font-size: 12px; gap: 6px; }
    .wbtm_bus_tab_wrapper .wtbm_start_route::before,
    .wbtm_bus_tab_wrapper .wtbm_return_route::before { width: 22px; height: 22px; font-size: 11px; }
    .wbtm_bus_tab_wrapper .wbtm-step-connector { flex: 1 1 20px; min-width: 16px; margin: 0 6px; }

    /* Selected-bus summary card stacks */
    .wbtm_selected_bus_card { flex-wrap: wrap; }
    .wbtm_selbus_body { flex: 1 1 200px; padding: 12px 14px; }
    .wbtm_selbus_route { font-size: 16px; }
    .wbtm_selbus_actions {
        flex-direction: row;
        width:          100%;
        min-width:      0;
        border-left:    none;
        border-top:     1px solid #eee;
        padding:        10px 14px;
    }

    /* Count + sort header wraps instead of overflowing */
    .wbtm-list-header { flex-wrap: wrap; gap: 6px; }
    .wbtm-list-count  { font-size: 14px; }
    .wbtm-list-sort   { font-size: 13px; }

    /* Bus card: the three tracks collapse to one, so the four panels stack. */
    .wbtm-card-wrap {
        grid-template-columns: 1fr;
        grid-template-rows:    auto;
        min-height:            0;
        border-radius:         18px;
    }
    .wbtm-bus-list  { min-width: 0; }
    .wbtm-card-photo {
        grid-column:   1;
        grid-row:      auto;   /* stop spanning: every panel is its own row now */
        width:         100%;
        min-width:     0;
        max-width:     none;
        height:        170px;
        border-right:  none;
        border-bottom: 1px solid #e5edf7;
    }
    .wbtm-card-photo img { max-height: none; }
    .wbtm-card-head {
        grid-column: 1;
        grid-row:    auto;
        padding:     16px 16px 0;
    }
    .wbtm-card-times {
        grid-column:   1;
        grid-row:      auto;
        border-top:    none;
        width:         100%;
        border-right:  none;
        border-bottom: 1px solid #e5edf7;
        /* Head room for the duration pill, which floats above the track: the
           heading line sits directly on top of the strip on a phone, and with
           desktop padding the pill rode up over the operator name. */
        padding:       34px 16px 18px;
        column-gap:    10px;
        background:    linear-gradient(135deg, #f8fbff 0%, #f2f6ff 100%);
    }
    .wbtm-card-info { grid-column: 1; grid-row: auto; }
    .wbtm-time-depart, .wbtm-time-arrive { font-size: 20px; }
    .wbtm-duration-track-wrap { min-width: 40px; }
    .wbtm-card-info  { border-right: none; border-bottom: 1px solid #e5edf7; padding: 16px 16px; gap: 10px; }
    .wbtm-card-price {
        grid-column:     1;
        grid-row:        auto;
        width:           100%;
        min-width:       0;
        max-width:       none;
        border-left:     none;
        flex-direction:  row;
        align-items:     center;
        justify-content: space-between;
        flex-wrap:       wrap;
        gap:             12px;
        padding:         16px 16px;
        background:      linear-gradient(180deg, #fcfdff 0%, #f6f8ff 100%);
    }
    .wbtm-starting-from { width: 100%; text-align: left; margin-bottom: -6px; }
    .wbtm-price-value   { font-size: 22px; text-align: left; }
    .wbtm-card-price .wbtm-seat-book { width: auto; margin-top: 0; }
    .wbtm-card-price #get_wbtm_bus_details { width: auto !important; padding: 10px 20px !important; }
}
@media (max-width: 480px) {
    /* Keeps the head room the floating duration pill needs — a flat 12px here
       put the pill back on top of the operator name on small phones. */
    .wbtm-card-times { padding: 32px 12px 12px; }
    .wbtm-time-depart, .wbtm-time-arrive { font-size: 16px; }
    .wbtm-city-depart, .wbtm-city-arrive { font-size: 10px; }
    .wbtm-price-value { font-size: 20px; }
    .wbtm-card-price #get_wbtm_bus_details { padding: 9px 16px !important; font-size: 13px !important; }
}
</style>

<div class="wbtm_search_result_holder">
    <?php
    // Show the filter sidebar only for multi-bus route search results.
    // When $post_id is set, this template is rendering a single bus's own
    // booking widget (e.g. the "search from this bus" box on the bus's
    // single page) — there's only ever one bus in that result set, so a
    // filter sidebar is meaningless there; the list area goes full width.
    // Frontend Display settings (Global Settings → Frontend Display).
    // Each defaults to 'show' so existing sites are unaffected until toggled.
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_panel     = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_filter_panel', 'show') !== 'hide';
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_time      = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_filter_departure_time', 'show') !== 'hide';
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_type      = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_filter_bus_type', 'show') !== 'hide';
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_operator  = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_filter_bus_operator', 'show') !== 'hide';
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_boarding  = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_filter_boarding_point', 'show') !== 'hide';
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    $wbtm_fd_sort      = WBTM_Global_Function::get_settings('wbtm_frontend_display_settings', 'show_sort_bar', 'show') !== 'hide';

    $has_left_filter = count($bus_titles) > 0 && empty($post_id) && $wbtm_fd_panel;

    // Always show all 4 departure-time options, regardless of whether the
    // current result set happens to have a bus in that window.
    // Labels intentionally show no raw numbers (e.g. "12–6" reads as ambiguous
    // without AM/PM) — the hour range is kept only as the checkbox's hidden
    // value attribute below, which is what actually drives the filter:
    //   Morning   6:00 AM – 11:59 AM   (hour >= 6  && hour < 12)
    //   Afternoon 12:00 PM – 5:59 PM   (hour >= 12 && hour < 18)
    //   Evening   6:00 PM  – 9:59 PM   (hour >= 18 && hour < 22)
    //   Night     10:00 PM – 5:59 AM   (hour >= 22 || hour < 6, wraps midnight)
    $all_time_opts = [
        'morning'   => ['label' => '🌅 ' . __('Morning',   'bus-ticket-booking-with-seat-reservation'), 'min' => 6,  'max' => 12],
        'afternoon' => ['label' => '☀️ '  . __('Afternoon', 'bus-ticket-booking-with-seat-reservation'), 'min' => 12, 'max' => 18],
        'evening'   => ['label' => '🌆 ' . __('Evening',   'bus-ticket-booking-with-seat-reservation'), 'min' => 18, 'max' => 22],
        'night'     => ['label' => '🌙 ' . __('Night',     'bus-ticket-booking-with-seat-reservation'), 'min' => 22, 'max' => 6],
    ];
    $wbtm_time_buckets = $all_time_opts;
    ?>

    <?php if ($has_left_filter) : ?>
    <div class="wbtm_bus_left_filter_holder">
        <?php
        // Collapsible "Filters" bar: assets/frontend/wtbm_search.css already ships
        // the full styling for `.wbtm-mobile-filter-toggle` (desktop collapse-to-rail
        // AND mobile results-first collapse) and assets/frontend/wbtm.js already
        // wires its click handler -- but no template ever rendered the button
        // itself, so both CSS rules that hide the panel by default (mobile, and
        // desktop once collapsed) had no way to ever be reversed.
        ?>
        <button type="button" class="wbtm-mobile-filter-toggle" aria-expanded="true">
            <span class="wbtm-mobile-filter-toggle-label">
                <i class="fas fa-filter" aria-hidden="true"></i>
                <?php esc_html_e('Filters', 'bus-ticket-booking-with-seat-reservation'); ?>
            </span>
            <span class="wbtm-mobile-filter-caret"><i class="fas fa-chevron-down" aria-hidden="true"></i></span>
        </button>
        <div class="wbtm-filter-card">

            <div class="wbtm-filter-header">
                <span class="wbtm-filter-header-title"><?php esc_html_e('Filters', 'bus-ticket-booking-with-seat-reservation'); ?></span>
            </div>

            <!-- Departure Time -->
            <?php if ($wbtm_fd_time && !empty($wbtm_time_buckets)) : ?>
            <div class="wbtm-filter-section">
                <div class="wbtm-filter-section-label"><?php esc_html_e('Departure Time', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <?php foreach ($wbtm_time_buckets as $k => $opt) : ?>
                <label class="wbtm-filter-cb-row">
                    <input type="checkbox"
                           class="<?php echo esc_attr($filter_by_box); ?>"
                           data-filter="wbtm_departure_time"
                           value="<?php echo esc_attr($opt['min'] . '-' . $opt['max']); ?>">
                    <?php echo esc_html($opt['label']); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Bus Type -->
            <?php $unique_bus_types = array_unique(array_filter($bus_types)); ?>
            <?php if ($wbtm_fd_type && !empty($unique_bus_types)) : ?>
            <div class="wbtm-filter-section">
                <div class="wbtm-filter-section-label"><?php esc_html_e('Bus Type', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <?php foreach ($unique_bus_types as $type) : ?>
                <label class="wbtm-filter-cb-row">
                    <input type="checkbox" class="<?php echo esc_attr($filter_by_box); ?>" data-filter="wbtm_bus_type" value="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html($type); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Bus Operator -->
            <?php if ($wbtm_fd_operator && !empty($bus_titles)) : ?>
            <div class="wbtm-filter-section">
                <div class="wbtm-filter-section-label"><?php esc_html_e('Bus Operator', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <?php foreach (array_unique($bus_titles) as $title) : ?>
                <label class="wbtm-filter-cb-row">
                    <input type="checkbox" class="<?php echo esc_attr($filter_by_box); ?>" data-filter="wbtm_bus_name" value="<?php echo esc_attr($title); ?>">
                    <?php echo esc_html($title); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Boarding Point -->
            <?php if ($wbtm_fd_boarding && !empty($all_boarding_routes)) : ?>
            <div class="wbtm-filter-section">
                <div class="wbtm-filter-section-label"><?php esc_html_e('Boarding Point', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                <?php foreach (array_unique($all_boarding_routes) as $route) : if (!$route) continue; ?>
                <label class="wbtm-filter-cb-row">
                    <input type="checkbox" class="<?php echo esc_attr($filter_by_box); ?>" data-filter="wbtm_bus_start_route" value="<?php echo esc_attr($route); ?>">
                    <?php echo esc_html($route); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Reset — below every filter section, at the bottom of the list. -->
            <div class="wbtm-filter-reset-row">
                <span class="wbtm-filter-reset-btn wbtm_reset_filter-checkbox">
                    <?php esc_html_e('Reset', 'bus-ticket-booking-with-seat-reservation'); ?>
                </span>
            </div>

            <!-- Member Discount promo card — content configurable under
                 Settings → Promo Banner (WBTM_Global_settings.php). -->
            <?php
            $wbtm_promo_enabled = WBTM_Global_Function::get_settings('wbtm_promo_settings', 'promo_enabled', 'enable');
            if ($wbtm_promo_enabled !== 'disable') :
                $wbtm_promo_title      = WBTM_Global_Function::get_settings('wbtm_promo_settings', 'promo_title', __('Member Discount', 'bus-ticket-booking-with-seat-reservation'));
                $wbtm_promo_desc       = WBTM_Global_Function::get_settings('wbtm_promo_settings', 'promo_desc', __('Save up to 15% on your first trip to {destination}.', 'bus-ticket-booking-with-seat-reservation'));
                $wbtm_promo_btn_text   = WBTM_Global_Function::get_settings('wbtm_promo_settings', 'promo_button_text', __('Join Now', 'bus-ticket-booking-with-seat-reservation'));
                $wbtm_promo_btn_link   = WBTM_Global_Function::get_settings('wbtm_promo_settings', 'promo_button_link', '');
                // With a destination, fill the token in; without one, drop the
                // "to {destination}" phrase entirely so the sentence still reads
                // cleanly (mirrors the two hand-written variants this replaced).
                if ($end_route) {
                    $wbtm_promo_desc = str_replace('{destination}', $end_route, $wbtm_promo_desc);
                } else {
                    $wbtm_promo_desc = preg_replace('/\s*\bto\s*\{destination\}/i', '', $wbtm_promo_desc);
                    $wbtm_promo_desc = str_replace('{destination}', '', $wbtm_promo_desc);
                }
            ?>
            <div class="wbtm-member-promo">
                <p class="wbtm-member-promo-title"><?php echo esc_html($wbtm_promo_title); ?></p>
                <p class="wbtm-member-promo-desc">
                    <?php echo esc_html($wbtm_promo_desc); ?>
                </p>
                <a href="<?php echo esc_url($wbtm_promo_btn_link ? $wbtm_promo_btn_link : '#'); ?>" class="wbtm-member-promo-btn"><?php echo esc_html($wbtm_promo_btn_text); ?></a>
            </div>
            <?php endif; ?>

        </div><!-- /.wbtm-filter-card -->
    </div><!-- /.wbtm_bus_left_filter_holder -->
    <?php endif; ?>

    <div id="wbtm-bus-popup" class="wbtm-bus-popup">
        <div class="wbtm-bus-popup-inner">
            <span class="wbtm-popup-close">&times;</span>
            <div class="wbtm-popup-content"></div>
        </div>
    </div>

    <div class="wbtm_bus_list_area">
        <input type="hidden" name="bus_start_route" value="<?php echo esc_attr($search_info['bus_start_route'] ?? ''); ?>" />
        <input type="hidden" name="bus_end_route"   value="<?php echo esc_attr($search_info['bus_end_route']   ?? ''); ?>" />
        <input type="hidden" name="j_date"          value="<?php echo esc_attr($search_info['j_date']          ?? ''); ?>" />
        <input type="hidden" name="r_date"          value="<?php echo esc_attr($search_info['r_date']          ?? ''); ?>" />
        <input type="hidden" name="wbtm_start_route" value="<?php echo esc_attr($start_route); ?>" />
        <input type="hidden" name="wbtm_end_route"   value="<?php echo esc_attr($end_route); ?>" />
        <input type="hidden" name="wbtm_price_leg"   value="<?php echo esc_attr($wbtm_price_leg); ?>" />
        <input type="hidden" name="wbtm_date"        value="<?php echo esc_attr(gmdate('Y-m-d', strtotime($date))); ?>" />

        <?php
        // Sort by departure time (earliest first)
        usort($bus_data, function ($a, $b) {
            return strtotime($a['all_info']['bp_time']) - strtotime($b['all_info']['bp_time']);
        });

        $total_buses = count($bus_data);
        ?>

        <!-- Count + sort header -->
        <div class="wbtm-list-header">
            <div class="wbtm-list-count">
                <strong><?php echo esc_html($total_buses); ?></strong>
                <?php echo esc_html__('buses available for', 'bus-ticket-booking-with-seat-reservation'); ?>
                <?php echo esc_html(date_i18n('F j', strtotime($date))); ?>
            </div>
            <?php if ($wbtm_fd_sort) : ?>
            <div class="wbtm-list-sort">
                <?php $wbtm_sort_id = 'wbtm_sort_' . sanitize_html_class($filter_by_box); ?>
                <label for="<?php echo esc_attr($wbtm_sort_id); ?>" class="wbtm-sort-label-text">
                    <?php esc_html_e('Sort by', 'bus-ticket-booking-with-seat-reservation'); ?>:
                </label>
                <select id="<?php echo esc_attr($wbtm_sort_id); ?>" class="formControl wbtm-sort-select">
                    <option value="earliest" selected><?php esc_html_e('Earliest First', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                    <option value="latest"><?php esc_html_e('Latest First', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                    <option value="price_asc"><?php esc_html_e('Price: Low to High', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                    <option value="price_desc"><?php esc_html_e('Price: High to Low', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                    <option value="duration_asc"><?php esc_html_e('Shortest Duration', 'bus-ticket-booking-with-seat-reservation'); ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php foreach ($bus_data as $key => $bus) :
            $bus_id       = $bus['bus_id'];
            $popup_tabs   = WBTM_Functions::single_bus_details_tabs_filtered($bus_id);
            $all_info     = $bus['all_info'];
            $wbtm_price_leg = $bus['price_leg'] ?? $wbtm_price_leg;
            $route_priced = $bus['route_priced'] ?? true;
            $bus_count++;
            $price        = $all_info['price'];
            $bp_time      = $all_info['bp_time'];
            $dp_time      = $all_info['dp_time'];
            $next_day     = $all_info['next_day'] ?? '0';

            $bus_boarding_routes = WBTM_Functions::get_bus_route($bus_id);
            $bus_type            = WBTM_Functions::synchronize_bus_type($bus_id);

            $bp_ts  = strtotime($bp_time);
            $dp_ts  = strtotime($dp_time);
            if ($next_day == '1') { $dp_ts += 86400; }
            $dur_s  = $dp_ts - $bp_ts;
            $dur_h  = floor($dur_s / 3600);
            $dur_m  = floor(($dur_s % 3600) / 60);
            $duration_formatted = sprintf(
                /* translators: 1: hours, 2: minutes */
                __('%1$dh %2$dm', 'bus-ticket-booking-with-seat-reservation'),
                $dur_h, $dur_m
            );

            $show_details_tabs  = WBTM_Global_Function::get_settings('wbtm_general_settings', 'show_hide_bus_details_tabs', 'show');
            $details_tabs_class = $show_details_tabs === 'hide' ? ' wbtm_no_tabs' : '';

            // Bus amenity/feature list (graceful no-op when class unavailable)
            $feature_list = [];
            if (class_exists('WTBM_Features_Seating')) {
                $all_features     = WTBM_Features_Seating::get_all_bus_features();
                $selected_ids     = get_post_meta($bus_id, 'wbbm_bus_features_term_id', true);
                $feature_list     = WBTM_Functions::getSelectedFeatures($all_features, (array)$selected_ids);
            }

            // Sold-out detection: a bus with no seats left still appears in the
            // list but is flagged so the card can be greyed + its Book button disabled.
            $is_sold_out = (int) ($all_info['available_seat'] ?? 0) <= 0;
        ?>

            <div class="wbtm-bus-list wtbm_bus_counter <?php echo esc_attr($wbtm_bus_search); echo esc_attr(WBTM_Global_Function::check_product_in_cart($bus_id) ? ' in_cart' : ''); echo esc_attr($is_sold_out ? ' wbtm-sold-out' : ''); ?>"
                 id="wbtm_bust_list"
                 data-bus-id="<?php echo esc_attr($bus_id); ?>"
                 data-same-bus-return="<?php echo WBTM_Functions::is_same_bus_return_enabled($bus_id) ? '1' : '0'; ?>"
                 data-bp-time="<?php echo esc_attr($all_info['bp_time']); ?>"
                 data-departure="<?php echo esc_attr((int) $bp_ts); ?>"
                 data-price="<?php echo $route_priced ? esc_attr((float) $price) : ''; ?>"
                 data-duration="<?php echo esc_attr((int) $dur_s); ?>">

                <!-- Hidden fields required by JS/cart -->
                <input type="hidden" name="wbtm_bus_name" value="<?php echo esc_attr(get_the_title($bus_id)); ?>" />
                <input type="hidden" name="wbtm_bus_type" value="<?php echo esc_attr($bus_type); ?>" />
                <?php foreach ((array)$bus_boarding_routes as $boarding_route) : if (!$boarding_route) continue; ?>
                <input type="hidden" name="wbtm_bus_start_route" value="<?php echo esc_attr($boarding_route); ?>" />
                <?php endforeach; ?>

                <!-- ── 3-column card ──────────────────────────────── -->
                <div class="wbtm-card-wrap">

                    <!-- LEFT: bus / boat photo (restored in 5.9.x; the modern
                         card rewrite dropped the per-departure thumbnail) -->
                    <div class="wbtm-card-photo">
                        <?php WBTM_Functions::logo_thumbnail_display($bus_id); ?>
                        <?php
                            // Bus / boat type (the wbtm_bus_cat term stored on the post).
                            // The badge styles existed but nothing ever rendered them, so
                            // the type a customer filters by was invisible on the results.
                            $wbtm_card_type = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category');
                        ?>
                        <?php if ($wbtm_card_type !== '') : ?>
                            <span class="wbtm-type-badge"><?php echo esc_html($wbtm_card_type); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Operator name + vehicle number: the card's heading line, above
                         the journey strip. Its own grid item rather than the first row
                         of .wbtm-card-info, because the journey strip sits between it
                         and the rest of the bus info. -->
                    <?php $wbtm_bus_no = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_no'); ?>
                    <div class="wbtm-card-head">
                        <div class="wbtm-operator-row">
                            <span class="wbtm-operator-name _textTheme"
                                  data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>">
                                <?php echo esc_html(get_the_title($bus_id)); ?>
                            </span>
                            <?php if ($wbtm_bus_no !== '') : ?>
                            <div class="wbtm-coach-number">
                                <span><?php echo esc_html(WBTM_Translations::text_bus_no()); ?>:</span>
                                <strong><?php echo esc_html($wbtm_bus_no); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- times + duration visual -->
                    <div class="wbtm-card-times">
                        <div class="wbtm-time-depart">
                            <?php echo esc_html(date_i18n('H:i', $bp_ts)); ?>
                        </div>
                        <div class="wbtm-city-depart">
                            <?php echo esc_html($all_info['bp']); ?>
                        </div>
                        <div class="wbtm-duration-track-wrap">
                            <div class="wbtm-track-dot"></div>
                            <div class="wbtm-track-line"></div>
                            <div class="wbtm-track-duration"><?php echo esc_html($duration_formatted); ?></div>
                            <div class="wbtm-track-dot"></div>
                        </div>
                        <div class="wbtm-time-arrive">
                            <?php echo esc_html(date_i18n('H:i', $dp_ts)); ?>
                            <?php if ($next_day == '1') : ?>
                                <span style="font-size:11px;color:#e8510f">+1</span>
                            <?php endif; ?>
                        </div>
                        <div class="wbtm-city-arrive">
                            <?php echo esc_html($all_info['dp']); ?>
                        </div>
                    </div>

                    <!-- MIDDLE: bus name, badge, amenities, seats, popup links -->
                    <div class="wbtm-card-info">
                        <?php if (!empty($feature_list)) : ?>
                        <div class="wbtm-amenities">
                            <?php foreach ($feature_list as $feat) :
                                $feat_name = $feat['name'] ?? ($feat['label'] ?? '');
                                $feat_icon = $feat['icon'] ?? '';
                            ?>
                            <span class="wbtm-amenity">
                                <?php if ($feat_icon) : ?>
                                    <i class="<?php echo esc_attr($feat_icon); ?>"></i>
                                <?php endif; ?>
                                <?php echo esc_html($feat_name); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Popup links + extra details tabs -->
                        <div class="wbtm_bus_details_tabs_holder<?php echo esc_attr($details_tabs_class); ?>">
                            <?php
                            if ($show_details_tabs !== 'hide') {
                                echo wp_kses_post(WBTM_Functions::single_bus_details_popup_tabs($bus_id, $popup_tabs));
                            }
                            if ($btn_show == 'hide' && $all_info['regi_status'] == 'no') {
                                WBTM_Layout::trigger_view_seat_details();
                            }
                            ?>
                        </div>
                    </div>

                    <!-- RIGHT: price + Book Seat button -->
                    <div class="wbtm-card-price">
                        <?php if (!$route_priced) : ?>
                            <div class="wbtm-route-unavailable">
                                <i class="fas fa-circle-info" aria-hidden="true"></i>
                                <span><?php echo esc_html($wbtm_unpriced_msg); ?></span>
                            </div>
                        <?php elseif ($is_sold_out) : ?>
                            <div class="wbtm-soldout-badge">
                                <?php esc_html_e('Sold Out', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                        <?php else : ?>
                            <div class="wbtm-starting-from">
                                <?php esc_html_e('Starting from', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                            <div class="wbtm-price-value">
                                <?php echo wp_kses_post(WBTM_Global_Function::format_price($price)); ?>
                            </div>
                        <?php endif; ?>

                        <?php // Seat availability reads with the price and the Book button, not among the amenities. ?>
                        <div class="wbtm-seats-avail">
                            <?php echo esc_html($all_info['available_seat']); ?>
                            <?php esc_html_e('seats available', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </div>
                        <?php if ($route_priced) : ?>
                        <div class="wbtm-seat-book <?php echo esc_html($btn_show); ?>">
                            <?php echo WBTM_Functions::full_bus_booking_button($bus_id, $all_info, $date, $wbtm_price_leg); ?>
                            <button type="button"
                                    class="_themeButton_xs<?php echo esc_attr($is_sold_out ? ' wbtm-disabled' : ''); ?>"
                                <?php echo $is_sold_out ? 'disabled' : ''; ?>
                                    id="get_wbtm_bus_details"
                                    data-bus_id="<?php echo esc_attr($bus_id); ?>"
                                    data-price-leg="<?php echo esc_attr($wbtm_price_leg); ?>"
                                    data-open-text="<?php esc_attr_e('Book Seat', 'bus-ticket-booking-with-seat-reservation'); ?>"
                                    data-close-text="<?php echo esc_attr(WBTM_Translations::text_close_seat()); ?>"
                                    data-add-class="mActive">
                                <span data-text>
                                    <?php esc_html_e('Book Seat', 'bus-ticket-booking-with-seat-reservation'); ?>
                                    <i class="fas fa-long-arrow-alt-right"></i>
                                </span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                </div><!-- /.wbtm-card-wrap -->

            </div><!-- /.wbtm-bus-list -->

            <!-- Seat plan expands here (must stay directly after .wbtm-bus-list) -->
            <div class="wbtm_bus_details mT_xs" data-row_id="<?php echo esc_attr($bus_id); ?>">
                <!-- seat plan loads here via JS -->
            </div>

        <?php endforeach; ?>

        <?php if ($bus_count === 0) : ?>
            <div><?php WBTM_Layout::msg(WBTM_Translations::text_no_bus()); ?></div>
        <?php endif; ?>

    </div><!-- /.wbtm_bus_list_area -->
</div><!-- /.wbtm_search_result_holder -->

<?php
} else {
    WBTM_Layout::msg(WBTM_Translations::text_no_bus());
}
//echo '<pre>';	print_r($bus_ids);	echo '</pre>';
