<?php

declare(strict_types=1);

use mojo\observer\testing\RunRouting;
use PHPUnit\Framework\TestCase;
use yii\caching\ArrayCache;

/** Where a run's notifications go, kept from the test request for the queue jobs that send them. */
final class ObserverRunRoutingTest extends TestCase
{
    private const RUN = '0123456789abcdef0123456789abcdef';
    private const PROBE = 'observer+0123456789abcdef0123456789abcdef@mojo.ch';

    public function testItRemembersARunsMailModeAndProbe(): void
    {
        $cache = new ArrayCache();
        (new RunRouting($cache))->remember(self::RUN, 'redirect', self::PROBE);

        // A queue job reads it with its own instance.
        $routing = new RunRouting($cache);
        self::assertSame(['mail' => 'redirect', 'probe' => self::PROBE], $routing->of(self::RUN));
        self::assertNull($routing->of('fedcba9876543210fedcba9876543210'));
    }

    public function testAnythingElseInTheCacheIsNoRouting(): void
    {
        $cache = new ArrayCache();
        $cache->set('observer:run:' . self::RUN, ['mail' => 'redirect']);

        self::assertNull((new RunRouting($cache))->of(self::RUN));
    }

    public function testOnlyAnObserverTitleNamesARun(): void
    {
        self::assertSame(self::RUN, RunRouting::runOf('observer:' . self::RUN));
        self::assertSame('observer:' . self::RUN, RunRouting::title(self::RUN));

        foreach ([null, '', 'Kontaktanfrage', 'observer:', 'Observer:' . self::RUN, 'observer: ' . self::RUN, 'observer:' . self::RUN . '0', 'observer:' . strtoupper(self::RUN)] as $title) {
            self::assertNull(RunRouting::runOf($title), var_export($title, true));
        }
    }
}
