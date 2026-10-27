<?php

declare(strict_types=1);

namespace mojo\observer\security;

use craft\events\CancelableEvent;
use yii\base\Event;

/**
 * A request carrying a form-test token (X-Observer-Test) renders without captchas, for this request
 * only: Blitz must neither serve it from its cache nor store it. Blitz asks whether a request is
 * cacheable at Craft's init, after the plugins loaded, so a listener registered in Observer::init()
 * is in time. Without Blitz nothing is registered.
 */
final class BlitzGuard
{
    public const CACHE_REQUEST_SERVICE = 'putyourlightson\\blitz\\services\\CacheRequestService';
    public const EVENT = 'isCacheableRequest';

    /** @return bool whether a listener was registered */
    public static function install(?string $header, string $serviceClass = self::CACHE_REQUEST_SERVICE): bool
    {
        if ($header === null || $header === '' || !class_exists($serviceClass)) {
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
