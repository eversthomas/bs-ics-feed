# Projektplan: BS WP ICS Feed Reader

---

## Phase 1: Grundgerüst, CPT & Tabbed Admin UI
- [x] Hauptdatei `bs-wp-ics-feed-reader.php` mit Header, Autoloading und Lifecycle-Hooks anlegen.
- [x] `uninstall.php` für saubere Datenbereinigung aller CPTs und Metadaten implementieren.
- [x] CPT `bs_ics_feed` registrieren (`public => false`, `show_ui => true`, `supports => ['title']`).
- [x] Meta-Box mit nativer WordPress-Tab-Navigation aufsetzen:
  - Tab 1: **Quelle** (URL-Eingabe, Sync-Button, Status & Last-Synced-Anzeige)
  - Tab 2: **Felder & Inhalt** (Dynamische Checkboxen, Label-Overrides)
  - Tab 3: **Darstellung** (Layout Grid/List, Limit, Sortierung, Filter "Nur zukünftige Termine", Datumsformat)
  - Tab 4: **Kachel-Design** (Grid-Spalten 1–4, Akzentfarbe, Hintergrundfarbe, Rahmenradius)
- [x] Sidebar-Meta-Box mit Copy-to-Clipboard für den Shortcode `[bs_ics_calendar id="..."]` und visuellem Feedback („Kopiert!“) erstellen.

---

## Phase 2: Parser-Modul & Manuelle AJAX-Synchronisation
- [x] `includes/class-bs-ics-parser.php` erstellen:
  - Line-Unfolding (RFC 5545) für mehrzeilige Properties.
  - Datums- und Zeitzonen-Normalisierung auf `wp_timezone()`.
  - Ganztagestermine (`VALUE=DATE`) als `all_day => true` flaggen ohne UTC-Offset-Verschiebung.
  - Feldextraktion und Text-Unescaping (`\,`, `\;`, `\n`).
- [x] AJAX-Endpunkt `bs_ics_sync_feed` in `class-bs-ics-admin.php` registrieren:
  - Nonce- & Rechteprüfung (`check_ajax_referer`, `current_user_can('edit_post')`).
  - Abruf via `wp_remote_get()` mit `webcal://` $\rightarrow$ `https://`-Ersetzung.
  - **Stale-Cache-Fallback:** Bestehende `_bs_ics_cached_events` bei Netzwerk- oder HTTP-Fehlern schützen.
  - Aggregation aller gefundenen RFC-Schlüssel zur UI-Rückgabe.
  - **Checkbox-State-Preservation:** Vorhandene Feld-Aktivierungen und Label-Overrides im Array beibehalten.
  - Speichern des bereinigten Event-Arrays in `_bs_ics_cached_events`.
- [x] `assets/js/admin-inspect.js` für interaktives Feedback, Lade-Spinner und dynamisches Rendern der Checkbox-Liste schreiben.

---

## Phase 3: Shortcode-Renderer & Vanilla CSS
- [x] `includes/class-bs-ics-renderer.php` aufbauen:
  - Shortcode `[bs_ics_calendar id="..." layout="..." limit="..."]` registrieren.
  - Auslesen des lokalen Event-Caches aus `_bs_ics_cached_events`.
  - Filterung nach Zeitraum (Zukunft/Alle) und Sortierung (ASC/DESC).
  - Semantische HTML5-Ausgabe mit `<time datetime="...">` für barrierefreie Datumsanzeige.
  - Ganztagestermine: Ausgabe rein als Datum ohne Uhrzeit.
  - Sicheres Escaping: `nl2br(wp_kses_post())` für `DESCRIPTION`, `esc_html()` für `SUMMARY` & `LOCATION`.
- [x] Inline-Styles für CSS Custom Properties (`--bs-ics-*`) am Container injizieren.
- [x] `assets/css/frontend.css` mit responsivem CSS Grid & Listenansicht (1-spaltig auf mobilen Geräten) erstellen.

---

## Phase 4: Validierung, Error-Handling & Polish
- [x] Abfangen fehlerhafter ICS-Dateien und leere Feld-Zustände (`:empty`-CSS-Styling).
- [x] Frontend-Empty-States (*„Keine anstehenden Termine vorhanden.“*).
- [x] Vollständige Internationalisierung (i18n) mit Text-Domain `bs-wp-ics-feed-reader`.
- [x] Code-Audit und Validierung nach WordPress Coding Standards (WPCS).

---

## Phase 5: BS-Designsystem & Weiterlesen-/Detailansichts-Erweiterung
- [x] Admin-UI gemäß `BS-PluginDesignSystem.md` modernisieren (OKLCH-Tokens, Pill-Switches, Panel-Karten, klare Typografie).
- [x] Tab 2 (Felder & Inhalt): Getrennte Aktivierung für *„In Übersicht (Teaser)“* und *„In Detailansicht“* implementieren.
- [x] Tab 3 (Darstellung): Weiterlesen-Modus (`expand` / `single` / `none`) und individuelle Button-Texte (*Weiterlesen*, *Weniger anzeigen*, *Zurück zur Übersicht*) einbauen.
- [x] Frontend-Renderer & CSS/JS: Fließenden Accordion-Aufklappmodus und Einzelansichts-Modus (`?bs_event=<uid>`) mit Zurück-Link umsetzen.

---

## Phase 6: SEO, a11y, Schnellfilter & Add-to-Calendar
- [x] Schema.org Event JSON-LD direkt im Body ausgeben (Rich Snippets ohne `<head>`-Eingriff).
- [x] WCAG 2.1 AA Screenreader-Klassen (`.bs-ics-sr-only`) und WAI-ARIA Attribute implementieren.
- [x] „In Kalender eintragen“-Exportmenü für Google Calendar, Outlook Web und clientseitigen .ics-Download.
- [x] Clientseitige Echtzeit-Suche und Kategorie-Filterbar (Vanilla JS).
- [x] Hintergrund-Synchronisation via `WP-Cron` (`bs_ics_cron_sync_event`).
- [x] Vollständige `README.md` Dokumentation anlegen.

---

## Phase 7: Erweiterte Design-Presets & Gutenberg-Block
- [x] Design-Presets (Klassisch / Minimal Flat / Accent Header) und Theme-Farbvererbung (`inherit_theme_colors`).
- [x] Einstellbare Schatten-Stärken, Innenabstände (Padding) und Rahmenbreiten/Farben in Tab 4.
- [x] Server-Side Rendered Gutenberg-Block `bs-wp-ics/calendar` mit Live-Vorschau und Sidebar-InspectorControls.