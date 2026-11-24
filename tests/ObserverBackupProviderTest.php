<?php

declare(strict_types=1);

use mojo\observer\providers\BackupProvider;
use PHPUnit\Framework\TestCase;

/**
 * The ages of the newest local backup files, without GLOB_BRACE (undefined on musl builds). A sanitized
 * copy (craft-backup 2.6) and an upload still in progress lie beside the archives and are no backup.
 */
final class ObserverBackupProviderTest extends TestCase
{
    private const FILES = [
        'a.sql' => 1792000000,
        'b.zip' => 1792003600,
        'c.gz' => 1791990000,
        'notes.txt' => 1792090000,
        'archives/site.zip' => 1792007200,
        'archives/craft-backup-2026-10-14_21-00-00-a1b2c3d4-sanitized.zip' => 1792090000,
        'archives/craft-backup-2026-10-14_22-00-00-b1b2c3d4-db.zip.part' => 1792093600,
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/observer-backups-' . uniqid();
        mkdir($this->root . '/archives', 0777, true);
        foreach (self::FILES as $file => $time) {
            touch($this->root . '/' . $file, $time);
        }
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::FILES) as $file) {
            unlink($this->root . '/' . $file);
        }
        rmdir($this->root . '/archives');
        rmdir($this->root);
    }

    public function testDatabaseDumpsAreSqlZipAndGzFiles(): void
    {
        self::assertSame(['count' => 3, 'newestAt' => '2026-10-14T18:46:40Z'], BackupProvider::databaseDumps($this->root));
    }

    public function testArchivesAreTheCompleteFilesUnderArchivesButSanitizedCopies(): void
    {
        self::assertSame(['count' => 1, 'newestAt' => '2026-10-14T19:46:40Z'], BackupProvider::archives($this->root));
    }

    public function testSanitizedCopiesAreCountedOnTheirOwn(): void
    {
        self::assertSame(['count' => 1, 'newestAt' => '2026-10-15T18:46:40Z'], BackupProvider::sanitizedCopies($this->root));
    }

    public function testAMissingDirectoryHasNoBackups(): void
    {
        self::assertSame(['count' => 0, 'newestAt' => null], BackupProvider::archives($this->root . '/nowhere'));
        self::assertSame(['count' => 0, 'newestAt' => null], BackupProvider::databaseDumps($this->root . '/nowhere'));
        self::assertSame(['count' => 0, 'newestAt' => null], BackupProvider::sanitizedCopies($this->root . '/nowhere'));
    }
}
