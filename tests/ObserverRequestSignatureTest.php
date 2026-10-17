<?php

declare(strict_types=1);

use mojo\observer\security\RequestSignature;
use PHPUnit\Framework\TestCase;

/**
 * Observer signs in TypeScript (packages/jobs/src/craft/signature.ts), the module verifies in PHP.
 * Both test suites pin the same vectors, so a change on either side fails before it ships.
 */
final class ObserverRequestSignatureTest extends TestCase
{
    private const KEY = 'observer-shared-test-vector-key-0123456789';
    private const NOW = 1792000000;
    private const NONCE = '0f1e2d3c4b5a69788796a5b4c3d2e1f0';

    public function testTheSharedVectorForARequest(): void
    {
        self::assertSame(
            '445a0513b14b35436cd1c9bb0c7e6d30f4103f4fe394cf3494194294b9886b64',
            RequestSignature::sign(self::KEY, 'GET', '/actions/observer/api/vitals', self::NOW, self::NONCE, ''),
        );
    }

    public function testTheSharedVectorForADeployMarker(): void
    {
        self::assertSame(
            'ace2a8d14ea8c8a319f716c46d249535f4878a446f7410b3d1639a8bd8586b54',
            RequestSignature::sign(self::KEY, 'POST', 'deploy-marker', self::NOW, self::NONCE, '{"release":"20261015-101500-abc1234","ref":"v5.2.0"}'),
        );
    }

    public function testAValidFreshSignaturePasses(): void
    {
        $signature = RequestSignature::sign(self::KEY, 'GET', '/actions/observer/api/ping', self::NOW, self::NONCE, '');

        self::assertNull(RequestSignature::verify(self::KEY, 'GET', '/actions/observer/api/ping', (string)self::NOW, self::NONCE, $signature, '', self::NOW + 60));
        self::assertNull(RequestSignature::verify(self::KEY, 'get', '/actions/observer/api/ping', (string)self::NOW, strtoupper(self::NONCE), strtoupper($signature), '', self::NOW));
    }

    public function testAnythingChangedIsRefused(): void
    {
        $signature = RequestSignature::sign(self::KEY, 'GET', '/actions/observer/api/ping', self::NOW, self::NONCE, '');
        $verify = static fn (string $method, string $target, string $nonce, string $body, string $key = self::KEY): ?string
            => RequestSignature::verify($key, $method, $target, (string)self::NOW, $nonce, $signature, $body, self::NOW);

        self::assertSame('invalid signature', $verify('GET', '/actions/observer/api/vitals', self::NONCE, ''));
        self::assertSame('invalid signature', $verify('POST', '/actions/observer/api/ping', self::NONCE, ''));
        self::assertSame('invalid signature', $verify('GET', '/actions/observer/api/ping', self::NONCE, 'x'));
        self::assertSame('invalid signature', $verify('GET', '/actions/observer/api/ping', str_repeat('a', 32), ''));
        self::assertSame('invalid signature', $verify('GET', '/actions/observer/api/ping', self::NONCE, '', 'another-key-of-sufficient-length-0123456'));
    }

    public function testStaleFutureAndMalformedInputIsRefused(): void
    {
        $signature = RequestSignature::sign(self::KEY, 'GET', '/x', self::NOW, self::NONCE, '');

        self::assertStringStartsWith('timestamp outside', (string)RequestSignature::verify(self::KEY, 'GET', '/x', (string)self::NOW, self::NONCE, $signature, '', self::NOW + 301));
        self::assertStringStartsWith('timestamp outside', (string)RequestSignature::verify(self::KEY, 'GET', '/x', (string)self::NOW, self::NONCE, $signature, '', self::NOW - 301));
        self::assertSame('malformed timestamp', RequestSignature::verify(self::KEY, 'GET', '/x', '17920e5', self::NONCE, $signature, '', self::NOW));
        self::assertSame('malformed nonce', RequestSignature::verify(self::KEY, 'GET', '/x', (string)self::NOW, 'not-hex', $signature, '', self::NOW));
        self::assertSame(
            'missing X-Observer-Timestamp, X-Observer-Nonce or X-Observer-Signature',
            RequestSignature::verify(self::KEY, 'GET', '/x', (string)self::NOW, '', $signature, '', self::NOW),
        );
    }

    public function testEveryNonceIsFresh(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', RequestSignature::nonce());
        self::assertNotSame(RequestSignature::nonce(), RequestSignature::nonce());
    }
}
