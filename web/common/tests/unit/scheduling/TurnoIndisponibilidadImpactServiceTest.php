<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\TurnoIndisponibilidadImpactService;

class TurnoIndisponibilidadImpactServiceTest extends Unit
{
    public function testPreviewSinTurnosParaPesInvalido(): void
    {
        $preview = TurnoIndisponibilidadImpactService::previewPorPesYRango(
            0,
            '2026-06-01',
            '2026-06-05'
        );

        $this->assertSame(0, $preview['turnos_afectados_total']);
        $this->assertFalse($preview['requiere_confirmacion']);
        $this->assertStringContainsString('No hay turnos pendientes', $preview['mensaje']);
    }

    public function testPreviewRequiereFechaInicio(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TurnoIndisponibilidadImpactService::previewPorPesYRango(1, '', null);
    }
}
