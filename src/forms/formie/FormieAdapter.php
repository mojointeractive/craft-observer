<?php

declare(strict_types=1);

namespace mojo\observer\forms\formie;

use ArrayObject;
use Craft;
use craft\db\Query;
use craft\mail\Message;
use DateTime;
use mojo\observer\forms\FormAdapter;
use mojo\observer\testing\MailRouting;
use mojo\observer\testing\TestToken;
use verbb\formie\base\Captcha;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\enums\workflow\Stage;
use verbb\formie\enums\workflow\Task;
use verbb\formie\events\MailEvent;
use verbb\formie\events\ModifyFormIntegrationsEvent;
use verbb\formie\events\RegisterStageTasksEvent;
use verbb\formie\events\SubmissionWorkflowStageEvent;
use verbb\formie\events\TriggerIntegrationEvent;
use verbb\formie\Formie;
use verbb\formie\services\Emails;
use verbb\formie\services\Integrations;
use verbb\formie\services\SubmissionWorkflow;
use verbb\formie\workflow\tasks\TaskInterface;
use yii\base\Event;

/**
 * Formie 4's forms for observer: handles, fields, how they submit and notify, and how busy they
 * are (the inventory, the `formie-forms` endpoint). Recipient addresses are left out: observer
 * never stores client data.
 */
final class FormieAdapter implements FormAdapter
{
    /** The oldest Formie this adapter knows. */
    public const MIN_FORMIE_VERSION = '4.0.0-alpha';

    /** A test submission's title is this and the run id: it finds the run's submission. */
    public const TITLE_PREFIX = 'observer:';

    /** Where the run lives in the submission's custom metadata, read again in queue jobs. */
    public const METADATA_KEY = 'observer';

    public function handle(): string
    {
        return 'formie';
    }

    public function available(): bool
    {
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $formie = $app->getPlugins()->getPlugin('formie');

        return $formie !== null && $app->getPlugins()->isPluginEnabled('formie')
            && version_compare($formie->getVersion(), self::MIN_FORMIE_VERSION, '>=');
    }

    public function inventory(): array
    {
        $forms = [];
        foreach (Formie::$plugin->getForms()->getAllForms() as $form) {
            $forms[] = self::form($form);
        }

        return ['forms' => $forms];
    }

    public function listen(): void
    {
        // Integrations run in queue jobs: the saved metadata says the submission is a test.
        Event::on(Integrations::class, Integrations::EVENT_BEFORE_TRIGGER_INTEGRATION, static function (TriggerIntegrationEvent $event): void {
            if ($event->submission !== null && self::testOf($event->submission) !== null) {
                $event->isValid = false;
            }
        });
        Event::on(Emails::class, Emails::EVENT_BEFORE_SEND_MAIL, static function (MailEvent $event): void {
            $test = $event->submission !== null ? self::testOf($event->submission) : null;
            if ($test !== null && $event->email instanceof Message) {
                self::route($event->email, $test);
            }
        });
    }

    public function startTest(TestToken $token): void
    {
        $state = new ArrayObject(['active' => false]);

        // The token's form renders and validates without its captchas (reCAPTCHA among them).
        Event::on(Integrations::class, Integrations::EVENT_MODIFY_FORM_INTEGRATIONS, static function (ModifyFormIntegrationsEvent $event) use ($token): void {
            if ($event->form?->handle === $token->form && $event->integrations !== null) {
                $event->integrations = array_filter($event->integrations, static fn ($integration): bool => !$integration instanceof Captcha);
            }
        });

        Event::on(SubmissionWorkflow::class, SubmissionWorkflow::EVENT_BEFORE_STAGE, static function (SubmissionWorkflowStageEvent $event) use ($token, $state): void {
            if ($event->stage !== Stage::PREPARE->value || $event->request === null) {
                return;
            }
            $form = $event->request->form;
            if ($form->handle !== $token->form || self::completedSubmission($token->run) !== null) {
                Craft::warning("observer: refused a test submission for {$form->handle} (token for {$token->form}, run {$token->run}).", __METHOD__);
                $event->isValid = false;

                return;
            }
            $form->settings->disableCaptchas = true;
            $event->request->submission->title = self::TITLE_PREFIX . $token->run;
            $form->setSubmissionMetadata([self::METADATA_KEY => ['run' => $token->run, 'mail' => $token->mail, 'probe' => $token->probe]]);
            $state['active'] = true;
        });

        // A browser fills the honeypot, the start time and the replay token like a visitor; observer's
        // run is faster than a person, so the guards are skipped for its form, with the captchas.
        Event::on(SubmissionWorkflow::class, SubmissionWorkflow::EVENT_REGISTER_STAGE_TASKS, static function (RegisterStageTasksEvent $event) use ($state): void {
            if ($event->stage !== Stage::SCREEN->value || $state['active'] !== true) {
                return;
            }
            $skipped = [Task::SCREEN_RUN_CAPTCHA_CHECKS->value, Task::SCREEN_RUN_SUBMISSION_GUARDS->value];
            $event->tasks = array_values(array_filter($event->tasks, static fn (TaskInterface $task): bool => !in_array($task->getName(), $skipped, true)));
        });
    }

