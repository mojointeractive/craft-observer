<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use craft\helpers\App;
use mojo\observer\vitals\Provider;

/**
 * How old the newest backups are: craft-backup's local archives (craftly's config/backup.php
 * writes them to storage/backups/archives) and Craft's own database dumps in storage/backups.
 * Read from disk, so it answers without the Backup plugin too.
 */
final class BackupProvider implements Provider
{
    /**
     * craft-backup 2.6's sanitized copies, written beside the archives: `<name>-<date>-<runId>-sanitized.<ext>`.
     * A copy for developers is no backup of the site.
     */
    private const SANITIZED = '/-[a-f0-9]{8}-sanitized\.(zip|tar\.gz|tar\.gz\.enc)$/';

    /** An upload in progress (craft-backup 2.3.5+ writes `<archive>.part` and renames it once complete). */
    private const PARTIAL = '/\.part$/';

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
            'archives' => self::archives($root),
            'sanitizedCopies' => self::sanitizedCopies($root),
            'databaseDumps' => self::databaseDumps($root),
            'offsiteConfigured' => App::env('BACKUP_SFTP_HOST') !== null,
        ];
    }

    /**
     * Every complete file under archives/ but the sanitized copies.
     *
     * @return array{count: int, newestAt: string|null}
     */
    public static function archives(string $root): array
    {
        return self::newest(array_values(array_filter(
            glob($root . '/archives/*') ?: [],
            static fn (string $file): bool => !preg_match(self::SANITIZED, basename($file)) && !preg_match(self::PARTIAL, $file),
        )));
    }

    /** @return array{count: int, newestAt: string|null} */
    public static function sanitizedCopies(string $root): array
    {
        return self::newest(array_values(array_filter(
            glob($root . '/archives/*') ?: [],
            static fn (string $file): bool => (bool)preg_match(self::SANITIZED, basename($file)),
        )));
    }

    /**
     * No GLOB_BRACE: it is undefined on musl builds of PHP (Alpine images).
     *
     * @return array{count: int, newestAt: string|null}
     */
    public static function databaseDumps(string $root): array
    {
        $files = [];
        foreach (['sql', 'zip', 'gz'] as $extension) {
            $files = [...$files, ...(glob($root . '/*.' . $extension) ?: [])];
        }

        return self::newest($files);
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
