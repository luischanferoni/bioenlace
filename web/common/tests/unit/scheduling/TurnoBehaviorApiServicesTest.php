<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoAgentActionExplanationService;
use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoBehaviorAggregateService;
use common\components\Domain\Scheduling\BehaviorProfile\Domain\Port\TurnoBehaviorProfileContract;

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
