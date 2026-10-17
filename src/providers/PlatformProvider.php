<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use craft\helpers\App;
use mojo\observer\vitals\Provider;

/** Versions, environment and the configuration observer watches. */
final class PlatformProvider implements Provider
{
    public function key(): string
    {
        return 'platform';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $general = $app->getConfig()->getGeneral();
        /** @var \craft\db\Connection $db */
        $db = $app->getDb();
        $craftly = @file_get_contents(Craft::getAlias('@root') . '/.craftly.json');
        $craftlyRecord = is_string($craftly) ? json_decode($craftly, true) : null;

        return [
            'craftVersion' => $app->getVersion(),
            'craftEdition' => $app->edition->handle(),
            'craftlyVersion' => is_array($craftlyRecord) ? ($craftlyRecord['coreVersion'] ?? null) : null,
            'phpVersion' => PHP_VERSION,
            'dbDriver' => $db->getDriverLabel(),
            'dbVersion' => App::normalizeVersion($db->getSchema()->getServerVersion()),
            'timezone' => $app->getTimeZone(),
            'environment' => (string)(App::env('ENVIRONMENT') ?: $app->env),
            'devMode' => $general->devMode,
            'allowAdminChanges' => $general->allowAdminChanges,
            'systemLive' => $app->getIsLive(),
            'maintenance' => App::parseBooleanEnv('$MAINTENANCE') ?? false,
            'sites' => array_map(static fn ($site): array => [
                'handle' => $site->handle,
                'name' => $site->getName(),
                'baseUrl' => $site->getBaseUrl(),
                'language' => $site->language,
                'primary' => $site->primary,
            ], $app->getSites()->getAllSites()),
        ];
    }
}
