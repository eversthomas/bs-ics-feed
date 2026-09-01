/**
 * Admin Script für Tab-Navigation und Shortcode-Kopierfunktion.
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// 1. Tab-Navigation
		$('.bs-ics-nav-tab-wrapper').on('click', '.nav-tab', function (e) {
			e.preventDefault();

			var $clickedTab = $(this);
			var targetTab = $clickedTab.data('tab');

			// Aktiven Tab in der Navigation umschalten
			$clickedTab.siblings('.nav-tab').removeClass('nav-tab-active');
			$clickedTab.addClass('nav-tab-active');

			// Tab-Inhalte umschalten
			$('.bs-ics-tab-content').hide().removeClass('bs-ics-tab-active');
			$('#bs-ics-tab-' + targetTab).show().addClass('bs-ics-tab-active');
		});

		// 2. Shortcode Copy-to-Clipboard
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
