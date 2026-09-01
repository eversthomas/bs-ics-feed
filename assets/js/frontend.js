/**
 * Frontend Script für BS WP ICS Feed Reader.
 * Beinhaltet:
 * 1. Barrierefreies Accordion-Aufklappen (Details)
 * 2. Clientseitiges Echtzeit-Suchfeld & Kategorie-Schnellfilter
 * 3. „In Kalender eintragen“-Dropdown & direkten .ics-Download
 *
 * Reines Vanilla JavaScript ohne externe Bibliotheken.
 */

document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	// 1. ACCORDION-AUFKLAPPEN (Teaser vs. Details)
	document.addEventListener('click', function (e) {
		var toggleBtn = e.target.closest('.bs-ics-toggle-btn');
		if (!toggleBtn) {
			return;
		}

		e.preventDefault();

		var card = toggleBtn.closest('.bs-ics-card');
		if (!card) {
			return;
		}

		var details = card.querySelector('.bs-ics-card-details');
		if (!details) {
			return;
		}

		var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
		var moreText = toggleBtn.getAttribute('data-more-text') || 'Weiterlesen';
		var lessText = toggleBtn.getAttribute('data-less-text') || 'Weniger anzeigen';
		var btnTextSpan = toggleBtn.querySelector('.bs-ics-btn-text') || toggleBtn;

		if (isExpanded) {
			toggleBtn.setAttribute('aria-expanded', 'false');
			details.classList.remove('is-open');
			btnTextSpan.textContent = moreText;
		} else {
			toggleBtn.setAttribute('aria-expanded', 'true');
			details.classList.add('is-open');
			btnTextSpan.textContent = lessText;
		}
	});

	// 2. „IN KALENDER EINTRAGEN“-DROPDOWN
	document.addEventListener('click', function (e) {
		var calBtn = e.target.closest('.bs-ics-cal-btn');

		// Alle geöffneten Menüs schließen
		document.querySelectorAll('.bs-ics-cal-menu').forEach(function (menu) {
			if (!calBtn || menu !== calBtn.nextElementSibling) {
				menu.hidden = true;
				if (menu.previousElementSibling) {
					menu.previousElementSibling.setAttribute('aria-expanded', 'false');
				}
			}
		});

		if (calBtn) {
			e.preventDefault();
			e.stopPropagation();
			var menu = calBtn.nextElementSibling;
			if (menu && menu.classList.contains('bs-ics-cal-menu')) {
				var isOpen = !menu.hidden;
				menu.hidden = isOpen;
				calBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
			}
		}
	});

	// Menü bei Escape schließen
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			document.querySelectorAll('.bs-ics-cal-menu').forEach(function (menu) {
				menu.hidden = true;
				if (menu.previousElementSibling) {
					menu.previousElementSibling.setAttribute('aria-expanded', 'false');
				}
			});
		}
	});

	// 3. CLIENTSEITIGER .ICS-DOWNLOAD (Apple / iCal)
	document.addEventListener('click', function (e) {
		var downloadBtn = e.target.closest('.bs-ics-download-ics');
		if (!downloadBtn) {
			return;
		}

		e.preventDefault();
		var base64Data = downloadBtn.getAttribute('data-ics');
		var filename = downloadBtn.getAttribute('data-filename') || 'termin.ics';

		if (!base64Data) {
			return;
		}

		try {
			var decodedData = decodeURIComponent(escape(window.atob(base64Data)));
			var blob = new Blob([decodedData], { type: 'text/calendar;charset=utf-8;' });
			var link = document.createElement('a');
			var url = URL.createObjectURL(blob);
			link.setAttribute('href', url);
			link.setAttribute('download', filename);
			link.style.visibility = 'hidden';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		} catch (err) {
			// Fallback falls Decoding fehlschlägt
			window.location.href = 'data:text/calendar;charset=utf-8;base64,' + base64Data;
		}
	});

	// 4. ECHTZEIT-SUCHE & KATEGORIE-FILTER
	var wrappers = document.querySelectorAll('.bs-ics-wrapper');
	wrappers.forEach(function (wrapper) {
		var searchInput = wrapper.querySelector('.bs-ics-search-input');
		var catButtons = wrapper.querySelectorAll('.bs-ics-cat-btn');
		var cards = wrapper.querySelectorAll('.bs-ics-card');

		if (!cards.length) {
			return;
		}

		var activeCategory = 'all';
		var searchTerm = '';

		function applyFilters() {
			var visibleCount = 0;

			cards.forEach(function (card) {
				var cardText = card.textContent.toLowerCase();
				var cardCat = (card.getAttribute('data-category') || '').toLowerCase();

				var matchesSearch = !searchTerm || cardText.indexOf(searchTerm) !== -1;
				var matchesCat = (activeCategory === 'all') || (cardCat.indexOf(activeCategory.toLowerCase()) !== -1);

				if (matchesSearch && matchesCat) {
					card.style.display = '';
					visibleCount++;
				} else {
					card.style.display = 'none';
				}
			});

			// Optionaler Hinweis wenn keine Termine zum Filter passen
			var emptyNote = wrapper.querySelector('.bs-ics-filter-empty');
			if (visibleCount === 0) {
				if (!emptyNote) {
					emptyNote = document.createElement('div');
					emptyNote.className = 'bs-ics-empty-state bs-ics-filter-empty';
					emptyNote.innerHTML = '<p>Keine Termine für diesen Filter gefunden.</p>';
					var container = wrapper.querySelector('.bs-ics-container');
					if (container) {
						container.parentNode.insertBefore(emptyNote, container.nextSibling);
					}
				}
				emptyNote.style.display = 'block';
			} else if (emptyNote) {
				emptyNote.style.display = 'none';
			}
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				searchTerm = this.value.trim().toLowerCase();
				applyFilters();
			});
		}

		catButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				catButtons.forEach(function (b) { b.classList.remove('is-active'); });
				this.classList.add('is-active');
				activeCategory = this.getAttribute('data-cat') || 'all';
				applyFilters();
			});
		});
	});
});
