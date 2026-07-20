<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine;
use Symfony\Component\Yaml\Yaml;

/**
 * Contrato del flow consultas-seguimiento: routing por necesidad y draft keys.
 */
class ConsultasSeguimientoFlowYamlTest extends Unit
{
    public function testRoutingPorNecesidadYDraftKeys(): void
    {
        $path = dirname(__DIR__, 3)
            . '/metadata/bioenlace/assistant/intents/create/atencion.consultas-seguimiento-flow.yaml';
        $this->assertFileExists($path);
        $yaml = Yaml::parseFile($path);
        $this->assertIsArray($yaml);

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

        $this->assertArrayHasKey('select_necesidad', $byId);
        $routes = [];
        foreach ($byId['select_necesidad']['next_routing'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = $row['when']['draft_equals']['seguimiento_necesidad'] ?? null;
            if (is_string($when) && $when !== '') {
                $routes[$when] = (string) ($row['next'] ?? '');
            }
        }
        $this->assertSame('select_medicamentos_renovacion', $routes['renovar_medicacion'] ?? null);
        $this->assertSame('select_medicamentos_ajuste', $routes['solicitar_ajuste'] ?? null);
        $this->assertSame('select_preferencia_turno', $routes['solicitar_turno'] ?? null);

        $this->assertArrayHasKey('select_care_plan', $byId);
        $this->assertTrue($byId['select_care_plan']['review_prefilled'] ?? false);
        $this->assertArrayHasKey('select_medicamentos_renovacion', $byId);
        $this->assertArrayNotHasKey('confirmar_renovacion', $byId);
        $this->assertArrayHasKey('select_medicamentos_ajuste', $byId);
        $this->assertArrayHasKey('captura_ajuste_motivo', $byId);

        $this->assertSame(
            'clinical.care-plan.medicamentos-como-paciente',
            $byId['select_medicamentos_renovacion']['open_ui']['action_id'] ?? null
        );
        $this->assertSame('', $byId['select_medicamentos_renovacion']['next'] ?? null);
        $this->assertSame(
            'clinical.care-plan.confirmar-renovacion-como-paciente',
            $byId['select_medicamentos_renovacion']['flow_submit']['action_id'] ?? null
        );
        $this->assertSame(
            'Solicitar renovación',
            $byId['select_medicamentos_renovacion']['flow_submit']['label'] ?? null
        );
        $this->assertSame(
            'renovacion',
            $byId['select_medicamentos_renovacion']['flow_submit']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste',
            $byId['captura_ajuste_motivo']['composer_capture']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste_motivo',
            $byId['captura_ajuste_motivo']['composer_capture']['draft_field'] ?? null
        );
    }

    public function testCarePlanPrefilledStillOpensReviewStep(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => 'atencion.consultas-seguimiento-flow',
            'draft' => [
                'intake_tipo' => 'seguimiento',
                'care_plan_id' => '11',
                'seguimiento_necesidad' => 'renovar_medicacion',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_care_plan', $response['subintent_id'] ?? null);
        $this->assertSame(
            'clinical.care-plan.ver-tratamiento-paciente',
            $response['open_ui']['action_id'] ?? null
        );
        $this->assertContains('care_plan_id', $response['provides'] ?? []);
    }

    public function testConfirmedCarePlanAdvancesToDirectRenewalSubmit(): void
    {
        $draft = [
            'intake_tipo' => 'seguimiento',
            'care_plan_id' => '11',
            'seguimiento_necesidad' => 'renovar_medicacion',
        ];
        $medicationStep = SubIntentEngine::process([
            'intent_id' => 'atencion.consultas-seguimiento-flow',
            'subintent_id' => 'select_care_plan',
            'draft' => $draft,
        ], 0);

        $this->assertTrue($medicationStep['success'] ?? false);
        $this->assertSame('select_medicamentos_renovacion', $medicationStep['subintent_id'] ?? null);
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
}
