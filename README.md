# mojo observer for Craft CMS

The connector between a Craft CMS 5 site and mojo observer, mojo's monitoring tool. It answers
observer's signed requests about the install (versions, pending updates, the queue, backups, admins, the
craftly doctor where there is one, an Oh Dear health report, the Formie inventory), reports deploys with
`php craft observer/deploy-marker`, and lets observer's form tests pass through real forms.

It is configured by environment variables only and does nothing until `OBSERVER_KEY` is set.

## Requirements

Craft CMS 5.11 or later, PHP 8.2 or later. Forms: Formie 4.

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

## Environment

| Variable | Meaning |
|---|---|
| `OBSERVER_KEY` | The site's key from observer (64 hex characters). The switch: without it every endpoint answers 404. |
| `OBSERVER_ENABLED` | `false` turns the plugin off with the key still set. Default `true`. |
| `OBSERVER_SITE_ID` | observer's id for the site, for deploy markers. |
| `OBSERVER_HOOK_URL` | observer's deploy hook, for deploy markers. |
| `OBSERVER_HEALTH_SECRET` | The secret of Oh Dear's application-health check, so Oh Dear may read `health` without a signature. |

## What observer asks

Every request to `/actions/observer/api/<action>` is signed with `OBSERVER_KEY` (HMAC-SHA256 over method,
path, timestamp, nonce and body hash; five minutes of clock skew, no nonce twice, 60 requests a minute).
The actions: `ping`, `vitals`, `doctor`, `health`, `formie-forms`, `form-test-run`, `form-test-cleanup`.
No recipient address leaves the site.

## Form tests

observer submits a tested form like a visitor, with an `X-Observer-Test` token signed for one run, one
form and at most ten minutes. With a valid token only: the page renders uncached and the form renders and
validates without its captchas, Formie's honeypot, minimum-time and replay guards are skipped for it, its
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
