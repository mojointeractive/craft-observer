<?php

declare(strict_types=1);

namespace mojo\observer\vitals;

use Throwable;

/**
 * Assembles the vitals payload from providers. One provider failing never costs the others: its
 * entry becomes `{"error": "<class>: <message>"}` and observer turns that into a warning.
 */
final class Vitals
{
    /** Bumped only for changes observer cannot read; additions keep it. */
    public const API_VERSION = 1;

    /**
     * @param iterable<Provider> $providers
     * @return array{api: int, collectedAt: string, providers: array<string, array<string, mixed>>}
     */
    public static function collect(iterable $providers, int $now): array
    {
        $out = [];
        foreach ($providers as $provider) {
            try {
                if (!$provider->available()) {
                    continue;
                }
                $out[$provider->key()] = $provider->collect();
            } catch (Throwable $e) {
                $out[$provider->key()] = ['error' => get_class($e) . ': ' . $e->getMessage()];
            }
        }

        return [
            'api' => self::API_VERSION,
            'collectedAt' => gmdate('Y-m-d\TH:i:s\Z', $now),
            'providers' => $out,
        ];
    }
}
