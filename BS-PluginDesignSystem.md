# BS-PluginDesignSystem.md

**Universelles Designsystem für BS-WordPress-Plugin-Backends**

Gilt für alle Plugins mit dem Präfix `bs*` (BS Overhead Toggles, BS Kudo Karten, Custom Puppy Application Form u. a.). Ziel: Jedes Plugin-Backend ist sofort als "aus derselben Werkstatt" erkennbar, ohne dass WordPress-Core-Optik imitiert oder ein Fremdkörper im wp-admin entsteht. Kein Build-Step — reines CSS mit Custom Properties, keine Präprozessoren, keine Frameworks.

---

## 1. Designhaltung

Zielgruppe ist immer die Person, die im Backend Entscheidungen trifft, nicht die Person, die das Plugin gebaut hat — ruhig, technisch-präzise, ohne Spielerei. Die Ästhetik lehnt sich an systemisches, klares Denken an (Struktur sichtbar machen statt verstecken), nicht an verspielte SaaS-Dashboards. Zurückhaltende Farbigkeit, ein einziger klarer Akzent, viel Weißraum, keine dekorativen Verläufe oder Schatten-Spielereien.

Bewusst vermieden: warmes Creme + Terracotta-Akzent, Beinahe-Schwarz + Neon-Akzent, Zeitungs-Layout mit Haarlinien — das sind aktuell die Standard-Ausweichrouten generischer KI-generierter Interfaces. Stattdessen: ein kühler, technischer Grundton mit einem einzigen gesättigten Akzent, der Handlungsfähigkeit markiert (aktive Toggles, primäre Aktionen).

