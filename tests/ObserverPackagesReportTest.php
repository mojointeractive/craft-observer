<?php

declare(strict_types=1);

use mojo\observer\packages\PackagesReport;
use PHPUnit\Framework\TestCase;

/**
 * The Composer inventory observer matches against security advisories: every installed package with
 * its version, development requirements marked.
 */
final class ObserverPackagesReportTest extends TestCase
{
    public function testEveryPackageHasItsNameVersionAndWhetherItIsADevRequirement(): void
    {
        $report = PackagesReport::build([
            'packages' => [
                ['name' => 'verbb/formie', 'version' => '3.1.20', 'version_normalized' => '3.1.20.0'],
                ['name' => 'phpunit/phpunit', 'version' => '11.5.2'],
                ['name' => 'craftcms/cms', 'version' => '5.11.5.1'],
            ],
            'dev' => true,
            'dev-package-names' => ['phpunit/phpunit'],
        ]);

        self::assertSame([
            ['name' => 'craftcms/cms', 'version' => '5.11.5.1', 'dev' => false],
            ['name' => 'phpunit/phpunit', 'version' => '11.5.2', 'dev' => true],
            ['name' => 'verbb/formie', 'version' => '3.1.20', 'dev' => false],
        ], $report['packages']);
    }

    public function testALeadingVIsDroppedAndNamesAreLowerCase(): void
    {
        $report = PackagesReport::build(['packages' => [
            ['name' => 'Amphp/Amp', 'version' => 'v3.1.3'],
            ['name' => 'mojo/site', 'version' => 'dev-main'],
        ]]);

        self::assertSame([
            ['name' => 'amphp/amp', 'version' => '3.1.3', 'dev' => false],
            ['name' => 'mojo/site', 'version' => 'dev-main', 'dev' => false],
        ], $report['packages']);
    }

    public function testAnEntryWithoutNameOrVersionIsLeftOut(): void
    {
        $report = PackagesReport::build(['packages' => [['name' => 'a/b'], 'nonsense', ['version' => '1.0.0'], ['name' => 'c/d', 'version' => '1.0.0']]]);

        self::assertSame([['name' => 'c/d', 'version' => '1.0.0', 'dev' => false]], $report['packages']);
    }

    public function testAPackageFromAPathRepositoryStaysOnTheSite(): void
    {
        $report = PackagesReport::build(['packages' => [
            ['name' => 'client/site-module', 'version' => '1.0.0', 'dist' => ['type' => 'path', 'url' => 'modules/site']],
            ['name' => 'craftcms/cms', 'version' => '5.11.5.1', 'dist' => ['type' => 'zip', 'url' => 'https://api.github.com/x']],
        ]]);

        self::assertSame([['name' => 'craftcms/cms', 'version' => '5.11.5.1', 'dev' => false]], $report['packages']);
    }

    public function testAnythingButComposerTwosFileIsAnErrorNotAnEmptyInventory(): void
    {
        self::assertArrayHasKey('error', PackagesReport::build(null));
        self::assertArrayHasKey('error', PackagesReport::build([['name' => 'a/b', 'version' => '1.0.0']]));
        self::assertArrayNotHasKey('packages', PackagesReport::build(null));
    }

    public function testAMissingFileReadsAsNull(): void
    {
        self::assertNull(PackagesReport::read(__DIR__ . '/no-such-installed.json'));
    }
}
