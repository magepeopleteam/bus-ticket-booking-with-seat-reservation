/**
 * WBTM global admin toast notifications — window.wbtmToast.{success,error,warning,info,show}.
 * Self-contained (jQuery only), loaded once on every plugin admin screen so any
 * script in this plugin (or the Pro add-on) can call it without its own copy.
 */
(function ($, window) {
	'use strict';

	if (window.wbtmToast) {
		return; // Already initialized (e.g. a screen loaded this file twice).
	}

	var ICONS = {
		success: 'dashicons-yes-alt',
		error: 'dashicons-warning',
		warning: 'dashicons-flag',
		info: 'dashicons-info-outline'
	};

	function getContainer() {
		var $container = $('#wbtm-toast-container');
		if (!$container.length) {
			$container = $('<div id="wbtm-toast-container"></div>').appendTo('body');
		}
		return $container;
	}

	/**
	 * @param {string} message
	 * @param {string} [type] success|error|warning|info (default: info)
	 * @param {number} [duration] ms before auto-dismiss, 0 disables auto-dismiss
	 */
	function show(message, type, duration) {
		if (!message) {
			return;
		}
		type = ICONS[type] ? type : 'info';
		duration = typeof duration === 'number' ? duration : 3200;

		var $toast = $(
			'<div class="wbtm-toast wbtm-toast-' + type + '" role="status">' +
				'<span class="wbtm-toast-icon"><span class="dashicons ' + ICONS[type] + '"></span></span>' +
				'<span class="wbtm-toast-body"></span>' +
				'<span class="wbtm-toast-close dashicons dashicons-no-alt" aria-label="Dismiss"></span>' +
			'</div>'
		);
		$toast.find('.wbtm-toast-body').text(message);
		getContainer().append($toast);

		function dismiss() {
			if ($toast.hasClass('is-leaving')) {
				return;
			}
			$toast.addClass('is-leaving').removeClass('is-visible');
			setTimeout(function () {
				$toast.remove();
			}, 250);
		}

		$toast.on('click', '.wbtm-toast-close', dismiss);

		requestAnimationFrame(function () {
			$toast.addClass('is-visible');
		});

		if (duration > 0) {
			setTimeout(dismiss, duration);
		}

		return { dismiss: dismiss };
	}

	window.wbtmToast = {
		show: show,
		success: function (message, duration) { return show(message, 'success', duration); },
		error: function (message, duration) { return show(message, 'error', duration); },
		warning: function (message, duration) { return show(message, 'warning', duration); },
		info: function (message, duration) { return show(message, 'info', duration); }
	};
})(jQuery, window);
