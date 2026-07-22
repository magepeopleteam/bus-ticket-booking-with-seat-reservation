/* global wbtmWcPaymentManager, jQuery */
( function ( $ ) {
	'use strict';

	var cfg = window.wbtmWcPaymentManager || {};
	var i18n = cfg.i18n || {};

	function ajax( data ) {
		return $.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			data: $.extend( { nonce: cfg.nonce }, data ),
		} );
	}

	$( function () {
		var $manager = $( '.wbtm-wc-payment-manager' ).first();
		if ( ! $manager.length ) {
			return;
		}

		// Tag the manager's settings-table row with `woocommerce-field` so the
		// existing payment-tab show/hide + accordion logic manages its visibility.
		if ( ! $manager.data( 'wbtm-relocated' ) ) {
			$manager.closest( 'tr' ).addClass( 'woocommerce-field' );
			$manager.data( 'wbtm-relocated', true );
		}

		// -----------------------------------------------------------
		// Expand / collapse a gateway's native settings form
		// -----------------------------------------------------------
		$manager.on( 'click', '.wbtm-gw-configure-btn', function () {
			var $card = $( this ).closest( '.wbtm-gw-card' );
			var $body = $card.find( '.wbtm-gw-body' );
			var open = $body.is( ':visible' );

			$manager.find( '.wbtm-gw-body' ).slideUp( 150 );
			$manager.find( '.wbtm-gw-configure-btn' ).text( i18n.configure || 'Configure' );

			if ( ! open ) {
				$body.slideDown( 150 );
				$( this ).text( i18n.close || 'Close' );
			}
		} );

		// -----------------------------------------------------------
		// Quick enable/disable toggle in the card header
		// -----------------------------------------------------------
		$manager.on( 'change', '.wbtm-gw-toggle-input', function () {
			var $input = $( this );
			var $card = $input.closest( '.wbtm-gw-card' );
			var gatewayId = $input.data( 'gateway-id' );
			var enabled = $input.is( ':checked' ) ? 'yes' : 'no';

			$input.prop( 'disabled', true );

			ajax( {
				action: 'wbtm_wc_toggle_gateway',
				gateway_id: gatewayId,
				enabled: enabled,
			} )
				.done( function ( res ) {
					if ( res && res.success ) {
						applyEnabledState( $card, res.data.enabled === 'yes' );
					} else {
						$input.prop( 'checked', ! $input.is( ':checked' ) );
						window.alert( ( res && res.data ) || i18n.error );
					}
				} )
				.fail( function () {
					$input.prop( 'checked', ! $input.is( ':checked' ) );
					window.alert( i18n.error );
				} )
				.always( function () {
					$input.prop( 'disabled', false );
				} );
		} );

		// -----------------------------------------------------------
		// Save a gateway's native settings (process_admin_options)
		// Bound to the button click — the container is a <div>, not a <form>
		// (it lives inside the Settings API <form>; nested forms are invalid).
		// -----------------------------------------------------------
		$manager.on( 'click', '.wbtm-gw-save-btn', function ( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $form = $btn.closest( '.wbtm-gw-form' );
			var $card = $btn.closest( '.wbtm-gw-card' );
			var gatewayId = $form.data( 'gateway-id' );
			var $status = $form.find( '.wbtm-gw-status' );

			// Native WC field names are woocommerce_{id}_{field}; submit as-is.
			var payload = { action: 'wbtm_wc_save_gateway', gateway_id: gatewayId };
			$.each( $form.find( ':input' ).serializeArray(), function ( i, f ) {
				payload[ f.name ] = f.value;
			} );

			$btn.prop( 'disabled', true );
			$status.removeClass( 'is-success is-error' ).text( i18n.saving || 'Saving…' );

			ajax( payload )
				.done( function ( res ) {
					if ( res && res.success ) {
						$status.addClass( 'is-success' ).text( res.data.message || i18n.saved );
						applyEnabledState( $card, res.data.enabled === 'yes' );
						$card.find( '.wbtm-gw-toggle-input' ).prop( 'checked', res.data.enabled === 'yes' );
						setTimeout( function () {
							$status.removeClass( 'is-success' ).text( '' );
						}, 2500 );
					} else {
						$status.addClass( 'is-error' ).text( ( res && res.data ) || i18n.error );
					}
				} )
				.fail( function () {
					$status.addClass( 'is-error' ).text( i18n.error );
				} )
				.always( function () {
					$btn.prop( 'disabled', false );
				} );
		} );

		function applyEnabledState( $card, isOn ) {
			$card.toggleClass( 'is-enabled', isOn ).toggleClass( 'is-disabled', ! isOn );
			$card.find( '.wbtm-gw-badge' ).text( isOn ? ( i18n.enabled || 'Enabled' ) : ( i18n.disabled || 'Disabled' ) );
		}

		// Initialise WC enhanced selects / tooltips inside the forms.
		try {
			if ( $.fn.selectWoo ) {
				$manager.find( 'select.wc-enhanced-select' ).selectWoo();
			} else if ( $.fn.select2 ) {
				$manager.find( 'select.wc-enhanced-select' ).select2();
			}
			$( document.body ).trigger( 'init_tooltips' );
		} catch ( err ) {
			/* non-fatal — fields still work as plain inputs */
		}
	} );
} )( jQuery );
