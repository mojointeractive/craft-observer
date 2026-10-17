<?php

declare(strict_types=1);

namespace mojo\observer\security;

/**
 * What the authenticator needs to remember between requests. CacheNonceStore keeps it in Craft's
 * cache; the tests use an array.
 */
interface NonceStore
{
    /** Remembers $id for $seconds; false when it was already remembered (a replay). */
    public function remember(string $id, int $seconds): bool;

    /** Adds one to the counter $id, which expires after $seconds, and returns the new count. */
    public function increment(string $id, int $seconds): int;
}
