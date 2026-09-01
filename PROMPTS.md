# Antigravity Task-Prompts: BS WP ICS Feed Reader

Kopiere den jeweiligen Prompt in Antigravity, um die einzelnen Phasen schrittweise abzuarbeiten.

---

## Prompt für Phase 1: Fundament, CPT & Tabbed UI

```text
Du bist ein erfahrener WordPress-Entwickler. Implementiere Phase 1 des Plugins "BS WP ICS Feed Reader" (Slug: bs-wp-ics-feed-reader) gemäß SPEC.md und ROADMAP.md.

Erstelle folgende Dateien mit vollständigem, produktionsreifem Code:

1. `bs-wp-ics-feed-reader.php`:
   - Standard-Plugin-Header (Plugin Name: BS WP ICS Feed Reader, Text Domain: bs-wp-ics-feed-reader).
   - Konstanten für Pfade und Version (Prefix: `BS_ICS_`).
   - Initialisierung und Einbindung der Includes.
   - Aktivierungs- und Deaktivierungshooks (kein Cron in dieser Phase).

2. `uninstall.php`:
   - Sicherheitscheck auf `WP_UNINSTALL_PLUGIN`.
   - Vollständiges Löschen aller `bs_ics_feed`-Posts und zugehörigen Post-Metas.

3. `includes/class-bs-ics-cpt.php`:
   - Registrierung des internen CPT `bs_ics_feed` (`public => false`, `show_ui => true`, `supports => ['title']`).

4. `includes/class-bs-ics-admin.php`:
   - Registrierung der Haupt-Meta-Box mit WordPress-nativer Tab-Navigation (`nav-tab-wrapper` mit den Tabs: 'source', 'fields', 'display', 'design').
   - Registrierung der Sidebar-Meta-Box mit Shortcode-Anzeige `[bs_ics_calendar id="..."]` und Copy-Button.
   - Enqueue von Admin-CSS und JS nur auf dem CPT-Screen (`bs_ics_feed`).
   - Speichern der Meta-Felder mit strikter Nonce- und Rechteprüfung (`current_user_can('edit_post')`).

5. `assets/css/admin.css` & `assets/js/admin-copy.js`:
   - CSS für saubere Tab-Umschaltung, Formular-Layouts und Sidebar-Box.
   - Vanilla JS für den One-Click-Copy-Button des Shortcodes mit visuellem Feedback (z. B. Wechsel zu „Kopiert!“).

Halte dich strikt an Vanilla PHP/JS ohne externe Libraries und achte auf saubere Nonces und Berechtigungsprüfungen.
```

---

## Prompt für Phase 2: Parser-Modul & Manuelle AJAX-Synchronisation

```text
Du bist ein erfahrener WordPress-Entwickler. Implementiere Phase 2 des Plugins "BS WP ICS Feed Reader" gemäß SPEC.md und ROADMAP.md.

Erstelle und erweitere folgende Komponenten:

1. `includes/class-bs-ics-parser.php`:
   - Line-Unfolding nach RFC 5545 (Zusammenführen umgebrochener Zeilen mit vorangestelltem Leerzeichen/Tab).
   - Parsing der `VEVENT`-Blöcke in ein normalisiertes PHP-Array.
   - Zeitzonen- & Datums-Handling:
     * UTC-Zeiten (`...Z`) auf `wp_timezone()` umrechnen.
     * Lokale Zeiten mit `TZID` via `DateTimeImmutable` parsen.
     * Ganztagestermine (`VALUE=DATE`): Explizit als `all_day => true` kennzeichnen; keine UTC-Offset-Verschiebung anwenden, um Datumsverfälschungen (z. B. auf den Vortag 23:00 Uhr) zu verhindern.
   - Text-Unescaping für maskierte Zeichen (`\,`, `\;`, `\n`).
   - Field-Discovery: Dynamische Aggregation aller im Feed enthaltenen RFC-Schlüssel (SUMMARY, LOCATION, DESCRIPTION etc.).

2. AJAX-Handler in `includes/class-bs-ics-admin.php`:
   - Registrierung von `wp_ajax_bs_ics_sync_feed`.
   - Strikte Prüfung mit `check_ajax_referer('bs_ics_admin_nonce', 'nonce')` und `current_user_can('edit_post', $post_id)`.
   - Feed-Abruf via `wp_remote_get()` mit Ersetzung von `webcal://` durch `https://`.
   - **Stale-Cache-Fallback:** Schlägt der Abruf fehl (HTTP-Fehler oder Timeout), bleibt der bisherige Cache in `_bs_ics_cached_events` zwingend erhalten.
   - **Checkbox-State-Preservation:** Bei erneuter Analyse bleiben zuvor vom Nutzer ausgewählte Felder (`_bs_ics_field_config`) und Labels aktiv erhalten.
   - Speichern der Events in `_bs_ics_cached_events`, der Felder in `_bs_ics_available_fields` und des Timestamps in `_bs_ics_last_synced`.
   - Strukturierte JSON-Rückgabe für das Admin-Interface.

