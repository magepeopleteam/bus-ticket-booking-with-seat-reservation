jQuery(function ($) {
	'use strict';

	if (typeof wbtm_standalone_auth_params === 'undefined') {
		return;
	}

	var params = wbtm_standalone_auth_params;
	var $modal = $('#wbtm-auth-modal');
	if (!$modal.length) {
		return;
	}

	var pendingRequestData = null;
	var pendingRetry = null;

	function showError(message) {
		$modal.find('.wbtm-auth-error').text(message || params.i18n.error_generic).show();
	}

	// wp_send_json_error() responds with a real HTTP error status, so jQuery
	// routes it to error: rather than success: — but it still parses the JSON
	// body into jqXHR.responseJSON, which is where the actual server message
	// (wrong password, duplicate email, etc.) lives instead of a generic one.
	function messageFromResponse(payload) {
		return (payload && payload.data && payload.data.message) || '';
	}

	function clearError() {
		$modal.find('.wbtm-auth-error').hide().text('');
	}

	function switchTab(mode) {
		clearError();
		$modal.find('.wbtm-auth-tab').removeClass('is-active').attr('aria-selected', 'false');
		$modal.find('.wbtm-auth-tab[data-tab="' + mode + '"]').addClass('is-active').attr('aria-selected', 'true');
		$modal.find('.wbtm-auth-panel').removeClass('is-active');
		$modal.find('.wbtm-auth-panel-' + mode).addClass('is-active');
	}

	function openModal() {
		switchTab('login');
		$modal.addClass('is-open');
		$('body').addClass('wbtm-auth-modal-open');
	}

	function closeModal() {
		$modal.removeClass('is-open');
		$('body').removeClass('wbtm-auth-modal-open');
	}

	$(document).on('wbtm_require_login', function (e, requestData, retryFn) {
		pendingRequestData = requestData;
		pendingRetry = retryFn;
		openModal();
	});

	$modal.on('click', '.wbtm-auth-tab', function () {
		switchTab($(this).data('tab'));
	});

	$modal.on('click', '.wbtm-auth-modal-close', function () {
		closeModal();
	});

	$modal.on('click', function (e) {
		if (e.target === this) {
			closeModal();
		}
	});

	$modal.on('click', '.wbtm-auth-password-toggle', function () {
		var $input = $(this).siblings('input');
		var isPassword = $input.attr('type') === 'password';
		$input.attr('type', isPassword ? 'text' : 'password');
		$(this).text(isPassword ? '🙈' : '👁');
	});

	function handleAuthSuccess() {
		// The login/register response itself can't carry a working wbtm_form_nonce —
		// the browser only attaches the real logged_in cookie on the NEXT request, so
		// a nonce minted inline there would be hashed against the wrong session token
		// and fail verification on the retry. Fetch it here instead, in a genuinely
		// separate request, now that the cookie is actually in play.
		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: {
				action: 'wbtm_standalone_refresh_nonce'
			},
			success: function (nonceResponse) {
				var retry = pendingRetry;
				var requestData = pendingRequestData;
				pendingRequestData = null;
				pendingRetry = null;

				if (nonceResponse.success && requestData) {
					requestData.wbtm_form_nonce = nonceResponse.data.form_nonce;
					$('input[name="wbtm_form_nonce"]').val(nonceResponse.data.form_nonce);
				}

				closeModal();

				if (typeof retry === 'function') {
					retry();
				}
			},
			error: function () {
				// Keep the modal open so the message is actually visible — the user is
				// logged in at this point, just couldn't fetch the retry nonce yet.
				showError(params.i18n.error_generic);
			}
		});
	}

	$modal.on('submit', '.wbtm-auth-panel-login', function (e) {
		e.preventDefault();
		clearError();
		var $form = $(this);
		var $btn = $form.find('.wbtm-auth-submit');
		var originalText = $btn.text();
		$btn.prop('disabled', true).text(params.i18n.processing);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: {
				action: 'wbtm_standalone_login',
				nonce: params.nonce,
				login: $form.find('[name="login"]').val(),
				password: $form.find('[name="password"]').val()
			},
			success: function (response) {
				if (response.success) {
					handleAuthSuccess(response);
				} else {
					showError(messageFromResponse(response));
				}
			},
			error: function (jqXHR) {
				showError(messageFromResponse(jqXHR.responseJSON));
			},
			complete: function () {
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});

	$modal.on('submit', '.wbtm-auth-panel-register', function (e) {
		e.preventDefault();
		clearError();
		var $form = $(this);
		var $btn = $form.find('.wbtm-auth-submit');
		var originalText = $btn.text();
		var password = $form.find('[name="password"]').val();

		if (password.length < params.min_password_length) {
			showError(params.i18n.password_too_short);
			return;
		}

		$btn.prop('disabled', true).text(params.i18n.processing);

		$.ajax({
			url: params.ajax_url,
			type: 'POST',
			data: {
				action: 'wbtm_standalone_register',
				nonce: params.nonce,
				name: $form.find('[name="name"]').val(),
				email: $form.find('[name="email"]').val(),
				password: password
			},
			success: function (response) {
				if (response.success) {
					handleAuthSuccess(response);
				} else {
					showError(messageFromResponse(response));
				}
			},
			error: function (jqXHR) {
				showError(messageFromResponse(jqXHR.responseJSON));
			},
			complete: function () {
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});
});
