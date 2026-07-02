/**
 * Modern bus editor — shell behaviour only (stepper, save, per-user switch, toast).
 *
 * IMPORTANT: This script drives ONLY the modern shell. All section functionality
 * (seat grid, cabin builder, pricing matrix, route rows, price-override modal) is
 * still driven by the existing admin scripts (wbtm_admin.js / wbtm_admin_settings.js),
 * which remain enqueued. We never touch those nodes here.
 */
(function ($) {
	'use strict';

	var cfg = window.wbtmBme || {};

	/* ---------------------------------------------------------------- *
	 *  Per-user editor switch (works in both classic & modern screens)
	 * ---------------------------------------------------------------- */
	function setUi(ui) {
		if (!cfg.ajax) {
			return;
		}
		$.post(cfg.ajax, {
			action: 'wbtm_set_bus_edit_ui',
			nonce: cfg.nonce,
			ui: ui
		}).always(function () {
			window.location.reload();
		});
	}

	$(document).on('click', '[data-bme-ui]', function (e) {
		e.preventDefault();
		setUi($(this).data('bme-ui'));
	});

	/* ---------------------------------------------------------------- *
	 *  Everything below requires the modern shell to be present
	 * ---------------------------------------------------------------- */
	var $root = $('#wbtm-bme');
	if (!$root.length) {
		return;
	}

	// Full-screen takeover hook for the <html> element (CSS removes the
	// admin-bar padding only when this class is present).
	document.documentElement.classList.add('wbtm-bme-html');

	var $steps = $root.find('.wbtm-bme__step');
	var order = $steps.map(function () { return $(this).data('bme-go'); }).get();
	var total = parseInt($root.data('total'), 10) || order.length;
	var cur = 0;

	function goStep(index) {
		if (index < 0) { index = 0; }
		if (index > order.length - 1) { index = order.length - 1; }
		cur = index;
		var name = order[cur];
		// Expose the current step so CSS can hide the rail / go full-width on
		// config-heavy steps (seat, pricing, advanced).
		$root.attr('data-step', name);

		$root.find('.wbtm-bme__panel').each(function () {
			$(this).toggleClass('active', $(this).data('bme-panel') === name);
		});
		$steps.each(function () {
			var i = parseInt($(this).data('bme-index'), 10);
			$(this).toggleClass('active', i === cur).toggleClass('done', i < cur);
		});
		$root.find('.wbtm-bme__conn').each(function () {
			var ci = parseInt($(this).data('bme-conn'), 10);
			$(this).toggleClass('done', ci <= cur);
		});
		$root.find('[data-bme-stepof]').text('Step ' + (cur + 1) + ' of ' + total);
		$root.find('[data-bme-prev]').prop('disabled', cur === 0);

		var $next = $root.find('[data-bme-next]');
		$next.text(cur === order.length - 1 ? (cfg.updateTxt || 'Update') : (cfg.nextTxt || 'Next Step'));

		var top = $root.offset() ? $root.offset().top - 60 : 0;
		$('html, body').animate({ scrollTop: top }, 200);

		// Repaint our wp_editor() instance when returning to the general panel
		if (name === 'general') {
			setTimeout(function () {
				if (window.tinymce) {
					var ed = tinymce.get('wbtm_bme_content');
					if (ed) { ed.execCommand('mceRepaint'); }
				}
			}, 50);
		}
	}

	$steps.on('click', function () {
		goStep(parseInt($(this).data('bme-index'), 10));
	});
	$root.on('click', '[data-bme-prev]', function () {
		if (cur > 0) { goStep(cur - 1); }
	});
	$root.on('click', '[data-bme-next]', function () {
		if (cur < order.length - 1) {
			goStep(cur + 1);
		} else {
			submitForm();
		}
	});

	/* ---------------------------------------------------------------- *
	 *  Save — reuse WordPress' own Update/Publish button so post_status
	 *  and all hidden fields stay correct.
	 * ---------------------------------------------------------------- */
	function submitForm() {
		// Flag the save so we can confirm it after WordPress reloads the page.
		try { sessionStorage.setItem('wbtmBmeSaved', '1'); } catch (e) {}
		toast(cfg.savingTxt || 'Saving…');
		var $publish = $('#publish');
		if (!$publish.length) { $publish = $('#save-post'); }
		if ($publish.length) {
			$publish.removeClass('disabled').prop('disabled', false).trigger('click');
		} else {
			var form = document.getElementById('post');
			if (form) {
				if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
			}
		}
	}
	$root.on('click', '[data-bme-save]', function (e) {
		e.preventDefault();
		submitForm();
	});

	/* ---------------------------------------------------------------- *
	 *  Bus name <-> hidden WP #title sync (title box is CSS-hidden).
	 *  Two visual proxies (topbar + the inline "Post Title" field under
	 *  the Bus Information band) both mirror the one real #title input.
	 * ---------------------------------------------------------------- */
	var $title = $('#title');
	var $busName = $('#wbtm-bme-title'); // the editable topbar title
	var $busNameInline = $('#wbtm-bme-title-inline'); // inline "Post Title" field
	if ($title.length && ($busName.length || $busNameInline.length)) {
		// Seed both proxies from the real WP title if they're empty.
		if ($busName.length && !$busName.val() && $title.val()) {
			$busName.val($title.val());
		}
		if ($busNameInline.length && !$busNameInline.val() && $title.val()) {
			$busNameInline.val($title.val());
		}
		$busName.add($busNameInline).on('input', function () {
			var val = $(this).val();
			$title.val(val);
			$busName.add($busNameInline).not(this).val(val);
			// Clear WP's "Enter title here" prompt state so the title saves.
			$('#title-prompt-text').addClass('screen-reader-text');
		});
	}

	/* ---------------------------------------------------------------- *
	 *  Relocate the "Post Title"/"Post Content" block to sit right after
	 *  the "Bus Information" band, and move the REAL WP content editor
	 *  (#postdivrich) into its content slot — reusing the same editor
	 *  instance (TinyMCE, Add Media, Visual/Text tabs) rather than a
	 *  duplicate, so #content is submitted exactly once.
	 * ---------------------------------------------------------------- */
	(function relocatePostFields() {
		// Place Basic Information card as the first child of postfields-body
		// (before the tabsItem that holds the spec rows).
		var $postFields = $root.find('[data-bme-postfields]');
		var $pbody = $root.find('[data-bme-section="WBTM_Settings_General"]');
		if ($postFields.length && $pbody.length) {
			$postFields.prependTo($pbody);
		}
	})();


	/* ---------------------------------------------------------------- *
	 *  Live toast feedback on real interactions (mirrors the mockup)
	 * ---------------------------------------------------------------- */
	function rowLabel($el) {
		var t = $el.closest('[class*="_dFlex_justifyBetween"], .wbtm-bme__frow, [class*="_dFlex_alignCenter"]')
			.find('label').first().text().replace(/\s+/g, ' ').trim();
		if (t.length > 42) { t = t.slice(0, 42) + '…'; }
		return t;
	}

	// Inject the green "On/Off" badge next to the reservation toggle (mockup).
	function syncOnOff($input) {
		var $badge = $input.closest('.roundSwitchLabel').parent().find('[data-bme-onoff]');
		if ($badge.length) {
			$badge.toggleClass('on', $input.is(':checked')).toggleClass('off', !$input.is(':checked'))
				.text($input.is(':checked') ? 'On' : 'Off');
		}
	}
	var $res = $root.find('input[name="wbtm_registration"]');
	if ($res.length) {
		var $resLabel = $res.closest('.roundSwitchLabel');
		if (!$resLabel.parent().find('[data-bme-onoff]').length) {
			$('<span class="wbtm-bme__onoff" data-bme-onoff></span>').insertBefore($resLabel);
		}
		syncOnOff($res);
	}

	// Toggle switches -> badge + "<Label>: On/Off" toast
	$root.on('change', '.roundSwitchLabel input[type="checkbox"]', function () {
		syncOnOff($(this));
		toast((rowLabel($(this)) || 'Setting') + ': ' + (this.checked ? 'On' : 'Off'));
	});

	// Action buttons from the reused sections -> contextual confirmation.
	var actionMsgs = {
		wbtm_create_seat_plan: 'Seat plan generated',
		wbtm_create_seat_plan_dd: 'Upper deck generated',
		wbtm_configure_cabins: 'Cabins configured',
		wbtm_generate_cabin_seats: 'Cabin seat plan generated',
		wbtm_add_return_route_item: 'Return stop added',
		wbtm_add_item: 'Row added',
		wbtm_item_remove: 'Item removed'
	};
	$root.on('click', '.wbtm_create_seat_plan, .wbtm_create_seat_plan_dd, .wbtm_configure_cabins, .wbtm_generate_cabin_seats, .wbtm_add_item, .wbtm_add_return_route_item, .wbtm_item_remove', function () {
		var el = this, msg = 'Updated';
		Object.keys(actionMsgs).forEach(function (cls) {
			if (el.classList.contains(cls)) { msg = actionMsgs[cls]; }
		});
		toast(msg);
	});

	/* ---------------------------------------------------------------- *
	 *  Feature image (WP post thumbnail) uploader in the preview rail
	 * ---------------------------------------------------------------- */
	function setHero(id, url) {
		var $img = $('#wbtm-bme-hero-img');
		var $ph = $root.find('.wbtm-bme__rail-hero-ph');
		$('#wbtm-bme-thumbnail').val(id || '');
		if (url) {
			$img.attr('src', url).show();
			$ph.hide();
			$root.find('[data-bme-feat-remove]').show();
		} else {
			$img.attr('src', '').hide();
			$ph.show();
			$root.find('[data-bme-feat-remove]').hide();
		}
	}
	var featFrame;
	$root.on('click', '[data-bme-feat-set]', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) { return; }
		if (featFrame) { featFrame.open(); return; }
		featFrame = wp.media({ title: (cfg.featTitle || 'Select feature image'), button: { text: (cfg.featBtn || 'Use image') }, library: { type: 'image' }, multiple: false });
		featFrame.on('select', function () {
			var a = featFrame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			setHero(a.id, url);
			toast(cfg.featSet || 'Feature image set');
		});
		featFrame.open();
	});
	$root.on('click', '[data-bme-feat-remove]', function (e) {
		e.preventDefault();
		setHero('', '');
		toast(cfg.featRemoved || 'Feature image removed');
	});

	/* ---------------------------------------------------------------- *
	 *  Relocate the classic "Bus Logo" row (from the reused General
	 *  Settings body) into its own rail card, right after Featured Image.
	 *  We move the actual DOM node (not a copy) so its existing upload
	 *  button/hidden input keep working unchanged and nothing gets
	 *  submitted twice — the classic General Settings render method
	 *  itself is untouched.
	 * ---------------------------------------------------------------- */
	(function relocateBusLogoRow() {
		var $logoSlot = $root.find('[data-bme-logo-slot]');
		var $logoRow = $root.find('input[name="wbtm_bus_logo"]').closest('._dLayout_padding_dFlex_justifyBetween_alignCenter');
		if (!$logoSlot.length || !$logoRow.length) { return; }

		$logoRow.addClass('wbtm-bme__logo-row').appendTo($logoSlot);

		var $box = $logoRow.find('.wbtm_add_single_image');
		// Placeholder icon shown inside the box only while empty (button is
		// visible); hidden automatically once classic JS hides the button.
		$box.find('> button').html('<span class="dashicons dashicons-media-default wbtm-bme__logo-drop-icon"></span>');

		// "Change image"/"Remove" text links below the box, matching the
		// Featured Image card. They trigger the SAME classic handlers
		// (delegated on .wbtm_add_single_image / .wbtm_remove_single_image)
		// rather than duplicating any upload logic.
		var $acts = $(
			'<div class="wbtm-bme__logo-acts">' +
				'<button type="button" class="wbtm-bme__feat-link" data-bme-logo-set>Change image</button>' +
				'<button type="button" class="wbtm-bme__feat-link wbtm-bme__feat-link--rm" data-bme-logo-remove style="display:none">Remove</button>' +
			'</div>'
		).appendTo($logoSlot);

		function syncActs() {
			var hasImage = $box.find('.wbtm_single_image_item').length > 0;
			$acts.find('[data-bme-logo-set]').text(hasImage ? 'Change image' : 'Upload image');
			$acts.find('[data-bme-logo-remove]').toggle(hasImage);
		}
		$acts.on('click', '[data-bme-logo-set]', function (e) {
			e.preventDefault();
			$box.trigger('click');
		});
		$acts.on('click', '[data-bme-logo-remove]', function (e) {
			e.preventDefault();
			$box.find('.wbtm_remove_single_image').trigger('click');
		});
		// The classic upload JS adds/removes .wbtm_single_image_item and
		// shows/hides the button on its own — watch for that instead of
		// duplicating its logic.
		if (window.MutationObserver) {
			new MutationObserver(syncActs).observe($box.get(0), { childList: true, attributes: true, subtree: true, attributeFilter: ['style', 'class'] });
		}
		syncActs();
	})();

	/* ---------------------------------------------------------------- *
	 *  Wrap the remaining classic General Settings rows (Bus No, Coach
	 *  Type, Reservation on/off) in a bordered box. Runs after the Bus
	 *  Logo relocation above, so that row is already gone from this set —
	 *  only these three are left in the General Info step at this point.
	 * ---------------------------------------------------------------- */
	(function wrapGeneralInfoRows() {
		var $rows = $root.find('[data-bme-panel="general"] ._dLayout_padding_dFlex_justifyBetween_alignCenter').not('.wbtm-bme__info-field-row');
		if (!$rows.length) { return; }
		$rows.wrapAll('<div class="wbtm-bme__postfields-body"></div>');
		var $body = $rows.first().parent();
		$body.wrap('<div class="wbtm-bme__general-rows-box specifications"></div>');
		var $specs = $body.parent();
		$specs.prepend('<div class="wbtm-bme__postfields-header"><div class="wbtm-bme__postfields-header-title">Specifications &amp; Configuration</div><div class="wbtm-bme__postfields-header-sub">Here You can add bus number, coach type and keep registration off if needed.</div></div>');
		// Lift specs box out of tabsItem — place it directly in postfields-body
		// before the features subsection, so all three cards are siblings.
		var $pbody = $root.find('[data-bme-section="WBTM_Settings_General"]');
		var $subsection = $pbody.find('.wbtm-bme__subsection').first();
		if ($subsection.length) {
			$specs.insertBefore($subsection);
		} else {
			$specs.appendTo($pbody);
		}
		// Small icon per row label (Bus No, Coach Type, Reservation on/off),
		// matched by the row's real field name so order changes can't mismatch.
		var icons = {
			wbtm_bus_no: 'dashicons-id-alt',
			wbtm_bus_category: 'dashicons-category',
			wbtm_registration: 'dashicons-yes-alt'
		};
		$rows.each(function () {
			var $row = $(this);
			var name = $row.find('input, select').first().attr('name');
			var icon = icons[name];
			var $label = $row.find('> :first-child label').first();
			if (icon && $label.length && !$label.find('.wbtm-bme__row-icon').length) {
				$label.prepend('<span class="dashicons ' + icon + ' wbtm-bme__row-icon"></span>');
			}
		});
	})();

	/* ---------------------------------------------------------------- *
	 *  Relocate the classic "Available Feature" checkbox list (from the
	 *  Advanced step's Bus Feature tab) into the General Info step's Bus
	 *  Features slot. Its change handler is delegated on document by class
	 *  name (wtbm_bus_feature_checkbox) and saves via its own AJAX call, so
	 *  moving the markup doesn't touch that behaviour at all.
	 * ---------------------------------------------------------------- */
	(function relocateFeatureChecklist() {
		var $slot     = $root.find('[data-bme-features-slot]');
		var $checklist = $root.find('.wtbm_all_selected_term_condition');
		var $label    = $root.find('[data-bme-features-label]');
		if (!$slot.length || !$checklist.length) { return; }

		// Build header from the PHP-rendered label element
		var labelText = $label.length ? $label.find('label').text().trim() : 'Bus Features';
		var subText   = $label.length ? $label.find('span').text().trim()   : '';
		var $header = $('<div class="wbtm-bme__postfields-header">'
			+ '<div class="wbtm-bme__postfields-header-title">' + labelText + '</div>'
			+ (subText ? '<div class="wbtm-bme__postfields-header-sub">' + subText + '</div>' : '')
			+ '</div>');

		// Wrap just the checkbox list in a body div, then move header + body
		// directly into the slot — removing both old wrapper divs.
		var $features = $checklist.find('.wtbm-bus-features');
		$features.wrap('<div class="wbtm-bme__postfields-body"></div>');
		var $body = $features.parent();
		$slot.append($header).append($body);
		$checklist.remove();
		$label.remove();

		// ---- "Add Feature" button + modal ----
		var $addBtn = $('<button type="button" class="wbtm-bme__feat-add-btn"><span class="dashicons dashicons-plus-alt2"></span> Add Feature</button>');
		$body.append($addBtn);

		// Build modal once and append to $root
		var modalId = 'wbtm-bme-feat-modal';
		if (!$root.find('#' + modalId).length) {
			var $modal = $([
				'<div id="' + modalId + '" class="wbtm-bme__feat-modal" style="display:none">',
				  '<div class="wbtm-bme__feat-modal-backdrop"></div>',
				  '<div class="wbtm-bme__feat-modal-box">',
				    '<div class="wbtm-bme__feat-modal-head">',
				      '<span class="wbtm-bme__feat-modal-title">Add Bus Feature</span>',
				      '<button type="button" class="wbtm-bme__feat-modal-close fas fa-times"></button>',
				    '</div>',
				    '<div class="wbtm-bme__feat-modal-body">',
					  '<label class="wbtm-bme__feat-modal-label">Feature Name</label>',
					  '<input type="text" class="wbtm-bme__feat-modal-name" placeholder="Wi-Fi, USB Port…" autocomplete="off">',
					  '<label class="wbtm-bme__feat-modal-label" style="margin-top:14px">Feature Icon <small>(optional)</small></label>',
					  '<div class="wbtm_add_icon_image_area fdColumn wbtm-bme__feat-icon-area">',
					    '<input type="hidden" class="wbtm-bme__feat-icon-val" value="">',
					    '<div class="wbtm_icon_item wbtm-bme__feat-icon-item" style="display:none">',
					      '<span class="wbtm-bme__feat-icon-swatch allCenter wbtm_icon_add" data-target-popup="#wbtm_add_icon_popup" title="Change Icon">',
					        '<span class="wbtm-bme__feat-icon-display" data-add-icon></span>',
					      '</span>',
					      '<button type="button" class="wbtm-bme__feat-icon-change wbtm_icon_add" data-target-popup="#wbtm_add_icon_popup">Change icon</button>',
					      '<button type="button" class="wbtm_icon_remove wbtm-bme__feat-icon-remove fas fa-times" title="Remove Icon"></button>',
					    '</div>',
					    '<div class="wbtm_add_icon_image_button_area">',
					      '<button class="wbtm-bme__feat-icon-btn wbtm_icon_add" type="button" data-target-popup="#wbtm_add_icon_popup">',
					        '<span class="wbtm-bme__feat-icon-btn-swatch allCenter"><span class="fas fa-plus"></span></span>',
					        '<span class="wbtm-bme__feat-icon-btn-copy">Choose an icon</span>',
					      '</button>',
					    '</div>',
					  '</div>',
					'</div>',
					'<div class="wbtm-bme__feat-modal-foot">',
				      '<button type="button" class="wbtm-bme__feat-modal-cancel">Cancel</button>',
				      '<button type="button" class="wbtm-bme__feat-modal-submit">Add Feature</button>',
				    '</div>',
				  '</div>',
				'</div>'
			].join(''));
			$root.append($modal);
		}

		function openFeatModal() {
			var $m = $root.find('#' + modalId);
			$m.find('.wbtm-bme__feat-modal-name').val('');
			$m.find('.wbtm-bme__feat-icon-val').val('');
			$m.find('[data-add-icon]').removeAttr('class');
			$m.find('.wbtm-bme__feat-icon-item').hide();
			$m.find('.wbtm_add_icon_image_button_area').show();
			$m.find('.wbtm-bme__feat-modal-submit').prop('disabled', false).text('Add Feature');
			$m.show();
			setTimeout(function () { $m.find('.wbtm-bme__feat-modal-name').focus(); }, 50);
		}
		function closeFeatModal() {
			$root.find('#' + modalId).hide();
		}

		$root.on('click', '.wbtm-bme__feat-add-btn', openFeatModal);
		$root.on('click', '.wbtm-bme__feat-modal-close, .wbtm-bme__feat-modal-cancel, .wbtm-bme__feat-modal-backdrop', closeFeatModal);

		// Submit
		$root.on('click', '.wbtm-bme__feat-modal-submit', function () {
			var $m    = $root.find('#' + modalId);
			var name  = $m.find('.wbtm-bme__feat-modal-name').val().trim();
			var icon  = $m.find('.wbtm-bme__feat-icon-val').val().trim();
			var postId = $('[name="wbtm_post_id"]').val() || $('[name="post_ID"]').val() || 0;
			if (!name) {
				$m.find('.wbtm-bme__feat-modal-name').focus();
				return;
			}
			var $btn = $(this).prop('disabled', true).text('Adding…');
			$.ajax({
				url: wbtm_ajax_url,
				type: 'POST',
				data: {
					action:       'wbtm_bme_create_bus_feature',
					nonce:        wbtm_nonce,
					post_id:      postId,
					feature_name: name,
					feature_icon: icon
				},
				success: function (res) {
					if (!res.success) {
						alert(res.data || 'Could not add feature.');
						$btn.prop('disabled', false).text('Add Feature');
						return;
					}
					var d = res.data;
					// Append new chip to the feature list, pre-checked
					var $chip = $(
						'<label>' +
						'<input type="checkbox" class="wtbm_bus_feature_checkbox" data-term-id="' + d.term_id + '" checked>' +
						(d.icon ? '<span class="wbtm_bus_feature_icon ' + d.icon + '"></span>' : '') +
						d.name +
						'</label><br>'
					);
					$features.append($chip);
					closeFeatModal();
					toast('Feature "' + d.name + '" added');
					// Trigger the existing save-features AJAX via the new checkbox's change event
					$chip.find('.wtbm_bus_feature_checkbox').trigger('change');
				},
				error: function () {
					alert('Request failed. Please try again.');
					$btn.prop('disabled', false).text('Add Feature');
				}
			});
		});
	})();

	/* ---------------------------------------------------------------- *
	 *  "Enable same bus for return trips" checkbox -- show/hide the
	 *  return route settings area below it. The area starts hidden (CSS
	 *  display:none) and is revealed when the checkbox is checked.
	 * ---------------------------------------------------------------- */
	(function initReturnRouteToggle() {
		var $cb   = $root.find('input[name="wbtm_same_bus_return_enabled"][type="checkbox"]');
		var $area = $root.find('.wbtm_return_route_settings_area');
		if (!$cb.length || !$area.length) { return; }
		function syncArea() { $area.toggle($cb.is(':checked')); }
		syncArea(); // set state from saved value on page load
		$root.on('change', 'input[name="wbtm_same_bus_return_enabled"][type="checkbox"]', syncArea);
	})();

	/* ---------------------------------------------------------------- *
	 *  Lower Deck "Layout Settings" column (Seat Configure step): add a
	 *  title, stack the Driver Position row (it's bare label+select
	 *  siblings, not the col_6/col_6 pattern the toggle rows use), merge
	 *  Seat Rows + Seat Columns into one 2-up row, and make the Generate
	 *  button full width — matching the approved mockup. Pure DOM/class
	 *  changes on top of the classic markup; WBTM_Seat_Configuration is
	 *  untouched.
	 * ---------------------------------------------------------------- */
	(function redesignSeatLayoutSettings() {
		var $col = $root.find('input[name="wbtm_seat_rows"]').closest('._dlayout_bR_bgWhite_padding_xs');
		if (!$col.length) { return; }
		$col.addClass('layour-settings');

		$col.prepend('<div class="wbtm-bme__seat-settings-title">Layout Settings</div>');

		var $driverSelect = $col.find('select[name="driver_seat_position"]');
		$driverSelect.closest('._dFlex_justifyBetween_alignCenter').addClass('wbtm-bme__row-stacked');

		var $rowsRow = $col.find('input[name="wbtm_seat_rows"]').closest('._dFlex_justifyBetween_alignCenter');
		var $colsRow = $col.find('input[name="wbtm_seat_cols"]').closest('._dFlex_justifyBetween_alignCenter');
		if ($rowsRow.length && $colsRow.length) {
			$rowsRow.next('.divider').remove();
			$rowsRow.add($colsRow).wrapAll('<div class="wbtm-bme__seat-rowcols-grid"></div>');
		}

		$col.find('.wbtm_create_seat_plan').addClass('wbtm-bme__seat-generate-btn');
	})();

	/* ---------------------------------------------------------------- *
	 *  Gallery — enable/disable toggle + inline add/remove in the rail
	 * ---------------------------------------------------------------- */
	var $gallerySection = $root.find('[data-bme-gallery-section]');
	var $galleryList = $root.find('[data-bme-gallery-list]');
	var $galleryEmpty = $root.find('[data-bme-gallery-empty]');

	function refreshGalleryEmptyState() {
		$galleryEmpty.toggle($galleryList.find('[data-bme-gallery-item]').length === 0);
	}

	$root.on('change', '[data-bme-gallery-toggle]', function () {
		var on = $(this).is(':checked');
		$gallerySection.toggle(on);
		toast('Gallery: ' + (on ? 'On' : 'Off'));
	});

	var galleryFrame;
	$root.on('click', '[data-bme-gallery-add]', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) { return; }
		if (galleryFrame) { galleryFrame.open(); return; }
		galleryFrame = wp.media({ title: 'Select gallery images', button: { text: 'Add to gallery' }, library: { type: 'image' }, multiple: true });
		galleryFrame.on('select', function () {
			var selection = galleryFrame.state().get('selection');
			selection.each(function (a) {
				a = a.toJSON();
				var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				var $item = $('<div class="wbtm-bme__gallery-item" data-bme-gallery-item></div>');
				$item.append($('<img>').attr({ src: url, alt: '' }));
				$item.append($('<input type="hidden" name="wbtm_gallery_images[]">').val(a.id));
				$item.append($('<button type="button" class="wbtm-bme__gallery-item-rm" data-bme-gallery-remove aria-label="Remove image">&times;</button>'));
				$galleryList.append($item);
			});
			refreshGalleryEmptyState();
			toast('Gallery image' + (selection.length > 1 ? 's' : '') + ' added');
			galleryFrame.state().get('selection').reset();
		});
		galleryFrame.open();
	});
	$root.on('click', '[data-bme-gallery-remove]', function () {
		$(this).closest('[data-bme-gallery-item]').remove();
		refreshGalleryEmptyState();
		toast('Gallery image removed');
	});

	/* ---------------------------------------------------------------- *
	 *  Rail "Manage" buttons -> jump to the relevant step + section
	 * ---------------------------------------------------------------- */
	$root.on('click', '[data-bme-goto]', function () {
		var step = $(this).data('bme-goto');
		var idx = order.indexOf(step);
		if (idx >= 0) { goStep(idx); }
		var sel = $(this).data('bme-scroll');
		if (sel) {
			var $t = $root.find('[data-tabs="' + sel + '"]');
			if ($t.length) {
				setTimeout(function () { $t[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 320);
			}
		}
	});

	/* ---------------------------------------------------------------- *
	 *  Blank seat cells -> hide View (price) + rotate controls
	 *
	 *  A cell is "blank" when its seat-id input is empty (not a sellable
	 *  seat). We only toggle a presentational class; the hidden
	 *  wbtm_*_rotation[] field stays in the DOM so the saved rotation array
	 *  keeps the same length/order as the seat array. We re-evaluate on the
	 *  SAME signals the classic seat engine uses: typing (input), drop/erase
	 *  (which call .trigger('change')), and full re-render (the custom
	 *  'wbtm_seat_plan_dom_updated' event fired after Generate / Create).
	 * ---------------------------------------------------------------- */
	function markCellBlank($c) {
		if (!$c || !$c.length) { return; }
		var v = $.trim($c.find('input.formControl').first().val() || '');
		$c.toggleClass('wbtm-bme-blank', v === '');
	}
	function markAllBlankSeats() {
		$root.find('.wbtm_seat_container').each(function () {
			markCellBlank($(this));
		});
	}
	// Per-cell update on manual typing, drop, or eraser/clear.
	$root.on('input change', 'input.wbtm_id_validation', function () {
		markCellBlank($(this).closest('.wbtm_seat_container'));
	});
	// Full sweep after the seat grid is regenerated/replaced.
	$(document).on('wbtm_seat_plan_dom_updated', function () {
		setTimeout(markAllBlankSeats, 0);
	});
	// Initial pass (seats are server-rendered, so they already exist).
	setTimeout(markAllBlankSeats, 250);

	/* ---------------------------------------------------------------- *
	 *  Toast
	 * ---------------------------------------------------------------- */
	var toastTimer;
	function toast(msg) {
		var $t = $root.find('[data-bme-toast]');
		if (!$t.length) { return; }
		$t.find('[data-bme-toast-msg]').text(msg);
		$t.addClass('show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { $t.removeClass('show'); }, 2200);
	}

	// Confirm a successful save ONCE after WordPress reloads the editor. We use
	// only our own sessionStorage flag (set in submitForm) so the toast shows a
	// single time after a save — never on every page load / reload.
	var justSaved = false;
	try { justSaved = sessionStorage.getItem('wbtmBmeSaved') === '1'; } catch (e) {}
	if (justSaved) {
		try { sessionStorage.removeItem('wbtmBmeSaved'); } catch (e) {}
		setTimeout(function () { toast(cfg.savedTxt || 'Saved'); }, 350);
	}


	/* ---------------------------------------------------------------- *
	 *  Icon picker popup — live search + "no results" state            *
	 *  Piggybacks on the existing popup HTML rendered via admin_footer. *
	 * ---------------------------------------------------------------- */
	(function initIconPickerSearch() {
		var $popup = $('.wbtm_add_icon_popup');
		if (!$popup.length) { return; }

		// Inject no-results message into the grid once
		$popup.find('.popup_all_icon').append(
			'<div class="wbtm-bme-icon-noresults">' +
				'<span class="fas fa-search wbtm-bme-nores-icon"></span>' +
				'<p>No icons match <strong class="q"></strong></p>' +
			'</div>'
		);

		function resetSearch() {
			var $input = $popup.find('input[name="mp_select_icon_name"]');
			if ($input.val()) {
				$input.val('');
				$popup.find('.popupTabItem').show();
				$popup.find('.iconItem').show();
				$popup.find('.wbtm-bme-icon-noresults').hide();
			}
		}

		// Clear on open
		$(document).on('click', '.wbtm_icon_add', resetSearch);

		// Clear on category click (runs after existing handler via setTimeout)
		$(document).on('click', '.wbtm_add_icon_popup [data-icon-menu]', function () {
			if ($popup.find('input[name="mp_select_icon_name"]').val()) {
				setTimeout(function () {
					$popup.find('.popupTabItem').each(function () {
						$(this).find('.iconItem').show();
					});
					$popup.find('.wbtm-bme-icon-noresults').hide();
					$popup.find('input[name="mp_select_icon_name"]').val('');
				}, 0);
			}
		});

		// Live search
		$(document).on('input', '.wbtm_add_icon_popup input[name="mp_select_icon_name"]', function () {
			var q = $(this).val().toLowerCase().trim();
			var $grid = $popup.find('.popup_all_icon');
			var $nr   = $popup.find('.wbtm-bme-icon-noresults');

			if (!q) {
				$grid.find('.popupTabItem').show();
				$grid.find('.iconItem').show();
				$nr.hide();
				return;
			}

			var total = 0;
			$grid.find('.popupTabItem').each(function () {
				var matched = 0;
				$(this).find('.iconItem').each(function () {
					var name = ($(this).data('icon-name') || $(this).attr('title') || '').toLowerCase();
					var cls  = ($(this).data('icon-class') || '').toLowerCase();
					var ok   = name.indexOf(q) >= 0 || cls.indexOf(q) >= 0;
					$(this).toggle(ok);
					if (ok) { matched++; }
				});
				$(this).toggle(matched > 0);
				total += matched;
			});

			$nr.find('.q').text('"' + q + '"');
			$nr.toggle(total === 0);
		});
	})();

	/* ---------------------------------------------------------------- *
	 *  Skeleton / shimmer placeholder loaders for each panel section   *
	 *  Skeleton HTML is server-rendered in PHP so it shows immediately  *
	 *  on page load — JS only removes the --loading class per panel.   *
	 * ---------------------------------------------------------------- */
	(function initSkeletons() {
		function removeLoading(el) {
			var ov = el.querySelector('.wbtm-bme__skel-ov');
			if (ov) {
				ov.classList.add('out');
				setTimeout(function () {
					el.classList.remove('wbtm-bme__panel--loading', 'wbtm-bme__rail-card--loading');
				}, 260);
			} else {
				el.classList.remove('wbtm-bme__panel--loading', 'wbtm-bme__rail-card--loading');
			}
		}

		// Panels: remove loading when .active is applied by goStep()
		$root.find('.wbtm-bme__panel').each(function () {
			var panel = this;
			var done = false;
			var obs = new MutationObserver(function () {
				if (!done && panel.classList.contains('active')) {
					done = true;
					obs.disconnect();
					setTimeout(function () { removeLoading(panel); }, 400);
				}
			});
			obs.observe(panel, { attributes: true, attributeFilter: ['class'] });
		});

		// The initially-active panel already has .active in PHP; goStep(0) won't
		// mutate its class attribute, so trigger removal separately.
		// Rail cards are always visible — remove their loading state at the same time.
		setTimeout(function () {
			var panel = $root.find('.wbtm-bme__panel.active')[0];
			if (panel) { removeLoading(panel); }

			$root.find('.wbtm-bme__rail-card--loading').each(function () {
				removeLoading(this);
			});
		}, 500);
	})();

	// Initialise.
	goStep(0);

})(jQuery);
