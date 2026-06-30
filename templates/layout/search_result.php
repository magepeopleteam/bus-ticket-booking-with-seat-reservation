<?php
/*
* @Author 		engr.sumonazma@gmail.com
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
    foreach ($bus_ids as $bus_id) {
        $wbtm_price_leg = WBTM_Functions::resolve_price_leg_for_od_pair(
            $bus_id,
            $start_route,
            $end_route,
            ( $journey_type === 'return_journey' ) ? 'return' : 'outbound'
        );
       
        $all_info = WBTM_Functions::get_bus_all_info($bus_id, $date, $start_route, $end_route, $wbtm_price_leg);
        if (sizeof($all_info) > 0) {
           
            $bus_data[] = [
                'bus_id'   => $bus_id,
                'all_info' => $all_info,
                'price_leg' => $wbtm_price_leg,
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
   Only shown for round-trip searches (when a return tab actually exists).
   A <span class="wbtm-step-connector"> is inserted between the two tabs in PHP. */
.wbtm_bus_tab_wrapper {
    display: none !important;
}
.wbtm_bus_tab_wrapper:has(.wtbm_return_route) {
    display:         flex !important;
    align-items:     center;
    justify-content: center;
    gap:             0;
    padding:         14px 24px;
    background:      #fff;
    border:          1px solid #e8ecf0;
    border-radius:   12px;
    margin-bottom:   16px;
    box-shadow:      0 1px 4px rgba(0,0,0,.04);
}
.wbtm_bus_tab_wrapper .wtbm_start_route,
.wbtm_bus_tab_wrapper .wtbm_return_route {
    display:     flex;
    align-items: center;
    gap:         10px;
    font-size:   14px;
    font-weight: 600;
    color:       #9ca3af;
    cursor:      pointer;
    background:  none;
    border:      none;
    padding:     6px 0;
    white-space: nowrap;
    flex:        1;
    transition:  color 0.15s;
}
/* Numbered circle */
.wbtm_bus_tab_wrapper .wtbm_start_route::before,
.wbtm_bus_tab_wrapper .wtbm_return_route::before {
    content:         '1';
    display:         flex;
    align-items:     center;
    justify-content: center;
    width:           26px;
    height:          26px;
    border-radius:   50%;
    background:      #e9eaf0;
    color:           #9ca3af;
    font-size:       12px;
    font-weight:     700;
    flex-shrink:     0;
    transition:      background 0.15s, color 0.15s;
}
.wbtm_bus_tab_wrapper .wtbm_return_route::before { content: '2'; }
.wbtm_bus_tab_wrapper .wtbm_start_route { justify-content: flex-end; }
div#wbtm_date_start_route { height: 50px; }

/* Dotted connector — a real <span> inserted by PHP so it sits between the two tabs */
.wbtm_bus_tab_wrapper .wbtm-step-connector {
    flex:       0 0 100px;
    height:     0;
    border-top: 2px dashed #d1d5db;
    margin:     0;
    align-self: center;
}

/* Active step: dark navy circle + bold dark text */
@media (min-width: 0px) {
    .wbtm_bus_tab_wrapper .wbtm_tab_active {
        color:      #111827;
        box-shadow: none;
    }
}
.wbtm_bus_tab_wrapper .wbtm_tab_active::before {
    background: #16213e;
    color:      #fff;
}

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

/* ── Route summary card — hidden ────────────────────────────────── */
.wbtm_search_route_container { display: none !important; }
.wbtm_search_route_container.__unused {
    display:         flex !important;
    align-items:     center !important;
    gap:             24px !important;
    background:      #fff !important;
    border:          1px solid #e8ecf0 !important;
    border-radius:   12px !important;
    padding:         16px 22px !important;
    margin-bottom:   16px !important;
    box-shadow:      0 1px 6px rgba(0,0,0,.05) !important;
}

