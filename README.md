# mojo observer for Craft CMS

The connector between a Craft CMS 5 site and mojo observer, mojo's monitoring tool. It answers
observer's signed requests about the install (versions, pending updates, the queue, backups, admins, the
craftly doctor where there is one, an Oh Dear health report, the Formie inventory), reports deploys with
`php craft observer/deploy-marker`, and lets observer's form tests pass through real forms.

It is configured by environment variables only and does nothing until `OBSERVER_KEY` is set.

## Requirements

Craft CMS 5.11 or later, PHP 8.2 or later. Forms: Formie 4.0.0 or later (not its betas). Backups: the
Backup plugin (`webhubworks/craft-backup`) where observer should watch them.

The site must answer at its domain root with Craft's default `actionTrigger` (`actions`): observer signs
the paths `/actions/observer/api/<action>` and the Patrol bypass matches exactly those. Nonces, the rate
counter and the form tests' mail routing live in Craft's data cache, so web requests and queue workers must
share it (Redis on a host with several nodes; the default file cache is fine on one host). The queue figures
come from Craft's queue table; a site on another queue driver reports zeros there.

## On a craftly site

Nothing to install: craftly requires this plugin from `plugins/observer` and installs it with
`php craft up`. craftly's `docs/observer.md` has the details.

## On any other Craft 5 site

1. Copy this folder to `plugins/observer` in the project.
2. Add the path repository and require the plugin in the project's `composer.json`:

   ```json
   "repositories": [{ "type": "path", "url": "plugins/observer", "options": { "symlink": true } }],
   "require": { "mojointeractive/craft-observer": "^1.0" }
   ```

3. `composer update mojointeractive/craft-observer`, then `php craft plugin/install observer`, and commit
   `composer.json`, `composer.lock`, `plugins/observer` and `config/project` (the install is a project
   config change, applied on the servers by `php craft up`).
4. In observer, open the site → **Craft** → **Generate connection key**, and put the lines it shows into
   the host's `.env`.
5. If the site uses Patrol's maintenance mode, let observer's requests through, so observer sees the
   maintenance instead of an outage:

   ```php
   'maintenanceModeEnabled' => App::env('MAINTENANCE')
       && !\mojo\observer\security\MaintenanceBypass::applies((string)($_SERVER['REQUEST_URI'] ?? '')),
   ```

6. To record deploys, run `php craft observer/deploy-marker` on the server right after each release goes
   live (`--release` names it; by default the directory the `current` symlink points at). It never fails:
   without its variables, or with observer unreachable, it says so and exits 0.
7. `php craft observer/check --ping` says what observer will find here: the configuration, which plugins
   it can report on (the Backup plugin, Formie), the data cache, and whether a signed request to `ping`
   gets through the web server (`--url=<base url>` when the primary site's URL is not reachable from the
   server itself). It exits 1 while a line is not ok.

## Environment

| Variable | Meaning |
|---|---|
| `OBSERVER_KEY` | The site's key from observer (observer generates 64 hex characters; anything of 32 or more counts). The switch: without it every endpoint answers 404. |
| `OBSERVER_ENABLED` | `false` turns the plugin off with the key still set. Default `true`. |
| `OBSERVER_SITE_ID` | observer's id for the site, for deploy markers. |
| `OBSERVER_HOOK_URL` | observer's deploy hook, for deploy markers. |
| `OBSERVER_HEALTH_SECRET` | The secret of Oh Dear's application-health check, so Oh Dear may read `health` without a signature. |

## What observer asks

Every request to `/actions/observer/api/<action>` is signed with `OBSERVER_KEY` (HMAC-SHA256 over method,
path, timestamp, nonce and body hash; five minutes of clock skew, no nonce twice, 60 requests a minute).
The actions: `ping`, `vitals`, `backups`, `doctor`, `health`, `formie-forms`, `form-test-run`, `form-test-cleanup`.
No recipient address leaves the site.

`vitals` is one entry per provider: the platform (versions, environment, the settings observer watches), the
updates Craft knows of, the queue, `system` (free space under storage/, pending migrations, project-config
drift), the newest local backup files, the admin accounts, and the craftly doctor's lines where there is
one. `health` is the same in Oh Dear's application-health format: storage, migrations, project config and
the queue on every site, then the doctor's lines; Oh Dear reads it with `OBSERVER_HEALTH_SECRET`.

`backups` reports what the Backup plugin (`webhubworks/craft-backup`) knows: each target with its backups
(time and size), whether it could be listed, free disk where the driver says, the last run and its errors
per target, the retention config and the `monitor_backups` rules. Target hosts, users, passwords, keys and
paths stay on the site. The answer is cached 10 minutes; SFTP targets are listed with a 10 s timeout. Without
the Backup plugin it answers `{"installed": false}`.

## Form tests

observer submits a tested form like a visitor, with an `X-Observer-Test` token signed for one run, one
form and at most ten minutes. With a valid token only: the page renders uncached and the form renders and
validates without its captchas (with Blitz installed, Craft neither serves that request from Blitz's
cache nor stores it; a file cache served by a server rewrite never reaches Craft, so there the page's
`?observer-run=<id>` must not be cached as the same page: `craft observer/check` warns when it would be),
Formie's honeypot and minimum-time guards are off for the request, its
integrations do not run, and its notification mail goes to observer's probe inbox (or the probe is added as
Bcc), each message with `X-Observer-Run`. A request whose token does not verify, or names a form plugin the
site does not run, submits nothing (409). The routing waits in Craft's data cache for the queue jobs, so
the queue workers must share it; a test notification without routing is held back, never sent to the
form's recipients. observer deletes the test submission afterwards.

## Other form plugins

Forms reach observer through form adapters (`mojo\observer\forms\FormAdapter`). The plugin brings one for
Formie; another form plugin adds its own with a class-level listener,
`Event::on(Observer::class, Observer::EVENT_REGISTER_FORM_ADAPTERS, …)`, that appends to the event's
`adapters`. Two adapters with the same handle: the last one counts.
