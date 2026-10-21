<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Assistant\EncounterFollowupFlowDraftHydrator;
use common\components\Domain\Scheduling\Agenda\Application\Service\EncounterFollowupIntakeCatalogService;
use common\components\Domain\Scheduling\Agenda\Application\UseCase\SubmitEncounterFollowupIntake;

class EncounterFollowupFlowDraftHydratorTest extends Unit
{
    public function testInfiereSeguimientoConsultaPreviaDesdeEncounterId(): void
    {
        $body = [
            'draft' => [
                'encounter_id' => '42',
            ],
        ];

        EncounterFollowupFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame(
            EncounterFollowupIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            $body['draft'][SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO] ?? null
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

        EncounterFollowupFlowDraftHydrator::hydrateWithOptions($body);

        $this->assertSame(
            EncounterFollowupIntakeCatalogService::INTAKE_SEGUIMIENTO,
            $body['draft'][SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO] ?? null
        );
    }
}
