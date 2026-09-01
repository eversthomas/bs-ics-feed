=== BS ICS Feed ===
Contributors: (add your WordPress.org username here)
Tags: calendar, ics, ical, events, gutenberg
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display external ICS/iCalendar feeds as accessible calendar cards via shortcode, Gutenberg block, or widget. Supports recurring events.

== Description ==

**BS ICS Feed** lets you pull in external calendar feeds (Google Calendar, Apple iCloud, Outlook, Nextcloud, club/community software, and any other RFC 5545-compliant ICS source) and display them as clean, accessible, fully customizable event cards on your WordPress site.

Feeds are managed as their own content type, synced with one click, cached locally for fast page loads, and displayed wherever you need them: a native Gutenberg block, a flexible shortcode, or a classic sidebar widget.

= Key Features =

* **Native Gutenberg block** with a real server-side live preview in the editor, plus full InspectorControls for layout, sorting, and design.
* **Recurring events (RRULE)**: daily, weekly, monthly, and yearly recurrence rules are automatically expanded into individual occurrences, including `BYDAY`, `BYMONTHDAY`, `BYMONTH`, `INTERVAL`, `COUNT`, `UNTIL`, and `EXDATE`. Timezone- and DST-safe.
* **Combine multiple calendars** in a single view: pass a comma-separated list of feed IDs to merge feeds, each visually distinguished with its own accent color, source badge, and dedicated quick filter.
* **Three tile design presets** (Classic, Minimal/Flat, Accent Header) with granular control over colors, shadows, padding, border radius, and optional inheritance of your active theme's colors.
* **Teaser vs. detail field control**: decide, per RFC 5545 field, whether it appears immediately in the card or only after clicking "Read more" (accordion or dedicated single-event page).
* **CSV export**: a one-click, Excel-compatible CSV download of the currently displayed events (semicolon-separated with UTF-8 BOM for correct handling of accented characters).
* **Add to Calendar**: one-click export of any event to Google Calendar, Outlook Web, or as an Apple/ICS download.
* **Client-side search and category filtering**, no page reload required.
* **Resilient local caching**: the frontend never makes a live request to the external feed. If the source is temporarily unreachable, the existing cached events are kept.
* **Automatic background sync** via WP-Cron (hourly, twice daily, or daily per feed), with a built-in status panel and setup guidance for a real server cron job.
* **Schema.org Event structured data (JSON-LD)** for Google Rich Snippets, without touching `<head>`.
* **Accessibility**: WAI-ARIA roles and states throughout (tabs, accordion, dialogs), screen-reader labels, visible focus states, and a dedicated print stylesheet.
* **Classic sidebar widget** for themes or widget areas without block-widget support.
* **No external runtime dependencies**: no jQuery on the frontend, no bundled frameworks — vanilla CSS Grid and vanilla JavaScript.

= Security =

* Feed URLs are fetched with `wp_safe_remote_get()`, which blocks requests to internal/private IP ranges at the WordPress core level.
* All state-changing actions are nonce-protected; feed management requires a dedicated capability limited to Administrators and Editors by default.
* Feed descriptions are rendered as plain text, not HTML, to avoid content injection from a feed source you don't fully control.
* The CSV export endpoint's download link is nonce-bound to the specific feed combination, preventing blind enumeration of feed IDs that aren't embedded anywhere on your site.

== Installation ==

1. Upload the `bs-ics-feed` folder to `/wp-content/plugins/`, or install the plugin ZIP directly via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to the new **ICS Feeds** menu item and add your first feed.
4. Enter the feed's ICS/iCal URL and click **Analyze & Sync Feed**.
5. Choose which fields appear in the teaser vs. the detail view, configure layout and design, then publish.
6. Display it with the shortcode `[bs_ics_calendar id="123"]`, the **ICS Calendar Feed** Gutenberg block, or the **ICS Calendar Feed** widget.

== Frequently Asked Questions ==

= Which calendar sources are supported? =

Any publicly accessible ICS/iCalendar feed that follows RFC 5545 — including Google Calendar, Apple iCloud, Outlook/Office 365, Nextcloud, and most club, community, and event management software.

= Does it fetch the feed live on every page view? =

No. Feeds are synced (manually or automatically via WP-Cron) and cached in your WordPress database. Frontend page views never trigger an external request, so a slow or temporarily unreachable calendar source never slows down or breaks your site — the last successfully cached events are kept.

= Are recurring events (like a weekly class) supported? =

