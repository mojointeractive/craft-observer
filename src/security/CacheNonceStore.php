<?php

declare(strict_types=1);

namespace mojo\observer\security;

use yii\caching\CacheInterface;

final class CacheNonceStore implements NonceStore
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function remember(string $id, int $seconds): bool
    {
        return $this->cache->add('observer:nonce:' . $id, 1, $seconds);
    }

    public function increment(string $id, int $seconds): int
    {
        $key = 'observer:count:' . $id;
        $count = (int)($this->cache->get($key) ?: 0) + 1;
        $this->cache->set($key, $count, $seconds);

        return $count;
    }
}
