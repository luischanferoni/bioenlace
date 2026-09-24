<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine;
use Symfony\Component\Yaml\Yaml;

/**
 * Contrato Control/Seguimiento dentro de Solicitar AtenciÃ³n (pasos cs_* absorbidos).
 */
class ConsultasSeguimientoFlowYamlTest extends Unit
{
    private const INTENT = 'atencion.necesito-atencion';

    public function testRoutingPorNecesidadYDraftKeys(): void
    {
        $path = dirname(__DIR__, 3)
            . '/components/Domain/Clinical/Encounter/Application/Flows/intents/create/atencion.necesito-atencion.yaml';
        $this->assertFileExists($path);
        $raw = Yaml::parseFile($path);
        $this->assertIsArray($raw);
        $this->assertArrayNotHasKey('subintents', $raw);
        $this->assertSame('scheduling.solicitar_atencion', $raw['draft_hydrator']['handler'] ?? null);
        $this->assertSame('triage_raiz', $raw['initial'] ?? null);

        $extra = $raw['context'] ?? [];
        $this->assertContains('medication_request_ids', $extra);
        $this->assertContains('medicacion_operacion', $extra);
        $this->assertContains('ajuste_motivo', $extra);

        $byId = is_array($raw['states'] ?? null) ? $raw['states'] : [];

        $raizRoutes = self::targetsByGuard($byId['triage_raiz']['always'] ?? [], 'triage_raiz');
        $this->assertSame('cs_hub', $raizRoutes['seguimiento_cronico'] ?? null);

        $this->assertArrayHasKey('cs_hub', $byId);
        $this->assertSame('consultas-seguimiento.hub', $byId['cs_hub']['meta']['open_ui']['action_id'] ?? null);
        $this->assertArrayHasKey('cs_condition_acciones', $byId);
        $this->assertSame(
            'consultas-seguimiento.condicion-acciones',
            $byId['cs_condition_acciones']['meta']['open_ui']['action_id'] ?? null
        );
        $this->assertSame(
            'draft.protocol_id',
            $byId['cs_condition_acciones']['meta']['open_ui']['params']['protocol_id'] ?? null
        );

        $hubKindRoutes = self::targetsByGuard($byId['cs_hub']['always'] ?? [], 'control_hub_kind');
        $this->assertSame('cs_condition_acciones', $hubKindRoutes['condition'] ?? null);
        $this->assertSame('cs_condition_acciones', $hubKindRoutes['protocol'] ?? null);
        $this->assertSame('cs_select_necesidad', $hubKindRoutes['care_plan'] ?? null);
        $this->assertArrayNotHasKey('consulta_general', $hubKindRoutes);
        $this->assertArrayNotHasKey('consulta_previa', $hubKindRoutes);
        $this->assertArrayNotHasKey('general', $hubKindRoutes);

        $this->assertArrayHasKey('cs_select_necesidad', $byId);
        $routes = self::targetsByGuard($byId['cs_select_necesidad']['always'] ?? [], 'seguimiento_necesidad');
        $this->assertSame('cs_select_medicamentos', $routes['renovar_medicacion'] ?? null);
        $this->assertSame('cs_select_medicamentos', $routes['solicitar_ajuste'] ?? null);
        $this->assertSame('select_tipo_atencion', $routes['solicitar_turno'] ?? null);

        $necesidadSkipModalidad = null;
        foreach ($byId['cs_select_necesidad']['always'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $guard = $row['guard'] ?? null;
            if (is_array($guard)
                && ($guard['seguimiento_necesidad'] ?? null) === 'solicitar_turno'
                && ($guard['modalidad_paso_requerido'] ?? null) === '0') {
                $necesidadSkipModalidad = (string) ($row['target'] ?? '');
                break;
            }
        }
        $this->assertSame('cs_select_preferencia_turno', $necesidadSkipModalidad);

        $modalidadRoutes = [];
        foreach ($byId['select_tipo_atencion']['always'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $guard = $row['guard'] ?? null;
            if (!is_array($guard)) {
                continue;
            }
            if (($guard['seguimiento_necesidad'] ?? null) === 'solicitar_turno'
                && ($guard['tipo_atencion'] ?? null) === 'teleconsulta') {
                $modalidadRoutes['teleconsulta_cp'] = (string) ($row['target'] ?? '');
            } elseif (($guard['seguimiento_necesidad'] ?? null) === 'solicitar_turno'
                && !isset($guard['tipo_atencion'])) {
                $modalidadRoutes['presencial_cp'] = (string) ($row['target'] ?? '');
            }
        }
        $this->assertSame('cs_select_dia_teleconsulta', $modalidadRoutes['teleconsulta_cp'] ?? null);
        $this->assertSame('cs_select_preferencia_turno', $modalidadRoutes['presencial_cp'] ?? null);

        $this->assertArrayHasKey('cs_select_care_plan', $byId);
        $this->assertTrue($byId['cs_select_care_plan']['meta']['review_prefilled'] ?? false);
        $this->assertArrayHasKey('triage_raiz', $byId);
        $this->assertTrue($byId['triage_raiz']['meta']['review_prefilled'] ?? false);
        $this->assertTrue($byId['select_pedido_acto']['meta']['review_prefilled'] ?? false);
        $this->assertArrayHasKey('cs_select_medicamentos', $byId);
        $this->assertArrayHasKey('cs_captura_ajuste_motivo', $byId);

        $medRoutes = self::targetsByGuard($byId['cs_select_medicamentos']['always'] ?? [], 'seguimiento_necesidad');
        $this->assertSame('cs_captura_ajuste_motivo', $medRoutes['solicitar_ajuste'] ?? null);
        $this->assertSame('', $medRoutes['renovar_medicacion'] ?? null);

        $this->assertSame(
            'clinical.care-plan.medicamentos-como-paciente',
            $byId['cs_select_medicamentos']['meta']['open_ui']['action_id'] ?? null
        );
        $this->assertSame(
            'clinical.care-plan.confirmar-renovacion-como-paciente',
            $byId['cs_select_medicamentos']['meta']['flow_submit']['action_id'] ?? null
        );
        $this->assertSame(
            'Solicitar renovaciÃ³n',
            $byId['cs_select_medicamentos']['meta']['flow_submit']['label'] ?? null
        );
        $this->assertSame(
            'renovacion',
            $byId['cs_select_medicamentos']['meta']['flow_submit']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste',
            $byId['cs_captura_ajuste_motivo']['meta']['composer_capture']['params']['medicacion_operacion'] ?? null
        );
        $this->assertSame(
            'ajuste_motivo',
            $byId['cs_captura_ajuste_motivo']['meta']['composer_capture']['draft_field'] ?? null
        );
    }

    /**
     * @param mixed $always
     * @return array<string, string>
     */
    private static function targetsByGuard($always, string $field): array
    {
        $out = [];
        if (!is_array($always)) {
            return $out;
        }
        foreach ($always as $row) {
            if (!is_array($row)) {
                continue;
            }
            $guard = $row['guard'] ?? null;
            $value = is_array($guard) ? ($guard[$field] ?? null) : null;
            if (is_string($value) && $value !== '') {
                $out[$value] = (string) ($row['target'] ?? '');
            }
        }

        return $out;
    }

    public function testSeguimientoCronicoAbreHubSinAncla(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'draft' => [
                'triage_raiz' => 'seguimiento_cronico',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('cs_hub', $response['subintent_id'] ?? null);
        $this->assertSame(
            'consultas-seguimiento.hub',
            $response['open_ui']['action_id'] ?? null
        );
        $this->assertArrayNotHasKey(
            'flow_submit',
            $response,
            'cs_hub no es terminal: no debe mostrar Confirmar y Enviar'
        );
    }

    public function testHubConAnclaCarePlanAvanzaANecesidad(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'cs_hub',
            'draft' => [
                'triage_raiz' => 'seguimiento_cronico',
                'control_hub_anchor' => 'cp:11',
                'control_hub_kind' => 'care_plan',
                'care_plan_id' => '11',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('cs_select_necesidad', $response['subintent_id'] ?? null);
        $this->assertArrayNotHasKey('flow_submit', $response);
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
        $this->assertSame('Solicitar renovaciÃ³n', $medicationStep['flow_submit']['label'] ?? null);
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
