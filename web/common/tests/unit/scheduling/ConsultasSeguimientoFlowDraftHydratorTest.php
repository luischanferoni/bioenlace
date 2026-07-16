<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoFlowDraftHydrator;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeCatalogService;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeService;

class ConsultasSeguimientoFlowDraftHydratorTest extends Unit
{
    public function testInfiereSeguimientoConsultaPreviaDesdeEncounterId(): void
    {
        $body = [
            'draft' => [
                'encounter_id' => '42',
            ],
        ];

        ConsultasSeguimientoFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame(
            ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            $body['draft'][ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO] ?? null
        );
        $this->assertSame('42', $body['draft']['encounter_id'] ?? null);
    }

    public function testCarePlanSigueImplicandoSeguimientoTratamiento(): void
    {
        $body = [
            'draft' => [
                'care_plan_id' => '7',
            ],
        ];

        ConsultasSeguimientoFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame(
            ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
            $body['draft'][ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO] ?? null
        );
    }
}
