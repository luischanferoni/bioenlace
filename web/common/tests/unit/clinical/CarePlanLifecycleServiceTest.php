<?php

namespace common\tests\unit\clinical;

use common\components\Clinical\Enum\CarePlanCategory;
use common\components\Clinical\Enum\CarePlanStatus;
use common\components\Clinical\Service\CarePlanLifecycleService;
use common\components\Clinical\Service\CarePlanService;
use common\components\Clinical\Support\CarePlanProgramMeta;
use common\models\Clinical\CarePlan;
use Codeception\Test\Unit;

class CarePlanLifecycleServiceTest extends Unit
{
    public function testPersistentCategoriesDoNotCompleteOnEncounterClose(): void
    {
        verify(CarePlanCategory::completesOnEncounterClose(CarePlanCategory::ACUTE_AMBULATORY))->true();
        verify(CarePlanCategory::completesOnEncounterClose(CarePlanCategory::CHRONIC))->false();
        verify(CarePlanCategory::completesOnEncounterClose(CarePlanCategory::INPATIENT))->false();
        verify(CarePlanCategory::completesOnEncounterClose(CarePlanCategory::PROGRAM))->false();
    }

    public function testProgramMetaExhausted(): void
    {
        $json = CarePlanProgramMeta::encode(3, 3);
        verify(CarePlanProgramMeta::isExhausted($json))->true();
        verify(CarePlanProgramMeta::isExhausted(CarePlanProgramMeta::encode(3, 2)))->false();
    }

    public function testCompletedPlanIsNotMutable(): void
    {
        $service = new CarePlanService();
        $plan = new CarePlan();
        $plan->status = CarePlanStatus::COMPLETED;

        $this->expectException(\InvalidArgumentException::class);
        $service->assertMutable($plan);
    }

    public function testInvalidCategoryOnCreateDraft(): void
    {
        $service = new CarePlanService();
        $this->expectException(\InvalidArgumentException::class);
        $service->createDraft(1, 'categoria-inventada');
    }

    public function testHoldAndResumeTransitions(): void
    {
        verify(CarePlanStatus::canTransition(CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD))->true();
        verify(CarePlanStatus::canTransition(CarePlanStatus::ON_HOLD, CarePlanStatus::ACTIVE))->true();
        verify(CarePlanStatus::canTransition(CarePlanStatus::ON_HOLD, CarePlanStatus::COMPLETED))->true();
    }
}
