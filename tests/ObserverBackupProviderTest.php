<?php

declare(strict_types=1);

use mojo\observer\providers\BackupProvider;
use PHPUnit\Framework\TestCase;

/** The ages of the newest local backup files, without GLOB_BRACE (undefined on musl builds). */
final class ObserverBackupProviderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/observer-backups-' . uniqid();
        mkdir($this->root . '/archives', 0777, true);
        foreach (['a.sql' => 1792000000, 'b.zip' => 1792003600, 'c.gz' => 1791990000, 'notes.txt' => 1792090000, 'archives/site.zip' => 1792007200] as $file => $time) {
            touch($this->root . '/' . $file, $time);
        }
    }

    protected function tearDown(): void
    {
        foreach (['a.sql', 'b.zip', 'c.gz', 'notes.txt', 'archives/site.zip'] as $file) {
            unlink($this->root . '/' . $file);
        }
        rmdir($this->root . '/archives');
        rmdir($this->root);
    }

    public function testDatabaseDumpsAreSqlZipAndGzFiles(): void
    {
        self::assertSame(['count' => 3, 'newestAt' => '2026-10-14T18:46:40Z'], BackupProvider::databaseDumps($this->root));
    }

    public function testArchivesAreEveryFileUnderArchives(): void
    {
        self::assertSame(['count' => 1, 'newestAt' => '2026-10-14T19:46:40Z'], BackupProvider::archives($this->root));
    }

    public function testAMissingDirectoryHasNoBackups(): void
    {
        self::assertSame(['count' => 0, 'newestAt' => null], BackupProvider::archives($this->root . '/nowhere'));
        self::assertSame(['count' => 0, 'newestAt' => null], BackupProvider::databaseDumps($this->root . '/nowhere'));
    }
}
