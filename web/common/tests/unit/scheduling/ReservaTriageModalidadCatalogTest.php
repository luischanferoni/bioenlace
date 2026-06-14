<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ReservaTriageModalidadStepService;
use common\components\Domain\Scheduling\Service\ReservaTurnoTriageCatalogService;
use common\components\Domain\Scheduling\Service\TeleconsultaElegibilidadService;

class ReservaTriageModalidadCatalogTest extends Unit
{
    public function testModalidadStepFueraDelYamlClinico(): void
    {
        $catalog = new ReservaTurnoTriageCatalogService();
        $manifest = $catalog->getManifest();

        $this->assertArrayNotHasKey('modalidad', $manifest['steps'] ?? []);

        $step = $catalog->getStep(ReservaTriageModalidadStepService::STEP_ID);
        $this->assertNotNull($step);
        $this->assertSame('tipo_atencion', $step['draft_field']);
        $this->assertSame(ReservaTriageModalidadStepService::TITLE, $step['title']);
    }

    public function testListStepIdsIncluyeModalidad(): void
    {
        $ids = (new ReservaTurnoTriageCatalogService())->listStepIds();
        $this->assertContains('modalidad', $ids);
    }

    public function testElegibilidadClinicaDefaultPermitido(): void
    {
        $svc = new TeleconsultaElegibilidadService();
        $res = $svc->resolverParaDraft([
            'triage_raiz' => 'malestar_nuevo',
            'triage_zona' => 'zona_sistemas',
        ]);
        $this->assertSame(TeleconsultaElegibilidadService::ELEG_PERMITIDO, $res['elegibilidad_clinica']);
    }
}
