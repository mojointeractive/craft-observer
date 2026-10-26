<?php

declare(strict_types=1);

use mojo\observer\backups\BackupsReport;
use PHPUnit\Framework\TestCase;

/**
 * What observer learns about a site's backups: craft-backup's targets with their logical backups,
 * the last run and the retention config. Nothing that could reach a target leaves the site.
 */
final class ObserverBackupsReportTest extends TestCase
{
    private const NOW = 1792900000; // 2026-10-25T…Z

    private const RETENTION = [
        'keep_all_for_days' => 7,
        'keep_daily_for_days' => 16,
        'keep_weekly_for_weeks' => 8,
        'keep_monthly_for_months' => 4,
        'keep_yearly_for_years' => 2,
        'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
    ];

    /**
     * @param array<string, array<string, mixed>> $targets
     * @param array<string, mixed> $retention
     * @param array<string, mixed> $runState
     * @return array<string, mixed>
     */
    private static function report(
        array $targets,
        callable $inspect,
        array $retention = self::RETENTION,
        array $runState = ['lastRun' => null, 'lastSuccessfulRun' => null],
    ): array {
        return BackupsReport::build($targets, $retention, [], $runState, $inspect, self::NOW);
    }

    /** @param list<array{path: string, size: int, modified: int}> $backups */
    private static function listing(array $backups): callable
    {
        return static fn (): array => ['backups' => $backups, 'disk' => ['total' => 100, 'free' => 40]];
    }

    public function testTheTwoHalvesOfASplitRunAreOneBackup(): void
    {
        $report = self::report(['local' => ['driver' => 'local', 'root' => '@storage/backups/archives']], self::listing([
            ['path' => '/x/craft-backup-2026-10-24_03-00-00-a1b2c3d4-db.zip', 'size' => 100, 'modified' => 1792800000],
            ['path' => '/x/craft-backup-2026-10-24_03-00-00-a1b2c3d4-files.zip', 'size' => 900, 'modified' => 1792800060],
        ]));

        self::assertSame([['at' => gmdate('Y-m-d\TH:i:s\Z', 1792800060), 'bytes' => 1000]], $report['targets'][0]['backups']);
        self::assertSame(['total' => 100, 'free' => 40], $report['targets'][0]['disk']);
        self::assertTrue($report['targets'][0]['reachable']);
        self::assertNull($report['targets'][0]['error']);
    }

    public function testBackupsAreNewestFirstAndCappedAtOneThousand(): void
    {
        $files = [];
        for ($i = 0; $i < 1200; $i++) {
            $files[] = ['path' => sprintf('/x/craft-backup-%08x.zip', $i), 'size' => 1, 'modified' => 1792000000 + $i * 60];
        }
        $backups = self::report(['local' => ['driver' => 'local']], self::listing($files))['targets'][0]['backups'];

        self::assertCount(1000, $backups);
        self::assertSame(gmdate('Y-m-d\TH:i:s\Z', 1792000000 + 1199 * 60), $backups[0]['at']);
        self::assertGreaterThan($backups[1]['at'], $backups[0]['at']);
    }

    public function testAnUnreachableTargetIsReportedAndTheOthersStillListed(): void
    {
        $report = self::report(
            ['local' => ['driver' => 'local'], 'offsite' => ['driver' => 'sftp', 'host' => 'backup.example.com']],
            static function (string $name): array {
                if ($name === 'offsite') {
                    throw new RuntimeException('Connection timed out');
                }

                return ['backups' => [['path' => '/x/a-a1b2c3d4.zip', 'size' => 5, 'modified' => 1792800000]], 'disk' => null];
            },
        );

        self::assertSame(['local', 'offsite'], array_column($report['targets'], 'name'));
        self::assertCount(1, $report['targets'][0]['backups']);
        self::assertSame(
            ['name' => 'offsite', 'driver' => 'sftp', 'reachable' => false, 'error' => 'Connection timed out', 'backups' => [], 'disk' => null],
            $report['targets'][1],
        );
    }

    public function testNothingThatReachesATargetLeavesTheSite(): void
    {
        $json = (string)json_encode(self::report(['offsite' => [
            'driver' => 'sftp',
            'host' => 'backup.example.com',
            'username' => 'craft-user',
            'password' => 'hunter2-secret',
            'private_key' => '/home/craft/.ssh/id_backup',
            'passphrase' => 'phrase-secret',
            'root' => '/srv/backups/client',
        ]], self::listing([['path' => '/srv/backups/client/craft-backup-a1b2c3d4.zip', 'size' => 1, 'modified' => 1792800000]])));

        foreach (['backup.example.com', 'craft-user', 'hunter2-secret', 'id_backup', 'phrase-secret', '/srv/backups', 'host', 'password', 'private_key', 'passphrase', 'path'] as $needle) {
            self::assertStringNotContainsString($needle, $json);
        }
    }

