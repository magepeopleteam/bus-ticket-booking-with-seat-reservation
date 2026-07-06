/**
 * WBTM WooCommerce Installer
 * Handles AJAX installation & activation of WooCommerce
 * with smooth progress animations.
 * Popup shows on every admin page when WooCommerce is not active.
 */
(function ($) {
	'use strict';

	var config        = window.wbtm_woo_installer || {};
	// A single AJAX call can legitimately run long (large installs, slow
	// disks/AV scanning). Rather than treat a client-side timeout as a hard
	// failure, we let the request abort and keep polling progress instead —
	// the server keeps working in the background regardless.
	var AJAX_TIMEOUT            = 300000;       // 5 minutes per request
	var PROGRESS_POLL_INTERVAL  = 800;          // ms
	var MAX_TOTAL_WAIT          = 20 * 60000;   // 20 minutes absolute ceiling before giving up

	var $overlay  = null;
	var $popup    = null;
	var $btn      = null;
	var $progress = null;
	var $fill     = null;
	var $status   = null;
	var $actions  = null;
	var isWorking = false;

	// Per-attempt token so the progress poll never shows a stale percentage
	// left over from a previous (failed) attempt.
	var progressToken    = '';
	var progressTimer    = null;
	var lastPercent      = 0;
	// Chunked installer: the flow is a chain of discrete server steps
	// (download → install → activate), each its own HTTP request.
	var launchedSteps    = {};    // guards against launching the same step twice
	var finished         = false; // true once a terminal outcome (success/error) has fired
	var processStartTime  = 0;

	/**
	 * Initialize when DOM is ready.
	 */
	$(document).ready(function () {
		$overlay  = $('#wbtm-woo-overlay');
		$popup    = $overlay.find('.wbtm-woo-popup');
		$btn      = $('#wbtm-woo-install-btn');
		$progress = $('#wbtm-woo-progress');
		$fill     = $('#wbtm-woo-progress-fill');
		$status   = $('#wbtm-woo-status-text');
		$actions  = $overlay.find('.wbtm-woo-actions');

		if (!$overlay.length) {
			return;
		}

		// Install / Activate button click
		$btn.on('click', function (e) {
			e.preventDefault();
			if (isWorking) {
				return;
			}
			startProcess();
		});
	});

	/**
	 * Start the chunked download → install → activate → redirect process.
	 */
	function startProcess() {
		isWorking        = true;
		progressToken    = 'wbtm' + Date.now().toString(36) + Math.random().toString(36).slice(2);
		lastPercent      = 0;
		launchedSteps    = {};
		finished         = false;
		processStartTime = Date.now();
		$btn.prop('disabled', true);

		// Show progress, hide actions
		$actions.slideUp(250);
		$progress.slideDown(300);

		startProgressPolling();

		// If WooCommerce files already exist, skip straight to activation;
		// otherwise begin with the download step.
		if (config.woo_installed === 'yes') {
			runStep('activate');
		} else {
			runStep('download');
		}
	}

	/**
	 * Human-readable starting text + baseline percent for each step, shown the
	 * instant a step's request is fired so the UI never looks stalled.
	 */
	function stepIntro(step) {
		switch (step) {
			case 'download': return { percent: 5,  text: config.i18n.installing };
			case 'install':  return { percent: 60, text: config.i18n.installing };
			case 'activate': return { percent: 92, text: config.i18n.activating };
			default:         return { percent: lastPercent, text: '' };
		}
	}

	/**
	 * Run a single server step. Idempotent per step: launchedSteps guards
	 * against the same step being started twice (e.g. if both the AJAX response
	 * and a progress-poll recovery try to advance to it).
	 */
	function runStep(step) {
		if (finished || launchedSteps[step]) {
			return;
		}
		launchedSteps[step] = true;

		var intro = stepIntro(step);
		if (intro.percent >= lastPercent) {
			lastPercent = intro.percent;
		}
		setProgress(lastPercent, intro.text);

		$.ajax({
			url:      config.ajax_url,
			type:     'POST',
			dataType: 'json',
			timeout:  AJAX_TIMEOUT,
			data: {
				action:         'wbtm_woo_install_step',
				nonce:          config.install_nonce,
				progress_token: progressToken,
				step:           step
			},
			success: function (response) {
				if (finished) {
					return;
				}
				if (response && response.success) {
					advance(response.data && response.data.next ? response.data.next : '');
				} else {
					showError(response && response.data && response.data.message
						? response.data.message
						: stepErrorText(step));
				}
			},
			error: function (jqXHR, textStatus) {
				if (textStatus === 'timeout') {
					// The server keeps running past a dropped connection — keep
					// polling; the poller recovers the outcome for this step.
					setProgress(lastPercent, config.i18n.timeout_wait);
					return;
				}
				if (finished) {
					return;
				}
				showError(stepErrorText(step));
			}
		});
	}

	/**
	 * Advance the chain to the next step, or finish when there is none.
	 */
	function advance(next) {
		if (!next) {
			if (!finished) {
				finished = true;
				showSuccess();
			}
			return;
		}
		runStep(next);
	}

	function stepErrorText(step) {
		return (step === 'activate') ? config.i18n.activate_error : config.i18n.install_error;
	}

	/**
	 * Poll the server every PROGRESS_POLL_INTERVAL ms for the real
	 * download/install percentage so the user always knows how far along
	 * (and roughly how much longer) the process is. This also lets us
	 * recover the final outcome even if the original AJAX call timed out
	 * client-side while the server kept working.
	 */
	function startProgressPolling() {
		stopProgressPolling();
		progressTimer = setInterval(fetchProgress, PROGRESS_POLL_INTERVAL);
	}

	function stopProgressPolling() {
		if (progressTimer) {
			clearInterval(progressTimer);
			progressTimer = null;
		}
	}

	function fetchProgress() {
		$.post(config.ajax_url, {
			action:         'wbtm_install_progress',
			nonce:          config.install_nonce,
			progress_token: progressToken
		}, function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}

			var data    = response.data;
			var percent = parseInt(data.percent, 10);

			if (!isNaN(percent) && percent >= lastPercent && (percent > 0 || data.text)) {
				lastPercent = percent;
				setProgress(percent, data.text);
			}

			if (finished) {
				return;
			}

			if (data.status === 'error') {
				showError(data.text || config.i18n.install_error);
				return;
			}

			// A step finished server-side. Recover the chain even if that step's
			// own AJAX response was lost to a client-side timeout: advance to the
			// next step, or finish when there is none. advance()/runStep() are
			// idempotent, so this can't double-run a step already in flight.
			if (data.status === 'success') {
				advance(data.next ? data.next : '');
				return;
			}

			// Still genuinely in progress. Only give up after a generous
			// absolute ceiling, in case the server process died without
			// ever recording a final status.
			if (Date.now() - processStartTime > MAX_TOTAL_WAIT) {
				showError(config.i18n.timeout_error);
			}
		}, 'json');
	}

	/**
	 * Update progress bar width and status text. Always shows the
	 * percentage so the user has a concrete sense of remaining time.
	 */
	function setProgress(percent, text) {
		percent = Math.max(0, Math.min(100, percent));
		$fill.css('width', percent + '%');
		$status.text(text ? (percent + '% — ' + text) : (percent + '%'))
			.removeClass('wbtm-success wbtm-error');
	}

	/**
	 * Show success state and redirect.
	 */
	function showSuccess() {
		stopProgressPolling();
		setProgress(100, config.i18n.success);
		$popup.addClass('wbtm-state-success');
		$status.addClass('wbtm-success');

		// Change icon to checkmark
		$popup.find('.wbtm-woo-icon').html(
			'<svg width="40" height="40" viewBox="0 0 24 24" fill="none">' +
			'<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>' +
			'<path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
			'</svg>'
		);

		// Update title
		$popup.find('.wbtm-woo-title').text(config.i18n.success);
		$popup.find('.wbtm-woo-desc').text(config.i18n.redirecting);

		// Redirect after short delay
		setTimeout(function () {
			window.location.href = config.redirect_url;
		}, 1500);
	}

	/**
	 * Show error state with retry option.
	 */
	function showError(message) {
		finished  = true;
		isWorking = false;
		stopProgressPolling();
		$popup.addClass('wbtm-state-error');
		$status.text(message).addClass('wbtm-error');
		$fill.css('width', '100%');

		// Show actions again with retry
		$btn.prop('disabled', false);
		$actions.slideDown(250);

		// Reset state for retry after a moment
		setTimeout(function () {
			$popup.removeClass('wbtm-state-error');
			$progress.slideUp(250);
			$fill.css('width', '0%');
		}, 3000);
	}

})(jQuery);
