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

    /**
     * What happens to a notification of a submission with this title: an ordinary one is sent as
     * configured, a test's is routed to its probe, and a test's whose routing is gone is held back.
     *
     * @return array{action: 'send'}|array{action: 'hold', run: string}|array{action: 'route', run: string, mail: string, probe: string}
     */
    public function decide(?string $title): array
    {
        $run = self::runOf($title);
        if ($run === null) {
            return ['action' => 'send'];
        }
        $routing = $this->of($run);

        return $routing === null
            ? ['action' => 'hold', 'run' => $run]
            : ['action' => 'route', 'run' => $run, 'mail' => $routing['mail'], 'probe' => $routing['probe']];
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
