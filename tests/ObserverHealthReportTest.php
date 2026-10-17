<?php

declare(strict_types=1);

use mojo\observer\health\HealthReport;
use PHPUnit\Framework\TestCase;

/**
 * Oh Dear's application-health format from the craftly doctor's lines.
 */
final class ObserverHealthReportTest extends TestCase
{
    public function testDoctorLinesBecomeOhDearCheckResults(): void
    {
        $report = HealthReport::fromDoctor([
            ['check' => 'redis', 'status' => 'ok', 'message' => 'the cache answers'],
            ['check' => 'project config', 'status' => 'warn', 'message' => 'the yaml and the database differ — craft up'],
            ['check' => 'queue', 'status' => 'error', 'message' => '3 job(s) waiting for over an hour'],
        ], 1792000000);

        self::assertSame(1792000000, $report['finishedAt']);
        self::assertSame(['Redis', 'ProjectConfig', 'Queue'], array_column($report['checkResults'], 'name'));
        self::assertSame(['ok', 'warning', 'failed'], array_column($report['checkResults'], 'status'));
        self::assertSame('', $report['checkResults'][0]['notificationMessage']);
        self::assertSame('3 job(s) waiting for over an hour', $report['checkResults'][2]['notificationMessage']);
    }

    public function testAnUnknownStatusIsReportedAsCrashed(): void
    {
        $report = HealthReport::fromDoctor([['check' => 'x', 'status' => 'odd', 'message' => 'm']], 1);

        self::assertSame('crashed', $report['checkResults'][0]['status']);
    }
}
