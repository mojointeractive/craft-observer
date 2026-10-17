<?php

declare(strict_types=1);

namespace mojo\observer\forms;

use mojo\observer\Observer;
use yii\base\Event;

/** The available form adapters, asked for afresh on each request through Observer::EVENT_REGISTER_FORM_ADAPTERS. */
final class FormAdapters
{
    /** @return array<string, FormAdapter> keyed by handle */
    public static function all(): array
    {
        $event = new RegisterFormAdaptersEvent();
        Event::trigger(Observer::class, Observer::EVENT_REGISTER_FORM_ADAPTERS, $event);

        $adapters = [];
        foreach ($event->adapters as $adapter) {
            if ($adapter->available()) {
                $adapters[$adapter->handle()] = $adapter;
            }
        }

        return $adapters;
    }

    public static function get(string $handle): ?FormAdapter
    {
        return self::all()[$handle] ?? null;
    }
}
