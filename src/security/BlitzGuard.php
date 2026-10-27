<?php

declare(strict_types=1);

namespace mojo\observer\security;

use craft\events\CancelableEvent;
use mojo\observer\testing\TestToken;
use yii\base\Event;

/**
 * A request carrying a verified form-test token (X-Observer-Test) renders without captchas, for this
 * request only: Blitz must neither serve it from its cache nor store it. Only a token that verifies
 * counts, so nobody can make pages render uncached by sending the header. Blitz asks whether a
 * request is cacheable at Craft's init, after the plugins loaded, so a listener registered in
 * Observer::init() is in time. Without Blitz nothing is registered.
 *
 * A file cache served by a server rewrite never reaches PHP: there `?observer-run=<id>` keeps the
 * test page out of the cache unless Blitz caches query strings as the same page
 * (`craft observer/check` warns about that setting).
 */
final class BlitzGuard
{
    public const CACHE_REQUEST_SERVICE = 'putyourlightson\\blitz\\services\\CacheRequestService';
    public const EVENT = 'isCacheableRequest';

    /**
     * @param TestToken|string|null $test what testing\TestRequest::fromHeader() made of the header
     * @return bool whether a listener was registered
     */
    public static function install(TestToken|string|null $test, string $serviceClass = self::CACHE_REQUEST_SERVICE): bool
    {
        if (!$test instanceof TestToken || !class_exists($serviceClass)) {
            return false;
        }
        Event::on($serviceClass, self::EVENT, static function (Event $event): void {
            if ($event instanceof CancelableEvent) {
                $event->isValid = false;
            }
        });

        return true;
    }
}
