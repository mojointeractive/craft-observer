<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use mojo\observer\vitals\Provider;

/**
 * What every Craft install can say about itself without craftly: free space under storage/,
 * pending migrations and project-config drift. Facts only; observer judges them, and
 * health\HealthReport judges them for Oh Dear. On craftly sites the doctor no longer repeats them
 * (CraftlyDoctorProvider::withoutCovered).
 */
final class SystemProvider implements Provider
{
    public function key(): string
    {
        return 'system';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $path = (string)Craft::getAlias('@storage');
        $updates = $app->getUpdates();
        $plugins = $app->getPlugins();
        $pluginMigrations = [];
        foreach ($plugins->getAllPlugins() as $plugin) {
            if ($plugins->isPluginUpdatePending($plugin)) {
                $pluginMigrations[] = $plugin->id;
            }
        }

        return [
            'storage' => self::storage($path, @disk_free_space($path), @disk_total_space($path)),
            'migrations' => [
                'craft' => $updates->getIsCraftUpdatePending(),
                'plugins' => $pluginMigrations,
                'content' => array_values($app->getContentMigrator()->getNewMigrations()),
            ],
            'projectConfigPending' => $app->getProjectConfig()->areChangesPending(null, true),
        ];
    }

    /**
     * @param float|false $free what disk_free_space() said
     * @param float|false $total what disk_total_space() said
     * @return array{path: string, freeBytes: int|null, totalBytes: int|null}
     */
    public static function storage(string $path, float|false $free, float|false $total): array
    {
        return [
            'path' => $path,
            'freeBytes' => $free === false ? null : (int)$free,
            'totalBytes' => $total === false ? null : (int)$total,
        ];
    }
}
