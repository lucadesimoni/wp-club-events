=== WP Club Events Simple ===
Contributors: lucadesimoni
Tags: events, calendar, google calendar, ics, gutenberg
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Event calendar for clubs and associations: Google Calendar sync, timeline, calendar and tile views, ICS export, sharing and email subscriptions.

== Description ==

WP Club Events Simple helps clubs and associations publish their events with a
polished front end that adapts to the active theme.

* Create events in WordPress, or sync them from one or more Google Calendars.
* Show events as tiles, cards, a vertical timeline, a monthly calendar, a
  yearly agenda, compact lists, or an all-in-one events hub with search.
* One-click "Add to calendar" (.ics) per event, plus a subscribable ICS feed.
* Share buttons: the device's native share sheet, with WhatsApp, Facebook,
  email and copy-link as fallback.
* Email subscriptions for new events, and an optional front-end form where
  logged-in members submit events for review.
* Every view is available as a block (Gutenberg, Spectra), an Elementor widget
  and a shortcode, with alignment, spacing and colour controls.
* Follows the Astra theme's palette, buttons and typography automatically;
  works with any other theme using its own defaults.

== Installation ==

1. Upload the plugin through *Plugins > Add New > Upload Plugin*, or install it
   from the plugin directory, and activate it.
2. Add events under *Club Events > Add Event*, or connect a Google Calendar
   under *Club Events > Google Calendars*.
3. Insert one of the *Club Events* blocks (or Elementor widgets) on a page, or
   use a shortcode listed below.

== Frequently Asked Questions ==

= Do I need a Google account? =

No. Google Calendar sync is optional; events created in WordPress work
without it.

= Which themes are supported? =

Any theme. With Astra the plugin also picks up the global colour palette,
button style and heading font from the Customizer.

= Is the plugin translated? =

It is translation-ready and ships German (Germany, Switzerland, Austria).

== External services ==

This plugin connects to the **Google Calendar API** (Google LLC), only if you
connect a Google Calendar under *Club Events > Google Calendars*.

* What is sent: the calendar ID and the API key you entered, plus the date range
  to fetch. No visitor data is sent.
* When: on the scheduled sync (hourly by default, configurable) and when you
  press "Sync now".
* Terms of service: https://developers.google.com/terms
* Privacy policy: https://policies.google.com/privacy

The share buttons link to WhatsApp (https://wa.me), Facebook
(https://www.facebook.com/sharer/sharer.php) and the visitor's email app.
Nothing is sent to these services unless a visitor clicks the button, and then
only the event's title and URL.

* WhatsApp terms and privacy: https://www.whatsapp.com/legal
* Facebook terms: https://www.facebook.com/terms.php, privacy:
  https://www.facebook.com/privacy/policy

== Shortcodes ==

* `[club_events]` — the full events hub: search, view switcher (tiles / list
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

= 1.6.0 =
* Robustness: nothing the plugin renders or hooks into can take a page down.
  Every shortcode, block, Elementor widget and theme/template hook is guarded;
  if one fails, visitors see the rest of the page, editors see a short note,
  and the error is logged (with WP_DEBUG).
* The plugin refuses to load, with an admin notice instead of a fatal error,
  when a second copy is active, when another plugin uses one of its class
  names, or when WordPress/PHP are too old.
* The database schema is repaired automatically after an update (updates do
  not re-run activation); sites where the subscriber table was never created
  are fixed on the next page view.
* CSS no longer touches anything outside the plugin: the Astra header z-index
  rule now only applies on event pages, and the required-field asterisk style
  is scoped to the plugin's forms.
* Front-end scripts set up each component separately, so one failure cannot
  stop the others or other scripts on the page.
* Uninstall removes all plugin options.

= 1.5.0 =
* Renamed to WP Club Events Simple (the plugin slug, shortcodes and blocks
  are unchanged, so updating keeps everything working). Author: Outthinkx Club.
* Prepared for the WordPress.org plugin directory. No change for visitors on a
  German-language site.
* German now comes from bundled translation files (de_DE, de_CH, de_AT and
  their formal/informal variants) instead of being forced on every site; the
  default tile button label is the translatable "Read more".
* The Aktivriege 2026 import moved to the separate "Club Events – STV Malters"
  add-on.
* Front-end scripts for the subscribe form, event submission, "My events" and
  the archive view switcher moved from inline <script> tags into the enqueued
  script.
* Fix: text entered in the settings and the calendar editor gained
  backslashes before quotes (request data was never unslashed).
* Fix: the database schema could not be upgraded (dbDelta could not parse
  CREATE TABLE IF NOT EXISTS).
* Readme: installation, FAQ and the External services disclosure.

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

== Upgrade Notice ==

= 1.5.0 =
The Aktivriege 2026 import tool is now a separate add-on. Install "Club Events – STV Malters" if you still need it.
