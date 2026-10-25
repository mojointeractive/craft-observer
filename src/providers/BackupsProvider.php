<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use mojo\observer\backups\BackupsReport;
use mojo\observer\vitals\Provider;
use RuntimeException;
use Throwable;
use webhubworks\backup\models\BackupConfig;
use webhubworks\backup\Plugin as BackupPlugin;
use webhubworks\backup\services\targets\LocalTarget;
use webhubworks\backup\services\targets\SftpTarget;

/**
 * craft-backup's view of the site's backups for the `backups` action: every target listed (SFTP with a
 * short timeout, so an unreachable host costs seconds, not the request), the last run, the retention
 * config. Kept 10 minutes in Craft's cache, because listing a remote target is slow. Not part of vitals.
 */
final class BackupsProvider implements Provider
{
    private const CACHE_KEY = 'observer:backups';
    private const CACHE_SECONDS = 600;
    private const SFTP_TIMEOUT = 10;

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

        return BackupsReport::build(
            $config->targets,
            $config->retention,
            $config->monitorBackups,
            $runState,
            static fn (string $name, array $def): array => self::inspect($def),
            $now,
        );
    }

    /**
     * @param array<string, mixed> $def
     * @return array{backups: list<array{path: string, size: int, modified: int}>, disk: ?array{total: int, free: int}}
     */
    private static function inspect(array $def): array
    {
        $target = match ($def['driver'] ?? null) {
            'local' => new LocalTarget($def),
            // The site's own timeout when shorter; never longer than ours.
            'sftp' => new SftpTarget(['timeout' => min(self::SFTP_TIMEOUT, (int)($def['timeout'] ?? self::SFTP_TIMEOUT))] + $def),
            default => throw new RuntimeException('Unknown driver ' . json_encode($def['driver'] ?? null)),
        };

        return ['backups' => array_values($target->list()), 'disk' => $target->diskUsage()];
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
