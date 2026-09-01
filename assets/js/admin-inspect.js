/**
 * Admin Script für AJAX-gestützte Feed-Analyse und dynamisches Checkbox-Rendering.
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $syncBtn     = $('#bs-ics-sync-btn');
		var $urlInput    = $('#bs_ics_feed_url');
		var $spinner     = $('#bs-ics-sync-spinner');
		var $msgBox      = $('#bs-ics-sync-message');
		var $btnText     = $syncBtn.find('.sync-btn-text');
		var originalText = $btnText.text();

		// Interaktiver Status-Wechsel für Pill-Switches (BS Design System)
		$(document).on('change', '.bs-toggle-switch input[type="checkbox"]', function () {
			var $checkbox = $(this);
			var $status = $checkbox.closest('.bs-toggle-switch').find('.bs-toggle-status');
			var i18n = (typeof bsIcsAdminSync !== 'undefined' && bsIcsAdminSync.i18n) ? bsIcsAdminSync.i18n : {};

			if ($checkbox.is(':checked')) {
				$status.text(i18n.active || 'Aktiv');
			} else {
				$status.text(i18n.inactive || 'Inaktiv');
			}
		});

		if (!$syncBtn.length) {
			return;
		}

		$syncBtn.on('click', function (e) {
			e.preventDefault();

			var feedUrl = $.trim($urlInput.val());
			var i18n = (typeof bsIcsAdminSync !== 'undefined' && bsIcsAdminSync.i18n) ? bsIcsAdminSync.i18n : {};

			if (!feedUrl) {
				showNotice(i18n.enterUrl || 'Bitte gib zuerst eine gültige Feed-URL ein.', 'error');
				$urlInput.focus();
				return;
			}

			// UI in Ladezustand versetzen
			$syncBtn.prop('disabled', true);
			$spinner.addClass('is-active');
			$btnText.text(i18n.syncing || 'Synchronisiere Feed...');
			$msgBox.empty();

			$.ajax({
				url: bsIcsAdminSync.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'bs_ics_sync_feed',
					nonce: bsIcsAdminSync.nonce,
					post_id: bsIcsAdminSync.postId,
					feed_url: feedUrl
				},
				success: function (response) {
					if (response && response.success) {
						var data = response.data;

						// 1. Status-Zähler & Datum aktualisieren
						if (data.last_synced_formatted) {
							$('#bs-ics-last-synced-label').text(data.last_synced_formatted);
						}
						if (typeof data.count !== 'undefined') {
							$('#bs-ics-cached-count').text(data.count);
						}

						// 2. Felder-Tabelle dynamisch aktualisieren
						if (data.field_config_html) {
							$('#bs-ics-fields-tbody').html(data.field_config_html);
						}

						// 3. Erfolgsmeldung anzeigen
						showNotice(data.message || (i18n.syncSuccess || 'Feed erfolgreich synchronisiert!'), 'success');
					} else {
						var errorMsg = (response && response.data && response.data.message) ? response.data.message : (i18n.syncError || 'Fehler bei der Synchronisation.');
						showNotice(errorMsg, 'error');
					}
				},
				error: function (xhr) {
					var errorMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
						? xhr.responseJSON.data.message
						: (i18n.networkError || 'Verbindungsfehler beim Abruf des Feeds.');
					showNotice(errorMsg, 'error');
				},
				complete: function () {
					// UI zurücksetzen
					$syncBtn.prop('disabled', false);
					$spinner.removeClass('is-active');
					$btnText.text(originalText);
				}
			});
		});

		/**
		 * Zeigt eine formatierte Notice-Nachricht an.
		 *
		 * @param {string} text  Die Nachricht.
		 * @param {string} type  'success' oder 'error'.
		 */
		function showNotice(text, type) {
			var noticeClass = (type === 'success') ? 'notice-success' : 'notice-error';
			var html = '<div class="notice ' + noticeClass + ' inline" style="margin: 5px 0 0 0; padding: 8px 12px;"><p style="margin: 0;">' + $('<div>').text(text).html() + '</p></div>';
			$msgBox.html(html);
		}
	});
})(jQuery);
