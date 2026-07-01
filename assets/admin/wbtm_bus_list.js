/* ==========================================================================
   WBTM Bus Fleet - list design interactions
   Search, type filter, status tabs, client-side pagination.
   ========================================================================== */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var fleet = document.querySelector('.wbtm-fleet');
		if (!fleet) {
			return;
		}

		var table     = document.getElementById('wbtmBusTable');
		var searchEl  = document.getElementById('wbtmSearchInput');
		var searchBox = searchEl ? searchEl.closest('.wbtm-search-box') : null;
		var clearBtn  = document.getElementById('wbtmSearchClear');
		var typeEl    = document.getElementById('wbtmTypeFilter');
		var emptyMsg  = document.getElementById('wbtmEmptyMsg');
		var pageInfo  = document.getElementById('wbtmPageInfo');
		var pageBtns  = document.getElementById('wbtmPageBtns');
		var tabs      = Array.prototype.slice.call(document.querySelectorAll('.wbtm-filter-pill'));

		if (!table) {
			return;
		}

		table.style.display = 'table';

		var PER_PAGE = 12;
		var rows     = Array.prototype.slice.call(table.querySelectorAll('tr.wbtm-row'));

		var state = {
			search: '',
			type:   '',
			status: '',
			page:   1
		};

		/* ---- Matching --------------------------------------------------- */
		function matches(el) {
			var name   = (el.getAttribute('data-name') || '');
			var type   = (el.getAttribute('data-type') || '');
			var status = (el.getAttribute('data-status') || '');
			if (state.search && name.indexOf(state.search) === -1) { return false; }
			if (state.type && type !== state.type) { return false; }
			if (state.status && status !== state.status) { return false; }
			return true;
		}

		/* ---- Render with pagination ------------------------------------ */
		function render() {
			var matched = rows.filter(matches);
			var total   = matched.length;
			var pages   = Math.max(1, Math.ceil(total / PER_PAGE));
			if (state.page > pages) { state.page = pages; }
			var start = (state.page - 1) * PER_PAGE;
			var end   = start + PER_PAGE;

			rows.forEach(function (el) { el.style.display = 'none'; });
			matched.forEach(function (el, i) {
				if (i >= start && i < end) { el.style.display = ''; }
			});

			if (emptyMsg) { emptyMsg.style.display = total === 0 ? 'block' : 'none'; }

			renderPageInfo(total, start, end);
			renderPageButtons(pages);
		}

		function renderPageInfo(total, start, end) {
			if (!pageInfo) { return; }
			if (total === 0) { pageInfo.textContent = '0 buses'; return; }
			pageInfo.textContent = 'Showing ' + (start + 1) + '-' + Math.min(end, total) + ' of ' + total + ' buses';
		}

		function makeBtn(label, page, opts) {
			opts = opts || {};
			var btn = document.createElement('button');
			btn.className = 'wbtm-page-btn' + (opts.active ? ' active' : '');
			btn.innerHTML = label;
			if (opts.disabled) {
				btn.disabled = true;
			} else {
				btn.addEventListener('click', function () {
					state.page = page;
					render();
					fleet.scrollIntoView({ behavior: 'smooth', block: 'start' });
				});
			}
			return btn;
		}

		function renderPageButtons(pages) {
			if (!pageBtns) { return; }
			pageBtns.innerHTML = '';
			if (pages <= 1) { return; }
			pageBtns.appendChild(makeBtn('&#8249;', state.page - 1, { disabled: state.page === 1 }));
			for (var p = 1; p <= pages; p++) {
				pageBtns.appendChild(makeBtn(String(p), p, { active: p === state.page }));
			}
			pageBtns.appendChild(makeBtn('&#8250;', state.page + 1, { disabled: state.page === pages }));
		}

		function resetAndRender() {
			state.page = 1;
			render();
		}

		/* ---- Live search ------------------------------------------------ */
		if (searchEl) {
			var onSearch = function () {
				state.search = searchEl.value.toLowerCase().trim();
				if (searchBox) {
					searchBox.classList.toggle('has-value', searchEl.value.length > 0);
				}
				resetAndRender();
			};
			searchEl.addEventListener('input', onSearch);
			searchEl.addEventListener('search', onSearch);
			if (searchBox) {
				searchEl.addEventListener('focus', function () { searchBox.classList.add('is-focused'); });
				searchEl.addEventListener('blur',  function () { searchBox.classList.remove('is-focused'); });
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					searchEl.value = '';
					searchEl.focus();
					onSearch();
				});
			}
		}

		/* ---- Custom styled dropdown (type filter) ---------------------- */
		function buildDropdown(select) {
			var options = Array.prototype.slice.call(select.options);

			var wrap = document.createElement('div');
			wrap.className = 'wbtm-dropdown';

			var toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'wbtm-dropdown-toggle';
			toggle.setAttribute('aria-haspopup', 'listbox');
			toggle.setAttribute('aria-expanded', 'false');

			var label = document.createElement('span');
			label.className = 'wbtm-dropdown-label';
			label.textContent = options[select.selectedIndex] ? options[select.selectedIndex].text : '';

			var caret = document.createElement('span');
			caret.className = 'wbtm-caret';
			caret.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';

			toggle.appendChild(label);
			toggle.appendChild(caret);

			var menu = document.createElement('div');
			menu.className = 'wbtm-dropdown-menu';
			menu.setAttribute('role', 'listbox');

			var check = '<span class="wbtm-check"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>';

			options.forEach(function (opt) {
				var item = document.createElement('div');
				item.className = 'wbtm-dropdown-option' + (opt.value === select.value ? ' selected' : '');
				item.setAttribute('role', 'option');
				item.setAttribute('data-value', opt.value);
				item.innerHTML = '<span>' + opt.text + '</span>' + check;
				item.addEventListener('click', function () {
					select.value = opt.value;
					label.textContent = opt.text;
					menu.querySelectorAll('.wbtm-dropdown-option').forEach(function (o) { o.classList.remove('selected'); });
					item.classList.add('selected');
					closeMenu();
					state.type = opt.value;
					resetAndRender();
				});
				menu.appendChild(item);
			});

			function openMenu()  { wrap.classList.add('open');    toggle.setAttribute('aria-expanded', 'true'); }
			function closeMenu() { wrap.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); }

			toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				wrap.classList.contains('open') ? closeMenu() : openMenu();
			});
			document.addEventListener('click',   function (e) { if (!wrap.contains(e.target)) { closeMenu(); } });
			document.addEventListener('keydown',  function (e) { if (e.key === 'Escape') { closeMenu(); } });

			wrap.appendChild(toggle);
			wrap.appendChild(menu);
			select.parentNode.insertBefore(wrap, select.nextSibling);
		}

		if (typeEl) { buildDropdown(typeEl); }

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) { t.classList.remove('active'); });
				this.classList.add('active');
				state.status = this.getAttribute('data-status') || '';
				resetAndRender();
			});
		});

		/* ---- Init ------------------------------------------------------- */
		render();
	});
})();
