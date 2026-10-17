<?php

declare(strict_types=1);

namespace mojo\observer\security;

/**
 * Patrol's maintenance mode answers every site request with /offline. observer's own action paths
 * pass, so `ping` can report the maintenance; the API still checks every signature itself. Only the
 * path counts: the same text in a query string or under another path stays in maintenance.
 */
final class MaintenanceBypass
{
    public static function applies(string $requestUri): bool
    {
        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/actions/observer/api/[a-z-]+$#', $path) === 1;
    }
}
