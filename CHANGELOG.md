# Changelog

All notable changes to **Club Events Manager** are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.6.0] — 2026-09-26

Makes sure the plugin cannot break a website.

### Added
- **Error containment (`CE_Safe`).** Every shortcode, block render, Elementor
  hook and theme/template hook (`pre_get_posts`, `template_include`,
  `body_class`, the Astra filters, `wp_head`, `init` handlers, publish
  notifications) runs inside a guard. A failure discards its partial output,
  returns the unfiltered value or an empty string (a short note for editors),
  and is reported via `wp_trigger_error()` under WP_DEBUG and the
  `ce_render_error` action. Verified by injecting failures into every renderer
  in a sandbox: each page still loaded completely.
- **Safe loading.** Instead of fataling, the plugin shows an admin notice and
  stays inactive when a second copy is active, when another plugin already
  defines one of its classes (checked for all 25), or on WordPress < 6.5 /
  PHP < 7.4. Start-up errors are caught the same way. `ce_plugin()` is
  declared conditionally.
- **Schema self-repair.** `ce_db_version` triggers `create_tables()` once per
  version, because updates do not run the activation hook; this also creates
  the subscriber table on sites where 1.4.0 and earlier never did.
- `club-events/tests/robustness.php` (22 checks, run in CI).

### Changed
- The Astra header `z-index` rule (previously `!important` on every page)
  only applies on single event pages; `.required` is scoped to the plugin's
  forms.
- Front-end initialisation is isolated per component (`safely()`).
- Uninstall removes all 14 plugin options (it left six behind).

## [1.5.0] — 2026-09-26

Prepares the plugin for the WordPress.org plugin directory. Visitors on a
German-language site see no difference.

### Changed
- **Renamed to WP Club Events Simple**, author Outthinkx Club. Display name
  only: the `club-events` slug, text domain, shortcodes, blocks and options are
  unchanged, so existing sites update in place.
- **German via translation files.** A `gettext` filter forced 15 strings to
  German on every site, whatever its language. They now ship as catalogs in
  `club-events/languages/` (de_DE, de_DE_formal, de_CH, de_CH_informal, de_AT;
  built by `tools/i18n/build-de.py`), and the tile button default is the
  translatable "Read more" instead of a hard-coded "Weiterlesen".
- **Club-specific import moved out.** The Aktivriege 2026 import (and its e2e
  test) is now the separate add-on `addons/club-events-stv-malters`, released
  as `club-events-stv-malters.zip` next to the plugin.
- **No inline scripts.** The subscribe form, event submission, "My events"
  delete and the archive view switcher were printed as inline `<script>`
  tags; they now live in the enqueued `club-events-public.js` as delegated
  handlers (strings via `wp_localize_script`, nonce via a data attribute).
- `date()` → `gmdate()` (identical under WordPress, which runs PHP in UTC);
  renderer output that is escaped internally is annotated for PHPCS.
- Plugin header: author, GPL-2.0-or-later, Plugin URI; readme gains
  Installation, FAQ, **External services** (Google Calendar API, share links)
  and Upgrade Notice sections.

### Fixed
- **Backslashes in saved text.** Request data was sanitised but never
  unslashed, so a quote in the sender name, a calendar name or a submitted
  event gained a backslash (`Kid\'s Club`). All 21 inputs now use
  `wp_unslash()` before sanitising.
- **Schema upgrades never applied.** `CREATE TABLE IF NOT EXISTS` made
  `dbDelta()` read the table name as "IF"; the sync-status columns are now part
  of the table definition.

## [1.4.0] — 2026-09-25

Makes the plugin a first-class citizen of Astra, Spectra / Gutenberg and
Elementor. No shortcode, attribute or option changed; existing pages keep
rendering, now in the theme's colours.

### Compatibility
- Requires WordPress **6.5** or later; tested up to **7.1**. Block API v3 and
  the iframed editor need 6.3+, and 6.5 is the supported floor.
- Editor controls opt into the WordPress 7.0 control styles
  (`__nextHasNoMarginBottom`, `__next40pxDefaultSize`). WordPress 6.7-6.9
  logged a deprecation warning for every text, select, range and toggle
  control in the block inspector; 7.0 removed the old styles.

