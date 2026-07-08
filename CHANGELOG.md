# Changelog

All notable changes to **Club Events Manager** are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

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
