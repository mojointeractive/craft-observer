<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use craft\db\Query;
use craft\db\Table;
use mojo\observer\vitals\Provider;

/**
 * Queue depth and age, read from Craft's queue table (a site on another queue driver reports
 * zeros). The oldest pending age counts from when the job became due, its delay included, so a
 * job scheduled for later is not "waiting". observer and health\HealthReport judge it.
 */
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
        $oldestDue = (new Query())->from(Table::QUEUE)->where(['fail' => false, 'timeUpdated' => null])->min('[[timePushed]] + [[delay]]');

        return [
            'pending' => $count(['fail' => false, 'timeUpdated' => null]),
            'reserved' => (int)(new Query())->from(Table::QUEUE)->where(['fail' => false])->andWhere(['not', ['timeUpdated' => null]])->count(),
            'failed' => $count(['fail' => true]),
            'oldestPendingAgeSeconds' => $oldestDue === null ? null : max(0, time() - (int)$oldestDue),
        ];
    }
}
