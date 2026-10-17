<?php

declare(strict_types=1);

namespace mojo\observer\forms\formie;

use Craft;
use craft\db\Query;
use DateTime;
use mojo\observer\forms\FormAdapter;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\Formie;

/**
 * Formie 4's forms for observer: handles, fields, how they submit and notify, and how busy they
 * are (the inventory, the `formie-forms` endpoint). Recipient addresses are left out: observer
 * never stores client data.
 */
final class FormieAdapter implements FormAdapter
{
    /** The oldest Formie this adapter knows. */
    public const MIN_FORMIE_VERSION = '4.0.0-alpha';

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
