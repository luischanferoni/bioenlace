<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultaAsyncBandejaPrioridadService;

class ConsultaAsyncBandejaPrioridadScoringTest extends Unit
{
    public function testUrgencyBandAScoresHigherThanD(): void
    {
        $svc = new ConsultaAsyncBandejaPrioridadService();
        $config = [
            'scoring' => [
                'urgency_band' => ['A' => 100, 'D' => 20, 'default' => 10],
            ],
        ];

        $itemA = [
            'encounter_id' => 1,
            'urgency_band' => 'A',
            'created_at' => date('Y-m-d H:i:s'),
            'sla' => ['incumplido' => false],
            'status' => 'planned',
        ];
        $itemD = [
            'encounter_id' => 2,
            'urgency_band' => 'D',
            'created_at' => date('Y-m-d H:i:s'),
            'sla' => ['incumplido' => false],
            'status' => 'planned',
        ];

        $scoreA = $svc->computePrioridad($itemA, null, $config);
        $scoreD = $svc->computePrioridad($itemD, null, $config);

        $this->assertGreaterThan($scoreD['score'], $scoreA['score']);
    }

    public function testSlaIncumplidoAddsBonus(): void
    {
        $svc = new ConsultaAsyncBandejaPrioridadService();
        $config = [
            'scoring' => [
                'urgency_band' => ['default' => 10],
                'sla_incumplido' => 50,
            ],
        ];

        $base = [
            'encounter_id' => 3,
            'urgency_band' => 'C',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'status' => 'planned',
        ];
        $sinSla = $svc->computePrioridad(array_merge($base, ['sla' => ['incumplido' => false]]), null, $config);
        $conSla = $svc->computePrioridad(array_merge($base, ['sla' => ['incumplido' => true]]), null, $config);

        $this->assertSame(50, $conSla['score'] - $sinSla['score']);
    }

    public function testSortItemsAssignsRankByScore(): void
    {
        $svc = new ConsultaAsyncBandejaPrioridadService();
        $items = [
            [
                'encounter_id' => 10,
                'created_at' => '2026-06-01 10:00:00',
                'prioridad' => ['score' => 30, 'factors' => []],
            ],
            [
                'encounter_id' => 11,
                'created_at' => '2026-06-01 09:00:00',
                'prioridad' => ['score' => 80, 'factors' => []],
            ],
        ];

        $sorted = $svc->sortItems($items);
        $this->assertSame(11, $sorted[0]['encounter_id']);
        $this->assertSame(1, $sorted[0]['prioridad']['rank']);
        $this->assertSame(2, $sorted[1]['prioridad']['rank']);
    }
}
