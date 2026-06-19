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
	 *  Bus name <-> hidden WP #title sync (title box is CSS-hidden)
	 * ---------------------------------------------------------------- */
	var $title = $('#title');
	var $busName = $('#wbtm-bme-title'); // the editable topbar title
	if ($busName.length && $title.length) {
		// Seed the topbar title from the real WP title if empty.
		if (!$busName.val() && $title.val()) {
			$busName.val($title.val());
		}
		$busName.on('input', function () {
			$title.val($busName.val());
			// Clear WP's "Enter title here" prompt state so the title saves.
			$('#title-prompt-text').addClass('screen-reader-text');
			// Live-update the preview rail name.
			$('#wbtm-bme-rail-name').text($busName.val() || 'Untitled bus');
		});
	}

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

	// Initialise.
	goStep(0);

})(jQuery);
