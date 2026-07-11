/* Coupon editor interactions — tabs, segmented controls, conditional fields,
 * day toggles, Select2. Scoped to .wbtm-cpn. */
( function ( $ ) {
	'use strict';

	$( function () {
		var $root = $( '.wbtm-cpn' );
		if ( ! $root.length ) {
			return;
		}

		/* ---- Tabs ---- */
		$root.on( 'click', '.wbtm-cpn__tab', function () {
			var tab = $( this ).data( 'tab' );
			$root.find( '.wbtm-cpn__tab' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
			$root.find( '.wbtm-cpn__panel' ).removeClass( 'is-active' );
			$root.find( '.wbtm-cpn__panel[data-panel="' + tab + '"]' ).addClass( 'is-active' );
		} );

		/* ---- Segmented: discount type controls the amount symbol + cap field ---- */
		function syncDiscountType() {
			var type = $root.find( 'input[name="wbtm_coupon[discount_type]"]:checked' ).val();
			$root.find( '.wbtm-cpn__segmented[data-controls="discount-type"] .wbtm-cpn__seg' ).each( function () {
				$( this ).toggleClass( 'is-active', $( this ).find( 'input' ).is( ':checked' ) );
			} );
			var $sym = $root.find( '[data-amount-symbol]' );
			$sym.text( type === 'percent' ? '%' : ( $sym.data( 'currency' ) || '' ) );
			$root.find( '[data-show-for="percent"]' ).toggle( type === 'percent' );
		}
		$root.on( 'change', 'input[name="wbtm_coupon[discount_type]"]', syncDiscountType );

		/* ---- Segmented: apply-to controls the bus/type pickers ---- */
		function syncApplyTo() {
			var val = $root.find( 'input[name="wbtm_coupon[apply_to]"]:checked' ).val();
			$root.find( '.wbtm-cpn__segmented[data-controls="apply-to"] .wbtm-cpn__seg' ).each( function () {
				$( this ).toggleClass( 'is-active', $( this ).find( 'input' ).is( ':checked' ) );
			} );
			$root.find( '[data-show-for-apply="specific"]' ).toggle( val === 'specific' );
		}
		$root.on( 'change', 'input[name="wbtm_coupon[apply_to]"]', syncApplyTo );

		/* ---- Day toggles ---- */
		$root.on( 'change', '.wbtm-cpn__day input', function () {
			$( this ).closest( '.wbtm-cpn__day' ).toggleClass( 'is-active', $( this ).is( ':checked' ) );
		} );

		/* ---- Code: force uppercase, strip spaces ---- */
		$root.on( 'input', '.wbtm-cpn__code', function () {
			var start = this.selectionStart;
			this.value = this.value.toUpperCase().replace( /\s+/g, '' );
			try { this.setSelectionRange( start, start ); } catch ( e ) {}
		} );

		/* ---- Select2 ---- */
		if ( $.fn.select2 ) {
			$root.find( '.wbtm-cpn__select2' ).each( function () {
				$( this ).select2( {
					width: '100%',
					placeholder: $( this ).data( 'placeholder' ) || '',
					allowClear: true
				} );
			} );
		}

		syncDiscountType();
		syncApplyTo();
	} );
} )( jQuery );
