<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\Entitlement\EfectorEncounterEntitlementService;

class EfectorEncounterEntitlementServiceTest extends Unit
{
    public function testFirstDayOfNextMonth(): void
    {
        $from = new \DateTimeImmutable('2026-07-10');
        $this->assertSame(
            '2026-08-01',
            EfectorEncounterEntitlementService::firstDayOfNextMonth($from)
        );
        $fromEnd = new \DateTimeImmutable('2026-01-31');
        $this->assertSame(
            '2026-02-01',
            EfectorEncounterEntitlementService::firstDayOfNextMonth($fromEnd)
        );
    }

    public function testIsAdminEfectorServicioInvalidId(): void
    {
        $this->assertFalse(EfectorEncounterEntitlementService::isAdminEfectorServicio(0));
    }

    public function testAssertCanAddPesNoopWithoutContract(): void
    {
        // Sin filas de entitlement el assert no bloquea (allow_all / sin tope).
        EfectorEncounterEntitlementService::assertCanAddPes(0, 0, 0);
        $this->assertTrue(true);
    }
}
