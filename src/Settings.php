<?php

declare(strict_types=1);

namespace mojo\observer;

/**
 * The connector's configuration, read from the environment only (docs/observer.md).
 *
 * OBSERVER_KEY is the switch: without a key of at least 32 characters the API answers 404, as if
 * the module were not installed. OBSERVER_ENABLED=false turns it off with the key still set.
 * OBSERVER_SITE_ID and OBSERVER_HOOK_URL let `craft observer/deploy-marker` report deploys;
 * OBSERVER_HEALTH_SECRET lets Oh Dear read the health report, which it cannot sign.
 */
final class Settings
{
    public const MIN_KEY_LENGTH = 32;

    public function __construct(
        public readonly ?string $key,
        public readonly bool $enabled,
        public readonly ?string $siteId,
        public readonly ?string $hookUrl,
        public readonly ?string $healthSecret,
    ) {
    }

    /**
     * @param callable(string): (string|null) $env returns a variable's value, null when unset or empty
     */
    public static function fromEnv(callable $env): self
    {
        $key = self::value($env('OBSERVER_KEY'));
        $enabled = strtolower((string)($env('OBSERVER_ENABLED') ?? 'true'));

        return new self(
            key: $key !== null && strlen($key) >= self::MIN_KEY_LENGTH ? $key : null,
            enabled: !in_array($enabled, ['false', '0', 'no', 'off'], true),
            siteId: self::value($env('OBSERVER_SITE_ID')),
            hookUrl: self::value($env('OBSERVER_HOOK_URL')),
            healthSecret: self::value($env('OBSERVER_HEALTH_SECRET')),
        );
    }

    /**
     * An environment value as text. Craft's App::env() turns "false" into false and digits into
     * numbers; this turns them back, so OBSERVER_ENABLED=false keeps its meaning.
     */
    public static function envString(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string)$value : null;
    }

    /** True when the API answers at all. */
    public function apiIsOn(): bool
    {
        return $this->enabled && $this->key !== null;
    }

    /** True when `craft observer/deploy-marker` has everything it needs. */
    public function canReportDeploys(): bool
    {
        return $this->apiIsOn() && $this->siteId !== null && $this->hookUrl !== null;
    }

    private static function value(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
