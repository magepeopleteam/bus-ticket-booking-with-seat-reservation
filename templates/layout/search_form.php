<?php
	/*
* @Author 		MagePeople Team
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	//================//
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$post_id = $post_id ?? 0;
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$params = $params ?? [];
//	wbtm_load_search_form($post_id, $params);
//	function wbtm_load_search_form($post_id, $params) {

		$style = array_key_exists('style', $params) ? $params['style'] : '';
		$form_style = array_key_exists('style', $params) ? $params['style'] : '';
		$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
//		$left_filter = array_key_exists('left_filter_input', $params) ? $params['left_filter_input'] : '';
		$left_filter = array_key_exists('left_filter', $params) ? $params['left_filter'] : '';
		if (is_page()) {
			$left_filter = $left_filter ?: 'on';
		} else {
			$left_filter = 'off';
		}

		$left_filter_type = array_key_exists('left_filter_type', $params) && $params['left_filter_type']? $params['left_filter_type'] : 'on';
		$left_filter_operator = array_key_exists('left_filter_operator', $params) && $params['left_filter_operator']? $params['left_filter_operator'] : 'on';
		$left_filter_boarding = array_key_exists('left_filter_boarding', $params) && $params['left_filter_boarding']? $params['left_filter_boarding'] : 'on';
        //====================//
		$start_route = '';
		$end_route = '';
		$start_time = '';
		$end_time = '';
		// Allow processing of GET parameters for direct links (shareable URLs)
	// OR when nonce is present and valid (form submissions)
	$has_search_params = isset($_GET['bus_start_route']) || isset($_GET['bus_end_route']) || isset($_GET['j_date']);
	$nonce_valid = isset($_GET['wbtm_form_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['wbtm_form_nonce'])), 'wbtm_form_nonce');
	
	if ($nonce_valid || $has_search_params) {

			$start_route = isset($_POST['wbtm_bp_place']) ? sanitize_text_field(wp_unslash($_POST['wbtm_bp_place'])) : '';
			$start_route = $start_route ?: (isset($_GET['bus_start_route']) ? sanitize_text_field(wp_unslash($_GET['bus_start_route'])) : '');
			//===============//
			$start_time = isset($_POST['j_date']) ? sanitize_text_field(wp_unslash($_POST['j_date'])) : '';
			$start_time = $start_time ?: (isset($_GET['j_date']) ? sanitize_text_field(wp_unslash($_GET['j_date'])) : '');
			$start_time = $start_time ? gmdate('Y-m-d', strtotime($start_time)) : '';
			//===============//
			$end_route = isset($_POST['wbtm_dp_place']) ? sanitize_text_field(wp_unslash($_POST['wbtm_dp_place'])) : '';
			$end_route = $end_route ?: (isset($_GET['bus_end_route']) ? sanitize_text_field(wp_unslash($_GET['bus_end_route'])) : '');
			//===============//
			$end_time = isset($_POST['r_date']) ? sanitize_text_field(wp_unslash($_POST['r_date'])) : '';
			$end_time = $end_time ?: (isset($_GET['r_date']) ? sanitize_text_field(wp_unslash($_GET['r_date'])) : '');
			$end_time = $end_time ? gmdate('Y-m-d', strtotime($end_time)) : '';


            $left_filter = isset($_GET['wbtm_left_filter_show']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_show'])) : 'off';
            $left_filter_type = isset($_GET['wbtm_left_filter_type']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_type'])) : 'on';
            $left_filter_operator = isset($_GET['wbtm_left_filter_operator']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_operator'])) : 'on';
            $left_filter_boarding = isset($_GET['wbtm_left_filter_boarding']) ? sanitize_text_field(wp_unslash($_GET['wbtm_left_filter_boarding'])) : 'on';
			//===============//
		}
		$return_date_show = WBTM_Global_Function::get_settings('wbtm_general_settings', 'bus_return_show', 'enable');
		$buy_ticket_text = WBTM_Translations::text_buy_ticket();
		$placeholder_text = WBTM_Translations::text_please_select();
		$global_settings = get_option('wbtm_general_settings');
		$btn_show = (is_array($global_settings) && array_key_exists('show_hide_view_seats_button', $global_settings)) ? $global_settings['show_hide_view_seats_button'] : 'show';
		/****************************/
		$active_redirect_page = WBTM_Global_Function::get_settings('wbtm_general_settings', 'active_redirect_page', 'off');
		$search_page_redirect = WBTM_Global_Function::get_settings('wbtm_general_settings', 'search_page_redirect');
		$redirect_url = $active_redirect_page == 'on' && $search_page_redirect && $post_id == 0 ? get_home_url() . '/' . get_page_uri($search_page_redirect) : '';
		$redirect_url = is_admin() ? '' : $redirect_url;
		/*********************************/
		$search_info['bus_start_route'] = $start_route;
		$search_info['bus_end_route'] = $end_route;
		$search_info['j_date'] = $start_time;
		$search_info['r_date'] = $end_time;



        $left_filter_show = array(
            'left_filter_input'     => $left_filter,
            'left_filter_type'     => $left_filter_type,
            'left_filter_operator' => $left_filter_operator,
            'left_filter_boarding' => $left_filter_boarding,
        );
		?>
        <div id="wbtm_area">
            <style>
            /* ============================================================
               WBTM Pill-Bar Search Form — scoped to .wbtm-bar-redesign
               All rules prefixed with #wbtm_area .wbtm-bar-redesign so
               they never bleed into other plugin areas.
               ============================================================ */

            /* Outer wrapper */
            #wbtm_area .wbtm_search_area.wbtm-bar-redesign {
                background: transparent;
                padding: 0;
                /* Also clear the classic (non-redesign) skin's own card look
                   from assets/frontend/wbtm.css (.wbtm_style .wbtm_search_area
                   { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }),
                   which this redesign skin didn't reset — it rendered behind
                   the pill container's own shadow/radius below as a faint
                   rectangular echo poking out at the corners. */
                border-radius: 0;
                box-shadow: none;
            }
            #wbtm_area .wbtm-bar-redesign h4 {
                display: none;
            }

            /* ── Pill container ──────────────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_input_fields_holder {
                display:      flex;
                flex-wrap:    nowrap;
                align-items:  stretch;
                background:   #ffffff;
                border-radius: 10px;
                border:       1.5px solid #dde1e7;
                box-shadow:   0 2px 16px rgba(0,0,0,.09);
                min-height:   64px;
                padding:      0;
            }

            /* ── Row groups ──────────────────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wbtm_input_fields_holder,
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location,
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_date {
                display:        flex;
                flex-direction: row;
                align-items:    stretch;
                flex:           1;
                min-width:      0;
            }
            /* Positioning context for the swap toggle below, which floats over
               the From/To divider instead of taking up flex space itself. */
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location {
                position: relative;
            }

            /* ── Each field segment ──────────────────────────────── */
            /* wbtm.css's own base rule (`.wtbm_inputList{margin:0 5px 0 0}`,
               unscoped, applies everywhere incl. here) was never overridden
               by this redesign block, so the From field kept an extra 5px
               margin-right the swap toggle's absolute centering above didn't
               account for -- its true right edge sat 5px left of the
               container's midpoint, reopening a small gap next to the
               toggle. margin:0 here removes it without touching wbtm.css's
               own rule, so any other (non-redesign) use of .wtbm_inputList
               elsewhere in the plugin is unaffected. */
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList {
                flex:         1;
                min-width:    110px;
                position:     relative;
                padding:      30px 40px;
                margin:       0;
                border-right: 1.5px solid #dde1e7;
                display:      flex;
                align-items:  center;
                cursor:       pointer;
                transition:   background 0.15s ease;
            }
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList:last-child {
                border-right: none;
            }
            #wbtm_area .wbtm-bar-redesign .wtbm_inputList:hover {
                background: #f5f7fb;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location .wtbm_inputList:first-child:hover {
                border-radius: 9px 0 0 9px;
            }

            /* ── Field label ("From", "Journey Date" …) ──────────── */
            #wbtm_area .wbtm-bar-redesign label.wtbm_fdColumn {
                display:        flex;
                flex-direction: column;
                width:          100%;
                font-size:      11px;
                font-weight:    700;
                color:          #777;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                line-height:    1.2;
                cursor:         pointer;
                margin:         0;
            }
            /* "Return Date (Optional)" is longer than every other field's label
               ("From", "Journey Date" …) at the same size/column width, so it
               alone wrapped onto a second line. Keep it on one line -- the
               main "Return Date" text stays the same size as every other
               label; only the "(Optional)" suffix (its own span, split out in
               WBTM_Layout::return_date_picker()) shrinks, same idea as a
               form's "(optional)" hint text elsewhere. */
            #wbtm_area .wbtm-bar-redesign .wbtm_return_date label.wtbm_fdColumn {
                white-space: nowrap;
            }
            /* mp_global's generic form skin (wbtm_plugin_global.css:
               `.wbtm_style .mpForm label span { width:100% }`) styles every
               <span> inside a <label> under this form -- meant for other
               label+span uses elsewhere, but it also matches the
               "(Optional)" wrapper span from WBTM_Layout::return_date_picker()
               (the only field label that's a span rather than plain text).
               Its margin-bottom (which used to push Return Date's calendar
               row lower than every other field's) was removed at the source
               since nothing else in the plugin used it; width:100% is
               harmless for other uses but still reset here since this span
               is meant to size to its own text, not fill the column. */
            #wbtm_area .wbtm-bar-redesign .wtbm_field_label_text {
                width:       auto;
                line-height: 1.2;
            }
            /* A nested inline element at a SMALLER font-size than its
               surroundings still inflates the shared line box's height
               beyond either font's own line-height on its own -- baseline
               alignment needs extra room to fit both the larger font's
               ascent and the smaller font's descent (vertical-align:middle
               alone only softens this, doesn't remove it). That's what was
               still making Return Date's label ~6-8px taller than the other
               fields' single-size labels, which in turn pushed its calendar
               row down whenever .wtbm_inputList vertically centers the whole
               label column. Scaling the glyphs down with a `transform`
               instead of a smaller `font-size` sidesteps the problem
               entirely: transform is a paint-time effect that never
               participates in line-box/layout height calculations, so this
               span still measures as the same 11px text everything else on
               the line does. */
            #wbtm_area .wbtm-bar-redesign .wtbm_field_label_suffix {
                display:          inline-block;
                transform:        scale(.82);
                transform-origin: left center;
                font-weight:      600;
                color:            #999;
            }

            /* ── Icon + value row ────────────────────────────────── */
            /* wbtm.css sets .wbtm_search_area .marker i { position: absolute; left: 10px }
               and compensates with input { padding-left: 30px }.
               We reset both here so the flex gap controls spacing instead. */
            #wbtm_area .wbtm-bar-redesign .marker,
            #wbtm_area .wbtm-bar-redesign .calendar {
                display:     flex !important;
                align-items: center !important;
                gap:         7px !important;
                margin-top:  5px;
                font-size:   15px;
                color:       #1a1a1a;
            }
            /* .calendar never receives the loading spinner below, so it stays
               static; .marker does (wbtm_plugin_global.js's wbtm_loader_xs()
               appends it into "From"/"To"'s own .marker while the city list
               loads via AJAX), so it needs to be ITS positioning context.
               gap trimmed 7px->5px: a selected date's rendered text (e.g.
               "Wed 26 Aug , 2026") can come out 1-2px wider than the field's
               available width depending on which digits appear (proportional
               fonts render "2"/"6"/"8" wider than "1"), clipping the last
               character -- freeing 2px here from the icon gap (which has
               plenty of slack) covers it without touching font-size/weight. */
            #wbtm_area .wbtm-bar-redesign .calendar {
                gap:      5px !important;
                position: static !important;
            }
            /* Was `position:static !important` here too (to defeat wbtm.css's
               absolute-icon layout, same as .calendar above) -- but the
               loading spinner (div.wbtm_loader_xs, itself `position:absolute;
               inset:0`) needs an actually-positioned ancestor to size/center
               itself against. With none available, it escaped all the way up
               to .wtbm_inputList (the next positioned ancestor, the whole
               pill-shaped field) and rendered centred over the entire field
               instead of just this icon+input row -- looking off-centre and
               oversized relative to what's visibly its container. Icons
               inside stay pinned by their own `position:static !important`
               below regardless, so switching this to `relative` doesn't
               reintroduce the old absolute-icon behaviour. */
            #wbtm_area .wbtm-bar-redesign .marker {
                position: relative !important;
            }
            /* Loading spinner (assets/global/wbtm_global.js's wbtm_loader_xs()):
               fills .marker exactly and centres the icon within it now that
               .marker is its real positioning context. Retinted to the site's
               accent instead of the plugin's hard-coded default, and its dark
               `#0003` overlay dropped in favour of a plain white one so it
               reads as "this field is loading", not "this field is disabled". */
            #wbtm_area .wbtm-bar-redesign div.wbtm_loader_xs {
                display:         flex !important;
                align-items:     center !important;
                justify-content: center !important;
                background:      rgba(255,255,255,.85) !important;
                border-radius:   999px;
            }
            /* The plugin's own icon (Font Awesome's fa-spinner glyph + its
               fa-pulse class) animates as a discrete 8-step "tick", not a
               smooth rotation -- reads as choppy/dated. Hide the glyph
               (::before is where FA actually draws it, per its own
               `.fas:before{content:var(--fa)}`) and turn the element itself
               into a plain CSS ring instead: a continuous linear rotation
               reads as noticeably smoother, and it no longer depends on an
               icon font's own glyph metrics at all (recurring source of
               centering bugs elsewhere in this file). */
            #wbtm_area .wbtm-bar-redesign div.wbtm_loader_xs .fa-spinner {
                display:       inline-block !important;
                width:         18px !important;
                height:        18px !important;
                min-width:     18px !important;
                max-width:     18px !important;
                box-sizing:    border-box !important;
                flex-shrink:   0 !important;
                border:        2px solid #f4e0d8 !important;
                border:        2px solid color-mix(in srgb, var(--wbtm_color_theme, #e8510f) 15%, #ffffff) !important;
                border-top-color: var(--wbtm_color_theme, #e8510f) !important;
                border-radius: 50% !important;
                animation:     wbtm-field-spin .7s linear infinite !important;
                font-size:     0 !important;
                color:         transparent !important;
            }
            #wbtm_area .wbtm-bar-redesign div.wbtm_loader_xs .fa-spinner::before {
                content: none !important;
            }
            @keyframes wbtm-field-spin {
                to { transform: rotate(360deg); }
            }
            #wbtm_area .wbtm-bar-redesign .marker > i,
            #wbtm_area .wbtm-bar-redesign .calendar > i {
                position:    static !important;  /* reset from position:absolute */
                top:         auto !important;
                left:        auto !important;
                transform:   none !important;
                font-size:   15px !important;
                color:       #666 !important;
                flex-shrink: 0 !important;
                width:       16px !important;
                text-align:  center !important;
            }

            /* ── Input styled as plain readable text ─────────────── */
            #wbtm_area .wbtm-bar-redesign .formControl {
                border:       0 !important;
                background:   transparent !important;
                padding:      0 !important;         /* removes original 30px-left that accommodated absolute icon */
                padding-left: 0 !important;
                margin:       0 !important;
                font-size:    15px !important;
                font-weight:  600 !important;
                color:        #1a1a1a !important;
                box-shadow:   none !important;
                outline:      none !important;
                height:       auto !important;
                line-height:  1.3 !important;
                width:        100%;
                min-width:    0;
                cursor:       pointer;
            }
            /* Extra safety margin on top of the gap trim above: different
               selected dates land on different digits/month abbreviations
               with slightly different total widths, so shave a fraction off
               letter-spacing here too rather than tune the gap to one exact
               date string. Small enough (-0.2px per character) not to read
               as tightened text, comfortably covers the 1-2px this was
               overflowing by. */
            #wbtm_area .wbtm-bar-redesign .calendar .formControl {
                letter-spacing: -0.2px;
            }
            #wbtm_area .wbtm-bar-redesign .formControl::placeholder {
                color:       #aaa !important;
                font-weight: 400 !important;
            }

            /* ── Swap toggle ⇄ (floats over the From/To divider) ───
               Used to be a normal flex item with its own left/right margin,
               which reserved a strip of dead space between it and each field
               that neither side's own background (incl. :hover) ever
               painted -- visible as a gap around the divider line, worse
               once either field's hover grey stopped right at that margin.
               Absolutely-centering it over the shared border removes that
               reserved space entirely: the two fields now sit directly
               adjacent with nothing between them but their own 1.5px
               border, and the button is just an overlay on top of it. */
            /* wbtm.css's own base rule for this exact selector (line ~1184)
               sets `margin: 9px -4px` -- for an absolutely positioned
               element, margin still offsets it from the top/left position
               it's otherwise placed at, so that -4px horizontal margin was
               shifting this button 4px left of the true centre even after
               overriding top/left/transform here (this rule never touched
               margin, so the legacy value kept applying). margin:0 below
               neutralizes it. */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle {
                position:        absolute;
                top:             50%;
                left:            50%;
                transform:       translate(-50%, -50%);
                margin:          0;
                width:           34px;
                height:          34px;
                border-radius:   50%;
                background:      #fff;
                border:          1.5px solid #dde1e7;
                box-shadow:      0 1px 5px rgba(0,0,0,.09);
                display:         flex;
                align-items:     center;
                justify-content: center;
                cursor:          pointer;
                transition:      background 0.15s;
                z-index:         2;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle:hover {
                background: #f0f2f5;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle i {
                font-size:  13px;
                color:      #555;
                transition: transform 0.25s;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle.rotate i {
                transform: rotate(180deg);
            }

            /* ── Dropdown list — floats above content ─────────────── */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list {
                position:      absolute !important;
                top:           calc(100% + 8px) !important;
                left:          0 !important;
                width:         max-content !important;
                min-width:     285px !important;
                background:    #fff !important;
                border:        1.5px solid #dde1e7 !important;
                border-radius: 14px !important;
                box-shadow:    0 8px 28px rgba(0,0,0,.13) !important;
                z-index:       9999 !important;
                margin:        0 !important;
                padding:       0 !important;
                overflow:      hidden !important;
                max-height:    320px !important;
                overflow-y:    auto !important;
            }
            /* Panel title sticky header via CSS ::before */
            #wbtm_area .wbtm-bar-redesign .wbtm_start_point ul.wbtm_input_select_list::before {
                content:        'Select Boarding Point' !important;
                display:        block !important;
                padding:        13px 16px 11px !important;
                font-size:      11px !important;
                font-weight:    700 !important;
                color:          #6b7280 !important;
                letter-spacing: 0.5px !important;
                text-transform: uppercase !important;
                border-bottom:  1px solid #f1f3f5 !important;
                background:     #fff !important;
                position:       sticky !important;
                top:            0 !important;
                z-index:        1 !important;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_dropping_point ul.wbtm_input_select_list::before {
                content:        'Select Dropping Point' !important;
                display:        block !important;
                padding:        13px 16px 11px !important;
                font-size:      11px !important;
                font-weight:    700 !important;
                color:          #6b7280 !important;
                letter-spacing: 0.5px !important;
                text-transform: uppercase !important;
                border-bottom:  1px solid #f1f3f5 !important;
                background:     #fff !important;
                position:       sticky !important;
                top:            0 !important;
                z-index:        1 !important;
            }
            /* List items — flex row: checkbox + city name + optional tick */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li {
                display:       flex !important;
                align-items:   center !important;
                gap:           12px !important;
                padding:       11px 16px !important;
                font-size:     14px !important;
                color:         #374151 !important;
                border-bottom: 1px solid #f7f8fa !important;
                margin:        0 !important;
                cursor:        pointer !important;
                transition:    background 0.1s !important;
            }
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li:last-child {
                border-bottom: none !important;
            }
            /* Square checkbox on the left */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li::before {
                content:       '' !important;
                display:       inline-block !important;
                width:         16px !important;
                height:        16px !important;
                min-width:     16px !important;
                border:        2px solid #d1d5db !important;
                border-radius: 4px !important;
                background:    #fff !important;
                box-sizing:    border-box !important;
                flex-shrink:   0 !important;
            }
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li:hover {
                background: #f8f9fa !important;
            }
            /* Selected item — filled checkbox + right blue tick badge */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_selected {
                font-weight: 600 !important;
            }
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_selected::before {
                background:   #16213e !important;
                border-color: #16213e !important;
            }
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_selected::after {
                content:         '✓' !important;
                margin-left:     auto !important;
                display:         inline-flex !important;
                align-items:     center !important;
                justify-content: center !important;
                width:           20px !important;
                height:          20px !important;
                min-width:       20px !important;
                border-radius:   50% !important;
                background:      #dbeafe !important;
                color:           #1d4ed8 !important;
                font-size:       11px !important;
                font-weight:     700 !important;
                flex-shrink:     0 !important;
            }

            /* ── Live search / autocomplete states ───────────────── */
            /* The li rule above needs `display:flex !important` for the
               checkbox + city + tick row, and an !important stylesheet
               declaration outranks the inline `display:none` that jQuery's
               filter sets — so non-matching cities could never be hidden.
               Hiding is therefore driven by this class instead. */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_filtered_out {
                display: none !important;
            }
            /* "No match" placeholder — plain text row, no checkbox, not clickable
               (pointer-events:none keeps it out of the shared li click handler). */
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_no_result {
                color:          #9ca3af !important;
                font-style:     italic !important;
                cursor:         default !important;
                pointer-events: none !important;
            }
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_no_result::before,
            #wbtm_area .wbtm-bar-redesign ul.wbtm_input_select_list li.wbtm_city_no_result::after {
                display: none !important;
            }

            .wtbm_inputList.wbtm_input_select.wbtm_dropping_point {
                border-right: 1px solid #e9e5e5 !important;
            }

            /* ── Search button section ───────────────────────────── */
            #wbtm_area .wbtm-bar-redesign .wtbm_bus_search_button_holder {
                display:      flex;
                align-items:  center;
                padding:      6px;
                margin-right: 20px;
                flex-shrink: 0;
            }
            /* Stack both buttons inside the holder; PHP inline style="display:none/block"
               controls which one is visible — we must NOT override display with !important
               or both buttons would show at once. */
            #wbtm_area .wbtm-bar-redesign .search_button_holder {
                display:        flex;
                flex-direction: column;
                align-items:    stretch;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_button_spacer {
                display: none !important;
            }

            /* ── Button holder: vertically center the button ────────── */
            #wbtm_area .wbtm-bar-redesign .search_button_holder {
                justify-content: center !important;
            }
            #wbtm_area .wbtm-bar-redesign .wbtm_search_button_spacer {
                display: none !important;
            }

            /* ── Search / Modify button ───────────────────────────── */
            /* No "display" property here — PHP inline style="display:none/block" controls
               which of the two buttons is shown. We only restyle the visible one. */
            #wbtm_area .wbtm-bar-redesign .wbtm_search_action_button {
                border-radius: 50px !important;
                padding:       13px 26px !important;
                font-size:     15px !important;
                font-weight:   600 !important;
                white-space:   nowrap !important;
                height:        auto !important;
                line-height:   1.3 !important;
                align-items:   center !important;
                gap:           7px !important;
                border:        none !important;
                cursor:        pointer;
                text-align:    center;
            }

            /* ── Tablet / narrow desktop: tighten, do not overflow ──
               The bar is flex-wrap:nowrap and each field carries 40px of
               horizontal padding on top of its 110px min-width, so four
               fields plus the swap and search buttons need roughly 1000px.
               With no rules between 767px and full desktop width the bar
               simply ran off the side of the page, taking the results list
               below it with it. Trimming the padding is enough to fit: the
               fields keep their order and the bar keeps its single-row shape. */
            @media (min-width: 768px) and (max-width: 1199px) {
                #wbtm_area .wbtm-bar-redesign .wtbm_inputList {
                    padding:   22px 16px;
                    min-width: 88px;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_search_input_fields_holder {
                    min-height: 58px;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_bus_search_button_holder {
                    padding: 8px;
                }
            }

            /* ── Mobile: stack vertically ────────────────────────── */
            @media (max-width: 767px) {
                #wbtm_area .wbtm-bar-redesign .wbtm_search_input_fields_holder {
                    flex-direction: column;
                    border-radius:  10px;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_input_fields_holder,
                #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_location,
                #wbtm_area .wbtm-bar-redesign .wbtm_input_start_end_date {
                    flex-direction: column;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_inputList {
                    border-right:  none;
                    border-bottom: 1.5px solid #dde1e7;
                    /* The desktop 30px/40px padding was never overridden here, so
                       each stacked field stood ~99px tall and the four of them
                       pushed the results a whole screen down the page. */
                    padding:       15px 16px;
                    min-width:     0;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_inputList:last-child {
                    border-bottom: none;
                }
                /* position:relative on .wbtm_input_start_end_location now comes from
                   the base (non-media) rule above; re-position the toggle for the
                   stacked layout -- right-aligned instead of centered, since From/To
                   now stack as full-width rows rather than sitting side by side.
                   assets/frontend/wbtm.css has its own older mobile rule for this
                   same selector (`position:static!important; transform:rotate(90deg)
                   !important; margin:4px auto!important`), written for the classic
                   (non-"redesign") skin -- !important beats this block's higher
                   selector specificity regardless of source order, so it was
                   centering the button and rotating its icon instead of the
                   right-aligned placement below. !important here to actually win. */
                #wbtm_area .wbtm-bar-redesign .wbtm_search_location_toggle {
                    position:  absolute !important;
                    top:       50% !important;
                    left:      auto !important;
                    right:     14px !important;
                    transform: translateY(-50%) !important;
                    margin:    0 !important;
                }
                #wbtm_area .wbtm-bar-redesign .wtbm_bus_search_button_holder {
                    padding: 10px;
                }
                #wbtm_area .wbtm-bar-redesign .wbtm_search_action_button {
                    width:           100% !important;
                    justify-content: center !important;
                }
            }
            </style>
            <script>
            jQuery(document).ready(function($) {
                var wbtmNoResultText = <?php echo wp_json_encode( __( 'No matching location found', 'bus-ticket-booking-with-seat-reservation' ) ); ?>;
                var wbtmFieldSelector = '#wbtm_area .wbtm-bar-redesign .wbtm_input_select input.formControl';

                // Lower-cased and accent-stripped, so "brasov" still matches "Braşov".
                function wbtmNormalize(value) {
                    value = (value || '').toLowerCase().trim();
                    return value.normalize ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value;
                }

                // Filter the city list to what the user typed. The shared handler in
                // wbtm_plugin_global.js hides items with an inline display:none, which the
                // pill-bar's `li{display:flex !important}` rule outranks, so filtering is
                // re-applied here with a class the CSS above can act on.
                function wbtmFilterRouteList($input) {
                    var $wrap  = $input.closest('.wbtm_input_select');
                    var $list  = $wrap.find('.wbtm_input_select_list');
                    var $items = $list.find('li').not('.wbtm_city_no_result');
                    var term   = wbtmNormalize($input.val());
                    var shown  = 0;

                    $items.each(function() {
                        var value = wbtmNormalize($(this).attr('data-value'));
                        var match = term === '' || value.indexOf(term) !== -1;
                        $(this).toggleClass('wbtm_city_filtered_out', !match);
                        if (match) {
                            shown++;
                        }
                    });

                    if (!$items.length) {
                        return;
                    }
                    var $empty = $list.find('li.wbtm_city_no_result');
                    if (shown === 0) {
                        if (!$empty.length) {
                            // data-value="" is required: the shared handlers in
                            // wbtm_plugin_global.js call .toLowerCase() on every li's
                            // data-value and would throw on a missing attribute.
                            $empty = $('<li class="wbtm_city_no_result" data-value="" aria-live="polite"></li>').text(wbtmNoResultText).appendTo($list);
                        }
                        $empty.removeClass('wbtm_city_filtered_out');
                    } else {
                        $empty.addClass('wbtm_city_filtered_out');
                    }
                }

                // Mark the currently-selected li when the dropdown opens, and start from
                // the full list so a previously picked city can be changed.
                $(document).on('click focus', wbtmFieldSelector, function() {
                    var currentVal = $(this).val().toLowerCase().trim();
                    var $list = $(this).closest('.wbtm_input_select').find('.wbtm_input_select_list');
                    $list.find('li').removeClass('wbtm_city_filtered_out');
                    $list.find('li.wbtm_city_no_result').addClass('wbtm_city_filtered_out');
                    $list.find('li').not('.wbtm_city_no_result').each(function() {
                        var liVal = ($(this).attr('data-value') || '').toLowerCase().trim();
                        $(this).toggleClass('wbtm_city_selected', liVal === currentVal && currentVal !== '');
                    });
                });

                // Type to filter — `input` also covers paste, cut and clear-button clears.
                $(document).on('input', wbtmFieldSelector, function() {
                    wbtmFilterRouteList($(this));
                    $(this).closest('.wbtm_input_select').find('.wbtm_input_select_list').stop(true, true).show();
                });

                // Update selected state when an li is clicked
                $(document).on('click', '#wbtm_area .wbtm-bar-redesign .wbtm_input_select_list li', function() {
                    var $list = $(this).closest('.wbtm_input_select_list');
                    $list.find('li').removeClass('wbtm_city_selected wbtm_city_filtered_out');
                    $list.find('li.wbtm_city_no_result').addClass('wbtm_city_filtered_out');
                    $(this).addClass('wbtm_city_selected');
                });

                // Defensive fix for a reported one-time layout jump right after the
                // first city selection: Select2 (mp_global's own `.wbtm_select2`
                // widgets, initialized once for every matching element present at
                // page load -- see wbtm_plugin_global.js) measures its own width
                // from the element it's attached to; anything conditionally hidden
                // at that moment measures as 0-width, and only self-corrects --
                // with a visible reflow -- the first time it's actually revealed.
                // Every later reveal reuses the already-correct measurement, which
                // matches "jumps once, never again" exactly. Rather than wait for
                // the user's own first open to trigger that correction, re-measure
                // proactively the moment anything inside this search widget
                // becomes visible, so the correction (if any) never coincides with
                // an actual user interaction. No-op if this install's routes never
                // use a `.wbtm_select2` field at all.
                if (window.MutationObserver && $('.wbtm_select2').length) {
                    var wbtmArea = document.getElementById('wbtm_area');
                    if (wbtmArea) {
                        var wbtmReflowObserver = new MutationObserver(function (mutations) {
                            mutations.forEach(function (m) {
                                var el = m.target;
                                if (!el || el.offsetParent === null) { return; } // still hidden
                                $(el).find('.wbtm_select2').add($(el).filter('.wbtm_select2')).each(function () {
                                    if ($(this).data('select2')) {
                                        $(this).select2('destroy');
                                        $(this).select2({});
                                    }
                                });
                            });
                        });
                        wbtmReflowObserver.observe(wbtmArea, { attributes: true, attributeFilter: ['style', 'class'], subtree: true });
                        // Only the first reveal needs catching; stop watching well
                        // after the page has settled so this never runs indefinitely.
                        setTimeout(function () { wbtmReflowObserver.disconnect(); }, 60000);
                    }
                }
            });
            </script>

            <div class="_dLayout wbtm_search_area wbtm-bar-redesign <?php echo esc_attr($form_style_class); ?>">
				<?php if ($buy_ticket_text) { ?>
                    <h4><?php echo esc_html($buy_ticket_text); ?></h4>
				<?php } ?>
                <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <form action="<?php echo esc_attr($redirect_url); ?>" method="get" class="mpForm">

                    <input type="hidden" name='wbtm_list_style' value="<?php echo esc_attr($style); ?>"/>
                    <input type="hidden" name='wbtm_list_btn_show' value="<?php echo esc_attr($btn_show); ?>"/>
                    <?php /* Redesigned bar always enables the left filter sidebar regardless of is_page() context */ ?>
                    <input type="hidden" name='wbtm_left_filter_show' value="on"/>
                    <input type="hidden" name='wbtm_left_filter_type' value="<?php echo esc_attr($left_filter_type); ?>"/>
                    <input type="hidden" name='wbtm_left_filter_operator' value="<?php echo esc_attr($left_filter_operator); ?>"/>
                    <input type="hidden" name='wbtm_left_filter_boarding' value="<?php echo esc_attr($left_filter_boarding); ?>"/>

					<?php wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce'); ?>
					<?php if (is_admin()) { ?>
                        <input type="hidden" name="post_type" value="wbtm_bus"/>
                        <input type="hidden" name="page" value="wbtm_backend_order"/>
					<?php } ?>

                    <div class="wbtm_search_input_fields_holder">
                        <div class="wbtm_input_fields_holder">
                            <div class="wbtm_input_start_end_location">
                                
                                <div class="wtbm_inputList wbtm_input_select wbtm_start_point">
                                    <label class="wtbm_fdColumn">
                                        <?php echo esc_html( WBTM_Translations::text_from() ); ?>
                                        <div class="marker">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <input type="text" class="formControl" name="bus_start_route" id="bus_start_route" value="<?php echo esc_attr( $start_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                                        </div>
                                    </label>
                                    <?php WBTM_Layout::route_list( $post_id ); ?>
                                </div>
                                <div class="wbtm_search_location_toggle" id="wbtm_search_location_toggle" title="Swap locations">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="wtbm_inputList wbtm_input_select wbtm_dropping_point" data-alert="<?php echo esc_html( WBTM_Translations::text_select_wrong_route() ); ?>">
                                    <label class="wtbm_fdColumn ">
                                        <?php echo esc_html( WBTM_Translations::text_to() ); ?>
                                        <div class="marker">
                                            <i class="fas fa-map-marker-alt wtbm_icon_margin"></i>
                                            <input type="text" class="formControl" name="bus_end_route" value="<?php echo esc_attr( $end_route ); ?>" placeholder="<?php echo esc_attr( $placeholder_text ); ?>" autocomplete="off" required/>
                                        </div>
                                    </label>
                                    <?php WBTM_Layout::route_list( $post_id ); ?>
                                </div>
                            </div>
                            <div class="wbtm_input_start_end_date">
                                <div class="wtbm_inputList wbtm_journey_date">
                                    <?php WBTM_Layout::journey_date_picker( $post_id, $start_route, $end_route, $start_time ); ?>
                                </div>
                                <?php if ( $return_date_show == 'enable' && ( $post_id == 0 || WBTM_Functions::is_same_bus_return_enabled( $post_id ) ) ) { ?>
                                    <div class="wtbm_inputList wbtm_return_date">
                                        <?php WBTM_Layout::return_date_picker( $post_id, $end_route, $start_route, $start_time, $end_time ); ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="wtbm_bus_search_button_holder" style="display: flex">
                            <div class="_dFlex_fdColumn_justifyBetween_fullHeight search_button_holder">
                                <span class="wbtm_search_button_spacer" aria-hidden="true">&nbsp;</span>
                                <?php if ( $active_redirect_page == 'on' && $search_page_redirect ) {
                                    $redirect_btn_display = 'block';
                                    $ajax_btn_display = 'none';
                                }else{
                                    $redirect_btn_display = 'none';
                                    $ajax_btn_display = 'block';
                                }?>
                                    <button type="submit" class="_themeButton_radius wbtm_bus_submit wbtm_search_action_button" data-loading-text="<?php echo esc_attr__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ); ?>" style="display: <?php echo esc_attr( $redirect_btn_display );?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} else { ?>
                                    <button type="button" class="_themeButton_radius get_wbtm_bus_list wbtm_search_action_button" data-loading-text="<?php echo esc_attr__( 'Searching...', 'bus-ticket-booking-with-seat-reservation' ); ?>" style=" display: <?php echo esc_attr( $ajax_btn_display ); ?>">
                                        <span class="fas fa-search mR_xs"></span><?php echo esc_html( WBTM_Translations::text_search() ); ?>
                                    </button>
<!--                                --><?php //} ?>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="_ovHidden wbtm_search_result">
				<?php WBTM_Layout::wbtm_bus_list($post_id, $start_route, $end_route, $start_time, $end_time, $style, $btn_show, $search_info, $left_filter_show ); ?>
            </div>
        </div>
		<?php
//do_action('wbtm_after_search_list');
//	}
