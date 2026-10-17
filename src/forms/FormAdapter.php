<?php

declare(strict_types=1);

namespace mojo\observer\forms;

use mojo\observer\testing\TestToken;

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

    /**
     * Hooks for every request, queue jobs included, that act on test submissions already saved:
     * keep them away from integrations, send their notifications where the test says.
     */
    public function listen(): void;

    /**
     * This request carries a valid test token for one of this adapter's forms: let that form's
     * submission through captchas and guards, and mark it as the token's run.
     */
    public function startTest(TestToken $token): void;

    /** This request carries a test token that does not verify: submit nothing. */
    public function refuseTests(string $reason): void;

    /**
     * The run's submission and the notifications sent for it (testing\RunReport::shape()), or null
     * when the run made no submission.
     *
     * @return array<string, mixed>|null
     */
    public function report(string $run): ?array;

    /**
     * Deletes the run's submission and what was sent for it, unless its notifications are still on
     * their way.
     *
     * @return array{deleted: bool, reason?: string}
     */
    public function cleanUp(string $run): array;
}
