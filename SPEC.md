# Plugin-Spezifikation: BS WP ICS Feed Reader

## 1. Übersicht & Zielsetzung

Ein modulares, performantes und sicheres WordPress-Plugin zur Verwaltung und strukturierten Ausgabe von ICS-Kalender-Feeds. Nutzer können Feeds als Custom Post Type anlegen, die Struktur per Klick analysieren, gewünschte Felder auswählen und das Kachel-Layout über native CSS-Variablen steuern. Die Ausgabe erfolgt über den Shortcode `[bs_ics_calendar]`.

* **Plugin-Name:** BS WP ICS Feed Reader
* **Plugin-Slug:** `bs-wp-ics-feed-reader`
* **Textdomain:** `bs-wp-ics-feed-reader`
* **Prefix (Funktionen/Klassen/Konstanten):** `BS_ICS_`, `bs_ics_`

---

## 2. Architektur & Dateistruktur

```text
bs-wp-ics-feed-reader/
├── bs-wp-ics-feed-reader.php         # Plugin-Bootstrap, Lifecycle-Hooks, Autoloading & Cron
├── uninstall.php                     # Vollständige Bereinigung bei Plugin-Löschung
├── README.md                         # Umfassende Dokumentation für Nutzer und Entwickler
├── BS-PluginDesignSystem.md          # Universelles Designsystem für BS-Plugin-Backends
├── SPEC.md                           # Technische Spezifikation
├── ROADMAP.md                        # Projektplan & Phasen
├── PROMPTS.md                        # Entwicklungs-Prompts
├── languages/
│   └── bs-wp-ics-feed-reader.pot     # GNU gettext Übersetzungsvorlage
├── includes/
│   ├── class-bs-ics-cpt.php          # CPT 'bs_ics_feed' & Spaltenverwaltung
│   ├── class-bs-ics-parser.php       # RFC 5545 Parser (Zeitzonen, Unfolding, ReDoS-Schutz)
│   ├── class-bs-ics-admin.php        # Tabbed Meta-Boxes, Pill-Switches, Dreier-Schema, AJAX-Sync
│   ├── class-bs-ics-renderer.php     # Shortcode [bs_ics_calendar], Schema.org JSON-LD, Filter, Export
│   └── class-bs-ics-block.php        # Server-Side Rendered Gutenberg-Block (bs-wp-ics/calendar)
└── assets/
    ├── css/
    │   ├── admin.css                 # BS Design System (OKLCH, Pill-Switches, Dreier-Schema)
    │   ├── frontend.css              # Vanilla CSS Grid & List, Presets, Filter, Add-to-Cal, a11y
    │   └── block-editor.css          # Gutenberg Editor Preview Stylesheet
    └── js/
        ├── admin-inspect.js          # AJAX Feed-Inspektion mit dynamischem Table-Reload
        ├── admin-copy.js             # Shortcode One-Click-Copy mit visuellem Feedback
        ├── frontend.js               # Accordion, Echtzeit-Filter & Add-to-Calendar (Vanilla JS)
        └── block.js                  # Gutenberg Block-Registrierung & Live-Vorschau
```

---

## 3. Datenstruktur & Post-Meta Schema

Jeder Kalender-Feed ist ein eigener Post im Custom Post Type `bs_ics_feed`.

| Meta-Key | Typ | Beschreibung |
| :--- | :--- | :--- |
| `_bs_ics_feed_url` | `string` | Bereinigte Quell-URL des ICS-Feeds (`https://...`) |
| `_bs_ics_available_fields` | `array` | Vom Parser dynamisch erkannte RFC-Felder (`SUMMARY`, `LOCATION` etc.) |
| `_bs_ics_field_config` | `array` | Feld-Konfiguration: `[ FIELD => [ 'teaser' => bool, 'detail' => bool, 'label' => string ] ]` |
| `_bs_ics_display_settings` | `array` | Layout (`grid`/`list`), Sortierung (`asc`/`desc`), Limit, Datumsformat, Filter (`only_future`), Weiterlesen-Modus (`read_more_mode`: `expand`/`single`/`none`), Button-Texte (`read_more_text`, `read_less_text`, `back_text`) |
| `_bs_ics_design_settings` | `array` | Spalten (1–4), Akzentfarbe, Hintergrund, Rahmenradius |
| `_bs_ics_cached_events` | `array` | Normalisierte, geparste Event-Objekte (JSON/Array) |
| `_bs_ics_last_synced` | `int` | Unix-Timestamp der letzten erfolgreichen Synchronisation |

---

## 4. Parser-Vorgaben (RFC 5545) & Caching

* **Line-Unfolding:** Zeilenumbrüche mit folgendem Leerzeichen oder Tab (`\r\n ` oder `\n\t`) müssen vor dem Parsen zusammengeführt werden.
* **Protokoll-Handling:** `webcal://`-URLs werden vor dem Request via `wp_remote_get()` in `https://` umgewandelt.
* **Zeitzonen & Datumsnormalisierung:**
  * **UTC-Zeiten (`...Z`):** Konvertierung in die konfigurierte WordPress-Zeitzone (`wp_timezone()`).
  * **Lokale Zeiten mit `TZID`:** Parsing über PHP `DateTimeImmutable` unter Berücksichtigung der angegebenen Zeitzone.
  * **Ganztagestermine (`VALUE=DATE`):** Explizite Kennzeichnung mit `all_day => true`. Keine Zeitzonenkonvertierung oder UTC-Offsets anwenden, um Datumsverschiebungen auf den Vortag (z. B. 23:00 Uhr) zu verhindern.
