<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoResolucionShortlistService;
use common\models\Scheduling\Turno;

class TurnoResolucionShortlistScoringTest extends Unit
{
    public function testNeighborSamePesScoresHighest(): void
    {
        $turno = new Turno();
        $turno->id_profesional_efector_servicio = 10;
        $turno->fecha = date('Y-m-d', strtotime('+3 days'));

        $neighbor = [
            'kind' => 'neighbor',
            'fecha' => $turno->fecha,
            'id_profesional_efector_servicio' => 10,
        ];
        $otherPes = [
            'kind' => 'slot',
            'fecha' => date('Y-m-d', strtotime('+10 days')),
            'id_profesional_efector_servicio' => 99,
        ];

        $config = [
            'scoring' => [
                'same_pes' => 30,
                'neighbor_option' => 25,
                'same_date_as_original' => 15,
                'proximity_per_day' => 2,
                'max_proximity_days' => 14,
            ],
        ];

        $neighborScore = TurnoResolucionShortlistService::scoreCandidate($turno, $neighbor, $config);
        $otherScore = TurnoResolucionShortlistService::scoreCandidate($turno, $otherPes, $config);

        $this->assertGreaterThan($otherScore, $neighborScore);
    }
}
