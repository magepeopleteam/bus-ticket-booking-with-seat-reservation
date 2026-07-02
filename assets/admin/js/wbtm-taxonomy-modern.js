/**
 * Modern reskin, shared by every bus-related taxonomy screen (Bus Type,
 * Bus Stops, Pickup Point, Drop-Off Point, Bus Features — edit-tags.php /
 * term.php). Only enqueued on those exact screens, one taxonomy at a time
 * — see WBTM_Taxonomy_Modern::enqueue(). All copy (heading, button/modal
 * text, placeholders) comes from window.wbtmTaxonomyModern, localized per
 * taxonomy server-side, so this file itself has no taxonomy-specific text.
 *
 * Four jobs:
 *  1. Swap in the friendlier heading copy.
 *  2. Replace the term search box with an "Add New {Item}" button that
 *     opens an AJAX popup (posts to wp_ajax_wbtm_add_bus_type), instead of
 *     the inline sidebar form WordPress renders by default.
 *  3. Repurpose that now-unused sidebar column as a Pro/addons promo panel,
 *     and flip the layout so the term table is the wide main column with
 *     the promo as a narrow sidebar on the right.
 *  4. Turn the Edit/Delete row links into icon buttons that open the same
 *     popup (edit) or confirm-then-AJAX-delete (delete) — neither ever
 *     navigates away from this screen.
 */
