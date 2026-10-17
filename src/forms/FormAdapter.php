<?php

declare(strict_types=1);

namespace mojo\observer\forms;

/**
 * How one form plugin's forms reach observer. The plugin brings one for Formie (formie/FormieAdapter);
 * another form plugin adds its own through Observer::EVENT_REGISTER_FORM_ADAPTERS.
 */
interface FormAdapter
{
    /** The adapter's name in observer's requests, e.g. "formie". */
    public function handle(): string;

    /** False when its form plugin is missing, disabled or too old: the adapter is left out. */
    public function available(): bool;

    /**
     * The forms observer can test, without recipient addresses: `{"forms": [...]}`.
     *
     * @return array<string, mixed>
     */
    public function inventory(): array;
}
