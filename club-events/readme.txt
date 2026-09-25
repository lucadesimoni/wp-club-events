=== Club Events Manager ===
Contributors: lucadesimoni
Tags: events, calendar, google calendar, ics, club
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
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

= 1.4.0 =
* Requires WordPress 6.5 or later (tested up to 7.1). Block editor controls use
  the WordPress 7.0 control styles, so 6.7-6.9 no longer log deprecation
  warnings and nothing shifts on 7.x.
* Astra: the design-token bridge now actually applies. It was printed before
  the plugin stylesheet, whose own defaults overrode it, so events kept the
  plugin's blue instead of the Astra palette. It now loads after the
  stylesheet, and in the block editor and the Elementor preview too.
* Astra: palette slots fixed. Secondary text used Astra's "secondary
  background" colour (white) and subtle backgrounds used a supporting colour
  that is near-black in the default palette. Muted text and borders are now
  mixed from the text and surface colours, so dark palettes work.
* Astra: buttons, heading font and heading sizes are read from the Astra
  Customizer (radius, padding, colours, font size, weight, transform) instead
  of CSS variables Astra never defines.
* Astra: a sidebar chosen for events in the Astra meta box or Customizer is
  respected; breadcrumbs cover category, type and tag archives.
* Gutenberg / Spectra: blocks use block API v3 (iframed editor) and support
  wide/full alignment, anchors, margin, padding, and text/background colours.
  Wide or full alignment used to break the editor preview ("Invalid
  parameter(s): attributes") and was ignored on the front end.
* Gutenberg / Spectra: new Accent colour setting on every block, picked from
  the theme palette (Astra's global colours stay linked to the palette).
* Gutenberg / Spectra: category and event type are dropdowns of your terms.
* Gutenberg / Spectra: three block patterns under "Club Events".
* Elementor: every widget has a Style tab (colours, corner radius, title,
  text and button typography, Elementor global colours and fonts).
* Elementor: category and event type are dropdowns of your terms.
* Elementor: widgets now work in the editor preview after a setting changes.
  The Events Hub did not respond and timeline items stayed invisible.

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
