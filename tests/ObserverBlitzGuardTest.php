<?php

declare(strict_types=1);

use craft\events\CancelableEvent;
use mojo\observer\security\BlitzGuard;
use mojo\observer\testing\TestToken;
use PHPUnit\Framework\TestCase;
use yii\base\Component;
use yii\base\Event;

final class ObserverBlitzGuardStubService extends Component
{
}

/** A request carrying a verified form-test token is never served from or stored in Blitz's cache. */
final class ObserverBlitzGuardTest extends TestCase
{
    private const KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';
    private const NOW = 1792223700;

    protected function tearDown(): void
    {
        Event::off(ObserverBlitzGuardStubService::class, BlitzGuard::EVENT);
    }

    private static function token(): TestToken
    {
        $header = TestToken::sign(self::KEY, [
            'v' => 1,
            'run' => '0123456789abcdef0123456789abcdef',
            'adapter' => 'formie',
            'form' => 'craftlyContact',
            'exp' => self::NOW + 300,
            'mail' => 'redirect',
            'probe' => 'observer+0123456789abcdef0123456789abcdef@mojo.ch',
        ]);
        $token = TestToken::verify(self::KEY, $header, self::NOW);
        self::assertInstanceOf(TestToken::class, $token);

        return $token;
    }

    public function testAVerifiedTestRequestIsNotCacheable(): void
    {
        self::assertTrue(BlitzGuard::install(self::token(), ObserverBlitzGuardStubService::class));

        $event = new CancelableEvent();
        (new ObserverBlitzGuardStubService())->trigger(BlitzGuard::EVENT, $event);

        self::assertFalse($event->isValid);
    }

    public function testNothingWithoutAVerifiedTokenOrWithoutBlitz(): void
    {
        // No header, a refused header (TestRequest::fromHeader's reason), and no Blitz: no listener.
        self::assertFalse(BlitzGuard::install(null, ObserverBlitzGuardStubService::class));
        self::assertFalse(BlitzGuard::install('expired', ObserverBlitzGuardStubService::class));
        self::assertFalse(BlitzGuard::install(self::token(), 'putyourlightson\\nowhere\\CacheRequestService'));

        $event = new CancelableEvent();
        (new ObserverBlitzGuardStubService())->trigger(BlitzGuard::EVENT, $event);
        self::assertTrue($event->isValid);
    }
}
