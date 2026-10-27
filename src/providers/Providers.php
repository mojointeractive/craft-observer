<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use mojo\observer\vitals\Provider;

/** The providers the vitals action asks, in payload order. */
final class Providers
{
    /** @return list<Provider> */
    public static function vitals(): array
    {
        return [
            new PlatformProvider(),
            new UpdatesProvider(),
            new QueueProvider(),
            new SystemProvider(),
            new BackupProvider(),
            new UsersProvider(),
            new CraftlyDoctorProvider(),
        ];
    }
}
