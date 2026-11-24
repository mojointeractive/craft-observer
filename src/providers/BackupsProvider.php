<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use mojo\observer\backups\BackupsReport;
use mojo\observer\backups\TargetInspector;
use mojo\observer\vitals\Provider;
use Throwable;
use webhubworks\backup\models\BackupConfig;
use webhubworks\backup\Plugin as BackupPlugin;

/**
 * craft-backup's view of the site's backups for the `backups` action: every target listed with its
 * backups, sanitized copies and Craft's own dumps (TargetInspector: SFTP with a short timeout, so an
 * unreachable host costs seconds, not the request; whichever plugin version is installed), the last
 * run, the retention config. Kept 10 minutes in Craft's cache, because listing a remote target is
 * slow. Not part of vitals.
 */
final class BackupsProvider implements Provider
{
    private const CACHE_KEY = 'observer:backups';
    private const CACHE_SECONDS = 600;

    public function key(): string
    {
        return 'backups';
    }

    public function available(): bool
    {
        return $this->plugin() !== null;
    }

    public function collect(): array
    {
        $cache = Craft::$app->getCache();
        $cached = $cache->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $plugin = $this->plugin();
        if ($plugin === null) {
            return ['installed' => false];
        }
        $report = ['installed' => true, 'version' => $plugin->getVersion()] + self::report($plugin, time());
        $cache->set(self::CACHE_KEY, $report, self::CACHE_SECONDS);

        return $report;
    }

    /** @return array<string, mixed> */
    private static function report(BackupPlugin $plugin, int $now): array
    {
        $runState = $plugin->runState->read();
        try {
            $config = BackupConfig::load();
        } catch (Throwable $e) {
            return BackupsReport::failed($e->getMessage(), $runState, $now);
        }

        $inspector = TargetInspector::forInstalledPlugin();

        return BackupsReport::build(
            $config->targets,
            $config->retention,
            $config->monitorBackups,
            $runState,
            static fn (string $name, array $def): array => $inspector->inspect($def, $config->name),
            $now,
        );
    }

    /** The Backup plugin, when it is installed and enabled. */
    private function plugin(): ?BackupPlugin
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $plugin = $app->getPlugins()->getPlugin('backup');

        return $plugin instanceof BackupPlugin ? $plugin : null;
    }
}
