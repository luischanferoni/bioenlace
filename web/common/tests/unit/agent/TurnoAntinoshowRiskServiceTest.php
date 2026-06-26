<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoAntinoshowRiskService;

class TurnoAntinoshowRiskServiceTest extends Unit
{
    public function testHighRiskWithTwoNoShows(): void
    {
        $level = TurnoAntinoshowRiskService::computeRiskLevel(2, 5, false, [
            'high_min_no_shows' => 2,
            'medium_min_no_shows' => 1,
            'long_lead_days' => 21,
            'first_visit_level' => 'medium',
        ]);
        $this->assertSame('high', $level);
    }

    public function testMediumRiskWithLongLead(): void
    {
        $level = TurnoAntinoshowRiskService::computeRiskLevel(0, 30, false, [
            'high_min_no_shows' => 2,
            'medium_min_no_shows' => 1,
            'long_lead_days' => 21,
        ]);
        $this->assertSame('medium', $level);
    }

    public function testFirstVisitDefaultsToMedium(): void
    {
        $level = TurnoAntinoshowRiskService::computeRiskLevel(0, 3, true, [
            'first_visit_level' => 'medium',
        ]);
        $this->assertSame('medium', $level);
    }

    public function testLowRiskStablePatient(): void
    {
        $level = TurnoAntinoshowRiskService::computeRiskLevel(0, 5, false, []);
        $this->assertSame('low', $level);
    }
}
