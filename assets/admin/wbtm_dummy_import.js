/**
 * WBTM Demo Data Importer (chunked).
 * Drives the demo import one small batch at a time so low-memory hosts
 * (e.g. default 2M/short-timeout shared hosting) never fatal on a single
 * heavy request. Auto-starts on any plugin admin page while an import is
 * pending; each AJAX call inserts taxonomies or a single bus and returns
 * progress until { done: true }.
 */
(function ($) {
	'use strict';

	var config = window.wbtm_dummy_import || {};

	var $toast = null;
	var $text  = null;
	var $fill  = null;

	var MAX_STEPS = 100; // Hard safety ceiling so a bug can never loop forever.
	var steps     = 0;

	$(document).ready(function () {
		$toast = $('#wbtm-dummy-import-toast');
		if (!$toast.length || !config.ajax_url) {
			return;
		}
		$text = $('#wbtm-di-text');
		$fill = $('#wbtm-di-fill');

		$toast.show();
		setProgress(3, config.i18n.importing);
		runBatch();
	});

	function runBatch() {
		if (steps++ > MAX_STEPS) {
			return finish(false);
		}

		$.ajax({
			url:      config.ajax_url,
			type:     'POST',
			dataType: 'json',
			timeout:  120000,
			data: {
				action: 'wbtm_dummy_import_batch',
				nonce:  config.nonce
			},
			success: function (response) {
				if (!response || !response.success || !response.data) {
					return finish(false);
				}
				var data = response.data;
				setProgress(data.percent, data.message || config.i18n.importing);

				if (data.done) {
					return finish(true);
				}
				// Chain the next batch immediately (each is a fresh PHP request).
				runBatch();
			},
			error: function () {
				// Leave the state option intact — it resumes on next page load.
				finish(false);
			}
		});
	}

	function setProgress(percent, text) {
		percent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
		if ($fill) {
			$fill.css('width', percent + '%');
		}
		if ($text && text) {
			$text.text(text);
		}
	}

	function finish(ok) {
		if (ok) {
			setProgress(100, config.i18n.done);
			$toast.addClass('wbtm-di-success');
			// Reload so the freshly imported buses appear in the list.
			setTimeout(function () {
				window.location.reload();
			}, 1200);
		} else {
			setProgress(100, config.i18n.error);
			$toast.addClass('wbtm-di-error');
			setTimeout(function () {
				$toast.fadeOut(400);
			}, 4000);
		}
	}

})(jQuery);
