<?php

declare(strict_types=1);

namespace mojo\observer\backups;

use Throwable;
use webhubworks\backup\services\BackupGrouper;

/**
 * What observer learns about a site's backups, from craft-backup's config, its run state and a
 * listing of each target. Each target keeps only its name and driver: hosts, users, passwords,
 * keys and paths stay on the site.
 */
final class BackupsReport
{
    /** At most this many backups per target, newest first. */
    public const MAX_BACKUPS = 1000;

    /**
     * @param array<string, array<string, mixed>> $targets craft-backup's `targets`, name ⇒ driver definition
     * @param array<string, mixed> $retention craft-backup's `retention`
     * @param array<int, mixed> $rules craft-backup's `monitor_backups`
     * @param array{lastRun: ?array<string, mixed>, lastSuccessfulRun: ?array<string, mixed>} $runState RunStateStore::read()
     * @param callable(string, array<string, mixed>): array{backups: list<array{path: string, size: int, modified: int}>, disk: ?array{total: int, free: int}} $inspect
     *        lists one target; may throw when the target cannot be reached
     * @return array<string, mixed>
     */
    public static function build(array $targets, array $retention, array $rules, array $runState, callable $inspect, int $now): array
    {
        $out = [];
        foreach ($targets as $name => $def) {
            $name = (string)$name;
            $driver = is_string($def['driver'] ?? null) ? $def['driver'] : null;
            try {
                $listing = $inspect($name, $def);
                $out[] = [
                    'name' => $name,
                    'driver' => $driver,
                    'reachable' => true,
                    'error' => null,
                    'backups' => self::backups($listing['backups']),
                    'disk' => $listing['disk'],
                ];
            } catch (Throwable $e) {
                $out[] = ['name' => $name, 'driver' => $driver, 'reachable' => false, 'error' => self::redact($e->getMessage(), $targets), 'backups' => [], 'disk' => null];
            }
        }

        return [
            'collectedAt' => self::iso($now),
            'configError' => null,
            'targets' => $out,
            'retention' => self::retention($retention),
            'rules' => self::rules($rules),
        ] + self::runs($runState, $targets);
    }

    /**
     * craft-backup's config does not load: no targets to list, the run state still says what happened last.
     *
     * @param array{lastRun: ?array<string, mixed>, lastSuccessfulRun: ?array<string, mixed>} $runState
     * @return array<string, mixed>
     */
    public static function failed(string $configError, array $runState, int $now): array
    {
        return [
            'collectedAt' => self::iso($now),
            'configError' => self::redact($configError, []),
            'targets' => [],
            'retention' => null,
            'rules' => [],
        ] + self::runs($runState);
    }

    /**
     * @param list<array{path: string, size: int, modified: int}> $files
     * @return list<array{at: string, bytes: int}>
     */
    private static function backups(array $files): array
    {
        $groups = BackupGrouper::group($files);
        usort($groups, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return array_map(
            static fn (array $group): array => ['at' => self::iso($group['modified']), 'bytes' => $group['size']],
            array_slice($groups, 0, self::MAX_BACKUPS),
        );
    }

    /**
     * @param array<string, mixed> $retention
     * @return array{keepAllForDays: int, keepDailyForDays: int, keepWeeklyForWeeks: int, keepMonthlyForMonths: int, keepYearlyForYears: int, sizeCapMb: ?int}
     */
    private static function retention(array $retention): array
    {
        $cap = $retention['delete_oldest_backups_when_using_more_megabytes_than'] ?? null;

        return [
            'keepAllForDays' => (int)($retention['keep_all_for_days'] ?? 0),
            'keepDailyForDays' => (int)($retention['keep_daily_for_days'] ?? 0),
            'keepWeeklyForWeeks' => (int)($retention['keep_weekly_for_weeks'] ?? 0),
            'keepMonthlyForMonths' => (int)($retention['keep_monthly_for_months'] ?? 0),
            'keepYearlyForYears' => (int)($retention['keep_yearly_for_years'] ?? 0),
            'sizeCapMb' => is_numeric($cap) ? (int)$cap : null,
        ];
    }

    /**
     * @param array<int, mixed> $rules
     * @return list<array{target: string, minNumberOfBackups: ?int, youngestWithin: ?string}>
     */
    private static function rules(array $rules): array
    {
        $out = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['target'] ?? null)) {
                continue;
            }
            $min = $rule['min_number_of_backups'] ?? null;
            $youngest = $rule['youngest_backup_should_be_within_the_last'] ?? null;
            $out[] = [
                'target' => $rule['target'],
                'minNumberOfBackups' => is_int($min) ? $min : null,
                'youngestWithin' => is_string($youngest) ? $youngest : null,
            ];
        }

        return $out;
    }

    /**
     * Messages from craft-backup and its SFTP library name hosts, users and paths: none of them leaves the site.
     *
     * @param array<string, array<string, mixed>> $targets
     */
    private static function redact(string $message, array $targets): string
    {
        $replace = [];
        foreach ($targets as $def) {
            foreach (['host' => '[host]', 'username' => '[user]'] as $key => $placeholder) {
                if (is_string($def[$key] ?? null) && $def[$key] !== '') {
                    $replace[$def[$key]] = $placeholder;
                }
            }
        }
        // Longest first, so a host that contains another value is replaced whole.
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $message = strtr($message, $replace);

        // Absolute paths, roots included: from a slash not after a word character or a colon, to the next space or quote.
        return (string)preg_replace('#(?<![\w:])/[^\s\'"]+#', '[path]', $message);
    }

    /**
     * @param array{lastRun: ?array<string, mixed>, lastSuccessfulRun: ?array<string, mixed>} $runState
     * @param array<string, array<string, mixed>> $targets
     * @return array{lastRun: ?array<string, mixed>, lastSuccessfulRunAt: ?string}
     */
    private static function runs(array $runState, array $targets = []): array
    {
        $last = $runState['lastRun'];
        $success = $runState['lastSuccessfulRun'];

        return [
            'lastRun' => is_int($last['at'] ?? null) ? [
                'at' => self::iso($last['at']),
                'status' => ($last['status'] ?? null) === 'ok' ? 'ok' : 'failed',
                'errors' => array_values(array_map(static fn ($error): string => self::redact((string)$error, $targets), (array)($last['errors'] ?? []))),
                'targetStatuses' => array_map(static fn ($status): string => self::redact((string)$status, $targets), (array)($last['targetStatuses'] ?? [])),
            ] : null,
            'lastSuccessfulRunAt' => is_int($success['at'] ?? null) ? self::iso($success['at']) : null,
        ];
    }

    private static function iso(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
