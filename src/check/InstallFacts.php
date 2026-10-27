<?php

declare(strict_types=1);

namespace mojo\observer\check;

use Craft;
use craft\helpers\App;
use mojo\observer\forms\FormAdapters;
use mojo\observer\Observer;
use mojo\observer\Settings;

/** The facts InstallCheck judges, read from this install. */
final class InstallFacts
{
    /** @return array<string, mixed> */
    public static function collect(): array
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $settings = Observer::settings();
        $plugins = $app->getPlugins();
        $formie = $plugins->getPlugin('formie');
        $backup = $plugins->getPlugin('backup');
        $blitz = $plugins->getPlugin('blitz');
        $adapter = FormAdapters::get('formie') !== null;
        $sent = null;
        if ($adapter && $formie !== null) {
            $formieSettings = $formie->getSettings();
            $sent = is_object($formieSettings) && property_exists($formieSettings, 'sentNotifications')
                ? (bool)$formieSettings->sentNotifications
                : null;
        }

        return [
            'keyLength' => strlen(trim((string)Settings::envString(App::env('OBSERVER_KEY')))),
            'enabled' => $settings->enabled,
            'siteId' => $settings->siteId,
            'hookUrl' => $settings->hookUrl,
            'healthSecret' => $settings->healthSecret,
            'doctor' => class_exists('modules\\craftly\\health\\Doctor'),
            'backup' => $backup !== null && $plugins->isPluginEnabled('backup') ? $backup->getVersion() : null,
            'formie' => $formie !== null && $plugins->isPluginEnabled('formie') ? $formie->getVersion() : null,
            'formieAdapter' => $adapter,
            'formieSentNotifications' => $sent,
            'cache' => get_class($app->getCache()),
            'blitz' => $blitz !== null && $plugins->isPluginEnabled('blitz') ? $blitz->getVersion() : null,
        ];
    }
}
