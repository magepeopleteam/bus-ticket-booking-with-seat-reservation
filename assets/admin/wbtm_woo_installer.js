/**
 * WBTM WooCommerce Installer
 * Handles AJAX installation & activation of WooCommerce
 * with smooth progress animations.
 * Popup shows on every admin page when WooCommerce is not active.
 */
(function ($) {
	'use strict';

	var config    = window.wbtm_woo_installer || {};
	var $overlay  = null;
	var $popup    = null;
	var $btn      = null;
	var $progress = null;
	var $fill     = null;
	var $status   = null;
	var $actions  = null;
	var isWorking = false;

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
	 * Start the install → activate → redirect process.
	 */
	function startProcess() {
		isWorking = true;
		$btn.prop('disabled', true);

		// Show progress, hide actions
		$actions.slideUp(250);
		$progress.slideDown(300);

		if (config.woo_installed === 'yes') {
			// Already installed, just activate
			setProgress(30, config.i18n.activating);
			activateWooCommerce();
		} else {
			// Install first, then activate
			setProgress(10, config.i18n.installing);
			installWooCommerce();
		}
	}

	/**
	 * AJAX: Install WooCommerce.
	 */
	function installWooCommerce() {
		$.ajax({
			url:      config.ajax_url,
			type:     'POST',
			dataType: 'json',
			data: {
				action: 'wbtm_install_woocommerce',
				nonce:  config.install_nonce
			},
			success: function (response) {
				if (response.success) {
					setProgress(60, config.i18n.activating);
					activateWooCommerce();
				} else {
					showError(response.data && response.data.message
						? response.data.message
						: config.i18n.install_error);
				}
			},
			error: function () {
				showError(config.i18n.install_error);
			}
		});
	}

	/**
	 * AJAX: Activate WooCommerce.
	 */
	function activateWooCommerce() {
		$.ajax({
			url:      config.ajax_url,
			type:     'POST',
			dataType: 'json',
			data: {
				action: 'wbtm_activate_woocommerce',
				nonce:  config.activate_nonce
			},
			success: function (response) {
				if (response.success) {
					showSuccess();
				} else {
					showError(response.data && response.data.message
						? response.data.message
						: config.i18n.activate_error);
				}
			},
			error: function () {
				showError(config.i18n.activate_error);
			}
		});
	}

	/**
	 * Update progress bar width and status text.
	 */
	function setProgress(percent, text) {
		$fill.css('width', percent + '%');
		$status.text(text).removeClass('wbtm-success wbtm-error');
	}

	/**
	 * Show success state and redirect.
	 */
	function showSuccess() {
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
		isWorking = false;
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
