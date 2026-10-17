<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use craft\helpers\App;
use craft\models\Update;
use mojo\observer\vitals\Provider;
use RuntimeException;
use Throwable;

/**
 * Craft's own update check, refreshed at most once an hour (Craft keeps a successful answer for a
 * day), so a critical release reaches observer within about an hour of the next sync. A failed check
 * is an error, never an all-clear: observer then keeps the packages it knows.
 */
final class UpdatesProvider implements Provider
{
    private const REFRESHED_KEY = 'observer:updates-refreshed';

    public function key(): string
    {
        return 'updates';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $cache = $app->getCache();
        $refresh = $cache->get(self::REFRESHED_KEY) === false;
        $updatesService = $app->getUpdates();
        $updates = $updatesService->cacheUpdates(self::updateData(
            $refresh,
            static fn (): mixed => $cache->get($updatesService->cacheKey),
            static fn (): array => $app->getApi()->getUpdates(),
        ));
        if ($refresh) {
            $cache->set(self::REFRESHED_KEY, time(), 3600);
        }

        $licenseInfo = $cache->get(App::CACHE_KEY_LICENSE_INFO);
        $packages = [self::package(
            'craft',
            'Craft CMS',
            $app->getVersion(),
            $updates->cms,
            is_array($licenseInfo) ? (string)($licenseInfo['craft']['status'] ?? 'unknown') : 'unknown',
            'https://github.com/craftcms/cms/blob/5.x/CHANGELOG.md',
        )];

        $plugins = $app->getPlugins();
        foreach ($plugins->getAllPluginInfo() as $handle => $info) {
            if (!$info['isInstalled']) {
                continue;
            }
            $packages[] = self::package(
                $handle,
                (string)$info['name'],
                (string)$info['version'],
                $updates->plugins[$handle] ?? null,
                $plugins->getPluginLicenseKeyStatus($handle)->value,
                $info['changelogUrl'] ?? "https://plugins.craftcms.com/$handle/changelog",
            ) + ['enabled' => (bool)$info['isEnabled']];
        }

        return ['refreshed' => $refresh, 'packages' => $packages];
    }

    /**
     * The answer of Craft's update API: the cached one unless a refresh is due. Craft's own
     * getUpdates() turns a failed request into an empty answer (and caches it for five minutes),
     * which would report every package as current; here a failure throws, so Vitals reports it.
     *
     * @param callable(): mixed $cached
     * @param callable(): array<string, mixed> $fetch
     * @return array<string, mixed>
     */
    public static function updateData(bool $refresh, callable $cached, callable $fetch): array
    {
        if (!$refresh) {
            $data = $cached();
            if (is_array($data) && $data !== []) {
                return $data;
            }
        }

        try {
            $data = $fetch();
        } catch (Throwable $e) {
            throw new RuntimeException("Craft's update service did not answer: {$e->getMessage()}", 0, $e);
        }
        if ($data === []) {
            throw new RuntimeException("Craft's update service answered without update data");
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function package(string $handle, string $name, string $installed, ?Update $update, string $license, ?string $changelogUrl): array
    {
        $latest = $update?->getLatest();

        return [
            'handle' => $handle,
            'name' => $name,
            'installed' => $installed,
            'latest' => $latest->version ?? $installed,
            'latestDate' => $latest?->date?->format('Y-m-d'),
            'pendingReleases' => $update !== null ? count($update->releases) : 0,
            'critical' => $update?->getHasCritical() ?? false,
            'updateStatus' => $update->status ?? Update::STATUS_ELIGIBLE,
            'abandoned' => $update->abandoned ?? false,
            'replacement' => $update?->replacementName,
            'license' => $license,
            'changelogUrl' => $changelogUrl,
        ];
    }
}
