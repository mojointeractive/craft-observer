<?php

declare(strict_types=1);

use mojo\observer\security\NonceStore;
use mojo\observer\security\RequestAuthenticator;
use mojo\observer\security\RequestSignature;
use PHPUnit\Framework\TestCase;

/**
 * A captured request cannot be played back, and a leaked key cannot hammer the site.
 */
final class ObserverRequestAuthenticatorTest extends TestCase
{
    private const KEY = 'observer-shared-test-vector-key-0123456789';
    private const NOW = 1792000020;

    private static function store(): NonceStore
    {
        return new class() implements NonceStore {
            /** @var array<string, int> */
            private array $values = [];

            public function remember(string $id, int $seconds): bool
            {
                if (isset($this->values[$id])) {
                    return false;
                }
                $this->values[$id] = 1;

                return true;
            }

            public function increment(string $id, int $seconds): int
            {
                return $this->values[$id] = ($this->values[$id] ?? 0) + 1;
            }
        };
    }

    /** @return array{0: string, 1: string} nonce and signature */
    private static function signed(string $target = '/actions/observer/api/ping'): array
    {
        $nonce = RequestSignature::nonce();

        return [$nonce, RequestSignature::sign(self::KEY, 'GET', $target, self::NOW, $nonce, '')];
    }

    public function testASignedRequestPassesOnceAndItsReplayIsRefused(): void
    {
        $auth = new RequestAuthenticator(self::KEY, self::store());
        [$nonce, $signature] = self::signed();

        self::assertNull($auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, $signature, self::NOW));
        self::assertSame('replayed request', $auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, $signature, self::NOW));
    }

    public function testTwoRequestsInTheSameSecondBothPass(): void
    {
        $auth = new RequestAuthenticator(self::KEY, self::store());
        [$firstNonce, $first] = self::signed();
        [$secondNonce, $second] = self::signed();

        self::assertNull($auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $firstNonce, $first, self::NOW));
        self::assertNull($auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $secondNonce, $second, self::NOW));
    }

    public function testABadSignatureDoesNotUseUpItsNonce(): void
    {
        $auth = new RequestAuthenticator(self::KEY, self::store());
        [$nonce, $signature] = self::signed();

        self::assertSame('invalid signature', $auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, str_repeat('0', 64), self::NOW));
        self::assertNull($auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, $signature, self::NOW));
    }

    public function testTheSixtyFirstRequestInAMinuteIsRefused(): void
    {
        $auth = new RequestAuthenticator(self::KEY, self::store());
        for ($i = 0; $i < RequestAuthenticator::RATE_LIMIT; $i++) {
            [$nonce, $signature] = self::signed();
            self::assertNull($auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, $signature, self::NOW));
        }
        [$nonce, $signature] = self::signed();

        self::assertSame(
            'rate limit reached, retry next minute',
            $auth->authenticate('GET', '/actions/observer/api/ping', '', (string)self::NOW, $nonce, $signature, self::NOW),
        );
    }
}
