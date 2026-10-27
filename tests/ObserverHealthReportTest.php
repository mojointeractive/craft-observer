<?php

declare(strict_types=1);

use mojo\observer\health\HealthReport;
use PHPUnit\Framework\TestCase;

/**
 * Oh Dear's application-health format: the plugin's own checks (storage, migrations, project
 * config, queue) from the system and queue data, then the craftly doctor's lines.
 */
final class ObserverHealthReportTest extends TestCase
{
    private const MB = 1048576;

    /** @return array<string, mixed> */
    private static function system(int|null $freeBytes = 2048 * self::MB, bool $craft = false, array $content = [], bool $drift = false): array
    {
        return [
            'storage' => ['path' => '/srv/storage', 'freeBytes' => $freeBytes, 'totalBytes' => 10240 * self::MB],
            'migrations' => ['craft' => $craft, 'plugins' => [], 'content' => $content],
            'projectConfigPending' => $drift,
        ];
    }

    /** @return array<string, mixed> */
    private static function queue(?int $oldest = null, int $failed = 0): array
    {
        return ['pending' => $oldest === null ? 0 : 1, 'reserved' => 0, 'failed' => $failed, 'oldestPendingAgeSeconds' => $oldest];
    }

    public function testDoctorLinesBecomeOhDearCheckResults(): void
    {
        $report = HealthReport::build([
            ['check' => 'redis', 'status' => 'ok', 'message' => 'the cache answers'],
            ['check' => 'brand contrast', 'status' => 'warn', 'message' => 'two pairings fail AA'],
            ['check' => 'singles', 'status' => 'error', 'message' => 'the website single is missing'],
        ], null, null, 1792000000);

        self::assertSame(1792000000, $report['finishedAt']);
        self::assertSame(['Redis', 'BrandContrast', 'Singles'], array_column($report['checkResults'], 'name'));
        self::assertSame(['ok', 'warning', 'failed'], array_column($report['checkResults'], 'status'));
        self::assertSame('', $report['checkResults'][0]['notificationMessage']);
        self::assertSame('the website single is missing', $report['checkResults'][2]['notificationMessage']);
    }

    public function testAnUnknownStatusIsReportedAsCrashed(): void
    {
        $report = HealthReport::build([['check' => 'x', 'status' => 'odd', 'message' => 'm']], null, null, 1);

        self::assertSame('crashed', $report['checkResults'][0]['status']);
    }

    public function testTheOwnChecksComeFirstAndAreOkOnAHealthySite(): void
    {
        $report = HealthReport::build([['check' => 'redis', 'status' => 'ok', 'message' => 'm']], self::system(), self::queue(), 1);

        self::assertSame(['Storage', 'Migrations', 'ProjectConfig', 'Queue', 'Redis'], array_column($report['checkResults'], 'name'));
        self::assertSame(['ok', 'ok', 'ok', 'ok', 'ok'], array_column($report['checkResults'], 'status'));
        self::assertSame('Project config', $report['checkResults'][2]['label']);
    }

    public function testStorageThresholds(): void
    {
        $status = static fn (?int $free): array => HealthReport::build([], self::system($free), null, 1)['checkResults'][0];

        self::assertSame('failed', $status(199 * self::MB)['status']);
        self::assertStringContainsString('only 199 MB free', $status(199 * self::MB)['notificationMessage']);
        self::assertSame('warning', $status(1023 * self::MB)['status']);
        self::assertSame('ok', $status(1024 * self::MB)['status']);
        self::assertSame('ok', $status(null)['status']);
        self::assertSame('free space unknown', $status(null)['shortSummary']);
    }

    public function testPendingMigrationsFail(): void
    {
        $migrations = static fn (bool $craft, array $content): array => HealthReport::build([], self::system(craft: $craft, content: $content), null, 1)['checkResults'][1];

        self::assertSame('ok', $migrations(false, [])['status']);
        self::assertSame('failed', $migrations(true, [])['status']);
        self::assertSame('failed', $migrations(false, ['m260101_000000_fields'])['status']);
        self::assertStringContainsString('m260101_000000_fields', $migrations(false, ['m260101_000000_fields'])['notificationMessage']);
    }

    public function testProjectConfigDriftWarns(): void
    {
        self::assertSame('warning', HealthReport::build([], self::system(drift: true), null, 1)['checkResults'][2]['status']);
        self::assertSame('ok', HealthReport::build([], self::system(), null, 1)['checkResults'][2]['status']);
    }

    public function testTheQueueFailsWhenAJobWaitsOverAnHourAndWarnsOnFailedJobs(): void
    {
        $queue = static fn (?int $oldest, int $failed): array => HealthReport::build([], null, self::queue($oldest, $failed), 1)['checkResults'][0];

        self::assertSame('Queue', $queue(null, 0)['name']);
        self::assertSame('ok', $queue(3600, 0)['status']);
        self::assertSame('failed', $queue(3601, 0)['status']);
        self::assertStringContainsString('1 h 0 min', $queue(3601, 0)['notificationMessage']);
        self::assertSame('warning', $queue(null, 2)['status']);
        self::assertStringContainsString('2 failed job(s)', $queue(null, 2)['notificationMessage']);
        // A stuck queue outranks failed jobs.
        self::assertSame('failed', $queue(7200, 2)['status']);
    }

    public function testEveryResultHasOhDearsFields(): void
    {
        $result = HealthReport::build([], self::system(), null, 1)['checkResults'][0];

        self::assertSame(['name', 'label', 'status', 'notificationMessage', 'shortSummary', 'meta'], array_keys($result));
        self::assertInstanceOf(stdClass::class, $result['meta']);
    }
}
