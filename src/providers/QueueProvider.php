<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use craft\db\Query;
use craft\db\Table;
use mojo\observer\vitals\Provider;

/** Queue depth and age, read from Craft's queue table (the craftly doctor judges it). */
final class QueueProvider implements Provider
{
    public function key(): string
    {
        return 'queue';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(): array
    {
        $count = static fn (array $where): int => (int)(new Query())->from(Table::QUEUE)->where($where)->count();
        $oldest = (new Query())->from(Table::QUEUE)->where(['fail' => false, 'timeUpdated' => null])->min('timePushed');

        return [
            'pending' => $count(['fail' => false, 'timeUpdated' => null]),
            'reserved' => (int)(new Query())->from(Table::QUEUE)->where(['fail' => false])->andWhere(['not', ['timeUpdated' => null]])->count(),
            'failed' => $count(['fail' => true]),
            'oldestPendingAgeSeconds' => $oldest === null ? null : max(0, time() - (int)$oldest),
        ];
    }
}
