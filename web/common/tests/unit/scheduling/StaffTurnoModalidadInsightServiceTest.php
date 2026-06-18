<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\StaffModalidadInsightCatalogService;
use common\components\Domain\Scheduling\Service\StaffTurnoModalidadInsightService;
use common\components\Domain\Scheduling\Service\TurnoReservaTriageDraftBuilder;
use common\models\Scheduling\Turno;

class StaffTurnoModalidadInsightServiceTest extends Unit
{
    protected function _before(): void
    {
        StaffModalidadInsightCatalogService::resetCache();
    }

    public function testDraftBuilderReconstruyePathDesdeMetaJson(): void
    {
        $turno = new Turno();
        $turno->reserva_triage_code = 'seguimiento_cronico';
        $turno->reserva_triage_meta_json = json_encode([
            'path' => [
                ['field' => 'triage_raiz', 'code' => 'seguimiento_cronico', 'label' => 'Seguimiento'],
                ['field' => 'triage_evolucion', 'code' => 'evolucion_estable', 'label' => 'Estable'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $draft = (new TurnoReservaTriageDraftBuilder())->buildFromTurno($turno);

        $this->assertSame('seguimiento_cronico', $draft['triage_raiz']);
        $this->assertSame('evolucion_estable', $draft['triage_evolucion']);
    }

    public function testSinInsightSiTeleconsulta(): void
    {
        $turno = new Turno();
        $turno->tipo_atencion = Turno::TIPO_ATENCION_TELECONSULTA;
        $turno->reserva_triage_code = 'seguimiento_cronico';

        $this->assertNull((new StaffTurnoModalidadInsightService())->insightParaTurno($turno));
    }

    public function testSinInsightSinTriage(): void
    {
        $turno = new Turno();
        $turno->tipo_atencion = Turno::TIPO_ATENCION_PRESENCIAL;

        $this->assertNull((new StaffTurnoModalidadInsightService())->insightParaTurno($turno));
    }

    public function testCatalogoDeclaraModalidadesParaSugerido(): void
    {
        $catalog = new StaffModalidadInsightCatalogService();
        $mods = $catalog->modalidadesParaElegibilidad('sugerido');

        $this->assertNotEmpty($mods);
        $codes = array_column($mods, 'code');
        $this->assertContains('teleconsulta', $codes);
        $this->assertContains('async', $codes);
    }
}
