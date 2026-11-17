<?php

declare(strict_types=1);

namespace mojo\observer\security;

use Closure;

/**
 * Whether the site is in maintenance, for `ping` and `vitals`: observer holds its alerts back while
 * it lasts.
 *
 * The plugin does not know how a site switches maintenance on. Whatever does tells it here
 * (`MaintenanceState::$resolver = static fn (): bool => …;`); without a resolver the answer is the
 * `MAINTENANCE` environment variable.
 */
final class MaintenanceState
{
    /** @var (Closure(): bool)|null */
    public static ?Closure $resolver = null;

    public static function active(?bool $environment): bool
    {
        if (self::$resolver !== null) {
            try {
                return (bool)(self::$resolver)();
            } catch (\Throwable) {
                // A resolver that cannot answer must not take the API down with it.
            }
        }

        return $environment ?? false;
    }
}
