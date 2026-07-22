<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeCatalogService;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeService;

class ConsultasSeguimientoIntakeServiceTest extends Unit
{
    public function testEsIntakeIncluyeSeguimientoConsultaPrevia(): void
    {
        $draft = [
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
        ];

        $this->assertTrue(ConsultasSeguimientoIntakeService::esIntakeConsultasSeguimiento($draft));
    }

    public function testCompilarMetaAsyncIncluyeReferenceEncounterId(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $meta = $svc->compilarMetaAsync([
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            'encounter_id' => '99',
            'triage_raiz' => 'seguimiento_cronico',
        ]);

        $this->assertSame('consulta_async_solicitud', $meta['tipo']);
        $this->assertSame(99, $meta['reference_encounter_id']);
        $this->assertNull($meta['care_plan_id']);
    }

    public function testPrepararDraftSeteaOperacionRenovacion(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $draft = [
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
            ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD => 'renovar_medicacion',
            'medication_request_ids' => '10, 20',
        ];
        $svc->prepararDraft($draft, 0);

        $this->assertSame(
            ConsultasSeguimientoIntakeService::MEDICACION_OP_RENOVACION,
            $draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICACION_OPERACION] ?? null
        );
        $this->assertSame('10,20', $draft[ConsultasSeguimientoIntakeService::DRAFT_MEDICATION_REQUEST_IDS] ?? null);
    }

    public function testCompilarMetaAsyncIncluyeMedicacionEstructurada(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $meta = $svc->compilarMetaAsync([
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
            ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD => 'solicitar_ajuste',
            ConsultasSeguimientoIntakeService::DRAFT_MEDICACION_OPERACION =>
                ConsultasSeguimientoIntakeService::MEDICACION_OP_AJUSTE,
            ConsultasSeguimientoIntakeService::DRAFT_MEDICATION_REQUEST_IDS => '7,8',
            ConsultasSeguimientoIntakeService::DRAFT_AJUSTE_MOTIVO => 'Me genera mareos a la mañana',
            'care_plan_id' => '3',
            'triage_raiz' => 'seguimiento_cronico',
        ]);

        $this->assertSame('ajuste', $meta['medicacion_operacion']);
        $this->assertSame([7, 8], $meta['medication_request_ids']);
        $this->assertSame('Me genera mareos a la mañana', $meta['ajuste_motivo']);
        $this->assertSame(3, $meta['care_plan_id']);
    }

    public function testCompilarMetaAsyncIncluyeConditionAncla(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $meta = $svc->compilarMetaAsync([
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL,
            'condition_ref' => '8',
            'condition_codigo' => 'J11.1',
            'control_hub_kind' => 'condition',
            'triage_raiz' => 'seguimiento_cronico',
        ]);

        $this->assertSame('J11.1', $meta['condition_codigo']);
        $this->assertSame('8', $meta['condition_ref']);
        $this->assertSame('condition', $meta['control_hub_kind']);
        $this->assertNull($meta['care_plan_id']);
    }

    public function testCompilarMetaAsyncNoUsaRefNumericoComoCodigo(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $meta = $svc->compilarMetaAsync([
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL,
            'condition_ref' => '8',
            'triage_raiz' => 'seguimiento_cronico',
        ]);

        $this->assertSame('8', $meta['condition_ref']);
        $this->assertNull($meta['condition_codigo']);
    }

    public function testPrepararDraftUnificaDudaEnContarEvolucion(): void
    {
        $svc = new ConsultasSeguimientoIntakeService();
        $draft = [
            ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO =>
                ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
            ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD => 'duda',
        ];
        $svc->prepararDraft($draft, 0);

        $this->assertSame(
            'contar_evolucion',
            $draft[ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD] ?? null
        );
    }
}
