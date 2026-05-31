/*
 * Multi-step wizard driver for the bus add/edit screen.
 *
 * Progressive enhancement only — it does NOT touch any field or the save path.
 * It drives the EXISTING tabs (ul.tabLists > li[data-tabs-target], whose click
 * handler in wbtm_plugin_global.js already switches .tabsItem panels) and adds
 * a progress bar + Back / Continue / Publish footer. The final "Publish /
 * Update" button just clicks WordPress's own #publish button, so the post
 * saves through the normal save_post hooks.
 */
(function () {
	'use strict';

	var l10n = (window.wbtmBusWizard && window.wbtmBusWizard.i18n) || {};
	var t = function (k, d) { return l10n[k] || d; };

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		var style = document.querySelector('.wbtm_style');
		var tabs = style && style.querySelector('.tabLists');
		if (!tabs) { return; }
		var steps = Array.prototype.slice.call(tabs.querySelectorAll('li[data-tabs-target]'));
		if (steps.length < 2) { return; }

		document.body.classList.add('wbtm-bus-wizard');
		var wrap = tabs.closest('.wbtm_tabs') || style;
		var content = wrap.querySelector('.tabsContent');

		// progress bar between the stepper and the content
		var progress = document.createElement('div');
		progress.className = 'wbtm-wz-progress';
		progress.innerHTML = '<div class="wbtm-wz-fill"></div>';
		if (content) { content.parentNode.insertBefore(progress, content); }
		else { tabs.parentNode.insertBefore(progress, tabs.nextSibling); }
		var fill = progress.firstChild;

		// footer
		var footer = document.createElement('div');
		footer.className = 'wbtm-wz-footer';
		footer.innerHTML =
			'<div class="wbtm-wz-left"><div class="wbtm-wz-dots"></div><div class="wbtm-wz-indicator"></div></div>' +
			'<div class="wbtm-wz-right">' +
				'<button type="button" class="wbtm-wz-btn wbtm-wz-back">' + esc(t('back', 'Back')) + '</button>' +
				'<button type="button" class="wbtm-wz-btn wbtm-wz-next"></button>' +
			'</div>';
		wrap.appendChild(footer);

		var dotsWrap = footer.querySelector('.wbtm-wz-dots');
		var indicator = footer.querySelector('.wbtm-wz-indicator');
		var btnBack = footer.querySelector('.wbtm-wz-back');
		var btnNext = footer.querySelector('.wbtm-wz-next');

		steps.forEach(function () {
			var d = document.createElement('span');
			d.className = 'wbtm-wz-dot';
			dotsWrap.appendChild(d);
		});
		var dots = Array.prototype.slice.call(dotsWrap.children);

		function total() { return steps.length; }
		function currentIndex() {
			for (var i = 0; i < steps.length; i++) {
				if (steps[i].classList.contains('active')) { return i; }
			}
			return 0;
		}

		// make sure one step is active (the existing JS normally activates the first)
		if (!tabs.querySelector('li.active')) { steps[0].click(); }

		function publishLabel() {
			var p = document.getElementById('publish');
			var v = p && (p.value || p.textContent);
			return (v && v.trim()) || t('publish', 'Publish');
		}

		function render() {
			var idx = currentIndex();
			var last = total() - 1;
			steps.forEach(function (li, i) { li.classList.toggle('wbtm-done', i < idx); });
			dots.forEach(function (d, i) {
				d.classList.toggle('active', i === idx);
				d.classList.toggle('done', i < idx);
			});
			fill.style.width = (((idx + 1) / total()) * 100) + '%';
			btnBack.style.display = idx > 0 ? '' : 'none';
			if (idx === last) {
				btnNext.textContent = publishLabel();
				btnNext.classList.add('is-publish');
			} else {
				btnNext.textContent = t('continue', 'Continue');
				btnNext.classList.remove('is-publish');
			}
			indicator.textContent = t('stepOf', 'Step %1 of %2').replace('%1', idx + 1).replace('%2', total());
		}

		function goTo(i) {
			if (i < 0 || i >= total()) { return; }
			steps[i].click();   // reuse the existing tab-switch handler
			render();
			try {
				var card = document.getElementById('wbtm_meta_box_panel');
				if (card) { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			} catch (e) {}
		}

		function publish() {
			var p = document.getElementById('publish') || document.getElementById('save-post');
			if (p) { p.click(); }
		}

		btnNext.addEventListener('click', function () {
			if (currentIndex() === total() - 1) { publish(); }
			else { goTo(currentIndex() + 1); }
		});
		btnBack.addEventListener('click', function () { goTo(currentIndex() - 1); });

		// keep chrome in sync if a step pill is clicked directly
		tabs.addEventListener('click', function (e) {
			if (e.target && e.target.closest && e.target.closest('li[data-tabs-target]')) {
				window.setTimeout(render, 0);
			}
		});

		document.body.classList.remove('wbtm-bus-wz-loading');
		render();
	});

	function esc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}
})();