### Fixed
- **The Astra bridge never took effect.** It was printed in `wp_head` before
  the plugin stylesheet, so the stylesheet's `:root` defaults won the cascade:
  events stayed plugin-blue and only a few button rules followed Astra. The
  bridge is now inline CSS on the `club-events` stylesheet (printed after it),
  which also carries it into the block-editor iframe and the Elementor preview.
- **Wrong Astra palette slots.** Secondary text mapped to
  `--ast-global-color-5` (Astra's secondary *background*, white) and subtle
  backgrounds to `--ast-global-color-7` (near-black in the default palette).
  Slots now follow Astra's meaning (0 brand, 1 alternate brand, 2 headings,
  3 text, 4 primary background, 5 surfaces); muted text and borders are mixed
  from text and surface, so dark palettes stay legible.
- **Astra typography and buttons.** The bridge referenced CSS variables Astra
  does not define (`--ast-button-border-radius`, `--ast-heading-font-family`,
  …), so the hard-coded fallbacks always won and headings were forced to the
  body font. Button colours, radius (Astra 4 four-corner and legacy), padding,
  font size, weight, transform and letter spacing, plus heading font, weight
  and H1–H4 sizes, are now read from the Customizer (`ce_astra_tokens` filter).
- **Aligned blocks broke the editor preview.** Alignment was declared only in
  JavaScript, so choosing Wide/Full made the server-side-render request fail
  with "Invalid parameter(s): attributes", and the front end ignored it.
- **Elementor editor preview.** Widgets re-rendered after a setting change
  were never initialised: the Events Hub did not respond and timeline items
  stayed at opacity 0. The front-end script now initialises idempotently and
  hooks `frontend/element_ready/global` (exposed as `ClubEvents.init()`).
- Single events no longer override a sidebar chosen in the Astra meta box or
  Customizer (the full-bleed hero applies only to the full-width layout);
  Astra breadcrumbs now cover category, type and tag archives.

### Added
- **Blocks on API v3** with server-side supports: wide/full alignment, anchor,
  margin, padding, text and background colour — usable from Gutenberg and
  Spectra alike. Blocks render inside a wrapper that applies them, and stay
  inside flex parents such as Spectra containers.
- **Accent colour** on every block, chosen from the theme palette. Palette
  values such as `var(--ast-global-color-0)` are kept, so the block follows
  later palette changes.
- **Term dropdowns** for category and event type, in the block inspector and
  the Elementor panel (previously free-text slugs).
- **Block patterns** ("Club Events" category): Events page, Upcoming events
  teaser, Calendar with subscribe form — core blocks only.
- **Elementor Style tab** on all 11 widgets: accent, title, text, secondary
  text, card background, subtle background and border colours; card and
  button corner radius; title, text and button typography — with Elementor
  global colours and fonts. Widgets declare their style/script dependencies.
- `club-events/tests/theme-integration.php` (run in CI): Astra token parsing,
  the colour sanitiser, the block wrapper, and static checks for API v3,
  iframe-ready assets and the Elementor re-init hook.

## [1.3.0] — 2026-08-11

Completes the editor surface: every shortcode is now also a Gutenberg block
**and** an Elementor widget. No markup, attribute, or option changed, so
existing pages render exactly as before.

### Added
- **Events Hub block & Elementor widget** (`[club_events]`). The hub block was
  registered server-side in 1.2.0 but never registered in the editor script, so
  it never appeared in the inserter; it now does, with controls for the enabled
  views, the default view, columns, search, filter bar, and the Subscribe (ICS)
  button.
- **Event Tiles block & Elementor widget** (`[club_events_tiles]`) — same fix,
  plus controls for excerpt, location, time, type badges, share, ICS, and the
  call-to-action label.
- **Event Share Actions block & Elementor widget** (`[club_events_share]`) —
  previously shortcode-only.
- **Timeline layout control** in both editors, exposing the
  `layout="center"` alternating timeline added in 1.2.0.
- `club-events/tests/widgets-parity.php` — a static test that fails if the
  shortcode, block, and Elementor surfaces drift apart again (a block missing
  on either side, an attribute mismatch, a widget passing an attribute the
  shortcode does not accept, or an inconsistent version number).

