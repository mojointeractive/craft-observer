<?php

declare(strict_types=1);

use mojo\observer\providers\SystemProvider;
use PHPUnit\Framework\TestCase;

/** The storage figures of the system entry: bytes as integers, null where PHP cannot tell. */
final class ObserverSystemProviderTest extends TestCase
{
    public function testUnknownSpaceIsNull(): void
    {
        self::assertSame(
            ['path' => '/srv/storage', 'freeBytes' => null, 'totalBytes' => null],
            SystemProvider::storage('/srv/storage', false, false),
        );
    }

    public function testSpaceIsReportedInWholeBytes(): void
    {
        self::assertSame(
            ['path' => '/srv/storage', 'freeBytes' => 500000000, 'totalBytes' => 1000000000],
            SystemProvider::storage('/srv/storage', 5.0e8, 1.0e9),
        );
    }
}
