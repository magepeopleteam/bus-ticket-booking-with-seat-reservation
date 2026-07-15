(function ($) {
	'use strict';

	/* Delegates to the plugin's shared global toast (assets/admin/js/wbtm-toast.js,
	   loaded on every plugin admin screen including this one) so this page doesn't
	   carry its own duplicate toast implementation/CSS. */
	function toast(message, type) {
		if (window.wbtmToast) {
			window.wbtmToast.show(message, type);
		}
	}

	function closeAllDropdowns() {
		$('.wbtm-bkl-dropdown.is-open').each(function () {
			$(this).removeClass('is-open drop-up');
			$(this).find('.wbtm-bkl-dropdown-toggle').attr('aria-expanded', 'false');
		});
	}

	/* Patches every status pill/badge on the page referencing this booking id —
	   the row badge in the list and the two pills on the detail page (H1 + card)
	   all need to stay in sync after a status change. */
	function paintStatus(id, badgeHtml) {
		$('tr[data-row-id="' + id + '"]').find('td').eq(6).html(badgeHtml);
		$('.wbtm-bkl-current-status[data-booking-id="' + id + '"]').html(badgeHtml);
	}

	$(function () {
		var vars = window.wbtmBookingList || {};
		var i18n = vars.i18n || {};
		var $deleteModal = $('#wbtm-bkl-delete-modal');
		var $statusModal = $('#wbtm-bkl-status-modal');

		/* Collapsible Filters panel — collapsed by default, click the header
		   (arrow or bar) to toggle. Pure CSS class flip, no AJAX involved. */
		$(document).on('click', '.wbtm-bkl-filters-toggle', function () {
			var $toggle = $(this);
			var $body = $toggle.closest('.wbtm-bkl-filters-panel').find('.wbtm-bkl-filters-body');
			var open = $body.toggleClass('is-open').hasClass('is-open');
			$toggle.attr('aria-expanded', open ? 'true' : 'false');
		});

		/* Row actions dropdown */
		$(document).on('click', '.wbtm-bkl-dropdown-toggle', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $dropdown = $(this).closest('.wbtm-bkl-dropdown');
			var willOpen = !$dropdown.hasClass('is-open');
			closeAllDropdowns();
			if (willOpen) {
				var spaceBelow = window.innerHeight - this.getBoundingClientRect().bottom;
				$dropdown.toggleClass('drop-up', spaceBelow < 220);
				$dropdown.addClass('is-open');
				$(this).attr('aria-expanded', 'true');
			}
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('.wbtm-bkl-dropdown').length) {
				closeAllDropdowns();
			}
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				closeAllDropdowns();
				$deleteModal.hide();
				$statusModal.hide();
			}
		});

		/* Locked (PRO-only) triggers */
		$(document).on('click', '.wbtm-bkl-locked-trigger', function (e) {
			e.preventDefault();
			e.stopPropagation();
			toast(i18n.proOnly || 'This is a PRO feature.');
			closeAllDropdowns();
		});

		/* Change Status: shared modal, opened from the row dropdown or the
		   detail-page header button — same trigger class either place. */
		$(document).on('click', '.wbtm-bkl-change-status-btn', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			$statusModal.find('#wbtm-bkl-status-modal-id').val($btn.data('id'));
			$statusModal.find('#wbtm-bkl-status-modal-ref').text($btn.data('ref'));
			$statusModal.find('input[name="wbtm_bkl_status_modal_option"]').prop('checked', false);
			$statusModal.find('input[value="' + $btn.data('status') + '"]').prop('checked', true);
			$statusModal.show();
			closeAllDropdowns();
		});

		$statusModal.on('click', '.wbtm-bkl-modal-close', function () {
			$statusModal.hide();
		});
		$statusModal.on('click', function (e) {
			if (e.target === this) {
				$statusModal.hide();
			}
		});

		$('#wbtm-bkl-status-modal-save').on('click', function () {
			var $btn = $(this);
			var id = $statusModal.find('#wbtm-bkl-status-modal-id').val();
			var status = $statusModal.find('input[name="wbtm_bkl_status_modal_option"]:checked').val();
			if (!id || !status) {
				return;
			}
			$btn.prop('disabled', true);
			$.post(vars.ajaxUrl, {
				action: 'wbtm_bkl_change_status',
				nonce: vars.nonce,
				booking_id: id,
				status: status
			}).done(function (response) {
				if (response && response.success) {
					paintStatus(id, response.data.badge);
					if (response.data.log_entry) {
						$('#wbtm-bkl-activity-log').prepend(response.data.log_entry);
					}
					toast((response.data && response.data.message) || i18n.statusUpdated || 'Status updated.', 'success');
					$statusModal.hide();
				} else {
					toast((response && response.data && response.data.message) || i18n.statusError || 'Could not update the status.', 'error');
				}
			}).fail(function () {
				toast(i18n.statusError || 'Could not update the status.', 'error');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		/* Notes (detail page only) */
		$(document).on('click', '#wbtm-bkl-note-add', function () {
			var $btn = $(this);
			var id = $btn.data('id');
			var note = $.trim($('#wbtm-bkl-note-input').val());
			if (!note) {
				return;
			}
			$btn.prop('disabled', true);
			$.post(vars.ajaxUrl, {
				action: 'wbtm_bkl_add_note',
				nonce: vars.nonce,
				booking_id: id,
				note: note
			}).done(function (response) {
				if (response && response.success) {
					$('#wbtm-bkl-notes-list .wbtm-bkl-log-empty').remove();
					$('#wbtm-bkl-notes-list').prepend(response.data.log_entry);
					$('#wbtm-bkl-note-input').val('');
				} else {
					toast((response && response.data && response.data.message) || i18n.statusError || 'Could not add the note.', 'error');
				}
			}).fail(function () {
				toast(i18n.statusError || 'Could not add the note.', 'error');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		/* Delete flow: dropdown item (single) or bulk bar (many) -> confirm modal
		   -> AJAX. The modal's hidden field holds a comma-separated id list so
		   both call sites share one confirm/AJAX/removal path. */
		$(document).on('click', '.wbtm-bkl-del-btn', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			openDeleteModal([String($btn.data('id'))], String($btn.data('ref')));
			closeAllDropdowns();
		});

		function openDeleteModal(ids, refText) {
			$deleteModal.find('#wbtm-bkl-delete-id').val(ids.join(','));
			$deleteModal.find('#wbtm-bkl-delete-ref').text(refText);
			$deleteModal.show();
		}

		$deleteModal.on('click', '.wbtm-bkl-modal-close', function () {
			$deleteModal.hide();
		});
		$deleteModal.on('click', function (e) {
			if (e.target === this) {
				$deleteModal.hide();
			}
		});

		$('#wbtm-bkl-delete-confirm').on('click', function () {
			var $btn = $(this);
			var raw = $deleteModal.find('#wbtm-bkl-delete-id').val();
			var ids = raw ? raw.split(',').filter(Boolean) : [];
			if (!ids.length) {
				return;
			}
			$btn.prop('disabled', true);
			$.post(vars.ajaxUrl, {
				action: 'wbtm_bkl_delete',
				nonce: vars.nonce,
				booking_id: ids
			}).done(function (response) {
				if (response && response.success) {
					(response.data.ids || ids).forEach(function (id) {
						$('tr[data-row-id="' + id + '"]').fadeOut(200, function () {
							$(this).remove();
						});
					});
					toast((response.data && response.data.message) || i18n.deleted || 'Booking deleted.', 'success');
					resetBulkBar();
				} else {
					toast((response && response.data && response.data.message) || i18n.deleteError || 'Could not delete the booking.', 'error');
				}
			}).fail(function () {
				toast(i18n.deleteError || 'Could not delete the booking.', 'error');
			}).always(function () {
				$btn.prop('disabled', false);
				$deleteModal.hide();
			});
		});

		/* Bulk actions bar */
		function getCheckedIds() {
			return $('.wbtm-bkl-row-check:checked').map(function () {
				return $(this).val();
			}).get();
		}

		function resetBulkBar() {
			$('#wbtm-bkl-select-all').prop('checked', false);
			$('.wbtm-bkl-row-check').prop('checked', false);
			$('#wbtm-bkl-bulk-action').val('-1');
		}

		$(document).on('change', '#wbtm-bkl-select-all', function () {
			$('.wbtm-bkl-row-check').prop('checked', $(this).is(':checked'));
		});

		$(document).on('change', '.wbtm-bkl-row-check', function () {
			var total = $('.wbtm-bkl-row-check').length;
			var checked = $('.wbtm-bkl-row-check:checked').length;
			$('#wbtm-bkl-select-all').prop('checked', total > 0 && total === checked);
		});

		$(document).on('click', '#wbtm-bkl-bulk-apply', function () {
			var $select = $('#wbtm-bkl-bulk-action');
			var action = $select.val();
			var ids = getCheckedIds();

			if (action === '-1') {
				toast(i18n.chooseBulkAction || 'Please choose a bulk action.');
				return;
			}
			if (!ids.length) {
				toast(i18n.selectAtLeastOne || 'Please select at least one booking.');
				return;
			}

			if (action === 'delete') {
				openDeleteModal(ids, ids.length + ' ' + (i18n.selectedBookings || 'selected booking(s)'));
				return;
			}

			if (action.indexOf('status:') === 0) {
				var $option = $select.find('option:selected');
				if ($option.data('pro')) {
					toast(i18n.proOnly || 'This is a PRO feature.');
					return;
				}
				var status = action.slice('status:'.length);
				if (!window.confirm((i18n.confirmBulkStatus || 'Change status of %d booking(s)?').replace('%d', ids.length))) {
					return;
				}
				$.post(vars.ajaxUrl, {
					action: 'wbtm_bkl_bulk_change_status',
					nonce: vars.nonce,
					booking_id: ids,
					status: status
				}).done(function (response) {
					if (response && response.success) {
						Object.keys(response.data.updated).forEach(function (id) {
							paintStatus(id, response.data.updated[id]);
						});
						toast(response.data.message || i18n.statusUpdated || 'Status updated.', 'success');
						resetBulkBar();
					} else {
						toast((response && response.data && response.data.message) || i18n.statusError || 'Could not update the status.', 'error');
					}
				}).fail(function () {
					toast(i18n.statusError || 'Could not update the status.', 'error');
				});
			}
		});
	});
})(jQuery);
