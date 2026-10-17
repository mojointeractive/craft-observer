<?php

declare(strict_types=1);

namespace mojo\observer\testing;

/** Whether this request is one of observer's form tests, judged by its X-Observer-Test header. */
final class TestRequest
{
    public const HEADER = 'X-Observer-Test';

    /**
     * Null for an ordinary request (no header); the token for a valid test; otherwise the reason the
     * header is refused, and the request then submits nothing.
     */
    public static function fromHeader(?string $header, ?string $key, int $now): TestToken|string|null
    {
        if ($header === null || $header === '') {
            return null;
        }
        if ($key === null) {
            return 'not configured';
        }

        return TestToken::verify($key, $header, $now);
    }
}