**Signatur-Element:** Der Erläuterungs-Block im Dreier-Schema (Was macht's / Nutzen / Bricht es was) ist über alle BS-Plugins hinweg identisch aufgebaut — das wird zum wiedererkennbaren Merkmal, nicht ein Logo oder Icon-Stil.

---

## 2. Farb-Tokens (OKLCH)

Alle Farben als CSS Custom Properties definiert, damit einzelne Plugins bei Bedarf punktuell überschreiben können, ohne das System zu duplizieren.

```css
:root {
  /* Neutral-Skala — kühles Grau-Blau, kein reines Grau */
  --bs-neutral-0:   oklch(99% 0.002 250);   /* Seitenhintergrund */
  --bs-neutral-50:  oklch(97% 0.004 250);   /* Karten-/Panel-Hintergrund */
  --bs-neutral-100: oklch(93% 0.006 250);   /* Trennlinien, Rahmen */
  --bs-neutral-300: oklch(80% 0.010 250);   /* deaktivierte Elemente */
  --bs-neutral-500: oklch(58% 0.014 250);   /* sekundärer Text */
  --bs-neutral-700: oklch(38% 0.016 250);   /* primärer Text */
  --bs-neutral-900: oklch(22% 0.018 250);   /* Überschriften, hoher Kontrast */

  /* Akzent — gedecktes Petrol/Teal, bewusst kein WordPress-Blau, kein Terracotta */
  --bs-accent-300: oklch(82% 0.07  200);
  --bs-accent-500: oklch(58% 0.11  200);    /* primäre Aktion, aktiver Toggle */
  --bs-accent-700: oklch(40% 0.10  200);    /* Hover/Active-State */

  /* Semantische Farben */
  --bs-success-500: oklch(62% 0.13 152);    /* Toggle aktiv, positive Bestätigung */
  --bs-warning-500: oklch(74% 0.15  75);    /* "Bricht es was"-Hinweis */
  --bs-warning-100: oklch(96% 0.03  75);    /* Hintergrund Warnkasten */
  --bs-danger-500:  oklch(56% 0.19  25);    /* destruktive Aktion, kritischer Hinweis */
  --bs-info-500:    oklch(60% 0.09 235);    /* neutraler Hinweis, "experimentell"-Badge */

  /* Elevation — bewusst sehr flach, kein weicher Drop-Shadow-Stil */
  --bs-border: 1px solid var(--bs-neutral-100);
  --bs-radius-sm: 4px;
  --bs-radius-md: 8px;
}
```

**Kontrastregel:** Text auf `--bs-neutral-50`/`--bs-neutral-0` immer mindestens `--bs-neutral-700`, niemals `--bs-neutral-500` als Fließtext — nur für sekundäre Metainformation (Zeitstempel, Modul-IDs im „Für Entwickler"-Detail).

---

## 3. Typografie

System-Font-Stack, keine Webfont-Ladezeit im wp-admin — passt zur Vanilla-/Kein-Build-Step-Linie und vermeidet zusätzlichen Overhead in genau dem Plugin, das Overhead reduzieren soll.

```css
:root {
  --bs-font-ui: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  --bs-font-mono: ui-monospace, "SF Mono", "Cascadia Code", Consolas, monospace; /* für Hook-/Options-Namen im Entwickler-Detail */

  --bs-text-xs:   0.75rem;   /* Metainfo, Badges */
  --bs-text-sm:   0.875rem;  /* Erläuterungstext, sekundäre Labels */
  --bs-text-base: 1rem;      /* Standard-Fließtext, Toggle-Label */
  --bs-text-lg:   1.125rem;  /* Kategorie-Überschriften */
  --bs-text-xl:   1.375rem;  /* Seitentitel */

  --bs-weight-regular: 400;
  --bs-weight-medium:  500;  /* Toggle-Label, Kategorie-Titel */
  --bs-weight-semibold: 600; /* Seitentitel, Warnhinweis-Label */

  --bs-leading-tight:  1.25; /* Überschriften */
  --bs-leading-normal: 1.5;  /* Fließtext */
}
```

Regel: Monospace ausschließlich für technische Bezeichner (`bsot_gutenberg`, Hook-Namen) im eingeklappten Entwickler-Detail — nie im Standard-Erläuterungstext, damit dieser für Nicht-Entwickler lesbar bleibt.

---

## 4. Abstände & Raster

```css
:root {
  --bs-space-1: 4px;
  --bs-space-2: 8px;
  --bs-space-3: 12px;
  --bs-space-4: 16px;
  --bs-space-6: 24px;
  --bs-space-8: 32px;
  --bs-space-12: 48px;
}
```

Karten/Panels: `--bs-space-6` Innenabstand. Abstand zwischen Toggle-Zeilen innerhalb einer Kategorie: `--bs-space-3`. Abstand zwischen Kategorien: `--bs-space-12` — deutlich größer, damit Gruppierung ohne Trennlinien allein durch Weißraum erkennbar ist.

---

## 5. Komponenten-Blueprints

### 5.1 Toggle-Zeile

Struktur pro Zeile: `[Toggle-Switch] [Label + Kurzbeschreibung] [Status-Badge] [„mehr erfahren"-Trigger]`

- Toggle-Switch: kein natives Checkbox-Kästchen — ein Pill-Switch (`--bs-accent-500` wenn aktiv, `--bs-neutral-300` wenn inaktiv), da schneller visuell scanbar über eine lange Liste hinweg als Häkchen.
- Label: `--bs-text-base`, `--bs-weight-medium`, `--bs-neutral-900`
- Kurzbeschreibung darunter: `--bs-text-sm`, `--bs-neutral-500`, max. eine Zeile — Details stehen im aufklappbaren Erläuterungs-Block (5.2), nicht hier.
- „Experimentell"-Badge (falls zutreffend): kleines Pill in `--bs-info-500`-Ton, `--bs-text-xs`, direkt neben dem Label — nicht erst im aufgeklappten Detail, damit Vorsicht sofort sichtbar ist.

### 5.2 Erläuterungs-Block (Dreier-Schema) — das Signatur-Element

Erscheint beim Aufklappen einer Toggle-Zeile, immer in derselben Reihenfolge und Optik über alle BS-Plugins hinweg:

```
┌─────────────────────────────────────────┐
│ Was macht's                              │  ← --bs-text-sm, --bs-neutral-700
│ [Alltagssprachliche Beschreibung]        │
│                                           │
│ Nutzen                                   │  ← --bs-text-sm, --bs-weight-medium
│ [Warum abschalten sinnvoll ist]          │
│                                           │
│ ⚠ Bricht es was                          │  ← nur wenn zutreffend, eigener Kasten
│ [Warnhinweis]                            │     Hintergrund --bs-warning-100
└─────────────────────────────────────────┘     Text --bs-neutral-900, Icon --bs-warning-500
```

Der Warnkasten hat **immer** dieselbe Position (letztes Element) und Optik — kein Fließtext-Einschub an variabler Stelle, damit man sich beim schnellen Scannen mehrerer Toggles auf die gleiche Blickposition verlassen kann.

Optionales „Für Entwickler"-Detail (eingeklappt, `--bs-font-mono`, `--bs-text-xs`): Hook-/Filter-Namen, interne Options-Keys.

### 5.3 Kategorie-Gruppierung

Karten mit `--bs-neutral-50`-Hintergrund, `--bs-border`, `--bs-radius-md`. Kategorie-Titel `--bs-text-lg`/`--bs-weight-medium`/`--bs-neutral-900`, mit kurzer Ein-Satz-Erklärung darunter in `--bs-neutral-500`. Gruppierung nach Nutzen-Kategorie (z. B. „Performance", „Sicherheit/Privacy", „Aufräumen im Code"), nicht nach WordPress-Subsystem — siehe Projektplan Abschnitt 7a.

### 5.4 Quick-Setup / Preset-Aktion

Primärer Button oben auf der Seite, deutlich abgesetzt vom Rest: `--bs-accent-500`-Hintergrund, weißer Text, `--bs-radius-sm`, `--bs-weight-semibold`. Beschriftung aktivbezogen und konkret („Standard-Konfiguration anwenden"), nicht generisch („Los geht's"). Nach Anwendung: kurze Bestätigung in `--bs-success-500`-Ton, kein Modal.

### 5.5 Sekundäre/destruktive Aktionen

Sekundäre Buttons: transparenter Hintergrund, `1px solid --bs-neutral-300`, Text `--bs-neutral-700`. Destruktive Aktionen (z. B. „Alle Toggles zurücksetzen"): Text/Rahmen in `--bs-danger-500`, erst nach Bestätigungsschritt ausführen — nie ein einzelner Klick für irreversible Änderungen.

### 5.6 Status-Erkennbarkeit ohne Nachdenken

Jede Toggle-Zeile trägt zusätzlich zum Switch ein kleines Statuswort (`Aktiv` / `Inaktiv`) in `--bs-text-xs`, `--bs-success-500` bzw. `--bs-neutral-500` — Redundanz zur reinen Switch-Farbe, damit der Zustand auch bei schnellem Überfliegen einer langen Liste oder bei Farbfehlsichtigkeit eindeutig ist.

---

## 6. Bewegung

Zurückhaltend: Toggle-Wechsel und Aufklappen des Erläuterungs-Blocks mit kurzer Transition (150–200ms, `ease-out`), keine Seiteneffekte wie Bounce oder Scale. `prefers-reduced-motion: reduce` respektieren — Transition auf `none` setzen.

---

## 7. Barrierefreiheit (Minimalstandard für alle BS-Plugins)

- Toggle-Switches als `<button role="switch" aria-checked>`, nicht als reines `<div>` mit Klick-Handler
- Sichtbarer Fokus-Ring in `--bs-accent-500` auf allen interaktiven Elementen
- Warnkasten-Icon (⚠) immer mit Text begleitet, nie allein als Bedeutungsträger
- Farbkontrast Text/Hintergrund mindestens WCAG AA (bei den obigen Neutral-/Akzent-Werten gegeben, bei künftigen Anpassungen prüfen)

---

## 8. Anwendung in neuen Plugins

Beim Start eines neuen BS-Plugin-Backends: Diese Datei einlesen, Tokens 1:1 als CSS Custom Properties übernehmen, Komponenten-Blueprints aus Abschnitt 5 als Ausgangspunkt nehmen. Plugin-spezifische Ergänzungen (z. B. zusätzliche Komponenten, die nur ein Plugin braucht) gehören in eine plugin-eigene CSS-Datei, die auf diesen Tokens aufbaut — nicht in dieses zentrale Dokument, damit es universell wiederverwendbar bleibt.
