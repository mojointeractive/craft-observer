<?php

declare(strict_types=1);

use mojo\observer\testing\TestToken;
use PHPUnit\Framework\TestCase;

/**
 * The token that turns one request into an observer form test. observer's
 * packages/jobs/src/forms/token.test.ts pins the same vector.
 */
final class ObserverTestTokenTest extends TestCase
{
    private const KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private const PAYLOAD = [
        'v' => 1,
        'run' => '0123456789abcdef0123456789abcdef',
        'adapter' => 'formie',
        'form' => 'craftlyContact',
        'exp' => 1792224000,
        'mail' => 'redirect',
        'probe' => 'observer+0123456789abcdef0123456789abcdef@mojo.ch',
    ];

    private const HEADER = 'eyJ2IjoxLCJydW4iOiIwMTIzNDU2Nzg5YWJjZGVmMDEyMzQ1Njc4OWFiY2RlZiIsImFkYXB0ZXIiOiJmb3JtaWUiLCJmb3JtIjoiY3JhZnRseUNvbnRhY3QiLCJleHAiOjE3OTIyMjQwMDAsIm1haWwiOiJyZWRpcmVjdCIsInByb2JlIjoib2JzZXJ2ZXIrMDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWZAbW9qby5jaCJ9.b0fc6df7fb3849fc3a34ffd77ba34de7b5e2fe0eb6229c021fa3b903fbfe0f17';

    public function testItSignsTheSharedVector(): void
    {
        self::assertSame(self::HEADER, TestToken::sign(self::KEY, self::PAYLOAD));
    }

    public function testAValidTokenNamesRunFormAndMail(): void
    {
        $token = TestToken::verify(self::KEY, self::HEADER, 1792223700);

        self::assertInstanceOf(TestToken::class, $token);
        self::assertSame('0123456789abcdef0123456789abcdef', $token->run);
        self::assertSame('formie', $token->adapter);
        self::assertSame('craftlyContact', $token->form);
        self::assertSame(1792224000, $token->expires);
        self::assertSame('redirect', $token->mail);
        self::assertSame('observer+0123456789abcdef0123456789abcdef@mojo.ch', $token->probe);
    }

    public function testItExpires(): void
    {
        self::assertSame('expired', TestToken::verify(self::KEY, self::HEADER, 1792224001));
    }

    public function testItMayNotLiveLongerThanTenMinutes(): void
    {
        self::assertSame('valid for more than 600 seconds', TestToken::verify(self::KEY, self::HEADER, 1792223000));
    }

    public function testAChangedPayloadOrAnotherKeyIsRefused(): void
    {
        [$payload, $signature] = explode('.', self::HEADER);
        $other = TestToken::sign(self::KEY, ['form' => 'other'] + self::PAYLOAD);
        [$otherPayload] = explode('.', $other);

        self::assertSame('invalid signature', TestToken::verify(self::KEY, $otherPayload . '.' . $signature, 1792223700));
        self::assertSame('invalid signature', TestToken::verify(str_repeat('x', 64), self::HEADER, 1792223700));
        self::assertSame('invalid signature', TestToken::verify(self::KEY, $payload . '.' . str_repeat('0', 64), 1792223700));
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'no dot' => ['abc'];
        yield 'empty parts' => ['.'];
        yield 'not base64' => ['***.' . str_repeat('a', 64)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformed')]
    public function testMalformedHeadersAreRefused(string $header): void
    {
        self::assertSame('malformed', TestToken::verify(self::KEY, $header, 1792223700));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function badPayloads(): iterable
    {
        yield 'no run' => [array_diff_key(self::PAYLOAD, ['run' => 1])];
        yield 'run not 32 hex' => [['run' => 'ABC'] + self::PAYLOAD];
        yield 'unknown mail mode' => [['mail' => 'forward'] + self::PAYLOAD];
        yield 'adapter with dots' => [['adapter' => 'for.mie'] + self::PAYLOAD];
        yield 'form missing' => [array_diff_key(self::PAYLOAD, ['form' => 1])];
        yield 'probe not an address' => [['probe' => 'nobody'] + self::PAYLOAD];
        yield 'another version' => [['v' => 2] + self::PAYLOAD];
    }

    /** @param array<string, mixed> $payload */
    #[\PHPUnit\Framework\Attributes\DataProvider('badPayloads')]
    public function testSignedButIncompletePayloadsAreRefused(array $payload): void
    {
        self::assertSame('malformed', TestToken::verify(self::KEY, TestToken::sign(self::KEY, $payload), 1792223700));
    }
}
