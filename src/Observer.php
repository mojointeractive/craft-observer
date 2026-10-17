<?php

declare(strict_types=1);

namespace mojo\observer;

use Craft;
use craft\base\Plugin;
use craft\helpers\App;
use mojo\observer\forms\FormAdapters;
use mojo\observer\forms\formie\FormieAdapter;
use mojo\observer\forms\RegisterFormAdaptersEvent;
use mojo\observer\testing\TestRequest;
use mojo\observer\testing\TestToken;
use yii\base\Event;

/**
 * mojo observer's connector (README.md): answers observer's signed requests about this install at
 * `/actions/observer/api/<action>` and reports deploys with `craft observer/deploy-marker`.
 * Configured by environment only (Settings); nothing to set in the control panel.
 */
class Observer extends Plugin
{
    /** Collects the form adapters (forms\FormAdapters); the event is a RegisterFormAdaptersEvent. */
    public const EVENT_REGISTER_FORM_ADAPTERS = 'registerFormAdapters';

    public function init(): void
    {
        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'mojo\\observer\\console\\controllers'
            : 'mojo\\observer\\controllers';

        parent::init();

        Event::on(self::class, self::EVENT_REGISTER_FORM_ADAPTERS, static function (RegisterFormAdaptersEvent $event): void {
            $event->adapters[] = new FormieAdapter();
        });

        // After every plugin has loaded, so a form plugin that loads after this one is there.
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $app->onInit(static function (): void {
            self::listenForTests();
        });
    }

    /**
     * Form tests: every adapter acts on saved test submissions (also in queue jobs); a site request
     * with an X-Observer-Test header is a test when the token verifies, and submits nothing when not.
     */
    private static function listenForTests(): void
    {
        $adapters = FormAdapters::all();
        foreach ($adapters as $adapter) {
            $adapter->listen();
        }

        $request = Craft::$app->getRequest();
        if (!$request instanceof \craft\web\Request || !$request->getIsSiteRequest()) {
            return;
        }
        $header = $request->getHeaders()->get(TestRequest::HEADER);
        $settings = self::settings();
        $test = TestRequest::fromHeader(is_string($header) ? $header : null, $settings->apiIsOn() ? $settings->key : null, time());
        if ($test instanceof TestToken) {
            ($adapters[$test->adapter] ?? null)?->startTest($test);
        } elseif (is_string($test)) {
            foreach ($adapters as $adapter) {
                $adapter->refuseTests($test);
            }
        }
    }

    public static function settings(): Settings
    {
        return Settings::fromEnv(static fn (string $name): ?string => Settings::envString(App::env($name)));
    }
}
