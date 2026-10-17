<?php

declare(strict_types=1);

use mojo\observer\security\MaintenanceBypass;
use PHPUnit\Framework\TestCase;

/**
 * During maintenance only observer's own action paths pass Patrol; a visitor cannot reach the live
 * site by putting that path anywhere else in the URL.
 */
final class ObserverMaintenanceBypassTest extends TestCase
{
    public function testObserversActionsPass(): void
    {
        self::assertTrue(MaintenanceBypass::applies('/actions/observer/api/ping'));
        self::assertTrue(MaintenanceBypass::applies('/actions/observer/api/formie-forms'));
        self::assertTrue(MaintenanceBypass::applies('/actions/observer/api/vitals?x=1'));
    }

    public function testEverythingElseStaysInMaintenance(): void
    {
        self::assertFalse(MaintenanceBypass::applies(''));
        self::assertFalse(MaintenanceBypass::applies('/'));
        self::assertFalse(MaintenanceBypass::applies('/?/actions/observer/api/'));
        self::assertFalse(MaintenanceBypass::applies('/?x=/actions/observer/api/ping'));
        self::assertFalse(MaintenanceBypass::applies('/news/actions/observer/api/ping'));
        self::assertFalse(MaintenanceBypass::applies('/actions/observer/api/ping/extra'));
    }
}
