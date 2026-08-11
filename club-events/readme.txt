=== Club Events Manager ===
Contributors: lucadesimoni
Tags: events, calendar, google calendar, ics, club
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modern event management for clubs — sync multiple Google Calendars, timeline,
overview, tiles & blog embeds, ICS export, sharing, and email subscriptions.

== Description ==

Club Events Manager helps clubs publish and manage events with a polished,
theme-adaptive frontend.

* Configurable event types with per-type colours (falls back to the Astra
  theme colour when none is set).
* Connect multiple Google Calendars and sync multiple event types each.
* Display events as tiles, cards, a vertical timeline, a monthly overview, a
  yearly agenda, or compact lists.
* Sharing (native Web Share with WhatsApp / Facebook / Email / Copy fallback)
  and one-click ICS "Add to calendar" — inline on listings and event pages.
* Email subscriptions and a frontend submission form.
* Comprehensive Astra theme bridge for colours, typography, and buttons.

== Shortcodes ==

* `[club_events]` — the full Anlässe hub: search, view switcher (tiles / list
  / timeline / calendar) and Subscribe (ICS).
* `[club_events_tiles]` — blog-card style previews of the next events.
* `[club_events_timeline]` — vertical timeline grouped by month.
* `[club_events_overview]` — monthly calendar grid + list.
* `[club_events_cards]` — responsive card grid.
* `[club_events_yearly]` — full-year agenda by month.
* `[club_events_list]` — compact list for sidebars.
* `[club_events_share]` — share + ICS actions for the current event.
* `[club_events_subscribe]` — email subscription form.
* `[club_events_submit]` — frontend event submission.
* `[club_events_my_events]` — user event dashboard.

== Changelog ==

= 1.3.0 =
* Every shortcode is now also a Gutenberg block and an Elementor widget.
* New Events Hub, Event Tiles, and Event Share blocks + Elementor widgets. The
  hub and tiles blocks were registered server-side but never appeared in the
  block inserter — they do now.
* Timeline layout (default / centred alternating) is now selectable in both
  editors.
* Fixed: block previews fell back to a static placeholder because the editor
  script did not declare wp-server-side-render.
* Fixed: Elementor toggles other than "show past / filter / image" did not
  override the shortcode default when switched off.
* Fixed: double-encoded ampersands in text values passed from Elementor.
* No markup or attribute changes — existing pages render exactly as before.

= 1.2.0 =
* New [club_events] hub: search, view switch (tiles/list/timeline/calendar),
  Subscribe (ICS), and a filter bar — a full "Anlässe" page in one shortcode.
* Central timeline layout with events alternating left/right.
* Backend management submenus for Categories, Event Types, and Tags.
* Configurable Events page + option to hide the built-in /events archive.
* Restyled Monday-first monthly calendar; all components Astra-aligned.

= 1.1.0 =
* Configurable event types with colour + Astra theme-colour fallback.
* Multiple calendars, each syncing multiple event types.
* Dashboard tiles / table / timeline views.
* New [club_events_tiles] blog-card preview shortcode (incl. no-image variant).
* Sharing (native + WhatsApp/Facebook/Email/Copy) and inline ICS download on
  overview listings and event pages.
* Comprehensive Astra design-token bridge; sleek mobile presentation.
* Fixed invalid nested-anchor markup in tile/card wrappers.

= 1.0.0 =
* Initial release: Google Calendar sync, timeline & overview views, blog
  embeds, ICS export, and email subscriptions.
