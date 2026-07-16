/**
 * Bus Manager Global Settings — Tab switching & mobile sidebar
 */
(function ($, bmGs) {
	'use strict';

	var tabMeta = bmGs.tabMeta || {};
	var defaultTab = bmGs.defaultTab || '';
	var STORAGE_KEY = 'wbtmGsActiveTab';

	/** Persist the active tab so it survives the full page reload the Save
	 *  button triggers (a real form POST to options.php, not an AJAX save). */
	bmGs.rememberTab = function (id) {
		try { sessionStorage.setItem(STORAGE_KEY, id); } catch (e) { /* storage unavailable */ }
	};

	/** Last active tab from a previous load in this browser tab/session, if any. */
	bmGs.recallTab = function () {
		try { return sessionStorage.getItem(STORAGE_KEY) || ''; } catch (e) { return ''; }
	};

	/** Switch to a tab panel */
	bmGs.switchTab = function (id, btn) {
		$('.bm-gs__tab-panel').removeClass('bm-gs--active');
		$('.bm-gs__nav-item').removeClass('bm-gs--active');
		$('#bm-tab-' + id).addClass('bm-gs--active');
		if (btn) { $(btn).addClass('bm-gs--active'); }
		var meta = tabMeta[id] || [id, ''];
		$('#bm-topbar-title').text(meta[0] || id);
		$('#bm-topbar-sub').text(meta[1] || '');
		bmGs.closeSidebar();

		if (typeof bmGs.rememberTab === 'function') {
			bmGs.rememberTab(id);
		}
	};

	/** Open mobile sidebar */
	bmGs.openSidebar = function () {
		$('#bm-sidebar').addClass('bm-gs--open');
		$('#bm-overlay').addClass('bm-gs--open');
	};

	/** Close mobile sidebar */
	bmGs.closeSidebar = function () {
		$('#bm-sidebar').removeClass('bm-gs--open');
		$('#bm-overlay').removeClass('bm-gs--open');
	};

	$(function () {

		// Nav item click
		$(document).on('click', '.bm-gs__nav-item', function (e) {
			e.preventDefault();
			var tabId = $(this).data('tab');
			if (tabId) { bmGs.switchTab(tabId, this); }
		});

		// Menu button click (mobile)
		$(document).on('click', '#bm-menu-btn', function () {
			bmGs.openSidebar();
		});

		// Overlay click → close sidebar
		$(document).on('click', '#bm-overlay', function () {
			bmGs.closeSidebar();
		});

		// Topbar save button → submit active tab's form
		// Uses HTMLFormElement.prototype.submit to avoid the "id=submit" shadow bug
		// (WP submit_button() outputs <input id="submit"> which shadows form.submit).
		$(document).on('click', '#bm-save-btn', function (e) {
			e.preventDefault();
			var $f = $('.bm-gs__tab-panel.bm-gs--active').find('form').first();
			if ($f.length) { HTMLFormElement.prototype.submit.call($f[0]); }
		});

		// Activate the tab the admin was last on (e.g. before clicking Save, which
		// does a full page reload via a real form POST), falling back to the
		// server-provided default when nothing was remembered yet or the
		// remembered tab no longer exists (e.g. a Pro-only tab after deactivation).
		var recalledTab = bmGs.recallTab();
		var startTab = (recalledTab && $('#bm-tab-' + recalledTab).length) ? recalledTab : defaultTab;
		if (startTab && $('#bm-tab-' + startTab).length) {
			bmGs.switchTab(startTab, $('.bm-gs__nav-item[data-tab="' + startTab + '"]').first()[0]);
		}
	});

})(jQuery, window.bmGs = window.bmGs || {});