### Fixed
- Block previews in the editor: the block script never declared
  `wp-server-side-render`, so on sites where nothing else enqueued it every
  block silently fell back to a static placeholder instead of a live preview.
- Elementor switchers that are off now send an explicit `0` for every `show_*`
  attribute. Previously only `show_past`, `show_filter`, and `show_image` were
  translated, so any other toggle turned off would have fallen back to the
  shortcode default (on).
- Elementor text controls (category, event type, CTA label) no longer run
  through `esc_attr()` before being spliced into a shortcode string, which
  double-encoded ampersands in rendered output. Characters that would break
  shortcode parsing are stripped instead.

## [1.2.0] — 2026-07-05

### Added
- **`[club_events]` hub** — a complete "Anlässe" page in one shortcode/block:
  a search box, a view switcher (tiles / list / timeline / calendar), a
  Subscribe (ICS) button, and a category/type filter bar. Views are rendered
  once and switched client-side; search and filter apply across views; the
  chosen view is remembered.
- **Central timeline** — `[club_events_timeline layout="center"]` renders a
  central line with event cards alternating left and right (collapses to a
  single rail on mobile). Also available as the hub's timeline view.
- **Taxonomy management in the backend** — Categories, Event Types, and Tags
  now have their own submenus under *Club Events*, so terms can be added,
  renamed, and deleted freely (e.g. use Categories as Riegen: Jugend,
  Aktivriege, Männerriege, Frauenriege, Gesamtverein, Extern). Events can be
  assigned to multiple categories and multiple event types.
- **Configurable Events page** (Settings → Events Page & Display): pick an
  existing page (e.g. an "Anlässe" page that already lists all events) as the
  events index. Event back-links and breadcrumbs point there instead of the
  built-in `/events` archive.
- **Hide built-in events archive** option — redirects `/events` to the chosen
  Events page so there is a single events index.

### Changed
- **Restyled the monthly calendar** (`[club_events_overview]`): clean rounded
  day cells, a clear "today" highlight, pill-style event chips, and it now
  honours the site's start-of-week with localized weekday names (Monday-first
  for de-CH).
- All new components inherit Astra (Pro) design tokens via the `--ce-*` bridge.

## [1.1.0] — 2026-06-29

### Added
- **Configurable event types** with full CRUD UI and per-type colour. When a
  colour isn't set, the type falls back to the Astra theme primary
  (`var(--ce-primary)`) so every type always renders with a colour. A
  "Use theme color (Astra)" toggle stores an empty colour to inherit the theme.
- **Multiple calendars**, each able to sync multiple event types.
- **Comprehensive Astra theme bridge** — maps Astra design tokens
  (colours, typography, buttons, inputs, spacing) onto the plugin's components.
- **Dashboard overview** with switchable **tiles / table / timeline** views
  (preference persisted), plus an event-types stat tile.
- **`[club_events_tiles]`** shortcode — blog-card style homepage previews,
  filterable by one or more event types/categories. Options: `columns`,
  `limit`, `show_image`, `show_excerpt`, `show_time`, `show_location`,
  `show_types`, `show_share`, `show_ics`, `cta`. Works with no images for a
  sleek text-only card (colored top accent).
- **Sharing** — native Web Share API with a WhatsApp / Facebook / Email / Copy
  popover fallback, available inline on overview listings (tiles, cards,
  timeline) and on event pages.
- **Inline ICS download** alongside share on overview listings and event pages.
- **`[club_events_share]`** shortcode for standalone share/ICS actions.

### Changed
- Consolidated Google Calendar configuration onto the Calendars admin page
  (removed the duplicate Settings section).
- Improved admin styling across dashboard, calendars, and settings.
- Sleek mobile-optimized presentation of upcoming events.

### Fixed
- Invalid nested-anchor markup in tile/card wrappers that could break the card
  layout — wrappers are now `<div>` with a stretched title-overlay link, so
  action buttons are independently clickable.

## [1.0.0]

### Added
- Initial release: Google Calendar sync, timeline & overview views, blog
  embeds, ICS export, and email subscriptions.

[1.1.0]: https://github.com/lucadesimoni/wp_club_events/releases/tag/v1.1.0
[1.0.0]: https://github.com/lucadesimoni/wp_club_events/releases/tag/v1.0.0
