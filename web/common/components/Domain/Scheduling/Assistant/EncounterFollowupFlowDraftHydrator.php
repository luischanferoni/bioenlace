<?php

namespace common\components\Domain\Scheduling\Assistant;

use common\components\Domain\Scheduling\Agenda\Application\UseCase\BuildControlSeguimientoHub;
use common\components\Domain\Scheduling\Assistant\Application\UseCase\SubmitEncounterFollowupIntake;
use common\components\Domain\Scheduling\Assistant\Application\UseCase\EncounterFollowupIntakeCatalogService;

/**
 * Enriquece el draft de Control/Seguimiento (dentro de Solicitar Atención) antes del SubIntentEngine.
 */
final class EncounterFollowupFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options ignorado
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $idPersona = (int) (\Yii::$app->user->getIdPersona() ?? 0);

        foreach ([
            'intake_tipo',
            'seguimiento_necesidad',
            'preferencia_profesional_turno',
            'care_plan_id',
            'encounter_id',
            'medication_request_ids',
            'medicacion_operacion',
            'ajuste_motivo',
            'mensaje',
            'control_hub_anchor',
            'control_hub_kind',
            'condition_ref',
            'condition_codigo',
            'condition_accion',
            'protocol_id',
            'protocol_action_outcome',
        ] as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v !== '') {
                continue;
            }
            $fromBody = trim((string) ($body[$key] ?? ''));
            if ($fromBody !== '') {
                $draft[$key] = $fromBody;
            }
        }

        (new BuildControlSeguimientoHub())->applyAnchorToDraft($draft);

        if (
            trim((string) ($draft[SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO] ?? '')) === ''
            && (int) ($draft['encounter_id'] ?? 0) > 0
        ) {
            $draft[SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO]
                = EncounterFollowupIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA;
        }

        if (
            trim((string) ($draft[SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO] ?? '')) === ''
            && (
                trim((string) ($draft[SubmitEncounterFollowupIntake::DRAFT_SEGUIMIENTO_NECESIDAD] ?? '')) !== ''
                || (int) ($draft['care_plan_id'] ?? 0) > 0
            )
        ) {
            $draft[SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO]
                = EncounterFollowupIntakeCatalogService::INTAKE_SEGUIMIENTO;
        }

        // Acción sobre condición → draft + outcome desde protocolo o default del hub.
        $condAccion = trim((string) ($draft['condition_accion'] ?? ''));
        if ($condAccion !== '') {
            $codigo = trim((string) ($draft['condition_codigo'] ?? $draft['condition_ref'] ?? ''));
            $protocolId = trim((string) ($draft['protocol_id'] ?? ''));
            $resolved = (new BuildControlSeguimientoHub())->resolveConditionAction(
                $codigo !== '' ? $codigo : null,
                $condAccion,
                $protocolId !== '' ? $protocolId : null
            );
            if ($resolved !== null) {
                foreach ($resolved['draft'] as $k => $v) {
                    if (trim((string) ($draft[$k] ?? '')) === '' && $v !== '') {
                        $draft[$k] = $v;
                    }
                }
                $draft['protocol_action_outcome'] = $resolved['outcome'];
                if ($resolved['protocol_id'] !== '') {
                    $draft['protocol_id'] = $resolved['protocol_id'];
                }
            }
        }

        (new SubmitEncounterFollowupIntake())->prepararDraft($draft, $idPersona);

        if (
            trim((string) ($draft[SubmitEncounterFollowupIntake::DRAFT_INTAKE_TIPO] ?? ''))
            === EncounterFollowupIntakeCatalogService::INTAKE_SEGUIMIENTO
            && (int) ($draft['care_plan_id'] ?? 0) > 0
            && trim((string) ($draft[SubmitEncounterFollowupIntake::DRAFT_SEGUIMIENTO_NECESIDAD] ?? '')) === ''
        ) {
            // care_plan sin necesidad: el paso select_necesidad sigue pendiente.
        }

        $body['draft'] = $draft;
    }
}
