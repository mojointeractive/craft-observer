<?php

declare(strict_types=1);

use craft\events\CancelableEvent;
use mojo\observer\security\BlitzGuard;
use PHPUnit\Framework\TestCase;
use yii\base\Component;

final class ObserverBlitzGuardStubService extends Component
{
}

/** A request carrying a form-test token is never served from or stored in Blitz's cache. */
final class ObserverBlitzGuardTest extends TestCase
{
    public function testATestRequestIsNotCacheable(): void
    {
        self::assertTrue(BlitzGuard::install('some.token', ObserverBlitzGuardStubService::class));

        $event = new CancelableEvent();
        (new ObserverBlitzGuardStubService())->trigger(BlitzGuard::EVENT, $event);

        self::assertFalse($event->isValid);
    }

    public function testNothingWithoutAHeaderOrWithoutBlitz(): void
    {
        self::assertFalse(BlitzGuard::install(null, ObserverBlitzGuardStubService::class));
        self::assertFalse(BlitzGuard::install('', ObserverBlitzGuardStubService::class));
        self::assertFalse(BlitzGuard::install('some.token', 'putyourlightson\\nowhere\\CacheRequestService'));
    }
}
