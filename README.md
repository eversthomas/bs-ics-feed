# BS WP ICS Feed Reader

> **Modulares, performantes und barrierefreies WordPress-Plugin zur Verwaltung und strukturierten Ausgabe von iCalendar/ICS-Kalender-Feeds mit Shortcode & Gutenberg-Block.**

[![Version](https://img.shields.io/badge/Version-1.1.0-blue.svg)](https://wordpress.org/)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WCAG](https://img.shields.io/badge/Accessibility-WCAG%202.1%20AA-success.svg)]()
[![Design System](https://img.shields.io/badge/Design%20System-BS--PluginDesignSystem-teal.svg)]()
[![Gutenberg Ready](https://img.shields.io/badge/Gutenberg-Block%20Ready-black.svg)]()

---

## 📖 Übersicht

**BS WP ICS Feed Reader** ermöglicht das einfache Einbinden und stilvolle Ausgeben externer Kalender-Feeds (z. B. aus Google Calendar, Apple iCloud, Outlook, REDAXO forCal, Nextcloud oder Vereinssoftware) in WordPress-Websites. 

Feeds werden als eigener Inhaltstyp verwaltet, per Klick synchronisiert, im lokalen Cache gespeichert und wahlweise über den **Gutenberg-Block (`ICS Kalender-Feed`)** oder den flexiblen Shortcode `[bs_ics_calendar id="..."]` im Frontend angezeigt.

---

## ✨ Hauptfunktionen

### 🧱 Nativer Gutenberg-Block (`bs-wp-ics/calendar`)
* **Echte Live-Vorschau im Editor:** Dank Server-Side-Rendering (`<ServerSideRender />`) sehen Redakteure direkt im Block-Editor die echten Termine und das reale Kacheldesign.
* **Bequeme Sidebar-Einstellungen (InspectorControls):** Feed-Auswahl per Dropdown, Spalten-Schieberegler (1–4), Layout-Umschaltung, Design-Presets und Filter direkt im Gutenberg-Panel.

### 🎨 Design-Presets & Theme-Harmonie
* **3 Design-Stile / Presets:**
  * **Klassisch (Card):** Weißer Kärtchen-Hintergrund, dezenter Schatten und farbige Akzentlinie.
  * **Minimal / Flat:** Rahmenbetont ohne Schatten – fügt sich nahtlos in moderne Block-Themes ein.
  * **Accent Header:** Vollflächig farbige Kopfzeile für Datum und Status.
* **Theme-Farbvererbung:** Optionale automatische Übernahme der Schrift- und Linkfarben des aktiven WordPress-Themes (ideal für Dark Mode oder farbige Container).
* **Granulare Steuerung:** Einstellbare Schattenstärke, Kachel-Innenabstände (Kompakt / Normal / Großzügig), Rahmenbreite und Rahmenfarbe.

### ⚡ Performance & Caching
* **Ausfallsicherer lokaler Cache (`_bs_ics_cached_events`):** Frontend-Aufrufe erzeugen keine externen HTTP-Requests – blitzschnelle Ladezeiten.
* **Stale-Cache-Fallback:** Bei Netzwerkstörungen, Server-Timeouts oder HTTP-Fehlern der Feed-Quelle bleibt der bestehende Termin-Cache geschützt und intakt.
* **Zero External Dependencies:** Kein jQuery im Frontend, kein Bootstrap, kein Tailwind – reines Vanilla CSS Grid und modernes Vanilla JS.

### 📅 RFC-5545 Parser & Zeitzonen
* **Line-Unfolding:** Zuverlässiges Zusammenführen mehrzeiliger Kalendereinträge nach RFC 5545.
* **Ganztagstermine (`VALUE=DATE`):** Werden automatisch erkannt und ohne Zeitzonen-Offsets dargestellt (verhindert das Verschieben auf 23:00 Uhr des Vortags).
* **Zeitzonen-Normalisierung:** Automatische Umrechnung von UTC-Zeiten (`Z`) und Zeitzonen-Kennungen (`TZID`) in die WordPress-Zeitzone (`wp_timezone()`).

### 🔍 Granulare Feldsteuerung & Weiterlesen-System
* **Teaser- vs. Detail-Trennung:** Lege für jedes RFC-Feld separat fest, ob es sofort in der Übersicht (Teaser) oder erst in den Details sichtbar ist.
* **Aufklapp-Modus (Accordion):** Fließendes Aufklappen zusätzlicher Detailfelder direkt in der Kachel mit interaktivem Button (*„Weiterlesen“* $\leftrightarrow$ *„Weniger anzeigen“*).
* **Einzelansichts-Modus:** Öffnet auf Wunsch eine separate, großzügige Einzelseite des Termins mit Zurück-Link (`?bs_event=<uid>`).

### 🎯 SEO & Barrierefreiheit (a11y)
* **Schema.org Event JSON-LD:** Gibt strukturierte Daten für Google Event Rich Snippets direkt im Shortcode-Body aus – **ohne jeden Eingriff in den `<head>`**.
* **Semantische `<time>`-Tags:** Vollständige ISO-8601 Zeitangaben für Screenreader und Suchmaschinen.
* **WAI-ARIA Konformität:** Vollständige Tastaturbedienbarkeit, `aria-expanded`, `aria-controls` und Screenreader-Labels.
* **Print-Stylesheet:** Optimierte Druckausgabe ohne Buttons und mit vollen Termindetails.

### 🚀 Zusatzfunktionen
* **„In Kalender eintragen“ (Add to Calendar):** Ein-Klick-Export zu Google Calendar, Outlook Web oder als Apple/ICS-Download.
* **Clientseitiger Schnellfilter:** Live-Suchfeld und Kategorie-Filter-Badges direkt im Frontend.
* **Automatischer Hintergrund-Sync (`WP-Cron`):** Stündlich, 2x täglich oder täglich im Hintergrund aktualisierbar.

---

## 📥 Installation

1. Lade das Plugin-Verzeichnis `bs-wp-ics-feed-reader` in den Ordner `/wp-content/plugins/` hoch (oder lade die ZIP-Datei unter **Plugins $\rightarrow$ Installieren $\rightarrow$ Plugin hochladen** hoch).
2. Aktiviere das Plugin im WordPress-Menü **Plugins**.
3. Navigiere zum neuen Menüpunkt **ICS Feeds $\rightarrow$ Neu hinzufügen**.

---

## 🛠️ Verwendung

### 1. Feed anlegen & synchronisieren
1. Gib dem Feed einen sprechenden Titel (z. B. *„AWO Veranstaltungskalender“*).
2. Trage im Tab **Quelle & Synchronisation** die Feed-URL ein.
3. Klicke auf **Feed analysieren & synchronisieren**.
4. Wähle im Tab **Felder & Inhalt** aus, welche Felder im Teaser und welche in den Details erscheinen sollen.
5. Konfiguriere im Tab **Darstellung** das Layout (Grid oder List), das Weiterlesen-Verhalten und Filter.
6. Passe im Tab **Kachel-Design** Stil, Spaltenanzahl, Farben und Rahmenradius an.
7. Klicke auf **Veröffentlichen**.

### 2. Im Gutenberg-Editor einbinden
1. Öffne einen Beitrag oder eine Seite im Block-Editor.
2. Tippe `/ics` oder `/kalender` und wähle den Block **ICS Kalender-Feed**.
3. Wähle in der rechten Seitenleiste den gewünschten Feed aus.
4. Die Vorschau wird sofort live im Editor gerendert!

### 3. Alternativ per Shortcode einbinden

Kopiere den Shortcode aus der Sidebar der Feed-Bearbeitung:

```text
[bs_ics_calendar id="123"]
```

#### Shortcode-Attribute (Optionale Overrides):

| Parameter | Werte | Standard | Beschreibung |
| :--- | :--- | :--- | :--- |
| `id` | Zahl | `0` | **Pflichtfeld:** Die ID des `bs_ics_feed`-Posts |
| `layout` | `grid`, `list` | *aus Feed* | Kachel-Raster (`grid`) oder Listenansicht (`list`) |
| `columns` | `1`, `2`, `3`, `4` | *aus Feed* | Anzahl der Spalten im Desktop-Grid |
| `limit` | Zahl (z. B. `5`) | `0` (alle) | Maximale Anzahl angezeigter Termine |
| `sort` | `asc`, `desc` | `asc` | Chronologisch aufsteigend oder absteigend |
| `only_future` | `true`, `false` | `true` | Nur anstehende Termine anzeigen |
| `style` | `card`, `flat`, `accent_header` | *aus Feed* | Design-Stil / Preset |
| `accent` | Hex-Farbe (z. B. `#0073aa`) | *aus Feed* | Überschreibt die Akzentfarbe |
| `mode` | `expand`, `single`, `none` | *aus Feed* | Weiterlesen-Modus (Aufklappen, Einzelansicht, Deaktiviert) |
| `filter` | `true`, `false` | *aus Feed* | Such- & Kategoriefilterleiste aktivieren/deaktivieren |
| `export` | `true`, `false` | *aus Feed* | „In Kalender eintragen“-Buttons anzeigen |

---

## 🔒 Sicherheit & Datenschutz

* **SSRF-Schutz:** Abruf externer Feeds über `wp_safe_remote_get()` mit Validierung und Timeout.
* **CSRF-Schutz:** Alle AJAX-Endpunkte und Formularaktionen sind mit Nonces abgesichert (`check_ajax_referer`, `check_admin_referer`).
* **Rechtekontrolle:** Zugriff nur für autorisierte Nutzer (`current_user_can('edit_post')`).
* **Late Escaping:** Alle Ausgaben werden vor der Anzeige im Browser konsequent mit `esc_html()`, `esc_attr()`, `esc_url()` und `nl2br(wp_kses_post())` gesichert.
* **Updatesicherheit:** Bei Updates via ZIP-Upload bleiben alle Feed-Einstellungen, CPT-Einträge und gecachten Termine in der Datenbank zu 100 % erhalten.

---

## 📂 Architektur

```text
bs-wp-ics-feed-reader/
├── bs-wp-ics-feed-reader.php         # Plugin-Bootstrap, Lifecycle, Autoloading & WP-Cron
├── uninstall.php                     # Vollständige Bereinigung bei Plugin-Löschung
├── README.md                         # Diese Dokumentation
├── BS-PluginDesignSystem.md          # Designsystem für BS-Plugin-Backends
├── SPEC.md                           # Technische Spezifikation
├── ROADMAP.md                        # Projektplan & Phasen
├── PROMPTS.md                        # Entwicklungs-Prompts
├── languages/
│   └── bs-wp-ics-feed-reader.pot     # GNU gettext Übersetzungsvorlage
├── includes/
│   ├── class-bs-ics-cpt.php          # CPT 'bs_ics_feed' & Spaltenverwaltung
│   ├── class-bs-ics-parser.php       # RFC 5545 Parser (Zeitzonen, Unfolding, ReDoS-Schutz)
│   ├── class-bs-ics-admin.php        # Tabbed Meta-Boxes, Pill-Switches, Dreier-Schema, AJAX-Sync
│   ├── class-bs-ics-renderer.php     # Shortcode-Renderer, Schema.org JSON-LD, Filter, Export
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

## 📝 Changelog

### Version 1.1.0
* **Neu:** Nativer Gutenberg-Block (`bs-wp-ics/calendar`) mit echter Server-Side Live-Vorschau und InspectorControls.
* **Neu:** Erweiterte Kachel-Design-Presets (*Klassisch*, *Minimal / Flat*, *Accent Header*).
* **Neu:** Theme-Farbvererbung (Text- und Linkfarben passen sich dynamisch dem aktiven Theme an).
* **Neu:** Konfigurierbare Schatten-Stärken, Innenabstände (Padding), Rahmenbreite und Rahmenfarben.
* **Neu:** Schema.org Event JSON-LD Structured Data (Google Rich Snippets) direkt im Shortcode-Body.
* **Neu:** Barrierefreiheit nach WCAG 2.1 AA (`.bs-ics-sr-only`, WAI-ARIA Attribute).
* **Neu:** „In Kalender eintragen“-Exportfunktion (Google Calendar, Outlook Web, Apple/iCal-Download).
* **Neu:** Clientseitige Echtzeit-Suche und Kategorie-Filterleiste.
* **Neu:** Automatischer Hintergrund-Sync via `WP-Cron`.
* **Neu:** Modernes Admin-Interface nach `BS-PluginDesignSystem.md` mit Pill-Switches und Dreier-Schema.

### Version 1.0.0
* Erstveröffentlichung mit CPT-Verwaltung, RFC 5545 Parser, AJAX-Sync, Shortcode-Renderer und responsivem Grid/List-Layout.

---

## 📜 Lizenz

Lizenziert unter der [GNU General Public License v2 oder höher](https://www.gnu.org/licenses/gpl-2.0.html).
