<?php

declare(strict_types=1);

namespace mojo\observer\deploy;

use mojo\observer\security\RequestSignature;
use mojo\observer\Settings;

/**
 * The request `craft observer/deploy-marker` sends: which release went live, signed with the
 * site's key so observer's deploy-hook function can tell it really came from this site.
 */
final class DeployMarker
{
    public const TARGET = 'deploy-marker';

    /**
     * @return array{url: string, headers: array<string, string>, body: string}
     */
    public static function request(Settings $settings, string $release, ?string $ref, int $now, string $nonce): array
    {
        if (!$settings->canReportDeploys() || $settings->key === null || $settings->hookUrl === null || $settings->siteId === null) {
            throw new \LogicException('OBSERVER_KEY, OBSERVER_SITE_ID and OBSERVER_HOOK_URL must be set to report deploys.');
        }
        $body = (string)json_encode(['release' => $release, 'ref' => $ref], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'url' => $settings->hookUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Observer-Site' => $settings->siteId,
                'X-Observer-Timestamp' => (string)$now,
                'X-Observer-Nonce' => $nonce,
                'X-Observer-Signature' => RequestSignature::sign($settings->key, 'POST', self::TARGET, $now, $nonce, $body),
            ],
            'body' => $body,
        ];
    }

    /**
     * The release name of an atomic deploy: the directory `current` points at
     * (`releases/20261015-101500-abc1234`). A checkout outside `releases/` has none.
     */
    public static function releaseFromPath(string $root): ?string
    {
        $resolved = realpath($root) ?: $root;

        return basename(dirname($resolved)) === 'releases' ? basename($resolved) : null;
    }
}
