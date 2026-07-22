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
		button.addEventListener('click', function () {
			openAddModal(cfg.taxonomy);
		});

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
						openEditModal(cfg.taxonomy, termId);
					});
					td.appendChild(editLink);
				}
				if (deleteLink) {
					deleteLink.classList.add('wbtm-row-action-icon', 'wbtm-row-action-delete');
					deleteLink.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
					deleteLink.addEventListener('click', function (evt) {
						evt.preventDefault();
						deleteBusType(cfg.taxonomy, termId, deleteLink);
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

	/**
	 * The name is no longer a link to term.php — every action already has
	 * its own icon in the Actions column, so this just stops the name
	 * itself from navigating away. Keeps the same "row-title" class (a
	 * <span> now, not an <a>) so the existing color/weight styling and
	 * prependFeatureIcons() below still find and style it the same way.
	 */
	function removeNameLinks(wrap) {
		wrap.querySelectorAll('#col-right table.wp-list-table tbody td.column-name a.row-title').forEach(function (link) {
			var span = el('span', { class: 'row-title' });
			while (link.firstChild) {
				span.appendChild(link.firstChild);
			}
			link.replaceWith(span);
		});
	}

	/**
	 * Bus Features only: prepends the feature's chosen icon before its name
	 * in the list table — same "<span class='wbtm_bus_feature_icon {class}'>"
	 * convention already used in the bus-edit feature checklist, so the
	 * icon font (already loaded admin-wide) renders it identically here.
	 */
	function prependFeatureIcons(wrap) {
		if (!cfg.featureIcons) {
			return;
		}
		wrap.querySelectorAll('#col-right table.wp-list-table tbody tr').forEach(function (row) {
			var icon = cfg.featureIcons[String(getRowTermId(row))];
			if (!icon) {
				return;
			}
			var titleLink = row.querySelector('.column-name .row-title');
			if (!titleLink || titleLink.querySelector('.wbtm_bus_feature_icon')) {
				return;
			}
			titleLink.insertAdjacentElement('afterbegin', el('span', { class: 'wbtm_bus_feature_icon ' + icon }));
		});
	}

	/* ---------------------------------------------------------------- *
	 *  Popup — shared by Add (button) and Edit (pencil icon). Same form,
	 *  different title/submit label/AJAX action; Edit additionally does a
	 *  quick round trip on open to fetch fresh field values.
	 * ---------------------------------------------------------------- */

	var modalEls = null;
	var modalState = { mode: 'add', termId: 0 };

	function escapeHtml(value) {
		var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
		return String(value || '').replace(/[&<>"']/g, function (ch) {
			return map[ch];
		});
	}

	/** Options for the "Under Bus Stop" dropdown, built once from the bus stops localized server-side. */
	function buildBusStopOptions() {
		var stops = cfg.busStops || [];
		var options = '<option value="">' + escapeHtml(strings.underStopEmpty || '') + '</option>';
		stops.forEach(function (stop) {
			options += '<option value="' + stop.id + '">' + escapeHtml(stop.name) + '</option>';
		});
		return options;
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
				'<div class="form-field wbtm-slug-field">' +
					'<label for="wbtm-modal-slug">' + (strings.slug || '') + '</label>' +
					'<input type="text" id="wbtm-modal-slug" placeholder="' + (strings.slugPlaceholder || '') + '" />' +
					'<p>' + (strings.slugHelp || '') + '</p>' +
				'</div>' +
				'<div class="form-field wbtm-understop-field wbtm-hidden">' +
					'<label for="wbtm-modal-understop">' + (strings.underStop || 'Under Bus Stop') + '</label>' +
					'<select id="wbtm-modal-understop">' + buildBusStopOptions() + '</select>' +
				'</div>' +
				'<div class="form-field">' +
					'<label for="wbtm-modal-description">' + (strings.description || '') + '</label>' +
					'<textarea id="wbtm-modal-description" placeholder="' + (strings.descPlaceholder || '') + '"></textarea>' +
					'<p>' + (strings.descHelp || '') + '</p>' +
				'</div>' +
				(cfg.iconFieldHtml ?
					'<div class="form-field wbtm-icon-field">' +
						'<label>' + (strings.icon || 'Icon') + '</label>' +
						cfg.iconFieldHtml +
						'<p>' + (strings.iconHelp || '') + '</p>' +
					'</div>'
				: '') +
			'</div>' +
			'<div class="wbtm-modal-footer">' +
				'<button type="button" class="button wbtm-modal-cancel">' + (strings.cancel || '') + '</button>' +
				'<button type="button" class="button button-primary wbtm-modal-submit">' + (strings.submit || '') + '</button>' +
			'</div>';

		document.body.appendChild(overlay);

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

	/**
	 * Mirrors the show/hide logic WBTM_Select_Icon_image::load_icon() applies
	 * server-side based on whether an icon is already set — the injected
	 * markup always starts in the "no icon" state (it was rendered once,
	 * empty, for Add mode), so Edit has to replicate the toggle in JS.
	 */
	function setIconFieldState(modal, iconClass) {
		var area = modal.dialog.querySelector('.wbtm-icon-field .wbtm_add_icon_image_area');
		if (!area) {
			return;
		}
		var hiddenInput = area.querySelector('input[type="hidden"]');
		var preview = area.querySelector('[data-add-icon]');
		var previewItem = area.querySelector('.wbtm_icon_item');
		var addButtonArea = area.querySelector('.wbtm_add_icon_image_button_area');

		if (hiddenInput) {
			hiddenInput.value = iconClass || '';
		}
		if (preview) {
			preview.setAttribute('class', iconClass || '');
		}
		if (previewItem) {
			previewItem.style.display = iconClass ? '' : 'none';
		}
		if (addButtonArea) {
			addButtonArea.style.display = iconClass ? 'none' : '';
		}
	}

	function getIconFieldValue(dialog) {
		var input = dialog.querySelector('.wbtm-icon-field .wbtm_add_icon_image_area input[type="hidden"]');
		return input ? input.value : '';
	}

	function resetModalFields(modal) {
		clearError();
		modal.dialog.querySelector('#wbtm-modal-name').value = '';
		modal.dialog.querySelector('#wbtm-modal-slug').value = '';
		modal.dialog.querySelector('#wbtm-modal-description').value = '';
		var understopSelect = modal.dialog.querySelector('#wbtm-modal-understop');
		if (understopSelect) {
			understopSelect.value = '';
		}
		setIconFieldState(modal, '');
	}

	/**
	 * Popup copy for one taxonomy. The host screen's own taxonomy (cfg.taxonomy)
	 * is always in cfg.perTaxonomy; a merged section's taxonomy (e.g. Drop-Off
	 * Point rendered on the Pickup Point screen) is there too, added server-side
	 * by WBTM_Taxonomy_Modern::enqueue(). Falls back to the host's own strings
	 * so the popup still works even if a taxonomy is somehow missing an entry.
	 */
	function bundleFor(taxonomy) {
		var perTaxonomy = cfg.perTaxonomy || {};
		if (taxonomy && perTaxonomy[taxonomy]) {
			return perTaxonomy[taxonomy];
		}
		return {
			modalTitle: strings.modalTitle,
			editModalTitle: strings.editModalTitle,
			namePlaceholder: strings.namePlaceholder,
			nameFieldLabel: strings.name,
			slugPlaceholder: strings.slugPlaceholder,
			descPlaceholder: strings.descPlaceholder,
			submit: strings.submit,
			confirmDelete: strings.confirmDelete,
			linkedToStops: false
		};
	}

	function applyBundlePlaceholders(modal, bundle) {
		modal.dialog.querySelector('#wbtm-modal-name').setAttribute('placeholder', bundle.namePlaceholder || '');
		modal.dialog.querySelector('#wbtm-modal-slug').setAttribute('placeholder', bundle.slugPlaceholder || '');
		modal.dialog.querySelector('#wbtm-modal-description').setAttribute('placeholder', bundle.descPlaceholder || '');

		var nameLabel = modal.dialog.querySelector('label[for="wbtm-modal-name"]');
		if (nameLabel) {
			nameLabel.textContent = bundle.nameFieldLabel || strings.name || '';
		}

		// Pickup/Drop-Off Point: Slug is auto-generated from the name and never
		// shown, replaced in the form by the "Under Bus Stop" dropdown. Every
		// other taxonomy keeps the Slug field and never shows the dropdown.
		var slugField = modal.dialog.querySelector('.wbtm-slug-field');
		var understopField = modal.dialog.querySelector('.wbtm-understop-field');
		if (slugField) {
			slugField.classList.toggle('wbtm-hidden', !!bundle.linkedToStops);
		}
		if (understopField) {
			understopField.classList.toggle('wbtm-hidden', !bundle.linkedToStops);
		}
	}

	function openAddModal(taxonomy) {
		taxonomy = taxonomy || cfg.taxonomy;
		var modal = buildModal();
		var bundle = bundleFor(taxonomy);
		modalState = { mode: 'add', termId: 0, taxonomy: taxonomy };
		resetModalFields(modal);
		applyBundlePlaceholders(modal, bundle);
		modal.titleEl.textContent = bundle.modalTitle || 'Add New Bus Type';
		modal.submitBtn.disabled = false;
		modal.submitBtn.textContent = bundle.submit || 'Add New Bus Type';
		modal.overlay.classList.remove('wbtm-hidden');
		modal.dialog.querySelector('#wbtm-modal-name').focus();
	}

	function openEditModal(taxonomy, termId) {
		taxonomy = taxonomy || cfg.taxonomy;
		if (!termId) {
			return;
		}
		var modal = buildModal();
		var bundle = bundleFor(taxonomy);
		modalState = { mode: 'edit', termId: termId, taxonomy: taxonomy };
		resetModalFields(modal);
		applyBundlePlaceholders(modal, bundle);
		modal.titleEl.textContent = bundle.editModalTitle || 'Edit Bus Type';
		modal.submitBtn.textContent = strings.saveChanges || 'Save Changes';
		modal.submitBtn.disabled = true;
		modal.dialog.querySelector('#wbtm-modal-name').value = strings.loadingTerm || 'Loading…';
		modal.overlay.classList.remove('wbtm-hidden');

		var body = new URLSearchParams();
		body.set('action', 'wbtm_get_bus_type');
		body.set('nonce', cfg.getNonce || '');
		body.set('taxonomy', taxonomy || '');
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
				if (modalState.mode !== 'edit' || modalState.termId !== termId || modalState.taxonomy !== taxonomy) {
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
				var understopSelect = modal.dialog.querySelector('#wbtm-modal-understop');
				if (understopSelect) {
					understopSelect.value = data.underStop ? String(data.underStop) : '';
				}
				setIconFieldState(modal, data.icon || '');
				modal.submitBtn.disabled = false;
			})
			.catch(function () {
				if (modalState.mode === 'edit' && modalState.termId === termId && modalState.taxonomy === taxonomy) {
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
		var taxonomy = modalState.taxonomy || cfg.taxonomy;
		var bundle = bundleFor(taxonomy);
		clearError();

		if (!name) {
			showError(strings.nameRequired || 'Name is required.');
			return;
		}

		var body = new URLSearchParams();
		body.set('action', isEdit ? 'wbtm_edit_bus_type' : 'wbtm_add_bus_type');
		body.set('nonce', (isEdit ? cfg.editNonce : cfg.nonce) || '');
		body.set('taxonomy', taxonomy || '');
		if (isEdit) {
			body.set('term_id', String(modalState.termId));
		}
		body.set('tag-name', name);
		body.set('slug', dialog.querySelector('#wbtm-modal-slug').value.trim());
		body.set('description', dialog.querySelector('#wbtm-modal-description').value);
		var understopSelect = dialog.querySelector('#wbtm-modal-understop');
		if (understopSelect) {
			body.set('under_bus_stop', understopSelect.value || '0');
		}
		if (cfg.iconFieldHtml && cfg.iconMetaKey) {
			body.set(cfg.iconMetaKey, getIconFieldValue(dialog));
		}

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
				modalEls.submitBtn.textContent = isEdit ? (strings.saveChanges || 'Save Changes') : (bundle.submit || 'Add New Bus Type');
			});
	}

	/* ---------------------------------------------------------------- *
	 *  Delete — confirm, then AJAX. Never navigates: no bare link to
	 *  edit-tags.php?action=delete&... is ever followed.
	 * ---------------------------------------------------------------- */

	function deleteBusType(taxonomy, termId, triggerEl) {
		taxonomy = taxonomy || cfg.taxonomy;
		if (!termId) {
			return;
		}
		if (!window.confirm(bundleFor(taxonomy).confirmDelete || 'Delete this item?')) {
			return;
		}

		var body = new URLSearchParams();
		body.set('action', 'wbtm_delete_bus_type');
		body.set('nonce', cfg.deleteNonce || '');
		body.set('taxonomy', taxonomy || '');
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
	 *  Merged section — a secondary taxonomy's whole management UI (e.g.
	 *  Drop-Off Point), server-rendered by
	 *  WBTM_Taxonomy_Modern::render_merged_taxonomy_section() as an extra
	 *  section on this screen. Its markup is already in the finished
	 *  "modern" shape (icon Actions column, slug badge, no name link), so
	 *  unlike the host table above, nothing here needs transforming —
	 *  only the Add/Edit/Delete controls need wiring to the same popup.
	 * ---------------------------------------------------------------- */

	function wireMergedSections(wrap) {
		wrap.querySelectorAll('.wbtm-merged-section[data-taxonomy]').forEach(function (section) {
			var taxonomy = section.getAttribute('data-taxonomy');

			var addButton = section.querySelector('.wbtm-add-bus-type-btn');
			if (addButton) {
				addButton.addEventListener('click', function () {
					openAddModal(taxonomy);
				});
			}

			section.querySelectorAll('.wbtm-row-action-edit').forEach(function (link) {
				link.addEventListener('click', function (evt) {
					evt.preventDefault();
					var termId = parseInt(link.getAttribute('data-term-id'), 10) || 0;
					openEditModal(taxonomy, termId);
				});
			});

			section.querySelectorAll('.wbtm-row-action-delete').forEach(function (link) {
				link.addEventListener('click', function (evt) {
					evt.preventDefault();
					var termId = parseInt(link.getAttribute('data-term-id'), 10) || 0;
					deleteBusType(taxonomy, termId, link);
				});
			});
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
			removeNameLinks(wrap);
			removeBulkSelection(wrap);
			badgeSlugs(wrap);
			prependFeatureIcons(wrap);
			wireMergedSections(wrap);
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

/**
 * Icon picker popup — live search + "no results" state. Same feature
 * already shipped for the bus-edit "Add Feature" modal (see
 * wbtm-bus-edit-modern.js), ported verbatim so the shared
 * #wbtm_add_icon_popup widget behaves identically wherever it's opened
 * from. Only ever finds anything to attach to on the Bus Features screen
 * (the only one of the 5 taxonomies with an icon field), self-gating via
 * the popup's absence everywhere else. jQuery is safe here — it's already
 * a hard dependency of the picker's own click handlers, which is what
 * renders the popup in the first place.
 */
(function ($) {
	'use strict';
	if (!$) {
		return;
	}

	function initIconPickerSearch() {
		var $popup = $('.wbtm_add_icon_popup');
		if (!$popup.length || $popup.data('wbtm-search-wired')) {
			return;
		}
		$popup.data('wbtm-search-wired', true);

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

		$(document).on('click', '.wbtm_icon_add', resetSearch);

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

		$(document).on('input', '.wbtm_add_icon_popup input[name="mp_select_icon_name"]', function () {
			var q = $(this).val().toLowerCase().trim();
			var $grid = $popup.find('.popup_all_icon');
			var $nr = $popup.find('.wbtm-bme-icon-noresults');

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
					var cls = ($(this).data('icon-class') || '').toLowerCase();
					var ok = name.indexOf(q) >= 0 || cls.indexOf(q) >= 0;
					$(this).toggle(ok);
					if (ok) { matched++; }
				});
				$(this).toggle(matched > 0);
				total += matched;
			});

			$nr.find('.q').text('"' + q + '"');
			$nr.toggle(total === 0);
		});
	}

	// The popup only exists once something calls do_action('wbtm_input_add_icon', ...)
	// on this request — by the time our own script runs (footer-enqueued),
	// it's already in the DOM if it's going to be at all.
	$(function () {
		initIconPickerSearch();
	});
})(window.jQuery);