Yes. Daily, weekly, monthly, and yearly `RRULE` recurrence patterns are expanded into individual event occurrences automatically, including exceptions (`EXDATE`). Very rare recurrence modifiers (`BYSETPOS`, `BYWEEKNO`, `BYYEARDAY`, `RDATE`) are not supported.

= Can I show events from more than one calendar in the same place? =

Yes. Use a comma-separated list of feed IDs, e.g. `[bs_ics_calendar id="12,34,56"]`, or add additional calendars in the Gutenberg block's sidebar. Each event is labeled with its source and colored with that feed's own accent color, and visitors can filter by source.

= Does this work without Gutenberg / in a classic theme? =

Yes. Use the shortcode or the classic sidebar widget — both work independently of the block editor.

= Is the output accessible? =

Yes. The frontend markup uses semantic HTML, WAI-ARIA roles/states, visible focus indicators, and screen-reader-only labels throughout, and includes a dedicated print stylesheet.

= Where can I get support? =

Please use the plugin's support forum on WordPress.org, or reach out via [bezugssysteme.de](https://bezugssysteme.de).

== Screenshots ==

1. Feed configuration screen with tabbed settings (source, fields, display, design).
2. Frontend calendar grid with teaser cards, search bar, and category filter.
3. Gutenberg block with live server-side preview and sidebar controls.
4. Tile design presets and color customization.

== Changelog ==

= 1.6.1 =
* Renamed the plugin from "BS WP ICS Feed Reader" to "BS ICS Feed" (name, slug, text domain, main file, translation template, block namespace) to comply with the WordPress.org trademark policy on the term "WP".
* Added GPLv2 license header and LICENSE.txt.
* Added this readme.txt in the official WordPress.org format.
* Minor code cleanup: translator comments, removed unneeded `load_plugin_textdomain()` call, removed a disallowed `suppress_filters` query argument.

= 1.6.0 =
* New: CSV export button lets visitors download the currently displayed events as an Excel-compatible CSV file.
* New: dedicated, nonce-secured `admin-ajax.php` endpoint for the export, decoupled from page rendering.
* New: per-feed toggle, shortcode attribute (`csv`), and block control for the CSV export button.

= 1.5.0 =
* New: classic WP_Widget "ICS Calendar Feed" for sidebars and widget areas without block-widget support.

= 1.4.0 =
* New: combine multiple feeds into a single view via a comma-separated list of feed IDs, with per-source color coding, badges, and a dedicated quick filter.
* New: matching multi-select control in the Gutenberg block sidebar.

= 1.3.0 =
* New: recurring event support (RRULE) — daily/weekly/monthly/yearly rules, BYDAY/BYMONTHDAY/BYMONTH, INTERVAL, COUNT, UNTIL, and EXDATE are expanded into individual, timezone- and DST-safe occurrences.
* New: "Recurring" badge on affected event cards.

= 1.2.0 =
* Security: feed descriptions are now rendered as plain text instead of HTML.
* Security: the "Add to Calendar" ICS export now escapes special characters correctly per RFC 5545.
* Security: feed management is now restricted to Administrators and Editors via a dedicated capability.
* Fix: the WP-Cron background sync now actually respects each feed's chosen interval (hourly/twice daily/daily) instead of always syncing hourly.
* Fix: events without their own UID in the source feed now get a stable, deterministic fallback ID, so single-event links survive future syncs.
* New: WP-Cron status panel in the admin screen, including setup guidance for a real server cron job.
* New: live design preview, persistent active settings tab, full ARIA tab pattern, and basic dark-mode support in the admin and frontend UI.
* Fix: settings tabs no longer overlap the sidebar meta box on narrow screens.

= 1.1.0 =
* New: native Gutenberg block with a real server-side live preview.
* New: three tile design presets, theme color inheritance, configurable shadows/padding/borders.
* New: Schema.org Event JSON-LD structured data.
* New: WCAG 2.1 AA accessibility pass.
* New: "Add to Calendar" export (Google Calendar, Outlook Web, Apple/ICS download).
* New: client-side search and category filter.
* New: automatic WP-Cron background sync.

= 1.0.0 =
* Initial release: custom post type feed management, RFC 5545 parser, AJAX sync, shortcode renderer, responsive grid/list layout.

== Upgrade Notice ==

= 1.6.1 =
Plugin renamed from "BS WP ICS Feed Reader" to "BS ICS Feed" (new slug: bs-ics-feed). If you installed a previous version, deactivate it and install this one fresh — your feed data is preserved as long as you don't delete the old plugin's data first.
