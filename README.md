# Club Events Manager

Modern event management for clubs — sync multiple Google Calendars, show events
as tiles, timelines, cards, or a monthly calendar, export ICS feeds, let members
subscribe by email, and drop a full events hub on any page. Built to adapt
automatically to the **Astra** theme (including Astra Pro).

> Originally built for [STV Malters](https://mataxacu.myhostpoint.ch) (Aktivriege,
> Jugend, Männerriege, Frauenriege), but works for any club.

---

## Features

- **Events hub** — the `[club_events]` shortcode/block renders a complete
  "Anlässe" page: a search box, a view switcher (tiles / list / timeline /
  calendar), a Subscribe (ICS) button, and a category/type filter bar. Views
  switch client-side and the chosen view is remembered.
- **Configurable event types & categories** with per-type colours (falling back
  to the Astra theme colour). Manage them from the backend; assign several to
  one event.
- **Multiple Google Calendars**, each syncing one or more event types, on a
  schedule.
- **Many display options** — tiles (blog-card style, with or without images),
  a vertical or **central/alternating** timeline, a responsive card grid, a
  restyled monthly calendar, a yearly agenda, and compact lists.
- **Sharing & ICS** — native Web Share (WhatsApp / Facebook / Email / Copy) and
  one-click "Add to calendar" inline on listings and event pages.
- **Email subscriptions** and a **frontend submission** form.
- **Configurable Events page** — point event links at an existing page (e.g.
  your "Anlässe" page) and optionally hide the built-in `/events` archive.
- **Astra (Pro) design-token bridge** — colours, typography, buttons, and inputs
  inherit your theme automatically. Also plays nicely with Elementor, Spectra,
  and the block editor.
- Mobile-optimized throughout.

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

1. Download the latest `club-events.zip` from the
   [Releases](https://github.com/lucadesimoni/wp_club_events/releases) page.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, install,
   and activate.
3. Open **Club Events** in the admin menu to add events, event types, and
   calendars.

## Quick start

Create a page (e.g. *Anlässe*) and add the hub:

```
[club_events]
```

Then, under **Club Events → Settings → Events Page & Display**, select that page
as the Events Page and (optionally) tick *Hide the built-in events archive* so
there is a single events index.

## Shortcodes

| Shortcode | What it renders |
|-----------|-----------------|
| `[club_events]` | Full hub: search + view switch (tiles/list/timeline/calendar) + Subscribe (ICS) |
| `[club_events_tiles]` | Blog-card style previews of the next events |
| `[club_events_timeline]` | Vertical timeline grouped by month (`layout="center"` for an alternating central line) |
| `[club_events_overview]` | Monthly calendar grid + list |
| `[club_events_cards]` | Responsive card grid |
| `[club_events_yearly]` | Full-year agenda by month |
| `[club_events_list]` | Compact list for sidebars |
| `[club_events_share]` | Share + ICS actions for the current event |
| `[club_events_subscribe]` | Email subscription form |
| `[club_events_submit]` | Frontend event submission |
| `[club_events_my_events]` | A user's submitted events |

Common attributes: `event_type`, `category` (comma-separated slugs), `limit`,
`columns`, `views`, `default`, `show_search`, `show_filter`, `show_subscribe`,
`show_image`, `show_time`, `show_location`, `show_types`.

Each shortcode is also available as a Gutenberg block under the *Club Events*
category.

## Development

```
club-events/
├─ club-events.php         # plugin bootstrap + version
├─ includes/               # CPT, taxonomies, shortcodes, REST, ICS, Google sync, Astra bridge
├─ admin/                  # admin pages and views
├─ public/css|js/          # frontend + admin assets
├─ templates/              # single + archive templates
└─ tests/                  # standalone PHP e2e harness
```

Run the end-to-end test suite (no WordPress required):

```bash
php club-events/tests/e2e-aktivriege.php
```

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

GPL-2.0-or-later.