/* Left block: label + date + day stacked */
.wbtm_search_route_return_date {
    display:        flex;
    flex-direction: column;
    gap:            2px;
    min-width:      110px;
    border-right:   1px solid #e8ecf0;
    padding-right:  22px;
}
.wbtm_search_route_label {
    font-size:      10px;
    font-weight:    700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color:          var(--wbtm_color_theme, #e8510f);
    margin-bottom:  2px;
}
.wbtm_search_route_date {
    font-size:   14px;
    font-weight: 700;
    color:       #111;
    line-height: 1.2;
}
.wbtm_search_route_day {
    font-size: 12px;
    color:     #888;
}

/* Centre block: city A ── bus ──▶ city B */
.wbtm_search_route_cities_wrapper {
    display:     flex;
    align-items: center;
    gap:         0;
    flex:        1;
}
.wbtm_search_route_city_section {
    display:        flex;
    flex-direction: column;
    align-items:    flex-start;
    gap:            2px;
}
.wbtm_search_route_city_section_right { align-items: flex-end; }
.wbtm_search_route_city {
    font-size:   18px;
    font-weight: 700;
    color:       #111;
    line-height: 1;
}
.wbtm_search_route_airport_code {
    font-size:      11px;
    font-weight:    600;
    color:          #aaa;
    letter-spacing: .5px;
}

/* Bus icon + connecting line between the two cities */
.wbtm_search_route_icon_wrapper {
    flex:            1;
    display:         flex;
    align-items:     center;
    justify-content: center;
    position:        relative;
    padding:         0 14px;
}
.wbtm_search_route_icon_wrapper::before,
.wbtm_search_route_icon_wrapper::after {
    content:    '';
    position:   absolute;
    top:        50%;
    height:     1px;
    width:      calc(50% - 20px);
    background: repeating-linear-gradient(90deg, #ccc 0, #ccc 4px, transparent 4px, transparent 8px);
}
.wbtm_search_route_icon_wrapper::before { left: 0; }
.wbtm_search_route_icon_wrapper::after  { right: 0; }
.wbtm_search_route_bus_icon {
    width:           36px;
    height:          36px;
    border-radius:   50%;
    background:      var(--wbtm_color_theme, #e8510f);
    display:         flex;
    align-items:     center;
    justify-content: center;
    color:           #fff;
    font-size:       15px;
    flex-shrink:     0;
    z-index:         1;
    position:        relative;
}
/* hide the dropdown arrow — not needed in this layout */
.wbtm_search_route_dropdown_icon { display: none !important; }

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
    gap:        5px;
}
.wbtm-sort-label {
    font-weight: 600;
    color:       #222;
    cursor:      pointer;
    display:     flex;
    align-items: center;
    gap:         4px;
}
.wbtm-sort-label i { font-size: 11px; }

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

/* 3-column card row */
.wbtm-card-wrap {
    display:     flex;
    align-items: stretch;
    min-height:  110px;
}

/* ── LEFT: times + duration track ───────────────────────────────── */
.wbtm-card-times {
    flex:                  0 0 340px;
    display:               grid;
    grid-template-columns: auto 1fr auto;
    grid-template-rows:    auto auto;
    align-items:           center;
    align-content:         center;
    row-gap:               0;
    column-gap:            10px;
    padding:               20px 18px;
    background:            #ffffff;
    border-right:          1px solid #e3e5e9;
}
.wbtm-time-depart,
.wbtm-time-arrive {
    font-size:   20px;
    font-weight: 800;
    color:       #0f172a;
    line-height: 1;
    letter-spacing: -0.5px;
}
.wbtm-time-depart { grid-column: 1; grid-row: 1; }
.wbtm-time-arrive { grid-column: 3; grid-row: 1; text-align: right; }
.wbtm-city-depart {
    grid-column: 1;
    grid-row:    2;
    font-size:   11px;
    font-weight: 500;
    color:       #94a3b8;
    margin-top:  3px;
    letter-spacing: 0.2px;
}
.wbtm-city-arrive {
    grid-column: 3;
    grid-row:    2;
    font-size:   11px;
    font-weight: 500;
    color:       #94a3b8;
    margin-top:  3px;
    text-align:  right;
    letter-spacing: 0.2px;
}
.wbtm-duration-track-wrap {
    grid-column:     2;
    grid-row:        1 / span 2;
    position:        relative;
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    gap:             3px;
    min-width:       70px;
}
.wbtm-track-duration {
    position:      absolute;
    bottom:        100%;
    left:          50%;
    transform:     translateX(-50%);
    margin-bottom: 4px;
    font-size:     10px;
    font-weight:   700;
    color:         var(--wbtm_color_theme, #e8510f);
    white-space:   nowrap;
    line-height:   1;
    background:    #fff3ee;
    padding:       2px 6px;
    border-radius: 20px;
}
.wbtm-track-dot {
    width:         7px;
    height:        7px;
    border-radius: 50%;
    background:    #fff;
    border:        2px solid #c8d0dc;
    flex-shrink:   0;
}
.wbtm-track-line {
    position:   relative;
    width:      100%;
    height:     0;
    min-width:  30px;
    border-top: 2px dashed #c8d0dc;
}
.wbtm-track-line::after {
    content:         '\f207';
    font-family:     'Font Awesome 5 Free';
    font-weight:     900;
    position:        absolute;
    top:             50%;
    left:            50%;
    transform:       translate(-50%, -50%);
    width:           22px;
    height:          22px;
    border-radius:   50%;
    background:      var(--wbtm_color_theme, #e8510f);
    display:         flex;
    align-items:     center;
    justify-content: center;
    color:           #fff;
    font-size:       9px;
    line-height:     22px;
    box-shadow:      0 2px 6px rgba(232,81,15,.35);
}

/* ── MIDDLE: bus info ────────────────────────────────────────────── */
.wbtm-card-info {
    flex:           1;
    padding:        18px 15px;
    display:        flex;
    flex-direction: column;
    gap:            8px;
    border-right:   1px solid #eaecf2;
}
.wbtm-operator-row {
    display:     flex;
    align-items: center;
    gap:         8px;
    flex-wrap:   wrap;
}
.wbtm-operator-name {
    font-size:   15px;
    font-weight: 700;
    color:       #0f172a;
    cursor:      pointer;
}
.wbtm-type-badge {
    font-size:     10px;
    font-weight:   700;
    padding:       3px 10px;
    border-radius: 20px;
    white-space:   nowrap;
    letter-spacing: 0.3px;
    text-transform: uppercase;
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
.wbtm-amenity {
    display:       inline-flex;
    align-items:   center;
    gap:           5px;
    font-size:     11px;
    font-weight:   500;
    color:         #475569;
    background:    #f1f5f9;
    padding:       3px 9px;
    border-radius: 20px;
    border:        1px solid #e2e8f0;
}
.wbtm-amenity i {
    color:     #64748b;
    font-size: 11px;
}
.wbtm-seats-avail {
    display:     inline-flex;
    align-items: center;
    gap:         6px;
    font-size:   12px;
    font-weight: 600;
    color:       #15803d;
    background:  #f0fdf4;
    padding:     4px 10px;
    border-radius: 20px;
    align-self:  flex-start;
    border:      1px solid #bbf7d0;
}
.wbtm-seats-avail i { color: #16a34a; font-size: 11px; }
.wbtm-card-info .wbtm_bus_details_tabs_holder {
    margin-top:  auto;
    padding-top: 8px;
    border-top:  1px dashed #e8ecf0;
}
.wbtm-card-info .wbtm_bus_popup_links {
    display:    flex !important;
    flex-wrap:  wrap;
    gap:        6px;
    visibility: visible !important;
    opacity:    1 !important;
    transform:  none !important;
}
.wbtm-card-info .wbtm_bus_popup_link {
    font-size:     11px;
    font-weight:   500;
    color:         #475569;
    background:    #f8fafc;
    border:        1px solid #e2e8f0;
    border-radius: 6px;
    padding:       3px 10px;
    cursor:        pointer;
    transition:    background 0.15s, color 0.15s;
}

/* ── RIGHT: price + book ─────────────────────────────────────────── */
.wbtm-card-price {
    flex:            0 0 325px;
    padding:         18px 20px;
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    gap:             8px;
    background:      #fafbfd;
}
.wbtm-starting-from {
    font-size:     10px;
    font-weight:   600;
    color:         #94a3b8;
    text-align:    center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.wbtm-price-value {
    font-size:   28px;
    font-weight: 800;
    color:       #0f172a;
    text-align:  center;
    line-height: 1;
    letter-spacing: -1px;
}
.wbtm-price-value .woocommerce-Price-amount {
    font-size: inherit !important;
    color:     inherit !important;
}
.wbtm-card-price .wbtm-seat-book {
    width:      100%;
    margin-top: 2px;
}
.wbtm-card-price .wbtm-seat-book._themeButton_xs,
.wbtm-card-price ._themeButton_xs,
.wbtm-card-price #get_wbtm_bus_details {
    width:           100% !important;
    border-radius:   50px !important;
    padding:         11px 18px !important;
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
    box-shadow:      0 3px 10px rgba(232,81,15,.3) !important;
    transition:      box-shadow 0.15s, opacity 0.15s !important;
}

/* Seat-expansion area */
.wbtm_bus_details { border-top: 1px solid #eaecf2; }


/* ── Mobile ─────────────────────────────────────────────────────── */
@media (max-width: 767px) {
    .wbtm_search_result_holder  { flex-direction: column; }
    .wbtm_bus_left_filter_holder { flex: none; width: 100%; }
    .wbtm-card-wrap { flex-direction: column; }
    .wbtm-card-times {
        border-right:  none;
        border-bottom: 1px solid #eaecf2;
        padding:       14px 16px;
        column-gap:    8px;
        background:    #f9fafb;
    }
    .wbtm-time-depart, .wbtm-time-arrive { font-size: 17px; }
    .wbtm-duration-track-wrap { min-width: 40px; }
    .wbtm-card-info  { border-right: none; border-bottom: 1px solid #eaecf2; }
    .wbtm-card-price { flex: none; flex-direction: row; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 16px; background: #fafbfd; }
    .wbtm-card-price .wbtm-seat-book { width: auto; margin-top: 0; }
    .wbtm-card-price #get_wbtm_bus_details { width: auto !important; padding: 10px 20px !important; }
}
</style>

<div class="wbtm_search_result_holder">
    <?php
    // Always show the filter sidebar when there are buses — the redesigned
    // form forces wbtm_left_filter_show=on, but guard with bus count anyway.
    $has_left_filter = count($bus_titles) > 0;

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
        <div class="wbtm-filter-card">

            <!-- Header -->
            <div class="wbtm-filter-header">
                <span class="wbtm-filter-header-title"><?php esc_html_e('Filters', 'bus-ticket-booking-with-seat-reservation'); ?></span>
                <span class="wbtm-filter-reset-btn wbtm_reset_filter-checkbox">
                    <?php esc_html_e('Reset', 'bus-ticket-booking-with-seat-reservation'); ?>
                </span>
            </div>

            <!-- Departure Time -->
            <?php if (!empty($wbtm_time_buckets)) : ?>
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
            <?php if (!empty($unique_bus_types)) : ?>
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
            <?php if (!empty($bus_titles)) : ?>
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
            <?php if (!empty($all_boarding_routes)) : ?>
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

            <!-- Member Discount promo card -->
            <div class="wbtm-member-promo">
                <p class="wbtm-member-promo-title"><?php esc_html_e('Member Discount', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                <p class="wbtm-member-promo-desc">
                    <?php
                    if ($end_route) {
                        /* translators: %s: destination city */
                        printf(esc_html__('Save up to 15%% on your first trip to %s.', 'bus-ticket-booking-with-seat-reservation'), esc_html($end_route));
                    } else {
                        esc_html_e('Save up to 15% on your first trip.', 'bus-ticket-booking-with-seat-reservation');
                    }
                    ?>
                </p>
                <a href="#" class="wbtm-member-promo-btn"><?php esc_html_e('Join Now', 'bus-ticket-booking-with-seat-reservation'); ?></a>
            </div>

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
            <div class="wbtm-list-sort">
                <?php esc_html_e('Sort by', 'bus-ticket-booking-with-seat-reservation'); ?>:
                <span class="wbtm-sort-label">
                    <?php esc_html_e('Earliest First', 'bus-ticket-booking-with-seat-reservation'); ?>
                    <i class="fas fa-chevron-down"></i>
                </span>
            </div>
        </div>

        <?php foreach ($bus_data as $key => $bus) :
            $bus_id       = $bus['bus_id'];
            $popup_tabs   = WBTM_Functions::single_bus_details_tabs_filtered($bus_id);
            $all_info     = $bus['all_info'];
            $wbtm_price_leg = $bus['price_leg'] ?? $wbtm_price_leg;
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
        ?>

            <div class="wbtm-bus-list wtbm_bus_counter <?php echo esc_attr($wbtm_bus_search); echo esc_attr(WBTM_Global_Function::check_product_in_cart($bus_id) ? ' in_cart' : ''); ?>"
                 id="wbtm_bust_list"
                 data-bus-id="<?php echo esc_attr($bus_id); ?>"
                 data-same-bus-return="<?php echo WBTM_Functions::is_same_bus_return_enabled($bus_id) ? '1' : '0'; ?>"
                 data-bp-time="<?php echo esc_attr($all_info['bp_time']); ?>">

                <!-- Hidden fields required by JS/cart -->
                <input type="hidden" name="wbtm_bus_name" value="<?php echo esc_attr(get_the_title($bus_id)); ?>" />
                <input type="hidden" name="wbtm_bus_type" value="<?php echo esc_attr($bus_type); ?>" />
                <?php foreach ((array)$bus_boarding_routes as $boarding_route) : if (!$boarding_route) continue; ?>
                <input type="hidden" name="wbtm_bus_start_route" value="<?php echo esc_attr($boarding_route); ?>" />
                <?php endforeach; ?>

                <!-- ── 3-column card ──────────────────────────────── -->
                <div class="wbtm-card-wrap">

                    <!-- LEFT: times + duration visual -->
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
                        <div class="wbtm-operator-row">
                            <span class="wbtm-operator-name _textTheme"
                                  data-href="<?php echo esc_attr(get_the_permalink($bus_id)); ?>">
                                <?php echo esc_html(get_the_title($bus_id)); ?>
                            </span>
                            <span class="wbtm-type-badge <?php echo $bus_type === 'AC' ? 'wbtm-type-badge--top' : 'wbtm-type-badge--std'; ?>">
                                <?php echo $bus_type === 'AC'
                                    ? esc_html__('Top Rated', 'bus-ticket-booking-with-seat-reservation')
                                    : esc_html__('Standard', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </span>
                        </div>

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

                        <div class="wbtm-seats-avail">
                            <i class="fas fa-chair"></i>
                            <?php echo esc_html($all_info['available_seat']); ?>
                            <?php esc_html_e('seats available', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </div>

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
                        <div class="wbtm-starting-from">
                            <?php esc_html_e('Starting from', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </div>
                        <div class="wbtm-price-value">
                            <?php echo wp_kses_post(wc_price($price)); ?>
                        </div>
                        <div class="wbtm-seat-book <?php echo esc_html($btn_show); ?>">
                            <?php echo WBTM_Functions::full_bus_booking_button($bus_id, $all_info, $date, $wbtm_price_leg); ?>
                            <button type="button"
                                    class="_themeButton_xs"
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
