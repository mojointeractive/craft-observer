<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use Craft;
use craft\elements\User;
use mojo\observer\vitals\Provider;

/** Admin accounts and how many of them sign in without a second factor. Counts only, no names. */
final class UsersProvider implements Provider
{
    public function key(): string
    {
        return 'users';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        $admins = User::find()->admin(true)->status(User::STATUS_ACTIVE)->all();
        /** @var \craft\web\Application|\craft\console\Application $app */
        $app = Craft::$app;
        $auth = $app->getAuth();
        $lastLogins = array_filter(array_map(static fn (User $user) => $user->lastLoginDate?->getTimestamp(), $admins));

        return [
            'admins' => count($admins),
            'adminsWithoutTwoStep' => count(array_filter($admins, static fn (User $user): bool => !$auth->hasActiveMethod($user))),
            'lastAdminLoginAt' => $lastLogins === [] ? null : gmdate('Y-m-d\TH:i:s\Z', max($lastLogins)),
        ];
    }
}
