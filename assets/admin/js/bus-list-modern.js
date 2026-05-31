/*
 * Modern "Bus List" renderer for edit.php?post_type=wbtm_bus.
 *
 * Reads the native WP list-table rows (real titles, Edit/Trash/View URLs with
 * nonces, thumbnail, coach no / type / coach-type / author / status), then
 * builds the card-grid + list-view UI and hides the native markup (kept in the
 * DOM so all those URLs stay valid). Edit links open the overlay editor
 * (bus-modal-overlay.js intercepts them). Status tabs + pagination reuse the
 * native (server-side) links; search + type filter are client-side.
 */
(function () {
	'use strict';

	var l10n = (window.wbtmBusList && window.wbtmBusList.i18n) || {};
	var t = function (k, d) { return l10n[k] || d; };

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		var list = document.getElementById('the-list');
		var wrap = document.querySelector('.wrap');
		if (!list || !wrap) { return; }

		var rows = extractRows(list);
		var meta = extractMeta();
		document.body.classList.add('wbtm-bus-modern');

		var root = document.createElement('div');
		root.className = 'wbtmf';
		try { root.classList.toggle('is-list', window.localStorage.getItem('wbtm_bus_view') === 'list'); } catch (e) {}
		wrap.appendChild(root);

		root.innerHTML = buildHtml(rows, meta);
		wireEvents(root, meta);
		document.body.classList.remove('wbtm-bus-loading');
	});

	/* ---------- read native data ---------- */
	function txt(el, sel) {
		var n = el.querySelector(sel);
		return n ? n.textContent.trim() : '';
	}
	function typeOf(coachType) {
		var s = (coachType || '').toLowerCase();
		if (!s || s === '-') { return ''; }
		if (s.indexOf('non') !== -1) { return 'nonac'; }
		if (s.indexOf('ac') !== -1) { return 'ac'; }
		return 'other';
	}
	function extractRows(list) {
		var out = [];
		var imgMap = (window.wbtmBusList && window.wbtmBusList.images) || {};
		var trs = list.querySelectorAll('tr:not(.no-items):not(.inline-edit-row)');
		Array.prototype.forEach.call(trs, function (tr) {
			var titleA = tr.querySelector('.row-title') || tr.querySelector('.column-title strong a') || tr.querySelector('.column-title a');
			var editA = tr.querySelector('.row-actions .edit a') || titleA;
			var trashA = tr.querySelector('.row-actions .trash a, .row-actions .delete a');
			var viewA = tr.querySelector('.row-actions .view a');
			var img = tr.querySelector('.column-wbtm_thumbnail img');
			var coachType = txt(tr, '.column-wbtm_coach_type');
			var authorRaw = txt(tr, '.column-wbtm_added_by');
			var name = authorRaw.replace(/\[.*?\]/g, '').trim();
			var initials = (name.split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2) || 'NA').toUpperCase();
			var isDraft = /status-(draft|pending|private)/.test(tr.className);
			out.push({
				title:   titleA ? titleA.textContent.trim() : txt(tr, '.column-title'),
				editUrl: editA ? editA.getAttribute('href') : '',
				trashUrl: trashA ? trashA.getAttribute('href') : '',
				viewUrl: viewA ? viewA.getAttribute('href') : '',
				thumb:   imgMap[(tr.id || '').replace(/^post-/, '')] || (img ? img.getAttribute('src') : ''),
				coachNo: txt(tr, '.column-wbtm_bus_no'),
				busType: txt(tr, '.column-wbtm_bus_type'),
				coachType: coachType,
				type:    typeOf(coachType),
				author:  name || authorRaw,
				initials: initials,
				status:  isDraft ? t('draftLabel', 'Draft') : t('publishedLabel', 'Published'),
				isDraft: isDraft
			});
		});
		return out;
	}

	function statusCount(cls) {
		var el = document.querySelector('.subsubsub .' + cls + ' .count');
		if (!el) { return null; }
		var n = el.textContent.replace(/[^\d]/g, '');
		return n === '' ? null : parseInt(n, 10);
	}
	function extractMeta() {
		var h1 = document.querySelector('.wrap h1.wp-heading-inline');
		var addA = document.querySelector('.wrap .page-title-action');
		var dn = document.querySelector('.displaying-num');

		var tabs = [];
		Array.prototype.forEach.call(document.querySelectorAll('.subsubsub > li > a'), function (a) {
			tabs.push({
				label: a.childNodes[0] ? a.childNodes[0].textContent.trim() : a.textContent.trim(),
				count: (a.querySelector('.count') ? a.querySelector('.count').textContent.trim() : ''),
				href: a.getAttribute('href'),
				current: a.classList.contains('current')
			});
		});

		var pag = document.querySelector('.tablenav.bottom .tablenav-pages .pagination-links') ||
			document.querySelector('.tablenav-pages .pagination-links');

		return {
			title: t('title', (h1 ? h1.textContent.trim() : 'Bus List')),
			count: dn ? dn.textContent.trim() : '',
			addUrl: addA ? addA.getAttribute('href') : 'post-new.php?post_type=wbtm_bus',
			addLabel: addA ? addA.textContent.trim() : t('add', 'Add New Bus'),
			tabs: tabs,
			total: statusCount('all'),
			published: statusCount('publish'),
			pagHtml: pag ? pag.innerHTML : ''
		};
	}

	/* ---------- build markup ---------- */
	function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
	function escA(s) { return esc(s).replace(/"/g, '&quot;'); }

	var ICON = {
		grid: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
		list: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
		plus: '<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		search: '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
		edit: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
		trash: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>'
	};

	function buildHtml(rows, meta) {
		var ac = 0, nonac = 0;
		rows.forEach(function (r) { if (r.type === 'ac') { ac++; } if (r.type === 'nonac') { nonac++; } });
		var total = meta.total != null ? meta.total : rows.length;
		var published = meta.published != null ? meta.published : rows.length;

		var h = '';
		// header
		h += '<div class="wbtmf-head">' +
			'<div class="wbtmf-title">' + esc(meta.title) + (meta.count ? ' <span>' + esc(meta.count) + '</span>' : '') + '</div>' +
			'<div class="wbtmf-head-actions">' +
				'<div class="wbtmf-vtoggle">' +
					'<button type="button" class="wbtmf-vtog" data-view="grid" title="' + escA(t('grid', 'Grid view')) + '">' + ICON.grid + '</button>' +
					'<button type="button" class="wbtmf-vtog" data-view="list" title="' + escA(t('list', 'List view')) + '">' + ICON.list + '</button>' +
				'</div>' +
				'<a class="wbtmf-add" href="' + escA(meta.addUrl) + '">' + ICON.plus + esc(meta.addLabel) + '</a>' +
			'</div></div>';

		// stats
		h += '<div class="wbtmf-stats">' +
			stat('red', '🚌', total, t('total', 'Total Buses')) +
			stat('green', '✅', published, t('published', 'Published')) +
			stat('blue', '❄️', ac, t('ac', 'AC Coach')) +
			stat('orange', '💨', nonac, t('nonac', 'Non AC Coach')) +
			'</div>';

		// filters: status pills (native links) + search + type
		var pills = '';
		if (meta.tabs.length) {
			meta.tabs.forEach(function (tb) {
				pills += '<a class="wbtmf-pill' + (tb.current ? ' active' : '') + '" href="' + escA(tb.href) + '">' +
					esc(tb.label) + (tb.count ? ' ' + esc(tb.count) : '') + '</a>';
			});
		} else {
			pills = '<span class="wbtmf-pill active">' + esc(t('allTab', 'All')) + '</span>';
		}
		h += '<div class="wbtmf-filters">' +
			'<div class="wbtmf-pills">' + pills + '</div>' +
			'<div class="wbtmf-search">' + ICON.search +
				'<input type="text" id="wbtmf-search" placeholder="' + escA(t('searchPh', 'Search buses...')) + '"></div>' +
			'<select class="wbtmf-select" id="wbtmf-type">' +
				'<option value="">' + esc(t('allTypes', 'All Types')) + '</option>' +
				'<option value="ac">' + esc(t('acOpt', 'AC')) + '</option>' +
				'<option value="nonac">' + esc(t('nonacOpt', 'Non AC')) + '</option>' +
			'</select></div>';

		// grid
		h += '<div class="wbtmf-grid" id="wbtmf-grid">';
		if (!rows.length) {
			h += '<div class="wbtmf-empty">' + esc(t('empty', 'No buses found.')) + '</div>';
		} else {
			rows.forEach(function (r) { h += card(r); });
		}
		h += '</div>';

		// list/table
		h += '<table class="wbtmf-table"><thead><tr>' +
			'<th>' + esc(t('cName', 'Bus Name')) + '</th>' +
			'<th>' + esc(t('cCoach', 'Coach No')) + '</th>' +
			'<th>' + esc(t('cType', 'Bus Type')) + '</th>' +
			'<th>' + esc(t('cCoachType', 'Coach Type')) + '</th>' +
			'<th>' + esc(t('cStatus', 'Status')) + '</th>' +
			'<th>' + esc(t('cActions', 'Actions')) + '</th>' +
			'</tr></thead><tbody>';
		rows.forEach(function (r) { h += rowHtml(r); });
		h += '</tbody></table>';

		// pagination
		h += '<div class="wbtmf-pag">' +
			'<div class="wbtmf-pag-info" id="wbtmf-pag-info">' + esc(meta.count || (rows.length + ' items')) + '</div>' +
			(meta.pagHtml ? '<div class="wbtmf-pag-btns">' + meta.pagHtml + '</div>' : '') +
			'</div>';
		return h;
	}

	function stat(ic, glyph, num, lab) {
		return '<div class="wbtmf-stat"><div class="wbtmf-stat-ic ' + ic + '">' + glyph + '</div>' +
			'<div><div class="wbtmf-stat-num">' + num + '</div><div class="wbtmf-stat-lab">' + esc(lab) + '</div></div></div>';
	}

	function card(r) {
		var typeCls = r.type === 'ac' ? 'ac' : (r.type === 'nonac' ? 'nonac' : 'plan');
		var thumb = r.thumb
			? '<img src="' + escA(r.thumb) + '" alt="' + escA(r.title) + '">'
			: '<span class="wbtmf-thumb-ph">🚌</span>';
		var badges = '';
		if (r.coachType && r.coachType !== '-') { badges += '<span class="wbtmf-tb ' + typeCls + '">' + esc(r.coachType) + '</span>'; }
		if (r.busType) { badges += '<span class="wbtmf-tb plan">' + esc(r.busType) + '</span>'; }
		var metaPills = '';
		if (r.busType) { metaPills += '<span class="wbtmf-mp type">' + esc(r.busType) + '</span>'; }
		if (r.coachType && r.coachType !== '-') {
			metaPills += '<span class="wbtmf-mp ' + (r.type === 'nonac' ? 'nonac' : 'ac') + '">' + esc(r.coachType + (r.type ? ' Coach' : '')) + '</span>';
		}
		return '<div class="wbtmf-card" data-name="' + escA(r.title.toLowerCase()) + '" data-type="' + escA(r.type) + '" data-edit="' + escA(r.editUrl) + '">' +
			'<div class="wbtmf-thumb">' + thumb + '<div class="wbtmf-thumb-ov"></div>' +
				'<div class="wbtmf-badges">' + badges + '</div>' +
				(r.coachNo ? '<span class="wbtmf-coachno">' + esc(r.coachNo) + '</span>' : '') +
				'<div class="wbtmf-actions">' +
					(r.editUrl ? '<a class="wbtmf-abtn edit" href="' + escA(r.editUrl) + '" title="' + escA(t('edit', 'Edit')) + '">' + ICON.edit + '</a>' : '') +
					(r.trashUrl ? '<a class="wbtmf-abtn del" href="' + escA(r.trashUrl) + '" title="' + escA(t('trash', 'Trash')) + '">' + ICON.trash + '</a>' : '') +
				'</div>' +
			'</div>' +
			'<div class="wbtmf-body"><div class="wbtmf-name">' + esc(r.title) + '</div>' +
				'<div class="wbtmf-meta">' + metaPills + '</div>' +
				'<div class="wbtmf-foot">' +
					'<div class="wbtmf-author"><span class="wbtmf-avatar">' + esc(r.initials) + '</span>' + esc(r.author) + '</div>' +
					'<span class="wbtmf-status' + (r.isDraft ? ' draft' : '') + '">' + esc(r.status) + '</span>' +
				'</div>' +
			'</div></div>';
	}

	function rowHtml(r) {
		var ctCls = r.type === 'nonac' ? 'nonac' : 'ac';
		return '<tr class="wbtmf-row" data-name="' + escA(r.title.toLowerCase()) + '" data-type="' + escA(r.type) + '" data-edit="' + escA(r.editUrl) + '">' +
			'<td>' + esc(r.title) + '</td>' +
			'<td>' + esc(r.coachNo || '-') + '</td>' +
			'<td>' + (r.busType ? '<span class="wbtmf-tbadge type">' + esc(r.busType) + '</span>' : '-') + '</td>' +
			'<td>' + (r.coachType && r.coachType !== '-' ? '<span class="wbtmf-tbadge ' + ctCls + '">' + esc(r.coachType) + '</span>' : '-') + '</td>' +
			'<td><span class="wbtmf-status' + (r.isDraft ? ' draft' : '') + '">' + esc(r.status) + '</span></td>' +
			'<td>' + (r.editUrl ? '<a class="wbtmf-tedit" href="' + escA(r.editUrl) + '">' + esc(t('edit', 'Edit')) + '</a>' : '') + '</td>' +
			'</tr>';
	}

	/* ---------- events ---------- */
	function wireEvents(root, meta) {
		var grid = root.querySelector('#wbtmf-grid');
		var search = root.querySelector('#wbtmf-search');
		var type = root.querySelector('#wbtmf-type');
		var info = root.querySelector('#wbtmf-pag-info');
		var totalItems = root.querySelectorAll('.wbtmf-card').length;

		// view toggle
		setActiveTog(root);
		Array.prototype.forEach.call(root.querySelectorAll('.wbtmf-vtog'), function (b) {
			b.addEventListener('click', function () {
				var v = b.getAttribute('data-view');
				root.classList.toggle('is-list', v === 'list');
				try { window.localStorage.setItem('wbtm_bus_view', v); } catch (e) {}
				setActiveTog(root);
			});
		});

		// open edit (overlay) when a card / row is clicked (but not on a real link/button)
		root.addEventListener('click', function (e) {
			if (e.target.closest('a')) { return; }            // let edit/trash links work
			var host = e.target.closest('.wbtmf-card, .wbtmf-row');
			if (!host) { return; }
			var url = host.getAttribute('data-edit');
			if (!url) { return; }
			var a = document.createElement('a');     // route through an <a> so the overlay JS intercepts it
			a.href = url; a.style.display = 'none';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
		});

		// client filter (search + type) over the current page
		function applyFilter() {
			var q = (search.value || '').toLowerCase().trim();
			var ty = type.value;
			var shown = 0;
			Array.prototype.forEach.call(root.querySelectorAll('.wbtmf-card, .wbtmf-row'), function (el) {
				var ok = (!q || (el.getAttribute('data-name') || '').indexOf(q) !== -1) &&
					(!ty || el.getAttribute('data-type') === ty);
				el.classList.toggle('wbtmf-hide', !ok);
				if (el.classList.contains('wbtmf-card') && ok) { shown++; }
			});
			if (info) {
				info.textContent = (q || ty)
					? t('showing', 'Showing %1 of %2').replace('%1', shown).replace('%2', totalItems)
					: (meta.count || (totalItems + ' items'));
			}
		}
		if (search) { search.addEventListener('input', applyFilter); }
		if (type) { type.addEventListener('change', applyFilter); }
	}

	function setActiveTog(root) {
		var list = root.classList.contains('is-list');
		Array.prototype.forEach.call(root.querySelectorAll('.wbtmf-vtog'), function (b) {
			b.classList.toggle('active', b.getAttribute('data-view') === (list ? 'list' : 'grid'));
		});
	}
})();
