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

    /**
     * `start` the test when its adapter is here; `refuse` a header that does not verify or names an
     * adapter that is not (a form of another plugin version would submit like a visitor's).
     *
     * @param list<string> $adapters the available adapters' handles
     * @return 'none'|'start'|'refuse'
     */
    public static function decide(TestToken|string|null $test, array $adapters): string
    {
        if ($test === null) {
            return 'none';
        }

        return $test instanceof TestToken && in_array($test->adapter, $adapters, true) ? 'start' : 'refuse';
    }
}
