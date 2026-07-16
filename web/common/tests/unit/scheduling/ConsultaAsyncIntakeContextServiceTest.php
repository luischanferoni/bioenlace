<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Service\ConsultaAsyncIntakeContextService;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeCatalogService;

class ConsultaAsyncIntakeContextServiceTest extends Unit
{
    public function testRetornaNullSinIntakeTipo(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $this->assertNull($svc->buildFromMeta(['urgency_band' => 'C'], 1));
    }

    public function testConsultaGeneralResumeTipo(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL,
        ], 1);

        $this->assertNotNull($ctx);
        $this->assertSame('consulta_general', $ctx['intake_tipo']);
        $this->assertStringContainsString('Consulta general', $ctx['summary']);
        $this->assertSame([], $ctx['references']);
    }

    public function testRenovacionIncluyeOperacionYMedicamentosEnLines(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO,
            'seguimiento_necesidad' => 'renovar_medicacion',
            'medicacion_operacion' => 'renovacion',
            'medication_labels' => ['Enalapril 10 mg', 'AAS 100 mg'],
        ], 1);

        $this->assertNotNull($ctx);
        $codes = array_column($ctx['lines'], 'code');
        $this->assertContains('seguimiento_necesidad', $codes);
        $this->assertContains('medicacion_operacion', $codes);
        $this->assertContains('medication_request_ids', $codes);
        $this->assertStringContainsString('Renovación', $ctx['summary']);
        $this->assertStringContainsString('Enalapril', $ctx['summary']);
    }

    public function testSeguimientoConsultaPreviaIncluyeReferencia(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            'reference_encounter_id' => 55,
        ], 12);

        $this->assertNotNull($ctx);
        $this->assertStringContainsString('consulta previa', strtolower($ctx['summary']));
        $this->assertNotEmpty($ctx['lines']);
        $this->assertSame('reference_encounter', $ctx['lines'][0]['code']);
        $this->assertSame(12, $ctx['subject_persona_id']);
        $this->assertCount(1, $ctx['references']);
        $this->assertSame('reference_encounter', $ctx['references'][0]['kind']);
        $this->assertSame(55, $ctx['references'][0]['encounter_id']);
        $this->assertSame(12, $ctx['references'][0]['subject_persona_id']);
    }
}
