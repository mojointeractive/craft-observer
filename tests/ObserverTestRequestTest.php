<?php

declare(strict_types=1);

use mojo\observer\testing\TestRequest;
use mojo\observer\testing\TestToken;
use PHPUnit\Framework\TestCase;

/** A request is a form test only with a valid X-Observer-Test header; a bad one is refused, none is ignored. */
final class ObserverTestRequestTest extends TestCase
{
    private const KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private static function header(int $expires): string
    {
        return TestToken::sign(self::KEY, [
            'v' => 1,
            'run' => '0123456789abcdef0123456789abcdef',
            'adapter' => 'formie',
            'form' => 'craftlyContact',
            'exp' => $expires,
            'mail' => 'redirect',
            'probe' => 'observer+0123456789abcdef0123456789abcdef@mojo.ch',
        ]);
    }

    public function testWithoutTheHeaderItIsAnOrdinaryRequest(): void
    {
        self::assertNull(TestRequest::fromHeader(null, self::KEY, 1000));
        self::assertNull(TestRequest::fromHeader('', self::KEY, 1000));
    }

    public function testAValidHeaderIsATest(): void
    {
        $token = TestRequest::fromHeader(self::header(1300), self::KEY, 1000);

        self::assertInstanceOf(TestToken::class, $token);
        self::assertSame('craftlyContact', $token->form);
    }

    public function testAnExpiredHeaderSaysWhy(): void
    {
        self::assertSame('expired', TestRequest::fromHeader(self::header(900), self::KEY, 1000));
    }

    public function testAHeaderOnASiteWithoutAKeyIsRefused(): void
    {
        self::assertSame('not configured', TestRequest::fromHeader(self::header(1300), null, 1000));
    }
}
