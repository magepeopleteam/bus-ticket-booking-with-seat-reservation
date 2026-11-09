/* global wp, wbtmAppHome */
(function () {
	'use strict';
	var root = document.querySelector('.wbtm-app-home');
	if (!root) return;
	var nextId = 0;
	var tabs = Array.from(root.querySelectorAll('[data-home-tab]'));
	var panels = Array.from(root.querySelectorAll('[data-home-panel]'));
	function showTab(key) {
		if (!tabs.some(function (tab) { return tab.dataset.homeTab === key; })) key = 'introduction';
		tabs.forEach(function (tab) { var active = tab.dataset.homeTab === key; tab.setAttribute('aria-selected', active ? 'true' : 'false'); tab.tabIndex = active ? 0 : -1; });
		panels.forEach(function (panel) { panel.hidden = panel.dataset.homePanel !== key; });
		var contentForm = root.querySelector('#wbtm-app-home-form');
		if (contentForm) contentForm.hidden = !Array.from(contentForm.querySelectorAll('[data-home-panel]')).some(function (panel) { return panel.dataset.homePanel === key; });
		try { sessionStorage.setItem('wbtm-app-home-tab', key); } catch (_error) { /* Navigation works without browser storage. */ }
		try { var url = new URL(window.location.href); url.searchParams.set('app_tab', key); window.history.replaceState(null, '', url.href); } catch (_error) { /* Tabs work without URL updates. */ }
	}
	if (tabs.length && panels.length) {
		root.classList.add('is-tabbed');
		root.querySelector('[data-home-tabs]').hidden = false;
		var selected = 'introduction';
		try { selected = sessionStorage.getItem('wbtm-app-home-tab') || selected; } catch (_error) { /* Use the first tab. */ }
		selected = root.dataset.initialTab || selected;
		showTab(selected);
		tabs.forEach(function (tab, index) {
			tab.addEventListener('click', function () { showTab(tab.dataset.homeTab); });
			tab.addEventListener('keydown', function (event) {
				var next;
				if (event.key === 'ArrowDown' || event.key === 'ArrowRight') next = (index + 1) % tabs.length;
				else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
				else if (event.key === 'Home') next = 0;
				else if (event.key === 'End') next = tabs.length - 1;
				if (next === undefined) return;
				event.preventDefault(); showTab(tabs[next].dataset.homeTab); tabs[next].focus();
			});
		});
		root.addEventListener('invalid', function (event) { var panel = event.target.closest('[data-home-panel]'); if (panel) showTab(panel.dataset.homePanel); }, true);
	}

	function refreshRows(collection) {
		var rows = collection.querySelector('[data-rows]');
		Array.from(rows.children).forEach(function (row, index) {
			row.querySelector('[data-row-title]').textContent = collection.dataset.itemLabel + ' ' + (index + 1);
			row.querySelector('[data-up]').disabled = index === 0;
			row.querySelector('[data-down]').disabled = index === rows.children.length - 1;
		});
		collection.querySelector('[data-add]').disabled = rows.children.length >= Number(collection.dataset.limit);
		var count = root.querySelector('[data-count-for="' + collection.dataset.collection + '"]');
		if (count) count.textContent = String(rows.children.length);
	}

	function refreshOrder() {
		var rows = root.querySelector('[data-order-list]').children;
		Array.from(rows).forEach(function (row, index) {
			row.querySelector('[data-up]').disabled = index === 0;
			row.querySelector('[data-down]').disabled = index === rows.length - 1;
		});
	}

	function refreshPreview(field) {
		var input = field.querySelector('[data-image-url]');
		var preview = field.querySelector('[data-image-preview]');
		try {
			var url = new URL(input.value);
			if (!/^https?:$/.test(url.protocol) || url.username || url.password) throw new Error('Invalid image URL');
			preview.src = url.href;
			preview.hidden = false;
		} catch (_error) {
			preview.hidden = true;
			preview.removeAttribute('src');
		}
	}

	root.addEventListener('click', function (event) {
		var button = event.target.closest('button');
		if (!button || !root.contains(button)) return;
		if (button.hasAttribute('data-copy-connection')) {
			var code = root.querySelector('[data-connection-code]');
			var status = root.querySelector('[data-copy-status]');
			if (!code || !status) return;
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(code.value).then(function () { status.textContent = wbtmAppHome.codeCopied; }, function () { code.focus(); code.select(); status.textContent = wbtmAppHome.codeCopyFallback; });
			} else { code.focus(); code.select(); status.textContent = wbtmAppHome.codeCopyFallback; }
			return;
		}
		var collection = button.closest('[data-collection]');
		var item = button.closest('[data-item], [data-order-row]');
		if (button.hasAttribute('data-add')) {
			var rows = collection.querySelector('[data-rows]');
			if (rows.children.length >= Number(collection.dataset.limit)) return;
			var fragment = collection.querySelector('[data-template]').content.cloneNode(true);
			var index = 'new' + Date.now() + (++nextId);
			fragment.querySelectorAll('[name]').forEach(function (input) {
				input.name = input.name.replace('__INDEX__', index);
			});
			fragment.querySelector('[data-item-id]').value = index;
			var firstInput = fragment.querySelector('input:not([type="hidden"]), textarea, select');
			rows.appendChild(fragment);
			refreshRows(collection);
			if (firstInput) firstInput.focus();
		} else if (button.hasAttribute('data-remove')) {
			item.remove();
			refreshRows(collection);
			collection.querySelector('[data-add]').focus();
		} else if (button.hasAttribute('data-up') || button.hasAttribute('data-down')) {
			var upward = button.hasAttribute('data-up');
			var adjacent = upward ? item.previousElementSibling : item.nextElementSibling;
			if (adjacent) {
				if (upward) item.parentNode.insertBefore(item, adjacent);
				else item.parentNode.insertBefore(adjacent, item);
			}
			if (collection) refreshRows(collection);
			else refreshOrder();
			button.focus();
		} else if (button.hasAttribute('data-image-choose')) {
			var field = button.closest('.wbtm-app-home-field');
			// Media APIs are provided by wp_enqueue_media(); no custom upload endpoint.
			// https://developer.wordpress.org/reference/functions/wp_enqueue_media/
			var frame = wp.media({
				title: wbtmAppHome.mediaTitle,
				button: { text: wbtmAppHome.mediaButton },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				field.querySelector('[data-image-url]').value = attachment.url || '';
				refreshPreview(field);
			});
			frame.open();
		} else if (button.hasAttribute('data-image-clear')) {
			var imageField = button.closest('.wbtm-app-home-field');
			imageField.querySelector('[data-image-url]').value = '';
			refreshPreview(imageField);
		}
	});
	root.addEventListener('change', function (event) {
		if (event.target.hasAttribute('data-image-url')) refreshPreview(event.target.closest('.wbtm-app-home-field'));
	});
	root.querySelectorAll('[data-collection]').forEach(refreshRows);
	refreshOrder();
})();
