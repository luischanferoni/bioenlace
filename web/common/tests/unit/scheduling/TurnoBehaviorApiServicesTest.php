<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoAgentActionExplanationService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorAggregateService;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileContract;

class TurnoBehaviorApiServicesTest extends Unit
{
    public function testExplanationRejectsInvalidIds(): void
    {
        $svc = new TurnoAgentActionExplanationService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->explainOwnAction(0, 1);
    }

    public function testAggregateRequiresSupportedWindow(): void
    {
        TurnoBehaviorProfileContract::resetCacheForTests();
        $svc = new TurnoBehaviorAggregateService(new TurnoBehaviorProfileContract([
            'version' => 1,
            'windows_days' => [90, 180, 365],
            'min_sample_size' => 5,
            'scopes' => ['EFECTOR'],
            'metrics' => [],
            'events' => [],
        ]));
        $this->expectException(\InvalidArgumentException::class);
        $svc->forEfector(['id_efector' => 1, 'window_days' => 45]);
    }

    public function testAggregateRequiresEfector(): void
    {
        $svc = new TurnoBehaviorAggregateService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->forEfector(['window_days' => 90]);
    }
}
