<?php

declare(strict_types=1);

use mojo\observer\providers\CraftlyDoctorProvider;
use PHPUnit\Framework\TestCase;

/** The craftly doctor's lines minus the checks the plugin reports itself on every site. */
final class ObserverCraftlyDoctorProviderTest extends TestCase
{
    public function testTheChecksThePluginCoversAreDropped(): void
    {
        $line = static fn (string $check): array => ['check' => $check, 'status' => 'ok', 'message' => 'm'];
        $kept = CraftlyDoctorProvider::withoutCovered([
            $line('redis'), $line('storage'), $line('migrations'), $line('project config'), $line('singles'), $line('queue'),
        ]);

        self::assertSame(['redis', 'singles'], array_column($kept, 'check'));
        self::assertSame([0, 1], array_keys($kept));
    }

    public function testUnavailableWithoutTheDoctorClass(): void
    {
        self::assertFalse((new CraftlyDoctorProvider('modules\\nowhere\\Doctor'))->available());
    }
}