    public function testErrorsNameNoHostUserOrPath(): void
    {
        $def = ['driver' => 'sftp', 'host' => 'sftp.example.com', 'username' => 'craft-user', 'root' => '/srv/backups/client'];
        $report = BackupsReport::build(
            ['offsite' => $def],
            self::RETENTION,
            [],
            ['lastRun' => [
                'at' => 1792810000,
                'status' => 'failed',
                'errors' => ['offsite: Could not copy backup to /var/www/shared/storage/backups/archives/x.zip'],
                'targetStatuses' => ['offsite' => 'failed: Unable to connect to host sftp.example.com'],
            ], 'lastSuccessfulRun' => null],
            static fn (): array => throw new RuntimeException('Unable to connect to sftp.example.com as craft-user: cannot write /srv/backups/client/x.zip'),
            self::NOW,
        );

        self::assertSame('Unable to connect to [host] as [user]: cannot write [path]', $report['targets'][0]['error']);
        self::assertSame(['offsite: Could not copy backup to [path]'], $report['lastRun']['errors']);
        self::assertSame(['offsite' => 'failed: Unable to connect to host [host]'], $report['lastRun']['targetStatuses']);
    }

    public function testRetentionIsReportedInObserversTerms(): void
    {
        $report = self::report(['local' => ['driver' => 'local']], self::listing([]));
        self::assertSame(
            ['keepAllForDays' => 7, 'keepDailyForDays' => 16, 'keepWeeklyForWeeks' => 8, 'keepMonthlyForMonths' => 4, 'keepYearlyForYears' => 2, 'sizeCapMb' => 5000],
            $report['retention'],
        );

        $uncapped = self::report(['local' => ['driver' => 'local']], self::listing([]), ['delete_oldest_backups_when_using_more_megabytes_than' => null]);
        self::assertSame(
            ['keepAllForDays' => 0, 'keepDailyForDays' => 0, 'keepWeeklyForWeeks' => 0, 'keepMonthlyForMonths' => 0, 'keepYearlyForYears' => 0, 'sizeCapMb' => null],
            $uncapped['retention'],
        );
    }

    public function testTheSitesOwnRulesAreReported(): void
    {
        $report = BackupsReport::build(['local' => ['driver' => 'local']], self::RETENTION, [
            ['target' => 'local', 'min_number_of_backups' => 1, 'youngest_backup_should_be_within_the_last' => '1d', 'warn_when_disk_space_is_lower_than' => null],
            ['target' => 'offsite'],
        ], ['lastRun' => null, 'lastSuccessfulRun' => null], self::listing([]), self::NOW);

        self::assertSame([
            ['target' => 'local', 'minNumberOfBackups' => 1, 'youngestWithin' => '1d'],
            ['target' => 'offsite', 'minNumberOfBackups' => null, 'youngestWithin' => null],
        ], $report['rules']);
    }

    public function testTheLastRunIsReportedInUtc(): void
    {
        $report = self::report(['local' => ['driver' => 'local']], self::listing([]), self::RETENTION, [
            'lastRun' => ['at' => 1792810000, 'status' => 'failed', 'errors' => ['offsite: Auth failed'], 'targetStatuses' => ['local' => 'ok', 'offsite' => 'failed: Auth failed']],
            'lastSuccessfulRun' => ['at' => 1792720000],
        ]);

        self::assertSame([
            'at' => gmdate('Y-m-d\TH:i:s\Z', 1792810000),
            'status' => 'failed',
            'errors' => ['offsite: Auth failed'],
            'targetStatuses' => ['local' => 'ok', 'offsite' => 'failed: Auth failed'],
        ], $report['lastRun']);
        self::assertSame(gmdate('Y-m-d\TH:i:s\Z', 1792720000), $report['lastSuccessfulRunAt']);
        self::assertSame(gmdate('Y-m-d\TH:i:s\Z', self::NOW), $report['collectedAt']);
    }

    public function testNoRunYetIsNull(): void
    {
        $report = self::report(['local' => ['driver' => 'local']], self::listing([]));

        self::assertNull($report['lastRun']);
        self::assertNull($report['lastSuccessfulRunAt']);
    }

    public function testAnInvalidConfigIsReportedWithoutTargets(): void
    {
        $report = BackupsReport::failed("Encryption is enabled but 'encryption.key' is empty.", ['lastRun' => null, 'lastSuccessfulRun' => null], self::NOW);

        self::assertSame("Encryption is enabled but 'encryption.key' is empty.", $report['configError']);
        self::assertSame(
            "Invalid local target root: '[path]'.",
            BackupsReport::failed("Invalid local target root: '/var/www/shared/storage/backups'.", ['lastRun' => null, 'lastSuccessfulRun' => null], self::NOW)['configError'],
        );
        self::assertSame([], $report['targets']);
        self::assertNull($report['retention']);
        self::assertSame([], $report['rules']);
    }
}
