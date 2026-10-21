<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\CareCohort\Application\Catalog\CareFollowupNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class CareFollowupNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        CareFollowupNotificationCatalog::resetCacheForTests();
    }

    public function testResolvesStaffAndPatientCopyByRule(): void
    {
        $catalog = new CareFollowupNotificationCatalog();

        $staff = $catalog->forRule('worsening_evolution', 'staff');
        $patient = $catalog->forRule('poor_adherence', 'patient');

        self::assertSame('Seguimiento: posible empeoramiento', $staff['title']);
        self::assertStringContainsString('{touchpoint_title}', $staff['body_template']);
        self::assertSame('Recordatorio de medicación', $patient['title']);
        self::assertSame('', $patient['body_template']);
        self::assertStringContainsString('equipo de salud', $patient['body']);
    }

    public function testMissingCopyFailsExplicitly(): void
    {
        $catalog = new CareFollowupNotificationCatalog();

        $this->expectException(\RuntimeException::class);
        $catalog->forRule('unknown_rule', 'staff');
    }
}
