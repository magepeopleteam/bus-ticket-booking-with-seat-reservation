/* global wp, wbtmAppHome */
(function () {
	'use strict';
	var root = document.querySelector('.wbtm-app-home');
	if (!root) return;
	var nextId = 0;

	function refreshRows(collection) {
		var rows = collection.querySelector('[data-rows]');
		Array.from(rows.children).forEach(function (row, index) {
			row.querySelector('[data-row-title]').textContent = collection.dataset.itemLabel + ' ' + (index + 1);
			row.querySelector('[data-up]').disabled = index === 0;
			row.querySelector('[data-down]').disabled = index === rows.children.length - 1;
		});
		collection.querySelector('[data-add]').disabled = rows.children.length >= Number(collection.dataset.limit);
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
