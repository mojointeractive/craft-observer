<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use craft\helpers\App;
use mojo\observer\vitals\Provider;

/**
 * How old the newest backups are: craft-backup's local archives (craftly's config/backup.php
 * writes them to storage/backups/archives) and Craft's own database dumps in storage/backups.
 */
final class BackupProvider implements Provider
{
    public function key(): string
    {
        return 'backup';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        $root = (string)Craft::getAlias('@storage/backups');

        return [
            'archives' => self::newest(glob($root . '/archives/*') ?: []),
            'databaseDumps' => self::newest(glob($root . '/*.{sql,zip,gz}', GLOB_BRACE) ?: []),
            'offsiteConfigured' => App::env('BACKUP_SFTP_HOST') !== null,
        ];
    }

    /**
     * @param list<string> $files
     * @return array{count: int, newestAt: string|null}
     */
    private static function newest(array $files): array
    {
        $times = array_map(static fn (string $file): int => (int)filemtime($file), array_filter($files, 'is_file'));

        return ['count' => count($times), 'newestAt' => $times === [] ? null : gmdate('Y-m-d\TH:i:s\Z', max($times))];
    }
}
