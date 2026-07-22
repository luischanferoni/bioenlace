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

    public function testConsultaGeneralIncluyeTipoLabelYReferencia(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_CONSULTA_GENERAL,
        ], 1);

        $this->assertNotNull($ctx);
        $this->assertSame('consulta_general', $ctx['intake_tipo']);
        $this->assertStringContainsString('Consulta general', (string) ($ctx['tipo_label'] ?? ''));
        $this->assertArrayNotHasKey('summary', $ctx);
        $this->assertCount(1, $ctx['references']);
        $this->assertSame('clinical_history', $ctx['references'][0]['kind']);
        $this->assertSame(1, $ctx['references'][0]['subject_persona_id']);
        $this->assertNull($ctx['reference_encounter']);
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
        $this->assertArrayNotHasKey('summary', $ctx);
        $codes = array_column($ctx['lines'], 'code');
        $this->assertContains('seguimiento_necesidad', $codes);
        $this->assertContains('medicacion_operacion', $codes);
        $this->assertContains('medication_request_ids', $codes);
        $values = array_column($ctx['lines'], 'value');
        $joined = implode(' ', $values);
        $this->assertStringContainsString('Solicitud de renovación de medicación', $joined);
        $this->assertStringContainsString('Enalapril', $joined);
        $this->assertSame('clinical_history', $ctx['references'][0]['kind']);
    }

    public function testSeguimientoConsultaPreviaIncluyeReferenciaYDetalle(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            'reference_encounter_id' => 55,
        ], 12);

        $this->assertNotNull($ctx);
        $this->assertArrayNotHasKey('summary', $ctx);
        $this->assertStringContainsString('consulta previa', strtolower((string) ($ctx['tipo_label'] ?? '')));
        $this->assertNotEmpty($ctx['lines']);
        $this->assertSame('reference_encounter', $ctx['lines'][0]['code']);
        $this->assertSame(12, $ctx['subject_persona_id']);
        $this->assertIsArray($ctx['reference_encounter']);
        $this->assertSame(55, $ctx['reference_encounter']['encounter_id']);
        $this->assertIsArray($ctx['reference_encounter']['detail']);
        $kinds = array_column($ctx['references'], 'kind');
        $this->assertContains('reference_encounter', $kinds);
        $this->assertContains('clinical_history', $kinds);
        $refEnc = null;
        foreach ($ctx['references'] as $ref) {
            if (($ref['kind'] ?? '') === 'reference_encounter') {
                $refEnc = $ref;
                break;
            }
        }
        $this->assertNotNull($refEnc);
        $this->assertSame(55, $refEnc['encounter_id']);
        $this->assertSame(12, $refEnc['subject_persona_id']);
    }

    public function testBandejaOmiteDetailDeEncounterReferencia(): void
    {
        $svc = new ConsultaAsyncIntakeContextService();
        $ctx = $svc->buildFromMeta([
            'intake_tipo' => ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA,
            'reference_encounter_id' => 55,
        ], 12, ['include_reference_detail' => false]);

        $this->assertNotNull($ctx);
        $this->assertSame(55, $ctx['reference_encounter']['encounter_id']);
        $this->assertArrayNotHasKey('detail', $ctx['reference_encounter']);
        $this->assertSame('Atención #55', $ctx['lines'][0]['value']);
    }
}
