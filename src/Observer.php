<?php

declare(strict_types=1);

namespace mojo\observer;

use Craft;
use craft\base\Plugin;
use craft\helpers\App;
use mojo\observer\forms\formie\FormieAdapter;
use mojo\observer\forms\RegisterFormAdaptersEvent;
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
    }

    public static function settings(): Settings
    {
        return Settings::fromEnv(static fn (string $name): ?string => Settings::envString(App::env($name)));
    }
}