    public function refuseTests(string $reason): void
    {
        Event::on(SubmissionWorkflow::class, SubmissionWorkflow::EVENT_BEFORE_STAGE, static function (SubmissionWorkflowStageEvent $event) use ($reason): void {
            if ($event->stage === Stage::PREPARE->value) {
                Craft::warning("observer: refused a submission with an invalid test token ($reason).", __METHOD__);
                $event->isValid = false;
            }
        });
    }

    /** The submission a run made, when the run got as far as a complete one. */
    public static function completedSubmission(string $run): ?Submission
    {
        $submission = Submission::find()->title(self::TITLE_PREFIX . $run)->status(null)->isSpam(null)->isIncomplete(false)->one();

        return $submission instanceof Submission ? $submission : null;
    }

    /** @return array{run: string, mail: string, probe: string}|null */
    private static function testOf(Submission $submission): ?array
    {
        $test = $submission->getMetadata('custom')[self::METADATA_KEY] ?? null;

        return is_array($test) && is_string($test['run'] ?? null) && is_string($test['mail'] ?? null) && is_string($test['probe'] ?? null)
            ? ['run' => $test['run'], 'mail' => $test['mail'], 'probe' => $test['probe']]
            : null;
    }

    /** @param array{run: string, mail: string, probe: string} $test */
    private static function route(Message $message, array $test): void
    {
        $routed = MailRouting::route($test['mail'], $test['probe'], self::addresses($message->getTo()), self::addresses($message->getCc()), self::addresses($message->getBcc()));

        $headers = $message->getSymfonyEmail()->getHeaders();
        $headers->remove('Cc');
        $headers->remove('Bcc');
        $message->setTo($routed['to']);
        if ($routed['cc'] !== []) {
            $message->setCc($routed['cc']);
        }
        if ($routed['bcc'] !== []) {
            $message->setBcc($routed['bcc']);
        }
        $message->setHeader('X-Observer-Run', $test['run']);
    }

    /**
     * @param array<int|string, string>|string|null $value a mailer's recipients: addresses, or address => name
     * @return list<string>
     */
    private static function addresses(array|string|null $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }
        if (is_string($value)) {
            return [$value];
        }
        $out = [];
        foreach ($value as $key => $name) {
            $out[] = is_string($key) ? $key : $name;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function form(Form $form): array
    {
        $last = Submission::find()->formId($form->id)->status(null)->orderBy(['elements.dateCreated' => SORT_DESC])->one();
        $failedSince = (new DateTime('-7 days'))->format('Y-m-d H:i:s');

        return [
            'handle' => $form->handle,
            'title' => $form->title,
            'pages' => count($form->getPages()),
            'fields' => array_values(array_map(static fn ($field): array => [
                'handle' => $field->handle,
                'type' => (new \ReflectionClass($field))->getShortName(),
                'required' => (bool)$field->required,
            ], $form->getFields())),
            'submitMethod' => $form->settings->submitMethod,
            'submitAction' => $form->settings->submitAction,
            'notifications' => array_values(array_map(static fn ($notification): array => [
                'handle' => $notification->handle,
                'name' => $notification->name,
                'enabled' => (bool)$notification->enabled,
                'recipients' => $notification->recipients,
            ], $form->getNotifications() ?? [])),
            'submissions' => (int)Submission::find()->formId($form->id)->status(null)->count(),
            'lastSubmissionAt' => $last?->dateCreated?->format('Y-m-d\TH:i:s\Z'),
            'failedNotifications7d' => (int)(new Query())
                ->from('{{%formie_sentnotifications}}')
                ->where(['formId' => $form->id, 'success' => false])
                ->andWhere(['>=', 'dateCreated', $failedSince])
                ->count(),
        ];
    }
}
