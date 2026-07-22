/**
 * Documents screen behaviour.
 *
 * Three small jobs: live search across every chapter, keeping the sidebar in
 * sync with what is on screen, and honouring a #hash deep link.
 *
 * The search filters whole chapters rather than individual lines, so a match
 * is always shown with the context around it.
 */
(function ($) {
	'use strict';

	var i18n = window.wbtmDocsI18n || {};

	$(function () {
		var $content = $('#wbtm-docs-content');
		if (!$content.length) {
			return;
		}

		var $chapters = $content.find('.wbtm-docs-chapter');
		var $heads    = $content.find('.wbtm-docs-group-head');
		var $navLinks = $('#wbtm-docs-nav').find('a[data-target]');
		var $noRes    = $('#wbtm-docs-no-results');
		var $count    = $('#wbtm-docs-search-count');
		var $bar      = $('.wbtm-docs-search-bar');
		var $input    = $('#wbtm-docs-search');

		/* ------------------------------ search ------------------------------ */

		// Cache the searchable text once — re-reading .text() on every keystroke
		// across ~40 chapters is the difference between instant and laggy.
		$chapters.each(function () {
			this.wbtmDocsText = ($(this).text() || '').toLowerCase();
		});

		function clearSearch() {
			$chapters.removeClass('wbtm-docs-hidden');
			$heads.removeClass('wbtm-docs-hidden');
			$navLinks.closest('li').removeClass('wbtm-docs-hidden');
			$noRes.attr('hidden', true).text('');
			$count.text('');
			$bar.removeClass('is-active');
		}

		function runSearch(raw) {
			var term = $.trim(String(raw || '')).toLowerCase();

			if (term.length < 2) {
				clearSearch();
				return;
			}

			$bar.addClass('is-active');

			var hits = 0;
			var visibleIds = {};

			$chapters.each(function () {
				var match = this.wbtmDocsText.indexOf(term) !== -1;
				$(this).toggleClass('wbtm-docs-hidden', !match);
				if (match) {
					hits++;
					visibleIds[this.id] = true;
				}
			});

			// A group heading only stays when at least one chapter under it survived.
			$heads.each(function () {
				var $next = $(this).nextUntil('.wbtm-docs-group-head', '.wbtm-docs-chapter');
				var keep  = $next.filter(':not(.wbtm-docs-hidden)').length > 0;
				$(this).toggleClass('wbtm-docs-hidden', !keep);
			});

			// Mirror the filtering in the sidebar so it matches what is on screen.
			$navLinks.each(function () {
				var id = $(this).data('target');
				$(this).closest('li').toggleClass('wbtm-docs-hidden', !visibleIds[id]);
			});

			if (hits === 0) {
				$noRes.text(i18n.noResults || 'Nothing matched that search.').removeAttr('hidden');
				$count.text('0');
			} else {
				$noRes.attr('hidden', true).text('');
				$count.text(hits + ' ' + (i18n.matches || 'matches'));
			}
		}

		var searchTimer = null;
		$input.on('input', function () {
			var value = this.value;
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(function () {
				runSearch(value);
			}, 160);
		});

		$input.on('keydown', function (e) {
			if (e.key === 'Escape') {
				this.value = '';
				clearSearch();
			}
		});

		$('#wbtm-docs-search-clear').on('click', function () {
			$input.val('').trigger('focus');
			clearSearch();
		});

		/* --------------------------- nav highlight -------------------------- */

		function setCurrent(id) {
			$navLinks.removeClass('is-current');
			$navLinks.filter('[data-target="' + id + '"]').addClass('is-current');
		}

		$navLinks.on('click', function () {
			setCurrent($(this).data('target'));
		});

		// Highlight whichever chapter is nearest the top of the viewport.
		if ('IntersectionObserver' in window) {
			var observer = new IntersectionObserver(function (entries) {
				var best = null;
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					if (!best || entry.boundingClientRect.top < best.boundingClientRect.top) {
						best = entry;
					}
				});
				if (best && best.target.id) {
					setCurrent(best.target.id);
				}
			}, { rootMargin: '-60px 0px -70% 0px', threshold: 0 });

			$chapters.each(function () {
				if (this.id) {
					observer.observe(this);
				}
			});
		}

		/* ---------------------------- deep links ---------------------------- */

		var hash = (window.location.hash || '').replace(/^#/, '');
		if (hash) {
			var $target = $content.find('#' + $.escapeSelector(hash));
			if ($target.length) {
				setCurrent(hash);
				window.setTimeout(function () {
					$target[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
				}, 80);
			}
		}
	});
}(jQuery));
