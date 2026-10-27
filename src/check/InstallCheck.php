<?php

declare(strict_types=1);

namespace mojo\observer\check;

use mojo\observer\Settings;

/**
 * What an install's facts (InstallFacts) mean, as the lines `craft observer/check` prints after
 * installing the plugin: what is configured, what observer can watch here, and what it cannot.
 */
final class InstallCheck
{
    /**
     * @param array<string, mixed> $facts
     * @return list<array{subject: string, status: 'ok'|'warn'|'fail', text: string}>
     */
    public static function lines(array $facts): array
    {
        $lines = [];
        $keyLength = (int)($facts['keyLength'] ?? 0);
        $lines[] = match (true) {
            $keyLength === 0 => self::line('key', 'fail', 'OBSERVER_KEY is not set: every endpoint answers 404'),
            $keyLength < Settings::MIN_KEY_LENGTH => self::line('key', 'fail', "OBSERVER_KEY has $keyLength characters, " . Settings::MIN_KEY_LENGTH . ' or more are needed: it is ignored and every endpoint answers 404'),
            default => self::line('key', 'ok', "OBSERVER_KEY is set ($keyLength characters)"),
        };
        $lines[] = ($facts['enabled'] ?? true)
            ? self::line('enabled', 'ok', 'OBSERVER_ENABLED is on')
            : self::line('enabled', 'warn', 'OBSERVER_ENABLED=false: the API answers 404 and no deploy marker is sent');
        $siteId = $facts['siteId'] ?? null;
        $hookUrl = $facts['hookUrl'] ?? null;
        $lines[] = is_string($siteId) && is_string($hookUrl)
            ? self::line('deploy markers', 'ok', "deploy markers go to $hookUrl for site $siteId")
            : self::line('deploy markers', 'warn', 'OBSERVER_SITE_ID or OBSERVER_HOOK_URL is not set: `craft observer/deploy-marker` sends nothing');
        $lines[] = ($facts['healthSecret'] ?? null) !== null
            ? self::line('health secret', 'ok', "OBSERVER_HEALTH_SECRET is set: Oh Dear's application-health check may read `health`")
            : self::line('health secret', 'ok', "OBSERVER_HEALTH_SECRET is not set (optional: it lets Oh Dear's application-health check read `health`)");
        $lines[] = ($facts['doctor'] ?? false)
            ? self::line('doctor', 'ok', 'the craftly doctor is here: its lines join vitals and health')
            : self::line('doctor', 'ok', 'no craftly doctor (not a craftly site): health still reports storage, migrations, project config and the queue');
        $backup = $facts['backup'] ?? null;
        $lines[] = is_string($backup)
            ? self::line('backup', 'ok', "Backup plugin $backup: its targets, runs and retention are reported")
            : self::line('backup', 'warn', 'the Backup plugin (webhubworks/craft-backup) is not installed: observer reports the site as having no backups');
        $formie = $facts['formie'] ?? null;
        $adapter = (bool)($facts['formieAdapter'] ?? false);
        $lines[] = match (true) {
            !is_string($formie) => self::line('formie', 'ok', 'no Formie: no form inventory and no form tests here'),
            !$adapter => self::line('formie', 'warn', "Formie $formie is older than 4.0.0: its forms are not reported and form tests are refused"),
            default => self::line('formie', 'ok', "Formie $formie: its forms are reported and can be tested"),
        };
        $sent = $facts['formieSentNotifications'] ?? null;
        if ($adapter && is_bool($sent)) {
            $lines[] = $sent
                ? self::line('sent notifications', 'ok', 'Formie keeps its sent notifications: form tests can see whether their mail went out')
                : self::line('sent notifications', 'warn', "Formie's sent-notification log is off: form tests cannot see whether their notifications went out");
        }
        $cache = (string)($facts['cache'] ?? 'unknown');
        $lines[] = str_contains($cache, 'FileCache')
            ? self::line('cache', 'ok', "data cache $cache: fine on one host; with queue workers on another host use a shared cache such as Redis (nonces, rate limit and form-test mail routing live in it)")
            : self::line('cache', 'ok', "data cache $cache, shared by web requests and queue workers");
        $blitz = $facts['blitz'] ?? null;
        if (is_string($blitz)) {
            // Blitz's QUERY_STRINGS_CACHE_URLS_AS_SAME_PAGE: a server rewrite onto a file cache then serves the test page from the cache.
            $lines[] = ($facts['blitzQueryStringCaching'] ?? null) === 2
                ? self::line('blitz', 'warn', "Blitz $blitz caches URLs with query strings as the same page (queryStringCaching = 2): a file cache served by a server rewrite answers observer's form-test page (?observer-run=…) from the cache, captchas included; set queryStringCaching to 0 or 1")
                : self::line('blitz', 'ok', "Blitz $blitz: Craft serves a form-test request live and does not store it");
        }

        return $lines;
    }

    /** @param list<array{subject: string, status: string, text: string}> $lines */
    public static function exitCode(array $lines): int
    {
        foreach ($lines as $line) {
            if ($line['status'] !== 'ok') {
                return 1;
            }
        }

        return 0;
    }

    /** @return array{subject: string, status: 'ok'|'warn'|'fail', text: string} */
    private static function line(string $subject, string $status, string $text): array
    {
        /** @var 'ok'|'warn'|'fail' $status */
        return ['subject' => $subject, 'status' => $status, 'text' => $text];
    }
}
