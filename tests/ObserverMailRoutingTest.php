<?php

declare(strict_types=1);

use mojo\observer\testing\MailRouting;
use PHPUnit\Framework\TestCase;

/** Where a test submission's notification goes: only to the probe, or to the client with the probe in Bcc. */
final class ObserverMailRoutingTest extends TestCase
{
    private const PROBE = 'observer+0123456789abcdef0123456789abcdef@mojo.ch';

    public function testRedirectSendsToTheProbeOnly(): void
    {
        self::assertSame(
            ['to' => [self::PROBE], 'cc' => [], 'bcc' => []],
            MailRouting::route('redirect', self::PROBE, ['office@client.ch'], ['boss@client.ch'], ['archive@client.ch']),
        );
    }

    public function testCopyKeepsTheRecipientsAndAddsTheProbeOnceInBcc(): void
    {
        self::assertSame(
            ['to' => ['office@client.ch'], 'cc' => ['boss@client.ch'], 'bcc' => ['archive@client.ch', self::PROBE]],
            MailRouting::route('copy', self::PROBE, ['office@client.ch'], ['boss@client.ch'], ['archive@client.ch']),
        );
        self::assertSame(
            ['to' => ['office@client.ch'], 'cc' => [], 'bcc' => [self::PROBE]],
            MailRouting::route('copy', self::PROBE, ['office@client.ch'], [], [self::PROBE]),
        );
    }

    public function testAnUnknownModeIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MailRouting::route('forward', self::PROBE, [], [], []);
    }
}
