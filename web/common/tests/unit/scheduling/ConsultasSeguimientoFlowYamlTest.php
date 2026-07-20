<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine;
use Symfony\Component\Yaml\Yaml;

/**
 * Contrato Control/Seguimiento dentro de Solicitar Atención (pasos cs_* absorbidos).
 */
class ConsultasSeguimientoFlowYamlTest extends Unit
{
    private const INTENT = 'atencion.necesito-atencion';

    public function testRoutingPorNecesidadYDraftKeys(): void
    {
        $path = dirname(__DIR__, 3)
            . '/metadata/bioenlace/assistant/intents/create/atencion.necesito-atencion.yaml';
        $this->assertFileExists($path);
        $yaml = Yaml::parseFile($path);
        $this->assertIsArray($yaml);
        $this->assertSame('scheduling.solicitar_atencion', $yaml['draft_hydrator']['handler'] ?? null);

        $extra = $yaml['draft_keys_extra'] ?? [];
        $this->assertContains('medication_request_ids', $extra);
        $this->assertContains('medicacion_operacion', $extra);
        $this->assertContains('ajuste_motivo', $extra);

        $byId = [];
        foreach ($yaml['subintents'] ?? [] as $si) {
            if (is_array($si) && isset($si['id'])) {
                $byId[(string) $si['id']] = $si;
            }
        }

        $raizRoutes = [];
        foreach ($byId['triage_raiz']['next_routing'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = $row['when']['draft_equals']['triage_raiz'] ?? null;
            if (is_string($when) && $when !== '') {
                $raizRoutes[$when] = (string) ($row['next'] ?? '');
            }
        }
        $this->assertSame('cs_hub', $raizRoutes['seguimiento_cronico'] ?? null);

        $this->assertArrayHasKey('cs_hub', $byId);
        $this->assertSame('consultas-seguimiento.hub', $byId['cs_hub']['open_ui']['action_id'] ?? null);
        $this->assertArrayHasKey('cs_condition_acciones', $byId);
        $this->assertSame(
            'consultas-seguimiento.condicion-acciones',
            $byId['cs_condition_acciones']['open_ui']['action_id'] ?? null
        );

        $this->assertArrayHasKey('cs_select_necesidad', $byId);
        $routes = [];
        foreach ($byId['cs_select_necesidad']['next_routing'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = $row['when']['draft_equals']['seguimiento_necesidad'] ?? null;
            if (is_string($when) && $when !== '') {
                $routes[$when] = (string) ($row['next'] ?? '');
            }
        }
        $this->assertSame('cs_select_medicamentos', $routes['renovar_medicacion'] ?? null);
        $this->assertSame('cs_select_medicamentos', $routes['solicitar_ajuste'] ?? null);
        $this->assertSame('cs_select_preferencia_turno', $routes['solicitar_turno'] ?? null);

        $this->assertArrayHasKey('cs_select_care_plan', $byId);
        $this->assertTrue($byId['cs_select_care_plan']['review_prefilled'] ?? false);
        $this->assertArrayHasKey('cs_select_medicamentos', $byId);
        $this->assertArrayHasKey('cs_captura_ajuste_motivo', $byId);

        $medRoutes = [];
        foreach ($byId['cs_select_medicamentos']['next_routing'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = $row['when']['draft_equals']['seguimiento_necesidad'] ?? null;
            if (is_string($when) && $when !== '') {
                $medRoutes[$when] = (string) ($row['next'] ?? '');
            }
        }
        $this->assertSame('cs_captura_ajuste_motivo', $medRoutes['solicitar_ajuste'] ?? null);
        $this->assertSame('', $medRoutes['renovar_medicacion'] ?? null);

        $this->assertSame(
            'clinical.care-plan.medicamentos-como-paciente',
            $byId['cs_select_medicamentos']['open_ui']['action_id'] ?? null
        );
        $this->assertSame(
            'clinical.care-plan.confirmar-renovacion-como-paciente',
            $byId['cs_select_medicamentos']['flow_submit']['action_id'] ?? null
        );
        $this->assertSame(
            'Solicitar renovación',
            $byId['cs_select_medicamentos']['flow_submit']['label'] ?? null
        );
        $this->assertSame(
            'renovacion',
            $byId['cs_select_medicamentos']['flow_submit']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste',
            $byId['cs_captura_ajuste_motivo']['composer_capture']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste_motivo',
            $byId['cs_captura_ajuste_motivo']['composer_capture']['draft_field'] ?? null
        );
    }

    public function testCarePlanPrefilledStillOpensReviewStep(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'draft' => [
                'triage_raiz' => 'seguimiento_cronico',
                'intake_tipo' => 'seguimiento',
                'care_plan_id' => '11',
                'seguimiento_necesidad' => 'renovar_medicacion',
                'control_hub_anchor' => 'cp:11',
                'control_hub_kind' => 'care_plan',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertContains(
            $response['subintent_id'] ?? null,
            ['cs_select_necesidad', 'cs_select_medicamentos', 'cs_select_care_plan'],
            'subintent=' . ($response['subintent_id'] ?? '')
        );
    }

    public function testConfirmedCarePlanAdvancesToDirectRenewalSubmit(): void
    {
        $draft = [
            'triage_raiz' => 'seguimiento_cronico',
            'intake_tipo' => 'seguimiento',
            'care_plan_id' => '11',
            'seguimiento_necesidad' => 'renovar_medicacion',
        ];
        $medicationStep = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'cs_select_care_plan',
            'draft' => $draft,
        ], 0);

        $this->assertTrue($medicationStep['success'] ?? false);
        $this->assertSame('cs_select_medicamentos', $medicationStep['subintent_id'] ?? null);
        $this->assertSame(
            'clinical.care-plan.medicamentos-como-paciente',
            $medicationStep['open_ui']['action_id'] ?? null
        );
        $this->assertSame(
            'clinical.care-plan.confirmar-renovacion-como-paciente',
            $medicationStep['flow_submit']['action_id'] ?? null
        );
        $this->assertSame('Solicitar renovación', $medicationStep['flow_submit']['label'] ?? null);
        $this->assertSame(
            'renovacion',
            $medicationStep['flow_submit']['body_template']['medicacion_operacion'] ?? null
        );
    }

    public function testAjusteBranchDoesNotExposeRenewalSubmitOnMedicationStep(): void
    {
        $draft = [
            'triage_raiz' => 'seguimiento_cronico',
            'intake_tipo' => 'seguimiento',
            'care_plan_id' => '11',
            'seguimiento_necesidad' => 'solicitar_ajuste',
        ];
        $medicationStep = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'cs_select_medicamentos',
            'draft' => $draft,
        ], 0);

        $this->assertTrue($medicationStep['success'] ?? false);
        $this->assertSame('cs_select_medicamentos', $medicationStep['subintent_id'] ?? null);
        $this->assertArrayNotHasKey('flow_submit', $medicationStep);
    }

    public function testAjusteBranchAdvancesToComposerAfterMedicationSelection(): void
    {
        $draft = [
            'triage_raiz' => 'seguimiento_cronico',
            'intake_tipo' => 'seguimiento',
            'care_plan_id' => '11',
            'seguimiento_necesidad' => 'solicitar_ajuste',
            'medication_request_ids' => ['101'],
        ];
        $nextStep = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'cs_select_medicamentos',
            'draft' => $draft,
        ], 0);

        $this->assertTrue($nextStep['success'] ?? false);
        $this->assertSame('cs_captura_ajuste_motivo', $nextStep['subintent_id'] ?? null);
        $this->assertArrayNotHasKey('flow_submit', $nextStep);
        $this->assertArrayHasKey('composer_capture', $nextStep);
    }
}
