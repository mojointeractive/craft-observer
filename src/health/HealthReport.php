<?php

declare(strict_types=1);

namespace mojo\observer\health;

/**
 * Oh Dear's application-health format (https://ohdear.app/docs/features/application-health-monitoring):
 * the plugin's own checks from the system and queue entries (storage, migrations, project config,
 * queue: the thresholds craftly's doctor uses), then the craftly doctor's lines where there is one.
 */
final class HealthReport
{
    private const STATUS = ['ok' => 'ok', 'warn' => 'warning', 'error' => 'failed'];
    private const MB = 1048576;
    private const STORAGE_ERROR_MB = 200;
    private const STORAGE_WARN_MB = 1024;
    private const QUEUE_STALE_SECONDS = 3600;

    /**
     * @param list<array{check: string, status: string, message: string}> $doctor
     * @param array<string, mixed>|null $system the system provider's entry
     * @param array<string, mixed>|null $queue the queue provider's entry
     * @return array{finishedAt: int, checkResults: list<array<string, mixed>>} `meta` is an object, as Oh Dear expects
     */
    public static function build(array $doctor, ?array $system, ?array $queue, int $now): array
    {
        $results = [];
        if ($system !== null) {
            $results[] = self::result('Storage', 'Storage', ...self::storage($system['storage'] ?? []));
            $results[] = self::result('Migrations', 'Migrations', ...self::migrations($system['migrations'] ?? []));
            $results[] = self::result('ProjectConfig', 'Project config', ...self::projectConfig((bool)($system['projectConfigPending'] ?? false)));
        }
        if ($queue !== null) {
            $results[] = self::result('Queue', 'Queue', ...self::queue($queue));
        }
        foreach ($doctor as $line) {
            $results[] = self::result(self::name($line['check']), ucfirst($line['check']), self::STATUS[$line['status']] ?? 'crashed', $line['message']);
        }

        return ['finishedAt' => $now, 'checkResults' => $results];
    }

    /**
     * @param array<string, mixed> $storage
     * @return array{string, string} status and message
     */
    private static function storage(array $storage): array
    {
        $free = $storage['freeBytes'] ?? null;
        if (!is_int($free)) {
            return ['ok', 'free space unknown'];
        }
        $mb = intdiv($free, self::MB);
        if ($mb < self::STORAGE_ERROR_MB) {
            return ['failed', "only {$mb} MB free under storage/ — uploads and backups will fail"];
        }

        return $mb < self::STORAGE_WARN_MB ? ['warning', "{$mb} MB free under storage/"] : ['ok', "{$mb} MB free"];
    }

    /**
     * @param array<string, mixed> $migrations
     * @return array{string, string}
     */
    private static function migrations(array $migrations): array
    {
        $plugins = (array)($migrations['plugins'] ?? []);
        $content = (array)($migrations['content'] ?? []);
        if (($migrations['craft'] ?? false) === true || $plugins !== []) {
            return ['failed', 'Craft or plugin migrations are pending — craft up'];
        }
        if ($content !== []) {
            return ['failed', count($content) . ' content migration(s) pending — craft up: ' . implode(', ', $content)];
        }

        return ['ok', 'none pending'];
    }

    /** @return array{string, string} */
    private static function projectConfig(bool $pending): array
    {
        return $pending ? ['warning', 'the yaml and the database differ — craft up'] : ['ok', 'in sync'];
    }

    /**
     * @param array<string, mixed> $queue
     * @return array{string, string}
     */
    private static function queue(array $queue): array
    {
        $oldest = $queue['oldestPendingAgeSeconds'] ?? null;
        if (is_int($oldest) && $oldest > self::QUEUE_STALE_SECONDS) {
            $waited = intdiv($oldest, 3600) . ' h ' . intdiv($oldest % 3600, 60) . ' min';
            return ['failed', "a job has waited $waited — no queue runner is working the queue off"];
        }
        $failed = (int)($queue['failed'] ?? 0);

        return $failed > 0 ? ['warning', "$failed failed job(s) — CP → Utilities → Queue Manager"] : ['ok', 'nothing stuck'];
    }

    /** @return array<string, mixed> */
    private static function result(string $name, string $label, string $status, string $message): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'status' => $status,
            'notificationMessage' => $status === 'ok' ? '' : $message,
            'shortSummary' => $status === 'ok' ? ($message === '' ? 'ok' : $message) : mb_strimwidth($message, 0, 60, '…'),
            'meta' => new \stdClass(),
        ];
    }

    /** "project config" → "ProjectConfig", the CamelCase names Oh Dear shows. */
    private static function name(string $check): string
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^a-z0-9]+/i', ' ', $check) ?? $check));
    }
}
