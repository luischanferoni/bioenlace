<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\components\Domain\Clinical\Service\CarePlanService;
use Codeception\Test\Unit;

class CarePlanServiceTest extends Unit
{
    public function testCarePlanStatusTransitions(): void
    {
        verify(CarePlanStatus::canTransition(CarePlanStatus::DRAFT, CarePlanStatus::ACTIVE))->true();
        verify(CarePlanStatus::canTransition(CarePlanStatus::ACTIVE, CarePlanStatus::COMPLETED))->true();
        verify(CarePlanStatus::canTransition(CarePlanStatus::COMPLETED, CarePlanStatus::ACTIVE))->false();
        verify(CarePlanStatus::canTransition(CarePlanStatus::DRAFT, CarePlanStatus::COMPLETED))->false();
    }

    public function testInvalidTransitionThrows(): void
    {
        $service = new CarePlanService();
        $plan = new \common\models\Clinical\CarePlan();
        $plan->status = CarePlanStatus::COMPLETED;

        $this->expectException(\InvalidArgumentException::class);
        $service->activate($plan);
    }
}
