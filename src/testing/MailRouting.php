<?php

declare(strict_types=1);

namespace mojo\observer\testing;

use InvalidArgumentException;

/**
 * Where a test submission's notification goes. `redirect` (the default): to the probe address only,
 * so the client never sees observer's test. `copy`: to the client as configured, with the probe added
 * as Bcc, for a client who wants to see the tests arrive.
 */
final class MailRouting
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @return array{to: list<string>, cc: list<string>, bcc: list<string>}
     */
    public static function route(string $mode, string $probe, array $to, array $cc, array $bcc): array
    {
        return match ($mode) {
            'redirect' => ['to' => [$probe], 'cc' => [], 'bcc' => []],
            'copy' => ['to' => $to, 'cc' => $cc, 'bcc' => in_array($probe, $bcc, true) ? $bcc : [...$bcc, $probe]],
            default => throw new InvalidArgumentException("Unknown mail mode \"$mode\"."),
        };
    }
}
