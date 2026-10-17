<?php

declare(strict_types=1);

namespace mojo\observer\health;

/**
 * Oh Dear's application-health format, built from the craftly doctor's results, so Oh Dear's own
 * check can watch the same things observer does (https://ohdear.app/docs/features/application-health-monitoring).
 */
final class HealthReport
{
    private const STATUS = ['ok' => 'ok', 'warn' => 'warning', 'error' => 'failed'];

    /**
     * @param list<array{check: string, status: string, message: string}> $doctor
     * @return array{finishedAt: int, checkResults: list<array<string, mixed>>} `meta` is an object, as Oh Dear expects
     */
    public static function fromDoctor(array $doctor, int $now): array
    {
        $results = [];
        foreach ($doctor as $line) {
            $status = self::STATUS[$line['status']] ?? 'crashed';
            $results[] = [
                'name' => self::name($line['check']),
                'label' => ucfirst($line['check']),
                'status' => $status,
                'notificationMessage' => $status === 'ok' ? '' : $line['message'],
                'shortSummary' => $status === 'ok' ? 'ok' : mb_strimwidth($line['message'], 0, 60, '…'),
                'meta' => new \stdClass(),
            ];
        }

        return ['finishedAt' => $now, 'checkResults' => $results];
    }

    /** "project config" → "ProjectConfig", the CamelCase names Oh Dear shows. */
    private static function name(string $check): string
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^a-z0-9]+/i', ' ', $check) ?? $check));
    }
}
