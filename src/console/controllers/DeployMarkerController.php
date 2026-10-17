<?php

declare(strict_types=1);

namespace mojo\observer\console\controllers;

use Craft;
use craft\console\Controller;
use mojo\observer\deploy\DeployMarker;
use mojo\observer\Observer;
use mojo\observer\security\RequestSignature;
use Throwable;
use yii\console\ExitCode;

/**
 * `craft observer/deploy-marker` — tells observer which release just went live. Meant for
 * bin/deploy.sh's POST_FLIP_HOOK. It never fails a deploy: a missing configuration or an
 * unreachable observer is reported and the command still exits 0.
 */
class DeployMarkerController extends Controller
{
    /** @var string|null The release name; defaults to the directory `current` points at. */
    public ?string $release = null;

    /** @var string|null The git ref that was deployed, e.g. a tag. */
    public ?string $ref = null;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'release', 'ref'];
    }

    public function actionIndex(): int
    {
        $settings = Observer::settings();
        if (!$settings->canReportDeploys()) {
            $this->stdout("observer: OBSERVER_KEY, OBSERVER_SITE_ID or OBSERVER_HOOK_URL is not set, no deploy marker sent.\n");

            return ExitCode::OK;
        }

        $release = $this->release ?? DeployMarker::releaseFromPath((string)Craft::getAlias('@root')) ?? 'unknown';
        try {
            $request = DeployMarker::request($settings, $release, $this->ref, time(), RequestSignature::nonce());
            $response = Craft::createGuzzleClient(['timeout' => 10])->post($request['url'], [
                'headers' => $request['headers'],
                'body' => $request['body'],
                'http_errors' => false,
            ]);
            $status = $response->getStatusCode();
            $this->stdout(sprintf("observer: deploy marker for %s %s (HTTP %d).\n", $release, $status < 300 ? 'sent' : 'refused', $status));
        } catch (Throwable $e) {
            $this->stderr("observer: deploy marker not sent: {$e->getMessage()}\n");
        }

        return ExitCode::OK;
    }
}
