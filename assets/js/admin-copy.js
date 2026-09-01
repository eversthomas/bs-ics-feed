/**
 * Admin Script für Tab-Navigation und Shortcode-Kopierfunktion.
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// 1. Tab-Navigation (ARIA-Tabs-Pattern + Persistenz des zuletzt aktiven Tabs)
		var $tabWrapper = $('.bs-ics-nav-tab-wrapper');
		var $tabs = $tabWrapper.find('.nav-tab');
		var tabStorageKey = 'bsIcsActiveAdminTab';

		function activateTab($tab) {
			if (!$tab || !$tab.length) {
				return;
			}
			var targetTab = $tab.data('tab');

			// Aktiven Tab in der Navigation umschalten (inkl. ARIA-Status & Roving Tabindex)
			$tabs.removeClass('nav-tab-active').attr({ 'aria-selected': 'false', tabindex: '-1' });
			$tab.addClass('nav-tab-active').attr({ 'aria-selected': 'true', tabindex: '0' });

			// Tab-Inhalte umschalten
			$('.bs-ics-tab-content').hide().removeClass('bs-ics-tab-active');
			$('#bs-ics-tab-' + targetTab).show().addClass('bs-ics-tab-active');
		}

		function rememberTab(targetTab) {
			try {
				window.localStorage.setItem(tabStorageKey, targetTab);
			} catch (err) {
				// localStorage evtl. nicht verfügbar (Privatmodus o. Ä.) - reine Komfortfunktion, kein Problem.
			}
		}

		$tabWrapper.on('click', '.nav-tab', function (e) {
			e.preventDefault();
			var $clickedTab = $(this);
			activateTab($clickedTab);
			rememberTab($clickedTab.data('tab'));
		});

		// Pfeiltasten-Navigation gemäß WAI-ARIA Tabs-Pattern
		$tabWrapper.on('keydown', '.nav-tab', function (e) {
			var currentIndex = $tabs.index(this);
			var newIndex = null;

			if (e.key === 'ArrowRight') {
				newIndex = (currentIndex + 1) % $tabs.length;
			} else if (e.key === 'ArrowLeft') {
				newIndex = (currentIndex - 1 + $tabs.length) % $tabs.length;
			} else if (e.key === 'Home') {
				newIndex = 0;
			} else if (e.key === 'End') {
				newIndex = $tabs.length - 1;
			}

			if (newIndex !== null) {
				e.preventDefault();
				var $target = $tabs.eq(newIndex);
				activateTab($target);
				$target.trigger('focus');
				rememberTab($target.data('tab'));
			}
		});

		// Zuletzt aktiven Tab wiederherstellen (falls vorhanden und gültig)
		try {
			var savedTab = window.localStorage.getItem(tabStorageKey);
			if (savedTab) {
				var $savedTab = $tabs.filter('[data-tab="' + savedTab + '"]');
				if ($savedTab.length) {
					activateTab($savedTab);
				}
			}
		} catch (err) {
			// localStorage evtl. nicht verfügbar - Standard-Tab (erster) bleibt aktiv.
		}

		// 2. Live-Vorschau im Tab „Kachel-Design“
		var $designPreview = $('#bs-ics-design-preview-wrapper');
		if ($designPreview.length) {
			var $cardStyle     = $('#bs_ics_card_style');
			var $inheritColors = $('input[name="bs_ics_design_settings[inherit_theme_colors]"]');
			var $accentColor   = $('#bs_ics_accent_color');
			var $bgColor       = $('#bs_ics_bg_color');
			var $shadowStyle   = $('#bs_ics_shadow_style');
			var $cardPadding   = $('#bs_ics_card_padding');
			var $borderRadius  = $('#bs_ics_border_radius');
			var $borderWidth   = $('#bs_ics_border_width');
			var $borderColor   = $('#bs_ics_border_color');

			function setPreviewModifierClass(prefix, value) {
				var el = $designPreview[0];
				el.className = el.className.replace(new RegExp('\\b' + prefix + '\\S+', 'g'), '').trim();
				el.classList.add(prefix + value);
			}

			function updateDesignPreview() {
				setPreviewModifierClass('bs-ics-style-', $cardStyle.val() || 'card');
				setPreviewModifierClass('bs-ics-shadow-', $shadowStyle.val() || 'subtle');
				setPreviewModifierClass('bs-ics-pad-', $cardPadding.val() || 'normal');
				$designPreview.toggleClass('bs-ics-inherit-colors', $inheritColors.is(':checked'));

				var vars = '--bs-ics-accent: ' + ($accentColor.val() || '#0073aa') + '; '
					+ '--bs-ics-bg: ' + ($bgColor.val() || '#ffffff') + '; '
					+ '--bs-ics-radius: ' + ($borderRadius.val() || 8) + 'px; '
					+ '--bs-ics-border-w: ' + ($borderWidth.val() || 1) + 'px; '
					+ '--bs-ics-border-c: ' + ($borderColor.val() || '#e2e8f0') + ';';

				$designPreview.attr('style', 'margin: 0; max-width: 340px; ' + vars);
			}

			$('#bs-ics-tab-design').on('input change', 'input, select', updateDesignPreview);
			updateDesignPreview();
		}

		// 3. Shortcode Copy-to-Clipboard
		var $copyBtn = $('#bs-ics-copy-shortcode-btn');
		var $shortcodeInput = $('#bs-ics-shortcode-input');
		var defaultCopyText = (typeof bsIcsAdminCopy !== 'undefined' && bsIcsAdminCopy.copyText) ? bsIcsAdminCopy.copyText : 'Shortcode kopieren';
		var copiedText = (typeof bsIcsAdminCopy !== 'undefined' && bsIcsAdminCopy.copiedText) ? bsIcsAdminCopy.copiedText : 'Kopiert!';
		var resetTimer = null;

		function copyShortcode() {
			var textToCopy = $shortcodeInput.val();
			if (!textToCopy) {
				return;
			}

			// In die Zwischenablage kopieren (Clipboard API mit Fallback)
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(textToCopy).then(showCopiedFeedback).catch(fallbackCopy);
			} else {
				fallbackCopy();
			}
		}

		function fallbackCopy() {
			$shortcodeInput.focus().select();
			try {
				document.execCommand('copy');
				showCopiedFeedback();
			} catch (err) {
				console.error('Fehler beim Kopieren des Shortcodes:', err);
			}
		}

		function showCopiedFeedback() {
			$copyBtn.addClass('bs-ics-copied');
			$copyBtn.find('.btn-text').text(copiedText);

			if (resetTimer) {
				clearTimeout(resetTimer);
			}

			resetTimer = setTimeout(function () {
				$copyBtn.removeClass('bs-ics-copied');
				$copyBtn.find('.btn-text').text(defaultCopyText);
			}, 2000);
		}

		$copyBtn.on('click', function (e) {
			e.preventDefault();
			copyShortcode();
		});

		$shortcodeInput.on('click', function () {
			$(this).select();
		});
	});
})(jQuery);
