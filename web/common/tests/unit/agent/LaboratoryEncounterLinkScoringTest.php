<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Laboratory\Service\LaboratoryEncounterLinkScoringService;

class LaboratoryEncounterLinkScoringTest extends Unit
{
    public function testPickUnambiguousWinnerRequiresGap(): void
    {
        $config = ['min_winner_score' => 35, 'min_score_gap' => 10];
        $scored = [
            ['encounter_id' => 1, 'score' => 50],
            ['encounter_id' => 2, 'score' => 45],
        ];

        $this->assertNull(LaboratoryEncounterLinkScoringService::pickUnambiguousWinner($scored, $config));

        $scored[0]['score'] = 60;
        $winner = LaboratoryEncounterLinkScoringService::pickUnambiguousWinner($scored, $config);
        $this->assertNotNull($winner);
        $this->assertSame(1, $winner['encounter_id']);
    }
}
