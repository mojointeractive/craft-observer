<?php

declare(strict_types=1);

namespace mojo\observer\console\controllers;

use Craft;
use craft\console\Controller;
use mojo\observer\check\InstallCheck;
use mojo\observer\check\InstallFacts;
use mojo\observer\Observer;
use mojo\observer\security\RequestSignature;
use Throwable;

/**
 * `craft observer/check [--ping] [--url=<base url>]` — what observer will find on this install:
 * the configuration, which plugins it can report on, and (with --ping) whether a signed request
 * to `ping` gets through the web server. Exits 1 when a line is not ok.
 */
class CheckController extends Controller
{
    /** @var bool Also send a signed ping to this site over HTTP. */
    public bool $ping = false;

    /** @var string|null The base URL to ping; defaults to the primary site's. */
    public ?string $url = null;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'ping', 'url'];
    }

    public function actionIndex(): int
    {
        $lines = InstallCheck::lines(InstallFacts::collect());
        if ($this->ping) {
            $lines[] = $this->pingLine();
        }
        foreach ($lines as $line) {
            $mark = match ($line['status']) {
                'ok' => '  ok   ',
                'warn' => '  warn ',
                default => '  FAIL ',
            };
            $this->stdout("$mark{$line['subject']}: {$line['text']}\n");
        }
        $code = InstallCheck::exitCode($lines);
        $this->stdout($code === 0 ? "observer: ready\n" : "observer: see the lines above\n");

        return $code;
    }

    /** @return array{subject: string, status: 'ok'|'warn'|'fail', text: string} */
    private function pingLine(): array
    {
        $settings = Observer::settings();
        if ($settings->key === null) {
            return ['subject' => 'ping', 'status' => 'fail', 'text' => 'no key to sign with'];
        }
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $base = rtrim($this->url ?? (string)$app->getSites()->getPrimarySite()->getBaseUrl(), '/');
        $url = $base . '/actions/observer/api/ping';
        $path = (string)parse_url($url, PHP_URL_PATH);
        $now = time();
        $nonce = RequestSignature::nonce();
        try {
            $response = Craft::createGuzzleClient(['timeout' => 15])->get($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Observer-Timestamp' => (string)$now,
                    'X-Observer-Nonce' => $nonce,
                    'X-Observer-Signature' => RequestSignature::sign($settings->key, 'GET', $path, $now, $nonce, ''),
                ],
                'http_errors' => false,
            ]);
            $status = $response->getStatusCode();

            return $status === 200
                ? ['subject' => 'ping', 'status' => 'ok', 'text' => "a signed request to $url answers 200"]
                : ['subject' => 'ping', 'status' => 'fail', 'text' => "a signed request to $url answers HTTP $status (401: key or clock; 404: key unset, plugin disabled, or the site is not at this URL)"];
        } catch (Throwable $e) {
            return ['subject' => 'ping', 'status' => 'fail', 'text' => "$url could not be reached: {$e->getMessage()}"];
        }
    }
}
