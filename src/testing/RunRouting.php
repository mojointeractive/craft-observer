<?php

declare(strict_types=1);

namespace mojo\observer\testing;

use yii\caching\CacheInterface;

/**
 * Where a run's notifications go (its token's mail mode and probe address), kept from the test request
 * for the queue jobs that send them later. In the cache, not on the submission: a fresh install of
 * Formie 4.0.0-beta.16 has no column for submission metadata and drops it without a word. A test
 * submission is known by its title alone; one whose routing is gone is mailed to nobody.
 */
final class RunRouting
{
    /** Longer than any run waits for its mail (60 min) and for a delayed queue. */
    public const SECONDS = 86400;

    public const TITLE_PREFIX = 'observer:';

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function remember(string $run, string $mail, string $probe): void
    {
        $this->cache->set(self::key($run), ['mail' => $mail, 'probe' => $probe], self::SECONDS);
    }

    /** @return array{mail: string, probe: string}|null */
    public function of(string $run): ?array
    {
        $routing = $this->cache->get(self::key($run));

        return is_array($routing) && is_string($routing['mail'] ?? null) && is_string($routing['probe'] ?? null)
            ? ['mail' => $routing['mail'], 'probe' => $routing['probe']]
            : null;
    }

    /** The title a run's submission gets. */
    public static function title(string $run): string
    {
        return self::TITLE_PREFIX . $run;
    }

    /** The run a submission title names, or null for every title a run did not set. */
    public static function runOf(?string $title): ?string
    {
        return $title !== null && preg_match('/^observer:([0-9a-f]{32})$/', $title, $match) === 1 ? $match[1] : null;
    }

    private static function key(string $run): string
    {
        return 'observer:run:' . $run;
    }
}
