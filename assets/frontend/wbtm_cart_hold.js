/**
 * Seat-hold countdown for the cart and checkout pages.
 *
 * On the booking page the countdown badge lives next to the seat plan. Once the
 * seats are in the cart, the customer moves to the cart/checkout page where that
 * badge no longer exists — so this standalone, dependency-free widget shows the
 * same "your seats are held for MM:SS" reassurance there.
 *
 * The deadline is a FIXED moment stored server-side in the WooCommerce session
 * (WBTM_Seat_Hold::cart_hold_deadline), so the countdown keeps ticking toward
 * the same instant across page reloads instead of restarting. `expires` and
 * `now` are both server timestamps; the initial remaining time is derived from
 * them and then ticked down against the local wall clock, which makes it immune
 * to any difference between the browser's clock and the server's.
 *
 * Self-contained on purpose (no jQuery, injects its own styles): the booking
 * page assets are not loaded on cart/checkout, and the block cart / checkout
 * render a React DOM we cannot rely on.
 */
(function () {
	'use strict';

	var cfg = window.wbtm_cart_hold;
	if (!cfg || !cfg.expires) {
		return;
	}

	// True seconds left at the moment the page was rendered, from the server's
	// own clock — then counted down locally so a skewed browser clock can't throw
	// it off.
	var initialRemaining = Math.round(cfg.expires - (cfg.now || cfg.expires));
	var localStart = Date.now();

	function remainingNow() {
		return initialRemaining - Math.floor((Date.now() - localStart) / 1000);
	}

	function injectStyles() {
		if (document.getElementById('wbtm-cart-hold-style')) {
			return;
		}
		var css =
			'.wbtm-cart-hold{display:flex;align-items:center;gap:8px;justify-content:center;' +
			'margin:0 0 16px;padding:10px 14px;border-radius:8px;background:#e8f4e8;' +
			'border:1px solid #2e7d32;color:#1b5e20;font-weight:600;font-size:14px;' +
			'line-height:1.4;text-align:center;}' +
			'.wbtm-cart-hold__time{font-variant-numeric:tabular-nums;}' +
			'.wbtm-cart-hold.is-urgent{background:#fff4e5;border-color:#e65100;color:#b34700;}' +
			'.wbtm-cart-hold.is-expired{background:#fdecea;border-color:#c62828;color:#b71c1c;}' +
			'.wbtm-cart-hold svg{flex:none;}';
		var style = document.createElement('style');
		style.id = 'wbtm-cart-hold-style';
		style.appendChild(document.createTextNode(css));
		document.head.appendChild(style);
	}

	// Insert the banner at the top of whatever cart/checkout container exists —
	// classic markup first, then the block equivalents, then a safe fallback.
	function mountPoint() {
		var selectors = [
			'.woocommerce-checkout .woocommerce-notices-wrapper',
			'.woocommerce-cart .woocommerce-notices-wrapper',
			'form.woocommerce-checkout',
			'form.woocommerce-cart-form',
			'.wc-block-checkout',
			'.wc-block-cart',
			'.woocommerce'
		];
		for (var i = 0; i < selectors.length; i++) {
			var el = document.querySelector(selectors[i]);
			if (el) {
				return el;
			}
		}
		return null;
	}

	function clockIcon() {
		return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
			'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
			'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
	}

	function format(remaining) {
		var m = Math.floor(remaining / 60);
		var s = remaining % 60;
		return ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
	}

	var banner = null;
	var timeEl = null;
	var timer = null;

	function ensureBanner() {
		if (banner && document.body.contains(banner)) {
			return banner;
		}
		var host = mountPoint();
		if (!host) {
			return null;
		}
		injectStyles();
		banner = document.createElement('div');
		banner.className = 'wbtm-cart-hold';
		banner.setAttribute('role', 'status');
		banner.innerHTML = clockIcon() +
			'<span>' + (cfg.label || 'Your seats are held for') +
			' <span class="wbtm-cart-hold__time"></span></span>';
		host.insertBefore(banner, host.firstChild);
		timeEl = banner.querySelector('.wbtm-cart-hold__time');
		return banner;
	}

	function showExpired() {
		if (timer) {
			window.clearInterval(timer);
			timer = null;
		}
		if (!ensureBanner()) {
			return;
		}
		banner.classList.remove('is-urgent');
		banner.classList.add('is-expired');
		banner.innerHTML = clockIcon() + '<span>' +
			(cfg.expired || 'Your seat hold has expired. Please review your seats.') + '</span>';
	}

	function tick() {
		var remaining = remainingNow();
		if (remaining <= 0) {
			showExpired();
			return;
		}
		if (!ensureBanner() || !timeEl) {
			return;
		}
		timeEl.textContent = format(remaining);
		banner.classList.toggle('is-urgent', remaining < 60);
	}

	function start() {
		if (remainingNow() <= 0) {
			// Already lapsed before the page even rendered — just show the notice,
			// never reload (that would loop while the seats sit in the cart).
			showExpired();
			return;
		}
		tick();
		timer = window.setInterval(tick, 1000);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
