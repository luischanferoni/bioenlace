<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Laboratory\Application\Agents\PostLabClassificationAgentPolicy;
use common\components\Domain\Clinical\Laboratory\Application\Catalog\PostLabClassificationNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class PostLabClassificationNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        PostLabClassificationNotificationCatalog::resetCacheForTests();
    }

    public function testResolvesPatientAndStaffCopyBySeverity(): void
    {
        $catalog = new PostLabClassificationNotificationCatalog();

        $patient = $catalog->forSeverity('critical', 'patient');
        $staff = $catalog->forSeverity('critical', 'staff');
        $normal = $catalog->forSeverity('normal', 'patient');

        self::assertSame('Resultado de laboratorio importante', $patient['title']);
        self::assertStringContainsString('atención urgente', $patient['body']);
        self::assertSame('', $patient['body_template']);
        self::assertSame('Laboratorio: valor crítico', $staff['title']);
        self::assertStringContainsString('{analyte_display}', $staff['body_template']);
        self::assertSame('Tus estudios están dentro de rangos esperados. Tu médico los revisará en la próxima consulta.', $normal['body']);
    }

    public function testMissingCopyFailsExplicitly(): void
    {
        $catalog = new PostLabClassificationNotificationCatalog();

        $this->expectException(\RuntimeException::class);
        $catalog->forSeverity('unknown', 'patient');
    }

    public function testPolicyContainsActionsButNoNotificationCopy(): void
    {
        $outcomes = PostLabClassificationAgentPolicy::config()['outcomes'];

        self::assertSame('notify_patient_and_staff', $outcomes['critical']['action']);
        self::assertSame('notify_patient', $outcomes['control']['action']);
        self::assertSame('notify_patient', $outcomes['normal']['action']);
        self::assertArrayNotHasKey('patient', $outcomes['critical']);
        self::assertArrayNotHasKey('staff', $outcomes['critical']);
    }
}
