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

	// SEO title/description counters are shared by manual editing and the PRO
	// AI generator (which triggers the same input event after filling fields).
	function updateSeoCounter(counter) {
		var $counter = $(counter);
		var $field = $('#' + $counter.data('seo-counter'));
		if (!$field.length) { return; }
		var length = Array.from($field.val() || '').length;
		var min = parseInt($counter.data('good-min'), 10) || 0;
		var max = parseInt($counter.data('good-max'), 10) || 0;
		$counter.text(length + '/' + max).toggleClass('is-good', length >= min && length <= max);
	}

	$root.on('input', '#wbtm-seo-title, #wbtm-seo-description', function () {
		$root.find('[data-seo-counter="' + this.id + '"]').each(function () { updateSeoCounter(this); });
	});
	$root.find('[data-seo-counter]').each(function () { updateSeoCounter(this); });

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
	 *  Save — background (AJAX) submit of WordPress' own #post form, so the
	 *  admin stays on the editor (no page reload) while the ENTIRE native
	 *  save pipeline still runs server-side: WordPress' edit_post() →
	 *  save_post → every existing plugin meta handler, exactly as a real
	 *  form submit. We only change the transport (fetch instead of a full
	 *  navigation) and then patch the UI in place.
	 *
	 *  On any hard failure (expired nonce, network/server error) we fall
	 *  back to a real form submit so the admin's edits are never lost — that
	 *  is the ONLY path that reloads, and only when the quiet save couldn't
	 *  complete.
	 * ---------------------------------------------------------------- */
	var savingActive = false;

	/** Disable the save/next/caret controls and show a "Saving…" state on the primary button. */
	function setSaving(saving) {
		savingActive = saving;
		var $primary = $root.find('[data-bme-save]');
		var $next    = $root.find('[data-bme-next]');
		var $caret   = $root.find('[data-bme-split-toggle]');
		if (saving) {
			$primary.data('bmeLabel', $primary.text());
			$primary.prop('disabled', true).addClass('is-saving').text(cfg.savingTxt || 'Saving…');
			$next.prop('disabled', true);
			$caret.prop('disabled', true);
		} else {
			var lbl = $primary.data('bmeLabel');
			if (lbl) { $primary.text(lbl); }
			$primary.prop('disabled', false).removeClass('is-saving');
			$next.prop('disabled', false);
			$caret.prop('disabled', false);
			// Restore the Back button's per-step disabled state.
			$root.find('[data-bme-prev]').prop('disabled', cur === 0);
		}
	}

	/**
	 * Reflect the post-save status on the pill + button labels without a reload.
	 * Whichever primary action ran ("Update"/"Publish") always leaves the bus
	 * published; "Switch to Draft" leaves it a draft. Status transitions are
	 * driven by the explicit publish/saveasdraft flags we post (just like WP's
	 * own buttons), so this is purely cosmetic — it never affects the DB result.
	 */
	function updateStatusUi(mode) {
		var published = (mode !== 'draft');
		$root.find('.wbtm-bme__status-pill')
			.toggleClass('is-published', published)
			.toggleClass('is-draft', !published)
			.text(published ? (cfg.publishedTxt || 'Published') : (cfg.draftTxt || 'Draft'));
		// Next primary label (restored by setSaving after this runs).
		$root.find('[data-bme-save]').data('bmeLabel', published ? (cfg.updateTxt || 'Update') : (cfg.publishTxt || 'Publish'));
		// The dropdown option is always the opposite of the primary action.
		$root.find('[data-bme-save-as]').text(published ? (cfg.switchDraftTxt || 'Switch to Draft') : (cfg.saveDraftTxt || 'Save Draft'));
	}

	/**
	 * Keep the live form perpetually "freshly loaded": copy the regenerated
	 * nonces out of the returned edit-page HTML into the current form, so the
	 * NEXT background save stays valid across a long editing session. (WP
	 * nonces are reusable within their lifetime, so this is belt-and-braces.)
	 */
	function refreshNonces(html) {
		var parsed;
		try { parsed = new DOMParser().parseFromString(html, 'text/html'); } catch (e) { return; }
		if (!parsed) { return; }
		['_wpnonce', 'wbtm_type_nonce', 'wbtm_gallery_image_nonce'].forEach(function (id) {
			var fresh = parsed.getElementById(id);
			var live  = document.getElementById(id);
			if (fresh && live && typeof fresh.value !== 'undefined') { live.value = fresh.value; }
		});
	}

	/**
	 * @param {string} mode 'primary' (Update/Publish) or 'draft' (Save/Switch to Draft).
	 */
	function serializeAndSave(mode) {
		if (savingActive) { return; }
		var form = document.getElementById('post');
		if (!form) { return; }

		// Flush the visual editor into its textarea before we serialize.
		if (window.tinymce && window.tinymce.triggerSave) {
			try { window.tinymce.triggerSave(); } catch (e) {}
		}

		var fd = new FormData(form);
		// FormData omits submit buttons that weren't the actual submitter, so we
		// name the intended action explicitly — the exact fields WP's own Publish
		// / Save Draft buttons post, which is how edit_post() decides post_status.
		if (mode === 'draft') {
			fd.delete('publish');
			fd.set('saveasdraft', '1');
		} else {
			fd.delete('saveasdraft');
			var $pub = $('#publish');
			fd.set('publish', ($pub.length && $pub.val()) ? $pub.val() : 'Publish');
		}

		var postUrl = new URL(form.getAttribute('action') || 'post.php', window.location.href).href;

		setSaving(true);
		toast(cfg.savingTxt || 'Saving…');

		fetch(postUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (res) {
				return res.text().then(function (html) { return { res: res, html: html }; });
			})
			.then(function (data) {
				// edit_post() redirects on success and wp_die()s (no redirect) on
				// failure, so a followed redirect is the reliable success signal.
				var ok = data.res.ok && (data.res.redirected || /[?&]message=\d/.test(data.res.url));
				if (!ok) { throw new Error('save-failed'); }
				refreshNonces(data.html);
				updateStatusUi(mode);
				setSaving(false);
				toast(cfg.savedTxt || 'Saved');
			})
			.catch(function () {
				// Quiet save couldn't complete — fall back to a real submit so the
				// edits are saved (and confirmed by the post-reload toast) rather
				// than lost. This is the only branch that reloads the page.
				try { sessionStorage.setItem('wbtmBmeSaved', '1'); } catch (e) {}
				toast(cfg.errorTxt || 'Couldn’t save in background — saving…');
				if (mode === 'draft') {
					var df = form.querySelector('input[name="saveasdraft"]');
					if (!df) {
						df = document.createElement('input');
						df.type = 'hidden';
						df.name = 'saveasdraft';
						form.appendChild(df);
					}
					df.value = '1';
				}
				setTimeout(function () {
					if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
				}, 700);
			});
	}

	function submitForm() { serializeAndSave('primary'); }

	$root.on('click', '[data-bme-save]', function (e) {
		e.preventDefault();
		submitForm();
	});

	/* ---------------------------------------------------------------- *
	 *  Split-button dropdown — one extra option, always the opposite of
	 *  whatever the primary button already does ("Update"/"Publish").
	 *  "Save as Draft"/"Switch to Draft" runs the same background save as the
	 *  primary button, but posts WordPress' own core 'saveasdraft' flag — the
	 *  exact flag its native Save Draft button uses (see _wp_translate_postdata()
	 *  in wp-admin/includes/post.php) — so post_status ends up 'draft' no matter
	 *  what the primary action is, all without a page reload.
	 * ---------------------------------------------------------------- */
	var $split = $root.find('[data-bme-split]');
	var $splitToggle = $split.find('[data-bme-split-toggle]');
	var $splitMenu = $split.find('[data-bme-split-menu]');

	function closeSplitMenu() {
		$splitMenu.attr('hidden', true);
		$splitToggle.attr('aria-expanded', 'false');
	}
	function openSplitMenu() {
		$splitMenu.removeAttr('hidden');
		$splitToggle.attr('aria-expanded', 'true');
	}

	$splitToggle.on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		if ($splitMenu.attr('hidden')) { openSplitMenu(); } else { closeSplitMenu(); }
	});
	$(document).on('click', function (e) {
		if ($split.length && !$split.is(e.target) && $split.has(e.target).length === 0) {
			closeSplitMenu();
		}
	});
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' || e.keyCode === 27) { closeSplitMenu(); }
	});

	function submitFormAs(status) {
		serializeAndSave(status === 'draft' ? 'draft' : 'primary');
	}
	$splitMenu.on('click', '[data-bme-save-as]', function (e) {
		e.preventDefault();
		closeSplitMenu();
		submitFormAs($(this).data('bme-save-as'));
	});
	// "Classic editor" lives in this dropdown too now — its own reload logic
	// is still the document-level [data-bme-ui] handler above; this just
	// tidies up the menu before that reload happens.
	$splitMenu.on('click', '[data-bme-ui]', function () {
		closeSplitMenu();
	});

	/* ---------------------------------------------------------------- *
	 *  Preview — proxy to WordPress' own hidden #post-preview link, whose
	 *  core click handler (wp-admin/js/post.js) saves an autosave first and
	 *  opens the preview in a reused tab, so unsaved changes show up too.
	 * ---------------------------------------------------------------- */
	$root.on('click', '[data-bme-preview]', function (e) {
		var $native = $('#post-preview');
		if ($native.length) {
			e.preventDefault();
			$native.trigger('click');
		}
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
		// Suppressed for programmatic clicks (see enhanceDateSettings()'s
		// auto-add-one-empty-row logic) — that's not a real user action, so
		// it shouldn't produce a "Row added" toast on every page load.
		if (window.__wbtmSuppressActionToast) { return; }
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
	 *  Date Settings: split the single flowing #mp_repeated block into 3
	 *  titled cards (Operation Schedule / Off Days / Excluded Dates).
	 *  Everything stays INSIDE #mp_repeated (only re-grouped into new
	 *  sub-wrappers that are still its children), so the existing
	 *  show/hide toggle between "Particular" and "Repeated" date-type
	 *  modes — which hides/shows #mp_repeated as one unit — keeps
	 *  working exactly as before; we only ever move real DOM rows, never
	 *  clone them, so the repeater add/remove/sortable JS (delegated
	 *  generically off .wbtm_settings_area / .wbtm_item_insert /
	 *  .wbtm_hidden_content, see wbtm_admin_settings.js) is untouched.
	 * ---------------------------------------------------------------- */
	(function enhanceDateSettings() {
		var $section = $root.find('[data-bme-section="WBTM_Date_Settings"]');
		if (!$section.length) { return; }
		var $repeated = $section.find('[data-collapse="#mp_repeated"]');
		if (!$repeated.length) { return; }

		var $centerRows = $repeated.children('._dLayout_padding_dFlex_justifyBetween_alignCenter');
		var $startRows = $repeated.children('._dLayout_padding_dFlex_justifyBetween_alignStart');
		// Defensive: bail cleanly (leave the classic layout as-is) if the
		// classic template ever changes shape instead of assuming positions.
		if ($centerRows.length < 5 || $startRows.length < 2) { return; }

		function makeCard(title, iconClass, sub) {
			return $(
				'<div class="wbtm-bme__ds-card">' +
					'<div class="wbtm-bme__ds-card-head">' +
						'<span class="dashicons ' + iconClass + '"></span>' +
						'<div>' +
							'<div class="wbtm-bme__ds-card-title">' + title + '</div>' +
							(sub ? '<div class="wbtm-bme__ds-card-sub">' + sub + '</div>' : '') +
						'</div>' +
					'</div>' +
					'<div class="wbtm-bme__ds-card-body"></div>' +
				'</div>'
			);
		}

		// Card 1 — Operation Schedule: Repeated Start/End Date, Repeat After,
		// Max advance days (the first 4 of the 5 "alignCenter" rows).
		var $scheduleRows = $centerRows.slice(0, 4);
		var $card1 = makeCard('Operation Schedule', 'dashicons-calendar-alt');
		$scheduleRows.first().before($card1);
		$card1.find('.wbtm-bme__ds-card-body').append($scheduleRows).addClass('wbtm-bme__ds-schedule-body');

		// Card 2 — Off Days: the 5th "alignCenter" row (the day-of-week pill
		// checkboxes). The pill styling itself already exists (.groupCheckBox
		// / .customCheckboxLabel:has(input:checked)) — this just gives it its
		// own card and moves its label/description into the card header.
		var $offDayRow = $centerRows.eq(4);
		var $offDayLabel = $offDayRow.find('> [class*="_dFlex_fdColumn"] label').first().text().trim() || 'Off Days';
		var $offDaySub = $offDayRow.find('> [class*="_dFlex_fdColumn"] span').first().text().trim();
		var $card2 = makeCard($offDayLabel, 'dashicons-marker', $offDaySub);
		$offDayRow.before($card2);
		$offDayRow.find('> [class*="_dFlex_fdColumn"]').remove();
		$card2.find('.wbtm-bme__ds-card-body').append($offDayRow).addClass('wbtm-bme__ds-offday-body');

		// Card 3 — Excluded Dates: both "alignStart" rows (individual off
		// dates + off-date ranges), placed side by side. Each row's own
		// label/description becomes a small heading over its own list so the
		// two stay distinguishable once they're side by side in one card.
		var $card3 = makeCard('Excluded Dates', 'dashicons-trash', 'Individual off dates and off-date ranges for this bus.');
		$startRows.first().before($card3);
		$startRows.each(function () {
			var $row = $(this);
			var label = $row.find('> [class*="_dFlex_fdColumn"] label').first().text().trim();
			$row.find('> [class*="_dFlex_fdColumn"]').remove();
			// The "+ Add New"/"+ Add Range" button is NOT moved into the
			// mini-head (a previous version of this code did, and broke it):
			// its click handler is `$(this).closest(".wbtm_settings_area")`
			// (wbtm_admin_settings.js) — .closest() only walks up real
			// ancestors, so moving the button out to a sibling container
			// made that lookup return nothing and every subsequent action a
			// silent no-op. The button stays exactly where classic markup put
			// it (still a real descendant of .wbtm_settings_area); CSS alone
			// repositions it to sit visually next to this title instead.
			$row.prepend('<div class="wbtm-bme__ds-mini-head"><span>' + (label || '') + '</span></div>');
		});
		$card3.find('.wbtm-bme__ds-card-body').append($startRows).addClass('wbtm-bme__ds-excluded-body');

		// Intentionally NO auto-inserted blank row here: an Excluded-Dates list
		// with zero saved rows stays empty (just its "Add New" button). Off
		// dates/ranges are optional, and auto-adding a phantom empty row made
		// deleting every row look like it "came back" after save/reload — the
		// blank row was re-created on each page load even though the saved list
		// really was empty. Now an emptied list stays empty until the admin
		// clicks "Add New" themselves.
	})();

	/* ---------------------------------------------------------------- *
	 *  Registration Form (Pro addon "WBTM_Settings_PRO" section):
	 *  form-builder redesign only — every real field a passenger's saved
	 *  data depends on stays exactly where classic markup put it.
	 *
	 *  CORE fields (Name/Email/Phone/Address/Gender): these 5 <tr> are NOT
	 *  a repeater — WBTM_Settings_PRO::settings_save() matches
	 *  wbtm_label_text[]/wbtm_input_required[]/wbtm_input_active[] back to
	 *  saved data BY ARRAY INDEX POSITION, not by an id. So these rows are
	 *  only ever decorated in place (icon, CORE badge, toggle-switch
	 *  bridge for the two <select>s, inline label editing) — never
	 *  reordered, never added to/removed from, no .sortable() at all.
	 *
	 *  ADDITIONAL (custom) fields: a real repeater — save rebuilds this
	 *  list from scratch every time, keyed by each row's own field_id/
	 *  values, so add/remove/reorder stay fully safe here. Fields that
	 *  move into the expanded "detail" cell only ever move to a SIBLING
	 *  <td> inside their OWN <tr> — never out of it — so the existing
	 *  type-change handler in wbtm_admin_pro.js (delegated via
	 *  `.closest('tr')`) keeps resolving correctly whether a row is
	 *  collapsed or expanded.
	 * ---------------------------------------------------------------- */
	(function enhanceRegistrationForm() {
		var $section = $root.find('[data-bme-section="WBTM_Settings_PRO"]');
		if (!$section.length) { return; }

		// The classic inner heading/description duplicate the card's own
		// auto-generated header (title/subtitle from get_steps()) — hide via CSS,
		// nothing to do here except leave the markup untouched.

		function bridgeToggle($select) {
			if (!$select.length || $select.data('bme-bridged')) { return; }
			$select.data('bme-bridged', true);
			$select.addClass('wbtm-bme__pf-hidden-native');
			var $sw = $(
				'<label class="roundSwitchLabel wbtm-bme__pf-switch">' +
					'<input type="checkbox"' + ($select.val() === '1' ? ' checked' : '') + '>' +
					'<span class="roundSwitch"></span>' +
				'</label>'
			);
			$select.after($sw);
			$sw.find('input').on('change', function () {
				$select.val(this.checked ? '1' : '').trigger('change');
			});
		}

		// Shared by core + custom rows: shows/hides a "REQUIRED" pill in the
		// collapsed name-row (same visual treatment as the core rows' "CORE"
		// pill) so required fields are visible at a glance without opening the
		// edit panel. Kept in sync with the real <select> via its change event,
		// which bridgeToggle()'s roundSwitch already triggers on every toggle.
		function syncRequiredBadge($nameRow, $reqSelect) {
			var $badge = $nameRow.find('.wbtm-bme__pf-badge--required');
			if (!$badge.length) {
				$badge = $('<span class="wbtm-bme__pf-badge wbtm-bme__pf-badge--required">Required</span>').appendTo($nameRow);
			}
			function sync() { $badge.toggle($reqSelect.val() === '1'); }
			sync();
			$reqSelect.on('change', sync);
		}

		/* ---------------- shared type -> icon map (core + custom fields) ---------------- */
		var TYPE_ICONS = {
			text: 'dashicons-editor-textcolor', email: 'dashicons-email-alt', number: 'dashicons-calculator',
			select: 'dashicons-menu-alt', checkbox: 'dashicons-yes-alt', radio: 'dashicons-marker',
			textarea: 'dashicons-align-left', date: 'dashicons-calendar-alt', select_gender: 'dashicons-groups'
		};

		/* ---------------- CORE FIELDS ---------------- */
		// The 5 default fields, mirrored from WBTM_Attendee_form::default_form()
		// purely so a removed field can be re-added client-side without a round
		// trip — settings_save() itself never trusts this JS copy; it always
		// falls back to the REAL PHP default_form() for any field_id it doesn't
		// already have saved data for.
		var CORE_DEFAULTS = [
			{ id: 'wbtm_full_name', label: 'Passenger Name', type: 'text' },
			{ id: 'wbtm_reg_email', label: 'Passenger Email', type: 'email' },
			{ id: 'wbtm_reg_phone', label: 'Passenger Phone', type: 'text' },
			{ id: 'wbtm_reg_address', label: 'Passenger Address', type: 'textarea' },
			{ id: 'wbtm_user_gender', label: 'Gender', type: 'select_gender' }
		];
		var $coreTable = $section.find('table').first();
		var $coreBody = $coreTable.find('> tbody.wbtm_core_field_area');
		var $hiddenCoreRow = $section.find('.wbtm_core_field_hidden_row .wbtm_core_field_row').first();

		if ($coreBody.length && $hiddenCoreRow.length) {
			$coreTable.addClass('wbtm-bme__pf-core-table');

			function decorateCoreRow($row) {
				if ($row.hasClass('wbtm-bme__pf-done')) { return; }
				$row.addClass('wbtm-bme__pf-done wbtm-bme__pf-row wbtm-bme__pf-core-row wbtm-bme__frow');

				var $th = $row.find('> th').first();
				var $tds = $row.find('> td');
				var $labelTd = $tds.eq(0).addClass('wbtm-bme__pf-labeltd');
				var $typeTd = $tds.eq(1);
				var $reqTd = $tds.eq(2);
				var $removeTd = $tds.eq(3).addClass('wbtm-bme__pf-remove-td');
				$removeTd.find('.wbtm_core_field_remove').addClass('wbtm-bme__pf-core-delete');
				// Edit pencil + drag handle live here too, grouped with delete —
				// one action area on the right instead of splitting edit (left,
				// in the name cell) from delete (right). The drag handle is a
				// real jQuery UI sortable handle (see the .sortable() call
				// below) now that reordering is safe: settings_save() matches
				// core fields by field_id, not array position, so which order
				// they're submitted in no longer matters.
				$removeTd.prepend(
					'<button type="button" class="wbtm-bme__pf-edit-btn" title="Edit field"><span class="dashicons dashicons-edit"></span></button>' +
					'<div class="_mpBtn_themeButton_xs wbtm_sortable_button" title="Drag to reorder"><span class="fas fa-expand-arrows-alt mp_zero"></span></div>'
				);

				var $labelField = $labelTd.find('input[name="wbtm_label_text[]"]').closest('label');
				var $labelInput = $labelField.find('input[name="wbtm_label_text[]"]');
				var $typeSelect = $typeTd.find('select[name="wbtm_input_type[]"]');
				var dLabel = $.trim($th.text()) || $.trim($labelInput.val());
				var initial = $.trim($labelInput.val()) || dLabel;

				function currentIcon() { return TYPE_ICONS[$typeSelect.val()] || 'dashicons-editor-textcolor'; }
				$th.attr('title', dLabel).html('<span class="dashicons ' + currentIcon() + ' wbtm-bme__pf-icon wbtm-bme__pf-type-icon"></span>');

				$labelTd.prepend(
					'<div class="wbtm-bme__pf-name-row">' +
						'<span class="wbtm-bme__pf-name-mirror">' + initial + '</span>' +
					'</div>'
				);
				$labelInput.on('input', function () {
					$labelTd.find('.wbtm-bme__pf-name-mirror').text($.trim($(this).val()) || dLabel);
				});
				$typeSelect.on('change', function () {
					$th.find('.wbtm-bme__pf-type-icon').attr('class', 'dashicons ' + currentIcon() + ' wbtm-bme__pf-icon wbtm-bme__pf-type-icon');
				});

				// "Input Type" and "Required" both move into the edit panel next
				// to the Field Label — there is no repeater/live-JS tied to these
				// core <select>s (unlike the custom fields table), so moving them
				// to a different <td> within this SAME <tr> is safe: each <select>
				// still submits, in the same row/order, wherever it physically sits.
				var $typeField = $typeSelect.closest('label').addClass('wbtm-bme__pf-detail-field');
				$typeField.prepend('<span class="wbtm-bme__pf-detail-label">Input Type</span>');

				var $reqSelect = $reqTd.find('select[name="wbtm_input_required[]"]');
				bridgeToggle($reqSelect);
				var $reqField = $reqSelect.closest('label').addClass('wbtm-bme__pf-detail-field');
				$reqField.prepend('<span class="wbtm-bme__pf-detail-label">Required</span>');
				syncRequiredBadge($labelTd.find('.wbtm-bme__pf-name-row'), $reqSelect);

				$labelTd.append(
					$('<div class="wbtm-bme__pf-edit-panel"></div>')
						.append($labelField)
						.append($typeField)
						.append($reqField)
						.append('<button type="button" class="wbtm-bme__pf-done-btn">Done</button>')
				);
				$typeTd.add($reqTd).remove(); // now empty — their content already moved above

				// No more Active toggle — a default field is either in this list
				// (always active) or removed entirely via the trash icon. Matches
				// custom fields, which have never had an on/off state either.
			}

			$coreBody.find('> tr.wbtm_core_field_row').each(function () { decorateCoreRow($(this)); });

			// Drag-to-reorder — safe now that settings_save() matches by
			// field_id rather than array position (see decorateCoreRow()'s
			// comment above). Newly-restored rows (appended later, below)
			// don't need a separate init call: jQuery UI re-scans a
			// sortable's children at the start of every drag, so they're
			// automatically included without calling .sortable('refresh').
			if ($.fn.sortable) {
				$coreBody.sortable({ handle: '.wbtm_sortable_button' });
			}

			// "Restore" pills for any of the 5 defaults that aren't currently
			// present (removed earlier, or a never-saved bus that's missing one
			// for any other reason) — clones the server-rendered hidden template
			// row (WBTM_Settings_PRO::tab_content()'s wbtm_core_field_hidden_row)
			// so the restored row's markup is guaranteed identical in shape to a
			// real one, then fills in that field's known default values.
			var $restoreWrap = $('<div class="wbtm-bme__pf-restore-pills"></div>').insertAfter($coreTable);
			function refreshRestorePills() {
				var present = $coreBody.find('input[name="wbtm_core_field_id[]"]').map(function () { return this.value; }).get();
				var missing = CORE_DEFAULTS.filter(function (d) { return present.indexOf(d.id) === -1; });
				$restoreWrap.empty().toggle(missing.length > 0);
				if (!missing.length) { return; }
				$restoreWrap.append('<span class="wbtm-bme__pf-suggest-label"><span class="dashicons dashicons-image-rotate"></span>Restore</span>');
				missing.forEach(function (d) {
					$restoreWrap.append('<button type="button" class="wbtm-bme__pf-pill" data-restore-id="' + d.id + '">+ ' + d.label + '</button>');
				});
			}
			refreshRestorePills();

			$section.on('click', '.wbtm-bme__pf-restore-pills .wbtm-bme__pf-pill', function () {
				var id = $(this).data('restore-id');
				var tpl = CORE_DEFAULTS.filter(function (d) { return d.id === id; })[0];
				if (!tpl) { return; }
				var $newRow = $hiddenCoreRow.clone();
				$newRow.find('th').text(tpl.label);
				$newRow.find('input[name="wbtm_core_field_id[]"]').val(tpl.id);
				$newRow.find('input[name="wbtm_label_text[]"]').val(tpl.label).attr('placeholder', tpl.label);
				$newRow.find('select[name="wbtm_input_type[]"]').val(tpl.type);
				$newRow.find('select[name="wbtm_input_required[]"]').val('1');
				$newRow.find('select[name="wbtm_input_active[]"]').val('1');
				$coreBody.append($newRow);
				decorateCoreRow($newRow);
				refreshRestorePills();
				toast('"' + tpl.label + '" restored');
			});

			// Removal happens via the shared, classic+modern click handler in
			// wbtm_admin_pro.js (confirm, then .remove() the <tr>) — watch for
			// that via MutationObserver rather than binding a second handler on
			// the same button, so there's only ever one confirm() dialog.
			if (window.MutationObserver) {
				new MutationObserver(refreshRestorePills).observe($coreBody.get(0), { childList: true });
			}
		}

		/* ---------------- ADDITIONAL (custom) FIELDS ---------------- */
		// (TYPE_ICONS is shared — declared once above, alongside the core fields.)
		var $customArea = $section.find('.wbtm_custom_form_setting_area');
		var $customTbody = $customArea.find('tbody.wbtm_item_insert');
		var $addBtn = $customArea.find('.wbtm_add_item').first();

		// Shared with the Custom Field Builder's own submit handler further
		// below — declared here (function declarations are hoisted through
		// this whole enhanceRegistrationForm() scope) so decorateCustomRow()
		// can also use it for auto-generating wbtm_custom_id[] from the label.
		function slugifyFieldId(label) {
			var base = (label || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
			if (!base) { base = 'custom_field'; }
			if (/^[0-9]/.test(base)) { base = 'f_' + base; }
			var existing = $customTbody.find('input[name="wbtm_custom_id[]"]').map(function () { return this.value; }).get();
			var id = base, n = 1;
			while (existing.indexOf(id) >= 0) { n++; id = base + '_' + n; }
			return id;
		}

		function decorateCustomRow($tr) {
			if (!$tr.length || $tr.hasClass('wbtm-bme__pf-done')) { return; }
			$tr.addClass('wbtm-bme__pf-done wbtm-bme__pf-row wbtm-bme__pf-custom-row wbtm-bme__frow');

			var $tds = $tr.find('> td');
			var $labelTd = $tds.eq(0);
			var $idTd = $tds.eq(1);
			var $typeTd = $tds.eq(2);
			var $valueTd = $tds.eq(3);
			var $reqTd = $tds.eq(4);
			var $defaultTd = $tds.eq(5);
			var $actionTd = $tds.eq(6);

			var $typeSelect = $typeTd.find('select[name="wbtm_custom_type[]"]');
			var $labelField = $labelTd.find('input[name="wbtm_custom_label[]"]').closest('label');
			var $labelInput = $labelField.find('input[name="wbtm_custom_label[]"]');
			var $idInput = $idTd.find('input[name="wbtm_custom_id[]"]');

			$labelTd.addClass('wbtm-bme__pf-labeltd');
			var initialLabel = $.trim($labelInput.val()) || 'New field';
			$labelTd.prepend(
				'<div class="wbtm-bme__pf-name-row">' +
					'<span class="dashicons ' + (TYPE_ICONS[$typeSelect.val()] || 'dashicons-editor-textcolor') + ' wbtm-bme__pf-icon wbtm-bme__pf-type-icon"></span>' +
					'<span class="wbtm-bme__pf-name-mirror">' + initialLabel + '</span>' +
				'</div>'
			);
			// Edit pencil grouped with delete/drag on the right (matching core
			// fields) instead of sitting alone in the name cell on the left.
			var $buttonGroup = $actionTd.find('.buttonGroup');
			$buttonGroup.prepend('<button type="button" class="wbtm-bme__pf-edit-btn" title="Edit field"><span class="dashicons dashicons-edit"></span></button>');
			// WBTM_Custom_Layout::move_remove_button() always renders the delete
			// button before the drag handle, so the DOM order here starts as
			// [edit, delete, drag]. Reordered to [edit, drag, delete] to match the
			// default fields' order (their JS prepends edit+drag together, ahead
			// of the classic single delete button already in the row) — moved in
			// the actual DOM rather than just visually via CSS `order`, so tab
			// order and screen readers see the same sequence sighted users do.
			$buttonGroup.find('.wbtm_sortable_button').insertBefore($buttonGroup.find('.wbtm_item_remove'));
			// Unique ID is system-only now - never shown/editable. A brand-new
			// row (no id yet, e.g. from the classic "Add New Custom Form"
			// button or a fresh clone) gets one auto-generated from the label
			// as the admin types; a row that already HAD a real id when we
			// first decorated it (an existing, previously-saved field) is left
			// alone forever - auto-generation only ever applies once, up
			// front, never retroactively renaming an established field's id.
			var autoId = !$.trim($idInput.val());
			if (autoId) { $idInput.val(slugifyFieldId(initialLabel)); }
			$labelInput.on('input', function () {
				var val = $.trim($(this).val());
				$labelTd.find('.wbtm-bme__pf-name-mirror').text(val || 'New field');
				if (autoId) { $idInput.val(slugifyFieldId(val)); }
			});
			// Field Value (options, only meaningful for select/checkbox/radio)
			// visibility is driven by our OWN data-custom-type attribute + CSS,
			// not the classic dNone/slideUp()/slideDown() mechanism - that
			// mechanism is timing-sensitive (it only reacts to a live change
			// event, so a value programmatically set without ever having fired
			// change at the right moment can be left in the wrong state) and
			// proved unreliable for fields created via the quick-builder. This
			// keeps the underlying <select> and wbtm_admin_pro.js's handler
			// completely untouched (still fires, still safe) - we just no
			// longer depend on ITS resulting inline style for what the admin
			// actually sees.
			function syncTypeAttr() { $tr.attr('data-custom-type', $typeSelect.val()); }
			syncTypeAttr();
			$typeSelect.on('change', function () {
				$labelTd.find('.wbtm-bme__pf-type-icon').attr('class', 'dashicons ' + (TYPE_ICONS[this.value] || 'dashicons-editor-textcolor') + ' wbtm-bme__pf-icon wbtm-bme__pf-type-icon');
				syncTypeAttr();
			});

			var $reqSelect = $reqTd.find('select[name="wbtm_custom_required[]"]');
			bridgeToggle($reqSelect);
			syncRequiredBadge($labelTd.find('.wbtm-bme__pf-name-row'), $reqSelect);

			// Build ONE edit panel (Field Label + Input Type + Field Value +
			// Required + Done, all on one line) directly inside the label cell
			// - the exact same structure decorateCoreRow() uses, so an
			// expanded custom field looks identical to an expanded default
			// field, instead of the label sitting on its own row with a
			// separate full-width "detail" block below it. Every real field
			// stays inside THIS SAME <tr> the whole time - only which <td> it
			// sits in changes - so wbtm_admin_pro.js's `.closest('tr')` type-
			// change handler (which shows/hides Field Value/Default Value/
			// Date) keeps resolving correctly regardless of collapsed/
			// expanded state.
			var $typeField = $typeSelect.closest('label').addClass('wbtm-bme__pf-detail-field');
			$typeField.prepend('<span class="wbtm-bme__pf-detail-label">Input Type</span>');

			var $valueField = $valueTd.find('label').first().addClass('wbtm-bme__pf-detail-field');
			$valueField.prepend('<span class="wbtm-bme__pf-detail-label">Options</span>');

			var $reqField = $reqSelect.closest('label').addClass('wbtm-bme__pf-detail-field');
			$reqField.prepend('<span class="wbtm-bme__pf-detail-label">Required</span>');

			$labelTd.append(
				$('<div class="wbtm-bme__pf-edit-panel"></div>')
					.append($labelField)
					.append($typeField)
					.append($valueField)
					.append($reqField)
					.append('<button type="button" class="wbtm-bme__pf-done-btn">Done</button>')
			);

			// Unique ID and Default Value/Date - real, submitted form fields
			// (so any already-saved default value keeps round-tripping
			// untouched), just never shown to the admin.
			var $hidden = $('<div class="wbtm-bme__pf-hidden-system"></div>').appendTo($labelTd);
			$idTd.contents().appendTo($hidden);
			$defaultTd.contents().appendTo($hidden);
			$idTd.add($typeTd).add($valueTd).add($reqTd).add($defaultTd).remove();
		}


		$customTbody.find('> tr.wbtm_remove_area').each(function () { decorateCustomRow($(this)); });
		if (window.MutationObserver && $customTbody.length) {
			new MutationObserver(function () {
				$customTbody.find('> tr.wbtm_remove_area').each(function () { decorateCustomRow($(this)); });
			}).observe($customTbody.get(0), { childList: true });
		}

		// Resolve the ROW first (works no matter which <td> the clicked button
		// currently sits in — the edit pencil lives in the action area, a
		// SIBLING of .wbtm-bme__pf-labeltd rather than a descendant of it, so
		// .closest() alone can't reach it), then the label cell within that
		// row is what .wbtm-bme__pf-open actually toggles — core and custom
		// rows now use the identical mechanism (both build one edit panel
		// inside the label cell). Shared by the pencil (toggle) and Done
		// (always-close) handlers so they can never drift out of sync.
		function pfOpenTarget($row) {
			var $labelTd = $row.find('.wbtm-bme__pf-labeltd');
			return $labelTd.length ? $labelTd : $row;
		}
		function pfSetEditIcon($row, open) {
			$row.find('.wbtm-bme__pf-edit-btn .dashicons').toggleClass('dashicons-edit', !open).toggleClass('dashicons-yes', open);
		}

		// Pencil click -> expand/collapse the label cell's edit panel.
		$section.on('click', '.wbtm-bme__pf-edit-btn', function (e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			var $target = pfOpenTarget($row);
			var open = $target.toggleClass('wbtm-bme__pf-open').hasClass('wbtm-bme__pf-open');
			pfSetEditIcon($row, open);
			if (open) {
				$row.find('input[name="wbtm_label_text[]"], input[name="wbtm_custom_label[]"]').first().trigger('focus');
			}
		});
		// Done click -> always closes (never toggles), same target resolution.
		$section.on('click', '.wbtm-bme__pf-done-btn', function (e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			pfOpenTarget($row).removeClass('wbtm-bme__pf-open');
			pfSetEditIcon($row, false);
		});

		/* ---------------- Suggested pills + Custom Field Builder ---------------- */
		if ($customArea.length && $addBtn.length) {
			var SUGGESTIONS = [
				{ label: 'NID/Passport No.', type: 'text' },
				{ label: 'Date of Birth', type: 'date' },
				{ label: 'Emergency Contact Number', type: 'text' }
			];

			$addBtn.addClass('wbtm-bme__pf-native-add');

			var pillsHtml = '';
			SUGGESTIONS.forEach(function (s) {
				pillsHtml += '<button type="button" class="wbtm-bme__pf-pill" data-pf-label="' + s.label + '" data-pf-type="' + s.type + '">+ ' + s.label + '</button>';
			});

			var $builderWrap = $(
				'<div class="wbtm-bme__pf-suggestions">' +
					'<button type="button" class="wbtm-bme__pf-add-field-btn"><span class="dashicons dashicons-plus-alt2"></span> Add New Field</button>' +
					'<span class="wbtm-bme__pf-suggest-label"><span class="dashicons dashicons-lightbulb"></span>Suggested</span>' +
					pillsHtml +
				'</div>' +
				'<div class="wbtm-bme__pf-builder">' +
					'<div class="wbtm-bme__pf-builder-head"><span class="dashicons dashicons-plus-alt2"></span> Custom Field Builder</div>' +
					'<div class="wbtm-bme__pf-builder-body">' +
						'<label class="wbtm-bme__pf-builder-field">' +
							'<span>Field label</span>' +
							'<input type="text" class="wbtm-bme__pf-builder-label" placeholder="e.g. NID/Passport No.">' +
						'</label>' +
						'<label class="wbtm-bme__pf-builder-field">' +
							'<span>Input type</span>' +
							'<select class="wbtm-bme__pf-builder-type">' +
								'<option value="text">Text</option>' +
								'<option value="email">Email</option>' +
								'<option value="number">Number</option>' +
								'<option value="select">Select</option>' +
								'<option value="checkbox">Checkbox</option>' +
								'<option value="radio">Radio</option>' +
								'<option value="textarea">Textarea</option>' +
								'<option value="date">Date</option>' +
							'</select>' +
						'</label>' +
						'<label class="wbtm-bme__pf-builder-switch">' +
							'<span>Required at booking</span>' +
							'<label class="roundSwitchLabel"><input type="checkbox" class="wbtm-bme__pf-builder-required"><span class="roundSwitch"></span></label>' +
						'</label>' +
					'</div>' +
					'<div class="wbtm-bme__pf-builder-foot">' +
						'<button type="button" class="wbtm-bme__pf-builder-cancel">Cancel</button>' +
						'<button type="button" class="wbtm-bme__pf-builder-submit">Add field</button>' +
					'</div>' +
				'</div>'
			);
			$customArea.append($builderWrap);
			var $builder = $customArea.find('.wbtm-bme__pf-builder');

			function openBuilder(prefill) {
				$builder.addClass('wbtm-bme__pf-builder-open');
				$builder.find('.wbtm-bme__pf-builder-label').val(prefill && prefill.label ? prefill.label : '');
				$builder.find('.wbtm-bme__pf-builder-type').val(prefill && prefill.type ? prefill.type : 'text');
				$builder.find('.wbtm-bme__pf-builder-required').prop('checked', false);
				setTimeout(function () { $builder.find('.wbtm-bme__pf-builder-label').trigger('focus'); }, 50);
			}
			function closeBuilder() {
				$builder.removeClass('wbtm-bme__pf-builder-open');
			}

			$section.on('click', '.wbtm-bme__pf-add-field-btn', function () {
				if ($builder.hasClass('wbtm-bme__pf-builder-open')) { closeBuilder(); return; }
				openBuilder();
			});
			$section.on('click', '.wbtm-bme__pf-pill', function () {
				openBuilder({ label: $(this).data('pf-label'), type: $(this).data('pf-type') });
			});
			$section.on('click', '.wbtm-bme__pf-builder-cancel', closeBuilder);

			$section.on('click', '.wbtm-bme__pf-builder-submit', function () {
				var label = $.trim($builder.find('.wbtm-bme__pf-builder-label').val());
				var type = $builder.find('.wbtm-bme__pf-builder-type').val();
				var required = $builder.find('.wbtm-bme__pf-builder-required').is(':checked');
				if (!label) {
					$builder.find('.wbtm-bme__pf-builder-label').trigger('focus');
					return;
				}
				var fieldId = slugifyFieldId(label);

				// Reuse the REAL "Add" button/click handler (wbtm_admin_settings.js's
				// own clone-from-hidden-template logic) rather than re-implementing
				// row creation — same technique already used for Date Settings'
				// auto-add-empty-row feature.
				window.__wbtmSuppressActionToast = true;
				$addBtn.trigger('click');
				window.__wbtmSuppressActionToast = false;

				setTimeout(function () {
					var $tr = $customTbody.find('> tr.wbtm_remove_area').last();
					decorateCustomRow($tr);
					$tr.find('input[name="wbtm_custom_label[]"]').val(label).trigger('input');
					$tr.find('input[name="wbtm_custom_id[]"]').val(fieldId);
					$tr.find('select[name="wbtm_custom_type[]"]').val(type).trigger('change');
					$tr.find('select[name="wbtm_custom_required[]"]').val(required ? '1' : '').trigger('change');
					$tr.find('.wbtm-bme__pf-switch input[type="checkbox"]').prop('checked', required);
					closeBuilder();
					toast('Field "' + label + '" added');
				}, 0);
			});
		}
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
		var $aisleRow = $col.find('input[name="wbtm_seat_aisle_after_col"]').closest('._dFlex_justifyBetween_alignCenter');
		if ($rowsRow.length && $colsRow.length) {
			$rowsRow.next('.divider').remove();
			var $grouped = $rowsRow.add($colsRow);
			if ($aisleRow.length) {
				$colsRow.next('.divider').remove();
				$grouped = $grouped.add($aisleRow);
			}
			$grouped.wrapAll('<div class="wbtm-bme__seat-rowcols-grid"></div>');
		}

		$col.find('.wbtm_create_seat_plan, .wbtm_apply_seat_template').addClass('wbtm-bme__seat-generate-btn');
	})();

	/* ---------------------------------------------------------------- *
	 *  Cabin "Seat Rows / Seat Columns / Aisle Position" — same 3-up grid
	 *  treatment as the deck's Layout Settings column above (reuses the
	 *  SAME wbtm-bme__seat-rowcols-grid / wbtm-bme__seat-generate-btn
	 *  classes/CSS, so it's the identical design), but per-cabin and with
	 *  cabin field names (wbtm_cabin_rows[]/wbtm_cabin_cols[] parallel
	 *  arrays + a class-only Aisle field, not a scoped scalar field) — see
	 *  render_cabin_seat_template_picker() in WBTM_Seat_Configuration.php.
	 *  Runs for every cabin already on the page, and (via the
	 *  MutationObserver) for any cabin added later through "Configure
	 *  Cabins" (built client-side in wbtm_admin_settings.js).
	 * ---------------------------------------------------------------- */
	(function redesignCabinSeatLayoutSettings() {
		function processPicker($picker) {
			if (!$picker.length || $picker.hasClass('wbtm-bme__cabin-grid-done')) { return; }
			$picker.addClass('wbtm-bme__cabin-grid-done');

			var $rowsRow = $picker.find('input[name="wbtm_cabin_rows[]"]').closest('._dFlex_justifyBetween_alignCenter');
			var $colsRow = $picker.find('input[name="wbtm_cabin_cols[]"]').closest('._dFlex_justifyBetween_alignCenter');
			var $aisleRow = $picker.find('.wbtm_cabin_aisle_after_col').closest('._dFlex_justifyBetween_alignCenter');
			if ($rowsRow.length && $colsRow.length) {
				$rowsRow.next('.divider').remove();
				var $grouped = $rowsRow.add($colsRow);
				if ($aisleRow.length) {
					$colsRow.next('.divider').remove();
					$grouped = $grouped.add($aisleRow);
				}
				$grouped.wrapAll('<div class="wbtm-bme__seat-rowcols-grid"></div>');
			}

			$picker.find('.wbtm_apply_cabin_seat_template').addClass('wbtm-bme__seat-generate-btn');
			$picker.siblings('.wbtm_generate_cabin_seats').addClass('wbtm-bme__seat-generate-btn');
		}

		function processAllCabins() {
			$root.find('.wbtm_cabin_seat_template_picker').each(function () {
				processPicker($(this));
			});
		}

		processAllCabins();

		if (window.MutationObserver) {
			var $cabinList = $root.find('.wbtm_cabin_list');
			if ($cabinList.length) {
				new MutationObserver(processAllCabins).observe($cabinList.get(0), { childList: true });
			}
		}
	})();

	/* ---------------------------------------------------------------- *
	 *  Passenger Types + Route Pricing Matrix (Pricing & Route step):
	 *  side-by-side cards instead of two stacked tables, each with its
	 *  own header, matching the approved mockup. Passenger Types becomes
	 *  a card list (icon + name) instead of a plain table; the pricing
	 *  table keeps its structure but the split Boarding/Dropping header
	 *  collapses into one "Boarding → Dropping" label and route names
	 *  get the mockup's colored styling (CSS only, see wbtm-bus-edit-
	 *  modern.css). Pure DOM/class changes on top of the classic markup;
	 *  WBTM_Pricing_Routing.php is untouched, so the classic screen keeps
	 *  its existing two-stacked-tables look.
	 * ---------------------------------------------------------------- */
	(function redesignPricingSettings() {
		var $ticketArea = $root.find('.wbtm_ticket_type_area');
		var $priceArea = $root.find('.wbtm_price_setting_area');
		if (!$ticketArea.length || !$priceArea.length) { return; }

		// Replace the single "Pricing Settings" header above both tables
		// with two per-card headers instead (added below).
		var $outer = $ticketArea.closest('._dLayout_padding');
		$outer.prev('._dLayout_padding_bgLight').remove();

		// Drop the vertical spacer between the two areas — side-by-side
		// grid columns don't need it.
		$ticketArea.nextUntil($priceArea, '._mT').remove();

		if (!$ticketArea.find('> .wbtm-bme__pricing-card-title').length) {
			$ticketArea.prepend('<div class="wbtm-bme__pricing-card-title">Passenger Types</div>');
		}
		if (!$priceArea.find('> .wbtm-bme__pricing-card-title').length) {
			$priceArea.prepend(
				'<div class="wbtm-bme__pricing-card-title">Route Pricing Matrix' +
				'<span>Set ticket prices for each route and segment.</span></div>'
			);
		}

		if (!$ticketArea.parent().hasClass('wbtm-bme__pricing-grid')) {
			$ticketArea.add($priceArea).wrapAll('<div class="wbtm-bme__pricing-grid"></div>');
		}

		// Passenger type icon per built-in ticket type id; custom types
		// (blank id) fall back to a generic icon.
		var TICKET_ICONS = { adult: 'fa-user', child: 'fa-child', infant: 'fa-baby' };
		function addTicketIcon($row) {
			var $cell = $row.find('td').first();
			if ($cell.find('.wbtm-bme__ticket-icon').length) { return; }
			var typeId = $row.find('input[name="wbtm_ticket_type_id[]"]').val();
			var icon = TICKET_ICONS[typeId] || 'fa-user-tag';
			$cell.prepend('<span class="wbtm-bme__ticket-icon"><span class="fas ' + icon + '"></span></span>');
		}
		$ticketArea.find('.wbtm_ticket_type_item').each(function () { addTicketIcon($(this)); });

		var $ticketInsert = $ticketArea.find('.wbtm_item_insert');
		if (window.MutationObserver && $ticketInsert.length) {
			new MutationObserver(function () {
				$ticketInsert.find('.wbtm_ticket_type_item').each(function () { addTicketIcon($(this)); });
			}).observe($ticketInsert.get(0), { childList: true });
		}

		// Collapse the split Boarding/Dropping header cell into one label.
		var $bdHeader = $priceArea.find('thead th[colspan="2"]').first();
		if ($bdHeader.length && !$bdHeader.find('.wbtm-bme__price-bd-label').length) {
			$bdHeader.html('<span class="wbtm-bme__price-bd-label">Boarding &rarr; Dropping</span>');
		}
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

	/* ---------------------------------------------------------------- *
	 *  Payment Method modal — reuses Settings → Payments panel markup.
	 * ---------------------------------------------------------------- */
	var $paymentModal = $('#wbtm-bme-payment-modal');
	if ($paymentModal.length) {
		$paymentModal.appendTo('body');
		$(document).on('click', '[data-wbtm-payment-modal-open]', function (e) {
			e.preventDefault();
			$paymentModal.css('display', 'flex');
		});
		$(document).on('click', '[data-wbtm-payment-modal-close]', function () {
			$paymentModal.hide();
		});
		$paymentModal.on('click', function (e) {
			if (e.target === this) { $paymentModal.hide(); }
		});
		$(document).on('keydown', function (e) {
			if ((e.key === 'Escape' || e.keyCode === 27) && $paymentModal.is(':visible')) {
				$paymentModal.hide();
			}
		});

		// The payment panel's own AJAX handlers (gateway toggle/save, booking
		// mode, custom gateways) come from the Settings page and only update
		// inline status text — surface each successful save through this
		// editor's toast too, so the feedback is unmissable.
		var paymentActions = {
			wbtm_wc_toggle_gateway:     null, // message built from response below
			wbtm_wc_save_gateway:       'Payment gateway settings saved',
			wbtm_save_gateway_settings: 'Payment gateway settings saved',
			wbtm_save_booking_mode:     'Booking mode saved'
		};
		$(document).ajaxSuccess(function (event, xhr, settings, response) {
			var data = typeof settings.data === 'string' ? settings.data : '';
			var m = data.match(/(?:^|&)action=([^&]+)/);
			if (!m || !(m[1] in paymentActions)) { return; }
			if (!response || response.success !== true) { return; }
			var msg = paymentActions[m[1]];
			if (m[1] === 'wbtm_wc_toggle_gateway') {
				var on = response.data && response.data.enabled === 'yes';
				msg = on ? 'Payment gateway enabled' : 'Payment gateway disabled';
			}
			toast(msg);
		});
	}

	// Initialise.
	goStep(0);

})(jQuery);
