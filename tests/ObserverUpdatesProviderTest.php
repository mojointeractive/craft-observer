<?php

declare(strict_types=1);

use mojo\observer\providers\UpdatesProvider;
use PHPUnit\Framework\TestCase;

/**
 * Craft's own update service swallows a failed request and caches an empty answer, which reads as
 * "everything is current". The provider fails loudly instead, so observer keeps the packages it
 * knows and reports that the update check failed.
 */
final class ObserverUpdatesProviderTest extends TestCase
{
    private const ANSWER = ['cms' => ['status' => 'eligible', 'releases' => []], 'plugins' => []];

    public function testACachedAnswerIsUsedUntilTheNextRefresh(): void
    {
        $data = UpdatesProvider::updateData(
            refresh: false,
            cached: static fn (): mixed => self::ANSWER,
            fetch: static fn (): array => throw new LogicException('must not fetch'),
        );

        self::assertSame(self::ANSWER, $data);
    }

    public function testARefreshAsksCraftsApi(): void
    {
        $fresh = ['cms' => [], 'plugins' => ['formie' => ['status' => 'eligible', 'releases' => []]]];
        $data = UpdatesProvider::updateData(
            refresh: true,
            cached: static fn (): mixed => self::ANSWER,
            fetch: static fn (): array => $fresh,
        );

        self::assertSame($fresh, $data);
    }

    public function testAnUnreachableUpdateServiceIsAnErrorNotAnAllClear(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Craft's update service did not answer: cURL error 28");

        UpdatesProvider::updateData(
            refresh: true,
            cached: static fn (): mixed => false,
            fetch: static fn (): array => throw new RuntimeException('cURL error 28'),
        );
    }

    public function testTheEmptyAnswerCraftCachesAfterAFailureIsNotTrusted(): void
    {
        $this->expectException(RuntimeException::class);

        UpdatesProvider::updateData(
            refresh: false,
            cached: static fn (): mixed => [],
            fetch: static fn (): array => [],
        );
    }
}
