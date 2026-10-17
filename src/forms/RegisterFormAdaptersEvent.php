<?php

declare(strict_types=1);

namespace mojo\observer\forms;

use yii\base\Event;

/** Collects the form adapters: a listener appends its own to $adapters. */
class RegisterFormAdaptersEvent extends Event
{
    /** @var list<FormAdapter> */
    public array $adapters = [];
}
