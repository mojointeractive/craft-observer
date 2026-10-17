<?php

declare(strict_types=1);

use mojo\observer\vitals\Provider;
use mojo\observer\vitals\Vitals;
use PHPUnit\Framework\TestCase;

/**
 * The vitals payload: one provider's failure is reported, never fatal; a provider whose
 * dependency is missing is left out.
 */
final class ObserverVitalsTest extends TestCase
{
    /** @param (callable(): array<string, mixed>)|null $collect */
    private static function provider(string $key, bool $available, ?callable $collect = null): Provider
    {
        return new class($key, $available, $collect) implements Provider {
            /** @param (callable(): array<string, mixed>)|null $collect */
            public function __construct(private string $key, private bool $available, private $collect)
            {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function available(): bool
            {
                return $this->available;
            }

            public function collect(): array
            {
                return ($this->collect)();
            }
        };
    }

    public function testEveryAvailableProviderGetsAnEntry(): void
    {
        $payload = Vitals::collect([
            self::provider('platform', true, static fn (): array => ['craftVersion' => '5.11.5']),
            self::provider('formie', false),
        ], 1792000000);

        self::assertSame(Vitals::API_VERSION, $payload['api']);
        self::assertSame('2026-10-14T17:46:40Z', $payload['collectedAt']);
        self::assertSame(['platform' => ['craftVersion' => '5.11.5']], $payload['providers']);
    }

    public function testAFailingProviderIsReportedAndTheOthersStillAnswer(): void
    {
        $payload = Vitals::collect([
            self::provider('queue', true, static fn (): array => throw new RuntimeException('database gone')),
            self::provider('platform', true, static fn (): array => ['phpVersion' => '8.5.11']),
        ], 1792000000);

        self::assertSame(['error' => 'RuntimeException: database gone'], $payload['providers']['queue']);
        self::assertSame(['phpVersion' => '8.5.11'], $payload['providers']['platform']);
    }
}
