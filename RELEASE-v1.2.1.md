# Release handoff — v1.2.1

The code is committed and pushed to `claude/repo-check-release-dtts1z`
(commit `eae4b12`). Tag creation and the GitHub Release could not be
completed from this session: the git credential is scoped to that one
branch, so `git push origin v1.2.1` returns **403**, and the GitHub API is
blocked at the proxy ("GitHub access is not enabled for this session").
The steps below finish the release from a machine with normal access.

## 1. Merge into the default branch

The default branch is `claude/wordpress-club-events-plugin-elqzxy`, whose
tip (`9493238`) is the direct parent of `eae4b12` — so this fast-forwards.

```bash
git fetch origin
git checkout claude/wordpress-club-events-plugin-elqzxy
git merge --ff-only origin/claude/repo-check-release-dtts1z
git push origin claude/wordpress-club-events-plugin-elqzxy
```

## 2. Create the tags

`v1.2.1` is the new release. The three earlier tags are backfilled so the
`CHANGELOG.md` links resolve — the repository currently has no tags at all.

```bash
git tag -a v1.0.0 cdc36e5 -m "Club Events Manager 1.0.0"
git tag -a v1.1.0 1d37656 -m "Club Events Manager 1.1.0"
git tag -a v1.2.0 9493238 -m "Club Events Manager 1.2.0"
git tag -a v1.2.1 eae4b12 -m "Club Events Manager 1.2.1"
git push origin v1.0.0 v1.1.0 v1.2.0 v1.2.1
```

Tag targets, for reference:

| Tag | Commit | Why that commit |
|---|---|---|
| `v1.0.0` | `cdc36e5` | "Add Club Events Manager WordPress plugin (v1.0.0)" — the initial release |
| `v1.1.0` | `1d37656` | Last commit at version 1.1.0, before 1.2.0 work began |
| `v1.2.0` | `9493238` | Merge that shipped 1.2.0; version file reads 1.2.0 |
| `v1.2.1` | `eae4b12` | This release |

## 3. Create the GitHub Release

Cut it from `v1.2.1`, title **Club Events Manager 1.2.1**, and attach
`club-events.zip` — `README.md` tells users to install by downloading that
file from the Releases page, so a release without the asset breaks the
documented install path. The zip is built with:

```bash
zip -r club-events.zip club-events -x "club-events/tests/*"
```

Release notes:

---

Maintenance release — no new features, five fixes for bugs that broke
documented behaviour. **Recommended for all installs.**

### Fixed

- **The subscribers table was never created.** The activation schema used
  `CREATE TABLE IF NOT EXISTS`, but `dbDelta()` derives a table's name by
  matching `CREATE TABLE ([^ ]*)` — it read both statements as a table
  called `IF`, so the second definition overwrote the first and only
  `{prefix}ce_calendars` was created. Email subscriptions failed on every
  install since 1.0.0.
- **Schema fixes now reach existing installs.** WordPress only fires the
  activation hook on activation, so a plugin *update* never ran the schema
  code. A version check on `admin_init` re-runs table creation, migrations
  and option defaults when the stored `ce_db_version` differs — which is
  what repairs sites that installed 1.0.0–1.2.0.
- **Boolean shortcode attributes were ignored.** Shortcode attributes
  always arrive as strings, so `show_image="false"` was the *truthy* string
  `"false"`. All documented boolean attributes are now normalised. This
  also removes the duplicate filter bar in the `[club_events]` hub's
  calendar view.
- **Combining a category with an event type dropped the type filter.**
  `CE_CPT::get_events()` built its `tax_query`/`meta_query` into the
  defaults array, so a caller-supplied `tax_query` replaced them wholesale
  via `wp_parse_args()`. Clauses are now merged.
- **Blank card/tile placeholders** for events with no explicit colour — the
  gradient produced the invalid stop `var(--ce-primary)aa`, and browsers
  dropped the whole declaration.

### Changed

- `uninstall.php` removes the options added in 1.1.0/1.2.0.
- Repository links corrected to `wp-club-events`.

### Added

- `tests/regressions.php` — 15 checks covering the fixes above.

**Upgrading:** activate as usual; the schema repair runs on the first
admin page load after the update.

---

## Verification performed

- `php -l` clean across all 29 PHP files (PHP 8.4).
- `node --check` clean on all three JS files.
- Existing E2E suite: 41/41 passed (`php club-events/tests/e2e-aktivriege.php`).
- New regression suite: 15/15 passed (`php club-events/tests/regressions.php`).
- Release zip re-extracted and re-linted; contains 36 files, no dev files.
