jQuery(function ($) {
	'use strict';

	var $modal = $('#wbtm-checkout-modal');
	var $pageLoader = $('#wbtm-page-loader');
	var params = typeof wbtm_standalone_checkout_params !== 'undefined' ? wbtm_standalone_checkout_params : null;

	function openCheckoutModal(html) {
		if (!$modal.length) {
			return;
		}
		hideModalLoader();
		var $dialog = $modal.find('.wbtm-checkout-modal__dialog');
		var $body = $modal.find('.wbtm-checkout-modal__body');
		$dialog.removeClass('is-preparing');
		$body.html(html);
		$dialog.toggleClass('is-confirmation', $body.children('.wbtm-confirm-card').length > 0);
		$modal.addClass('is-open').attr('aria-hidden', 'false');
		$('body').addClass('wbtm-checkout-modal-open');
	}

	function closeCheckoutModal() {
		if (!$modal.length) {
			return;
		}
		hideModalLoader();
		$modal.find('.wbtm-checkout-modal__dialog').removeClass('is-preparing is-confirmation');
		$modal.removeClass('is-open').attr('aria-hidden', 'true');
		$('body').removeClass('wbtm-checkout-modal-open');
		$modal.find('.wbtm-checkout-modal__body').empty();
	}

	function showModalLoader(message, isPreparing) {
		if (!$modal.length) {
			return;
		}
		var text = message || (params && params.i18n.processing) || 'Processing…';
		$modal.find('.wbtm-checkout-modal__loader-text').text(text);
		$modal.find('.wbtm-checkout-modal__dialog')
			.addClass('is-loading')
			.toggleClass('is-preparing', !!isPreparing);
		$modal.find('.wbtm-checkout-modal__loader').attr('aria-hidden', 'false');
	}

	function hideModalLoader() {
		if (!$modal.length) {
			return;
		}
		$modal.find('.wbtm-checkout-modal__dialog').removeClass('is-loading is-preparing');
		$modal.find('.wbtm-checkout-modal__loader').attr('aria-hidden', 'true');
	}

	function showCheckoutModalPreloader(message) {
		if (!$modal.length) {
			return;
		}
		$modal.find('.wbtm-checkout-modal__body').empty();
		showModalLoader(message, true);
		$modal.addClass('is-open').attr('aria-hidden', 'false');
		$('body').addClass('wbtm-checkout-modal-open');
	}

	function showPageLoader(message) {
		if (!$pageLoader.length) {
			$pageLoader = $(
				'<div id="wbtm-page-loader" class="wbtm-page-loader is-active" aria-hidden="false" role="status" aria-live="polite">' +
					'<div class="wbtm-page-loader__inner">' +
						'<div class="wbtm-page-loader__spinner" aria-hidden="true"></div>' +
						'<p class="wbtm-page-loader__text"></p>' +
					'</div>' +
				'</div>'
			);
			$('body').append($pageLoader);
		}
		var text = message || (params && params.i18n.loading_confirmation) || 'Loading your confirmation…';
		$pageLoader.find('.wbtm-page-loader__text').text(text);
		$pageLoader.addClass('is-active').attr('aria-hidden', 'false');
		$('body').addClass('wbtm-page-loader-open');
	}

	function navigateAway(url, loaderMessage) {
		showPageLoader(loaderMessage);
		closeCheckoutModal();
		window.location.href = url;
	}

	$(document).on('wbtm_standalone_checkout_loading', function (e, message) {
		var loaderText = message || (params && params.i18n.loading_checkout) || 'Preparing checkout…';
		showCheckoutModalPreloader(loaderText);
	});

	$(document).on('wbtm_standalone_checkout_loading_end', function () {
		if (!$modal.find('.wbtm-checkout-modal__body').children().length) {
			closeCheckoutModal();
			return;
		}
		hideModalLoader();
	});

	$(document).on('wbtm_standalone_checkout_open', function (e, html) {
		openCheckoutModal(html);
	});

	$(document).on('click', '#wbtm-checkout-modal .wbtm-checkout-modal__close, #wbtm-checkout-modal .wbtm-checkout-modal__backdrop', function () {
		if ($modal.find('.wbtm-checkout-modal__dialog').hasClass('is-loading')) {
			return;
		}
		closeCheckoutModal();
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $modal.hasClass('is-open') && !$modal.find('.wbtm-checkout-modal__dialog').hasClass('is-loading')) {
			closeCheckoutModal();
		}
	});

	if (!params) {
		return;
	}

	// Highlight the selected gateway card (visual only, radio itself is still the source of truth).
	$(document).on('change', '.wbtm-standalone-checkout input[name="wbtm_checkout_gateway"]', function () {
		var $option = $(this).closest('.wbtm-standalone-checkout');
		$option.find('.wbtm-gateway-option').removeClass('is-selected');
		$(this).closest('.wbtm-gateway-option').addClass('is-selected');
	});

	// ---- Coupon apply / remove on the checkout card ----
	$(document).on('click', '.wbtm-coupon-apply-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}
		var $container = $btn.closest('.wbtm-standalone-checkout');
		var $box = $btn.closest('.wbtm-coupon-box');
		var $message = $box.find('.wbtm-coupon-message');
		var code = $.trim($box.find('[name="wbtm_coupon_code"]').val() || '');

		var showMessage = function (text, isError) {
			$message
				.text(text)
				.removeClass('is-error is-info')
				.addClass(isError ? 'is-error' : 'is-info')
				.show();
		};

		if (!code) {
			showMessage(params.i18n.enter_coupon, true);
			return;
		}

		$btn.prop('disabled', true).text(params.i18n.applying_coupon);
		$message.hide();

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: {
				action: 'wbtm_standalone_apply_coupon',
				nonce: params.nonce,
				booking_id: $container.data('booking-id'),
				coupon_code: code,
				billing_email: $container.find('[name="wbtm_billing_email"]').val()
			},
			success: function (response) {
				if (response.success && response.data) {
					$container.find('.wbtm-coupon-code').text(response.data.code);
					$container.find('.wbtm-coupon-discount').html('&minus;' + response.data.discount_html);
					$container.find('.wbtm-ticket-coupon').show();
					$container.find('.wbtm-ticket-total__value').html(response.data.payable_html);
					$box.find('.wbtm-coupon-applied__code').text(response.data.code);
					$box.find('.wbtm-coupon-form').hide();
					$box.find('.wbtm-coupon-applied').show();
					showMessage(response.data.message || '', false);
					return;
				}
				showMessage((response && response.data) || params.i18n.error_generic, true);
			},
			error: function (jqXHR) {
				showMessage((jqXHR.responseJSON && jqXHR.responseJSON.data) || params.i18n.error_generic, true);
			},
			complete: function () {
				$btn.prop('disabled', false).text(params.i18n.apply_coupon);
			}
		});
	});

	$(document).on('keypress', '.wbtm-coupon-form [name="wbtm_coupon_code"]', function (e) {
		if (e.which === 13) {
			e.preventDefault();
			$(this).closest('.wbtm-coupon-form').find('.wbtm-coupon-apply-btn').trigger('click');
		}
	});

	$(document).on('click', '.wbtm-coupon-remove-btn', function (e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}
		var $container = $btn.closest('.wbtm-standalone-checkout');
		var $box = $btn.closest('.wbtm-coupon-box');
		var $message = $box.find('.wbtm-coupon-message');

		var showMessage = function (text, isError) {
			$message
				.text(text)
				.removeClass('is-error is-info')
				.addClass(isError ? 'is-error' : 'is-info')
				.show();
		};

		$btn.prop('disabled', true);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: {
				action: 'wbtm_standalone_remove_coupon',
				nonce: params.nonce,
				booking_id: $container.data('booking-id')
			},
			success: function (response) {
				if (response.success && response.data) {
					$container.find('.wbtm-ticket-coupon').hide();
					$container.find('.wbtm-coupon-code').text('');
					$container.find('.wbtm-coupon-discount').empty();
					$container.find('.wbtm-ticket-total__value').html(response.data.total_html);
					$box.find('[name="wbtm_coupon_code"]').val('');
					$box.find('.wbtm-coupon-applied').hide();
					$box.find('.wbtm-coupon-form').show();
					showMessage(response.data.message || '', false);
					return;
				}
				showMessage((response && response.data) || params.i18n.error_generic, true);
			},
			error: function (jqXHR) {
				showMessage((jqXHR.responseJSON && jqXHR.responseJSON.data) || params.i18n.error_generic, true);
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	$(document).on('click', '.wbtm-checkout-pay-btn', function (e) {
		e.preventDefault();

		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}

		var $label = $btn.find('.wbtm-pay-cta-label');
		var $container = $btn.closest('.wbtm-standalone-checkout');
		var $message = $container.find('.wbtm-pay-message');
		var gateway = $container.find('input[name="wbtm_checkout_gateway"]:checked').val();
		var inModal = $container.closest('#wbtm-checkout-modal').length > 0;

		var showMessage = function (text, isError) {
			$message
				.text(text)
				.removeClass('is-error is-info')
				.addClass(isError ? 'is-error' : 'is-info')
				.show();
		};

		if (!gateway) {
			showMessage(params.i18n.select_gateway, true);
			return;
		}

		var data = {
			action: 'wbtm_standalone_checkout_pay',
			nonce: params.nonce,
			booking_id: $container.data('booking-id'),
			gateway: gateway,
			billing_name: $container.find('[name="wbtm_billing_name"]').val(),
			billing_email: $container.find('[name="wbtm_billing_email"]').val(),
			billing_phone: $container.find('[name="wbtm_billing_phone"]').val()
		};

		var extractMessage = function (payload) {
			if (!payload || typeof payload.data === 'undefined') {
				return '';
			}
			return typeof payload.data === 'string' ? payload.data : (payload.data.message || '');
		};

		var handleFailure = function (response) {
			if (inModal) {
				hideModalLoader();
			}
			showMessage(extractMessage(response) || params.i18n.error_generic, true);
			$btn.prop('disabled', false);
			$label.text(params.i18n.pay_now);
		};

		$btn.prop('disabled', true);
		$label.text(params.i18n.processing);
		$message.hide();

		if (inModal) {
			showModalLoader(params.i18n.processing);
		}

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: data,
			success: function (response) {
				if (response.success && response.data && response.data.confirmation_html) {
					$(document).trigger('wbtm_standalone_checkout_open', [response.data.confirmation_html]);
					return;
				}
				if (response.success && response.data && response.data.confirmation_url) {
					navigateAway(response.data.confirmation_url, params.i18n.loading_confirmation);
					return;
				}
				if (response.success && response.data && response.data.redirect_url) {
					navigateAway(response.data.redirect_url, params.i18n.redirecting_payment);
					return;
				}
				handleFailure(response);
			},
			error: function (jqXHR) {
				handleFailure(jqXHR.responseJSON);
			}
		});
	});
});
