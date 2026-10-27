<?php

declare(strict_types=1);

use mojo\observer\check\InstallCheck;
use PHPUnit\Framework\TestCase;

/** `craft observer/check`: what an install's facts mean, as lines an operator reads after installing. */
final class ObserverInstallCheckTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function facts(array $overrides = []): array
    {
        return $overrides + [
            'keyLength' => 64,
            'enabled' => true,
            'siteId' => 'a3d2…',
            'hookUrl' => 'https://observer.mojo.ch/functions/v1/deploy-hook',
            'healthSecret' => null,
            'doctor' => true,
            'backup' => '2.3.4',
            'formie' => '4.0.4',
            'formieAdapter' => true,
            'formieSentNotifications' => true,
            'cache' => 'yii\\redis\\Cache',
            'blitz' => '5.14.1',
        ];
    }

    /** @return array<string, string> status by subject */
    private static function statuses(array $facts): array
    {
        $out = [];
        foreach (InstallCheck::lines($facts) as $line) {
            $out[$line['subject']] = $line['status'];
        }

        return $out;
    }

    public function testAConfiguredCraftlySiteIsAllOk(): void
    {
        $lines = InstallCheck::lines(self::facts());

        self::assertSame(['ok'], array_values(array_unique(array_column($lines, 'status'))));
        self::assertSame(
            ['key', 'enabled', 'deploy markers', 'health secret', 'doctor', 'backup', 'formie', 'sent notifications', 'cache', 'blitz'],
            array_column($lines, 'subject'),
        );
        self::assertSame(0, InstallCheck::exitCode($lines));
    }

    public function testTheKeyIsTheSwitch(): void
    {
        self::assertSame('fail', self::statuses(self::facts(['keyLength' => 0]))['key']);
        self::assertSame('fail', self::statuses(self::facts(['keyLength' => 20]))['key']);
        self::assertSame('warn', self::statuses(self::facts(['enabled' => false]))['enabled']);
        self::assertSame(1, InstallCheck::exitCode(InstallCheck::lines(self::facts(['keyLength' => 0]))));
    }

    public function testDeployMarkersNeedTheSiteIdAndTheHookUrl(): void
    {
        self::assertSame('warn', self::statuses(self::facts(['siteId' => null]))['deploy markers']);
        self::assertSame('warn', self::statuses(self::facts(['hookUrl' => null]))['deploy markers']);
        self::assertSame(1, InstallCheck::exitCode(InstallCheck::lines(self::facts(['hookUrl' => null]))));
    }

    public function testWhatIsOptionalIsOkWhenAbsent(): void
    {
        $plain = self::statuses(self::facts(['doctor' => false, 'formie' => null, 'formieAdapter' => false, 'formieSentNotifications' => null, 'blitz' => null, 'healthSecret' => null]));

        self::assertSame('ok', $plain['doctor']);
        self::assertSame('ok', $plain['formie']);
        self::assertSame('ok', $plain['health secret']);
        self::assertArrayNotHasKey('sent notifications', $plain);
        self::assertArrayNotHasKey('blitz', $plain);
    }

    public function testWhatObserverWatchesWarnsWhenItCannot(): void
    {
        self::assertSame('warn', self::statuses(self::facts(['backup' => null]))['backup']);
        self::assertSame('warn', self::statuses(self::facts(['formie' => '3.1.6', 'formieAdapter' => false]))['formie']);
        self::assertSame('warn', self::statuses(self::facts(['formieSentNotifications' => false]))['sent notifications']);
    }

    public function testTheFileCacheIsANoteNotAWarning(): void
    {
        $lines = InstallCheck::lines(self::facts(['cache' => 'craft\\cache\\FileCache']));
        $cache = array_values(array_filter($lines, static fn (array $l): bool => $l['subject'] === 'cache'))[0];

        self::assertSame('ok', $cache['status']);
        self::assertStringContainsString('queue workers', $cache['text']);
    }
}
