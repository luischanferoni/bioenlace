<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Enum\ConditionClinicalStatus;
use common\components\Domain\Clinical\Service\ConditionLifecycleService;
use common\models\Clinical\Condition;

class ConditionLifecycleServiceTest extends Unit
{
    public function testActiveCanResolveAndInactivate(): void
    {
        $this->assertTrue(
            ConditionClinicalStatus::canTransition(
                ConditionClinicalStatus::ACTIVE,
                ConditionClinicalStatus::RESOLVED
            )
        );
        $this->assertTrue(
            ConditionClinicalStatus::canTransition(
                ConditionClinicalStatus::ACTIVE,
                ConditionClinicalStatus::INACTIVE
            )
        );
        $this->assertTrue(
            ConditionClinicalStatus::canTransition(
                ConditionClinicalStatus::RESOLVED,
                ConditionClinicalStatus::ACTIVE
            )
        );
    }

    public function testResolvedCannotGoToInactiveDirectly(): void
    {
        $this->assertFalse(
            ConditionClinicalStatus::canTransition(
                ConditionClinicalStatus::RESOLVED,
                ConditionClinicalStatus::INACTIVE
            )
        );
    }

    public function testClosureOptionsHaveNoImplicitSelection(): void
    {
        $opts = ConditionClinicalStatus::closureOptions();
        $this->assertNotEmpty($opts);
        foreach ($opts as $opt) {
            $this->assertArrayHasKey('value', $opt);
            $this->assertArrayHasKey('label', $opt);
            $this->assertArrayNotHasKey('selected', $opt);
        }
    }

    public function testTransitionThrowsOnInvalidPath(): void
    {
        $svc = new ConditionLifecycleService();
        $cond = new Condition();
        $cond->id = 1;
        $cond->clinical_status = ConditionClinicalStatus::RESOLVED;

        $this->expectException(\InvalidArgumentException::class);
        $svc->transition($cond, ConditionClinicalStatus::INACTIVE);
    }

    public function testNormalizeMapResolutionsViaApplyRejectsWrongSubject(): void
    {
        $svc = new ConditionLifecycleService();
        // Sin fila en BD: applyResolutions lanza not found.
        $this->expectException(\InvalidArgumentException::class);
        $svc->applyResolutions(['999999' => ConditionClinicalStatus::RESOLVED], 1);
    }
}