(function () {
	'use strict';

	var cfg = window.wbtmTaxonomyModern || {};
	var strings = cfg.strings || {};

	function el(tag, attrs, html) {
		var node = document.createElement(tag);
		attrs = attrs || {};
		Object.keys(attrs).forEach(function (key) {
			node.setAttribute(key, attrs[key]);
		});
		if (html !== undefined) {
			node.innerHTML = html;
		}
		return node;
	}

	/* ---------------------------------------------------------------- *
	 *  Heading
	 * ---------------------------------------------------------------- */

	function swapHeading(wrap) {
		var heading = wrap.querySelector('h1.wp-heading-inline');
		if (!heading || !cfg.heading) {
			return;
		}
		heading.textContent = cfg.heading;

		if (cfg.subheading && !wrap.querySelector('.wbtm-taxonomy-subtitle')) {
			var subtitle = el('p', { class: 'wbtm-taxonomy-subtitle' });
			subtitle.textContent = cfg.subheading;
			heading.insertAdjacentElement('afterend', subtitle);
		}
	}

	/* ---------------------------------------------------------------- *
	 *  Search box -> "Add New Bus Type" button, docked into the top
	 *  tablenav's right side, next to the item count — same row as
	 *  Bulk actions / Apply, sized and aligned to match them.
	 * ---------------------------------------------------------------- */

	function replaceSearchWithAddButton(wrap) {
		var searchBox = wrap.querySelector('p.search-box');
		if (searchBox) {
			searchBox.remove();
		}

		var tablenavTop = wrap.querySelector('#col-right .tablenav.top');
		if (!tablenavTop) {
			return;
		}

		var button = el('button', { type: 'button', class: 'button button-primary wbtm-add-bus-type-btn' });
		button.textContent = cfg.addButtonLabel || 'Add New Bus Type';
		button.addEventListener('click', openAddModal);

		var right = el('div', { class: 'wbtm-tablenav-right' });
		var pages = tablenavTop.querySelector('.tablenav-pages');
		if (pages) {
			right.appendChild(pages);
		}
		right.appendChild(button);
		tablenavTop.appendChild(right);
	}

	/* ---------------------------------------------------------------- *
	 *  Sidebar: add-term form -> Pro/addons promo panel
	 * ---------------------------------------------------------------- */

	function buildPromoPanel(wrap) {
		var colWrap = wrap.querySelector('#col-left .col-wrap');
		if (!colWrap) {
			return;
		}

		var features = (cfg.proFeatures || []).map(function (feature) {
			return '<li>' + feature + '</li>';
		}).join('');

		colWrap.innerHTML =
			'<div class="wbtm-promo-panel">' +
				'<span class="wbtm-promo-eyebrow">' + (strings.promoEyebrow || '') + '</span>' +
				'<h2>' + (strings.promoTitle || '') + '</h2>' +
				'<p class="wbtm-promo-body">' + (strings.promoBody || '') + '</p>' +
				'<ul class="wbtm-promo-features">' + features + '</ul>' +
				'<a class="button button-primary wbtm-promo-cta" href="' + (cfg.proUrl || '#') + '">' + (strings.promoCta || '') + '</a>' +
			'</div>';
	}

	/* ---------------------------------------------------------------- *
	 *  Table: dedicated icon-button Actions column (instead of the
	 *  hover-reveal text links WordPress renders under the term name),
	 *  and a pill treatment for the slug column. The actual Edit/Delete
	 *  anchors are *moved*, not cloned, so their nonced hrefs and any
	 *  core JS bound to their classes (the delete confirm dialog) keep
	 *  working exactly as before — only their position and look change.
	 * ---------------------------------------------------------------- */

	/** Drops the repeated header row WordPress renders in <tfoot> — with
	 *  only two bus types, a second header at the bottom is just clutter. */
	function removeTableFooter(wrap) {
		var tfoot = wrap.querySelector('#col-right table.wp-list-table tfoot');
		if (tfoot) {
			tfoot.remove();
		}
	}

	/** WordPress ids each row "tag-{term_id}" — that's the only place the
	 *  term id is available once the row has been rebuilt. */
	function getRowTermId(row) {
		var match = /^tag-(\d+)$/.exec(row.id || '');
		return match ? parseInt(match[1], 10) : 0;
	}

	function buildActionsColumn(wrap) {
		var table = wrap.querySelector('#col-right table.wp-list-table');
		if (!table) {
			return;
		}

		var label = strings.actionsColumn || 'Actions';

		// Last column, after Count. Column widths are fixed percentages
		// (see CSS) so this always has room without needing to scroll.
		table.querySelectorAll('thead tr').forEach(function (headerRow) {
			var th = el('th', { scope: 'col', class: 'manage-column column-wbtm-actions' });
			th.textContent = label;
			headerRow.appendChild(th);
		});

		table.querySelectorAll('tbody tr').forEach(function (row) {
			var td = el('td', { class: 'column-wbtm-actions', 'data-colname': label });
			var rowActions = row.querySelector('.row-actions');
			var termId = getRowTermId(row);

			if (rowActions) {
				var editLink = rowActions.querySelector('.edit a');
				var deleteLink = rowActions.querySelector('.delete a');

				// Both anchors are moved (not cloned) so their aria-labels
				// carry over, but the click is now fully handled in JS —
				// opens the same popup as Add (edit) or confirms + calls
				// our own AJAX action (delete) — neither ever navigates.
				if (editLink) {
					editLink.classList.add('wbtm-row-action-icon', 'wbtm-row-action-edit');
					editLink.innerHTML = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
					editLink.addEventListener('click', function (evt) {
						evt.preventDefault();
						openEditModal(termId);
					});
					td.appendChild(editLink);
				}
				if (deleteLink) {
					deleteLink.classList.add('wbtm-row-action-icon', 'wbtm-row-action-delete');
					deleteLink.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
					deleteLink.addEventListener('click', function (evt) {
						evt.preventDefault();
						deleteBusType(termId, deleteLink);
					});
					td.appendChild(deleteLink);
				}
				// Quick Edit / View stay reachable via keyboard on the row
				// itself; the hover-text list they lived in is redundant
				// with the icon column now, so it's hidden via CSS.
			}

			row.appendChild(td);
		});
	}

	/**
	 * Drops multi-select entirely: the checkbox column (header, rows,
	 * footer) and the "Bulk actions / Apply" controls in both tablenavs —
	 * redundant now that every row has its own Edit/Delete icons.
	 */
	function removeBulkSelection(wrap) {
		var table = wrap.querySelector('#col-right table.wp-list-table');
		if (table) {
			table.querySelectorAll('.check-column').forEach(function (cell) {
				cell.remove();
			});
		}
		wrap.querySelectorAll('#col-right .tablenav .actions.bulkactions').forEach(function (bulk) {
			bulk.remove();
		});
	}

	function badgeSlugs(wrap) {
		wrap.querySelectorAll('#col-right table.wp-list-table tbody td.column-slug').forEach(function (cell) {
			var text = cell.textContent.trim();
			if (!text || cell.querySelector('.wbtm-slug-badge')) {
				return;
			}
			cell.innerHTML = '';
			var badge = el('span', { class: 'wbtm-slug-badge' });
			badge.textContent = text;
			cell.appendChild(badge);
		});
	}

	/* ---------------------------------------------------------------- *
	 *  Popup — shared by Add (button) and Edit (pencil icon). Same form,
	 *  different title/submit label/AJAX action; Edit additionally does a
	 *  quick round trip on open to fetch fresh field values + a parent
	 *  list with the term's own subtree excluded.
	 * ---------------------------------------------------------------- */

	var modalEls = null;
	var modalState = { mode: 'add', termId: 0 };

	function buildParentOptions(select, options) {
		select.innerHTML = '';
		select.appendChild(el('option', { value: '0' }, strings.parentNone || 'None'));
		(options || cfg.parentOptions || []).forEach(function (term) {
			var opt = el('option', { value: String(term.id) });
			opt.textContent = term.name;
			select.appendChild(opt);
		});
	}

	function buildModal() {
		if (modalEls) {
			return modalEls;
		}

		var overlay = el('div', { class: 'wbtm-modal-overlay wbtm-hidden' });
		var dialog = el('div', { class: 'wbtm-modal', role: 'dialog', 'aria-modal': 'true' });
		overlay.appendChild(dialog);

		dialog.innerHTML =
			'<div class="wbtm-modal-header">' +
				'<h2>' + (strings.modalTitle || '') + '</h2>' +
				'<button type="button" class="wbtm-modal-close" aria-label="' + (strings.cancel || 'Cancel') + '">&times;</button>' +
			'</div>' +
			'<div class="wbtm-modal-body">' +
				'<p class="wbtm-modal-error wbtm-hidden"></p>' +
				'<div class="form-field">' +
					'<label for="wbtm-modal-name">' + (strings.name || '') + '</label>' +
					'<input type="text" id="wbtm-modal-name" placeholder="' + (strings.namePlaceholder || '') + '" />' +
					'<p>' + (strings.nameHelp || '') + '</p>' +
				'</div>' +
				'<div class="form-field">' +
					'<label for="wbtm-modal-slug">' + (strings.slug || '') + '</label>' +
					'<input type="text" id="wbtm-modal-slug" placeholder="' + (strings.slugPlaceholder || '') + '" />' +
					'<p>' + (strings.slugHelp || '') + '</p>' +
				'</div>' +
				'<div class="form-field">' +
					'<label for="wbtm-modal-parent">' + (strings.parent || '') + '</label>' +
					'<select id="wbtm-modal-parent"></select>' +
					'<p>' + (strings.parentHelp || '') + '</p>' +
				'</div>' +
				'<div class="form-field">' +
					'<label for="wbtm-modal-description">' + (strings.description || '') + '</label>' +
					'<textarea id="wbtm-modal-description" placeholder="' + (strings.descPlaceholder || '') + '"></textarea>' +
					'<p>' + (strings.descHelp || '') + '</p>' +
				'</div>' +
			'</div>' +
			'<div class="wbtm-modal-footer">' +
				'<button type="button" class="button wbtm-modal-cancel">' + (strings.cancel || '') + '</button>' +
				'<button type="button" class="button button-primary wbtm-modal-submit">' + (strings.submit || '') + '</button>' +
			'</div>';

		document.body.appendChild(overlay);

		buildParentOptions(dialog.querySelector('#wbtm-modal-parent'));

		var titleEl = dialog.querySelector('.wbtm-modal-header h2');
		var closeBtn = dialog.querySelector('.wbtm-modal-close');
		var cancelBtn = dialog.querySelector('.wbtm-modal-cancel');
		var submitBtn = dialog.querySelector('.wbtm-modal-submit');

		closeBtn.addEventListener('click', closeModal);
		cancelBtn.addEventListener('click', closeModal);
		overlay.addEventListener('click', function (evt) {
			if (evt.target === overlay) {
				closeModal();
			}
		});
		submitBtn.addEventListener('click', submitModal);

		modalEls = { overlay: overlay, dialog: dialog, submitBtn: submitBtn, titleEl: titleEl };
		return modalEls;
	}

	function showError(message) {
		var errorEl = modalEls.dialog.querySelector('.wbtm-modal-error');
		errorEl.textContent = message;
		errorEl.classList.remove('wbtm-hidden');
	}

	function clearError() {
		var errorEl = modalEls.dialog.querySelector('.wbtm-modal-error');
		errorEl.textContent = '';
		errorEl.classList.add('wbtm-hidden');
	}

	function resetModalFields(modal) {
		clearError();
		modal.dialog.querySelector('#wbtm-modal-name').value = '';
		modal.dialog.querySelector('#wbtm-modal-slug').value = '';
		modal.dialog.querySelector('#wbtm-modal-description').value = '';
		buildParentOptions(modal.dialog.querySelector('#wbtm-modal-parent'), cfg.parentOptions);
	}

	function openAddModal() {
		var modal = buildModal();
		modalState = { mode: 'add', termId: 0 };
		resetModalFields(modal);
		modal.titleEl.textContent = strings.modalTitle || 'Add New Bus Type';
		modal.submitBtn.disabled = false;
		modal.submitBtn.textContent = strings.submit || 'Add New Bus Type';
		modal.overlay.classList.remove('wbtm-hidden');
		modal.dialog.querySelector('#wbtm-modal-name').focus();
	}

	function openEditModal(termId) {
		if (!termId) {
			return;
		}
		var modal = buildModal();
		modalState = { mode: 'edit', termId: termId };
		resetModalFields(modal);
		modal.titleEl.textContent = strings.editModalTitle || 'Edit Bus Type';
		modal.submitBtn.textContent = strings.saveChanges || 'Save Changes';
		modal.submitBtn.disabled = true;
		modal.dialog.querySelector('#wbtm-modal-name').value = strings.loadingTerm || 'Loading…';
		modal.overlay.classList.remove('wbtm-hidden');

		var body = new URLSearchParams();
		body.set('action', 'wbtm_get_bus_type');
		body.set('nonce', cfg.getNonce || '');
		body.set('taxonomy', cfg.taxonomy || '');
		body.set('term_id', String(termId));

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				// The modal may have been closed and reopened for something
				// else while this was in flight — ignore a stale response.
				if (modalState.mode !== 'edit' || modalState.termId !== termId) {
					return;
				}
				if (!json || !json.success) {
					var message = (json && json.data && json.data.message) ? json.data.message : (strings.genericError || 'Something went wrong.');
					showError(message);
					modal.dialog.querySelector('#wbtm-modal-name').value = '';
					return;
				}
				var data = json.data;
				modal.dialog.querySelector('#wbtm-modal-name').value = data.name || '';
				modal.dialog.querySelector('#wbtm-modal-slug').value = data.slug || '';
				modal.dialog.querySelector('#wbtm-modal-description').value = data.description || '';
				buildParentOptions(modal.dialog.querySelector('#wbtm-modal-parent'), data.parentOptions);
				modal.dialog.querySelector('#wbtm-modal-parent').value = String(data.parent || 0);
				modal.submitBtn.disabled = false;
			})
			.catch(function () {
				if (modalState.mode === 'edit' && modalState.termId === termId) {
					showError(strings.genericError || 'Something went wrong.');
					modal.dialog.querySelector('#wbtm-modal-name').value = '';
				}
			});
	}

	function closeModal() {
		if (modalEls) {
			modalEls.overlay.classList.add('wbtm-hidden');
		}
	}

	function submitModal() {
		var dialog = modalEls.dialog;
		var name = dialog.querySelector('#wbtm-modal-name').value.trim();
		var isEdit = modalState.mode === 'edit';
		clearError();

		if (!name) {
			showError(strings.nameRequired || 'Name is required.');
			return;
		}

		var body = new URLSearchParams();
		body.set('action', isEdit ? 'wbtm_edit_bus_type' : 'wbtm_add_bus_type');
		body.set('nonce', (isEdit ? cfg.editNonce : cfg.nonce) || '');
		body.set('taxonomy', cfg.taxonomy || '');
		if (isEdit) {
			body.set('term_id', String(modalState.termId));
		}
		body.set('tag-name', name);
		body.set('slug', dialog.querySelector('#wbtm-modal-slug').value.trim());
		body.set('parent', dialog.querySelector('#wbtm-modal-parent').value);
		body.set('description', dialog.querySelector('#wbtm-modal-description').value);

		modalEls.submitBtn.disabled = true;
		modalEls.submitBtn.textContent = isEdit ? (strings.saving || 'Saving…') : (strings.submitting || 'Adding…');

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json && json.success) {
					window.location.reload();
					return;
				}
				var message = (json && json.data && json.data.message) ? json.data.message : (strings.genericError || 'Something went wrong.');
				showError(message);
			})
			.catch(function () {
				showError(strings.genericError || 'Something went wrong.');
			})
			.finally(function () {
				modalEls.submitBtn.disabled = false;
				modalEls.submitBtn.textContent = isEdit ? (strings.saveChanges || 'Save Changes') : (strings.submit || 'Add New Bus Type');
			});
	}

	/* ---------------------------------------------------------------- *
	 *  Delete — confirm, then AJAX. Never navigates: no bare link to
	 *  edit-tags.php?action=delete&... is ever followed.
	 * ---------------------------------------------------------------- */

	function deleteBusType(termId, triggerEl) {
		if (!termId) {
			return;
		}
		if (!window.confirm(strings.confirmDelete || 'Delete this bus type?')) {
			return;
		}

		var body = new URLSearchParams();
		body.set('action', 'wbtm_delete_bus_type');
		body.set('nonce', cfg.deleteNonce || '');
		body.set('taxonomy', cfg.taxonomy || '');
		body.set('term_id', String(termId));

		if (triggerEl) {
			triggerEl.style.pointerEvents = 'none';
			triggerEl.style.opacity = '.5';
		}

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json && json.success) {
					window.location.reload();
					return;
				}
				var message = (json && json.data && json.data.message) ? json.data.message : (strings.deleteFailed || 'Could not delete this bus type.');
				window.alert(message);
			})
			.catch(function () {
				window.alert(strings.deleteFailed || 'Could not delete this bus type.');
			})
			.finally(function () {
				if (triggerEl) {
					triggerEl.style.pointerEvents = '';
					triggerEl.style.opacity = '';
				}
			});
	}

	/* ---------------------------------------------------------------- *
	 *  Init
	 * ---------------------------------------------------------------- */

	function init() {
		var wrap = document.querySelector('.wrap');
		if (!wrap) {
			document.body.classList.add('wbtm-taxonomy-ready');
			return;
		}
		wrap.classList.add('wbtm-taxonomy-modern-wrap');

		// The CSS loading placeholder hides .wrap's contents from first paint
		// until 'wbtm-taxonomy-ready' lands on <body> — the try/finally makes
		// sure that happens even if one of these throws, so a bug here can
		// never leave the page stuck behind the loader.
		try {
			swapHeading(wrap);
			replaceSearchWithAddButton(wrap);
			buildPromoPanel(wrap);
			removeTableFooter(wrap);
			buildActionsColumn(wrap);
			removeBulkSelection(wrap);
			badgeSlugs(wrap);
		} finally {
			document.body.classList.add('wbtm-taxonomy-ready');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
