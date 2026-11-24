<?php

declare(strict_types=1);

use mojo\observer\backups\TargetInspector;
use PHPUnit\Framework\TestCase;
use webhubworks\backup\models\BackupConfig;
use webhubworks\backup\services\targets\TargetInterface;

/**
 * One target listed on any version of craft-backup: the backups always; sanitized copies and
 * Craft's dumps only where the plugin can tell them apart (2.6+); whichever driver builds the target.
 */
final class ObserverTargetInspectorTest extends TestCase
{
    private const KINDS = ['sanitized' => 'sanitized', 'craft' => 'craft'];

    /**
     * A target that answers each kind with its own file and records how it was asked. A target of a
     * version before 2.6 declares `list()` without a parameter; PHP passes an argument to it anyway,
     * which is why the inspector must not ask one for a kind.
     *
     * @return TargetInterface&object{calls: list<list<mixed>>}
     */
    private static function target(): TargetInterface
    {
        return new class() implements TargetInterface {
            /** @var list<list<mixed>> */
            public array $calls = [];

            public function upload(string $localPath, string $remoteFilename, BackupConfig $config): void
            {
            }

            public function list(string $kind = self::KIND_BACKUP): array
            {
                $this->calls[] = func_get_args();

                return [['path' => "/x/{$kind}.zip", 'size' => strlen($kind), 'modified' => 1792800000, 'encrypted' => null]];
            }

            public function delete(string $path): void
            {
            }

            public function diskUsage(): ?array
            {
                return ['total' => 100, 'free' => 40];
            }
        };
    }

    public function testAPluginThatListsKindsGivesEachItsOwnList(): void
    {
        $target = self::target();
        $listing = (new TargetInspector(static fn (): TargetInterface => $target, self::KINDS))->inspect(['driver' => 'local'], 'craft-backup');

        self::assertSame('/x/backup.zip', $listing['backups'][0]['path']);
        self::assertSame('/x/sanitized.zip', $listing['sanitized'][0]['path'] ?? null);
        self::assertSame('/x/craft.zip', $listing['craft'][0]['path'] ?? null);
        self::assertSame(['total' => 100, 'free' => 40], $listing['disk']);
        self::assertSame([[], ['sanitized'], ['craft']], $target->calls);
    }

    public function testAnOlderPluginIsAskedForItsBackupsOnly(): void
    {
        $target = self::target();
        $listing = (new TargetInspector(static fn (): TargetInterface => $target, null))->inspect(['driver' => 'local'], 'craft-backup');

        self::assertSame('/x/backup.zip', $listing['backups'][0]['path']);
        self::assertNull($listing['sanitized']);
        self::assertNull($listing['craft']);
        self::assertSame([[]], $target->calls, 'list() without an argument, once');
    }

    public function testTheTargetIsBuiltFromItsDefinitionAndTheBackupName(): void
    {
        $built = [];
        $make = static function (array $definition, string $backupName) use (&$built): TargetInterface {
            $built[] = [$definition, $backupName];

            return self::target();
        };
        // A driver the site registered: its definition reaches the factory as it is.
        (new TargetInspector($make, self::KINDS))->inspect(['driver' => 's3-archive', 'bucket' => 'b', 'timeout' => 90], 'client-site');

        self::assertSame([[['driver' => 's3-archive', 'bucket' => 'b', 'timeout' => 90], 'client-site']], $built);
    }

    public function testAnSftpTargetIsListedWithAShortTimeout(): void
    {
        $timeouts = [];
        $make = static function (array $definition) use (&$timeouts): TargetInterface {
            $timeouts[] = $definition['timeout'] ?? null;

            return self::target();
        };
        $inspector = new TargetInspector($make, null);

        $inspector->inspect(['driver' => 'sftp', 'host' => 'h'], 'n');
        $inspector->inspect(['driver' => 'sftp', 'host' => 'h', 'timeout' => 30], 'n');
        $inspector->inspect(['driver' => 'sftp', 'host' => 'h', 'timeout' => 4], 'n');

        self::assertSame([TargetInspector::SFTP_TIMEOUT, TargetInspector::SFTP_TIMEOUT, 4], $timeouts);
    }

    public function testATargetThatCannotBeBuiltThrows(): void
    {
        $inspector = new TargetInspector(static fn (): TargetInterface => throw new RuntimeException("Unknown target driver 'ftp'."), self::KINDS);

        $this->expectExceptionMessage("Unknown target driver 'ftp'.");
        $inspector->inspect(['driver' => 'ftp'], 'n');
    }

    public function testTheInstalledPluginsKindsAreDetected(): void
    {
        $inspector = TargetInspector::forInstalledPlugin();
        $kinds = (new ReflectionProperty($inspector, 'kinds'))->getValue($inspector);

        self::assertSame(
            defined(TargetInterface::class . '::KIND_SANITIZED') ? ['sanitized' => 'sanitized', 'craft' => 'craft'] : null,
            $kinds,
        );
    }
}
