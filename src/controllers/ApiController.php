<?php

declare(strict_types=1);

namespace mojo\observer\controllers;

use Craft;
use craft\web\Controller;
use mojo\observer\forms\FormAdapters;
use mojo\observer\health\HealthReport;
use mojo\observer\Observer;
use mojo\observer\providers\CraftlyDoctorProvider;
use mojo\observer\providers\PlatformProvider;
use mojo\observer\providers\Providers;
use mojo\observer\security\CacheNonceStore;
use mojo\observer\security\RequestAuthenticator;
use mojo\observer\vitals\Vitals;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * `/actions/observer/api/<action>`: every action answers JSON to a request signed with the site's
 * OBSERVER_KEY (security/). Without a key the whole controller is a 404.
 * `health` also accepts Oh Dear's `oh-dear-health-check-secret` header, since Oh Dear cannot sign.
 */
class ApiController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE;

    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        $settings = Observer::settings();
        if (!$settings->apiIsOn() || $settings->key === null) {
            throw new NotFoundHttpException();
        }
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->response->format = Response::FORMAT_JSON;
        $this->response->getHeaders()->set('Cache-Control', 'no-store');

        $request = Craft::$app->getRequest();
        $headers = $request->getHeaders();
        if ($action->id === 'health' && $settings->healthSecret !== null
            && hash_equals($settings->healthSecret, (string)$headers->get('oh-dear-health-check-secret', ''))) {
            return true;
        }

        $problem = (new RequestAuthenticator($settings->key, new CacheNonceStore(Craft::$app->getCache())))->authenticate(
            $request->getMethod(),
            (string)parse_url($request->getUrl(), PHP_URL_PATH),
            $request->getRawBody(),
            (string)$headers->get('X-Observer-Timestamp', ''),
            (string)$headers->get('X-Observer-Nonce', ''),
            (string)$headers->get('X-Observer-Signature', ''),
            time(),
        );
        if ($problem !== null) {
            throw new HttpException(401, $problem);
        }

        return true;
    }

    public function actionPing(): Response
    {
        $platform = (new PlatformProvider())->collect();

        return $this->asJson([
            'api' => Vitals::API_VERSION,
            'module' => 'plugin',
            'pluginVersion' => Observer::getInstance()?->getVersion(),
            'craftVersion' => $platform['craftVersion'],
            'craftlyVersion' => $platform['craftlyVersion'],
            'environment' => $platform['environment'],
            'maintenance' => $platform['maintenance'],
            'serverTime' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    public function actionVitals(): Response
    {
        return $this->asJson(Vitals::collect(Providers::vitals(), time()));
    }

    public function actionDoctor(): Response
    {
        $doctor = new CraftlyDoctorProvider();

        return $this->asJson($doctor->available() ? $doctor->collect() : ['checks' => []]);
    }

    public function actionHealth(): Response
    {
        $doctor = new CraftlyDoctorProvider();

        return $this->asJson(HealthReport::fromDoctor($doctor->available() ? $doctor->collect()['checks'] : [], time()));
    }

    public function actionFormieForms(): Response
    {
        return $this->asJson(FormAdapters::get('formie')?->inventory() ?? ['forms' => []]);
    }
}