* **Bereinigung:** Textmaskierungen (`\,`, `\;`, `\n`) werden sauber dekodiert.
* **Stale-Cache-Fallback:** Schlägt ein Abruf fehl (z. B. HTTP 404/500, Netzwerk-Timeout oder ungültige Antwort), bleibt der bestehende Cache `_bs_ics_cached_events` zwingend erhalten und es wird eine Fehlermeldung protokolliert/ausgegeben.

---

## 5. UI & Admin-Designsystem (BS-PluginDesignSystem)

* **Designhaltung & Tokens:** Umgesetzt nach `BS-PluginDesignSystem.md` mit kühler Grau-Blau-Neutral-Skala (`--bs-neutral-*`), Petrol-Akzent (`--bs-accent-*`), Pill-Switches für Status/Toggles und flachen Panel-Karten (`--bs-neutral-50`).
* **Tab-Navigation:** Strukturierte WordPress-Tabs (Quelle, Felder & Inhalt, Darstellung, Kachel-Design).
* **Teaser- vs. Detailansicht-Zuweisung (Tab 2):** Jedes RFC-Feld kann separat für die Listen-Übersicht (`teaser`) und/oder die Detailansicht (`detail`) aktiviert werden.
* **Checkbox-State-Preservation:** Bei erneuter Feed-Analyse und -Synchronisation via AJAX bleiben zuvor ausgewählte Felder und benutzerdefinierte Labels aktiv und erhalten.
* **Clipboard-Feedback:** Der One-Click-Kopierbutton in der Shortcode-Sidebar liefert sofortiges visuelles Feedback (Wechsel zu „Kopiert!“ mit automatischem Reset).

---

## 6. Frontend-Renderer & Weiterlesen-System

* **Semantische Zeitangaben (a11y & SEO):** Datums- und Uhrzeitwerte müssen als semantisches HTML5-Tag gerendert werden:
  ```html
  <time datetime="2026-09-01T10:00:00+02:00">01.09.2026 10:00</time>
  ```
  bzw. bei Ganztagesterminen:
  ```html
  <time datetime="2026-09-01">01.09.2026</time>
  ```
* **Formatierung Ganztagesevents:** Wenn `all_day === true`, wird ausschließlich das Datum ohne Zeitangabe ausgegeben.
* **Weiterlesen-Modi (`read_more_mode`):**
  * **Aufklappen (`expand`):** Teaser-Felder sind direkt sichtbar; Klick auf *„Weiterlesen“* klappt den Container `.bs-ics-card-details` mit den Detail-Feldern fließend auf; Button wechselt zu *„Weniger anzeigen“*.
  * **Einzelansicht (`single`):** Klick auf *„Weiterlesen“* verlinkt auf `?bs_event=<uid>`. Der Renderer schaltet bei vorhandenem Parameter auf die vollwertige Einzelansicht des Termins mit allen Detail-Informationen und einem *„Zurück zur Übersicht“*-Link um.
* **Escaping- & Sicherheitsregeln bei der Ausgabe:**
  * **`SUMMARY` & `LOCATION`:** Strikt mit `esc_html()` maskiert.
  * **`DESCRIPTION`:** Verarbeitet über `nl2br(wp_kses_post())`, um sichere HTML-Formatierungen und Absätze zu erhalten.
  * **Attribute & CSS-Variablen:** Strikt mit `esc_attr()` maskiert.
* **Empty-States:** Wenn keine Termine vorhanden sind (oder alle gefiltert wurden), wird ein barrierefreier Hinweistext (*„Keine anstehenden Termine vorhanden.“*) ausgegeben.

---

## 7. Styling- & Design-Philosophie

* **Keine externen Frameworks:** Reines Vanilla CSS mit modernem CSS Grid und Flexbox.
* **CSS Custom Properties:** Alle visuellen Einstellungen aus Tab 4 werden dynamisch als Inline-Variablen am Wrapper-Element injiziert:
  ```html
  <div class="bs-ics-container" style="--bs-ics-cols: 3; --bs-ics-accent: #0073aa; --bs-ics-radius: 8px; --bs-ics-bg: #ffffff;">
  ```
* **Kompaktheit & Responsiveness:** Das Stylesheet beschränkt sich auf modulare Layout-Regeln, Typografie, `:empty`-Pseudoklassen, fließende Aufklapp-Transitionen und responsive Breakpoints (1-spaltig auf Mobile).

---

## 8. Sicherheits- & WordPress-Standards

* **CSRF-Schutz:** Jeder AJAX-Request und jeder Speichervorgang prüft Nonces via `check_ajax_referer()` bzw. `check_admin_referer()`.
* **Rechteprüfung:** Zugriff ausschließlich bei erfüllter Berechtigung `current_user_can('edit_post', $post_id)`.
* **Sanitizing bei Eingabe:**
  * URL: `esc_url_raw()`
  * Strings: `sanitize_text_field()`
  * Farben: `sanitize_hex_color()`
  * Zahlen: `absint()`
* **Lifecycle & Updatesicherheit:** Bei manuellen Plugin-ZIP-Updates bleiben alle CPT-Einträge und Metadaten in `wp_postmeta` zu 100% erhalten. `uninstall.php` löscht Daten nur bei bewusster Plugin-Löschung.