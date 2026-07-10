<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\Entitlement\EfectorEncounterEntitlementService;
use common\models\BillingAccount;
use common\models\BillingAccountEfector;

class BillingAccountEntitlementPoolTest extends Unit
{
    public function testFirstDayOfNextMonth(): void
    {
        $from = new \DateTimeImmutable('2026-07-10');
        $this->assertSame(
            '2026-08-01',
            EfectorEncounterEntitlementService::firstDayOfNextMonth($from)
        );
    }

    public function testResolveAccountWithoutMembership(): void
    {
        $this->assertNull(EfectorEncounterEntitlementService::resolveAccountIdForEfector(0));
        $this->assertSame([], EfectorEncounterEntitlementService::memberEfectorIds(0));
        $this->assertSame([], EfectorEncounterEntitlementService::affiliateEfectorIds(0));
        $this->assertSame([], EfectorEncounterEntitlementService::affiliationAccountsForEfector(0));
        $this->assertSame([], EfectorEncounterEntitlementService::contractSummary(0));
    }

    public function testTipoOptions(): void
    {
        $opts = BillingAccount::tipoOptions();
        $this->assertArrayHasKey(BillingAccount::TIPO_MINISTERIO, $opts);
        $this->assertArrayHasKey(BillingAccount::TIPO_RED, $opts);
        $this->assertArrayHasKey(BillingAccount::TIPO_EFECTOR, $opts);
    }

    public function testRolMembresiaOptions(): void
    {
        $opts = BillingAccountEfector::rolOptions();
        $this->assertArrayHasKey(BillingAccountEfector::ROL_POOL, $opts);
        $this->assertArrayHasKey(BillingAccountEfector::ROL_AFILIADO, $opts);
    }

    public function testAssertCanAddPesNoopWithoutAccount(): void
    {
        EfectorEncounterEntitlementService::assertCanAddPes(0, 0, 0);
        $this->assertTrue(true);
    }
}
