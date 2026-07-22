<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\ConditionPresentationService;
use common\models\Clinical\Condition;

class ConditionPresentationServiceTest extends Unit
{
    public function testToPatientSummaryIncluyeAnchorYAcciones(): void
    {
        $cond = new Condition();
        $cond->id = 42;
        $cond->code = 'I10';
        $cond->display = 'Hipertensión arterial esencial';
        $cond->clinical_status = 'ACTIVE';
        $cond->verification_status = 'CONFIRMED';

        $summary = (new ConditionPresentationService())->toPatientSummary($cond);

        $this->assertSame(42, $summary['id']);
        $this->assertSame('I10', $summary['codigo']);
        $this->assertSame('Hipertensión arterial esencial', $summary['label']);
        $this->assertSame('diag:I10', $summary['control_hub_anchor']);
        $this->assertSame('Activa', $summary['statusLabel']);
        $this->assertNotEmpty($summary['seguimientoAcciones']);
        $this->assertArrayHasKey('code', $summary['seguimientoAcciones'][0]);
        $this->assertArrayHasKey('label', $summary['seguimientoAcciones'][0]);
        $this->assertArrayHasKey('draft', $summary['seguimientoAcciones'][0]);
    }

    public function testToPatientSummaryAcortaDisplayLargo(): void
    {
        $cond = new Condition();
        $cond->id = 1;
        $cond->code = 'I10';
        $cond->display = 'hipertensión arterial esencial en seguimiento inicial';
        $cond->clinical_status = 'ACTIVE';
        $cond->verification_status = 'PROVISIONAL';

        $summary = (new ConditionPresentationService())->toPatientSummary($cond);

        $this->assertSame('hipertensión arterial esencial', $summary['label']);
    }
}
