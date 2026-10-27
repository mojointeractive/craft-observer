# Changelog — mojo observer for Craft CMS

The plugin's versions; craftly's own CHANGELOG says which craftly release carries which.

## 1.2.0 — 2026-10-27

### Added

- `vitals` has a `system` entry on every site: free space under storage/, pending Craft, plugin and
  content migrations, project-config drift.
- `health` reports storage, migrations, project config and the queue on every site, then the craftly
  doctor's lines where there is one.
- `craft observer/check [--ping] [--url=…]`: what observer will find on this install, for a site that
  is not on craftly (craftly's `scripts/observer-check.php` prints the same lines).
- With Blitz installed, a request carrying a form-test token is neither served from nor stored in its
  cache, whatever its query-string setting.

### Changed

- The craftly doctor's `storage`, `migrations`, `project config` and `queue` lines are no longer repeated
  through the plugin: it reports those itself. `craft craftly/project/doctor` still runs them.
- The queue's oldest pending age counts from when a job became due, its delay included.

### Fixed

- The `backup` entry no longer needs `GLOB_BRACE`, which musl builds of PHP (Alpine images) lack.

## 1.1.0 — 2026-10-26

- The `backups` action: the Backup plugin's targets, backups, last run and retention.
- Each package in `vitals` names `latestSameMajor`.

## 1.0.1 — 2026-10-23

- Form tests on Formie 4.0 (the betas' submission workflow was renamed).

## 1.0.0 — 2026-10-23

- The connector as a plugin: signed status endpoints, deploy markers, form tests through Formie.
