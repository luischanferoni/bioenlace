<?php

namespace common\tests\unit\agent;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoResolucionAutoReservaService;
use common\models\Scheduling\Turno;

class TurnoResolucionAutoReservaScoringTest extends Unit
{
    public function testPickUnambiguousWinnerRequiresGap(): void
    {
        $config = ['min_winner_score' => 40, 'min_score_gap' => 8];
        $scored = [
            ['score' => 50, 'fecha' => '2026-07-01', 'hora' => '10:00'],
            ['score' => 45, 'fecha' => '2026-07-02', 'hora' => '11:00'],
        ];

        $this->assertNull(TurnoResolucionAutoReservaService::pickUnambiguousWinner($scored, $config));

        $scored[0]['score'] = 55;
        $winner = TurnoResolucionAutoReservaService::pickUnambiguousWinner($scored, $config);
        $this->assertNotNull($winner);
        $this->assertSame(55, $winner['score']);
    }

    public function testFranjaFilterExcludesMorningWhenOnlyTarde(): void
    {
        $turno = new Turno();
        $turno->id_profesional_efector_servicio = 5;
        $turno->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;

        $candidates = [
            ['fecha' => '2026-07-03', 'hora' => '09:00', 'id_profesional_efector_servicio' => 5],
            ['fecha' => '2026-07-03', 'hora' => '15:00', 'id_profesional_efector_servicio' => 5],
        ];
        $prefs = [
            'franjas' => ['TARDE'],
            'dias_semana' => [],
            'mismo_pes_prioritario' => true,
        ];

        $filtered = TurnoResolucionAutoReservaService::applyHardPreferenceFilters($candidates, $turno, $prefs);
        $this->assertCount(1, $filtered);
        $this->assertSame('15:00', $filtered[0]['hora']);
    }

    public function testMismoPesPrioritarioKeepsSameProfessionalWhenAvailable(): void
    {
        $turno = new Turno();
        $turno->id_profesional_efector_servicio = 10;

        $candidates = [
            ['fecha' => '2026-07-03', 'hora' => '10:00', 'id_profesional_efector_servicio' => 99],
            ['fecha' => '2026-07-04', 'hora' => '11:00', 'id_profesional_efector_servicio' => 10],
        ];
        $prefs = ['mismo_pes_prioritario' => true];

        $filtered = TurnoResolucionAutoReservaService::applyHardPreferenceFilters($candidates, $turno, $prefs);
        $this->assertCount(1, $filtered);
        $this->assertSame(10, $filtered[0]['id_profesional_efector_servicio']);
    }
}
