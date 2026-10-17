<?php

declare(strict_types=1);

namespace mojo\observer\security;

/**
 * Lets a request through when it carries a fresh, valid signature whose nonce has not been seen
 * before, and the key has made fewer than RATE_LIMIT requests this minute.
 */
final class RequestAuthenticator
{
    public const RATE_LIMIT = 60;

    public function __construct(
        private readonly string $key,
        private readonly NonceStore $store,
    ) {
    }

    /**
     * Null when the request may proceed; otherwise the reason it may not.
     */
    public function authenticate(
        string $method,
        string $target,
        string $body,
        string $timestamp,
        string $nonce,
        string $signature,
        int $now,
    ): ?string {
        $problem = RequestSignature::verify($this->key, $method, $target, $timestamp, $nonce, $signature, $body, $now);
        if ($problem !== null) {
            return $problem;
        }
        if (!$this->store->remember(strtolower($nonce), 2 * RequestSignature::MAX_SKEW_SECONDS)) {
            return 'replayed request';
        }
        if ($this->store->increment('minute:' . intdiv($now, 60), 120) > self::RATE_LIMIT) {
            return 'rate limit reached, retry next minute';
        }

        return null;
    }
}
