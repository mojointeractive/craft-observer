<?php

declare(strict_types=1);

use mojo\observer\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The connector is configured by environment only, and its key is the switch: an install that
 * never set OBSERVER_KEY answers 404 on the API, as if the module were not there.
 */
final class ObserverSettingsTest extends TestCase
{
    private const KEY = 'k0123456789abcdef0123456789abcdef0123456789';

    /** @param array<string, string> $vars */
    private static function settings(array $vars): Settings
    {
        return Settings::fromEnv(static fn (string $name): ?string => $vars[$name] ?? null);
    }

    public function testWithoutAKeyTheApiIsOff(): void
    {
        self::assertFalse(self::settings([])->apiIsOn());
        self::assertFalse(self::settings(['OBSERVER_KEY' => '   '])->apiIsOn());
    }

    public function testAKeyTooShortToBeObserversIsIgnored(): void
    {
        self::assertNull(self::settings(['OBSERVER_KEY' => 'short-key'])->key);
    }

    /** Craft's App::env() turns "false" into false and digits into numbers; the switch must survive that. */
    public function testValuesCraftNormalisedKeepTheirMeaning(): void
    {
        self::assertSame('false', Settings::envString(false));
        self::assertSame('true', Settings::envString(true));
        self::assertSame('0', Settings::envString(0));
        self::assertNull(Settings::envString(null));

        $env = ['OBSERVER_KEY' => self::KEY, 'OBSERVER_ENABLED' => false];
        $settings = Settings::fromEnv(static fn (string $name): ?string => Settings::envString($env[$name] ?? null));
        self::assertFalse($settings->apiIsOn());
    }

    public function testAKeySwitchesTheApiOnUnlessDisabled(): void
    {
        self::assertTrue(self::settings(['OBSERVER_KEY' => self::KEY])->apiIsOn());
        self::assertFalse(self::settings(['OBSERVER_KEY' => self::KEY, 'OBSERVER_ENABLED' => 'false'])->apiIsOn());
        self::assertFalse(self::settings(['OBSERVER_KEY' => self::KEY, 'OBSERVER_ENABLED' => '0'])->apiIsOn());
        self::assertTrue(self::settings(['OBSERVER_KEY' => self::KEY, 'OBSERVER_ENABLED' => 'true'])->apiIsOn());
    }

    public function testDeployReportsNeedTheSiteIdAndTheHookUrl(): void
    {
        self::assertFalse(self::settings(['OBSERVER_KEY' => self::KEY, 'OBSERVER_SITE_ID' => 'a'])->canReportDeploys());
        self::assertTrue(self::settings([
            'OBSERVER_KEY' => self::KEY,
            'OBSERVER_SITE_ID' => 'a',
            'OBSERVER_HOOK_URL' => 'https://x.supabase.co/functions/v1/deploy-hook',
        ])->canReportDeploys());
    }
}