3. `assets/js/admin-inspect.js`:
   - Event-Listener auf den Sync-/Analyse-Button.
   - AJAX-Call mit Lade-Spinner und Fehleranzeige.
   - Dynamisches Rendern/Aktualisieren der Checkbox-Tabelle im Tab "Felder & Inhalt" unter Beibehaltung aktiver Zustände.
```

---

## Prompt für Phase 3: Shortcode-Renderer & Vanilla CSS

```text
Du bist ein erfahrener WordPress-Entwickler. Implementiere Phase 3 des Plugins "BS WP ICS Feed Reader" gemäß SPEC.md und ROADMAP.md.

Erstelle folgende Komponenten:

1. `includes/class-bs-ics-renderer.php`:
   - Registrierung des Shortcodes `[bs_ics_calendar id="..." layout="..." limit="..."]`.
   - Laden des Event-Caches aus `_bs_ics_cached_events` des angegebenen Posts.
   - Filterung abgelaufener Termine (wenn im Tab "Darstellung" oder per Attribut aktiviert) und Sortierung (`ASC`/`DESC`).
   - Semantisches HTML-Rendering:
     * Barrierefreie Zeitangaben über `<time datetime="YYYY-MM-DDTHH:MM:SS+TZ">Formatted Date</time>`.
     * Ganztagestermine (`all_day === true`): Reine Datumsausgabe ohne Uhrzeitangabe (`<time datetime="YYYY-MM-DD">`).
     * Dynamische Ausgabe der in `_bs_ics_field_config` aktivierten Felder mit individuellen Labels.
   - Striktes Escaping:
     * `SUMMARY` und `LOCATION` mit `esc_html()`.
     * `DESCRIPTION` mit `nl2br(wp_kses_post())`.
     * Container-Attribute und CSS-Variablen mit `esc_attr()`.
   - Dynamische Inline-Styles für CSS Custom Properties (`--bs-ics-cols`, `--bs-ics-accent`, `--bs-ics-radius`, `--bs-ics-bg`).

2. `assets/css/frontend.css`:
   - Modernes Vanilla CSS Grid mit dynamischer Spaltenanzahl über `var(--bs-ics-cols, 3)`.
   - Listenansicht (`.bs-ics-layout-list`) und Kachelansicht (`.bs-ics-layout-grid`).
   - Responsive Breakpoints (1-spaltig auf mobilen Endgeräten).
   - Ansprechendes, neutrales Design für Event-Kacheln (Datum, Titel, Location, Badge, Beschreibung).
```

---

## Prompt für Phase 4: Validierung, Error-Handling & Polish

```text
Du bist ein erfahrener WordPress-Entwickler. Implementiere Phase 4 des Plugins "BS WP ICS Feed Reader" gemäß SPEC.md und ROADMAP.md.

Führe folgende Härtungen, Optimierungen und Tests durch:

1. Fehler- und Randfallbehandlung:
   - Graceful Degradation bei defekten, unvollständigen oder leeren ICS-Dateien (keine PHP-Fatal-Errors).
   - Saubere Fehlermeldungen im Admin-Bereich bei fehlerhaften HTTP-Antworten oder ungültigen URLs.
   - Frontend-Empty-States (*„Keine anstehenden Termine vorhanden.“*) mit barrierefreier Hinweisausgabe.
   - CSS-Styling für leere Felder (Nutzung der `:empty`-Pseudoklasse zur Vermeidung unschöner Leerabstände).

2. WordPress Standards & Internationalisierung:
   - Vollständige Übersetzungsvorbereitung aller Strings mit Text-Domain `bs-wp-ics-feed-reader` (`__()`, `_e()`, `esc_html__()` etc.).
   - Überprüfung aller Sanitization-, Validierungs- und Escaping-Vorgänge.
   - Bereinigung von PHP-Notices (z. B. Absicherung aller Array-Keys mit `isset()` bzw. `!empty()`).
   - Überprüfung der Einhaltung der WordPress Coding Standards (WPCS).
```