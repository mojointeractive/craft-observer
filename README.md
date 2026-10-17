# mojo observer for Craft CMS

The connector between a Craft CMS 5 site and [mojo observer](https://observer.mojo.ch), mojo's monitoring
tool. It answers observer's signed requests about the install (versions, pending updates, the queue,
backups, admins, the craftly doctor where there is one, an Oh Dear health report, the Formie inventory),
reports deploys with `php craft observer/deploy-marker`, and lets observer's form tests pass through real
forms.

It is configured by environment variables only and does nothing until `OBSERVER_KEY` is set. The
variables, the endpoints and what each one returns: craftly's `docs/observer.md`.

## Requirements

Craft CMS 5.11 or later, PHP 8.2 or later. Forms: Formie 4.

## On a craftly site

Nothing to do: craftly requires this plugin from `plugins/observer` and installs it with `php craft up`.

## On any other Craft 5 site

1. Copy this folder to `plugins/observer` in the project.
2. Add the path repository and require the plugin:

   ```json
   "repositories": [{ "type": "path", "url": "plugins/observer", "options": { "symlink": true } }],
   "require": { "mojointeractive/craft-observer": "^1.0" }
   ```

3. `composer update mojointeractive/craft-observer`, then `php craft plugin/install observer`.
4. Set `OBSERVER_KEY` (observer generates it on the site's Craft tab) and, for deploy markers,
   `OBSERVER_SITE_ID` and `OBSERVER_HOOK_URL`.
5. If the site uses Patrol's maintenance mode, let observer's requests through, as craftly's
   `config/patrol.php` does with `mojo\observer\security\MaintenanceBypass::applies()`.

## Other form plugins

Forms reach observer through form adapters (`mojo\observer\forms\FormAdapter`). The plugin brings one for
Formie; another form plugin adds its own by listening to `Observer::EVENT_REGISTER_FORM_ADAPTERS`.
